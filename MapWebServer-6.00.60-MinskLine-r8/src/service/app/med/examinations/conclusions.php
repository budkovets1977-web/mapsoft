<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_conclusions { private $db; public function readConclusions($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    medconclusioncode "id",
    name "name"
FROM medconclusion
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readConclusionGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $conclusionId = isEmptyProperty($params, 'conclusionId', 0); $tree = isEmptyProperty($params, 'tree', false); if ($tree) { $sql = <<< "EOT"
select
 G.MedConclusionGroupCode "id",
 cast(C.Name || ' - ' || G.Name as varchar(110))  "name"
from
  MedConclusion C
  join MedConclusionGroup G on G.MedConclusionCode = C.MedConclusionCode
order by
 C.Name, G.Position
EOT;
} else { $sql = <<< "EOT"
SELECT
    cg.medconclusiongroupcode "id",
    cg.position "position",
    cg.name "name",
    cg.medconclusioncode "conclusionId",
    c.name "conclusionName"
FROM medconclusion c
JOIN medconclusiongroup cg ON cg.medconclusionCode = c.medconclusionCode
WHERE (($conclusionId = 0) OR (cg.medconclusioncode = $conclusionId))
ORDER BY c.name, cg.position
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateConclusions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
UPDATE medconclusion SET
    name = case when ? = 1 then ? else name end
WHERE
    medconclusioncode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateConclusionGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
UPDATE medconclusiongroup set
    name = case when ? = 1 then ? else name end,
    position = case when ? = 1 then ? else position end,
    medconclusioncode = case when ? = 1 then ? else medconclusioncode end
WHERE medconclusiongroupcode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, propertyExistsSQL($row, 'position'), null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'conclusionId'), isUndefinedProperty($row, 'conclusionId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createConclusions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO medconclusion (name)
values (?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medconclusion'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createConclusionGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO medconclusiongroup (name, position, medconclusioncode)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'name'), isEmptyProperty($row, 'position'), isEmptyProperty($row, 'conclusionId'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medconclusiongroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteConclusions($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medconclusion', 'medconclusioncode', $records); } public function deleteConclusionGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medconclusiongroup', 'medconclusiongroupcode', $records); } } $service = new Junior\Server(new med_examinations_conclusions()); $service->process(); 