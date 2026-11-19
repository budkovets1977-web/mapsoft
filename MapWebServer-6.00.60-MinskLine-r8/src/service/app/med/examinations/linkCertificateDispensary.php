<?php
 require_once(__DIR__ . '/../../init.php'); class med_examinations_linkCertificateDispensary { const TASK_NAME = 'medExaminationsLinkCertificateDispensary'; const URL_ADD = '/service/med/examinations/linkCertificateDispensary.php'; const ERROR_SEND_REQUEST_UNKNOWN = -1; const ERROR_SEND_RESPONSE_UNKNOWN = -2; const ERROR_SEND_UNKNOWN_TEXT = "Неизвестная ошибка при передаче"; private $db; private $extraSettings; private $arrErr = array(1 => "не удалось идентифицировать поликлинику по коду ОКПО", 2 => "не удалось записать ответ в БД."); private $results = []; private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } private function getSettings($cfg = null) { $config = ($cfg ? $cfg : getSessionValue('config')); $extra = $this->extraSettings = readExtraSettings($config, self::TASK_NAME); if (!$cfg) { $this->log("[0] Чтение параметров..."); $this->log(print_r($extra, true)); } if (empty($extra)) { $this->results[0] = 'Ошибка чтения параметров [отсутствуют параметры]'; $this->log((!$cfg ? "[0]" : "") . "Чтение параметров... Ошибка [отсутствуют параметры]", 2); return false; } elseif (empty($extra->active)) { $this->results[0] = 'Ошибка чтения параметров [обмен данными запрещен]'; $this->log((!$cfg ? "[0]" : "") . "Чтение параметров... Ошибка [обмен данными запрещен]", 1); return false; } if (!$cfg) { $this->log("[0] Чтение параметров... ОК"); } return true; } private function readConfigSettings() { $settings = readJsonSettings('settings_med'); if ($settings && isset($settings['configLinkCertificateDispensary'])) { return $settings['configLinkCertificateDispensary']; } else { return null; } } private function connectDataBase() { $сonfig = $this->readConfigSettings(); if (!$сonfig) { $this->log("Отсутствует настройка конфигурации сервиса!", 1); throw new Exception("Отсутствует настройка конфигурации сервиса!"); } $this->db = $this->db ? : connectDb($сonfig); $this->extraSettings ? : $this->getSettings($сonfig); return $this->db; } private function getCertificateData() { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = $this->extraSettings->organizationId; $ret = []; $sql = <<<"EOT"
    SELECT {$db->sybase("TOP 100")}
        MC.Code "docNumber",
        MC.MedCertificateCode "medCertificateCode",
        MC.Attributes "attributes",
        MC.UniDictCodeMark "purposeId",
        NULLIF(MC.json, '')::jsonb ->> 'targetId' AS "targetId",
        CASE WHEN (MC.Attributes & 1) = 1 THEN true ELSE false END AS "paid",
        MC.EnterpriseCode "enterpriseCode",
        MC.MedPatientCode "medPatientCode",
        CASE WHEN MC.medPatientCode IS NOT NULL THEN FN_cmFIO(MP.ManCode) ELSE MC.fio END "patienFio",
        CASE WHEN MC.medPatientCode IS NOT NULL THEN P.dateofBirth ELSE MC.birthday END "patientDateOfBirth",
        CASE WHEN MC.medPatientCode IS NOT NULL THEN FN_cmCalcAddress(MP.ManCode,1,0,1) ELSE MC.Address END "patientAddressFact",
        FN_cmCalcAddress(MP.ManCode,0,0,1) "patientAddress",
        CASE WHEN MC.medPatientCode IS NOT NULL THEN P.PassportNumber ELSE MC.passport END "patientPassportNumber",
        P.PersonalNumber "patientPersonalNumber",
        MC.certificateType "certificateType",
        MC.note "note"
    FROM MedCertificate AS MC
    JOIN EnterpriseNumber EN ON EN.enterpriseCode = MC.enterpriseCode
    LEFT JOIN MedPatient AS MP ON MP.MedPatientCode = MC.MedPatientCode
    LEFT JOIN ManPassport AS P ON MP.ManCode = P.ManCode
    WHERE
        MC.EnterpriseCode = ? AND COALESCE(MC.CertificateType,0) = 9 AND MC.Requesttime IS NULL
    ORDER BY MC.CertificateType
    {$db->postgre("LIMIT 100")}
    EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseId]); foreach ($stmt->fetchAll() as $row) { $rec = (object) $row; $data = new stdClass(); $data->DocNumber = $rec->docNumber; $data->MedCertificateCode = $rec->medCertificateCode; $data->Attributes = $rec->attributes; $data->SendTime = date('Y-m-d H:i:s'); $data->RequestTime = null; $data->DepartureTime = null; $data->ResponseTime = null; $data->Target = $rec->targetId; $data->Paid = $rec->paid; $data->Auto = null; $data->EnterpriseCode = $rec->enterpriseCode; $data->MedPatientCode = $rec->medPatientCode; $data->Note = $rec->note; $data->RawResult = null; $patient = [ "FIO" => $rec->patienFio ?? null, "DateOfBirth" => $rec->patientDateOfBirth ? (new DateTime($rec->patientDateOfBirth))->format('d.m.Y') : null, "Address" => $rec->patientAddress ?? null, "AddressFact" => $rec->patientAddressFact ?? null, "PassportNumber" => $rec->patientPassportNumber ?? null, "PersonalNumber" => $rec->patientPersonalNumber ?? null ]; $data->Patient = json_encode($patient, JSON_UNESCAPED_UNICODE); $organizations = [ "src" => [ "Guid" => $this->extraSettings->guid, "EnterpriseCode" => $this->extraSettings->organizationId, "EnterpriseName" => $this->extraSettings->organizationName ], "dest" => [ ] ]; $data->Organizations = json_encode($organizations, JSON_UNESCAPED_UNICODE); $ret[] = $data; } return $ret; } public function ping($params = null) { $this->db ? : $this->db = $this->connectDataBase(); return (empty($this->extraSettings->active) === false); } private function getCertificateDispensary($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); $enterpriseId = $this->extraSettings->organizationId; if (!$record) { return null; } $sql = <<<"EOT"
SELECT
    medCertificateDispensaryCode "id"
FROM MedCertificateDispensary
WHERE
    enterpriseCode = ? AND
    COALESCE(codeokpo,'') = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseId, $record->codeOKPO]); $row = $stmt->fetch(); return $row ? $row['id'] : null; } private function getWordsFromString($string) { if (preg_match_all("/\b(\w+\-{0,}\w{0,})\b/ui", $string, $matches)) { return $matches[1]; } return array(); } private function getNumbersFromString($string) { $result = array(); $str = preg_replace('/\.|\/|-| дом| д| квартира| кв| корпус| корп| к| п| г/ui', ' ', $string); $arr = $this->getWordsFromString($str); if (count($arr) > 0) { $idx = -1; for ($i = count($arr) - 1; $i >= 0; $i--) { if (!preg_match("/\b(\d+([a-zA-Zа-яА-Я])?|[a-zA-Zа-ик-юА-ИК-Ю]{1})\b/ui", $arr[$i])) { $idx = $i; break; } } $result = array_slice($arr, $idx + 1); } return $result; } private function getPatient($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); $enterpriseId = $this->extraSettings->organizationId; if (!$record) { return null; } $fio = $this->getWordsFromString($record->fio); $f = count($fio) >= 1 ? $fio[0] : ''; $i = count($fio) >= 2 ? $fio[1] : ''; $o = count($fio) >= 3 ? $fio[2] : ''; $numbers = $this->getNumbersFromString($record->address); $cnt = count($numbers); if ($cnt > 0) { $house = $cnt < 3 ? $numbers[0] : $numbers[$cnt - 3]; $corps = $cnt >= 3 ? $numbers[$cnt - 2] : ''; $flat = $cnt <= 3 ? ( $cnt === 2 ? $numbers[1] : ( $cnt === 3 ? $numbers[2] : '')) : $numbers[$cnt - 1]; } else { $house = $corps = $flat = ''; } $sql = <<<"EOT"
SELECT
    mp.medPatientCode "id"
FROM MedPatient AS mp
JOIN Man AS m ON m.manCode = mp.manCode
  AND M.surName LIKE '$f'
  AND M.name LIKE '$i%'
  AND M.secondName LIKE '$o%'
JOIN ManPassport AS pass ON pass.manCode = mp.manCode AND pass.dateOfBirth =  ?
WHERE
    mp.enterpriseCode = ? AND
    (
        ( COALESCE(pass.addressHouse,'') = '$house' AND
          COALESCE(pass.addressCorps,'') = '$corps' AND
          COALESCE(pass.addressFlat,'') = '$flat'
        )
        OR
       (  COALESCE(pass.AddressResideHouse,'') LIKE '$house' AND
          COALESCE(pass.AddressResideCorps,'') LIKE '$corps' AND
          COALESCE(pass.AddressResideFlat,'') LIKE '$flat'
        )
    )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$record->dateOfBirth, $enterpriseId]); $row = $stmt->fetchAll(); return ($row && count($row) == 1) ? $row[0]['id'] : null; } private function getNextCode($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); $enterpriseId = $this->extraSettings->organizationId; if (!$record) { return null; } $sql = <<<"EOT"
SELECT
{$db->dialect("COALESCE(NULLIF(TRIM(IncStr(MAX( REPEAT(' ', 20 - LENGTH(TRIM(Code))) || TRIM(Code)))),''),'1')", "COALESCE(NULLIF(asa_trim(IncStr(MAX( REPEAT( E' ' , 20 - LENGTH(asa_trim(Code))) || asa_trim(Code)))), E'' ), E'1' )")} "nextCode"
FROM MedCertificate
WHERE CertificateType = 10
  AND EnterpriseCode = ?
  AND IssueDate >= {$db->dialect("YMD(DATEPART(YEAR, ?),1,1)", "(YMD((to_char((?)::timestamp,'FMYYYY')::integer),1,1))")}
  AND IssueDate <= {$db->dialect("DATEADD(DAY,-1,YMD(DATEPART(YEAR, ?)+1,1,1))", "(DATEADD('DAY',-1,YMD((to_char((?)::timestamp,'FMYYYY')::integer)+1,1,1)))")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseId, $record->issueDate, $record->issueDate]); $row = $stmt->fetch(); if ($row) { return $row['nextCode']; } else { return '1'; } } private function isExistsRequestCertificate($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); $enterpriseId = $this->extraSettings->organizationId; $sql = <<<"EOT"
SELECT
    1
FROM MedCertificate
WHERE
    COALESCE(certificateType,0) = 10 AND
    enterpriseCode = ? AND
    COALESCE(medCertificateDispensaryCode,0) = ? AND
    COALESCE(linkpi,0) = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseId, $record->certificateDispensaryId, $record->id]); return ($stmt->fetch()); } private function addRequestCertificate($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); $enterpriseId = $this->extraSettings->organizationId; if (!$record) { return false; } if (!$this->isExistsRequestCertificate($record)) { $record->code = $this->getNextCode($record); $sql = <<<"EOT"
INSERT INTO MedCertificate
    (certificateType, code, issueDate, medCertificateDispensaryCode, enterpriseCode, medPatientCode, fio, birthday, address, purpose, linkpi, requesttime)
VALUES
    (10, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $record->code, $record->issueDate, $record->certificateDispensaryId, $enterpriseId, $record->patientId, $record->fio, $record->dateOfBirth, $record->address, $record->purpose, $record->id ]); } else { $this->log("Запрос уже существует, пропущен. (MedCertificateDispensaryCode = " . (string) $record->certificateDispensaryId . ", LinkPI = " . (string) $record->id . ")", 1); } return true; } public function updateCertificateRequestTime($medCertificateDataForExternalServer) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    UPDATE MedCertificate SET
        requesttime = case when ? = 1 then ? else requesttime end
    WHERE
        MedCertificateCode = ?
    EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ true, $medCertificateDataForExternalServer->SendTime, $medCertificateDataForExternalServer->MedCertificateCode ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateCertificateByExternalServerResponse( $medcertificatecode, $requestresult, $respondtime, $conclusion, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId ) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
        UPDATE MedCertificate SET
            RequestResult = CASE WHEN ? = 1 THEN ? ELSE RequestResult END,
            RespondTime = CASE WHEN ? = 1 THEN ? ELSE RespondTime END,
            Conclusion = CASE WHEN ? = 1 THEN ? ELSE Conclusion END,
            ConclusionTime = CASE WHEN ? = 1 THEN ? ELSE ConclusionTime END,
            Attributes = CASE 
                WHEN ? = 1 THEN (Attributes | 2)  -- установить бит isAuto
                ELSE Attributes
            END,
            json = (
                CASE 
                    WHEN NULLIF(json, '') IS NULL THEN
                        jsonb_set(
                            jsonb_set('{}'::jsonb, '{dispensaryName}', to_jsonb(?::text), true),
                            '{dispensaryId}', to_jsonb(?::int), true
                        )::text
                    ELSE
                        jsonb_set(
                            jsonb_set(json::jsonb, '{dispensaryName}', to_jsonb(?::text), true),
                            '{dispensaryId}', to_jsonb(?::int), true
                        )::text
                END
            )
        WHERE MedCertificateCode = ?
        EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ true, $requestresult, true, $respondtime, true, $conclusion, true, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId, $dispensaryName, $dispensaryId, $medcertificatecode ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function processRequestData($data = null) { if (!$data) { return null; } $cnt = count($data); $cntAdd = 0; $result = new \stdClass(); $result->successList = []; $result->failureList = []; $this->log("Добавление запросов в БД. Количество записей для добавления: $cnt"); foreach ($data as $row) { $row->certificateDispensaryId = $this->getCertificateDispensary($row); if (!$row->certificateDispensaryId) { $err = new \stdClass(); $err->id = $row->id; $err->errorCode = 1; $result->failureList[] = $err; $this->log("Ошибка: " . $this->arrErr[1] . " : " . $row->codeOKPO, 2); continue; } $row->patientId = $this->getPatient($row); if ($this->addRequestCertificate($row)) { $cntAdd++; $result->successList[] = $row->id; } } $this->log("Добавление запросов в БД. Добавленено: $cntAdd"); return $result; } private function updateResponseCertificate($record = null) { $db = $this->db ? : $this->db = $this->connectDataBase(); if (!$record) { return false; } $sql = <<<"EOT"
{$db->postgre("WITH upd AS (")}
UPDATE MedCertificate SET
    requestResult = ?,
    conclusion = ?,
    respondTime = NOW()
WHERE MedCertificateCode = ?
{$db->postgre("returning *)")}
{$db->postgre("SELECT COUNT(*) \"cnt\" from upd")}
EOT;
$db->beginTransaction(); $cnt = 0; try { $stmt = $db->prepare($sql); $stmt->execute([$record->requestResult, $record->conclusion, $record->linkPI]); if ($db->isSybase) { $check = $db->prepare("SELECT @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = fetchValue($stmt, 'cnt'); } $db->commit(); } catch (Exception $e) { $db->rollback(); $this->log("Обновление запроса в БД... Ошибка", 2); return false; } if ($cnt <= 0) { $this->log("Не удалось записать ответ в БД. Key: " . (string) $record->linkPI, 2); } return ($cnt > 0); } public function processResponseData($data = null) { if (!$data) { return null; } $result = new \stdClass(); $result->successList = []; $result->failureList = []; $cnt = count($data); $cntRes = 0; $this->log("Запись ответов в БД. Количество записей для обновления: $cnt"); foreach ($data as $row) { if ($this->updateResponseCertificate($row)) { $result->successList[] = $row->id; $cntRes++; } else { $err = new \stdClass(); $err->id = $row->id; $err->errorCode = 2; $result->failureList[] = $err; } } $this->log("Запись ответов в БД. Обновлено: $cntRes"); return $result; } private function sendCertificateData($params = null) { $this->extraSettings ? : $this->getSettings(); if (empty($this->extraSettings)) { return false; } if (empty($this->extraSettings->active)) { return false; } $this->log("[0]Запрос из цода данных для обновления справок"); $config = getSessionValue('config'); $settings = readExtraSettings($config, 'medExaminationsLinkCertificateDispensary'); $params = httpCreateParam($settings); $params->request_method = 'getRequestsByOrganisationId'; $params->param = array( 'guid' => $settings->guid ); $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ от сервера'; throw new Exception('Ошибка при получении обработанных результатов из цод'.$result); } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); $this->results[1] = "Получено результатов: " . count($result ?? []); if (is_array($result)) { foreach ($result as $res) { $responseData = is_string($res->response_data) ? safe_json_decode($res->response_data) : $res->response_data; $requestData = is_string($res->request_data) ? safe_json_decode($res->request_data) : $res->request_data; $organizations = is_string($requestData->Organizations) ? safe_json_decode($requestData->Organizations) : $requestData->Organizations; $dispensaryData = $organizations->dest; $conclusion = null; $respondtime = null; $conclusionTime = null; $dispensaryName = null; $dispensaryId = null; $requestresult = $responseData->Result; $medcertificatecode = $requestData->MedCertificateCode; $isAutoResultFromCod = $requestData->Auto ? 1 : 0; if ($requestresult) { $date = new DateTime(); $now = $date->format('Y-m-d H:i:s'); $conclusionTime = $now; $respondtime = $now; $conclusion = $responseData->Conclusion; $dispensaryName = $dispensaryData->EnterpriseName; $dispensaryId = $dispensaryData->EnterpriseCode; } $this->updateCertificateByExternalServerResponse( $medcertificatecode, $requestresult, $respondtime, $conclusion, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId ); } } break; default: $result = $result ? : $response['body']; throw new Exception('Ошибка при получении обработанных результатов из цод'.$result); } $this->log("[1]Выбор информации из БД..."); $data = $this->getCertificateData(); if (!$data && $data !== array()) { $this->results[0] = "Ошибка при выборе информации из БД!"; $this->log("[1]Выбор информации из БД... Ошибка!", 2); return false; } $cnt = count($data); $this->log("[1]Выбор информации из БД... Количество выбранных записей: $cnt"); $this->results[1] .= "<br>Записей для отправки: $cnt"; if ($cnt > 0) { $cntSend = 0; $cntFail = 0; $this->log("[2] Начало передачи информации..."); if (!$params = httpCreateParam($this->extraSettings)) { $this->log("Ошибка инициализация подключюения", 1); return false; } $params->certificate = true; $params->debug = true; $params->timeout = 120; foreach ($data as $packageDataRow) { $params->request_method = 'addRequest'; $params->param = array( 'guid' => $this->extraSettings->guid ); $params->request_content = json_encode($packageDataRow); $this->log("[3]Передача запросов addRequest. ID medcertificate: $packageDataRow->MedCertificateCode"); $response = httpPOST($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); $this->updateCertificateRequestTime($packageDataRow); $this->log("[6]Передача данных в цод успешна"); $cntSend +=1; break; default: $result = $result ? : $response['body']; $this->log("[7]При отправке запросов в цод произошла ошибка: $result", 2); $cntFail += 1; } } $this->results[1] .= ";<br> Отправлено: $cntSend" . ($cntFail > 0 ? "; <br> Не удалось отправить: $cntFail" : ""); $this->log("[8]Окончание передачи информации. Отправлено записей: $cntSend" . ($cntFail > 0 ? ". Не удалось отправить: $cntFail" : "")); } return true; } public function startExchange($params = null) { $this->sendCertificateData(); return $this->results; } } handleJsonRpc('med_examinations_linkCertificateDispensary'); 