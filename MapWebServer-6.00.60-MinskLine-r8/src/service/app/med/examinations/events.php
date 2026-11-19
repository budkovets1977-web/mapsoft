<?php
 require_once(__DIR__.'/../../init.php'); class med_examinations_events { private $db; public function readEventGroups() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    SELECT
        medActionGroupCode "id",
        name "groupName"
    FROM medActionGroup
    ORDER by 2 ASC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createEventGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT INTO medActionGroup (name)
    VALUES (?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'groupName') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedActionGroup'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateEventGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    UPDATE medActionGroup
    SET
    name = case when ? = 1 then ? else name end
    WHERE medActionGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'groupName'), isUndefinedProperty($row, 'groupName'), isUndefinedProperty($row, 'id', 0) ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteEventGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medActionGroup', 'medActionGroupCode', $records); } public function readEvents() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    SELECT
        medActionCode "id",
        medActionGroupCode "groupId",
        name "eventName",
        note "note"
    FROM medAction
    ORDER by 3 ASC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createEvents($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT INTO medAction (medActionGroupCode,name,note)
    VALUES (?,?,?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'groupId'), isUndefinedProperty($row, 'eventName'), isUndefinedProperty($row, 'note') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedAction'); $ret[] = $rec; } $results = isset($row->resultsIUD) ? $row->resultsIUD : null; if ($results) { $this->syncResults($results, $rec->id); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateEvents($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    UPDATE medAction
    SET
    name = case when ? = 1 then ? else name end,
    note = case when ? = 1 then ? else note end
    WHERE medActionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'eventName'), isUndefinedProperty($row, 'eventName'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'id', 0) ]); } $results = isset($row->resultsIUD) ? $row->resultsIUD : null; if ($results) { $this->syncResults($results, $row->id); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteEvents($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medAction', 'medActionCode', $records); } public function readEventResults($params) { $db = $this->db ?: $this->db = connectDb(); $eventId = isEmptyProperty($params, 'eventId'); $sql = <<<"EOT"
    SELECT
        medActionResultCode "id",
        medActionCode "eventId",
        name "name"
    FROM medActionResult
    WHERE medActionCode = $eventId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } private function syncResults($records, $id) { $db = $this->db ?: $this->db = connectDb(); $sqlInsert = <<<"EOT"
INSERT INTO medActionResult (medActionCode,name)
    VALUES (?,?)
EOT;
$sqlUpdate = <<<"EOT"
 UPDATE medActionResult
    SET
    medActionCode = case when ? = 1 then ? else medActionCode end,
    name = case when ? = 1 then ? else name end
    WHERE medActionResultCode = ?
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtUpdate = $db->prepare($sqlUpdate); try { $deleting = []; foreach ($records as $row) { switch ($row->iud) { case 1: $stmtInsert->execute([ $id, isUndefinedProperty($row, 'name') ]); break; case 2: $stmtUpdate->execute([ propertyExistsSQL($row, 'eventId'), isUndefinedProperty($row, 'eventId'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'id', 0) ]); break; case 3: $deleting[] = (object) ['id' => $row->id]; break; } } if (!empty($deleting)) { $in = createSqlInCondition($deleting); $sqlDelete = <<<"EOT"
DELETE FROM medActionResult
WHERE medActionResultCode $in
EOT;
$stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute(); } } catch (Throwable $e) { throw $e; } return true; } } $service = new Junior\Server(new med_examinations_events()); $service->process(); 