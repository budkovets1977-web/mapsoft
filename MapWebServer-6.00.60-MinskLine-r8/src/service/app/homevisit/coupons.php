<?php
 require_once(__DIR__.'/../init.php'); class homevisit_coupon { const ERROR_ORDER_NO_PATIENT = -101; const ERROR_ORDER_NO_COUPON = -102; const ERROR_ORDER_NO_FREE_COUPON = -103; const ERROR_ORDER_NO_QUOTA_COUPON = -104; private $db; public function readRepeated($params) { function createObjectCoupon($stringObjectCoupon) { $words = preg_split("/[\s]+/", $stringObjectCoupon); $out_object = []; for ($i = 0; $i < count($words); $i++) { if ($words[$i] !== '') { switch ($i) { case 0: $out_object += array('id' => $words[$i]); break; case 1: $out_object += array('onDate' => Date("d.m.Y", safe_strtotime($words[$i]))); break; case 2: $out_object += array('onTime' => Date("H:i", safe_strtotime($words[$i]))); break; } } } return $out_object; } $db = $this->db ? : $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $indrepriseId = getSessionValue('mapEnterpriseCode'); $currDate = $last_date = isEmptyProperty($params, 'currDate', Date("Ymd")); $couponId = isEmptyProperty($params, 'couponId', 0); $output = []; $sql = <<<"EOT"
select MC.VisitTime "onTime"
from MedCoupon MC
inner join MedCouponWork MCW on MCW.MedCouponWorkCode = MC.MedCouponWorkCode
                            and MCW.FlagSecondReception = 1
inner join MedDoctor MD on MD.MedDoctorCode = MC.MedDoctorCode
inner join Department D on D.DepartmentCode = MD.DepartmentCode
inner join Branch B on B.BranchCode = D.BranchCode
                   and B.EnterpriseCode = $indrepriseId
where (MC.MedDoctorCode = $doctorId
  and MC.OnDate >= $currDate
  and COALESCE(MC.CouponState, 0) = 0
  and MC.VisitTime is not null)
  or MC.MedCouponCode = $couponId
group by MC.VisitTime
order by MC.VisitTime\n
EOT;
$stmtDate = $db->prepare($sql); $stmtDate->execute(); $fieldName = 'couponInfo'; $columnsTime = ''; $countColumns = 0; while ($row = $stmtDate->fetch()) { $countColumns++; $columnSql = <<<"EOT"
/* column %s */
,(select _MC.MedCouponCode || {$db->postgre("E")}' ' ||
         _MC.OnDate || {$db->postgre("E")}' ' ||
         CAST(CAST(_MC.VisitTime AS TIME) AS VARCHAR(8))
       from MedCoupon _MC
       inner join MedCouponWork _MCW on _MCW.MedCouponWorkCode = _MC.MedCouponWorkCode
                                   and _MCW.FlagSecondReception = 1
       inner join MedDoctor _MD on _MD.MedDoctorCode = _MC.MedDoctorCode
       inner join Department _D on _D.DepartmentCode = _MD.DepartmentCode
       inner join Branch _B on _B.BranchCode = _D.BranchCode
                           and _B.EnterpriseCode = %d
       where (_MC.MedDoctorCode = %d
         and _MC.OnDate = MC.onDate
         and COALESCE(_MC.CouponState, 0) = 0
         and _MC.VisitTime = {$db->postgre("E")}'%s')
         or (_MC.MedCouponCode = $couponId
             and _MC.VisitTime = {$db->postgre("E")}'%s'
             and _MC.OnDate = MC.onDate)) "%s"\n
EOT;
$columnsTime .= sprintf($columnSql, $countColumns, $indrepriseId, $doctorId, $row['onTime'], $row['onTime'], $fieldName . $countColumns); } $sql = <<<"EOT"
select {$db->sybase("top 20")}
      MC.onDate "onDate"
/* BEGIN колонки с днями, если их нет - то пусто */
$columnsTime
/* END колонки с днями, если их нет - то пусто */
from MedCoupon MC
inner join MedCouponWork MCW on MCW.MedCouponWorkCode = MC.MedCouponWorkCode
                            and MCW.FlagSecondReception = 1
inner join MedDoctor MD on MD.MedDoctorCode = MC.MedDoctorCode
inner join Department D on D.DepartmentCode = MD.DepartmentCode
inner join Branch B on B.BranchCode = D.BranchCode
                   and B.EnterpriseCode = $indrepriseId
where (MC.MedDoctorCode = $doctorId
    and MC.OnDate >= $currDate
  and COALESCE(MC.CouponState, 0) = 0
  and MC.VisitTime is not null)
  or MC.MedCouponCode = $couponId
group by MC.onDate
order by MC.onDate
{$db->postgre("limit 20")}\n
EOT;
$stmtGeneral = $db->prepare($sql); $stmtGeneral->execute(); while ($row = $stmtGeneral->fetch()) { $tmp_array_data = []; $tmp_array_data += array('onDate' => Date("d.m.Y", safe_strtotime($row['onDate']))); $cnt_col = 0; for ($i = 0; $i < $countColumns; $i++) { $cnt_row = 0; if (createObjectCoupon($row[$fieldName . (($i + 1) + (5 * $cnt_row))])) { $cnt_col++; $tmp_array_data += array($fieldName . $cnt_col => createObjectCoupon($row[$fieldName . (($i + 1) + (5 * $cnt_row))])); } if ($cnt_col === 5) { $cnt_col = 0; $cnt_row++; $output["data"][] = $tmp_array_data; $tmp_array_data = []; } } if (count($tmp_array_data)) { $output["data"][] = $tmp_array_data; } } return $output; } public function readProfessionsDirection($pid = 0) { $db = $this->db ? : $this->db = connectDb(); $doctorId = (int) getSessionValue('mapMedDoctor')['MedDoctorCode']; $professionId = (int) getSessionValue('mapMedDoctor')['MedProfessionCode']; $departmentId = (int) getSessionValue('mapMedDoctor')['DepartmentCode']; $res = selectSql($db, <<<"EOT"
select
    q.MedProfessionCode as "id",
    p.Name as "name",
    min(q.MedCouponWorkCode) as "medCouponWorkId",
    max(qp.Percent) as "quotaPercent"
from MedCouponQuota as q
join MedProfession as p on p.MedProfessionCode = q.MedProfessionCode
join MedCouponQuotaPercent as qp on qp.MedCouponQuotaCode = q.MedCouponQuotaCode
where (qp.MedDoctorCode is null or QP.MedDoctorCode = :doctorId)
    and (qp.MedProfessionCode is null or QP.MedProfessionCode = :professionId)
    and (qp.DepartmentCode is null or qp.DepartmentCode = :departmentId)
    and (:pid = 0 or :pid = q.MedProfessionCode)
group by 1, 2
order by 2
EOT,
[ 'doctorId' => $doctorId, 'professionId' => $professionId, 'departmentId' => $departmentId, 'pid' => $pid ] ); if ($pid > 0) { $res = reset($res); } return $res; } public function readDirection($params) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = (int) getSessionValue('mapEnterpriseCode'); $visitDate = (string) isEmptyProperty($params, 'visitDate', Date("Y-m-d")); $toProfessionId = (int) isEmptyProperty($params, 'professionId', 0); $toDoctorId = (int) isEmptyProperty($params, 'doctorId', 0); $profs = $this->readProfessionsDirection($toProfessionId); $toMedCouponWorkId = (int) isEmptyArrayValue($profs, 'medCouponWorkId', 0); $toQuotaPercent = (float) isEmptyArrayValue($profs, 'quotaPercent', 0.0); $times = selectSql( $db, <<<"EOT"
select
    c.VisitTime as "onTime"
from MedCoupon as c
join MedDoctor as d on d.MedDoctorCode = c.MedDoctorCode
join Department as dep on dep.DepartmentCode = d.DepartmentCode
join Branch as b on b.BranchCode = dep.BranchCode
    and b.EnterpriseCode = :enterpriseId
where coalesce(c.MedCouponWorkCode, 0) = :toMedCouponWorkId
    and coalesce(c.CouponState, 0 ) = 0
    and d.MedProfessionCode = :toProfessionId
    and (:toDoctorId = 0 or c.medDoctorCode = :toDoctorId)
    and c.OnDate >= :visitDate
    and c.VisitTime is not null
group by c.VisitTime
order by 1
EOT,
[ 'enterpriseId' => $enterpriseId, 'toMedCouponWorkId' => $toMedCouponWorkId, 'toProfessionId' => $toProfessionId, 'toDoctorId' => $toDoctorId, 'visitDate' => $visitDate ] ); if (!$times) { return []; } $columnsTime = ''; $countColumns = 0; foreach ($times as $t) { $countColumns++; $columnsTime .= <<<"EOT"
    /* column #$countColumns */
    , (
        select
            json_build_object(
                'id', _c.MedCouponCode,
                'onDate', _c.OnDate,
                'onTime', cast(_c.VisitTime as time),
                'doctorId', _c.MedDoctorCode,
                'doctorName', fn_cmFio(_d.ManCode)
            )
        from MedCoupon as _c
        join MedDoctor as _d on _d.MedDoctorCode = _c.MedDoctorCode
        join Department as _dep on _dep.DepartmentCode = _d.DepartmentCode
        join Branch as _b on _b.BranchCode = _dep.BranchCode
            and _b.EnterpriseCode = :enterpriseId
        where coalesce(_c.MedCouponWorkCode, 0) = :toMedCouponWorkId
            and coalesce(_c.CouponState, 0 ) = 0
            and _d.MedProfessionCode = :toProfessionId
            and (:toDoctorId = 0 or _c.medDoctorCode = :toDoctorId)
            and _c.OnDate = c.OnDate
            and _c.VisitTime = '{$t['onTime']}'
        order by _c.MedCouponCode
        limit 1
    ) as "data$countColumns"\n
EOT;
} $rows = selectSql( $db, <<<"EOT"
select
    c.OnDate as "onDate",
    fn_cmRounding(
        coalesce((
            select
                count(_c.MedCouponCode)
            from MedCoupon as _c
            join MedDoctor as _d on _d.MedDoctorCode = _c.MedDoctorCode
            join Department as _dep on _dep.DepartmentCode = _d.DepartmentCode
            join Branch as _b on _b.BranchCode = _dep.BranchCode
                and _b.EnterpriseCode = :enterpriseId
            where _c.MedCouponWorkCode = c.MedCouponWorkCode
                and coalesce(_c.CouponState, 0 ) <> 3
                and _d.MedProfessionCode = d.MedProfessionCode
                and _c.OnDate = c.OnDate
            ), 0) * $toQuotaPercent * 0.01, 1, 1) -
        coalesce((
            select
                count(ls.MedCouponCode)
            from MedLogSend as ls
            join MedCoupon as _c on _c.MedCouponCode = ls.MedCouponCode
            where /*ls.MedDoctorCode = :doctorId
                and*/ ls.MedProfessionCode = d.MedProfessionCode
                and ls.MedCouponWorkCode = c.MedCouponWorkCode
                and _c.OnDate = c.OnDate
    ), 0) as "restCount"
    /* BEGIN колонки с днями, если их нет - то пусто */
    $columnsTime
    /* END колонки с днями, если их нет - то пусто */
from MedCoupon as c
join MedDoctor as d on d.MedDoctorCode = c.MedDoctorCode
join Department as dep on dep.DepartmentCode = d.DepartmentCode
join Branch as b on b.BranchCode = dep.BranchCode
    and b.EnterpriseCode = :enterpriseId
where coalesce(c.MedCouponWorkCode, 0) = :toMedCouponWorkId
    and coalesce(c.CouponState, 0 ) = 0
    and d.MedProfessionCode = :toProfessionId
    and (:toDoctorId = 0 or c.medDoctorCode = :toDoctorId)
    and c.OnDate > :visitDate
    and c.VisitTime is not null
group by c.OnDate, d.MedProfessionCode, c.MedCouponWorkCode
order by 1
limit 60
EOT,
[ 'enterpriseId' => $enterpriseId, 'toMedCouponWorkId' => $toMedCouponWorkId, 'toProfessionId' => $toProfessionId, 'toDoctorId' => $toDoctorId, 'visitDate' => $visitDate, ] ); if (!$rows) { return []; } $res = []; foreach ($rows as $r) { $arr = array( 'onDate' => $r['onDate'] ); $cnt_col = 0; $empty_added = false; for ($i = 0; $i < $countColumns; $i++) { $cnt_row = 0; if ($cnt_col == 0) { $arr += array( 'restCount' => (int) $r['restCount'] ); } $col_data = (string) $r['data'.(($i + 1) + (5 * $cnt_row))]; if ($col_data) { $col_data = json_clean_decode($col_data); if ((int) $r['restCount'] > 0 || !$empty_added) { $cnt_col++; if ((int) $r['restCount'] <= 0) { $empty_added = true; } $arr += array('data'.$cnt_col => $col_data); } } if ($cnt_col === 5) { $cnt_col = 0; $cnt_row++; $res[] = $arr; $arr = []; } } if (count($arr)) { $res[] = $arr; } } return $res; } public function orderDirection($params) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = (int) getSessionValue('mapEnterpriseCode'); $doctorId = (int) getSessionValue('mapMedDoctor')['MedDoctorCode']; $onDate = (string) isEmptyProperty($params, 'onDate', Date("Y-m-d")); $patientId = (int) isEmptyProperty($params, 'patientId', 0); $couponId = (int) isEmptyProperty($params, 'couponId', 0); $toProfessionId = (int) isEmptyProperty($params, 'professionId', 0); $profs = $this->readProfessionsDirection($toProfessionId); $toMedCouponWorkId = (int) isEmptyArrayValue($profs, 'medCouponWorkId', 0); $toQuotaPercent = (float) isEmptyArrayValue($profs, 'quotaPercent', 0.0); if (!$patientId) { throw new Exception("Пациент не указан!", self::ERROR_ORDER_NO_PATIENT); } $coupon = selectSql( $db, 'select MedCouponCode as "id", coalesce(CouponState, 0) as "state" '. 'from MedCoupon where MedCouponCode = :id limit 1', ['id' => $couponId] ); if (!$coupon || empty($coupon)) { throw new Exception("Талон не найден!", self::ERROR_ORDER_NO_COUPON); } $coupon = reset($coupon); if ($coupon['state'] != 0) { throw new Exception("Талон уже ".($coupon['state'] == 1 ? 'заказан' : ($coupon['state'] == 2 ? 'выдан' : 'отменен'))."!", self::ERROR_ORDER_NO_FREE_COUPON); } $restCount = (int) selectSqlValue( $db, <<<"EOT"
select
    fn_cmRounding(
        coalesce((
            select
                count(_c.MedCouponCode)
            from MedCoupon as _c
            join MedDoctor as _d on _d.MedDoctorCode = _c.MedDoctorCode
            join Department as _dep on _dep.DepartmentCode = _d.DepartmentCode
            join Branch as _b on _b.BranchCode = _dep.BranchCode
                and _b.EnterpriseCode = :enterpriseId
            where _c.MedCouponWorkCode = c.MedCouponWorkCode
                and coalesce(_c.CouponState, 0 ) <> 3
                and _d.MedProfessionCode = d.MedProfessionCode
                and _c.OnDate = c.OnDate
            ), 0) * $toQuotaPercent * 0.01, 1, 1) -
        coalesce((
            select
                count(ls.MedCouponCode)
            from MedLogSend as ls
            join MedCoupon as _c on _c.MedCouponCode = ls.MedCouponCode
            where /*ls.MedDoctorCode = :doctorId
                and*/ ls.MedProfessionCode = d.MedProfessionCode
                and ls.MedCouponWorkCode = c.MedCouponWorkCode
                and _c.OnDate = c.OnDate
    ), 0) as "restCount"
from MedCoupon as c
join MedDoctor as d on d.MedDoctorCode = c.MedDoctorCode
join Department as dep on dep.DepartmentCode = d.DepartmentCode
join Branch as b on b.BranchCode = dep.BranchCode
    and b.EnterpriseCode = :enterpriseId
where c.MedCouponCode = :couponId
EOT,
[ 'enterpriseId' => $enterpriseId, 'couponId' => $couponId ] ); if ($restCount <= 0) { throw new Exception("Заказ на этот день невозможен, исчерпана квота!", self::ERROR_ORDER_NO_FREE_COUPON); } $db->beginTransaction(); try { executeSql( $db, "update MedCoupon set ". " MedPatientCode = :patientId, ". " CouponState = 1, ". " OrderTime = now(), ". " SelfRecording = 0 ". "where MedCouponCode = :couponId ", [ 'patientId' => $patientId, 'couponId' => $couponId ] ); executeSql( $db, <<<"EOT"
insert into MedLogSend (
    DocumentDate,
    DocumentNumber,
    EnterpriseCode,
    MedCouponCode,
    MedCouponWorkCode,
    MedDoctorCode,
    MedPatientCode,
    MedProfessionCode,
    SendType
) values (
    cast(:onDate as date),
    trim(coalesce((
        select IncStr(repeat(' ', 20 - length(DocumentNumber)) || DocumentNumber)
        from MedLogSend
        where date_part('year', DocumentDate) = date_part('year', cast(:onDate as date))
        order by repeat(' ', 20 - length(DocumentNumber)) || DocumentNumber desc
        limit 1
        ), '1')),
    :enterpriseId,
    :couponId,
    :toMedCouponWorkId,
    :doctorId,
    :patientId,
    :toProfessionId,
    1
)
EOT,
[ 'onDate' => $onDate, 'enterpriseId' => $enterpriseId, 'couponId' => $couponId, 'toMedCouponWorkId' => $toMedCouponWorkId, 'doctorId' => $doctorId, 'patientId' => $patientId, 'toProfessionId' => $toProfessionId ] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } function freeDirection($couponId) { $db = $this->db ? : $this->db = connectDb(); $couponId = (int) $couponId; if (!$couponId) { throw new Exception("Талон не найден!", self::ERROR_ORDER_NO_COUPON); } $db->beginTransaction(); try { executeSql( $db, "update MedCoupon set ". " MedPatientCode = null, ". " FIO = null, ". " OrderTime = null, ". " CouponState = 0, ". " GiveTime = null ". "where MedCouponCode = :couponId ", [ 'couponId' => $couponId ] ); executeSql( $db, "delete from MedLogSend where MedCouponCode = :couponId", [ 'couponId' => $couponId ] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } $service = new Junior\Server(new homevisit_coupon()); $service->process();