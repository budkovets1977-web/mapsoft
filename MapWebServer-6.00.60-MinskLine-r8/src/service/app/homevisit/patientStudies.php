<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientStudies { private $db; public function readPatientStudies($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
    MLS.MedLogStudyCode "id",
    MLS.Type "type",
    date(MLS.ExecTime) "onDate",
    MS.Name "name",
    --22589 CAST(FN_htmMask2Str(MLS.htmPageCode,MS.ResultMask) as VARCHAR(255)) "result"
    MLS.Result "result"
from MedLogStudy MLS
join MedStudy MS ON (MS.MedStudyCode = MLS.MedStudyCode AND MS.Type = MLS.Type)
where MLS.MedPatientCode = ? AND
      COALESCE(ExecTime,19000101) >= dateadd({$db->postgre("'")}month{$db->postgre("'")}, -1, {$db->dialect("today()", "current_date")})

union all

select
    MF.MedFluorCode "id",
    0 "type",
    MF.OnDate "onDate",
    MPT.Name "name",
    --22589 CAST(MF.Conclusion as VARCHAR(255)) "result"
    MF.Conclusion "result"
from MedFluor MF
join MedPreventType MPT ON MPT.MedpreventTypeCode = MF.MedPreventTypeCode
where MF.medpatientCode = ? and
      COALESCE(ondate,19000101) >= ymd(year({$db->dialect("today()", "current_date")}) -1, 1, 1)
order by 3 desc {$db->postgre("NULLS LAST")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'patientId', 0), isEmptyProperty($params, 'patientId', 0) ]); return $stmt->fetchAll(); } } $service = new Junior\Server(new homevisit_patientStudies()); $service->process();