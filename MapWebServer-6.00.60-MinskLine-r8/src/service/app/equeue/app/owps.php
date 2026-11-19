<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_owps { private $db; public function readOWPs(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
select
    o.MedEqOWPCode "id",
    o.Number "number",
    o.Cabinet "cabinet",
    o.Active "active",
    o.Name "name",
    o.MedCabinetCode "medCabinetCode",
    s.MedEqServiceCode "serviceId",
    s.MedEqServiceCode "oldServiceId",
    s.Name || case when asa_trim(coalesce(s.Cipher, E'')) <> (E'') then E' (' || asa_trim(s.Cipher) || E')' else E'' end "serviceName",
    o.MedEqDeviceCodeConsole "consoleId",
    o.MedEqDeviceCodeDisplay "displayId",
    s.Active "serviceActive",
    MC.Cabinet "cabinetNumber"
from MedEqOWP o
left outer join MedEqService s on s.MedEqServiceCode = o.MedEqServiceCode
    and s.EnterpriseCode = o.EnterpriseCode
left outer join MedCabinet MC on o.MedCabinetCode = MC.MedCabinetCode
where o.EnterpriseCode = ?
order by 2, 5, 3, 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } public function updateOWPs($records, $noTransaction = false){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
update MedEqOWP set
    MedEqServiceCode = case when ? = 1 then ? else MedEqServiceCode end,
    Number = case when ? = 1 then ? else Number end,
    Cabinet = case when ? = 1 then ? else Cabinet end,
    Active = case when ? = 1 then ? else Active end,
    Name = case when ? = 1 then ? else Name end,
    MedEqDeviceCodeConsole = case when ? = 1 then ? else MedEqDeviceCodeConsole end,
    MedEqDeviceCodeDisplay = case when ? = 1 then ? else MedEqDeviceCodeDisplay end,
    MedCabinetCode = case when ? = 1 then ? else MedCabinetCode end
where MedEqOWPCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } if (!$noTransaction) { $db->beginTransaction(); } try { foreach($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'serviceId'), isUndefinedProperty($row, 'serviceId'), propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'cabinet'), isUndefinedProperty($row, 'cabinet'), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'consoleId'), isUndefinedProperty($row, 'consoleId'), propertyExistsSQL($row, 'displayId'), isUndefinedProperty($row, 'displayId'), propertyExistsSQL($row, 'medCabinetCode'), isUndefinedProperty($row, 'medCabinetCode'), $row->id ]); } if (!$noTransaction) { $db->commit(); } } catch (Throwable $e) { if (!$noTransaction) { $db->rollback(); } throw $e; } return true; } public function deleteOWPs($records){ $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = "DELETE FROM MedEqOWP WHERE MedEqOWPCode $in"; $stmt = $db->prepare($sql); $stmt->execute(); return true; } public function syncOWPs($owp, $categories){ $db = $this->db ?: $this->db = connectDb(); $owpId = (int)$owp->id; $db->beginTransaction(); try { if ($owpId == 0) { $sqlOWPInsert = <<<EOT
INSERT INTO MedEqOWP (MedEqServiceCode, Active, Number, Cabinet, Name, MedEqDeviceCodeConsole, MedEqDeviceCodeDisplay, EnterpriseCode, MedCabinetCode)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtOWPInsert = $db->prepare($sqlOWPInsert); $stmtOWPInsert->execute([ $owp->serviceId, isEmptyProperty($owp, 'active'), $owp->number, isEmptyProperty($owp, 'cabinet'), isEmptyProperty($owp, 'name'), isEmptyProperty($owp, 'consoleId'), isEmptyProperty($owp, 'displayId'), getSessionValue('mapEnterpriseCode'), isEmptyProperty($owp, 'medCabinetCode') ]); $owpId = $db->lastInsertId('seq_MedEqOWP'); } else { $this->updateOWPs($owp, true); } if ($owp->serviceId !== $owp->oldServiceId) { $sqlOWPCategoriesDelete = "DELETE FROM MedEqOWPCategory WHERE MedEqOWPCode = ?"; $stmtOWPCategoriesDelete = $db->prepare($sqlOWPCategoriesDelete); $stmtOWPCategoriesDelete->execute([ $owpId ]); } if (isset($categories)) { $in = createSqlInCondition($categories, 'id'); $sqlCategoryDelete = <<<EOT
DELETE FROM MedEqOWPCategory
WHERE MedEqOWPCode = $owpId AND NOT (MedEqServiceCategoryCode $in)
EOT;
$stmtCategoryDelete = $db->prepare($sqlCategoryDelete); $stmtCategoryDelete->execute(); $sqlCategoryInsert = <<<EOT
INSERT INTO MedEqOWPCategory (MedEqOWPCode, MedEqServiceCategoryCode)
        SELECT $owpId, s.MedEqServiceCategoryCode
        FROM MedEqServiceCategory s
        WHERE s.MedEqServiceCategoryCode $in
            AND NOT EXISTS(SELECT * FROM MedEqOWPCategory
                 WHERE MedEqOWPCode = $owpId AND MedEqServiceCategoryCode = s.MedEqServiceCategoryCode
                 LIMIT 1)
EOT;
$stmtCategoryInsert = $db->prepare($sqlCategoryInsert); $stmtCategoryInsert->execute(); $sqlCategoryUpdate = <<<EOT
UPDATE MedEqOWPCategory SET Type = ?
    WHERE MedEqOWPCode = ? AND MedEqServiceCategoryCode = ?
EOT;
$stmtCategoryUpdate = $db->prepare($sqlCategoryUpdate); if (is_object($categories)) { $categories = [$categories]; } foreach($categories as $row) { $stmtCategoryUpdate->execute([ $row->type, $owpId, $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $owpId; } } handleJsonRpc('equeue_app_owps');