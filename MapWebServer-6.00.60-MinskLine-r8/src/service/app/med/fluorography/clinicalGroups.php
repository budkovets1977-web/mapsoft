<?php
 require_once(__DIR__.'/../../init.php'); class med_fluorography_clinicalgroups { private $db; public function readClinicalGroups($params) { $db = $this->db ?: $this->db = connectDb(); $preventKindId = isset($params->preventKindId) && isEmptyProperty($params, 'preventKindId') ? $params->preventKindId : null; $sql = <<<"EOT"
SELECT
    medclinicalgroupcode "id",
    cipher "cipher",
    name "name"
FROM medclinicalgroup
WHERE medpreventtypecode = $preventKindId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } $service = new Junior\Server(new med_fluorography_clinicalgroups()); $service->process(); 