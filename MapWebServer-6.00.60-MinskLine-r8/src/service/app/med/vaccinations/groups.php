<?php
 require_once(__DIR__.'/../../init.php'); class med_vaccinations_groups { private $db; public function readGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    medInoculateGroupCode "id",
    name "name"
FROM
    MedInoculateGroup
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
insert into MedInoculateGroup (name)
values (?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInoculateGroup'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedInoculateGroup set
       name = case when ? = 1 then ? else name end
where medInoculateGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInoculateGroup', 'medInoculateGroupCode', $records); } } $service = new Junior\Server(new med_vaccinations_groups()); $service->process(); 