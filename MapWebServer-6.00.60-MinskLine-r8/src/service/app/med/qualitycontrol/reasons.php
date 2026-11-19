<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_reasons { private $db; const DICT_NUMBER = 83; const CODE_UNIQUE_ERROR_CODE = -101; public function readReasons($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = [ ':dictNumber' => self::DICT_NUMBER ]; $parentId = isEmptyProperty($params, 'unidictCodeParent'); $parentIdSql = ''; if ($parentId) { $parentIdSql = ' AND U.UniDictCodeParent = :parentId'; $sqlParams[':parentId'] = $parentId; } $search = isEmptyProperty($params, 'search'); $searchSql = ''; if ($search) { $searchSql = ' AND (U.Code LIKE :search OR U.Value LIKE :search)'; $sqlParams[':search'] = "%$search%"; } $isActive = isEmptyProperty($params, 'isActive'); $isActiveSql = ''; if ($isActive) { $isActiveSql = ' AND U.inactive = 0'; } $sql = <<<"EOT"
select
    U.UniDictCode "id",
    U.DictNumber "dictNumber",
    U.Cipher "cipher",
    U.Value "value",
    U.Value1 "value1",
    U.Value2 "value2",
    U.Value3 "value3",
    U.ValueInteger "valueInteger",
    U.ValueString1 "valueString1",
    U.ValueString2 "valueString2",
    U.Code "code",
    U.Position "position",
    CASE U.inactive 
        WHEN 1 THEN 0 
        WHEN 0 THEN 1
    END "active"
from UniDict U
WHERE (
    U.DictNumber = :dictNumber
    $parentIdSql
    $isActiveSql
    $searchSql
)
ORDER BY U.Code DESC
EOT;
return selectSql($db, $sql, $sqlParams); } public function updateReasons($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update UniDict set
       Cipher = case when ? = 1 then ? else Cipher end,
       Value = case when ? = 1 then ? else Value end,
       Value1 = case when ? = 1 then ? else Value1 end,
       Value2 = case when ? = 1 then ? else Value2 end,
       Value3 = case when ? = 1 then ? else Value3 end,
       ValueInteger = case when ? = 1 then ? else ValueInteger end,
       ValueString1 = case when ? = 1 then ? else ValueString1 end,
       ValueString2 = case when ? = 1 then ? else ValueString2 end,
       Code = case when ? = 1 then ? else Code end,
       Position = case when ? = 1 then ? else Position end,
       inactive = case when ? = 1 then ? else inactive end
where UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $codeExists = propertyExistsSQL($row, 'code'); $code = isUndefinedProperty($row, 'code'); if ($codeExists) { $this->checkCodeUnique($db, $code); } $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'value'), isUndefinedProperty($row, 'value'), propertyExistsSQL($row, 'value1'), isUndefinedProperty($row, 'value1'), propertyExistsSQL($row, 'value2'), isUndefinedProperty($row, 'value2'), propertyExistsSQL($row, 'value3'), isUndefinedProperty($row, 'value3'), propertyExistsSQL($row, 'valueInteger'), isUndefinedProperty($row, 'valueInteger'), propertyExistsSQL($row, 'valueString1'), isUndefinedProperty($row, 'valueString1'), propertyExistsSQL($row, 'valueString2'), isUndefinedProperty($row, 'valueString2'), $codeExists, $code, propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'active'), falseTo0(!isUndefinedProperty($row, 'active')), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } protected function checkCodeUnique ($db, $code) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT 
        UniDictCode
    FROM UniDict
    WHERE dictNumber = :dictNumber AND code = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':dictNumber' => self::DICT_NUMBER, ':code' => $code ]); if ($result) { throw new Exception("Запись с кодом $code уже существует", self::CODE_UNIQUE_ERROR_CODE); } } public function deleteReasons($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "UniDict", "UniDictCode", $records); } public function createReasons($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into UniDict (DictNumber, Cipher, Value, Value1, Value2, Value3, ValueInteger, ValueString1, ValueString2, Code, Position, inactive)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
returning UniDictCode
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $code = isUndefinedProperty($row, 'code'); $this->checkCodeUnique($db, $code); $ret[] = $rec = new stdClass; $rec->id = selectSqlValue($db, $sql, [ self::DICT_NUMBER, isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'value'), isUndefinedProperty($row, 'value1'), isUndefinedProperty($row, 'value2'), isUndefinedProperty($row, 'value3'), isUndefinedProperty($row, 'valueInteger'), isUndefinedProperty($row, 'valueString1'), isUndefinedProperty($row, 'valueString2'), $code, isUndefinedProperty($row, 'position'), falseTo0(!isUndefinedProperty($row, 'active')) ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('med_qualitycontrol_reasons'); 