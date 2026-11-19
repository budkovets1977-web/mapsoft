<?php
 require_once(__DIR__.'/../../init.php'); class map_tables_analytics { private $db; public function readAnalytics () { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseParentCode = (int) getSessionValue('mapEnterpriseParentCode'); $mapGenTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $sql = <<<'EOT'
select
    GTA.GenTableAnalyticsCode "id",
    GTA.EnterpriseCode "enterpriseId",
    GTA.GenTableDictCode "dictId",
    GTA.GenTableSetCode "setId",
    GTD.Name "dictName",
    GTA.Name "name",
    GTA.FilterName "filterName",
    GTA.StartDate "startDate",
    GTA.FinishDate "finishDate",
    GTA.Position "position"
from GenTableAnalytics GTA
left join GenTableDict GTD on GTD.GenTableDictCode = GTA.GenTableDictCode
where COALESCE(GTA.EnterpriseCode, 0) = ? and
      COALESCE(GTA.GenTableSetCode, 0) = ?
order by position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseParentCode, $mapGenTableSetCode ]); return $stmt->fetchAll(); } public function createAnalytics ($records) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseParentCode = (int) getSessionValue('mapEnterpriseParentCode'); $mapGenTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $sql = <<<"EOT"
insert into GenTableAnalytics (
    EnterpriseCode, GenTableDictCode, GenTableSetCode, Name, FilterName,
    StartDate, FinishDate, Position)
values (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ $mapEnterpriseParentCode, isEmptyProperty($row, 'dictId'), $mapGenTableSetCode, isEmptyProperty($row, 'name'), isEmptyProperty($row, 'filterName'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate'), isEmptyProperty($row, 'position') ]); $ret[] = $rec = new stdClass; $rec->id = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_GenTableAnalytics'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateAnalytics ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update GenTableAnalytics set
    EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
    GenTableDictCode = case when ? = 1 then ? else GenTableDictCode end,
    GenTableSetCode = case when ? = 1 then ? else GenTableSetCode end,
    Name = case when ? = 1 then ? else Name end,
    FilterName = case when ? = 1 then ? else FilterName end,
    StartDate = case when ? = 1 then ? else StartDate end,
    FinishDate = case when ? = 1 then ? else FinishDate end,
    Position = case when ? = 1 then ? else Position end
where GenTableAnalyticsCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'enterpriseId'), isEmptyProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'dictId'), isEmptyProperty($row, 'dictId'), propertyExistsSQL($row, 'setId'), isEmptyProperty($row, 'setId'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'filterName'), isEmptyProperty($row, 'filterName'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'position'), isEmptyProperty($row, 'position'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteAnalytics ($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from GenTableAnalytics where GenTableAnalyticsCode $in
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } public function readFilters () { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseParentCode = (int) getSessionValue('mapEnterpriseParentCode'); $mapGenTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $sql = <<<"EOT"
select distinct
    FilterName "name"
from GenTableAnalytics
where COALESCE(EnterpriseCode, 0) = ? and
      COALESCE(GenTableSetCode, 0) = ? and
      NULLIF(filterName, {$db->postgre("E")}'') is not null
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseParentCode, $mapGenTableSetCode ]); return $stmt->fetchAll(); } } $service = new Junior\Server(new map_tables_analytics()); $service->process(); 