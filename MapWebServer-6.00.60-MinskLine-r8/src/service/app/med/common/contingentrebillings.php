<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_contingentRebillings { private $db; public function readContingentRebillings($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlWhere = ''; if (!empty($params->patientId)) { $sqlWhere .= "AND MPat.MedPatientCode = {$params->patientId} "; } if (!empty($params->contingentId)) { $sqlWhere .= "AND MPC.MedPatientCardCode = {$params->contingentId} "; } $sql = <<<SQL
            SELECT 
                MPCR.MedPatientCardRegCode  "id",
                MPCR.MedPatientCardCode     "contingentId",
                MPCR.RegistrationDate       "registrationDate",
                MPCR.Commission             "commission",
                MPCR.Rehabilitation         "rehabilitation",
                MPCR.EnterpriseCode         "organizationId",
                MPCR.MedProfessionCode      "specialityId",
                MPCR.MedDoctorCode          "doctorId",
                CASE WHEN (MPCR.MedDoctorCode IS NOT NULL)
                    THEN CONCAT_WS(', ', fn_cmFIO(MD.ManCode), MPro.Name)
                    ELSE CONCAT_WS(', ', Ent.Name, MPro.Name)
                END "doctorDisplay"
            FROM MedPatientCardReg MPCR
                LEFT JOIN MedPatientCard MPC ON MPC.MedPatientCardCode = MPCR.MedPatientCardCode
                LEFT JOIN MedPatient MPat ON MPat.MedPatientCode = MPC.MedPatientCode
                LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MPCR.MedDoctorCode
                LEFT JOIN MedProfession MPro 
                    ON MPro.MedProfessionCode = CASE WHEN (MPCR.MedDoctorCode IS NOT NULL) THEN MD.MedProfessionCode ELSE MPCR.MedProfessionCode END
                LEFT JOIN Enterprise Ent
                    ON Ent.EnterpriseCode = CASE WHEN (MPCR.MedDoctorCode IS NOT NULL) THEN MD.DepartmentCode ELSE MPCR.EnterpriseCode END
            WHERE 1 = 1 $sqlWhere
            ORDER BY MPCR.RegistrationDate DESC;
        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateContingentRebillings($records, $params = null) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) $records = [$records]; $sql = <<<SQL
            UPDATE MedPatientCardReg SET 
                MedPatientCardCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCardCode END, 
                RegistrationDate = CASE WHEN ? = 1 THEN ? ELSE RegistrationDate END, 
                Commission = CASE WHEN ? = 1 THEN ? ELSE Commission END,
                Rehabilitation = CASE WHEN ? = 1 THEN ? ELSE Rehabilitation END,
                MedDoctorCode = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END,
                EnterpriseCode = CASE WHEN ? = 1 THEN ? ELSE EnterpriseCode END,
                MedProfessionCode = CASE WHEN ? = 1 THEN ? ELSE MedProfessionCode END
            WHERE MedPatientCardRegCode = ?;
        SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'contingentId'), isUndefinedProperty($row, 'contingentId'), propertyExistsSQL($row, 'registrationDate'), isUndefinedProperty($row, 'registrationDate'), propertyExistsSQL($row, 'commission'), isUndefinedProperty($row, 'commission'), propertyExistsSQL($row, 'rehabilitation'), isUndefinedProperty($row, 'rehabilitation'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'organizationId'), isUndefinedProperty($row, 'organizationId'), propertyExistsSQL($row, 'specialityId'), isUndefinedProperty($row, 'specialityId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteContingentRebillings($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<SQL
            DELETE FROM MedPatientCardReg WHERE MedPatientCardRegCode $in;
        SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createContingentRebillings($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) $records = [$records]; $sql = <<<SQL
            INSERT INTO MedPatientCardReg 
                (MedPatientCardCode, RegistrationDate, Commission, Rehabilitation, MedDoctorCode, MedProfessionCode, EnterpriseCode)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?)
            RETURNING MedPatientCardRegCode AS id;
        SQL;
$result = []; $stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'contingentId'), isUndefinedProperty($row, 'registrationDate'), isUndefinedProperty($row, 'commission'), isUndefinedProperty($row, 'rehabilitation'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'specialityId'), isUndefinedProperty($row, 'organizationId'), ]); $result[] = $rec = new stdClass; $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } } $service = new Junior\Server(new med_common_contingentRebillings()); $service->process(); 