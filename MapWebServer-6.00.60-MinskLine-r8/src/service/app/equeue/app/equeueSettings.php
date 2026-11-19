<?php
require_once(__DIR__ . '/../../init.php'); require_once('util/scheduler.php'); class equeue_app_equeueSettings { private $db; public function updateExtraSettings($params){ $scheduleSetup = !empty($params->scheduleSetup); $res = Scheduler::writeExtraSettings(null, $params->name, $params->value, $scheduleSetup); return $res; } public function getCategoriesFromExternalMIS($params){ $url = $params->url; $request = new stdClass(); $request->url = $url; $result = httpGET($request); if (empty($result)){ $result = []; $result['error'] = true; $result['errorDescription'] = 'Нет ответа от ' . $request->url; return $result; } $result['error'] = false; $result['errorDescription'] = ''; switch($result['status'][1]){ case "200": $body = safe_json_decode($result['body']); $this->saveCategoriesToDB($body); break; case "400": $result['error'] = true; $result['errorDescription'] = $request->url . '. Ошибка 400. Неверный запрос.'; break; case "404": $result['error'] = true; $result['errorDescription'] = $request->url . '. Ошибка 404. Ресурс недоступен.'; break; } return $result; } private function saveCategoriesToDB($data){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            INSERT INTO medEqPatientCategoryExtLink AS e (code,
                                            enterpriseCode,
                                            name)
            VALUES (?, ?, ?)
            ON CONFLICT (code, enterpriseCode)
            DO UPDATE
            SET name = CASE WHEN ? = 1 THEN EXCLUDED.name ELSE e.name END
EOT;
$stmt = $db->prepare($sql); foreach($data as $row){ $stmt->execute([ isEmptyProperty($row, 'code'), $enterpriseId, isEmptyProperty($row, 'display'), propertyExistsSQL($row, 'display') ]); } } public function deleteExternalMISData(){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            DELETE FROM medEqPatientCategoryExtLink
            WHERE enterpriseCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseId ]); return true; } } handleJsonRpc('equeue_app_equeueSettings');