<?php
require_once(__DIR__.'/../../init.php'); class equeue_orc_redirection { private $db; public function redirectPatient($params){ $db = $this->db ?: $this->db = connectDb(); $ticketId = $params->ticketId; $ticketNumber = $params->ticketNumber; $serviceId = $params->serviceId; $optionId = $params->optionId; $serviceToReturnToId = $params->serviceToReturnToId; $serviceRedirectTimeout = $params->serviceRedirectTimeout; $patientServed = 2; $owpId = $params->owpId; $patientServedLog = 5; $ret = new stdClass; $ret->error = false; $ret->errorMsg = 'Ошибка'; $sql = <<<EOT
            SELECT
                medPatientCode "medPatientCode",
                medLogStudyCode "medLogStudyCode",
                medEqPatientCategoryCode "medEqPatientCategoryCode",
                medEqInfoboxCode "medEqInfoboxCode",
                pin
            FROM medEqQueueCoupon
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ticketId ]); $currentTalon = $stmt->fetch(); $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['medPatientCode'], $currentTalon['medLogStudyCode'], $serviceId, $optionId, $currentTalon['medEqPatientCategoryCode'], $currentTalon['medEqInfoboxCode'], $currentTalon['pin'] ]); $newTalonId = (int)fetchValue($stmt, 'id'); switch($newTalonId){ case -4: $ret->error = true; $ret->errorMsg = 'По выбранной услуге в системе не создано ни одного оператора.'; break; case -5: $ret->error = true; $ret->errorMsg = 'Превышено максимальное количество талонов по выбранной услуге.'; break; case -6: $ret->error = true; $ret->errorMsg = 'Время выдачи талонов по выбранной услуге истекло или услуга не привязана ни к одному инфокиоску.'; break; case -7: $ret->error = true; $ret->errorMsg = 'Пациента не успеют обслужить в рамках рабочего времени.'; break; } if ($ret->error){ return $ret; } $sql = <<<EOT
            UPDATE MedEqQueueCoupon
                SET documentNumber = ?,
                    medEqServiceCodeRedirect = ?,
                    keepTillTime = (select NOW() + (? * interval '1 minute'))
            WHERE MedEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ticketNumber, $serviceToReturnToId, $serviceRedirectTimeout, $newTalonId ]); $sql = 'SELECT PR_medEqLogOwpAction(?, ?, ?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $patientServedLog, $ticketId, $ticketNumber ]); $sql = <<<EOT
            UPDATE MedEqQueueCoupon
                SET FinishTime = NOW(),
                    State = ?
            WHERE MedEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientServed, $ticketId ]); } } handleJsonRpc('equeue_orc_redirection');