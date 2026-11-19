<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_releaseCoupon { const TASK_NAME = 'talonbyReleaseCoupon'; private $db; public function releaseCoupon($MedCouponCode, $MedPatientCode, $AMode) { $params = new stdClass; $params->aMode = isset($AMode) ? $AMode : 0; $params->medCouponCode = isset($MedCouponCode) ? $MedCouponCode : 0; $params->medPatientCode = isset($MedPatientCode) ? $MedPatientCode : 0; if (!$this->couponSummary($params)) { return false; } if ($this->CouponUpdate($params)) { return true; } return false; } private function couponSummary($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
  MC.OnDate as OnDate,
  VisitTime,
  FN_cmFIO(MD.ManCode) as DocName,
  MP.Name as SpecName,
  MCG.Cabinet as Cabinet,
  MC.MedDoctorCode as MedDoctorCode,
  COALESCE(MC.MedPatientCode,0) as MedPatientCode,
  COALESCE(MC.SelfRecording,0) as SelfRecording,
  MC.FIO as FIO,     asa_trim(eq.DocumentNumber) as EQCouponNumber
FROM MedCoupon AS MC LEFT OUTER JOIN MedDoctor AS MD ON MD.MedDoctorCode = MC.MedDoctorCode
LEFT OUTER JOIN MedProfession AS MP ON MP.MedProfessionCode = MD.MedProfessionCode
LEFT OUTER JOIN MedCouponGraph AS MCG ON (MC.MedDoctorCode = MCG.MedDoctorCode AND MC.OnDate=MCG.OnDate)
left join MedEqQueueCoupon AS eq on eq.MedCouponCode = mc.MedCouponCode
WHERE mc.MedCouponCode=$params->medCouponCode
ORDER BY 1 DESC, 2 DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $item = $stmt->fetch(); if ($item['medpatientcode'] != $params->medPatientCode) { return false; } return true; } private function CouponUpdate($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCoupon SET
    medpatientcode = null,
    selfrecording = null,
    couponstate = 0,
    ordertime = null,
    note = null,
    shortnumber = null
WHERE medcouponcode= $params->medCouponCode
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("При редактировании талона произошла ошибка: $e"); return false; } return true; } } handleJsonRpc('med_talonby_releaseCoupon'); 