<?php
require_once(__DIR__ . '/../../init.php'); class med_lab_analyzer { const TASK_NAME = 'medLabAnalyzer'; const PAGE_SIZE = 30; private $db; public function readAnalyzer($params = null) { $db = $this->db ?: $this->db = connectDb(); $active = (int) isUndefinedProperty($params, 'active'); $sql = <<<"EOT"
SELECT
 DV.medLabDeviceCode as id,
 DV.Code as Code,
 DV.Hash as Hash,
 DV.medLabDriverCode as "driverCode",
 DR.Name as "driverName",
 DV.Model as Model,
 DV.Name as Name,
 DV.SerialNumber as "serialNumber",
 DV.Settings as Settings,
 DV.Active as Active
FROM medlabdevice AS DV JOIN medlabdriver AS DR ON DR.medlabdrivercode = DV.medlabdrivercode
WHERE COALESCE( DV.Active, 0 ) = $active
ORDER BY 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readAnalyzersType($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
 medLabDriverCode "id",
 Name "name",
 BarCodeType "typeBarCode"
FROM MedLabDriver
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateAnalyzersType($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'id'); return $id ? updateSql($db, 'MedLabDriver', 'MedLabDriverCode', $params, 'BarCodeType', 'typeBarCode') : false; } public function readStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
medStudyCode "id",
name "name"
from medstudy
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createAnalyzersTypeStudy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        INSERT INTO MedLabDriverTemplate (medStudyCode,medLabDriverCode)
        values (?,?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'studyId'), isEmptyProperty($row, 'driverId') ]); $param = new stdClass; $param->id = (int) $db->lastInsertId('seq_MedLabDriverTemplate'); $ret = $this->readAnalyzersTypeStudy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateAnalyzersTypeStudy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        UPDATE MedLabDriverTemplate
        set
        medStudyCode = case when ? = 1 then ? else medStudyCode end,
        medLabDriverCode = case when ? = 1 then ? else medLabDriverCode end
        WHERE
        MedLabDriverTemplateCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'studyId'), isEmptyProperty($row, 'studyId'), propertyExistsSQL($row, 'driverId'), isEmptyProperty($row, 'driverId'), isEmptyProperty($row, 'id') ]); $param = new stdClass(); $param->id = $row->id; $ret = $this->readAnalyzersTypeStudy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteAnalyzersTypeStudy($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLabDriverTemplate', 'MedLabDriverTemplateCode', $records); } public function readAnalyzersTypeStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $sql = <<<"EOT"
SELECT
  DT.medLabDriverTemplateCode "id",
  DT.medLabDriverCode "driverId",
  DT.medStudyCode "studyId",
  MS.Name "studyName"
FROM medlabdrivertemplate AS DT LEFT OUTER JOIN medStudy AS MS ON MS.medStudyCode = DT.medStudyCode
where DT.medLabDriverTemplateCode = $id or 0 = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getMapLabServiceSettings($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = 17; $sql = <<<EOT
            SELECT Data::TEXT
            FROM SysSetupFunc
            WHERE Type = $type
            ORDER BY SysSetupFuncCode
            LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $result = $stmt->fetch(); $ret = is_array($result) ? $result['data'] : null; return $ret; } public function sendDeviceHash($params = null) { $db = $this->db ?: $this->db = connectDb(); $this->log("Отправка хэша анализаторов"); $sql = <<<"EOT"
SELECT Code, Hash FROM medLabDevice WHERE Active = 1 ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $result = $stmt->fetchAll(); if (count($result) != 0) { $setting = $this->getMapLabServiceSettings(); $url = safe_explode("\n", $setting)[2]; $url = safe_explode("=", $url)[1] . "set=004"; foreach ($result as $key => $res) { $key = $key + 1; $url .= "&DeviceCode" . $key . "=" . $res['code'] . "&HashCode" . $key . "=" . $res['hash']; } $params = new stdClass(); $params->url = $url; $params->proxy = null; $response = httpGET($params); $this->log("Хэши отправлены!"); } else { $this->log("Нет данных для отправки!"); } return true; } public function callTestMapLabService($params = null) { $db = $this->db ?: $this->db = connectDb(); $this->log("Проверка подклчения"); $response = httpGET($params); if (isset($response['status'][1])) { switch ($response['status'][1]) { case "200": $message = "Подключение прошло успешно!"; $this->log("Подключение прошло успешно"); break; default: $message = "При подключении произошла ошибка!"; $this->log("При подключении произошла ошибка!"); } } else { $message = "При подключении произошла ошибка!"; $this->log("При подключении произошла ошибка!"); } return $message; } public function callMapLabService($params = null) { $db = $this->db ?: $this->db = connectDb(); $this->log("Получение результатов"); $response = httpGET($params); switch ($response['status'][1]) { case "200": $message = "Данные получены!"; $this->log("Данные получены"); break; default: $message = "При подключении произошла ошибка!"; $this->log("При получении результатов произошла ошибка"); } if ($response) { $xml = new SimpleXMLElement($response['body']); if (!$xml->MeasureItem) { $message = "При обращении к MapLabService получен пустой ответ."; $this->log("При обращении к MapLabService получен пустой ответ."); } else { $this->log("Обработка данных..."); $items = array(); foreach ($xml->MeasureItem as $meas) { $jsonArray = array(); $jsonObject = new stdClass(); foreach ($meas->ValueItem as $val) { $item = $val->Label->__toString(); $jsonObject->$item=$val->Value->__toString(); } $json = json_encode($jsonObject, TRUE); $meas->resultJson = $json; array_push($items, $meas); } $this->log("Данные успешно обработанны"); return $items; } } else { $message = "Не удалось подключиться к MapLabService!"; $this->log("Не удалось подключиться к MapLabService!"); } return $message; } public function getMapLabServiceDevice($params = null) { $db = $this->db ?: $this->db = connectDb(); $this->log("Получение списка анализаторов"); $response = httpGET($params); switch ($response['status'][1]) { case "200": $message = "Данные получены!"; $this->log("Данные получены"); break; default: $message = "При подключении произошла ошибка!"; $this->log("При получении анализаторов произошла ошибка"); } if ($response) { $xml = new SimpleXMLElement($response['body']); if (!$xml->DeviceItem) { $message = "Список анализаторов пуст!"; $this->log("Список анализаторов пуст"); } else { $this->log("Обработка списка анализаторов..."); $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT MedLabDriverCode, Name FROM MedLabDriver
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $drivers = $stmt->fetchAll(); $db->beginTransaction(); try { $this->log("Обновление списка типов анализаторов..."); foreach ($xml->DeviceItem as $device) { $device = (array) $device; $flag = false; foreach ($drivers as $driver) { if ($driver['name'] === $device['ClassName']) { $flag = true; break; } } if (!$flag) { $sql = <<<"EOT"
INSERT INTO MedLabDriver (Name) VALUES (?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $device['ClassName'] ]); } $this->log("Обновление списка типов анализаторов завершено!"); $sql = <<<"EOT"
SELECT
 DV.medLabDeviceCode as id,
 DV.Code as Code,
 DV.Hash as Hash,
 DV.medLabDriverCode as "driverCode",
 DR.Name as "driverName",
 DV.Model as Model,
 DV.Name as Name,
 DV.SerialNumber as "serialNumber",
 DV.Settings as Settings,
 DV.Active as Active
FROM medlabdevice AS DV JOIN medlabdriver AS DR ON DR.medlabdrivercode = DV.medlabdrivercode
ORDER BY 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $devices = $stmt->fetchAll(); $flag = false; $this->log("Деакцивация неактивных анализаторов..."); foreach ($devices as $deviceDb) { if ((int) $device['Code'] === (int) $deviceDb['code']) { $flag = true; $activeFlag = $deviceDb['active']; break; } } foreach ($devices as $deviceDb) { if ((int) $device['Code'] != (int) $deviceDb['code']) { $sql = <<<"EOT"
UPDATE MedLabDevice SET Active = null WHERE MedLabDeviceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $deviceDb['id'] ]); } } $this->log("Деакцивация неактивных анализаторов закончена!"); if (!$flag) { $driverCode = null; $sql = <<<"EOT"
SELECT MedLabDriverCode, Name FROM MedLabDriver
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $drivers = $stmt->fetchAll(); foreach ($drivers as $driver) { if ($driver['name'] === $device['ClassName']) { $driverCode = $driver['medlabdrivercode']; break; } } $sql = <<<"EOT"
INSERT INTO MedLabDevice ( code, medlabdrivercode, model, name, serialnumber, settings, active ) Values(?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $device['Code'] ? $device['Code'] : null, $driverCode, $device['Model'] ? $device['Model'] : null, $device['Name'] ? $device['Name'] : null, $device['SerialNum'] ? $device['SerialNum'] : null, $device['ChannelSettings'] ? $device['ChannelSettings'] : null, 1 ]); } else { if ($activeFlag === 0 || !$activeFlag) { $sql = <<<"EOT"
UPDATE MedLabDevice SET Active = 1 WHERE MedLabDeviceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $deviceDb['id'] ]); } } } $db->commit(); $this->log("Обработка списка анализаторов успешно заверешна!"); } catch (Throwable $e) { $db->rollback(); $this->log("Ошибка при обработке списка анализаторов... Отмена изменений"); throw $e; } $this->sendDeviceHash(); return true; } } else { $message = "Не удалось подключиться к MapLabService!"; $this->log("Не удалось подключиться к MapLabService!"); } return $message; } public function createOrUpdateMapLabService($params) { $db = $this->db ?: $this->db = connectDb(); $exchangeEnabled = isEmptyProperty($params, 'exchangeEnabled'); $url = isEmptyProperty($params, 'url'); $type = 17; $data = "[Settings]  \n ExchangeEnabled=" . (string) $exchangeEnabled . " \n URL=" . $url; $sql = <<<"EOT"
SELECT count(*) FROM sysSetupFunc WHERE Type = $type;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $count = fetchValue($stmt, 'count'); if ($count != 0) { $sql = <<<"EOT"
UPDATE sysSetupFunc SET Data = '$data' WHERE Type = $type
EOT;
} else { $sql = <<<"EOT"
INSERT INTO sysSetupFunc (Data,Type) VALUES ('$data',$type)
EOT;
} $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readAnalyzerType2() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            SELECT
                MedLabDriverCode                "id",
                COALESCE(ProtocolName, Name)    "name",
                BarcodeType                     "barcodeType"
            FROM MedLabDriver
            ORDER BY 2
            SQL;
return selectSql($db, $sql); } public function readAnalyzer2() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            SELECT
                DV.MedLabDeviceCode                 "id",
                DV.Code                             "code",
                DV.Hash                             "hash",
                DV.MedLabDriverCode                 "driverId",
                DR.Name                             "driverName",
                DR.ProtocolName                     "driverProtocolName",
                COALESCE(DR.ProtocolName, DR.Name)  "driverDisplayName",
                DV.Name                             "name",
                DV.Model                            "model",
                DV.SerialNumber                     "serialNumber",
                DV.Settings                         "settings",
                DV.Active                           "active"
            FROM MedLabDevice DV
            LEFT JOIN MedLabDriver DR ON DR.MedLabDriverCode = DV.MedLabDriverCode
            ORDER BY 1
            SQL;
return selectSql($db, $sql); } public function readIndicators($params = null) { $db = $this->db ?: $this->db = connectDb(); $medLabDriverId = intval(isEmptyProperty($params, 'medLabDriverId', 0)); $medLabDeviceId = intval(isEmptyProperty($params, 'medLabDeviceId', 0)); $sql = <<<SQL
            SELECT
                DT.MedLabDeviceTestCode     "id",                   -- 1
                SUBSTR(D.Model || ', '
                        || D.SerialNumber
                        || ' (' || COALESCE(Drv.ProtocolName, Drv.Name) || ')',
                        1, 254)             "deviceName",           -- 2
                D.MedLabDeviceCode          "medLabDeviceId",       -- 3
                Drv.MedLabDriverCode        "medLabDriverId",       -- 4
                DT.ProtocolName             "protocolName",         -- 5
                DT.TemplateName             "templateName",         -- 6
                DT.ShortName                "shortName",            -- 7
                DT.UnitName                 "unitName",             -- 8
                DT.Name                     "name",                 -- 9
                DT.Type                     "type",                 -- 10
                DT.Accuracy                 "accuracy",             -- 11
                DT.CoefficientB             "coefficientB",         -- 12
                DT.CoefficientK             "coefficientK",         -- 13
                DT.Active                   "active",               -- 14
                COALESCE((
                    SELECT 1
                    FROM MedLabDeviceTestNorm N
                    WHERE COALESCE(N.Active, 0) = 1
                        AND N.MedLabDeviceTestCode = DT.MedLabDeviceTestCode
                    LIMIT 1
                ), 0)                       "normExists"            -- 15
            FROM MedLabDeviceTest DT, MedLabDriver Drv, MedLabDevice D
            WHERE DT.MedLabDeviceCode = D.MedLabDeviceCode
                AND D.MedLabDriverCode = Drv.MedLabDriverCode
                AND (:medLabDeviceId = 0 OR D.MedLabDeviceCode = :medLabDeviceId)
                AND (:medLabDriverId = 0 OR Drv.MedLabDriverCode = :medLabDriverId)
            ORDER BY 2, 9
            SQL;
$ret = selectSql($db, $sql, [ ':medLabDeviceId' => $medLabDeviceId, ':medLabDriverId' => $medLabDriverId ]); return $ret; } public function updateIndicators($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MedLabDeviceTest', 'MedLabDeviceTestCode', $records, "Active", "active"); } public function updateIndicatorValues($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
        UPDATE medlabdevicetest
        SET
            templateName   = CASE WHEN ? = 1 THEN ? ELSE templateName END,
            accuracy       = CASE WHEN ? = 1 THEN ? ELSE accuracy END,
            coefficientB   = CASE WHEN ? = 1 THEN ? ELSE coefficientB END,
            coefficientK   = CASE WHEN ? = 1 THEN ? ELSE coefficientK END
        WHERE medlabdevicetestcode = ?
    SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ propertyExistsSQL($records, 'templateName'), isUndefinedProperty($records, 'templateName'), propertyExistsSQL($records, 'accuracy'), isUndefinedProperty($records, 'accuracy'), propertyExistsSQL($records, 'coefficientB'), isUndefinedProperty($records, 'coefficientB'), propertyExistsSQL($records, 'coefficientK'), isUndefinedProperty($records, 'coefficientK'), isEmptyProperty($records, 'id') ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $stmt->fetchAll(); } public function deleteIndicators($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLabDeviceTest', 'MedLabDeviceTestCode', $records); } public function readIndicatorNorms($params = null) { $db = $this->db ?: $this->db = connectDb(); $medLabDeviceTestId = isEmptyProperty($params, 'medLabDeviceTestId', 0); $sql = <<<SQL
            SELECT
                MedLabDeviceTestNormCode                "id",                       -- 1
                MedLabDeviceTestCode                    "medLabDeviceTestId",       -- 2
                Active                                  "active",                   -- 3
                ValueNumberMin                          "valueNumberMin",           -- 4
                ValueNumberMax                          "valueNumberMax",           -- 5
                AgeMin                                  "ageMin",                   -- 6
                AgeMax                                  "ageMax",                   -- 7
                Gender                                  "gender",                   -- 8
                ValueNumberMinInclude                   "valueNumberMinInclude",    -- 9
                PayAttentionIn                          "payAttentionIn",           -- 10
                ValueNumberMaxInclude                   "valueNumberMaxInclude",    -- 11
                PayAttentionOut                         "payAttentionOut",          -- 12
                Description                             "description",              -- 13
                PayAttentionIn + PayAttentionOut * 2    "payAttention"              -- 14
            FROM MedLabDeviceTestNorm
            WHERE MedLabDeviceTestCode = :medLabDeviceTestId
            ORDER BY 6
            SQL;
$ret = selectSql($db, $sql, [ ':medLabDeviceTestId' => $medLabDeviceTestId ]); return $ret; } public function createIndicatorNorms($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            INSERT INTO MedLabDeviceTestNorm (
                MedLabDeviceTestCode, Active, ValueNumberMin,
                ValueNumberMax, AgeMin, AgeMax,
                Gender, ValueNumberMinInclude, PayAttentionIn,
                ValueNumberMaxInclude, PayAttentionOut, Description
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
            SQL;
if (is_object($records)) { $records = [$records]; } $stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'medLabDeviceTestId'), 1, isUndefinedProperty($row, 'valueNumberMin'), isUndefinedProperty($row, 'valueNumberMax'), isUndefinedProperty($row, 'ageMin'), isUndefinedProperty($row, 'ageMax'), isUndefinedProperty($row, 'gender'), isEmptyProperty($row, 'valueNumberMinInclude'), isEmptyProperty($row, 'payAttentionIn', 0), isEmptyProperty($row, 'valueNumberMaxInclude'), isEmptyProperty($row, 'payAttentionOut', 0), isEmptyProperty($row, 'description') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateIndicatorNorms($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            UPDATE MedLabDeviceTestNorm
            SET
                Active                  = CASE WHEN ? = 1 THEN ? ELSE Active END,
                ValueNumberMin          = CASE WHEN ? = 1 THEN ? ELSE ValueNumberMin END,
                ValueNumberMax          = CASE WHEN ? = 1 THEN ? ELSE ValueNumberMax END,
                --
                AgeMin                  = CASE WHEN ? = 1 THEN ? ELSE AgeMin END,
                AgeMax                  = CASE WHEN ? = 1 THEN ? ELSE AgeMax END,
                Gender                  = CASE WHEN ? = 1 THEN ? ELSE Gender END,
                --
                ValueNumberMinInclude   = CASE WHEN ? = 1 THEN ? ELSE ValueNumberMinInclude END,
                PayAttentionIn          = CASE WHEN ? = 1 THEN ? ELSE PayAttentionIn END,
                ValueNumberMaxInclude   = CASE WHEN ? = 1 THEN ? ELSE ValueNumberMaxInclude END,
                --
                PayAttentionOut         = CASE WHEN ? = 1 THEN ? ELSE PayAttentionOut END,
                Description             = CASE WHEN ? = 1 THEN ? ELSE Description END
            WHERE medLabDeviceTestNormCode = ?
            SQL;
if (is_object($records)) { $records = [$records]; } $stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'active'), isEmptyProperty($row, 'active'), propertyExistsSQL($row, 'valueNumberMin'), isUndefinedProperty($row, 'valueNumberMin'), propertyExistsSQL($row, 'valueNumberMax'), isUndefinedProperty($row, 'valueNumberMax'), propertyExistsSQL($row, 'ageMin'), isUndefinedProperty($row, 'ageMin'), propertyExistsSQL($row, 'ageMax'), isUndefinedProperty($row, 'ageMax'), propertyExistsSQL($row, 'gender'), isUndefinedProperty($row, 'gender'), propertyExistsSQL($row, 'valueNumberMinInclude'), isEmptyProperty($row, 'valueNumberMinInclude'), propertyExistsSQL($row, 'payAttentionIn'), isEmptyProperty($row, 'payAttentionIn', 0), propertyExistsSQL($row, 'valueNumberMaxInclude'), isEmptyProperty($row, 'valueNumberMaxInclude'), propertyExistsSQL($row, 'payAttentionOut'), isEmptyProperty($row, 'payAttentionOut', 0), propertyExistsSQL($row, 'description'), isEmptyProperty($row, 'description'), isEmptyProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteIndicatorNorms($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLabDeviceTestNorm', 'MedLabDeviceTestNormCode', $records); } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_lab_analyzer');