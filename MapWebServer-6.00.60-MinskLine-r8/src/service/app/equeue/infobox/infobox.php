<?php
require_once(__DIR__.'/../../init.php'); class equeue_infobox_infobox { private $db; const BARCODE = 41; const TALONBY = 42; const EXTERNALMISBARCODE = 43; public function readSettings($infoboxName){ $settings = readJsonSettings('settings_equeueinfobox'); if ($settings && isset($settings['infoboxes']) && isset($settings['infoboxes'][$infoboxName])) { $settings['infoboxes'][$infoboxName]['date'] = date('D M d Y H:i:s'); return $settings['infoboxes'][$infoboxName]; } else { return null; } } public function readData($infoboxName){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                b.medEqInfoboxCode id,
                e.enterpriseCode "enterpriseId",
                e.name "enterpriseName"
            FROM medEqInfobox b
            JOIN enterprise e ON e.enterpriseCode = b.enterpriseCode
            WHERE b.name = ?
                AND b.active = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$infoboxName]); $infobox = null; if (!($row = $stmt->fetch())){ return null; } $infobox = $row; $sql = <<<EOT
            SELECT
                c.medEqPatientCategoryCode "id",
                c.name,
                c.note,
                c.type,
                STRING_AGG((sc.medEqServiceCode )::TEXT, E',' ORDER BY sc.priority NULLS FIRST) "servicesId",
                JSON_AGG(
                    JSON_BUILD_OBJECT(
                        'serviceId', sc.medEqServiceCode,
                        'priority', COALESCE(sc.priority, 0)
                    )
                ) AS "services",
                (
                    SELECT COALESCE(JSON_AGG(MPCI.medprivelegecode), '[]'::json)
                    FROM MedEqPatientCategoryIn MPCI
                    WHERE MPCI.medEqPatientCategoryCode = c.medEqPatientCategoryCode
                ) "medPrivilegeCodes"
            FROM medEqPatientCategory c
            LEFT OUTER JOIN medEqServiceCategory sc ON sc.medEqPatientCategoryCode = c.medEqPatientCategoryCode
                AND sc.active = 1
            WHERE c.active = 1
            GROUP BY c.medEqPatientCategoryCode, c.name, c.note, c.type
            ORDER BY 4, 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $categories = []; $category = null; while ($row = $stmt->fetch()) { $categories[] = $category = new stdClass; $category->id = (int)$row['id']; $category->name = $row['name']; $category->type = $row['type']; isset($row['note']) && $category->note = $row['note']; $category->servicesId = $row['servicesId']; $category->services = json_decode($row['services'], true); $category->medPrivilegeCodes = json_decode($row['medPrivilegeCodes'], true); } $sql = <<<EOT
            SELECT
                s.medEqServiceCode id,
                s.name,
                s.cipher,
                s.note,
                s.label,
                s.quota,
                s.proceedTime                   "proceedTime",
                fn_cmUniTimeTable(LOCALTIMESTAMP, 1, s.medEqServiceCode)
                                                "timeCode",
                (SELECT MIN(startTime) FROM uniTimeTable
                    WHERE uniTimeTableCode = fn_cmUniTimeTable(LOCALTIMESTAMP, 1, s.medEqServiceCode))
                                                "startTime",
                (SELECT MAX(finishTime) FROM uniTimeTable
                    WHERE uniTimeTableCode = fn_cmUniTimeTable(LOCALTIMESTAMP, 1, s.medEqServiceCode))
                                                "finishTime",
                so.medEqServiceOptionCode       "optionId",
                so.name                         "optionName",
                so.label                        "optionLabel",
                so.note                         "optionNote",
                so.proceedTime                  "optionProceedTime",
                bs.position                     "servicePosition",
                so.position                     "optionPosition"
            FROM medEqInfobox b
            JOIN medEqInfoboxService bs ON bs.medEqInfoboxCode = b.medEqInfoboxCode
            JOIN medEqService s ON s.enterpriseCode = b.enterpriseCode
                AND s.medEqServiceCode = bs.medEqServiceCode
                AND s.active = 1
            LEFT OUTER JOIN medEqServiceOption so ON so.medEqServiceCode = s.medEqServiceCode
                AND so.active = 1
                AND ((SELECT COUNT(*) FROM uniTimeTable WHERE source = 3 AND sourceCode = so.medEqServiceOptionCode) = 0
                    OR ((SELECT COUNT(*) FROM uniTimeTable WHERE source = 3 AND sourceCode = so.medEqServiceOptionCode) > 0
                        AND fn_cmUniTimeTable(LOCALTIMESTAMP, 3, so.medEqServiceOptionCode) IS NOT NULL))
            WHERE b.medEqInfoboxCode = ?
                AND b.active = 1
                AND fn_cmUniTimeTable(LOCALTIMESTAMP, 1, s.medEqServiceCode) IS NOT NULL
            ORDER BY 16, 17
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$infobox['id']]); $services = []; $service = null; while ($row = $stmt->fetch()){ if (!$service || $service->id !== (int)$row['id']) { $services[] = $service = new stdClass; $service->id = (int)$row['id']; $service->name = $row['name']; isset($row['cipher']) && $service->cipher = $row['cipher']; isset($row['note']) && $service->note = $row['note']; } if (isset($row['optionId'])){ $service->options[] = $option = new stdClass; $option->id = (int)$row['optionId']; $option->name = $row['optionName']; isset($row['optionLabel']) && $option->label = $row['optionLabel']; isset($row['optionNote']) && $option->note = $row['optionNote']; } } $ret = new stdClass; $ret->infobox = $infobox; $ret->categories = $categories; $ret->services = $services; $ret->date = date('D M d Y H:i:s'); return $ret; } public function initService($services){ $db = $this->db ?: $this->db = connectDb(); $sql = "SELECT PR_medEqInitServiceQueues(?)"; $stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($services as &$s){ $stmt->execute([$s]); } $db->commit(); } catch(Throwable $e){ $db->rollback(); throw $e; } return true; } public function sendToQueue($params){ $db = $this->db ?: $this->db = connectDb(); $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ isEmptyProperty($params, 'patient'), isEmptyProperty($params, 'study'), isEmptyProperty($params, 'service'), isEmptyProperty($params, 'option'), isEmptyProperty($params, 'category'), isEmptyProperty($params, 'infobox'), isEmptyProperty($params, 'pin') ]); $db->commit(); }catch(Throwable $e){ $db->rollback(); throw $e; } if (!($row = $stmt->fetch())){ return null; } $sql = <<<EOT
            SELECT
                mec.createTime                  "createTime",
                TRIM(mec.documentNumber)        "number",
                mec.medEqPatientCategoryCode    "categoryId",
                mep.name                        "categoryName"
            FROM medEqQueueCoupon mec
            LEFT JOIN medEqPatientCategory mep ON mep.medeqpatientcategorycode = mec.medeqpatientcategorycode
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $row['id'] ]); $data = $stmt->fetch(); if ($data){ $row['ticketCreateTime'] = $data['createTime']; $row['number'] = $data['number']; $row['category'] = new stdClass; $row['category']->id = $data['categoryId']; $row['category']->name = $data['categoryName']; } $sql = <<<EOT
            SELECT COUNT(*) "ticketsAmount"
            FROM medEqQueueCoupon
            WHERE medEqServiceCode = ?
                AND state = 0
                    OR state = -1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'service') ]); $data = $stmt->fetch(); if ($data){ $row['ticketsAmount'] = $data['ticketsAmount']; } return $row; } public function checkBarcode($params){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $barcode = isEmptyProperty($params, 'barcode'); $enterpriseId = isEmptyProperty($params, 'enterpriseId'); switch($params->type){ case self::BARCODE: $sql = 'SELECT FN_medEqBarCodeCheck(?, ?) "value"'; $stmt = $db->prepare($sql); $stmt->execute([ $barcode, $enterpriseId ]); if (!($row = $stmt->fetch())){ return null; } $ret = $row['value']; break; case self::TALONBY: if (safe_strlen($barcode) < 6){ $barcode = substr('0000' . $barcode, -5); } $sql = <<<EOT
                    SELECT
                        m.medEqQueueCouponCode      "talonId",
                        m.createTime                "createTime",
                        m.startTime                 "startTime",
                        TRIM(m.documentNumber)      "talonNumber",
                        m.medEqServiceCode          "serviceId",
                        mes."name"                  "serviceName",
                        mes.cipher                  "serviceCipher",
                        m.medEqServiceOptionCode    "optionId",
                        mso."name"                  "optionName",
                        mso."label"                 "optionLabel",
                        m.medEqPatientCategoryCode  "categoryId",
                        mpc."name"                  "categoryName",
                        m.medPatientCode            "patientId",
                        mp.manCode                  "personId",
                        FN_cmFIO(mp.manCode)        "personName",
                        m.pin,
                        m.state,
                        (SELECT COUNT(*) FROM medEqQueueCoupon WHERE medEqServiceCode = m.medEqServiceCode AND state IN (0, -1))
                                                    "ticketsAmount",
                        100                         "type"
                    FROM medEqQueueCoupon m
                    LEFT JOIN medPatient mp ON mp.medPatientCode = m.medPatientCode
                    LEFT JOIN medEqService mes ON mes.medEqServiceCode = m.medEqServiceCode
                    LEFT JOIN medEqServiceOption mso ON mso.medEqServiceOptionCode = m.medEqServiceOptionCode
                    LEFT JOIN medEqPatientCategory mpc ON mpc.medEqPatientCategoryCode = m.medEqPatientCategoryCode
                    WHERE m.pin = ?
                    LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $barcode ]); $data = $stmt->fetch(); $ret = json_encode($data); if (!$data){ $sql = <<<EOT
                        SELECT FN_medEqBarCodeCheck(FN_barCode_MakeLogStudy(medLogStudyCode), ?) "value"
                        FROM medLogStudy
                        WHERE pin = ?
                            AND dirTime > ('NOW'::DATE - INTERVAL '6 month')
                        ORDER BY dirTime DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseId, $barcode ]); if (!($row = $stmt->fetch())){ return null; } $ret = $row['value']; } break; case self::EXTERNALMISBARCODE: $ret = new stdClass(); $request = new stdClass(); $request->url = $params->externalMisUrl; $request->headers['Content-Type'] = "application/x-www-form-urlencoded"; $request->request_content = http_build_query(array('Identifier' => $params->barcode)); $result = httpPOST($request); $ret->type = 200; $ret->status = $result['status']; switch($result['status'][1]){ case "200": $body = safe_json_decode($result['body']); $ret->ExternalMisCategoryCode = $body[0]->code; $ret->ExternalMisCategoryName = $body[0]->display; $sql = <<<EOT
                            SELECT mep.medEqPatientCategoryCode     "categoryId",
                                   mep.name                         "categoryName"
                            FROM medEqPatientCategoryExtLink mec
                            LEFT JOIN medEqPatientCategory mep ON mep.medEqPatientCategoryCode = mec.medEqPatientCategoryCode
                            WHERE mec.code = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $ret->ExternalMisCategoryCode ]); $data = $stmt->fetch(); $ret->CategoryId = $data['categoryId']; $ret->CategoryName = $data['categoryName']; break; case "400": break; case "404": break; } $ret = json_encode($ret); break; } return $ret; } public function changeTalonState($params){ $db = $this->db ?: $this->db = connectDb(); $talonId = $params->talonId; $state = $params->state; $sql = <<<EOT
            UPDATE medEqQueueCoupon SET
                state = ?
            WHERE medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $state, $talonId ]); } public function restoreTalon($talon){ $db = $this->db ?: $this->db = connectDb(); $ret = new stdClass; $talonId = isEmptyProperty($talon, 'talonId'); $talonNumber = isEmptyProperty($talon, 'talonNumber'); $patientId = isEmptyProperty($talon, 'patientId'); $logstudyId = null; $serviceId = isEmptyProperty($talon, 'serviceId'); $optionId = isEmptyProperty($talon, 'optionId'); $categoryId = isEmptyProperty($talon, 'categoryId'); $infoboxId = isEmptyProperty($talon, 'infoboxId'); $pin = null; $sql = 'SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id'; $stmt = $db->prepare($sql); $stmt->execute([ $patientId, $logstudyId, $serviceId, $optionId, $categoryId, $infoboxId, $pin ]); $newTalonId = (int)fetchValue($stmt, 'id'); if ($newTalonId > 0){ $ret->error = false; $sql = <<<EOT
                UPDATE MedEqQueueCoupon
                    SET documentNumber = ?
                WHERE MedEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $talonNumber, $newTalonId ]); $sql = <<<EOT
                SELECT
                    m.medEqQueueCouponCode      "talonId",
                    m.createTime                "createTime",
                    m.startTime                 "startTime",
                    TRIM(m.documentNumber)      "talonNumber",
                    m.medEqServiceCode          "serviceId",
                    mes."name"                  "serviceName",
                    mes.cipher                  "serviceCipher",
                    m.medEqServiceOptionCode    "optionId",
                    mso."name"                  "optionName",
                    mso."label"                 "optionLabel",
                    m.medEqPatientCategoryCode  "categoryId",
                    mpc."name"                  "categoryName",
                    m.medPatientCode            "patientId",
                    mp.manCode                  "personId",
                    FN_cmFIO(mp.manCode)        "personName",
                    m.pin,
                    m.state,
                    (SELECT COUNT(*) FROM medEqQueueCoupon WHERE medEqServiceCode = m.medEqServiceCode AND state IN (0, -1))
                                                "ticketsAmount"
                FROM medEqQueueCoupon m
                LEFT JOIN medPatient mp ON mp.medPatientCode = m.medPatientCode
                LEFT JOIN medEqService mes ON mes.medEqServiceCode = m.medEqServiceCode
                LEFT JOIN medEqServiceOption mso ON mso.medEqServiceOptionCode = m.medEqServiceOptionCode
                LEFT JOIN medEqPatientCategory mpc ON mpc.medEqPatientCategoryCode = m.medEqPatientCategoryCode
                WHERE m.medEqQueueCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $newTalonId ]); $ret->talonData = $stmt->fetch(); } else { $ret->error = true; $ret->errorCode = $newTalonId; } return $ret; } } handleJsonRpc('equeue_infobox_infobox');