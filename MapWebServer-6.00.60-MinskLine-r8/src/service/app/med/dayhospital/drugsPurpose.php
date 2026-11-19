<?php
require_once(__DIR__.'/../../init.php'); class med_dayhospital_drugsPurpose { private $db; public function readDrugsPurpose($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = "WHERE 1=1"; $isCard = isEmptyProperty($params, 'isCard'); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate'); $allergyId = isEmptyProperty($params, 'allergyId'); $id = isEmptyProperty($params, 'id'); $logId = isEmptyProperty($params, 'logId'); if ($patientId) { $where .= " AND MDHP.MedPatientCode = :patientId"; $sqlParams['patientId'] = $patientId; } if ($allergyId) { $where .= " AND MDHP.MedAllergyCode = :allergyId"; $sqlParams['allergyId'] = $allergyId; } if ($id) { $where .= " AND MDHP.MedDayHopsitalPurposeCode = :id"; $sqlParams['id'] = $id; } if ($doctorId) { $where .= " AND MDHP.MedDoctorCode = :doctorId"; $sqlParams['doctorId'] = $doctorId; } if ($onDate) { $where .= " AND MDHP.CreateTime::date BETWEEN (:onDate::date - INTERVAL '1 year')  AND :onDate"; $sqlParams['onDate'] = $onDate; } if ($logId) { $where .= " AND MDHP.MedDayHospitalCode = :logId"; $sqlParams['logId'] = $logId; } $where .= ' AND (MDHP.CiszResourceValueCodeMedDevice IS NULL AND MDHP.CiszResourceValueCodeMedDeviceIssued IS NULL)'; if ($isCard) { $sqlCard = <<<"EOT"
    MDHP.MedDayHopsitalPurposeCode                      "recordId",
    null                                                "parentId",
    MDHP.CreateTime                                     "parentDate",
    MDHP.CreateTime                                     "recordDate",
    COALESCE(MDHP.MedDayHopsitalPurposeCode, '')::text || '-'   "path",
    29                                                  "recordType",
    29                                                  "sortType",
    fn_cmFIO(MD.ManCode,1)                              "doctorInitials",
EOT;
} else { $sqlCard = ''; } $sql = <<<"EOT"
SELECT
    $sqlCard
    MDHP.MedDayHopsitalPurposeCode "id",
    MDHP.MedDoctorCode "doctorId",
    FN_cmFIO(MD.ManCode, 1) "doctorFio",
    MP.Name "doctorProfession",
    D.Name "departmentName",
    MDHP.MatWealthCode "drugId",
    MWO.Name "wealthName",
    MWO.SecondName "wealthNameInn",
    MWN.Name0 "drugName",
    MWN.Name0_1 "drugInnName",
    MDHP.Dose "dose",
    CRV_DOSE.Name "doseUnitName",
    CRV_DOSE.CiszResourceValueCode "doseUnitCode",
    MDHP.MedGraphAcceptCode "graphId",
    MGA.Name "graphName",
    MDHP.PurposeTime "startTime",
    MDHP.CancelTime "finishTime",
    MDHP.CreateTime "createDate",
    MDHP.MedPurposeMethodCode "methodId",
    MPM.Name "methodNameOld",
    MDHP.Note "note",
    (MDHP.MedDoctorCodeCancel IS NOT NULL) "isCanceled",
    MDHP.MedDoctorCodeCancel "rejectDoctorId",
    FN_cmFIO(MD2.ManCode, 1) "rejectDoctorFio",
    CRV_Cancel.CiszResourceValueCode "rejectCode",
    CRV_Cancel.Name "rejectName",
    CRV_Category.CiszResourceValueCode "typeCode",
    CRV_Category.Name "typeName",
    MDHP.Description "scheduleText",
    MDHP.Instruction "instruction",
    COALESCE(NULLIF(TRIM(MDHP.Description), ''), MDHP.Instruction) AS "description",
    CRV_Method.CiszResourceValueCode "methodCode",
    CRV_Method.Name "methodName",
    MDHP.MedPatientCode "patientId",
    FN_cmFIO(MPT.ManCode) "patientFio",
    MDHP.MedDiagnosisCode "diagnosisId",
    ((CASE WHEN MDI.Cipher1 IS NULL THEN E''
      ELSE MDI.Cipher1 || E' - '
      END) || MDI.Name) "diagnosisName",
    COALESCE(VS.vitalSigns, '[]') "vitalSigns",
    MDHP.InjectionRate "speed",
    CRV_Speed.Name "speedUnitName",
    CRV_Speed.CiszResourceValueCode "speedUnitCode",
    MDHP.Json "drugJson",
    COALESCE(NULLIF(MDHP.Json, '')::json -> 'medication' ->> 'name', NULL) AS "drugNameCisz",
    MWM.Name "measureDoseName",
    MDHP.Position "position",
    CL.Status "ciszStatus"
FROM MedDayHopsitalPurpose MDHP
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MDHP.MedDoctorCode
LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
LEFT JOIN Department D ON D.DepartmentCode = MD.DepartmentCode
LEFT JOIN MatWealth MW ON MW.MatWealthCode = MDHP.MatWealthCode
LEFT JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
LEFT JOIN MatWealthNames MWN on MWN.MatWealthCode = MW.MatWealthCode
LEFT JOIN CiszResourceValue CRV_DOSE ON CRV_DOSE.CiszResourceValueCode = MDHP.CiszResourceCodeDoseMeasure
LEFT JOIN MedPurposeMethod MPM ON MPM.MedPurposeMethodCode = MDHP.MedPurposeMethodCode
LEFT JOIN MedGraphAccept MGA ON MGA.MedGraphAcceptCode = MDHP.MedGraphAcceptCode
LEFT JOIN MedDoctor MD2 ON MD2.MedDoctorCode = MDHP.MedDoctorCodeCancel
LEFT JOIN MedPatient MPT ON MPT.MedpatientCode = MDHP.MedPatientCode
LEFT JOIN CiszResourceValue CRV_Cancel ON CRV_Cancel.CiszResourceValueCode = MDHP.CiszResourceCodeCancelCause
LEFT JOIN CiszResourceValue CRV_Category ON CRV_Category.CiszResourceValueCode = MDHP.CiszResourceCodeCategory
LEFT JOIN CiszResourceValue CRV_Method ON CRV_Method.CiszResourceValueCode = MDHP.CiszResourceCodeInjectionRoute
LEFT JOIN CiszResourceValue CRV_Speed ON CRV_Speed.CiszResourceValueCode = MDHP.CiszResourceCodeInjectionRate
LEFT JOIN MedDiagnosis MDI ON MDI.MedDiagnosisCode = MDHP.MedDiagnosisCode
LEFT JOIN MatWealthMeasure MWM ON MWM.MatWealthMeasureCode = MW.MatWealthMeasureDoseSizeCode
LEFT JOIN CiszLink CL ON CL.TableName = 'MedMedication' AND CL.PrimaryKey = MDHP.MedDayHopsitalPurposeCode AND CL.Type = 1
LEFT JOIN LATERAL (
    SELECT json_agg(
        json_build_object(
            'vitalSignId', MDVS.MedDayHopsitalPurposeVitalSignsCode,
            'vitalSignType', MDVS.Type,
            'vitalSignNumber', MDVS.ValueNumber
        )
    ) AS vitalSigns
    FROM MedDayHopsitalPurposeVitalSigns MDVS
    WHERE MDVS.MedDayHopsitalPurposeCode = MDHP.MedDayHopsitalPurposeCode
) VS ON TRUE
$where
ORDER BY MDHP.CreateTime, MDHP.MedDayHopsitalPurposeCode DESC
EOT;
return selectSql($db, $sql, $sqlParams); } public function createDrugsPurpose($records) { $db = $this->db ?: $this->db = connectDb(); $recordsList = is_array($records) && isset($records[0]) ? $records : [$records]; foreach ($recordsList as $record) { $vitalSigns = $record->vitalSigns ?? []; unset($record->vitalSigns); if ($vitalSigns && is_string($vitalSigns)) { $vitalSigns = json_decode($vitalSigns, true) ?? []; } if (isEmptyProperty($record, 'allergyId')) { $newId = insertSql($db, 'MedDayHopsitalPurpose', $record, "MedPatientCode MedDiagnosisCode MedDoctorCode
            json Dose MedGraphAcceptCode
            PurposeTime CancelTime Note
            CreateTime MedDoctorCodeCancel CiszResourceCodeCancelCause
            CiszResourceCodeCategory Description Instruction
            CiszResourceCodeInjectionRoute CiszResourceCodeDoseMeasure CiszResourceCodeInjectionRate
            InjectionRate MedAllergyCode", "patientId diagnosisId doctorId
            drugJson dose graphId
            startTime finishTime note
            createDate rejectDoctorId rejectCode
            typeCode scheduleText instruction
            methodCode doseUnitCode speedUnitCode
            speed allergyId" ); } else { $newId = insertSql($db, 'MedDayHopsitalPurpose', $record, "MedPatientCode MedDiagnosisCode MedDoctorCode
            json Dose MedGraphAcceptCode
            PurposeTime CancelTime Note
            CreateTime MedDoctorCodeCancel CiszResourceCodeCancelCause
            CiszResourceCodeCategory Description Instruction
            CiszResourceCodeInjectionRoute CiszResourceCodeDoseMeasure CiszResourceCodeInjectionRate
            InjectionRate MedDayHospitalCode Position", "patientId diagnosisId doctorId
            drugJson dose graphId
            startTime finishTime note
            createDate rejectDoctorId rejectCode
            typeCode scheduleText instruction
            methodCode doseUnitCode speedUnitCode
            speed logId position" ); } $sqlInsert = <<<SQL
            INSERT INTO MedDayHopsitalPurposeVitalSigns (MedDayHopsitalPurposeCode, Type, ValueNumber)
            VALUES (?, ?, ?)
            SQL;
$stmt = $db->prepare($sqlInsert); foreach ($vitalSigns as $v) { $MedDayHopsitalPurposeCode = $newId[0]->id; $Type = $v['vitalSignType']; $ValueNumber = $v['vitalSignNumber']; if (!empty($ValueNumber)) { $stmt->execute([$MedDayHopsitalPurposeCode, $Type, $ValueNumber]); } } if (isset($record->graphItems)) { $this->updateGraph((object)[ 'purposeId' => $newId[0]->id, 'items' => !empty($record->graphItems) ? $record->graphItems : [] ]); } if (isset($record->logId)) { $log = new med_dayhospital_log(); $log->updateCiszStatus($record->logId); } } return $newId; } public function updateDrugsPurpose($records) { $db = $this->db ?: $this->db = connectDb(); $recordsList = is_array($records) && isset($records[0]) ? $records : [$records]; foreach ($recordsList as $record) { $vitalSigns = $record->vitalSigns ?? []; unset($record->vitalSigns); if ($vitalSigns && is_string($vitalSigns)) { $vitalSigns = json_decode($vitalSigns, true) ?? []; } $db->beginTransaction(); try { updateSql($db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $record, "MedPatientCode MedDiagnosisCode MedDoctorCode
            json Dose MedGraphAcceptCode
            PurposeTime CancelTime Note
            CreateTime MedDoctorCodeCancel CiszResourceCodeCancelCause
            CiszResourceCodeCategory Description Instruction
            CiszResourceCodeInjectionRoute CiszResourceCodeDoseMeasure CiszResourceCodeInjectionRate
            InjectionRate MedDayHospitalCode Position", "patientId diagnosisId doctorId
            drugJson dose graphId
            startTime finishTime note
            createDate rejectDoctorId rejectCode
            typeCode scheduleText instruction
            methodCode doseUnitCode speedUnitCode
            speed logId position" ); $purposeId = $record->id; $vsUpdateSql = "UPDATE MedDayHopsitalPurposeVitalSigns
                        SET ValueNumber = :value
                        WHERE MedDayHopsitalPurposeVitalSignsCode = :vsId"; $vsInsertSql = "INSERT INTO MedDayHopsitalPurposeVitalSigns
                        (MedDayHopsitalPurposeCode, Type, ValueNumber)
                        VALUES (:purposeId, :type, :value)"; $vsUpdateStmt = $db->prepare($vsUpdateSql); $vsInsertStmt = $db->prepare($vsInsertSql); foreach ($vitalSigns as $v) { if (!empty($v['vitalSignId'])) { $vsUpdateStmt->execute([ ':value' => $v['vitalSignNumber'], ':vsId' => $v['vitalSignId'] ]); } else if (!empty($v['vitalSignNumber'])) { $vsInsertStmt->execute([ ':purposeId' => $purposeId, ':type' => $v['vitalSignType'], ':value' => $v['vitalSignNumber'] ]); } } if (isset($record->graphItems)) { $this->updateGraph((object)[ 'purposeId' => $purposeId, 'items' => !empty($record->graphItems) ? $record->graphItems : [] ]); } $stmt = $db->prepare("SELECT MedDayHospitalCode FROM MedDayHopsitalPurpose WHERE MedDayHopsitalPurposeCode = :purposeId"); $stmt->execute([':purposeId' => $purposeId]); $logId = $stmt->fetchColumn() ?: null; if ($logId) { $log = new med_dayhospital_log(); $log->updateCiszStatus($logId); } $db->commit(); } catch (\Exception $e) { $db->rollBack(); throw $e; } } return true; } public function deleteDrugsPurpose($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $records); } public function readDrugInfo($params) { $db = $this->db ?: $this->db = connectDb(); $drugId = isEmptyProperty($params, 'drugId'); $sql = <<<"EOT"
SELECT
    MW.MatWealthCode "id",
    MW.DoseSize "dose",
    MWO.Name "name",
    MWM.Name "measureDoseName",
    MWM.MatWealthMeasureCode "measureDoseId"
FROM MatWealth MW
LEFT JOIN MatWealthMeasure MWM ON MWM.MatWealthMeasureCode = MW.MatWealthMeasureDoseSizeCode
LEFT JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
WHERE MW.MatWealthCode = :id
EOT;
return selectSql($db, $sql, [':id' => $drugId])[0]; } public function readGraph($params = null) { if (!$params) { return []; } $db = $this->db ?: $this->db = connectDb(); $purposeId = isEmptyProperty($params, 'drugPurposeId'); $items = isEmptyProperty($params, 'items'); if ($purposeId) { $sql = <<<"EOT"
SELECT
        MDHG.MedGraphAcceptCode "id",
        MGA.Name "name"
        FROM MedDayHopsitalPurposeGraphAccept MDHG
           LEFT JOIN MedGraphAccept MGA ON MGA.MedGraphAcceptCode = MDHG.MedGraphAcceptCode
        WHERE MDHG.MedDayHopsitalPurposeCode = $purposeId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } else if ($items) { if (!is_array($items)) { $items = [$items]; } if (!is_array($items)) { $items = [$items]; } $items = array_map(function ($item) { if (is_object($item)) { return $item->id ?? null; } return $item; }, $items); $placeholders = implode(',', array_fill(0, count($items), '?')); $sql = <<<"EOT"
SELECT
        MGA.MedGraphAcceptCode "id",
        MGA.Name "name"
        FROM MedGraphAccept MGA
        WHERE MGA.MedGraphAcceptCode IN ($placeholders)
EOT;
$stmt = $db->prepare($sql); $stmt->execute($items); } return $stmt->fetchAll(); } public function updateGraph($params = null) { $db = $this->db ?: $this->db = connectDb(); $purposeId = isEmptyProperty($params, 'purposeId'); $items = isUndefinedProperty($params, 'items'); if (!$purposeId || !is_array($items)) { return true; } $sql = "
        SELECT MedGraphAcceptCode
        FROM MedDayHopsitalPurposeGraphAccept
        WHERE MedDayHopsitalPurposeCode = :purposeId
    "; $stmt = $db->prepare($sql); $stmt->execute(['purposeId' => $purposeId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedDayHopsitalPurposeGraphAccept
            (MedDayHopsitalPurposeCode, MedGraphAcceptCode)
            VALUES (:purposeId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'purposeId' => $purposeId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedDayHopsitalPurposeGraphAccept
            WHERE MedDayHopsitalPurposeCode = ?
            AND MedGraphAcceptCode IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$purposeId], $toDelete)); } return true; } } handleJsonRpc('med_dayhospital_drugsPurpose');