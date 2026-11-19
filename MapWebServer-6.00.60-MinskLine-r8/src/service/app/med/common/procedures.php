<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_procedures { private $db; public function readProcedures($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
SELECT
    MSP.MedSanProcCode                    "id",
    MSP.Name                              "name",
    MSP.EnterpriseCode                    "enterpriseId",
    MSP.Paid                              "isPaid",
    MSP.Active                            "active",
    MSP.AmountPerDay                      "amountPerDay",
    MSP.AmountPerCourse                   "amountPerCourse",
    MSP.Interval                          "interval",
    COALESCE(array_agg(MSF.BranchCode) FILTER (WHERE MSF.BranchCode IS NOT NULL), '{}')                   "branchId",
    COALESCE(string_agg(B.Name, ', '), '')                                                                "branchName",
    COALESCE(array_agg(MSF.DepartmentCode) FILTER (WHERE MSF.DepartmentCode IS NOT NULL), '{}')           "departmentId",
    COALESCE(string_agg(D.Name, ', '), '')                                                                "departmentName",
    COALESCE(array_agg(MSF.MedProfessionCode) FILTER (WHERE MSF.MedProfessionCode IS NOT NULL), '{}')     "professionIds",
    COALESCE(string_agg(P.Name, ', '), '')                                                                "professionNames",
    COALESCE(array_agg(MSF.MedDoctorCode) FILTER (WHERE MSF.MedDoctorCode IS NOT NULL), '{}')             "doctorIds",
    COALESCE(string_agg(CASE
            WHEN DR.ManCode IS NOT NULL AND fn_cmfio(DR.ManCode) <> ''
            THEN fn_cmfio(DR.ManCode)
            ELSE NULL
        END,', '), '') AS "doctorNames",
    COALESCE(array_agg(MSF.MedCouponWorkCode) FILTER (WHERE MSF.MedCouponWorkCode IS NOT NULL), '{}')     "couponWorkIds",
    COALESCE(string_agg(CW.Name, ', '), '')                                                               "couponWorkNames",
    COALESCE(array_agg(MSF.Cabinet) FILTER (WHERE MSF.Cabinet IS NOT NULL), '{}')                         "cabinets",
    COALESCE(array_agg(MSF.MedSanProcGroupCode) FILTER (WHERE MSF.MedSanProcGroupCode IS NOT NULL), '{}') "sanProcGroupIds",
    COALESCE(string_agg(SG.Name, ', '), '')                                                               "sanProcGroupNames",
    MSP.CiszResourceValueCodeMedicalServiceType                                                           "ciszNameId",
    CRV.Name                                                                                              "ciszName"
FROM MedSanProc MSP
LEFT JOIN MedSanProcFeature MSF ON MSF.MedSanProcCode = MSP.MedSanProcCode
LEFT JOIN Branch B ON B.BranchCode = MSF.BranchCode
LEFT JOIN Department D ON D.DepartmentCode = MSF.DepartmentCode
LEFT JOIN MedProfession P ON P.MedProfessionCode = MSF.MedProfessionCode
LEFT JOIN MedDoctor DR ON DR.MedDoctorCode = MSF.MedDoctorCode
LEFT JOIN MedCouponWork CW ON CW.MedCouponWorkCode = MSF.MedCouponWorkCode
LEFT JOIN MedSanProcGroup SG ON SG.MedSanProcGroupCode = MSF.MedSanProcGroupCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MSP.CiszResourceValueCodeMedicalServiceType
WHERE MSP.EnterpriseCode = $enterpriseId
GROUP BY MSP.MedSanProcCode, MSP.Name, MSP.Paid, MSP.Active, MSP.AmountPerDay, MSP.AmountPerCourse, MSP.Interval, MSP.EnterpriseCode, CRV.Name
ORDER BY MSP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $rows = $stmt->fetchAll(); $arrayFields = [ 'branchId', 'departmentId', 'professionIds', 'doctorIds', 'couponWorkIds', 'cabinets', 'sanProcGroupIds' ]; foreach ($rows as &$row) { foreach ($arrayFields as $field) { if (isset($row[$field])) { $val = trim($row[$field], '{}'); $row[$field] = $val !== '' ? explode(',', $val) : []; $row[$field] = array_map(fn($v) => trim($v, '"'), $row[$field]); } else { $row[$field] = []; } } } return $rows; } public function createProcedures($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); try { $db->beginTransaction(); $sql = "INSERT INTO MedSanProc
            (Name, Paid, Active, AmountPerDay, AmountPerCourse, Interval, EnterpriseCode, CiszResourceValueCodeMedicalServiceType)
            VALUES (:name, :isPaid, :active, :amountPerDay, :amountPerCourse, :interval, :enterpriseCode, :ciszNameId)"; $stmt = $db->prepare($sql); $stmt->execute([ ':name' => $params->name, ':isPaid' => $params->isPaid ? 1 : 0, ':active' => $params->active ? 1 : 0, ':amountPerDay' => $params->amountPerDay, ':amountPerCourse' => $params->amountPerCourse, ':interval' => $params->interval, ':enterpriseCode' => $enterpriseId, ':ciszNameId' => $params->ciszNameId, ]); $procId = (int)$db->lastInsertId('seq_medsanproc'); $this->saveProcedureFeatures($procId, $params, $db); $db->commit(); return ['success' => true, 'id' => $procId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'id' => null, 'message' => $e->getMessage()]; } } public function updateProcedures($params) { $db = $this->db ?: $this->db = connectDb(); $procId = (int)$params->id; try { $db->beginTransaction(); $updates = []; $paramsArray = [':id' => $procId]; if (isset($params->name)) { $updates[] = 'Name = :name'; $paramsArray[':name'] = $params->name; } if (isset($params->isPaid)) { $updates[] = 'Paid = :isPaid'; $paramsArray[':isPaid'] = $params->isPaid ? 1 : 0; } if (isset($params->active)) { $updates[] = 'Active = :active'; $paramsArray[':active'] = $params->active ? 1 : 0; } if (isset($params->amountPerDay)) { $updates[] = 'AmountPerDay = :amountPerDay'; $paramsArray[':amountPerDay'] = $params->amountPerDay; } if (isset($params->amountPerCourse)) { $updates[] = 'AmountPerCourse = :amountPerCourse'; $paramsArray[':amountPerCourse'] = $params->amountPerCourse; } if (isset($params->interval)) { $updates[] = 'Interval = :interval'; $paramsArray[':interval'] = $params->interval; } if (isset($params->ciszNameId)) { $updates[] = 'CiszResourceValueCodeMedicalServiceType = :ciszNameId'; $paramsArray[':ciszNameId'] = $params->ciszNameId; } if (!empty($updates)) { $sql = "UPDATE MedSanProc
                   SET " . implode(', ', $updates) . "
                 WHERE MedSanProcCode = :id"; $stmt = $db->prepare($sql); $stmt->execute($paramsArray); } $featureFields = [ 'branchId' => 'BranchCode', 'departmentId' => 'DepartmentCode', 'professionIds' => 'MedProfessionCode', 'doctorIds' => 'MedDoctorCode', 'couponWorkIds' => 'MedCouponWorkCode', 'cabinets' => 'Cabinet', 'sanProcGroupIds'=> 'MedSanProcGroupCode' ]; foreach ($featureFields as $paramKey => $dbField) { $incoming = []; if (!empty($params->{$paramKey}) && is_array($params->{$paramKey})) { foreach ($params->{$paramKey} as $value) { if ($dbField !== 'Cabinet') { $value = (int)$value; if ($value <= 0) continue; } else { $value = trim((string)$value); if ($value === '') continue; } $incoming[] = $value; } } $incoming = array_values(array_unique($incoming)); $sql = "SELECT $dbField
                  FROM MedSanProcFeature
                 WHERE MedSanProcCode = :procId
                   AND $dbField IS NOT NULL"; $stmt = $db->prepare($sql); $stmt->execute([':procId' => $procId]); $existing = $stmt->fetchAll(PDO::FETCH_COLUMN); $existing = array_map(function($v) use ($dbField) { return $dbField === 'Cabinet' ? trim((string)$v) : (int)$v; }, $existing); $existing = array_values(array_unique($existing)); $toDelete = array_diff($existing, $incoming); $toInsert = array_diff($incoming, $existing); if ($toDelete) { $in = implode(',', array_fill(0, count($toDelete), '?')); $sql = "DELETE FROM MedSanProcFeature
                     WHERE MedSanProcCode = ?
                       AND $dbField IN ($in)"; $stmt = $db->prepare($sql); $stmt->execute(array_merge([$procId], $toDelete)); } if ($toInsert) { $this->saveProcedureFeatureField($procId, $dbField, $toInsert, $db); } } $db->commit(); return ['success' => true, 'id' => $procId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'id' => null, 'message' => $e->getMessage()]; } } private function saveProcedureFeatureField($procId, $dbField, array $values, $db) { foreach ($values as $value) { $sql = "INSERT INTO MedSanProcFeature (MedSanProcCode, $dbField)
                VALUES (:procId, :value)"; $stmt = $db->prepare($sql); $stmt->execute([ ':procId' => $procId, ':value' => $value ]); } } private function saveProcedureFeatures($procId, $params, $db) { $featureFields = [ 'branchId' => 'BranchCode', 'departmentId' => 'DepartmentCode', 'professionIds' => 'MedProfessionCode', 'doctorIds' => 'MedDoctorCode', 'couponWorkIds' => 'MedCouponWorkCode', 'cabinets' => 'Cabinet', 'sanProcGroupIds' => 'MedSanProcGroupCode' ]; foreach ($featureFields as $paramKey => $dbField) { if (!empty($params->{$paramKey}) && is_array($params->{$paramKey})) { $stmt = $db->prepare("INSERT INTO MedSanProcFeature (MedSanProcCode, $dbField) VALUES (:procId, :value)"); foreach ($params->{$paramKey} as $value) { if ($dbField !== 'Cabinet') { $value = (int)$value; if ($value <= 0) continue; } else { $value = trim((string)$value); if ($value === '') continue; } $stmt->execute([ ':procId' => $procId, ':value' => $value ]); } } } } public function deleteProcedures($params) { $db = $this->db ?: $this->db = connectDb(); $procId = (int)$params->id; $db->beginTransaction(); try { $stmt = $db->prepare("DELETE FROM MedSanProc WHERE MedSanProcCode = :procId"); $stmt->execute([':procId' => $procId]); $db->commit(); return true; } catch (PDOException $e) { $db->rollBack(); if ($e->getCode() === '23503') { throw new Exception('Процедура используется в шаблоне или направлении и не может быть удалена'); } throw $e; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function readProcedureIntervals($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id) || !is_numeric($params->id)) { throw new Exception("Не указан id процедуры для выборки интервалов"); } $procId = (int)$params->id; $sql = <<<EOT
WITH ManualIntervals AS (
    SELECT
        COALESCE(array_agg(MPI.MedsanprocIntervalCode) FILTER (WHERE MPI.MedsanprocIntervalCode  IS NOT NULL), '{}') "medSanProcIntervalId",
        NULL                                 "medSanProcGroupIntervalId",
        MPI.Medsanproccode1                  "procFrom",
        COALESCE(array_agg(MPI.Medsanproccode2) FILTER (WHERE MPI.Medsanproccode2  IS NOT NULL), '{}')     "procTo",
        MPI.Interval                         "intervalMinutes",
        1                                    "type",
        COALESCE(string_agg(MP.Name, ', '), '')                                                            "procToNames"
    FROM MedSanProcInterval MPI
    JOIN MedSanProc MP ON MP.MedSanProcCode = MPI.MedSanProcCode2
    WHERE MPI.Medsanproccode1 = :procId
    GROUP BY MPI.Medsanproccode1, MPI.Interval
),
GroupIntervals AS (
    SELECT
        NULL::integer[]                      "medSanProcIntervalId",
        GI.MedsanprocGroupIntervalCode       "medSanProcGroupIntervalId",
        PFFrom.Medsanproccode                "procFrom",
        COALESCE(array_agg(PFTo.Medsanproccode) FILTER (WHERE PFTo.Medsanproccode  IS NOT NULL), '{}')     "procTo",
        GI.Interval                           "intervalMinutes",
        2                                     "type",
        COALESCE(string_agg(MP.Name, ', '), '')                                                            "procToNames"
    FROM MedSanProcGroupInterval GI
    JOIN MedSanProcFeature PFFrom
        ON PFFrom.Medsanprocgroupcode = GI.Medsanprocgroupcode1
    JOIN MedSanProcFeature PFTo
        ON PFTo.Medsanprocgroupcode = GI.Medsanprocgroupcode2
    JOIN MedSanProc MP ON MP.MedSanProcCode = PFTo.MedSanProcCode
    WHERE PFFrom.Medsanproccode = :procId
    GROUP BY PFFrom.Medsanproccode, GI.MedsanprocGroupIntervalCode
)
SELECT *
FROM ManualIntervals

UNION ALL

SELECT *
FROM GroupIntervals
ORDER BY "procFrom", "procTo", "type"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':procId' => $procId]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); $arrayFields = [ 'procTo', 'medSanProcIntervalId' ]; foreach ($rows as &$row) { foreach ($arrayFields as $field) { if (!isset($row[$field])) { $row[$field] = []; continue; } $val = trim($row[$field], '{}'); if ($field === 'medSanProcIntervalId' && $row['type'] === 2) { $row[$field] = null; } else { $row[$field] = $val !== '' ? array_map('intval', explode(',', $val)) : []; } } $row['id'] = random_int(1000000, 9999999); $row['procFrom'] = (int)$row['procFrom']; $row['intervalMinutes'] = (int)$row['intervalMinutes']; $row['type'] = (int)$row['type']; $row['medSanProcIntervalId'] = $row['medSanProcIntervalId'] !== null ? (int)$row['medSanProcIntervalId'] : null; $row['medSanProcGroupIntervalId'] = $row['medSanProcGroupIntervalId'] !== null ? (int)$row['medSanProcGroupIntervalId'] : null; } return $rows; } public function readAvailableProcedures($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->procId) || !is_numeric($params->procId)) { throw new Exception("Не указан procId"); } $procId = (int)$params->procId; $sql = <<<EOT
SELECT p.Medsanproccode "id", p.Name "name"
FROM MedSanProc p
WHERE p.Medsanproccode NOT IN (
    SELECT unnest(COALESCE(array_agg(mpi.Medsanproccode2), '{}'))
    FROM MedSanProcInterval mpi
    WHERE mpi.Medsanproccode1 = :procId
    ORDER BY p.Name
  )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':procId' => $procId]); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function createProcedureIntervals($params) { $db = $this->db ?: $this->db = connectDb(); $procFrom = (int)$params->procFrom; $interval = (int)$params->intervalMinutes; $insertedIds = []; try { $db->beginTransaction(); $sql = "INSERT INTO MedSanProcInterval (Medsanproccode1, Medsanproccode2, Interval)
                VALUES (:procFrom, :procTo, :interval)"; $stmt = $db->prepare($sql); foreach ($params->procTo as $procTo) { $procToId = (int)$procTo; if ($procToId <= 0) continue; $stmt->execute([ ':procFrom' => $procFrom, ':procTo' => $procToId, ':interval' => $interval ]); $insertedIds[] = (int)$db->lastInsertId('seq_medsanprocinterval'); } $db->commit(); return ['success' => true, 'insertedIds' => $insertedIds, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'insertedIds' => [], 'message' => $e->getMessage()]; } } public function updateProcedureIntervals($params = null) { $db = $this->db ?: $this->db = connectDb(); $procFrom = $params->procFrom ?? null; $interval = $params->intervalMinutes ?? null; $procToList = $params->procTo ?? []; $deletedProcTo = $params->deletedProcTo ?? []; if (!$procFrom || $interval === null || !is_array($procToList)) { throw new Exception('Некорректные данные'); } $stmt = $db->prepare("SELECT * FROM medsanprocinterval WHERE medsanproccode1 = ?"); $stmt->execute([$procFrom]); $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC); $existingMap = []; try { $db->beginTransaction(); foreach ($existingRows as $row) { $existingMap[$row['medsanproccode2']] = $row; } foreach ($procToList as $procTo) { if (isset($existingMap[$procTo])) { if ($existingMap[$procTo]['interval'] != $interval) { $stmt = $db->prepare("UPDATE medsanprocinterval SET interval = ? WHERE medsanprocintervalcode = ?"); $stmt->execute([$interval, $existingMap[$procTo]['medsanprocintervalcode']]); } } else { $stmt = $db->prepare("INSERT INTO medsanprocinterval (medsanproccode1, medsanproccode2, interval) VALUES (?, ?, ?)"); $stmt->execute([$procFrom, $procTo, $interval]); } } if (!empty($deletedProcTo)) { $inPlaceholders = implode(',', array_fill(0, count($deletedProcTo), '?')); $stmt = $db->prepare("DELETE FROM medsanprocinterval WHERE medsanproccode1 = ? AND medsanproccode2 IN ($inPlaceholders)"); $stmt->execute(array_merge([$procFrom], $deletedProcTo)); } $db->commit(); return ['success' => true, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProcedureIntervals($params) { $db = $this->db ?: $this->db = connectDb(); $procFrom = $params->procFrom ?? null; $deletedProcTo = $params->deletedProcTo ?? []; if (!$procFrom || !is_array($deletedProcTo) || empty($deletedProcTo)) { return ['success' => false, 'error' => 'Некорректные данные для удаления']; } try { $inPlaceholders = implode(',', array_fill(0, count($deletedProcTo), '?')); $stmt = $db->prepare("DELETE FROM medsanprocinterval WHERE medsanproccode1 = ? AND medsanproccode2 IN ($inPlaceholders)"); $stmt->execute(array_merge([$procFrom], $deletedProcTo)); return ['success' => true]; } catch (Exception $e) { return ['success' => false, 'error' => $e->getMessage()]; } } public function readDoctorCabinets() { $db = $this->db ?: $this->db = connectDb(); $result = selectSql($db, <<<SQL
        SELECT
        C.Cabinet AS cabinet,
        STRING_AGG(DISTINCT Doc.MedDoctorCode, ', ') AS doctors
        FROM
        MedCabinet C
        LEFT JOIN (
        SELECT Cabinet, MedDoctorCode
        FROM MedCouponGraph
        UNION ALL
        SELECT MPGW.Cabinet, MPG.MedDoctorCode
        FROM MedCouponGraphWork MPGW
        INNER JOIN MedCouponGraph MPG
        ON MPG.medCouponGraphCode = MPGW.medCouponGraphCode
        ) AS Doc
        ON Doc.Cabinet = C.Cabinet
        GROUP BY C.Cabinet
        ORDER BY C.Cabinet;
        SQL)
; return $result; } public function findDuplicateName($params = null) { $db = $this->db ?: $this->db = connectDb(); $name = isEmptyProperty($params, 'name'); $id = isEmptyProperty($params, 'id'); if (!$name) { return false; } $name = trim($name); $name = preg_replace('/[\s\x{00A0}\x{2000}-\x{200B}\x{FEFF}]+/u', ' ', $name); if ($id) { $sql = "SELECT 1
                FROM MedSanProc MSP
                WHERE TRIM(MSP.Name) = :name AND MSP.MedSanProcCode <> :id
                LIMIT 1"; $stmt = $db->prepare($sql); $stmt->bindParam(':name', $name, PDO::PARAM_STR); $stmt->bindParam(':id', $id, PDO::PARAM_INT); } else { $sql = "SELECT 1
                FROM MedSanProc MSP
                WHERE TRIM(MSP.Name) = :name
                LIMIT 1"; $stmt = $db->prepare($sql); $stmt->bindParam(':name', $name, PDO::PARAM_STR); } $stmt->execute(); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ? true : false; } public function readProcedureCiszItems($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
       MSPR.MedSanProcRuleCode AS "id",
       MSPR.MedSanProcCode AS "procedureId",
       MSPR.CiszResourceValueCodePhysParam AS "paramId",
       CRV.Name AS "paramName",
       CRV.Code AS "paramCode",
       MSPR.Comparator AS "operator",
       MSPR.ValueNumber AS "paramValue",
       MSPR.CiszResourceValueCodeUnit AS "unit",
       CRVU.Name AS "unitName",
       COALESCE(
            NULLIF(
                CASE
                    WHEN MSPR.Comparator = 'ad' THEN '='
                    ELSE MSPR.Comparator
                END,
                ''
            ) || ' ',
            ''
        ) || trim(trailing '.' from trim(trailing '0' from MSPR.ValueNumber::text)) AS "fullValue"
FROM MedSanProcRule MSPR
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MSPR.CiszResourceValueCodePhysParam
LEFT JOIN CiszResourceValue CRVU ON CRVU.CiszResourceValueCode = MSPR.CiszResourceValueCodeUnit
WHERE MSPR.MedSanProcCode = ?
EOT;
$stmt = $db->prepare($sql); $procedureId = isUndefinedProperty($params, 'procedureId'); $stmt->execute([ $procedureId ]); return $stmt->fetchAll(); } public function createProcedureCiszItems($records) { $db = $this->db ?: $this->db = connectDb(); $operatorMap = [ ')' => '>', '(' => '<', ')=' => '>=', '(=' => '<=' ]; $sql = <<< "EOT"
    INSERT INTO MedSanProcRule (MedSanProcCode, CiszResourceValueCodePhysParam, Comparator, ValueNumber, CiszResourceValueCodeUnit)
    VALUES (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $operator = isUndefinedProperty($row, 'operator'); $operator = $operatorMap[$operator] ?? $operator; $stmt->execute([ isUndefinedProperty($row, 'procedureId'), isUndefinedProperty($row, 'paramId'), $operator, isUndefinedProperty($row, 'paramValue'), isUndefinedProperty($row, 'unit') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedSanProcRule'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateProcedureCiszItems($records) { $db = $this->db ?: $this->db = connectDb(); $operatorMap = [ ')' => '>', '(' => '<', ')=' => '>=', '(=' => '<=' ]; $sql = <<<"EOT"
UPDATE MedSanProcRule SET
       MedSanProcCode = CASE WHEN ? = 1 THEN ? ELSE MedSanProcCode END,
       CiszResourceValueCodePhysParam = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodePhysParam END,
       Comparator = CASE WHEN ? = 1 THEN ? ELSE Comparator END,
       ValueNumber = CASE WHEN ? = 1 THEN ? ELSE ValueNumber END,
       CiszResourceValueCodeUnit = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeUnit END
WHERE MedSanProcRuleCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $operator = isUndefinedProperty($row, 'operator'); $operator = $operatorMap[$operator] ?? $operator; $stmt->execute([ propertyExistsSQL($row, 'procedureId'), isUndefinedProperty($row, 'procedureId'), propertyExistsSQL($row, 'paramId'), isUndefinedProperty($row, 'paramId'), propertyExistsSQL($row, 'operator'), $operator, propertyExistsSQL($row, 'paramValue'), isUndefinedProperty($row, 'paramValue'), propertyExistsSQL($row, 'unit'), isUndefinedProperty($row, 'unit'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteProcedureCiszItems($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedSanProcRule', 'MedSanProcRuleCode', $records); } public function getAllowedCiszCodes($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    CRV.Code
FROM CiszResourceValue CRV
JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = 'PhysicalParameters'
WHERE NOT EXISTS (
    SELECT 1
    FROM MedSanProcRule MSPR
    WHERE MSPR.MedSanProcCode = ?
      AND MSPR.CiszResourceValueCodePhysParam = CRV.CiszResourceValueCode
)
EOT;
$stmt = $db->prepare($sql); $procedureId = isUndefinedProperty($params, 'procedureId'); $stmt->execute([ $procedureId ]); return $stmt->fetchAll(); } } handleJsonRpc('med_common_procedures'); 