<?php
 require_once(__DIR__.'/../../init.php'); class map_tables_enterprisesInfo { private $db; public function readEnterprisesInfo($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode') ? : 0; $userEnterpriseCodes = getSessionValue('mapUserEnterpriseCodes') ? : 'null'; $hex = str_pad(dechex($enterpriseCode), 8, '0', STR_PAD_LEFT); $oneEnterpriseId = isEmptyProperty($params, 'enterpriseId', 0); $sql = <<<"EOT"
select
       E.EnterpriseCode "id",
       E.Name "name",
       E.FullName "fullName",
       E.PhoneNumber "phone",
       FN_cmAddressName(EA.AddressLocationCode) "address",
       Md.SurName || {$db->postgre("E")}' ' || Md.Name || {$db->postgre("E")}' ' || Md.SecondName "director",
       Ma.SurName || {$db->postgre("E")}' ' || Ma.Name || {$db->postgre("E")}' ' || Ma.SecondName "accountant"
from Enterprise E
     left join EnterpriseBasic EB on EB.EnterpriseCode = E.EnterpriseCode
     left join EnterpriseAddress EA on EA.EnterpriseAddressCode = EB.EnterpriseAddressCode
     left join WorkerInfo WId on WId.WorkerCode = EB.WorkerDirectorCode
     left join Man Md on Md.ManCode = wid.ManCode
     left join WorkerInfo WIa on WIa.WorkerCode = EB.WorkerAccountantCode
     left join Man Ma on Ma.ManCode = WIa.ManCode
where (E.EnterpriseCode = $enterpriseCode or (
        fn_TreePath('Enterprise', 'EnterpriseParentCode', EnterpriseParentCode) like '%{$hex}%'
        and ('{$userEnterpriseCodes}' = 'null' or ('{$userEnterpriseCodes}' <> 'null' and e.EnterpriseCode in ({$userEnterpriseCodes})))
    )
) and ($oneEnterpriseId = 0 or E.EnterpriseCode = $oneEnterpriseId) /* MAP-2106 */
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readEnterprisesInfoData($params) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = isEmptyProperty($params, 'enterpriseId', '0'); $sql = <<<"EOT"
select
       E.EnterpriseCode "id",
       E.Name "name",
       E.PhoneNumber "phone",
       EB.EnterpriseBasicCode "basicId",
       EA.EnterpriseAddressCode "eAddressId",
       AL.AddressLocationCode "locationId",
       APT.ShortName "placeType",
       A.Place "placeName",
       AST.ShortName "streetType",
       A.Street "streetName",
       AL.House "house",
       AL.Corps "corps",
       AL.Flat "flat",
       EB.WorkerDirectorCode "directorId",
       Md.SurName "dSurName",
       Md.Name "dName",
       Md.SecondName "dSecondName",
       EB.WorkerAccountantCode "accountantId",
       Ma.SurName "aSurName",
       Ma.Name "aName",
       Ma.SecondName "aSecondName"
from Enterprise E
     left join EnterpriseBasic EB on EB.EnterpriseCode = E.EnterpriseCode
     left join EnterpriseAddress EA on EA.EnterpriseAddressCode = EB.EnterpriseAddressCode
     left join AddressLocation AL on AL.AddressLocationCode = EA.AddressLocationCode
     left join Address A on A.AddressCode = AL.AddressCode
     left join AddressPlaceType APT on APT.AddressPlaceTypeCode = A.AddressPlaceTypeCode
     left join AddressStreetType AST on AST.AddressStreetTypeCode = A.AddressStreetTypeCode
     left join WorkerInfo WId on WId.WorkerCode = EB.WorkerDirectorCode
     left join Man Md on Md.ManCode = WId.ManCode
     left join WorkerInfo WIa on WIa.WorkerCode = EB.WorkerAccountantCode
     left join Man Ma on Ma.ManCode = WIa.ManCode
where E.EnterpriseCode = $enterpriseId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function syncEnterprisesInfoData($params) { function getTypeId($db, $nameType, $type = 0) { $ret = 0; $tableName = $type === 0 ? 'AddressPlaceType' : 'AddressStreetType'; $fieldName = $type === 0 ? 'AddressPlaceTypeCode' : 'AddressStreetTypeCode'; $sql = <<<"EOT"
select $fieldName "typeId"
from $tableName
where ShortName = {$db->postgre("E")}'$nameType'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($row = $stmt->fetch()) { $ret = $row['typeId']; } return $ret; } function insertWorker($db, $surName, $name, $secondName, $enterpriseId) { $sql = <<<"EOT"
select ES.EnterpriseStructureCode "structureId"
from EnterpriseStructure ES
     join EnterpriseStructureInfo ESI on ESI.EnterpriseStructureCode = ES.EnterpriseStructureCode
                                     and ESI.Name = {$db->postgre("E")}'Сводная бухотчетность (веб)'
where ES.EnterpriseCode = $enterpriseId
order by 1
EOT;
$structureId = (int) selectSqlValue($db, $sql); if ($structureId == 0) { $sql = <<<"EOT"
insert into EnterpriseStructure(EnterpriseCode, StartDate, FinishDate, ForceUpdate)
values ($enterpriseId, 19000101, 30000101, 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $structureId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_EnterpriseStructure'); $sql = <<<"EOT"
insert into EnterpriseStructureInfo(ChangeDate, EnterpriseStructureCode, FullName, Name)
values (19000101, $structureId, {$db->postgre("E")}'Сводная бухотчетность (веб)',
        {$db->postgre("E")}'Сводная бухотчетность (веб)')
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } $sql = <<<"EOT"
insert into Man(SurName, Name, SecondName)
values ({$db->postgre("E")}'$surName', {$db->postgre("E")}'$name', {$db->postgre("E")}'$secondName')
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $manId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_Man'); $sql = <<<"EOT"
insert into Worker(EnterpriseCode, StartDate, FinishDate)
values ($enterpriseId, 19000101, 30000101)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $workerId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_Worker'); $sql = <<<"EOT"
insert into WorkerInfo(ChangeDate, EnterpriseStructureCode, ManCode, WorkerCode)
values (19000101, $structureId, $manId, $workerId)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $workerId; } $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode') ? : 0; $enterpriseId = isEmptyProperty($params, 'id', 0); $basicId = isEmptyProperty($params, 'basicId', 0); $eAddressId = isEmptyProperty($params, 'eAddressId', 0); $locationId = isEmptyProperty($params, 'locationId', 0); $placeType = isEmptyProperty($params, 'placeType', ''); $placeName = isEmptyProperty($params, 'placeName', ''); $streetType = isEmptyProperty($params, 'streetType', ''); $streetName = isEmptyProperty($params, 'streetName', ''); $house = isEmptyProperty($params, 'house', ''); $corps = isEmptyProperty($params, 'corps', ''); $flat = isEmptyProperty($params, 'flat', ''); $directorId = isEmptyProperty($params, 'directorId', 0); $dSurName = isEmptyProperty($params, 'dSurName', ''); $dName = isEmptyProperty($params, 'dName', ''); $dSecondName = isEmptyProperty($params, 'dSecondName', ''); $accountantId = isEmptyProperty($params, 'accountantId', 0); $aSurName = isEmptyProperty($params, 'aSurName', ''); $aName = isEmptyProperty($params, 'aName', ''); $aSecondName = isEmptyProperty($params, 'aSecondName', ''); $phone = isEmptyProperty($params, 'phone', ''); $db->beginTransaction(); try { $sql = <<<"EOT"
update Enterprise set
PhoneNumber = {$db->postgre("E")}'$phone'
where EnterpriseCode = $enterpriseId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($basicId === 0) { $sql = <<<"EOT"
insert into EnterpriseBasic(ChangeDate, EnterpriseCode)
values(19000101, $enterpriseId)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $basicId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_EnterpriseBasic'); } if ($placeType != '' || $placeName != '' || $streetType != '' || $streetName != '' || $house != '' || $corps != '' || $flat != '') { $placeTypeId = getTypeId($db, $placeType); $streetTypeId = getTypeId($db, $streetType, 1); $sql = <<<"EOT"
select AddressCode "addressId"
from Address
where (({$db->dialect("TRIM", "asa_trim")}({$db->postgre("E")}'$placeName') = {$db->postgre("E")}'') or (AddressPlaceTypeCode = $placeTypeId))
  and Place = {$db->postgre("E")}'$placeName'
  and (({$db->dialect("TRIM", "asa_trim")}({$db->postgre("E")}'$streetName') = {$db->postgre("E")}'') or (AddressStreetTypeCode = $streetTypeId))
  and Street = {$db->postgre("E")}'$streetName'
EOT;
$addressId = (int) selectSqlValue($db, $sql); if (!$addressId) { if ($placeTypeId === 0) { $sql = <<<"EOT"
insert into AddressPlaceType(ShortName)
values({$db->postgre("E")}'$placeType')
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $placeTypeId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_AddressPlaceType'); } if ($streetTypeId === 0) { $sql = <<<"EOT"
insert into AddressStreetType(ShortName)
values({$db->postgre("E")}'$streetType')
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $streetTypeId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_AddressStreetType'); } $sql = <<<"EOT"
insert into Address(AddressPlaceTypeCode, AddressStreetTypeCode, Place, Street, CountryCode)
values($placeTypeId, $streetTypeId, {$db->postgre("E")}'$placeName',
       {$db->postgre("E")}'$streetName', (select CountryCode
                                          from Enterprise
                                          where EnterpriseCode = $enterpriseCode))
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $addressId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_Address'); } if ($locationId === 0) { $sql = <<<"EOT"
insert into AddressLocation(AddressCode, House, Corps, Flat)
values ($addressId, {$db->postgre("E")}'$house', {$db->postgre("E")}'$corps', {$db->postgre("E")}'$flat')
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $locationId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_AddressLocation'); } else { $sql = <<<"EOT"
update AddressLocation set
    AddressCode = $addressId,
    House = {$db->postgre("E")}'$house',
    Corps = {$db->postgre("E")}'$corps',
    Flat = {$db->postgre("E")}'$flat'
where AddressLocationCode = $locationId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } if ($eAddressId === 0) { $sql = <<<"EOT"
insert into EnterpriseAddress(AddressLocationCode, AddressType, StartDate, FinishDate, EnterpriseCode)
values ($locationId, 0, 19000101, 30000101, $enterpriseId)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $eAddressId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_EnterpriseAddress'); } else { $sql = <<<"EOT"
update EnterpriseAddress set
    AddressLocationCode = $locationId
where EnterpriseAddressCode = $eAddressId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } else { $eAddressId = null; } $sql = <<<"EOT"
update EnterpriseBasic set
    EnterpriseAddressCode = ? /* eAddressId*/
where  EnterpriseBasicCode = $basicId
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $eAddressId ]); if ($directorId === 0) { if ($dSurName != '' || $dName != '' || $dSecondName != '') { $directorId = insertWorker($db, $dSurName, $dName, $dSecondName, $enterpriseId); $sql = <<<"EOT"
update EnterpriseBasic set
    WorkerDirectorCode = $directorId
where EnterpriseBasicCode = $basicId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } else { $sql = <<<"EOT"
update Man set
    SurName = {$db->postgre("E")}'$dSurName',
    Name = {$db->postgre("E")}'$dName',
    SecondName = {$db->postgre("E")}'$dSecondName'
where ManCode = (select {$db->sybase("first")} manCode
                 from WorkerInfo
                 where WorkerCode = $directorId {$db->postgre("limit 1")})
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } if ($accountantId === 0) { if ($aSurName != '' || $aName != '' || $aSecondName != '') { $accountantId = insertWorker($db, $aSurName, $aName, $aSecondName, $enterpriseId); $sql = <<<"EOT"
update EnterpriseBasic set
    WorkerAccountantCode = $accountantId
where EnterpriseBasicCode = $basicId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } else { $sql = <<<"EOT"
update Man set
    SurName = {$db->postgre("E")}'$aSurName',
    Name = {$db->postgre("E")}'$aName',
    SecondName = {$db->postgre("E")}'$aSecondName'
where ManCode = (select {$db->sybase("first")} manCode
                 from WorkerInfo
                 where WorkerCode = $accountantId {$db->postgre("limit 1")})
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } $db->commit(); $params = new stdClass; $params->enterpriseId = $enterpriseId; return $this->readEnterprisesInfo($params); } catch (Throwable $e) { $db->rollback(); throw $e; } } } $service = new Junior\Server(new map_tables_enterprisesInfo()); $service->process(); 