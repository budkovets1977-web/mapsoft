<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_procedureSendLog { const MAX_PAGE_SIZE = 25; private $db; public function readProcedureSendLog($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $patient = isEmptyProperty($params, 'patientId'); $doctor = isEmptyProperty($params, 'doctorId'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $enterpriseId = (int) getSessionValue('mapEnterpriseCode'); $state = isEmptyProperty($params, 'state'); $isCard = isEmptyProperty($params, 'isCard'); $sort = $this->getSort(isEmptyProperty($params, 'sort'), isEmptyProperty($params, 'dir')); $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int)(($page - 1) * $limit); $start = $start < 0 ? 0 : $start; if ($isCard) { $sqlCard = <<<"EOT"
    MSPL.MedSanProcLogCode                              "recordId",
    MSPL.DocumentDate                                   "parentDate",
    MSPL.DocumentDate                                   "recordDate",
    COALESCE(MSPL.MedSanProcLogCode, '')::text || '-'   "path",
    27                                                  "recordType",
    null                                                "parentId",
    null                                                "parentType",
    2                                                   "sortType",
    fn_cmFIO(MD.ManCode,1)                              "doctorInitials",
EOT;
} else { $sqlCard = ''; } $totalCouponsSubquery = <<<SQL
(
    SELECT COALESCE(SUM(R.amount), 0)
    FROM MedSanProcLogRow R
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
)
SQL;
$orderCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCoupon MC ON MC.MedCouponCode = C.MedCouponCode
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
      AND (MC.CouponState IN (1, 2))
)
SQL;
$visitCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCouponAdd MCA ON MCA.MedCouponCode = C.MedCouponCode AND MCA.MedLogServiceCode IS NULL
        AND MCA.Status = 11
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
)
SQL;
$sql = <<<"EOT"
SELECT
    $sqlCard
    MSPL.MedSanProcLogCode                   "id",
    MSPL.EnterpriseCode                      "enterpriseId",
    MSPL.DocumentNumber                      "documentNumber",
    MSPL.DocumentDate                        "documentDate",
    MSPL.StartDate                           "startDate",
    MSPL.FinishDate                          "finishDate",
    MSPL.MedDoctorCode                       "doctorId",
    fn_cmfio(MD.ManCode)                     "doctor",
    MP.Name                                  "doctorProfessionName",
    MSPL.MedPatientCode                      "patientId",
    fn_cmfio(MPT.ManCode)                    "patient",
    MPT.NumberHistorySickness                "cardNumber",
    MPS.DateOfBirth                          "dateOfBirth",
    fn_cmCalcAddress(MPS.manCode, 5)::text   "address",
    B.Name                                   "doctorBranch",
    $totalCouponsSubquery                    "totalCouponsCount",
    $orderCouponsSubquery                    "orderCouponsCount",
    $visitCouponsSubquery                    "visitCouponsCount",
    (MSPL.Attributes & 1) <> 0              AS "showCisz",
    (MSPL.Attributes & 2) <> 0              AS "allFinished",
    CL.Status                               AS "ciszStatus"
FROM MedSanProcLog MSPL
LEFT JOIN MedDoctor MD ON MD.meddoctorcode = MSPL.meddoctorcode
LEFT JOIN MedProfession MP ON MD.Medprofessioncode = MP.medprofessioncode
LEFT JOIN MedPatient MPT ON MPT.MedPatientCode = MSPL.MedPatientCode
LEFT JOIN ManPassport MPS ON MPT.ManCode = MPS.ManCode
LEFT JOIN DEPARTMENT D ON D.DepartmentCode = MD.DepartmentCode
LEFT JOIN Branch B ON B.BranchCode = D.BranchCode
LEFT JOIN CiszLink CL ON CL.TableName LIKE 'MedProcedure%' AND CL.PrimaryKey = MSPL.MedSanProcLogCode AND CL.Type = 1
WHERE 1=1
EOT;
if ($id) { $sql .= " AND MSPL.MedSanProcLogCode = $id"; } if ($patient) { $sql .= " AND MSPL.MedPatientCode = $patient"; } if ($doctor) { $sql .= " AND MSPL.MedDoctorCode = $doctor"; } if ($startDate) { $sql .= " AND MSPL.DocumentDate >= '$startDate'"; } if ($finishDate) { $sql .= " AND MSPL.DocumentDate <= '$finishDate'"; } if ($enterpriseId) { $sql .= " AND MSPL.EnterpriseCode <= '$enterpriseId'"; } if ($state) { $today = date('Y-m-d'); if ($state == 1) { $sql .= " AND (MSPL.FinishDate < '$today' OR $visitCouponsSubquery = $totalCouponsSubquery)"; } elseif ($state == 2) { $sql .= " AND MSPL.FinishDate >= '$today' AND $visitCouponsSubquery <> $totalCouponsSubquery"; } } $sqlTotal = "SELECT COUNT(*) AS total FROM ($sql) t"; $stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql .= " ORDER BY $sort LIMIT $limit OFFSET $start"; $root = new stdClass(); $root->total = $total; $root->items = selectSql($db, $sql); if ($isCard) { return $root->items; } else { return $root; } } private function getSort($sortstr, $dir = null) { if (isset($sortstr)) { $sorters = base64_decode($sortstr); $sorters = safe_json_decode($sorters, false); if((is_null($sorters)) && (!empty($dir))) { $it = new stdClass(); $it->property = $sortstr; $it->direction = $dir; $sorters = [$it]; } $sb = array(); foreach ($sorters as $sort) { switch ($sort->property) { case 'numberHistorySickness': $sb[] = "RIGHT(REPEAT(' ' , 20) || asa_trim(numberHistorySickness), 20) $sort->direction"; break; default: $sb[] = "\"$sort->property\" $sort->direction"; break; } } return implode(', ', $sb); } else { return '5 desc'; } } public function getNewNumber() { $db = $this->db ?: $this->db = connectDb(); $year = date('Y'); $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(documentNumber), 20)) as varchar(20))
    FROM
        MedSanProcLog
    WHERE
        EXTRACT(YEAR FROM documentDate) = :year
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "documentNumber"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(['year' => $year]); $row = fetchValue($stmt, 'documentNumber'); return $row; } public function isNumberExists($documentNumber, $excludeId = null) { $db = $this->db ?: $this->db = connectDb(); $year = date('Y'); $sql = <<<SQL
SELECT 1
FROM MedSanProcLog
WHERE documentNumber = :documentNumber
  AND EXTRACT(YEAR FROM documentDate) = :year
SQL;
$params = [ 'documentNumber' => $documentNumber, 'year' => $year ]; if ($excludeId) { $sql .= " AND MedSanProcLogCode <> :excludeId"; $params['excludeId'] = $excludeId; } $sql .= " LIMIT 1"; $stmt = $db->prepare($sql); $stmt->execute($params); return (bool) $stmt->fetch(PDO::FETCH_ASSOC); } public function createProcedureSendLog($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<< "EOT"
    INSERT INTO MedSanProcLog 
        (EnterpriseCode, DocumentNumber, DocumentDate, StartDate, FinishDate, MedDoctorCode, MedPatientCode, Attributes) 
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($params)) { $params = [$params]; } $ret = []; $db->beginTransaction(); try { foreach ($params as $row) { $documentNumber = isUndefinedProperty($row, 'documentNumber'); $attributes = 0; if (!empty($row->showCisz)) { $attributes |= 1; } if (!empty($row->allFinished)) { $attributes |= 2; } $stmt->execute([ $enterpriseId, $documentNumber, isUndefinedProperty($row, 'documentDate'), isEmptyProperty($row, 'startDate'), isEmptyProperty($row, 'finishDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'patientId'), $attributes ]); $newId = (int) $db->lastInsertId('seq_MedSanProcLog'); $param = new stdClass; $param->id = $newId; $ret = $this->readProcedureSendLog($param); } $db->commit(); } catch (Throwable $e) { $db->rollBack(); throw $e; } return $ret; } public function updateProcedureSendLog($params) { $db = $this->db ?: $this->db = connectDb(); try { $db->beginTransaction(); $paramsMap = [ 'documentNumber' => 'DocumentNumber', 'documentDate' => 'DocumentDate', 'startDate' => 'StartDate', 'finishDate' => 'FinishDate', 'doctorId' => 'MedDoctorCode', 'patientId' => 'MedPatientCode' ]; $updateFields = []; $binds = [':id' => $params->id]; foreach ($paramsMap as $key => $dbField) { if (isset($params->$key)) { $updateFields[] = "$dbField = :$key"; $binds[":$key"] = $params->$key; } } if (property_exists($params, 'showCisz') || property_exists($params, 'allFinished')) { $stmt = $db->prepare("
                SELECT Attributes
                FROM MedSanProcLog
                WHERE MedSanProcLogCode = :id
            "); $stmt->execute([':id' => $params->id]); $attributes = (int)$stmt->fetchColumn(); if (property_exists($params, 'showCisz')) { if ($params->showCisz) { $attributes |= 1; } else { $attributes &= ~1; } } if (property_exists($params, 'allFinished')) { if ($params->allFinished) { $attributes |= 2; } else { $attributes &= ~2; } } $updateFields[] = 'Attributes = :attributes'; $binds[':attributes'] = $attributes; } if (empty($updateFields)) { return ['success' => true, 'id' => $params->id]; } else { $ciszLinkUuid = selectSql($db, <<<SQL
                SELECT 
                    Cl.Uuid "uuid"
                FROM CiszLink CL
                WHERE CL.PrimaryKey = :patientId AND CL.TableName = :tableName
            SQL,
[ ':patientId' => $params->id, ':tableName' => 'MedProcedure' ]); if ($ciszLinkUuid) { executeSql($db, <<<SQL
                UPDATE CiszLink
                SET Status = 2
                WHERE TableName = 'MedProcedure'
                  AND PrimaryKey = :id
            SQL,
[ ':id' => $params->id ]); } } $sql = "UPDATE MedSanProcLog SET " . implode(', ', $updateFields) . " WHERE MedSanProcLogCode = :id"; $stmt = $db->prepare($sql); $stmt->execute($binds); $db->commit(); return ['success' => true, 'id' => $params->id]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProcedureSendLog($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int)$params->id; $sql = <<<"EOT"
    SELECT
        MC.MedCouponCode
    FROM MedSanProcLog ML
    JOIN MedSanProcLogRow MLR ON ML.MedSanProcLogCode = MLR.MedSanProcLogCode
    JOIN MedSanProcLogRowCoupon MLC ON MLC.MedSanProcLogRowCode = MLR.MedSanProcLogRowCode
    JOIN MedCoupon MC ON MC.MedCouponCode = MLC.MedCouponCode
    WHERE MLR.MedSanProcLogCode = :id
        AND (CAST(MC.OnDate AS TIMESTAMP) + MC.VisitTime::TIME) >= CURRENT_TIMESTAMP
        AND MC.CouponState IN (1, 2)
    EOT;
$stmtCoupons = $db->prepare($sql); $stmtCoupons->execute([':id' => $id]); $coupons = $stmtCoupons->fetchAll(PDO::FETCH_ASSOC); $scheduleMap = new med_reception_coupons(); $db->beginTransaction(); try { foreach ($coupons as $coupon) { $result = $scheduleMap->updateCoupons( (object)[ 'id' => $coupon['medcouponcode'], 'state' => 0, 'patientId' => null, 'triggerFlag' => null, 'orderTime' => null, ] ); } $stmt = $db->prepare("DELETE FROM MedSanProcLog WHERE MedSanProcLogCode = :id"); $stmt->execute([':id' => $id]); $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function readProcedureSendItems($params = null) { $db = $this->db ?: $this->db = connectDb(); $sendId = (int)$params->id; $totalCouponsSubquery = <<<SQL
(
    SELECT COALESCE(SUM(R.amount), 0)
    FROM MedSanProcLogRow R
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
      AND R.MedsanProcCode = MSLR.MedSanProcCode
      AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
)
SQL;
$orderCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCoupon MC ON MC.MedCouponCode = C.MedCouponCode
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
      AND (MC.CouponState IN (1, 2))
      AND R.MedsanProcCode = MSLR.MedSanProcCode
      AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
)
SQL;
$visitCouponsSubquery = <<<SQL
(
    SELECT COUNT(*)
    FROM MedSanProcLogRow R
    JOIN MedSanProcLogRowCoupon C ON C.MedSanProcLogRowCode = R.MedSanProcLogRowCode
    JOIN MedCouponAdd MCA ON MCA.MedCouponCode = C.MedCouponCode AND MCA.MedLogServiceCode IS NULL
        AND MCA.Status = 11
    WHERE R.MedSanProcLogCode = MSPL.MedSanProcLogCode
      AND R.MedsanProcCode = MSLR.MedSanProcCode
      AND R.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
)
SQL;
$sql = <<<EOT
SELECT
    MSLR.MedSanProcLogRowCode          "id",
    MSLR.MedSanProcLogCode             "sendId",
    MSLR.MedSanProcCode                "procedureId",
    MSP.Name                           "procedureName",
    MSLR.Amount                        "amount",
    MSLR.Periodicity                   "periodicity",
    MSLR.DayKind                       "dayKind",
    MSLR.MinWriteTime                  "minWriteTime",
    MSLR.MaxWriteTime                  "maxWriteTime",
    MSP.Paid                           "isPaid",
    $totalCouponsSubquery              "totalCouponsCount",
    $orderCouponsSubquery              "orderCouponsCount",
    $visitCouponsSubquery              "visitCouponsCount",
    MSLR.CiszResourceValueCodeServiceCategory  "healthCareCode",
    CRV.Name                                   "healthCareName",
    MSLR.DepartmentCode                        "departmentId",
    D.Name                                     "departmentName",
    MSLR.MedDiagnosisCode                      "diagnosisId",
    CASE 
        WHEN MSLR.MedDiagnosisCode IS NOT NULL 
            THEN MD.Cipher1 || ' - ' || MD.Name 
        END                                    "diagnosisName",
    MSLR.CiszResourceValueCodeProcResult       "result",
    MSLR.Conclusion                            "recommendation",
    MSLR.Note                                  "note",
    MSPLRA2.AnatomicCodes                      "anatomic"
FROM MedSanProcLog MSPL
JOIN MedSanProcLogRow MSLR ON MSPL.MedSanProcLogCode = MSLR.MedSanProcLogCode
LEFT JOIN MedSanProc MSP ON MSLR.MedSanProcCode = MSP.MedSanProcCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MSLR.CiszResourceValueCodeServiceCategory
LEFT JOIN Department D ON D.DepartmentCode = MSLR.DepartmentCode
LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MSLR.MedDiagnosisCode
LEFT JOIN (
    SELECT
        MSPLRA.MedSanProcLogRowCode,
        string_agg(
            MSPLRA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY MSPLRA.MedSanProcLogRowCode
        ) AS AnatomicCodes
    FROM MedSanProcLogRowAnatomic MSPLRA
    GROUP BY MSPLRA.MedSanProcLogRowCode
) MSPLRA2 ON MSPLRA2.MedSanProcLogRowCode = MSLR.MedSanProcLogRowCode
WHERE MSLR.MedSanProcLogCode = :sendId
ORDER BY MSP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':sendId' => $sendId]); $rows = $stmt->fetchAll(); foreach ($rows as &$row) { if (!empty($row['minWriteTime'])) { $row['minWriteTime'] = substr($row['minWriteTime'], 11, 5); } if (!empty($row['maxWriteTime'])) { $row['maxWriteTime'] = substr($row['maxWriteTime'], 11, 5); } } unset($row); return $rows; } public function createProcedureSendItems($paramsArray) { if (!is_array($paramsArray)) { $paramsArray = [$paramsArray]; } $db = $this->db ?: $this->db = connectDb(); $results = []; try { $sql = "INSERT INTO MedSanProcLogRow
            (MedSanProcLogCode, MedSanProcCode, Amount, Periodicity, DayKind, MinWriteTime, MaxWriteTime,
             CiszResourceValueCodeServiceCategory, DepartmentCode, MedDiagnosisCode,
             CiszResourceValueCodeProcResult, Conclusion, Note) 
            VALUES 
            (:sendId, :procedureId, :amount, :periodicity, :dayKind, :minWriteTime, :maxWriteTime,
             :healthCareCode, :departmentId, :diagnosisId,
             :result, :recommendation, :note)
            RETURNING MedSanProcLogRowCode"; $stmt = $db->prepare($sql); foreach ($paramsArray as $params) { $minTime = isset($params->minWriteTime) && $params->minWriteTime !== '' ? '1900-01-01 ' . date('H:i:s', strtotime($params->minWriteTime)) : null; $maxTime = isset($params->maxWriteTime) && $params->maxWriteTime !== '' ? '1900-01-01 ' . date('H:i:s', strtotime($params->maxWriteTime)) : null; $stmt->execute([ ':sendId' => $params->sendId, ':procedureId' => $params->procedureId, ':amount' => $params->amount, ':periodicity' => $params->periodicity, ':dayKind' => $params->dayKind, ':minWriteTime' => $minTime, ':maxWriteTime' => $maxTime, ':healthCareCode' => $params->healthCareCode ?? null, ':departmentId' => $params->departmentId ?? null, ':diagnosisId' => $params->diagnosisId ?? null, ':result' => $params->result ?? null, ':recommendation' => $params->recommendation ?? null, ':note' => $params->note ?? null ]); $newItemId = $stmt->fetchColumn(); if (isset($params->anatomic)) { $this->updateAnatomic((object)[ 'id' => $newItemId, 'items' => $params->anatomic ]); } $results[] = ['success' => true, 'id' => $newItemId]; } return $results; } catch (PDOException $e) { return ['success' => false, 'message' => $e->getMessage()]; } } public function updateProcedureSendItems($paramsArray) { if (!is_array($paramsArray)) { $paramsArray = [$paramsArray]; } $db = $this->db ?: $this->db = connectDb(); $results = []; try { $paramsMap = [ 'procedureId' => 'MedSanProcCode', 'amount' => 'Amount', 'periodicity' => 'Periodicity', 'dayKind' => 'DayKind', 'minWriteTime' => 'MinWriteTime', 'maxWriteTime' => 'MaxWriteTime', 'healthCareCode' => 'CiszResourceValueCodeServiceCategory', 'departmentId' => 'DepartmentCode', 'diagnosisId' => 'MedDiagnosisCode', 'result' => 'CiszResourceValueCodeProcResult', 'recommendation' => 'Conclusion', 'note' => 'Note' ]; foreach ($paramsArray as $params) { $updateFields = []; $binds = [':id' => $params->id]; foreach ($paramsMap as $key => $dbField) { if (property_exists($params, $key)) { $updateFields[] = "$dbField = :$key"; $binds[":$key"] = $params->$key; } } if (!empty($updateFields)) { $sql = "UPDATE MedSanProcLogRow SET " . implode(', ', $updateFields) . " WHERE MedSanProcLogRowCode = :id"; $stmt = $db->prepare($sql); $stmt->execute($binds); } if (property_exists($params, 'anatomic')) { $this->updateAnatomic((object)[ 'id' => $params->id, 'items' => $params->anatomic ]); } $results[] = ['success' => true, 'id' => $params->id]; } return ['items' => $results]; } catch (PDOException $e) { return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProcedureSendItems($paramsArray) { if (!is_array($paramsArray)) { $paramsArray = [$paramsArray]; } $db = $this->db ?: $this->db = connectDb(); try { $db->beginTransaction(); $stmt = $db->prepare("DELETE FROM MedSanProcLogRow WHERE MedSanProcLogRowCode = :id"); foreach ($paramsArray as $params) { if (!isset($params->id) || !is_numeric($params->id)) { throw new Exception("Не указан корректный ID для удаления"); } $id = (int)$params->id; $stmt->execute([':id' => $id]); } $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); return; } } public function readAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); if (!$items) { return []; } $sql = <<<SQL
SELECT
    CRV.CiszResourceValueCode AS "id",
    CRV.Name AS "name"
FROM CiszResourceValue CRV
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $procedureId = isEmptyProperty($params, 'id'); $items = isUndefinedProperty($params, 'items'); if (!$procedureId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedSanProcLogRowAnatomic
        WHERE MedSanProcLogRowCode = :procedureId
    "; $stmt = $db->prepare($sql); $stmt->execute(['procedureId' => $procedureId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedSanProcLogRowAnatomic
            (MedSanProcLogRowCode, CiszResourceValueCodeAnatomic)
            VALUES (:procedureId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'procedureId' => $procedureId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedSanProcLogRowAnatomic
            WHERE MedSanProcLogRowCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$procedureId], $toDelete)); } return true; } } if (!isset($notExecute)) { handleJsonRpc('med_reception_procedureSendLog'); } 