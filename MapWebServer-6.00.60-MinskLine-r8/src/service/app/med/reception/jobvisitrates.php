<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_visitrates { private $db; public function readJobVisitRates($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"SQL"
SELECT 
    MJP.MedJobPlanCode as "id",
    MP.Name AS "professionName",
    MP.MedProfessionCode AS "professionId",
    MJP.MedJobVisitKindCode AS "visitKindCode",
    MJP.VisitNumberOnHour AS "visitNumberOnHour"
FROM MedProfession MP
    INNER JOIN MedJobPlan MJP ON MJP.MedProfessionCode = MP.MedProfessionCode
    WHERE    
        MJP.MedJobVisitKindCode IN (1, 2, 3)  -- Виды посещений: амбулаторные приемы (2), визиты (1), профосмотры (3)
        AND MJP.MedJobPlanCode = (
            SELECT MIN(MedJobPlanCode)
            FROM MedJobPlan 
            WHERE MedProfessionCode = MP.MedProfessionCode 
            AND MedJobVisitKindCode = MJP.MedJobVisitKindCode
        )
    ORDER BY MP.Name, MJP.MedJobVisitKindCode;
SQL;
return selectSql($db, $sql); } public function createJobVisitRates($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"SQL"
        INSERT INTO MedJobPlan (medprofessioncode, medjobvisitkindcode, visitnumberonhour, visitnumberonhourchild) VALUES (?, ?, ?, ?)
    SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); if (is_object($records)) { $records = [$records]; } try { foreach ($records as $record) { $professionId = isUndefinedProperty($record, 'professionId'); $arr = [isUndefinedProperty($record, 'outpatientHour'), isUndefinedProperty($record, 'visitHour'), isUndefinedProperty($record, 'examinationsHour')]; for ($i = 1; $i <= 3; $i++) { $stmt->execute([ $professionId, $i, $arr[$i - 1], 0 ]); }; } $db->commit(); return ['success' => true]; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function updateJobVisitRates($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"SQL"
            UPDATE MedJobPlan SET
                medprofessioncode = case when ? = 1 then ? else medprofessioncode end,
                visitnumberonhour = case when ? = 1 then ? else visitnumberonhour end
            WHERE medjobplancode = ?
    SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); if (is_object($records)) { $records = [$records]; } try { foreach ($records as $record) { $professionId = isUndefinedProperty($record, 'professionId'); $arrHour = [isUndefinedProperty($record, 'outpatientHour'), isUndefinedProperty($record, 'visitHour'), isUndefinedProperty($record, 'examinationsHour')]; $arrProp = [propertyExistsSQL($record, 'outpatientHour'), propertyExistsSQL($record, 'visitHour'), propertyExistsSQL($record, 'examinationsHour')]; $arrKindVisit = [isUndefinedProperty($record, 'outpatientHourId'), isUndefinedProperty($record, 'visitHoursId'), isUndefinedProperty($record, 'examinationsHoursId')]; $professionExist = propertyExistsSQL($record, 'professionId'); for ($i = 1; $i <= 3; $i++) { if ($arrProp[$i - 1] || $professionExist) { $stmt->execute([ $professionExist, $professionId, $arrProp[$i - 1], $arrHour[$i - 1], $arrKindVisit[$i - 1] ]); } }; } $db->commit(); return ['success' => true]; } catch (Exception $e) { $db->rollBack(); throw $e; } } public function deleteJobVisitRates($records) { $db = $this->db ?: $this->db = connectDb(); $outpatientHoursId = isUndefinedProperty($records, 'outpatientHourId'); $visitHoursId = isUndefinedProperty($records, 'visitHoursId'); $examinationsHoursId = isUndefinedProperty($records, 'examinationsHoursId'); $sql = <<<"SQL"
            DELETE FROM MedJobPlan WHERE medjobplancode = :outpatientHoursId OR medjobplancode = :visitHoursId OR medjobplancode = :examinationsHoursId
        SQL;
$sqlParams[':outpatientHoursId'] = $outpatientHoursId; $sqlParams[':visitHoursId'] = $visitHoursId; $sqlParams[':examinationsHoursId'] = $examinationsHoursId; return executeSql($db, $sql, $sqlParams); } } handleJsonRpc('med_reception_visitrates'); 