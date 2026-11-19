<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_owpdevices { private $db; public function readDisplays(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
select mis.medEqServiceCode "serviceId",
        md.medEqDeviceCode "deviceId",
        cast(md.number as varchar) || E', ' || left(md.note, 100) "deviceName"
from MedEqInfoboxService mis
join MedEqDevice md on md.MedEqInfoboxCode = mis.MedEqInfoboxCode and coalesce(md.type, 0) = 2
join MedEqInfobox mi on mi.MedEqInfoboxCode = mis.MedEqInfoboxCode and coalesce(mi.Active, 0) = 1
join MedEqService ms on ms.MedEqServiceCode = mis.MedEqServiceCode and coalesce(ms.Active, 0) = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_owpdevices');