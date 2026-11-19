<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_reception_visitservices { private $db; public function readVisitServices($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $doctorId = isEmptyProperty($params, 'doctorId'); $professionId = isEmptyProperty($params, 'professionId'); $cabinetId = isEmptyProperty($params, 'cabinetId'); $search = isEmptyProperty($params, "search", ""); $sqlCondition = ""; $sqlWhere = "where S.EnterpriseCode = $enterpriseCode"; if ($doctorId || $professionId || $cabinetId) { $sqlWhere .= " and S.active = 1"; $sqlCondition = "where pmsVisitServiceCode = S.pmsVisitServiceCode"; $sqlCondition .= $doctorId ? " and coalesce(medDoctorCode, $doctorId) = $doctorId" : ""; $sqlCondition .= $professionId ? " and coalesce(medProfessionCode, $professionId) = $professionId" : ""; $sqlCondition .= $cabinetId ? " and coalesce(medCabinetCode, $cabinetId) = $cabinetId" : ""; $sqlWhere .= <<<"EOT"
        and exists(
            select 1
            from PmsVisitServiceCondition
            $sqlCondition
        )
EOT;
} if ($search) { $sqlWhere .= " and (s.name ilike '%$search%')"; } $sql = <<<"EOT"
select
    s.pmsVisitServiceCode as "id",
    s.Active as "active",
    s.Name as "name",
    cast(s.TimeSlot / 60 as integer) as "timeSlot"
from pmsVisitService s
$sqlWhere
order by s.Name
EOT;
return selectSql($db, $sql); } public function createVisitServices ($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); return insertSql($db, 'pmsVisitService', $records, "EnterpriseCode, Active, Name, TimeSlot", "active, name, timeSlot", "$enterpriseCode, ?, ?, ? * 60" ); } public function updateVisitServices ($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } foreach ($records as &$value) { if (isUndefinedProperty($value, 'timeSlot')) { $value->timeSlot = $value->timeSlot * 60; } } return updateSql($db, 'pmsVisitService', 'pmsVisitServiceCode', $records, "Active, Name, TimeSlot", "active, name, timeSlot" ); } public function deleteVisitServices ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsVisitService', 'pmsVisitServiceCode', $records); } public function readVisitServiceConditions ($params) { $db = $this->db ?: $this->db = connectDb(); $visitServiceId = (int) isEmptyProperty($params, 'visitServiceId'); $sql = <<<"EOT"
select
    sc.pmsVisitServiceConditionCode as id,
    sc.pmsVisitServiceCode as "visitServiceId",
    sc.MedDoctorCode as "doctorId",
    fn_cmFio(d.ManCode) as "doctorName",
    sc.MedProfessionCode as "professionId",
    p.Name as "professionName",
    sc.MedCabinetCode as "cabinetId",
    c.Cabinet as "cabinetName"
from pmsVisitServiceCondition as sc
left join MedDoctor as d on d.MedDoctorCode = sc.MedDoctorCode
left join MedProfession as p on p.MedProfessionCode = sc.MedProfessionCode
left join MedCabinet as c on c.MedCabinetCode = sc.MedCabinetCode
where sc.pmsVisitServiceCode = ?
EOT;
return selectSql($db, $sql, [$visitServiceId]); } public function createVisitServiceConditions ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'pmsVisitServiceCondition', $records, "pmsVisitServiceCode, MedDoctorCode, MedProfessionCode, MedCabinetCode", "visitServiceId, doctorId, professionId, cabinetId" ); } public function updateVisitServiceConditions ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsVisitServiceCondition', 'pmsVisitServiceConditionCode', $records, "MedDoctorCode, MedProfessionCode, MedCabinetCode", "doctorId, professionId, cabinetId" ); } public function deleteVisitServiceConditions ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsVisitServiceCondition', 'pmsVisitServiceConditionCode', $records); } } handleJsonRpc('pmed_reception_visitservices');