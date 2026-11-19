<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_graphPerson { private $db; public function readGraphPerson($params = null) { $db = $this->db ?: $this->db = connectDb(); $ids = isEmptyProperty($params, 'graphId', 0); $checkDateTime = isEmptyProperty($params, 'checkDateTime'); $checkDateTimeSql = ''; $checkDateTimeJoinSql = ''; $sqlParams = []; if ($checkDateTime) { $checkDateTime = dbDateTime($checkDateTime); $checkDateTimeJoinSql = ' LEFT JOIN AmbGraph AG on AG.AmbGraphCode = AGP.AmbGraphCode'; $checkDateTimeSql = <<<EOT
AND (CASE WHEN AGP.FinishTime::time < AGP.StartTime::time 
          THEN  (AG.OnDate + '1 day'::interval + AGP.FinishTime::time)::timestamp >= :date::timestamp
		  ELSE (AGP.FinishTime::time >= :date::time AND AGP.StartTime::time <= :date::time)
	      END)
EOT;
$sqlParams[':date'] = $checkDateTime; } $checkOverride = isEmptyProperty($params, 'checkOverride'); $checkOverrideSql = ''; $checkOverrideJoin = ''; if ($checkOverride) { $checkOverrideSql = ',
    (CASE WHEN (AGP.AmbGraphPersonCodePrev IS NOT NULL) 
            OR EXISTS(
                 SELECT 
                   1
                 FROM AmbGraphPerson
                 WHERE AmbGraphPerson.AmbGraphPersonCodePrev = AGP.AmbGraphPersonCode
                 LIMIT 1  
            ) THEN 1
            ELSE null
    END) "isOverrideGraph"'; $checkOverrideJoin = ' LEFT JOIN AmbGraph G on G.AmbGraphCode = AGP.AmbGraphCode'; } $sql = <<<"EOT"
select
    AGP.AmbGraphPersonCode "id",
    AGP.ambGraphCode       "graphId",
    AGP.ambRoleCode        "roleId",
    AR.name                "roleName",
    to_char(AGP.finishTime, '2008-01-01 HH24:MI:SS')
                           "finishTime",
    AGP.medDoctorCode      "doctorId",
    FN_cmFIO(MD.manCode)   "doctorFio",
    to_char(AGP.startTime, '2008-01-01 HH24:MI:SS')
                           "startTime",
    MP.Name                "profession"
    $checkOverrideSql
from AmbGraphPerson AGP
    left join AmbRole AR on AGP.ambRoleCode = AR.ambRoleCode
    left join MedDoctor MD on MD.medDoctorCode = AGP.medDoctorCode
    left join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
    $checkOverrideJoin
$checkDateTimeJoinSql
where AGP.ambGraphCode IN ({$ids})
$checkDateTimeSql
order by AR.position
EOT;
return selectSql($db, $sql, $sqlParams); } public function createGraphPerson($records) { $db = $this->db ?: $this->db = connectDb(); $this->checkGraphEditAccess(); return insertSql($db, 'AmbGraphPerson', $records, 'ambGraphCode, ambRoleCode, finishTime, medDoctorCode, startTime, ambGraphPersonCodePrev', 'graphId, roleId, finishTime, doctorId, startTime, ambGraphPersonCodePrev' ); } protected function checkGraphEditAccess() { suspendJsonRpc(); require_once (__DIR__ . '/graph.php'); resumeJsonRpc(); $graph = new med_ambulance_graph(); $graph->checkGraphEditAccess(); } public function updateGraphPerson($records) { $db = $this->db ?: $this->db = connectDb(); $this->checkGraphEditAccess(); return updateSql($db, 'AmbGraphPerson', 'ambGraphPersonCode', $records, 'ambRoleCode, finishTime, medDoctorCode, startTime', 'roleId, finishTime, doctorId, startTime' ); } public function deleteGraphPerson($records) { $db = $this->db ?: $this->db = connectDb(); $this->checkGraphEditAccess(); return deleteSql($db, 'AmbGraphPerson', 'ambGraphPersonCode', $records); } } handleJsonRpc('med_ambulance_graphPerson');