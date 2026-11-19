<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_wealthMeasure { private $db; const MAX_PAGE_SIZE = 30; public function readWealthMeasure($params = null) { $db = $this->db ?: $this->db = connectDb(); $sort = $this->getSort(isEmptyProperty($params, 'sort')); $search = ""; if (isEmptyProperty($params, 'search')) { $search .= "and name LIKE '%$params->search%' or fullName1 LIKE '%$params->search%'"; } $sqlFromWhere = "
    FROM MatWealthMeasure
    WHERE 1=1
    $search"; $sqlTotal = <<<"EOT"
    SELECT
        COUNT(*) "total"
    $sqlFromWhere
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', $total); $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $start = $total < $start ? 0 : $start; $sql = <<< "EOT"
    SELECT
        matWealthMeasureCode "id",
        name "name",
        fullName1 "fullName1",
        fullName3 "fullName3",
        fullName5 "fullName5",
        code "code",
        type "type"
    $sqlFromWhere
    ORDER BY $sort
    limit $limit offset $start
EOT;
$root = new stdClass(); $root->total = $total; $root->items = selectSql($db, $sql); return $root; } public function createWealthMeasure($records) { $db = $this->db ?: $this->db = connectDb(); $sqlInsert = <<< "EOT"
    INSERT INTO MatWealthMeasure(name, fullName1, fullName3, fullName5, code, type)
    VALUES (?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sqlInsert); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'name'), isEmptyProperty($row, 'fullName1'), isEmptyProperty($row, 'fullName3'), isEmptyProperty($row, 'fullName5'), isEmptyProperty($row, 'code'), isEmptyProperty($row, 'type') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MatWealthMeasure'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWealthMeasure($records) { $db = $this->db ?: $this->db = connectDb(); $sqlUpdate = <<< "EOT"
    UPDATE MatWealthMeasure
    SET
        name = case when ? = 1 then ? else name end,
        fullName1 = case when ? = 1 then ? else fullName1 end,
        fullName3 = case when ? = 1 then ? else fullName3 end,
        fullName5 = case when ? = 1 then ? else fullName5 end,
        code = case when ? = 1 then ? else code end,
        type = case when ? = 1 then ? else type end
    WHERE MatWealthMeasureCode = ?
EOT;
$stmt = $db->prepare($sqlUpdate); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'fullName1'), isEmptyProperty($row, 'fullName1'), propertyExistsSQL($row, 'fullName3'), isEmptyProperty($row, 'fullName3'), propertyExistsSQL($row, 'fullName5'), isEmptyProperty($row, 'fullName5'), propertyExistsSQL($row, 'code'), isEmptyProperty($row, 'code'), propertyExistsSQL($row, 'type'), isEmptyProperty($row, 'type'), isEmptyProperty($row, 'id') ]); $rec = new stdClass; $rec->id = $row->id; $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteWealthMeasure($records) { $db = $this->db ?: $this->db = connectDb(); deleteSql($db, 'MatWealthMeasure', 'MatWealthMeasureCode', $records); } private function getSort($sortstr) { if (isset($sortstr)) { $sorters = safe_json_decode($sortstr, false); $sb = array(); foreach ($sorters as $sort) { if ($sort->property === "code") { $sb[] = "RIGHT(REPEAT( E' ' ,20)+asa_trim(code),20) $sort->direction"; } else { $sb[] = "$sort->property $sort->direction"; } } return implode(', ', $sb); } else { return '1'; } } } handleJsonRpc('map_common_wealthMeasure'); 