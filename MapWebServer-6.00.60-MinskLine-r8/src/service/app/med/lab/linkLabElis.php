<?php
 require_once(__DIR__ . '/../../init.php'); class med_lab_linkLabElis { const TASK_NAME = 'medElis'; const DEBUG_HTTP = false; const DIRECTION_STATUS_SEND = 7; private $db; private $extraSettings; private $cfg = null; private $syncErrorMessage = null; public function startExchange() { $this->log('Запуск функции полного обмена данными startExchange()...'); try { $this->getSettings(); $directions = $this->getDirections(); $message2 = $this->saveResults($directions); $message3 = $this->sendResults(); $this->log('Работа функции полного обмена данными startExchange() завершена'); return "$message2 <br> $message3"; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате выполнения функции полного обмена данными произошла ошибка: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } private function getExternalFiles($htmCode) { $db = $this->getDb(); $this->log('Получение файлов исследования...'); $sql = <<<"EOT"
SELECT
    encode(MEFT.data, 'base64') AS file,
    MEFT.filename AS fileName
FROM
    MedExternalFile MEFT
WHERE
    MEFT.source = 20 and MEFT.sourceCode = ?
EOT;
return selectSql($db, $sql, [$htmCode]); } private function getDirections() { if (!$params = httpCreateParam($this->extraSettings)) { $message = "Ошибка инициализация подключения (synchronizeStudies)"; $this->log($message, 2); return $message; } $params->request_method = 'lab/getdirectionsbylabguid'; $params->param = array( 'guid' => $this->extraSettings->misId ); $params->debug = self::DEBUG_HTTP; $params->timeout = 60; $response = httpGET($params); $result = null; if (!is_array($response)) { $this->log("getDirections: httpGET вернул " . gettype($response) . " (соединение с ЦОД ЕЛИС не установлено либо превышен таймаут)", 2); $this->log("Выполнение прервано", 2); return []; } if (!isNullArrayValue($response['status'], 1)) { $this->log('пустой ответ', 2); } switch (isEmptyArrayValue($response['status'], 1)) { case "200": if ($response['body']) { $result = json_clean_decode($response['body']); } $this->log(" Получение списка принятых направлений завершено"); $ids = []; foreach ($result as $value) { $id = isEmptyProperty($value, 'id'); $ids[] = $id; } $this->setOkDirection($ids); break; default: $result = $result ?: $response['body']; $message = "  При получении списка принятых направлений произошла ошибка: $result"; $this->log($message, 2); $this->log("Выполнение прервано", 2); break; } return $result; } private function saveResults($directions = null) { $db = $this->getDb(); if (!is_array($directions)) { if (is_string($directions) && $directions !== '') { $this->log("saveResults: получена строка вместо массива: $directions", 2); } return '1. Не получено новых направлений'; } if ($directions) { $cnt = count($directions); $arrInternalId = []; $arrCodes = []; $arrBarcodes = []; $arrCiphers = []; $arrNewDiagnosisCode = []; $cnt_ok = 0; $cnt_error = 0; $cnt_update = 0; foreach ($directions as $value) { $data = isEmptyProperty($value, 'data'); if ($data) { $data = json_clean_decode($value->data); $value->data = $data; $internalId = isEmptyProperty($data, 'medStudyInternalId'); $cipher1 = isEmptyProperty($data, 'medDiagnosisCipher1'); $internalId && $arrInternalId[$internalId] = null; $cipher1 && $arrCiphers[$cipher1] = null; } else { $message = "  Получены пустые данные, id: $value->id"; $this->log($message, 2); } } $sizeArrCiphers = sizeof($arrCiphers); if (sizeof($arrInternalId)) { $sqlStudyCodeIn = ' in('; foreach ($arrInternalId as $key => $value) { $sqlStudyCodeIn .= $key . ','; } $sqlStudyCodeIn = substr($sqlStudyCodeIn, 0, -1); $sqlStudyCodeIn .= ')'; if ($sizeArrCiphers) { $sqlCiphersIn = " in('"; foreach ($arrCiphers as $key => $value) { $sqlCiphersIn .= $key . "','"; } $sqlCiphersIn = substr($sqlCiphersIn, 0, -2); $sqlCiphersIn .= ')'; $sqlDiagnosisCode = <<<EOT
SELECT
    md.medDiagnosisCode,
    md.cipher1
FROM medDiagnosis md
WHERE
    md.cipher1 $sqlCiphersIn
EOT;
} $sqlStudyCode = <<<EOT
SELECT
    ms.internalId,
    ms.medStudyCode
FROM medLabStudy ms
WHERE
    ms.ElisType  = 2 AND
    ms.internalId $sqlStudyCodeIn
EOT;
$sqlBarcodes = <<<"EOT"
SELECT
    MedLogStudyCode "id",
    Barcode "barcode"
FROM medlogstudy
WHERE barcodesource = 3
EOT;
try { $labStudyCodes = selectSql($db, $sqlStudyCode); $barcodes = selectSql($db, $sqlBarcodes); foreach ($labStudyCodes as $value) { $arrCodes[$value['internalid']] = $value['medstudycode']; } foreach ($barcodes as $value) { $arrBarcodes[$value['barcode']] = $value['id']; } if ($sizeArrCiphers) { $labDiagnosisCode = selectSql($db, $sqlDiagnosisCode); foreach ($labDiagnosisCode as $value) { $arrNewDiagnosisCode[$value['cipher1']] = $value['meddiagnosiscode']; } } } catch (Throwable $e) { $message = $e->getMessage(); $this->log("В результате получения данных из medLabStudy и medDiagnosis произошла ошибка: $message", 2); return 'В результате получения данных из medLabStudy и medDiagnosis произошла ошибка'; } $sql = <<<EOT
INSERT INTO MedLogStudy (
    MedStudyCode,
    DirTime,
    BarCode,
    htmPageCode,
    State,
    Type,
    PatientFIO,
    PatientDateOfBirth,
    PatientAddress,
    PatientSex,
    MedDiagnosisCode,
    RawResult,
    BiopsySyncStatus,
    ProcessingDate,
    EnterpriseCodeFrom
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
EOT;
$sqlUpdate = <<<EOT
UPDATE MedLogStudy SET
    MedStudyCode = ?,
    DirTime = ?,
    State = ?,
    Type = ?,
    PatientFIO = ?,
    PatientDateOfBirth = ?,
    PatientAddress = ?,
    PatientSex = ?,
    MedDiagnosisCode = ?,
    RawResult = ?,
    BiopsySyncStatus = ?,
    ProcessingDate = ?,
    EnterpriseCodeFrom = ?
WHERE BarCode = ?
AND lognumber IS NULL
RETURNING MedLogStudyCode
EOT;
foreach ($directions as $value) { $data = isEmptyProperty($value, 'data'); $internalId = $data ? isEmptyProperty($data, 'medStudyInternalId') : null; $exInternalId = array_key_exists($internalId, $arrCodes); $barCode = isEmptyProperty($value, 'barcode'); if (!$data || !$barCode || !$internalId) continue; if (!$exInternalId) { $cnt_error++; $this->log("  При записи направления произошла ошибка, не найден medStudyCode по internalId: $internalId"); continue; } $cipher1 = isEmptyProperty($data, 'medDiagnosisCipher1'); $exCipher1 = array_key_exists($cipher1, $arrNewDiagnosisCode); $rawResult = isEmptyProperty($data, 'RawResult'); if ($rawResult) { $search = array( '"citizenship_BLR":"False","citizenship_other":"False"', '"citizenship_BLR":"True"', '"citizenship_BLR":"False",', '"citizenship_other":"True"', '"citizenship_other":"False",', '"paid":"False"', '"paid":"True"'); $replace = array( '"citizenship":"РБ"', '"citizenship":"РБ"', '', '"citizenship":"Иностранец"', '', '"paid":"Нет"', '"paid":"Да"' ); $rawResult = str_replace($search, $replace, $rawResult); } $enterpriseFromName = trim(isEmptyProperty(safe_json_decode($rawResult), 'Name', '')); $processingDate = dbDateTime(isEmptyProperty($data, 'ProcessingDate')); $dirTime = dbDateTime(isEmptyProperty($data, 'DirTime')); $studyId = null; $enterpriseCodeFrom = $this->getEnterpriseCode($db, $enterpriseFromName); if (!empty($arrBarcodes[$barCode])) { try { $studyId = selectSqlValue($db, $sqlUpdate, [ $arrCodes[$internalId], $dirTime, 1, 1, isEmptyProperty($data, 'PatientFIO'), isEmptyProperty($data, 'PatientDateOfBirth'), isEmptyProperty($data, 'PatientAddress'), isEmptyProperty($data, 'PatientSex', 0), ($cipher1 && $exCipher1) ? $arrNewDiagnosisCode[$cipher1] : null, $rawResult, 6, $processingDate, $enterpriseCodeFrom, $barCode ]); } catch (Throwable $e) { $cnt_error++; $message = "  При обновлении направления произошла ошибка: $barCode"; $this->log($message, 2); } if ($studyId) { $cnt_update++; continue; } } try { executeSql($db, $sql, [ $arrCodes[$internalId], $dirTime, $barCode, null, 1, 1, isEmptyProperty($data, 'PatientFIO'), isEmptyProperty($data, 'PatientDateOfBirth'), isEmptyProperty($data, 'PatientAddress'), isEmptyProperty($data, 'PatientSex', 0), ($cipher1 && $exCipher1) ? $arrNewDiagnosisCode[$cipher1] : null, $rawResult, 6, $processingDate, $enterpriseCodeFrom ]); $cnt_ok++; } catch (Throwable $e) { $cnt_error++; $message = "  При записи направления произошла ошибка: $barCode"; $this->log($message, 2); } } $this->log("  Направлений получено: $cnt_ok. Направлений обновлено: $cnt_update."); return "1. Прием Направлений завершен :<br>   - получено $cnt_ok;<br>   - ошибок $cnt_error;<br>   - обновлено $cnt_update."; } } return '1. Не получено новых направлений'; } private function setOkDirection($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (setOkDirection)"; $this->log($message, 2); return $message; } $params->request_method = 'lab/setOkDirections'; $params->param = array( 'guid' => $settings->misId, 'dirArr' => json_encode($data) ); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); return $response; } protected function getEnterpriseCode($db, $enterpriseName) { if (!$enterpriseName) { $this->log('Название организации передано не было!'); return 0; } $sqlCheck = <<<"EOT"
SELECT
E.enterpriseCode "id"
FROM Enterprise E
WHERE COALESCE( NULLIF( ASA_TRIM( E.FullName ), '' ), E.Name ) = :enterpriseName
EOT;
$result = selectSqlValue($db, $sqlCheck, [':enterpriseName' => $enterpriseName]); if ($result) { return $result; } $sqlInsert = <<<"EOT"
INSERT INTO Enterprise (name, fullname, startDate, finishDate)
VALUES (:enterpriseName, :enterpriseName, 19000101, 30000101)
RETURNING EnterpriseCode
EOT;
$enterpriseId = selectSqlValue($db, $sqlInsert, [':enterpriseName' => $enterpriseName]); $sqlInsertInfo = <<<"EOT"
INSERT INTO EnterpriseInfo (Name, FullName, EnterpriseCode, ChangeDate)
VALUES (:enterpriseName, :enterpriseName, :enterpriseId, 19000101)
EOT;
selectSqlValue($db, $sqlInsertInfo, [ ':enterpriseName' => $enterpriseName, ':enterpriseId' => $enterpriseId ]); return $enterpriseId; } private function sendResults() { $db = $this->getDb(); $this->log("  Отправка результатов..."); $sqlGetResult = <<<EOT
SELECT
    map.JSON_OBJECT( '', map.JSON_Array( 'medLogStudies', -- все исследования = элементы массива
(SELECT
  STRING_AGG(Q1.string, ',') FROM
(SELECT
map.JSON_OBJECT( '',
map.JSON_Numeric( 'Attributes', COALESCE(LS.Attributes,0) ) || -- признаки платности и гражданства
map.JSON_String( 'BarCode', LS.BarCode ) || -- печатается на пробирке, id-ик направления в ЕЛИС
map.JSON_Numeric( 'BarCodeSource', LS.BarCodeSource ) || -- источник штрих-кода
map.JSON_String( 'Conclusion', LS.Conclusion ) || -- заключение
map.JSON_String( 'Department', LS.Department ) || -- отделение
map.JSON_String( 'Htmpagecode', LS.HtmPageCode ) || -- htmpagecode направления в лаборатории
map.JSON_String( 'PublicationDate', LS.PublicationDate ) || --  дата-время начала обработки исследования
map.JSON_String( 'DirNumber', LS.DirNumber ) || -- номер направления
map.JSON_String( 'DirTime', DATEFORMAT( LS.DirTime, 'yyyy-mm-dd hh:nn:ss' )) ||
map.JSON_String( 'DoctorFIO', FN_cmFIO( DrExec.ManCode )) || -- ФИО врача-исполнителя (лаборанта)
map.JSON_String( 'Email', LS.Email ) ||
map.JSON_Numeric( 'EnterpriseCodePoliclinic', Eexec.EnterpriseCode ) || -- id организации врача-исполнителя
map.JSON_String( 'EnterpriseNamePoliclinic', COALESCE( NULLIF( ASA_TRIM( Eexec.FullName ), '' ), Eexec.Name ) ) || -- название организации врача-исполнителя
map.JSON_String( 'ExecTime', DATEFORMAT( LS.ExecTime, 'yyyy-mm-dd hh:nn:ss' )) || -- дата-время завершения выполнения исследования
map.JSON_String( 'LogNumber', LS.LogNumber ) || -- номер в журнале (лаборатории)
map.JSON_Numeric( 'medLogStudyCode', LS.medLogStudyCode ) || -- id направления в базе лаборатории
map.JSON_Numeric( 'medStudyCode', LS.medStudyCode ) || -- id вида исследования в базе лаборатории
map.JSON_Numeric( 'medStudyInternalId', labS.InternalId ) || -- id вида исследования в ЕЛИС
map.JSON_String( 'medStudyName', S.Name ) || -- название исследования в базе лаборатории
map.JSON_String( 'additional_data', LS.json )  || -- дополнительные данные по исследованию
map.JSON_String( 'RawResult', coalesce(
      NULLIF((SELECT map.JSON_Object('',STRING_AGG(map.JSON_String(
          hp.destname,
          hu.value, 0),','), 0)
          FROM htmpageunitlist hp
            JOIN medstudy ms ON ms.htmpagecode = hp.htmpagecode
            JOIN medlogstudy ml ON ms.medstudycode = ml.medstudycode
            LEFT JOIN htmunit hu ON hu.htmpagecode = ml.htmpagecode AND hp.name = hu.name
          WHERE ml.medlogstudycode =  LS.medlogstudycode
            AND hp.listtype = 2),'{}'),
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
                                                                                    'RPR,RPR_value,RPR_titr,RPR_select,RPGA,RPGA_value,RPGA_titr,RPGA_select,RIF_abs,RIF_abs_value,RIF_abs_titr,RIF_ABS_select,RIF_200,RIF_200_value,RIF_200_titr,RIF_200_select,IFA,IFA_value,IFA_select,DNK_VPCH_VKR,DateMenstrualCycle,PatientFIO_conclusion,' ||
                                                                                    'Conclusion,MedDoctorSignDate,Надпись_заключение,ЛПУ (название),PatientOperations,HormonalDrugs,PersonalNumber,тест,Пациент_заключение,' ||
                                                                                    'HormonalDrugsDuration,PatientPhone,LastDatePregnancy,DNK_VPCH_VKR_hpv_value,BuhExecSignDate,PersonalMedicalNumber,PersonalMedicalNumberSt'), '<unit>(.*?)</unit>', 'gi') u))) ||

map.JSON_String( 'SendTime', DATEFORMAT( LS.SendTime, 'yyyy-mm-dd hh:nn:ss' )) || -- время передачи направления (BiopsySyncStatus = 2)
map.JSON_Numeric( 'SequenceNumber', LS.SequenceNumber ) || -- номер очереди направления в базе лаборатории
map.JSON_Numeric( 'State', LS.State ) ||
map.JSON_String( 'StatusText', LS.StatusText ) ||
map.JSON_Numeric( 'Type', LS.Type, 0 )
, 0 ) as "string"
FROM medLogStudy LS
 JOIN medLabStudy labS ON labS.medLabStudyCode = (SELECT MIN(labS_.medLabStudyCode) FROM medLabStudy labS_ WHERE labS_.medStudyCode = LS.medStudyCode AND labS_.Active = 1 AND labS_.ELIStype = 2)
 LEFT OUTER JOIN medStudy S ON S.medStudyCode = LS.medStudyCode
 LEFT OUTER JOIN medDoctor DrExec ON DrExec.medDoctorCode = LS.medDoctorCodeExec
 LEFT OUTER JOIN Enterprise Eexec ON Eexec.EnterpriseCode = (SELECT Br.EnterpriseCode FROM Branch Br, Department D WHERE D.DepartmentCode = DrExec.DepartmentCode AND Br.BranchCode = D.BranchCode LIMIT 1)
WHERE LS.BiopsySyncStatus = 6
 AND LS.State = 2 -- работа по исследованию выполнена
 AND LS.ExecTime IS NOT NULL -- есть дата/время выполнения окончания работы над исследованием
ORDER BY LS.ExecTime, LS.ProcessingDate, LS.DirTime, LS.medLogStudyCode
LIMIT 100) AS Q1), 0 ), 0 )
EOT;
$sqlValue = selectSqlValue($db, $sqlGetResult); $data = safe_json_decode($sqlValue); if (is_null($data)) { for ($i = 0; $i <= 31; ++$i) { $sqlValue = str_replace(chr($i), '', $sqlValue); } $sqlValue = str_replace(chr(127), '', $sqlValue); if (0 === safe_strpos(bin2hex($sqlValue), 'efbbbf')) { $sqlValue = substr($sqlValue, 3); } $data = safe_json_decode($sqlValue); } $studies = $data->medLogStudies; $cnt = count($studies); if (!$cnt) { $this->log('Не найдено новых результатов', 2); return '2. Не найдено новых результатов'; } $this->log(" Результатов для отправки: $cnt"); $result = new stdClass(); $result->medLogStudies = array(); foreach ($studies as $study) { $el = new stdClass(); $files = $this->getExternalFiles($study->medLogStudyCode); if (!empty($files)) { $study->study_files = json_encode($files); } $el->data = $study; $el->barcode = $study->BarCode; array_push($result->medLogStudies, $el); if ($study->additional_data) { $additional_data = json_decode($study->additional_data); if ($additional_data -> DNK_VPCH_VKR_KOL_hpv) { $vpchJsonVal = $additional_data->DNK_VPCH_VKR_KOL_hpv; $rawResult = json_decode($study->RawResult); $rawResult -> DNK_VPCH_VKR_KOL_hpv = $vpchJsonVal; $study->RawResult = json_encode($rawResult); } } } try { $response = $this->postDir($result); $msg = ''; if (!isNullArrayValue($response['status'], 1)) { $msg = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $this->updateDirectionsSuccessful($studies); break; default: $msg = $msg ?: safe_json_decode($response['body']); $this->log("  При отправке результатов произошла ошибка: {$msg}", 2); } } catch (Throwable $e) { $this->log("  Ошибка: {$e->getMessage()}", 2); throw $e; } $message = "2. Отправка результатов завершена ($cnt)."; $this->log("  $message"); return $message; } public function updateLabStudy($records) { $db = $this->getDb(); $sql = <<<"EOT"
UPDATE medLabStudy SET
  MedStudyCode = CASE WHEN ? = 1 THEN ? ELSE MedStudyCode END
WHERE medLabStudyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'studyId'), isEmptyProperty($row, 'studyId'), $row->id ]); $param = new stdClass; $param->id = $row->id; $ret = $this->readLabStudy($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readLabStudy($params = null) { $db = $this->getDb(); $id = isEmptyProperty($params, 'id') ? $params->id : 0; $cfg = $this->cfg ?: getSessionValue('config'); $settings = readExtraSettings($cfg, self::TASK_NAME); if (is_object($settings)) { $organizationType = isUndefinedProperty($settings, 'organizationType', false); if (!$organizationType) { return array(); } } $sql = <<<EOT
            SELECT
                Sec.MedLabStudySectionCode * -1 "id",
                NULL                            "parentId",
                Sec.Name                        "name",
                'Код ЛИС : ' || Sec.LisId       "description",
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
                St.Description                  "description",
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
return selectSql($db, $sql); } private function updateDirectionsSuccessful($studies) { $db = $this->getDb(); $sql = <<<"EOT"
UPDATE MedLogStudy SET
    BiopsySyncStatus = :status
WHERE MedLogStudyCode = :medLogStudyCode
EOT;
$db->beginTransaction(); try { foreach ($studies as $study) { $medLogStudyCode = isEmptyProperty($study, 'medLogStudyCode'); if ($medLogStudyCode) { executeSql($db, $sql, [ ':medLogStudyCode' => $medLogStudyCode, ':status' => self::DIRECTION_STATUS_SEND ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("Обновление данных и статуса исследований в БД. Ошибка:" . $e->getMessage(), 2); return false; } return true; } private function postDir($data) { $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (postDirections)"; $this->log($message, 2); return $message; } $params->request_method = 'lab/addresults'; $params->param = array('guid' => $settings->misId); $params->request_content = json_encode($data); $params->debug = self::DEBUG_HTTP; $response = httpPOST($params); return $response; } private function getSettings() { if (!empty($this->extraSettings)) { return $this->extraSettings; } $cfg = $this->cfg ?: getSessionValue('config'); $result = null; $settingsValid = true; $this->log('Загрузка настроек...'); $settings = readExtraSettings($cfg, self::TASK_NAME); if (empty($settings)) { $this->log('Не произведена настройка обмена информацией', 2); } elseif ($settings->active) { if (empty($settings->url)) { $this->log('Не заполнена настройка Адрес сервера API', 2); $settingsValid = false; } if (empty($settings->misId)) { $this->log('Не заполнена настройка Код МИС', 2); $settingsValid = false; } if (empty($settings->organizationId)) { $this->log('Не заполнена настройка Организация', 2); $settingsValid = false; } if ($settingsValid) { $settings->task = self::TASK_NAME; $this->log('Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } } else { $this->log('В настройках запрещен обмен данными', 1); return $settings; } return $result; } private function getDb() { return $this->db ?: $this->db = connectDb($this->cfg); } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); if ($type == 1 || $type == 2) { if (!$this->syncErrorMessage) { $this->syncErrorMessage = $message; } else { $this->syncErrorMessage .= "\n$message"; } } } public function synchronizeStudies() { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = isUndefinedProperty($settings, 'organizationType'); $active = isUndefinedProperty($settings, 'active'); if (!$active || !$organizationType) { throw new Error('Неоходимо установить настройки перед синхронизацией!'); } $this->log("  Синхронизация справочника исследований..."); if (!$params = httpCreateParam($settings)) { $message = "Ошибка инициализация подключения (synchronizeStudies)"; $this->log($message, 2); return $message; } $params->request_method = 'lis/getlaboratoriesbymisguid'; $params->param = array( 'guid' => $settings->misId ); $params->debug = self::DEBUG_HTTP; $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $laboratories = safe_json_decode($response['body']); break; default: $result = $result ?: $response['body']; $message = "  При получении справочника исследований произошла ошибка: $result"; $this->log($message, 2); $this->log("Выполнение прервано", 2); return $message; } $sqlSelectMedLabStudySection = <<<EOT
            SELECT MedLabStudySectionCode
            FROM MedLabStudySection
            WHERE LisSectionId = ?
EOT;
$sqlInsertMedLabStudySection = <<<EOT
            INSERT INTO MedLabStudySection (LisSectionId, Name, InternalId, LisId)
            VALUES (?, ?, ?, ?)
EOT;
$sqlUpdateMedLabStudySection = <<<EOT
            UPDATE MedLabStudySection SET
                Name = ?
            WHERE MedLabStudySectionCode = ?
EOT;
$sqlSelectMedLabStudy = <<<EOT
            SELECT MedLabStudyCode "medLabStudyId"
            FROM MedLabStudy
            WHERE LisStudyId = ?
EOT;
$sqlInsertMedLabStudy = <<<EOT
            INSERT INTO MedLabStudy (
                LisStudyId, MedLabStudySectionCode, InternalId,
                Name, Description, ProcedureRoom,
                Active, BarcodeType, RequiredDiagnosis,
                ShortName, BarCodeWideBarRatio, BarCodeZoom,
                PregnancyObligatory, AnalyserName, AnalyserDescription,
                ElisType
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
            WHERE LisStudyId = ?
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
$total_count = 0; $cnt_update = 0; $cnt_insert = 0; $cnt_delete = 0; $db->beginTransaction(); try { $cnt_delete += $this->clearMedLabStudySection(createSqlInCondition($laboratories, 'Id')); foreach ($laboratories as $laboratory) { $total_count++; $medLabStudySectionId = selectSqlValue($db, $sqlSelectMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id') ]); if ($medLabStudySectionId) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudySection, [ isUndefinedProperty($laboratory, 'Name'), $medLabStudySectionId ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudySection, [ isUndefinedProperty($laboratory, 'Id'), isUndefinedProperty($laboratory, 'Name'), isUndefinedProperty($laboratory, 'InternalId'), isUndefinedProperty($laboratory, 'LisId') ]); $medLabStudySectionId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudySection'); } $analyzes = isUndefinedProperty($laboratory, 'Analyses'); $cnt_delete += $this->clearMedLabStudy(createSqlInCondition($analyzes, 'Id'), $medLabStudySectionId); foreach ($analyzes as $analysis) { $total_count++; $lisStudyId = isUndefinedProperty($analysis, 'Id'); $studyIds = selectSql($db, $sqlSelectMedLabStudy, [ $lisStudyId ], PDO::FETCH_OBJ); if (count($studyIds) > 0) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudy, [ isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription'), $lisStudyId ]); } else { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudy, [ $lisStudyId, $medLabStudySectionId, isUndefinedProperty($analysis, 'InternalId'), isUndefinedProperty($analysis, 'Name'), isUndefinedProperty($analysis, 'Description'), isUndefinedProperty($analysis, 'ProcedureRoom', 1), isUndefinedProperty($analysis, 'IsActive'), isUndefinedProperty($analysis, 'BarCodeType'), isUndefinedProperty($analysis, 'IsDiagnosisObligatory'), isUndefinedProperty($analysis, 'ShortName'), isUndefinedProperty($analysis, 'BarCodeWideBarRatio'), isUndefinedProperty($analysis, 'BarCodeZoom'), isUndefinedProperty($analysis, 'PregnancyObligatory'), isUndefinedProperty($analysis, 'AnalyserName'), isUndefinedProperty($analysis, 'AnalyserDescription'), $organizationType ]); $newStudy = new stdClass; $newStudy->medLabStudyId = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId('seq_MedLabStudy'); array_push($studyIds, $newStudy); } $tests = isUndefinedProperty($analysis, 'Tests'); $cnt_delete += $this->clearMedLabStudyTest(createSqlInCondition($tests, 'Id'), $lisStudyId); foreach ($tests as $test) { $total_count++; $LisTestId = isUndefinedProperty($test, 'Id'); $testsCount = selectSqlValue($db, $sqlSelectMedLabStudyTest, [ $LisTestId ]); if ($testsCount > 0) { $cnt_update++; executeSql($db, $sqlUpdateMedLabStudyTest, [ isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), isUndefinedProperty($test, 'TryTree'), isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode'), $LisTestId ]); } else { foreach ($studyIds as $row) { $cnt_insert++; executeSql($db, $sqlInsertMedLabStudyTest, [ isUndefinedProperty($test, 'Id'), isUndefinedProperty($row, 'medLabStudyId'), isUndefinedProperty($test, 'InternalId'), isUndefinedProperty($test, 'Name'), isUndefinedProperty($test, 'IsInput'), isUndefinedProperty($test, 'Description'), isUndefinedProperty($test, 'IsActive'), isUndefinedProperty($test, 'Type'), isUndefinedProperty($test, 'TryTree'), isUndefinedProperty($test, 'TryLink'), isUndefinedProperty($test, 'Choice'), isUndefinedProperty($test, 'LoincCode') ]); } } } } } $db->commit(); $message = "  1. Синхронизация справочника исследований завершена.<br>  Синхронизировано $total_count записей:<br>   - добавлено $cnt_insert;<br>   - обновлено $cnt_update;<br>   - удалено $cnt_delete."; $this->log($message); return $message; } catch (Throwable $e) { $db->rollback(); $message = $e->getMessage(); $this->log("  Ошибка синхронизации справочника исследований: $message", 2); $this->log("Выполнение прервано", 2); throw $e; } } private function clearMedLabStudySection($ids) { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = $settings->organizationType; $sql = <<<"EOT"
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
{$db->postgre("SELECT COUNT(*) \"cnt\" FROM upd")}
EOT;
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } private function clearMedLabStudy($ids, $sectionId) { $db = $this->getDb(); $settings = $this->getSettings(); $organizationType = $settings->organizationType; $sql = <<<"EOT"
{$db->postgre("with upd as (")}
DELETE FROM MedLabStudy
WHERE LisStudyId NOT $ids
  AND MedLabStudySectionCode = $sectionId
  AND elisType = $organizationType
{$db->postgre("returning *)")}
{$db->postgre("SELECT COUNT(*) \"cnt\" FROM upd")}
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
$cnt = 0; $res = selectSqlValue($db, $sql); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = $res; } return $cnt; } public function cloneStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isEmptyProperty($params, 'studyId'); $sqlInsertStudy = <<<EOT
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
$db->beginTransaction(); try { executeSql($db, $sqlInsertStudy, [ $studyId ]); $medLabStudyNewId = $db->lastInsertId('seq_MedLabStudy'); executeSql($db, $sqlInsertStudyTest, [ $medLabStudyNewId, $studyId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_lab_linkLabElis');