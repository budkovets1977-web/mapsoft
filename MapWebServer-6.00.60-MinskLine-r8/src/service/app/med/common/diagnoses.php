<?php
require_once(__DIR__.'/../../init.php'); class map_med_diagnoses { private $db; public function setDb($db) { $this->db = $db; } public function readDiagnoses($param) { $db = $this->db ?: $this->db = connectDb(); $find = isset($param->find) && isEmptyProperty($param, 'find'); $where = !$find ? "coalesce(MedDiagnosisParentCode, 0) = $param->node" : "(Cipher1 like {$db->postgre("E")}'%$param->find%' or Name like {$db->postgre("E")}'%$param->find%')"; $sql = <<<"EOT"
select
    d.MedDiagnosisCode "id",
    {$db->dialect("if exists (select 1
                              from MedDiagnosis
                              where MedDiagnosisCode = d.MedDiagnosisParentCode
                                and FlagVisible = d.FlagVisible
                                and $where) then d.MedDiagnosisParentCode endif", "case
                     when exists(select 1
                                 from MedDiagnosis
                                 where MedDiagnosisCode = d.MedDiagnosisParentCode
                                   and FlagVisible = d.FlagVisible
                                   and $where
                                 limit 1) then d.MedDiagnosisParentCode
                   end")} "parentId",
    d.Cipher1 "cipher",
    d.Name "name",
    {$db->dialect("if exists (select first 1 from MedDiagnosis where MedDiagnosisParentCode = d.MedDiagnosisCode ) then 0 else 1 endif", "case
                     when exists(select 1
                                 from MedDiagnosis
                                 where MedDiagnosisParentCode = d.MedDiagnosisCode
                                 limit 1) then 0
                     else 1
                   end")} "leaf",
    trim(d.Cipher1 || ' ' || d.Name) "display",
    d.DiagnosisType "type"
from MedDiagnosis d
where d.FlagVisible = 1 and $where
order by 3, 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass; $item->id = $id = $row['id']; $item->name = $row['name']; $item->cipher = $row['cipher']; $item->display = $row['display']; $item->type = $row['type']; $item->parentId = $parentId = $row['parentId']; $item->checked = false; $item->leaf = $row['leaf'] == 1; $items[$id] = $item; $root[] = $item; } return $root; } public function readPatientDiagnosis($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $diagnosisCond = $diagnosisId ? "AND MVP.MedDiagnosisCode = $diagnosisId" : ""; $onDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate', Date('Ymd')))); $isCard = isEmptyProperty($params, 'isCard', false); $sort = isEmptyProperty($params, 'sort', 0); $orderBy = ''; if ($sort == 1) { $orderBy = $db->dialect("ORDER BY display", "ORDER BY display"); } else { $orderBy = $db->dialect("ORDER BY visitLastDate desc", "ORDER BY 4 desc nulls last"); } $cardFields = $isCard ? <<<"EOT"
MD.MedDiagnosisCode   as "recordId",
MAX(MV.VisitDate)     as "recordDate",
MAX(MV.VisitDate)     as "parentDate",
COALESCE(MD.MedDiagnosisCode, '')::text || '-' || COALESCE(MVP.SicknessKind, '')::text  || '-' || COALESCE(mvpe.asrevealed, '')::text || '-' as "path",
20                    as "recordType",
null                  as "parentId",
null                  as "parentType",
13                    as "sortType",
EOT
: ''; $cardOncoFields = $isCard ? <<<EOT
            MD.MedDiagnosisCode     "recordId",
            MED.StartDate           "recordDate",
            MED.StartDate           "parentDate",
            NULL                    "path",
            20                      "recordType",
            NULL                    "parentId",
            NULL                    "parentType",
            13                      "sortType",
EOT
: ''; $sql = <<<"EOT"
SELECT
    MVP.medVisitPatientCode "id",
    MD.MedDiagnosisCode "diagnosisId",
    $cardFields
    {$db->dialect("IFNULL(MD.Cipher1, '', MD.Cipher1 || ' - ') || MD.Name", "(case
                      when MD.Cipher1 is null then E''
                      else MD.Cipher1 || E' - '
                    end) || MD.Name")} "display",
    MAX(MV.VisitDate) "visitLastDate",
    MVP.medVisitCode "visit",
    MVP.medVisitPatientCode "visitId",
    MVP.medPatientCode "patientId",
    MVP.documentSeries "documentSeries",
    MVP.documentNumber "documentNumber",
    MVP.medDiagnosisCourseCode "diagnosisCourseId",
    MVP.visitCase "visitCase",
    MVP.visitZone "visitZone",
    MVP.visitHospital "visitHospital",
    MVP.medServiceCode "serviceId",
    MVP.unidictServiceLevelCode "serviceLevelId",
    MVP.visitDispensary "visitDispensary",
    MVP.medPatientGroupCode "patientGroupId",
    MVP.attendantDiagnosis "attendantDiagnosis",
    MVP.pressure "pressure",
    MVP.visitCount "visitCount",
    MVP.visitMarkNew "visitMarkNew",
    MVP.serviceForm "serviceForm",
    MVP.SicknessKind "sicknessKind",
    MVP.visitEnd "visitEnd",
    MVP.visitEndNew "visitEndNew",
    MVP.visitCaseNew "visitCaseNew",
    MD.Cipher1 "cipher",
    MVPE.treatmenteffect "result",
    MD.Name "name",
    MD.DiagnosisType "type",
    CAST((fn_cmFIO(d.Mancode) ||  ' - ' || mpro.Name ||  ' - ' || fn_cmDepartmentName(mv.DepartmentCode)) as varchar(254)) "doctor",
    CASE (not (MVP.VisitCase=1 AND NULLIF(MVP.VisitEnd,0) IS NOT NULL AND MVP.VisitEnd != (2)) and mvpe.ChangeDate is not Null) WHEN true then 1 WHEN false THEN 0 END "badRec",
    (CASE MD.DiagnosisType
       WHEN 1 THEN 'хронический диагноз'
       WHEN 2 THEN 'острый'
       WHEN 3 THEN 'внешняя причина'
       WHEN 4 THEN 'группа'
       ELSE ''
     END) "typeName",
    COALESCE(mvpe.AsRevealed, 2) "asRevealed",
    MVPE.MedVisitPatientExCode "visitExId",
    MVPE.finishYear "finishYear",
    (CASE mvpe.asrevealed
       WHEN 1 THEN 'при профилактическом осмотре'
       WHEN 2 THEN 'при посещении'
       ELSE 'при посещении'
     END) "asRevealedText",
    COALESCE(mvpe.HelpType, 1) "helpType",
    (CASE mvpe.HelpType
       WHEN 1 THEN 'амбулаторная'
       WHEN 2 THEN 'стационарная'
       WHEN 4 THEN 'амбулаторная и стационарная'
       ELSE 'амбулаторная'
     END) "helpTypeText",
    d.medDoctorCode "doctorId",
    fn_cmFIO(DD.manCode) "newDoctor",
    MVPE.MedDoctorCodeChange "newDoctorId",
    MVPE.changeDate "changeDate",
    MDP.Name "parentName",
    ((YEAR($onDate) > YEAR(MAX(MV.VisitDate))) AND (MVP.SicknessKind IN (2,3,4) OR MVP.SicknessKind is null)) "needRediagnostic",
    (COALESCE((
              SELECT MAX(YEAR(mv.VisitDate))
              FROM MedVisitPatient AS mp
              LEFT JOIN MedVisit AS mv ON mv.MedVisitCode = mp.MedVisitCode
              WHERE mp.MedPatientCode = $patientId
                  AND mp.MedDiagnosisCode = MD.MedDiagnosisCode
                  AND mv.VisitDate < $onDate
                  AND mp.VisitEnd != 2
    ), 0) < YEAR($onDate)) "isFirstVisit",
    EXISTS(
        SELECT _MEDI.MedExtDocumentItemCode
            FROM MedExtDocumentItem _MEDI
            LEFT JOIN MedExtDocument _MED ON _MED.MedExtDocumentCode = _MEDI.MedExtDocumentCode
            WHERE _MEDI.Type = 3             -- Финальный диагноз
                AND _MED.MedPatientCode = $patientId
                AND _MEDI.Cipher = MD.Cipher1
                LIMIT 1
    ) "isOnco",
    MVPE.ClinicalDiagnosis "clinicalDiagnosis",
    MVPE.Note "note",
    CRV.CiszResourceValueCode "kindDiagId",
    CRV.Name "kindDiagName",
    CRV_CAN.CiszResourceValueCode "cancerStageId",
    CRV_CAN.Name "cancerStageName",
    MVPA2.AnatomicCodes  "anatomicIds",
    MVPA2.AnatomicNames  "anatomicNames",
CASE
    WHEN MVPE.FinishDate IS NOT NULL
        THEN MVPE.FinishDate
    WHEN MVPE.FinishYear IS NOT NULL
        THEN MAKE_DATE(MVPE.FinishYear, 12, 31)
    ELSE NULL
END AS "finishDate"
FROM MedVisitPatient MVP
JOIN MedDiagnosis MD on MD.MedDiagnosisCode = MVP.MedDiagnosisCode
LEFT JOIN MedVisitPatientEx mvpe ON mvpe.MedVisitPatientCode = MVP.MedVisitPatientCode
LEFT JOIN MedDiagnosis MDP on MDP.MedDiagnosisCode = MD.MedDiagnosisParentCode
LEFT JOIN MedVisit MV on MV.MedVisitCode = MVP.MedVisitCode
LEFT JOIN MedDoctor d on d.MedDoctorCode = mv.MedDoctorCode
LEFT JOIN MedProfession mpro on mpro.MedProfessionCode = mv.MedProfessionCode
LEFT JOIN MedDoctor DD on DD.MedDoctorCode = MVPE.MedDoctorCodeChange
LEFT JOIN MedProfession MPROO on MPROO.MedProfessionCode = MVPE.MedDoctorCodeChange
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MVPE.CiszResourceValueCodeDiagKind
    AND CRV.CiszResourceCode = (SELECT CR.CiszResourceCode FROM CiszResource CR WHERE CR.Identifier = 'KindOfDiagnosisCode')
LEFT JOIN CiszResourceValue CRV_CAN ON CRV_CAN.CiszResourceValueCode = MVPE.CiszResourceValueCodeCancerStage
    AND CRV_CAN.CiszResourceCode = (SELECT CR.CiszResourceCode FROM CiszResource CR WHERE CR.Identifier = 'StagesOfMalignTum')
LEFT JOIN (
    SELECT
        MVPA.MedVisitPatientCode,
        string_agg(
            MVPA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY MVPA.MedVisitPatientAnatomicCode
        ) AS AnatomicCodes,
        string_agg(
            CRV.Name, ';'
            ORDER BY MVPA.MedVisitPatientAnatomicCode
        ) AS AnatomicNames
    FROM MedVisitPatientAnatomic MVPA
    JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MVPA.CiszResourceValueCodeAnatomic
    GROUP BY MVPA.MedVisitPatientCode
) MVPA2 ON MVPA2.MedVisitPatientCode = MVP.MedVisitPatientCode
WHERE
  MVP.MedPatientCode = $patientId
  $diagnosisCond
  AND MVP.MedDiagnosisCode IS NOT NULL
  AND MD.MedDiagnosisCode IS NOT NULL
  AND ((mvp.VisitCase=1 AND NULLIF(mvp.VisitEnd,0) IS NOT NULL AND mvp.VisitEnd != 2) or (not (MVP.VisitCase = 1 AND NULLIF(MVP.VisitEnd,0) IS NOT NULL AND MVP.VisitEnd != (2)) and mvpe.ChangeDate is not Null))
  AND (MVP.VisitEnd = NULL OR MVP.VisitEnd IN (1, 3, 4))
GROUP BY MPROO.Name, DD.manCode, MD.MedDiagnosisCode, MD.Cipher1, MD.Name, MDP.Name, MD.DiagnosisType, MVP.SicknessKind, mvpe.MedVisitPatientExCode, d.MedDoctorCode, mpro.MedProfessionCode, mv.departmentcode, MVP.medVisitPatientCode, MVP.visitEnd, MVP.visitEndNew, MVP.medVisitCode, MVP.MedPatientCode, MVP.VisitMark, MVP.VisitMarkNew, MVP.serviceForm,
    CRV.CiszResourceValueCode,CRV.Name, CRV_CAN.CiszResourceValueCode,CRV_CAN.Name, MVPA2.AnatomicNames, MVPA2.AnatomicCodes
HAVING MAX(MV.VisitDate) = (SELECT MAX(mv1.VisitDate)
                            FROM MedVisitPatient AS mp1
                            LEFT JOIN MedVisit AS mv1 ON mv1.MedVisitCode = mp1.MedVisitCode
                            WHERE mp1.MedPatientCode = $patientId
                            AND mp1.VisitCase=1
                            AND NULLIF(mp1.VisitEnd,0) IS NOT NULL
                            AND mp1.VisitEnd != 2
                            AND mp1.MedDiagnosisCode = MD.MedDiagnosisCode)
    OR MAX(MV.VisitDate) IS NULL

UNION ALL

SELECT
    NULL                    "id",
    MD.MedDiagnosisCode     "diagnosisId",
    $cardOncoFields
    (
        CASE
            WHEN MD.Cipher1 IS NULL THEN E''
            ELSE MD.Cipher1 || E' - '
        END
    ) || MD.Name            "display",
    NULL                    "visitLastDate",
    NULL                    "visit",
    NULL                    "visitId",
    MED.MedPatientCode      "patientId",
    NULL                    "documentSeries",
    NULL                    "documentNumber",
    NULL                    "diagnosisCourseId",
    NULL                    "visitCase",
    NULL                    "visitZone",
    NULL                    "visitHospital",
    NULL                    "serviceId",
    NULL                    "serviceLevelId",
    NULL                    "visitDispensary",
    NULL                    "patientGroupId",
    NULL                    "attendantDiagnosis",
    NULL                    "pressure",
    NULL                    "visitCount",
    NULL                    "visitMarkNew",
    NULL                    "serviceForm",
    NULL                    "sicknessKind",
    NULL                    "visitEnd",
    NULL                    "visitEndNew",
    NULL                    "visitCaseNew",
    MD.Cipher1              "cipher",
    NULL                    "result",
    MD.Name                 "name",
    NULL                    "type",
    NULL                    "doctor",
    NULL                    "badRec",
    NULL                    "typeName",
    NULL                    "asRevealed",
    NULL                    "visitExId",
    NULL                    "finishYear",
    NULL                    "asRevealedText",
    NULL                    "helpType",
    NULL                    "helpTypeText",
    NULL                    "doctorId",
    NULL                    "newDoctor",
    NULL                    "newDoctorId",
    NULL                    "changeDate",
    NULL                    "parentName",
    NULL                    "needRediagnostic",
    NULL                    "isFirstVisit",
    TRUE                    "isOnco",
    NULL                    "clinicalDiagnosis",
    NULL                    "note",
    NULL                    "kindDiagCode",
    NULL                    "kindDiagName",
    NULL                    "cancerStageId",
    NULL                    "cancerStageName",
    NULL                    "anatomicIds",
    NULL                    "anatomicNames",
    NULL                    "finishDate"
FROM MedExtDocumentItem MEDI
LEFT JOIN MedExtDocument MED ON MED.MedExtDocumentCode = MEDI.MedExtDocumentCode
LEFT JOIN MedDiagnosis MD ON MD.Cipher1 = MEDI.Cipher
WHERE MEDI.Type = 3                 -- Финальный диагноз
    AND MED.HtmPageCode IS NULL     -- Только диагнозы полученные из ЦОДа и на которые у нас нет документа
    AND MED.MedPatientCode = $patientId

{$orderBy}
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function findDiagnoses($params) { $db = $this->db ?: $this->db = connectDb(); $find = isset($params->find) && isEmptyProperty($params, 'find'); $id = isEmptyProperty($params, 'id'); $where = ""; $sqlParams = []; if ($id) { $where .= "and MD.MedDiagnosisCode = ? "; $sqlParams[] = $id; } if ($find) { $words = preg_split("/[\s]+/", $params->find); $word_count = count($words); $where_condition_name = ""; $where_condition_cipher = ""; for ($i = 0; $i < $word_count; $i++) { if (preg_match("/([[:alpha:]]{1}\d{1,3}(\.\d{1,5}(\*|)|\*|))/", $words[$i])) { $where_condition_cipher .= sprintf(" or {$db->dialect("cipher", "MD.Cipher1")} like {$db->postgre("E")}'%%%s%%'\r\n", $words[$i]); } else { $where_condition_name .= sprintf(" and {$db->dialect("fullName", "MDP.Name || E' ' || MD.Name")} like {$db->postgre("E")}'%%%s%%'\r\n", $words[$i]); } } if ($where_condition_cipher !== "") { $where .= " and (\r\n(" . mb_substr($where_condition_cipher, 4) . ")"; } if ($where_condition_name !== "") { if ($where_condition_cipher !== "") { $where .= " or (\r\n" . mb_substr($where_condition_name, 4) . ")"; } else { $where .= " and (\r\n" . mb_substr($where_condition_name, 4); } } if ($where !== "") { $where .= ")"; } } $sql = <<<"EOT"
select
  MD.MedDiagnosisCode "id",
  MD.Cipher1 "cipher",
  MDP.MedDiagnosisCode "parentId",
  MDP.Name "parentName",
  MD.Name "name",
  MDP.Name || {$db->postgre("E")}' ' || MD.Name "fullName",
  {$db->dialect("IFNULL(MD.Cipher1, '', MD.Cipher1 || ' - ') || MD.Name", "(case
                    when MD.Cipher1 is null then E''
                    else MD.Cipher1 || E' - '
                  end) || MD.Name")} "display",
  MD.DiagnosisType "type"
from MedDiagnosis MD
inner join  MedDiagnosis MDP on MDP.MedDiagnosisCode = MD.MedDiagnosisParentCode
where MD.Cipher1 <> {$db->postgre("E")}'' $where
EOT;
return selectSql($db, $sql, $sqlParams); } public function queryDiagnosisComboBox($params) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'id'); $find = isUndefinedProperty($params, 'find'); $cipher = isUndefinedProperty($params, 'cipher'); $where = ""; $order = "order by cipher"; $limit = ""; if ($id) { if (is_string($id)) { $id = preg_split("/[\s,]+/", $id, -1, PREG_SPLIT_NO_EMPTY); } $sql_id = createSqlInCondition($id); $where = "md.meddiagnosiscode $sql_id"; } else if ($find) { if ($db->isSybase) { $find = trim($find); $where = "(md.cipher1 LIKE '$find%' OR md.Name LIKE '%$find%')"; $where .= " and md.FlagVisible = 1 and coalesce(md.Cipher1, '') <> ''"; } else { $find = trim(mb_ereg_replace("[\(\)]+", "", trim($find))); $query = mb_ereg_replace("[\s]+", ":* & ", $find) . ":*"; if ($cipher) { $query = trim($find); $where = "md.cipher1 like '$query%'"; } else { $where = "md.textsearch"; $where .= " @@ to_tsquery('simple', '{$query}')"; } if (isUndefinedProperty($params, 'showAll')) { $where .= " and md.FlagVisible = 1"; } else { $where .= " and md.FlagVisible = 1 and coalesce(md.Cipher1, '') <> ''"; } } } else { return []; } $sql = <<<"EOT"
select
    md.meddiagnosiscode "id",
    md.cipher1 "cipher",
    md.name "name",
    mdp.name "parentName",
    md.diagnosistype "type",
    md.treePath "treePath"
from meddiagnosis md
    left outer join meddiagnosis mdp on mdp.meddiagnosiscode = md.meddiagnosisparentcode
where
    {$where}
{$order}
{$limit}
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readRecipes($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    *
from
    MedVisitPatient
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readPreviousRegistration($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $date = isEmptyProperty($params, 'date'); $sql = <<<"EOT"
SELECT
    MVP.medVisitPatientCode "id",
    MV.visitDate "visitDate",
    CAST((fn_cmFIO(MD.manCode) ||  ' - ' || mpro.Name ||  ' - ' || fn_cmDepartmentName(MV.DepartmentCode)) as varchar(254)) "doctor",
    (CASE MVP.sicknessKind
       WHEN 1 THEN 'острое'
       WHEN 2 THEN 'первичное хроническое'
       WHEN 3 THEN 'хроническое вне обострения'
       WHEN 4 THEN 'обострение хронического'
       ELSE ''
     END) "sicknessKind"
FROM medVisitPatient MVP
    LEFT JOIN medVisit as MV ON MVP.medVisitCode = MV.medVisitCode
    LEFT JOIN MedDoctor AS MD ON MV.medDoctorCode = MD.medDoctorCode
    LEFT JOIN MedProfession mpro on mpro.MedProfessionCode = mv.MedProfessionCode
WHERE
    MVP.medPatientCode = $patientId and MVP.medDiagnosisCode = $diagnosisId
    and MV.visitDate < $date
ORDER BY 2 DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function checkPatientFinalDiagnosis($records = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    count(medVisitPatientExCode) "id"
FROM
    MedVisitPatientEx
WHERE
    medVisitPatientCode = $records->id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll()[0]['id']; } public function createPatientFinalDiagnosis($records = null) { return $this->insertOrUpdateFinalDiagnosis($records); } public function editResult($records = null) { $db = $this->db ?: $this->db = connectDb(); $rec = new stdClass; $sql = <<<"EOT"
UPDATE
    MedVisitPatient
SET
    visitEnd = case when ? = 1 then ? else visitEnd end
WHERE
    medVisitPatientCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'result'), isEmptyProperty($row, 'result'), $row->id ]); $rec->diagnosisId = $row->nameId; $rec->patientId = $row->patientId; $ret[] = $ret[] = $this->readPatientFinalDiagnosis($rec); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientFinalDiagnosis($records = null) { return $this->insertOrUpdateFinalDiagnosis($records); } public function insertOrUpdateFinalDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            INSERT INTO medVisitPatientEx (medVisitPatientCode, asRevealed, treatmentEffect,
                                            helpType, finishYear, changeDate,
                                            medDoctorCodeChange, ClinicalDiagnosis, Note,
                                            CiszResourceValueCodeDiagKind, CiszResourceValueCodeCancerStage, FinishDate)
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?)
            ON CONFLICT (medVisitPatientCode) DO UPDATE SET
                asRevealed =            CASE WHEN ? = 1 THEN EXCLUDED.asRevealed ELSE medVisitPatientEx.asRevealed END,
                treatmentEffect =       CASE WHEN ? = 1 THEN EXCLUDED.treatmentEffect ELSE medVisitPatientEx.treatmentEffect END,
                helpType =              CASE WHEN ? = 1 THEN EXCLUDED.helpType ELSE medVisitPatientEx.helpType END,
                finishYear =            CASE WHEN ? = 1 THEN EXCLUDED.finishYear ELSE medVisitPatientEx.finishYear END,
                changeDate =            CASE WHEN ? = 1 THEN EXCLUDED.changeDate ELSE medVisitPatientEx.changeDate END,
                medDoctorCodeChange =   CASE WHEN ? = 1 THEN EXCLUDED.medDoctorCodeChange ELSE medVisitPatientEx.medDoctorCodeChange END,
                ClinicalDiagnosis =   CASE WHEN ? = 1 THEN EXCLUDED.ClinicalDiagnosis ELSE medVisitPatientEx.ClinicalDiagnosis END,
                Note =   CASE WHEN ? = 1 THEN EXCLUDED.Note ELSE medVisitPatientEx.Note END,
                CiszResourceValueCodeDiagKind =   CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeDiagKind ELSE medVisitPatientEx.CiszResourceValueCodeDiagKind END,
                CiszResourceValueCodeCancerStage =   CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeCancerStage ELSE medVisitPatientEx.CiszResourceValueCodeCancerStage END,
                FinishDate =   CASE WHEN ? = 1 THEN EXCLUDED.FinishDate ELSE medVisitPatientEx.FinishDate END
            RETURNING medVisitPatientExCode AS id
EOT;
$stmt = $db->prepare($sql); $sqlEnd = <<<EOT
UPDATE MedVisitPatient
SET
VisitEndNew = CASE
        WHEN ? = 1 THEN ?
        ELSE VisitEndNew END,
Attributes = CASE
    WHEN ? = 1 THEN
        CASE WHEN ? = 4 THEN COALESCE(Attributes,0) | 2
        ELSE COALESCE(Attributes,0) & ~2 END
    ELSE Attributes END
WHERE MedVisitPatientCode = ?
EOT;
$stmtEnd = $db->prepare($sqlEnd); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'visitId'), isUndefinedProperty($row, 'asRevealed'), isUndefinedProperty($row, 'result'), isUndefinedProperty($row, 'helpType'), isUndefinedProperty($row, 'finishYear'), isUndefinedProperty($row, 'changeDate'), isUndefinedProperty($row, 'changeDoctorId'), isUndefinedProperty($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'kindDiagId'), isUndefinedProperty($row, 'cancerStageId'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'asRevealed'), propertyExistsSQL($row, 'result'), propertyExistsSQL($row, 'helpType'), propertyExistsSQL($row, 'finishYear'), propertyExistsSQL($row, 'changeDate'), propertyExistsSQL($row, 'changeDoctorId'), propertyExistsSQL($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'note'), propertyExistsSQL($row, 'kindDiagId'), propertyExistsSQL($row, 'cancerStageId'), propertyExistsSQL($row, 'finishDate') ]); if (!empty($row->visitId)) { $stmtEnd->execute([ propertyExistsSQL($row, 'visitEndNew'), isUndefinedProperty($row, 'visitEndNew'), propertyExistsSQL($row, 'visitEndNew'), isUndefinedProperty($row, 'visitEndNew'), $row->visitId ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readDiagnosisAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $visitId = isEmptyProperty($params, 'visitId'); $sql = <<<"EOT"
SELECT
        MVPA.CiszResourceValueCodeAnatomic "id",
        CRV.Name "name"
        FROM MedVisitPatientAnatomic MVPA
           LEFT JOIN CiszResourceValue CRV ON MVPA.CiszResourceValueCodeAnatomic = CRV.CiszResourceValueCode
        WHERE MVPA.MedVisitPatientCode = $visitId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDiagnosisAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $visitId = isEmptyProperty($params, 'visitId'); $items = isEmptyProperty($params, 'items'); if (!$visitId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedVisitPatientAnatomic
        WHERE MedVisitPatientCode = :visitId
    "; $stmt = $db->prepare($sql); $stmt->execute(['visitId' => $visitId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedVisitPatientAnatomic
            (MedVisitPatientCode, CiszResourceValueCodeAnatomic)
            VALUES (:visitId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'visitId' => $visitId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedVisitPatientAnatomic
            WHERE MedVisitPatientCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$visitId], $toDelete)); } return true; } public function getStatDiagnosis($params = null) { $db = $this->db ?: $this->db = connectDb(); if ($params->isStat === true) { $diagnosis = <<<SQL
            WITH diagnosisType AS (
            SELECT 1 AS Code, E'направившего учреждения' AS Name UNION ALL
            SELECT 2 AS Code, E'клинический заключительный' AS Name UNION ALL
            SELECT 3 AS Code, E'клинический осложненный' AS Name UNION ALL
            SELECT 4 AS Code, E'клинический сопутствующий' AS Name UNION ALL
            SELECT 5 AS Code, E'патологический' AS Name UNION ALL
            SELECT 6 AS Code, E'паталогический осложненный' AS Name UNION ALL
            SELECT 7 AS Code, E'патологический сопутствующий' AS Name UNION ALL
            SELECT 8 AS Code, E'причина смерти' AS Name UNION ALL
            SELECT 9 AS Code, E'заболевание вызвавшее смерть' AS Name UNION ALL
            SELECT 10 AS Code, E'другие важные заболевание способствующие смерти' AS Name)
            SELECT
            SD.MedStatCardDiagnosisCode AS "id",
            SD.DiagnosisType AS "diagnosisType",
            CASE SD.DiagnosisType
                   WHEN 1 THEN E'направившего учр'
                   WHEN 2 THEN E'клин.закл'
                   WHEN 3 THEN E'клин.осложн'
                   WHEN 4 THEN E'клин.сопутств'
                   WHEN 5 THEN E'патолог'
                   WHEN 6 THEN E'паталог.осложн'
                   WHEN 7 THEN E'патолог.сопутств'
                   WHEN 8 THEN E'прич.смерти'
                   WHEN 9 THEN E'заболев.вызвавш.смерть'
                   WHEN 10 THEN E'др.важн.заб.способств.смерти'
                   ELSE E''
               END AS "diagnosisTypeName",
            SD.MedDiagnosisCode AS "diagnosisCode",
            SD.MedDiagnosisCourseCode AS "courseCode",
            SD.MedStatCardCode AS "statCardCode",
            DE.ClinicalDiagnosis AS "clinicalDiagnosisExtra",
            SD.CiszDiagnosisExtraCode AS "diagnosisExtraCode",
            MD.Cipher1 AS "diagnosisCipher",
            MD.Cipher1 || E' - ' || MD.Name AS "medDiagnosisName",
            SD.DiagnosisType AS "diagnosisType",
            DT.Name AS "diagnosisTypeName",
            KDC.CiszResourceValueCode AS "kindOfDiagnosisCode",
            KDC.Name AS "kindOfDiagnosisName",
            CS.CiszResourceValueCode AS "cancerStageCode",
            CS.Name AS "cancerStageName",
            DE.DiagnosisDate AS "diagnosisDate",
            DE.MedDoctorCode AS "doctorCode",
            FN_cmFIO(DOC.ManCode, 1) as "medDoctorName",
            (SELECT string_agg((AT.Name)::text, E'; ' )
            FROM CiszDiagnosisExtraAnatomic AS DA 
            INNER JOIN CiszResourceValue AS AT ON AT.CiszResourceValueCode = DA.CiszResourceValueCodeAnatomic
            INNER JOIN CiszResource AS RAT ON RAT.CiszResourceCode = AT.CiszResourceCode
                 AND RAT.Identifier = E'AnatomicalTerminology' WHERE DA.CiszDiagnosisExtraCode = DE.CiszDiagnosisExtraCode) AS "anatomicalTerminology",
            (SELECT string_agg((AT.CiszResourceValueCode)::text, E';' )
            FROM CiszDiagnosisExtraAnatomic AS DA
            INNER JOIN CiszResourceValue AS AT ON AT.CiszResourceValueCode = DA.CiszResourceValueCodeAnatomic
            INNER JOIN CiszResource AS RAT ON RAT.CiszResourCecode = AT.CiszResourceCode
                 AND RAT.Identifier = E'AnatomicalTerminology' WHERE DA.CiszDiagnosisExtraCode = DE.CiszDiagnosisExtraCode) AS "anatomicalTerminologyCodes",
            SD.ClinicalDiagnosis AS "clinicalDiagnosis",
            CASE EXISTS(SELECT 1 FROM CiszPatientDignosis AS PD WHERE PD.MedPatientcode = SC.MedPatientcode
                 AND PD.DiagnosisCipher = MD.Cipher1
                 AND PD.CiszResourceValueCodeDiagKind = DE.CiszResourceValueCodeDiagKind)
            WHEN true THEN 1 END AS "ciszPosted"
            FROM MedStatCard AS SC 
            INNER JOIN MedStatCardDiagnosis AS SD ON sd.MedStatCardCode = SC.MedStatCardCode
            INNER JOIN MedDiagnosis AS MD ON MD.MedDiagnosisCode = SD.MedDiagnosisCode
            LEFT JOIN diagnosisType AS DT ON DT.Code = SD.DiagnosisType
            LEFT JOIN CiszDiagnosisExtra AS DE ON DE.CiszDiagnosisExtracode = SD.CiszDiagnosisExtraCode
            LEFT JOIN MedDoctor AS DOC ON DOC.MedDoctorCode = DE.MedDoctorCode
            LEFT OUTER JOIN CiszResourceValue AS KDC ON KDC.CiszResourceValueCode = DE.CiszResourceValueCodeDiagKind
            AND KDC.CiszResourceCode IN (
                SELECT CiszResourceCode
                FROM CiszResource
                 WHERE Identifier = E'KindOfDiagnosisCode' LIMIT 1 )
            LEFT OUTER JOIN CiszResourceValue AS CS ON CS.CiszResourceValueCode = DE.CiszResourceValueCodeCancerStage
            AND CS.CiszResourceCode IN (
                SELECT CiszResourceCode
                FROM CiszResource
                 WHERE Identifier = E'StagesOfMalignTum' LIMIT 1 )
            WHERE SC.MedPatientcode = :MedPatientCode
            AND (COALESCE(:KindOfDiagnosisCode, 0) = 0 OR (DE.CiszResourceValueCodeDiagKind = :KindOfDiagnosisCode))
            ORDER BY DE.DiagnosisDate;
        SQL;
$stmt = $db->prepare($diagnosis); $stmt->execute([ 'MedPatientCode' => $params->patientId, 'KindOfDiagnosisCode' => $params->diagnosisKindCode, ]); return $stmt->fetchAll(); } else { $diagnosis = <<<SQL
                SELECT
                mp.MedVisitPatientCode AS "id",
                CAST(MIN(md.Cipher1 || E' - ' || md.Name) AS VARCHAR(255)) AS "medDiagnosisName",
                MAX(mv.VisitDate) AS "visitDate",
                mp.SicknessKind AS "sicknessKind",
                COALESCE(mvpe.HelpType, 1) AS "helpType",
                CASE COALESCE(mvpe.HelpType, 1)
                    WHEN 1 THEN E'амбулаторная'
                    WHEN 2 THEN E'стационарная'
                    WHEN 4 THEN E'амбулаторная и стационарная'
                    ELSE E'амбулаторная'
                END AS "helpTypeName",
                mvpe.TreatmentEffect AS "treatmentEffect",
                CASE mvpe.TreatmentEffect
                    WHEN 1 THEN E'улучшилось'
                    WHEN 2 THEN E'без перемен'
                    WHEN 3 THEN E'ухудшилось'
                    ELSE E''
                END AS "treatmentEffectName",
                COALESCE(mvpe.AsRevealed, 2) AS "asRevealed",
                CASE COALESCE(mvpe.AsRevealed, 2)
                    WHEN 1 THEN E'при профилактическом осмотре'
                    WHEN 2 THEN E'при посещении'
                    ELSE E'при посещении'
                END AS "asRevealedName",
                CAST((fn_cmFIO(d.Mancode) || E' - ' || mpro.Name || E' - ' || fn_cmDepartmentName(mv.DepartmentCode)) AS VARCHAR(254)) AS "doctorFio",
                CASE mp.SicknessKind
                    WHEN 1 THEN E'острое'
                    WHEN 2 THEN E'первичное хроническое'
                    WHEN 3 THEN E'хроническое вне обострения'
                    WHEN 4 THEN E'обострение хронического'
                END AS "sicknessKindName",
                mp.MedVisitPatientCode AS "medVisitPatientCode",
                mvpe.MedVisitPatientExCode AS "medVisitPatientExCode",
                mp.MedDiagnosisCode AS "medDiagnosisCode",
                md.DiagnosisType AS "diagnosisType",
                COALESCE(mvpe.FinishDate, YMD(mvpe.FinishYear + 1, 1, 1) - 1) AS "finishDate",
                mvpe.ChangeDate AS "changeDate",
                mvpe.MedDoctorCodeChange AS "medDoctorCodeChange",
                mp.MedDiagnosisCode AS "fMedDiagnosisCode",
                (
                    SELECT MAX(mv_287.VisitDate)
                    FROM MedVisit AS mv_287
                    JOIN MedVisitPatient AS mvp ON mvp.MedVisitCode = mv_287.MedVisitCode
                    WHERE mvp.VisitCase = 1
                        AND mvp.MedPatientCode = :MedPatientCode
                        AND mvp.MedDiagnosisCode = mp.MedDiagnosisCode
                ) AS "visitDateLast",
                CAST(
                    fn_cmFIO(d2.Mancode) ||
                    CASE WHEN mpro2.MedProfessionCode IS NOT NULL THEN E' - ' || mpro2.Name ELSE '' END ||
                    CASE WHEN d2.DepartmentCode IS NOT NULL THEN E' - ' || fn_cmDepartmentName(d2.DepartmentCode) ELSE '' END
                    AS VARCHAR(254)
                ) AS "doctorChangeFIO",
                CASE
                    WHEN (NOT (mp.VisitCase = 1 AND NULLIF(mp.VisitEnd, 0) IS NOT NULL AND mp.VisitEnd != 2) AND mvpe.ChangeDate IS NOT NULL) THEN 1
                    ELSE 0
                END AS "badRec",
                mvpe.Note AS "note",
                kdc.ciszresourcevaluecode AS "kindOfDiagnosisCode",
                kdc.name AS "kindOfDiagnosisName",
                mvpe.clinicaldiagnosis AS "clinicaldiagnosis",
                (
                    SELECT STRING_AGG(at.name, E'; ')
                    FROM medvisitpatientanatomic AS ma
                    INNER JOIN ciszresourcevalue AS at ON at.ciszresourcevaluecode = ma.ciszresourcevaluecodeanatomic
                    INNER JOIN ciszresource AS rat ON rat.ciszresourcecode = at.ciszresourcecode
                        AND rat.identifier = E'AnatomicalTerminology'
                    WHERE ma.medvisitpatientcode = mvpe.medvisitpatientcode
                ) AS "anatomicalTerminology",
                (
                    SELECT STRING_AGG(at.ciszresourcevaluecode, E';')
                    FROM medvisitpatientanatomic AS ma
                    INNER JOIN ciszresourcevalue AS at ON at.ciszresourcevaluecode = ma.ciszresourcevaluecodeanatomic
                    INNER JOIN ciszresource AS rat ON rat.ciszresourcecode = at.ciszresourcecode
                        AND rat.identifier = E'AnatomicalTerminology'
                    WHERE ma.medvisitpatientcode = mvpe.medvisitpatientcode
                ) AS "anatomicalTerminologyCodes",
                (
                    SELECT STRING_AGG(at.ciszresourcevaluecode, E';')
                    FROM medvisitpatientanatomic AS ma
                    INNER JOIN ciszresourcevalue AS at ON at.ciszresourcevaluecode = ma.ciszresourcevaluecodeanatomic
                    INNER JOIN ciszresource AS rat ON rat.ciszresourcecode = at.ciszresourcecode
                        AND rat.identifier = E'AnatomicalTerminology'
                    WHERE ma.medvisitpatientcode = mvpe.medvisitpatientcode
                ) AS "anatomicalTerminologyCodesOld",
                cs.ciszresourcevaluecode AS "cancerStageCode",
                cs.name AS "cancerStageName",
                md.Cipher1 AS "diagnosisCipher"
            FROM MedVisitPatient AS mp
            LEFT JOIN MedVisitPatientEx AS mvpe ON mvpe.MedVisitPatientCode = mp.MedVisitPatientCode
            LEFT JOIN MedDiagnosis AS md ON mp.MedDiagnosisCode = md.MedDiagnosisCode
            LEFT JOIN MedVisit AS mv ON mv.MedVisitCode = mp.MedVisitCode
            LEFT JOIN MedDoctor AS d ON d.MedDoctorCode = mv.MedDoctorCode
            LEFT JOIN MedProfession AS mpro ON mpro.MedProfessionCode = mv.MedProfessionCode
            LEFT JOIN MedDoctor AS d2 ON d2.MedDoctorCode = mvpe.MedDoctorCodeChange
            LEFT JOIN MedProfession AS mpro2 ON mpro2.MedProfessionCode = d2.MedProfessionCode
            LEFT JOIN ciszresourcevalue AS kdc ON kdc.ciszresourcevaluecode = mvpe.ciszresourcevaluecodediagkind
                AND kdc.ciszresourcecode IN (
                    SELECT ciszresourcecode
                    FROM ciszresource
                    WHERE identifier = E'KindOfDiagnosisCode'
                    LIMIT 1
                )
            LEFT JOIN ciszresourcevalue AS cs ON cs.ciszresourcevaluecode = mvpe.ciszresourcevaluecodecancerstage
                AND cs.ciszresourcecode IN (
                    SELECT ciszresourcecode
                    FROM ciszresource
                    WHERE identifier = E'StagesOfMalignTum'
                    LIMIT 1
                )
            WHERE mp.MedPatientCode = :MedPatientCode
                AND (
                    (mp.VisitCase = 1 AND NULLIF(mp.VisitEnd, 0) IS NOT NULL AND mp.VisitEnd != 2)
                    OR (
                        NOT (mp.VisitCase = 1 AND NULLIF(mp.VisitEnd, 0) IS NOT NULL AND mp.VisitEnd != 2)
                        AND mvpe.ChangeDate IS NOT NULL
                    )
                )
            GROUP BY
                mp.MedDiagnosisCode,
                mp.SicknessKind,
                mvpe.HelpType,
                mvpe.TreatmentEffect,
                mvpe.AsRevealed,
                fn_cmFIO(d.Mancode),
                mpro.Name,
                fn_cmDepartmentName(mv.DepartmentCode),
                mp.MedVisitPatientCode,
                mvpe.MedVisitPatientExCode,
                md.DiagnosisType,
                mvpe.ChangeDate,
                mvpe.MedDoctorCodeChange,
                fn_cmFIO(d2.Mancode),
                mpro2.MedProfessionCode,
                mpro2.Name,
                d2.DepartmentCode,
                mvpe.Note,
                kdc.ciszresourcevaluecode,
                kdc.name,
                mvpe.clinicaldiagnosis,
                cs.ciszresourcevaluecode,
                cs.name,
                md.Cipher1
            ORDER BY "visitDate" DESC, "kindOfDiagnosisName", "medVisitPatientCode"
            SQL;
$stmt = $db->prepare($diagnosis); $stmt->execute([ 'MedPatientCode' => $params->patientId, ]); return $stmt->fetchAll(); } } } handleJsonRpc('map_med_diagnoses');