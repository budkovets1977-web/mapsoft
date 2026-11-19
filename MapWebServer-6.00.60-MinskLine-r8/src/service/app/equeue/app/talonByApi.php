<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_talonByApi { const TASK_NAME = 'equeueAppTalonByApi'; const SETTINGS_TASK_NAME = 'medReceptionLinkTalon'; const INFO = 0; const WARNING = 1; const ERROR = 2; private $db; private $extraSettings; public function startExchange(){ $settings = $this->readSettings(); if ($this->checkSettings($settings)){ $settings->task = self::TASK_NAME; $this->extraSettings = $settings; $this->log('Настройки загружены успешно...'); do { $eQueueData = $this->getEQueueData(); $response = $this->sendEQueueData($eQueueData); $eQueueData = null; sleep(5); } while(true); } return $response; } private function readSettings(){ if (!empty($this->extraSettings)){ return $this->extraSettings; } $this->log('Загрузка настроек...'); $settings = readExtraSettings(getSessionValue('config'), self::SETTINGS_TASK_NAME); return $settings; } private function checkSettings($settings){ $settingsValid = false; if ($settings == new stdClass()){ $this->log('Не произведена настройка обмена информацией с talon.by', self::WARNING); } elseif (isset($settings->active) && $settings->active){ if (empty($settings->urlEq)){ $this->log('Не заполнена настройка Адрес сервера (ЭО)', self::ERROR); }else{ $settingsValid = true; } if (empty($settings->clientId)){ $this->log('Не заполнена настройка Логин клиента', self::ERROR); }else{ $settingsValid = true; } if (empty($settings->clientPassword)){ $this->log('Не заполнена настройка Пароль клиента', self::ERROR); }else{ $settingsValid = true; } if (!$settingsValid){ throw new Exception('Проверьте правильность заполнения данных для подключения.'); } } else { $this->log('В настройках не включен обмен данными', self::WARNING); throw new Exception('В настройках не включен обмен данными'); } return $settingsValid; } private function log($message, $type = 0){ app_log($message, $type, self::TASK_NAME); } private function getEQueueData(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT ROW_TO_JSON("eQueueData") "serviceData"
            FROM (
                SELECT
                    medEqServiceCode id,
                    name "serviceName",
                    label,
                    note,
                    cipher,
                    quota,
                    (SELECT ARRAY_TO_JSON(ARRAY_AGG(ROW_TO_JSON(tt)))
                        FROM (
                            SELECT
                                name "timetableName",
                                startTime "startTime",
                                finishTime "finishTime",
                                ruleDay "ruleDay",
                                ruleDaysOfWeek "ruleDaysOfWeek",
                                ruleEvenOdd "ruleEvenOdd",
                                ruleMonth "ruleMonth",
                                ruleYear "ruleYear"
                            FROM uniTimetable
                            WHERE source = 1
                                AND sourceCode = mes.medEqServiceCode
                            ORDER BY startTime
                        ) tt
                    ) AS timetable,
                    (SELECT ARRAY_TO_JSON(ARRAY_AGG(ROW_TO_JSON(cabs)))
                        FROM (
                            SELECT DISTINCT cabinet
                            FROM medEqOwp
                            WHERE active = 1
                                AND medEqServiceCode = mes.medEqServiceCode
                            ORDER BY cabinet
                        ) cabs
                    ) AS cabinets,
                    (SELECT ARRAY_TO_JSON(ARRAY_AGG(ROW_TO_JSON(t)))
                        FROM (
                            SELECT
                                medEqQueueCouponCode id,
                                createTime "createTime",
                                trim(documentNumber) "talonNumber",
                                state
                            FROM medEqQueueCoupon
                            WHERE medEqServiceCode = mes.medEqServiceCode
                                AND state in (0, -1)
                            ORDER BY createTime
                        ) t
                    ) AS talons,
                    (SELECT ARRAY_TO_JSON(ARRAY_AGG(ROW_TO_JSON(opt)))
                        FROM (
                            SELECT
                                medEqServiceOptionCode id,
                                "name" "optionName",
                                "label" "optionLabel",
                                note "optionNote",
                                "position" "optionPosition"
                            FROM medEqServiceOption
                            WHERE medEqServiceCode = 74
                                AND active = 1
                            ORDER BY position
                        ) opt
                    ) AS "options"
                FROM medEqService mes
                WHERE active = 1
                AND enterpriseCode = ?
                AND (hideOnSite IS NULL OR hideOnSite != 1)
                ORDER BY "serviceName"
            ) "eQueueData"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $this->extraSettings->mapEnterpriseCode ]); $ret = $stmt->fetchAll(); $eQueueData = new stdClass(); foreach($ret as $row){ $eQueueData->eQueueData[] = safe_json_decode($row['serviceData']); } $eQueueData->login = $this->extraSettings->clientId; $eQueueData->password = $this->extraSettings->clientPassword; return $eQueueData; } private function sendEQueueData($eQueueData){ $params = new stdClass(); $params->url = rtrim($this->extraSettings->urlEq, '/') . "/equeue/exchange"; $params->certificate = true; $params->method = "updateEqueue"; $params->params = [$eQueueData]; $response = httpJsonRpc($params); $params = null; $this->log('Данные отправлены...'); return $response; } public function requestDataForQueue($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $customerId = isEmptyProperty($params, 'customerId'); $patientId = isEmptyProperty($params, 'patientId'); $serviceId = isEmptyProperty($params, 'equeueId'); $optionId = isEmptyProperty($params, 'optionId'); $logStudyId = null; $categoryId = null; $infoboxId = null; $sql = 'SELECT PR_medEqPatientCheck(?, ?, ?, ?, ?, ?) response'; $stmt = $db->prepare($sql); $stmt->execute([ $patientId, $logStudyId, $serviceId, $optionId, $categoryId, $infoboxId ]); $response = fetchValue($stmt, 'response'); $response = safe_explode(',', $response); $errorCode = $response[0]; $categoryId = $response[2]; $this->checkErrorCode($errorCode); $sql = <<<EOT
            SELECT
                name category,
                (SELECT COUNT(*) FROM medEqQueueCoupon WHERE medEqServiceCode = :serviceId) "talonCount",
                (SELECT COUNT(*) FROM medEqQueueCoupon WHERE medEqServiceCode = :serviceId AND medEqPatientCategoryCode = :categoryId) "talonCountForCategory",
                NULL "waitingTime"
            FROM medEqPatientCategory
            WHERE medEqPatientCategoryCode = :categoryId
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':serviceId' => $serviceId, ':categoryId' => $categoryId ]); $ret = (object)$stmt->fetch(); if (!$ret){ throw new Exception("По указанным параметрам ничего не найдено"); } return $ret; } public function sendToQueue($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $customerId = isEmptyProperty($params, 'customerId'); $patientId = isEmptyProperty($params, 'patientId'); $serviceId = isEmptyProperty($params, 'equeueId'); $optionId = isEmptyProperty($params, 'optionId'); $logStudyId = null; $categoryId = null; $infoboxId = null; $pin = null; $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $patientId, $logStudyId, $serviceId, $optionId, $categoryId, $infoboxId, $pin ]); $id = $db->lastInsertId('seq_medEqQueueCoupon'); if ($id >= 0){ $sql2 = 'SELECT FN_medEqGetCouponPIN(?)'; $stmt2 = $db->prepare($sql2); $stmt2->execute([ $id ]); } $db->commit(); }catch(Throwable $e){ $db->rollback(); throw $e; } $talonId = (int)fetchValue($stmt, 'id'); $this->checkErrorCode($talonId); $sql = <<<EOT
            SELECT
                mc."name" category,
                TRIM(m.documentNumber) "talonNumber",
                m.pin "shortNumber"
            FROM medEqQueueCoupon m
            LEFT JOIN medEqPatientCategory mc ON mc.medEqPatientCategoryCode = m.medEqPatientCategoryCode
            WHERE m.medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $talonId ]); $ret = (object)$stmt->fetch(); if (!$ret){ throw new Exception("Ошибка постановки в очередь"); } return $ret; } private function checkErrorCode($code){ switch($code){ case -4: $msg = 'По выбранной услуге в системе не создано ни одного оператора.'; $this->log($msg, self::ERROR); throw new Exception($msg, self::ERROR); case -5: $msg = 'Превышено максимальное количество талонов по выбранной услуге.'; $this->log($msg, self::ERROR); throw new Exception($msg, self::ERROR); case -6: $msg = 'Время выдачи талонов по выбранной услуге истекло или услуга не привязана ни к одному инфокиоску.'; $this->log($msg, self::ERROR); throw new Exception($msg, self::ERROR); case -7: $msg = 'Пациента не успеют обслужить в рамках рабочего времени.'; $this->log($msg, self::ERROR); throw new Exception($msg, self::ERROR); } if ($code < 0){ $msg = 'Ошибка постановки в очередь.'; $this->log($msg, self::ERROR); throw new Exception($msg, self::ERROR); } } } handleJsonRpc('equeue_app_talonByApi');