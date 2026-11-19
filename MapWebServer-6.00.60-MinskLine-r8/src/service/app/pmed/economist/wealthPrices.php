<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../common/wealths.php'); resumeJsonRpc(); class pmed_economist_wealthPrices { private $db; public function readWealthPrices($params = null){ $db = $this->db ?: $this->db = connectDb(); $search = isEmptyProperty($params, 'search', ''); $date = isEmptyProperty($params, 'date', date('Y-m-d')); $date = date('Y-m-d', safe_strtotime($date)); $exactDate = isEmptyProperty($params, 'exactDate', false); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $structureId = isEmptyProperty($params, 'structureId', null); $showHistory = isEmptyProperty($params, 'showHistory', false); $historyObjectId = isEmptyProperty($params, 'historyObjectId', false); $wealthId = isEmptyProperty($params, 'wealthId', null); $sqlWhereDate = "WHERE onDate <= '$date'"; if ($exactDate){ $sqlWhereDate = "WHERE onDate = '$date'"; } $oneRecordNotHistoryJoin = ''; if (!$showHistory){ $oneRecordNotHistoryJoin = <<<EOT
                INNER JOIN (
                    SELECT * FROM (
                        SELECT wpi.pmsWealthPriceCode,
                            wpi.onDate,
                            wpi.matWealthCode,
                            ROW_NUMBER() OVER(PARTITION BY wi.matWealthObjectCode ORDER BY onDate DESC) "rn"
                        FROM pmsWealthPrice wpi
                        LEFT JOIN matWealth wi ON wi.matWealthCode = wpi.matWealthCode
                        $sqlWhereDate
                            AND enterpriseCode = :enterpriseId
                            AND enterpriseStructureCode = :structureId
                    ) "t"
                    WHERE t.rn = 1
                ) "pwp" ON pwp.pmsWealthPriceCode = wp.pmsWealthPriceCode
EOT;
} $sqlWhere = "1 = 1"; if ($search){ $sqlWhere = ""; $arrSearch = safe_explode(" ", $search); if ($arrSearch){ foreach ($arrSearch as $v){ if (!empty(trim($v))){ $sqlWhere = glueStrings($sqlWhere, trim($v), ":* & "); } } $sqlWhere = $sqlWhere . ":*"; $sqlWhere = "(w.textSearch @@ TO_TSQUERY('{$sqlWhere}') OR w.textSearch @@ TO_TSQUERY('simple', '{$sqlWhere}'))"; } } if ($showHistory){ $sqlWhere = $sqlWhere . ' AND wo.matWealthObjectCode = ' . $historyObjectId; } if ($wealthId) { $sqlWhere = <<<"EOT"
-- достаем не по хар-ки мц, а по самому объекту мц
wp.matWealthCode = any(select _MW_W.matWealthCode
from MatWealth _MW
     join MatWealth _MW_W on _MW_W.MatWealthObjectCode = _MW.matWealthObjectCode
where _MW.matWealthCode = {$wealthId})
EOT;
} $sql = <<<EOT
            SELECT wp.pmsWealthPriceCode id,
                    wp.enterpriseStructureCode "structureId",
                    wp.onDate "onDate",
                    wp.price,
                    COALESCE(NULLIF(w.commercialName, ''), wo.name) "materialCommercialName",
                    w.feature,
                    wo.name "materialName",
                    m.name "measureName",
                    wo.doseSize "doseSize",
                    m2.name "measureDoseSizeName",
                    wp.documentNumber "invoiceNumber",
                    wp.documentDate "invoiceDate",
                    wp.invoicePrice "invoicePrice",
                    wp.wholesalePercent "wholesaleMarkup",
                    wp.producerPrice "manufacturerPrice",
                    wp.tradingPercent "tradeMarkup",
                    wp.tradingPrice "tradeMarkupPrice",
                    wp.vatRate "vatRate",
                    wp.priceWithVat "priceWithVat",
                    wp.vat,
                    wp.tradingCost "tradeMarkupCost",
                    wp.retailRatio "retailRatio",
                    wp.matWealthCode "wealthId",
                    NULL "addNewWealthOption",
                    wo.matWealthObjectCode "matWealthObjectId",
                    wo.secondName "matWealthObjectSecondName",
                    NULL "newWealthCommercialName",
                    NULL "newWealthDescription",
                    NULL "newWealthManufacturer",
                    NULL "manufacturerCountryId",
                    NULL "newWealthBarcode",
                    fn_pmsMatWealthName(wp.matWealthCode, 1) "matWealthDisplayName",
                    CASE WHEN wp.active IS NULL THEN 1 ELSE NULL END "cancelPrice",
                    CASE WHEN wp.onDate = :date THEN 1 ELSE NULL END "todayPrice"
            FROM pmsWealthPrice wp
                $oneRecordNotHistoryJoin
            LEFT JOIN matWealth w ON w.matWealthCode = wp.matWealthCode
            LEFT JOIN matWealthObject wo ON wo.matWealthObjectCode = w.matWealthObjectCode
            LEFT JOIN matWealthMeasure m ON m.matWealthMeasureCode = wo.matWealthMeasureCode
            LEFT JOIN matWealthMeasure m2 on m2.matWealthMeasureCode = wo.matWealthMeasureDoseSizeCode
            WHERE $sqlWhere
                AND wp.enterpriseCode = :enterpriseId
                AND wp.enterpriseStructureCode = :structureId
            ORDER BY wp.onDate DESC, fn_pmsMatWealthName(wp.matWealthCode, 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ "date" => $date, "enterpriseId" => $enterpriseCode, "structureId" => $structureId ]); if ($wealthId) { $ret = $stmt->fetch(PDO::FETCH_OBJ); } else { $ret = $stmt->fetchAll(); } return $ret; } public function updateWealthPrices($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $clsWealths = new pmed_common_wealths; $sqlUpdate = <<<EOT
            UPDATE pmsWealthPrice
            SET onDate = CASE WHEN ? THEN ? ELSE onDate END,
                price = CASE WHEN ? THEN ? ELSE price END,
                documentNumber = CASE WHEN ? THEN ? ELSE documentNumber END,
                documentDate = CASE WHEN ? THEN ? ELSE documentDate END,
                invoicePrice = CASE WHEN ? THEN ? ELSE invoicePrice END,
                wholesalePercent = CASE WHEN ? THEN ? ELSE wholesalePercent END,
                producerPrice = CASE WHEN ? THEN ? ELSE producerPrice END,
                tradingPercent = CASE WHEN ? THEN ? ELSE tradingPercent END,
                tradingPrice = CASE WHEN ? THEN ? ELSE tradingPrice END,
                vatRate = CASE WHEN ? THEN ? ELSE vatRate END,
                priceWithVat = CASE WHEN ? THEN ? ELSE priceWithVat END,
                vat = CASE WHEN ? THEN ? ELSE vat END,
                tradingCost = CASE WHEN ? THEN ? ELSE tradingCost END,
                retailRatio = CASE WHEN ? THEN ? ELSE retailRatio END,
                matWealthCode = CASE WHEN ? THEN ? ELSE matWealthCode END,
                active = ?
            WHERE pmsWealthPriceCode = ?
EOT;
$stmtUpdate = $db->prepare($sqlUpdate); $db->beginTransaction(); try { foreach ($records as $row){ if (isEmptyProperty($row, 'addNewWealthOption')){ $wealthRow = new stdClass(); $wealthRow->name = $row->materialName; $wealthRow->secondName = $row->matWealthObjectSecondName; $wealthRow->wealthObjectId = $row->matWealthObjectId; $wealthRow->wealthId = null; $wealthRow->commercialName = $row->newWealthCommercialName; $wealthRow->feature = $row->newWealthDescription; $wealthRow->barcode = $row->newWealthBarcode; $wealthRow->manufacturerCountryId = $row->manufacturerCountryId; $wealthRow->manufacturer = $row->newWealthManufacturer; $clsWealths->checkExistWealth($wealthRow); $sqlWealth = <<<EOT
                        INSERT INTO matWealth
                            ( matWealthObjectCode, active, commercialName,
                            feature, barcode )
                        VALUES
                            ( ?, ?, ?,
                            ?, ? )
EOT;
$stmtWealth = $db->prepare($sqlWealth); $stmtWealth->execute([ $wealthRow->wealthObjectId, 1, isEmptyProperty($wealthRow, 'commercialName'), isEmptyProperty($wealthRow, 'feature'), isEmptyProperty($wealthRow, 'barcode') ]); $row->wealthId = $db->lastInsertId('seq_matWealth'); $sqlManufacturer = <<<EOT
                        INSERT INTO matWealthManufacturer
                            (matWealthCode, countryCode, manufacturer)
                        VALUES (?, ?, ?)
EOT;
$stmtManufacturer = $db->prepare($sqlManufacturer); $stmtManufacturer->execute([ $row->wealthId, $row->manufacturerCountryId, $row->newWealthManufacturer ]); $this->createWealthPrices($row); }else if (isEmptyProperty($row, 'addNewRecordBasedOnOld')){ $this->createWealthPrices($row); }else{ $active = 1; if (isUndefinedProperty($row, 'cancelPrice') == 1){ $active = null; $row->price = null; $row->invoiceNumber = null; $row->invoiceDate = null; $row->invoicePrice = null; $row->wholesaleMarkup = null; $row->manufacturerPrice = null; $row->tradeMarkup = null; $row->tradeMarkupPrice = null; $row->vatRate = null; $row->priceWithVat = null; $row->vat = null; $row->tradeMarkupCost = null; $row->retailRatio = null; } $stmtUpdate->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'price'), isUndefinedProperty($row, 'price'), propertyExistsSQL($row, 'invoiceNumber'), isUndefinedProperty($row, 'invoiceNumber'), propertyExistsSQL($row, 'invoiceDate'), isUndefinedProperty($row, 'invoiceDate'), propertyExistsSQL($row, 'invoicePrice'), isUndefinedProperty($row, 'invoicePrice'), propertyExistsSQL($row, 'wholesaleMarkup'), isUndefinedProperty($row, 'wholesaleMarkup'), propertyExistsSQL($row, 'manufacturerPrice'), isUndefinedProperty($row, 'manufacturerPrice'), propertyExistsSQL($row, 'tradeMarkup'), isUndefinedProperty($row, 'tradeMarkup'), propertyExistsSQL($row, 'tradeMarkupPrice'), isUndefinedProperty($row, 'tradeMarkupPrice'), propertyExistsSQL($row, 'vatRate'), isUndefinedProperty($row, 'vatRate'), propertyExistsSQL($row, 'priceWithVat'), isUndefinedProperty($row, 'priceWithVat'), propertyExistsSQL($row, 'vat'), isUndefinedProperty($row, 'vat'), propertyExistsSQL($row, 'tradeMarkupCost'), isUndefinedProperty($row, 'tradeMarkupCost'), propertyExistsSQL($row, 'retailRatio'), isUndefinedProperty($row, 'retailRatio'), propertyExistsSQL($row, 'wealthId'), isUndefinedProperty($row, 'wealthId'), $active, $row->id ]); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function createWealthPrices($records){ $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); if (isEmptyProperty($records, 'updateRecord')){ $this->updateWealthPrices($records); return true; } if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            INSERT INTO pmsWealthPrice
                ( onDate, price, documentNumber,
                    documentDate, invoicePrice, wholesalePercent,
                    producerPrice, tradingPercent, tradingPrice,
                    vatRate, priceWithVat, vat,
                    tradingCost, retailRatio, matWealthCode,
                    enterpriseCode, enterpriseStructureCode, active )
            VALUES ( ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ? )

EOT;
$stmt = $db->prepare($sql); foreach ($records as $row){ $active = 1; if (isEmptyProperty($row, 'cancelPrice') == 1){ $active = null; } $stmt->execute([ isUndefinedProperty($row, 'onDate'), isUndefinedProperty($row, 'price'), isUndefinedProperty($row, 'invoiceNumber'), isUndefinedProperty($row, 'invoiceDate'), isUndefinedProperty($row, 'invoicePrice'), isUndefinedProperty($row, 'wholesaleMarkup'), isUndefinedProperty($row, 'manufacturerPrice'), isUndefinedProperty($row, 'tradeMarkup'), isUndefinedProperty($row, 'tradeMarkupPrice'), isUndefinedProperty($row, 'vatRate'), isUndefinedProperty($row, 'priceWithVat'), isUndefinedProperty($row, 'vat'), isUndefinedProperty($row, 'tradeMarkupCost'), isUndefinedProperty($row, 'retailRatio'), isUndefinedProperty($row, 'wealthId'), $enterpriseCode, isUndefinedProperty($row, 'structureId'), $active ]); } return true; } public function checkIfRecordExists($params){ $db = $this->db ?: $this->db = connectDb(); $params->onDate = date('Y-m-d', safe_strtotime($params->onDate)); $params->invoiceDate = date('Y-m-d', safe_strtotime($params->invoiceDate)); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT pmsWealthPriceCode id
            FROM pmsWealthPrice
            WHERE enterpriseCode = ?
                AND enterpriseStructureCode = ?
                AND onDate = ?
                AND matWealthCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseCode, $params->structureId, $params->onDate, $params->wealthId ]); $ret = $stmt->fetchAll(); return $ret; } public function deleteWealthPrices($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            DELETE FROM pmsWealthPrice
            WHERE pmsWealthPriceCode = ?
EOT;
$stmt = $db->prepare($sql); foreach($records as $row){ $stmt->execute([$row->id]); } return true; } } handleJsonRpc('pmed_economist_wealthPrices');