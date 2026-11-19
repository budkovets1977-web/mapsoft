<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientAddInfo { private $db; public function readPatientAddInfo ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = $db->isSybase ? <<<"EOT"
select
    coalesce(pas.Sex, 0) "sex",
    a.MaritalStatus "maritalStatus",
    a.TermsLiving "termsLiving",
    a.Relatives "relatives",
    a.AttitudeToWork "attitudeToWork",
    ph.PhoneNumber "phoneNumber",

    (select first e.Name ||
        if trim(isnull(pb.ClassGroup, '')) <> '' then '-' || trim(isnull(pb.ClassGroup, '')) endif ||
            if trim(isnull(ud.Value, '')) <> '' then '-' || trim(isnull(ud.Value, '')) endif
    from MedPatientBusy pb
    join Enterprise e on e.EnterpriseCode = pb.EnterpriseCode
    left join UniDict ud on ud.uniDictCode = pb.UniDictCodeProfession
    where NOW() between coalesce(pb.StartDate, 19000101) and coalesce(pb.FinishDate, 30000101)
        and pb.MedPatientCode = p.MedPatientCode) "work",

    a.PaymentType "paymentType",
    a.InsuranceSerial "insuranceSerial",
    a.InsuranceNumber "insuranceNumber",
    a.AllergicHistory "allergicHistory",
    a.DrugIntolerance "drugIntolerance",
    a.ReactionToVaccination "reactionToVaccination",
    a.BloodType "bloodType",
    a.RhFactor "rhFactor",
    a.BloodTransfusion "bloodTransfusion",

    (select list(dateformat(t.OperationDate, 'dd.mm.yyyy') || ', ' || t.Name, '<br>')
    from (
        select lho.OperationDate, o.Name
        from MedLogHospital lh
        left join MedLogHospitalOper lho on lh.MedLogHospitalCode = lho.MedLogHospitalCode
        left join MedOperation o on lho.MedOperationCode = o.MedOperationCode
        where lh.MedPatientCode = p.MedPatientCode
        union
        select ao.OperationDate, o.Name
        from MedAmbulOperation ao
        left join MedOperation o on ao.MedOperationCode = o.MedOperationCode
        where ao.MedPatientCode = p.MedPatientCode
        order by 1 desc) t
    where t.OperationDate is not null) "operations",

    (select list(dateformat(pd.DateDeseases, 'dd.mm.yyyy') ||
        if d.MedDiagnosisCode is null then '' else ', ' || d.Cipher1 || '-' || d.Name endif,
            '<br>' order by pd.DateDeseases desc)
    from MedPatientDeseases pd
    left join MedDiagnosis d ON d.MedDiagnosisCode = pd.MedDiagnosisCode
    where pd.MedPatientCode = p.MedPatientCode) "deseases",

    a.OtherNote "otherNote"
from MedPatient p
left outer join ManPassport pas on pas.ManCode = p.ManCode
left outer join MedAnamnesis a on a.MedPatientCode = p.MedPatientCode
left outer join ManPhone ph on ph.ManCode = p.ManCode
    and PhoneType = 2
where p.MedPatientCode = ?
EOT
: <<<"EOT"
select
  coalesce (pas.Sex, 0) as "sex",
  a.MaritalStatus as "maritalStatus",
  a.TermsLiving as "termsLiving",
  a.Relatives as "relatives",
  a.AttitudeToWork as "attitudeToWork",
  ph.PhoneNumber as "phoneNumber",
  (select
     e.Name || case
                 when asa_trim(COALESCE(pb.ClassGroup, E'')) <> (E'') then E'-' || asa_trim(COALESCE(pb.ClassGroup, E''))
               end || case
                        when asa_trim(COALESCE(ud.Value, E'')) <> (E'') then E'-' || asa_trim(COALESCE(ud.Value, E''))
                      end
   from MedPatientBusy pb
        join Enterprise e on e.EnterpriseCode = pb.EnterpriseCode
        left join UniDict ud on ud.uniDictCode = pb.UniDictCodeProfession
   where (clock_timestamp()::timestamp) between coalesce(pb.StartDate, 19000101)
          and coalesce (pb.FinishDate, 30000101)
          and pb.MedPatientCode = p.MedPatientCode
   limit 1) as "work",
  a.PaymentType as "paymentType",
  a.InsuranceSerial as "insuranceSerial",
  a.InsuranceNumber as "insuranceNumber",
  a.AllergicHistory as "allergicHistory",
  a.DrugIntolerance as "drugIntolerance",
  a.ReactionToVaccination as "reactionToVaccination",
  a.BloodType as "bloodType",
  a.RhFactor as "rhFactor",
  a.BloodTransfusion as "bloodTransfusion",
  (select
     string_agg((dateformat(t.OperationDate, E'dd.mm.yyyy') || E', ' || t.Name)::text, E'<br>')
   from (select
           lho.OperationDate,
           o.Name
         from MedLogHospital lh
              left join MedLogHospitalOper lho on lh.MedLogHospitalCode = lho.MedLogHospitalCode
              left join MedOperation o on lho.MedOperationCode = o.MedOperationCode
         where lh.MedPatientCode = p.MedPatientCode
         union
         select
           ao.OperationDate,
           o.Name
         from MedAmbulOperation ao
              left join MedOperation o on ao.MedOperationCode = o.MedOperationCode
         where ao.MedPatientCode = p.MedPatientCode
         order by 1 desc NULLS LAST
       ) t
   where t.OperationDate is not null) as "operations",
  (select
     string_agg((dateformat(pd.DateDeseases, E'dd.mm.yyyy') || case
                                                                  when d.MedDiagnosisCode is null then E''
                                                                  else E', ' || d.Cipher1 || E'-' || d.Name
                                                                end)::text, E'<br>' order by pd.DateDeseases desc NULLS LAST)
   from MedPatientDeseases pd
   left join MedDiagnosis d on d.MedDiagnosisCode = pd.MedDiagnosisCode
   where pd.MedPatientCode = p.MedPatientCode) as "deseases",
  a.OtherNote as "otherNote"
from MedPatient p
     left outer join ManPassport pas on pas.ManCode = p.ManCode
     left outer join MedAnamnesis a on a.MedPatientCode = p.MedPatientCode
     left outer join ManPhone ph on ph.ManCode = p.ManCode and PhoneType = 2
where p.MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([isEmptyProperty($params, 'patientId')]); return $stmt->fetchAll(); } } $service = new Junior\Server(new homevisit_patientAddInfo()); $service->process(); 