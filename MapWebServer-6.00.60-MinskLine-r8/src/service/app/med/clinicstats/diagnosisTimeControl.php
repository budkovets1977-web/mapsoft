<?php
 require_once(__DIR__.'/../../init.php'); class med_clinicstats_diagnosisTimeControl { private $db; public function readDiagnosisTimeControl($params = null) { $db = $this->db ?: $this->db = connectDb(); $currId = isEmptyProperty($params, 'currId'); $where = $currId ? "where mdc.medDiagnosisTimeControlCode = $currId" : ""; $sql = <<<"EOT"
SELECT
    mdc.medDiagnosisTimeControlCode "id",
    mdc.controlDays "days",
    mdc.medDiagnosisCode "diagnosisId",
    md.cipher1 "diagnosisCipher",
    md.name "diagnosisName"
FROM MedDiagnosisTimeControl AS mdc
LEFT JOIN MedDiagnosis AS md ON md.medDiagnosisCode = mdc.medDiagnosisCode
$where
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $currId ? $stmt->fetch() : $stmt->fetchAll(); } public function updateDiagnosisTimeControl($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 UPDATE MedDiagnosisTimeControl SET
    medDiagnosisCode = CASE WHEN ? = 1 THEN ? ELSE medDiagnosisCode END,
    controlDays = CASE WHEN ? = 1 THEN ? ELSE controldays END
WHERE medDiagnosisTimeControlCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'diagnosisId'), isEmptyProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'days'), isUndefinedProperty($row, 'days'), $row->id ]); $param = new stdClass(); $param->currId = $row->id; $ret [] = $this->readDiagnosisTimeControl($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createDiagnosisTimeControl($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedDiagnosisTimeControl (medDiagnosisCode, controlDays)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'days') ]); $param = new stdClass(); $param->currId = $db->lastInsertId('seq_MedDiagnosisTimeControl'); $ret [] = $this->readDiagnosisTimeControl($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteDiagnosisTimeControl($records) { return deleteSql($this->db, 'MedDiagnosisTimeControl', 'medDiagnosisTimeControlCode', $records); } } $service = new Junior\Server(new med_clinicstats_diagnosisTimeControl()); $service->process(); 