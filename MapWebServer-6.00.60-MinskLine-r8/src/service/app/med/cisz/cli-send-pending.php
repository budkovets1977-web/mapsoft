<?php
/**
 * CLI-скрипт потоковой отправки неотправленных дневников (посещений + направлений) за день в ЦИСЗ.
 *
 * НЕ изменяет вендорский код — использует штатный JSON-RPC метод:
 *   med_cisz_bundle.createBundleAndImport({ entity, visitPatientId, patientId })
 *
 * Запуск (PHP CLI на сервере, из каталога src/service):
 *   php.cmd -f app/med/cisz/cli-send-pending.php <config> sendPending date=YYYY-MM-DD [entity=all|MedVisitPatient|MedLogSend] [limit=N] [dry=1]
 *
 * Примеры:
 *   # только посмотреть, что будет отправлено (без реальной отправки):
 *   php.cmd -f app/med/cisz/cli-send-pending.php mis sendPending date=2026-11-11 entity=all dry=1
 *
 *   # отправить посещения за день:
 *   php.cmd -f app/med/cisz/cli-send-pending.php mis sendPending date=2026-11-11 entity=MedVisitPatient
 *
 *   # отправить направления за день:
 *   php.cmd -f app/med/cisz/cli-send-pending.php mis sendPending date=2026-11-11 entity=MedLogSend
 *
 *   # всё за день, по 50 за раз:
 *   php.cmd -f app/med/cisz/cli-send-pending.php mis sendPending date=2026-11-11 entity=all limit=50
 *
 * Механика бутстрапа CLI (автоматически через init.php -> util/session.php):
 *   handleConsoleCommands() кладёт $argv в $_REQUEST (config=argv[1], norpc=argv[2], argscmd=остальное);
 *   initSession() создаёт новую сессию (Redis), Cache::sessionWrite() пишет её.
 *
 * Авторизация (полная PKCE, как в браузере):
 *   med_cisz_api.checkAuthAndGetUrl() -> если нет валидного токена, возвращает authorize-URL.
 *   Скрипт печатает URL, ждёт ввода кода авторизации (code), вызывает med_cisz_api.getToken()
 *   (обмен code -> access/refresh токены), токен сохраняется в сессии 'cisz'.
 */

require_once(__DIR__ . '/../../init.php');
require_once(__DIR__ . '/constant.php');
require_once(__DIR__ . '/enums.php');
suspendJsonRpc();
require_once(__DIR__ . '/api.php');
resumeJsonRpc();
require_once(__DIR__ . '/bundle.php');

// bundle.php заканчивается handleJsonRpc('med_cisz_bundle'), который в CLI-режиме
// (при наличии $_REQUEST['norpc']) уже может выполнить метод. Гасим это и управляем сами.
$_REQUEST['norpc'] = null;
$_REQUEST['argscmd'] = [];

final class med_cisz_cli_send_pending {

    private med_cisz_api $api;

    public function __construct() {
        $this->api = new med_cisz_api();
    }

    /**
     * Точка входа.
     * @param object $args  поля: date, entity, limit, dry
     */
    public function sendPending(object $args): void {
        $date   = isUndefinedProperty($args, 'date', date('Y-m-d'));
        $entity = isUndefinedProperty($args, 'entity', 'all');
        $limit  = (int) isUndefinedProperty($args, 'limit', 0);
        $dry    = !empty(isUndefinedProperty($args, 'dry', 0));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->out("Неверный формат даты: '$date' (нужен YYYY-MM-DD)", true);
            exit(2);
        }
        if (!in_array($entity, ['all', 'MedVisitPatient', 'MedLogSend'], true)) {
            $this->out("Недопустимый entity: '$entity' (допустимо: all, MedVisitPatient, MedLogSend)", true);
            exit(2);
        }

        $this->out('=== Потоковая отправка дневников в ЦИСЗ ===');
        $this->out("Дата: $date | entity: $entity | лимит: " . ($limit ?: 'без лимита') . ' | режим: ' . ($dry ? 'DRY-RUN (без отправки)' : 'ОТПРАВКА'));
        $this->out('');

        // 1. Авторизация (при необходимости — полная PKCE)
        $this->ensureAuthorized();

        // 2. Сбор неотправленных записей за день
        $tasks = $this->collectPending($date, $entity, $limit);

        if (empty($tasks)) {
            $this->out("Неотправленных дневников за $date не найдено.");
            return;
        }

        $this->out('Найдено к отправке: ' . count($tasks));
        $this->out('');

        // 3. Потоковая отправка
        $ok = 0; $fail = 0; $total = count($tasks);
        foreach ($tasks as $i => $task) {
            $label = sprintf('%s #%d (patient %d)', $task->entity, $task->visitPatientId, $task->patientId);
            if ($dry) {
                $this->out(sprintf('[%d/%d] %s — пропущено (dry-run)', $i + 1, $total, $label));
                $ok++;
                continue;
            }
            $this->out(sprintf('[%d/%d] %s — отправка...', $i + 1, $total, $label));
            try {
                // ВАЖНО: новый экземпляр med_cisz_bundle на каждую запись (класс
                // накапливает состояние; иначе ресурсы попадают в чужие бандлы).
                $bundle = new med_cisz_bundle();
                $res = $bundle->createBundleAndImport((object) [
                    'entity'        => $task->entity,
                    'visitPatientId'=> $task->visitPatientId,
                    'patientId'     => $task->patientId,
                ]);
                $err = (is_object($res) && isset($res->error)) ? $res->error : null;
                if (empty($err)) {
                    $this->out("    OK");
                    $ok++;
                } else {
                    $msg = is_string($err) ? $err : json_encode($err, JSON_UNESCAPED_UNICODE);
                    $this->out("    ОШИБКА: $msg", true);
                    $fail++;
                }
            } catch (Throwable $e) {
                $this->out('    ИСКЛЮЧЕНИЕ: ' . $e->getMessage(), true);
                $fail++;
            }
            if ($i + 1 < $total) { usleep(200000); } // пауза 0.2 c между пакетами
        }

        $this->out('');
        $this->out("=== Готово: отправлено $ok, ошибок $fail из $total ===");
    }

    /**
     * Убеждаемся в наличии валидного access_token в сессии 'cisz'.
     * Полная PKCE: checkAuthAndGetUrl() -> печать URL -> чтение code -> getToken().
     */
    private function ensureAuthorized(): void {
        // checkAuthAndGetUrl сам обновит refresh-токен, если можно, иначе вернёт URL
        $res = $this->api->checkAuthAndGetUrl(false);
        if ($res === true) {
            $this->out('Токен ЦИСЗ валиден.');
            $this->out('');
            return;
        }

        $this->out('Нет валидного токена ЦИСЗ — требуется авторизация (PKCE).');
        $this->out('');
        $this->out('Откройте в браузере следующий URL и войдите под учётной записью врача:');
        $this->out('');
        $this->out("  $res");
        $this->out('');
        $this->out('После входа браузер вернётся на redirect-адрес вида ...?code=XXXX&state=YYYY.');
        $this->out('Вставьте значение параметра "code" и нажмите Enter:');
        $this->out('> ');

        $code = trim((string) fgets(STDIN));
        if ($code === '') {
            $this->out('Код авторизации не введён. Выход.', true);
            exit(3);
        }

        // Кладём code в сессию и обмениваем на токен (как это делает callback api.php?app=web&op=login)
        setSessionValue('cisz_auth_code', $code);
        $token = $this->api->getToken(false);

        if (!$token || empty($token->access_token)) {
            $this->out('Не удалось обменять код на токен ЦИСЗ.', true);
            exit(4);
        }

        $this->out('Авторизация выполнена, токен сохранён.');
        $this->out('');
    }

    /**
     * Собирает неотправленные посещения и/или направления за дату.
     * «Не отправлено» = нет записи в CiszLink со статусом Sent (1) для сущности.
     */
    private function collectPending(string $date, string $entity, int $limit): array {
        $tasks = [];
        $db = connectDb();
        $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

        $wantVisit = in_array($entity, ['all', 'MedVisitPatient'], true);
        $wantLog   = in_array($entity, ['all', 'MedLogSend'], true);

        if ($wantVisit) {
            $rows = selectSql($db, <<<SQL
                SELECT
                    mvp.MedVisitPatientCode AS "visitPatientId",
                    mvp.MedPatientCode       AS "patientId"
                FROM MedVisitPatient mvp
                JOIN MedVisit mv ON mv.MedVisitCode = mvp.MedVisitCode
                WHERE mv.VisitDate = :date
                  AND NOT EXISTS (
                      SELECT 1 FROM CiszLink cl
                      WHERE cl.TableName = 'MedVisitPatient'
                        AND cl.PrimaryKey = mvp.MedVisitPatientCode
                        AND cl.Status = 1
                  )
                ORDER BY mvp.MedVisitPatientCode
                $limitSql
            SQL, [':date' => $date], PDO::FETCH_OBJ);

            foreach ($rows as $row) {
                $tasks[] = (object) [
                    'entity'        => 'MedVisitPatient',
                    'visitPatientId'=> (int) $row->visitPatientId,
                    'patientId'     => (int) $row->patientId,
                ];
            }
        }

        if ($wantLog) {
            $rows = selectSql($db, <<<SQL
                SELECT
                    mls.MedLogSendCode AS "visitPatientId",
                    mls.MedPatientCode AS "patientId"
                FROM MedLogSend mls
                WHERE mls.DocumentDate = :date
                  AND NOT EXISTS (
                      SELECT 1 FROM CiszLink cl
                      WHERE cl.TableName = 'MedLogSend'
                        AND cl.PrimaryKey = mls.MedLogSendCode
                        AND cl.Status = 1
                  )
                ORDER BY mls.MedLogSendCode
                $limitSql
            SQL, [':date' => $date], PDO::FETCH_OBJ);

            foreach ($rows as $row) {
                $tasks[] = (object) [
                    'entity'        => 'MedLogSend',
                    'visitPatientId'=> (int) $row->visitPatientId,
                    'patientId'     => (int) $row->patientId,
                ];
            }
        }

        return $tasks;
    }

    private function out(string $msg, bool $toStderr = false): void {
        fwrite($toStderr ? STDERR : STDOUT, $msg . "\n");
    }
}

// Запуск
$method = (is_string($_REQUEST['norpc'] ?? null) && $_REQUEST['norpc'] !== '')
    ? $_REQUEST['norpc']
    : 'sendPending';
$args = (object) (is_array($_REQUEST['argscmd'] ?? null) ? $_REQUEST['argscmd'] : []);

$cli = new med_cisz_cli_send_pending();
$cli->{$method}($args);
