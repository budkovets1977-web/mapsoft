<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/common/patients.php'); require_once(__DIR__ . '/../../med/ambulance/logStats.php'); require_once(__DIR__ . '/../../med/ambulance/logDiagnoses.php'); require_once(__DIR__ . '/../../med/ambulance/wealths.php'); resumeJsonRpc(); class med_ambulancemobile_calls { private $db; const NOTICE_UPDATE_TYPE = 'ambulance-update-call'; const NOTIFICATION_LIFE_LENGTH = 65; public function connectAndroidBot($params = null){ $db = $this->db ?: $this->db = connectDb(); $androidId = isEmptyProperty($params, 'androidId'); $newConnect = isEmptyProperty($params, 'newConnect'); $telegramtext = isEmptyProperty($params, 'telegramText'); $reset = isEmptyProperty($params, 'reset'); if ($androidId){ $sql = <<<EOT
                SELECT
                    ambMobileDeviceCode
                FROM ambMobileDevice
                WHERE androidId = ?
EOT;
$code = selectSqlValue($db, $sql, [$androidId]); if (!$code && !$newConnect) return false; $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $dateStr = "'NOW'::DATE"; $where = "WHERE gp.medDoctorCode = :doctorId AND (g.onDate = {$dateStr}
      or (g.onDate = ({$dateStr} - '1 day'::interval) and s.finishTime < s.startTime))"; $sqlParams = [ ':doctorId' => $doctorId ]; $sql = <<<EOT
                SELECT
                    a.ambTeamCode
                FROM ambGraph a
                WHERE a.ambGraphCode = (
                    SELECT g.ambGraphCode
                    FROM ambGraphPerson gp
                    LEFT JOIN ambGraph g ON g.ambGraphCode = gp.ambGraphCode
                    LEFT JOIN ambShift s ON s.ambShiftCode = g.ambShiftCode
                    $where
                    LIMIT 1
                )
EOT;
$teamCode = selectSqlValue($db, $sql, $sqlParams); if ($teamCode){ if ($reset && $code){ $sql = <<<EOT
                        UPDATE ambMobileDevice
                        SET
                            telegramCode = ?
                        WHERE ambMobileDeviceCode = ?
EOT;
executeSql($db, $sql, [$telegramtext,$code]); return true; } else if ($reset){ $sql = <<<EOT
                        INSERT INTO ambMobileDevice
                                (androidId, telegramCode, ambTeamCode)
                        VALUES (?,?,?)
EOT;
executeSql($db, $sql, [$androidId, $telegramtext, $teamCode]); return true; } if (!$telegramtext){ $sql = <<<EOT
                        SELECT 1
                        FROM ambMobileDevice
                        WHERE androidId = ? AND ambTeamCode = ?
EOT;
if (selectSqlValue($db, $sql, [$androidId, $teamCode])) return true; } $sql = <<<EOT
                    UPDATE ambMobileDevice
                    SET
                        ambTeamCode = null
                    WHERE ambTeamCode = ?
EOT;
selectSqlValue($db, $sql, [$teamCode]); if ($code){ $sql = <<<EOT
                        UPDATE ambMobileDevice
                        SET
                            ambTeamCode = ?
                        WHERE ambMobileDeviceCode = ?
EOT;
executeSql($db, $sql, [$teamCode, $code]); } else { $sql = <<<EOT
                        INSERT INTO ambMobileDevice
                                (androidId, telegramCode, ambTeamCode)
                        VALUES (?,?,?)
EOT;
executeSql($db, $sql, [$androidId, $telegramtext, $teamCode]); } } } return true; } public function readCalls($params = null){ $db = $this->db ?: $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $dateStr = "'NOW'::DATE"; $where = "WHERE gp.medDoctorCode = :doctorId AND (G.onDate = {$dateStr}
                    OR (G.onDate = ({$dateStr} - '1 day'::interval) AND S.finishTime < S.startTime))"; $sqlParams = [ ':doctorId' => $doctorId ]; $callId = (int) isEmptyProperty($params, 'callId'); if ($callId){ $where .= ' AND al.ambLogCode = :callId'; $sqlParams[':callId'] = $callId; } $sql = <<<EOT
            SELECT al.ambLogCode                    id,
                al.active,
                al.priority,
                al.fio                              "patientFio",
                al.address || CASE WHEN coalesce(al.flat, '') = '' THEN '' ELSE ' кв. ' || al.flat END
                                                    "address",
                al.callSaveTime                     "callSaveTime",    -- Время создания вызова
                al.callSaveTime                     "callSaveDate",    -- Дата создания вызова
                al.callTime                         "callTime",        -- Время звонка
                al.transferTime                     "transferTime",    -- Время назначения (передача вызова бригаде)
                al.checkoutTime                     "checkoutTime",    -- Время принятия (бригада нажала кнопку принять вызов)
                al.endTime                          "endTime",         -- Время завершения работы на вызове
                al.medPatientCode                   "patientId",
                al.ageDay                           "ageDays",
                al.ageMonth                         "ageMonths",
                al.ageYear                          "ageYears",
                al.returnTime                       "returnTime",
                al.arrivalTime                      "arrivalTime",
                al.inTown                           "inTown",
                al.cardNumber                       "cardNumber",
                al.dateOfBirth                      "dateOfBirth",
                al.sex,
                al.phone,
                al.callTime                         "callTime",
                ud1.value                           "reason",
                al.uniDictCodeReason                "reasonKind",
                al.reasonText                       "reasonText",
                al.callerPhone                      "callerPhone",
                al.caller,
                al.departureResult                  "departureResult",
                al.activeObserve                    "activeObserve",
                al.enterpriseCodeObserve            "enterpriseObserveId",
                al.activeObserveAccepted            "activeObserveAccepted",        -- ФИО принявшего
                to_char(al.activeObserveTime, '2008-01-01 HH24:MI:SS')
                                                    "activeObserveTime",            -- Время передачи
                al.activeObserveTime                "activeObserveDate",            -- Дата передачи
                al.departureResultFlags             "departureResultFlags",         -- Битовая маска
                ald.enterpriseCode                  "deliveryEnterpriseId",
                ald.whom                            "deliveryWhom",                 -- ФИО принявшего
                to_char(ald.deliveryTime , '2008-01-01 HH24:MI:SS')
                                                    "deliveryTime",                 -- Время принятия
                ald.deliveryTime                    "deliveryDate",                 -- Дата принятия
                ald.hospitalized                    "hospitalized",                 -- Госпитализирован
                al.resident                         "resident",                     -- Иногородний / иностранец
                al.countryCode                      "countryCode",                  -- Код страны для "Иностранец"
                al.unidictCodeArea                  "codeArea",                     -- Код области для "Иногородний"
                al.unidictCodeDistrict              "codeDistrict",                 -- Код района внутри области для "Иногородний"
                al.birth                            "birth",                        -- Роды
                al.death                            "death",
                al.careResult                       "careResult",
                al.reasonRefuse                     "reasonRefuse",
                al.unsuccessfulDeparture            "unsuccessfulDeparture",
                al.unsuccessfulDepartureText        "unsuccessfulDepartureText",    -- Причина безрезультатного выезда
                al.location,
                al.note,
                al.callKind                         "callKind",
                al.repeatCall                       "repeatCall",
                als.lowPressure                     "bloodLowPressure",
                als.highPressure                    "bloodHighPressure",
                als.pulse                           "pulsenumber",
                als.heartRate                       "breathingRate",
                als.temperature                     "bodyTemperature",
                s.name                              "shiftName",
                t.teamNumber                        "teamNumber",
                t.type                              "teamType",
                al.ambGraphCode                     "ambGraphId",
                al.isCardDone                       "cardDone",
                TRIM(m.name || ' ' || m.secondname || ' ' || m.surname)
                                                    "fioDispatcher",
                al.departureKm                      "kilometers"
            FROM ambLog al
            LEFT JOIN uniDict ud1 ON ud1.uniDictCode = al.uniDictCodeReason AND ud1.dictNumber = 59 -- Повод из справочника
            LEFT JOIN medDoctor md ON md.userCode = al.userCodeDispatcher
            LEFT JOIN man m ON m.mancode = md.mancode
            LEFT JOIN ambGraph g ON g.ambGraphCode = al.ambGraphCode
            LEFT JOIN ambShift s ON s.ambShiftCode = g.ambShiftCode
            LEFT JOIN ambTeam t ON t.ambTeamCode = s.ambTeamCode
            LEFT JOIN amblogsheet als ON als.amblogcode = al.amblogcode
            LEFT JOIN AmbLogDelivery ald ON ald.ambLogCode = al.ambLogCode
            WHERE NOT (al.active IS NULL AND al.checkoutTime IS NULL)
                AND al.ambGraphCode IN (
                    SELECT g.ambGraphCode
                    FROM ambGraphPerson gp
                    LEFT JOIN ambGraph g ON g.ambGraphCode = gp.ambGraphCode
                    $where
                )
            ORDER BY CASE WHEN al.endTime IS NULL THEN 0 ELSE 1 END, al.endTime DESC, al.ambLogCode
EOT;
$res = selectSql($db, $sql, $sqlParams); return $res; } public function updateCalls($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            UPDATE ambLog
            SET checkoutTime =              CASE WHEN ? = 1 THEN ? ELSE checkoutTime END,
                endTime =                   CASE WHEN ? = 1 THEN ? ELSE endTime END,
                returnTime =                CASE WHEN ? = 1 THEN ? ELSE returnTime END,
                arrivalTime =               CASE WHEN ? = 1 THEN ? ELSE arrivalTime END,
                activeObserve =             CASE WHEN ? = 1 THEN ? ELSE activeObserve END,
                enterpriseCodeObserve =     CASE WHEN ? = 1 THEN ? ELSE enterpriseCodeObserve END,
                activeObserveAccepted =     CASE WHEN ? = 1 THEN ? ELSE activeObserveAccepted END,
                activeObserveTime =         CASE WHEN ? = 1 THEN ? ELSE activeObserveTime END,
                departureResultFlags =      CASE WHEN ? = 1 THEN ? ELSE departureResultFlags END,
                unsuccessfulDeparture =     CASE WHEN ? = 1 THEN ? ELSE unsuccessfulDeparture END,
                unsuccessfulDepartureText = CASE WHEN ? = 1 THEN ? ELSE unsuccessfulDepartureText END,
                resident =                  CASE WHEN ? = 1 THEN ? ELSE resident END,
                death =                     CASE WHEN ? = 1 THEN ? ELSE death END,
                reasonRefuse =              CASE WHEN ? = 1 THEN ? ELSE reasonRefuse END,
                birth =                     CASE WHEN ? = 1 THEN ? ELSE birth END,
                careResult =                CASE WHEN ? = 1 THEN ? ELSE careResult END,
                departureResult =           CASE WHEN ? = 1 THEN ? ELSE departureResult END,
                isCardDone =                CASE WHEN ? = 1 THEN ? ELSE isCardDone END,
                departureKm =               CASE WHEN ? = 1 THEN ? ELSE departureKm END,
                countryCode =               CASE WHEN ? = 1 THEN ? ELSE countryCode END,
                unidictCodeArea =           CASE WHEN ? = 1 THEN ? ELSE unidictCodeArea END,
                unidictCodeDistrict =       CASE WHEN ? = 1 THEN ? ELSE unidictCodeDistrict END
            WHERE ambLogCode = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row){ if (propertyExistsSQL($row, 'medDiagnosisId')){ $sqlDiagnosis = <<<EOT
                        UPDATE ambLogDiagnosis
                        SET medDiagnosisCode = CASE WHEN ? = 1 THEN ? ELSE medDiagnosisCode END
                        WHERE ambLogDiagnosisCode = (
                            SELECT ambLogDiagnosisCode
                            FROM ambLogDiagnosis
                            WHERE ambLogCode = ? AND diagnosisType = 1
                            ORDER BY ambLogDiagnosisCode
                            LIMIT 1
                        );
EOT;
$stmtDiagnosis = $db->prepare($sqlDiagnosis); $stmtDiagnosis->execute([ propertyExistsSQL($row, 'medDiagnosisId'), isUndefinedProperty($row, 'medDiagnosisId'), isEmptyProperty($row, 'id') ]); $sqlDiagnosis = <<<EOT
                    INSERT INTO ambLogDiagnosis
                        (ambLogCode, diagnosisType, medDiagnosisCode)
                    SELECT :ambLogId, 1, :medDiagnosisId
                    WHERE NOT EXISTS (SELECT 1 FROM ambLogDiagnosis WHERE ambLogCode = :ambLogId AND diagnosisType = 1);
EOT;
$stmtDiagnosis = $db->prepare($sqlDiagnosis); $stmtDiagnosis->execute([ ':medDiagnosisId' => isEmptyProperty($row, 'medDiagnosisId'), ':ambLogId' => isEmptyProperty($row, 'id') ]); } $sqlSheet = <<<EOT
                    UPDATE ambLogSheet
                    SET
                        lowPressure =   CASE WHEN ? = 1 THEN ? ELSE lowPressure END,
                        highPressure =  CASE WHEN ? = 1 THEN ? ELSE highPressure END,
                        pulse =         CASE WHEN ? = 1 THEN ? ELSE pulse END,
                        heartRate =     CASE WHEN ? = 1 THEN ? ELSE heartRate END,
                        temperature =   CASE WHEN ? = 1 THEN ? ELSE temperature END
                    WHERE ambLogCode = ?;
EOT;
$stmtSheet = $db->prepare($sqlSheet); $stmtSheet->execute([ propertyExistsSQL($row, 'bloodLowPressure'), isUndefinedProperty($row, 'bloodLowPressure'), propertyExistsSQL($row, 'bloodHighPressure'), isUndefinedProperty($row, 'bloodHighPressure'), propertyExistsSQL($row, 'pulseNumber'), isUndefinedProperty($row, 'pulseNumber'), propertyExistsSQL($row, 'breathingRate'), isUndefinedProperty($row, 'breathingRate'), propertyExistsSQL($row, 'bodyTemperature'), isUndefinedProperty($row, 'bodyTemperature'), isUndefinedProperty($row, 'id') ]); $sqlSheet = <<<EOT
                    INSERT INTO ambLogSheet
                        (ambLogCode, lowPressure, highPressure,
                        pulse, heartRate, temperature)
                    SELECT
                        :ambLogId, :lowPressure, :highPressure,
                        :pulse, :heartRate, :temperature
                    WHERE NOT EXISTS (SELECT 1 FROM ambLogSheet WHERE ambLogCode = :ambLogId);
EOT;
$stmtSheet = $db->prepare($sqlSheet); $stmtSheet->execute([ ':ambLogId' => isEmptyProperty($row, 'id'), ':lowPressure' => isEmptyProperty($row, 'bloodLowPressure'), ':highPressure' => isEmptyProperty($row, 'bloodHighPressure'), ':pulse' => isEmptyProperty($row, 'pulseNumber'), ':heartRate' => isEmptyProperty($row, 'breathingRate'), ':temperature' => isEmptyProperty($row, 'bodyTemperature') ]); if (property_exists($row, 'whereTo') || property_exists($row, 'deliveryTime') || property_exists($row, 'deliveryEnterpriseId') || property_exists($row, 'receiptDate') || property_exists($row, 'diagnosisId') || property_exists($row, 'dischargeTime') || property_exists($row, 'exodus') || property_exists($row, 'diffInDiagnosis') ){ $this->updateLogDelivery($row); } +$existsActiveObserveTime = propertyExistsSQL($row, 'activeObserveTime') && propertyExistsSQL($row, 'activeObserveDate'); $activeObserveTime = null; if ($existsActiveObserveTime){ $activeObserveTime = $this->createDateTimeField(isEmptyProperty($row, 'activeObserveDate'), isEmptyProperty($row, 'activeObserveTime')); } $stmt->execute([ propertyExistsSQL($row, 'checkoutTime'), isUndefinedProperty($row, 'checkoutTime'), propertyExistsSQL($row, 'endTime'), isUndefinedProperty($row, 'endTime'), propertyExistsSQL($row, 'returnTime'), isUndefinedProperty($row, 'returnTime'), propertyExistsSQL($row, 'arrivalTime'), isUndefinedProperty($row, 'arrivalTime'), propertyExistsSQL($row, 'activeObserve'), isEmptyProperty($row, 'activeObserve'), propertyExistsSQL($row, 'enterpriseObserveId'), isEmptyProperty($row, 'enterpriseObserveId'), propertyExistsSQL($row, 'activeObserveAccepted'), isEmptyProperty($row, 'activeObserveAccepted'), $existsActiveObserveTime, $activeObserveTime, propertyExistsSQL($row, 'departureResultFlags'), isEmptyProperty($row, 'departureResultFlags'), propertyExistsSQL($row, 'unsuccessfulDeparture'), isEmptyProperty($row, 'unsuccessfulDeparture'), propertyExistsSQL($row, 'unsuccessfulDepartureText'), isEmptyProperty($row, 'unsuccessfulDepartureText'), propertyExistsSQL($row, 'resident'), isEmptyProperty($row, 'resident'), propertyExistsSQL($row, 'death'), isEmptyProperty($row, 'death'), propertyExistsSQL($row, 'reasonRefuse'), isEmptyProperty($row, 'reasonRefuse'), propertyExistsSQL($row, 'birth'), isEmptyProperty($row, 'birth'), propertyExistsSQL($row, 'careResult'), isEmptyProperty($row, 'careResult'), propertyExistsSQL($row, 'departureResult'), isUndefinedProperty($row, 'departureResult'), propertyExistsSQL($row, 'cardDone'), isUndefinedProperty($row, 'cardDone'), propertyExistsSQL($row, 'kilometers'), isUndefinedProperty($row, 'kilometers'), propertyExistsSQL($row, 'countryCode'), isEmptyProperty($row, 'countryCode'), propertyExistsSQL($row, 'codeArea'), isEmptyProperty($row, 'codeArea'), propertyExistsSQL($row, 'codeDistrict'), isEmptyProperty($row, 'codeDistrict'), $row->id ]); $this->createNotice($row->id); med_ambulance_logStats::updateMedEmergency($db, $row->id, false); } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } private function updateLogDelivery($record){ +$existsDeliveryTime = propertyExistsSQL($record, 'deliveryTime') && propertyExistsSQL($record, 'deliveryDate'); $deliveryTime = null; if ($existsDeliveryTime){ $deliveryTime = $this->createDateTimeField(isEmptyProperty($record, 'deliveryDate'), isEmptyProperty($record, 'deliveryTime')); } $sql = <<<"EOT"
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
executeSql($this->db, $sql, [ isUndefinedProperty($record, 'id'), isUndefinedProperty($record, 'whereTo'), $deliveryTime, isEmptyProperty($record, 'deliveryEnterpriseId'), isUndefinedProperty($record, 'receiptDate'), isUndefinedProperty($record, 'diagnosisId'), isUndefinedProperty($record, 'dischargeTime'), isUndefinedProperty($record, 'exodus'), isUndefinedProperty($record, 'diffInDiagnosis'), isUndefinedProperty($record, 'deliveryWhom'), isUndefinedProperty($record, 'hospitalized'), propertyExistsSQL($record, 'whereTo'), $existsDeliveryTime, propertyExistsSQL($record, 'deliveryEnterpriseId'), propertyExistsSQL($record, 'receiptDate'), propertyExistsSQL($record, 'diagnosisId'), propertyExistsSQL($record, 'dischargeTime'), propertyExistsSQL($record, 'exodus'), propertyExistsSQL($record, 'diffInDiagnosis'), propertyExistsSQL($record, 'deliveryWhom'), propertyExistsSQL($record, 'hospitalized') ]); } public function createNotice($sourceId){ $notice = new stdClass(); $userId = getSessionValue('mapUserCode'); $notice->type = self::NOTICE_UPDATE_TYPE; $notice->source = (string)$sourceId; $notice->validTime = new DateTime('now +'.self::NOTIFICATION_LIFE_LENGTH.' seconds'); $notice->userCodeAuthor = $userId; $data = new stdClass(); $data->ambLogId = $sourceId; $notice->data = $data; common_notices::createNotice($notice); } private function createDateTimeField($date, $time){ if (empty($date) && empty($time)){ return null; } $_date = new DateTime(empty($date) ? '19000101' : $date); $_time = new DateTime(empty($time) ? '00:00:00' : $time); return date($_date->format('Y-m-d') . ' ' . $_time->format('H:i:s')); } public function getDoctor(){ $db = $this->db ?: $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; return selectSql($db, <<<"EOT"
SELECT
    MD.MedDoctorCode "id",
    FN_cmFIO(MD.ManCode)::text "fio"
FROM MedDoctor MD
WHERE MD.MedDoctorCode = :doctorId
EOT,
[':doctorId' => $doctorId]); } public function getTeamNumber($params = null){ $db = $this->db ?: $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $dateStr = "'NOW'::DATE"; $dateTimeStr = "'now'"; $where = "WHERE gp.medDoctorCode = :doctorId AND
        (
            (G.onDate = {$dateStr} and
              (
                (S.finishTime > S.startTime and (G.onDate::date + S.finishTime::time) > {$dateTimeStr})
                or
                (S.finishTime < S.startTime and (G.onDate::date + S.startTime::time) < {$dateTimeStr})
              )
            )
            or
            (G.onDate = ({$dateStr} - '1 day'::interval) and
            S.finishTime < S.startTime and ({$dateStr} + S.finishTime::time) > {$dateTimeStr})
        )"; $sqlParams = [ ':doctorId' => $doctorId ]; return selectSql($db, <<<"EOT"
SELECT t.teamnumber,
       trim(m.name || ' ' || m.secondname || ' ' || m.surname) "fioUserDoctor"
FROM ambGraphPerson gp
LEFT JOIN ambGraph g ON g.ambGraphCode = gp.ambGraphCode
LEFT JOIN ambTeam t ON t.ambTeamCode = g.ambTeamCode
LEFT JOIN ambShift s ON s.ambShiftCode = g.ambShiftCode
LEFT JOIN meddoctor md ON md.meddoctorcode = gp.meddoctorcode
LEFT JOIN man m ON m.mancode = md.mancode
$where
EOT,
$sqlParams); } public function getHeaderInfo(){ $doctor = $this->getDoctor()[0]; $result = new stdClass(); $result->doctorFio = $doctor['fio']; $result->teamNumber = $this->getTeamNumber(); return $result; } } handleJsonRpc('med_ambulancemobile_calls');