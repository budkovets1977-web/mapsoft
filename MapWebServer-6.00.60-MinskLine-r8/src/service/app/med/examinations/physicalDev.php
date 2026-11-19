<?php
 require_once(__DIR__.'/../../init.php'); class med_examinations_physicalDev { private $db; function __construct() { $this->db = connectDb(); } public function readMethod($params = null) { $db = $this->db; $sql = <<<EOT
SELECT
    U.UniDictCode "id",
    U.Value "name"
FROM UniDict U 
WHERE U.DictNumber = 85
EOT;
return selectSql($db, $sql); } public function createMethod($params = null) { $db = $this->db; $method = isEmptyProperty($params, 'name'); $sql = <<<EOT
INSERT INTO UniDict (DictNumber, Value)
VALUES (85, ?)
RETURNING UniDictCode
EOT;
$ret[] = $rec = new stdClass; $rec->id = selectSqlValue($db, $sql, [$method]); return $ret; } public function updateMethod($record = null) { $db = $this->db; return updateSql($db, 'UniDict', 'UniDictCode', $record, 'Value', 'name'); } public function deleteMethod($records = null) { $db = $this->db; return deleteSql($db, 'UniDict', 'UniDictCode', $records); } public function readEstimate($params = null) { $db = $this->db; $methodId = isUndefinedProperty($params, 'methodId'); $sqlParams = []; if ($methodId) { $sqlParams[':methodId'] = $methodId; } $all = isEmptyProperty($params, 'all'); $sqlSelect = ' U.Value "name",'; $sqlWhere = ' AND U.UniDictCodeParent = :methodId'; $sqlJoin = ''; if ($all) { $sqlSelect = <<<EOT
(U.Value || ' - ' || UP.Value) "name",
EOT;
$sqlWhere = ' AND U.UniDictCodeParent <> 0'; $sqlJoin = ' LEFT JOIN UniDict UP ON UP.UniDictCode = U.UniDictCodeParent'; } $sql = <<<EOT
SELECT
    U.UniDictCode "id",
    $sqlSelect
    U.UniDictCodeParent "uniDictCodeParent"
FROM UniDict U
$sqlJoin
WHERE U.DictNumber = 86
$sqlWhere
EOT;
return selectSql($db, $sql, $sqlParams); } public function createEstimate($params = null) { $db = $this->db; $method = isEmptyProperty($params, 'name'); $parentId = isUndefinedProperty($params, 'uniDictCodeParent'); $sql = <<<EOT
INSERT INTO UniDict (DictNumber, Value, UniDictCodeParent)
VALUES (86, ?, ?)
RETURNING UniDictCode
EOT;
$ret[] = $rec = new stdClass; $rec->id = selectSqlValue($db, $sql, [$method, $parentId]); return $ret; } public function updateEstimate($record = null) { $db = $this->db; return updateSql($db, 'UniDict', 'UniDictCode', $record, 'Value', 'name'); } public function deleteEstimate($records = null) { $db = $this->db; return deleteSql($db, 'UniDict', 'UniDictCode', $records); } } handleJsonRpc('med_examinations_physicalDev'); 