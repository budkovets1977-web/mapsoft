<?php
 require_once(__DIR__ . '/../../init.php'); class map_med_lexemes { private $db; public function readLexemes($params) { $db = $this->db ? : $this->db = connectDb(); $sql = $db->isSybase ? <<<"EOT"
with recursive tree ( MedLexemeCode, parentId, category, name, position, treePath, leaf ) AS
        ( SELECT t.MedLexemeCode, if t.MedLexemeCodeParent = ? then null else t.medlexemecodeParent endif, t.category, t.Name, t.position, t.treePath,
          if exists (select first 1 from MedLexeme where coalesce(MedLexemeCodeParent,0) = t.MedLexemeCode) then 0 else 1 endif "leaf"
          FROM MedLexeme t
          WHERE t.MedLexemeCodeParent = ?
          UNION ALL
          SELECT t.MedLexemeCode, if t.MedLexemeCodeParent = ? then null else t.medlexemecodeParent endif, t.category, t.Name, t.position, t.treePath,
          if exists (select first 1 from MedLexeme where coalesce(MedLexemeCodeParent,0) = t.MedLexemeCode) then 0 else 1 endif "leaf"
          FROM MedLexeme t
          INNER JOIN tree tr
          ON ( t.MedLexemeCodeParent = tr.MedLexemeCode )
        )
        SELECT medLexemeCode, COALESCE(parentId,0) as parentId, COALESCE(category,0) as category, name, position, treePath, leaf
        FROM tree tr
EOT
: <<<"EOT"
with recursive tree (medLexemeCode, parentId, category, name, position, treePath, leaf) AS
        (select
            t.MedLexemeCode,
            case
              when t.MedLexemeCodeParent = ?
            then null
              else t.medlexemecodeParent
            end,
            t.category,
            t.Name,
            t.position,
            t.treePath,
            case
              when exists(select 1
                          from MedLexeme
                          where coalesce (MedLexemeCodeParent, 0) = t.MedLexemeCode
                          limit 1) then 0
              else 1
            end as "leaf"
          from MedLexeme t
          where t.MedLexemeCodeParent = ?
          union all
          select
            t.MedLexemeCode,
            case
              when t.MedLexemeCodeParent = ?
            then null
              else t.medlexemecodeParent
            end,
            t.category,
            t.Name,
            t.position,
            t.treePath,
            case
              when exists(select 1
                          from MedLexeme
                          where coalesce (MedLexemeCodeParent, 0) = t.MedLexemeCode
                          limit 1) then 0
              else 1
            end as "leaf"
          from MedLexeme t
               inner join tree tr on (t.MedLexemeCodeParent = tr.MedLexemeCode))
        select
          MedLexemeCode "medLexemeCode",
          COALESCE(parentId, 0) "parentId",
          COALESCE(category, 0) "category",
          name "name",
          position "position",
          treePath "treePath",
          leaf "leaf"
        from tree tr
        order by position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([isEmptyProperty($params, 'medLexemeCode', 0),isEmptyProperty($params, 'medLexemeCode', 0),isEmptyProperty($params, 'medLexemeCode', 0)]); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass; $item->id = $id = $row['medLexemeCode']; $item->parentId = $row['parentId']; $item->category = $row['category']; $item->name = $row['name']; $item->position = $row['position']; $item->treePath = $row['treePath']; $item->leaf = ($row['leaf'] == 1); $item->loaded = true; $items[$id] = $item; $root[] = $item; } return $root; } public function readLexemesNew($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
with recursive tree (medLexemeCode, parentId, category, name, position, treePath, leaf) AS
        (select
            t.MedLexemeCode,
            t.medlexemecodeParent,
            t.category,
            t.Name,
            t.position,
            t.treePath,
            case
              when exists(select 1
                          from MedLexeme
                          where coalesce (MedLexemeCodeParent, 0) = t.MedLexemeCode
                          limit 1) then false
              else true
            end as "leaf"
          from MedLexeme t
          where t.MedLexemeCodeParent = ?
          union all
          select
            t.MedLexemeCode,
            t.medlexemecodeParent,
            t.category,
            t.Name,
            t.position,
            t.treePath,
            case
              when exists(select 1
                          from MedLexeme
                          where coalesce (MedLexemeCodeParent, 0) = t.MedLexemeCode
                          limit 1) then false
              else true
            end as "leaf"
          from MedLexeme t
               inner join tree tr on (t.MedLexemeCodeParent = tr.MedLexemeCode))
        select
          MedLexemeCode "id",
          COALESCE(parentId, 0) "parentId",
          COALESCE(category, 0) "category",
          name "name",
          position "position",
          treePath "treePath",
          leaf "leaf"
        from tree tr
        order by position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([isEmptyProperty($params, 'id')]); return $stmt->fetchAll(); } public function createLexemes($records) { $db = $this->db ?: $this->db = connectDb(); $sqlGetPosition = <<<"EOT"
select MAX(Position) + 1 "pos"
from medlexeme
where coalesce(medlexemeCodeParent,0) = ?
EOT;
$sql = <<<"EOT"
insert into MedLexeme (MedLexemeCodeParent, Category, Name, Position)
values (?, nullif(?,0), ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmtGetPosition = $db->prepare($sqlGetPosition); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmtGetPosition->execute([ isEmptyProperty($row, 'parentId'), ]); $position = (int) fetchValue($stmtGetPosition, 'pos'); $stmt->execute([ isEmptyProperty($row, 'parentId'), isEmptyProperty($row, 'category'), isEmptyProperty($row, 'name'), $position ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedLexeme'); $rec->position = $position; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateLexemes($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedLexeme set
    MedLexemeCodeParent = case when ? = 1 then nullif(?,0) else MedLexemeCodeParent end,
    Category = case when ? = 1 then nullif(?,0) else Category end,
    Name = case when ? = 1 then ? else Name end,
    Position = case when ? = 1 then ? else Position end
where MedLexemeCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'parentId'), isEmptyProperty($row, 'parentId'), propertyExistsSQL($row, 'category'), isEmptyProperty($row, 'category'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteLexemes($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedLexeme where MedLexemeCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readDicts($params = null) { $db = $this->db ?: $this->db = connectDb(); $dictList = isset($params->dictList) && isEmptyProperty($params, 'dictList') ? $params->dictList : null; $dictSQL = '(1 = 1)'; if (isset($dictList) && is_array($dictList)) { $nameList = ''; foreach ($dictList as $row) { if (isset($row) && trim($row) !== '') { $nameList .= (($nameList === '') ? '' : ',') . '\'' . $row . '\''; } } if ($nameList !== '') { $dictSQL = 'name in (' . $nameList . ')'; } } $sql = <<<"EOT"
select
    MedLexemeCode "id",
    Name "name"
from MedLexeme
where MedLexemeCodeParent is null and
      $dictSQL
order by name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getLexemeInfo($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $name = isset($params->name) && isEmptyProperty($params, 'name') ? $params->name : null; $ret = new stdClass; if ($params === null || $id === null) { return null; } $sql = <<<"EOT"
select
    1 "flag"
from MedLexeme
where coalesce(MedLexemeCodeParent,0) = ?
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); $ret->isExists = fetchValue($stmt, 'flag'); if ($name) { $sql = <<<"EOT"
select
    fn_htmPageFindLexeme(?) "template"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$name]); $ret->template = fetchValue($stmt, 'template'); } return $ret; } public function readLexemeOptions($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; if ($params === null || $id === null) { return null; } $sql = <<<"EOT"
select
    MedLexemeOptionCode "id",
    MedLexemeCode "lexemeId",
    Name "name"
from MedLexemeOption
where MedLexemeCode = ?
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); return $stmt->fetchAll(); } public function updateLexemeOptions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedLexemeOption set
    MedLexemeCode = case when ? = 1 then ? else MedLexemeCode end,
    Name = case when ? = 1 then ? else Name end
where MedLexemeOptionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'lexemeId'), isEmptyProperty($row, 'lexemeId'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteLexemeOptions($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedLexemeOption where MedLexemeOptionCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createLexemeOptions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedLexemeOption (MedLexemeCode, Name)
values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'lexemeId'), isEmptyProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedLexemeOption'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readOptionDicts($params = null) { $db = $this->db ?: $this->db = connectDb(); $dictList = isset($params->dictList) && isEmptyProperty($params, 'dictList') ? $params->dictList : null; $dictSQL = '(1 = 1)'; if (isset($dictList) && is_array($dictList)) { $nameList = ''; foreach ($dictList as $row) { if (isset($row) && trim($row) !== '') { $nameList .= (($nameList === '') ? '' : ',') . '\'' . $row . '\''; } } if ($nameList !== '') { $dictSQL = 'MLO.name in (' . $nameList . ')'; } } $sql = <<<"EOT"
select
       ML.medLexemeCode "id",
       ML.name "name"
from MedLexeme ML
     join MedLexemeOption MLO on MLO.medLexemeCode = ML.medLexemeCode
                             and $dictSQL
where ML.medLexemeCodeParent is null
order by ML.name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } $service = new Junior\Server(new map_med_lexemes()); $service->process(); 