<?php
require_once(__DIR__.'/../../init.php'); class med_recipes_medicalDevices { private $db; public function readMedicalDevices($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ""; $isCard = isEmptyProperty($params, 'isCard'); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate'); $id = isEmptyProperty($params, 'id'); if ($patientId) { $where .= " AND MDHP.MedPatientCode = :patientId"; $sqlParams['patientId'] = $patientId; } if ($id) { $where .= " AND MDHP.MedDayHopsitalPurposeCode = :id"; $sqlParams['id'] = $id; } if ($doctorId) { $where .= " AND (MDHP.MedDoctorCode = :doctorId OR MDHP.MedDoctorCode IS NULL)"; $sqlParams['doctorId'] = $doctorId; } if ($onDate) { $where .= " AND COALESCE(MDHP.PurposeTime::date, MDHP.IssueDate::date) BETWEEN (:onDate::date - INTERVAL '1 year')  AND :onDate"; $sqlParams['onDate'] = $onDate; } if ($isCard) { $sqlCard = <<<"EOT"
    MDHP.MedDayHopsitalPurposeCode                              "recordId",
    null                                                        "parentId",
    COALESCE(MDHP.PurposeTime::date, MDHP.IssueDate::date)      "parentDate",
    COALESCE(MDHP.PurposeTime::date, MDHP.IssueDate::date)      "recordDate",
    COALESCE(MDHP.MedDayHopsitalPurposeCode, '')::text || '-'   "path",
    32                                                          "recordType",
    32                                                          "sortType",
    fn_cmFIO(MD.ManCode,1)                                      "doctorInitials",
EOT;
} else { $sqlCard = ''; } $sql = <<<"EOT"
SELECT
    $sqlCard
    MDHP.MedDayHopsitalPurposeCode                  "id",
    MDHP.MedDoctorCode                              "doctorId",
    FN_cmFIO(MD.ManCode, 1)                         "doctorFio",
    MP.Name                                         "doctorProfession",
    D.Name                                          "departmentName",
    MDHP.MedPatientCode                             "patientId",
    FN_cmFIO(MPT.ManCode)                           "patientFio",
    COALESCE(MDHP.PurposeTime::date, MDHP.IssueDate::date) "parentDate2",
    MDHP.PurposeTime                                "createDate",
    MDHP.CancelTime                                 "finishDate",
    MDHP.CiszResourceValueCodePrescribeIndication   "indicationId",
    CRV_PI.Name                                     "indicationName",
    MDHP.CiszResourceValueCodeMedDevice             "nomenclatureId",
    CRV_MD.Name                                     "nomenclatureName",
    MDHP.CiszResourceValueCodeTermOfPurchase        "termsId",
    CRV_TP.Name                                     "termsName",
    MDHP.CiszResourceCodeCancelCause                "rejectCode",
    CRV_CC.Name                                     "rejectName",
    MDHP.CiszResourceValueCodeMedDeviceIssued       "nomenclatureSupId",
    CRV_MDI.Name                                    "nomenclatureSupName",
    MDHP.CiszResourceValueCodeTermOfIssue           "termsSupId",
    CRV_TI.Name                                     "termsSupName",
    CDE.CiszResourceValueCodeCancerStage            "cancerStageId",
    CRV_CS.Name                                     "cancerStageName",
    CDE.CiszResourceValueCodeDiagKind               "kindDiagId",
    CRV_DK.Name                                     "kindDiagName",
    MDHP.MedDiagnosisCode                           "diagnosisId",
    ((CASE WHEN MDI.Cipher1 IS NULL THEN E''
      ELSE MDI.Cipher1 || E' - '
      END) || MDI.Name)                             "diagnosisName",
    MDHP.MedDeviceAmount                            "amount",
    MDHP.EnterpriseCodeSupply                       "organizationId",
    EN.Name                                         "organizationName",
    MDHP.EnterpriseSupplyText                       "organizationText",
    MDHP.Json                                       "json",
    MDHP.Note                                       "information",
    MDHP.MedDoctorCodeCancel                        "rejectDoctorId",
    MDHP.IssueDate                                  "supplyDate",
    MDHP.MedRelativesCode                           "relativeId",
    CASE WHEN MR.ManCode IS NOT NULL THEN FN_cmFIO(MR.ManCode) || ', '  || CRV_RL.Name END "relativeName",
    CASE WHEN MDHP.IssueDate IS NULL THEN NULL
        WHEN MDHP.MedRelativesCode IS NOT NULL THEN 2 ELSE 1 END "receiverId",
    MDHP.MedDeviceAmountIssued                      "amountSup",
    MDHP.EnterpriseCodeSupplyIssued                 "organizationIdSup",
    ENI.Name                                        "organizationNameSup",
    MDHP.EnterpriseSupplyTextIssued                 "organizationTextSup",
    MDHP.DateOfUse                                  "serviceDate",
    MDHP.RecordNumber                               "logNumber",
    MDHP.InstructionIssue                           "instruction",
    MDHP.ReleaseDate                                "productionDate",
    MDHP.SerialNumber                               "serialNumber",
    MDHP.Model                                      "model",
    MDHP.MedDeviceNote                              "noteDevice",
    CDE.CiszDiagnosisExtraCode                      "diagnosisExtraId",
    CDE.ClinicalDiagnosis                           "clinicalDiagnosis",
    CDE.Note                                        "note",
    CDE.DiagnosisDate                               "diagnosisDate",
    CDE.MedDoctorCode                               "diagnosisDoctorId",
    MDHP.MedDoctorCodeCancel IS NOT NULL            "isCanceled",
    MDHP.IssueDate IS NOT NULL                      "isSupplied",
    MDHP.EnterpriseCodeMedDevice                    "organizationDeviceId",
    ENM.Name                                        "organizationDeviceName",
    CDEA2.AnatomicCodes                             "anatomic",
    CASE
    WHEN MDHP.MedDoctorCodeCancel IS NOT NULL THEN 0
    WHEN MDHP.IssueDate IS NOT NULL THEN 2
    WHEN MDHP.PurposeTime IS NOT NULL THEN 1
    ELSE NULL END                                       "deviceStatus"
FROM MedDayHopsitalPurpose MDHP
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MDHP.MedDoctorCode
LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
LEFT JOIN Department D ON D.DepartmentCode = MD.DepartmentCode
LEFT JOIN MedPatient MPT ON MPT.MedpatientCode = MDHP.MedPatientCode
LEFT JOIN MedDiagnosis MDI ON MDI.MedDiagnosisCode = MDHP.MedDiagnosisCode
LEFT JOIN Enterprise EN ON EN.EnterpriseCode = MDHP.EnterpriseCodeSupply
LEFT JOIN Enterprise ENI ON ENI.EnterpriseCode = MDHP.EnterpriseCodeSupplyIssued
LEFT JOIN Enterprise ENM ON ENM.EnterpriseCode = MDHP.EnterpriseCodeMedDevice
LEFT JOIN MedRelatives MR ON MR.MedRelativesCode = MDHP.MedRelativesCode
LEFT JOIN CiszDiagnosisExtra CDE ON CDE.CiszDiagnosisExtraCode = MDHP.CiszDiagnosisExtraCode
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
LEFT JOIN CiszResourceValue CRV_PI ON CRV_PI.CiszResourceValueCode = MDHP.CiszResourceValueCodePrescribeIndication
LEFT JOIN CiszResourceValue CRV_MD ON CRV_MD.CiszResourceValueCode = MDHP.CiszResourceValueCodeMedDevice
LEFT JOIN CiszResourceValue CRV_TP ON CRV_TP.CiszResourceValueCode = MDHP.CiszResourceValueCodeTermOfPurchase
LEFT JOIN CiszResourceValue CRV_CC ON CRV_CC.CiszResourceValueCode = MDHP.CiszResourceCodeCancelCause
LEFT JOIN CiszResourceValue CRV_MDI ON CRV_MDI.CiszResourceValueCode = MDHP.CiszResourceValueCodeMedDeviceIssued
LEFT JOIN CiszResourceValue CRV_TI ON CRV_TI.CiszResourceValueCode = MDHP.CiszResourceValueCodeTermOfIssue
LEFT JOIN CiszResourceValue CRV_CS ON CRV_CS.CiszResourceValueCode = CDE.CiszResourceValueCodeCancerStage
LEFT JOIN CiszResourceValue CRV_DK ON CRV_DK.CiszResourceValueCode = CDE.CiszResourceValueCodeDiagKind
LEFT JOIN CiszResourceValue CRV_RL ON CRV_RL.CiszResourceValueCode = MR.CiszResourceValueCodeRelatedType
WHERE (MDHP.CiszResourceValueCodeMedDevice IS NOT NULL OR MDHP.CiszResourceValueCodeMedDeviceIssued IS NOT NULL)
$where
ORDER BY MDHP.CreateTime, MDHP.MedDayHopsitalPurposeCode DESC
EOT;
return selectSql($db, $sql, $sqlParams); } public function createMedicalDevices($records) { $db = $this->db ?: $this->db = connectDb(); $recordsList = is_array($records) && isset($records[0]) ? $records : [$records]; try { $db->beginTransaction(); foreach ($recordsList as $record) { $diagnosisExtra = null; if (!empty($record->diagnosisId)) { $diagnosisExtra = insertSql($db, 'CiszDiagnosisExtra', $record, "CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note,
                DiagnosisDate, MedDoctorCode", "cancerStageId, kindDiagId, clinicalDiagnosis, note,
                diagnosisDate, diagnosisDoctorId" ); } $record->diagnosisExtraId = $diagnosisExtra[0]->id ?? null; if (isset($record->anatomic) && !empty($record->diagnosisExtraId)) { $this->updateAnatomic((object)[ 'id' => $record->diagnosisExtraId, 'items' => $record->anatomic ]); } $newId = insertSql($db, 'MedDayHopsitalPurpose', $record, "MedPatientCode, PurposeTime, CancelTime,
                MedDoctorCode, CiszResourceValueCodePrescribeIndication, MedDiagnosisCode,
                CiszResourceValueCodeMedDevice, MedDeviceAmount, CiszResourceValueCodeTermOfPurchase,
                EnterpriseCodeSupply, EnterpriseSupplyText, Json,
                Note, MedDoctorCodeCancel, CiszResourceCodeCancelCause,
                IssueDate, MedRelativesCode, CiszResourceValueCodeMedDeviceIssued,
                MedDeviceAmountIssued, EnterpriseCodeSupplyIssued, EnterpriseSupplyTextIssued,
                CiszResourceValueCodeTermOfIssue, DateOfUse, RecordNumber,
                InstructionIssue, ReleaseDate, SerialNumber,
                Model, MedDeviceNote, CiszDiagnosisExtraCode,
                EnterpriseCodeMedDevice", "patientId, createDate, finishDate,
                doctorId, indicationId, diagnosisId,
                nomenclatureId, amount, termsId,
                organizationId, organizationText, json,
                information, rejectDoctorId, rejectCode,
                supplyDate, relativeId, nomenclatureSupId,
                amountSup, organizationIdSup, organizationTextSup,
                termsSupId, serviceDate, logNumber,
                instruction, productionDate, serialNumber,
                model, noteDevice, diagnosisExtraId,
                organizationDeviceId" ); } $db->commit(); return $newId; } catch (\Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; } } public function updateMedicalDevices($records) { $db = $this->db ?: $this->db = connectDb(); $recordsList = is_array($records) && isset($records[0]) ? $records : [$records]; $sql = <<<SQL
UPDATE CiszDiagnosisExtra SET
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
    DiagnosisDate = CASE WHEN ? = 1 THEN ? ELSE DiagnosisDate END,
    MedDoctorCode = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END
WHERE CiszDiagnosisExtraCode = ?
SQL;
$stmt = $db->prepare($sql); try { $db->beginTransaction(); foreach ($recordsList as $record) { $diagnosisExtraId = selectSqlValue( $db, "SELECT CiszDiagnosisExtraCode
                        FROM MedDayHopsitalPurpose
                        WHERE MedDayHopsitalPurposeCode = ?", [$record->id] ); if (!empty($diagnosisExtraId)) { $stmt->execute([ propertyExistsSQL($record, 'cancerStageId'), isUndefinedProperty($record, 'cancerStageId'), propertyExistsSQL($record, 'kindDiagId'), isUndefinedProperty($record, 'kindDiagId'), propertyExistsSQL($record, 'clinicalDiagnosis'), isUndefinedProperty($record, 'clinicalDiagnosis'), propertyExistsSQL($record, 'note'), isUndefinedProperty($record, 'note'), propertyExistsSQL($record, 'diagnosisDate'), isUndefinedProperty($record, 'diagnosisDate'), propertyExistsSQL($record, 'diagnosisDoctorId'), isUndefinedProperty($record, 'diagnosisDoctorId'), $diagnosisExtraId ]); } if (isset($record->anatomic) && !empty($diagnosisExtraId)) { $this->updateAnatomic((object)[ 'id' => $diagnosisExtraId, 'items' => $record->anatomic ]); } $result = updateSql( $db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $record, "MedPatientCode, PurposeTime, CancelTime,
                MedDoctorCode, CiszResourceValueCodePrescribeIndication, MedDiagnosisCode,
                CiszResourceValueCodeMedDevice, MedDeviceAmount, CiszResourceValueCodeTermOfPurchase,
                EnterpriseCodeSupply, EnterpriseSupplyText, Json,
                Note, MedDoctorCodeCancel, CiszResourceCodeCancelCause,
                IssueDate, MedRelativesCode, CiszResourceValueCodeMedDeviceIssued,
                MedDeviceAmountIssued, EnterpriseCodeSupplyIssued, EnterpriseSupplyTextIssued,
                CiszResourceValueCodeTermOfIssue, DateOfUse, RecordNumber,
                InstructionIssue, ReleaseDate, SerialNumber,
                Model, MedDeviceNote, CiszDiagnosisExtraCode,
                EnterpriseCodeMedDevice", "patientId, createDate, finishDate,
                doctorId, indicationId, diagnosisId,
                nomenclatureId, amount, termsId,
                organizationId, organizationText, json,
                information, rejectDoctorId, rejectCode,
                supplyDate, relativeId, nomenclatureSupId,
                amountSup, organizationIdSup, organizationTextSup,
                termsSupId, serviceDate, logNumber,
                instruction, productionDate, serialNumber,
                model, noteDevice, diagnosisExtraId,
                organizationDeviceId" ); } $db->commit(); return $result; } catch (\Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; } } public function deleteMedicalDevices($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $records); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $diagnosisCiszId = isEmptyProperty($params, 'id'); $items = isUndefinedProperty($params, 'items'); if (!$diagnosisCiszId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM CiszDiagnosisExtraAnatomic
        WHERE CiszDiagnosisExtraCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $diagnosisCiszId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO CiszDiagnosisExtraAnatomic
            (CiszDiagnosisExtraCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $diagnosisCiszId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM CiszDiagnosisExtraAnatomic
            WHERE CiszDiagnosisExtraCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$diagnosisCiszId], $toDelete)); } return true; } } handleJsonRpc('med_recipes_medicalDevices'); 