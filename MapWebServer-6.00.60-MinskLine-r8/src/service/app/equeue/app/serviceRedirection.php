<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_serviceRedirection { private $db; public function readServiceRedirection($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT
                mes.medEqServiceCode "medEqServiceId",
                mes.cipher,
                mes.name,
                mes.enterpriseCode "enterpriseId",
                mes.active,
                mesr.redirectTimeout "redirectTimeout",
                mesr.abilityToReturn "abilityToReturn",
                CASE WHEN mesr.medEqServiceCode IS NULL THEN 0 ELSE 1 END used
            FROM medEqService mes
            LEFT OUTER JOIN medEqServiceRedirect mesr ON mesr.medEqServiceCodeRedirect = mes.medEqServiceCode AND mesr.medEqServiceCode = ?
            WHERE mes.enterpriseCode = ?
                AND mes.active = 1
                AND mes.medEqServiceCode != COALESCE(?, 0)
            ORDER BY used DESC, name, cipher
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->serviceId, $mapEnterpriseCode, $params->serviceId ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_serviceRedirection');