<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_externalMisCategory { private $db; public function readExternalMisCategory(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT
                mep.medEqPatientCategoryExtLinkCode     id,
                mep.code                                "externalMisCategoryCode",
                mep.name                                "externalMisCategoryName",
                mep.medEqPatientCategoryCode            "categoryId",
                mec.name                                "categoryName"
            FROM medEqPatientCategoryExtLink mep
            LEFT JOIN medEqPatientCategory mec ON mec.medEqPatientCategoryCode = mep.medEqPatientCategoryCode
            WHERE mep.enterpriseCode = ?
            ORDER BY 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); return $stmt->fetchAll(); } public function updateExternalMisCategory($records){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
                UPDATE medEqPatientCategoryExtLink
                SET medEqPatientCategoryCode = CASE WHEN ? = 1 THEN ? ELSE medEqPatientCategoryCode END
                WHERE medEqPatientCategoryExtLinkCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'categoryId'), isEmptyProperty($row, 'categoryId'), isEmptyProperty($row, 'id') ]); } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return true; } } handleJsonRpc('equeue_app_externalMisCategory');