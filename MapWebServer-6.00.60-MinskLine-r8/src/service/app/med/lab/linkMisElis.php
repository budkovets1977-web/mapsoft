<?php
 require_once(__DIR__ . '/../../init.php'); class med_lab_linkMisElis { const TASK_NAME = 'medELIS'; const DEBUG_HTTP = false; const DIRECTION_STATUS_READY = 2; const DIRECTION_BARCODE_SOURCE = 3; private $db; private $extraSettings; private $cfg = null; private $syncErrorMessage = null; private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); if ($type == 1 || $type == 2) { if (!$this->syncErrorMessage) { $this->syncErrorMessage = $message; } else { $this->syncErrorMessage .= "\n$message"; } } } public function startExchange ($data) { $this->log('Запуск функции полного обмена данными startExchange()...'); try { $this->getSettings(); $message2 = $this->sendDirections(); $message3 = $this->receiveResults(); $this->log($message3); $this->log('Работа функции полного обмена данными startExchange() завершена'); return "$message2<br>$message3"; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате выполнения функции полного обмена данными произошла ошибка: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } public function sendDirections() { $this->log("  Отправка направлений на анализ..."); $data = $this->getDataToRequest(); $result = null; if (!$data) { $message = "  Нет направлений для отправки."; $this->log($message, 1); return "2. Отправка направлений завершена." . $message; } $cnt = count($data->medLogStudies); $this->log("  Направлений для отправки: $cnt"); try { $response = $this->postDir($data); if (!is_array($response)) { $message = "Получен недействительный ответ от сервера при отправке направлений"; $this->log($message); throw new Error($message); } if (!isNullArrayValue($response['status'], 1)) { $message = "Получен недействительный ответ от сервера при отправке направлений"; $this->log($message); throw new Error($message); } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); if (is_object($result) && $error = isUndefinedProperty($result, 'error')) { $message = "Направление отправлено не было, ошибка из ЦОД: $error"; $this->log($message); throw new Error($message); } if (!is_object($result)) { $message = "Получен недействительный ответ от сервера при отправке направлений"; $this->log($message); throw new Error($message); } $this->updateDirectionsSuccessful($data, $result); break; default: $result = $result ? : safe_json_decode($response['body']); $this->log("  При отправке направлений произошла ошибка: {$result}", 2); } } catch (Throwable $e) { $this->log("  Ошибка: {$e->getMessage()}", 2); throw $e; } $message = "2. Отправка направлений завершена ($cnt)."; $this->log("  $message"); return $message; } public function receiveResults() { $this->log("  Прием результатов..."); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (receiveResults)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/result/getresultsbymisguid'; $params->debug = self::DEBUG_HTTP; $cnt_ok = 0; $cnt_error = 0; $cnt_send_ok = 0; $param = (object) array( 'guid' => $settings->misId ); $params->param = $param; $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $receivedId = []; $ids = []; $result = safe_json_decode($response['body']); if (is_array($result)) { foreach ($result as $res) { $id = isEmptyProperty($res, 'id'); $this->updateLogStudy($res, $receivedId); $ids[] = $id; } $this->setOkReceptions($ids); } $cnt_ok = count($receivedId); $cnt_send_ok = count($ids); break; default: $cnt_error++; $result = $result ? : $response['body']; $message = "  При получении результатов исследований произошла ошибка: $result"; $this->log($message, 2); } return "3. Прием результатов завершен :<br>   - получено $cnt_ok;<br>   - ошибок $cnt_error ;<br>   - подтверждений $cnt_send_ok."; } private function setOkReceptions($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (setOkReception)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/result/setOkReception'; $params->param = array( 'guid' => $settings->misId, 'receivedId' => json_encode($data) ); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); return $response; } private function checkAndRemoveOldPdf(string $barcode) { $db = $this->db; $selectSql = <<<"EOT"
SELECT
    MEFT.MedExternalFileCode AS "id"
FROM MedExternalFileT MEFT
WHERE MEFT.source = 20
  AND MEFT.sourceCode = (
      SELECT MedLogStudyCode
      FROM MedLogStudy
      WHERE Barcode = '$barcode'
      LIMIT 1
  )
ORDER BY MEFT.createTime DESC;
EOT;
$oldPdf = selectSql($db, $selectSql); if (count($oldPdf)) { $this->deleteExternalPdf($oldPdf); } } private function savePdf($pdf, string $fileName, string $barcode) { $db = $this->db; $sql = <<<"EOT"
INSERT INTO MedExternalFileT
    (Source, FileName, SourceCode, Data)
VALUES (20, '$fileName', (SELECT MedLogStudyCode FROM MedLogStudy WHERE Barcode = '$barcode'  limit 1), :PDF)
EOT;
$stmt = $db->prepare($sql); $stmt->bindParam(':PDF', $pdf, PDO::PARAM_LOB); try { $stmt->execute(); $db->commit(); return true; } catch (Throwable $e) { $this->log("Во время сохранения PDF-файла произошла ошибка:" . $e->getMessage(), 2); return false; } } private function updateLogStudy($study, &$receivedId) { $db = $this->getDb(); $data = safe_json_decode($study->data); $files = isUndefinedProperty($data,'study_files'); $rawResult = isUndefinedProperty($data,'RawResult'); $barcode = isUndefinedProperty($data,'BarCode'); $logNumber = isUndefinedProperty($data,'LogNumber'); $publicationDate = isUndefinedProperty($data,'PublicationDate'); $execTime = dbDateTime($data->ExecTime); $sql = <<<"EOT"
UPDATE MedLogStudy set
    BiopsySyncStatus = 3,
    ExecTime = ?,
    state = 2,
    RawResult = ?,
    LogNumber = ?,
    PublicationDate = ?,
    Json = ?
WHERE barCode = ? and barCodeSource = 3
EOT;
$db->beginTransaction(); try { executeSql($db, $sql, [ $execTime, $rawResult, $logNumber, $publicationDate, json_encode($data), $barcode ]); $receivedId[] = $barcode; if (!empty($files)) { $files = json_decode($files); $this->checkAndRemoveOldPdf($barcode); foreach ($files as $dataFile) { $this->savePdf(base64_decode($dataFile->file), $dataFile->filename, $barcode); } } if ($db->inTransaction()) { $db->commit(); } } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollback(); } $message = $e->getMessage(); $this->log("  Ошибка обновления статуса исследования barcode = {$barcode}: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } public function deleteExternalPdf( $records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medExternalFileT', 'medExternalFileCode', $records); } public function synchronizeStudies() { $db = $this->getDb(); $settings = $this->getSettings(); $active = isUndefinedProperty($settings, 'active'); if (!$active) { throw new Error('Неоходимо установить настройки перед синхронизацией!'); } $this->log("  Синхронизация справочника исследований..."); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (synchronizeStudies)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/getlaboratoriesbymisguid'; $params->param = array( 'guid' => $settings->misId ); $params->debug = self::DEBUG_HTTP; $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $laboratories = safe_json_decode($response['body']); break; default: $result = $result ? : $response['body']; $message = "  При получении справочника исследований произошла ошибка: $result"; $this->log($message, 2); $this->log("Выполнение прервано", 2); return $message; } $sqlSelectMedLabStudySection = <<<EOT
            SELECT MedLabStudySectionCode
            FROM MedLabStudySection
            WHERE LisSectionId = ?
             AND ELisType = 1
EOT;
$sqlInsertMedLabStudySection = <<<EOT
            INSERT INTO MedLabStudySection (LisSectionId, Name, InternalId, ELisType)
            VALUES (?, ?, ?, ?)
EOT;
$sqlUpdateMedLabStudySection = <<<EOT
            UPDATE MedLabStudySection SET
                Name = ?
            WHERE MedLabStudySectionCode = ?
                AND ELisType = ?
EOT;
$sqlSelectMedLabStudy = <<<EOT
            SELECT MedLabStudyCode "medLabStudyId"
            FROM MedLabStudy
            WHERE LisStudyId = ? AND ELisType = ?
            ORDER BY MedLabStudyCode
EOT;
$sqlInsertMedLabStudy = <<<EOT
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
                ?)
EOT;
$sqlUpdateMedLabStudy = <<<EOT
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
            WHERE LisStudyId = ? AND ELisType = ?
EOT;
$sqlSelectMedLabStudyTest = <<<EOT
            SELECT COUNT(MedLabStudyTestCode)
            FROM MedLabStudyTest
            WHERE LisTestId = ?
EOT;
$sqlInsertMedLabStudyTest = <<<EOT
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
EOT;
$sqlUpdateMedLabStudyTest = <<<EOT
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
EOT;
$total_count = 0; $cnt_update = 0; $cnt_insert = 0; $cnt_delete = 0; $db->beginTransaction(); try { $cnt_delete += $this->clearMedLabStudySection(createSqlInCondition($laboratories, 'Id')); foreach ($laboratories as $laboratory) { $total_count++; $medLabStudySectionId = selectSqlValue($db, $sqlSelectMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id') ]); if ($medLabStudySectionId) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudySection, [ isUndefinedProperty($laboratory, 'Name'), $medLabStudySectionId, 1 ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id'), isUndefinedProperty($laboratory, 'Name'), isUndefinedProperty($laboratory, 'InternalId'), 1 ]); $medLabStudySectionId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudySection'); } $analyzes = isUndefinedProperty($laboratory, 'Analyses'); $cnt_delete += $this->clearMedLabStudy(createSqlInCondition($analyzes, 'Id'), $medLabStudySectionId); foreach ($analyzes as $analysis) { $total_count++; $lisStudyId = isUndefinedProperty($analysis, 'Id'); $studyIds = selectSql($db, $sqlSelectMedLabStudy, [ $lisStudyId, 1 ], PDO::FETCH_OBJ); $medLabStudyIdTarget = null; if (count($studyIds) > 0) { $cnt_update++; $medLabStudyIdTarget = isUndefinedProperty($studyIds[0], 'medLabStudyId'); executeSql($db, $sqlUpdateMedLabStudy, [ isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription'), $lisStudyId, 1 ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudy, [ $lisStudyId, $medLabStudySectionId, isUndefinedProperty($analysis, 'InternalId'), isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription'), 1 ]); $medLabStudyIdTarget = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudy'); } $tests = isUndefinedProperty($analysis, 'Tests'); $cnt_delete += $this->clearMedLabStudyTest(createSqlInCondition($tests, 'Id'), $lisStudyId); foreach ($tests as $test) { $total_count++; $LisTestId = isUndefinedProperty($test, 'Id'); $testsCount = selectSqlValue($db, $sqlSelectMedLabStudyTest, [ $LisTestId ]); if ($testsCount > 0) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudyTest, [ isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), isUndefinedProperty($test, 'TryTree'), isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode'), $LisTestId ]); } else { foreach ($studyIds as $row) { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudyTest, [ $LisTestId, $medLabStudyIdTarget, isUndefinedProperty($test, 'InternalId'), isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), isUndefinedProperty($test, 'TryTree'), isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode') ]); } } } } } $db->commit(); $message = "  1. Синхронизация справочника исследований завершена.<br>  Синхронизировано $total_count записей:<br>   - добавлено $cnt_insert;<br>   - обновлено $cnt_update;<br>   - удалено $cnt_delete."; $this->log($message); return $message; } catch (Throwable $e) { $db->rollback(); $message = $e->getMessage(); $this->log("  Ошибка синхронизации справочника исследований: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } private function clearMedLabStudySection($ids) { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = $settings->organizationType; $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudySection mlss
WHERE mlss.LisSectionId NOT $ids AND Exists(
    SELECT
        mls.ElisType
    FROM MedLabStudy mls
    WHERE mls.MedLabStudySectionCode = mlss.MedLabStudySectionCode
        AND mls.elisType = $organizationType
)
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function clearMedLabStudy($ids, $sectionId) { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = $settings->organizationType; $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudy
WHERE LisStudyId NOT $ids
  AND MedLabStudySectionCode = $sectionId
  AND elisType = $organizationType
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function clearMedLabStudyTest($ids, $lisStudyId) { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = $settings->organizationType; $sql = <<<EOT
            {$db->postgre("WITH upd AS (")}
            DELETE FROM MedLabStudyTest
            WHERE LisTestId NOT $ids
                AND MedLabStudyCode = $lisStudyId
                AND (
                    SELECT ElisType
                    FROM MedLabStudy
                    WHERE LisStudyId = $lisStudyId
                    LIMIT 1
                ) = $organizationType
            {$db->postgre("RETURNING *)")}
            {$db->postgre("SELECT COUNT(*) \"cnt\" FROM upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function updateDirectionsSuccessful($data, $response) { $db = $this->getDb(); $directions = $data->medLogStudies; $sql = <<<"EOT"
UPDATE MedLogStudy set
    BarCode = :barCode,
    BarCodeSource = :barCodeSource,
    BiopsySyncStatus = :status
WHERE MedLogStudyCode = :misId
EOT;
$db->beginTransaction(); try { foreach($directions as $direction) { $misId = $direction->misId; executeSql($db, $sql, [ ':misId' => $misId, ':barCode' => $response->{$misId}, ':barCodeSource' => self::DIRECTION_BARCODE_SOURCE, ':status' => self::DIRECTION_STATUS_READY ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("Обновление данных и сатуса исследований в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } private function postDir($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (postDirections)"; $this->log($message, 2); return $message; } $params->request_method = 'api/referral/addreferral'; $params->param = array('guid' => $settings->misId); $params->request_content = json_encode($data); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); return $response; } private function getDataToRequest () { $db = $this->getDb(); $sql = <<<"EOT"
SELECT
   map.JSON_Object( '', map.JSON_Array( 'medLogStudies', -- все исследования = элементы массива
(SELECT
  STRING_AGG(Q1.string, ',') FROM
(SELECT
map.JSON_Object( '',
map.JSON_Numeric( 'Attributes', COALESCE(LS.Attributes,0) ) || -- признаки платности и гражданства
map.JSON_String( 'BarCode', LS.BarCode ) || -- печатается на пробирке, id-ик направления в ЕЛИС
map.JSON_Numeric( 'BarCodeSource', LS.BarCodeSource ) || -- источник штрих-кода
map.JSON_String( 'BarCodeMAP', FN_barCode_MakeLogStudy(LS.medLogStudyCode) ) || -- штрих-код, кот. печатается на направлении в поликлинике
map.JSON_String( 'Conclusion', LS.Conclusion ) || -- заключение (мало ли, будут что-то передавать в лабораторию)
map.JSON_String( 'Department', LS.Department ) || -- отделение
map.JSON_String( 'DirNumber', LS.DirNumber ) || -- номер направления
map.JSON_String( 'DirTime', DATEFORMAT( LS.DirTime, 'yyyy-mm-dd hh:nn:ss' )) ||
map.JSON_String( 'DoctorFIO', FN_cmFIO( DrSend.ManCode )) || -- ФИО направившего врача
map.JSON_String( 'Email', LS.Email ) ||
map.JSON_Numeric( 'EnterpriseCodePoliclinic', Efrom.EnterpriseCode ) || -- id организации направившего врача
map.JSON_String( 'EnterpriseNamePoliclinic', COALESCE( NULLIF( ASA_TRIM( Efrom.FullName ), '' ), Efrom.Name ) ) || -- название организации направившего врача
map.JSON_String( 'medDiagnosisCipher1', ASA_TRIM(COALESCE(Diag.Cipher1,'')) ) || -- шифр диагноза по МКБ-10
map.JSON_Numeric( 'medDiagnosisCode', LS.medDiagnosisCode ) || -- id диагноза в базе поликлиники
map.JSON_Numeric( 'medDoctorCode', LS.medDoctorCode ) || -- id врача в базе поликлиники
map.JSON_Numeric( 'medLogStudyCode', LS.medLogStudyCode ) || -- id направления в базе поликлиники
map.JSON_Numeric( 'medPatientCode', LS.medPatientCode ) || -- id пациента в базе поликлиники
map.JSON_Numeric( 'medStudyCode', LS.medStudyCode ) || -- id вида исследования в базе поликлиники
map.JSON_Numeric( 'medStudyInternalId', labS.InternalId ) || -- id исследования в ЕЛИС
map.JSON_String( 'medStudyName', S.Name ) || -- название исследования в пол-ке
map.JSON_String( 'NumberHistorySickness', LS.NumberHistorySickness ) || -- № амб. карты пациента
map.JSON_String( 'PatientAddress', LS.PatientAddress ) || -- адрес пациента
map.JSON_String( 'PatientDateOfBirth', DATEFORMAT( LS.PatientDateOfBirth, 'yyyy-mm-dd hh:nn:ss' )) || -- дата рождения пациента
map.JSON_String( 'PatientFIO', LS.PatientFIO ) || -- ФИО пациента
map.JSON_Numeric( 'PatientSex', LS.PatientSex ) || -- пол пациента
map.JSON_String( 'PIN', LS.PIN ) || -- короткий номер для поиска направления в базе поликлиники (может пригодится)
map.JSON_Numeric( 'PregnancyWeek', LS.PregnancyWeek ) || -- неделя беременности пациента
map.JSON_String( 'ProcessingDate', DATEFORMAT( LS.ProcessingDate, 'yyyy-mm-dd hh:nn:ss' )) || -- дата-время забора крови
map.JSON_String( 'RawResult', coalesce(
      NULLIF((SELECT map.JSON_Object('',STRING_AGG(map.JSON_String(
          hp.destname,
          hu.value, 0),','), 0)
          FROM htmpageunitlist hp
            JOIN medstudy ms ON ms.htmpagecode = hp.htmpagecode
            JOIN medlogstudy ml ON ms.medstudycode = ml.medstudycode
            LEFT JOIN htmunit hu ON hu.htmpagecode = ml.htmpagecode AND hp.name = hu.name
          WHERE ml.medlogstudycode =  LS.medlogstudycode
            AND hp.listtype = 1),'{}'),
      (SELECT map.JSON_Object( '', STRING_AGG( map.JSON_String( (SELECT regexp_matches(u[1], '<name>(.*?)</name>', 'i'))[1]::varchar(250),
                                                               COALESCE( NULLIF( TRIM((SELECT regexp_matches(u[1], '<lval>(.*?)</lval>', 'i'))[1]::text), '' ), (SELECT regexp_matches(u[1], '<val>(.*?)</val>', 'i'))[1]::varchar(254), '' ), 0 ), ','
                                                               ORDER BY (SELECT regexp_matches(u[1], '<name>(.*?)</name>', 'i'))[1]::varchar(250) ), 0 )
                           FROM regexp_matches( FN_htmPageGetUnits( LS.htmpagecode, 'DNK_VPCH_VKR_KOL_hpv_copy_value,Address,Number_Study,DNK_VPCH_VKR_KOL,Диагноз,IntakeDate,DNK_VPCH_VKR_KOL_hpv_value,paid,' ||
                                                                                    'DNK_N_GONORRHOEAE_select,DNK_TRICH_VAGINALIS_select,diagnosis_list,elistype,Note,DNK_N_GONORRHOEAE,DoctorExecSign,FEMOFLOR_select,' ||
                                                                                    'RNK_SARS_COV_2_select,PublicationDate,Pregnancy,ЛПУ_название,Sex,DNK_CHL_TRACHOMATIS_select,BuhExecName,DoctorExecSignDate,' ||
                                                                                    'Штрихкод,DNK_VPCH_VKR_KACH,Направление,patientfio,Dir_Number,Гриф,DNK_VPCH_VKR_KOL_select,departmentName,DNK_UREPLASMA_SPP_select,' ||
                                                                                    'DoctorExecName,KVANT_21,CreationDate,EnterpriseName,DNK_HSV_1_2_select,Birthday,DNK_HSV_1_2,RNK_SARS_COV_2,KVANT_21_select,' ||
                                                                                    'Notice,Result,Организация,DNK_M_GENITALIUM,DNK_TRICH_VAGINALIS,DNK_CHL_TRACHOMATIS,citizenship,DateExec,DNK_VPCH_VKR_KACH_select,' ||
                                                                                    'IntakePlace,DNK_UREPLASMA_SPP,DNK_M_GENITALIUM_select,diagnosis,FIO,departmentMain,Отделение,Name,Dir_number,Штрих-код ЕЛИС,' ||
                                                                                    'Department,diagnosis_name,PersonalMedicalNumber,PersonalMedicalNumberSt,doctor_post_in,Doctor,citizenship_BLR,citizenship_other,' ||
                                                                                    'RPR_select,RPGA_select,RIF_ABS_select,RIF_200_select,IFA_select,DNK_VPCH_VKR,DateMenstrualCycle,PatientFIO_conclusion,' ||
                                                                                    'MedDoctorSignDate,Надпись_заключение,ЛПУ (название),PatientOperations,HormonalDrugs,PersonalNumber,тест,Пациент_заключение,' ||
                                                                                    'HormonalDrugsDuration,PatientPhone,LastDatePregnancy,PersonalMedicalNumber,PersonalMedicalNumberSt'), '<unit>(.*?)</unit>', 'gi') u))) ||
map.JSON_String( 'SendTime', DATEFORMAT( LS.SendTime, 'yyyy-mm-dd hh:nn:ss' )) ||
map.JSON_Numeric( 'State', LS.State ) ||
map.JSON_String( 'StatusText', LS.StatusText ) ||
map.JSON_Numeric( 'Type', LS.Type, 0 )
, 0) as "string"
FROM medLogStudy LS
 JOIN medLabStudy labS ON labS.medLabStudyCode = (SELECT MIN(labS_.medLabStudyCode) FROM medLabStudy labS_ WHERE labS_.medStudyCode = LS.medStudyCode AND labS_.Active = 1 AND labS_.ELIStype = 1)
 LEFT OUTER JOIN medDiagnosis Diag ON Diag.medDiagnosisCode = LS.medDiagnosisCode
 LEFT OUTER JOIN medStudy S ON S.medStudyCode = LS.medStudyCode
 LEFT OUTER JOIN medDoctor DrSend ON DrSend.medDoctorCode = LS.medDoctorCode
 LEFT OUTER JOIN Enterprise Efrom ON Efrom.EnterpriseCode = (SELECT Br.EnterpriseCode FROM Branch Br, Department D WHERE D.DepartmentCode = DrSend.DepartmentCode AND Br.BranchCode = D.BranchCode LIMIT 1)
 WHERE LS.BiopsySyncStatus = 1
  AND LS.Type = 1
   -- пока не будем ориентироваться на забор крови  AND LS.ProcessingDate IS NOT NULL -- пока образец биологического материала (крови) не взяли, в ЕЛИС направление не заберём; ProcessingDate заполняется при печати наклейки на пробирку (функцией, вызываемой при генерации отчета для печати этой наклейки)
  AND COALESCE( ASA_TRIM(LS.PatientFIO), '' ) != '' -- будем просто ждать сохранения направления с внесённым пациентом
  ORDER BY LS.ProcessingDate, LS.DirTime, LS.medLogStudyCode
  LIMIT 100) AS Q1), 0 ), 0 )
EOT;
$sqlValue = selectSqlValue($db, $sql); $data = safe_json_decode($sqlValue); if (is_null($data)) { for ($i = 0; $i <= 31; ++$i) { $sqlValue = str_replace(chr($i), '', $sqlValue); } $sqlValue = str_replace(chr(127), '', $sqlValue); if (0 === safe_strpos(bin2hex($sqlValue), 'efbbbf')) { $sqlValue = substr($sqlValue, 3); } $data = safe_json_decode($sqlValue); } $studies = $data->medLogStudies; if (count($studies)) { $result = new stdClass(); $result->medLogStudies = array(); foreach ($studies as $study) { $el = new stdClass(); $el->data = $study; $el->misId = $study->medLogStudyCode; $el->studyId = $study->medStudyInternalId; $el->barCode = $study->BarCode; array_push($result->medLogStudies, $el); } return $result; } return false; } public function getSettings() { if (!empty($this->extraSettings)) { return $this->extraSettings; } $cfg = $this->cfg ? : getSessionValue('config'); $result = null; $settingsValid = true; $this->log('Загрузка настроек...'); $settings = readExtraSettings($cfg, self::TASK_NAME); if (empty($settings)) { $this->log('Не произведена настройка обмена информацией', 2); } elseif (is_object($settings) && isUndefinedProperty($settings, 'active')) { if (empty($settings->url)) { $this->log('Не заполнена настройка Адрес сервера API', 2); $settingsValid = false; } if (empty($settings->misId)) { $this->log('Не заполнена настройка Код МИС', 2); $settingsValid = false; } if (empty($settings->organizationId)) { $this->log('Не заполнена настройка Организация', 2); $settingsValid = false; } if ($settingsValid) { $settings->task = self::TASK_NAME; $this->log('Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } } else { $this->log('В настройках запрещен обмен данными', 1); return $settings; } return $result; } private function getDb() { return $this->db ? : $this->db = connectDb($this->cfg); } public function updateLabStudy($records) { $db = $this->getDb(); $sql = <<<"EOT"
UPDATE medLabStudy SET
  MedStudyCode = case when ? = 1 then ? else MedStudyCode end
WHERE medLabStudyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'studyId'), isEmptyProperty($row, 'studyId'), $row->id ]); $param = new stdClass; $param->id = $row->id; $ret = $this->readLabStudy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readLabStudy($params = null) { $db = $this->getDb(); $id = isEmptyProperty($params, 'id') ? $params->id : 0; $cfg = $this->cfg ? : getSessionValue('config'); $settings = readExtraSettings($cfg, self::TASK_NAME); if (is_object($settings)) { $organizationType = isUndefinedProperty($settings, 'organizationType', false); if (!$organizationType) { return array(); } } $sql = <<<EOT
            SELECT
                Sec.MedLabStudySectionCode * -1 "id",
                NULL                            "parentId",
                Sec.Name                        "name",
--                 'Код ЛИС : ' || Sec.LisId       "description",
                NULL                            "studyName",
                NULL                            "studyId",
                1                               "active",
                0                               "leaf"
            FROM MedLabStudySection Sec
            WHERE $id = 0
            AND EXISTS(
                SELECT 1
                FROM MedLabStudy MLS
                WHERE MLS.MedLabStudySectionCode = Sec.MedLabStudySectionCode
                    AND MLS.ElisType = $organizationType
            )
            UNION ALL
            SELECT
                St.MedLabStudyCode              "id",
                St.MedLabStudySectionCode * -1  "parentId",
                St.Name                         "name",
--                 St.Description                  "description",
                CASE P.IsTemplate
                    WHEN 1 THEN '(МАП) '
                    ELSE ''
                END || MS.Name                  "studyName",
                St.MedStudyCode                 "studyId",
                St.Active                       "active",
                1                               "leaf"
            FROM MedLabStudy St
            LEFT JOIN MedStudy MS ON MS.MedStudyCode = St.MedStudyCode
            LEFT JOIN HtmPageT P ON P.HtmPageCode = MS.HtmPageCode
            WHERE ($id = 0 OR St.MedLabStudyCode = $id)
                AND St.ElisType = $organizationType
            ORDER BY Name
EOT;
return selectSql($db, $sql); } private function getConfig() { header('Access-Control-Allow-Origin: *'); $settings = readJsonSettings('settings_med'); if ($settings && isset($settings['defaultConfig'])) { $config = $settings['defaultConfig']; } else { $msg = 'Отсутствует настройка конфигурации по умолчанию "defaultConfig" в файле "cfg\settings_med.json"'; http_response_code(500); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); die; } return $config; } public function getBarcode() { $config = $this->getConfig(); $errorMsg = "При получении нового штрихкода ЕЛИС произошла ошибка:"; $extraSettings = readExtraSettings($config, self::TASK_NAME); if (!$params = httpCreateParam($extraSettings)) { $message = "Ошибка инициализация подключения (getBarcode)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/getNextBarcode'; $params->param = array( 'guid' => $extraSettings->misId ); $params->debug = self::DEBUG_HTTP; $response = httpGET($params); $result = null; $msg = ''; if ($response) { if (!isNullArrayValue($response['status'], 1)) { $msg = "$errorMsg Сервер вернул пустой ответ"; $result = new stdClass(); $result->err = 1; $result->msg = $msg; $this->log("  $msg", 2); } $status = isEmptyArrayValue($response['status'], 1); switch ($status) { case "200": if ($response['body']) { $responseData = json_clean_decode($response['body']); $result = $responseData->nextbarcode; } $this->log("  Новый штрихкод получен"); break; default: $msg = $response['body']; $result = new stdClass(); $result->err = $status; $result->msg = $msg; $this->log("  $errorMsg $msg", 2); break; } } else { $msg = "$errorMsg Сервер недоступен"; $result = new stdClass(); $result->err = 1; $result->msg = $msg; $this->log("  $msg", 2); } return $result; } public function cloneStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isEmptyProperty($params, 'studyId'); $sqlInsertStudy = <<<EOT
            INSERT INTO MedLabStudy (
                LisStudyId, MedLabStudySectionCode, InternalId,
                Name, Description, ProcedureRoom,
                Active, BarcodeType, RequiredDiagnosis,
                ShortName, BarCodeWideBarRatio, BarCodeZoom,
                PregnancyObligatory, AnalyserName, AnalyserDescription,
                ElisType
            )
            SELECT
                LisStudyId, MedLabStudySectionCode, InternalId,
                Name, Description, ProcedureRoom,
                Active, BarcodeType, RequiredDiagnosis,
                ShortName, BarCodeWideBarRatio, BarCodeZoom,
                PregnancyObligatory, AnalyserName, AnalyserDescription,
                ElisType
            FROM MedLabStudy
            WHERE MedLabStudyCode = ?
EOT;
$sqlInsertStudyTest = <<<EOT
            INSERT INTO MedLabStudyTest (
                LisTestId, MedLabStudyCode, InternalId,
                Name, IsInput, Description,
                Active, Type, MedLabStudyTestCodeParent,
                MedLabStudyTestCodeLink, DefaultFlag, LoincCode
            )
            SELECT
                LisTestId, ?, InternalId,
                Name, IsInput, Description,
                Active, Type, MedLabStudyTestCodeParent,
                MedLabStudyTestCodeLink, DefaultFlag, LoincCode
            FROM MedLabStudyTest
            WHERE MedLabStudyCode = ?
EOT;
$db->beginTransaction(); try { executeSql($db, $sqlInsertStudy, [ $studyId ]); $medLabStudyNewId = $db->lastInsertId('seq_MedLabStudy'); executeSql($db, $sqlInsertStudyTest, [ $medLabStudyNewId, $studyId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_lab_linkMisElis');