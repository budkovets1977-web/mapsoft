<?php
 require_once(__DIR__.'/../../init.php'); class map_tables_dictionaries { private $db; public function readDictionaries () { $db = $this->db ?: $this->db = connectDb(); $sql = <<<'EOT'
select
    d.GenTableDictCode "id",
    d.GenTableSetCode "genTableSetCode",
    d.Name "name",
    d.DataLink "dataLink"
from GenTableDict d
where d.GenTableSetCode = ?
order by 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([(int) getSessionValue('mapGenTableSetCode')]); return $stmt->fetchAll(); } public function readDictionariesData () { $db = $this->db ?: $this->db = connectDb(); $sql = <<<'EOT'
select
    v.GenTableDictDataCode "id",
    v.GenTableDictCode "genTableDictCode",
    v.Cipher "cipher",
    v.Value "value"
from GenTableDict d
     join GenTableDictData v on
        v.GenTableDictCode = d.GenTableDictCode
where d.GenTableSetCode = ?
order by 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([(int) getSessionValue('mapGenTableSetCode')]); return $stmt->fetchAll(); } public function createDictionariesData ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into GenTableDictData (
    GenTableDictCode,
    Cipher,
    Value
) values (
    ?, ?, ?
)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ isEmptyProperty($row, 'genTableDictCode'), isEmptyProperty($row, 'cipher'), isEmptyProperty($row, 'value') ]); $ret[] = $rec = new stdClass; $rec->id = $db->isSybase ? $db->lastInsertId('GenTableDictDataCode') : $db->lastInsertId('seq_GenTableDictData'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateDictionariesData ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update
    GenTableDictData
set
    GenTableDictCode = case when ? = 1 then ? else GenTableDictCode end,
    Cipher = case when ? = 1 then ? else Cipher end,
    Value = case when ? = 1 then ? else Value end
where
    GenTableDictDataCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'genTableDictCode'), isUndefinedProperty($row, 'genTableDictCode'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'value'), isUndefinedProperty($row, 'value'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDictionariesData ($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from GenTableDictData where GenTableDictDataCode $in
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } } $service = new Junior\Server(new map_tables_dictionaries()); $service->process(); 