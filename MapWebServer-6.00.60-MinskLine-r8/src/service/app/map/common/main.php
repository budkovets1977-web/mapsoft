<?php
 $serviceClass = 'map_common_main'; class map_common_main extends common_main { const ERROR_USER_NOT_LINK = -1001; const ERROR_TASK_NOT_FOUND = -1002; const ERROR_TASK_NOT_ALLOWED = -1003; public function changeApp () { $ret = parent::changeApp(); $db = $this->db ?: $this->db = connectDb(); $user = getSessionValue('user'); $app = getSessionValue('app'); $userCode = isColumnExists($db, 'webuser', 'usercode') ? "u.UserCode" : "null"; $columnSuperUser = isColumnExists($db, 'webuser', 'superuser') ? "u.SuperUser" : "0"; $sql = <<<"EOT"
select {$db->sybase('first')}
    t.WebTaskCode "task",
    -- t.Links "links",
    coalesce($userCode, u.UserCode) "userCode",
    fn_WebUserAccess('task-' || t.App, :userId) "allow",
    $columnSuperUser "superuser"
from WebTask t
left outer join WebUser u on u.WebUserCode = :userId
where t.App = :app
{$db->postgre('limit 1')}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':userId' => $user, ':app' => $app ]); $row = $stmt->fetch(); if (empty($row)) { throw new Exception("Ошибка загрузки настроек задачи!", self::ERROR_TASK_NOT_FOUND); } if ((int) $row['allow'] == 0) { throw new Exception("Доступ к задаче запрещен! Обратитесь к Администратору.", self::ERROR_TASK_NOT_ALLOWED); } $mapUserCode = (int) $row['userCode']; if (!$mapUserCode) { throw new Exception("У текущего веб-пользователя не настроена привязка к пользователю МАП!", self::ERROR_USER_NOT_LINK); } $task = $row['task']; $superuser = !empty($row['superuser']); $sql = <<<"EOT"
select
    string_agg(EnterpriseCode::text, ',') "userEnterpriseCodes"
from WebEnterprise
where coalesce(EnterpriseCode, 0) <> 0
    and fn_WebUserAccessOrganization(EnterpriseCode, ?) = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $user ]); $row = $stmt->fetch(); setSessionValue( 'mapWebTaskCode', $task, 'mapUserCode', $mapUserCode, 'mapUserEnterpriseCodes', $row['userEnterpriseCodes'], 'su', $superuser ); $ret->su = $superuser; return $ret; } public function readTasks ($params = null) { $user = getSessionValue('user'); $db = $this->db ?: $this->db = connectDb(); $all = isEmptyProperty($params, 'all', 0); $app = isEmptyProperty($params, 'app'); if (empty($app)) { $rows = selectSql($db, <<<SQL
SELECT
    App "app",
    WebTaskCode "taskCode"
FROM
    FN_webGetTasks(:userId)
WHERE
    (:all = 1 OR (:all = 0 AND fn_WebUserAccess('task-' || App, :userId) = 1))
SQL,
[ ':all' => $all, ':userId' => $user ]); } else { $rows = selectSql($db, 'SELECT :app "app", FN_webGetTaskCode(:app) "taskCode"', ['app' => $app]); } $needMobile = !!isUndefinedArrayValue(getSessionValue('appConfig'), 'mobile'); $configName = getSessionValue('config'); $apps = loadAppConfigs(); $ret = []; foreach ($rows as $row) { $appName = $row['app']; if (isset($apps->{$appName})) { $cfg = getTestedAppConfig($appName, $configName); if (!empty($cfg)) { $appMobile = !!isUndefinedArrayValue($cfg, 'mobile'); $fullscreen = isUndefinedArrayValue($cfg, 'fullscreen', false); if ($all == 1 || (!$fullscreen && $appMobile === $needMobile)) { unsetAppConfigBackend($cfg); $cfg['taskCode'] = $row['taskCode']; $cfg['taskName'] = isUndefinedArrayValue($cfg, 'title'); $ret[] = $cfg; } } } } return $ret; } public function readNavigatorItems ($params) { if (!empty($params->node)) { return null; } $db = $this->db ?: $this->db = connectDb(); $user = getSessionValue('user'); $task = isEmptyProperty($params, 'task', ''); $columnSuperUser = isColumnExists($db, 'webuser', 'superuser') ? "SuperUser" : "0"; $sql = <<<"EOT"
select
    SysMenuCode "id",
    SysMenuParentCode "parentId",
    Name "name",
    ModuleFileName widget,
    State state,
    Args args,
    ExData "exData"
from FN_webGetNVItems(:task)
where ModuleFileName is null or fn_WebUserAccess(Permission, :userId) = 1
    or (ModuleFileName = 'common-roles' and (select $columnSuperUser from WebUser where WebUserCode = :userId) = 1)
order by Level, Pos
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':task' => $task, ':userId' => $user ]); $items = []; $rows = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass; $item->id = $id = $row['id']; $item->name = $row['name']; $items[$id] = $item; $rows[$id] = $row; $parentId = $row['parentId']; if (isset($parentId) && !isset($items[$parentId])) { continue; } $widget = $row['widget']; if (!empty($widget)) { $item->leaf = true; $item->widget = $widget; } if (isset($parentId)) { $parent = $items[$parentId]; if (!isset($parent->children)) { $parent->children = []; } $parent->children[] = $item; } else { $root[] = $item; } $args = $row['args']; if (!empty($args)) { $args = json_clean_decode($args); if (isset($args->iconCls)) { $item->iconCls = $args->iconCls; unset($args->iconCls); } else if (isset($item->widget) && $item->widget === 'map-reports-params') { $item->iconCls = 'x-fa fa-file-text-o'; } if (isset($args->expanded) && !isset($item->leaf)) { $item->expanded = $args->expanded; unset($args->expanded); } $item->params = $args; } $item->exData = $row['exData']; } if (count($root) > 1) { $ret = new \stdClass; $ret->children = $root; } else { $ret = $root[0]; } $this->deleteEmptyFolders($ret, true); $this->skipLevels($ret, $rows); return $ret; } private function deleteEmptyFolders ($parent, $expanded) { if (isset($parent->children)) { $children = $parent->children; $separator = false; $last = true; for ($i = count($children) - 1; $i >= 0; --$i) { $child = $children[$i]; if (isset($expanded) && !isset($child->leaf) && !isset($child->expanded)) { $child->expanded = $expanded; } $this->deleteEmptyFolders($child, isset($child->expanded) ? $child->expanded : $expanded); if ($child->name === '-') { unset($children[$i]); $separator = true; } else if (!isset($child->leaf) && empty($child->children)) { unset($children[$i]); } else { if ($separator && !$last) { $child->cls = 'app-separator'; } $separator = false; $last = false; } } if (empty($children)) { unset($parent->children); } else { $parent->children = array_values($children); } } } private function skipLevels ($parent, &$rows) { if (isset($parent->children)) { $new = []; $children = &$parent->children; for ($i = 0; $i < count($children); ++$i) { $child = $children[$i]; $this->skipLevels($child, $rows); $state = $rows[$child->id]['state']; if ($state == 7 || ($state == 4 && count($children) == 1)) { if (isset($child->children)) { array_add($new, $child->children); } } else { $new[] = $child; } } $parent->children = $new; } } } 