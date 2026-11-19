<?php
require_once(__DIR__.'/../../init.php'); class med_sickdays_reasons { private $db; function __construct() { $this->db = connectDb(); } public function readReasons($params = null) { $db = $this->db; $sort = isEmptyProperty($params, 'sort', 2); $dir = isEmptyProperty($params, 'dir', ""); $sql = <<<SQL
            SELECT
                MDT.MedDiagnosisTerminalCode    "id",
                MDT.Cipher                      "cipher",
                MDT.Name                        "name",
                U.UniDictCode                   "disabilityType",
                U.ValueText                     "disabilityTypeName",
                U.Code                          "disabilityTypeCode",
                STRING_AGG(U2.Code || CASE WHEN U2.ValueText IS NOT NULL THEN '. ' || U2.ValueText ELSE '' END, ', ')
                                                "medDiagnosisDisabilityTypeCodeNames",
                COALESCE(to_json(array_agg(U2.UniDictCode) FILTER (WHERE U2.UniDictCode IS NOT NULL)), '[]'::json) "medDiagnosisDisabilityTypes"
            FROM MedDiagnosisTerminal MDT
            LEFT JOIN UniDict U ON U.UnidictCode = MDT.UnidictCodeTempDisabilityType
            LEFT JOIN MedDiagnosisTerminalDisabilityType MDTDT ON MDTDT.MedDiagnosisTerminalCode = MDT.MedDiagnosisTerminalCode
            LEFT JOIN UniDict U2 ON U2.UniDictCode = MDTDT.UnidictCodeTempDisabilityType
            GROUP BY 1, 2, 3, 4, 5, 6
            ORDER BY $sort $dir
            SQL;
return selectSql($db, $sql); } public function createReasons($records) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO MedDiagnosisTerminal (Cipher, Name, Typehl7, UnidictCodeTempDisabilityType)
VALUES (CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(?), 20)) as varchar(20)), ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $sqlDisabilityTypes = <<<"EOT"
INSERT INTO MedDiagnosisTerminalDisabilityType (MedDiagnosisTerminalCode, UnidictCodeTempDisabilityType)
VALUES (?, ?)
EOT;
$stmtDisabilityTypes = $db->prepare($sqlDisabilityTypes); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $disabilityTypes = isUndefinedProperty($row, 'medDiagnosisDisabilityTypes', []); $firstType = !empty($disabilityTypes) ? $disabilityTypes[0] : isEmptyProperty($row, 'disabilityType'); $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), 99, $firstType ]); $reasonId = $db->lastInsertId('seq_meddiagnosisterminal'); if (!empty($disabilityTypes) && is_array($disabilityTypes)) { foreach ($disabilityTypes as $typeId) { $stmtDisabilityTypes->execute([$reasonId, $typeId]); } } $ret[] = $rec = new stdClass; $rec->id = $reasonId; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateReasons($records) { $db = $this->db; $sql = <<<"EOT"
UPDATE MedDiagnosisTerminal SET
    Cipher = CASE WHEN ? = 1 THEN CAST((RIGHT(REPEAT(E' ', 20) || asa_trim(?), 20)) as varchar(20)) ELSE Cipher END,
    Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
    UnidictCodeTempDisabilityType = CASE WHEN ? = 1 THEN ? ELSE UnidictCodeTempDisabilityType END
WHERE
    MedDiagnosisTerminalCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlDeleteDisabilityTypes = <<<"EOT"
DELETE FROM MedDiagnosisTerminalDisabilityType
WHERE MedDiagnosisTerminalCode = ?
EOT;
$stmtDeleteDisabilityTypes = $db->prepare($sqlDeleteDisabilityTypes); $sqlInsertDisabilityTypes = <<<"EOT"
INSERT INTO MedDiagnosisTerminalDisabilityType (MedDiagnosisTerminalCode, UnidictCodeTempDisabilityType)
VALUES (?, ?)
EOT;
$stmtInsertDisabilityTypes = $db->prepare($sqlInsertDisabilityTypes); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $reasonId = $row->id; $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'disabilityType'), isUndefinedProperty($row, 'disabilityType'), $reasonId ]); if (propertyExistsSQL($row, 'medDiagnosisDisabilityTypes')) { $stmtDeleteDisabilityTypes->execute([$reasonId]); $disabilityTypes = isUndefinedProperty($row, 'medDiagnosisDisabilityTypes', []); if (!empty($disabilityTypes) && is_array($disabilityTypes)) { foreach ($disabilityTypes as $typeId) { $stmtInsertDisabilityTypes->execute([$reasonId, $typeId]); } } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteReasons($records) { $db = $this->db; return deleteSql($db, 'meddiagnosisterminal', 'meddiagnosisterminalcode', $records); } public function readReasonDiagnoses($params = null) { $db = $this->db; $reasonId = isset($params->reasonId) && isEmptyProperty($params, 'reasonId') ? $params->reasonId : null; $sql = <<< "EOT"
SELECT
    MDTI.meddiagnosisterminalincode "id",
    MDTI.meddiagnosisterminalcode "reasonId",
    MDTI.meddiagnosiscode "diagnosisId",
    MD.cipher1 + ' ' + MD.name "name"
FROM meddiagnosisterminalin MDTI
    join meddiagnosis MD on MD.meddiagnosiscode = MDTI.meddiagnosiscode
WHERE MDTI.meddiagnosisterminalcode =  $reasonId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createReasonDiagnoses($records) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO meddiagnosisterminalin (meddiagnosisterminalcode, meddiagnosiscode)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'reasonId'), isEmptyProperty($row, 'diagnosisId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_meddiagnosisterminalin'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateReasonDiagnoses($records) { $db = $this->db; $sql = <<<"EOT"
UPDATE MedDiagnosisTerminalIn SET
    MedDiagnosisTerminalCode = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisTerminalCode END,
    MedDiagnosisCode = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCode END
WHERE MedDiagnosisTerminalInCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'reasonId'), isUndefinedProperty($row, 'reasonId'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteReasonDiagnoses($records) { $db = $this->db; return deleteSql($db, 'meddiagnosisterminalin', 'meddiagnosisterminalincode', $records); } public function createBatchReasonDiagnoses($params) { $db = $this->db; $reasonId = isEmptyProperty($params, 'reasonId'); $diagnoses = isUndefinedProperty($params, 'diagnoses', []); if (empty($reasonId) || empty($diagnoses)) { throw new Exception('Не указан reasonId или диагнозы'); } $sql = <<<"EOT"
INSERT INTO MedDiagnosisTerminalin (MedDiagnosisTerminalCode, MedDiagnosisCode)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; $db->beginTransaction(); try { foreach ($diagnoses as $diagnosis) { $diagnosisId = isEmptyProperty($diagnosis, 'diagnosisId'); if (!empty($diagnosisId)) { $stmt->execute([$reasonId, $diagnosisId]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_meddiagnosisterminalin'); $rec->reasonId = $reasonId; $rec->diagnosisId = $diagnosisId; $ret[] = $rec; } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateBatchReasonDiagnoses($params) { $db = $this->db; $reasonId = isEmptyProperty($params, 'reasonId'); $diagnoses = isUndefinedProperty($params, 'diagnoses', []); if (empty($reasonId)) { throw new Exception('Не указан reasonId'); } $sqlDelete = <<<"EOT"
DELETE FROM MedDiagnosisTerminalin
WHERE MedDiagnosisTerminalCode = ?
EOT;
$stmtDelete = $db->prepare($sqlDelete); $sqlInsert = <<<"EOT"
INSERT INTO MedDiagnosisTerminalin (MedDiagnosisTerminalCode, MedDiagnosisCode)
VALUES (?, ?)
EOT;
$stmtInsert = $db->prepare($sqlInsert); $db->beginTransaction(); try { $stmtDelete->execute([$reasonId]); foreach ($diagnoses as $diagnosis) { $diagnosisId = isEmptyProperty($diagnosis, 'diagnosisId'); if (!empty($diagnosisId)) { $stmtInsert->execute([$reasonId, $diagnosisId]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readReasonDisabilityTypes($params = null) { $db = $this->db; $reasonId = isEmptyProperty($params, 'reasonId'); $sql = <<<"EOT"
SELECT
    MDTDT.MedDiagnosisTerminalDisabilityTypeCode "id",
    MDTDT.MedDiagnosisTerminalCode "medDiagnosisTerminalCode",
    MDTDT.UniDictCodeTempDisabilityType "unidictCodeTempDisabilityType"
FROM MedDiagnosisTerminalDisabilityType MDTDT
WHERE MDTDT.MedDiagnosisTerminalCode = $reasonId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createReasonDisabilityTypes($records) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO MedDiagnosisTerminalDisabilityType (MedDiagnosisTerminalCode, UniDictCodeTempDisabilityType)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'medDiagnosisTerminalCode'), isEmptyProperty($row, 'unidictCodeTempDisabilityType') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_meddiagnosisterminaldisabilitytype'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateReasonDisabilityTypes($records) { $db = $this->db; $sql = <<<"EOT"
UPDATE MedDiagnosisTerminalDisabilityType set
    MedDiagnosisTerminalCode = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisTerminalCode END,
    UniDictCodeTempDisabilityType = CASE WHEN ? = 1 THEN ? ELSE UniDictCodeTempDisabilityType END
WHERE MedDiagnosisTerminalDisabilityTypeCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'medDiagnosisTerminalCode'), isUndefinedProperty($row, 'medDiagnosisTerminalCode'), propertyExistsSQL($row, 'unidictCodeTempDisabilityType'), isUndefinedProperty($row, 'unidictCodeTempDisabilityType'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteReasonDisabilityTypes($records) { $db = $this->db; return deleteSql($db, 'MedDiagnosisTerminalDisabilityType', 'MedDiagnosisTerminalDisabilityTypeCode', $records); } } handleJsonRpc('med_sickdays_reasons');