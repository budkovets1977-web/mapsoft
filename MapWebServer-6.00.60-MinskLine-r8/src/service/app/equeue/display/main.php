<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_main { private $db; public function readDisplayParams($params){ $db = $this->db ?: $this->db = connectDb(); $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $sql = <<<EOT
            SELECT displayType "displayType"
            FROM medEqDisplay
            WHERE enterpriseCode = ?
                AND displayNumber LIKE ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $webEnterpriseCode, $displayNumber ]); $ret = $stmt->fetchAll(); return $ret; } } handleJsonRpc('equeue_display_main');