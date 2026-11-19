<?php
require_once(__DIR__ . '/../../init.php');

/**
 * Мониторинг отправок в ЦИСЗ в реальном времени.
 * Доступ — по разрешению 'med-cisz-monitor' (узел в дереве прав:
 * Администрирование → Медицина, рядом с «Связь с ЦИСЗ»).
 */
class med_cisz_monitor {

    private ?PDO_ANY $db = null;

    /**
     * Подключение к БД: из сессии (обычный вход в МИС) либо, если монитор
     * открыт в браузере без сессии МИС, — первая конфигурация с подключением
     * из cfg/config.json. Монитор только читает ciszHistory, поэтому фолбэк
     * не даёт доступа к чему-либо сверх разрешения med-cisz-monitor.
     */
    private function dbConnect(): PDO_ANY {
        if ($this->db) {
            return $this->db;
        }
        if (!getSessionValue('config')) {
            // Монитор открыт вне сессии МИС: подставляем настройки приложений
            // (webAssistUrl и т.п.), как это делает createSession при входе.
            if (!getSessionValue('settingsApp')) {
                setSessionValue('settingsApp', readJsonSettings('settings_app', false));
            }
            $ini = readConfigJson();
            foreach ($ini as $name => $cfg) {
                if (!empty($cfg['connect'])) {
                    return $this->db = connectDb($name);
                }
            }
            throw new Exception("Не найдена конфигурация с подключением к БД в cfg/config.json", -2);
        }
        return $this->db = connectDb();
    }

    /**
     * Список отправок (ActionType=1 «Регистрация») за день + итог по каждому
     * пользователю. Результат отправки берётся из последней записи
     * проверки статуса/отмены (ActionType 2/3) с тем же BundleId:
     *   1 - ожидает, 2 - в обработке, 3 - успех, 4 - ошибка, 5 - отменено
     * @param object|null $params {
     *      @param string $params->date Дата в формате YYYY-MM-DD. По умолчанию сегодня.
     *      @param int    $params->limit Максимум записей (по умолчанию 500).
     * }
     * @return object { items: [...], serverTime: 'YYYY-MM-DD HH:MM:SS' }
     */
    public function readActivity($params = null) {
        $db = $this->dbConnect();

        // только для авторизованных пользователей МИС
        if (!getSessionValue('user')) {
            throw new Exception("Требуется вход в систему", -401);
        }
        if (!testUserAccess('med-cisz-monitor', $db)) {
            throw new Exception("Нет доступа к мониторингу ЦИСЗ. Обратитесь к администратору.", -1);
        }

        $params = (object)($params ?: array()); // JSON-RPC passes params as array
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($params->date ?? '')) ? $params->date : date('Y-m-d');
        $limit = max(10, min(2000, (int)($params->limit ?? 500)));

        $rows = selectSql($db, <<<SQL
            SELECT
                ch.ciszHistoryCode                              AS "id",
                ch.RequestTime                                  AS "requestTime",
                ch.ResponseTime                                 AS "responseTime",
                ch.Status                                       AS "status",
                ch.UserCode                                     AS "userId",
                COALESCE(NULLIF(fn_cmFio(md.ManCode, 1), ''), wu.Nick, 'Код ' || ch.UserCode)
                                                                AS "userName",
                fn_cmFio(mp.ManCode)                            AS "patientName",
                ch.BundleId                                     AS "bundleId",
                (SELECT cs.Status
                 FROM ciszHistory cs
                 WHERE cs.BundleId = ch.BundleId
                   AND cs.ActionType IN (2, 3)
                   AND cs.Status IN (3, 4, 5)
                 ORDER BY cs.ciszHistoryCode DESC
                 LIMIT 1)                                       AS "result"
            FROM ciszHistory ch
            LEFT JOIN MedDoctor  md ON md.UserCode      = ch.UserCode
            LEFT JOIN WebUser    wu ON wu.UserCode      = ch.UserCode
            LEFT JOIN MedPatient mp ON mp.MedPatientCode = ch.MedPatientCode
            WHERE ch.ActionType = 1
              AND ch.RequestTime >= :dateFrom
              AND ch.RequestTime <  :dateTo
            ORDER BY ch.ciszHistoryCode DESC
            LIMIT $limit
        SQL, [
            ':dateFrom' => $date . ' 00:00:00',
            ':dateTo'   => date('Y-m-d H:i:s', strtotime($date . ' +1 day')),
        ]);

        return [
            'items'      => $rows,
            'serverTime' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Сводная таблица по пользователям за день: ФИО, дата, время первого
     * пакета, время последней активности, всего пакетов.
     * @param object|null $params { date: 'YYYY-MM-DD' }
     * @return array { items: [...], serverTime: '...' }
     */
    public function readSummary($params = null) {
        $db = $this->dbConnect();

        if (!getSessionValue('user')) {
            throw new Exception("Требуется вход в систему", -401);
        }
        if (!testUserAccess('med-cisz-monitor', $db)) {
            throw new Exception("Нет доступа к мониторингу ЦИСЗ. Обратитесь к администратору.", -1);
        }

        $params = (object)($params ?: array()); // JSON-RPC passes params as array
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($params->date ?? '')) ? $params->date : date('Y-m-d');

        $rows = selectSql($db, <<<SQL
            SELECT
                ch.UserCode                                             AS "userId",
                COALESCE(NULLIF(fn_cmFio(md.ManCode, 1), ''), wu.Nick, 'Код ' || ch.UserCode)
                                                                        AS "userName",
                :date::date                                             AS "date",
                to_char(MIN(ch.RequestTime), 'HH24:MI:SS')              AS "firstSend",
                to_char(MAX(COALESCE(ch.ResponseTime, ch.RequestTime)), 'HH24:MI:SS')
                                                                        AS "lastActivity",
                COUNT(*)                                                AS "total",
                (SELECT COUNT(*) FROM ciszHistory chm
                  WHERE chm.ActionType = 1
                    AND chm.UserCode = ch.UserCode
                    AND chm.RequestTime >= date_trunc('month', :date::date)
                    AND chm.RequestTime <  date_trunc('month', :date::date) + interval '1 month')
                                                                        AS "monthTotal"
            FROM ciszHistory ch
            LEFT JOIN MedDoctor md ON md.UserCode = ch.UserCode
            LEFT JOIN WebUser   wu ON wu.UserCode = ch.UserCode
            WHERE ch.ActionType = 1
              AND ch.RequestTime >= :dateFrom
              AND ch.RequestTime <  :dateTo
            GROUP BY ch.UserCode, 2
            ORDER BY 2
        SQL, [
            ':date'     => $date,
            ':dateFrom' => $date . ' 00:00:00',
            ':dateTo'   => date('Y-m-d H:i:s', strtotime($date . ' +1 day')),
        ]);

        // «Отправлено сегодня» — всего пакетов за фактический сегодняшний день
        // (независимо от выбранной даты в фильтре)
        $todayTotal = (int)selectSql($db, <<<SQL
            SELECT COUNT(*)
            FROM ciszHistory
            WHERE ActionType = 1
              AND RequestTime >= current_date
              AND RequestTime <  current_date + interval '1 day'
        SQL, [])[0]['count'];

        // ленивое создание дневного HTML-отчёта: после 20:00, если ещё не создан
        if ((int)date('G') >= 20) {
            try {
                $this->saveDailyReport((object)['date' => date('Y-m-d')]);
            } catch (Exception $e) {
                // не мешаем ответу монитора из-за ошибки записи отчёта
            }
        }

        return [
            'items'      => $rows,
            'todayTotal' => $todayTotal,
            'serverTime' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Проверка доступа и данные текущего пользователя для шапки монитора.
     * @return object { allowed: bool, userName: string, isAdmin: bool }
     */
    public function whoAmI() {
        $db = $this->dbConnect();
        // без авторизации в МИС доступа нет
        if (!getSessionValue('user')) {
            return [
                'allowed'  => false,
                'userName' => '',
                'isAdmin'  => false,
                'needLogin' => true
            ];
        }
        $allowed = testUserAccess('med-cisz-monitor', $db);
        return [
            'allowed'  => $allowed,
            'userName' => (string) getSessionValue('userName'),
            'isAdmin'  => (bool) getSessionValue('su', false),
        ];
    }

    /**
     * Отчёт об активных пользователях: кто сейчас подключён к МИС,
     * время входа, АРМ (IP/имя компьютера), программа (браузер/ОС).
     * Источник: Redis-сессии MapWebServer (sess:<id> -> $_SESSION).
     * @param object|null $params { minutes: int } — считать активными сессии
     *   с активностью за последние N минут (по умолчанию 30; 0 — все за сутки)
     * @return array { items: [...], serverTime: '...' }
     */
    public function readActiveUsers($params = null) {
        $db = $this->dbConnect();

        if (!getSessionValue('user')) {
            throw new Exception("Требуется вход в систему", -401);
        }
        if (!testUserAccess('med-cisz-monitor', $db)) {
            throw new Exception("Нет доступа к мониторингу ЦИСЗ. Обратитесь к администратору.", -1);
        }

        $params = (object)($params ?: array()); // JSON-RPC passes params as array
        $minutes = max(0, min(1440, (int)($params->minutes ?? 30)));
        $threshold = $minutes > 0 ? date('Y-m-d H:i:s', time() - $minutes * 60) : null;

        // ФИО пользователей из БД (для полноты, если сессия хранит только Nick)
        $fio = [];
        foreach (selectSql($db, "SELECT WebUserCode, Nick, Fio, LastLogonTime FROM WebUser", null) as $u) {
            $fio[(int)$u['webusercode']] = $u;
        }

        // Имена компьютеров: обратный DNS в этой сети не работает (каждый запрос
        // блокируется ~15 сек и не возвращает имя). Имя берётся из карты IP→АРМ,
        // которую администратор может вести вручную (Redis-ключ iphostmap).
        $ipHosts = Cache::get('iphostmap');
        if (!is_array($ipHosts)) $ipHosts = [];

        $items = [];
        $sessions = Cache::getSessions(); // [id => accessTime]
        foreach ($sessions as $id => $accessTime) {
            $data = Cache::get(Cache::sessionKey((string)$id));
            if (!is_array($data) || empty($data['user'])) continue; // только вошедшие
            if ($threshold !== null && (string)$accessTime < $threshold) continue; // неактивные

            $userCode = (int)$data['user'];
            $ip = (string)($data['REMOTE_ADDR'] ?? '');
            $agent = (string)($data['HTTP_USER_AGENT'] ?? '');

            $computer = ($ip !== '' && array_key_exists($ip, $ipHosts)) ? (string)$ipHosts[$ip] : '';

            $items[] = [
                'userId'       => $userCode,
                'userName'     => (string)($data['userName'] ?? ($fio[$userCode]['nick'] ?? ('Код ' . $userCode))),
                'fio'          => (string)($fio[$userCode]['fio'] ?? ''),
                'loginTime'    => (string)($fio[$userCode]['lastlogontime'] ?? ''),
                'accessTime'   => (string)$accessTime,
                'ip'           => $ip,
                'computer'     => $computer,
                'agent'        => $agent,
                'config'       => (string)($data['config'] ?? (($data['appConfig']['config'] ?? '') ?: '')),
                'isCurrent'    => $userCode === (int)getSessionValue('user')
                                  && $ip === (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            ];
        }

        // сортировка: по имени пользователя, затем по активности
        usort($items, function ($a, $b) {
            return strcasecmp($a['userName'], $b['userName'])
                ?: strcmp($b['accessTime'], $a['accessTime']);
        });

        return [
            'items'      => $items,
            'serverTime' => date('Y-m-d H:i:s'),
        ];
    }

    /** Разбор User-Agent в человекочитаемую строку «Браузер X • ОС Y» */
    public static function describeAgent(string $agent): string {
        $browser = 'Неизвестно';
        foreach ([
            'YaBrowser'    => 'Яндекс.Браузер',
            'Edg/'         => 'Edge',
            'OPR/'         => 'Opera',
            'Chrome'       => 'Chrome',
            'Firefox'      => 'Firefox',
            'Safari'       => 'Safari',
            'MSIE'         => 'Internet Explorer',
            'Trident/'     => 'Internet Explorer',
        ] as $key => $name) {
            if (stripos($agent, $key) !== false) { $browser = $name; break; }
        }
        $os = 'Неизвестно';
        foreach ([
            'Windows NT 10.0' => 'Windows 10/11',
            'Windows NT 6.3'  => 'Windows 8.1',
            'Windows NT 6.1'  => 'Windows 7',
            'Windows'         => 'Windows',
            'Android'         => 'Android',
            'iPhone|iPad'     => 'iOS',
            'Linux'           => 'Linux',
            'Mac OS'          => 'macOS',
        ] as $key => $name) {
            foreach (explode('|', $key) as $k) {
                if (stripos($agent, $k) !== false) { $os = $name; break 2; }
            }
        }
        return $browser . ' • ' . $os;
    }

    /**
     * HTML-отчёт «Отправки в ЦИСЗ» за дату. Вызывается планировщиком (20:00)
     * или вручную из CLI. Не требует веб-сессии, доступ не проверяет —
     * вызывается на сервере локально.
     * @param object|null $params { date: 'YYYY-MM-DD' } — по умолчанию сегодня
     * @return array { file: string, rows: int } — путь к файлу и число строк
     */
    public function saveDailyReport($params = null) {
        $params = (object)($params ?: array()); // JSON-RPC passes params as array
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($params->date ?? '')) ? $params->date : date('Y-m-d');

        // сводная выборка без проверки сессии: через внутренний вызов SQL напрямую
        $db = $this->dbConnect();
        if (!getSessionValue('settingsApp')) {
            setSessionValue('settingsApp', readJsonSettings('settings_app', false));
        }

        $rows = selectSql($db, <<<SQL
            SELECT
                COALESCE(NULLIF(fn_cmFio(md.ManCode, 1), ''), wu.Nick, 'Код ' || ch.UserCode)
                                                                        AS "userName",
                to_char(MIN(ch.RequestTime), 'HH24:MI:SS')              AS "firstSend",
                to_char(MAX(COALESCE(ch.ResponseTime, ch.RequestTime)), 'HH24:MI:SS')
                                                                        AS "lastActivity",
                COUNT(*)                                                AS "total",
                (SELECT COUNT(*) FROM ciszHistory chm
                  WHERE chm.ActionType = 1
                    AND chm.UserCode = ch.UserCode
                    AND chm.RequestTime >= date_trunc('month', :date::date)
                    AND chm.RequestTime <  date_trunc('month', :date::date) + interval '1 month')
                                                                        AS "monthTotal"
            FROM ciszHistory ch
            LEFT JOIN MedDoctor md ON md.UserCode = ch.UserCode
            LEFT JOIN WebUser   wu ON wu.UserCode = ch.UserCode
            WHERE ch.ActionType = 1
              AND ch.RequestTime >= :dateFrom
              AND ch.RequestTime <  :dateTo
            GROUP BY ch.UserCode, 1
            ORDER BY 1
        SQL, [
            ':date'     => $date,
            ':dateFrom' => $date . ' 00:00:00',
            ':dateTo'   => date('Y-m-d H:i:s', strtotime($date . ' +1 day')),
        ]);

        $totalPacks = 0;
        foreach ($rows as $r) $totalPacks += (int)$r['total'];

        $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
        $tr = '';
        foreach ($rows as $r) {
            $tr .= '<tr>'
                . '<td>' . $esc($r['userName']) . '</td>'
                . '<td class="c">' . $esc($date) . '</td>'
                . '<td class="c">' . $esc($r['firstSend']) . '</td>'
                . '<td class="c">' . $esc($r['lastActivity']) . '</td>'
                . '<td class="c">' . (int)$r['total'] . '</td>'
                . '<td class="c">' . (int)$r['monthTotal'] . '</td>'
                . '</tr>';
        }
        if (!$rows) {
            $tr = '<tr><td colspan="6" class="c">Отправок в этот день не было</td></tr>';
        }

        $genTime = date('d.m.Y H:i:s');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Отправки в ЦИСЗ — $date</title>
<style>
body { font: 14px/1.5 "Segoe UI", system-ui, sans-serif; color: #16243a; margin: 24px; }
h1 { font-size: 20px; color: #0b2545; }
.meta { color: #476b8f; font-size: 13px; margin-bottom: 16px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #dde8f4; padding: 7px 12px; }
th { background: #eef4fa; text-align: left; font-size: 12.5px; text-transform: uppercase; letter-spacing: .04em; color: #476b8f; }
td.c { text-align: center; font-variant-numeric: tabular-nums; }
tr:nth-child(even) td { background: #f8fbff; }
.total { margin-top: 14px; font-weight: 700; }
</style>
</head>
<body>
<h1>Отправки в ЦИСЗ — отчёт за $date</h1>
<div class="meta">Сформирован автоматически: $genTime · Пользователей: {$this->cnt(count($rows))} · Пакетов всего: $totalPacks</div>
<table>
<thead><tr><th>Пользователь</th><th>Дата</th><th>Первый пакет</th><th>Обновлено</th><th>Всего пакетов</th><th>Всего с начала месяца</th></tr></thead>
<tbody>$tr</tbody>
</table>
<div class="total">Итого пакетов за день: $totalPacks</div>
</body>
</html>
HTML;

        // каталог отчётов: \Mapsoft\Rep_CISZ на сервере; при запуске на локальной
        // машине — соответствующий сетевой путь
        $dir = '//192.168.1.200/Mapsoft/Rep_CISZ';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) || !is_dir($dir)) {
                // запасной вариант: рядом с корнем сервера
                $alt = getServerRootPath() . 'log/Rep_CISZ';
                if (!is_dir($alt)) @mkdir($alt, 0777, true);
                $dir = $alt;
            }
        }
        $file = $dir . '/CISZ_' . str_replace('-', '', $date) . '.html';
        $written = file_put_contents($file, $html);
        if ($written === false) {
            throw new Exception("Не удалось записать отчёт: $file", -1);
        }
        return ['file' => $file, 'rows' => count($rows), 'bytes' => $written];
    }

    /** Склонение слова «пользователь» */
    private function cnt($n) {
        $m10 = $n % 10; $m100 = $n % 100;
        if ($m10 == 1 && $m100 != 11) return $n . ' пользователь';
        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return $n . ' пользователя';
        return $n . ' пользователей';
    }
}

handleJsonRpc('med_cisz_monitor');
