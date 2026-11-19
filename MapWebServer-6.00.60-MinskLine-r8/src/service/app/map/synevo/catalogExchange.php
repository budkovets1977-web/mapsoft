<?php
 require_once(__DIR__ . '/../../init.php'); class map_synevo_catalogExchange { private $db; public $enterpriseCode; const TASK_NAME = 'synevo'; public function startExchange($configs = null) { $this->log("Старт " . __METHOD__); if (empty($configs)) { $configs = getSessionValue('config', ''); } $configList = array_filter(safe_explode(',', preg_replace('/[\s,;]+/', ',', $configs))); if (empty($configList)) { $this->log("Не указаны конфигурации", 2); return false; } foreach ($configList as $cfg) { $this->log("Конфигурация \"$cfg\""); try { $this->db = connectDb($cfg); } catch (Throwable $e) { $this->log($e->getMessage(), 2); continue; } try { $userList = $this->getUserList(); if (empty($userList)) { $this->log("Отсутствует список контрактов", 1); continue; } for ($i = 0; $i < count($userList); $i++) { $user = $userList[$i]; $this->log("Организация \"$user->enterpriseName\""); $username = isEmptyProperty($user, 'username'); $password = isEmptyProperty($user, 'password'); $contractId = isEmptyProperty($user, 'id'); $allServicesFlag = 0; $this->enterpriseCode = $user->enterpriseCode; if (!$username || !$password || !$contractId) { $this->log("Отсутствуют обязательные реквизиты контракта (номер, пользователь и пароль)", 2); continue; } $authToken = $this->getAuthToken($username, $password); if (!$authToken) { $this->log("Ошибка авторизации по контракту " . $contractId, 2); continue; } if ($allServicesFlag === 0) { $allServices = $this->getAllServices($authToken); if (empty($allServices)) { $this->log("Ошибка получения общего каталога услуг", 2); continue; } if (!$this->updateAllServices($allServices)) { $this->log("Ошибка записи общего каталога услуг", 2); continue; } $serviceGroups = $this->getServiceGroups($authToken); if (empty($serviceGroups)) { $this->log("Ошибка получение списка групп", 2); continue; } if (!$this->updateServiceGroups($serviceGroups)) { $this->log("Ошибка записи списка групп", 2); continue; } $allServicesFlag = 1; } if (empty($allServices)) { $this->log("Пропуск итерации из-за ошибки получения общего каталога услуг", 2); continue; } $contractServices = $this->getContractServices($authToken, $contractId); if (empty($contractServices)) { $this->log("Ошибка получения списка услуг по контракту", 2); continue; } if (!$this->updateContractServices($contractServices)) { $this->log("Ошибка записи списка услуг по контракту", 2); continue; } $contractPrices = $this->getContractPrices($authToken, $contractId); if (empty($contractPrices)) { $this->log("Ошибка получения цен по контракту", 2); continue; } if (!$this->updateContractPrices($contractPrices)) { $this->log("Ошибка записи цен по контракту", 2); continue; } } } catch (Throwable $e) { $this->log($e->getMessage(), 2); continue; } } return true; } private function getUserList() { $db = $this->db; $sql = <<<"EOT"
SELECT
    e.EnterpriseCode "enterpriseCode",
    e.Name "enterpriseName",
    sc.Id "id",
    sc.username "username",
    sc.password "password"
FROM SynevoContract sc
    JOIN Enterprise e ON e.EnterpriseCode = sc.EnterpriseCode
WHERE sc.StartDate <= DATE( (LOCALTIMESTAMP)) and sc.FinishDate >= DATE((LOCALTIMESTAMP))
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_OBJ); } private function getAuthToken($userName, $password) { $request = new stdClass(); $request->url = "https://client.synevo.by/api/AuthService.svc/Token?username=" . $userName . "&password=" . $password; $result = httpGET($request); if (empty($result)) { $this->log("Нет ответа от " . $request->url, 2); return false; } switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); $authToken = $result->AuthToken; return $authToken; case "400": $this->log($request->url . " HTTP 400 Bad Request"); return false; } return false; } private function getAllServices($authToken) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/Dictionary.svc/Services?lang=ru"; $result = httpGET($request); if (empty($result)) { return false; } switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); return $result; case "400": $this->log($request->url . " HTTP 400 Bad Request"); return false; } return false; } private function updateAllServices($records) { $db = $this->db; $vowels = array("α", "β"); $replace = array("a", "b"); $sql1 = <<<"EOT"
INSERT INTO SynevoService
    (Id, Code, Name, Note, DiscountType, WorkDay)
    VALUES (?,?,?,?,?,?)
ON CONFLICT (id) DO UPDATE SET
    Code = case when ? = 1 then EXCLUDED.Code else SynevoService.Code end,
    Name = case when ? = 1 then EXCLUDED.Name else SynevoService.Name end,
    Note = case when ? = 1 then EXCLUDED.Note else SynevoService.Note end,
    DiscountType = case when ? = 1 then EXCLUDED.DiscountType else SynevoService.DiscountType end,
    WorkDay = case when ? = 1 then EXCLUDED.WorkDay else SynevoService.WorkDay end
WHERE SynevoService.id = EXCLUDED.id and (SynevoService.Code != EXCLUDED.Code or SynevoService.Name != EXCLUDED.Name or SynevoService.Note != EXCLUDED.Note or SynevoService.DiscountType != EXCLUDED.DiscountType or SynevoService.WorkDay != EXCLUDED.WorkDay)
EOT;
$sql2 = <<<"EOT"
INSERT INTO SynevoServiceList
    (Type, ServiceId, ListId)
    VALUES (:Type,:ServiceId,:ListId)
ON CONFLICT (type, serviceId, listid) DO UPDATE SET
    Type = :Type
WHERE SynevoServiceList.type = EXCLUDED.Type and SynevoServiceList.ServiceId = EXCLUDED.ServiceId and SynevoServiceList.ListId = EXCLUDED.ListId
RETURNING SynevoServiceList.SynevoServiceListCode AS ListId
EOT;
$stmt1 = $db->prepare($sql1); if (is_object($records)) { $records = [$records]; } $deleteServiceArray = array(); $deleteListArray = array(); $db->beginTransaction(); try { foreach ($records as &$row) { $row->Name = str_replace($vowels, $replace, $row->Name); if (isUndefinedProperty($row, 'Id')) { array_push($deleteServiceArray, $row->Id); } $row->Name = $row->Code . ' ' . $row->Name; $stmt1->execute([ isUndefinedProperty($row, 'Id'), isUndefinedProperty($row, 'Code'), isUndefinedProperty($row, 'Name'), isUndefinedProperty($row, 'Remark'), isUndefinedProperty($row, 'IsDiscountAllowed'), isUndefinedProperty($row, 'WorkingDays'), propertyExistsSQL($row, 'Code'), propertyExistsSQL($row, 'Name'), propertyExistsSQL($row, 'Remark'), propertyExistsSQL($row, 'IsDiscountAllowed'), propertyExistsSQL($row, 'WorkingDays') ]); foreach ($row->MedicalServiceGroups as $group) { $params = array(":Type" => 2, ":ServiceId" => isUndefinedProperty($row, 'Id'), ":ListId" => $group); array_push($deleteListArray, selectSqlValue($db, $sql2, $params)); } foreach ($row->Components as $component) { $params = array(":Type" => 1, ":ServiceId" => isUndefinedProperty($row, 'Id'), ":ListId" => $component); array_push($deleteListArray, selectSqlValue($db, $sql2, $params)); } } $deleteString = implode(',', $deleteServiceArray); $deleteListString = implode(',', $deleteListArray); $sql3 = <<<"EOT"
DELETE FROM SynevoService
    WHERE Id NOT IN ($deleteString)
EOT;
$stmt3 = $db->prepare($sql3); $stmt3->execute(); $sql4 = <<<"EOT"
DELETE FROM SynevoServiceList
    WHERE SynevoServiceListCode NOT IN ($deleteListString)
EOT;
$stmt4 = $db->prepare($sql4); $stmt4->execute(); $db->commit(); } catch (Throwable $e) { $this->log("Во время сохранения общего каталога произошла ошибка:" . $e->getMessage(), 2); $db->rollback(); return false; } return true; } private function getContractServices($authToken, $contractId) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/ContractService.svc/Contracts/" . $contractId . "/Services"; $result = httpGET($request); if (empty($result)) { return false; } switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); return $result; case "400": $this->log($request->url . " HTTP 400 Bad Request"); return false; } return false; } private function updateContractServices($records) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO SynevoServicePrice
    (EnterpriseCode, ServiceCode, WorkDay)
    VALUES (?,?,?)
ON CONFLICT (EnterpriseCode, ServiceCode) DO UPDATE SET
    EnterpriseCode = case when ? = 1 then EXCLUDED.EnterpriseCode else SynevoServicePrice.EnterpriseCode end,
    ServiceCode = case when ? = 1 then EXCLUDED.ServiceCode else SynevoServicePrice.ServiceCode end,
    WorkDay = case when ? = 1 then EXCLUDED.WorkDay else SynevoServicePrice.WorkDay end
WHERE SynevoServicePrice.EnterpriseCode = EXCLUDED.EnterpriseCode
    and SynevoServicePrice.ServiceCode = EXCLUDED.ServiceCode
RETURNING SynevoServicePrice.SynevoServicePriceCode AS PriceId
EOT;
if (is_object($records)) { $records = [$records]; } $deleteArray = array(); $db->beginTransaction(); try { foreach ($records as &$row) { $params = array( $this->enterpriseCode, isUndefinedProperty($row, 'ServiceCode'), isUndefinedProperty($row, 'WorkingDays'), $this->enterpriseCode, propertyExistsSQL($row, 'ServiceCode'), propertyExistsSQL($row, 'WorkingDays') ); array_push($deleteArray, selectSqlValue($db, $sql, $params)); } $deleteString = implode(',', $deleteArray); $sql2 = <<<"EOT"
DELETE FROM synevoserviceprice
    WHERE synevoservicepricecode NOT IN ($deleteString)
EOT;
$stmt4 = $db->prepare($sql2); $stmt4->execute(); $db->commit(); } catch (Throwable $e) { $this->log("Во время сохранения услуг по контракту произошла ошибка:" . $e->getMessage(), 2); $db->rollback(); return false; } return true; } private function getServiceGroups($authToken) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/Dictionary.svc/MedicalServiceGroups"; $result = httpGET($request); if (empty($result)) { return false; } switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); return $result; case "400": $this->log($request->url . " HTTP 400 Bad Request"); return false; } return false; } private function updateServiceGroups($records) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO SynevoServiceGroup
    (Id, Code, Name, ParentCode)
    VALUES (?,?,?,?)
ON CONFLICT (Id) DO UPDATE SET
    Code = case when ? = 1 then EXCLUDED.Code else SynevoServiceGroup.Code end,
    Name = case when ? = 1 then EXCLUDED.Name else SynevoServiceGroup.Name end,
    ParentCode = case when ? = 1 then EXCLUDED.ParentCode else SynevoServiceGroup.ParentCode end
WHERE SynevoServiceGroup.Id = EXCLUDED.Id
RETURNING SynevoServiceGroup.Id AS GroupId
EOT;
if (is_object($records)) { $records = [$records]; } $deleteArray = array(); $db->beginTransaction(); try { foreach ($records as &$row) { $params = array( isUndefinedProperty($row, 'Id'), isUndefinedProperty($row, 'Code'), isUndefinedProperty($row, 'Name'), isUndefinedProperty($row, 'ParentGroupId'), propertyExistsSQL($row, 'Code'), propertyExistsSQL($row, 'Name'), propertyExistsSQL($row, 'ParentGroupId'), ); array_push($deleteArray, selectSqlValue($db, $sql, $params)); } $deleteString = implode(',', $deleteArray); $sql2 = <<<"EOT"
DELETE FROM SynevoServiceGroup
    WHERE Id NOT IN ($deleteString)
EOT;
$stmt4 = $db->prepare($sql2); $stmt4->execute(); $db->commit(); } catch (Throwable $e) { $this->log("Во время сохранения групп услуг произошла ошибка:" . $e->getMessage(), 2); $db->rollback(); return false; } return true; } private function getContractPrices($authToken, $contractId) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/ContractService.svc/PriceBookService/" . $contractId . "/Services"; $result = httpGET($request); if (empty($result)) { return false; } switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); return $result; case "400": $this->log($request->url . " HTTP 400 Bad Request"); return false; } return false; } private function updateContractPrices($records) { $db = $this->db; $sql = <<<"EOT"
UPDATE SynevoServicePrice SET
    Price = case when ? = 1 then ? else Price end
WHERE EnterpriseCode = ? and ServiceCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as &$row) { $stmt->execute([ propertyExistsSQL($row, 'Price'), isUndefinedProperty($row, 'Price'), $this->enterpriseCode, isUndefinedProperty($row, 'Code') ]); } $db->commit(); } catch (Throwable $e) { $this->log("Во время сохранения цен услуг произошла ошибка:" . $e->getMessage(), 2); $db->rollback(); return false; } return true; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('map_synevo_catalogExchange'); 