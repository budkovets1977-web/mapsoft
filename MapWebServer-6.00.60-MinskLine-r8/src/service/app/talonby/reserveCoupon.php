<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_reserveCoupon { const TASK_NAME = 'talonbyReserveCoupon'; const MAX_ACTIVE_COUPONS = 5; private $db; public function freeCouponWithReg($medCouponCode, $medPatientCode) { if (!$medCouponCode) { return 'MedCouponCode = 0'; } $ban = $this->checkBan($medPatientCode); if ($ban === -2) { return -2; } $coupon = $this->checkCoupon($medCouponCode, $medPatientCode); if ($coupon < 0) { return $coupon; } $result = $this->couponUpdate($medCouponCode, $medPatientCode); return $result; } private function checkBan($medPatientCode) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT COUNT(*)  as ActiveCount
FROM MedCoupon
WHERE MedPatientCode = $medPatientCode
    AND SelfRecording = 1
    AND OnDate >= (today())
    AND CouponState IN (1,2)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(); if ($items[0]['activecount'] >= self::MAX_ACTIVE_COUPONS) { return -2; } else { return true; } } private function checkCoupon($medCouponCode, $medPatientCode) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    CASE WHEN MCC.medCouponControlCode IS NULL THEN 0 ELSE 1 END as SetupIsPresent,
    CASE MC.OnDate = DATE( (LOCALTIMESTAMP)) WHEN true THEN 1 WHEN false THEN 0 END as Today,
    COALESCE( MCC.SelfRecordingPercent, 0.0 ) as QPercent,
    COALESCE( MCC.WithoutControl, 0 ) as WithoutControl,
    (CASE (EXISTS(  SELECT 1 FROM TalonServicePrice AS TSP JOIN TalonService AS TS ON TS.TalonServiceCode = TSP.TalonServiceCode
                    WHERE COALESCE(TSP.Active, 0) = 1 AND COALESCE(TS.Active, 0) = 1 AND TSP.MedProfessionCode = MD.MedProfessionCode
                        AND TSP.MedCouponWorkCode = COALESCE(MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode )))
        WHEN true THEN 1 END ) as pFlag,
    COUNT(CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2)
        WHEN true THEN MC.medCouponCode END ) as TotalCount,
    COUNT( CASE MC.medPatientCode = $medPatientCode AND
        COALESCE( MC.CouponState, 0 ) IN (1,2)
        WHEN true THEN MC.medCouponCode END ) as PatientCount,
    COUNT( CASE MC.medPatientCode IS NOT NULL AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0
          WHEN true THEN MC.medCouponCode END ) as OrderedCount,
    COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0)
          WHEN true THEN MC.medCouponCode END ) as FreeCount,
    ROUND( (COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2)
          WHEN true THEN MC.medCouponCode END ) ) * (COALESCE( MCC.SelfRecordingPercent, 0.0 ) ) * 0.01, 0 ) - (COUNT( CASE MC.medPatientCode IS NOT NULL AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0
          WHEN true THEN MC.medCouponCode END ) ) as RestCount

FROM medCoupon AS MC2 JOIN medDoctor AS MD ON MD.medDoctorCode = MC2.medDoctorCode
 LEFT OUTER JOIN medCouponControl AS MCC ON MCC.medProfessionCode = MD.medProfessionCode
  AND MCC.FlagSelfRecording = 1
  AND MCC.medCouponWorkCode = COALESCE( MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode )

 LEFT OUTER JOIN medCoupon AS MC ON MC.medDoctorCode = MC2.medDoctorCode
  AND MC.OnDate = MC2.OnDate
  AND COALESCE( MC.medCouponWorkCodeChange, MC.medCouponWorkCode ) = MCC.medCouponWorkCode

WHERE MC2.medCouponCode = $medCouponCode
  AND (COALESCE( MC2.CouponState, 0 ) = 0
      OR  (COALESCE( MC2.medPatientCode, 0 ) = $medPatientCode
       AND COALESCE( MC2.CouponState, 0) <> (0)))

GROUP BY 1, 2, 3, 4, MD.MedProfessionCode, MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(); if ($items) { if ($items[0]['today'] === 1) { return -2; } elseif ($items[0]['totalcount'] === 0) { return -4; } elseif ($items[0]['patientcount'] > 0) { return -1; } elseif ($items[0]['setupispresent'] === 0) { return -2; } elseif ($items[0]['restcount'] === 0) { return -3; } elseif ($items[0]['pflag'] === 1) { return -10; } else { return true; } } } private function couponUpdate($medCouponCode, $medPatientCode, $SelfRecording = 1, $couponState = 1, $couponType = 0, $SetShortNumber = 0) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCoupon AS _t0_ SET
    medpatientcode = $medPatientCode,
    selfrecording = $SelfRecording,
    couponstate = $couponState,
    ordertime = DATE( (LOCALTIMESTAMP)),
    coupontype = $couponType,
    shortnumber = CASE $SetShortNumber = 1 WHEN true THEN $SetShortNumber WHEN false THEN MC.ShortNumber END
FROM MedCoupon AS MC LEFT OUTER JOIN MedDoctor AS MD ON MC.MedDoctorCode = MD.MedDoctorCode
    LEFT OUTER JOIN MedCouponControl AS MCC ON (MD.MedprofessionCode = MCC.MedProfessionCode AND MCC.MedCouponWorkCode = MC.MedCouponWorkCode)
WHERE (_t0_.medcouponcode=MC.medcouponcode)
    AND ( MC.MedCouponCode = $medCouponCode)

EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return 1; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_talonby_reserveCoupon'); 