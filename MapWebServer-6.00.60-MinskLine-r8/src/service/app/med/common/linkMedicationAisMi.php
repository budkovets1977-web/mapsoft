<?php
set_time_limit(1800); require_once(__DIR__ . '/../../init.php'); class med_common_linkMedicationAisMi { const DEBUG_HTTP = 0; const DETAILED_LOG = 0; const TASK_NAME = "linkMedicationAisMi"; private ?object $extraSettings; private ?PDO_ANY $db = null; private ?string $task = null; private function initVars(?string $taskName = null): void { $this->task = $taskName ?? self::TASK_NAME; } private function log(string $message, int $type = 0) { app_log($message, $type, $this->task); } private function getSettings() { self::DETAILED_LOG && $this->log("    [getSettings] Вызов getSettings."); if (!empty($this->extraSettings)) { self::DETAILED_LOG && $this->log("    [getSettings] Настройки уже загружены, возвращаем кэшированные."); return $this->extraSettings; } self::DETAILED_LOG && $this->log('    [getSettings] Загрузка настроек из конфигурации...'); $settings = readExtraSettings(getSessionValue('config'), $this->task); self::DETAILED_LOG && $this->log("    [getSettings] Настройки получены из конфигурации. Передаем на проверку checkSettings."); return $this->checkSettings($settings); } private function checkSettings(object $settings) { self::DETAILED_LOG && $this->log("    [checkSettings] Начало проверки настроек."); $settingsValid = true; $result = null; if (empty($settings)) { $this->log('    [checkSettings] Настройки пусты. Обмен не настроен.', 2); } elseif (isset($settings->active) && $settings->active) { self::DETAILED_LOG && $this->log("    [checkSettings] Обмен активен. Проверяем обязательные поля..."); if (empty($settings->url)) { $this->log('    [checkSettings] Не заполнена настройка "Адрес сервера API"', 2); $settingsValid = false; } else { self::DETAILED_LOG && $this->log("    [checkSettings] url: {$settings->url}"); } if (empty($settings->organizationId)) { $this->log('    [checkSettings] Не заполнена настройка "Идентификатор организации"', 1); } else { self::DETAILED_LOG && $this->log("    [checkSettings] organizationId: {$settings->organizationId}"); } if (empty($settings->enterpriseCode)) { $this->log('    [checkSettings] Не заполнена настройка "Код организации в БД"', 1); } else { self::DETAILED_LOG && $this->log("    [checkSettings] enterpriseCode: {$settings->enterpriseCode}"); } if ($settingsValid) { self::DETAILED_LOG && $this->log("    [checkSettings] Все обязательные поля заполнены. Формируем итоговый URL..."); $settings->task = $this->task; if (substr($settings->url, -1) !== '/') { self::DETAILED_LOG && $this->log("    [checkSettings] URL не заканчивается на '/', добавляем слэш."); $settings->url .= "/"; } self::DETAILED_LOG && $this->log("    [checkSettings] Итоговый базовый URL: {$settings->url}"); $this->log('    [checkSettings] Настройки загружены успешно.'); $this->extraSettings = $settings; $result = $this->extraSettings; } else { $this->log("    [checkSettings] Настройки содержат ошибки. Exception.", 2); throw new Exception('  Проверьте правильность заполнения данных для подключения.'); } } else { $this->log('    [checkSettings] В настройках запрещен обмен данными.', 1); throw new Exception('В настройках запрещен обмен данными'); } return $result; } private function sendData(?object $params = null): string { self::DETAILED_LOG && $this->log("    [sendData] Начало выполнения."); $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)(getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); self::DETAILED_LOG && $this->log("    [sendData] enterpriseId=$enterpriseId"); $settings = $this->getSettings(); $organizationAISKey = isUndefinedProperty($settings, 'organizationId'); $url = isUndefinedProperty($settings, 'url'); $organizationName = isUndefinedProperty($settings, 'organizationName'); $records = null; $title = "Передача сведений об остатках ИМН в АИС МИ"; $this->log("    [sendData] $title"); $sections = createSqlInCondition(json_clean_decode($settings->usedSections)); $sources = (array)json_clean_decode($settings->matSources); $sources = 'IN (' . trim(implode(', ', array_keys($sources))) . ')'; self::DETAILED_LOG && $this->log("    [sendData] SQL-условия: sources=$sources, sections=$sections"); $message = ''; $sql = <<<SQL
            WITH TBuhMol AS (
                SELECT BuhMolCode
                FROM BuhMol
                WHERE CURRENT_DATE BETWEEN StartDate AND FinishDate
                    AND COALESCE(NoBalance, 0) != 1
            ),
            --
            -- Остатки от МОЛов. Таблица MedCalc.
            -- Запрос по МОЛам.
            -- Выбираем предметы у которых нет номенклатурного кода и ищем по партии поставки
            -- упаковки, дозировку и номенклатурный код в поставке. Переводим количество в упаковки.
            TBalancesFromMedCalc AS (
                SELECT
                    UIL.GUID                        AS GUID,
                    MW.MatWealthCode                AS MatWealthCode,
                    FN_cmMatWealthNameEx(SR.MatWealthCodeD, 7)
                                                    AS UOM,
                    (COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0)) / M.DoseNumber
                                                    AS QuantityCounterparty, -- Переводим в упаковки
                    MC.BuhMolCode                   AS BuhMolCode,
                    UIL.UniImportLinkCode           AS UniImportLinkCode,
                    SR.MatSourceCode                AS MatSourceCode,
                    MWS.MatWealthSectionCode        AS MatWealthSectionCode,
                    BM.WorkerCode                   AS WorkerCode,
                    SR.ValidTerm                    AS ExpirationDate,
                    MWS.FormType                    AS FormType
                FROM MedCalc                MC
                JOIN MedCalcDate            MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth              MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject        MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                JOIN MatWealthSection       MWS ON MWS.MatWealthSectionCode = MWO.MatWealthSectionCode
                JOIN SklRow                 SR ON SR.SklRowCode = MC.SklRowCode
                    AND SR.SklRowCode > 0
                    AND COALESCE(MWS.FormType, 0) = 3
                LEFT JOIN MatWealth         M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                LEFT JOIN BuhMol            BM ON BM.BuhMolCode = MC.BuhMolCode
                LEFT JOIN UniImportLink     UIL ON UIL.UniImportLinkCode = M.UniImportLinkCode
                LEFT JOIN WorkerInfo        WI ON WI.WorkerCode = BM.WorkerCode
                WHERE MC.EnterpriseCode = :enterpriseId
                    AND MC.BuhMolCode IN (SELECT BuhMolCode FROM TBuhMol)
                    AND MCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM MedCalcDate
                        WHERE MedCalcCode = MC.MedCalcCode
                            AND OnDate <= CURRENT_DATE
                    )
                    AND COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0) > 0
                    AND COALESCE(MW.UniImportLinkCode, 0) = 0
                    AND COALESCE(M.DoseNumber, 0) != 0
                --
                UNION ALL
                --
                -- Запрос по МОЛам.
                -- Номенклатурный код у предмета есть. Берем как есть. Ничего не конвертируем.
                SELECT
                    UIL.GUID                        AS GUID,
                    MW.MatWealthCode                AS MatWealthCode,
                    FN_cmMatWealthNameEx(MW.MatWealthCode, 7)
                                                    AS UOM,
                    COALESCE(MCD.AmountD, 0) - COALESCE(MCD.AmountK, 0)
                                                    AS QuantityCounterparty,
                    MC.BuhMolCode                   AS BuhMolCode,
                    UIL.UniImportLinkCode           AS UniImportLinkCode,
                    SR.MatSourceCode                AS MatSourceCode,
                    MWS.MatWealthSectionCode        AS MatWealthSectionCode,
                    BM.WorkerCode                   AS WorkerCode,
                    SR.ValidTerm                    AS ExpirationDate,
                    MWS.FormType                    AS FormType
                FROM MedCalc                MC
                JOIN MedCalcDate            MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth              MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject        MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                JOIN MatWealthSection       MWS ON MWS.MatWealthSectionCode = MWO.MatWealthSectionCode
                JOIN SklRow                 SR ON SR.SklRowCode = MC.SklRowCode
                    AND COALESCE(MWS.FormType, 0) = 3 -- Медикаменты
                LEFT JOIN BuhMol            BM ON BM.BuhMolCode = MC.BuhMolCode
                LEFT JOIN WorkerInfo        WI ON WI.WorkerCode = BM.WorkerCode
                LEFT JOIN UniImportLink     UIL ON UIL.UniImportLinkCode = MW.UniImportLinkCode
                WHERE MC.EnterpriseCode = :enterpriseId
                    AND MC.BuhMolCode IN (SELECT BuhMolCode FROM TBuhMol)
                    AND MCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM MedCalcDate
                        WHERE MedCalcCode = MC.MedCalcCode
                            AND OnDate <= CURRENT_DATE
                    )
                    AND COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0) > 0
                    AND COALESCE(MW.UniImportLinkCode, 0) != 0
            ),
            --
            -- Выбираем МОЛов из запроса выше для исключения их в дальнейшем
            TMolsExclude AS (
                SELECT BM.WorkerCode
                FROM TBalancesFromMedCalc BMC
                JOIN BuhMol BM ON BM.BuhMolCode = BMC.BuhMolCode
                    AND BMC.WorkerCode IS NOT NULL
            ),
            --
            -- Остатки по Аптеке(склад). Таблица SklCard.
            -- Выбираем предметы у которых нет номенклатурного кода и ищем по партии поставки
            -- упаковки, дозировку и номенклатурный код в поставке. Переводим количество в упаковки.
            TBalancesFromSklCard AS (
                SELECT
                    UIL.GUID                        AS GUID,
                    W.MatWealthCode                 AS MatWealthCode,
                    FN_cmMatWealthNameEx(SR.MatWealthCodeD, 7)
                                                    AS UOM,
                    (COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0)) / M.DoseNumber
                                                    AS QuantityCounterparty, -- Переводим в упаковки
                    UIL.UniImportLinkCode           AS UniImportLinkCode,
                    SC.MatSourceCode                AS MatSourceCode,
                    MWS.MatWealthSectionCode        AS MatWealthSectionCode,
                    SC.WorkerCode                   AS WorkerCode,
                    SR.ValidTerm                    AS ExpirationDate,
                    MWS.FormType                    AS FormType
                FROM MatWealth              W
                LEFT JOIN MatWealthObject   MWO ON MWO.MatWealthObjectCode = W.MatWealthObjectCode
                JOIN MatWealthSection       MWS ON MWS.MatWealthSectionCode = MWO.MatWealthSectionCode
                LEFT JOIN SklCard           SC ON SC.MatWealthCode = W.MatWealthCode
                    AND SC.WorkerCode NOT IN (SELECT WorkerCode FROM TMolsExclude)
                    AND SC.EnterpriseCode = :enterpriseId
                LEFT JOIN WorkerInfo        WI ON WI.WorkerCode = SC.WorkerCode
                LEFT JOIN SklRow            SR ON SR.SklRowCode = SC.SklRowCode
                LEFT JOIN MatWealth         M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                LEFT JOIN UniImportLink     UIL ON UIL.UniImportLinkCode = M.UniImportLinkCode
                LEFT JOIN SklCardDate       SCD ON SCD.SklCardCode = SC.SklCardCode
                    AND SCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM SklCardDate
                        WHERE SklCardCode = SC.SklCardCode
                            AND OnDate <= CURRENT_DATE
                    )
                WHERE COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0) > 0
                    AND COALESCE(W.UniImportLinkCode, 0) = 0
                    AND COALESCE(M.DoseNumber, 0) != 0
                --
                UNION ALL
                --
                -- Номенклатурный код у предмета есть. Берем как есть. Ничего не конвертируем.
                SELECT
                    UIL.GUID                        AS GUID,
                    W.MatWealthCode                 AS MatWealthCode,
                    FN_cmMatWealthNameEx(W.MatWealthCode, 7)
                                                    AS UOM,
                    COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0)
                                                    AS QuantityCounterparty,
                    UIL.UniImportLinkCode           AS UniImportLinkCode,
                    SC.MatSourceCode                AS MatSourceCode,
                    MWS.MatWealthSectionCode        AS MatWealthSectionCode,
                    SC.WorkerCode                   AS WorkerCode,
                    SR.ValidTerm                    AS ExpirationDate,
                    MWS.FormType                    AS FormType
                FROM UniImportLink      UIL
                JOIN MatWealth          W ON UIL.UniImportLinkCode = W.UniImportLinkCode
                JOIN MatWealthObject    MWO ON MWO.MatWealthObjectCode = W.MatWealthObjectCode
                JOIN MatWealthSection   MWS ON MWS.MatWealthSectionCode = MWO.MatWealthSectionCode
                JOIN SklCard            SC ON SC.MatWealthCode = W.MatWealthCode
                    AND SC.WorkerCode NOT IN (SELECT WorkerCode FROM TMolsExclude)
                    AND SC.EnterpriseCode = :enterpriseId
                JOIN WorkerInfo         WI ON WI.WorkerCode = SC.WorkerCode
                JOIN SklRow             SR ON SR.SklRowCode = SC.SklRowCode
                LEFT JOIN MatWealth     M ON M.MatWealthCode = SR.MatWealthCodeD
                LEFT JOIN SklCardDate   SCD ON SCD.SklCardCode = SC.SklCardCode
                    AND SCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM SklCardDate
                        WHERE SklCardCode = SC.SklCardCode
                            AND OnDate <= CURRENT_DATE
                    )
                WHERE COALESCE(SCD.AmountD, 0.0) - COALESCE (SCD.AmountK, 0.0) > 0
                    AND COALESCE (W.UniImportLinkCode, 0) != 0
                --
                UNION ALL
                --
                -- MAP-15152
                SELECT
                    UIL.GUID                        AS GUID,
                    W.MatWealthCode                 AS MatWealthCode,
                    FN_cmMatWealthNameEx(W.MatWealthCode, 7)
                                                    AS UOM,
                    COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0)
                                                    AS QuantityCounterparty,
                    UIL.UniImportLinkCode           AS UniImportLinkCode,
                    SC.MatSourceCode                AS MatSourceCode,
                    MWS.MatWealthSectionCode        AS MatWealthSectionCode,
                    SC.WorkerCode                   AS WorkerCode,
                    SR.ValidTerm                    AS ExpirationDate,
                    MWS.FormType                    AS FormType
                FROM UniImportLink      UIL
                JOIN MatWealth          W ON UIL.UniImportLinkCode = W.UniImportLinkCode
                JOIN MatWealthObject    MWO ON MWO.MatWealthObjectCode = W.MatWealthObjectCode
                JOIN MatWealthSection   MWS ON MWS.MatWealthSectionCode = MWO.MatWealthSectionCode
                JOIN SklCard            SC ON SC.MatWealthCode = W.MatWealthCode
                    AND SC.WorkerCode IN (SELECT WorkerCode FROM TMolsExclude)
                    AND SC.EnterpriseCode = :enterpriseId
                    AND COALESCE(MWS.FormType, 0) != 3 -- Медикаменты (MAP-15152)
                JOIN WorkerInfo         WI ON WI.WorkerCode = SC.WorkerCode
                JOIN SklRow             SR ON SR.SklRowCode = SC.SklRowCode
                LEFT JOIN MatWealth     M ON M.MatWealthCode = SR.MatWealthCodeD
                LEFT JOIN SklCardDate   SCD ON SCD.SklCardCode = SC.SklCardCode
                    AND SCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM SklCardDate
                        WHERE SklCardCode = SC.SklCardCode
                            AND OnDate <= CURRENT_DATE
                    )
                WHERE COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0) > 0
                    AND COALESCE(W.UniImportLinkCode, 0) != 0
            ),
            --
            -- Объединяем запросы, получаем общие остатки по МОЛам и Аптеке(складу)
            TBalances AS (
                SELECT
                    BMC.GUID,
                    BMC.MatWealthCode,
                    BMC.UOM,
                    BMC.QuantityCounterparty,
                    BMC.MatSourceCode,
                    BMC.ExpirationDate,
                    BMC.MatWealthSectionCode
                FROM UniImportLink U
                JOIN TBalancesFromMedCalc BMC ON BMC.UniImportLinkCode = U.UniImportLinkCode
                    AND U.Inactive = 1
                --
                UNION ALL
                --
                SELECT
                    BSC.GUID,
                    BSC.MatWealthCode,
                    BSC.UOM,
                    BSC.QuantityCounterparty,
                    BSC.MatSourceCode,
                    BSC.ExpirationDate,
                    BSC.MatWealthSectionCode
                FROM UniImportLink U
                JOIN TBalancesFromSklCard BSC ON BSC.UniImportLinkCode = U.UniImportLinkCode
                    AND U.Inactive = 1
                    -- Отсекам для склада записи для которых есть запись о МОЛе в бухучете
                    -- Смотри подробности в задаче WS-15945
                    AND BSC.WorkerCode NOT IN (
                        SELECT DISTINCT W.WorkerCode
                        FROM BuhMol B
                        LEFT JOIN WorkerInfo W ON W.WorkerCode = B.WorkerCode
                        LEFT JOIN EnterpriseStructure E ON E.EnterpriseStructureCode = W.EnterpriseStructureCode
                        WHERE CURRENT_DATE BETWEEN B.StartDate AND B.FinishDate
                            AND COALESCE(B.NoBalance, 0) != 1
                            AND COALESCE(B.RestByDoc, 0) = 1
                            AND COALESCE(W.WorkerCode, 0) != 0
                            AND E.EnterpriseCode = :enterpriseId
                    )
            )
            --
            SELECT
                TB.GUID                         "guid",
                TB.MatWealthCode                "matWealthId",
                FN_cmMatWealthNameEx(TB.MatWealthCode, 8)
                                                "name",
                TB.UOM                          "uom", -- Unit of measurement
                TB.MatSourceCode                "matSourceId",
                EXTRACT(epoch FROM TB.ExpirationDate)::BIGINT
                                                "expirationDate",
                SUM(TB.QuantityCounterparty)    "quantity"
            FROM TBalances TB
            WHERE TB.MatWealthSectionCode $sections
                AND TB.MatSourceCode $sources
            GROUP BY
                "guid",
                "matWealthId",
                "name",
                "uom",
                "matSourceId",
                "expirationDate"
            ORDER BY
                "guid"
            SQL;
$totalCount = 0; try { $records = selectSql($db, $sql, array("enterpriseId" => $enterpriseId), PDO::FETCH_OBJ); if (!empty($records)) { $totalCount = count($records); $this->log("    [sendData] SQL выполнен. Получено записей: $totalCount."); } } catch (Throwable $e) { $message = $e->getMessage(); $this->log("    [sendData] Исключение при выполнении SQL: $message", 2); throw $e; } $this->log("    [sendData] Инициализация HTTP-подключения для отправки данных..."); if (!$params = httpCreateParam($settings)) { $message = "$title.<br>Ошибка инициализации подключения."; $this->log("    [sendData] Ошибка инициализации подключения (httpCreateParam вернул false).", 2); return $message; } if ($totalCount === 0) { $msg = "Не найдено записей для отправки."; $this->log("    [sendData] $msg Отправка прервана."); return "$title.<br>$msg."; } $csvUtf8 = $this->generateUTF8CSV($records); $params->url = $url; $hash = md5(uniqid()); $params->headers['X-API-Key'] = $organizationAISKey; $params->headers['Content-Type'] = "multipart/form-data; boundary=$hash"; $body = "--$hash\r\n"; $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"Остатки_ОЗ_" . date('Y-m-d') . ".csv\"\r\n"; $body .= "Content-Type: text/csv\r\n\r\n"; $body .= $csvUtf8 . "\r\n"; $body .= "--$hash--\r\n"; $params->body = $body; $params->needCertificate = true; $params->customCaFile = false; $params->debug = self::DEBUG_HTTP; $this->log("    [sendData] URL для POST запроса: {$url}"); $this->log("    [sendData] Отправляемые данные: \r\n" . $csvUtf8); $this->log("    [sendData] Всего записей для отправки: $totalCount"); $this->log("    [sendData] Отправляем POST запрос..."); $response = httpPOST($params); if (!$response) { $message = "$title.<br>Ошибка подключения. Указан неверный URL, либо сервис недоступен."; $this->log("    [sendData] Ошибка подключения. URL: {$params->url}", 2); return $message; } $status = isEmptyArrayValue($response["status"], 1); $this->log("    [sendData] Ответ сервера получен. HTTP статус: $status"); $result = null; if (!isNullArrayValue($response["status"], 1)) { $result = "пустой ответ"; $this->log("    [sendData] Статус ответа пуст.", 1); } $serverMsg = ''; $taskId = ''; switch ($status) { case "201": $resp = $response["body"]; $res = safe_json_decode($resp); if ($res) { if ($res->message && $res->task_id) { $serverMsg = $res->message; $taskId = $res->task_id; $message = "$title.<br>$serverMsg<br>TaskId на сервере АИС МИ: $taskId<br>Отправлено записей: <b>$totalCount</b>"; } else { $message = "$title.<br>Отправлено записей: <b>$totalCount</b>"; } $this->log("    [sendData] Сервер вернул статус $status. Сообщение: $serverMsg", 2); $this->log("    [sendData] TaskId на сервере АИС МИ $taskId", 2); } else { $bodyPreview = mb_substr($response["body"], 0, 10000); $msg = "Ошибка отправки данных. Сервер вернул некорректный ответ: $bodyPreview"; $this->log("    [sendData] $msg", 2); return "$title.<br>$msg"; } break; default: $resDetail = ''; $resp = $response["body"]; $res = safe_json_decode($resp); if ($res) { $resDetail = $res->detail; } $result = $result ?: mb_substr($response["body"], 0, 10000); $message = "$title.<br>Сервер вернул ответ: Ошибка $status. $resDetail"; $this->log("    [sendData] Ошибка $status. Тело: $result", 2); return $message; } self::DETAILED_LOG && $this->log("    [sendData] Завершено успешно. Отправлено записей: $totalCount"); return $message; } private function generateUTF8CSV(array $records): string { $settings = $this->getSettings(); $organizationName = isEmptyProperty($settings, 'organizationName'); $sources = (array)json_clean_decode($settings->matSources); $scvLines[] = implode(';', [ 'Организация', 'ИМН', 'Наименование', 'Цена', 'Количество', 'ТипФинансирования', 'ЕдиницыИзмерения', 'СрокГодности', 'Тендер', 'Договор', 'Производитель', 'Поставщик', 'Страна', 'СтранаПроизводитель', 'ДатаВыгрузки' ]); $AISCodes = [ 1 => 'republic', 2 => 'locale', 3 => 'own' ]; foreach ($records as $row) { $row = (array)$row; $expirationDate = $row['expirationDate']; $expirationDate = $expirationDate ? date('d.m.Y', $row['expirationDate']) : ''; $scvLines[] = sprintf( '%s;%s;%s;;%s;%s;%s;%s;;;;;;;%s', $this->csvEscape($organizationName), $row['guid'], $this->csvEscape($row['name']), number_format($row['quantity'], 2, '.', ''), $AISCodes[$sources[$row['matSourceId']]], $row['uom'], $expirationDate, date('d.m.Y') ); } $csv = implode("\r\n", $scvLines); $csvUtf8 = mb_convert_encoding($csv, 'UTF-8'); return $csvUtf8; } private function csvEscape(string $value): string { if (strpbrk($value, ';"') !== false) { return '"' . str_replace('"', '""', $value) . '"'; } return $value; } public function getOrganizationId() { self::DETAILED_LOG && $this->log("    [getOrganizationId] Запрос идентификатора организации из БД."); $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)getSessionValue('mapEnterpriseCode'); self::DETAILED_LOG && $this->log("    [getOrganizationId] enterpriseId=$enterpriseId"); $result = selectSqlValue($db, "SELECT CodeHl7 FROM EnterpriseNumber WHERE EnterpriseCode = :enterpriseId LIMIT 1", array("enterpriseId" => $enterpriseId) ); self::DETAILED_LOG && $this->log("    [getOrganizationId] Получен CodeHl7: " . ($result ?? 'null')); return $result; } public function startExchange(?object $params = null): string { $task = isUndefinedProperty($params, 'task'); $this->initVars($task); $msg = ''; $this->log("============================================================================"); $this->log("[startExchange] Начинаем обмен данными с АИС МИ. Время: " . date('Y-m-d H:i:s')); try { self::DETAILED_LOG && $this->log("[startExchange] Шаг 1/2: загрузка и проверка настроек..."); $this->getSettings(); self::DETAILED_LOG && $this->log("[startExchange] Настройки загружены."); self::DETAILED_LOG && $this->log("[startExchange] Шаг 2/2: отправка данных об остатках..."); $msg = $this->sendData($params); self::DETAILED_LOG && $this->log("[startExchange] sendData вернула сообщение: $msg"); $this->log("[startExchange] Обмен данными завершен. Время: " . date('Y-m-d H:i:s')); return $msg; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("[startExchange] КРИТИЧЕСКАЯ ОШИБКА: $message", 2); self::DETAILED_LOG && $this->log("[startExchange] Стек вызовов: " . $e->getTraceAsString(), 2); throw $e; } } } handleJsonRpc('med_common_linkMedicationAisMi');