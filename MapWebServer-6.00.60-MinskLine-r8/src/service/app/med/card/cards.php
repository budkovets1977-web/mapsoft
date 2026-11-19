<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/vaccinations/inoculationsLog.php'); require_once(__DIR__ . '/../../med/recipes/recipes.php'); require_once(__DIR__ . '/../../med/common/diagnoses.php'); require_once(__DIR__ . '/../../med/common/patientDispensaries.php'); require_once(__DIR__ . '/../../med/dayhospital/log.php'); require_once(__DIR__ . '/../../med/vaccinations/contrsLog.php'); require_once(__DIR__ . '/../../med/clinicstats/logOperations.php'); require_once(__DIR__ . '/../../med/reception/logSends.php'); require_once(__DIR__ . '/../../med/examinations/inspections.php'); require_once(__DIR__ . '/../../med/clinicstats/ambulance.php'); require_once(__DIR__ . '/../../med/lab/studies.php'); require_once(__DIR__ . '/../../map/common/periodLock.php'); require_once(__DIR__ . '/../../map/common/storage.php'); require_once(__DIR__ . '/../../med/reception/procedureSendLog.php'); require_once(__DIR__ . '/../../med/dayhospital/drugsPurpose.php'); require_once(__DIR__ . '/../../med/recipes/medicalDevices.php'); resumeJsonRpc(); class med_card_cards { const PAGE_SIZE = 30; const NEED_LOG = false; const SRCH_FLD_PROCESSED = 'srch_Processed'; const SRCH_FLD_SORT = 'srch_Sort'; const SRCH_FLD_FOUND = 'srch_Found'; const DENY_DELETE_VISIT_NOT_OWN_RULE = 'med-card-cards_deny_delete_visit_not_own'; const DENY_DELETE_VISIT_NOT_OWN_TEXT = 'У вас нет права на удаление посещений других пользователей'; const DENY_DELETE_VISIT_NOT_OWN_CODE = - 403; const DENY_UPDATE_VISIT_NOT_OWN_RULE = 'med-card-cards_deny_update_visit_not_own'; const DENY_UPDATE_VISIT_NOT_OWN_TEXT = 'У вас нет права на редактирование посещений других пользователей'; const DENY_UPDATE_VISIT_NOT_OWN_CODE = - 403; const DENY_DELETE_HTML_NOT_OWN_RULE = 'med-card-cards_deny_delete_html_not_own'; const DENY_DELETE_HTML_NOT_OWN_TEXT = 'У вас нет права на удаление дневников других пользователей'; const DENY_DELETE_HTML_NOT_OWN_CODE = - 403; const DENY_UPDATE_HTML_NOT_OWN_RULE = 'med-card-cards_deny_update_html_not_own'; const DENY_UPDATE_HTML_NOT_OWN_TEXT = 'У вас нет права на редактирование дневников других пользователей'; const DENY_UPDATE_HTML_NOT_OWN_CODE = - 403; private $db; private function log($message, $type = 0) { app_log($message, $type, "cardTimeTest"); } public function readCards($params = null) { $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $sessionDoctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $doctorId = isEmptyProperty($params, 'doctorId') ? $params->doctorId : ($sessionDoctorId ? $sessionDoctorId : 0); $taskDate = Date('Ymd'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $dir = isEmptyProperty($params, 'dir', 'asc'); $sort = isEmptyProperty($params, 'sort'); $sqlOrder = "ORDER BY "; if (!empty($sort)) { switch (true) { case ($sort === 'appointmentTime'): $sqlOrder .= "
                    CASE WHEN Dates.AdditionalCoupon IS NULL AND Dates.RealAdditionalCoupon = TRUE THEN 1 ELSE 0 END ASC,
                    DATETIME(Dates.VisitTime) $dir"; break; case ($sort === "visitTime"): $sqlOrder .= "
                    Dates.Flag,
                    CASE
                        WHEN Dates.Flag IN (1) THEN DATETIME(Dates.VisitTime)
                        WHEN Dates.Flag IN (2, 4, 5, 6) THEN NULL
                    END $dir,
                    CASE
                        WHEN Dates.Flag IN (1) THEN NULL
                        WHEN Dates.Flag IN (2, 4, 5, 6) THEN Dates.medVisitPatientCode
                    END ASC"; break; default: if ($sort === 'numberHistory') { $sqlOrder .= " RIGHT(REPEAT(' ', 20) + ASA_TRIM(MP.NumberHistorySickness), 20) $dir, "; } else { $sqlOrder .= " \"$sort\" $dir, "; } $sqlOrder .= "
                    Dates.Flag,
                    CASE
                        WHEN Dates.Flag IN (1) THEN DATETIME(Dates.VisitTime)
                        WHEN Dates.Flag IN (2, 4, 5, 6) THEN NULL
                    END,
                    CASE
                        WHEN Dates.Flag IN (1) THEN NULL
                        WHEN Dates.Flag IN (2, 4, 5, 6) THEN Dates.MedVisitPatientCode
                    END ASC"; } } else { $sqlOrder .= "
                Dates.Flag,
                CASE
                    WHEN Dates.Flag IN (1) THEN DATETIME(Dates.VisitTime)
                    WHEN Dates.Flag IN (2, 4, 5, 6) THEN NULL
                END,
                CASE
                    WHEN Dates.Flag IN (1) THEN NULL
                    WHEN Dates.Flag IN (2, 4, 5, 6) THEN Dates.MedVisitPatientCode
                END ASC"; } $doctorInfo = $this->readDoctorInfo($doctorId); $sql = <<<SQL
            SELECT
                Dates.MedPatientCode                                    "id",
                MP.NumberHistorySickness                                "card",
                FN_cmFIO(MP.ManCode)                                    "pFio",
                MPt.DateOfBirth                                         "dateOfBirth",
                MP.OutReasonEx                                          "outReason",
                MT.Note                                                 "patientNote",
                Dates.CouponNote                                        "couponNote",
                MP.ArchivalNumber                                       "archivalNumber",
                (
                    CASE
                        WHEN COALESCE(MP.FinishDate, 30000101)::DATE = 30000101
                            OR MP.FinishDate > DATE('NOW'::DATE) THEN 1
                        ELSE 0
                    END
                )                                                       "isActive",
                MP.FinishDate                                           "finishDate",
                FN_medPatientPictureList(Dates.MedPatientCode, NULL, 2, 1)
                                                                        "shortStatesList",
                MPG.Name                                                "group",
                FN_cmCalcAddress(MP.ManCode, 1, 0, 1)                   "resAddress",
                CASE ASA_TRIM((FN_cmCalcAddress(MP.ManCode, 1, 0, 1))) = ''
                    WHEN TRUE THEN FN_cmCalcAddress(MP.ManCode, 2, 0, 1)
                    WHEN FALSE THEN (FN_cmCalcAddress(MP.ManCode, 1, 0, 1))
                END                                                     "address",
                CASE (
                    MP.OutReason IS NOT NULL
                        AND MP.OutReason <> (2)
                        AND MP.FinishDate <= ($finishDate)
                    )
                    WHEN TRUE THEN 1
                    WHEN FALSE THEN NULL
                END                                                     "died",
                COALESCE((
                    SELECT 1
                    FROM MedVisit MV_
                    JOIN MedVisitPatient MVP_ ON MVP_.MedVisitCode = MV_.MedVisitCode
                        AND MVP_.MedPatientCode = Dates.MedPatientCode
                    WHERE MV_.MedDoctorCode = Dates.DoctorCode
                        AND MV_.VisitDate = DATE(Dates.VisitDate)
                    LIMIT 1), 0
                )::INTEGER                                              "visitDone",
                COALESCE(Dates.VisitDate, 19000101)                     "dateVisitTo",
                COALESCE(Dates.VisitDate, 19000101)                     "dateCoupon",
                COALESCE(DATE(Dates.VisitTime), 19000101)               "dateCall",
                COALESCE(DATE(Dates.VisitTime), 19000101)               "dateCallVisit",
                DATETIME(Dates.VisitTime)                               "timeCoupon",
                Dates.VisitTime                                         "visitTime",
                Dates.flag                                              "flag",
                Dates.DoctorCode                                        "doctorId",
                MPt.Sex                                                 "sex",
                FN_wgCalcStage(2, $taskDate, MPt.DateOfBirth, 0, 0, 0, NULL)
                                                                        "age",
                (
                    SELECT STRING_AGG((MPG_.Name)::TEXT, ', ' ORDER BY MPG_.Name)
                    FROM MedPatientAddGroup MPAG_
                    JOIN MedPatientGroup MPG_ ON MPG_.MedPatientGroupCode = MPAG_.MedPatientGroupCode
                    WHERE MPAG_.MedPatientCode = Dates.MedPatientCode
                )                                                       "specGroup",
                (
                    SELECT STRING_AGG(E_.Name, ', ' ORDER BY E_.Name)
                    FROM MedPatientBusy MPB_
                    INNER JOIN Enterprise E_ ON E_.EnterpriseCode = MPB_.EnterpriseCode
                    WHERE $taskDate BETWEEN MPB_.StartDate AND COALESCE(MPB_.FinishDate, 30000101)
                        AND MPB_.MedPatientCode = Dates.MedPatientCode
                )                                                       "busyness",
                Dates.vType                                             "vType",
                CASE -- Если талоны
                    WHEN Flag IN (1, 5) THEN (
                        SELECT Name
                        FROM MedCouponWork
                        WHERE COALESCE(FlagCoupon, 0) <> 0
                            AND MedCouponWorkCode = Dates.vType
                    )
                END                                                     "vTypeName",
                Dates.VisitPlace                                        "visitPlace",
                Dates.VisitCaseNew                                      "visitCaseNew",
                Dates.MedVisitPatientCode                               "medVisitPatientCode",
                Dates.MedInspectCode                                    "medInspectCode",
                (
                    SELECT STRING_AGG(
                        (
                            CASE MPH.PhoneType
                                WHEN 1 THEN 'д.'
                                WHEN 2 THEN 'р.'
                                WHEN 3 THEN 'м.'
                                ELSE ''
                            END || ' ' || MPH.PhoneNumber
                        )::TEXT, ', ' ORDER BY MPH.PhoneType DESC
                    )
                    FROM ManPhone MPH
                    WHERE MPH.ManCode = MP.ManCode
                        AND COALESCE(MPH.PhoneNumber, '') <> ''
                        AND (
                            MPH.PhoneType IN (1, 2, 3)
                                OR MPH.PhoneType IS NULL
                        )
                )                                                       "phoneList",
                Dates.AdditionalCoupon                                  "additionalCoupon",
                Dates.RealAdditionalCoupon                              "realAdditionalCoupon",
                COALESCE(CL.Status, 0)                                  "ciszState",
                CASE
                    WHEN Dates.VisitCaseNew IS NULL THEN 4
                    ELSE COALESCE(CLV.Status, 0)
                END                                                     "ciszVisitState"
            FROM (
                SELECT
                    MedPatientCode,
                    MAX(VD)                         AS VisitDate,
                    CASE WHEN MAX(HasVisit) = 1 THEN
                         CASE WHEN SUM(DISTINCT Flag) IN (1) THEN 5
                              WHEN SUM(DISTINCT Flag) IN (2) THEN 6
                              ELSE SUM(DISTINCT Flag) END
                    ELSE SUM(DISTINCT Flag) END AS Flag,
                    MAX(VTime)                      AS VisitTime,
                    MAX(MedDoctorCode)              AS DoctorCode,
                    MAX(VType)                      AS VType,
                    MAX(VisitPlace)                 AS VisitPlace,
                    MAX(VisitCaseNewAll)            AS VisitCaseNew,
                    MAX(MedVisitPatientCode)        AS MedVisitPatientCode,
                    MAX(MedInspectCode)             AS MedInspectCode,
                    -- Патч ИИ
                    -- WS-15799: признаки доп.талона агрегируем, а не группируем по ним,
                    -- иначе посещение (признаки NULL) и доп.талон пациента попадают в разные строки
                    -- с одинаковым MedPatientCode и отметка посещения у доп.талона пропадает
                    BOOL_OR(AdditionalCoupon)       AS AdditionalCoupon,
                    BOOL_OR(RealAdditionalCoupon)   AS RealAdditionalCoupon,
                    MAX(CouponNote)                 AS CouponNote
                FROM (
                        SELECT
                            *,
                            MAX(VisitCaseNew) OVER ( PARTITION BY MedPatientCode, Vd ) AS VisitCaseNewAll,
                            MAX(CASE WHEN Flag = 4 THEN 1 ELSE 0 END) OVER ( PARTITION BY MedPatientCode, Vd ) AS HasVisit,
                            ROW_NUMBER() OVER (
                                PARTITION BY MedPatientCode, Vd
                                ORDER BY VTime IS NULL
                            ) AS RN
                        FROM (
                            SELECT
                                MedPatientCode,
                                MV.VisitDate        AS vd,
                                4                   AS Flag,
                                NULL                AS VTime,
                                NULL                AS MedDoctorCode,
                                NULL                AS VType,
                                VisitPlace,
                                VisitCaseNew,
                                MedVisitPatientCode,
                                MVDOC.MedInspectCode,
                                NULL                AS AdditionalCoupon,
                                NULL                AS RealAdditionalCoupon,
                                NULL                AS CouponNote
                            FROM MedVisitPatient MVP
                            LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
                            LEFT JOIN LATERAL (
                                SELECT MedInspectCode
                                FROM MedVisitDocument
                                WHERE MedVisitPatientCode = MVP.MedVisitPatientCode
                                ORDER BY MedVisitDocumentCode
                                LIMIT 1
                            ) MVDOC ON TRUE
                            WHERE MV.VisitDate BETWEEN $startDate AND $finishDate
                                AND MVP.MedVisitPatientCodeParent IS NULL
                                AND MVP.MedPhantomCode IS NULL
                                AND MVP.VisitCount IS NULL
                                AND (0 = $doctorId OR MV.MedDoctorCode = $doctorId)
                                AND COALESCE(MVP.AttendantDiagnosis, 0) = 0
                            UNION
                            SELECT
                                MedPatientCode,
                                Date(VTime),
                                Flag,
                                VTime,
                                MedDoctorCode,
                                VType,
                                NULL            AS VisitPlace,
                                NULL            AS VisitCaseNew,
                                NULL            AS MedVisitPatientCode,
                                NULL            AS MedInspectCode,
                                AdditionalCoupon,
                                RealAdditionalCoupon,
                                CouponNote
                            FROM (
                                SELECT
                                    MedPatientCode,
                                    Flag,
                                    VTime,
                                    MedDoctorCode,
                                    VType,
                                    AdditionalCoupon,
                                    RealAdditionalCoupon,
                                    CouponNote,
                                    RANK () OVER (PARTITION BY MedPatientCode ORDER BY VTime DESC) R
                                FROM (
                                    SELECT
                                        MedPatientCode,
                                        2                   AS Flag,
                                        DATEFORMAT(COALESCE(CallTime, 19000101), 'yyyy-mm-dd 23:59')
                                                            AS VTime,
                                        MedDoctorCode,
                                        CallType            AS VType,
                                        NULL                AS AdditionalCoupon,
                                        NULL                AS RealAdditionalCoupon,
                                        NULL                AS CouponNote
                                    FROM MedCall
                                    WHERE CallTime BETWEEN $startDate AND DateAdd('day', 1, $finishDate)
                                        AND EnterpriseCode = $enterpriseId
                                        AND MedPatientCode IS NOT NULL
                                        AND (0 = $doctorId OR MedDoctorCode = $doctorId)
                                    UNION
                                    SELECT
                                        MC.MedPatientCode,
                                        1 AS Flag,
                                        DATEFORMAT(COALESCE(MC.OnDate, 19000101), 'yyyy-mm-dd')
                                            || ' '
                                            || DATEFORMAT(COALESCE(MC.VisitTime, MCA.VisitTime, 19000101), 'hh:nn') AS VTime,
                                        MC.MedDoctorCode,
                                        COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode) AS vType,
                                        CASE WHEN MCA.VisitTime IS NOT NULL THEN TRUE END AS AdditionalCoupon,
                                        CASE WHEN MC.VisitTime IS NULL THEN TRUE END AS RealAdditionalCoupon,
                                        MC.Note AS CouponNote
                                    FROM MedCoupon MC
                                    LEFT JOIN MedCouponAdd MCA ON MCA.MedCouponCode = MC.MedCouponCode
                                    WHERE MC.OnDate BETWEEN $startDate AND $finishDate
                                        AND MedPatientCode IS NOT NULL
                                        AND (0 = $doctorId OR MC.MedDoctorCode = $doctorId)
                                ) CallCouponAll
                            ) CallCouponMax
                            WHERE R = 1
                        )
                ) InnerDates
                GROUP BY MedPatientCode
            ) Dates
            JOIN MedPatient MP ON MP.MedPatientCode = Dates.MedPatientCode
            LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode
            LEFT JOIN ManPassport MPt ON MPt.ManCode = MP.ManCode
            LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = Dates.DoctorCode
            LEFT JOIN ManTask MT ON MT.ManCode = MP.ManCode
                AND MT.EnterpriseCode = $enterpriseId
                AND MT.TypeTask = 12
            LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
            LEFT JOIN LATERAL (
                SELECT Status
                FROM CiszLink
                WHERE Type = 2
                    AND (
                        (Dates.MedInspectCode IS NOT NULL
                            AND TableName = 'MedInspect'
                            AND PrimaryKey = Dates.MedInspectCode)
                        OR (Dates.MedInspectCode IS NULL
                            AND TableName = 'MedVisitPatient'
                            AND PrimaryKey = Dates.MedVisitPatientCode)
                    )
                LIMIT 1
            ) CLV ON TRUE
            WHERE MP.EnterpriseCode = $enterpriseId
                AND ((
                        MD.StartDate IS NOT NULL
                        AND MD.FinishDate IS NOT NULL
                    )
                    OR Dates.DoctorCode IS NULL
                )
            $sqlOrder
            SQL;
$root = new stdClass(); $root->doctorInfo = $doctorInfo; $root->cards = selectSql($db, $sql); return $root; } public function readDoctorInfo($doctorId = null) { $db = $this->db ?: $this->db = connectDb(); $sessionDoctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $id = isset($doctorId) ? $doctorId : ($sessionDoctorId ? $sessionDoctorId : 0); $sql = <<<"EOT"
select
       MD.MedDoctorCode "doctorId",
       FN_cmFIO(MD.ManCode) "fio",
       MD.departmentCode "departmentId",
       D.Name "department",
       MD.medProfessioncode "professionId",
       MP.Name "profession",
       E.EnterpriseCode "enterprsieId",
       E.Name "enterprise"
from MedDoctor MD
     join Department D on D.DepartmentCOde = MD.DepartmentCode
     join Branch B on B.BranchCode = D.BranchCode
     join Enterprise E on E.EnterpriseCode = B.EnterpriseCode
     left join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
where MD.MedDoctorCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $rec = $stmt->fetch(); return $rec ? $rec : []; } public function readCardData($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $taskDate = Date('Ymd'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
       MP.MedPatientCode "id",
       MP.NumberHistorySickness "card",
       MP.personDataPermissionDate "personDataPermissionDate",
       M.SurName "sName",
       M.Name "pName",
       M.SecondName "sdName",
       FN_cmFIO(MP.ManCode) "fio",
       MPP.DateOfBirth "dateOfBirth",
       MP.ManCode "manCode",
       MPP.Sex "sex",
       0 as "photoExist",
       MP.PhotoId as "photoId",
       FN_cmCalcAddress(MP.ManCode, 5, 0, 1) "address",
       (select string_agg((CASE WHEN PhoneType = 1 THEN ' дом. '
                                WHEN PhoneType = 2 THEN ' служ. '
                                WHEN PhoneType = 3 THEN ' м. '
                                ELSE ' доп. '
                           END || PhoneNumber)::text, ',')
        from ManPhone
        where ManCode = MP.ManCode AND PhoneType <> 4)::text "phones",
       MPG.Name "groupName",
       MP.OutReasonEx "outReason",
       (CASE
            WHEN COALESCE(MP.FinishDate, 30000101)::date = 30000101 OR MP.finishDate > DATE(NOW()) THEN 1
            ELSE 0
        END) "isActive",
       NULLIF(MP.FinishDate, 30000101) "finishDate",
       MP.MedPatientGroupCode "patientGroupId",
       (select string_agg((CASE asa_trim(_MPR.Name) <> ''
                                WHEN true THEN DATEFORMAT(_MPC.StartDate, 'dd.mm.yyyy' ) ||  ' - ' || asa_trim(_MPR.Name) || CASE COALESCE(_MD.MedDiagnosisCode, 0) <> 0
                                                                                                                                  WHEN true THEN ' - (' || _MD.Cipher1 || ') ' || _MD.Name
                                                                                                                                  WHEN false THEN ''
                                                                                                                             END || CASE COALESCE(_MPR.PrivilegePercent, 0) <> 0
                                                                                                                                         WHEN true THEN ' (' || asa_trim(STR(_MPR.PrivilegePercent)) || '%)'
                                                                                                                                         WHEN false THEN ''
                                                                                                                                  END
                           END)::text, ' <br>' order by _MPC.StartDate)
        from MedPatientCard _MPC
             left join MedPrivelege _MPR on _MPR.MedPrivelegeCode = _MPC.MedPrivelegeCode
             left join MedDiagnosis _MD on _MD.MedDiagnosisCode = _MPC.MedDiagnosisCode
        where _MPC.MedPatientCode = MP.MedPatientCode
          and _MPC.StartDate <= $taskDate
          and COALESCE(_MPC.FinishDate, 30000101) > $taskDate)::text "priveleges",
       (select string_agg(_MPG.Name::text, ', ' order by _MPG.Name)
        from MedPatientAddGroup _MPAG
             left join MedPatientGroup _MPG on _MPG.MedPatientGroupCode = _MPAG.MedPatientGroupCode
        where _MPAG.MedPatientCode = MP.MedPatientCode)::text "addGroupName",
       (select string_agg(E_.Name, ', ' order by E_.Name)
        from MedPatientBusy MPB_
             join Enterprise E_ on E_.EnterpriseCode = MPB_.EnterpriseCode
        where $taskDate between MPB_.StartDate and COALESCE(MPB_.FinishDate, 30000101)
          and MPB_.MedPatientCode = MP.MedPatientCode) "busyness",
        FN_wgCalcStage(2, $taskDate,  MPP.DateOfBirth, 0, 0, 0, null) "age",
        (select 1 from MedDispensary where MedPatientCode = MP.MedPatientCode limit 1) "dispensaryFlag",
        MPQ.documentdate "questionary",
        (select max(inspectDate) from MedInspect MI where MI.enterprisecode = MP.EnterpriseCode AND MI.medpatientcode = MP.MedPatientCode AND inspectDate <= $taskDate ) as "dispensarylastInspectDate",
    MP.Anonymous AS "anonymous",
    CASE WHEN (COALESCE(MP.Attributes,0) & 2) = 2 THEN 1 ELSE 0 END "unknown",
    MBC.BarCode "personalCardNumber",
           (SELECT string_agg(_MPG.Name::text, ', ' order by _MPG.Name)
        FROM MedPatientRegistration _MPR
            LEFT JOIN MedPatientGroup _MPG ON _MPG.MedPatientGroupCode = _MPR.MedPatientGroupCode
        WHERE _MPR.MedPatientCode = MP.MedPatientCode
        AND $taskDate BETWEEN _MPR.StartDate AND COALESCE(_MPR.FinishDate, 30000101))::text "mainGroupName",
    FN_cmCalcAddress(MP.ManCode, 0)::text as "addressRegistration",
    FN_cmCalcAddress(MP.ManCode, 1)::text as "addressReside",
    MPP.PersonalNumber "personalNumber",
    MPK.Name "passportKind",
    MPP.PassportNumber "passportNumber",
    MPP.Authority "passportAuthority",
    MPP.DateOfIssue "passportDate",
    COALESCE(CL.Status,0) "ciszStatus",
(
    SELECT string_agg(
        asa_trim(_MPR.Name) || ', ' || DATEFORMAT(_MPC.StartDate, 'dd.mm.yyyy'),
        '; '
        ORDER BY _MPC.StartDate
    )
    FROM MedPatientCard _MPC
         LEFT JOIN MedPrivelege _MPR
             ON _MPR.MedPrivelegeCode = _MPC.MedPrivelegeCode
    WHERE _MPC.MedPatientCode = MP.MedPatientCode
      AND _MPC.StartDate <= $taskDate
      AND COALESCE(_MPC.FinishDate, 30000101) > $taskDate
)::text "privelegesNew"
from MedPatient MP
     join Man M on M.ManCode = MP.ManCode
     left join MedPatientGroup MPG on MPG.MedPatientGroupCode = MP.MedPatientGroupCode
     left join ManPassport MPP on MPP.ManCode = MP.ManCode
     left join MedBarCode MBC on MBC.ManCode = MP.ManCode
                             and $taskDate between MBC.StartDate and DATEADD('day', -1, COALESCE(MBC.StopDate, 30000101))
     left join (select _MPQ.MedPatientCode, _MPQ.documentdate from MedPatientQuestionary _MPQ
            where _MPQ.MedPatientCode = $id order by _MPQ.documentdate limit 1) MPQ on MPQ.MedPatientCode = MP.MedPatientCode
     LEFT JOIN ManPassportKind MPK ON MPP.ManPassportKindCode = MPK.ManPassportKindCode
     LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
where MP.EnterpriseCode = $enterpriseId
  and MP.MedPatientCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = $stmt->fetch(); $storage = new map_common_storage(); if ($root['photoId'] != null) { $fileName = $fileName = "patients-photo/" . mb_substr($root['photoId'], 0, 1) . "/" . mb_substr($root['photoId'], 1, 2) . "/" . $root['photoId'] . ".jpg"; if ($storage->isFileExists(2, $fileName)) { $root['photoExist'] = 1; $storage = new map_common_storage(); $root['photoBase'] = base64_encode($storage->readFile(2, $fileName)); } } return $root; } public function checkCardData($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $taskDate = Date('Ymd'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    MP.MedPatientCode AS "id",
    CASE
        WHEN (
            MPP.CiszAddressCode IS NOT NULL
            OR (
                CR.NAME IS NOT NULL
                AND TRIM(CR.NAME) <> ''
                AND CR.NAME NOT ILIKE '%беларус%'
                AND CR.NAME <> 'РБ'
            )
            OR MPP.AddressCode IS NULL
        )
        AND (
            MPP.CiszAddressResideCode IS NOT NULL
            OR (
                C.NAME IS NOT NULL
                AND TRIM(C.NAME) <> ''
                AND C.NAME NOT ILIKE '%беларус%'
                AND C.NAME <> 'РБ'
            )
            OR MPP.AddressResideCode IS NULL
        )
        THEN FALSE
        ELSE TRUE
    END AS "isCiszAddress",
    CASE WHEN COALESCE(CL.Status,0) = 0 THEN TRUE ELSE FALSE END AS "isCiszSend",
    CASE WHEN MP.personDataPermissionDate IS NOT NULL THEN FALSE ELSE TRUE END "isAgreement",
CASE
    WHEN MP.FinishDate < $taskDate::date
    THEN FALSE
    
    WHEN MPP.DateOfBirth > ($taskDate::date - INTERVAL '1 month')
    THEN FALSE
    
    WHEN MPP.DateOfBirth > ($taskDate::date - INTERVAL '1 year')
    THEN NOT EXISTS (
        SELECT 1 FROM MedInspect MI
        WHERE MI.EnterpriseCode = MP.EnterpriseCode
          AND MI.MedPatientCode  = MP.MedPatientCode
          AND MI.InspectDate BETWEEN ($taskDate::date - INTERVAL '1 month') AND $taskDate
    )

    WHEN MPP.DateOfBirth > ($taskDate::date - INTERVAL '18 years')
    THEN NOT EXISTS (
        SELECT 1 FROM MedInspect MI
        WHERE MI.EnterpriseCode = MP.EnterpriseCode
          AND MI.MedPatientCode  = MP.MedPatientCode
          AND MI.InspectDate BETWEEN ($taskDate::date - INTERVAL '1 year') AND $taskDate
    )

    WHEN MPP.DateOfBirth > ($taskDate::date - INTERVAL '40 years')
    THEN NOT EXISTS (
        SELECT 1 FROM MedInspect MI
        WHERE MI.EnterpriseCode = MP.EnterpriseCode
          AND MI.MedPatientCode  = MP.MedPatientCode
          AND MI.InspectDate BETWEEN ($taskDate::date - INTERVAL '3 years') AND $taskDate
    )

    ELSE NOT EXISTS (
        SELECT 1 FROM MedInspect MI
        WHERE MI.EnterpriseCode = MP.EnterpriseCode
          AND MI.MedPatientCode  = MP.MedPatientCode
          AND MI.InspectDate BETWEEN ($taskDate::date - INTERVAL '1 year') AND $taskDate
    )
END AS "isInspect"
FROM MedPatient MP
JOIN Man M ON M.ManCode = MP.ManCode
LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode
LEFT JOIN ManPassport MPP ON MPP.ManCode = MP.ManCode
LEFT JOIN Address A ON A.AddressCode = MPP.AddressResideCode
LEFT JOIN Country C ON C.CountryCode = A.CountryCode
LEFT JOIN Address AR ON AR.AddressCode = MPP.AddressCode
LEFT JOIN Country CR ON CR.CountryCode = AR.CountryCode
LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
WHERE MP.EnterpriseCode = $enterpriseId
  AND MP.MedPatientCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = $stmt->fetch(); return $root; } public function readPatientDynamicObservation($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $taskDate = Date('Ymd'); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    MD.MedDispensaryCode AS "id",
    MDG.Cipher1 AS "diagCipher",
    fn_cmfio(MDC.Mancode, 1) || ', ' || MPR.Name AS "doctor",

    (
        SELECT string_agg(
            'Контрольный осмотр ДН ' || MDG.Cipher1 ||
            ' не пройден ' ||
            DATEFORMAT(MDT.AssignedDate, 'dd.mm.yyyy'),
            ', ' ORDER BY MDG.Cipher1, MDT.AssignedDate
        )
        FROM MedDispensaryTurnout MDT
        WHERE MDT.MedDispensaryCode = MD.MedDispensaryCode
          AND $taskDate > MDT.AssignedDate
          AND MDT.VisitDate IS NULL
    ) AS "controlTurnout",
    (
        SELECT string_agg(
            'Исследование ДН ' || MDG.Cipher1 ||
            ' не пройден ' ||
            DATEFORMAT(MDS.AppointmentDate, 'dd.mm.yyyy'),
            ', ' ORDER BY MDG.Cipher1, MDS.AppointmentDate
        )
        FROM MedDispensaryStudy MDS
        LEFT JOIN MedLogStudy MLS
            ON MLS.MedLogStudyCode = MDS.MedLogStudyCode
        WHERE MDS.MedDispensaryCode = MD.MedDispensaryCode
          AND $taskDate > MDS.AppointmentDate
          AND MLS.ExecTime IS NULL
    ) AS "controlStudy"

FROM MedPatient MP
JOIN Man M ON M.ManCode = MP.ManCode
JOIN MedDispensary MD ON MD.MedPatientCode = MP.MedPatientCode
   AND $taskDate BETWEEN MD.StartDate AND COALESCE(MD.FinishDate, 30000101)
LEFT JOIN MedDiagnosis MDG ON MDG.MedDiagnosisCode = MD.MedDiagnosisCode
LEFT JOIN MedDoctor MDC ON MDC.MedDoctorCode = MD.MedDoctorCode
LEFT JOIN MedProfession MPR ON MPR.MedProfessionCode = MDC.MedProfessionCode
WHERE MP.EnterpriseCode = $enterpriseId
  AND MP.MedPatientCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = $stmt->fetchAll(); return $root; } function checkImportant($patientId, $type) { $db = $this->db ?: $this->db = connectDb(); $sqlCheckImportant = <<<"EOT"
select important  from medlogstudy
  where medpatientCode = $patientId and important=1  and payAttention=1 and type = $type
EOT;
$stmt = $db->prepare($sqlCheckImportant); $stmt->execute(); return $stmt->rowCount() > 0 ? true : false; } function checkNotDoctorViewed($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $idsHave = isEmptyProperty($params, 'idsHave'); if ($idsHave) { $idsHave = " and medEmergencyCode not in ($idsHave)"; } else { $idsHave = ""; } $sql = <<<"EOT"
select
    medEmergencyCode "id"
from MedEmergency
where medPatientCode = ? and doctorViewed is null and status <> 3 $idsHave
EOT;
$records = selectSql($db, $sql, [ $patientId ]); if (count($records)) { return $records; } else { $result = new stdClass(); $result->result = false; return $result; } } function osort(&$array, $props) { if (!is_array($props)) { $props = array($props => true); } usort($array, function($a, $b) use ($props) { foreach ($props as $prop => $ascending) { if ($a[$prop] != $b[$prop]) { if ($ascending) { return $a[$prop] > $b[$prop] ? 1 : -1; } else { return $b[$prop] > $a[$prop] ? 1 : -1; } } } return -1; }); } function keysInObject($test, $source) { if (is_array($test)) { foreach ($test as $item) { $res = property_exists($source, $item); if ($res) { return true; } } return false; } else { return property_exists($source, $test); } } function checkRecord($item, $obj) { foreach ($obj as $key => $value) { if ($item[$key] != $value) { return false; } } return true; } public function readCardDocuments($params) { $dayHospitalCode = isEmptyProperty($params, 'dayHospitalCode', null); $limit = isEmptyProperty($params, 'limit', self::PAGE_SIZE); $page = isEmptyProperty($params, 'page', 0); $groupTypes = isEmptyProperty($params, 'groupTypes', []); $tmpParams = new stdClass(); $tmpParams->patientId = isUndefinedProperty($params, 'patientId', 0); if ($dayHospitalCode) { $tmpParams->id = $dayHospitalCode; } $tmpParams->searchText = isEmptyProperty($params, 'searchText'); $tmpParams->isCard = true; self::NEED_LOG && $this->log("Загрузка документов..."); self::NEED_LOG && $this->log("Загрузка данных..."); self::NEED_LOG && $timeAll = $startTime = $startTimeData = microtime(true); $visits = []; $visitDocuments = []; $addVisitDocuments = []; $isLoad = self::keysInObject(0, $groupTypes); $isLoadVisits = self::keysInObject(1, $groupTypes); $isLoadVisitDocuments = self::keysInObject(array(1, 2), $groupTypes); if ($isLoad || $isLoadVisitDocuments) { $tmpParams->externalDocumentsSourceCode = isEmptyProperty($params, 'externalDocumentsSourceCode'); $visits = $this->readVisits($tmpParams); $visitId = []; $visitAddIds = ''; foreach ($visits as $row) { $visitId[] = (object) $row; if (!empty($row['addVisitId'])) { $visitAddIds .= ($visitAddIds === '' ? '' : ',') . $row['addVisitId']; } } $visitsIds = createSqlInCondition($visitId, 'recordId'); $tmpParams->visitIds = $visitsIds; $visitDocuments = $this->readVisitDocuments($tmpParams); unset($tmpParams->visitIds); } self::NEED_LOG && $this->log("load visits =                 " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $hospitals = []; $isLoadHospitals = self::keysInObject(3, $groupTypes); if ($isLoad || $isLoadHospitals) { $hospitals = $this->readHospital($tmpParams); } self::NEED_LOG && $this->log("load hospitals =              " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $studies = []; $isAllStudies = self::keysInObject(4, $groupTypes); $isStudies = self::keysInObject(array(5, 6, 7), $groupTypes); if ($isLoad || $isAllStudies || $isStudies) { $tmpParams->type = []; if ($isLoad || $isAllStudies || self::keysInObject(5, $groupTypes)) { $tmpParams->type[] = 1; } if ($isLoad || $isAllStudies || self::keysInObject(6, $groupTypes)) { $tmpParams->type[] = 2; } if ($isLoad || $isAllStudies || self::keysInObject(7, $groupTypes)) { $tmpParams->type[] = 3; } $studies = $this->readStudy($tmpParams); unset($tmpParams->type); } self::NEED_LOG && $this->log("load studies =                " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $dispensary = []; $isLoadDispensary = self::keysInObject(8, $groupTypes); if ($isLoad || $isLoadDispensary) { $dispensary = new med_common_patientDispensaries(); $dispensary = $dispensary->readPatientDispensaries($tmpParams); } self::NEED_LOG && $this->log("load dispensary =             " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $recipes = []; $isLoadRecipes = self::keysInObject(9, $groupTypes); if ($isLoad || $isLoadRecipes) { $recipes = new med_recipes_recipes(); $recipes = $recipes->selectRecipes($tmpParams); } self::NEED_LOG && $this->log("load recipes =                " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $inoculationsLog = []; $isLoadInoculationsLog = self::keysInObject(10, $groupTypes); if ($isLoad || $isLoadInoculationsLog) { $inoculationsLog = new med_vaccinations_inoculationsLog(); $inoculationsLog = $inoculationsLog->readInoculationsLog($tmpParams); } self::NEED_LOG && $this->log("load inoculationsLog =        " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $contrLog = []; $isLoadContrLog = self::keysInObject(11, $groupTypes); if ($isLoad || $isLoadContrLog) { $contr = new med_vaccinations_contrsLog(); $contrLog = $contr->readContrsLog($tmpParams); } self::NEED_LOG && $this->log("load contrLog =               " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $flurs = []; $isLoadFlurs = self::keysInObject(13, $groupTypes); if ($isLoad || $isLoadFlurs) { $flurs = $this->readFlurs($tmpParams); } self::NEED_LOG && $this->log("load flurs =                  " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $inspections = []; $isLoadInspections = self::keysInObject(14, $groupTypes); if ($isLoad || $isLoadInspections) { $inspections = new med_examinations_inspections(); $inspections = $inspections->readInspections($tmpParams); } self::NEED_LOG && $this->log("load inspections =            " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $dayHospitals = []; $dayHospitalDocuments = []; $isLoadDayHospitalDocuments = self::keysInObject(array(15, 16), $groupTypes); if ($isLoad || $isLoadDayHospitalDocuments) { $dayHospitals = new med_dayhospital_log(); $dayHospitals = $dayHospitals->readDayHospital($tmpParams); $dayHospitalId = []; foreach ($dayHospitals as $row) { $dayHospitalId[] = (object) $row; } $dayHospitalsIds = createSqlInCondition($dayHospitalId, 'recordId'); $tmpParams->dayHospitalIds = $dayHospitalsIds; $dayHospitalDocuments = new med_dayhospital_log(); $dayHospitalDocuments = $dayHospitalDocuments->readDayHospitalDocuments($tmpParams); unset($tmpParams->dayHospitalIds); } self::NEED_LOG && $this->log("load dayHospitals =           " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $ambulOperationDocuments = []; $isLoadAmbulOperationsDocuments = self::keysInObject(17, $groupTypes); if ($isLoad || $isLoadAmbulOperationsDocuments) { $ambulOperationInstance = new med_clinicstats_logOperations(); $ambulOperationRecords = $ambulOperationInstance->readLogOperations($tmpParams); $idsArray = []; foreach ($ambulOperationRecords as $row) { $idsArray[] = $row['id']; } $ambulOperationRecordIds = implode(',', $idsArray); if (!empty($ambulOperationRecordIds)) { $ambulOperationDocuments = $ambulOperationInstance->getLogOperationDocuments($ambulOperationRecordIds); } } self::NEED_LOG && $this->log("load ambulOperationdocuments =        " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $ambulOperations = []; $documentsForAmbulOperations = []; $isLoadAmbulOperations = self::keysInObject(18, $groupTypes); if ($isLoad || $isLoadAmbulOperations) { $ambulOperationInstance = new med_clinicstats_logOperations(); $ambulOperations = $ambulOperationInstance->readLogOperations($tmpParams); $idsArray = []; foreach ($ambulOperations as $row) { $idsArray[] = $row['id']; } $ambulOperationRecordIds = implode(',', $idsArray); if (!empty($ambulOperationRecordIds)) { $documentsForAmbulOperations = $ambulOperationInstance->getLogOperationDocuments($ambulOperationRecordIds); } } self::NEED_LOG && $this->log("load ambulOperations =        " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $sends = []; $results = []; $isLoadSends = self::keysInObject(19, $groupTypes); if ($isLoad || $isLoadSends) { $sends = new med_reception_logSends(); $sends = $sends->readLogSends($tmpParams); if ($isLoadSends) { $results = new med_reception_logSends(); $tmpParams->resultType = 1; $tmpParams->groupTypes = $groupTypes; $results = $results->readLogSends($tmpParams); unset($tmpParams->resultType); unset($tmpParams->groupTypes); } } self::NEED_LOG && $this->log("load sends =                  " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $isLoadResults = self::keysInObject(20, $groupTypes); if (($isLoad || $isLoadResults) && empty($results)) { $results = new med_reception_logSends(); $tmpParams->resultType = 1; $tmpParams->groupTypes = $groupTypes; $results = $results->readLogSends($tmpParams); unset($tmpParams->resultType); unset($tmpParams->groupTypes); } self::NEED_LOG && $this->log("load results =                " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $diagnosis = []; $isLoadDiagnosis = self::keysInObject(21, $groupTypes); if ($isLoad || $isLoadDiagnosis) { $diagnosis = new map_med_diagnoses(); $diagnosis = $diagnosis->readPatientDiagnosis($tmpParams); } self::NEED_LOG && $this->log("load diagnosis =              " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $logHospital = []; $isLoadLogHospital = self::keysInObject(22, $groupTypes); if ($isLoad || $isLoadLogHospital) { $logHospital = $this->readLogHospital($tmpParams); } self::NEED_LOG && $this->log("load logHospital =            " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $ambulance = []; $isLoadAmbulance = self::keysInObject(23, $groupTypes); if ($isLoad || $isLoadAmbulance) { $tmpParams->noLimit = true; $ambulance = new med_clinicstats_ambulance(); $ambulance = $ambulance->readAmbulance($tmpParams); } self::NEED_LOG && $this->log("load ambulance =              " . (microtime(true) - $startTime)); $isStomVisits= self::keysInObject([30,1,2], $groupTypes); if ($isLoad || $isStomVisits ) { $stomVisits = $this->readStomVisits($tmpParams, self::keysInObject(1, $groupTypes) ); } $isLoadOncology = self::keysInObject(24, $groupTypes); $oncology = []; if ($isLoad || $isLoadOncology) { $oncology = $this->readOncologyDocument($tmpParams); $oncologyId = []; foreach ($oncology as $row) { $oncologyId[] = (object) $row; } $oncologyIds = createSqlInCondition($oncologyId, 'recordId'); $tmpParams->oncologyIds = $oncologyIds; $operations = $this->readOncologyOperation($tmpParams); unset($tmpParams->oncologyIds); } self::NEED_LOG && $this->log("load StomVisits =              " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Загрузка данных...            " . (microtime(true) - $startTimeData)); self::NEED_LOG && $startTime = microtime(true); $procedureSends = []; $isLoadProcedureSends = self::keysInObject(27, $groupTypes); if ($isLoad || $isLoadProcedureSends) { $procedureSends = new med_reception_procedureSendLog(); $procedureSends = $procedureSends->readProcedureSendLog($tmpParams); } self::NEED_LOG && $this->log("load procedure sends =                  " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $purposes = []; $isLoadPurposes = self::keysInObject(28, $groupTypes); if ($isLoad || $isLoadPurposes) { $drugsPurpose = new med_dayhospital_drugsPurpose(); $drugsPurpose = $drugsPurpose->readDrugsPurpose($tmpParams); $recipes = new med_recipes_recipes(); $recipes = $recipes->selectRecipes($tmpParams); $purposes = array_merge( is_array($drugsPurpose) ? $drugsPurpose : [], is_array($recipes) ? $recipes : [] ); usort($purposes, function ($a, $b) { return $a['recordDate'] <=> $b['recordDate']; }); } self::NEED_LOG && $this->log("load purpose =          " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $drugsPurpose = []; $isLoadDrugsPurpose = self::keysInObject(29, $groupTypes); if ($isLoad || $isLoadDrugsPurpose) { $drugsPurpose = new med_dayhospital_drugsPurpose(); $drugsPurpose = $drugsPurpose->readDrugsPurpose($tmpParams); } self::NEED_LOG && $this->log("load drugs purpose =                  " . (microtime(true) - $startTime)); self::NEED_LOG && $startTime = microtime(true); $medicalDevice = []; $isLoadMedicalDevice = self::keysInObject(32, $groupTypes); if ($isLoad || $isLoadMedicalDevice) { $medicalDevice = new med_recipes_medicalDevices(); $medicalDevice = $medicalDevice->readMedicalDevices($tmpParams); } self::NEED_LOG && $this->log("load medical devices =                  " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Объединение выборки..."); self::NEED_LOG && $startTime = microtime(true); $ret = []; if ($isLoad || self::keysInObject(1, $groupTypes)) { array_add($ret, $visits); } if ($isLoad || $isLoadVisitDocuments) { array_add($ret, $visitDocuments); } if ($isLoad || $isLoadHospitals) { array_add($ret, $hospitals); } if ($isLoad || $isLoadDispensary) { array_add($ret, $dispensary); } if ($isLoad || $isLoadRecipes) { array_add($ret, $recipes); } if ($isLoad || self::keysInObject(16, $groupTypes)) { array_add($ret, $dayHospitals); } if ($isLoad || $isLoadDayHospitalDocuments) { array_add($ret, $dayHospitalDocuments); } if ($isLoad || $isLoadInoculationsLog) { array_add($ret, $inoculationsLog); } if ($isLoad || $isLoadContrLog) { array_add($ret, $contrLog); } if ($isLoad || $isLoadDiagnosis) { array_add($ret, $diagnosis); } if ($isLoad || $isAllStudies || $isStudies) { array_add($ret, $studies); } if ($isLoad || $isLoadFlurs) { array_add($ret, $flurs); } if ($isLoad || $isLoadLogHospital) { array_add($ret, $logHospital); } if ($isLoad || $isLoadInspections) { array_add($ret, $inspections); } if ($isLoad || $isLoadSends) { array_add($ret, $sends); $isLoadSends && array_add($ret, $results); } if ($isLoad || $isLoadResults) { array_add($ret, $results); } if ($isLoad || $isLoadAmbulOperations) { array_add($ret, $ambulOperations); array_add($ret, $documentsForAmbulOperations); } if ($isLoad || $isLoadAmbulOperationsDocuments) { array_add($ret, $ambulOperationDocuments); } if ($isLoad || $isLoadAmbulance) { array_add($ret, $ambulance); } if ($isLoad || $isStomVisits) { array_add($ret, $stomVisits); } if ($isLoad || $isLoadOncology) { array_add($ret, $oncology); array_add($ret, $operations); } if ($isLoad || $isLoadProcedureSends) { array_add($ret, $procedureSends); } if ($isLoad || $isLoadPurposes) { array_add($ret, $purposes); } if ($isLoad || $isLoadDrugsPurpose) { array_add($ret, $drugsPurpose); } if ($isLoad || $isLoadMedicalDevice) { array_add($ret, $medicalDevice); } self::NEED_LOG && $this->log("Объединение выборки...        " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Обработка поиска..."); self::NEED_LOG && $startTime = microtime(true); if ($tmpParams->searchText) { $ret = $this->findDocument($ret, $tmpParams->searchText); } self::NEED_LOG && $this->log("Обработка поиска...           " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Сортировка... "); self::NEED_LOG && $startTime = microtime(true); if ($tmpParams->searchText) { self::osort($ret, array(self::SRCH_FLD_SORT => true, 'parentDate' => false, 'sortType' => true, 'path' => true)); } else { self::osort($ret, array('parentDate' => false, 'sortType' => true, 'path' => true)); } self::NEED_LOG && $this->log("Сортировка...                 " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Поиск страницы... "); self::NEED_LOG && $startTime = microtime(true); $count = count($ret); $retVisitsPage = null; $findObj = isEmptyProperty($params, 'findObj'); $start = 0; if ($findObj) { foreach ($ret as $key => $row) { if (self::checkRecord($row, $findObj)) { $page = (int) ($key / $limit); $start = $page * $limit; if ($isLoadVisits) { $groupedDocumentsByVisits = $this->groupDocumentsByVisits($ret); $count = count($groupedDocumentsByVisits); $pageData = array_slice($groupedDocumentsByVisits, $start, $limit); $retVisitsPage = $this->flattenDocuments($pageData); if ($retVisitsPage) { $ret = $retVisitsPage; } } break; } } } else { $start = $count <= $limit ? 0 : isEmptyProperty($params, 'start', 0); if ($isLoadVisits) { $groupedDocumentsByVisits = $this->groupDocumentsByVisits($ret); $count = count($groupedDocumentsByVisits); $start = $count <= $limit ? 0 : isEmptyProperty($params, 'start', 0); $pageData = array_slice($groupedDocumentsByVisits, $start, $limit); $retVisitsPage = $this->flattenDocuments($pageData); } } self::NEED_LOG && $this->log("Поиск страницы...             " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Выборка страницы... "); self::NEED_LOG && $startTime = microtime(true); $ret = array_slice($ret, $start, $limit); if ($isLoadVisits && $retVisitsPage) { $ret = $retVisitsPage; } self::NEED_LOG && $this->log("Выборка страницы...           " . (microtime(true) - $startTime)); $root = new stdClass(); $root->total = $count; $root->items = $ret; $root->page = $page; self::NEED_LOG && $this->log("Поиск важных исследований..."); self::NEED_LOG && $startTime = microtime(true); $root->importantLab = $this->checkImportant($tmpParams->patientId, 1); $root->importantLuch = $this->checkImportant($tmpParams->patientId, 2); $root->importantFunc = $this->checkImportant($tmpParams->patientId, 3); self::NEED_LOG && $this->log("Поиск важных исследований...  " . (microtime(true) - $startTime)); self::NEED_LOG && $this->log("Загрузка документов...        " . (microtime(true) - $timeAll)); return $root; } function groupDocumentsByVisits(array $items): array { $result = []; foreach ($items as $item) { $item['documents'] = []; if (empty($item['parentId'])) { $result[$item['recordId']] = $item; } } foreach ($items as $item) { if (!empty($item['parentId'])) { $parentId = explode('-', $item['path']); $result[$parentId[0]]['documents'][] = $item; } } return $result; } function flattenDocuments(array $items): array { $result = []; foreach ($items as $item) { $copy = $item; unset($copy['documents']); $result[] = $copy; if (!empty($item['documents'])) { $result = array_merge( $result, $this->flattenDocuments($item['documents']) ); } } return $result; } public function findDocument($array, $text) { if (empty($text)) { return $array; } $arrTypeNames = [ 1 => 'Посещение пациента', 2 => '', 3 => 'Лист временной нетрудоспособности', 4 => 'Лабораторное исследование', 5 => 'Лучевая диагностика', 6 => 'Функциональная диагностика', 7 => 'Диспансерный учет', 8 => [ 0 => 'Рецепт льготный', 1 => 'Рецепт', 2 => 'Рецепт особого контроля', ], 9 => 'Прививка', 10 => '', 11 => 'Противопоказание', 12 => 'Обследование', 13 => 'Госпитализация', 14 => 'Дневник врача', 15 => 'Дневной стационар', 16 => '', 17 => 'Амбулаторная операция', 18 => 'Направление', 19 => '', 20 => 'Диагноз', 21 => 'Вызов скорой помощи', 22 => 'Медосмотры', 27 => 'Направление на процедуры', 28 => 'Назначения ЛС', 29 => 'Список назначений ЛС', 30 => 'Посещение пациента', 31 => '', 32 => 'Назначения ИМН' ]; $arrVisitPlaceKind = [ 0 => 'прием в ОЗ', 1 => 'вызов врача на дом', 2 => 'активное посещение', 3 => 'патронаж' ]; $arrVisitSicknessKind = [ 1 => 'острое', 2 => 'первичное хроническое', 3 => 'хроническое вне обострения', 4 => 'обострение хрониического' ]; $arrVisitEndNewKind = [ 1 => 'лечение закончено', 2 => 'лечение не закончено', 3 => 'смерть', 4 => 'госпитализация' ]; $arrVisitCaseKind = [ 1 => 'первичный', 2 => 'повторный' ]; $arrVisitMarkNewKind = [ 1 => 'по заболеванию', 2 => 'консультация', 3 => 'профосмотр с выявл. заболев.', 4 => 'профосмотр здоровых', 5 => 'диспансеризация', 6 => 'прочее больными', 7 => 'прочее здоровыми' ]; $arrDispansaryCaregoryKind = [ 1 => 'Д', 2 => 'К' ]; $arrRecipeTermUseKind = [ 5 => '5 дней', 15 => '15 дней', 30 => '30 дней', 60 => '2 месяца', 180 => '6 месяцев' ]; function convertDate($date, $pattern = 'd.m.Y') { return is_null($date) ? '' : date_format(safe_date_create($date), $pattern); } function convertNumber($number) { return is_null($number) ? '' : number_format($number, 4, '.', ''); } function getKindValue($arr, $value) { if (is_null($value)) { return ''; } return isset($arr[$value]) ? $arr[$value] : ''; } function getInspectConclusion($values) { $conslusionGroups = isset($values['conclusionGroupNames']) ? safe_explode(',', $values['conclusionGroupNames']) : null; $conslusionNames = isset($values['conclusionNames']) ? safe_explode(',', $values['conclusionNames']) : null; $str = ""; if (!empty($conslusionGroups)) { foreach ($conslusionGroups as $key => $value) { $str .= "{$conslusionNames[$key]} {$value} \n"; } } return $str; } function translateDate($date) { if ($date === '') { return ''; } $arrMonth = [ 'jan' => 'янв', 'feb' => 'февр', 'mar' => 'март', 'apr' => 'апр', 'may' => 'май', 'jun' => 'июнь', 'jul' => 'июль', 'aug' => 'авг', 'sep' => 'сент', 'oct' => 'окт', 'nov' => 'нояб', 'dec' => 'дек' ]; $arrDate = safe_explode(" ", $date); $arrDate[1] = $arrMonth[toLower($arrDate[1])]; return implode(" ", $arrDate); } function createTitleString($value, $typeNames) { $recordType = $value['recordType']; $string = translateDate(convertDate( is_null($value['parentDate']) ? $value['recordDate'] : $value['parentDate'], 'd M Y')); if ($recordType === 8) { $string .= " {$typeNames[$recordType][$value['type']]}"; } else { if (array_key_exists($recordType, $typeNames)) { $string .= " {$typeNames[$recordType]}"; } } return $string; } $ret = []; $text = trim(preg_replace('/\s{2,}/', ' ', $text)); $quotedText = preg_quote($text, '/'); $textStrict = preg_replace("/\s+/", "[\s\S]*", $quotedText); $regStrict = "/$textStrict/imux"; $textAll = preg_replace("/\s+/", "|", $quotedText); $regAll = "/$textAll/imu"; foreach ($array as &$value) { if (!empty($value[self::SRCH_FLD_PROCESSED])) { continue; } switch ($value['recordType']) { case 1: $visitPlaceDisplay = getKindValue($arrVisitPlaceKind, $value['visitPlaceKind']); $visitMarkNewDisplay = getKindValue($arrVisitMarkNewKind, $value['visitMarkNewKind']); $isRegDiagnosis = $value['visitEndNewKind'] !== 2 && $value['visitCaseKind'] === 1; if ($isRegDiagnosis) { $visitCaseDisplay = getKindValue($arrVisitCaseKind, $value['visitCaseKind']); $visitSicknessDisplay = getKindValue($arrVisitSicknessKind, $value['visitSicknessKind']); $visitEndNewDisplay = getKindValue($arrVisitEndNewKind, $value['visitEndNewKind']); $diagnosisReg = "{$visitCaseDisplay} {$visitSicknessDisplay} {$visitEndNewDisplay}"; } else { $diagnosisReg = ""; } $string = <<<"EOT"
{$value['doctorFio']}
{$value['doctorProfession']}
{$visitPlaceDisplay}
{$visitMarkNewDisplay}
{$value['pressure']}
{$value['diagnosisCipher']}
{$value['diagnosisName']}
{$diagnosisReg}
{$value['traumaDisplay']}
{$value['addDiagnosis']}
EOT;
break; case 2: $date = convertDate($value['parentDate']); $string = <<<"EOT"
{$value['pageName']}
{$date}
EOT;
break; case 3: $openDate = convertDate($value['openDate']); $closeDate = convertDate($value['closeDate']); $string = <<<"EOT"
{$value['documentSeries']}
{$value['documentNumber']}
{$value['diagnosisOpen']}
{$value['doctorOpen']}
{$value['doctorOpenProfession']}
{$openDate}
{$value['diagnosisClose']}
{$openDate}
{$closeDate}
{$value['hospitalDay']}
{$value['doctorClose']}
{$value['doctorCloseProfession']}
{$value['markList']}

{$value['busy']}
EOT;
break; case 4: case 5: case 6: $recordDate = convertDate($value['recordDate']); $exTime = convertDate($value['exTime']); $string = <<<"EOT"
{$value['pageName']}
{$recordDate}
{$value['dirDoc']}
{$exTime}
{$value['res']}
EOT;
break; case 7: $opendDate = convertDate($value['openDate']); $closestTurnoutDate = convertDate($value['closestTurnoutDate']); $closeDate = convertDate($value['closeDate']); $dispansaryCaregoryDisplay = getKindValue($arrDispansaryCaregoryKind, $value['category']); $string = <<<"EOT"
{$dispansaryCaregoryDisplay}
{$value['motiveCaptureTextShort']}
{$opendDate}
{$value['diagnosisOpenCipher']}
{$value['diagnosisOpenName']}
{$value['registrationGroupName']}
{$value['riskGroupName']}
{$value['professionName']}
{$value['doctorName']}
{$value['departmentName']}
{$closestTurnoutDate}
{$closeDate}
{$value['motiveDiscardText']}
{$value['diagnosisCloseCipher']}
{$value['diagnosisCloseName']}
EOT;
break; case 8: $startDate = convertDate($value['startDate']); $outDate = convertDate($value['outDate']); $percent = convertNumber($value['privilegePercent']); $recipeTermUseDisplay = getKindValue($arrRecipeTermUseKind, $value['termUse']); $string = <<<"EOT"
{$value['series']}
{$value['number']}
{$startDate}
{$outDate}
{$recipeTermUseDisplay}
{$value['privelegeName']}
{$percent}
{$value['doctorFio']}
{$value['departmentName']}
{$value['displayWealth']}
EOT;
break; case 9: $planDate = convertDate($value['planDate']); $string = <<<"EOT"
{$value['name']}
{$planDate}
EOT;
break; case 10: break; case 11: $startDate = convertDate($value['startDate']); $finishDate = convertDate($value['finishDate']); $string = <<<"EOT"
{$value['inoculateGroups']}
{$value['disclaimerReasons']}
{$value['diagnosisCipher']}
{$value['diagnosis']}
{$startDate}
{$finishDate}
{$value['doctorProfession']}
{$value['doctor']}
EOT;
break; case 12: $string = <<<"EOT"
{$value['number']}
{$value['preventName']}
{$value['conclusion']}
{$value['important']}
EOT;
break; case 13: $recordDate = convertDate($value['recordDate']); $hospitalDate = convertDate($value['hospitalDate']); $outHospitalDate = convertDate($value['outHospitalDate']); $string = <<<"EOT"
{$value['kindName']}
{$value['number']}
{$recordDate}
{$value['doctorFIO']}
{$hospitalDate}
{$value['enterpriseHospital']}
{$value['diagnosisDirectCipher']}
{$value['diagnosisDirectName']}
{$outHospitalDate}
{$value['hospitalResult']}
{$value['diagnosisClinicCipher']}
{$value['diagnosisClinicName']}
EOT;
break; case 14: $date = convertDate($value['changeTime']); $string = <<<"EOT"
{$value['pageName']}
{$date}
EOT;
break; case 15: $startDate = convertDate($value['startDate']); $finishDate = convertDate($value['finishDate']); $string = <<<"EOT"
{$value['diagnosis']}
{$startDate}
{$finishDate}
{$value['departmentName']}
{$value['issueName']}
{$value['doctorProfessionName']}
{$value['doctor']}
{$value['directDoctor']}
{$value['directDoctorProfessionName']}
EOT;
break; case 16: $string = ''; break; case 17: $string = <<<"EOT"
{$value['operationsName']}
{$value['diagnosisName']}
{$value['diagnosisFinalName']}
{$value['doctorsName']}
{$value['surgeonName']}
EOT;
break; case 18: $recordDate = convertDate($value['recordDate']); $couponDate = convertDate($value['couponDate']); $couponVisitTime = convertDate($value['couponDate'], 'H:s'); $string = <<<"EOT"
{$value['medCouponWorkName']}
{$value['sendTypeDisplay']}
{$value['documentNumber']}
{$recordDate}
{$value['doctor']}
{$value['professionName']}
{$value['couponDoctor']}
{$value['medCouponWorkName']}
{$couponDate}
{$couponVisitTime}
{$value['organizationName']}
{$value['sendGoal']}
{$value['send_DocumentName']}
{$value['send_DocumentNumber']}
{$value['send_DocumentDate']}
{$value['fluorConclusion']}
EOT;
break; case 19: $string = <<<"EOT"
{$value['send_DocumentName']}
{$value['send_DocumentNumber']}
{$value['send_DocumentDate']}
{$value['fluorConclusion']}
EOT;
break; case 20: $changeDate = convertDate($value['changeDate']); $string = <<<"EOT"
{$value['display']}
{$value['display']}
{$value['typeName']}
{$value['asRevealedText']}
{$value['helpTypeText']}
{$value['doctor']}
{$value['parentName']}
{$value['newDoctor']}
{$changeDate}
EOT;
break; case 21: $callTime = convertDate($value['callTime'], 'H:i'); $string = <<<"EOT"
{$callTime}
{$value['diagnosisCipher']}
{$value['diagnosisName']}
EOT;
break; case 22: $inspectDate = convertDate($value['inspectDate']); $inspectConclusions = getInspectConclusion($value); $string = <<<"EOT"
{$value['preventTypeName']}
{$inspectDate}
{$value['doctorFIO']}
{$inspectConclusions}
EOT;
break; case 27: $documentDate = convertDate($value['documentDate']); $startDate = convertDate($value['startDate']); $finishDate = convertDate($value['finishDate']); $string = <<<"EOT"
{$value['documentNumber']}
{$documentDate}
{$startDate}
{$finishDate}
{$value['doctor']}
{$value['doctorProfessionName']}
EOT;
break; case 28: $startDate = convertDate($value['startDate']); $outDate = convertDate($value['outDate']); $percent = convertNumber($value['privilegePercent']); $startTime = convertDate($value['startTime']); $finishTime = convertDate($value['finishTime']); $recipeTermUseDisplay = getKindValue($arrRecipeTermUseKind, $value['termUse']); $string = <<<"EOT"
{$value['series']}
{$value['number']}
{$startDate}
{$outDate}
{$startTime}
{$finishTime}
{$recipeTermUseDisplay}
{$value['privelegeName']}
{$percent}
{$value['doctorFio']}
{$value['departmentName']}
{$value['displayWealth']}
{$value['drugName']}
{$value['drugInnName']}
EOT;
break; case 29: $startDate = convertDate($value['startTime']); $finishDate = convertDate($value['finishTime']); $string = <<<"EOT"
{$value['doctorFio']}
{$startDate}
{$finishDate}
{$value['departmentName']}
{$value['doctorProfession']}
{$value['drugName']}
{$value['drugInnName']}
EOT;
break; case 30: $recordDate = convertDate($value['recordDate']); $string = <<<"EOT"
{$value['doctorFio']}
{$value['doctorDepartmentName']}
{$value['userName']}
{$value['viewKind']}
{$recordDate}
EOT;
break; case 31: $date = convertDate($value['parentDate']); $string = <<<"EOT"
{$value['doctorFio']}
{$value['doctorDepartment']}
{$value['doctorProfession']}
{$value['diagnosisCipher']}
{$value['diagnosisName']}
{$value['viewKind']}
{$value['stomVisitKind']}
{$date}
EOT;
break; case 32: $startDate = convertDate($value['parentDate']); $finishDate = convertDate($value['finishDate']); $string = <<<"EOT"
{$value['doctorFio']}
{$value['departmentName']}
{$value['doctorProfession']}
{$value['diagnosisName']}
{$value['nomenclatureName']}
{$value['nomenclatureSupName']}
{$startDate}
{$finishDate}
EOT;
break; default: $string = ""; } $string = createTitleString($value, $arrTypeNames) . " {$string}"; $matchStrict = []; $matchAll = []; $match = preg_match($regStrict, $string, $matchStrict); $match = preg_match_all($regAll, $string, $matchAll, PREG_SET_ORDER, 0) || $match; if ($match) { if ($matchStrict) { $value[self::SRCH_FLD_SORT] = 0; $value[self::SRCH_FLD_FOUND] = true; } else { $pv = safe_explode(' ', $text); $pv_cnt = 0; $arr = []; foreach ($matchAll as $match) { $arr[] = mb_strtolower($match[0]); } $arr = array_unique($arr); foreach ($pv as $el) { foreach ($arr as $match) { toLower($el) === $match && $pv_cnt++; } } if ($pv_cnt === count($pv)) { $value[self::SRCH_FLD_SORT] = 1; $value[self::SRCH_FLD_FOUND] = true; } else { continue; } } array_push($ret, $value); if (is_null($value['parentId'])) { foreach ($array as &$item) { if ($item['parentId'] === $value['recordId'] && empty($item[self::SRCH_FLD_PROCESSED])) { $item[self::SRCH_FLD_PROCESSED] = 1; $item[self::SRCH_FLD_SORT] = $value[self::SRCH_FLD_SORT]; array_push($ret, $item); } } } else { foreach ($array as &$item) { if ($value['parentId'] === $item['recordId'] && empty($item[self::SRCH_FLD_PROCESSED])) { $item[self::SRCH_FLD_PROCESSED] = 1; $item[self::SRCH_FLD_SORT] = $value[self::SRCH_FLD_SORT]; array_push($ret, $item); } } } } } foreach ($ret as &$item) { unset($item[self::SRCH_FLD_PROCESSED]); } return $ret; } public function readStomVisits($params,$v = false) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $sql = <<<"EOT"
select
       -----------------------------------------------------------------------------------------------------------------
       SV.StomVisitCode "recordId",
       SV.VisitDate "recordDate",
       COALESCE(SV.StomVisitCode, '')::text || '-'   "path",
       SV.VisitDate "parentDate",
       31 "recordType",
       30 "docType",
       NULL "parentId",
       null "parentType",
       0 "sortType",
       /* проверка на наличие подчиенных документов */
       (select 1
        from medVisitDocument
        where StomVisitCode = SV.StomVisitCode
        limit 1) "isLeaf",
       -----------------------------------------------------------------------------------------------------------------
       CASE SC.CardType WHEN 1 THEN 'Посещение стоматолога' ELSE 'Посещение стоматолога-ортопеда' END "VisitType",
       SV.VisitDate "visitDate",
       MD.MedDoctorCode "doctorId",
       MPRO.Name "doctorProfession",
       MD.DepartmentCode "doctorDepartmentId",
       FN_cmFIO(MD.ManCode, 1) "doctorFio",
       FN_cmDepartmentName(MD.DepartmentCode) "doctorDepartment",
       0 "visitPlaceKind",
       SV.MedDiagnosisCode "diagnosisId",
       D.Cipher1 "diagnosisCipher",
       D.Name "diagnosisName", /*диагноз*/
       CASE SV.visitKind WHEN 1 THEN 'Первичное' WHEN 2 THEN 'Повторное' ELSE NULL END "viewKind",/*Посещение*/
       LOWER(SVK.name) "stomVisitKind", /*Форма обслуживания*/
       MJVK.name "jobVisitKind",/*Место Осмотр */
       CASE SV.treatmentKind WHEN 1 THEN 'начато' WHEN 2 THEN 'продолжено' WHEN 3 THEN 'закончено' ELSE NULL END "treatmentKind" /*Закончен случай*/
FROM StomCard SC
     JOIN StomVisit SV ON SV.stomcardcode = SC.stomcardcode
     LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = SV.MedDoctorCode
     LEFT JOIN MedProfession MPRO ON MPRO.MedProfessionCode = MD.MedProfessionCode
     LEFT JOIN meddiagnosis D ON D.meddiagnosiscode = SV.MedDiagnosisCode
     LEFT JOIN StomVisitKind SVK ON SVK.stomvisitkindcode = SV.stomvisitkindcode
     LEFT JOIN MedJobVisitKind MJVK ON MJVK.medjobvisitkindcode = SV.MedJobVisitKindCode
WHERE SC.medpatientcode = $patientId
EOT;
$visit = selectSql($db, $sql); $id = createSqlInCondition($visit,"recordId"); $sql = <<<"EOT"
  SELECT
                --------------------------------------------------------------------------------------
                MVD.medVisitDocumentCode    "recordId",
                MVD.createTime              "recordDate",
                -- все документы будут подчиненны гл. записям medVisitPatient
                SV.StomVisitCode::text || '-' || COALESCE(MVD.medVisitDocumentCode, '')::text
                                            "path",
                SV.visitDate                "parentDate",
                30                           "recordType",
                1                           "docType",
                SV.StomVisitCode            "parentId",
                30                          "parentType",
                0                           "sortType",

                --------------------------------------------------------------------------------------
                CASE SC.CardType WHEN 1 THEN 'Стомотолог-терапевт' ELSE 'Стоматолог-ортопед' END "VisitType",
                CASE SV.visitKind WHEN 1 THEN 'первичное' WHEN 2 THEN 'повторное' ELSE NULL END "viewKind",/*Посещение*/
                HT.name                     "pageName",
                U.userCode                  "userId",
                U.nick                      "userName",
                MVD.htmPageCode             "pageId",
                HP.createTime               "createTime",
                MVD.changeTime              "changeTime",
                COALESCE(HP.ready, 0)       "ready",
                MVD.htmTemplateCode         "templateId",
                HT.htmPageCode              "templatePageId",
                SV.medDoctorCode            "doctorId",
                FN_cmFIO(m.manCode)::TEXT   "doctorFio",
                md.departmentCode           "doctorDepartmentId",
                FN_cmDepartmentName(md.departmentCode, 1)::TEXT
                                            "doctorDepartmentName"
            FROM StomVisit SV
            JOIN StomCard SC ON SC.stomcardcode = SV.stomcardcode
            JOIN MedVisitDocument MVD ON MVD.StomVisitCode = SV.StomVisitCode
            left join Users U ON U.userCode = MVD.userCode
            left join htmPage HP ON HP.htmPageCode = MVD.htmPageCode
            left join htmTemplate HT ON HT.htmTemplateCode = MVD.htmTemplateCode
            LEFT JOIN medDoctor md ON MD.medDoctorCode = SV.medDoctorCode
            LEFT JOIN man m ON m.manCode = md.manCode
            where SV.StomVisitCode $id -- гл.записи
                and ((MVD.type = 1 AND COALESCE(HP.isDeleted, 0) = 0) OR
                    (MVD.type = 2 AND ((0 = 1 AND MVD.deleteTime IS NOT NULL)
                                        or (0 = 0 AND MVD.deleteTime IS NULL))))
EOT;
$dinary = selectSql($db, $sql); if($v) { array_add( $dinary, $visit); } return $dinary; } public function readVisits($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $externalDocumentsSourceCode = isEmptyProperty($params, 'externalDocumentsSourceCode'); $sql = <<<"EOT"
select
       -----------------------------------------------------------------------------------------------------------------
       t.MedVisitPatientCode "recordId",
       t.VisitDate "recordDate",
       COALESCE(t.MedVisitPatientCode, '')::text || '-' "path",
       t.VisitDate "parentDate",
       1 "recordType",
       1 "docType",
       null "parentId",
       null "parentType",
       0 "sortType",
       /* проверка на наличие подчиенных документов */
       (select 1
        from medVisitDocument
        where medVisitPatientCode = t.MedVisitPatientCode
        limit 1) "isLeaf",
        Coalesce(mvdoc.MedInspectCode,0) "medInspectCode",
       -----------------------------------------------------------------------------------------------------------------
       t.VisitDate "visitDate",
       MD.MedDoctorCode "doctorId",
       t.logHospitalId "logHospitalId",
       MPRO.Name "doctorProfession",
       t.DepartmentCode "doctorDepartmentId",
       FN_cmFIO(MD.ManCode, 1) "doctorFio",
       FN_cmDepartmentName(t.DepartmentCode) "doctorDepartment",
       COALESCE(t.VisitPlace, 0) "visitPlaceKind",
       t.MedDiagnosisCode "diagnosisId",
       D.Cipher1 "diagnosisCipher",
       D.Name "diagnosisName",
       COALESCE(t.attendantDiagnosis, 0) "attendantDiagnosis",
       t.SicknessKind "visitSicknessKind",
       t.VisitCaseNew "visitCaseNew",
       t.VisitEndNew "visitEndNewKind",
       addDiag."addDiagnosis" "addDiagnosis",
       addDiag."addVisitId" "addVisitId",
       t.VisitMarkNew "visitMarkNewKind",
       CASE
           WHEN COALESCE(t.Pressure, '') <> '' THEN regexp_replace(('0' || asa_substr(t.Pressure, 1, 3)), '[^\d]', '', 'g')::integer || '/' ||
                                                    regexp_replace(('0' || asa_substr(t.Pressure, 5, 3)), '[^\d]', '', 'g')::integer
           ELSE ''
       END "pressure",
       CONCAT_WS(' - ', MT.Code, MT.Name) "traumaDisplay",
       t.visitCase "visitCaseKind",
       COALESCE(ExternalDocs.ExternalDocumentsCount, 0) as "externalDocumentsCount",
COALESCE(CL.Status, 0) "ciszStatus"
from (
-- достаем все посещения для пациента в память для ускорения работы с ними
with __visits as (select
                         MVP.MedVisitPatientCode,
                         MV.VisitDate,
                         MVP.MedDiagnosisCode,
                         COALESCE(MVP.attendantDiagnosis, 0) "attendantDiagnosis",
                         MV.medVisitCode
                  from MedVisitPatient MVP
                       join MedVisit MV on MV.MedVisitCode = MVP.MedVisitCode
   where MVP.MedPatientCode = {$patientId}
     and MVP.MedVisitPatientCodeParent IS NULL
)
select
       MVP.MedVisitPatientCode,
       MV.MedDoctorCode,
       MV.MedProfessionCode,
       MVP.EnterpriseCodeDirected,
       COALESCE(MLH.MedDiagnosisCodeClinic, MVP.MedDiagnosisCode) as MedDiagnosisCode,
       MV.VisitDate,
       CASE
            WHEN COALESCE(MVP.Attributes, 0) & 1 = 1
            THEN 4
            ELSE MVP.visitPlace
       END "visitplace",
       MVP.attendantDiagnosis,
       MVP.SicknessKind,
       MVP.VisitCaseNew,
       CASE
            WHEN COALESCE(MVP.Attributes, 0) & 2 = 2
            THEN 5
            ELSE COALESCE(MVP.visitEndNew, 0)
       END "visitendnew",
       MV.DepartmentCode,
       MVP.visitMarkNew,
       MVP.pressure,
       COALESCE(MLH.MedTraumaCode, MVP.medTraumaCode) as medTraumaCode,
       MLH.MedLogHospitalCode as logHospitalId,
       MVP.VisitCase,
       MV.medVisitCode,
       MVP.serviceForm
from __visits __V
     join MedVisitPatient MVP on MVP.MedVisitPatientCode = __V.MedVisitPatientCode
     join MedVisit MV on MV.MedVisitCode = MVP.MedVisitCode
     LEFT JOIN MedLogHospital MLH ON MLH.MedLogHospitalCode = MVP.MedLogHospitalCode
     -- выбираем первую запись без галки attendantDiagnosis, с мин. кодом, с наличием диагноза
     -- (предпочтительно, или выберет первую по вторичным условиям)
     -- латерал быстрее работает нежели присвоение подзапросу
     join lateral (select V_.MedVisitPatientCode
                   from __visits V_
                   where V_.medVisitCode = MV.medVisitCode
                   order by V_."attendantDiagnosis", V_.MedVisitPatientCode, COALESCE(V_.MedDiagnosisCode, 0) desc
                   limit 1
     ) gVisitId on true
where MVP.MedVisitPatientCode = gVisitId.MedVisitPatientCode
) t
   left join MedDoctor MD on MD.MedDoctorCode = t.MedDoctorCode
      left join lateral (
        SELECT MedInspectCode
        FROM medVisitDocument
        WHERE MedVisitPatientCode = t.MedVisitPatientCode
        ORDER BY MedVisitDocumentCode
        LIMIT 1
    ) mvdoc on true
   left join MedProfession MPRO on MPRO.MedProfessionCode = COALESCE(MD.MedProfessionCode, t.MedProfessionCode)
   left join Enterprise E on E.EnterpriseCode = t.EnterpriseCodeDirected
   left join MedDiagnosis D on D.MedDiagnosisCode = t.MedDiagnosisCode
   left join medTrauma MT on MT.medTraumacode = t.medTraumacode
   -- достаем сопутствующие диагнозы
   left join lateral (SELECT
                            string_agg(SQ."diagCipher" || ' - ' || SQ."diagName" || ';'
                                       || COALESCE(SQ."visitCase", '') || ';'
                                       || COALESCE(SQ."sicknessKind", '') || ';'
                                       || COALESCE(SQ."visitEndNew", ''), '||') "addDiagnosis",

                            string_agg(SQ."id", ', ') "addVisitId"
                      FROM (SELECT
                                MVP_.MedVisitPatientCode    "id",
                                D_.cipher1                  "diagCipher",
                                D_.name                     "diagName",
                                MVP_.visitCase              "visitCase",
                                MVP_.sicknessKind           "sicknessKind",
                                MVP_.visitEndNew            "visitEndNew"
                            FROM MedVisitPatient MVP_
                                JOIN MedVisit MV_ ON MV_.MedVisitCode = MVP_.MedVisitCode
                                JOIN MedDiagnosis D_ ON D_.MedDiagnosisCode = MVP_.MedDiagnosisCode
                            WHERE MV_.medVisitCode = t.medVisitCode
                                AND MVP_.medpatientCode = {$patientId}
                                AND coalesce(MVP_.visitPlace, 0) = coalesce(t.visitPlace, 0)
                                AND coalesce(MVP_.visitMarkNew, 0) = coalesce(t.visitMarkNew, 0)
                                AND coalesce(MVP_.visitCaseNew, 0) = coalesce(t.visitCaseNew, 0)
                                AND coalesce(MVP_.serviceForm, 0) = coalesce(t.serviceForm, 0)
                                AND CASE
                                        WHEN MVP_.MedLogHospitalCode IS NULL
                                            THEN MVP_.medVisitPatientCode <> t.medVisitPatientCode
                                        ELSE 1 = 1
                                    END
                            ORDER BY 1 DESC
                            ) SQ
                     ) addDiag on TRUE
    LEFT JOIN (
        SELECT
            SourceCode,
            count(MedExternalFileCode) as ExternalDocumentsCount
        FROM MedExternalFileT
        WHERE Source = '{$externalDocumentsSourceCode}'
        GROUP BY SourceCode
    ) ExternalDocs on ExternalDocs.SourceCode = t.MedVisitPatientCode
LEFT JOIN LATERAL (
    SELECT Status
    FROM CiszLink
    WHERE Type = 2
      AND (
            (mvdoc.MedInspectCode IS NOT NULL
                AND TableName = 'MedInspect'
                AND PrimaryKey = mvdoc.MedInspectCode)
         OR (mvdoc.MedInspectCode IS NULL
                AND TableName = 'MedVisitPatient'
                AND PrimaryKey = t.MedVisitPatientCode)
      )
    LIMIT 1
) CL ON TRUE
EOT;
return selectSql($db, $sql); } public function readVisitDocuments($params) { $db = $this->db ?: $this->db = connectDb(); $visitIds = isEmptyProperty($params, 'visitIds', 'in (0)'); $patientId = isEmptyProperty($params, 'patientId', 0); $sql = <<<EOT
            select
                --------------------------------------------------------------------------------------
                MVD.medVisitDocumentCode    "recordId",
                MVD.createTime              "recordDate",
                -- все документы будут подчиненны гл. записям medVisitPatient
                MVP_G.medVisitPatientCode::text || '-' || COALESCE(MVD.medVisitDocumentCode, '')::text
                                            "path",
                MV.visitDate                "parentDate",
                2                           "recordType",
                1                           "docType",
                MVP.medVisitPatientCode     "parentId",
                1                           "parentType",
                0                           "sortType",
                --------------------------------------------------------------------------------------
                HT.name                     "pageName",
                U.userCode                  "userId",
                U.nick                      "userName",
                COALESCE(MVD.MedInspectCode,0) "medInspectCode",
                MVD.htmPageCode             "pageId",
                HP.createTime               "createTime",
                MVD.changeTime              "changeTime",
                COALESCE(HP.ready, 0)       "ready",
                MVD.htmTemplateCode         "templateId",
                HT.htmPageCode              "templatePageId",
                MV.medDoctorCode            "doctorId",
                FN_cmFIO(m.manCode)::TEXT   "doctorFio",
                md.departmentCode           "doctorDepartmentId",
                FN_cmDepartmentName(md.departmentCode, 1)::TEXT
                                            "doctorDepartmentName"
            from MedVisit MV
            join MedVisitPatient MVP_G on MVP_G.medVisitCode = MV.medVisitCode
            -- достаем любые документы пациента на текущий medVisit, не важно на каком medVisitPatient они записаны
            join MedVisitPatient MVP on MVP.medVisitCode = MV.medVisitCode
                and MVP.medPatientCode = {$patientId}
            --
            join MedVisitDocument MVD on MVD.medVisitPatientCode = MVP.medVisitPatientCode
            --
            left join Users U on U.userCode = MVD.userCode
            left join htmPage HP on HP.htmPageCode = MVD.htmPageCode
            left join htmTemplate HT on HT.htmTemplateCode = MVD.htmTemplateCode
            LEFT JOIN medDoctor md ON md.medDoctorCode = mv.medDoctorCode
            LEFT JOIN man m ON m.manCode = md.manCode
            where MVP_G.medVisitPatientCode $visitIds -- гл.записи
                and ((MVD.type = 1 and COALESCE(HP.isDeleted, 0) = 0) or
                    (MVD.type = 2 and ((0 = 1 and MVD.deleteTime IS NOT NULL)
                                        or (0 = 0 and MVD.deleteTime IS NULL))))
EOT;
return selectSql($db, $sql); } private function createVisit($record) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($record, 'doctorId', 0); $departmentId = isEmptyProperty($record, 'departmentId', 0); $professionId = isEmptyProperty($record, 'professionId', 0); $visitDate = isEmptyProperty($record, 'visitDate', new DateTime()); $sqlCheck = <<<"EOT"
select medVisitCode "id"
from medVisit
where VisitDate = ?
  and COALESCE(medDoctorCode, 0) = {$doctorId}
  and COALESCE(departmentCode, 0) = {$departmentId}
  and COALESCE(medProfessionCode, 0) = {$professionId}
limit 1
EOT;
$sql = <<<"EOT"
insert into MedVisit
    (VisitDate, MedDoctorCode, DepartmentCode, MedProfessionCode)
values
    (?, ?, ?, ?)
EOT;
$ret = new stdClass(); $ret->id = selectSqlValue($db, $sqlCheck, [dbDate($visitDate)]); if (empty($ret->id)) { try { executeSql($db, $sql, [ dbDate($visitDate), isEmptyProperty($record, 'doctorId'), isEmptyProperty($record, 'departmentId'), isEmptyProperty($record, 'professionId') ]); $ret->id = $ret->visitId = $db->lastInsertId('seq_MedVisit'); } catch (Throwable $e) { throw $e; } } return $ret; } public function deleteVisit($params) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedVisit', 'MedVisitCode', isUndefinedProperty($params, 'visitId', 0)); } public function createVisitPatient($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $checkVisitPatient = isEmptyProperty($params, 'checkVisitPatient'); $visit = $this->createVisit($params); $visitId = $visit->id; $ret = new stdClass(); try { if (empty($visit->visitId) && $checkVisitPatient) { $sqlCheck = <<<"EOT"
select MedVisitPatientCode "id"
from MedVisitPatient
where MedVisitCode = $visitId
  and MedPatientCode = $patientId
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute(); $ret->id = fetchValue($stmtCheck, 'id'); } if (empty($ret->id) || !$checkVisitPatient) { $sql = <<<"EOT"
insert into MedVisitPatient -- а надо ли по умолчанию устанавливать?
    (MedVisitCode, MedPatientCode, VisitMarkNew, VisitCase, VisitCaseNew, ServiceForm, AttendantDiagnosis)
values
    (?, ?, ?, ?, ?, 1, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $visitId, $patientId, isUndefinedProperty($params, 'visitMarkNew', 1), isUndefinedProperty($params, 'visitCase', 1), isEmptyProperty($params, 'visitCaseNew', 1), isEmptyProperty($params, 'attendantDiagnosis') ]); $ret->id = $ret->visitPatientId = $db->lastInsertId('seq_MedVisitPatient'); } $ret->visitId = isEmptyProperty($visit, 'visitId'); return $ret; } catch (Throwable $e) { throw $e; } } public function updateVisitPatient($params) { $db = $this->db ?: $this->db = connectDb(); $recordId = isEmptyProperty($params, 'recordId', 0); $this->checkAccessVisitPatient($recordId, 'edit'); $sql = <<<"EOT"
update medVisitPatient set
       attendantDiagnosis = ?
where medVisitPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ falseTo0(isEmptyProperty($params, 'attendantDiagnosis')), $recordId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteVisitPatient($params) { $db = $this->db ?: $this->db = connectDb(); try { $visitPatientId = isUndefinedProperty($params, 'recordId', 0); $this->checkAccessVisitPatient($visitPatientId, 'delete'); return deleteSql($db, 'MedVisitPatient', 'MedVisitPatientCode', $visitPatientId); } catch (Throwable $e) { if ($e->getCode() === "30001") { $lockPeriod = new map_common_periodLock(); $lockDate = safe_date_create($lockPeriod->getVisitLockDate()); throw new Exception("Нельзя удалить запись в закрытом периоде по " . date_format($lockDate, "d.m.Y") . "!"); } throw $e; } } protected function checkAccessVisitPatient($visitPatientId, $action) { $db = $this->db ?: $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $isUsersVisit = selectSqlValue($db, <<<"EOT"
SELECT
    MV.MedDoctorCode "doctorId"
FROM MedVisitPatient MVP
LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
WHERE MedVisitPatientCode = :visitPatientId
LIMIT 1
EOT,
[ ':visitPatientId' => $visitPatientId ] ) === $doctorId; if (!$isUsersVisit) { switch($action) { case 'edit': $this->checkAccess( self::DENY_UPDATE_VISIT_NOT_OWN_RULE, self::DENY_UPDATE_VISIT_NOT_OWN_TEXT, self::DENY_UPDATE_VISIT_NOT_OWN_CODE, true); break; case 'delete': $this->checkAccess( self::DENY_DELETE_VISIT_NOT_OWN_RULE, self::DENY_DELETE_VISIT_NOT_OWN_TEXT, self::DENY_DELETE_VISIT_NOT_OWN_CODE, true); break; } } } public function createVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedVisitDocument
    (MedVisitPatientCode, Type, UserCode, htmTemplateCode, Note, changeTime, createTime)
values
    (?, ?, ?, ?, 'Новая запись', LOCALTIMESTAMP, LOCALTIMESTAMP)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { if (isEmptyProperty($params, 'isAdd')) { $visitPatient = $this->createVisitPatient($params); $ret->visitId = isEmptyProperty($visitPatient, 'visitId'); $ret->visitPatientId = isEmptyProperty($visitPatient, 'visitPatientId'); $visitPatientId = isEmptyProperty($visitPatient, 'id'); } else { $visitPatientId = isEmptyProperty($params, 'visitPatientId'); } $stmt->execute([ $visitPatientId, isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function checkAccessMedInspect($params) { $db = $this->db ?: $this->db = connectDb(); $pageId = isEmptyProperty($params, 'pageId'); $sql = <<<SQL
            SELECT MVD.MedInspectCode
            FROM MedVisitDocument MVD
            LEFT JOIN MedInspect MI on MI.medinspectcode = MVD.medinspectcode
            LEFT JOIN MedPrevent MP ON MP.MedPreventCode = MI.MedPreventCode
            LEFT JOIN MedPreventType MPT ON MPT.MedPreventTypeCode = MP.MedPreventTypeCode
            WHERE MVD.HtmPageCode = :pageId
                AND MPT.InputForm != 6  -- Исключаем из проверки водительскую медкомиссию
            SQL;
$ret = (int)selectSqlValue($db, $sql, [ ':pageId' => $pageId ]); return $ret; } public function deleteVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isUndefinedProperty($params, 'documentId', 0); $this->checkAccessVisitDocument($documentId, 'delete'); return deleteSql($db, 'MedVisitDocument', 'MedVisitDocumentCode', $documentId); } protected function checkAccessVisitDocument($documentId, $action) { $db = $this->db ?: $this->db = connectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $userId = getSessionValue('mapUserCode'); $accessData = selectSql($db, <<<"EOT"
SELECT
    MV.MedDoctorCode "doctorId",
    MVD.userCode "userId"
FROM MedVisitDocument MVD
LEFT JOIN MedVisitPatient MVP ON MVP.MedVisitPatientCode = MVD.MedVisitPatientCode
LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
WHERE MedVisitDocumentCode = :documentId
LIMIT 1
EOT,
[ ':documentId' => $documentId ] ); if (!empty($accessData) && $accessData[0]['doctorId'] !== $doctorId && $accessData[0]['userId'] !== $userId) { switch($action) { case 'edit': $this->checkAccess( self::DENY_UPDATE_HTML_NOT_OWN_RULE, self::DENY_UPDATE_HTML_NOT_OWN_TEXT, self::DENY_UPDATE_HTML_NOT_OWN_CODE, true); break; case 'delete': $this->checkAccess( self::DENY_DELETE_HTML_NOT_OWN_RULE, self::DENY_DELETE_HTML_NOT_OWN_TEXT, self::DENY_DELETE_HTML_NOT_OWN_CODE, true); break; } } } protected function checkAccess($rule, $errorText, $errorCode, $isInverse = false) { if (($isInverse && testUserAccess($rule)) || (!$isInverse && !testUserAccess($rule))) { throw new Exception($errorText, $errorCode); } } public function updateVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId', 0); $sql = <<<SQL
UPDATE MedVisitDocument SET
    HtmPageCode = ?,
    ChangeTime = LOCALTIMESTAMP
WHERE MedVisitDocumentCode = $documentId
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ isEmptyProperty($params, 'pageId') ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $this->updateCiszStatus($params); return true; } public function deleteDocument($params) { $params->visitDocumentId = isUndefinedProperty($params, 'documentId', 0); $visitPatientId = isUndefinedProperty($params, 'visitPatientId', 0); if (!$visitPatientId){ return $this->deleteVisitDocument($params); } else { if ($this->checkVisitDocument($params)) { return $this->deleteVisitDocument($params); } else { $params->recordId = isUndefinedProperty($params, 'visitPatientId', 0); return $this->deleteVisitPatient($params); } } } public function checkVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select 1 "isExists"
from MedVisitDocument
where MedVisitPatientCode = ?
  and MedVisitDocumentCode <> ?
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'visitPatientId', 0), isUndefinedProperty($params, 'visitDocumentId', 0) ]); return fetchValue($stmt, 'isExists') ? true : false; } private function readFlurs($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
       MF.MedFluorCode  "recordId",
       MF.OnDate "recordDate",
       MF.OnDate "parentDate",
       COALESCE(MF.MedFluorCode, '')::text || '-' "path",
       12 "recordType",
       null "parentId",
       null "parentType",
       10 "sortType",
       MF.Number "number",
       MPT.Name "preventName",
       MF.MedPreventTypeCode "preventCode",
       MF.Conclusion "conclusion",
       MF.Important "important"
       FROM MedFluor MF
       LEFT JOIN  MedPreventType MPT on MPT.MedPreventTypeCode = MF.MedPreventTypeCode
WHERE MF.MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'patientId', 0) ]); return $stmt->fetchAll(); } private function readLogHospital($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
       MLH.MedLogHospitalCode  "recordId",
       MLH.DirectDate "recordDate",
       MLH.DirectDate "parentDate",
       COALESCE(MLH.MedLogHospitalCode, '')::text || '-' "path",
       13 "recordType",
       null "parentId",
       null "parentType",
       11 "sortType",
       MLH.NumberOrder "number",
       MLH.MedDoctorCode "medDoctorCode",
       FN_cmFIO(MD.ManCode,1) "doctorFIO",
       MLH.HospitalKind "hospitalKind",
       (CASE MLH.HospitalKind
            WHEN 1 THEN 'плановая'
            WHEN 2 THEN 'экстренная'
            ELSE 'не указан' END) "kindName",
       MLH.HospitalDate "hospitalDate",
       MLH.OutDate "outHospitalDate",
       MLH.EpicrisisDate "epicrisisDate",
       MLH.EnterpriseCodeHospital "enterpriseCodeHospital",
       E.Name "enterpriseHospital",
       MLH.MedDiagnosisCodeClinic "medDiagnosisCodeClinic",
       MLH.MedDiagnosisCodeDirect "medDiagnosisCodeDirect",
       MD2.Name "diagnosisClinicName",
       MD2.Cipher1 "diagnosisClinicCipher",
       MD3.Name "diagnosisDirectName",
       MD3.Cipher1 "diagnosisDirectCipher",
       MT.MedTraumaCode "traumaId",
       MT.Code "traumaCode",
       MT.Name "traumaDisplay",
       MLH.UniDictCodeResult "uniDictCodeResult",
       UD.Value "hospitalResult"
       FROM MedLogHospital MLH
       LEFT JOIN  MedDoctor MD ON MD.MedDoctorCode = MLH.MedDoctorCode
       LEFT JOIN Enterprise E ON E.EnterpriseCode = MLH.EnterpriseCodeHospital
       LEFT JOIN MedDiagnosis MD2 ON MD2.MedDiagnosisCode = MLH.MedDiagnosisCodeClinic
       LEFT JOIN MedDiagnosis MD3 ON MD3.MedDiagnosisCode = MLH.MedDiagnosisCodeDirect
       LEFT JOIN MedTrauma MT ON MT.MedTraumaCode = MLH.MedTraumaCode
       LEFT JOIN UniDict UD ON (UD.UniDictCode=MLH.UnidictCodeResult AND UD.DictNumber=38)
WHERE MLH.MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'patientId', 0) ]); return $stmt->fetchAll(); } private function readHospital($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       MH.MedHospitalCode "recordId",
       MH.StartDate "recordDate",
       MH.StartDate "parentDate",
       COALESCE(MH.MedHospitalCode, '')::text || '-' "path",
       3 "recordType",
       null "parentId",
       null "parentType",
       1 "sortType",
       -----------------------------------------------------------------------------------------------------------------
       MHN.DocumentSeries "documentSeries",
       MHN.DocumentNumber "documentNumber",
       FN_cmFIO(MH.ManCodeReceived) "fioReceived",
       MH.PatientEmployment "busy",
       --MH.MedDoctorOpenCode "doctorOpenId",
       --MH.MedDoctorCloseCode "doctorCloseId",
       --MH.MedDiagnosisPrimaryCode "diagnosisOpenId",
       --MH.MedDiagnosisFinalCode "diagnosisCloseId",
       MH.StartDate "openDate",
       MH.FinishDate "closeDate",
       FN_cmGetDayString(MH.HospitalDay) "hospitalDay",
       MH.Checked "checked",
      (select string_agg((select Value
                          from UniDict
                          where UniDictCode = UniDictCodeMark)::text, ',')
       from MedHospitalMark
       where MedhospitalCode = MH.MedHospitalCode) "markList",
      (select MP_.Name
       from MedDoctor MD_
            left join MedProfession MP_ on MP_.MedProfessionCode = MD_.MedProfessionCode
       where MD_.MedDoctorCode = MH.MedDoctorOpenCode ) "doctorOpenProfession",
      (select MP_.Name
       from MedDoctor MD_
            left join MedProfession MP_ on MP_.MedProfessionCode = MD_.MedProfessionCode
       where MD_.MedDoctorCode = MH.MedDoctorCloseCode) "doctorCloseProfession",
      FN_cmFIO((select ManCode
                from MedDoctor
                where MedDoctorCode = MH.MedDoctorOpenCode )) "doctorOpen",
      FN_cmFIO((select ManCode
                from MedDoctor
                where MedDoctorCode = MH.MedDoctorCloseCode)) "doctorClose",
      (select Cipher1  || ' - ' || Name
       from MedDiagnosis
       where MedDiagnosisCode = MH.MedDiagnosisFinalCode) "diagnosisClose",
      (select Cipher1 || ' - ' || Name
       from MedDiagnosis
       where MedDiagnosisCode = MH.MedDiagnosisPrimaryCode) "diagnosisOpen",
       CL.Status "ciszStatus"
from MedHospital MH
     LEFT JOIN CiszLink CL ON CL.TableName = 'temporaryDisability' AND CL.PrimaryKey = MH.MedHospitalCode AND CL.Type = 1
     left join MedHospitalNext MHN on MH.MedHospitalCode = MHN.MedHospitalCode
                                  and MHN.Position = 0
where MH.MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'patientId', 0) ]); return $stmt->fetchAll(); } private function readStudy($params) { $db = $this->db ?: $this->db = connectDb(); $condTypes = createSqlInCondition(isUndefinedProperty($params, 'type', 0)); $study = new med_lab_studies(); $solisMark = $study->getSolisMarkSQLFields(); $sql = <<<"EOT"
select
       MLS.MedLogStudyCode "recordId",
       exists(select MLSS.StudyInstanceUID "id" from MedLogStudySOP MLSS where MLSS.MedLogStudyCode = MLS.MedLogStudyCode) "hasDocuments",
       to_char(MLS.DirTime, 'YYYY-MM-DD') "parentDate",
       MLS.DirTime "recordDate",
       COALESCE(MLS.MedLogStudyCode, '')::text || '-' "path",
       CASE MS.TYPE
            WHEN 1 THEN 4
            WHEN 2 THEN 5
            WHEN 3 THEN 6
       END "recordType",
       MS.Type + 100 "docType",
       null "parentId",
       null "parentType",
       3 "sortType",
       -----------------------------------------------------------------------------------------------------------------
       MS.Name "pageName",
       MLS.DirNumber "dirNumber",
       MLS.htmPageCode "pageId",
       MLS.DirTime "dirTime",
       FN_cmFIO(MD1.ManCode) "dirDoc",
       --MPG.Name "patientSector",
       HPT.IsTemplate "isTemplate",
       --MLS.PatientFIO "patientFio",
       --MLS.PatientDateOfBirth "birthDate",
       --MLS.PatientAddress "address",
       MLS.ExecTime "exTime",
       CASE
            WHEN MLS.MedDoctorCodeExec IS NOT NULL THEN FN_cmFIO(MD2.ManCode)
            ELSE FN_cmFIO(BM.ManCode)
       END "exDoc",
       --22589 FN_htmMask2Str(MLS.htmPageCode, MLS.Result) "res",
       MLS.Result "res",
       MS.htmPageCode "templatePageId",
       MLS.State "state",
       MLS.Important "importantAttribute",
       MLS.PayAttention "payAttention",
       MS.Type "type",
       {$solisMark}, /*WS-236*/
       (SELECT 1 FROM MedLogStudySop MLSS WHERE MLSS.MedLogStudyCode = MLS.MedLogStudyCode ORDER BY StudyTime DESC LIMIT 1) "studySopExists"
from MedLogStudy MLS
     left join MedPatient MP on MP.MedPatientCode = MLS.MedPatientCode
     left join Department D1 on D1.DepartmentCode = MLS.DepartmentCode
     left join MedDoctor MD1 on MD1.MedDoctorCode = MLS.MedDoctorCode
     left join ManPassport MPASS on MPASS.ManCode = MP.ManCode
     left join Department D2 on D2.DepartmentCode = MLS.DepartmentCodeExec
     left join MedDoctor MD2 on MD2.MedDoctorCode = MLS.MedDoctorCodeExec
     left join BuhMol BM on BM.BuhMolCode = MLS.BuhMolCodeExec
     left join MedPatientGroup MPG on mpg.MedPatientGroupCode = MP.MedPatientGroupCode
     left join htmPageT HPT on HPT.htmPageCode = MLS.htmPageCode
     left join medStudy MS on MS.MedStudyCode = MLS.MedStudyCode
where MLS.MedPatientCode = ?
  and MS.Type $condTypes
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'patientId', 0) ]); return $stmt->fetchAll(); } public function createStudy($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId'); $state = isEmptyProperty($params, 'state', 1); $type = isUndefinedProperty($params, 'type'); $studyId = isEmptyProperty($params, 'studyId'); $patientId = isEmptyProperty($params, 'patientId'); $dirTime = isEmptyProperty($params, 'dirTime'); $execTime = isEmptyProperty($params, 'execTime'); $dirNumber = null; if ($patientId && $state !== 2) { $sqlPatient = <<<"EOT"
select
       MP.NumberHistorySickness "card",
       Fn_cmFIO(MP.ManCode) "fio",
       MPASS.DateOfBirth "dateB",
       FN_cmCalcAddress(MP.ManCode) "address",
       COALESCE(MPASS.Sex, 0) "sex"
from MedPatient MP
     left join ManPassport MPASS ON MPASS.ManCode = MP.ManCode
where MedPatientCode = $patientId
limit 1
EOT;
$stmtPatient = $db->prepare($sqlPatient); $stmtPatient->execute(); $patientInfo = $stmtPatient->fetch(); $params->patientFio = $patientInfo['fio']; $params->numberHistory = $patientInfo['card']; $params->patientDateOfBirth = $patientInfo['dateB']; $params->patientAddress = $patientInfo['address']; $params->patientSex = $patientInfo['sex']; } if (!$dirTime) { $sqlCheck = <<<"EOT"
select
      asa_trim(INCSTR(COALESCE((select REPEAT(' ', 20 - LENGTH(asa_trim(DirNumber))) || asa_trim(DirNumber)
                                from MedLogStudy
                                where medStudyCode = ?
                                  and EXTRACT(year FROM DirTime) = EXTRACT(year FROM CURRENT_DATE)
                                order by 1 desc limit 1), '0')))::text "dirNumber"
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute([ (int) $studyId ]); $dirNumber = fetchValue($stmtCheck, 'dirNumber'); } $sql = <<<"EOT"
insert into MedLogStudy
    (Type, DirTime, State, MedStudyCode, MedDoctorCodeExec, MedDoctorCode, DirNumber, medPatientCode, PatientFIO,
     NumberHistorySickness, PatientDateOfBirth, PatientAddress, patientSex, ExecTime, Barcode)
values
    (?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $type, is_int($state) ? $state : 1, $studyId, ($type === 1 || $type === 2) ? $doctorId : null, ($type !== 1 && $type === 2) ? $doctorId : null, $dirNumber, $patientId, isUndefinedProperty($params, 'patientFio'), isUndefinedProperty($params, 'numberHistory'), isEmptyProperty($params, 'patientDateOfBirth') ? dbDate(isEmptyProperty($params, 'patientDateOfBirth')) : null, isUndefinedProperty($params, 'patientAddress'), isUndefinedProperty($params, 'patientSex'), $execTime ? dbDate($execTime) : null, isUndefinedProperty($params, 'barcode') ]); $ret = new stdClass(); $ret->id = $db->lastInsertId('seq_MedLogStudy'); if ($ret->id && $dirTime) { selectSql($db, 'UPDATE MedLogStudy SET DirTime = ? WHERE MedLogStudyCode = ?', [dbDateTime($dirTime), $ret->id]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createCardDocuments($record = null) { $db = $this->db ?: $this->db = connectDb(); $fileName = str_replace(',', '_', $_FILES['document']['name']); $db->beginTransaction(); try { $source = isEmptyProperty($record, 'source', 110); $sourceId = isEmptyProperty($record, 'sourceId'); $sql = <<<SQL
                INSERT INTO MedExternalFileT (
                    Data, Source, SourceCode, FileName
                )
                VALUES (
                    :fileData, :source, :sourceId, :fileName
                )
                SQL;
$stmt = $db->prepare($sql); $fileData = file_get_contents($_FILES['document']['tmp_name']); unlink($_FILES['document']['tmp_name']); $stmt->bindParam(':fileData', $fileData, PDO::PARAM_LOB); $stmt->bindParam(':source', $source, PDO::PARAM_INT); $stmt->bindParam(':sourceId', $sourceId, PDO::PARAM_INT); $stmt->bindParam(':fileName', $fileName, PDO::PARAM_STR); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $rec = new stdClass; $rec->success = true; echo json_encode($rec); } public function updateStudy($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isUndefinedProperty($params, 'documentId', 0); $pageId = isEmptyProperty($params, 'pageId'); $sql = <<<"EOT"
update MedLogStudy set
       htmPageCode = ?,
       payAttention = case when ? = 1 then ? else payAttention end
where MedLogStudyCode = {$documentId}
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $pageId, propertyExistsSQL($params, 'payAttention'), isEmptyProperty($params, 'payAttention') ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (isEmptyProperty($params, 'isNew')) { $study = new med_lab_studies(); $study->sendToSolis($documentId); } return true; } public function deleteStudy($params) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLogStudy', 'MedLogStudyCode', isUndefinedProperty($params, 'documentId', 0)); } public function readCardsInformation($params) { $patientCode = isUndefinedProperty($params, 'patientCode'); if ($patientCode) { $db = $this->db ?: $this->db = connectDb(); $sqlAnem = <<<"EOT"
select * from MedAnamnesis ma where MedPatientCode = ?
EOT;
$stmtAnem = $db->prepare($sqlAnem); $stmtAnem->execute([$patientCode]); $anamnesis = $stmtAnem->fetch(); $sqlPhone = <<<"EOT"
select ManPhoneCode "phoneId", PhoneNumber "phoneNumber"
from ManPhone p
    left join MedPatient mp on mp.ManCode = p.ManCode
where p.PhoneType = 2 and mp.MedPatientCode = ?
EOT;
$stmtPhone = $db->prepare($sqlPhone); $stmtPhone->execute([$patientCode]); $phone = $stmtPhone->fetch(); $sqlPatientBusy = <<<"EOT"
select
    case when Ent.Name is not null then
        case when mb.ClassGroup is not null then
            case when udp.Value is not null then
                cast(Ent.Name+'-'+mb.ClassGroup+'-'+udp.Value as varchar(255))
            else cast(Ent.Name+'-'+mb.ClassGroup as varchar(255)) end
       else
            case when udp.Value is not null then
                cast(Ent.Name+'-'+udp.Value AS varchar(255))
            else cast(Ent.Name as varchar(255)) end
       end
    else '' end
    as "profName"
from MedPatientBusy as mb
    left join Enterprise as Ent on mb.EnterpriseCode = Ent.EnterpriseCode
    left join UniDict as udp on udp.uniDictCode = mb.UniDictCodeProfession
where now() between mb.StartDate and mb.FinishDate and mb.MedPatientCode = ? limit 1
EOT;
$stmtBusy = $db->prepare($sqlPatientBusy); $stmtBusy->execute([$patientCode]); $busy = $stmtBusy->fetch(); $sqlOperations = <<<"EOT"
select mlho.OperationDate "operationDate", mo.Name "name"
from MedLogHospitalOper mlho
    left join MedLogHospital mlh on mlh.MedLogHospitalCode = mlho.MedLogHospitalCode
    left join MedOperation mo on mlho.MedOperationCode = mo.MedOperationCode
where mlh.MedPatientCode = ?
union
select mao.OperationDate "operationDate", mo.Name "name"
from MedAmbulOperation mao
    left join MedOperation mo on mao.MedOperationCode = mo.MedOperationCode
where mao.MedPatientCode = ?
order by 1 desc
EOT;
$stmtOperations = $db->prepare($sqlOperations); $stmtOperations->execute([$patientCode, $patientCode]); $operations = $stmtOperations->fetchAll(); return array('id' => $anamnesis && $anamnesis['medanamnesiscode'] ? $anamnesis['medanamnesiscode'] : null, 'maritalStatus' => $anamnesis && $anamnesis['maritalstatus'] ? $anamnesis['maritalstatus'] : null, 'termsLiving' => $anamnesis && $anamnesis['termsliving'] ? $anamnesis['termsliving'] : null, 'relatives' => $anamnesis && $anamnesis['relatives'] ? $anamnesis['relatives'] : '', 'attitudeToWork' => $anamnesis && $anamnesis['attitudetowork'] ? $anamnesis['attitudetowork'] : null, 'paymentType' => $anamnesis && $anamnesis['paymenttype'] ? $anamnesis['paymenttype'] : null, 'insuranceSerial' => $anamnesis && $anamnesis['insuranceserial'] ? $anamnesis['insuranceserial'] : '', 'insuranceNumber' => $anamnesis && $anamnesis['insurancenumber'] ? $anamnesis['insurancenumber'] : '', 'allergicHistory' => $anamnesis && $anamnesis['allergichistory'] ? $anamnesis['allergichistory'] : '', 'drugInTolerance' => $anamnesis && $anamnesis['drugintolerance'] ? $anamnesis['drugintolerance'] : '', 'reactionToVaccination' => $anamnesis && $anamnesis['reactiontovaccination'] ? $anamnesis['reactiontovaccination'] : '', 'bloodType' => $anamnesis && $anamnesis['bloodtype'] ? $anamnesis['bloodtype'] : null, 'rhFactor' => $anamnesis && $anamnesis['rhfactor'] ? $anamnesis['rhfactor'] : null, 'bloodTransfusion' => $anamnesis && $anamnesis['bloodtransfusion'] ? $anamnesis['bloodtransfusion'] : '', 'otherNote' => $anamnesis && $anamnesis['othernote'] ? $anamnesis['othernote'] : '', 'phoneId' => $phone ? $phone['phoneId'] : null, 'phoneNumber' => $phone ? $phone['phoneNumber'] : '', 'profName' => $busy ? $busy['profName'] : '', 'operations' => $operations ? $operations : null, 'patientCode' => $patientCode); } else { throw new Exception("Пациент не определен"); } } public function updateCardsInformation($params) { $db = $this->db ?: $this->db = connectDb(); $patientChange = array(); $patientChange['attitudeToWork'] = isEmptyProperty($params, 'attitudeToWork'); $patientChange['maritalStatus'] = isEmptyProperty($params, 'maritalStatus'); foreach ($patientChange as $key => $row) { $type = null; if ($key === 'attitudeToWork' && !is_null($row)) { $type = 3; } if ($key === 'maritalStatus' && !is_null($row)) { $type = 2; } if (!is_null($type)) { $sqlAnem = <<<"EOT"
select
    ma.attitudeToWork "attitudeToWork",
    ma.maritalStatus "maritalStatus"
    from MedAnamnesis ma where MedPatientCode = ?
EOT;
$stmtAnem = $db->prepare($sqlAnem); $stmtAnem->execute([$params->patientCode]); if ($type === 2) { $value = fetchValue($stmtAnem, 'maritalStatus'); switch ($value) { case 1: $value = '1-женат/замужем'; break; case 2: $value = '2-холост/не замужем'; break; case 3: $value = '3-разведен(а)'; break; case 4: $value = '4-вдова/вдовец'; break; } } if ($type === 3) { $value = fetchValue($stmtAnem, 'attitudeToWork'); switch ($value) { case 1: $value = '1-работающий'; break; case 2: $value = '2-учащийся'; break; case 3: $value = '3-студент'; break; case 4: $value = '4-пенсионер'; break; case 5: $value = '5-домохозяйка'; break; case 6: $value = '6-безработный'; break; case 7: $value = '7-иждивенец'; break; } } if ($value) { $sql = <<<"EOT"
insert into MedPatientChange (ChangeDate, MedPatientCode, Type, Value)
values (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ date('d.m.Y'), $params->patientCode, $type, $value, ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } } } $db->beginTransaction(); if (isEmptyProperty($params, 'id', null)) { $sqlUpdAnemn = <<<"EOT"
update MedAnamnesis set
       MaritalStatus = case when ? = 1 then ? else MaritalStatus end,
       TermsLiving = case when ? = 1 then ? else TermsLiving end,
       Relatives = case when ? = 1 then ? else Relatives end,
       AttitudeToWork = case when ? = 1 then ? else AttitudeToWork end,
       PaymentType = case when ? = 1 then ? else PaymentType end,
       InsuranceSerial = case when ? = 1 then ? else InsuranceSerial end,
       InsuranceNumber = case when ? = 1 then ? else InsuranceNumber end,
       AllergicHistory = case when ? = 1 then ? else AllergicHistory end,
       DrugInTolerance = case when ? = 1 then ? else DrugInTolerance end,
       ReactionToVaccination = case when ? = 1 then ? else ReactionToVaccination end,
       BloodType = case when ? = 1 then ? else BloodType end,
       RhFactor = case when ? = 1 then ? else RhFactor end,
       BloodTransfusion = case when ? = 1 then ? else BloodTransfusion end,
       OtherNote = case when ? = 1 then ? else OtherNote end
where MedAnamnesisCode = ?
EOT;
$stmtUpdAnemn = $db->prepare($sqlUpdAnemn); $stmtUpdAnemn->execute([ propertyExistsSQL($params, 'maritalStatus'), isUndefinedProperty($params, 'maritalStatus'), propertyExistsSQL($params, 'termsLiving'), isUndefinedProperty($params, 'termsLiving'), propertyExistsSQL($params, 'relatives'), isUndefinedProperty($params, 'relatives'), propertyExistsSQL($params, 'attitudeToWork'), isUndefinedProperty($params, 'attitudeToWork'), propertyExistsSQL($params, 'paymentType'), isUndefinedProperty($params, 'paymentType'), propertyExistsSQL($params, 'insuranceSerial'), isUndefinedProperty($params, 'insuranceSerial'), propertyExistsSQL($params, 'insuranceNumber'), isUndefinedProperty($params, 'insuranceNumber'), propertyExistsSQL($params, 'allergicHistory'), isUndefinedProperty($params, 'allergicHistory'), propertyExistsSQL($params, 'drugInTolerance'), isUndefinedProperty($params, 'drugInTolerance'), propertyExistsSQL($params, 'reactionToVaccination'), isUndefinedProperty($params, 'reactionToVaccination'), propertyExistsSQL($params, 'bloodType'), isUndefinedProperty($params, 'bloodType'), propertyExistsSQL($params, 'rhFactor'), isUndefinedProperty($params, 'rhFactor'), propertyExistsSQL($params, 'bloodTransfusion'), isUndefinedProperty($params, 'bloodTransfusion'), propertyExistsSQL($params, 'otherNote'), isUndefinedProperty($params, 'otherNote'), $params->id ]); } else { $sqlInsAnemn = <<<"EOT"
insert into MedAnamnesis (MaritalStatus, TermsLiving, Relatives, AttitudeToWork, PaymentType, InsuranceSerial, InsuranceNumber, AllergicHistory, DrugInTolerance, ReactionToVaccination, BloodType, RhFactor, BloodTransfusion, OtherNote, MedPatientCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtInsAnemn = $db->prepare($sqlInsAnemn); $stmtInsAnemn->execute([ isUndefinedProperty($params, 'maritalStatus'), isUndefinedProperty($params, 'termsLiving'), isUndefinedProperty($params, 'relatives'), isUndefinedProperty($params, 'attitudeToWork'), isUndefinedProperty($params, 'paymentType'), isUndefinedProperty($params, 'insuranceSerial'), isUndefinedProperty($params, 'insuranceNumber'), isUndefinedProperty($params, 'allergicHistory'), isUndefinedProperty($params, 'drugInTolerance'), isUndefinedProperty($params, 'reactionToVaccination'), isUndefinedProperty($params, 'bloodType'), isUndefinedProperty($params, 'rhFactor'), isUndefinedProperty($params, 'bloodTransfusion'), isUndefinedProperty($params, 'otherNote'), $params->patientCode ]); } if (propertyExistsSQL($params, 'phoneNumber')) { if (isEmptyProperty($params, 'phoneId', null)) { $sqlUpdPhone = <<<"EOT"
update ManPhone set PhoneNumber = ? where ManPhoneCode = ?
EOT;
$stmtUpdPhone = $db->prepare($sqlUpdPhone); $stmtUpdPhone->execute([isUndefinedProperty($params, 'phoneNumber'), $params->phoneId]); } else { $sqlInsPhone = <<<"EOT"
insert into ManPhone (PhoneNumber, PhoneType, ManCode) values (?, ?, (select ManCode from MedPatient where MedPatientCode = ?))
EOT;
$stmtInsPhone = $db->prepare($sqlInsPhone); $stmtInsPhone->execute([isUndefinedProperty($params, 'phoneNumber'), 2, $params->patientCode]); } } try { $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPatientStatus($params) { $db = $this->db ?: $this->db = connectDb(); $type = isUndefinedProperty($params, 'type'); $id = isUndefinedProperty($params, 'id'); $sql = <<<"EOT"
select
  MPC.MedPatientChangeCode "id",
  MPC.Value "value",
  MPC.Type "type",
  MPC.ChangeDate "changeDate"
from MedPatientChange MPC
where MPC.Type = $type and MPC.MedPatientCode = $id
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCardDocument($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE
    MedVisitPatient
SET
    visitCase = case when ? = 1 then ? else visitCase end,
    visitCaseNew = case when ? = 1 then ? else visitCaseNew end,
    sicknessKind = case when ? = 1 then ? else sicknessKind end,
    visitEnd = case when ? = 1 then ? else visitEnd end,
    visitEndNew = case when ? = 1 then ? else visitEndNew end
WHERE
    medVisitPatientCode = ?
RETURNING visitCase, visitCaseNew, sicknessKind, visitEnd, visitEndNew
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 1, null, 1, null, 1, null, 1, null, 1, null, $row->visitPatientId ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCardDocumentEx($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    INSERT INTO MedVisitPatientEx (medVisitPatientCode, helpType, asRevealed, finishYear, changeDate, medDoctorCodeChange)
    VALUES (?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'visitPatientId'), isUndefinedProperty($row, 'helpType'), isUndefinedProperty($row, 'asRevealed'), isUndefinedProperty($row, 'finishYear'), isUndefinedProperty($row, 'changeDate'), isUndefinedProperty($row, 'newDoctorId') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCardDocument($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    INSERT INTO MedVisitPatient (medVisitCode, medPatientCode, medDiagnosisCode, visitPlace, visitFirst, visitCase, visitCaseNew, visitEnd, visitEndNew, sicknessKind, visitMark, visitMarkNew, serviceForm)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'visitId'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'visitPlace'), isUndefinedProperty($row, 'visitFirst'), isUndefinedProperty($row, 'visitCase'), isUndefinedProperty($row, 'visitCaseNew'), isUndefinedProperty($row, 'visitEnd'), isUndefinedProperty($row, 'visitEndNew'), isUndefinedProperty($row, 'sicknessId'), isUndefinedProperty($row, 'visitMark'), isUndefinedProperty($row, 'visitMarkNew'), isUndefinedProperty($row, 'serviceForm') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedVisitPatient'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function pasteDocument($params) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<SQL
            INSERT INTO MedVisitDocument (
                Type, Note, ChangeTime,
                CreateTime, MedVisitPatientCode, UserCode,
                HtmTemplateCode, MedDayHospitalCode
            )
            SELECT
                1, 'Копия', LOCALTIMESTAMP,
                LOCALTIMESTAMP, ?, ?,
                HtmTemplateCode, MedDayHospitalCode
            FROM MedVisitDocument
            WHERE MedVisitDocumentCode = ?
            SQL;
$stmt = $db->prepare($sql); if (is_object($params)) { $params = [ $params ]; } $db->beginTransaction(); try { foreach ($params as $row) { $stmt->execute([ isUndefinedProperty($row, 'medVisitPatientId'), isUndefinedProperty($row, 'userCode'), isUndefinedProperty($row, 'medVisitDocumentCode') ]); $newMedVisitDocumentCode = $db->lastInsertId('seq_MedVisitDocument'); $sqlHpc = <<<SQL
                    SELECT HtmPageCode
                    FROM MedVisitDocument
                    WHERE MedVisitDocumentCode = ?
                    LIMIT 1
                    SQL;
$oldhtmPageCode = selectSqlValue($db, $sqlHpc, [ isUndefinedProperty($row, 'medVisitDocumentCode') ]); if ($oldhtmPageCode) { $sqlName = <<<SQL
                        SELECT Name
                        FROM HtmPage
                        WHERE HtmPageCode = ?
                        LIMIT 1
                        SQL;
$oldhtmPageName = selectSqlValue($db, $sqlName, [ $oldhtmPageCode ]); $newhtmPageCode = selectSqlValue( $db, 'SELECT FN_htmPageCopy(?, ?, ?)', [ $oldhtmPageCode, $newMedVisitDocumentCode, $oldhtmPageName ] ); $sql = <<<SQL
                        UPDATE MedVisitDocument
                        SET HtmPageCode = $newhtmPageCode
                        WHERE MedVisitDocumentCode = $newMedVisitDocumentCode
                        SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $sqlVDate = <<<SQL
                        SELECT VisitDate
                        FROM MedVisit MV
                        JOIN MedVisitPatient MVP ON MVP.medVisitPatientCode = ?
                            AND MVP.medVisitCode = MV.medVisitCode
                        LIMIT 1
                        SQL;
$visitDate = selectSqlValue($db, $sqlVDate, [ isUndefinedProperty($row, 'medVisitPatientId') ]); selectSqlValue($db, "SELECT FN_htmPageUpdateUnit($newhtmPageCode, 'dateVisit', ?)", [ $visitDate ]); selectSqlValue($db, "SELECT FN_htmPageUpdateUnit($newhtmPageCode, 'visitDate', ?)", [ $visitDate ]); $settings = selectSqlValue($db, 'SELECT Data::TEXT FROM SysSetupFunc WHERE Type = 15'); if (!$settings) { $settings = 'Рецепты_несколько Льготные_несколько Льготные_особые_несколько Выбор_услуг'; } $settings = safe_explode(' ', $settings); foreach ($settings as $row) { selectSqlValue($db, "SELECT FN_htmPageUpdateUnit($newhtmPageCode, '$row', null)"); } } $ret[] = $newMedVisitDocumentCode; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createSettingsCopy($params) { $db = $this->db ?: $this->db = connectDb(); $data = isUndefinedProperty($params,'data'); $sql = <<<"EOT"
INSERT INTO SysSetupFunc (data, type) VALUES (?, 15)
ON CONFLICT (type) DO UPDATE SET
data = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([$data,$data]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readSettingsCopy() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT Data::text as "data"
FROM SysSetupFunc
WHERE Type = 15
EOT;
$result = selectSql($db,$sql); return $result; } public function updateSettingsCopy($params) { $db = $this->db ?: $this->db = connectDb(); return $this->createSettingsCopy($params); } private function readOncologyDocument($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isUndefinedProperty($params, 'patientId'); if (!$patientId) { return false; } $sqlParams = []; $sqlParams[':patientId'] = $patientId; $sql = <<<EOT
SELECT
    MED.MedExtDocumentCode                                          "recordId",
    MED.StartDate                                                   "parentDate",
    MED.FinishDate                                                  "finishDate",
    (
        SELECT STRING_AGG((CASE WHEN MD.MedDiagnosisCode IS NOT NULL
                    THEN  MD.Cipher1 || ' - ' || MD.Name
                    ELSE EDI.Cipher || (CASE WHEN COALESCE(EDI.ValueString, '') <> '' THEN ' - ' || EDI.ValueString ELSE '' END)  END), ', ')
        FROM MedExtDocumentItem EDI
        LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = COALESCE(EDI.ValueInteger,0)
        WHERE COALESCE(EDI.Type,0) = 3
            AND ( COALESCE(EDI.MedExtDocumentCode,0) = MED.MedExtDocumentCode OR
                 COALESCE(EDI.MedExtDocumentOperationCode,0) IN (SELECT MedExtDocumentOperationCode
                                                               FROM MedExtDocumentOperation
                                                               WHERE MedExtDocumentCode = MED.MedExtDocumentCode) )
    )                                                               "diagnosis",
    (
        SELECT STRING_AGG(DISTINCT UIL.Note, '; ')
        FROM MedExtDocumentItem EDI
        JOIN UniImportLink UIL ON COALESCE(UIL.PrimaryKey,0) = EDI.ValueInteger
        WHERE COALESCE(EDI.Type,0) = 1
            AND UIL.Program = 21
            AND UIL.DataType = 1
            AND ( COALESCE(EDI.MedExtDocumentCode,0) = MED.MedExtDocumentCode OR
                 COALESCE(EDI.MedExtDocumentOperationCode,0) IN (SELECT MedExtDocumentOperationCode
                                                               FROM MedExtDocumentOperation
                                                               WHERE MedExtDocumentCode = MED.MedExtDocumentCode) )
    )                                                               "groupRiskList",
    (
        SELECT MEDO.Result
        FROM MedExtDocumentOperation MEDO
        WHERE MEDO.MedExtDocumentCode = MED.MedExtDocumentCode AND
              MEDO.Is_Finished = 1 LIMIT 1
    )                                                               "result",
    MED.State                                                       "state",
    25                                                              "recordType",
    null                                                            "parentId",
    null                                                            "parentType",
    5                                                               "sortType",
    COALESCE(MED.MedExtDocumentCode, '')::text || '-'
                                                                    "path",
    MED.Cancer_Revealed                                             "cancerRevealed",
    MED.Precancer_Revealed                                          "precancerRevealed"
FROM MedExtDocument MED
WHERE MED.MedPatientCode = :patientId
ORDER BY MED.StartDate, 1
EOT;
return selectSql($db, $sql, $sqlParams); } private function readOncologyOperation($params) { $db = $this->db ?: $this->db = connectDb(); $oncologyIds = isEmptyProperty($params, 'oncologyIds'); if (!$oncologyIds) { return false; } $sql = <<<EOT
SELECT
    OP.MedExtDocumentOperationCode                                      "recordId",
    OP.MedExtDocumentCode                                               "parentId",
    OP.Changetime                                                       "recordDate",
    (
        CASE WHEN COALESCE(OP.Organization_short_name,'') = ''
             THEN OP.Organization_name
             ELSE OP.Organization_short_name
        END
    )                                                                   "orgName",
    CASE (COALESCE(OP.Target,''))
        WHEN 'biopsy' THEN 'Биопсия'
        WHEN 'otolaryngologist' THEN 'Оториноларинголог'
        WHEN 'dentist' THEN 'Стоматолог'
        WHEN '11cch' THEN '11 ГКБ'
        WHEN 'oncologist' THEN 'Онколог'
    END                                                                 "targetName",
    OP.DoctorFIO                                                        "doctorFio",
    CASE (COALESCE(OP.Spec_doctor,''))
        WHEN 'biopsy' THEN 'Биопсия'
        WHEN 'otolaryngologist' THEN 'Оториноларинголог'
        WHEN 'dentist' THEN 'Стоматолог'
        WHEN '11cch' THEN '11 ГКБ'
        WHEN 'oncologist' THEN 'Онколог'
        WHEN 'therapist' THEN 'Терапевт'
    END                                                                 "doctorSpecName",
    (
        SELECT STRING_AGG(DISTINCT UIL.Note, '; ')
        FROM MedExtDocumentItem EDI
        JOIN UniImportLink UIL ON COALESCE(UIL.PrimaryKey,0) = EDI.ValueInteger
        WHERE COALESCE(EDI.Type,0) = 1
            AND UIL.Program = 21
            AND UIL.DataType = 1
            AND COALESCE(EDI.MedExtDocumentOperationCode,0) = OP.MedExtDocumentOperationCode
    )                                                                   "groupRiskList",
    (
        SELECT STRING_AGG(
                CASE WHEN MD.MedDiagnosisCode IS NOT NULL
                     THEN  MD.Cipher1 || ' - ' || MD.Name
                     ELSE EDI.Cipher || ' - ' || EDI.ValueString
                END, ', ')
        FROM MedExtDocumentItem EDI
        LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = COALESCE(EDI.ValueInteger,0)
        WHERE (COALESCE(EDI.Type,0) = 2 OR COALESCE(EDI.Type,0) = 3)
            AND COALESCE(EDI.MedExtDocumentOperationCode,0) = OP.MedExtDocumentOperationCode
    )                                                                   "diagnosis",
    OP.Cancer_Revealed                                                  "cancerRevealed",
    OP.Precancer_Revealed                                               "precancerRevealed",
    OP.Result                                                           "result",
    OP.SyncErrorMessage                                                 "syncErrorMessage",
    OP.State                                                            "state",
    26                                                                  "recordType",
    25                                                                  "parentType",
    5                                                                   "sortType",
    MED.StartDate "parentDate",
    COALESCE(MED.MedExtDocumentCode, '')::text || '-' || COALESCE(OP.MedExtDocumentOperationCode, '')::text "path"
FROM MedExtDocumentOperation OP
LEFT JOIN MedExtDocument MED ON MED.MedExtDocumentCode = OP.MedExtDocumentCode
WHERE OP.MedExtDocumentCode $oncologyIds
ORDER BY OP.OperationTime,  OP.Changetime
EOT;
return selectSql($db, $sql); } public function updateCiszStatus($records) { $getMvp = selectSql($this->db, <<<SQL
        SELECT
           MedVisitPatientCode AS "MedVisitPatientCode",
           MedInspectCode AS "MedInspectCode"
        FROM MedVisitDocument
            WHERE MedVisitDocumentCode = :id
 SQL,
[ ':id' => $records->documentId, ], PDO::FETCH_OBJ ); if ($getMvp) { $isInspect = !empty($getMvp[0]->MedInspectCode); $statusCisz = selectSql($this->db, <<<SQL
        SELECT
           *
        FROM CiszLink
            WHERE PrimaryKey = :id AND TableName = :entity AND Type = 2
 SQL,
[ ':id' => $isInspect ? $getMvp[0]->MedInspectCode : $getMvp[0]->MedVisitPatientCode, ':entity' => $isInspect ? 'MedInspect' : 'MedVisitPatient' ], PDO::FETCH_OBJ ); if ($statusCisz && $statusCisz[0]->status === 1) { executeSql($this->db, <<<SQL
                    UPDATE CiszLink
                    SET Status = 2
                    WHERE PrimaryKey = :id AND TableName = :entity AND type = 2
SQL,
[ ':id' => $isInspect ? $getMvp[0]->MedInspectCode : $getMvp[0]->MedVisitPatientCode, ':entity' => $isInspect ? 'MedInspect' : 'MedVisitPatient' ]); } } return true; } } handleJsonRpc('med_card_cards');