<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_procedureCouponsVisit { private $db; public function readProcedureCouponsVisit($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); if ($id && (!$startDate || !$finishDate)) { $sqlDates = <<<EOT
SELECT
    MSPL.StartDate    "startDate",
    MSPL.FinishDate    "finishDate"
    FROM MedSanProcLog MSPL
WHERE MSPL.MedSanProcLogCode = $id
EOT;
$dates = selectSql($db, $sqlDates); $startDate = $dates[0]['startDate']; $finishDate = $dates[0]['finishDate']; } $startDateSql = dbDate($startDate); $finishDateSql = dbDate($finishDate); $sql = <<<EOT
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
    CASE MCA.MedCouponCode IS NOT NULL WHEN TRUE THEN TRUE END   "isVisited",
    COALESCE(MC.VisitTime::timestamp,
        (SELECT MCAD.VisitTime::timestamp FROM MedCouponAdd MCAD 
            WHERE MCAD.MedCouponCode = MC.MedCouponCode AND MCAD.MedLogServiceCode IS NULL))  "visitTime",
    fn_cmfio(MD.ManCode)          "doctorFio",
    MP.Name                       "doctorProfession",
    CAB.Field2                    "cabinet",
    MC.MedCouponCode              "couponId",
    MSPL.StartDate                "startDate",
    MSPL.FinishDate               "finishDate",
    MSPL.DocumentNumber           "documentNumber"
FROM MedSanProcLog MSPL
JOIN MedSanProcLogRow MSLR ON MSLR.MedSanProcLogCode = MSPL.MedSanProcLogCode
JOIN DateRange DR ON DR.TheDate BETWEEN '$startDateSql'::date AND '$finishDateSql'::date
JOIN MedSanProc MSP ON MSP.MedSanProcCode = MSLR.MedSanProcCode
JOIN MedSanProcLogRowCoupon MSPC ON MSLR.MedSanProcLogRowCode = MSPC.MedSanProcLogRowCode
JOIN MedCoupon MC ON MC.MedCouponCode = MSPC.MedCouponCode AND MC.OnDate = DR.TheDate 
    AND MC.CouponState IN (1, 2)
LEFT JOIN MedCouponAdd MCA ON MCA.MedCouponCode = MC.MedCouponCode AND MCA.MedLogServiceCode IS NULL
    AND MCA.Status = 11
LEFT JOIN MedDoctor MD ON MC.MedDoctorCode = MD.MedDoctorCode
LEFT JOIN MedProfession MP ON MD.MedProfessionCode = MP.MedProfessionCode
    LEFT JOIN (
        SELECT 
            C.MedCouponCode AS field1,
            COALESCE(
                CAD.Cabinet,
                (
                    SELECT COALESCE(NULLIF(MPGW.Cabinet, ''), NULLIF(MPG.Cabinet, ''))
                    FROM MedCouponGraph MPG
                    LEFT JOIN MedCouponGraphWork MPGW 
                        ON MPGW.MedCouponGraphCode = MPG.MedCouponGraphCode
                        AND MPGW.StartTime <= C.VisitTime 
                        AND MPGW.FinishTime > C.VisitTime
                    WHERE MPG.OnDate = c.OnDate 
                      AND MPG.MedDoctorCode = C.MedDoctorCode
                    LIMIT 1
                ),
                DOC.Cabinet
            ) AS field2
        FROM MedCoupon C
        LEFT JOIN MedCouponAdd CAD ON CAD.MedCouponCode = C.MedCouponCode
        JOIN MedDoctor DOC ON DOC.MedDoctorCode = C.MedDoctorCode
    ) AS CAB ON CAB.Field1 = MC.MedCouponCode
WHERE MSPL.MedSanProcLogCode = $id
ORDER BY DR.TheDate, "visitTime", MSP.Name
EOT;
$rows = selectSql($db, $sql); return $rows; } public function updateProcedureCouponsVisit($params = null) { $db = $this->db ?: $this->db = connectDb(); $records = $params->changedRecords ?? []; foreach ($records as $row) { $couponId = $row->couponId ?? 0; $isVisited = isset($row->isVisited) && (bool)$row->isVisited; if (!$couponId) { continue; } if ($isVisited) { $sqlCheck = "SELECT 1 FROM MedCouponAdd WHERE MedCouponCode = $couponId AND MedLogServiceCode IS NULL LIMIT 1"; $exists = selectSql($db, $sqlCheck); if (empty($exists)) { $sqlInsert = "
                    INSERT INTO MedCouponAdd (MedCouponCode, Status)
                    VALUES ($couponId, 11)
                "; executeSql($db, $sqlInsert); } else { $sqlUpdate = "
                    UPDATE MedCouponAdd
                    SET Status = 11
                    WHERE MedCouponCode = $couponId AND MedLogServiceCode IS NULL
                "; executeSql($db, $sqlUpdate); } } else { $sqlClear = "
                UPDATE MedCouponAdd
                SET Status = NULL
                WHERE MedCouponCode = $couponId AND MedLogServiceCode IS NULL
            "; executeSql($db, $sqlClear); } } return true; } } handleJsonRpc('med_reception_procedureCouponsVisit');