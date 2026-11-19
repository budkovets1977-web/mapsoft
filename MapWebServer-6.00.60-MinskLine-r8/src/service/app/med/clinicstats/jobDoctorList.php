<?php
 require_once (__DIR__ . '/../../init.php'); class med_clinicstats_jobDoctorList { private $db; public function readJobDoctorList($params = null) { $db = $this->db ? : $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : 0; $startDate = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? Date("Y-m-d", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Y-m-d", safe_strtotime('1900-01-01')); $finishDate = isset($params->finishDate) && isEmptyProperty($params, 'finishDate') ? Date("Y-m-d", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : Date("Y-m-d", safe_strtotime('2038-01-01')); $doctorId = isset($params->doctorId) && isEmptyProperty($params, 'doctorId') ? $params->doctorId : 0; $departmentId = isset($params->departmentId) && isEmptyProperty($params, 'departmentId') ? $params->departmentId : 0; $professionId = isset($params->professionId) && isEmptyProperty($params, 'professionId') ? $params->professionId : 0; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sort = isEmptyProperty($params, 'sort', 'doctorName'); $dir = isEmptyProperty($params, 'dir', ''); $sqlFromWhere = <<<"SQL"
            FROM MedJobDoctor AS MJD
                JOIN MedDoctor AS MD ON MD.MedDoctorCode = MJD.MedDoctorCode
                JOIN Department AS D ON D.DepartmentCode = MJD.DepartmentCode 
                    AND fn_WebUserAccessDepartment(D.DepartmentCode) = 1
                JOIN Branch AS B ON B.BranchCode = D.BranchCode AND B.EnterpriseCode = $currentEnterprise
                JOIN MedProfession AS MP ON MP.MedProfessionCode = MJD.MedProfessionCode
                LEFT OUTER JOIN MedJobVisitKind AS MJVK ON MJVK.MainFlag = 1
                LEFT OUTER JOIN MedJobPlan AS MJP ON MJP.MedJobVisitKindCode = MJVK.MedJobVisitKindCode AND MJP.MedProfessionCode = MJD.MedProfessionCode
                LEFT OUTER JOIN MedJobDoctorVisit AS MJDV ON MJDV.MedJobDoctorCode = MJD.MedJobDoctorCode AND MJDV.MedJobVisitKindCode is not NULL
            WHERE MJD.ChangeDate >= ('$startDate'::date ) AND MJD.ChangeDate < '$finishDate'::date
                AND (0 = $doctorId or $doctorId = MJD.MedDoctorCode)
                AND (0 = $professionId or $professionId = MJD.MedProfessionCode)
                AND (0 = $departmentId or $departmentId = MJD.DepartmentCode)
                AND (0 = $id or $id = MJD.MedJobDoctorCode)
        SQL;
$sql = <<<"SQL"
            SELECT MJD.MedJobDoctorCode "id"
            $sqlFromWhere
            GROUP BY MJD.MedJobDoctorCode
        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $total = count($stmt->fetchAll()); $offset = $total < $offset ? 0 : $offset; $sql = <<<"SQL"
            SELECT
                MJD.MedJobDoctorCode "id",
                min(MJD.ChangeDate) "changeDate",
                min(MP.Name) "professionName",
                (min(coalesce(B.Name, E'' )) + E' - ' + min(coalesce(D.Name, E'' ))) "departmentName",
                fn_cmFIO(min(MD.ManCode)) "doctorName",
                min(MD.MedDoctorCode) "doctorId",
                min(MJD.PlanTime) "planTime",
                min(MJD.FactTime) "factTime",
                min(MJD.PlanOtherTime) "planOtherTime",
                min(MJD.FactOtherTime) "factOtherTime",
                min(MJD.HourOnRatePlan) "planRateHour",
                min(MJD.HourOnRateFact) "factRateHour",
                MJD.VisitCount "visitCount",
                MJD.ActiveVisitCount "activeVisitCount",
                min(coalesce(MJD.Intensity, MJDV.Intensity)) "intensity",
                sum(coalesce(MJDV.PlanVisit, 0.0)) "planVisit",
                sum(coalesce(MJDV.FactVisit, 0.0)) "factVisit",
                min(MJP.VisitNumberOnHour) "norm",
                min(MP.HourOnRate) "hourOnRate",
                count(MJDV.MedJobDoctorVisitCode) = 0 "phantom"
            $sqlFromWhere
            GROUP BY MJD.MedJobDoctorCode
            ORDER BY "$sort" $dir
            LIMIT $limit OFFSET $offset
        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function readJobDoctorCalcList($params = null) { $db = $this->db ? : $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId') ? $params->doctorId : 0; $departmentId = isEmptyProperty($params, 'departmentId') ? $params->departmentId : 0; $professionId = isEmptyProperty($params, 'professionId') ? $params->professionId : 0; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
    MJD.medJobDoctorCode "id"
from MedJobDoctor MJD
    join MedDoctor MD on MD.medDoctorCode = MJD.medDoctorCode
    join Department D on D.departmentCode = MJD.departmentCode
        and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
    join Branch AS B on B.branchCode = D.branchCode
        and B.enterpriseCode = $currentEnterprise
    join MedProfession MP on MP.medProfessionCode = MJD.medProfessionCode
    left join MedJobDoctorVisit MJDV on MJDV.medJobDoctorCode = MJD.medJobDoctorCode
        and MJDV.medJobVisitKindCode is not null
where MJD.changeDate >= ? and MJD.changeDate < ?
    and (0 = $doctorId or $doctorId = MJD.medDoctorCode)
    and (0 = $professionId or $professionId = MJD.medProfessionCode)
    and (0 = $departmentId or $departmentId = MJD.departmentCode)
group by MJD.medJobDoctorCode
EOT;
return selectSql($db, $sql, [ dbDate(isEmptyProperty($params, 'startDate', '1900-01-01')), dbDate(isEmptyProperty($params, 'finishDate', '2035-01-01')) ]); } public function calcCreateJobDoctor($params = null) { $db = $this->db ? : $this->db = connectDb(); $startDate = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? Date("Y-m-d", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Y-m-d", safe_strtotime('1900-01-01')); $finishDate = isset($params->finishDate) && isEmptyProperty($params, 'finishDate') ? Date("Y-m-d", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : Date("Y-m-d", safe_strtotime('2035-01-01')); $doctorId = isset($params->doctorId) && isEmptyProperty($params, 'doctorId') ? $params->doctorId : 0; $departmentId = isset($params->departmentId) && isEmptyProperty($params, 'departmentId') ? $params->departmentId : 0; $professionId = isset($params->professionId) && isEmptyProperty($params, 'professionId') ? $params->professionId : 0; $startDate = date('Y-m-d', safe_strtotime('first day of this month', safe_strtotime($startDate))); $finishDate = date('Y-m-d', safe_strtotime('first day of +1 month', safe_strtotime($finishDate))); $db->beginTransaction(); try { $sql = <<<"SQL"
                INSERT INTO MedJobDoctor (
                    ChangeDate, MedDoctorCode, DepartmentCode, MedProfessionCode, PlanTime, FactTime)
            SELECT 
                DISTINCT YMD(YEAR(MV.VisitDate), MONTH(MV.VisitDate), 1),
                MV.MedDoctorCode,
                MV.DepartmentCode,
                MV.MedProfessionCode,
                MP.HourOnRate,
                MP.HourOnRate
            FROM MedVisit AS MV
                JOIN Department AS D ON D.DepartmentCode = MV.DepartmentCode
                JOIN Branch AS B ON B.BranchCode = D.BranchCode
                JOIN MedProfession AS MP ON MP.MedProfessionCode = MV.MedProfessionCode
            WHERE B.EnterpriseCode = 1
                AND MV.MedDoctorCode IS NOT NULL
                AND MV.DepartmentCode IS NOT NULL
                AND MV.MedProfessionCode IS NOT NULL
                AND (0 = $doctorId OR MV.MedDoctorCode = $doctorId)
                AND (0 = $departmentId OR MV.DepartmentCode = $departmentId)
                AND (0 = $professionId OR MV.MedProfessionCode = $professionId)
                AND MV.VisitDate < E'$finishDate'
                AND MV.VisitDate >= E'$startDate'
                AND NOT EXISTS (
                    SELECT MedJobDoctorCode
                    FROM MedJobDoctor
                    WHERE MedDoctorCode = MV.MedDoctorCode
                        AND DepartmentCode = MV.DepartmentCode
                        AND MedProfessionCode = MV.MedProfessionCode
                        AND ChangeDate = YMD(YEAR(MV.VisitDate), MONTH(MV.VisitDate), 1)
                )
                AND YMD(YEAR(MV.VisitDate), MONTH(MV.VisitDate), 1) NOT IN (
                    SELECT ChangeDate
                    FROM MedJobDoctor
                    WHERE MedDoctorCode = $doctorId
                        AND ChangeDate >= E'$startDate'
                );
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $idQuery = <<<"SQL"
    SELECT 
        MedJobDoctorCode "id",
        MedDoctorCode "doctorId",
        MedProfessionCode "professionId",
        ChangeDate "ChangeDate"
    FROM MedJobDoctor
    WHERE ChangeDate >= E'$startDate'
      AND ChangeDate < E'$finishDate'
      AND ($doctorId = 0 OR MedDoctorCode = $doctorId)
      AND ($departmentId = 0 OR DepartmentCode = $departmentId)
      AND ($professionId = 0 OR MedProfessionCode = $professionId)
SQL;
$idStmt = $db->query($idQuery); $newRecords = $idStmt->fetchAll(PDO::FETCH_ASSOC); foreach ($newRecords as $record) { $doctorInfoParams = new stdClass(); $doctorInfoParams->doctorId = $record['doctorId']; $doctorInfo = $this->getDoctorInfo($doctorInfoParams)[0]; $jobDoctorVisitParams = new stdClass(); $jobDoctorVisitParams->professionId = $record['professionId']; $jobDoctorVisit = $this->readJobDoctorVisit($jobDoctorVisitParams); $jobDoctorId = $record['id']; $updateJobDoctorList = []; for ($i = 0; $i < count($jobDoctorVisit); $i++) { $updateJobDoctorListRecord = new stdClass(); if (!$i) { $updateJobDoctorListRecord->planTime = $doctorInfo['rateHour']; $updateJobDoctorListRecord->factTime = $doctorInfo['rateHour']; } $updateJobDoctorListRecord->norm = $jobDoctorVisit[$i]['norm']; $updateJobDoctorListRecord->id = $jobDoctorVisit[$i]['id']; $updateJobDoctorListRecord->doctorJobId = $record['id']; if (!$i) { $updateJobDoctorListRecord->planVisit = $doctorInfo['rateHour'] * $jobDoctorVisit[$i]['norm']; } $updateJobDoctorList[] = $updateJobDoctorListRecord; } $checkJobVisit = <<<"SQL"
                        SELECT MedJobDoctorVisitCode "id"
                        from MedJobDoctorVisit
                        where MedJobDoctorCode = ?
            SQL;
$baseNorm = new stdClass(); $baseNorm->planTime = $doctorInfo['rateHour']; $baseNorm->factTime = $doctorInfo['rateHour']; $baseNorm->norm = $jobDoctorVisit[0]['norm']; $baseNorm->planVisit = $doctorInfo['rateHour'] * $jobDoctorVisit[0]['norm']; $baseNorm->factVisit = 0; $baseNorm->doctorJobId = $record['id']; $this->syncJobDoctorVisit($baseNorm); $stmt = $db->prepare($checkJobVisit); $stmt->execute([ isEmptyProperty($jobDoctorVisitParams, 'doctorJobId') ]); $id = fetchValue($stmt, 'id'); if (!$id) { $this->syncJobDoctorVisit($updateJobDoctorList); } } return true; } public function calcJobDoctor($params = null) { $db = $this->db ? : $this->db = connectDb(); $jobDoctors = isUndefinedProperty($params, 'jobDoctors'); $doctorId = isset($jobDoctors->doctorId) && isEmptyProperty($jobDoctors, 'doctorId') ? $params->doctorId : null; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $db->beginTransaction(); try { if (is_numeric($jobDoctors)) { $jobDoctorId = $jobDoctors; $sql = <<<"EOT"
DO \$anon$
BEGIN
	UPDATE medJobDoctor SET visitcount = NULL, activevisitcount = NULL WHERE medJobDoctorCode = $jobDoctorId;
UPDATE medJobDoctorVisit SET factvisit = NULL WHERE medJobDoctorCode = $jobDoctorId;
UPDATE medJobDoctor AS _t0_ SET
activevisitcount = Q.ActiveVisitCount,
visitcount = Q.VisitCount
FROM medJobDoctor AS JD ,
(SELECT
COUNT( CASE VP.VisitPlace = 1 WHEN true THEN VP.medVisitPatientCode WHEN false THEN NULL END ),
COUNT( VP.VisitPlace )
FROM medJobDoctor AS JD_ JOIN medVisit AS V ON V.DepartmentCode = JD_.DepartmentCode AND V.medProfessionCode = JD_.medProfessionCode AND V.MedDoctorCode = JD_.MedDoctorCode AND V.VisitDate >= (JD_.ChangeDate )AND V.VisitDate < DATEADD( 'MONTH', 1, JD_.ChangeDate )
JOIN medVisitPatient AS VP ON VP.medVisitCode = V.medVisitCode AND VP.VisitPlace IS NOT NULL AND COALESCE( VP.AttendantDiagnosis, 0 ) = 0
WHERE JD_.medJobDoctorCode = $jobDoctorId) AS Q (ActiveVisitCount, VisitCount)
 WHERE (_t0_.medjobdoctorcode=JD.medjobdoctorcode)
 AND (JD.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS _t0_ SET
factvisit = ROUND( Q.FactVisit, 0 )
FROM medJobDoctorVisit AS JDV JOIN (SELECT JVK.medJobVisitKindCode as medJobVisitKindCode,
SUM( CASE JVK.ChildFlag
WHEN 0 THEN 1
WHEN 1 THEN CASE JP.medJobPlanCode IS NOT NULL AND DATEDIFFYEAR( COALESCE( MP.DateOfBirth, 30000101 ), V.VisitDate ) BETWEEN 0 AND 14
WHEN true THEN JP.VisitNumberOnHour / JP.VisitNumberOnHourChild
WHEN false THEN 1.0 END END ) as FactVisit
FROM medJobVisitKind AS JVK JOIN medJobDoctor AS JD ON JD.medJobDoctorCode = $jobDoctorId
JOIN medDoctor AS Dr ON Dr.medDoctorCode = JD.medDoctorCode
LEFT OUTER JOIN medJobPlan AS JP ON  JP.medProfessionCode = Dr.medProfessionCode AND JP.medJobVisitKindCode = JVK.medJobVisitKindCode AND COALESCE( JP.VisitNumberOnHour, 0.0 ) <> (0.0 )AND COALESCE( JP.VisitNumberOnHourChild, 0.0 ) <> (0.0 )AND JP.VisitNumberOnHour <> (JP.VisitNumberOnHourChild )JOIN medVisit AS V ON  V.DepartmentCode = JD.DepartmentCode AND V.medProfessionCode = JD.medProfessionCode AND V.MedDoctorCode = JD.MedDoctorCode AND V.VisitDate >= (JD.ChangeDate )AND V.VisitDate < DATEADD( 'MONTH', 1, JD.ChangeDate )
JOIN medVisitPatient AS VP ON  VP.medVisitCode = V.medVisitCode AND COALESCE( VP.AttendantDiagnosis, 0 ) = 0
AND CHARINDEX( FN_cmString( VP.VisitPlace, 4 ), COALESCE( NULLIF( JVK.VisitPlace,  E'' ),  E'0' )) > 0
AND CHARINDEX( FN_cmString( VP.VisitMark, 4 ), COALESCE( NULLIF( JVK.VisitMark,  E'' ),  E'0' )) > 0
LEFT OUTER JOIN medPatient AS P ON P.medPatientCode = VP.medPatientCode
LEFT OUTER JOIN ManPassport AS MP ON MP.ManCode = P.ManCode
GROUP BY 1) AS Q ON Q.medJobVisitKindCode = JDV.medJobVisitKindCode
 WHERE (_t0_.medjobdoctorvisitcode=JDV.medjobdoctorvisitcode)
 AND (JDV.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS _t0_ SET
factvisit = COALESCE( JDV.FactVisit, 0 ) + Q.FactVisit
FROM medJobVisitKind AS JVK , medJobDoctorVisit AS JDV ,
(SELECT
SUM( COALESCE( O.VisitCond, 1.0 ) * COALESCE( NULLIF( AO.OperationCount, 0 ), 1 ) ) as FactVisit
FROM medJobDoctor AS JD JOIN medDoctor AS Dr ON Dr.medDoctorCode = JD.medDoctorCode
JOIN medAmbulOperation AS AO ON AO.EnterpriseCode = $currentEnterprise AND AO.OperationDate >= (JD.ChangeDate )AND AO.OperationDate < DATEADD( 'MONTH', 1, JD.ChangeDate ) AND AO.medDoctorCode = JD.medDoctorCode AND AO.medProfessionCode = JD.medProfessionCode
JOIN medOperation AS O ON O.medOperationCode = AO.medOperationCode AND O.OperationType = 1 AND O.medOperationParentCode IS NOT NULL
WHERE JD.medJobDoctorCode = $jobDoctorId) AS Q  WHERE (_t0_.medjobdoctorvisitcode=JDV.medjobdoctorvisitcode)
 AND (JVK.MainFlag = 1
AND JDV.medJobVisitKindCode = JVK.medJobVisitKindCode
AND Q.FactVisit IS NOT NULL
AND JDV.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS V SET factvisit = ROUND( (SELECT SUM( JDV.FactVisit / JDV.Norm * COALESCE( NULLIF( M.Norm, 0.0 ), 1.0 ) ) FROM medJobDoctorVisit AS M , medJobDoctorVisit AS JDV WHERE M.medJobDoctorVisitCode = V.medJobDoctorVisitCode AND JDV.medJobDoctorCode = M.medJobDoctorCode AND JDV.medJobVisitKindCode IS NOT NULL AND COALESCE( JDV.FactVisit, 0.0 ) <> (0.0 )AND COALESCE( JDV.Norm, 0.0 ) <> (0.0)), 0 ) WHERE V.medJobDoctorCode = $jobDoctorId AND V.medJobVisitKindCode IS NULL;
END \$anon$;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } else { foreach ($jobDoctors as $jobDoctor) { $doctorInfoParams = new stdClass(); $doctorInfoParams->doctorId = $jobDoctor->doctorId; $doctorInfo = $this->getDoctorInfo($doctorInfoParams)[0]; $jobDoctorVisitParams = new stdClass(); $jobDoctorVisitParams->doctorJobId = $jobDoctor->id; $jobDoctorVisitParams->professionId = $doctorInfo["professionId"]; $jobDoctorVisit = $this->readJobDoctorVisit($jobDoctorVisitParams); $jobDoctorId = $jobDoctor->id; $jobDoctorVisit[0]["planTime"] = $doctorInfo["rateHour"]; $updateJobDoctorList = []; for ($i = 0; $i < count($jobDoctorVisit); $i++) { $updateJobDoctorListRecord = new stdClass(); if (!$i) { $updateJobDoctorListRecord->planTime = $doctorInfo["rateHour"]; $updateJobDoctorListRecord->factTime = $doctorInfo["rateHour"]; } $updateJobDoctorListRecord->id = $jobDoctorVisit[$i]["id"]; $updateJobDoctorListRecord->doctorJobId = $jobDoctor->id; if (!$i) { $updateJobDoctorListRecord->planVisit = $doctorInfo["rateHour"] * $jobDoctorVisit[$i]["norm"]; } $updateJobDoctorList[] = $updateJobDoctorListRecord; } $checkJobVisit = <<<"SQL"
                        SELECT MedJobDoctorVisitCode "id"
                        from MedJobDoctorVisit
                        where MedJobDoctorCode = ?
SQL;
$stmt = $db->prepare($checkJobVisit); $stmt->execute([ isEmptyProperty($jobDoctorVisitParams, 'doctorJobId') ]); $id = fetchValue($stmt, 'id'); if (!$id) { $this->syncJobDoctorVisit($updateJobDoctorList); } $sql = <<<"EOT"
DO \$anon$
BEGIN
	UPDATE medJobDoctor SET visitcount = NULL, activevisitcount = NULL WHERE medJobDoctorCode = $jobDoctorId;
UPDATE medJobDoctorVisit SET factvisit = NULL WHERE medJobDoctorCode = $jobDoctorId;
UPDATE medJobDoctor AS _t0_ SET
activevisitcount = Q.ActiveVisitCount,
visitcount = Q.VisitCount
FROM medJobDoctor AS JD ,
(SELECT
COUNT( CASE VP.VisitPlace = 1 WHEN true THEN VP.medVisitPatientCode WHEN false THEN NULL END ),
COUNT( VP.VisitPlace )
FROM medJobDoctor AS JD_ JOIN medVisit AS V ON V.DepartmentCode = JD_.DepartmentCode AND V.medProfessionCode = JD_.medProfessionCode AND V.MedDoctorCode = JD_.MedDoctorCode AND V.VisitDate >= (JD_.ChangeDate )AND V.VisitDate < DATEADD( 'MONTH', 1, JD_.ChangeDate )
JOIN medVisitPatient AS VP ON VP.medVisitCode = V.medVisitCode AND VP.VisitPlace IS NOT NULL AND COALESCE( VP.AttendantDiagnosis, 0 ) = 0
WHERE JD_.medJobDoctorCode = $jobDoctorId) AS Q (ActiveVisitCount, VisitCount)
 WHERE (_t0_.medjobdoctorcode=JD.medjobdoctorcode)
 AND (JD.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS _t0_ SET
factvisit = ROUND( Q.FactVisit, 0 )
FROM medJobDoctorVisit AS JDV JOIN (SELECT JVK.medJobVisitKindCode as medJobVisitKindCode,
SUM( CASE JVK.ChildFlag
WHEN 0 THEN 1
WHEN 1 THEN CASE JP.medJobPlanCode IS NOT NULL AND DATEDIFFYEAR( COALESCE( MP.DateOfBirth, 30000101 ), V.VisitDate ) BETWEEN 0 AND 14
WHEN true THEN JP.VisitNumberOnHour / JP.VisitNumberOnHourChild
WHEN false THEN 1.0 END END ) as FactVisit
FROM medJobVisitKind AS JVK JOIN medJobDoctor AS JD ON JD.medJobDoctorCode = $jobDoctorId
JOIN medDoctor AS Dr ON Dr.medDoctorCode = JD.medDoctorCode
LEFT OUTER JOIN medJobPlan AS JP ON  JP.medProfessionCode = Dr.medProfessionCode AND JP.medJobVisitKindCode = JVK.medJobVisitKindCode AND COALESCE( JP.VisitNumberOnHour, 0.0 ) <> (0.0 )AND COALESCE( JP.VisitNumberOnHourChild, 0.0 ) <> (0.0 )AND JP.VisitNumberOnHour <> (JP.VisitNumberOnHourChild )JOIN medVisit AS V ON  V.DepartmentCode = JD.DepartmentCode AND V.medProfessionCode = JD.medProfessionCode AND V.MedDoctorCode = JD.MedDoctorCode AND V.VisitDate >= (JD.ChangeDate )AND V.VisitDate < DATEADD( 'MONTH', 1, JD.ChangeDate )
JOIN medVisitPatient AS VP ON  VP.medVisitCode = V.medVisitCode AND COALESCE( VP.AttendantDiagnosis, 0 ) = 0
AND CHARINDEX( FN_cmString( VP.VisitPlace, 4 ), COALESCE( NULLIF( JVK.VisitPlace,  E'' ),  E'0' )) > 0
AND CHARINDEX( FN_cmString( VP.VisitMark, 4 ), COALESCE( NULLIF( JVK.VisitMark,  E'' ),  E'0' )) > 0
LEFT OUTER JOIN medPatient AS P ON P.medPatientCode = VP.medPatientCode
LEFT OUTER JOIN ManPassport AS MP ON MP.ManCode = P.ManCode
GROUP BY 1) AS Q ON Q.medJobVisitKindCode = JDV.medJobVisitKindCode
 WHERE (_t0_.medjobdoctorvisitcode=JDV.medjobdoctorvisitcode)
 AND (JDV.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS _t0_ SET
factvisit = COALESCE( JDV.FactVisit, 0 ) + Q.FactVisit
FROM medJobVisitKind AS JVK , medJobDoctorVisit AS JDV ,
(SELECT
SUM( COALESCE( O.VisitCond, 1.0 ) * COALESCE( NULLIF( AO.OperationCount, 0 ), 1 ) ) as FactVisit
FROM medJobDoctor AS JD JOIN medDoctor AS Dr ON Dr.medDoctorCode = JD.medDoctorCode
JOIN medAmbulOperation AS AO ON AO.EnterpriseCode = $currentEnterprise AND AO.OperationDate >= (JD.ChangeDate )AND AO.OperationDate < DATEADD( 'MONTH', 1, JD.ChangeDate ) AND AO.medDoctorCode = JD.medDoctorCode AND AO.medProfessionCode = JD.medProfessionCode
JOIN medOperation AS O ON O.medOperationCode = AO.medOperationCode AND O.OperationType = 1 AND O.medOperationParentCode IS NOT NULL
WHERE JD.medJobDoctorCode = $jobDoctorId) AS Q  WHERE (_t0_.medjobdoctorvisitcode=JDV.medjobdoctorvisitcode)
 AND (JVK.MainFlag = 1
AND JDV.medJobVisitKindCode = JVK.medJobVisitKindCode
AND Q.FactVisit IS NOT NULL
AND JDV.medJobDoctorCode = $jobDoctorId) ;
UPDATE medJobDoctorVisit AS V SET factvisit = ROUND( (SELECT SUM( JDV.FactVisit / JDV.Norm * COALESCE( NULLIF( M.Norm, 0.0 ), 1.0 ) ) FROM medJobDoctorVisit AS M , medJobDoctorVisit AS JDV WHERE M.medJobDoctorVisitCode = V.medJobDoctorVisitCode AND JDV.medJobDoctorCode = M.medJobDoctorCode AND JDV.medJobVisitKindCode IS NOT NULL AND COALESCE( JDV.FactVisit, 0.0 ) <> (0.0 )AND COALESCE( JDV.Norm, 0.0 ) <> (0.0)), 0 ) WHERE V.medJobDoctorCode = $jobDoctorId AND V.medJobVisitKindCode IS NULL;
END \$anon$;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } $db->commit(); } } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createJobDoctorList($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
insert into MedJobDoctor (ChangeDate, MedDoctorCode, MedProfessionCode, Intensity, PlanTime, FactTime, PlanOtherTime, FactOtherTime, DepartmentCode, VisitCount, ActiveVisitCount, HourOnRatePlan, HourOnRateFact)
    values ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'changeDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'professionId'), isUndefinedProperty($row, 'intensity'), isUndefinedProperty($row, 'planTime'), isUndefinedProperty($row, 'factTime'), isUndefinedProperty($row, 'planOtherTime'), isUndefinedProperty($row, 'factOtherTime'), isEmptyProperty($row, 'departmentId'), isUndefinedProperty($row, 'visitCount'), isUndefinedProperty($row, 'activeVisitCount'), isUndefinedProperty($row, 'planRateHour'), isUndefinedProperty($row, 'factRateHour') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedJobDoctor'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readJobDoctorList($rec); } public function updateJobDoctorList($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update MedJobDoctor set
    ChangeDate = case when ? = 1 then ? else ChangeDate end,
    MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
    MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end,
    Intensity = case when ? = 1 then ? else Intensity end,
    PlanTime = case when ? = 1 then ? else PlanTime end,
    FactTime = case when ? = 1 then ? else FactTime end,
    PlanOtherTime = case when ? = 1 then ? else PlanOtherTime end,
    FactOtherTime = case when ? = 1 then ? else FactOtherTime end,
    DepartmentCode = case when ? = 1 then ? else DepartmentCode end,
    VisitCount = case when ? = 1 then ? else VisitCount end,
    ActiveVisitCount = case when ? = 1 then ? else ActiveVisitCount end,
    HourOnRatePlan = case when ? = 1 then ? else HourOnRatePlan end,
    HourOnRateFact = case when ? = 1 then ? else HourOnRateFact end
where MedJobDoctorCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'changeDate'), isUndefinedProperty($row, 'changeDate'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'intensity'), isUndefinedProperty($row, 'intensity'), propertyExistsSQL($row, 'planTime'), isUndefinedProperty($row, 'planTime'), propertyExistsSQL($row, 'factTime'), isUndefinedProperty($row, 'factTime'), propertyExistsSQL($row, 'planOtherTime'), isUndefinedProperty($row, 'planOtherTime'), propertyExistsSQL($row, 'factOtherTime'), isUndefinedProperty($row, 'factOtherTime'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'visitCount'), isUndefinedProperty($row, 'visitCount'), propertyExistsSQL($row, 'activeVisitCount'), isUndefinedProperty($row, 'activeVisitCount'), propertyExistsSQL($row, 'planRateHour'), isUndefinedProperty($row, 'planRateHour'), propertyExistsSQL($row, 'factRateHour'), isUndefinedProperty($row, 'factRateHour'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteJobDoctorList($records) { $db = $this->db ? : $this->db = connectDb(); return deleteSql($db, 'MedJobDoctor', 'MedJobDoctorCode', $records); } public function getDoctorInfo($params = null) { $db = $this->db ? : $this->db = connectDb(); $doctorId = isset($params->doctorId) && isEmptyProperty($params, 'doctorId') ? $params->doctorId : null; $currentEnterprise = getSessionValue('mapEnterpriseCode'); if (!$params || !$doctorId) { return null; } $sql = <<<"EOT"
SELECT
  fn_cmFIO(MD.ManCode) "doctorFio",
  D.Name "departmentName",
  D.DepartmentCode "departmentId",
  MP.Name "spetialityName",
  MP.MedProfessionCode "professionId",
  MP.HourOnRate "rateHour"
FROM MedDoctor MD
JOIN Department AS D ON D.DepartmentCode = MD.DepartmentCode
    and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
JOIN Branch AS B ON B.BranchCode = D.BranchCode AND B.EnterpriseCode = $currentEnterprise
JOIN MedProfession AS MP ON MP.MedProfessionCode = MD.MedProfessionCode
WHERE MD.MedDoctorCode = $doctorId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readJobDoctorVisit($params = null) { $db = $this->db ? : $this->db = connectDb(); $doctorJobId = isset($params->doctorJobId) && isEmptyProperty($params, 'doctorJobId') && is_numeric($params->doctorJobId) ? $params->doctorJobId : 0; $professionId = isset($params->professionId) && isEmptyProperty($params, 'professionId') ? $params->professionId : 0; $sql = <<<"EOT"
    Select
        VK.MedJobVisitKindCode "id",
        VK.Name "name",
        COALESCE(VK.MainFlag,0)  "mainFlag",
        VK.Position "position",
        DV.MedJobDoctorVisitCode "visitKindId",
        DV.MedJobDoctorCode "doctorJobId",
        DV.PlanTime "planTime",
        DV.FactTime "factTime",
        DV.PlanVisit "planVisit",
        DV.FactVisit "factVisit",
        DV.Intensity "intensity",
        0.0 "planFvd",
        0.0 "factFvd",
        0.0 "fn",
        COALESCE(DV.Norm,JP.VisitNumberOnHour,JP.VisitNumberOnHourChild) "norm"
        From MedJobVisitKind AS VK
        LEFT OUTER JOIN MedJobDoctorVisit AS DV ON DV.MedJobVisitKindCode = VK.MedJobVisitKindCode AND DV.MedJobDoctorCode = $doctorJobId
        LEFT OUTER JOIN MedJobPlan AS JP ON JP.MedProfessionCode = $professionId AND JP.MedJobVisitKindCode = VK.MedJobVisitKindCode
        Order by 3 DESC, 4 ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function syncJobDoctorVisit($records) { $db = $this->db ? : $this->db = connectDb(); $rec = new stdClass; if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $kindCode = intval(isEmptyProperty($row, 'id')) === 0 ? null : isEmptyProperty($row, 'id'); $sql = <<<"EOT"
select MedJobDoctorVisitCode "id"
    from MedJobDoctorVisit
where MedJobDoctorCode = ? and MedJobVisitKindCode is not distinct from ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($row, 'doctorJobId'), $kindCode ]); $id = fetchValue($stmt, 'id'); $sql = <<<"EOT"
insert into MedJobDoctorVisit (MedJobDoctorVisitCode,PlanTime, FactTime, Norm, PlanVisit, FactVisit, MedJobVisitKindCode, MedJobDoctorCode)
    values (?, ?, ?, ?, ?, ?, ?, ? )
on conflict (MedJobDoctorVisitCode) do update set
    PlanTime = case when ? = 1 then EXCLUDED.PlanTime else MedJobDoctorVisit.PlanTime end,
    FactTime = case when ? = 1 then EXCLUDED.FactTime else MedJobDoctorVisit.FactTime end,
    Norm = case when ? = 1 then EXCLUDED.Norm else MedJobDoctorVisit.Norm end,
    PlanVisit = case when ? = 1 then EXCLUDED.PlanVisit else MedJobDoctorVisit.PlanVisit end,
    FactVisit = case when ? = 1 then EXCLUDED.FactVisit else MedJobDoctorVisit.FactVisit end
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $id ? $id : null, isUndefinedProperty($row, 'planTime'), isUndefinedProperty($row, 'factTime'), isUndefinedProperty($row, 'norm'), isUndefinedProperty($row, 'planVisit'), isUndefinedProperty($row, 'factVisit'), $kindCode, isEmptyProperty($row, 'doctorJobId'), propertyExistsSQL($row, 'planTime'), propertyExistsSQL($row, 'factTime'), propertyExistsSQL($row, 'norm'), propertyExistsSQL($row, 'planVisit'), propertyExistsSQL($row, 'factVisit'), ]); $rec->id = isEmptyProperty($row, 'doctorJobId'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readJobDoctorList($rec); } public function deleteJobDoctorVisit($records) { $db = $this->db ? : $this->db = connectDb(); return deleteSql($db, 'MedJobDoctorVisit', 'MedJobDoctorVisitCode', $records); } } $service = new Junior\Server(new med_clinicstats_jobdoctorlist()); $service->process(); 