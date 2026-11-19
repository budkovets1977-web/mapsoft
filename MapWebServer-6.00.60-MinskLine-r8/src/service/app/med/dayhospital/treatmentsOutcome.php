<?php
 require_once(__DIR__.'/../../init.php'); class med_dayhospital_treatmentsOutcome { private $db; public function readTreatmentsOutcome($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    UniDictCode "id",
    Cipher "cipher",
    Value "name",
    CASE WHEN InActive = 1 THEN 0 WHEN InActive = 0 THEN 1 END "active"
FROM UniDict
    WHERE DictNumber = 24
    ORDER BY Cipher
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateTreatmentsOutcome($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 UPDATE UniDict  SET
    Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END,
    Value = CASE WHEN ? = 1 THEN ? ELSE Value END,
    InActive = CASE WHEN ? = 1 THEN ? ELSE InActive END
WHERE UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'active'), !isUndefinedProperty($row, 'active'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createTreatmentsOutcome($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO UniDict ( Cipher, Value, InActive, DictNumber)
VALUES (?, ?, ?, 24)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), !isUndefinedProperty($row, 'active') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_UniDict'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteTreatmentsOutcome($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'UniDict', 'UniDictCode', $records); } } handleJsonRpc('med_dayhospital_treatmentsOutcome');