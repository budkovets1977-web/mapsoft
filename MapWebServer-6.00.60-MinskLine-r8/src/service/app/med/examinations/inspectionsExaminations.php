<?php
require_once (__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/card/cards.php'); resumeJsonRpc(); class med_examinations_inspectionsExaminations { private $db; public function readInspectionsExaminations($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : 0; $driverLicense = isEmptyProperty($params, 'driverLicense'); $sqlDLFields = ''; $sqlDLJoin = ''; $sqlDLWhere = ''; if ($driverLicense) { $sqlDLFields = <<<SQL
    HP.Name                     "docName",
    HP.CreateTime               "changeTime",
    U.Nick                      "userName",
    MVD.MedVisitDocumentCode    "docId",
    HP.HtmPageCode              "pageId",
    MVD.MedVisitPatientCode     "medVisitPatientId",
    DI1.Cipher1                 "additionalDiagnosisCipher",
    DI1.Name                    "additionalDiagnosisName",
    R1.MedDiagnosisCode         "additionalDiagnosisId",
    1                           "driverExamination",
SQL;
$sqlDLJoin = <<<SQL
LEFT JOIN MedVisitDocument MVD ON MVD.MedInspectResultCode = R.MedInspectResultCode
    AND MVD.MedVisitDocumentCode = (
        SELECT MAX(_MVD.MedVisitDocumentCode)
        FROM MedVisitDocument _MVD
        WHERE _MVD.MedInspectResultCode = MVD.MedInspectResultCode
    )
LEFT JOIN HtmPage HP ON HP.HtmPageCode = MVD.HtmPageCode
LEFT JOIN Users U ON U.UserCode = HP.UserCode
LEFT JOIN MedInspectResult R1 ON R1.MedInspectCode = R.MedInspectCode
    AND R1.MedProfessionCode = R.MedProfessionCode
    AND R1.PrimaryDiagnosis IS NULL
LEFT JOIN MedDiagnosis DI1 ON DI1.MedDiagnosisCode = R1.MedDiagnosisCode
SQL;
$sqlDLWhere = <<<SQL
AND R.PrimaryDiagnosis = 1
SQL;
} $sql = <<<SQL
SELECT
    R.MedInspectResultCode      "id",
    $sqlDLFields
    R.MedInspectCode            "inspectionsId",
    R.Manual                    "manual",
    R.MedProfessionCode         "professionsId",
    R.MedDoctorCode             "doctorsId",
    R.PlanDate                  "planDate",
    R.InspectDate               "inspectDate",
    R.MedDiagnosisCode          "diagnosisId",
    R.Note                      "note",
    P.Name                      "professionsName",
    FN_cmFIO(D.ManCode, 1)      "doctorsFIO",
    DI.Cipher1                  "cipher",
    DI.Name                     "diagnosisName",
    COALESCE(R.FirstTime, 0)    "firstTime",
    R.ProfDisease               "profDisease",
    R.Contraindication          "contraindication",
    R.DecisionDiagnosis         "decisionDiagnosis",
    R.PrimaryDiagnosis          "primaryDiagnosis",
    R.CiszResourceValueCodeDiagKind    "kindDiagId",
    CRV.Name                           "kindDiagName",
    CRV.Code                           "kindDiagCode",
    R.ClinicalDiagnosis                "clinicalDiagnosis",
    R.CiszResourceValueCodeCancerStage "cancerStageId",
    MIA2.AnatomicCodes  "anatomic",
    CASE WHEN R.MedDiagnosisCode IS NOT NULL THEN DI.Cipher1 || ' - ' || DI.Name END "diagnosisFullName"
FROM MedInspectResult R
LEFT OUTER JOIN MedProfession P ON P.MedProfessionCode = R.MedProfessionCode
LEFT OUTER JOIN MedDoctor D ON D.MedDoctorCode = R.MedDoctorCode
LEFT OUTER JOIN MedDiagnosis DI ON DI.MedDiagnosisCode = R.MedDiagnosisCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = R.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        MIA.MedInspectResultCode,
        string_agg(
            MIA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY MIA.MedInspectResultCode
        ) AS AnatomicCodes
    FROM MedInspectAnatomic MIA
    JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MIA.CiszResourceValueCodeAnatomic
    GROUP BY MIA.MedInspectResultCode
) MIA2 ON MIA2.MedInspectResultCode = R.MedInspectResultCode
$sqlDLJoin
WHERE R.MedInspectCode = $inspectionId
$sqlDLWhere
ORDER BY R.PlanDate
SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function createInspectionsExaminations($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedInspectResult (MedInspectCode, Manual, MedProfessionCode, MedDoctorCode, PlanDate,
    InspectDate, MedDiagnosisCode, Note, FirstTime, ProfDisease, Contraindication, PrimaryDiagnosis,
    CiszResourceValueCodeDiagKind, ClinicalDiagnosis, CiszResourceValueCodeCancerStage)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
       ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'manual'), isEmptyProperty($row, 'professionsId'), isEmptyProperty($row, 'doctorsId'), isEmptyProperty($row, 'planDate'), isEmptyProperty($row, 'inspectDate'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'firstTime'), isEmptyProperty($row, 'profDisease'), isEmptyProperty($row, 'contraindication'), isEmptyProperty($row, 'primaryDiagnosis'), isEmptyProperty($row, 'kindDiagId'), isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'cancerStageId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInspectResult'); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $rec->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateInspectionsExaminations($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
UPDATE MedInspectResult SET
    MedInspectCode      = CASE WHEN ? = 1 THEN ? ELSE MedInspectCode END,
    Manual              = CASE WHEN ? = 1 THEN ? ELSE Manual END,
    MedProfessionCode   = CASE WHEN ? = 1 THEN ? ELSE MedProfessionCode END,
    MedDoctorCode       = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END,
    PlanDate            = CASE WHEN ? = 1 THEN ? ELSE PlanDate END,
    InspectDate         = CASE WHEN ? = 1 THEN ? ELSE InspectDate END,
    MedDiagnosisCode    = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCode END,
    Note                = CASE WHEN ? = 1 THEN ? ELSE Note END,
    FirstTime           = CASE WHEN ? = 1 THEN ? ELSE FirstTime END,
    ProfDisease         = CASE WHEN ? = 1 THEN ? ELSE ProfDisease END,
    Contraindication    = CASE WHEN ? = 1 THEN ? ELSE Contraindication END,
    PrimaryDiagnosis    = CASE WHEN ? = 1 THEN ? ELSE PrimaryDiagnosis END,
    DecisionDiagnosis   = CASE WHEN ? = 1 THEN ? ELSE DecisionDiagnosis END,
    CiszResourceValueCodeDiagKind   = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis               = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END
WHERE MedInspectResultCode = ?
SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (propertyExistsSQL($row, 'decisionDiagnosis') && isEmptyProperty($row, 'diagnosisId') === null) { $row->decisionDiagnosis = null; } $stmt->execute([ propertyExistsSQL($row, 'inspectionsId'), isUndefinedProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'manual'), isUndefinedProperty($row, 'manual'), propertyExistsSQL($row, 'professionsId'), isUndefinedProperty($row, 'professionsId'), propertyExistsSQL($row, 'doctorsId'), isUndefinedProperty($row, 'doctorsId'), propertyExistsSQL($row, 'planDate'), isUndefinedProperty($row, 'planDate'), propertyExistsSQL($row, 'inspectDate'), isUndefinedProperty($row, 'inspectDate'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'firstTime'), isUndefinedProperty($row, 'firstTime'), propertyExistsSQL($row, 'profDisease'), isUndefinedProperty($row, 'profDisease'), propertyExistsSQL($row, 'contraindication'), isUndefinedProperty($row, 'contraindication'), propertyExistsSQL($row, 'primaryDiagnosis'), isUndefinedProperty($row, 'primaryDiagnosis'), propertyExistsSQL($row, 'decisionDiagnosis'), isEmptyProperty($row, 'decisionDiagnosis'), propertyExistsSQL($row, 'kindDiagId'), isUndefinedProperty($row, 'kindDiagId'), propertyExistsSQL($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'cancerStageId'), isUndefinedProperty($row, 'cancerStageId'), $row->id ]); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $row->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteInspectionsExaminations($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $visitPatientId = isEmptyProperty($row, 'medVisitPatientId'); $visitDocumentId = isEmptyProperty($row, 'docId'); $driverExamination = isEmptyProperty($row, 'driverExamination'); if ($visitPatientId) { $params = new stdClass(); $params->visitPatientId = $visitPatientId; $params->visitDocumentId = $visitDocumentId; $this->deleteVisitDocument($params); } $sql = <<<SQL
DELETE FROM MedInspectResult
WHERE MedInspectResultCode = ?
RETURNING *
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $row->id ]); if ($driverExamination) { $deletedRow = $stmt->fetch(); $inspectId = $deletedRow['medinspectcode']; $professionId = $deletedRow['medprofessioncode']; $sql = <<<SQL
DELETE FROM MedInspectResult
WHERE PrimaryDiagnosis IS NULL
    AND MedInspectCode = ?
    AND MedProfessionCode = ?
SQL;
executeSql($db, $sql, [ $inspectId, $professionId ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
INSERT INTO MedVisitDocument (
    MedVisitPatientCode, Type, UserCode,
    HtmTemplateCode, Note, ChangeTime,
    CreateTime, MedInspectCode
)
VALUES (
    ?, ?, ?,
    ?, 'Новая запись', LOCALTIMESTAMP,
    LOCALTIMESTAMP, ?
)
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { if (isEmptyProperty($params, 'isAdd')) { $medCard = new med_card_cards(); $visitPatient = $medCard->createVisitPatient($params); $ret->visitId = isEmptyProperty($visitPatient, 'visitId'); $visitPatientId = isEmptyProperty($visitPatient, 'id'); $ret->visitPatientId = $visitPatientId; } else { $visitPatientId = isEmptyProperty($params, 'visitPatientId'); } $stmt->execute([ $visitPatientId, isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId'), isEmptyProperty($params, 'inspectId') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId', 0); $pageId = isEmptyProperty($params, 'pageId'); $medInspectResultId = isEmptyProperty($params, 'medInspectResultId'); $sql = <<<SQL
UPDATE MedVisitDocument SET
    HtmPageCode = ?,
    ChangeTime = LOCALTIMESTAMP,
    MedInspectResultCode = ?
WHERE MedVisitDocumentCode = ?
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $pageId, $medInspectResultId, $documentId ]); $this->updateMedInspectResultFromDoc($params); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateMedInspectResultFromDoc($params) { $db = $this->db ?: $this->db = connectDb(); $pageId = isEmptyProperty($params, 'pageId'); $medInspectResultId = isEmptyProperty($params, 'medInspectResultId'); $inspectDate = NULL; $medDiagnosisId = NULL; $additionalDiagnosisId = NULL; $medDoctorId = NULL; $note = ''; $decision = ''; $period = ''; $decisionComment = ''; $sql = <<<SQL
SELECT *
FROM HtmUnit
WHERE htmPageCode = ?
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $pageId ]); $docData = $stmt->fetchAll(); foreach ($docData as $row) { switch ($row['name']) { case 'dateVisit': $inspectDate = trim($row['value']); break; case 'diagnosisMain': $longValue = trim((string)($row['longvalue'] ?? '')); $value = $longValue ?: trim((string)($row['value'] ?? '')); $json = safe_json_decode($value); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $id = isEmptyProperty($json, 'code'); $id = $id ?: isEmptyProperty($json, 'id'); $medDiagnosisId = empty($displayValue) ? null : $id; } break; case 'diagnosisMainAdd': $longValue = trim((string)($row['longvalue'] ?? '')); $value = $longValue ?: trim((string)($row['value'] ?? '')); $json = safe_json_decode($value); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $id = isEmptyProperty($json, 'code'); $id = $id ?: isEmptyProperty($json, 'id'); $additionalDiagnosisId = empty($displayValue) ? null : $id; } break; case 'doctorMain': $longValue = trim((string)($row['longvalue'] ?? '')); $value = $longValue ?: trim((string)($row['value'] ?? '')); $json = safe_json_decode($value); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $id = isEmptyProperty($json, 'code'); $id = $id ?: isEmptyProperty($json, 'id'); $medDoctorId = empty($displayValue) ? null : $id; } break; case 'Годность': $decision = trim((string)($row['value'] ?? '')); break; case 'Сроки': $periodText[1] = '5 лет'; $periodText[2] = '1 год'; $periodText[3] = '2 года'; $periodText[4] = '3 года'; $value = trim((string)($row['value'] ?? '')); $index = (int)$value; $period = mb_strlen($value, 'utf8') > 1 ? $value : (in_array($index, [1, 2, 3, 4]) ? $periodText[$index] : ''); break; case 'годность_текст': $value = trim((string)($row['value'] ?? '')); $longValue = trim((string)($row['longvalue'] ?? '')); $decisionComment = $value ?: $longValue; break; } } $note = $decision ? $decision : ''; $note = $note && $period ? $note . ', ' . $period : $note; $note = $note && $decisionComment ? $note . ', ' . $decisionComment : $note; if (mb_strlen($note, 'utf8') > 247) { $note = mb_substr($note, 0, 247) . '...'; } $sql = <<<SQL
UPDATE MedInspectResult SET
    InspectDate = ?,
    MedDiagnosisCode = ?,
    MedDoctorCode = ?,
    Note = ?
WHERE MedInspectResultCode = ?
RETURNING *
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $inspectDate, $medDiagnosisId, $medDoctorId, $note, $medInspectResultId ]); if ($additionalDiagnosisId) { $row = $stmt->fetch(); $inspectId = $row['medinspectcode']; $professionId = $row['medprofessioncode']; $sql = <<<SQL
UPDATE MedInspectResult SET
    InspectDate = :inspectDate,
    MedDiagnosisCode = :diagnosisId,
    MedDoctorCode = :doctorId
WHERE PrimaryDiagnosis IS NULL
    AND MedInspectCode = :inspectId
    AND MedProfessionCode = :professionId;

INSERT INTO MedInspectResult (
    MedInspectCode, MedProfessionCode, InspectDate,
    MedDiagnosisCode, MedDoctorCode
)
SELECT
    :inspectId, :professionId, :inspectDate,
    :diagnosisId, :doctorId
WHERE NOT EXISTS (
    SELECT 1
    FROM MedInspectResult
    WHERE PrimaryDiagnosis IS NULL
        AND MedInspectCode = :inspectId
        AND MedProfessionCode = :professionId
);
SQL;
executeSql($db, $sql, [ ':inspectDate' => $inspectDate, ':diagnosisId' => $additionalDiagnosisId, ':doctorId' => $medDoctorId, ':inspectId' => $inspectId, ':professionId' => $professionId ], true); } return true; } public function deleteVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $inspectResultId = isEmptyProperty($params, 'inspectResultId'); if ($this->checkVisitDocument($params)) { $this->deleteDocument($params); } else { $this->deleteVisitAndDocument($params); } if ($inspectResultId) { $this->clearMedInspectResult($params); } return true; } public function deleteVisitAndDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitPatientId = isEmptyProperty($params, 'visitPatientId'); try { $sql = <<<SQL
DELETE FROM MedVisitPatient
WHERE MedVisitPatientCode = ?
RETURNING *
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $visitPatientId ]); $row = $stmt->fetch(); $visitId = $row['medvisitcode']; $patientId = $row['medpatientcode']; $sql = <<<SQL
DELETE FROM MedVisitPatient
WHERE MedVisitCode = ?
    AND MedPatientCode = ?
SQL;
executeSql($db, $sql, [ $visitId, $patientId ]); } catch (Throwable $e) { if ($e->getCode() === "30001") { $lockPeriod = new map_common_periodLock(); $lockDate = safe_date_create($lockPeriod->getVisitLockDate()); throw new Exception("Нельзя удалить запись в закрытом периоде по " . date_format($lockDate, "d.m.Y") . "!"); } throw $e; } return true; } public function deleteDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitDocumentId = isEmptyProperty($params, 'visitDocumentId'); return deleteSql($db, 'MedVisitDocument', 'MedVisitDocumentCode', $visitDocumentId); } private function checkVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitPatientId = isEmptyProperty($params, 'visitPatientId'); $visitDocumentId = isEmptyProperty($params, 'visitDocumentId'); $sql = <<<SQL
SELECT 1 "exists"
FROM MedVisitDocument
WHERE MedVisitPatientCode = ?
    AND MedVisitDocumentCode <> ?
LIMIT 1
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $visitPatientId, $visitDocumentId ]); return fetchValue($stmt, 'exists') ? TRUE : FALSE; } private function clearMedInspectResult($params) { $db = $this->db ?: $this->db = connectDb(); $inspectResultId = isEmptyProperty($params, 'inspectResultId'); $sql = <<<SQL
UPDATE MedInspectResult SET
    InspectDate = NULL,
    MedDiagnosisCode = NULL,
    MedDoctorCode = NULL,
    Note = NULL,
    DecisionDiagnosis = NULL
WHERE MedInspectResultCode = ?
RETURNING *
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $inspectResultId ]); $row = $stmt->fetch(); $inspectId = $row['medinspectcode']; $professionId = $row['medprofessioncode']; $sql = <<<SQL
DELETE FROM MedInspectResult
WHERE PrimaryDiagnosis IS NULL
    AND MedInspectCode = ?
    AND MedProfessionCode = ?
SQL;
executeSql($db, $sql, [ $inspectId, $professionId ]); return true; } public function readAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); if (!$items) { return []; } $sql = <<<SQL
SELECT
    CRV.CiszResourceValueCode AS id,
    CRV.Name AS name
FROM CiszResourceValue CRV
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectId = isEmptyProperty($params, 'inspectId'); $items = isUndefinedProperty($params, 'items'); if (!$inspectId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedInspectAnatomic
        WHERE MedInspectResultCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $inspectId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedInspectAnatomic
            (MedInspectResultCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $inspectId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedInspectAnatomic
            WHERE MedInspectResultCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$inspectId], $toDelete)); } return true; } } if (!isset($inspectExamNotExecute)) { handleJsonRpc('med_examinations_inspectionsExaminations'); }