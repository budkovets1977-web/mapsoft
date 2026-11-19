<?php
 require_once(__DIR__ . '/../../init.php'); class med_reception_workGraphCabinet { private const DEBUG = false; private $db; public function readWorkGraph($params = null) { $period = isEmptyProperty($params, 'period', '19000101'); $cabinets = $this->getCabinets($params); if (empty($cabinets)) { return false; } $total = $cabinets[0]['cnt']; $cabinetsWork = $this->getGraph($cabinets, $period); foreach ($cabinets as &$cabinet) { unset($cabinet['cnt']); foreach ($cabinetsWork as $work) { if ($work['cabinet'] === $cabinet['cabinet']) { $cabinet['col' . +$work['day']] = $work; } } } $root = new stdClass(); $root->total = $total; $root->items = $cabinets; return $root; } private function getGraph($cabinets, $period) { $db = $this->db ?: $this->db = connectDb(); $cabinetsIn = createSqlInCondition($cabinets, 'cabinet'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
with cabinetWork as (/* список кабинетов за указанный период */
select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
            WHEN true THEN MCG.cabinet
            ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime "startTimeWork",
       MCGW.finishTime "finishTimeWork",
       MCG.startTime "startTime",
       MCG.finishTime "finishTime"

from MedCouponGraph MCG
     left join MedDoctor MD on MD.medDoctorCode = MCG.medDoctorCode
     left join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
     left join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) >=  {$period}
  and COALESCE(MCG.onDate, 19000101) < {$period} + interval '1 month'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END {$cabinetsIn} /* выбираем только необходимые кабинеты */
),
cabinetList as (/* список кабинетов с рамками рабочего времени */
select
       CW.onDate,
       to_char(CW.onDate, 'DD') "day",
       /* время работы кабинета, берем и из списка работ, либо из указанных рамок в графике */
       MIN(COALESCE(CW."startTimeWork", CW."startTime")) "startTime",
       MAX(COALESCE(CW."finishTimeWork", CW."finishTime")) "finishTime",
       CW."cabinet",
       MCab.startTime "cabinetStartTime",
       MCab.finishTime "cabinetFinishTime"
from cabinetWork CW
      join MedCabinet MCab on MCab.cabinet = CW."cabinet" and MCab.enterpriseCode = $enterpriseId
group by CW.onDate, CW."cabinet", MCab.startTime, MCab.finishTime
order by CW."cabinet"
),
coupons as ( /* список всех талонов по кабинетам */
select distinct
       MC.medCouponCode, MC.*,
       CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END "cabinet",
       (select
           CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
               WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                              0.01 *
                              (100.0 + COALESCE(MD.intensity, 0.0)) * COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
               WHEN false THEN 0.0
           END::numeric "visitTime"
       from MedCouponWork MCW
       join MedDoctor MD on MD.medDoctorCode = MC.medDoctorCode
       left join MedCouponWorkProf MCWP on MCWP.medCouponWorkCode = MCW.medCouponWorkCode
                                       and MCWP.medProfessionCode = MD.medProfessionCode
       left join MedJobVisitKind VK on VK.MedJobVisitKindCode = MCW.MedJobVisitKindCode
       left join MedJobPlan MJP on MJP.medProfessionCode = MD.medProfessionCode
                               and MJP.medJobVisitKindCode = VK.medJobVisitKindCode
  where MCW.medCouponWorkCode = CW.medCouponWorkCode) "needTime"
from medCoupon MC
     join cabinetWork CW on CW.onDate = MC.onDate
                        /* берем по виду работ либо по измененному виду работ для отмененных талонов  */
                        --and CW.medCouponWorkCode in (MC.medCouponWorkCode, MC.medcouponworkcodechange)
                        and (CW.medCouponWorkCode = MC.medCouponWorkCode or
                             (COALESCE(MC.couponState, 0) = 3 and CW.medCouponWorkCode = MC.medcouponworkcodechange))
                        and CW.medDoctorCode = MC.medDoctorCode
where CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END = CW."cabinet"
  and MC.visitTime between CW."startTimeWork" and CW."finishTimeWork" -- доп. проверка на вхождение в период работы
)

select
       CL.onDate "onDate",
       CL."day",
       CL."startTime",
       CL."finishTime",
       CL."cabinet",
       CL."cabinetStartTime",
       CL."cabinetFinishTime",
       /* кол-во талонов, связь по MedCouponWorkCode, MedDoctorCode, Cabinet
        * (через MedDoctorCode + MedCouponGraphWork|MedCouponGraph)
        */
       (select COUNT(*)
        from coupons C
        where C.onDate = CL.onDate
          --and COALESCE(C.couponState, 0) = 0
          and C."cabinet" = CL."cabinet"
       ) "cntCoupon",
      /* Загруженность кабинета талонами
       * (время талонов / время работы кабинета)
       */
        (select sum(C."needTime")
        from coupons C
        where C.onDate = CL.onDate
          --and COALESCE(C.couponState, 0) = 0
          and C."cabinet" = CL."cabinet"
       ) "sumTime",
       /* кол-во отмененных талонов, связь по MedCouponWorkCode|Medcouponworkcodechange, MedDoctorCode, Cabinet
        * (через MedDoctorCode + MedCouponGraphWork|MedCouponGraph)
        */
       (select COUNT(*)
        from coupons C
        where C.onDate = CL.onDate
          and COALESCE(C.couponState, 0) = 3
          and C."cabinet" = CL."cabinet"
       ) "cntCancelCoupon",
       /*
        * Причины отмены, если есть. Берем любую запись, т.к. они все будут отменены с одинаковым видом работы
        */
       (select MCW.name
        from cabinetWork CW
             join MedCouponWork MCW on MCW.medCouponWorkCode = CW.medCouponWorkCode
                                   and COALESCE(MCW.flagCancel, 0) = 1
        where CW.onDate = CL.onDate
          and CASE COALESCE(CW.cabinet, '') = ''
                   WHEN true THEN CW.cabinet
                   ELSE CW.cabinet
              END = CL."cabinet"
        limit 1
       ) "cancelReason",
       (select
               to_char(SUM(COALESCE(CW."finishTimeWork", '19000101 00:00:00')::time -
                           COALESCE(CW."startTimeWork", '19000101 00:00:00')::time), 'HH24:MI')
        from cabinetWork CW
        where CW.onDate = CL.onDate
          and CASE COALESCE(CW.cabinet, '') = ''
                   WHEN true THEN CW.cabinet
                   ELSE CW.cabinet
              END = CL."cabinet"
        group by CASE COALESCE(CW.cabinet, '') = ''
                      WHEN true THEN CW.cabinet
                      ELSE CW.cabinet
                 END
       ) "cntTime" /* занятое время */
from cabinetList CL
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-getGraph'); return selectSql($db, $sql); } private function getCabinets($params) { $db = $this->db ?: $this->db = connectDb(); $limit = isEmptyProperty($params, 'limit', 10); $offset = isEmptyProperty($params, 'start', 0); $period = isEmptyProperty($params, 'period', '19000101'); $cabinet = isEmptyProperty($params, 'cabinet'); $whereCabinet = empty($cabinet) ? '' : "and MC.cabinet = '{$cabinet}'"; $cabinetId = isEmptyProperty($params, 'cabinetId'); $whereCabinetId = empty($cabinetId) ? '' : "and MC.medCabinetCode = {$cabinetId}"; $doctorId = isEmptyProperty($params, 'doctorId'); $whereDoctor = empty($doctorId) ? '' : "and MD.medDoctorCode = {$doctorId}"; $departmentId = isEmptyProperty($params, 'departmentId'); $whereDoctor .= empty($departmentId) ? '' : "\n  and MD.departmentCode = {$departmentId}"; $doctorSearch = isEmptyProperty($params, 'doctorSearch'); $whereDoctor .= empty($doctorSearch) ? '' : "\n  and MP.name like '%{$doctorSearch}%'"; if (!empty($whereDoctor)) { $whereDoctor = <<<"EOT"
  and MC.cabinet in (select
                            CASE COALESCE(MCGW.cabinet, '') = ''
                                 WHEN true THEN MCG.cabinet
                                 ELSE MCGW.cabinet
                            END "cabinet"
                     from MedCouponGraph MCG
                          left join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
                          left join MedDoctor MD on MD.medDoctorCode = MCG.medDoctorCode
                          left join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                     where COALESCE(MCG.onDate, 30000101) >= {$period}
                       and COALESCE(MCG.onDate, 19000101) < {$period} + interval '1 month'
                       {$whereDoctor})
EOT;
} $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
       MC.medCabinetCode "id",
       MC.cabinet "cabinet",
       MC.meddoctorcode "doctorId",
       FN_cmFIO(MD.manCode)  "doctor",
       MP.name "profession",
       D.name "department",
       MC.startTime "startTime",
       MC.finishTime "finishTime",
       COUNT(*) OVER() "cnt"
from medCabinet MC
     left join MedDoctor MD on MD.medDoctorCode = MC.medDoctorCode
     left join MedProfession MP on MP.medProfessionCode = MD.medProfessionCode
     left join Department D on D.departmentCode = MD.departmentCode
                           and fn_WebUserAccessDepartment(D.departmentCode) = 1
where MC.enterpriseCode = {$enterpriseCode}
  and COALESCE(MC.active, 0) = 1
  {$whereCabinetId}
  {$whereCabinet}
  {$whereDoctor}
order by MC.cabinet
limit {$limit} offset {$offset}
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-getCabinets'); return selectSql($db, $sql); } public function readWorkCabinet($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
       MC.medCabinetCode "id",
       MC.cabinet "cabinet",
       MC.finishTime "finishTime",
       MC.medDoctorCode "doctorId",
       MC.note "note",
       MC.startTime "startTime",
       FN_cmFIO(MD.manCode) "fio",
       MP.medProfessionCode "professionId",
       MP.name "name"
from medCabinet MC
     left join medDoctor MD on MD.medDoctorCode = MC.medDoctorCode
     left join medProfession MP on MP.medProfessionCode = MD.medProfessionCode
where MC.enterpriseCode = {$enterpriseCode}
  and COALESCE(MC.active, 0) = 1
order by MC.cabinet
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-readWorkCabinet'); return selectSql($db, $sql); } public function generateWorkCabinetCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $startDate = isEmptyProperty($params, 'startDate', 19000101); $finishDate = isEmptyProperty($params, 'finishDate', 19000101); $check = $this->checkCouponDatesFromGenerateCoupon($cabinet, $startDate, $finishDate); if (empty($check['processing'])) { return $this->getDaylist($check['skip']); } $db->beginTransaction(); try { $processingDates = createSqlInCondition($check['processing']); $this->deleteCoupon($cabinet, $processingDates); $this->createCoupon($cabinet, $processingDates, $check['skip']); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return $this->getDaylist($check['skip']); } private function deleteCoupon($cabinet, $dates) { $sql = <<<"EOT"
with cabinetWork as (
select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) {$dates}
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
), coupons as (
select distinct
       MC.medCouponCode
from medCoupon MC
     join cabinetWork CW on CW.onDate = MC.onDate
                        --and CW.medCouponWorkCode in (MC.medCouponWorkCode, MC.medcouponworkcodechange)
                        and (CW.medCouponWorkCode = MC.medCouponWorkCode or
                             (COALESCE(MC.couponState, 0) = 3 and CW.medCouponWorkCode = MC.medcouponworkcodechange))
                        and CW.medDoctorCode = MC.medDoctorCode
where CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END = CW."cabinet"
  and COALESCE(MC.CouponState, 0) not in (1, 2) -- очищаем только если не использованы
  and MC.visitTime between CW.startTime and CW.finishTime -- доп. проверка на вхождение в период работы
)

delete
from MedCoupon MC
     using coupons C
where MC.medCouponCode = C.medCouponCode
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-deleteCoupon'); executeSql($this->db, $sql); } private function createCoupon($cabinet, $processing, $skip) { if (empty($skip)) { $skipSql = ''; } else { $skipSql = 'and COALESCE(MCG.onDate, 19000101) not ' . createSqlInCondition($skip); } $sql = <<<"EOT"
DO \$anon\$
DECLARE
  prev_Time timestamp;
  cur_Time timestamp;
  I int4;
  TimeDelta numeric(20,8);
BEGIN
  DECLARE cur_CG_r RECORD;

  cur_CG NO SCROLL CURSOR
  FOR

     with cabinetWork as (/* список кабинетов за указанный период */
     select
            MCG.onDate,
            MCG.medDoctorCode,
            MCGW.medCouponWorkCode,
            MCGW.medCouponGraphWorkCode,
            MCGW.startTime,
            MCGW.finishTime
     from MedCouponGraph MCG
          join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
     where COALESCE(MCG.onDate, 19000101) {$processing}
       {$skipSql}
       and CASE COALESCE(MCGW.cabinet, '') = ''
                WHEN true THEN MCG.cabinet
                ELSE MCGW.cabinet
           END = '{$cabinet}' /* выбираем только необходимые кабинеты */
     )

     select
            CW.medDoctorCode cur_DoctorCode,
            CW.onDate cur_Date,
            CW.medCouponWorkCode cur_CouponWorkCode,
            CW.startTime cur_StartTime,
            CW.finishTime cur_FinishTime,
            (extract(epoch from (CW.finishTime::time - CW.startTime::time)) / 60)::int cur_Period,
            CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                 WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                                        (100.0 + COALESCE(MD.intensity, 0.0)) *
                                        0.01 *
                                        COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
                 WHEN false THEN 0.0
            END visitTime,
            CASE (CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                       WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                                              (100.0 + COALESCE(MD.intensity, 0.0)) *
                                              0.01 *
                                              COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
                       WHEN false THEN 0.0
                  END) > 0
                 WHEN true THEN FN_cmRounding(ROUND(((extract(epoch from (CW.finishTime::time - CW.startTime::time)) / 60)::int) /
                                                      CAST((CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                                                                 WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                                                                                        (100.0 + COALESCE(MD.intensity, 0.0)) *
                                                                                        0.01 *
                                                                                        COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
                                                                 WHEN false THEN 0.0
                                                            END) AS numeric), 1), 1, - 1)
                 WHEN false THEN 0
            END visitCount,
            COALESCE(MCW.flagCoupon, 0) cur_FlagCoupon

     from CabinetWork CW
          join MedCouponWork MCW on MCW.medCouponWorkCode = CW.medCouponWorkCode
          join MedDoctor MD on MD.medDoctorCode = CW.medDoctorCode
          left join MedJobVisitKind MJVK on MJVK.medJobVisitKindCode = MCW.medJobVisitKindCode
          left join MedCouponWorkProf MCWP ON MCWP.medCouponWorkCode = MCW.medCouponWorkCode
                                          and MCWP.medProfessionCode = MD.medProfessionCode
          left join MedJobPlan MJP on MJP.medProfessionCode = MD.medProfessionCode
                                  and MJP.medJobVisitKindCode = MJVK.medJobVisitKindCode

     where COALESCE(MCW.flagCoupon, 0) = 1
       and (CASE (CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                       WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.VisitNumberOnHour, 0.0), MJP.VisitNumberOnHour) *
                                              (100.0 + COALESCE(MD.Intensity, 0.0)) *
                                              0.01 *
                                              COALESCE(NULLIF(MCWP.Coefficient, 0.0), 1.0))
                       WHEN false THEN 0.0
                  END) > 0
                 WHEN true THEN FN_cmRounding(ROUND(((extract(epoch from (CW.FinishTime::time - CW.StartTime::time)) / 60)::int) /
                                                      CAST((CASE COALESCE(NULLIF(MCWP.VisitNumberOnHour, 0.0), MJP.VisitNumberOnHour, 0.0) > 0.0
                                                                 WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.VisitNumberOnHour, 0.0), MJP.VisitNumberOnHour) *
                                                                                        (100.0 + COALESCE(MD.Intensity, 0.0)) *
                                                                                        0.01 *
                                                                                        COALESCE(NULLIF(MCWP.Coefficient, 0.0), 1.0))
                                                                 WHEN false THEN 0.0
                                                            END) AS numeric), 1), 1, - 1)
                 WHEN false THEN 0
            END) > 0
     order by 2, 4, 5;

  BEGIN cur_CG:=NULL;
    FOR cur_CG_r IN cur_CG
    LOOP
         cur_Time := cur_CG_r.cur_StartTime;
         prev_Time := cur_CG_r.cur_StartTime - interval '1 millisecond';
         I := 0;
         TimeDelta := 0;
    WHILE I < cur_CG_r.VisitCount
      LOOP I := I + 1;
      IF cur_Time <= (cur_CG_r.cur_FinishTime )
         THEN IF prev_Time < cur_Time
                 THEN insert into MedCoupon
                        (ondate, meddoctorcode, visittime, medcouponworkcode, couponstate)
                      values
                        (cur_CG_r.cur_Date, cur_CG_r.cur_DoctorCode, cur_Time, cur_CG_r.cur_CouponWorkCode, 0);
                      prev_Time := cur_Time;
              END IF;
         ELSE
           I := cur_CG_r.VisitCount;
      END IF;
      cur_Time := cur_Time + (ROUND(TimeDelta + cur_CG_r.VisitTime, 0 ) || ' minute')::interval;
      TimeDelta := TimeDelta + cur_CG_r.VisitTime - ROUND( TimeDelta + cur_CG_r.VisitTime, 0 );
    END LOOP;
    END LOOP;
  END;

END \$anon\$;
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-createCoupon'); executeSql($this->db, $sql); } private function checkCouponDatesFromClear($cabinet, $date) { return $this->checkCouponDates($cabinet, dbDate($date), dbDate($date), null, null, true); } private function checkCouponDatesFromFill($cabinet, $startDate, $finishDate, $maskDay, $parityDay) { return $this->checkCouponDates($cabinet, dbDate($startDate), dbDate($finishDate), $maskDay, $parityDay, false, 2); } private function checkCouponDatesFromGenerateCoupon($cabinet, $startDate, $finishDate) { return $this->checkCouponDates($cabinet, dbDate($startDate), dbDate($finishDate)); } private function checkCouponDates( $cabinet, $startDate, $finishDate, $maskDay = null, $parityDay = null, $onlyOrdered = false, $skipType = 0) { $maskDaySql = empty($maskDay) ? '' : "and POWER(2, DOW(MC.onDate)) & {$maskDay} <> 0"; switch ($parityDay) { case 1: $parityDaySql = 'and DAY(MC.onDate) & 1 = 1'; break; case 2: $parityDaySql = 'and DAY(MC.onDate) & 1 = 0'; break; default: $parityDaySql = ''; } $statedSql = empty($onlyOrdered) ? 'and COALESCE(MC.CouponState, 0) <> 0' : 'and COALESCE(MC.CouponState, 0) in (1, 2)'; $sql = <<<"EOT"
with cabinetWork as (
select -- выборка видов работ по дням
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
            WHEN true THEN MCG.cabinet
            ELSE MCGW.cabinet
       END "cabinet" ,
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 19000101) between '{$startDate}' and '{$finishDate}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
), coupons as (
select -- предварительная выборка талонов
       MC.medCouponCode,
       MC.visitTime,
       MC.couponState,
       MC.onDate,
       MC.medDoctorCode,
       MC.medCouponWorkCode,
       MC.medCouponWorkCodeChange
from MedCoupon MC
where MC.onDate between '{$startDate}' and '{$finishDate}'
  -- проверка четности дня
  {$parityDaySql}
  -- проверка дня в недели
  {$maskDaySql}
  -- проверка состояния талона
  {$statedSql}
  -- проверка по врачам в кабинете
  and MC.medDoctorCode in (select distinct medDoctorCode from cabinetWork)
)

select
       t."date",
       SUM(t."mask") "mask"  -- на всякий суммируем для битовой маски
from (select /* проверка наличия талонов по связанным с ними графиком работ (общая проверка) */
             CW.OnDate "date",
             EXISTS(select
                           _C.medCouponCode
                    from coupons _C
                         join cabinetWork _CW on _CW.onDate = _C.onDate
                                             --and _CW.medCouponWorkCode in (_C.medCouponWorkCode, _C.medcouponworkcodechange)
                                             and (_CW.medCouponWorkCode = _C.medCouponWorkCode or
                                                (COALESCE(_C.couponState, 0) = 3 and _CW.medCouponWorkCode = _C.medcouponworkcodechange))
                                             and _CW.medDoctorCode = _C.medDoctorCode
                    where _C.onDate = CW.onDate
                      -- проверяем кабинет, если не нашли по работе, пробуем по врачу
                      and CASE COALESCE(_CW."cabinet", '') = ''
                               WHEN true THEN (select _MCG_.cabinet
                                               from MedCouponGraph _MCG_
                                               where _MCG_.onDate = _C.onDate
                                                 and _MCG_.medDoctorCode = _C.medDoctorCode)
                               ELSE COALESCE(_CW."cabinet", '')
                          END = CW."cabinet"
                      and _C.visitTime between _CW.startTime and _CW.finishTime
                    limit 1)::int * 2 "mask"
      from cabinetWork CW
      union
      select /* проверка наличия талонов без связанного графика работы (для автозаполнения дней ) */
             C.onDate "date",
             4 "mask"
      from coupons C
           join cabinetWork CW on (CW.medCouponWorkCode = C.medCouponWorkCode or
                                   CW.medCouponWorkCode = C.medCouponWorkCodeChange)
                              and CW.medDoctorCode = C.medDoctorCode
                              -- отсекаем связанные с графиком даты
                              and CW.onDate <> C.onDate
                              -- берем только попадающие по времени
                              and C.visitTime between CW.startTime and CW.finishTime
      ) t
group by t."date"
EOT;
$skip = []; $processing = []; $this::DEBUG && app_log($sql, 0, 'workGraphCabinet-checkCouponDates'); $dayList = selectSql($this->db, $sql); foreach ($dayList as $day) { if ($day['mask'] > 0) { if (($skipType === 0 && ($day['mask'] & 2 === 0)) || ($skipType === 1 && ($day['mask'] & 4 === 0))) { continue; } $skip[] = $day['date']; } else { $processing[] = $day['date']; } } return [ 'skip' => $skip, 'processing' => $processing ]; } private function getDaylist($dates) { $days = ''; foreach ($dates as $item) { if (empty($item)) { continue; } $date = DateTime::createFromFormat('Y-m-d', $item); $days .= (empty($days) ? '' : ', ') . $date->format('d.m.Y'); } return $days; } public function clearDayCabinet($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $date = isEmptyProperty($params, 'day', 19000101); $check = $this->checkCouponDatesFromClear($cabinet, $date); if (empty($check['processing'])) { return -1; } $db->beginTransaction(); try { $processingDates = createSqlInCondition($check['processing']); $this->deleteCoupon($cabinet, $processingDates); $this->deleteWorkGraph($cabinet, $processingDates); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return true; } private function deleteWorkGraph($cabinet, $dates) { $sql = <<<"EOT"
DO \$anon\$
BEGIN
/* удаляем записанные виды работ по кабинету */
delete
from MedCouponGraphWork MCGW
     using MedCouponGraph MCG
where MCGW.medCouponGraphCode = MCG.medCouponGraphCode
  and MCG.onDate {$dates}
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}';

/* удаляем графики работ по кабинету */
delete
from MedCouponGraph MCG
where MCG.cabinet = '{$cabinet}'
  and MCG.onDate {$dates}
  and not EXISTS(select MCGW.MedCouponGraphWorkCode
                 from MedCouponGraphWork MCGW
                 where MCGW.MedCouponGraphCode = MCG.MedCouponGraphCode
                 limit 1);
END \$anon\$;
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-deleteWorkGraph'); executeSql($this->db, $sql); } public function cancelDayCabinet($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $date = dbDate(isEmptyProperty($params, 'onDate')); $workId = isEmptyProperty($params, 'workId'); if (empty($workId)) { return -1; } $db->beginTransaction(); try { $this->cancelCoupon($cabinet, $date, $workId); $this->cancelWork($cabinet, $date, $workId); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return true; } private function cancelCoupon($cabinet, $date, $workId = null) { if (empty($workId)) { $dateSql = "and COALESCE(MCG.onDate, 30000101) in ({$date})"; $workSql = "medCouponWorkCodeChange = C.medCouponWorkCode,"; $cancelWorkSql = <<<"EOT"
join MedCouponWork MCW on MCW.medCouponWorkCode = MCGW.medCouponWorkCode
                      and COALESCE(MCW.flagCancel, 0) = 1
EOT;
} else { $dateSql = "and COALESCE(MCG.onDate, 30000101) = '{$date}'"; $workSql = "medCouponWorkCodeChange = {$workId},"; $cancelWorkSql = ""; } $sql = <<<"EOT"
with cabinetWork as (
select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
     {$cancelWorkSql}
where CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
  {$dateSql}
), coupons as (
select distinct
       MC.medCouponCode,
       CW.medCouponWorkCode
from medCoupon MC
     join cabinetWork CW on CW.onDate = MC.onDate
                        --and CW.medCouponWorkCode in (MC.medCouponWorkCode, MC.medcouponworkcodechange)
                        and (CW.medCouponWorkCode = MC.medCouponWorkCode or
                             (COALESCE(MC.couponState, 0) = 3 and CW.medCouponWorkCode = MC.medcouponworkcodechange))
                        and CW.medDoctorCode = MC.medDoctorCode
where CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END = CW."cabinet"
  and COALESCE(MC.CouponState, 0) in (0, 1, 2)
  and MC.visitTime between CW.startTime and CW.finishTime -- доп. проверка на вхождение в период работы
)

update MedCoupon set -- отменяем необходимые талоны
       {$workSql}
       couponState = 3
from Coupons C
where C.medCouponCode = MedCoupon.medCouponCode
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-cancelCoupon'); executeSql($this->db, $sql); } private function cancelWork($cabinet, $date, $workId) { $sql = <<<"EOT"
with works as (
select -- ищем все работы по кабинету по заданному дню
       MCGW.MedCouponGraphWorkCode
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) = '{$date}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
)

update MedCouponGraphWork set -- обновляем вид работы
       medCouponWorkCode = {$workId}
from works W
where W.medCouponGraphWorkCode = MedCouponGraphWork.medCouponGraphWorkCode
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-cancelWork'); executeSql($this->db, $sql); } private function checkIntersectedWork($cabinet, $date, $startDate, $finishDate, $maskDay, $parityDay) { $maskDaySql = empty($maskDay) ? '' : "and POWER(2, DOW(MCG.onDate)) & {$maskDay} <> 0"; switch ($parityDay) { case 1: $parityDaySql = 'and DAY(MCG.onDate) & 1 = 1'; break; case 2: $parityDaySql = 'and DAY(MCG.onDate) & 1 = 0'; break; default: $parityDaySql = ''; } $sql = <<<"EOT"
        with cabinetWork as (
select distinct -- достаем уникальные medCouponGraph
       MCG.medDoctorCode,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.MedCouponGraphCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
  and MCG.onDate = '{$date}'
)

select
       string_agg(MCG.onDate::text, ',')
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
     join cabinetWork CW on true
where MCG.onDate between '{$startDate}' and '{$finishDate}'
  and MCG.medDoctorCode = CW.medDoctorCode
  and GREATEST(MCGW.startTime, CW.startTime::timestamp) <
      LEAST(MCGW.finishTime, CW.finishTime::timestamp)
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END <> '{$cabinet}'
  -- проверка четности дня
  {$parityDaySql}
  -- проверка дня в недели
  {$maskDaySql}
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-checkIntersectedWork'); return safe_explode(',', selectSqlValue($this->db, $sql)); } public function fillWorkDays($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $date = isEmptyProperty($params, 'date'); $maskDay = isEmptyProperty($params, 'maskDay'); $parity = isUndefinedProperty($params, 'parity'); $check = $this->checkCouponDatesFromFill($cabinet, $startDate, $finishDate, $maskDay, $parity); $checkWork = $this->checkIntersectedWork( $cabinet, dbDate($date), dbDate($startDate), dbDate($finishDate), $maskDay, $parity); $currDate = new DateTime($startDate); $daysCount = $currDate->diff(new DateTime($finishDate))->days; $db->beginTransaction(); $processing = ''; try { $dayInterval = new DateInterval('P1D'); for ($i = 0; $i <= $daysCount; $i++) { if ($currDate->getTimestamp() === safe_strtotime($date) || ($parity === 1 && $currDate->format('d') & 1 === 0) || ($parity === 2 && $currDate->format('d') & 1 === 1) || ((pow(2, (int) $currDate->format('w') + 1) & $maskDay) === 0) || (in_array($currDate->format('Y-m-d'), $check['skip'])) || (in_array($currDate->format('Y-m-d'), $checkWork))) { $currDate->add($dayInterval); continue; } $processing .= (empty($processing) ? '' : ', ') . "'{$currDate->format('Y-m-d')}'"; $currDate->add($dayInterval); } if (empty($processing)) { return $this->createFillWorkResult($check, $checkWork, false); } $this->deleteCoupon($cabinet, "in ({$processing})"); $this->deleteWorkGraph($cabinet, "in ({$processing})"); $this->pasteDay($cabinet, dbDate($date), $processing); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return $this->createFillWorkResult($check, $checkWork); } private function createFillWorkResult($checkCoupon, $checkWork, $processing = true) { return [ 'processing' => $processing, 'couponDay' => $this->getDaylist($checkCoupon['skip']), 'workDay' => $this->getDaylist($checkWork) ]; } private function pasteDay($cabinet, $day, $dates) { $this->copyCouponGraph($cabinet, $day, $dates); $this->copyCouponGraphWork($cabinet, $day, $dates); $this->copyCoupon($cabinet, $day, $dates); $this->cancelCoupon($cabinet, $dates); } private function copyCouponGraph($cabinet, $day, $arrayDates) { $sql = <<<"EOT"
with cabinetWork as (
select distinct -- достаем уникальные medCouponGraph
       MCG.medDoctorCode,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.MedCouponGraphCode,
       MCG.startTime,
       MCG.finishTime
from MedCouponGraph MCG
     left join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
  and MCG.onDate = '{$day}'
  )

insert into MedCouponGraph (medDoctorCode, cabinet, onDate, startTime, finishTime)
select
       CW.medDoctorCode,
       CW.cabinet,
       dates::date,
       CW.startTime,
       CW.finishTime
from unnest(ARRAY[{$arrayDates}]) dates -- формируем запрос по массиву дат, куда необходимо скопировать
     join cabinetWork CW on true
where not EXISTS(select medCouponGraphCode -- добавляем только не существующие связки врач-дата в таблицу
                 from MedCouponGraph
                 where onDate = dates::date
                   and medDoctorCode = CW.medDoctorCode
                 limit 1)
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-copyCouponGraph'); executeSql($this->db, $sql); } private function copyCouponGraphWork($cabinet, $day, $arrayDates) { $sql = <<<"EOT"
with cabinetWork as (select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
            WHEN true THEN MCG.cabinet
            ELSE MCGW.cabinet
       END "cabinet",
       MCGW.medCouponWorkCode,
       MCG.startTime,
       MCG.finishTime,
       MCGW.startTime "startTimeWork",
       MCGW.finishTime "finishTimeWork",
       MCGW.alternateFlag,
       MCG.medDoctorCode
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
  and MCG.onDate = '{$day}'
)

insert into MedCouponGraphWork (medCouponGraphCode, medCouponWorkCode, startTime, finishTime, cabinet, alternateFlag)
select
       MCG.medCouponGraphCode,
       CW.medCouponWorkCode,
       CW."startTimeWork",
       CW."finishTimeWork",
       CW."cabinet",
       CW.alternateFlag
from unnest(ARRAY[{$arrayDates}]) dates -- формируем запрос по массиву дат, куда необходимо скопировать
     join cabinetWork CW on true
     -- поиск нужного нам id записи в medCouponGraph для связи, с учетом уже существующих
     join MedCouponGraph MCG on MCG.medDoctorCode = CW.medDoctorCode
                            and MCG.onDate = dates::date
                            --and MCG.cabinet = CW."cabinet"
                            --and MCG.startTime = CW.startTime
                            --and MCG.finishTime = CW.finishTime
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-copyCouponGraphWork'); executeSql($this->db, $sql); } private function copyCoupon($cabinet, $day, $arrayDates) { $sql = <<<"EOT"
with cabinetWork as (
select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) = '{$day}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
), coupons as (
select distinct
       MC.visitTime,
       MC.medDoctorCode,
       MC.medCouponWorkCode,
       MC.medCouponWorkCodeChange,
       CASE
            WHEN COALESCE(MC.couponState, 0) = 3 and COALESCE(MCW.flagCancel, 0) = 1 THEN MC.couponState
            ELSE 0
       END "couponState"
from medCoupon MC
     join cabinetWork CW on CW.onDate = MC.onDate
                        --and CW.medCouponWorkCode in (MC.medCouponWorkCode, MC.medcouponworkcodechange)
                        and (CW.medCouponWorkCode = MC.medCouponWorkCode or
                             (COALESCE(MC.couponState, 0) = 3 and CW.medCouponWorkCode = MC.medcouponworkcodechange))
                        and CW.medDoctorCode = MC.medDoctorCode
     -- выбираем только отмененные работы
     left join MedCouponWork MCW on MCW.medCouponWorkCode = MC.medcouponworkcodechange
                                and COALESCE(MCW.flagCancel, 0) = 1
where CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END = CW."cabinet"
  and MC.visitTime between CW.startTime and CW.finishTime -- доп. проверка на вхождение в период работы
)

insert into MedCoupon (onDate, visitTime, medDoctorCode, medCouponWorkCode, medCouponWorkCodeChange , couponstate)
select
       dates::date,
       C.visitTime,
       C.medDoctorCode,
       C.medCouponWorkCode,
       C.medCouponWorkCodeChange,
       C."couponState"
from unnest(ARRAY[{$arrayDates}]) dates -- формируем запрос по массиву дат, куда необходимо скопировать
     join coupons C on true
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-copyCoupon'); executeSql($this->db, $sql); } public function readCabinetJob($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $date = dbDate(isEmptyProperty($params, 'date')); $sql = <<<"EOT"
select
       MCG.medCouponGraphCode || '-' || MCGW.medCouponGraphWorkCode "id",
       MCG.medCouponGraphCode "graphId",
       MCGW.medCouponGraphWorkCode "graphWorkId",
       MCG.onDate::timestamp "onDate",
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode "doctorId",
       FN_cmFIO(MD.manCode) "doctor",
       MP.name "profession",
       MCGW.medCouponWorkCode "workId",
       MCW.name "work",
       MCGW.startTime "workStartTime",
       MCGW.finishTime "workFinishTime",
       to_char(COALESCE(CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                             WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                                                   0.01 *
                                                   (100.0 + COALESCE(MD.intensity, 0.0)) * COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
                             WHEN false THEN 0.0
                        END, 0.0), 'FM90.00') "visitTime",
       FN_cmRounding(COALESCE(((extract(epoch from (MCGW.finishTime::time - MCGW.startTime::time) ) / 60)::int)::numeric/
                              CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
                                   WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.VisitNumberOnHour) *
                                                         0.01 *
                                                         (100.0 + COALESCE(MD.intensity, 0.0)) * COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
                                   WHEN false THEN 0.0
                              END, 0.0), 1, 1) "couponCount"
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
     join MedDoctor MD on MD.medDoctorCode = MCG.medDoctorCode
     join MedProfession MP on MP.medProfessionCode = MD.medProfessionCode
     join MedCouponWork MCW on MCW.medCouponWorkCode = MCGW.medCouponWorkCode
     left join MedJobVisitKind MJVK on MJVK.medJobVisitKindCode = MCW.medJobVisitKindCode
     left join MedCouponWorkProf MCWP on MCWP.medCouponWorkCode = MCW.medCouponWorkCode
                                     and MCWP.medProfessionCode = MD.medProfessionCode
     left join MedJobPlan MJP on MJP.medProfessionCode = MD.medProfessionCode
                             and MJP.medJobVisitKindCode = MJVK.medJobVisitKindCode

where COALESCE(MCG.onDate, 30000101) = '{$date}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'

order by MCGW.startTime, MCGW.finishTime
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-readCabinetJob'); return selectSql($db, $sql); } public function readCabinetCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $cabinet = isEmptyProperty($params, 'cabinet'); $date = dbDate(isEmptyProperty($params, 'date')); $sql = <<<"EOT"
with cabinetWork as (
select
       MCG.onDate,
       CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
       END "cabinet",
       MCG.medDoctorCode,
       MCGW.medCouponWorkCode,
       MCGW.startTime,
       MCGW.finishTime
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) = '{$date}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END = '{$cabinet}'
)

select distinct
       MC.medCouponCode "id",
       MC.visitTime "visitTime",
       MC.medCouponWorkCode "workId",
       MCW.name "work",
       COALESCE(MC.couponState, 0) "couponState",
       MC.medPatientCode "patientId",
       FN_cmFIO(MP.manCode) "patient",
       MP.numberHistorySickness "card",
       MC.medDoctorCode "doctorId",
       FN_cmFIO(MD.manCode) "doctor",
       MPR.name "profession",
       MC.medCouponWorkCodeChange "workChangeId"
from MedCoupon MC
     join cabinetWork CW on CW.onDate = MC.onDate
                       --and CW.medCouponWorkCode in (MC.medCouponWorkCode, MC.medcouponworkcodechange)
                        and (CW.medCouponWorkCode = MC.medCouponWorkCode or
                             (COALESCE(MC.couponState, 0) = 3 and CW.medCouponWorkCode = MC.medcouponworkcodechange))
                        and CW.medDoctorCode = MC.medDoctorCode
     join MedDoctor MD on MD.medDoctorCode = MC.medDoctorCode
     join MedProfession MPR on MPR.medProfessionCode = MD.medProfessionCode
     left join MedCouponWork MCW on MCW.medCouponWorkCode = MC.medCouponWorkCode
     left join MedPatient MP on MP.medPatientCode = MC.medPatientCode
where CASE COALESCE(CW."cabinet", '') = ''
           /* если не удалось получить кабинет по работе, пробуем через врача на талоне */
           WHEN true THEN (select _MCG.cabinet
                           from MedCouponGraph _MCG
                           where _MCG.onDate = MC.onDate
                             and _MCG.medDoctorCode = MC.medDoctorCode)
           ELSE COALESCE(CW."cabinet", '')
      END = CW."cabinet"
  and MC.visitTime between CW.startTime and CW.finishTime -- доп. проверка на вхождение в период работы
order by MC.visitTime
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-readCabinetCoupon'); return selectSql($db, $sql); } public function createCabinetCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedCoupon
  (onDate, medDoctorCode, visitTime, medCouponWorkCode, couponState)
values
  (?, ?, ?, ?, 0)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'onDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'visitTime'), isEmptyProperty($row, 'workId') ]); $rec = new stdClass(); $rec->id = $db->lastInsertId('seq_MedCoupon'); $ret[] = $rec; } return $ret; } public function updateCabinetCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedCoupon  set
        medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
        visitTime = case when ? = 1 then ? else visitTime end,
        medCouponWorkCode = case when ? = 1 then ? else medCouponWorkCode end,
        medCouponWorkCodeChange = case when ? = 1 then ? else medCouponWorkCodeChange end,
        couponState = case when ? = 1 then ? else couponState end
where medCouponCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'visitTime'), isUndefinedProperty($row, 'visitTime'), propertyExistsSQL($row, 'workId'), isUndefinedProperty($row, 'workId'), propertyExistsSQL($row, 'workChangeId'), isUndefinedProperty($row, 'workChangeId'), propertyExistsSQL($row, 'couponState'), isUndefinedProperty($row, 'couponState'), $row->id ]); } return true; } public function deleteCabinetCoupon($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedCoupon', 'MedCouponCode', $records, 'id', false); } public function createCabinetJob($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $row->graphId = $this->getGraphId($row); if (empty($row->graphId)) { $row->startTime = $row->workStartTime; $row->finishTime = $row->workFinishTime; $row->graphId = $this->createGraph($row); } $rec = new stdClass(); $rec->graphId = $row->graphId; $rec->graphWorkId = $this->createGraphWork($row); $rec->id = "{$rec->graphId}-{$rec->graphWorkId}"; $ret[] = $rec; } $row = is_array($records) ? $records[0] : $records; $this->updateGraphTime($row->cabinet, $row->onDate); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return $ret; } public function updateCabinetJob($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $record) { $graphIdCur = $this->getGraphId($record); if (empty($graphIdCur)) { $graphIdCur = $this->createGraph($record); } $graphIdOld = isEmptyProperty($record, 'graphId'); $record->graphId = $graphIdCur; $this->updateGraphWork($record); if ($graphIdOld !== $graphIdCur) { $this->deleteEmptyGraph($graphIdOld); } } $row = is_array($records) ? $records[0] : $records; $this->updateGraphTime($row->cabinet, $row->onDate); $db->commit(); } catch (Throwable $exc) { $db->rollback(); throw $exc; } return true; } public function deleteCabinetJob($records) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $this->deleteGraphWork($records); $row = is_array($records) ? $records[0] : $records; $this->deleteEmptyGraph(null, $row->cabinet, $row->onDate); $this->updateGraphTime($row->cabinet, $row->onDate); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function deleteGraphWork($records) { return deleteSql($this->db, 'MedCouponGraphWork', 'MedCouponGraphWorkCode', $records, 'graphWorkId', false); } private function updateGraphWork($record) { $sql = <<<"EOT"
update MedCouponGraphWork set
       medCouponGraphCode = case when ? = 1 then ? else medCouponGraphCode end,
       medCouponWorkCode = case when ? = 1 then ? else medCouponWorkCode end,
       startTime = case when ? = 1 then ? else startTime end,
       finishTime = case when ? = 1 then ? else finishTime end
where medCouponGraphWorkCode = ?
EOT;
executeSql($this->db, $sql, [ propertyExistsSQL($record, 'graphId'), isUndefinedProperty($record, 'graphId'), propertyExistsSQL($record, 'workId'), isUndefinedProperty($record, 'workId'), propertyExistsSQL($record, 'workStartTime'), isUndefinedProperty($record, 'workStartTime'), propertyExistsSQL($record, 'workFinishTime'), isUndefinedProperty($record, 'workFinishTime'), $record->graphWorkId ]); } private function updateGraphTime($cabinet, $onDate) { $sql = <<<"EOT"
with workTime as (
select
       MCG.medCouponGraphCode,
       MIN(MCGW.startTime) "start",
       MAX(MCGW.finishTime) "finish"
from MedCouponGraph MCG
     left join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where COALESCE(MCG.onDate, 30000101) = '{$onDate}'
  and CASE COALESCE(MCGW.cabinet, '') = ''
           WHEN true THEN MCG.cabinet
           ELSE MCGW.cabinet
      END ='{$cabinet}'
group by MCG.medCouponGraphCode)

update MedCouponGraph set -- обновляем время работы кабинета
       startTime = WT."start",
       finishTime = WT."finish"
from workTime WT
where WT.medCouponGraphCode = MedCouponGraph.medCouponGraphCode
EOT;
executeSql($this->db, $sql); } private function createGraph($record) { $sql = <<<"EOT"
insert into MedCouponGraph
  (medDoctorCode, onDate, startTime, finishTime, cabinet)
values
  (?, ?, ?, ?, ?)
returning medCouponGraphCode
EOT;
return selectSqlValue($this->db, $sql, [ isEmptyProperty($record, 'doctorId'), isEmptyProperty($record, 'onDate'), isEmptyProperty($record, 'startTime'), isEmptyProperty($record, 'finishTime'), isEmptyProperty($record, 'cabinet') ]); } private function createGraphWork($record) { $sql = <<<"EOT"
insert into MedCouponGraphWork
  (medCouponGraphCode, medCouponWorkCode, startTime, finishTime, cabinet)
values
  (?, ?, ?, ?, ?)
returning medCouponGraphWorkCode
EOT;
return selectSqlValue($this->db, $sql, [ isEmptyProperty($record, 'graphId'), isEmptyProperty($record, 'workId'), isEmptyProperty($record, 'workStartTime'), isEmptyProperty($record, 'workFinishTime'), isEmptyProperty($record, 'cabinet') ]); } private function getGraphId($params) { $sql = <<<"EOT"
select medCouponGraphCode
from MedCouponGraph
where onDate = ?
  and medDoctorCode = ?
-- на всякий
order by medCouponGraphCode
limit 1
EOT;
return selectSqlValue($this->db, $sql, [ isEmptyProperty($params, 'onDate'), isEmptyProperty($params, 'doctorId') ]); } public function getVisitTime($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
      CASE COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour, 0.0) > 0.0
           WHEN true THEN 60.0 / (COALESCE(NULLIF(MCWP.visitNumberOnHour, 0.0), MJP.visitNumberOnHour) *
                                 0.01 *
                                 (100.0 + COALESCE(MD.intensity, 0.0)) * COALESCE(NULLIF(MCWP.coefficient, 0.0), 1.0))
           WHEN false THEN 0.0
       END::numeric "visitTime"
from MedCouponWork MCW
     join MedDoctor MD on MD.medDoctorCode = ?
     left join MedCouponWorkProf MCWP on MCWP.medCouponWorkCode = MCW.medCouponWorkCode
                                     and MCWP.medProfessionCode = MD.medProfessionCode
     left join MedJobVisitKind VK on VK.MedJobVisitKindCode = MCW.MedJobVisitKindCode
     left join MedJobPlan MJP on MJP.medProfessionCode = MD.medProfessionCode
                             and MJP.medJobVisitKindCode = VK.medJobVisitKindCode
where MCW.medCouponWorkCode = ?
EOT;
return selectSqlValue($db, $sql, [ isEmptyProperty($params, 'doctorId'), isEmptyProperty($params, 'workId') ]); } private function deleteEmptyGraph($graphId = null, $cabinet = null, $onDate = null) { $sqlGraphId = $graphId ? "and MCG.medCouponGraphCode = {$graphId}" : ''; $sqlDate = $onDate ? "and MCG.onDate = '{$onDate}'" : ''; $sqlCabinet = $cabinet ? "and MCG.cabinet = '{$cabinet}'" : ''; if (empty($sqlGraphId) && (empty($sqlDate) || empty($sqlCabinet))) { throw new Exception("[deleteEmptyGraph] Не заполнены параметры!"); } $sql = <<<"EOT"
with works as (
select MCG.medCouponGraphCode
from MedCouponGraph MCG
     left join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where MCGW.medCouponGraphWorkCode is null
  {$sqlGraphId}
  {$sqlDate}
  {$sqlCabinet}
)

delete
from MedCouponGraph MCG
     using works W
where MCG.medCouponGraphCode = W.medCouponGraphCode
EOT;
$this::DEBUG && app_log($sql, 0, 'workGraphCabinet-deleteEmptyGraph'); executeSql($this->db, $sql); } public function checkDoctorWorkInDay($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select EXISTS(
select MCG.medCouponGraphCode
from MedCouponGraph MCG
     join MedCouponGraphWork MCGW on MCGW.medCouponGraphCode = MCG.medCouponGraphCode
where MCG.onDate = ?
  and MCG.medDoctorCode = ?
  and MCGW.MedCouponGraphWorkCode <> ?
  and (GREATEST(MCG.startTime, ?::timestamp) <
      LEAST(MCG.finishTime, ?::timestamp))
limit 1)
EOT;
return selectSqlValue($db, $sql, [ isEmptyProperty($params, 'onDate'), isEmptyProperty($params, 'doctor'), isEmptyProperty($params, 'graphWorkId', 0), isEmptyProperty($params, 'startTime'), isEmptyProperty($params, 'finishTime') ]); } } handleJsonRpc('med_reception_workGraphCabinet'); 