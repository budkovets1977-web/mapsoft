<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientVaccinations { private $db; public function readPatientVaccinations($params) { $db = $this->db ? : $this->db = connectDb(); $patientId = (int) $params->patientId; $sql = $db->isSybase ? <<<"EOT"
select
    MIL.MedInoculateLogCode "inoculatelogId",
    MIL.InoculateDate "inoculateDate",
    FN_wgCalcStage(2, MIL.InoculateDate, MPt.DateOfBirth, 0, 0, 0) "patientAge",
    SUBSTRING("patientAge", 1, CHARINDEX(' ', SUBSTRING("patientAge", CHARINDEX(' ', "patientAge") + 1)) + 2) "patientAgeText",
    MIG.MedInoculateGroupCode "groupId",
    MIG.Name "groupName",
    MI.MedInoculateCode "inoculateId",
    MI.Name "inoculateName",
    (select
        LIST(MV.name || ' (' || MVP.Serial || ') ' || DOUBLETOSTR(MVP.DoseSize), '; ' order by MV.Name)
     from medVaccineMove MVM
     inner join MedVaccineParty MVP on MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
     inner join MedVaccine MV on MV.MedVaccineCode = MVP.MedVaccineCode
     where MVM.MedInoculateLogCode = MIL.MedInoculateLogCode) "vaccineInfo"
from MedInoculateLog MIL
inner join MedInoculate MI on MI.MedInoculateCode = MIL.MedInoculateCode
inner join MedInoculateGroup MIG on MIG.MedInoculateGroupCode = MI.MedInoculateGroupCode
inner join Medpatient MP on MP.MedPatientCode = MIL.MedPatientCode
inner join ManPassport MPt on MPt.ManCode = MP.manCode
where MIL.MedPatientCode = $patientId and
      (MIL.InoculateDate is not null and MIL.InoculateDate <= now())
order by MIL.InoculateDate desc
EOT
: <<<"EOT"
select
  MIL.MedInoculateLogCode as "inoculatelogId",
  MIL.InoculateDate as "inoculateDate",
  FN_wgCalcStage(2, MIL.InoculateDate, MPt.DateOfBirth, 0, 0, 0) as "patientAge",
  asa_substr(FN_wgCalcStage(2, MIL.InoculateDate, MPt.DateOfBirth, 0, 0, 0), 1, CHARINDEX(E' ', asa_substr(
      FN_wgCalcStage(2, MIL.InoculateDate, MPt.DateOfBirth, 0, 0, 0), CHARINDEX(E' ', FN_wgCalcStage(2, MIL.InoculateDate, MPt.DateOfBirth, 0, 0, 0))+ 1)) + 2) as "patientAgeText",
  MIG.MedInoculateGroupCode as "groupId",
  MIG.Name as "groupName",
  MI.MedInoculateCode as "inoculateId",
  MI.Name as "inoculateName",
  (select
      string_agg((MV.name || E' (' || MVP.Serial || E') ' || DOUBLETOSTR((MVP.DoseSize)::numeric))::text, E'; '
    order by MV.Name NULLS FIRST)
    from medVaccineMove MVM
         inner join MedVaccineParty MVP on MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
         inner join MedVaccine MV on MV.MedVaccineCode = MVP.MedVaccineCode
    where MVM.MedInoculateLogCode = MIL.MedInoculateLogCode
  ) as "vaccineInfo"
from MedInoculateLog MIL
     inner join MedInoculate MI on MI.MedInoculateCode = MIL.MedInoculateCode
     inner join MedInoculateGroup MIG on MIG.MedInoculateGroupCode = MI.MedInoculateGroupCode
     inner join Medpatient MP on MP.MedPatientCode = MIL.MedPatientCode
     inner join ManPassport MPt on MPt.ManCode = MP.manCode
where MIL.MedPatientCode = $patientId
      and (MIL.InoculateDate is not null
      and MIL.InoculateDate <= ((clock_timestamp()::timestamp)))
order by MIL.InoculateDate desc NULLS LAST
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } $service = new Junior\Server(new homevisit_patientVaccinations()); $service->process(); 