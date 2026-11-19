<?php
 require_once(__DIR__.'/../../init.php'); class map_common_countries { private $db; public function readCountries($params = null) { $db = $this->db ?: $this->db = connectDb(); $excludeCountryId = isUndefinedProperty($params, 'excludeCountryId'); $where = ''; if ($excludeCountryId) { $where .= "WHERE CountryCode <> $excludeCountryId"; } $sql = <<<"EOT"
select
    CountryCode "id",
    Name "name",
    Cipher "cipher",
    WageCode "wageCode"
from Country
$where
order by 2, 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCountries($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update Country set
       Name = case when ? = 1 then ? else Name end,
       Cipher = case when ? = 1 then ? else Cipher end,
       WageCode = case when ? = 1 then ? else WageCode end
where CountryCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'wageCode'), isUndefinedProperty($row, 'wageCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteCountries($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from Country where CountryCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCountries($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into Country (Name, Cipher, WageCode)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'wageCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_Country'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } $service = new Junior\Server(new map_common_countries()); $service->process();