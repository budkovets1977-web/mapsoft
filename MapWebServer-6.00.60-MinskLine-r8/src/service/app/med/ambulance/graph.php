<?php
require_once(__DIR__ . '/../../init.php'); class med_ambulance_graph { private $db; const GRAPH_EDIT_PERMISSION = 'med-ambulance-graph-edit'; const ERROR_NO_GRAPH_EDIT_PERMISSION = 'У Вас нет прав на редактирование графика. Обратитесь к Администратору.'; const UPDATE_ACTIVE_PERMISSION = 'med-ambulance-teamscontrol-brigade-activation'; const ERROR_UPDATE_ACTIVE_PERMISSION = 'У Вас нет прав на активирование бригад. Обратитесь к Администратору.'; public function readGraph($params = null) { if (isEmptyProperty($params, 'teamControl')) { return $this->readTeamControl($params); } $db = $this->db ?: $this->db = connectDb(); $teamId = ''; if (isEmptyProperty($params, 'teamId')) { $teamId = 'and T.ambTeamCode = ' . (string) $params->teamId; } $officesId = ''; if (isEmptyProperty($params,'officesId')) { $officesId = 'and T.enterprisestructurecode = ' . (int) $params->officesId; } $monthYear = dbDate($params->monthYear); $sqlRecords = <<<"EOT"
select
    DAY(G.onDate)::text || '_' || S.ambShiftCode cellId,
    G.ambGraphCode "graphId",
    G.onDate "date",
    G.startTime "shiftStartTime",
    G.finishTime "shiftFinishTime",
    G.ambTeamCode "teamId",
    DAY(G.onDate) "day",
    R.name "roleName",
    R.shortName "roleShortName",
    R.position "rposition",
    R.active "roleActive",
    T.EnterpriseStructureCode "enterpriseStructureId",
    S.ambShiftCode "shiftId",
    GP.ambRoleCode "roleId",
    GP.ambGraphPersonCode "id",
    GP.startTime "personStartTime",
    GP.finishTime "personFinishTime",
    GP.medDoctorCode "doctorId",
    D.MedProfessionCode "professionId",
    G.carnumber "carName",
    (M.surname
     || CASE WHEN M.name IS NOT NULL THEN ' ' || upper(substring(M.name,1,1)) || '.' ELSE ''  END
     || CASE WHEN M.secondname IS NOT NULL THEN ' ' || upper(substring(M.secondname,1,1)) || '.' ELSE '' END ) "surname",
    G.inWork "inWork",
    (CASE WHEN (GP.AmbGraphPersonCodePrev IS NOT NULL) 
            OR EXISTS(
                 SELECT 
                   1
                 FROM AmbGraphPerson
                 WHERE AmbGraphPerson.AmbGraphPersonCodePrev = GP.ambGraphPersonCode
                 LIMIT 1  
            ) THEN 1
            ELSE null
    END) "isOverrideGraph"
from AmbGraph G
join AmbGraphPerson GP on GP.ambGraphCode = G.ambGraphCode
join AmbRole R on R.ambRoleCode = GP.ambRoleCode
join AmbTeam T on T.ambTeamCode = G.ambTeamCode
join MedDoctor D on D.medDoctorCode = GP.medDoctorCode
join Man M on M.manCode = D.manCode
join AmbShift S on S.ambShiftCode = G.ambShiftCode
where (fn_WebUserAccessOffice(T.enterprisestructurecode) = 1)
    and G.onDate between dateFirstOfThisMonth(?) and dateLastOfThisMonth(?) $teamId $officesId
EOT;
$records = selectSql($db, $sqlRecords, [ $monthYear, $monthYear]); $sqlColumns = <<<"EOT"
select
    S.ambShiftCode "id",
    S.ambTeamCode "teamId",
    S.name "shiftName",
    S.StartTime "startTime",
    S.FinishTime "finishTime",
    T.TeamNumber "teamNumber",
    T.enterprisestructurecode "teamEnterpriseStructureCode",
    (select string_agg(TR.roles, '; ') rolCount
    from
        (select
            t.ambTeamCode,
            case when count(TR.ambTeamRoleCode) > 1
            then count(TR.ambTeamRoleCode)::text || ' '
            else '' end || R.name roles
        from ambTeam T
        left join AmbTeamRole TR on  T.ambTeamCode = TR.ambTeamCode
        left join AmbRole R on TR.ambRoleCode = R.ambRoleCode
        where R.active = 1
        group by T.ambTeamCode,
        R.ambRoleCode, R.shortName, r.position
        order by r.position) TR
    where TR.ambTeamCode = T.ambTeamCode),

    (select json_agg(RC) roles from (select
        TR.ambRoleCode "roleId",
        count(TR.ambTeamRoleCode) "roleCount"
    from AmbTeamRole TR
    where TR.ambTeamCode = T.ambTeamCode
    group by TR.ambRoleCode) RC),

    CASE T.type
        WHEN 1 THEN '(фельдшерская)'
        WHEN 2 THEN '(реанимационная)'
        WHEN 3 THEN '(интенсивной терапии)'
        WHEN 4 THEN '(педиатрическая)'
        WHEN 5 THEN '(психиатрическая)'
        WHEN 6 THEN '(общепрофильная)'
    END "type"

from AmbShift S
join AmbTeam T on T.ambTeamCode = S.AmbTeamCode
where (fn_WebUserAccessOffice(T.enterprisestructurecode) = 1) and T.active = 1 $teamId $officesId
order by S.ambTeamCode, S.name
EOT;
$columns = selectSql($db, $sqlColumns); foreach ($columns as &$column) { $column['roles'] = safe_json_decode($column['roles']); } $result = new stdClass(); $result->records = $records; $result->columns = $columns; return $result; } public function checkGraphEditAccess() { if (!testUserAccess(self::GRAPH_EDIT_PERMISSION)) { throw new Exception(self::ERROR_NO_GRAPH_EDIT_PERMISSION, ERROR_NOT_ALLOWED); } } public function saveChangesGraph($params) { $db = $this->db ?: $this->db = connectDb(); $this->checkGraphEditAccess(); $records = isEmptyProperty($params, 'data'); if (empty($records)) { return true; } if (is_object($records)) { $records = [$records]; } $delete = []; try { $db->beginTransaction(); foreach ($records as $row) { $crudState = isEmptyProperty($row, 'crudState'); if ($crudState === 'D' || $crudState === 'CD') { array_push($delete, $row); } else if ($crudState === 'C') { $graphId = $this->checkGraph($row); if (empty($graphId)) { $graphId = $this->createGraph($row); } $row->graphId = $graphId; $this->createGraphPerson($row); } } if (!empty($delete)) { $this->deleteGraphPerson($delete); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function createGraph($row) { $sql = <<<"EOT"
insert into AmbGraph (OnDate, AmbTeamCode, AmbShiftCode, Status, StartTime, FinishTime)
values (?, ?, ?, ?, ?, ?)
returning AmbGraphCode "id"
EOT;
return selectSqlValue($this->db, $sql, [ isEmptyProperty($row, 'date'), isEmptyProperty($row, 'teamId'), isEmptyProperty($row, 'shiftId'), 0, isEmptyProperty($row, 'shiftStartTime'), isEmptyProperty($row, 'shiftFinishTime') ]); } private function createGraphPerson($row) { $sql = <<<"EOT"
insert into AmbGraphPerson (AmbGraphCode, MedDoctorCode, AmbRoleCode, StartTime, FinishTime)
values (?, ?, ?, ?, ?)
EOT;
return executeSql($this->db, $sql, [ isEmptyProperty($row, 'graphId'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'roleId'), isEmptyProperty($row, 'shiftStartTime'), isEmptyProperty($row, 'shiftFinishTime') ]); } private function checkGraph($row) { $sql = <<<"EOT"
select
    G.ambGraphCode "id"
from AmbGraph G
    where G.ambShiftCode = ?
      and G.onDate = ?
limit 1
EOT;
return selectSqlValue($this->db, $sql, [ isEmptyProperty($row, 'shiftId'), isEmptyProperty($row, 'date') ]); } private function deleteGraphPerson($records) { return deleteSql($this->db, 'AmbGraphPerson', 'AmbGraphPersonCode', $records, 'id', false); } private function clearEmptyGraph($date) { $whereSql = ''; if (!empty($date)) { $first = date("Y-m-01", safe_strtotime($date)); $last = date("Y-m-t", safe_strtotime($date)); $whereSql = "and onDate between \"{$first}\" and \"{$last}\""; } $sql = <<<"EOT"
delete from AmbGraph
where AmbGraphCode IN (select AG.AmbGraphCode
                       from AmbGraph AG
                            left join AmbGraphPerson AGP on AGP.ambGraphCode = AG.ambGraphCode
                                                        and AGP.AmbGraphPersonCode is null)
  {$whereSql}
EOT;
executeSql($this->db, $sql); } protected function checkAccessUpdateActive () { if (!testUserAccess(self::UPDATE_ACTIVE_PERMISSION)) { throw new Exception(self::ERROR_UPDATE_ACTIVE_PERMISSION, ERROR_NOT_ALLOWED); } } public function changeActiveState($records) { $db = $this->db ?: $this->db = connectDb(); $status = isEmptyProperty($records, 'status'); !$status && $this->checkAccessUpdateActive(); $inWork = isEmptyProperty($records,'inWork',false); if (!$inWork && !$status) { $records->carName = null; } return updateSql($db, 'AmbGraph', 'ambGraphCode', $records, 'inWork, carnumber, status', 'inWork, carName, status' ); } public function readTeamControl($params) { $db = $this->db ?: $this->db = connectDb(); $dateStr = 'now'; $dateTimeStr = 'now()'; $sqlParams = []; $status = isEmptyProperty($params, 'status'); $statusSql = empty($status) ? "" : "and AG.status = {$status}"; $team = isEmptyProperty($params, 'team'); $teamSql = empty($team) ? "" : "and AG.ambTeamCode = {$team}"; $teamType = isEmptyProperty($params, 'teamType'); if (empty($teamType)) { $teamTypeSql = ""; } else if (is_array($teamType)) { $teamTypeSql = ""; foreach ($teamType as $key => $value) { if ($key == 0) { $teamTypeSql .= "and (AT.type = {$value}"; } else { $teamTypeSql .= " or AT.type = {$value}"; } } $teamTypeSql .= ")"; } else { $teamTypeSql = "and AT.type = {$teamType}"; } $inWork = isEmptyProperty($params, 'inWork'); $inWorkSql = empty($inWork) ? "" : "and AG.inWork = {$inWork}"; $teamId = isEmptyProperty($params, 'teamId'); $teamIdSql = empty($teamId) ? "" : "and AT.ambTeamCode = {$teamId}"; $teamNotId = isEmptyProperty($params, 'teamNotId'); $teamNotIdSql = ''; if (!empty($teamNotId)) { $sqlParams[':teamNotId'] = $teamNotId; $teamNotIdSql = "AND AT.AmbTeamCode <> :teamNotId"; } $teamStatus = isEmptyProperty($params, 'teamStatus'); $teamStatusSql = empty($teamStatus) ? "" : "and AG.status = {$teamStatus}"; $offices = isEmptyProperty($params,'offices'); $officesSql = empty($offices) ? "" : "and AT.enterprisestructurecode = {$offices}"; $sql = <<< "EOT"
select
    AG.ambGraphCode     "id",
    AT.ambTeamCode      "teamId",
    AT.teamNumber       "teamNumber",
    AT.type             "teamType",
    AG.inWork           "inWork",
    AL.transferTime     "transferTime",
    AL.checkOutTime     "checkOutTime",
    AL.address          "address",
    AG.status           "status",
    AG.carnumber        "carName",
    AG.OnDate           "onDate",
    CASE
        WHEN AG.onDate < '{$dateStr}'::date THEN 1 ELSE 0
    END "prevShift",
    to_char(AmbS.startTime, 'HH24:MI') || '-' ||  to_char(AmbS.finishTime, 'HH24:MI')
                        "shift",
    CASE
        WHEN AT.type = 1 THEN 'фельдшерская'
        WHEN AT.type = 2 THEN 'реанимационная'
        WHEN AT.type = 3 THEN 'интенсивной терапии'
        WHEN AT.type = 4 THEN 'педиатрическая'
        WHEN AT.type = 5 THEN 'психиатрическая' ELSE 'общепрофильная врачебная'
    END                 "type",
    CASE WHEN AG.status = 1 THEN 'на станции'
         WHEN AG.status = 2 THEN 'на выезде'
         WHEN AG.status = 3 THEN 'возвращается на станцию'
         WHEN AG.status = 4 THEN 'на обеде'
         WHEN AG.status = 5 THEN 'ремонт машины'
    END                 "statusDisplay",
    AmbS.startTime      "shiftStartTime",
    AmbS.finishTime     "shiftFinishTime",
    AmbS.Name           "shiftName"        
from AmbGraph AG
join AmbTeam AT on AG.ambTeamCode = AT.ambTeamCode and (fn_WebUserAccessOffice(AT.enterprisestructurecode) = 1)
join AmbShift AmbS on AG.ambShiftCode = AmbS.ambShiftCode
left join AmbLog AL on AL.ambLogCode = AG.ambLogCode and AL.returnTime is null and AL.active is not null
left join ambLog ALReturn on ALReturn.ambLogCode = (SELECT ambLogCode FROM ambLog
                                                WHERE ambgraphcode = AG.ambgraphcode AND returnTime IS NOT NULL
                                                ORDER BY returnTime DESC
                                                LIMIT 1)
where (AG.onDate = '{$dateStr}'::date
      or (AG.onDate = ('{$dateStr}'::date - '1 day'::interval) and AmbS.finishTime < AmbS.startTime)) --and AG.inWork = 1)
and (
    (AmbS.finishTime > AmbS.startTime and (('{$dateStr}'::time > AmbS.startTime and '{$dateStr}'::time < AmbS.finishTime) or AG.inWork = 1)) --Текущее время в пределах непереходящей смены или бригада активна
    or (AmbS.finishTime < AmbS.startTime
        and (
            ('{$dateTimeStr}' > (AG.onDate::date + AmbS.startTime) and '{$dateTimeStr}' < ((AG.onDate::date + '1 day'::interval) + AmbS.finishTime))
            or AG.inWork = 1
        )
    ) --Текущее время в пределах переходящей смены или бригада активна
)
and (
    EXISTS (
        SELECT
            1
        FROM ambGraphPerson
        WHERE ambGraphCode = AG.ambGraphCode
        LIMIT 1
    )
)
{$statusSql}
{$teamSql}
{$inWorkSql}
{$teamTypeSql}
{$teamStatusSql}
{$teamIdSql}
{$officesSql}
$teamNotIdSql
order by AG.inWork desc, -- Сначала активные
    CASE WHEN AL.checkoutTime IS NULL THEN 0 ELSE 1 END ASC, -- Сначала те, что не на выезде
    ALReturn.returnTime asc NULLS first, -- Сначала те, что не возвращались с вызова в смене
    CASE
        WHEN AG.status = 1 THEN 0
        WHEN AG.status = 2 THEN 2   -- Сначала те, у кого статус "На станции", потом "Возвращается", потом "На выезде"
        WHEN AG.status = 3 THEN 1
    END asc, AG.OnDate
EOT;
return selectSql($db, $sql, $sqlParams); } } handleJsonRpc('med_ambulance_graph');