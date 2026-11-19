<?php
require_once(__DIR__.'/../../init.php'); class equeue_display_master { private $db; private $timeout = 300; public function readMasterActiveTalonsList($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseCode = $params->webEnterpriseCode; $sql = <<<EOT
            SELECT
                enterpriseCode "enterpriseId",
                medEqServiceCode "medEqServiceCode",
                medEqQueueCode "medEqQueueCode",
                medEqQueueCouponCode "medEqQueueCouponCode",
                cito,
                priority,
                DATE_TRUNC('second', createtime) "createTime",
                state,
                documentNumber "documentNumber",
                withinServiceNumber "withinServiceNumber",
                cipher
            FROM PR_medEqGetCouponList()
            WHERE enterpriseCode = ?
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $webEnterpriseCode ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqTalonsHash'); if ($savedHash != $retHash){ setSessionValue('webEqTalonsHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); return $ret; } public function readMasterTalonsInvited($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $sql = <<<EOT
            SELECT
                ec.medEqQueueCouponCode id,
                ec.documentNumber "documentNumber",
                ec.state,
                DATE_TRUNC('second', ec.startTime) "startTime",
                eo.cabinet
            FROM medEqQueueCoupon ec
            LEFT JOIN medEqOwp eo ON eo.medEqOwpCode = ec.medEqOwpCode
            WHERE state in (1, 2, 3)
                AND ec.medEqServiceCode IN (
                    SELECT mes.medEqServiceCode
                    FROM medEqDisplay md
                    LEFT JOIN medEqDisplayService mds ON mds.medEqDisplayCode = md.medEqDisplayCode
                    LEFT JOIN medEqService mes ON mes.medEqServiceCode = mds.medEqServiceCode
                    WHERE md.displayNumber LIKE ?
                        AND mes.enterpriseCode = ?
                )
            ORDER BY startTime DESC
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqInvitedTalonsHash'); if ($savedHash != $retHash){ setSessionValue('webEqInvitedTalonsHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); return $ret; } public function getDisplayData($params){ $db = $this->db ?: $this->db = connectDb(); $webEnterpriseCode = isEmptyProperty($params, 'webEnterpriseCode'); $displayNumber = isEmptyProperty($params, 'displayNumber'); $sql = <<<EOT
            SELECT
                COALESCE(mes.name, mes2.name) "serviceName",
                COALESCE(mes.cipher, mes2.cipher) cipher,
                mds.position,
                ARRAY_TO_STRING(
                    ARRAY(
                        SELECT COALESCE(mes.medEqServiceCode, mes2.medEqServiceCode)
                        FROM medEqDisplay med
                        LEFT JOIN medEqDisplayOwp mdo ON mdo.medEqDisplayCode = med.medEqDisplayCode
                        LEFT JOIN medEqOwp mo ON mo.medEqOwpCode = mdo.medEqOwpCode
                        LEFT JOIN medEqService mes ON mes.medEqServiceCode = mo.medEqServiceCode
                        LEFT JOIN medEqDisplayService mds ON mds.medEqDisplayCode = med.medEqDisplayCode AND med.displayType = 1
                        LEFT JOIN medEqService mes2 ON mes2.medEqServiceCode = mds.medEqServiceCode
                        WHERE med.displayNumber LIKE :displayNumber
                            AND med.enterpriseCode = :webEnterpriseCode
                            AND fn_cmUniTimeTable(CLOCK_TIMESTAMP()::TIMESTAMP, 1, COALESCE(mes.medEqServiceCode, mes2.medEqServiceCode)) IS NOT NULL
                        ORDER BY mds.position
                    ), '-'
                ) "uniqueSequence"
            FROM medEqDisplay med
            LEFT JOIN medEqDisplayOwp mdo ON mdo.medEqDisplayCode = med.medEqDisplayCode
            LEFT JOIN medEqOwp mo ON mo.medEqOwpCode = mdo.medEqOwpCode
            LEFT JOIN medEqService mes ON mes.medEqServiceCode = mo.medEqServiceCode
            LEFT JOIN medEqDisplayService mds ON mds.medEqDisplayCode = med.medEqDisplayCode AND med.displayType = 1
            LEFT JOIN medEqService mes2 ON mes2.medEqServiceCode = mds.medEqServiceCode
            WHERE med.displayNumber LIKE :displayNumber
                AND med.enterpriseCode = :webEnterpriseCode
                AND fn_cmUniTimeTable(CLOCK_TIMESTAMP()::TIMESTAMP, 1, COALESCE(mes.medEqServiceCode, mes2.medEqServiceCode)) IS NOT NULL
            ORDER BY mds.position
EOT;
$sql2 = <<<EOT
            SELECT
                marqueeText "marqueeText",
                reload,
                useVoice "useVoice",
                voice,
                phrase1,
                phrase2,
                showVideo "showVideo"
            FROM medEqDisplay
            WHERE displayNumber LIKE ?
                AND enterpriseCode = ?
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $ret = new stdClass; $stmt = $db->prepare($sql); $stmt->execute([ ':displayNumber' => $displayNumber, ':webEnterpriseCode' => $webEnterpriseCode ]); $ret->data = $stmt->fetchAll(); $stmt = $db->prepare($sql2); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $displayData = $stmt->fetch(); if ($displayData){ $ret->marqueeText = $displayData['marqueeText']; $ret->reload = $displayData['reload']; $ret->useVoice = $displayData['useVoice']; $ret->voice = $displayData['voice']; $ret->phrase1 = $displayData['phrase1']; $ret->phrase2 = $displayData['phrase2']; $ret->showVideo = $displayData['showVideo']; } $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqDisplayDataHash'); if ($savedHash != $retHash){ setSessionValue('webEqDisplayDataHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; $ret = null; } gc_collect_cycles(); sleep(1); } while($doLoop); if ($ret){ $ret->date = date('D M d Y H:i:s'); } return $ret; } public function getPatientsToCall($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $webEnterpriseCode = isEmptyProperty($params, 'webEnterpriseCode'); $displayNumber = isEmptyProperty($params, 'displayNumber'); $sql = <<<EOT
            SELECT
                ec.medEqQueueCouponCode id,
                ec.createTime "createTime",
                ec.documentNumber "documentNumber",
                ec.startTime "startTime",
                ec.state,
                ec.callCount "callCount",
                eo.cabinet
            FROM medEqQueueCoupon ec
            LEFT JOIN medEqOwp eo ON eo.medEqOwpCode = ec.medEqOwpCode
            WHERE ec.state = 1
                AND ec.medEqServiceCode IN (
                    SELECT mes.medEqServiceCode
                    FROM medEqDisplay md
                    LEFT JOIN medEqDisplayService mds ON mds.medEqDisplayCode = md.medEqDisplayCode
                    LEFT JOIN medEqService mes ON mes.medEqServiceCode = mds.medEqServiceCode
                    WHERE md.displayNumber LIKE ?
                        AND mes.enterpriseCode = ?
                )
            ORDER BY ec.startTime
EOT;
$doLoop = true; $finishTime = time() + $this->timeout; do { $stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $ret = $stmt->fetchAll(); $retHash = md5(json_encode($ret)); $savedHash = getSessionValue('webEqPatientsToCallHash'); if ($savedHash != $retHash){ setSessionValue('webEqPatientsToCallHash', $retHash); $doLoop = false; } if (time() > $finishTime){ $doLoop = false; } gc_collect_cycles(); sleep(1); } while($doLoop); return $ret; } public function resetReloadFlag($params){ $db = $this->db ?: $this->db = connectDb(); $displayNumber = $params->displayNumber; $webEnterpriseCode = $params->webEnterpriseCode; $sql = 'UPDATE medEqDisplay SET reload = 0 WHERE displayNumber = ? AND enterpriseCode = ?'; $stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); return true; } public function getServerTime(){ $ret = new stdClass; $ret->date = date('D M d Y H:i:s'); return $ret; } public function loadVideoFilesListForDisplay($params){ $db = $this->db ?: $this->db = connectDb(); $webEnterpriseCode = $params->webEnterpriseCode; $displayNumber = $params->displayNumber; $result = []; $sql = <<<EOT
            SELECT mvl.filename
            FROM medEqVideoProgramList mvl
            JOIN medEqVideoProgram mvp ON mvp.medEqVideoProgramCode = mvl.medEqVideoProgramCode
            JOIN medEqDisplay md ON md.medEqVideoProgramCode = mvp.medEqVideoProgramCode
            WHERE md.displayNumber LIKE ?
                AND md.enterpriseCode = ?
            ORDER BY mvl.position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $displayNumber, $webEnterpriseCode ]); $result['data'] = $stmt->fetchAll(); $result['HTTP_ORIGIN'] = $_SERVER['HTTP_ORIGIN']; return $result; } } handleJsonRpc('equeue_display_master');