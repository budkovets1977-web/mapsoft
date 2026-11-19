<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_wealth { private $db; public function readWealth($params = null) { $db = $this->db ? : $this->db = connectDb(); $taskTypes = isset($params->taskTypes) && isEmptyProperty($params, 'taskTypes') ? $params->taskTypes : null; $checkedTask = null; if(isset($taskTypes)) { $checkedTask = $this->CheckTasks($taskTypes); } switch ($checkedTask) { case 11: $task = 1; break; case 14: $task = 0; break; default: $task = 1; } $objectId = isEmptyProperty($params, 'objectId'); if (empty($objectId)) { $enterpriseCode = getSessionValue('mapEnterpriseCode'); $privilegedOnly = isset($params->privilegedOnly) ? " AND (COALESCE(MW.Vitality , WO.Vitality, 0)) <> (0)" : " "; $search = isset($params->search) ? " AND (WO.Name LIKE '%$params->search%' OR WO.Cipher LIKE '%$params->search%')" : " "; $cond = ""; if ($taskTypes) { $cond .= "AND ( 0 = 1"; foreach ($taskTypes as $item) { $cond .= " OR TaskNumbers LIKE  E'%,$item,%'"; } $cond .= ")"; } } $sqlWhere = $objectId ? "WO.matWealthObjectCode = $objectId" : <<<"EOT"
    WS.SectionType IS NULL
    $privilegedOnly
    $search
    AND WS.EnterpriseCode = $enterpriseCode
    $cond
EOT;
$sqlOrder = $objectId ? "" : "ORDER BY REPEAT(  E' ' , 20 - LENGTH( (COALESCE( WO.Cipher,  E'' ) ) ) ) + (COALESCE( WO.Cipher,  E'' ) )"; $sql = <<<"EOT"
SELECT
    WO.MatWealthObjectCode "id",
    COALESCE( WO.Cipher,  E'' ) "cipher",
    WO.ClassifierCode "classifierId",
    WO.Name as "name",
    WO.MatWealthSectionCode as "wealthSectionId",
    WO.MatWealthGroupCode "wealthGroupId",
    WO.FullName as "fullName",
    WO.SecondName "secondName",
    WS.Name "sectionName",
    WG.Name "groupName",
    CASE $task = 1 WHEN true THEN COALESCE(MW.Vitality , WO.Vitality, 0) WHEN false THEN WO.Vitality2 END "vitality",
    (CAST((CASE COALESCE(MW.Vitality , WO.Vitality, 0) when 1 then  E'МинЗдрав' when 2 then  E'Гл.Врач' else  E'' end) AS varchar(8))) "vitalityName",
    (CAST((CASE COALESCE(WO.Vitality2, 0) when 1 then  E'Список Минздрава' when 2 then  E'приказ Главврача' when 3 then  E'Решение консилиума' else  E'' end) AS varchar(20))) "vitality2Name",
    CASE $task = 1 WHEN true THEN WO.Vitality WHEN false THEN WO.Vitality2 END "vitalityIdWO",
    COALESCE(MW.Vitality,-1) "vitalityIdW",
    ws.FormType "formType"
FROM
    matWealthObject AS WO
    JOIN MatWealthSection AS WS ON WS.MatWealthSectionCode = WO.MatWealthSectionCode
    LEFT OUTER JOIN MatWealthGroup AS WG ON WG.MatWealthGroupCode = WO.MatWealthGroupCode
    LEFT JOIN MatWealth AS MW ON $task = 1 AND MW.MatWealthCode = COALESCE( (SELECT W.MatWealthCode FROM MatWealth AS W WHERE W.MatWealthObjectCode = WO.MatWealthObjectCode and W.Vitality is not null LIMIT 1 ),0)
WHERE
    $sqlWhere
$sqlOrder
EOT;
return selectSql($db, $sql); } public function CheckTasks($taskTypes) { $Lr = false; $Pers = false; foreach ($taskTypes as $item) { if ($item == 11) { $Lr = true; } if (in_array($item, [14, 15, 30, 41, 42, 44])) { $Pers = true; } } if ($Lr && !$Pers) { return 11; } elseif (!$Lr && $Pers) { return 14; } elseif ((!$Lr && !$Pers) || ($Lr && $Pers)) { return -1; } } public function createWealth($records) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO matWealthObject (cipher, classifiercode, name, fullname, secondname, matwealthsectioncode, matwealthgroupcode, vitality, enterprisecode)
values (?,?,?,?,?,?,?,?,$enterpriseCode)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'classifierId'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'fullName'), isUndefinedProperty($row, 'secondName'), isUndefinedProperty($row, 'wealthSectionId'), isUndefinedProperty($row, 'wealthGroupId'), isUndefinedProperty($row, 'vitality') ]); $ret[] = $rec = new StdClass; $rec->id = $db->lastInsertId('seq_matwealthobject'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWealth($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
UPDATE matWealthObject SET
    cipher = case when ? = 1 then ? else cipher end,
    classifiercode = case when ? = 1 then ? else classifiercode end,
    name = case when ? = 1 then ? else name end,
    fullname = case when ? = 1 then ? else fullname end,
    secondname = case when ? = 1 then ? else secondname end,
    matwealthsectioncode = case when ? = 1 then ? else matwealthsectioncode end,
    matwealthgroupcode = case when ? = 1 then ? else matwealthgroupcode end,
    vitality = case when ? = 1 then ? else vitality end
WHERE matwealthobjectcode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'classifierId'), isUndefinedProperty($row, 'classifierId'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'fullName'), isUndefinedProperty($row, 'fullName'), propertyExistsSQL($row, 'secondName'), isUndefinedProperty($row, 'secondName'), propertyExistsSQL($row, 'wealthSectionId'), isUndefinedProperty($row, 'wealthSectionId'), propertyExistsSQL($row, 'wealthGroupId'), isUndefinedProperty($row, 'wealthGroupId'), propertyExistsSQL($row, 'vitality'), isUndefinedProperty($row, 'vitality'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getNextCipher() { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(cipher), 20)) as varchar(20))
    FROM
        matWealthObject
    WHERE
        matwealthobjectcode = (SELECT max(matwealthobjectcode) from matWealthObject)
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "cipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'cipher'); return $cipher; } public function deleteWealth($records) { $db = $this->db ? : $this->db = connectDb(); return deleteSql($db, 'matWealthObject', 'matwealthobjectcode', $records); } } handleJsonRpc('map_common_wealth'); 