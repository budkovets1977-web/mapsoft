<?php
require_once(__DIR__ . '/../../init.php'); class med_vaccinations_log { private $db; public function readLog($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $startDate = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? $params->startDate : null; $finishDate = isset($params->finishDate) && isEmptyProperty($params, 'finishDate') ? $params->finishDate : null; $dateType = isset($params->dateType) && isEmptyProperty($params, 'dateType') ? $params->dateType : 0; $inoculateId = isset($params->inoculateId) && isEmptyProperty($params, 'inoculateId') ? $params->inoculateId : null; $inoculateGroupId = isset($params->inoculateGroupId) && isEmptyProperty($params, 'inoculateGroupId') ? $params->inoculateGroupId : null; $sector = isset($params->sectorId) && isEmptyProperty($params, 'sectorId') ? $params->sectorId : null; $patient = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sort = isEmptyProperty($params, 'sort', 'planDate'); $dir = isEmptyProperty($params, 'dir', 'desc'); $currDate = Date("Ymd"); $modal = isEmptyProperty($params, 'modal'); $mode = isEmptyProperty($params, 'mode'); $sqlFromWhere = "
FROM MedInoculateLog MIL
       JOIN MedPatient MP ON MP.MedPatientCode = MIL.MedPatientCode
       LEFT JOIN BuhMol BH ON BH.BuhMolCode = MIL.BuhMolCode
       LEFT JOIN ManPassport pass ON pass.ManCode = mp.ManCode
       LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode
        LEFT JOIN MedInoculateLog MIL2 ON MIL2.MedInoculateLogCode = (SELECT MIL2.MedInoculateLogCode
           from MedInoculateLog MIL2 WHERE MIL2.MedPatientCode=MIL.MedPatientCode
                ORDER BY MIL2.inoculateDate desc  LIMIT 1 offset 1)
       LEFT JOIN MedInoculate MI ON MI.MedInoculateCode = MIL.MedInoculateCode
       LEFT JOIN MedInoculateGroup MIG ON MI.MedInoculateGroupCode = MIG.MedInoculateGroupCode
       LEFT JOIN MedInoculate MI2 ON MI2.MedInoculateCode = MIL2.MedInoculateCode
       LEFT JOIN MedInoculateGroup MIG2 ON MI2.MedInoculateGroupCode = MIG2.MedInoculateGroupCode
       LEFT JOIN MedVaccineMove MVM ON MVM.MedInoculateLogCode = MIL.MedInoculateLogCode
       LEFT JOIN MedVaccineParty MVP ON MVP.MedVaccinePartyCode = MVM.MedVaccinePartyCode
       LEFT JOIN MedVaccine MV ON MV.MedVaccineCode = MVP.MedVaccineCode
       LEFT JOIN uniImportLink uil ON (uil.PrimaryKey = MV.MedVaccineCode) AND (uil.Program = 18) AND (uil.DataType = 1)        -- MAP2545
       LEFT JOIN CiszResourceValue CRIS ON CRIS.CiszResourceValueCode = MIL.CiszResourceValueCodeInjectionSite
       LEFT JOIN CiszResourceValue CRIM ON CRIM.CiszResourceValueCode = MIL.CiszResourceValueCodeInjectionMethod
       LEFT JOIN CiszResourceValue CRP ON CRP.CiszResourceValueCode = MIL.CiszResourceValueCodePaid
       LEFT JOIN CiszResourceValue CRVS ON CRVS.CiszResourceValueCode = MIL.CiszResourceValueCodeStatus
       LEFT JOIN EnterpriseStructureInfo ESIP ON ESIP.EnterpriseStructureInfoCode = MIL.DepartmentCode
       LEFT JOIN LATERAL (
            SELECT json_agg(
                json_build_object(
                    'observationOnjectiveId', MILI.MedInoculateLogInspectionCode,
                    'observationOnjectiveType', MILI.Type,
                    'observationOnjectiveString', MILI.Description
                )
            ) AS ObservationsObjective
            FROM MedInoculateLogInspection MILI
            WHERE MILI.MedInoculateLogCode = MIL.MedInoculateLogCode
       ) OS ON TRUE
       LEFT JOIN LATERAL (
            SELECT json_agg(
                json_build_object(
                    'vitalSignId', MILVS.MedInoculateLogVitalSignsCode,
                    'vitalSignType', MILVS.Type,
                    'vitalSignNumber', MILVS.ValueNumber
                )
            ) AS VitalSigns
            FROM MedInoculateLogVitalSigns MILVS
            WHERE MILVS.MedInoculateLogCode = MIL.MedInoculateLogCode
        ) VS ON TRUE
where 1 = 1
    /* хоть один участок доступен пользователю */
    and (mp.MedPatientGroupCode in (select gc from pg_)
        or (
            exists (select 1 from MedPatientAddGroup pga
                where pga.MedPatientCode = mp.MedPatientCode
                    and pga.MedPatientGroupCode in (select gc from pg_))
        )
    )"; if ($id && !is_numeric($id)) { $sqlMax = "
SELECT Max(MedInoculateLogCode)
FROM MedInoculateLog"; $stmtMax = $db->prepare($sqlMax); $stmtMax->execute(); $id = fetchValue($stmtMax, 'max'); } if (isset($id)) { $sqlFromWhere .= " and MIL.MedInoculateLogCode =  $id"; } switch ($dateType) { case 0: { if (isset($startDate)) { $sqlFromWhere .= " and MIL.planDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MIL.planDate <= $finishDate"; } break; } case 1: { if (isset($startDate)) { $sqlFromWhere .= " and MIL.inoculateDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MIL.inoculateDate <= $finishDate"; } break; } } if (isset($inoculateId)) { $sqlFromWhere .= " and MI.MedInoculateCode = $inoculateId"; } if (isset($inoculateGroupId)) { $sqlFromWhere .= " and MIG.MedInoculateGroupCode = $inoculateGroupId"; } if (isset($sector)) { $sqlFromWhere .= " and MPG.MedPatientGroupCode = $sector"; } if (isset($patient)) { $sqlFromWhere .= " and MIL.MedPatientCode = $patient"; } if (isset($mode) && $mode == "covid19"){ $sqlFromWhere .= " AND uil.program = 18 AND uil.dataType = 1"; } $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
select DISTINCT
       MIL.MedInoculateLogCode "id"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = count($stmt->fetchAll()); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
SELECT
       MVP. MedVaccinePartyCode "vaccinePartyId",
       MIL.MedInoculateLogCode "id",
       MIL.PlanDate "planDate",
       FN_cmCalcAddress(MP.ManCode, 0)::text "address",
       FN_cmCalcAddress(MP.ManCode, 1)::text "addressReside",
       MIL.MedInoculateCode "medInoculateCode",
       MIL.MedPatientCode "medPatientCode",
       FN_cmFIO(MP.ManCode)::text "patientFIO",
       MP.ManCode "manId",
       MVP.validTerm "expireDate",
       MIL.RuleDate "ruleDate",
       MI.Name "inoculateName",
       MIG.Name "inoculateGroupName",
       MIG.MedInoculateGroupCode "medInoculateGroupCode",
       MIL.InoculateDate "inoculateDate",
       MIL.InputPlace "inputPlace",
       MIL.InoculateReason "inoculateReason",
       MIL.ReactionResult "reactionResult",
       MIL.TestResult "testResult",
       pass.DateOfBirth as "dateOfBirth",
       MIL.FailReason "failReason",
       MIL.TestTypeResult "testTypeResult",
       MIL.TubVaccineResult "tubVaccineResult",
       MIL.ValueResult "valueResult",
       MIL.BuhMolCode "buhMolCode",
       MIL2.InoculateDate "prev_InoculateDate",
       MI2.Name "prev_InoculateName",
       MIG2.Name "prev_InoculateGroupName",
       MPG.Name "sector",
       MV.Name "vaccineName",
       MV.MedVaccineCode "vaccineId",
       MVM.Amount "amount",
       MVM.MedVaccineMoveCode "medVaccineMoveCode",
       CASE WHEN MVM.Alien = 1 THEN 0 ELSE 1 END "alien",
       MVP.DoseSize "doseSize",
       mv.doseMeasure "doseMeasure",
       MVP.Manufacture "manufacture",
       MVP.ControlNumber "controlNumber",
       MVP.Serial "serial",
       MIL.InoculateDateIndividual "dateIndividual",
       MIL.InputPlaceSide "inputPlaceSide",
       MIL.Note "note",
       MIL.Phone "phone",
       MIL.PhoneRefusal "phoneRefusal",
       MVP.MedVaccinePartyCode "medVaccinePartyCode",
       (CASE (DATEDIFFYEAR(pass.dateOfBirth, $currDate)) > 0
       WHEN true THEN CAST(FN_cmGetMonthYearString((DATEDIFFYEAR(pass.dateOfBirth, $currDate))) as VARCHAR(250))
       WHEN false THEN CASE (DATEDIFFMONTH(pass.dateOfBirth, $currDate)) > 0
                          WHEN true then cast(FN_cmGetMonthYearString(((DATEDIFFMONTH(pass.dateOfBirth, $currDate)) / 100.00), 2) as VARCHAR(250))
                          WHEN false THEN CASE (DATEDIFF('day', pass.dateOfBirth, $currDate)) > 0
                                               WHEN true THEN CAST(FN_cmGetDayString((DATEDIFF('day', pass.dateOfBirth, $currDate))) as VARCHAR(250))
                                          END
                     END
       END) "patientAge",
       uil.note "vaccineData",   -- MAP2545
       MIL.DepartmentCode "departmentId",
       ESIP.FullName "department",
       MIL.CiszResourceValueCodeInjectionSite "ciszInjectionSiteId",
       CRIS.Name "ciszInjectionSite",
       MIL.CiszResourceValueCodeInjectionMethod "ciszInjectionMethodId",
       CRIM.Name "ciszInjectionMethod",
       CRP.Code::bool "ciszPaidId",
       CRP.Name "ciszPaid",
       MIL.reaction30min "reaction30min",
       MIL.RecommendedDoseCount "recomendedDose",
       COALESCE(OS.ObservationsObjective, '[]') "objectiveExaminations",
       COALESCE(VS.VitalSigns, '[]') "vitalSigns",
       MIL.CiszResourceValueCodeStatus "recomendationStatusId",
       CRVS.Name "recomendationStatus",
       MIL.Description "recNote",
       MIL.RuleDateFinish  "ruleDateFinish"
$sqlFromWhere
order by "$sort" $dir
limit $limit offset $offset
EOT;
$mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->enterpriseId = $mapEnterpriseCode; $root->items = $stmt->fetchAll(); return $root; } public function syncLog($records) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $codes = selectSql($db, <<<SQL
        SELECT
            CRV.Code,
            CRV.CiszResourceValueCode
        FROM CiszResourceValue CRV
        JOIN CiszResource CR ON CRV.CiszResourceCode = CR.CiszResourceCode AND CR.Identifier = 'AISReportFundingSource'
SQL)
; $paidCodes = []; foreach ($codes as $item) { $paidCodes[$item['code']] = $item['ciszresourcevaluecode']; } $sql = <<<"EOT"
insert into MedInoculateLog (MedInoculateLogCode, PlanDate, MedInoculateCode, MedPatientCode, RuleDate, InoculateDate,
    InoculateReason, ReactionResult, TestResult, FailReason,
    TestTypeResult, TubVaccineResult, ValueResult, BuhMolCode, InoculateDateIndividual, InputPlaceSide, Note, Phone, PhoneRefusal, DepartmentCode,
    CiszResourceValueCodeInjectionSite, CiszResourceValueCodeInjectionMethod, CiszResourceValueCodePaid, reaction30min, RecommendedDoseCount, CiszResourceValueCodeStatus,
    Description, RuleDateFinish)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
on conflict (MedInoculateLogCode) do update set
    PlanDate = case when ? = 1 then EXCLUDED.PlanDate else MedInoculateLog.PlanDate end,
    MedInoculateCode = case when ? = 1 then EXCLUDED.MedInoculateCode else MedInoculateLog.MedInoculateCode end,
    MedPatientCode = case when ? = 1 then EXCLUDED.MedPatientCode else MedInoculateLog.MedPatientCode end,
    RuleDate = case when ? = 1 then EXCLUDED.RuleDate else MedInoculateLog.RuleDate end,
    InoculateDate = case when ? = 1 then EXCLUDED.InoculateDate else MedInoculateLog.InoculateDate end,
    InoculateReason = case when ? = 1 then EXCLUDED.InoculateReason else MedInoculateLog.InoculateReason end,
    ReactionResult = case when ? = 1 then EXCLUDED.ReactionResult else MedInoculateLog.ReactionResult end,
    TestResult = case when ? = 1 then EXCLUDED.TestResult else MedInoculateLog.TestResult end,
    FailReason = case when ? = 1 then EXCLUDED.FailReason else MedInoculateLog.FailReason end,
    TestTypeResult = case when ? = 1 then EXCLUDED.TestTypeResult else MedInoculateLog.TestTypeResult end,
    TubVaccineResult = case when ? = 1 then EXCLUDED.TubVaccineResult else MedInoculateLog.TubVaccineResult end,
    ValueResult = case when ? = 1 then EXCLUDED.ValueResult else MedInoculateLog.ValueResult end,
    BuhMolCode = case when ? = 1 then EXCLUDED.BuhMolCode else MedInoculateLog.BuhMolCode end,
    InoculateDateIndividual = case when ? = 1 then EXCLUDED.InoculateDateIndividual else MedInoculateLog.InoculateDateIndividual end,
    InputPlaceSide = case when ? = 1 then EXCLUDED.InputPlaceSide else MedInoculateLog.InputPlaceSide end,
    Note = case when ? = 1 then EXCLUDED.Note else MedInoculateLog.Note end,
    Phone = case when ? = 1 then EXCLUDED.Phone else MedInoculateLog.Phone end,
    PhoneRefusal = case when ? = 1 then EXCLUDED.PhoneRefusal else MedInoculateLog.PhoneRefusal end,
    DepartmentCode = case when ? = 1 then EXCLUDED.DepartmentCode else MedInoculateLog.DepartmentCode end,
    CiszResourceValueCodeInjectionSite = case when ? = 1 then EXCLUDED.CiszResourceValueCodeInjectionSite else MedInoculateLog.CiszResourceValueCodeInjectionSite end,
    CiszResourceValueCodeInjectionMethod = case when ? = 1 then EXCLUDED.CiszResourceValueCodeInjectionMethod else MedInoculateLog.CiszResourceValueCodeInjectionMethod end,
    CiszResourceValueCodePaid = case when ? = 1 then EXCLUDED.CiszResourceValueCodePaid else MedInoculateLog.CiszResourceValueCodePaid end,
    reaction30min = case when ? = 1 then EXCLUDED.reaction30min else MedInoculateLog.reaction30min end,
    RecommendedDoseCount = case when ? = 1 then EXCLUDED.RecommendedDoseCount else MedInoculateLog.RecommendedDoseCount end,
    CiszResourceValueCodeStatus = case when ? = 1 then EXCLUDED.CiszResourceValueCodeStatus else MedInoculateLog.CiszResourceValueCodeStatus end,
    Description = case when ? = 1 then EXCLUDED.Description else MedInoculateLog.Description end,
    RuleDateFinish = case when ? = 1 then EXCLUDED.RuleDateFinish else MedInoculateLog.RuleDateFinish end
RETURNING MedInoculateLogCode AS id
EOT;
$stmt = $db->prepare($sql); $insertVitalSignSql = <<<SQL
            INSERT INTO MedInoculateLogVitalSigns (MedInoculateLogCode, Type, ValueNumber)
                VALUES (?, ?, ?)
        SQL;
$stmtVitalSign = $db->prepare($insertVitalSignSql); $insertInspectionSql = <<<SQL
            INSERT INTO MedInoculateLogInspection (MedInoculateLogCode, Type, Description)
                VALUES (?, ?, ?)
        SQL;
$stmtInspectionSql = $db->prepare($insertInspectionSql); $insertContactSql = <<<SQL
            INSERT INTO ManPhone (manCode, phoneNumber, phoneType)
                VALUES (?, ?, 3) ON CONFLICT DO NOTHING;
        SQL;
$stmtContact = $db->prepare($insertContactSql); $sqlParty = <<<"EOT"
insert into MedVaccineParty(MedVaccineCode,Serial,ControlNumber,Manufacture,DoseSize,EnterpriseCode,ValidTerm)
values (?, ?, ?, ?, ?, ?, ?)
        RETURNING MedVaccinePartyCode AS id
EOT;
$stmtParty = $db->prepare($sqlParty); $sqlPartyUpdate = <<<"EOT"
UPDATE MedVaccineParty SET
    MedVaccineCode = CASE WHEN ? = 1 THEN ? ELSE MedVaccineCode END,
    Serial = CASE WHEN ? = 1 THEN ? ELSE Serial END,
    ControlNumber = CASE WHEN ? = 1 THEN ? ELSE ControlNumber END,
    Manufacture = CASE WHEN ? = 1 THEN ? ELSE Manufacture END,
    DoseSize = CASE WHEN ? = 1 THEN ? ELSE DoseSize END,
    ValidTerm = CASE WHEN ? = 1 THEN ? ELSE ValidTerm END
WHERE MedVaccinePartyCode = ?;
EOT;
$stmtPartyUpdate = $db->prepare($sqlPartyUpdate); $sqlMoveSelect = <<<"SQL"
    SELECT MedVaccineMoveCode, Alien
    FROM MedVaccineMove
    WHERE MedInoculateLogCode = ? AND MoveType = 4
    LIMIT 1
SQL;
$stmtMoveSelect = $db->prepare($sqlMoveSelect); $sqlMove = <<<"SQL"
            INSERT INTO MedVaccineMove (MoveType, MedVaccinePartyCode, Amount, MedInoculateLogCode, Alien, OnDate)
                VALUES (4, ?, ?, ?, ?, ?)
        SQL;
$stmtMove = $db->prepare($sqlMove); $sqlMoveUpdate = <<<"SQL"

    UPDATE MedVaccineMove SET
        MedVaccinePartyCode = CASE WHEN ? = 1 THEN ? ELSE MedVaccinePartyCode END,
        Amount = CASE WHEN ? = 1 THEN ? ELSE Amount END,
        Alien = CASE WHEN ? = 1 THEN ? ELSE Alien END,
        OnDate = CASE WHEN ? = 1 THEN ? ELSE OnDate END
    WHERE MedVaccineMoveCode = ?
SQL;
$stmtMoveUpdate = $db->prepare($sqlMoveUpdate); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret = new stdClass; $rec = new stdClass; $logId = null; if (is_numeric($row->id) && $row->id > 0) { $logId = $row->id; } if (property_exists($row, 'ciszPaidId')) { $code = filter_var($row->ciszPaidId, FILTER_VALIDATE_BOOLEAN) ? 'True' : 'False'; $row->ciszPaidId = $paidCodes[$code] ?? null; } $stmt->execute([ $logId, isUndefinedProperty($row, 'planDate'), isEmptyProperty($row, 'medInoculateCode'), isEmptyProperty($row, 'medPatientCode'), isUndefinedProperty($row, 'ruleDate'), isUndefinedProperty($row, 'inoculateDate'), isUndefinedProperty($row, 'inoculateReason'), isUndefinedProperty($row, 'reactionResult'), isUndefinedProperty($row, 'testResult'), isUndefinedProperty($row, 'failReason'), isUndefinedProperty($row, 'testTypeResult'), isUndefinedProperty($row, 'tubVaccineResult'), isUndefinedProperty($row, 'valueResult'), isEmptyProperty($row, 'buhMolCode'), isUndefinedProperty($row, 'dateIndividual'), isUndefinedProperty($row, 'inputPlaceSide'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'phone'), isUndefinedProperty($row, 'phoneRefusal'), isEmptyProperty($row, 'departmentId'), isEmptyProperty($row, 'ciszInjectionSiteId'), isEmptyProperty($row, 'ciszInjectionMethodId'), isEmptyProperty($row, 'ciszPaidId'), isEmptyProperty($row, 'reaction30min'), isEmptyProperty($row, 'recomendedDose'), isEmptyProperty($row, 'recomendationStatusId'), isUndefinedProperty($row, 'recNote'), isUndefinedProperty($row, 'ruleDateFinish'), propertyExistsSQL($row, 'planDate'), propertyExistsSQL($row, 'medInoculateCode'), propertyExistsSQL($row, 'medPatientCode'), propertyExistsSQL($row, 'ruleDate'), propertyExistsSQL($row, 'inoculateDate'), propertyExistsSQL($row, 'inoculateReason'), propertyExistsSQL($row, 'reactionResult'), propertyExistsSQL($row, 'testResult'), propertyExistsSQL($row, 'failReason'), propertyExistsSQL($row, 'testTypeResult'), propertyExistsSQL($row, 'tubVaccineResult'), propertyExistsSQL($row, 'valueResult'), propertyExistsSQL($row, 'buhMolCode'), propertyExistsSQL($row, 'dateIndividual'), propertyExistsSQL($row, 'inputPlaceSide'), propertyExistsSQL($row, 'note'), propertyExistsSQL($row, 'phone'), propertyExistsSQL($row, 'phoneRefusal'), propertyExistsSQL($row, 'departmentId'), propertyExistsSQL($row, 'ciszInjectionSiteId'), propertyExistsSQL($row, 'ciszInjectionMethodId'), propertyExistsSQL($row, 'ciszPaidId'), propertyExistsSQL($row, 'reaction30min'), propertyExistsSQL($row, 'recomendedDose'), propertyExistsSQL($row, 'recomendationStatusId'), propertyExistsSQL($row, 'recNote'), propertyExistsSQL($row, 'ruleDateFinish') ]); $rec->id = fetchValue($stmt, 'id'); $vitalSigns = $row->vitalSigns ?? []; if ($vitalSigns && is_string($vitalSigns)) { $vitalSigns = json_decode($vitalSigns, true) ?? []; } $vsUpdateSql = "UPDATE MedInoculateLogVitalSigns
                        SET ValueNumber = :value
                        WHERE MedInoculateLogVitalSignsCode = :vsId"; $vsInsertSql = "INSERT INTO MedInoculateLogVitalSigns
                        (MedInoculateLogCode, Type, ValueNumber)
                        VALUES (:id, :type, :value)"; $vsUpdateStmt = $db->prepare($vsUpdateSql); $vsInsertStmt = $db->prepare($vsInsertSql); foreach ($vitalSigns as $v) { if (!empty($v['vitalSignId'])) { $vsUpdateStmt->execute([ ':value' => $v['vitalSignNumber'], ':vsId' => $v['vitalSignId'] ]); } else if (!empty($v['vitalSignNumber'])) { $vsInsertStmt->execute([ ':id' => $rec->id, ':type' => $v['vitalSignType'], ':value' => $v['vitalSignNumber'] ]); } } $objectiveExaminations = $row->objectiveExaminations ?? []; if ($objectiveExaminations && is_string($objectiveExaminations)) { $objectiveExaminations = json_decode($objectiveExaminations, true) ?? []; } $osUpdateSql = "UPDATE MedInoculateLogInspection
                        SET Description = :value
                        WHERE MedInoculateLogInspectionCode = :vsId"; $osInsertSql = "INSERT INTO MedInoculateLogInspection
                        (MedInoculateLogCode, Type, Description)
                        VALUES (:id, :type, :value)"; $osUpdateStmt = $db->prepare($osUpdateSql); $osInsertStmt = $db->prepare($osInsertSql); foreach ($objectiveExaminations as $o) { if (!empty($o['observationOnjectiveId'])) { $osUpdateStmt->execute([ ':value' => $o['observationOnjectiveString'], ':vsId' => $o['observationOnjectiveId'] ]); } else if (!empty($o['observationOnjectiveString'])) { $osInsertStmt->execute([ ':id' => $rec->id, ':type' => $o['observationOnjectiveType'], ':value' => $o['observationOnjectiveString'] ]); } } if (isEmptyProperty($row, 'phone')) { $stmtContact->execute([ $row->manId, $row->phone ]); } $partyId = isUndefinedProperty($row, 'medVaccinePartyCode'); $moveRows = selectSql($db, $sqlMoveSelect, [ $rec->id ]); $moveRow = $moveRows ? (object)$moveRows[0] : null; $moveId = $moveRow->medvaccinemovecode ?? null; $prevAlien = $moveRow->alien ?? null; if (!$row->alien) { $selectFromMedVaccineParty = null; $existedRecordOnInoculateDate = null; if ($partyId) { $selectFromMedVaccinePartySql = <<<SQL
                            SELECT
                                MVP.ValidTerm "expireDate",
                                MVP.DoseSize "doseSize",
                                MVP.Manufacture "manufacture",
                                MVP.ControlNumber "controlNumber",
                                MVP.Serial "serial",
                                MVP.MedVaccineCode "vaccineId"
                            FROM MedVaccineParty MVP
                            WHERE MVP.MedVaccinePartyCode = ?
                            LIMIT 1
                            SQL;
$selectFromMedVaccineParty = selectSql($db, $selectFromMedVaccinePartySql, [ $partyId ]); $selectFromMedVaccineParty = $selectFromMedVaccineParty ? (object)$selectFromMedVaccineParty[0] : null; } else { $selectExistedRecordSql = <<<SQL
                        SELECT 1
                        FROM MedVaccineParty MVP
                        LEFT JOIN MedVaccineMove MVM ON MVM.MedVaccinePartyCode = MVP.MedVaccinePartyCode
                        LEFT JOIN MedInoculateLog MIL ON MIL.MedInoculateLogCode = MVM.MedInoculateLogCode
                        WHERE MIL.InoculateDate = ?
                            AND MIL.MedPatientCode = ?
                            AND MVP.ValidTerm = ?
                            AND MVP.DoseSize = ?
                            AND MVP.Manufacture = ?
                            AND MVP.ControlNumber = ?
                            AND MVP.Serial = ?
                            AND MVP.MedVaccineCode = ?
                        LIMIT 1
                        SQL;
$existedRecordOnInoculateDate = selectSql($db, $selectExistedRecordSql, [ isEmptyProperty($row, 'inoculateDate'), isEmptyProperty($row, 'medPatientCode'), isEmptyProperty($row, 'expireDate'), isEmptyProperty($row, 'doseSize'), isEmptyProperty($row, 'manufacture'), isEmptyProperty($row, 'controlNumber'), isEmptyProperty($row, 'serial'), isEmptyProperty($row, 'vaccineId'), ]); } $uniqMedVaccinePartyCodeSql = <<<SQL
                        SELECT MedVaccinePartyCode
                        FROM MedVaccineParty
                        WHERE EnterpriseCode = ?
                        AND ValidTerm = ?
                        AND COALESCE(DoseSize, '') = ?
                        AND COALESCE(Manufacture, '') = ?
                        AND COALESCE(ControlNumber, '') = ?
                        AND COALESCE(Serial, '') = ?
                        AND COALESCE(MedVaccineCode, '') = ?
                        LIMIT 1
                        SQL;
$params = [ $mapEnterpriseCode, isEmptyProperty($row, 'expireDate', isEmptyProperty($selectFromMedVaccineParty, 'expireDate', null)), isEmptyProperty($row, 'doseSize', isEmptyProperty($selectFromMedVaccineParty, 'doseSize', '')), isEmptyProperty($row, 'manufacture', isEmptyProperty($selectFromMedVaccineParty, 'manufacture', '')), isEmptyProperty($row, 'controlNumber', isEmptyProperty($selectFromMedVaccineParty, 'controlNumber', '')), isEmptyProperty($row, 'serial', isEmptyProperty($selectFromMedVaccineParty, 'serial', '')), isEmptyProperty($row, 'vaccineId', isEmptyProperty($selectFromMedVaccineParty, 'vaccineId', null)) ]; $uniqMedVaccinePartyCode = selectSqlValue($db, $uniqMedVaccinePartyCodeSql, $params); $existedPartyId = $uniqMedVaccinePartyCode ?: null; if ($existedPartyId && !$existedRecordOnInoculateDate) { $partyId = $existedPartyId; } else { if ($partyId && $prevAlien) { $stmtPartyUpdate->execute([ propertyExistsSQL($row, 'vaccineId'), isEmptyProperty($row, 'vaccineId'), propertyExistsSQL($row, 'serial'), isEmptyProperty($row, 'serial'), propertyExistsSQL($row, 'controlNumber'), isEmptyProperty($row, 'controlNumber'), propertyExistsSQL($row, 'manufacture'), isEmptyProperty($row, 'manufacture'), propertyExistsSQL($row, 'doseSize'), isEmptyProperty($row, 'doseSize'), propertyExistsSQL($row, 'expireDate'), isEmptyProperty($row, 'expireDate'), $partyId ]); } else { $stmtParty->execute([ isEmptyProperty($row, 'vaccineId'), isEmptyProperty($row, 'serial'), isEmptyProperty($row, 'controlNumber'), isEmptyProperty($row, 'manufacture'), isEmptyProperty($row, 'doseSize'), $mapEnterpriseCode, isEmptyProperty($row, 'expireDate') ]); $partyId = fetchValue($stmtParty, 'id'); } } } if ($partyId) { if ($moveId) { $stmtMoveUpdate->execute([ propertyExistsSQL($row, 'medVaccinePartyCode'), $partyId, propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'alien'), !$row->alien, propertyExistsSQL($row, 'inoculateDate'), isUndefinedProperty($row, 'inoculateDate'), $moveId ]); } else { $stmtMove->execute([ $partyId, isUndefinedProperty($row, 'amount'), $rec->id, !$row->alien, isUndefinedProperty($row, 'inoculateDate') ]); } } } if (isset($row->id) && is_numeric($row->id)) { $ciszLinkUuid = selectSql($db, <<<SQL
                    SELECT
                        Cl.Uuid "uuid"
                    FROM CiszLink CL
                    WHERE CL.PrimaryKey = :id AND CL.TableName = :tableName
                SQL,
[ ':id' => $row->id, ':tableName' => 'MedVaccination' ]); if ($ciszLinkUuid) { executeSql($db, <<<SQL
                        UPDATE CiszLink
                            SET Status = 2
                        WHERE TableName = 'MedVaccination'
                        AND PrimaryKey = :id
                    SQL,
[ ':id' => $row->id ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $ret = $this->readLog($rec); return $ret; } public function readLogInoculateType($params) { $db = $this->db ?: $this->db = connectDb(); $tree = isset($params->tree) && isEmptyProperty($params, 'tree') ? $params->tree : null; $includingInfectionType = isset($params->includingInfectionType) && isEmptyProperty($params, 'includingInfectionType') ? $params->includingInfectionType : null; if ($includingInfectionType) { $sqlInfectionTypeColumns = ', MIF.InfectionType "infectionType"'; $sqlInfectionTypePadding = ', null "infectionType"'; $sqlInfectionTypeJoin = <<<"EOT"
     LEFT JOIN MedInoculateInfection MII ON MII.MedInoculateCode = MI.MedInoculateCode
     LEFT JOIN MedInfection MIF ON MII.MedInfectionCode = MIF.MedInfectionCode
EOT;
} else { $sqlInfectionTypeColumns = ''; $sqlInfectionTypePadding = ''; $sqlInfectionTypeJoin = ''; } if ($tree) { $sqlTree = <<<"EOT"
UNION
SELECT
      MIG.MedInoculateGroupCode "inoculateGroupCode",
      MIG.Name "inoculateGroupName",
      null "inoculateCode",
      null "inoculateName"
      $sqlInfectionTypePadding
FROM MedInoculateGroup MIG
EOT;
} else { $sqlTree = <<<"EOT"
EOT;
} $sqlOrder = <<<"EOT"
ORDER BY "inoculateGroupName", "inoculateName"
EOT;
$sql = <<<"EOT"
SELECT
      MIG.MedInoculateGroupCode "inoculateGroupCode",
      MIG.Name "inoculateGroupName",
      MI.MedInoculateCode "inoculateCode",
      MI.Name "inoculateName"
      $sqlInfectionTypeColumns
FROM MedInoculate MI
     LEFT JOIN MedInoculateGroup MIG ON MI.MedInoculateGroupCode = MIG.MedInoculateGroupCode
     $sqlInfectionTypeJoin
$sqlTree
$sqlOrder
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->items = $stmt->fetchAll(); return $root; } public function getPatientData($params) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->patientCode) && isEmptyProperty($params, 'patientCode') ? $params->patientCode : null; $currDate = Date("Ymd"); $sql = <<<"EOT"
SELECT
      MP.ManCode "manId",
      FN_cmCalcAddress(MP.ManCode, 5)::text "addressDisplay",
      mpg.Name "sector",
      dateformat(pass.DateOfBirth,  E'dd.mm.yyyy' ) "dateOfBirth",
      (SELECT PhoneNumber FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) "mobilePhone",
      (SELECT string_agg(UD.Value, ', ')
         FROM UniDict AS UD
         LEFT JOIN MedPatientBusy as MB ON MB.UniDictCodeProfession = UD.uniDictCode
        WHERE MB.MedPatientCode = MP.MedPatientCode
        AND $currDate BETWEEN MB.StartDate AND MB.FinishDate) as "job"
FROM MedPatient MP
     LEFT JOIN ManPassport AS pass ON pass.ManCode = MP.ManCode
     LEFT JOIN MedPatientGroup AS mpg ON mpg.MedPatientGroupCode = MP.MedPatientGroupCode
                WHERE MP.MedPatientCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->items = $stmt->fetchAll(); return $root; } public function getPatientCount($params) { $db = $this->db ?: $this->db = connectDb(); $enterprise = isset($params->enterpriseId) && isEmptyProperty($params, 'enterpriseId') ? $params->enterpriseId : null; $sql = <<<"EOT"
SELECT COUNT(*)
FROM MedInoculateLog
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = fetchValue($stmt, 'count'); return $root; } public function isProbe($params) { $db = $this->db ?: $this->db = connectDb(); $type = isset($params->type) && isEmptyProperty($params, 'type') ? $params->type : null; $inoculateId = isset($params->inoculateId) && isEmptyProperty($params, 'inoculateId') ? $params->inoculateId : null; $sql = <<<"EOT"
select CASE exists(select * from MedInoculateInfection AS mii join MedInfection AS mi on mii.MedInfectionCode = mi.MedInfectionCode
    and mi.InfectionType = $type
    where mii.MedInoculateCode = $inoculateId) WHEN true then 1 WHEN false THEN 0 END as probe;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = fetchValue($stmt, 'probe'); return (bool) $root; } public function getMedContr($params) { $db = $this->db ?: $this->db = connectDb(); $patient = isset($params->patient) && isEmptyProperty($params, 'patient') ? $params->patient : null; $inoculateGroup = isset($params->inoculateGroup) && isEmptyProperty($params, 'inoculateGroup') ? $params->inoculateGroup : null; $inoculate = isset($params->inoculate) && isEmptyProperty($params, 'inoculate') ? $params->inoculate : null; $sql = <<<"EOT"
SELECT
    PC.StartDate "startDate",
    PC.FinishDate "finishDate",
    PC.Note "note",
    IC.Name "contrName",
    MD.Cipher "cipher",
    string_agg('по заболеванию ' || MD.Cipher1 || ' ' || MD.Name || ' c '|| dateformat(PC.StartDate,  E'dd.mm.yyyy' )  ||  case when PC.FinishDate is null then '' else ' по ' end  || dateformat(PC.FinishDate,  E'dd.mm.yyyy' )   || '\n' , ', ') "display",
    MD.Name "diagnosisName"
FROM MedPatientContr PC
                LEFT JOIN MedInoculate I ON I.MedInoculateCode = $inoculate
                LEFT JOIN MedInoculateContr IC  ON IC.MedInoculateContrCode = PC.MedInoculateContrCode
                LEFT JOIN MedDiagnosis MD  ON MD.MedDiagnosisCode = PC.MedDiagnosisCode
                WHERE PC.MedPatientCode = $patient and ($inoculateGroup = 0 or PC.MedInoculateGroupCode=$inoculateGroup)
                and PC.MedInoculateGroupCode = Coalesce( I.MedInoculateGroupCode, PC.MedInoculateGroupCode )
                GROUP BY PC.startDate,PC.FinishDate, PC.Note, IC.Name, MD.Cipher, MD.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->items = $stmt->fetchAll(); $items = $root->items; $ret = " "; foreach ($items as $value) { $ret .= $value['display']; } return $ret; } public function planDelete($params) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $startDate = isset($params->startDate) && isEmptyProperty($params, 'startDate') ? $params->startDate : null; $finishDate = isset($params->finishDate) && isEmptyProperty($params, 'finishDate') ? $params->finishDate : null; $inoculateId = isset($params->inoculateId) && isEmptyProperty($params, 'inoculateId') ? $params->inoculateId : 0; $inoculateGroupId = isset($params->inoculateGroupId) && isEmptyProperty($params, 'inoculateGroupId') ? $params->inoculateGroupId : 0; $sector = isset($params->sectorId) && isEmptyProperty($params, 'sectorId') ? $params->sectorId : 0; $patient = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : 0; $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sqlFromWhere = "WHERE  l1.MedInoculateCode = l.MedInoculateCode AND (coalesce(l1.InoculateDate, NULL) is NULL) AND (l1.failreason IS NULL OR l1.failreason = 0) "; if (isset($startDate)) { $sqlFromWhere .= "AND l1.PlanDate >= $startDate "; } if (isset($finishDate)) { $sqlFromWhere .= "AND l1.PlanDate <= $finishDate "; } $sqlFromWhere .= "AND (mi.MedInoculateGroupCode = $inoculateGroupId  OR $inoculateGroupId = 0) "; $sqlFromWhere .= "AND (l.MedInoculateCode = $inoculateId   OR $inoculateId = 0) "; $sqlFromWhere .= "AND (l1.MedPatientCode = $patient OR $patient = 0) "; $sqlFromWhere .= "AND (p.MedPatientGroupCode = $sector  OR $sector = 0) "; $sqlFromWhere .= "AND p.EnterpriseCode = $mapEnterpriseCode"; $sql = <<<"EOT"
    DELETE FROM MedInoculateLog AS l1
USING
  MedInoculateLog AS l
JOIN MedPatient AS p ON p.MedPatientCode = l.MedPatientCode
LEFT OUTER JOIN MedInoculate AS mi ON mi.MedInoculateCode = l.MedInoculateCode
LEFT OUTER JOIN MedVaccineMove AS mvm ON mvm.MedInoculateLogCode = l.MedInoculateLogCode
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteLog($records) { return deleteSql($this->db, 'MedInoculateLog', 'MedInoculateLogCode', $records); } public function readDocuments($params) { $id = isUndefinedProperty($params, 'logId'); $db = $this->db ?: $this->db = connectDb(); $sqlDocuments = <<<"EOT"
            SELECT
               MEFT.MedExternalFileCode         as "id",
               MEFT.FileName                    as "documentName",
               MEFT.Source                      as "source",
               MEFT.SourceCode                  as "logId"
            FROM MedExternalFileT MEFT
            WHERE MEFT.Source = 130 AND MEFT.SourceCode = ?
        EOT;
$stmtDocuments = $db->prepare($sqlDocuments); $stmtDocuments->execute([$id]); $documents = $stmtDocuments->fetchAll(); return $documents; } } handleJsonRpc('med_vaccinations_log');