<?php
require_once(__DIR__.'/../../init.php'); class map_common_organizationKinds { private $db; const AMBULANCE_APP = 'ambulance'; const AMBULANCE_TASK = 'mapCommonOrganizationKindsSMP'; public function readOrganizationKinds($params = null){ $db = $this->db ?: $this->db = connectDb(); $app = getSessionValue('app'); if ($app == self::AMBULANCE_APP){ $smpSettings = readExtraSettings($db, self::AMBULANCE_TASK); $settingsOrganizationCategoryId = isUndefinedProperty($smpSettings, 'lpuOrganizationCategoryId'); } $organizationId = isset($params->organizationId) ? (int) $params->organizationId : 0; $sql = <<<EOT
            SELECT
                ek.enterpriseKindCode   "id",
                ek.name                 "name",
                ek.Type                 "employmentTypeId",
                CASE WHEN EXISTS(
                    SELECT eik.enterpriseInKindCode
                    FROM enterpriseInKind eik
                    WHERE eik.enterpriseCode = $organizationId
                        AND eik.enterpriseKindCode = ek.enterpriseKindCode
                ) THEN 1 ELSE 0 END     "used"
            FROM enterpriseKind ek
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $categories = $stmt->fetchAll(); if ($app == self::AMBULANCE_APP){ $i = 0; foreach($categories as $category){ if ($category['id'] == $settingsOrganizationCategoryId){ $category['isLPU'] = true; $categories[$i] = $category; } $i++; } } return $categories; } public function updateOrganizationKinds($records, $params = null){ $db = $this->db ?: $this->db = connectDb(); $app = getSessionValue('app'); if ($app == self::AMBULANCE_APP){ $smpSettings = readExtraSettings($db, self::AMBULANCE_TASK); $settingsOrganizationCategoryId = isUndefinedProperty($smpSettings, 'lpuOrganizationCategoryId'); } if (isset($params) && isset($params->organizationId)){ $organizationId = isset($params->organizationId) ? (int) $params->organizationId : 0; if (is_object($records)){ $records = [$records]; } $sqlInsert = <<<EOT
                INSERT INTO enterpriseInKind (enterpriseKindCode, enterpriseCode)
                VALUES (?, $organizationId)
EOT;
$sqlDelete = <<<EOT
                DELETE
                FROM enterpriseInKind
                WHERE enterpriseKindCode = ?
                    AND enterpriseCode = $organizationId
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtDelete = $db->prepare($sqlDelete); $db->beginTransaction(); try { foreach ($records as $row){ if (isUndefinedProperty($row, 'used')){ $stmtInsert->execute([ $row->id ]); } else { $stmtDelete->execute([ $row->id ]); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } } else { $sql = <<<EOT
                UPDATE enterpriseKind SET
                    Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
                    Type = CASE WHEN ? = 1 THEN ? ELSE Type END
                WHERE EnterpriseKindCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row){ $isLPU = isUndefinedProperty($row, 'isLPU'); $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'employmentTypeId'), isUndefinedProperty($row, 'employmentTypeId'), $row->id ]); if ($app == self::AMBULANCE_APP){ if ($isLPU == true){ $smpSettings->lpuOrganizationCategoryId = $row->id; } else if ($isLPU == false){ $smpSettings->lpuOrganizationCategoryId = null; } writeExtraSettings($db, self::AMBULANCE_TASK, $smpSettings); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } } return true; } public function deleteOrganizationKinds($records){ $db = $this->db ?: $this->db = connectDb(); $app = getSessionValue('app'); if ($app == self::AMBULANCE_APP){ $smpSettings = readExtraSettings($db, self::AMBULANCE_TASK); $settingsOrganizationCategoryId = isUndefinedProperty($smpSettings, 'lpuOrganizationCategoryId'); } if (is_object($records)){ $records = [$records]; } if ($app == self::AMBULANCE_APP){ foreach($records as $row){ if (isEmptyProperty($row, 'id') == $settingsOrganizationCategoryId){ $smpSettings->lpuOrganizationCategoryId = null; writeExtraSettings($db, self::AMBULANCE_TASK, $smpSettings); } } } $in = createSqlInCondition($records); $sql = <<<EOT
            DELETE
            FROM enterpriseKind
            WHERE enterpriseKindCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function createOrganizationKinds($records){ $db = $this->db ?: $this->db = connectDb(); $app = getSessionValue('app'); if ($app == self::AMBULANCE_APP){ $smpSettings = readExtraSettings($db, self::AMBULANCE_TASK); $settingsOrganizationCategoryId = isUndefinedProperty($smpSettings, 'lpuOrganizationCategoryId'); } if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
           INSERT INTO enterpriseKind (Name, Type)
            VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; $db->beginTransaction(); try { foreach ($records as $row){ $isLPU = isUndefinedProperty($row, 'isLPU'); $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'employmentTypeId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_EnterpriseKind'); if ($app == self::AMBULANCE_APP && $isLPU){ $smpSettings->lpuOrganizationCategoryId = $rec->id; writeExtraSettings($db, self::AMBULANCE_TASK, $smpSettings); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_organizationKinds');