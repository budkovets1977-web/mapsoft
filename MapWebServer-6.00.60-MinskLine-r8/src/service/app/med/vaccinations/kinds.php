<?php
 require_once(__DIR__ . '/../../init.php'); class med_vaccinations_kinds { private $db; public function readKinds($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isset($params->search) ? " AND (mi.name LIKE '%$params->search%' OR " . "mig.name LIKE '%$params->search%' OR " . "mmi.name LIKE '%$params->search%')" : ""; $sql = <<< "EOT"
SELECT
    mi.medInoculateCode "id",
    mig.name "group",
    mig.medInoculateGroupCode "groupId",
    mi.name "name",
    mi.position "position",
    mi.termType "termTypeId",
    mi.termDay "termDay",
    mi.termMonth "termMonth",
    mi.termYear "termYear",
    mi.type "vaccinationTp",
    COALESCE (mi.termDay, mi.termMonth, mi.termYear) "termDate",
    mi.CiszResourceValueCodeType "vaccinationTpCiszCode",
    CRV.Name "vaccinationTpCiszName",
    CASE
        WHEN mi.termYear > 0 THEN 0
        WHEN mi.termMonth > 0 THEN 1
        WHEN mi.termDay > 0 THEN 2
    END "measureId",
    string_agg(DISTINCT(mmi.name::text), '; ') AS "infectionNames",
    string_agg(DISTINCT(mmi.medInfectionCode::text), ',') AS "infectionIds",
    string_agg(DISTINCT(mmi.ciszresourcevaluecodeinfection::text), ',') AS "infectionIdsCisz",
    CRV.Code "vaccinationTypeCode"
FROM
    MedInoculate AS mi
    LEFT JOIN CiszResourceValue AS CRV ON CRV.CiszResourceValueCode = mi.CiszResourceValueCodeType
    LEFT JOIN MedInoculateInfection AS mii ON mii.medInoculateCode = mi.medInoculateCode
    LEFT JOIN MedInfection AS mmi ON mmi.medInfectionCode = mii.medInfectionCode
    LEFT JOIN MedInoculateGroup AS mig ON mig.medInoculateGroupCode = mi.medInoculateGroupCode
WHERE
   1 = 1
   $search
GROUP BY
    mi.name,
    mi.medInoculateCode,
    mig.name,
    mig.medInoculateGroupCode,
    CRV.Name,
    CRV.Code
ORDER BY
    mi.medInoculateGroupCode,
    mi.position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createDependences($params) { $db = $this->db ?: $this->db = connectDb(); $inoculateId = isset($params->inoculateId) && isEmptyProperty($params, 'inoculateId') ? $params->inoculateId : null; $sql = <<< "EOT"
INSERT INTO MedInoculateInfection (medinoculatecode, medinfectioncode)
VALUES ($inoculateId, ?)
EOT;
$deletesql = <<<"EOT"
DELETE from MedInoculateInfection
    WHERE medinoculatecode = $inoculateId
EOT;
$stmt = $db->prepare($sql); $stmt2 = $db->prepare($deletesql); $db->beginTransaction(); $stmt2->execute(); try { foreach ($params->infectionId as $row) { $stmt->execute([ $row ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createKinds($records) { $db = $this->db ?: $this->db = connectDb(); $sqlPos = <<<SQL
SELECT MAX(Position)
FROM MedInoculate
WHERE MedInoculateGroupCode = ?
SQL;
$sql = <<< "EOT"
insert into MedInoculate (medInoculateGroupCode, position, name, termType, termDay, termMonth, termYear, ciszResourceValueCodeType)
values (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $groupId = isUndefinedProperty($row, 'groupId'); $pos = (int)selectSqlValue($db, $sqlPos, [ $groupId ]); $pos++; $stmt->execute([ $groupId, $pos, isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'termTypeId'), isUndefinedProperty($row, 'termDay'), isUndefinedProperty($row, 'termMonth'), isUndefinedProperty($row, 'termYear'), isUndefinedProperty($row, 'vaccinationTpCiszCode') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInoculate'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateKinds($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedInoculate set
    medInoculateGroupCode = case when ? = 1 then ? else medInoculateGroupCode end,
    position = case when ? = 1 then ? else position end,
    name = case when ? = 1 then ? else name end,
    termType = case when ? = 1 then ? else termType end,
    termDay = case when ? = 1 then ? else termDay end,
    termMonth = case when ? = 1 then ? else termMonth end,
    termYear = case when ? = 1 then ? else termYear end,
    ciszResourceValueCodeType = case when ? = 1 then ? else ciszResourceValueCodeType end
where medInoculateCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'groupId'), isUndefinedProperty($row, 'groupId'), propertyExistsSQL($row, 'position'), isEmptyProperty($row, 'position'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'termType'), isEmptyProperty($row, 'termType'), propertyExistsSQL($row, 'termDay'), isEmptyProperty($row, 'termDay'), propertyExistsSQL($row, 'termMonth'), isEmptyProperty($row, 'termMonth'), propertyExistsSQL($row, 'termYear'), isEmptyProperty($row, 'termYear'), propertyExistsSQL($row, 'vaccinationTpCiszCode'), isEmptyProperty($row, 'vaccinationTpCiszCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteKinds($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInoculate', 'medInoculateCode', $records); } } handleJsonRpc('med_vaccinations_kinds');