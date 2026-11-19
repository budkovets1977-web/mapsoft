<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_owpServiceCategories { private $db; public function readOWPServiceCategories($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    sc.MedEqServiceCategoryCode "id",
    pc.Name "name",
    sc.Cipher "cipher",
    CASE
        WHEN oc.MedEqOWPCategoryCode IS NOT NULL THEN 1
        ELSE 0
    END "used",
    oc.Type "type"
FROM MedEqServiceCategory sc
JOIN MedEqPatientCategory pc ON pc.MedEqPatientCategoryCode = sc.MedEqPatientCategoryCode AND pc.Active = 1
LEFT OUTER JOIN MedEqOWPCategory oc ON oc.MedEqOWPCode = ?
    AND oc.MedEqServiceCategoryCode = sc.MedEqServiceCategoryCode
WHERE sc.Active = 1
    AND sc.MedEqServiceCode = ?
ORDER BY 4 DESC, 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->owpId, isEmptyProperty($params, 'serviceId') ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_owpServiceCategories');