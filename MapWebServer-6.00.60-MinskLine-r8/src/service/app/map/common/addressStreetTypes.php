<?php
 require_once(__DIR__.'/../../init.php'); class map_common_addressStreetTypes { private $db; public function readAddressStreetTypes($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    AddressStreetTypeCode "id",
    Name "name",
    ShortName "shortName",
    (Attributes & 1)::boolean  "isCisz"
from AddressStreetType
order by 2, 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateAddressStreetTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update AddressStreetType set
       Name = case when ? = 1 then ? else Name end,
       ShortName = case when ? = 1 then ? else ShortName end
where AddressStreetTypeCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'shortName'), isUndefinedProperty($row, 'shortName'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteAddressStreetTypes($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from AddressStreetType where AddressStreetTypeCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createAddressStreetTypes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AddressStreetType (Name, ShortName)
values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'shortName') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_AddressStreetType'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_addressStreetTypes');