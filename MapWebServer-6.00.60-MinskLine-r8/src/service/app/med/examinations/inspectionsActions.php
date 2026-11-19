<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_inspectionsActions { private $db; public function readInspectionsActions($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : 0; $sql = <<<"EOT"
SELECT
    m.MedInspectActionCode "id",
    COALESCE(m.Executed, 0) "executed",
    m.MedActionCode "actionId",
    a.Name "actionName",
    m.MedActionResultCode "actionResultId",
    m.MedInspectCode "inspectionsId",
    COALESCE(m.Recommend, 0) "recommend",
    COALESCE(m.RecommendBefore, 0) "recommendBefore",
    DI.Name "diagnosisName",
    DI.Cipher1 "diagnosisCipher",
    m.MedDiagnosisCode "diagnosisId",
    M.CiszResourceValueCodeDiagKind    "kindDiagId",
    CRV.Name                           "kindDiagName",
    M.ClinicalDiagnosis                "clinicalDiagnosis",
    M.CiszResourceValueCodeCancerStage "cancerStageId",
    CASE WHEN M.MedDiagnosisCode IS NOT NULL THEN DI.Cipher1 || ' - ' || DI.Name END "diagnosisFullName",
    M.MedDoctorCode             "doctorsId",
    M.Note                      "note",
    FN_cmFIO(D.ManCode, 1)      "doctorsFIO",
    M.ActionDate                "actionDate",
    MIA2.AnatomicCodes  "anatomic"
FROM MedInspectAction AS m LEFT OUTER JOIN MedAction AS a ON a.MedActionCode = m.MedActionCode
LEFT OUTER JOIN MedDiagnosis AS DI ON DI.MedDiagnosisCode = m.MedDiagnosisCode
LEFT JOIN MedDoctor D ON D.MedDoctorCode = M.MedDoctorCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = M.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        MIA.MedInspectActionCode,
        string_agg(
            MIA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY MIA.MedInspectActionCode
        ) AS AnatomicCodes
    FROM MedInspectAnatomic MIA
    JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MIA.CiszResourceValueCodeAnatomic
    GROUP BY MIA.MedInspectActionCode
) MIA2 ON MIA2.MedInspectActionCode = M.MedInspectActionCode
WHERE m.MedInspectCode = $inspectionsId
ORDER BY 4 DESC;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createInspectionsActions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedInspectAction (Executed, MedActionCode, MedActionResultCode, MedInspectCode, Recommend, RecommendBefore, MedDiagnosisCode,
                              MedDoctorCode, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, CiszResourceValueCodeCancerStage, Note, ActionDate)
VALUES (?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'executed'), isEmptyProperty($row, 'actionId'), isEmptyProperty($row, 'actionResultId'), isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'recommend'), isEmptyProperty($row, 'recommendBefore'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'doctorsId'), isEmptyProperty($row, 'kindDiagId'), isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'cancerStageId'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'actionDate') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInspectAction'); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $rec->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateInspectionsActions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedInspectAction SET
    Executed = case when ? = 1 then ? else Executed end,
    MedActionCode = case when ? = 1 then ? else MedActionCode end,
    MedActionResultCode = case when ? = 1 then ? else MedActionResultCode end,
    MedInspectCode = case when ? = 1 then ? else MedInspectCode end,
    Recommend = case when ? = 1 then ? else Recommend end,
    RecommendBefore = case when ? = 1 then ? else RecommendBefore end,
    MedDiagnosisCode= case when ? = 1 then ? else MedDiagnosisCode end,
    MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
    CiszResourceValueCodeDiagKind = case when ? = 1 then ? else CiszResourceValueCodeDiagKind end,
    ClinicalDiagnosis = case when ? = 1 then ? else ClinicalDiagnosis end,
    CiszResourceValueCodeCancerStage = case when ? = 1 then ? else CiszResourceValueCodeCancerStage end,
    Note = case when ? = 1 then ? else Note end,
    ActionDate = case when ? = 1 then ? else ActionDate end
WHERE MedInspectActionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'executed'), isUndefinedProperty($row, 'executed'), propertyExistsSQL($row, 'actionId'), isUndefinedProperty($row, 'actionId'), propertyExistsSQL($row, 'actionResultId'), isUndefinedProperty($row, 'actionResultId'), propertyExistsSQL($row, 'inspectionsId'), isUndefinedProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'recommend'), isUndefinedProperty($row, 'recommend'), propertyExistsSQL($row, 'recommendBefore'), isUndefinedProperty($row, 'recommendBefore'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'doctorsId'), isUndefinedProperty($row, 'doctorsId'), propertyExistsSQL($row, 'kindDiagId'), isUndefinedProperty($row, 'kindDiagId'), propertyExistsSQL($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'cancerStageId'), isUndefinedProperty($row, 'cancerStageId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'actionDate'), isUndefinedProperty($row, 'actionDate'), $row->id ]); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $row->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteInspectionsActions($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInspectAction', 'MedInspectActionCode', $records); } public function readAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); if (!$items) { return []; } $sql = <<<SQL
SELECT
    CRV.CiszResourceValueCode AS id,
    CRV.Name AS name
FROM CiszResourceValue CRV
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectId = isEmptyProperty($params, 'inspectId'); $items = isUndefinedProperty($params, 'items'); if (!$inspectId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedInspectAnatomic
        WHERE MedInspectActionCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $inspectId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedInspectAnatomic
            (MedInspectActionCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $inspectId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedInspectAnatomic
            WHERE MedInspectActionCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$inspectId], $toDelete)); } return true; } } $service = new Junior\Server(new med_examinations_inspectionsActions()); $service->process(); 