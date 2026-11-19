<?php
 require_once(__DIR__.'/../../init.php'); class med_common_planPopulation { private $db; public function readPlanPopulation($params = null) { $db = $this->db ?: $this->db = connectDb(); $categoryId = isEmptyProperty($params, 'categoryId'); $dateId = isEmptyProperty($params, 'dateId'); $sql = <<<"EOT"
SELECT
    MPP.medpatientplancode "id",
    MPP.medpatientplandatecode "dateId",
    MPP.peoplecategory "categoryId",
    MPP.medpatientpolycliniccode "clinicId",
    MPP.medpatientgroupcode "regionId",
    MPP.workercount "count",
    MPPD.ondate "date",
    MPG.name "region",
    UNI.value "category",
    MPPC.name "clinic"
FROM medpatientplan MPP
    left join medpatientpolyclinic MPPC on MPPC.medpatientpolycliniccode =  MPP.medpatientpolycliniccode
    left join medpatientplandate MPPD on MPPD.medpatientplandatecode = MPP.medpatientplandatecode
    left join unidict UNI on UNI.unidictcode = MPP.peoplecategory
    left join medpatientgroup MPG on MPG.medpatientgroupcode = MPP.medpatientgroupcode
WHERE MPP.peoplecategory = $categoryId and MPP.medpatientplandatecode = $dateId
EOT;
if (isset($categoryId) && isset($dateId)) { $stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } public function updatePlanPopulation($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
UPDATE medpatientplan SET
    medpatientgroupcode = case when ? = 1 then ? else medpatientgroupcode end,
    medpatientpolycliniccode = case when ? = 1 then ? else medpatientpolycliniccode end,
    workercount = case when ? = 1 then ? else workercount end
WHERE medpatientplancode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'regionId'), isUndefinedProperty($row, 'regionId'), propertyExistsSQL($row, 'clinicId'), isUndefinedProperty($row, 'clinicId'), propertyExistsSQL($row, 'count'), isUndefinedProperty($row, 'count'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createPlanPopulation($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO medpatientplan (medpatientgroupcode, medpatientpolycliniccode, workercount, medpatientplandatecode, peoplecategory)
values (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'regionId'), isEmptyProperty($row, 'clinicId'), isEmptyProperty($row, 'count'), isEmptyProperty($row, 'dateId'), isEmptyProperty($row, 'categoryId'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_medpatientplan'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePlanPopulation($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medpatientplan', 'medpatientplancode', $records); } public function copyPlanPopulation($params) { $db = $this->db ?: $this->db = connectDb(); $dateId = isEmptyProperty($params, 'dateId'); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO MedPatientPlan (MedPatientGroupCode, PeopleCategory, WorkerCount, MedPatientPlanDateCode, MedPatientPolyclinicCode)
    SELECT MedPatientGroupCode, PeopleCategory, WorkerCount, $dateId, MedPatientPolyclinicCode FROM MedPatientPlan
    WHERE MedPatientPlanDateCode = (
        SELECT MedPatientPlanDateCode
        FROM  medpatientplandate
        WHERE ondate = (
            SELECT max(ondate)
            FROM medpatientplandate
            WHERE coalesce(ondate,30000101)< (
                    SELECT ondate
                    FROM medpatientplandate
                    WHERE MedPatientPlanDateCode = $dateId)
                and enterprisecode = $enterpriseCode)
          and enterprisecode = $enterpriseCode
        order by MedPatientPlanDateCode desc
        limit 1)
EOT;
$stmt = $db->prepare($sql); if ($dateId) { $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } } public function checkValidation($params) { $db = $this->db ?: $this->db = connectDb(); $categoryId = isEmptyProperty($params, 'categoryId'); $sql = <<<"EOT"
SELECT
    peoplecategory "categoryId"
FROM medpatientplan
WHERE peoplecategory = $categoryId
LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $result = count($stmt->fetchAll()); if ($result) { return true; } } } $service = new Junior\Server(new med_common_planPopulation()); $service->process();