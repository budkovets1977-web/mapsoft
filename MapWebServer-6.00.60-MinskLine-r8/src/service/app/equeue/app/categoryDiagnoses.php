<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_categoryDiagnoses { private $db; public function readCategoryDiagnoses($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
                SELECT
                    ci.MedEqPatientCategoryInCode "id",
                    d.MedDiagnosisCode "diagnosisId",
                    d.Cipher1 "diagnosisCipher",
                    d.name "diagnosisName"
                FROM MedEqPatientCategoryIn ci
                    JOIN MedDiagnosis d ON d.MedDiagnosisCode = ci.MedDiagnosisCode
                WHERE MedEqPatientCategoryCode = ?
                ORDER BY 3, 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->categoryId ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_categoryDiagnoses');