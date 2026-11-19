<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_sectors { private $db; public function readSectors($params = null) { $db = $this->db ?: $this->db = connectDb(); $isFilter = isset($params) ? (int) isUndefinedProperty($params, 'isFilter', 0) : 0; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<SQL
select
    pg.MedPatientGroupCode "id",
    pg.Cipher "cipher",
    pg.Name "name",
    pg.EnterpriseCode "enterpriseId",
    pg.RegionType "regionType",
    ud.Value "regionName",
    pg.VisitZone "visitZone",
    pg.MedDoctorCode "doctorId",
    FN_cmFIO(md.ManCode) "doctorName",
    coalesce(pg.ShowWithPatient,0) "showWithPatient",
    EXISTS(SELECT 1 FROM medpatientlocation WHERE MedPatientGroupCode = pg.MedPatientGroupCode LIMIT 1) "fromPatientLocation",
    MPL.Special "special"
from MedPatientGroup pg
left join UniDict ud on ud.UniDictCode = pg.RegionType
left join MedDoctor md on md.MedDoctorCode = pg.MedDoctorCode
LEFT JOIN MedPatientLocation MPL ON MPL.MedPatientGroupCode = PG.MedPatientGroupCode
where pg.EnterpriseCode = $enterpriseCode
    and ($isFilter = 0 or ($isFilter = 1 and fn_WebUserAccessMedSector(pg.MedPatientGroupCode) = 1))
order by 3
SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateSectors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedPatientGroup set
    Cipher = case when ? = 1 then ? else Cipher end,
    Name = case when ? = 1 then ? else Name end,
    EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
    RegionType = case when ? = 1 then ? else RegionType end,
    VisitZone = case when ? = 1 then ? else VisitZone end,
    MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
    ShowWithPatient = case when ? = 1 then nullif(?,0) else ShowWithPatient end
where MedPatientGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'enterpriseId'), isEmptyProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'regionType'), isUndefinedProperty($row, 'regionType'), propertyExistsSQL($row, 'visitZone'), isUndefinedProperty($row, 'visitZone'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'showWithPatient'), isUndefinedProperty($row, 'showWithPatient'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteSectors($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM MedPatientGroup WHERE MedPatientGroupCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createSectors($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
insert into MedPatientGroup (Cipher, Name, EnterpriseCode, RegionType, VisitZone, MedDoctorCode, ShowWithPatient)
values (?, ?, ?, ?, ?, ?, nullif(?,0))
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), $enterpriseCode, isUndefinedProperty($row, 'regionType'), isUndefinedProperty($row, 'visitZone'), isEmptyProperty($row, 'doctorId'), isUndefinedProperty($row, 'showWithPatient') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatientGroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } $service = new Junior\Server(new med_common_sectors()); $service->process(); 