<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_criteriaKinds { private $db; const ERROR_ALREADY_USED_CODE_CODE = -101; const ERROR_ALREADY_USED_CODE_TEXT = "Данный код уже используется. Он должен быть уникальным в рамках даты."; public function readCriteriaKinds($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $criteriaDateId = isEmptyProperty($params, 'criteriaDateId'); $sqlCriteriaDate = ''; if ($criteriaDateId) { $sqlCriteriaDate = " AND MQCK.MedQualityCriteriaDateCode = :criteriaDateId"; $sqlParams[':criteriaDateId'] = $criteriaDateId; } $documentDate = isEmptyProperty($params, 'documentDate'); $sqlDocumentDate = ''; if ($documentDate) { $sqlDocumentDate = " 
AND MQCK.MedQualityCriteriaDateCode in (
    SELECT 
        MQCD1.MedQualityCriteriaDateCode
    FROM MedQualityCriteriaDate MQCD1
    WHERE MQCD1.onDate <= :documentDate
    ORDER BY MQCD1.onDate DESC
    LIMIT 1
)"; $sqlParams[':documentDate'] = $documentDate; } $sql = <<<"EOT"
SELECT 
    MQCK.MedQualityCriteriaKindCode "id",
    MQCK.MedQualityCriteriaDateCode "criteriaDateId",
    MQCD.onDate "onDate",
    MQCK.TalonByCode "talonBySpecialtyId",
    coalesce(MQCK.TalonByCode, 0) "talonBySpecialtyIdDisplay", -- Для варианта ответа в комбике
    coalesce(UIL.name, 'Общее по учреждению') "talonBySpecialtyName",
    MQCK.code "code",
    MQCK.name "name"
FROM MedQualityCriteriaKind MQCK 
LEFT JOIN MedQualityCriteriaDate MQCD ON MQCD.MedQualityCriteriaDateCode = MQCK.MedQualityCriteriaDateCode
LEFT JOIN UniImportLink UIL ON UIL.UniImportLinkCode = MQCK.TalonByCode
WHERE 1 = 1
$sqlCriteriaDate
$sqlDocumentDate
EOT;
return selectSql($db, $sql, $sqlParams); } protected function checkCodeUnique($db, $record) { $sql = <<<"EOT"
SELECT EXISTS(
SELECT 
    1 
FROM MedQualityCriteriaKind
WHERE MedQualityCriteriaDateCode = :criteriaDateId 
    AND code = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':criteriaDateId' => isUndefinedProperty($record, 'criteriaDateId'), ':code' => isUndefinedProperty($record, 'code') ]); if ($result) { throw new Exception(self::ERROR_ALREADY_USED_CODE_TEXT, self::ERROR_ALREADY_USED_CODE_CODE); } } public function createCriteriaKinds($records, $inTransaction = false) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $result = []; $resultCriteria = []; $db->beginTransaction(); try { suspendJsonRpc(); require_once (__DIR__ . '/criteria.php'); resumeJsonRpc(); $criterionClass = new med_qualitycontrol_criteria(); foreach ($records as $record) { $result[] = $rec = new stdClass(); $this->checkCodeUnique($db, $record); $sqlInsert = <<<"EOT"
    INSERT INTO MedQualityCriteriaKind (MedQualityCriteriaDateCode, TalonByCode, code, name)
    VALUES (:criteriaDateId, :talonBySpecialtyId, :code, :name)
    RETURNING MedQualityCriteriaKindCode
    EOT;
$rec->id = $id = selectSqlValue($db, $sqlInsert, [ ':criteriaDateId' => isUndefinedProperty($record, 'criteriaDateId'), ':talonBySpecialtyId' => isUndefinedProperty($record, 'talonBySpecialtyId'), ':code' => isUndefinedProperty($record, 'code'), ':name' => isUndefinedProperty($record, 'name') ]); if ($fromId = isUndefinedProperty($record, 'copyFromId')) { $criteria = $this->getCopiedCriteria($criterionClass, $fromId, $id); $resultCriteria = array_merge($resultCriteria, $criteria); } } $db->commit(); if (!empty($resultCriteria)) { $criterionClass->createCriteria($resultCriteria); } } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } protected function getCopiedCriteria ($criterionClass, $fromId, $toId) { $criterionParams = new stdClass(); $criterionParams->criteriaKindId = $fromId; $criteria = $criterionClass->readCriteria($criterionParams); foreach ($criteria as &$criterion) { $criterion = (object)$criterion; $criterion->criteriaKindId = $toId; } return $criteria; } public function updateCriteriaKinds($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $result = []; $db->beginTransaction(); try { foreach ($records as $record) { $result[] = $rec = new stdClass(); if (property_exists($record, 'code')) { $this->checkCodeUnique($db, $record); } $sqlUpdate = <<<"EOT"
UPDATE MedQualityCriteriaKind SET
   MedQualityCriteriaDateCode = CASE WHEN :criteriaDateIdExists = 1 THEN :criteriaDateId ELSE MedQualityCriteriaDateCode END,
   TalonByCode = CASE WHEN :talonBySpecialtyIdExists = 1 THEN :talonBySpecialtyId ELSE TalonByCode END,
   Code = CASE WHEN :codeExists = 1 THEN :code ELSE Code END,
   Name = CASE WHEN :nameExists = 1 THEN :name ELSE Name END
WHERE MedQualityCriteriaKindCode = :id
RETURNING MedQualityCriteriaKindCode
EOT;
$rec->id = selectSqlValue($db, $sqlUpdate, [ ':criteriaDateIdExists' => propertyExistsSQL($record, 'criteriaDateId'), ':criteriaDateId' => isUndefinedProperty($record, 'criteriaDateId'), ':talonBySpecialtyIdExists' => propertyExistsSQL($record, 'talonBySpecialtyId'), ':talonBySpecialtyId' => isUndefinedProperty($record, 'talonBySpecialtyId'), ':codeExists' => propertyExistsSQL($record, 'code'), ':code' => isUndefinedProperty($record, 'code'), ':nameExists' => propertyExistsSQL($record, 'name'), ':name' => isUndefinedProperty($record, 'name'), ':id' => isUndefinedProperty($record, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function deleteCriteriaKinds($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedQualityCriteriaKind', 'MedQualityCriteriaKindCode', $records); } } handleJsonRpc('med_qualitycontrol_criteriaKinds'); 