<?php
 require_once(__DIR__ . '/../../init.php'); require_once(__DIR__ . '/logStats.php'); class med_ambulance_logActions { private $db; private function sessionConnectDb() { return $this->db ?: $this->db = connectDb(); } public function readActions($params) { $this->sessionConnectDb(); $sql = <<<"EOT"
select
    ALD.AmbLogActionCode "id",
    ALD.ambLogCode "ambLogId",
    ALD.uniDictCodeAction "actionId",
    UD.value "actionName"
from ambLogAction ALD
join unidict UD on UD.uniDictCode = ALD.uniDictCodeAction
where ALD.ambLogCode = ?
order by id
EOT;
return selectSql($this->db, $sql, [ isEmptyProperty($params, 'ambLogId') ]); } public function createActions($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = insertSql($db, 'ambLogAction', $records, 'ambLogCode, uniDictCodeAction', 'ambLogId, actionId' ); $ambLogCode = $records[0]->ambLogId; med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function updateActions($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = updateSql($db, 'ambLogAction', 'ambLogActionCode', $records, 'ambLogCode, uniDictCodeAction', 'ambLogId, actionId' ); $ambLogCode = selectSqlValue($db, "select ambLogCode from ambLogAction where ambLogActionCode = ?", [$records[0]->id]); med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function deleteActions($records) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { if (is_object($records)) $records = [$records]; $ret = deleteSql($db, 'ambLogAction', 'ambLogActionCode', $records); $ambLogCode = selectSqlValue($db, "select ambLogCode from ambLogAction where ambLogActionCode = ?", [$records[0]->id]); med_ambulance_logStats::updateMedEmergency($db, $ambLogCode); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function clearActions($ambLogId) { $db = $this->sessionConnectDb(); $db->beginTransaction(); try { $sql = <<<"EOT"
delete from ambLogAction
where AmbLogCode = {$ambLogId}
EOT;
executeSql($db, $sql); med_ambulance_logStats::updateMedEmergency($db, $ambLogId); return true; } catch (Throwable $e) { $db->rollback(); throw $e; } } } handleJsonRpc('med_ambulance_logActions'); 