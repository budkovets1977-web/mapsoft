<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/card/cards.php'); resumeJsonRpc(); class med_dayhospital_log { private $db; public function readDayHospital($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $patientId = isEmptyProperty($params, 'patientId'); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : null; $departmentId = isEmptyProperty($params, 'departmentId'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $issue = isEmptyProperty($params, 'issue'); $dateType = isEmptyProperty($params, 'dateType'); $doctorId = isEmptyProperty($params, 'doctorId'); $limit = (int)isEmptyProperty($params, 'limit', 25); $offset = (int)isEmptyProperty($params, 'start', 0); $page = (int)isEmptyProperty($params, 'page', 0); $isCard = isEmptyProperty($params, 'isCard', false); $currDate = Date("Ymd"); $sex = is_object($params) ? isUndefinedProperty($params, 'sex') : null; $startAge = isEmptyProperty($params, 'startAge', 0); $finishAge = isEmptyProperty($params, 'finishAge', 999); $contingentId = isEmptyProperty($params, 'contingentId', false); $active = isEmptyProperty($params, 'active', false); $plan = isEmptyProperty($params, 'plan'); $directDoctorId = isEmptyProperty($params, 'directDoctorId', false); $search = isEmptyProperty($params, 'search'); $sort = isEmptyProperty($params, 'sort'); $dir = isEmptyProperty($params, 'dir', 'asc'); $sqlLimit = ""; $sqlParams = []; if ($offset !==0 or $page !== 0) { $offset = $offset ? $offset : ($page - 1) * $limit; } if (empty($sort) or $sort === 'default') { $sqlOrder = "ORDER BY
                            CASE WHEN MDH.status = 1 AND MDH.issue IS NULL THEN 1
                                WHEN MDH.status IS NULL AND MDH.issue IS NULL THEN 2
                                ELSE 3
                            END $dir"; } elseif ($sort === 'numberHistory') { $sqlOrder = "ORDER BY RIGHT(REPEAT(' ', 20) + asa_trim(MP.NumberHistorySickness), 20) $dir "; } else { $sqlOrder = "ORDER BY \"$sort\" $dir "; } if (empty($isCard)) { $sqlLimit .= "LIMIT $limit OFFSET $offset"; } $sqlFrom = <<<SQL
            FROM MedDayHospital MDH
            JOIN Department D on D.DepartmentCode = MDH.DepartmentCode
                AND fn_WebUserAccessDepartment(d.DepartmentCode) = 1
            LEFT OUTER JOIN MedPatient MP ON MP.MedPatientCode = MDH.MedPatientCode
            LEFT JOIN MedPatientGroup AS MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode
            LEFT OUTER JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MDH.MedDiagnosisCode
            LEFT OUTER JOIN UniDict UD ON UD.UniDictCode = MDH.Issue AND DictNumber = 24
            LEFT OUTER JOIN MedDoctor MDOC ON MDOC.MedDoctorCode = MDH.MedDoctorCode
            LEFT OUTER JOIN MedProfession MEDP ON MEDP.MedProfessionCode = MDOC.MedProfessionCode
            LEFT OUTER JOIN ManPassport PASS ON PASS.ManCode = MP.ManCode
            LEFT OUTER JOIN medDoctor MDOC2 ON MDOC2.MedDoctorCode = MDH.MedDoctorCodeSender
            LEFT OUTER JOIN MedProfession MEDP2 ON MEDP2.MedProfessionCode = MDOC2.MedProfessionCode
            LEFT JOIN MedLogSend MLS ON MLS.MedLogSendCode = MDH.MedLogSendCode
            LEFT JOIN MedDiagnosis MDF ON MDF.MedDiagnosisCode = MDH.MedDiagnosisCodeFinal
            SQL;
$sqlWhere = "WHERE 1 = 1 "; $drugsSql = ''; $needDrugs = isEmptyProperty($params, 'needDrugs'); $documentDate = isEmptyProperty($params, 'documentDate'); if ($needDrugs && $documentDate) { $drugsSql = $this->getDrugsSql(); $sqlParams[':documentDate'] = dbDate($documentDate); $sqlWhere .= 'AND :documentDate = :documentDate '; } if (!empty($directDoctorId)) { $sqlWhere .= "AND MDH.medDoctorCodeSender = :directDoctorId "; $sqlParams[':directDoctorId'] = $directDoctorId; } if (!is_null($sex)) { $sqlWhere .= " AND PASS.Sex = :sex"; $sqlParams[':sex'] = $sex; } $sqlWhere .= " AND PASS.dateofbirth BETWEEN :dates AND :datef"; $sqlParams[':dates'] = dbDate(new DateTime("- $finishAge years - 1 years + 1 day")); $sqlParams[':datef'] = dbDate(new DateTime("- $startAge years")); if ($search) { $sqlParams[':search'] = $search; $sqlParams[':search2'] = (int)$search ?: null; $sqlWhere .= " AND (LOWER(fn_cmFIO(MP.ManCode)) LIKE '%' || LOWER(:search) || '%'
                           OR EXTRACT('year' FROM AGE('now'::DATE, PASS.DateOfBirth)) = :search2
                           OR LOWER(fn_cmFIO(MDOC.ManCode)) LIKE '%' || LOWER(:search) || '%'
                           OR LOWER(fn_cmFIO(MDOC2.ManCode)) LIKE '%' || LOWER(:search) || '%' "; $diagnosisSearchArr = safe_explode(' ', $search); if (count($diagnosisSearchArr)) { $sqlWhere .= " OR ("; foreach ($diagnosisSearchArr as $key => $diagnosisSearch) { if ($key) { $sqlWhere .= " AND "; } $sqlWhere .= " (LOWER(MD.Cipher1) LIKE '%$diagnosisSearch%'
                        OR LOWER(MD.Name) LIKE '%$diagnosisSearch%') "; } $sqlWhere .= ")"; } $sqlWhere .= ")"; } if (!empty($contingentId)) { $sqlFrom .= " LEFT OUTER JOIN MedPatientCard MPC ON MPC.Medpatientcode = MP.MedPatientCode "; if (!empty($startDate)) { $sqlWhere .= " AND :startDate BETWEEN COALESCE(MPC.StartDate, 19000101) AND COALESCE(MPC.FinishDate, 30000101)"; $sqlParams[':startDate'] = $startDate; } if (!empty($finishDate)) { $sqlWhere .= " AND :finishDate  BETWEEN COALESCE(MPC.StartDate, 19000101) AND COALESCE(MPC.FinishDate, 30000101)"; $sqlParams[':finishDate'] = $finishDate; } if ((empty($finishDate)) and (empty($finishDate))) { $currDate = Date('Y-m-d'); $sqlWhere .= " AND :currDate  BETWEEN COALESCE(MPC.StartDate, 19000101) AND COALESCE(MPC.FinishDate, 30000101)"; $sqlParams[':currDate'] = $currDate; } $sqlWhere .=" AND medprivelegecode ".createSqlInCondition($contingentId); } if (isset($patientId)) { $sqlWhere .= " AND MDH.MedPatientCode = :patientId "; $sqlParams[':patientId'] = $patientId; } if (isset($id)) { $sqlWhere .= " AND MDH.MedDayHospitalCode = :id"; $sqlParams[':id'] = $id; } switch ($dateType) { case 0: if (isset($startDate)) { $sqlWhere .= " AND MDH.directDate >= :startDate"; $sqlParams[':startDate'] = $startDate; } if (isset($finishDate)) { $sqlWhere .= " AND MDH.directDate <= :finishDate"; $sqlParams[':finishDate'] = $finishDate; } break; case 1: if (isset($startDate)) { $sqlWhere .= " AND MDH.startDate >= :startDate"; $sqlParams[':startDate'] = $startDate; } if (isset($finishDate)) { $sqlWhere .= " AND MDH.startDate <= :finishDate"; $sqlParams[':finishDate'] = $finishDate; } break; case 2: if (isset($startDate)) { $sqlWhere .= " AND MDH.finishDate >= :startDate"; $sqlParams[':startDate'] = $startDate; } if (isset($finishDate)) { $sqlWhere .= " AND MDH.finishDate <= :finishDate"; $sqlParams[':finishDate'] = $finishDate; } break; case 3: if ($startDate && !$finishDate) { $sqlWhere .= " AND MDH.startDate >= :startDate"; $sqlParams[':startDate'] = $startDate; } else if ($startDate && $finishDate) { $sqlWhere .= " AND MDH.startDate <= :finishDate AND MDH.finishDate >= :startDate"; $sqlParams[':finishDate'] = $finishDate; $sqlParams[':startDate'] = $startDate; } else if ($finishDate) { $sqlWhere .= " AND MDH.finishDate <= :finishDate"; $sqlParams[':finishDate'] = $finishDate; } break; } if (isset($departmentId)) { $sqlWhere .= " AND MDH.DepartmentCode = :departmentId"; $sqlParams[':departmentId'] = $departmentId; } if (isset($diagnosisId)) { $sqlWhere .= " AND MDH.MedDiagnosisCode ".createSqlInCondition($diagnosisId); } if (isset($issue) || $active || $plan) { $tempSqlWhere = ''; if (isset($issue)) { $tempSqlWhere .= " AND (MDH.issue " . createSqlInCondition($issue); } if ($active) { $tempSqlWhere .= $tempSqlWhere ? " OR (MDH.Issue IS NULL AND MDH.Status = 1)" : " AND ((MDH.Issue IS NULL AND MDH.Status = 1)"; } if ($plan) { $tempSqlWhere .= $tempSqlWhere ? " OR (MDH.Status IS NULL AND MDH.Issue IS NULL)" : " AND ((MDH.Status IS NULL AND MDH.Issue IS NULL)"; } $sqlWhere .= $tempSqlWhere . ")"; } If (isset($doctorId)) { $sqlWhere .= " AND MDH.MedDoctorCode = :doctorId"; $sqlParams[':doctorId'] = $doctorId; } $sql = <<<SQL
            SELECT
                COUNT(*) "total",
                SUM(MDH.Cotday) "sumCotday"
            $sqlFrom
            $sqlWhere
                AND MDH.Cotday > 0
            SQL;
$result = selectSql($db, $sql, $sqlParams); $total = $result[0]['total']; $sumCotday = $result[0]['sumCotday']; $offset = $total < $offset ? 0 : $offset; $sql = <<<SQL
            SELECT
                MDH.MedDayHospitalCode                      "id",
                $drugsSql
                15                                          "recordType",
                MDH.StartDate                               "recordDate",
                MDH.StartDate                               "parentDate",
                null                                        "parentId",
                null                                        "parentType",
                5                                           "sortType",
                COALESCE(MDH.MedDayHospitalCode, '')::text || '-'
                                                            "path",
                MDH.MedDayHospitalCode                      "recordId",
                MDH.MedPatientCode                          "patientId",
                MP.NumberHistorySickness                    "numberHistory",
                fn_cmFIO(MP.ManCode)                        "patientFIO",
                PASS.DateOfBirth                            "birth",
                FN_cmCalcAddress(MP.ManCode, 2)             "patientRegistrationAddress",
                FN_cmCalcAddress(MP.ManCode, 1)             "patientAddress",
                MDH.StartDate                               "startDate",
                MDH.FinishDate                              "finishDate",
                MDH.DirectDate                              "directDate",
                MDH.MedDiagnosisCode                        "diagnosisId",
                CASE
                WHEN MDH.MedDiagnosisCode IS NOT NULL
                THEN MD.Cipher1 + ' - ' + MD.Name
                ELSE ''
                END                                         "diagnosis",
                MDH.ClinicalDiagnosis                       "clinicalDiagnosis",
                MDH.Issue                                   "issue",
                MDH.status                                  "status",
                UD.Value                                    "issueName",
                MDH.MedDoctorCode                           "doctorId",
                MDH.medDoctorCodeSender                     "directDoctorId",
                fn_cmFIO(MDOC.ManCode)                      "doctor",
                fn_cmFIO(MDOC2.ManCode)                     "directDoctor",
                MDH.DepartmentCode                          "departmentId",
                MDH.Cotday                                  "cotday",
                D.name                                      "departmentName",
                fn_cmDepartmentName(MDOC.DepartmentCode)    "doctorDepartmentName",
                fn_cmDepartmentName(MDOC2.DepartmentCode)   "directDoctorDepartmentName",
                MEDP.Name                                   "doctorProfessionName",
                MEDP2.Name                                  "directDoctorProfessionName",
                (
                    SELECT STRING_AGG(ud.value::TEXT, '; ' ORDER BY ud.value)
                    FROM medPatientBusy mb
                    LEFT JOIN enterprise eb ON eb.EnterpriseCode = mb.EnterpriseCode
                    LEFT JOIN uniDict ud ON ud.uniDictCode = COALESCE(mb.uniDictCodeProfession, 0) AND ud.dictNumber = 19
                    WHERE mb.medPatientCode = mp.medPatientCode
                        AND '$currDate' BETWEEN mb.startDate AND mb.finishDate
                )                                           "profession",
                mpg.name                                    "sector",
                (
                    SELECT STRING_AGG(pr.name, ', ')
                    FROM medPatientCard pc
                    JOIN medPrivelege pr ON pr.medPrivelegeCode = pc.medPrivelegeCode
                    WHERE pc.medPatientCode = mp.medPatientCode
                        AND '$currDate' BETWEEN pc.startDate AND COALESCE(pc.finishDate, 30000101)
                )                                           "privileges",
                MDH.CiszResourceValueCodePayType           "paymentTypeId",
                MDH.CiszResourceValueCodeMedCareType       "healthCareTypeId",
                MDH.InsuranceNumber                        "insuranceNumber",
                MDH.InsuranceOrganization                  "insuranceOrganization",
                MDH.InsuranceIssueDate                     "insuranceCreateDate",
                MDH.InsuranceStartDate                     "insuranceStartDate",
                MDH.InsuranceFinishDate                    "insuranceEndDate",
                MDH.SendGoalText                           "goal",
                MDH.MedLogSendCode                         "sendId",
                CASE 
                WHEN MDH.MedLogSendCode IS NOT NULL 
                THEN 'Направление № ' || MLS.DocumentNumber || ' от ' || TO_CHAR(MLS.DocumentDate, 'DD.MM.YYYY')
                ELSE '' 
                END                                        "sendName",
                MDH.MedDiagnosisCodeFinal                  "finalDiagnosisId",
                CASE
                WHEN MDH.MedDiagnosisCodeFinal IS NOT NULL
                THEN MDF.Cipher1 + ' - ' + MDF.Name
                ELSE ''
                END                                         "finalDiagnosisName",
                MDH.ClinicalDiagnosisFinal                 "clinicalDiagnosisFinal",
                MDH.Note                                   "note",
                MDH.CiszResourceValueCodeCancerStage       "cancerStageId",
                (SELECT string_agg(UD.Value::text, '; ' ORDER BY UD.Value)
                FROM MedPatientBusy MB
                LEFT JOIN Enterprise as EB ON MB.EnterpriseCode = EB.EnterpriseCode
                LEFT JOIN UniDict as UD ON UD.UniDictCode = COALESCE(MB.UniDictCodeProfession,0) and UD.DictNumber = 19
                WHERE MB.MedPatientCode = MP.MedPatientCode
                AND $currDate BETWEEN MB.StartDate AND MB.FinishDate) as "employment"
            $sqlFrom
            $sqlWhere
            $sqlOrder
            $sqlLimit
            SQL;
if ($isCard) { return selectSql($db, $sql, $sqlParams); } else { $root = new stdClass(); $root->total = $total; $root->items = selectSql($db, $sql, $sqlParams); $root->sumCotday = empty($sumCotday) ? 0 : $sumCotday; return $root; } } protected function getDrugsSql () { return <<<"EOT"
(
SELECT
    '[' || STRING_AGG(drug_obj, ', ') || ']'
FROM (
    SELECT
    '{'
    || JSON_String('drugName', COALESCE(NULLIF(MDHP.Json, '')::json -> 'medication' ->> 'name', NULL))
    || JSON_NUMERIC('dose', MDHP.dose)
    || JSON_String('measureDoseName', CRV.Name)
    || JSON_String('methodName', MPM.name)
    || JSON_String('graphName', MGA.name)
    || JSON_String('startTime', MDHP.purposeTime)
    || JSON_NUMERIC('note', MDHP.note, 0)
    || '}' "drug_obj"

    FROM MedDayHopsitalPurpose MDHP
    LEFT JOIN MatWealth MW ON MW.MatWealthCode = MDHP.MatWealthCode
    LEFT JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
    LEFT JOIN MatWealthMeasure MWM ON MWM.MatWealthMeasureCode = MW.MatWealthMeasureDoseSizeCode
    LEFT JOIN MedPurposeMethod MPM ON MPM.MedPurposeMethodCode = MDHP.MedPurposeMethodCode
    LEFT JOIN MedGraphAccept MGA ON MGA.MedGraphAcceptCode = MDHP.MedGraphAcceptCode
    LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MDHP.CiszResourceCodeDoseMeasure
    WHERE MDHP.MedDayHospitalCode = MDH.MedDayHospitalCode
        AND COALESCE(MDHP.cancelTime, '3000-01-01')::date > :documentDate
) t1
) "drugsJson",
EOT;
} public function readDayHospitalDocuments($params) { $db = $this->db ?: $this->db = connectDb(); $dayHospitalIds = isEmptyProperty($params, 'dayHospitalIds', 'in (0)'); $sql = <<<SQL
SELECT
    MVD.MedVisitDocumentCode "id",
    MVP.MedVisitPatientCode "visitPatientId",
    ----------------------------------------------------------------------------
    MVD.medVisitDocumentCode "recordId",
    MVD.CreateTime "recordDate",
    COALESCE(MDH.MedDayHospitalCode, '')::text || '-' || COALESCE(MVD.medVisitDocumentCode, '')::text "path",
    MDH.StartDate "parentDate",
    14 "recordType",
    1 "docType",
    MDH.MedDayHospitalCode "parentId",
    15 "parentType",
    5 "sortType",
    ----------------------------------------------------------------------------
    HT.Name "pageName", --
    U.Nick "userName", --
    MVD.htmPageCode "pageId", --
    HP.CreateTime "createTime", --
    MVD.ChangeTime "changeTime", --
    COALESCE(HP.Ready, 0) "ready",
    MVD.htmTemplateCode "templateId", --
    HT.htmPageCode "templatePageId",
    MV.medDoctorCode            "doctorId",
    FN_cmFIO(M.manCode)::TEXT   "doctorFio",
    MD.departmentCode           "doctorDepartmentId",
    FN_cmDepartmentName(MD.departmentCode, 1)::TEXT
                                "doctorDepartmentName"
FROM medVisitDocument MVD
LEFT OUTER JOIN MedDayHospital MDH on MDH.MedDayHospitalCode = MVD.MedDayHospitalCode
LEFT OUTER JOIN Users U on U.UserCode = MVD.UserCode
LEFT OUTER JOIN htmPage HP on HP.htmPageCode = MVD.htmPageCode
LEFT OUTER JOIN htmTemplate HT on HT.htmTemplateCode = MVD.htmTemplateCode
LEFT JOIN MedVisitPatient MVP ON MVP.MedVisitPatientCode = MVD.MedVisitPatientCode
LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MV.MedDoctorCode
LEFT JOIN man M ON M.manCode = MD.manCode
WHERE MVD.MedDayHospitalCode $dayHospitalIds
    and ((MVD.Type = 1 and COALESCE(HP.IsDeleted, 0) = 0) or
       (MVD.Type = 2 and ((0 = 1 and MVD.DeleteTime IS NOT NULL) or
                          (0 = 0 and MVD.DeleteTime IS NULL))))
SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDayHospital($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
UPDATE MedDayHospital SET
    MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
    MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
    MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end,
    medDoctorCodeSender = case when ? = 1 then ? else medDoctorCodeSender end,
    StartDate = case when ? = 1 then ? else StartDate end,
    FinishDate = case when ? = 1 then ? else FinishDate end,
    Cotday = case when ? = 1 then ? else Cotday end,
    Issue = case when ? = 1 then ? else Issue end,
    DirectDate = case when ? = 1 then ? else DirectDate end,
    DepartmentCode = case when ? = 1 then ? else DepartmentCode end,
    ClinicalDiagnosis = case when ? = 1 then ? else ClinicalDiagnosis end,
    Status = case when ? = 1 then ? else Status end,
    CiszResourceValueCodePayType = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodePayType END,
    CiszResourceValueCodeMedCareType = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeMedCareType END,
    InsuranceNumber = CASE WHEN ? = 1 THEN ? ELSE InsuranceNumber END,
    InsuranceOrganization = CASE WHEN ? = 1 THEN ? ELSE InsuranceOrganization END,
    InsuranceIssueDate = CASE WHEN ? = 1 THEN ? ELSE InsuranceIssueDate END,
    InsuranceStartDate = CASE WHEN ? = 1 THEN ? ELSE InsuranceStartDate END,
    InsuranceFinishDate = CASE WHEN ? = 1 THEN ? ELSE InsuranceFinishDate END,
    SendGoalText = CASE WHEN ? = 1 THEN ? ELSE SendGoalText END,
    MedLogSendCode = CASE WHEN ? = 1 THEN ? ELSE MedLogSendCode END,
    MedDiagnosisCodeFinal = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCodeFinal END,
    ClinicalDiagnosisFinal = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosisFinal END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END
WHERE MedDayHospitalCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $rec = new stdClass; try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'directDoctorId'), isUndefinedProperty($row, 'directDoctorId'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'cotday'), isUndefinedProperty($row, 'cotday'), propertyExistsSQL($row, 'issue'), isUndefinedProperty($row, 'issue'), propertyExistsSQL($row, 'directDate'), isUndefinedProperty($row, 'directDate'), propertyExistsSQL($row, 'departmentId'), isUndefinedProperty($row, 'departmentId'), propertyExistsSQL($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'status'), isUndefinedProperty($row, 'status'), propertyExistsSQL($row, 'paymentTypeId'), isUndefinedProperty($row, 'paymentTypeId'), propertyExistsSQL($row, 'healthCareTypeId'), isUndefinedProperty($row, 'healthCareTypeId'), propertyExistsSQL($row, 'insuranceNumber'), isUndefinedProperty($row, 'insuranceNumber'), propertyExistsSQL($row, 'insuranceOrganization'), isUndefinedProperty($row, 'insuranceOrganization'), propertyExistsSQL($row, 'insuranceCreateDate'), isUndefinedProperty($row, 'insuranceCreateDate'), propertyExistsSQL($row, 'insuranceStartDate'), isUndefinedProperty($row, 'insuranceStartDate'), propertyExistsSQL($row, 'insuranceEndDate'), isUndefinedProperty($row, 'insuranceEndDate'), propertyExistsSQL($row, 'goal'), isUndefinedProperty($row, 'goal'), propertyExistsSQL($row, 'sendId'), isUndefinedProperty($row, 'sendId'), propertyExistsSQL($row, 'finalDiagnosisId'), isUndefinedProperty($row, 'finalDiagnosisId'), propertyExistsSQL($row, 'clinicalDiagnosisFinal'), isUndefinedProperty($row, 'clinicalDiagnosisFinal'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'cancerStageId'), isUndefinedProperty($row, 'cancerStageId'), $row->id ]); $rec->id = $row->id; $this->updateCiszStatus($rec->id); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readDayHospital($rec); } public function createDayHospital($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            INSERT INTO MedDayHospital (
                MedPatientCode, MedDoctorCode, MedDiagnosisCode,
                medDoctorCodeSender, StartDate, FinishDate,
                Cotday, Issue, DirectDate,
                DepartmentCode, ClinicalDiagnosis, Status,
                CiszResourceValueCodePayType, CiszResourceValueCodeMedCareType, InsuranceNumber,
                InsuranceOrganization, InsuranceIssueDate, InsuranceStartDate,
                InsuranceFinishDate, SendGoalText, MedLogSendCode,
                MedDiagnosisCodeFinal, ClinicalDiagnosisFinal, Note,
                CiszResourceValueCodeCancerStage
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?
            )
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'directDoctorId'), isEmptyProperty($row, 'startDate'), isEmptyProperty($row, 'finishDate'), isEmptyProperty($row, 'cotday'), isEmptyProperty($row, 'issue'), isEmptyProperty($row, 'directDate'), isEmptyProperty($row, 'departmentId'), isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'status'), isEmptyProperty($row, 'paymentTypeId'), isEmptyProperty($row, 'healthCareTypeId'), isEmptyProperty($row, 'insuranceNumber'), isEmptyProperty($row, 'insuranceOrganization'), isEmptyProperty($row, 'insuranceCreateDate'), isEmptyProperty($row, 'insuranceStartDate'), isEmptyProperty($row, 'insuranceEndDate'), isEmptyProperty($row, 'goal'), isEmptyProperty($row, 'sendId'), isEmptyProperty($row, 'finalDiagnosisId'), isEmptyProperty($row, 'clinicalDiagnosisFinal'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'cancerStageId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedDayHospital'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readDayHospital($rec); } public function deleteDayHospital($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedDayHospital', 'MedDayHospitalCode', $records); } public function createVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            INSERT INTO MedVisitDocument (
                MedVisitPatientCode, Type, UserCode,
                HtmTemplateCode, Note, ChangeTime,
                CreateTime, medDayHospitalCode
            )
            VALUES (
                ?, ?, ?,
                ?, 'Новая запись', LOCALTIMESTAMP,
                LOCALTIMESTAMP, ?
            )
            SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { if (isEmptyProperty($params, 'isAdd')) { $medCard = new med_card_cards(); $visitPatient = $medCard->createVisitPatient($params); $ret->visitId = isEmptyProperty($visitPatient, 'visitId'); $visitPatientId = isEmptyProperty($visitPatient, 'id'); $ret->visitPatientId = $visitPatientId; } else { $visitPatientId = isEmptyProperty($params, 'visitPatientId'); } $stmt->execute([ $visitPatientId, isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId'), isEmptyProperty($params, 'dayHospitalId') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId', 0); $pageId = isEmptyProperty($params, 'pageId'); $dayHospitalId = isEmptyProperty($params, 'dayHospitalId'); $sql = <<<SQL
            UPDATE MedVisitDocument SET
                HtmPageCode = ?,
                ChangeTime = LOCALTIMESTAMP,
                MedDayHospitalCode = ?
            WHERE MedVisitDocumentCode = ?
            SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $pageId, $dayHospitalId, $documentId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); if ($this->checkVisitDocument($params)) { $this->deleteDocument($params); } else { $this->deleteVisitAndDocument($params); } return true; } private function checkVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitPatientId = isEmptyProperty($params, 'visitPatientId'); $visitDocumentId = isEmptyProperty($params, 'visitDocumentId'); $sql = <<<SQL
            SELECT 1 "exists"
            FROM MedVisitDocument
            WHERE MedVisitPatientCode = ?
                AND MedVisitDocumentCode <> ?
            LIMIT 1
            SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $visitPatientId, $visitDocumentId ]); return fetchValue($stmt, 'exists') ? TRUE : FALSE; } public function deleteDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitDocumentId = isEmptyProperty($params, 'visitDocumentId'); return deleteSql($db, 'MedVisitDocument', 'MedVisitDocumentCode', $visitDocumentId); } public function deleteVisitAndDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitPatientId = isEmptyProperty($params, 'visitPatientId'); try { $sql = <<<SQL
                DELETE FROM MedVisitPatient
                WHERE MedVisitPatientCode = ?
                RETURNING *
                SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $visitPatientId ]); $row = $stmt->fetch(); $visitId = $row['medvisitcode']; $patientId = $row['medpatientcode']; $sql = <<<SQL
                DELETE FROM MedVisitPatient
                WHERE MedVisitCode = ?
                    AND MedPatientCode = ?
                SQL;
executeSql($db, $sql, [ $visitId, $patientId ]); } catch (Throwable $e) { if ($e->getCode() === "30001") { $lockPeriod = new map_common_periodLock(); $lockDate = safe_date_create($lockPeriod->getVisitLockDate()); throw new Exception("Нельзя удалить запись в закрытом периоде по " . date_format($lockDate, "d.m.Y") . "!"); } throw $e; } return true; } public function readRegimeViolation($params = null) { $db = $this->db ?: $this->db = connectDb(); $logId = (int) isEmptyProperty($params, 'logId'); if (!$logId) return null; $sql = <<<"EOT"
SELECT 
    MDV.MedDayhospitalViolationCode "id",
    MDV.MedDayhospitalCode "logId",
    MDV.ViolationTime "dateTime",
    MDV.Note "description",
    DATE(MDV.ViolationTime) "date",
    MDV.ViolationTime "time"
FROM MedDayhospitalViolation MDV
WHERE MDV.MedDayhospitalCode = $logId
ORDER BY MDV.ViolationTime
EOT;
return selectSql($db, $sql); } public function createRegimeViolation($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $record) { if (isset($record->date) && isset($record->time)) { $record->violationTime = (new DateTime($record->date))->format('Y-m-d') . ' ' . (new DateTime($record->time))->format('H:i'); } else { $record->violationTime = null; } insertSql( $db, 'MedDayHospitalViolation', $record, "MedDayHospitalCode ViolationTime Note", "logId violationTime description" ); } if (isset($records[0]->logId)) { $this->updateCiszStatus($records[0]->logId); } $db->commit(); } catch (\Exception $e) { $db->rollBack(); throw $e; } return true; } public function updateRegimeViolation($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $db->beginTransaction(); try { $logId = null; foreach ($records as $record) { $stmt = $db->prepare( "SELECT ViolationTime, MedDayHospitalCode FROM MedDayHospitalViolation WHERE MedDayHospitalViolationCode = ?" ); $stmt->execute([$record->id]); $current = $stmt->fetch(PDO::FETCH_ASSOC); $currentTime = new DateTime($current['violationtime']); $logId = new DateTime($current['meddayhospitalcode']); $newDate = isset($record->date) ? (new DateTime($record->date))->format('Y-m-d') : $currentTime->format('Y-m-d'); $newTime = isset($record->time) ? (new DateTime($record->time))->format('H:i') : $currentTime->format('H:i'); $record->violationTime = $newDate . ' ' . $newTime; updateSql( $db, 'MedDayHospitalViolation', 'MedDayHospitalViolationCode', $record, "ViolationTime Note", "violationTime description" ); } if ($logId) { $this->updateCiszStatus($logId); } $db->commit(); } catch (\Exception $e) { $db->rollBack(); throw $e; } return true; } public function deleteRegimeViolation($records) { $db = $this->db ?: $this->db = connectDb(); $recordsList = is_array($records) ? $records : [$records]; $db->beginTransaction(); try { foreach ($recordsList as $code) { deleteSql($db, 'MedDayHospitalViolation', 'MedDayHospitalViolationCode', $code); } $db->commit(); } catch (\Exception $e) { $db->rollBack(); throw $e; } return true; } public function readDayHospitalAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $logId = isEmptyProperty($params, 'logId'); $sql = <<<"EOT"
SELECT
        MDPA.CiszResourceValueCodeAnatomic "id",
        CRV.Name "name"
        FROM MedDayHospitalAnatomic MDPA
           LEFT JOIN CiszResourceValue CRV ON MDPA.CiszResourceValueCodeAnatomic = CRV.CiszResourceValueCode
        WHERE MDPA.MedDayHospitalCode = $logId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDayHospitalAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $logId = isEmptyProperty($params, 'logId'); $items = isEmptyProperty($params, 'items'); if (!$logId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM MedDayHospitalAnatomic
        WHERE MedDayHospitalCode = :logId
    "; $stmt = $db->prepare($sql); $stmt->execute(['logId' => $logId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO MedDayHospitalAnatomic
            (MedDayHospitalCode, CiszResourceValueCodeAnatomic)
            VALUES (:logId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'logId' => $logId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM MedDayHospitalAnatomic
            WHERE MedDayHospitalCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$logId], $toDelete)); } $this->updateCiszStatus($logId); return true; } public function updateCiszStatus($id) { $db = $this->db ?: $this->db = connectDb(); $sql = "SELECT Status FROM CiszLink
                WHERE PrimaryKey = ?
                AND TableName = 'MedDayHospital'
                AND Type = 2"; $stmt = $db->prepare($sql); $stmt->execute([$id]); $ciszState = $stmt->fetchColumn(); if ($ciszState === 1) { $db->beginTransaction(); $updateSql = "UPDATE CiszLink SET Status = 2
                         WHERE PrimaryKey = ?
                         AND TableName = 'MedDayHospital'"; $updateStmt = $db->prepare($updateSql); $updateStmt->execute([$id]); $db->commit(); } return true; } } handleJsonRpc('med_dayhospital_log');