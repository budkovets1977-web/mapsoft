<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/clinicstats/visits.php'); resumeJsonRpc(); class med_clinicstats_logHospitals { private $db; const DOCUMENTS_SOURCE = 30; public function readLogHospitals($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $dateType = isEmptyProperty($params, 'dateType', 0); $organization = isEmptyProperty($params, 'organizationId'); $diagnosis = isEmptyProperty($params, 'diagnosisId'); $patient = isEmptyProperty($params, 'patientId'); $doctor = isEmptyProperty($params, 'doctorId'); $epicrisisExist = isEmptyProperty($params, 'epicrisisExist', 0); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sort = isEmptyProperty($params, 'sort', 'id'); $dir = isEmptyProperty($params, 'dir', ''); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sqlFromWhere = "
FROM MedLogHospital MLH
       LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MLH.MedDoctorCode
       LEFT JOIN MedProfile MPr ON MPr.MedProfileCode = MLH.MedProfileCode
       LEFT JOIN MedDoctor MD3 ON MD3.MedDoctorCode = MLH.MedDoctorCodeRegistry
       LEFT JOIN MedPatient MP ON MP.MedPatientCode = MLH.MedPatientCode
       LEFT JOIN Enterprise E ON E.EnterpriseCode = MLH.EnterpriseCodeHospital
       LEFT JOIN MedDiagnosis MD2 ON MD2.MedDiagnosisCode = MLH.MedDiagnosisCodeClinic
       LEFT JOIN MedTrauma MT ON MT.MedTraumaCode = MLH.MedTraumaCode
       LEFT JOIN UniDict UD ON (UD.UniDictCode=MLH.UnidictCodeResult AND UD.DictNumber=38)
       LEFT JOIN CiszResourceValue CRV_SPEC ON CRV_SPEC.CiszResourceValueCode = MLH.CiszResourceValueCodeSpecialization
       LEFT JOIN CiszResourceValue CRV_CAT ON CRV_CAT.CiszResourceValueCode = MLH.CiszResourceValueCodeMedServCat
       LEFT JOIN CiszResourceValue CRV_REJECT ON CRV_REJECT.CiszResourceValueCode = MLH.CiszResourceValueCodeRejectCause
       LEFT JOIN MedDiagnosis MDI ON MDI.MedDiagnosisCode = MLH.MedDiagnosisCodeDirect
       LEFT JOIN Department DEP ON DEP.DepartmentCode = MLH.DepartmentCode
where MLH.EnterpriseCode = $mapEnterpriseCode  "; if (isset($id)) { $sqlFromWhere .= " and MLH.MedLogHospitalCode = $id"; } switch ($dateType) { case 0: { if (isset($startDate)) { $sqlFromWhere .= " and MLH.directDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MLH.directDate <= $finishDate"; } break; } case 1: { if (isset($startDate)) { $sqlFromWhere .= " and MLH.hospitalDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MLH.hospitalDate <= $finishDate"; } break; } case 2: { if (isset($startDate)) { $sqlFromWhere .= " and MLH.outDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MLH.outDate <= $finishDate"; } break; } case 3: { if (isset($startDate)) { $sqlFromWhere .= " and MLH.hospitalDate <= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MLH.hospitalDate >= $finishDate"; } break; } } switch ($epicrisisExist) { case 1: { $sqlFromWhere .= " and MLH.epicrisisDate IS NOT NULL"; break; } case 2: { $sqlFromWhere .= " and MLH.epicrisisDate IS NULL"; break; } } if (isset($organization)) { $sqlFromWhere .= " and MLH.EnterpriseCodeHospital = $organization"; } if (isset($diagnosis)) { $sqlFromWhere .= " and MLH.MedDiagnosisCodeClinic = $diagnosis"; } if (isset($patient)) { $sqlFromWhere .= " and MLH.MedPatientCode = $patient"; } if (isset($doctor)) { $sqlFromWhere .= " and MLH.MedDoctorCode = $doctor"; } $sql = <<<"EOT"
select
       COUNT(*) "total"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
SELECT
       MLH.MedLogHospitalCode  "id",
       MLH.NumberOrder "number",
       MLH.MedDoctorCode "medDoctorCode",
       MLH.MedDoctorCodeRegistry "medDoctorCodeRegistry",
       MLH.MedPatientCode "medPatientCode",
       FN_cmFIO(MD.ManCode,1) "doctorFIO",
       FN_cmFIO(MD3.ManCode,1) "doctorRegistryFIO",
       FN_cmFIO(MP.ManCode,1) "patientFIO",
       FN_cmFIO(MP.ManCode) "patientFIOFull",
       FN_cmFIO(MD.ManCode) "doctorFIOFull",
       MLH.HospitalKind "hospitalKind",
       CASE
           WHEN MLH.HospitalKind = 1 THEN 'Плановая'
           ELSE 'Экстренная'
       END "kindName",
       MLH.FailReason "failReason",
       MLH.MedProfileCode "medProfileCode",
       MPr.name "medProfileName",
       MLH.DirectDate "directDate",
       MLH.HospitalDate "hospitalDate",
       MLH.OutDate "outHospitalDate",
       MLH.EpicrisisDate "epicrisisDate",
       MLH.EpicrisisDateRefined "epicrisisDateRefined",
       MLH.EnterpriseCodeHospital "enterpriseCodeHospital",
       E.Name "enterpriseHospital",
       MLH.MedDiagnosisCodeDirect "medDiagnosisCodeDirect",
       MLH.MedDiagnosisCodeClinic "medDiagnosisCodeClinic",
       MLH.SicknessKind "sicknessKind",
       MT.MedTraumaCode "traumaId",
       MT.Name "traumaDisplay",
       MT.Code "traumaCode",
       MD2.Name "DiagnosisClinicName",
       MD2.Cipher1 "DiagnosisClinicCipher",
       MLH.UniDictCodeResult "uniDictCodeResult",
       MLH.EnterpriseCode "enterpriseCode",
       UD.Value "hospitalResult",
       MLH.medStatCardCode "statCardId",
       MLH.AgreedWith "agreedWith",
       MLH.CiszResourceValueCodeSpecialization "specializationCode",
       CRV_SPEC.Name "specializationName",
       MLH.CiszResourceValueCodeMedServCat "healthCareCode",
       CRV_CAT.Name "healthCareName",
       MLH.CiszResourceValueCodeHospGoal "goal",
       MLH.ConfirmationDate "confirmationDate",
       MLH.Note "note",
       MLH.RejectDate "refusalDate",
       MLH.RejectBy "refusalBy",
       MLH.CiszResourceValueCodeRejectCause "refusalReasonCode",
       CRV_REJECT.Name "refusalReasonName",
       MLH.PlanDate "planStartDate",
       MLH.PlanDateFinish "planFinishDate",
       (CASE WHEN MDI.Cipher1 IS NULL THEN E''
           ELSE MDI.Cipher1 || E' - '
           END) || MDI.Name  "medDiagnosisNameDirect",
       MLH.PatientInstruction "instruction",
       MLH.DepartmentCode "departmentId",
       DEP.Name "departmentName"
$sqlFromWhere
order by "$sort" $dir
limit $limit offset $offset
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function createLogHospitals($records) { return $this->syncLogHospitals($records, true); } public function updateLogHospitals($records) { return $this->syncLogHospitals($records, false); } public function syncLogHospitals($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $ret = []; $sql = <<<"EOT"
insert into MedLogHospital (MedLogHospitalCode, NumberOrder, MedDoctorCode, MedDoctorCodeRegistry,MedPatientCode,HospitalKind,
    FailReason,MedProfileCode,DirectDate,HospitalDate,OutDate,EpicrisisDate,
    EpicrisisDateRefined,EnterpriseCodeHospital,MedDiagnosisCodeClinic,UniDictCodeResult,SicknessKind,MedTraumaCode,EnterpriseCode, medDiagnosisCodeDirect,
    AgreedWith, CiszResourceValueCodeSpecialization, CiszResourceValueCodeMedServCat, CiszResourceValueCodeHospGoal,
    ConfirmationDate, Note, RejectDate, RejectBy, CiszResourceValueCodeRejectCause, PlanDate, PlanDateFinish, PatientInstruction, DepartmentCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
on conflict (MedLogHospitalCode) do update set
    NumberOrder = case when ? = 1 then EXCLUDED.NumberOrder else MedLogHospital.NumberOrder end,
    MedDoctorCode = case when ? = 1 then EXCLUDED.MedDoctorCode else MedLogHospital.MedDoctorCode end,
    MedDoctorCodeRegistry = case when ? = 1 then EXCLUDED.MedDoctorCodeRegistry else MedLogHospital.MedDoctorCodeRegistry end,
    MedPatientCode = case when ? = 1 then EXCLUDED.MedPatientCode else MedLogHospital.MedPatientCode end,
    HospitalKind = case when ? = 1 then EXCLUDED.HospitalKind else MedLogHospital.HospitalKind end,
    FailReason = case when ? = 1 then EXCLUDED.FailReason else MedLogHospital.FailReason end,
    MedProfileCode = case when ? = 1 then EXCLUDED.MedProfileCode else MedLogHospital.MedProfileCode end,
    DirectDate = case when ? = 1 then EXCLUDED.DirectDate else MedLogHospital.DirectDate end,
    HospitalDate = case when ? = 1 then EXCLUDED.HospitalDate else MedLogHospital.HospitalDate end,
    OutDate = case when ? = 1 then EXCLUDED.OutDate else MedLogHospital.OutDate end,
    EpicrisisDate = case when ? = 1 then EXCLUDED.EpicrisisDate else MedLogHospital.EpicrisisDate end,
    EpicrisisDateRefined = case when ? = 1 then EXCLUDED.EpicrisisDateRefined else MedLogHospital.EpicrisisDateRefined end,
    EnterpriseCodeHospital = case when ? = 1 then EXCLUDED.EnterpriseCodeHospital else MedLogHospital.EnterpriseCodeHospital end,
    MedDiagnosisCodeClinic = case when ? = 1 then EXCLUDED.MedDiagnosisCodeClinic else MedLogHospital.MedDiagnosisCodeClinic end,
    UniDictCodeResult = case when ? = 1 then EXCLUDED.UniDictCodeResult else MedLogHospital.UniDictCodeResult end,
    SicknessKind = case when ? = 1 then EXCLUDED.SicknessKind else MedLogHospital.SicknessKind end,
    MedTraumaCode = case when ? = 1 then EXCLUDED.MedTraumaCode else MedLogHospital.MedTraumaCode end,
    medDiagnosisCodeDirect = case when ? = 1 then EXCLUDED.medDiagnosisCodeDirect else MedLogHospital.medDiagnosisCodeDirect end,
    AgreedWith = CASE WHEN ? = 1 THEN EXCLUDED.AgreedWith ELSE MedLogHospital.AgreedWith END,
    CiszResourceValueCodeSpecialization = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeSpecialization ELSE MedLogHospital.CiszResourceValueCodeSpecialization END,
    CiszResourceValueCodeMedServCat = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeMedServCat ELSE MedLogHospital.CiszResourceValueCodeMedServCat END,
    CiszResourceValueCodeHospGoal = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeHospGoal ELSE MedLogHospital.CiszResourceValueCodeHospGoal END,
    ConfirmationDate = CASE WHEN ? = 1 THEN EXCLUDED.ConfirmationDate ELSE MedLogHospital.ConfirmationDate END,
    Note = CASE WHEN ? = 1 THEN EXCLUDED.Note ELSE MedLogHospital.Note END,
    RejectDate = CASE WHEN ? = 1 THEN EXCLUDED.RejectDate ELSE MedLogHospital.RejectDate END,
    RejectBy = CASE WHEN ? = 1 THEN EXCLUDED.RejectBy ELSE MedLogHospital.RejectBy END,
    CiszResourceValueCodeRejectCause = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeRejectCause ELSE MedLogHospital.CiszResourceValueCodeRejectCause END,
    PlanDate = CASE WHEN ? = 1 THEN EXCLUDED.PlanDate ELSE MedLogHospital.PlanDate END,
    PlanDateFinish = CASE WHEN ? = 1 THEN EXCLUDED.PlanDateFinish ELSE MedLogHospital.PlanDateFinish END,
    PatientInstruction = CASE WHEN ? = 1 THEN EXCLUDED.PatientInstruction ELSE MedLogHospital.PatientInstruction END,
    DepartmentCode = CASE WHEN ? = 1 THEN EXCLUDED.DepartmentCode ELSE MedLogHospital.DepartmentCode END
    RETURNING MedLogHospitalCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isEmptyProperty($row, 'id', null), isUndefinedProperty($row, 'number'), isEmptyProperty($row, 'medDoctorCode'), isEmptyProperty($row, 'medDoctorCodeRegistry'), isEmptyProperty($row, 'medPatientCode'), isUndefinedProperty($row, 'hospitalKind'), isUndefinedProperty($row, 'failReason'), isEmptyProperty($row, 'medProfileCode'), isUndefinedProperty($row, 'directDate'), isUndefinedProperty($row, 'hospitalDate'), isUndefinedProperty($row, 'outHospitalDate'), isUndefinedProperty($row, 'epicrisisDate'), isUndefinedProperty($row, 'epicrisisDateRefined'), isEmptyProperty($row, 'enterpriseCodeHospital'), isEmptyProperty($row, 'medDiagnosisCodeClinic'), isEmptyProperty($row, 'uniDictCodeResult'), isEmptyProperty($row, 'sicknessKind'), isEmptyProperty($row, 'traumaId'), $mapEnterpriseCode, isEmptyProperty($row, 'medDiagnosisCodeDirect'), isUndefinedProperty($row, 'agreedWith'), isEmptyProperty($row, 'specializationCode'), isEmptyProperty($row, 'healthCareCode'), isEmptyProperty($row, 'goal'), isEmptyProperty($row, 'confirmationDate'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'refusalDate'), isEmptyProperty($row, 'refusalBy'), isEmptyProperty($row, 'refusalReasonCode'), isEmptyProperty($row, 'planStartDate'), isEmptyProperty($row, 'planFinishDate'), isEmptyProperty($row, 'instruction'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'number'), propertyExistsSQL($row, 'medDoctorCode'), propertyExistsSQL($row, 'medDoctorCodeRegistry'), propertyExistsSQL($row, 'medPatientCode'), propertyExistsSQL($row, 'hospitalKind'), propertyExistsSQL($row, 'failReason'), propertyExistsSQL($row, 'medProfileCode'), propertyExistsSQL($row, 'directDate'), propertyExistsSQL($row, 'hospitalDate'), propertyExistsSQL($row, 'outHospitalDate'), propertyExistsSQL($row, 'epicrisisDate'), propertyExistsSQL($row, 'epicrisisDateRefined'), propertyExistsSQL($row, 'enterpriseCodeHospital'), propertyExistsSQL($row, 'medDiagnosisCodeClinic'), propertyExistsSQL($row, 'uniDictCodeResult'), propertyExistsSQL($row, 'sicknessKind'), propertyExistsSQL($row, 'traumaId'), propertyExistsSQL($row, 'medDiagnosisCodeDirect'), propertyExistsSQL($row, 'agreedWith'), propertyExistsSQL($row, 'specializationCode'), propertyExistsSQL($row, 'healthCareCode'), propertyExistsSQL($row, 'goal'), propertyExistsSQL($row, 'confirmationDate'), propertyExistsSQL($row, 'note'), propertyExistsSQL($row, 'refusalDate'), propertyExistsSQL($row, 'refusalBy'), propertyExistsSQL($row, 'refusalReasonCode'), propertyExistsSQL($row, 'planStartDate'), propertyExistsSQL($row, 'planFinishDate'), propertyExistsSQL($row, 'instruction'), propertyExistsSQL($row, 'departmentId') ]); $rec->id = fetchValue($stmt, 'id'); if (property_exists($row, 'inspections')) { $this->syncHospitalInspections( (int)$rec->id, is_array($row->inspections) ? $row->inspections : [] ); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function syncHospitalInspections(int $hospitalId, array $incomingInspects): void { $db = $this->db ?: $this->db = connectDb(); $incomingInspects = array_map('intval', $incomingInspects); $incomingInspects = array_unique($incomingInspects); $stmt = $db->prepare("
        SELECT CiszResourceValueCodeAction
        FROM MedLogHospitalAction
        WHERE MedLogHospitalCode = ?
    "); $stmt->execute([$hospitalId]); $existing = $stmt->fetchAll(PDO::FETCH_COLUMN); $existing = array_map('intval', $existing); $toAdd = array_diff($incomingInspects, $existing); $toRemove = array_diff($existing, $incomingInspects); if ($toAdd) { $stmtInsert = $db->prepare("
            INSERT INTO MedLogHospitalAction
                (MedLogHospitalCode, CiszResourceValueCodeAction)
            VALUES (?, ?)
        "); foreach ($toAdd as $actionCode) { $stmtInsert->execute([$hospitalId, $actionCode]); } } if ($toRemove) { $in = implode(',', array_fill(0, count($toRemove), '?')); $stmtDelete = $db->prepare("
            DELETE FROM MedLogHospitalAction
            WHERE MedLogHospitalCode = ?
              AND CiszResourceValueCodeAction IN ($in)
        "); $stmtDelete->execute(array_merge([$hospitalId], $toRemove)); } } public function readHospitalInspections($params = null) { $db = $this->db ?: $this->db = connectDb(); $hospitalId = isset($params->hospitalId) && isEmptyProperty($params, 'hospitalId') ? $params->hospitalId : null; $sql = <<<"EOT"
SELECT
        LH.CiszResourceValueCodeAction "id",
        CRV.Name "name"
        FROM MedLogHospitalAction LH
           LEFT JOIN CiszResourceValue CRV ON LH.CiszResourceValueCodeAction = CRV.CiszResourceValueCode
        WHERE LH.MedLogHospitalCode = $hospitalId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getHealthcareCategory($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
        CRV.CiszResourceValueCode "id",
        CRV.Name "name"
        FROM CiszResourceValue CRV
            JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = 'HealthcareServiceCategory'
        WHERE CRV.Code = '02/2'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(PDO::FETCH_ASSOC); } public function getPatientSex($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $sql = <<<"EOT"
SELECT
        P.sex "sex"
        FROM ManPassport P
            JOIN MedPatient MP ON MP.ManCode = P.ManCode
        WHERE MP.MedPatientCode = $patientId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(PDO::FETCH_ASSOC); } public function checkNumber($params = null) { $db = $this->db ?: $this->db = connectDb(); $number = isset($params->number) && isEmptyProperty($params, 'number') ? $params->number : null; if ($number === null) { return true; } $sql = <<<"EOT"
SELECT 1
    FROM MedLogHospital MLH
        WHERE MLH.NumberOrder = $number
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $result = $stmt->fetch(PDO::FETCH_ASSOC); return $result === false; } public function deleteLogHospitals($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLogHospital', 'MedLogHospitalCode', $records); } public function readLogHospitalsDiagnosis($params = null) { $db = $this->db ?: $this->db = connectDb(); $hospitalId = isset($params->hospitalId) && isEmptyProperty($params, 'hospitalId') ? $params->hospitalId : null; $sql = <<<"EOT"
SELECT
        LH.MedLogHospitalDiagnosisCode  "id",
        LH.MedLogHospitalCode "hospitalCode",
        LH.MedDiagnosisCode "diagnosisId",
        LH.SicknessKind "sicknessKind",
        LH.MedTraumaCode "traumaId",
        MT.Name "traumaDisplay",
        MT.Code "traumaCode",
        MD.Name "name",
        MD.Cipher1 "cipher"
        FROM MedLogHospitalDiagnosis LH
           LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = LH.MedDiagnosisCode
           LEFT JOIN MedTrauma MT ON MT.MedTraumaCode = LH.MedTraumaCode
        WHERE LH.MedLogHospitalCode = $hospitalId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createLogHospitalsDiagnosis($records) { return $this->syncLogHospitalsDiagnosis($records, true); } public function updateLogHospitalsDiagnosis($records) { return $this->syncLogHospitalsDiagnosis($records, false); } public function getControlDays($diagnosisId) { $visit_module = new med_clinicstats_visits(); return $visit_module->checkControlDays($diagnosisId); } public function checkPeriodDiagnosis($params, $controlDays) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patient'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $outDate = isEmptyProperty($params, 'outDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'outDate'))) : null; $sql = <<<"EOT"
    SELECT
        mvp.MedVisitPatientCode
    FROM MedVisitPatient as mvp
    LEFT JOIN MedVisit as mv ON mv.MedVisitCode = mvp.MedVisitCode
    LEFT JOIN MedDiagnosis as md ON md.MedDiagnosisCode = mvp.MedDiagnosisCode
    WHERE mvp.MedPatientCode = :patientId
        AND mvp.medDiagnosisCode = :diagnosisId
        AND MV.VisitDate between DATEADD('Day', -1 * :controlDays, :outDate) AND DATEADD('Day', :controlDays, :outDate)
        AND md.DiagnosisType = 2
EOT;
$sqlParams=[':patientId'=>$patientId,':diagnosisId'=> $diagnosisId,':controlDays'=>$controlDays,':outDate'=>$outDate]; return +!!selectSqlValue($db, $sql,$sqlParams); } public function generateVisitCase($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patient'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $outDate = isEmptyProperty($params, 'outDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'outDate'))) : null; $controlDays = $this->getControlDays($diagnosisId); $periodDiagnosis = $this->checkPeriodDiagnosis($params, $controlDays); $sql = <<< "EOT"
        SELECT
            mvp.medVisitPatientCode
        from MedVisitPatient mvp
        LEFT JOIN MedVisit AS mv ON mv.medVisitCode = mvp.medVisitCode
        LEFT JOIN MedDiagnosis AS md ON md.medDiagnosisCode = mvp.medDiagnosisCode
        WHERE mvp.medPatientCode = :patientId AND mvp.medDiagnosisCode = :diagnosisId
            AND mvp.visitCase = 1
            AND (mvp.visitEnd <> 2 OR mvp.visitEnd IS NULL)
            AND (
                    (md.diagnosisType = 1 AND YEAR(Mv.VisitDate) = YEAR(:outDate))
                OR
                    (md.diagnosisType = 2 AND :periodDiagnosis = 1)
                )
EOT;
$sqlParams=[':patientId' => $patientId,':diagnosisId' => $diagnosisId,':outDate' => $outDate,':periodDiagnosis' => $periodDiagnosis]; return selectSqlValue($db, $sql, $sqlParams) ? 2 : 1; } public function syncLogHospitalsDiagnosis($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedLogHospitalDiagnosis (MedLogHospitalDiagnosisCode, MedLogHospitalCode, MedDiagnosisCode, SicknessKind, MedTraumaCode)
values (?, ?, ?, ?, ?)
on conflict (MedLogHospitalDiagnosisCode) do update set
    MedLogHospitalCode = case when ? = 1 then EXCLUDED.MedLogHospitalCode else MedLogHospitalDiagnosis.MedLogHospitalCode end,
    MedDiagnosisCode = case when ? = 1 then EXCLUDED.MedDiagnosisCode else MedLogHospitalDiagnosis.MedDiagnosisCode end,
    SicknessKind = case when ? = 1 then EXCLUDED.SicknessKind else MedLogHospitalDiagnosis.SicknessKind end,
    MedTraumaCode = case when ? = 1 then EXCLUDED.MedTraumaCode else MedLogHospitalDiagnosis.MedTraumaCode end
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ $isInsert ? null : isEmptyProperty($row, 'id', null), isEmptyProperty($row, 'hospitalCode'), isEmptyProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'sicknessKind'), isEmptyProperty($row, 'traumaId'), propertyExistsSQL($row, 'hospitalCode'), propertyExistsSQL($row, 'diagnosisId'), propertyExistsSQL($row, 'sicknessKind'), propertyExistsSQL($row, 'traumaId'), ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } public function deleteLogHospitalsDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sqlDelete = <<<EOT
            DELETE FROM MedVisitPatient
            WHERE MedLogHospitalCode = :logHospitalId
                AND MedDiagnosisCode = :diagnosisId
EOT;
$stmtDelete = $db->prepare($sqlDelete); foreach ($records as $row) { $logHospitalId = isEmptyProperty($row, 'hospitalCode'); $diagnosisId = isEmptyProperty($row, 'diagnosisId'); deleteSql($db, 'MedLogHospitalDiagnosis', 'MedLogHospitalDiagnosisCode', isEmptyProperty($row, 'id')); $stmtDelete->execute([ ':logHospitalId' => $logHospitalId, ':diagnosisId' => $diagnosisId ]); } return true; } public function searchDiagnos($params = null) { $db = $this->db ?: $this->db = connectDb(); $cipher = isset($params) ? $params : null; $sql = <<<"EOT"
SELECT
       MD.MedDiagnosisCode  "id"
       FROM MedDiagnosis MD
       WHERE MD.Cipher1 = '$cipher'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getMaxNumber($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select COALESCE(incstr((SELECT MAX(NumberOrder) FROM MedLogHospital)),1) AS "maxCipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCipher = fetchValue($stmt, 'maxCipher'); return $maxCipher; } public function readLogHospitalsOpers($params = null) { $db = $this->db ?: $this->db = connectDb(); $hospitalId = isEmptyProperty($params, 'hospitalId'); $sql = <<<"EOT"
SELECT
       LO.MedLogHospitalOperCode  "id",
       LO.MedLogHospitalCode "hospitalCode",
       LO.MedOperationCode "operationId",
       LO.OperationDate "operationDate",
       MO.Name "name",
       MOP.Name "className"
       FROM MedLogHospitalOper LO
       LEFT JOIN MedOperation MO ON MO.MedOperationCode = LO.MedOperationCode
       LEFT JOIN medOperation MOP ON MOP.medOperationCode = MO.medOperationParentCode
       WHERE LO.MedLogHospitalCode = $hospitalId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createLogHospitalsOpers($records) { return $this->syncLogHospitalsOpers($records, true); } public function updateLogHospitalsOpers($records) { return $this->syncLogHospitalsOpers($records, false); } public function syncLogHospitalsOpers($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedLogHospitalOper (MedLogHospitalOperCode, MedLogHospitalCode, MedOperationCode, OperationDate)
values (?, ?, ?, ?)
on conflict (MedLogHospitalOperCode) do update set
    MedLogHospitalCode = case when ? = 1 then EXCLUDED.MedLogHospitalCode else MedLogHospitalOper.MedLogHospitalCode end,
    MedOperationCode = case when ? = 1 then EXCLUDED.MedOperationCode else MedLogHospitalOper.MedOperationCode end,
    OperationDate = case when ? = 1 then EXCLUDED.OperationDate else MedLogHospitalOper.OperationDate end
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ $isInsert ? null : isEmptyProperty($row, 'id', null), isEmptyProperty($row, 'hospitalCode'), isEmptyProperty($row, 'operationId'), isUndefinedProperty($row, 'operationDate'), propertyExistsSQL($row, 'hospitalCode'), propertyExistsSQL($row, 'operationId'), propertyExistsSQL($row, 'operationDate'), ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } public function deleteLogHospitalsOpers($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLogHospitalOper', 'MedLogHospitalOperCode', $records); } public function createMedVisit($params) { $db = $this->db ?: $this->db = connectDb(); $records = $params->records; $hospitalId = isEmptyProperty($params, 'hospitalCode'); $sqlDelete = <<<"EOT"
 DELETE FROM MedVisitPatient WHERE MedLogHospitalCode = $hospitalId
EOT;
$stmtDelete = $db->prepare($sqlDelete); $db->beginTransaction(); try { $stmtDelete->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } foreach ($records as $row) { $doctor = isEmptyProperty($row, 'doctor'); $outDate = isEmptyProperty($row, 'outDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($row, 'outDate'))) : null; $sqlVisit = <<<"EOT"
SELECT
       MV.MedVisitCode "medVisitCode"
       FROM MedVisit MV
       WHERE MV.MedDoctorCode = :doctor and  MV.VisitDate = :outDate
EOT;
$sqlParams = [':doctor' => $doctor,':outDate'=>$outDate]; $stmtVisit = $db->prepare($sqlVisit); $stmtVisit->execute($sqlParams); $visitId = fetchValue($stmtVisit, 'medVisitCode'); $visitCase = isEmptyProperty($row, 'isGeneral') ? null : $this->generateVisitCase($row); if (!isset($visitId)) { $sqlDoctor = <<<"EOT"
SELECT
       MD.DepartmentCode "departmentCode",
       MD.MedProfessionCode "medProfessionCode"
       FROM MedDoctor MD
       WHERE MD.MedDoctorCode = $doctor
EOT;
$stmtDoctor = $db->prepare($sqlDoctor); $stmtDoctor->execute(); $ret = $stmtDoctor->fetch(); $medProfessionCode = $ret['medProfessionCode']; $departmentCode = $ret['departmentCode']; $sqlVisit2 = <<<"EOT"
with ins as (
INSERT INTO MedVisit (VisitDate, MedDoctorCode, MedProfessionCode, DepartmentCode)
VALUES (?, ?, ?, ?)
 returning  *
) select * from ins
EOT;
$stmtVisit2 = $db->prepare($sqlVisit2); $db->beginTransaction(); $stmtVisit2->execute([ isset($outDate) ? $outDate : null, isset($doctor) ? $doctor : null, isset($medProfessionCode) ? $medProfessionCode : null, isset($departmentCode) ? $departmentCode : null ]); $visitId = (int) $db->lastInsertId('seq_MedVisit'); $db->commit(); } $sql = <<<"EOT"
   INSERT INTO MedVisitPatient (MedVisitCode,MedPatientCode, MedDiagnosisCode, MedLogHospitalCode, MedTraumaCode,
    VisitMark, ServiceForm, VisitPlace, SicknessKind, AttendantDiagnosis, VisitCase, VisitEndNew, VisitMarkNew, VisitCaseNew)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $visitId, isEmptyProperty($row, 'patient'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'hospitalCode'), isEmptyProperty($row, 'traumaId'), 1, 1, 0, isUndefinedProperty($row, 'sicknessKind'), 1, $visitCase, 4, 1, 1 ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } } public function generateSicknessKind($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $sql = <<< "EOT"
    select
    a.VisitDate, b.MedVisitPatientCode
    from MedVisit a
    join MedVisitPatient b on a.MedVisitCode = b.MedVisitCode
    where MedPatientCode = $patientId and MedDiagnosisCode = $diagnosisId and
    a.VisitDate =(
        select
            MAX (a.VisitDate)
        from MedVisit a
        join MedVisitPatient b on  a.MedVisitCode = b.MedVisitCode where MedPatientCode = $patientId and MedDiagnosisCode = $diagnosisId
    )
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll() ? 4 : 2; } public function GetDistrictDoctor($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $sql = <<< "EOT"
    select
        mpcg.MedDoctorCode "id",
        FN_cmFIO(
            (select ManCode from medDoctor where MedDoctorCode = mpcg.MedDoctorCode)
        ) "fio"
    from MedPatient as mp
    left join medpatientgroup as mpcg on mpcg.medpatientgroupcode = mp.medpatientgroupcode
    where mp.medpatientcode = $patientId
    limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(); } public function readExternalDocuments($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    DISTINCT ON (MEFT.MedExternalFileCode)
    MEFT.MedExternalFileCode    "id",
    MEFT.CreateTime             "createTime",
    MEFT.UserCode               "creatorId",
    MEFT.Version                "version",
    MEFT.FilePath               "filePath",
    MEFT.FileName               "fileName",
    MEFT.FileTime               "fileTime",
    MEFT.Source                 "source",
    MEFT.SourceCode             "sourceId",
    CASE
      WHEN MEFT.Source = 10 THEN -1             -- -1 = Консультации врачей
      WHEN MEFT.Source = 20 THEN MLS.Type       --  1 = Лабораторные исследования,
                                                --  2 = Лучевые исследования,
                                                --  3 = Функциональные исследования
      ELSE 0                                    --  0 = Другие документы
    END                         "sectionType"
FROM
    MedExternalFileT MEFT
    LEFT JOIN MedVisitPatient MVP ON MVP.MedPatientCode = :patientCode
    LEFT JOIN MedLogStudy MLS ON MLS.MedPatientCode = :patientCode
WHERE (MEFT.Source = 10 AND MEFT.SourceCode = MVP.MedVisitPatientCode)
   OR (MEFT.Source = 20 AND MEFT.SourceCode = MLS.MedLogStudyCode)
   OR (MEFT.Source = 110 AND MEFT.SourceCode = :patientCode)
ORDER BY MEFT.MedExternalFileCode, "createTime" DESC
EOT;
return selectSql($db, $sql, [ 'patientCode' => isEmptyProperty($params, 'sourceId', 0), ]); } public function readLogHospitalDocuments($params) { $db = $this->db ?: $this->db = connectDb(); $vaccineDocumentId = isEmptyProperty($params, 'vaccineDocumentId'); $where = ''; if ($vaccineDocumentId) { $where = " AND MEFT.MedExternalFileCode = $vaccineDocumentId"; } $sql = <<<"EOT"
select
    MEFT.MedExternalFileCode "id",
    MEFT.createTime "createTime",
    MEFT.userCode "creatorId",
    MEFT.version "version",
    MEFT.filePath "filePath",
    MEFT.fileName "fileName",
    MEFT.fileTime "fileTime",
    MEFT.source "source",
    MEFT.sourceCode "sourceId"
from MedExternalFileT MEFT
where MEFT.source = ? and MEFT.sourceCode = ?
$where
order by "createTime" desc
EOT;
return selectSql($db, $sql, [ isEmptyProperty($params, 'source', self::DOCUMENTS_SOURCE), isEmptyProperty($params, 'sourceId', 0) ]); } public function createLogHospitalDocuments($record = null) { $db = $this->db ?: $this->db = connectDb(); $fileName = str_replace(',', '_', $_FILES['document']['name']); $db->beginTransaction(); try { $source = isEmptyProperty($record, 'source', self::DOCUMENTS_SOURCE); $sourceId = isEmptyProperty($record, 'sourceId'); $sql = <<<EOT
    insert into medExternalFileT as MEFT
        (data, source, sourceCode, fileName)
    values
        (:fileData, :source, :sourceId, :fileName)
    EOT;
$stmt = $db->prepare($sql); $fileData = file_get_contents($_FILES['document']['tmp_name']); unlink($_FILES['document']['tmp_name']); $stmt->bindParam(':fileData', $fileData, PDO::PARAM_LOB); $stmt->bindParam(':source', $source, PDO::PARAM_INT); $stmt->bindParam(':sourceId', $sourceId, PDO::PARAM_INT); $stmt->bindParam(':fileName', $fileName, PDO::PARAM_STR); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $rec = new stdClass; $rec->success = true; echo json_encode($rec); } public function getDocumentFromBD($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       fileexists       "fileexists",
       filename         "filename",
       storagefilename  "storagefilename",
       data::bytea      "data"
from medExternalFile
where medexternalfilecode = ?
EOT;
$rows = selectSql($db, $sql, [ isEmptyProperty($params, 'id', 0) ]); if ($rows === false) { error_log('1: Нету связанного вложения!'); return 1; } $row = reset($rows); if (empty($row['fileexists'])) { error_log('2: Отсутствует файл!'); return 2; } return $row; } public function deleteLogHospitalDocuments($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medExternalFileT', 'medExternalFileCode', $records); } public function readDocument ($params) { $type = isEmptyProperty($params, 'type'); if ($type == 0) { $document = $this->getPatientPhoto($params); return; } else if ($type == 1) { $document = $this->getAttachmentFromBD($params); } if (is_int($document)) { $this->buildResponseError($document); return; } $this->buildResponse($document); } private function buildResponseError($error) { switch ($error) { case 1: $text = 'Нету связанного вложения!'; break; case 2: $text = 'Отсутствует файл!'; break; default: $text = 'Не удалось загрузить вложение!'; break; } echo <<<html
{$text}
html;
} private function buildResponse($attachment) { $fileName = str_replace("\\", '_', $attachment['storagefilename']); $filePath = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . "{$fileName}"; file_put_contents("{$filePath}", $attachment['data']); $contentType = mime_content_type($filePath); header("Content-Type: {$contentType}"); header('Cache-control: private, no-store, no-cache, must-revalidate, max-age=0'); header('Connection: close'); header('Content-Disposition: inline; filename="'.basename($attachment['filename']).'"'); readfile("{$filePath}"); unlink("{$filePath}"); } public function getAttachmentFromBD($params) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = [ isEmptyProperty($params, 'sourceId', 0), isEmptyProperty($params, 'source', 0) ]; $where = 'where sourceCode = ? and source = ?'; $fileId = isEmptyProperty($params, 'fileId', 0); if ($fileId) { array_push($sqlParams, $fileId); $where .= ' and medexternalfilecode = ?'; } $sql = <<<"EOT"
select
       fileexists       "fileexists",
       filename         "filename",
       storagefilename  "storagefilename",
       data::bytea      "data"
from medExternalFile
$where
EOT;
$rows = selectSql($db, $sql, $sqlParams); if ($rows === false) { error_log('1: Нету связанного вложения!'); return 1; } $row = reset($rows); if (empty($row['fileexists'])) { error_log('2: Отсутствует файл!'); return 2; } return $row; } } handleJsonRpc('med_clinicstats_logHospitals');