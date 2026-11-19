<?php
 require_once(__DIR__.'/../../init.php'); class med_common_infections { private $db; public function readInfections($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
SELECT
    MI.MedInfectionCode AS "id",
    MI.Name AS "name",
    MI.InfectionType AS "infectionType",
    MI.PauseFromPrior AS "pauseFromPrior",
    MI.CiszResourceValueCodeInfection AS "infectionCiszCode",
    CRV.Name AS "infectionCisz"
FROM
    MedInfection MI
LEFT JOIN CiszResourceValue AS CRV ON CRV.CiszResourceValueCode = MI.CiszResourceValueCodeInfection
ORDER BY
    name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createInfections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    insert into MedInfection (name, infectionType, pauseFromPrior, ciszResourceValueCodeInfection)
    values (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'infectionType'), isUndefinedProperty($row, 'pauseFromPrior'), isUndefinedProperty($row, 'infectionCiszCode'), ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInfection'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateInfections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedInfection set
       name = case when ? = 1 then ? else name end,
       infectionType = case when ? = 1 then ? else infectionType end,
       pauseFromPrior = case when ? = 1 then ? else pauseFromPrior end,
       CiszResourceValueCodeInfection = case when ? = 1 then ? else CiszResourceValueCodeInfection end
where MedInfectionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $infectionCiszCode = isUndefinedProperty($row, 'infectionCiszCode') ? isUndefinedProperty($row, 'infectionCiszCode') : null; $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'infectionType'), isEmptyProperty($row, 'infectionType'), propertyExistsSQL($row, 'pauseFromPrior'), isEmptyProperty($row, 'pauseFromPrior'), propertyExistsSQL($row, 'infectionCiszCode'), $infectionCiszCode, $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteInfections($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInfection', 'MedInfectionCode', $records); } } $service = new Junior\Server(new med_common_infections()); $service->process(); 