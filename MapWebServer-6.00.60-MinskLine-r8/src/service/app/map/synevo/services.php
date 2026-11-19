<?php
 require_once(__DIR__ . '/../../init.php'); class map_synevo_services { private $db; public function readServices($params) { $db = $this->db ?: $this->db = connectDb(); $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $enterpriseId = getSessionValue('mapEnterpriseCode'); $search = isset($params->search) ? " AND (sgroup.name LIKE '%$params->search%' OR sservice.name LIKE '%$params->search%')" : " "; if ($synevoLogServiceId) { return $this->readSelectedServices($synevoLogServiceId); } $sql = <<< "EOT"
        with service__ as (
            select 
                sservice.id,
                sprice.price,
                sprice.enterprisecode,
                sprice.synevoservicepricecode,
                slist.listid
            from synevoserviceprice sprice
            join synevoservice sservice on sservice.code = sprice.servicecode
            left join synevoservicelist slist on slist.serviceid = sservice.id and slist.type = 2
            left join synevoservicegroup sgroup on sgroup.id = slist.listid
            where sprice.enterprisecode = $enterpriseId
            $search
            )
            (
                select 
                ss.id "id",
                ss.code "code",
                ss.name "name",
                sp.price "price",
                ss.note "note",
                ss.discountType "discount",
                ss.workday "workday",
                (
                    select 
                    	string_agg(json_build_object(
         				'id',svs.id,
          				'name', svs.name
                        )::text, ',')
                    from synevoservicelist ssl
                    join synevoservice svs on svs.id = ssl.listid
                    where ssl.type = 1 and ssl.serviceid = ss.id     
                ) "components",
                sp.listId "parentId",
                1 "leaf"
                from service__ sp
                left join synevoservice ss on ss.id = sp.id
            )
            UNION
            (
                select 
                sg.id "id",
                sg.code "code",
                sg.name "name",
                null "price",
                null "note",
                null "discount",
                null "workday",
                null "components",
                null "parentId",
                0 "leaf"
                from synevoservicegroup sg
                join service__ s__ on s__.listid = sg.id
            )
            order by 10 asc, 3 asc
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass(); $item->id = $id = $row['id']; $item->code = $row['code']; $item->name = $row['name']; $item->price = $row['price']; $item->note = $row['note']; $item->discount = $row['discount']; $item->workday = $row['workday']; $item->isPackage = !empty($row['components']); $item->components = safe_json_decode('[' . $row['components'] . ']'); $item->parentId = $row['parentId']; $item->leaf = $row['leaf'] == 1; $items[$id] = $item; $root[] = $item; } $ret = new stdClass(); return $root; } public function readSelectedServices($synevoLogServiceId) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<< "EOT"
        select 
                logrow.serviceid "id",
                logrow.servicecode "code",
                logrow.servicename "name",
                logrow.serviceprice "price",
                (
                    select string_agg(svs.name, ',')
                    from synevoservicelist ssl
                    join synevoservice svs on svs.id = ssl.listid
                    where ssl.type = 1 and ssl.serviceid = sservice.id     
                ) "components"
        from  synevologservicerow logrow
        left join synevoservice sservice on sservice.id = logrow.serviceid
        left join synevoserviceprice sprice on sservice.code = sprice.servicecode
        left join synevoservicelist slist on slist.serviceid = sservice.id and slist.type = 1
        where logrow.serviceid is not null and logrow.synevologservicecode = $synevoLogServiceId 
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $res = $stmt->fetchAll(); return $res; } public function createSynevoOrder($params) { $ret = new stdClass(); $records = isUndefinedProperty($params, 'records', []); $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $barCode = isEmptyProperty($params, 'barCode'); if (is_object($records)) { $records = [$records]; } try { $contract = $this->getContract(); $authToken = $this->getAuthToken($contract['username'], $contract['password']); $patient = $this->getPatientInfo($synevoLogServiceId); $personId = $this->getPersonId($patient['PatientCode']); $patient['ContractId'] = $contract['synevoId']; if ($personId) { $this->updateSynevoPatient($authToken, $patient, $personId); } else { $personId = $this->createSynevoPatient($authToken, $patient); $personId && $this->savePersonId($personId, $synevoLogServiceId); } $order = $this->formationSynevoOrder($records, $patient, $barCode); $response = $this->sendSynevoOrder($authToken, $order); if ($response->OrderId) { $this->saveOrderData($response, $synevoLogServiceId); $this->saveSelectedServices($synevoLogServiceId, $records); !$personId && $this->savePersonId($response->PersonId, $synevoLogServiceId); $ret->code = 0; $ret->orderId = (int) $response->OrderId; $ret->state = 1; return $ret; } else { return false; } } catch (Throwable $e) { $this->log($e->getMessage(), 2); $ret->code = $e->getCode(); $ret->errorMessage = $e->getMessage(); $ret->orderId = null; $ret->state = null; return $ret; } } public function saveSelectedServices($synevoLogServiceId, $records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<< "EOT"
        insert into synevologservicerow (synevologservicecode, serviceId, serviceCode, serviceName, serviceAmount, servicePrice, resultSum)
        values (?, ?, ?, ?, ?, ?, ?);
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ $synevoLogServiceId, isEmptyProperty($row, 'id'), isEmptyProperty($row, 'code'), isEmptyProperty($row, 'name'), 1, isEmptyProperty($row, 'price'), isEmptyProperty($row, 'price') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function getContract() { try { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        SELECT
            sc.synevoContractCode "id",
            sc.id "synevoId",
            sc.finishDate "finishDate",
            sc.name "name",
            sc.startDate "startDate",
            sc.password "password",
            sc.username "username"
        FROM SynevoContract sc
        WHERE enterpriseCode = ? and now() between sc.startDate and sc.finishDate
    EOT;
return selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode') ])[0]; } catch (Throwable $e) { throw new Exception('Не удалось получить активный контракт', 1); } } public function checkContract() { try { $this->getContract(); return true; } catch (Throwable $e) { return false; } } private function createSynevoPatient($authToken, $patient) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/PatientService.svc/CreatePatient"; $request->request_content = json_encode($patient, JSON_UNESCAPED_UNICODE); $this->log('Создаем пациента в Synevo...'); $result = httpPUT($request); if (!empty($result)) { switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); $this->log($result->StatusMessage, 3); if ($result->IsSuccess) { return $result->PatientId; } return null; } return null; } else { $this->log('Получен пустой ответ', 3); return null; } } private function updateSynevoPatient($authToken, $patient, $personId) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/PatientService.svc/UpdatePatient"; $patient['Id'] = $personId; $request->request_content = json_encode($patient, JSON_UNESCAPED_UNICODE); $this->log('Обновляем пациента в Synevo...'); $result = httpPOST($request); if (!empty($result)) { switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); $this->log($result->StatusMessage, 3); if ($result->IsSuccess) { return true; } throw new Exception('Не удалось обновить пациента в Synevo', 4); case "400": throw new Exception('Не удалось обновить пациента в Synevo', 4); } throw new Exception('Не удалось обновить пациента в Synevo', 4); } else { $this->log('Получен пустой ответ', 3); throw new Exception('Не удалось обновить пациента в Synevo', 4); } } private function getAuthToken($userName, $password) { $request = new stdClass(); $request->url = "https://client.synevo.by/api/AuthService.svc/Token?username=" . $userName . "&password=" . $password; $result = httpGET($request); if ($result) { switch ($result['status'][1]) { case "200": $result = safe_json_decode($result['body']); $authToken = $result->AuthToken; if (empty($authToken)) { throw new Exception('Ошибка авторизации.', 2); } $this->log('Авторизация пользователя ' . $userName . ' пройдена успешно', 0); return $authToken; case "400": throw new Exception('Не удалось подключиться к серверу для авторизации.', 2); } return $result; } else { throw new Exception('Не удалось подключиться к серверу для авторизации.', 2); } } private function sendSynevoOrder($authToken, $order) { $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/OrderService.svc/Orders"; $request->request_content = $order; $this->log('Отправляем заказ...'); $result = httpPOST($request); if (!empty($result)) { switch ($result['status'][1]) { case "200": $this->log($result['status'][1] . ', ' . $result['status'][2], 3); $result = safe_json_decode($result['body']); $this->log($result->StatusMessage, 0); if ($result->IsSuccess) { return $result; } throw new Exception('Ошибка создания заказа в Synevo.', 5); case "400": $this->log($result['status'][1] . ', ' . $result['status'][2], 3); throw new Exception('Ошибка создания заказа в Synevo.', 5); } throw new Exception('Ошибка создания заказа в Synevo.', 5); } else { $this->log('Получен пустой ответ', 3); throw new Exception('Ошибка создания заказа в Synevo.', 5); } } private function formationSynevoOrder($records, $patient, $barCode) { $ret = []; $serviceCodes = []; $order = $patient; foreach ($records as $row) { $serviceCodes[] = (string) isEmptyProperty($row, 'code'); } $order['ServiceCodes'] = $serviceCodes; if (!empty($barCode)) { $order['Barcode'] = substr($barCode, 0, 10); } unset($order['Address']); $ret['order'] = $order; return json_encode($ret, JSON_UNESCAPED_UNICODE); } private function getPatientInfo($synevoLogServiceId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    SELECT
        M.name || ' ' || M.secondname "FirstName",
        M.surname "LastName",
        dateformat (MPS.dateOfBirth, 'YYYYMMDD') "DateOfBirth",
        SLS.medPatientCode "PatientCode",
        CASE WHEN MPS.sex = 0 THEN 'M' ELSE 'F' END "Gender",
        (
            SELECT
            phoneNumber
            from ManPhone
            where phoneType in (1,3) and mancode = M.manCode
            order by phoneType desc, ManPhoneCode DESC
            limit 1
        ) "Phone",
        FN_cmCalcAddress(MP.ManCode, 1) "Address",
        MPH.phoneNumber "Email"
    FROM SynevoLogService SLS
    JOIN MedPatient MP on MP.medPatientCode = SLS.medPatientCode
    JOIN Man M on M.manCode = MP.manCode
    JOIN ManPassport MPS on MPS.manCode = M.manCode
    LEFT JOIN ManPhone MPH on MPH.manCode = M.manCode and MPH.phoneType = 4
    WHERE SLS.synevoLogServiceCode = ?
EOT;
return selectSql($db, $sql, [ $synevoLogServiceId ])[0]; } private function getPersonId($patientId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    SELECT 
        personId
    FROM SynevoLogService
    WHERE medPatientCode = ? AND personId IS NOT NULL
    limit 1
EOT;
return selectSqlValue($db, $sql, [ $patientId ]); } private function savePersonId($personId, $synevoLogServiceId) { $record = new stdClass(); $record->id = $synevoLogServiceId; $record->personId = $personId; $db = $this->db ?: $this->db = connectDb(); updateSql($db, 'SynevoLogService', 'SynevoLogServiceCode', $record, "personId"); } private function saveOrderData($orderData, $synevoLogServiceId) { $record = new stdClass(); $record->id = $synevoLogServiceId; $record->orderId = $orderData->OrderId; $record->messageText = $orderData->StatusMessage; $record->barCode = $orderData->Barcode; $record->state = 1; $db = $this->db ?: $this->db = connectDb(); updateSql($db, 'SynevoLogService', 'SynevoLogServiceCode', $record, "orderId, messageText, barCode, state"); } private function getBarcodeJsonFromSynevo($barCode, $orderId) { $contract = $this->getContract(); $authToken = $this->getAuthToken($contract['username'], $contract['password']); $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/PrintingService.svc/PrintBarcode?barcode=" . $barCode; $this->log('Получаем штрихкоды в JSON...'); $result = httpGET($request); if (!empty($result)) { switch ($result['status'][1]) { case "200": $this->log($result['status'][1] . ', ' . $result['status'][2], 3); $result = $result['body']; if (empty($result)) { return null; } $this->saveBarcodeJson($orderId, $result); return safe_json_decode($result); case "400": return null; throw new Exception('Ошибка получения штрихкодов от Synevo.', 6); } return null; throw new Exception('Ошибка получения штрихкодов от Synevo.', 6); } else { return null; $this->log('Получен пустой ответ', 3); throw new Exception('Ошибка получения штрихкодов от Synevo.', 6); } } private function saveBarcodeJson($orderId, $result) { $record = new stdClass(); $record->id = $orderId; $record->json = $result; $record->state = 1; $db = $this->db ?: $this->db = connectDb(); updateSql($db, 'SynevoLogService', 'orderId', $record, "json"); } private function getBarcodePdf($params) { $barCode = isEmptyProperty($params, 'barCode'); $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $pdf = $this->getPdfFromDatabase($params, 50); if (!empty($pdf)) { if (!$pdf['fileexists']) { } return $pdf; } else { $pdf = []; $pdfContent = $this->getBarcodePdfFromSynevo($params); $this->doSaveBarCodePdf($barCode, $synevoLogServiceId, $pdfContent); $pdf['data'] = $pdfContent; return $pdf; } } private function getPdfFromDatabase($params, $source) { $db = $this->db ?: $this->db = connectDb(); $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $sql = <<< "EOT"
    SELECT
        fileexists,
        filename,
        data::bytea
      FROM MedExternalFile
      WHERE sourceCode = ? and source = ?
    EOT;
$ret = selectSql($db, $sql, [$synevoLogServiceId, $source]); return empty($ret) ? null : $ret[0]; } private function getFileForPrint($params) { $fileType = isEmptyProperty($params, 'fileType'); $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); if ($fileType == 1) { return $this->getPdfFromDatabase($params, 50); } if ($fileType == 2) { return $this->getPdfFromDatabase($params, 51); } } public function doPrintPdf($params) { $fileType = isEmptyProperty($params, 'fileType'); $file = $this->getFileForPrint($params); if ($file) { $fileName = ($file['filename'] ?? "tempfile"); $format = 'pdf'; file_put_contents($fileName . ".pdf", $file['data']); header("Content-Type: application/{$format}"); header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1'); header('Pragma: public'); header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); header('Content-Disposition: inline; filename="' . str_replace(' ', '_', $fileName) . '_' . udate('Y-m-d_H-i-s') . '.' . $format . '"'); readfile($fileName . ".pdf"); unlink($fileName . ".pdf"); error_log("Файл отправлен!"); } return false; } public function checkAndGetFileForPrint($params) { $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $barCode = isEmptyProperty($params, 'barCode'); $fileType = isEmptyProperty($params, 'fileType'); if ($fileType == 1) { if ($this->checkFileExist(50, $synevoLogServiceId)) { return 0; } else { $pdfContent = $this->getBarcodePdfFromSynevo($params); if ($pdfContent) { $this->doSaveBarCodePdf($barCode, $synevoLogServiceId, $pdfContent); return 0; } else { return 10; } } } if ($fileType == 2) { if ($this->checkFileExist(51, $synevoLogServiceId)) { return 0; } else { return 11; } } } private function checkFileExist($source, $sourceCode) { try { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    SELECT
        fileexists
      FROM MedExternalFile
      WHERE sourceCode = ? and source = ?
    EOT;
$ret = selectSqlValue($db, $sql, [$sourceCode, $source]); if (empty($ret)) { throw new Exception('Запись в базе не найдена'); } return $ret; } catch (Throwable $e) { if (empty($this->checkContract())) { throw new Exception('Печать невоможна, так как нет действующего контракта.'); }; return false; } } private function doSaveBarCodePdf($barCode, $synevoLogServiceId, $pdf) { $db = $this->db ?: $this->db = connectDb(); $fileName = 'Barcode' . $barCode; $sql = <<< "EOT"
    INSERT INTO MedExternalFileT (filename,source, sourceCode, data)
    VALUES ('$fileName', 50, $synevoLogServiceId, :PDF)
EOT;
try { $stmt = $db->prepare($sql); $stmt->bindParam(':PDF', $pdf, PDO::PARAM_LOB); $stmt->execute(); return true; } catch (Throwable $e) { return false; } } private function getBarcodePdfFromSynevo($params) { $synevoLogServiceId = isUndefinedProperty($params, 'synevoLogServiceId', 0); $barCode = isEmptyProperty($params, 'barCode'); $contract = $this->getContract(); $authToken = $this->getAuthToken($contract['username'], $contract['password']); $request = new stdClass(); $request->headers['AuthToken'] = $authToken; $request->headers['Content-Type'] = "application/json"; $request->url = "https://client.synevo.by/api/OrderService.svc/PrintPdfBarcode?barcode=" . $barCode; $this->log('Получаем штрихкоды в Pdf...'); $result = httpGET($request); if (!empty($result)) { switch ($result['status'][1]) { case "200": $this->log($result['status'][1] . ', ' . $result['status'][2], 3); $result = safe_json_decode($result['body']); if ($result->IsSuccess) { $pdf = base64_decode($result->FileName); return $pdf; } throw new Exception('Ошибка получения штрихкодов от Synevo.', 8); return null; case "400": return null; } return null; } else { return null; } } public function getOrderInfo($params) { $db = $this->db ?: $this->db = connectDb(); $synevoLogServiceId = isEmptyProperty($params, 'synevoLogServiceId'); $sql = <<< "EOT"
    SELECT
        orderId "orderId",
        personId "personId",
        barCode "barCode",
        CASE when state is null then  'Обращение к Синево не выполнялось'
             when state=1 then 'Заказ отправлен в Синево'
             when state=2 then 'От Синево получен результат по заказу'
        END "stateDisplay",
        state "state",
        json "json",
        messageText "messageText"
    FROM SynevoLogService
    WHERE SynevoLogServiceCode = ?
EOT;
$ret = new stdClass(); $order = selectSql($db, $sql, [ $synevoLogServiceId ])[0]; if (!empty($order['orderId'])) { if (empty($order['json'])) { $order['json'] = $this->getBarcodeJsonFromSynevo($order['barCode'], $order['orderId']); } else { $order['json'] = safe_json_decode($order['json']); } } $ret->order = $order; $ret->patientInfo = $this->getPatientInfo($synevoLogServiceId); return $ret; } private function log($message, $type = 0) { app_log($message, $type, 'synevo'); } } if ( $_POST && isset($_POST['download']) && isset($_POST['print']) && isset($_POST['args']) ) { $service = new map_synevo_services(); $args = safe_json_decode($_POST['args']); $service->doPrintPdf($args); } else { handleJsonRpc('map_synevo_services'); } 