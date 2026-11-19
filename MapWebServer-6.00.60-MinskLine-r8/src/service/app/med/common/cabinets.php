<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_cabinets { private $db; public function readCabinets($params = null) { $db = $this->db ?: $this->db = connectDb(); $active = isEmptyProperty($params, 'active'); $activeSql = empty($active) ? '' : " and coalesce(mc.active,0) = 1"; $enterpriseId = getSessionValue('mapEnterpriseCode'); $enterpriseStructureId = isEmptyProperty($params, 'enterpriseStructureId') ? isEmptyProperty($params, 'enterpriseStructureId') : getSessionValue('EnterpriseStructureCode', 0); $search = isEmptyProperty($params, "search", ""); if ($search) { $search = " and (mc.cabinet ilike '%$search%')"; } $sql = <<<"EOT"
SELECT
    mc.medCabinetCode "id",
    mc.active "active",
    mc.cabinet "cabinet",
    mc.enterpriseCode "enterpriseId",
    mc.finishTime "finishTime",
    mc.medDoctorCode "doctorId",
    mc.enterpriseStructureCode "enterpriseStructureId",
    mc.note "note",
    mp.name "professionName",
    mc.startTime "startTime",
    fn_cmFio(doc.ManCode, 1) "doctorName",
    fn_cmEnterpriseStructureName(mc.enterpriseStructureCode, 2) "enterpriseStructureName"
FROM MedCabinet mc
LEFT JOIN MedDoctor doc on doc.MedDoctorCode = mc.MedDoctorCode
LEFT JOIN MedProfession mp on mp.MedProfessionCode = doc.MedProfessionCode
WHERE mc.enterpriseCode = $enterpriseId
  and ($enterpriseStructureId = 0 or mc.enterpriseStructureCode = $enterpriseStructureId)
$search
$activeSql
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCabinets($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update MedCabinet  set
    active = case when ? = 1 then ? else active end,
    cabinet = case when ? = 1 then ? else cabinet end,
    FinishTime = case when ? = 1 then ? else FinishTime end,
    medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
    enterpriseStructureCode = case when ? = 1 then ? else enterpriseStructureCode end,
    note = case when ? = 1 then ? else note end,
    StartTime = case when ? = 1 then ? else StartTime end
where medCabinetCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $finishTime = ''; $startTime = ''; $stmt->execute([ propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'cabinet'), isUndefinedProperty($row, 'cabinet'), propertyExistsSQL($row, 'finishTime'), isUndefinedProperty($row, 'finishTime'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'enterpriseStructureId'), isUndefinedProperty($row, 'enterpriseStructureId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'startTime'), isUndefinedProperty($row, 'startTime'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCabinets($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
insert into MedCabinet (active, cabinet, enterpriseCode, finishTime, medDoctorCode, enterpriseStructureCode, note, startTime)
values (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'active'), isUndefinedProperty($row, 'cabinet'), $enterpriseId, isUndefinedProperty($row, 'finishTime'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'enterpriseStructureId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'startTime') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCabinet'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteCabinets($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedCabinet', 'MedCabinetCode', $records); } } handleJsonRpc('med_common_cabinets'); 