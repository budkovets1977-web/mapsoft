<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/common/addressPlaceTypes.php'); require_once(__DIR__ . '/../../map/common/addressStreetTypes.php'); resumeJsonRpc(); class map_common_addresses { const ps_None = 0; const ps_Name = 1; const ps_Quot = 2; const wk_None = 0; const wk_Prefix = 1; const wk_Char = 2; const wk_Digit = 3; const ei_any = 0; const ei_prefix = 1; const ei_name = 2; const ei_house = 3; const ei_corps = 4; const ei_flat = 5; private $db; private $srvAddressPlaceTypes; private $srvAddressStreetTypes; public function readAddresses($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isset($params->search) ? (string) $params->search : ""; $searchPlaceType = isset($params->searchPlaceType) ? (string) $params->searchPlaceType : ""; $searchStreetType = isset($params->searchStreetType) ? (string) $params->searchStreetType : ""; $countryId = isset($params->country) ? (int) $params->country : 0; $addressId = isEmptyProperty($params, 'id', 0); $excludeId = isEmptyProperty($params, 'excludeId', -1); $addressPlaceTypeId = isEmptyProperty($params,'addressPlaceTypeId'); $sqlAddressPlaceTypeId = ''; $place = isEmptyProperty($params,'place'); $sqlPlace = ''; $district = isEmptyProperty($params,'district'); $sqlDistrict = ''; $area = isEmptyProperty($params,'area'); $sqlArea = ''; $areaType = isEmptyProperty($params,'areaType'); $sqlAreaType = ''; $sqlParams = []; $excludeCountryId = isEmptyProperty($params, 'excludeCountryId'); $sqlExcludeCountry = ''; $organizationId = isEmptyProperty($params, 'organizationId'); $organizationWhereSql = ''; $organizationJoinSql = ''; if ($organizationId) { $organizationWhereSql = "AND ea.EnterpriseCode = :organizationId"; $sqlParams[':organizationId'] = $organizationId; $organizationJoinSql = "
            left join AddressLocation AL on AL.AddressCode = a.AddressCode
            left join EnterpriseAddress ea on ea.AddressLocationCode = AL.AddressLocationCode
            "; } if (isset($params->page)) { $page = (int) $params->page; } else { $page = 1; } $limit = (isset($params->limit) && $params->limit > 0) ? (int) $params->limit : 25; $start = (int) (($page - 1) * $limit); if ($start < 0) { $start = 0; } if (safe_strlen($search) == 1) { $start = 0; } if ($addressPlaceTypeId) { $sqlAddressPlaceTypeId = " AND a.AddressPlaceTypeCode = :addressPlaceTypeId"; $sqlParams[':addressPlaceTypeId'] = $addressPlaceTypeId; } if ($place) { $sqlPlace = " AND lower(a.Place) like lower(:place)"; $sqlParams[':place'] = $place; } if ($district) { $sqlDistrict = " AND lower(a.District) like lower(:district)"; $sqlParams[':district'] = $district; } if ($area) { $sqlArea = " AND lower(a.Area) like lower(:area)"; $sqlParams[':area'] = $area; } if ($areaType) { $sqlAreaType = " AND lower(a.AreaType) like lower(:areaType)"; $sqlParams[':areaType'] = $areaType; } if ($excludeCountryId) { $sqlExcludeCountry = " AND A.CountryCode <> :excludedCountryId"; $sqlParams[':excludedCountryId'] = $excludeCountryId; } $searchPlaceTypeCondition = ($searchPlaceType == "" ? "" : "COALESCE(p.ShortName, p.Name,  '') like  '%$searchPlaceType%' or"); $searchStreetTypeCondition = ($searchStreetType == "" ? "" : "COALESCE(s.ShortName, s.Name,  '') like  '%$searchStreetType%' or"); $searchCondition = <<<"EOT"
(($countryId = 0) or (a.CountryCode = $countryId)) and
(($addressId = 0) or (a.AddressCode = $addressId)) and
(COALESCE(a.AddressCode, -1) <> $excludeId) and
(
    COALESCE(s.ShortName, s.Name,  '') || COALESCE(a.Street,  '' ) like  '%$search%' or
    COALESCE(p.ShortName, p.Name,  '') || COALESCE(a.Place,  '' ) like  '%$search%' or
    $searchPlaceTypeCondition
    $searchStreetTypeCondition
    COALESCE(a.CodeSOATO,  '' ) like  '%$search%' or
    COALESCE(a.Region,  '' ) like  '%$search%' or
    COALESCE(a.District,  '' ) like  '%$search%' or
    COALESCE(a.Area,  '' ) ||  ' ' || COALESCE(a.AreaType,  '' ) like  '%$search%' or
    COALESCE(a.WardType,  '' ) ||  ' ' || COALESCE(a.Ward,  '' ) like  '%$search%'
)
$sqlAddressPlaceTypeId
$sqlPlace
$sqlDistrict
$sqlArea
$sqlAreaType
$organizationWhereSql
$sqlExcludeCountry
EOT;
$sqlTotal = <<<"EOT"
select count(*) as total
from Address a
/*left join Country c on c.CountryCode = a.CountryCode*/
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
left join AddressStreetType s on s.AddressStreetTypeCode = a.AddressStreetTypeCode
$organizationJoinSql
where
$searchCondition
EOT;
$total = (int) selectSqlValue($db,$sqlTotal, $sqlParams); if ($total < $start) { $start = 0; } !$db->isSybase ?: $start++; $sql = <<<"EOT"
select {$db->sybase("TOP $limit START AT $start")}
    a.AddressCode "id",
    a.CountryCode "countryId",
    a.Region "region",
    a.District "district",
    a.Area "area",
    a.Place "place",
    a.Ward "ward",
    a.Street "street",
    a.WardType "wardType",
    a.AreaType "areaType",
    a.CodeSOATO "codeSOATO",
    a.AddressPlaceTypeCode "addressPlaceTypeId",
    a.AddressStreetTypeCode "addressStreetTypeId",
    c.Name "country",
    COALESCE(p.ShortName, p.Name,  '') AS "placeType",
    COALESCE(s.ShortName, s.Name,  '') AS "streetType"
from Address a
left join Country c on c.CountryCode = a.CountryCode
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
left join AddressStreetType s on s.AddressStreetTypeCode = a.AddressStreetTypeCode
$organizationJoinSql
where
$searchCondition
        ORDER BY a.Street
{$db->postgre("limit $limit offset $start")}
EOT;
$root = new stdClass(); $root->total = $total; $root->items = selectSql($db,$sql,$sqlParams); return $root; } public function readToAddressCombo($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlFields = ''; $searchField = ''; $part = isEmptyProperty($params, 'part'); switch ($part) { case 'place': $sqlFields = <<<"EOT"
        a.place "addressPartText",
        COALESCE(p.ShortName, p.Name,  '') AS "addressPartType",
        CASE 
            WHEN COALESCE(p.ShortName, p.Name,  '') = '' 
            THEN a.place
            ELSE COALESCE(p.ShortName, p.Name,  '') || ' ' || a.place
        END  "displayAddressPart",
        (a.place like :search || '%') "isStart"
EOT;
$searchField = 'a.place'; break; case 'street': $sqlFields = <<<"EOT"
     a.street "addressPartText",
     COALESCE(s.ShortName, s.Name,  '') AS "addressPartType",
     CASE 
        WHEN COALESCE(s.ShortName, s.Name,  '') = '' 
        THEN a.street
        ELSE COALESCE(s.ShortName, s.Name,  '') || ' ' || a.street
     END  "displayAddressPart",
     (a.street like :search || '%') "isStart"
EOT;
$searchField = 'a.street'; break; } $search = isEmptyProperty($params, 'search', ''); $where = "WHERE $searchField like '%' || :search || '%'"; $sqlParams = [ ':search' => $search ]; $sql = <<<"EOT"
select distinct
    $sqlFields
from Address a
left join Country c on c.CountryCode = a.CountryCode
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
left join AddressStreetType s on s.AddressStreetTypeCode = a.AddressStreetTypeCode
$where
order by 4 desc, 2
EOT;
return selectSql($db, $sql, $sqlParams); } public function updateAddresses($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update Address set
       CountryCode = case when ? = 1 then ? else CountryCode end,
       Region = case when ? = 1 then ? else Region end,
       District = case when ? = 1 then ? else District end,
       Area = case when ? = 1 then ? else Area end,
       Place = case when ? = 1 then ? else Place end,
       Ward = case when ? = 1 then ? else Ward end,
       Street = case when ? = 1 then ? else Street end,
       WardType = case when ? = 1 then ? else WardType end,
       AreaType = case when ? = 1 then ? else AreaType end,
       CodeSOATO = case when ? = 1 then ? else CodeSOATO end,
       AddressPlaceTypeCode = case when ? = 1 then ? else AddressPlaceTypeCode end,
       AddressStreetTypeCode = case when ? = 1 then ? else AddressStreetTypeCode end
where AddressCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'countryId'), isEmptyProperty($row, 'countryId'), propertyExistsSQL($row, 'region'), isUndefinedProperty($row, 'region'), propertyExistsSQL($row, 'district'), isUndefinedProperty($row, 'district'), propertyExistsSQL($row, 'area'), isUndefinedProperty($row, 'area'), propertyExistsSQL($row, 'place'), isUndefinedProperty($row, 'place'), propertyExistsSQL($row, 'ward'), isUndefinedProperty($row, 'ward'), propertyExistsSQL($row, 'street'), isUndefinedProperty($row, 'street'), propertyExistsSQL($row, 'wardType'), isUndefinedProperty($row, 'wardType'), propertyExistsSQL($row, 'areaType'), isUndefinedProperty($row, 'areaType'), propertyExistsSQL($row, 'codeSOATO'), isUndefinedProperty($row, 'codeSOATO'), propertyExistsSQL($row, 'addressPlaceTypeId'), isEmptyProperty($row, 'addressPlaceTypeId'), propertyExistsSQL($row, 'addressStreetTypeId'), isEmptyProperty($row, 'addressStreetTypeId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteAddresses($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from Address where AddressCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createAddresses($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"

insert into Address (CountryCode, Region, District, Area, Place, Ward, Street, WardType, AreaType, CodeSOATO, AddressPlaceTypeCode, AddressStreetTypeCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $existsId = $this->isAddressExists($row); if ($existsId) { $rec->id = $existsId; } else { $stmt->execute([ isUndefinedProperty($row, 'countryId'), isUndefinedProperty($row, 'region'), isUndefinedProperty($row, 'district'), isUndefinedProperty($row, 'area'), isUndefinedProperty($row, 'place'), isUndefinedProperty($row, 'ward'), isUndefinedProperty($row, 'street'), isUndefinedProperty($row, 'wardType'), isUndefinedProperty($row, 'areaType'), isUndefinedProperty($row, 'codeSOATO'), isUndefinedProperty($row, 'addressPlaceTypeId'), isUndefinedProperty($row, 'addressStreetTypeId') ]); $rec->id = $db->lastInsertId('seq_Address'); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } function isAddressExists($params = null) { $res = null; if ($params) { $db = $this->db ? : $this->db = connectDb(); $countryId = isEmptyProperty($params, 'countryId', 0); $area = isEmptyProperty($params, 'area', ''); $district = isEmptyProperty($params, 'district', ''); $placeType = isEmptyProperty($params, 'addressPlaceTypeId', 0); $place = isEmptyProperty($params, 'place', ''); $streetType = isEmptyProperty($params, 'addressStreetTypeId', 0); $street = isEmptyProperty($params, 'street', ''); $sql = <<<"EOT"
    select
        a.AddressCode "id"
    from Address a
    where COALESCE(a.CountryCode,0) = $countryId
      and COALESCE(a.area,'') = '$area'
      and COALESCE(a.district,'') = '$district'
      and COALESCE(a.addressPlaceTypeCode,0) = $placeType
      and COALESCE(a.place,'') = '$place'
      and COALESCE(a.addressStreetTypeCode,0) = $streetType
      and COALESCE(a.street,'') = '$street'
    limit 1
    EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $row = $stmt->fetch(); $res = $row ? $row['id'] : null; } return $res; } public function getCurrentEnterpriseCounryId() { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT e.CountryCode "countryId"
FROM Enterprise e
WHERE e.EnterpriseCode = $enterpriseCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $countryId = fetchValue($stmt, 'countryId'); return $countryId; } public function readAddressSection($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isEmptyProperty($params, 'type', 0); $field = ''; switch ($type) { case 0: $field = 'AreaType'; break; case 1: $field = 'Area'; break; case 2: $field = 'District'; break; case 3: $field = 'Region'; break; case 4: $field = 'Place'; break; case 5: $field = 'WardType'; break; case 6: $field = 'Ward'; break; case 7: $field = 'Street'; break; case 8: $sql = <<<"EOT"
            select
                name "name"
            from country
            order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } $sql = <<<"EOT"
select distinct
    asa_trim($field) "name"
from Address
where $field is not null
  and asa_trim($field) <> ''
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function replaceAddress($params = null) { $db = $this->db ? : $this->db = connectDb(); $deleteId = isEmptyProperty($params, 'deleteId', 0); $replaceId = isEmptyProperty($params, 'replaceId', 0); $address = isEmptyProperty($params, 'address', ''); if ($deleteId === 0 || $replaceId ===0) { return null; } $sql = <<<"EOT"
select PR_syReplace( :TableName, :OldKey, :NewKey, :LogMsg )
EOT;
$stmt = $db->prepare($sql); $stmt->execute(['address', $deleteId, $replaceId, $address]); return (true); } public function isAddressUsed($params = null) { $db = $this->db ? : $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); if ($id === 0) { return null; } $sql = <<<"EOT"
select CASE EXISTS(
    select a.adr from (
        select addressCode as adr
        from AddressLocation where addressCode = :adrcod
        union all
        select addressCode as adr
        from ManPassport where addressCode = :adrcod2
        union all
        select manpassport_79.AddressResideCode as adr
        from ManPassport AS manpassport_79 where manpassport_79.AddressResideCode = :adrcod3
        union all
        select manpassport_103.AddressCodeBirth as adr
        from ManPassport AS manpassport_103 where manpassport_103.AddressCodeBirth =  :adrcod4
    ) AS a )  WHEN true then 1 WHEN false THEN 0 END as flag
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id, $id, $id, $id]); $row = $stmt->fetch(); $res = $row ? $row['flag'] : 0; return ($res === 1); } function formatAddressString($addr) { if (empty($addr)) { return $addr; } $prefix = array( 'г' => 'г.', 'ул' => 'ул.' ); $addressPlaceTypes = $this->srvAddressPlaceTypes ?: $this->srvAddressPlaceTypes = new map_common_addressPlaceTypes(); $addressStreetTypes = $this->srvAddressStreetTypes ?: $this->srvAddressStreetTypes = new map_common_addressStreetTypes(); $placeTypes = $addressPlaceTypes->readAddressPlaceTypes(); $streetTypes = $addressStreetTypes->readAddressStreetTypes(); foreach ($placeTypes as $val) { if ($val['shortName'] && !is_numeric($val['shortName'])) { if (mb_substr($val['shortName'], -1) === '.') { $val['shortName'] = mb_substr($val['shortName'], 0, -1); } $prefix[mb_strtolower($val['shortName'])] = mb_strtolower($val['shortName']) . '.'; } } foreach ($streetTypes as $val) { if ($val['shortName'] && !is_numeric($val['shortName'])) { if (mb_substr($val['shortName'], -1) === '.') { $val['shortName'] = mb_substr($val['shortName'], 0, -1); } $prefix[mb_strtolower($val['shortName'])] = mb_strtolower($val['shortName']) . '.'; } } $str = str_replace(array(',', 'д.', 'к.', 'кв.'), '', $addr); $array = safe_explode(" ", $str); foreach ($array as $k => $v) { $array[$k] = $prefix[mb_strtolower($v)] ?? $v; } return implode(" ", $array); } public function getAddressData($params = null) { $search = $this->formatAddressString(isEmptyProperty($params, 'search')); $multi = isEmptyProperty($params, 'multi'); if (!$search) { return null; } $res = $this->doParseAddress($search); if ($multi) { $fRes = $this->doFindAddressEx($res); $res->data = $fRes; } else { $fRes = $this->doFindAddress($res); if ($fRes) { $res = (object)array_merge((array)$res, (array)$fRes); } } return $res; } public function createAddressByField ($record) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"

insert into Address (CountryCode, Region, District, Area, Place, Ward, Street, WardType, AreaType, CodeSOATO, AddressPlaceTypeCode, AddressStreetTypeCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
        (select 
            p.AddressPlaceTypeCode
        from AddressPlaceType p
        where coalesce(p.ShortName, p.Name,  '') like ? or coalesce(p.ShortName, p.Name,  '') like ? || '.'
        order by 1 desc
        limit 1), 
        (select 
            s.AddressStreetTypeCode
        from AddressStreetType s
        where coalesce(s.ShortName, s.Name,  '') like ? or coalesce(s.ShortName, s.Name,  '') like ? || '.'
        order by 1 desc
        limit 1)
        )
EOT;
$stmt = $db->prepare($sql); $ret = []; $db->beginTransaction(); try { $ret[] = $rec = new stdClass; $strAddress = isUndefinedProperty($record, 'str'); if ($strAddress) { $parseParams = new stdClass(); $parseParams->search = $strAddress; $addressParsed = $this->getAddressData($parseParams); if ($addressParsed) { $existsId = $this->isAddressExistsForField($addressParsed); if ($existsId) { $rec->id = $existsId; } else { $place = isUndefinedProperty($addressParsed, 'place'); $street = isUndefinedProperty($addressParsed, 'street'); $placeType = isUndefinedProperty($addressParsed, 'placeType'); $streetType = isUndefinedProperty($addressParsed, 'streetType'); $stmt->execute([ null, null, null, null, $place, null, $street, null, null, null, $placeType, $placeType, $streetType, $streetType ]); $rec->id = $db->lastInsertId('seq_Address'); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } function isAddressExistsForField($params = null) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select 
    exists(
        select 
               * 
        from address a 
        left join AddressPlaceType p on p.addressPlaceTypeCode = a.addressPlaceTypeCode
        left join AddressStreetType s on s.addressStreetTypeCode = a.addressStreetTypeCode
        where a.place like :place 
            and a.street like :street
            and (coalesce(p.ShortName, p.Name,  '') like :placeType or coalesce(p.ShortName, p.Name,  '') like :placeType || '.')
            and (coalesce(s.ShortName, s.Name,  '') like :streetType or coalesce(s.ShortName, s.Name,  '') like :streetType || '.')
    ) 
    
EOT;
return selectSqlValue($db, $sql, [ ':place' => isEmptyProperty($params, 'place'), ':street' => isEmptyProperty($params, 'street'), ':placeType' => isEmptyProperty($params, 'placeType'), ':streetType' => isEmptyProperty($params, 'streetType') ]); } public function doParseAddress($addr = null) { if (empty($addr)) { return null; } $res = new stdClass(); $text = trim($addr); $sf = []; $ps = self::ps_None; $wk = self::wk_None; $startPos = 0; $charPassed = 0; $placeCheck = false; $i = 0; while ($i < mb_strlen($text)) { $char = mb_substr($text, $i, 1); switch ($ps) { case self::ps_None: switch ($char) { case '0': case '1': case '2': case '3': case '4': case '5': case '6': case '7': case '8': case '9': $ps = self::ps_Name; if (count($sf) >= 1 && end($sf)->wk == self::wk_Prefix) { $wk = self::wk_Char; } else { $wk = self::wk_Digit; } break; case ' ': case '.': break; case '"': $ps = self::ps_Quot; $wk = self::wk_Char; $i++; break; default: $ps = self::ps_Name; $wk = self::wk_Char; break; } if ($ps !== self::ps_None) { $startPos = $i; } break; case self::ps_Name: switch ($char) { case ' ': $ps = self::ps_None; break; case '-': case '/': case '\\': if ($wk == self::wk_Digit) { $ps = self::ps_None; } break; case '.': $ps = self::ps_None; $wk = self::wk_Prefix; break; } break; case self::ps_Quot: if ($char =='"') { $ps = self::ps_None; } break; } if ((($ps == self::ps_None || $i == mb_strlen($text)-1)) && ($wk !== self::wk_None)) { if (($i == mb_strlen($text)-1) && (mb_substr($text, $i, 1) !== '"')) { $i++; } $add = new stdClass(); $add->s = mb_substr($text, $startPos, $i - $startPos); $add->wk = $wk; $sf[] = $add; switch ($wk) { case self::wk_Char: $charPassed++; break; case self::wk_Digit: $charPassed = 0; break; } if ($charPassed > 1) { $placeCheck = true; } $wk = self::wk_None; } $i++; } if (count($sf) == 0) { return null; } $ei = self::ei_any; $charPassed = 0; $placeNameCheck = true; $addressPlaceTypes = $this->srvAddressPlaceTypes ?: $this->srvAddressPlaceTypes = new map_common_addressPlaceTypes(); $addressStreetTypes = $this->srvAddressStreetTypes ?: $this->srvAddressStreetTypes = new map_common_addressStreetTypes(); $placeTypes = $addressPlaceTypes->readAddressPlaceTypes(); $streetTypes = $addressStreetTypes->readAddressStreetTypes(); foreach ($sf as $value) { if ( $ei == self::ei_any || ($ei == self::ei_name && $value->wk >= self::wk_Char) || ($ei >= self::ei_house && $value->wk >= self::wk_Digit) ) { switch ($value->wk) { case self::wk_Prefix: $isPlaceType = false; $isStreetType = false; if (!$placeCheck && $charPassed == 0) { foreach ($placeTypes as $val) { if ($val['shortName'] && !is_numeric($val['shortName'])) { if (mb_substr($val['shortName'], -1) === '.') { $val['shortName'] = mb_substr($val['shortName'], 0, -1); } if (mb_strtolower($value->s) === mb_strtolower($val['shortName'])) { $isPlaceType = true; break; } } } foreach ($streetTypes as $val) { if ($val['shortName'] && !is_numeric($val['shortName'])) { if (mb_substr($val['shortName'], -1) === '.') { $val['shortName'] = mb_substr($val['shortName'], 0, -1); } if (mb_strtolower($value->s) === mb_strtolower($val['shortName'])) { $isStreetType = true; break; } } } } $placeNameCheck = $isPlaceType && !$isStreetType; if ($placeNameCheck || ($placeCheck && $charPassed == 0)) { $res->placeType = $value->s; $placeCheck = true; } else { if (!$placeCheck || ($placeCheck && $charPassed == 1)) { $res->streetType = $value->s; } } $ei = self::ei_name; break; case self::wk_Char: $isPlace = false; $isStreet = false; if (!$placeCheck && $placeNameCheck && $charPassed == 0) { $tmpParams = new stdClass(); $tmpParams->searchPlaceType = $value->s; $place = $this->readAddresses($tmpParams); $isPlace = $place->items[0]; $tmpParams = new stdClass(); $tmpParams->searchStreetType = $value->s; $street = $this->readAddresses($tmpParams); $isStreet = $street->items[0]; } if (($placeCheck && $charPassed == 0) || ($isPlace && !$isStreet)) { $res->place = $value->s; } else { if (!$placeCheck || ($placeCheck && $charPassed == 1)) { $res->street = $value->s; } } $charPassed++; $ei = self::ei_any; break; case self::wk_Digit: switch ($ei) { case self::ei_any: case self::ei_house: $res->house = $value->s; $ei = self::ei_corps; break; case self::ei_corps: if ($value === $sf[array_key_last($sf)]) { $res->flat = $value->s; } else { $res->corps = $value->s; $ei = self::ei_flat; } break; case self::ei_flat: $res->flat = $value->s; break; } break; } } } return $res; } public function doFindAddress($params) { $placeType = isset($params->placeType) ? (string) $params->placeType : ""; $place = isset($params->place) ? (string) $params->place : ""; $streetType = isset($params->streetType) ? (string) $params->streetType : ""; $street = isset($params->street) ? (string) $params->street : ""; if (empty($placeType) && empty($place) && empty($streetType) && empty($street)) { return null; } $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
with T (id, placetype, place, streetType, street) as (
select
    MAX(a.AddressCode) "id",
    COALESCE(p.ShortName, p.Name, '') "placeType",
    COALESCE(a.Place, '') "place",
    COALESCE(s.ShortName, s.Name, '') "streetType",
    COALESCE(a.Street, '') "street"
from Address a
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
left join AddressStreetType s on s.AddressStreetTypeCode = a.AddressStreetTypeCode
where
(
    COALESCE(p.ShortName, p.Name, '') like  '$placeType%' and
    COALESCE(a.Place, '') like '$place%' and
    COALESCE(s.ShortName, s.Name, '') like  '$streetType%' and
    COALESCE(a.Street, '') like '$street%'
)
group by 2,3,4,5
),
E (Place) as (
        select AD.Place
        from EnterpriseAddress EA
        join AddressLocation AL ON AL.AddressLocationCode = EA.AddressLocationCode
        join Address AD ON AD.AddressCode = AL.AddressCode
        where EA.EnterpriseCode = 1
          and TODAY() BETWEEN COALESCE(EA.StartDate,19000101) and COALESCE(EA.FinishDate,30000101)
        limit 1 )
select T.id, T.placeType "placeType", T.place, T.streetType "streetType", T.street, (SELECT COUNT(DISTINCT T.street) from T) "streetCount",
       (SELECT COUNT(DISTINCT T.place) from T) "placeCount",
       ( ('$place' <> '') or
         ('$place' = '' and COALESCE(t.Place, '') = '') or
         ('$place' = '' and COALESCE(t.Place, '') = E.Place) ) as flag
from T
left join E on True
order by flag desc, placeType desc, streetType desc
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $res = $stmt->fetch(); if ($res && ( ((int)$res['streetCount'] === 1 && (int)$res['flag'] === 1) || ((int)$res['streetCount'] === 1 && (int)$res['placeCount'] === 1) ) ) { $result = $res; } else { $result = null; } return $result; } public function doFindAddressEx($params) { $placeType = $addressId = isEmptyProperty($params, 'placeType', ""); $place = isEmptyProperty($params, 'place', ""); $streetType = isEmptyProperty($params, 'streetType', ""); $street = isEmptyProperty($params, 'street', ""); $house = isEmptyProperty($params, 'house', ""); $corps = isEmptyProperty($params, 'corps', ""); $flat = isEmptyProperty($params, 'flat', ""); if (empty($placeType) && empty($place) && empty($streetType) && empty($street)) { return null; } $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    a.AddressCode "id",
    c.name "country",
    a.area "area",
    a.district "district",
    COALESCE(p.ShortName, p.Name, '') "placeType",
    COALESCE(a.Place, '') "place",
    COALESCE(s.ShortName, s.Name, '') "streetType",
    COALESCE(a.Street, '') "street"
from Address a
left join country c on c.countryCode = a.countryCode
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
left join AddressStreetType s on s.AddressStreetTypeCode = a.AddressStreetTypeCode
where
    COALESCE(p.ShortName, p.Name, '') like  '$placeType%' and
    COALESCE(a.Place, '') like '$place%' and
    COALESCE(s.ShortName, s.Name, '') like  '$streetType%' and
    COALESCE(a.Street, '') like '$street%'

EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $res = []; while ($row = $stmt->fetch()) { $item = new stdClass; $item->id = $id = $row['id']; $item->country = $row['country']; $item->area = $row['area']; $item->district = $row['district']; $item->placeType = $row['placeType']; $item->place = $row['place']; $item->streetType = $row['streetType']; $item->street = $row['street']; $addr = $row['placeType']; $addr .= empty($row['place']) ? '' : (empty($addr) ? '' : (empty($row['placeType']) ? ', ' : ' ')).$row['place']; $addr .= empty($row['streetType']) ? '' : (empty($addr) ? '' : ', ').$row['streetType']; $addr .= empty($row['street']) ? '' : (empty($addr) ? '' : (empty($row['streetType']) ? ', ' : ' ')).$row['street']; $addr .= empty($house) ? '' : (empty($addr) ? '' : ', ').'д.'.$house; $addr .= empty($corps) ? '' : (empty($addr) ? '' : ', ').'к.'.$corps; $addr .= empty($flat) ? '' : (empty($addr) ? '' : ', ').'кв.'.$flat; $item->addressDisplay = $addr; $res[] = $item; } return $res; } } handleJsonRpc('map_common_addresses');