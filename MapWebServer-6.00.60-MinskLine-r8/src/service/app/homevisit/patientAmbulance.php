<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientAmbulance { private $db; public function readPatientAmbulance($params) { $db = $this->db ? : $this->db = connectDb(); $patientId = (int) $params->patientId; $sql = $db->isSybase ? <<<"EOT"

select *
from (
    select
        ME.MedEmergencyCode "eventId",
        ME.CallDate "eventDate",
        null "departmentId",
        'Скорая помощь' "departmentName",
        MD.MedDiagnosisCode "diagnosisId",
        IFNULL(MD.Cipher1, '', MD.Cipher1 || ' - ') || MD.Name "diagnosisName",
        null as "startDate"
    from MedEmergency ME
    left join MedDiagnosis MD on MD.medDiagnosisCode = ME.MedDiagnosisCode
    where ME.CallDate <= now()
      and ME.MedPatientCode = $patientId
    union all
    select
        MDH.MedDayHospitalCode "eventId",
        MDH.FinishDate "eventDate",
        null "departnemntId",
        'Дневной стационар' "departmentName",
        MD.MedDiagnosisCode "diagnosisId",
        IFNULL(MD.Cipher1, '', MD.Cipher1 || ' - ') || MD.Name "diagnosisName",
        mdh.StartDate "startDate"
    from MedDayHospital MDH
    left join MedDiagnosis MD on MD.MedDiagnosisCode = MDH.MedDiagnosisCode
    where MDH.FinishDate <= now()
      and MDH.MedPatientCode = $patientId
    union all
    select
        MLH.MedLogHospitalCode "eventId",
        MLH.OutDate "eventDate",
        IFNULL(MLH.EnterpriseCodeHospital, MLH.EnterpriseCode, MLH.EnterpriseCodeHospital) "departnemntId",
        E.Name "departmentName",
        MD.MedDiagnosisCode "diagnosisId",
        IFNULL(MD.Cipher1, '', MD.Cipher1 || ' - ') || MD.Name "diagnosisName",
        mlh.HospitalDate "startDate"
    from MedLogHospital MLH
    left join MedDiagnosis MD on MD.MedDiagnosisCode = MLH.MedDiagnosisCodeClinic
    left join Enterprise E on E.EnterpriseCode = IFNULL(MLH.EnterpriseCodeHospital, MLH.EnterpriseCode, MLH.EnterpriseCodeHospital)
    where MLH.OutDate <= now()
      and MLH.MedPatientCode = $patientId
    ) EP
order by EP.eventDate desc
EOT
: <<<"EOT"
select
  *
from (select
        ME.MedEmergencyCode as "eventId",
        ME.CallDate as "eventDate",
        null as "departmentId",
        E'Скорая помощь' as "departmentName",
        MD.MedDiagnosisCode as "diagnosisId",
        (case
           when MD.Cipher1 is null then E''
           else MD.Cipher1 || E' - '
         end) || MD.Name as "diagnosisName",
        null as "startDate"
      from MedEmergency ME
           left join MedDiagnosis MD on MD.medDiagnosisCode = ME.MedDiagnosisCode
      where ME.CallDate <= ((clock_timestamp()::timestamp))
            and ME.MedPatientCode = $patientId
      union all
      select
        MDH.MedDayHospitalCode as "eventId",
        MDH.FinishDate as "eventDate",
        null as "departnemntId",
        E'Дневной стационар' as "departmentName",
        MD.MedDiagnosisCode as "diagnosisId",
        (case
           when MD.Cipher1 is null then E''
          else MD.Cipher1 || E' - '
         end) || MD.Name as "diagnosisName",
        mdh.StartDate "startDate"
      from MedDayHospital MDH
           left join MedDiagnosis MD on MD.MedDiagnosisCode = MDH.MedDiagnosisCode
      where MDH.FinishDate <= ((clock_timestamp()::timestamp))
            and MDH.MedPatientCode = $patientId
      union all
      select
        MLH.MedLogHospitalCode as "eventId",
        MLH.OutDate as "eventDate",
        (case
           when MLH.EnterpriseCodeHospital is null then MLH.EnterpriseCode
           else MLH.EnterpriseCodeHospital
         end) as "departnemntId",
        E.Name as "departmentName",
        MD.MedDiagnosisCode as "diagnosisId",
        (case
           when MD.Cipher1 is null then E''
           else MD.Cipher1 || E' - '
         end) || MD.Name as "diagnosisName",
        mlh.HospitalDate "startDate"
      from MedLogHospital MLH
           left join MedDiagnosis MD on MD.MedDiagnosisCode = MLH.MedDiagnosisCodeClinic
           left join Enterprise E on E.EnterpriseCode = (case
                                                           when MLH.EnterpriseCodeHospital is null then MLH.EnterpriseCode
                                                           else MLH.EnterpriseCodeHospital
                                                         end)
      where MLH.OutDate <= ((clock_timestamp()::timestamp))
            and MLH.MedPatientCode = $patientId) EP
order by 2 desc NULLS LAST
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } $service = new Junior\Server(new homevisit_patientAmbulance()); $service->process(); 