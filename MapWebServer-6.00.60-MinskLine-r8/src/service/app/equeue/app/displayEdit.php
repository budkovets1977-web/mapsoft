<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_displayEdit { private $db; private $audioPath = "audio/equeue/display/"; public function reloadDisplay($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $displayNumber = $params->displayNumber; $sql = "UPDATE medEqDisplay SET reload = 1 WHERE enterpriseCode = ? AND displayNumber LIKE ?"; $stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode, $displayNumber ]); return true; } public function readDisplayOperators(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
                SELECT
                    meo.medEqOwpCode id,
                    meo.name "operatorName",
                    meo.number "operatorNumber",
                    meo.cabinet,
                    mes.name "serviceName",
                    mes.cipher "serviceCipher"
                FROM medEqOwp meo
                    LEFT JOIN medEqService mes ON mes.medEqServiceCode = meo.medEqServiceCode
                WHERE meo.active = 1
                        and meo.enterpriseCode = ?
                ORDER BY mes.name, meo.number
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); $result = $stmt->fetchAll(); return $result; } public function readDisplayServices(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
                SELECT
                    ms.medEqServiceCode id,
                    ms.name,
                    ms.cipher,
                    ds.position,
                    CASE WHEN ds.medEqDisplayServiceCode IS NOT NULL THEN 1 ELSE 0 END used
                FROM medEqService ms
                    LEFT JOIN medEqDisplayService ds on ds.medEqServiceCode = ms.medEqServiceCode
                WHERE ms.active = 1 AND ms.enterpriseCode = ?
                ORDER BY 5 DESC, ds.position, ms.name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); $result = $stmt->fetchAll(); return $result; } public function readDisplayVoices(){ $dir = getResourcesPath() . $this->audioPath; $files = glob($dir . "*"); $result = []; $i = 0; foreach ($files as $file){ array_push($result, array("id" => $i, "name" => basename($file))); $i++; } return $result; } public function readDisplayPhrases(){ $res = getResourcesPath(); $dir = $res . $this->audioPath; $files = glob($dir . "*"); $result = []; $i = 0; foreach ($files as $file){ $voice = basename($file); $dirPhrases = $dir . "/" . $voice . "/фраза1/"; $phrasesFiles = glob($dirPhrases . "*"); foreach ($phrasesFiles as $pfile){ if (is_file($pfile)){ array_push($result, array("id" => $i, "name" => basename($pfile, ".mp3"), "voice" => $voice, "phraseType" => 1)); $i++; } } $dirPhrases = $dir . "/" . $voice . "/фраза2/"; $phrasesFiles = glob($dirPhrases . "*"); foreach ($phrasesFiles as $pfile){ if (is_file($pfile)){ array_push($result, array("id" => $i, "name" => basename($pfile, ".mp3"), "voice" => $voice, "phraseType" => 2)); $i++; } } } return $result; } public function loadVideoPrograms(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $result = null; $sql = <<<EOT
            SELECT medEqVideoProgramCode    id,
                    name                    "videoProgramName"
            FROM medEqVideoProgram
            WHERE enterpriseCode = ?
            ORDER BY name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); $result = $stmt->fetchAll(); return $result; } } handleJsonRpc('equeue_app_displayEdit');