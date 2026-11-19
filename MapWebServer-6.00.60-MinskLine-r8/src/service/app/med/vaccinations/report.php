<?php
 require_once(__DIR__ . '/../../init.php'); class med_vaccinations_report { private $db; public function readReport() { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    SELECT
        medInoculateReportCode "id",
        name "name"
    FROM MedInoculateReport
    order by 2
EOT;
return selectSql($db, $sql); } public function createReport($records) { $db = $this->db ?: $this->db = connectDb(); $sqlInsert = <<< "EOT"
    INSERT INTO MedInoculateReport (name)
    VALUES (?)
EOT;
$stmt = $db->prepare($sqlInsert); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'name') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_MedInoculateReport'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateReport($records) { $db = $this->db ?: $this->db = connectDb(); $sqlUpdate = <<< "EOT"
    UPDATE MedInoculateReport
    SET
       name = case when ? = 1 then ? else name end
    where medInoculateReportCode = ?
EOT;
$stmt = $db->prepare($sqlUpdate); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), isEmptyProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteReport($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInoculateReport', 'medInoculateReportCode', $records); } public function readReportRow($params) { $db = $this->db ?: $this->db = connectDb(); $reportId = isUndefinedProperty($params, 'reportId', 0); $sql = <<< "EOT"
    SELECT
        RR.medInoculateReportRowCode "id",
        RR.medInoculateReportCode "reportId",
        RR.position "position",
        RR.rowNumber "rowNumber",
        RR.name "name",
        RR.ageFrom "ageFrom",
        RR.ageTo "ageTo",
        RR.monthFrom "monthFrom",
        RR.monthTo "monthTo",
        string_agg (RI.medInoculateCode, ',') as "inoculateIds",
        string_agg (RI.medVaccineCode, ',') as "vaccineIds",
        string_agg (RI.medInfectionCode, ',') as "infectionIds",
        string_agg (concat_ws('-',MIG.name,MI.name), ',') as "inoculateNames",
        string_agg (MV.name, ',') as "vaccineNames",
        string_agg (MIF.name, ',') as "infectionNames"
FROM medInoculateReportRow RR
LEFT JOIN medInoculateReportIn as RI on RI.medInoculateReportRowCode = RR.medInoculateReportRowCode
LEFT JOIN medInoculate as MI on MI.medInoculateCode = RI.medInoculateCode
LEFT JOIN medVaccine as MV on MV.medVaccineCode = RI.medVaccineCode
LEFT JOIN medInfection as MIF on MIF.medInfectionCode = RI.medInfectionCode
LEFT JOIN medInoculateGroup as MIG on MIG.medInoculateGroupCode = MI.medInoculateGroupCode
WHERE RR.medInoculateReportCode = $reportId
GROUP BY 1,2,3,4,5,6,7,8
ORDER BY position
/*ORDER BY RIGHT(REPEAT( E' ' ,20)+asa_trim(RR.rowNumber),20) ASC*/
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); $rows = []; foreach ($ret as $row) { $row['inoculateIds'] = $this->getArraysGroup($row, 'inoculateIds'); $row['vaccineIds'] = $this->getArraysGroup($row, 'vaccineIds'); $row['infectionIds'] = $this->getArraysGroup($row, 'infectionIds'); $rows[] = $row; } return $rows; } public function createReportRow($records, $param) { $db = $this->db ?: $this->db = connectDb(); $sqlInsert = <<< "EOT"
    INSERT INTO medInoculateReportRow (medInoculateReportCode, position, rowNumber, name, ageFrom, ageTo, monthFrom, monthTo)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    RETURNING position AS position
EOT;
$stmt = $db->prepare($sqlInsert); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($param, 'reportId'), isEmptyProperty($param, 'position'), isEmptyProperty($row, 'rowNumber'), isEmptyProperty($row, 'name'), isEmptyProperty($row, 'ageFrom'), isEmptyProperty($row, 'ageTo'), isEmptyProperty($row, 'monthFrom'), isEmptyProperty($row, 'monthTo') ]); $rec = new stdClass; $rec->id = (int) $db->lastInsertId('seq_medInoculateReportRow'); $log = $stmt->fetch(); $rec->position = $log['position']; $tagsIUD = isEmptyProperty($row, 'tagsIUD'); if ($tagsIUD) { foreach ($tagsIUD as $action) { $this->updateIncoming($action, $rec->id); } } $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateReportRow($records, $param) { $db = $this->db ?: $this->db = connectDb(); $reportId = isUndefinedProperty($param, 'reportId', 0); $sqlUpdate = <<< "EOT"
    UPDATE MedInoculateReportRow
    SET
       MedInoculateReportCode = case when ? = 1 then ? else MedInoculateReportCode end,
       position = case when ? = 1 then ? else position end,
       rowNumber = case when ? = 1 then ? else rowNumber end,
       name = case when ? = 1 then ? else name end,
       ageFrom = case when ? = 1 then ? else ageFrom end,
       ageTo = case when ? = 1 then ? else ageTo end,
       monthFrom = case when ? = 1 then ? else monthFrom end,
       monthTo = case when ? = 1 then ? else monthTo end
    where medInoculateReportRowCode = ?
EOT;
$stmt = $db->prepare($sqlUpdate); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, isEmptyProperty($row, 'id') ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($param, 'reportId'), isUndefinedProperty($param, 'reportId', 0), propertyExistsSQL($row, 'position'), isEmptyProperty($row, 'position'), propertyExistsSQL($row, 'rowNumber'), isEmptyProperty($row, 'rowNumber'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'ageFrom'), isEmptyProperty($row, 'ageFrom'), propertyExistsSQL($row, 'ageTo'), isEmptyProperty($row, 'ageTo'), propertyExistsSQL($row, 'monthFrom'), isEmptyProperty($row, 'monthFrom'), propertyExistsSQL($row, 'monthTo'), isEmptyProperty($row, 'monthTo'), isEmptyProperty($row, 'id') ]); $rec = new stdClass; $rec->id = isEmptyProperty($row, 'id'); $tagsIUD = isEmptyProperty($row, 'tagsIUD'); if ($tagsIUD) { foreach ($tagsIUD as $action) { $this->updateIncoming($action, $rec->id); } } $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteReportRow($records) { $db = $this->db ?: $this->db = connectDb(); deleteSql($db, 'medInoculateReportRow', 'medInoculateReportRowCode', $records); } public function updateIncoming($action, $reportRowId) { $db = $this->db ?: $this->db = connectDb(); $id = $action->id; $field = ""; switch ($action->field) { case 'tfInfections': $field = "medInfectionCode"; break; case 'tfInoculates' : $field = "medInoculateCode"; break; case 'tfVaccines' : $field = "medVaccineCode"; break; } $sqlInsert = <<< "EOT"
    INSERT INTO medInoculateReportIn($field, medInoculateReportRowCode)
    VALUES($id, $reportRowId)
EOT;
$sqlDelete = <<< "EOT"
    DELETE FROM medInoculateReportIn
    WHERE $field = $id AND medInoculateReportRowCode = $reportRowId
EOT;
$stmt = $db->prepare($action->iud === 1 ? $sqlInsert : $sqlDelete); $stmt->execute(); } private function getArraysGroup($obj, $field) { $obj[$field] = safe_explode(',', $obj[$field]); foreach ($obj[$field] as $index => $value) { $obj[$field][$index] = (int) $value; } return $obj[$field]; } } handleJsonRpc('med_vaccinations_report'); 