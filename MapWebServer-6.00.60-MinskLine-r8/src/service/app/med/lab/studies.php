<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/linkLis.php'); resumeJsonRpc(); class med_lab_studies { const PAGE_SIZE = 30; private $db; public function readStudies($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $currId = isEmptyProperty($params, 'sourceKey'); $type = isUndefinedProperty($params, 'type', 0); $isLab = $type === 1; $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : Date(19000101); $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : Date(30000101); $fieldType = isUndefinedProperty($params, 'dateType') ? 'MLS.DirTime' : 'MLS.ExecTime'; $whereDoctor = isEmptyProperty($params, 'doctorId') ? "and (MLS.MedDoctorCode = $params->doctorId or MLS.MedDoctorCodeExec = $params->doctorId)" : ""; $whereExecDoctor = isEmptyProperty($params, 'execDoctorId') ? "and (MLS.MedDoctorCodeExec = $params->execDoctorId or MLS.MedDoctorCodeExec = $params->execDoctorId)" : ""; $whereExecBuhMol = isEmptyProperty($params, 'execBuhMolId') ? "and (MLS.BuhMolCodeExec = $params->execBuhMolId or MLS.BuhMolCodeExec = $params->execBuhMolId)" : ""; $whereStudy = isEmptyProperty($params, 'studyId') ? "and MLS.MedStudyCode = $params->studyId" : ""; $whereImportant = isEmptyProperty($params, 'important') ? $params->important === 1 ? "and MLS.Important = $params->important" : "and coalesce(MLS.Important,0) !=1" : ""; $whereComplete = isEmptyProperty($params, 'complete') ? $params->complete === 1 ? "and MLS.State = 2" : "and coalesce(MLS.State,0) <> 2" : ""; $wherePatient = isEmptyProperty($params, 'patientId') ? "and MLS.MedPatientCode = $params->patientId" : ""; $whereDriverCode = isEmptyProperty($params, 'driverId') ? "  and (MLS.MedStudyCode in (select medStudyCode from MedLabDriverTemplate where MedLabDriverCode = $params->driverId))" : ""; $sort = isEmptyProperty($params, 'sort') ? "\"$params->sort\"" : '1'; $dir = isEmptyProperty($params, 'dir') ? $params->dir : ''; $sort = isEmptyProperty($params, 'sort') === "dirNumber" ? "RIGHT(REPEAT(' ', 20) + asa_trim(MLS.DirNumber), 20)" : $sort; $whereExclude = isEmptyProperty($params, 'excludeIds') ? ('AND MLS.MedLogStudyCode NOT ' . createSqlInCondition(explode(',', $params->excludeIds))) : ''; $labJoin = $isLab ? "left join MedLabStudy MLS_L on MLS_L.medStudyCode = MLS.medStudyCode" : ""; $ciszTypes = $this->getCiszTypes(); $ciszTemplateIds = []; foreach ($ciszTypes as $item) { $ciszTemplateIds[] = $item['templateId']; } if (!empty($ciszTemplateIds)) { $ciszIdsString = implode(',', $ciszTemplateIds); $isCiszField = "CASE WHEN MS.MedStudyCode IN ($ciszIdsString) THEN true ELSE false END \"isCisz\""; } else { $isCiszField = "false \"isCisz\""; } $from = <<<"EOT"
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
     left join unidict UD on UD.unidictcode = MLS.unidictcode_timenorm
     LEFT JOIN (
       SELECT
         PrimaryKey, MAX(Status) AS Status
       FROM CiszLink
       WHERE SPLIT_PART(TableName, '/', 1) = 'MedLogStudy'
       GROUP BY PrimaryKey
     ) CL ON CL.PrimaryKey = MLS.MedLogStudyCode
     LEFT JOIN MedProfession MPR ON MPR.MedProfessionCode = MD2.MedProfessionCode
     {$labJoin}
EOT;
$pageSize = isEmptyProperty($params, 'limit', self::PAGE_SIZE); $pageSize = $pageSize <= self::PAGE_SIZE ? self::PAGE_SIZE : $pageSize; if ($currId) { $where = "where medLogStudyCode = $currId"; $limit = ""; } else { $where = <<<"EOT"
where MLS.Type IS NOT NULL
    /* хоть один участок доступен пользователю */
    and (mp.MedPatientGroupCode in (select gc from pg_)
        or (
            exists (select 1 from MedPatientAddGroup pga
                where pga.MedPatientCode = mp.MedPatientCode
                    and pga.MedPatientGroupCode in (select gc from pg_))
        )
        or mls.MedPatientCode is null
    )
  and MLS.Type = $type
  and $fieldType >= $startDate
  and $fieldType < $finishDate + INTERVAL '1 day'
  $whereImportant
  $whereComplete
  $whereExecBuhMol
  $whereExecDoctor
  $whereDoctor
  $whereStudy
  $whereDriverCode
  $wherePatient
  $whereExclude
EOT;
$sqlCount = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
select COUNT(MlS.MedLogStudyCode) "total"
$from
$where
EOT;
$stmtCount = $db->prepare($sqlCount); $stmtCount->execute(); $ret = new stdClass(); $ret->total = (int) fetchValue($stmtCount, 'total'); $start = $ret->total < $pageSize ? 0 : isEmptyProperty($params, 'start', 0); $limit = "limit $pageSize offset $start"; } $solisMark = $this->getSolisMarkSQLFields(); $labFields = $isLab ? ", MLS_L.barcodeType \"barcodeType\"" . ", MLS_L.ElisType \"elisType\"" . ", MLS.barcode || DATEFORMAT(MLS.dirTime, ', dd.mm.yyyy') || ' - ' || FN_cmFIO(MP.manCode, 1) \"research\"" : ""; $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
select
    MLS.MedLogStudyCode "id",
    MLS.DirNumber "dirNumber",
    MLS.htmPageCode "pageId",
    MLS.DirTime "dirTime",
    FN_cmFIO(MD1.ManCode) "dirDoc",
    MPG.Name "patientSector",
    MLS.MedDiagnosisCode "diagnosisId",
    MS.Name "nameStudy",
    HPT.IsTemplate "isTemplate",
    MS.MedStudyCode "templateId",
    {$isCiszField},
    MLS.PatientFIO "patientFio",
    MLS.PatientDateOfBirth "birthDate",
    MLS.PatientAddress "address",
    MLS.Important "importantAttribute",
    MLS.PayAttention "payAttention",
    MLS.ExecTime "exTime",
    (SELECT 1 FROM MedLogStudySop MLSS WHERE MLSS.MedLogStudyCode = MLS.MedLogStudyCode ORDER BY StudyTime DESC LIMIT 1) "studySopExists",
    CASE
         WHEN MLS.MedDoctorCodeExec IS NOT NULL THEN FN_cmFIO(MD2.ManCode)
         ELSE FN_cmFIO(BM.ManCode)
    END "exDoc",
    --22589 FN_htmMask2Str(MLS.htmPageCode, MLS.Result) "res",
    MLS.Result "res",
    (SELECT 1 FROM MedExternalFileT WHERE SourceCode = MLS.MedLogStudyCode LIMIT 1) "exFileExists",
    (SELECT COUNT(MedExternalFileCode) FROM MedExternalFileT WHERE SourceCode = MLS.MedLogStudyCode LIMIT 1) "exFileCount",
    MS.htmPageCode "templatePageId",
    MLS.State "state",
    coalesce(MLS.FactTime, UD.value1) "normTime",
    CL.Status "ciszStatus",
CASE
    WHEN NULLIF(TRIM(MLS.Result), '') IS NOT NULL THEN
        MLS.Result
    WHEN NULLIF(TRIM(MLS.Conclusion), '') IS NOT NULL THEN
        MLS.Conclusion
    WHEN MLS.MedDoctorCodeExec IS NOT NULL THEN
        FN_cmFIO(MD2.ManCode, 1) || ', ' || MPR.Name
    ELSE
        FN_cmFIO(BM.ManCode, 1)
END AS "fullResult",
    $solisMark
    {$labFields}
$from
$where
order by $sort $dir
$limit
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($currId) { $ret = $stmt->fetch(); } else { $ret->items = $stmt->fetchAll(); } return $ret; } public function getCiszTypes() { $db = $this->db ?: $this->db = connectDb(); $data = selectSql($db, <<<SQL
            SELECT
            MS.MedStudyCode "templateId"
            FROM CiszStudy CS
            JOIN CiszStudyList CSL ON CSL.CiszStudyCode = CS.CiszStudyCode
            JOIN MedStudy MS ON MS.MedStudyCode = CSL.MedStudyCode
        SQL
); return $data; } public function deleteStudies($records) { $db = $this->db ?: $this->db = connectDb(); $sqlDeleteMIR = <<<SQL
            DELETE FROM MedInspectResearch
            WHERE MedLogStudyCode = :medLogStudyId
                AND EXISTS (
                    SELECT 1
                    FROM MedLogStudy
                    WHERE MedLogStudyCode = :medLogStudyId
                )
            SQL;
$sqlDeleteMLS = <<<SQL
            DELETE FROM MedLogStudy
            WHERE MedLogStudyCode = :medLogStudyId
            SQL;
if (is_object($records)) { $records = [ $records ]; } $db->beginTransaction(); try { foreach ($records as $row) { $medLogStudyId = $row->id; executeSql($db, $sqlDeleteMIR, [ ':medLogStudyId' => $medLogStudyId ]); executeSql($db, $sqlDeleteMLS, [ ':medLogStudyId' => $medLogStudyId ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createStudy($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId'); $type = isUndefinedProperty($params, 'type'); $studyId = isEmptyProperty($params, 'studyId'); $patientId = isEmptyProperty($params, 'patientId'); if ($patientId) { $sqlPatient = <<<"EOT"
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
$stmtPatient = $db->prepare($sqlPatient); $stmtPatient->execute(); $patientInfo = $stmtPatient->fetch(); $params->patientFio = $patientInfo['fio']; $params->numberHistory = $patientInfo['card']; $params->patientDateOfBirth = $patientInfo['dateB']; $params->patientAddress = $patientInfo['address']; $params->patientSex = $patientInfo['sex']; } $dirNumber = $this->getNextDirNumber($studyId); $sql = <<<"EOT"
insert into MedLogStudy
    (Type, DirTime, State, MedStudyCode, MedDoctorCodeExec, MedDoctorCode, DirNumber, medPatientCode, PatientFIO,
     NumberHistorySickness, PatientDateOfBirth, PatientAddress, PatientSex, Barcode)
values
    (?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $type, 1, $studyId, null, $doctorId, $dirNumber, isEmptyProperty($params, 'patientId'), isUndefinedProperty($params, 'patientFio'), isUndefinedProperty($params, 'numberHistory'), isEmptyProperty($params, 'patientDateOfBirth'), isUndefinedProperty($params, 'patientAddress'), isUndefinedProperty($params, 'patientSex'), isUndefinedProperty($params, 'barcode') ]); $id = $db->lastInsertId('seq_MedLogStudy'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $id; } public function setPayAttention($params) { $db = $this->db ?: $this->db = connectDb(); $res = new stdClass(); $res->apply = 0; $sqlCheckAttention = <<<"EOT"
Select case  when coalesce(mls.important,0) != 0 and mls.state=2 then 1 else 0 end "checkAttention"
from medlogStudy mls
where mls.medlogstudycode = $params->sourceKey
EOT;
$stmt = $db->prepare($sqlCheckAttention); $stmt->execute(); $checkAttention = fetchValue($stmt, 'checkAttention'); if ($checkAttention != 0) { $sql = <<<"EOT"
update MedLogStudy set
       payAttention = 1
where MedLogStudyCode = $params->sourceKey
EOT;
} else { $sql = <<<"EOT"
update MedLogStudy set
       payAttention = 0
where MedLogStudyCode = $params->sourceKey
EOT;
} $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $res->apply = 1; return $res; } public function updateStudy($params) { if (is_array($params)) { return $this->updateStudies($params); } $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'id'); $sourceKey = isUndefinedProperty($params, 'sourceKey', isUndefinedProperty($params, 'id', 0)); $pageId = isEmptyProperty($params, 'pageId'); $sql = <<<"EOT"
update MedLogStudy set
       htmPageCode = $pageId
where MedLogStudyCode = $sourceKey
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $par = new stdClass(); $par->sourceKey = $sourceKey; $this->setPayAttention($par); if ($id) { return true; } else { $this->sendToSolis($sourceKey); return $this->readStudies($par); } } public function updateStudies($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } return updateSql($db, 'MedLogStudy', 'MedLogStudyCode', $records, 'htmPageCode', 'pageId'); } public function UpdateCiszStatus($record) { $db = $this->db ?: $this->db = connectDb(); $sql = "SELECT Status FROM CiszLink
                WHERE PrimaryKey = ?
                AND SPLIT_PART(tablename, '/', 1) = 'MedLogStudy'
                AND Type = 2"; $stmt = $db->prepare($sql); $stmt->execute([$record->sourceKey]); $ciszState = $stmt->fetchColumn(); if ($ciszState = 1) { $db->beginTransaction(); $updateSql = "UPDATE CiszLink SET Status = 2
                         WHERE PrimaryKey = ?
                         AND SPLIT_PART(tablename, '/', 1) = 'MedLogStudy'"; $updateStmt = $db->prepare($updateSql); $updateStmt->execute([$record->sourceKey]); $affectedRows = $updateStmt->rowCount(); $db->commit(); } return true; } private function getNextDirNumber($studyId) { $db = $this->db ?: $this->db = connectDb(); $sqlCheck = <<<"EOT"
select
  COALESCE(
    (select MAX(CAST(DirNumber AS INTEGER)) + 1
     from MedLogStudy
     where
       medStudyCode = ?
       and EXTRACT(year FROM DirTime) = EXTRACT(year FROM CURRENT_DATE)
       and DirNumber ~ '^\d+$'),
    1
  )::text as "dirNumber"
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute([(int)$studyId]); return fetchValue($stmtCheck, 'dirNumber'); } public function createTemplateDirections($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isset($params->doctorId) && isEmptyProperty($params, 'doctorId') ? $params->doctorId : null; $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $onDate = isEmptyProperty($params, 'onDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate'))) : null; $studyList = isset($params->studyList) && isEmptyProperty($params, 'studyList') ? $params->studyList : null; $rec = null; $ret = []; if (!$doctorId || !$patientId || !$onDate || !$studyList) { return false; } $db->beginTransaction(); try { foreach ($studyList as $t) { $dirNumber = $this->getNextDirNumber($t->studyId); $sql = <<<"EOT"
insert into MedLogStudy (
    MedPatientCode,
    DirTime,
    MedDoctorCode,
    DepartmentCode,
    MedStudyCode,
    htmPageCode,
    State,
    Type,
    DirNumber,
    NumberHistorySickness,
    PatientFIO,
    PatientDateOfBirth,
    PatientAddress,
    PatientSex,
    Department )
select
    mp.MedPatientCode,
    ? curDirTime,
    md.MedDoctorCode,
    md.DepartmentCode,
    ? curMedStudyCode,
    null curHtmPageCode,
    1,
    ? curType,
    ? curDirNumber,
    mp.NumberHistorySickness,
    fn_cmFIO(mp.ManCode) PatientFIO,
    p.DateOfBirth PatientDateOfBirth,
    FN_cmCalcAddress(mp.ManCode) PatientAddress,
    p.Sex PatientSex,
    case
        when asa_trim(coalesce(d.FullName, E'')) = E'' then asa_trim(coalesce(d.Name, E''))
        else asa_trim(coalesce(d.FullName, E''))
    end Department
from MedPatient mp
left outer join ManPassport p on p.ManCode = mp.ManCode,
MedDoctor md
left outer join Department d on d.DepartmentCode = md.DepartmentCode
where mp.MedPatientCode = ?
    and md.MedDoctorCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $onDate, (int) $t->studyId, (int) $t->studyType, $dirNumber, (int) $patientId, (int) $doctorId ]); $rec = new stdClass(); $rec->id = (int) $db->lastInsertId('seq_MedLogStudy'); $rec->studyId = $t->studyId; $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readPatientStudies($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate'))) : null; $filterByDoctor = isUndefinedProperty($params, 'filterByDoctor', 1); $sqlWhere = ''; if (isset($patientId)) { $sqlWhere .= " AND MLS.MedPatientCode = $patientId"; } if (isset($doctorId) && $filterByDoctor) { $sqlWhere .= " AND MLS.MedDoctorCode = $doctorId"; } if (isset($onDate)) { $sqlWhere .= " AND CAST(MLS.DirTime AS DATE) = $onDate"; } $solisFields = $this->getSolisMarkSQLFields(); $sql = <<<EOT
            SELECT
                MLS.MedLogStudyCode     "id",
                MLS.htmPageCode         "pageId",
                MS.htmPageCode          "templatePageId",
                MS.Name                 "nameStudy",
                MS.FullName             "fullNameStudy",
                MLS.Type                "type",
                {$solisFields}
            FROM MedLogStudy MLS
            LEFT JOIN MedStudy MS ON MS.MedStudyCode = MLS.MedStudyCode
            WHERE MLS.Type IS NOT NULL
                $sqlWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function doAddMedicalExaminationReferrals($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId'); $preventTypeId = isEmptyProperty($params, 'preventTypeId'); $onDate = isEmptyProperty($params, 'onDate'); $onDate = $onDate ? Date("Ymd", safe_strtotime($onDate)) : null; $preventId = null; $studies = null; $pdInfo = null; $ret = []; if (!is_numeric($preventTypeId)) { throw new Exception("Заполните профилактику"); } $sql = <<<EOT
            SELECT FN_medFindPrevent(
                :patientId,
                (
                    SELECT STRING_AGG((MedPreventCode)::TEXT, ',')
                    FROM MedPrevent
                    WHERE MedPreventTypeCode = :preventTypeId
                ),
                COALESCE((
                        SELECT Pass.DateOfBirth
                        FROM MedPatient MP
                        JOIN ManPassport Pass ON Pass.ManCode = MP.ManCode
                        WHERE MP.MedPatientCode = :patientId
                    ), YMD(YEAR('now'::DATE), 1, 1)
                ),
                :onDate
            )
EOT;
$preventId = selectSqlValue($db, $sql, [ ':patientId' => $patientId, ':preventTypeId' => $preventTypeId, ':onDate' => $onDate ]); if (empty($preventId)) { throw new Exception("Не удалось определить группу профилактики пациента!"); } $sql = <<<EOT
            SELECT
                MPD.UniDictCodeResearch     "uniDictIdResearch",
                MS.MedStudyCode             "studyId",
                MS.Type                     "type"
            FROM MedPreventDirection MPD
            JOIN MedResearch MR ON MR.UniDictCode = mpd.UniDictCodeResearch
            JOIN MedResearchRow MRR ON MRR.MedResearchCode = MR.MedResearchCode
            JOIN MedStudy MS ON MS.MedStudyCode = MRR.MedStudyCode
            WHERE MPD.MedPreventCode = :preventId
                AND MPD.MedProfessionCode IS NULL
                AND MPD.UniDictCodeProfessionRW IS NULL
                AND MPD.UniDictCodeResearch IS NOT NULL
                AND COALESCE(MPD.PeriodMonth, -1) <= (
                    DATEDIFF(
                        'Month',
                        COALESCE((
                            SELECT MAX(MIR.ResearchDate)
                            FROM MedInspectResearch MIR
                            JOIN MedInspect MI ON MI.MedInspectCode = MIR.MedInspectCode
                                AND MI.MedPatientCode = :patientId
                            JOIN MedPrevent P ON P.MedPreventCode = MI.MedPreventCode
                            WHERE COALESCE(MIR.UniDictCodeResearch,0) = MPD.UniDictCodeResearch
                                AND MIR.ResearchDate IS NOT NULL), 19000101
                        ),
                        :onDate
                    )
                )
EOT;
$studies = selectSql($db, $sql, [ ':preventId' => $preventId, ':patientId' => $patientId, ':onDate' => $onDate ]); if (empty($studies)) { throw new Exception("Диагностические исследования для группы профилактики не заданы!"); } $sql = <<<EOT
            SELECT
                MAX(MLS.medLogStudyCode)    "logStudyId",
                MLS.MedStudyCode            "studyId"
            FROM MedLogStudy MLS
            WHERE MLS.MedPatientCode = :patientId
                AND MLS.DirTime::DATE = :onDate
            GROUP BY "studyId"
EOT;
$sac = selectSql($db, $sql, [ ':patientId' => $patientId, ':onDate' => $onDate ]); $studiesAlreadyCreated = []; foreach ($sac as $study) { $studiesAlreadyCreated[$study['studyId']] = $study['logStudyId']; } $sql = <<<EOT
            SELECT
                MP.NumberHistorySickness        "numberHistorySickness",
                FN_cmFIO(MP.ManCode)            "patientName",
                MPASS.DateOfBirth               "patientBirthDate",
                FN_cmCalcAddress(MP.ManCode)    "patientAddress",
                MPASS.Sex                       "patientGender",
                MD.DepartmentCode               "doctorDepartmentId",
                CASE ASA_TRIM(COALESCE(D.FullName,  E'')) = E''
                    WHEN true THEN ASA_TRIM(COALESCE(D.Name, E''))
                    WHEN false THEN ASA_TRIM(COALESCE(D.FullName, E''))
                END                             "doctorDepartmentName"
            FROM MedPatient MP
            JOIN medDoctor MD ON MD.medDoctorCode = :doctorId
            JOIN ManPassport MPASS ON MPASS.manCode = MP.ManCode
            LEFT JOIN Department D ON D.DepartmentCode = MD.DepartmentCode
            WHERE medPatientCode = :patientId
EOT;
$pdInfo = selectSql($db, $sql, [ ':doctorId' => $doctorId, ':patientId' => $patientId ])[0]; $sql = <<<EOT
            INSERT INTO MedLogStudy (
                MedPatientCode, DirTime, MedDoctorCode,
                DepartmentCode, MedStudyCode, HtmPageCode,
                State, Type, DirNumber,
                NumberHistorySickness, PatientFio, PatientDateOfBirth,
                PatientAddress, PatientSex, Department
            )
            SELECT
                :patientId, :onDate, :doctorId,
                :doctorDepartmentId, :studyId, null,
                1, :type, ASA_TRIM(
                                INCSTR(
                                    COALESCE((
                                        SELECT REPEAT(E' ', 20 - LENGTH(ASA_TRIM(DirNumber))) || ASA_TRIM(DirNumber)
                                        FROM MedLogStudy
                                        WHERE MedDoctorCode = :doctorId
                                            AND MedStudyCode = :studyId
                                            AND YEAR(DirTime) = YEAR((LOCALTIMESTAMP))
                                        ORDER BY 1 desc
                                        LIMIT 1
                                    ), E'0')
                                )
                            ),
                :numberHistorySickness, :patientFio, :patientDateOfBirth,
                :patientAddress, :patientGender, :doctorDepartmentName
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($studies as $study) { if (!array_key_exists($study['studyId'], $studiesAlreadyCreated)) { $stmt->execute([ ':patientId' => $patientId, ':onDate' => $onDate, ':doctorId' => $doctorId, ':doctorDepartmentId' => $pdInfo['doctorDepartmentId'], ':studyId' => $study['studyId'], ':type' => $study['type'], ':numberHistorySickness' => $pdInfo['numberHistorySickness'], ':patientFio' => $pdInfo['patientName'], ':patientDateOfBirth' => $pdInfo['patientBirthDate'], ':patientAddress' => $pdInfo['patientAddress'], ':patientGender' => $pdInfo['patientGender'], ':doctorDepartmentName' => $pdInfo['doctorDepartmentName'] ]); $ret[] = (int)$db->lastInsertId('seq_MedLogStudy'); } else { $ret[] = (int)$studiesAlreadyCreated[$study['studyId']]; } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function selectStudies($params = null) { $db = $this->db ?: $this->db = connectDb(); $inMedStudy = createSqlInCondition(isEmptyProperty($params, 'studies', 0)); $sql = <<<"EOT"
select
        MS.MedStudyCode "id",
        MS.Name "name"
from MedStudy MS
where MS.MedStudyCode $inMedStudy
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function sendToSolis($id) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
SELECT 1
FROM MedLogStudy S
JOIN MedLabStudy L ON L.MedStudyCode = S.MedStudyCode
    AND L.Active = 1 AND L.ElisType is null
WHERE S.MedLogStudyCode = ? AND S.Type = 1 AND S.BiopsySyncStatus = 1
EOT;
$res = (int) selectSqlValue( $db, $sql, [(int) $id] ); if ($res != 1) { return true; } $link = new med_lab_linkLis(); return $link->sendDirectionOne(getSessionValue('config'), (int) $id); } private function getFindResearchSql($studyId, $longBarcode) { $longBarcode = $longBarcode ? ', FN_barCode_MakeLogStudy(mls.medLogStudyCode) as "longBarcode"' : ''; return <<<"EOT"
select
    mls.medLogStudyCode as "id",
    ms.name as "name",
    FN_cmFIO(mp.manCode) as "fio",
    mls.barcode as "barcode",
    mls.state as "state",
    mls.htmPageCode as "pageId",
    mls_l.barcodeType as "barcodeType",
    mls.barcode || dateformat(mls.dirTime, ', dd.mm.yyyy') || ' - ' || FN_cmFIO(mp.manCode, 1) as "research"
    $longBarcode
from MedLogStudy as mls
join MedStudy as ms on ms.medStudyCode = mls.medStudyCode
join MedPatient as mp on mp.medPatientCode = mls.medPatientCode
left join MedLabStudy as mls_l on mls_l.medStudyCode = mls.medStudyCode
where mls.medLogStudyCode = $studyId
  and MLS.htmPageCode is not null
limit 1
EOT;
} public function findResearch($params) { $db = $this->db ?: $this->db = connectDb(); $barcode = isUndefinedProperty($params, 'barcode', ''); if (!empty($barcode) && safe_strlen($barcode) === 16) { $studyId = substr($barcode, 7, 8); $res = selectSql($db, $this->getFindResearchSql($studyId, true)); if (!empty($res) && $res[0]['longBarcode'] == $barcode) { unset($res[0]['longBarcode']); return $res; } } $barcode = ltrim(isUndefinedProperty($params, 'barcode', ''), '0'); $cipher = ltrim(substr($this->getEnterpriseCipher(), -3), '0'); if (safe_strpos($barcode, $cipher) === 0) { $studyId = substr($barcode, safe_strlen($cipher)); } else { $studyId = $barcode; } if (empty($studyId)) { return null; } return selectSql($db, $this->getFindResearchSql($studyId, false)); } private function getEnterpriseCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select cipher
from Enterprise
where enterpriseCode = ?
EOT;
$ret = selectSqlValue($db, $sql, [ getSessionValue('mapEnterpriseCode') ]); return empty($ret) ? 0 : $ret; } public function getSolisMarkSQLFields($params = null) { return <<<"EOT"
CASE MLS.Type
     WHEN 1 THEN CASE
                      WHEN COALESCE(MLS.BiopsySyncStatus, 0) in (2,3,4) THEN 1 /*Green*/
                      WHEN (COALESCE(MLS.BiopsySyncStatus, 0) = 5) and
                           (COALESCE(TRIM(MLS.SyncResult), '') = '409') THEN 2 /*Red*/
                      WHEN ((COALESCE(TRIM(MLS.SyncResult), '') <> '200' or
                             COALESCE(TRIM(MLS.SyncResult), '') <> '409') and COALESCE(TRIM(MLS.SyncResult), '') <> '') or
                            COALESCE(MLS.BiopsySyncStatus, 0) = 1 THEN 3 /*Yellow*/
                      ELSE 0 /*White*/
                 END
END  as "solisMarkColor",
CASE MLS.Type
     WHEN 1 THEN CASE WHEN COALESCE(MLS.BiopsySyncStatus, 0) in (2,3,4) THEN
                                to_char(MLS.SendTime,'DD.MM.YYYY HH24:MI:SS') /*Green*/
                      WHEN (COALESCE(MLS.BiopsySyncStatus, 0) = 5) and
                           (COALESCE(TRIM(MLS.SyncResult), '') = '409') THEN
                                to_char(MLS.AnswerTime,'DD.MM.YYYY HH24:MI:SS') || ' - ' ||  MLS.SyncErrorMessage /*Red*/
                      WHEN ((COALESCE(TRIM(MLS.SyncResult), '') <> '200' or
                             COALESCE(TRIM(MLS.SyncResult), '') <> '409') and COALESCE(TRIM(MLS.SyncResult), '') <> '') or
                            COALESCE(MLS.BiopsySyncStatus, 0) = 1 THEN MLS.SyncErrorMessage /*Yellow*/
                      ELSE '' /*White*/
                 END
END as "solisMarkHint",
CASE MLS.Type
     WHEN 1 THEN COALESCE(MLS.BiopsySyncStatus, 0)
     ELSE null
END as "biopsySyncStatus",
CASE MLS.Type
     WHEN 1 THEN MLS.RawResult
     ELSE null
END as "rawResult"
EOT;
} public function getSolisMarkStatus($params) { $db = $this->db ?: $this->db = connectDb(); $ids = isUndefinedProperty($params, 'ids', 0); $fields = $this->getSolisMarkSQLFields(); $sql = <<<"EOT"
select
       MLS.medLogStudyCode "id",
       {$fields}
from MedLogStudy MLS
where MLS.medLogStudyCode in ({$ids})
EOT;
return selectSql($db, $sql); } } handleJsonRpc('med_lab_studies');