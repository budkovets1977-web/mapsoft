<?php
require_once(__DIR__.'/../../init.php'); class med_dayhospital_graphAccept { private $db; const CODE_UNIQUE_ERROR_CODE = -101; const CODE_USED = -102; public function readGraphAccept($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
WITH times AS (
    SELECT 
        medGraphAcceptCode,
        STRING_AGG(time, ',') timeArray
    FROM (
        SELECT 
            medGraphAcceptTimeCode,
            medGraphAcceptCode,
            '{' || JSON_NUMERIC('hour', hour) || JSON_NUMERIC('minute', minute) 
            || JSON_NUMERIC('tillHour', tillHour) || JSON_NUMERIC('tillMinute', tillMinute, 0) || '}' "time"
        FROM MedGraphAcceptTime
    ) AS t1
    GROUP BY medGraphAcceptCode
)
SELECT 
    MGA.MedGraphAcceptCode "id",
    MGA.name "name",
    MGA.cipher "code",
    CASE coalesce(MGA.inactive, 0)
        WHEN 1 THEN null 
        WHEN 0 THEN 1
    END "active",
    '[' || T.timeArray || ']' "timeArray",
        MGA.Amount AS "maxAmount",
    MGA.AmountFrom AS "frequencyFrom",
    MGA.AmountTill AS "frequencyTo",
    MGA.AmountPeriod AS "periodFrom",
    MGA.AmountPeriodTill AS "periodTo",
    MGA.AmountPeriodTimeUnit AS "periodUnitId",
    MGA.Duration AS "durationFrom",
    MGA.DurationTill AS "durationTo",
    MGA.DurationTimeUnit AS "durationUnitId",
    MGA.Description AS "note",
    MGA.Note AS "descriptionAuto",
    (MGA.WeekDayMask & 1) > 0 AS "sunday",
    (MGA.WeekDayMask & 2) > 0 AS "monday",
    (MGA.WeekDayMask & 4) > 0 AS "tuesday",
    (MGA.WeekDayMask & 8) > 0 AS "wednesday",
    (MGA.WeekDayMask & 16) > 0 AS "thursday",
    (MGA.WeekDayMask & 32) > 0 AS "friday",
    (MGA.WeekDayMask & 64) > 0 AS "saturday",
    MGA.Instruction AS "instruction"
FROM MedGraphAccept MGA
LEFT JOIN times T ON T.medGraphAcceptCode = MGA.MedGraphAcceptCode
EOT;
return selectSql($db, $sql); } public function updateGraphAccept($records) { $db = $this->db ?: $this->db = connectDb(); $weekDayBits = [ 'sunday' => 1, 'monday' => 2, 'tuesday' => 4, 'wednesday' => 8, 'thursday' => 16, 'friday' => 32, 'saturday' => 64 ]; $sql = <<<"EOT"
UPDATE MedGraphAccept SET
   cipher = case when :codeExists = 1 then :code else cipher end,
   name = case when :nameExists = 1 then :name else name end,
   inactive = case when :inactiveExists = 1 then :inactive else inactive end,
   Amount = CASE WHEN :amountExists = 1 THEN :amount ELSE Amount END,
   AmountFrom = CASE WHEN :amountFromExists = 1 THEN :amountFrom ELSE AmountFrom END,
   AmountTill = CASE WHEN :amountTillExists = 1 THEN :amountTill ELSE AmountTill END,
   AmountPeriod = CASE WHEN :periodExists = 1 THEN :period ELSE AmountPeriod END,
   AmountPeriodTill = CASE WHEN :periodTillExists = 1 THEN :periodTill ELSE AmountPeriodTill END,
   AmountPeriodTimeUnit = CASE WHEN :periodTimeUnitExists = 1 THEN :periodTimeUnit ELSE AmountPeriodTimeUnit END,
   WeekDayMask = CASE WHEN :weekDayMaskExists = 1 THEN :weekDayMask ELSE WeekDayMask END,
   Duration = CASE WHEN :durationExists = 1 THEN :duration ELSE Duration END,
   DurationTill = CASE WHEN :durationTillExists = 1 THEN :durationTill ELSE DurationTill END,
   DurationTimeUnit = CASE WHEN :durationTimeUnitExists = 1 THEN :durationTimeUnit ELSE DurationTimeUnit END,
   Note = CASE WHEN :descriptionExists = 1 THEN :description ELSE Note END,
   Description = CASE WHEN :noteExists = 1 THEN :note ELSE Description END,
   Instruction = CASE WHEN :instructionExists = 1 THEN :instruction ELSE Instruction END
where MedGraphAcceptCode = :id
EOT;
if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $codeExists = propertyExistsSQL($row, 'code'); $code = isUndefinedProperty($row, 'code'); if ($codeExists) { $this->checkCodeUnique($db, $code); } $weekDayMaskExists = false; $weekDayMask = null; foreach ($weekDayBits as $day => $bit) { if (propertyExistsSQL($row, $day)) { $weekDayMaskExists = true; break; } } if ($weekDayMaskExists) { $currentMask = selectSqlValue( $db, "SELECT COALESCE(WeekDayMask, 0) 
         FROM MedGraphAccept 
         WHERE MedGraphAcceptCode = :id", [':id' => isUndefinedProperty($row, 'id')] ); $weekDayMask = (int)$currentMask; foreach ($weekDayBits as $day => $bit) { if (propertyExistsSQL($row, $day)) { $val = (bool)isUndefinedProperty($row, $day); if ($val) { $weekDayMask |= $bit; } else { $weekDayMask &= ~$bit; } } } } selectSql($db, $sql, [ ':codeExists' => $codeExists, ':code' => $code, ':nameExists' => propertyExistsSQL($row, 'name'), ':name' => isUndefinedProperty($row, 'name'), ':inactiveExists' => propertyExistsSQL($row, 'active'), ':inactive' => (int)(!isUndefinedProperty($row, 'active')), ':amountExists' => propertyExistsSQL($row, 'maxAmount'), ':amount' => isUndefinedProperty($row, 'maxAmount'), ':amountFromExists' => propertyExistsSQL($row, 'frequencyFrom'), ':amountFrom' => isUndefinedProperty($row, 'frequencyFrom'), ':amountTillExists' => propertyExistsSQL($row, 'frequencyTo'), ':amountTill' => isUndefinedProperty($row, 'frequencyTo'), ':periodExists' => propertyExistsSQL($row, 'periodFrom'), ':period' => isUndefinedProperty($row, 'periodFrom'), ':periodTillExists' => propertyExistsSQL($row, 'periodTo'), ':periodTill' => isUndefinedProperty($row, 'periodTo'), ':periodTimeUnitExists' => propertyExistsSQL($row, 'periodUnitId'), ':periodTimeUnit' => isUndefinedProperty($row, 'periodUnitId'), ':weekDayMaskExists' => $weekDayMaskExists, ':weekDayMask' => $weekDayMask, ':durationExists' => propertyExistsSQL($row, 'durationFrom'), ':duration' => isUndefinedProperty($row, 'durationFrom'), ':durationTillExists' => propertyExistsSQL($row, 'durationTo'), ':durationTill' => isUndefinedProperty($row, 'durationTo'), ':durationTimeUnitExists' => propertyExistsSQL($row, 'durationUnitId'), ':durationTimeUnit' => isUndefinedProperty($row, 'durationUnitId'), ':descriptionExists' => propertyExistsSQL($row, 'descriptionAuto'), ':description' => isUndefinedProperty($row, 'descriptionAuto'), ':noteExists' => propertyExistsSQL($row, 'note'), ':note' => isUndefinedProperty($row, 'note'), ':instructionExists' => propertyExistsSQL($row, 'instruction'), ':instruction' => isUndefinedProperty($row, 'instruction'), ':id' => isUndefinedProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createGraphAccept($records) { $db = $this->db ?: $this->db = connectDb(); $weekDayBits = [ 'sunday' => 1, 'monday' => 2, 'tuesday' => 4, 'wednesday' => 8, 'thursday' => 16, 'friday' => 32, 'saturday' => 64 ]; $sql = <<<"EOT"
INSERT INTO MedGraphAccept (cipher, name, inactive, Amount, AmountFrom, AmountTill, 
    AmountPeriod, AmountPeriodTill, AmountPeriodTimeUnit, WeekDayMask, 
    Duration, DurationTill, DurationTimeUnit, Note, Description,
    Instruction) 
VALUES(:code, :name, :inactive, :amount, :amountFrom, :amountTill,
    :period, :periodTill, :periodTimeUnit, :weekDayMask,
    :duration, :durationTill, :durationTimeUnit, :description, :note,
    :instruction)
RETURNING MedGraphAcceptCode
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $code = isUndefinedProperty($row, 'code'); $this->checkCodeUnique($db, $code); $weekDayMask = 0; foreach ($weekDayBits as $day => $bit) { if (propertyExistsSQL($row, $day) && (bool)isUndefinedProperty($row, $day)) { $weekDayMask |= $bit; } } $rec->id = selectSqlValue($db, $sql, [ ':code' => $code, ':name' => isUndefinedProperty($row, 'name'), ':inactive' => (int)(!isUndefinedProperty($row, 'active')), ':amount' => isUndefinedProperty($row, 'maxAmount'), ':amountFrom' => isUndefinedProperty($row, 'frequencyFrom'), ':amountTill' => isUndefinedProperty($row, 'frequencyTo'), ':period' => isUndefinedProperty($row, 'periodFrom'), ':periodTill' => isUndefinedProperty($row, 'periodTo'), ':periodTimeUnit' => isUndefinedProperty($row, 'periodUnitId'), ':weekDayMask' => $weekDayMask, ':duration' => isUndefinedProperty($row, 'durationFrom'), ':durationTill' => isUndefinedProperty($row, 'durationTo'), ':durationTimeUnit' => isUndefinedProperty($row, 'durationUnitId'), ':description' => isUndefinedProperty($row, 'descriptionAuto'), ':note' => isUndefinedProperty($row, 'note'), ':instruction' => isUndefinedProperty($row, 'instruction') ]); $timeArrayJson = isUndefinedProperty($row, 'timeArray'); if ($timeArrayJson) { $timeArray = json_decode($timeArrayJson, true); if ($timeArray && is_array($timeArray)) { $timeRecordsObjects = []; foreach ($timeArray as $t) { $obj = new stdClass; $obj->graphId = (int)$rec->id; $obj->hour = (int)$t['hour']; $obj->minute = (int)$t['minute']; $obj->tillHour = (int)$t['tillHour']; $obj->tillMinute = (int)$t['tillMinute']; $timeRecordsObjects[] = $obj; } insertSql($db, 'MedGraphAcceptTime', $timeRecordsObjects, 'MedGraphAcceptCode Hour Minute TillHour TillMinute', 'graphId hour minute tillHour tillMinute'); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } protected function checkCodeUnique ($db, $code) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT 
        MedGraphAcceptCode
    FROM MedGraphAccept
    WHERE cipher = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':code' => $code ]); if ($result) { throw new Exception("Запись с кодом $code уже существует", self::CODE_UNIQUE_ERROR_CODE); } } protected function checkUsedInDayHospitalPurpose ($db, $id) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT 
        MedDayHopsitalPurposeCode
    FROM MedDayHopsitalPurpose
    WHERE MedGraphAcceptCode = :id
)
EOT;
$result = selectSqlValue($db, $sql, [ ':id' => $id ]); if ($result) { throw new Exception("Нельзя удалить запись, так как данные используются в журнале дневного стационара", self::CODE_UNIQUE_ERROR_CODE); } } public function deleteGraphAccept($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
DELETE FROM MedGraphAccept 
WHERE MedGraphAcceptCode = :id
EOT;
$db->beginTransaction(); try { foreach ($records as $row) { $id = isUndefinedProperty($row, 'id'); $this->checkUsedInDayHospitalPurpose($db, $id); selectSqlValue($db, $sql, [ ':id' => $id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_dayhospital_graphAccept');