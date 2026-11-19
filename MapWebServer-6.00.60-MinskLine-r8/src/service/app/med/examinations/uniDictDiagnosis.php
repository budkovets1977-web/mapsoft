<?php
require_once(__DIR__.'/../../init.php'); class med_examinations_uniDictDiagnosis { private $db; public function readUniDictDiagnosis($params = null) { $db = $this->db ? : $this->db = connectDb(); $uniDictId = isset($params->uniDictId) && isEmptyProperty($params, 'uniDictId') ? $params->uniDictId : null; $sql = <<<"EOT"
select
    UD.unidictdiagnosiscode "id",
    UD.unidictcode "uniDictId",
    UD.meddiagnosiscode "diagnosisId",
    MD.cipher1 + ' ' + MD.name "name"
from unidictdiagnosis UD
    join meddiagnosis MD on MD.meddiagnosiscode = UD.meddiagnosiscode
    join unidict UNI on UNI.unidictcode = UD.unidictcode
where UD.unidictcode = $uniDictId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateUniDictDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update unidictdiagnosis set
    meddiagnosiscode = case when ? = 1 then ? else meddiagnosiscode end,
    unidictcode = case when ? = 1 then ? else unidictcode end
where unidictdiagnosiscode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $db->beginTransaction(); try{ foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'diagnosisId'), isEmptyProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'uniDictId'), isEmptyProperty($row, 'uniDictId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createUniDictDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
insert into unidictdiagnosis (unidictcode, meddiagnosiscode)
values(?,?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'uniDictId'), isEmptyProperty($row, 'diagnosisId') ]); $ret[] = $rec = new stdClass(); $rec->id = $db->lastInsertId('seq_unidictdiagnosis'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteUnidictDiagnosis($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'unidictdiagnosis', 'unidictdiagnosiscode', $records); } } handleJsonRpc('med_examinations_uniDictDiagnosis');