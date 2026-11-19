<?php
 require_once(__DIR__.'/../../init.php'); class med_clinicstats_mortality { private $db; public function readMortality($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $startDate = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? $params->startDate : null; $finishDate = isset($params->startDate) && isEmptyProperty($params, 'finishDate') ? $params->finishDate : null; $dateType = isset($params->dateType) && isEmptyProperty($params, 'dateType') ? $params->dateType : 0; $searchText = isset($params->searchText) && isEmptyProperty($params, 'searchText') ? $params->searchText : null; if (isset($searchText) && (trim($searchText)==='') ) { $searchText = null; } $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sqlFromWhere = "
from MedLogDeath AS mld
left outer join MedPatient AS mp ON mp.MedPatientCode = mld.MedPatientCode
left outer join MedDiagnosis AS md1 ON md1.MedDiagnosisCode = mld.MedDiagnosisCode
left outer join MedDiagnosis AS md2 ON md2.MedDiagnosisCode = mld.MedDiagnosisCodeDirect
left outer join UniDict AS ud ON ud.UniDictCode = mld.RegistrationPlace and DictNumber = 26
WHERE
    mp.EnterpriseCode = $enterpriseCode"; if (isset($searchText)) { $sqlFromWhere .= " and ( FN_cmFIO(MP.ManCode) like '%$searchText%'
                        or MP.NumberHistorySickness like '%$searchText%'
                        or FN_cmCalcAddress(MP.ManCode) like '%$searchText%'
                        or md1.Cipher1 like '%$searchText%'
                        or md1.Name like '%$searchText%'
                        or md2.Cipher1 like '%$searchText%'
                        or md2.Name like '%$searchText%' )"; } if (isset($startDate)) { if ($dateType == 0) { $sqlFromWhere .= " and mld.DeathDate >= $startDate"; } else { $sqlFromWhere .= " and mld.EndDate >= $startDate"; } } if (isset($finishDate)) { if ($dateType == 0) { $sqlFromWhere .= " and mld.DeathDate <= $finishDate"; } else { $sqlFromWhere .= " and mld.EndDate <= $finishDate"; } } if ($dateType == 0) { $sqlOrder = " order by mld.DeathDate desc, mld.MedLogDeathCode desc"; } else { $sqlOrder = " order by mld.EndDate desc, mld.MedLogDeathCode desc"; } $sql = <<<"EOT"
select
       COUNT(*) "total"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
select
    mld.MedLogDeathCode "id",
    mld.DeathDate "deathDate",
    mld.DeathPlace "deathPlace",
    mld.MedDiagnosisCode "diagnosisId",
    case
        when mld.MedDiagnosisCode is not null then cast((md1.Cipher1  || ' - ' || md1.Name) as varchar(250))
        else null
    END "diagnosisName",
    mld.MedDiagnosisCodeDirect "diagnosisIdDirect",
    case
        when mld.MedDiagnosisCodeDirect is not null then cast((md2.Cipher1  || ' - ' || md2.Name) as varchar(250))
        else null
    end "diagnosisNameDirect",
    mld.MedPatientCode "patientId",
    fn_cmFIO(mp.ManCode) "patientName",
    mld.RegistrationPlace "registrationPlace",
    ud.Cipher|| E' - ' ||ud.Value "registrationPlaceName",
    mld.EndDate "endDate",
    mld.DeathCertificateNumber "deathCertificateNumber"
$sqlFromWhere
$sqlOrder
limit $limit offset $offset
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function updateMortality($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedLogDeath set
    DeathDate = case when ? = 1 then ? else DeathDate end,
    DeathPlace = case when ? = 1 then ? else DeathPlace end,
    MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end,
    MedDiagnosisCodeDirect = case when ? = 1 then ? else MedDiagnosisCodeDirect end,
    MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
    RegistrationPlace = case when ? = 1 then ? else RegistrationPlace end,
    EndDate = case when ? = 1 then ? else EndDate end,
    DeathCertificateNumber = case when ? = 1 then ? else DeathCertificateNumber end
where MedLogDeathCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'deathDate'), isUndefinedProperty($row, 'deathDate'), propertyExistsSQL($row, 'deathPlace'), isUndefinedProperty($row, 'deathPlace'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'diagnosisIdDirect'), isUndefinedProperty($row, 'diagnosisIdDirect'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'registrationPlace'), isUndefinedProperty($row, 'registrationPlace'), propertyExistsSQL($row, 'endDate'), isUndefinedProperty($row, 'endDate'), propertyExistsSQL($row, 'deathCertificateNumber'), isEmptyProperty($row, 'deathCertificateNumber'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteMortality($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedLogDeath where MedLogDeathCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createMortality($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedLogDeath (DeathDate, DeathPlace, MedDiagnosisCode, MedDiagnosisCodeDirect, MedPatientCode, RegistrationPlace, EndDate, DeathCertificateNumber)
values (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlCard = <<<"EOT"
update MedPatientCard set
    FinishDate = DATEADD('day', -1, ?)
where MedPatientCode = ?
  and coalesce(FinishDate, 30000101) >= ?
EOT;
$sqlDispensary = <<<"EOT"
update MedDispensary set
    FinishDate = DATEADD('day', -1, ?),
    MotiveDiscard = 7,
    MedDiagnosisCodeDiscard = MedDiagnosisCode
where MedPatientCode = ?
  and coalesce(FinishDate, 30000101) = 30000101
EOT;
$sqlPatient = <<<"EOT"
update MedPatient set
    FinishDate = ?,
    OutReasonEx = 4
where MedPatientCode = ?
EOT;
$sqlDeregisterPatient= <<<"EOT"
update MedPatientRegistration set
    FinishDate = ?,
    OutReasonEx = 4
where MedPatientCode = ?
  and (COALESCE(FinishDate, 30000101)::DATE = 30000101 OR FinishDate > 'NOW'::DATE) 
EOT;
$stmt = $db->prepare($sql); $stmtCard = $db->prepare($sqlCard); $stmtDispensary = $db->prepare($sqlDispensary); $stmtPatient = $db->prepare($sqlPatient); $stmtDeregisterPatient = $db ->prepare($sqlDeregisterPatient); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'deathDate'), isUndefinedProperty($row, 'deathPlace'), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisIdDirect'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'registrationPlace'), isUndefinedProperty($row, 'endDate'), isEmptyProperty($row, 'deathCertificateNumber') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedLogDeath'); $stmtCard->execute([ isUndefinedProperty($row, 'endDate'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'endDate') ]); $stmtDispensary->execute([ isUndefinedProperty($row, 'endDate'), isUndefinedProperty($row, 'patientId') ]); $stmtPatient->execute([ isUndefinedProperty($row, 'endDate'), isUndefinedProperty($row, 'patientId') ]); $stmtDeregisterPatient->execute([ isUndefinedProperty($row, 'endDate'), isUndefinedProperty($row, 'patientId') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getPatientInfo($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $endDate = isset($params->endDate) && isEmptyProperty($params, 'endDate') ? $params->endDate : null; $ret = null; if (!$params || !$patientId || !$endDate) { return null; } $sql = <<<"EOT"
select
    mp.Name "name"
from MedPatientCard as mpc
join MedPrivelege as mp on mp.MedPrivelegeCode = mpc.MedPrivelegeCode
where mpc.MedPatientCode = $patientId
  and coalesce(FinishDate, 30000101) >= $endDate
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $resultCard = $stmt->fetchAll(); $sql = <<<"EOT"
select
    cast((md.Cipher1  || ' - ' || md.Name) as varchar(250)) "name"
from MedDispensary d
join MedDiagnosis AS md ON md.MedDiagnosisCode = d.MedDiagnosisCode
where d.MedPatientCode = $patientId
  and coalesce(d.FinishDate, 30000101) = 30000101
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $resultDispensary = $stmt->fetchAll(); if ($resultCard || $resultDispensary) { $ret = new stdClass(); if ($resultCard) { $ret->medPatientCard = $resultCard; } if ($resultDispensary) { $ret->medDispensary = $resultDispensary; } } return $ret; } } $service = new Junior\Server(new med_clinicstats_mortality()); $service->process();