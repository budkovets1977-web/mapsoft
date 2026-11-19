<?php
 require_once(__DIR__.'/../../init.php'); class med_common_contingents { private $db; public function readContingents($params = null) { $db = $this->db ?: $this->db = connectDb(); $stateId = isset($params->stateId) ? (int) $params->stateId : 0; $stateSql = $stateId ? ",EXISTS(SELECT mpf.MedPrivelegeFeatureCode
        FROM MedPrivelegeFeature AS mpf
        WHERE mpf.picture = $stateId
          AND mpf.MedPrivelegeCode = MP.MedPrivelegeCode LIMIT 1) AS used" : ""; $sql = <<<"EOT"
select
    MP.MedPrivelegeCode "id",
    MP.Name "name",
    MP.PrivilegePercent "exemption",
    MP.Social "social",
    MP.MedPrivilegeGroupCode "contingentGroupId",
    MPG.Name "contingentGroupName",
    MP.UnderAgeYear "underAgeYear",
    MP.UnderAgeMonth "underAgeMonth",
    MP.AfterAgeYear "afterAgeYear",
    MP.AfterAgeMonth "afterAgeMonth"
    $stateSql
from MedPrivelege MP
left join MedPrivilegeGroup MPG ON MP.MedPrivilegeGroupCode = MPG.MedPrivilegeGroupCode
order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateContingents($records, $params = null) { $db = $this->db ?: $this->db = connectDb(); if (isset($params) && isset($params->stateId)) { $stateId = isUndefinedProperty($params, 'stateId') ? (int) $params->stateId : 0; if (is_object($records)) { $records = [$records]; } $sqlInsert = <<<"EOT"
INSERT INTO MedPrivelegeFeature(MedPrivelegeCode, picture)
values(?, $stateId)
EOT;
$sqlDelete = <<<"EOT"
DELETE FROM MedPrivelegeFeature WHERE MedPrivelegeCode = ? and picture = $stateId
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtDelete = $db->prepare($sqlDelete); $db->beginTransaction(); try { foreach ($records as $row) { if (isUndefinedProperty($row, 'used')) { $stmtInsert->execute([ $row->id ]); } else { $stmtDelete->execute([ $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } else { $sql = <<<"EOT"
update MedPrivelege set
       Name = case when ? = 1 then ? else Name end,
       PrivilegePercent = case when ? = 1 then ? else PrivilegePercent end,
       Social = case when ? = 1 then ? else Social end,
       MedPrivilegeGroupCode = case when ? = 1 then ? else MedPrivilegeGroupCode end,
       UnderAgeYear = case when ? = 1 then ? else UnderAgeYear end,
       UnderAgeMonth = case when ? = 1 then ? else UnderAgeMonth end,
       AfterAgeYear = case when ? = 1 then ? else AfterAgeYear end,
       AfterAgeMonth = case when ? = 1 then ? else AfterAgeMonth end
where MedPrivelegeCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'exemption'), isEmptyProperty($row, 'exemption'), propertyExistsSQL($row, 'social'), isEmptyProperty($row, 'social'), propertyExistsSQL($row, 'contingentGroupId'), isEmptyProperty($row, 'contingentGroupId'), propertyExistsSQL($row, 'underAgeYear'), isUndefinedProperty($row, 'underAgeYear'), propertyExistsSQL($row, 'underAgeMonth'), isEmptyProperty($row, 'underAgeMonth'), propertyExistsSQL($row, 'afterAgeYear'), isEmptyProperty($row, 'afterAgeYear'), propertyExistsSQL($row, 'afterAgeMonth'), isEmptyProperty($row, 'afterAgeMonth'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } return true; } public function deleteContingents($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPrivelege where MedPrivelegeCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createContingents($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedPrivelege (Name, PrivilegePercent, Social, MedPrivilegeGroupCode, UnderAgeYear, UnderAgeMonth, AfterAgeYear, AfterAgeMonth, Cipher)
values (?, ?, ?, ?, ?, ?, ?, ?, (select incstr(trim(MAX(repeat(' ', 20 - length(Cipher)) || Cipher))) from MedPrivelege))
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'exemption'), isUndefinedProperty($row, 'social'), isUndefinedProperty($row, 'contingentGroupId'), isUndefinedProperty($row, 'underAgeYear'), isUndefinedProperty($row, 'underAgeMonth'), isUndefinedProperty($row, 'afterAgeYear'), isUndefinedProperty($row, 'afterAgeMonth') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPrivelege'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } $service = new Junior\Server(new med_common_contingents()); $service->process(); 