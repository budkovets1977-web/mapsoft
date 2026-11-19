<?php
 require_once(__DIR__.'/../../init.php'); class med_common_professions { private $db; public function readProfessions($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    UniDictCode "id",
    Value "name"
from UniDict
where (DictNumber = 19)
order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateProfessions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update UniDict set
       Value = case when ? = 1 then ? else Value end
where UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteProfessions($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from UniDict where UniDictCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createProfessions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into UniDict (DictNumber, Value)
values (19, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_UniDict'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('med_common_professions');