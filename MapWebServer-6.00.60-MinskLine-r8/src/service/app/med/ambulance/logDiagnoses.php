<?php
 require_once(__DIR__ . '/../../init.php'); require_once(__DIR__ . '/logStats.php'); class med_ambulance_logDiagnoses { private $db; private function sessionConnectDb() { return $this->db ?: $this->db = connectDb(); } public function readDiagnoses($params) { $this->sessionConnectDb(); $sql = <<<"EOT"
select
    ALD.AmbLogDiagnosisCode "id",
    ALD.ambLogCode          "ambLogId",
    ALD.medDiagnosisCode    "diagnosisId",
    ALD.diagnosisType       "type",
    ALD.ClinicalDiagnosis   "clinicalDiagnosis",
    MD.name                 "diagnosisName",
    MD.cipher1               "diagnosisCipher"   
from AmbLogDiagnosis ALD
LEFT JOIN meddiagnosis MD ON ALD.meddiagnosiscode = MD.meddiagnosiscode
where ALD.ambLogCode = ?
order by id
EOT;
return selectSql($this->db, $sql, [ isEmptyProperty($params, 'ambLogId') ]); } public function createDiagnoses($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = insertSql($db, 'ambLogDiagnosis', $records, 'ambLogCode, medDiagnosisCode, diagnosisType, ClinicalDiagnosis', 'ambLogId, diagnosisId, type, clinicalDiagnosis' ); $ambLogCode = $records[0]->ambLogId; med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function updateDiagnoses($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = updateSql($db, 'ambLogDiagnosis', 'ambLogDiagnosisCode', $records, 'medDiagnosisCode, diagnosisType, ClinicalDiagnosis', 'diagnosisId, type, clinicalDiagnosis' ); $ambLogCode = selectSqlValue($db, "select ambLogCode from ambLogDiagnosis where ambLogDiagnosisCode = ?", [$records[0]->id]); med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function deleteDiagnoses($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = deleteSql($db, 'ambLogDiagnosis', 'ambLogDiagnosisCode', $records); $ambLogCode = selectSqlValue($db, "select ambLogCode from ambLogDiagnosis where ambLogDiagnosisCode = ?", [$records[0]->id]); med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function clearDiagnoses($ambLogId) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { $sql = <<<"EOT"
delete from AmbLogDiagnosis
where AmbLogCode = {$ambLogId}
EOT;
executeSql($db, $sql); med_ambulance_logStats::updateMedEmergency($db, $ambLogId); return true; } catch (Throwable $e) { $db->rollback(); throw $e; } } } handleJsonRpc('med_ambulance_logDiagnoses'); 