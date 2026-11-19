<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_reception_visitCoupons { private $db; public function readVisitCoupon($params) { $db = $this->db ?: $this->db = connectDb(); $isGraph = isUndefinedProperty($params, 'isGraph'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $enterpriseStructureCode = getSessionValue('EnterpriseStructureCode', 0); $fioMode = (int) isEmptyProperty($params, 'initials'); $sql = ""; $sqlBody = <<<"EOT"
select
    PVC.pmsVisitCouponCode "id",
    PVC.enterpriseCode "enterpriseCode",
    CASE
        WHEN PVC.enterpriseCode = $enterpriseId AND MC.enterpriseStructureCode = $enterpriseStructureCode
        THEN 1
        else 0
    END "fromCurrentStructure",
    PVC.visitTime::time "visitTime",
    PVC.visitTime::date "visitDate",
    PVC.visitTime       "visitDateTime",
    to_char(PVC.orderTime, 'YYYY-MM-DD HH24:MI:SS') "orderTime",
    CASE PVC.MedPatientCode IS NOT NULL
        WHEN true THEN FN_cmFIO(MP.ManCode, $fioMode)
        WHEN false THEN PVC.PatientFIO
    END "patientFio",
    PVC.patientPhone "patientPhone",
    MPass.sex "sex",
    PVC.medPatientCode "patientId",
    cast(PVC.timeslot / 60 as integer) "timeslot",
    PVC.medCabinetCode "cabinetId",
    MC.cabinet "cabinet",
    PVC.medDoctorCode "doctorId",
    FN_cmFIO(MD.ManCode) "doctor",
    COALESCE(PVC.medProfessionCode, MD.medProfessionCode) "professionId",
    COALESCE(MPC.Name, MPD.Name) "profession",
    E.Name "organization",
    CT.State "state",
    CT.Note "stateNote",
    to_char(CT.ChangeTime, 'YYYY-MM-DD HH24:MI:SS') "stateTime",
    PVC.pmsVisitServiceCode "serviceId",
    pvs.Name "serviceName",
    pvc.canceled "canceled",
    po.pmsOrderCode as "pmsOrderId"
from PmsVisitCoupon PVC
left join MedProfession MPC on MPC.MedProfessionCode = COALESCE(PVC.medProfessionCode,0)
left join medPatient MP on MP.medPatientCode = PVC.medPatientCode
left join ManPassport MPass on MPass.manCode = MP.manCode
left join Enterprise E ON E.EnterpriseCode = PVC.EnterpriseCode
left join MedDoctor MD ON MD.medDoctorCode = PVC.medDoctorCode
left join MedProfession MPD on MPD.MedProfessionCode = MD.MedProfessionCode
left join MedCabinet MC on MC.medCabinetCode = PVC.medCabinetCode
left join PmsVisitCouponTalk CT on ct.pmsVisitCouponCode = PVC.pmsVisitCouponCode
    and COALESCE(ct.ChangeTime,19000101) = COALESCE((
        select max(ChangeTime)
        from pmsVisitCouponTalk
        where pmsVisitCouponCode = pvc.pmsVisitCouponCode
    ),19000101)
left join pmsVisitService pvs on pvs.pmsVisitServiceCode = pvc.pmsVisitServiceCode
left join pmsOrder as po on po.pmsVisitCouponCode = pvc.pmsVisitCouponCode
EOT;
if ($isGraph) { $sql = $this->createRequestForGraph($params, $sqlBody); } else { $sql = $this->createRequestForHistory($params, $sqlBody); } return selectSql($db, $sql, []); } private function createRequestForHistory($params, $sqlBody) { $where ='where 1=1'; $id = isUndefinedProperty($params, 'id'); $needLoadHistory = isUndefinedProperty($params, 'needLoadHistory'); $patientId = isUndefinedProperty($params, 'patientId'); $fio = isUndefinedProperty($params, 'fio'); $phone = isUndefinedProperty($params, 'mobilePhone'); $joinHistory = ""; if ($needLoadHistory) { $joinHistory = <<<"EOT"
            join pmsVisitCoupon History on History.pmsVisitCouponCode = $id
            and (
                (history.medpatientcode is not null and history.medpatientcode = pvc.medpatientcode)
                or (history.medpatientcode is null
                    and coalesce(History.patientFio,'') = coalesce(pvc.patientfio,'')
                    and coalesce(history.patientphone,'') = coalesce(pvc.patientphone,'')
                )
            )
EOT;
} else if ($id) { $where .= "and PVC.pmsVisitCouponCode = $id"; } if ($patientId) { $where .= " and PVC.medPatientCode = $patientId"; } else { if ($fio) { $where .= " and PVC.patientFio LIKE '%$fio%'"; } if ($phone) { $where .= " and PVC.patientPhone LIKE'%$phone%'"; } } return <<<"EOT"
            $sqlBody
            $joinHistory
            $where
EOT;
} private function createRequestForGraph($params, $sqlBody) { $id = isUndefinedProperty($params, 'id'); $filter = isEmptyProperty($params, 'filter'); $mode = $filter ? isEmptyProperty($filter, 'mode') : 1; $enterpriseId = getSessionValue('mapEnterpriseCode'); $enterpriseStructureCode = getSessionValue('EnterpriseStructureCode', 0); $startDate = isEmptyProperty($params, 'startDate') ? dbDate(isEmptyProperty($params, 'startDate')) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? dbDate(isEmptyProperty($params, 'finishDate')) : null; $dateSql = ($startDate && $finishDate) ? " and CAST(COALESCE(PVC.visitTime,19000101) as date) between '{$startDate}' and '{$finishDate}'" : ''; $cabinetIds = isEmptyProperty($params, 'cabinetIds'); $cabinetIdList = empty($cabinetIds) ? '' : (in_array($mode, [2, 3]) ? reset($cabinetIds) : implode(",", $cabinetIds)); $cabinetSql = empty($cabinetIds) ? '' : " and PVC.medCabinetCode in ({$cabinetIdList})"; $skipCanceled = isEmptyProperty($params, 'skipCanceled'); $skipCanceledSql = empty($skipCanceled) ? '' : " and COALESCE(PVC.canceled,0) = 0"; $where = "where PVC.enterpriseCode = {$enterpriseId} and ($enterpriseStructureCode = 0 or MC.enterpriseStructureCode = $enterpriseStructureCode)"; if ($id) { $where .= " and PVC.pmsVisitCouponCode = $id"; } $where .= $dateSql; $where .= $cabinetSql; $where .= $skipCanceledSql; return <<<"EOT"
            $sqlBody
            $where
EOT;
} public function createVisitCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO PmsVisitCoupon (enterpriseCode, visitTime, orderTime, medPatientCode, patientFio, patientPhone, timeslot, medCabinetCode, medDoctorCode, medProfessionCode, pmsVisitServiceCode)
VALUES (?, ?::time + ?::date, now(), ?, ?, ?, ?*60, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ $enterpriseCode, isEmptyProperty($row, 'visitTime'), isEmptyProperty($row, 'visitDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'patientFio'), isEmptyProperty($row, 'patientPhone'), isEmptyProperty($row, 'timeslot'), isEmptyProperty($row, 'cabinetId'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'professionId'), isEmptyProperty($row, 'serviceId') ]); $id = $db->lastInsertId('seq_PmsVisitCoupon'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readVisitCoupon((object) ['id' => $id]); } public function updateVisitCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update PmsVisitCoupon set
    visitTime = case when ? = 1 then
        (case when ? = 1 then ? else visitTime end)::time
        +
        (case when ? = 1 then ? else visitTime end)::date
    else
    visitTime
    end,
    orderTime = case when ? = 1 then
        (case when ? = 1 then ? else orderTime end)::time
        +
        (case when ? = 1 then ? else orderTime end)::date
    else
    orderTime
    end,
    medCabinetCode = case when ? = 1 then ? else medCabinetCode end,
    medPatientCode = case when ? = 1 then ? else medPatientCode end,
    patientFio = case when ? = 1 then ? else patientFio end,
    patientPhone = case when ? = 1 then ? else patientPhone end,
    timeslot = case when ? = 1 then ?*60 else timeslot end,
    medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
    medProfessionCode = case when ? = 1 then ? else medProfessionCode end,
    pmsVisitServiceCode = case when ? = 1 then ? else pmsVisitServiceCode end,
    canceled = case when ? = 1 then ? else canceled end
where pmsVisitCouponCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'visitTime') || propertyExistsSQL($row, 'visitDate'), propertyExistsSQL($row, 'visitTime'), isEmptyProperty($row, 'visitTime'), propertyExistsSQL($row, 'visitDate'), isEmptyProperty($row, 'visitDate'), propertyExistsSQL($row, 'orderTime') || propertyExistsSQL($row, 'orderDate'), propertyExistsSQL($row, 'orderTime'), isEmptyProperty($row, 'orderTime'), propertyExistsSQL($row, 'orderDate'), isEmptyProperty($row, 'orderDate'), propertyExistsSQL($row, 'cabinetId'), isEmptyProperty($row, 'cabinetId'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'patientFio'), isUndefinedProperty($row, 'patientFio'), propertyExistsSQL($row, 'patientPhone'), isEmptyProperty($row, 'patientPhone'), propertyExistsSQL($row, 'timeslot'), isEmptyProperty($row, 'timeslot'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'professionId'), isUndefinedProperty($row, 'professionId'), propertyExistsSQL($row, 'serviceId'), isUndefinedProperty($row, 'serviceId'), propertyExistsSQL($row, 'canceled'), isEmptyProperty($row, 'canceled'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getPatientPhones($patientId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    string_agg(P.phoneNumber, ', ')
from ManPhone P
where (P.phoneType in (1,3) and (P.phoneType IS NOT NULL and P.manCode = MP.manCode
left join medPatient MP on MP.medPatientCode = ?
EOT;
return selectSqlValue($db, $sql, [ $patientId ]); } public function deleteVisitCoupon() { } public function readVisitCouponTalk($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int) isUndefinedProperty($params, 'id'); $visitCouponId = (int) isEmptyProperty($params, 'visitCouponId'); if ($id) { $where = "pvct.pmsVisitCouponTalkCode = ".$id; } else { $where = "pvct.pmsVisitCouponCode = ".$visitCouponId; } $sql = <<<"EOT"
select
    pvct.pmsVisitCouponTalkCode "id",
    to_char(pvct.ChangeTime, 'YYYY-MM-DD HH24:MI:SS') "changeTime",
    pvct.note "note",
    pvct.pmsVisitCouponCode "visitCouponId",
    pvct.state "state"
    /*
    pvct.talkDuration "talkDuration",
    pvct.talkType "talkType",
    */
from PmsVisitCouponTalk pvct
where $where
order by pvct.changeTime desc
EOT;
return selectSql($db, $sql, []); } public function createVisitCouponTalk($records) { $db = $this->db ?: $this->db = connectDb(); $userCode = getSessionValue('mapUserCode'); $sql = <<<"EOT"
INSERT INTO PmsVisitCouponTalk (changeTime, note, pmsVisitCouponCode, state, UserCode, talkType/*, talkDuration*/)
VALUES (now(), ?, ?, ?, ?, 1)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'note'), isEmptyProperty($row, 'visitCouponId'), isEmptyProperty($row, 'state'), $userCode ]); $id = $db->lastInsertId('seq_PmsVisitCouponTalk'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readVisitCouponTalk((object) ['id' => $id]); } public function deleteVisitCouponTalk($records) { } } handleJsonRpc('pmed_reception_visitCoupons'); 