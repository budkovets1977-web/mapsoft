<?php
require_once(__DIR__.'/../../init.php'); class med_dayhospital_drugMethod { private $db; const CODE_UNIQUE_ERROR_CODE = -101; const CODE_USED = -102; public function readDrugMethod($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT 
    MPM.MedPurposeMethodCode "id",
    MPM.name "name",
    MPM.cipher "code",
    CASE coalesce(MPM.inactive, 0)
        WHEN 1 THEN null 
        WHEN 0 THEN 1
    END "active"
FROM MedPurposeMethod MPM
EOT;
return selectSql($db, $sql); } public function updateDrugMethod($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedPurposeMethod SET
   cipher = case when :codeExists = 1 then :code else cipher end,
   name = case when :nameExists = 1 then :name else name end,
   inactive = case when :inactiveExists = 1 then :inactive else inactive end
where MedPurposeMethodCode = :id
EOT;
if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $codeExists = propertyExistsSQL($row, 'code'); $code = isUndefinedProperty($row, 'code'); if ($codeExists) { $this->checkCodeUnique($db, $code); } selectSql($db, $sql, [ ':codeExists' => $codeExists, ':code' => $code, ':nameExists' => propertyExistsSQL($row, 'name'), ':name' => isUndefinedProperty($row, 'name'), ':inactiveExists' => propertyExistsSQL($row, 'active'), ':inactive' => (int)(!isUndefinedProperty($row, 'active')), ':id' => isUndefinedProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createDrugMethod($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedPurposeMethod (cipher, name, inactive) 
VALUES(:code, :name, :inactive)
RETURNING MedPurposeMethodCode
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $code = isUndefinedProperty($row, 'code'); $this->checkCodeUnique($db, $code); $rec->id = selectSqlValue($db, $sql, [ ':code' => $code, ':name' => isUndefinedProperty($row, 'name'), ':inactive' => (int)(!isUndefinedProperty($row, 'active')) ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } protected function checkCodeUnique ($db, $code) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT 
        MedPurposeMethodCode
    FROM MedPurposeMethod
    WHERE cipher = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':code' => $code ]); if ($result) { throw new Exception("Запись с кодом $code уже существует", self::CODE_UNIQUE_ERROR_CODE); } } protected function checkUsedInDayHospitalPurpose ($db, $id) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT 
        MedDayHopsitalPurposeCode
    FROM MedDayHopsitalPurpose
    WHERE MedPurposeMethodCode = :id
)
EOT;
$result = selectSqlValue($db, $sql, [ ':id' => $id ]); if ($result) { throw new Exception("Нельзя удалить запись, так как данные используются в журнале дневного стационара", self::CODE_UNIQUE_ERROR_CODE); } } public function deleteDrugMethod($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
DELETE FROM MedPurposeMethod 
WHERE MedPurposeMethodCode = :id
EOT;
$db->beginTransaction(); try { foreach ($records as $row) { $id = isUndefinedProperty($row, 'id'); $this->checkUsedInDayHospitalPurpose($db, $id); selectSqlValue($db, $sql, [ ':id' => $id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_dayhospital_drugMethod');