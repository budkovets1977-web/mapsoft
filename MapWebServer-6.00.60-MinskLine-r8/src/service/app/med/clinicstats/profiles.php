<?php
 require_once(__DIR__ . '/../../init.php'); class med_clinicstats_profiles { private $db; public function readProfiles($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
       MD.MedProfileCode "id",
       MD.Name "name",
       MD.Cipher "cipher"
       FROM MedProfile MD
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createProfiles($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedProfile (Cipher, Name)
values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedProfile'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateProfiles($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedProfile set
       Cipher = case when ? = 1 then ? else Cipher end,
       Name = case when ? = 1 then ? else Name end
where MedProfileCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteProfiles($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedProfile', 'MedProfileCode', $records); } public function getMaxCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(cipher), 20)) as varchar(20))
    FROM
        medprofile
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "maxCipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCipher = fetchValue($stmt, 'maxCipher'); return is_null($maxCipher) ? 1 : $maxCipher; } } $service = new Junior\Server(new med_clinicstats_profiles()); $service->process(); 