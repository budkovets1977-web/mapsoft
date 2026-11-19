<?php
 require_once(__DIR__.'/../../init.php'); class map_common_uniDicts { private $db; public function readUniDicts($params = null) { $db = $this->db ?: $this->db = connectDb(); $dictNumber = isEmptyProperty($params, 'dictNumber', 0); $sqlParams = [ ':dictNumber' => $dictNumber ]; $parentId = isEmptyProperty($params, 'unidictCodeParent'); $parentIdSql = ''; if ($parentId) { $parentIdSql = ' AND U.UniDictCodeParent = :parentId'; $sqlParams[':parentId'] = $parentId; } $preloadLogId = isEmptyProperty($params, 'preloadLogId'); $preloadLogIdSql = ''; if ($preloadLogId) { $preloadLogIdSql = <<<"EOT"
OR (U.UniDictCode = (SELECT UniDictCodeReason FROM AmbLog WHERE AmbLogCode = :preloadLogId))
EOT;
$sqlParams[':preloadLogId'] = $preloadLogId; } $isActive = isEmptyProperty($params, 'isActive'); $isActiveSql = ''; if ($isActive) { $isActiveSql = ' AND U.inactive = 0'; } $sql = <<<"EOT"
select
    U.UniDictCode "id",
    U.DictNumber "dictNumber",
    U.Cipher "cipher",
    U.Value "value",
    U.Value1 "value1",
    U.Value2 "value2",
    U.Value3 "value3",
    U.ValueInteger "valueInteger",
    U.ValueInteger2 "valueInteger2",
    U.ValueString1 "valueString1",
    U.ValueString2 "valueString2",
    U.Code "code",
    U.Position "position",
    U.ValueText "valueText",
    CASE U.inactive 
        WHEN 1 THEN 0 
        WHEN 0 THEN 1
    END "active"
from UniDict U
WHERE (
    U.DictNumber = :dictNumber
    $parentIdSql
    $isActiveSql
)
$preloadLogIdSql
order by U.position, U.value
EOT;
return selectSql($db, $sql, $sqlParams); } public function updateUniDicts($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update UniDict set
       Cipher = case when ? = 1 then ? else Cipher end,
       Value = case when ? = 1 then ? else Value end,
       Value1 = case when ? = 1 then ? else Value1 end,
       Value2 = case when ? = 1 then ? else Value2 end,
       Value3 = case when ? = 1 then ? else Value3 end,
       ValueInteger = case when ? = 1 then ? else ValueInteger end,
       ValueInteger2 = case when ? = 1 then ? else ValueInteger2 end,
       ValueString1 = case when ? = 1 then ? else ValueString1 end,
       ValueString2 = case when ? = 1 then ? else ValueString2 end,
       Code = case when ? = 1 then ? else Code end,
       Position = case when ? = 1 then ? else Position end,
       inactive = case when ? = 1 then ? else inactive end,
       ValueText = CASE WHEN ? = 1 THEN ? ELSE ValueText END
where UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'value'), cleanNonprintableChars(isUndefinedProperty($row, 'value', '')), propertyExistsSQL($row, 'value1'), isUndefinedProperty($row, 'value1'), propertyExistsSQL($row, 'value2'), isUndefinedProperty($row, 'value2'), propertyExistsSQL($row, 'value3'), isUndefinedProperty($row, 'value3'), propertyExistsSQL($row, 'valueInteger'), isUndefinedProperty($row, 'valueInteger'), propertyExistsSQL($row, 'valueInteger2'), isUndefinedProperty($row, 'valueInteger2'), propertyExistsSQL($row, 'valueString1'), cleanNonprintableChars(isUndefinedProperty($row, 'valueString1', '')), propertyExistsSQL($row, 'valueString2'), cleanNonprintableChars(isUndefinedProperty($row, 'valueString2', '')), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'active'), falseTo0(!isUndefinedProperty($row, 'active')), propertyExistsSQL($row, 'valueText'), isUndefinedProperty($row, 'valueText'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteUniDicts($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "UniDict", "UniDictCode", $records); } public function createUniDicts($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into UniDict (DictNumber, Cipher, Value, Value1, Value2, Value3, ValueInteger, ValueInteger2, ValueString1, ValueString2, Code, Position, inactive, ValueText)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'dictNumber'), isUndefinedProperty($row, 'cipher'), cleanNonprintableChars((isUndefinedProperty($row, 'value') ?? '')), isUndefinedProperty($row, 'value1'), isUndefinedProperty($row, 'value2'), isUndefinedProperty($row, 'value3'), isUndefinedProperty($row, 'valueInteger'), isUndefinedProperty($row, 'valueInteger2'), cleanNonprintableChars((isUndefinedProperty($row, 'valueString1') ?? '')), cleanNonprintableChars((isUndefinedProperty($row, 'valueString2') ?? '')), isUndefinedProperty($row, 'code'), isUndefinedProperty($row, 'position'), falseTo0(!isUndefinedProperty($row, 'active')), isUndefinedProperty($row, 'valueText') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_unidict'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_uniDicts');