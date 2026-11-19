<?php
require_once(__DIR__ . '/../../init.php'); class map_common_organizations { private $db; const AMBULANCE_APP = 'ambulance'; const AMBULANCE_MOBILE_APP = 'ambulancemobile'; const AMBULANCE_TASK = 'mapCommonOrganizationKindsSMP'; public function readOrganizations($params = null){ $db = $this->db ?: $this->db = connectDb(); $onlyLPU = isEmptyProperty($params, 'onlyLPU'); $app = getSessionValue('app'); $APPS = [self::AMBULANCE_APP, self::AMBULANCE_MOBILE_APP]; $whereLPU = ''; if (in_array($app, $APPS) && $onlyLPU){ $smpSettings = readExtraSettings($db, self::AMBULANCE_TASK); $settingsOrganizationCategoryId = isUndefinedProperty($smpSettings, 'lpuOrganizationCategoryId'); if ($settingsOrganizationCategoryId){ $whereLPU = " AND org.\"kindsId\" LIKE '%,$settingsOrganizationCategoryId,%'"; } } $id = isEmptyProperty($params, 'id'); $search = isEmptyProperty($params, 'search', ""); $kind = isEmptyProperty($params, 'kind', 0); $sort = isEmptyProperty($params, 'sort', 1); $dir = isEmptyProperty($params, 'dir', ""); $parent = isEmptyProperty($params, 'parent', ""); $webAuth = isEmptyProperty($params, 'webAuth', 0); switch($webAuth) { case 0: $webAuth = ""; break; case 1: $webAuth = "AND WE.WebEnterpriseCode IS NOT NULL /*AND WE.Active = 1*/ AND COALESCE(WE.Online, 0) = 1"; break; case 2: $webAuth = "AND (WE.WebEnterpriseCode IS NULL OR COALESCE(WE.Online, 0) <> 1)"; break; default: $webAuth = ""; } if ($parent) { $parent = 'AND E.EnterpriseParentCode = ' . $parent; } $limit = isEmptyProperty($params, 'limit', 0); $page = isEmptyProperty($params, 'page', 1); $offset = ($page - 1) * $limit; $sort = str_replace("webOn", "21", $sort); if (safe_strlen($search) === 1) { $offset = 0; } $whereId = (empty($id) || !is_int($id)) ? '' : " and E.EnterpriseCode = {$id}"; $ciszOnly = isEmptyProperty($params, 'ciszOnly'); $whereCisz = $ciszOnly ? "AND EN.CiszGuid IS NOT NULL" : ""; $sql = <<<"EOT"
    E.EnterpriseCode AS "id",
    E.Name AS "name",
    E.FullName AS "fullName",
    E.Cipher AS "cipher",
    E.CountryCode AS "countryId",
    E.PhoneNumber AS "phone",
    E.HowToGo AS "note",
    E.EnterpriseParentCode AS "parentId",
    E1.Name AS "parent",
    EN.CodeUNN AS "unn",
    AL.AddressCode AS "addressId",
    {$db->dialect("
    (TRIM(
    IF (TRIM(A.Area) || '') <> ''
       THEN TRIM(A.Area) || IF (TRIM(A.AreaType) || '') <> ''
                               THEN ' ' || TRIM(A.AreaType)
                            ENDIF || ', '
    ENDIF ||
    IF (TRIM(A.District) || '') <> ''
       THEN TRIM(A.District) || ' р-н, '
    ENDIF ||
    IF (TRIM(A.Region) || '') <> ''
       THEN TRIM(A.Region) || ', '
    ENDIF ||
    IF (TRIM(A.Place) || '') <> ''
       THEN IF (TRIM(PT.ShortName) || '') <> ''
               THEN TRIM(PT.ShortName) || ' '
            ENDIF || TRIM(A.Place) || ', '
    ENDIF ||
    IF (TRIM(A.Ward) || '') <> ''
       THEN IF (TRIM(A.WardType) || '') <> ''
               THEN TRIM(A.WardType) || ' '
            ENDIF || TRIM(A.Ward) || ', '
    ENDIF ||
    IF (TRIM(A.Street) || '') <> ''
       THEN IF (TRIM(ST.ShortName) ||  '') <> ''
               THEN TRIM(ST.ShortName) || ' '
            ENDIF || TRIM(A.Street)
    ENDIF) || '')", "
    (asa_trim(
    CASE (asa_trim(A.Area) ||  E'' ) <> ( E'' )
      WHEN true then asa_trim(A.Area)  ||
        CASE (asa_trim(A.AreaType) ||  E'' ) <> ( E'' )
          WHEN true then  E' ' || asa_trim(A.AreaType)
        END || E', '
    END ||
    CASE (asa_trim(A.District) ||  E'' ) <> ( E'' )
      WHEN true then asa_trim(A.District) ||  E' р-н, '
    END ||
    CASE (asa_trim(A.Region) ||  E'' ) <> ( E'' )
      WHEN true then asa_trim(A.Region) ||  E', '
    END ||
    CASE (asa_trim(A.Place) ||  E'' ) <> ( E'' )
      WHEN true then
        CASE (asa_trim(PT.ShortName) ||  E'' ) <> ( E'' )
          WHEN true then asa_trim(PT.ShortName) ||  E' '
        END || asa_trim(A.Place) ||  E', '
    END ||
    CASE (asa_trim(A.Ward) ||  E'' ) <> ( E'' )
      WHEN true then
        CASE (asa_trim(A.WardType) ||  E'' ) <> ( E'' )
          WHEN true then asa_trim(A.WardType) ||  E' '
        END || asa_trim(A.Ward) ||  E', '
    END ||
    CASE (asa_trim(A.Street) ||  E'' ) <> ( E'' )
      WHEN true then
        CASE (asa_trim(ST.ShortName) ||  E'' ) <> ( E'' )
          WHEN true then asa_trim(ST.ShortName) ||  E' '
        END || asa_trim(A.Street)
    END) || E'')
        ")} AS "address",
    AL.House AS "house",
    AL.Corps AS "corps",
    AL.Flat AS "flat",
    AL.ZipCode AS "zipcode",
    AL.AddressLocationCode AS "addressLocationId",
    EA.EnterpriseAddressCode AS "organizationAddressId",
    CAST((select {$db->dialect("LIST(K.Name ORDER BY K.Name)", "string_agg((K.Name)::text, E', ' order by K.Name)")}
          from EnterpriseInKind AS EK
               join EnterpriseKind AS K on K.EnterpriseKindCode = EK.EnterpriseKindCode
          where EK.EnterpriseCode = E.EnterpriseCode) as varchar(254)) AS "kinds",
    CAST((select ',' || {$db->dialect("LIST(K.EnterpriseKindCode ORDER BY K.EnterpriseKindCode)", "string_agg((K.EnterpriseKindCode)::text, E',' order by K.EnterpriseKindCode)")} || ','
          from EnterpriseInKind AS EK
               join EnterpriseKind AS K on K.EnterpriseKindCode = EK.EnterpriseKindCode
          where EK.EnterpriseCode = E.EnterpriseCode) as varchar(254)) AS "kindsId",
    CASE WHEN WE.WebEnterpriseCode IS NOT NULL /*AND WE.Active = 1*/ AND COALESCE(WE.Online, 0) = 1 THEN 1 ELSE 0 END "webOn",
    WE.UnionGroup "unionGroup",
    WE.Domains "domains",
    EN.CiszGuid "ciszGuid",
    CASE 
    WHEN NOT EXISTS (
        SELECT 1 
        FROM EnterpriseInKind EK
        WHERE EK.EnterpriseCode = E.EnterpriseCode
    )
    OR EXISTS (
        SELECT 1
        FROM EnterpriseInKind EK
        JOIN EnterpriseKind K 
            ON K.EnterpriseKindCode = EK.EnterpriseKindCode
        WHERE EK.EnterpriseCode = E.EnterpriseCode
          AND (K.Type IN (0,1) OR K.Type IS NULL)
    )
    THEN true
    ELSE false
END AS "isWork"
from Enterprise AS E
    left join EnterpriseNumber AS EN ON EN.EnterpriseCode = E.EnterpriseCode
    left join Enterprise AS E1 on E1.EnterpriseCode = E.EnterpriseParentCode
    left join EnterpriseBasic AS EB ON EB.EnterpriseCode = E.EnterpriseCode
    left join EnterpriseAddress AS EA ON EA.EnterpriseAddressCode = EB.EnterpriseAddressCode
    left join AddressLocation as AL ON AL.AddressLocationCode = EA.AddressLocationCode
    left join Address AS A on A.AddressCode = AL.AddressCode
    left join AddressPlaceType AS PT on PT.AddressPlaceTypeCode = A.AddressPlaceTypeCode
    left join AddressStreetType AS ST on ST.AddressStreetTypeCode = A.AddressStreetTypeCode
    left join WebEnterprise as WE on WE.EnterpriseCode = E.EnterpriseCode
    /*
    left join lateral(
        select {$db->sybase("first")}
            WebEnterpriseCode, Active, Online, UnionGroup
        from WebEnterprise where EnterpriseCode = E.EnterpriseCode
        order by WebEnterpriseCode {$db->postgre("limit 1")} ) as WE
    */
where E.EnterpriseCode <> 0
    and (WE.WebEnterpriseCode is null or fn_WebUserAccessOrganization(e.EnterpriseCode) = 1)
    AND (
        ($kind = 0) or
        (EXISTS(
          SELECT EnterpriseInKindCode
          FROM EnterpriseInKind
          WHERE EnterpriseCode = E.EnterpriseCode
            AND EnterpriseKindCode = $kind))
      )
  AND ((COALESCE(E.Name,  '' ) LIKE '%$search%') OR (E.Cipher LIKE '%$search%'))
  {$whereId}
  $parent
  $webAuth
  $whereCisz
EOT;
$sqlTotal = <<<"EOT"
            SELECT COUNT(*) total
            FROM (
                SELECT
                $sql
            ) org
            WHERE 1 = 1
            $whereLPU
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $root = new stdClass(); $total = (int) fetchValue($stmtTotal, 'total'); $root->total = $total; $root->webOptionsAccess = testUserAccess('map-common-organizations-weboptions', $db); $offset = $total < $offset ? 0 : $offset; if($limit) { $sqlFull = <<<"EOT"
                SELECT *
                FROM (
                    SELECT {$db->sybase("TOP $limit START AT $offset")}
                    $sql
                    ORDER BY $sort $dir
                    {$db->postgre("LIMIT $limit OFFSET $offset")}
                ) org
                WHERE 1 = 1
                $whereLPU
EOT;
} else { $sqlFull = <<<"EOT"
                SELECT *
                FROM (
                    SELECT $sql
                    ORDER BY $sort $dir
                ) org
                WHERE 1 = 1
                $whereLPU
EOT;
} $stmt = $db->prepare($sqlFull); $stmt->execute(); $root->items = $stmt->fetchAll(); return $root; } public function updateOrganizations($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sqlInsertE = <<<"EOT"
INSERT INTO Enterprise (Name, FullName, Cipher, CountryCode, PhoneNumber,
    EnterpriseParentCode, HowToGo, StartDate, FinishDate)
VALUES (?, ?, RIGHT(REPEAT(' ' , 10) || TRIM(?), 10), ?, ?, ?, ?, YMD(1900, 1, 1), YMD(3000, 1, 1))
EOT;
$stmtInsertE = $db->prepare($sqlInsertE); $sqlUpdateE = <<<"EOT"
UPDATE Enterprise SET
  Name = case when ? = 1 then ? else Name end,
  FullName = case when ? = 1 then ? else FullName end,
  Cipher = case when ? = 1 then RIGHT(REPEAT(' ' , 10) || TRIM(?), 10) else Cipher end,
  CountryCode = case when ? = 1 then ? else CountryCode end,
  PhoneNumber = case when ? = 1 then ? else PhoneNumber end,
  EnterpriseParentCode = case when ? = 1 then ? else EnterpriseParentCode end,
  HowToGo = case when ? = 1 then ? else HowToGo end
WHERE EnterpriseCode = ?
EOT;
$stmtUpdateE = $db->prepare($sqlUpdateE); $sqlInsertEB = <<<"EOT"
INSERT INTO EnterpriseBasic (ChangeDate, EnterpriseCode, EnterpriseAddressCode)
VALUES (YMD(1900, 1, 1), ?, ?)
EOT;
$stmtInsertEB = $db->prepare($sqlInsertEB); $sqlUpdateEB = <<<"EOT"
UPDATE EnterpriseBasic SET
  EnterpriseAddressCode = ?
WHERE EnterpriseCode = ?
EOT;
$stmtUpdateEB = $db->prepare($sqlUpdateEB); $sqlInsertEI = <<<"EOT"
INSERT INTO EnterpriseInfo (ChangeDate, EnterpriseCode, Name, FullName, Cipher, EnterpriseParentCode)
VALUES (YMD(1900, 1, 1), ?, ?, ?, RIGHT(REPEAT(' ' , 10) || TRIM(?), 10), ?)
EOT;
$stmtInsertEI = $db->prepare($sqlInsertEI); $sqlUpdateEI = <<<"EOT"
UPDATE EnterpriseInfo SET
  Name = case when ? = 1 then ? else Name end,
  FullName = case when ? = 1 then ? else FullName end,
  Cipher = case when ? = 1 then RIGHT(REPEAT(' ' , 10) || TRIM(?), 10) else Cipher end,
  EnterpriseParentCode = case when ? = 1 then ? else EnterpriseParentCode end
WHERE EnterpriseCode = ?
EOT;
$stmtUpdateEI = $db->prepare($sqlUpdateEI); $sqlInsertEN = <<<"EOT"
INSERT INTO EnterpriseNumber (ChangeDate, EnterpriseCode, CodeUNN)
VALUES (YMD(1900, 1, 1), ?, ?)
EOT;
$stmtInsertEN = $db->prepare($sqlInsertEN); $sqlUpdateEN = <<<"EOT"
UPDATE EnterpriseNumber SET
    CodeUNN = CASE WHEN ? = 1 THEN ? ELSE CodeUNN END
WHERE EnterpriseCode = ?
EOT;
$stmtUpdateEN = $db->prepare($sqlUpdateEN); $sqlInsertAL = <<<"EOT"
INSERT INTO AddressLocation (AddressCode, House, Corps, Flat, Zipcode)
VALUES (?, ?, ?, ?, ?)
EOT;
$stmtInsertAL = $db->prepare($sqlInsertAL); $sqlUpdateAL = <<<"EOT"
UPDATE AddressLocation SET
  AddressCode = case when ? = 1 then ? else AddressCode end,
  House = case when ? = 1 then ? else House end,
  Corps = case when ? = 1 then ? else Corps end,
  Flat = case when ? = 1 then ? else Flat end,
  Zipcode = case when ? = 1 then ? else Zipcode end
WHERE AddressLocationCode = ?
EOT;
$stmtUpdateAL = $db->prepare($sqlUpdateAL); $sqlInsertEA = <<<"EOT"
INSERT INTO EnterpriseAddress (AddressType, StartDate, FinishDate, EnterpriseCode, AddressLocationCode)
VALUES (0, YMD(1900, 1, 1), YMD(3000, 1, 1), ?, ?)
EOT;
$stmtInsertEA = $db->prepare($sqlInsertEA); $sqlUpdateEA = <<<"EOT"
UPDATE EnterpriseAddress SET
  AddressLocationCode = ?
WHERE EnterpriseAddressCode = ?
EOT;
$stmtUpdateEA = $db->prepare($sqlUpdateEA); $sqlInsertWeb = <<<"EOT"
INSERT INTO WebEnterprise (EnterpriseCode, Active, Name)
VALUES (?, 1, ?)
EOT;
$stmtInsertWeb = $db->prepare($sqlInsertWeb); $sqlInsertWebOptions = <<<"EOT"
UPDATE WebEnterprise SET
    UnionGroup = ?,
    Domains = ?,
    Online = ?
WHERE EnterpriseCode = ?
EOT;
$stmtInsertWebOptions = $db->prepare($sqlInsertWebOptions); $sqlUpdateWeb = <<<"EOT"
UPDATE WebEnterprise SET
  UnionGroup = case when ? = 1 and ? = 1 then ? else UnionGroup end,
  Domains = case when ? = 1 and  ? = 1 then ? else Domains end,
  Online = case when ? = 1 and  ? = 1 then ? else Online end,
  Name = case when ? = 1 then ? else Name end
WHERE EnterpriseCode = ?
EOT;
$stmtUpdateWeb = $db->prepare($sqlUpdateWeb); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; if (empty($row->id)) { $stmtInsertE->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'fullName'), isEmptyProperty($row, 'cipher'), isEmptyProperty($row, 'countryId'), isEmptyProperty($row, 'phone'), isEmptyProperty($row, 'parentId'), isEmptyProperty($row, 'note') ]); $rec->id = $db->lastInsertId(); } else { $stmtUpdateE->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'fullName'), isUndefinedProperty($row, 'fullName'), propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'cipher'), propertyExistsSQL($row, 'countryId'), isEmptyProperty($row, 'countryId'), propertyExistsSQL($row, 'phone'), isEmptyProperty($row, 'phone'), propertyExistsSQL($row, 'parentId'), isEmptyProperty($row, 'parentId'), propertyExistsSQL($row, 'note'), isEmptyProperty($row, 'note'), ($rec->id = $row->id) ]); } if (empty(selectSqlValue($db, 'select 1 from EnterpriseInfo where EnterpriseCode = ?', [$rec->id]))) { $stmtInsertEI->execute([ $rec->id, isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'fullName'), isEmptyProperty($row, 'cipher'), isEmptyProperty($row, 'parentId') ]); } else { $stmtUpdateEI->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'fullName'), isUndefinedProperty($row, 'fullName'), propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'cipher'), propertyExistsSQL($row, 'parentId'), isEmptyProperty($row, 'parentId'), $rec->id ]); } if (empty(selectSqlValue($db, 'select 1 from EnterpriseNumber where EnterpriseCode = ?', [$rec->id]))) { $stmtInsertEN->execute([ $rec->id, isEmptyProperty($row, 'unn') ]); } else { $stmtUpdateEN->execute([ propertyExistsSQL($row, 'unn'), isEmptyProperty($row, 'unn'), $rec->id ]); } if (empty($row->addressLocationId)) { $stmtInsertAL->execute([ isEmptyProperty($row, 'addressId'), isEmptyProperty($row, 'house'), isEmptyProperty($row, 'corps'), isEmptyProperty($row, 'flat'), isEmptyProperty($row, 'zipcode') ]); $rec->addressLocationId = $db->lastInsertId(); } else { $stmtUpdateAL->execute([ propertyExistsSQL($row, 'addressId'), isEmptyProperty($row, 'addressId'), propertyExistsSQL($row, 'house'), isEmptyProperty($row, 'house'), propertyExistsSQL($row, 'corps'), isEmptyProperty($row, 'corps'), propertyExistsSQL($row, 'flat'), isEmptyProperty($row, 'flat'), propertyExistsSQL($row, 'zipcode'), isEmptyProperty($row, 'zipcode'), ($rec->addressLocationId = $row->addressLocationId) ]); } if (empty($row->organizationAddressId)) { $stmtInsertEA->execute([ $rec->id, $rec->addressLocationId ]); $rec->organizationAddressId = $db->lastInsertId(); } else { $stmtUpdateEA->execute([ $rec->addressLocationId, ($rec->organizationAddressId = $row->organizationAddressId) ]); } if (empty(selectSqlValue($db, 'select 1 from EnterpriseBasic where EnterpriseCode = ?', [$rec->id]))) { $stmtInsertEB->execute([ $rec->id, $rec->organizationAddressId ]); } else { $stmtUpdateEB->execute([ $rec->organizationAddressId, $rec->id ]); } $webOptionsAccess = (int) testUserAccess('map-common-organizations-weboptions', $db); if (empty(selectSqlValue($db, 'select 1 from WebEnterprise where EnterpriseCode = ?', [$rec->id]))) { $stmtInsertWeb->execute([ $rec->id, isUndefinedProperty($row, 'name'), ]); if ($webOptionsAccess) { $stmtInsertWebOptions->execute([ isEmptyProperty($row, 'unionGroup'), isEmptyProperty($row, 'domains'), isEmptyProperty($row, 'webOn', 0), $rec->id, ]); } } else { $stmtUpdateWeb->execute([ propertyExistsSQL($row, 'unionGroup'), $webOptionsAccess, isEmptyProperty($row, 'unionGroup'), propertyExistsSQL($row, 'domains'), $webOptionsAccess, isEmptyProperty($row, 'domains'), propertyExistsSQL($row, 'webOn'), $webOptionsAccess, isEmptyProperty($row, 'webOn', 0), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $rec->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createOrganizations($records) { return $this->updateOrganizations($records); } public function deleteOrganizations($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $inAddressLocation = createSqlInCondition($records, 'addressLocationId'); $sql = <<<"EOT"
delete from Enterprise where EnterpriseCode $in
EOT;
$sqlAddressLocation = <<<"EOT"
delete from AddressLocation where AddressLocationCode $inAddressLocation
EOT;
$stmt = $db->prepare($sql); $stmtAddressLocation = $db->prepare($sqlAddressLocation); $db->beginTransaction(); try { $stmt->execute(); $stmtAddressLocation->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getCountryIdByCipher($cipher = 'BLR') { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    countrycode AS "id"
from country
where cipher = '$cipher'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $countryId = fetchValue($stmt, 'id'); return $countryId; } public function getNextCipher($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select incstr(COALESCE(TRIM(
    (select {$db->sybase("first")} cipher from Enterprise ORDER BY cipher DESC {$db->postgre("LIMIT 1")})
), '0')) AS "cipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'cipher'); return $cipher; } public function readUnionGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select UnionGroup "name"
from WebEnterprise
where coalesce(UnionGroup, '') <> ''
group by UnionGroup
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('map_common_organizations');