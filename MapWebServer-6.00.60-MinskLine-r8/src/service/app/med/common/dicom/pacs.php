<?php
 require_once(__DIR__ . '/../../../init.php'); require_once('dicom/Dicom.php'); class med_common_dicom_pacs { private $db; public function readPacs($params = null) { $db = $this->db ?: $this->db = connectDb(); $serviceId = isset($params->serviceId) ? (int) $params->serviceId : 0; $sql = <<<"EOT"
select
    dp.dicompacscode "id",
    dp.dicomservicecode "serviceId",
    dp.ae "ae",
    dp.port "port",
    dp.active "active",
    dp.note "note",
    dp.name "name",
    dp.host "host",
    dp.methodget "methodGet",
    dp.httpport "portHttp",
    (select ds.ae from dicomservice ds where ds.dicomservicecode = dp.dicomservicecode order by 1 limit 1) as "aeService",
    (select e.name from enterprise e where e.enterprisecode = 
        (select ds2.enterprisecode from dicomservice ds2 where ds2.dicomservicecode=dp.dicomservicecode order by 1 limit 1) order by 1 limit 1) as "serviceOrganizationName"
from DicomPacs dp
    where dp.dicomservicecode= ? or 0 = ?
order by dp.name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$serviceId, $serviceId]); return $stmt->fetchAll(); } public function updatePacs($records) { $db = $this->db ?: $this->db = connectDb(); emptyRecordFields($records, 'active'); return updateSql($db, 'DicomPacs', 'dicompacscode', $records, 'dicomservicecode, name, note, host, port, ae, methodget, active, httpport', 'serviceId, name, note, host, port, ae, methodGet, active, portHttp'); } public function deletePacs($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'DicomPacs', 'dicompacscode', $records); } public function createPacs($records) { $db = $this->db ?: $this->db = connectDb(); emptyRecordFields($records, 'active'); return insertSql($db, 'DicomPacs', $records, 'dicomservicecode, name, note, host, port, ae, methodget, active, httpport', 'serviceId, name, note, host, port, ae, methodGet, active, portHttp'); } public function testPacs($serviceId, $aec, $peer, $port) { $db = $this->db ?: $this->db = connectDb(); $aet = selectSqlValue($db, 'select ae from DicomService where DicomServiceCode = ?', [$serviceId]); $net = new \Dicom\Net($aet, $aec, $peer, $port); if ($net->echoScu()) return true; throw new \Exception($net->out); } } handleJsonRpc('med_common_dicom_pacs'); 