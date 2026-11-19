<?php  require_once(__DIR__ . '/../../init.php'); class med_vaccinations_vaccines_reception{ private $db; public function readVaccinesReception($params = null) { $db = $this->db ?: $this->db = connectDb(); $limit = isset($params->limit) && $params->limit >= 0 ? $params->limit : 20; $offset = isset($params->start) && $params->start >= 0 ? $params->start : 0; $infectionId = isEmptyProperty($params, 'infectionId'); $beginDate = dbDate(isEmptyProperty($params, 'beginDate')) ?: '1900-01-01'; $endDate = dbDate(isEmptyProperty($params, 'endDate')) ?: '3000-01-01'; $vaccineId = isEmptyProperty($params, 'vaccinesId'); $id = isEmptyProperty($params, 'id'); $sqlWhere = ''; if (isset($vaccineId)) { $sqlWhere .= "AND MVP.MedVaccineCode = $vaccineId "; } if (isset($infectionId)) { $sqlWhere .= "AND $infectionId IN (SELECT MedInfectionCode FROM MedVaccineInfection WHERE MedVaccineCode = MVP.MedVaccineCode) "; } if (!$id && ($endDate || $beginDate)) { $sqlWhere .= "AND MVM.onDate BETWEEN '$beginDate'::date AND '$endDate'::date "; } $sql = <<<SQL
        SELECT DISTINCT
           MVM.MedVaccineMoveCode                           as "id",
           MVM.OnDate                                       as "arrivalDate",
           MV.Name                                          as "vaccineName",
           MVP.MedVaccineCode                               as "vaccineCode", 
           MVP.serial                                       as "serial",
           MVP.ControlNumber                                as "controlNumber",
           (SELECT name FROM country 
            WHERE CountryCode = MV.countrycodemade)         as "manufacture",
            MV.DoseMeasure                                  as "doseMeasure",
           E.EnterpriseCode                                 as "enterpriseCodeSupply",
           E.fullname                                       as "enterpriseSupply",
           MVP.DoseSize                                     as "doseSize",
           MVM.Amount                                       as "amountDose",
           MVP.ValidTerm                                    as "validTerm",
           (SELECT Name FROM enterprise 
            WHERE EnterpriseCode = MVP.EnterpriseCode)      as "enterprise", 
            BM.ManCode                                       as "buhmanCode",
           m.Surname                                        as "manSurname", 
           fn_cmFio(BM.ManCode)                             as "fio",
           MVM.Note                                         as "note",
           MV.MedVaccineCode                                as "vaccineCode",
           MVP.TransportTemperature                         as "transportTemperature",
           (SELECT STRING_AGG(MVI.MedInfectionCode::text, ', ') 
            FROM medVaccineInfection MVI 
            WHERE MVI.MedVaccineCode = MV.MedVaccineCode)   as "infectionCodes",
            MV.Json                                         as "json"
        FROM MedVaccineMove MVM
        LEFT JOIN MedVaccineParty MVP ON MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
        LEFT JOIN MedVaccine MV ON MV.MedVaccineCode = MVP.MedVaccineCode
        LEFT JOIN buhmol BM ON MVM.BuhmolCode = BM.BuhmolCode
        LEFT JOIN man m ON BM.ManCode = m.ManCode
        LEFT JOIN medVaccineInfection MVI ON MV.MedVaccineCode = MVI.MedVaccineCode
        LEFT JOIN Enterprise E ON MVM.enterprisecodesupply = E.EnterpriseCode
        WHERE MVM.MoveType = 1 $sqlWhere
        ORDER BY 2 DESC
        LIMIT $limit OFFSET $offset
    SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $queryTotal = <<<"SQL"
            SELECT COUNT(MVM.MedVaccineMoveCode) "total"
            FROM MedVaccineMove MVM
                LEFT JOIN MedVaccineParty MVP ON MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
            WHERE MVM.MoveType = 1 $sqlWhere
    SQL;
$stmtTotal = $db->prepare($queryTotal); $stmtTotal->execute(); return (object) [ 'items' => $stmt->fetchAll(), 'total' => fetchValue($stmtTotal, 'total') ]; } public function createVaccinesReception($params) { $controlNumber = isEmptyProperty($params, 'controlNumber'); $doseSize = isEmptyProperty($params, 'doseSize'); $validTerm = dbDate(isEmptyProperty($params, 'validTerm')); $arrivalDate = dbDate(isEmptyProperty($params, 'arrivalDate')); $note = isEmptyProperty($params, 'note'); $serial = isEmptyProperty($params, 'serial'); $manufacture = isEmptyProperty($params, 'manufacture'); $vaccineCode = isEmptyProperty($params, 'vaccineCode'); $amountDose = isEmptyProperty($params, 'amountDose'); $manId = isEmptyProperty($params, 'manId'); $transporttemperature = isEmptyProperty($params, 'transportTemperature'); $enterpriseCodeSupply = isEmptyProperty($params, 'enterpriseCodeSupply'); $db = $this->db ?: $this->db = connectDb(); $sqlParty = <<<"SQL"
        INSERT INTO MedVaccineParty (ControlNumber, DoseSize, MedVaccineCode, EnterpriseCode, Manufacture, Serial, ValidTerm, TransportTemperature, BuhmolCode)
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
$sqlMove = <<<"SQL"
        INSERT INTO MedVaccineMove (Amount, MedVaccinePartyCode, MoveType, Note, OnDate, BuhmolCode, EnterpriseCodeSupply)
        VALUES(?, ?, ?, ?, ?, ?, ?)
SQL;
$db->beginTransaction(); try { if ($manId) { $buhmolId = selectSqlValue($db, "
                SELECT BuhmolCode
                FROM Buhmol
                WHERE ManCode = $manId
                "); } else { $buhmolId = null; } $stmt = $db->prepare( $sqlParty); $stmt->execute([ $controlNumber, $doseSize, $vaccineCode, $enterpriseCodeSupply, $manufacture, $serial, $validTerm, $transporttemperature, $buhmolId ]); $partyId = $db->lastInsertId('seq_MedVaccineParty'); $stmt = $db->prepare($sqlMove); $stmt->execute([ $amountDose, $partyId, 1, $note, $arrivalDate, $buhmolId, $enterpriseCodeSupply ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteVaccinesReception($params) { $moveId = isEmptyProperty($params, 'id'); $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $partyId = selectSqlValue($db, "
                SELECT MedVaccinePartyCode
                FROM MedVaccineMove
                WHERE MedVaccineMoveCode = $moveId
            "); $sqlMove = <<<"SQL"
            DELETE FROM MedVaccineMove 
            WHERE MedVaccineMoveCode = ?
            SQL;
$stmt = $db->prepare($sqlMove); $stmt->execute([$moveId]); if ($partyId) { $sqlParty = <<<"SQL"
            DELETE FROM MedVaccineParty 
            WHERE MedVaccinePartyCode = ?
            SQL;
$stmt = $db->prepare($sqlParty); $stmt->execute([$partyId]); $db->commit(); return true; } } catch (Throwable $e) { $db->rollback(); throw $e; } } public function updateVaccinesReception($params) { $manId = isEmptyProperty($params, 'manId'); $moveId = isEmptyProperty($params, 'id'); $db = $this->db ?: $this->db = connectDb(); $sqlMove = <<<"SQL"
        UPDATE MedVaccineMove SET
            Amount = CASE WHEN ? = 1 THEN ? ELSE Amount END,
            Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
            Ondate = CASE WHEN ? = 1 THEN ? ELSE Ondate END,
            BuhmolCode = CASE WHEN ? = 1 THEN ? ELSE BuhmolCode END,
            EnterpriseCodeSupply = CASE WHEN ? = 1 THEN ? ELSE EnterpriseCodeSupply END
        WHERE MedVaccineMoveCode = ?
    SQL;
$buhmolId = selectSqlValue($db, "
            SELECT BuhmolCode
            FROM Buhmol
            WHERE ManCode = ?", [$manId] ); $partyId = selectSqlValue($db, "
            SELECT MedVaccinePartyCode 
            FROM MedVaccineMove
            WHERE MedVaccineMoveCode = ?", [$moveId] ); $sqlParty = <<<"SQL"
            UPDATE MedVaccineParty SET
            ControlNumber = CASE WHEN ? = 1 THEN ? ELSE ControlNumber END,
            DoseSize = CASE WHEN ? = 1 THEN ? ELSE DoseSize END,
            ValidTerm = CASE WHEN ? = 1 THEN ? ELSE ValidTerm END,
            Manufacture = CASE WHEN ? = 1 THEN ? ELSE Manufacture END,
            Serial = CASE WHEN ? = 1 THEN ? ELSE Serial END,
            EnterpriseCode = CASE WHEN ? = 1 THEN ? ELSE EnterpriseCode END,
            MedVaccineCode = CASE WHEN ? = 1 THEN ? ELSE MedVaccineCode END,
            TransportTemperature = CASE WHEN ? = 1 THEN ? ELSE TransportTemperature END
        WHERE MedVaccinePartyCode = ?
SQL;
$stmtMove = $db->prepare($sqlMove); $stmtParty = $db->prepare($sqlParty); if (is_object($params)) { $params = [$params]; } $db->beginTransaction(); try { foreach ($params as $row) { $stmtMove->execute([ 0, null, 0, null, 0, null, 0, null, 0, null, $row->id ]); $stmtParty->execute([ 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, $partyId ]); } foreach ($params as $row) { $arrivalDate = propertyExistsSQL($row, 'arrivalDate') ? (is_string($row->arrivalDate) ? date('Y-m-d', strtotime($row->arrivalDate)) : $row->arrivalDate) : null; $validTerm = propertyExistsSQL($row, 'validTerm') ? (is_string($row->validTerm) ? date('Y-m-d', strtotime($row->validTerm)) : $row->validTerm) : null; $stmtMove->execute([ propertyExistsSQL($row, 'amountDose') ? 1 : 0, isEmptyProperty($row, 'amountDose'), propertyExistsSQL($row, 'note') ? 1 : 0, isEmptyProperty($row, 'note'), propertyExistsSQL($row, 'arrivalDate') ? 1 : 0, $arrivalDate, $buhmolId ? 1 : 0, $buhmolId, propertyExistsSQL($row, 'enterpriseCodeSupply') ? 1 : 0, isEmptyProperty($row, 'enterpriseCodeSupply'), $row->id ]); $stmtParty->execute([ propertyExistsSQL($row, 'controlNumber') ? 1 : 0, isEmptyProperty($row, 'controlNumber'), propertyExistsSQL($row, 'doseSize') ? 1 : 0, isEmptyProperty($row, 'doseSize'), propertyExistsSQL($row, 'validTerm') ? 1 : 0, $validTerm, propertyExistsSQL($row, 'manufacture') ? 1 : 0, isEmptyProperty($row, 'manufacture'), propertyExistsSQL($row, 'serial') ? 1 : 0, isEmptyProperty($row, 'serial'), propertyExistsSQL($row, 'enterpriseCodeSupply') ? 1 : 0, isEmptyProperty($row, 'enterpriseCodeSupply'), propertyExistsSQL($row, 'vaccineCode') ? 1 : 0, isEmptyProperty($row, 'vaccineCode'), propertyExistsSQL($row, 'transportTemperature') ? 1 : 0, isEmptyProperty($row, 'transportTemperature'), $partyId ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_vaccinations_vaccines_reception');