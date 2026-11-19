<?php
 trait map_common_rolesPermission { protected $ERROR_SU_DONT_EDIT = 'Нельзя редактировать признак суперпользователя'; protected $ERROR_SU_DONT_EDIT_CODE = -601; protected $ERROR_SU_DONT_TRASH = 'Нельзя редактировать признак активности'; protected $ERROR_SU_DONT_TRASH_CODE = -602; protected $ERROR_SU_DONT_DELETE = 'Нельзя удалить запись'; protected $ERROR_SU_DONT_DELETE_CODE = -603; protected $ERROR_SU_DONT_ONLINE = 'Нельзя редактировать признак активной учётной записи'; protected $ERROR_SU_DONT_ONLINE_CODE = -604; protected $ERROR_DONT_EDIT_SU = 'Редактирование информации по суперпользователю запрещено'; protected $ERROR_DONT_EDIT_SU_CODE = -605; public function readPermissions ($params = null) { $db = $this->db ?: $this->db = connectDb(); executeSql($db, 'select set_config(?, ?, false)', ['map.webusercode', ''] ); try { $params = $params ?: new stdClass(); $node = isEmptyProperty($params, 'node', ''); $taskId = isEmptyProperty($params, 'taskId', 0); if (substr($node, 0, 1) === '@') { return $this->readResources($params); } $sql = <<<"EOT"
SELECT
    Permission AS id,
    Name AS text,
    PermissionParent AS parent,
    (SELECT Value FROM WebUserAssignment WHERE WebUserCode = :userId AND Permission = f.Permission) AS "assigned",
    FN_webUserAccess(f.Permission, :userId) AS "actual",
    FN_webUserAccess(f.Permission, :userId, 1) AS "inherite",
    PermissionDefault as "permissionDefault"
FROM
    FN_WebUserGetTreePermissions(:taskId) f
ORDER BY
    Level, Pos
EOT;
$stmt = $db->prepare($sql); $res = []; $items = []; $stmt->execute([ ':userId' => isEmptyProperty($params, 'userId', 0), ':taskId' => $taskId ]); while (($item = $stmt->fetch(PDO::FETCH_OBJ))) { if ($item->text === '-') { continue; } $items[$item->id] = $item; if (isset($item->parent)) { if (isset($items[$item->parent])) { $parent = $items[$item->parent]; $parent->children[] = $item; } } else { $res[] = $item; } } foreach ($items as $item) { $item->actualOld = $item->actual; $item->text = preg_replace('/({|}|&)/u', '', $item->text); if (empty($item->children) && substr($item->id, 0, 1) !== '@') { $item->loaded = 1; } } if (!empty($res)) { $res[0]->text = 'Все'; $res[0]->expanded = true; $fn = function (&$items, $key, $fn) { $item = $items[$key]; if (isset($item->children)) { for ($i = count($item->children) - 1; $i >= 0; $i--) { $fn($item->children, $i, $fn); } $item->children = array_values($item->children); } if (substr($item->id, 0, 1) === '#' && empty($item->children)) { unset($items[$key]); } }; $fn($res, 0, $fn); } } finally { executeSql($db, 'select set_config(?, ?, false)', ['map.webusercode', (string) getSessionValue('user')] ); } return $res; } public function readResources ($params) { $node = $params->node; if (substr($node, 0, 14) === '@organization-') return $this->readOrganizationResources($params); if (substr($node, 0, 8) === '@office-') return $this->readOfficeResources($params); if (substr($node, 0, 12) === '@med-sector-') return $this->readMedSectorResources($params); } public function updatePermissions ($records, $params) { $db = $this->db ?: $this->db = connectDb(); $userId = isUndefinedProperty($params, 'userId', 0); $this->checkAccess((object) ["id" => $userId]); if (!$userId) { throw new Exception("Переданы неверные параметры (params->userId)", -1); } $sql = <<<"EOT"
insert into WebUserAssignment (CachedValue, Permission, Value, WebUserCode)
values (null, :permission, :value, :userId)
  on conflict (WebUserCode, Permission) do
    update set
      CachedValue = null,
      Permission = :permission,
      Value = :value
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { $stmt->execute([ ':userId' => $userId, ':permission' => $row->id, ':value' => $row->assigned, ]); } $this->clearCache($userId); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function clearCache ($userId) { $db = $this->db ?: $this->db = connectDb(); executeSql($db, "update WebUserAssignment set CachedValue = null where WebUserCode = ? and CachedValue is not null", [$userId] ); executeSql($db, "update WebUserAssignment as a set CachedValue = null ". "from WebUser u ". "join WebUserMembership m on m.WebUserCodeRole = u.WebUserCode ". "where u.WebUserCode = ? and u.ThisRole = 1 ". "   and a.WebUserCode = m.WebUserCode ". "   and a.CachedValue is not null", [$userId] ); } private function checkAccess($row, $action = 0) { if (!is_object($row)) { return; } $db = $this->db ?: $this->db = connectDb(); $columnSuperUser = isColumnExists($db, 'webuser', 'superuser') ? "SuperUser" : "0"; $currentIsSu = getSessionValue('su', false); $currentUserId = getSessionValue('user'); $userId = (int) $row->id; $userIsSu = (int) selectSqlValue($db, "select $columnSuperUser from WebUser where WebUserCode = ?", [$userId] ); if ($currentIsSu && $userId == $currentUserId) { if ($action == 1) { throw new Exception($this->ERROR_SU_DONT_DELETE, $this->ERROR_SU_DONT_DELETE_CODE); } else { if (property_exists($row, 'su')) { throw new Exception($this->ERROR_SU_DONT_EDIT, $this->ERROR_SU_DONT_EDIT_CODE); } if (property_exists($row, 'active')) { throw new Exception($this->ERROR_SU_DONT_TRASH, $this->ERROR_SU_DONT_TRASH_CODE); } if (property_exists($row, 'online')) { throw new Exception($this->ERROR_SU_DONT_ONLINE, $this->ERROR_SU_DONT_ONLINE_CODE); } } } if (!$currentIsSu) { if ($action == 0) { if (($userId != 0 && property_exists($row, 'su')) || ($userId == 0 && isUndefinedProperty($row, 'su') == 1)) { throw new Exception($this->ERROR_SU_DONT_EDIT, $this->ERROR_SU_DONT_EDIT_CODE); } if ($userIsSu) { throw new Exception($this->ERROR_DONT_EDIT_SU, $this->ERROR_DONT_EDIT_SU_CODE); } } elseif ($action == 1) { if ($userIsSu) { throw new Exception($this->ERROR_SU_DONT_DELETE, $this->ERROR_SU_DONT_DELETE_CODE); } } } } protected function readMedSectorResources ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    '@med-sector-' || pg.MedPatientGroupCode AS id,
    pg.Name || CASE WHEN d.ManCode IS NULL THEN ''
        ELSE ' (' || FN_cmFIO(d.ManCode, 1) || ')' END AS text,
    '@med-sector-root' AS parent,
    (SELECT Value FROM WebUserAssignment
        WHERE WebUserCode = :userId AND Permission = ('@med-sector-' || pg.MedPatientGroupCode)) AS "assigned",
    FN_webUserAccessMedSector(pg.MedPatientGroupCode, :userId) AS "actual",
    FN_webUserAccessMedSector(pg.MedPatientGroupCode, :userId, 1) AS "inherite",
    1 AS loaded
FROM
    MedPatientGroup pg
    LEFT OUTER JOIN MedDoctor d ON d.MedDoctorCode = pg.MedDoctorCode
WHERE EnterpriseCode = :enterpriseId
ORDER BY text
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':userId' => isEmptyProperty($params, 'userId', 0), ':enterpriseId' => $params->enterpriseId ]); $res = []; while (($item = $stmt->fetch(PDO::FETCH_OBJ))) { $item->actualOld = $item->actual; $res[] = $item; } return $res; } protected function readOrganizationResources ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    '@organization-' || e.EnterpriseCode AS id,
    w.Name AS text,
    CAST(:parent AS text) AS parent,
    (SELECT Value FROM WebUserAssignment
        WHERE WebUserCode = :userId AND Permission = ('@organization-' || e.EnterpriseCode)) AS "assigned",
    FN_webUserAccessOrganization(e.EnterpriseCode, :userId) AS "actual",
    FN_webUserAccessOrganization(e.EnterpriseCode, :userId, 1) AS "inherite",
    CASE WHEN EXISTS(
        SELECT 1 FROM Enterprise e2
            JOIN WebEnterprise w2 ON w2.EnterpriseCode = e2.EnterpriseCode AND w2.Active = 1 AND w2.Online = 1
        WHERE e2.EnterpriseParentCode = e.EnterpriseCode)
        THEN 0 ELSE 1 END AS loaded
FROM Enterprise e
    JOIN WebEnterprise w ON w.EnterpriseCode = e.EnterpriseCode AND w.Active = 1 AND w.Online = 1
WHERE ((:parentId = 0 AND e.EnterpriseParentCode IS NULL) OR e.EnterpriseParentCode = :parentId)
    AND e.EnterpriseCode <> 0
ORDER BY CASE WHEN e.EnterpriseCode = :enterpriseId THEN 0 ELSE 1 END, w.Name
EOT;
$parent = $params->node; if ($parent === '@organization-root') { $parentId = 0; } else { $parentId = (int)substr($parent, 14); } $stmt = $db->prepare($sql); $stmt->execute([ ':userId' => isEmptyProperty($params, 'userId', 0), ':parent' => $parent, ':parentId' => $parentId, ':enterpriseId' => $params->enterpriseId ]); $res = []; while (($item = $stmt->fetch(PDO::FETCH_OBJ))) { $item->actualOld = $item->actual; $res[] = $item; } return $res; } protected function readOfficeResources ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    '@office-' || es.EnterpriseStructureCode AS id,
    esi.Name AS text,
    CAST(:parent AS text) AS parent,
    (SELECT Value FROM WebUserAssignment
        WHERE WebUserCode = :userId AND Permission = ('@office-' || es.EnterpriseStructureCode)) AS "assigned",
    FN_webUserAccessOffice(es.EnterpriseStructureCode, :userId) AS "actual",
    FN_webUserAccessOffice(es.EnterpriseStructureCode, :userId, 1) AS "inherite",
    CASE WHEN EXISTS(
        SELECT 1 FROM EnterpriseStructure
            WHERE EnterpriseStructureParentCode = es.EnterpriseStructureCode)
        THEN 0 ELSE 1 END AS loaded
FROM EnterpriseStructure es
    JOIN EnterpriseStructureInfo esi ON esi.EnterpriseStructureCode = es.EnterpriseStructureCode
WHERE ((:parentId = 0 AND es.EnterpriseStructureParentCode IS NULL) OR es.EnterpriseStructureParentCode = :parentId)
    AND es.EnterpriseCode = :enterpriseId
ORDER BY esi.Name
EOT;
$parent = $params->node; if ($parent === '@office-root') { $parentId = 0; } else { $parentId = (int)substr($parent, 8); } $stmt = $db->prepare($sql); $stmt->execute([ ':userId' => isEmptyProperty($params, 'userId', 0), ':parent' => $parent, ':parentId' => $parentId, ':enterpriseId' => $params->enterpriseId ]); $res = []; while (($item = $stmt->fetch(PDO::FETCH_OBJ))) { $item->actualOld = $item->actual; $res[] = $item; } return $res; } }