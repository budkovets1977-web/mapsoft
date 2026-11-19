<?php
 require_once(__DIR__.'/../../init.php'); class med_clinicstats_traumaTypes { private $db; public function readTraumaTypes ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"

select
    medtraumacode "id",
    code "code",
    name "name",
    reportcolumn "reportColumn",
    intoxication "intoxication"
from MedTrauma
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateTraumaTypes ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedTrauma set
    code = case when ? = 1 then ? else code end,
    name = case when ? = 1 then ? else name end,
    reportcolumn = case when ? = 1 then ? else reportcolumn end,
    intoxication = case when ? = 1 then ? else intoxication end
where medtraumacode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $db->beginTransaction(); try{ foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'reportColumn'), isUndefinedProperty($row, 'reportColumn'), propertyExistsSQL($row, 'intoxication'), isUndefinedProperty($row, 'intoxication'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createTraumaTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedTrauma ( code, name, reportcolumn, intoxication)
values(?,?,?,?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'code'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'reportColumn'), isUndefinedProperty($row, 'intoxication') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedTrauma'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteTraumaTypes($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedTrauma', 'medtraumacode', $records); } } $service = new Junior\Server(new med_clinicstats_traumatypes()); $service->process();