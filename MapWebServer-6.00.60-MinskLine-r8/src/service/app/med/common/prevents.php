<?php
require_once(__DIR__.'/../../init.php'); class med_common_prevents { private $db; public function readPreventTypes($params = null) { $db = $this->db ?: $this->db = connectDb(); $onlyDispensary = isEmptyProperty($params, 'onlyDispensary'); $where = ''; if ($onlyDispensary) { $where .= ' AND mp.inputForm IN (2, 3, 9, 10)'; } $onlyExaminations = isEmptyProperty($params, 'onlyExaminations'); $where = ''; if ($onlyExaminations) { $where .= ' AND mp.inputForm IN (1)'; } $sqlParams = []; $id = isEmptyProperty($params, 'id'); if ($id) { $where .= ' AND mp.medPreventTypeCode = :id'; $sqlParams[':id'] = $id; } $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT 
                MP.MedPreventTypeCode                               "id",
                MP.Name                                             "name",
                MP.InputForm                                        "inputFormId",
                MP.FormFields                                       "formFields",
                MP.DisplayInInfobox                                 "displayInInfobox",
                STRING_AGG(MPTC.MedCabinetCode, ',')                "cabinets",
                STRING_AGG(distinct MC.Cabinet, ',')                "cabinetsNames",
                (MP.CiszMark = 1)                                   "isCisz"
                --
                /*
                CASE mp.InputForm
                    WHEN 1 THEN 'Обследования'
                    WHEN 2 THEN 'Диспансерные осмотры взрослых'
                    WHEN 3 THEN 'Диспансерные осмотры детей'
                    WHEN 4 THEN 'Медосмотры работающих'
                    WHEN 5 THEN 'Медосмотры ЖД'
                END                                                 "inputFormName",
                COALESCE(mp.RegistryControl, 0)                     "registryControl",
                COALESCE(mp.ExchangeDPC, 0)                         "exchangeDPC",
                mp.MedPrivelegeCodeDPC                              "contingentDPCId",
                (
                    SELECT Name
                    FROM MedPrivelege
                    WHERE MedPrivelegeCode = mp.MedPrivelegeCodeDPC
                )                                                   "contingentDPCName",
                mp.MedCouponWorkCode                                "jobId",
                (
                    SELECT Name
                    FROM MedCouponWork
                    WHERE FlagCoupon = 1
                        AND MedCouponWorkCode = mp.MedCouponWorkCode
                )                                                   "jobName",
                mp.medProfessionCode                                "professionId",
                (
                    SELECT Name
                    FROM medProfession
                    WHERE medProfessionCode = mp.medProfessionCode
                )                                                   "professionName",
                */
            FROM MedPreventType MP
            LEFT JOIN MedPreventTypeCabinet MPTC ON MP.MedPreventTypeCode = MPTC.MedPreventTypeCode
            LEFT JOIN MedCabinet MC ON MPTC.MedCabinetCode = MC.MedCabinetCode
            WHERE MP.EnterpriseCode = $enterpriseId
                $where
            GROUP BY 1
            ORDER BY MP.InputForm, MP.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute($sqlParams); return $stmt->fetchAll(); } public function insertOrUpdatePreventTypes($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $ret = []; $sql = <<<EOT
INSERT INTO MedPreventType (
    MedPreventTypeCode, EnterpriseCode, Name, RegistryControl, InputForm, FormFields, MedProfessionCode,
    MedCouponWorkCode, ExchangeDPC, MedPrivelegeCodeDPC, DisplayInInfobox, CiszMark)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (MedPreventTypeCode) DO UPDATE SET
    Name = case when ? = 1 then EXCLUDED.Name else MedPreventType.Name end,
    RegistryControl = case when ? = 1 then EXCLUDED.RegistryControl else MedPreventType.RegistryControl end,
    InputForm = case when ? = 1 then EXCLUDED.InputForm else MedPreventType.InputForm end,
    FormFields = case when ? = 1 then EXCLUDED.FormFields else MedPreventType.FormFields end,
    MedProfessionCode = case when ? = 1 then EXCLUDED.MedProfessionCode else MedPreventType.MedProfessionCode end,
    MedCouponWorkCode = case when ? = 1 then EXCLUDED.MedCouponWorkCode else MedPreventType.MedCouponWorkCode end,
    ExchangeDPC = case when ? = 1 then EXCLUDED.ExchangeDPC else MedPreventType.ExchangeDPC end,
    MedPrivelegeCodeDPC = case when ? = 1 then EXCLUDED.MedPrivelegeCodeDPC else MedPreventType.MedPrivelegeCodeDPC end,
    DisplayInInfobox = case when ? = 1 then EXCLUDED.DisplayInInfobox else MedPreventType.DisplayInInfobox end,
    CiszMark = CASE WHEN ? = 1 THEN EXCLUDED.CiszMark ELSE MedPreventType.CiszMark END
RETURNING MedPreventTypeCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $isCiszValue = null; if (property_exists($row, 'isCisz') && $row->isCisz === true) { $isCiszValue = 1; } $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), $enterpriseId, isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'registryControl'), isUndefinedProperty($row, 'inputFormId'), isUndefinedProperty($row, 'formFields'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'couponJobId'), isUndefinedProperty($row, 'exchangeDPC'), isUndefinedProperty($row, 'contingentDPCId'), isUndefinedProperty($row, 'displayInInfobox'), $isCiszValue, propertyExistsSQL($row, 'name'), propertyExistsSQL($row, 'registryControl'), propertyExistsSQL($row, 'inputFormId'), propertyExistsSQL($row, 'formFields'), propertyExistsSQL($row, 'professionId'), propertyExistsSQL($row, 'couponJobId'), propertyExistsSQL($row, 'exchangeDPC'), propertyExistsSQL($row, 'contingentDPCId'), propertyExistsSQL($row, 'displayInInfobox'), propertyExistsSQL($row, 'isCisz') ]); $rec->id = fetchValue($stmt, 'id'); if (isEmptyProperty($row, 'cabinets')) { $this->syncCabinets($row->cabinets, $rec->id); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPreventTypes($records) { return $this->insertOrUpdatePreventTypes($records, true); } public function updatePreventTypes($records) { return $this->insertOrUpdatePreventTypes($records, false); } public function deletePreventTypes($records) { return deleteSql($this->db, 'MedPreventType', 'MedPreventTypeCode', $records); } public function copyPrevent($params) { $db = $this->db ?: $this->db = connectDb(); $preventId = isEmptyProperty($params, 'preventId', 0); $sql = <<<EOT
            INSERT INTO MedPrevent(
                Position, Name, PeriodMonth,
                Sex, AgeFrom, AgeTo,
                AgeFromMonth, AgeToMonth, MedPreventTypeCode,
                FlagVisit
            )
            SELECT
                COALESCE(
                            (
                                SELECT
                                    MAX(mpp.Position)
                                FROM MedPrevent mpp
                                WHERE mpp.MedPreventTypeCode = mp.MedPreventTypeCode OR COALESCE(mpp.MedPreventTypeCode, 0) = 0
                            ), 0
                        ) + 1,
                mp.Name || ' (копия)',
                mp.PeriodMonth,
                mp.Sex,
                mp.AgeFrom,
                mp.AgeTo,
                mp.AgeFromMonth,
                mp.AgeToMonth,
                null,
                mp.FlagVisit
            FROM MedPrevent mp
            WHERE mp.MedPreventCode = $preventId
            RETURNING MedPreventCode AS id
EOT;
$stmt = $db->prepare($sql); $sqlRows = <<<EOT
INSERT INTO medpreventrow(medpreventcode, rowtype, meddiagnosiscode, medprivelegecode, medpatientgroupcode, medconclusiongroupcode)
SELECT ?,
       pr.rowtype,
       pr.meddiagnosiscode,
       pr.medprivelegecode,
       pr.medpatientgroupcode,
       pr.medconclusiongroupcode
FROM medpreventrow AS pr
WHERE pr.medpreventcode = $preventId
EOT;
$stmtRows = $db->prepare($sqlRows); $sqlDirections = <<<EOT
INSERT INTO MedPreventDirection(MedPreventCode, MedProfessionCode, unidictcoderesearch, unidictcodeprofessionrw, documentkind, periodmonth, flagcondition, rowcode131udv, rowcode131udr, CiszResourceValueCodeActivity)
SELECT ?,
       pd.MedProfessionCode,
       pd.unidictcoderesearch,
       pd.unidictcodeprofessionrw,
       pd.documentkind,
       pd.periodmonth,
       pd.flagcondition,
       pd.rowcode131udv,
       pd.rowcode131udr,
       PD.CiszResourceValueCodeActivity
FROM MedPreventDirection pd
WHERE pd.MedPreventCode = $preventId
EOT;
$stmtDirections = $db->prepare($sqlDirections); $sqlConclusions = <<<EOT
INSERT INTO MedPreventConclusion(MedPreventCode, MedConclusionCode)
SELECT ?,
       pc.MedConclusionCode
FROM MedPreventConclusion pc
WHERE pc.MedPreventCode = $preventId
EOT;
$stmtConclusions = $db->prepare($sqlConclusions); $db->beginTransaction(); try { $stmt->execute(); $id = fetchValue($stmt, 'id'); $stmtRows->execute([$id]); $stmtDirections->execute([$id]); $stmtConclusions->execute([$id]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $id; } public function copyPreventionGroups($params) { $db = $this->db ?: $this->db = connectDb(); $copyFromPreventTypeId = isEmptyProperty($params, 'preventTypeId', 0); $copyToPreventTypeId = isEmptyProperty($params, 'copyToPreventTypeId', 0); $id = 0; $preventionGroupsToCopy = $this->readPrevents($params); $sqlDeletePreventionGroups = <<<EOT
            DELETE FROM medPrevent
            WHERE medPreventTypeCode = ?
EOT;
$stmtDeletePreventionGroups = $db->prepare($sqlDeletePreventionGroups); $sqlInsertPreventionGroup = <<<EOT
            INSERT INTO medPrevent(
                position, name, periodMonth,
                sex, ageFrom, ageTo,
                AgeFromMonth, AgeToMonth, medPreventTypeCode,
                flagVisit
            )
            SELECT
                COALESCE(
                            (
                                SELECT MAX(mp1.position)
                                FROM medPrevent mp1
                                WHERE mp1.medPreventTypeCode = :copyToPreventTypeId
                            ), 0
                        ) + 1,
                mp.name,
                mp.periodMonth,
                mp.sex,
                mp.ageFrom,
                mp.ageTo,
                MP.AgeFromMonth,
                MP.AgeToMonth,
                :copyToPreventTypeId,
                mp.flagVisit
            FROM medPrevent mp
            WHERE mp.medPreventCode = :preventId
            RETURNING medPreventCode AS id
EOT;
$stmtInsertPreventionGroup = $db->prepare($sqlInsertPreventionGroup); $sqlRows = <<<EOT
            INSERT INTO medPreventRow(medPreventCode, rowType, medDiagnosisCode,
                                        medPrivelegeCode, medPatientGroupCode, medConclusionGroupCode)
            SELECT
                :newPreventId,
                pr.rowType,
                pr.medDiagnosisCode,
                pr.medPrivelegeCode,
                pr.medPatientGroupCode,
                pr.medConclusionGroupCode
            FROM medPreventRow pr
            WHERE pr.medPreventCode = :preventId
EOT;
$stmtRows = $db->prepare($sqlRows); $sqlDirections = <<<EOT
            INSERT INTO medPreventDirection(medPreventCode, medProfessionCode, uniDictCodeResearch,
                                            uniDictCodeProfessionRw, documentKind, periodMonth,
                                            flagCondition, rowCode131udv, rowCode131udr, CiszResourceValueCodeActivity)
            SELECT
                :newPreventId,
                pd.medProfessionCode,
                pd.uniDictCodeResearch,
                pd.uniDictCodeProfessionRw,
                pd.documentKind,
                pd.periodMonth,
                pd.flagCondition,
                pd.rowCode131udv,
                pd.rowCode131udr,
                PD.CiszResourceValueCodeActivity
            FROM medPreventDirection pd
            WHERE pd.medPreventCode = :preventId
EOT;
$stmtDirections = $db->prepare($sqlDirections); $sqlConclusions = <<<EOT
            INSERT INTO medPreventConclusion(medPreventCode, medConclusionCode)
            SELECT
                :newPreventId,
                pc.medConclusionCode
            FROM medPreventConclusion pc
            WHERE pc.medPreventCode = :preventId
EOT;
$stmtConclusions = $db->prepare($sqlConclusions); $db->beginTransaction(); try { $stmtDeletePreventionGroups->execute([ $copyToPreventTypeId ]); foreach ($preventionGroupsToCopy as $pg) { $stmtInsertPreventionGroup->execute([ ':copyToPreventTypeId' => $copyToPreventTypeId, ':preventId' => $pg['id'] ]); $row = $stmtInsertPreventionGroup->fetch(); $id = $row['id']; $stmtRows->execute([ ':newPreventId' => $id, ':preventId' => $pg['id'] ]); $stmtDirections->execute([ ':newPreventId' => $id, ':preventId' => $pg['id'] ]); $stmtConclusions->execute([ ':newPreventId' => $id, ':preventId' => $pg['id'] ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPrevents($params = null) { $db = $this->db ?: $this->db = connectDb(); if (isEmptyProperty($params, 'patientId')) { return $this->readPreventByPatient($params); } $preventTypeId = isEmptyProperty($params, 'preventTypeId', 0); $id = isEmptyProperty($params, 'id', 0); $sql = <<<EOT
            SELECT
                MP.MedPreventCode                       "id",
                MP.Position                             "position",
                MP.Name                                 "name",
                MP.PeriodMonth                          "periodMonth",
                MP.Sex                                  "sex",
                MP.AgeFrom                              "ageFrom",
                MP.AgeTo                                "ageTo",
                MP.AgeFromMonth                         "ageFromMonth",
                MP.AgeToMonth                           "ageToMonth",
                MP.MedPreventTypeCode                   "preventTypeId",
                COALESCE(MP.flagVisit, 0)               "flagVisit",
                (
                    SELECT string_agg(distinct(mpr.medDiagnosisCode::TEXT), ',')
                    FROM medPreventRow mpr
                    WHERE mpr.MedPreventCode = mp.MedPreventCode
                    AND mpr.rowType = 1
                )                                       "diagnosisIds",
                (
                    SELECT string_agg(distinct(mpr.medPrivelegeCode::TEXT), ',')
                    FROM medPreventRow mpr
                    WHERE mpr.MedPreventCode = mp.MedPreventCode
                    AND mpr.rowType = 2
                )                                       "contingentsIds",
                (
                    SELECT string_agg(distinct(mpr.medPatientGroupCode::TEXT), ',')
                    FROM medPreventRow mpr
                    WHERE mpr.MedPreventCode = mp.MedPreventCode
                    AND mpr.rowType = 3
                )                                       "sectorsIds",
                (
                    SELECT string_agg(distinct(mpr.medConclusionGroupCode::TEXT), ',')
                    FROM medPreventRow mpr
                    WHERE mpr.MedPreventCode = mp.MedPreventCode
                    AND mpr.rowType = 8
                )                                       "conclusionGroupsIds",
                (
                    SELECT string_agg(distinct(pc.medConclusionCode::TEXT), ',')
                    FROM medPreventConclusion pc
                    WHERE pc.MedPreventCode = mp.MedPreventCode
                )                                       "conclusionsIds",
                MPT.InputForm                           "inputForm",
                CRV1.Code                               "ciszCode"
            FROM MedPrevent MP
            LEFT JOIN MedPreventType MPT ON MP.MedPreventTypeCode = MPT.MedPreventTypeCode
            LEFT JOIN LATERAL (
                SELECT CRV.Code
                FROM CiszResourceValue CRV
                JOIN CiszResource CR ON CRV.CiszResourceCode = CR.CiszResourceCode AND CR.Source = 33
                WHERE MP.MedPreventCode = CRV.SourceCode
            ) CRV1 ON TRUE
            WHERE ((COALESCE(MP.MedPreventTypeCode, 0) = $preventTypeId) OR (COALESCE(MP.MedPreventTypeCode, 0) = 0))
            AND (($id = 0) OR ($id = MP.MedPreventCode))
            ORDER BY MP.Position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readPreventByPatient($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $sqlParams = [ ':patientId' => $patientId ]; $preventTypeIdSql = ""; $preventTypeId = isEmptyProperty($params, 'preventTypeId'); if ($preventTypeId) { $preventTypeIdSql = <<<EOT
                WHERE PR.MedPreventTypeCode = :preventTypeId
                    AND (
                        DATEDIFFMONTH(PASS.DateOfBirth, COALESCE(:documentDate, 'now')::DATE) >= COALESCE(PR.AgeFrom, -99999) * 12 + COALESCE(PR.AgeFromMonth, 0)
                            AND DATEDIFFMONTH(PASS.DateOfBirth, COALESCE(:documentDate, 'now')::DATE) <= COALESCE(PR.AgeTo, 99999) * 12 + COALESCE(PR.AgeToMonth, 11)
                            AND (PR.sex IS NULL OR PR.sex = PASS.sex)
                    )
EOT;
$sqlParams[':preventTypeId'] = $preventTypeId; } $documentDate = isEmptyProperty($params, 'documentDate'); $sqlParams[':documentDate'] = $documentDate; $sql = <<<EOT
            SELECT
                PR.MedPreventCode       "id",
                PR.Name                 "name",
                PRT.MedPreventTypeCode  "preventTypeId",
                PRT.inputForm           "inputForm",
                (
                    DATEDIFFMONTH(PASS.DateOfBirth, COALESCE(:documentDate, 'now')::DATE) >= COALESCE(PR.AgeFrom, -99999) * 12 + COALESCE(PR.AgeFromMonth, 0)
                    AND DATEDIFFMONTH(PASS.DateOfBirth, COALESCE(:documentDate, 'now')::DATE) <= COALESCE(PR.AgeTo, 99999) * 12 + COALESCE(PR.AgeToMonth, 11)
                        AND (PR.Sex IS NULL OR PR.Sex = PASS.sex)
                )                       "isSelected"
            FROM MedPrevent PR
            JOIN MedPreventType PRT ON PRT.MedPreventTypeCode = PR.MedPreventTypeCode
            LEFT JOIN MedPatient MP ON MP.MedPatientCode = :patientId
            LEFT JOIN ManPassport PASS ON PASS.manCode = MP.manCode
            $preventTypeIdSql
            ORDER BY "isSelected" DESC
EOT;
return selectSql($db, $sql, $sqlParams); } public function insertOrUpdatePrevents($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<EOT
            INSERT INTO MedPrevent (
                MedPreventCode, Position, Name,
                PeriodMonth, Sex, AgeFrom,
                AgeTo, AgeFromMonth, AgeToMonth,
                FlagVisit, MedPreventTypeCode
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?
            )
            ON CONFLICT (MedPreventCode) DO UPDATE SET
                Position                = CASE WHEN ? = 1 THEN EXCLUDED.Position              ELSE MedPrevent.Position END,
                Name                    = CASE WHEN ? = 1 THEN EXCLUDED.Name                  ELSE MedPrevent.Name END,
                PeriodMonth             = CASE WHEN ? = 1 THEN EXCLUDED.PeriodMonth           ELSE MedPrevent.PeriodMonth END,
                Sex                     = CASE WHEN ? = 1 THEN EXCLUDED.Sex                   ELSE MedPrevent.Sex END,
                AgeFrom                 = CASE WHEN ? = 1 THEN EXCLUDED.AgeFrom               ELSE MedPrevent.AgeFrom END,
                AgeTo                   = CASE WHEN ? = 1 THEN EXCLUDED.AgeTo                 ELSE MedPrevent.AgeTo END,
                AgeFromMonth            = CASE WHEN ? = 1 THEN EXCLUDED.AgeFromMonth          ELSE MedPrevent.AgeFromMonth END,
                AgeToMonth              = CASE WHEN ? = 1 THEN EXCLUDED.AgeToMonth            ELSE MedPrevent.AgeToMonth END,
                FlagVisit               = CASE WHEN ? = 1 THEN EXCLUDED.FlagVisit             ELSE MedPrevent.FlagVisit END,
                MedPreventTypeCode      = CASE WHEN ? = 1 THEN EXCLUDED.MedPreventTypeCode    ELSE MedPrevent.MedPreventTypeCode END
            RETURNING MedPreventCode AS id
EOT;
$stmt = $db->prepare($sql); $sqlSetNullPosition = <<<EOT
            UPDATE MedPrevent SET Position = null
            WHERE MedPreventCode = ?
EOT;
$stmtSetNullPosition = $db->prepare($sqlSetNullPosition); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmtSetNullPosition->execute([ $isInsert ? null : isUndefinedProperty($row, 'id') ]); } foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'position'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'periodMonth'), isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'ageFrom'), isUndefinedProperty($row, 'ageTo'), isUndefinedProperty($row, 'ageFromMonth'), isUndefinedProperty($row, 'ageToMonth'), isUndefinedProperty($row, 'flagVisit'), isUndefinedProperty($row, 'preventTypeId'), propertyExistsSQL($row, 'position'), propertyExistsSQL($row, 'name'), propertyExistsSQL($row, 'periodMonth'), propertyExistsSQL($row, 'sex'), propertyExistsSQL($row, 'ageFrom'), propertyExistsSQL($row, 'ageTo'), propertyExistsSQL($row, 'ageFromMonth'), propertyExistsSQL($row, 'ageToMonth'), propertyExistsSQL($row, 'flagVisit'), propertyExistsSQL($row, 'preventTypeId') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPrevents($records) { return $this->insertOrUpdatePrevents($records, true); } public function updatePrevents($records) { return $this->insertOrUpdatePrevents($records, false); } public function deletePrevents($records) { return deleteSql($this->db, 'medPrevent', 'medPreventCode', $records); } public function readPreventRows($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventId = isEmptyProperty($params, 'preventId', 0); $rowType = isEmptyProperty($params, 'rowType', 0); $sql = <<<EOT
SELECT pr.medpreventrowcode "id",
       pr.medpreventcode    "preventId",
       pr.rowtype           "rowType",
       pr.meddiagnosiscode  "diagnosisId",
       pr.medprivelegecode  "contingentId",
       pr.medpatientgroupcode "sectorId",
       pr.medconclusiongroupcode "conclusionGroupId"
FROM medpreventrow AS pr
WHERE pr.medpreventcode = $preventId
  AND (($rowType = 0) OR ($rowType = pr.rowtype))
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdatePreventRows($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<EOT
INSERT INTO MedPreventRow (MedPreventRowCode, MedPreventCode, rowtype, meddiagnosiscode, medprivelegecode,
                           medpatientgroupcode, medconclusiongroupcode)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (MedPreventRowCode) DO UPDATE SET
    meddiagnosiscode = case when ? = 1 then EXCLUDED.meddiagnosiscode else medpreventrow.meddiagnosiscode end,
    medprivelegecode = case when ? = 1 then EXCLUDED.medprivelegecode else medpreventrow.medprivelegecode end,
    medpatientgroupcode = case when ? = 1 then EXCLUDED.medpatientgroupcode else medpreventrow.medpatientgroupcode end,
    medconclusiongroupcode = case when ? = 1 then EXCLUDED.medconclusiongroupcode else medpreventrow.medconclusiongroupcode end
RETURNING MedPreventRowCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'preventId'), isUndefinedProperty($row, 'rowType'), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'contingentId'), isUndefinedProperty($row, 'sectorId'), isUndefinedProperty($row, 'conclusionGroupId'), propertyExistsSQL($row, 'diagnosisId'), propertyExistsSQL($row, 'contingentId'), propertyExistsSQL($row, 'sectorId'), propertyExistsSQL($row, 'conclusionGroupId') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPreventRows($records) { return $this->insertOrUpdatePreventRows($records, true); } public function updatePreventRows($records) { return $this->insertOrUpdatePreventRows($records, false); } public function deletePreventRows($records) { return deleteSql($this->db, 'MedPreventRow', 'MedPreventRowCode', $records); } public function readPreventDirectionConsults($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventId = isEmptyProperty($params, 'preventId', 0); $sql = <<<EOT
SELECT pd.MedPreventDirectionCode    "id",
       pd.MedPreventCode             "preventId",
       pd.MedProfessionCode          "professionId",
       p.Name                        "professionName",
       pd.UniDictCodeProfessionRW    "professionRWId",
       u.Value                       "professionRWName",
       COALESCE(pd.DocumentKind, 0)  "documentKind",
       pd.PeriodMonth                "periodMonth",
       COALESCE(pd.FlagCondition, 0) "flagCondition",
       pd.rowCode131uDR              "rowCode131uDR",
       PD.CiszResourceValueCodeActivity    "ciszActivityId",
       CRV.Name                      "ciszActivityName"
FROM MedPreventDirection pd
JOIN MedProfession p on p.MedProfessionCode = pd.MedProfessionCode
LEFT JOIN UniDict u on u.UniDictCode =  pd.UniDictCodeProfessionRW
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = PD.CiszResourceValueCodeActivity
WHERE pd.MedPreventCode = $preventId
ORDER BY 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPreventDirectionConsults($records) { return $this->insertOrUpdatePreventDirections($records, true); } public function updatePreventDirectionConsults($records) { return $this->insertOrUpdatePreventDirections($records, false); } public function deletePreventDirectionConsults($records) { return $this->deletePreventDirections($records); } public function readPreventDirectionResearches($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventId = isEmptyProperty($params, 'preventId', 0); $sql = <<<EOT
SELECT pd.MedPreventDirectionCode    "id",
       pd.MedPreventCode             "preventId",
       pd.UniDictCodeResearch        "researchId",
       u.Value                       "researchName",
       pd.UniDictCodeProfessionRW    "professionRWId",
       ud.Value                      "professionRWName",
       pd.PeriodMonth                "periodMonth",
       COALESCE(pd.FlagCondition, 0) "flagCondition",
       PD.CiszResourceValueCodeActivity    "ciszActivityId",
       CRV.Name                      "ciszActivityName"
FROM MedPreventDirection pd
JOIN UniDict u on u.UniDictCode = pd.UniDictCodeResearch
LEFT JOIN UniDict ud on ud.UniDictCode =  pd.UniDictCodeProfessionRW AND u.DictNumber = 28
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = PD.CiszResourceValueCodeActivity
WHERE pd.MedPreventCode = $preventId
ORDER BY 4
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPreventDirectionResearches($records) { return $this->insertOrUpdatePreventDirections($records, true); } public function updatePreventDirectionResearches($records) { return $this->insertOrUpdatePreventDirections($records, false); } public function deletePreventDirectionResearches($records) { return $this->deletePreventDirections($records); } public function insertOrUpdatePreventDirections($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<EOT
INSERT INTO MedPreventDirection (
    MedPreventDirectionCode, MedPreventCode, MedProfessionCode, UniDictCodeResearch, UniDictCodeProfessionRW,
    DocumentKind, PeriodMonth, FlagCondition, RowCode131udv, RowCode131udr, CiszResourceValueCodeActivity)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (MedPreventDirectionCode) DO UPDATE SET
    MedPreventCode = case when ? = 1 then EXCLUDED.MedPreventCode else MedPreventDirection.MedPreventCode end,
    MedProfessionCode = case when ? = 1 then EXCLUDED.MedProfessionCode else MedPreventDirection.MedProfessionCode end,
    UniDictCodeResearch = case when ? = 1 then EXCLUDED.UniDictCodeResearch else MedPreventDirection.UniDictCodeResearch end,
    UniDictCodeProfessionRW = case when ? = 1 then EXCLUDED.UniDictCodeProfessionRW else MedPreventDirection.UniDictCodeProfessionRW end,
    DocumentKind = case when ? = 1 then EXCLUDED.DocumentKind else MedPreventDirection.DocumentKind end,
    PeriodMonth = case when ? = 1 then EXCLUDED.PeriodMonth else MedPreventDirection.PeriodMonth end,
    FlagCondition = case when ? = 1 then EXCLUDED.FlagCondition else MedPreventDirection.FlagCondition end,
    RowCode131udv = case when ? = 1 then EXCLUDED.RowCode131udv else MedPreventDirection.RowCode131udv end,
    RowCode131udr = case when ? = 1 then EXCLUDED.RowCode131udr else MedPreventDirection.RowCode131udr end,
    CiszResourceValueCodeActivity = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeActivity ELSE MedPreventDirection.CiszResourceValueCodeActivity END
RETURNING MedPreventDirectionCode AS id
EOT;
$stmt = $db->prepare($sql); $sqlPreventCiszCode = <<<EOT
SELECT MPD.MedPreventDirectionCode
FROM MedPreventDirection MPD
JOIN MedPrevent MP ON MPD.MedPreventCode = MP.MedPreventCode
JOIN CiszResourceValue CRV ON CRV.SourceCode = MP.MedPreventCode
JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
JOIN (
    SELECT 
        CRV2.Code,
        MPD2.MedProfessionCode,
        MPD2.UniDictCodeResearch
    FROM MedPreventDirection MPD2
    JOIN MedPrevent MP2 ON MPD2.MedPreventCode = MP2.MedPreventCode
    JOIN CiszResourceValue CRV2 ON CRV2.SourceCode = MP2.MedPreventCode
    JOIN CiszResource CR2 ON CR2.CiszResourceCode = CRV2.CiszResourceCode
    WHERE CR2.Source = 33
    AND MPD2.MedPreventDirectionCode = ?
) t
ON t.Code = CRV.Code
AND ((t.MedProfessionCode = MPD.MedProfessionCode AND MPD.UniDictCodeResearch IS NULL) 
    OR (t.UniDictCodeResearch = MPD.UniDictCodeResearch AND MPD.MedProfessionCode IS NULL))
WHERE CR.Source = 33
EOT;
$stmtPreventCiszCode = $db->prepare($sqlPreventCiszCode); $sqlUpdateCisz = <<<EOT
UPDATE MedPreventDirection
SET CiszResourceValueCodeActivity = ?
WHERE MedPreventDirectionCode = ?
EOT;
$stmtUpdateCisz = $db->prepare($sqlUpdateCisz); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'preventId'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'researchId'), isUndefinedProperty($row, 'professionRWId'), isUndefinedProperty($row, 'documentKind'), isUndefinedProperty($row, 'periodMonth'), isUndefinedProperty($row, 'flagCondition'), isUndefinedProperty($row, 'rowCode131uDV'), isUndefinedProperty($row, 'rowCode131uDR'), isUndefinedProperty($row, 'ciszActivityId'), propertyExistsSQL($row, 'preventId'), propertyExistsSQL($row, 'professionId'), propertyExistsSQL($row, 'researchId'), propertyExistsSQL($row, 'professionRWId'), propertyExistsSQL($row, 'documentKind'), propertyExistsSQL($row, 'periodMonth'), propertyExistsSQL($row, 'flagCondition'), propertyExistsSQL($row, 'rowCode131uDV'), propertyExistsSQL($row, 'rowCode131uDR'), propertyExistsSQL($row, 'ciszActivityId') ]); $rec->id = fetchValue($stmt, 'id'); if (propertyExistsSQL($row, 'ciszActivityId')) { $stmtPreventCiszCode->execute([ $rec->id ]); $linkedIds = $stmtPreventCiszCode->fetchAll(PDO::FETCH_COLUMN); foreach ($linkedIds as $linkedId) { if ($linkedId == $rec->id) { continue; } $stmtUpdateCisz->execute([ isUndefinedProperty($row, 'ciszActivityId'), $linkedId ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePreventDirections($records) { return deleteSql($this->db, 'MedPreventDirection', 'MedPreventDirectionCode', $records); } public function readPreventConclusions ($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventId = isEmptyProperty($params, 'preventId', 0); $sql = <<<EOT
SELECT c.MedConclusionCode                       "id",
       c.Name                                    "name",
       (pc.MedPreventConclusionCode IS NOT NULL) "used",
       pc.MedPreventCode                         "preventId"
FROM MedConclusion c
LEFT JOIN MedPreventConclusion pc ON pc.MedConclusionCode = c.MedConclusionCode AND pc.MedPreventCode = $preventId
ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updatePreventConclusions($records) { $db = $this->db ?: $this->db = connectDb(); $sqlInsert = <<<EOT
INSERT INTO MedPreventConclusion (MedConclusionCode, MedPreventCode)
VALUES (?, ?)
EOT;
$stmtInsert = $db->prepare($sqlInsert); $sqlDelete = <<<EOT
DELETE FROM MedPreventConclusion
WHERE MedConclusionCode = ?
  AND MedPreventCode = ?
EOT;
$stmtDelete = $db->prepare($sqlDelete); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (isUndefinedProperty($row, 'used')) { $stmtInsert->execute([ isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'preventId') ]); } else { $stmtDelete->execute([ isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'preventId') ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } private function syncCabinets($cabinets = null, $preventTypeCode = null ) { $db = $this->db; $sqlSelect = <<<"SQL"
SELECT *
FROM MedPreventTypeCabinet MPTC
WHERE MPTC.MedPreventTypeCode = ? 
SQL;
$existingRecords = selectSql($db, $sqlSelect, [$preventTypeCode]); if (!$preventTypeCode && empty($existingRecords)) { return; } $sampleResearchCabinetsIds = !$cabinets ? [] : $cabinets; foreach ($sampleResearchCabinetsIds as $key => $value) { foreach ($existingRecords as $keyExisting => $valueExisting) { if ((int)$sampleResearchCabinetsIds === $valueExisting) { unset($sampleResearchCabinetsIds[$key]); unset($existingRecords[$keyExisting]); } } } $sqlDelete = <<<"SQL"
DELETE FROM MedPreventTypeCabinet
WHERE MedPreventTypeCode = ? AND MedCabinetCode= ?
SQL;
foreach ($existingRecords as $existingRecord) { executeSql($db, $sqlDelete, [$existingRecord['medpreventtypecode'], $existingRecord['medcabinetcode']]); } $sqlInsert = <<<"SQL"
INSERT INTO MedPreventTypeCabinet (MedCabinetCode, MedPreventTypeCode)
VALUES (?, ?)
SQL;
foreach ($sampleResearchCabinetsIds as $cabinetCode) { executeSql($db, $sqlInsert, [$cabinetCode, $preventTypeCode]); } } } handleJsonRpc('med_common_prevents');