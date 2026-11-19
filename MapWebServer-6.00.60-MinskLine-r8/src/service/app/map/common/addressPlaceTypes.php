<?php
 require_once(__DIR__.'/../../init.php'); class map_common_addressPlaceTypes { private $db; public function readAddressPlaceTypes($params = null) { $db = $this->db ?: $this->db = connectDb(); return selectSql($db, 'select
    apt.AddressPlaceTypeCode "id",
    apt.Name "name",
    apt.ShortName "shortName",
    apt.LiveZone "liveZone",
    u.Value "liveZoneName",
    (Attributes & 1)::boolean  "isCisz"
from AddressPlaceType apt
left join UniDict u on u.UniDictCode = apt.LiveZone
    and u.DictNumber = 12
order by 2, 3'); } public function updateAddressPlaceTypes($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'AddressPlaceType', 'AddressPlaceTypeCode', $records, 'Name, ShortName, LiveZone', 'name, shortName, liveZone'); } public function deleteAddressPlaceTypes($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AddressPlaceType', 'AddressPlaceTypeCode', $records); } public function createAddressPlaceTypes($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'AddressPlaceType', $records, 'Name, ShortName, LiveZone', 'name, shortName, liveZone'); } } handleJsonRpc('map_common_addressPlaceTypes');