<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_workGraph { protected ?PDO_ANY $db = null; public function readWorkGraph(object $params) { $db = $this->db ?: $this->db = connectDb(); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 10; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $where = "mcg.OnDate >= '$params->year-$params->month-01' and mcg.OnDate < DATEADD('MONTH', 1, '$params->year-$params->month-01')"; if (!empty($params->specialty)) { $whereArray[] = "md.MedProfessionCode = " . $params->specialty; } if (!empty($params->department)) { $whereArray[] = "md.DepartmentCode = " . $params->department; } if (!empty($params->officeUnitId)) { $whereArray[] = " es.enterpriseStructureParentCode = ".$params->officeUnitId; } if (!empty($params->doctorId)) { $whereArray[] = "md.MedDoctorCode = " . $params->doctorId; } $searchText = isEmptyProperty($params, 'searchText') ? $params->searchText : ''; $whereDoc = isset($whereArray) ? "where " . implode(" and ", $whereArray) : ""; $whereDoc .= isset($whereArray) ? " and coalesce(md.FinishDate,30000101) >= '$params->year-$params->month-01'" : " where coalesce(md.FinishDate,30000101) >= '$params->year-$params->month-01'"; $where = $whereDoc ? $whereDoc . " and " . $where : "where " . $where; $sqlTotal = <<<"EOT"
select
       COUNT(*) "total"
from MedDoctor md
left outer join MedProfession mp on mp.MedProfessionCode = md.MedProfessionCode
left join Department d on d.DepartmentCode = md.DepartmentCode
    and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
left outer join Branch b on b.branchCode = d.branchCode
left outer join EnterpriseStructure es on es.departmentCodeWeb = d.DepartmentCode
$whereDoc
and (MP.Name like '%$searchText%' or  FN_cmFIO(md.ManCode) like '%$searchText%')
and b.enterpriseCode = $enterpriseCode
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $offset = $total < $offset ? 0 : $offset; $sqlDoc = <<<SQL
            SELECT
                MD.MedDoctorCode                "id",
                FN_cmFIO(MD.ManCode)            "name",
                MD.Cabinet                      "doctorCabinet",
                MD.BasicTimetable               "basicTimetable",
                MD.MedProfessionCode            "specialtyCode",
                MP.Name                         "specialty",
                D.Name                          "department",
                (
                    SELECT STRING_AGG((Name)::TEXT, ',' )
                    FROM MedPatientGroup
                    WHERE MedDoctorCode = MD.MedDoctorCode
                )                               "sectorName",
                DOUBLETOSTR(
                    COALESCE(
                        ROUND((
                            (
                                SELECT COUNT(C.MedCouponCode)
                                FROM MedCoupon C
                                WHERE C.MedDoctorCode = MD.MedDoctorCode
                                AND C.OnDate >= '$params->year-$params->month-01'
                                AND C.OnDate < DATEADD('MONTH', 1, '$params->year-$params->month-01')
                                AND COALESCE(C.CouponState, 0) IN (1, 2)
                            )::NUMERIC
                            /
                            NULLIF((
                                SELECT COUNT(C.MedCouponCode)
                                FROM MedCoupon C
                                WHERE C.MedDoctorCode = MD.MedDoctorCode
                                AND C.OnDate >= '$params->year-$params->month-01'
                                AND C.OnDate < DATEADD('MONTH', 1, '$params->year-$params->month-01')
                            ), 0)
                        ) * 100, 1),
                        0
                    )
                )                               "percent"
            FROM MedDoctor MD
            LEFT OUTER JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
            JOIN Department D ON D.DepartmentCode = MD.DepartmentCode
                AND fn_WebUserAccessDepartment(D.DepartmentCode) = 1
            LEFT OUTER JOIN Branch B ON B.BranchCode = D.BranchCode
            LEFT OUTER JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = D.DepartmentCode
            $whereDoc
                AND (MP.Name LIKE '%$searchText%' OR FN_cmFIO(MD.ManCode) LIKE '%$searchText%')
                AND B.EnterpriseCode = $enterpriseCode
            ORDER BY 2 ASC
            LIMIT $limit offset $offset
            SQL;
$sqlGraph = <<<SQL
            SELECT
                MCG.MedCouponGraphCode          "id",
                TO_CHAR(MCG.OnDate, 'DD')       "date",
                MCG.StartTime                   "startTime",
                MCG.FinishTime                  "finishTime",
                TO_CHAR(MCG.StartTime, 'HH24:MI') || '-' || TO_CHAR(MCG.FinishTime, 'HH24:MI')
                                                "schedule",
                MCG.OnDate                      "fullDate",
                MCG.Cabinet                     "cabinet",
                MCG.MedDoctorCode               "doctorId",
                (
                    SELECT COUNT(C.MedCouponCode)
                    FROM MedCoupon C
                    WHERE C.MedDoctorCode = MD.MedDoctorCode
                        AND C.VisitTime IS NOT NULL
                        AND C.OnDate = MCG.OnDate
                )                               "countCoupon",
                (
                    SELECT COUNT(C.MedCouponCode)
                    FROM MedCoupon C
                    WHERE C.MedDoctorCode = MD.MedDoctorCode
                        AND COALESCE(C.CouponState, 0) = 3
                        AND C.VisitTime IS NOT NULL
                        AND C.OnDate = MCG.OnDate
                )                               "cancelCoupon",
                DOUBLETOSTR(
                    COALESCE(
                        ROUND((
                            (
                                SELECT COUNT(C.MedCouponCode)
                                FROM MedCoupon C
                                WHERE C.MedDoctorCode = MD.MedDoctorCode
                                AND C.OnDate = MCG.OnDate
                                AND COALESCE(C.CouponState, 0) IN (1, 2)
                            )::NUMERIC
                            /
                            NULLIF((
                                SELECT COUNT(C.MedCouponCode)
                                FROM MedCoupon C
                                WHERE C.MedDoctorCode = MD.MedDoctorCode
                                AND C.OnDate = MCG.OnDate
                            ), 0)
                        ) * 100, 1),
                        0
                    )
                )                               "percent",
                COALESCE(
                    (
                        SELECT CW.Name
                        FROM MedCoupon C
                        JOIN MedCouponWork AS CW ON COALESCE(C.MedCouponWorkCodeChange, C.MedCouponWorkCode) = CW.MedCouponWorkCode AND COALESCE(CW.FlagCancel, 0) = 1
                        WHERE C.MedDoctorCode = MD.MedDoctorCode AND COALESCE(C.CouponState, 0) = 3 AND C.VisitTime IS NOT NULL AND C.OnDate = MCG.OnDate
                        LIMIT 1
                    ),
                    (
                        SELECT CW.Name
                        FROM MedCouponGraph MGRAPH
                        JOIN MedCouponGraphWork AS MCGW ON MGRAPH.MedCouponGraphCode = MCGW.MedCouponGraphCode
                        JOIN MedCouponWork AS CW ON MCGW.MedCouponWorkCode = CW.MedCouponWorkCode AND COALESCE(CW.FlagCancel, 0) = 1
                        WHERE MGRAPH.MedDoctorCode = MD.MedDoctorCode AND MGRAPH.OnDate = MCG.OnDate
                        LIMIT 1
                    )
                )                               "cancelReason"
            FROM MedCouponGraph MCG
            JOIN MedDoctor MD ON MCG.MedDoctorCode = MD.MedDoctorCode
            LEFT OUTER JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = MD.DepartmentCode
            $where
            SQL;
$stmtDoc = $db->prepare($sqlDoc); $stmtDoc->execute(); $doctors = $stmtDoc->fetchAll(); $stmtGraph = $db->prepare($sqlGraph); $stmtGraph->execute(); $workGraphs = $stmtGraph->fetchAll(); $ret = []; foreach ($doctors as $key => $doctor) { $ret[$doctor['id']] = $key; foreach ($workGraphs as $workGraph) { if ($doctor['id'] === $workGraph['doctorId']) { $doctors[$ret[$workGraph['doctorId']]]['col' . (+$workGraph['date'])] = $workGraph; } } } $root = new stdClass(); $root->total = $total; $root->items = $doctors; return $root; } public function updateWorkGraph($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            UPDATE MedDoctor
            SET BasicTimeTable = CASE WHEN ? = 1 THEN ? ELSE BasicTimeTable END
            WHERE MedDoctorCode = ?
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $basicTimetable = $this->sanitize_win1251(isUndefinedProperty($row, 'basicTimetable')); $stmt->execute([ propertyExistsSQL($row, 'basicTimetable'), $basicTimetable, $row->id ]); $talonDoctor = new stdClass; $talonDoctor->medDoctorCode = $row->id; $talonDoctor->BasicTimeTable = isUndefinedProperty($row, 'basicTimetable'); unset($talonDoctor); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } function sanitize_win1251($str) { $ret = $str ? iconv('UTF-8', 'WINDOWS-1251//IGNORE', $str) : null; $ret = $ret ? iconv('WINDOWS-1251', 'UTF-8', $ret) : null; return $ret; } public function syncWorkGraphDay($record) { $db = $this->db ?: $this->db = connectDb(); $objStartTime = safe_date_create(isUndefinedProperty($record, 'startTime')); $startTime = $objStartTime ? $objStartTime->format('1900-01-01 H:i:s') : null; $objFinishTime = safe_date_create(isUndefinedProperty($record, 'finishTime')); $finishTime = $objFinishTime ? $objFinishTime->format('1900-01-01 H:i:s') : null; $basicTimetable = $this->sanitize_win1251(isUndefinedProperty($record, 'basicTimetable')); $doctorId = isUndefinedProperty($record, 'doctorId'); if (!$startTime || !$finishTime) { throw new Exception('syncWorkGraphDay: передан неверный формат даты!'); } $db->beginTransaction(); try { $sql = <<<SQL
                UPDATE MedDoctor
                SET BasicTimetable = CASE WHEN ? = 1 THEN ? ELSE BasicTimetable END
                WHERE MedDoctorCode = ?
                SQL;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($record, 'basicTimetable'), $basicTimetable, $doctorId ]); if ((int) $record->id) { $sql = <<<"EOT"
update MedCouponGraph set
    StartTime = case when ? = 1 then ? else StartTime end,
    FinishTime = case when ? = 1 then ? else FinishTime end,
    Cabinet = case when ? = 1 then ? else Cabinet end
where MedCouponGraphCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($record, 'startTime'), $startTime, propertyExistsSQL($record, 'finishTime'), $finishTime, propertyExistsSQL($record, 'cabinet'), isEmptyProperty($record, 'cabinet'), $record->id ]); $ret = $record->id; } else { $sql = <<<"EOT"
insert into MedCouponGraph (OnDate, StartTime, FinishTime, Cabinet, MedDoctorCode)
values (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($record, 'date'), $startTime, $finishTime, isUndefinedProperty($record, 'cabinet'), isUndefinedProperty($record, 'doctorId') ]); $ret = $db->lastInsertId('seq_MedCouponGraph'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function checkGraphCabinet($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select EXISTS(
select MCG.medCouponGraphCode
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where MCG.onDate = ?
  and MCGW.MedCouponGraphWorkCode <> ?
  and (GREATEST(MCG.startTime, ?::timestamp) <
      LEAST(MCG.finishTime, ?::timestamp))
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = ?
          limit 1)
EOT;
return selectSqlValue($db, $sql, [ isEmptyProperty($params, 'onDate'), isEmptyProperty($params, 'graphWorkId', 0), isEmptyProperty($params, 'startTime'), isEmptyProperty($params, 'finishTime'), isEmptyProperty($params, 'cabinet') ]); } public function readWorkGraphJob($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $cancel = isset($params->cancel) && isEmptyProperty($params, 'cancel') ? $params->cancel : null; $sqlFromWhere = ""; if (isset($id)) { $sqlFromWhere .= " gw.medCouponGraphWorkCode =  $id"; } else { $sqlFromWhere .= " g.medCouponGraphCode = ?"; } if (isset($cancel)) { $sqlFromWhere .= " gw.flagCancel = 1"; } $sql = <<<"EOT"
select
    gw.medCouponGraphWorkCode "id",
    gw.medCouponGraphCode "couponGraphCode",
    gw.medCouponWorkCode "couponWorkCode",
    gw.StartTime "startTime",
    gw.FinishTime "finishTime",
    gw.Cabinet "cabinet",
    gw.AlternateFlag "alternateFlag",
    cw.Name "name",
    cast(case coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0
        when true then 60.0 / (coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * 0.01 * (100.0 + coalesce( D.Intensity, 0.0 )) * coalesce( nullif( CWP.Coefficient, 0.0 ), 1.0 ))
        when false then 0.0
    end as numeric) "visitTime",
    round((datediff('minute',gw.StartTime, gw.FinishTime) + 1) / cast(case coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0
                                                           when true then 60.0 / (coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * 0.01 * (100.0 + coalesce( D.Intensity, 0.0 )) * coalesce( nullif( CWP.Coefficient, 0.0 ), 1.0 ))
                                                           when false then null
                                                           end as numeric), 1) "couponCount"
from medCouponGraph g
join medCouponGraphWork gw on gw.medCouponGraphCode = g.medCouponGraphCode
left join medCouponWork cw on cw.medCouponWorkCode = gw.medCouponWorkCode

left join medJobVisitKind vk on vk.medJobVisitKindCode = cw.medJobVisitKindCode
join medDoctor d on d.medDoctorCode = g.medDoctorCode
left join medCouponWorkProf cwp on cwp.medCouponWorkCode = cw.medCouponWorkCode and cwp.medProfessionCode = d.medProfessionCode
left join medJobPlan p on p.medProfessionCode = d.medProfessionCode and p.medJobVisitKindCode = vk.medJobVisitKindCode

where  $sqlFromWhere
order by 4, 5, 6
EOT;
$stmt = $db->prepare($sql); if (isset($id)) { $stmt->execute(); } else { $stmt->execute([$params->dayCode]); } return $stmt->fetchAll(); } public function createWorkGraphJob($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into medCouponGraphWork (medCouponGraphCode, medCouponWorkCode, startTime, finishTime, cabinet, alternateFlag)
values (?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $objStartTime = safe_date_create(isUndefinedProperty($row, 'startTime')); $startTime = $objStartTime ? $objStartTime->format('1900-01-01 H:i:s') : null; $objFinishTime = safe_date_create(isUndefinedProperty($row, 'finishTime')); $finishTime = $objFinishTime ? $objFinishTime->format('1900-01-01 H:i:s') : null; if (!$startTime || !$finishTime) { throw new Exception('createWorkGraphJob: передан неверный формат даты!'); } $stmt->execute([ isEmptyProperty($row, 'couponGraphCode'), isEmptyProperty($row, 'couponWorkCode'), $startTime, $finishTime, isUndefinedProperty($row, 'cabinet'), isUndefinedProperty($row, 'alternateFlag') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCouponGraphWork'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWorkGraphJob($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update medCouponGraphWork set
       medCouponGraphCode = case when ? = 1 then ? else medCouponGraphCode end,
       medCouponWorkCode = case when ? = 1 then ? else medCouponWorkCode end,
       startTime = case when ? = 1 then ? else startTime end,
       finishTime = case when ? = 1 then ? else finishTime end,
       cabinet = case when ? = 1 then ? else cabinet end,
       alternateFlag = case when ? = 1 then ? else alternateFlag end
where medCouponGraphWorkCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $objStartTime = safe_date_create(isUndefinedProperty($row, 'startTime')); $startTime = $objStartTime ? $objStartTime->format('1900-01-01 H:i:s') : null; $objFinishTime = safe_date_create(isUndefinedProperty($row, 'finishTime')); $finishTime = $objFinishTime ? $objFinishTime->format('1900-01-01 H:i:s') : null; if (!$startTime || !$finishTime) { throw new Exception('updateWorkGraphJob: передан неверный формат даты!'); } $stmt->execute([ propertyExistsSQL($row, 'couponGraphCode'), isEmptyProperty($row, 'couponGraphCode'), propertyExistsSQL($row, 'couponWorkCode'), isEmptyProperty($row, 'couponWorkCode'), propertyExistsSQL($row, 'startTime'), $startTime, propertyExistsSQL($row, 'finishTime'), $finishTime, propertyExistsSQL($row, 'cabinet'), isUndefinedProperty($row, 'cabinet'), propertyExistsSQL($row, 'alternateFlag'), isUndefinedProperty($row, 'alternateFlag'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWorkGraphJob($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medCouponGraphWork', 'medCouponGraphWorkCode', $records); } public function readWorkGraphCoupon($params = null) { $db = $this->db ?: $this->db = connectDb(); $date = isEmptyProperty($params, 'date'); $doctor = isEmptyProperty($params, 'doctor'); $sql = <<<"EOT"
select
    c.medCouponCode "id",
    c.medDoctorCode "doctorId",
    c.OnDate "date",
    c.fio "patientFioString",
    c.VisitTime "visitTime",
    c.CouponState "couponStateCode",
    case coalesce(c.CouponState,0) when 0 then 'Свободен' when 1 then 'Заказан' when 2 then 'Выдан' when 3 then 'Отменен' end "couponState",
    c.medCouponWorkCode "couponWorkCode",
    c.medCouponWorkCodeChange "couponWorkCodeChange",
    cw.Name "couponWork",
    CWCancel.Name "cancelWork",
    p.NumberHistorySickness "historyNumber",
    fn_cmFIO(p.ManCode) "patientFIO",
    C.Note "note"
from medCoupon c
left outer join medPatient p on p.medPatientCode = c.medPatientCode
left outer join medCouponWork cw on cw.medCouponWorkCode = c.medCouponWorkCode
LEFT JOIN medCouponWork CWCancel ON CWCancel.medCouponWorkCode = C.medCouponWorkCodeChange AND CWCancel.flagCancel = 1
where c.OnDate = ? and c.medDoctorCode = ? and c.VisitTime is not null
order by 2
EOT;
return selectSql($db, $sql, [$date, $doctor]); } public function createWorkGraphCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into medCoupon (VisitTime, medCouponWorkCode, medCouponWorkCodeChange, OnDate, medDoctorCode, CouponState)
values (?, ?, ?, ?, ?, 0)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $objTime = safe_date_create(isUndefinedProperty($row, 'visitTime')); $visitTime = $objTime ? $objTime->format('1900-01-01 H:i:s') : null; if (!$visitTime) { throw new Exception('createWorkGraphCoupon: передан неверный формат даты!'); } $stmt->execute([ $visitTime, isUndefinedProperty($row, 'couponWorkCode'), isUndefinedProperty($row, 'couponWorkCodeChange'), isUndefinedProperty($row, 'date'), isUndefinedProperty($row, 'doctorId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCoupon'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWorkGraphCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update medCoupon set
       VisitTime = case when ? = 1 then ? else VisitTime end,
       couponState = case when ? = 1 then ? else couponState end,
       medCouponWorkCode = case when ? = 1 then ? else medCouponWorkCode end,
       medCouponWorkCodeChange = case when ? = 1 then ? else medCouponWorkCodeChange end
where medCouponCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $objTime = safe_date_create(isUndefinedProperty($row, 'visitTime')); $visitTime = $objTime ? $objTime->format('1900-01-01 H:i:s') : null; if (!$visitTime) { throw new Exception('createWorkGraphCoupon: передан неверный формат даты!'); } $stmt->execute([ propertyExistsSQL($row, 'visitTime'), $visitTime, propertyExistsSQL($row, 'couponStateCode'), isEmptyProperty($row, 'couponStateCode'), propertyExistsSQL($row, 'couponWorkCode'), isEmptyProperty($row, 'couponWorkCode'), propertyExistsSQL($row, 'couponWorkCodeChange'), isEmptyProperty($row, 'couponWorkCodeChange'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWorkGraphCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sqlCheck = <<<"EOT"
select 1 "flag" from medCoupon where couponState in (1,2) and MedCouponCode $in limit 1
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute(); if (fetchValue($stmtCheck, 'flag')) { throw new Exception('Удалять можно только свободные или отмененные талоны!'); } $sql = <<<"EOT"
delete from medCoupon where MedCouponCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function cancelWorkGraphCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update medCoupon set
       couponState = 3
 where OnDate = ? and medDoctorCode = ? and VisitTime BETWEEN ? AND ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $params->date, $params->doctor, $params->startTime, $params->finishTime ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return null; } public function generateWorkGraphCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $doctor = isEmptyProperty($params, 'doctor'); $date = isEmptyProperty($params, 'date'); if (!$doctor || !$date) { throw new Exception('generateWorkGraphCoupon: переданы неверные параметры!'); } $sqlCheck = <<<"EOT"
select 1 "flag" from medCoupon where couponState in (1,2,3) and medDoctorCode = $doctor AND OnDate = $date limit 1
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute(); if (fetchValue($stmtCheck, 'flag')) { throw new Exception('Генерация невозможна, поскольку имеются заказанные, выданные или отмененные талоны!'); } $sql = <<<"EOT"
DO \$anon$
DECLARE
  prev_Time timestamp;
  cur_Time timestamp;
  I int4;
  TimeDelta numeric(20,8);
BEGIN
    DELETE FROM medCoupon
  WHERE medDoctorCode = $params->doctor AND OnDate = $params->date;
DECLARE cur_CG_r RECORD;
    cur_CG NO SCROLL CURSOR FOR
        SELECT G.medDoctorCode as cur_DoctorCode,
            G.OnDate as cur_Date,
            GW.medCouponWorkCode as cur_CouponWorkCode,
            GW.StartTime as cur_StartTime,
            GW.FinishTime as cur_FinishTime,
            DATEDIFF( 'MINUTE', CAST(GW.StartTime as time), CAST(GW.FinishTime as time) ) + 1 as cur_Period,
            CAST(CASE COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0 WHEN true THEN 60.0 / (COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * (100.0 + COALESCE( D.Intensity, 0.0 )) * 0.01 * COALESCE( NULLIF( CWP.Coefficient, 0.0 ), 1.0 )) WHEN false THEN 0.0 END as numeric) as VisitTime,
            CASE CAST((CASE COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0 WHEN true THEN 60.0 / (COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * (100.0 + COALESCE( D.Intensity, 0.0 )) * 0.01 * COALESCE( NULLIF( CWP.Coefficient, 0.0 ), 1.0 )) WHEN false THEN 0.0 END) as numeric ) > 0 WHEN true THEN FN_cmRounding( ROUND( CAST( (DATEDIFF( 'MINUTE', CAST(GW.StartTime as time), CAST(GW.FinishTime as time) ) + 1 ) AS numeric ) / CAST( (CASE COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0 WHEN true THEN 60.0 / (COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * (100.0 + COALESCE( D.Intensity, 0.0 )) * 0.01 * COALESCE( NULLIF( CWP.Coefficient, 0.0 ), 1.0 )) WHEN false THEN 0.0 END ) AS numeric ), 1 ), 1, -1 ) WHEN false THEN 0 END as VisitCount,
            COALESCE( CW.FlagCoupon, 0 ) as cur_FlagCoupon
        FROM medCouponGraph AS G
        JOIN medCouponGraphWork AS GW ON GW.medCouponGraphCode = G.medCouponGraphCode
        JOIN medCouponWork AS CW ON CW.medCouponWorkCode = GW.medCouponWorkCode
        LEFT OUTER JOIN medJobVisitKind AS VK ON VK.medJobVisitKindCode = CW.medJobVisitKindCode
        JOIN medDoctor AS D ON D.medDoctorCode = G.medDoctorCode
        LEFT OUTER JOIN medCouponWorkProf AS CWP ON CWP.medCouponWorkCode = CW.medCouponWorkCode AND CWP.medProfessionCode = D.medProfessionCode
        LEFT OUTER JOIN medJobPlan AS P ON P.medProfessionCode = D.medProfessionCode AND P.medJobVisitKindCode = VK.medJobVisitKindCode
        WHERE
            G.medDoctorCode = $params->doctor AND
            G.OnDate = $params->date AND
            (COALESCE( CW.FlagCoupon, 0 ) ) = 1 AND
            (CASE (CASE COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0 WHEN true THEN 60.0 / (COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * (100.0 + COALESCE( D.Intensity, 0.0 )) * 0.01 * COALESCE( NULLIF( CWP.Coefficient, 0.0 ), 1.0 )) WHEN false THEN 0.0 END ) > 0 WHEN true THEN FN_cmRounding( ROUND( CAST( (DATEDIFF( 'MINUTE', CAST(GW.StartTime as time), CAST(GW.FinishTime as time) ) + 1 ) AS numeric ) / CAST( (CASE COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0 WHEN true THEN 60.0 / (COALESCE( NULLIF( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * (100.0 + COALESCE( D.Intensity, 0.0 )) * 0.01 * COALESCE( NULLIF( CWP.Coefficient, 0.0 ), 1.0 )) WHEN false THEN 0.0 END ) AS numeric ), 1 ), 1, -1 ) WHEN false THEN 0 END ) > 0
        ORDER BY 2, 4, 5 ;
            BEGIN
                cur_CG:=NULL;
                FOR cur_CG_r IN cur_CG LOOP
                    cur_Time := cur_CG_r.cur_StartTime;
                    prev_Time := DATEADD( 'millisecond', -1, cur_CG_r.cur_StartTime );
                    I := 0;
                    TimeDelta := 0;
                    WHILE I < cur_CG_r.VisitCount LOOP
                        I := I + 1;
                        IF cur_Time <= (cur_CG_r.cur_FinishTime ) THEN
                            IF prev_Time < cur_Time THEN
                                INSERT INTO medCoupon (ondate , meddoctorcode , visittime , medcouponworkcode , couponstate ) VALUES (cur_CG_r.cur_Date, cur_CG_r.cur_DoctorCode, cur_Time, cur_CG_r.cur_CouponWorkCode, 0 );
                                prev_Time := cur_Time;
                            END IF;
                            ELSE
                                I := cur_CG_r.VisitCount;
                            END IF;
                        cur_Time := DATEADD( 'MINUTE', ROUND( TimeDelta + cur_CG_r.VisitTime, 0 ), cur_Time );
                        TimeDelta := TimeDelta + cur_CG_r.VisitTime - ROUND( TimeDelta + cur_CG_r.VisitTime, 0 );
                    END LOOP;
                END LOOP ;
        END ;
END \$anon$;
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return (true); } public function getCouponWorkVisitTime($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId'); $couponWorkId = isEmptyProperty($params, 'couponWorkId'); if (!$doctorId || !$couponWorkId) { return null; } $sql = <<<"EOT"
select
    cast(case coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour, 0.0 ) > 0.0
        when true then 60.0 / (coalesce( nullif( CWP.VisitNumberOnHour, 0.0 ), P.VisitNumberOnHour ) * 0.01 * (100.0 + coalesce( D.Intensity, 0.0 )) * coalesce( nullif( CWP.Coefficient, 0.0 ), 1.0 ))
        when false then 0.0
    end as numeric) "visitTime"
from
    medCouponWork AS CW
join medDoctor as D on D.medDoctorCode = ?
left join medCouponWorkProf as CWP on CWP.medCouponWorkCode = CW.medCouponWorkCode and CWP.medProfessionCode = D.medProfessionCode
left join medJobVisitKind as VK on VK.MedJobVisitKindCode = CW.MedJobVisitKindCode
left join medJobPlan as P on P.medProfessionCode = D.medProfessionCode and P.medJobVisitKindCode = VK.medJobVisitKindCode
WHERE
    CW.MedCouponWorkCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, $couponWorkId]); $ret = fetchValue($stmt, 'visitTime'); return $ret; } public function checkOrderedCoupons($id) { $db = $this->db ?: $this->db = connectDb(); if (!$id) { return false; } $sql = <<<"EOT"
select
    1 "flag"
from MedCoupon
where
    coalesce(CouponState,0) in (1,2) and
    VisitTime is not null and
    MedDoctorCode = (select MedDoctorCode from MedCouponGraph where MedCouponGraphCode = ?  limit 1 ) and
    OnDate = (select OnDate from MedCouponGraph where MedCouponGraphCode = ?  limit 1 ) limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id, $id]); return (int) fetchValue($stmt, 'flag'); } public function clearWorkGraphDay($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); if (!$id) { return null; } $res = new stdClass; $exists = $this->checkOrderedCoupons($id); if ($exists === 1) { $res->code = -1; } else { $db->beginTransaction(); try { $sql = <<<"EOT"
delete from medCoupon
where
    VisitTime is not null and
    MedDoctorCode = (select MedDoctorCode from MedCouponGraph where MedCouponGraphCode = ?  limit 1 ) and
    OnDate = (select OnDate from MedCouponGraph where MedCouponGraphCode = ?  limit 1 )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id, $id]); $sql = <<<"EOT"
delete from medCouponGraph
where
    medCouponGraphCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $res->code = 0; } return ($res); } public function pasteWorkGraphDay($params) { $db = $this->db ?: $this->db = connectDb(); $src = isEmptyProperty($params, 'src'); $dst = isEmptyProperty($params, 'dst'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate'); $inTransaction = isEmptyProperty($params, 'inTransaction'); if (!$src || !$doctorId || !$onDate || ($src === $dst)) { return null; } if ($dst) { $obj = new stdClass; $obj->id = $dst; $res = $this->clearWorkGraphDay($obj); if ($res && $res->code === -1) { return $res; } } $res = new stdClass; if (!$inTransaction) { $db->beginTransaction(); } try { $sql = <<<"EOT"
insert into MedCouponGraph ( meddoctorcode, ondate, starttime, finishtime, cabinet )
(
    select
        ?, ?, starttime, finishtime, COALESCE((select cabinet from MedCouponGraph where MedCouponGraphCode = ? and MedDoctorCode = ? limit 1 ),(select cabinet from MedDoctor where MedDoctorCode = ? limit 1))
    from MedCouponGraph
    where
        MedCouponGraphCode = ?
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, $onDate, $src, $doctorId, $doctorId, $src]); $dst = $db->lastInsertId('seq_MedCouponGraph'); $sql = <<<"EOT"
insert into MedCouponGraphWork ( medcoupongraphcode, medcouponworkcode, starttime, finishtime, alternateflag,cabinet )
(
    select
        ?, medcouponworkcode, starttime, finishtime, alternateflag, cabinet
    from MedCouponGraphWork
    where
        MedCouponGraphCode = ?
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$dst, $src]); $sql = <<<"EOT"
insert into MedCoupon ( OnDate, VisitTime, medDoctorCode, medCouponWorkCode, medCouponWorkCodeChange , couponstate )
(
    select distinct
        ?, mc.VisitTime, ?, mc.medCouponWorkCode, mc.medCouponWorkCodeChange,
        CASE WHEN  mc.couponstate = 3 AND mcw.flagCancel = 1  THEN mc.couponstate
                   else 0 END
    from medCoupon as mc
    left join MedCouponGraph as mcg on mcg.onDate = mc.OnDate AND mcg.medDoctorCode = mc.medDoctorCode
    left join MedCouponGraphWork as mcgw on mcgw.medCouponGraphCode = mcg.medCouponGraphCode AND mc.visitTime >= mcgw.startTime AND mc.visitTime < mcgw.finishTime
    left join MedCouponWork as mcw on mcw.medCouponWorkCode = mcgw.medCouponWorkCode
    where
        mc.VisitTime is not null and
        mc.MedDoctorCode = (select MedDoctorCode from MedCouponGraph where MedCouponGraphCode = ?  limit 1 ) and
        mc.OnDate = (select OnDate from MedCouponGraph where MedCouponGraphCode = ?  limit 1 )
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$onDate, $doctorId, $src, $src]); $sql = <<<"EOT"
select
    mcg.MedCouponGraphCode "id",
    mcg.MedDoctorCode "doctorId",
    to_char(mcg.OnDate,  'DD') "date",
    mcg.StartTime "startTime",
    mcg.FinishTime "finishTime",
    mcg.Cabinet "cabinet",
    (select count(c.medCouponCode) from MedCoupon c where c.MedDoctorCode = mcg.MedDoctorCode
        and c.VisitTime is not null and c.OnDate = mcg.OnDate) "countCoupon",
    (select count(c.medCouponCode) from MedCoupon c where c.MedDoctorCode = mcg.MedDoctorCode
        and coalesce(c.couponState,0) = 3 and c.VisitTime is not null and c.OnDate = mcg.OnDate) "cancelCoupon",
    coalesce(
        (select cw.Name from MedCoupon c
            join MedCouponWork as cw on coalesce(c.MedCouponWorkCodeChange,c.MedCouponWorkCode) = cw.MedCouponWorkCode and coalesce(cw.FlagCancel,0) = 1
            where c.MedDoctorCode = mcg.MedDoctorCode and coalesce(c.couponState,0) = 3 and c.VisitTime is not null and c.OnDate = mcg.OnDate limit 1),
        (select cw.Name from MedCouponGraph mgraph
         join MedCouponGraphWork as mcgw on mgraph.MedCouponGraphCode = mcgw.MedCouponGraphCode
         join MedCouponWork as cw on mcgw.MedCouponWorkCode = cw.MedCouponWorkCode and coalesce(cw.FlagCancel,0) = 1
         where mgraph.MedDoctorCode = mcg.MedDoctorCode and mgraph.OnDate = mcg.OnDate limit 1)
    ) "cancelReason"
from MedCouponGraph mcg
where MedCouponGraphCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$dst]); $row = $stmt->fetch(); if ($row) { $res->id = $row['id']; $res->doctorId = $row['doctorId']; $res->date = $row['date']; $res->startTime = $row['startTime']; $res->finishTime = $row['finishTime']; $res->cabinet = $row['cabinet']; $res->countCoupon = $row['countCoupon']; $res->cancelCoupon = $row['cancelCoupon']; $res->cancelReason = $row['cancelReason']; } $sql = <<<"EOT"
select
        mgw.StartTime "startTime",
        mgw.FinishTime "finishTime",
        mgw.MedCouponWorkCode "couponWorkId",
        mgw.StartTime "startTime",
        mcw.FlagCancel "cancel"
    from medCouponGraphWork mgw
    left join MedCouponWork as mcw on mcw.medCouponWorkCode = mgw.medCouponWorkCode
    where medCouponGraphCode = ? and mcw.FlagCancel=1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$res->id]); $jobs = $stmt->fetchAll(); $sql = <<<"EOT"
select
        mc.MedCouponCode "couponId",
        mc.VisitTime "visitTime",
        mc.medDoctorCode "doctorId",
        mc.OnDate "date"
    from medCoupon mc
    where mc.medDoctorCode = ? and mc.onDate = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$res->doctorId, $onDate]); $coupons = $stmt->fetchAll(); foreach ($coupons as $couponValue) { foreach ($jobs as $jobValue) { if (safe_strtotime(substr($jobValue['startTime'], -8)) <= safe_strtotime(substr($couponValue['visitTime'], -8)) && safe_strtotime(substr($jobValue['finishTime'], -8)) >= safe_strtotime(substr($couponValue['visitTime'], -8))) { $sqlCoupon = <<<"EOT"
update MedCoupon SET
    MedCouponWorkCodeChange = ?,
    CouponState = 3
where MedCouponCode = ?
EOT;
$stmtCoupon = $db->prepare($sqlCoupon); $stmtCoupon->execute([$jobValue['couponWorkId'], $couponValue['couponId']]); } } } if (!$inTransaction) { $db->commit(); } } catch (Throwable $e) { if (!$inTransaction) { $db->rollback(); } throw $e; } $res->code = 0; return ($res); } public function cancelWorkGraphDay($params) { $db = $this->db ?: $this->db = connectDb(); $dayId = isEmptyProperty($params, 'dayId'); $jobId = isEmptyProperty($params, 'jobId'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate'); $sTime = isEmptyProperty($params, 'sTime'); $fTime = isEmptyProperty($params, 'fTime'); if (!$dayId || !$jobId) { return null; } $db->beginTransaction(); try { $sql = <<<"EOT"
SELECT
    gw.MedCouponGraphWorkCode "vMedCouponGraphWorkCode",
    gw.MedCouponGraphCode "vMedCouponGraphCode",
    gw.MedCouponWorkCode "vMedCouponWorkCode",
    DATEFORMAT( gw.StartTime,  E'hh:mm' ) "vStartTime",
    DATEFORMAT( GW.FinishTime,  E'hh:mm' ) "vFinishTime"
FROM MedCouponGraph AS g
JOIN MedCouponGraphWork AS gw ON gw.MedCouponGraphCode = g.MedCouponGraphCode
WHERE
        g.MedCouponGraphCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$dayId]); $records = $stmt->fetchAll(); if (empty($records)) { $sqlCoupon = <<<"EOT"
UPDATE MedCoupon SET
    MedCouponWorkCodeChange = ?,
    CouponState = 3
WHERE MedDoctorCode = ?
    AND OnDate = ?
    AND DATEFORMAT( VisitTime,  E'hh:mm' ) BETWEEN ? AND ?
    AND COALESCE(CouponState,0) IN (0,1,2)
EOT;
$stmtCoupon = $db->prepare($sqlCoupon); $stmtCoupon->execute([$jobId, $doctorId, $onDate, $sTime, $fTime]); } else { foreach ($records as $row) { $sqlCoupon = <<<"EOT"
UPDATE MedCoupon SET
    MedCouponWorkCodeChange = ?,
    CouponState = 3
WHERE MedDoctorCode = ?
    AND OnDate = ?
    AND DATEFORMAT( VisitTime,  E'hh:mm' ) BETWEEN ? AND ?
    AND COALESCE(CouponState,0) IN (0,1,2)
EOT;
$stmtCoupon = $db->prepare($sqlCoupon); $stmtCoupon->execute([$jobId, $doctorId, $onDate, $row['vStartTime'], $row['vFinishTime']]); $sql = <<<"EOT"
DELETE FROM MedCouponGraphWork
WHERE MedCouponGraphCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$dayId]); } } $sql = <<<"EOT"
INSERT INTO MedCouponGraphWork ( MedCouponGraphCode, MedCouponWorkCode, StartTime, FinishTime )
VALUES (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$dayId, $jobId, $sTime, $fTime]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return (true); } public function fillWorkGraphDay($params) { $db = $this->db ?: $this->db = connectDb(); $skipDate = isEmptyProperty($params, 'skipDate'); $dayId = isEmptyProperty($params, 'dayId'); $doctorId = isEmptyProperty($params, 'doctorId'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $dayArr = [isEmptyProperty($params, 'd1', 0), isEmptyProperty($params, 'd2', 0), isEmptyProperty($params, 'd3', 0), isEmptyProperty($params, 'd4', 0), isEmptyProperty($params, 'd5', 0), isEmptyProperty($params, 'd6', 0), isEmptyProperty($params, 'd7', 0)]; $parity = isEmptyProperty($params, 'parity', 0); if (!$dayId || !$doctorId || !$startDate || !$finishDate) { return null; } $wm = $this->getWeekMask($dayArr); if ($wm === 0) { return null; } switch ($parity) { case 0: $paritySQL = ''; break; case 1: $paritySQL = 'and DAY( OnDate ) & 1 = 1'; break; case 2: $paritySQL = 'and DAY( OnDate ) & 1 = 0'; break; } $weekSQL = 'and POWER( 2, case DOW(OnDate) = 1 when true then 6 when false then DOW(OnDate) - 2 end ) & ? <> 0'; $sql = <<<"EOT"
select
    (   select array_to_string_distinct(array_agg(DATEFORMAT( OnDate,  E'dd.mm.yyyy' ) ORDER BY OnDate),  E', ' )
        from MedCoupon
        where medDoctorCode = ? AND OnDate BETWEEN ? AND ? AND OnDate <> ? AND VisitTime IS NOT NULL
        $paritySQL
        $weekSQL
        AND COALESCE( CouponState, 0 ) <> (0)
    ) "skipList"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, $startDate, $finishDate, $skipDate, $wm]); $row = $stmt->fetch(); $daysCount = date_diff(safe_date_create($startDate), safe_date_create($finishDate))->days; $db->beginTransaction(); try { for ($i = 0; $i <= $daysCount; $i++) { $d = safe_date_create($startDate . '+' . $i . ' day'); if (date_timestamp_get($d) === safe_strtotime($skipDate)) { continue; } switch ($parity) { case 1: if ((date_format($d, 'd') & 1) === 0) { continue 2; } break; case 2: if ((date_format($d, 'd') & 1) === 1) { continue 2; } break; } $dow = (int) (date_format($d, 'w')); $dow--; if ($dow < 0) { $dow = 6; } if ($dayArr[$dow] === 0) { continue; } if (safe_strpos($row['skipList'], date_format($d, "d.m.Y")) === false) { $sql = <<<"EOT"
delete from medCouponGraph
where
    medDoctorCode = ? and
    onDate = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, date_format($d, "Ymd")]); $sql = <<<"EOT"
delete from medCoupon
where
    medDoctorCode = ? and
    onDate = ? and
    visitTime is not null
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, date_format($d, "Ymd")]); $obj = new stdClass(); $obj->src = $dayId; $obj->doctorId = $doctorId; $obj->onDate = date_format($d, "Ymd"); $obj->inTransaction = true; $this->pasteWorkGraphDay($obj); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $res = new stdClass; $res->code = 0; $res->skipList = $row['skipList']; return ($res); } public function freeWorkGraphDay($params) { $db = $this->db ?: $this->db = connectDb(); $dayId = isEmptyProperty($params, 'dayId'); $doctorId = isEmptyProperty($params, 'doctorId'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $dayArr = [ isEmptyProperty($params, 'd1', 0), isEmptyProperty($params, 'd2', 0), isEmptyProperty($params, 'd3', 0), isEmptyProperty($params, 'd4', 0), isEmptyProperty($params, 'd5', 0), isEmptyProperty($params, 'd6', 0), isEmptyProperty($params, 'd7', 0) ]; $parity = isEmptyProperty($params, 'parity', 0); $sqlParams = []; if (!$dayId || !$doctorId || !$startDate || !$finishDate) { return null; } $sqlParams[':doctorId'] = $doctorId; $sqlParams[':startDate'] = $startDate; $sqlParams[':finishDate'] = $finishDate; $wm = $this->getWeekMask($dayArr); if ($wm === 0) { return null; } $sqlParams[':wm'] = $wm; switch ($parity) { case 0: $paritySQL = ''; break; case 1: $paritySQL = 'AND DAY( OnDate ) & 1 = 1'; break; case 2: $paritySQL = 'AND DAY( OnDate ) & 1 = 0'; break; } $weekSQL = 'AND POWER( 2, CASE DOW(OnDate) = 1 WHEN TRUE THEN 6 WHEN FALSE THEN DOW(OnDate) - 2 END ) & :wm <> 0'; $sqlCount = <<<"EOT"
SELECT 1
FROM MedCoupon
WHERE MedDoctorCode = :doctorId AND OnDate BETWEEN :startDate AND :finishDate
$paritySQL
$weekSQL
AND COALESCE( CouponState, 0 ) <> (0)
LIMIT 1
EOT;
$resultCount = selectSqlValue($db, $sqlCount, $sqlParams); $res = new stdClass; if ($resultCount) { $sql = <<<EOT
WITH Rows AS (
    UPDATE MedCoupon
        SET CouponState = 0,
            MedPatientCode = NULL,
            SelfRecording = NULL,
            Fio = NULL,
            Note = NULL,
            GiveTime = NULL,
            OrderTime = NULL,
            TriggerFlag = NULL,
            MedCouponWorkCodeChange = NULL
    WHERE MedDoctorCode = :doctorId AND OnDate BETWEEN :startDate AND :finishDate
    $paritySQL
    $weekSQL
    AND COALESCE( CouponState, 0 ) <> (0)
    RETURNING MedCouponCode
)
SELECT COUNT(*) FROM Rows
EOT;
$res->count = selectSqlValue($db, $sql, $sqlParams); $res->code = 0; return $res; } else { $res->code = -1; return $res; } } public function fillWorkGraphMonth($params) { $db = $this->db ?: $this->db = connectDb(); $days = isEmptyProperty($params, 'days'); $doctorId = isEmptyProperty($params, 'doctorId'); $startDate = isEmptyProperty($days[0], 'date'); $finishDate = isEmptyProperty($days[count($days) - 1], 'date'); if (!$doctorId) { return null; } $sql = <<<"EOT"
select
    (   select array_to_string_distinct(array_agg(DATEFORMAT( OnDate,  E'dd.mm.yyyy' ) ORDER BY OnDate),  E', ' )
        from MedCoupon
        where medDoctorCode = ? AND OnDate BETWEEN ? AND ? AND VisitTime IS NOT NULL
        AND COALESCE( CouponState, 0 ) <> (0)
    ) "skipList"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, $startDate, $finishDate]); $row = $stmt->fetch(); $db->beginTransaction(); try { foreach ($days as $day) { $d = safe_date_create($day->date); if (safe_strpos($row['skipList'], date_format($d, "d.m.Y")) === false) { $sql = <<<"EOT"
delete from medCouponGraph
where
    medDoctorCode = ? and
    onDate = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, date_format($d, "Ymd")]); $sql = <<<"EOT"
delete from medCoupon
where
    medDoctorCode = ? and
    onDate = ? and
    visitTime is not null
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$doctorId, date_format($d, "Ymd")]); if ($day->dayId) { $obj = new stdClass(); $obj->src = $day->dayId; $obj->doctorId = $doctorId; $obj->onDate = date_format($d, "Ymd"); $obj->inTransaction = true; $this->pasteWorkGraphDay($obj); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $res = new stdClass; $res->code = 0; $res->skipList = $row['skipList']; return ($res); } public function createWorkGraphCoupons($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isUndefinedProperty($params, 'doctorId', 0); $startDate = isUndefinedProperty($params, 'startDate', 0); $finishDate = isUndefinedProperty($params, 'finishDate', 0); $sqlCheck = <<< "EOT"
    SELECT
        (SELECT
           array_to_string_distinct(array_agg(DATEFORMAT( OnDate, 'dd.mm.yyyy' ) ORDER BY OnDate),  ', ')
         FROM medCoupon
         WHERE medDoctorCode = $doctorId
           AND OnDate BETWEEN $startDate AND $finishDate
           AND COALESCE( CouponState, 0 ) <> (0)) as skipDates,
        (SELECT
           array_to_string_distinct(array_agg(DATEFORMAT( OnDate, 'yyyymmdd' )  ORDER BY OnDate), ',')
         FROM medCoupon
         WHERE medDoctorCode = $doctorId
           AND OnDate BETWEEN $startDate AND $finishDate
           AND COALESCE( CouponState, 0 ) <> (0))  as dateList
EOT;
$res = selectSql($db, $sqlCheck)[0]; $dateList = !empty($res) ? $res['datelist'] : null; $excludeDays = $dateList ? 'AND NOT OnDate IN (' . $dateList . ')' : ''; $excludeDays2 = $dateList ? 'AND (NOT G.OnDate IN  (' . $dateList . ')
        OR (G.OnDate IN (' . $dateList . ') AND
            (SELECT C.medCouponCode
             FROM medCoupon AS C WHERE C.medDoctorCode = G.medDoctorCode
               AND C.OnDate = G.OnDate
               AND C.medCouponWorkCode = GW.medCouponWorkCode
               AND C.VisitTime BETWEEN GW.StartTime AND GW.FinishTime LIMIT 1 ) IS NULL ))' : ''; $sqlGenerate = <<<"EOT"
     DO \$anon\$
DECLARE
  prev_Time timestamp;
  cur_Time timestamp;
  I int4;
  TimeDelta numeric(20,8);
BEGIN
  DELETE
  FROM medCoupon
  WHERE medDoctorCode = $doctorId AND
        OnDate BETWEEN $startDate AND
        $finishDate
        $excludeDays;
  DECLARE cur_CG_r RECORD;
    cur_CG NO SCROLL CURSOR
    FOR
        SELECT
            G.medDoctorCode as cur_DoctorCode,
            G.OnDate as cur_Date,
            GW.medCouponWorkCode as cur_CouponWorkCode,
            GW.StartTime as cur_StartTime,
            GW.FinishTime as cur_FinishTime,
            DATEDIFF('MINUTE', CAST (GW.StartTime as time), CAST (GW.FinishTime as time)) + 1 as cur_Period,
            CASE COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour, 0.0) > 0.0
               WHEN true THEN 60.0 /(COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour) *(100.0 + COALESCE(D.Intensity, 0.0)) * 0.01 * COALESCE(NULLIF(CWP.Coefficient, 0.0), 1.0))
               WHEN false THEN 0.0
            END as VisitTime,
            CASE(CASE COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour, 0.0) > 0.0
                     WHEN true THEN 60.0 /(COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour) *(100.0 + COALESCE(D.Intensity, 0.0)) * 0.01 * COALESCE(NULLIF(CWP.Coefficient, 0.0), 1.0))
                     WHEN false THEN 0.0
                   END) > 0
               WHEN true THEN FN_cmRounding(
               ROUND(
                    CAST(
                        (DATEDIFF('MINUTE', CAST (GW.StartTime as time), CAST (GW.FinishTime as time)) + 1) AS numeric) /
                            CAST (
                                (CASE COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour, 0.0) > 0.0
                                    WHEN true THEN 60.0 /(COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour) *(100.0 + COALESCE(D.Intensity, 0.0)) * 0.01 * COALESCE(NULLIF(CWP.Coefficient, 0.0), 1.0))
                                    WHEN false THEN 0.0
                                END) AS numeric), 1), 1, - 1)
               WHEN false THEN 0
            END as VisitCount,
            COALESCE(CW.FlagCoupon, 0) as cur_FlagCoupon
      FROM medCouponGraph AS G
           JOIN medCouponGraphWork AS GW ON GW.medCouponGraphCode = G.medCouponGraphCode
           JOIN medCouponWork AS CW ON CW.medCouponWorkCode = GW.medCouponWorkCode
           LEFT OUTER JOIN medJobVisitKind AS VK ON VK.medJobVisitKindCode = CW.medJobVisitKindCode
           JOIN medDoctor AS D ON D.medDoctorCode = G.medDoctorCode
           LEFT OUTER JOIN medCouponWorkProf AS CWP ON CWP.medCouponWorkCode = CW.medCouponWorkCode AND CWP.medProfessionCode = D.medProfessionCode
           LEFT OUTER JOIN medJobPlan AS P ON P.medProfessionCode = D.medProfessionCode AND P.medJobVisitKindCode = VK.medJobVisitKindCode
      WHERE G.medDoctorCode = $doctorId AND
            G.OnDate BETWEEN $startDate AND
            $finishDate
            $excludeDays2
            AND (COALESCE(CW.FlagCoupon, 0)) = 1 AND
            (CASE (CASE COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour, 0.0) > 0.0
                     WHEN true THEN 60.0 /(COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour) *(100.0 + COALESCE(D.Intensity, 0.0)) * 0.01 * COALESCE(NULLIF(CWP.Coefficient, 0.0), 1.0))
                     WHEN false THEN 0.0
                   END) > 0
               WHEN true THEN FN_cmRounding(
                            ROUND(
                                CAST (
                                    (DATEDIFF('MINUTE', CAST (GW.StartTime as time), CAST (GW.FinishTime as time)) + 1) AS numeric) /
                                    CAST (
                                        (CASE COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour, 0.0) > 0.0
                                            WHEN true THEN 60.0 /(COALESCE(NULLIF(CWP.VisitNumberOnHour, 0.0), P.VisitNumberOnHour) *(100.0 + COALESCE(D.Intensity, 0.0)) * 0.01 * COALESCE(NULLIF(CWP.Coefficient, 0.0), 1.0))
                                            WHEN false THEN 0.0
                                        END) AS numeric), 1), 1, - 1)
               WHEN false THEN 0
             END) > 0
      ORDER BY 2, 4, 5;
BEGIN cur_CG:=NULL;
    FOR cur_CG_r IN cur_CG
        LOOP
            cur_Time := cur_CG_r.cur_StartTime;
            prev_Time := DATEADD( 'millisecond', -1, cur_CG_r.cur_StartTime );
            I := 0;
            TimeDelta := 0;
        WHILE I < cur_CG_r.VisitCount
            LOOP I := I + 1;
                IF cur_Time <= (cur_CG_r.cur_FinishTime )
                    THEN IF prev_Time < cur_Time
                        THEN  INSERT INTO medCoupon(ondate, meddoctorcode, visittime, medcouponworkcode, couponstate)
                        VALUES (cur_CG_r.cur_Date, cur_CG_r.cur_DoctorCode, cur_Time, cur_CG_r.cur_CouponWorkCode, 0);
                        prev_Time := cur_Time;
                    END IF;
                ELSE
                    I := cur_CG_r.VisitCount;
                END IF;
                cur_Time := DATEADD( 'MINUTE', ROUND( TimeDelta + cur_CG_r.VisitTime, 0 ), cur_Time );
                TimeDelta := TimeDelta + cur_CG_r.VisitTime - ROUND( TimeDelta + cur_CG_r.VisitTime, 0 );
            END LOOP;
        END LOOP ;
    END ;
END \$anon\$;
EOT;
executeSql($db, $sqlGenerate); return $res; } protected function getWeekMask($arr) { if ($arr[0] === 1) { $res = 1; } else { $res = 0; } if ($arr[1] === 1) { $res += 2; } if ($arr[2] === 1) { $res += 4; } if ($arr[3] === 1) { $res += 8; } if ($arr[4] === 1) { $res += 16; } if ($arr[5] === 1) { $res += 32; } if ($arr[6] === 1) { $res += 64; } return ($res); } } handleJsonRpc('med_reception_workGraph'); 