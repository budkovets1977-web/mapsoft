<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_categoryPrivileges { private $db; public function readCategoryPrivileges($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    ci.MedEqPatientCategoryInCode "id",
    pg.MedPrivilegeGroupCode "groupId",
    pg.Cipher "groupCipher",
    pg.Name "groupName",
    p.MedPrivelegeCode "privilegeId",
    p.Cipher "privilegeCipher",
    p.Name "privilegeName"
FROM MedEqPatientCategoryIn ci
JOIN MedPrivelege p ON p.MedPrivelegeCode = ci.MedPrivelegeCode
JOIN MedPrivilegeGroup pg ON pg.MedPrivilegeGroupCode = p.MedPrivilegeGroupCode
WHERE MedEqPatientCategoryCode = ?
ORDER BY 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->categoryId ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_categoryPrivileges');