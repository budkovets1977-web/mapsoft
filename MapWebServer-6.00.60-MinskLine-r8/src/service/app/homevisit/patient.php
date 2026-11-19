<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patient { private $db; public function readPatient ($params) { $db = $this->db ?: $this->db = connectDb(); $sql = $db->isSybase ? <<<'EOT'
select first
    dateformat(pas.DateOfBirth, 'dd.mm.yyyy') "birthDate",
    FN_wgCalcStage(3, today(), pas.DateOfBirth, 0, 0, 0) "age",

    (select first dateformat(v.VisitDate, 'dd.mm.yyyy') || ', ' ||
        fn_cmFio(d.ManCode, 1) || ', ' ||
        trim(pro.Name) ||
        if vp.MedDiagnosisCode is null then '' else ', ' || di.Cipher1 || ' - ' || di.Name endif
    from MedVisitPatient vp
    left outer join MedVisit v on v.MedVisitCode = vp.MedVisitCode
    left outer join MedDoctor d on d.MedDoctorCode = v.MedDoctorCode
    left outer join MedDiagnosis di on di.MedDiagnosisCode = vp.MedDiagnosisCode
    left outer join MedProfession pro on pro.MedProfessionCode = isnull(v.MedProfessionCode, d.MedProfessionCode)
    where vp.MedPatientCode = p.MedPatientCode
    order by v.VisitDate desc) "lastVisit",

    (select list('с ' || dateformat(h.StartDate, 'dd.mm.yyyy') || ': ' ||
        d.Cipher1 || ' - ' || d.Name, '; ')
    from MedHospital h
    join MedDiagnosis d on d.MedDiagnosisCode = h.MedDiagnosisPrimaryCode
    where h.MedPatientCode = p.MedPatientCode
        and h.StartDate <= now()
        and isnull(h.FinishDate, 30000101) = 30000101) "hospital",

    (select list(distinct d.Cipher1 || ' - ' || pr.Name, '; ')
    from MedDispensary dis
    left outer join MedDiagnosis d on d.MedDiagnosisCode = dis.MedDiagnosisCode
    left outer join MedProfession pr on pr.MedProfessionCode = dis.MedProfessionCode
    where dis.MedPatientCode = p.MedPatientCode
        and now() between isnull(dis.StartDate, 19000101) and isnull(dis.FinishDate, 30000101)) "dispensary",

    JSON_Array('', (
        select list(
            JSON_Object('',
                JSON_Numeric('id', pr.MedPrivelegeCode) ||
                JSON_String('name', pr.Name) ||
                JSON_Numeric('groupId', pr.MedPrivilegeGroupCode) ||
                JSON_Numeric('percent', isnull(pr.PrivilegePercent, 0), 0), 0), ',')
        from MedPatientCard pc
        join MedPrivelege pr on pr.MedPrivelegeCode = pc.MedPrivelegeCode
        where pc.MedPatientCode = p.MedPatientCode
            and now() between pc.StartDate and isnull(pc.FinishDate, 30000101)), 0) "priveleges",

    (select first pt.MedPreventTypeCode
    from MedPreventType pt
    where pt.EnterpriseCode = p.EnterpriseCode
    and ((DATEDIFFYEAR(pas.DateOfBirth, now()) < 18 and pt.InputForm = 3)
        or (DATEDIFFYEAR(pas.DateOfBirth, now()) >= 18 and pt.InputForm = 2))) "preventTypeId",

    (select first trim(dateformat(i.InspectDate, 'dd.mm.yyyy') || ': ' ||
        trim(pr.Name) || ', ' ||
        (select list(cg.Name, '; ' order by cg.Position)
        from MedInspectConclusion ic
        join MedConclusionGroup cg on cg.MedConclusionGroupCode = ic.MedConclusionGroupCode
        where ic.MedInspectCode = i.MedInspectCode))
    from MedInspect i
    join MedPrevent pr on pr.MedPreventCode = i.MedPreventCode
        and pr.MedPreventTypeCode = "preventTypeId"
    where i.MedPatientCode = p.MedPatientCode
        and i.InspectDate = (
            select max(i2.InspectDate) from MedInspect i2
            join MedPrevent pr2 on pr2.MedPreventCode = i2.MedPreventCode
                and pr2.MedPreventTypeCode = "preventTypeId"
            where MedPatientCode = i.MedPatientCode
            and i2.InspectDate <= now())) "lastInspect"
from MedPatient p
left join ManPassport pas on pas.ManCode = p.ManCode
where p.MedPatientCode = ?
EOT
: <<<'EOT'
SELECT DATEFORMAT (
  pas.DateOfBirth,
  E'dd.mm.yyyy'
  ) AS "birthDate",
 FN_wgCalcStage(3, (CURRENT_DATE), pas.DateOfBirth, 0, 0, 0) AS "age",
 (
  SELECT DATEFORMAT (
    v.VisitDate,
    E'dd.mm.yyyy'
    ) || E', ' || fn_cmFio(d.ManCode, 1) || E', ' || asa_trim(pro.NAME) || CASE WHEN vp.MedDiagnosisCode IS NULL THEN E'' ELSE E', ' || di.Cipher1 || E' - ' || di.NAME END
  FROM MedVisitPatient vp
  LEFT OUTER JOIN MedVisit v ON v.MedVisitCode = vp.MedVisitCode
  LEFT OUTER JOIN MedDoctor d ON d.MedDoctorCode = v.MedDoctorCode
  LEFT OUTER JOIN MedDiagnosis di ON di.MedDiagnosisCode = vp.MedDiagnosisCode
  LEFT OUTER JOIN MedProfession pro ON pro.MedProfessionCode = COALESCE(v.MedProfessionCode, d.MedProfessionCode)
  WHERE vp.MedPatientCode = p.MedPatientCode
  ORDER BY v.VisitDate DESC NULLS LAST LIMIT 1
  ) AS "lastVisit",
 (
  SELECT string_agg((
     E'n ' || DATEFORMAT (
      h.StartDate,
      E'dd.mm.yyyy'
      ) || E': ' || d.Cipher1 || E' - ' || d.NAME
     )::TEXT, E'; ')
  FROM MedHospital h
  JOIN MedDiagnosis d ON d.MedDiagnosisCode = h.MedDiagnosisPrimaryCode
  WHERE h.MedPatientCode = p.MedPatientCode AND h.StartDate <= ((clock_timestamp()::TIMESTAMP)) AND COALESCE(h.FinishDate, 30000101) = 30000101
  ) AS "hospital",
 (
  SELECT array_to_string_distinct(array_agg(d.Cipher1 || E' - ' || pr.NAME), E'; ')
  FROM MedDispensary dis
  LEFT OUTER JOIN MedDiagnosis d ON d.MedDiagnosisCode = dis.MedDiagnosisCode
  LEFT OUTER JOIN MedProfession pr ON pr.MedProfessionCode = dis.MedProfessionCode
  WHERE dis.MedPatientCode = p.MedPatientCode AND (clock_timestamp()::TIMESTAMP) BETWEEN COALESCE(dis.StartDate, 19000101) AND COALESCE(dis.FinishDate, 30000101)
  ) AS "dispensary",
 JSON_Array(E'', (
   SELECT string_agg((JSON_Object(E'', JSON_Numeric(E'id', (pr.MedPrivelegeCode)::NUMERIC) || JSON_String(E'name', pr.NAME) || JSON_Numeric(E'groupId', (pr.MedPrivilegeGroupCode)::NUMERIC) || JSON_Numeric(E'percent', (COALESCE(pr.PrivilegePercent, 0))::NUMERIC, 0), 0))::TEXT, E',')
   FROM MedPatientCard pc
   JOIN MedPrivelege pr ON pr.MedPrivelegeCode = pc.MedPrivelegeCode
   WHERE pc.MedPatientCode = p.MedPatientCode AND (clock_timestamp()::TIMESTAMP) BETWEEN pc.StartDate AND COALESCE(pc.FinishDate, 30000101)
   ), 0) AS "priveleges",
 (
  SELECT pt.MedPreventTypeCode
  FROM MedPreventType pt
  WHERE pt.EnterpriseCode = p.EnterpriseCode AND ((DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) < 18 AND pt.InputForm = 3) OR (DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) >= (18) AND pt.InputForm = 2)) LIMIT 1
  ) AS "preventTypeId",
 (
  SELECT asa_trim(DATEFORMAT (
     i.InspectDate,
     E'dd.mm.yyyy'
     ) || E': ' || asa_trim(pr.NAME) || E', ' || (
     SELECT string_agg((cg.NAME)::TEXT, E'; ' ORDER BY cg.Position NULLS FIRST)
     FROM MedInspectConclusion ic
     JOIN MedConclusionGroup cg ON cg.MedConclusionGroupCode = ic.MedConclusionGroupCode
     WHERE ic.MedInspectCode = i.MedInspectCode
     ))
  FROM MedInspect i
  JOIN MedPrevent pr ON pr.MedPreventCode = i.MedPreventCode AND pr.MedPreventTypeCode = (
    SELECT pt.MedPreventTypeCode
    FROM MedPreventType pt
    WHERE pt.EnterpriseCode = p.EnterpriseCode AND ((DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) < 18 AND pt.InputForm = 3) OR (DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) >= (18) AND pt.InputForm = 2)) LIMIT 1
    )
  WHERE i.MedPatientCode = p.MedPatientCode AND i.InspectDate = (
    SELECT max(i2.InspectDate)
    FROM MedInspect i2
    JOIN MedPrevent pr2 ON pr2.MedPreventCode = i2.MedPreventCode AND pr2.MedPreventTypeCode = (
      SELECT pt.MedPreventTypeCode
      FROM MedPreventType pt
      WHERE pt.EnterpriseCode = p.EnterpriseCode AND ((DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) < 18 AND pt.InputForm = 3) OR (DATEDIFFYEAR(pas.DateOfBirth, (clock_timestamp()::TIMESTAMP)) >= (18) AND pt.InputForm = 2)) LIMIT 1
      )
    WHERE MedPatientCode = i.MedPatientCode AND i2.InspectDate <= ((clock_timestamp()::TIMESTAMP))
    ) LIMIT 1
  ) AS "lastInspect"
FROM MedPatient p
LEFT JOIN ManPassport pas ON pas.ManCode = p.ManCode
WHERE p.MedPatientCode = ? /*0*/
 LIMIT 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute([isEmptyProperty($params, 'patientId')]); $row = $stmt->fetch(); if ($row) { $age = $row['age']; if ($age == 0) { $row['age'] = $row['birthDate']; } else { if (($age % 10) == 1) { $row['age'] = $age.' год'; } elseif (($age % 10) > 1 && ($age % 10) < 5) { $row['age'] = $age.' года'; } else { $row['age'] = $age.' лет'; } $row['age'] = $row['birthDate'].' ('.$row['age'].')'; } $row['priveleges'] = safe_json_decode($row['priveleges'], JSON_OBJECT_AS_ARRAY); usort($row['priveleges'], function($a, $b) { return $b['percent'] - $a['percent']; }); } return $row; } } $service = new Junior\Server(new homevisit_patient()); $service->process(); 