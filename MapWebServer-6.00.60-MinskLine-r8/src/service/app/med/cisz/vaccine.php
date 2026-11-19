<?php
 require_once(__DIR__ . '/../../init.php'); require_once('traits/utilsTrait.php'); class med_cisz_vaccine { use med_cisz_utilsTrait; private $db; private object $settings; public function readCiszPatientVaccine($record) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($record, 'patientId'); $result = selectSql($db, <<<SQL
        SELECT Json
        FROM CiszData
        WHERE ResourceName = 'ImmunizationRecommendation'
          AND MedPatientCode = :id
    SQL,
[':id' => $patientId]); $data = []; foreach ($result as $res) { if (empty($res['json'])) { continue; } $jsonArray = json_decode($res['json']); if (empty($jsonArray)) { return []; } foreach ($jsonArray as $json) { $diseaseCodes = []; $diseaseNames = []; foreach ($json->targetDisease ?? [] as $disease) { foreach ($disease->coding ?? [] as $coding) { if (!empty($coding->code)) { $diseaseCodes[] = $coding->code; } if (!empty($coding->display)) { $diseaseNames[] = $coding->display; } } } $item = [ 'disease' => $diseaseCodes, 'diseaseName' => implode(', ', $diseaseNames), 'forecastStatus' => $json->forecastStatus->coding[0]->code ?? '', 'forecastStatusName' => $json->forecastStatus->coding[0]->display ?? '', 'description' => $json->description ?? null, 'vaccineTypeId' => $json->vaccineTypeId ?? null, 'vaccineTypeName' => $json->vaccineTypeName ?? '', 'vaccine' => $json->vaccine ?? null, 'vaccineName' => $json->vaccineName ?? '', 'fileId' => $json->fileId ?? null, 'fileName' => $json->fileName ?? '' ]; foreach ($json->extension ?? [] as $ext) { switch (basename($ext->url)) { case 'DateCriterionPeriod': $item['startPeriod'] = $ext->valuePeriod->start ?? null; $item['endPeriod'] = $ext->valuePeriod->end ?? null; break; case 'VaccinationType': $item['profType'] = $ext->valueCodeableConcept->coding[0]->code ?? ''; $item['profTypeName'] = $ext->valueCodeableConcept->coding[0]->display ?? ''; break; case 'VaccineDose': $item['dose'] = $ext->valueQuantity->value ?? ''; $item['doseUnit'] = $ext->valueQuantity->code ?? ''; $item['doseUnitName'] = $this->getResourceValueName($db, 'AISReportUnits', $item['doseUnit']); break; case 'AttachDocumentImmun': $item['ciszFile'] = $ext->valueReference->reference ?? ''; break; } } $data[] = $item; } } return $data; } public function updateCiszPatientVaccine($record) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($record, 'patientId'); $recommendations = isEmptyProperty($record, 'recommendations'); $json = []; if (!empty($recommendations)) { foreach ($recommendations as $rec) { $item = []; $diseases = []; foreach (($rec->disease ?? []) as $i => $code) { $diseases[] = [ 'coding' => [[ 'system' => med_cisz_constant::$valueSet['AISReportImmunizationTargetDiseases'], 'code' => $code, 'display' => explode(', ', $rec->diseaseName ?? '')[$i] ?? '' ]] ]; } if ($diseases) { $item['targetDisease'] = $diseases; } if (isset($rec->vaccineTypeId)) { $item['vaccineTypeId'] = $rec->vaccineTypeId; $item['vaccineTypeName'] = $rec->vaccineTypeName; } if (isset($rec->vaccine)) { $item['vaccine'] = $rec->vaccine; } if (isset($rec->vaccineName)) { $item['vaccineName'] = $rec->vaccineName; } if (isset($rec->fileId)) { $item['fileId'] = $rec->fileId; $item['fileName'] = $rec->fileName; } $item['forecastStatus'] = [ 'coding' => [[ 'system' => med_cisz_constant::$valueSet['ImmunizationRecommendationStatus'], 'code' => $rec->forecastStatus ?? '', 'display' => $rec->forecastStatusName ?? '' ]] ]; if (!empty($rec->description)) { $item['description'] = $rec->description; } $extensions = []; if (!empty($rec->startPeriod) || !empty($rec->endPeriod)) { $extensions[] = [ 'url' => med_cisz_constant::$structureDefinitions['DateCriterionPeriod'], 'valuePeriod' => [ 'start' => $rec->startPeriod ?? null, 'end' => $rec->endPeriod ?? null ] ]; } if (!empty($rec->profType)) { $extensions[] = [ 'url' => med_cisz_constant::$structureDefinitions['VaccinationType'], 'valueCodeableConcept' => [ 'coding' => [[ 'system' => med_cisz_constant::$valueSet['AISReportVaccinationType'], 'code' => $rec->profType, 'display' => $rec->profTypeName ?? '' ]] ] ]; } if (!empty($rec->dose)) { $extensions[] = [ 'url' => med_cisz_constant::$structureDefinitions['VaccineDose'], 'valueQuantity' => [ 'system' => med_cisz_constant::$valueSet['AISReportUnits'], 'value' => $rec->dose, 'code' => $rec->doseUnit ?? '', 'display' => $rec->doseUnitName ?? '' ] ]; } if ($rec->ciszFile) { $item['fileName'] = $rec->fileName ?? ''; $extensions[] = [ 'url' => med_cisz_constant::$structureDefinitions['AttachDocumentImmun'], 'valueReference' => [ 'reference' => $rec->ciszFile ] ]; } if ($extensions) { $item['extension'] = $extensions; } $json[] = $item; } } $jsonString = json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); executeSql($db, <<<SQL
    UPDATE CiszData
    SET Json = :json
    WHERE ResourceName='ImmunizationRecommendation'
      AND MedPatientCode=:id
SQL,
[ ':json' => $jsonString, ':id' => $patientId ]); $ciszLinkUuid = selectSql($db, <<<SQL
            SELECT 
                Cl.Uuid "uuid"
            FROM CiszLink CL
            WHERE CL.PrimaryKey = :patientId AND CL.TableName = :tableName
        SQL,
[ ':patientId' => $patientId, ':tableName' => 'MedVaccinationRecommendation' ]); if ($ciszLinkUuid) { executeSql($db, <<<SQL
                UPDATE CiszLink
                SET Status = 2
                WHERE TableName = 'MedVaccinationRecommendation'
                  AND PrimaryKey = :id
            SQL,
[ ':id' => $patientId ]); } } public function getCiszDataCode($record) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($record, 'patientId'); $ciszDataCode = selectSqlValue($db, <<<SQL
        SELECT CiszDataCode
        FROM CiszData
        WHERE ResourceName = 'ImmunizationRecommendation'
          AND MedPatientCode = :id
    SQL,
[ ':id' => $patientId ]); if ($ciszDataCode) { return $ciszDataCode; } executeSql($db, <<<SQL
        INSERT INTO CiszData (
            ResourceName,
            MedPatientCode
        )
        VALUES (
            'ImmunizationRecommendation',
            :id
        )
    SQL,
[ ':id' => $patientId ]); return $db->lastInsertId(); } public function getPatientUuid($record) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($record, 'patientId'); return selectSqlValue($db, <<<SQL
        SELECT Uuid
        FROM CiszLink
        WHERE TableName = 'MedPatient'
          AND Type = 1
          AND PrimaryKey = :id
        LIMIT 1
    SQL,
[ ':id' => $patientId ]); } public function getResourceValueName($db, string $resourceIdentifier, string $code): ?string { return selectSqlValue($db, <<<SQL
        SELECT
            CRV.Name
        FROM CiszResourceValue CRV
        JOIN CiszResource CR
            ON CR.CiszResourceCode = CRV.CiszResourceCode
        WHERE CR.Identifier = :identifier
          AND CRV.Code = :code
    SQL,
[ ':identifier' => $resourceIdentifier, ':code' => $code ]); } public function readInfection($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); $itemsIds = isUndefinedProperty($params, 'itemsIds'); if (!$items && !$itemsIds) { return []; } $sql = <<<SQL
SELECT
    CRV.Code AS "code",
    CRV.Name AS "name"
FROM CiszResourceValue CRV
JOIN Ciszresource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = 'AISReportImmunizationTargetDiseases'
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { $incomingIds[] = (int)$item; } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.Code IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } } if (!empty($itemsIds)) { $incomingValueIds = array_map( 'intval', explode(',', $itemsIds) ); $placeholders = implode( ',', array_map(fn($k) => ":itemId$k", array_keys($incomingValueIds)) ); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingValueIds as $k => $id) { $bindings[":itemId$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } } handleJsonRpc('med_cisz_vaccine');