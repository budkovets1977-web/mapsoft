<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_criteria { private $db; const ERROR_ALREADY_USED_CODE_CODE = -101; const ERROR_ALREADY_USED_CODE_TEXT = "Данный код уже используется. Он должен быть уникальным в рамках категории."; public function readCriteria($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $criteriaKindId = isEmptyProperty($params, 'criteriaKindId'); $sqlCriteriaKind = ''; if ($criteriaKindId) { $sqlCriteriaKind = " AND MQC.MedQualityCriteriaKindCode = :criteriaKindId"; $sqlParams[':criteriaKindId'] = $criteriaKindId; } $sql = <<<"EOT"
SELECT 
    MQC.MedQualityCriteriaCode "id", 
    MQC.MedQualityCriteriaKindCode "criteriaKindId",
    MQC.Code "code",
    MQC.Name "name",
    MQC.fullName "fullName",
    MQC.position "position",
    MQC.mark "mark",
    MQC.type "type",
    MQC.note "note",
    MQC.calcMark "calcMark",
    CASE MQC.calcMark 
        WHEN 1 THEN 0
        WHEN 0 THEN 1
    END "notCalcMark"
FROM MedQualityCriteria MQC
WHERE 1 = 1
$sqlCriteriaKind
ORDER BY MQC.position
EOT;
return selectSql($db, $sql, $sqlParams); } protected function checkCodeUnique($db, $record) { $sql = <<<"EOT"
SELECT EXISTS(
SELECT 
    1 
FROM MedQualityCriteria
WHERE MedQualityCriteriaKindCode = :criteriaKindId 
  AND code = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':criteriaKindId' => $record->criteriaKindId, ':code' => $record->code ]); if ($result) { throw new Exception(self::ERROR_ALREADY_USED_CODE_TEXT, self::ERROR_ALREADY_USED_CODE_CODE); } } public function createCriteria($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $result = []; $db->beginTransaction(); try { foreach ($records as $record) { $result[] = $rec = new stdClass(); $this->checkCodeUnique($db, $record); $sqlInsert = <<<"EOT"
INSERT INTO MedQualityCriteria (MedQualityCriteriaKindCode, Code, Name, fullName, position, mark, type, note, calcMark)
VALUES (:criteriaKindId, :code, :name, :fullName, coalesce(
    (SELECT
        max(position) "res"
    FROM MedQualityCriteria
    WHERE coalesce(MedQualityCriteriaKindCode, 0) = :criteriaKindId
    ), 0
) + 1, :mark, :type, :note, :calcMark)
RETURNING MedQualityCriteriaCode
EOT;
$rec->id = selectSqlValue($db, $sqlInsert, [ ':criteriaKindId' => isUndefinedProperty($record, 'criteriaKindId'), ':code' => isUndefinedProperty($record, 'code'), ':name' => isUndefinedProperty($record, 'name'), ':fullName' => isUndefinedProperty($record, 'fullName'), ':mark' => isUndefinedProperty($record, 'mark'), ':type' => isUndefinedProperty($record, 'type'), ':note' => isUndefinedProperty($record, 'note'), ':calcMark' => isUndefinedProperty($record, 'calcMark') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function updateCriteria($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $result = []; $db->beginTransaction(); try { foreach ($records as $record) { $result[] = $rec = new stdClass(); if (property_exists($record, 'code')) { $this->checkCodeUnique($db, $record); } $sqlUpdate = <<<"EOT"
UPDATE MedQualityCriteria SET
   MedQualityCriteriaKindCode = CASE WHEN :criteriaKindIdExists = 1 THEN :criteriaKindId ELSE MedQualityCriteriaKindCode END,
   Code = CASE WHEN :codeExists = 1 THEN :code ELSE Code END,
   Name = CASE WHEN :nameExists = 1 THEN :name ELSE Name END,
   FullName = CASE WHEN :fullNameExists = 1 THEN :fullName ELSE FullName END,
   position = CASE WHEN :positionExists = 1 THEN :position ELSE position END,
   mark = CASE WHEN :markExists = 1 THEN :mark ELSE mark END,
   type = CASE WHEN :typeExists = 1 THEN :type ELSE type END,
   note = CASE WHEN :noteExists = 1 THEN :note ELSE note END,
   calcMark = CASE WHEN :calcMarkExists = 1 THEN :calcMark ELSE calcMark END
WHERE MedQualityCriteriaCode = :id
RETURNING MedQualityCriteriaCode
EOT;
$rec->id = selectSqlValue($db, $sqlUpdate, [ ':criteriaKindIdExists' => propertyExistsSQL($record, 'criteriaKindId'), ':criteriaKindId' => isUndefinedProperty($record, 'criteriaKindId'), ':codeExists' => propertyExistsSQL($record, 'code'), ':code' => isUndefinedProperty($record, 'code'), ':nameExists' => propertyExistsSQL($record, 'name'), ':name' => isUndefinedProperty($record, 'name'), ':fullNameExists' => propertyExistsSQL($record, 'fullName'), ':fullName' => isUndefinedProperty($record, 'fullName'), ':positionExists' => propertyExistsSQL($record, 'position'), ':position' => isUndefinedProperty($record, 'position'), ':markExists' => propertyExistsSQL($record, 'mark'), ':mark' => isUndefinedProperty($record, 'mark'), ':typeExists' => propertyExistsSQL($record, 'type'), ':type' => isUndefinedProperty($record, 'type'), ':noteExists' => propertyExistsSQL($record, 'note'), ':note' => isUndefinedProperty($record, 'note'), ':calcMarkExists' => propertyExistsSQL($record, 'calcMark'), ':calcMark' => isUndefinedProperty($record, 'calcMark'), ':id' => isUndefinedProperty($record, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function deleteCriteria($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedQualityCriteria', 'MedQualityCriteriaCode', $records); } } handleJsonRpc('med_qualitycontrol_criteria'); 