<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_singleOWP { private $db; private $timeout = 300; public function getInvitedTalon($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $sql = <<<EOT
            SELECT
                mec.medEqQueueCouponCode id,
                mec.createTime,
                mec.documentNumber,
                mec.startTime,
                mec.state,
                mec.callCount
            FROM medEqQueueCoupon mec
            LEFT JOIN medEqDisplayOwp meo on meo.medEqOwpCode = mec.medEqOwpCode
            LEFT JOIN medEqDisplay med on med.medEqDisplayCode = meo.medEqDisplayCode
            WHERE mec.state = 1
                AND med.displayNumber LIKE ?
                AND med.enterpriseCode = ?
            ORDER BY startTime
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqInvitedTalonsHash'); if ($savedHash != $retHash){ setSessionValue('webEqInvitedTalonsHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); return $ret; } } handleJsonRpc('equeue_display_singleOWP');