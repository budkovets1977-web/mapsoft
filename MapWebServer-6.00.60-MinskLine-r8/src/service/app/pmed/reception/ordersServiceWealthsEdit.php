<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../reception/orderServices.php'); require_once(__DIR__ . '/../economist/wealthPrices.php'); resumeJsonRpc(); class pmed_reception_ordersServiceWealthsEdit { private $db; public function ordersServiceWealthsController($params) { $db = $this->db ?: $this->db = connectDb(); $records = isUndefinedProperty($params, 'records'); $db->beginTransaction(); try { foreach ($records as $row) { if (isUndefinedProperty($row, 'crudState') === 'C') { $this->createOrdersServiceWealthsEdit($row); } else if (isUndefinedProperty($row, 'crudState') === 'U') { $this->updateOrdersServiceWealthsEdit($row); } else if (isUndefinedProperty($row, 'crudState') === 'D') { $this->deleteOrdersServiceWealthsEdit($row); } } if ($params->records) { unset($params->records); } $this->updateOrderService($params); $db->commit(); return true; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function readOrdersServiceWealthsEdit($params) { $db = $this->db ?: $this->db = connectDb(); $orderServiceId = isEmptyProperty($params, 'orderServiceId'); $sql = <<<"EOT"
SELECT
    PmsOrderServiceWealthCode "id",
    PmsOrderServiceCode "orderServiceId",
    MatWealthCode "wealthId",
    Amount "amount",
    Price "price",
    Vat "vat",
    TradingCost "tradingCost",
    Summ "summ",
    VatSum "vatSum",
    TradingSum "tradingSum"
FROM
    PmsOrderServiceWealth
WHERE PmsOrderServiceCode = $orderServiceId
EOT;
return selectSql($db, $sql); } private function createOrdersServiceWealthsEdit($record) { $db = $this->db ?: $this->db = connectDb(); $clsWealthPrice = new pmed_economist_wealthPrices; $params = new stdClass; $params->orderServiceId = isEmptyProperty($record, 'orderServiceId'); $params->wealthId = isEmptyProperty($record, 'wealthId'); $params->structureId = isEmptyProperty($record, 'structureId'); $wealthPrice = $clsWealthPrice->readWealthPrices($params); if (!$wealthPrice) { $wealthPrice = new stdClass; $wealthPrice->price = null; $wealthPrice->vat = null; $wealthPrice->tradeMarkupCost = null; } $sql = <<<"EOT"
INSERT INTO PmsOrderServiceWealth
    (PmsOrderServiceCode, MatWealthCode, Amount, Price, Vat, TradingCost, Summ, VatSum, TradingSum)
VALUES (?,?,?,?,?,?,?,?,?)
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute([ isUndefinedProperty($record, 'orderServiceId'), isUndefinedProperty($record, 'wealthId'), isUndefinedProperty($record, 'amount'), isUndefinedProperty($wealthPrice, 'price', 0), isUndefinedProperty($wealthPrice, 'vat', 0), isUndefinedProperty($wealthPrice, 'tradeMarkupCost', 0), isUndefinedProperty($wealthPrice, 'price', 0) * $record->amount, isUndefinedProperty($wealthPrice, 'vat', 0) * $record->amount, isUndefinedProperty($wealthPrice, 'tradeMarkupCost', 0) * $record->amount ]); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function updateOrdersServiceWealthsEdit($record) { $db = $this->db ?: $this->db = connectDb(); $clsWealthPrice = new pmed_economist_wealthPrices; $params = new stdClass; $params->orderServiceId = isEmptyProperty($record, 'orderServiceId'); $params->wealthId = isEmptyProperty($record, 'wealthId'); $params->structureId = isEmptyProperty($record, 'structureId'); $wealthPrice = $clsWealthPrice->readWealthPrices($params); if (!$wealthPrice) { $wealthPrice = new stdClass; $wealthPrice->price = null; $wealthPrice->vat = null; $wealthPrice->tradeMarkupCost = null; } $sql = <<<"EOT"
UPDATE PmsOrderServiceWealth SET
    MatWealthCode = case when ? = 1 then ? else MatWealthCode end,
    Amount = case when ? = 1 then ? else Amount end,
    Price = case when ? = 1 then ? else Price end,
    Vat = case when ? = 1 then ? else Vat end,
    TradingCost = case when ? = 1 then ? else TradingCost end,
    Summ = case when ? = 1 then ? else Summ end,
    VatSum = case when ? = 1 then ? else VatSum end,
    TradingSum = case when ? = 1 then ? else TradingSum end
WHERE PmsOrderServiceWealthCode = ?
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute([ propertyExistsSQL($record, 'wealthId'), isUndefinedProperty($record, 'wealthId'), propertyExistsSQL($record, 'amount'), isUndefinedProperty($record, 'amount'), propertyExistsSQL($wealthPrice, 'price'), isUndefinedProperty($wealthPrice, 'price', 0), propertyExistsSQL($wealthPrice, 'vat'), isUndefinedProperty($wealthPrice, 'vat', 0), propertyExistsSQL($wealthPrice, 'tradeMarkupCost'), isUndefinedProperty($wealthPrice, 'tradeMarkupCost', 0), propertyExistsSQL($wealthPrice, 'price'), isUndefinedProperty($wealthPrice, 'price', 0) * isUndefinedProperty($record, 'amount', 0), propertyExistsSQL($wealthPrice, 'vat'), isUndefinedProperty($wealthPrice, 'vat', 0) * isUndefinedProperty($record, 'amount', 0), propertyExistsSQL($wealthPrice, 'tradeMarkupCost'), isUndefinedProperty($wealthPrice, 'tradeMarkupCost', 0) * isUndefinedProperty($record, 'amount', 0), $record->id ]); } catch (Throwable $e) { throw $e; } return true; } private function deleteOrdersServiceWealthsEdit($record) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'PmsOrderServiceWealth', 'PmsOrderServiceWealthCode', $record); } private function updateOrderService($params) { $db = $this->db ?: $this->db = connectDb(); $oderServiceId = isEmptyProperty($params, 'orderServiceId', 0); $orderServiceCount = isEmptyProperty($params, 'orderServiceCount', 0.0); $sql = <<<"EOT"
update pmsOrderService set
       --- услуга
       amount = {$orderServiceCount},
       summ = (summ / amount) * {$orderServiceCount},
       vatsum = (vatsum / amount) * {$orderServiceCount},
       resultsum = (summ / amount) * {$orderServiceCount} + wealthsum,
       --- тариф
       tariffSum = (tariffSum / amount) * {$orderServiceCount},
       tariffVATSum = (tariffVATSum / amount) * {$orderServiceCount},
       --- материалы
       materialSum = (materialSum / amount) * {$orderServiceCount},
       materialSumWithoutVAT = (materialSumWithoutVAT / amount) * {$orderServiceCount},
       materialSumWithVAT = (materialSumWithVAT / amount) * {$orderServiceCount},
       --- услуги сторонних организаций
       asideServiceSum = (asideServiceSum / amount) * {$orderServiceCount},
       asideServiceSumVat = (asideServiceSumVat / amount) * {$orderServiceCount}
where pmsOrderServiceCode = {$oderServiceId}
EOT;
executeSql($db, $sql); return true; } } handleJsonRpc('pmed_reception_ordersServiceWealthsEdit'); 