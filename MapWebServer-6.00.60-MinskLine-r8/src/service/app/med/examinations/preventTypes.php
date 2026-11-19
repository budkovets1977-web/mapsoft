<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_preventTypes { private $db; public function readPreventTypes($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventTypesId = isset($params->preventTypesId) && isEmptyProperty($params, 'preventTypesId') ? $params->preventTypesId : null; $sql = <<<"EOT"
SELECT
    MC.name "conclusionName",
    MC.medconclusioncode "conclusionId"
FROM
    MedPreventConclusion MPC
    left join MedConclusion MC on MPC.MedConclusionCode = MC.MedConclusionCode
WHERE MPC.MedPreventCode = $preventTypesId
    and EXISTS(SELECT MCG.MedConclusionGroupCode FROM MedConclusionGroup MCG
               WHERE MCG.MedConclusionCode = MC.MedConclusionCode)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readOldPreventTypes($params) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : null; $sql = <<<"EOT"
SELECT
    MIC.medinspectconclusioncode "id",
    MC.name "conclusionName",
    MC.medconclusioncode "conclusionId",
    MCG.name "name",
    MCG.medconclusiongroupcode "conclusionGroupId",
    MIC.note "note"
FROM
    MedConclusion MC
    JOIN MedInspectConclusion MIC on MIC.MedConclusionCode = MC.MedConclusionCode
    LEFT JOIN MedConclusionGroup MCG on MCG.MedConclusionGroupCode = MIC.MedConclusionGroupCode
WHERE
    MIC.MedInspectCode = $inspectionsId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPreventTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedInspectConclusion (medinspectcode, medconclusiongroupcode, note, medconclusioncode)
VALUES (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'conclusionGroupId'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'conclusionId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInspectConclusion'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePreventTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedInspectConclusion SET
    medinspectcode = case when ? = 1 then ? else medinspectcode end,
    medconclusiongroupcode = case when ? = 1 then ? else medconclusiongroupcode end,
    note = case when ? = 1 then ? else note end,
    medconclusioncode = case when ? = 1 then ? else medconclusioncode end
WHERE medinspectconclusioncode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'inspectionsId'), isUndefinedProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'conclusionGroupId'), isUndefinedProperty($row, 'conclusionGroupId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'conclusionId'), isUndefinedProperty($row, 'conclusionId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePreventTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
DELETE FROM MedInspectConclusion WHERE medinspectcode = $records
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } } $service = new Junior\Server(new med_examinations_preventTypes()); $service->process(); 