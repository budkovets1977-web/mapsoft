<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_reserveCouponStranger { const MAX_ACTIVE_COUPONS = 5; const TASK_NAME = 'talonbyReserveCouponStranger'; private $db; public function freeCouponWithoutReg($medCouponCode, $aData) { $Amode = 0; $aCount = count($aData); $aCount >= 1 ? $pSurName = $aData[0] : $pSurName = ''; $aCount >= 2 ? $pName = $aData[1] : $pName = ''; $aCount >= 3 ? $pSecondName = $aData[2] : $pSecondName = ''; $aCount >= 4 ? $pDateofBirth = $aData[3] : $pDateofBirth = ''; $aCount >= 5 ? $aPlace = $aData[4] : $aPlace = ''; $aCount >= 6 ? $aStreet = $aData[5] : $aStreet = ''; $aCount >= 7 ? $aHouse = $aData[6] : $aHouse = ''; $aCount >= 8 ? $aCorp = $aData[7] : $aCorp = ''; $aCount >= 9 ? $aFlat = $aData[8] : $aFlat = ''; $aCount >= 10 ? $pPhone = $aData[9] : $pPhone = ''; $aCount >= 11 ? $pEmail = $aData[10] : $pEmail = ''; $pFIO = trim($pSurName) . ' ' . trim($pName) . ' ' . trim($pSecondName) . ' ' . trim($pDateofBirth) . ' ' . trim($aPlace) . ' ' . trim($aStreet) . ' ' . trim($aHouse) . '/' . trim($aCorp) . '-' . trim($aFlat); $pNote = trim($pPhone) . ' ' . trim($pEmail); $params = new stdClass; $params->medCouponCode = $medCouponCode; $params->FIO = $pFIO; $params->medPatientCode = null; $params->selfRecording = 1; $params->couponState = 1; $params->couponType = 0; $params->mode = 1; $params->note = $pNote; $params->setShortNumber = 0; $strangerBan = $this->checkStrangerBan($pFIO); if ($strangerBan === 0) { return $strangerBan; } $check = $this->checkStrangerCoupon($params); if ($check < 0) { return $check; } if ($this->CouponStrangerUpdate($params)) { return 1; } else { return -5; } } private function checkStrangerBan($pFIO) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
  (SELECT Count(*)
  FROM MedCoupon
    WHERE (
      COALESCE(FIO, E'' ) = COALESCE('$pFIO', E'' )

    AND SelfRecording = 1 AND Date(OnDate) < Date((LOCALTIMESTAMP))AND CouponState = 1)) as NotUsedCount,
  (SELECT Count(*)
   FROM MedCoupon
         WHERE
            (COALESCE(FIO, E'' ) = COALESCE('$pFIO', E'' ))
            AND SelfRecording=1
     AND Date(OnDate)>=(Date((LOCALTIMESTAMP))
     )AND CouponState IN (1,2)     ) as ActiveCount;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetch(); if ($items['activecount'] >= self::MAX_ACTIVE_COUPONS) { return 0; } return true; } private function checkStrangerCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    (CASE WHEN MCC.medCouponControlCode IS NULL THEN 0 ELSE 1  END ) as SetupIsPresent,
    CASE MC.OnDate = DATE( (LOCALTIMESTAMP)) WHEN true THEN 1 WHEN false THEN 0 END as Today,
    COALESCE( MCC.SelfRecordingPercent, 0.0 ) as QPercent,
    COALESCE( MCC.WithoutControl, 0 ) as WithoutControl,
    (CASE (EXISTS(SELECT 1 FROM TalonServicePrice AS TSP JOIN TalonService AS TS ON TS.TalonServiceCode = TSP.TalonServiceCode
                    WHERE COALESCE(TSP.Active, 0) = 1 AND COALESCE(TS.Active, 0) = 1 AND TSP.MedProfessionCode = MD.MedProfessionCode AND TSP.MedCouponWorkCode = COALESCE(MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode ))) WHEN true THEN 1 END ) as pFlag,
    COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2) AND MC.medDoctorCode = MC2.medDoctorCode
          WHEN true THEN MC.medCouponCode END ) as TotalCount,
    COUNT( CASE (
                     ( (:medPatientCode::integer IS NOT NULL) AND (COALESCE(MC.MedPatientCode,0)=COALESCE(:medPatientCode::integer,0)) ) OR
                     ( (:medPatientCode::integer IS NULL) AND (:FIO::text IS NOT NULL) AND (COALESCE(MC.FIO, E'' ) = COALESCE(:FIO::text, E'' )) )
                   ) AND
           COALESCE( MC.CouponState, 0 ) IN (1,2)
          WHEN true THEN MC.medCouponCode END ) as PatientCount,
    COUNT( CASE MC.medPatientCode IS NOT NULL AND MC.medDoctorCode = MC2.medDoctorCode AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0
          WHEN true THEN MC.medCouponCode END ) as OrderedCount,
    COUNT( CASE MC.medPatientCode IS NOT NULL AND MC.medDoctorCode = MC2.medDoctorCode AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0 AND
           COALESCE( MC.SelfRecording, 0 ) = 1
          WHEN true THEN MC.medCouponCode END ) as SelfOrderedCount,
    COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0) AND MC.medDoctorCode = MC2.medDoctorCode
          WHEN true THEN MC.medCouponCode END ) as FreeCount,
    FN_cmMax( 0,
        FN_cmMin( ROUND( 0.01 * (COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2) AND MC.medDoctorCode = MC2.medDoctorCode
            WHEN true THEN MC.medCouponCode END ) ) * (COALESCE( MCC.SelfRecordingPercent, 0.0 ) ), 0 ) - (COUNT( CASE MC.medPatientCode IS NOT NULL AND MC.medDoctorCode = MC2.medDoctorCode AND
            COALESCE( MC.CouponState, 0 ) IN (1,2) AND
            COALESCE( MC.CouponType, 0 ) = 0 AND
            COALESCE( MC.SelfRecording, 0 ) = 1
            WHEN true THEN MC.medCouponCode END ) ),
                (COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2) AND MC.medDoctorCode = MC2.medDoctorCode
            WHEN true THEN MC.medCouponCode END ) ) - (COUNT( CASE MC.medPatientCode IS NOT NULL AND MC.medDoctorCode = MC2.medDoctorCode AND
            COALESCE( MC.CouponState, 0 ) IN (1,2) AND
            COALESCE( MC.CouponType, 0 ) = 0
            WHEN true THEN MC.medCouponCode END ) ))) as RestCount
FROM medCoupon AS MC2 JOIN medDoctor AS MD ON MD.medDoctorCode = MC2.medDoctorCode
    LEFT OUTER JOIN medCouponControl AS MCC ON MCC.medProfessionCode = MD.medProfessionCode
    AND MCC.FlagSelfRecording = 1
    AND MCC.medCouponWorkCode = COALESCE( MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode )
    LEFT OUTER JOIN medDoctor AS MD3 ON MD3.medProfessionCode = MCC.medProfessionCode
    LEFT OUTER JOIN medCoupon AS MC ON MC.medDoctorCode = MD3.medDoctorCode
        AND MC.OnDate = MC2.OnDate
        AND COALESCE( MC.medCouponWorkCodeChange, MC.medCouponWorkCode ) = MCC.medCouponWorkCode
WHERE MC2.medCouponCode = :medCouponCode
    AND (COALESCE( MC2.CouponState, 0 ) = 0
                  OR ( (
                ( (:medPatientCode::integer IS NOT NULL) AND (COALESCE(MC2.MedPatientCode,0)=COALESCE(:medPatientCode::integer,0)) ) OR
                ( (:medPatientCode::integer IS NULL) AND (:FIO::text IS NOT NULL) AND (COALESCE(MC2.FIO, E'' ) = COALESCE(:FIO::text, E'' )) )
               )
            AND COALESCE( MC2.CouponState, 0) <> (0)))
    AND MC.OnDate IS NOT NULL
GROUP BY 1, 2, 3, 4, MD.MedProfessionCode, MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':medPatientCode' => isUndefinedProperty($params, 'medPatientCode'), ':FIO' => isUndefinedProperty($params, 'FIO'), ':medCouponCode' => isUndefinedProperty($params, 'medCouponCode') ]); $items = $stmt->fetch(); if ($items['today'] == 1) { return -2; } else { if ($items['withoutcontrol'] == 0) { return -6; } else { if ($items['totalcount'] == 0) { return -4; } elseif ($items['patientcount'] > 0) { return -1; } elseif ($items['setupispresent'] == 0) { return -2; } elseif ($items['restcount'] == 0) { return -3; } elseif ($items['pflag'] == 1) { return -10; } } } } private function CouponStrangerUpdate($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
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
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ ':medPatientCode' => isUndefinedProperty($params, 'medPatientCode'), ':FIO' => isUndefinedProperty($params, 'FIO'), ':medCouponCode' => isUndefinedProperty($params, 'medCouponCode'), ':selfRecording' => isUndefinedProperty($params, 'selfRecording'), ':couponState' => isUndefinedProperty($params, 'couponState'), ':couponType' => isUndefinedProperty($params, 'couponType'), ':mode' => isUndefinedProperty($params, 'mode'), ':note' => isUndefinedProperty($params, 'note'), ':setShortNumber' => isUndefinedProperty($params, 'setShortNumber') ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("При редактировании талона произошла ошибка: $e"); return false; } return true; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_talonby_reserveCouponStranger'); 