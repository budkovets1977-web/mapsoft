<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_infoboxDevices { private $db; public function readInfoboxDevices($params){ $db = $this->db ?: $this->db = connectDb(); $infoboxId = (int) $params->infoboxId; $sql = <<<EOT
            SELECT
                MedEqDeviceCode     "id",
                Interface           "interfaceId",
                MedEqInfoboxCode    "infoboxId",
                Note                "note",
                Number              "number",
                Protocol            "protocolId",
                Setup               "setup",
                Type                "type"
            FROM MedEqDevice
            WHERE MedEqInfoboxCode = $infoboxId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } private function udpateDeviceTables($paramDeviceTables, $infoboxId, $type = 1){ $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { foreach ($paramDeviceTables as $deviceTable) { $deviceTableId = (int) $deviceTable->id; if ($deviceTableId == 0) { $sql = <<<EOT
INSERT INTO MedEqDevice (Interface, MedEqInfoboxCode, Note, Number, Protocol, Setup, Type)
    VALUES (?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($deviceTable, 'interfaceId'), $infoboxId, isEmptyProperty($deviceTable, 'note'), isEmptyProperty($deviceTable, 'number'), isEmptyProperty($deviceTable, 'protocolId'), isEmptyProperty($deviceTable, 'setup'), $type ]); $deviceTable->id = $db->lastInsertId('seq_MedEqDevice'); } else { $sql = <<<EOT
UPDATE MedEqDevice SET
    Interface = CASE WHEN ? = 1 THEN ? ELSE Interface END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
    Number = CASE WHEN ? = 1 THEN ? ELSE Number END,
    Protocol = CASE WHEN ? = 1 THEN ? ELSE Protocol END,
    Setup = CASE WHEN ? = 1 THEN ? ELSE Setup END
WHERE MedEqDeviceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($deviceTable, 'interfaceId'), isUndefinedProperty($deviceTable, 'interfaceId'), propertyExistsSQL($deviceTable, 'note'), isUndefinedProperty($deviceTable, 'note'), propertyExistsSQL($deviceTable, 'number'), isUndefinedProperty($deviceTable, 'number'), propertyExistsSQL($deviceTable, 'protocolId'), isUndefinedProperty($deviceTable, 'protocolId'), propertyExistsSQL($deviceTable, 'setup'), isUndefinedProperty($deviceTable, 'setup'), $deviceTableId ]); } } $in = createSqlInCondition($paramDeviceTables, 'id'); $sql = <<<EOT
DELETE FROM MedEqDevice
WHERE MedEqInfoboxCode = $infoboxId
    AND NOT (MedEqDeviceCode $in)
    AND Type = $type
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function syncInfoboxDevices($infoboxId, $paramsDevices, $paramsTables){ if (isset($paramsDevices)) { $this->udpateDeviceTables($paramsDevices, $infoboxId); } if (isset($paramsTables)) { $this->udpateDeviceTables($paramsTables, $infoboxId, 2); } } } handleJsonRpc('equeue_app_infoboxDevices');