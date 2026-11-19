<?php
require_once(__DIR__.'/../../init.php'); class med_dayhospital_logDrugs { private $db; public function readLogDrugs($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ''; $whereArr = []; $logId = isEmptyProperty($params, 'logId'); if ($logId) { $sqlParams[':logId'] = $logId; $whereArr[] = '(MDHP.MedDayHospitalCode = :logId)'; $this->updatePositions($logId); } if (!empty($whereArr)) { $where = 'WHERE ' . implode(' AND ', $whereArr); } $sql = <<<"EOT"
SELECT 
    MDHP.MedDayHopsitalPurposeCode "id",
    MDHP.MedDayHospitalCode "logId",
    MDHP.MedDoctorCode "doctorId",
    FN_cmFIO(MD.manCode) "doctorFio",
    MP.name "professionName",
    D.name "departmentName",
    MDHP.MatWealthCode "drugId",
    MWO.name "drugName",
    MDHP.dose "dose",
    MWM.name "measureDoseName",
    MDHP.MedGraphAcceptCode "graphId",
    MGA.name "graphName",
    MDHP.purposeTime "startTime",
    MDHP.cancelTime "finishTime",
    MDHP.MedPurposeMethodCode "methodId",
    MPM.name "methodName",
    MDHP.note "note",
    MDHP.position "position"
FROM MedDayHopsitalPurpose MDHP 
LEFT JOIN MedDoctor MD ON MD.medDoctorCode = MDHP.medDoctorCode
LEFT JOIN MedProfession MP ON MP.medProfessionCode = MD.medProfessionCode
LEFT JOIN Department D ON D.departmentCode = MD.departmentCode
LEFT JOIN MatWealth MW ON MW.MatWealthCode = MDHP.MatWealthCode
LEFT JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
LEFT JOIN MatWealthMeasure MWM ON MWM.MatWealthMeasureCode = MW.MatWealthMeasureDoseSizeCode
LEFT JOIN MedPurposeMethod MPM ON MPM.MedPurposeMethodCode = MDHP.MedPurposeMethodCode
LEFT JOIN MedGraphAccept MGA ON MGA.MedGraphAcceptCode = MDHP.MedGraphAcceptCode
$where
ORDER BY MDHP.position nulls last
EOT;
return selectSql($db, $sql, $sqlParams); } protected function updatePositions ($logId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
WITH records AS (
SELECT
    MDHP.MedDayHopsitalPurposeCode,
    ROW_NUMBER() OVER(ORDER BY MDHP.position nulls last, MDHP.MedDayHopsitalPurposeCode) "pos"
FROM MedDayHopsitalPurpose MDHP
WHERE MDHP.MedDayHospitalCode = :logId
)
UPDATE MedDayHopsitalPurpose MDHP
SET position = (
    SELECT 
        r.pos
    FROM records r
    WHERE R.MedDayHopsitalPurposeCode = MDHP.MedDayHopsitalPurposeCode
    )
WHERE MedDayHospitalCode = :logId
EOT;
return selectSql($db, $sql, [':logId' => $logId]); } public function readDrugInfo ($params) { $db = $this->db ?: $this->db = connectDb(); $drugId = isEmptyProperty($params, 'drugId'); $sql = <<<"EOT"
SELECT 
    MW.MatWealthCode "id",
    MW.doseSize "dose",
    MWO.name "name",
    MWM.name "measureDoseName"
FROM MatWealth MW
LEFT JOIN MatWealthMeasure MWM ON MWM.MatWealthMeasureCode = MW.MatWealthMeasureDoseSizeCode
LEFT JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
WHERE MW.MatWealthCode = :id
EOT;
return selectSql($db, $sql, [':id' => $drugId])[0]; } public function updateLogDrugs($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $records, "MedDayHospitalCode MedDoctorCode MatWealthCode dose MedGraphAcceptCode purposeTime cancelTime MedPurposeMethodCode position Note", "logId doctorId drugId dose graphId startTime finishTime methodId position note" ); } public function createLogDrugs($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'MedDayHopsitalPurpose', $records, 'MedDayHospitalCode MedDoctorCode MatWealthCode dose MedGraphAcceptCode purposeTime cancelTime MedPurposeMethodCode position Note', 'logId doctorId drugId dose graphId startTime finishTime methodId position note'); } public function deleteLogDrugs($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedDayHopsitalPurpose', 'MedDayHopsitalPurposeCode', $records); } } handleJsonRpc('med_dayhospital_logDrugs');