<?php
 require_once(__DIR__ . '/../../init.php'); class map_docs_components { private $db; public function readComponentGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       HCG.htmComponentGroupCode "id",
       HCG.Name "name",
       HCG.TaskNumbers "tasksId",
       string_agg(WT.Name::text, ', ' order by WT.Name) "tasks"
from htmComponentGroup HCG
     left join WebTask WT on HCG.TaskNumbers LIKE '%,' || WT.WebTaskCode::text || ',%'
where HCG.Type = 1
group by 1, 2, 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createComponentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into htmComponentGroup (Name, TaskNumbers, Type)
values (?, ?, 1)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'name'), isEmptyProperty($row, 'tasksId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_htmComponentGroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateComponentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update htmComponentGroup set
       Name = case when ? = 1 then ? else Name end,
       TaskNumbers = case when ? = 1 then ? else TaskNumbers end
WHERE htmComponentGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'tasksId'), isUndefinedProperty($row, 'tasksId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteComponentGroups($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from htmComponentGroup
where htmComponentGroupCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readComponents($params = null) { $db = $this->db ?: $this->db = connectDb(); $componentWhere = isset($params->componentId) && isEmptyProperty($params, 'componentId') ? "and C.htmComponentCode = " . $params->componentId : ""; $sql = <<<"EOT"
select
       C.htmComponentCode "id",
       C.htmComponentGroupCode "groupId",
       C.Caption "caption",
       CAST((select string_agg((CT.SourceTable)::text, ', ')
             from htmComponentTable CT
             where CT.htmComponentCode = C.htmComponentCode) as varchar(255)) "tablesName"
from htmComponent C
where 0 = 0 /*для корректности добавления условаия по htmComponentCode*/
  $componentWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function deleteComponents($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from htmComponent
where htmComponentCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function syncComponentInfo($record) { $db = $this->db ?: $this->db = connectDb(); $sqlInsertTable = <<<"EOT"
insert into htmComponentTable (htmComponentCode, SourceTable)
values (?, ?)
EOT;
$sqlUpdateTable = <<<"EOT"
update htmComponentTable set
       SourceTable = case when ? = 1 then ? else SourceTable end
WHERE htmComponentTableCode = ?
EOT;
$sqlInsertQuery = <<<"EOT"
insert into htmComponentQuery (htmComponentCode, QueryType, QueryText, Position, Name, ElementName)
values (?, ?, ?, ?, ?, ?)
EOT;
$sqlUpdateQuery = <<<"EOT"
update htmComponentQuery set
       QueryType = case when ? = 1 then ? else QueryType end,
       QueryText = case when ? = 1 then ? else QueryText end,
       Position = case when ? = 1 then ? else Position end,
       Name = case when ? = 1 then ? else Name end,
       ElementName = case when ? = 1 then ? else ElementName end
WHERE htmComponentQueryCode = ?
EOT;
$stmtInsertTable = $db->prepare($sqlInsertTable); $stmtUpdateTable = $db->prepare($sqlUpdateTable); $stmtInsertQuery = $db->prepare($sqlInsertQuery); $stmtUpdateQuery = $db->prepare($sqlUpdateQuery); $tables = isset($record->tables) ? $record->tables : null; if ($tables) { $deleting = []; foreach ($tables as $table) { switch ($table->iud) { case 1: $stmtInsertTable->execute([ $record->id, isUndefinedProperty($table, 'name') ]); break; case 2: $stmtUpdateTable->execute([ propertyExistsSQL($table, 'name'), isUndefinedProperty($table, 'name'), $table->id ]); break; case 3: $deleting[] = (object) ['id' => $table->id]; break; } } if ($deleting !== []) { $in = createSqlInCondition($deleting); $sqlDeleteTable = <<<"EOT"
delete from htmComponentTable
where htmComponentTableCode $in
EOT;
$stmtDeleteTable = $db->prepare($sqlDeleteTable); $stmtDeleteTable->execute(); } } $queries = isset($record->queries) ? $record->queries : null; if ($queries) { $deleting = []; foreach ($queries as $query) { switch ($query->iud) { case 1: $stmtInsertQuery->execute([ $record->id, isUndefinedProperty($query, 'type'), base64_decode(isUndefinedProperty($query, 'text')), isUndefinedProperty($query, 'position'), isUndefinedProperty($query, 'name'), isUndefinedProperty($query, 'element') ]); break; case 2: $stmtUpdateQuery->execute([ propertyExistsSQL($query, 'type'), isUndefinedProperty($query, 'type'), propertyExistsSQL($query, 'text'), base64_decode(isUndefinedProperty($query, 'text')), propertyExistsSQL($query, 'position'), isUndefinedProperty($query, 'position'), propertyExistsSQL($query, 'name'), isUndefinedProperty($query, 'name'), propertyExistsSQL($query, 'element'), isUndefinedProperty($query, 'element'), $query->id ]); break; case 3: $deleting[] = (object) ['id' => $query->id]; break; } } if ($deleting !== []) { $in = createSqlInCondition($deleting); $sqlDeleteQuery = <<<"EOT"
delete from htmComponentQuery
where htmComponentQueryCode $in
EOT;
$stmtDeleteQuery = $db->prepare($sqlDeleteQuery); $stmtDeleteQuery->execute(); } } } public function readComponentInfo($params) { $db = $this->db ?: $this->db = connectDb(); $componentId = isset($params->componentId) && isEmptyProperty($params, 'componentId') ? $params->componentId : null; $sql = <<<"EOT"
select
       htmComponentCode "id",
       htmComponentGroupCode "groupId",
       Caption "caption",
       Name "name",
       Note "note"
from htmComponent
where htmComponentCode = $componentId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetch(); $sql = <<<"EOT"
select
      htmComponentTableCode "id",
      sourceTable "name"
from htmComponentTable
where htmComponentCode = $componentId
EOT;
$ret += ['tables' => selectSql($db, $sql)]; $sql = <<<"EOT"
select
       htmComponentQueryCode "id",
       htmComponentCode "componentId",
       Position "position",
       QueryText "text",
       QueryType "type",
       Name "name",
       ElementName "element"
from htmComponentQuery
where htmComponentCode =  $componentId
order by Position
EOT;
$queries = selectSql($db, $sql); foreach ($queries as &$value) { $value['text'] = base64_encode($value['text']); } $ret += ['queries' => $queries]; return $ret; } public function createComponentInfo($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into htmComponent (htmComponentGroupCode, Note, Caption, Name)
values (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'groupId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'caption'), isUndefinedProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_htmComponent'); $row->id = $rec->id; $this->syncComponentInfo($row); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteComponentInfo($records = null) { } public function updateComponentInfo($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update htmComponent set
       htmComponentGroupCode = case when ? = 1 then ? else htmComponentGroupCode end,
       Note = case when ? = 1 then ? else Note end,
       Caption = case when ? = 1 then ? else Caption end
WHERE htmComponentCode= ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'groupId'), isUndefinedProperty($row, 'groupId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'caption'), isUndefinedProperty($row, 'caption'), $row->id ]); $this->syncComponentInfo($row); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readSchemeTable($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
      SchemeTableCode "id",
      Name "name"
from SchemeTable
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function copyComponent($params) { $db = $this->db ?: $this->db = connectDb(); $componentId = isset($params->componentId) && isEmptyProperty($params, 'componentId') ? $params->componentId : null; $sqlComponent = <<<"EOT"
insert into htmComponent (htmComponentGroupCode, Name, HTML, Note, Caption)
select htmComponentGroupCode, ?, HTML, Note, Caption || ' копия'
from htmComponent
where htmComponentCode = $componentId
EOT;
$sqlTables = <<<"EOT"
insert into htmComponentTable (htmComponentCode, SourceTable)
select ?, SourceTable
from htmComponentTable
where htmComponentCode = $componentId
EOT;
$sqlQueries = <<<"EOT"
insert into htmComponentQuery (htmComponentCode, QueryType, Position, QueryText, Name, ElementName)
select ?, QueryType, Position, QueryText, Name, ElementName
from htmComponentQuery
where htmComponentCode = $componentId
EOT;
if ($componentId) { $db->beginTransaction(); try { $stmt = $db->prepare($sqlComponent); $stmt->execute([ isUndefinedProperty($params, 'guid') ]); $newId = $db->lastInsertId('seq_htmComponent'); $stmt = $db->prepare($sqlTables); $stmt->execute([ $newId ]); $stmt = $db->prepare($sqlQueries); $stmt->execute([ $newId ]); $param = new stdClass(); $param->componentId = $newId; $rec = $this->readComponents($param); $db->commit(); return $rec; } catch (Throwable $e) { $db->rollback(); throw $e; } } return false; } public function moveComponent($params) { $db = $this->db ?: $this->db = connectDb(); $componentId = isset($params->componentId) && isEmptyProperty($params, 'componentId') ? $params->componentId : null; $newGroupId = isset($params->groupId) && isEmptyProperty($params, 'groupId') ? $params->groupId : null; if ($componentId && $newGroupId) { $sql = <<<"EOT"
update htmComponent set
       htmComponentGroupCode = $newGroupId
where htmComponentCode = $componentId
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } } $service = new Junior\Server(new map_docs_components()); $service->process(); 