<?php
require_once(__DIR__.'/../../init.php'); class equeue_orc_settings { private $db; public function setSessionVars($params){ $webEnterpriseCode = $params->customerId; setSessionValue('customer', $webEnterpriseCode); $db = $this->db ? : $this->db = connectDb(); $sql = <<<EOT
            SELECT enterpriseCode id
            FROM webEnterprise
            WHERE webEnterpriseCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $webEnterpriseCode ]); $row = $stmt->fetch(); $mapEnterpriseCode = $row ? $row['id'] : 0; setSessionValue('mapEnterpriseCode', $mapEnterpriseCode); } } handleJsonRpc('equeue_orc_settings');