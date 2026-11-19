<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_criteriaDates { private $db; const ERROR_INVALID_DATE_CODE = -101; const ERROR_INVALID_DATE_TEXT = "Дата должна быть больше, чем в последней записи."; public function readCriteriaDates($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $sql = <<<"EOT"
SELECT 
    MQCD.MedQualityCriteriaDateCode "id",
    MQCD.onDate "onDate"
FROM MedQualityCriteriaDate MQCD 
EOT;
return selectSql($db, $sql, $sqlParams); } public function createCriteriaDates($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $result = []; $resultCriteriaKinds = []; $db->beginTransaction(); try { suspendJsonRpc(); require_once (__DIR__ . '/criteriaKinds.php'); resumeJsonRpc(); $criteriaKindClass = new med_qualitycontrol_criteriaKinds(); foreach ($records as $record) { $result[] = $rec = new stdClass(); $onDate = isUndefinedProperty($record, 'onDate'); $this->validateDate($db, $onDate); $sqlInsert = <<<"EOT"
    INSERT INTO MedQualityCriteriaDate (onDate)
    VALUES (:onDate)
    RETURNING MedQualityCriteriaDateCode
    EOT;
$rec->id = $id = selectSqlValue($db, $sqlInsert, [ ':onDate' => $onDate ]); if ($fromId = isUndefinedProperty($record, 'copyFromId')) { $criteriaKinds = $this->getCopiedCriteriaKinds($criteriaKindClass, $fromId, $id); $resultCriteriaKinds = array_merge($resultCriteriaKinds, $criteriaKinds); } } $db->commit(); if (!empty($resultCriteriaKinds)) { $criteriaKindClass->createCriteriaKinds($resultCriteriaKinds); } } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } protected function validateDate ($db, $onDate) { $sql = <<<"EOT"
SELECT 
    onDate
FROM MedQualityCriteriaDate
WHERE onDate >= :onDate
EOT;
$result = selectSql($db, $sql, [':onDate' => $onDate]); if (!empty($result)) { throw new Exception(self::ERROR_INVALID_DATE_TEXT, self::ERROR_INVALID_DATE_CODE); } } protected function getCopiedCriteriaKinds ($criteriaKindClass, $fromId, $toId) { $criteriaKindParams = new stdClass(); $criteriaKindParams->criteriaDateId = $fromId; $criteriaKinds = $criteriaKindClass->readCriteriaKinds($criteriaKindParams); foreach ($criteriaKinds as &$criteriaKind) { $criteriaKind = (object)$criteriaKind; $criteriaKind->criteriaDateId = $toId; $criteriaKind->copyFromId = $criteriaKind->id; } return $criteriaKinds; } public function updateCriteriaDates($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } foreach ($records as $record) { if (property_exists($record, 'onDate')) { $this->validateDate($db, isUndefinedProperty($record, 'onDate')); } } return updateSql($db, 'MedQualityCriteriaDate', 'MedQualityCriteriaDateCode', $records, 'onDate', 'onDate'); } public function deleteCriteriaDates($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedQualityCriteriaDate', 'MedQualityCriteriaDateCode', $records); } } handleJsonRpc('med_qualitycontrol_criteriaDates'); 