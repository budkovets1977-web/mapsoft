<?php
 require_once(__DIR__ . '/../../init.php'); class map_med_diaries { private $db; public function setDb($db) { $this->db = $db; } public function readDiaries($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : 0; $doctorId = isset($params->doctorId) && isEmptyProperty($params, 'doctorId') ? $params->doctorId : 0; $professionId = isset($params->professionId) && isEmptyProperty($params, 'professionId') ? $params->professionId : 0; $date = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Ymd", safe_strtotime('1900-01-01')); $sql = <<<"EOT"
select
       HP.Name "name", 
       HP.htmPageCode "pageCode",
       MVD.CreateTime "date",
       FN_cmFIO(MD.manCode, 1) "doctorFio"
from medVisitPatient MVP
     join MedVisit MV on MV.MedVisitCode = MVP.MedVisitCode
     join MedDoctor MD on MD.MedDoctorCode = MV.MedDoctorCode
     join medVisitDocument MVD on MVD.MedVisitPatientCode = MVP.MedVisitPatientCode
     left join htmPageT HP on HP.htmPageCode = MVD.htmPageCode
where  ($patientId=0 or MVP.MedPatientCode=$patientId) and ($doctorId=0 or MV.MedDoctorCode=$doctorId) and ($professionId=0 or MD.MedProfessionCode=$professionId) and (MVD.CreateTime>=($date))
order by 2 desc
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } } handleJsonRpc('map_med_diaries'); 