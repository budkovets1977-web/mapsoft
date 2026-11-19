<?php
require_once(__DIR__.'/../../init.php'); class med_lab_linkBiopsy { const TASK_NAME = 'medLabLinkBiopsy'; const DEBUG = false; private $db; private $extraSettings; private $results = []; private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } private function getSettings() { $extra = $this->extraSettings = readExtraSettings(getSessionValue('config'), self::TASK_NAME); $this->log("[0]Чтение параметров..."); $this->log(print_r($extra, true)); if (empty($extra)) { $this->results[0] = 'Ошибка чтения параметров [отсутствуют параметры]'; $this->log("[0]Чтение параметров... Ошибка [отсутствуют параметры]", 2); return false; } elseif (empty($extra->active)) { $this->results[0] = 'Ошибка чтения параметров [обмен данными запрещен]'; $this->log("[0]Чтение параметров... Ошибка [обмен данными запрещен]", 1); return false; } elseif (empty($extra->url)) { $this->results[0] = 'Ошибка чтения параметров [не указан URL адрес]'; $this->log("[0]Чтение параметров... Ошибка [не указан URL адрес]", 2); return false; } $this->log("[0]Чтение параметров... ОК"); return true; } private function readDirections($params = null) { $db = $this->db ? : $this->db = connectDb(); $extra = $this->extraSettings; $sqlDirections = <<<"EOT"
select
       COALESCE(EN.CodeOKPOReal, '0') "OfficeDirectCode",
       CAST(? as VARCHAR(255)) "OfficeDirectNm",
       CAST(? as INTEGER) "OfficeDirectId",
       COALESCE(MLS.MedLogStudyCode, 0) "OfficeDirectBiopsyId",
       1 "OfficeDirectObjectId",
       CAST(CASE COALESCE((select value
                           from htmUnit
                           where htmPageCode = MLS.htmPageCode
                             and name = 'OriginId_'), '')
                 WHEN '' THEN 0
                 WHEN 'биопсийный' THEN 1
                 WHEN 'операционный' THEN 2
            END as INTEGER) "OriginId",
       MP.numberhistorysickness "HistoryIllNmb",
       (select value
        from htmUnit
        where htmPageCode = MLS.htmPageCode
          and name = 'DepartmentNm_') "DepartmentNm",
       FN_cmFIO(Doctor.ManCode) "DirectDoctor",
       COALESCE(CAST((select {$db->dialect("SUBSTRING(value, 7, 4) || SUBSTRING(value, 4, 2) || SUBSTRING(value, 1, 2)", "value")}
                      from htmUnit
                      where htmPageCode = MLS.htmPageCode
                        and name = 'processingDate') as Date), 19000101) "DateGet",
       COALESCE(CAST((select {$db->dialect("SUBSTRING(value, 7, 4) || SUBSTRING(value, 4, 2) || SUBSTRING(value, 1, 2)", "value")}
                      from htmUnit
                      where htmPageCode = MLS.htmPageCode
                        and name = 'dateSend') as Date), 19000101) "DateSend",
       COALESCE(M.Surname, '') "Surname",
       COALESCE(M.Name, '') "Fname",
       COALESCE(M.SecondName, '') "Sname",
       COALESCE(MPs.Sex, 0) "Sex",
       COALESCE(MPs.dateofbirth, 19000101) "Birthday",
       A_R.codesoato "SoatoCode",
       A_R.place "CityNm",
       A_R.street "StreetNm",
       CASE WHEN MPs.addresscorps is null THEN MPs.addresshouse
            ELSE MPs.addresscorps || '/' || MPs.addresshouse
       END "House",
       MPs.addressflat "Flat",
       COALESCE(A_L.codesoato, A_R.codesoato) "SoatoCodeLive",
       COALESCE(A_L.place, A_R.place) "CityNmLive",
       COALESCE(A_L.street, A_R.street) "StreetNmLive",
       CASE WHEN MPs.addressresidecode is null THEN CASE WHEN MPs.addresscorps is null THEN MPs.addresshouse
                                                         ELSE MPs.addresscorps || '/' || MPs.addresshouse
                                                    END
            ELSE CASE WHEN MPs.addressresidecorps is null THEN MPs.addressresidehouse
                      ELSE MPs.addressresidecorps || '/' || MPs.addressresidehouse
                 END
       END "HouseLive",
       MPs.addressresideflat "FlatLive",
       COALESCE((select value
                 from htmUnit
                 where htmPageCode = MLS.htmPageCode
                   and name = 'ResearchObject_'), '') "ResearchObject",
       (select COALESCE(value, longvalue, NULL)
        from htmUnit
        where htmPageCode = MLS.htmPageCode
          and name = 'ClinicData_') "ClinicData",
       CASE WHEN md.cipher1 IS NOT NULL or md.cipher1 <> '' THEN md.cipher1 ELSE '' END ||
       CASE WHEN md.Name IS NOT NULL or md.Name <> '' THEN ' - ' || md.Name ELSE '' END ||
       CASE WHEN COALESCE((select value
                           from htmUnit
                           where htmPageCode = MLS.htmPageCode
                             and name = 'DsClinical_'), '') <> '' THEN '. ' || (select value
                                                                                from htmUnit
                                                                                where htmPageCode = MLS.htmPageCode
                                                                                  and name = 'DsClinical_') ELSE '' END "DsClinical",
       'false' "IsCito", -- Признак экспресс биопсии
       'false' "IsPaid" -- Признак платной биопсии
from MedLogStudy MLS
    join MedPatient MP on MP.MedPatientCode = MLS.MedPatientCode
    join Enterprise E on E.EnterpriseCode = MP.EnterpriseCode
    join EnterpriseNumber EN on EN.EnterpriseCode = E.EnterpriseCode
    join Man M on M.ManCode = MP.ManCode
    join ManPassport MPs on MPs.ManCode = MP.ManCode
    left join Address A_R on A_R.AddressCode = MPs.AddressCode
    left join Address A_L on A_L.AddressCode = MPs.Addressresidecode
    left join MedDiagnosis MD on MD.medDiagnosisCode = MLS.medDiagnosisCode
    left join MedDoctor Doctor on Doctor.MedDoctorCode = MLS.MedDoctorCode
where MLS.BiopsySyncStatus = 1
  and MLS.MedStudyCode IN ($extra->studies)
  and MP.EnterpriseCode = ?
EOT;
$stmtDirections = $db->prepare($sqlDirections); $stmtDirections->execute([ empty($extra->organizationName) ? '' : $extra->organizationName, empty($extra->officeDirectId) ? 0 : $extra->officeDirectId, $extra->organizationId ]); $ret = []; foreach ($stmtDirections->fetchAll() as $row) { $ret[] = (object) $row; } return $ret; } private function updateDirections($data) { $db = $this->db ? : $this->db = connectDb(); $inMedStudy = createSqlInCondition($data, 'OfficeDirectBiopsyId'); $sql = <<<"EOT"
{$db->postgre("with upd as (")}
update MedLogStudy set
       BiopsySyncStatus = 2
where MedLogStudyCode $inMedStudy
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$db->beginTransaction(); $cnt = 0; try { $stmt = $db->prepare($sql); $stmt->execute(); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = fetchValue($stmt, 'cnt'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("[3]Обновление сатуса исследований в БД... Ошибка", 1); return false; } $this->log("[3]Обновление сатуса исследований в БД... Количество обновленных записей: $cnt"); return true; } public function postDirections() { $this->extraSettings ? : $this->getSettings(); if (empty($this->extraSettings)) { return $this->results; } $this->log("[1]Выбор исследований для отправки..."); if ($data = $this->readDirections()) { $cnt = count($data); $this->log("[1]Выбор исследований для отправки... Количество выбранных исследований: $cnt"); $this->results[1] = "Направлений для отправки: $cnt"; $this->log("[2]Отправка исследований..."); if ($result = $this->postDir($data)) { if ($result['status'][1] === "200") { $this->log("   Отправка запроса...ОК"); $this->log("[2]Отправка исследований... Количество отправленных исследований: $cnt"); $this->results[1] .= "; отправлено: $cnt"; $this->log("[3]Обновление сатуса исследований в БД..."); $this->updateDirections($data); return $this->results; } $this->log("[2]Отправка исследований... {$result['status'][0]}\r\n{$result['body']}", 1); $this->results[1] .= '; ошибка отправки'; return $this->results; } $this->log("[2]Отправка исследований... Ошибка", 1); $this->results[1] .= '; ошибка отправки'; return $this->results; } $this->results[1] = "Направлений для отправки: 0"; $this->log("[1]Выбор исследований для отправки... Нет данных для отправки", 1); return $this->results; } private function postDir($data) { $extra = $this->extraSettings; $this->log("   Инициализация подключения..."); if ($params = httpCreateParam($extra)) { $this->log("   Инициализация подключения... ОК"); $params->request_method = 'PostBiopsyClinic'; $params->request_content = json_encode($data, JSON_UNESCAPED_UNICODE); $params->debug = self::DEBUG; $this->log("   Отправка запроса..."); return httpPOST($params); } $this->log("   Инициализация подключюения... Ошибка", 1); return false; } private function updateResultDir($params) { $db = $this->db ? : $this->db = connectDb(); $rawData = isEmptyProperty($params, 'rawData'); $sql = <<<"EOT"
{$db->postgre("with upd as (")}
update MedLogStudy set
       RawResult = ?,
       BiopsySyncStatus = 3,
       State = 2,
       LogNumber = ?,
       execTime = ?
where MedLogStudyCode = ?
{$db->postgre("returning *)")}
{$db->postgre("select COUNT(*) \"cnt\" from upd")}
EOT;
$db->beginTransaction(); $cnt = 0; try { $stmt = $db->prepare($sql); $stmt->execute([ $rawData, isEmptyProperty($params, 'logNumber'), Date("Ymd H:i:s", safe_strtotime(isEmptyProperty($params, 'execTime'))), isEmptyProperty($params, 'studyId') ]); if ($db->isSybase) { $check = $db->prepare("select @@ROWCOUNT cnt"); $check->execute(); $cnt = fetchValue($check, 'cnt'); } else { $cnt = fetchValue($stmt, 'cnt'); } $cnt != 0 ? : $this->log("  Не найдена запись \r\n" . print_r($rawData, true), 1); $db->commit(); } catch (Throwable $e) { $this->log("  Ошибка обновления \r\n" . print_r($rawData, true), 1); $db->rollback(); return false; } return $cnt != 0; } public function getResults($params = null) { $this->extraSettings ? : $this->getSettings(); if (empty($this->extraSettings)) { return $this->results; } $this->log("[1]Получение результатов исследований..."); if (!$ret = $this->getDir($params)) { $err = httpError(); $errStr = httpErrorStr(); $this->log("[1]Получение результатов исследований... Ошибка: [$err] $errStr", 1); $this->results[2] = 'Ошибка приема'; return $this->results; } $this->log("   Отправка запроса...ОК"); if ($ret['status'][1] === "200") { $dir = safe_json_decode($ret['body']); $cnt = count($dir); $this->results[2] = "Получено исследований: $cnt"; $this->log("[1]Получение результатов исследований... Количество полученных исследований: $cnt"); $this->log("[2]Обработка результатов исследований..."); if (!empty($dir)) { $doneArray = []; $par = new stdClass(); $this->log("[3]Обновление результатов исследований в БД..."); $cntError = 0; foreach ($dir as $row) { $par->studyId = $row->OfficeDirectBiopsyId; $par->logNumber = $row->BiopsyNmb; $par->execTime = $row->DateAnswer; $par->rawData = json_encode($row, JSON_UNESCAPED_UNICODE); $this->updateResultDir($par) ? $doneArray[] = $row->Id : $cntError++; unset($par->studyId); unset($par->rawData); unset($par->execTime); unset($par->logNumber); } $cntDone = count($doneArray); $this->results[2] .= "; обновлено: $cntDone"; $this->results[2] .= "; пропущено: $cntError"; $this->log("[3]Обновление результатов исследований в БД... Количество успешно обновленных исследований: $cntDone"); $this->log("[3]Обновление результатов исследований в БД... Количество пропущенных при обновлении исследований: $cntError"); if (!empty($doneArray)) { $this->log("[4]Отправка подтверждения..."); $this->log("[4]Отправка подтверждения... Количество отправляемых исследований: $cntDone"); if (!$this->putDone($doneArray)) { $this->log("[4]Отправка подтверждения... Ошибка", 1); return $this->results; } $this->log("   Отправка запроса...ОК"); $this->log("[4]Отправка подтверждения... ОК"); } $this->log("[2]Обработка результатов исследований... ОК"); return $this->results; } else { $this->log("[1]Получение результатов исследований... Отсутствуют исследования"); $this->results[2] = "Получено исследований: 0"; return $this->results; } } $this->log("[1]Получение результатов исследований... {$ret['status'][0]}\r\n{$ret['body']}", 1); $this->results[2] = "Получено исследований: 0"; return $this->results; } private function putDone($data) { $extra = $this->extraSettings; $this->log("   Инициализация подключения..."); if ($params = httpCreateParam($extra)) { $this->log("   Инициализация подключения... OK"); $params->request_method = "ConfirmBiopsyRecived"; $params->request_content = json_encode($data, JSON_UNESCAPED_UNICODE); $params->headers['Content-Type'] = "application/json"; $params->debug = self::DEBUG; $this->log("   Отправка запроса..."); return httpPUT($params); } $this->log("   Инициализация подключения... Ошибка", 1); return false; } private function getDir($param = null) { $extra = $this->extraSettings; $this->log("   Инициализация подключения..."); if ($params = httpCreateParam($extra)) { $this->log("   Инициализация подключения... OK"); $params->request_method = "GetBiopsyWithoutConfirm"; $params->param = array( 'officeDirectId' => $extra->officeDirectId, 'onlyNotOfficeDirectReceived' => isset($param->isNew) ? ($param->isNew ? 'true' : 'false') : 'true', 'skip' => 0, 'limit' => 100 ); $params->debug = self::DEBUG; $this->log("   Отправка запроса..."); return httpGET($params); } $this->log("   Инициализация подключения... Ошибка", 1); return false; } public function startExchange($params = null) { $this->getResults($params); $this->postDirections(); return $this->results; } } handleJsonRpc('med_lab_linkBiopsy');