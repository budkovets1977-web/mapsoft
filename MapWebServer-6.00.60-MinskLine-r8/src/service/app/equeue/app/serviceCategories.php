<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_serviceCategories { private $db; public function readServiceCategories($params){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                pc.MedEqPatientCategoryCode id,
                pc.Name,
                CASE WHEN sc.MedEqServiceCategoryCode IS NULL OR sc.Active = 1 THEN 1 ELSE NULL END active,
                sc.Cipher,
                CASE WHEN sc.MedEqServiceCategoryCode IS NOT NULL THEN 1 ELSE 0 END used,
                sc.InitialDocumentNumber "initialDocumentNumber",
                sc.Priority,
                sc.Rotation,
                pc.Type "categoryType",
                pc.Active "categoryActive",
                CASE WHEN (sc.MedEqServiceCategoryCode IS NOT NULL OR sc.Active = 1) OR pc.active = 1 THEN 1 ELSE 0 END "usedDeleted"
            FROM MedEqPatientCategory pc
            LEFT OUTER JOIN MedEqServiceCategory sc ON sc.MedEqServiceCode = ?
                AND sc.MedEqPatientCategoryCode = pc.MedEqPatientCategoryCode
            ORDER BY 5 desc, 2, 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->serviceId ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_serviceCategories');