<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_segment { private $db; private $timeout = 300; public function getSegmentDisplayData($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseId = $params->webEnterpriseId; $sql = <<<EOT
            SELECT
                mec.medEqQueueCouponCode id,
                mec.createTime "createTime",
                TRIM(mec.documentNumber) "documentNumber",
                mec.startTime "startTime",
                mec.state,
                mec.callCount "callCount",
                med.blinkCount "blinkCount",
                med.displayNumber "displayNumber",
                med.ipAddress "ipAddress",
                med.ipPort "ipPort",
                med.portNumber "portNumber",
                med.portSpeed "portSpeed",
                CASE WHEN med.displayKind = 2 THEN 1 ELSE 0 END "color",
                c1.color "letterColor",
                c2.color "digitsColor",
                c3.color "borderColor1",
                c4.color "borderColor2"
            FROM medEqQueueCoupon mec
            LEFT JOIN medEqDisplayOwp meo ON meo.medEqOwpCode = mec.medEqOwpCode
            LEFT JOIN medEqDisplay med ON med.medEqDisplayCode = meo.medEqDisplayCode
            LEFT JOIN medEqDisplayColor c1 ON c1.medEqDisplayCode = med.medEqDisplayCode AND c1.type = 1
            LEFT JOIN medEqDisplayColor c2 ON c2.medEqDisplayCode = med.medEqDisplayCode AND c2.type = 2
            LEFT JOIN medEqDisplayColor c3 ON c3.medEqDisplayCode = med.medEqDisplayCode AND c3.type = 3
            LEFT JOIN medEqDisplayColor c4 ON c4.medEqDisplayCode = med.medEqDisplayCode AND c4.type = 4
            WHERE mec.state = 1
                AND med.displayKind IN (1, 2)
                AND med.enterpriseCode = ?
            ORDER BY startTime
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $webEnterpriseId ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqInvitedTalonsHash'); if ($savedHash != $retHash){ setSessionValue('webEqInvitedTalonsHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); return $ret; } } handleJsonRpc('equeue_display_segment');