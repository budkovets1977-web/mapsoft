<?php
 require_once(__DIR__ . '/../../init.php'); class med_clinicstats_ambulance { private $db; public function readAmbulance($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id') ? $params->id : null; $startDate = isEmptyProperty($params, 'startDate') ? $params->startDate : null; $finishDate = isEmptyProperty($params, 'finishDate') ? $params->finishDate : null; $priority = isEmptyProperty($params, 'priority') ? $params->priority : null; $sector = isEmptyProperty($params, 'sector') ? $params->sector : null; $asset = isEmptyProperty($params, 'asset') ? $params->asset : null; $confirm = isEmptyProperty($params, 'confirm') ? $params->confirm : null; $diagnosis = isEmptyProperty($params, 'diagnosisId') ? $params->diagnosisId : null; $patient = isEmptyProperty($params, 'patientId') ? $params->patientId : null; $isCard = isEmptyProperty($params, 'isCard'); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $noLimit = isEmptyProperty($params, 'noLimit'); $sqlLimit = "LIMIT $limit OFFSET $offset"; if ($noLimit) { $sqlLimit = ''; } $sort = isEmptyProperty($params, 'sort', '5,7,9'); $dir = isEmptyProperty($params, 'dir', ''); $providedHelpData = ""; $sqlFrom = "
from MedEmergency AS me
left join UniDict AS ud on ud.UniDictCode = me.Priority and ud.DictNumber = 22
left join MedPatient AS mp on mp.MedPatientCode = me.MedPatientCode
left join MedDiagnosis AS md on md.MedDiagnosisCode = me.MedDiagnosisCode
left join ManPassport AS pass on pass.ManCode = mp.ManCode
left join MedPatientPolyclinic p on p.MedPatientPolyclinicCode = mp.medPatientPolyclinicCode
left join MedPatientRegion r ON r.MedPatientRegionCode = p.MedPatientRegionCode
left join MedPatientGroup mpg ON mpg.MedPatientGroupCode = mp.MedPatientGroupCode
"; $sqlWhere = "where 1 = 1"; if (isset($id)) { $providedHelpData = <<<"EOT"
,
als.lowPressure "lowpressure",
als.highPressure "highpressure",
als.pulse "pulse",
als.heartRate "heartRate",
als.temperature "temperature",
al.departureResult "departureResult",

(select
    STRING_AGG (UDA.value, '; ')
from AmbLogAction ALA
join UniDict UDA on ALA.UniDictCodeAction = UDA.UniDictCode and UDA.DictNumber = 64
where AmbLogCode = me.AmbLogCode
group by AmbLogCode) "actions",

(select
    STRING_AGG (mwn.name0, '; ')
from AmbLogWealth ALW
join matWealthNames mwn on ALW.matWealthCode = mwn.matWealthCode
where AmbLogCode = me.AmbLogCode
group by AmbLogCode) "medicaments"
EOT;
$sqlFrom .= "
            left join AmbLogSheet als on me.AmbLogCode = als.AmbLogCode
            left join AmbLog al on me.AmbLogCode = al.AmbLogCode"; $sqlWhere .= " and me.MedEmergencyCode = $id"; } if (isset($startDate)) { $sqlWhere .= " and me.CallDate >= $startDate"; } if (isset($finishDate)) { $sqlWhere .= " and me.CallDate <= $finishDate"; } if (isset($priority)) { $sqlWhere .= " and me.Priority = $priority"; } if (isset($sector)) { $sqlWhere .= " and ( mp.MedPatientGroupCode = $sector or exists (select 1 from MedPatientAddGroup where MedPatientCode = mp.MedPatientCode and MedPatientGroupCode = $sector ))"; } if (isset($diagnosis)) { $sqlWhere .= " and me.MedDiagnosisCode = $diagnosis"; } if (isset($patient)) { $sqlWhere .= " and me.MedPatientCode = $patient"; } if (isset($asset)) { $sqlWhere .= " and COALESCE(me.asset,0) = 1"; } if (isset($confirm)) { $sqlWhere .= " and COALESCE(me.confirm,0) = $confirm%2"; } $sql = <<<"EOT"
select
       COUNT(*) "total"
$sqlFrom
$sqlWhere
EOT;
if (!$id) { $stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; } $cardData = !$isCard ? "" : <<<"EOT"
    ,21 "recordType",
    null "parentId",
    null "parentType",
    me.CallDate "parentDate",
    12 "sortType",
    me.MedEmergencyCode "recordId",
    me.CallDate "recordDate",
    me.CallDate "parentDate",
    COALESCE(me.MedEmergencyCode, '')::text || '-' "path"
EOT;
$sql = <<<"EOT"
select
    me.MedEmergencyCode "id",
    me.Priority "priority",
    me.Status "state",
    cast(ud.Cipher|| E'. ' ||ud.Value as varchar(250)) "priorityName",
    me.CallDate "callDate",
    CAST(CAST('2008-01-01' as DATE) + CAST(me.CallTime as TIME) AS TIMESTAMP) "callTime", /* специально выбираем дату 01.01.2008,т.к. компонент DateField на форме-редакторе всеравно устанавливает её такой */
    me.CallNumber "callNumber",
    me.MedPatientCode "patientId",
    me.DoctorViewed "doctorViewed",
    CASE me.MedPatientCode is not null WHEN true then fn_cmFio(mp.ManCode) WHEN false THEN me.PatientFIO END "patientName",
    CASE me.MedPatientCode is not null WHEN true then
    (CASE (datediffyear(pass.DateOfBirth, (me.CallDate ) ) ) > 0 WHEN true then
    cast(fn_cmGetMonthYearString((datediffyear(pass.DateOfBirth, (me.CallDate ) ) ) )|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN CASE (datediffmonth(pass.DateOfBirth, (me.CallDate ) ) ) > 0 WHEN true then
    cast(fn_cmGetMonthYearString(((datediffmonth(pass.DateOfBirth, (me.CallDate ) ) ) /100.00), 2)|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN CASE (datediff('day', pass.DateOfBirth, (me.CallDate ) ) ) > 0 WHEN true then
    cast(fn_cmGetDayString((datediff('day', pass.DateOfBirth, (me.CallDate ) ) ) )|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN cast( E'(' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(12))
    END END END )
    WHEN false THEN me.PatientAge END "patientAge",
    CASE me.MedPatientCode is not null WHEN true then FN_cmCalcAddress(mp.ManCode, 5)::text WHEN false THEN me.PatientAddress END "patientAddress",
    me.patientFIO "patientText",
    me.MedDiagnosisCode "diagnosisId",
    md.Cipher1 "diagnosisCipher",
    CASE me.MedPatientCode is not null WHEN true THEN md.Name WHEN false THEN me.diagnosis END "diagnosisName",
    me.diagnosis "diagnosisText",
    me.Asset "asset",
    CASE (p.MedPatientPolyclinicCode IS NOT NULL)
        WHEN true THEN p.Name +  E' (' + r.Name +  E')'
        WHEN false THEN NULL
    END "patientPolyclinicName",
    mpg.Name "patientGroupName",
    me.Region "region",
    COALESCE(me.VisitZone, CASE me.medPatientCode IS NULL WHEN true THEN 0 WHEN false THEN (SELECT MAX(tpg.VisitZone) FROM MedPatientGroup AS tpg WHERE tpg.MedPatientGroupCode = mp.MedPatientGroupCode or tpg.MedPatientGroupCode IN (SELECT MedPatientGroupCode FROM MedPatientAddGroup WHERE MedPatientCode = mp.MedPatientCode) )  END ) "visitZone",
    COALESCE(me.Chronic,0) "chronic",
    COALESCE(me.Confirm,0) "confirm",
    me.Status "status"
    $cardData
    $providedHelpData
$sqlFrom
$sqlWhere
order by $sort $dir
$sqlLimit
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($isCard) { if ($id) { $root = new stdClass(); $root->items = $stmt->fetch(); return $root; } return $stmt->fetchAll(); } if ($id) { $row = $stmt->fetch(); $root = new stdClass(); $root->id = $row['id']; $root->patientAge = $row['patientAge']; $root->priorityName = $row['priorityName']; $root->diagnosisCipher = $row['diagnosisCipher']; $root->diagnosisName = $row['diagnosisName']; return $root; } else { $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } } public function updateAmbulance($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedEmergency set
    Priority = case when ? = 1 then ? else Priority end,
    CallDate = case when ? = 1 then ? else CallDate end,
    CallTime = case when ? = 1 then CAST(CAST('1900-01-01' as DATE) + CAST(? as TIME) AS TIMESTAMP) else CallTime end, /* дату всегда ставим 01.01.1900 */
    CallNumber = case when ? = 1 then ? else CallNumber end,
    MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
    MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end,
    Region = case when ? = 1 then ? else Region end,
    VisitZone = case when ? = 1 then ? else VisitZone end,
    Chronic = case when ? = 1 then ? else Chronic end,
    Confirm = case when ? = 1 then ? else Confirm end,
    DoctorViewed = case when ? = 1 then ? else DoctorViewed end
where MedEmergencyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'priority'), isUndefinedProperty($row, 'priority'), propertyExistsSQL($row, 'callDate'), isUndefinedProperty($row, 'callDate'), propertyExistsSQL($row, 'callTime'), isUndefinedProperty($row, 'callTime'), propertyExistsSQL($row, 'callNumber'), isUndefinedProperty($row, 'callNumber'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'region'), isEmptyProperty($row, 'region'), propertyExistsSQL($row, 'visitZone'), isUndefinedProperty($row, 'visitZone'), propertyExistsSQL($row, 'chronic'), isEmptyProperty($row, 'chronic'), propertyExistsSQL($row, 'confirm'), isEmptyProperty($row, 'confirm'), propertyExistsSQL($row, 'doctorViewed'), isEmptyProperty($row, 'doctorViewed'), $row->id ]); $param = new stdClass(); $param->id = $row->id; $ret[] = $this->readAmbulance($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteAmbulance($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedEmergency where MedEmergencyCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createAmbulance($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedEmergency (Priority, CallDate, CallTime, CallNumber, MedPatientCode, MedDiagnosisCode, Region, VisitZone, Chronic, Confirm)
values (?, ?, CAST(CAST('1900-01-01' as DATE) + CAST(? as TIME) AS TIMESTAMP), ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'priority'), isUndefinedProperty($row, 'callDate'), isUndefinedProperty($row, 'callTime'), isUndefinedProperty($row, 'callNumber'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'diagnosisId'), isEmptyProperty($row, 'region'), isUndefinedProperty($row, 'visitZone'), isEmptyProperty($row, 'chronic'), isEmptyProperty($row, 'confirm') ]); $param = new stdClass(); $param->id = $db->lastInsertId('seq_MedEmergency'); $ret[] = $this->readAmbulance($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getPatientInfo($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $onDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate', Date('Ymd')))); $sql = <<<"EOT"
select
    mpp.Name "polyclinicName",
    mpg.Name "groupName",
    (CASE (datediffyear(pass.DateOfBirth, $onDate::date ) ) > 0 WHEN true then
    cast(fn_cmGetMonthYearString((datediffyear(pass.DateOfBirth, $onDate::date ) ) )|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN CASE (datediffmonth(pass.DateOfBirth, $onDate::date ) ) > 0 WHEN true then
    cast(fn_cmGetMonthYearString(((datediffmonth(pass.DateOfBirth, $onDate::date ) ) /100.00), 2)|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN CASE (datediff('day', pass.DateOfBirth, $onDate::date ) ) > 0 WHEN true then
    cast(fn_cmGetDayString((datediff('day', pass.DateOfBirth, $onDate::date ) ) )|| E' (' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(250))
    WHEN false THEN cast( E'(' ||dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' )|| E')' as varchar(12))
    END END END ) "age",
    (select MAX(pg.VisitZone)
     from MedPatientGroup pg
     where pg.MedPatientGroupCode = mp.MedPatientGroupCode
        or pg.MedPatientGroupCode IN (SELECT MedPatientGroupCode FROM MedPatientAddGroup WHERE MedPatientCode = mp.MedPatientCode)) "visitZone"
from MedPatient mp
join ManPassport pass ON pass.ManCode = MP.ManCode
left join MedPatientPolyclinic mpp ON mpp.MedPatientPolyclinicCode = mp.MedPatientPolyclinicCode
left join MedPatientGroup mpg ON mpg.MedPatientGroupCode = mp.MedPatientGroupCode
where mp.MedPatientCode = $patientId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetch(); return $ret; } public function getChronicByDiagnosisId($params) { $db = $this->db ?: $this->db = connectDb(); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $patientId = isEmptyProperty($params, 'patientId'); $callDateYear = isEmptyProperty($params, 'callDateYear', Date('Y')); $default = isEmptyProperty($params, 'default'); $callDateYearPrev = $callDateYear - 1; $sql = <<<"EOT"
select 1 AS "res"
from MedVisitPatient mvp
join MedVisit mv ON mv.medVisitCode = mvp.medVisitCode
where mvp.MedPatientCode = $patientId
  and YEAR(mv.VisitDate) IN ($callDateYearPrev, $callDateYear)
  and mvp.MedDiagnosisCode = $diagnosisId
  and COALESCE(mvp.VisitCase,0) = 1
  and COALESCE(mvp.SicknessKind,0) IN (2,3,4)
  and COALESCE(mvp.VisitEnd,0) = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $res = fetchValue($stmt, 'res'); return $res ? $res : $default; } public function updateConfirmFlag($params = null) { $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate') ? $params->startDate : null; $finishDate = isEmptyProperty($params, 'finishDate') ? $params->finishDate : null; $priority = isEmptyProperty($params, 'priority') ? $params->priority : null; $sector = isEmptyProperty($params, 'sector') ? $params->sector : null; $asset = isEmptyProperty($params, 'asset') ? $params->asset : null; $confirm = isEmptyProperty($params, 'confirm') ? $params->confirm : null; $diagnosis = isEmptyProperty($params, 'diagnosisId') ? $params->diagnosisId : null; $patient = isEmptyProperty($params, 'patientId') ? $params->patientId : null; $sqlWhere = ""; if (isset($startDate)) { $sqlWhere .= " and me.CallDate >= $startDate"; } if (isset($finishDate)) { $sqlWhere .= " and me.CallDate <= $finishDate"; } if (isset($priority)) { $sqlWhere .= " and me.Priority = $priority"; } if (isset($sector)) { $sqlWhere .= " and ( mp.MedPatientGroupCode = $sector or exists (select 1 from MedPatientAddGroup where MedPatientCode = mp.MedPatientCode and MedPatientGroupCode = $sector ))"; } if (isset($diagnosis)) { $sqlWhere .= " and me.MedDiagnosisCode = $diagnosis"; } if (isset($patient)) { $sqlWhere .= " and me.MedPatientCode = $patient"; } if (isset($asset)) { $sqlWhere .= " and COALESCE(me.asset,0) = 1"; } if (isset($confirm)) { $sqlWhere .= " and COALESCE(me.confirm,0) = $confirm%2"; } $sql = <<<"EOT"
update medemergency AS tme set
    confirm = 1
from medemergency AS me
left join medpatient AS mp ON mp.MedPatientCode = me.medPatientCode
where tme.medemergencycode = me.medemergencycode
  and COALESCE(me.confirm,0) <> 1
$sqlWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } if (!isset($notExecute)) { handleJsonRpc('med_clinicstats_ambulance'); }