<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_common_patientsInsurances { private $db; public function readPatientInsurances($params) { $db = $this->db ?: $this->db = connectDb(); $isArchive = isUndefinedProperty($params, 'isArchive', 0); $patientId = isEmptyProperty($params, 'patientId', 0); $sql =<<<"EOT"
    SELECT
        PI.pmsPatientInsuranceCode "id",
        PI.documentNumber "documentNumber",
        PI.program "program",
        PI.medPatientCode "patientId",
        PI.enterpriseCodeSupply "enterpriseSupplyId",
        E.name "enterpriseSupplyName",
        PI.startDate "startDate",
        PI.finishDate "finishDate"
    FROM PmsPatientInsurance PI
    LEFT JOIN Enterprise E on E.enterpriseCode = COALESCE(PI.enterpriseCodeSupply,0)
    WHERE PI.medPatientCode = ?
EOT;
return selectSql($db, $sql, [ $patientId ]); } public function createPatientInsurances($records) { $db = $this->db ?: $this->db = connectDb(); $sql =<<<"EOT"
    INSERT INTO PmsPatientInsurance(documentNumber, enterpriseCodeSupply, finishDate, medPatientCode, program, startDate)
    VALUES(?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'documentNumber'), isUndefinedProperty($row, 'enterpriseSupplyId'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'program'), isUndefinedProperty($row, 'startDate') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_PmsPatientInsurance'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientInsurances($records) { $db = $this->db ?: $this->db = connectDb(); $sql =<<<"EOT"
update PmsPatientInsurance set
    documentNumber = case when ? = 1 then ? else documentNumber end,
    enterpriseCodeSupply = case when ? = 1 then ? else enterpriseCodeSupply end,
    finishDate = case when ? = 1 then ? else finishDate end,
    program = case when ? = 1 then ? else program end,
    startDate = case when ? = 1 then ? else startDate end
where pmsPatientInsuranceCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'documentNumber'), isEmptyProperty($row, 'documentNumber'), propertyExistsSQL($row, 'enterpriseSupplyId'), isEmptyProperty($row, 'enterpriseSupplyId'), propertyExistsSQL($row, 'finishDate'), isEmptyProperty($row, 'finishDate'), propertyExistsSQL($row, 'program'), isEmptyProperty($row, 'program'), propertyExistsSQL($row, 'startDate'), isEmptyProperty($row, 'startDate'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePatientInsurances ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'PmsPatientInsurance', 'pmsPatientInsuranceCode', $records); } } handleJsonRpc('pmed_common_patientsInsurances');