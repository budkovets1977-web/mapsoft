<?php
 require_once(__DIR__.'/../../init.php'); class med_common_districts { private $db; public function readDistricts($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
    MedPatientRegionCode "id",
    Name "name"
from MedPatientRegion
where (EnterpriseCode = $enterpriseCode)
order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDistricts($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPatientRegion set
       Name = case when ? = 1 then ? else Name end
where MedPatientRegionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDistricts($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPatientRegion where MedPatientRegionCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createDistricts($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
insert into MedPatientRegion (EnterpriseCode, Name)
values ($enterpriseCode,  ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatientRegion'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } $service = new Junior\Server(new med_common_districts()); $service->process();