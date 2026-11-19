<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_inspectionsHniz { private $db; public function readInspectionsHniz($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectionsId = isset($params->inspectionsId) && isEmptyProperty($params, 'inspectionsId') ? $params->inspectionsId : 0; $patientCode = isset($params->patientCode) ? (int)$params->patientCode : 0; $sql = <<<"EOT"
WITH hnizType AS (
    SELECT 1 AS bit, 'myocardial_infarction' AS code, 'Острый инфаркт миокарда' AS name
    UNION ALL
    SELECT 2, 'stroke', 'Острое нарушение мозгового кровообращения'
    UNION ALL
    SELECT 4, 'diabetes', 'Сахарный диабет'
    UNION ALL
    SELECT 8, 'cancer', 'Онкозаболевания'
),
inspectData AS (
    SELECT 
        MI.MedInspectCode,
        MI.MedPatientCode,
        MIO.ValueNumber
    FROM MedInspect MI
    LEFT JOIN MedInspectOther MIO
        ON MIO.MedInspectCode = MI.MedInspectCode AND MIO.Type = 9
    WHERE MI.MedInspectCode = $inspectionsId
),
relativesData AS (
    SELECT 
        COALESCE(ID.MedPatientCode, $patientCode) AS MedPatientCode,
        NULLIF(hnis->>'diagnosis','null')::int AS diagnosis,
        NULLIF(hnis->>'relativesid','null')::int AS relativesid,
        rel.rElem->>'diagnosisId' AS relativeDiagnosisId
    FROM (
        SELECT * FROM inspectData
        UNION ALL
        SELECT NULL, $patientCode, NULL
    ) ID
    JOIN MedPatient MP ON MP.MedPatientCode = $patientCode
    CROSS JOIN LATERAL jsonb_array_elements(
    COALESCE(
        (
            CASE
                WHEN MP.Json IS NULL OR MP.Json = ''
                    THEN '{}'::jsonb
                ELSE MP.Json::jsonb
            END
        )->'HNIS','[]'::jsonb)) hnis
    LEFT JOIN LATERAL (
        SELECT rElem
        FROM jsonb_array_elements(
        COALESCE(
            (
                CASE
                    WHEN MP.Json IS NULL OR MP.Json = ''
                        THEN '{}'::jsonb
                    ELSE MP.Json::jsonb
                END
            )->'relatives','[]'::jsonb)) rElem
        WHERE NULLIF(rElem->>'id','null')::int = NULLIF(hnis->>'relativesid','null')::int
        LIMIT 1
    ) rel ON TRUE
)
SELECT
    HT.bit AS "id",
    HT.code AS "code",
    HT.name AS "name",
    CASE 
        WHEN ID.ValueNumber IS NULL THEN NULL
        ELSE (ID.ValueNumber & HT.bit) = HT.bit
    END AS "checked",
    RD.relativesid AS "relativeId",
    RD.relativeDiagnosisId AS "relativeDiagnosisId",
    MD.Cipher1 || ' ' || MD.Name AS "relativeDiagnosis",
    fn_cmfio(R.ManCode) AS "relativeFio",
    CASE R.RelationDegree
        WHEN 0 THEN 'другая'
        WHEN 1 THEN 'мать/отец'
        WHEN 2 THEN 'мачеха/отчим'
        WHEN 3 THEN 'опекун'
        WHEN 4 THEN 'бабушка/дедушка'
        WHEN 5 THEN 'сестра/брат'
        WHEN 6 THEN 'дочь/сын'
        WHEN 7 THEN 'падчерица/пасынок'
        WHEN 8 THEN 'подопечная/подопечный'
        WHEN 9 THEN 'внучка/внук'
        WHEN 10 THEN 'родитель-воспитатель'
        WHEN 11 THEN 'приёмный родитель'
        WHEN 12 THEN 'супруг'
        WHEN 13 THEN 'прабабушка/прадедушка'
        WHEN 14 THEN 'неизвестно'
        ELSE CRV.Name
    END AS "familyRelationship",
    MPas.DateOfBirth AS "relativeBirthday"
FROM hnizType HT
LEFT JOIN inspectData ID ON $inspectionsId != 0 OR TRUE
LEFT JOIN relativesData RD 
    ON RD.MedPatientCode = COALESCE(ID.MedPatientCode, $patientCode) AND RD.diagnosis = HT.bit
LEFT JOIN MedRelatives R ON R.MedRelativesCode = RD.relativesid
LEFT JOIN ManPassport MPas ON MPas.ManCode = R.ManCode
LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = RD.relativeDiagnosisId
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = R.CiszResourceValueCodeFamilyMemeber
ORDER BY HT.bit;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createInspectionsHniz($records) { $this->updateInspectionsHniz($records); return true; } public function updateInspectionsHniz($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } if (empty($records)) { return false; } $inspectionsId = $records[0]->inspectionsId ?? 0; $sqlGet = "SELECT ValueNumber FROM MedInspectOther WHERE MedInspectCode = ? AND Type = 9"; $stmt = $db->prepare($sqlGet); $stmt->execute([$inspectionsId]); $currentMask = (int)$stmt->fetchColumn() ?: 0; foreach ($records as $row) { if (isset($row->checked)) { $id = (int)$row->id; if ($row->checked) { $currentMask |= $id; } else { $currentMask &= ~$id; } } } $db->beginTransaction(); try { $sqlCheck = "SELECT MedInspectOtherCode FROM MedInspectOther WHERE MedInspectCode = ? AND Type = 9"; $stmt = $db->prepare($sqlCheck); $stmt->execute([$inspectionsId]); $existingId = $stmt->fetchColumn(); if ($existingId) { $sqlUpdate = "UPDATE MedInspectOther SET ValueNumber = ? WHERE MedInspectOtherCode = ?"; $stmt = $db->prepare($sqlUpdate); $stmt->execute([$currentMask, $existingId]); } else { $sqlInsert = "INSERT INTO MedInspectOther (MedInspectCode, Type, ValueNumber) VALUES (?, 9, ?)"; $stmt = $db->prepare($sqlInsert); $stmt->execute([$inspectionsId, $currentMask]); } $sqlPatient = "SELECT MedPatientCode, Json FROM MedPatient WHERE MedPatientCode = (
            SELECT MedPatientCode FROM MedInspect WHERE MedInspectCode = ?
        )"; $stmt = $db->prepare($sqlPatient); $stmt->execute([$inspectionsId]); $patientData = $stmt->fetch(PDO::FETCH_ASSOC); $patientCode = $patientData['medpatientcode']; $patientJson = json_decode($patientData['json'] ?? '{}', true); $oldHnis = $patientJson['HNIS'] ?? null; if (!isset($patientJson['HNIS']) || !is_array($patientJson['HNIS'])) { $patientJson['HNIS'] = [ ['diagnosis' => 1, 'relativesid' => null], ['diagnosis' => 2, 'relativesid' => null], ['diagnosis' => 4, 'relativesid' => null], ['diagnosis' => 8, 'relativesid' => null], ]; } $hnisMap = []; foreach ($patientJson['HNIS'] as &$elem) { if (isset($elem['diagnosis'])) { $hnisMap[$elem['diagnosis']] = &$elem; } } unset($elem); foreach ($records as $row) { if (property_exists($row, 'relativeId')) { $diagnosisId = (int)$row->id; $relId = $row->relativeId === '' ? null : $row->relativeId; if (isset($hnisMap[$diagnosisId])) { $hnisMap[$diagnosisId]['relativesid'] = $relId; } else { $patientJson['HNIS'][] = [ 'diagnosis' => $diagnosisId, 'relativesid' => $relId ]; } } } usort($patientJson['HNIS'], fn($a, $b) => $a['diagnosis'] <=> $b['diagnosis']); $defaultHnis = [ ['diagnosis' => 1, 'relativesid' => null], ['diagnosis' => 2, 'relativesid' => null], ['diagnosis' => 4, 'relativesid' => null], ['diagnosis' => 8, 'relativesid' => null], ]; $oldNorm = $this->normalizeHnis($oldHnis, $defaultHnis); $newNorm = $this->normalizeHnis($patientJson['HNIS'], $defaultHnis); $hnisChanged = ($oldNorm !== $newNorm); if ($hnisChanged) { $sqlUpdatePatient = "UPDATE MedPatient SET Json = ? WHERE MedPatientCode = ?"; $stmt = $db->prepare($sqlUpdatePatient); $stmt->execute([json_encode($patientJson, JSON_UNESCAPED_UNICODE), $patientCode]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function normalizeHnis(?array $hnis, array $default): array { if (!is_array($hnis) || $hnis === []) { $hnis = $default; } $result = []; foreach ($hnis as $elem) { if (!isset($elem['diagnosis'])) continue; $rel = $elem['relativesid'] ?? null; if ($rel === '') $rel = null; $result[(int)$elem['diagnosis']] = $rel; } ksort($result); return $result; } public function readDiagnosisName(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $diagnosisId = isEmptyProperty($params, 'diagnosisId') ?: 0; if (!$diagnosisId){ return []; } return selectSql($db, <<<SQL
        SELECT MD.Cipher1 || ' ' || MD.Name AS "relativeDiagnosis"
        FROM MedDiagnosis MD
        WHERE MD.MedDiagnosisCode = $diagnosisId
        SQL
); } } handleJsonRpc('med_examinations_inspectionsHniz');