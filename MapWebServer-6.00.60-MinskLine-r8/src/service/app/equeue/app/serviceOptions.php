<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_serviceOptions { private $db; public function readServiceOptions($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                medEqServiceOptionCode id,
                position,
                name,
                label,
                note,
                active,
                /*ControlData "controlData",*/
                ((EXTRACT(hour FROM ProceedTime))::integer)* 60 + ((EXTRACT(minute FROM ProceedTime))::integer) "proceedTimeMinute",
                ((EXTRACT(second FROM ProceedTime))::integer) "proceedTimeSecond"
            FROM medEqServiceOption
            WHERE medEqServiceCode = ?
            ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->serviceId ]); $ret = $stmt->fetchAll(); return $ret; } } handleJsonRpc('equeue_app_serviceOptions');