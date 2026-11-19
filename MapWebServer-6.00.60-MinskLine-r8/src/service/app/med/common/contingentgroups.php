<?php
 require_once(__DIR__.'/../../init.php'); class med_common_contingentGroups { private $db; public function readContingentGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    MedPrivilegeGroupCode "id",
    Name "name"
from MedPrivilegeGroup
order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateContingentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPrivilegeGroup set
    Name = case when ? = 1 then ? else Name end
where MedPrivilegeGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteContingentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPrivilegeGroup 
where MedPrivilegeGroupCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createContingentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedPrivilegeGroup (Cipher , Name)
values 
    ((select incstr(trim(MAX(repeat(' ', 20 - length(Cipher)) || Cipher))) from MedPrivilegeGroup),  ?);
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPrivilegeGroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } $service = new Junior\Server(new med_common_contingentGroups()); $service->process();