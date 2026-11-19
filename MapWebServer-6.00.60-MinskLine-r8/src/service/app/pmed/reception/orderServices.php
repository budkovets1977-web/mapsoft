<?php
require_once(__DIR__ . '/../../init.php'); class pmed_reception_orderServices { private $db; public function readOrderServices($params){ $db = $this->db ?: $this->db = connectDb(); $orderId = isEmptyProperty($params, 'orderId'); $total = empty(isEmptyProperty($params, 'noTotal')); $sql = <<<EOT
            SELECT
                os.pmsOrderServiceCode "orderServiceId",
                1 as "rowLevel",
                os.pmsOrderServiceCodeParent "orderServiceParentId",
                os.pmsOrderCode "orderId",
                os.medDoctorCode "doctorId",
                os.pmsPriceListCode "priceListId",
                pl.fullCode "serviceFullCode",
                pl.name "name",
                os.amount,
                os.tariff,
                os.tariffSum "tariffSum",
                os.tariffVatPercent "tariffVatPercent",
                os.tariffVatSum "tariffVatSum",
                os.materialSum "materialSum",
                os.materialSumWithoutVat "materialSumWithoutVat",
                os.materialSumWithVat "materialSumWithVat",
                os.asideServiceSum "asideServiceSum",
                os.asideServiceSumVat "asideServiceSumVat",
                os.summ "summ",
                os.vatSum "vatSum",
                os.wealthSum "wealthSum",
                os.wealthVatSum "wealthVatSum",
                os.wealthTradingSum "wealthTradingSum",
                os.resultSum "resultSum",
                os.proceedTime "proceedTime",
                1 "editVisible",
                NULL "serviceWealthId",
                NULL "matWealthName",
                0 "total"
            FROM pmsOrderService os
            JOIN pmsPriceList pl on pl.pmsPriceListCode = os.pmsPriceListCode
            WHERE os.pmsOrderCode = :orderId

            UNION ALL

            SELECT
                sw.pmsOrderServiceCode "orderServiceId",
                2 as "rowLevel",
                NULL "orderServiceParentId",
                os.pmsOrderCode "orderId",
                os.medDoctorCode "doctorId",
                NULL "priceListId",
                pl.fullCode "serviceFullCode",
                fn_pmsMatWealthName(sw.matWealthCode, 1) "name",
                sw.amount "amount",
                NULL "tariff",
                NULL "tariffSum",
                NULL "tariffVatPercent",
                NULL "tariffVatSum",
                NULL "materialSum",
                NULL,
                NULL,
                NULL,
                NULL,
                sw.summ "summ",
                NULL "vatSum",
                NULL "wealthSum",
                NULL "wealthVatSum",
                NULL "wealthTradingSum",
                NULL "resultSum",
                NULL "proceedTime",
                0 "editVisible",
                sw.pmsOrderServiceWealthCode "serviceWealthId",
                fn_pmsMatWealthName(sw.matWealthCode, 1) "matWealthName",
                0 "total"
            FROM pmsOrderServiceWealth sw
            LEFT JOIN pmsOrderService os ON os.pmsOrderServiceCode = sw.pmsOrderServiceCode
            JOIN pmsPriceList pl ON pl.pmsPriceListCode = os.pmsPriceListCode
            WHERE os.pmsOrderCode = :orderId
EOT;
$totalSql = <<<EOT
            UNION ALL
            SELECT
                NULL "orderServiceId",
                1 as "rowLevel",
                NULL "orderServiceParentId",
                NULL "orderId",
                NULL "doctorId",
                NULL "priceListId",
                NULL "serviceFullCode",
                E'Итоговая стоимость' "name",
                NULL "amount",
                NULL "tariff",
                NULL "tariffSum",
                NULL "tariffVatPercent",
                NULL "tariffVatSum",
                NULL "materialSum",
                NULL,
                NULL,
                NULL,
                NULL,
                od.resultSum "summ",
                NULL "vatSum",
                NULL "wealthSum",
                NULL "wealthVatSum",
                NULL "wealthTradingSum",
                NULL "resultSum",
                NULL "proceedTime",
                0 "editVisible",
                NULL "serviceWealthId",
                NULL "matWealthName",
                1 "total"
            FROM pmsOrder od
            WHERE od.pmsOrderCode = :orderId
                AND ((SELECT COUNT(*) FROM pmsOrderService WHERE pmsOrderCode = :orderId) != 0)
EOT;
$orderSql = <<<EOT
            ORDER BY "orderServiceId", "total", "serviceFullCode", "rowLevel", "matWealthName"
EOT;
if ($total) { $sql .= $totalSql; } $sql .= $orderSql; $stmt = $db->prepare($sql); $stmt->execute([ "orderId" => $orderId ]); $ret = $stmt->fetchAll(); return $ret; } public function createOrderServices($records){ $db = $this->db ?: $this->db = connectDb(); $idArr = []; if (is_object($records)) { $records = [$records]; } $sqlService = <<<"EOT"
INSERT INTO pmsOrderService (pmsOrderServiceCodeParent, pmsOrderCode, medDoctorCode, pmsPriceListCode, amount, tariff, tariffSum, tariffVatPercent, tariffVatSum,
                             materialSum, materialSumWithVat, materialSumWithoutVat, asideServiceSum, asideServiceSumVat, summ, vatSum, wealthSum, wealthVatSum, wealthTradingSum, resultSum, proceedTime)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtService = $db->prepare($sqlService); $sqlWealth = <<<"EOT"
INSERT INTO pmsOrderServiceWealth (pmsOrderServiceCode, matWealthCode, amount, price, summ, vat, vatSum, tradingCost, tradingSum)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $record) { if (empty(isEmptyProperty($record, 'wealthId'))) { $serviceParentId = array_key_exists((int)isEmptyProperty($record, 'parentPriceListId'), $idArr) ? $idArr[(int)isEmptyProperty($record, 'parentPriceListId')] : null; $stmtService->execute([ $serviceParentId, isUndefinedProperty($record, 'orderId'), isEmptyProperty($record, 'doctorId'), isUndefinedProperty($record, 'priceListId'), isUndefinedProperty($record, 'amount'), isUndefinedProperty($record, 'tariff'), isUndefinedProperty($record, 'tariffSum'), isUndefinedProperty($record, 'tariffVatPercent'), isUndefinedProperty($record, 'tariffVatSum'), isUndefinedProperty($record, 'materialSum'), isUndefinedProperty($record, 'materialSumWithVat'), isUndefinedProperty($record, 'materialSumWithoutVat'), isUndefinedProperty($record, 'asideServiceSum'), isUndefinedProperty($record, 'asideServiceSumVat'), isUndefinedProperty($record, 'summ'), isUndefinedProperty($record, 'vatSum'), isUndefinedProperty($record, 'wealthSum'), isUndefinedProperty($record, 'wealthVatSum'), isUndefinedProperty($record, 'wealthTradingSum'), isUndefinedProperty($record, 'resultSum'), isUndefinedProperty($record, 'proceedTime') ]); $id = $db->lastInsertId('seq_PmsOrderService'); $idArr[(int)isEmptyProperty($record, 'priceListId')] = $id; } else { if (array_key_exists((int)isEmptyProperty($record, 'parentPriceListId'), $idArr)) { $orderServiceId = $idArr[(int)isEmptyProperty($record, 'parentPriceListId')]; $stmtWealth->execute([ $orderServiceId, isEmptyProperty($record, 'wealthId'), isUndefinedProperty($record, 'amount'), isUndefinedProperty($record, 'wealthPrice'), isUndefinedProperty($record, 'wealthSum'), isUndefinedProperty($record, 'wealthVat'), isUndefinedProperty($record, 'wealthVatSum'), isUndefinedProperty($record, 'wealthTraidingCost'), isUndefinedProperty($record, 'wealthTraidingSum') ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateOrderServices($records){ $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsOrderService', 'pmsOrderServiceCode', $records, "pmsOrderServiceCodeParent, pmsOrderCode, medDoctorCode, pmsPriceListCode, amount, tariff, tariffSum, tariffVatPercent, tariffVatSum,
             materialSum, materialVatPercent, outOrgSum, outOrgVatSum, summ, vatSum, wealthSum, wealthVatSum, wealthTradingSum, resultSum, proceedTime", "parentId, orderId, doctorId, priceListId, amount, tariff, tariffSum, tariffVatPercent, tariffVatSum,
             materialSum, materialVatPercent, outOrgSum, outOrgVatSum, summ, vatSum, wealthSum, wealthvatSum, wealthtradingSum, resultSum, proceedTime" ); } public function deleteOrderServices($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sqlService = <<<EOT
            DELETE FROM pmsOrderService
            WHERE pmsOrderServiceCode = ?
EOT;
$stmtService = $db->prepare($sqlService); $sqlWealthAll = <<<EOT
            DELETE FROM pmsOrderServiceWealth
            WHERE pmsOrderServiceCode = ?
EOT;
$stmtWealthAll = $db->prepare($sqlWealthAll); $sqlWealth = <<<EOT
            DELETE FROM pmsOrderServiceWealth
            WHERE pmsOrderServiceWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row){ if ($row->rowLevel == 1){ $stmtWealthAll->execute([$row->orderServiceId]); $stmtService->execute([$row->orderServiceId]); } else { $stmtWealth->execute([$row->serviceWealthId]); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function doSplitOrderServices($params){ $db = $this->db ?: $this->db = connectDb(); $orderId = isEmptyProperty($params, 'orderId'); $srvArr = isEmptyProperty($params, 'orderServices'); $res = null; if (!empty($orderId) && !empty($srvArr)) { $srvList = implode(',', $srvArr); $sql = <<<EOT
update pmsOrderService set
    pmsOrderCode = :orderId
where
    pmsOrderServiceCode in ({$srvList})
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ "orderId" => $orderId ]); $res = true; } return $res; } } handleJsonRpc('pmed_reception_orderServices');