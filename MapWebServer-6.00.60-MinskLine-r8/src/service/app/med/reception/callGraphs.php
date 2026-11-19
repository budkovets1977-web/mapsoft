<?php
require_once(__DIR__.'/../../init.php'); class med_reception_callGraphs { private $db; public function readCallGraphs($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    MedCallGraphCode "id",
    StartTime "startTime",
    FinishTime "finishTime",
    RuleDay "ruleDay",
    RuleDaysOfWeek "ruleDaysOfWeek",
    RuleMonth "ruleMonth",
    RuleYear "ruleYear",
    BranchId "branchId",
    BranchName "branchName"
from MedCallGraph
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCallGraphs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedCallGraph  set
    StartTime = case when ? = 1 then ? else StartTime end,
    FinishTime = case when ? = 1 then ? else FinishTime end,
    RuleDay = case when ? = 1 then ? else RuleDay end,
    RuleDaysOfWeek = case when ? = 1 then ? else RuleDaysOfWeek end,
    RuleMonth = case when ? = 1 then ? else RuleMonth end,
    RuleYear = case when ? = 1 then ? else RuleYear end,
    BranchId = case when ? = 1 then ? else BranchId end,
    BranchName = case when ? = 1 then ? else BranchName end
where MedCallGraphCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'startTime'), isUndefinedProperty($row, 'startTime'), propertyExistsSQL($row, 'finishTime'), isUndefinedProperty($row, 'finishTime'), propertyExistsSQL($row, 'ruleDay'), isUndefinedProperty($row, 'ruleDay'), propertyExistsSQL($row, 'ruleDaysOfWeek'), isUndefinedProperty($row, 'ruleDaysOfWeek'), propertyExistsSQL($row, 'ruleMonth'), isUndefinedProperty($row, 'ruleMonth'), propertyExistsSQL($row, 'ruleYear'), isUndefinedProperty($row, 'ruleYear'), propertyExistsSQL($row, 'branchId'), isUndefinedProperty($row, 'branchId'), propertyExistsSQL($row, 'branchName'), isUndefinedProperty($row, 'branchName'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCallGraphs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
INSERT INTO MedCallGraph (
    StartTime, FinishTime, RuleDay,
    RuleDaysOfWeek, RuleMonth, RuleYear,
    BranchId, BranchName
)
VALUES (
    ?, ?, ?,
    ?, ?, ?,
    ?, ?
)
SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'startTime'), isUndefinedProperty($row, 'finishTime'), isUndefinedProperty($row, 'ruleDay'), isUndefinedProperty($row, 'ruleDaysOfWeek'), isUndefinedProperty($row, 'ruleMonth'), isUndefinedProperty($row, 'ruleYear'), isUndefinedProperty($row, 'branchId'), isUndefinedProperty($row, 'branchName') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCallGraph'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteCallGraphs($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedCallGraph', 'MedCallGraphCode', $records); } } handleJsonRpc('med_reception_callGraphs');