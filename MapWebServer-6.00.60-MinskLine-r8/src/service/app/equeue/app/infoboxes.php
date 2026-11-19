<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_infoboxes { private $db; public function readInfoboxes(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                medEqInfoboxCode id,
                name,
                cipher,
                active
            FROM medEqInfobox
            WHERE enterpriseCode = ?
            ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } public function updateInfoboxes($records, $noTransaction = false){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE MedEqInfobox SET
                name =      CASE WHEN ? = 1 THEN ? ELSE name END,
                cipher =    CASE WHEN ? = 1 THEN ? ELSE cipher END,
                active =    CASE WHEN ? = 1 THEN ? ELSE active END
            WHERE MedEqInfoboxCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } if (!$noTransaction) { $db->beginTransaction(); } try { foreach($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), $row->id ]); } if (!$noTransaction) { $db->commit(); } } catch (Throwable $e) { if (!$noTransaction) { $db->rollback(); } throw $e; } return true; } public function deleteInfoboxes($records){ $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = "DELETE FROM MedEqInfobox WHERE MedEqInfoboxCode $in"; $stmt = $db->prepare($sql); $stmt->execute(); return true; } public function syncInfoboxes($infobox, $services){ $db = $this->db ?: $this->db = connectDb(); $infoboxId = (int) $infobox->id; $db->beginTransaction(); try { if ($infoboxId == 0) { $sqlInfoboxInsert = <<<EOT
                    INSERT INTO MedEqInfobox (EnterpriseCode, Name, Cipher, Active)
                        VALUES (?, ?, ?, ?)
EOT;
$stmtInfoboxInsert = $db->prepare($sqlInfoboxInsert); $stmtInfoboxInsert->execute([ getSessionValue('mapEnterpriseCode'), $infobox->name, isEmptyProperty($infobox, 'cipher'), isEmptyProperty($infobox, 'active') ]); $infoboxId = $db->lastInsertId('seq_MedEqInfobox'); } else { $this->updateInfoboxes($infobox, true); } if (isset($services)) { $in = createSqlInCondition($services, 'id'); $sqlServiceDelete = <<<EOT
                    DELETE FROM MedEqInfoboxService
                    WHERE MedEqInfoboxCode = $infoboxId AND NOT (MedEqServiceCode $in)
EOT;
$stmtServiceDelete = $db->prepare($sqlServiceDelete); $stmtServiceDelete->execute(); $sqlServiceInsert = <<<EOT
                    INSERT INTO MedEqInfoboxService (MedEqInfoboxCode, MedEqServiceCode)
                        SELECT $infoboxId, s.MedEqServiceCode
                        FROM MedEqService s
                        WHERE s.MedEqServiceCode $in
                            AND NOT EXISTS(
                                SELECT * FROM MedEqInfoboxService
                                    where MedEqInfoboxCode = $infoboxId AND MedEqServiceCode = s.MedEqServiceCode
                                LIMIT 1)
EOT;
$stmtServiceInsert = $db->prepare($sqlServiceInsert); $stmtServiceInsert->execute(); $sqlPosition = <<<EOT
                    UPDATE MedEqInfoboxService SET
                        Position = NULL
                    WHERE MedEqInfoboxCode = ?
EOT;
$stmtPosition = $db->prepare($sqlPosition); $stmtPosition->execute([ $infoboxId ]); $sqlServiceUpdate = <<<EOT
                    UPDATE MedEqInfoboxService SET
                        Position = ?
                    WHERE MedEqInfoboxCode = ? AND MedEqServiceCode = ?
EOT;
$stmtServiceUpdate = $db->prepare($sqlServiceUpdate); if (is_object($services)) { $services = [$services]; } foreach($services as $row) { $stmtServiceUpdate->execute([ $row->position, $infoboxId, $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $infoboxId; } } handleJsonRpc('equeue_app_infoboxes');