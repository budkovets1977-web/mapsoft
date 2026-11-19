<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_videoProgramList { private $db; public function readVideoProgramList($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT
                mvl.medEqVideoProgramListCode id,
                mvl.fileName "fileName",
                mvl.medEqVideoProgramCode "videoProgramId",
                mvl.position
            FROM medEqVideoProgramList mvl
            JOIN medEqVideoProgram mvp on mvp.medEqVideoProgramCode = mvl.medEqVideoProgramCode
            WHERE mvp.medEqVideoProgramCode = ?
                AND mvp.enterpriseCode = ?
            ORDER BY position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'videoProgramId'), $mapEnterpriseCode ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_videoProgramList');