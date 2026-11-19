<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_infoboxServices { private $db; public function readInfoboxServices($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                ms.MedEqServiceCode id,
                ms.Name,
                ms.Cipher,
                mis.Position,
                CASE WHEN mis.MedEqInfoboxServiceCode IS NOT NULL THEN 1 ELSE 0 END used
            FROM MedEqService ms
            LEFT JOIN MedEqInfoboxService mis on mis.MedEqInfoboxCode = ?
                AND mis.MedEqServiceCode = ms.MedEqServiceCode
            WHERE ms.Active = 1
                AND ms.EnterpriseCode = ?
            ORDER BY 5 DESC, mis.Position, ms.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->infoboxId, getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_infoboxServices');