<?php
 require_once(__DIR__.'/../../init.php'); class med_common_patientGroups { private $db; public function readPatientGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $ids = isEmptyProperty($params, 'ids'); if (!empty($id)) { $ids[] = $id; } $where = !empty($ids) ? ("WHERE MP.MedPhantomCode IN (" . str_repeat('?', count($ids)) . ")") : ""; $whereParams = !empty($ids) ? $ids : []; $sql = <<<"EOT"
SELECT
    MP.MedPhantomCode               "id",
    MP.Cipher                       "cipher",
    MP.Name                         "name",
    MP.Sex                          "sex",
    MP.MedPatientGroupCode          "groupCode",
    MPG.Name                        "group",
    MP.MedPatientPolyclinicCode     "polyclinicCode",
    MPP.Name                        "polyclinic",
    MP.AgeFrom                      "ageFrom",
    MP.AgeTo                        "ageTo"
FROM MedPhantom MP
LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode
LEFT JOIN MedPatientPolyclinic MPP ON MPP.MedPatientPolyclinicCode = MP.MedPatientPolyclinicCode
$where
ORDER BY MP.Cipher::integer
EOT;
$stmt = $db->prepare($sql); $stmt->execute($whereParams); return $stmt->fetchAll(); } public function createPatientGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedPhantom(
    Cipher,
    Name,
    Sex,
    MedPatientGroupCode,
    MedPatientPolyclinicCode,
    AgeFrom,
    AgeTo
)
VALUES (?, ?, ?, ?, ?, ?, ?);
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ids = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'groupCode'), isUndefinedProperty($row, 'polyclinicCode'), isUndefinedProperty($row, 'ageFrom'), isUndefinedProperty($row, 'ageTo'), ]); $ids[] = $db->lastInsertId('seq_MedPhantom'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (!empty($ids)) { $param = new stdClass(); $param->ids = $ids; $ret = $this->readPatientGroups($param); return $ret; } else { return true; } } public function updatePatientGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedPhantom SET
    Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END,
    Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
    Sex = CASE WHEN ? = 1 THEN ? ELSE Sex END,
    MedPatientGroupCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientGroupCode END,
    MedPatientPolyclinicCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientPolyclinicCode END,
    AgeFrom = CASE WHEN ? = 1 THEN ? ELSE AgeFrom END,
    AgeTo = CASE WHEN ? = 1 THEN ? ELSE AgeTo END
WHERE MedPhantomCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $ids = []; try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'sex'), isUndefinedProperty($row, 'sex'), propertyExistsSQL($row, 'groupCode'), isUndefinedProperty($row, 'groupCode'), propertyExistsSQL($row, 'polyclinicCode'), isUndefinedProperty($row, 'polyclinicCode'), propertyExistsSQL($row, 'ageFrom'), isUndefinedProperty($row, 'ageFrom'), propertyExistsSQL($row, 'ageTo'), isUndefinedProperty($row, 'ageTo'), $row->id ]); $ids[] = $row->id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (!empty($ids)) { $param = new stdClass(); $param->ids = $ids; $ret = $this->readPatientGroups($param); return $ret; } else { return true; } } public function deletePatientGroups($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM MedPhantom
WHERE MedPhantomCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readAreas($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    MPG.MedPatientGroupCode "id",
    MPG.Name "name"
FROM MedPatientGroup MPG
WHERE
    (MPG.EnterpriseCode = ?) AND
    (fn_WebUserAccessMedSector(MPG.MedPatientGroupCode) = 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseCode, ]); return $stmt->fetchAll(); } public function getNextCipher($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    INCSTR(
        ASA_TRIM(
            (
                SELECT
                    MAX(RIGHT(SPACE(20) || MP.Cipher, 20))
                FROM
                    MedPhantom MP
            )
        )
    ) "cipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'cipher'); return $cipher; } public function checkCipher($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $cipher = isEmptyProperty($params, 'cipher'); if (empty($cipher)) { return false; } $sql = <<<"EOT"
SELECT
    COUNT(MP.Cipher::integer) "count"
FROM
    MedPhantom MP
WHERE
    (MP.Cipher = ?)
EOT;
$data = [ $cipher ]; if (!empty($id)) { $sql .= ' AND (MP.MedPhantomCode <> ?)'; $data[] = $id; } $stmt = $db->prepare($sql); $stmt->execute($data); $count = fetchValue($stmt, 'count'); return ($count == 0); } } $service = new Junior\Server(new med_common_patientGroups()); $service->process();