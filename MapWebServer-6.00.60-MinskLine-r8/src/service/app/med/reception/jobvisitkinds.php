<?php
 require_once(__DIR__.'/../../init.php'); class med_reception_visitkinds { private $db; public function readJobVisitKinds($params = null) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
    MJVK.MedJobVisitKindCode "id",
    MJVK.Name "name",
    MJVK.MainFlag "main",    
    MJVK.ChildFlag "childFlag",
    MJVK.VisitPlace "visitPlace",
    MJVK.VisitMark "visitMark",          
    MJVK.Position "position"
from MedJobVisitKind MJVK
order by main DESC, position ASC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateJobVisitKinds($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedJobVisitKind set
       Name = case when ? = 1 then ? else Name end,
       MainFlag = case when ? = 1 then ? else MainFlag end,  
       ChildFlag = case when ? = 1 then ? else ChildFlag end, 
       VisitPlace = case when ? = 1 then ? else VisitPlace end,
       VisitMark = case when ? = 1 then ? else VisitMark end
where MedJobVisitKindCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'main'), isEmptyProperty($row, 'main'), propertyExistsSQL($row, 'childFlag'), isEmptyProperty($row, 'childFlag'), propertyExistsSQL($row, 'visitPlace'), isEmptyProperty($row, 'visitPlace'), propertyExistsSQL($row, 'visitMark'), isUndefinedProperty($row, 'visitMark'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createJobVisitKinds($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedJobVisitKind (Name, MainFlag, ChildFlag, VisitPlace, VisitMark, Position)
values (?, 0, ?, ?, ?, (select MAX(Position) + 1 from MedJobVisitKind))
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'childFlag') ?: 0, isUndefinedProperty($row, 'visitPlace'), isUndefinedProperty($row, 'visitMark') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedJobVisitKind'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteJobVisitKinds($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedJobVisitKind where MedJobVisitKindCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function setMainJobVisitKind($jobVisitKindId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedJobVisitKind set
       MainFlag = case when MedJobVisitKindCode = ? then 1 else 0 end
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([$jobVisitKindId]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updatePosition($records) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $sqlSetNull = <<<"EOT"
update MedJobVisitKind set Position = null
EOT;
$sqlSetPosition = <<<"EOT"
update MedJobVisitKind set Position = ? 
where MedJobVisitKindCode = ?
EOT;
$stmtSetNull = $db->prepare($sqlSetNull); $stmtSetNull->execute(); foreach ($records as $row) { $stmtSetPosition = $db->prepare($sqlSetPosition); $stmtSetPosition->execute([ isUndefinedProperty($row, 'position'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } $service = new Junior\Server(new med_reception_visitkinds()); $service->process();