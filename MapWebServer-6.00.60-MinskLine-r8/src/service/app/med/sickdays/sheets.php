<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/sickdays/marks.php'); resumeJsonRpc(); class med_sickdays_sheets { const PAGE_SIZE = 30; private $db; public function readSheets($params = null) { $db = $this->db ?: $this->db = connectDb(); $filters = isEmptyProperty($params, 'filter'); $sqlParams = []; $currDate = Date("Ymd"); $filterSql = ''; if ($filters) { $filters = base64_decode($filters); $filters = safe_json_decode($filters, false); $filterSql = $this->getFilterSql($filters, $sqlParams); } $currId = isEmptyProperty($params, 'currId') ? $params->currId : null; $isCardLoad = isEmptyProperty($params, 'isCardLoad') ? $params->isCardLoad : null; $idParams = $currId ? "and MH.MedHospitalCode = $currId" : ''; $sort = isEmptyProperty($params, 'sort', 4); $dir = isEmptyProperty($params, 'dir', ""); $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::PAGE_SIZE); $limit = $limit <= self::PAGE_SIZE ? self::PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $isVKK = isEmptyProperty($params, 'isVKK'); $codeFromOpen = isUndefinedProperty($params, 'codeFromOpen'); if ($isVKK && $codeFromOpen) { $filterSql .= <<<"EOT"
AND ((MH.StartHospitalDate >= :dateFrom AND (MH.FinishDate <= :dateTo))
    OR ( :dateFrom BETWEEN MH.StartHospitalDate AND MH.FinishDate))
AND MH.MedHospitalCode <> :codeFromOpen
AND NOT EXISTS(
    SELECT
        1
    FROM MedHospitalMark MHM
    LEFT JOIN UniDict UD ON UD.DictNumber = 20 AND UD.UniDictCode = MHM.UniDictCodeMark
    WHERE MHM.MedHospitalCode = MH.MedHospitalCode
    AND UD.Value1::int IN(3,4,5,6)
    LIMIT 1)
EOT;
$sqlParams[':codeFromOpen'] = $codeFromOpen; } $sqlMain = <<<"EOT"
from MedHospital MH
     left join MedHospitalNext MHN on MH.MedHospitalCode = MHN.MedHospitalCode
                                  and MHN.Position = 0
     left join MedPatient MP on MP.MedPatientCode = MH.MedPatientCode
     left join ManPassport MANP on MANP.ManCode = MP.ManCode
     LEFT JOIN meddoctor MDopen ON MDopen.meddoctorcode = MH.meddoctoropencode
     LEFT JOIN meddoctor MDclose ON MDclose.meddoctorcode = MH.meddoctorclosecode
     LEFT JOIN MedDoctor MDCC ON MDCC.MedDoctorCode = MH.MedDoctorCodeChief
     LEFT JOIN CiszLink CL ON CL.TableName = 'temporaryDisability' AND CL.PrimaryKey = MH.MedHospitalCode AND CL.Type = 1
     LEFT JOIN EnterpriseStructure ES
                                    ON (ES.departmentcodeweb = MDopen.departmentcode
                                    AND  MDclose.meddoctorcode is NULL) OR
                                    ES.departmentcodeweb = MDclose.departmentcode
where 0 = 0
  $idParams
  $filterSql
  and MHN.DocumentNumber IS NOT NULL
EOT;
if ($currId) { $start = 0; $limit = 1; } else { $sqlCount = <<<"EOT"
select
       COUNT(*) "total"
$sqlMain
EOT;
$stmtCount = $db->prepare($sqlCount); $stmtCount->execute($sqlParams); $total = (int) fetchValue($stmtCount, 'total'); $start = $total < $start ? 0 : $start; } $sortingFields = [ 'documentNumber' => "LPAD(CONCAT(MHN.DocumentSeries, MHN.DocumentNumber), 10, '0') $dir", 'patientFio' => "\"patientFio\" $dir", 'openDate' => "MH.StartDate $dir", 'giveDate' => "MH.GiveDate $dir", 'startHospitalDate' => "MH.StartHospitalDate $dir", 'doctorOpen' => "\"doctorOpen\" $dir", 'diagnosisOpen' => "\"diagnosisOpenCode\" $dir", 'closeDate' => "MH.FinishDate $dir NULLS LAST", 'doctorClose' => "\"doctorClose\" $dir NULLS LAST", 'diagnosisClose' => "\"diagnosisCloseCode\" $dir", 'casesCount' => "MH.CasesCount $dir", 'hospitalDay' => "MH.HospitalDay $dir", 'markList' => "\"markList\" $dir", ]; $orderBy = key_exists($sort, $sortingFields) ? "ORDER BY {$sortingFields[$sort]}" : ''; $sqlData = <<<"EOT"
select T.*,
       (CASE (DATEDIFFYEAR(T."dateOfBirth", $currDate)) > 0
             WHEN true THEN CAST(FN_cmGetMonthYearString((DATEDIFFYEAR(T."dateOfBirth", $currDate))) as VARCHAR(250))
             WHEN false THEN CASE (DATEDIFFMONTH(T."dateOfBirth", $currDate)) > 0
                                  WHEN true then cast(FN_cmGetMonthYearString(((DATEDIFFMONTH(T."dateOfBirth", $currDate)) / 100.00), 2) as VARCHAR(250))
                                  WHEN false THEN CASE (DATEDIFF('day', T."dateOfBirth", $currDate)) > 0
                                                       WHEN true THEN CAST(FN_cmGetDayString((DATEDIFF('day', T."dateOfBirth", $currDate))) as VARCHAR(250))
                                                  END
                             END
        END) "ageStr",
      (select MP_.Name
       from MedDoctor MD_
            left join MedProfession MP_ on MP_.MedProfessionCode = MD_.MedProfessionCode
       where MD_.MedDoctorCode = T."doctorOpenId") "doctorOpenProfession",
      (select MP_.Name
       from MedDoctor MD_
            left join MedProfession MP_ on MP_.MedProfessionCode = MD_.MedProfessionCode
       where MD_.MedDoctorCode = t."doctorCloseId") "doctorCloseProfession",
      FN_cmCalcAddress(T."manIdP") "address",
      (select Cipher1  || ' - ' || (select Name
                                    from MedDiagnosis
                                    where MedDiagnosisCode = T."diagnosisCloseId")
             from MedDiagnosis
             where MedDiagnosisCode = T."diagnosisCloseId")::text "diagnosisClose",
      (select Cipher1 || ' - ' || (select Name
                                   from MedDiagnosis
                                   where MedDiagnosisCode = T."diagnosisOpenId")
       from MedDiagnosis
       where MedDiagnosisCode = T."diagnosisOpenId")::text "diagnosisOpen",
      (select Cipher1
       from MedDiagnosis
       where MedDiagnosisCode = T."diagnosisOpenId")::text "diagnosisOpenChiper",
      (select Cipher1
       from MedDiagnosis
       where MedDiagnosisCode = T."diagnosisCloseId")::text "diagnosisCloseChiper"
from (
select
       MH.MedHospitalCode "id",
       MH.HospitalType "type",
       MHN.DocumentSeries "documentSeries",
       MHN.DocumentNumber "documentNumber",
       MH.MedPatientCode "patientId",
       MP.ManCode "manIdP",
       FN_cmFIO(MP.ManCode) "patientFio",
       FN_cmFIO((select ManCode from MedDoctor where MedDoctorCode = MH.MedDoctorOpenCode)) "doctorOpen",
       NULLIF(FN_cmFIO((select ManCode from MedDoctor where MedDoctorCode = MH.MedDoctorCloseCode)), '') "doctorClose",
       (select Cipher1 from MedDiagnosis where MedDiagnosisCode = MH.MedDiagnosisPrimaryCode) "diagnosisOpenCode",
       (select Cipher1 from MedDiagnosis where MedDiagnosisCode = MH.MedDiagnosisFinalCode) "diagnosisCloseCode",
       (select string_agg((select Value
                          from UniDict
                          where UniDictCode = UniDictCodeMark)::text, ', ')
       from MedHospitalMark
       where MedhospitalCode = MH.MedHospitalCode) "markList",
       MANP.DateOfBirth "dateOfBirth",
       (select asa_trim((select Name
                            from Enterprise
                            where EnterpriseCode = MPB_.EnterpriseCode)) || CASE (MPB_.UniDictCodeProfession IS NOT NULL)
                                                                                 WHEN true THEN ', ' || asa_trim((select Value
                                                                                                                  from UniDict
                                                                                                                  where UniDictCode = MPB_.UniDictCodeProfession))
                                                                                 WHEN false THEN NULL
                                                                            END::text
           from MedPatientBusy MPB_
           where MPB_.medPatientCode =  MH.MedPatientCode
             and $currDate between MPB_.startdate and MPB_.finishdate
           order by MPB_.startDate DESC, MPB_.finishDate DESC
           limit 1) "patientInfo",
       FN_cmFIO(MH.ManCodeReceived) "fioReceived",
       MH.ManCodeReceived "manIdReceived",
       MH.PatientEmployment "busy",
       MH.MedDoctorOpenCode "doctorOpenId",
       MH.MedDoctorCloseCode "doctorCloseId",
       MH.MedDiagnosisPrimaryCode "diagnosisOpenId",
       MH.MedDiagnosisFinalCode "diagnosisCloseId",
       MH.StartDate "openDate",
       MH.GiveDate "giveDate",
       MH.StartHospitalDate "startHospitalDate",
       MH.FinishDate "closeDate",
       MH.HospitalDay "hospitalDay",
       MH.CasesCount "casesCount",
       MH.Checked "checked",
       CL.Status "ciszStatus",
       MH.MedDoctorCodeChief "chiefId",
       MH.UnidictCodeTempDisabilityType "tempDisabilityTypeId"
$sqlMain
$orderBy
limit $limit offset $start
) T;
EOT;
$sqlDisableOptimization = <<<"EOT"
SET ENABLE_HASHJOIN TO FALSE;
EOT;
$sqlEnableOptimization = <<<"EOT"
SET ENABLE_HASHJOIN TO TRUE;
EOT;
$stmtDisable = $db->prepare($sqlDisableOptimization); $stmtDisable->execute(); $stmtData = $db->prepare($sqlData); $stmtData->execute($sqlParams); $stmtEnable = $db->prepare($sqlEnableOptimization); $stmtEnable->execute(); if ($currId && $isCardLoad) { $root = new stdClass(); $root->items = $stmtData->fetchAll(); return $root; } elseif ($currId) { return $stmtData->fetch(); } else { $root = new stdClass(); $root->total = $total; $root->items = $stmtData->fetchAll(); return $root; } } protected function getFilterSql($filters, &$sqlParams) { $resultSql = []; $currDate = Date("Ymd"); $sqlParams[':dateType'] = -1; $sqlParams[':dateFrom'] = dbDate(Date(19000101)); $sqlParams[':dateTo'] = dbDate(Date(30000101)); foreach ($filters as $filter) { switch ($filter->property) { case 'searchText': $sqlParams[':searchText'] = "%" . $filter->inputValue . "%"; $tempSql = <<<EOT
AND (-- serial||number
       MHN.DocumentSeries || MHN.DocumentNumber like :searchText
       -- patient
       /*OR FN_cmFIO(MP.ManCode) like :searchText*/
       -- doctorOpen
       OR (:dateType != 1 AND FN_cmFIO((SELECT ManCode
                      FROM MedDoctor
                      WHERE MedDoctorCode = MH.MedDoctorOpenCode)) LIKE :searchText )
       --doctorClose
       OR (:dateType != 0 AND FN_cmFIO((SELECT ManCode
                    FROM MedDoctor
                    WHERE MedDoctorCode = MH.MedDoctorCloseCode)) LIKE :searchText )
       -- address
       /*OR FN_cmCalcAddress(MP.ManCode) LIKE :searchText*/
       -- patientInfo
       /*OR (select asa_trim((SELECT Name
                            FROM Enterprise
                            WHERE EnterpriseCode = MPB_.EnterpriseCode)) || CASE (MPB_.UniDictCodeProfession IS NOT NULL)
                                                                                 WHEN true THEN ', ' || asa_trim((SELECT Value
                                                                                                                  FROM UniDict
                                                                                                                  WHERE UniDictCode = MPB_.UniDictCodeProfession))
                                                                                 WHEN false THEN NULL
                                                                            END::text
           FROM MedPatientBusy MPB_
           WHERE MPB_.medPatientCode =  MH.MedPatientCode
             AND $currDate between MPB_.startdate AND MPB_.finishdate
           ORDER BY MPB_.startDate DESC, MPB_.finishDate DESC
           limit 1) LIKE :searchText*/
        -- infoReciv
        OR FN_cmFIO(MH.ManCodeReceived) LIKE :searchText
        /*or MH.PatientEmployment LIKE :searchText*/
        -- diagnosisClose
        OR (SELECT Cipher1  || ' ' || (SELECT Name
                                       FROM MedDiagnosis
                                       WHERE MedDiagnosisCode = MH.MedDiagnosisFinalCode)
            FROM MedDiagnosis
            WHERE MedDiagnosisCode = MH.MedDiagnosisFinalCode)::text LIKE :searchText
        -- diagnosisOpen
        OR (SELECT Cipher1 || ' ' || (SELECT Name
                                      FROM MedDiagnosis
                                      WHERE MedDiagnosisCode = MH.MedDiagnosisPrimaryCode)
            FROM MedDiagnosis
            WHERE MedDiagnosisCode = MH.MedDiagnosisPrimaryCode)::text LIKE :searchText
      )
EOT;
$resultSql[] = $tempSql; break; case 'datePeriodFrom': $sqlParams[':dateFrom'] = dbDate($filter->inputValue); break; case 'datePeriodTo': $sqlParams[':dateTo'] = dbDate($filter->inputValue); break; case 'dateType': $sqlParams[':dateType'] = $filter->inputValue; $tempSql = ''; switch ($filter->inputValue) { case 0: $tempSql = " AND (MH.StartDate BETWEEN :dateFrom AND :dateTo)"; break; case 1: $tempSql = " AND (COALESCE(MH.FinishDate, 30000101) BETWEEN :dateFrom AND :dateTo)"; break; case 2: $tempSql = " AND (MH.StartDate <= :dateTo AND (MH.FinishDate >= :dateFrom or COALESCE(MH.FinishDate, 30000101) = 30000101))"; break; case 3: $tempSql = " AND (COALESCE(MH.GiveDate, 30000101) BETWEEN :dateFrom AND :dateTo)"; break; } $resultSql[] = $tempSql; break; case 'patient': $sqlParams[':patientCode'] = $filter->inputValue; $tempSql = " AND (MH.MedPatientCode = :patientCode)"; $resultSql[] = $tempSql; break; case 'sex': $sqlParams[':sex'] = $filter->inputValue; $tempSql = " AND (MANP.Sex = :sex)"; $resultSql[] = $tempSql; break; case 'ageStart': $sqlParams[':ageStart'] = $filter->inputValue; $tempSql = "AND (EXTRACT(YEAR FROM age($currDate, MANP.DateOfBirth)) >= :ageStart)"; $resultSql[] = $tempSql; break; case 'ageFinish': $sqlParams[':ageFinish'] = $filter->inputValue; $tempSql = "AND (EXTRACT(YEAR FROM age($currDate, MANP.DateOfBirth)) <= :ageFinish)"; $resultSql[] = $tempSql; break; case 'contingentId': $sqlCond = createSqlInCondition($filter->inputValue); $tempSql = <<<EOT
                    AND EXISTS (
                        SELECT 1
                        FROM MedPatientCard MPC
                        WHERE MPC.MedPatientCode = MH.MedPatientCode
                          AND MPC.MedPrivelegeCode {$sqlCond}
                        LIMIT 1)
                    EOT;
$resultSql[] = $tempSql; break; case 'existDS': $categorySql = ''; foreach ($filters as $f) { if ($f->property === 'category') { $categorySql = 'AND MD.Category = :category'; $sqlParams[':category'] = $f->inputValue; break; } } $tempSql = <<<EOT
 AND (EXISTS (SELECT 1 FROM MedDispensary MD
                WHERE MD.MedPatientCode = MH.MedPatientCode
                    AND COALESCE(MD.FinishDate, 30000101) >= $currDate
                    $categorySql
                    LIMIT 1))
EOT;
$resultSql[] = $tempSql; break; case 'manReceived': $sqlParams[':manReceived'] = $filter->inputValue; $tempSql = "AND (MH.ManCodeReceived = :manReceived)"; $resultSql[] = $tempSql; break; case 'workOrg': $sqlParams[':workOrg'] = $filter->inputValue; $profSql = ''; foreach ($filters as $f) { if ($f->property === 'profession') { $profSql = 'AND MPD.UniDictCodeProfession = :profession'; $sqlParams[':profession'] = $f->inputValue; break; } } $tempSql = <<<EOT
 AND (EXISTS (SELECT 1 FROM MedPatientBusy MPD
                WHERE MPD.MedPatientCode = MH.MedPatientCode
                    AND $currDate between MPD.StartDate and MPD.FinishDate
                    AND MPD.EnterpriseCode = :workOrg
                    $profSql
                    LIMIT 1))
EOT;
$resultSql[] = $tempSql; break; case 'profession': $workOrgEx = false; foreach ($filters as $f) { if ($f->property === 'workOrg') { $workOrgEx = true; break; } } if (!$workOrgEx) { $tempSql = <<<EOT
 AND (EXISTS (SELECT 1 FROM MedPatientBusy MPD
                WHERE MPD.MedPatientCode = MH.MedPatientCode
                    AND $currDate between MPD.StartDate and MPD.FinishDate
                    AND MPD.UniDictCodeProfession = :profession
                    LIMIT 1))
EOT;
$sqlParams[':profession'] = $filter->inputValue; $resultSql[] = $tempSql; } break; case 'officeMain': $tempSql = <<<EOT
AND (ES.enterpriseStructureParentCode = :officeUnitId)
EOT;
$sqlParams[':officeUnitId'] = $filter->inputValue; $resultSql[] = $tempSql; break; case 'office': $tempSql = <<<EOT
AND (ES.enterpriseStructureCode = :officeId)
EOT;
$sqlParams[':officeId'] = $filter->inputValue; $resultSql[] = $tempSql; break; case 'typeDocument': $tempSql = <<<EOT
AND (MH.HospitalType = :typeDocument)
EOT;
$sqlParams[':typeDocument'] = $filter->inputValue; $resultSql[] = $tempSql; break; case 'statusOpen': $tempSql = ''; if ($filter->inputValue === 1) { $tempSql = <<<EOT
AND (MH.FinishDate IS NULL)
EOT;
} else { $tempSql = <<<EOT
AND (MH.FinishDate IS NOT NULL)
EOT;
} $resultSql[] = $tempSql; break; case 'diagnosisId': $sqlCond = createSqlInCondition($filter->inputValue); $tempSql = <<<EOT
AND ((MH.MedDiagnosisPrimaryCode $sqlCond) OR (MH.MedDiagnosisFinalCode $sqlCond))
EOT;
$resultSql[] = $tempSql; break; case 'doctorId': $tempSql = <<<EOT
AND (MH.MedDoctorOpenCode = :doctorId OR MH.MedDoctorCloseCode = :doctorId)
EOT;
foreach ($filters as $f) { if ($f->property === 'role') { if ($f->inputValue === 1) { $tempSql = <<<EOT
AND (MH.MedDoctorOpenCode = :doctorId)
EOT;
} else { $tempSql = <<<EOT
AND (MH.MedDoctorCloseCode = :doctorId)
EOT;
} break; } } $sqlParams[':doctorId'] = $filter->inputValue; $resultSql[] = $tempSql; break; case 'diagnosisTermId': $tempSql = <<<EOT
AND (MH.MedDiagnosisTerminalCode = :diagnosisTermId)
EOT;
$sqlParams[':diagnosisTermId'] = $filter->inputValue; $resultSql[] = $tempSql; break; case 'markId': $sqlCond = createSqlInCondition($filter->inputValue); $tempSql = <<<EOT
AND (EXISTS(
        SELECT 1
        FROM MedHospitalMark MHM
        WHERE MHM.MedHospitalCode = MH.MedHospitalCode
        AND MHM.UniDictCodeMark $sqlCond
        LIMIT 1))
EOT;
$resultSql[] = $tempSql; break; } } if ($sqlParams[':dateType'] === -1) { unset($sqlParams[':dateFrom']); unset($sqlParams[':dateTo']); } if (empty($sqlParams[':searchText'])) { unset($sqlParams[':dateType']); } if (empty($resultSql)) { return ''; } $result = implode(' ', $resultSql); return $result; } public function setVerified($params) { $db = $this->db ? : $this->db = connectDb(); $checked = isset($params->checked) && isEmptyProperty($params, 'checked') ? $params->checked : 0; $records = isset($params->records) && isEmptyProperty($params, 'records') ? $params->records : 0; $in = createSqlInCondition($records); $sql = <<<"EOT"
update MedHospital set
       Checked = $checked
where MedHospitalCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readUniDictData() { $uniDict = new med_sickdays_marks(); $dictRows = $uniDict->readMarks(); $ret = []; foreach ($dictRows as $row) { $item = new stdClass(); $item->id = $row['id']; $item->name = $row['name']; $item->markType = (int) $row['markType']; $item->important = (int) $row['important']; $ret[] = $item; } return $ret; } private function readMarks($id) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
       MHM.MedHospitalmarkCode "id",
       MHM.UniDictcodeMark "markId",
       UD.Value "markName",
       MHM.MarkDate "markDate",
       MHM.MedDoctorCode "doctorId",
       FN_cmFIO((select ManCode
                 from MedDoctor
                 where MedDoctorCode = MHM.MedDoctorCode)) "doctor",
       COALESCE(UD.ValueInteger, 0) "important",
       MHM.DurationContinuous "contCount",
       MHM.DurationTotal "sumCount",
       MHM.Information "information"
from medhospitalmark MHM
     left join unidict UD on UD.unidictcode = MHM.unidictcodemark
where medhospitalcode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } private function readExtensions($id) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
       MHE.MedHospitalExtensionCode "id",
       MHE.MedDoctorCode "doctorId",
       FN_cmFIO(MD.ManCode) "doctor",
       MHE.ToDate "toDate",
       MHE.MeetKind "meetKindId"
from MedHospital MH
     join MedHospitalExtension MHE on MHE.MedHospitalCode = MH.MedHospitalCode
     left join MedDoctor MD on MD.MedDoctorCode = MHE.MedDoctorCode
where MH.MedHospitalCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readDiagnosisTerminal(?object $params = null): array|object { $db = $this->db ?: $this->db = connectDb(); $diagnosisId = (int)isUndefinedProperty($params, 'diagnosisId', 0); $disabilityTypeId = isUndefinedProperty($params, 'disabilityTypeId', 0); $sql = ''; if ($diagnosisId > 0) { $sql = <<<SQL
                SELECT
                    MDT.MedDiagnosisTerminalCode            "id",
                    MDT.Cipher || ' - ' || MDT.Name         "name",
                    MDT.UniDictCodeTempDisabilityType       "disabilityType",
                    (CASE WHEN MDTI.MedDiagnosisTerminalInCode IS NULL THEN 0 ELSE 1 END)
                                                            "existDiagnosis",
                    STRING_AGG(MDTDT.UniDictCodeTempDisabilityType, ',')
                                                            "typeIds",
                    NULL                                    "linkedDiagnosisIds"
                FROM MedDiagnosisTerminal MDT
                LEFT JOIN MedDiagnosisTerminalIn MDTI ON MDTI.MedDiagnosisTerminalCode = MDT.MedDiagnosisTerminalCode
                LEFT JOIN MedDiagnosisTerminalDisabilityType MDTDT ON MDTDT.MedDiagnosisTerminalCode = MDT.MedDiagnosisTerminalCode
                WHERE (
                        :diagnosisId = 0
                        OR MDTI.MedDiagnosisCode = :diagnosisId
                        OR :diagnosisId IN (
                            SELECT D2.MedDiagnosisCode
                            FROM MedDiagnosis D1, MedDiagnosis D2
                            WHERE D2.TreePath >= (D1.TreePath || INTTOHEX(D1.MedDiagnosisCode))
                                AND D2.TreePath < INCHEXSTR(D1.TreePath || INTTOHEX(D1.MedDiagnosisCode))
                                AND D1.MedDiagnosisCode = MDTI.MedDiagnosisCode
                        )
                    )
                    -- Если diagnosisId задан, исключаем терминалы без привязки
                    AND (:diagnosisId = 0 OR MDTI.MedDiagnosisTerminalInCode IS NOT NULL)
                    -- Фильтр по виду ВН (через прямое поле или таблицу соответствий)
                    AND (:disabilityTypeId = 0
                            OR MDT.UnidictCodeTempDisabilityType = :disabilityTypeId
                            OR MDTDT.UniDictCodeTempDisabilityType = :disabilityTypeId)
                GROUP BY
                    MDT.MedDiagnosisTerminalCode,
                    MDT.Cipher,
                    MDT.Name,
                    MDT.UniDictCodeTempDisabilityType,
                    MDTI.MedDiagnosisTerminalInCode
                ORDER BY FN_cmPadNumber(MDT.Cipher) || MDT.Name
                SQL;
} else { $sql = <<<SQL
                SELECT
                    MDT.MedDiagnosisTerminalCode            "id",
                    MDT.Cipher || ' - ' || MDT.Name         "name",
                    MDT.UniDictCodeTempDisabilityType       "disabilityType",
                    (CASE WHEN MDTI.MedDiagnosisTerminalInCode IS NULL THEN 0 ELSE 1 END)
                                                            "existDiagnosis",
                    DT.TypeIds                              "typeIds",
                    MDTI.ReasonDiagnosisIds                 "linkedDiagnosisIds"
                FROM MedDiagnosisTerminal MDT
                LEFT JOIN (
                    SELECT
                        MedDiagnosisTerminalCode,
                        MIN(MedDiagnosisTerminalInCode) AS MedDiagnosisTerminalInCode,
                        STRING_AGG(MedDiagnosisCode::TEXT, ',') AS ReasonDiagnosisIds
                    FROM MedDiagnosisTerminalIn
                    GROUP BY MedDiagnosisTerminalCode
                ) MDTI ON MDTI.MedDiagnosisTerminalCode = MDT.MedDiagnosisTerminalCode
                LEFT JOIN (
                    SELECT
                        MedDiagnosisTerminalCode,
                        STRING_AGG(UniDictCodeTempDisabilityType::TEXT, ',') AS typeIds
                    FROM MedDiagnosisTerminalDisabilityType
                    GROUP BY MedDiagnosisTerminalCode
                ) DT ON DT.MedDiagnosisTerminalCode = MDT.MedDiagnosisTerminalCode
                WHERE (
                        :diagnosisId = 0
                        OR :diagnosisId = ANY(string_to_array(MDTI.ReasonDiagnosisIds, ',')::int[])
                        OR :diagnosisId IN (
                            SELECT D2.MedDiagnosisCode
                            FROM MedDiagnosis D1, MedDiagnosis D2
                            WHERE D2.TreePath >= (D1.TreePath || INTTOHEX(D1.MedDiagnosisCode))
                                AND D2.TreePath < INCHEXSTR(D1.TreePath || INTTOHEX(D1.MedDiagnosisCode))
                                AND D1.MedDiagnosisCode = ANY(string_to_array(MDTI.ReasonDiagnosisIds, ',')::int[])
                        )
                    )
                    -- Если diagnosisId задан, исключаем терминалы без привязки
                    AND (:diagnosisId = 0 OR MDTI.MedDiagnosisTerminalInCode IS NOT NULL)
                    -- Фильтр по виду ВН (через прямое поле или таблицу соответствий)
                    AND (:disabilityTypeId = 0 OR MDT.UniDictCodeTempDisabilityType = :disabilityTypeId)
                ORDER BY FN_cmPadNumber(MDT.Cipher) || MDT.Name
                SQL;
} $stmt = $db->prepare($sql); $stmt->bindValue(':diagnosisId', (int)$diagnosisId, PDO::PARAM_INT); $stmt->bindValue(':disabilityTypeId', (int)$disabilityTypeId, PDO::PARAM_INT); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readManReceived($params) { $db = $this->db ? : $this->db = connectDb(); if (!isset($params->onlyAlive)) { $params->onlyAlive = true; } $patientInfo = new med_common_patients(); return $patientInfo->readPatientRelatives($params); } public function readBusy($params) { $db = $this->db ? : $this->db = connectDb(); $patientId = isUndefinedProperty($params, 'patientId', 0); $receiverId = isUndefinedProperty($params, 'receiverId', 0); $currDate = Date('Ymd'); if ($receiverId) { $sql = <<<"EOT"
SELECT DISTINCT
    MH.PatientEmployment "professionName"
FROM MedHospital MH
WHERE MH.MedPatientCode = $patientId
AND MH.ManCodeReceived = $receiverId
EOT;
} else { $sql = <<<"EOT"
SELECT string_agg(
    E.Name || CASE
                 WHEN UD.Value IS NOT NULL THEN ', ' || UD.Value
                 ELSE ''
              END,
    ', '
) AS "professionName"
FROM MedPatientBusy MB
LEFT JOIN Enterprise as E ON MB.EnterpriseCode = E.EnterpriseCode
LEFT JOIN UniDict UD ON UD.UniDictCode = MB.UniDictCodeProfession
WHERE MB.MedPatientCode = $patientId
    AND $currDate BETWEEN MB.StartDate AND MB.FinishDate
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readSheetData($params) { $db = $this->db ? : $this->db = connectDb(); $hospitalId = isEmptyProperty($params, 'hospitalId') ? $params->hospitalId : null; $hospitalIdCond = $hospitalId ? "and MH.MedHospitalCode = $hospitalId" : ""; $patientId = isEmptyProperty($params, 'patientId'); $patientCond = $patientId ? "and MH.MedpatientCode = $patientId" : ""; $startDate = isEmptyProperty($params, 'startDate') ? $params->startDate : null; $dateCond = $startDate ? "and MH.StartDate <= $startDate and COALESCE(MH.FinishDate, 30000101) = 30000101" : ""; $limit = $hospitalId ? "" : "order by 1 limit 1"; if ($hospitalId && $patientId && $startDate) { $sqlIsExist = <<< "EOT"
        select
            MH.medHospitalCode
        from medHospital MH
        where 1=1
        $hospitalIdCond
EOT;
$stmtIsExist = $db->prepare($sqlIsExist); $stmtIsExist->execute(); if ($stmtIsExist->fetch()) { $dateCond = ""; } else { $hospitalIdCond = ""; } } $currDate = Date("Ymd"); $sql = <<<"EOT"
select
       MH.MedHospitalCode "id",
       MH.HospitalType "type",
       MH.Checked "checked",
       MHN.DocumentSeries "documentSeries",
       MHN.DocumentNumber "documentNumber",
       MHN.StartDate "startDate",
       MHN.TreatmentMode "treatmentId",
       MHN.MedHospitalNextCode "nextId",
    -- prev begin
       CASE MH.MedHospitalCodePrior IS NOT NULL
            WHEN true THEN MH.MedHospitalCodePrior
            WHEN false THEN MHN_Prior.MedHospitalNextCode
       END "id_prior",
       MHN_Prior.DocumentSeries "documentSeries_prior",
       MHN_Prior.DocumentNumber "documentNumber_prior",
       MHN_Prior.StartDate "startDate_prior",
       MHN_Prior.TreatmentMode "treatmentId_prior",
       MHN_Prior.PriorNumber "number_prior",
    -- prev end
    -- patietn begin
       MH.MedPatientCode "patientId",
       FN_cmFIO(MP.ManCode) "patientFio",
       (select asa_trim((select Name
                         from Enterprise
                         where EnterpriseCode = MPB_.EnterpriseCode)) || CASE (MPB_.UniDictCodeProfession IS NOT NULL)
                                                                              WHEN true THEN ', ' || asa_trim((select Value
                                                                                                               from UniDict
                                                                                                               where UniDictCode = MPB_.UniDictCodeProfession))
                                                                              WHEN false THEN NULL
                                                                         END::text
        from MedPatientBusy MPB_
        where MPB_.medPatientCode =  MH.MedPatientCode
          and $currDate between MPB_.startdate and MPB_.finishdate
        order by MPB_.startDate DESC, MPB_.finishDate DESC
        limit 1) "patientBusy",
    -- patietn end
    -- manReceived begin
       MH.PatientEmployment "busy",
       MH.ManCodeReceived "manIdReceived",
       FN_cmFIO(MH.ManCodeReceived) "fioReceived",
    -- manReceived end
    -- open begin
       MH.StartDate "openDate",
       MH.MedDiagnosisPrimaryCode "openDiagnosisId",
       CASE WHEN MH.MedDiagnosisPrimaryCode IS NOT NULL THEN MD.Cipher1 || ' - ' || MD.Name  ELSE '' END "openDiagnosisName",
       MH.MedDoctorOpenCode "openDoctorId",
       MH.GiveDate "giveDate",
       MH.StartHospitalDate "startHospitalDate",
       COALESCE(MH.MeetKindStart, 0) "meetKindStartId",
    -- open end
    -- close begin
       MH.FinishDate "closeDate",
       MH.MedDiagnosisFinalCode "closeDiagnosisId",
       CASE WHEN MH.MedDiagnosisFinalCode IS NOT NULL THEN MD2.Cipher1 || ' - ' || MD2.Name  ELSE '' END "closeDiagnosisName",
       MH.MedDoctorCloseCode "closeDoctorId",
       MH.CasesCount "casesCount",
       MH.HospitalDay "hospitalDay",
       COALESCE(MH.MeetKindFinish, 0) "meetKindFinishId",
       MH.MedDiagnosisTerminalCode "diagnosisTerminalId",
       MH.UnidictCodeTempDisabilityType "codeTempDisabilityType",
       MH.Result "resultId",
       MH.WorkStartDate "workStartDate",
       MH.ConclusionMrec "conclusionMrec",
       MH.FinishDateMse "finishDateMse",
       MH.MedDoctorCodeChiefMrec "medDoctorCodeMrec",
       MH.StartDateMse "startDateMse",
       ( CASE WHEN MH.MedDoctorCodeChiefMrec IS NOT NULL
             THEN FN_cmFIO(MDC.ManCode)
             ELSE MH.MedDoctorChiefMrec_fio
             END ) "medDoctorCodeMrecFio",
    -- close end
       CASE EXISTS(select MedHospitalCode
                   from MedHospital
                   where MedHospitalCodePrior = MH.MedHospitalCode
                   limit 1)
            WHEN true THEN 1
            WHEN false THEN 0
       END "isExistsNext",
       MH.MedDoctorCodeChief "chiefId",
       MPC.PersonalNumber "personalNumberChief",
       CDE.CiszDiagnosisExtraCode "diagnosisExtraOpenId",
       CDE.ClinicalDiagnosis "clinicalOpenDiagnosis",
       CDE.Note "noteOpenDiag",
       CDE.DiagnosisDate "diagnosisOpenDate",
       CDE.MedDoctorCode "diagnosisDoctorOpenId",
       CDEA2.AnatomicCodes "anatomicOpen",
       CDE.CiszResourceValueCodeCancerStage "cancerStageOpenId",
       CDE.CiszResourceValueCodeDiagKind "kindDiagOpenId",

       CDEC.CiszDiagnosisExtraCode "diagnosisExtraCloseId",
       CDEC.ClinicalDiagnosis "clinicalCloseDiagnosis",
       CDEC.Note "noteCloseDiag",
       CDEC.DiagnosisDate "diagnosisCloseDate",
       CDEC.MedDoctorCode "diagnosisDoctorCloseId",
       CDEA2C.AnatomicCodes "anatomicClose",
       CDEC.CiszResourceValueCodeCancerStage "cancerStageCloseId",
       CDEC.CiszResourceValueCodeDiagKind "kindDiagCloseId"
from MedHospital MH
     LEFT JOIN CiszDiagnosisExtra CDE ON CDE.CiszDiagnosisExtraCode = MH.MedDiagnosisExtraCodePrimary
     LEFT JOIN CiszDiagnosisExtra CDEC ON CDEC.CiszDiagnosisExtraCode = MH.MedDiagnosisExtraCodeFinal
     LEFT JOIN (
         SELECT
             CDEA.CiszDiagnosisExtraCode,
             string_agg(
                 CDEA.CiszResourceValueCodeAnatomic, ';'
                 ORDER BY CDEA.CiszDiagnosisExtraCode
             ) AS AnatomicCodes
         FROM CiszDiagnosisExtraAnatomic CDEA
         GROUP BY CDEA.CiszDiagnosisExtraCode
     ) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
     LEFT JOIN (
         SELECT
             CDEA.CiszDiagnosisExtraCode,
             string_agg(
                 CDEA.CiszResourceValueCodeAnatomic, ';'
                 ORDER BY CDEA.CiszDiagnosisExtraCode
             ) AS AnatomicCodes
         FROM CiszDiagnosisExtraAnatomic CDEA
         GROUP BY CDEA.CiszDiagnosisExtraCode
     ) CDEA2C ON CDEA2C.CiszDiagnosisExtraCode = CDEC.CiszDiagnosisExtraCode                    
     left join MedHospitalNext MHN on MHN.MedHospitalCode = MH.MedHospitalCode
                                  and MHN.Position = 0
     left join MedPatient MP on MP.MedPatientCode = MH.MedPatientCode
     left join ManPassport MANP on MANP.ManCode = MP.ManCode
     LEFT JOIN MedDoctor MDC ON MDC.MedDoctorCode = MH.MedDoctorCodeChiefMrec
     LEFT JOIN MedDoctor MDCO ON MDCO.MedDoctorCode = MH.MedDoctorCodeChief
     LEFT JOIN ManPassport MPC ON MPC.ManCode = MDCO.ManCode
     left join lateral (select
                               MHN_.MedHospitalNextCode,
                               MHN_.DocumentSeries,
                               MHN_.DocumentNumber,
                               MHN_.StartDate,
                               MHN_.TreatmentMode,
                               MHN_.PriorNumber
                        from MedHospitalNext MHN_
                        where MHN_.MedHospitalCode = CASE MH.MedHospitalCodePrior IS NOT NULL
                                                          WHEN true THEN MH.MedHospitalCodePrior
                                                          WHEN false THEN MH.MedHospitalCode
                                                     END
                          and (MH.MedHospitalCodePrior IS NOT NULL or (MH.MedHospitalCodePrior IS NULL
                                                                       and MHN_.PriorNumber IS NOT NULL  and MHN_.Position > 0))
                        order by MHN_.Position
                        limit 1) MHN_Prior on TRUE
     LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MH.MedDiagnosisPrimaryCode
     LEFT JOIN MedDiagnosis MD2 ON MD2.MedDiagnosisCode = MH.MedDiagnosisFinalCode
where 1 = 1
$hospitalIdCond
$patientCond
$dateCond
$limit
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetch(); if (!$ret && $hospitalIdCond === "") { $ret = []; $par = new stdClass(); $par->patientId = $patientId; $par->hospitalId = 0; $ret += ['receivedData' => $this->readManReceived($par)]; $ret += ['uniDictData' => $this->readUniDictData()]; $ret += ['isNewList' => true]; return $ret; } $selectHospitalId = $hospitalId ? $hospitalId : $ret['id']; $ret += ['uniDictData' => $this->readUniDictData()]; $ret += ['marksData' => $this->readMarks($selectHospitalId)]; $ret += ['extensionsData' => $this->readExtensions($selectHospitalId)]; $par = new stdClass(); $par->patientId = $ret['patientId']; $par->hospitalId = $selectHospitalId; $ret += ['receivedData' => $this->readManReceived($par)]; return $ret; } public function updateSheetData($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update medHospital set
       hospitalType = case when ? = 1 then COALESCE(?, 0) else hospitalType end,
       medPatientCode = case when ? = 1 then ? else medPatientCode end,
       medDoctorOpenCode = case when ? = 1 then ? else medDoctorOpenCode end,
       medDoctorCloseCode = case when ? = 1 then ? else medDoctorCloseCode end,
       startDate = case when ? = 1 then ? else startDate end,
       finishDate = case when ? = 1 then ? else finishDate end,
       hospitalDay = case when ? = 1 then ? else hospitalDay end,
       medDiagnosisPrimaryCode = case when ? = 1 then ? else medDiagnosisPrimaryCode end,
       medDiagnosisFinalCode = case when ? = 1 then ? else medDiagnosisFinalCode end,
       medDiagnosisTerminalCode = case when ? = 1 then ? else medDiagnosisTerminalCode end,
       giveDate = case when ? = 1 then ? else giveDate end,
       casesCount = case when ? = 1 then ? else casesCount end,
       startHospitalDate = case when ? = 1 then ? else startHospitalDate end,
       manCodeReceived = case when ? = 1 then ? else manCodeReceived end,
       result = case when ? = 1 then ? else result end,
       workStartDate = case when ? = 1 then ? else workStartDate end,
       patientEmployment = case when ? = 1 then ? else patientEmployment end,
       meetKindStart = case when ? = 1 then ? else meetKindStart end,
       meetKindFinish = case when ? = 1 then ? else meetKindFinish end,
       medHospitalCodePrior = case when ? = 1 then ? else medHospitalCodePrior end,
       UnidictCodeTempDisabilityType = CASE WHEN ? = 1 THEN ? ELSE UnidictCodeTempDisabilityType END,
       ConclusionMrec = CASE WHEN ? = 1 THEN ? ELSE ConclusionMrec END,
       FinishDateMse = CASE WHEN ? = 1 THEN ? ELSE FinishDateMse END,
       MedDoctorCodeChiefMrec = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCodeChiefMrec END,
       StartDateMse = CASE WHEN ? = 1 THEN ? ELSE StartDateMse END,
       MedDoctorChiefMrec_fio = CASE WHEN ? = 1 THEN ? ELSE MedDoctorChiefMrec_fio END,
       MedDoctorCodeChief = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCodeChief END,
       MedDiagnosisExtraCodePrimary = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisExtraCodePrimary END,
       MedDiagnosisExtraCodeFinal = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisExtraCodeFinal END
where medHospitalCode = ?
EOT;
$sqlExctraDiagnosis = <<<SQL
        UPDATE CiszDiagnosisExtra SET
            CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END,
            CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
            ClinicalDiagnosis = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
            Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
            DiagnosisDate = CASE WHEN ? = 1 THEN ? ELSE DiagnosisDate END,
            MedDoctorCode = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END
        WHERE CiszDiagnosisExtraCode = ?
        SQL;
$stmt = $db->prepare($sql); $stmtDiagnosis = $db->prepare($sqlExctraDiagnosis); if (is_object($records)) { $records = [$records]; } $manId = 0; $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if (isset($row->fioReceived) && !isset($row->receivedId) && $row->fioReceived !== "") { $row->manIdReceived = $this->createMan($row->fioReceived); } if (isset($row->fioReceived) && isset($row->receivedId) && $row->fioReceived !== "") { $row->manIdReceived = $row->receivedId; } if (isset($row->receivedUsed) && $row->receivedUsed === 0) { $manId = $this->getCurrentManId($row->id); } if (isset($row->openDiagnosis) && $row->openDiagnosis->diagnosisName) { if ($row->openDiagnosis->id) { $stmtDiagnosis->execute([ propertyExistsSQL($row->openDiagnosis, 'cancerStageId'), isEmptyProperty($row->openDiagnosis, 'cancerStageId'), propertyExistsSQL($row->openDiagnosis, 'kindDiagId'), isEmptyProperty($row->openDiagnosis, 'kindDiagId'), propertyExistsSQL($row->openDiagnosis, 'clinicalDiagnosis'), isUndefinedProperty($row->openDiagnosis, 'clinicalDiagnosis'), propertyExistsSQL($row->openDiagnosis, 'note'), isUndefinedProperty($row->openDiagnosis, 'note'), propertyExistsSQL($row->openDiagnosis, 'diagnosisDate'), isUndefinedProperty($row->openDiagnosis, 'diagnosisDate'), propertyExistsSQL($row->openDiagnosis, 'diagnosisDoctorId'), isEmptyProperty($row->openDiagnosis, 'diagnosisDoctorId'), $row->openDiagnosis->id ]); $row->diagnosisOpenExtraId = $row->openDiagnosis->id; } else { $openDiagnosis = clone $row->openDiagnosis; $openDiagnosis->cancerStageId = isEmptyProperty($openDiagnosis, 'cancerStageId'); $openDiagnosis->kindDiagId = isEmptyProperty($openDiagnosis, 'kindDiagId'); $openDiagnosis->diagnosisDoctorId = isEmptyProperty($openDiagnosis, 'diagnosisDoctorId'); $diagnosisExtraOpen = insertSql($db, 'CiszDiagnosisExtra', $openDiagnosis, "CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note,
                        DiagnosisDate, MedDoctorCode", "cancerStageId, kindDiagId, clinicalDiagnosis, note,
                        diagnosisDate, diagnosisDoctorId" ); $row->diagnosisOpenExtraId = $diagnosisExtraOpen[0]->id ?? null; } if (isset($row->openDiagnosis->anatomic) && !empty($row->diagnosisOpenExtraId)) { $this->updateAnatomic((object)[ 'id' => $row->diagnosisOpenExtraId, 'items' => $row->openDiagnosis->anatomic ]); } } if (isset($row->closeDiagnosis) && $row->closeDiagnosis->diagnosisName) { if ($row->closeDiagnosis->id) { $stmtDiagnosis->execute([ propertyExistsSQL($row->closeDiagnosis, 'cancerStageId'), isEmptyProperty($row->closeDiagnosis, 'cancerStageId'), propertyExistsSQL($row->closeDiagnosis, 'kindDiagId'), isEmptyProperty($row->closeDiagnosis, 'kindDiagId'), propertyExistsSQL($row->closeDiagnosis, 'clinicalDiagnosis'), isUndefinedProperty($row->closeDiagnosis, 'clinicalDiagnosis'), propertyExistsSQL($row->closeDiagnosis, 'note'), isUndefinedProperty($row->closeDiagnosis, 'note'), propertyExistsSQL($row->closeDiagnosis, 'diagnosisDate'), isUndefinedProperty($row->closeDiagnosis, 'diagnosisDate'), propertyExistsSQL($row->closeDiagnosis, 'diagnosisDoctorId'), isEmptyProperty($row->closeDiagnosis, 'diagnosisDoctorId'), $row->closeDiagnosis->id ]); $row->diagnosisCloseExtraId = $row->closeDiagnosis->id; } else { $closeDiagnosis = clone $row->closeDiagnosis; $closeDiagnosis->cancerStageId = isEmptyProperty($closeDiagnosis, 'cancerStageId'); $closeDiagnosis->kindDiagId = isEmptyProperty($closeDiagnosis, 'kindDiagId'); $closeDiagnosis->diagnosisDoctorId = isEmptyProperty($closeDiagnosis, 'diagnosisDoctorId'); $diagnosisExtraClose = insertSql($db, 'CiszDiagnosisExtra', $closeDiagnosis, "CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note,
                        DiagnosisDate, MedDoctorCode", "cancerStageId, kindDiagId, clinicalDiagnosis, note,
                        diagnosisDate, diagnosisDoctorId" ); $row->diagnosisCloseExtraId = (int)$diagnosisExtraClose[0]->id ?? null; } if (isset($row->closeDiagnosis->anatomic) && !empty($row->diagnosisCloseExtraId)) { $this->updateAnatomic((object)[ 'id' => $row->diagnosisCloseExtraId, 'items' => $row->closeDiagnosis->anatomic ]); } } $stmt->execute([ propertyExistsSQL($row, 'type'), isEmptyProperty($row, 'type'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), propertyExistsSQL($row, 'openDoctorId'), isEmptyProperty($row, 'openDoctorId'), propertyExistsSQL($row, 'closeDoctorId'), isEmptyProperty($row, 'closeDoctorId'), propertyExistsSQL($row, 'openDate'), isUndefinedProperty($row, 'openDate'), propertyExistsSQL($row, 'closeDate'), isUndefinedProperty($row, 'closeDate'), propertyExistsSQL($row, 'hospitalDay'), isUndefinedProperty($row, 'hospitalDay', 0), propertyExistsSQL($row, 'openDiagnosisId'), isEmptyProperty($row, 'openDiagnosisId'), propertyExistsSQL($row, 'closeDiagnosisId'), isEmptyProperty($row, 'closeDiagnosisId'), propertyExistsSQL($row, 'diagnosisTerminalId'), isEmptyProperty($row, 'diagnosisTerminalId'), propertyExistsSQL($row, 'giveDate'), isUndefinedProperty($row, 'giveDate'), propertyExistsSQL($row, 'casesCount'), isUndefinedProperty($row, 'casesCount', 0), propertyExistsSQL($row, 'startHospitalDate'), isUndefinedProperty($row, 'startHospitalDate'), propertyExistsSQL($row, 'manIdReceived'), isEmptyProperty($row, 'manIdReceived'), propertyExistsSQL($row, 'resultId'), isUndefinedProperty($row, 'resultId'), propertyExistsSQL($row, 'workStartDate'), isUndefinedProperty($row, 'workStartDate'), propertyExistsSQL($row, 'busy'), isUndefinedProperty($row, 'busy'), propertyExistsSQL($row, 'meetKindStartId'), isUndefinedProperty($row, 'meetKindStartId'), propertyExistsSQL($row, 'meetKindFinishId'), isUndefinedProperty($row, 'meetKindFinishId'), isEmptyProperty($row, 'number_prior') ? 0 : propertyExistsSQL($row, 'id_prior'), isUndefinedProperty($row, 'id_prior'), propertyExistsSQL($row , 'codeTempDisabilityType'), isUndefinedProperty($row , 'codeTempDisabilityType'), propertyExistsSQL($row , 'conclusionMrec'), isUndefinedProperty($row , 'conclusionMrec'), propertyExistsSQL($row , 'finishDateMse'), isUndefinedProperty($row , 'finishDateMse'), propertyExistsSQL($row , 'medDoctorCodeMrec'), isUndefinedProperty($row , 'medDoctorCodeMrec'), propertyExistsSQL($row , 'startDateMse'), isUndefinedProperty($row , 'startDateMse'), propertyExistsSQL($row, 'medDoctorCodeMrecFio'), isUndefinedProperty($row, 'medDoctorCodeMrecFio'), propertyExistsSQL($row, 'chiefId'), isUndefinedProperty($row, 'chiefId'), propertyExistsSQL($row, 'diagnosisOpenExtraId'), isUndefinedProperty($row, 'diagnosisOpenExtraId'), propertyExistsSQL($row, 'diagnosisCloseExtraId'), isUndefinedProperty($row, 'diagnosisCloseExtraId'), $row->id ]); if ($manId) { $this->deleteMan($manId); } $this->updateSheetNext($row); switch (true) { case (isset($row->id_prior)) && (isset($row->number_prior)) && (!isset($row->documentSeries_prior) || $row->documentSeries_prior === '') && (!isset($row->documentNumber_prior) || $row->documentNumber_prior === ''): $this->deleteSheetNext($row->id_prior); break; case!isset($row->id_prior) && ((isset($row->documentSeries_prior) && $row->documentSeries_prior !== '') || (isset($row->documentNumber_prior) && $row->documentNumber_prior !== '')): $this->createSheetNext($row, false); break; case isset($row->id_prior) && isset($row->number_prior): $this->updateSheetNext($row, false); break; } $extensions = isset($row->extensionsIUD) ? $row->extensionsIUD : null; if ($extensions) { $this->syncExtensions($extensions, $row->id); } $marks = isset($row->marksIUD) ? $row->marksIUD : null; if ($marks) { $this->syncMarks($marks, $row->id); } $params = new stdClass(); if (isEmptyProperty($row, 'isHTML')) { $params->hospitalId = $row->id; $ret[] = (object) $this->readSheetData($params); } else { $params->currId = $row->id; $ret[] = (object) $this->readSheets($params); } } executeSql($db, <<<SQL
                UPDATE CiszLink SET
                   Status = :state
                WHERE
                    PrimaryKey = :id AND TableName = :tableName
            SQL,
[ ':state' => 2, ':id' => $row->id, ':tableName' => 'temporaryDisability' ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createSheetData($records) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
insert into medHospital
    (EnterpriseCode, HospitalType, MedPatientCode, MedDoctorOpenCode, MedDoctorCloseCode, StartDate, FinishDate, HospitalDay, MedDiagnosisPrimaryCode,
     MedDiagnosisFinalCode, MedDiagnosisTerminalCode, GiveDate, CasesCount, StartHospitalDate, ManCodeReceived, Result, WorkStartDate, MeetKindStart,
     MeetKindFinish, PatientEmployment, medHospitalCodePrior, UnidictCodeTempDisabilityType, ConclusionMrec, FinishDateMse, MedDoctorCodeChiefMrec, StartDateMse,
     MedDoctorChiefMrec_fio, MedDoctorCodeChief, MedDiagnosisExtraCodePrimary, MedDiagnosisExtraCodeFinal)
values
    ($enterpriseId, COALESCE(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $manId = 0; $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if (isset($row->fioReceived) && !isset($row->receivedId) && $row->fioReceived !== "") { $row->manIdReceived = $this->createMan($row->fioReceived); } if (isset($row->fioReceived) && isset($row->receivedId) && $row->fioReceived !== "") { $row->manIdReceived = $row->receivedId; } if (isset($row->receivedUsed) && $row->receivedUsed === 0) { $manId = $this->getCurrentManId($row->id); } $diagnosisExtraOpen = null; $diagnosisExtraClose = null; if (isset($row->openDiagnosis) && $row->openDiagnosis->diagnosisName) { $diagnosisExtraOpen = insertSql($db, 'CiszDiagnosisExtra', $row->openDiagnosis, "CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note,
                DiagnosisDate, MedDoctorCode", "cancerStageId, kindDiagId, clinicalDiagnosis, note,
                diagnosisDate, diagnosisDoctorId" ); $row->diagnosisOpenExtraId = $diagnosisExtraOpen[0]->id ?? null; } if (isset($row->closeDiagnosis) && $row->closeDiagnosis->diagnosisName) { $diagnosisExtraClose = insertSql($db, 'CiszDiagnosisExtra', $row->closeDiagnosis, "CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note,
                DiagnosisDate, MedDoctorCode", "cancerStageId, kindDiagId, clinicalDiagnosis, note,
                diagnosisDate, diagnosisDoctorId" ); $row->diagnosisCloseExtraId = $diagnosisExtraClose[0]->id ?? null; } if (isset($row->openDiagnosis->anatomic) && !empty($row->diagnosisOpenExtraId)) { $this->updateAnatomic((object)[ 'id' => $row->diagnosisOpenExtraId, 'items' => $row->openDiagnosis->anatomic ]); } if (isset($row->closeDiagnosis->anatomic) && !empty($row->diagnosisCloseExtraId)) { $this->updateAnatomic((object)[ 'id' => $row->diagnosisCloseExtraId, 'items' => $row->closeDiagnosis->anatomic ]); } $stmt->execute([ isEmptyProperty($row, 'type'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'openDoctorId'), isEmptyProperty($row, 'closeDoctorId'), isUndefinedProperty($row, 'openDate'), isUndefinedProperty($row, 'closeDate'), isUndefinedProperty($row, 'hospitalDay', 0), isEmptyProperty($row, 'openDiagnosisId'), isEmptyProperty($row, 'closeDiagnosisId'), isEmptyProperty($row, 'diagnosisTerminalId'), isUndefinedProperty($row, 'giveDate'), isUndefinedProperty($row, 'casesCount', 0), isUndefinedProperty($row, 'startHospitalDate'), isEmptyProperty($row, 'manIdReceived'), isUndefinedProperty($row, 'resultId'), isUndefinedProperty($row, 'workStartDate'), isEmptyProperty($row, 'meetKindStartId'), isEmptyProperty($row, 'meetKindFinishId'), isEmptyProperty($row, 'busy'), isEmptyProperty($row, 'id_prior'), isEmptyProperty($row, 'codeTempDisabilityType'), isUndefinedProperty($row, 'conclusionMrec'), isUndefinedProperty($row, 'finishDateMse'), isUndefinedProperty($row, 'medDoctorCodeMrec'), isUndefinedProperty($row, 'startDateMse'), isUndefinedProperty($row, 'medDoctorCodeMrecFio'), isUndefinedProperty($row, 'chiefId'), isUndefinedProperty($row, 'diagnosisOpenExtraId'), isUndefinedProperty($row, 'diagnosisCloseExtraId') ]); if ($manId) { $this->deleteMan($manId); } $params = new stdClass(); $isHTML = isEmptyProperty($row, 'isHTML'); $row->id = $isHTML ? $params->hospitalId = (int) $db->lastInsertId('seq_medHospital') : $params->currId = (int) $db->lastInsertId('seq_medHospital'); $row->nextId = $this->createSheetNext($row); if (!$row->id_prior && ((isset($row->documentSeries_prior) && $row->documentSeries_prior !== '') || (isset($row->documentNumber_prior) && $row->documentNumber_prior !== ''))) { $this->createSheetNext($row, false); } $extensions = isset($row->extensionsIUD) ? $row->extensionsIUD : null; if ($extensions) { $this->syncExtensions($extensions, $row->id); } $marks = isset($row->marksIUD) ? $row->marksIUD : null; if ($marks) { $this->syncMarks($marks, $row->id); } $ret[] = (object) ($isHTML ? $this->readSheetData($params) : $this->readSheets($params)); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteSheet($records) { $db = $this->db ? : $this->db = connectDb(); $id = $records->id; $priorData = $this->readNextSheetData($id); if ($priorData) { $series = $priorData['series']; $number = $priorData['number']; $date = date_format(date_create_from_format('Y-m-d', $priorData['date']), 'd.m.Y'); throw new Exception("Невозможно удалить документ, так как он связан с последующим: $series $number от $date!"); } $sql = <<<"EOT"
delete from MedHospital
where MedHospitalCode = $id
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function readNextSheetData($id) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
MHN.documentSeries "series",
MHN.documentNumber "number",
MHN.startDate "date"
from MedHospitalNext MHN
left join MedHospital MH ON MH.medhospitalcode = MHN.medhospitalcode
where MH.MedHospitalCodePrior = $id
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(); } private function syncMarks($records, $id) { $db = $this->db ? : $this->db = connectDb(); $sqlInsert = <<<"EOT"
insert into medHospitalMark
    (MedHospitalCode, UniDictCodeMark, MarkDate, MedDoctorCode, DurationContinuous, DurationTotal, Information)
values
    (?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlUpdate = <<<"EOT"
update medHospitalMark set
       UniDictCodeMark = case when ? = 1 then ? else UniDictCodeMark end,
       MarkDate = case when ? = 1 then ? else MarkDate end,
       MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
       DurationContinuous = case when ? = 1 then ? else DurationContinuous end,
       DurationTotal = case when ? = 1 then ? else DurationTotal end,
       Information = case when ? = 1 then ? else Information end
where medHospitalMarkCode = ?
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtUpdate = $db->prepare($sqlUpdate); try { $deleting = []; foreach ($records as $row) { switch ($row->iud) { case 3: $deleting[] = (object) ['id' => $row->id]; break; } } if ($deleting !== []) { $in = createSqlInCondition($deleting); $sqlDelete = <<<"EOT"
delete from medHospitalMark
where medHospitalMarkCode $in
EOT;
$stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute(); } foreach ($records as $row) { switch ($row->iud) { case 1: $stmtInsert->execute([ $id, isEmptyProperty($row, 'markId'), isUndefinedProperty($row, 'markDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'contCount'), isUndefinedProperty($row, 'sumCount'), isEmptyProperty($row, 'information') ]); break; case 2: $stmtUpdate->execute([ propertyExistsSQL($row, 'markId'), isEmptyProperty($row, 'markId'), propertyExistsSQL($row, 'markDate'), isUndefinedProperty($row, 'markDate'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'contCount'), isEmptyProperty($row, 'contCount'), propertyExistsSQL($row, 'sumCount'), isUndefinedProperty($row, 'sumCount'), propertyExistsSQL($row, 'information'), isEmptyProperty($row, 'information'), $row->id ]); break; } } } catch (Throwable $e) { throw $e; } return true; } private function syncExtensions($records, $id) { $db = $this->db ? : $this->db = connectDb(); $sqlInsert = <<<"EOT"
insert into medHospitalExtension
    (MedHospitalCode, ToDate, MedDoctorCode, MeetKind)
values
    (?, ?, ?, ?)
EOT;
$sqlUpdate = <<<"EOT"
update medHospitalExtension set
       toDate = case when ? = 1 then ? else toDate end,
       medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
       meetKind = case when ? = 1 then ? else meetKind end
where medHospitalExtensionCode = ?
EOT;
$stmtInsert = $db->prepare($sqlInsert); $stmtUpdate = $db->prepare($sqlUpdate); try { $deleting = []; foreach ($records as $row) { switch ($row->iud) { case 1: $stmtInsert->execute([ $id, isUndefinedProperty($row, 'toDate'), isEmptyProperty($row, 'doctorId'), isUndefinedProperty($row, 'meetKindId', 0) ]); break; case 2: $stmtUpdate->execute([ propertyExistsSQL($row, 'toDate'), isUndefinedProperty($row, 'toDate'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'meetKindId'), isUndefinedProperty($row, 'meetKindId', 0), $row->id ]); break; case 3: $deleting[] = (object) ['id' => $row->id]; break; } } if ($deleting !== []) { $in = createSqlInCondition($deleting); $sqlDelete = <<<"EOT"
delete from MedHospitalExtension
where MedHospitalExtensionCode $in
EOT;
$stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute(); } } catch (Throwable $e) { throw $e; } return true; } private function updateSheetNext($record, $our = true) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update medHospitalNext set
       documentSeries = case when ? = 1 then ? else documentSeries end,
       documentNumber = case when ? = 1 then ? else documentNumber end,
       startDate = case when ? = 1 then ? else startDate end,
       treatmentMode = case when ? = 1 then ? else treatmentMode end
where medHospitalNextCode = ?
EOT;
$stmt = $db->prepare($sql); $prior = $our ? '' : '_prior'; try { $stmt->execute([ propertyExistsSQL($record, 'documentSeries' . $prior), isUndefinedProperty($record, 'documentSeries' . $prior), propertyExistsSQL($record, 'documentNumber' . $prior), isUndefinedProperty($record, 'documentNumber' . $prior), propertyExistsSQL($record, 'startDate' . $prior), isUndefinedProperty($record, 'startDate' . $prior), propertyExistsSQL($record, 'treatmentId' . $prior), isEmptyProperty($record, 'treatmentId' . $prior), $our ? $record->nextId : $record->id_prior ]); } catch (Throwable $e) { throw $e; } return true; } private function createSheetNext($record, $our = true) { $db = $this->db ? : $this->db = connectDb(); $position = 0; $sql = <<<"EOT"
insert into MedHospitalNext
    (MedHospitalCode, DocumentSeries, DocumentNumber, Position, StartDate, TreatmentMode, priorNumber)
values
    (?, ?, ?, ?, ?, ?, ?)

EOT;
$stmt = $db->prepare($sql); if (!$our) { $sqlGetPosition = <<<"EOT"
select MAX(Position) + 1 "pos"
from medHospitalNext
where medHospitalCode = ?
EOT;
$stmtGetPosition = $db->prepare($sqlGetPosition); $stmtGetPosition->execute([ $record->id ]); $position = fetchValue($stmtGetPosition, 'pos'); } $prior = $our ? '' : '_prior'; try { $stmt->execute([ isUndefinedProperty($record, 'id'), isUndefinedProperty($record, 'documentSeries' . $prior), isUndefinedProperty($record, 'documentNumber' . $prior), $position, isUndefinedProperty($record, 'startDate' . $prior), isUndefinedProperty($record, 'treatmentId' . $prior), $our ? null : 1 ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medHospitalNext'); } catch (Throwable $e) { throw $e; } return $rec; } private function deleteSheetNext($id) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
delete from MedHospitalNext
where MedHospitalNextCode = $id
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute(); } catch (Throwable $e) { throw $e; } return true; } private function createMan($aFio) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
insert into Man
    (SurName, Name, SecondName)
values
    (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $fio = preg_split("/[\s.,]+/u", $aFio); try { $stmt->execute([ isset($fio[0]) ? $fio[0] : null, isset($fio[1]) ? $fio[1] : null, isset($fio[2]) ? trim(implode(" ", (array_slice($fio, 2)))) : null ]); $ret = $db->lastInsertId('seq_Man'); } catch (Throwable $e) { throw $e; } return $ret; } private function getCurrentManId($recId) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select manCodeReceived
from MedHospital
where medHospitalCode = $recId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return fetchValue($stmt, 'mancodereceived'); } private function deleteMan($aId) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
delete from Man
where ManCode = $aId
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute(); } catch (Throwable $e) { throw $e; } return true; } public function checkUnic($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"SQL"
SELECT
       MH.MedHospitalCode
FROM MedHospitalNext MHN
     JOIN MedHospital MH ON MH.MedHospitalCode = MHN.MedHospitalCode
WHERE lower(MHN.DocumentNumber) = lower(?)
  AND lower(MHN.DocumentSeries) = lower(?)
  AND MH.MedHospitalCode <> ?
  AND MH.FinishDate IS NULL
LIMIT 1
SQL;
return selectSqlValue($db, $sql, [ isEmptyProperty($params, 'documentNumber', ''), isEmptyProperty($params, 'documentSeries', ''), isEmptyProperty($params, 'hospitalId', 0), ]) || false; } public function calculateSumCount($params) { $db = $this->db ? : $this->db = connectDb(); $beginDate = isEmptyProperty($params, 'beginDate'); $patientId = isUndefinedProperty($params, 'patientId'); $id = isUndefinedProperty($params, 'medHospitalCode', 0); $sqlParams = []; if (!$beginDate || !$patientId) { return false; } $date = dbDate((new DateTime($beginDate))->modify('-1 year')); $beginDate = dbDate($beginDate); $sqlParams[':id'] = $id; $sqlParams[':date'] = $date; $sqlParams[':beginDate'] = $beginDate; $sqlParams[':patientId'] = $patientId; $sql = <<<EOT
SELECT
MH.StartHospitalDate,
MH.FinishDate,
MH.HospitalDay
FROM MedHospital MH
WHERE MedPatientCode = :patientId
AND ((MH.StartHospitalDate >= :date AND (MH.FinishDate <= :beginDate))
    OR ( :date BETWEEN MH.StartHospitalDate AND MH.FinishDate))
AND MH.MedHospitalCode <> :id
AND NOT EXISTS(
    SELECT
        1
    FROM MedHospitalMark MHM
    LEFT JOIN UniDict UD ON UD.DictNumber = 20 AND UD.UniDictCode = MHM.UniDictCodeMark
    WHERE MHM.MedHospitalCode = MH.MedHospitalCode
    AND UD.Value1::int IN(3,4,5,6)
    LIMIT 1)
EOT;
return selectSql($db, $sql, $sqlParams); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $diagnosisCiszId = isEmptyProperty($params, 'id'); $items = isUndefinedProperty($params, 'items'); if (!$diagnosisCiszId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM CiszDiagnosisExtraAnatomic
        WHERE CiszDiagnosisExtraCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $diagnosisCiszId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO CiszDiagnosisExtraAnatomic
            (CiszDiagnosisExtraCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $diagnosisCiszId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM CiszDiagnosisExtraAnatomic
            WHERE CiszDiagnosisExtraCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$diagnosisCiszId], $toDelete)); } return true; } } handleJsonRpc('med_sickdays_sheets');