<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_proceduresTemplates { private $db; public function readProceduresTemplates($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
SELECT
    MSPT.MedSanProcTemplateCode               "id",
    MSPT.Name                                 "name",
    MSPT.EnterpriseCode                       "enterpriseId",
    MSPT.MinDayCount                          "minDayCount",
    MSPT.MaxDayCount                          "maxDayCount"
FROM MedSanProcTemplate MSPT
WHERE MSPT.EnterpriseCode = $enterpriseId
ORDER BY MSPT.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $rows = $stmt->fetchAll(); return $rows; } public function createProceduresTemplates($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); try { $db->beginTransaction(); $sql = "INSERT INTO MedSanProcTemplate (Name, EnterpriseCode, MinDayCount, MaxDayCount) 
                     VALUES (:name, :enterpriseCode, :minDayCount, :maxDayCount)
                     RETURNING MedSanProcTemplateCode"; $stmt = $db->prepare($sql); $maxDayCount = isset($params->maxDayCount) && $params->maxDayCount !== '' ? (int)$params->maxDayCount : null; $stmt->execute([ ':name' => trim($params->name), ':enterpriseCode' => $enterpriseId, ':minDayCount' => (int)$params->minDayCount, ':maxDayCount' => $maxDayCount ]); $newId = $stmt->fetchColumn(); $db->commit(); return ['success' => true, 'id' => $newId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function updateProceduresTemplates($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int)($params->id ?? $params->id ?? $params->MedSanProcTemplateCode ?? 0); if ($id <= 0) { return ['success' => false, 'message' => 'Missing id']; } $name = isset($params->name) ? trim($params->name) : null; $min = isset($params->minDayCount) ? (int)$params->minDayCount : null; $max = isset($params->maxDayCount) ? (int)$params->maxDayCount : null; $set = []; $bind = [':id' => $id]; if ($name !== null && $name !== '') { $set[] = 'Name = :name'; $bind[':name'] = $name; } if ($min !== null) { $set[] = 'MinDayCount = :minDayCount'; $bind[':minDayCount'] = $min; } if (array_key_exists('maxDayCount', (array)$params)) { if ($max === null) { $set[] = 'MaxDayCount = NULL'; } else { $set[] = 'MaxDayCount = :maxDayCount'; $bind[':maxDayCount'] = $max; } } if (!$set) { return ['success' => true, 'id' => $id, 'updated' => 0]; } $sql = "UPDATE MedSanProcTemplate
               SET " . implode(', ', $set) . "
             WHERE MedSanProcTemplateCode = :id"; try { $db->beginTransaction(); $stmt = $db->prepare($sql); $stmt->execute($bind); $db->commit(); return ['success' => true, 'id' => $id, 'updated' => $stmt->rowCount()]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProceduresTemplates($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int)$params->id; $db->beginTransaction(); try { $stmt = $db->prepare("DELETE FROM MedSanProcTemplate WHERE MedSanProcTemplateCode = :id"); $stmt->execute([':id' => $id]); $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function readProceduresTemplatesItems($params = null) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id) || !is_numeric($params->id)) { throw new Exception("Не указан id группы для выборки"); } $templateId = (int)$params->id; $sql = <<<EOT
SELECT
    MSTI.MedSanProcTemplateInCode      "id",
    MSTI.MedSanProcTemplateCode        "templateId",
    MSTI.MedSanProcCode                "procedureId",
    MSP.Name                           "procedureName",
    MSTI.Amount                        "amount",
    MSTI.Periodicity                   "periodicity",
    MSTI.DayKind                       "dayKind",
    MSTI.MinWriteTime                  "minWriteTime",
    MSTI.MaxWriteTime                  "maxWriteTime",
    MSP.Paid                           "isPaid"
FROM MedSanProcTemplateIn MSTI
LEFT JOIN MedSanProc MSP ON MSTI.MedSanProcCode = MSP.MedSanProcCode
WHERE MSTI.MedSanProcTemplateCode = :templateId
ORDER BY MSP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':templateId' => $templateId]); $rows = $stmt->fetchAll(); foreach ($rows as &$row) { if (!empty($row['minWriteTime'])) { $row['minWriteTime'] = substr($row['minWriteTime'], 11, 5); } if (!empty($row['maxWriteTime'])) { $row['maxWriteTime'] = substr($row['maxWriteTime'], 11, 5); } } unset($row); return $rows; } public function readAvailableTemplateItems($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->templateId) || !is_numeric($params->templateId)) { throw new Exception("Не указан templateId"); } $templateId = (int)$params->templateId; $sql = <<<EOT
SELECT MSP.MedSanProcCode "id", MSP.Name "name", MSP.Paid "isPaid"
FROM MedSanProc MSP
WHERE MSP.MedSanProcCode NOT IN (
    SELECT unnest(COALESCE(array_agg(MSTI.MedSanProcCode), '{}'))
    FROM MedSanProcTemplateIn MSTI
    WHERE MSTI.MedSanProcTemplateCode = :templateId
  ) 
  AND MSP.Active = 1
ORDER BY MSP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':templateId' => $templateId]); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function createProceduresTemplatesItems($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->procedureId) || !is_numeric($params->procedureId)) { throw new Exception("Не указан код процедуры"); } try { $db->beginTransaction(); $minTime = isset($params->minWriteTime) && $params->minWriteTime !== '' ? '1900-01-01 ' . $params->minWriteTime : null; $maxTime = isset($params->maxWriteTime) && $params->maxWriteTime !== '' ? '1900-01-01 ' . $params->maxWriteTime : null; $sql = "INSERT INTO MedSanProcTemplateIn 
                (MedSanProcTemplateCode, MedSanProcCode, Amount, Periodicity, DayKind, MinWriteTime, MaxWriteTime)
                VALUES 
                (:templateId, :procedureId, :amount, :periodicity, :dayKind, :minWriteTime, :maxWriteTime)
                RETURNING MedSanProcTemplateInCode"; $stmt = $db->prepare($sql); $stmt->execute([ ':templateId' => (int)$params->templateId, ':procedureId' => (int)$params->procedureId, ':amount' => isset($params->amount) ? (float)$params->amount : null, ':periodicity' => isset($params->periodicity) ? (int)$params->periodicity : null, ':dayKind' => isset($params->dayKind) ? trim($params->dayKind) : null, ':minWriteTime' => $minTime, ':maxWriteTime' => $maxTime ]); $newId = $stmt->fetchColumn(); $db->commit(); return ['success' => true, 'id' => $newId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function updateProceduresTemplatesItems($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id) || !is_numeric($params->id)) { throw new Exception("Не указан id записи для обновления"); } $mapping = [ 'MedSanProcCode' => 'procedureId', 'Amount' => 'amount', 'Periodicity' => 'periodicity', 'DayKind' => 'dayKind', 'MinWriteTime' => 'minWriteTime', 'MaxWriteTime' => 'maxWriteTime' ]; $setParts = []; $bindings = [':id' => (int)$params->id]; foreach ($mapping as $dbField => $paramField) { if (isset($params->$paramField)) { if (in_array($dbField, ['MinWriteTime', 'MaxWriteTime'])) { $timeValue = $params->$paramField; $bindings[":$dbField"] = $timeValue !== '' ? '1900-01-01 ' . $timeValue : null; } else { $bindings[":$dbField"] = $params->$paramField; } $setParts[] = "$dbField = :$dbField"; } } if (empty($setParts)) { return ['success' => true]; } $sql = "UPDATE MedSanProcTemplateIn
            SET " . implode(', ', $setParts) . "
            WHERE MedSanProcTemplateInCode = :id"; try { $stmt = $db->prepare($sql); $stmt->execute($bindings); return ['success' => true, 'message' => null]; } catch (PDOException $e) { return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProceduresTemplatesItems($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int)$params->id; $db->beginTransaction(); try { $stmt = $db->prepare("DELETE FROM MedSanProcTemplateIn WHERE MedSanProcTemplateInCode = :id"); $stmt->execute([':id' => $id]); $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function findDuplicateName($params = null) { $db = $this->db ?: $this->db = connectDb(); $name = isEmptyProperty($params, 'name'); $id = isEmptyProperty($params, 'id'); if (!$name) { return false; } $name = trim($name); $name = preg_replace('/[\s\x{00A0}\x{2000}-\x{200B}\x{FEFF}]+/u', ' ', $name); if ($id) { $sql = "SELECT 1
                FROM MedSanProcTemplate MSPT
                WHERE TRIM(MSPT.Name) = :name AND MSPT.MedSanProcTemplateCode <> :id
                LIMIT 1"; $stmt = $db->prepare($sql); $stmt->bindParam(':name', $name, PDO::PARAM_STR); $stmt->bindParam(':id', $id, PDO::PARAM_INT); } else { $sql = "SELECT 1
                FROM MedSanProcTemplate MSPT
                WHERE TRIM(MSPT.Name) = :name
                LIMIT 1"; $stmt = $db->prepare($sql); $stmt->bindParam(':name', $name, PDO::PARAM_STR); } $stmt->execute(); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ? true : false; } } handleJsonRpc('med_common_proceduresTemplates');