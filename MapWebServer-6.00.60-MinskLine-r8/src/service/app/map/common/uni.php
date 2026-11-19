<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_uni { private $db; const MAX_PAGE_SIZE = 30; public function readUni($params = null) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $dictNumber = isset($params->dictNumber) && isEmptyProperty($params, 'dictNumber') ? $params->dictNumber : 0; $searchText = isset($params->searchText) && isEmptyProperty($params, 'searchText') ? $params->searchText : null; if (isset($searchText) && (trim($searchText) === '')) { $searchText = null; } $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sqlSearch = ""; if (isset($searchText)) { $sqlSearch .= " and (Name like '%$searchText%'
                        or Code like '%$searchText%' )"; } $sqlTotal = <<<"EOT"
    SELECT
        COUNT(*) "total"
    FROM
     Uni
        WHERE EnterpriseCode = $mapEnterpriseCode
        AND dict = $dictNumber $sqlSearch
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $root = new stdClass(); $total = (int) fetchValue($stmtTotal, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
SELECT
 UniCode "id",
 EnterpriseCode "enterpriseCode",
 Code "code",
 Dict "dictNumber",
 Position "position",
 Name "name"
FROM
 Uni
WHERE EnterpriseCode = $mapEnterpriseCode
AND dict = $dictNumber $sqlSearch
ORDER BY Position
limit $limit offset $offset
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function updateUni($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update Uni set
       Name = case when ? = 1 then ? else Name end,
       Code = case when ? = 1 then ? else Code end,
       Position = case when ? = 1 then ? else Position end,
       Dict = case when ? = 1 then ? else Dict end,
       UniCodeParent = case when ? = 1 then ? else UniCodeParent end,
       EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end
where UniCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'dictNumber'), isUndefinedProperty($row, 'dictNumber'), propertyExistsSQL($row, 'uniCodeParent'), isUndefinedProperty($row, 'uniCodeParent'), propertyExistsSQL($row, 'enterpriseCode'), isUndefinedProperty($row, 'enterpriseCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getMaxCode($params = null) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $dictNumber = isset($params->dictNumber) && isEmptyProperty($params, 'dictNumber') ? $params->dictNumber : 0; $sql = <<<"EOT"
select asa_trim(COALESCE((
     select IncStr(code)
     from uni
     where dict = $dictNumber
     and enterprisecode=$mapEnterpriseCode
     order by RIGHT(REPEAT( E' ' ,20)+asa_trim(code),20) desc LIMIT 1 ),  E'0'));
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCode = fetchValue($stmt, 'asa_trim'); return $maxCode; } public function deleteUni($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "Uni", "UniCode", $records); } public function createUni($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into Uni (Name, Code, EnterpriseCode, Dict, Position, UniCodeParent)
values (?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'code'), getSessionValue('mapEnterpriseCode'), isEmptyProperty($row, 'dictNumber'), isEmptyProperty($row, 'position'), isUndefinedProperty($row, 'uniCodeParent') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_Uni'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_uni'); 