<?php
 require_once(__DIR__.'/../../init.php'); class map_common_cities { private $db; public function readCities($params = null) { $db = $this->db ?: $this->db = connectDb(); $excludeCountryId = isUndefinedProperty($params, 'excludeCountryId'); $where = ''; if ($excludeCountryId) { $where .= " AND A.CountryCode <> $excludeCountryId"; } $sql = <<<"EOT"
select 
    a.Place "place",
    a.AddressPlaceTypeCode "addressPlaceTypeId",
    COALESCE(p.ShortName, p.Name,  '') AS "placeType",
    a.District "district",
    a.Area "area",
    a.AreaType "areaType"
from Address a
left join AddressPlaceType p on p.AddressPlaceTypeCode = a.AddressPlaceTypeCode
where a.place IS NOT NULL 
and a.place <> ''
$where
GROUP BY 1,2,3,4,5,6
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('map_common_cities');