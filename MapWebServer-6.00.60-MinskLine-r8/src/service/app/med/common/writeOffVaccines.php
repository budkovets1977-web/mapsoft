<?php
 require_once(__DIR__.'/../../init.php'); class med_common_writeoffvaccines { private $db; public function readWriteOffVaccines($params = null) { $db = $this->db ?: $this->db = connectDb(); $limit = isset($params->limit) && $params->limit >= 0 ? $params->limit : 20; $offset = isset($params->start) && $params->start >= 0 ? $params->start : 0; $sqlWhere = ''; if (is_object($params->dateRange) && isset($params->dateRange->from) && isset($params->dateRange->to) ) { $sqlWhere .= "AND MVM.OnDate BETWEEN '{$params->dateRange->from}' AND '{$params->dateRange->to}' "; } if (!empty($params->infectionId)) { $sqlWhere .= "AND {$params->infectionId} IN (SELECT MedInfectionCode FROM MedVaccineInfection WHERE MedVaccineCode = MVP.MedVaccineCode) "; } if (!empty($params->vaccineId)) { $sqlWhere .= "AND MVP.MedVaccineCode = {$params->vaccineId} "; } $query = <<<"SQL"
            SELECT
                MVM.MedVaccineMoveCode      "id",
                MVM.OnDate                  "onDate",
                MV.MedVaccineCode           "vaccineId",
                MVP.MedVaccinePartyCode     "vaccinePartyId",
                MV.Name                     "vaccineName",
                MVP.Serial                  "serial",
                MVP.ControlNumber           "controlNumber",
                C.Name                      "country",
                MV.DoseSize                 "doseSize",
                MV.DoseMeasure              "doseMeasure",
                MVM.Amount                  "dosesAmount",
                MVP.ValidTerm               "validTerm",
                MVM.OutReason               "reason",
                MVM.EnterpriseCodeSupply    "organizationId",
                E.Name                      "organizationName",
                BM_out.BuhMolCode              "givePersonId",
                FN_cmFIO(BM_out.ManCode)    "givePersonName",
                BM_in.BuhMolCode               "getPersonId",
                FN_cmFIO(BM_in.ManCode)     "getPersonName",
                MVM.Note                    "note",
                MV.Json                     "json"
            FROM MedVaccineMove AS MVM
                LEFT JOIN MedVaccineParty AS MVP ON MVP.MedVaccinePartyCode = MVM.MedVaccinePartyCode
                LEFT JOIN MedVaccine AS MV ON MV.MedVaccineCode = MVP.MedVaccineCode
                LEFT JOIN Country AS C ON C.CountryCode = MV.CountryCodeMade
                LEFT JOIN Enterprise AS E ON E.EnterpriseCode = MVM.EnterpriseCodeSupply
                LEFT JOIN BuhMol AS BM_in ON BM_in.BuhMolCode = MVM.BuhMolCode
                LEFT JOIN BuhMol AS BM_out ON BM_out.BuhMolCode = MVM.BuhMolCodeK
            WHERE MVM.MoveType = 2 $sqlWhere
            ORDER BY 2 DESC
            LIMIT $limit OFFSET $offset;
        SQL;
$stmt = $db->prepare($query); $stmt->execute(); $queryTotal = <<<"SQL"
            SELECT COUNT(MVM.MedVaccineMoveCode) "total"
            FROM MedVaccineMove AS MVM
                LEFT JOIN MedVaccineParty AS MVP ON MVP.MedVaccinePartyCode = MVM.MedVaccinePartyCode
            WHERE MVM.MoveType = 2 $sqlWhere;
        SQL;
$stmtTotal = $db->prepare($queryTotal); $stmtTotal->execute(); return (object) [ 'items' => $stmt->fetchAll(), 'total' => fetchValue($stmtTotal, 'total') ]; } public function createWriteOffVaccines($records) { $db = $this->db ?: $this->db = connectDb(); $reason = isUndefinedProperty($records, 'reason'); $sql = <<<SQL
        INSERT INTO MedVaccineMove 
            (OnDate, Amount, BuhMolCode, BuhMolCodeK, EnterpriseCodeSupply, MedVaccinePartyCode, MoveType, Note, OutReason)
        VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?);
    SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'onDate'), isUndefinedProperty($row, 'dosesAmount'), isUndefinedProperty($row, 'getPersonId'), isUndefinedProperty($row, 'givePersonId'), isUndefinedProperty($row, 'organizationId'), isUndefinedProperty($row, 'vaccinePartyId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'reason'), ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedVaccineMove'); $ret[] = $rec; } if ($reason === 4) { foreach ($records as $row) { $vaccinePartyCode = isUndefinedProperty($row, 'vaccinePartyId'); $getpersonId = isUndefinedProperty($row, 'getPersonId'); $amount = isUndefinedProperty($row, 'dosesAmount'); } $sqlParty = <<<SQL
                SELECT
                    ControlNumber,
                    DoseSize,
                    MedVaccineCode,
                    EnterpriseCode,
                    Manufacture, 
                    Serial, 
                    ValidTerm, 
                    TransportTemperature
                FROM MedVaccineParty
                WHERE MedVaccinePartyCode = ?
            SQL;
$stmtParty = $db->prepare($sqlParty); $stmtParty->execute([$vaccinePartyCode]); $partyresult = $stmtParty->fetchAll(); if (!empty($partyresult)) { $party = $partyresult[0]; $sqlFindExistingParty = <<<SQL
                    SELECT MedVaccinePartyCode FROM MedVaccineParty
                    WHERE MedVaccineCode = ?
                      AND Serial = ?
                      AND ValidTerm = ?
                      AND ControlNumber = ?
                      AND BuhMolCode = ?
                SQL;
$stmtFind = $db->prepare($sqlFindExistingParty); $stmtFind->execute([ $party['medvaccinecode'], $party['serial'], $party['validterm'], $party['controlnumber'], $getpersonId ]); $existingParty = $stmtFind->fetch(); if ($existingParty) { $newPartyId = $existingParty['medvaccinepartycode']; } else { $sqlTransferParty = <<<SQL
                        INSERT INTO MedVaccineParty (
                            ControlNumber,
                            DoseSize,
                            MedVaccineCode,
                            EnterpriseCode,
                            Manufacture,
                            Serial,
                            ValidTerm,
                            TransportTemperature,
                            buhmolcode
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    SQL;
$stmtTransferParty = $db->prepare($sqlTransferParty); $stmtTransferParty->execute([ $party['controlnumber'], $party['dosesize'], $party['medvaccinecode'], $party['enterprisecode'], $party['manufacture'], $party['serial'], $party['validterm'], $party['transporttemperature'], $getpersonId ]); $newPartyId = $db->lastInsertId(); } $sqlTransferMove = <<<SQL
                    INSERT INTO MedVaccineMove
                        (OnDate, Amount, BuhMolCode, EnterpriseCodeSupply, MedVaccinePartyCode, MoveType, Note, OutReason)
                    VALUES (?, ?, ?, ?, ?, 1, ?, ?);
                SQL;
$stmtTransferMove = $db->prepare($sqlTransferMove); foreach ($records as $row) { $stmtTransferMove->execute([ isUndefinedProperty($row, 'onDate'), isUndefinedProperty($row, 'dosesAmount'), isUndefinedProperty($row, 'getPersonId'), isUndefinedProperty($row, 'organizationId'), $newPartyId, isUndefinedProperty($row, 'note'), null ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWriteOffVaccines($records) { $db = $this->db ?: $this->db = connectDb(); $sqlPostParty = <<<SQL
SELECT MedVaccinePartyCode, OnDate, BuhMolCode, Amount, enterprisecodesupply FROM MedVaccineMove WHERE MedVaccineMoveCode = ?;
SQL;
$stmtPostParty = $db->prepare($sqlPostParty); $stmtPostParty->execute([$records->id]); $res = $stmtPostParty->fetch(); if ($res) { $medVaccinePartyCode = $res['medvaccinepartycode']; $amount = $res['amount']; $buhmolCode = $res['buhmolcode']; $onDate = $res['ondate']; $sqlGetParty = <<<SQL
SELECT medVaccinePartyCode,
       buhmolcodek
    FROM MedVaccineMove
    WHERE medVaccinePartyCode != ?
      AND amount = ?
      AND buhmolCode = ?
      AND onDate LIKE ?
      AND MoveType  = 1
SQL;
$stmtGetParty = $db->prepare($sqlGetParty); $stmtGetParty->execute([ $medVaccinePartyCode, $amount, $buhmolCode, $onDate ]); $resMoveId = $stmtGetParty->fetch(); if ($resMoveId) { $moveId = $resMoveId['medvaccinepartycode']; if (isset($records->reason) && $records->reason && $records->reason !== 4) { $sqlDeleteMove = <<<SQL
                    DELETE FROM MedVaccineMove
                    WHERE medVaccinePartyCode = ?
                SQL;
$stmtDeleteMove = $db->prepare($sqlDeleteMove); $stmtDeleteMove->execute([$moveId]); $sqlDeleteParty = <<<SQL
                    DELETE FROM MedVaccineParty
                    WHERE MedVaccinePartyCode = ?
                SQL;
$stmtDeleteParty = $db->prepare($sqlDeleteParty); $stmtDeleteParty->execute([$moveId]); } else { $sqlUpdateTransfer = <<<"SQL"
                    UPDATE MedVaccineMove SET
                        OnDate = CASE WHEN ? = 1 THEN ? ELSE OnDate END,
                        Amount = CASE WHEN ? = 1 THEN ? ELSE Amount END,
                        BuhMolCode = CASE WHEN ? = 1 THEN ? ELSE BuhMolCode END,
                        BuhMolCodeK = CASE WHEN ? = 1 THEN ? ELSE BuhMolCodeK END,
                        EnterpriseCodeSupply = CASE WHEN ? = 1 THEN ? ELSE EnterpriseCodeSupply END,
                        MedVaccinePartyCode = CASE WHEN ? = 1 THEN ? ELSE MedVaccinePartyCode END,
                        Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
                        OutReason = CASE WHEN ? = 1 THEN ? ELSE OutReason END
                    WHERE MoveType = 1 AND medVaccinePartyCode = ?;
                SQL;
$stmtUpdateTransfer = $db->prepare($sqlUpdateTransfer); if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { $stmtUpdateTransfer->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'dosesAmount'), isUndefinedProperty($row, 'dosesAmount'), propertyExistsSQL($row, 'getPersonId'), isUndefinedProperty($row, 'getPersonId'), propertyExistsSQL($row, 'givePersonId'), isUndefinedProperty($row, 'givePersonId'), propertyExistsSQL($row, 'organizationId'), isUndefinedProperty($row, 'organizationId'), propertyExistsSQL($row, 'vaccinePartyId'), isUndefinedProperty($row, 'vaccinePartyId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'reason'), isUndefinedProperty($row, 'reason'), $moveId ]); } } } else if (isset($records->reason) && $records->reason && $records->reason === 4) { $sqlParty = <<<SQL
                    SELECT
                        ControlNumber,
                        DoseSize,
                        MedVaccineCode,
                        EnterpriseCode,
                        Manufacture, 
                        Serial, 
                        ValidTerm, 
                        TransportTemperature
                    FROM MedVaccineParty
                    WHERE MedVaccinePartyCode = ?
                SQL;
$stmtParty = $db->prepare($sqlParty); $stmtParty->execute([$medVaccinePartyCode]); $partyresult = $stmtParty->fetchAll(); $party = $partyresult[0]; $sqlTransferParty = <<<SQL
                        INSERT INTO MedVaccineParty (
                            ControlNumber,
                            DoseSize,
                            MedVaccineCode,
                            EnterpriseCode,
                            Manufacture,
                            Serial,
                            ValidTerm,
                            TransportTemperature,
                            buhmolcode
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    SQL;
$stmtTransferParty = $db->prepare($sqlTransferParty); $stmtTransferParty->execute([ $party['controlnumber'], $party['dosesize'], $party['medvaccinecode'], $party['enterprisecode'], $party['manufacture'], $party['serial'], $party['validterm'], $party['transporttemperature'], isUndefinedProperty($records, 'getPersonId') ]); $newPartyId = $db->lastInsertId(); $sqlTransferMove = <<<SQL
                        INSERT INTO MedVaccineMove
                            (OnDate, Amount, BuhMolCode, EnterpriseCodeSupply, MedVaccinePartyCode, MoveType, Note, OutReason)
                        VALUES (?, ?, ?, ?, ?, 1, ?, ?);
                    SQL;
$stmtTransferMove = $db->prepare($sqlTransferMove); $enterprisecodesupply = $res['enterprisecodesupply']; $stmtTransferMove->execute([ $onDate, $amount, isUndefinedProperty($records, 'getPersonId'), $enterprisecodesupply, $newPartyId, isUndefinedProperty($records, 'note'), null ]); } } $sql = <<<"SQL"
            UPDATE MedVaccineMove SET
                OnDate = CASE WHEN ? = 1 THEN ? ELSE OnDate END,
                Amount = CASE WHEN ? = 1 THEN ? ELSE Amount END,
                BuhMolCode = CASE WHEN ? = 1 THEN ? ELSE BuhMolCode END,
                BuhMolCodeK = CASE WHEN ? = 1 THEN ? ELSE BuhMolCodeK END,
                EnterpriseCodeSupply = CASE WHEN ? = 1 THEN ? ELSE EnterpriseCodeSupply END,
                MedVaccinePartyCode = CASE WHEN ? = 1 THEN ? ELSE MedVaccinePartyCode END,
                Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
                OutReason = CASE WHEN ? = 1 THEN ? ELSE OutReason END
            WHERE MoveType = 2 AND MedVaccineMoveCode = ?;
        SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (isset($row->getPersonName) && empty($row->getPersonName)) { $sqlBuhmolCode = <<<SQL
                        SELECT buhmolcodek
                            FROM MedVaccineMove
                            WHERE MedVaccineMoveCode = ?
                    SQL;
$stmtBuhmolCode = $db->prepare($sqlBuhmolCode); $stmtBuhmolCode->execute([ $row->id ]); $buhmolCode = $stmtBuhmolCode->fetch(); $buhmolCode = $buhmolCode['buhmolcodek']; $stmt->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'dosesAmount'), isUndefinedProperty($row, 'dosesAmount'), 1, 0, 1, $buhmolCode, propertyExistsSQL($row, 'organizationId'), isUndefinedProperty($row, 'organizationId'), propertyExistsSQL($row, 'vaccinePartyId'), isUndefinedProperty($row, 'vaccinePartyId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'reason'), isUndefinedProperty($row, 'reason'), $row->id ]); } else { $stmt->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'dosesAmount'), isUndefinedProperty($row, 'dosesAmount'), propertyExistsSQL($row, 'getPersonId'), isUndefinedProperty($row, 'getPersonId'), propertyExistsSQL($row, 'givePersonId'), isUndefinedProperty($row, 'givePersonId'), propertyExistsSQL($row, 'organizationId'), isUndefinedProperty($row, 'organizationId'), propertyExistsSQL($row, 'vaccinePartyId'), isUndefinedProperty($row, 'vaccinePartyId'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'reason'), isUndefinedProperty($row, 'reason'), $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWriteOffVaccines($records) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($records, 'id'); $sql = <<<SQL
        SELECT MedVaccinePartyCode, Amount, BuhmolCode, OnDate
        FROM MedVaccineMove
        WHERE MedVaccineMoveCode = ?
    SQL;
$stmt = $db->prepare($sql); $stmt->execute([$id]); $result = $stmt->fetch(); if (!$result) { throw new Exception("Списание не найдено."); } $writeOffPartyCode = $result['medvaccinepartycode']; $amount = $result['amount']; $buhmolCode = $result['buhmolcode']; $onDate = $result['ondate']; $sqlFindLinkedIncome = <<<SQL
        SELECT MedVaccineMoveCode, MedVaccinePartyCode
        FROM MedVaccineMove
        WHERE MoveType = 1
          AND Amount = ?
          AND BuhmolCode = ?
          AND OnDate::date = ?::date
          AND MedVaccinePartyCode != ?
        ORDER BY MedVaccineMoveCode DESC
        LIMIT 1
    SQL;
$stmt = $db->prepare($sqlFindLinkedIncome); $stmt->execute([$amount, $buhmolCode, $onDate, $writeOffPartyCode]); $linkedIncome = $stmt->fetch(); if ($linkedIncome) { $incomeMoveCode = $linkedIncome['medvaccinemovecode']; $incomePartyCode = $linkedIncome['medvaccinepartycode']; $stmt = $db->prepare("DELETE FROM MedVaccineMove WHERE MedVaccineMoveCode = ?"); $stmt->execute([$incomeMoveCode]); $stmt = $db->prepare("SELECT COUNT(*) FROM MedVaccineMove WHERE MedVaccinePartyCode = ?"); $stmt->execute([$incomePartyCode]); $moveCount = $stmt->fetchColumn(); if ((int)$moveCount === 0) { $stmt = $db->prepare("DELETE FROM MedVaccineParty WHERE MedVaccinePartyCode = ?"); $stmt->execute([$incomePartyCode]); } } return deleteSql($db, 'MedVaccineMove', 'MedVaccineMoveCode', $records); } } $service = new Junior\Server(new med_common_writeoffvaccines()); $service->process(); 