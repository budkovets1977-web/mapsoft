<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once (__DIR__ . '/../../med/qualitycontrol/log.php'); resumeJsonRpc(); class med_qualitycontrol_documents { private $db; const ACCESS_OTHER_USERS_EDITING = 'med-qualitycontrol-log-other-users-editing'; public function readDocuments($params = null) { $db = $this->db ?: $this->db = connectDb(); $logId = isUndefinedProperty($params, 'logId'); $sql = <<<"EOT"
SELECT
    MQL.MedQualityLogInCode "id",
    FN_cmFIO(MP.ManCode) "patientFIO",
    MQL.MedPatientCode "patientId",
    MQL.Mark "mark",
    MQL.MedQualityLogCode "logId",
    MQL.ConclusionDate "conclusionDate",
    MP.NumberHistorySickness "numberHistorySickness",
    (SELECT STRING_AGG(MQLI.note, '<br><br>')
      FROM
          (SELECT
          MQC.Code || ' - ' || CASE WHEN MQLC.Mark = 1 THEN 'Да'
                                    WHEN MQLC.Mark = 0 THEN 'Нет'
                                    WHEN MQLC.Mark = 0.5 THEN '0.5'
                                    ELSE 'Не применялось'
                                END
                   || ' - '
                   || MQLC.Note "note"
          FROM MedQualityLogCriteria MQLC
          LEFT JOIN MedQualityCriteria MQC ON MQC.MedQualityCriteriaCode = MQLC.MedQualityCriteriaCode
          WHERE MQLC.MedQualityLogInCode = MQL.MedQualityLogInCode AND MQLC.Note IS NOT NULL AND MQLC.Note <> ''
          ORDER BY RIGHT(REPEAT(E' ', 20) + asa_trim(MQC.name), 20)
               ) MQLI) "note"
FROM MedQualityLogin MQL
LEFT JOIN MedPatient MP on MQL.MedPatientCode = MP.MedPatientCode
WHERE MQL.MedQualityLogCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $logId ]); return $stmt->fetchAll(); } public function createDocuments($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'MedQualityLogin', $records, 'MedQualityLogCode, MedPatientCode, Mark, ConclusionDate', 'logId, patientId, mark, conclusionDate'); } public function deleteDocuments($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [ $records ]; } $accessGranted = false; $accessOtherUsersEditing = testUserAccess(self::ACCESS_OTHER_USERS_EDITING); $userDoctorId = (int)getSessionValue('mapMedDoctor')['MedDoctorCode']; $log = new med_qualitycontrol_log(); $params = new stdClass(); $params->id = isEmptyProperty($records[0], 'logId'); $logData = $log->readLog($params); if ($logData->total > 0) { $logData = $logData->items[0]; $chairManDoctorId = $logData['chairManDoctorId']; $experts = $logData['experts']; $accessGranted = ($accessOtherUsersEditing || (in_array($userDoctorId, $experts) || $userDoctorId === $chairManDoctorId)); } if (!$accessGranted) { return false; } return deleteSql($db, 'MedQualityLogin', 'medqualitylogincode', $records); } } handleJsonRpc('med_qualitycontrol_documents');