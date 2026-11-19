<?php
 require_once(__DIR__ . '/../../../init.php'); class med_common_dicom_tasks { private $db; public function readDicomTasks($params = null) { $db = $this->db ?: $this->db = connectDb(); $serviceId = isset($params->serviceId) ? (int) $params->serviceId : 0; $sql = <<<"EOT"
select
    dt.dicomtaskcode "id",
    dt.dicomservicecode "serviceId",
    dt.dicompacscodesrc "pacsSrcId",
    dt.dicompacscodedst "pacsDstId",
    dt.active "active",
    dt.action "action",
    dt.periodicity "periodicity",
    dt.modalities "modalities",
    dt.accessionnumber "accessNum",
    dt.searchdepth "searchDep",
    dt.lastruntime "lastRun",
    (select ds.ae from dicomservice ds where ds.dicomservicecode = dt.dicomservicecode order by 1 limit 1) as "aeService",
    (select dp.name from dicompacs dp where dp.dicompacscode = dt.dicompacscodesrc order by 1 limit 1) as "pacsSrcName",
    (select dp.name from dicompacs dp where dp.dicompacscode = dt.dicompacscodedst order by 1 limit 1) as "pacsDstName",
    (select e.name from enterprise e where e.enterprisecode = 
        (select ds2.enterprisecode from dicomservice ds2 where ds2.dicomservicecode=dt.dicomservicecode order by 1 limit 1) order by 1 limit 1) as "serviceOrganizationName"
from DicomTask dt
    where dt.dicomservicecode= ? or 0 = ?
order by dt.dicomtaskcode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$serviceId, $serviceId]); return $stmt->fetchAll(); } public function updateDicomTasks($records) { $db = $this->db ?: $this->db = connectDb(); emptyRecordFields($records, 'active, pacsDstId, pacsSrcId'); return updateSql($db, 'DicomTask', 'dicomtaskcode', $records, 'dicomservicecode, dicompacscodesrc, dicompacscodedst, active, action, periodicity, modalities, accessionnumber, searchdepth, lastruntime', 'serviceId, pacsSrcId, pacsDstId, active, action, periodicity, modalities, accessNum, searchDep, lastRun'); } public function deleteDicomTasks($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'DicomTask', 'dicomtaskcode', $records); } public function createDicomTasks($records) { $db = $this->db ?: $this->db = connectDb(); emptyRecordFields($records, 'active, pacsDstId, pacsSrcId'); return insertSql($db, 'DicomTask', $records, 'dicomservicecode, dicompacscodesrc, dicompacscodedst, active, action, periodicity, modalities, accessionnumber, searchdepth, lastruntime', 'serviceId, pacsSrcId, pacsDstId, active, action, periodicity, modalities, accessNum, searchDep, lastRun'); } } handleJsonRpc('med_common_dicom_tasks'); 