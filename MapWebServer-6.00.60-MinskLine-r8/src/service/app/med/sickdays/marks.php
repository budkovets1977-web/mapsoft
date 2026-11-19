<?php
 require_once(__DIR__.'/../../init.php'); class med_sickdays_marks { private $db; public function readMarks($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    UniDictCode "id",
    Value "name",
    Value1 "markType",
    ValueInteger "important"
from UniDict
    where DictNumber = 20
    order by Value
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateMarks($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update UniDict  set
    Value = case when ? = 1 then ? else Value end,
    ValueInteger = case when ? = 1 then ? else ValueInteger end,
    Value1 = case when ? = 1 then ? else Value1 end
where UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'important'), isUndefinedProperty($row, 'important'), propertyExistsSQL($row, 'markType'), isUndefinedProperty($row, 'markType'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createMarks($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into UniDict ( Value, ValueInteger, Value1, DictNumber)
values (?, ?, ?, 20)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'important'), isUndefinedProperty($row, 'markType') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_UniDict'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteMarks($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'UniDict', 'UniDictCode', $records); } } if (!isset($notExecute)) { $service = new Junior\Server(new med_sickdays_marks()); $service->process(); }