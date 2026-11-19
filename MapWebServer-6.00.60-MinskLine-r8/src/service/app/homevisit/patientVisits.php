<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientVisits { private $db; public function readPatientVisits($params) { $db = $this->db ? : $this->db = connectDb(); $patientId = (int) $params->patientId; $sql = <<<"EOT"
SELECT {$db->sybase("TOP 100")}
    /* т.к. все равно в один день, берем последний по базе */
    MAX(MV.MedVisitCode) "visitId",
    MV.VisitDate "visitDate",
    MD.MedDoctorCode "doctorId",
    FN_cmFIO(MD.ManCode, 1) "doctorFio",
    MD.MedProfessionCode "professionId",
    MP.Name "professionName",
    /* складываем диагнозы одного дня в обратном порядке */
    {$db->dialect("LIST('<div class=\"item-padding\">' || IFNULL(MDs.Cipher1, '', MDs.Cipher1 || ' - ') || MDs.Name, '</div>' ORDER BY MV.MedVisitCode DESC)", "STRING_AGG((E'<div class=\"item-padding\">' || (CASE
                                                  WHEN MDs.Cipher1 IS NULL then E''
                                                  ELSE MDs.Cipher1 || E' - '
                                                END) || MDs.Name)::TEXT, E'</div>' ORDER BY MV.MedVisitCode DESC NULLS LAST)")} "diagnosisText",
    (SELECT STRING_AGG(FN_htmGetResultByTemplateMask(vd.htmpagecode, vd.htmtemplatecode), ';')
        FROM medVisitDocument vd
        WHERE vd.medVisitPatientCode = mvp.medVisitPatientCode) "resutls"
FROM MedVisitPatient MVP
INNER JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
INNER JOIN MedDoctor MD ON MD.MedDoctorCode = MV.MedDoctorCode
INNER JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
LEFT JOIN MedDiagnosis MDs ON MDs.MedDiagnosisCode = MVP.MedDiagnosisCode
WHERE MV.VisitDate <= {$db->dialect("NOW()", "(CLOCK_TIMESTAMP()::TIMESTAMP)")}
  /* ? - patientId */
  AND MVP.MedPatientCode = $patientId
GROUP BY MV.VisitDate, MD.MedDoctorCode, MD.ManCode, MD.MedProfessionCode, MP.Name, mvp.medVisitPatientCode
ORDER BY MV.VisitDate desc {$db->postgre("NULLS LAST")}
{$db->postgre("LIMIT 100")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('homevisit_patientVisits');