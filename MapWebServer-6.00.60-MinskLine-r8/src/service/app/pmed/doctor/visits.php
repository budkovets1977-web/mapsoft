<?php
require_once(__DIR__ . '/../../init.php'); class pmed_doctor_visits { private $db; public function readVisits($params = null){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $enterpriseStructureId = getSessionValue('EnterpriseStructureCode'); $search = isEmptyProperty($params, 'search', ''); $date = isEmptyProperty($params, 'date', date('Y-m-d')); $date = date('Y-m-d', safe_strtotime($date)); $doctorId = isEmptyProperty($params, 'doctorId', null); $specialtyId = isEmptyProperty($params, 'specialtyId', null); $cabinetId = isEmptyProperty($params, 'cabinetId', null); $sqlWhere = ""; $sqlWhere2 = ""; if ($search){ $sqlWhere .= " AND (FN_cmFIO(p.manCode) LIKE '%$search%' OR vc.patientFio LIKE '%$search%')"; $sqlWhere2 .= " AND FN_cmFIO(p.manCode) LIKE '%$search%'"; } if ($doctorId){ $sqlWhere .= " AND vc.medDoctorCode = $doctorId"; $sqlWhere2 .= " AND o.medDoctorCode = $doctorId"; } if ($specialtyId){ $sqlWhere .= " AND vc.medProfessionCode = $specialtyId"; $sqlWhere2 .= " AND o.medProfessionCode = $specialtyId"; } if ($cabinetId){ $sqlWhere .= " AND vc.medCabinetCode = $cabinetId"; } $sql = <<<EOT
            SELECT vc.pmsVisitCouponCode id,
                    vc.enterpriseCode "enterpriseId",
                    vc.goalName "goalName",
                    vc.medCabinetCode "medCabinetId",
                    vc.medDoctorCode "medDoctorId",
                    vc.medPatientCode "medPatientId",
                    vc.medProfessionCode "medProfessionId",
                    vc.orderTime "orderTime",
                    CASE WHEN vc.medPatientCode IS NOT NULL THEN FN_cmFIO(p.manCode) ELSE vc.patientFio END "patientFio",
                    vc.patientPhone "patientPhone",
                    vc.timeSlot "timeSlot",
                    vc.visitTime "visitTime",
                    vc.canceled,
                    vc.pmsVisitServiceCode "pmsVisitServiceId",
                    vt.changeTime "changeTime",
                    vt.note,
                    vt.talkDuration "talkDuration",
                    vt.talkType "talkType",
                    vt.state "status",
                    vt.userCode "userId",
                    o.medDiagnosisCode "medDiagnosisId",
                    d.cipher1 "diagnosisCipher",
                    d.name "diagnosisName",
                    FN_cmFIO(doc.manCode, 1) "doctorFio",
                    o.orderDate "orderDate",
                    o.doneDate "orderDoneDate",
                    mc.cabinet,
                    mc.enterpriseStructureCode "enterpriseStructureId",
                    mc.starttime "medCabinetStartTime",
                    mc.finishTime "medCabinetFinishTime",
                    mc.note "medCabinetNote",
                    mp.name "medProfessionName",
                    mp.cipher "medProfessionCipher",
                    CASE
                        WHEN vt.state = 5 THEN 1 -- Прием
                        WHEN vt.state IN (6, 7) THEN 3 -- Закончил, оплатил
                        ELSE 2
                    END "sortStatus",
                    d2.cipher1 "lastDiagnosisCipher",
                    d2.name "lastDiagnosisName",
                    o2.orderDate "lastOrderDate",
                    FN_cmFIO(doc2.manCode, 1) "lastDoctorFio"
            FROM pmsVisitCoupon vc
            LEFT JOIN (
                SELECT * FROM (
                    SELECT *, ROW_NUMBER() OVER(PARTITION BY pmsVisitCouponCode ORDER BY changeTime DESC) "rn" FROM pmsVisitCouponTalk
                ) "t"
                WHERE t.rn = 1
            ) vt ON vt.pmsVisitCouponCode = vc.pmsVisitCouponCode
            LEFT JOIN pmsOrder o ON o.pmsVisitCouponCode = vc.pmsVisitCouponCode
            LEFT JOIN medDiagnosis d ON d.medDiagnosisCode = o.medDiagnosisCode
            LEFT JOIN medDoctor doc ON doc.medDoctorCode = vc.medDoctorCode
            LEFT JOIN medPatient p ON p.medPatientCode = vc.medPatientCode
            LEFT JOIN medCabinet mc ON mc.medCabinetCode = vc.medCabinetCode
            LEFT JOIN medProfession mp ON mp.medProfessionCode = vc.medProfessionCode
            LEFT JOIN pmsOrder o2 on o2.pmsOrderCode = (
                SELECT pmsOrderCode
                FROM pmsOrder
                WHERE medPatientCode = vc.medPatientCode
                    AND orderDate < :date
                    ORDER BY pmsOrderCode DESC
                    LIMIT 1
                )
            LEFT JOIN medDiagnosis d2 ON d2.medDiagnosisCode = o2.medDiagnosisCode
            LEFT JOIN medDoctor doc2 ON doc2.medDoctorCode = o2.medDoctorCode
            WHERE vc.canceled IS NULL
                AND vt.state != 3 -- Талон отменен
                AND vc.enterpriseCode = :enterpriseId
                AND mc.enterpriseStructureCode = :enterpriseStructureId
                AND DATE(vc.visitTime) = :date
                $sqlWhere

            UNION ALL

            SELECT NULL id,
                o.enterpriseCode "enterpriseId",
                NULL "goalName",
                NULL "medCabinetId",
                o.medDoctorCode "medDoctorId",
                o.medPatientCode "medPatientId",
                o.medProfessionCode "medProfessionId",
                NULL "orderTime",
                FN_cmFIO(p.manCode) "patientFio",
                NULL "patientPhone",
                NULL "timeSlot",
                NULL "visitTime",
                NULL "canceled",
                NULL "pmsVisitServiceId",
                NULL "changeTime",
                NULL "note",
                NULL "talkDuration",
                NULL "talkType",
                NULL "status",
                NULL "userId",
                o.medDiagnosisCode "medDiagnosisId",
                d.cipher1 "diagnosisCipher",
                d.name "diagnosisName",
                FN_cmFIO(doc.manCode, 1) "doctorFio",
                o.orderDate "orderDate",
                o.doneDate "orderDoneDate",
                NULL "cabinet",
                o.enterpriseStructureCode "enterpriseStructureId",
                NULL "medCabinetStartTime",
                NULL "medCabinetFinishTime",
                NULL "medCabinetNote",
                mp.name "medProfessionName",
                mp.cipher "medProfessionCipher",
                4 "sortStatus",
                NULL "lastDiagnosisCipher",
                NULL "lastDiagnosisName",
                NULL "lastOrderDate",
                NULL "lastDoctorFio"
            FROM pmsOrder o
            LEFT JOIN medDiagnosis d ON d.medDiagnosisCode = o.medDiagnosisCode
            LEFT JOIN medDoctor doc ON doc.medDoctorCode = o.medDoctorCode
            LEFT JOIN medPatient p ON p.medPatientCode = o.medPatientCode
            LEFT JOIN medProfession mp ON mp.medProfessionCode = o.medProfessionCode
            WHERE o.enterpriseCode = :enterpriseId
                AND o.enterpriseStructureCode = :enterpriseStructureId
                AND o.pmsVisitCouponCode IS NULL
                AND DATE(o.orderDate) = :date
                $sqlWhere2

            ORDER BY "sortStatus", "visitTime"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ "enterpriseId" => $enterpriseId, "enterpriseStructureId" => $enterpriseStructureId, "date" => $date ]); $ret = $stmt->fetchAll(); return $ret; } public function changeTalonStatus($params){ $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('mapUserCode'); $closeAllTalons = isUndefinedProperty($params, 'closeAllTalons'); $patientsInWork = isUndefinedProperty($params, 'patientsInWork'); $sql = <<<EOT
            INSERT INTO pmsVisitCouponTalk
                (pmsVisitCouponCode, changeTime, state, userCode)
            VALUES (?, NOW(), ?,  ?)
EOT;
$stmt = $db->prepare($sql); if ($closeAllTalons){ foreach ($patientsInWork as $item){ $stmt->execute([ isUndefinedProperty($item, 'pmsVisitCouponId'), 6, $userId ]); } $stmt->execute([ isUndefinedProperty($params, 'pmsVisitCouponId'), 5, $userId ]); }else{ $stmt->execute([ isUndefinedProperty($params, 'pmsVisitCouponId'), isUndefinedProperty($params, 'status'), $userId ]); } return true; } } handleJsonRpc('pmed_doctor_visits');