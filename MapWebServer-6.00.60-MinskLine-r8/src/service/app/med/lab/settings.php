<?php
require_once(__DIR__ . '/../../init.php'); class med_lab_settings { private $db; public function readUltrasoundSettings($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = (int) isUndefinedProperty($params, 'id'); $type = (int) isUndefinedProperty($params, 'type'); $where = ''; $whereType = ''; if ($id) { $where = " AND R.MedStudyReportCode = $id"; } switch ($type) { case 2: $whereType = " BETWEEN 102 AND 108"; break; case 3: $whereType = " = 101"; break; default: $whereType = " BETWEEN 101 AND 108"; break; } $sql = <<<"EOT"
SELECT
    R.MedStudyReportCode "id",
    R.Type "medStudyType",
    STRING_AGG(RI.MedStudyCode::text, ',' ORDER BY RI.MedStudyCode) "templateId",
    STRING_AGG(MS.Name::text, ', ' ORDER BY RI.MedStudyCode) "studyTemplate"
FROM MedStudyReport R
JOIN MedStudyReportIN RI ON R.MedStudyReportCode = RI.MedStudyReportCode
JOIN MedStudy MS ON MS.MedStudyCode = RI.MedStudyCode
WHERE R.Type $whereType
$where
GROUP BY R.MedStudyReportCode, R.Type
ORDER BY 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createUltrasoundSettings($records) { $db = $this->db ?: $this->db = connectDb(); $sqlReport = <<< "EOT"
        INSERT INTO MedStudyReport (Type, Name)
        VALUES (?, ?)
EOT;
$stmtReport = $db->prepare($sqlReport); $sqlReportIn = <<< "EOT"
        INSERT INTO MedStudyReportIn (MedStudyReportCode, MedStudyCode)
        VALUES (?, ?)
EOT;
$stmtReportIn = $db->prepare($sqlReportIn); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmtReport->execute([ isEmptyProperty($row, 'medStudyType'), isEmptyProperty($row, 'protocol') ]); $param = new stdClass; $param->id = (int) $db->lastInsertId('seq_MedStudyReport'); $templateIds = isEmptyProperty($row, 'templateId'); if ($templateIds !== null && $templateIds !== '') { $ids = is_array($templateIds) ? $templateIds : explode(',', (string) $templateIds); foreach ($ids as $templateId) { $templateId = trim($templateId); if ($templateId === '') { continue; } $stmtReportIn->execute([ $param->id, (int) $templateId ]); } } $ret = $this->readUltrasoundSettings($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateUltrasoundSettings($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        UPDATE MedStudyReport SET
        Type = CASE WHEN ? = 1 THEN ? ELSE Type END,
        Name = CASE WHEN ? = 1 THEN ? ELSE Name END
        WHERE MedStudyReportCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlSelectIn = <<< "EOT"
    SELECT MedStudyCode
    FROM MedStudyReportIn
    WHERE MedStudyReportCode = ?
EOT;
$stmtSelectIn = $db->prepare($sqlSelectIn); $sqlDeleteIn = <<< "EOT"
    DELETE FROM MedStudyReportIn
    WHERE MedStudyReportCode = ? AND MedStudyCode = ?
EOT;
$stmtDeleteIn = $db->prepare($sqlDeleteIn); $sqlInsertIn = <<< "EOT"
    INSERT INTO MedStudyReportIn (MedStudyReportCode, MedStudyCode)
    VALUES (?, ?)
EOT;
$stmtInsertIn = $db->prepare($sqlInsertIn); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $id = (int) isEmptyProperty($row, 'id'); $stmt->execute([ propertyExistsSQL($row, 'medStudyType'), isEmptyProperty($row, 'medStudyType'), propertyExistsSQL($row, 'protocol'), isEmptyProperty($row, 'protocol'), $id ]); $stmtSelectIn->execute([$id]); $current = []; foreach ($stmtSelectIn->fetchAll() as $r) { $current[(int) $r['medstudycode']] = true; } $templateIds = isEmptyProperty($row, 'templateId'); $new = []; if ($templateIds !== null && $templateIds !== '') { $ids = is_array($templateIds) ? $templateIds : explode(',', (string) $templateIds); foreach ($ids as $templateId) { $templateId = trim($templateId); if ($templateId === '') { continue; } $new[(int) $templateId] = true; } } foreach ($current as $code => $_) { if (!isset($new[$code])) { $stmtDeleteIn->execute([$id, $code]); } } foreach ($new as $code => $_) { if (!isset($current[$code])) { $stmtInsertIn->execute([$id, $code]); } } $param = new stdClass(); $param->id = $row->id; $ret = $this->readUltrasoundSettings($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteUltrasoundSettings($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedStudyReport', 'MedStudyReportCode', $records); } public function getUsedTypes($params = null) { $db = $this->db ?: $this->db = connectDb(); $currentCode = (int) isUndefinedProperty($params, 'currentCode'); $where = ''; if ($currentCode) { $where = " AND R.Type <> $currentCode"; } $sql = <<<"EOT"
SELECT
    R.Type "medStudyType"
FROM MedStudyReport R
WHERE R.Type BETWEEN 101 AND 108
$where
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('med_lab_settings');