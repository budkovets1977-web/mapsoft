<?php
 require_once(__DIR__ . '/../../init.php'); class med_card_historyPatient { private $db; function __construct() { $this->db = connectDb(); } public function readHistoryPatient($params) { $db = $this->db; $patientId = isEmptyProperty($params, 'patientId', 0); $unitName = isEmptyProperty($params, 'unitName'); $dateFrom = isEmptyProperty($params, 'dateFrom'); $dateTo = isEmptyProperty($params, 'dateTo'); $doctorId = isUndefinedProperty($params, 'doctorId'); $documentId = isUndefinedProperty($params, 'documentId'); $sqlParams = []; $sqlWhere = ''; if (!$patientId || !$unitName) { return false; } $sqlParams[':patientId'] = $patientId; $sqlParams[':unitName'] = $unitName; if ($dateFrom) { $sqlWhere .= ' AND MV.VisitDate >= :dateFrom'; $sqlParams[':dateFrom'] = dbDate($dateFrom); } if ($dateTo) { $sqlWhere .= ' AND MV.VisitDate <= :dateTo'; $sqlParams[':dateTo'] = dbDate($dateTo); } if ($doctorId) { $sqlWhere .= ' AND MV.MedDoctorCode = :doctorId'; $sqlParams[':doctorId'] = $doctorId; } if ($documentId) { $sqlWhere .= ' AND MVD.HtmTemplateCode = :documentId'; $sqlParams[':documentId'] = $documentId; } $sql = <<<SQL
WITH MainResult AS (
SELECT MVD.medVisitDocumentCode    "id",
(
    SELECT 
    STRING_AGG(Units.STR,',')
    FROM (
        SELECT 
            COALESCE( NULLIF( TRIM((SELECT regexp_matches(Unit[1], '<lval>(.*?)</lval>', 'i'))[1]::text), '' ),
                (SELECT regexp_matches(Unit[1], '<val>(.*?)</val>', 'i'))[1]::varchar(254), '' ) as STR
        FROM regexp_matches( FN_htmPageGetUnits( MVD.HtmPageCode, :unitName), '<unit>(.*?)</unit>', 'gi') Unit) Units
) "result"
FROM MedVisit MV
JOIN MedVisitPatient MVP_G on MVP_G.medVisitCode = MV.medVisitCode
JOIN MedVisitPatient MVP ON MVP.medVisitCode = MV.medVisitCode
                            AND MVP.medPatientCode = :patientId
JOIN MedVisitDocument MVD ON MVD.medVisitPatientCode = MVP.medVisitPatientCode
LEFT JOIN Users U ON U.UserCode = MVD.UserCode
LEFT JOIN htmPage HP ON HP.HtmPageCode = MVD.HtmPageCode
WHERE MVP_G.MedVisitPatientCodeParent IS NULL
    AND ((MVD.Type = 1 AND COALESCE(HP.IsDeleted, 0) = 0) OR
        (MVD.Type = 2 AND ((0 = 1 AND MVD.DeleteTime IS NOT NULL)
                            OR (0 = 0 AND MVD.DeleteTime IS NULL))))
$sqlWhere
GROUP BY MVD.medVisitDocumentCode
)
SELECT
    MVD.MedVisitDocumentCode    "id",
    MV.VisitDate                "visitDate",
    HT.Name                     "pageName",
    CASE 
        WHEN M2.ManCode IS NULL 
            THEN U.Nick 
        ELSE FN_cmFIO(M2.ManCode)::TEXT 
    END                         "userFio",
    MVD.HtmTemplateCode         "templateId",
    MV.MedDoctorCode            "doctorId",
    FN_cmFIO(M.ManCode)::TEXT   "doctorFio",
    MPRO.Name                   "doctorProfession",
    MR.result                   "result"
FROM MedVisit MV
JOIN MedVisitPatient MVP_G ON MVP_G.MedVisitCode = MV.MedVisitCode
JOIN MedVisitPatient MVP ON MVP.MedVisitCode = MV.MedVisitCode
                            AND MVP.MedPatientCode = :patientId
JOIN MedVisitDocument MVD ON MVD.MedVisitPatientCode = MVP.MedVisitPatientCode
JOIN MainResult MR ON MR.id = MVD.medVisitDocumentCode AND COALESCE(MR.result, '') <> ''
LEFT JOIN Users U ON U.UserCode = MVD.UserCode
LEFT JOIN htmPage HP ON HP.HtmPageCode = MVD.HtmPageCode
LEFT JOIN HtmTemplate HT ON HT.HtmTemplateCode = MVD.HtmTemplateCode
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MV.MedDoctorCode
LEFT JOIN MedDoctor MD2 ON MD2.UserCode = MVD.UserCode
LEFT JOIN MedProfession MPRO ON MPRO.MedProfessionCode = MD.MedProfessionCode
LEFT JOIN Man M ON M.ManCode = MD.ManCode
LEFT JOIN Man M2 on M2.ManCode = MD2.ManCode
WHERE MVP_G.MedVisitPatientCodeParent IS NULL
    AND ((MVD.Type = 1 AND COALESCE(HP.IsDeleted, 0) = 0) OR
        (MVD.Type = 2 AND ((0 = 1 AND MVD.DeleteTime IS NOT NULL)
                            OR (0 = 0 AND MVD.DeleteTime IS NULL))))
$sqlWhere
ORDER BY MV.VisitDate DESC
SQL;
return selectSql($db, $sql, $sqlParams); } } handleJsonRpc('med_card_historyPatient');