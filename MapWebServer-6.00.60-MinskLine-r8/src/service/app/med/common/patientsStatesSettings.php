<?php
require_once(__DIR__.'/../../init.php'); class med_common_patientsStatesSettings { private $db; private $sqlStates = <<<"EOT"
SELECT
  199 "id",
  'Контингенты' "name",
  'map-icons map-icons-med-patient-contingent' "iconCls"
UNION ALL
SELECT
  110 "id",
  'Ветераны и участники ВОВ' "name",
  'map-icons map-icons-med-patient-contingent-wwii' "iconCls"
UNION ALL
SELECT
  120 "id",
  'Ликвидаторы аварии на ЧАЭС' "name",
  'map-icons map-icons-med-patient-contingent-chernobyl' "iconCls"
UNION ALL
SELECT
  130 "id",
  'Обязанные лица' "name",
  'map-icons map-icons-med-patient-contingent-due' "iconCls"
UNION ALL
SELECT
  140 "id",
  'Льготное приобретение медикаментов' "name",
  'map-icons map-icons-med-patient-contingent-preferential' "iconCls"
UNION ALL
SELECT
  299 "id",
  'Диспансерное наблюдение' "name",
  'map-icons map-icons-med-patient-dispensary' "iconCls"
UNION ALL
SELECT
  210 "id",
  'Сахарный диабет' "name",
  'map-icons map-icons-med-patient-dispensary-diabetes' "iconCls"
UNION ALL
SELECT
  220 "id",
  'Онкологические заболевания' "name",
  'map-icons map-icons-med-patient-dispensary-cancer' "iconCls"
EOT;
public function readDiagnosis($params) { $db = $this->db ?: $this->db = connectDb(); $stateId = isset($params->stateId) ? (int) $params->stateId : 0; $find = isset($params->find) && isEmptyProperty($params, 'find'); $findText = isUndefinedProperty($params, 'find', ''); $used = isUndefinedProperty($params, 'used', false) ? 1 : 0; $where = (!$find && !$used) ? "(coalesce(MedDiagnosisParentCode, 0) = $params->node)" : ($find ? "(Cipher1 LIKE '%$findText%' OR Name LIKE '%$findText%')" : "(1=1)"); $sql = <<<"EOT"
SELECT
    d.MedDiagnosisCode "id",
    CASE WHEN EXISTS(
        SELECT 1
        FROM MedDiagnosis md
        LEFT JOIN MedPrivelegeFeature AS mpfp ON mpfp.MedDiagnosisCode = md.MedDiagnosisCode AND mpfp.picture = $stateId
        WHERE md.MedDiagnosisCode = d.MedDiagnosisParentCode
          AND md.FlagVisible = d.FlagVisible
          AND ($used = 0 OR mpfp.MedPrivelegeFeatureCode IS NOT NULL)
          AND $where)
      THEN d.MedDiagnosisParentCode
    END "parentId",
    trim(d.Cipher1 || ' ' || d.Name) "name",
    CASE WHEN EXISTS(
        SELECT 1
        FROM MedDiagnosis
        WHERE MedDiagnosisParentCode = d.MedDiagnosisCode
        LIMIT 1)
      THEN 0
      ELSE 1
    END "leaf",
    (mpf.MedPrivelegeFeatureCode IS NOT NULL) "checked"
FROM MedDiagnosis d
LEFT JOIN MedPrivelegeFeature AS mpf ON mpf.MedDiagnosisCode = d.MedDiagnosisCode AND mpf.picture = $stateId
WHERE ((d.FlagVisible = 1 ) OR (mpf.MedPrivelegeFeatureCode IS NOT NULL))
  AND $where
  AND ($used = 0 OR mpf.MedPrivelegeFeatureCode IS NOT NULL)
ORDER BY 4, 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass; $item->id = $id = $row['id']; $item->name = $row['name']; $item->parentId = $parentId = $row['parentId']; $item->checked = $row['checked']; $item->leaf = $row['leaf'] == 1; $items[$id] = $item; $root[] = $item; } return $root; } public function updateDiagnosis($records, $params = null) { $db = $this->db ?: $this->db = connectDb(); if (isset($params) && isset($params->stateId)) { $stateId = isUndefinedProperty($params, 'stateId') ? (int) $params->stateId : 0; if (is_object($records)) { $records = [$records]; } $sqlInsert = <<<"EOT"
INSERT INTO MedPrivelegeFeature(MedDiagnosisCode, picture)
values(?, $stateId)
EOT;
$sqlDelete = <<<"EOT"
DELETE FROM MedPrivelegeFeature WHERE MedDiagnosisCode = ? and picture = $stateId
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtDelete = $db->prepare($sqlDelete); $db->beginTransaction(); try { foreach ($records as $row) { if (isUndefinedProperty($row, 'checked')) { $stmtInsert->execute([ $row->id ]); } else { $stmtDelete->execute([ $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } return true; } public function readStates($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : 0; $sql = <<<"EOT"
SELECT s.*,
      (SELECT string_agg(trim(d.Cipher1 || ' ' || d.Name)::text, '; ' ORDER BY trim(d.Cipher1 || ' ' || d.Name))
       FROM MedPrivelegeFeature mpf
       JOIN MedDiagnosis d ON d.MedDiagnosisCode = mpf.MedDiagnosisCode
       WHERE mpf.picture = s.id) "diagnosesList",
      (SELECT string_agg(p.Name::text, '; ' ORDER BY p.Name)
       FROM MedPrivelegeFeature mpf
       JOIN MedPrivelege p ON p.MedPrivelegeCode = mpf.MedPrivelegeCode
       WHERE mpf.picture = s.id) "contingetntsList"
FROM ($this->sqlStates) s
WHERE ($id = 0 OR s.id = $id)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readPatientsStatesSettings($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : 0; $stateId = isset($params->stateId) && isEmptyProperty($params, 'stateId') ? $params->stateId : 0; $sql = <<<"EOT"
SELECT
    MedPrivelegeFeatureCode "id",
    Picture "stateId",
    MedDiagnosisCode "diagnosisId",
    MedPrivelegeCode "contingetnId",
FROM MedPrivelegeFeature
WHERE ($id = 0 or ? = $id)
  AND ($stateId = 0 or Picture = $stateId)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdatePatientsStatesSettings($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedPrivelegeFeature (MedPrivelegeFeatureCode, MedDiagnosisCode, MedPrivelegeCode, Picture)
VALUES (?, ?, ?, ?)
ON CONFLICT (?) DO UPDATE SET
    MedDiagnosisCode = case when ? = 1 then EXCLUDED.MedDiagnosisCode else MedPrivelegeFeature.MedDiagnosisCode end,
    MedPrivelegeCode = case when ? = 1 then EXCLUDED.MedPrivelegeCode else MedPrivelegeFeature.MedPrivelegeCode end,
    Picture = case when ? = 1 then EXCLUDED.Picture else MedPrivelegeFeature.Picture end
RETURNING MedPrivelegeFeatureCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'contingetnId'), isUndefinedProperty($row, 'stateId'), propertyExistsSQL($row, 'diagnosisId'), propertyExistsSQL($row, 'contingetnId'), propertyExistsSQL($row, 'stateId') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientsStatesSettings($records) { return $this->insertOrUpdatePatientsStatesSettings($records, true); } public function updatePatientsStatesSettings($records) { return $this->insertOrUpdatePatientsStatesSettings($records, false); } public function deletePatientsStatesSettings($records) { return deleteSql($this->db, 'MedPrivelegeFeature', 'MedPrivelegeFeatureCode', $records); } } if (!isset($notExecute)) { handleJsonRpc('med_common_patientsStatesSettings'); }