<?php
 $serviceClass = 'map_common_login'; class map_common_login extends common_login { const ERROR_ENTERPRISE_NOT_LINK = -1001; const NEW_CUSTOMER_NAME = 'Текущая организация'; const NEW_USER_NAME = 'Администратор'; const timeout = 60 * 60; const wait = 60; const maxcount = 3; public function readCustomers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $domainsSql = isColumnExists($db, 'WebEnterprise', 'Domains') ? ', Domains "domains"' : ''; $sql = <<<"EOT"
select
    WebEnterpriseCode "id",
    Name "name",
    UnionGroup "group",
    IsParent "isParent",
    EnterpriseCode "mapEnterpriseCode"
    $domainsSql
from FN_webGetEnterprises()
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $entrance = !empty($params->entrance); $ret = []; while(($row = $stmt->fetch())) { if ($entrance && !empty($row['domains']) && preg_match("/{$row['domains']}/im", $_SERVER['SERVER_NAME']) !== 1) { continue; } $ret[] = $row; } if (empty($ret)) { $ret = [self::createCustomer($params)]; } return $ret; } public function readUsers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $params = $params ?: new stdClass; $users = isEmptyProperty($params, 'users'); $customerId = isEmptyProperty($params, 'customerId', empty($users) ? getSessionValue('customer') : null); $sqlWhere = empty($users) ? '' : 'WHERE U.WebUserCode ' . createSqlInCondition($users); $sql = <<<SQL
SELECT
    U.WebUserCode "id",
    U.WebEnterpriseCode "customerId",
    U.Nick "name",
--    WebFuncs "roles",
    U.MapNick "mapName",
    U.MapUserCode "mapUserCode",
    (SELECT (WU.Profile::json)->>'post' FROM WebUser WU WHERE WU.WebUserCode = U.WebUserCode) "post"
FROM FN_webGetUsers(?::int) AS U
$sqlWhere
SQL;
$stmt = $db->prepare($sql); $stmt->execute([$customerId]); $ret = $stmt->fetchAll(); if (empty($ret) && !empty($users)) { $ret = [self::createUser($params)]; } return $ret; } public function login ($userName, $password, $customerId) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $selectVersionWhatsNewWeb = isColumnExists($db, 'Users', 'VersionWhatsNewWeb') ? "(select VersionWhatsNewWeb from Users where UserCode = u.MapUserCode)" : "''"; $sql = <<<SQL
select
    WebUserCode "id",
    WebEnterpriseCode "customerId",
    Nick "name",
--    WebFuncs "roles",
    MapNick "mapName",
    MapUserCode "mapUserCode",
    (select 1 from WebUser where WebUserCode = u.WebUserCode and Photo is not null) "existsPhoto",
    $selectVersionWhatsNewWeb "versionWhatsNewWeb"
from FN_webGetUsers(?) u
SQL;
$stmt = $db->prepare($sql); $stmt->execute([$customerId]); $users = $stmt->fetchAll(); if (empty($users)) { $users = [self::createUser((object)['customerId' => $customerId])]; } $user = null; foreach ($users as $value) { if ($value['name'] === $userName) { $user = $value; break; } } if (empty($user)) { throw new Exception("Несуществующий пользователь \"$userName\""); } $row_webAuthLogin = selectSql($db, "select
      u.Nick,
      COALESCE(u.Active, 0) AS uActive,
      COALESCE(u.Online, 0) AS uOnline,
      COALESCE(u.Password,  '') AS Password,
      e.Name,
      COALESCE(e.Active, 0) AS eActive,
      COALESCE(e.Online, 0) AS eOnline,
      COALESCE(u.logintrycount, 0) AS logintrycount,
      CASE WHEN lastlogintrytime IS NOT NUll THEN extract(epoch from (now() - lastlogintrytime::timestamp)) ELSE -1 END AS timeOut
    from
      WebUser AS u
    join WebEnterprise AS e on e.WebEnterpriseCode = u.WebEnterpriseCode
    where
      WebUserCode = ?", [$user['id']]); $this->invalidAuthorizations($user['id'],3,$row_webAuthLogin[0]["timeout"],$row_webAuthLogin[0]["logintrycount"]); $row = ['CCode' => null, 'ECode' => 0, 'EMsg' => '']; $row['CCode'] = uniqid(); $db->beginTransaction(); try { executeSql ($db, "UPDATE WebUser set confirm = ? WHERE  WebUserCode = ?;", [$row['CCode'], $user['id']]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (empty($row_webAuthLogin)) { $row['ECode'] = -1; $row['EMsg'] = 'Пользователь с id = ' . $user['id'] . ' отсутствует в сервере МАП'; } else { $row_webAuthLogin = $row_webAuthLogin[0]; switch (true) { case $row_webAuthLogin['eactive'] != 1: $row['ECode'] = -5; $row['EMsg'] = 'Организация "' . $row_webAuthLogin['name'] . '" удалена из сервера МАП'; break; case $row_webAuthLogin['eonline'] != 1: $row['ECode'] = -6; $row['EMsg'] = 'Организация "' . $row_webAuthLogin['name'] . '" отключена от онлайна в сервере МАП'; break; case $row_webAuthLogin['uactive'] != 1: $row['ECode'] = -2; $row['EMsg'] = 'Пользователь "' . $row_webAuthLogin['nick'] . '" удален из сервера МАП'; break; case $row_webAuthLogin['uonline'] != 1: $row['ECode'] = -2; $row['EMsg'] = 'Пользователь "' . $row_webAuthLogin['nick'] . '" отключен от онлайна в сервере МАП'; break; case (($row_webAuthLogin['password'] || $password) && md5($password) !== $row_webAuthLogin['password']): $row = selectSql($db, 'select CCode "CCode", ECode "ECode", EMsg "EMsg"
                            from
                                FN_webAuthLogin(?, ?)', [$user['id'], $password]); if (empty($row)) { throw new Exception("Неизвестная ошибка авторизации пользователя", 1); } $row = $row[0]; if ($row['ECode'] == 0) { break; } $row['ECode'] = -3; $row['EMsg'] = 'Неверный пароль'; break; default: $row['ECode'] = 0; $row['EMsg'] = ''; break; } } if ($row['ECode'] != 0) { $row['CCode'] = null; throw new Exception($row['EMsg'], $row['ECode']); } $row = selectSql($db, 'select
    ECode "ECode",
    EMsg "EMsg",
    EnterpriseCode "EnterpriseCode",
    WebFuncs "WebFuncs",
    Profile "Profile"
from
    FN_webAuthConfirm(?, ?)', [$user['id'], $row['CCode']]); if (empty($row)) { throw new Exception("Неизвестная ошибка подтверждения авторизации", 1); } $row = $row[0]; if ($row['ECode'] != 0) { throw new Exception($row['EMsg'], $row['ECode']); } if (!$row['EnterpriseCode']) { throw new Exception("У текущей веб-организации не настроена привязка к организации МАП!", self::ERROR_ENTERPRISE_NOT_LINK); } $userProfile = isset($row['Profile']) ? safe_json_decode($row['Profile']) : new \stdClass; setSessionValue( 'mapEnterpriseCode', $row['EnterpriseCode'], 'userProfile', $userProfile ); $db->commit(); } catch (Throwable $e) { $db->rollback(); $code = $e->getCode(); if($code == -3 || $code == -11) { $this->invalidAuthorizations($user['id'],1); if($code == -11 && (($row_webAuthLogin[0]["logintrycount"]-1)/self::maxcount) == 2) { $this->doubleInvalidAuthorizations($user['id']); } } throw $e; } $this->invalidAuthorizations($user['id'],2); setSessionValue( 'user', $user['id'], 'userName', $user['name'], 'customer', $customerId, 'customerName', $row_webAuthLogin['name'], 'mapEnterpriseCode', $row['EnterpriseCode'], 'userProfile', $userProfile ); return [ 'user' => $user, 'profile' => $userProfile ]; } private function doubleInvalidAuthorizations($userId) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $maxCount = self::maxcount; $sql = <<<"EOT"
UPDATE webuser
SET logintrycount = $maxCount,
lastlogintrytime = now()
where webusercode = :id
EOT;
$sqlParams[':id'] = $userId; executeSql($db,$sql,$sqlParams); } private function invalidAuthorizations($userId, $type, $time = 0, $countinvalidAuthorizations = 0) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; switch ($type) { case 1: $sql = "UPDATE webuser
                set logintrycount = CASE
                    WHEN logintrycount is NULL
                        THEN 1
                    WHEN extract(epoch from (now() - lastlogintrytime::timestamp)) < :timeout AND logintrycount is NOT NULL
                        THEN  logintrycount + 1 ELSE 1 END,
                    lastlogintrytime = now()
                where webusercode = :id"; $sqlParams[':id'] = $userId; $sqlParams[':timeout'] = self::timeout; executeSql($db,$sql,$sqlParams); break; case 2: $sql = "UPDATE webuser
                        SET logintrycount = 0
                        where webusercode = :id"; $sqlParams[':id'] = $userId; executeSql($db,$sql,$sqlParams); break; case 3: $restTime = round((self::wait - $time)); if ((self::maxcount <= $countinvalidAuthorizations) and ($restTime > 0)) { throw new Exception("Превышено количество попыток ввода пароля, попробовать снова можно через $restTime секунд", -11); } break; } } private function createCustomer($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = (int) selectSqlValue($db, "
            select min(EnterpriseCode)
            from EnterpriseInKind
            where EnterpriseCode <> 0
             and EnterpriseKindCode in (
              select EnterpriseKindCode
              from EnterpriseKind
              where Name like 'текущая')" ); $db->beginTransaction(); try { if ($id == 0) { executeSql($db, "
                    insert into Enterprise (Name, FullName, StartDate, FinishDate)
                    values (:name, :name, '1900-01-01', '3000-01-01')", ['name' => self::NEW_CUSTOMER_NAME] ); $id = $db->lastInsertId('seq_Enterprise'); executeSql($db, "
                    insert into EnterpriseInfo (ChangeDate, EnterpriseCode, Name, FullName)
                    select '1900-01-01', EnterpriseCode, Name, Name
                    from Enterprise
                    where EnterpriseCode = :id", ['id' => $id] ); } executeSql($db, "
                insert into WebEnterprise (EnterpriseCode, Name, Active, Online)
                select EnterpriseCode, Name, 1, 1
                from Enterprise
                where EnterpriseCode = :id", ['id' => $id] ); $webId = $db->lastInsertId('seq_WebEnterprise'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return [ 'id' => $webId, 'name' => self::NEW_CUSTOMER_NAME, 'group' => null, 'isParent' => 1, 'mapEnterpriseCode' => $id ]; } private function createUser($params = null) { $db = $this->db ?: $this->db = connectDb(); $customerId = isEmptyProperty($params, 'customerId', getSessionValue('customer')); $db->beginTransaction(); try { executeSql($db, "
                insert into WebUser (Nick, Active, Online, WebEnterpriseCode)
                values (:name, 1, 1, :customerId)", [ 'name' => self::NEW_USER_NAME, 'customerId' => $customerId ] ); $id = $db->lastInsertId('seq_WebUser'); $mapId = $db->lastInsertId('seq_Users'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return [ 'id' => $id, 'customerId' => $customerId, 'name' => self::NEW_USER_NAME, 'mapName' => self::NEW_USER_NAME, 'mapUserCode' => $mapId ]; } }