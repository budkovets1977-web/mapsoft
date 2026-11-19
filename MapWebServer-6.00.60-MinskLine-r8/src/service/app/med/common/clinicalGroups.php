<?php
 require_once(__DIR__.'/../../init.php'); class med_common_clinicalGroups { private $db; public function readClinicalGroups($params) { $db = $this->db ?: $this->db = connectDb(); $preventKind = isEmptyProperty($params, 'preventKind'); $sql = <<<"EOT"
SELECT
    MCG.medclinicalgroupcode "id",
    MCG.medpreventtypecode "preventKind",
    MCG.cipher "cipher",
    MCG.name "name"
FROM medclinicalgroup MCG
WHERE
    MCG.medpreventtypecode = $preventKind
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createClinicalGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT into medclinicalgroup (medpreventtypecode, cipher, name)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'preventKind'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medclinicalgroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateClinicalGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
 update medclinicalgroup  set
    cipher = case when ? = 1 then ? else cipher end,
    name = case when ? = 1 then ? else name end
where medclinicalgroupcode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteClinicalGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medclinicalgroup', 'medclinicalgroupcode', $records); } } $service = new Junior\Server(new med_common_clinicalGroups()); $service->process(); 