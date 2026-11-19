<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_map { private $db; private $login; private $password; private $token; private $apiUrl; private $navSystem; private $isActive; const MAP_PERMISSION = 'med-ambulance-map'; const ERROR_NO_MAP_PERMISSION = 'У Вас нет прав доступа к данным GPS-карты. Обратитесь к Администратору.'; public function getAuthData() { try { $extraSettingParams = readExtraSettings(null, 'medAmbulanceNavigationSettings', true); $this->login = $extraSettingParams['clientLogin']; $this->password = $extraSettingParams['clientPassword']; $this->apiUrl = $extraSettingParams['url']; $this->isActive = $extraSettingParams['active']; $this->navSystem = $extraSettingParams['navSystem']; } catch (Throwable $e) { throw new Exception('Не выполнена настройка связи с навигационными системами'); } } public function readCars() { $this->getAuthData(); if ($this->getToken() && $this->isActive) { return $this->getInfo(); } } protected function checkAccessMap () { if (!testUserAccess(self::MAP_PERMISSION)) { throw new Exception(self::ERROR_NO_MAP_PERMISSION, ERROR_NOT_ALLOWED); } } public function readMap() { $this->checkAccessMap(); $this->getAuthData(); if ($this->getToken() && $this->isActive) { $cars = $this->getInfo(); switch ($this->navSystem) { case 'bts': if ($cars->items) { $active = $this->getActiveAmbGraph(); $activeArr = []; $carsArr = []; foreach ($cars->items as $row) { $carsArr[$row->object_id] = $row; } foreach ($active as $row) { array_push($activeArr, $row['carid']); $carsArr[$row['carid']]->type = $row['type']; $carsArr[$row['carid']]->typeName = $row['typeName']; $carsArr[$row['carid']]->teamnumber = $row['teamnumber']; $carsArr[$row['carid']]->specialists = $row['specialists']; $carsArr[$row['carid']]->agid = $row['agid']; $carsArr[$row['carid']]->teamMembers = $row['teamMembers']; $carsArr[$row['carid']]->teamString = $row['teamString']; $carsArr[$row['carid']]->status = $row['status']; if ($row['status'] === 2) { $carsArr[$row['carid']]->priority = $row['priority']; $carsArr[$row['carid']]->priorityName = $row['priorityName']; $carsArr[$row['carid']]->arrivalTime = $row['arrivalTime']; $carsArr[$row['carid']]->transferTime = $row['transferTime']; $carsArr[$row['carid']]->checkOutTime = $row['checkOutTime']; if ($row['priority'] === 1 && !$row['arrivalTime']) { $carsArr[$row['carid']]->address = $row['address']; $carsArr[$row['carid']]->reason = $row['reason']; } } } $arr = []; foreach ($carsArr as $row) { if (in_array($row->object_id, $activeArr)) { array_push($arr, $row); } } return $arr; }; break; case 'rc': if ($cars) { $active = $this->getActiveAmbGraph(); $activeArr = []; $carsArr = []; foreach ($cars as $row) { $carsArr[$row->object_id] = $row; } foreach ($active as $row) { array_push($activeArr, $row['carid']); $carsArr[$row['carid']]->type = $row['type']; $carsArr[$row['carid']]->typeName = $row['typeName']; $carsArr[$row['carid']]->teamnumber = $row['teamnumber']; $carsArr[$row['carid']]->specialists = $row['specialists']; $carsArr[$row['carid']]->agid = $row['agid']; $carsArr[$row['carid']]->teamMembers = $row['teamMembers']; $carsArr[$row['carid']]->teamString = $row['teamString']; $carsArr[$row['carid']]->status = $row['status']; if ($row['status'] === 2) { $carsArr[$row['carid']]->priority = $row['priority']; $carsArr[$row['carid']]->priorityName = $row['priorityName']; $carsArr[$row['carid']]->arrivalTime = $row['arrivalTime']; $carsArr[$row['carid']]->transferTime = $row['transferTime']; $carsArr[$row['carid']]->checkOutTime = $row['checkOutTime']; if ($row['priority'] === 1 && !$row['arrivalTime']) { $carsArr[$row['carid']]->address = $row['address']; $carsArr[$row['carid']]->reason = $row['reason']; } } } $arr = []; foreach ($carsArr as $row) { if (in_array($row->object_id, $activeArr)) { array_push($arr, $row); } } return $arr; }; break; } } } private function getToken() { $navSystem = $this->navSystem; $params = new stdClass(); if ($navSystem && $navSystem == 'bts') { $params->url = $this->apiUrl . '/integration_get_token.php?type=GET_TOKEN&login=' . $this->login . '&password=' . $this->password; $result = httpGET($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $this->token = $result->token; return true; default: return false; } } else if ($navSystem && $navSystem == 'rc') { $params->url = $this->apiUrl; $params->headers['Content-Type'] = "application/json"; $params->request_content = json_encode( array( 'login' => $this->login, 'password' => $this->password), JSON_UNESCAPED_UNICODE); $params->request_method = 'login'; $result = httpPOST($params); if (!$result['status']) return false; switch ($result['status'][1]) { case "200": case 'ok': $result = safe_json_decode($result['body'], true); $this->token = $result['data']['token']; return true; default: return false; } } } private function getInfo() { $navSystem = $this->navSystem; $params = new stdClass(); if ($navSystem && $navSystem == 'bts') { $params->url = $this->apiUrl . '/integration.php?type=CURRENT_POSITION&token=' . $this->token; $result = httpGET($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $cars = $result->root->result; foreach ($cars->items as $car) { $car->object_id = 'gps' . $car->object_id; } return $cars; default: return false; } } else if ($navSystem && $navSystem == 'rc') { $params->url = $this->apiUrl . 'get_last_data_custom?token=' . $this->token; $params->headers['Content-Type'] = "multipart/form-data"; $result = httpPOST($params); switch ($result['status'][1]) { case "200": $cars = safe_json_decode($result['body']); foreach ($cars as $car) { $car->object_name = ' ' . $car->auto_number . ' '; $car->datetime = date('Y-m-d H:i:s', safe_strtotime($car->datetime)); $car->object_id = 'gps' . $car->object_id; } return $cars; default: return false; } } } public function changeBrigadeCar($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'AmbGraph', 'ambGraphCode', $records, 'carId, carnumber, ambCarCode', 'carId, carName, localCarId', 'ambGraphId'); } private function getActiveAmbGraph() { $db = $this->db ?: $this->db = connectDb(); $dateStr = 'now'; $sql = <<<"EOT"
SELECT 
    AG.carId "carid", 
    AG.ambgraphcode "agid",
    AT.type "type",
    CASE AT.type
        WHEN 1 THEN 'Фельдшерская'
        WHEN 2 THEN 'Реанимационная'
        WHEN 3 THEN 'Интенсивной терапии'
        WHEN 4 THEN 'Педиатрическая'
        WHEN 5 THEN 'Психиатрическая'
        WHEN 6 THEN 'Врачебная общепрофильная'
    END "typeName",
    AT.teamnumber "teamnumber",
       
    (SELECT array_to_json(array_agg(row_to_json(t))) FROM (
        SELECT 
            AR.name "name"
        FROM AmbTeamRole ATR
        JOIN AmbRole AR ON AR.ambRoleCode = ATR.ambRoleCode
        WHERE ATR.ambTeamCode = AT.ambTeamCode
    ) as t) as "specialists", -- Список ролей, которые есть в бригаде
       
    AL.address "address",
    coalesce(UD.value, AL.reasonText) "reason",  -- Повод
       
    AG.status "status",  
    CASE AG.status
        WHEN 1 THEN 'на станции' 
        WHEN 2 THEN 'на выезде' 
        WHEN 3 THEN 'возвращается на станцию' 
    END "statusName",
       
       
    AL.priority "priority",
    CASE AL.priority 
        WHEN 1 THEN 'неотложный'
        WHEN 2 THEN 'экстренный'
    END "priorityName", 
        
    AL.arrivalTime "arrivalTime", --Время прибытия
    AL.transferTime "transferTime", --Время передачи
    AL.checkOutTime "checkOutTime", -- Время выезда
    AL.teammembers  "teamMembers",
(SELECT string_agg(AR.name || ': ' || FN_cmFIO(MD.manCode), ';')
FROM AmbGraphPerson AGP
left join AmbRole AR on AGP.ambRoleCode = AR.ambRoleCode
left join MedDoctor MD on MD.medDoctorCode = AGP.medDoctorCode
where AGP.ambGraphCode = AG.ambgraphcode) "teamString"
FROM ambgraph AG
JOIN ambteam AT on AG.ambteamcode = AT.ambteamcode 
JOIN AmbShift AmbS on AG.ambShiftCode = AmbS.ambShiftCode
LEFT JOIN AmbLog AL ON AL.ambLogCode = AG.ambLogCode 
LEFT JOIN UniDict UD ON UD.unidictcode = AL.unidictcodereason 
WHERE AG.inwork = 1 AND AG.carId is not null AND (AG.onDate = '{$dateStr}'::date
      or (AG.onDate = ('{$dateStr}'::date - '1 day'::interval) and AmbS.finishTime < AmbS.startTime))
EOT;
return selectSql($db, $sql); } } handleJsonRpc('med_ambulance_map'); 