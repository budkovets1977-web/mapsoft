<?php
 require_once(__DIR__.'/../../init.php'); class med_vaccinations_disclaimerReasons { private $db; public function readDisclaimerReasons($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    medInoculateContrCode "id",
    name "name",
    notPlan "notPlan"
FROM
    MedInoculateContr
ORDER BY
    name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createDisclaimerReasons($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
insert into MedInoculateContr (name, notPlan)
values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'notPlan') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInoculateContr'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateDisclaimerReasons($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedInoculateContr set
       name = case when ? = 1 then ? else name end,
       notPlan = case when ? = 1 then ? else notPlan end
where medInoculateContrCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'notPlan'), isEmptyProperty($row, 'notPlan'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDisclaimerReasons($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInoculateContr', 'medInoculateContrCode', $records); } } $service = new Junior\Server(new med_vaccinations_disclaimerReasons()); $service->process(); 