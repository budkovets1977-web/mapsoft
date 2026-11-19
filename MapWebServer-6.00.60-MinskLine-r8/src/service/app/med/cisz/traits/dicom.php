<?php
 require_once('utilsTrait.php'); trait med_cisz_dicom { use med_cisz_utilsTrait; private function createDicomDevice(int $id) { $row = selectSql($this->db, <<<SQL
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
            WHERE DPD.DicomPacsDevicesCode = :id
        ORDER BY DPD.ae
        SQL,
[ ':id' => $id ], PDO::FETCH_OBJ ); foreach ($row as $item) { $id = generateUUID(null, null, true); if (isset($item->json)) { $medicalPruduct = safe_json_decode($item->json)->medicalProducts; $medicalPruductCode = $medicalPruduct->code; $medicalPruductManufacturer = $medicalPruduct->manufacturer; } $resource = (object) [ 'fullUrl' => 'Device/' . $id, 'resource' => (object) [ 'resourceType' => 'Device', 'id' => $id, 'meta' => (object) [ 'profile' => [ med_cisz_constant::$structureDefinitions['DeviceImagingStudy'] ] ], 'extension' => [ (object) [ 'url' => med_cisz_constant::$structureDefinitions['AETitle'], 'valueString' => $item->ae ], (object) [ 'url' => med_cisz_constant::$structureDefinitions['DeviceAddress'], 'valueString' => $item->host ] ], 'identifier' => [ (object) [ 'value' => $item->cardNumber, 'assigner' => $this->organization->link ] ], 'definition' => (object) [ 'concept' => self::getCodingStructure(med_cisz_constant::$valueSet['MedicalProducts'], $medicalPruductCode, $medicalPruduct->name) ], 'status' => $item->active ? 'active' : 'inactive', 'manufacturer' => $medicalPruductManufacturer ? $medicalPruductManufacturer : null, 'serialNumber' => $item->serialNumber, 'modelNumber' => $item->model, 'name' => $item->name ? [ (object) [ 'value' => $item->name, 'type' => 'user-friendly-name' ] ] : null, 'owner' => $this->organization->link ] ]; } $this->dicomDevice[] = $resource ?? null; } }