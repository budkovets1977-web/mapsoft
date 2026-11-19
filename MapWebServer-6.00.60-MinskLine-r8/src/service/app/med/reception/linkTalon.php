<?php
 require_once(__DIR__ . '/../../init.php'); class med_reception_linkTalon { const TASK_NAME = 'medReceptionLinkTalon'; const DEBUG_HTTP = true; private $db; private $extraSettings; private $authorization; public function startExchange() { $items = $this->getBrockerData(); if ($items) { $sended = $this->sendBrockerData($items); if ($sended) { $this->deleteResult($sended); return true; } } } private function getSettings() { if (!empty($this->extraSettings)) { return $this->extraSettings; } $this->log('Загрузка настроек...'); $settings = readExtraSettings(getSessionValue('config'), self::TASK_NAME); return $this->checkSettings($settings); } private function checkSettings($settings) { $settingsValid = true; if (empty($settings)) { $this->log('Не произведена настройка обмена информацией с talon.by', 2); } elseif (isset($settings->active) && $settings->active) { if (empty($settings->url)) { $this->log('Не заполнена настройка Адрес сервера API', 2); $settingsValid = false; } if (empty($settings->clientId)) { $this->log('Не заполнена настройка Идентификатор клиента', 2); $settingsValid = false; } if (empty($settings->clientPassword)) { $this->log('Не заполнена настройка Пароль клиента', 1); } if ($settingsValid) { $settings->task = self::TASK_NAME; $this->log('Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } else { throw new Exception('Проверьте правильность заполнения данных для подключения.'); } } else { $this->log('В настройках запрещен обмен данными', 1); throw new Exception('В настройках запрещен обмен данными'); } return $result; } private function getAuthorization($forceAuthorization = false) { if (!empty($this->authorization) && !$forceAuthorization) { return $this->authorization; } $settings = $this->getSettings(); if (empty($settings)) { return null; } $this->log("Авторизация пользователя $settings->clientId"); $params = new stdClass(); $params->url = $settings->url; $params->proxy = $this->getProxySettings($settings); $params->headers['Authorization'] = "Bearer " . base64_encode("$settings->clientId:$settings->clientPassword"); $params->headers['Content-Type'] = "application/json"; $params->request_content = json_encode( array( 'username' => $settings->clientId, 'password' => $settings->clientPassword), JSON_UNESCAPED_UNICODE); $params->request_method = 'authenticate'; $params->debug = self::DEBUG_HTTP; $result = httpPOST($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $this->authorization = $result->data->token; $this->log("Авторизация пользователя $settings->clientId прошла успешно"); return $this->authorization; case "404": $answer = safe_json_decode(stristr($result['body'], '{')); $this->log("$answer->message"); break; case "401": $answer = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка авторизации $answer->message"); break; default: $this->log("Ошибка авторизации пользователя $settings->clientId", 2); unset($this->authorization); return null; } } private function getHealth() { $settings = $this->getSettings(); if (empty($settings)) { return null; } $params = new stdClass(); $params->url = $settings->url; $params->proxy = $this->getProxySettings($settings); $params->headers['Authorization'] = "Bearer " . $this->authorization; $params->headers['Content-Type'] = "application/json"; $params->request_method = 'check-all'; $params->debug = self::DEBUG_HTTP; $this->log("Проверка состояния сервера ЦОД"); $result = httpGET($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Проверка прошла успешно"); return true; default: $this->log("Ошибка проверки статуса", 2); return false; } } public function getBrockerData() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    TalonBrockerCode "id",
    Action "rule",
    ActionType "service",
    Data "body"
FROM TalonBrocker
ORDER BY ActionOrder, TalonBrockerCode
LIMIT 100
EOT;
$items = selectSql($db, $sql); $total_count = count($items); if ($total_count) { $this->log("Получено $total_count записей, требующих обновления"); $this->log("Начало процесса синхронизации..."); return $items; } else { $this->log("Нет данных для отправки"); return false; } } private function sendBrockerData($items) { $params = $this->setupConnect(); $params->request_method = 'management'; foreach ($items as &$row) { $row['body'] = safe_json_decode($row['body']); } $items = json_encode($items, JSON_UNESCAPED_UNICODE); $params->request_content = $items; $result = httpPOST($params); if ($result) { switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Сихронизация завершена"); break; case "400": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка: $result->reason, $result->message", 2); break; case "404": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка: $result->reason, $result->message", 2); break; default: $this->log("Что-то пошло не так...", 2); break; } return $result->data->processed; } else { $this->log("Ошибка подключения к ЦОД"); return false; } } private function deleteResult($array) { $db = $this->db ?: $this->db = connectDb(); $this->log("Удаление синхронизированных записей..."); foreach ($array as $row) { deleteSql($db, 'TalonBrocker', 'TalonBrockerCode', $row); $this->log("Удаление записи id: $row из брокера"); } return true; } private function setupConnect() { $authorization = $this->getAuthorization(); if (empty($authorization)) { $message = "Неудачная попытка авторизации"; $this->log($message, 2); throw new Exception('Проблема с авторизацией'); } $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключюения"; $this->log($message, 1); throw new Exception($message); } $params = new stdClass(); $params->url = $settings->url; $params->proxy = $this->getProxySettings($settings); $params->headers['Authorization'] = "Bearer " . $this->authorization; $params->headers['Content-Type'] = "application/json"; $params->debug = self::DEBUG_HTTP; return $params; } public function getCatalog() { $params = $this->setupConnect(); $params->request_method = 'catalog/get'; $this->log("Попытка получения каталога"); $result = httpGET($params); switch ($result['status'][1]) { case "200": $answer = safe_json_decode(stristr($result['body'], '{')); $this->log("Каталог получен успешно"); $this->log("Попытка записи каталога в БД"); $created = $this->updateCatalog($answer->data); if ($created === true) { $this->log("Запись каталога прошла успешно"); return true; } break; case "403": $answer = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка получения каталога $answer->message"); return false; case "401": $this->log("Проблема с авторизацией"); throw new Exception('Проблема с авторизацией'); default: $this->log("Что-то пошло не так"); } } private function updateCatalog($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO TalonService
    (TalonServiceCode, Code, Name, Active, TalonServiceCodeParent)
VALUES (?,?,?,?,?)
ON CONFLICT (TalonServiceCode) DO UPDATE SET
    Code = case when ? = 1 then EXCLUDED.Code else TalonService.Code end,
    Name = case when ? = 1 then EXCLUDED.Name else TalonService.Name end,
    Active = case when ? = 1 then EXCLUDED.Active else TalonService.Active end,
    TalonServiceCodeParent = case when ? = 1 then EXCLUDED.TalonServiceCodeParent else TalonService.TalonServiceCodeParent end
WHERE TalonService.TalonServiceCode = EXCLUDED.TalonServiceCode
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'active'), isUndefinedProperty($row, 'parent'), propertyExistsSQL($row, 'cipher'), propertyExistsSQL($row, 'name'), propertyExistsSQL($row, 'active'), propertyExistsSQL($row, 'parent') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("ошибка $e"); throw $e; } return true; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } private function getProxySettings($settings) { return $settings->proxyUse ? array( 'host' => isEmptyProperty($settings, 'proxyServer', ''), 'port' => isEmptyProperty($settings, 'proxyPort', 0), 'usr' => isEmptyProperty($settings, 'proxyUser', ''), 'pwd' => isEmptyProperty($settings, 'proxyPassword', '') ) : array(); } public function startFullSync() { $db = $this->db ?: $this->db = connectDb(); $sql1 = <<<"EOT"
SELECT
    MP.MedProfessionCode "id",
    MP.Name "name",
    UL.Code "uniSpecialty"
FROM MedProfession MP
    LEFT JOIN UniImportLink UL ON UL.UniImportLinkCode = MP.TalonByCode
EOT;
$sql2 = <<<"EOT"
SELECT
    TalonServicePriceCode "id",
    TalonServiceCode "medservice",
    MedProfessionCode "specialty",
    MedCouponWorkCode "couponwork",
    Price "price",
    Note "description",
    Active "active"
FROM TalonServicePrice
EOT;
$medSpecialty = selectSql($db, $sql1); $talonPrice = selectSql($db, $sql1); return true; } public function keepConection() { $params = $this->setupConnect(); $params->request_method = 'catalog/get'; $i = 1; while ($i > 0) { $this->checkCustomTalons($params); } } private function checkCustomTalons($params) { $result = httpGET($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Успех, ща пойдём на перезапуск"); break; case "400": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка: $result->reason, $result->message", 2); break; case "404": $result = safe_json_decode(stristr($result['body'], '{')); $this->log("Ошибка: $result->reason, $result->message", 2); break; default: $this->log("Что-то пошло не так...", 2); break; } } public function exchangeVisitedCoupons() { $items = $this->checkVisitedCoupons(); if ($this->couponsToBrocker($items)) { $this->deleteVisitedCoupons($items); return true; } } private function checkVisitedCoupons() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    visitedCouponsCode "id"
FROM visitedCoupons
EOT;
return selectSql($db, $sql); } private function couponsToBrocker($items) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT INTO TalonBrocker (Action, ActionType, ActionOrder, Data)
    VALUES ('CREATE','VisitedCoupon', 80, ?)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($items as $row) { $stmt->execute([ json_encode($row, JSON_UNESCAPED_UNICODE) ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log($e); } return true; } private function deleteVisitedCoupons($items) { $db = $this->db ?: $this->db = connectDb(); foreach ($items as $row) { try { deleteSql($db, 'visitedCoupons', 'visitedCouponsCode', $row['id']); } catch (Throwable $e) { $this->log($e); } } return true; } public function confirmUser($patientObj) { $params = $this->setupConnect(); $params->request_method = 'user/confirm'; $content = new stdClass; $content->cabinetCode = $patientObj->userCode; $params->request_content = json_encode( $content, JSON_UNESCAPED_UNICODE); $result = httpPOST($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); $res = new stdClass; $res->accountNumber = $result->data->username; $res->phoneNumber = $result->data->cabinet_phone; return $res; case "400": $result = safe_json_decode(stristr($result['body'], '{')); break; case "404": $result = "Данный пациент не найден."; return $result; default: $this->log("Что-то пошло не так...", 2); break; } } public function cabinetUser($record) { $params = $this->setupConnect(); $params->request_method = 'user/activate'; $content = new stdClass; $content->cabinetCode = $record[0]->cipher; $content->cabinetUser = $record[0]->patientId; $params->request_content = json_encode( $content, JSON_UNESCAPED_UNICODE); $result = httpPOST($params); switch ($result['status'][1]) { case "200": $result = safe_json_decode(stristr($result['body'], '{')); return true; case "400": $result = safe_json_decode(stristr($result['body'], '{')); break; case "404": $result = "Данный пациент не найден."; break; default: $this->log("Что-то пошло не так...", 2); break; } } public function ambAuth($params) { $privateKey = file_get_contents(__DIR__ . '/../../../../../crt/private.pem'); suspendJsonRpc(); require_once (__DIR__ . '/../../../lib/php-jwt/JWT.php'); resumeJsonRpc(); $amb = $this->sendAmbCard($params); $JWTService = new Firebase\JWT\JWT(); $jwt = $JWTService->encode($amb, $privateKey, 'RS256'); echo $jwt; } public function sendAmbCard($params) { if (isset($params)) { suspendJsonRpc(); require_once (__DIR__ . '/../card/cards.php'); resumeJsonRpc(); $patientId = is_object($params) ? isUndefinedProperty($params, 'patientId', 0) : $params['patientId']; $info = new med_card_cards(); $patient = new stdClass(); $patient->id = (int) $patientId; $patientInfo = new stdClass(); $patientInfo->patientFIO = $info->readCardData($patient); $parameters = new stdClass(); $parameters->patientId = $patientId; $parameters->start = 0; $parameters->page = 1; $parameters->limit = 10000; $parameters->groupTypes = new stdClass(); $parameters->groupTypes->{1} = true; $parameters->groupTypes->{2} = true; $parameters->groupTypes->{3} = true; $parameters->groupTypes->{4} = true; $parameters->groupTypes->{5} = true; $parameters->groupTypes->{6} = true; $parameters->groupTypes->{7} = true; $parameters->groupTypes->{8} = true; $parameters->groupTypes->{9} = true; $parameters->groupTypes->{10} = true; $parameters->groupTypes->{13} = true; $parameters->groupTypes->{17} = true; $parameters->groupTypes->{18} = true; $parameters->groupTypes->{21} = true; $parameters->groupTypes->{23} = true; $patientInfo->patientDocs = $info->readCardDocuments($parameters); $json = json_encode($this->getObj($patientInfo), JSON_UNESCAPED_UNICODE); header('Content-Type: application/json'); return $json; } } private function getObj($params) { suspendJsonRpc(); require_once (__DIR__ . '/../../map/docs/editor.php'); resumeJsonRpc(); $visits = array(); $sickList = array(); $operations = array(); $prescription = array(); $vaccination = array(); $labTest = array(); $radTest = array(); $funcTest = array(); $surveys = array(); $ambulance = array(); foreach ($params->patientDocs->items as $row) { switch ($row['recordType']) { case "1": array_push($visits, $row); break; case "3": array_push($sickList, $row); break; case "4": array_push($labTest, $row); break; case "5": array_push($radTest, $row); break; case "6": array_push($funcTest, $row); break; case "8": array_push($prescription, $row); break; case "9": array_push($vaccination, $row); break; case "12": array_push($surveys, $row); break; case "17": array_push($operations, $row); break; case "21": array_push($ambulance, $row); break; } } $result = new stdClass(); $result->visits = $visits; $result->sickList = $sickList; $result->operations = $operations; $result->prescription = $prescription; $result->vaccination = $vaccination; $result->labTest = $labTest; $result->radTest = $radTest; $result->funcTest = $funcTest; $result->surveys = $surveys; $result->ambulance = $ambulance; $resultObj = new stdClass(); $resultObj->patientFio = $params->patientFIO; $resultObj->items = $result; return $resultObj; } public function freeCouponWithReg($medCouponCode, $medPatientCode) { if (!$medCouponCode) { return 'MedCouponCode = 0'; } $ban = $this->checkBan($medPatientCode); if ($ban === -2) { return 'Слишком много талонов'; } $coupon = $this->checkCoupon($medCouponCode, $medPatientCode); if ($coupon < 0) { return $coupon; } $result = $this->couponUpdate($medCouponCode, $medPatientCode); return $result; } public function freeCouponWithoutReg($medCouponCode = 1) { $medCouponCode = 1; $aData[0] = 'Фамилия'; $aData[1] = 'Имя'; $aData[2] = 'Отчество'; $aData[3] = '1995-01-01'; $aData[4] = 'Нас. Пункт'; $aData[5] = 'Улица'; $aData[6] = 'Дом'; $aData[7] = 'Корпус'; $aData[8] = 'Квартира'; $aData[9] = 'Телефон'; $aData[10] = 'мыло'; $aCount = count($aData); $aCount >= 1 ? $pSurName = $aData[0] : $pSurName = ''; $aCount >= 2 ? $pName = $aData[1] : $pName = ''; $aCount >= 3 ? $pSecondName = $aData[2] : $pSecondName = ''; $aCount >= 4 ? $pDateofBirth = $aData[3] : $pDateofBirth = ''; $aCount >= 5 ? $aPlace = $aData[4] : $aPlace = ''; $aCount >= 6 ? $aStreet = $aData[5] : $aStreet = ''; $aCount >= 7 ? $aHouse = $aData[6] : $aHouse = ''; $aCount >= 8 ? $aCorp = $aData[7] : $aCorp = ''; $aCount >= 9 ? $aFlat = $aData[8] : $aFlat = ''; $aCount >= 10 ? $pPhone = $aData[9] : $pPhone = ''; $aCount >= 11 ? $pEmail = $aData[10] : $pEmail = ''; $pFIO = trim($pSurName).' ' .trim($pName).' ' .trim($pSecondName).' ' .trim($pDateofBirth).' ' .trim($aPlace).' ' .trim($aStreet).' ' .trim($aHouse).'/' .trim($aCorp).'-' .trim($aFlat); $patientArr1 = $this->getPatient(array('Прокопенко', 'Д', 'И'), array('Бельского', '22', '', '22', 'Минск'), '1986-11-11'); $patientArr2 = $this->getPatient(array('Прокопенко', 'Д', 'И'), array('Бельского', '22', '', '22', 'Минск'), '1986-11-11'); return true; } private function checkStrangerBan($pFIO) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
  (SELECT Count(*)
  FROM MedCoupon
    WHERE (
      COALESCE(FIO, E'' ) = COALESCE($pFIO, E'' ))
    )
    AND SelfRecording=1
  AND Date(OnDate)<Date((LOCALTIMESTAMP))
  )AND CouponState=1
  ) as NotUsedCount,
  (SELECT Count(*)
   FROM MedCoupon
         WHERE
            (COALESCE(FIO, E'' ) = COALESCE($pFIO, E'' ))
            AND SelfRecording=1
     AND Date(OnDate)>=(Date((LOCALTIMESTAMP))
     )AND CouponState IN (1,2)     ) as ActiveCount;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(); } private function checkBan($medPatientCode) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT COUNT(*)  as ActiveCount
FROM MedCoupon
WHERE MedPatientCode = $medPatientCode
    AND SelfRecording = 1
    AND OnDate >= (today())
    AND CouponState IN (1,2)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(); if ($items[0]['activecount'] > 5) { return -2; } else { return true; } } private function checkCoupon($medCouponCode, $medPatientCode) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    CASE WHEN MCC.medCouponControlCode IS NULL THEN 0 ELSE 1 END as SetupIsPresent,
    CASE MC.OnDate = DATE( (LOCALTIMESTAMP)) WHEN true THEN 1 WHEN false THEN 0 END as Today,
    COALESCE( MCC.SelfRecordingPercent, 0.0 ) as QPercent,
    COALESCE( MCC.WithoutControl, 0 ) as WithoutControl,
    (CASE (EXISTS(  SELECT 1 FROM TalonServicePrice AS TSP JOIN TalonService AS TS ON TS.TalonServiceCode = TSP.TalonServiceCode
                    WHERE COALESCE(TSP.Active, 0) = 1 AND COALESCE(TS.Active, 0) = 1 AND TSP.MedProfessionCode = MD.MedProfessionCode
                        AND TSP.MedCouponWorkCode = COALESCE(MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode )))
        WHEN true THEN 1 END ) as pFlag,
    COUNT(CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2)
        WHEN true THEN MC.medCouponCode END ) as TotalCount,
    COUNT( CASE MC.medPatientCode = $medPatientCode AND
        COALESCE( MC.CouponState, 0 ) IN (1,2)
        WHEN true THEN MC.medCouponCode END ) as PatientCount,
    COUNT( CASE MC.medPatientCode IS NOT NULL AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0
          WHEN true THEN MC.medCouponCode END ) as OrderedCount,
    COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0)
          WHEN true THEN MC.medCouponCode END ) as FreeCount,
    ROUND( (COUNT( CASE COALESCE( MC.CouponState, 0 ) IN (0,1,2)
          WHEN true THEN MC.medCouponCode END ) ) * (COALESCE( MCC.SelfRecordingPercent, 0.0 ) ) * 0.01, 0 ) - (COUNT( CASE MC.medPatientCode IS NOT NULL AND
           COALESCE( MC.CouponState, 0 ) IN (1,2) AND
           COALESCE( MC.CouponType, 0 ) = 0
          WHEN true THEN MC.medCouponCode END ) ) as RestCount

FROM medCoupon AS MC2 JOIN medDoctor AS MD ON MD.medDoctorCode = MC2.medDoctorCode
 LEFT OUTER JOIN medCouponControl AS MCC ON MCC.medProfessionCode = MD.medProfessionCode
  AND MCC.FlagSelfRecording = 1
  AND MCC.medCouponWorkCode = COALESCE( MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode )

 LEFT OUTER JOIN medCoupon AS MC ON MC.medDoctorCode = MC2.medDoctorCode
  AND MC.OnDate = MC2.OnDate
  AND COALESCE( MC.medCouponWorkCodeChange, MC.medCouponWorkCode ) = MCC.medCouponWorkCode

WHERE MC2.medCouponCode = $medCouponCode
  AND (COALESCE( MC2.CouponState, 0 ) = 0
      OR  (COALESCE( MC2.medPatientCode, 0 ) = $medPatientCode
       AND COALESCE( MC2.CouponState, 0) <> (0)))

GROUP BY 1, 2, 3, 4, MD.MedProfessionCode, MC2.medCouponWorkCodeChange, MC2.medCouponWorkCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(); if ($items) { if ($items[0]['today'] === 1) { return -2; } elseif ($items[0]['totalcount'] === 0) { return -4; } elseif ($items[0]['patientcount'] > 0) { return -1; } elseif ($items[0]['setupispresent'] === 0) { return -2; } elseif ($items[0]['restcount'] === 0) { return -3; } elseif ($items[0]['pflag'] === 1) { return -10; } else { return true; } } } private function couponUpdate($medCouponCode, $medPatientCode, $SelfRecording = 1, $couponState = 1, $couponType = 0, $SetShortNumber = 0) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCoupon AS _t0_ SET
    medpatientcode = $medPatientCode,
    selfrecording = $SelfRecording,
    couponstate = $couponState,
    ordertime = DATE( (LOCALTIMESTAMP)),
    coupontype = $couponType,
    shortnumber = CASE $SetShortNumber = 1 WHEN true THEN $SetShortNumber WHEN false THEN MC.ShortNumber END
FROM MedCoupon AS MC LEFT OUTER JOIN MedDoctor AS MD ON MC.MedDoctorCode = MD.MedDoctorCode
    LEFT OUTER JOIN MedCouponControl AS MCC ON (MD.MedprofessionCode = MCC.MedProfessionCode AND MCC.MedCouponWorkCode = MC.MedCouponWorkCode)
WHERE (_t0_.medcouponcode=MC.medcouponcode)
    AND ( MC.MedCouponCode = $medCouponCode)

EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getPatient($AFIO, $AAddress, $ADateOfBirth) { $db = $this->db ?: $this->db = connectDb(); $vowels = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я']; $surname = trim(str_replace($vowels, '_', mb_strtoupper($AFIO[0], 'UTF-8'))) ?: ''; $firstName = trim(str_replace($vowels, '_', mb_strtoupper($AFIO[1], 'UTF-8'))) ?: ''; $secondName = trim(str_replace($vowels, '_', mb_strtoupper($AFIO[2], 'UTF-8'))) ?: ''; $street = trim(str_replace($vowels, '_', mb_strtoupper($AAddress[0], 'UTF-8'))) ?: ''; $house = trim(str_replace($vowels, '_', mb_strtoupper($AAddress[1], 'UTF-8'))) ?: ''; $corp = trim(str_replace($vowels, '_', mb_strtoupper($AAddress[2], 'UTF-8'))) ?: ''; $flat = trim(str_replace($vowels, '_', mb_strtoupper($AAddress[3], 'UTF-8'))) ?: ''; $place = trim(str_replace($vowels, '_', mb_strtoupper($AAddress[4], 'UTF-8'))) ?: ''; $dateOfBirth = trim($ADateOfBirth); if (!$surname || !$street) { return -2; } $sql = <<<"EOT"
SELECT MP.MedPatientCode as Code, FN_cmFIO(MP.ManCode) as Name
FROM MedPatient AS MP
    LEFT JOIN Man ON MP.ManCode = Man.ManCode
    LEFT JOIN ManPassport AS Pass ON MP.ManCode = Pass.ManCode
    LEFT JOIN Address AS Addr ON Pass.AddressCode = Addr.AddressCode
    LEFT JOIN Address AS AddrR ON Pass.AddressResideCode = AddrR.AddressCode
WHERE MP.FinishDate > (LOCALTIMESTAMP)
    AND Year(Pass.DateOfBirth) = Year(Date( E'$dateOfBirth' ))
    AND (Man.Surname ilike  E'$surname%' OR  E'$surname%' = E'' )
    AND (Man.Name ilike  E'$firstName%' OR  E'$firstName%' = E'' )
    AND (Man.SecondName ilike  E'$secondName%' OR  E'$secondName%' = E'' )
    AND (COALESCE(NULLIF(AddrR.Place, E'' ),NULLIF(Addr.Place, E'' ),  E'' ) ilike  E'$place%' OR  E'$place%' = E'' )
    AND (COALESCE(NULLIF(AddrR.Street, E'' ),NULLIF(Addr.Street, E'' ),  E'' ) ilike  E'%$street%' OR  E'%$street%' = E'' )
    AND (COALESCE(NULLIF(AddressResideHouse, E'' ),NULLIF(AddressHouse, E'' ),  E'' ) ilike  E'$house' OR  E'$house' = E'' )
    AND (COALESCE(NULLIF(AddressResideCorps, E'' ),NULLIF(AddressCorps, E'' ),  E'' ) ilike  E'$corp%' OR  E'$corp%' = E'' )
    AND (COALESCE(NULLIF(AddressResideFlat, E'' ),NULLIF(AddressFlat, E'' ),  E'' ) ilike  E'$flat' OR  E'$flat' = E'' );
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $patient = $stmt->fetchAll(); $patientCount = count($patient); if ($patientCount === 0) { return NULL; } else if ($patientCount > 1) { return -1; } else { return $patient[0]; } } } if ($_GET && isset($_GET['patientId'])) { $service = new med_reception_linkTalon(); $sessionsValues = readJsonSettings('settings_app', false); setSessionValue('mapEnterpriseCode', $sessionsValues->talonEnterpriseCode); $service->ambAuth($_GET); } else { handleJsonRpc('med_reception_linkTalon'); } 