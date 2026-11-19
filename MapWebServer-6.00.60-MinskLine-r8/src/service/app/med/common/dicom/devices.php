<?php
 require_once(__DIR__ . '/../../../init.php'); class med_common_dicom_devices { private $db; public function readDicomDevices($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    DPD.DicomPacsDevicesCode "id",
    DPD.DicomPacsCode "pacsId",
    DPD.Name "name",
    DPD.Ae "ae",
    DPD.SerialNumber "serialNumber",
    DPD.CardNumber "cardNumber",
    DPD.Manufacturer "manufacturer",
    DPD.Model "model",
    DPD.Json "json",
    DPD.Active "active",
    DPD.Host "host"
FROM DicomPacsDevices DPD
ORDER BY DPD.ae
EOT;
$stmt = $db->prepare($sql); $stmt->execute([]); return $stmt->fetchAll(); } public function createDicomDevices($records) { $db = $this->db ?: $this->db = connectDb(); $dicomPacsCode = isUndefinedProperty($records, 'pacsId'); $name = isUndefinedProperty($records, 'name'); $ae = isUndefinedProperty($records, 'ae'); $serialNumber = isUndefinedProperty($records, 'serialNumber'); $cardNumber = isUndefinedProperty($records, 'cardNumber'); $manufacturer = isUndefinedProperty($records, 'manufacturer'); $model = isUndefinedProperty($records, 'model'); $json = isUndefinedProperty($records, 'json'); $active = isUndefinedProperty($records, 'active'); $host = isUndefinedProperty($records, 'host'); $sql = <<<"EOT"
            INSERT INTO DicomPacsDevices (DicomPacsCode, Name, Ae, SerialNumber, CardNumber, Manufacturer, Model, Json, Active, Host)
            VALUES (:dicomPacsCode, :name, :ae, :serialNumber, :cardNumber, :manufacturer, :model, :json, :active, :host)
        EOT;
executeSql($db, $sql, [ 'dicomPacsCode' => $dicomPacsCode, 'name' => $name, 'ae' => $ae, 'serialNumber' => $serialNumber, 'cardNumber' => $cardNumber, 'manufacturer' => $manufacturer, 'model' => $model, 'json' => $json, 'active' => $active, 'host' => $host ]); } public function updateDicomDevices($records) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($records, 'id'); $fields = [ 'DicomPacsCode' => isUndefinedProperty($records, 'pacsId'), 'Name' => isUndefinedProperty($records, 'name'), 'Ae' => isUndefinedProperty($records, 'ae'), 'SerialNumber' => isUndefinedProperty($records, 'serialNumber'), 'CardNumber' => isUndefinedProperty($records, 'cardNumber'), 'Manufacturer' => isUndefinedProperty($records, 'manufacturer'), 'Model' => isUndefinedProperty($records, 'model'), 'Json' => isUndefinedProperty($records, 'json'), 'Active' => isUndefinedProperty($records, 'active'), 'Host' => isUndefinedProperty($records, 'host') ]; $filteredFields = array_filter($fields, function($value) { return $value !== null; }); if (empty($filteredFields)) { throw new Exception('No fields to update'); } $setParts = []; $params = ['id' => $id]; foreach ($filteredFields as $dbField => $value) { $paramName = ':' . lcfirst($dbField); $setParts[] = "$dbField = $paramName"; $params[lcfirst($dbField)] = $value; } $sql = "UPDATE DicomPacsDevices SET " . implode(', ', $setParts) . " WHERE DicomPacsDevicesCode = :id"; executeSql($db, $sql, $params); } public function deleteDicomDevices($records) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($records, 'id'); $sql = <<<"EOT"
            DELETE FROM DicomPacsDevices 
            WHERE DicomPacsDevicesCode = :id
        EOT;
executeSql($db, $sql, [ 'id' => $id ]); } public function getLastCardNumber() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
            SELECT CardNumber "cardNumber"
            FROM DicomPacsDevices 
            ORDER BY DicomPacsDevicesCode DESC 
            LIMIT 1
        EOT;
$stmt = $db->prepare($sql); $stmt->execute([]); $result = $stmt->fetch(PDO::FETCH_ASSOC); return $result ? $result['cardNumber'] : null; } } handleJsonRpc('med_common_dicom_devices'); 