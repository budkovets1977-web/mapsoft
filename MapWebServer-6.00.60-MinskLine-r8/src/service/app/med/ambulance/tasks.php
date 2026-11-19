<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_tasks { const NOTICE_CLOSE_TIMEOUT = 5 * 60; private $db; public function readTasks($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $patientSql = empty($patientId) ? '' : "and T.medPatientCode = {$patientId}"; $userId = isEmptyProperty($params, 'userId'); $userSql = empty($userId) ? '' : "and T.userCode = {$userId}"; $appelType = isEmptyProperty($params, 'appealType'); $appelTypeSql = empty($appelType) ? '' : "and T.appealType = {$appelType}"; $offices = isEmptyProperty($params,'offices'); $officesSql = empty($offices) ? '' : "and T.enterprisestructurecode = {$offices}"; $sql = <<< "EOT"
select
    T.ambTaskCode                           "id",
    T.startTime::date                       "startDate",
    T.startTime::time                       "startTime",
    CASE
        WHEN T.finishTime = 30000101 THEN null
        ELSE T.finishTime
    END::date as                            "finishDate",
    CASE
        WHEN T.finishTime = 30000101 THEN null
        ELSE T.finishTime
    END::time as                            "finishTime",
    T.appealType                            "appealType",
    T.weekDayMask                           "weekDayMask",
    T.warnMinutes                           "warnMinutes",
    T.transFrom                             "transFrom",
    T.transTo                               "transTo",
    T.userCode                              "userId",
    U.nick                                  "user",
    T.timeTable                             "timeTable",
    T.repeat                                "repeat",
    T.note                                  "note",
    T.medPatientCode                        "patientId",
    FN_cmFIO(P.ManCode, 0)                  "fio",
    FN_cmCalcAddress(P.ManCode, 5)::text as "address",
    T.enterprisestructurecode               "enterpriseStructureZoneId",
    ESI.name                                "officesName",
    T.address                               "addressNotPatient",
    T.dateofbirth                           "dateOfBirthNotPatient",
    T.fio                                   "fioNotPatient",
    T.flat                                  "kv",
    T.phone                                 "phone",
    T.sex                                   "sex"     
from AmbTask T
left join Users U ON U.userCode = T.userCode
left join MedPatient P on P.medPatientCode = T.medPatientCode
left join ManPassport MP on MP.manCode = P.manCode
left join ManPhone PH on PH.manCode = MP.manCode and PH.phoneType = 3
left join EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = T.enterprisestructurecode
where (fn_WebUserAccessOffice(T.enterprisestructurecode) = 1)
{$patientSql}
{$userSql}
{$appelTypeSql}
{$officesSql}
order by 1 desc
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = []; while ($row = $stmt->fetch()) { $table = safe_json_decode($row['timeTable'], true); $timeTable = []; if (empty($table)) { $row['timeTable'] = null; } else { foreach ($table as $key => $value) { $item = new stdClass; $item->id = $key; $item->time = $value; $timeTable[] = $item; } $row['timeTable'] = $timeTable; } $ret[] = $row; } return $ret; } public function createTasks($records) { $db = $this->db ?: $this->db = connectDb(); $time_table = null; $sql = <<<"EOT"
insert into AmbTask
    (appealType, enterpriseStructureCode, finishTime, medPatientCode,
     note, repeat, startTime,timeTable, usercode,
     warnMinutes, weekDayMask, transFrom, transTo, address, dateofbirth, fio, flat, phone, sex)
values
    (?, ?, coalesce(?,'30000101')::date + coalesce(?,'00:00')::time, ?,
     ?, ?, ?::date + coalesce(?,'00:00')::time, ?, ?,
     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if (isUndefinedProperty($row, 'timeTable')) { $table = new stdClass; foreach ($row->timeTable as $item) { $table->{$item->id} = $item->time; } $time_table = json_encode($table); } $stmt->execute([ isUndefinedProperty($row, 'appealType'), isUndefinedProperty($row,'enterpriseStructureZoneId'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'finishTime'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'repeat'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'startTime'), $time_table, getSessionValue('mapUserCode'), isUndefinedProperty($row, 'warnMinutes'), isUndefinedProperty($row, 'weekDayMask'), isUndefinedProperty($row, 'transFrom'), isUndefinedProperty($row, 'transTo'), isUndefinedProperty($row,'addressNotPatient'), isUndefinedProperty($row,'dateOfBirthNotPatient'), isUndefinedProperty($row,'fioNotPatient'), isUndefinedProperty($row,'kv'), isUndefinedProperty($row,'phone'), isUndefinedProperty($row,'sex') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_AmbTask'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateTasks($records) { $db = $this->db ?: $this->db = connectDb(); $time_table = null; $sql = <<<"EOT"
update AmbTask set
    appealType = case when ? = 1 then ? else appealType end,
    changeTime = LOCALTIMESTAMP,
    finishTime = case when ? = 1 then
        (case when ? = 1 then ? else finishTime end)::time
        +
        (case when ? = 1 then ? else finishTime end)::date
    else
    finishTime
    end,
    medPatientCode = case when ? = 1 then ? else medPatientCode end,
    note = case when ? = 1 then ? else note end,
    repeat = case when ? = 1 then ? else repeat end,
    startTime = case when ? = 1 then
        (case when ? = 1 then ? else startTime end)::time
        +
        (case when ? = 1 then ? else startTime end)::date
    else
    startTime
    end,
    timeTable = case when ? = 1 then ? else timeTable end,
    usercode = ?,
    warnMinutes = case when ? = 1 then ? else warnMinutes end,
    weekDayMask = case when ? = 1 then ? else weekDayMask end,
    transFrom = case when ? = 1 then ? else transFrom end,
    transTo = case when ? = 1 then ? else transTo end,
    enterprisestructurecode = case when ? = 1 then ? else enterprisestructurecode end,
    address = case when ? = 1 then ? else address end,
    dateofbirth = case when ? = 1 then ? else dateofbirth end,
    fio = case when ? = 1 then ? else fio end,
    flat = case when ? = 1 then ? else flat end,
    phone = case when ? = 1 then ? else phone end,
    sex = case when ? = 1 then ? else sex end
where AmbTaskCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (propertyExistsSQL($row, 'timeTable')) { $table = new stdClass; foreach ($row->timeTable as $item) { $table->{$item->id} = $item->time; } $time_table = json_encode($table); } $stmt->execute([ propertyExistsSQL($row, 'appealType'), isUndefinedProperty($row, 'appealType'), propertyExistsSQL($row, 'finishTime') || propertyExistsSQL($row, 'finishDate'), propertyExistsSQL($row, 'finishTime'), isEmptyProperty($row, 'finishTime'), propertyExistsSQL($row, 'finishDate'), isEmptyProperty($row, 'finishDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'repeat'), isUndefinedProperty($row, 'repeat'), propertyExistsSQL($row, 'startTime') || propertyExistsSQL($row, 'startDate'), propertyExistsSQL($row, 'startTime'), isEmptyProperty($row, 'startTime'), propertyExistsSQL($row, 'startDate'), isEmptyProperty($row, 'startDate'), propertyExistsSQL($row, 'timeTable'), $time_table, getSessionValue('mapUserCode'), propertyExistsSQL($row, 'warnMinutes'), isUndefinedProperty($row, 'warnMinutes'), propertyExistsSQL($row, 'weekDayMask'), isUndefinedProperty($row, 'weekDayMask'), propertyExistsSQL($row, 'transFrom'), isUndefinedProperty($row, 'transFrom'), propertyExistsSQL($row, 'transTo'), isUndefinedProperty($row, 'transTo'), property_exists($row,'enterpriseStructureZoneId'), isUndefinedProperty($row,'enterpriseStructureZoneId'), propertyExistsSQL($row,'addressNotPatient'), isUndefinedProperty($row,'addressNotPatient'), propertyExistsSQL($row,'dateOfBirthNotPatient'), isUndefinedProperty($row,'dateOfBirthNotPatient'), propertyExistsSQL($row,'fioNotPatient'), isUndefinedProperty($row,'fioNotPatient'), propertyExistsSQL($row,'kv'), isUndefinedProperty($row,'kv'), propertyExistsSQL($row,'phone'), isUndefinedProperty($row,'phone'), propertyExistsSQL($row,'sex'), isUndefinedProperty($row,'sex'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteTasks($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'AmbTask', 'AmbTaskCode', $records); } public function deleteNotice($ambId) { $db = $this->db ?: $this->db = connectDb(); $user = getSessionValue('mapUserCode', getSessionValue('user')); $noticeId = $this->findNotice($ambId); if (count($noticeId)) { try { $timeoutClose = self::NOTICE_CLOSE_TIMEOUT; $sqlUpdate = <<<"SQL"
update sysNotice set Active = null
where sysNoticeCode = ?;
SQL;
$sqlInsert = <<<"SQL"
insert into sysNotice (Type, NoticeTime, ValidTime, ParentCode, Source,
                    UserCodeAuthor, ProcessedUsers, EnterpriseCode, Active)
select
    'system-close-notice',
    now(),
    now() + '$timeoutClose seconds'::interval,
    ?,
    ?,
    $user, null, enterprisecode, 1
from sysnotice
where sysnoticecode = ?;
SQL;
$stmt = $db->prepare($sqlUpdate); $stmt2 = $db->prepare($sqlInsert); $db->beginTransaction(); foreach ($noticeId as $row) { $id = $row['id']; if ($id) { $stmt->execute([ $id ]); $stmt2->execute([ $id, $id, $id, ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } return true; } public function findNotice($ambId) { $db = $this->db ?: $this->db = connectDb(); $noticeId = false; if ($ambId && (is_int($ambId))) { $sql = <<<"EOT"
SELECT
    sysnoticecode "id"
FROM 
sysnotice
WHERE 
source = $ambId::text
AND type =  'ambulance-new-task'
EOT;
$noticeId = selectSql($db,$sql); } return $noticeId; } } handleJsonRpc('med_ambulance_tasks'); 