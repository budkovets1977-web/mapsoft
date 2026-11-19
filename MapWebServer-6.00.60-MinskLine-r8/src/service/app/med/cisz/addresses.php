<?php
 require_once(__DIR__ . '/../../init.php'); class med_cisz_addresses { private $db; public function getSearchAddress($params) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'addressId'); $house = isUndefinedProperty($params, 'house'); $sql = <<<"EOT"
SELECT
    AD.Place "place",
    APT.ShortName "placeType",
    AD.Street "street",
    AST.Name "streetType"
FROM Address AD
LEFT JOIN AddressPlaceType AS APT ON APT.AddressPlaceTypeCode = AD.AddressPlaceTypeCode
LEFT JOIN AddressStreetType AS AST ON AST.AddressStreetTypeCode = AD.AddressStreetTypeCode
WHERE AD.AddressCode = :id
EOT;
$address = selectSql($db, $sql, [ ':id' => $id ]); if (empty($address)) { return ''; } $address = $address[0]; $placeType = trim($address['placeType'] ?? ''); if ($placeType && !str_ends_with($placeType, '.')) { $placeType .= '.'; } $streetType = mb_convert_case( trim($address['streetType'] ?? ''), MB_CASE_TITLE, 'UTF-8' ); $street = mb_convert_case( trim($address['street'] ?? ''), MB_CASE_TITLE, 'UTF-8' ); return trim( "{$placeType} {$address['place']}, {$streetType} {$street}, д.{$house}" ); } public function getBelarusId() { $db = $this->db ?: $this->db = connectDb(); return selectSqlValue( $db, "SELECT C.CountryCode 
     FROM Country C 
     WHERE (C.Cipher = '112' OR NULLIF(C.Cipher, '') IS NULL) AND C.Name LIKE '%Беларусь%'
     ORDER BY C.Cipher DESC, C.CountryCode
     LIMIT 1" ); } public function readCiszStates($params = null) { $db = $this->db ?: $this->db = connectDb(); $clinicId = isEmptyProperty($params, 'clinicCode'); $result = selectSql($db, <<<SQL
    SELECT
        R.CiszAddressPartCode "id",
        R.Name "name",
        R.Code "ciszCode",
        R.Level "level"
    FROM CiszAddressPart R
    WHERE R.CiszAddressPartCodeParent IS NULL
    ORDER BY R.Name;
    SQL)
; return $result; } public function readCiszDistricts($params = null) { $db = $this->db ?: $this->db = connectDb(); $clinicId = isEmptyProperty($params, 'clinicCode'); $result = selectSql($db, <<<SQL
    SELECT
        R.CiszAddressPartCode "id",
        R.Name "name",
        R.Code "ciszCode",
        R.Level "level",
        R.CiszAddressPartCodeParent "parentId"
    FROM CiszAddressPart R
    JOIN CiszAddressPart A ON R.CiszAddressPartCodeParent = A.CiszAddressPartCode
    WHERE A.CiszAddressPartCodeParent IS NULL
    ORDER BY R.Name;
    SQL)
; return $result; } public function readCiszCities($params = null) { $db = $this->db ?: $this->db = connectDb(); $clinicId = isEmptyProperty($params, 'clinicCode'); $result = selectSql($db, <<<SQL
    SELECT
        R.CiszAddressPartCode "id",
        R.Name "name",
        R.Code "ciszCode",
        R.Level "level",
        R.CiszAddressPartCodeParent "parentId"
    FROM CiszAddressPart R
    JOIN CiszAddressPart A ON R.CiszAddressPartCodeParent = A.CiszAddressPartCode
    JOIN CiszAddressPart B ON A.CiszAddressPartCodeParent = B.CiszAddressPartCode
    WHERE B.CiszAddressPartCodeParent IS NULL
    ORDER BY R.Name;
    SQL)
; return $result; } public function getAddressCode($params): ?int { $db = $this->db ?: $this->db = connectDb(); $partOf = isUndefinedProperty($params, 'partOf'); $address = isUndefinedProperty($params, 'address'); $belarus = isUndefinedProperty($params, 'belarusId'); $addressId = isUndefinedProperty($params, 'addressId'); $parsed = $this->parseAddress($db, $address); if (!$addressId) { $addressId = selectSqlValue($db, <<<SQL
        SELECT
            AddressCode
        FROM Address
          WHERE CodeSoato = :soato
        LIMIT 1
    SQL,
[ ':soato' => $partOf ]); } if ($addressId) { $this->updateAddressIfChanged($db, $addressId, $parsed, $belarus); return (int)$addressId; } return $this->createParentAddress($db, $parsed, $partOf, $belarus); } private function updateAddressIfChanged($db, int $addressCode, array $p, $belarus) { $sql = <<<'SQL'
        UPDATE Address
        SET
            CountryCode = :countrycode,
            Region = :region,
            District = :district,
            Area = :area,
            Place = :place,
            Street = :street,
            AreaType = :areatype,
            AddressStreetTypeCode = :streettype,
            AddressPlaceTypeCode = :placetype
        WHERE AddressCode = :code
          AND (
              CountryCode IS DISTINCT FROM :countrycode_cmp
              OR Region IS DISTINCT FROM :region_cmp
              OR District IS DISTINCT FROM :district_cmp
              OR Area IS DISTINCT FROM :area_cmp
              OR Place IS DISTINCT FROM :place_cmp
              OR Street IS DISTINCT FROM :street_cmp
              OR AreaType IS DISTINCT FROM :areatype_cmp
              OR AddressStreetTypeCode IS DISTINCT FROM :streettype_cmp
              OR AddressPlaceTypeCode IS DISTINCT FROM :placetype_cmp
          )
    SQL;
$values = [ 'countrycode' => $belarus, 'region' => $p['region'], 'district' => $p['district'], 'area' => $p['area'], 'place' => $p['city'], 'street' => $p['street'], 'areatype' => $p['area'] ? 'обл' : '', 'streettype' => $p['streetType'], 'placetype' => $p['cityType'], ]; $binds = [':code' => $addressCode]; foreach ($values as $key => $value) { $binds[":$key"] = $value; $binds[":{$key}_cmp"] = $value; } $stmt = $db->prepare($sql); $stmt->execute($binds); } public function parseAddress($db, $address) { $city = $address->city ?? null; $fullName = trim($address->fullName ?? ''); $district = null; $region = null; $street = null; $cityType = null; $streetType = null; $area = null; $streetTypes = selectSql($db, <<<SQL
        SELECT
            AddressStreetTypeCode "id",
            Name "name",
            ShortName "shortName"
        FROM AddressStreetType
    SQL)
; $cityTypes = selectSql($db, <<<SQL
        SELECT
            AddressPlaceTypeCode "id",
            Name "name",
            ShortName "shortName"
        FROM AddressPlaceType
    SQL)
; if ($fullName) { $parts = array_map( 'trim', preg_split('/\s*,\s*/u', $fullName) ); $parts = array_values(array_filter($parts, fn($p) => $p !== '')); foreach ($parts as $i => $part) { if ($district && $region && $area) { break; } if ($area === null) { if (preg_match('/^(.*?)\s+область$/iu', $part, $m)) { $area = trim($m[1]); $usedIndexes[$i] = true; continue; } } if ($district === null) { if (preg_match('/^(.*?)\s+район$/iu', $part, $m)) { $district = trim($m[1]); $usedIndexes[$i] = true; continue; } } if (preg_match('/^(.*?)\s+с\/с$/iu', $part, $m)) { $region = trim($m[1]); $usedIndexes[$i] = true; continue; } } if ($city) { if (preg_match('/^(.+?\.)\s*(.+)$/u', $city, $matches)) { foreach ($parts as $i => $part) { if (mb_strtolower(trim($part)) === mb_strtolower($city)) { $usedIndexes[$i] = true; break; } } $type = trim($matches[1]); $normalizedType = mb_strtolower(str_replace('.', '', $type)); foreach ($cityTypes as $cityTypeItem) { $shortName = trim($cityTypeItem['shortName'] ?? ''); $normalizedShortName = mb_strtolower(str_replace('.', '', $shortName)); if ($normalizedShortName !== '' && $normalizedType === $normalizedShortName) { $cityType = $cityTypeItem['id']; break; } } $city = trim(preg_replace('/^[^.]+\.\s*/u', '', $city)); } } usort($streetTypes, function ($a, $b) { return mb_strlen($b['name'] ?? '') <=> mb_strlen($a['name'] ?? ''); }); foreach ($parts as $i => $part) { if (isset($usedIndexes[$i])) continue; $streetValue = trim($part); if (preg_match('/\bд\./iu', $streetValue)) { $usedIndexes[$i] = true; continue; } foreach ($streetTypes as $streetTypeItem) { $name = trim($streetTypeItem['name'] ?? ''); if ($name === '') continue; $pattern = '/^' . preg_quote($name, '/') . '(?:\s+|$)/iu'; if (preg_match($pattern, $streetValue)) { $streetType = $streetTypeItem['id']; $street = trim(preg_replace($pattern, '', $streetValue, 1)); $usedIndexes[$i] = true; break 2; } } } } return compact('region', 'district', 'area', 'city', 'street', 'cityType', 'streetType'); } private function createParentAddress($db, array $p, $partOf, $belarus): int { $sql = <<<"EOT"
        INSERT INTO Address (CountryCode, Region, District,
                             Area, Place,
                             Street, AreaType,
                             CodeSOATO, AddressStreetTypeCode, AddressPlaceTypeCode)
        VALUES (?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?)
    EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $belarus, $p['region'], $p['district'], $p['area'], $p['city'], $p['street'], $p['area'] ? 'обл' : '', $partOf, $p['streetType'], $p['cityType'], ]); return (int)$db->lastInsertId('seq_Address'); } } handleJsonRpc('med_cisz_addresses');