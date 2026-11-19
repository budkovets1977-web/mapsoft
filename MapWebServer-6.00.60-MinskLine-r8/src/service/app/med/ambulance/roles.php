<?php
require_once(__DIR__ . '/../../init.php'); class med_ambulance_roles { private $db; public function readRoles($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                r.ambRoleCode       "id",
                r.active            "active",
                r.name              "name",
                r.shortName         "shortName",
                r.position          "position",
                res.professions
            FROM ambRole r
            LEFT JOIN ambRoleProfession rp ON r.ambRoleCode = rp.ambRoleCode
            LEFT JOIN (
                SELECT
                    foo.code,
                    STRING_AGG (
                        foo.name,
                        '; <br>'
                    ) "professions"
                FROM (
                    SELECT
                        rp.ambRoleCode code,
                        mp.name
                    FROM AmbRoleProfession RP
                    LEFT JOIN medProfession mp ON rp.medProfessionCode = mp.medProfessionCode
                    ORDER BY mp.name
                ) foo
                GROUP BY foo.code
            ) res ON r.ambRoleCode = res.code
            GROUP BY r.ambRolecode, res.professions
            ORDER BY r.position
EOT;
return selectSql($db, $sql); } public function getMaxPosition() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
select
    max(R.position) "position"
from AmbRole R
EOT;
return selectSqlValue($db, $sql); } public function createRoles($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'AmbRole', $records, "Active, Name, ShortName, Position", "active, name, shortName, position", "?, ?, ?, ?" ); } public function updateRoles($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } foreach ($records as $row) { if (isUndefinedProperty($row, 'position') || (property_exists($row, 'active') && $row->active === null)) { executeSql($db, 'update ambRole set Position = null where ambRoleCode = ?', [$row->id]); } else if (property_exists($row, 'active') && $row->active === true) { $roles = new med_ambulance_roles(); $newPosition = $roles->getMaxPosition() + 1; $sql = <<<EOT
update ambRole set Position = $newPosition where ambRoleCode = ?
EOT;
executeSql($db, $sql, [$row->id]); } } return updateSql($db, 'AmbRole', 'AmbRoleCode', $records, "Active, Name, ShortName, Position", "active, name, shortName, position", ); } public function deleteRoles($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbRole', 'AmbRoleCode', $records); } public function readRoleProfessions($params) { $db = $this->db ?: $this->db = connectDb(); $roleId = (int) isEmptyProperty($params, 'roleId'); $sql = <<<EOT
select
    RP.ambRoleProfessionCode "id",
    RP.ambRoleCode "roleId",
    RP.medprofessioncode "professionId"
from AmbRoleProfession RP
    where RP.ambRoleCode = ?
EOT;
return selectSql($db, $sql, [$roleId]); } public function createRoleProfessions($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'AmbRoleProfession', $records, "ambRoleCode, medprofessioncode", "roleId, professionId" ); } public function updateRoleProfessions($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'AmbRoleProfession', 'AmbRoleProfessionCode', $records, "ambRoleCode, medprofessioncode", "roleId, professionId" ); } public function deleteRoleProfessions($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbRoleProfession', 'AmbRoleProfessionCode', $records); } } handleJsonRpc('med_ambulance_roles');