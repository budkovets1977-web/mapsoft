<?php
 require_once(__DIR__.'/../../init.php'); suspendJsonRpc(); require_once(__DIR__.'/filters.php'); resumeJsonRpc(); class map_tables_configurations { private $db; public function readEnterprises () { $filter = new map_tables_filters(); $params = new stdClass(); $params->fromConfigurations = true; return $filter->readEnterprises($params); } public function readTables ($params) { $db = $this->db ?: $this->db = connectDb(); $mapGenTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $sql = <<<"EOT"
select
    G.GenTableGroupCode || {$db->postgre("E")}'.' || T.GenTableCode "id",
    G.GenTableGroupCode "groupId",
    G.name "groupName",
    T.GenTableCode "tableId",
    T.label "tableName",
    (select count(*) from GenTableConfig where GenTableCode = t.GenTableCode and EnterpriseCode = ?) "configCount",
    g.name,
    t.position
from
GenTableGroup g
JOIN GenTable t on t.GenTableGroupCode = g.GenTableGroupCode
where g.GenTableSetCode = ? and
      coalesce(t.TableType, 0) = 0
Order by 7, 8
EOT;
$stmt = $db->prepare($sql); $stmt->execute([isEmptyProperty($params, 'enterpriseId', 0), $mapGenTableSetCode]); $ret = []; $g = null; while ($row = $stmt->fetch()) { if (!$g || $g->groupId !== (int)$row['groupId']) { $ret[] = $g = new stdClass; $g->id = (string)$row['groupId'].'.0'; $g->groupId = (int)$row['groupId']; $g->name = $row['groupName']; $g->configCount = 0; $g->expanded = false; } $g->children[] = $p = new stdClass; $p->id = (string)$row['id']; $p->tableId = (int)$row['tableId']; $p->groupId = (int)$row['groupId']; $p->label = $row['tableName']; if ((int)$row['configCount'] === 0) { $p->name = $row['tableName']; } else { $p->name = $row['tableName'].' ('.(string)$row['configCount'].')'; }; $p->configCount = (int)$row['configCount']; $p->leaf = true; } return ['children' => $ret]; } public function readConfigurations ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    tc.GenTableConfigCode "id",
    tc.EnterpriseCode "enterpriseId",
    tc.GenTableCode "tableId",
    tc.StartDate "startDate",
    tc.FinishDate "finishDate",
     (select
        {$db->dialect("'{' || list(map.JSON_Object(ca.GenTableAnalyticsCode,
                                               map.JSON_Numeric('id', ca.GenTableAnalyticsCode) ||
                                               map.JSON_Numeric('dictId', ca.GenTableDictDataCode) ||
                                               map.JSON_String('dictValue', dd.Value, 0), 0)) || '}'", "E'{' || string_agg((map.JSON_Object(ca.GenTableAnalyticsCode,
                                                       map.JSON_Numeric(E'id', (ca.GenTableAnalyticsCode)::numeric) ||
                                                       map.JSON_Numeric(E'dictId', (ca.GenTableDictDataCode)::numeric) ||
                                                       map.JSON_String(E'dictValue', dd.Value, 0), 0))::text, ',') || E'}'")}
        from GenTableConfigAnalytics ca
            join GenTableDictData dd on dd.GenTableDictDataCode = ca.GenTableDictDataCode
        where ca.GenTableConfigCode = tc.GenTableConfigCode
    ) "analytics"
from GenTableConfig tc
where tc.EnterpriseCode = ? and
      tc.GenTableCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'enterpriseId', 0), isEmptyProperty($params, 'tableId', 0) ]); $ret = []; while($row = $stmt->fetch()) { $array = safe_json_decode($row['analytics']); foreach ($array as $v) { $row['a_'.$v->id] = $v->dictId; $row['v_'.$v->id] = $v->dictValue; } $ret[] = $row; } return $ret; } public function createConfigurations ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into GenTableConfig (
    EnterpriseCode, GenTableCode, StartDate, FinishDate)
values (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'enterpriseId'), isUndefinedProperty($row, 'tableId'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate') ]); $ret[] = $rec = new stdClass; $rec->id = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_GenTableConfig'); $param = new stdClass; $param->id = $rec->id; $param->rec = $row; $param->del = false; $this->updateGenTableConfigAnalytics($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateConfigurations ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update GenTableConfig set
    EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
    GenTableCode = case when ? = 1 then ? else GenTableCode end,
    StartDate = case when ? = 1 then ? else StartDate end,
    FinishDate = case when ? = 1 then ? else FinishDate end
where GenTableConfigCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'enterpriseId'), isEmptyProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'tableId'), isEmptyProperty($row, 'tableId'), propertyExistsSQL($row, 'startDate'), date("Y-m-d", safe_strtotime(isUndefinedProperty($row, 'startDate'))), propertyExistsSQL($row, 'finishDate'), date("Y-m-d", safe_strtotime(isUndefinedProperty($row, 'finishDate'))), $row->id ]); $param = new stdClass; $param->id = $row->id; $param->rec = $row; $param->del = true; $this->updateGenTableConfigAnalytics($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteConfigurations ($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from GenTableConfig where GenTableConfigCode $in
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } public function copyConfigurations($params) { $db = $this->db ?: $this->db = connectDb(); if (isset($params->enterpriseList)) { $enterpriseList = $params->enterpriseList; } else { $enterpriseList = []; } if (isset($params->configurationList)) { $configurationList = $params->configurationList; } else { $configurationList = []; } $db->beginTransaction(); try { foreach ($enterpriseList as $eKey=>$eValue) { foreach ($configurationList as $cKey=>$cValue) { $sql = <<<"EOT"
insert into GenTableConfig (
    EnterpriseCode, GenTableCode, StartDate, FinishDate )
(
select
    {$db->dialect("cast(? as integer)", "?::integer")} "enterpriseId",
    tc.GenTableCode "tableId",
    tc.StartDate "startDate",
    tc.FinishDate "finishDate"
from GenTableConfig tc
where tc.GenTableConfigCode = ?
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $eValue, $cValue]); $configId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_GenTableConfig'); $sql = <<<"EOT"
insert into GenTableConfigAnalytics
(GenTableConfigCode, GenTableAnalyticsCode, GenTableDictDataCode)
(
select
    {$db->dialect("cast(? as integer)", "?::integer")} ,
    GenTableAnalyticsCode,
    GenTableDictDataCode
from GenTableConfigAnalytics
where GenTableConfigCode = ?
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $configId, $cValue]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } private function updateGenTableConfigAnalytics($params) { $db = $this->db ?: $this->db = connectDb(); $aCodeList = ''; $aValueInsert = ''; $s = ''; if (isset($params->id)) { $id = (int) $params->id; } if (isset($params->rec)) { $rec = $params->rec; } if (isset($params->del)) { $del = $params->rec; } if (!isset($id) || !isset($rec) || !isset($del)) { throw new Exception("В функцию updateGenTableConfigAnalytics переданы неверные параметры!", -1); } foreach($rec as $key=>$value) { if (stripos($key, 'a_') === 0) { $s = substr($key, 2); if ($aCodeList === '') { $aCodeList = $s; } else { $aCodeList = $aCodeList.','.$s; } if ((int)$value !== 0) { if ($aValueInsert === '') { $aValueInsert = 'SELECT '.$id.','.$s.','.$value; } else { $aValueInsert = $aValueInsert.' UNION ALL SELECT '.$id.','.$s.','.$value; } } } } $isCurTransaction = false; if (!$db->inTransaction()) { $db->beginTransaction(); $isCurTransaction = true; } try { if ($del && $aCodeList != '') { $sql = <<<"EOT"
delete from GenTableConfigAnalytics
where GenTableConfigCode = ? and
      GenTableAnalyticsCode in ($aCodeList)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); } if ($aValueInsert != '') { $sql = <<<"EOT"
insert into GenTableConfigAnalytics
(GenTableConfigCode, GenTableAnalyticsCode, GenTableDictDataCode)
($aValueInsert)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } if ($isCurTransaction) { $db->commit(); } } catch (Throwable $e) { if ($isCurTransaction) { $db->rollback(); } throw $e; } } public function isParentEnterprise() { return (getSessionValue('mapEnterpriseParentCode') === getSessionValue('mapEnterpriseCode')); } } handleJsonRpc("map_tables_configurations"); 