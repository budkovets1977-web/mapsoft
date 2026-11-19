<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_videoPrograms { private $db; public function readVideoPrograms(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT
                medEqVideoProgramCode id,
                name "videoProgramName"
            FROM medEqVideoProgram
            WHERE enterpriseCode = ?
            ORDER BY name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); return $stmt->fetchAll(); } private function updateVideoProgram($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            UPDATE medEqVideoProgram
            SET
                enterpriseCode = CASE WHEN ? = 1 THEN ? ELSE enterpriseCode END,
                name = CASE WHEN ? = 1 THEN ? ELSE name END
            WHERE medEqVideoProgramCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ 1, $mapEnterpriseCode, propertyExistsSQL($params, 'videoProgramName'), isEmptyProperty($params, 'videoProgramName'), isEmptyProperty($params, 'id') ]); return true; } public function deleteVideoPrograms($params){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqVideoProgram WHERE medEqVideoProgramCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $params->id ]); return true; } public function syncVideoProgram($params){ $db = $this->db ?: $this->db = connectDb(); $videoProgram = isset($params->videoProgram) ? $params->videoProgram : null; $videos = isset($params->videos) ? $params->videos : null; $videoProgramId = (int)$videoProgram->id; if (!$videoProgramId){ $sql = <<<EOT
                INSERT INTO medEqVideoProgram(enterpriseCode,
                                                name)
                VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode'), isEmptyProperty($videoProgram, 'videoProgramName') ]); $videoProgramId = $db->lastInsertId('seq_medEqVideoProgram'); } else { $this->updateVideoProgram($videoProgram); } if ($videoProgramId){ if ($videos || is_array($videos)){ $sql = <<<EOT
                    DELETE FROM medEqVideoProgramList
                    WHERE medEqVideoProgramCode = $videoProgramId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $sql = <<<EOT
                    INSERT INTO medEqVideoProgramList
                        (fileName, medEqVideoProgramCode, position)
                    VALUES (?, ?, ?)
EOT;
$i = 1; foreach($videos as $item){ $stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($item, 'fileName'), $videoProgramId, $i ]); $i++; } } } return $videoProgramId; } } handleJsonRpc('equeue_app_videoPrograms');