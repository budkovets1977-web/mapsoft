<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_teams { private $db; public function readTeams($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isEmptyProperty($params, "search", ""); $officesId = isEmptyProperty($params,'officesId'); $active = isEmptyProperty($params,'active'); $teamNumber = isEmptyProperty($params, 'teamNumber'); $where = ''; $sqlParams = []; if ($officesId) { $where = 'AND T.enterprisestructurecode = :officesId '; $sqlParams[':officesId'] = (int) $officesId; } if ($active) { $where .= ' AND T.active = 1'; } if ($search) { $search .= '%'; $where .= ' AND (T.teamNumber ilike :search)'; $sqlParams[':search'] = $search; } if ($teamNumber) { $where .= ' AND T.teamNumber ilike :teamNumber '; $sqlParams[':teamNumber'] = $teamNumber; } $sql = <<<"EOT"
select
    T.ambTeamCode             "id",
    T.active                  "active",
    T.type                    "typeId",
    T.teamNumber              "teamNumber",
    T.enterprisestructurecode "officesCode",   
    ESI.name                  "officesName",     
        STRING_AGG (
  to_char(S.startTime, 'HH24:MI') || '-' ||  to_char(S.finishTime, 'HH24:MI'),
        '; '
    )                         "shifts"
from AmbTeam T
    left join AmbShift S on T.ambteamcode = S.ambteamcode
    LEFT JOIN EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = T.enterprisestructurecode
where (fn_WebUserAccessOffice(T.enterprisestructurecode) = 1) $where
group by T.ambteamcode,ESI.name
order by T.teamNumber
EOT;
return selectSql($db, $sql, $sqlParams); } public function createTeams ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AmbTeam
    (enterprisestructurecode, Active, TeamNumber, Type)
values
    (?, ?, ?, ?)
returning AmbTeamCode
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $teamNumber = isUndefinedProperty($row, 'teamNumber'); if ($this->teamNumberExists($teamNumber)) { throw new Exception("Бригада с номером \"$teamNumber\" уже существует."); } $ret[] = $rec = new stdClass; $rec->id = selectSqlValue($db, $sql, [ isUndefinedProperty($row, 'officesCode'), isUndefinedProperty($row,'active'), $teamNumber, isUndefinedProperty($row, 'typeId') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function teamNumberExists ($teamNumber) { $params = new stdClass(); $params->teamNumber = $teamNumber; return !!count($this->readTeams($params)); } public function updateTeams ($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } return updateSql($db, 'AmbTeam', 'AmbTeamCode', $records, "enterprisestructurecode, Active, TeamNumber, Type", "officesCode, active, teamNumber, typeId" ); } public function deleteTeams ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbTeam', 'AmbTeamCode', $records); } public function readTeamShifts ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ''; $teamId = isEmptyProperty($params, 'teamId'); if (!empty($teamId)) { if(is_array($teamId)) { $len = count($teamId); $teamId = createSqlInCondition($teamId); $where .= $where ? " and S.ambTeamCode {$teamId}" : "where S.ambTeamCode {$teamId}"; $sql = <<<"EOT"
                select
                    max(S.ambShiftCode) "id",             
					S.StartTime "startTime",
                    S.FinishTime "finishTime"
                from AmbShift S
                $where
                group BY 2,3
                having count(*) = $len
EOT;
return selectSql($db, $sql, $sqlParams); } else { $teamId = (int) $teamId; $where .= $where ? ' and S.ambTeamCode = ?' : 'where S.ambTeamCode = ?'; array_push($sqlParams, $teamId); } } $sql = <<<"EOT"
select
    S.ambShiftCode "id",
    S.ambTeamCode "teamId",
    S.Name "name",
    S.StartTime "startTime",
    S.FinishTime "finishTime"
from AmbShift S
$where
EOT;
return selectSql($db, $sql, $sqlParams); } public function createTeamShifts ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'AmbShift', $records, "ambTeamCode, Name, StartTime, FinishTime", "teamId, name, startTime, finishTime" ); } public function updateTeamShifts ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'AmbShift', 'ambShiftCode', $records, "Name, StartTime, FinishTime", "name, startTime, finishTime" ); } public function deleteTeamShifts ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbShift', 'ambShiftCode', $records); } public function readTeamRoles ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ''; $teamId = (int) isEmptyProperty($params, 'teamId'); $doctorId = (int) isEmptyProperty($params, 'doctorId'); if ($teamId) { $where .= $where ? ' and TR.ambTeamCode = ?' : 'where TR.ambTeamCode = ?'; array_push($sqlParams, $teamId); } if ($doctorId) { $where .= $where ? ' and D.medDoctorCode = ?' : 'where D.medDoctorCode = ?'; array_push($sqlParams, $doctorId); } $sql = <<<"EOT"
select
    TR.AmbTeamRoleCode "id",
    TR.ambTeamCode "teamId",
    TR.AmbRoleCode "roleId",
    R.name "roleName",
    R.active "roleActive"
from AmbTeamRole TR
    left join AmbRole R on TR.AmbRoleCode = R.AmbRoleCode
$where
order by R.position, TR.AmbTeamRoleCode
EOT;
if (isEmptyProperty($params, 'groupRole')) { $sql = <<<"EOT"
select
    TR.AmbRoleCode "roleId",
    R.name "roleName",
    R.shortName "roleShortName",
    R.active "roleActive",
    min(R.position) rposition
from AmbTeamRole TR
    left join AmbRole R on TR.AmbRoleCode = R.AmbRoleCode
    left join AmbRoleProfession RP on R.AmbRoleCode = RP.AmbRoleCode
    join MedDoctor D on RP.MedProfessionCode = D.MedProfessionCode
$where
group by TR.AmbRoleCode, R.name, R.shortName, R.active
order by R.active DESC, rposition

EOT;
} return selectSql($db, $sql, $sqlParams); } public function createTeamRoles ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'AmbTeamRole', $records, "ambTeamCode, AmbRoleCode", "teamId, roleId" ); } public function updateTeamRoles($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'AmbTeamRole', 'AmbTeamRoleCode', $records, "AmbRoleCode", "roleId" ); } public function deleteTeamRoles ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbTeamRole', 'AmbTeamRoleCode', $records); } } handleJsonRpc('med_ambulance_teams'); 