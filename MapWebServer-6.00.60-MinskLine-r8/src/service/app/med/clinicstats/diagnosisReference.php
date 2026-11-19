<?php
 require_once(__DIR__ . '/../../init.php'); class med_clinicstats_diagnosisReference { private $db; public $diagnosisReference; public function readDiagnosisReferences() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT mdr.MedDiagnosisReferenceCode "id",
       mdr.Cipher1 "cipher",
       mdr.MedDiagnosisCode "diagnosisId",
       md.Cipher1 || ' ' || md.Name "diagnosisName"
FROM MedDiagnosisReference mdr
LEFT JOIN MedDiagnosis md ON md.MedDiagnosisCode = mdr.MedDiagnosisCode
EOT;
$this->diagnosisReference = selectSql($db, $sql); return $this->diagnosisReference; } public function createDiagnosisReferences($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedDiagnosisReference (Cipher1, MedDiagnosisCode)
VALUES(?, ?)
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { executeSql($db, $sql, [ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'diagnosisId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedDiagnosisReference'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateDiagnosisReferences($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedDiagnosisReference SET
  Cipher1 = case when ? = 1 then ? else Cipher1 end,
  MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end
WHERE MedDiagnosisReferenceCode = ?
EOT;
if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { executeSql($db, $sql, [ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), isEmptyProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDiagnosisReferences($records) { deleteSql($this->db, 'MedDiagnosisReference', 'MedDiagnosisReferenceCode', $records); } } handleJsonRpc('med_clinicstats_diagnosisReference');