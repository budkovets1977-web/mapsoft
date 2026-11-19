<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_coupons { const ERROR_COUPON_ALREADY_FREE = 0; const ERROR_COUPON_ALREADY_ORDERED = -1; const ERROR_COUPON_ALREADY_GIVED = -2; const ERROR_COUPON_ALREADY_CANCELED = -3; const ERROR_COUPON_ORDER_EMPTY_DOCTOR = -10; const ERROR_NO_COUPON = -666; private $db; public function readCoupons($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $id = isEmptyProperty($params, 'id', 0); $onDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate', Date('Ymd')))); $isCombo = isEmptyProperty($params, 'isCombo', 0); $doctorId = isEmptyProperty($params, 'doctorId', 0); $paramDoctors = isUndefinedProperty($params, 'doctors'); $patientId = isEmptyProperty($params, 'patientId', 0); $state = isUndefinedProperty($params, 'state', -1); $jobId = isEmptyProperty($params, 'jobId', 0); $flagSecondReception = isEmptyProperty($params, 'flagSecondReception', 0); $limit = isEmptyProperty($params, 'limit', 40); $cityInAddress = isUndefinedProperty($params, 'cityInAddress'); $typeOut = $cityInAddress ? 0 : 3; if ($doctorId) { $paramDoctors = is_array($paramDoctors) ? array_merge($paramDoctors, [$doctorId]) : [$doctorId]; } $doctorsCondition = createSqlInCondition($paramDoctors); $doctorsCount = !$paramDoctors ? $patientId == 0 ? 1 : 0 : count($paramDoctors); $fromWhereSql = <<<"EOT"
FROM MedCoupon AS c
JOIN MedCouponWork mcw ON mcw.MedCouponWorkCode = c.MedCouponWorkCode AND (0 = $flagSecondReception OR mcw.FlagSecondReception = 1)
JOIN MedDoctor AS doc ON doc.MedDoctorCode = c.MedDoctorCode
JOIN Department AS dep ON dep.DepartmentCode = doc.DepartmentCode
    and fn_WebUserAccessDepartment(dep.DepartmentCode) = 1
JOIN Branch AS br ON br.BranchCode = dep.BranchCode AND br.EnterpriseCode = $enterpriseCode
LEFT JOIN MedPatient AS p ON p.MedPatientCode = c.MedPatientCode
LEFT JOIN ManPhone AS mnp ON mnp.ManCode = p.ManCode
LEFT JOIN MedPatientGroup AS pg ON pg.MedPatientGroupCode = p.MedPatientGroupCode
LEFT JOIN MedPatientGroup AS pg2 ON pg2.MedPatientGroupCode = doc.MedPatientGroupCode
/*LEFT JOIN Enterprise AS IVE ON IVE.EnterpriseCode = c.EnterpriseCodeWhoDelivered*/
LEFT JOIN MedProfession AS mp on mp.MedProfessionCode = doc.MedProfessionCode
LEFT JOIN medCouponWork cw ON cw.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
WHERE (c.MedCouponCode = $id) OR ($id = 0
  AND ((0 = $isCombo AND c.OnDate >= $onDate::date AND c.OnDate <= DATEADD('MONTH', 6, $onDate)::date)
  OR (1 = $isCombo AND c.OnDate = $onDate))
  AND ($doctorsCount = 0 OR c.MedDoctorCode $doctorsCondition)
  AND ($patientId = 0 OR c.MedPatientCode = $patientId OR (COALESCE(c.CouponState, 0) = 0 AND $flagSecondReception = 1))
  AND ($jobId = 0 OR (CASE WHEN c.MedCouponWorkCodeChange IS NULL THEN c.MedCouponWorkCode = $jobId ELSE c.MedCouponWorkCodeChange = $jobId END))
  AND ($state = -1 OR COALESCE(c.CouponState, 0) = $state))
EOT;
$total = 0; if (!$id) { $sqlTotal = <<<"EOT"
SELECT COUNT(*) AS "total"
$fromWhereSql
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); } $comboField=''; if ($isCombo) { $orderBy = "CASE WHEN COALESCE(c.visitTime, ca.visitTime) IS NULL THEN 1 ELSE 0 END, COALESCE(c.visitTime, ca.visitTime) ASC, c.medCouponCode ASC"; $comboField = 'COALESCE(to_char(c.visitTime, \'HH24:MI\'), to_char(ca.visitTime, \'HH24:MI\'), (\'№\' || rank() OVER (PARTITION BY COALESCE(c.visitTime, ca.visitTime) ORDER BY c.MedCouponCode ASC))::text) AS "visitTimeDop",'; } else { $orderBy = "c.OnDate ASC, mp.Name /*doc.MedProfessionCode*/, c.VisitTime ASC, c.OrderTime ASC"; } $sql = <<<"EOT"
SELECT T.*,
       FN_cmFIO(T."doctorManId", 1) AS "doctor",
       FN_cmFIO(T."patientManId") AS "patient",
       FN_cmFIO(T."patientManId", 0) "printPatient",
       FN_cmCalcAddress(T."patientManId", 5, $typeOut)::TEXT AS "address",
       FN_cmCalcAddress(T."patientManId", 5, 0)::TEXT "printAddress",
        ( SELECT Name
            FROM MedCouponWork
            WHERE MedCouponWorkCode = (
                CASE WHEN T."jobChangeId" IS NOT NULL
                         AND EXISTS (
                             SELECT 1
                             FROM MedCouponWork
                             WHERE MedCouponWorkCode = T."jobChangeId" AND flagcancel = 1
                         ) THEN T."jobId"
                    ELSE COALESCE(T."jobChangeId", T."jobId") END) LIMIT 1
        ) AS job,
       (SELECT '<b>Кабинет:</b> ' || COALESCE(wg.Cabinet, T."cabinet") || ' <b>Время работы:</b> ' || wg.StartTime || '-' || wg.FinishTime || ' ' || wg.graph
        FROM (
        SELECT COALESCE(NULLIF(mpgw.Cabinet,''), NULLIF(mpg.Cabinet,'')) Cabinet, -- 1462
               CAST(CAST(mpg.StartTime AS TIME) AS VARCHAR(5)) StartTime,
               CAST(CAST(mpg.FinishTime AS TIME) AS VARCHAR(5)) FinishTime,
               string_agg('<b>' || mcw.Name || ':</b> ' || CAST(CAST(mpgw.StartTime AS TIME) AS VARCHAR(5)) || '-' || CAST(CAST(mpgw.FinishTime AS TIME) AS VARCHAR(5)), ' ' ORDER BY mpgw.StartTime, mcw.Name) graph
        FROM MedCouponGraph mpg
        JOIN MedCouponGraphWork mpgw ON mpgw.MedCouponGraphCode = mpg.MedCouponGraphCode
        JOIN MedCouponWork mcw ON mcw.MedCouponworkCode = mpgw.MedCouponworkCode  AND mpgw.MedCouponWorkCode = T."jobId"
        WHERE mpg.OnDate = T."onDate" AND mpg.MedDoctorCode = T."doctorId"
        GROUP BY 1, 2, 3) wg
        )::text "timeTable",
        (
            SELECT MIN(mpgw.StartTime)
            FROM MedCouponGraph mpg
            JOIN MedCouponGraphWork mpgw ON mpgw.MedCouponGraphCode = mpg.MedCouponGraphCode AND mpgw.MedCouponWorkCode = T."jobId"
            WHERE mpg.OnDate = T."onDate" AND mpg.MedDoctorCode = T."doctorId"
        )                                   "startTime",
        (
            SELECT MAX(mpgw.FinishTime)
            FROM MedCouponGraph mpg
            JOIN MedCouponGraphWork mpgw ON mpgw.MedCouponGraphCode = mpg.MedCouponGraphCode AND mpgw.MedCouponWorkCode = T."jobId"
            WHERE mpg.OnDate = T."onDate" AND mpg.MedDoctorCode = T."doctorId"
        )                                   "finishTime",
        (
            SELECT MIN(MPG.StartTime)
            FROM MedCouponGraph MPG
            JOIN MedCouponGraphWork MPGW ON MPGW.MedCouponGraphCode = MPG.MedCouponGraphCode
            WHERE MPG.OnDate = T."onDate" AND MPG.MedDoctorCode = T."doctorId"
        )                                   "cabinetWorkStartTime",
        (
            SELECT MAX(MPG.FinishTime)
            FROM MedCouponGraph MPG
            JOIN MedCouponGraphWork MPGW ON MPGW.MedCouponGraphCode = MPG.MedCouponGraphCode
            WHERE MPG.OnDate = T."onDate" AND MPG.MedDoctorCode = T."doctorId"
        )                                   "cabinetWorkFinishTime",
        (
            SELECT COUNT(*) FROM MedCoupon
            WHERE MedDoctorCode = T."doctorId"
            AND OnDate = T."onDate"
            AND ( VisitTime <= COALESCE(T."visitTime", E'3000-1-1') OR T."visitTime" IS NULL AND MedCouponCode <= T."id" )
        )                                   "couponNumber",
        (
            select DocumentNumber
            from MedEqQueueCoupon
            where MedCouponCode = t.id
            limit 1
        )                                   "eqCouponNumber",
        NOW()::timestamp                    "dateTimeNow"
FROM (
SELECT
  c.MedCouponCode AS "id",
  c.OnDate AS "onDate",
  $comboField
  c.VisitTime AS "visitTime",
  CASE WHEN c.visitTime IS NULL THEN 1 ELSE 0 END
                                                            "isExtraTalon",
  c.MedDoctorCode AS "doctorId",
  doc.ManCode AS "doctorManId",
  mp.MedProfessionCode AS "professionId",
  mp.Name AS "doctorProfession",
  c.MedPatientCode AS "patientId",
  p.ManCode AS "patientManId",
  c.CouponState AS "state",
  COALESCE(c.SelfRecording, 0) AS "selfRecording",
  COALESCE(c.CouponType, 0) AS "type",
  c.MedCouponWorkCode AS "jobId",
  c.MedCouponWorkCodeChange AS "jobChangeId",
  c.OrderTime AS "orderTime",
  c.GiveTime AS "giveTime",
  CASE ($patientId = 0)
    WHEN TRUE THEN
      CASE ($doctorsCount = 1)
        WHEN TRUE THEN 2
        WHEN FALSE THEN 0
      END
    WHEN FALSE THEN 1
  END AS "viewKind",
  c.FIO AS "fio",
  (select string_agg((concat(E', ', case
when mpn.phoneType=1 then 'д'
when mpn.phoneType=2 then 'р'
when mpn.phoneType=3 then 'м'
when mpn.phoneType=4 then 'п'
else null
end, E'.' ,mpn.phoneNumber))::text, E'' ) from manphone AS mpn where mpn.ManCode=p.ManCode) as contact,
  c.Note AS "note",
  -- doc.Cabinet 1462
  COALESCE((SELECT COALESCE(NULLIF(mpgw.cabinet,''), NULLIF(mpg.cabinet,''))
   FROM MedCouponGraph mpg
     LEFT JOIN MedCouponGraphWork mpgw ON mpgw.MedCouponGraphCode = mpg.MedCouponGraphCode
        AND mpgw.MedCouponWorkCode = COALESCE(c.MedCouponWorkCodeChange, c.MedCouponWorkCode)
   WHERE mpg.OnDate = c.OnDate AND mpg.MedDoctorCode = c.MedDoctorCode
   LIMIT 1), doc.Cabinet) AS "cabinet",
  p.NumberHistorySickness AS "numberHistorySickness",
  pg.Name AS "sectorName",
  pg2.Name AS "doctorSectorName",
  cw.iconIndex "iconIndex",
  cw.flagcancel "flagcancel"
/*
 (SELECT CAST(string_agg((CASE Ph.PhoneType = 1 WHEN true THEN  'д.' WHEN false THEN  'р.' END || ' ' ||Ph.Phonenumber)::text,  ',' ) as varchar(254))
 FROM ManPhone AS Ph WHERE Ph.ManCode = p.ManCode ) as phones,
  p.NumberHistorySickness,
  (SELECT COUNT(*) FROM MedCoupon
   WHERE MedDoctorCode = c.MedDoctorCode
   AND OnDate = c.OnDate
   AND ( VisitTime <= (COALESCE( c.VisitTime, 30000101 ) )OR c.VisitTime IS NULL AND MedCouponCode <= (c.MedCouponCode ))
  ) AS CouponNumber,
  CASE c.InitialDate IS NULL WHEN true THEN 0 WHEN false THEN 1 END as InitialVisitFlag,
  c.InitialDate as InitialDate,
  c.EnterpriseCodeWhoDelivered as EnterpriseCodeWhoDelivered,
  COALESCE(IVE.FullName,IVE.Name) as EnterpriseNameWhoDelivered,
  CASE (p.OutReason IS NOT NULL AND p.FinishDate <= $onDate::date) WHEN true THEN 1 WHEN false THEN NULL END AS OutSign,
  ShortNumber
*/
$fromWhereSql
ORDER BY $orderBy
LIMIT $limit) AS T
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } private function getCouponInternal($id) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                   c.couponState "state",
                   c.medPatientCode "patientId",
                   (c.medPatientCode IS NOT NULL OR (c.fio IS NOT NULL AND c.fio <>'')) as "hasPatient"
            FROM medCoupon c
            WHERE c.medCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); if (!($coupon = $stmt->fetch())) { throw new Exception("Неверный идентификатор талона ($id)!", self::ERROR_NO_COUPON); } return $coupon; } public function insertOrUpdateCoupons($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $noRet = false; $ret = []; $couponIds = []; $sql = <<<EOT
            INSERT INTO medCoupon (
                medCouponCode, onDate, visitTime,
                medDoctorCode, medPatientCode, couponState,
                selfRecording, couponType, medCouponWorkCode,
                medCouponWorkCodeChange, orderTime, giveTime,
                fio, Note, triggerFlag)
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?)
            ON CONFLICT (medCouponCode) DO UPDATE SET
                onDate = CASE WHEN ? = 1 THEN EXCLUDED.onDate ELSE medCoupon.onDate END,
                visitTime = CASE WHEN ? = 1 THEN EXCLUDED.visitTime ELSE medCoupon.visitTime END,
                medDoctorCode = CASE WHEN ? = 1 THEN EXCLUDED.medDoctorCode ELSE medCoupon.medDoctorCode END,
                medPatientCode = CASE WHEN ? = 1 THEN EXCLUDED.medPatientCode ELSE medCoupon.medPatientCode END,
                couponState = CASE WHEN ? = 1 THEN EXCLUDED.couponState ELSE medCoupon.couponState END,
                selfRecording = CASE WHEN ? = 1 THEN EXCLUDED.selfRecording ELSE medCoupon.selfRecording END,
                couponType = CASE WHEN ? = 1 THEN EXCLUDED.couponType ELSE medCoupon.couponType END,
                medCouponWorkCode = CASE WHEN ? = 1 THEN EXCLUDED.medCouponWorkCode ELSE medCoupon.medCouponWorkCode END,
                medCouponWorkCodeChange = CASE WHEN ? = 1 THEN EXCLUDED.medCouponWorkCodeChange ELSE medCoupon.medCouponWorkCodeChange END,
                orderTime = CASE WHEN ? = 1 THEN EXCLUDED.orderTime ELSE medCoupon.orderTime END,
                giveTime = CASE WHEN ? = 1 THEN EXCLUDED.giveTime ELSE medCoupon.giveTime END,
                fio = CASE WHEN ? = 1 THEN EXCLUDED.fio ELSE medCoupon.fio END,
                note = CASE WHEN ? = 1 THEN EXCLUDED.note ELSE medCoupon.note END,
                triggerFlag = CASE WHEN ? = 1 THEN EXCLUDED.triggerFlag ELSE medCoupon.triggerFlag END
            RETURNING medCouponCode id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $isMass = count($records) > 1; $db->beginTransaction(); try { foreach ($records as $row) { $newState = isUndefinedProperty($row, 'state', 0); $hasState = property_exists($row, 'state'); if ($isInsert) { if (!isUndefinedProperty($row, 'doctorId')) { throw new Exception('Для дополнительного талона не указан доктор', self::ERROR_COUPON_ORDER_EMPTY_DOCTOR); } } else { $sqlLock = 'SELECT 1 FROM medCoupon WHERE medCouponCode = ? FOR UPDATE'; $db->prepare($sqlLock)->execute([isUndefinedProperty($row, 'id')]); $coupon = $this->getCouponInternal(isUndefinedProperty($row, 'id')); switch ($newState) { case 0: if ($coupon['state'] == 0) { throw new Exception('Талон уже свободен', self::ERROR_COUPON_ALREADY_FREE); } if ($hasState && $newState === 0) { $sql = "DELETE FROM MedSanProcLogRowCoupon WHERE MedCouponCode = ?"; $stmtDel = $db->prepare($sql); $stmtDel->execute([$row->id]); } break; case 1: if ($coupon['state'] == 1 && $coupon['hasPatient']) { throw new Exception('Талон уже заказан', self::ERROR_COUPON_ALREADY_ORDERED); } if ($coupon['state'] == 2) { throw new Exception('Талон уже выдан', self::ERROR_COUPON_ALREADY_GIVED); } if ($coupon['state'] == 3) { throw new Exception('Талон уже отменен', self::ERROR_COUPON_ALREADY_CANCELED); } break; case 2: if ($coupon['state'] == 3) { throw new Exception('Талон уже отменен', self::ERROR_COUPON_ALREADY_CANCELED); } break; case 3: if ($coupon['state'] == 3) { throw new Exception('Талон уже отменен', self::ERROR_COUPON_ALREADY_CANCELED); } break; } if ($newState == $coupon['state'] && $coupon['hasPatient']) { throw new Exception('Талон уже заказан', self::ERROR_COUPON_ALREADY_ORDERED); } } if ($isInsert && (!propertyExistsSQL($row, 'orderTime') || is_null($row->orderTime))) { $row->orderTime = date('Y-m-d H:i:s'); } $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'onDate'), isUndefinedProperty($row, 'visitTime'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'state'), isUndefinedProperty($row, 'selfRecording'), isUndefinedProperty($row, 'type'), isUndefinedProperty($row, 'jobId'), isUndefinedProperty($row, 'jobChangeId'), isUndefinedProperty($row, 'orderTime'), isUndefinedProperty($row, 'giveTime'), isUndefinedProperty($row, 'fio'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'triggerFlag'), propertyExistsSQL($row, 'onDate'), propertyExistsSQL($row, 'visitTime'), propertyExistsSQL($row, 'doctorId'), propertyExistsSQL($row, 'patientId'), propertyExistsSQL($row, 'state'), propertyExistsSQL($row, 'selfRecording'), propertyExistsSQL($row, 'type'), propertyExistsSQL($row, 'jobId'), propertyExistsSQL($row, 'jobChangeId'), propertyExistsSQL($row, 'orderTime'), propertyExistsSQL($row, 'giveTime'), propertyExistsSQL($row, 'fio'), propertyExistsSQL($row, 'note'), propertyExistsSQL($row, 'triggerFlag') ]); $coupon = $this->readCoupons((object)["id" => fetchValue($stmt, 'id')])->items[0]; $ret[] = $coupon; $couponIds[] = $coupon['id']; if ($newState == 2) { $this->processEq($coupon); } $extraTalonVisitTime = isUndefinedProperty($row, 'extraTalonVisitTime'); $extraTime = isUndefinedProperty($row, 'extraTime'); if (!empty($extraTalonVisitTime) || $extraTime) { $noRet = true; $row->extraTalonId = $coupon['id']; $this->processExtraTalon($row); } } $this->createTalonMapUpdateNotice($db); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if ($noRet) { $ret = null; } return $ret; } private function createTalonMapUpdateNotice($db) { $talonMap = new med_reception_talonMap(); $talonMap->createNotice($db); } private function processExtraTalon($talon) { $db = $this->db ?: $this->db = connectDb(); $extraTime = isUndefinedProperty($talon, 'extraTime'); $extraTalonVisitTime = $extraTime ? null : isUndefinedProperty($talon, 'extraTalonVisitTime'); $sql = <<<EOT
            WITH medCouponAddId AS (
                SELECT medCouponAddCode id
                FROM medCouponAdd
                WHERE medCouponCode = ?
                LIMIT 1
            )
            INSERT INTO medCouponAdd (medCouponAddCode, medCouponCode, visitTime, cabinet)
            VALUES ((SELECT id FROM medCouponAddId), ?, ?, ?)
            ON CONFLICT (medCouponAddCode) DO UPDATE SET
                visitTime = CASE WHEN ? = 1 THEN EXCLUDED.visitTime ELSE medCouponAdd.visitTime END,
                cabinet = CASE WHEN ? = 1 THEN EXCLUDED.cabinet ELSE medCouponAdd.cabinet END
EOT;
$stmt = $db->prepare($sql); if (!empty($extraTalonVisitTime) || $extraTime) { $stmt->execute([ isUndefinedProperty($talon, 'extraTalonId'), isUndefinedProperty($talon, 'extraTalonId'), isUndefinedProperty($talon, 'extraTalonVisitTime'), isUndefinedProperty($talon, 'cabinet'), propertyExistsSQL($talon, 'extraTalonVisitTime'), propertyExistsSQL($talon, 'cabinet') ]); } } public function createCoupons($records) { return $this->insertOrUpdateCoupons($records, true); } public function updateCoupons($records) { return $this->insertOrUpdateCoupons($records, false); } public function swapCoupons($params) { $delete = new stdClass(); $delete->id = isUndefinedProperty($params, 'oldCoupon'); $delete->state = 0; $delete->patientId = null; $delete->orderTime = null; $delete->triggerFlag = null; $whiteList = ['id', 'orderTime', 'state', 'patientId', 'fio']; $record = new stdClass(); $paramsList = get_object_vars($params); foreach ($paramsList as $field => $Item) { if (in_array($field,$whiteList)){ $record->$field = $params->$field; } } try { $this->updateCoupons($record); } catch (Throwable $e){ throw $e; } return $this->updateCoupons($delete); } public function deleteCoupons($records) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $result = deleteSql($this->db, 'medCoupon', 'medCouponCode', $records, 'id', false); $this->createTalonMapUpdateNotice($db); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function checkUsageQuota($onDate, $doctorId, $jobId, $professionId) { if (!$onDate || !$doctorId || !$jobId || !$professionId) { throw new Exception("В функцию проверки превышения квот не переданы все необходимые параметры!", 1); } $db = $this->db ?: $this->db = connectDb(); $onDateObj = new DateTime($onDate); $onDateParam = $onDateObj->format("Ymd"); $sql = <<<"EOT"
WITH COUPON_STATE AS (
        SELECT SUM(
          CASE COALESCE(CouponState, 0) IN (1, 2) AND DATE(OrderTime) < DATEADD('DAY', -1, OnDate)
            WHEN TRUE THEN 1
            WHEN FALSE THEN 0
          END) AS CountPrior,
          COUNT(*) AS CountAll
        FROM MedCoupon
        WHERE MedDoctorCode = $doctorId
          AND MedCouponWorkCode = $jobId
          AND OnDate = $onDateParam
          AND COALESCE(CouponState, 0) <> (3)),
     COUPON_QUOTE AS(
        SELECT OrderPercent
        FROM MedCouponControl
        WHERE MedCouponWorkCode = $jobId
          AND MedProfessionCode = $professionId)

SELECT CASE CountPrior >= (ROUND(CountAll * (SELECT OrderPercent FROM COUPON_QUOTE) * 0.01, 0))
           WHEN TRUE THEN 1
           WHEN FALSE THEN 0
       END
FROM COUPON_STATE
EOT;
$res = selectSqlValue($db, $sql); return $res ? true : false; } public function readDoctorsAndSpecialties($params) { $db = $this->db ?: $this->db = connectDb(); $find = isUndefinedProperty($params, 'find'); if ($find) { $query = mb_ereg_replace("[\s]+", ":* & ", trim($find)) . ":*"; $sql = <<<"EOT"
SELECT DISTINCT asa_trim(Authority) as "id"
FROM ManPassport
WHERE Authority IS NOT NULL
  AND Authority <> ''
  AND to_tsvector(Authority) @@ to_tsquery('{$query}')
--GROUP BY upper(asa_trim(Authority)), "id"
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } else { return []; } } public function getPatientPreventInfo($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $patientId = isEmptyProperty($params, 'patientId'); $ret = new stdClass(); $sql = <<<"EOT"
select
    FullName "enterpriseName"
from Enterprise
where enterpriseCode = $enterpriseCode;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $row = $stmt->fetch(); $ret->enterpriseName = $row['enterpriseName']; if (!$patientId) { return $ret; } $sql = <<<"EOT"
select
    mpt.name AS "type",
    CASE (
        (select Max(T.f) from (
            select
                mf.ondate
            from Medprevent AS mp join MedFluor AS mf on mp.MedpreventTypeCode = mf.MedpreventTypeCode
            where mf.enterprisecode = $enterpriseCode
            and mp.MedpreventCode = mp1.MedPreventCode and mf.MedPatientCode = $patientId
            union all
            select
            mi.PlanDate
            from Medprevent AS mp_122 join medinspect AS mi on mi.MedpreventCode = mp_122.MedpreventCode
            where mi.MedpreventCode = mp1.MedPreventCode and mi.MedPatientCode = $patientId
        ) AS T (f)) ) is null
    WHEN true THEN TODAY()
    WHEN false THEN DateAdd('month', mp1.PeriodMonth, ((select Max(T.f) from (
        select
            mf.ondate
        from Medprevent AS mp join MedFluor AS mf on mp.MedpreventTypeCode = mf.MedpreventTypeCode
        where mf.enterprisecode = $enterpriseCode
        and mp.MedpreventCode = mp1.MedPreventCode and mf.MedPatientCode = $patientId
        union all
        select
        mi.PlanDate
        from Medprevent AS mp_122 join medinspect AS mi on mi.MedpreventCode = mp_122.MedpreventCode
        where mi.MedpreventCode = mp1.MedPreventCode and mi.MedPatientCode = $patientId
        ) AS T (f)) ) )
    END AS "planDate",
    TODAY() AS "nowDate"
from MedPreventType AS mpt
join MedPrevent AS mp1 on mp1.MedPreventCode = (
    select FN_medFindPrevent(274, (
        Select string_agg((mp.MedPreventCode)::text, ',' ) from MedPreventType AS mpt1 join Medprevent AS mp on mp.MedPreventTypeCode = mpt1.MedPreventTypeCode
        where mpt1.MedPreventTypeCode = mpt.MedPreventTypeCode), YMD(YEAR(TODAY()), 1,1), TODAY(), 1 ))
where mpt.Enterprisecode = $enterpriseCode
and mpt.registryControl = 1
order by 2, 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $text = ""; while ($row = $stmt->fetch()) { if ($row['nowDate'] >= $row['planDate']) { $text = $text . $row['type'] . " " . date('d.m.Y', safe_strtotime($row['planDate'])) . chr(13) . chr(10); } } if ($text !== "") { $ret->preventText = $text; } return $ret; } public function getCouponDayInfo($params = null) { $db = $this->db ?: $this->db = connectDb(); $date = isEmptyProperty($params, 'date'); $professionId = isEmptyProperty($params, 'professionId'); $doctorId = isEmptyProperty($params, 'doctorId'); $jobId = isEmptyProperty($params, 'jobId'); $ret = new stdClass; $sql = <<<"EOT"
select count(*)
from medCoupon "mc"
left join meddoctor "md" on md.meddoctorcode = mc.meddoctorcode
where mc.ondate = '$date' and mc.medcouponworkcode=$jobId and md.medprofessionCode=$professionId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret->talonCount = (int) fetchValue($stmt, 'count'); $sql = <<<"EOT"
select count(*)
from medcoupon "mc"
left join medlogsend mls on mls.medcouponcode = mc.medcouponcode
where mls.meddoctorcode=$doctorId and mc.onDate = '$date'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret->doctorTalonCount = (int) fetchValue($stmt, 'count'); return $ret; } public function cancelCouponDay($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId'); $date = isEmptyProperty($params, 'date'); $sql = <<<"EOT"
update medCoupon set
       couponState = 3
 where OnDate = ? and medDoctorCode = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $params->date, $params->doctorId ]); $this->createTalonMapUpdateNotice($db); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } private function processEq($coupon) { $db = $this->db ?: $this->db = connectDb(); $coupon = arrayToObject($coupon, 'stdClass'); $sql = <<<"EOT"
select p.MedEqServiceCode "eqServiceId",
    (select i.MedEqInfoboxCode
    from MedEqInfoboxService s
    join MedEqInfobox i on i.MedEqInfoboxCode = s.MedEqInfoboxCode
        and i.Active = 1
    where s.MedEqServiceCode = p.MedEqServiceCode
    order by i.MedEqInfoboxCode
    limit 1) "eqInfoboxId"
from MedCoupon c
join MedDoctor d on d.MedDoctorCode = c.MedDoctorCode
join MedCouponWorkProf p on p.MedCouponWorkCode = coalesce(:jobChangeId, :jobId)
    and p.MedProfessionCode = d.MedProfessionCode
where c.MedCouponCode = :couponId
limit 1
EOT;
$row = selectSql($db, $sql, [ ':jobChangeId' => isUndefinedProperty($coupon, 'jobChangeId', null), ':jobId' => isUndefinedProperty($coupon, 'jobId', 0), ':couponId' => isUndefinedProperty($coupon, 'id', 0) ]); if (!is_array($row)) { return; } $row = reset($row); if (!$row) { return; } $eqServiceId = (int) $row['eqServiceId']; $eqInfoboxId = (int) $row['eqInfoboxId']; if ($eqServiceId == 0 || $eqInfoboxId == 0) { return; } $db->beginTransaction(); try { $eqQueueCouponId = (int) selectSqlValue($db, 'select FN_medEqGetNewCouponCode(?, null, ?, null, null, ?)', [ isUndefinedProperty($coupon, 'patientId'), $eqServiceId, $eqInfoboxId ] ); if ($eqQueueCouponId < 1) { $msg = "Ошибка постановки талона в электронную очередь: ($eqQueueCouponId) "; switch ($eqQueueCouponId) { case -1: $msg .= 'не указан код услуги'; break; case -2: $msg .= 'не указан код пациента (нет в направлении/нет направления) и не указан инфокиоск (удаленный заказ возможен только с авторизацией)'; break; case -3: $msg .= 'не удалось определить категорию пациента'; break; case -4: $msg .= 'нет ни одного РМО, который обслуживает пациентов с такой категорией'; break; case -5: $msg .= 'по категории пациента закончилась квота'; break; case -6: $msg .= 'сессия уже закончена'; break; case -7: $msg .= 'нельзя добавить талон в очередь, т.к. его не успеют обслужить в течение текущей сессии'; break; default: $msg .= 'неизвестная ошибка'; break; } error_log(print_r($msg, true)); return; } executeSql($db, 'update MedEqQueueCoupon set MedCouponCode = ? where MedEqQueueCouponCode = ?', [ isUndefinedProperty($coupon, 'id'), $eqQueueCouponId ] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); error_log(print_r($e, true)); } } } handleJsonRpc('med_reception_coupons');