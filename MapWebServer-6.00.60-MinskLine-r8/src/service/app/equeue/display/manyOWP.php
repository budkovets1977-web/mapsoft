<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_manyOWP { private $db; private $timeout = 300; public function getOperatorsNames($params){ $db = $this->db ?: $this->db = connectDb(); $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $sql = <<<EOT
            SELECT
                meo.medEqOwpCode    id,
                meo.name            operatorName
            FROM medEqDisplay md
            LEFT JOIN medEqDisplayOwp mdo ON mdo.medEqDisplayCode = md.medEqDisplayCode
            LEFT JOIN medEqOwp meo ON meo.medEqOwpCode = mdo.medEqOwpCode
            WHERE md.displayNumber LIKE ?
                AND meo.enterpriseCode = ?
            ORDER BY mdo.position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); return $stmt->fetchAll(); } public function getPatientsToCall($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $sql = <<<EOT
            SELECT
                ec.medEqQueueCouponCode id,
                ec.documentNumber,
                ec.callCount,
                eo.medEqOwpCode owpId,
                eo.number owpNumber
            FROM medEqQueueCoupon ec
            LEFT JOIN medEqOwp eo ON eo.medEqOwpCode = ec.medEqOwpCode
            WHERE ec.state = 1
                AND eo.medEqOwpCode IN (
                    SELECT meo.medEqOwpCode
                    FROM medEqDisplay md
                    LEFT JOIN medEqDisplayOwp mdo ON mdo.medEqDisplayCode = md.medEqDisplayCode
                    LEFT JOIN medEqOwp meo ON meo.medEqOwpCode = mdo.medEqOwpCode
                    WHERE md.displayNumber LIKE ?
                        AND meo.enterpriseCode = ?
                )
            ORDER BY ec.startTime
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqPatientToCallHash'); if ($savedHash != $retHash){ setSessionValue('webEqPatientToCallHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); $result = new stdClass(); $result->data = $ret; $result->owpIds = []; foreach ($ret as $val){ array_push($result->owpIds, $val['owpid']); } return $result; } } handleJsonRpc('equeue_display_manyOWP');