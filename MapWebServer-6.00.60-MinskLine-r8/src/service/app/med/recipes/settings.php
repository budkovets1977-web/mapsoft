<?php
 require_once(__DIR__.'/../../init.php'); class med_recipes_settings { private $db; public function readSettings() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
    medRecipeStandartSNCode "id",
    documentNumber "number",
    documentSeries "series"
from MedRecipeStandartSN
where EnterpriseCode = $enterpriseId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createSettings($record) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO MedRecipeStandartSN (documentNumber, documentSeries, enterpriseCode)
VALUES (?,?,?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($record, 'number'), isUndefinedProperty($record, 'series'), $enterpriseId = getSessionValue('mapEnterpriseCode') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedRecipeStandartSN'); return $rec; } public function updateSettings($record) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedRecipeStandartSN
SET
    documentNumber = case when ? = 1 then ? else documentNumber end,
    documentSeries = case when ? = 1 then ? else documentSeries end
where medRecipeStandartSNCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($record, 'number'), isUndefinedProperty($record, 'number'), propertyExistsSQL($record, 'series'), isUndefinedProperty($record, 'series'), isUndefinedProperty($record, 'id') ]); return true; } } $service = new Junior\Server(new med_recipes_settings()); $service->process(); 