<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_proceduresGroups { private $db; public function readProceduresGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
SELECT
    MSG.MedSanProcGroupCode               "id",
    MSG.Name                              "name",
    MSG.EnterpriseCode                    "enterpriseId",
    COALESCE(array_agg(MSF.MedSanProcCode) FILTER (WHERE MSF.MedSanProcGroupCode IS NOT NULL), '{}')      "procedureIds",
    COALESCE(array_agg(MSP.Name) FILTER (WHERE MSP.Name IS NOT NULL), '{}')                               "procedureNames"
FROM MedSanProcGroup MSG
LEFT JOIN MedSanProcFeature MSF ON MSF.MedSanProcGroupCode = MSG.MedSanProcGroupCode
LEFT JOIN MedSanProc MSP ON MSF.MedSanProcCode = MSP.MedSanProcCode
WHERE MSG.EnterpriseCode = $enterpriseId
GROUP BY MSG.MedSanProcGroupCode, MSG.Name
ORDER BY MSG.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $rows = $stmt->fetchAll(); $arrayFields = [ 'procedureIds', 'procedureNames', ]; foreach ($rows as &$row) { foreach ($arrayFields as $field) { if (isset($row[$field])) { $val = trim($row[$field], '{}'); $row[$field] = $val !== '' ? explode(',', $val) : []; $row[$field] = array_map(fn($v) => trim($v, '"'), $row[$field]); } else { $row[$field] = []; } } } return $rows; } public function createProceduresGroups($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); if (!isset($params->name) || trim($params->name) === '') { return ['success' => false, 'id' => null, 'message' => 'Не указано наименование группы']; } $procedureIds = $params->procedureIds ?? []; try { $db->beginTransaction(); $sqlGroup = "INSERT INTO MedSanProcGroup (Name, EnterpriseCode) 
                     VALUES (:name, :enterpriseCode) 
                     RETURNING MedSanProcGroupCode"; $stmtGroup = $db->prepare($sqlGroup); $stmtGroup->execute([ ':name' => trim($params->name), ':enterpriseCode' => $enterpriseId ]); $groupId = $stmtGroup->fetchColumn(); if (!empty($procedureIds)) { $sqlFeature = "INSERT INTO MedSanProcFeature (MedSanProcGroupCode, MedSanProcCode) VALUES (:groupId, :procId)"; $stmtFeature = $db->prepare($sqlFeature); foreach ($procedureIds as $procId) { $stmtFeature->execute([ ':groupId' => $groupId, ':procId' => $procId ]); } } $db->commit(); return ['success' => true, 'id' => (int)$groupId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'id' => null, 'message' => $e->getMessage()]; } } public function updateProceduresGroups($params) { $db = $this->db ?: $this->db = connectDb(); $groupId = (int)($params->id ?? 0); if ($groupId <= 0) { return ['success' => false, 'message' => 'Некорректный идентификатор группы']; } try { $db->beginTransaction(); if (isset($params->name) && trim($params->name) !== '') { $sql = "UPDATE MedSanProcGroup 
                       SET Name = :name 
                     WHERE MedSanProcGroupCode = :id"; $stmt = $db->prepare($sql); $stmt->execute([ ':name' => trim($params->name), ':id' => $groupId ]); } $incoming = []; if (!empty($params->procedureIds) && is_array($params->procedureIds)) { foreach ($params->procedureIds as $procId) { $procId = (int)$procId; if ($procId > 0) { $incoming[] = $procId; } } } $incoming = array_values(array_unique($incoming)); $sql = "SELECT MedSanProcCode 
                  FROM MedSanProcFeature 
                 WHERE MedSanProcGroupCode = :groupId"; $stmt = $db->prepare($sql); $stmt->execute([':groupId' => $groupId]); $existing = $stmt->fetchAll(PDO::FETCH_COLUMN); $existing = array_map('intval', $existing); $toDelete = array_diff($existing, $incoming); $toInsert = array_diff($incoming, $existing); if (!empty($toDelete)) { $in = implode(',', array_fill(0, count($toDelete), '?')); $sql = "DELETE FROM MedSanProcFeature 
                     WHERE MedSanProcGroupCode = ? 
                       AND MedSanProcCode IN ($in)"; $stmt = $db->prepare($sql); $stmt->execute(array_merge([$groupId], $toDelete)); } if (!empty($toInsert)) { $sql = "INSERT INTO MedSanProcFeature (MedSanProcGroupCode, MedSanProcCode) 
                    VALUES (:groupId, :procId)"; $stmt = $db->prepare($sql); foreach ($toInsert as $procId) { $stmt->execute([ ':groupId' => $groupId, ':procId' => $procId ]); } } $db->commit(); return ['success' => true, 'id' => $groupId]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProceduresGroups($params) { $db = $this->db ?: $this->db = connectDb(); $groupId = (int)$params->id; $db->beginTransaction(); try { $stmt = $db->prepare("DELETE FROM MedSanProcFeature WHERE MedSanProcGroupCode = :groupId"); $stmt->execute([':groupId' => $groupId]); $stmt = $db->prepare("DELETE FROM MedSanProcGroup WHERE MedSanProcGroupCode = :groupId"); $stmt->execute([':groupId' => $groupId]); $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function readProceduresGroupsIntervals($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id) || !is_numeric($params->id)) { throw new Exception("Не указан id группы для выборки интервалов"); } $groupId = (int)$params->id; $sql = <<<EOT
SELECT
    MSGI.MedSanProcGroupIntervalCode      "id",
    MSGI.MedSanProcGroupCode1             "procGroupFrom",
    MSGI.MedSanProcGroupCode2             "procGroupTo",
    MSG.Name                              "procGroupToName",
    MSGI.Interval                         "intervalMinutes"
FROM MedSanProcGroupInterval MSGI
LEFT JOIN MedSanProcGroup MSG ON MSGI.MedSanProcGroupCode2 = MSG.MedSanProcGroupCode
WHERE MSGI.MedSanProcGroupCode1 = :groupId
GROUP BY MSGI.MedSanProcGroupIntervalCode, MSG.Name
ORDER BY MSG.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':groupId' => $groupId]); $rows = $stmt->fetchAll(); return $rows; } public function readAvailableGroups($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->groupId) || !is_numeric($params->groupId)) { throw new Exception("Не указан groupId"); } $groupId = (int)$params->groupId; $sql = <<<EOT
SELECT g.MedSanProcGroupCode "id", g.Name "name"
FROM MedSanProcGroup g
WHERE g.MedSanProcGroupCode NOT IN (
    SELECT unnest(COALESCE(array_agg(mgi.MedSanProcGroupCode2), '{}'))
    FROM MedSanProcGroupInterval mgi
    WHERE mgi.MedSanProcGroupCode1 = :groupId
  )
ORDER BY g.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([':groupId' => $groupId]); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function createProceduresGroupsIntervals($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->procGroupFrom) || !is_numeric($params->procGroupFrom)) { return ['success' => false, 'message' => 'Не указан procGroupFrom']; } $procGroupFrom = (int)$params->procGroupFrom; $procGroupTo = (int)$params->procGroupTo; $interval = (int)$params->intervalMinutes; try { $db->beginTransaction(); $sql = "INSERT INTO MedSanProcGroupInterval 
                    (MedSanProcGroupCode1, MedSanProcGroupCode2, Interval) 
                VALUES 
                    (:procGroupFrom, :procGroupTo, :interval)"; $stmt = $db->prepare($sql); $stmt->execute([ ':procGroupFrom' => $procGroupFrom, ':procGroupTo' => $procGroupTo, ':interval' => $interval ]); $insertedId = $db->lastInsertId(); $db->commit(); return ['success' => true, 'id' => (int)$insertedId, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function updateProceduresGroupsIntervals($params) { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id) || !is_numeric($params->id)) { return ['success' => false, 'message' => 'Не указан id интервала']; } $id = (int)$params->id; $fields = []; $bindParams = [':id' => $id]; if (isset($params->procGroupTo)) { $fields[] = 'MedSanProcGroupCode2 = :procGroupTo'; $bindParams[':procGroupTo'] = (int)$params->procGroupTo; } if (isset($params->intervalMinutes)) { $fields[] = 'Interval = :interval'; $bindParams[':interval'] = (int)$params->intervalMinutes; } if (empty($fields)) { return ['success' => true, 'message' => 'Нет изменений']; } try { $db->beginTransaction(); $sql = "UPDATE MedSanProcGroupInterval
                SET " . implode(', ', $fields) . "
                WHERE MedSanProcGroupIntervalCode = :id"; $stmt = $db->prepare($sql); $stmt->execute($bindParams); $db->commit(); return ['success' => true, 'message' => null]; } catch (PDOException $e) { $db->rollBack(); return ['success' => false, 'message' => $e->getMessage()]; } } public function deleteProceduresGroupsIntervals($params) { $db = $this->db ?: $this->db = connectDb(); $id = (int)$params->id; $db->beginTransaction(); try { $stmt = $db->prepare("DELETE FROM MedSanProcGroupInterval WHERE MedSanProcGroupIntervalCode = :id"); $stmt->execute([':id' => $id]); $db->commit(); return true; } catch (Exception $e) { $db->rollBack(); throw $e; } } } handleJsonRpc('med_common_proceduresGroups');