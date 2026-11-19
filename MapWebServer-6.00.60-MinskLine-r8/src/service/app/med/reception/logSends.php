<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_logSends { const MAX_PAGE_SIZE = 30; private $db; public function readLogSends($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $sendType = isEmptyProperty($params, 'sendType'); $patient = isEmptyProperty($params, 'patientId'); $profession = isEmptyProperty($params, 'professionId'); $doctor = isEmptyProperty($params, 'doctorId'); $beginDate = isEmptyProperty($params, 'beginDate'); $endDate = isEmptyProperty($params, 'endDate'); $isCard = isEmptyProperty($params, 'isCard'); $resultType = isEmptyProperty($params, 'resultType'); $groupTypes = isEmptyProperty($params, 'groupTypes'); $recType = $resultType ? 19 : 18; $sortType = 2; $needId = $resultType ? '' : 'mls.medLogSendCode "id",'; $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); if ($isCard) { $sqlCard = <<<"EOT"
    CASE $recType
        WHEN 18 THEN mls.medLogSendCode
        WHEN 19 THEN
            CASE
                WHEN mls.sendType IN (3,4) THEN mlsy.medLogStudyCode
                ELSE mvd.medVisitDocumentCode
            END
    END "recordId",
    CASE
        WHEN mls.sendType IN (3,4) THEN ms.type + 100
        ELSE 1
    END "docType",
    to_char(CASE $recType
        WHEN 18 THEN mls.documentDate
        WHEN 19 THEN
        CASE
            WHEN mls.sendType IN (3,4) THEN COALESCE(mlsy.execTime, mlsy.dirTime)
            WHEN mls.sendType = 5 then mf.onDate
            ELSE mls.documentDate
        END
    END, 'YYYY-MM-DD') "parentDate",
    mls.documentDate "recordDate",
    COALESCE(mls.medLogSendCode, '')::text || '-' || CASE $recType
                                                          WHEN 18 THEN ''
                                                          ELSE COALESCE(mvd.medVisitDocumentCode, '')::text
                                                      END "path",
    $recType "recordType",
    CASE $recType
         WHEN 18 THEN null
         ELSE mls.medLogSendCode
    END "parentId",
    CASE $recType
         WHEN 18 THEN null
         WHEN 19 THEN 18
    END "parentType",
    {$sortType} "sortType",
    CASE mls.htmPageCode is not null and $recType = 18
         WHEN true THEN 1
         ELSE 0
    END "isLeaf",
    CASE mls.sendType
       WHEN 1 THEN 'Консультация специалиста'
       WHEN 2 THEN 'Консультация внешнего специалиста'
       WHEN 5 THEN 'Другие обследования'
    END "sendTypeDisplay",
EOT;
} else { $sqlCard = ''; } $sqlFromWhere = "
    FROM medLogSend mls
    LEFT JOIN MedDoctor md ON mls.medDoctorCode = md.medDoctorCode
    LEFT JOIN MedProfession mpr ON mls.medProfessionCode = mpr.medProfessionCode
    LEFT JOIN MedProfession mpr2 ON md.medProfessionCode = mpr2.medProfessionCode
    LEFT JOIN Department d ON md.departmentCode = d.departmentCode
    JOIN MedPatient mp ON mls.medPatientCode = mp.medPatientCode
        and mp.EnterpriseCode = $enterpriseCode
    LEFT JOIN ManPassport mps ON mp.manCode = mps.manCode
    LEFT JOIN MedCouponWork mcw ON mls.medCouponWorkCode = mcw.medCouponWorkCode
    LEFT JOIN MedCoupon mc ON mls.medCouponCode = mc.medCouponCode
    LEFT JOIN MedDoctor mdr ON mc.medDoctorCode = mdr.medDoctorCode
    left join MedProfession MPR_C on MPR_C.medProfessionCode = MDR.medProfessionCode
    LEFT JOIN MedPatientPolyclinic mpp ON mpp.medPatientPolyclinicCode = mls.medPatientPolyclinicStudy
    LEFT JOIN MedVisitDocument mvd ON mls.htmPageCode = mvd.htmPageCode
    LEFT JOIN MedVisitPatient mvp ON mvd.medVisitPatientCode = mvp.medVisitPatientCode
    LEFT JOIN MedVisit mv ON mvp.medVisitCode = mv.medVisitCode
    LEFT JOIN Enterprise e ON mls.enterpriseCode = e.enterpriseCode
    LEFT JOIN MedLogStudy mlsy ON mls.htmPageCode = mlsy.htmPageCode
    LEFT JOIN MedStudy ms ON mlsy.medStudyCode = ms.medStudyCode
    LEFT JOIN HtmPage hp ON mls.htmPageCode = hp.htmPageCode
    LEFT JOIN MedPreventType mpt ON mpt.medPreventTypeCode = mls.medPreventTypeCode
    LEFT JOIN MedFluor mf ON mls.medFluorCode = mf.medFluorCode
    LEFT JOIN UniImportLink uc ON uc.primaryKey = mls.medLogSendCode
                              AND uc.program = 9
                              AND uc.dataType = 1
                              AND uc.deleteTime IS NULL
    LEFT JOIN UniImportLink us ON us.uniImportLinkCode = mpr.talonByCode
    LEFT JOIN MedDoctor mdv ON mdv.medDoctorCode = mv.medDoctorCode
    LEFT JOIN HtmPageT rhp ON rhp.htmPageCode = ( SELECT MIN(thp.htmPageCode)
                                                  FROM UniImportLink tul
                                                  JOIN HtmPage thp ON thp.htmPageCode = tul.primaryKey2
                                                  WHERE tul.program = 9
                                                    AND tul.dataType = 23
                                                    AND tul.primaryKey = mls.medLogSendCode
                                                    AND tul.deleteTime IS NULL )
    LEFT JOIN HtmPageT rhc ON rhc.htmPageCode = ( SELECT MIN(thp.htmPageCode)
                                                  FROM UniImportLink tul
                                                  JOIN HtmPage thp ON thp.htmPageCode = tul.primaryKey2
                                                  WHERE tul.program = 9
                                                    AND tul.dataType = 2
                                                    AND tul.primaryKey = mls.medLogSendCode
                                                    AND tul.deleteTime IS NULL )
    LEFT JOIN CiszResourceValue CRV1 ON CRV1.CiszResourceValueCode = MLS.CiszResourceValueCodeServiceCategory
    LEFT JOIN CiszResourceValue CRV2 ON CRV2.CiszResourceValueCode = MLS.CiszResourceValueCodeSpecialization
    LEFT JOIN CiszResourceValue CRV3 ON CRV3.CiszResourceValueCode = MLS.CiszResourceValueCodeService
    LEFT JOIN MedDiagnosis MDI ON MDI.MedDiagnosisCode = MLS.MedDiagnosisCode
    LEFT JOIN Department DEP ON DEP.DepartmentCode = MLS.DepartmentCodeStudy
    LEFT JOIN Branch BR ON BR.BranchCode = DEP.BranchCode
    WHERE 1=1"; if ($isCard && $recType === 19) { $isHideResult = property_exists($groupTypes, 0) || property_exists($groupTypes, 19) ? 1 : "null"; $sqlFromWhere .= <<<"EOT"
    AND CASE mls.sendType
             WHEN 5 THEN {$isHideResult}
             ELSE null
        END is null
EOT;
} if (isset($sendType)) { $sqlFromWhere .= " AND mls.sendType = $sendType"; } if (isset($patient)) { $sqlFromWhere .= " AND mls.medPatientCode = $patient"; } if ($beginDate) { $sqlFromWhere .= " AND mls.documentDate >= $beginDate"; } if ($endDate) { $sqlFromWhere .= " AND mls.documentDate <= $endDate"; } if ($doctor) { $sqlFromWhere .= " AND mls.medDoctorCode = $doctor"; } if ($id) { $sqlFromWhere .= " AND mls.medLogSendCode = $id"; } if ($profession) { $sqlFromWhere .= " AND mls.medProfessionCode = $profession"; } if ($recType === 19) { $sqlFromWhere .= " AND
                CASE
                    WHEN mls.sendType = 5 THEN mf.medFluorCode
                    ELSE mls.htmPageCode
                END IS NOT NULL"; } $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $sqlTotal = <<<"EOT"
SELECT COUNT(*) "total"
$sqlFromWhere
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $sql = <<<"EOT"
SELECT
    $sqlCard
    {$needId}
    mls.documentNumber "documentNumber",
    mls.documentDate "documentDate",
    mf.number "visitDocumentNumber",
    mls.medDoctorCode "doctorId",
    mls.medPatientCode "patientId",
    hp.name "pageName",
    mc.onDate "couponDate",
    CASE WHEN mc.couponState = 1 AND mc.medpatientcode = mls.medpatientcode THEN 1
        WHEN mc.couponState = 1 AND mc.medpatientcode <> mls.medpatientcode THEN 3
        ELSE mc.couponState END "couponState", /* если заказан на другого человека, значит для этого отменен*/
    CASE WHEN mc.couponState = 0 THEN 'Талон свободен'
        WHEN mc.couponState = 1 AND mc.medpatientcode = mls.medpatientcode THEN 'Талон заказан'
        WHEN mc.couponState = 2 THEN 'Талон выдан'
        WHEN mc.couponState = 3 OR
            (mc.medpatientcode <> mls.medpatientcode AND mls.medpatientcode IS NOT NULL)
            THEN 'Талон отменен'
        ELSE '' END "displayCouponState",
    mc.medDoctorCode "couponDoctorId",
    mc.visitTime "couponVisitTime",
    fn_cmFIO(mdr.manCode,1) "couponDoctor",
    fn_cmFIO(mdv.ManCode,1) "htmPageDoctor",
    mpt.name "preventName",
    COALESCE(mls.controlMoreSurvey, 0)  "controlMoreSurvey",
    mls.enterpriseCode "organizationId",
    e.name "organizationName",
    mls.medPatientPolyclinicStudy "polyclinicStudyId",
    mls.htmPageCode "pageId",
    mls.medCouponCode "couponId",
    mls.medCouponWorkCode "couponWorkId",
    mls.medProfessionCode "professionId",
    CASE
        WHEN mls.sendType IN (6,7) THEN us.name
        ELSE mpr.name
    END "professionName",
    mpr2.name "doctorProfessionName",
    mls.sendGoal "sendGoal",
    mls.sendType "sendTypeId",
    mls.medFluorCode "fluorId",
    mls.medPreventTypeCode "preventTypeId",
    fn_cmFIO(md.manCode,1) "doctor",
    fn_cmCalcAddress(mp.manCode, 5)::text as "address",
    fn_cmFIO(mp.manCode) "patient",
    mps.dateOfBirth "dateOfBirth",
    fn_wgCalcStage(3, (CURRENT_DATE), mps.dateOfBirth, 0, 0, 0)  "age",
    mp.numberHistorySickness "historyNumber",
    CASE
        WHEN mls.sendType IN (3,4) THEN
            CASE mls.htmPageCode IS NOT NULL
                WHEN true THEN cast(fn_htmMask2Str(mls.htmPageCode, ms.resultMask) AS varchar(254))
                WHEN false THEN NULL
            END
        WHEN mls.sendType = 5 THEN cast(mf.conclusion AS Varchar(255))
        WHEN mls.sendType = 6 THEN dateFormat(rhc.createTime, E'dd.mm.yyyy' )
        --WHEN mls.sendType IN (1,2) THEN dateFormat(mvd.createTime, E'dd.mm.yyyy' )
        WHEN mls.sendType = 7 THEN dateFormat(rhp.createTime, E'dd.mm.yyyy' )
        ELSE NULL
    END "result",
    CASE
        WHEN mls.sendType IN (1, 2, 3, 4) THEN
            CASE mls.medCouponWorkCode IS NOT NULL
                WHEN true THEN mcw.name
                WHEN false THEN NULL
            END
        WHEN mls.sendType = 5 THEN (SELECT name FROM MedPreventType WHERE medPreventTypeCode = mls.medPreventTypeCode)
        ELSE NULL
    END "medCouponWorkName",
     CASE
        WHEN mls.sendType IN (1, 2, 3, 4) THEN
            CASE mc.medcouponworkcodechange IS NOT NULL AND mc.couponState = 3
                WHEN true THEN (SELECT name FROM medcouponwork WHERE mc.medcouponworkcodechange = medcouponworkcode and flagcancel = 1)
                WHEN false THEN
                    CASE
                        WHEN mc.couponState = 3 THEN 'не указана'
                        WHEN mc.medpatientcode <> mls.medpatientcode AND mls.medpatientcode IS NOT NULL THEN 'заказан на другого человека'
                        ELSE NULL
                    END
                ELSE NULL
            END
        ELSE NULL
    END "medCouponWorkChancelName",
    CASE
        WHEN mls.sendType IN (3,4) THEN
            CASE hp.name IS NOT NULL
                WHEN true THEN cast(hp.name AS varchar(200))
                WHEN false THEN NULL
            END ||
                CASE mlsy.dirNumber IS NOT NULL
                    WHEN true THEN E' № ' ||  asa_trim(mlsy.dirNumber)
                    WHEN false THEN NULL
                END ||
                    CASE mlsy.execTime IS NOT NULL
                        WHEN true THEN E' от ' || cast(dateFormatConvert(mlsy.execTime, 'DD.MM.YYYY') AS varchar(10))
                        WHEN false THEN NULL
                    END
        WHEN mls.sendType = 5 THEN
            CASE mf.number IS NOT NULL
                WHEN true THEN E'№ ' || mf.number
                WHEN false THEN NULL
            END ||
                CASE mf.onDate IS NOT NULL
                    WHEN true THEN E' от ' || Cast(dateFormatConvert(mf.onDate, 'DD.MM.YYYY') AS varchar(10))
                    WHEN false THEN NULL
                END
        ELSE
            CASE NULLIF(hp.name, E'' ) IS NOT NULL
                WHEN true THEN cast(hp.name AS varchar(200))
                WHEN false THEN NULL
            END ||
                CASE NULLIF(mvp.documentNumber, E'' ) IS NOT NULL
                    WHEN true THEN E' № ' || asa_trim(mvp.documentNumber)
                    WHEN false THEN NULL END ||
                        CASE mv.visitDate IS NOT NULL
                            WHEN true THEN E' от ' || cast(dateFormatConvert(mv.visitDate, 'DD.MM.YYYY') AS varchar(10))
                            WHEN false THEN NULL
                        END
    END "visitDocumentName",
    CASE
         WHEN MLS.sendType = 5 THEN null
         ELSE CASE HP.name IS NOT NULL
                   WHEN true THEN CAST(HP.name AS varchar(200))
                   WHEN false THEN NULL
              END
    END "send_DocumentName",
    CASE
         WHEN MLS.sendType IN (3,4) THEN CASE MLSY.dirNumber IS NOT NULL
                                              WHEN true THEN asa_trim(MLSY.dirNumber)
                                              WHEN false THEN NULL
                                         END
         WHEN MLS.sendType = 5 THEN CASE MF.number IS NOT NULL
                                         WHEN true THEN MF.number
                                         WHEN false THEN NULL
                                    END
         ELSE CASE NULLIF(MVP.documentNumber, '') IS NOT NULL
                   WHEN true THEN asa_trim(MVP.documentNumber)
                   WHEN false THEN NULL
              END
    END "send_DocumentNumber",
    CASE
         WHEN MLS.sendType IN (3,4) THEN CASE MLSY.execTime IS NOT NULL
                                              WHEN true THEN CAST(dateFormatConvert(MLSY.execTime, 'DD.MM.YYYY') AS varchar(10))
                        WHEN false THEN NULL
                    END
         WHEN MLS.sendType = 5 THEN CASE MF.onDate IS NOT NULL
                                         WHEN true THEN CAST(dateFormatConvert(MF.onDate, 'DD.MM.YYYY') AS varchar(10))
                                         WHEN false THEN NULL
                                    END
         ELSE CASE MV.visitDate IS NOT NULL
                   WHEN true THEN CAST(dateFormatConvert(MV.visitDate, 'DD.MM.YYYY') AS varchar(10))
                   WHEN false THEN NULL
              END
    END "send_DocumentDate",
    CASE
        WHEN mls.medCouponCode IS NOT NULL THEN cast(dateFormatConvert(mc.onDate, 'DD.MM.YYYY') AS varchar(10)) ||  E' ' ||
                                                cast(dateFormatConvert(mc.visitTime, 'HH:MI:SS') AS varchar(5))  ||  E' ' ||
                                                cast(fn_cmFIO(mdr.manCode,1) AS varchar(30))
        WHEN (uc.uniImportLinkCode IS NOT NULL) AND (COALESCE(mls.SendType,0) in (6)) THEN
            CASE (COALESCE(uc.Name, E'' ) <> ( E'' ))
                WHEN true THEN uc.name || E', '
            END ||
            dateFormat(uc.valueDate, E'dd.mm.yyyy hh:nn' ) ||
            CASE (COALESCE(uc.valueString, E'' ) <> ( E'' ))
                WHEN true THEN E' (' || uc.valueString || E')'
            END
        ELSE NULL
    END /* || CASE WHEN mc.couponState = 0 THEN '(талон свободен)'
        WHEN mc.couponState = 1 AND mc.medpatientcode = mls.medpatientcode THEN '(талон заказан)'
        WHEN mc.couponState = 2 THEN '(талон выдан)'
        WHEN mc.couponState = 3 OR
            (mc.medpatientcode <> mls.medpatientcode AND mls.medpatientcode IS NOT NULL)
            THEN '(талон отменен)'
        ELSE '' END */ "visitTimes",
    mc.OnDate "coupon_Date",
    mc.visitTime "coupon_Time",
    MPR_C.name "coupon_ProfessionName",
    cast(mf.conclusion AS varchar(255)) "fluorConclusion",
    MLS.Priority "priorityId",
    MLS.CiszResourceValueCodeServiceCategory "categoryCode",
    CRV1.Name "categoryName",
    MLS.StartActionDate "startDate",
    MLS.FinishActionDate "finishDate",
    MLS.CiszResourceValueCodeSpecialization "specialityCode",
    CRV2.Name "specialityName",
    MLS.CiszResourceValueCodeService "serviceTypeCode",
    CRV3.Name "serviceTypeName",
    MLS.MedDiagnosisCode "diagnosisId",
    (CASE WHEN MDI.Cipher1 IS NULL THEN E''
           ELSE MDI.Cipher1 || E' - '
           END) || MDI.Name "diagnosisName",
    MLS.Note "note",
    MLS.PatientInstruction "instruction",
    MLS.DepartmentCodeStudy "departmentId",
    DEP.Name "departmentName",
    BR.EnterpriseCode "departmentOrg",
    CASE MLS.Priority 
        WHEN 1 THEN 'Планово'
        WHEN 2 THEN 'Экстренно'
        WHEN 3 THEN 'Неотложно'
    END "priorityName",
    MLS.RejectDate "rejectDate",
    MLS.CiszResourceValueCodeRejectCause "reasonRejectionId"
    $sqlFromWhere
    ORDER BY mls.documentDate DESC, RIGHT(REPEAT(E' ', 20) || asa_trim(mls.documentNumber), 20), mls.medProfessionCode 
EOT;
$items = selectSql($db, $sql); $root = new stdClass(); $root->total = $total; $root->items = $items; $root->page = $page; if ($isCard) { return $root->items; } else { if ($id) { return $root->items; } else { $root->items = array_slice($items, $start, $limit); return $root; } } } public function createLogSends($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        INSERT INTO MedLogSend (documentNumber, documentDate, meddoctorcode, medpatientcode, sendType, sendGoal, medProfessionCode, enterpriseCode,
                                medPreventTypeCode,medCouponWorkCode,medFluorCode,medCouponCode,htmPageCode,
                                Priority, CiszResourceValueCodeServiceCategory, StartActionDate, FinishActionDate,
                                CiszResourceValueCodeSpecialization, CiszResourceValueCodeService, MedDiagnosisCode, Note,
                                PatientInstruction, DepartmentCodeStudy, RejectDate, CiszResourceValueCodeRejectCause)
        values (?,?,?,?,?,?,?,?,?,?,?,?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $sendTypeId = isUndefinedProperty($row, 'sendTypeId'); $documentNumber = isUndefinedProperty($row, 'documentNumber'); if (!empty($documentNumber) && trim((string)$documentNumber) !== '') { if ($this->checkLogSendNumber($documentNumber, $sendTypeId)) { $documentNumber = $this->getDocumentNumber($sendTypeId); } } else { $documentNumber = $this->getDocumentNumber($sendTypeId); } $stmt->execute([ $documentNumber, isUndefinedProperty($row, 'documentDate'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'patientId'), $sendTypeId, isUndefinedProperty($row, 'sendGoal'), isEmptyProperty($row, 'professionId'), isEmptyProperty($row, 'organizationId'), isEmptyProperty($row, 'preventTypeId'), isEmptyProperty($row, 'couponWorkId'), isEmptyProperty($row, 'fluorId'), isEmptyProperty($row, 'couponId'), isEmptyProperty($row, 'pageId'), isEmptyProperty($row, 'priorityId'), isEmptyProperty($row, 'categoryCode'), isEmptyProperty($row, 'startDate'), isEmptyProperty($row, 'finishDate'), isEmptyProperty($row, 'specialityCode'), isEmptyProperty($row, 'serviceTypeCode'), isEmptyProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'instruction'), isUndefinedProperty($row, 'departmentId'), isEmptyProperty($row, 'rejectDate'), isEmptyProperty($row, 'reasonRejectionId') ]); $param = new stdClass; $param->id = (int) $db->lastInsertId('seq_MedLogSend'); if (property_exists($row, 'services')) { $this->syncSendServices( $param->id, is_array($row->services) ? $row->services : [] ); } $ret[] = $this->readLogSends($param)[0]; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getDocumentNumber($sendTypeId, $sendId = null) { $db = $this->db ?: $this->db = connectDb(); if ($sendTypeId == 1 || $sendTypeId == 2) { $whereCondition = "SendType IN (1, 2)"; $params = []; } else { $whereCondition = "SendType = ?"; $params = [$sendTypeId]; } if ($sendId) { $whereCondition .= " AND MedLogSendCode <> ?"; $params[] = $sendId; } $sql = <<<"EOT"
SELECT
    MedLogSendCode                                     "medLogSendCode",
    incstr(COALESCE(asa_trim(DocumentNumber), '0'))    "newNumber"
FROM
    MedLogSend
WHERE
    {$whereCondition}
ORDER BY
    MedLogSendCode DESC
LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute($params); return fetchValue($stmt, 'newNumber'); } public function getCouponQuotaPercent($params = null) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId'); $professionId = isEmptyProperty($params, 'professionId'); $couponWorkId = isEmptyProperty($params, 'couponWorkId'); $sql = <<<SQL
            SELECT
                MCP.Percent "percent"
            FROM MedCouponQuotaPercent MCP
            LEFT JOIN MedCouponQuota MCQ ON MCQ.MedCouponQuotaCode = MCP.MedCouponQuotaCode
            LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = $doctorId
            WHERE MCQ.MedCouponWorkCode = $couponWorkId
                AND MCQ.MedProfessionCode = $professionId
                AND (
                    CASE
                        WHEN MCP.MedDoctorCode IS NULL THEN TRUE
                        ELSE MCP.MedDoctorCode = MD.MedDoctorCode
                    END
                    AND CASE
                        WHEN MCP.MedProfessionCode IS NULL THEN TRUE
                        ELSE MCP.MedProfessionCode = MD.MedProfessionCode
                    END
                    AND CASE
                        WHEN MCP.DepartmentCode IS NULL THEN TRUE
                        ELSE MCP.DepartmentCode = MD.DepartmentCode
                    END
                )
            ORDER BY MCP.MedDoctorCode DESC, MCP.MedProfessionCode DESC, MCP.DepartmentCode DESC
            LIMIT 1
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $percent = (int) fetchValue($stmt, 'percent'); return $percent; } public function updateLogSends($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        UPDATE MedLogSend
        set
        documentNumber = case when ? = 1 then ? else documentNumber end,
        documentDate = case when ? = 1 then ? else documentDate end,
        meddoctorcode = case when ? = 1 then ? else meddoctorcode end,
        medpatientcode = case when ? = 1 then ? else medpatientcode end,
        sendType = case when ? = 1 then ? else sendType end,
        sendGoal = case when ? = 1 then ? else sendGoal end,
        medProfessionCode = case when ? = 1 then ? else medProfessionCode end,
        enterpriseCode = case when ? = 1 then ? else enterpriseCode end,
        medPreventTypeCode = case when ? = 1 then ? else medPreventTypeCode end,
        medCouponWorkCode = case when ? = 1 then ? else medCouponWorkCode end,
        medFluorCode = case when ? = 1 then ? else medFluorCode end,
        medCouponCode = case when ? = 1 then ? else medCouponCode end,
        htmPageCode = case when ? = 1 then ? else htmPageCode end,
        Priority = CASE WHEN ? = 1 THEN ? ELSE Priority END,
        CiszResourceValueCodeServiceCategory = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeServiceCategory END,
        StartActionDate = CASE WHEN ? = 1 THEN ? ELSE StartActionDate END,
        FinishActionDate = CASE WHEN ? = 1 THEN ? ELSE FinishActionDate END,
        CiszResourceValueCodeSpecialization = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeSpecialization END,
        CiszResourceValueCodeService = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeService END,
        MedDiagnosisCode = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCode END,
        Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
        PatientInstruction = CASE WHEN ? = 1 THEN ? ELSE PatientInstruction END,
        DepartmentCodeStudy = CASE WHEN ? = 1 THEN ? ELSE DepartmentCodeStudy END,
        RejectDate = CASE WHEN ? = 1 THEN ? ELSE RejectDate END,
        CiszResourceValueCodeRejectCause = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeRejectCause END
        WHERE
        MedLogSendCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $id = isEmptyProperty($row, 'id'); $documentNumber = isEmptyProperty($row, 'documentNumber'); if (!empty($documentNumber) && trim((string)$documentNumber) !== '') { $sendTypeId = isEmptyProperty($row, 'sendTypeId'); if (!$sendTypeId) { $sendTypeId = selectSqlValue($db, "
                            SELECT 
                                SendType
                            FROM MedLogSend
                            WHERE MedLogSendCode = :id
                            ", [':id' => $id]); } if ($this->checkLogSendNumber($documentNumber, $sendTypeId)) { $documentNumber = $this->getDocumentNumber($sendTypeId, $id); } } $stmt->execute([ propertyExistsSQL($row, 'documentNumber'), $documentNumber, propertyExistsSQL($row, 'documentDate'), isEmptyProperty($row, 'documentDate'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), propertyExistsSQL($row, 'sendTypeId'), isEmptyProperty($row, 'sendTypeId'), propertyExistsSQL($row, 'sendGoal'), isEmptyProperty($row, 'sendGoal'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'organizationId'), isEmptyProperty($row, 'organizationId'), propertyExistsSQL($row, 'preventTypeId'), isEmptyProperty($row, 'preventTypeId'), propertyExistsSQL($row, 'couponWorkId'), isEmptyProperty($row, 'couponWorkId'), propertyExistsSQL($row, 'fluorId'), isEmptyProperty($row, 'fluorId'), propertyExistsSQL($row, 'couponId'), isEmptyProperty($row, 'couponId'), propertyExistsSQL($row, 'pageId'), isEmptyProperty($row, 'pageId'), propertyExistsSQL($row, 'priorityId'), isEmptyProperty($row, 'priorityId'), propertyExistsSQL($row, 'categoryCode'), isEmptyProperty($row, 'categoryCode'), propertyExistsSQL($row, 'startDate'), isEmptyProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isEmptyProperty($row, 'finishDate'), propertyExistsSQL($row, 'specialityCode'), isEmptyProperty($row, 'specialityCode'), propertyExistsSQL($row, 'serviceTypeCode'), isEmptyProperty($row, 'serviceTypeCode'), propertyExistsSQL($row, 'diagnosisId'), isEmptyProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'note'), isEmptyProperty($row, 'note'), propertyExistsSQL($row, 'instruction'), isEmptyProperty($row, 'instruction'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'rejectDate'), isEmptyProperty($row, 'rejectDate'), propertyExistsSQL($row, 'reasonRejectionId'), isEmptyProperty($row, 'reasonRejectionId'), isEmptyProperty($row, 'id') ]); if (property_exists($row, 'services')) { $this->syncSendServices( (int)$row->id, is_array($row->services) ? $row->services : [] ); } $param = new stdClass(); $param->id = $row->id; $ret[] = $this->readLogSends($param)[0]; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function setFreeCoupon($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            UPDATE MedCoupon SET
                MedPatientCode = null,
                CouponState = 0,
                SelfRecording = 0,
                OrderTime = null,
                GiveTime = null,
                FIO = null,
                Note = null
            WHERE MedCouponCode = ?
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteLogSends($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedLogSend', 'MedLogSendCode', $records); } public function readSendServices($params = null) { $db = $this->db ?: $this->db = connectDb(); $sendId = isEmptyProperty($params, 'sendId'); $sql = <<<"EOT"
SELECT
        MLSA.CiszResourceValueCodeAddService "id",
        CRV.Name "name"
        FROM MedLogSendAddService MLSA
           LEFT JOIN CiszResourceValue CRV ON MLSA.CiszResourceValueCodeAddService = CRV.CiszResourceValueCode
        WHERE MLSA.MedLogSendCode = $sendId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } private function syncSendServices(int $sendId, array $incomingServices): void { $db = $this->db ?: $this->db = connectDb(); $incomingServices = array_map('intval', $incomingServices); $incomingServices = array_unique($incomingServices); $stmt = $db->prepare("
        SELECT CiszResourceValueCodeAddService
        FROM MedLogSendAddService
        WHERE MedLogSendCode = ?
    "); $stmt->execute([$sendId]); $existing = $stmt->fetchAll(PDO::FETCH_COLUMN); $existing = array_map('intval', $existing); $toAdd = array_diff($incomingServices, $existing); $toRemove = array_diff($existing, $incomingServices); if ($toAdd) { $stmtInsert = $db->prepare("
            INSERT INTO MedLogSendAddService
                (MedLogSendCode, CiszResourceValueCodeAddService)
            VALUES (?, ?)
        "); foreach ($toAdd as $actionCode) { $stmtInsert->execute([$sendId, $actionCode]); } } if ($toRemove) { $in = implode(',', array_fill(0, count($toRemove), '?')); $stmtDelete = $db->prepare("
            DELETE FROM MedLogSendAddService
            WHERE MedLogSendCode = ?
              AND CiszResourceValueCodeAddService IN ($in)
        "); $stmtDelete->execute(array_merge([$sendId], $toRemove)); } } public function checkProfession($params = null) { $db = $this->db ?: $this->db = connectDb(); $sourceCode = isEmptyProperty($params, 'id'); $sql = <<<"EOT"
SELECT
        CRV.CiszResourceValueCode "id",
        CR.Source "source",
        CRV.Name "name"
        FROM CiszResourceValue CRV
           JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Source IN (35, 36)
           WHERE CRV.SourceCode = $sourceCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function checkLogSendNumber($documentNumber, $sendTypeId) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = [ ':documentNumber' => $documentNumber ]; if ($sendTypeId == 1 || $sendTypeId == 2) { $sendTypeSql = "MLS.SendType IN (1, 2) AND "; } else { $sendTypeSql = "MLS.SendType = :sendTypeId AND "; $sqlParams[':sendTypeId'] = $sendTypeId; } $sql = "
        SELECT 1
        FROM MedLogSend MLS
        WHERE $sendTypeSql TRIM(MLS.DocumentNumber) = TRIM(:documentNumber)
        LIMIT 1
    "; return (bool)selectSqlValue($db, $sql, $sqlParams); } } if (!isset($notExecute)) { handleJsonRpc('med_reception_logSends'); }