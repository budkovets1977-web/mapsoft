<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_inspectDLCategories { private $db; public function readinspectDLCategories($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isEmptyProperty($params, 'inspectionsId', 0); $inspectionsPlanId = isEmptyProperty($params, 'inspectionsPlanId', 0); $sql = <<<"EOT"
SELECT
    MIDLC.medinspectdlcategorycode    "id",
    MIDLC.medinspectcode              "inspectId",
    MIDLC.medinspectplancode          "inspectPlanId",
    MIDLC.flags                       "flags",
    MIDLC.note                        "note",
    U.value                           "name",
    U.unidictcode                     "nameId"
FROM medinspectdlcategory  MIDLC
    JOIN unidict  U ON MIDLC.unidictcodedlcategory = U.unidictcode AND U.dictNumber = 64
WHERE MIDLC.medinspectcode = $inspectionsId OR MIDLC.medinspectplancode = $inspectionsPlanId
ORDER BY U.value ASC;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createinspectDLCategories($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT INTO MedInspectDLCategory (medInspectCode, medInspectPlanCode, flags, note, unidictCodeDLCategory)
    VALUES (?, ?, ?, ?, ?)
    EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $arr = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'inspectionsPlanId'), isEmptyProperty($row, 'flags'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'nameId') ]); $arr[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medinspectdlcategory'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $arr; } public function updateinspectDLCategories($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedInspectDLCategory SET
    medInspectCode = case when ? = 1 then ? else medInspectCode end,
    medInspectPlanCode = case when ? = 1 then ? else medInspectPlanCode end,
    flags = case when ? = 1 then ? else flags end,
    note = case when ? = 1 then ? else note end,
    unidictCodeDLCategory = case when ? = 1 then ? else unidictCodeDLCategory end
WHERE medinspectdlcategorycode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'inspectionsId'), isEmptyProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'inspectionsPlanId'), isEmptyProperty($row, 'inspectionsPlanId'), propertyExistsSQL($row, 'flags'), isEmptyProperty($row, 'flags'), propertyExistsSQL($row, 'note'), isEmptyProperty($row, 'note'), propertyExistsSQL($row, 'nameId'), isEmptyProperty($row, 'nameId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteinspectDLCategories($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInspectDLCategory', 'medinspectdlcategorycode', $records); } } handleJsonRpc('med_examinations_inspectDLCategories');