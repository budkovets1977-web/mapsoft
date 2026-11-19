<?php
require_once(__DIR__ . '/../../init.php'); class med_common_linkMols { const DEBUG_HTTP = true; const TASK_NAME = "linkMols"; const LOG_INFO = 0; const LOG_WARNING = 1; const LOG_ERROR = 2; private $db; private $extraSettings; private function log($message, $type = 0){ app_log($message, $type, self::TASK_NAME); } private function getSettings(){ if (!empty($this->extraSettings)){ return $this->extraSettings; } $this->log('    Загрузка настроек...'); $settings = readExtraSettings(getSessionValue('config'), self::TASK_NAME); return $this->checkSettings($settings); } private function checkSettings($settings){ $settingsValid = true; if (empty($settings)){ $this->log('    Не произведена настройка обмена информацией', self::LOG_ERROR); } elseif (isset($settings->active) && $settings->active){ if (empty($settings->url)){ $this->log('    Не заполнена настройка "Адрес сервера API"', self::LOG_ERROR); $settingsValid = false; } if (empty($settings->organizationId)){ $this->log('    Не заполнена настройка "Идентификатор организации"', self::LOG_WARNING); } if (empty($settings->enterpriseCode)){ $this->log('    Не заполнена настройка "Код организации в БД"', self::LOG_WARNING); } if ($settingsValid){ $settings->task = self::TASK_NAME; if (parse_url($settings->url, PHP_URL_PATH) !== "/"){ $settings->url .= "/"; } $this->log('    Настройки загружены успешно'); $this->extraSettings = $settings; $result = $this->extraSettings; } else { throw new Exception('    Проверьте правильность заполнения данных для подключения.'); } } else { $this->log('    В настройках запрещен обмен данными', self::LOG_WARNING); throw new Exception('В настройках запрещен обмен данными'); } return $result; } public function startExchange(){ $this->log("Запуск startExchange()..."); try { $this->getSettings(); $message = $this->proccessMolExchange(); $this->log("Работа startExchange() завершена"); return $message; } catch (Throwable $e){ $message = $e->getMessage(); $this->log("Произошла ошибка: $message", self::LOG_ERROR); throw $e; } } public function proccessMolExchange() { $db = $this->db ?? $this->db = connectDb(); $enterpriseId = (int)(getSessionValue("mapEnterpriseCode") ?? isUndefinedProperty($this->extraSettings, 'enterpriseCode')); $settings = $this->getSettings(); $title = "Передача сведений об остатках перечня медикаментов в ЦОД"; $this->log("    $title (proccessMolExchange)..."); $sql = <<<SQL
            SELECT
                ms.name                     "IMNNAME",
                mwo.name                    "TORG",
                mwo.secondName              "FGRLEVEL1",
                mw.fullNameExternal         "IMN",
                u1.name                     "FGRLEVEL2",
                u2.name                     "FGRLEVEL3",
                u3.name                     "FGRLEVEL4",
                mw.dosageForm               "LEKFORM",
                mw.substance                "KOLDV1",
                mwm1.name                   "EDIZM1",
                mw.doseSize                 "KOLPR",
                mwm2.name                   "EDIZMPR",
                mwm3.name                   "VIDUP",
                u4.name                     "IMF",
                mw.itemNumber               "KPR",
                mw.linkCipher               "KODN",
                DATEFORMAT(SR.ValidTerm, 'MMYY')
                                            "DI",
                SR.Party                    "SPAR",
                mw.doseNumber               "KOLUP",
                SUM(COALESCE(scd.amountD, 0.0) - COALESCE(scd.amountK, 0.0))
                                            "CKOL",
                SUM(scd.amountD)            "KOL"
            FROM sklCard sc
            LEFT JOIN sklCardDate scd ON scd.sklCardCode = sc.sklCardCode
                AND scd.onDate = (
                    SELECT MAX(OnDate)
                    FROM sklCardDate
                    WHERE sklCardCode = sc.sklCardCode
                        AND onDate <= 'NOW'::DATE
                )
            LEFT JOIN SklRow SR ON SR.SklRowCode = SC.SklRowCode
            LEFT JOIN matWealth mw ON mw.matWealthCode = sc.matWealthCode
            LEFT JOIN matWealthObject mwo ON mwo.matWealthObjectCode = mw.matWealthObjectCode
            LEFT JOIN matSource ms ON ms.matSourceCode = sc.matSourceCode
            LEFT JOIN uni u1 ON u1.uniCode = mw.uniCodeGroupFarm2 AND u1.dict = 9 AND u1.enterpriseCode = :enterpriseId
            LEFT JOIN uni u2 ON u2.uniCode = mw.uniCodeGroupFarm3 AND u2.dict = 10 AND u2.enterpriseCode = :enterpriseId
            LEFT JOIN uni u3 ON u3.uniCode = mw.uniCodeGroupFarm AND u3.dict = 2 AND u3.enterpriseCode = :enterpriseId
            LEFT JOIN uni u4 ON u4.uniCode = mw.uniCodeManufactuter AND u4.dict = 11 AND u4.enterpriseCode = :enterpriseId
            LEFT JOIN matWealthMeasure mwm1 ON mwm1.matWealthMeasureCode = mw.matWealthMeasureCodeSubstance
            LEFT JOIN matWealthMeasure mwm2 ON mwm2.matWealthMeasureCode = mw.matWealthMeasureDoseSizeCode
            LEFT JOIN matWealthMeasure mwm3 ON mwm3.matWealthMeasureCode = mw.matWealthMeasureExtCode
            WHERE sc.enterpriseCode = :enterpriseId
                    AND (ms.enterpriseCode = :enterpriseId or ms.enterpriseCode is null)
                    AND COALESCE(scd.amountD, 0.0) - COALESCE(scd.amountK, 0.0) != 0.0
                    AND NULLIF(mw.linkCipher, '') != null
            GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19
            SQL;
$total_count = 0; try { $records = selectSql($db, $sql, array("enterpriseId" => $enterpriseId), PDO::FETCH_OBJ); if (!empty($records)){ $total_count = count($records); } } catch (Throwable $e){ $message = $e->getMessage(); $this->log("    Ошибка: $message", self::LOG_ERROR); throw $e; } if (!$params = httpCreateParam($settings)){ $message = "<div>$title.<br>Ошибка инициализации подключения.</div>"; $this->log("    Ошибка инициализации подключения", self::LOG_ERROR); return $message; } $params->debug = self::DEBUG_HTTP; $params->request_content = json_encode($records, JSON_UNESCAPED_UNICODE); $params->param = array('guid' => $settings->organizationId); $response = httpPOST($params); if (!$response){ $message = "<div>$title.<br>Ошибка подключения. Указан неверный URL, либо сервис недоступен.</div>"; $this->log("    Ошибка подключения. Указан неверный URL, либо сервис недоступен.", self::LOG_ERROR); return $message; } $result = null; if (!isNullArrayValue($response["status"], self::LOG_WARNING)){ $result = "пустой ответ"; } switch (isEmptyArrayValue($response["status"], self::LOG_WARNING)){ case "200": break; default: $result = $result ?? $response["body"]; $message = "<div>$title.<br>Ошибка сервера: $result</div>"; $this->log("    Ошибка сервера: $result", self::LOG_ERROR); return $message; } $message = "<div>$title.<br>Отправлено записей: <b>$total_count</div>"; $this->log("    Успешно. Всего отправлено записей: $total_count."); return $message; } } handleJsonRpc('med_common_linkMols');