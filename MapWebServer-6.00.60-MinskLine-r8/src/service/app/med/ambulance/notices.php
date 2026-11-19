<?php
 class med_ambulance_notices extends common_notices { const NOTICE_TEMPLATES = [ 'ambulance-update-call' => [ 'tasks' => 'ambulance', 'persist' => false, 'widget' => 'med-ambulance-log' ], 'ambulance-new-call' => [ 'tasks' => 'ambulancemobile', 'persist' => true, 'title' => 'Новый вызов', 'confirmText' => 'Открыть вызов', 'cancelText' => 'Прочитано', 'widget' => 'med-ambulancemobile-patient', 'tpl' => [ '<div>Назначен новый <tpl if="priority == 2">экстренный<tpl else>неотложный</tpl> вызов №{cardNumber}.</div>' ] ], 'ambulance-new-task' => [ 'tasks' => 'ambulance', 'persist' => true, 'title' => 'Новое задание', 'confirmText' => 'Создать вызов', 'cancelText' => 'Отмена', 'checkMethod'=> 'checkAmbulanceNewTask', 'widget' => 'med-ambulance-logedit', 'tpl' => [ '<div>Необходимо назначить бригаду на <span class="attention">{startTime:date("H:i")}</span>.</div>', '<table class="fieldset">', '<tr><td>Адрес:</td><td>{address}</td></tr>', '<tr><td>ФИО:</td><td>{fio}</td></tr>', '<tr><td>Обращение:</td><td><tpl if="appealType == 1">обезболивание<tpl else>транспортировка</tpl></td></tr>', '<tpl if="note">', '<tr><td>Примечание:</td><td>{note}</td></tr>', '</tpl>', '</table>' ] ], ]; public static function checkAmbulanceNewTask() { $db = connectDb(); $noticeType = 'ambulance-new-task'; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $tasks = selectSql($db, <<<"SQL"
-- однократные
    SELECT
        A.ambTaskCode "ambTaskCode",
        A.MedPatientCode "patientId",
        MP.NumberHistorySickness "cardNumber",
        (case when A.fio IS NOT NULL then A.fio else FN_cmFIO(MP.ManCode) end) "fio",
        (case when A.address IS NOT NULL then A.address else FN_cmCalcAddress(MP.ManCode) end)  "address",
        (case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end) "dateOfBirth",
        extract('year' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageYear",
        extract('month' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageMonth",
        extract('day' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageDay",
        (case when A.sex IS NOT NULL then A.sex else P.sex end) "sex",
        A.flat "kv",
        A.phone "phone",
        A.StartTime "startTime",
        A.AppealType "appealType",
        A.Note "note",
        A.StartTime - 'now'::timestamp "timeLeft",
        A.ambTaskCode::text "source",
        A.enterprisestructurecode "enterprisestructurecode"
    FROM
        ambTask A
        LEFT JOIN MedPatient MP ON MP.MedPatientCode = A.MedPatientCode
        LEFT JOIN ManPassport P ON P.ManCode = MP.ManCode
    WHERE

        (fn_WebUserAccessOffice(A.EnterpriseStructureCode) = 1)
        AND coalesce(A.repeat, 0) = 0
        AND A.FinishTime = '3000-01-01'::timestamp -- apply index for StartTime!
        AND A.StartTime BETWEEN 'now'::timestamp AND ('now'::timestamp + make_interval(mins => A.WarnMinutes))
        AND NOT EXISTS(SELECT 1 FROM sysNotice WHERE type = :type AND source = A.ambTaskCode::text AND (EnterpriseCode = :enterpriseCode))
-- многократные
UNION ALL
    SELECT
        A.ambTaskCode "ambTaskCode",
        A.MedPatientCode "patientId",
        MP.NumberHistorySickness "cardNumber",
       (case when A.fio IS NOT NULL then A.fio else FN_cmFIO(MP.ManCode) end) "fio",
        (case when A.address IS NOT NULL then A.address else FN_cmCalcAddress(MP.ManCode) end)  "address",
        (case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end) "dateOfBirth",
        extract('year' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageYear",
        extract('month' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageMonth",
        extract('day' from age('now'::date, case when A.dateofbirth IS NOT NULL then A.dateofbirth else P.DateOfBirth end)) "ageDay",
        (case when A.sex IS NOT NULL then A.sex else P.sex end) "sex",
        A.flat "kv",
        A.phone "phone",
        TT.StartTime "startTime",
        A.AppealType "appealType",
        A.Note "note",
        TT.StartTime - 'now'::timestamp "timeLeft",
        A.ambTaskCode::text || '-' || TT.StartTime "source",
        A.enterprisestructurecode "enterprisestructurecode"
    FROM
        ambTask A
        CROSS JOIN (VALUES ('now'::date), ('now'::date + '1 day'::interval)) DT(value) -- текущая и следующая дата
        CROSS JOIN LATERAL (SELECT DT.value + J.value::time FROM json_each_text(A.Timetable::json) J) TT(startTime)
        LEFT JOIN MedPatient MP ON MP.MedPatientCode = A.MedPatientCode
        LEFT JOIN ManPassport P ON P.ManCode = MP.ManCode
    WHERE
        (fn_WebUserAccessOffice(A.EnterpriseStructureCode) = 1)
        AND A.repeat = 1
        AND 'now'::timestamp BETWEEN (A.StartTime - make_interval(mins => A.WarnMinutes)) AND A.FinishTime
        AND (A.WeekDayMask & (1 << extract(dow from DT.value))) <> 0 -- дни недели
        AND TT.StartTime BETWEEN 'now'::timestamp AND ('now'::timestamp + make_interval(mins => A.WarnMinutes))
        AND NOT EXISTS(SELECT 1 FROM sysNotice WHERE type = :type AND source = (A.ambTaskCode::text || '-' || TT.StartTime) AND (EnterpriseCode = :enterpriseCode))
ORDER BY
    "timeLeft"
SQL
, [ ':type' => $noticeType, ':enterpriseCode' => $enterpriseCode ], PDO::FETCH_OBJ); if (empty($tasks)) { return; } foreach($tasks as $data) { $notice = new stdClass(); $notice->type = $noticeType; $notice->source = $data->source; unset($data->source); $notice->validTime = new DateTime($data->startTime); $notice->enterpriseCode = $enterpriseCode; $notice->singleton = 1; $notice->data = $data; self::createNotice($notice); } } }