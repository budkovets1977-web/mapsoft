<?php
require_once(__DIR__ . '/../../../../init.php'); class med_cisz_serviceRequest_hospitalisation_importer { private PDO_ANY $db; use med_cisz_utilsTrait; const string KIND_OF_DIAGNOSIS_DICTIONARY = 'KindOfDiagnosisCode'; const string CANCER_STAGE_DICTIONARY = 'StagesOfMalignTum'; public function __construct() { $this->db = connectDb(); } public function import($requestData):object { $db = $this->db; $medPatientCode = $requestData->medPatientCode ?? null; try { $db->beginTransaction(); $medLogCode = $this->insertMedLog($medPatientCode, $requestData); $medLogDiagnosisCodes = $this->insertMedLogDiagnosis($medLogCode, $requestData); $ciszDocExtraCode = $this->insertCiszDocExtra($medLogCode, $requestData); $db->commit(); return (object)[ 'medLogCode' => $medLogCode, 'medLogDiagnosisCodes' => $medLogDiagnosisCodes, 'ciszDocExtraCode' => $ciszDocExtraCode, ]; } catch (Exception $e) { $db->rollBack(); throw $e; } } private function insertMedLog($medPatientCode, $requestData):int { $db = $this->db; $hospitalisationReasonUse = selectSqlValue($db, <<<"SQL"
SELECT CRV.ciszResourceValueCode FROM CiszResource CR JOIN CiszResourceValue CRV ON CRV.CiszResourceCode = CR.CiszResourceCode
        WHERE CR.Identifier = 'HospitalisationReasonUse' AND CRV.code = '1';
SQL)
; $enterpriseCode = selectSqlValue($db, <<<"SQL"
SELECT E.EnterpriseCode FROM EnterpriseNumber E WHERE E.ciszGuid = :reqFromOrganizationUuid
SQL,
[ ':reqFromOrganizationUuid' => $requestData->reqFromOrganizationUuid ?? '' ] ); $sqlMedLog = <<<"SQL"
INSERT INTO MedLog(medPatientCode ,enterpriseSendCode, directdate, incomedate, ciszresourcevaluecodehosptype, numberhistory)
VALUES
(:medPatientCode, :enterpriseSendCode, :directDate, CURRENT_TIMESTAMP, :reasonResourceValueCode, :numberHistory)
RETURNING MedLogCode;
SQL;
return selectSqlValue($db, $sqlMedLog, [ ':medPatientCode' => $medPatientCode, ':enterpriseSendCode' => $enterpriseCode, ':directDate' => dbDate( $requestData->authoredOn ?? null ), ':reasonResourceValueCode' => $hospitalisationReasonUse, ':numberHistory' => $this->getNumberHistory() ]); } private function insertMedLogDiagnosis($medLogCode, $requestData):array { $db = $this->db; $diagnosisType = 1; $sql = <<<"SQL"
INSERT INTO MedLogDiagnosis(diagnosisType, meddiagnosiscode, medLogCode)
VALUES
(:diagnosisType, :diagnosisCode, :medLogCode)
RETURNING MedLogDiagnosisCode;
SQL;
$resultData = []; $diagnoses = $requestData->diagnoses ?? []; $sqlCiszDiagnosisExtra = <<<"SQL"
INSERT INTO ciszDiagnosisExtra(
    ciszResourceValueCodeCancerStage, 
    ciszResourceValueCodeDiagKind, 
    clinicalDiagnosis,
    note,
    diagnosisDate,
    medDoctorCode)
VALUES (
    :resourceValueCancerStage,
    :resourceValueDiagKind,
    :clinicalDiagnosis,
    :note,
    :diagnosisDate,
    null)
RETURNING ciszDiagnosisExtraCode;
SQL;
foreach ($diagnoses as $diagnosis) { $diagnosisCode = selectSqlValue($db, <<<"SQL"
SELECT medDiagnosisCode FROM MedDiagnosis WHERE cipher1 = :cipher
SQL,
[':cipher' => $diagnosis->code ?? '']); $diagnosisInfo = new stdClass(); $diagnosisInfo->medLogDiagnosisCode = selectSqlValue($db, $sql, [ ':diagnosisType' => $diagnosisType, ':diagnosisCode' => $diagnosisCode, ':medLogCode' => $medLogCode ]); $resourceValueKindDiagnosis = ($diagnosis->kindCode ?? '') ? self::getSourceCodeByCiszCode($diagnosis->kindCode, self::KIND_OF_DIAGNOSIS_DICTIONARY) : null; $resourceValueCancerStage = ($diagnosis->stageCode ?? '') ? self::getSourceCodeByCiszCode($diagnosis->stageCode, self::CANCER_STAGE_DICTIONARY) : null; $ciszDiagnosisExtraCode = selectSqlValue($db, $sqlCiszDiagnosisExtra, [ ':resourceValueDiagKind' => $resourceValueKindDiagnosis, ':resourceValueCancerStage' => $resourceValueCancerStage, ':clinicalDiagnosis' => $diagnosis->clinicalDiagnosis ?? '', ':note' => $diagnosis->note ?? '', ':diagnosisDate' => dbDate($diagnosis->assertedDate ?? null) ]); $diagnosisInfo->ciszDiagnosisExtraCode = $ciszDiagnosisExtraCode; $resultData[] = $diagnosisInfo; } return $resultData; } private function insertCiszDocExtra($medLogCode, $requestData): int { $db = $this->db; $docType = 4; $sql = <<<"SQL"
INSERT INTO CiszDocExtra(documentType, json, medLogCode)
VALUES
(:docType, :json, :medLogCode)
RETURNING CiszDocExtraCode;
SQL;
return selectSqlValue($db, $sql, [ ':docType' => $docType, ':json' => json_encode($requestData, JSON_UNESCAPED_UNICODE), ':medLogCode' => $medLogCode ]); } private function getNumberHistory():int { $db = $this->db; return selectSqlValue($db, <<<"SQL"
WITH RECURSIVE t(n) AS (
    SELECT COALESCE(NumberHistory, '')::text
    FROM MedStatCard
    WHERE MedStatCardCode = (
        SELECT MAX(MedStatCardCode) 
        FROM MedStatCard 
        WHERE EXTRACT(YEAR FROM InDate) = EXTRACT(YEAR FROM CURRENT_DATE)
          AND COALESCE(NumberHistory, '') <> ''
    )
    
    UNION ALL
    
    SELECT 
        INCSTR(TRIM(RIGHT(REPEAT(' ',20)+TRIM(t.n),20)))
    FROM t
    INNER JOIN MedStatCard msc 
        ON t.n = COALESCE(msc.NumberHistory, '') 
       AND EXTRACT(YEAR FROM msc.InDate) = EXTRACT(YEAR FROM CURRENT_DATE)
       AND COALESCE(msc.NumberHistory, '') <> ''
)

SELECT MAX(t.n) FROM t;
SQL)
; } }