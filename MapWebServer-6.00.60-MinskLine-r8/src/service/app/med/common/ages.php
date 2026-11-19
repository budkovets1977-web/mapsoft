<?php
 require_once(__DIR__.'/../../init.php'); class med_common_ages { private $db; public function readAgesSet($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    ageSetCode "id",
    name "name"
FROM
    AgeSet
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readAgeGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    ageGroupCode "id",
    name "name",
    position "position",
    ageSetCode "ageSetId"
FROM
    AgeGroup
ORDER BY
    position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readAgeYears($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    ageYearCode "id",
    ageGroupCode "ageGroupId",
    position "position",
    yearFrom "yearFrom",
    yearTo "yearTo"
FROM
    AgeYear
ORDER BY
    position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateAgesSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update AgeSet set
       name = case when ? = 1 then ? else name end
where AgeSetCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateAgeGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update AgeGroup set
       name = case when ? = 1 then ? else name end,
       position = case when ? = 1 then ? else position end,
       ageSetCode = case when ? = 1 then ? else ageSetCode end
where AgeGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, propertyExistsSQL($row, 'position'), null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'ageSetId'), isUndefinedProperty($row, 'ageSetId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateAgeYears($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update AgeYear set
       ageGroupCode = case when ? = 1 then ? else ageGroupCode end,
       position = case when ? = 1 then ? else position end,
       yearFrom = case when ? = 1 then ? else yearFrom end,
       yearTo = case when ? = 1 then ? else yearTo end
where AgeYearCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'ageGroupId'), isUndefinedProperty($row, 'ageGroupId'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'yearFrom'), isUndefinedProperty($row, 'yearFrom'), propertyExistsSQL($row, 'yearTo'), isUndefinedProperty($row, 'yearTo'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createAgesSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AgeSet (name)
values (?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_AgeSet'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createAgeGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AgeGroup (name, position, ageSetCode)
values (?, coalesce((SELECT
    max(position) "res"
FROM
    AgeGroup
WHERE
        coalesce(ageSetCode, 0) = ?), 0) + 1, ?)
RETURNING position AS position, ageGroupCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'ageSetId'), isUndefinedProperty($row, 'ageSetId'), ]); $ret[] = $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; $rec->position = $log['position']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createAgeYears($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AgeYear (ageGroupCode, position, yearFrom, yearTo)
values (?, coalesce((SELECT
    max(position) "res"
FROM
    AgeYear
WHERE
        coalesce(ageGroupCode, 0) = ?), 0) + 1, ?, ?)
RETURNING position AS position, ageYearCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'ageGroupId'), isUndefinedProperty($row, 'ageGroupId'), isUndefinedProperty($row, 'yearFrom'), isUndefinedProperty($row, 'yearTo'), ]); $ret[] = $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; $rec->position = $log['position']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteAgesSet($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AgeSet', 'AgeSetCode', $records); } public function deleteAgeGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AgeGroup', 'AgeGroupCode', $records); } public function deleteAgeYears($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AgeYear', 'AgeYearCode', $records); } } $service = new Junior\Server(new med_common_ages()); $service->process(); 