<?php
require_once (__DIR__ . '/../../init.php'); class med_examinations_inspections { private $db; const QUESTION_DICT_NUMBER = 74; const ANSWER_DICT_NUMBER = 75; const RISK_FACTOR_DICT_NUMBER = 75; public function readInspections($params = null) { $db = $this->db ?: $this->db = connectDb(); $currentEnterprise = getSessionValue('mapEnterpriseCode'); $currId = isEmptyProperty($params, 'currId') ? isEmptyProperty($params, 'currId') : isEmptyProperty($params, 'id'); $isCard = isEmptyProperty($params, 'isCard'); if (empty($currId)) { $inspectsId = isEmptyProperty($params, 'inspectsId', 0); $patientId = isEmptyProperty($params, 'patientId', 0); $doctorId = isEmptyProperty($params, 'doctorId', 0); $inspectKind = isEmptyProperty($params, 'inspectKind', 0); $drComission = isEmptyProperty($params, 'drComission', 0); $enterpriseCode = isEmptyProperty($params, 'enterpriseCode', 0); $preventType = isEmptyProperty($params, 'preventType', 0); $checkStartDate = $inspectsId < 0 ? 0 : 1; $checkFinishDate = $inspectsId < 0 ? 0 : 1; $startDate = date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))); $finishDate = isEmptyProperty($params, 'endDate') ? date("Ymd", safe_strtotime(isEmptyProperty($params, 'endDate'))) : "30000101"; $inspect = isEmptyProperty($params, 'inspect', 0); $preventId = isEmptyProperty($params, 'preventId', 0); $patientGroupId = isEmptyProperty($params, 'patientGroupId', 0); $enterpriseType = isEmptyProperty($params, 'enterpriseType', 0); $classGroup = isEmptyProperty($params, 'classGroup', ''); $diagnosisId = isEmptyProperty($params, 'diagnosisId', 0); $conclusionId = isEmptyProperty($params, 'conclusionId', 0); $sort = isEmptyProperty($params, 'sort', 'planDate'); $dir = isEmptyProperty($params, 'dir', 'desc'); $offset = isEmptyProperty($params, 'start', 0); $limit = isEmptyProperty($params, 'limit', 25); $sqlWhere = <<<EOT
where (I.MedPatientCode = 0 or 0 = 0)
  and (I.MedInspectCode = $inspectsId or 0 = $inspectsId)
  and (I.InspectKind= $inspectKind or 0 = $inspectKind)
  and (I.UniDictCodeDrComission = $drComission or 0 = $drComission)
  and I.EnterpriseCode = $currentEnterprise
  and (PR.MedPreventTypeCode = $preventType or 0=$preventType)
  and ($checkStartDate = 0 or (($inspect = 1 and I.InspectDate >= $startDate) or
                               ($inspect = 2 and I.PlanDate >= $startDate) or
                               ($inspect = 0 and (I.InspectDate >= $startDate or I.PlanDate >= $startDate)))
                            and (((I.InspectDate <= $finishDate and I.InspectDate >= $startDate) or
                                (I.PlanDate <= $finishDate and I.PlanDate >= $startDate))))
  and ($checkFinishDate = 0 or (($inspect = 1 and I.InspectDate <= $finishDate) or
                                ($inspect = 2 and I.PlanDate <= $finishDate) or
                                ($inspect = 0 and (I.InspectDate <= $finishDate or I.PlanDate <= $finishDate)))
                            and (((I.InspectDate <= $finishDate and I.InspectDate >= $startDate) or
                                 (I.PlanDate <= $finishDate and I.PlanDate >= $startDate))))
  and ($preventId = 0 or I.MedPreventCode = $preventId)
  and ($inspect = 0 or
       ($inspect = 1 and I.InspectDate is not null) or
       ($inspect = 2 and I.InspectDate is null))
  and ($patientGroupId = 0 or P.MedPatientGroupCode = $patientGroupId)
  and ($enterpriseCode = 0 or
       COALESCE((select
                        MedPatientBusyCode
                 from MedPatientBusy
                 where MedPatientCode = I.MedPatientCode
                   and EnterpriseCode = $enterpriseCode
                   and I.PlanDate between StartDate and FinishDate
                 limit 1), 0) <> 0)
  and ($enterpriseType = 0 or
       COALESCE((select
                        MPB_.MedPatientBusyCode
                 from MedPatientBusy MPB_
                      join EnterpriseInKind EK_ on EK_.EnterpriseCode = MPB_.EnterpriseCode
                                               and EK_.EnterpriseKindCode = $enterpriseType
                 where MPB_.MedPatientCode = I.MedPatientCode
                   and I.PlanDate between MPB_.StartDate and MPB_.FinishDate
                  limit 1), 0) <> 0)
  and ('$classGroup' =  '' or
       COALESCE((select
                        MedPatientBusyCode
                 from MedPatientBusy
                 where MedPatientCode = I.MedPatientCode
                   and ClassGroup like '$classGroup'
                   and I.PlanDate between StartDate and FinishDate
                 limit 1), 0) <> 0)
  and ($patientId = 0 or I.MedPatientCode = $patientId)
  and ($doctorId = 0 or I.MedDoctorCode = $doctorId)
  and ($diagnosisId = 0  or
       COALESCE((select
                        MIR_.MedInspectResultCode
                 from MedDiagnosis MD_
                      join MedInspectResult MIR_ on MIR_.MedInspectCode = I.MedInspectCode
                      join MedDiagnosis MD2_ on MD2_.MedDiagnosisCode = MIR_.MedDiagnosisCode
                 where MD_.MedDiagnosisCode = $diagnosisId
                   and (MD2_.MedDiagnosisCode = MD_.MedDiagnosisCode or (MD2_.TreePath >= (MD_.TreePath || INTTOHEX(MD_.MedDiagnosisCode)) and
                                                                         MD2_.TreePath < INCHEXSTR(MD_.TreePath || INTTOHEX(MD_.MedDiagnosisCode))))
                 limit 1), 0) <> 0)
  and ($conclusionId = 0 or
       COALESCE((select
                        MedInspectConclusionCode
                 from MedInspectConclusion
                 where MedInspectCode = I.MedInspectCode
                   and MedConclusionGroupCode = $conclusionId
                 limit 1), 0) <> 0)
  /* :viewKind из МАП
  and (CASE COALESCE(:viewKind, 0)
            WHEN 1 THEN CASE I.MedInspectCodeParent IS NULL
                             WHEN true THEN 1
                             WHEN false THEN 0
                        END
            WHEN 2 THEN CASE I.MedInspectCodeParent IS NULL
                             WHEN true THEN 0
                             WHEN false THEN 1
                        END
            ELSE 1
       END) = 1 */
  /* :NotMedInspectCode из МАП
  and ((:NotMedInspectCode IS NULL) or (i.MedInspectCode <> :NotMedInspectCode)) */
EOT;
} else { $sqlWhere = "where I.MedInspectCode = $currId"; } $sqlWhere .= "
    and (p.MedPatientGroupCode in (select gc from pg_)
        or (
            exists (select 1 from MedPatientAddGroup pga
                where pga.MedPatientCode = p.MedPatientCode
                    and pga.MedPatientGroupCode in (select gc from pg_))
        )
        or P.MedPatientGroupCode IS NULL
    )"; $sqlFrom = <<<EOT
            FROM MedInspect I
            JOIN MedPatient P ON P.MedPatientCode = I.MedPatientCode
            JOIN Man M ON M.ManCode = P.ManCode
            JOIN MedPrevent PR ON PR.MedPreventCode = I.MedPreventCode
            LEFT JOIN MedPreventType MPT ON PR.MedPreventTypeCode = MPT.MedPreventTypeCode
            LEFT JOIN MedPatientGroup PG ON PG.MedPatientGroupCode = P.MedPatientGroupCode
            LEFT JOIN MedDoctor D ON D.MedDoctorCode = I.MedDoctorCode
            LEFT JOIN Man MD ON MD.ManCode = D.ManCode
            LEFT JOIN ManPassport MP ON MP.ManCode = M.ManCode
            LEFT JOIN UniDict U ON U.DictNumber = 41
                    AND U.UniDictCode = I.UniDictCodeProfessionRW
            LEFT JOIN UniDict UWC ON UWC.DictNumber = 43
                    AND UWC.UniDictCode = I.UniDictCodeWorkCondition
            LEFT JOIN medDoctor d1 ON d1.medDoctorCode = i.medDoctorCodeOther
            LEFT JOIN medProfession mp1 ON mp1.medProfessionCode = d1.medProfessionCode
            LEFT JOIN buhMol bm ON bm.buhMolCode = i.buhMolCodeOther
            LEFT JOIN department d2 ON d2.departmentCode = bm.departmentCode
            LEFT JOIN enterpriseStructure es ON es.departmentCodeWeb = d2.departmentCode
            LEFT JOIN MedInspectOther MIO ON MIO.MedInspectCode = I.MedInspectCode AND MIO.Type = 8
            LEFT JOIN CiszResourceValue CRV ON MIO.CiszResourceValueCodeCentile = CRV.CiszResourceValueCode
            LEFT JOIN CiszLink CL ON I.MedInspectCode = CL.PrimaryKey AND CL.Type = 2 AND CL.TableName = 'MedInspect'
EOT;
if (empty($currId)) { $sqlTotal = <<<EOT
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $currentEnterprise
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
select
    COUNT(*) "total"
$sqlFrom
$sqlWhere
EOT;
$total = (int) selectSqlValue($db, $sqlTotal); $total >= $offset ?: $offset = 0; $sqlOrder = "order by \"$sort\" $dir"; $sqlLimit = "limit $limit offset $offset"; } else { $sqlOrder = $sqlLimit = ''; } if ($isCard) { $sqlCard = <<<EOT
       I.MedInspectCode "recordId",
       I.PlanDate "parentDate",
       I.PlanDate "recordDate",
       COALESCE(I.MedInspectCode, '')::text || '-' "path",
       22 "recordType",
       null "parentId",
       null "parentType",
       7 "sortType",
EOT;
} else { $sqlCard = ''; } $isDl = isUndefinedProperty($params, 'isDl'); if ($isDl) { $sqlDl = <<<EOT
                ,
                (
                    SELECT STRING_AGG(U.Value, ', ')
                    FROM MedInspectDlCategory MIDLC
                    lEFT JOIN UniDict U ON U.UniDictCode = MIDLC.UniDictCodeDlCategory
                    WHERE MIDLC.MedInspectCode = I.MedInspectCode
                )                                   "categoryDl",
                (
                    SELECT STRING_AGG(MDS.Cipher1 || ' - ' || MDS.Name, ', ')
                    FROM MedInspectResult MIR
                    LEFT JOIN MedDiagnosis MDS ON MDS.MedDiagnosisCode = MIR.MedDiagnosisCode
                    WHERE MIR.MedInspectCode = I.MedInspectCode
                        AND MIR.DecisionDiagnosis = 1
                )                                   "dlDiagnosis",
                (
                    SELECT CASE WHEN COALESCE(MIDLC.Flags, 2) = 1 THEN 1 ELSE 2 END
                    FROM MedInspectDlCategory MIDLC
                    WHERE MIDLC.MedInspectCode = I.MedInspectCode
                    LIMIT 1
                )                                   "conclusionFlag"
EOT;
$result = isUndefinedProperty($params, 'result'); $resultParam = ($result === 1 || $result === 2) ? $result : 0; if ($resultParam) { $sqlWhere .= ' AND EXISTS (SELECT 1 FROM MedInspectDlCategory MIDLC
                                            WHERE MIDLC.MedInspectCode = I.MedInspectCode AND
                                            MIDLC.Flags = ' . $resultParam . ' LIMIT 1)'; } $resultPeriod = isUndefinedProperty($params, 'resultPeriod'); $resultPeriod = is_numeric($resultPeriod) ? $resultPeriod : 0; if ($resultPeriod) { $sqlWhere .= " AND (date_part('year',I.ActualDate) - date_part('year',I.InspectDate)) = ". $resultPeriod; } } else { $sqlDl = ''; } $sql = <<<EOT
            WITH pg_ AS (
                SELECT MedPatientGroupCode gc
                FROM MedPatientGroup
                WHERE EnterpriseCode = $currentEnterprise
                    AND fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
            )
            SELECT
                $sqlCard
                I.MedInspectCode                    "id",
                I.EnterpriseCode                    "entrepriseId",
                I.PlanDate                          "planDate",
                I.MedPatientCode                    "patientId",
                I.MedPreventCode                    "preventGroup",
                I.ActualDate                        "actualDate",
                I.InspectKind                       "inspectKind",
                I.UniDictCodeDrComission            "udDrComissionId",
                I.UniDictCodeProfessionRW           "udProfessionRwId",
                I.UniDictCodeWorkCondition          "udWorkConditionId",
                I.DiagnosisNote                     "diagnosisNote",
                I.DocumentList                      "documentList",
                I.Information                       "information",
                CASE U.Value IS NULL
                        WHEN true THEN '<все>'
                        WHEN false THEN U.Value
                END                                 "professionRw",
                UWC.Value                           "workCondition",
                PR.Name                             "preventName",
                MPT.Name                            "preventTypeName",
                PR.MedPreventTypeCode               "preventTypeId",
                COALESCE(mpt.InputForm, 0)          "preventInputForm",
                P.NumberHistorySickness             "numberHistorySickness",
                asa_trim(M.SurName || ' ' || M.Name || ' ' || M.SecondName)::text
                                                    "patientFIO",
                PG.Name                             "patientGroup",
                PG.MedPatientGroupCode              "patientGroupId",
                asa_trim((
                    select string_agg((
                        asa_trim(E_.Name ||
                        CASE asa_trim(MPB_.ClassGroup) <> ''
                            WHEN true THEN ' - ' || MPB_.ClassGroup
                            END ||
                        CASE asa_trim(UD_.Value) <> ''
                            WHEN true THEN ' (' || UD_.Value || ')'
                            END))::text, ','
                    ORDER BY MPB_.StartDate)
                    from MedPatientBusy MPB_
                    join Enterprise E_ on E_.EnterpriseCode = MPB_.EnterpriseCode
                    left join UniDict UD_ on UD_.UniDictCode = MPB_.UniDictCodeProfession
                        and UD_.DictNumber = 19
                    left join EnterpriseKind EK_ on EK_.EnterpriseKindCode = MPB_.EnterpriseKindCode
                    where MPB_.MedPatientCode = I.MedPatientCode
                        and I.PlanDate between MPB_.StartDate
                        and MPB_.FinishDate)::text
                )                                   "patientBusy",
                I.InspectDate                       "inspectDate",
                I.MedDoctorCode                     "doctorId",
                asa_trim(MD.SurName || ' ' || MD.Name || ' ' || MD.SecondName)::text
                                                    "doctorFIO",
                I.Conclusion                        "finalNote",
                MP.DateOfBirth                      "dateOfBirth",
                FN_cmCalcAddress(MP.manCode, 0)     "address",
                FN_cmCalcAddress(MP.manCode, 1)     "addressReside",
                I.UniDictCodeResult                 "udResultId",
                I.TermExtraInspect                  "termExtraInspect",
                I.DocumentNumber                    "documentNumber",
                I.FlagsDl                           "flagsDl",
                I.DocumentDate                      "documentDate",
                I.CertificateDate                   "certificateDate",
                I.CertificateNumber                 "certificateNumber",
                I.ConclusionDate                    "conclusionDate",
                I.ConclusionNumber                  "conclusionNumber",
                I.CheckNumber                       "checkNumber",
                I.UniDictCodeDrComissionDirect      "udDrComissionDirectId",
                I.State                             "state",
                I.Checked                           "checked",
                I.MedInspectCodeParent              "inspectParentId",
                asa_trim((
                    select DATEFORMAT(MI_.InspectDate, 'dd.mm.yyyy' ) || ' ' || M_.SurName || ' ' || M_.Name || ' ' || M_.SecondName
                    from MedInspect MI_
                    left join MedDoctor MD_ on MD_.MedDoctorCode = MI_.MedDoctorCode
                    left join Man M_ on M_.ManCode = MD_.ManCode
                    where MI_.MedInspectCode = I.MedInspectCodeParent)) || ' ' ||
                    COALESCE((
                        select
                            string_agg((MCG_.Name)::text, ',' order by MC_.Name)
                        from MedInspectConclusion MIC_
                        join MedConclusionGroup MCG_ on MCG_.MedConclusionGroupCode = MIC_.MedConclusionGroupCode
                        join MedConclusion MC_ on MC_.MedConclusionCode = MCG_.MedConclusionCode
                        where MIC_.MedInspectCode = I.MedInspectCodeParent), '')::text
                                                    "inspectParentName",
                MP.PersonalNumber                   "personalNumber",
                (select MedInspectCode
                    from MedInspect
                    where MedInspectCodeParent = I.MedInspectCode
                    limit 1)                        "inspectDirectToCode",
                CASE ((select MedInspectCode
                        from MedInspect
                        where MedInspectCodeParent = I.MedInspectCode
                        limit 1)) IS NULL
                    WHEN true THEN ''
                    WHEN false THEN asa_trim((
                        select UD_.Cipher || ' - ' || UD_.Value || ' - ' || COALESCE(DATEFORMAT(MI_.PlanDate, 'dd.mm.yyyy' ) || ' ', '' )
                        from MedInspect MI_
                        left join UniDict UD_ on UD_.UniDictCode = MI_.UniDictCodeDrComission
                        left join MedDoctor MD_ on MD_.MedDoctorCode = MI_.MedDoctorCode
                        left join Man M_ on M_.ManCode = MD_.ManCode
                        where MI_.MedInspectCode = ((
                            select MedInspectCode
                            from MedInspect
                            where MedInspectCodeParent = I.MedInspectCode
                            limit 1)))) || ' ' ||
                            (select '(' || string_agg((MCG_.Name )::text, ',' order by MC_.Name) || ')'
                            from MedInspectConclusion MIC_
                            join MedConclusionGroup MCG_ on MCG_.MedConclusionGroupCode = MIC_.MedConclusionGroupCode
                            join MedConclusion MC_ on MC_.MedConclusionCode = MCG_.MedConclusionCode
                            where MIC_.MedInspectCode = ((
                                select MedInspectCode
                                from MedInspect
                                where MedInspectCodeParent = I.MedInspectCode
                                limit 1)))
                END::text                           "medInspectDirectToName",
                (select CASE EXISTS(
                    select 1
                    from (
                        select 1
                        from MedInspectResult MIR_
                        where MIR_.MedInspectCode = INS_.MedInspectCode
                        and MIR_.PlanDate is not null
                        and MIR_.InspectDate is null
                        limit 1
                    ) _MIR
                    union all
                    select 1
                    from (
                        select 1
                        from MedInspectResearch MIR_
                        where MIR_.MedInspectCode = INS_.MedInspectCode
                        and MIR_.PlanDate is not null
                        and MIR_.ResearchDate is null
                        limit 1
                    ) _MIRe
                )
                    WHEN true THEN 1
                    WHEN false THEN 0
                    END                             InvalidState
                from MedInspect INS_
                where INS_.MedInspectCode = I.MedInspectCode)
                                                    "invalidState",
                conclusion."conclusionGroupNames",
                conclusion."conclusionNames",
                conclusion."conclusionIds",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 1)               "height",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 2)               "weight",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 3)               "bodyMassIndex",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 4)               "waistCircumference",
                (SELECT valueString
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 5)               "arterialPressure",
                (SELECT valueString
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 6)               "intraOcularPressure",
                (SELECT valueDate
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
               AND type = 7)               "oralExamination",
                (SELECT COALESCE(valueNumber::text, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 10)               "score",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 11)               "regularPhysicalActivity",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 12)               "tobaccoUse",
                (SELECT valueDate
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 13)               "aOneDate",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 14)              "glucose",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 15)               "headCircumference",
                (SELECT COALESCE(valueNumber, valueString)
                    FROM medInspectOther
                    WHERE medInspectCode = i.medInspectCode
                        AND type = 16)              "chestCircumference",
                MIO.CiszResourceValueCodeCentile    "physicalDevCode",
                CRV.Name                            "physicalDevName",
                i.medDoctorCodeOther                "medDoctorOtherId",
                FN_cmFIO(d1.manCode)::TEXT          "anthropometricDataDoctorFio",
                CASE
                    WHEN mp1.name IS NOT NULL AND d1.departmentCode IS NOT NULL
                        THEN mp1.name || ' (' || FN_cmDepartmentName(d1.departmentCode, 1)::TEXT || ')'
                END                                 "anthropometricDataDoctorInfo",
                i.buhMolCodeOther                   "buhMolOtherId",
                FN_cmFIO(bm.manCode)                "anthropometricDataNurseFio",
                COALESCE(FN_cmDepartmentName(es.departmentCodeWeb, 1)::TEXT, d2.name)
                                                    "anthropometricDataNurseInfo",
                I.CiszResourceValueCodeRejectCause  "rejectionReasonId",
                COALESCE(CL.Status, 0)              "ciszStatus"
                $sqlDl
                $sqlFrom
                /* перенес из полей, немного ускоряет выборку */
                join lateral (
                    select
                        string_agg(MCG_.Name::text, ',' order by MC_.Name) "conclusionGroupNames",
                        string_agg(MC_.Name::text, ',' order by MC_.Name) "conclusionNames",
                        string_agg(MC_.MedConclusionCode::text, ',' order by MC_.Name) "conclusionIds"
                    from MedInspectConclusion MIC_
                    join MedConclusionGroup MCG_ on MCG_.MedConclusionGroupCode = MIC_.MedConclusionGroupCode
                    join MedConclusion MC_ on MC_.MedConclusionCode = MCG_.MedConclusionCode
                    where MIC_.MedInspectCode = I.MedInspectCode) conclusion on true
            $sqlWhere
            $sqlOrder
            $sqlLimit
EOT;
$ret = selectSql($db, $sql); if (empty($currId)) { $root = new stdClass(); $root->total = $total; $root->items = $ret; if ($isCard) { return $root->items; } else { return $root; } } else { return $ret; } } public function getLastInspectionIdIfOpened ($params) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $questionaryDate = isEmptyProperty($params, 'questionaryDate'); $sqlParams[':questionaryDate'] = dbDate($questionaryDate); $patientId = isEmptyProperty($params, 'patientId'); $patientSql = 'AND I.medPatientCode = :patientId'; $sqlParams[':patientId'] = $patientId; $preventId = isEmptyProperty($params, 'preventId'); $preventSql = 'AND I.medPreventCode = :preventId'; $sqlParams[':preventId'] = $preventId; if (!$questionaryDate || !$patientId || !$preventId) { throw new Error('Передайте необходимые параметры!'); } $sql = <<<"EOT"
SELECT
    I.medInspectCode "id",
    I.inspectDate "inspectDate"
FROM MedInspect I
WHERE I.planDate >= (:questionaryDate::DATE - '1 year'::INTERVAL)
$patientSql
$preventSql
ORDER BY I.planDate DESC
LIMIT 1
EOT;
$result = selectSql($db, $sql, $sqlParams); if (!empty($result) && $result[0]['inspectDate'] === null) { $params = new stdClass(); $params->currId = $result[0]['id']; return $this->readInspections($params)[0]; } return null; } public function readGroup($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = isEmptyProperty($params, 'enterpriseCode', 0); $enterpriseType = isEmptyProperty($params, 'enterpriseType', 0); $sql = <<<EOT
SELECT DISTINCT classgroup "name"
FROM medpatientbusy
where (enterprisecode = $enterpriseCode or 0 = $enterpriseCode )and (enterprisekindcode = $enterpriseType or 0 = $enterpriseType ) and classgroup != null
order by 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function planDelete($params = null) { $db = $this->db ?: $this->db = connectDb(); $inspectsId = isEmptyProperty($params, 'inspectsId', 0); $patientId = isEmptyProperty($params, 'patientId', 0); $doctorId = isEmptyProperty($params, 'doctorId', 0); $inspectKind = isEmptyProperty($params, 'inspectKind', 0); $drComission = isEmptyProperty($params, 'drComission', 0); $enterpriseCode = isEmptyProperty($params, 'enterpriseCode', 0); $preventType = isEmptyProperty($params, 'preventType', 0); $checkStartDate = $inspectsId < 0 ? 0 : 1; $checkFinishDate = $inspectsId < 0 ? 0 : 1; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $inspect = isEmptyProperty($params, 'inspect', 0); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Ymd", safe_strtotime('1900-01-01')); $finishDate = isEmptyProperty($params, 'endDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'endDate'))) : Date("Ymd", safe_strtotime('2035-01-01')); $preventId = isEmptyProperty($params, 'preventId',0); $patientGroupId = isEmptyProperty($params, 'patientGroupId', 0); $enterpriseType = isEmptyProperty($params, 'enterpriseType', 0); $classGroup = isEmptyProperty($params, 'classGroup', ''); $classGroup = "'$classGroup'"; $diagnosisId = isEmptyProperty($params, 'diagnosisId', 0); $conclusionId = isEmptyProperty($params, 'conclusionId', 0); $sql = <<<EOT
DELETE FROM MedInspect
where
 COALESCE(MedInspect.InspectDate,30000101) = 30000101
 and MedInspect.EnterpriseCode = $currentEnterprise
 and (select MedPreventTypeCode from MedPrevent where MedPreventCode = MedInspect.MedPreventCode LIMIT 1 ) = $preventType
 and ($checkStartDate = 0 or MedInspect.PlanDate >= ($startDate))
 and ($checkFinishDate = 0 or MedInspect.PlanDate <= ($finishDate))
 and ($preventId = 0 or MedInspect.MedPreventCode = $preventId)
 and ($inspect = 0 or ($inspect = 1 and MedInspect.InspectDate is not null) or ($inspect = 2 and MedInspect.InspectDate is null))
 and ((select MedPatientGroupCode
       from MedPatient
       where MedPatientCode = MedInspect.MedPatientCode) = $patientGroupId
       or $patientGroupId = 0)
 and ($enterpriseCode = 0 or COALESCE((select MedPatientBusyCode from MedPatientBusy
   where MedPatientCode = MedInspect.MedPatientCode and EnterpriseCode = $enterpriseCode
     and MedInspect.PlanDate between StartDate and FinishDate LIMIT 1 ),0) <> (0))
 and ($enterpriseType = 0 or COALESCE((select B2.MedPatientBusyCode from MedPatientBusy AS B2 join EnterpriseInKind AS K2 on K2.EnterpriseCode = B2.EnterpriseCode
       and K2.EnterpriseKindCode = $enterpriseType
   where B2.MedPatientCode = MedInspect.MedPatientCode and MedInspect.PlanDate between B2.StartDate and B2.FinishDate LIMIT 1 ),0) <> (0))
 and ($classGroup =  E'' or COALESCE((select MedPatientBusyCode from MedPatientBusy
   where MedPatientCode = MedInspect.MedPatientCode and ClassGroup like $classGroup
     and MedInspect.PlanDate between StartDate and FinishDate LIMIT 1 ),0) <> (0))
 and ($patientId = 0 or MedInspect.MedPatientCode = $patientId)
 and ($doctorId = 0 or MedInspect.MedDoctorCode = $doctorId)
 and ($diagnosisId = 0 or COALESCE((select MedInspectResultCode from MedInspectResult
   where MedInspectCode = MedInspect.MedInspectCode and MedDiagnosisCode = $diagnosisId LIMIT 1 ),0) <> (0))
 and ($conclusionId = 0 or COALESCE((select MedInspectConclusionCode from MedInspectConclusion
   where MedInspectCode = MedInspect.MedInspectCode and MedConclusionGroupCode = $conclusionId LIMIT 1 ),0) <> (0));
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPlanPatient($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = isEmptyProperty($params, 'enterpriseCode', 0); $type = isEmptyProperty($params, 'type', 0); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Y-m-d", safe_strtotime('1900-01-01')); $preventType = isEmptyProperty($params, 'preventType', 0); $preventId = isEmptyProperty($params, 'preventId', 0); $patientGroupId = isEmptyProperty($params, 'patientGroupId', 0); $planDate = '19000101'; $classGroup = isEmptyProperty($params, 'classGroup', ''); $classGroup = "'$classGroup'"; $planBirthYears = isEmptyProperty($params, 'planBirthYears'); $onlyRegesteredPations = isEmptyProperty($params, 'onlyRegesteredPations'); if ($preventType === 0) { $sql = <<<EOT
                SELECT
                    STRING_AGG((MedPreventCode)::TEXT, ',')
                FROM MedPrevent
                WHERE MedPreventTypeCode = $preventId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $preventType = $stmt->fetchAll(); $preventType = $preventType[0]['string_agg']; } if ($enterpriseCode != 0) { $enterpriseSql = <<<EOT
                AND (
                    SELECT MedPatientBusyCode
                    FROM MedPatientBusy
                    WHERE MedPatientCode = P_79.MedPatientCode
                        AND (EnterpriseCode = $enterpriseCode OR 0 = $enterpriseCode)
                        AND E'$startDate' BETWEEN StartDate AND FinishDate
                    LIMIT 1
                ) IS NOT NULL
EOT;
} else { $enterpriseSql = ""; } if ($classGroup != "''") { $classGroupSql = <<<EOT
                AND (
                    SELECT MedPatientBusyCode
                    FROM MedPatientBusy
                    WHERE MedPatientCode = P_79.MedPatientCode
                        AND (ClassGroup LIKE E$classGroup OR '' = $classGroup)
                        AND E'$startDate' BETWEEN StartDate AND FinishDate
                    LIMIT 1
                ) IS NOT NULL
EOT;
} else { $classGroupSql = ""; } if ($type === 2) { $kind = <<<EOT
                , YMD(
                    ((EXTRACT(YEAR FROM E'$startDate'))::integer),
                    ((EXTRACT(MONTH FROM PAS.DateOfBirth))::integer),
                    ((EXTRACT(DAY FROM PAS.DateOfBirth) - 1)::integer)
                ) DateOfBirth
EOT;
} else { $kind = ""; } $whereClause = ''; if (!empty($planBirthYears)) { $whereClause = ' AND birth_year' . createSqlInCondition($planBirthYears); } $onlyRegesteredPationsWhere = ''; $onlyRegesteredPationsJoin = ''; if ($onlyRegesteredPations) { $onlyRegesteredPationsWhere = <<<EOT
                AND (MPR.finishdate > CURRENT_DATE OR MPR.finishdate IS NULL)
EOT;
$onlyRegesteredPationsJoin = <<<EOT
                JOIN medpatientregistration MPR ON MPR.medpatientcode = T.MedPatientCode
EOT;
} $sql = <<<EOT
            WITH patient_data as (
                SELECT
                    T.MedPatientCode,
                    FN_medFindPrevent(T.MedPatientCode, E'$preventType', E'$planDate', E'$startDate') MedPreventCode,
                    EXTRACT(YEAR FROM pas.dateofbirth) birth_year
                    $kind
                FROM (
                    SELECT P_79.MedPatientCode
                    FROM MedPatient P_79
                    WHERE P_79.EnterpriseCode = 1
                        AND COALESCE(P_79.FlagGroup, 0) = 0
                        AND E'$startDate' BETWEEN P_79.StartDate AND P_79.FinishDate
                        AND (P_79.MedPatientGroupCode = $patientGroupId OR 0 = $patientGroupId)
                        $enterpriseSql
                        $classGroupSql
                        AND NOT EXISTS(
                            SELECT
                                I2.MedInspectCode,
                                DATEADD('day', -1, DATEADD('Year', 1, E'$startDate')) d,
                                DATEADD('month', -P2.PeriodMonth, (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate')))) StartDate2
                            FROM MedInspect I2
                            JOIN MedPrevent P2 ON P2.MedPreventCode = I2.MedPreventCode
                            WHERE I2.MedPatientCode = P_79.MedPatientCode
                                AND NOT (COALESCE(ActualDate, 30000101) BETWEEN E'$startDate' AND (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate'))))
                                AND i2.PlanDate >= ((DATEADD('month', -P2.PeriodMonth, (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate'))))))
                                AND (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate'))) < DATEADD('month', p2.PeriodMonth, i2.PlanDate)
                                AND (
                                        (
                                            i2.InspectDate between (DATEADD('month', -P2.PeriodMonth, (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate')))))
                                                AND (DATEADD('day', -1, DATEADD('Year', 1, E'$startDate')))
                                        )
                                        OR (COALESCE(i2.InspectDate, E'30000101') = E'30000101')
                                )
                                AND P2.MedPreventTypeCode = $preventId
                        )
                ) AS T (MedPatientCode)
                JOIN MedPatient P ON P.MedPatientCode = T.MedPatientCode
                JOIN ManPassport PAS ON PAS.ManCode = P.ManCode
                $onlyRegesteredPationsJoin
                WHERE (FN_medFindPrevent(T.MedPatientCode, E'$preventType', E'$planDate', E'$startDate')) IS NOT NULL
                $onlyRegesteredPationsWhere
            )
            SELECT *
            FROM patient_data
            WHERE 1 = 1
                $whereClause
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function generatePlanInspectResult($params = null) { $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Ymd", safe_strtotime('19000101')); $patientId = isEmptyProperty($params, 'patientId'); $db = isEmptyProperty($params, 'db'); $medInspectCode = isEmptyProperty($params, 'medInspectCode'); $existingProfessionIds = isEmptyProperty($params, 'existingProfessionIds', []); $newPreventId = isEmptyProperty($params, 'newPreventId'); $driverLicense = isEmptyProperty($params, 'driverLicense', 0); $prevent = $driverLicense ? $newPreventId : 'I.MedPreventCode'; $primaryDiagnosis = isEmptyProperty($params, 'primaryDiagnosis', 'NULL'); if ($db) { $sql = <<<SQL
                SELECT
                    (
                        SELECT MIR.InspectDate
                        FROM MedInspectResult AS MIR
                        JOIN MedInspect AS MI ON MI.MedInspectCode = MIR.MedInspectCode AND MI.MedPatientCode = $patientId
                        JOIN MedPrevent AS P ON P.MedPreventCode = MI.MedPreventCode
                        WHERE MIR.InspectDate IS NOT NULL
                        ORDER BY MIR.InspectDate DESC
                        LIMIT 1
                    )                       LastDirectionInspectDate,
                    PD.PeriodMonth          DirectionPeriodMonth,
                    PD.MedProfessionCode,
                    PD.FlagCondition
                FROM MedInspect I
                JOIN MedPreventDirection PD ON PD.MedPreventCode = I.MedPreventCode
                    AND PD.MedProfessionCode IS NOT NULL
                WHERE I.MedInspectCode = $medInspectCode
                SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $val = $stmt->fetchAll(); $union = ''; foreach ($val as $key => $row) { if (!$row['flagcondition']) { $lastDirDate = $row['lastdirectioninspectdate']; $startDateYear = new DateTime($startDate); date_date_set($startDateYear, date_format(new DateTime($startDate), "Y"), 1, 1); $startDateYear = date_format($startDateYear, "Y-m-d"); $directionPeriodMonth = $row['directionperiodmonth'] ? $row['directionperiodmonth'] : 0; if ($lastDirDate) { $lastDirDate = new DateTime($lastDirDate); date_modify($lastDirDate, "+" . $directionPeriodMonth . " month"); $lastDirDate = date_format($lastDirDate, "Y-m-d"); $date_arr = array(0 => $startDateYear, 1 => $lastDirDate); usort($date_arr, function ($a, $b) { $dateTimestamp1 = safe_strtotime($a); $dateTimestamp2 = safe_strtotime($b); return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1; }); $planDate = $date_arr[count($date_arr) - 1]; } else { $planDate = $startDateYear; } $profession = $row['medprofessioncode']; while (safe_strtotime($planDate) <= safe_strtotime($startDate)) { $union .= " SELECT $medInspectCode, $profession, '$planDate', $primaryDiagnosis UNION ALL"; $planDate = new DateTime($planDate ?? ''); $month = $directionPeriodMonth != 0 ? $directionPeriodMonth : 12; date_modify($planDate, "+" . $month . " month"); $planDate = date_format($planDate, "Y-m-d"); } } else { $profession = $row['medprofessioncode']; $union .= " SELECT $medInspectCode, $profession, NULL, $primaryDiagnosis UNION ALL"; } } $union = substr($union, 0, -10); if ($union) { $sql = <<<SQL
                    INSERT INTO MedInspectResult (
                        MedInspectCode, MedProfessionCode, PlanDate,
                        PrimaryDiagnosis
                    )
                    $union
                    SQL;
} else { $sql = <<<SQL
                    INSERT INTO MedInspectResult (
                        MedInspectCode, MedProfessionCode, PrimaryDiagnosis
                    )
                    SELECT
                        I.MedInspectCode,
                        PD.MedProfessionCode,
                        $primaryDiagnosis
                    FROM MedInspect I
                    JOIN MedPreventDirection PD ON PD.MedPreventCode = I.MedPreventCode
                        AND PD.MedProfessionCode IS NOT NULL
                    WHERE I.MedInspectCode = $medInspectCode
                    SQL;
} $stmt = $db->prepare($sql); $stmt->execute(); } else { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $sql = <<<SQL
                    SELECT
                        (
                            SELECT MIR.InspectDate
                            FROM MedInspectResult MIR
                            JOIN MedInspect MI ON MI.MedInspectCode = MIR.MedInspectCode AND MI.MedPatientCode = $patientId
                            JOIN MedPrevent P ON P.MedPreventCode = MI.MedPreventCode
                            WHERE MIR.InspectDate IS NOT NULL
                            ORDER BY MIR.InspectDate DESC
                            LIMIT 1
                        )                       LastDirectionInspectDate,
                        PD.PeriodMonth          DirectionPeriodMonth,
                        PD.MedProfessionCode,
                        PD.FlagCondition
                    FROM MedInspect I
                    JOIN MedPreventDirection PD ON PD.MedPreventCode = $prevent
                        AND PD.MedProfessionCode IS NOT NULL
                    WHERE I.MedInspectCode = $medInspectCode
                    SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $val = $stmt->fetchAll(); $union = ''; foreach ($val as $key => $row) { if (!$row['flagcondition']) { $lastDirDate = $row['lastdirectioninspectdate']; $startDateYear = new DateTime($startDate); date_date_set($startDateYear, date_format(new DateTime($startDate), "Y"), 1, 1); $startDateYear = date_format($startDateYear, "Y-m-d"); $directionPeriodMonth = $row['directionperiodmonth'] ? $row['directionperiodmonth'] : 0; if ($lastDirDate) { $lastDirDate = new DateTime($lastDirDate); date_modify($lastDirDate, "+" . $directionPeriodMonth . " month"); $lastDirDate = date_format($lastDirDate, "Y-m-d"); $date_arr = array(0 => $startDateYear, 1 => $lastDirDate); usort($date_arr, function ($a, $b) { $dateTimestamp1 = safe_strtotime($a); $dateTimestamp2 = safe_strtotime($b); return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1; }); $planDate = $date_arr[count($date_arr) - 1]; } else { $planDate = $startDateYear; } $profession = $row['medprofessioncode']; if (count($existingProfessionIds) && in_array($profession, $existingProfessionIds)) { continue; } while (safe_strtotime($planDate) <= safe_strtotime($startDate)) { $union .= " SELECT $medInspectCode, $profession, '$planDate', $primaryDiagnosis UNION ALL"; $planDate = new DateTime($planDate ?? ''); $month = $directionPeriodMonth != 0 ? $directionPeriodMonth : 12; date_modify($planDate, "+" . $month . " month"); $planDate = date_format($planDate, "Y-m-d"); } } else { $profession = $row['medprofessioncode']; if (count($existingProfessionIds) && in_array($profession, $existingProfessionIds)) { continue; } $union .= " SELECT $medInspectCode, $profession, NULL, $primaryDiagnosis UNION ALL"; } } $union = substr($union, 0, -10); if ($union) { $sql = <<<SQL
                        INSERT INTO MedInspectResult (
                            MedInspectCode, MedProfessionCode, PlanDate,
                            PrimaryDiagnosis
                        )
                        $union
                        SQL;
} else { $sql = 'SELECT 1'; if (!$driverLicense) { $sql = <<<SQL
                        INSERT INTO MedInspectResult (
                            MedInspectCode, MedProfessionCode, PrimaryDiagnosis
                        )
                        SELECT
                            I.MedInspectCode,
                            PD.MedProfessionCode,
                            $primaryDiagnosis
                        FROM MedInspect I
                        JOIN MedPreventDirection PD ON PD.MedPreventCode = I.MedPreventCode
                        AND PD.MedProfessionCode IS NOT NULL
                        WHERE I.MedInspectCode = $medInspectCode
                        SQL;
} } $stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } public function generatePlanInspectResearch($params = null) { $patientId = isEmptyProperty($params, 'patientId'); $db = isEmptyProperty($params, 'db'); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Ymd", safe_strtotime('19000101')); $medInspectCode = isEmptyProperty($params, 'medInspectCode'); if ($db) { $sql = <<<EOT
SELECT
    (
        SELECT
            MIR.ResearchDate
        FROM MedInspectResearch AS MIR
        JOIN MedInspect AS MI ON MI.MedInspectCode = MIR.MedInspectCode
            AND MI.MedPatientCode = $patientId
        JOIN MedPrevent AS P on P.MedPreventCode = MI.MedPreventCode
        WHERE MIR.ResearchDate IS NOT NULL
        ORDER BY MIR.ResearchDate DESC  LIMIT 1
    ) as LastDirectionResearchDate,
    pd.PeriodMonth as DirectionPeriodMonth,
    pd.UniDictCodeResearch,
    pd.FlagCondition
FROM MedInspect AS i
JOIN MedPreventDirection AS pd ON pd.MedPreventCode = i.MedPreventCode
    AND pd.UniDictCodeResearch IS NOT NULL
WHERE i.MedInspectCode = $medInspectCode;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = $stmt->fetchAll(); $union = ''; foreach ($val as $key => $row) { $researchCode = $row['unidictcoderesearch']; if (!$row['flagcondition']) { $lastDirDate = $row['lastdirectionresearchdate']; $startDateYear = new DateTime($startDate); date_date_set($startDateYear, date_format(new DateTime($startDate), "Y"), 1, 1); $startDateYear = date_format($startDateYear, "Y-m-d"); $directionPeriodMonth = $row['directionperiodmonth'] ? $row['directionperiodmonth'] : 0; if ($lastDirDate) { $lastDirDate = new DateTime($lastDirDate); date_modify($lastDirDate, "+" . $directionPeriodMonth . " month"); $lastDirDate = date_format($lastDirDate, "Y-m-d"); $date_arr = array(0 => $startDateYear, 1 => $lastDirDate); usort($date_arr, function ($a, $b) { $dateTimestamp1 = safe_strtotime($a); $dateTimestamp2 = safe_strtotime($b); return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1; }); $planDate = $date_arr[count($date_arr) - 1]; } else { $planDate = $startDateYear; } while (safe_strtotime($planDate) <= safe_strtotime($startDate)) { $union .= " SELECT $medInspectCode,$researchCode,'$planDate' UNION ALL"; $planDate = new DateTime($planDate ?? ''); $month = $directionPeriodMonth != 0 ? $directionPeriodMonth : 12; date_modify($planDate, "+" . $month . " month"); $planDate = date_format($planDate, "Y-m-d"); } } else { $union .= " SELECT $medInspectCode,$researchCode,NULL UNION ALL"; } if ($key === count($val) - 1 && $union != null) { $union = substr($union, 0, -10); } } if ($union) { $sql = <<<EOT
INSERT INTO MedInspectResearch (MedInspectCode, UniDictCodeResearch, PlanDate)
$union
EOT;
} else { $sql = <<<EOT
INSERT INTO MedInspectResearch (medinspectcode, unidictcoderesearch)
SELECT
    i.MedInspectCode,
    pd.UniDictCodeResearch
FROM MedInspect AS i
JOIN MedPreventDirection AS pd ON pd.MedPreventCode = i.MedPreventCode
    AND pd.UniDictCodeResearch IS NOT NULL
WHERE i.MedInspectCode = $medInspectCode;

EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); } else { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $sql = <<<EOT
SELECT
    (
        SELECT
           MIR.ResearchDate
        FROM MedInspectResearch AS MIR
        JOIN MedInspect AS MI ON MI.MedInspectCode = MIR.MedInspectCode AND MI.MedPatientCode = $patientId
        JOIN MedPrevent AS P on P.MedPreventCode = MI.MedPreventCode
        WHERE MIR.ResearchDate IS NOT NULL
        ORDER BY MIR.ResearchDate DESC  LIMIT 1
    ) as LastDirectionResearchDate,
    pd.PeriodMonth as DirectionPeriodMonth,
    pd.UniDictCodeResearch,
    pd.FlagCondition
FROM MedInspect AS i
JOIN MedPreventDirection AS pd ON pd.MedPreventCode = i.MedPreventCode
    AND pd.UniDictCodeResearch IS NOT NULL
WHERE i.MedInspectCode = $medInspectCode;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = $stmt->fetchAll(); $union = ''; foreach ($val as $key => $row) { $researchCode = $row['unidictcoderesearch']; if (!$row['flagcondition']) { $lastDirDate = $row['lastdirectionresearchdate']; $startDateYear = new DateTime($startDate); date_date_set($startDateYear, date_format(new DateTime($startDate), "Y"), 1, 1); $startDateYear = date_format($startDateYear, "Y-m-d"); $directionPeriodMonth = $row['directionperiodmonth'] ? $row['directionperiodmonth'] : 0; if ($lastDirDate) { $lastDirDate = new DateTime($lastDirDate); date_modify($lastDirDate, "+" . $directionPeriodMonth . " month"); $lastDirDate = date_format($lastDirDate, "Y-m-d"); $date_arr = array(0 => $startDateYear, 1 => $lastDirDate); usort($date_arr, function ($a, $b) { $dateTimestamp1 = safe_strtotime($a); $dateTimestamp2 = safe_strtotime($b); return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1; }); $planDate = $date_arr[count($date_arr) - 1]; } else { $planDate = $startDateYear; } while (safe_strtotime($planDate) <= safe_strtotime($startDate)) { $union .= " SELECT $medInspectCode,$researchCode,'$planDate' UNION ALL"; $planDate = new DateTime($planDate); $month = $directionPeriodMonth != 0 ? $directionPeriodMonth : 12; date_modify($planDate, "+" . $month . " month"); $planDate = date_format($planDate, "Y-m-d"); } } else { $union .= " SELECT $medInspectCode,$researchCode,NULL UNION ALL"; } if ($key === count($val) - 1 && $union != null) { $union = substr($union, 0, -10); } } if ($union) { $sql = <<<EOT
INSERT INTO MedInspectResearch (MedInspectCode, UniDictCodeResearch, PlanDate)
$union
EOT;
} else { $sql = <<<EOT
INSERT INTO MedInspectResearch (medinspectcode , unidictcoderesearch)
SELECT
    i.MedInspectCode,
    pd.UniDictCodeResearch
FROM MedInspect AS i
JOIN MedPreventDirection AS pd ON pd.MedPreventCode = i.MedPreventCode
    AND pd.UniDictCodeResearch IS NOT NULL
WHERE i.MedInspectCode = $medInspectCode;
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } public function generatePlanInspect($params = null) { $db = $this->db ?: $this->db = connectDb(); $patients = isEmptyProperty($params, 'patients'); $enterpriseCode = isEmptyProperty($params, 'enterpriseCode', 0); $preventId = isEmptyProperty($params, 'preventId', 0); $currentEnterprise = getSessionValue('mapEnterpriseCode'); $startDateTime = isEmptyProperty($params, 'startDate') ? Date("Y-m-d H:m:s", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Y-m-d H:m:s", safe_strtotime('1900-01-01')); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date("Ymd", safe_strtotime('19000101')); $db->beginTransaction(); try { foreach ($patients as $patient) { $patientId = $patient->medpatientcode; $patientPrevent = $patient->medpreventcode; $startDateTime = $startDate ? Date("Y-m-d H:m:s", safe_strtotime($startDate)) : Date("Y-m-d H:m:s", safe_strtotime('1900-01-01')); $startDateTime = property_exists($patient, 'dateofbirth') ? Date("Y-m-d H:m:s", safe_strtotime($patient->dateofbirth)) : $startDateTime; $prevMedInspectCode = null; if (!property_exists($patient, 'dateofbirth')) { $sql = <<<SQL
                        SELECT
                            I2.MedInspectCode,
                            COALESCE(I2.ActualDate, DATEADD('MONTH', P2.PeriodMonth, i2.PlanDate)),
                            DATEADD('MONTH', P2.PeriodMonth, i2.PlanDate)
                        FROM MedInspect I2
                        JOIN MedPrevent P2 ON P2.MedPreventCode = I2.MedPreventCode
                        WHERE I2.EnterpriseCode = $currentEnterprise
                            AND I2.MedPatientCode = $patientId
                            AND P2.MedPreventTypeCode = '$preventId'
                            AND i2.InspectDate IS NOT NULL
                            AND I2.PlanDate < '$startDateTime'::timestamp
                        ORDER BY I2.InspectDate DESC
                        LIMIT 1
                        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $val = $stmt->fetchAll(); if ($val) { $prevMedInspectCode = $val[0]['medinspectcode']; $coalesce = $val[0]['coalesce']; $dateadd = $val[0]['dateadd']; $date_arr = array( 0 => $startDateTime, 1 => $coalesce, 2 => $dateadd ); usort($date_arr, function ($a, $b) { $dateTimestamp1 = safe_strtotime($a); $dateTimestamp2 = safe_strtotime($b); return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1; }); $startDateTime = $date_arr[count($date_arr) - 1]; } } $sql = <<<SQL
                    SELECT
                        COUNT(*)
                    FROM MedInspect
                    WHERE MedPatientCode = $patientId
                        AND EnterpriseCode = $currentEnterprise
                        AND MedPreventCode = $patientPrevent
                        AND PlanDate = '$startDateTime'
                    SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $count = fetchValue($stmt, 'count'); $medInspectCode = null; if (!$count) { $sql = <<<SQL
                        INSERT INTO MedInspect (
                            EnterpriseCode, PlanDate, MedPatientCode, MedPreventCode
                        )
                        VALUES ($currentEnterprise, '$startDateTime'::TIMESTAMP, $patientId, $patientPrevent)
                        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $sql = <<<SQL
                        SELECT LASTVAL() "medInspect"
                        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $medInspectCode = fetchValue($stmt, 'medInspect'); if ($prevMedInspectCode) { $sql = <<<SQL
                            INSERT INTO MedInspectConclusion (
                                MedInspectCode, MedConclusionGroupCode, MedConclusionCode, Note
                            )
                            SELECT $medInspectCode, MedConclusionGroupCode, MedConclusionCode, Note
                            FROM MedInspectConclusion
                            WHERE MedInspectCode = $prevMedInspectCode
                            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); } $param = new stdClass(); $param->db = $db; $param->patientId = $patientId; $param->medInspectCode = $medInspectCode; $param->startDate = $startDateTime; $param->primaryDiagnosis = isEmptyProperty($params, 'primaryDiagnosis'); $this->generatePlanInspectResult($param); $this->generatePlanInspectResearch($param); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $medInspectCode; } public function calcResearch($params = null) { $inspectionsId = isEmptyProperty($params, 'inspectionsId'); $patientId = isEmptyProperty($params, 'patientId'); $planDate = isEmptyProperty($params, 'planDate') ? safe_strtotime(isEmptyProperty($params, 'planDate')) : null; $inspectDate = isEmptyProperty($params, 'inspectDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'inspectDate'))) : null; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $finishDate = $inspectDate ? $inspectDate : ($planDate ? Date("Ymd", $planDate) : date("Ymd")); $startDate = new DateTime($finishDate); $startDate->modify('first day of this month'); $startDate->modify('-1 year'); $startDate = $startDate->format("Ymd"); $year = date('Y', $planDate); $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT
    MedLogStudyCode,
    medFluorCode,
    MedDiagnosisCode,
    ExecTime,
    UniDictCodeResearch,
    Conclusion,
    MedVisitDocumentCode,
    MedDoctorCode,
    ClinicalDiagnosis,
    KindDiagCode,
    CancerCode,
    Note,
    AnatomicIds
FROM (
    SELECT
        MLS.MedLogStudyCode as MedLogStudyCode,
        null as medFluorCode,
        MLS.MedDiagnosisCode as MedDiagnosisCode,
        MLS.ExecTime as ExecTime,
        MR.UniDictCode as UniDictCodeResearch,
        CAST(FN_htmMask2Str(mls.htmPageCode, COALESCE((
          SELECT resultTemplate
          FROM medResearchRow
          WHERE medResearchCode = mr.medResearchCode
            AND medStudyCode = mls.MedStudyCode
        ), ms.ResultMask)) AS varchar(255)) as Conclusion,
        null as MedVisitDocumentCode,
        -- WS-11508: самый свежий результат на каждый шаблон наименования (к наименованию может быть привязано несколько шаблонов)
        ROW_NUMBER() OVER (PARTITION BY MR.UniDictCode, MLS.MedStudyCode ORDER BY MLS.ExecTime DESC) as rn,
        MLS.MedDoctorCodeExec AS MedDoctorCode,
        NULL AS ClinicalDiagnosis,
        NULL AS KindDiagCode,
        NULL AS CancerCode,
        MLS.Note AS Note,
        NULL::integer[] AS AnatomicIds
    FROM
        MedLogStudy AS MLS
        LEFT JOIN MedStudy AS ms ON ms.MedStudyCode = mls.MedStudyCode
        LEFT JOIN MedResearch AS MR ON MR.MedResearchCode IN (
            SELECT TMR.MedResearchCode
            FROM MedResearch AS TMR
            JOIN medResearchRow AS MRR_ ON MRR_.medResearchCode = TMR.medResearchCode
            AND MRR_.medStudyCode = MLS.medStudyCode
    )
    WHERE MLS.MedPatientCode = $patientId
      AND MR.UniDictCode IS NOT NULL
      AND MLS.ExecTime IS NOT NULL
      AND (
        (COALESCE(MS.Type, 0) = 1 AND COALESCE(MLS.ExecTime, 19000101) BETWEEN $startDate AND $finishDate)
        OR (COALESCE(MS.Type, 0) <> 1 AND EXTRACT(YEAR FROM MLS.ExecTime)::integer = $year)
      )
      AND MLS.MedStudyCode IN (
        SELECT MRR.MedStudyCode
        FROM MedResearch AS T
        JOIN MedResearchRow AS MRR ON MRR.medResearchCode = T.medResearchCode
        WHERE T.UniDictCode IN (
          SELECT UniDictCodeResearch FROM MedInspectResearch WHERE MedInspectCode = $inspectionsId
        )
        AND MRR.MedStudyCode IS NOT NULL
      )
) AS s
WHERE s.rn = 1

UNION ALL

SELECT
  null as MedLogStudyCode,
  MF.medFluorCode as medFluorCode,
  null as MedDiagnosisCode,
  MF.OnDate as ExecTime,
  mr_740.UniDictCode as UniDictCodeResearch,
  MF.Conclusion as Conclusion,
  null as MedVisitDocumentCode,
  NULL AS MedDoctorCode,
  NULL AS ClinicalDiagnosis,
  NULL AS KindDiagCode,
  NULL AS CancerCode,
  MF.Note AS Note,
  NULL::integer[] AS AnatomicIds
FROM medFluor AS MF
LEFT JOIN MedResearch AS mr_740 ON mr_740.MedResearchCode = (
  SELECT MAX(TMR.MedResearchCode)
  FROM MedResearch AS TMR
  WHERE TMR.medPreventTypeCode = MF.medPreventTypeCode
)
WHERE MF.MedPatientCode = $patientId
  AND MF.EnterpriseCode = $enterpriseCode
  AND MF.OnDate = (
    SELECT MAX(mfl.OnDate)
    FROM medFluor AS mfl
    WHERE mfl.medPreventTypeCode = MF.medPreventTypeCode
      AND mfl.MedPatientCode = MF.MedPatientCode
  )
  AND MF.medPreventTypeCode IN (
    SELECT medPreventTypeCode
    FROM MedPreventType
    WHERE EnterpriseCode = $enterpriseCode
      AND InputForm = 1
)

UNION ALL

SELECT
  MedLogStudyCode,
  medFluorCode,
  MedDiagnosisCode,
  ExecTime,
  UniDictCodeResearch,
  Conclusion,
  MedVisitDocumentCode,
  MedDoctorCode,
  ClinicalDiagnosis,
  KindDiagCode,
  CancerCode,
  Note,
  AnatomicIds
FROM (
  SELECT
    null as MedLogStudyCode,
    null as medFluorCode,
    MVP.meddiagnosiscode as MedDiagnosisCode,
    MVD.CreateTime as ExecTime,
    MR.UniDictCode as UniDictCodeResearch,
    CAST(FN_htmMask2Str(MVD.htmPageCode, COALESCE((
        SELECT resultTemplate
        FROM medResearchRow
        WHERE medResearchCode = MR.medResearchCode
          AND HtmTemplateCode = MVD.HtmTemplateCode
      ), HT.ResultMask)) AS varchar(255)) as Conclusion,
    MVD.MedVisitDocumentCode as MedVisitDocumentCode,
    -- WS-11508: самый свежий документ на каждый шаблон наименования
    ROW_NUMBER() OVER (PARTITION BY MR.UniDictCode, MVD.HtmTemplateCode ORDER BY MVD.CreateTime DESC) as rn,
    MV.MedDoctorCode AS MedDoctorCode,
    MVPE.ClinicalDiagnosis AS ClinicalDiagnosis,
    MVPE.CiszResourceValueCodeDiagKind AS KindDiagCode,
    MVPE.CiszResourceValueCodeCancerStage AS CancerCode,
    MVPE.Note AS Note,
    COALESCE(MVPA.AnatomicIds, '{}') AS AnatomicIds
  FROM MedResearchRow MRR
  JOIN MedResearch AS MR ON MR.MedResearchCode = MRR.MedResearchCode
  JOIN medvisitdocument AS MVD ON MVD.HtmTemplateCode = MRR.HtmTemplateCode
  JOIN htmtemplate AS HT ON HT.HtmTemplateCode = MRR.HtmTemplateCode
  JOIN medvisitpatient AS MVP ON MVP.medvisitpatientcode = MVD.medvisitpatientcode
  LEFT JOIN MedVisit AS MV ON MV.MedVisitCode = MVP.MedVisitCode
  LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
  LEFT JOIN (
      SELECT
          MedVisitPatientCode,
          array_agg(CiszResourceValueCodeAnatomic) FILTER (WHERE CiszResourceValueCodeAnatomic IS NOT NULL) AS AnatomicIds
      FROM MedVisitPatientAnatomic
      GROUP BY MedVisitPatientCode
  ) MVPA ON MVPA.MedVisitPatientCode = MVP.MedVisitPatientCode
  WHERE MR.UniDictCode IN (
    SELECT UniDictCodeResearch
    FROM MedInspectResearch
    WHERE MedInspectCode = $inspectionsId
  )
  AND MVP.MedPatientCode = $patientId
  AND MVD.CreateTime::date BETWEEN '$startDate' AND '$finishDate'
) AS t
WHERE t.rn = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $researchArray = $stmt->fetchAll(); $db->beginTransaction(); try { foreach ($researchArray as $row) { $medFluorCode = $row['medfluorcode'] ? $row['medfluorcode'] : 'null'; $medLogStudyCode = $row['medlogstudycode'] ? $row['medlogstudycode'] : 'null'; $medDiagnosisCode = $row['meddiagnosiscode'] ? $row['meddiagnosiscode'] : 'null'; $execTime = $row['exectime'] ? date('Ymd', safe_strtotime($row['exectime'])) : 'null'; $uniDictCodeResearch = $row['unidictcoderesearch'] ? $row['unidictcoderesearch'] : 'null'; $medVisitDocumentCode = $row['medvisitdocumentcode'] ?? 'null'; $medDoctorCode = $row['meddoctorcode'] ?? 'null'; $note = isset($row['note']) ? $db->quote($row['note']) : 'null'; $clinicalDiad = isset($row['clinicaldiagnosis']) ? $db->quote($row['clinicaldiagnosis']) : 'NULL'; $kindDiag = $row['ciszresourcevaluecodediagkind'] ?? 'null'; $cancerStage = $row['ciszresourcevaluecodecancerstage'] ?? 'null'; $anatomicIds = []; if (!empty($row['anatomicids'])) { $trimmed = trim($row['anatomicids'], '{}'); if ($trimmed !== '') { $anatomicIds = array_map('intval', explode(',', $trimmed)); } } if ($uniDictCodeResearch === 'null') { continue; } $conclusion = $row['conclusion'] ? $row['conclusion'] : ''; if ($medLogStudyCode !== 'null') { $sourceCond = "AND COALESCE(MedLogStudyCode, $medLogStudyCode) = $medLogStudyCode"; } elseif ($medVisitDocumentCode !== 'null') { $sourceCond = "AND COALESCE(MedVisitDocumentCode, $medVisitDocumentCode) = $medVisitDocumentCode"; } elseif ($medFluorCode !== 'null') { $sourceCond = "AND COALESCE(MedFluorCode, $medFluorCode) = $medFluorCode"; } else { $sourceCond = ''; } $sql = <<<EOT
SELECT
    MedInspectResearchCode "id"
FROM MedInspectResearch "idconclusion"
  WHERE MedInspectCode = $inspectionsId
  AND UniDictCodeResearch =$uniDictCodeResearch
  AND COALESCE(ResearchDate,19000101) = '$execTime'
  $sourceCond LIMIT 1 ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = fetchValue($stmt, 'id'); if (!$val) { $sql = <<<EOT
SELECT  MedInspectResearchCode "id" FROM MedInspectResearch
 WHERE MedInspectCode = $inspectionsId
 AND UniDictCodeResearch = $uniDictCodeResearch
 AND ResearchDate IS NULL
 $sourceCond
 ORDER BY COALESCE(PlanDate,30000101) ASC LIMIT 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = fetchValue($stmt, 'id'); } else { $sql = <<<EOT
UPDATE MedInspectResearch SET
   ResearchDate = $execTime,
   MedLogStudyCode = $medLogStudyCode,
   MedFluorCode = $medFluorCode,
   MedDiagnosisCode = $medDiagnosisCode,
   Conclusion = '$conclusion',
   MedVisitDocumentCode = $medVisitDocumentCode,
   ClinicalDiagnosis = $clinicalDiad,
   CiszResourceValueCodeDiagKind = $kindDiag,
   CiszResourceValueCodeCancerStage = $cancerStage,
   Note = $note,
   MedDoctorCode = $medDoctorCode
   WHERE MedInspectResearchCode = $val
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (!empty($anatomicIds)) { $inspectNotExecute = true; require_once(__DIR__ . '/inspectionsResearches.php'); $inspectResearch = new med_examinations_inspectionsResearches(); $inspectResearch->updateAnatomic((object)[ 'inspectId' => $val, 'items' => array_map(fn($id) => (object)['id' => $id], $anatomicIds) ]); unset($inspectNotExecute); } continue; } if ($val) { $sql = <<<EOT
UPDATE MedInspectResearch SET
   ResearchDate = $execTime,
   MedLogStudyCode = $medLogStudyCode,
   MedFluorCode = $medFluorCode,
   MedDiagnosisCode = $medDiagnosisCode,
   Conclusion = '$conclusion',
   MedVisitDocumentCode = $medVisitDocumentCode,
   ClinicalDiagnosis = $clinicalDiad,
   CiszResourceValueCodeDiagKind = $kindDiag,
   CiszResourceValueCodeCancerStage = $cancerStage,
   Note = $note,
   MedDoctorCode = $medDoctorCode
   WHERE MedInspectResearchCode = $val
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (!empty($anatomicIds)) { $inspectNotExecute = true; require_once(__DIR__ . '/inspectionsResearches.php'); $inspectResearch = new med_examinations_inspectionsResearches(); $inspectResearch->updateAnatomic((object)[ 'inspectId' => $val, 'items' => array_map(fn($id) => (object)['id' => $id], $anatomicIds) ]); unset($inspectNotExecute); } } else { $sql = <<<EOT
INSERT INTO MedInspectResearch (medinspectcode ,researchdate , medlogstudycode ,medfluorcode ,unidictcoderesearch ,meddiagnosiscode , conclusion, MedVisitDocumentCode,
                                ClinicalDiagnosis, CiszResourceValueCodeDiagKind, CiszResourceValueCodeCancerStage, Note, MedDoctorCode)
VALUES ($inspectionsId,$execTime,$medLogStudyCode,$medFluorCode,$uniDictCodeResearch,$medDiagnosisCode,'$conclusion', $medVisitDocumentCode,
        $clinicalDiad, $kindDiag, $cancerStage, $note, $medDoctorCode);
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $inspectId = $db->lastInsertId(); if (!empty($anatomicIds)) { $inspectNotExecute = true; require_once(__DIR__ . '/inspectionsResearches.php'); $inspectResearch = new med_examinations_inspectionsResearches(); $inspectResearch->updateAnatomic((object)[ 'inspectId' => $inspectId, 'items' => array_map(fn($id) => (object)['id' => $id], $anatomicIds) ]); unset($inspectNotExecute); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $param = new stdClass(); $param->inspectionsId = $inspectionsId; $inspectNotExecute = true; require_once(__DIR__ . '/inspectionsResearches.php'); $inspectResearch = new med_examinations_inspectionsResearches(); $ret = $inspectResearch->readInspectionsResearches($param); unset($inspectNotExecute); return $ret; } public function syncMedVisit($visitsArray) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { foreach ($visitsArray as $row) { $medInspectCode = $row['mic'] ? $row['mic'] : 'null'; $medProfessionCode = $row['medprofessioncode'] ? $row['medprofessioncode'] : 'null'; $medDoctorCode = $row['meddoctorcode'] ? $row['meddoctorcode'] : 'null'; $visitDate = $row['visitdate'] ? date('Ymd', safe_strtotime($row['visitdate'])) : 'null'; $medDiagnosisCode = $row['meddiagnosiscode'] ? $row['meddiagnosiscode'] : 'null'; $vFirst = $row['vfirst'] ? $row['vfirst'] : 0; $clinicalDiad = isset($row['clinicaldiagnosis']) ? $db->quote($row['clinicaldiagnosis']) : 'NULL'; $kindDiag = $row['ciszresourcevaluecodediagkind'] ?? 'null'; $cancerStage = $row['ciszresourcevaluecodecancerstage'] ?? 'null'; $visitId = $row['medvisitpatientcode'] ?? 'null'; $anatomicIds = []; if (!empty($row['anatomicids'])) { $trimmed = trim($row['anatomicids'], '{}'); if ($trimmed !== '') { $anatomicIds = array_map('intval', explode(',', $trimmed)); } } $sql = <<<EOT
SELECT MedInspectResultCode "id" FROM MedInspectResult
  WHERE MedInspectCode = $medInspectCode
  AND MedProfessionCode = $medProfessionCode
  AND COALESCE(InspectDate,19000101) = '$visitDate' LIMIT 1 ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = fetchValue($stmt, 'id'); $sqlVisit = <<<EOT
SELECT MedVisitDocumentCode "id" FROM MedVisitDocument
  WHERE MedVisitPatientCode = $visitId LIMIT 1 ;
EOT;
$stmtVisit = $db->prepare($sqlVisit); $stmtVisit->execute(); $visitDocumentId = fetchValue($stmtVisit, 'id'); if (!$val) { $sql = <<<EOT
SELECT MedInspectResultCode "id" FROM MedInspectResult
   WHERE MedInspectCode = $medInspectCode
   AND MedProfessionCode = $medProfessionCode
   AND InspectDate IS NULL
ORDER BY COALESCE(PlanDate,30000101)  LIMIT 1 ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $val = fetchValue($stmt, 'id'); } else { continue; } if ($val) { $sql = <<<EOT
UPDATE MedInspectResult SET
   Manual = 0,
   MedDoctorCode = $medDoctorCode,
   InspectDate = $visitDate,
   MedDiagnosisCode = $medDiagnosisCode,
   FirstTime = $vFirst,
   ClinicalDiagnosis = $clinicalDiad,
   CiszResourceValueCodeDiagKind = $kindDiag,
   CiszResourceValueCodeCancerStage = $cancerStage
   WHERE MedInspectResultCode = $val
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (!empty($anatomicIds)) { $inspectionsExaminations = new med_examinations_inspectionsExaminations(); $inspectionsExaminations->updateAnatomic((object)[ 'inspectId' => $val, 'items' => array_map(fn($id) => (object)['id' => $id], $anatomicIds) ]); } } else { $sql = <<<EOT
INSERT INTO MedInspectResult (medinspectcode ,Manual , MedProfessionCode ,MedDoctorCode ,InspectDate ,MedDiagnosisCode , FirstTime,
                              ClinicalDiagnosis, CiszResourceValueCodeDiagKind, CiszResourceValueCodeCancerStage)
    VALUES ($medInspectCode,0,$medProfessionCode,$medDoctorCode,'$visitDate',$medDiagnosisCode,$vFirst,
            $clinicalDiad, $kindDiag, $cancerStage);
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $inspectId = $db->lastInsertId(); $val = $inspectId; if (!empty($anatomicIds)) { $inspectionsExaminations = new med_examinations_inspectionsExaminations(); $inspectionsExaminations->updateAnatomic((object)[ 'inspectId' => $inspectId, 'items' => array_map(fn($id) => (object)['id' => $id], $anatomicIds) ]); } } if ($visitDocumentId && $val) { $sql = <<<EOT
UPDATE MedVisitDocument SET
   MedInspectResultCode = $val
   WHERE MedVisitDocumentCode = $visitDocumentId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function calcVisits($params = null) { $inspectionsId = isEmptyProperty($params, 'inspectionsId'); $patientId = isEmptyProperty($params, 'patientId', 0); $preventId = isEmptyProperty($params, 'preventId', 0); $planDate = isEmptyProperty($params, 'planDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'planDate'))) : null; $inspectDate = isEmptyProperty($params, 'inspectDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'inspectDate'))) : null; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $finishDate = $inspectDate ? $inspectDate : ($planDate ? $planDate : date("Ymd")); $startDate = new DateTime($finishDate); $startDate->modify('first day of this month'); $startDate->modify('-1 year'); $startDate = $startDate->format("Ymd"); $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
select
    $inspectionsId as MIC,
    0 as Manualy,
    mv.MedProfessionCode,
    mv.MedDoctorCode,
    mv.VisitDate,
    mvp.MedDiagnosisCode,
    CASE COALESCE(mvp.VisitFirst,0) = 1 WHEN true then 1 WHEN false THEN 0 END as VFirst,
    MVPE.ClinicalDiagnosis,
    MVPE.CiszResourceValueCodeDiagKind,
    MVPE.CiszResourceValueCodeCancerStage,
    COALESCE(array_agg(MVPA.CiszResourceValueCodeAnatomic) FILTER (WHERE MVPA.CiszResourceValueCodeAnatomic IS NOT NULL), '{}') AS AnatomicIds,
    MVP.MedVisitPatientCode
    from MedVisitPatient AS mvp left outer join MedVisit AS mv on mv.MedVisitCode = mvp.MedVisitCode
    LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
    LEFT JOIN MedVisitPatientAnatomic AS MVPA ON MVPA.MedVisitPatientCode = mvp.MedVisitPatientCode
      where
        mvp.MedPatientCode = $patientId
        and mvp.MedDiagnosisCode is not null
        and mvp.VisitCase = 1
        and COALESCE(mvp.VisitEnd, 0) <> (0
        )and mv.VisitDate <= ('$finishDate'
        )and mv.VisitDate >= ('$startDate'
        )and not exists(select mvp2.MedVisitPatientCode
     from MedVisitPatient AS mvp2 left outer join MedVisit AS mv2 on mv2.MedVisitCode = mvp2.MedVisitCode
      where mvp2.MedPatientCode = mvp.MedPatientCode
        and mvp2.MedDiagnosisCode = mvp.MedDiagnosisCode
        and (mv2.VisitDate > mv.VisitDate or (mv2.VisitDate = mv.VisitDate
        and mvp2.MedVisitPatientCode > mvp.MedVisitPatientCode))
        and mv2.VisitDate >= ('$startDate'
        )and mv2.VisitDate <= ('$finishDate'))
GROUP BY mvp.MedVisitPatientCode, mv.MedProfessionCode, mv.MedDoctorCode, mv.VisitDate, mvp.MedDiagnosisCode, 
         MVPE.ClinicalDiagnosis, MVPE.CiszResourceValueCodeDiagKind, MVPE.CiszResourceValueCodeCancerStage
Order by mv.VisitDate ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $visitsArray = $stmt->fetchAll(); $this->syncMedVisit($visitsArray); $sql = <<<EOT
select
    $inspectionsId as MIC,
    0 as Manualy,
    mv.MedProfessionCode,
    mv.MedDoctorCode,
    mv.VisitDate,
    mvp.MedDiagnosisCode,
    CASE NOT EXISTS (select
         mir.MedDiagnosisCode
        from MedInspect AS mi left outer join MedInspectResult AS mir on mir.MedInspectCode = mi.MedInspectCode
        where mi.MedPatientCode = mvp.MedPatientCode
         and mir.MedDiagnosisCode = mvp.MedDiagnosisCode) WHEN true then 1 WHEN false THEN 0 END as VFirst,
    MVPE.ClinicalDiagnosis,
    MVPE.CiszResourceValueCodeDiagKind,
    MVPE.CiszResourceValueCodeCancerStage,
    COALESCE(array_agg(MVPA.CiszResourceValueCodeAnatomic) FILTER (WHERE MVPA.CiszResourceValueCodeAnatomic IS NOT NULL), '{}') AS AnatomicIds,
    MVP.MedVisitPatientCode
   from
    MedVisitPatient AS mvp left outer join MedVisit AS mv on mv.MedVisitCode = mvp.MedVisitCode
   LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
   LEFT JOIN MedVisitPatientAnatomic AS MVPA ON MVPA.MedVisitPatientCode = mvp.MedVisitPatientCode
   where
    mvp.MedPatientCode = $patientId
    and mvp.MedDiagnosisCode is not null
    and (mvp.VisitCase <> (1 )or COALESCE(mvp.VisitEnd, 0) = 0)
    and mvp.VisitMark = 3
    and mv.VisitDate <= ('$finishDate'
    )and mv.VisitDate >= ('$startDate'
    )and not exists(select mvp2.MedVisitPatientCode
      from MedVisitPatient AS mvp2 left outer join MedVisit AS mv2 on mv2.MedVisitCode = mvp2.MedVisitCode
      where mvp2.MedPatientCode = mvp.MedPatientCode
        and mvp2.MedDiagnosisCode = mvp.MedDiagnosisCode
        and (mv2.VisitDate > mv.VisitDate or (mv2.VisitDate = mv.VisitDate
        and mvp2.MedVisitPatientCode > mvp.MedVisitPatientCode))
        and mv2.VisitDate >= ('$startDate'
        )and mv2.VisitDate <= ('$finishDate'))
GROUP BY mvp.MedVisitPatientCode, mv.MedProfessionCode, mv.MedDoctorCode, mv.VisitDate, mvp.MedDiagnosisCode, 
         MVPE.ClinicalDiagnosis, MVPE.CiszResourceValueCodeDiagKind, MVPE.CiszResourceValueCodeCancerStage
Order by mv.VisitDate ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $visitsArray = $stmt->fetchAll(); $this->syncMedVisit($visitsArray); $db->beginTransaction(); try { $sql = <<<EOT
INSERT INTO MedInspectResult (medinspectcode ,  manual ,  medprofessioncode ,  meddoctorcode , inspectdate , meddiagnosiscode , firsttime )
   select distinct
    $inspectionsId as MIC,
   1 as Manualy,
   md.MedProfessionCode,
   md.MedDoctorCode,
   cast('$finishDate' as date) as DateRes,
   md.MedDiagnosisCode,
    CASE NOT EXISTS (select MedDispensaryCode
       from MedDispensary
       where MedPatientCode = md.MedPatientCode
        and MedDispensaryCode = md.MedDispensaryCode
        and MotiveCapture = 1
        and years(StartDate) = years('$finishDate')) WHEN true then 0 WHEN false THEN 1 END as VFirst
  from
   MedDispensary AS md where
   md.MedPatientCode = $patientId
   and md.MedDiagnosisCode is not null
   and COALESCE(md.FinishDate, 30000101) = 30000101
   and md.StartDate <= ('$finishDate'
   )and not exists(select MedInspectResultCode
        from MedInspectResult
        where MedInspectCode = ($inspectionsId ) and MedDiagnosisCode = md.MedDiagnosisCode)
         and not exists(select md2.MedPatientCode
        from MedDispensary AS md2 where md2.MedPatientCode = md.MedPatientCode
         and md2.MedDiagnosisCode = md.MedDiagnosisCode
         and (md2.StartDate > md.StartDate or (md2.StartDate = md.StartDate
             and md2.MedDispensaryCode > md.MedDispensaryCode))
         and md2.StartDate >= ('$startDate'
         )and md2.StartDate <= ('$finishDate'));
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $sql = <<<EOT
select
    $inspectionsId as MIC,
    0 as Manualy,
    mv.MedProfessionCode,
    mv.MedDoctorCode,
    mv.VisitDate,
    mvp.MedDiagnosisCode,
    0 as VFirst,
    MVPE.ClinicalDiagnosis,
    MVPE.CiszResourceValueCodeDiagKind,
    MVPE.CiszResourceValueCodeCancerStage,
    COALESCE(array_agg(MVPA.CiszResourceValueCodeAnatomic) FILTER (WHERE MVPA.CiszResourceValueCodeAnatomic IS NOT NULL), '{}') AS AnatomicIds,
    MVP.MedVisitPatientCode
    from MedVisitPatient AS mvp left outer join MedVisit AS mv on mv.MedVisitCode = mvp.MedVisitCode
    LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
    LEFT JOIN MedVisitPatientAnatomic AS MVPA ON MVPA.MedVisitPatientCode = mvp.MedVisitPatientCode
    where
    mvp.MedPatientCode = $patientId
    and mvp.MedDiagnosisCode is not null
    and mvp.VisitMark in (3,4)
    and mv.VisitDate <= ('$finishDate'
    )and mv.VisitDate >= ('$startDate'
    )and not exists(select mvp2.MedVisitPatientCode
                  from MedVisitPatient AS mvp2 left outer join MedVisit AS mv2 on mv2.MedVisitCode = mvp2.MedVisitCode
                  where mvp2.MedPatientCode = mvp.MedPatientCode
                   and mv2.MedProfessionCode = mv.MedProfessionCode
                   and (mv2.VisitDate > mv.VisitDate or (mv2.VisitDate = mv.VisitDate
                   and mvp2.MedVisitPatientCode > mvp.MedVisitPatientCode))
                   and mv2.VisitDate >= ('$startDate'
                   )and mv2.VisitDate <= ('$finishDate'))
    GROUP BY mvp.MedVisitPatientCode, mv.MedProfessionCode, mv.MedDoctorCode, mv.VisitDate, mvp.MedDiagnosisCode, 
         MVPE.ClinicalDiagnosis, MVPE.CiszResourceValueCodeDiagKind, MVPE.CiszResourceValueCodeCancerStage
    Order by mv.VisitDate ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $visitsArray = $stmt->fetchAll(); $this->syncMedVisit($visitsArray); $sql = <<<EOT
SELECT DISTINCT
    $inspectionsId  as MIC,
    0 as Manualy,
    MD.MedProfessionCode,
    SV.MedDoctorCode,
    SV.VisitDate,
    SV.MedDiagnosisCode,
    0 as VFirst
    FROM StomVisit AS SV JOIN MedDoctor AS MD ON MD.MedDoctorCode = SV.MedDoctorCode
    JOIN StomCard AS SC ON SC.StomCardCode = SV.StomCardCode
   WHERE
    MD.MedProfessionCode IN (SELECT MedProfessionCode FROM MedPreventDirection
                             WHERE MedPreventCode = $preventId AND COALESCE(DocumentKind,0) = 1) AND
    SC.MedPatientCode =  $patientId AND
    SV.VisitDate <= ('$finishDate')AND
    SV.VisitDate >= ('$startDate')AND
    EXISTS (SELECT 1 FROM StomVisitService AS SVS JOIN Service AS S ON S.ServiceCode = SVS.ServiceCode AND COALESCE(ProphInspectionDone,0) = 1
            WHERE SVS.StomVisitCode = SV.StomVisitCode) AND
    NOT EXISTS (SELECT 1
                 FROM StomVisit AS SV2 JOIN MedDoctor AS MD2 ON MD2.MedDoctorCode = SV2.MedDoctorCode
                 JOIN StomCard AS SC2 ON SC2.StomCardCode = SV2.StomCardCode
                 WHERE SC2.MedPatientCode = SC.MedPatientCode AND
                   md2.MedProfessionCode = md.MedProfessionCode AND
                  (sv2.VisitDate > sv.VisitDate OR (sv2.VisitDate = sv.VisitDate
                           AND sv2.StomVisitCode > sv.StomVisitCOde)) AND
                  sv2.VisitDate >= ('$startDate')AND
                  sv2.VisitDate <= ('$finishDate')AND
                  EXISTS (SELECT 1 FROM StomVisitService AS SVS2 JOIN Service AS S2 ON S2.ServiceCode = SVS2.ServiceCode AND COALESCE(S2.ProphInspectionDone,0) = 1
                          WHERE SVS2.StomVisitCode = SV2.StomVisitCode))
    Order by SV.VisitDate ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $visitsArray = $stmt->fetchAll(); $this->syncMedVisit($visitsArray); $param = new stdClass(); $param->inspectionsId = $inspectionsId; $inspectExamNotExecute = true; require_once(__DIR__ . '/inspectionsExaminations.php'); $inspectResearch = new med_examinations_inspectionsExaminations(); $ret = $inspectResearch->readInspectionsExaminations($param); unset($inspectExamNotExecute); return $ret; } public function getQuestionaryResearches($params) { $db = $this->db ?: $this->db = connectDb(); $questionDictNumber = self::QUESTION_DICT_NUMBER; $answerDictNumber = self::ANSWER_DICT_NUMBER; $inspectId = isEmptyProperty($params, 'inspectId'); $patientQuestionaryId = isEmptyProperty($params, 'patientQuestionaryId'); $sqlParams = [ ':patientQuestionaryId' => $patientQuestionaryId ]; $sqlInspectId = ''; if ($inspectId) { $sqlInspectId = <<<"EOT"
    AND NOT EXISTS(
        SELECT
            1
        FROM MedInspectResearch MIR
        WHERE MIR.medInspectCode = :inspectId
            AND MIR.uniDictCodeResearch = U.uniDictCode
    )
EOT;
$sqlParams[':inspectId'] = $inspectId; } $sql = <<<"EOT"
WITH questions AS (
    SELECT
        uniDictCode id,
        code,
        valueString1 "name"
    FROM uniDict
    WHERE dictNumber = $questionDictNumber
        AND valueInteger = 1
    ORDER BY position
),
answers AS (
    SELECT
        uniDictCode id,
        code,
        valueString1 "name"
    FROM uniDict
    WHERE dictNumber = $answerDictNumber
    ORDER BY position
),
questionary AS (
    SELECT
        medQuestionaryCode,
        uniDictCode_question,
        Q.code "question_code",
        uniDictCode_answer,
        A.code "answer_code",
        medResearchCode,
        unidictcode_riskfactor
    FROM medQuestionary MQ
    JOIN questions Q ON Q.id = MQ.uniDictCode_question
    JOIN answers A ON A.id = MQ.uniDictCode_answer
)
SELECT DISTINCT
    MR.medResearchCode "researchId",
    U.uniDictCode "uniDictIdResearch",
    U.value "name",
    MPQ.documentDate "planDate"
FROM MedPatientQuestionary MPQ
JOIN MedPatientQuestionaryItem MPQI ON MPQI.MedPatientQuestionaryCode = MPQ.MedPatientQuestionaryCode
JOIN questionary Q ON Q.question_code = MPQI.itemNumber AND Q.answer_code = MPQI.itemAnswer
JOIN MedResearch MR ON MR.medResearchCode = Q.medResearchCode
JOIN UniDict U ON U.uniDictCode = MR.uniDictCode
WHERE MPQ.medPatientQuestionaryCode = :patientQuestionaryId
$sqlInspectId
EOT;
return selectSql($db, $sql, $sqlParams); } public function getQuestionaryRiskFactors($params) { $db = $this->db ?: $this->db = connectDb(); $questionDictNumber = self::QUESTION_DICT_NUMBER; $answerDictNumber = self::ANSWER_DICT_NUMBER; $inspectId = isEmptyProperty($params, 'inspectId'); $patientQuestionaryId = isEmptyProperty($params, 'patientQuestionaryId'); $sqlParams = [ ':patientQuestionaryId' => $patientQuestionaryId ]; $sqlInspectId = ''; if ($inspectId) { $sqlInspectId = <<<"EOT"
    AND NOT EXISTS(
        SELECT
            1
        FROM MedInspectRiscFactor MIRF
        WHERE MIRF.medInspectCode = :inspectId
            AND MIRF.uniDictCode_riskFactor = U.uniDictCode
    )
EOT;
$sqlParams[':inspectId'] = $inspectId; } $sql = <<<"EOT"
WITH questions AS (
    SELECT
        uniDictCode id,
        code,
        valueString1 "name"
    FROM uniDict
    WHERE dictNumber = $questionDictNumber
        AND valueInteger = 1
    ORDER BY position
),
answers AS (
    SELECT
        uniDictCode id,
        code,
        valueString1 "name"
    FROM uniDict
    WHERE dictNumber = $answerDictNumber
    ORDER BY position
),
questionary AS (
    SELECT
        medQuestionaryCode,
        uniDictCode_question,
        Q.code "question_code",
        uniDictCode_answer,
        A.code "answer_code",
        medResearchCode,
        uniDictCode_riskFactor
    FROM medQuestionary MQ
    JOIN questions Q ON Q.id = MQ.uniDictCode_question
    JOIN answers A ON A.id = MQ.uniDictCode_answer
)
SELECT DISTINCT
    Q.uniDictCode_riskFactor "riskFactorId",
    U.value "riskFactorName"
FROM MedPatientQuestionary MPQ
JOIN MedPatientQuestionaryItem MPQI ON MPQI.MedPatientQuestionaryCode = MPQ.MedPatientQuestionaryCode
JOIN questionary Q ON Q.question_code = MPQI.itemNumber AND Q.answer_code = MPQI.itemAnswer
JOIN UniDict U ON U.uniDictCode = Q.uniDictCode_riskFactor
WHERE MPQ.medPatientQuestionaryCode = :patientQuestionaryId
$sqlInspectId
EOT;
return selectSql($db, $sql, $sqlParams); } public function deleteInspections($records) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { if (is_object($records)) { $records = [$records]; } $sql = <<<EOT
UPDATE MedInspect SET ActualDate = NULL
WHERE ActualDate = ?
  AND MedPatientCode = ?
  AND MedPreventCode = ?
EOT;
$stmt = $db->prepare($sql); foreach ($records as $row) { if (isEmptyProperty($row, 'planDate') && isEmptyProperty($row, 'patientId') && isEmptyProperty($row, 'preventGroup')) { $stmt->execute([ isEmptyProperty($row, 'planDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'preventGroup') ]); } } $res = deleteSql($db, "MedInspect", "MedInspectCode", $records, 'id', false); $db->commit(); return $res; } catch (Throwable $ex) { $db->rollback(); throw $ex; } } public function createInspections($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<SQL
            INSERT INTO MedInspect (
                EnterpriseCode, PlanDate, MedPatientCode,
                MedPreventCode, InspectDate, MedDoctorCode,
                ActualDate, BuhMolCodeOther, MedDoctorCodeOther,
                ChangeTime, DocumentNumber, DocumentDate,
                CertificateDate, CertificateNumber, ConclusionDate,
                ConclusionNumber, CheckNumber, FlagsDl, Information, Conclusion,
                CiszResourceValueCodeRejectCause
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                LOCALTIMESTAMP, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?
            )
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ $enterpriseCode, isEmptyProperty($row, 'planDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'preventGroup'), isEmptyProperty($row, 'inspectDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'actualDate'), isEmptyProperty($row, 'buhMolOtherId'), isEmptyProperty($row, 'medDoctorOtherId'), isEmptyProperty($row, 'documentNumber'), isEmptyProperty($row, 'documentDate'), isEmptyProperty($row, 'certificateDate'), isEmptyProperty($row, 'certificateNumber'), isEmptyProperty($row, 'conclusionDate'), isEmptyProperty($row, 'conclusionNumber'), isEmptyProperty($row, 'checkNumber'), isUndefinedProperty($row, 'flagsDl'), isUndefinedProperty($row, 'information'), isUndefinedProperty($row, 'finalNote'), isUndefinedProperty($row, 'rejectionReasonId') ]); if (isEmptyProperty($row, 'anthropometricData')) { $this->saveAnthropometricData($row); } $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInspect'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function checkPlanInspectNeeded($id) { $result = false; $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
SELECT i.InspectDate                        "inspectDate",
       COALESCE(i.ActualDate, 30000101)     "actualDate",
       i.MedDoctorCode                      "doctorId",
       i.MedPatientCode                     "patientId",
       i.MedPreventCode                     "preventId",
       p.PeriodMonth                        "periodMonth",
       p.MedPreventTypeCode                 "preventTypeId",
       pt.InputForm                         "inputForm",
       COALESCE(pass.DateOfBirth, 19000101) "dateOfBirth"
FROM MedInspect i
JOIN MedPatient mp ON mp.MedPatientCode = i.MedPatientCode
LEFT JOIN ManPassport pass ON pass.ManCode = mp.ManCode
LEFT JOIN MedPrevent p ON p.MedPreventCode = i.MedPreventCode
LEFT JOIN MedPreventType pt ON pt.MedPreventTypeCode = p.MedPreventTypeCode
WHERE i.MedInspectCode = $id
  AND pt.InputForm IN (2,3,9,10)
EOT;
$inspect = selectSql($db, $sql); if (empty($inspect)) { return $result; } $date3000 = new DateTime("3000-01-01"); $actualDate = new DateTime($inspect[0]['actualDate'] ?? ''); $inspectDate = new DateTime($inspect[0]['inspectDate'] ?? ''); $inspectDate2 = new DateTime($inspect[0]['inspectDate'] ?? ''); $doctorId = $inspect[0]['doctorId']; $patientId = $inspect[0]['patientId']; $preventId = $inspect[0]['preventId']; $inputForm = $inspect[0]['inputForm']; $periodMonth = $inspect[0]['periodMonth']; $preventTypeId = $inspect[0]['preventTypeId']; $dateOfBirth = new DateTime($inspect[0]['dateOfBirth'] ?? ''); $interval = new DateInterval('P' . $periodMonth . 'M'); $inspectDate2->add($interval); $planDate = min([$actualDate, $inspectDate2]); if (($planDate == $inspectDate) || ($planDate == $date3000)) { $interval = new DateInterval('P1Y'); $planDate = $inspectDate; $planDate->add($interval); } if (($inputForm == 3) OR ($inputForm == 10)) { if ($actualDate && $actualDate != new DateTime("3000-01-01")) { $planDate = $actualDate; } else { $tmpDate = new DateTime($planDate->format("Y") . $dateOfBirth->format("md")); $tmpDate->modify("-1 day"); $planDate = $tmpDate; } } if (!$planDate || !$preventId || !$patientId || !$doctorId) { return $result; } $sql = <<<EOT
SELECT fn_medFindPrevent($patientId, (
         (select string_agg((mp.MedPreventCode)::text, ',' )
          from MedPrevent mp
          where mp.MedPreventTypeCode = $preventTypeId)
         ), ?, ?) "preventId"
EOT;
$prevent = selectSql($db, $sql, [$dateOfBirth->format("Ymd"), $planDate->format("Ymd")]); if ($prevent && $prevent[0]['preventId']) { $preventId = $prevent[0]['preventId']; } $sql = <<<EOT
SELECT 1
FROM MedInspect
WHERE EnterpriseCode = $enterpriseId
  AND PlanDate = ?
  AND MedPatientCode = $patientId
  AND MedPreventCode = $preventId
EOT;
$inspectExists = selectSql($db, $sql, [$planDate->format("Ymd")]); if (!$inspectExists) { $result = $planDate->format("d.m.Y"); } return $result; } public function createDirectionByPeriodMonth($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $oldId = isEmptyProperty($params, 'oldId'); $planDate = isEmptyProperty($params, 'planDate') ? new DateTime($params->planDate) : null; $flTransferOldDirection = isEmptyProperty($params, 'flTransferOldDirection') ? true : false; $db->beginTransaction(); try { $sql = <<<EOT
SELECT i.MedPreventCode                     "preventId",
       i.MedPatientCode                     "patientId",
       i.ActualDate                         "actualDate",
       p.MedPreventTypeCode                 "preventTypeId",
       COALESCE(pass.DateOfBirth, 19000101) "dateOfBirth"
FROM MedInspect i
JOIN MedPatient mp ON mp.MedPatientCode = i.MedPatientCode
LEFT JOIN ManPassport pass ON pass.ManCode = mp.ManCode
LEFT JOIN MedPrevent p ON p.MedPreventCode = i.MedPreventCode
WHERE i.MedInspectCode = $oldId
EOT;
$oldInspect = selectSql($db, $sql); if (empty($oldInspect)) { return false; } $preventId = $oldInspect[0]['preventId']; $patientId = $oldInspect[0]['patientId']; $preventTypeId = $oldInspect[0]['preventTypeId']; $actualDate = $oldInspect[0]['actualDate']; $dateOfBirth = new DateTime($oldInspect[0]['dateOfBirth'] ?? ''); $sql = <<<EOT
SELECT fn_medFindPrevent($patientId, (
         (select string_agg((mp.MedPreventCode)::text, ',' )
          from MedPrevent mp
          where mp.MedPreventTypeCode = $preventTypeId)
         ), ?, ?) "preventId"
EOT;
$prevent = selectSql($db, $sql, [$dateOfBirth->format("Ymd"), $planDate->format("Ymd")]); if ($prevent && $prevent[0]['preventId']) { $preventId = $prevent[0]['preventId']; } $sql = <<<EOT
INSERT INTO MedInspect (EnterpriseCode, PlanDate, MedPatientCode, MedPreventCode)
VALUES ($enterpriseId, ?, $patientId, $preventId)
EOT;
executeSql($db, $sql, [$planDate->format("Ymd")]); $id = $db->lastInsertId('seq_MedInspect'); if (!$id) { return false; } if ($flTransferOldDirection) { $sql = <<<EOT
SELECT MI.MedInspectCode "id",
       MI.InspectDate    "inspectDate"
FROM MedInspect MI
JOIN MedPrevent MP ON MP.MedPreventCode = MI.MedPreventCode AND MP.MedPreventTypeCode = $preventTypeId
WHERE MI.EnterpriseCode = $enterpriseId
  AND MI.MedPatientCode = $patientId
  AND MI.InspectDate IS NOT NULL
  AND MI.PlanDate < ?
ORDER BY MI.InspectDate DESC
LIMIT 1
EOT;
$oldInspect = selectSql($db, $sql, [$planDate->format("Ymd")]); if ($oldInspect) { $oldInspectId = $oldInspect[0]['id']; $oldInspectDate = new DateTime($oldInspect[0]['inspectDate'] ?? ''); $sql = <<<EOT
INSERT INTO MedInspectResult (MedInspectCode, MedProfessionCode, PlanDate)
SELECT $id, MedProfessionCode, PlanDate
FROM MedInspectResult
WHERE MedInspectCode = $oldInspectId
  AND MedProfessionCode IS NOT NULL
  AND InspectDate IS NULL
  AND COALESCE(PlanDate, 19000101) > ?
EOT;
executeSql($db, $sql, [$oldInspectDate->format("Ymd")]); $sql = <<<EOT
INSERT INTO MedInspectConclusion (MedInspectCode, MedConclusionGroupCode, MedConclusionCode, Note)
SELECT $id, MedConclusionGroupCode, MedConclusionCode, Note
FROM MedInspectConclusion
WHERE MedInspectCode = $oldInspectId
EOT;
executeSql($db, $sql); } } $sql = <<<EOT
SELECT (
  SELECT MIR.InspectDate
  FROM MedInspectResult MIR
  JOIN MedInspect MI ON MI.MedInspectCode = MIR.MedInspectCode AND MI.MedPatientCode = $patientId
  JOIN MedPrevent P ON P.MedPreventCode = MI.MedPreventCode
  WHERE MIR.InspectDate IS NOT NULL
  ORDER BY MIR.InspectDate DESC
  LIMIT 1)             "lastDirectionInspectDate",
  pd.PeriodMonth       "directionPeriodMonth",
  pd.MedProfessionCode "professionId",
  pd.FlagCondition     "flagCondition"
FROM MedInspect i
JOIN MedPreventDirection pd ON pd.MedPreventCode = i.MedPreventCode
 AND pd.MedProfessionCode IS NOT NULL
WHERE i.MedInspectCode = $id
EOT;
$directionList = selectSql($db, $sql); if ($directionList) { foreach ($directionList as $direction) { $professionId = $direction['professionId']; $firstDateOfYear = new DateTime(); date_date_set($firstDateOfYear, date_format($planDate, "Y"), 1, 1); $nextDirectionInspectDate = new DateTime($direction['lastDirectionInspectDate'] ?? ''); $interval = new DateInterval('P' . isEmptyProperty($direction, 'directionPeriodMonth', 1) . 'M'); date_add($nextDirectionInspectDate, $interval); $d = max($firstDateOfYear, $nextDirectionInspectDate); if ($direction['flagCondition']) { $sql = <<<EOT
INSERT INTO MedInspectResult (MedInspectCode, MedProfessionCode)
VALUES ($id, $professionId)
EOT;
executeSql($db, $sql); } else { $maxCode = selectSqlValue($db, "SELECT MAX(MedInspectResultCode) FROM MedInspectResult"); while ($d <= $planDate) { if ($flTransferOldDirection && $oldInspectId) { $sql = <<<EOT
DELETE FROM MedInspectResult WHERE MedInspectResultCode = (
  SELECT MedInspectResultCode
  FROM MedInspectResult
  WHERE MedInspectResultCode <= $maxCode
    AND MedInspectCode = $id
    AND MedProfessionCode = $professionId
    AND PlanDate IS NOT NULL
  ORDER BY PlanDate ASC
  LIMIT 1)
EOT;
executeSql($db, $sql); } $sql = <<<EOT
INSERT INTO MedInspectResult (MedInspectCode, MedProfessionCode, PlanDate)
VALUES ($id, $professionId, ?)
EOT;
executeSql($db, $sql, [$d->format("Ymd")]); $interval = new DateInterval('P' . isEmptyProperty($direction, 'directionPeriodMonth', 12) . 'M'); date_add($d, $interval); } } } } if ($flTransferOldDirection && $oldInspectId) { $sql = <<<EOT
INSERT INTO MedInspectResearch (MedInspectCode, UniDictCodeResearch, PlanDate)
SELECT $id, UniDictCodeResearch, PlanDate
FROM MedInspectResearch
WHERE MedInspectCode = $oldInspectId
  AND PlanDate IS NOT NULL
  AND UniDictCodeResearch IS NOT NULL
  AND ResearchDate IS NULL
  AND COALESCE(PlanDate, 19000101) > ?
EOT;
executeSql($db, $sql, [$oldInspectDate->format("Ymd")]); } $sql = <<<EOT
SELECT
  (SELECT MIR.ResearchDate
   FROM MedInspectResearch MIR
   JOIN MedInspect MI ON MI.MedInspectCode = MIR.MedInspectCode AND MI.MedPatientCode = $patientId
   JOIN MedPrevent P on P.MedPreventCode = MI.MedPreventCode
   WHERE MIR.ResearchDate IS NOT NULL
   ORDER BY MIR.ResearchDate DESC
   LIMIT 1)              "lastDirectionResearchDate",
  pd.PeriodMonth         "directionPeriodMonth",
  pd.UniDictCodeResearch "uniDictCodeResearch",
  pd.FlagCondition       "flagCondition"
FROM MedInspect i
JOIN MedPreventDirection pd ON pd.MedPreventCode = i.MedPreventCode AND pd.UniDictCodeResearch IS NOT NULL
WHERE i.MedInspectCode = $id
EOT;
$researches = selectSql($db, $sql); if ($researches) { foreach ($researches as $research) { $firstDateOfYear = new DateTime(); date_date_set($firstDateOfYear, $planDate->format("Y"), 1, 1); $nextDirectionResearchDate = new DateTime($research['lastDirectionResearchDate'] ?? ''); $interval = new DateInterval('P' . isEmptyProperty($research, 'directionPeriodMonth', 1) . 'M'); date_add($nextDirectionResearchDate, $interval); $d = max([$firstDateOfYear, $nextDirectionResearchDate]); $uniDictCodeResearch = $research['uniDictCodeResearch']; if ($research['flagCondition']) { $sql = <<<EOT
INSERT INTO MedInspectResearch (MedInspectCode, UniDictCodeResearch)
VALUES ($id, $uniDictCodeResearch)
EOT;
executeSql($db, $sql); } else { $maxCode = selectSqlValue($db, "SELECT MAX(MedInspectResearchCode) FROM MedInspectResearch"); while ($d < $planDate) { if ($flTransferOldDirection && $oldInspectId) { $sql = <<<EOT
DELETE FROM MedInspectResearch WHERE MedInspectResearchCode = (
  SELECT MedInspectResearchCode
  FROM MedInspectResearch
  WHERE MedInspectResearchCode <= $maxCode
    AND MedInspectCode = $id
    AND UniDictCodeResearch = $uniDictCodeResearch
    AND PlanDate IS NOT NULL
  ORDER BY PlanDate ASC
  LIMIT 1)
EOT;
executeSql($db, $sql); } $sql = <<<EOT
INSERT INTO MedInspectResearch (MedInspectCode, UniDictCodeResearch, PlanDate)
VALUES ($id, $uniDictCodeResearch, ?)
EOT;
executeSql($db, $sql, [$d->format("Ymd")]); $interval = new DateInterval('P' . isEmptyProperty($research, 'directionPeriodMonth', 12) . 'M'); date_add($d, $interval); } } } } if (!$actualDate) { $sql = <<<EOT
UPDATE MedInspect SET
  ActualDate = ?
WHERE MedInspectCode = $oldId
EOT;
executeSql($db, $sql, [$planDate->format("Ymd")]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateInspections($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $ciszLinkSql = <<<EOT
            SELECT CL.CiszLinkCode
            FROM MedInspect MI
            LEFT JOIN MedVisitDocument MD ON MD.MedInspectCode = MI.MedInspectCode
            JOIN CiszLink Cl ON Cl.PrimaryKey = MI.MedInspectCode
                                    AND CL.Type = 2 AND CL.Status = 1 AND CL.TableName = 'MedInspect'
            WHERE MI.MedInspectCode = ?
EOT;
$stmtSelectLink = $db->prepare($ciszLinkSql); $sql = <<<EOT
            UPDATE medInspect SET
                planDate            = CASE WHEN ? = 1 THEN ? ELSE planDate END,
                medPatientCode      = CASE WHEN ? = 1 THEN ? ELSE medPatientCode END,
                medPreventCode      = CASE WHEN ? = 1 THEN ? ELSE medPreventCode END,
                inspectDate         = CASE WHEN ? = 1 THEN ? ELSE inspectDate END,
                medDoctorCode       = CASE WHEN ? = 1 THEN ? ELSE medDoctorCode END,
                actualDate          = CASE WHEN ? = 1 THEN ? ELSE actualDate END,
                buhMolCodeOther     = CASE WHEN ? = 1 THEN ? ELSE buhMolCodeOther END,
                medDoctorCodeOther  = CASE WHEN ? = 1 THEN ? ELSE medDoctorCodeOther END,
                documentNumber      = CASE WHEN ? = 1 THEN ? ELSE documentNumber END,
                documentDate        = CASE WHEN ? = 1 THEN ? ELSE documentDate END,
                certificateDate     = CASE WHEN ? = 1 THEN ? ELSE certificateDate END,
                certificateNumber   = CASE WHEN ? = 1 THEN ? ELSE certificateNumber END,
                conclusionDate      = CASE WHEN ? = 1 THEN ? ELSE conclusionDate END,
                conclusionNumber    = CASE WHEN ? = 1 THEN ? ELSE conclusionNumber END,
                checkNumber         = CASE WHEN ? = 1 THEN ? ELSE checkNumber END,
                flagsDl             = CASE WHEN ? = 1 THEN ? ELSE flagsDl END,
                information         = CASE WHEN ? = 1 THEN ? ELSE information END,
                conclusion          = CASE WHEN ? = 1 THEN ? ELSE conclusion END,
                CiszResourceValueCodeRejectCause = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeRejectCause END
            WHERE medInspectCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlLink = <<<EOT
            UPDATE CiszLink SET
            Status = 2
            WHERE CiszLinkCode = ?
EOT;
$stmtLink = $db->prepare($sqlLink); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'planDate'), isUndefinedProperty($row, 'planDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'preventGroup'), isUndefinedProperty($row, 'preventGroup'), propertyExistsSQL($row, 'inspectDate'), isUndefinedProperty($row, 'inspectDate'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'actualDate'), isUndefinedProperty($row, 'actualDate'), propertyExistsSQL($row, 'buhMolOtherId'), isUndefinedProperty($row, 'buhMolOtherId'), propertyExistsSQL($row, 'medDoctorOtherId'), isUndefinedProperty($row, 'medDoctorOtherId'), propertyExistsSQL($row, 'documentNumber'), isUndefinedProperty($row, 'documentNumber'), propertyExistsSQL($row, 'documentDate'), isUndefinedProperty($row, 'documentDate'), propertyExistsSQL($row, 'certificateDate'), isUndefinedProperty($row, 'certificateDate'), propertyExistsSQL($row, 'certificateNumber'), isUndefinedProperty($row, 'certificateNumber'), propertyExistsSQL($row, 'conclusionDate'), isUndefinedProperty($row, 'conclusionDate'), propertyExistsSQL($row, 'conclusionNumber'), isUndefinedProperty($row, 'conclusionNumber'), propertyExistsSQL($row, 'checkNumber'), isUndefinedProperty($row, 'checkNumber'), propertyExistsSQL($row, 'flagsDl'), isUndefinedProperty($row, 'flagsDl'), propertyExistsSQL($row, 'information'), isUndefinedProperty($row, 'information'), propertyExistsSQL($row, 'finalNote'), isUndefinedProperty($row, 'finalNote'), propertyExistsSQL($row, 'rejectionReasonId'), isUndefinedProperty($row, 'rejectionReasonId'), $row->id ]); if (isEmptyProperty($row, 'anthropometricData')) { $this->saveAnthropometricData($row); } $stmtSelectLink->execute([$row->id]); $linkIds = $stmtSelectLink->fetchAll(PDO::FETCH_COLUMN); if (!empty($linkIds) && (!isset($row->ciszStatus) || $row->ciszStatus <> 1)) { foreach ($linkIds as $linkId) { $stmtLink->execute([$linkId]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function saveAnthropometricData($row) { $db = $this->db ?: $this->db = connectDb(); $fields = ['height', 'weight', 'bodyMassIndex', 'waistCircumference']; $sql = <<<EOT
            INSERT INTO medInspectOther AS mio
                        (medInspectCode, type, valueNumber, valueString, valueDate, uniDictCode, CiszResourceValueCodeCentile)
            VALUES(?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (medInspectCode, type)
            DO UPDATE
            SET
                valueNumber = CASE WHEN ? = 1 THEN EXCLUDED.valueNumber ELSE mio.valueNumber END,
                valueString = CASE WHEN ? = 1 THEN EXCLUDED.valueString ELSE mio.valueString END,
                valueDate = CASE WHEN ? = 1 THEN EXCLUDED.valueDate ELSE mio.valueDate END,
                uniDictCode = CASE WHEN ? = 1 THEN EXCLUDED.uniDictCode ELSE mio.uniDictCode END,
                CiszResourceValueCodeCentile = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeCentile ELSE mio.CiszResourceValueCodeCentile END
EOT;
$stmt = $db->prepare($sql); for ($i = 1; $i <= 4; $i++) { $field = $fields[$i-1]; $stmt->execute([ isEmptyProperty($row, 'id'), $i, isEmptyProperty($row, $field), isEmptyProperty($row, $field), null, null, null, propertyExistsSQL($row, $field), propertyExistsSQL($row, $field), 0, 0, 0 ]); } $stmt->execute([ isEmptyProperty($row, 'id'), 5, null, isEmptyProperty($row, 'arterialPressure'), null, null, null, 0, propertyExistsSQL($row, 'arterialPressure'), 0, 0, 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 6, null, isEmptyProperty($row, 'intraOcularPressure'), null, null, null, 0, propertyExistsSQL($row, 'intraOcularPressure'), 0, 0, 0 ]); if (propertyExistsSQL($row, 'oralExamination') !== 0) { $date = new DateTimeImmutable(isEmptyProperty($row, 'oralExamination', '')); $date = $date->format('d.m.Y'); } else { $date = null; } $stmt->execute([ isEmptyProperty($row, 'id'), 7, null, $date, isEmptyProperty($row, 'oralExamination'), null, null, 0, propertyExistsSQL($row, 'oralExamination'), propertyExistsSQL($row, 'oralExamination'), 0, 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 8, null, isEmptyProperty($row, 'physicalDevName'), null, null, isEmptyProperty($row, 'physicalDevCode'), 0, propertyExistsSQL($row, 'physicalDevName'), 0, 0, propertyExistsSQL($row, 'physicalDevCode') ]); $stmt->execute([ isEmptyProperty($row, 'id'), 10, isEmptyProperty($row, 'score'), isEmptyProperty($row, 'score'), null, null, null, propertyExistsSQL($row, 'score'), propertyExistsSQL($row, 'score'), 0, 0, 0 ]); if (propertyExistsSQL($row, 'aOneDate') !== 0) { $oDate = new DateTimeImmutable(isEmptyProperty($row, 'aOneDate', '')); $oDate = $oDate->format('d.m.Y'); } else { $oDate = null; } $stmt->execute([ isEmptyProperty($row, 'id'), 13, null, $oDate, isEmptyProperty($row, 'aOneDate'), null, null, 0, propertyExistsSQL($row, 'aOneDate'), propertyExistsSQL($row, 'aOneDate'), 0, 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 11, isEmptyProperty($row, 'regularPhysicalActivity'), isEmptyProperty($row, 'regularPhysicalActivity'), null, isEmptyProperty($row, 'regularPhysicalActivity'), null, propertyExistsSQL($row, 'regularPhysicalActivity'), propertyExistsSQL($row, 'regularPhysicalActivity'), 0, propertyExistsSQL($row, 'regularPhysicalActivity'), 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 12, isEmptyProperty($row, 'tobaccoUse'), isEmptyProperty($row, 'tobaccoUse'), null, isEmptyProperty($row, 'tobaccoUse'), null, propertyExistsSQL($row, 'tobaccoUse'), propertyExistsSQL($row, 'tobaccoUse'), 0, propertyExistsSQL($row, 'tobaccoUse'), 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 14, isEmptyProperty($row, 'glucose'), null, null, null, null, propertyExistsSQL($row, 'glucose'), 0, 0, 0, 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 15, isEmptyProperty($row, 'headCircumference'), null, null, null, null, propertyExistsSQL($row, 'headCircumference'), 0, 0, 0, 0 ]); $stmt->execute([ isEmptyProperty($row, 'id'), 16, isEmptyProperty($row, 'chestCircumference'), null, null, null, null, propertyExistsSQL($row, 'chestCircumference'), 0, 0, 0, 0 ]); } public function findPrevent($params = null) { $db = $this->db ?: $this->db = connectDb(); $needCheckPreventByDate = isUndefinedProperty($params, 'needCheckPreventByDate'); $visitPatientId = isUndefinedProperty($params, 'visitPatientId'); $sql = <<<EOT
            SELECT
                MedPreventTypeCode "id",
                Name "name"
            FROM MedPreventType
            WHERE EnterpriseCode = :ec
                AND InputForm IN (2, 3, 4, 9, 10)
EOT;
$result = selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode') ]); $arr = []; if ($result && $needCheckPreventByDate && $visitPatientId) { $sqlVisitPatient = <<<SQL
SELECT
    MV.VisitDate "visitDate",
    MVP.MedPatientCode "patientId",
    P.DateOfBirth "dateOfBirth"
FROM MedVisitPatient AS MVP
JOIN MedVisit AS MV ON MVP.MedVisitCode = MV.MedVisitCode
JOIN MedPatient AS MP ON MVP.MedPatientCode = MP.MedPatientCode
LEFT JOIN MedDoctor AS MD ON MD.MedDoctorCode = MV.MedDoctorCode
LEFT JOIN ManPassport AS P ON P.ManCode = MP.ManCode
WHERE MVP.MedVisitPatientCode = :MVP
    /* хоть один участок доступен пользователю */
    AND (fn_WebUserAccessMedSector(MP.MedPatientGroupCode) = 1
        OR (
          (SELECT 1 FROM MedPatientAddGroup PGA
           WHERE PGA.MedPatientCode = MP.MedPatientCode
            and fn_WebUserAccessMedSector(PGA.MedPatientGroupCode) = 1
           LIMIT 1) = 1
        )
    )
LIMIT 1 ;
SQL;
$resultVisitPatient = selectSql($db, $sqlVisitPatient, [$visitPatientId]); if (!$resultVisitPatient) { throw new Exception('Посещение не найдено!'); } $resultVisitPatient = $resultVisitPatient[0]; $args = new stdClass(); $args->visitDateObj = safe_date_create($resultVisitPatient['visitDate']); $args->visitDate = $args->visitDateObj->format('Ymd'); $fd = $args->visitDateObj->format('Y1231'); $sqlPlan = <<<SQL
SELECT
    MI.PlanDate "planDate",
    coalesce(MI.InspectDate,  E'30000101' ) "inspectDate"
FROM MedInspect AS MI
JOIN MedPrevent AS MP ON MP.MedPreventCode = MI.MedPreventCode AND MP.MedPreventTypeCode = :MPTC
WHERE
    MI.MedPatientCode = :MPC
    AND MI.PlanDate BETWEEN dateadd('month', -MP.PeriodMonth, $args->visitDate) AND $fd
ORDER BY MI.PlanDate DESC
LIMIT 1
SQL;
$args->patientId = $resultVisitPatient['patientId']; $args->startDate = $resultVisitPatient['dateOfBirth']; $args->finishDate = $resultVisitPatient['visitDate']; $args->needErrorPrevent = false; foreach ($result as $res) { $findRes = selectSql($db, $sqlPlan, [$res['id'], $args->patientId]); if ($findRes) { $findRes = $findRes[0]; $args->basicDateObj = safe_date_create($findRes['inspectDate']); $args->basicDate = $args->basicDateObj->format('Ymd'); if ($args->basicDate !== '30000101') { $args->preventTypeId = $res['id']; $args->finishDate = $args->basicDate; $fRes = $this->getPreventByDate($args); if ($fRes) { $arr[] = $res; } } else { $args->preventTypeId = $res['id']; $fRes = $this->getPreventByDate($args); if ($fRes) { $arr[] = $res; } } } else { $args->preventTypeId = $res['id']; $fRes = $this->getPreventByDate($args); if ($fRes) { $arr[] = $res; } } } return $arr; } return $result; } public function getPreventByDate($params = null) { $db = $this->db ?: $this->db = connectDb(); $result = null; $patientId = isEmptyProperty($params, 'patientId'); $preventTypeId = isEmptyProperty($params, 'preventTypeId'); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $needErrorPrevent = isUndefinedProperty($params, 'needErrorPrevent', true); if (!$params || !$patientId || !$preventTypeId || !$startDate || !$finishDate) { throw new Exception('getPreventByDate: переданы неверные параметры!'); } $sql = <<<EOT
select
    fn_medFindPrevent(:mpc, ((select string_agg((medprevent_14.MedPreventCode)::text, ',' ) from MedPrevent AS medprevent_14 where medprevent_14.MedPreventTypeCode = :mptc) ) , :sd, :fd) "preventId",
    PeriodMonth "periodMonth"
from MedPrevent
where MedPreventCode = (fn_medFindPrevent(:mpc2, ((select string_agg((medprevent_14.MedPreventCode)::text, ',' ) from MedPrevent AS medprevent_14 where medprevent_14.MedPreventTypeCode = :mptc2) ) , :sd2, :fd2) )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$patientId, $preventTypeId, $startDate, $finishDate, $patientId, $preventTypeId, $startDate, $finishDate]); $res = $stmt->fetch(); if ($res) { $result = new stdClass(); $result->preventId = $res['preventId']; $result->periodMonth = $res['periodMonth']; } else if ($needErrorPrevent) { throw new Exception('Не удалось определить группу профилактики!'); } else { return false; } return $result; } public function findInspect($params = null) { $db = $this->db ?: $this->db = connectDb(); $visitPatientId = isEmptyProperty($params, 'visitPatientId'); $preventTypeId = isEmptyProperty($params, 'preventTypeId'); $fRes = null; $args = null; if (!$visitPatientId) { throw new Exception('getInspectionInfo: переданы неверные параметры!'); } $sql = <<<EOT
SELECT
    MV.VisitDate "visitDate",
    MV.MedDoctorCode "doctorId",
    FN_cmFIO( MD.ManCode ) "doctorName",
    MVP.MedPatientCode "patientId",
    FN_cmFIO( MP.ManCode ) "patientName",
    P.DateOfBirth "dateOfBirth",
    PG.Name "patientGroup",
    MP.NumberHistorySickness "numberHistorySickness",
    P.PersonalNumber "personalNumber",
    FN_cmCalcAddress(MP.manCode, 0)::text "address",
    FN_cmCalcAddress(MP.manCode, 1)::text "addressReside",
    CAST(asa_trim((SELECT string_agg((asa_trim(k.Name|| E': ' ||E2.Name || CASE asa_trim(B2.ClassGroup) <> ( E'' )WHEN true THEN  E' - ' || B2.ClassGroup END ||
        CASE asa_trim(u_300.Value) <> ( E'' )WHEN true THEN  E' (' || u_300.Value ||  E')' END ))::text, ',' )
        FROM MedPatientBusy AS B2 JOIN Enterprise AS E2 ON E2.EnterpriseCode = B2.EnterpriseCode
        LEFT OUTER JOIN UniDict AS u_300 ON u_300.UniDictCode = B2.UniDictCodeProfession AND u_300.DictNumber = 19
        LEFT OUTER JOIN EnterpriseKind AS k ON k.EnterpriseKindCode = B2.EnterpriseKindCode
        WHERE B2.MedPatientCode = MP.MedPatientCode AND TODAY() BETWEEN B2.StartDate AND B2.FinishDate))
    AS varchar(100)) "patientBusy"
FROM MedVisitPatient AS MVP
JOIN MedVisit AS MV ON MVP.MedVisitCode=MV.MedVisitCode
JOIN MedPatient AS MP ON MVP.MedPatientCode=MP.MedPatientCode
LEFT JOIN MedDoctor AS MD ON MD.MedDoctorCode = MV.MedDoctorCode
LEFT JOIN ManPassport AS P ON P.ManCode=MP.ManCode
LEFT JOIN MedPatientGroup AS PG on PG.MedPatientGroupCode = MP.MedPatientGroupCode
WHERE MedVisitPatientCode = :MVP
    /* хоть один участок доступен пользователю */
    and (fn_WebUserAccessMedSector(mp.MedPatientGroupCode) = 1
        or (
          (select 1 from MedPatientAddGroup pga
           where pga.MedPatientCode = mp.MedPatientCode
            and fn_WebUserAccessMedSector(pga.MedPatientGroupCode) = 1
           limit 1) = 1
        )
    )
LIMIT 1 ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$visitPatientId]); $res = $stmt->fetch(); if (!$res) { return null; } $result = new stdClass(); $result->preventTypeId = $preventTypeId; $result->visitDateObj = safe_date_create($res['visitDate']); $result->visitDate = $result->visitDateObj->format('Ymd'); $result->doctorId = $res['doctorId']; $result->doctorName = $res['doctorName']; $result->patientId = $res['patientId']; $result->patientName = $res['patientName']; $result->dateOfBirth = safe_date_create($res['dateOfBirth'])->format('Ymd'); $result->patientGroup = $res['patientGroup']; $result->numberHistorySickness = $res['numberHistorySickness']; $result->personalNumber = $res['personalNumber']; $result->address = $res['address']; $result->addressReside = $res['addressReside']; $result->patientBusy = $res['patientBusy']; $result->findResult = 0; $fd = $result->visitDateObj->format('Y1231'); $sql = <<<EOT
SELECT
    mi.PlanDate "planDate",
    COALESCE(mi.InspectDate,  E'30000101' ) "inspectDate",
    mi.MedInspectCode "inspectId",
    mp.MedPreventTypeCode "preventTypeId",
    mp.MedPreventCode "preventId",
    COALESCE(mi.ActualDate, DATEADD('MONTH', mp.PeriodMonth, mi.PlanDate)) "actualDate",
    DATEADD('MONTH', mp.PeriodMonth, mi.PlanDate) "nextPlanDate"
FROM MedInspect AS mi
JOIN MedPrevent AS mp on mp.MedPreventCode = mi.MedPreventCode and mp.MedPreventTypeCode = :MPTC
WHERE
    mi.MedPatientCode = :MPC
    and mi.PlanDate between dateadd('month', -mp.PeriodMonth, $result->visitDate) and $fd
ORDER BY mi.PlanDate desc
LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$preventTypeId, $result->patientId]); $findRes = $stmt->fetch(); $needErrorPrevent = isUndefinedProperty($params, 'needErrorPrevent'); if ($findRes) { $result->basicDateObj = safe_date_create($findRes['inspectDate']); $result->basicDate = $result->basicDateObj->format('Ymd'); if ($result->basicDate !== '30000101') { $result->lastInspectId = $findRes['inspectId']; $args = new stdClass(); $args->patientId = $result->patientId; $args->preventTypeId = $preventTypeId; $args->startDate = $result->dateOfBirth; $args->finishDate = $result->basicDate; $args->needErrorPrevent = $needErrorPrevent; $fRes = $this->getPreventByDate($args); if (!$fRes) { return $result; } $result->findPrevent = $fRes; $di = new DateInterval('P0M'); $di->m = (int) $fRes->periodMonth; $result->planDateObj = $result->basicDateObj->add($di); $result->planDate = $result->planDateObj->format('Ymd'); if ($result->planDateObj < $result->visitDateObj) { $result->planDateObj = $result->visitDateObj; $result->planDate = $result->visitDate; } $args->finishDate = $result->planDate; $fRes = $this->getPreventByDate($args); if ($fRes) { $result->preventId = $fRes->preventId; } $result->findPrevent = $fRes; $sql = <<<EOT
SELECT
    mi.MedInspectCode "inspectId"
FROM MedInspect AS mi
JOIN MedPrevent AS mp on mp.MedPreventCode = mi.MedPreventCode
WHERE
    mi.MedPatientCode = :0
    and mp.MedPreventTypeCode = :1
    and mp.MedPreventCode = :2
    and mi.PlanDate = $result->planDate
ORDER BY mi.PlanDate DESC
LIMIT 1;
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$result->patientId, $preventTypeId, $result->preventId]); $res = $stmt->fetch(); if ($res && $res['inspectId']) { $result->findResult = 1; $result->inspectId = $res['inspectId']; $param = new stdClass(); $param->inspectsId = $res['inspectId']; $param->preventType = $preventTypeId; $result->record = $this->readInspections($param)->items; } else { foreach ($args as $property => $value) { unset($args->$property); } $args->inspectsId = $result->lastInspectId; $args->preventType = $preventTypeId; $result->lastRecord = $this->readInspections($args)->items; } } else { $result->findPrevent = true; $result->findResult = 1; $result->inspectId = $findRes['inspectId']; $args = new stdClass(); $args->inspectsId = $findRes['inspectId']; $args->preventType = $preventTypeId; $result->record = $this->readInspections($args)->items; } } else { $args = new stdClass(); $args->patientId = $result->patientId; $args->preventTypeId = $preventTypeId; $args->startDate = $result->dateOfBirth; $args->finishDate = $result->visitDate; $args->needErrorPrevent = $needErrorPrevent; $fRes = $this->getPreventByDate($args); if ($fRes) { $result->preventId = $fRes->preventId; } $result->findPrevent = $fRes; } return ($result); } public function onGetNextNumber($params = null) { $db = $this->db ?: $this->db = connectDb(); $field = isEmptyProperty($params, 'field'); if (!$field) { return false; } $year = date('Y'); $fieldName = ''; switch ($field) { case 'documentNumber': case 'certificateNumber': case 'conclusionNumber': $fieldName = $field; break; } if (!$fieldName) { return false; } $sql = <<<SQL
SELECT $field
FROM MedInspect
WHERE COALESCE($field, '') <> '' AND EXTRACT(YEAR FROM PlanDate) = $year
ORDER BY ChangeTime DESC, MedInspectCode DESC
LIMIT 1
SQL;
$res = selectSqlValue($db, $sql); return ($res || $res === 0) ? $res : 0; } } handleJsonRpc('med_examinations_inspections');