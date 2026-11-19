<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_inspectionsResearches { private $db; public function readInspectionsResearches($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : null; $sql = <<<"EOT"
SELECT
    m.MedInspectResearchCode "id",
    CASE COALESCE(m.MedLogStudyCode, 0) <> (0)
        WHEN true THEN
            CASE COALESCE(mls.State, 1) = 2 
                WHEN true THEN
                    CAST(FN_htmMask2Str(mls.htmPageCode, COALESCE(
                        (
                            select 
                                resultTemplate
                            from medResearchRow
                            where medResearchCode = mr.medResearchCode
                                and medStudyCode = mls.MedStudyCode
                        ), ms.ResultMask)) as varchar(255))
                WHEN false THEN  E'' 
            END 
        WHEN false THEN m.Conclusion
    END "conclusion",
    m.MedInspectCode "inspectionsId",
    COALESCE(m.PlanDate, TO_CHAR(DATE_TRUNC('year', CURRENT_DATE), 'YYYY-MM-DD')) AS "planDate",
    m.ResearchDate "researchDate",
    m.UniDictCodeResearch "uniDictIdResearch",
    r.Value "name",
    DI.Name "diagnosisName",
    DI.Cipher1 "diagnosisCipher",
    m.AddExamination "addExamination",
    m.MedDiagnosisCode "diagnosisId",
    m.MedLogStudyCode "logStudyId",
    m.MedFluorCode "fluorId",
    mvd.MedVisitDocumentCode "medVisitDocumentId",
    CASE mr.MedPreventTypeCode IS NOT NULL
        WHEN true THEN 4
        WHEN false THEN (
            select MS_.Type
            from medResearchRow AS MRR_ 
            join MedStudy AS MS_ on MS_.medStudyCode = MRR_.medStudyCode
            where MRR_.medResearchCode = MR.medResearchCode LIMIT 1 
        )
    END  "researchTypeId",
    (
        select medStudyCode
        from medResearchRow
        where medResearchCode = mr.medResearchCode
        order by medResearchRowCode LIMIT 1 
    ) "studyId",
    mr.MedPreventTypeCode "preventTypeId",
    mr.MedResearchCode "researchId",
    HT.HtmTemplateCode "HtmTemplateCode",
    CAST((mls.DirTime) as date) "tmpDate",
CASE 
  WHEN COALESCE(m.MedLogStudyCode, 0) <> 0 THEN
    CAST(ms.Name || ' №' || asa_trim(mls.DirNumber) || ' от ' ||
         DATEFORMAT(CAST(mls.DirTime AS date), 'dd.mm.yyyy') AS varchar(255))
  WHEN COALESCE(m.MedFluorCode, 0) <> 0 THEN
    CAST(' №' || mfc.Number || ' от ' ||
         DATEFORMAT(mfc.OnDate, 'dd.mm.yyyy') AS varchar(255))
  WHEN HT.Name IS NOT NULL THEN
    CAST(HT.Name || ' от ' || DATEFORMAT(mvd.createTime, 'dd.mm.yyyy') AS varchar(255))
  ELSE
    E''
END AS "direction",
    (
        select string_agg((MRR_.medStudyCode)::text, ',' )
        from medResearch AS MR_ 
        join medResearchRow AS MRR_ on MRR_.medResearchCode = MR_.medResearchCode
        where MR_.medResearchCode = mr.medResearchCode
    ) "listStudies",
    M.CiszResourceValueCodeDiagKind    "kindDiagId",
    CRV.Name                           "kindDiagName",
    CRV.Code                           "kindDiagCode",
    M.ClinicalDiagnosis                "clinicalDiagnosis",
    M.CiszResourceValueCodeCancerStage "cancerStageId",
    CASE WHEN M.MedDiagnosisCode IS NOT NULL THEN DI.Cipher1 || ' - ' || DI.Name END "diagnosisFullName",
    M.MedDoctorCode             "doctorsId",
    M.Note                      "note",
    FN_cmFIO(D.ManCode, 1)      "doctorsFIO",
    MIA2.AnatomicCodes  "anatomic"
FROM MedInspectResearch AS m
LEFT OUTER JOIN UniDict AS r ON r.UniDictCode = m.UniDictCodeResearch AND r.DictNumber = 28
LEFT OUTER JOIN MedDiagnosis AS DI ON DI.MedDiagnosisCode = m.MedDiagnosisCode
LEFT OUTER JOIN MedLogStudy AS mls ON mls.MedLogStudyCode = m.MedLogStudyCode
LEFT OUTER JOIN MedFluor AS mfc ON mfc.MedFluorCode = m.MedFluorCode
LEFT JOIN MedStudy AS ms ON ms.MedStudyCode = mls.MedStudyCode
LEFT JOIN MedResearch AS mr ON mr.UniDictCode = m.UniDictCodeResearch
LEFT JOIN MedVisitDocument AS mvd ON mvd.MedVisitDocumentCode = m.MedVisitDocumentCode
LEFT JOIN HtmTemplate AS HT ON HT.HtmTemplateCode = mvd.HtmTemplateCode
LEFT JOIN MedDoctor D ON D.MedDoctorCode = M.MedDoctorCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = M.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        MIA.MedInspectResearchCode,
        string_agg(
            MIA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY MIA.MedInspectResearchCode
        ) AS AnatomicCodes
    FROM MedInspectAnatomic MIA
    JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MIA.CiszResourceValueCodeAnatomic
    GROUP BY MIA.MedInspectResearchCode
) MIA2 ON MIA2.MedInspectResearchCode = M.MedInspectResearchCode
WHERE m.MedInspectCode = $inspectionsId
ORDER BY COALESCE(M.PlanDate,30000101) , 4 DESC;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createInspectionsResearches($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedInspectResearch
    (conclusion, medinspectcode, researchdate, unidictcoderesearch, addexamination,
     meddiagnosiscode, medlogstudycode, medfluorcode, plandate,
     MedDoctorCode, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, CiszResourceValueCodeCancerStage, Note)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'conclusion'), isEmptyProperty($row, 'inspectionsId'), isEmptyProperty($row, 'researchDate'), isEmptyProperty($row, 'uniDictIdResearch'), isEmptyProperty($row, 'addExamination'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'logStudyId'), isEmptyProperty($row, 'fluorId'), isEmptyProperty($row, 'planDate'), isEmptyProperty($row, 'doctorsId'), isEmptyProperty($row, 'kindDiagId'), isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'cancerStageId'), isEmptyProperty($row, 'note') ]); $ret[] = $rec = new StdClass; $rec->id = $db->lastInsertId('seq_MedInspectResearch'); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $rec->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateInspectionsResearches($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedInspectResearch SET
    conclusion = case when ? = 1 then ? else conclusion end,
    medinspectcode = case when ? = 1 then ? else medinspectcode end,
    researchdate = case when ? = 1 then ? else researchdate end,
    unidictcoderesearch = case when ? = 1 then ? else unidictcoderesearch end,
    addexamination = case when ? = 1 then ? else addexamination end,
    meddiagnosiscode = case when ? = 1 then ? else meddiagnosiscode end,
    medlogstudycode = case when ? = 1 then ? else medlogstudycode end,
    medfluorcode = case when ? = 1 then ? else medfluorcode end,
    plandate = case when ? = 1 then ? else plandate end,
    MedDoctorCode = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END
WHERE MedInspectResearchCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'inspectionsId'), isUndefinedProperty($row, 'inspectionsId'), propertyExistsSQL($row, 'researchDate'), isUndefinedProperty($row, 'researchDate'), propertyExistsSQL($row, 'uniDictIdResearch'), isUndefinedProperty($row, 'uniDictIdResearch'), propertyExistsSQL($row, 'addExamination'), isUndefinedProperty($row, 'addExamination'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'logStudyId'), isUndefinedProperty($row, 'logStudyId'), propertyExistsSQL($row, 'fluorId'), isUndefinedProperty($row, 'fluorId'), propertyExistsSQL($row, 'planDate'), isUndefinedProperty($row, 'planDate'), propertyExistsSQL($row, 'doctorsId'), isUndefinedProperty($row, 'doctorsId'), propertyExistsSQL($row, 'kindDiagId'), isUndefinedProperty($row, 'kindDiagId'), propertyExistsSQL($row, 'clinicalDiagnosis'),isUndefinedProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'cancerStageId'), isUndefinedProperty($row, 'cancerStageId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), $row->id ]); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'inspectId' => $row->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteInspectionsResearches($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInspectResearch', 'MedInspectResearchCode', $records); } public function readDiagnosisInfo($params) { $db = $this->db ?: $this->db = connectDb(); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $sql = <<<"EOT"
SELECT 
    DI.Cipher1 || ' - ' || DI.Name "diagnosisFullName",
    DI.Name "diagnosisName",
    DI.Cipher1 "diagnosisCipher"
FROM MedDiagnosis DI
WHERE DI.MedDiagnosisCode = :id
EOT;
return selectSql($db, $sql, [':id' => $diagnosisId])[0]; } public function readAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); if (!$items) { return []; } $sql = <<<SQL
SELECT
    CRV.CiszResourceValueCode AS id,
    CRV.Name AS name
FROM CiszResourceValue CRV
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectId = isEmptyProperty($params, 'inspectId'); $items = isUndefinedProperty($params, 'items'); if (!$inspectId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedInspectAnatomic
        WHERE MedInspectResearchCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $inspectId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedInspectAnatomic
            (MedInspectResearchCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $inspectId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedInspectAnatomic
            WHERE MedInspectResearchCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$inspectId], $toDelete)); } return true; } } if (!isset($inspectNotExecute)) { $service = new Junior\Server(new med_examinations_inspectionsResearches()); $service->process(); } 