<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_diagnosisReportSet { private $db; public function readDiagnosisReportRowSet($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : 0; $idArray = ""; if (isEmptyProperty($params, 'idArray', 0)) { $idArray = createSqlInCondition(isUndefinedProperty($params, 'idArray', 0)); $idArray = "and(L.MedDiagnosisReportLineSetCode $idArray )"; } $sql = <<<"EOT"
SELECT
 L.MedDiagnosisReportLineSetCode "id",
 L.Name "name",
 L.LineNumber "lineNumber",
 L.MedDiagnosisReportSetCode "diagnosisReportSetCode",
 L.Prefix "prefix",
 L.PrefixCarrying "prefixCarrying",
 L.Position "position",
 CASE
    WHEN L.Prefix=1  THEN 'в том числе'
    WHEN L.Prefix=1  THEN 'из них'
END "prefixText",
     ( Select
       string_agg( MD.Name, ',')
        from meddiagnosisreportlinelist as MDRL
                left join meddiagnosis as MD on MD.medDiagnosisCode=MDRL.medDiagnosisCode
                where MDRL.MedDiagnosisReportLineSetCode = L.MedDiagnosisReportLineSetCode
         ) "diagnosis",
       ( Select
       string_agg( MO.Name, ',')
        from meddiagnosisreportlinelist as MDRL
                left join medOperation as MO on MO.medOperationCode=MDRL.medOperationCode
                where MDRL.MedDiagnosisReportLineSetCode = L.MedDiagnosisReportLineSetCode
         ) "operations",
       ( Select
       string_agg( MD.medDiagnosisCode, ',' order by MD.medDiagnosisCode)
        from meddiagnosisreportlinelist as MDRL
                left join meddiagnosis as MD on MD.medDiagnosisCode=MDRL.medDiagnosisCode
                where MDRL.MedDiagnosisReportLineSetCode = L.MedDiagnosisReportLineSetCode
         ) "diagnosisIds",
       ( Select
       string_agg( MO.medOperationCode, ',' order by MO.medOperationCode)
        from meddiagnosisreportlinelist as MDRL
                left join medOperation as MO on MO.medOperationCode=MDRL.medOperationCode
                where MDRL.MedDiagnosisReportLineSetCode = L.MedDiagnosisReportLineSetCode
         ) "operationsIds",
 L.CipherList "cipherList"
FROM medDiagnosisReportLineSet as L
Where  ( 0 = $id or $id = L.MedDiagnosisReportLineSetCode) $idArray
group By 1
ORDER BY 7
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDiagnosisReportRowSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update medDiagnosisReportLineSet set
       LineNumber = case when ? = 1 then ? else LineNumber end,
       Prefix = case when ? = 1 then ? else Prefix end,
       PrefixCarrying = case when ? = 1 then ? else PrefixCarrying end,
       Name = case when ? = 1 then ? else Name end,
       Position = case when ? = 1 then ? else Position end,
       CipherList = case when ? = 1 then ? else CipherList end,
       MedDiagnosisReportSetCode= case when ? = 1 then ? else MedDiagnosisReportSetCode end
where medDiagnosisReportLineSetCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, 0, null, 0, null, 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'lineNumber'), isUndefinedProperty($row, 'lineNumber'), propertyExistsSQL($row, 'prefix'), isUndefinedProperty($row, 'prefix'), propertyExistsSQL($row, 'prefixCarrying'), isUndefinedProperty($row, 'prefixCarrying'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'cipherList'), isUndefinedProperty($row, 'cipherList'), propertyExistsSQL($row, 'diagnosisReportSetCode'), isUndefinedProperty($row, 'diagnosisReportSetCode'), $row->id ]); if (!is_null(isUndefinedProperty($row, 'diagnosisIds'))) { $diagnosis = safe_explode(',', isUndefinedProperty($row, 'diagnosisIds')); $sqlDelete = <<<"EOT"
delete from MedDiagnosisReportLineList where MedDiagnosisReportLineSetCode = ? and MedDiagnosisCode is not null
EOT;
$stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute([ $row->id ]); if (isUndefinedProperty($row, 'diagnosisIds')) { $sqlDiagnos = <<<"EOT"
insert into medDiagnosisReportLineList (MedDiagnosisCode, MedDiagnosisReportLineSetCode)
values (?, ?)
EOT;
$stmtDiagnos = $db->prepare($sqlDiagnos); foreach ($diagnosis as $diagnos) { $stmtDiagnos->execute([ $diagnos, $row->id ]); } } } if (!is_null(isUndefinedProperty($row, 'operationsIds'))) { $operations = safe_explode(',', isUndefinedProperty($row, 'operationsIds')); $sqlDelete = <<<"EOT"
delete from MedDiagnosisReportLineList where MedDiagnosisReportLineSetCode = ? and MedOperationCode is not null
EOT;
$stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute([ $row->id ]); if (isUndefinedProperty($row, 'operationsIds')) { $sqlOperations = <<<"EOT"
insert into medDiagnosisReportLineList (MedOperationCode, MedDiagnosisReportLineSetCode)
values (?, ?)
EOT;
$stmtOperations = $db->prepare($sqlOperations); foreach ($operations as $operation) { $stmtOperations->execute([ $operation, $row->id ]); } } } $res = new stdClass; $rec = new stdClass; $rec->id = $row->id; $ret[] = $rec; $res->idArray = $ret; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readDiagnosisReportRowSet($res); } public function deleteDiagnosisReportRowSet($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "medDiagnosisReportLineSet", "medDiagnosisReportLineSetCode", $records); } public function createDiagnosisReportRowSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into medDiagnosisReportLineSet (LineNumber, Prefix, PrefixCarrying, Name, Position, CipherList, MedDiagnosisReportSetCode)
values (?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlPos = <<<"EOT"
select max(position) from medDiagnosisReportLineSet
                where  MedDiagnosisReportSetCode = ?
EOT;
$stmtPos = $db->prepare($sqlPos); $stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmtPos->execute([ isUndefinedProperty($row, 'diagnosisReportSetCode') ]); $pos = fetchValue($stmtPos, 'max'); $stmt->execute([ isUndefinedProperty($row, 'lineNumber'), isUndefinedProperty($row, 'prefix'), isUndefinedProperty($row, 'prefixCarrying'), isUndefinedProperty($row, 'name'), $pos + 1, isUndefinedProperty($row, 'cipherList'), isUndefinedProperty($row, 'diagnosisReportSetCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedDiagnosisReportLineSet'); $ret[] = $rec; if (!is_null(isEmptyProperty($row, 'diagnosisIds'))) { $diagnosis = safe_explode(',', isUndefinedProperty($row, 'diagnosisIds')); $sqlDiagnos = <<<"EOT"
insert into medDiagnosisReportLineList (MedDiagnosisCode, MedDiagnosisReportLineSetCode)
values (?, ?)
EOT;
$stmtDiagnos = $db->prepare($sqlDiagnos); foreach ($diagnosis as $diagnos) { $stmtDiagnos->execute([ $diagnos, $rec->id ]); } } if (!is_null(isEmptyProperty($row, 'operationsIds'))) { $operations = safe_explode(',', isUndefinedProperty($row, 'operationsIds')); $sqlOperations = <<<"EOT"
insert into medDiagnosisReportLineList (MedOperationCode, MedDiagnosisReportLineSetCode)
values (?, ?)
EOT;
$stmtOperations = $db->prepare($sqlOperations); foreach ($operations as $operation) { $stmtOperations->execute([ $operation, $rec->id ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readDiagnosisReportRowSet($rec); } public function readDiagnosisReportSet($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
 medDiagnosisReportSetCode "id",
 Name "name"
FROM medDiagnosisReportSet
ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDiagnosisReportSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedDiagnosisReportSet set
       Name = case when ? = 1 then ? else Name end
where MedDiagnosisReportSetCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDiagnosisReportSet($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "MedDiagnosisReportSet", "MedDiagnosisReportSetCode", $records); } public function createDiagnosisReportSet($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedDiagnosisReportSet (Name)
values (?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedDiagnosisReportSet'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getMaxDiagnosisReportRowCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $diagnosisReportCode = isset($params->diagnosisReportSetCode) && isEmptyProperty($params, 'diagnosisReportSetCode') ? $params->diagnosisReportSetCode : 0; $sql = <<<"EOT"
select asa_trim(IncStr(COALESCE((
     select repeat( E''  ,20 - length(asa_trim(LineNumber))) || asa_trim(LineNumber)
     from MedDiagnosisReportLineSet
     where MedDiagnosisReportSetCode = $diagnosisReportCode
     order by 1 desc LIMIT 1 ),  E'0')));
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCode = fetchValue($stmt, 'asa_trim'); return $maxCode; } } handleJsonRpc('med_common_diagnosisreportset'); 