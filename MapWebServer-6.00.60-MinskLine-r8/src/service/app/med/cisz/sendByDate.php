<?php
/**
 * Сервис массовой отправки неотправленных дневников (посещений + направлений) за дату в ЦИСЗ.
 *
 * Отдельный класс (не изменяет вендорский bundle.php/api.php).
 * Переиспользует штатный метод отправки med_cisz_bundle::createBundleAndImport().
 *
 * JSON-RPC методы (через handleJsonRpc('med_cisz_sendByDate')):
 *   readUnsentByDate({date: 'YYYY-MM-DD', doctorId: number})  -> { items: [ {entity, id, patientId, patientName, doctorName, date, type} ] }
 *   sendSelected({items: [{entity, id, patientId}]}) -> { results: [ {entity, id, ok, message} ] }
 *
 * Примечание: регистрация пациента в ЦИСЗ списком не проверяется — отправка
 * (createBundleAndImport) сама регистрирует/сопоставляет пациента.
 */

require_once(__DIR__ . '/../../init.php');

class med_cisz_sendByDate {

    private ?PDO_ANY $db = null;

    private function db(): PDO_ANY {
        return $this->db ?: $this->db = connectDb();
    }

    /**
     * Список неотправленных посещений и направлений за дату.
     * «Не отправлено» = в CiszLink нет строки TableName='MedVisitPatient'|'MedLogSend'
     * c PrimaryKey=<id> и Status=1 (Sent).
     *
     * @return object { items: [...] }
     */
    public function readUnsentByDate(?object $params = null): object {
        $date = isUndefinedProperty($params, 'date');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception("Не указана или неверна дата (нужен формат YYYY-MM-DD)");
        }
        $db = $this->db();

        // Врач: явно выбранный в ведомости doctorId либо врач текущего пользователя.
        // Отображаются только записи выбранного врача.
        $doctorId = (int) (isUndefinedProperty($params, 'doctorId') ?: 0);
        if (!$doctorId) {
            $sessionDoctor = getSessionValue('mapMedDoctor');
            $doctorId = (int) (is_array($sessionDoctor) ? ($sessionDoctor['MedDoctorCode'] ?? 0) : 0);
        }
        if (!$doctorId) {
            throw new Exception("Не удалось определить врача. Выберите врача в ведомости и повторите.");
        }

        // Регистрация пациента в ЦИСЗ НЕ проверяется: штатный createBundleAndImport()
        // сам регистрирует/сопоставляет пациента (createPatient + checkPatientUuid),
        // как и вендорский cli-send-pending.php (collectPending).
        $wherePatientCisz = '';

        $items = [];

        // --- Посещения (MedVisitPatient) ---
        $visits = selectSql($db, <<<SQL
            SELECT
                mvp.MedVisitPatientCode AS "id",
                mvp.MedPatientCode       AS "patientId",
                mv.VisitDate             AS "date",
                mv.MedDoctorCode         AS "doctorId",
                m.SurName   AS "patientSurName",
                m.Name      AS "patientName",
                m.SecondName AS "patientSecondName",
                dm.SurName   AS "doctorSurName",
                dm.Name      AS "doctorName",
                dm.SecondName AS "doctorSecondName"
            FROM MedVisitPatient mvp
            JOIN MedVisit mv ON mv.MedVisitCode = mvp.MedVisitCode
            JOIN MedPatient mp ON mp.MedPatientCode = mvp.MedPatientCode
            LEFT JOIN Man m ON m.ManCode = mp.ManCode
            LEFT JOIN MedDoctor md ON md.MedDoctorCode = mv.MedDoctorCode
            LEFT JOIN Man dm ON dm.ManCode = md.ManCode
            WHERE mv.VisitDate = :date
              AND mv.MedDoctorCode = :doctorId
              AND mvp.MedVisitPatientCodeParent IS NULL
              AND mvp.MedPhantomCode IS NULL
              AND mvp.VisitCount IS NULL
              AND COALESCE(mvp.AttendantDiagnosis, 0) = 0
              $wherePatientCisz
              AND NOT EXISTS (
                  SELECT 1 FROM CiszLink cl
                  WHERE cl.TableName = 'MedVisitPatient'
                    AND cl.PrimaryKey = mvp.MedVisitPatientCode
                    AND cl.Status = 1
              )
            ORDER BY mv.VisitDate, mvp.MedVisitPatientCode
        SQL, [':date' => $date, ':doctorId' => $doctorId], PDO::FETCH_OBJ);

        foreach ($visits as $row) {
            $items[] = (object) [
                'entity'      => 'MedVisitPatient',
                'id'          => (int) $row->id,
                'patientId'   => (int) $row->patientId,
                'date'        => $row->date,
                'patientName' => trim(implode(' ', array_filter([$row->patientSurName, $row->patientName, $row->patientSecondName]))),
                'doctorName'  => trim(implode(' ', array_filter([$row->doctorSurName, $row->doctorName, $row->doctorSecondName]))),
                'type'        => 'Посещение',
            ];
        }

        // --- Направления (MedLogSend) ---
        $logs = selectSql($db, <<<SQL
            SELECT
                mls.MedLogSendCode AS "id",
                mls.MedPatientCode AS "patientId",
                mls.DocumentDate   AS "date",
                mls.MedDoctorCode  AS "doctorId",
                m.SurName   AS "patientSurName",
                m.Name      AS "patientName",
                m.SecondName AS "patientSecondName",
                dm.SurName   AS "doctorSurName",
                dm.Name      AS "doctorName",
                dm.SecondName AS "doctorSecondName"
            FROM MedLogSend mls
            JOIN MedPatient mp ON mp.MedPatientCode = mls.MedPatientCode
            LEFT JOIN Man m ON m.ManCode = mp.ManCode
            LEFT JOIN MedDoctor md ON md.MedDoctorCode = mls.MedDoctorCode
            LEFT JOIN Man dm ON dm.ManCode = md.ManCode
            WHERE mls.DocumentDate = :date
              AND mls.MedDoctorCode = :doctorId
              $wherePatientCisz
              AND NOT EXISTS (
                  SELECT 1 FROM CiszLink cl
                  WHERE cl.TableName = 'MedLogSend'
                    AND cl.PrimaryKey = mls.MedLogSendCode
                    AND cl.Status = 1
              )
            ORDER BY mls.DocumentDate, mls.MedLogSendCode
        SQL, [':date' => $date, ':doctorId' => $doctorId], PDO::FETCH_OBJ);

        foreach ($logs as $row) {
            $items[] = (object) [
                'entity'      => 'MedLogSend',
                'id'          => (int) $row->id,
                'patientId'   => (int) $row->patientId,
                'date'        => $row->date,
                'patientName' => trim(implode(' ', array_filter([$row->patientSurName, $row->patientName, $row->patientSecondName]))),
                'doctorName'  => trim(implode(' ', array_filter([$row->doctorSurName, $row->doctorName, $row->doctorSecondName]))),
                'type'        => 'Направление',
            ];
        }

        return (object) ['items' => $items];
    }

    /**
     * Массовая отправка выбранных записей.
     *
     * @param object $params { items: [ {entity, id, patientId}, ... ] }
     * @return object { results: [ {entity, id, ok, message}, ... ] }
     */
    public function sendSelected(?object $params = null): object {
        $items = isUndefinedProperty($params, 'items', []);
        if (!is_array($items) || empty($items)) {
            throw new Exception("Не переданы записи для отправки");
        }

        $results = [];

        foreach ($items as $item) {
            // ВАЖНО: новый экземпляр med_cisz_bundle на каждую запись — класс
            // накапливает состояние (patient, conditions, episodes и т.д.),
            // при переиспользовании ресурсы предыдущих записей попадают
            // в чужие бандлы («Cannot use external reference ...»).
            $bundle = new med_cisz_bundle();
            $item = (object) $item;
            $entity = $item->entity ?? null;
            $id = (int) ($item->id ?? 0);
            $patientId = (int) ($item->patientId ?? 0);

            if (!in_array($entity, ['MedVisitPatient', 'MedLogSend'], true) || !$id) {
                $results[] = (object) [
                    'entity'  => $entity,
                    'id'      => $id,
                    'ok'      => false,
                    'message' => 'Некорректная запись',
                ];
                continue;
            }

            try {
                // Если Patient уже отправлен, но UUID ещё не получен, сначала
                // запросить статус его регистрации и обновить существующую ссылку.
                if ($entity === 'MedVisitPatient' && $patientId) {
                    $patientPending = (int) selectSqlValue(
                        connectDb(),
                        "SELECT PrimaryKey FROM CiszLink WHERE PrimaryKey = ? AND TableName = 'MedPatient' AND Type = 1 AND Uuid IS NULL",
                        [$patientId]
                    );
                    if ($patientPending) {
                        $patientStatus = new med_cisz_bundle();
                        $patientStatus->checkBundleStatus((object) [
                            'entity'    => 'MedPatient',
                            'patientId' => $patientId,
                        ]);
                    }
                }
                $res = $bundle->createBundleAndImport((object) [
                    'entity'         => $entity,
                    'visitPatientId' => $id,
                    'patientId'      => $patientId,
                ]);
                $err = (is_object($res) && isset($res->error) && !empty($res->error)) ? $res->error : null;
                if (empty($err)) {
                    $results[] = (object) [
                        'entity'  => $entity,
                        'id'      => $id,
                        'ok'      => true,
                        'message' => '',
                    ];
                } else {
                    $results[] = (object) [
                        'entity'  => $entity,
                        'id'      => $id,
                        'ok'      => false,
                        'message' => is_string($err) ? $err : json_encode($err, JSON_UNESCAPED_UNICODE),
                    ];
                }
            } catch (Throwable $e) {
                $results[] = (object) [
                    'entity'  => $entity,
                    'id'      => $id,
                    'ok'      => false,
                    'message' => $e->getMessage(),
                    'retryable' => ((int) $e->getCode() === -7301),
                ];
            }
        }

        return (object) ['results' => $results];
    }
}

handleJsonRpc('med_cisz_sendByDate');
