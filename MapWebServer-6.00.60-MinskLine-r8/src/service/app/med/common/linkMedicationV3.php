<?php
set_time_limit(1800); require_once(__DIR__ . '/../../init.php'); class med_common_linkMedicationV3 { const DEBUG_HTTP = false; const DETAILED_LOG = false; const TASK_NAME = "linkMedicationV3"; private ?object $extraSettings; private ?object $db = null; private string $lockFile; private string $pharmaListFile; private string $pharmaListFileFullPath; private string $pharmaListOldFileFullPath; private ?string $task = null; private ?string $pharmaList = null; private ?string $listName = null; private function initVars(?string $taskName = null): void { $this->task = $taskName ?? self::TASK_NAME; $this->pharmaList = $this->task . 'List'; $this->lockFile = getLogPath() . $this->task . '.lock'; $this->pharmaListFile = $this->pharmaList . '.log'; $this->pharmaListFileFullPath = getLogPath() . $this->pharmaListFile; $this->pharmaListOldFileFullPath = getLogPath() . $this->pharmaList . '_OLD.log'; if (self::DETAILED_LOG) { $this->log("============================================================================"); $this->log("[initVars] lockFile: {$this->lockFile}"); $this->log("[initVars] pharmaListFileFullPath: {$this->pharmaListFileFullPath}"); $this->log("[initVars] pharmaListOldFileFullPath: {$this->pharmaListOldFileFullPath}"); } } private function createLockFile() { self::DETAILED_LOG && $this->log("    [createLockFile] Попытка создать lock-файл: {$this->lockFile}"); if (!file_exists($this->lockFile)) { $pid = getmypid(); file_put_contents($this->lockFile, $pid); self::DETAILED_LOG && $this->log("    [createLockFile] Lock-файл создан. PID: $pid"); } else { self::DETAILED_LOG && $this->log("    [createLockFile] Lock-файл уже существует. Создание пропущено.", 1); } } private function deleteLockFile() { $lockFile = $this->lockFile; self::DETAILED_LOG && $this->log("    [deleteLockFile] Попытка удалить lock-файл: $lockFile"); if (file_exists($lockFile)) { unlink($lockFile); self::DETAILED_LOG && $this->log("    [deleteLockFile] Lock-файл удален."); } else { self::DETAILED_LOG && $this->log("    [deleteLockFile] Lock-файл не найден. Удаление пропущено.", 1); } } private function log(string $message, int $type = 0) { app_log($message, $type, $this->task); } private function link_log(string $message, string $fileName) { error_log($message, 3, getLogPath() . $fileName . '.log'); } private function getSettings() { self::DETAILED_LOG && $this->log("    [getSettings] Вызов getSettings."); if (!empty($this->extraSettings)) { self::DETAILED_LOG && $this->log("    [getSettings] Настройки уже загружены, возвращаем кэшированные."); return $this->extraSettings; } self::DETAILED_LOG && $this->log('    [getSettings] Загрузка настроек из конфигурации...'); $settings = readExtraSettings(getSessionValue('config'), $this->task); self::DETAILED_LOG && $this->log("    [getSettings] Настройки получены из конфигурации. Передаем на проверку checkSettings."); return $this->checkSettings($settings); } private function getRawSettings() { return readExtraSettings(getSessionValue('config'), $this->task); } private function checkSettings(object $settings) { self::DETAILED_LOG && $this->log("    [checkSettings] Начало проверки настроек."); $settingsValid = true; $result = null; if (empty($settings)) { $this->log('    [checkSettings] Настройки пусты. Обмен не настроен.', 2); } elseif (isset($settings->active) && $settings->active) { self::DETAILED_LOG && $this->log("    [checkSettings] Обмен активен. Проверяем обязательные поля..."); if (empty($settings->url)) { $this->log('    [checkSettings] Не заполнена настройка "Адрес сервера API"', 2); $settingsValid = false; } else { self::DETAILED_LOG && $this->log("    [checkSettings] url: {$settings->url}"); } if (empty($settings->serverName)) { $this->log('    [checkSettings] Не заполнена настройка "Имя сервера"', 2); $settingsValid = false; } else { self::DETAILED_LOG && $this->log("    [checkSettings] serverName: {$settings->serverName}"); } if (empty($settings->serviceName)) { $this->log('    [checkSettings] Не заполнена настройка "Сервис"', 2); $settingsValid = false; } else { self::DETAILED_LOG && $this->log("    [checkSettings] serviceName: {$settings->serviceName}"); } if (empty($settings->serviceRootUrl)) { $this->log('    [checkSettings] Не заполнена настройка "Корневой URL сервиса"', 2); $settingsValid = false; } else { self::DETAILED_LOG && $this->log("    [checkSettings] serviceRootUrl: {$settings->serviceRootUrl}"); } if (empty($settings->organizationId)) { $this->log('    [checkSettings] Не заполнена настройка "Идентификатор организации"', 1); } else { self::DETAILED_LOG && $this->log("    [checkSettings] organizationId: {$settings->organizationId}"); } if (empty($settings->enterpriseCode)) { $this->log('    [checkSettings] Не заполнена настройка "Код организации в БД"', 1); } else { self::DETAILED_LOG && $this->log("    [checkSettings] enterpriseCode: {$settings->enterpriseCode}"); } if ($settingsValid) { self::DETAILED_LOG && $this->log("    [checkSettings] Все обязательные поля заполнены. Формируем итоговый URL..."); $settings->task = $this->task; if (parse_url($settings->url, PHP_URL_PATH) !== "/") { self::DETAILED_LOG && $this->log("    [checkSettings] URL не заканчивается на '/', добавляем слэш."); $settings->url .= "/"; } $settings->url .= $settings->serverName; self::DETAILED_LOG && $this->log("    [checkSettings] URL после добавления serverName: {$settings->url}"); $settings->url = glueStrings($settings->url, $settings->serviceName, "/"); self::DETAILED_LOG && $this->log("    [checkSettings] URL после добавления serviceName: {$settings->url}"); $settings->url = glueStrings($settings->url, $settings->serviceRootUrl, "/"); self::DETAILED_LOG && $this->log("    [checkSettings] Итоговый базовый URL: {$settings->url}"); $this->log('    [checkSettings] Настройки загружены успешно.'); $this->extraSettings = $settings; $result = $this->extraSettings; } else { $this->log("    [checkSettings] Настройки содержат ошибки. Exception.", 2); throw new Exception('  Проверьте правильность заполнения данных для подключения.'); } } else { $this->log('    [checkSettings] В настройках запрещен обмен данными.', 1); throw new Exception('В настройках запрещен обмен данными'); } return $result; } private function buildUrl(string $url, string $resource) { self::DETAILED_LOG && $this->log("    [buildUrl] Построение URL. Базовый: $url, ресурс: $resource"); $settings = $this->getSettings(); $result = glueStrings( glueStrings($url, $resource, "/"), $settings->organizationId, "/" ); self::DETAILED_LOG && $this->log("    [buildUrl] URL: $result"); return $result; } private function getMedicationList(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int) (getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); $settings = $this->getSettings(); $listLastDownload = isUndefinedProperty($settings, 'listLastDownload'); $forceUpdate = isUndefinedProperty($params, 'forceUpdate'); $title = "Начата загрузка справочника товаров контролируемого перечня. Файл log/php/$this->pharmaListFile"; $this->log("    [getMedicationList] $title"); if (!$params = httpCreateParam($settings)) { $message = "$title.<br>Ошибка инициализации подключения"; $this->log("    [getMedicationList] Ошибка инициализации подключения", 2); return $message; } $error = 0; $startCode = 0; $limit = 500; $i = 0; $j = 0; $proceed = true; if (!$forceUpdate && $listLastDownload) { $listLastDownload = new DateTime($listLastDownload); $now = new DateTime(); $interval = $now->diff($listLastDownload); if ($listLastDownload < $now && $interval->days > 30) { if (file_exists($this->pharmaListFileFullPath)) { rename($this->pharmaListFileFullPath, $this->pharmaListOldFileFullPath); } } else { $msg = "Справочнику меньше 30 дней. Пропускаем обновление справочника."; $this->log("    [getMedicationList] $msg"); return $msg; } } else { if (file_exists($this->pharmaListFileFullPath)) { rename($this->pharmaListFileFullPath, $this->pharmaListOldFileFullPath); } } if (!file_exists($this->lockFile)) { $this->createLockFile(); } else { $msg = 'Функция getMedicationList уже выполняется! Повторный запуск до завершения запрещен!'; $this->log($msg, 2); return $msg; } $url = $params->url; $belPharmaList = []; do { if ($i > 240) { $this->link_log("\n============== ВНИМАНИЕ! Ошибка. Не удалось загрузить справочник полностью ==============", $this->pharmaList); $error = 'Ошибка загрузки справочника'; $this->log("    [getMedicationList] $error. Сервис не отвечает.", 2); $proceed = false; } $params->url = $url . '/MedicationBlock/' . $settings->organizationId . '/' . $startCode . '?limit=' . $limit . '&format=JSON'; $params->param = array( "format" => "json" ); $params->debug = self::DEBUG_HTTP; $params->headers['Accept'] = 'version=3'; $response = httpGET($params); if (!$response) { usleep(500000); $i++; continue; } switch (isEmptyArrayValue($response["status"], 1)) { case "200": $resp = $response["body"]; $items = safe_json_decode($resp); $resp = substr(trim($resp), 1, -1); if ($items) { $belPharmaList = array_merge($belPharmaList, $items); $lastItem = end($items); $aSearch = [ '},{', '}, {' ]; $aReplace = [ "}\n{", "}\n{" ]; $this->link_log(($j === 0 ? "============== Справочник контролируемого перечня товаров $this->listName ==============" : '') . "\n" . str_replace($aSearch, $aReplace, $resp), $this->pharmaList); $startCode = $lastItem->Code; } else { $this->link_log("\n============== Справочник загружен полностью ==============", $this->pharmaList); $proceed = false; } $j++; break; default: $e = $response["body"]; $this->log("    Ошибка: $e", 2); $error = 'Ошибка загрузки справочника'; $proceed = false; break; } usleep(300000); $i++; } while ($proceed); $this->deleteLockFile(); if ($error === 0) { $this->log("    [getMedicationList] Справочник товаров контролируемого перечня $this->listName загружен полностью"); } else { $this->log("    [getMedicationList] $error"); } $this->log("    [getMedicationList] Сохранение справочника в базу данных"); $db->beginTransaction(); try { foreach ($belPharmaList as $item) { $name = isUndefinedProperty($item, 'Name'); $name = iconv('UTF-8', 'WINDOWS-1251//IGNORE', $name); $name = iconv('WINDOWS-1251', 'UTF-8', $name); $cipher = isUndefinedProperty($item, 'Code'); $guid = isUndefinedProperty($item, 'Guid'); $cipherTTN = isUndefinedProperty($item, 'ExtraCode'); $sql = <<<SQL
                    SELECT
                        WebBelPharmaWealthCheckCode AS "id",
                        Name AS "name"
                    FROM WebBelPharmaWealthCheck
                    WHERE EnterpriseCode = :enterpriseId AND Guid = :guid
                    LIMIT 1
                    SQL;
$rows = selectSql($db, $sql, [ ":enterpriseId" => $enterpriseId, ":guid" => $item->Guid ]); if ($rows) { $dbItem = $rows[0]; $id = (int) $dbItem['id']; if ($id > 0) { if ($dbItem['name'] !== $name) { $sql = <<<SQL
                                UPDATE WebBelPharmaWealthCheck
                                SET EnterpriseCode = :enterpriseId,
                                    Name = :name,
                                    LinkCipher = :cipher,
                                    LinkCipherTTN = :cipherTTN,
                                    UpdateTime = NULL -- Обновится триггером БД
                                WHERE WebBelPharmaWealthCheckCode = :id
                                SQL;
executeSql($db, $sql, array( ":enterpriseId" => $enterpriseId, ":name" => $name, ":cipher" => $cipher, ":cipherTTN" => $cipherTTN, ":id" => $id )); } } } else { if (trim($guid) !== '') { $sql = <<<SQL
                            INSERT INTO WebBelPharmaWealthCheck (EnterpriseCode, Name, LinkCipher, Guid, LinkCipherTTN)
                            VALUES (:enterpriseId, :name, :cipher, :guid, :cipherTTN)
                            SQL;
executeSql($db, $sql, [ ":enterpriseId" => $enterpriseId, ":name" => $name, ":cipher" => $cipher, ":guid" => $guid, ":cipherTTN" => $cipherTTN ]); } } } $db->commit(); $rSettings = $this->getRawSettings(); $rSettings->listLastDownload = date('Y-m-d'); writeExtraSettings(getSessionValue('config'), $this->task, $rSettings); } catch (Throwable $e) { $db->rollback(); $message = $e->getMessage(); $this->log("    Ошибка: $message", 2); $error = $message; } return $error == 0 ? 'Справочник обновлен' : $error; } private function sendData(?object $params = null): string { self::DETAILED_LOG && $this->log("    [sendData] Начало выполнения."); $db = $this->db ?: $this->db = connectDb(); $manual = isUndefinedProperty($params, 'manual'); $enterpriseId = (int)(getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); self::DETAILED_LOG && $this->log("    [sendData] manual=$manual, enterpriseId=$enterpriseId"); $settings = $this->getSettings(); $title = "Передача сведений об остатках контролируемого перечня медикаментов v3"; $this->log("    [sendData] $title"); $esi = createSqlInCondition(json_clean_decode($settings->usedOffices)); $sources = createSqlInCondition(json_clean_decode($settings->usedMatSources)); $sections = createSqlInCondition(json_clean_decode($settings->usedSections)); $republicBudget = createSqlInCondition(json_clean_decode($settings->usedRepublicBudget)); self::DETAILED_LOG && $this->log("    [sendData] SQL-условия: esi=$esi, sources=$sources, sections=$sections, republicBudget=$republicBudget"); $message = ''; $sql = <<<SQL
            -- Остатки форма МАП Медицина - Движение медикаментов - Расчеты - Остаток медикаментов
            WITH TBuhMol AS (
                SELECT BuhMolCode
                FROM BuhMol
                WHERE CURRENT_DATE BETWEEN StartDate AND FinishDate
                    AND COALESCE(NoBalance, 0) != 1
            ),
            TBalancesFromMedCalc AS ( -- Остатки от МОЛов. Таблица MedCalc.
                -- Запрос по МОЛам.
                -- Выбираем предметы у которых нет номенклатурного кода и ищем по партии поставки
                -- упаковки, дозировку и номенклатурный код в поставке. Переводим количество в упаковки.
                SELECT
                    M.LinkCipherForRest::BIGINT     LinkCipher, -- Номенклатурный код
                    (COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0))/M.DoseNumber
                                                    QuantityCounterparty, -- Переводим в упаковки
                    SR.Party                        Series,
                    SR.ValidTerm                    ExpirationDate,
                    MC.BuhMolCode                   BuhMolCode,
                    MC.EnterpriseCode               EnterpriseCode,
                    SR.MatSourceCode                MatSourceCode,
                    CASE
                        WHEN SR.MatSourceCode $republicBudget
                            THEN TRUE
                            ELSE FALSE
                    END                             RepublicBudget,
                    MC.MatWealthCode                MatWealthCode
                FROM MedCalc MC
                JOIN MedCalcDate MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                LEFT JOIN SklRow SR ON SR.SklRowCode = MC.SklRowCode
                    AND SR.SklRowCode > 0
                    AND SR.MatSourceCode $sources
                LEFT JOIN MatWealth M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                WHERE MC.EnterpriseCode = :enterpriseId
                    AND MC.BuhMolCode IN (SELECT BuhMolCode FROM TBuhMol)
                    AND MCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM MedCalcDate
                        WHERE MedCalcCode = MC.MedCalcCode
                            AND OnDate <= CURRENT_DATE
                    )
                    AND COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0) > 0
                    AND COALESCE(MW.LinkCipher::BIGINT, 0) = 0
                    AND COALESCE(M.DoseNumber, 0) != 0
                    AND (
                        MC.BuhMolCode IN (
                            SELECT BM.BuhMolCode
                            FROM BuhMol BM
                            LEFT JOIN WorkerInfo WI ON WI.WorkerCode = BM.WorkerCode
                            WHERE WI.EnterpriseStructureCode $esi
                        )
                        OR
                        MC.BuhMolCode IN (
                            SELECT BM.BuhMolCode
                            FROM BuhMol BM
                            LEFT JOIN Department D ON D.DepartmentCode = BM.DepartmentCode
                            LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = D.DepartmentCode
                            WHERE ES.EnterpriseStructureCode $esi
                        )
                    )
                --
                UNION ALL
                --
                -- Запрос по МОЛам.
                -- Номенклатурный код у предмета есть. Берем как есть. Ничего не конвертируем.
                SELECT
                    MW.LinkCipherForRest::BIGINT    LinkCipher, -- Номенклатурный код
                    COALESCE(MCD.AmountD, 0) - COALESCE(MCD.AmountK, 0)
                                                    QuantityCounterparty,
                    SR.Party                        Series,
                    SR.ValidTerm                    ExpirationDate,
                    MC.BuhMolCode                   BuhMolCode,
                    MC.EnterpriseCode               EnterpriseCode,
                    SR.MatSourceCode                MatSourceCode,
                    CASE
                        WHEN SR.MatSourceCode $republicBudget
                        THEN TRUE
                        ELSE FALSE
                    END                             RepublicBudget,
                    MC.MatWealthCode                MatWealthCode
                FROM MedCalc MC
                JOIN MedCalcDate MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                LEFT JOIN SklRow SR ON SR.SklRowCode = MC.SklRowCode
                    AND SR.SklRowCode > 0
                    AND SR.MatSourceCode $sources
                WHERE MC.EnterpriseCode = :enterpriseId
                    AND MC.BuhMolCode IN (SELECT BuhMolCode FROM TBuhMol)
                    AND MCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM MedCalcDate
                        WHERE MedCalcCode = MC.MedCalcCode
                            AND OnDate <= CURRENT_DATE
                    )
                    AND COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0) > 0
                    AND COALESCE(MW.LinkCipher::BIGINT, 0) != 0
                    AND (
                        MC.BuhMolCode IN (
                            SELECT BM.BuhMolCode
                            FROM BuhMol BM
                            LEFT JOIN WorkerInfo WI ON WI.WorkerCode = BM.WorkerCode
                            WHERE WI.EnterpriseStructureCode $esi
                        )
                        OR
                        MC.BuhMolCode IN (
                            SELECT BM.BuhMolCode
                            FROM BuhMol BM
                            LEFT JOIN Department D ON D.DepartmentCode = BM.DepartmentCode
                            LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = D.DepartmentCode
                            WHERE ES.EnterpriseStructureCode $esi
                        )
                    )
            ),
            TMolsExclude AS ( -- Выбираем МОЛов из запроса выше для исключения их в дальнейшем
                SELECT BM.WorkerCode
                FROM TBalancesFromMedCalc BMC
                LEFT JOIN BuhMol BM ON BM.BuhMolCode = BMC.BuhMolCode
                WHERE BM.WorkerCode IS NOT NULL
            ),
            TBalancesFromSklCard AS ( -- Остатки по Аптеке(склад). Таблица SklCard.
                -- Выбираем предметы у которых нет номенклатурного кода и ищем по партии поставки
                -- упаковки, дозировку и номенклатурный код в поставке. Переводим количество в упаковки.
                SELECT
                    M.LinkCipherForRest::BIGINT     LinkCipher, -- Номенклатурный код по партии
                    (COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0))/M.DoseNumber
                                                    QuantityCounterparty, -- Переводим в упаковки
                    SR.Party                        Series,
                    SR.ValidTerm                    ExpirationDate,
                    SC.EnterpriseCode               EnterpriseCode,
                    SC.MatSourceCode                MatSourceCode,
                    CASE
                        WHEN SC.MatSourceCode $republicBudget
                        THEN TRUE
                        ELSE FALSE
                    END                             RepublicBudget,
                    W.MatWealthCode                 MatWealthCode
                FROM MatWealth W
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = W.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                JOIN SklCard SC ON SC.MatWealthCode = W.MatWealthCode
                    AND SC.EnterpriseCode = :enterpriseId
                    AND SC.MatSourceCode $sources
                    AND SC.WorkerCode NOT IN (SELECT WorkerCode FROM TMolsExclude)
                JOIN WorkerInfo WI ON WI.WorkerCode = SC.WorkerCode
                    AND WI.EnterpriseStructureCode $esi
                JOIN SklCardDate SCD ON SCD.SklCardCode = SC.SklCardCode
                    AND SCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM SklCardDate
                        WHERE SklCardCode = SC.SklCardCode
                            AND OnDate <= CURRENT_DATE
                    )
                LEFT JOIN SklRow SR ON SR.SklRowCode = SC.SklRowCode
                LEFT JOIN MatWealth M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                WHERE COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0) > 0
                    AND COALESCE(W.LinkCipher::BIGINT, 0) = 0
                    AND COALESCE(M.DoseNumber, 0) != 0
                ----
                UNION ALL
                ----
                -- Номенклатурный код у предмета есть. Берем как есть. Ничего не конвертируем.
                SELECT
                    W.LinkCipherForRest::BIGINT     LinkCipher,
                    COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0)
                                                    QuantityCounterparty,
                    SR.Party                        Series,
                    SR.ValidTerm                    ExpirationDate,
                    SC.EnterpriseCode               EnterpriseCode,
                    SC.MatSourceCode                MatSourceCode,
                    CASE
                        WHEN SC.MatSourceCode $republicBudget
                        THEN TRUE
                        ELSE FALSE
                    END                             RepublicBudget,
                    W.MatWealthCode                 MatWealthCode
                FROM MatWealth W
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = W.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                JOIN SklCard SC ON SC.MatWealthCode = W.MatWealthCode
                    AND SC.EnterpriseCode = :enterpriseId
                    AND SC.MatSourceCode $sources
                    AND SC.WorkerCode NOT IN (SELECT WorkerCode FROM TMolsExclude)
                JOIN WorkerInfo WI ON WI.WorkerCode = SC.WorkerCode
                    AND WI.EnterpriseStructureCode $esi
                JOIN SklCardDate SCD ON SCD.SklCardCode = SC.SklCardCode
                    AND SCD.OnDate = (
                        SELECT MAX(OnDate)
                        FROM SklCardDate
                        WHERE SklCardCode = SC.SklCardCode
                            AND OnDate <= CURRENT_DATE
                    )
                LEFT JOIN SklRow SR ON SR.SklRowCode = SC.SklRowCode
                LEFT JOIN MatWealth M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                WHERE COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0) > 0
                    AND COALESCE(W.LinkCipher::BIGINT, 0) != 0
            ),
            TBalances AS ( -- Объединяем запросы, получаем общие остатки по МОЛам и Аптеке(складу)
                SELECT
                    LinkCipher::BIGINT,
                    QuantityCounterparty,
                    Series,
                    ExpirationDate,
                    EnterpriseCode,
                    MatSourceCode,
                    RepublicBudget,
                    MatWealthCode
                FROM TBalancesFromMedCalc
                WHERE LinkCipher::BIGINT IS NOT NULL
                    AND EnterpriseCode = :enterpriseId
                    AND MatSourceCode IS NOT NULL
                --
                UNION ALL
                --
                SELECT
                    LinkCipher::BIGINT,
                    QuantityCounterparty,
                    Series,
                    ExpirationDate,
                    EnterpriseCode,
                    MatSourceCode,
                    RepublicBudget,
                    MatWealthCode
                FROM TBalancesFromSklCard
                WHERE LinkCipher::BIGINT IS NOT NULL
                    AND EnterpriseCode = :enterpriseId
                    AND MatSourceCode IS NOT NULL
            )
            -- Финальная выборка данных
            SELECT DISTINCT
                D.MedicationCode::BIGINT    "MedicationCode",
                D.QuantityCounterparty      "QuantityCounterparty",
                D.ExpirationDate::BIGINT    "ExpirationDate",
                D.Series                    "Series",
                FN_cmMatWealthNameEx(MW.MatWealthCode, 1)
                                            "Name",
                D.Id                        "Id",
                D.CP                        "CP"
            FROM (
                SELECT
                    LinkCipher::BIGINT          MedicationCode,
                    ROUND(SUM(QuantityCounterparty), 2)
                                                QuantityCounterparty,
                    Series                      Series,
                    EXTRACT(epoch FROM ExpirationDate)
                                                ExpirationDate,
                    LinkCipher::BIGINT
                        || CASE
                            WHEN ExpirationDate IS NOT NULL
                                THEN '-' || TO_CHAR(ExpirationDate, 'YYYYMMDD')
                                ELSE ''
                            END                 Id,
                    RepublicBudget              CP
                FROM TBalances
                GROUP BY
                    LinkCipher::BIGINT,
                    Series,
                    ExpirationDate,
                    RepublicBudget
                ORDER BY
                    LinkCipher::BIGINT,
                    Series,
                    ExpirationDate
            ) AS D
            LEFT JOIN (SELECT DISTINCT ON (LinkCipherForRest::BIGINT) * FROM MatWealth) MW ON MW.LinkCipherForRest::BIGINT = D.MedicationCode
            WHERE COALESCE(FN_cmMatWealthNameEx(MW.MatWealthCode, 1), '') != ''
            SQL;
$totalCount = 0; try { $records = selectSql($db, $sql, array("enterpriseId" => $enterpriseId), PDO::FETCH_OBJ); if (!empty($records)) { $totalCount = count($records); $this->log("    [sendData] SQL выполнен. Получено записей: $totalCount."); convertSqlParams($records, ["number" => "QuantityCounterparty"]); self::DETAILED_LOG && $this->log("    [sendData] Числовые параметры конвертированы (QuantityCounterparty)."); $this->dumpSentData($records); } } catch (Throwable $e) { $message = $e->getMessage(); $this->log("    [sendData] Исключение при выполнении SQL: $message", 2); throw $e; } $this->log("    [sendData] Инициализация HTTP-подключения для отправки данных..."); if (!$params = httpCreateParam($settings)) { $message = "$title.<br>Ошибка инициализации подключения."; $this->log("    [sendData] Ошибка инициализации подключения (httpCreateParam вернул false).", 2); return $message; } $params->url = ($this->buildUrl($params->url, "ControlledList")) . '/?format=json'; $params->debug = self::DEBUG_HTTP; $params->request_content = json_encode($records, JSON_UNESCAPED_UNICODE); $params->headers['Accept'] = 'version=3'; $this->log("    [sendData] URL для POST запроса: {$params->url}"); $this->log("    [sendData] Отправляемые данные: " . $params->request_content); $this->log("    [sendData] Всего записей для отправки: $totalCount"); if ($totalCount === 0) { $msg = "Не найдено записей для отправки."; $this->log("    [sendData] $msg Отправка прервана."); return "$title.<br>$msg."; } $this->log("    [sendData] Отправляем POST запрос..."); $response = httpPOST($params); if (!$response) { $message = "$title.<br>Ошибка подключения. Указан неверный URL, либо сервис недоступен."; $this->log("    [sendData] Ошибка подключения (httpPOST вернул false). URL: {$params->url}", 2); return $message; } $status = isEmptyArrayValue($response["status"], 1); $this->log("    [sendData] Ответ сервера получен. HTTP статус: $status"); $result = null; if (!isNullArrayValue($response["status"], 1)) { $result = "пустой ответ"; $this->log("    [sendData] Статус ответа пуст.", 1); } switch ($status) { case "200": $resp = $response["body"]; $res = safe_json_decode($resp); if ($res) { $pid = isUndefinedProperty($res, 'id'); $pStatus = isUndefinedProperty($res, 'status'); self::DETAILED_LOG && $this->log("    [sendData] Данные отправлены. Id пакета: $pid. Сервер вернул статус: $pStatus."); switch ($pStatus) { case "InProgress": $this->log("    [sendData] Пакет $pid принят сервером в обработку (InProgress)."); if ($manual) { $this->log("    [sendData] Ручной запуск. Статус пакета не ожидаем — возвращаем управление."); $msg = "$title.<br>Отправлено записей: <b>$totalCount</b><br>Статус доставки запрашивается при выполнении по расписанию."; return $msg; } else { $minutes = 240; $minDisplay = $minutes / 60; $minWord = $this->declOfNum($minDisplay, ['минуту', 'минуты', 'минут']); $this->log("    [sendData] Автоматический запуск. Ожидаем $minDisplay $minWord ({$minutes}с) перед запросом статуса пакета $pid."); sleep($minutes); self::DETAILED_LOG && $this->log("    [sendData] Ожидание завершено. Запрашиваем статус пакета $pid."); $this->getPackageStatus($pid); } break; case "Denied": $msg = "$title.<br>Запрос отклонен сервером!"; $this->log("    [sendData] Сервер отклонил пакет $pid (Denied).", 2); return $msg; default: $this->log("    [sendData] Неизвестный статус пакета: $pStatus", 1); break; } } else { $bodyPreview = mb_substr(iconv('windows-1251', 'UTF-8', $response["body"]), 0, 10000); $msg = "Ошибка отправки данных. Сервер вернул некорректный ответ: $bodyPreview"; $this->log("    [sendData] $msg", 2); return "$title.<br>$msg"; } break; default: $result = $result ?: mb_substr(iconv('windows-1251', 'UTF-8', $response["body"]), 0, 10000); $message = "$title.<br>Ошибка сервера: $result"; $this->log("    [sendData] Ошибка сервера. Статус: $status. Тело: $result", 2); return $message; } $message = "$title.<br>Отправлено записей: <b>$totalCount</b>"; self::DETAILED_LOG && $this->log("    [sendData] Завершено успешно. Отправлено записей: $totalCount"); return $message; } private function dumpSentData(array $records): void { $fileName = $this->task . '_sent_' . date('Y-m-d_H-i-s') . '.csv'; $oldFiles = getLogPath() . $this->task . '_sent_*.csv'; $filePath = getLogPath() . $fileName; foreach (glob($oldFiles) as $file) { unlink($file); } $lines = []; $lines[] = "\xEF\xBB\xBF" . implode(';', [ 'Код', 'Количество', 'Срок годности', 'Серия', 'Наименование', 'Централизованная закупка' ]); foreach ($records as $row) { $row = (array)$row; $expDate = ''; if (!empty($row['ExpirationDate'])) { $expDate = date('d.m.Y', (int)$row['ExpirationDate']); } $cp = !empty($row['CP']) ? 'Да' : 'Нет'; $lines[] = implode(';', [ $row['MedicationCode'] ?? '', $row['QuantityCounterparty'] ?? '', $expDate, $this->csvEscape($row['Series'] ?? ''), $this->csvEscape($row['Name'] ?? ''), $cp ]); } file_put_contents($filePath, implode("\r\n", $lines)); $this->log("    [dumpSentData] Итоговые данные для отправки сохранены: log/php/$fileName"); } private function csvEscape(string $value): string { if (strpbrk($value, ';"') !== false) { return '"' . str_replace('"', '""', $value) . '"'; } return $value; } public function getOrganizationId() { self::DETAILED_LOG && $this->log("    [getOrganizationId] Запрос идентификатора организации из БД."); $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)getSessionValue('mapEnterpriseCode'); self::DETAILED_LOG && $this->log("    [getOrganizationId] enterpriseId=$enterpriseId"); $result = selectSqlValue($db, "SELECT CodeHl7 FROM EnterpriseNumber WHERE EnterpriseCode = :enterpriseId LIMIT 1", array("enterpriseId" => $enterpriseId) ); self::DETAILED_LOG && $this->log("    [getOrganizationId] Получен CodeHl7: " . ($result ?? 'null')); return $result; } private function getPackageStatus(string $pid): void { $this->log("    [getPackageStatus] Запрос статуса обработки пакета $pid сервером."); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $this->log("    [getPackageStatus] Ошибка инициализации подключения.", 2); return; } $params->url = $this->buildUrl($params->url, "ControlledList"); $params->url = $params->url . "/$pid/status"; self::DETAILED_LOG && $this->log("    [getPackageStatus] URL запроса статуса: {$params->url}"); $this->log("    [getPackageStatus] Проверить статус пакета можно вручную в течение 30 дней, перейдя по ссылке {$params->url}"); $params->param = ["format" => "json"]; $params->debug = self::DEBUG_HTTP; $params->headers['Accept'] = 'version=3'; $this->log("    [getPackageStatus] Отправляем GET запрос статуса..."); $response = httpGET($params); if (!$response) { $this->log("    [getPackageStatus] Ошибка. Сервис недоступен.", 2); return; } $status = isEmptyArrayValue($response["status"], 1); $this->log("    [getPackageStatus] HTTP статус ответа: $status"); switch ($status) { case "200": $resp = $response["body"]; $res = safe_json_decode($resp); if ($res) { $pStatus = isUndefinedProperty($res, 'status'); $processedItems = isUndefinedProperty($res, 'processedCount'); $errorsNum = +isUndefinedProperty($res, 'errorsCount'); self::DETAILED_LOG && $this->log("    [getPackageStatus] Пакет $pid: статус=$pStatus, обработано=$processedItems, ошибок=$errorsNum."); if ($errorsNum > 0) { $this->log("    [getPackageStatus] Обнаружены ошибки ($errorsNum). Ждем 60с перед запросом отчета.", 1); sleep(60); $this->log("    [getPackageStatus] Запрашиваем отчет об ошибках пакета $pid."); $this->getPackageReport($pid); } else { $this->log("    [getPackageStatus] Ошибок в пакете $pid нет."); } } else { $bodyPreview = mb_substr($response["body"], 0, 100); $this->log("    [getPackageStatus] Не удалось декодировать ответ: $bodyPreview", 2); } break; default: $e = mb_substr($response["body"], 0, 100); $this->log("    [getPackageStatus] Ошибка сервера. Статус: $status. Тело: $e", 2); break; } } private function getPackageReport(string $pid): void { $this->log("    [getPackageReport] Запрос информации об ошибках в пакете $pid."); $settings = $this->getSettings(); if (!$params = httpCreateParam($settings)) { $this->log("    [getPackageReport] Ошибка инициализации подключения.", 2); return; } $params->url = $this->buildUrl($params->url, "ControlledList"); $params->url = $params->url . "/$pid/report"; $this->log("    [getPackageReport] URL запроса отчета: {$params->url}"); $this->log("    [getPackageReport] Получить отчет об ошибках пакета можно вручную в течение 30 дней, перейдя по ссылке {$params->url}"); $params->param = ["format" => "json"]; $params->debug = self::DEBUG_HTTP; $params->headers['Accept'] = 'version=3'; $this->log("    [getPackageReport] Отправляем GET запрос отчета..."); $response = httpGET($params); if (!$response) { $this->log("    [getPackageReport] Ошибка. Сервис недоступен.", 2); return; } $status = isEmptyArrayValue($response["status"], 1); $this->log("    [getPackageReport] HTTP статус ответа: $status"); switch ($status) { case "200": $resp = $response["body"]; $res = safe_json_decode($resp); if ($res) { $commonError = isUndefinedProperty($res, 'commonError'); $productErrors = stripslashes(isUndefinedProperty($res, 'productErrors')); $ceTxt = $commonError ? "commonError: $commonError. " : ''; $peTxt = $productErrors ? "productErrors: $productErrors." : ''; $this->log("    [getPackageReport] Информация об ошибках пакета $pid: {$ceTxt}{$peTxt}"); if (!$ceTxt && !$peTxt) { $this->log("    [getPackageReport] Отчет об ошибках не содержит описания."); } } else { $bodyPreview = mb_substr($response["body"], 0, 100); $this->log("    [getPackageReport] Не удалось декодировать ответ: $bodyPreview", 2); } break; default: $e = mb_substr($response["body"], 0, 100); $this->log("    [getPackageReport] Ошибка сервера. Статус: $status. Тело: $e", 2); break; } } private function declOfNum(int $number, array $titles): string { $cases = [2, 0, 1, 1, 1, 2]; $number = abs($number); return $titles[ ($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)] ]; } public function startExchange(?object $params = null): string { $task = isUndefinedProperty($params, 'task'); $this->initVars($task); $msg = ''; $this->log("============================================================================"); $this->log("[startExchange] Начинаем обмен данными v3. Время: " . date('Y-m-d H:i:s')); try { self::DETAILED_LOG && $this->log("[startExchange] Шаг 1/3: загрузка и проверка настроек..."); $this->getSettings(); self::DETAILED_LOG && $this->log("[startExchange] Настройки загружены."); self::DETAILED_LOG && $this->log("[startExchange] Шаг 2/3: загрузка справочника медикаментов..."); $this->getMedicationList(); self::DETAILED_LOG && $this->log("[startExchange] Справочник обработан."); self::DETAILED_LOG && $this->log("[startExchange] Шаг 3/3: отправка данных об остатках..."); $msg = $this->sendData($params); self::DETAILED_LOG && $this->log("[startExchange] Данные отправлены. Результат: $msg"); $this->log("[startExchange] Обмен данными v3 завершен. Время: " . date('Y-m-d H:i:s')); return $msg; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("[startExchange] КРИТИЧЕСКАЯ ОШИБКА: $message", 2); self::DETAILED_LOG && $this->log("[startExchange] Стек вызовов: " . $e->getTraceAsString(), 2); throw $e; } } public function updateBelpharmaCatalog(?object $params = null): string { $task = isUndefinedProperty($params, 'task'); $this->initVars($task); $msg = ''; $this->listName = $task === 'linkMedicationV3MinskPharma' ? 'Минской фармации' : 'Белфармации'; $this->log("============================================================================"); $this->log("[updateBelpharmaCatalog] Обновление справочника $this->listName..."); try { $this->getSettings(); $params = new stdClass(); $params->forceUpdate = true; $msg = $this->getMedicationList($params); $this->log("[updateBelpharmaCatalog] Обновление справочника $this->listName завершено"); return $msg; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("[updateBelpharmaCatalog] Произошла ошибка: $message", 2); throw $e; } } } handleJsonRpc('med_common_linkMedicationV3');