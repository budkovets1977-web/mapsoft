<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_insurance_settings { private $db; public function readFilials() { $db = $this->db ?: $this->db = connectDb(); $currentEnterprise = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    ESI.EnterpriseStructureInfoCode "id",
    ESI.Name "name",
    ESI.FilialCode "filialCode"
FROM EnterpriseStructure ES
JOIN EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = ES.EnterpriseStructureCode
WHERE ES.EnterpriseStructureParentCode = null AND ES.EnterpriseCode = $currentEnterprise
ORDER BY ESI.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateFilials($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'EnterpriseStructureInfo', 'EnterpriseStructureInfoCode', $records, 'filialCode'); } } handleJsonRpc('pmed_insurance_settings'); 