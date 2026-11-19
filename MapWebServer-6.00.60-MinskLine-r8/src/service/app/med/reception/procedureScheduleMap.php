<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_procedureScheduleMap { private $db; const BOOKING_TIME_MINUTES = 10; const ERROR_ALREADY_BOOKED_MESSAGE = 'Талон уже забронирован!'; const ERROR_ALREADY_BOOKED_CODE = -11; const ERROR_BOOKING_TALON_DELETED_MESSAGE = 'Заказываемый талон не найден!'; const ERROR_BOOKING_TALON_DELETED_CODE = -12; public function readProcedureScheduleGridData($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $startDateSql = dbDate($startDate); $finishDateSql = dbDate($finishDate); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
WITH RECURSIVE DateRange AS (
    SELECT '$startDateSql'::date AS TheDate, '$finishDateSql'::date AS FinishDate
    UNION ALL
    SELECT (TheDate + INTERVAL '1 day')::date, FinishDate
    FROM DateRange
    WHERE (TheDate + INTERVAL '1 day')::date <= FinishDate
)
SELECT
    MSPL.MedSanProcLogCode        "id",
    MSLR.MedSanProcLogRowCode     "itemId",
    MSP.Name                      "procedureName",
    MSP.Paid                      "isPaid",
    DR.TheDate                    "couponDate",
    (
        SELECT COALESCE(SUM(R.amount), 0)
        FROM MedSanProcLogRow R
        WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
          AND R.MedSanProcCode = MSLR.MedSanProcCode
          AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
    ) "totalCoupons",
    (
        SELECT COUNT(*)
        FROM MedSanProcLogRow R
        JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
        JOIN MedCoupon MC ON MC.MedCouponCode = C.MedCouponCode
        WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
          AND R.MedSanProcCode = MSLR.MedSanProcCode
          AND MC.CouponState IN (1,2)
          AND MC.OnDate = DR.TheDate
          AND MC.MedPatientCode = MSPL.MedPatientCode
          AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
    ) "orderedCoupons",
    (
        SELECT COUNT(*)
        FROM MedSanProcLogRow R
        JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
        JOIN MedCoupon MC ON MC.MedCouponCode = C.MedCouponCode AND MC.OnDate = DR.TheDate
        JOIN MedCouponAdd MCA ON MCA.MedCouponCode = C.MedCouponCode AND MCA.MedLogServiceCode IS NULL
            AND MCA.Status = 11
        WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
          AND R.MedSanProcCode = MSLR.MedSanProcCode
          AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
    ) "visitedCoupons"
FROM MedSanProcLog MSPL
JOIN MedSanProcLogRow MSLR ON MSLR.MedSanProcLogCode = MSPL.MedSanProcLogCode
JOIN DateRange DR ON DR.TheDate BETWEEN '$startDateSql'::date AND '$finishDateSql'::date
JOIN MedSanProc MSP ON MSP.MedSanProcCode = MSLR.MedSanProcCode
WHERE MSPL.MedSanProcLogCode = $id
ORDER BY MSLR.MedSanProcLogRowCode, DR.TheDate
EOT;
$rows = selectSql($db, $sql); foreach ($rows as &$row) { if ((int)$row['orderedCoupons'] === 0 && (int)$row['visitedCoupons'] === 0) { $row['availableCoupons'] = $this->getAvailableCoupons( $db, $id, $row['itemId'], $row['couponDate'], $enterpriseId ); } else { $row['availableCoupons'] = 0; } } return $rows; } private function getAvailableCoupons($db, $id, $itemId, $theDate, $enterpriseId) { $sql = <<<EOT
SELECT COUNT(MC.MedCouponCode) AS cnt
FROM MedSanProcLogRow R
JOIN MedSanProcLog L ON L.MedSanProcLogCode = R.MedSanProcLogCode
JOIN MedSanProc P ON R.MedSanProcCode = P.MedSanProcCode
JOIN MedCoupon MC ON MC.OnDate = '$theDate'::date
JOIN MedDoctor D ON MC.MedDoctorCode = D.MedDoctorCode
LEFT JOIN Department DEP ON D.DepartmentCode = DEP.DepartmentCode
LEFT JOIN Branch BR ON BR.BranchCode = DEP.BranchCode AND BR.EnterpriseCode = $enterpriseId
LEFT JOIN MedCouponGraph MCG ON MC.MedDoctorCode = MCG.MedDoctorCode AND MC.OnDate = MCG.OnDate
LEFT JOIN LATERAL (
    SELECT *
    FROM MedCouponGraphWork MCW
    WHERE MCW.MedCouponGraphCode = MCG.MedCouponGraphCode
      AND (MC.VisitTime BETWEEN MCW.StartTime AND MCW.FinishTime OR MC.VisitTime IS NULL)
    LIMIT 1
) MCW ON TRUE
WHERE R.MedSanProcLogCode = $id
  AND R.MedSanProcLogRowCode = $itemId
  AND MC.CouponState = 0
  AND NOT EXISTS (SELECT * FROM MedCouponBooking MCB WHERE MCB.MedCouponCode = MC.MedCouponCode AND MCB.LimitTime >= LOCALTIMESTAMP LIMIT 1)
  AND ((MC.VisitTime::time between R.MinWriteTime::time AND R.MaxWriteTime::time) OR MC.VisitTime IS NULL)
  AND (
        NOT EXISTS (
             SELECT 1 FROM MedSanProcFeature PF
             WHERE PF.MedSanProcCode = P.MedSanProcCode
               AND PF.MedDoctorCode IS NOT NULL
        )
        OR MC.MedDoctorCode IN (
             SELECT PF.MedDoctorCode FROM MedSanProcFeature PF
             WHERE PF.MedSanProcCode = P.MedSanProcCode AND PF.MedDoctorCode IS NOT NULL
        )
      )
  AND (
        NOT EXISTS (
            SELECT 1 FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.MedProfessionCode IS NOT NULL
        )
        OR D.MedProfessionCode IN (
            SELECT PF.MedProfessionCode
            FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.MedProfessionCode IS NOT NULL
        )
      )
  AND (
        NOT EXISTS (
            SELECT 1 FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.DepartmentCode IS NOT NULL
        )
        OR DEP.DepartmentCode IN (
            SELECT PF.DepartmentCode
            FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.DepartmentCode IS NOT NULL
        )
      )
  AND (
        NOT EXISTS (
            SELECT 1 FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.BranchCode IS NOT NULL
        )
        OR BR.BranchCode IN (
            SELECT PF.BranchCode
            FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.BranchCode IS NOT NULL
        )
      )
  AND (
        NOT EXISTS (
            SELECT 1 FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.Cabinet IS NOT NULL
              AND PF.Cabinet > ''
        )
        OR COALESCE(NULLIF(MCW.Cabinet, ''), NULLIF(MCG.Cabinet, ''), D.Cabinet) IN (
            SELECT PF.Cabinet
            FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.Cabinet IS NOT NULL
              AND PF.Cabinet > ''
        )
      )
  AND (
        NOT EXISTS (
            SELECT 1 FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.MedCouponWorkCode IS NOT NULL
        )
        OR COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode) IN (
            SELECT PF.MedCouponWorkCode
            FROM MedSanProcFeature PF
            WHERE PF.MedSanProcCode = P.MedSanProcCode
              AND PF.MedCouponWorkCode IS NOT NULL
        )
      )
EOT;
$result = selectSql($db, $sql); return $result[0]['cnt'] ?? 0; } public function autoBookCoupons($params = null) { $db = $this->db ?: $this->db = connectDb(); $sendId = isEmptyProperty($params, 'sendId'); $resultSummary = [ 'procedures' => [], 'failedCount' => 0 ]; try { $db->beginTransaction(); $this->doCancelBookTalon(); $db->commit(); $stmt = $db->prepare("SELECT map.fn_medsanproclogplangetplan(:code, :flags)"); $stmt->execute([ ':code' => $sendId, ':flags' => 7 ]); $planJson = $stmt->fetchColumn(); $db->beginTransaction(); $stmt = $db->prepare("
            SELECT JSON
            FROM MedSanProcLog
            WHERE MedSanProcLogCode = :code
        "); $stmt->execute([':code' => $sendId]); $logJson = $stmt->fetchColumn(); $planData = json_decode($logJson, true); if (!isset($planData['plan']) || !is_array($planData['plan'])) { throw new Exception('Некорректный формат JSON-плана'); } $failedCount = 0; $updatedPlan = []; foreach ($planData['plan'] as $row) { if (!empty($row['medsanproclogrowcouponcode'])) { $updatedPlan[] = $row; continue; } $couponId = $row['medcouponcode'] ?? null; if (!$couponId) continue; try { $this->doBookTalon(['id' => $couponId]); $updatedPlan[] = $row; } catch (Exception $e) { $failedCount++; } } $planData['plan'] = $updatedPlan; $newJson = json_encode($planData, JSON_UNESCAPED_UNICODE); $stmt = $db->prepare("
            UPDATE MedSanProcLog
            SET JSON = :json
            WHERE MedSanProcLogCode = :code
        "); $stmt->execute([ ':json' => $newJson, ':code' => $sendId ]); $stmt2 = $db->prepare("
WITH plan AS (
    SELECT
        onDate,
        MedSanProcLogRowCode,
        MedCouponCode,
        MedSanProcLogRowCouponCode
    FROM json_to_recordset(
        (SELECT JSON FROM MedSanProcLog WHERE MedSanProcLogCode = :code)::json->'plan'
    ) AS Q(onDate date, MedSanProcLogRowCode integer, MedCouponCode integer, MedSanProcLogRowCouponCode integer)
),
plan_counts AS (
    SELECT
        MedSanProcLogRowCode,
        COUNT(*) AS bookedCount
    FROM plan
    GROUP BY MedSanProcLogRowCode
)
SELECT
    R.MedSanProcLogRowCode AS id,
    S.Name AS name,
    COALESCE(P.bookedCount, 0) AS bookedCount,
    R.Amount AS totalCount,
    S.Paid AS isPaid
FROM MedSanProcLogRow R
JOIN MedSanProc S ON S.MedSanProcCode = R.MedSanProcCode
LEFT JOIN plan_counts P ON P.MedSanProcLogRowCode = R.MedSanProcLogRowCode
WHERE R.MedSanProcLogCode = :code
ORDER BY R.MedSanProcLogRowCode;
        "); $stmt2->execute([':code' => $sendId]); $procedures = $stmt2->fetchAll(PDO::FETCH_ASSOC); $db->commit(); $resultSummary['procedures'] = $procedures; $resultSummary['failedCount'] = $failedCount; return $resultSummary; } catch (Exception $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; } } public function doCancelBookTalon($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); if ($id) { $sql = <<<"EOT"
SELECT
    PR_medCouponCancelBook(:id, 1)
EOT;
selectSql($db, $sql, [ ':id' => $id ]); return true; } $sql = <<<"EOT"
SELECT
    C.MedCouponCode "id"
FROM MedCouponBooking CB
JOIN MedCoupon C ON C.MedCouponCode = CB.MedCouponCode
WHERE CB.limitTime < (LOCALTIMESTAMP)
EOT;
$unbookingTalons = selectSql($db, $sql); if (count($unbookingTalons) > 0) { $sql = <<<"EOT"
SELECT
    PR_MedCouponCancelBook()
EOT;
selectSql($db, $sql); return true; } return true; } public function doBookTalon($params) { $db = $this->db ?: $this->db = connectDb(); $id = $params['id'] ?? null; $where = 'WHERE C.MedCouponCode = :id'; $sqlParams = [ ':id' => $id ]; $sql = <<<"EOT"
SELECT
    C.MedPatientCode "patientId",
    C.couponState "state"
FROM MedCoupon C
$where
EOT;
$coupons = selectSql($db, $sql, $sqlParams); if (count($coupons) > 0) { $coupon = $coupons[0]; if ($coupon['state'] === 1 && $coupon['patientId'] === null) { throw new Exception(self::ERROR_ALREADY_BOOKED_MESSAGE, self::ERROR_ALREADY_BOOKED_CODE); } $sql = <<<"EOT"
SELECT
    FN_medCouponToBook(:id, :minutes)
EOT;
$bookingResult = selectSqlValue($db, $sql, [ ':id' => $id, ':minutes' => self::BOOKING_TIME_MINUTES ]); } else { throw new Exception(self::ERROR_BOOKING_TALON_DELETED_MESSAGE, self::ERROR_BOOKING_TALON_DELETED_CODE); } return false; } public function doOrderCoupons($params = null) { $db = $this->db ?: $this->db = connectDb(); $sendId = isEmptyProperty($params, 'sendId'); $successCount = 0; $failedCount = 0; $stmt = $db->prepare("SELECT JSON FROM MedSanProcLog WHERE MedSanProcLogCode = :code"); $stmt->execute([':code' => $sendId]); $logJson = $stmt->fetchColumn(); if (!$logJson) { throw new Exception("План с талонами не найден"); } $planData = json_decode($logJson, true); $db->beginTransaction(); try { foreach ($planData['plan'] as &$row) { if (!empty($row['medsanproclogrowcouponcode'])) continue; $couponId = $row['medcouponcode'] ?? null; if (!$couponId) continue; $lockStmt = $db->prepare("SELECT 1 FROM MedCoupon WHERE MedCouponCode = :id FOR UPDATE"); $lockStmt->execute([':id' => $couponId]); $couponStmt = $db->prepare("
                SELECT MedPatientCode, CouponState
                FROM MedCoupon
                WHERE MedCouponCode = :id
            "); $couponStmt->execute([':id' => $couponId]); $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC); if (!$coupon) { $failedCount++; continue; } $hasBooking = selectSqlValue($db, "
    SELECT COUNT(*)
    FROM MedCouponBooking
    WHERE MedCouponCode = :id AND limitTime > LOCALTIMESTAMP
    ", [':id' => $couponId]); if (!(((int)$coupon['couponstate'] === 0) || ((int)$coupon['couponstate'] === 1 && $coupon['medpatientcode'] === null && $hasBooking > 0))) { $failedCount++; continue; } $updateStmt = $db->prepare("
                UPDATE MedCoupon
                SET CouponState = 1,
                    MedPatientCode = (
                        SELECT MedPatientCode
                        FROM MedSanProcLog
                        WHERE MedSanProcLogCode = :sendId
                    )
                WHERE MedCouponCode = :id
            "); $updateStmt->execute([ ':id' => $couponId, ':sendId' => $sendId ]); if ($updateStmt->rowCount() === 0) { $failedCount++; continue; } $insertStmt = $db->prepare("
                INSERT INTO MedSanProcLogRowCoupon (MedSanProcLogRowCode, MedCouponCode)
                VALUES (:rowId, :couponId)
                RETURNING MedSanProcLogRowCouponCode
            "); $insertStmt->execute([ ':rowId' => $row['medsanproclogrowcode'], ':couponId' => $couponId ]); $row['medsanproclogrowcouponcode'] = $insertStmt->fetchColumn(); $this->doCancelBookTalon((object)['id' => $couponId]); $successCount++; } $newJson = json_encode($planData, JSON_UNESCAPED_UNICODE); $updateJsonStmt = $db->prepare("
            UPDATE MedSanProcLog
            SET JSON = :json
            WHERE MedSanProcLogCode = :code
        "); $updateJsonStmt->execute([ ':json' => $newJson, ':code' => $sendId ]); $db->commit(); return [ 'successCoupons' => $successCount, 'failedCoupons' => $failedCount ]; } catch (Throwable $e) { $db->rollBack(); throw $e; } } public function doCancelCoupons($params = null) { $db = $this->db ?: $this->db = connectDb(); $sendId = isEmptyProperty($params, 'sendId'); $stmt = $db->prepare("
        SELECT JSON
        FROM MedSanProcLog
        WHERE MedSanProcLogCode = :code
    "); $stmt->execute([':code' => $sendId]); $logJson = $stmt->fetchColumn(); $planData = json_decode($logJson, true); $cancelledCount = 0; foreach ($planData['plan'] as $row) { $couponId = $row['medcouponcode'] ?? null; if ($couponId) { try { $paramObj = new stdClass(); $paramObj->id = $couponId; $this->doCancelBookTalon($paramObj); $cancelledCount++; } catch (Exception $e) { } } } return [ 'cancelledCount' => $cancelledCount, 'message' => "Отменено $cancelledCount талонов" ]; } public function readProcedureItems($params = null) { $db = $this->db ?: $this->db = connectDb(); $itemId = (int)$params->id; $totalCouponsSubquery = <<<SQL
(
    SELECT COALESCE(SUM(R.amount), 0)
    FROM MedSanProcLogRow R
    WHERE R.MedSanProcLogCode = MSLR.MedSanProcLogCode
      AND R.MedsanProcCode = MSLR.MedSanProcCode
)
SQL;
$orderCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCoupon MC ON MC.MedCouponCode = C.MedCouponCode
    WHERE R.MedSanProcLogCode = MSLR.MedSanProcLogCode
      AND (MC.CouponState IN (1, 2))
      AND R.MedsanProcCode = MSLR.MedSanProcCode
)
SQL;
$visitCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCouponAdd MCA ON MCA.MedCouponCode = C.MedCouponCode AND MCA.MedLogServiceCode IS NULL
        AND MCA.Status = 11
    WHERE R.MedSanProcLogCode = MSLR.MedSanProcLogCode
      AND R.MedsanProcCode = MSLR.MedSanProcCode
)
SQL;
$sql = <<<EOT
SELECT
    MSLR.MedSanProcLogRowCode          "id",
    MSLR.MedSanProcLogCode             "sendId",
    MSLR.MedSanProcCode                "procedureId",
    MSP.Name                           "procedureName",
    MSLR.Amount                        "amount",
    MSLR.Periodicity                   "periodicity",
    MSLR.DayKind                       "dayKind",
    MSLR.MinWriteTime                  "minWriteTime",
    MSLR.MaxWriteTime                  "maxWriteTime",
    MSP.Paid                           "isPaid",
    $totalCouponsSubquery              "totalCouponsCount",
    $orderCouponsSubquery              "orderCouponsCount",
    $visitCouponsSubquery              "visitCouponsCount",
    MSP.AmountPerDay                   "amountPerDay",
    MSP.AmountPerCourse                "amountPerCourse",
    MSP.Interval                       "interval",
    COALESCE(array_agg(MSF.BranchCode) FILTER (WHERE MSF.BranchCode IS NOT NULL), '{}')                   "branchId",
    COALESCE(string_agg(B.Name, ', '), '')                                                                "branchName",
    COALESCE(array_agg(MSF.DepartmentCode) FILTER (WHERE MSF.DepartmentCode IS NOT NULL), '{}')           "departmentId",
    COALESCE(string_agg(D.Name, ', '), '')                                                                "departmentName",
    COALESCE(array_agg(MSF.MedProfessionCode) FILTER (WHERE MSF.MedProfessionCode IS NOT NULL), '{}')     "professionIds",
    COALESCE(string_agg(P.Name, ', '), '')                                                                "professionNames",
    COALESCE(array_agg(MSF.MedDoctorCode) FILTER (WHERE MSF.MedDoctorCode IS NOT NULL), '{}')             "doctorIds",
    COALESCE(string_agg(CASE
            WHEN DR.ManCode IS NOT NULL AND fn_cmfio(DR.ManCode) <> ''
            THEN fn_cmfio(DR.ManCode)
            ELSE NULL
        END,', '), '') AS "doctorNames",
    COALESCE(array_agg(MSF.MedCouponWorkCode) FILTER (WHERE MSF.MedCouponWorkCode IS NOT NULL), '{}')     "couponWorkIds",
    COALESCE(string_agg(CW.Name, ', '), '')                                                               "couponWorkNames",
    COALESCE(array_agg(MSF.Cabinet) FILTER (WHERE MSF.Cabinet IS NOT NULL), '{}')                         "cabinets",
    COALESCE(array_agg(MSF.MedSanProcGroupCode) FILTER (WHERE MSF.MedSanProcGroupCode IS NOT NULL), '{}') "sanProcGroupIds",
    COALESCE(string_agg(SG.Name, ', '), '')                                                               "sanProcGroupNames"
FROM MedSanProcLog MSPL
JOIN MedSanProcLogRow MSLR ON MSPL.MedSanProcLogCode = MSLR.MedSanProcLogCode
LEFT JOIN MedSanProc MSP ON MSLR.MedSanProcCode = MSP.MedSanProcCode
LEFT JOIN MedSanProcFeature MSF ON MSF.MedSanProcCode = MSP.MedSanProcCode
LEFT JOIN Branch B ON B.BranchCode = MSF.BranchCode
LEFT JOIN Department D ON D.DepartmentCode = MSF.DepartmentCode
LEFT JOIN MedProfession P ON P.MedProfessionCode = MSF.MedProfessionCode
LEFT JOIN MedDoctor DR ON DR.MedDoctorCode = MSF.MedDoctorCode
LEFT JOIN MedCouponWork CW ON CW.MedCouponWorkCode = MSF.MedCouponWorkCode
LEFT JOIN MedSanProcGroup SG ON SG.MedSanProcGroupCode = MSF.MedSanProcGroupCode
WHERE MSLR.MedSanProcLogRowCode = :itemId
GROUP BY
    MSLR.MedSanProcLogRowCode, MSLR.MedSanProcLogCode, MSLR.MedSanProcCode, MSP.Name, MSLR.Amount, MSLR.Periodicity, MSLR.DayKind,
    MSLR.MinWriteTime, MSLR.MaxWriteTime, MSP.Paid, MSP.MedSanProcCode, MSP.AmountPerDay, MSP.AmountPerCourse, MSP.Interval
ORDER BY MSP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':itemId' => $itemId]); $rows = $stmt->fetchAll(); foreach ($rows as &$row) { if (!empty($row['minWriteTime'])) { $row['minWriteTime'] = substr($row['minWriteTime'], 11, 5); } if (!empty($row['maxWriteTime'])) { $row['maxWriteTime'] = substr($row['maxWriteTime'], 11, 5); } } $arrayFields = [ 'branchId', 'departmentId', 'professionIds', 'doctorIds', 'couponWorkIds', 'cabinets', 'sanProcGroupIds' ]; foreach ($rows as &$row) { foreach ($arrayFields as $field) { if (isset($row[$field])) { $val = trim($row[$field], '{}'); $row[$field] = $val !== '' ? explode(',', $val) : []; $row[$field] = array_map(fn($v) => trim($v, '"'), $row[$field]); } else { $row[$field] = []; } } } unset($row); return $rows; } public function linkProcedure($params) { $db = $this->db ?: $this->db = connectDb(); $talonId = (int)$params->talonId; $procedureId = (int)$params->procedureId; try { $db->beginTransaction(); $sql = "INSERT INTO MedSanProcLogRowCoupon (MedSanProcLogRowCode, MedCouponCode)
                     VALUES (:medSanProcLogRowCode, :medCouponCode)
                     RETURNING MedSanProcLogRowCouponCode"; $stmt = $db->prepare($sql); $stmt->execute([ ':medSanProcLogRowCode' => (int)$params->procedureId, ':medCouponCode' => (int)$params->talonId ]); $newId = $stmt->fetchColumn(); $db->commit(); return ['success' => true, 'id' => $newId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function swapProcedure($params) { $db = $this->db ?: $this->db = connectDb(); $talonId = (int)$params->talonId; $procedureId = (int)$params->procedureId; $newTalonId = (int)$params->newTalonId; try { $db->beginTransaction(); $sqlDelete = "DELETE FROM MedSanProcLogRowCoupon
                      WHERE MedSanProcLogRowCode = :procedureId
                        AND MedCouponCode = :talonId"; $stmt = $db->prepare($sqlDelete); $stmt->execute([ ':procedureId' => $procedureId, ':talonId' => $talonId ]); $sqlInsert = "INSERT INTO MedSanProcLogRowCoupon (MedSanProcLogRowCode, MedCouponCode)
                      VALUES (:procedureId, :newTalonId)
                      RETURNING MedSanProcLogRowCouponCode"; $stmt = $db->prepare($sqlInsert); $stmt->execute([ ':procedureId' => $procedureId, ':newTalonId' => $newTalonId ]); $newId = $stmt->fetchColumn(); $db->commit(); return ['success' => true, 'id' => $newId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function releaseProcedure($params) { $db = $this->db ?: $this->db = connectDb(); $talonId = (int)$params->talonId; try { $db->beginTransaction(); $sqlDelete = "DELETE FROM MedSanProcLogRowCoupon
                      WHERE MedCouponCode = :talonId"; $stmt = $db->prepare($sqlDelete); $stmt->execute([ ':talonId' => $talonId ]); $db->commit(); return ['success' => true, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function readLastSend($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isUndefinedProperty($params, 'patientId'); $today = date('Y-m-d'); $sql = <<<SQL
SELECT
    MSL.MedSanProcLogCode "sendId",
    MSL.StartDate "startDate",
    MSL.FinishDate "finishDate",
    MSL.DocumentNumber "documentNumber"
FROM MedSanProcLog MSL
WHERE MSL.MedPatientCode = :patientId AND MSL.FinishDate >= :today
ORDER BY MSL.DocumentDate DESC
LIMIT 1
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ ':patientId' => $patientId, ':today' => $today ]); $res = $stmt->fetch(PDO::FETCH_ASSOC); return $res ?: null; } } handleJsonRpc('med_reception_procedureScheduleMap');