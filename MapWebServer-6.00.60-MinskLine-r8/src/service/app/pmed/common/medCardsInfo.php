<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_common_medCardsInfo { private $db; public function readMedCardInfo($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $sql = <<<"EOT"
select
       MP.medPatientCode "patientId",
       FN_cmFIO(MP.manCode) "fio",
       case when MP.photoId is not null then 1 else 0 end "photoExists",
       MPass.sex "sex"
from medPatient MP
     join ManPassport MPass on MPass.manCode = MP.ManCode
where medPatientCode = {$patientId}
EOT;
return selectSql($db, $sql); } } handleJsonRpc('pmed_common_medCardsInfo'); 