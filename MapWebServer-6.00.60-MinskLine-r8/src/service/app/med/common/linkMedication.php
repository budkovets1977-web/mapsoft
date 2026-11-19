<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_linkMedication { const DEBUG_HTTP = false; const TASK_NAME = "linkMedication"; private $extraSettings; private $db; private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } private function getSettings() { if (!empty($this->extraSettings)) { return $this->extraSettings; } $this->log(' Загрузка настроек...'); $settings = readExtraSettings(getSessionValue('config'), self::TASK_NAME); return $this->checkSettings($settings); } private function checkSettings($settings) { $settingsValid = true; if (empty($settings)) { $this->log(' Не произведена настройка обмена информацией', 2); } elseif (isset($settings->active) && $settings->active) { if (empty($settings->url)) { $this->log(' Не заполнена настройка "Адрес сервера API"', 2); $settingsValid = false; } if (empty($settings->serverName)) { $this->log(' Не заполнена настройка "Имя сервера"', 2); $settingsValid = false; } if (empty($settings->serviceName)) { $this->log(' Не заполнена настройка "Сервис"', 2); $settingsValid = false; } if (empty($settings->serviceRootUrl)) { $this->log(' Не заполнена настройка "Корневой URL сервиса"', 2); $settingsValid = false; } if (empty($settings->organizationId)) { $this->log(' Не заполнена настройка "Идентификатор организации"', 1); } if (empty($settings->enterpriseCode)) { $this->log(' Не заполнена настройка "Код организации в БД"', 1); } if ($settingsValid) { $settings->task = self::TASK_NAME; if (parse_url($settings->url, PHP_URL_PATH) !== "/") { $settings->url .= "/"; } $settings->url .= $settings->serverName; $settings->url = glueStrings($settings->url, $settings->serviceName, "/"); $settings->url = glueStrings($settings->url, $settings->serviceRootUrl, "/"); $this->log(' Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } else { throw new Exception(' Проверьте правильность заполнения данных для подключения.'); } } else { $this->log(' В настройках запрещен обмен данными', 1); throw new Exception('В настройках запрещен обмен данными'); } return $result; } private function buildUrl($url, $resource) { $settings = $this->getSettings(); return glueStrings( glueStrings($url, $resource, "/"), $settings->organizationId, "/" ); } public function medicationList() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)(getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); $settings = $this->getSettings(); $title = "Синхронизация справочника товаров контролируемого перечня"; $this->log(" $title (medicationList)..."); if (!$params = httpCreateParam($settings)) { $message = "<ol start=1><li>$title.<br>Ошибка инициализации подключения.</li></ol>"; $this->log(" Ошибка инициализации подключения", 2); return $message; } $params->url = $this->buildUrl($params->url, "MedicationList"); $params->param = array( "format" => "json" ); $params->debug = self::DEBUG_HTTP; $response = httpGET($params); if (!$response) { $message = "<ol start=1><li>$title.<br>Ошибка подключения. Указан неверный URL, либо сервис недоступен.</li></ol>"; $this->log(" Ошибка подключения. Указан неверный URL, либо сервис недоступен.", 2); return $message; } $result = null; if (!isNullArrayValue($response["status"], 1)) { $result = "пустой ответ"; } $wealths = null; switch (isEmptyArrayValue($response["status"], 1)) { case "200": $wealths = safe_json_decode($response["body"]); break; default: $result = $result ?: $response["body"]; $message = "<ol start=1><li>$title.<br>Ошибка: $result</li></ol>"; $this->log(" Ошибка: $result", 2); return $message; } $total_count = 0; $cnt_update = 0; $cnt_insert = 0; $cnt_delete = 0; $db->beginTransaction(); try { $guids = createSqlInCondition($wealths, "Guid"); $sql = <<<"EOT"
with t as (
    delete from webbelpharmawealthcheck
    where enterprisecode = :enterpriseId
        and guid not $guids
    returning *
)
select count(*) from t
EOT;
$cnt_delete = selectSqlValue($db, $sql, array("enterpriseId" => $enterpriseId)); foreach ($wealths as $w) { $sql = <<<"EOT"
SELECT webbelpharmawealthcheckCode
FROM webbelpharmawealthcheck
WHERE enterprisecode = :enterpriseId
    AND guid = :guid
LIMIT 1
EOT;
$id = (int)selectSqlValue($db, $sql, array( "enterpriseId" => $enterpriseId, "guid" => $w->Guid )); if ($id > 0) { $sql = <<<"EOT"
UPDATE webbelpharmawealthcheck SET
    enterprisecode = :enterpriseId,
    name = :name,
    linkcipher = :cipher,
    linkcipherttn = :cipherttn
WHERE webbelpharmawealthcheckCode = :id
EOT;
executeSql($db, $sql, array( "enterpriseId" => $enterpriseId, "name" => $w->Name, "cipher" => $w->Code, "cipherttn" => isUndefinedProperty($w, 'ExtraCode'), "id" => $id )); $cnt_update++; } else { $sql = <<<"EOT"
INSERT INTO webbelpharmawealthcheck (enterprisecode, name, linkcipher, guid, linkcipherttn)
VALUES (:enterpriseId, :name, :cipher, :guid, :cipherttn)
EOT;
executeSql($db, $sql, array( "enterpriseId" => $enterpriseId, "name" => $w->Name, "cipher" => $w->Code, "guid" => $w->Guid, "cipherttn" => isUndefinedProperty($w, 'ExtraCode') )); $cnt_insert++; } $total_count++; } $db->commit(); $message = "<ol start=1><li>$title.<br>Синхронизировано <b>$total_count</b> записей:<br>" . "<ul><li>добавлено <b>$cnt_insert</b>;</li>" . "<li>обновлено <b>$cnt_update</b>;</li>" . "<li>удалено <b>$cnt_delete</b>.</li></ul></li></ol>"; $this->log(" Успешно. Всего $total_count записей: $cnt_insert (добавлено); $cnt_update (обновлено); $cnt_delete (удалено)"); return $message; } catch (Throwable $e) { $db->rollback(); $message = $e->getMessage(); $this->log(" Ошибка: $message", 2); throw $e; } } public function controlledList() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)(getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); $settings = $this->getSettings(); $title = "Передача сведений об остатках контролируемого перечня медикаментов"; $this->log(" $title (ControlledList)..."); $esi = createSqlInCondition(json_clean_decode($settings->usedOffices)); $sources = createSqlInCondition(json_clean_decode($settings->usedMatSources)); $sections = createSqlInCondition(json_clean_decode($settings->usedSections)); $sql = <<<SQL
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
                    M.LinkCipher::BIGINT    LinkCipher, -- Номенклатурный код
                    (COALESCE(MCD.AmountD, 0.0) - COALESCE(MCD.AmountK, 0.0))/M.DoseNumber
                                            QuantityCounterparty, -- Переводим в упаковки
                    SR.Party                Series,
                    SR.ValidTerm            ExpirationDate,
                    MC.BuhMolCode           BuhMolCode
                FROM MedCalc MC
                JOIN MedCalcDate MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                LEFT JOIN SklRow SR ON SR.SklRowCode = MC.SklRowCode
                    AND SR.SklRowCode > 0
                    AND SR.MatSourceCode $sources
                LEFT JOIN MatWealth M ON M.MatWealthCode = SR.MatWealthCodeD -- Подключаем данные партии прихода
                LEFT JOIN BuhMol BM ON BM.BuhMolCode = MC.BuhMolCode
                JOIN WorkerInfo WI ON WI.WorkerCode = BM.WorkerCode
                    AND WI.EnterpriseStructureCode $esi
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
                --
                UNION ALL
                --
                -- Запрос по МОЛам.
                -- Номенклатурный код у предмета есть. Берем как есть. Ничего не конвертируем.
                SELECT
                    MW.LinkCipher::BIGINT   LinkCipher, -- Номенклатурный код
                    COALESCE(MCD.AmountD, 0) - COALESCE(MCD.AmountK, 0)
                                            QuantityCounterparty,
                    SR.Party                Series,
                    SR.ValidTerm            ExpirationDate,
                    MC.BuhMolCode           BuhMolCode
                FROM MedCalc MC
                JOIN MedCalcDate MCD ON MCD.MedCalcCode = MC.MedCalcCode
                JOIN MatWealth MW ON MW.MatWealthCode = MC.MatWealthCode
                JOIN MatWealthObject MWO ON MWO.MatWealthObjectCode = MW.MatWealthObjectCode
                    AND MWO.MatWealthSectionCode $sections
                LEFT JOIN SklRow SR ON SR.SklRowCode = MC.SklRowCode
                    AND SR.SklRowCode > 0
                    AND SR.MatSourceCode $sources
                LEFT JOIN BuhMol BM ON BM.BuhMolCode = MC.BuhMolCode
                JOIN WorkerInfo WI ON WI.WorkerCode = BM.WorkerCode
                    AND WI.EnterpriseStructureCode $esi
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
            ),
            TMolsExclude AS ( -- Выбираем МОЛов из запроса выше для исключения их в дальнейшем
                SELECT BM.WorkerCode
                FROM TBalancesFromMedCalc BMC
                LEFT JOIN BuhMol BM ON BM.BuhMolCode = BMC.BuhMolCode
                    AND BM.WorkerCode IS NOT NULL
            ),
            TBalancesFromSklCard AS ( -- Остатки по Аптеке(склад). Таблица SklCard.
                -- Выбираем предметы у которых нет номенклатурного кода и ищем по партии поставки
                -- упаковки, дозировку и номенклатурный код в поставке. Переводим количество в упаковки.
                SELECT
                    M.LinkCipher::BIGINT    LinkCipher, -- Номенклатурный код по партии
                    (COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0))/M.DoseNumber
                                            QuantityCounterparty, -- Переводим в упаковки
                    SR.Party                Series,
                    SR.ValidTerm            ExpirationDate
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
                    C.LinkCipher::BIGINT    LinkCipher,
                    COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0)
                                            QuantityCounterparty,
                    SR.Party                Series,
                    SR.ValidTerm            ExpirationDate
                FROM WebBelpharmaWealthCheck C
                JOIN MatWealth W ON W.LinkCipher = C.LinkCipher
                    OR W.LinkCipher = C.LinkCipherTTN
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
                WHERE C.EnterpriseCode = :enterpriseId
                    AND COALESCE(SCD.AmountD, 0.0) - COALESCE(SCD.AmountK, 0.0) > 0
                    AND COALESCE(W.LinkCipher::BIGINT, 0) != 0
            ),
            TBalances AS ( -- Объединяем запросы, получаем общие остатки по МОЛам и Аптеке(складу)
                SELECT
                    C.LinkCipher::BIGINT, -- Использовать обязательно 5-тизначный код из таблицы WebBelpharmaWealthCheck
                    BMC.QuantityCounterparty,
                    BMC.Series,
                    BMC.ExpirationDate
                FROM WebBelpharmaWealthCheck C
                JOIN TBalancesFromMedCalc BMC ON BMC.LinkCipher = C.LinkCipher
                    OR BMC.LinkCipher = C.LinkCipherTTN
                WHERE C.EnterpriseCode = :enterpriseId
                --
                UNION ALL
                --
                SELECT
                    C.LinkCipher::BIGINT, -- Использовать обязательно 5-тизначный код из таблицы WebBelpharmaWealthCheck
                    BSC.QuantityCounterparty,
                    BSC.Series,
                    BSC.ExpirationDate
                FROM WebBelpharmaWealthCheck C
                JOIN TBalancesFromSklCard BSC ON BSC.LinkCipher = C.LinkCipher
                    OR BSC.LinkCipher = C.LinkCipherTTN
                WHERE C.EnterpriseCode = :enterpriseId
            )
            -- Финальная выборка данных
            SELECT
                LinkCipher::BIGINT          "MedicationCode",
                SUM(QuantityCounterparty)   "QuantityCounterparty",
                Series                      "Series",
                ExpirationDate              "ExpirationDate"
            FROM TBalances
            GROUP BY
                LinkCipher::BIGINT,
                Series, -- Группировка только по серии и дате срока годности
                ExpirationDate
            ORDER BY
                LinkCipher::BIGINT,
                Series,
                ExpirationDate
            SQL;
$total_count = 0; try { $records = selectSql($db, $sql, array("enterpriseId" => $enterpriseId), PDO::FETCH_OBJ); if (!empty($records)) { $total_count = count($records); convertSqlParams($records, ["number" => "QuantityCounterparty"]); foreach ($records as $r) { $r->QuantityCounterparty = (float) number_format($r->QuantityCounterparty, 2, '.', ''); if (!empty($r->ExpirationDate)) { $dt = date_create($r->ExpirationDate, new DateTimeZone('UTC')); $r->ExpirationDate = $dt ? $dt->getTimestamp() : null; } else { $r->ExpirationDate = null; } } } } catch (Throwable $e) { $message = $e->getMessage(); $this->log(" Ошибка: $message", 2); throw $e; } if (!$params = httpCreateParam($settings)) { $message = "<ol start=2><li>$title.<br>Ошибка инициализации подключения.</li></ol>"; $this->log(" Ошибка инициализации подключения", 2); return $message; } $params->url = $this->buildUrl($params->url, "ControlledList"); $params->param = array( "CompleteDataDeletion" => true ); $params->debug = self::DEBUG_HTTP; $params->request_content = json_encode($records, JSON_UNESCAPED_UNICODE); $params->headers['Accept'] = 'version=2'; $this->log("Отправляемые данные: " . $params->request_content); $response = httpPOST($params); if (!$response) { $message = "<ol start=2><li>$title.<br>Ошибка подключения. Указан неверный URL, либо сервис недоступен.</li></ol>"; $this->log(" Ошибка подключения. Указан неверный URL, либо сервис недоступен.", 2); return $message; } $result = null; if (!isNullArrayValue($response["status"], 1)) { $result = "пустой ответ"; } switch (isEmptyArrayValue($response["status"], 1)) { case "200": break; default: $result = $result ?: $response["body"]; $message = "<ol start=2><li>$title.<br>Ошибка сервера: $result</li></ol>"; $this->log(" Ошибка сервера: $result", 2); return $message; } $message = "<ol start=2><li>$title.<br>Отправлено записей: <b>$total_count</b>.</ol>"; $this->log(" Успешно. Всего отправлено записей: $total_count."); return $message; } public function startExchange() { $this->log("Запуск startExchange()..."); try { $this->getSettings(); $message1 = $this->medicationList(); $message2 = $this->controlledList(); $this->log("Работа startExchange() завершена"); return "$message1 $message2"; } catch (Throwable $e) { $message = $e->getMessage(); $this->log("Произошла ошибка: $message", 2); throw $e; } } public function getOrganizationId() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int)getSessionValue('mapEnterpriseCode'); return selectSqlValue($db, "SELECT codehl7 FROM enterprisenumber WHERE enterprisecode = :enterpriseId LIMIT 1", array("enterpriseId" => $enterpriseId) ); } } handleJsonRpc('med_common_linkMedication');