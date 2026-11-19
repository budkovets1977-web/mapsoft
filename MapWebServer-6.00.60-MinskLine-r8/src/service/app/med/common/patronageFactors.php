<?php
 require_once(__DIR__.'/../../init.php'); class med_common_patronageFactors { private $db; public function readPatronageFactors($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    MedPatronageFactorCode "id",
    AgeMonth "ageMonth",
    Periodicity "periodicity"
from MedPatronageFactor
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updatePatronageFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedPatronageFactor  set
    AgeMonth = case when ? = 1 then ? else AgeMonth end,
    Periodicity = case when ? = 1 then ? else Periodicity end
where MedPatronageFactorCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'ageMonth'), isUndefinedProperty($row, 'ageMonth'), propertyExistsSQL($row, 'periodicity'), isUndefinedProperty($row, 'periodicity'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createPatronageFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedPatronageFactor (AgeMonth, Periodicity)
values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'ageMonth'), isUndefinedProperty($row, 'periodicity'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatronageFactor'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatronageFactors($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedPatronageFactor', 'MedPatronageFactorCode', $records); } } $service = new Junior\Server(new med_common_patronageFactors()); $service->process(); 