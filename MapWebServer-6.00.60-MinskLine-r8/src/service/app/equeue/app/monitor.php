<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_monitor { private $db; public function readMonitor($params){ $db = $this->db ?: $this->db = connectDb(); $serviceId = (int)$params->service; $categoryId = (int)$params->category; $intServicePlusCategory = $serviceId + $categoryId; $sql = <<<EOT
SELECT
    q.MedEqServiceCode "serviceId",
    s.Name || CASE WHEN asa_trim(coalesce(s.Cipher, E'')) <> (E'') THEN E' (' || s.Cipher || E')' ELSE E'' END "serviceName",
    CASE WHEN (? <> 0) THEN q.MedEqPatientCategoryCode ELSE NULL END "categoryId",
    CASE WHEN (? <> 0) THEN pc.Name ELSE NULL END "categoryName",
    COUNT(CASE WHEN qc.State = - 1 THEN 1 END) "count100",
    COUNT(CASE WHEN qc.State = 0 THEN 1 END) "count0",
    COUNT(CASE WHEN qc.State = 1 THEN 1 END) "count1",
    COUNT(CASE WHEN qc.State = 2 THEN 1 END) "count2",
    COUNT(CASE WHEN qc.State = 3 THEN 1 END) "count3",
    COUNT(CASE WHEN qc.State = 4 THEN 1 END) "count4",
    COUNT(CASE WHEN qc.State = 5 THEN 1 END) "count5"
FROM MedEqQueue q
JOIN MedEqService s ON s.MedEqServiceCode = q.MedEqServiceCode
JOIN MedEqPatientCategory pc ON pc.MedEqPatientCategoryCode = q.MedEqPatientCategoryCode
LEFT OUTER JOIN MedEqQueueCoupon qc ON qc.MedEqServiceCode = q.MedEqServiceCode
    AND qc.MedEqPatientCategoryCode = q.MedEqPatientCategoryCode
WHERE (q.MedEqServiceCode = ? OR ? = 0)
    AND (q.MedEqPatientCategoryCode = ? OR ? = 0)
GROUP BY q.MedEqServiceCode, s.Name, s.Cipher, "categoryId", "categoryName"
HAVING COUNT (qc.MedEqQueueCouponCode) > 0
ORDER BY 5 DESC, 6 DESC, 7 DESC, 8 DESC, 9 DESC, 10 DESC, 11 DESC, 2, 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $intServicePlusCategory, $intServicePlusCategory, $serviceId, $serviceId, $categoryId, $categoryId ]); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_monitor');