<?php
require_once(__DIR__.'/../../init.php'); class equeue_orc_remoteControl { private $db; public function startStopWork($params){ $db = $this->db ?: $this->db = connectDb(); $owpId = $params->owpId; $owpType = $params->owpType; $owpAction = $params->owpAction; $sql = 'SELECT PR_medEqMakeOWPperiod(?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $owpType ]); $sql = 'SELECT PR_medEqLogOwpAction(?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $owpAction ]); } public function callPatient($params){ $db = $this->db ?: $this->db = connectDb(); $result = false; $owpId = $params->owpId; $owpAction = $params->owpAction; $sql = 'SELECT FN_medEqInvitePatient2(?) "ticketId"'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId ]); $ticketId = fetchValue($stmt, 'ticketId'); if ($ticketId != null){ $sql = <<<EOT
                SELECT
                    trim(mec.documentNumber)    "ticketNumber",
                    mepc.name                   "category",
                    FN_cmFIO(mp.manCode)        "patientName",
                    ms.name                     "studyName",
                    asa_trim(COALESCE(mls.dirNumber, null))
                                                "studyNumber"
                FROM medEqQueueCoupon mec
                LEFT JOIN medEqPatientCategory mepc ON mepc.medEqPatientCategoryCode = mec.medEqPatientCategoryCode
                LEFT JOIN medPatient mp ON mp.medPatientCode = mec.medPatientCode
                LEFT JOIN medLogStudy mls ON mls.medLogStudyCode = mec.medLogStudyCode
                LEFT JOIN medStudy ms ON ms.medStudyCode = mls.medStudyCode
                WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ticketId ]); $result = new stdClass(); $result->ticketData = $stmt->fetch(); $result->ticketData['ticketId'] = $ticketId; $sql = 'SELECT PR_medEqLogOwpAction(?, ?, ?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $owpAction, $result->ticketData['ticketId'], $result->ticketData['ticketNumber'] ]); } return $result; } public function checkIfPatientCalled($params){ $db = $this->db ?: $this->db = connectDb(); $owpId = $params->owpId; $serviceId = $params->serviceId; $sql = <<<EOT
            SELECT
                COUNT(*)                "patientCalled",
                TRIM(documentNumber)    "ticketNumber",
                medEqQueueCouponCode    "ticketId"
            FROM medEqQueueCoupon
            WHERE medEqOwpCode = ?
                AND medEqServiceCode = ?
                AND state = 1
            GROUP BY documentNumber, medEqQueueCouponCode
            ORDER BY documentNumber
            LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $owpId, $serviceId ]); return $stmt->fetch(); } public function getOrcConfig($params){ $db = $this->db ?: $this->db = connectDb(); $customerId = isEmptyProperty($params, 'customerId'); $serviceId = isEmptyProperty($params, 'serviceId'); $ret = new stdClass; $ret->waitTimeMilliseconds = 5000; $sql = <<<EOT
            SELECT
                (((EXTRACT(hour FROM WaitTime))::INTEGER) * 60 * 60 +
                ((EXTRACT(minute FROM WaitTime))::INTEGER) * 60 +
                ((EXTRACT(second FROM WaitTime))::INTEGER)) * 1000 "waitTimeMilliseconds"
            FROM MedEqService
            WHERE EnterpriseCode = ?
                AND MedEqServiceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $customerId, $serviceId ]); if ($stmt->rowCount() > 0){ $ret->waitTimeMilliseconds = fetchValue($stmt, 'waitTimeMilliseconds'); } $sql = <<<EOT
            SELECT
                msr.medEqServiceCodeRedirect    "serviceId",
                ms.name                         "serviceName",
                msr.abilityToReturn             "returnAbility",
                mso.medEqServiceOptionCode      "optionId",
                mso.name                        "optionName",
                msr.redirectTimeout             "redirectTimeout"
            FROM medEqServiceRedirect msr
            LEFT JOIN medEqService ms ON ms.medEqServiceCode = msr.medEqServiceCodeRedirect
            LEFT JOIN medEqServiceOption mso ON mso.medEqServiceCode = ms.medEqServiceCode
                AND mso.active = 1
                AND ((SELECT COUNT(*) FROM uniTimeTable WHERE source = 3 AND sourceCode = mso.medEqServiceOptionCode) = 0
                    OR ((SELECT COUNT(*) FROM uniTimeTable WHERE source = 3 AND sourceCode = mso.medEqServiceOptionCode) > 0
                        AND fn_cmUniTimeTable(LOCALTIMESTAMP, 3, mso.medEqServiceOptionCode) IS NOT NULL))
            WHERE msr.medEqServiceCode = ?
            ORDER BY ms.name, mso.name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $serviceId ]); $services = []; $service = null; $option = null; while ($row = $stmt->fetch()){ if (!$service || $service->id != (int)$row['serviceId']){ $services[] = $service = new stdClass; $service->id = (int)$row['serviceId']; $service->name = $row['serviceName']; $service->patientShouldReturn = $row['returnAbility']; $service->redirectTimeout = $row['redirectTimeout']; } if (isset($row['optionId'])){ $service->options[] = $option = new stdClass; $option->id = (int)$row['optionId']; $option->name = $row['optionName']; } } $ret->redirectionServices = $services; return $ret; } public function patientServedOrDidNotCome($params){ $db = $this->db ?: $this->db = connectDb(); $state = $params->state; $ticketId = $params->ticketId; $stateDidNotCome = 3; $sql = <<<EOT
            SELECT
                mqc.documentNumber              "talonNumber",
                mqc.medEqServiceCode            "serviceId",
                mqc.medEqServiceOptionCode      "serviceOptionId",
                mqc.medPatientCode              "medPatientCode",
                mqc.medLogStudyCode             "medLogStudyCode",
                mqc.medEqPatientCategoryCode    "medEqPatientCategoryCode",
                mqc.medEqInfoboxCode            "medEqInfoboxCode",
                mqc.pin,
                CASE WHEN LOCALTIMESTAMP < mqc.keepTillTime THEN 1 ELSE 0 END
                                                "talonLocked",
                mqc.keepTillTime                "keepTillTime",
                mqc.medEqServiceCodeRedirect    "serviceIdToRedirectTo",
                msr.redirectTimeout             "redirectTimeout"
            FROM medEqQueueCoupon mqc
            LEFT OUTER JOIN medEqServiceRedirect msr ON msr.medEqServiceCode = mqc.medEqServiceCodeRedirect
                AND msr.medEqServiceCodeRedirect = mqc.medEqServiceCode
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ticketId ]); $currentTalon = $stmt->fetch(); if ($currentTalon){ if ($currentTalon['talonLocked']){ $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['medPatientCode'], $currentTalon['medLogStudyCode'], $currentTalon['serviceId'], $currentTalon['serviceOptionId'], $currentTalon['medEqPatientCategoryCode'], $currentTalon['medEqInfoboxCode'], $currentTalon['pin'] ]); $newTalonId = (int)fetchValue($stmt, 'id'); if ($newTalonId < 0){ $sql = <<<EOT
                        UPDATE MedEqQueueCoupon
                            SET State = 0
                        WHERE MedEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ticketId ]); } else { $this->closeTalon($params, false); $sql = <<<EOT
                        UPDATE MedEqQueueCoupon
                            SET documentNumber = ?,
                                medEqServiceCodeRedirect = ?,
                                keepTillTime = ?
                        WHERE MedEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['talonNumber'], $currentTalon['serviceIdToRedirectTo'], $currentTalon['keepTillTime'], $newTalonId ]); } } else { if ($currentTalon['serviceIdToRedirectTo'] == null || $state == $stateDidNotCome){ $this->closeTalon($params, true); } else { $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['medPatientCode'], $currentTalon['medLogStudyCode'], $currentTalon['serviceIdToRedirectTo'], null, $currentTalon['medEqPatientCategoryCode'], $currentTalon['medEqInfoboxCode'], $currentTalon['pin'] ]); $redirectBackTalonId = (int)fetchValue($stmt, 'id'); $ret = new stdClass; $ret->error = false; if ($redirectBackTalonId < 0){ $sql = <<<EOT
                            SELECT name "serviceName"
                            FROM medEqService
                            WHERE medEqServiceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['serviceIdToRedirectTo'] ]); $ret->serviceName = fetchValue($stmt, 'serviceName'); } else { $sql = <<<EOT
                            UPDATE medEqQueueCoupon
                                SET documentNumber = ?,
                                keepTillTime = (SELECT LOCALTIMESTAMP + (? * INTERVAL '1 minute'))
                            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $currentTalon['talonNumber'], $currentTalon['redirectTimeout'], $redirectBackTalonId ]); } switch($redirectBackTalonId){ case -4: $ret->error = true; $ret->errorMsg = 'По выбранной услуге в системе не создано ни одного оператора.'; break; case -5: $ret->error = true; $ret->errorMsg = 'Превышено максимальное количество талонов по выбранной услуге.'; break; case -6: $ret->error = true; $ret->errorMsg = 'Время выдачи талонов по выбранной услуге истекло.'; break; case -7: $ret->error = true; $ret->errorMsg = 'Пациента не успеют обслужить в рамках рабочего времени.'; break; } $this->closeTalon($params, true); if ($ret->error){ return $ret; } } } } } public function callAgain($params){ $db = $this->db ?: $this->db = connectDb(); $owpId = $params->owpId; $serviceId = $params->serviceId; $owpAction = $params->owpAction; $sql = <<<EOT
            SELECT
                medEqQueueCouponCode    id,
                trim(documentNumber)    "ticketNumber",
                callCount               "callCount"
            FROM medEqQueueCoupon
            WHERE medEqOwpCode = ?
                AND medEqServiceCode = ?
                AND state = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $owpId, $serviceId ]); $rec = $stmt->fetch(); $sql = <<<EOT
            UPDATE medEqQueueCoupon
            SET callCount = ?
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ++$rec['callCount'], $rec['id'] ]); $sql = 'SELECT PR_medEqLogOwpAction(?, ?, ?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $owpAction, $rec['id'], $rec['ticketNumber'] ]); } public function checkIfThereIsRedirection($params){ $db = $this->db ?: $this->db = connectDb(); $serviceId = $params->serviceId; $sql = <<<EOT
            SELECT COUNT(*)
            FROM medEqServiceRedirect
            WHERE medEqServiceCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $serviceId ]); $result = $stmt->fetch(); return $result; } public function getPeopleInTheQueue($params){ $db = $this->db ?: $this->db = connectDb(); $serviceId = $params->serviceId; $ret = null; $sql = <<<EOT
            SELECT COUNT(*) "peopleInTheQueue"
            FROM medEqQueueCoupon
            WHERE medEqServiceCode = ?
                AND state = 0
            GROUP BY medEqServiceCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $serviceId ]); $ret = $stmt->fetchAll(); return $ret; } private function closeTalon($params, $logOwpAction){ $db = $this->db ?: $this->db = connectDb(); $state = $params->state; $ticketId = $params->ticketId; $ticketNumber = $params->ticketNumber; $owpId = $params->owpId; $medEqQueueCouponStatus = [ 'patientServed' => 2, 'patientDidNotCome' => 3 ]; $medEqOwpLogStatus = [ 'patientServed' => 5, 'patientDidNotCome' => 4 ]; $logStatus = $medEqOwpLogStatus['patientServed']; if ($state == $medEqQueueCouponStatus['patientDidNotCome']){ $logStatus = $medEqOwpLogStatus['patientDidNotCome']; } if ($logOwpAction){ $sql = 'SELECT PR_medEqLogOwpAction(?, ?, ?, ?)'; $stmt = $db->prepare($sql); $stmt->execute([ $owpId, $logStatus, $ticketId, $ticketNumber ]); } $sql = <<<EOT
            UPDATE medEqQueueCoupon
                SET finishTime = LOCALTIMESTAMP,
                    state = ?
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $state, $ticketId ]); } public function getSegmentDisplayData($params){ $db = $this->db ?: $this->db = connectDb(); $owpId = isEmptyProperty($params, 'owpId'); $sqlParams = [ ':owpId' => $owpId ]; $sql = <<<EOT
            SELECT
                med.blinkCount      "blinkCount",
                med.displayNumber   "displayNumber",
                med.ipAddress       "ipAddress",
                med.ipPort          "ipPort",
                med.portNumber      "portNumber",
                med.portSpeed       "portSpeed",
                CASE WHEN med.displayKind = 2 THEN 1 ELSE 0 END
                                    "color"
            FROM medEqDisplay med
            LEFT JOIN medEqDisplayOwp mdo ON mdo.medEqDisplayCode = med.medEqDisplayCode
            WHERE mdo.medEqOwpCode = :owpId
                AND med.displayKind = 2
EOT;
$ret = selectSql($db, $sql, $sqlParams); return $ret; } } handleJsonRpc('equeue_orc_remoteControl');