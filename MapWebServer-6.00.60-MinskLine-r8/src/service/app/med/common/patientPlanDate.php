<?php
 require_once(__DIR__.'/../../init.php'); class med_common_patientPlanDate { private $db; public function readPatientPlanDate($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    medpatientplandatecode "id",
    ondate "onDate"
FROM medpatientplandate
WHERE enterprisecode = $enterpriseCode
ORDER BY ondate
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updatePatientPlanDate($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
UPDATE medpatientplandate set
    ondate = case when ? = 1 then ? else ondate end
WHERE medpatientplandatecode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createPatientPlanDate($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO medpatientplandate (ondate, enterprisecode)
values (?, $enterpriseCode)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'onDate'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medpatientplandate'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatientPlanDate($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medpatientplandate', 'medpatientplandatecode', $records); } } $service = new Junior\Server(new med_common_patientPlanDate()); $service->process();