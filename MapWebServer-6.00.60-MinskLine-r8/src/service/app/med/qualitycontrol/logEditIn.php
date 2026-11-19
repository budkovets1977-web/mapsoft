<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_logEditIn { private $db; function __construct() { $this->db = connectDb(); } public function readLogEditIn($params = null) { $db = $this->db; $sqlParams = []; $criteriaLogId = isEmptyProperty($params, 'criteriaLogId'); $patientId = isEmptyProperty($params, 'patientId'); if (!$criteriaLogId || !$patientId) { return false; } else { $sqlCriteriaLogId = " AND MQL.MedQualityLogCode = :criteriaLogId AND MQLI.MedPatientCode = :criteriaPatientId"; $sqlParams[':criteriaLogId'] = $criteriaLogId; $sqlParams[':criteriaPatientId'] = $patientId; } $sqlCriteriaAll = <<<"EOT"
SELECT 
    MQLC.Mark "criteriaAnswerMark",
    MQLC.Note "criteriaAnswerNote",
    MQC.Name "criteriaName",
    MQC.Code "criteriaCode",
    MQC.FullName "criteriaFullName",
    MQC.CalcMark "criteriaCalcMark",
    MQC.Mark "criteriaMark",
    MQC.Note "criteriaNote",
    MQC.Type "criteriaType",
    MQC.MedQualityCriteriaCode "questionId",
    MQLC.MedQualityLogCriteriaCode "answerId",
    MQLI.MedQualityLogInCode "logInCode"
FROM MedQualityLog MQL
LEFT JOIN MedQualityLogIn MQLI ON MQL.MedQualityLogCode = MQLI.MedQualityLogCode
LEFT JOIN MedQualityCriteriaKind MQCK ON MQCK.MedQualityCriteriaKindCode = MQL.MedQualityCriteriaKindCode
LEFT JOIN MedQualityCriteria MQC ON MQC.MedQualityCriteriaKindCode = MQL.MedQualityCriteriaKindCode
LEFT JOIN MedQualityLogCriteria MQLC ON MQLC.MedQualityLogInCode = MQLI.MedQualityLogInCode AND MQLC.MedQualityCriteriaCode = MQC.MedQualityCriteriaCode
WHERE 1=1
$sqlCriteriaLogId
ORDER BY MQC.Position
EOT;
$sqlMainCriteria = <<<"EOT"
SELECT 
    MQCK.name "criteriaMainName",
    MQLI.mark "mainAnswerMark",
    MQLI.conclusiondate "conclusionDate",
    MQLI.medqualitylogincode "logInCode"
FROM MedQualityLog MQL
LEFT JOIN MedQualityLogIn MQLI ON MQL.MedQualityLogCode = MQLI.MedQualityLogCode
LEFT JOIN MedQualityCriteriaKind MQCK ON MQCK.MedQualityCriteriaKindCode = MQL.MedQualityCriteriaKindCode
WHERE 1=1
$sqlCriteriaLogId
LIMIT 1
EOT;
$data = new stdClass(); $data->criteriaAll = selectSql($db, $sqlCriteriaAll, $sqlParams); $data->mainCriteria = selectSql($db, $sqlMainCriteria, $sqlParams); return $data; } public function createLogEditIn($records) { return insertSql($this->db, 'medqualitylogcriteria', $records, "mark, medqualitycriteriacode, medqualitylogincode, note", "criteriaAnswerMark, questionId, logInCode, criteriaAnswerNote" ); } public function updateLogEditIn($records) { return updateSql($this->db, 'medqualitylogcriteria','medqualitylogcriteriacode', $records, 'mark note', 'criteriaAnswerMark criteriaAnswerNote', 'answerId' ); } public function updateQualityLogIn($record = null) { if (!$record) return false; $db = $this->db; $record->date = dbDate(new DateTime()); return updateSql($db, 'medqualitylogin', 'medqualitylogincode', $record, 'conclusiondate mark', 'date mark'); } } handleJsonRpc('med_qualitycontrol_logEditIn'); 