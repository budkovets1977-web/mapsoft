<?php
require_once(__DIR__.'/../../init.php'); class med_lab_linkOncology { const TASK_NAME = 'medLabLinkOncology'; const DEBUG = false; const RISK_PROGRAM = 21; const RISK_DATATYPE = 1; private $db; private $extraSettings; private $results = []; private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } private function getSettings() { $config = $this->getConfig(); $settings = $this->extraSettings = readExtraSettings($config, self::TASK_NAME); $this->log("[0] Чтение настроек..."); $this->log(print_r($settings, true)); if (empty($settings)) { $this->results[0] = 'Ошибка чтения настроек [отсутствуют настройки]'; $this->log("    [0] Чтение настроек... Ошибка [отсутствуют настройки]", 2); return false; } elseif (empty($settings->active)) { $this->results[0] = 'Ошибка чтения настроек [Обмен данными запрещен]'; $this->log("    [0] Чтение настроек... Ошибка [Обмен данными запрещен]", 1); return false; } elseif (empty($settings->url)) { $this->results[0] = 'Ошибка чтения настроек [Не указан URL адрес]'; $this->log("    [0] Чтение настроек... Ошибка [Не указан URL адрес]", 2); return false; } $this->log("    [0] Чтение настроек... ОК"); return true; } private function getConfig() { header('Access-Control-Allow-Origin: *'); $settings = readJsonSettings('settings_med'); if ($settings && isset($settings['defaultConfig'])) { $config = $settings['defaultConfig']; } else { $msg = 'Отсутствует настройка конфигурации по умолчанию "defaultConfig" в файле "cfg\settings_med.json"'; http_response_code(500); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); die; } return $config; } public function getCase($params = null) { $this->extraSettings ?: $this->getSettings(); if (empty($this->extraSettings)) { return $this->results; } $settings = $this->extraSettings; $oipiPatientId = isEmptyProperty($params, 'oipiPatientId'); $personalNumber = isEmptyProperty($params, 'personalNumber'); $medCardId = isEmptyProperty($params, 'medCardId'); $lastName = isEmptyProperty($params, 'lastName'); $firstName = isEmptyProperty($params, 'firstName'); $secondName = isEmptyProperty($params, 'secondName'); $gender = isEmptyProperty($params, 'gender'); $birthDate = isEmptyProperty($params, 'birthDate'); $birthDate = $birthDate ? (new DateTime($birthDate))->format("Y-m-d") : null; $medPatientId = isEmptyProperty($params, 'medPatientId'); $addToDb = isNullProperty($params, 'addToDb', true); $oipiParams = []; $this->log("[3] Получение случаев из ЦОДа..."); $this->log("    [3] Инициализация подключения..."); if ($params = httpCreateParam($settings)) { $this->log("    [3] Инициализация подключения... OK"); $params->request_method = "getCase"; $oipiPatientId && $oipiParams['id'] = $oipiPatientId; $personalNumber && $oipiParams['personal_number'] = $personalNumber; $medCardId && $oipiParams['med_card_id'] = $medCardId; $lastName && $oipiParams['last_name'] = $lastName; $firstName && $oipiParams['first_name'] = $firstName; $secondName && $oipiParams['second_name'] = $secondName; $gender && $oipiParams['sex'] = $gender; $birthDate && $oipiParams['birthdate'] = $birthDate; $params->request_content = json_encode($oipiParams, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG; $this->log("    [3] Отправка запроса..."); if (!$ret = httpPOST($params)) { $errorCode = httpError(); $errorText = httpErrorStr(); $errorTxt = "Получение Случаев... Ошибка: [$errorCode] $errorText"; $this->log("    [3] $errorTxt", 2); $this->results[3] = $errorTxt; return $this->results; } switch (isEmptyArrayValue($ret['status'], 1)) { case "200": $this->log("    [3] Ответ от ЦОДа получен..."); if ($addToDb) { $ret['medPatientId'] = $medPatientId; $this->addCaseToDB($ret); } break; default: $serverError = $ret['status'][1]; $serverMessage = $ret['body']; $errorText = 'Получение Случаев... Ошибка:'; $this->log("    [3] $errorText $serverMessage", 2); $this->results[3] = "$errorText [$serverError] $serverMessage"; return $this->results; } return $ret; } $this->log("    [3] Инициализация подключения... Ошибка", 2); $this->results[3] = 'Подключения к ЦОДу... Ошибка'; return $this->results; } private function addCaseToDB($params) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $cases = safe_json_decode($params['body']); $casesCount = count($cases); $medPatientId = $params['medPatientId']; $sql = <<<EOT
            INSERT INTO MedExtDocument (
                Id_Document, Id_Patient, IsType,
                Json, MedPatientCode, StartDate,
                State, FinishDate, Is_Finished,
                Cancer_Revealed, Precancer_Revealed
            )
            VALUES (
                ?, ?, 1,
                ?, ?, ?,
                2, ?, ?,
                ?, ?
            )
            ON CONFLICT (IsType, Id_Document) DO UPDATE SET
                Id_Patient          = CASE WHEN ? = 1 THEN EXCLUDED.Id_Patient          ELSE MedExtDocument.Id_Patient END,
                Json                = CASE WHEN ? = 1 THEN EXCLUDED.Json                ELSE MedExtDocument.Json END,
                StartDate           = CASE WHEN ? = 1 THEN EXCLUDED.StartDate           ELSE MedExtDocument.StartDate END,
                FinishDate          = CASE WHEN ? = 1 THEN EXCLUDED.FinishDate          ELSE MedExtDocument.FinishDate END,
                Is_Finished         = CASE WHEN ? = 1 THEN EXCLUDED.Is_Finished         ELSE MedExtDocument.Is_Finished END,
                Cancer_Revealed     = CASE WHEN ? = 1 THEN EXCLUDED.Cancer_Revealed     ELSE MedExtDocument.Cancer_Revealed END,
                Precancer_Revealed  = CASE WHEN ? = 1 THEN EXCLUDED.Precancer_Revealed  ELSE MedExtDocument.Precancer_Revealed END
            RETURNING MedExtDocumentCode AS id
EOT;
$resultTxt = "Получено случаев: $casesCount"; $this->log("    [3] $resultTxt"); $this->results[3] = $resultTxt; if (!empty($cases)) { $errorTxt = 'Сохранение Случаев в БД МИС'; $this->log("    [3] $errorTxt"); $db->beginTransaction(); try { $stmt = $db->prepare($sql); foreach ($cases as $case) { $caseIsFinished = isUndefinedProperty($case, 'is_finished') ? 1 : NULL; $cancerRevealed = isUndefinedProperty($case, 'cancer_revealed') ? 1 : NULL; $precancerRevealed = isUndefinedProperty($case, 'precancer_revealed') ? 1 : NULL; $caseCreatedAt = isUndefinedProperty($case, 'created_at'); $caseFinishedAt = isUndefinedProperty($case, 'finished_at'); if ($caseCreatedAt) { $caseCreatedAt = new DateTime($caseCreatedAt); $caseCreatedAt->setTimeZone(new DateTimeZone('Europe/Minsk')); } if ($caseFinishedAt) { $caseFinishedAt = new DateTime($caseFinishedAt); $caseFinishedAt->setTimeZone(new DateTimeZone('Europe/Minsk')); } $stmt->execute([ isUndefinedProperty($case, 'id'), isUndefinedProperty($case, 'patient_id'), json_encode($case, JSON_UNESCAPED_UNICODE), $medPatientId, dbDateTime($caseCreatedAt), dbDateTime($caseFinishedAt), $caseIsFinished, $cancerRevealed, $precancerRevealed, propertyExistsSQL($case, 'patient_id'), 1, propertyExistsSQL($case, 'created_at'), propertyExistsSQL($case, 'finished_at'), propertyExistsSQL($case, 'is_finished'), propertyExistsSQL($case, 'cancer_revealed'), propertyExistsSQL($case, 'precancer_revealed') ]); $caseId = fetchValue($stmt, 'id'); $groupRiskIds = isNullProperty($case, 'group_risk_ids'); if ($groupRiskIds) { $riskParams = new stdClass(); $riskParams->groupRiskIds = $groupRiskIds; $riskParams->caseId = $caseId; $this->addCaseExamGroupRiskToDB($riskParams); } $finalIcd10 = isNullProperty($case, 'final_icd10'); $diagnoses = isNullProperty($case, 'diagnosis', []); if ($finalIcd10) { $finalDiagnosis = new stdClass(); $finalDiagnosis->icd_10_code = $finalIcd10; $finalDiagnosis->icd_10_text = NULL; $finalDiagnosis->diagnosis = NULL; $finalDiagnosis->isFinal = true; array_push($diagnoses, $finalDiagnosis); } $diagnosesParams = new stdClass(); $diagnosesParams->diagnoses = $diagnoses; $diagnosesParams->caseId = $caseId; $this->addCaseExamDiagnosisToDB($diagnosesParams); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("    [3] $errorTxt... Ошибка", 2); $this->results[3] = "$errorTxt... Ошибка"; } } } public function getCaseExamList($params = null) { $this->extraSettings ?: $this->getSettings(); if (empty($this->extraSettings)) { return $this->results; } $settings = $this->extraSettings; $caseId = isEmptyProperty($params, 'caseId'); $addToDb = isNullProperty($params, 'addToDb', true); $ret = null; $params = new stdClass(); $params->caseId = $caseId; $oipiCaseId = $this->getOipiCaseId($params); $this->log("[4] Получение Осмотров из ЦОДа..."); if (!$oipiCaseId) { $errorTxt = 'Ошибка: Не удалось определить ID Случая в ОИПИ'; $this->log("    [4] $errorTxt", 2); $this->results[4] = $errorTxt; return $this->results; } $this->log("    [4] Инициализация подключения..."); if ($params = httpCreateParam($settings)) { $this->log("    [4] Инициализация подключения... OK"); if ($caseId) { $params->request_method = "listCaseExam/" . $oipiCaseId; $params->debug = self::DEBUG; $this->log("    [4] Отправка запроса..."); if (!$ret = httpGET($params)) { $errorCode = httpError(); $errorText = httpErrorStr(); $errorTxt = "Получение Осмотров... Ошибка: [$errorCode] $errorText"; $this->log("    [4] $errorTxt", 2); $this->results[4] = $errorTxt; return $this->results; } switch (isEmptyArrayValue($ret['status'], 1)) { case "200": $this->log("    [4] Ответ от ЦОДа получен..."); if ($addToDb) { $ret['caseId'] = $caseId; $this->addExamToDB($ret); } break; default: $serverError = $ret['status'][1]; $serverMessage = $ret['body']; $errorText = 'Получение Осмотров... Ошибка:'; $this->log("    [4] $errorText $serverMessage", 2); $this->results[4] = "$errorText [$serverError] $serverMessage"; return $this->results; } return $ret; } } $this->log("    [4] Инициализация подключения... Ошибка", 2); $this->results[4] = 'Подключения к ЦОДу... Ошибка'; return $this->results; } private function getOipiCaseId($params) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $caseId = isEmptyProperty($params, 'caseId'); if (!$caseId) { return null; } $sql = <<<EOT
            SELECT Id_Document
            FROM MedExtDocument
            WHERE MedExtDocumentCode = ?
EOT;
return selectSqlValue($db, $sql, [ $caseId ]); } private function addExamToDB($params) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $exams = safe_json_decode($params['body']); $examsCount = count($exams); $caseId = $params['caseId']; $currentDateTime = new DateTime(); $sql = <<<EOT
            INSERT INTO MedExtDocumentOperation (
                ChangeTime, DoctorFio, Id_Operation,
                Json, MedExtDocumentCode, OperationTime,
                Result, Spec_Doctor, State,
                Target, Organization_Name, Organization_Short_Name
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, 2,
                ?, ?, ?
            )
            ON CONFLICT (MedExtDocumentCode, Id_Operation) DO UPDATE SET
                ChangeTime                  = CASE WHEN ? = 1 THEN EXCLUDED.ChangeTime                  ELSE MedExtDocumentOperation.ChangeTime END,
                DoctorFio                   = CASE WHEN ? = 1 THEN EXCLUDED.DoctorFio                   ELSE MedExtDocumentOperation.DoctorFio END,
                Id_Operation                = CASE WHEN ? = 1 THEN EXCLUDED.Id_Operation                ELSE MedExtDocumentOperation.Id_Operation END,
                Json                        = CASE WHEN ? = 1 THEN EXCLUDED.Json                        ELSE MedExtDocumentOperation.Json END,
                OperationTime               = CASE WHEN ? = 1 THEN EXCLUDED.OperationTime               ELSE MedExtDocumentOperation.OperationTime END,
                Result                      = CASE WHEN ? = 1 THEN EXCLUDED.Result                      ELSE MedExtDocumentOperation.Result END,
                Spec_Doctor                 = CASE WHEN ? = 1 THEN EXCLUDED.Spec_Doctor                 ELSE MedExtDocumentOperation.Spec_Doctor END,
                Target                      = CASE WHEN ? = 1 THEN EXCLUDED.Target                      ELSE MedExtDocumentOperation.Target END,
                Organization_Name           = CASE WHEN ? = 1 THEN EXCLUDED.Organization_Name           ELSE MedExtDocumentOperation.Organization_Name END,
                Organization_Short_Name     = CASE WHEN ? = 1 THEN EXCLUDED.Organization_Short_Name     ELSE MedExtDocumentOperation.Organization_Short_Name END
            RETURNING MedExtDocumentOperationCode AS id
EOT;
$resultTxt = "Получено Осмотров: $examsCount"; $this->log("    [4] $resultTxt по Случаю с МИС ID $caseId"); $this->results[4] = $resultTxt; if (!empty($exams)) { $errorTxt = 'Сохранение Осмотров в БД МИС'; $this->log("    [4] $errorTxt"); $db->beginTransaction(); try { $stmt = $db->prepare($sql); foreach ($exams as $exam) { $examDateTime = isUndefinedProperty($exam, 'exam_datetime'); if ($examDateTime) { $examDateTime = new DateTime($examDateTime); $examDateTime->setTimeZone(new DateTimeZone('Europe/Minsk')); } $stmt->execute([ dbDateTime($currentDateTime), isUndefinedProperty($exam, 'fio_doctor'), isUndefinedProperty($exam, 'id'), json_encode($exam, JSON_UNESCAPED_UNICODE), $caseId, dbDateTime($examDateTime), isUndefinedProperty($exam, 'result'), isUndefinedProperty($exam, 'spec_doctor'), isUndefinedProperty($exam, 'referral_target'), isUndefinedProperty($exam, 'organization_name'), isUndefinedProperty($exam, 'organization_short_name'), 1, propertyExistsSQL($exam, 'fio_doctor'), propertyExistsSQL($exam, 'id'), 1, propertyExistsSQL($exam, 'exam_datetime'), propertyExistsSQL($exam, 'result'), propertyExistsSQL($exam, 'spec_doctor'), propertyExistsSQL($exam, 'referral_target'), propertyExistsSQL($exam, 'organization_name'), propertyExistsSQL($exam, 'organization_short_name') ]); $examId = fetchValue($stmt, 'id'); $groupRiskIds = isNullProperty($exam, 'group_risk_ids'); if ($groupRiskIds) { $riskParams = new stdClass(); $riskParams->groupRiskIds = $groupRiskIds; $riskParams->examId = $examId; $this->addCaseExamGroupRiskToDB($riskParams); } $diagnoses = isNullProperty($exam, 'diagnosis'); if ($diagnoses) { $diagnosesParams = new stdClass(); $diagnosesParams->diagnoses = $diagnoses; $diagnosesParams->examId = $examId; $this->addCaseExamDiagnosisToDB($diagnosesParams); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("    [4] $errorTxt... Ошибка", 2); $this->results[4] = "$errorTxt... Ошибка"; } } } private function addCaseExamDiagnosisToDB($params) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $diagnoses = isNullProperty($params, 'diagnoses'); $caseId = isNullProperty($params, 'caseId'); $examId = isNullProperty($params, 'examId'); $id = $examId ?: $caseId; $sqlWhereCaseExam = $examId ? 'MedExtDocumentOperationCode = :id' : 'MedExtDocumentCode = :id'; $sqlCaseExam = $examId ? 'MedExtDocumentOperationCode' : 'MedExtDocumentCode'; $sql = <<<EOT
            SELECT COUNT(MedExtDocumentItemCode)
            FROM MedExtDocumentItem
            WHERE $sqlWhereCaseExam
                AND Cipher = :icd10Code
EOT;
$sqlIns = <<<EOT
            INSERT INTO MedExtDocumentItem (
                $sqlCaseExam, Type, Note,
                Cipher, ValueString
            )
            VALUES (
                :id, :diagnosisType, :diagnosis,
                :icd10Code, :icd10Text
            )
EOT;
$stmtIns = $db->prepare($sqlIns); if ($diagnoses) { foreach ($diagnoses as $item) { $icd10Code = isNullProperty($item, 'icd_10_code'); $icd10Text = isNullProperty($item, 'icd_10_text'); $diagnosis = isNullProperty($item, 'diagnosis'); $isFinal = isNullProperty($item, 'isFinal', false); if ($icd10Code) { $diagnosisExists = selectSqlValue($db, $sql, [ ':id' => $id, ':icd10Code' => $icd10Code ]); if (!$diagnosisExists) { $stmtIns->execute([ ':id' => $id, ':diagnosisType' => $isFinal ? 3 : 2, ':diagnosis' => $diagnosis, ':icd10Code' => $icd10Code, ':icd10Text' => $icd10Text ]); } } } } } private function addCaseExamGroupRiskToDB($params) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $groupRiskIds = isNullProperty($params, 'groupRiskIds'); $caseId = isNullProperty($params, 'caseId'); $examId = isNullProperty($params, 'examId'); $id = $examId ?: $caseId; $sqlWhereCaseExam = $examId ? 'MedExtDocumentOperationCode = :id' : 'MedExtDocumentCode = :id'; $sqlCaseExam = $examId ? 'MedExtDocumentOperationCode' : 'MedExtDocumentCode'; $sql = <<<EOT
            SELECT COUNT(MedExtDocumentItemCode)
            FROM MedExtDocumentItem
            WHERE $sqlWhereCaseExam
                AND ValueInteger = :groupRiskId
EOT;
$sqlIns = <<<EOT
            INSERT INTO MedExtDocumentItem ($sqlCaseExam, Type, ValueInteger)
            VALUES (:id, 1, :groupRiskId) -- 1 - Группа риска
EOT;
$stmtIns = $db->prepare($sqlIns); if ($groupRiskIds) { $groupRiskIds = safe_explode(',', $groupRiskIds); foreach ($groupRiskIds as $groupRiskId) { $groupRiskExists = selectSqlValue($db, $sql, [ ':id' => $id, ':groupRiskId' => $groupRiskId ]); if (!$groupRiskExists) { $stmtIns->execute([ ':id' => $id, ':groupRiskId' => $groupRiskId ]); } } } } public function readGroupRiskList() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    UIL.PrimaryKey "id",
    UIL.Note "name"
FROM UniImportLink UIL
WHERE UIL.Program = :program AND
      UIL.State = 1 AND
      UIL.DataType = :dataType
EOT;
return selectSql($db, $sql, [':program' => self::RISK_PROGRAM, ':dataType' => self::RISK_DATATYPE]); } private function getGroupRiskList() { $db = $this->db ?: $this->db = connectDb(); $this->log("[5] Синхронизация Групп риска..."); $this->getSettings(); if (!$request = httpCreateParam($this->extraSettings)) { $message = "Ошибка инициализации подключения"; $this->log("    [5] $message", 2); throw new Exception($message); } $request->headers['Content-Type'] = "application/json"; $request->request_method = "listGroupRisk"; $request->debug = self::DEBUG; $catalog = httpGET($request); if (empty($catalog)) { $this->log("    [5] Нет ответа от " . $request->url, 2); return false; } $dbGroupRisk = $this->getDbGroupRisk(); $dbGroupRiskArray = array(); foreach ($dbGroupRisk as $record) { $dbGroupRiskArray[$record['primarykey']] = [ 'id' => $record['primarykey'], 'risk_name' => $record['note'], 'state' => $record['state'] ]; } $results = safe_json_decode($catalog['body'], JSON_OBJECT_AS_ARRAY); if (isset($results)) { $db->beginTransaction(); try { foreach ($results as $record) { $exists = array_key_exists($record['id'], $dbGroupRiskArray); if ($exists) { $this->updateGroupRisk($record, true); unset($dbGroupRiskArray[$record['id']]); } else { $this->insertGroupRisk($record); } } if (!empty($dbGroupRiskArray)) { foreach ($dbGroupRiskArray as $record) { $this->updateGroupRisk($record, false); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } else { $this->log('    [5] Ошибка json. ' . json_last_error() . ': ' . json_last_error_msg(), 2); return false; } return true; } private function getDbGroupRisk() { $db = $this->db ?: $this->db = connectDb(); return selectSql($db, 'SELECT PrimaryKey, Note, State FROM UniImportLink WHERE Program = :program AND DataType = :dataType ORDER BY Note', [':program' => self::RISK_PROGRAM, ':dataType' => self::RISK_DATATYPE] ); } private function updateGroupRisk($record, $exists) { $db = $this->db ?: $this->db = connectDb(); $state = $exists ? 1 : 'NULL'; try { executeSql($db, "UPDATE UniImportLink SET Note = '". $record['risk_name'] . "', State = " . $state . " WHERE Program = :program AND DataType = :dataType AND PrimaryKey = :primaryKey", [':program' => self::RISK_PROGRAM, ':dataType' => self::RISK_DATATYPE, ':primaryKey' => $record['id']]); $this->log("    [5] UniImportLink, обновлена запись с PrimaryKey = " . $record['id'], 0); } catch (Throwable $e) { $this->log("    [5] UniImportLink, не удалось обновить запись с PrimaryKey = " . $record['id'], 2); throw $e; } } private function insertGroupRisk($record) { $db = $this->db ?: $this->db = connectDb(); $sqlGroupRisk = <<<EOT
            INSERT INTO UniImportLink (DataType, PrimaryKey, Note, Program, UpdateTime, State)
            VALUES (:dataType, :primarykey, :note, :program, :updateTime, 1)
EOT;
$stmtGroupRisk = $db->prepare($sqlGroupRisk); $stmtGroupRisk->execute([ ':dataType' => self::RISK_DATATYPE, ':primarykey' => $record['id'], ':note' => $record['risk_name'], ':program' => self::RISK_PROGRAM, ':updateTime' => dbDateTime(new DateTime()) ]); } private function addCase() { $this->log("[1] Регистрация Случаев в ЦОДе..."); $this->log("    [1] Выбор случаев для регистрации..."); $cases = $this->readCase(); $cnt = count($cases); if (!$cnt) { $message = "Нет случаев для отправки."; $this->log("    [1] $message", 1); $this->results[1] = $message; return $this->results; } $message = 'Случаев для отправки:'; $this->log("    [1] $message $cnt"); $this->results[1] = "$message $cnt"; $cnt_send_ok = 0; $cnt_send_error = 0; $cnt_update_error = 0; $cnt_sync_ok = 0; try { foreach ($cases as $case) { if (empty($case['last_name']) || empty($case['first_name']) || empty($case['second_name']) || empty($case['birthdate'])) { $cnt_send_error++; $this->log("    [1] Случай {$case['id']} не отправлен. Не заполнены обязательные поля."); continue; } $response = $this->postCase($case); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); if (is_array($result)) { $result = $result[0]; } $this->log("    [1] Обновление случая MedExtDocumentCode = {$case['id']} в БД"); if (!$this->updateCaseSuccessful($case, $result, $response['body'])) { $cnt_update_error++; } $cnt_send_ok++; break; case "500": $result = $result ? : $response['body']; $this->log("    [1] При регистрации случая MedExtDocumentCode = {$case['id']} произошла ошибка: {$result}", 2); $this->log("    [1] Попытка получения случая MedExtDocumentCode = {$case['id']} из ЦОД", 2); $params = new stdClass; $params->personalNumber = $case['personal_number']; $params->medCardId = $case['med_card_id']; $params->lastName = $case['last_name']; $params->firstName = $case['first_name']; $params->secondName = $case['second_name']; $params->birthDate = $case['birthdate']; $params->medPatientId = $case['patient_id']; $params->addToDb = false; $oldCaseRes = $this->getCase($params); if ($oldCaseRes && $oldCaseRes['status'][1] === "200") { if (!$this->updateCaseSuccessful($case, safe_json_decode($oldCaseRes['body']), $oldCaseRes['body'])) { $cnt_update_error++; } $cnt_sync_ok++; } break; default: $cnt_send_error++; $result = $result ? : safe_json_decode($response['body']); $this->log("    [1] При регистрации случая MedExtDocumentCode = {$case['id']} произошла ошибка: {$result}", 2); break; } } } catch (Throwable $e) { $this->log("    [1] Ошибка: {$e->getMessage()}", 2); $this->results[1] .= '; ошибка отправки'; throw $e; } $message = " Регистрация случаев завершена ($cnt):<br>   - отправлено: $cnt_send_ok;<br>   - ошибок: $cnt_send_error."; $this->log("    [1] $message"); $this->results[1] .= "; отправлено: $cnt_send_ok; ошибок при отправке: $cnt_send_error; ошибок при обновлении: $cnt_update_error; синхронизировано: $cnt_sync_ok"; return $this->results; } private function updateCaseSuccessful($case, $result, $json) { $db = $this->db ?: $this->db = connectDb(); if (is_array($result)) { $count = count($result) - 1; for ($count; $count >= 0; $count--) { if (!property_exists($result[$count],'is_finished') || !$result[$count]->is_finished) { $result = $result[$count]; break; } } } if (!$case || !$result) { return false; } $caseCreatedAt = isUndefinedProperty($result, 'created_at'); if ($caseCreatedAt) { $caseCreatedAt = new DateTime($caseCreatedAt); $caseCreatedAt->setTimeZone(new DateTimeZone('Europe/Minsk')); } $sql = <<<EOT
UPDATE MedExtDocument SET
    State = 2,
    Id_Document = ?,
    Id_Patient = ?,
    Json = ?,
    StartDate = ?
WHERE MedExtDocumentCode = ?
EOT;
$db->beginTransaction(); try { executeSql($db, $sql, [ $result->id, $result->patient_id, $json, dbDateTime($caseCreatedAt), $case['id'] ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("    [1] Обновление случая MedExtDocumentCode = {$case['id']} после регистрации в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } private function postCase($data) { $this->extraSettings ? : $this->getSettings(); if (!$params = httpCreateParam($this->extraSettings)) { $this->log("    [1] Ошибка инициализации подключения", 2); $this->results[0] .= '; ошибка подключения'; return false; } $dataSend = cloneFn($data); unset($dataSend->id); unset($dataSend->patient_id); $params->request_method = 'addCase'; $params->request_content = json_encode($dataSend, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG; return httpPOST($params); return null; } private function readCase() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    MED.MedExtDocumentCode                                  "id",              /* ИД случая в БД МИС */
    MED.MedPatientCode                                      "patient_id",      /* ИД пациента в БД МИС */
    COALESCE(MPass.PersonalNumber, '')                      "personal_number", /* Номер паспорта */
    COALESCE(MBC.Barcode, '')                               "med_card_id",     /* Номер карты медицинского обслуживания */
    M.SurName                                               "last_name",       /* Фамилия */
    M.Name                                                  "first_name",      /* Имя */
    M.SecondName                                            "second_name",     /* Отчество */
    to_char(MPass.DateOfBirth, 'YYYY-MM-DD')                "birthdate",       /* Дата рождения */
    (
        SELECT PhoneNumber
        FROM ManPhone
        WHERE ManCode = M.ManCode AND
            COALESCE(Status, 1) = 1
        LIMIT 1
    )                                                       "phone",           /* Телефон */
    AReg.CodeSoato "soatoReg",
    AReg.Place "place_nmReg",
    AReg.Street "streetReg",
    MPass.AddressHouse "houseReg",
    MPass.AddressFlat "flatReg",
    ARes.CodeSoato "soatoRes",
    ARes.Place "place_nmRes",
    ARes.Street "streetRes",
    COALESCE(MPass.AddRessResideHouse, MPass.AddressHouse)  "houseRes",
    COALESCE(MPass.AddRessResideFlat, MPass.AddressFlat)    "flatRes",
    (
        SELECT STRING_AGG(MEDIR.ValueInteger, ',')
        FROM MedExtDocumentItem MEDIR
        WHERE MEDIR.MedExtDocumentCode = MED.MedExtDocumentCode AND
            MEDIR.Type = 1
    )                                                       "group_risk_ids"  /* Группы риска */
FROM MedExtDocument MED
LEFT JOIN MedPatient MP ON MP.MedPatientCode = MED.MedPatientCode
LEFT JOIN Man M ON M.ManCode = MP.ManCode
LEFT JOIN ManPassport MPass ON MPass.ManCode = M.ManCode
LEFT JOIN MedBarCode MBC ON MBC.ManCode = M.ManCode
LEFT JOIN Address AReg ON AReg.AddressCode = MPass.AddressCode
LEFT JOIN Address ARes ON ARes.AddressCode = COALESCE(MPass.AddressResideCode, MPass.AddressCode)
WHERE MED.State = 1
EOT;
$result = selectSql($db, $sql); if (count($result)) { $diagnosisSql = <<<EOT
SELECT
    Cipher "cipher",
    ValueString "valueString"
FROM MedExtDocumentItem
WHERE
    Type = 2 AND
    MedExtDocumentCode = ?
EOT;
foreach ($result as &$res) { $res['address_registration'] = new stdClass; $res['address_residential'] = new stdClass; $res['diagnosis'] = []; $res['address_registration']->soato = $res['soatoReg']; $res['address_registration']->place_nm = $res['place_nmReg']; $res['address_registration']->street = $res['streetReg']; $res['address_registration']->house = $res['houseReg']; $res['address_registration']->flat = $res['flatReg']; $res['address_registration']->adr_text = ''; unset($res['soatoReg']); unset($res['place_nmReg']); unset($res['streetReg']); unset($res['houseReg']); unset($res['flatReg']); $res['address_residential']->soato = $res['soatoRes']; $res['address_residential']->place_nm = $res['place_nmRes']; $res['address_residential']->street = $res['streetRes']; $res['address_residential']->house = $res['houseRes']; $res['address_residential']->flat = $res['flatRes']; $res['address_residential']->adr_text = ''; unset($res['soatoRes']); unset($res['place_nmRes']); unset($res['streetRes']); unset($res['houseRes']); unset($res['flatRes']); $diagnosis = selectSql($db, $diagnosisSql, [$res['id']]); foreach ($diagnosis as $diagnos) { $d = new stdClass; $d->icd_10_code = $diagnos['cipher']; $d->icd_10_text = $diagnos['valueString']; $d->diagnosis = ''; $res['diagnosis'][] = $d; } } } return $result; } private function addCaseExam() { $db = $this->db ?: $this->db = connectDb(); $this->log("[2] Выбор осмотров (обследований) для регистрации..."); $caseExams = $this->readCaseExam(); $cnt = count($caseExams); if (!$cnt) { $message = "    [2] Нет осмотров (обследований) для отправки."; $this->log($message, 1); $this->results[2] = ' Нет осмотров (обследований) для отправки'; return $this->results; } $this->log("    [2] Осмотров (обследований) для отправки: $cnt"); $this->results[2] = " Осмотров (обследований) для отправки: $cnt"; $cnt_send_ok = 0; $cnt_send_error = 0; $cnt_update_error = 0; $cnt_sync_ok = 0; try { foreach ($caseExams as $exam) { if (empty($exam['organization_id']) || empty($exam['cancer_case_id'])) { $cnt_send_error++; $this->log("    [2] Осмотр {$exam['id']} не отправлен. Не заполнены обязательные поля"); continue; } $response = $this->postCaseExam($exam); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $this->log("    [2] Обновление осмотра MedExtDocumentOperationCode = {$exam['id']} в БД"); $caseExamsParams = new stdClass(); $caseExamsParams->caseId = $exam['misCaseId']; $caseExamsParams->addToDb = false; $oipiExams = $this->getCaseExamList($caseExamsParams); if (isEmptyArrayValue($oipiExams['status'], 1) === '200') { $ExamsArray = safe_json_decode($oipiExams['body']); if (is_array($ExamsArray)) { $lastExam = $ExamsArray[count($ExamsArray) - 1]; $exam['oipiExamId'] = $lastExam->id; } } else { $this->log("    [2] Ошибка чтения осмотров из ЦОДа", 2); } if (!$this->updateCaseExamSuccessful($exam)) { $cnt_update_error++; } $cnt_send_ok++; break; case "500": $result = $result ?: $response['body']; $this->log("    [2] При регистрации осмотра MedExtDocumentCode = {$exam['id']} произошла ошибка 500: {$result}", 2); break; default: $cnt_send_error++; $jsonBody = safe_json_decode($response['body']); $result = $result ?: $jsonBody ?: $response['body']; $this->log("    [2] При регистрации осмотра MedExtDocumentOperationCode = {$exam['id']} произошла ошибка {$response['status'][1]}: {$result}", 2); break; } } } catch (Throwable $e) { $this->log("    [2] Ошибка: {$e->getMessage()}", 2); $this->results[2] .= '; ошибка отправки'; throw $e; } $message = "[2] Регистрация осмотров завершена ($cnt):<br>   - отправлено: $cnt_send_ok;<br>   - ошибок: $cnt_send_error."; $this->log("    $message"); $this->results[2] .= "; отправлено: $cnt_send_ok; ошибок при отправке: $cnt_send_error; ошибок при обновлении: $cnt_update_error; синхронизировано: $cnt_sync_ok"; return $this->results; } private function readCaseExam() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    MED.Id_Document                     "cancer_case_id",
    MEDO.MedExtDocumentOperationCode    "id",
    MEDO.Result                         "result",
    MEDO.DoctorFIO                      "fio_doctor",
    COALESCE(MEDO.Spec_Doctor, '')      "spec_doctor",
    MEDO.Target                         "target",
    CASE
        WHEN MEDO.Is_Finished = 1
            THEN true
            ELSE false
    END                                 "is_finished",
    MEDO.Is_Biopsy                      "is_biopsy",
    MEDO.Cancer_Revealed                "cancer_revealed",
    MEDO.Precancer_Revealed             "precancer_revealed",
    MED.MedExtDocumentCode              "misCaseId"
FROM MedExtDocumentOperation MEDO
LEFT JOIN MedExtDocument MED ON MED.MedExtDocumentCode = MEDO.MedExtDocumentCode
WHERE MEDO.State = 1
EOT;
$resultsCaseExam = selectSql($db, $sql); if (count($resultsCaseExam)) { $itemsSql = <<<EOT
SELECT
    Type                "type",
    Cipher              "cipher",
    ValueInteger        "valueInteger",
    ValueString         "valueString"
FROM MedExtDocumentItem
WHERE
    MedExtDocumentOperationCode = ?
EOT;
foreach ($resultsCaseExam as &$result) { $result['organization_id'] = $this->extraSettings->officeDirectId; $items = selectSql($db, $itemsSql, [$result['id']]); $risk = array(); $ref = new stdClass; $ref->target = $result['target']; $result['referral'] = $ref; foreach ($items as $item) { switch ($item['type']) { case 1: $risk[] = $item['valueInteger']; break; case 2: $d = new stdClass; $d->icd_10_code = $item['cipher']; $d->icd_10_text = $item['valueString']; $d->diagnosis = ''; $result['diagnosis'][] = $d; break; case 3: $result['final_icd10'] = $item['cipher']; break; default: break; } } $result['group_risk_ids'] = implode(',', $risk); } } return $resultsCaseExam; } private function postCaseExam($data) { $this->extraSettings ? : $this->getSettings(); if (!$params = httpCreateParam($this->extraSettings)) { $this->log("    [2] Ошибка инициализации подключения", 2); $this->results[2] .= '; ошибка подключения'; return false; } $dataSend = cloneFn($data); unset($dataSend->id); if (empty($dataSend->is_biopsy)) { unset($dataSend->is_biopsy); } if (empty($dataSend->cancer_revealed)){ unset($dataSend->cancer_revealed); } if (empty($dataSend->precancer_revealed)){ unset($dataSend->precancer_revealed); } $params->request_method = 'addCaseExam'; $params->request_content = json_encode($dataSend, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG; return httpPOST($params); } private function updateCaseExamSuccessful($caseExam) { if (!$caseExam) { $this->log("    [2] Обновление осмотра MedExtDocumentOperationCode = {$caseExam['id']}. Пустой осмотр"); return false; } $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
UPDATE MedExtDocumentOperation SET
    State = 2,
    Id_Operation = ?
WHERE MedExtDocumentOperationCode = ?
EOT;
$db->beginTransaction(); try { executeSql($db, $sql, [ $caseExam['oipiExamId'], $caseExam['id'] ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("    [2] Обновление осмотра MedExtDocumentOperationCode = {$caseExam['id']} после регистрации в БД. Ошибка:" . $e->getMessage(), 2); $this->results[2] = " Обновление осмотра MedExtDocumentOperationCode = {$caseExam['id']} после регистрации в БД. Ошибка:" . $e->getMessage(); return false; } return true; } public function getHistoryPatient($params = null) { $config = $this->getConfig(); $db = $this->db ?: $this->db = connectDb($config); $medPatientId = isEmptyProperty($params, 'medPatientId'); $ret = NULL; $sql = <<<EOT
            SELECT
                COALESCE(MPass.PersonalNumber, '')          "personalNumber",   -- Личный номер
                COALESCE(MBC.Barcode, '')                   "medCardId",        -- Номер карты медицинского обслуживания
                COALESCE(M.Surname, '')                     "lastName",         -- Фамилия
                COALESCE(M.Name, '')                        "firstName",        -- Имя
                COALESCE(M.SecondName, '')                  "secondName",       -- Отчество
                TO_CHAR(MPass.DateOfBirth, 'YYYY-MM-DD')    "birthDate"         -- Дата рождения
            FROM MedPatient MP
            LEFT JOIN Man M ON M.ManCode = MP.ManCode
            LEFT JOIN ManPassport MPass ON MPass.ManCode = M.ManCode
            LEFT JOIN MedBarCode MBC ON MBC.ManCode = M.ManCode
            WHERE MP.MedPatientCode = ?
EOT;
$sqlCase = <<<EOT
            SELECT
                MedExtDocumentCode "caseId"
            FROM MedExtDocument
            WHERE Id_Document = ?
                AND IsType = 1
EOT;
$patient = selectSql($db, $sql, [ $medPatientId ], PDO::FETCH_CLASS); if (count($patient)) { $patient = $patient[0]; $patient->medPatientId = $medPatientId; $casesOipiResponse = $this->getCase($patient); if (array_key_exists('status', $casesOipiResponse) && isEmptyArrayValue($casesOipiResponse['status'], 1) === '200') { $cases = safe_json_decode($casesOipiResponse['body']); foreach ($cases as $case) { $misCaseId = selectSqlValue($db, $sqlCase, [ isUndefinedProperty($case, 'id') ]); if ($misCaseId) { $params = new stdClass(); $params->caseId = $misCaseId; $examsOipiResponse = $this->getCaseExamList($params); if (array_key_exists('status', $examsOipiResponse) && isEmptyArrayValue($examsOipiResponse['status'], 1) === '200') { $exams = safe_json_decode($examsOipiResponse['body']); $case->exams = $exams; } else { $case->exams = $examsOipiResponse; } $ret[] = $case; } } } else { $ret = $casesOipiResponse; } } return $ret; } public function startExchange($params = null) { $this->extraSettings ?: $this->getSettings(); if (empty($this->extraSettings)) { return $this->results; } $this->log("startExchange..."); $this->getGroupRiskList(); $this->addCase(); $this->addCaseExam(); return $this->results; } } handleJsonRpc('med_lab_linkOncology');