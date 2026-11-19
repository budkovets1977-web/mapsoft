<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_reservePaidCoupon { const TASK_NAME = 'talonbyReservePaidCoupon'; const MAX_ACTIVE_COUPONS = 5; private $db; public function ReservePaidCoupon($MedCouponCode, $MedPatientCode, $AMode) { $params = new stdClass; $params->medCouponCode = isset($MedCouponCode) ? $MedCouponCode : 0; $params->medPatientCode = isset($MedPatientCode) ? $MedPatientCode : 0; $params->aMode = isset($AMode) ? $AMode : 0; $params->FIO = ''; $params->selfRecording = 1; $params->couponState = 1; $params->mode = 0; $params->note = ''; $params->setShortNumber = 0; if (in_array($params->aMode, [1, 3, 5])) { $params->couponType = 1; } else { $params->couponType = 0; } if ($params->medCouponCode === 0) { return 0; } $check = $this->checkPaidCoupon($params); if ($check < 0) { return $check; } if($this->CouponPaidUpdate($params)) { return true; } return false; } private function CouponPaidUpdate($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCoupon AS _t0_ SET
    medpatientcode = :medPatientCode::integer,
    selfrecording = :selfRecording,
    couponstate = :couponState,
    ordertime = Date(LOCALTIMESTAMP),
    fio = CASE :mode=1 WHEN true THEN :FIO WHEN false THEN NULL END ,
    coupontype = :couponType,
    note = NULLIF(COALESCE(:note, E'' ), E'' ),
    shortnumber = CASE :setShortNumber = 1 WHEN true THEN :setShortNumber WHEN false THEN mc.ShortNumber END
        FROM MedCoupon AS mc left outer join MedDoctor AS md On mc.MedDoctorCode=md.MedDoctorCode
        left outer join MedCouponControl AS mcc On (md.MedprofessionCode=mcc.MedProfessionCode AND mcc.MedCouponWorkCode=mc.MedCouponWorkCode)
WHERE (_t0_.medcouponcode=mc.medcouponcode)
    AND ( mc.MedCouponCode=:medCouponCode
        AND (:mode = 0
        OR (:mode = 1
            AND (COALESCE(mcc.FlagSelfRecording,0)=1
            AND COALESCE(mcc.WithoutControl,0)=1
        )
    )
  )
)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ ':medPatientCode' => isUndefinedProperty($params, 'medPatientCode'), ':FIO' => isUndefinedProperty($params, 'FIO'), ':medCouponCode' => isUndefinedProperty($params, 'medCouponCode'), ':selfRecording' => isUndefinedProperty($params, 'selfRecording'), ':couponState' => isUndefinedProperty($params, 'couponState'), ':couponType' => isUndefinedProperty($params, 'couponType'), ':mode' => isUndefinedProperty($params, 'mode'), ':note' => isUndefinedProperty($params, 'note'), ':setShortNumber' => isUndefinedProperty($params, 'setShortNumber') ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("При редактировании талона произошла ошибка: $e"); return false; } return true; } private function checkPaidCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    ( CASE WHEN MCC.medCouponControlCode IS NULL THEN 0 ELSE 1  END ) as SetupIsPresent,
    CASE MC.OnDate = DATE( (LOCALTIMESTAMP)) WHEN true THEN 1 WHEN false THEN 0 END as Today,
    COALESCE( MCC.SelfRecordingPercent, 0.0 ) as QPercent,
    COALESCE( MCC.WithoutControl, 0 ) as WithoutControl,
    (CASE (EXISTS(  SELECT 1 FROM TalonServicePrice AS TSP JOIN TalonService AS TS ON TS.TalonServiceCode = TSP.TalonServiceCode
                        WHERE COALESCE(TSP.Active, 0) = 1 AND COALESCE(TS.Active, 0) = 1 AND TSP.MedProfessionCode = MD.MedProfessionCode AND TSP.MedCouponWorkCode = COALESCE(MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode ))) WHEN true THEN 1 END ) as pFlag,
    COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2)
          WHEN true THEN MC.medCouponCode END ) as TotalCount,
    COUNT( CASE MC.medPatientCode = :medPatientCode AND
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
WHERE MC2.medCouponCode = :medCouponCode
  AND (COALESCE( MC2.CouponState, 0 ) = 0
      OR  (COALESCE( MC2.medPatientCode, 0 ) = :medPatientCode
       AND COALESCE( MC2.CouponState, 0) <> (0)))
GROUP BY 1, 2, 3, 4, MD.MedProfessionCode, MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':medPatientCode' => isUndefinedProperty($params, 'medPatientCode'), ':medCouponCode' => isUndefinedProperty($params, 'medCouponCode') ]); $items = $stmt->fetch(); if ($items['totalcount'] == 0) { return -4; } elseif ($items['pflag'] == 0) { return -10; } return true; } } handleJsonRpc('med_talonby_reservePaidCoupon'); 