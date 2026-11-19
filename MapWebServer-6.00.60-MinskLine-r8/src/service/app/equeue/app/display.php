<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_display { private $db; public function readDisplay(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
                SELECT
                    med.medEqDisplayCode id,
                    med.displayNumber "displayNumber",
                    LPAD(med.displayNumber, 10, '0') "sortDisplayNumber",
                    med.useVoice "useVoice",
                    med.displayType "displayType",
                    med.marqueeText "marqueeText",
                    STRING_AGG(eds.medEqServiceCode, ',') "servicesIds",
                    STRING_AGG(edo.medEqOwpCode, ',') "operatorsIds",
                    med.voice,
                    med.phrase1,
                    med.phrase2,
                    med.medEqVideoProgramCode "videoProgramId",
                    med.showVideo "showVideo"
                FROM medEqDisplay med
                LEFT JOIN medEqDisplayService eds ON eds.medEqDisplayCode = med.medEqDisplayCode
                LEFT JOIN medEqDisplayOwp edo ON edo.medEqDisplayCode = med.medEqDisplayCode
                WHERE med.enterpriseCode = ?
                    AND med.displayKind IS NULL
                GROUP BY 1,2,3,4,5,6
                ORDER BY 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); return $stmt->fetchAll(); } public function createDisplay($records){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
                INSERT INTO medEqDisplay(
                    displayNumber, enterpriseCode, useVoice,
                    displayType, marqueeText, voice,
                    phrase1, phrase2, medEqVideoProgramCode,
                    showVideo)
                VALUES (?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row){ $stmt->execute([ isEmptyProperty($row, 'displayNumber'), $mapEnterpriseCode, isEmptyProperty($row, 'useVoice'), isEmptyProperty($row, 'displayType'), isEmptyProperty($row, 'marqueeText'), isEmptyProperty($row, 'voice'), isEmptyProperty($row, 'phrase1'), isEmptyProperty($row, 'phrase2'), isEmptyProperty($row, 'videoProgramId'), isEmptyProperty($row, 'showVideo') ]); $rec = new stdClass; $rec->id = (int)$db->lastInsertId('seq_medEqDisplay'); $servicesIds = isEmptyProperty($row, 'arrServicesIds'); $operatorsIds = isEmptyProperty($row, 'arrOperatorsIds'); if ($servicesIds){ $this->updateServices($servicesIds, $rec->id); } if ($operatorsIds){ $this->updateOperators($operatorsIds, $rec->id); } $ret[] = $rec; } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } public function deleteDisplay($params){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqDisplay WHERE medEqDisplayCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $params->id ]); return true; } public function updateDisplay($records){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
                UPDATE medEqDisplay
                SET
                    displayNumber = CASE WHEN ? = 1 THEN ? ELSE displayNumber END,
                    enterpriseCode = CASE WHEN ? = 1 THEN ? ELSE enterpriseCode END,
                    useVoice = CASE WHEN ? = 1 THEN ? ELSE useVoice END,
                    displayType = CASE WHEN ? = 1 THEN ? ELSE displayType END,
                    marqueeText = CASE WHEN ? = 1 THEN ? ELSE marqueeText END,
                    voice = CASE WHEN ? = 1 THEN ? ELSE voice END,
                    phrase1 = CASE WHEN ? = 1 THEN ? ELSE phrase1 END,
                    phrase2 = CASE WHEN ? = 1 THEN ? ELSE phrase2 END,
                    medEqVideoProgramCode = CASE WHEN ? = 1 THEN ? ELSE medEqVideoProgramCode END,
                    showVideo = CASE WHEN ? = 1 THEN ? ELSE showVideo END
                WHERE medEqDisplayCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'displayNumber'), isEmptyProperty($row, 'displayNumber'), 1, $mapEnterpriseCode, propertyExistsSQL($row, 'useVoice'), isEmptyProperty($row, 'useVoice'), propertyExistsSQL($row, 'displayType'), isEmptyProperty($row, 'displayType'), propertyExistsSQL($row, 'marqueeText'), isEmptyProperty($row, 'marqueeText'), propertyExistsSQL($row, 'voice'), isEmptyProperty($row, 'voice'), propertyExistsSQL($row, 'phrase1'), isEmptyProperty($row, 'phrase1'), propertyExistsSQL($row, 'phrase2'), isEmptyProperty($row, 'phrase2'), propertyExistsSQL($row, 'videoProgramId'), isEmptyProperty($row, 'videoProgramId'), propertyExistsSQL($row, 'showVideo'), isEmptyProperty($row, 'showVideo'), isEmptyProperty($row, 'id') ]); $rec = new stdClass; $rec->id = isEmptyProperty($row, 'id'); $servicesIds = isEmptyProperty($row, 'arrServicesIds'); $operatorsIds = isEmptyProperty($row, 'arrOperatorsIds'); if ($servicesIds){ $this->updateServices($servicesIds, $rec->id); } if ($operatorsIds){ $this->updateOperators($operatorsIds, $rec->id); } $ret[] = $rec; } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } private function updateServices($servicesIds, $displayId){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqDisplayService WHERE medEqDisplayCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $displayId ]); $pos = 0; if ($servicesIds){ foreach ($servicesIds as $medEqServiceId){ $sql = <<<EOT
                        INSERT INTO medEqDisplayService(medEqDisplayCode,
                                                medEqServiceCode,
                                                position)
                        VALUES (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $displayId, $medEqServiceId, ++$pos ]); } } } private function updateOperators($operatorsIds, $displayId){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqDisplayOwp WHERE medEqDisplayCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $displayId ]); $pos = 0; if ($operatorsIds){ foreach ($operatorsIds as $medEqOperatorId){ $sql = <<<EOT
                        INSERT INTO medEqDisplayOwp(medEqDisplayCode,
                                                medEqOwpCode,
                                                position)
                        VALUES (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $displayId, $medEqOperatorId, ++$pos ]); } } } public function reloadAllDisplays(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = "UPDATE medEqDisplay SET reload = 1 WHERE enterpriseCode = ? AND displayKind != 1"; $stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); return true; } } handleJsonRpc('equeue_app_display');