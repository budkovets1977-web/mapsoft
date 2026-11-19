<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_settings { private $db; public function readDisplayNumbers(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                medEqDisplayCode id,
                displayNumber,
                enterpriseCode
            FROM medEqDisplay
            WHERE displayType IS NOT NULL
            ORDER BY enterpriseCode, displayNumber
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('equeue_display_settings');