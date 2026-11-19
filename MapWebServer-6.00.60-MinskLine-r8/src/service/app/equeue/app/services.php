<?php
require_once(__DIR__.'/../../init.php'); require_once('util/scheduler.php'); class equeue_app_services { const TASK_NAME = 'equeueAppServices'; private $db; public function readServices(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                mes.medEqServiceCode id,
                mes.name,
                mes.cipher,
                mes.active,
                ((EXTRACT(hour FROM WaitTime))::INTEGER) * 60 * 60 + ((EXTRACT(minute FROM WaitTime))::INTEGER) * 60 + ((EXTRACT(second FROM WaitTime))::INTEGER) "waitTime",
                mes.quota,
                mes.note,
                mes.label,
                ((EXTRACT(hour FROM ProceedTime))::INTEGER) * 60 + ((EXTRACT(minute FROM ProceedTime))::INTEGER) "proceedTimeMinute",
                ((EXTRACT(second FROM ProceedTime))::INTEGER) "proceedTimeSecond",
                STRING_AGG(mcwp.medCouponWorkProfCode, ',') "workProfIds"
            FROM medEqService mes
            LEFT JOIN medCouponWorkProf mcwp ON mcwp.medEqServiceCode = mes.medEqServiceCode
            WHERE enterpriseCode = ?
            GROUP BY mes.medEqServiceCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } public function updateServices($records, $noTransaction = false){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE MedEqService SET
                Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
                Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END,
                Active = CASE WHEN ? = 1 THEN ? ELSE Active END,
                Quota = CASE WHEN ? = 1 THEN ? ELSE Quota END,
                Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
                Label = CASE WHEN ? = 1 THEN ? ELSE Label END,
                WaitTime = CASE WHEN ? = 1 THEN CAST(DATEFORMAT(DATEADD(E'second', ?, 19700101), 'hh:nn:ss') AS VARCHAR(8)) ELSE WaitTime END,
                ProceedTime = NULLIF(CAST(DATEFORMAT(DATEADD(E'second', COALESCE(?, 0) * 60 + COALESCE (?, 0), 19700101), E'hh:nn:ss') AS VARCHAR(8)), E'00:00:00')
            WHERE MedEqServiceCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } if (!$noTransaction){ $db->beginTransaction(); } try { foreach($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'quota'), isUndefinedProperty($row, 'quota'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'label'), isUndefinedProperty($row, 'label'), propertyExistsSQL($row, 'waitTime'), isUndefinedProperty($row, 'waitTime'), $row->proceedTimeMinute, $row->proceedTimeSecond, $row->id ]); } if (!$noTransaction){ $db->commit(); } } catch (Throwable $e){ if (!$noTransaction){ $db->rollback(); } throw $e; } return true; } public function deleteServices($records){ $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = "DELETE FROM MedEqService WHERE MedEqServiceCode $in"; $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function syncServices($params){ $db = $this->db ?: $this->db = connectDb(); $service = isset($params->service) ? $params->service : null; $options = isset($params->options) ? $params->options : null; $categories = isset($params->categories) ? $params->categories : null; $redirection = isset($params->redirection) ? $params->redirection : null; $workTypes = isset($params->workTypes) ? $params->workTypes : null; $serviceId = (int)$service->id; $db->beginTransaction(); try { if ($serviceId == 0){ $sql = <<<EOT
INSERT INTO MedEqService(EnterpriseCode,
                            Name,
                            Cipher,
                            Active,
                            WaitTime,
                            Quota,
                            Note,
                            Label,
                            ProceedTime)
VALUES (?, ?, ?, ?,
        CAST(DATEFORMAT(DATEADD(E'second', ?, 19700101), 'hh:nn:ss') AS VARCHAR(8)),
        ?, ?, ?,
        NULLIF(CAST(DATEFORMAT(DATEADD(E'second', COALESCE(?, 0) * 60 + COALESCE(?, 0), 19700101), 'hh:nn:ss') AS VARCHAR(8)), E'00:00:00'))
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode'), $service->name, isEmptyProperty($service, 'cipher'), isEmptyProperty($service, 'active'), isEmptyProperty($service, 'waitTime'), isEmptyProperty($service, 'quota'), isEmptyProperty($service, 'note'), isEmptyProperty($service, 'label'), $service->proceedTimeMinute, $service->proceedTimeSecond ]); $serviceId = $db->lastInsertId('seq_MedEqService'); } else { $this->updateServices($service, true); } if ($options != null){ foreach($options as $o){ $optionId = (int) $o->id; if ($optionId == 0){ $sql = <<<EOT
INSERT INTO MedEqServiceOption (MedEqServiceCode, Position, Name, Label, Note, Active, ProceedTime)
        VALUES (?, NULL, ?, ?, ?, ?, NULLIF(CAST(DATEFORMAT(DATEADD(E'second', COALESCE(?, 0) * 60 + COALESCE(?, 0), 19700101), E'hh:nn:ss') AS VARCHAR(8)), E'00:00:00'))
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $serviceId, isEmptyProperty($o, 'name'), isEmptyProperty($o, 'label'), isEmptyProperty($o, 'note'), isEmptyProperty($o, 'active'), $o->proceedTimeMinute, $o->proceedTimeSecond ]); $o->id = $optionId = $db->lastInsertId('seq_MedEqServiceOption'); } else { $sql = <<<EOT
UPDATE MedEqServiceOption SET
    Position = null,
    Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
    Label = CASE WHEN ? = 1 THEN ? ELSE Label END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END,
    Active = CASE WHEN ? = 1 THEN ? ELSE Active END,
    ProceedTime = NULLIF(CAST(DATEFORMAT(DATEADD(E'second', COALESCE(?, 0) * 60 + COALESCE(?, 0), 19700101), E'hh:nn:ss') AS VARCHAR(8)), E'00:00:00')
WHERE MedEqServiceOptionCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($o, 'name'), isUndefinedProperty($o, 'name'), propertyExistsSQL($o, 'label'), isUndefinedProperty($o, 'label'), propertyExistsSQL($o, 'note'), isUndefinedProperty($o, 'note'), propertyExistsSQL($o, 'active'), isUndefinedProperty($o, 'active'), $o->proceedTimeMinute, $o->proceedTimeSecond, $o->id ]); } } $in = createSqlInCondition($options, 'id'); $sql = <<<EOT
DELETE FROM MedEqServiceOption
WHERE MedEqServiceCode = $serviceId AND NOT (MedEqServiceOptionCode $in)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); foreach($options as $o){ $sql = "UPDATE MedEqServiceOption SET Position = ? WHERE MedEqServiceOptionCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $o->position, $o->id ]); } } if ($categories != null){ $in = createSqlInCondition($categories, 'id'); $sql = <<<EOT
DELETE FROM MedEqServiceCategory
WHERE MedEqServiceCode = $serviceId AND NOT (MedEqPatientCategoryCode $in)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $sql = <<<EOT
INSERT INTO MedEqServiceCategory (MedEqServiceCode, MedEqPatientCategoryCode)
    SELECT $serviceId, pc.MedEqPatientCategoryCode
    FROM MedEqPatientCategory pc
    WHERE pc.MedEqPatientCategoryCode $in
        AND NOT EXISTS(
            SELECT * FROM MedEqServiceCategory
            WHERE MedEqServiceCode = $serviceId
                AND MedEqPatientCategoryCode = pc.MedEqPatientCategoryCode
                LIMIT 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $sql = <<<EOT
UPDATE MedEqServiceCategory SET
        Active = CASE WHEN ? = 1 THEN ? ELSE Active END,
        Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END,
        InitialDocumentNumber = CASE WHEN ? = 1 THEN ? ELSE InitialDocumentNumber END,
        Priority = CASE WHEN ? = 1 THEN ? ELSE Priority END,
        Rotation = CASE WHEN ? = 1 THEN ? ELSE Rotation END
WHERE MedEqServiceCode = $serviceId
    AND MedEqPatientCategoryCode = ?
EOT;
foreach($categories as $c){ $stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($c, 'active'), isUndefinedProperty($c, 'active'), propertyExistsSQL($c, 'cipher'), isUndefinedProperty($c, 'cipher'), propertyExistsSQL($c, 'initialDocumentNumber'), isUndefinedProperty($c, 'initialDocumentNumber'), propertyExistsSQL($c, 'priority'), isUndefinedProperty($c, 'priority'), propertyExistsSQL($c, 'rotation'), isUndefinedProperty($c, 'rotation'), $c->id ]); } } if ($redirection != null || is_array($redirection)){ $sql = <<<EOT
                    DELETE FROM medEqServiceRedirect
                    WHERE MedEqServiceCode = $serviceId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $sql = <<<EOT
                    INSERT INTO medEqServiceRedirect(medEqServiceCode,
                                                        medEqServiceCodeRedirect,
                                                        redirectTimeout,
                                                        abilityToReturn)
                    VALUES (?, ?, ?, ?)
EOT;
foreach($redirection as $r){ $stmt = $db->prepare($sql); $stmt->execute([ $serviceId, isEmptyProperty($r, 'medEqServiceId'), isEmptyProperty($r, 'redirectTimeout'), isEmptyProperty($r, 'abilityToReturn') ]); } } if ($workTypes != null){ $sql = <<<EOT
                    UPDATE medCouponWorkProf SET
                        medEqServiceCode = ?
                    WHERE medCouponWorkProfCode = ?
EOT;
foreach($workTypes as $workType){ $stmt = $db->prepare($sql); $stmt->execute([ $serviceId, $workType ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $serviceId; } public function forceInitService($params){ $db = $this->db ?: $this->db = connectDb(); $sql = 'SELECT PR_medEqInitServiceQueues(?, 1)'; $stmt = $db->prepare($sql); $stmt->execute([ $params->serviceId ]); } public function setupScheduler($active){ $settings = readExtraSettings(null, self::TASK_NAME); Scheduler::writeExtraSettings(null, self::TASK_NAME, (object)[ 'scheduleUUID' => isUndefinedProperty($settings, 'scheduleUUID'), 'scheduleActive' => $active, 'scheduleScript' => 'app/equeue/app/services.php', 'scheduleMethod' => 'executeServiceMaintenance', 'scheduleType' => 'minute', 'schedulePeriodicity' => 5 ], true); return true; } public function executeServiceMaintenance(){ $db = $this->db ?: $this->db = connectDb(); $sql = "SELECT medEqServiceCode id FROM medEqService"; $stmt = $db->prepare($sql); $stmt->execute(); $services = $stmt->fetchAll(); $sql = "SELECT PR_medEqInitServiceQueues(?)"; $stmt = $db->prepare($sql); foreach($services as $s){ $stmt->execute([$s['id']]); } return true; } public function testScheduler(){ $settings = readExtraSettings(null, self::TASK_NAME); return !empty($settings->scheduleActive); } } handleJsonRpc('equeue_app_services');