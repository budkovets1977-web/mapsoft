<?php
 require_once(__DIR__.'/../../init.php'); class med_common_vaccines { private $db; public function readVaccines($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isset($params->search) ? " AND mv.name LIKE '%$params->search%' " : " "; if (isset($params->vaccineId)){ $search .= " AND MV.MedVaccineCode = $params->vaccineId"; } $sql = <<<"SQL"
            SELECT
                MV.MedVaccineCode "id",
                MV.Name AS "name",
                MV.ReportName AS "reportName",
                MV.DoseMeasure AS "doseMeasure",
                MV.DoseSize AS "doseSize",
                MV.CountryCodeMade AS "countryId",
                C.Name AS "countryName",
                string_agg(DISTINCT(MI.Name::text), '; ') AS "infectionNames",
                string_agg(DISTINCT(MI.MedInfectionCode::text), ',') AS "infectionIds",
                MV.Json AS "json"
            FROM MedVaccine AS MV
                LEFT JOIN Country AS C ON C.CountryCode = MV.CountryCodeMade
                LEFT JOIN MedVaccineInfection AS MVI ON MVI.MedVaccineCode = MV.MedVaccineCode
                LEFT JOIN MedInfection AS MI ON MI.MedInfectionCode = MVI.MedInfectionCode
            WHERE 1 = 1 $search
            GROUP BY MV.MedVaccineCode, C.Name
            ORDER BY MV.Name
        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readVaccineInfections($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isset($params->vaccineId) ? " AND medVaccineCode = $params->vaccineId" : ""; $sql = <<< "EOT"
SELECT
    medVaccineInfectionCode "id",
    medVaccineCode AS "vaccineId",
    medInfectionCode AS "infectionId"
FROM
    MedVaccineInfection
WHERE
    1 = 1
    $search
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readLeftOverVaccines($params = null) { $db = $this->db ?: $this->db = connectDb(); $limit = isset($params->limit) && (int)$params->limit >= 0 ? (int)$params->limit : 20; $offset = isset($params->start) && (int)$params->start >= 0 ? (int)$params->start : 0; $onDate = !empty($params->onDate) ? $params->onDate : date('Y-m-d'); $enterpriseId = isset($params->enterpriseId) ? (int)$params->enterpriseId : null; $infectionId = isset($params->infectionId) ? (int)$params->infectionId : null; $findName = isset($params->findName) ? trim($params->findName) : ''; $vaccineId = isset($params->vaccineId) ? (int)$params->vaccineId : null; $vaccinePartyId = isset($params->vaccinePartyId) ? (int)$params->vaccinePartyId: null; $validTerm = isset($params->validTerm) ? trim($params->validTerm) : null; $serial = isset($params->serial) ? trim($params->serial) : null; $nurseId = isset($params->nurseId) ? (int)$params->nurseId : null; $bind = [ ':OnDate1' => $onDate, ':OnDate2' => $onDate, ]; $where = []; if (!empty($enterpriseId)) { $where[] = 'MVP.EnterpriseCode = :EnterpriseCode'; $bind[':EnterpriseCode'] = $enterpriseId; } if (!empty($infectionId) && $infectionId > 0) { $where[] = 'EXISTS (SELECT 1 FROM MedVaccineInfection VI WHERE VI.MedInfectionCode = :MedInfectionCode AND VI.MedVaccineCode = MV.MedVaccineCode)'; $bind[':MedInfectionCode'] = $infectionId; } if ($findName !== '') { $where[] = 'MV.Name ILIKE :FindName'; $bind[':FindName'] = '%'.$findName.'%'; } if (!empty($vaccineId)) { $where[] = 'MVP.MedVaccineCode = :VaccineId'; $bind[':VaccineId'] = $vaccineId; } if (!empty($vaccinePartyId)) { $where[] = 'MVP.MedVaccinePartyCode = :VaccinePartyId'; $bind[':VaccinePartyId'] = $vaccinePartyId; } if (!empty($validTerm)) { $where[] = 'MVP.ValidTerm = :ValidTerm'; $bind[':ValidTerm'] = $validTerm; } if (!empty($serial)) { $where[] = 'MVP.Serial = :Serial'; $bind[':Serial'] = $serial; } if (!empty($nurseId)) { $where[] = 'MVM.BuhMolCode = :NurseId'; $bind[':NurseId'] = $nurseId; } $whereSql = ''; if (!empty($where)) { $whereSql = "\n  AND " . implode("\n  AND ", $where) . "\n"; } $calcSub = <<<SQL
        SELECT C1.MedVaccinePartyCode,
           (C1.AmountD - C1.AmountK) AS dosesRemain,
           C1.OnDate                  AS CalcDate
        FROM MedVaccineCalc C1
        JOIN (
            SELECT MedVaccinePartyCode, MAX(OnDate) AS MaxOnDate
            FROM MedVaccineCalc
            WHERE OnDate <= :OnDate1
            GROUP BY MedVaccinePartyCode
        ) CM ON CM.MedVaccinePartyCode = C1.MedVaccinePartyCode
            AND C1.OnDate = CM.MaxOnDate
SQL;
$incomingLastSub = <<<SQL
        SELECT DISTINCT ON (MedVaccinePartyCode)
           MedVaccinePartyCode,
           BuhMolCode,
           OnDate AS LastDate
        FROM MedVaccineMove
        WHERE MoveType::int = 1 AND OnDate <= :OnDate2
        ORDER BY MedVaccinePartyCode, OnDate DESC
        SQL;
$baseSelect = <<<SQL
        SELECT
            MVP.MedVaccinePartyCode                AS "id",
            MV.MedVaccineCode                      AS "vaccineId",
            MV.Name                                AS "name",
            MV.ReportName                          AS "reportName",
            MV.DoseSize                            AS "doseSize",
            MV.DoseMeasure                         AS "doseMeasure",
            MVP.ValidTerm                          AS "validTerm",
            MVP.Serial                             AS "serial",
            MVM.BuhMolCode                         AS "buhmolcode",
            MVP.ControlNumber                      AS "controlNumber",
            (SELECT Name FROM Country WHERE CountryCode = MV.CountryCodeMade) AS "country",
            C.dosesRemain                          AS "dosesRemain",
            C.CalcDate                             AS "getDate",
            BM.BuhMolCode                          AS "givePersonId",
            fn_cmFio(bm.ManCode)                   AS "fioBm",
            D.Name                                 AS "departmentName",
            MV.Json                                AS "json"
        FROM MedVaccineParty MVP
        JOIN MedVaccine MV
          ON MV.MedVaccineCode = MVP.MedVaccineCode
        JOIN ({$calcSub}) C
          ON C.MedVaccinePartyCode = MVP.MedVaccinePartyCode
        LEFT JOIN ({$incomingLastSub}) MVM
          ON MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
        LEFT JOIN BuhMol BM
          ON BM.BuhMolCode = MVM.BuhMolCode
        LEFT JOIN Department D
          ON D.DepartmentCode = BM.DepartmentCode
        WHERE
            C.DosesRemain > 0
        {$whereSql}
        SQL;
$itemsSql = $baseSelect . "ORDER BY 3 ASC\nLIMIT {$limit} OFFSET {$offset};"; $stmt = $db->prepare($itemsSql); $stmt->execute($bind); $items = $stmt->fetchAll(); $totalSql = "SELECT COUNT(*) AS \"total\" FROM (\n{$baseSelect}\n) T;"; $stmtTotal = $db->prepare($totalSql); $stmtTotal->execute($bind); $total = fetchValue($stmtTotal, 'total'); return (object)[ 'items' => $items, 'total' => $total ]; } public function createVaccines($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            INSERT INTO MedVaccine 
                (Name, ReportName, DoseMeasure, DoseSize, CountryCodeMade, Json) 
            VALUES (?, ?, ?, ?, ?, ?)
        SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'reportName'), isUndefinedProperty($row, 'doseMeasure'), isUndefinedProperty($row, 'doseSize'), isUndefinedProperty($row, 'countryId'), isUndefinedProperty($row, 'json'), ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedVaccine'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createVaccineInfections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    insert into MedVaccineInfection (medVaccineCode, medInfectionCode)
    values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'vaccineId'), isUndefinedProperty($row, 'infectionId'), ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedVaccineInfection'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateVaccines($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            UPDATE MedVaccine SET
                Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
                ReportName = CASE WHEN ? = 1 THEN ? ELSE ReportName END,
                DoseMeasure = CASE WHEN ? = 1 THEN ? ELSE DoseMeasure END,
                DoseSize = CASE WHEN ? = 1 THEN ? ELSE DoseSize END,
                CountryCodeMade = CASE WHEN ? = 1 THEN ? ELSE CountryCodeMade END,
                Json = CASE WHEN ? = 1 THEN ? ELSE JSON END
            WHERE MedVaccineCode = ?
        SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'reportName'), isEmptyProperty($row, 'reportName'), propertyExistsSQL($row, 'doseMeasure'), isEmptyProperty($row, 'doseMeasure'), propertyExistsSQL($row, 'doseSize'), isEmptyProperty($row, 'doseSize'), propertyExistsSQL($row, 'countryId'), isEmptyProperty($row, 'countryId'), propertyExistsSQL($row, 'json'), isEmptyProperty($row, 'json'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateVaccineInfections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedVaccineInfection set
       medVaccineCode = case when ? = 1 then ? else medVaccineCode end,
       medInfectionCode = case when ? = 1 then ? else medInfectionCode end
where medVaccineInfectionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'vaccineId'), isUndefinedProperty($row, 'vaccineId'), propertyExistsSQL($row, 'infectionId'), isEmptyProperty($row, 'infectionId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteVaccines($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedVaccine', 'MedVaccineCode', $records); } public function deleteVaccineInfections($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedVaccineInfection', 'MedVaccineInfectionCode', $records); } } $service = new Junior\Server(new med_common_vaccines()); $service->process(); 