<?php
require_once(__DIR__.'/../../init.php'); class equeue_orc_infoboxServices { private $db; public function readInfoboxServices($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                ms.MedEqServiceCode "id",
                ms.Name,
                ms.Cipher,
                mis.Position
            FROM MedEqService ms
            LEFT OUTER JOIN MedEqInfoboxService mis ON mis.MedEqInfoboxCode = ?
                AND mis.MedEqServiceCode = ms.MedEqServiceCode
            WHERE ms.Active = 1
                AND ms.EnterpriseCode = ?
                AND mis.MedEqInfoboxServiceCode IS NOT NULL
            ORDER BY mis.Position, ms.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->infoboxId, getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_orc_infoboxServices');