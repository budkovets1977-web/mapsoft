<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_talonServices { private $db; public function readTalonServices($params = null) { $db = $this->db ?: $this->db = connectDb(); $professionId = isset($params->professionId) && isEmptyProperty($params, 'professionId') ? $params->professionId : null; $couponJobs = isset($params->couponJobs) && isEmptyProperty($params, 'couponJobs') ? $params->couponJobs : null; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $search = isset($params->search) ? " AND (TS.Name LIKE '%$params->search%' OR TS.FullCode LIKE '%$params->search%')" : " "; $sqlTable = <<<"EOT"
WITH sel AS(SELECT TalonServiceCode,
       Code,
       FullCode,
       Name,
       TalonServiceCodeParent,
       Active
       FROM talonservice TS
       WHERE Active = 1
EOT;
$sqlSelect = <<<"EOT"
SELECT DISTINCT
    CASE N.SysNumberCode  WHEN 1 THEN parent.TalonServiceCode       WHEN 2 THEN child.TalonServiceCode          ELSE TS.TalonServiceCode       END "id",
    CASE N.SysNumberCode  WHEN 1 THEN parent.Code                   WHEN 2 THEN child.Code                      ELSE TS.Code                   END "code",
    CASE N.SysNumberCode  WHEN 1 THEN parent.FullCode               WHEN 2 THEN child.FullCode                  ELSE TS.FullCode               END "fullCode",
    CASE N.SysNumberCode  WHEN 1 THEN parent.Name                   WHEN 2 THEN child.Name                      ELSE TS.Name                   END "name",
    CASE N.SysNumberCode  WHEN 1 THEN parent.TalonServiceCodeParent WHEN 2 THEN child.TalonServiceCodeParent    ELSE TS.TalonServiceCodeParent END "parentId",
    CASE N.SysNumberCode  WHEN 1 THEN parent.Active                 WHEN 2 THEN child.Active                    ELSE TS.Active                 END "active"
FROM sel AS TS
    JOIN SysNumber AS N ON N.SysNumberCode BETWEEN 1 AND 3
    LEFT OUTER JOIN sel AS parent ON N.SysNumberCode = 1 AND TS.TalonServiceCodeParent = parent.TalonServiceCode
    LEFT OUTER JOIN sel AS child  ON N.SysNumberCode = 2 AND TS.TalonServiceCode = child.TalonServiceCodeParent
WHERE
    ((N.SysNumberCode = 1 AND parent.TalonServiceCode IS NOT NULL) OR (N.SysNumberCode = 2 AND child.TalonServiceCode IS NOT NULL) OR N.SysNumberCode = 3)
     AND (CASE WHEN TS.TalonServiceCodeParent IS NULL THEN (SELECT TalonServiceCode FROM sel WHERE TalonServiceCodeParent = TS.TalonServiceCode LIMIT 1) ELSE 1 END) IS NOT NULL
    $search
EOT;
if (isset($params->professionId) && isset($params->couponJobs)) { $sqlTable .= " AND (SELECT TalonServicePriceCode FROM TalonServicePrice WHERE MedProfessionCode = $professionId AND MedCouponWorkCode =$couponJobs AND EnterpriseCode = $currentEnterprise AND TS.TalonServiceCode = TalonServiceCode LIMIT 1) IS NULL) "; } else { $sqlTable .= ") "; } $sql = $sqlTable . $sqlSelect; $tree = $this->formTree(selectSql($db, $sql)); return $tree; } private function formTree($array, $id = 'id', $parent_id = 'parentId', $children = 'children') { $tree = [[$children => []]]; $references = [&$tree[0]]; foreach ($array as $item) { if (!isset($item[$parent_id])) { $item[$parent_id] = 0; } if (isset($references[$item[$id]])) { $item[$children] = $references[$item[$id]][$children]; } $references[$item[$parent_id]][$children][] = $item; $references[$item[$id]] = &$references[$item[$parent_id]][$children][count($references[$item[$parent_id]][$children]) - 1]; } $final = $this->filterItems($tree[0]['children']); $ff = [$children => $final]; return $ff; } private function filterItems($items) { foreach ($items as &$value) { if (isset($value['children'])) { $value['children'] = $this->filterItems($value['children']); } else { $value['leaf'] = true; } } return $items; } } handleJsonRpc('med_common_talonServices'); 