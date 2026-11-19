<?php
require_once(__DIR__ . '/../../init.php'); class med_lab_linkLis { const TASK_NAME = 'medLabLinkLis'; const DEBUG_HTTP = false; const ONE_DIRECTION_ERROR_CODE = 499; const REFERRAL_NOT_FOUND_CODE = 601; private $db; private $extraSettings; private $tests; private $cfg = null; private $studyId = null; private $syncErrorMessage = null; private $isSendOneDirection = false; public function readLabStudy($params = null) { $db = $this->getDb(); $id = isEmptyProperty($params, 'id') ? $params->id : 0; $sql = <<<EOT
            SELECT
                sec.MedLabStudySectionCode * -1 "id",
                null                            "parentId",
                sec.Name                        "name",
                'Код ЛИС : '|| sec.lisid        "description",
                null                            "studyName",
                null                            "studyId",
                1                               "active",
                0                               "leaf"
            FROM MedLabStudySection sec
            WHERE $id = 0 AND EXISTS (
                SELECT 1
                FROM MedLabStudy MLS
                WHERE MLS.medLabStudySectionCode = sec.medLabStudySectionCode
                    AND MLS.elisType IS NULL
            )
            UNION ALL
            SELECT
                st.MedLabStudyCode              "id",
                st.MedLabStudySectionCode * -1  "parentId",
                st.Name                         "name",
                st.Description                  "description",
                case p.IsTemplate when 1 then '(МАП) ' else '' end || ms.Name "studyName",
                st.MedStudyCode                 "studyId",
                st.Active                       "active",
                1                               "leaf"
            FROM MedLabStudy st
            LEFT JOIN MedStudy ms ON ms.MedStudyCode = st.MedStudyCode
            LEFT JOIN htmPageT p ON p.htmPageCode = ms.htmPageCode
            WHERE ($id = 0 OR st.MedLabStudyCode = $id) AND st.elisType IS NULL
            ORDER BY name
EOT;
return selectSql($db, $sql); } public function updateLabStudy($records) { $db = $this->getDb(); $sql = <<<"EOT"
UPDATE medLabStudy SET
  MedStudyCode = case when ? = 1 then ? else MedStudyCode end
WHERE medLabStudyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'studyId'), isEmptyProperty($row, 'studyId'), $row->id ]); $param = new stdClass; $param->id = $row->id; $ret = $this->readLabStudy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readLabStudyTest($params = null) { $db = $this->getDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : 0; $sql = <<<EOT
            SELECT
                T.medLabStudyTestCode       "id",
                (
                    SELECT medLabStudyTestCode
                    FROM MedLabStudyTest
                    WHERE LisTestId = T.MedLabStudyTestCodeParent
                        AND MedLabStudyCode = T.MedLabStudyCode
                )                           "parentId",
                CASE WHEN T.medLabStudyTestCodeParent IS NULL
                    THEN
                        CASE WHEN EXISTS (
                                SELECT 1
                                FROM medLabStudyTest
                                WHERE medLabStudyTestCodeParent = T.LisTestId
                            )
                        THEN 0
                        ELSE 1
                        END
                    ELSE 1
                END                         "leaf",
                T.medLabStudyCode           "studyId",
                T.name                      "name",
                T.active                    "active",
                T.description               "description",
                T.isInput                   "isInput",
                T.medLabStudyTestCodeLink   "linkId",
                T.defaultFlag               "defaultFlag",
                T.internalId                "internalId",
                T.type                      "type",
                T.loincCode                 "loincCode",
                T.unitName                  "unitName",
                S.MedStudyCode              "medStudyId"
            FROM medLabStudyTest T
            JOIN medLabStudy S ON S.medLabStudyCode = T.medLabStudyCode
            WHERE ($id = 0 OR T.medLabStudyTestCode = $id)
            ORDER BY T.name
EOT;
return selectSql($db, $sql); } public function updateLabStudyTest($records) { $db = $this->getDb(); $sql = <<<"EOT"
UPDATE medLabStudyTest SET
  UnitName = case when ? = 1 then ? else UnitName end
WHERE medLabStudyTestCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'unitName'), isEmptyProperty($row, 'unitName'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getElements($params = null) { $db = $this->getDb(); $labStudyId = isEmptyProperty($params, 'labStudyId') ? $params->labStudyId : 0; $unitName = isEmptyProperty($params, 'unitName') ? $params->unitName : ''; $unitName = ',' . $unitName . ','; $sql = <<<"EOT"
SELECT DISTINCT
       U.Name  "name",
       CAST(CASE CAST('$unitName' AS text) LIKE  E'%,' || U.Name ||  E',%'
                WHEN true THEN 1
                WHEN false THEN 0
            END AS int4) "checked"
FROM medLabStudy AS MLS
     JOIN medStudy AS S ON S.MedStudyCode = MLS.MedStudyCode
     JOIN htmUnit AS U ON U.htmPageCode = S.htmPageCode
WHERE MLS.medLabStudyCode = $labStudyId
    and u.Name not like '%_NormalValue'
    and u.Name not like '%_NormalLow'
    and u.Name not like '%_NormalHigh'
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function startExchange() { $this->log('Запуск функции полного обмена данными startExchange()...'); try { $this->getSettings(); $message1 = $this->synchronizeStudies(); $message2 = $this->sendDirections(); $message3 = $this->receiveResults(); $message4 = $this->receiveResultsByMisGuid(); $this->log('Работа функции полного обмена данными startExchange() завершена'); $this->log("$message1 <br> $message2 <br> $message3 <br> $message4"); return "$message1 <br> $message2 <br> $message3 <br> $message4"; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате выполнения функции полного обмена данными произошла ошибка: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } public function sendDirectionOne($cfg, $studyId) { $this->cfg = $cfg; $this->studyId = $studyId; $this->isSendOneDirection = true; $this->log("Запуск функции отправки одного направления sendDirectionOne($cfg, $studyId)..."); try { if (!$this->cfg || !$this->studyId) { throw new Exception('Входные параметры переданы не полностью'); } $settings = $this->getSettings(); if (isUndefinedProperty($settings, 'active')) { $this->sendDirections(); } $this->log('Работа функции отправки одного направления sendDirectionOne завершена'); $this->updateDirectionFailure(null, null, false); return true; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате выполнения функции отправки одного направления произошла ошибка: $message", 2); $this->updateDirectionFailure(null, null, false); throw $e; } } public function getResultPdfOne($cfg, $studyId) { $this->cfg = $cfg; $this->studyId = $studyId; $db = $this->getDb(); $hasPdf = false; $this->log("Запуск функции получения PDF-результата одного направления getResultPdfOne($cfg, $studyId)..."); try { if (!$this->cfg || !$this->studyId) { throw new Exception('Входные параметры переданы не полностью'); } $settings = $this->getSettings(); if (isUndefinedProperty($settings, 'active')) { $sql = <<<"EOT"
select
    ls.MedLabStudyCode "AnalysisId",
    mls.Barcode "SequenceValue"
from MedLogStudy mls
join MedLabStudy ls on ls.MedStudyCode = mls.MedStudyCode
    and ls.Active = 1
where mls.MedLogStudyCode = ?
    and mls.State = 2
    and mls.BiopsySyncStatus in (3, 4)
limit 1
EOT;
$row = selectSql($db, $sql, [$studyId]); if (!is_array($row)) { throw new Exception('Направление не выполнено или не имеет результатов'); } $row = arrayToObject(reset($row), 'stdClass'); if (!isUndefinedProperty($row, 'AnalysisId')) { throw new Exception('Отсутствует анализ СОЛИС'); } if (!isUndefinedProperty($row, 'SequenceValue')) { throw new Exception('Отсутствует штрихкод направления'); } if (!$params = httpCreateParam($settings)) { throw new Exception('Ошибка инициализация подключения'); } $params->request_method = 'result/getresultsbyanalysisidandsequencevalue'; $params->request_content = json_encode([$row], JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); break; default: $result = $result ? : $response['body']; throw new Exception("  При получении PDF-результата одного направления произошла ошибка: $result"); } $result = reset($result); if (is_object($result)) { $result = isUndefinedProperty($result, 'Pdf'); } if (!$result) { throw new Exception("  Отсутствует PDF-документ с результатами"); } $result = base64_decode($result); header("Content-Type: application/pdf"); header("Cache-control: private, no-store, no-cache, must-revalidate, max-age=0"); header("Connection: close"); header("Content-Length: ". safe_strlen($result)); echo $result; $hasPdf = true; $this->log(' PDF-результат получен'); } else { throw new Exception("  Невреная настройка обмена информацией или обмен данными запрещен"); } $this->log('Работа функции получения PDF-результата одного направления getResultPdfOne завершена'); if ($hasPdf) { die(); } return true; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате выполнения функции получения PDF-результата одного направления произошла ошибка: $message", 2); throw $e; } } public function synchronizeStudies() { $db = $this->getDb(); $settings = $this->getSettings(); $this->log("  Синхронизация справочника исследований..."); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (synchronizeStudies)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/getlaboratoriesbymisguid'; $params->param = array( 'guid' => $settings->misId ); $params->debug = self::DEBUG_HTTP; $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $laboratories = safe_json_decode($response['body']); break; default: $result = $result ? : $response['body']; $message = "  При получении справочника исследований произошла ошибка: $result"; $this->log($message, 2); $this->log("Выполнение прервано", 2); return $message; } $sqlSelectMedLabStudySection = <<<SQL
            SELECT MedLabStudySectionCode
            FROM MedLabStudySection
            WHERE LisSectionId = ?  AND ELisType IS NULL
            SQL;
$sqlInsertMedLabStudySection = <<<SQL
            INSERT INTO MedLabStudySection (LisSectionId, Name, InternalId, LisId, ELisType)
            VALUES (?, ?, ?, ?, null)
            SQL;
$sqlUpdateMedLabStudySection = <<<SQL
            UPDATE MedLabStudySection SET
                Name = ?
            WHERE MedLabStudySectionCode = ? AND ELisType IS NULL
            SQL;
$sqlSelectMedLabStudy = <<<SQL
            SELECT MedLabStudyCode "medLabStudyId"
            FROM MedLabStudy
            WHERE LisStudyId = ? AND ELisType IS NULL
            SQL;
$sqlInsertMedLabStudy = <<<SQL
            INSERT INTO MedLabStudy (
                LisStudyId, MedLabStudySectionCode, InternalId,
                Name, Description, ProcedureRoom,
                Active, BarcodeType, RequiredDiagnosis,
                ShortName, BarCodeWideBarRatio, BarCodeZoom,
                PregnancyObligatory, AnalyserName, AnalyserDescription, ELisType
            )
            VALUES (?, ?, ?,
                    ?, ?, COALESCE(?, 1),
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    null)
            SQL;
$sqlUpdateMedLabStudy = <<<SQL
            UPDATE MedLabStudy SET
                Name = ?,
                Description = ?,
                ProcedureRoom = COALESCE(?, 1),
                Active = ?,
                BarcodeType = ?,
                RequiredDiagnosis = ?,
                ShortName = ?,
                BarCodeWideBarRatio = ?,
                BarCodeZoom = ?,
                PregnancyObligatory = ?,
                AnalyserName = ?,
                AnalyserDescription = ?
            WHERE LisStudyId = ?  AND ELisType IS NULL
            SQL;
$sqlSelectMedLabStudyTest = <<<SQL
            SELECT COUNT(MedLabStudyTestCode)
            FROM MedLabStudyTest
            WHERE LisTestId = ?
            SQL;
$sqlInsertMedLabStudyTest = <<<SQL
            INSERT INTO MedLabStudyTest (
                LisTestId, MedLabStudyCode, InternalId,
                Name, IsInput, Description,
                Active, Type, MedLabStudyTestCodeParent,
                MedLabStudyTestCodeLink, DefaultFlag, LoincCode
            )
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?)
            SQL;
$sqlUpdateMedLabStudyTest = <<<SQL
            UPDATE MedLabStudyTest SET
                Name = ?,
                IsInput = ?,
                Description = ?,
                Active = ?,
                Type = ?,
                MedLabStudyTestCodeParent = ?,
                MedLabStudyTestCodeLink = ?,
                DefaultFlag = ?,
                LoincCode = ?
            WHERE LisTestId = ?
            SQL;
$total_count = 0; $cnt_update = 0; $cnt_insert = 0; $cnt_delete = 0; $db->beginTransaction(); try { $cnt_delete += $this->clearMedLabStudySection(createSqlInCondition($laboratories, 'Id')); foreach ($laboratories as $laboratory) { $total_count++; $medLabStudySectionId = selectSqlValue($db, $sqlSelectMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id')]); if ($medLabStudySectionId) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudySection, [ isUndefinedProperty($laboratory, 'Name'), $medLabStudySectionId ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id'), isUndefinedProperty($laboratory, 'Name'), isUndefinedProperty($laboratory, 'InternalId'), isUndefinedProperty($laboratory, 'LisId') ]); $medLabStudySectionId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudySection'); } $analyzes = isUndefinedProperty($laboratory, 'Analyses'); $cnt_delete += $this->clearMedLabStudy(createSqlInCondition($analyzes, 'Id'), $medLabStudySectionId); foreach ($analyzes as $analysis) { $total_count++; $lisStudyId = isUndefinedProperty($analysis, 'Id'); $studyIds = selectSql($db, $sqlSelectMedLabStudy, [ $lisStudyId ], PDO::FETCH_OBJ); if (count($studyIds) > 0) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudy, [ isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription'), $lisStudyId ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudy, [ $lisStudyId, $medLabStudySectionId, isUndefinedProperty($analysis, 'InternalId'), isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription') ]); $newStudy = new stdClass; $newStudy->medLabStudyId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudy'); array_push($studyIds, $newStudy); } $tests = isUndefinedProperty($analysis, 'Tests'); $cnt_delete += $this->clearMedLabStudyTest(createSqlInCondition($tests, 'Id'), $lisStudyId); foreach ($tests as $test) { $total_count++; $lisTestId = isUndefinedProperty($test, 'Id'); $testsCount = selectSqlValue($db, $sqlSelectMedLabStudyTest, [ $lisTestId ]); $medLabStudyTestCodeParent = isUndefinedProperty($test, 'TryTree'); if ($lisTestId === $medLabStudyTestCodeParent) { $medLabStudyTestCodeParent = null; } if ($testsCount > 0) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudyTest, [ isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), $medLabStudyTestCodeParent, isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode'), $lisTestId ]); } else { foreach ($studyIds as $row) { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudyTest, [ isUndefinedProperty($test, 'Id'), isUndefinedProperty($row, 'medLabStudyId'), isUndefinedProperty($test, 'InternalId'), isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), $medLabStudyTestCodeParent, isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode') ]); } } } } } $db->commit(); $message = "  1. Синхронизация справочника исследований завершена.<br>  Синхронизировано $total_count записей:<br>   - добавлено $cnt_insert;<br>   - обновлено $cnt_update;<br>   - удалено $cnt_delete."; $this->log($message); return $message; } catch (Throwable $e) { $db->rollback(); $message = $e->getMessage(); $this->log("  Ошибка синхронизации справочника исследований: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } public function sendDirections() { $this->log("  Отправка направлений на анализ..."); $this->log("  Выбор направлений для отправки..."); $settingsErrorCode = 9999; $settingsErrorText = 'Ошибка отправки направления. Возможные причины: неверные настройки обмена данными или ошибка сети.'; $directions = $this->readDirections(); $cnt = count($directions); if (!$cnt) { $message = "  Нет направлений для отправки."; $this->log($message, 1); return "2. Отправка направлений завершена." . $message; } $this->log("  Направлений для отправки: $cnt"); $cnt_send_ok = 0; $cnt_send_error = 0; foreach ($directions as $direction) { if (!$this->isSendOneDirection) { $this->studyId = $direction->MedLogStudyCode; } $response = false; $sended = false; try { if (!isUndefinedProperty($direction, 'ReferralId')) { $response = $this->postDir([$direction]); $sended = true; $result = null; if ($response === false) { $result = new stdClass(); $result->ErrorCode = $settingsErrorCode; $result->ErrorMessage = $settingsErrorText; $this->updateDirectionFailure($direction, $result); continue; } if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); if (is_array($result)) { $result = $result[0]; } $this->updateDirectionSuccessful($direction, $result); $cnt_send_ok++; break; case "409": $result = safe_json_decode($response['body']); if (is_array($result)) { $result = $result[0]; } if (isEmptyProperty($result, 'ErrorCode')) { $this->log("При отправке направления MedLogStudyCode = {$direction->MedLogStudyCode} произошла ошибка с кодом {$result->ErrorCode} - {$result->ErrorMessage}", 2); } $this->updateDirectionFailure($direction, $result); break; default: $cnt_send_error++; $result = $result ? : safe_json_decode($response['body']); $this->log("  При при отправке направления MedLogStudyCode = {$direction->MedLogStudyCode} произошла ошибка: {$result}", 2); $this->updateDirectionFailure(null, null, false); } } else { $response = $this->postDir([$direction]); $sended = true; $result = null; if ($response === false) { $result = new stdClass(); $result->ErrorCode = $settingsErrorCode; $result->ErrorMessage = $settingsErrorText; $this->updateDirectionFailure($direction, $result); continue; } if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $this->updateDirectionStatus($direction); $cnt_send_ok++; break; case "409": $result = safe_json_decode($response['body']); if (is_array($result)) { $result = $result[0]; } if (isEmptyProperty($result, 'ErrorCode')) { $this->log("При отправке направления MedLogStudyCode = {$direction->MedLogStudyCode} произошла ошибка с кодом {$result->ErrorCode} - {$result->ErrorMessage}", 2); } $this->updateDirectionFailure($direction, $result); break; default: $cnt_send_error++; $result = $result ? : $response['body']; $this->log("  При обновлении направления MedLogStudyCode = {$direction->MedLogStudyCode} произошла ошибка: {$result}", 2); $this->updateDirectionFailure(null, null, false); } } if (!$response && !$sended) { $this->updateDirectionStatus($direction); } if (!$this->isSendOneDirection) { $this->studyId = null; } } catch (Throwable $e) { $this->log("  Ошибка: {$e->getMessage()}", 2); $this->updateDirectionFailure(null, null, false); throw $e; } } $message = "2. Отправка направлений завершена ($cnt):<br>   - отправлено $cnt_send_ok;<br>   - ошибок $cnt_send_error."; $this->log("  $message"); return $message; } public function receiveResults() { $this->log("  Прием результатов..."); $db = $this->getDb(); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (receiveResults)"; $this->log($message, 2); return $message; } $sql = <<<"EOT"
SELECT
    LS.MedLabStudyCode  AS "AnalysisId",
    MLS.Barcode         AS "SequenceValue"
FROM
    MedLogStudy MLS
    JOIN MedLabStudy LS
        ON LS.MedStudyCode = MLS.MedStudyCode
WHERE
    MLS.SendTime IS NOT NULL
    AND MLS.Barcode IS NOT NULL
    AND MLS.BiopsySyncStatus = 2
    AND MLS.RawResult IS NULL
    AND MLS.BarcodeSource IS NULL
    AND MLS.SendTime >= LOCALTIMESTAMP - INTERVAL '6 months'
ORDER BY
    MLS.SendTime DESC
EOT;
$analyses = selectSql($db, $sql, null, PDO::FETCH_ASSOC); $params->request_method = 'result/getresultsbyanalysisidandsequencevalue'; $params->timeout = 30000; $params->debug = self::DEBUG_HTTP; $cnt_ok = 0; $cnt_error = 0; if (!$analyses) { return "3. Прием результатов завершен :<br>   - нечего получать."; } foreach (array_chunk($analyses, 100) as $chunk) { $payload = array_map(static function (array $row): array { return [ 'AnalysisId' => (int) $row['AnalysisId'], 'SequenceValue' => (string) $row['SequenceValue'], ]; }, $chunk); $params->body = json_encode($payload, JSON_UNESCAPED_UNICODE); $response = httpPOST($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $receivedId = []; $result = safe_json_decode($response['body']); if (!is_array($result)) { $this->log("  Некорректный JSON в ответе: " . substr((string)$response['body'], 0, 500), 2); break; } foreach ($result as $res) { if (!isset($res->ReferralId)) { continue; } $cnt_ok++; $this->updateLogStudy($res, $receivedId); } $cnt_ok += count($receivedId); $this->confirmResult($receivedId); break; default: $cnt_error++; $result = $result ?: $response['body']; $this->log("  При получении результатов исследований произошла ошибка: $result", 2); } } return "3. Прием результатов завершен :<br>   - получено $cnt_ok;<br>   - ошибок $cnt_error."; } private function receiveResultsByMisGuid (): string { $this->log("  Прием результатов по GUID..."); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (receiveResults)"; $this->log($message, 2); return $message; } $params->request_method = 'result/GetResultsByMisGuidAndPublicationDate'; $params->timeout = 30000; $params->debug = self::DEBUG_HTTP; $dateTo = date_add(safe_date_create(), date_interval_create_from_date_string('1 days')); $dateFrom = date_sub(safe_date_create(), date_interval_create_from_date_string('30 days')); $cnt_ok = 0; $cnt_error = 0; $param = (object) array( 'misGuid' => $settings->misId, 'publicationDateFrom' => date_format($dateFrom, 'Y-m-d'), 'publicationDateTo' => date_format($dateTo, 'Y-m-d'), 'onlyNotReceived' => 'true' ); $params->param = $param; $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $receivedId = []; $result = safe_json_decode($response['body']); foreach ($result as $res) { if (!isset($res->ReferralId)) { continue; } $cnt_ok = $this->updateLogStudy($res, $receivedId); } $cnt_ok = count($receivedId); $this->confirmResult($receivedId); break; default: $cnt_error++; $result = $result ? : $response['body']; $message = "  При получении результатов исследований произошла ошибка: $result"; $this->log($message, 2); } return "3. Прием результатов по GUID завершен :<br>   - получено $cnt_ok;<br>   - ошибок $cnt_error."; } private function confirmResult($records) { $this->log("  Отмечаем принятые исследования..."); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (confirmResult)"; $this->log($message, 2); return $message; } $params->request_method = 'result/ConfirmReceived'; $params->request_content = json_encode($records, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); break; default: $result = $result ? : $response['body']; $message = "  При отправке списка принятых исследований произошла ошибка: $result"; $this->log($message, 2); } return true; } private function updateLogStudy($study, &$receivedId) { $db = $this->getDb(); $cnt_ok = 0; $important = 0; $tests = $study->Tests; $rawData = new stdClass; foreach ($study as $key => $value) { if (strcasecmp($key, 'pdf') == 0 || strcasecmp($key, 'tests') == 0) { continue; } if (strcasecmp($key, 'publicationdate') == 0) { $rawData->PublicationDate = Date("Y-m-d H:i:s", safe_strtotime($study->PublicationDate)); } else { $rawData->{$key} = $value; } } if (count($tests) > 0) { foreach ($tests as $test) { $r = selectSql($db, "
select
 mlst.UnitName as u
from MedLabStudyTest as mlst
join MedLabStudy as s on s.MedLabStudyCode = mlst.MedLabStudyCode
join MedLogStudy as mls on mls.ReferralId = ?
 and mls.MedStudyCode = s.MedStudyCode
where coalesce(mlst.UnitName,'') <> ''
 and mlst.InternalId = ?
limit 1", [$study->ReferralId, $test->InternalId]); if (!$r || !is_array($r) || count($r) == 0 || !$r[0]['u']) { $this->log("  Тест InternalId = $test->InternalId не найден в БД и будет пропущен", 1); continue; } $u = $r[0]['u']; $pos = strripos($r[0]['u'], ','); if ($pos) { $arrU = safe_explode(',', $r[0]['u']); foreach ($arrU as $uEl) { $rawData->{$uEl} = new stdClass(); foreach ($test as $key => $value) { $rawData->{$uEl}->{$key} = $value; } $rawData->{$uEl}->Important = $this->toNumeric($rawData->{$uEl}->TestReferenceSign) == 2; if ($rawData->{$uEl}->Important && $important == 0) { $important = 1; } $rawData->{$uEl}->Value = glueStrings(isUndefinedProperty($rawData->{$uEl}, 'TestNotes'), isUndefinedProperty($rawData->{$uEl}, 'Value'), '. '); } continue; } $rawData->{$u} = new stdClass(); foreach ($test as $key => $value) { $rawData->{$u}->{$key} = $value; } $rawData->{$u}->Important = $this->toNumeric($rawData->{$u}->TestReferenceSign) == 2; if ($rawData->{$u}->Important && $important == 0) { $important = 1; } $rawData->{$u}->Value = glueStrings(isUndefinedProperty($rawData->{$u}, 'TestNotes'), isUndefinedProperty($rawData->{$u}, 'Value'), '. '); } $cnt_ok++; } else { $this->log("  По анализу с ReferralId " . $study->ReferralId . " отсутствуют результаты", 1); } $rawData = json_encode($rawData, JSON_UNESCAPED_UNICODE); $r = selectSqlValue($db, "select 1 from MedLogStudy where ReferralId = ? limit 1", [$study->ReferralId]); if ($r != 1) { $this->log("  Направление ReferralId = $study->ReferralId не найдено в БД", 1); $receivedId[] = $study->ResultId; return 0; } $sql = <<<"EOT"
UPDATE MedLogStudy set
    BiopsySyncStatus = 3,
    State = 2,
    ExecTime = ?,
    LogNumber = ?,
    PublicationDate = ?,
    RawResult = ?,
    Important = ?,

    PayAttention = ?,
    syncResult = 200,
    syncErrorMessage = null
WHERE ReferralId = ?
EOT;
$db->beginTransaction(); try { executeSql($db, $sql, [ Date("Ymd H:i:s", safe_strtotime($study->PublicationDate)), $study->ResultId, Date("Ymd H:i:s", safe_strtotime($study->PublicationDate)), $rawData, $important, $important == 1 ? 1 : null, $study->ReferralId ]); if ($db->inTransaction()) { $db->commit(); } $receivedId[] = $study->ResultId; } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollback(); } $message = $e->getMessage(); $this->log("  Ошибка обновления сатуса исследования ReferralId = {$study->ReferralId}: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } return $cnt_ok; } private function clearMedLabStudySection($ids) { $db = $this->getDb(); $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudySection mlss
WHERE mlss.LisSectionId NOT $ids
    AND (
        SELECT
            mls.ElisType
        FROM MedLabStudy mls
        WHERE mls.MedLabStudySectionCode = mlss.MedLabStudySectionCode
        ORDER BY mls.ElisType DESC LIMIT 1
    ) IS NULL
    AND mlss.LisSectionId IS NOT NULL
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function clearMedLabStudy($ids, $sectionId) { $db = $this->getDb(); $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudy
WHERE LisStudyId NOT $ids
  AND MedLabStudySectionCode = $sectionId
  AND ElisType IS NULL
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function clearMedLabStudyTest($ids, $lisStudyId) { $db = $this->getDb(); $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudyTest
WHERE LisTestId NOT $ids
    AND MedLabStudyCode IN (
        SELECT MedLabStudyCode
        FROM MedLabStudy
        WHERE LisStudyId = $lisStudyId
    )
    AND (
        SELECT
            COUNT(ElisType)
        FROM MedLabStudy
        WHERE MedLabStudyCode IN (
            SELECT MedLabStudyCode
            FROM MedLabStudy
            WHERE LisStudyId = $lisStudyId
        )
        AND ElisType IS NOT NULL
    ) = 0
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function readTests($studyId = 0) { if ($studyId == 0 && $this->tests) { return $this->tests; } $db = $this->getDb(); if ($studyId != 0) { $sqlTests = <<<"EOT"
select
 mlst.LisTestId "id",
 s.LisStudyId "labStudyId",
 mlst.InternalId "internalId",
 mlst.UnitName "unitName",
 mlst.LoincCode "loincCode",
 u.Value "value",
 u.LongValue "longValue"
from MedLabStudyTest as mlst
join MedLabStudy as s on s.MedLabStudyCode = mlst.MedLabStudyCode
join MedLogStudy as mls on mls.MedLogStudyCode = ?
 and mls.MedStudyCode = s.MedStudyCode
join htmUnit u on u.htmPageCode = mls.HtmPageCode
 and (CASE WHEN strpos(mlst.UnitName,',') <> 0 THEN u.Name = substring(mlst.UnitName for strpos(mlst.UnitName,',') - 1) ELSE u.Name = mlst.UnitName END)
where coalesce(mlst.UnitName,'') <> ''
EOT;
$tests = selectSql($db, $sqlTests, [$studyId]); } else { $sqlTests = <<<"EOT"
SELECT LisTestId "id",
       MedLabStudyCode     "labStudyId",
       InternalId          "internalId",
       UnitName            "unitName",
       LoincCode           "loincCode"
FROM MedLabStudyTest MLST
WHERE COALESCE(UnitName, '') <> ''
EOT;
$tests = selectSql($db, $sqlTests); } $ret = []; if ($tests) { foreach ($tests as $row) { $test = (object) $row; $test->value = trim($test->value ?? ''); if (!$test->value) { $test->value = trim($test->longValue ?? ''); } unset($test->longValue); $ret[] = $test; } } $this->tests = $ret; return $this->tests; } private function getTestsByStudyId($labStudyId, $studyId) { $tests = $this->readTests($studyId); $ret = []; if ($tests) { foreach ($tests as $test) { if ($test->labStudyId == $labStudyId) { $t = new stdClass(); $t->Id = $test->id; $t->Value = $test->value; $t->LoincCode = $test->loincCode; $ret[] = $t; } } } return $ret; } private function postDir($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $this->log("Ошибка инициализации подключения", 2); return false; } $dataSend = cloneFn($data); foreach ($dataSend as $value) { unset($value->enterpriseCipher); } $params->request_method = 'referral/addreferral'; $params->request_content = json_encode($dataSend, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG_HTTP; return httpPOST($params); return null; } private function postIntakeDate($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $this->log("Ошибка инициализация подключения (postIntakeDate)", 2); return false; } $d = []; foreach ($data as $value) { $o = new stdClass; $o->ReferralId = $value->ReferralId; $o->IntakeDate = $value->IntakeDate; $d[] = $o; } $params->request_method = 'referral/setIntakeDate'; $params->request_content = json_encode($d, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG_HTTP; return httpPOST($params); } private function readDirections() { $db = $this->getDb(); $ret = []; $settings = $this->getSettings(); if (!isUndefinedProperty($settings, 'active')) { return $ret; } $studyId = (int) $this->studyId; $sqlDirections = <<<"EOT"
select
  MLS.ReferralId                                    "ReferralId",             /*ID направления в базе СОЛИС*/
  MLS.BarCode                                       "SequenceValue",          /*Штрих-код направления*/
  MLST.LisStudyId                                   "AnalysisId",             /*ID анализа в справочнике анализов базы СОЛИС*/
  0                                                 "Priority",               /*Приоритет*/
  mls.DirTime                                       "CreationDate",           /*Дата время создания направления в МИС*/
  null                                              "ProcessingDate",         /*Дата время на которое назначен забор крови*/
  mls.ProcessingDate                                "IntakeDate",             /*Дата время забора крови*/
  MLS.PublicationDate                               "PublicationDate",        /*Дата время регистрации направления в СОЛИС*/
  CASE WHEN md.cipher1 IS NOT NULL or md.cipher1 <> ''
    THEN md.cipher1
    ELSE ''
  END ||
  CASE WHEN md.Name IS NOT NULL or md.Name <> ''
    THEN ' - ' || md.Name
    ELSE ''
  END /* ||
  CASE WHEN COALESCE(HUDsClinical_.Value, '') <> ''
    THEN '. ' || HUDsClinical_.Value
    ELSE ''
  END */                                            "Diagnosis",              /*Клинический диагноз*/
  NULL                                              "Notice",                 /*Примечания*/
  '{$settings->misId}'                              "MisGuid",                /*Guid МИС*/
  FN_barCode_MakeLogStudy(MLS.MedLogStudyCode)      "MisReferralId",          /*ID направления внутри базы МИС (теперь это штрихкод)*/
  FN_cmFIO(MDoc.ManCode)                            "Doctor",                 /*ФИО врача (для печати на бланке с результатом)*/
  COALESCE(MLS.Department, FN_cmDepartmentName(MLS.DepartmentCode))
                                                    "Department",             /*Отделение*/
  NULL                                              "District",               /*Участок*/
  MLS.MedPatientCode                                "MisPersonId",            /*ID пациента внутри базы МИС*/
  MPs.PersonalNumber                                "PassportPersonalNumber", /*Личный номер по паспорту*/
  (SELECT {$db->dialect("FIRST", "")} mbc.BarCode
   FROM MedBarCode mbc
   WHERE mbc.ManCode = MP.ManCode
    and mls.DirTime between coalesce(mbc.StartDate, '1900-01-01') and coalesce(mbc.StopDate, '3000-01-01')
   ORDER BY mbc.StartDate DESC, mbc.StopDate, mbc.BarCode DESC
   {$db->dialect("", "LIMIT 1")})                   "PersonalMedicalNumber",  /*№ электронной медицинской карты*/
  COALESCE(M.Surname,'')                            "SurName",                /*Фамилия*/
  COALESCE(M.Name, '')                              "FirstName",              /*Имя*/
  COALESCE(M.SecondName, '')                        "MiddleName",             /*Отчество*/
  COALESCE(MLS.PatientDateOfBirth, MPs.dateofbirth) "Birthday",               /*Дата рождения*/
  CASE WHEN COALESCE(MLS.PatientSex, MPs.Sex, 0) = 0
    THEN 1
    ELSE 0
  END                                               "Sex",                    /*1 – М; 0 – Ж*/
  COALESCE(MLS.PatientAddress, FN_cmCalcAddress(MP.ManCode, 5))
                                                    "Address",                /*Адрес*/
  NULL                                              "Tests",                  /*Список тестов*/
  coalesce(mlst.ProcedureRoom, 0) "procedureRoom",
  mls.PregnancyWeek "WeekOfPregnancy",                                          /* неделя беременности */
  mlsp.MedLogStudyPartnerCode "partnerCode",
  mlsp.PartnerFIO "partnerFIO",
  mlsp.PartnerSex "partnerSex",
  mlsp.PartnerDateOfBirth "partnerBirthDay",
  mlsp.PartnerAddress "partnerAddress",
   right(asa_trim((select Cipher from Enterprise where EnterpriseCode = mp.EnterpriseCode limit 1)), 3) as "enterpriseCipher", /* последние 3 цифры кода организации */
   mls.MedLogStudyCode                               "MedLogStudyCode" /* вместо MisReferralId для внутренних обработок */
FROM MedLabStudy MLST
JOIN MedLogStudy MLS ON MLS.MedStudyCode = MLST.MedStudyCode AND MLS.BiopsySyncStatus = 1
JOIN MedPatient MP on MP.MedPatientCode = MLS.MedPatientCode AND MP.EnterpriseCode = ?
JOIN Man M on M.ManCode = MP.ManCode
JOIN ManPassport MPs on MPs.ManCode = MP.ManCode
JOIN htmPageT HPT ON HPT.htmPageCode = MLS.htmPageCode
LEFT JOIN MedDoctor as MDoc ON MDoc.MedDoctorCode = MLS.MedDoctorCode
LEFT JOIN MedDiagnosis MD on MD.medDiagnosisCode = MLS.medDiagnosisCode
left join MedLogStudyPartner mlsp on mlsp.MedLogStudyCode = mls.MedLogStudyCode

/* JOIN Enterprise E on E.EnterpriseCode = MP.EnterpriseCode */
/* JOIN EnterpriseNumber EN on EN.EnterpriseCode = E.EnterpriseCode */
/* AND FN_htmPageGetUnits(MLS.htmPageCode) LIKE '%<Name>.UnitName</Name>%'  проверка на название TODO */
where (mls.MedLogStudyCode = ? or ? = 0)
AND NOT EXISTS (
      SELECT 1 FROM MedLogStudy 
      WHERE MedLogStudyCode = mls.MedLogStudyCode 
        AND SyncResult LIKE ?
        AND BiopsySyncStatus = ?
  )
EOT;
$directions = selectSql($db, $sqlDirections, [$settings->organizationId, $studyId, $studyId, self::REFERRAL_NOT_FOUND_CODE, 5]); foreach ($directions as $row) { $direction = (object) $row; if (!isUndefinedProperty($direction, 'ReferralId')) { if ((int) $direction->procedureRoom == 0 && isUndefinedProperty($direction, 'CreationDate')) { $direction->IntakeDate = (new DateTime($direction->CreationDate))->add(new DateInterval('P1D'))->format(DateTime::W3C); } else { $direction->IntakeDate = null; } } else { $direction->IntakeDate = $direction->IntakeDate ? (new DateTime($direction->IntakeDate))->format(DateTime::W3C) : null; } unset($direction->procedureRoom); $direction->CreationDate = $direction->CreationDate ? (new DateTime($direction->CreationDate))->format(DateTime::W3C) : null; $direction->ProcessingDate = $direction->ProcessingDate ? (new DateTime($direction->ProcessingDate))->format(DateTime::W3C) : null; $direction->PublicationDate = $direction->PublicationDate ? (new DateTime($direction->PublicationDate))->format(DateTime::W3C) : null; $direction->Birthday = $direction->Birthday ? (new DateTime($direction->Birthday))->format(DateTime::W3C) : null; $direction->MisGuid = $settings->misId; $direction->Tests = $this->getTestsByStudyId($direction->AnalysisId, $direction->MedLogStudyCode); if ($direction->Tests) { $ret[] = $direction; } else { $this->log("Направление MedLogStudyCode = {$direction->MedLogStudyCode} не будет отправлено. Причина: отсутствие тестов в настройке.", 1); } if ((int) $direction->partnerCode != 0) { $direction->SurName = null; $direction->FirstName = null; $direction->MiddleName = null; $direction->PassportPersonalNumber = null; $direction->PersonalMedicalNumber = null; $matches = null; if (preg_match_all('/(\\w+)/ui', (string) $direction->partnerFIO, $matches)) { $matches = $matches[0]; $direction->SurName = isUndefinedArrayValue($matches, 0); $direction->FirstName = isUndefinedArrayValue($matches, 1); for ($i = 2; $i < count($matches); $i++) { $direction->MiddleName .= isUndefinedArrayValue($matches, $i)." "; } if (!empty($direction->MiddleName)) { $direction->MiddleName = trim($direction->MiddleName); } } $direction->Sex = (int) $direction->partnerSex == 0 ? 1 : 0; $direction->Birthday = $direction->partnerBirthDay ? (new DateTime($direction->partnerBirthDay))->format(DateTime::W3C) : null; $direction->Address = $direction->partnerAddress; } unset($direction->partnerCode); unset($direction->partnerFIO); unset($direction->partnerSex); unset($direction->partnerBirthDay); unset($direction->partnerAddress); } return $ret; } private function updateDirectionSuccessful($data, $response) { $db = $this->getDb(); $inMedStudy = createSqlInCondition($data, 'MedLogStudyCode'); $sql = <<<"EOT"
UPDATE MedLogStudy set
    BiopsySyncStatus = 2,
    syncResult = 200,
    syncErrorMessage = null,
    PublicationDate = ?,
    ReferralId = ?,
    BarCode = ?
WHERE BiopsySyncStatus = 1
  AND MedLogStudyCode $inMedStudy
EOT;
$publicationDate = Date("Ymd H:i:s", safe_strtotime($response->PublicationDate)); $db->beginTransaction(); try { executeSql($db, $sql, [ $publicationDate, $response->ReferralId, $response->SequenceValue ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("Обновление данных и сатуса исследований в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } private function updateDirectionFailure($data, $response, $fromLis = true) { $db = $this->getDb(); if ($data) { $inMedStudy = createSqlInCondition($data, 'MedLogStudyCode'); } else { $id = (int) $this->studyId; $inMedStudy = "in({$id})"; } $isReferralNotFound = isset($this->syncErrorMessage) && stripos($this->syncErrorMessage, 'not found') !== false; if ($isReferralNotFound) { $sql = <<<SQL
            UPDATE MedLogStudy
            SET BiopsySyncStatus = 5,
                SyncResult = ?,
                SyncErrorMessage = ?
            WHERE MedLogStudyCode $inMedStudy
            SQL;
$errorCode = self::REFERRAL_NOT_FOUND_CODE; $message = "Направление не найдено в системе СОЛИС"; } else if ($fromLis) { $message = $response->ErrorCode . '-' . $response->ErrorMessage; if ($response->ErrorCode === 9999) { $sql = <<<SQL
                    UPDATE MedLogStudy
                    SET SyncResult = 9999,
                        SyncErrorMessage = ?
                    WHERE BiopsySyncStatus = 1
                        AND MedLogStudyCode $inMedStudy
                    SQL;
} else { $sql = <<<SQL
                    UPDATE MedLogStudy
                    SET BiopsySyncStatus = 5,
                        SyncResult = 409,
                        SyncErrorMessage = ?
                    WHERE BiopsySyncStatus = 1
                        AND MedLogStudyCode $inMedStudy
                    SQL;
} } else { $message = $this->syncErrorMessage; if (!$message) { return true; } $sql = <<<"EOT"
UPDATE MedLogStudy set
       syncResult = ?,
       syncErrorMessage = ?
WHERE MedLogStudyCode $inMedStudy
EOT;
} $db->beginTransaction(); try { if ($isReferralNotFound) { executeSql($db, $sql, [601, $message]); } else if ($fromLis) { executeSql($db, $sql, [$message]); } else { executeSql($db, $sql, [self::ONE_DIRECTION_ERROR_CODE, $message]); } $db->commit(); $this->syncErrorMessage = null; } catch (Throwable $e) { $db->rollback(); $this->log("Обновление данных и сатуса исследований в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } private function updateDirectionStatus($data) { $db = $this->getDb(); $inMedStudy = createSqlInCondition($data, 'MedLogStudyCode'); $sql = <<<"EOT"
UPDATE MedLogStudy set
    BiopsySyncStatus = 2,
    syncResult = 200,
    syncErrorMessage = null
WHERE BiopsySyncStatus = 1
  AND MedLogStudyCode $inMedStudy
EOT;
$db->beginTransaction(); try { executeSql($db, $sql, []); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("Обновление только сатуса исследований в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } public function getSettings() { if (!empty($this->extraSettings)) { return $this->extraSettings; } $cfg = $this->cfg ? : getSessionValue('config'); $result = null; $settingsValid = true; $this->log('Загрузка настроек...'); $settings = readExtraSettings($cfg, self::TASK_NAME); if (empty($settings)) { $this->log('Не произведена настройка обмена информацией', 2); } elseif ($settings->active) { if (empty($settings->url)) { $this->log('Не заполнена настройка Адрес сервера API', 2); $settingsValid = false; } if (empty($settings->misId)) { $this->log('Не заполнена настройка Код МИС', 2); $settingsValid = false; } if (empty($settings->organizationId)) { $this->log('Не заполнена настройка Организация', 2); $settingsValid = false; } if ($settingsValid) { $settings->task = self::TASK_NAME; $this->log('Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } } else { $this->log('В настройках запрещен обмен данными', 1); return $settings; } return $result; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); if ($type == 1 || $type == 2) { if (!$this->syncErrorMessage) { $this->syncErrorMessage = $message; } else { $this->syncErrorMessage .= "\n$message"; } } } private function getDb() { return $this->db ? : $this->db = connectDb($this->cfg); } private function toNumeric($val) { if (is_string($val)) { $locale_info = localeconv(); if ($locale_info['decimal_point'] == '.') { $val = str_replace(',', '.', $val); } if ($locale_info['decimal_point'] == ',') { $val = str_replace('.', ',', $val); } } if (is_numeric($val)) { return $val; } } public function cloneStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isEmptyProperty($params, 'studyId'); $sqlInsertStudy = <<<EOT
            INSERT INTO MedLabStudy (LisStudyId, MedLabStudySectionCode, InternalId,
                    Name, Description, ProcedureRoom,
                    Active, BarcodeType, RequiredDiagnosis,
                    ShortName, BarCodeWideBarRatio, BarCodeZoom,
                    PregnancyObligatory, AnalyserName, AnalyserDescription)
            SELECT
                LisStudyId, MedLabStudySectionCode, InternalId,
                Name, Description, ProcedureRoom,
                Active, BarcodeType, RequiredDiagnosis,
                ShortName, BarCodeWideBarRatio, BarCodeZoom,
                PregnancyObligatory, AnalyserName, AnalyserDescription
            FROM MedLabStudy
            WHERE MedLabStudyCode = ?
EOT;
$sqlInsertStudyTest = <<<EOT
            INSERT INTO MedLabStudyTest (LisTestId, MedLabStudyCode, InternalId,
                    Name, IsInput, Description,
                    Active, Type, MedLabStudyTestCodeParent,
                    MedLabStudyTestCodeLink, DefaultFlag, LoincCode)
            SELECT
                LisTestId, ?, InternalId,
                Name, IsInput, Description,
                Active, Type, MedLabStudyTestCodeParent,
                MedLabStudyTestCodeLink, DefaultFlag, LoincCode
            FROM MedLabStudyTest
            WHERE MedLabStudyCode = ?
EOT;
$db->beginTransaction(); try { executeSql($db, $sqlInsertStudy, [ $studyId ]); $medLabStudyNewId = $db->lastInsertId('seq_MedLabStudy'); executeSql($db, $sqlInsertStudyTest, [ $medLabStudyNewId, $studyId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } if ($_POST && isset($_POST['download']) && isset($_POST['resultPdf']) && isset($_POST['args'])) { $service = new med_lab_linkLis(); $args = safe_json_decode($_POST['args']); $service->getResultPdfOne(getSessionValue('config'), isUndefinedProperty($args, 'studyId')); } else { handleJsonRpc('med_lab_linkLis'); }