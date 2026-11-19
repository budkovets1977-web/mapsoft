<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_schedules { private $db; public function readSchedules(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
                SELECT
                    uniTimeTableCode id,
                    sourceCode "serviceId",
                    NULL "optionId",
                    E'Все' "optionName",
                    ruleYear "ruleYear",
                    ruleMonth "ruleMonth",
                    ruleEvenOdd "ruleEvenOdd",
                    ruleDay "ruleDay",
                    ruleDaysOfWeek "ruleDaysOfWeek",
                    DATEFORMAT(startTime, E'HH:nn') "startTime",
                    DATEFORMAT(finishTime, E'HH:nn') "finishTime"
                FROM uniTimeTable
                WHERE source = 1    -- Услуги
                UNION ALL
                SELECT
                    u.uniTimeTableCode id,
                    so.medEqServiceCode "serviceId",
                    u.sourceCode "optionId",
                    so.name "optionName",
                    u.ruleYear "ruleYear",
                    u.ruleMonth "ruleMonth",
                    u.ruleEvenOdd "ruleEvenOdd",
                    u.ruleDay "ruleDay",
                    u.ruleDaysOfWeek "ruleDaysOfWeek",
                    DATEFORMAT(u.startTime, E'HH:nn') "startTime",
                    DATEFORMAT(u.finishTime, E'HH:nn') "finishTime"
                FROM uniTimeTable u
                LEFT JOIN medEqServiceOption so ON so.medEqServiceOptionCode = u.sourceCode
                WHERE u.source = 3    -- Опции
                ORDER BY 2, 3, 4, 5, 6, 7, 8, 9
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createSchedules($records){ $db = $this->db ?: $this->db = connectDb(); $ret = []; $source = 1; $sourceId = $records->serviceId; if ($records->optionId){ $source = 3; $sourceId = $records->optionId; } $sql = <<<EOT
            INSERT INTO uniTimeTable
                (ruleYear, ruleMonth, ruleEvenOdd,
                    ruleDay, ruleDaysOfWeek, startTime,
                    finishTime, source, sourceCode)
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($records, 'ruleYear'), isEmptyProperty($records, 'ruleMonth'), isEmptyProperty($records, 'ruleEvenOdd'), isEmptyProperty($records, 'ruleDay'), isEmptyProperty($records, 'ruleDaysOfWeek'), isUndefinedProperty($records, 'startTime'), isUndefinedProperty($records, 'finishTime'), $source, $sourceId ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_UniTimeTable'); return $ret; } public function updateSchedules($records){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE uniTimeTable SET
                ruleYear = CASE WHEN ? = 1 THEN ? ELSE ruleYear END,
                ruleMonth = CASE WHEN ? = 1 THEN ? ELSE ruleMonth END,
                ruleEvenOdd = CASE WHEN ? = 1 THEN ? ELSE ruleEvenOdd END,
                ruleDay = CASE WHEN ? = 1 THEN ? ELSE ruleDay END,
                ruleDaysOfWeek = CASE WHEN ? = 1 THEN ? ELSE ruleDaysOfWeek END,
                startTime = CASE WHEN ? = 1 THEN ? ELSE startTime END,
                finishTime = CASE WHEN ? = 1 THEN ? ELSE finishTime END,
                sourceCode = CASE WHEN ? = 1 THEN ? ELSE sourceCode END
            WHERE uniTimeTableCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'ruleYear'), isEmptyProperty($row, 'ruleYear'), propertyExistsSQL($row, 'ruleMonth'), isEmptyProperty($row, 'ruleMonth'), propertyExistsSQL($row, 'ruleEvenOdd'), isEmptyProperty($row, 'ruleEvenOdd'), propertyExistsSQL($row, 'ruleDay'), isEmptyProperty($row, 'ruleDay'), propertyExistsSQL($row, 'ruleDaysOfWeek'), isEmptyProperty($row, 'ruleDaysOfWeek'), propertyExistsSQL($row, 'startTime'), isUndefinedProperty($row, 'startTime'), propertyExistsSQL($row, 'finishTime'), isUndefinedProperty($row, 'finishTime'), propertyExistsSQL($row, 'serviceId'), isEmptyProperty($row, 'serviceId'), $row->id ]); } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function deleteSchedules ($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = "DELETE FROM UniTimeTable WHERE UniTimeTableCode $in"; $stmt = $db->prepare($sql); $stmt->execute(); return true; } } handleJsonRpc('equeue_app_schedules');