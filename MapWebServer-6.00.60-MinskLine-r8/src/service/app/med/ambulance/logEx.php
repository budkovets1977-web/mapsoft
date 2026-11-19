<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/common/patients.php'); require_once(__DIR__ . '/logStats.php'); require_once(__DIR__ . '/logDiagnoses.php'); require_once(__DIR__ . '/../../med/ambulance/wealths.php'); resumeJsonRpc(); class med_ambulance_logEx { private $db; const NEW_CALL_NOTICE = 'ambulance-new-call'; const UPDATE_CALL_NOTICE = 'ambulance-update-call'; private function sessionConnectDb(){ return $this->db ?: $this->db = connectDb(); } public function readLog($params){ $this->sessionConnectDb(); $sql = <<<EOT
            SELECT
                AL.amblogcode                       "id",
                AL.address                          "address",                  -- Адрес инцидента из журнала
                AL.ageday                           "ageDay",
                AL.agemonth                         "ageMonth",
                AL.ageyear                          "ageYear",
                AL.dateofbirth                      "dateOfBirth",
                AL.inTown                           "inTown",
                AL.repeatCall                       "repeatCall",
                AL.ambgraphcode                     "ambGraphId",
                AL.ambtaskcode                      "ambTaskCode",
                AL.appeal                           "appeal",
                AL.callerPhone                      "callerPhone",
                AL.callKind                         "callKind",
                to_char(AL.calltime, '2008-01-01 HH24:MI:SS')
                                                    "callTime",
                AL.calltime                         "callDate",
                to_char(AL.callsavetime, '2008-01-01 HH24:MI:SS')
                                                    "callSaveTime",
                AL.callsavetime                     "callSaveDate",
                AL.cardnumber                       "cardNumber",
                AL.careresult                       "careResult",
                AL.careresulttext                   "careResultText",
                to_char(AL.checkouttime, '2008-01-01 HH24:MI:SS')
                                                    "checkOutTime",
                AL.checkouttime                     "checkOutDate",
                AL.death                            "death",
                AL.departureresult                  "departureResult",
                AL.enterprisestructurecodezone      "enterpriseStructureZoneId",
                AL.enterprisestructurecode          "enterpriseStuctureId",
                AL.fio                              "fio",
                AL.location                         "location",
                AL.medPatientCode                   "patientId",
                AL.note                             "note",
                AL.paid                             "paid",
                AL.phone                            "patientPhone",
                COALESCE(AL.priority, 1)            "priority",
                AL.reasontext                       "reasonText",
                to_char(AL.returntime, '2008-01-01 HH24:MI:SS')
                                                    "returnTime",
                AL.returntime                       "returnDate",
                AL.sex                              "sex",
                AL.teammembers                      "teamMembers",
                AT.teamNumber                       "teamNumber",
                AT.type                             "teamType",
                to_char(AL.transfertime, '2008-01-01 HH24:MI:SS')
                                                    "transferTime",
                AL.transfertime                     "transferTimeDate",
                AL.caller                           "caller",
                AL.unidictcodereason                "reasonKind",
                AL.reasonrefuse                     "reasonRefuse",
                AL.unsuccessfuldeparture            "unsuccessfulDeparture",
                AL.usercodedispatcher               "dispatcherId",
                U.nick                              "dispatcher",
                AL.activeObserve                    "activeObserve",
                AL.enterpriseCodeObserve            "enterpriseObserveId",
                -- переадресация
                ALF.enterpriseCode                  "forwardingEnterpriseId",
                ALF.enterpriseStructureCode         "forwardingEnterpriseStructureId",
                to_char(ALF.forwardingTime, '2008-01-01 HH24:MI:SS')
                                                    "forwardingTime",
                ALF.whom                            "forwardingWhom",
                to_char(ALF.resultTime, '2008-01-01 HH24:MI:SS')
                                                    "forwardingResultTime",
                ALF.result                          "forwardingResult",
                ALF.isForwarding                    "isForwarding",
                AL.callCancel                       "callCancel",                   -- Отмена вызова
                AL.callCancelReason                 "callCancelReason",             -- Причина отмены
                -- сообщение
                to_char(ALM.messageTime, '2008-01-01 HH24:MI:SS')
                                                    "messageTime",
                ALM.unidictcodeauthority            "authorityKind",
                ALM.whom                            "messageWhom",
                -- результат
                COALESCE(ALD.whereTo, 0)            "whereTo",
                ALD.enterpriseCode                  "deliveryEnterpriseId",
                ALD.receiptDate                     "receiptDate",
                ALD.medDiagnosisCode                "diagnosisId",
                ALD.dischargeTime                   "dischargeTime",
                ALD.exodus                          "exodus",
                ALD.diffInDiagnosis                 "diffInDiagnosis",
                -- карта вызова
                AL.activeObserve                    "activeObserve",
                AL.enterpriseCodeObserve            "enterpriseObserveId",
                to_char(AL.arrivalTime, '2008-01-01 HH24:MI:SS')
                                                    "arrivalTime",
                AL.arrivalTime                      "arrivalDate",
                to_char(AL.endTime, '2008-01-01 HH24:MI:SS')
                                                    "endTime",
                AL.endTime                          "endDate",
                AL.activeObserveAccepted            "activeObserveAccepted",        -- ФИО принявшего
                to_char(AL.activeObserveTime, '2008-01-01 HH24:MI:SS')
                                                    "activeObserveTime",            -- Время передачи
                AL.activeObserveTime                "activeObserveDate",            -- Дата передачи
                AL.departureResultFlags             "departureResultFlags",         -- Битовая маска
                AL.unsuccessfulDepartureText        "unsuccessfulDepartureText",    -- Причина безрезультатного выезда
                ALD.whom                            "deliveryWhom",                 -- ФИО принявшего
                to_char(ALD.deliveryTime , '2008-01-01 HH24:MI:SS')
                                                    "deliveryTime",                 -- Время принятия
                ALD.deliveryTime                    "deliveryDate",                 -- Дата принятия
                ALD.hospitalized                    "hospitalized",                 -- Госпитализирован
                AL.resident                         "resident",                     -- Иногородний/иностранец
                AL.countryCode                      "countryCode",                  -- Код страны для "Иностранец"
                AL.unidictCodeArea                  "codeArea",                     -- Код области для "Иногородний"
                AL.unidictCodeDistrict              "codeDistrict",                 -- Код района внутри области для "Иногородний"
                AL.birth                            "birth",                        -- Роды
                AL.flat                             "kv",
                AL.amblogcodeparent                 "logCodeParent",
                UD.value                            "reasonDisplay",
                AL.servingPolyclinic                "servingPolyclinic",
                ALS.lowpressure                     "bloodLowPressure",
                ALS.highpressure                    "bloodHighPressure",
                ALS.pulse                           "pulsenumber",
                ALS.heartrate                       "breathingRate",
                ALS.temperature                     "bodyTemperature",
                AL.departureKm                      "kilometers",
                AL.iscarddone                       "cardDone"
            FROM AmbLog AL
            LEFT JOIN UniDict UD ON UD.uniDictCode = AL.uniDictCodeReason AND UD.dictNumber = 59
            LEFT JOIN AmbLogForwarding ALF on ALF.ambLogCode = AL.ambLogCode
            LEFT JOIN AmbLogMessage ALM on ALM.ambLogCode = AL.ambLogCode
            LEFT JOIN AmbLogDelivery ALD on ALD.ambLogCode = AL.ambLogCode
            LEFT JOIN AmbGraph AG on AG.ambGraphCode = AL.ambGraphCode
            LEFT JOIN AmbTeam AT on AT.ambTeamCode = AG.ambTeamCode
            LEFT JOIN ambLogSheet ALS on ALS.ambLogCode = AL.ambLogCode
            JOIN Users U on U.userCode = AL.userCodeDispatcher
            WHERE AL.ambLogCode = ?
EOT;
$res = selectSql($this->db, $sql, [ isEmptyProperty($params, 'id') ]); if (empty($res[0]['patientId'])){ return $res; } $query = new stdClass(); $query->patientId = $res[0]['patientId']; $query->getClinic = true; $query->getSector = true; $query->getAddress = true; $patient = new med_common_patients(); $patient = $patient->readPatientInfo($query); $res[0]['clinic'] = $patient['clinic']; $res[0]['sector'] = $patient['sectorName']; $res[0]['patientRegistrationAddress'] = $patient['registrationAddress']; $res[0]['patientResidenceAddress'] = $patient['residenceAddress']; return $res; } protected function checkLogAccess($type) { suspendJsonRpc(); require_once (__DIR__ . '/log.php'); resumeJsonRpc(); $log = new med_ambulance_log(); $log->checkLogAccess($type); } public function createLog($record){ $this->sessionConnectDb(); $this->checkLogAccess('add'); $this->db->beginTransaction(); try { $record->id = $this->insertLogBD($record); if (isUndefinedProperty($record, 'forwardingEnterpriseId')){ $this->createLogForwarding($record); } if (isUndefinedProperty($record, 'authorityKind')){ $this->createLogMessage($record); } if (isUndefinedProperty($record, 'whereTo')){ $this->createLogDelivery($record); } $this->db->commit(); } catch (Throwable $e){ $this->db->rollback(); throw $e; } $params = new stdClass(); $params->id = $record->id; return $this->readLog($params); } public function updateLog($record){ $this->sessionConnectDb(); $this->checkLogAccess('edit'); $this->db->beginTransaction(); try { $this->updateLogBD($record); if (property_exists($record,'forwardingEnterpriseId') || property_exists($record,'forwardingResult') || property_exists($record,'forwardingResultTime') || property_exists($record,'forwardingTime') || property_exists($record,'forwardingWhom') ){ $this->updateLogForwarding($record); } if (property_exists($record,'authorityKind') || property_exists($record,'messageWhom') || property_exists($record,'messageTime') ){ $this->updateLogMessage($record); } if (property_exists($record, 'whereTo') || property_exists($record, 'deliveryTime') || property_exists($record, 'deliveryEnterpriseId') || property_exists($record, 'receiptDate') || property_exists($record, 'diagnosisId') || property_exists($record, 'dischargeTime') || property_exists($record, 'exodus') || property_exists($record, 'diffInDiagnosis') ){ $this->updateLogDelivery($record); } if (isEmptyProperty($record, 'clearCallData')){ $this->clearCallData($record->id); } $this->db->commit(); } catch (Throwable $e){ $this->db->rollback(); throw $e; } return true; } private function insertLogBD($record){ $graphId = isEmptyProperty($record, 'ambGraphId'); $sql = <<<EOT
            INSERT INTO ambLog
                (active, address, ageday, agemonth,
                ageyear, ambgraphcode, intown, appeal,
                callerphone, callkind, calltime, callsavetime,
                cardnumber, careresult, careresulttext, checkouttime,
                dateofbirth, death, departureresult, enterprisestructurecode,
                enterprisestructurecodezone, fio, location, medpatientcode,
                note, paid, phone, priority,
                reasontext, returntime, sex, teammembers,
                transfertime, unidictcodereason, unsuccessfulDeparture, usercodedispatcher,
                activeobserve, arrivaltime, endtime, enterprisecodeobserve,
                caller, reasonrefuse, ambtaskcode, repeatcall,
                flat, amblogcodeparent, activeobserveaccepted, activeobservetime,
                departureresultflags, unsuccessfulDepartureText, resident, birth,
                callcancel, callcancelreason, servingPolyclinic, departureKm,
                isCardDone, carddata, countrycode, unidictcodearea, unidictcodedistrict)
            VALUES
                (1, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?::timestamp, ?::timestamp,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?::timestamp, ?::timestamp, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?::timestamp,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?)
            RETURNING ambLogCode AS id
EOT;
$oid = selectSqlValue($this->sessionConnectDb(), "SELECT oid FROM pg_class WHERE relname = 'amblog'"); if ($oid) executeSql($this->sessionConnectDb(), "SELECT pg_advisory_xact_lock($oid)"); if (isEmptyProperty($record, 'transferTimeDate')) { $transferTime = $this->createDateTimeField(isEmptyProperty($record, 'transferTimeDate'), isEmptyProperty($record, 'transferTime')); } elseif (!is_null(isEmptyProperty($record, 'transferTime'))) { $transferTime = $this->createDateTimeField(dbDateTime(time()), isEmptyProperty($record, 'transferTime')); } else { $transferTime = null; } if (isEmptyProperty($record, 'checkOutDate')) { $checkOutTime = $this->createDateTimeField(isEmptyProperty($record, 'checkOutDate'), isEmptyProperty($record, 'checkOutTime')); } elseif (!is_null(isEmptyProperty($record, 'checkOutTime'))) { $checkOutTime = $this->createDateTimeField(dbDateTime(time()), isEmptyProperty($record, 'checkOutTime')); } else { $checkOutTime = null; } $cardNumber = isEmptyProperty($record, 'cardNumber') ?? $this->getNextCardNumber(isEmptyProperty($record, 'callDate')); $result = selectSqlValue($this->db, $sql, [ isEmptyProperty($record, 'address'), isEmptyProperty($record, 'ageDay'), isEmptyProperty($record, 'ageMonth'), isEmptyProperty($record, 'ageYear'), isEmptyProperty($record, 'ambGraphId'), isEmptyProperty($record, 'inTown'), isEmptyProperty($record, 'appeal'), isEmptyProperty($record, 'callerPhone'), isEmptyProperty($record, 'callKind'), $this->createDateTimeField(isEmptyProperty($record, 'callDate'), isEmptyProperty($record, 'callTime')), $this->createDateTimeField(isEmptyProperty($record, 'callSaveDate'), isEmptyProperty($record, 'callSaveTime')), $cardNumber, isEmptyProperty($record, 'careResult'), isEmptyProperty($record, 'careResultText'), $checkOutTime, isEmptyProperty($record, 'dateOfBirth'), isEmptyProperty($record, 'death'), isEmptyProperty($record, 'departureResult'), getSessionValue('EnterpriseStructureCode'), isEmptyProperty($record, 'enterpriseStructureZoneId'), isEmptyProperty($record, 'fio'), isEmptyProperty($record, 'location'), isEmptyProperty($record, 'patientId'), isEmptyProperty($record, 'note'), isEmptyProperty($record, 'paid'), isEmptyProperty($record, 'patientPhone'), isEmptyProperty($record, 'priority'), isEmptyProperty($record, 'reasonText'), isEmptyProperty($record, 'returnTime'), isEmptyProperty($record, 'sex'), isEmptyProperty($record, 'teamMembers'), $transferTime, isEmptyProperty($record, 'reasonKind'), isEmptyProperty($record, 'unsuccessfulDeparture'), getSessionValue('mapUserCode'), isEmptyProperty($record, 'activeObserve'), $this->createDateTimeField(isEmptyProperty($record, 'arrivalDate'), isEmptyProperty($record, 'arrivalTime')), $this->createDateTimeField(isEmptyProperty($record, 'endDate'), isEmptyProperty($record, 'endTime')), isEmptyProperty($record, 'enterpriseObserveId'), isEmptyProperty($record, 'caller'), isEmptyProperty($record, 'reasonRefuse'), isEmptyProperty($record, 'ambTaskCode'), isEmptyProperty($record, 'repeatCall'), isEmptyProperty($record, 'kv'), isEmptyProperty($record, 'logCodeParent'), isEmptyProperty($record, 'activeObserveAccepted'), $this->createDateTimeField(isEmptyProperty($record, 'activeObserveDate'), isEmptyProperty($record, 'activeObserveTime')), isEmptyProperty($record, 'departureResultFlags'), isEmptyProperty($record, 'unsuccessfulDepartureText'), isEmptyProperty($record, 'resident'), isEmptyProperty($record, 'birth'), isEmptyProperty($record, 'callCancel'), isEmptyProperty($record, 'callCancelReason'), isEmptyProperty($record, 'servingPolyclinic'), isEmptyProperty($record, 'kilometers'), isEmptyProperty($record, 'cardDone'), isEmptyProperty($record, 'carddata'), isEmptyProperty($record, 'countrycode'), isEmptyProperty($record, 'unidictcodearea'), isEmptyProperty($record, 'unidictcodedistrict') ]); $ambLogId = $result; $this->createUpdateNotice($ambLogId, true); if ($graphId){ $sqlNotification = <<<EOT
                SELECT
                    md.userCode "userCode"
                FROM ambGraphPerson a
                LEFT JOIN medDoctor md ON a.medDoctorCode = md.medDoctorCode
                WHERE ambGraphCode = ?
                    AND md.userCode IS NOT NULL
EOT;
$doctors = selectSql($this->db, $sqlNotification, [$graphId], PDO::FETCH_NAMED); foreach ($doctors as $row){ $this->createMobileNotice($row, $ambLogId); } } return $result; } public function sendTelegramMessage($record){ $db = $this->db ?: $this->db = connectDb(); $graphId = isUndefinedProperty($record, 'ambGraphId'); $dontSendMessage = isUndefinedProperty($record, 'dontSendMessage'); if ($dontSendMessage) return false; $sqlTelegram = <<<EOT
            SELECT
                telegramCode
            FROM ambMobileDevice
            WHERE ambTeamCode = (SELECT ambTeamCode FROM ambGraph WHERE ambGraphCode = ?)
                AND ambTeamCode IS NOT NULL
            LIMIT 1
EOT;
$telegramId = selectSqlValue($db, $sqlTelegram, [$graphId], PDO::FETCH_NAMED); if ($telegramId && !isUndefinedProperty($record, 'endDate', false) && !isUndefinedProperty($record, 'endTime', false)){ $address = isEmptyProperty($record, 'address', ' '); $birth = isEmptyProperty($record, 'dateOfBirth', ' '); $reason = isEmptyProperty($record, 'reasonDisplay', ' '); $cardNumber = isEmptyProperty($record, 'cardNumber', ' '); $callSaveTime = $this->createDateTimeField(isEmptyProperty($record, 'callSaveDate'), isEmptyProperty($record, 'callSaveTime')); $priority = isEmptyProperty($record, 'priority') === 1 ? 'Неотложный' : 'Экстренный'; $kv = isEmptyProperty($record, 'kv') ? 'кв. ' . isEmptyProperty($record, 'kv') : ' '; $sex = isEmptyProperty($record, 'sex') === 0 ? 'М.' : 'Ж.'; $location = isEmptyProperty($record, 'location'); $locationStr = ' '; switch ($location){ case 1: $locationStr = 'улица'; break; case 2: $locationStr = 'рабочее место'; break; case 3: $locationStr = 'квартира'; break; case 4: $locationStr = 'общественное место'; break; case 5: $locationStr = 'организация здравоохранения'; break; } $path = "https://api.telegram.org/bot5319443750:AAFjdj9PoJdBvSjtZ5YvPSQTtIPQww04hC0/sendmessage"; $text = $priority . "\n". 'Адрес: ' . $address . ' ' . $kv . "\n". 'Дата рождения: ' . $birth . "\n". 'Пол: ' . $sex . "\n". 'Повод: ' . $reason . "\n". 'Место нахождения: ' . $locationStr . "\n". 'Карта №: ' . $cardNumber . ', дата и время вызова: ' . $callSaveTime . "\n" ; $data = array('chat_id' => $telegramId, 'text' => $text, 'parse_mode' => 'HTML'); $options = array( 'http' => array( 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data) ), 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ) ); $context = stream_context_create($options); try { $message = file_get_contents($path, false, $context); app_log($message); } catch (Throwable $e){ app_log('Сообщение для ' . $telegramId . ' не отправлено! ' . $e); } } else { app_log('Не найден telegramId для бригады из графика № ' . $graphId); } } public function createMobileNotice($row, $ambLogId){ $notice = new stdClass(); $notice->type = self::NEW_CALL_NOTICE; $notice->source = $ambLogId; $notice->validTime = (new DateTime())->add(new DateInterval('PT10M')); $notice->userCode = $row['userCode']; $notice->enterpriseCode = getSessionValue('mapEnterpriseCode'); $notice->singleton = 1; $data = new stdClass(); $data->ambLogId = $ambLogId; $notice->data = $data; common_notices::createNotice($notice); } public function createUpdateNotice($ambLogId, $isNew = false){ $notice = new stdClass(); $notice->type = self::UPDATE_CALL_NOTICE; $notice->source = $ambLogId; $notice->validTime = (new DateTime())->add(new DateInterval('PT2M')); $notice->userCodeAuthor = getSessionValue('mapUserCode'); $data = new stdClass(); $data->ambLogId = $ambLogId; if ($isNew){ $data->isNew = true; } $notice->data = $data; common_notices::createNotice($notice); } private function updateLogBD($record){ $graphId = isEmptyProperty($record, 'ambGraphId'); $cardNumber = property_exists($record, 'cardNumber'); if ($cardNumber) { $this->checkLogAccess('cardNumber'); } $sql = <<<EOT
            UPDATE ambLog SET
                active =                        CASE WHEN ? = 1 THEN ? ELSE active END,
                address =                       CASE WHEN ? = 1 THEN ? ELSE address END,
                ageDay =                        CASE WHEN ? = 1 THEN ? ELSE ageDay END,
                ageMonth =                      CASE WHEN ? = 1 THEN ? ELSE ageMonth END,
                ageYear =                       CASE WHEN ? = 1 THEN ? ELSE ageYear END,
                ambGraphCode =                  CASE WHEN ? = 1 THEN ? ELSE ambGraphCode END,
                inTown =                        CASE WHEN ? = 1 THEN ? ELSE inTown END,
                appeal =                        CASE WHEN ? = 1 THEN ? ELSE appeal END,
                uniDictCodeReason =             CASE WHEN ? = 1 THEN ? ELSE uniDictCodeReason END,
                unsuccessfulDeparture =         CASE WHEN ? = 1 THEN ? ELSE unsuccessfulDeparture END,
                callerPhone =                   CASE WHEN ? = 1 THEN ? ELSE callerPhone END,
                --
                callKind =                      CASE WHEN ? = 1 THEN ? ELSE callKind END,
                callTime =                      CASE WHEN ? = 1 THEN ?::TIMESTAMP ELSE callTime END,
                callSaveTime =                  CASE WHEN ? = 1 THEN ?::TIMESTAMP ELSE callSaveTime END,
                cardNumber =                    CASE WHEN ? = 1 THEN ? ELSE cardNumber END,
                careResult =                    CASE WHEN ? = 1 THEN ? ELSE careResult END,
                careResultText =                CASE WHEN ? = 1 THEN ? ELSE careResultText END,
                checkOutTime =                  CASE WHEN ? = 1 THEN ? ELSE checkOutTime END,
                dateOfBirth =                   CASE WHEN ? = 1 THEN ? ELSE dateOfBirth END,
                death =                         CASE WHEN ? = 1 THEN ? ELSE death END,
                departureResult =               CASE WHEN ? = 1 THEN ? ELSE departureResult END,
                --
                enterpriseStructureCodeZone =   CASE WHEN ? = 1 THEN ? ELSE enterpriseStructureCodeZone END,
                fio =                           CASE WHEN ? = 1 THEN ? ELSE fio END,
                location =                      CASE WHEN ? = 1 THEN ? ELSE location END,
                medPatientCode =                CASE WHEN ? = 1 THEN ? ELSE medPatientCode END,
                note =                          CASE WHEN ? = 1 THEN ? ELSE note END,
                paid =                          CASE WHEN ? = 1 THEN ? ELSE paid END,
                phone =                         CASE WHEN ? = 1 THEN ? ELSE phone END,
                priority =                      CASE WHEN ? = 1 THEN ? ELSE priority END,
                reasonText =                    CASE WHEN ? = 1 THEN ? ELSE reasonText END,
                returnTime =                    CASE WHEN ? = 1 THEN ? ELSE returnTime::TIMESTAMP END,
                --
                sex =                           CASE WHEN ? = 1 THEN ? ELSE sex END,
                teamMembers =                   CASE WHEN ? = 1 THEN ? ELSE teamMembers END,
                transferTime =                  CASE WHEN ? = 1 THEN ? ELSE transferTime END,
                activeObserve =                 CASE WHEN ? = 1 THEN ? ELSE activeObserve END,
                arrivalTime =                   CASE WHEN ? = 1 THEN ?::TIMESTAMP ELSE arrivalTime END,
                endTime =                       CASE WHEN ? = 1 THEN ?::TIMESTAMP ELSE endTime END,
                enterpriseCodeObserve =         CASE WHEN ? = 1 THEN ? ELSE enterpriseCodeObserve END,
                caller =                        CASE WHEN ? = 1 THEN ? ELSE caller END,
                reasonRefuse =                  CASE WHEN ? = 1 THEN ? ELSE reasonRefuse END,
                --
                activeObserveAccepted =         CASE WHEN ? = 1 THEN ? ELSE activeObserveAccepted END,
                activeObserveTime =             CASE WHEN ? = 1 THEN ? ELSE activeObserveTime END,
                departureResultFlags =          CASE WHEN ? = 1 THEN ? ELSE departureResultFlags END,
                unsuccessfulDepartureText =     CASE WHEN ? = 1 THEN ? ELSE unsuccessfulDepartureText END,
                resident =                      CASE WHEN ? = 1 THEN ? ELSE resident END,
                birth =                         CASE WHEN ? = 1 THEN ? ELSE birth END,
                callCancel =                    CASE WHEN ? = 1 THEN ? ELSE callCancel END,
                callCancelReason =              CASE WHEN ? = 1 THEN ? ELSE callCancelReason END,
                ambTaskCode =                   CASE WHEN ? = 1 THEN ? ELSE ambTaskCode END,
                repeatCall =                    CASE WHEN ? = 1 THEN ? ELSE repeatCall END,
                flat =                          CASE WHEN ? = 1 THEN ? ELSE flat END,
                servingPolyclinic =             CASE WHEN ? = 1 THEN ? ELSE servingPolyclinic END,
                departureKm =                   CASE WHEN ? = 1 THEN ? ELSE departureKm END,
                isCardDone =                    CASE WHEN ? = 1 THEN ? ELSE isCardDone END,
                countryCode =                   CASE WHEN ? = 1 THEN ? ELSE countryCode END,
                unidictCodeArea =               CASE WHEN ? = 1 THEN ? ELSE unidictCodeArea END,
                unidictCodeDistrict =           CASE WHEN ? = 1 THEN ? ELSE unidictCodeDistrict END
            WHERE ambLogCode = ?
            RETURNING ambLogCode AS id
EOT;
+$existsCallTime = propertyExistsSQL($record, 'callTime') && propertyExistsSQL($record, 'callDate'); $callTime = null; if ($existsCallTime){ $callTime = $this->createDateTimeField(isEmptyProperty($record, 'callDate'), isEmptyProperty($record, 'callTime')); } +$existsCallSaveTime = propertyExistsSQL($record,'callSaveTime') && propertyExistsSQL($record,'callSaveDate'); $callSaveTime = null; if ($existsCallSaveTime){ $callSaveTime = $this->createDateTimeField(isEmptyProperty($record, 'callSaveDate'), isEmptyProperty($record, 'callSaveTime')); } +$existsReturnTime = propertyExistsSQL($record, 'returnTime') && propertyExistsSQL($record, 'returnDate'); $returnTime = null; if ($existsReturnTime){ $returnTime = $this->createDateTimeField(isEmptyProperty($record, 'returnDate'), isEmptyProperty($record, 'returnTime')); } +$existsArrivalTime = propertyExistsSQL($record, 'arrivalTime') && propertyExistsSQL($record, 'arrivalDate'); $arrivalTime = null; if ($existsArrivalTime){ $arrivalTime = $this->createDateTimeField(isEmptyProperty($record, 'arrivalDate'), isEmptyProperty($record, 'arrivalTime')); } +$existsEndTime = propertyExistsSQL($record, 'endTime') && propertyExistsSQL($record, 'endDate'); $endTime = null; if ($existsEndTime){ $endTime = $this->createDateTimeField(isEmptyProperty($record, 'endDate'), isEmptyProperty($record, 'endTime')); } +$existsActiveObserveTime = propertyExistsSQL($record, 'activeObserveTime') && propertyExistsSQL($record, 'activeObserveDate'); $activeObserveTime = null; if ($existsActiveObserveTime){ $activeObserveTime = $this->createDateTimeField(isEmptyProperty($record, 'activeObserveDate'), isEmptyProperty($record, 'activeObserveTime')); } $transferTime = isEmptyProperty($record, 'transferTime'); if (isEmptyProperty($record, 'transferTimeDate')) { $transferTime = $this->createDateTimeField(isEmptyProperty($record, 'transferTimeDate'), isEmptyProperty($record, 'transferTime')); } elseif (!is_null($transferTime)) { $transferTime = $this->createDateTimeField(dbDateTime(time()), isEmptyProperty($record, 'transferTime')); } else { $transferTime = null; } if (isEmptyProperty($record, 'checkOutDate')) { $checkOutTime = $this->createDateTimeField(isEmptyProperty($record, 'checkOutDate'), isEmptyProperty($record, 'checkOutTime')); } elseif (!is_null(isEmptyProperty($record, 'checkOutTime'))) { $checkOutTime = $this->createDateTimeField(dbDateTime(time()), isEmptyProperty($record, 'checkOutTime')); } else { $checkOutTime = null; } $result = selectSqlValue($this->db, $sql, [ propertyExistsSQL($record, 'active'), isEmptyProperty($record, 'active'), propertyExistsSQL($record, 'address'), isEmptyProperty($record, 'address'), propertyExistsSQL($record, 'ageDay'), isEmptyProperty($record, 'ageDay'), propertyExistsSQL($record, 'ageMonth'), isEmptyProperty($record, 'ageMonth'), propertyExistsSQL($record, 'ageYear'), isEmptyProperty($record, 'ageYear'), propertyExistsSQL($record, 'ambGraphId'), isEmptyProperty($record, 'ambGraphId'), propertyExistsSQL($record, 'inTown'), isEmptyProperty($record, 'inTown'), propertyExistsSQL($record, 'appeal'), isEmptyProperty($record, 'appeal'), propertyExistsSQL($record, 'reasonKind'), isEmptyProperty($record, 'reasonKind'), propertyExistsSQL($record, 'unsuccessfulDeparture'), isEmptyProperty($record, 'unsuccessfulDeparture'), propertyExistsSQL($record, 'callerPhone'), isEmptyProperty($record, 'callerPhone'), propertyExistsSQL($record, 'callKind'), isEmptyProperty($record, 'callKind'), $existsCallTime, $callTime, $existsCallSaveTime, $callSaveTime, propertyExistsSQL($record, 'cardNumber'), isEmptyProperty($record, 'cardNumber'), propertyExistsSQL($record, 'careResult'), isEmptyProperty($record, 'careResult'), propertyExistsSQL($record, 'careResultText'), isEmptyProperty($record, 'careResultText'), propertyExistsSQL($record, 'checkOutTime'), $checkOutTime, propertyExistsSQL($record, 'dateOfBirth'), isEmptyProperty($record, 'dateOfBirth'), propertyExistsSQL($record, 'death'), isEmptyProperty($record, 'death'), propertyExistsSQL($record, 'departureResult'), isEmptyProperty($record, 'departureResult'), propertyExistsSQL($record, 'enterpriseStructureZoneId'), isEmptyProperty($record, 'enterpriseStructureZoneId'), propertyExistsSQL($record, 'fio'), isEmptyProperty($record, 'fio'), propertyExistsSQL($record, 'location'), isEmptyProperty($record, 'location'), propertyExistsSQL($record, 'patientId'), isEmptyProperty($record, 'patientId'), propertyExistsSQL($record, 'note'), isEmptyProperty($record, 'note'), propertyExistsSQL($record, 'paid'), isEmptyProperty($record, 'paid'), propertyExistsSQL($record, 'patientPhone'), isEmptyProperty($record, 'patientPhone'), propertyExistsSQL($record, 'priority'), isEmptyProperty($record, 'priority'), propertyExistsSQL($record, 'reasonText'), isEmptyProperty($record, 'reasonText'), $existsReturnTime, $returnTime, propertyExistsSQL($record, 'sex'), isEmptyProperty($record, 'sex'), propertyExistsSQL($record, 'teamMembers'), isEmptyProperty($record, 'teamMembers'), propertyExistsSQL($record, 'transferTime'), $transferTime, propertyExistsSQL($record, 'activeObserve'), isEmptyProperty($record, 'activeObserve'), $existsArrivalTime, $arrivalTime, $existsEndTime, $endTime, propertyExistsSQL($record, 'enterpriseObserveId'), isEmptyProperty($record, 'enterpriseObserveId'), propertyExistsSQL($record, 'caller'), isEmptyProperty($record, 'caller'), propertyExistsSQL($record, 'reasonRefuse'), isEmptyProperty($record, 'reasonRefuse'), propertyExistsSQL($record, 'activeObserveAccepted'), isEmptyProperty($record, 'activeObserveAccepted'), $existsActiveObserveTime, $activeObserveTime, propertyExistsSQL($record, 'departureResultFlags'), isEmptyProperty($record, 'departureResultFlags'), propertyExistsSQL($record, 'unsuccessfulDepartureText'), isEmptyProperty($record, 'unsuccessfulDepartureText'), propertyExistsSQL($record, 'resident'), isEmptyProperty($record, 'resident'), propertyExistsSQL($record, 'birth'), isEmptyProperty($record, 'birth'), propertyExistsSQL($record, 'callCancel'), isEmptyProperty($record, 'callCancel'), propertyExistsSQL($record, 'callCancelReason'), isEmptyProperty($record, 'callCancelReason'), propertyExistsSQL($record, 'ambTaskCode'), isEmptyProperty($record, 'ambTaskCode'), propertyExistsSQL($record, 'repeatCall'), isEmptyProperty($record, 'repeatCall'), propertyExistsSQL($record, 'kv'), isEmptyProperty($record, 'kv'), propertyExistsSQL($record, 'servingPolyclinic'), isEmptyProperty($record, 'servingPolyclinic'), propertyExistsSQL($record, 'kilometers'), isEmptyProperty($record, 'kilometers'), propertyExistsSQL($record, 'cardDone'), isEmptyProperty($record, 'cardDone'), propertyExistsSQL($record, 'countryCode'), isEmptyProperty($record, 'countryCode'), propertyExistsSQL($record, 'codeArea'), isEmptyProperty($record, 'codeArea'), propertyExistsSQL($record, 'codeDistrict'), isEmptyProperty($record, 'codeDistrict'), isUndefinedProperty($record, 'id') ]); med_ambulance_logStats::updateMedEmergency($this->db, $record->id); $ambLogId = $result; $this->createUpdateNotice($ambLogId); $callKind = isEmptyProperty($record, 'callKind'); if ($callKind === 11 && !$graphId){ $sql = <<<EOT
SELECT
    AL.ambgraphcode
FROM
    amblog AL
WHERE al.amblogcode = ?
EOT;
$graphId = selectSqlValue($this->db,$sql,[$ambLogId]); } if ($graphId){ $sqlNotification = <<<"EOT"
SELECT
    MD.UserCode as "userCode"
FROM
    AmbGraphPerson A
        LEFT JOIN medDoctor MD on A.MedDoctorCode = MD.MedDoctorCode
WHERE ambGraphCode = ?
AND MD.usercode IS NOT NULL
EOT;
$doctors = selectSql($this->db, $sqlNotification, [$graphId], PDO::FETCH_NAMED); foreach ($doctors as $row){ $this->createMobileNotice($row, $ambLogId); } } return $result; } public function deleteLog($records){ $this->sessionConnectDb(); $this->checkLogAccess('delete'); $this->db->beginTransaction(); try { $result = deleteSql($this->db, 'ambLog', 'ambLogCode', $records,'id', false); if (!is_array($records)){ $records = [$records]; } foreach ($records as $record){ $this->createUpdateNotice($record->id); } } catch (Throwable $e){ $this->db->rollback(); throw $e; } return $result; } private function createLogForwarding($record){ $sql = <<<"EOT"
insert into AmbLogForwarding
    (amblogcode, enterprisecode, enterprisestructurecode, forwardingtime, result, resulttime, whom, isforwarding)
values
    (?, ?, ?, ?::time, ?, ?::time, ?, ?)
EOT;
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isEmptyProperty($record, 'forwardingEnterpriseId'), isEmptyProperty($record, 'forwardingEnterpriseStructureId'), isUndefinedProperty($record, 'forwardingTime'), isEmptyProperty($record, 'forwardingResult'), isEmptyProperty($record, 'forwardingResultTime'), isUndefinedProperty($record, 'forwardingWhom'), isUndefinedProperty($record, 'isForwarding') ]); } private function updateLogForwarding($record){ $sql = <<<"EOT"
insert into AmbLogForwarding
    (amblogcode, enterprisecode, enterprisestructurecode, forwardingtime, result, resulttime, whom, isforwarding)
values
    (?, ?, ?, ?::time, ?, ?::time, ?, ?)
on conflict (amblogcode) do update set
    enterprisecode = case when ? = 1 then EXCLUDED.enterprisecode else AmbLogForwarding.enterprisecode end,
    enterprisestructurecode = case when ? = 1 then EXCLUDED.enterprisestructurecode else AmbLogForwarding.enterprisestructurecode end,
    forwardingTime = case when ? = 1 then EXCLUDED.forwardingTime::time else AmbLogForwarding.forwardingTime end,
    result = case when ? = 1 then EXCLUDED.result else AmbLogForwarding.result end,
    resultTime = case when ? = 1 then EXCLUDED.resultTime::time else AmbLogForwarding.resultTime end,
    --
    whom = case when ? = 1 then EXCLUDED.whom else AmbLogForwarding.whom end,
    isForwarding = case when ? = 1 then EXCLUDED.isForwarding else AmbLogForwarding.isForwarding end
EOT;
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isEmptyProperty($record, 'forwardingEnterpriseId'), isEmptyProperty($record, 'forwardingEnterpriseStructureId'), isUndefinedProperty($record, 'forwardingTime'), isEmptyProperty($record, 'forwardingResult'), isEmptyProperty($record, 'forwardingResultTime'), isUndefinedProperty($record, 'forwardingWhom'), isUndefinedProperty($record, 'isForwarding'), propertyExistsSQL($record, 'forwardingEnterpriseId'), propertyExistsSQL($record, 'forwardingEnterpriseStructureId'), propertyExistsSQL($record, 'forwardingTime'), propertyExistsSQL($record, 'forwardingResult'), propertyExistsSQL($record, 'forwardingResultTime'), propertyExistsSQL($record, 'forwardingWhom'), propertyExistsSQL($record, 'isForwarding') ]); } private function createLogMessage($record){ $sql = <<<"EOT"
insert into AmbLogMessage
    (amblogcode, messagetime, unidictcodeauthority, whom)
values
    (?, ?::time, ?, ?)
EOT;
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isUndefinedProperty($record, 'messageTime'), isEmptyProperty($record, 'authorityKind'), isEmptyProperty($record, 'messageWhom') ]); } private function updateLogMessage($record){ $id = isUndefinedProperty($record, 'id'); if ($id){ $sqlParams[':id'] = $id; $isOld = selectSqlValue($this->db,'SELECT EXISTS(SELECT 1 FROM amblogmessage amb WHERE amb.amblogcode = :id)',$sqlParams); if (!$isOld){ $this->createLogMessage($record); return true; } else { $sql = <<<"EOT"
update AmbLogMessage set
    messagetime = case when ? = 1 then ?::time else messagetime end,
    unidictcodeauthority = case when ? = 1 then ? else unidictcodeauthority end,
    whom = case when ? = 1 then ? else whom end
where ambLogCode = ?
EOT;
executeSql($this->db, $sql, [ propertyExistsSQL($record, 'messageTime'), isEmptyProperty($record, 'messageTime'), propertyExistsSQL($record, 'authorityKind'), isEmptyProperty($record, 'authorityKind'), propertyExistsSQL($record, 'messageWhom'), isEmptyProperty($record, 'messageWhom'), isUndefinedProperty($record, 'id') ]); } } } private function createLogDelivery($record){ $sql = <<<"EOT"
insert into AmbLogDelivery
    (amblogcode, whereTo, deliveryTime, enterpriseCode, receiptDate, medDiagnosisCode, dischargeTime, exodus, diffindiagnosis, whom, hospitalized)
values
    (?, ?, ?::time, ?, ?::timestamp, ?, ?::time, ?, ?, ?, ?)
EOT;
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isUndefinedProperty($record, 'whereTo'), $this->createDateTimeField(isEmptyProperty($record, 'deliveryDate'), isEmptyProperty($record, 'deliveryTime')), isEmptyProperty($record, 'deliveryEnterpriseId'), isUndefinedProperty($record, 'receiptDate'), isUndefinedProperty($record, 'diagnosisId'), isUndefinedProperty($record, 'dischargeTime'), isUndefinedProperty($record, 'exodus'), isUndefinedProperty($record, 'diffInDiagnosis'), isUndefinedProperty($record, 'deliveryWhom'), isUndefinedProperty($record, 'hospitalized') ]); } private function updateLogDelivery($record){ +$existsDeliveryTime = propertyExistsSQL($record, 'deliveryTime') && propertyExistsSQL($record, 'deliveryDate'); $deliveryTime = null; if ($existsDeliveryTime){ $deliveryTime = $this->createDateTimeField(isEmptyProperty($record, 'deliveryDate'), isEmptyProperty($record, 'deliveryTime')); } $sql = <<<"EOT"
insert into AmbLogDelivery
    (amblogcode, whereTo, deliveryTime, enterpriseCode, receiptDate, medDiagnosisCode, dischargeTime, exodus, diffindiagnosis, whom, hospitalized)
values
    (?, ?, ?::time, ?, ?::timestamp, ?, ?::time, ?, ?, ?, ?)
on conflict (amblogcode) do update set

    whereTo = case when ? = 1 then EXCLUDED.whereTo else AmbLogDelivery.whereTo end,
    deliveryTime = case when ? = 1 then EXCLUDED.deliveryTime::time else AmbLogDelivery.deliveryTime end,
    enterpriseCode = case when ? = 1 then EXCLUDED.enterpriseCode else AmbLogDelivery.enterpriseCode end,
    receiptDate = case when ? = 1 then EXCLUDED.receiptDate::timestamp else AmbLogDelivery.receiptDate end,
    medDiagnosisCode = case when ? = 1 then EXCLUDED.medDiagnosisCode else AmbLogDelivery.medDiagnosisCode end,
    --
    dischargeTime = case when ? = 1 then EXCLUDED.dischargeTime::time else AmbLogDelivery.dischargeTime end,
    exodus = case when ? = 1 then EXCLUDED.exodus else AmbLogDelivery.exodus end,
    diffInDiagnosis = case when ? = 1 then EXCLUDED.diffInDiagnosis else AmbLogDelivery.diffInDiagnosis end,
    whom = case when ? = 1 then EXCLUDED.whom else AmbLogDelivery.whom end,
    hospitalized = case when ? = 1 then EXCLUDED.hospitalized else AmbLogDelivery.hospitalized end
EOT;
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isUndefinedProperty($record, 'whereTo'), $deliveryTime, isEmptyProperty($record, 'deliveryEnterpriseId'), isUndefinedProperty($record, 'receiptDate'), isUndefinedProperty($record, 'diagnosisId'), isUndefinedProperty($record, 'dischargeTime'), isUndefinedProperty($record, 'exodus'), isUndefinedProperty($record, 'diffInDiagnosis'), isUndefinedProperty($record, 'deliveryWhom'), isUndefinedProperty($record, 'hospitalized'), propertyExistsSQL($record, 'whereTo'), $existsDeliveryTime, propertyExistsSQL($record, 'deliveryEnterpriseId'), propertyExistsSQL($record, 'receiptDate'), propertyExistsSQL($record, 'diagnosisId'), propertyExistsSQL($record, 'dischargeTime'), propertyExistsSQL($record, 'exodus'), propertyExistsSQL($record, 'diffInDiagnosis'), propertyExistsSQL($record, 'deliveryWhom'), propertyExistsSQL($record, 'hospitalized') ]); } private function createDateTimeField($date, $time){ if (empty($date) && empty($time)){ return null; } $_date = new DateTime(empty($date) ? '19000101' : $date); $_time = new DateTime(empty($time) ? '00:00:00' : $time); return date($_date->format('Y-m-d') . ' ' . $_time->format('H:i:s')); } public function getNextCardNumber($callDate){ $this->sessionConnectDb(); $callDate = new DateTime($callDate); $callDate->setDate((int)$callDate->format('Y'), 1, 1); $callDate->setTime(0, 0); $callDate = dbDateTime($callDate); $sql = <<<EOT
            SELECT SUBSTRING(cardNumber, '[0-9]+') + 1
            FROM ambLog
            WHERE cardNumber ~* '[0-9]+'
            AND ambLogCodeParent IS NULL
            AND callTime >= ?
            ORDER BY ambLogCode DESC
            LIMIT 1
EOT;
$result = selectSqlValue($this->db, $sql, [$callDate]); return $result ?: '1'; } private function clearCallData($ambLogId){ $this->clearCallDataLog($ambLogId); $wealths = new med_ambulance_wealths(); $wealths->clearWealths($ambLogId); $diagnoses = new med_ambulance_logDiagnoses(); $diagnoses->clearDiagnoses($ambLogId); } private function clearCallDataLog($ambLogId){ $sql = <<<"EOT"
update AmbLog set
    activeObserve = null,
    enterpriseCodeObserve = null,
    arrivalTime = null,
    endTime = null
where AmbLogCode = {$ambLogId}
EOT;
return executeSql($this->db, $sql); } public function createNewCall($params){ $this->sessionConnectDb(); $id = isEmptyProperty($params, 'id'); if (!$id) return false; $ambGraphId = isEmptyProperty($params, 'ambGraphId'); $teamNumber = isEmptyProperty($params, 'teamNumber'); $transferTime = isEmptyProperty($params, 'transferTime'); $teamMembers = isEmptyProperty($params, 'teamMembers'); $dontSendMessage = isEmptyProperty($params, 'dontSendMessage'); $query = new stdClass(); $newCall = new stdClass(); $query->id = $id; $call = $this->readLog($query); $call = (object)$call[0]; if (!$call) return false; $newCall->ambGraphId = $ambGraphId; $newCall->teamNumber = $teamNumber; $newCall->transferTime = $this->createDateTimeField(null, $transferTime); $newCall->teamMembers = $teamMembers; $newCall->priority = $call->priority; $newCall->cardNumber = $call->cardNumber; $newCall->address = $call->address; $newCall->kv = $call->kv; $newCall->inTown = $call->inTown; $newCall->patientId = $call->patientId; $newCall->fio = $call->fio; $newCall->callDate = $call->callDate; $newCall->callTime = $call->callTime; $newCall->callSaveDate = dbDate(new DateTime()); $newCall->callSaveTime = dbDateTime(new DateTime()); $newCall->dateOfBirth = $call->dateOfBirth; $newCall->sex = $call->sex; $newCall->ageYear = $call->ageYear; $newCall->ageMonth = $call->ageMonth; $newCall->ageDay = $call->ageDay; $newCall->clinic = isEmptyProperty($call, 'clinic'); $newCall->sector = isEmptyProperty($call, 'sector'); $newCall->reasonKind = $call->reasonKind; $newCall->patientPhone = $call->patientPhone; $newCall->repeatCall = $call->repeatCall; $newCall->reasonText = $call->reasonText; $newCall->callerPhone = $call->callerPhone; $newCall->location = $call->location; $newCall->appeal = $call->appeal; $newCall->callKind = $call->callKind; $newCall->enterpriseStructureZoneId = $call->enterpriseStructureZoneId; $newCall->dispatcher = $call->dispatcher; $newCall->dispatcherId = $call->dispatcherId; $newCall->logCodeParent = $call->logCodeParent ?? $call->id; $newCall->dontSendMessage = $dontSendMessage; $this->db->beginTransaction(); try { $result = $this->insertLogBD($newCall); $this->db->commit(); } catch (Throwable $e){ $this->db->rollback(); throw $e; } return $result; } } handleJsonRpc('med_ambulance_logEx');