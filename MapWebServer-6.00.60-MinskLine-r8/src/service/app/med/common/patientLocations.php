<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/common/addresses.php'); require_once(__DIR__ . '/../../map/common/addressPlaceTypes.php'); require_once(__DIR__ . '/../../map/common/addressStreetTypes.php'); resumeJsonRpc(); class med_common_patientLocations { private $db; public function readPatientLocation($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $id = isEmptyProperty($params, 'id'); $condition = $id ? "AND MedPatientLocationCode = $id" : ""; $sql = <<<"EOT"
select
    MPL.MedPatientLocationCode "id",
    MPL.MedPatientGroupCode "sectorId",
    MPL.MedPatientPolyclinicCode "clinicId",
    MPL.Special "special",
    MPL.SearchType "searchType",
    MPL.UseStartAge "useStartAge",
    MPL.StartAge "startAge",
    MPL.UseFinishAge "useFinishAge",
    MPL.FinishAge "finishAge",
    COALESCE(MPL.Sex, 2) "sex",
    COALESCE(asa_trim(MPG.Name), E'' ) as "sectorName",
    CASE MPP.MedPatientPolyclinicCode is not NULL
      WHEN true THEN COALESCE(MPP.Name, E'' )+ E' (' +COALESCE(MPR.Name, E'' )+ E')'
      WHEN false THEN  E''
    END "clinicName"
from MedPatientLocation AS MPL
join MedPatientGroup AS MPG ON MPG.MedPatientGroupCode = MPL.MedPatientGroupCode
    and MPG.EnterpriseCode = $enterpriseCode
    and fn_WebUserAccessMedSector(mpg.MedPatientGroupCode) = 1
left join UniDict AS UDG ON UDG.UniDictCode = MPG.RegionType
left join MedPatientPolyclinic AS MPP ON MPP.MedPatientPolyclinicCode = MPL.MedPatientPolyclinicCode and MPP.MedPatientRegionCode in (Select MedPatientRegionCode From MedPatientRegion Where EnterpriseCode = $enterpriseCode)
left join MedPatientRegion AS MPR ON MPR.MedPatientRegionCode = MPP.MedPatientRegionCode
where
    (MPP.MedPatientPolyclinicCode is not NULL or MPG.MedPatientGroupCode is not NULL)
    $condition
order by RIGHT(REPEAT( E' ' ,20)+asa_trim(MPG.Cipher),20) ,MPG.Name,MPL.Special,MPP.Name,MPR.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($id) { $row = $stmt->fetch(); $res = new stdClass(); $res->id = $row['id']; $res->sectorName = $row['sectorName']; $res->clinicName = $row['clinicName']; return $res; } else { return $stmt->fetchAll(); } } public function createPatientLocation($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedPatientLocation (MedPatientGroupCode, Special, MedPatientPolyclinicCode, SearchType, UseStartAge, StartAge, UseFinishAge, FinishAge, Sex)
values (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,2));
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if ($this->isPatientLocationExists($row, true)) { throw new Exception('Настройка для данного набора (участок, спец., поликлиника) уже существует!'); } $stmt->execute([ isEmptyProperty($row, 'sectorId'), isUndefinedProperty($row, 'special'), isEmptyProperty($row, 'clinicId'), isUndefinedProperty($row, 'searchType, '), isUndefinedProperty($row, 'useStartAge'), isUndefinedProperty($row, 'startAge'), isUndefinedProperty($row, 'useFinishAge'), isUndefinedProperty($row, 'finishAge'), isUndefinedProperty($row, 'sex') ]); $param = new stdClass(); $param->id = $db->lastInsertId('seq_MedPatientLocation'); $ret[] = $this->readPatientLocation($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientLocation($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPatientLocation set
    MedPatientGroupCode = case when ? = 1 then ? else MedPatientGroupCode end,
    Special = case when ? = 1 then ? else Special end,
    MedPatientPolyclinicCode = case when ? = 1 then ? else MedPatientPolyclinicCode end,
    SearchType = case when ? = 1 then ? else SearchType end,
    UseStartAge = case when ? = 1 then ? else UseStartAge end,
    StartAge = case when ? = 1 then ? else StartAge end,
    UseFinishAge = case when ? = 1 then ? else UseFinishAge end,
    FinishAge = case when ? = 1 then ? else FinishAge end,
    Sex = case when ? = 1 then NULLIF(?,2) else Sex end
where MedPatientLocationCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $ret = []; try { foreach ($records as $row) { if ($this->isPatientLocationExists($row, false)) { throw new Exception('Настройка для данного набора (участок, спец., поликлиника) уже существует!'); } $stmt->execute([ propertyExistsSQL($row, 'sectorId'), isEmptyProperty($row, 'sectorId'), propertyExistsSQL($row, 'special'), isUndefinedProperty($row, 'special'), propertyExistsSQL($row, 'clinicId'), isEmptyProperty($row, 'clinicId'), propertyExistsSQL($row, 'searchType'), isUndefinedProperty($row, 'searchType'), propertyExistsSQL($row, 'useStartAge'), isUndefinedProperty($row, 'useStartAge'), propertyExistsSQL($row, 'startAge'), isUndefinedProperty($row, 'startAge'), propertyExistsSQL($row, 'useFinishAge'), isUndefinedProperty($row, 'useFinishAge'), propertyExistsSQL($row, 'finishAge'), isUndefinedProperty($row, 'finishAge'), propertyExistsSQL($row, 'sex'), isUndefinedProperty($row, 'sex'), $row->id ]); $param = new stdClass(); $param->id = $row->id; $ret[] = $this->readPatientLocation($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatientLocation($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPatientLocation where MedPatientLocationCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function isPatientLocationExists($row, $mode) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select 1
from MedPatientLocation
where
    coalesce(MedPatientGroupCode,0) = ? and
    coalesce(Special,0) = ? and
    coalesce(MedPatientPolyclinicCode,0) = ? and
    MedPatientLocationCode <> ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($row, 'sectorId', 0), isEmptyProperty($row, 'special', 0), isEmptyProperty($row, 'clinicId', 0), $mode ? 0 : isEmptyProperty($row, 'id', 0) ]); return ($stmt->fetch()); } public function readPatientLocationAddress($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    medpatientlocationaddresscode "id",
    medpatientlocationcode "patientLocationId",
    area "area",
    areatype "areaType",
    district "district",
    region "region",
    place "place",
    placetype "placeType",
    ward "ward",
    wardtype "wardType",
    street "street",
    streettype "streetType",
    house "house",
    country "country"
from MedPatientLocationAddress
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $data = $stmt->fetchAll(); $root = new stdClass; $root->addressData = $data; $placeTypes = new map_common_addressPlaceTypes(); $placeTypeList = $placeTypes->readAddressPlaceTypes(); $root->placeTypeList = $placeTypeList; $streetTypes = new map_common_addressStreetTypes(); $streetTypeList = $streetTypes->readAddressStreetTypes(); $root->streetTypeList = $streetTypeList; $addresses = new map_common_addresses(); $params = new stdClass; $params->type = 0; $areaTypeList = $addresses->readAddressSection($params); $root->areaTypeList = $areaTypeList; $params->type = 1; $areaList = $addresses->readAddressSection($params); $root->areaList = $areaList; $params->type = 2; $districtList = $addresses->readAddressSection($params); $root->districtList = $districtList; $params->type = 3; $regionList = $addresses->readAddressSection($params); $root->regionList = $regionList; $params->type = 4; $placeList = $addresses->readAddressSection($params); $root->placeList = $placeList; $params->type = 5; $wardTypeList = $addresses->readAddressSection($params); $root->wardTypeList = $wardTypeList; $params->type = 6; $wardList = $addresses->readAddressSection($params); $root->wardList = $wardList; $params->type = 7; $streetList = $addresses->readAddressSection($params); $root->streetList = $streetList; $params->type = 8; $countriesList = $addresses->readAddressSection($params); $root->countriesList = $countriesList; return $root; } public function createPatientLocationAddress($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedPatientLocationAddress (medpatientlocationcode, area, areaType, district, region, place, placeType, ward, wardtype, street, streetType, house, country)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $data = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'patientLocationId'), isEmptyProperty($row, 'area'), isEmptyProperty($row, 'areaType'), isEmptyProperty($row, 'district'), isEmptyProperty($row, 'region'), isEmptyProperty($row, 'place'), isEmptyProperty($row, 'placeType'), isEmptyProperty($row, 'ward'), isEmptyProperty($row, 'wardType'), isEmptyProperty($row, 'street'), isEmptyProperty($row, 'streetType'), isEmptyProperty($row, 'house'), isEmptyProperty($row, 'country') ]); $data[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatientLocationAddress'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $root = new stdClass; $root->addressData = $data; return $root; } public function updatePatientLocationAddress($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPatientLocationAddress set
    area = case when ? = 1 then ? else area end,
    areaType = case when ? = 1 then ? else areaType end,
    district = case when ? = 1 then ? else district end,
    region = case when ? = 1 then ? else region end,
    place = case when ? = 1 then ? else place end,
    placeType = case when ? = 1 then ? else placeType end,
    ward = case when ? = 1 then ? else ward end,
    wardType = case when ? = 1 then ? else wardType end,
    street = case when ? = 1 then ? else street end,
    streetType = case when ? = 1 then ? else streetType end,
    house = case when ? = 1 then ? else house end,
    country = case when ? = 1 then ? else country end
where MedPatientLocationAddressCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'area'), isEmptyProperty($row, 'area'), propertyExistsSQL($row, 'areaType'), isEmptyProperty($row, 'areaType'), propertyExistsSQL($row, 'district'), isEmptyProperty($row, 'district'), propertyExistsSQL($row, 'region'), isEmptyProperty($row, 'region'), propertyExistsSQL($row, 'place'), isEmptyProperty($row, 'place'), propertyExistsSQL($row, 'placeType'), isEmptyProperty($row, 'placeType'), propertyExistsSQL($row, 'ward'), isEmptyProperty($row, 'ward'), propertyExistsSQL($row, 'wardType'), isEmptyProperty($row, 'wardType'), propertyExistsSQL($row, 'street'), isEmptyProperty($row, 'street'), propertyExistsSQL($row, 'streetType'), isEmptyProperty($row, 'streetType'), propertyExistsSQL($row, 'house'), isEmptyProperty($row, 'house'), propertyExistsSQL($row, 'country'), isEmptyProperty($row, 'country'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePatientLocationAddress($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPatientLocationAddress where MedPatientLocationAddressCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPatientLocationBusy($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $condition = $id ? "where MedPatientLocationBusyCode = $id" : ""; $sql = <<<"EOT"
select
    mb.MedPatientLocationBusyCode "id",
    mb.MedPatientLocationCode "patientLocationId",
    mb.EnterpriseKindCode "enterpriseKindId",
    mb.EnterpriseCode "enterpriseId",
    mb.DepartmentCode "departmentId",
    e.Name "enterpriseName",
    ek.Name "enterpriseKindName",
    FN_cmDepartmentName(mb.DepartmentCode) "departmentName"
from
    MedPatientLocationBusy mb
join Enterprise e on mb.EnterpriseCode = E.EnterpriseCode
join EnterpriseKind ek on mb.EnterpriseKindCode = ek.EnterpriseKindCode
$condition
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($id) { $row = $stmt->fetch(); $res = new stdClass(); $res->id = $row['id']; $res->enterpriseKindName = $row['enterpriseKindName']; $res->enterpriseName = $row['enterpriseName']; $res->departmentName = $row['departmentName']; return $res; } else { return $stmt->fetchAll(); } } public function createPatientLocationBusy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedPatientLocationBusy (medpatientlocationcode, EnterpriseKindCode, EnterpriseCode, DepartmentCode)
values (?, ?, ?, ?);
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'patientLocationId'), isEmptyProperty($row, 'enterpriseKindId'), isEmptyProperty($row, 'enterpriseId'), isEmptyProperty($row, 'departmentId'), ]); $param = new stdClass(); $param->id = $db->lastInsertId('seq_MedPatientLocationBusy'); $ret[] = $this->readPatientLocationBusy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientLocationBusy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPatientLocationBusy set
    EnterpriseKindCode = case when ? = 1 then ? else EnterpriseKindCode end,
    EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
    DepartmentCode = case when ? = 1 then ? else DepartmentCode end
where MedPatientLocationBusyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $ret = []; try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'enterpriseKindId'), isEmptyProperty($row, 'enterpriseKindId'), propertyExistsSQL($row, 'enterpriseId'), isEmptyProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), $row->id ]); $param = new stdClass(); $param->id = $row->id; $ret[] = $this->readPatientLocationBusy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatientLocationBusy($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPatientLocationBusy where MedPatientLocationBusyCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } $service = new Junior\Server(new med_common_patientLocations()); $service->process(); 