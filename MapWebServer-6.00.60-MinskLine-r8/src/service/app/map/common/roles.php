<?php
require_once('rolesPermission.php'); $serviceClass = 'map_common_roles'; class map_common_roles extends common_roles { use map_common_rolesPermission; const PHOTO_CACHE = 'mapCommonRolePhotoCache'; private $db; public function readUserProfile () { $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('user'); $sql = <<<EOT
select
    u.WebUserCode id,
    u.nick,
    u.fio,
    case when u.Photo is not null then 1 else 0 end "photoExists",
    u.profile,
    random() "random",
    u.UserCode "userId",
    case when coalesce(u.Password, '') <> '' then 1 else 0 end "passwordExists",
    MP.PersonalNumber "userInn",
    US.Uuid "practitionerUuid"
from WebUser u
LEFT JOIN MedDoctor MD ON MD.UserCode = u.UserCode
LEFT JOIN ManPassport MP ON MP.ManCode = MD.ManCode
LEFT JOIN Users US ON US.UserCode = u.UserCode
where u.WebUserCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$userId]); $res = $stmt->fetchAll(PDO::FETCH_OBJ); $user = reset($res); if (($profile = safe_json_decode($user->profile))) { if (empty($user->fio) && !empty($profile->fio)) { $user->fio = $profile->fio; } if (isset($profile->sex)) { $user->sex = $profile->sex; } $user->birthDate = dbDate(isUndefinedProperty($profile, 'birthDate')); $user->post = isUndefinedProperty($profile, 'post'); $user->contacts = isUndefinedProperty($profile, 'contacts'); $user->allowChangePassword = isUndefinedProperty($profile, 'allowChangePassword'); $user->allowChangeProfile = isUndefinedProperty($profile, 'allowChangeProfile'); } if (empty($user->sex) && empty($user->isRole)) { $user->sex = detectSex($user->fio ?: ''); } unset($user->profile); return $user; } public function updateUserProfile ($records) { $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('user'); $profile = safe_json_decode(selectSqlValue($this->db, 'select Profile from WebUser where WebUserCode = ?', [$userId] )); if (!isUndefinedProperty($profile, 'allowChangeProfile')) { return false; } $p1 = $p2 = ''; if (isUndefinedProperty($profile, 'allowChangePassword')) { $p1 = 'Password '; $p2 = 'password '; } $this->prepareRecords($records, true); updateSql($db, 'WebUser', 'WebUserCode', $records, "Nick FIO {$p1}Profile", "nick fio {$p2}profile" ); $profile = safe_json_decode(selectSqlValue($this->db, 'select Profile from WebUser where WebUserCode = ?', [$userId] )); $profile = (object) [ 'fio' => isUndefinedProperty($profile, 'fio'), 'sex' => isUndefinedProperty($profile, 'sex'), 'birthDate' => dbDate(isUndefinedProperty($profile, 'birthDate')), 'post' => isUndefinedProperty($profile, 'post'), 'contacts' => isUndefinedProperty($profile, 'contacts') ]; setSessionValue('userProfile', $profile); return $profile; } public function readRoles ($params = null) { $db = $this->db ?: $this->db = connectDb(); $params = $params ?: new stdClass(); $skipId = (int) isEmptyProperty($params, 'skipId', 0); $isAllData = !empty($params->allData); $sqlAllData = ''; if ($isAllData) { $sqlAllData = <<<EOT
, date_trunc('second', u.CreateTime) "createTime"
, date_trunc('second', u.LastLogonTime) "lastLogonTime"
, CASE WHEN COALESCE(u.Password, '') <> '' THEN 1 ELSE 0 END "passwordExists"
, m.idMembers "idMembers"
EOT;
} $sql = <<<EOT
            SELECT
                u.WebUserCode               "id",
                u.nick,
                u.WebEnterpriseCode         "enterpriseId",
                e.Name                      "enterpriseName",
                CAST(CASE WHEN u.ThisRole IS NULL THEN u.fio END AS text)
                                            "fio",
                u.Active                    "active",
                u.Online                    "online",
                u.ThisRole                  "isRole",
                m.displayMembers            "displayMembers",
                u.note,
                CASE WHEN u.Photo IS NOT NULL THEN 1 ELSE 0 END
                                            "photoExists",
                CAST(CASE WHEN u.ThisRole IS NULL THEN u.profile END AS text)
                                            "profile",
                random()                    "random",
                coalesce(u.SuperUser, 0)    "su"
                {$sqlAllData}
            FROM WebUser u
            JOIN WebEnterprise e ON e.WebEnterpriseCode = u.WebEnterpriseCode
                AND e.Active = 1 AND e.Online = 1,
            LATERAL(SELECT {$db->dialect('LIST', 'string_agg')}(r.Nick, ',' ORDER BY r.Nick) displayMembers,
                    {$db->dialect('LIST', 'string_agg')}(r.WebUserCode, ',' ORDER BY r.Nick) idMembers
                FROM WebUserMember m JOIN WebUser r ON r.WebUserCode = m.WebUserCodeRole
                WHERE m.WebUserCode = u.WebUserCode) m
            WHERE
                (:id = 0 OR u.WebUserCode = :id)
                AND (:skipId = 0 OR u.WebUserCode <> :skipId)
                AND (:enterpriseId = 0 OR u.WebEnterpriseCode = :enterpriseId)
                AND (:type = 'all' OR (:type = 'users' AND u.ThisRole IS NULL) OR (:type = 'roles' AND u.ThisRole = 1))
                AND (:activity = 'all' OR (:activity = 'active' AND u.Active = 1) OR (:activity = 'inactive' AND u.Active IS NULL))
                AND (:online = 'all' OR (:online = 'on' AND u.Online = 1) OR (:online = 'off' AND COALESCE(u.Online, 0) = 0))
                AND (CAST(:search AS text) IS NULL
                    OR u.nick LIKE '%' || :search || '%'
                    OR u.fio LIKE '%' || :search || '%'
                    OR m.displayMembers LIKE '%' || :search || '%'
                )
            ORDER BY u.nick
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':id' => isEmptyProperty($params, 'id', 0), ':skipId' => $skipId, ':enterpriseId' => isEmptyProperty($params, 'enterpriseId', 0), ':type' => isEmptyProperty($params, 'type', 'all'), ':activity' => isEmptyProperty($params, 'activity', 'all'), ':online' => isEmptyProperty($params, 'online', 'all'), ':search' => isEmptyProperty($params, 'search') ]); $res = $stmt->fetchAll(PDO::FETCH_OBJ); foreach ($res as $user) { if (($profile = safe_json_decode($user->profile))) { if (empty($user->fio) && !empty($profile->fio)) { $user->fio = $profile->fio; } if (isset($profile->sex)) { $user->sex = $profile->sex; } if ($isAllData) { $user->birthDate = isUndefinedProperty($profile, 'birthDate'); $user->post = isUndefinedProperty($profile, 'post'); $user->contacts = isUndefinedProperty($profile, 'contacts'); $user->allowChangePassword = isUndefinedProperty($profile, 'allowChangePassword'); $user->allowChangeProfile = isUndefinedProperty($profile, 'allowChangeProfile'); } } if (empty($user->sex) && empty($user->isRole)) { $user->sex = detectSex($user->fio ?: ''); } unset($user->profile); } return $res; } public function createRoles ($records) { $db = $this->db ?: $this->db = connectDb(); $this->prepareRecords($records, false); $db->beginTransaction(); try { $result = insertSql($db, 'WebUser', $records, 'Nick FIO WebEnterpriseCode Active Online ThisRole Note Password Profile SuperUser', 'nick fio enterpriseId active online isRole note password profile su', null, 'id', false); $this->updateMembers($records, $result); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function updateRoles ($records) { $db = $this->db ?: $this->db = connectDb(); $this->prepareRecords($records, true); $db->beginTransaction(); try { $result = updateSql($db, 'WebUser', 'WebUserCode', $records, 'Nick FIO WebEnterpriseCode Active Online ThisRole Note Password Profile SuperUser', 'nick fio enterpriseId active online isRole note password profile su', 'id', false); $this->updateMembers($records); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function deleteRoles ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { $this->checkAccess($row, 1); } return deleteSql($db, 'WebUser', 'WebUserCode', $records); } public function readPhoto($params = null) { $raw = null; $cache = getSessionValue(self::PHOTO_CACHE); if (isset($cache)) { $raw = base64_encode(file_get_contents($cache)); } else { $db = $this->db ?: $this->db = connectDb(); if ($params && property_exists($params, 'id')) { $id = $params->id; } else { $id = getSessionValue('user'); } $raw = selectSqlValue($db, "select encode(photo, 'base64') from WebUser where WebUserCode = ?", [$id]); } header('Content-type: image/jpeg'); header('Cache-Control: max-age=31536000'); echo base64_decode($raw); } public function updatePhoto() { header('Content-type: text/html'); $temp = $_FILES['filePhoto']['tmp_name']; $fileName = $temp.'.role.img'; rename($temp, $fileName); setSessionValue(self::PHOTO_CACHE, $fileName); $rec = new stdClass; $rec->success = true; echo json_encode($rec); } public function commitPhoto($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $action = isEmptyProperty($params, 'action', 'cancel'); $f = getSessionValue(self::PHOTO_CACHE); if ($action == "ok") { if (!is_null($f)) { $this->checkAccess($params); $image = file_get_contents($f); $db->beginTransaction(); try { executeSql($db, "update WebUser set photo = decode(?, 'base64') where WebUserCode = ?", [base64_encode($image), $id] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } } if (!is_null($f)) { unlink($f); setSessionValue(self::PHOTO_CACHE, null); } return true; } public function deletePhoto($params) { $db = $this->db ?: $this->db = connectDb(); $this->checkAccess($params); $id = isEmptyProperty($params, 'id', 0); $db->beginTransaction(); try { executeSql($db, "update WebUser set photo = null where WebUserCode = ?", [$id] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function prepareRecords ($records, $isUpdate) { convertSqlParams($records, [ 'null' => 'active isRole password su' ], function (&$row) use ($isUpdate) { $this->checkAccess($row); if (isset($row->password)) { $row->password = md5($row->password); } if (!(isset($row->fio) || isset($row->sex) || isset($row->birthDate) || isset($row->post) || isset($row->contacts) || isset($row->allowChangePassword) || isset($row->allowChangeProfile))) { return; } $profile = null; if ($isUpdate) { $profile = safe_json_decode(selectSqlValue($this->db, 'SELECT Profile FROM WebUser WHERE WebUserCode = ?', [$row->id])); } $profile = $profile ?: new stdClass; if (isset($row->fio)) $profile->fio = $row->fio; if (isset($row->sex)) $profile->sex = $row->sex; if (isset($row->birthDate)) $profile->birthDate = dbDate($row->birthDate); if (isset($row->post)) $profile->post = $row->post; if (isset($row->contacts)) $profile->contacts = $row->contacts; if (isset($row->allowChangePassword)) $profile->allowChangePassword = $row->allowChangePassword; if (isset($row->allowChangeProfile)) $profile->allowChangeProfile = $row->allowChangeProfile; $row->profile = json_encode($profile, JSON_UNESCAPED_UNICODE); }); } private function updateMembers($records, $newRecords = null) { if (!isset($records)) { return; } if (!is_array($records)) { $records = [$records]; } $db = $this->db ?: $this->db = connectDb(); for ($i = 0; $i < count($records); $i++) { if (!property_exists($records[$i], 'idMembers')) { continue; } $idMembers = isEmptyProperty($records[$i], 'idMembers', '0'); $id = is_array($newRecords) ? $newRecords[$i]->id : $records[$i]->id; executeSql($db, "delete from WebUserMember ". "where WebUserCode = ? ". " and WebUserCodeRole not in ($idMembers)", [$id] ); executeSql($db, "insert into WebUserMember (WebUserCode, WebUserCodeRole) ". "select ?, u.WebUserCode from WebUser u ". "where u.WebUserCode in ($idMembers) ". " and not exists ( ". "  select 1 from WebUserMember ". "  where WebUserCodeRole = u.WebUserCode ". "   and WebUserCode = ?)", [$id, $id] ); $this->clearCache($id); } } }