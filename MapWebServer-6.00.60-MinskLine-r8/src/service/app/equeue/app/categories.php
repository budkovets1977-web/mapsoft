<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_categories { private $db; private function getAge($row, $field){ if (isUndefinedProperty($row, $field.'Year') == null && isUndefinedProperty($row, $field.'Month') == null) { return null; } $y = isUndefinedProperty($row, $field.'Year', 0); $m = isUndefinedProperty($row, $field.'Month', 0); return $y + $m / 100; } public function readCategories($categoryId = null){ $db = $this->db ?: $this->db = connectDb(); $c = $categoryId ? " AND c.MedEqPatientCategoryCode = $categoryId" : ""; $sql = <<<EOT
select
    c.MedEqPatientCategoryCode "id",
    c.Name "name",
    truncnum(c.AgeMin, 0) "ageMinYear",
    truncnum((c.AgeMin - truncnum(c.AgeMin, 0)) * 100, 0) "ageMinMonth",
    truncnum(c.ageMax, 0) "ageMaxYear",
    truncnum((c.ageMax - truncnum(c.ageMax, 0)) * 100, 0) "ageMaxMonth",
    c.Gender "gender",
    c.Note "note",
    c.Active "active",
    c.Type "type",
    fn_cmAgeRange((c.AgeMin)::numeric, (c.AgeMax)::numeric, 1) as "ageText",
    (select string_agg((asa_trim(d.cipher1 || E' ' || d.Name))::text, ',')
      from MedEqPatientCategoryIn ci
      join MedDiagnosis d on d.MedDiagnosisCode = ci.MedDiagnosisCode
      where ci.MedEqPatientCategoryCode = c.MedEqPatientCategoryCode) "diagnosisText",
    (select string_agg((p.Name)::text, ',')
      from MedEqPatientCategoryIn ci
      join MedPrivelege p on p.MedPrivelegeCode = ci.MedPrivelegeCode
      where ci.MedEqPatientCategoryCode = c.MedEqPatientCategoryCode) "privilegeText"
from MedEqPatientCategory c
where (1 = 1) $c
order by 2, 8, 3, 4, 5
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCategories($records, $noTransaction = false){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
update MedEqPatientCategory set
    Name = case when ? = 1 then ? else Name end,
    AgeMin = case when ? = 1 then ? else AgeMin end,
    AgeMax = case when ? = 1 then ? else AgeMax end,
    Gender = case when ? = 1 then ? else Gender end,
    Note = case when ? = 1 then ? else Note end,
    Active = case when ? = 1 then ? else Active end,
    Type = case when ? = 1 then ? else Type end
where MedEqPatientCategoryCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } if (!$noTransaction) { $db->beginTransaction(); } try { foreach($records as $row) { $min = $this->getAge($row, 'ageMin'); $max = $this->getAge($row, 'ageMax'); $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'ageMinYear') || propertyExistsSQL($row, 'ageMinMonth'), $min, propertyExistsSQL($row, 'ageMaxYear') || propertyExistsSQL($row, 'ageMaxMonth'), $max, propertyExistsSQL($row, 'gender'), isUndefinedProperty($row, 'gender'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'type'), isUndefinedProperty($row, 'type'), $row->id ]); } if (!$noTransaction) { $db->commit(); } } catch (Throwable $e) { if (!$noTransaction) { $db->rollback(); } throw $e; } return true; } public function deleteCategories($records){ $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = "DELETE FROM MedEqPatientCategory WHERE MedEqPatientCategoryCode $in"; $stmt = $db->prepare($sql); $stmt->execute(); return true; } public function syncCategories($category, $privileges, $diagnoses){ $db = $this->db ?: $this->db = connectDb(); $categoryId = (int) $category->id; $db->beginTransaction(); try { if ($categoryId == 0) { $sqlIns = <<<EOT
INSERT INTO MedEqPatientCategory (
    Name, AgeMin, AgeMax, Gender, Note, Active, Type)
VALUES (?, ?, ?, ?, ?, ?, 1)
EOT;
$stmtIns = $db->prepare($sqlIns); $stmtIns->execute([ $category->name, $this->getAge($category, 'ageMin'), $this->getAge($category, 'ageMax'), isEmptyProperty($category, 'gender'), isEmptyProperty($category, 'note'), isEmptyProperty($category, 'active') ]); $categoryId = $db->lastInsertId('seq_MedEqPatientCategory'); } else { $this->updateCategories($category, true); } error_log(print_r($categoryId . ' <--- ', true)); if (isset($privileges)) { $in = createSqlInCondition($privileges, 'privilegeId'); $sqlDel = <<<EOT
DELETE FROM MedEqPatientCategoryIn
WHERE MedEqPatientCategoryCode = $categoryId
    AND MedPrivelegeCode IS NOT NULL
    AND NOT (MedPrivelegeCode $in)
EOT;
$stmtDel = $db->prepare($sqlDel); $stmtDel->execute(); $sqlIns = <<<EOT
INSERT INTO MedEqPatientCategoryIn (
    MedEqPatientCategoryCode, MedPrivelegeCode)
SELECT $categoryId, p.MedPrivelegeCode
FROM MedPrivelege p
WHERE p.MedPrivelegeCode $in
    AND NOT EXISTS(
        SELECT * FROM MedEqPatientCategoryIn
        WHERE MedEqPatientCategoryCode = $categoryId
            AND MedPrivelegeCode = p.MedPrivelegeCode
            LIMIT 1
        )
EOT;
$stmtIns = $db->prepare($sqlIns); $stmtIns->execute(); } if (isset($diagnoses)) { $in = createSqlInCondition($diagnoses, 'diagnosisId'); $sqlDel = <<<EOT
DELETE FROM MedEqPatientCategoryIn
WHERE MedEqPatientCategoryCode = $categoryId
    AND MedDiagnosisCode IS NOT NULL
    AND NOT (MedDiagnosisCode $in)
EOT;
$stmtDel = $db->prepare($sqlDel); $stmtDel->execute(); $sqlIns = <<<EOT
INSERT INTO MedEqPatientCategoryIn (
    MedEqPatientCategoryCode, MedDiagnosisCode)
SELECT $categoryId, d.MedDiagnosisCode
FROM MedDiagnosis d
WHERE d.MedDiagnosisCode $in
    AND NOT EXISTS(
        SELECT * FROM MedEqPatientCategoryIn
        WHERE MedEqPatientCategoryCode = $categoryId
            AND MedDiagnosisCode = d.MedDiagnosisCode
            LIMIT 1
        )
EOT;
$stmtIns = $db->prepare($sqlIns); $stmtIns->execute(); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $ret = null; $read = $this->readCategories($categoryId); if ($read) { $read = $read[0]; $ret = new stdClass; $ret->id = $categoryId; $ret->ageText = $read['ageText']; $ret->privilegeText = $read['privilegeText']; $ret->diagnosisText = $read['diagnosisText']; } return $ret; } } handleJsonRpc('equeue_app_categories');