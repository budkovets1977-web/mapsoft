<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/common/periodLock.php'); resumeJsonRpc(); class med_clinicstats_visits { private $db; public function readVisits($params) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $limit = isEmptyProperty($params, 'limit', 25); $offset = isEmptyProperty($params, 'start', 0); $currId = isEmptyProperty($params, 'currId'); $startDateSql = ""; $finishDateSql = ""; $doctorIdSql = ""; $orderSql = ""; $limitSql = ""; $currIdSql = ""; $fieldFailRecord = ""; if (empty($currId)) { $startDate = isEmptyProperty($params, 'startDate'); empty($startDate) ? : $startDateSql = "and V.VisitDate >= " . date("Ymd", safe_strtotime($startDate)); $finishDate = isEmptyProperty($params, 'finishDate'); empty($finishDate) ? : $finishDateSql = "and V.VisitDate <= " . date("Ymd", safe_strtotime($finishDate)); !isEmptyProperty($params, 'doctorId') ? : $doctorIdSql = "and V.medDoctorCode = {$params->doctorId}"; $orderSql = "order by \"visitDate\" DESC"; $limitSql = "limit $limit offset $offset"; } else { $currIdSql = "and V.MedVisitCode = $currId"; $fieldFailRecord = ", -1 \"failRecord\""; } $sqlFromWhere = <<<"EOT"
from Branch B
join Department D on D.BranchCode = B.BranchCode
    and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
join medVisit V on V.DepartmentCode = D.DepartmentCode
join medDoctor Dr on Dr.medDoctorCode = V.medDoctorCode
left join Man M on M.ManCode = Dr.ManCode
left join medProfession P on P.medProfessionCode = V.MedProfessionCode
where B.EnterpriseCode = $enterpriseId
  $currIdSql
  $startDateSql
  $finishDateSql
  $doctorIdSql
EOT;
if (empty($currId)) { $sql = <<<"EOT"
select
       COUNT(*) "total"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; } $sql = <<<"EOT"
select
       V.medVisitCode "id",
       V.VisitDate "visitDate",
       V.medDoctorCode "doctorId",
       asa_trim(M.SurName || ' ' || M.Name || ' ' || M.SecondName) "doctorFio",
       D.Name || '-' || B.Name "departmentName",
       P.Name "professionName",
       COALESCE((select COUNT(*)
        from medVisitPatient
        where medVisitCode = V.medVisitCode
          /*and COALESCE(medDiagnosisCode, 0) <> 0*/
          and COALESCE(attendantDiagnosis, 0) = 0
          and COALESCE(visitMarkNew, 0) <> 0), 0) "visitsCount",
       COALESCE((select COUNT(*)
        from medVisitPatient
        where medVisitCode = V.medVisitCode
          /*and COALESCE(medDiagnosisCode, 0) <> 0*/
          and COALESCE(visitMarkNew, 0) <> 0), 0) "casesCount"
       $fieldFailRecord
$sqlFromWhere
$orderSql
$limitSql
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (empty($currId)) { $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } return $stmt->fetch(); } public function readVisitPatients($params) { $db = $this->db ? : $this->db = connectDb(); $limit = isEmptyProperty($params, 'limit', 25); $offset = isEmptyProperty($params, 'start', 0); $taskDate = date('Ymd'); $visitId = isEmptyProperty($params, 'visitId', 0); $currId = isEmptyProperty($params, 'currId'); if (empty($currId)) { $sql = <<<"EOT"
with __VISITS as (
select
       MVP.medVisitpatientCode,
       MVP.medPatientCode,
       MVP.medDiagnosisCode,
       COALESCE(MVP.visitPlace, 0) "visitPlace",
       COALESCE(MVP.serviceForm, 0) "serviceForm",
       COALESCE(MVP.visitMarkNew, 0) "visitMarkNew",
       COALESCE(MVP.VisitCaseNew, 0) "visitCaseNew",
       COALESCE(MVP.attendantDiagnosis, 0) "attendantDiagnosis",
       MVP.MedPhantomCode
from medVisitPatient MVP
where MVP.MedVisitCode = $visitId
  and MVP.medVisitPatientCodeParent is null
)

SELECT COUNT(*) "total"
FROM (
    SELECT VP.MedVisitPatientCode
    FROM  __VISITS VP
        JOIN LATERAL (
            SELECT
                    VP_.MedVisitPatientCode
            FROM __VISITS VP_
            WHERE VP_.MedPatientCode = VP.MedPatientCode
                AND VP_."visitPlace" = VP."visitPlace"
                AND VP_."serviceForm" = VP."serviceForm"
                AND VP_."visitMarkNew" = VP."visitMarkNew"
                AND VP_."visitCaseNew" = VP."visitCaseNew"
            ORDER BY VP_."attendantDiagnosis", VP_.MedVisitPatientCode, VP_.MedDiagnosisCode DESC
            LIMIT 1
        ) gVisitId ON TRUE
    WHERE (VP.MedVisitPatientCode = gVisitId.MedVisitPatientCode)
      AND (VP.MedPhantomCode IS NULL)

    UNION

    /* групповые визиты */
    SELECT
        VPg.MedVisitPatientCode
    FROM
        __VISITS VPg
    WHERE
        VPg.MedPhantomCode IS NOT NULL
)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = fetchValue($stmt, 'total'); $total >= $offset ? : $offset = 0; } $whereVisitIdSql = "MVP.MedVisitCode = $visitId"; $limitSql = $currId ? "" : "limit $limit offset $offset"; $whereCurr = $currId ? "where visits.\"id\" = $currId" : ""; if (isset($params->sort) && $params->dir !== "") { $orderSql = "order by $params->sort $params->dir"; } else { $orderSql = $currId ? "" : "order by \"time\",\"id\" desc"; } $sql = <<<"EOT"
with __VISITS as (
select /* все визиты пациентов*/
       MVP.medVisitpatientCode,
       MVP.medPatientCode,
       MVP.medVisitCode,
       MVP.medDiagnosisCode,
       CASE
            WHEN COALESCE(MVP.Attributes, 0) & 1 = 1
            THEN 4
            ELSE COALESCE(MVP.visitPlace, 0)
       END                         "visitPlace", /*место*/
       COALESCE(MVP.serviceForm, 0) "serviceForm", /*форма обслуживания*/
       COALESCE(MVP.visitMarkNew, 0) "visitMarkNew", /*цель посещения*/
       COALESCE(MVP.VisitCaseNew, 0) "visitCaseNew", /*посещение*/
       COALESCE(MVP.attendantDiagnosis, 0) "attendantDiagnosis", /*флаг доп. диагноза*/
       (MVP.MedPhantomCode IS NOT NULL) "isGroupVisit",
       MVP.MedPhantomCode
from medVisitPatient MVP
where $whereVisitIdSql
  and MVP.medVisitPatientCodeParent is null
), __VISITS_GENERAL as (
select /* все главные записи визитов пациентов*/
       VP.medVisitpatientCode "id",
       FALSE "isGroupVisit",
       VP.medPatientCode,
       FN_cmFIO(MP.ManCode) "patientFio",
       NULL "groupName",
       VP.medDiagnosisCode "medDiagnosisCode",
       VP."visitPlace",
       VP."serviceForm",
       VP."visitMarkNew",
       VP."visitCaseNew",
       VP."attendantDiagnosis",
       1 "isGeneral",
       false "isFake",
       0 "excludingVisit",
       null "parentId",
       (SELECT  visitTime from medcoupon as MC
                left join medVisit as medV on VP.medVisitCode = medV.medVisitCode
                where medV.meddoctorcode = MC.meddoctorcode AND MC.medPatientCode = VP.medPatientCode AND MC.onDate = medV.visitDate limit 1) "time",
       FN_cmFIO(MP.ManCode) "sortName"
from  __VISITS VP
    left join MedPatient as MP ON MP.medPatientCode = VP.medPatientCode
    join lateral (select
                         VP_.MedvisitPatientCode
                  from __VISITS VP_
                  WHERE VP_.MedPatientCode = VP.MedPatientCode
                    and VP_."visitPlace" = VP."visitPlace"
                    and VP_."serviceForm" = VP."serviceForm"
                    and VP_."visitMarkNew" = VP."visitMarkNew"
                    and VP_."visitCaseNew" = VP."visitCaseNew"
                    and COALESCE(VP_.MedDiagnosisCode, 0) = COALESCE(VP.MedDiagnosisCode,0)
                    and VP_."attendantDiagnosis" = 0
                  order by VP_.MedVisitPatientCode, VP_.MedDiagnosisCode desc
                  limit 1) gVisitId on true
WHERE (VP.MedVisitPatientCode = gVisitId.MedvisitPatientCode)
  AND (VP.MedPhantomCode IS NULL)
union
select
       (VPr.medVisitpatientCode*(-1)) "id",
       FALSE "isGroupVisit",
       VPr.medPatientCode,
       FN_cmFIO(MPr.ManCode) "patientFio",
       NULL "groupName",
       null "medDiagnosisCode",
       VPr."visitPlace",
       VPr."serviceForm",
       VPr."visitMarkNew",
       VPr."visitCaseNew",
       VPr."attendantDiagnosis",
       1 "isGeneral",
       true "isFake",
       1 "excludingVisit",
       null "parentId",
       (SELECT  visitTime from medcoupon as MC
                left join medVisit as medV on VPr.medVisitCode = medV.medVisitCode
                where medV.meddoctorcode = MC.meddoctorcode AND MC.medPatientCode = VPr.medPatientCode AND MC.onDate = medV.visitDate limit 1) "time",
       FN_cmFIO(MPr.ManCode) "sortName"
from  __VISITS VPr
left join MedPatient as MPr ON MPr.medPatientCode = VPr.medPatientCode
join lateral (select
                         VP_.MedvisitPatientCode
                  from __VISITS VP_
                  WHERE VP_.MedPatientCode = VPr.MedPatientCode
                    and VP_."visitPlace" = VPr."visitPlace"
                    and VP_."serviceForm" = VPr."serviceForm"
                    and VP_."visitMarkNew" = VPr."visitMarkNew"
                    and VP_."visitCaseNew" = VPr."visitCaseNew"
                    and VP_."attendantDiagnosis" = 1
                  order by VP_.MedVisitPatientCode, VP_.MedDiagnosisCode desc
                  limit 1) gVisitId on true
WHERE (VPr.MedVisitPatientCode = gVisitId.MedvisitPatientCode)
AND (VPr.MedPhantomCode IS NULL)
AND not EXISTS (select
                         VP_.MedvisitPatientCode
                  from __VISITS VP_
                  WHERE VP_.MedPatientCode = VPr.MedPatientCode
                    and VP_."visitPlace" = VPr."visitPlace"
                    and VP_."serviceForm" = VPr."serviceForm"
                    and VP_."visitMarkNew" = VPr."visitMarkNew"
                    and VP_."visitCaseNew" = VPr."visitCaseNew"
                    and VP_."attendantDiagnosis" = 0
                  order by VP_.MedVisitPatientCode, VP_.MedDiagnosisCode desc
                  limit 1)

/* все записи групповых визитов */
UNION
SELECT
       VPg.MedVisitPatientCode "id",
       TRUE "isGroupVisit",
       VPg.MedPatientCode "medPatientCode",
       NULL "patientFio",
       MG.Name "groupName",
       VPg.MedDiagnosisCode "medDiagnosisCode",
       VPg."visitPlace",
       VPg."serviceForm",
       VPg."visitMarkNew",
       VPg."visitCaseNew",
       VPg."attendantDiagnosis",
       1 "isGeneral",
       FALSE "isFake",
       0 "excludingVisit",
       NULL "parentId",
       NULL "time",
       MG.Name "sortName"
FROM  __VISITS VPg
    LEFT JOIN MedPhantom AS MG ON MG.MedPhantomCode = VPg.MedPhantomCode
WHERE VPg.MedPhantomCode IS NOT NULL

$orderSql
$limitSql
)

select *,
    CASE WHEN visits."isGroupVisit" THEN visits."groupName"
         ELSE visits."patientFio"
         END "sortName"
from (select /*достаем главные записи посещений*/
             VG."id" as "id",
             MVP.medVisitCode "visitId",
             MVP.medPatientCode "patientId",
             MP.NumberHistorySickness "cardNumber",
             FN_cmFIO(MP.ManCode) "patientFio",
             MPASS.DateOfBirth "dateOfBirth",
             FN_wgCalcStage(2, $taskDate, MPASS.DateOfBirth, 0, 0, 0, null) "age",
             VG."medDiagnosisCode" "diagnosisId",
             D.Name "diagnosisName",
             D.Cipher1 "diagnosisCipher",
             D.DiagnosisType "diagnosisType",
             VG."visitPlace",
             VG."visitMarkNew",
             VG."visitCaseNew",
             VG."serviceForm",
             COALESCE(MVP.visitFirst, 0) "visitFirst",
             CASE
                WHEN COALESCE(MVP.Attributes, 0) & 2 = 2
                THEN 5
                ELSE COALESCE(MVP.visitEndNew, 0)
             END                  "visitEndNew",
             VISIT_ZONE.VisitZone "visitZone",
             MVP.VisitHospital "visitHospital",
             MVP.VisitDispensary "visitDispensary",
             MVP.medPatientGroupCode "patientGroupId",
             FN_cmCalcAddress(MP.ManCode, 5, 0, 1) "address",
             /*MVP.Pressure IS NOT NULL and MVP.Pressure <> '' and MVP.Pressure <> ' ' "pressureCheck",*/
             regexp_replace(('0' || asa_substr(MVP.Pressure, 1, 3)), '[^\d]', '', 'g')::integer "pressureMax",
             regexp_replace(('0' || asa_substr(MVP.Pressure, 5, 3)), '[^\d]', '', 'g')::integer "pressureMin",
             MVP.EnterpriseCodeDirected "enterpriseCodeDirected",
             E.Name "enterpriseDirectedName",
             MVP.VisitCase "visitCase",
             CASE WHEN VG."isGroupVisit" THEN MG.Sex
                  ELSE MPASS.Sex
                  END "sex",
             MVP.MedTraumaCode "traumaId",
             T.Code "traumaCode",
             T.Name "traumaName",
             MVP.SicknessKind "sicknessKind",
             VISIT_ZONE.Name "visitNameDep",
             COALESCE(MP.FinishDate, 19000101) "finishDate",
             MP.OutReason "outReason",
             VG."attendantDiagnosis",
             1 "isGeneral",
             null "parentId",
             VG."isFake",
             VG."excludingVisit",
             D.treePath "treePath",
             MVP.medLogHospitalCode "hospitalId",
            (SELECT  visitTime from medcoupon as MC
                left join medVisit as medV on MVP.medVisitCode = medV.medVisitCode
                where medV.meddoctorcode = MC.meddoctorcode AND MC.medPatientCode = MVP.medPatientCode AND MC.onDate = medV.visitDate limit 1) "time",
             MVPE.ClinicalDiagnosis "clinicalDiagnosis",
             MVPE.CiszResourceValueCodeDiagKind "kindDiagId",
             CRV.Name "kindDiagName",
             MVPE.CiszResourceValueCodeCancerStage "cancerStageId",
             VG."isGroupVisit",
             MVP.MedPhantomCode "phantomCode",
             MVP.VisitCount "visitCount",
             MG.Cipher "groupCipher",
             MG.Name "groupName",
             MG.AgeFrom "groupAgeFrom",
             MG.AgeTo "groupAgeTo"
      from  __VISITS_GENERAL VG
            join medVisitPatient MVP on MVP.medVisitpatientCode = @VG."id"
            LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
            LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MVPE.CiszResourceValueCodeDiagKind
            LEFT JOIN MedPatient MP ON MP.MedPatientCode = MVP.MedPatientCode
            LEFT JOIN MedPhantom MG ON MG.MedPhantomCode = MVP.MedPhantomCode
            left join medDiagnosis D on D.medDiagnosisCode = VG."medDiagnosisCode"
            left join medTrauma T on T.medTraumaCode = MVP.MedTraumaCode
            left join ManPassport MPASS on MPASS.ManCode = MP.ManCode
            left join Enterprise E on E.EnterpriseCode = MVP.EnterpriseCodeDirected
            left join lateral (select
                                      _VISIT_ZONE.VisitZone,-- "visitZone",
                                      _VISIT_ZONE.MedPatientGroupCode,
                                      _PGN.Name
                               from (select
                                            _T.MedPatientGroupCode,
                                            _T.VisitZone
                                     from (select
                                                  _AG.MedPatientGroupCode,
                                                  _PG.VisitZone
                                           from MedPatientAddGroup _AG
                                                left join medPatientGroup _PG on _PG.medPatientGroupCode = _AG.MedPatientGroupCode
                                           where _AG.MedPatientCode = MVP.medPatientCode
                                           union
                                           select
                                                  _MP.MedPatientGroupCode,
                                                  _PG.VisitZone
                                           from MedPatient _MP
                                                left join medPatientGroup _PG on _PG.medPatientGroupCode = _MP.MedPatientGroupCode
                                           where _MP.MedPatientCode = MVP.medPatientCode
                                           UNION
                                           SELECT
                                                  _MG.MedPatientGroupCode,
                                                  _PG.VisitZone
                                           FROM MedPhantom _MG
                                                LEFT JOIN MedPatientGroup _PG ON _PG.MedPatientGroupCode = _MG.MedPatientGroupCode
                                           WHERE _MG.MedPhantomCode = MVP.MedPhantomCode
                                           order by 2 desc, 1 desc) AS _T
                                     limit 1) _VISIT_ZONE
                                    left join medPatientGroup _PGN on _PGN.medPatientGroupCode = _VISIT_ZONE.MedPatientGroupCode) VISIT_ZONE on true
      union
      select /*достаем записи доп. диагнозов по посещениям*/
             MVP.medVisitPatientCode "id",
             MVP.medVisitCode "visitId",
             MVP.medPatientCode "patientId",
             null "cardNumber",
             null "patientFio",
             null "dateOfBirth",
             null "age",
             MVP.medDiagnosisCode "diagnosisId",
             D.Name "diagnosisName",
             D.Cipher1 "diagnosisCipher",
             D.DiagnosisType "diagnosisType",
             null "visitPlace",
             null "visitMarkNew",
             null "visitCaseNew",
             null "serviceForm",
             COALESCE(MVP.visitFirst, 0) "visitFirst",
             CASE
                WHEN COALESCE(MVP.Attributes, 0) & 2 = 2
                THEN 5
                ELSE COALESCE(MVP.visitEndNew, 0)
             END               "visitEndNew",
             null "visitZone",
             MVP.VisitHospital "visitHospital",
             MVP.VisitDispensary "visitDispensary",
             null "patientGroupId",
             null "address",
             /*null "pressureCheck",*/
             null "pressureMax",
             null "pressureMin",
             MVP.EnterpriseCodeDirected "enterpriseCodeDirected",
             null "enterpriseDirectedName",
             MVP.VisitCase "visitCase",
             null "sex",
             MVP.MedTraumaCode "traumaId",
             T.Code "traumaCode",
             T.Name "traumaName",
             MVP.SicknessKind "sicknessKind",
             null "visitNameDep",
             null "finishDate",
             null "outReason",
             VP."attendantDiagnosis",
             0 "isGeneral",
             gVisitId."id" as "parentId",
             null "isFake",
             null "excludingVisit",
             D.treePath "treePath",
             MVP.medLogHospitalCode "hospitalId",
             null "time",
             MVPE.ClinicalDiagnosis "clinicalDiagnosis",
             MVPE.CiszResourceValueCodeDiagKind "kindDiagId",
             CRV.Name "kindDiagName",
             MVPE.CiszResourceValueCodeCancerStage "cancerStageId",
             false "isGroupVisit",
             MVP.MedPhantomCode "phantomCode",
             MVP.VisitCount "visitCount",
             null "groupCipher",
             null "groupName",
             null "groupAgeFrom",
             null "groupAgeTo"
      from __VISITS VP
           join lateral (select
                                     VG."id"
                              from __VISITS_GENERAL VG
                              where VG.medPatientCode = VP.medPatientCode
                                and VG."visitPlace" = VP."visitPlace"
                                and VG."serviceForm" = VP."serviceForm"
                                and VG."visitMarkNew" = VP."visitMarkNew"
                                and VG."visitCaseNew" = VP."visitCaseNew"
                              order by VG."id", VG."medDiagnosisCode" desc
                              limit 1) gVisitId on true
           join medVisitPatient MVP on MVP.medVisitpatientCode = VP.medVisitpatientCode
           LEFT JOIN MedVisitPatientEx MVPE ON MVPE.MedVisitPatientCode = MVP.MedVisitPatientCode
           LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MVPE.CiszResourceValueCodeDiagKind
           left join medDiagnosis D on D.medDiagnosisCode = MVP.medDiagnosisCode
           left join medTrauma T on T.medTraumaCode = MVP.MedTraumaCode
      where VP."attendantDiagnosis" = 1
        and (VP.medVisitPatientCode <> gVisitId."id")
) visits
$orderSql
$whereCurr
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (empty($currId)) { $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } return $stmt->fetch(); } private function createVisit($record) { $db = $this->db ? : $this->db = connectDb(); $doctorId = isEmptyProperty($record, '_doctorId'); $departmentId = isEmptyProperty($record, '_departmentId'); $professionId = isEmptyProperty($record, '_professionId'); $visitDate = isEmptyProperty($record, '_visitDate'); $sqlCheck = <<<"EOT"
select medVisitCode "id"
from medVisit
where VisitDate = ?
  and MedDoctorCode = ?
  and DepartmentCode = ?
  and MedProfessionCode = ?
limit 1
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute([ $visitDate, $doctorId, $departmentId, $professionId ]); $visitId = fetchValue($stmtCheck, 'id'); if (empty($visitId)) { $sql = <<<"EOT"
insert into MedVisit
    (VisitDate, MedDoctorCode, DepartmentCode, MedProfessionCode)
values
    (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute([ $visitDate, $doctorId, $departmentId, $professionId ]); $param = new stdClass(); $param->currId = $db->lastInsertId('seq_MedVisit'); $param = $this->readVisits($param); return $param; } catch (Throwable $e) { throw $e; } } return $visitId; } public function createVisits($record) { $db = $this->db ? : $this->db = connectDb(); $doctorId = isEmptyProperty($record, 'doctorId', 0); $departmentId = isEmptyProperty($record, 'departmentId', 0); $professionId = isEmptyProperty($record, 'professionId', 0); $visitDate = isEmptyProperty($record, 'visitDate'); $sqlCheck = <<<"EOT"
select medVisitCode "id"
from medVisit
where VisitDate = ?
  and MedDoctorCode = ?
  and DepartmentCode = ?
  and MedProfessionCode = ?
limit 1
EOT;
$stmtCheck = $db->prepare($sqlCheck); $stmtCheck->execute([ $visitDate, $doctorId, $departmentId, $professionId ]); $visitId = fetchValue($stmtCheck, 'id'); if (empty($visitId)) { $sql = <<<"EOT"
insert into MedVisit
    (VisitDate, MedDoctorCode, DepartmentCode, MedProfessionCode)
values
    (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); try { $stmt->execute([ $visitDate, $doctorId, $departmentId, $professionId ]); $param = new stdClass(); $param->currId = $db->lastInsertId('seq_MedVisit'); $ret = new stdClass(); $ret->items = [$this->readVisits($param)]; return $ret; } catch (Throwable $e) { throw $e; } } throw new Exception('Данная ведомость уже внесена!'); } public function updateVisits($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update MedVisit set
    VisitDate = case when ? = 1 then ? else VisitDate end,
    MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
    DepartmentCode = case when ? = 1 then ? else DepartmentCode end,
    MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end
where MedVisitCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'visitDate'), isEmptyProperty($row, 'visitDate'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), $row->id ]); $params = new stdClass(); $params->id = $row->id; $ret[] = $this->readVisits($params); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteVisits($records) { $db = $this->db ? : $this->db = connectDb(); try { return deleteSql($db, 'MedVisit', 'MedVisitCode', $records); } catch (Throwable $e) { if ($e->getCode() === "30001") { $lockPeriod = new map_common_periodLock(); $lockDate = safe_date_create($lockPeriod->getVisitLockDate()); throw new Exception("Нельзя удалить запись в закрытом периоде по " . date_format($lockDate, "d.m.Y") . "!"); } throw $e; } } public function createVisitPatients($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
insert into MedVisitPatient
    (MedVisitCode, MedPatientCode, MedDiagnosisCode, VisitPlace, VisitMarkNew, VisitEndNew, VisitZone,  AttendantDiagnosis,
        Pressure, VisitCase, EnterpriseCodeDirected, MedTraumaCode, ServiceForm, SicknessKind, VisitCaseNew, Attributes,
        MedPhantomCode, VisitCount)
values
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $sqlEx = <<<"EOT"
INSERT INTO MedVisitPatientEx
    (MedVisitPatientCode, ClinicalDiagnosis, CiszResourceValueCodeDiagKind, CiszResourceValueCodeCancerStage)
VALUES
    (?, ?, ?, ?)
EOT;
$stmtEx = $db->prepare($sqlEx); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $visitId = isEmptyProperty($row, 'visitId'); if (empty($visitId)) { $visitId = $this->createVisit($row); if (!is_scalar($visitId)) { $ret[] = $rec = $visitId; $visitId = $rec['id']; } } $bit = null; $visitPlace = isEmptyProperty($row, 'visitPlace'); if ($visitPlace === 4) { $visitPlace = null; $bit = 1; } $visitEndNew = isEmptyProperty($row, 'visitEndNew'); if ($visitEndNew === 5) { $visitEndNew = 4; $bit = $bit ? $bit + 2 : 2; } $stmt->execute([ $visitId, isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'diagnosisId'), $visitPlace, isEmptyProperty($row, 'visitMarkNew'), $visitEndNew, isEmptyProperty($row, 'visitZone'), isEmptyProperty($row, 'attendantDiagnosis'), isEmptyProperty($row, 'pressureMin') && isEmptyProperty($row, 'pressureMin') ? $this->addDigit(strval(isEmptyProperty($row, 'pressureMax'))) . '.' . $this->addDigit(strval(isEmptyProperty($row, 'pressureMin'))) : null, isEmptyProperty($row, 'visitCase'), isEmptyProperty($row, 'enterpriseCodeDirected'), isEmptyProperty($row, 'traumaId'), isEmptyProperty($row, 'serviceForm'), isEmptyProperty($row, 'sicknessKind'), isEmptyProperty($row, 'visitCaseNew'), $bit, isEmptyProperty($row, 'phantomCode'), isEmptyProperty($row, 'visitCount'), ]); $param = new stdClass(); $param->visitId = $visitId; $param->currId = $db->lastInsertId('seq_MedVisitPatient'); $stmtEx->execute([ $param->currId, isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'kindDiagId'), isEmptyProperty($row, 'cancerStageId') ]); $ret[] = $this->readVisitPatients($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateVisitPatients($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
 update MedVisitPatient set
    MedVisitCode = ?,
    MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end,
    VisitPlace = NULLIF(?, 0),
    VisitMarkNew = case when ? = 1 then ? else VisitMarkNew end,
    VisitEndNew = ?,
    VisitZone = case when ? = 1 then ? else VisitZone end,
    AttendantDiagnosis = case when ? = 1 then ? else AttendantDiagnosis end,
    Pressure = ?,
    VisitCase = case when ? = 1 then ? else VisitCase end,
    EnterpriseCodeDirected = case when ? = 1 then ? else EnterpriseCodeDirected end,
    MedTraumaCode = case when ? = 1 then ? else MedTraumaCode end,
    ServiceForm = case when ? = 1 then ? else ServiceForm end,
    SicknessKind = case when ? = 1 then ? else SicknessKind end,
    VisitCaseNew = case when ? = 1 then ? else VisitCaseNew end,
    MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
    Attributes = ?,
    MedPhantomCode = case when ? = 1 then ? else MedPhantomCode end,
    VisitCount = case when ? = 1 then ? else VisitCount end
where MedVisitPatientCode = ?
EOT;
$sqlCheckEx = "SELECT 1 FROM MedVisitPatientEx WHERE MedVisitPatientCode = ?"; $stmtCheckEx = $db->prepare($sqlCheckEx); $sqlUpdEx = <<<"EOT"
 UPDATE MedVisitPatientEx SET
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN ? ELSE ClinicalDiagnosis END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeDiagKind END,
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeCancerStage END
WHERE MedVisitPatientCode = ?
EOT;
$sqlInsEx = <<<"EOT"
INSERT INTO MedVisitPatientEx
    (MedVisitPatientCode, ClinicalDiagnosis, CiszResourceValueCodeDiagKind, CiszResourceValueCodeCancerStage)
VALUES
    (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmtUpdEx = $db->prepare($sqlUpdEx); $stmtInsEx = $db->prepare($sqlInsEx); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $visitId = $this->createVisit($row); if (!is_scalar($visitId)) { $ret[] = $rec = $visitId; $visitId = $rec['id']; } $bit = null; $visitPlace = isEmptyProperty($row, 'visitPlace'); if ($visitPlace === 4) { $visitPlace = null; $bit = 1; } $visitEndNew = isEmptyProperty($row, 'visitEndNew'); if ($visitEndNew === 5) { $visitEndNew = 4; $bit = $bit ? $bit + 2 : 2; } $stmt->execute([ $visitId, propertyExistsSQL($row, 'diagnosisId'), isEmptyProperty($row, 'diagnosisId'), $visitPlace, propertyExistsSQL($row, 'visitMarkNew'), isEmptyProperty($row, 'visitMarkNew'), $visitEndNew, propertyExistsSQL($row, 'visitZone'), isEmptyProperty($row, 'visitZone'), propertyExistsSQL($row, 'attendantDiagnosis'), isEmptyProperty($row, 'attendantDiagnosis'), propertyExistsSQL($row, 'pressureMin') && propertyExistsSQL($row, 'pressureMin') ? $this->addDigit(strval(isUndefinedProperty($row, 'pressureMax'))) . '.' . $this->addDigit(strval(isUndefinedProperty($row, 'pressureMin'))) : null, propertyExistsSQL($row, 'visitCase'), isEmptyProperty($row, 'visitCase'), propertyExistsSQL($row, 'enterpriseCodeDirected'), isEmptyProperty($row, 'enterpriseCodeDirected'), propertyExistsSQL($row, 'traumaId'), isEmptyProperty($row, 'traumaId'), propertyExistsSQL($row, 'serviceForm'), isEmptyProperty($row, 'serviceForm'), propertyExistsSQL($row, 'sicknessKind'), isEmptyProperty($row, 'sicknessKind'), propertyExistsSQL($row, 'visitCaseNew'), isEmptyProperty($row, 'visitCaseNew'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), $bit, propertyExistsSQL($row, 'phantomCode'), isEmptyProperty($row, 'phantomCode'), propertyExistsSQL($row, 'visitCount'), isEmptyProperty($row, 'visitCount'), $row->id, ]); $param = new stdClass(); $param->visitId = $visitId; $param->currId = $row->id; $stmtCheckEx->execute([$row->id]); $exists = $stmtCheckEx->fetchColumn(); if ($exists) { $stmtUpdEx->execute([ propertyExistsSQL($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'kindDiagId'), isEmptyProperty($row, 'kindDiagId'), propertyExistsSQL($row, 'cancerStageId'), isEmptyProperty($row, 'cancerStageId'), $row->id ]); } else { $stmtInsEx->execute([ $row->id, isEmptyProperty($row, 'clinicalDiagnosis'), isEmptyProperty($row, 'kindDiagId'), isEmptyProperty($row, 'cancerStageId') ]); } $ret[] = $this->readVisitPatients($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function addDigit($str) { while (safe_strlen($str) < 3) { $str = '0' . $str; } return $str; } private function getAdditional($record) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select ADD_VISITS.medVisitPatientCode "id"
from medVisitpatient MVP
     join lateral (select
                          _MVP.medVisitpatientCode
                   from medVisitPatient _MVP
                   where _MVP.medVisitCode = {$record->visitId}
                     and _MVP.attendantDiagnosis = 1
                     and _MVP.medVisitPatientCodeParent is null
                     and _MVP.medPatientCode = MVP.medPatientCode
                     and COALESCE(_MVP.visitPlace, 0) = COALESCE(MVP.visitPlace, 0)
                     and COALESCE(_MVP.serviceForm, 0) = COALESCE(MVP.serviceForm, 0)
                     and COALESCE(_MVP.visitMarkNew, 0) = COALESCE(MVP.visitMarkNew, 0)
                     and COALESCE(_MVP.visitCaseNew, 0) = COALESCE(MVP.visitCaseNew, 0)
     ) ADD_VISITS on true
where MVP.medVisitpatientCode = {$record->id}
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = []; foreach ($stmt->fetchAll() as $row) { $ret[] = (object) $row; } return $ret; } public function deleteVisitPatients($records) { $db = $this->db ? : $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $arr = []; try { foreach ($records as $row) { $arr[] = $row; if (isEmptyProperty($row, 'isGeneral')) { $add = $this->getAdditional($row); empty($add) ?: $arr = array_merge($arr, $add); } } $in = createSqlInCondition($arr); $sql = <<<"EOT"
delete from MedVisitPatient where MedVisitPatientCode $in
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); if ($e->getCode() === "30001") { $lockPeriod = new map_common_periodLock(); $lockDate = safe_date_create($lockPeriod->getVisitLockDate()); throw new Exception("Нельзя удалить запись в закрытом периоде по " . date_format($lockDate, "d.m.Y") . "!"); } throw $e; } return true; } public function getDiagnosisInfo($diagnosisId) { $db = $this->db ? : $this->db = connectDb(); $ret = new stdClass(); if ($diagnosisId) { $sqlType = <<<"EOT"
select
       DiagnosisType "type"
from medDiagnosis
where medDiagnosisCode = ?
EOT;
$stmtType = $db->prepare($sqlType); $stmtType->execute([ $diagnosisId ]); $ret->type = fetchValue($stmtType, 'type'); $ret->controlDays = $this->checkControlDays($diagnosisId); } return $ret; } public function checkControlDays($diganosisId) { $db = $this->db ? : $this->db = connectDb(); $defaultDays = 30; $sql = <<<"EOT"
select
       DTC.ControlDays "controlDays"
from MedDiagnosisTimeControl DTC
     join MedDiagnosis MD on MD.MedDiagnosisCode = DTC.MedDiagnosisCode
where DTC.MedDiagnosisCode = ?
   or EXISTS(select 1
             from MedDiagnosis _MD
             where _MD.MedDiagnosisCode = DTC.MedDiagnosisCode
               and _MD.TreePath >= (MD.TreePath || INTTOHEX(MD.MedDiagnosisCode))
               and _MD.TreePath < INCHEXSTR(MD.TreePath || INTTOHEX(MD.MedDiagnosisCode))
             limit 1)
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $diganosisId ]); $ret = fetchValue($stmt, 'controlDays'); return empty($ret) ? $defaultDays : $ret; } private function checkDiagnosis(&$visitDate, $params, $controlDays = null) { $db = $this->db ? : $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $vd = date("Ymd", safe_strtotime(isEmptyProperty($params, 'visitDate'))); if (empty($patientId) || empty($diagnosisId)) { return 0; } $fieldCurrYearSql = isset($controlDays) ? "" : ", CASE YEAR($visitDate) = YEAR(mv.Visitdate)" . "       WHEN true THEN 1" . "       WHEN false THEN 0" . "  END \"currYear\""; $whereVisitDateSql = isset($controlDays) ? "and MV.VisitDate between DATEADD('Day', -1 * $controlDays, $visitDate) and DATEADD('Day', $controlDays, $visitDate)" : ""; $whereDiagnosisTypeSql = isset($controlDays) ? "and MD.DiagnosisType = 2" : "and (MD.DiagnosisType = 1 or MD.DiagnosisType is null)"; $sql = <<<"EOT"
select
       TO_CHAR(MV.visitDate, 'YYYYMMDD') "visitDate"
       $fieldCurrYearSql
from MedVisit MV
     join medVisitPatient MVP on MV.medVisitCode = MVP.medVisitcode
     join medDiagnosis MD on MVP.medDiagnosisCode = MD.medDiagnosisCode
where MVP.medPatientCode = ?
  and MVP.medVisitPatientCode <> ?
  and MVP.visitCaseNew = 1
  and MVP.visitEndNew is not null and  MVP.visitEndNew <> 2
  and MVP.medDiagnosisCode = ?
  $whereDiagnosisTypeSql
  $whereVisitDateSql
order by MV.visitDate desc
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId, is_string(isEmptyProperty($params, 'visitPatientId', 0)) ? 0 : isEmptyProperty($params, 'visitPatientId', 0), $diagnosisId ]); $ret = $stmt->fetch(); if (empty($ret)) { return isset($controlDays) ? 1 : 3; } elseif (isset($ret['currYear']) && $ret['currYear'] == 0) { return 4; } else { $visitDate = $ret['visitDate']; return isset($controlDays) ? 2 : 5; } } private function checkClassDiagnosis($visitDate, $params, $controlDays = null) { $db = $this->db ? : $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $vd = date("Ymd", safe_strtotime(isEmptyProperty($params, 'visitDate'))); if (empty($patientId) || empty($diagnosisId)) { return 0; } $whereDateSql = isset($controlDays) ? "MV.VisitDate between DATEADD('Day', -1 * $controlDays, $visitDate) and DATEADD('Day', $controlDays, $visitDate)" : "MV.VisitDate <= $visitDate"; $whereDiagnosisTypeSql = isset($controlDays) ? "and MD.DiagnosisType = 2" : "and (MD.DiagnosisType = 1 or MD.DiagnosisType is null)"; $sql = <<<"EOT"
with PATH as (
select
       asa_substr(TreePath, 1, 16) "path"
from MedDiagnosis
where MedDiagnosisCode = ?
)
select
       (MD.Cipher1 || ' - ' || MD.Name)::text "diagnosisName",
       TO_CHAR(MAX(MV.VisitDate), 'YYYYMMDD') "visitDate"
from MedVisit MV
     join MedVisitPatient MVP on MV.MedVisitCode = MVP.MedVisitCode
     join MedDiagnosis MD on MVP.MedDiagnosisCode = MD.MedDiagnosisCode
where $whereDateSql
  and MVP.MedPatientCode = ?
  and MVP.MedVisitPatientCode <> ?
  and MVP.VisitCase = 1
  and MVP.VisitEnd IS NOT NULL
  and MV.VisitDate <> $vd -- исключаем из проверки за текущее посещение, т.к. оно проверяется на форме
  and asa_substr(MD.TreePath, 1, 16) = (select path from PATH)
  $whereDiagnosisTypeSql
group by MVP.MedDiagnosisCode, (MD.Cipher1 || ' - ' || MD.Name)::text
order by MAX(MV.VisitDate) desc
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $diagnosisId, $patientId, is_string(isEmptyProperty($params, 'visitPatientId', 0)) ? 0 : isEmptyProperty($params, 'visitPatientId', 0) ]); $ret = new stdClass(); $arr = []; foreach ($stmt->fetchAll() as $row) { $date = date("d.m.Y", safe_strtotime($row['visitDate'])); $arr[] = "$date : {$row['diagnosisName']}"; } if (!empty($arr)) { $ret->controlList = $arr; $ret->controlMsg = "У пациента уже зарегистрированы другие диагнозы того же класса"; } return $ret; } public function checkRegistration($params) { $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $visitDate = date("Ymd", safe_strtotime(isEmptyProperty($params, 'visitDate'))); $classControl = isEmptyProperty($params, 'classControl'); $info = $this->getDiagnosisInfo($diagnosisId); $isEmpty = (array) $info; $ret = new stdClass(); if (!empty($isEmpty)) { if ($classControl !== 2) { switch ($info->type) { case 0: case 1: $res = $this->checkDiagnosis($visitDate, $params); break; case 2: $res = $this->checkDiagnosis($visitDate, $params, $info->controlDays); break; case 3: case 4: $res = 6; break; default: $res = 0; } switch ($res) { case 1: $ret->text = 'Острое или травма. Не регистрировалось'; $ret->code = 1; break; case 2: $ret->text = 'Острое или травма. Регистрировалось: ' . date("d.m.Y", safe_strtotime($visitDate)); $ret->flag = true; $ret->code = 2; break; case 3: $ret->text = 'Хроническое. Не регистрировалось'; $ret->code = 3; break; case 4: $ret->text = 'Хроническое. Не регистрировалось в текущем году'; $ret->code = 4; break; case 5: $ret->text = 'Хроническое. Зарегистрировано: ' . date("d.m.Y", safe_strtotime($visitDate)); $ret->code = 5; $ret->flag = true; break; case 6: $ret->text = 'Регистрация заболеваемости не допускается'; $ret->flag = true; $ret->code = 6; break; default: $ret->text = ''; } } if ($classControl) { switch ($info->type) { case 0: case 1: $check = $this->checkClassDiagnosis($visitDate, $params); if (isset($check->controlMsg)) { $ret->controlList = $check->controlList; $ret->controlMsg = $check->controlMsg; $ret->flag = true; } break; case 2: $check = $this->checkClassDiagnosis($visitDate, $params, $info->controlDays); if (isset($check->controlMsg)) { $ret->controlList = $check->controlList; $ret->controlMsg = $check->controlMsg; $ret->flag = true; } break; } } } return $ret; } public function checkDispensary ($params) { $db = $this->db ? : $this->db = connectDb(); $visitDate = dbDate(isEmptyProperty($params, 'visitDate')); $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $patientId = isEmptyProperty($params, 'patientId'); if(!$visitDate) { return -1; } $sql =<<< "EOT"
    select MedDispensaryCode
    from MedDispensary
    where ? between startDate and COALESCE(finishDate, 30000101) and medDiagnosisCode = ? and medPatientCode = ?
EOT;
$ret = selectSqlValue($db, $sql, [ $visitDate, $diagnosisId, $patientId ]); return $ret ? 1 : -1; } public function createVisitsFromCoupons($params) { $db = $this->db ? : $this->db = connectDb(); $visitId = isUndefinedProperty($params, 'visitId', 0); $doctorId = isUndefinedProperty($params, 'doctorId', 0); $visitDate = date("Ymd", safe_strtotime(isEmptyProperty($params, 'visitDate'))); $type = isUndefinedProperty($params, 'type', 0); $typeSqlWhere = $type && $type === 1 ? "and MC.CouponState = 2" : ""; $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
DO \$anon\$
BEGIN
  create temp table _ret on commit drop as select 0::integer n;

  DECLARE C1__r RECORD;
  C1_ NO SCROLL CURSOR FOR
    select
           MC.CallTime cur_CallTime,
           MC.medCallCode cur_CallCode,
           MC.medPatientCode cur_Patient,
           MPG.VisitZone cur_VisitZone,
           CASE MC.CallType
                WHEN 1 THEN 1
                WHEN 2 THEN 1
                WHEN 3 THEN 2
           END cur_VisitPlace
    from medCall MC
         join medPatient MP on MP.medPatientCode = MC.medPatientCode
         left join medPatientGroup MPG on MPG.medPatientGroupCode = MP.medPatientGroupCode
         left join medVisitPatient MVP on MVP.medVisitCode = $visitId
                                      and  MVP.medPatientCode = MC.medPatientCode
                                      and  MVP.VisitZone = MPG.VisitZone
    where MC.EnterpriseCode = $enterpriseId
      and MC.CallTime >= $visitDate
      and MC.CallTime < DATEADD('DAY', 1, $visitDate)
      and MC.medDoctorCode = $doctorId
      and MVP.medVisitPatientCode is null
    order by 1, 2;
  BEGIN
    C1_ := NULL;
    FOR C1__r IN C1_ LOOP
      insert into medVisitPatient
        (medvisitcode, medpatientcode, visitzone, visitplace, serviceform, visitendnew, visitcasenew)
      values
        ($visitId, C1__r.cur_Patient, C1__r.cur_VisitZone, C1__r.cur_VisitPlace, 1, 2, 1);

      update _ret set n = n + 1;
    END LOOP;
  END;

  DECLARE C2__r RECORD;
  C2_ NO SCROLL CURSOR FOR
    select
           MC.VisitTime cur_VisitTime,
           MC.medCouponCode cur_CouponCode,
           MC.medPatientCode cur_Patient,
           MPG.VisitZone cur_VisitZone
    from medCoupon MC
         join medPatient MP on MP.medPatientCode = MC.medPatientCode
         left join medPatientGroup MPG on MPG.medPatientGroupCode = MP.medPatientGroupCode
         left join medVisitPatient MVP on MVP.medVisitCode = $visitId
                                               and MVP.medPatientCode = MC.medPatientCode
                                               and MVP.VisitZone = MPG.VisitZone
    where MC.OnDate = $visitDate
      and MC.medDoctorCode = $doctorId
      and MC.VisitTime is not null
      and MVP.medVisitPatientCode is null
      and MC.CouponState between 1 and 2
      $typeSqlWhere
    order by 1, 2;

  BEGIN
    C2_ := NULL;
    FOR C2__r IN C2_ LOOP
      insert into medVisitPatient
        (medvisitcode, medpatientcode, visitzone, serviceform, visitendnew, visitcasenew)
      values
        ($visitId, C2__r.cur_Patient, C2__r.cur_VisitZone, 1, 2, 1);

      update _ret set n = n + 1;
    END LOOP;
  END;

  DECLARE C3__r RECORD;
  C3_ NO SCROLL CURSOR FOR
    select
           MC.medCouponCode cur_CouponCode,
           MC.medPatientCode cur_Patient,
           MPG.VisitZone cur_VisitZone
    from medCoupon MC
         join medPatient MP on MP.medPatientCode = MC.medPatientCode
         left join medPatientGroup MPG on MPG.medPatientGroupCode = MP.medPatientGroupCode
         left join medVisitPatient MVP on MVP.medVisitCode = $visitId
                                               and  MVP.medPatientCode = MC.medPatientCode
                                               and  MVP.VisitZone = MPG.VisitZone
    where MC.OnDate = $visitDate
      and MC.medDoctorCode = $doctorId
      and MC.VisitTime is null
      and MVP.medVisitPatientCode is null
      and MC.CouponState between 1 and 2
      $typeSqlWhere
    order by 1;

  BEGIN
    C3_ := NULL;
    FOR C3__r IN C3_ LOOP
      insert into medVisitPatient
        (medvisitcode, medpatientcode, visitzone, serviceform, visitendnew, visitcasenew)
      values
        ($visitId, C3__r.cur_Patient, C3__r.cur_VisitZone, 1, 2, 1);

      update _ret set n = n + 1;
    END LOOP;
  END;

END \$anon\$;
EOT;
$db->beginTransaction(); try { executeSql($db, $sql); $cnt = selectSqlValue($db, 'select n from _ret'); empty($cnt) ? $db->rollback() : $db->commit(); return $cnt; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function findInspect($params) { $db = $this->db ? : $this->db = connectDb(); $visitDate = date("Ymd", safe_strtotime(isEmptyProperty($params, 'visitDate'))); $sql = <<<"EOT"
select
       MI.PlanDate "planDate",
       COALESCE(MI.InspectDate, 30000101) "inspectDate",
       1 "inInspectRes",
       MI.MedInspectCode "inspectId",
       MP.MedPreventTypeCode "preventTypeId",
       MP.MedPreventCode "preventId"
from MedInspect MI
     left join MedPrevent MP on MP.MedPreventCode = MI.MedPreventCode
where MI.MedPatientCode = ?
  and MI.PlanDate between DATEADD('month', -MP.PeriodMonth, ?) and DATEADD('month', MP.PeriodMonth, ?)
order by MI.PlanDate desc
limit 1
EOT;
return selectSql($db, $sql, [ isUndefinedProperty($params, 'patientId'), $visitDate, $visitDate ]); } private function getPreventType() { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
       MedPreventTypeCode "preventTypeId",
       Name "name"
from MedPreventType
where EnterpriseCode = ?
  and inputform in (2,3,4)
EOT;
return selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode') ]); } public function getPrevent($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
      fn_medFindPrevent(?, (select string_agg((MedPreventCode)::text, ',')
                            from MedPrevent
                            where MedPreventTypeCode = ?), ?, ?) "preventId"
EOT;
$res = selectSqlValue($db, $sql, [ isUndefinedProperty($params, 'patientId'), isEmptyProperty($params, 'preventTypeId'), date("Ymd", safe_strtotime(isEmptyProperty($params, 'dateOfBirth'))), date("Ymd") ]); if (empty($res)) { throw new Exception('Для выбранного вида профилактики необходимо заполнить группы профилактики!'); } return $res; } public function findPrevent($params) { $db = $this->db ? : $this->db = connectDb(); $prevents = $this->getPreventType(); $ret = new stdClass(); if (empty($prevents)) { throw new Exception('Необходимо сначала заполнить справочник видов профилактики!'); } if (count($prevents) === 1) { $ret->preventTypeId = $prevents[0]['preventTypeId']; } else { $sql = <<<"EOT"
with PREVENTS as (
select string_agg((MP.MedPreventCode)::text, ',') "prevents"
from MedPreventType MPT
     join MedPrevent MP on MP.MedPreventTypeCode = MPT.MedPreventTypeCode
where enterpriseCode = ?
  and inputForm in (2,3,4)
group by MPT.MedPreventTypeCode
), PREVENTS_CODES as (
select fn_medFindPrevent(?, PREVENTS."prevents", ?, ?) "preventId"
from PREVENTS
)

select MP.MedPreventTypeCode "preventTypeId"
from PREVENTS_CODES PC
     join medPrevent MP on MP.medPreventCode = PC."preventId"
EOT;
$res = selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode'), isUndefinedProperty($params, 'patientId'), date("Ymd", safe_strtotime(isEmptyProperty($params, 'dateOfBirth'))), date("Ymd") ]); if (count($res) > 1) { $ret->preventTypeArr = $prevents; } else { $ret->preventTypeId = $res[0]['preventTypeId']; } } return $ret; } public function isAllowSelectPatient($params) { $db = $this->db ? : $this->db = connectDb(); $visitDate = isEmptyProperty($params, 'visitDate'); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId', 0); $professionId = isEmptyProperty($params, 'professionId', 0); $departmentId = isEmptyProperty($params, 'departmentId', 0); $visitPatientId = isEmptyProperty($params, 'visitPatientId', 0); $sql = <<< "EOT"
    SELECT
        MV.medVisitCode "visitId"
    FROM MedVisit MV
    LEFT JOIN MedVisitPatient as MVP ON MVP.medVisitCode = MV.medVisitCode
    WHERE MVP.medPatientCode = $patientId AND MV.visitdate = $visitDate AND
          MV.medDoctorCode = $doctorId AND MV.medProfessionCode = $professionId AND
          MV.departmentCode = $departmentId AND MVP.medVisitPatientCode != $visitPatientId AND
          MVP.attendantDiagnosis is NULL
    LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetch(); return $ret ? $ret : -1; } } handleJsonRpc('med_clinicstats_visits');