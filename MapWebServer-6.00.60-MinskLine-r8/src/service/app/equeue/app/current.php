<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_current { private $db; public function readCurrent($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $serviceId = (int)$params->service; $categoryId = (int)$params->category; $state = (int)$params->state; $sqlTextState = ''; switch($state){ case 1: $sqlTextState = 'and MEQC.State = -1'; break; case 2: $sqlTextState = 'and MEQC.State = 0'; break; case 3: $sqlTextState = 'and MEQC.State = 1'; break; case 4: $sqlTextState = 'and MEQC.State = 2'; break; case 5: $sqlTextState = 'and MEQC.State in (3, 4, 5, 6)'; break; } $sql = <<<EOT
select
    MEQC.MedEqQueueCouponCode "id",
    MEQC.MedEqServiceCode "serviceId",
    case
        when COALESCE(MEqS.Cipher, E'') = E'' then E''
        else MEqS.Cipher || E' - '
    end || MEqS.Name "serviceName",
    MEQC.MedEqPatientCategoryCode "categoryId",
    MEPC.Name "categoryName",
    MEQC.MedEqInfoboxCode "infoboxId",
    case
        when COALESCE(MEI.Cipher, E'') = E'' then E''
        else MEI.Cipher || E' - '
    end || MEI.name "infoboxName",
    MEQC.MedEqServiceOptionCode "optionId",
    MESO.Name "optionName",
    DATE_TRUNC('second', MEQC.CreateTime) "createTime",
    DATE_TRUNC('second', MEQC.StartTime) "startTime",
    DATE_TRUNC('second', MEQC.FinishTime) "finishTime",
    MEQC.MedPatientCode "patientId",
    case
        when MEQC.MedLogStudyCode is null then Fn_cmFIO(MP.ManCode) ||(case
                                                                         when MP.NumberHistorySickness is null then E''
                                                                         else E' [' || MP.NumberHistorySickness || E']'
                                                                       end)
        else MLS.PatientFIO ||(case
                                when MLS.NumberHistorySickness is null then E''
                                else E' [' || MLS.NumberHistorySickness || E'] '
                               end)
    end "patientName",
    MEQC.MedEqOWPCode "owpId",
    (case
        when MEO.Cabinet is null then E''
        else MEO.Cabinet || E', '
      end) || MEO.Number || E' ' || MEO.Name "owpName",
    MEQC.DocumentNumber "documentNumber",
    MEQC.State "state",
    MEQC.ResultPriority "resultPriority",
    MEQC.PIN "pin",
    MEQC.MedLogStudyCode "logStudyId",
    (case
        when MLS.DirNumber is null then E''
        else MLS.DirNumber || E', '
      end) || DATEFORMAT(MLS.DirTime, E'YYYY-MM-DD') "logStudyName",
    MEQC.UserCode "userId",
    wu.Nick "userName"
from MedEqQueueCoupon MEQC
left join MedEqService MEqS on MEqS.MedEqServiceCode = MEQC.MedEqServiceCode
left join MedEqPatientCategory MEPC on MEPC.MedEqPatientCategoryCode = MEQC.MedEqPatientCategoryCode
left join MedEqInfobox MEI on MEI.MedEqInfoboxCode =  MEQC.MedEqInfoboxCode
                          and MEI.EnterpriseCode = $mapEnterpriseCode
left join MedEqServiceOption MESO on MESO.MedEqServiceOptionCode = MEQC.MedEqServiceOptionCode
left join MedPatient MP on MP.MedPatientCode = MEQC.MedPatientCode
                       and MP.EnterpriseCode = $mapEnterpriseCode
left join MedEqOWP MEO on MEO.MedEqOWPCode = MEQC.MedEqOWPCode
                      and MEO.EnterpriseCode = $mapEnterpriseCode
left join MedLogStudy MLS on MLS.MedLogStudyCode = MEQC.MedLogStudyCode
left outer join WebUser wu on wu.UserCode = MEQC.UserCode
where ((MEQC.MedEqServiceCode = $serviceId) or (0 = $serviceId))
  and ((MEQC.MedEqPatientCategoryCode = $categoryId) or (0 = $categoryId))
  $sqlTextState
order by MEQC.CreateTime desc NULLS LAST
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function removeCurrentCoupon($params){ $db = $this->db ?: $this->db = connectDb(); $id = (int)$params->id; $userCode = getSessionValue('mapUserCode'); $finishTime = date("Y-m-d H:i:s"); $sql = 'SELECT FN_medEqSkipCoupon(?, ?, ?, ?) response'; $stmt = $db->prepare($sql); $stmt->execute([ $id, 5, $finishTime, $userCode ]); $ret = $stmt->fetch(); return $ret; } } handleJsonRpc('equeue_app_current');