<?php
require_once(__DIR__.'/../../init.php'); class med_common_patientDispensaries { private $db; public function readPatientDispensaries($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $patientId = isEmptyProperty($params, 'patientId', 0); $full = isEmptyProperty($params, 'full', 0); $onDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate', Date('Ymd')))); $condition = $id ? "D.MedDispensaryCode = $id" : "D.MedPatientCode = $patientId"; if(!$id && !$patientId) { throw new Exception("Требуемые параметры не переданы!"); } $inspection = $full ? "and D.FinishDate is NULL" : ""; $patientInfo = $this->readPatientInfo($patientId); $isCard = isEmptyProperty($params, 'isCard', false); $isDispansery = isEmptyProperty($params, 'isDispansery', false); $cardFields = $isCard || $isDispansery ? <<<"EOT"
D.MedDispensaryCode "recordId",
D.StartDate "recordDate",
D.StartDate "parentDate",
COALESCE(D.MedDispensaryCode, '')::text || '-' "path",
7 "recordType",
null "parentId",
null "parentType",
14 "sortType",
CASE D.MotiveCapture
  WHEN 1 THEN 'впервые'
  WHEN 2 THEN 'прибыл'
  WHEN 3 THEN 'по возрасту'
  WHEN 4 THEN 'перевод/изменение'
END "motiveCaptureTextShort",
CASE D.MotiveDiscard
  WHEN 1 THEN 'Выздоровление или стойкое улучшение'
  WHEN 2 THEN 'Выезд из зоны обслуживания медучреждения'
  WHEN 3 THEN 'Передан под наблюдение другому медучреждению'
  WHEN 4 THEN 'Передан под наблюдение органов соцобеспечения'
  WHEN 5 THEN 'Отсутствие сведений в течение года'
  WHEN 6 THEN 'Снятие диагноза'
  WHEN 7 THEN 'Смерть больного'
  WHEN 8 THEN 'Отказ пациента от диспансеризации'
  WHEN 9 THEN 'Перевод, изменение категории, группы наблюдения, диагноза'
END "motiveDiscardText",
EOT
: ''; $sql = <<<"EOT"
SELECT
  $cardFields
  D.MedDispensaryCode                            "id",
  D.StartDate                                    "openDate",
  D.MedDiagnosisCode                             "captureDiagnosId",
  case D.MedDiagnosisCode is not null
    when true then (mdc.Cipher1  || ' - ' || mdc.Name)::text
    else null
  end                                            "diagnosisOpen",
  mdc.Cipher1                                    "diagnosisOpenCipher",
  mdc.Name                                       "diagnosisOpenName",
  D.MedDiagnosisCodeDiscard                      "discardDiagnosId",
  case D.MedDiagnosisCodeDiscard is not null
    when true then (mdd.Cipher1  || ' - ' || mdd.Name)::text
    else null
  end                                            "diagnosisClose",
  mdd.Cipher1                                    "diagnosisCloseCipher",
  mdd.Name                                       "diagnosisCloseName",
  FN_cmFIO(MaP.ManCode,0)                         "patientFIO",
  MaP.DateOfBirth                                 "dateOfBirth",
  P.NumberHistorySickness                        "numberHistorySickness",
  D.MedDoctorCode                                "doctorId",
  FN_cmFIO(doc.ManCode)                          "doctorName",
  D.MedProfessionCode                            "professionId",
  mp.Name                                        "professionName",
  D.DepartmentCode                               "departmentId",
  FN_cmDepartmentName(D.DepartmentCode, 1)::text "departmentName",
  D.FinishDate                                   "closeDate",
  D.Category                                     "category",              /*1-Диспансерный,2-Консультативный*/
  D.MotiveCapture                                "motiveCapture",         /*причина взятия*/
  D.MotiveDiscard                                "motiveDiscard",         /*причина снятия*/
  D.unidictcoderegistration                      "registrationGroupId",   /*группа учета*/
  reg.Value                                      "registrationGroupName", /*группа учета*/
  D.unidictcoderisk                              "riskGroupId",           /*группа риска*/
  risk.Value                                     "riskGroupName",         /*группа учета*/
  D.sicknessdatebegin                            "sicknessDateBegin",     /*дата начала заболевания*/
  D.unidictcodedetect                            "detectId",              /*заболевание выявлено (кем)*/
  COALESCE(D.notWellTimed, 0)                    "notWellTimed",          /*выявлено (не) своевременно*/
  D.MedPatientGroupCode                          "sectorId",              /*участок*/
  D.MedPatientCode                               "patientId",             /*код пациента*/
  (SELECT MIN(dt.AssignedDate)
   FROM MedDispensaryTurnout dt
   WHERE dt.MedDispensaryCode = d.MedDispensaryCode
     AND dt.VisitDate IS NULL
     AND dt.AssignedDate >= $onDate)             "closestTurnoutDate",    /*ближайшая дата назначенной явки*/
  false                                          "needRefreshFlag",
  D.CiszDiagnosisExtraCode                       "captureDiagnosisExtraId",
  CDEC.ClinicalDiagnosis                         "captureClinicalDiagnosis",
  CDEC.CiszResourceValueCodeCancerStage          "captureCancerStageId",
  CDEC.Note                                      "captureNote",
  CDEC.CiszResourceValueCodeDiagKind             "captureKindDiagId",
  CDEC.MedDoctorCode                             "captureDiagnosisDoctorId",
  CDEC.DiagnosisDate                             "captureDiagnosisDate",
  CDEAC.AnatomicCodes                            "captureAnatomic",
  D.CiszDiagnosisExtraCodeDiscard                "discardDiagnosisExtraId",
  CDED.ClinicalDiagnosis                         "discardClinicalDiagnosis",
  CDED.CiszResourceValueCodeCancerStage          "discardCancerStageId",
  CDED.Note                                      "discardNote",
  CDED.CiszResourceValueCodeDiagKind             "discardKindDiagId",
  CDED.MedDoctorCode                             "discardDiagnosisDoctorId",
  CDED.DiagnosisDate                             "discardDiagnosisDate",
  CDEAD.AnatomicCodes                            "discardAnatomic"
FROM MedDispensary AS D
LEFT JOIN MedDiagnosis mdc ON mdc.MedDiagnosisCode = D.MedDiagnosisCode
LEFT JOIN MedDiagnosis mdd ON mdd.MedDiagnosisCode = D.MedDiagnosisCodeDiscard
LEFT JOIN MedProfession mp ON mp.MedProfessionCode = D.MedProfessionCode
LEFT JOIN MedDoctor doc ON doc.MedDoctorCode = D.MedDoctorCode
join MedPatient AS P on P.MedPatientCode = D.MedPatientCode
join Man AS M on M.ManCode = P.ManCode
LEFT JOIN Department dep ON dep.DepartmentCode = D.DepartmentCode
left outer join ManPassport AS MaP on MaP.ManCode = M.ManCode
LEFT JOIN UniDict reg ON reg.UniDictCode = D.unidictcoderegistration
LEFT JOIN UniDict risk ON risk.UniDictCode = D.unidictcoderisk
LEFT JOIN CiszDiagnosisExtra CDEC ON CDEC.CiszDiagnosisExtraCode = D.CiszDiagnosisExtraCode
LEFT JOIN CiszDiagnosisExtra CDED ON CDED.CiszDiagnosisExtraCode = D.CiszDiagnosisExtraCodeDiscard
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEAC ON CDEAC.CiszDiagnosisExtraCode = CDEC.CiszDiagnosisExtraCode
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEAD ON CDEAD.CiszDiagnosisExtraCode = CDED.CiszDiagnosisExtraCode
WHERE $condition $inspection
ORDER BY D.StartDate DESC, D.FinishDate DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $dispanseries = $stmt->fetchAll(); if ($isCard) { $root = $dispanseries; } else { $root->patientInfo = $patientInfo; $root->patientDispensaries = $dispanseries; } if ($id && !$isCard) { $row = $stmt->fetch(); return $row; } else { return $root; } } public function readPatientInfo ($patientId) { suspendJsonRpc(); require_once (__DIR__.'/patients.php'); resumeJsonRpc(); $info = new med_common_patients(); $params = new stdClass(); $params->getFio = true; $params->getCardNumber = true; $params->getAge = true; $params->patientId = $patientId; return $info->readPatientInfo($params); } public function insertOrUpdatePatientDispensaries($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedDispensary (
    MedDispensaryCode, StartDate, MedDiagnosisCode, MedDiagnosisCodeDiscard, MedDoctorCode, MedProfessionCode,
    DepartmentCode, FinishDate, Category, MotiveCapture, MotiveDiscard, unidictcoderegistration, unidictcoderisk,
    sicknessdatebegin, unidictcodedetect, notWellTimed, MedPatientGroupCode, MedPatientCode,
    CiszDiagnosisExtraCode, CiszDiagnosisExtraCodeDiscard)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
  (SELECT MedPatientGroupCode FROM MedPatient WHERE MedPatientCode = ?), ?, ?, ?)
ON CONFLICT (MedDispensaryCode) DO UPDATE SET
    StartDate = case when ? = 1 then EXCLUDED.StartDate else MedDispensary.StartDate end,
    MedDiagnosisCode = case when ? = 1 then EXCLUDED.MedDiagnosisCode else MedDispensary.MedDiagnosisCode end,
    MedDiagnosisCodeDiscard = case when ? = 1 then EXCLUDED.MedDiagnosisCodeDiscard else MedDispensary.MedDiagnosisCodeDiscard end,
    MedDoctorCode = case when ? = 1 then EXCLUDED.MedDoctorCode else MedDispensary.MedDoctorCode end,
    MedProfessionCode = case when ? = 1 then EXCLUDED.MedProfessionCode else MedDispensary.MedProfessionCode end,
    DepartmentCode = case when ? = 1 then EXCLUDED.DepartmentCode else MedDispensary.DepartmentCode end,
    FinishDate = case when ? = 1 then EXCLUDED.FinishDate else MedDispensary.FinishDate end,
    Category = case when ? = 1 then EXCLUDED.Category else MedDispensary.Category end,
    MotiveCapture = case when ? = 1 then EXCLUDED.MotiveCapture else MedDispensary.MotiveCapture end,
    MotiveDiscard = case when ? = 1 then EXCLUDED.MotiveDiscard else MedDispensary.MotiveDiscard end,
    unidictcoderegistration = case when ? = 1 then EXCLUDED.unidictcoderegistration else MedDispensary.unidictcoderegistration end,
    unidictcoderisk = case when ? = 1 then EXCLUDED.unidictcoderisk else MedDispensary.unidictcoderisk end,
    sicknessdatebegin = case when ? = 1 then EXCLUDED.sicknessdatebegin else MedDispensary.sicknessdatebegin end,
    unidictcodedetect = case when ? = 1 then EXCLUDED.unidictcodedetect else MedDispensary.unidictcodedetect end,
    notWellTimed = case when ? = 1 then EXCLUDED.notWellTimed else MedDispensary.notWellTimed end,
    CiszDiagnosisExtraCode = CASE WHEN ? = 1 THEN EXCLUDED.CiszDiagnosisExtraCode ELSE MedDispensary.CiszDiagnosisExtraCode END,
    CiszDiagnosisExtraCodeDiscard = CASE WHEN ? = 1 THEN EXCLUDED.CiszDiagnosisExtraCodeDiscard ELSE MedDispensary.CiszDiagnosisExtraCodeDiscard END
RETURNING MedDispensaryCode AS id
EOT;
$stmt = $db->prepare($sql); $sqlDiagnosis = <<<EOT
INSERT INTO CiszDiagnosisExtra (CiszDiagnosisExtraCode, CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, 
                                Note, MedDoctorCode, DiagnosisDate)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (CiszDiagnosisExtraCode) DO UPDATE SET
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeCancerStage ELSE CiszDiagnosisExtra.CiszResourceValueCodeCancerStage END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeDiagKind ELSE CiszDiagnosisExtra.CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN EXCLUDED.ClinicalDiagnosis ELSE CiszDiagnosisExtra.ClinicalDiagnosis END,
    Note = CASE WHEN ? = 1 THEN EXCLUDED.Note ELSE CiszDiagnosisExtra.Note END,
    MedDoctorCode = CASE WHEN ? = 1 THEN EXCLUDED.MedDoctorCode ELSE CiszDiagnosisExtra.MedDoctorCode END,
    DiagnosisDate = CASE WHEN ? = 1 THEN EXCLUDED.DiagnosisDate ELSE CiszDiagnosisExtra.DiagnosisDate END
RETURNING CiszDiagnosisExtraCode AS id
EOT;
$stmtDiagnosis = $db->prepare($sqlDiagnosis); $stmtExtra = $db->prepare("
            SELECT
                CiszDiagnosisExtraCode,
                CiszDiagnosisExtraCodeDiscard
            FROM MedDispensary
            WHERE MedDispensaryCode = ?
        "); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $diagnosisExtraIds = []; $diagnosisExtraExists = [ 'capture' => false, 'discard' => false ]; if (!$isInsert) { $stmtExtra->execute([isUndefinedProperty($row, 'id')]); $extraCodes = $stmtExtra->fetch(PDO::FETCH_ASSOC); } foreach (['capture', 'discard'] as $prefix) { $extraId = null; if (!$isInsert) { $extraId = $prefix === 'capture' ? $extraCodes['ciszdiagnosisextracode'] : $extraCodes['ciszdiagnosisextracodediscard']; } if ( !property_exists($row, $prefix . 'CancerStageId') && !property_exists($row, $prefix . 'KindDiagId') && !property_exists($row, $prefix . 'ClinicalDiagnosis') && !property_exists($row, $prefix . 'Note') && !property_exists($row, $prefix . 'DiagnosisDoctorId') && !property_exists($row, $prefix . 'DiagnosisDate') && !property_exists($row, $prefix . 'Anatomic') ) { $diagnosisExtraIds[$prefix] = null; continue; } $stmtDiagnosis->execute([ $extraId, isUndefinedProperty($row, $prefix . 'CancerStageId'), isUndefinedProperty($row, $prefix . 'KindDiagId'), isUndefinedProperty($row, $prefix . 'ClinicalDiagnosis'), isUndefinedProperty($row, $prefix . 'Note'), isUndefinedProperty($row, $prefix . 'DiagnosisDoctorId'), isUndefinedProperty($row, $prefix . 'DiagnosisDate'), propertyExistsSQL($row, $prefix . 'CancerStageId'), propertyExistsSQL($row, $prefix . 'KindDiagId'), propertyExistsSQL($row, $prefix . 'ClinicalDiagnosis'), propertyExistsSQL($row, $prefix . 'Note'), propertyExistsSQL($row, $prefix . 'DiagnosisDoctorId'), propertyExistsSQL($row, $prefix . 'DiagnosisDate') ]); $extraId = $stmtDiagnosis->fetchColumn(); $diagnosisExtraIds[$prefix] = $extraId; $diagnosisExtraExists[$prefix] = true; if (property_exists($row, $prefix . 'Anatomic') && $extraId) { $this->updateAnatomic((object)[ 'id' => $extraId, 'items' => isUndefinedProperty($row, $prefix . 'Anatomic') ]); } } $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'openDate'), isUndefinedProperty($row, 'captureDiagnosId'), isUndefinedProperty($row, 'discardDiagnosId'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'departmentId'), isUndefinedProperty($row, 'closeDate'), isUndefinedProperty($row, 'category'), isUndefinedProperty($row, 'motiveCapture'), isUndefinedProperty($row, 'motiveDiscard'), isUndefinedProperty($row, 'registrationGroupId'), isUndefinedProperty($row, 'riskGroupId'), isUndefinedProperty($row, 'sicknessDateBegin'), isUndefinedProperty($row, 'detectId'), isUndefinedProperty($row, 'notWellTimed'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'patientId'), $diagnosisExtraIds['capture'], $diagnosisExtraIds['discard'], propertyExistsSQL($row, 'openDate'), propertyExistsSQL($row, 'captureDiagnosId'), propertyExistsSQL($row, 'discardDiagnosId'), propertyExistsSQL($row, 'doctorId'), propertyExistsSQL($row, 'professionId'), propertyExistsSQL($row, 'departmentId'), propertyExistsSQL($row, 'closeDate'), propertyExistsSQL($row, 'category'), propertyExistsSQL($row, 'motiveCapture'), propertyExistsSQL($row, 'motiveDiscard'), propertyExistsSQL($row, 'registrationGroupId'), propertyExistsSQL($row, 'riskGroupId'), propertyExistsSQL($row, 'sicknessDateBegin'), propertyExistsSQL($row, 'detectId'), propertyExistsSQL($row, 'notWellTimed'), $diagnosisExtraExists['capture'], $diagnosisExtraExists['discard'] ]); $ret[] = $rec = new stdClass; $rec->id = $stmt->fetchColumn(); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientDispensaries($records) { return $this->insertOrUpdatePatientDispensaries($records, true); } public function updatePatientDispensaries($records) { return $this->insertOrUpdatePatientDispensaries($records, false); } public function deletePatientDispensaries($records) { return deleteSql($this->db, 'MedDispensary', 'MedDispensaryCode', $records); } public function readPatientDispensariesTurnouts($params) { $db = $this->db ?: $this->db = connectDb(); $dispensaryId = isEmptyProperty($params, 'dispensaryId'); $sql = <<<"EOT"
SELECT
    dt.MedDispensaryTurnoutCode "id",
    dt.MedDispensaryCode        "dispensaryId",
    dt.AssignedDate             "assignedDate",
    dt.VisitDate                "visitDate"
FROM MedDispensary d
JOIN MedDispensaryTurnout dt ON dt.MedDispensaryCode = d.MedDispensaryCode
WHERE d.MedDispensaryCode = $dispensaryId
ORDER BY dt.AssignedDate, dt.VisitDate
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdatePatientDispensariesTurnouts($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedDispensaryTurnout (MedDispensaryTurnoutCode, MedDispensaryCode, AssignedDate, VisitDate)
VALUES (?, ?, ?, ?)
ON CONFLICT (MedDispensaryTurnoutCode) DO UPDATE SET
    MedDispensaryCode = case when ? = 1 then EXCLUDED.MedDispensaryCode else MedDispensaryTurnout.MedDispensaryCode end,
    AssignedDate = case when ? = 1 then EXCLUDED.AssignedDate else MedDispensaryTurnout.AssignedDate end,
    VisitDate = case when ? = 1 then EXCLUDED.VisitDate else MedDispensaryTurnout.VisitDate end
RETURNING MedDispensaryTurnoutCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'dispensaryId'), isUndefinedProperty($row, 'assignedDate'), isUndefinedProperty($row, 'visitDate'), propertyExistsSQL($row, 'dispensaryId'), propertyExistsSQL($row, 'assignedDate'), propertyExistsSQL($row, 'visitDate') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientDispensariesTurnouts($records) { return $this->insertOrUpdatePatientDispensariesTurnouts($records, true); } public function updatePatientDispensariesTurnouts($records) { return $this->insertOrUpdatePatientDispensariesTurnouts($records, false); } public function deletePatientDispensariesTurnouts($records) { return deleteSql($this->db, 'MedDispensaryTurnout', 'MedDispensaryTurnoutCode', $records); } public function readPatientDispensariesEffects($params) { $db = $this->db ?: $this->db = connectDb(); $dispensaryId = isEmptyProperty($params, 'dispensaryId'); $sql = <<<"EOT"
SELECT
    mde.MedDispensaryEffectCode "id",
    mde.MedDispensaryCode       "dispensaryId",
    mde.DispensaryYear          "dispensaryYear",
    mde.SurveyDate              "surveyDate",
    mde.Efficiency              "efficiency",
    ud.value                    "efficiencyName"
FROM MedDispensary d
JOIN MedDispensaryEffect mde ON mde.MedDispensaryCode = d.MedDispensaryCode
LEFT JOIN UniDict ud ON ud.UniDictCode = mde.Efficiency and ud.DictNumber = 23
WHERE mde.MedDispensaryCode = $dispensaryId
ORDER BY mde.DispensaryYear DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdatePatientDispensariesEffects($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedDispensaryEffect (MedDispensaryEffectCode, MedDispensaryCode, DispensaryYear, SurveyDate, Efficiency)
VALUES (?, ?, ?, ?, ?)
ON CONFLICT (MedDispensaryEffectCode) DO UPDATE SET
    MedDispensaryCode = case when ? = 1 then EXCLUDED.MedDispensaryCode else MedDispensaryEffect.MedDispensaryCode end,
    DispensaryYear = case when ? = 1 then EXCLUDED.DispensaryYear else MedDispensaryEffect.DispensaryYear end,
    SurveyDate = case when ? = 1 then EXCLUDED.SurveyDate else MedDispensaryEffect.SurveyDate end,
    Efficiency = case when ? = 1 then EXCLUDED.Efficiency else MedDispensaryEffect.Efficiency end
RETURNING MedDispensaryEffectCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'dispensaryId'), isUndefinedProperty($row, 'dispensaryYear'), isUndefinedProperty($row, 'surveyDate'), isUndefinedProperty($row, 'efficiency'), propertyExistsSQL($row, 'dispensaryId'), propertyExistsSQL($row, 'dispensaryYear'), propertyExistsSQL($row, 'surveyDate'), propertyExistsSQL($row, 'efficiency') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientDispensariesEffects($records) { return $this->insertOrUpdatePatientDispensariesEffects($records, true); } public function updatePatientDispensariesEffects($records) { return $this->insertOrUpdatePatientDispensariesEffects($records, false); } public function deletePatientDispensariesEffects($records) { return deleteSql($this->db, 'MedDispensaryEffect', 'MedDispensaryEffectCode', $records); } public function readDispensaryDiagnosis($params) { $db = $this->db ?: $this->db = connectDb(); $dispensaryId = isEmptyProperty($params, 'dispensaryId'); $sql = <<<"EOT"
SELECT
    DD.medDispensaryDiagnosisCode "id",
    D.Cipher1 as "cipher",
    D.Cipher1 + ' ' + D.Name as "name",
    D.Cipher1 + ' - ' + D.Name as "diagnosisName",
    DD.DiagnosisType "diagnosisType",
    DD.medDispensaryCode "dispensaryId",
    DD.medDiagnosisCode "diagnosisId",
    DD.CiszDiagnosisExtraCode "diagnosisExtraId",
    CDE.ClinicalDiagnosis "clinicalDiagnosis",
    CDE.CiszResourceValueCodeCancerStage "cancerStageId",
    CDE.Note "note",
    CDE.CiszResourceValueCodeDiagKind "kindDiagId",
    CRV.Name "kindDiagName",
    CDE.MedDoctorCode "diagnosisDoctorId",
    CDE.DiagnosisDate "diagnosisDate",
    CDEA2.AnatomicCodes "anatomic"
FROM medDispensaryDiagnosis as DD
JOIN medDiagnosis as D ON D.medDiagnosisCode = DD.medDiagnosisCode
LEFT JOIN CiszDiagnosisExtra CDE ON CDE.CiszDiagnosisExtraCode = DD.CiszDiagnosisExtraCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = CDE.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
WHERE DD.medDispensaryCode = $dispensaryId
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createDispensaryDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT into medDispensaryDiagnosis (DiagnosisType, medDispensaryCode, medDiagnosisCode, CiszDiagnosisExtraCode)
    VALUES (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $sqlDiagnosis = <<<EOT
INSERT INTO CiszDiagnosisExtra (CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, 
                                Note, MedDoctorCode, DiagnosisDate)
VALUES (?, ?, ?, ?, ?, ?)
RETURNING CiszDiagnosisExtraCode AS id
EOT;
$stmtDiagnosis = $db->prepare($sqlDiagnosis); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $diagnosisExtraId = null; if ( property_exists($row, 'cancerStageId') || property_exists($row, 'kindDiagId') || property_exists($row, 'clinicalDiagnosis') || property_exists($row, 'note') || property_exists($row, 'diagnosisDoctorId') || property_exists($row, 'diagnosisDate') || property_exists($row, 'anatomic') ) { $stmtDiagnosis->execute([ isUndefinedProperty($row, 'cancerStageId'), isUndefinedProperty($row, 'kindDiagId'), isUndefinedProperty($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'diagnosisDoctorId'), isUndefinedProperty($row, 'diagnosisDate') ]); $diagnosisExtraId = $stmtDiagnosis->fetchColumn(); if (property_exists($row, 'anatomic') && $diagnosisExtraId) { $this->updateAnatomic((object)[ 'id' => $diagnosisExtraId, 'items' => $row->anatomic ]); } } $stmt->execute([ isUndefinedProperty($row, 'diagnosisType'), isUndefinedProperty($row, 'dispensaryId'), isUndefinedProperty($row, 'diagnosisId'), $diagnosisExtraId ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medDispensaryDiagnosis'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateDispensaryDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    UPDATE medDispensaryDiagnosis set
        medDiagnosisCode = case when ? = 1 then ? else medDiagnosisCode end,
        CiszDiagnosisExtraCode = CASE WHEN ? = 1 THEN ? ELSE CiszDiagnosisExtraCode END
    WHERE medDispensaryDiagnosisCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlGetExtra = <<<EOT
SELECT CiszDiagnosisExtraCode
FROM medDispensaryDiagnosis
WHERE medDispensaryDiagnosisCode = ?
EOT;
$stmtGetExtra = $db->prepare($sqlGetExtra); $sqlInsertExtra = <<<EOT
INSERT INTO CiszDiagnosisExtra (
    CiszResourceValueCodeCancerStage,
    CiszResourceValueCodeDiagKind,
    ClinicalDiagnosis,
    Note,
    MedDoctorCode,
    DiagnosisDate
)
VALUES (?, ?, ?, ?, ?, ?)
RETURNING CiszDiagnosisExtraCode
EOT;
$stmtInsertExtra = $db->prepare($sqlInsertExtra); $sqlUpdateExtra = <<<EOT
UPDATE CiszDiagnosisExtra SET
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
    MedDoctorCode = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END,
    DiagnosisDate = CASE WHEN ? = 1 THEN ? ELSE DiagnosisDate END
WHERE CiszDiagnosisExtraCode = ?
EOT;
$stmtUpdateExtra = $db->prepare($sqlUpdateExtra); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmtGetExtra->execute([$row->id]); $extraId = $stmtGetExtra->fetchColumn(); $hasExtraFields = property_exists($row, 'cancerStageId') || property_exists($row, 'kindDiagId') || property_exists($row, 'clinicalDiagnosis') || property_exists($row, 'note') || property_exists($row, 'diagnosisDoctorId') || property_exists($row, 'diagnosisDate') || property_exists($row, 'anatomic'); if ($extraId) { $stmtUpdateExtra->execute([ propertyExistsSQL($row, 'cancerStageId'), isUndefinedProperty($row, 'cancerStageId'), propertyExistsSQL($row, 'kindDiagId'), isUndefinedProperty($row, 'kindDiagId'), propertyExistsSQL($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'diagnosisDoctorId'), isUndefinedProperty($row, 'diagnosisDoctorId'), propertyExistsSQL($row, 'diagnosisDate'), isUndefinedProperty($row, 'diagnosisDate'), $extraId ]); $updateExtraFlag = false; } elseif ($hasExtraFields) { $stmtInsertExtra->execute([ isUndefinedProperty($row, 'cancerStageId'), isUndefinedProperty($row, 'kindDiagId'), isUndefinedProperty($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'diagnosisDoctorId'), isUndefinedProperty($row, 'diagnosisDate') ]); $extraId = $stmtInsertExtra->fetchColumn(); $updateExtraFlag = true; } else { $extraId = null; $updateExtraFlag = false; } if (property_exists($row, 'anatomic') && $extraId) { $this->updateAnatomic((object)[ 'id' => $extraId, 'items' => $row->anatomic ]); } $stmt->execute([ propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), $updateExtraFlag, $extraId, $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDispensaryDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medDispensaryDiagnosis', 'medDispensaryDiagnosisCode', $records); } public function getResultText($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $onDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate', Date('Ymd')))); $ret = []; if (!$id) { return null; } $sql = <<<"EOT"
SELECT DISTINCT
    CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture <> (4 )WHEN true THEN  E'Взят на учёт. ' WHEN false THEN  E'' END "field1",
    CASE COALESCE(mD.FinishDate, 30000101) = DATE($onDate) WHEN true THEN  E'Снят с учета. ' WHEN false THEN  E'' END "field2",
    CASE mDt.VisitDate = DATE($onDate) WHEN true THEN  E'Осмотрен. ' WHEN false THEN  E'' END as "field3",
    CASE mDt2.AssignedDate > DATE($onDate) WHEN true THEN  E'Явка назначена на ' + DATEFORMAT(mDt2.AssignedDate,  E'dd.mm.yyyy' ) +  E'. ' WHEN false THEN  E'' END "field4",
    CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture = 4
    WHEN true THEN  E'Переведён с диагноза: ' + mDi2.Cipher1 +  E' - ' + mDi2.Name +
    CASE COALESCE(mD.medDoctorCode, 0) <> (COALESCE(mD2.medDoctorCode, 0) )WHEN true THEN  E', к врачу: ' + mP.Name +  E' - ' + FN_cmFIO(mDoc.ManCode,1)  WHEN false THEN  E'' END +
    CASE COALESCE(mD.DepartmentCode, 0) <> (COALESCE(mD2.DepartmentCode, 0) )WHEN true THEN  E', в отделение: ' + COALESCE( NULLIF( asa_trim( D.FullName ),  E'' ), D.Name ) WHEN false THEN  E'' END +
    CASE COALESCE(mD.MedPatientGroupCode, 0) <> (COALESCE(mD2.MedPatientGroupCode, 0) )WHEN true THEN  E', на участок: ' + mPG.Name WHEN false THEN  E'' END +
    CASE COALESCE(mD.Category, 0) <> (COALESCE(mD2.Category, 0) )WHEN true THEN  E', в категорию учета: ' +
    CASE COALESCE(mD.Category, 0) = 1 WHEN true THEN  E'диспансерный' WHEN false THEN CASE COALESCE(mD.Category, 0) = 2 WHEN true THEN  E'консультативный' WHEN false THEN  E'' END END WHEN false THEN  E'' END +
    CASE COALESCE(mD.UniDictCodeRegistration, 0) <>  (COALESCE(mD2.UniDictCodeRegistration, 0) )WHEN true THEN  E', в группу учета: ' + UD1.Value WHEN false THEN  E'' END +
     CASE COALESCE(mD.UniDictCodeRisk, 0) <>  (COALESCE(mD2.UniDictCodeRisk, 0) )WHEN true THEN  E', в группу риска: ' + UD2.Value WHEN false THEN  E'' END +
      E'. ' WHEN false THEN  E'' END "field5",
    CASE mDE.MedDispensaryEffectCode IS NOT NULL AND mDE.SurveyDate = DATE($onDate) WHEN true THEN  E'Оценка эффективности ДН за ' + STR(mDE.DispensaryYear) +  E'г. - ' + UD.Value +  E'. ' WHEN false THEN  E'' END "field6",
    CASE mD.Category = 1 WHEN true THEN  E'Д-учёт по диагнозу ' WHEN false THEN  E'К-учёт по диагнозу ' END + mDi.Cipher1 +  E' - ' + mDi.Name +
    CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture = 4 WHEN true THEN CASE COALESCE(mD2.UniDictCodeRegistration, 0) <> (0 )WHEN true THEN  E', группа учета: ' + UDO1.Value WHEN false THEN  E'' END WHEN false THEN CASE COALESCE(mD.UniDictCodeRegistration, 0) <> (0 )WHEN true THEN  E', группа учета: ' + UD1.Value WHEN false THEN  E'' END END + E': ' as "field7",
    (CASE mD.Category = 1 WHEN true THEN  E'Д-учёт по диагнозу ' WHEN false THEN  E'К-учёт по диагнозу ' END + mDi.Cipher1 +  E' - ' + mDi.Name +
    CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture = 4 WHEN true THEN CASE COALESCE(mD2.UniDictCodeRegistration, 0) <> (0 )WHEN true THEN  E', группа учета: ' + UDO1.Value WHEN false THEN  E'' END WHEN false THEN CASE COALESCE(mD.UniDictCodeRegistration, 0) <> (0 )WHEN true THEN  E', группа учета: ' + UD1.Value WHEN false THEN  E'' END END + E': ' ) + (CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture <> (4 )WHEN true THEN  E'Взят на учёт. ' WHEN false THEN  E'' END ) + (CASE mD.StartDate = DATE($onDate) AND mD.MotiveCapture = 4
    WHEN true THEN  E'Переведён с диагноза: ' + mDi2.Cipher1 +  E' - ' + mDi2.Name +
    CASE COALESCE(mD.medDoctorCode, 0) <> (COALESCE(mD2.medDoctorCode, 0) )WHEN true THEN  E', к врачу: ' + mP.Name +  E' - ' + FN_cmFIO(mDoc.ManCode,1)  WHEN false THEN  E'' END +
    CASE COALESCE(mD.DepartmentCode, 0) <> (COALESCE(mD2.DepartmentCode, 0) )WHEN true THEN  E', в отделение: ' + COALESCE( NULLIF( asa_trim( D.FullName ),  E'' ), D.Name ) WHEN false THEN  E'' END +
    CASE COALESCE(mD.MedPatientGroupCode, 0) <> (COALESCE(mD2.MedPatientGroupCode, 0) )WHEN true THEN  E', на участок: ' + mPG.Name WHEN false THEN  E'' END +
    CASE COALESCE(mD.Category, 0) <> (COALESCE(mD2.Category, 0) )WHEN true THEN  E', в категорию учета: ' +
    CASE COALESCE(mD.Category, 0) = 1 WHEN true THEN  E'диспансерный' WHEN false THEN CASE COALESCE(mD.Category, 0) = 2 WHEN true THEN  E'консультативный' WHEN false THEN  E'' END END WHEN false THEN  E'' END +
    CASE COALESCE(mD.UniDictCodeRegistration, 0) <>  (COALESCE(mD2.UniDictCodeRegistration, 0) )WHEN true THEN  E', в группу учета: ' + UD1.Value WHEN false THEN  E'' END +
     CASE COALESCE(mD.UniDictCodeRisk, 0) <>  (COALESCE(mD2.UniDictCodeRisk, 0) )WHEN true THEN  E', в группу риска: ' + UD2.Value WHEN false THEN  E'' END +
      E'. ' WHEN false THEN  E'' END ) + (CASE mDt.VisitDate = DATE($onDate) WHEN true THEN  E'Осмотрен. ' WHEN false THEN  E'' END ) + (CASE mDE.MedDispensaryEffectCode IS NOT NULL AND mDE.SurveyDate = DATE($onDate) WHEN true THEN  E'Оценка эффективности ДН за ' + STR(mDE.DispensaryYear) +  E'г. - ' + UD.Value +  E'. ' WHEN false THEN  E'' END ) + (CASE mDt2.AssignedDate > DATE($onDate) WHEN true THEN  E'Явка назначена на ' + DATEFORMAT(mDt2.AssignedDate,  E'dd.mm.yyyy' ) +  E'. ' WHEN false THEN  E'' END ) + (CASE COALESCE(mD.FinishDate, 30000101) = DATE($onDate) WHEN true THEN  E'Снят с учета. ' WHEN false THEN  E'' END ) "disp"
FROM MedDispensary AS mD
LEFT OUTER JOIN MedDiagnosis AS mDi ON mDi.MedDiagnosisCode = mD.MedDiagnosisCode
LEFT OUTER JOIN MedDispensaryTurnout AS mDt ON mDt.MedDispensaryCode = mD.MedDispensaryCode
LEFT OUTER JOIN MedDispensaryTurnout AS mDt2 ON mDt2.AssignedDate = (SELECT MAX(MedDispensaryTurnout.AssignedDate) FROM MedDispensaryTurnout WHERE MedDispensaryTurnout.MedDispensaryCode = mD.MedDispensaryCode) AND COALESCE(mDt2.VisitDate, 30000101) = 30000101
LEFT OUTER JOIN MedDispensaryEffect AS mDE ON mDE.MedDispensaryCode = mD.MedDispensaryCode
LEFT OUTER JOIN UniDict AS UD ON UD.UniDictCode = mDE.Efficiency AND UD.DictNumber = 23
LEFT OUTER JOIN MedDispensary AS mD2 ON mD2.MedDiagnosisCodeDiscard = mD.MedDiagnosisCode AND mD2.FinishDate = DATEADD('DAY', -1, mD.StartDate) AND mD2.MedPatientCode = mD.MedPatientCode AND mD2.MotiveDiscard = 9
LEFT OUTER JOIN MedDiagnosis AS mDi2 ON mDi2.MedDiagnosisCode = mD2.MedDiagnosisCode
LEFT OUTER JOIN MedDoctor AS mDoc ON mDoc.medDoctorCode = mD.medDoctorCode
LEFT OUTER JOIN MedProfession AS mP ON mP.MedProfessionCode = mDoc.MedProfessionCode
LEFT OUTER JOIN Department AS D ON D.DepartmentCode = mD.DepartmentCode
LEFT OUTER JOIN MedPatientGroup AS mPG ON mPG.MedPatientGroupCode = mD.MedPatientGroupCode
LEFT OUTER JOIN UniDict AS UD1 ON UD1.UniDictCode = mD.UniDictCodeRegistration AND UD1.DictNumber = 13
LEFT OUTER JOIN UniDict AS UD2 ON UD2.UniDictCode = mD.UniDictCodeRisk AND UD2.DictNumber = 14
LEFT OUTER JOIN UniDict AS UDO1 ON UDO1.UniDictCode = mD2.UniDictCodeRegistration AND UDO1.DictNumber = 13
WHERE mD.MedDispensaryCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); $row = $stmt->fetch(); if ($row) { $ret[$id] = $row['field7'].$row['field1'].$row['field5'].$row['field3'].$row['field6'].$row['field4'].$row['field2']; } return $ret; } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $diagnosisCiszId = isEmptyProperty($params, 'id'); $items = isUndefinedProperty($params, 'items'); if (!$diagnosisCiszId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM CiszDiagnosisExtraAnatomic
        WHERE CiszDiagnosisExtraCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $diagnosisCiszId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO CiszDiagnosisExtraAnatomic
            (CiszDiagnosisExtraCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $diagnosisCiszId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM CiszDiagnosisExtraAnatomic
            WHERE CiszDiagnosisExtraCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$diagnosisCiszId], $toDelete)); } return true; } public function readPatientDispensariesStudy($params) { $db = $this->db ?: $this->db = connectDb(); $dispensaryId = isEmptyProperty($params, 'dispensaryId'); $sql = <<<"EOT"
SELECT
    MDS.medDispensaryStudyCode "id",
    MDS.AppointmentDate "assignedDate",
    MDS.MedLogStudyCode "studyId",
    MLS.Type "studyType",
    MLS.ExecTime "resultDate",
    CASE
    WHEN NULLIF(TRIM(MLS.Result), '') IS NOT NULL THEN
        MLS.Result
    WHEN NULLIF(TRIM(MLS.Conclusion), '') IS NOT NULL THEN
        MLS.Conclusion
    WHEN MLS.MedDoctorCodeExec IS NOT NULL THEN
        FN_cmFIO(MD.ManCode, 1) || ', ' || MPR.Name
    ELSE
        FN_cmFIO(BM.ManCode, 1)
    END AS "result",
    MS.Name "studyName"
FROM MedDispensaryStudy as MDS
LEFT JOIN MedLogStudy MLS ON MDS.MedLogStudyCode = MLS.MedLogStudyCode
LEFT JOIN MedStudy MS ON MS.MedStudyCode = MLS.MedStudyCode
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MLS.MedDoctorCodeExec
LEFT JOIN BuhMol BM ON BM.BuhMolCode = MLS.BuhMolCodeExec
LEFT JOIN MedProfession MPR ON MPR.MedProfessionCode = MD.MedProfessionCode
WHERE MDS.MedDispensaryCode = $dispensaryId
ORDER BY 2 DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPatientDispensariesStudy($records) { return $this->insertOrUpdatePatientDispensariesStudy($records, true); } public function updatePatientDispensariesStudy($records) { return $this->insertOrUpdatePatientDispensariesStudy($records, false); } public function deletePatientDispensariesStudy($records) { return deleteSql($this->db, 'MedDispensaryStudy', 'MedDispensaryStudyCode', $records); } public function insertOrUpdatePatientDispensariesStudy($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedDispensaryStudy (MedDispensaryStudyCode, MedDispensaryCode, MedLogStudyCode, AppointmentDate)
VALUES (?, ?, ?, ?)
ON CONFLICT (MedDispensaryStudyCode) DO UPDATE SET
    MedDispensaryCode = CASE WHEN ? = 1 THEN EXCLUDED.MedDispensaryCode ELSE MedDispensaryStudy.MedDispensaryCode END,
    MedLogStudyCode = CASE WHEN ? = 1 THEN EXCLUDED.MedLogStudyCode ELSE MedDispensaryStudy.MedLogStudyCode END,
    AppointmentDate = CASE WHEN ? = 1 THEN EXCLUDED.AppointmentDate ELSE MedDispensaryStudy.AppointmentDate END
RETURNING MedDispensaryStudyCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'dispensaryId'), isUndefinedProperty($row, 'studyId'), isUndefinedProperty($row, 'assignedDate'), propertyExistsSQL($row, 'dispensaryId'), propertyExistsSQL($row, 'studyId'), propertyExistsSQL($row, 'assignedDate') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('med_common_patientDispensaries');