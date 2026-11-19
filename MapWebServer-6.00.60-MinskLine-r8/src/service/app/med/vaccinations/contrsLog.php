<?php
 require_once(__DIR__.'/../../init.php'); class med_vaccinations_contrsLog { const MAX_PAGE_SIZE = 30; private $db; public function readContrsLog($params = null, $recordIds = null, $newRecordsIds = null) { $db = $this->db ?: $this->db = connectDb(); $sector = isEmptyProperty($params, 'sectorId'); $patient = isEmptyProperty($params, 'patientId'); $inoculateGroupId = isEmptyProperty($params, 'inoculateGroupId'); $beginDate = isEmptyProperty($params, 'beginDate'); $isCard = isEmptyProperty($params, 'isCard'); $id = isEmptyProperty($params, 'id'); $endDate = isEmptyProperty($params, 'endDate'); $ageFormat = "(CASE (DATEDIFFYEAR(mps.DateOfBirth, CURRENT_DATE)) > 0
             WHEN true THEN FN_cmGetMonthYearString((DATEDIFFYEAR(mps.DateOfBirth, CURRENT_DATE)))::text
             WHEN false THEN CASE (DATEDIFFMONTH(mps.DateOfBirth, CURRENT_DATE)) > 0
                                  WHEN true then FN_cmGetMonthYearString(((DATEDIFFMONTH(mps.DateOfBirth, CURRENT_DATE)) / 100.00), 2)::text
                                  WHEN false THEN CASE (DATEDIFF('day', mps.DateOfBirth, CURRENT_DATE)) > 0
                                                       WHEN true THEN FN_cmGetDayString((DATEDIFF('day', mps.DateOfBirth, CURRENT_DATE)))::text
                                                  END
                             END
        END)"; if (isset($isCard)) { $cardSelect = <<<"EOT"
    ,11 "recordType",
    null "parentId",
    null "parentType",
    9 "sortType"
EOT;
} else { $cardSelect = <<<"EOT"
EOT;
} $sqlFromWhere = "
from medpatientcontr mpc
LEFT JOIN medPatient as mp ON mp.medpatientcode = mpc.medpatientcode
LEFT JOIN manPassport as mps ON mps.mancode = mp.mancode
LEFT JOIN medDiagnosis as mds ON mds.medDiagnosisCode = mpc.medDiagnosisCode
LEFT JOIN medDoctor as md ON md.meddoctorcode = mpc.meddoctorcode
LEFT JOIN medinoculatecontr as mic ON mic.medinoculatecontrcode = mpc.medinoculatecontrcode
LEFT JOIN medinoculategroup as mig ON mig.medinoculategroupcode = mpc.medinoculategroupcode
LEFT JOIN medProfession as mpr ON mpr.medprofessioncode = md.medprofessioncode
WHERE 1=1"; $condInoculate = isset($inoculateGroupId) ? "HAVING string_agg(mpc.medInoculateGroupCode, ',') LIKE '%,$inoculateGroupId,%' " . "OR string_agg(mpc.medInoculateGroupCode, ',') LIKE '%,$inoculateGroupId' " . "OR string_agg(mpc.medInoculateGroupCode, ',') LIKE '$inoculateGroupId,%'" . "OR string_agg(mpc.medInoculateGroupCode, ',') LIKE '$inoculateGroupId'" : ""; if (isset($sector)) { $sqlFromWhere .= " and mp.MedPatientGroupCode = $sector"; } if (isset($patient)) { $sqlFromWhere .= " and mpc.medPatientCode = $patient"; } if ($beginDate && $id) { $sqlFromWhere .= " and mpc.startDate = $beginDate"; } if ($endDate && $id) { $sqlFromWhere .= " and mpc.finishDate = $endDate"; } if (!$id && ($endDate || $beginDate)) { $beginDate = $beginDate ? $beginDate : '19000101'; $endDate = $endDate ? $endDate : '30000101'; $sqlFromWhere .= " and (FN_cmmin(coalesce(mpc.finishDate,'30000101'::date),$endDate) - FN_cmmax(mpc.startDate, $beginDate)) >= 0  "; } if (isset($recordIds)) { $in = createSqlInCondition($recordIds); $sqlFromWhere .= " and medpatientcontrcode $in"; } if (isset($newRecordsIds)) { $in = createSqlInCondition($newRecordsIds); $sqlFromWhere .= " OR medpatientcontrcode $in"; } $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $sqlTotal = <<<"EOT"
SELECT
       COUNT(*) "total"
FROM (SELECT
    mpc.medPatientCode,
    mpc.startDate,
    mpc.finishDate,
    mpc.medDiagnosisCode,
    mpc.decisionDate,
    mpc.note,
    mpc.medDoctorCode,
    mpc.medInoculateContrCode
    $sqlFromWhere
    group by 1,2,3,4,5,6,7,8
    $condInoculate) AS t
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql = <<< "EOT"
select
mpc.medPatientCode as "patientId",
mpc.startDate as "startDate",
mpc.finishDate as "finishDate",
mpc.medDiagnosisCode as "diagnosisId",
mpc.decisionDate as "decisionDate",
mpc.note as "note",
mpc.medDoctorCode as "doctorId",
mpr.name as "doctorProfession",
mpc.medInoculateContrCode as "disclaimerReasonsId",
FN_cmFIO(mp.ManCode)::text as "patientFio",
mps.dateOfBirth as "dateOfBirth",
$ageFormat as "ageFormat",
FN_cmCalcAddress(mp.ManCode, 5)::text as "addressDisplay",
FN_cmFIO(md.ManCode)::text as "doctor",
mds.name as "diagnosis",
mds.cipher1 as "diagnosisCipher",
mic.name as "disclaimerReasons",
string_agg(mpc.medPatientContrCode, ',') as "arrayIds",
string_agg(mpc.medInoculateGroupCode, ',') as "inoculateGroupIds",
string_agg(mig.name, ',') as "inoculateGroups"
$cardSelect
$sqlFromWhere
group by 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17
$condInoculate
order by 10 ASC
limit $limit offset $start
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); if (isset($isCard)) { foreach ($root->items as $key => $item) { $item['recordId'] = $item['disclaimerReasonsId']; $item['recordDate'] = $item['startDate']; $item['parentDate'] = $item['startDate']; $item['path'] = (string) $item['disclaimerReasonsId']; $item['path'] .= '-'; $root->items[$key] = $item; } return $root->items; } return $root; } public function createContrsLog($records, $isUpdate = false) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
insert into medPatientContr (medPatientCode, medInoculateGroupCode, startDate, finishDate, medDiagnosisCode, decisionDate, note, medDoctorCode, medInoculateContrCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $inoculateGroups = isEmptyProperty($row, 'inoculateGroupIds'); foreach ($inoculateGroups as $inoculateGroup) { $stmt->execute([ isUndefinedProperty($row, 'patientId'), $inoculateGroup, isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'decisionDate'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'disclaimerReasonsId') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedPatientContr'); $ret[] = $rec; } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if ($isUpdate) { return $ret; } else { return $this->readContrsLog(null, $ret); } } public function updateContrsLog($records) { $db = $this->db ?: $this->db = connectDb(); $newRecords = $this->updateInoculateGroups($records); if (is_object($records)) { $records = [$records]; } $currIds = ""; $db->beginTransaction(); try { foreach ($records as $row) { $sql = <<<"EOT"
UPDATE medPatientContr SET
medPatientCode = case when ? = 1 then ? else medPatientCode end,
startDate = case when ? = 1 then ? else startDate end,
finishDate = case when ? = 1 then ? else finishDate end,
medDiagnosisCode = case when ? = 1 then ? else medDiagnosisCode end,
decisionDate = case when ? = 1 then ? else decisionDate end,
note = case when ? = 1 then ? else note end,
medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
medInoculateContrCode = case when ? = 1 then ? else medInoculateContrCode end
WHERE
medpatientcontrcode in ({$row->arrayIds})
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'decisionDate'), isUndefinedProperty($row, 'decisionDate'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'disclaimerReasonsId'), isUndefinedProperty($row, 'disclaimerReasonsId') ]); } $currIds .= $row->arrayIds; $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $currIds = ($currIds !== "" ? array_map('intval', safe_explode(',', $currIds)) : null); return $this->readContrsLog(null, $currIds, $newRecords); } public function updateInoculateGroups($records) { $incGroups = isEmptyProperty($records, 'groupsIUD'); $addGroup = []; $deleteGroup = []; if ($incGroups) { foreach ($incGroups as $group) { if ($group->resultIud === 2) { $rec = new stdClass; $rec->id = $group->groupId; $deleteGroup[] = $rec; } if ($group->resultIud === 1) { $addGroup[] = $group->groupId; } } if (!empty($deleteGroup)) { $this->deleteContrsLog($records, $deleteGroup); } if (!empty($addGroup)) { $records->inoculateGroupIds = $addGroup; $ret = $this->createContrsLog($records, true); return $ret; } } } public function deleteContrsLog($records, $incGroups = null) { $db = $this->db ?: $this->db = connectDb(); $in = isset($incGroups) ? createSqlInCondition($incGroups) : null; $cond = $in ? " AND medinoculategroupcode $in" : ""; $sql = <<<"EOT"
DELETE FROM medPatientContr
WHERE
medpatientcontrcode IN ($records->arrayIds) $cond
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } if (!isset($notExecute)) { handleJsonRpc('med_vaccinations_contrsLog'); } 