<?php
require_once(__DIR__.'/../../init.php'); class med_common_clinics { private $db; public function readClinics($params = null) { $db = $this->db ?: $this->db = connectDb(); $isActive = isEmptyProperty($params, 'isActive'); $where = ''; if ($isActive === 1) { $where = 'WHERE MPP.Inactive = 0'; } $sql = <<<EOT
            select
                MPP.MedPatientPolyclinicCode    "id",
                MPP.MedPatientRegionCode        "districtId",
                MPR.Name                        "districtName",
                MPP.Name                        "name",
                MPP.Cipher                      "cipher",                                    
                CASE MPP.Inactive 
                    WHEN 1 THEN 0 
                    WHEN 0 THEN 1
                END                             "active",
                MPP.EnterpriseCode              "organizationId",
                E.Name                          "organizationName"
            from MedPatientPolyclinic MPP
            left join Enterprise E ON E.EnterpriseCode = MPP.EnterpriseCode
            left join MedPatientRegion MPR ON MPR.MedPatientRegionCode = MPP.MedPatientRegionCode
            $where
            order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateClinics($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
update MedPatientPolyclinic set
       Name = case when ? = 1 then ? else Name end,
       MedPatientRegionCode = case when ? = 1 then ? else MedPatientRegionCode end,
       Cipher = case when ? = 1 then ? else Cipher end,
       EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
       Inactive = case when ? = 1 then ? else Inactive end
where MedPatientPolyclinicCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'districtId'), isEmptyProperty($row, 'districtId'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'organizationId'), isEmptyProperty($row, 'organizationId'), propertyExistsSQL($row, 'active'), falseTo0(!isUndefinedProperty($row, 'active')), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteClinics($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<EOT
delete from MedPatientPolyclinic where MedPatientPolyclinicCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createClinics($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
insert into MedPatientPolyclinic (MedPatientRegionCode, Name, Cipher, EnterpriseCode, Inactive)
values (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'districtId'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'organizationId'), falseTo0(!isUndefinedProperty($row, 'active')) ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatientPolyclinic'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('med_common_clinics');