<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_inspectionsRiskFactors { private $db; public function readInspectionsRiskFactors($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : 0; $sql = <<<"EOT"
SELECT
    m.medInspectRiscFactorCode "id",
    m.uniDictCode_riskFactor "riskFactorId",
    u.value "riskFactorName",
    m.medInspectCode "inspectionsId"
FROM MedInspectRiscFactor AS m 
LEFT JOIN UniDict AS u ON u.uniDictCode = m.uniDictCode_riskFactor
WHERE m.MedInspectCode = $inspectionsId
ORDER BY 1 DESC;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createInspectionsRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedInspectRiscFactor (medInspectCode, uniDictCode_riskFactor)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $arr = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'riskFactorId') ]); $arr[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInspectRiscFactor'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $arr; } public function updateInspectionsRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedInspectRiscFactor SET
    medInspectCode = case when ? = 1 then ? else medInspectCode end,
    uniDictCode_riskFactor = case when ? = 1 then ? else uniDictCode_riskFactor end
WHERE medInspectRiscFactorCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'inspectionsId'), isUndefinedProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'riskFactorId'), isUndefinedProperty($row, 'riskFactorId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteInspectionsRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInspectRiscFactor', 'medInspectRiscFactorCode', $records); } } handleJsonRpc('med_examinations_inspectionsRiskFactors');