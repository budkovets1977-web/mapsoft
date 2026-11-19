<?php
 require_once(__DIR__.'/../init.php'); class homevisit_visits { private $db; public function readVisits ($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isUndefinedProperty($params, 'doctorId', getSessionValue('mapMedDoctor')['MedDoctorCode']); $isSend = isUndefinedProperty($params, 'isSend', -1); switch ($isSend) { case 0: $isSendSql = ' and c.DoctorSendTime is null'; break; case 1: $isSendSql = ' and c.DoctorSendTime is not null'; break; default: $isSendSql = ''; } $isDone = isUndefinedProperty($params, 'isDone', -1); $doneSQL = 'select 1 '. 'from MedVisit v '. 'join MedVisitPatient vp on vp.MedVisitCode = v.MedVisitCode and vp.MedPatientCode = c.MedPatientCode and coalesce(vp.VisitPlace, 0) <> (0) '. 'where v.MedDoctorCode is not null and v.VisitDate = DATE(c.CallTime) limit 1'; switch ($isDone) { case 0: $isDoneSql = ' and not exists(' . $doneSQL . ')'; break; case 1: $isDoneSql = ' and exists(' . $doneSQL . ')'; break; default: $isDoneSql = ''; } $orderSql = isEmptyProperty($params, 'orderBy', $db->isSybase ? '12,8' : '12 NULLS FIRST, 8 NULLS FIRST'); $sql = $db->isSybase ? <<<"EOT"
select
    c.MedCallCode "id", --f1
    c.CallTime "callTime", --f2
    c.Motive "motive", --f3
    c.CallType "visitType", --f4
    c.MedPatientCode "patientId", --f5
    ifnull(c.MedPatientCode, c.FIO, fn_cmFio(p.ManCode)) "patientFio", --f6
    mp.DateOfBirth "patientBirthDate", --f7
    fn_cmCalcAddress(p.ManCode, 5, 0, 1) "patientAddress", --f8
    (select list(PhoneNumber)
        from ManPhone
        where ManCode = p.ManCode and PhoneType in (1, 3)) "patientPhones", --f9
    c.PhoneNumber "callPhone", --f10
    c.Note "note", --f11
    coalesce((select first 1
    from MedVisit v
    join MedVisitPatient vp on vp.MedVisitCode = v.MedVisitCode
        and vp.MedPatientCode = c.MedPatientCode
        and coalesce(vp.VisitPlace, 0) <> 0
    where v.MedDoctorCode = c.MedDoctorCode
        and v.VisitDate = date(c.CallTime)), 0) "done", --f12
    (select first 1 from MedPatientCard pc
    join MedPrivelege pr on pr.MedPrivelegeCode = pc.MedPrivelegeCode
        and coalesce(pr.PrivilegePercent, 0.0) <> 0.0
    where pc.MedPatientCode = p.MedPatientCode
        and ? between pc.StartDate and coalesce(pc.FinishDate, 30000101)) "patientBenefit", --f13
    c.DoctorSendTime AS "doctorSendTime" --f14
from MedCall c
join MedDoctor d on d.MedDoctorCode = c.MedDoctorCode
left outer join MedPatient p on p.MedPatientCode = c.MedPatientCode
left outer join ManPassport mp on mp.ManCode = p.ManCode
where c.EnterpriseCode = ?
  and date(c.CallTime) = ?
  and d.MedDoctorCode = ?
  {$isSendSql}
  {$isDoneSql}
-- для оптимизации postgre сортировку лучше сделать номерами order by coalesce(done, 0), patientAddress
order by {$orderSql}
EOT
: <<<"EOT"
SELECT c.MedCallCode AS "id",
 c.CallTime::timestamp(0) AS "callTime",
 c.Motive AS "motive",
 c.CallType AS "visitType",
 c.MedPatientCode AS "patientId",
 (CASE WHEN c.MedPatientCode IS NULL THEN c.FIO ELSE fn_cmFio(p.ManCode) END) AS "patientFio",
 mp.DateOfBirth AS "patientBirthDate",
 fn_cmCalcAddress(p.ManCode, 5, 0, 1) AS "patientAddress",
 (
  SELECT string_agg((PhoneNumber)::TEXT, ',')
  FROM ManPhone
  WHERE ManCode = p.ManCode AND PhoneType IN (1, 3)
  ) AS "patientPhones",
 c.PhoneNumber AS "callPhone",
 c.Note AS "note",
 coalesce((
   SELECT 1
   FROM MedVisit v
   JOIN MedVisitPatient vp ON vp.MedVisitCode = v.MedVisitCode AND vp.MedPatientCode = c.MedPatientCode AND coalesce(vp.VisitPlace, 0) <> (0)
   WHERE v.MedDoctorCode = c.MedDoctorCode AND v.VisitDate = DATE (c.CallTime) LIMIT 1
   ), 0) AS "done",
 (
  SELECT 1
  FROM MedPatientCard pc
  JOIN MedPrivelege pr ON pr.MedPrivelegeCode = pc.MedPrivelegeCode AND coalesce(pr.PrivilegePercent, 0.0) <> (0.0)
  WHERE pc.MedPatientCode = p.MedPatientCode AND ? /*0*/ BETWEEN pc.StartDate AND coalesce(pc.FinishDate, 30000101) LIMIT 1
  ) AS "patientBenefit",
  c.DoctorSendTime::timestamp(0) AS "doctorSendTime"
FROM MedCall c
JOIN MedDoctor d ON d.MedDoctorCode = c.MedDoctorCode
LEFT OUTER JOIN MedPatient p ON p.MedPatientCode = c.MedPatientCode
LEFT OUTER JOIN ManPassport mp ON mp.ManCode = p.ManCode
WHERE c.EnterpriseCode = ? /*1*/
  AND DATE (c.CallTime) = ? /*2*/
  AND d.MedDoctorCode = ? /*3*/
  {$isSendSql}
  {$isDoneSql}
ORDER BY {$orderSql}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'currentDate', date("Ymd")), getSessionValue('mapEnterpriseCode'), isEmptyProperty($params, 'currentDate', date("Ymd")), $doctorId ]); return $stmt->fetchAll(); } public function addVisitManual($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $enterpriseId = getSessionValue('mapEnterpriseCode'); $webUserId = getSessionValue('user'); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $res = null; $sql = <<<"EOT"
insert into medCall (enterpriseCode, medDoctorCode, medPatientCode, callType, callTime, doctorSendTime, webUserCode, flags)
values (?, ?, ?, ?, NOW(), NOW(), ?, ?)
EOT;
if ($patientId) { $stmt = $db->prepare($sql); $stmt->execute([$enterpriseId, $doctorId, $patientId, 3, $webUserId, 1]); $id = $db->lastInsertId(); if ($id) { $res = new \stdClass(); $res->id = $id; } } return $res; } public function readVisitsDoctors ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select distinct
    c.MedDoctorCode AS "id",
    FN_cmFIO(d.ManCode)  AS "name"
from MedCall c
join MedDoctor d ON d.MedDoctorCode = c.MedDoctorCode
where c.EnterpriseCode = ?
  and DATE(c.CallTime) = ?
  and not exists(
      select 1
      from MedVisit v
      join MedVisitPatient vp on vp.MedVisitCode = v.MedVisitCode and vp.MedPatientCode = c.MedPatientCode and coalesce(vp.VisitPlace, 0) <> (0)
      where v.MedDoctorCode = c.MedDoctorCode and v.VisitDate = DATE(c.CallTime) limit 1 )
ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode'), isEmptyProperty($params, 'currentDate', date("Ymd")) ]); return $stmt->fetchAll(); } public function assignVisits($params) { $db = $this->db ?: $this->db = connectDb(); $res = false; $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $idArr = isEmptyProperty($params, 'idArr', []); $idList = implode(',', $idArr); if (empty($idList)) { $idList = '-1'; } $sql = <<<"EOT"
update medCall set
   medDoctorCode = ?,
   doctorSendTime = NOW()
where medCallCode IN ({$idList})
EOT;
if (!empty($idArr)) { $stmt = $db->prepare($sql); $stmt->execute([$doctorId]); $res = true; } return $res; } } handleJsonRpc('homevisit_visits');