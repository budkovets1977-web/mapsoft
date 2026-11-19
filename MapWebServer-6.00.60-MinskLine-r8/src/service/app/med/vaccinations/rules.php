<?php
 require_once(__DIR__ . '/../../init.php'); class med_vaccinations_rules { private $db; public function readRules($params = null) { $db = $this->db ?: $this->db = connectDb(); $search = isset($params->nameId) ? " mi.medInoculateCode = $params->nameId " : ""; $sql = <<< "EOT"
SELECT
    mir.medInoculateRuleCode "id",
    mir.medInoculateCode "nameId",
    mir.position "position",
    mir.ruleType "ruleType",
    mir.termDay "termDay",
    mir.termMonth "termMonth",
    mir.termYear "termYear",
    CASE
        WHEN mir.termYear > 0 THEN 0
        WHEN mir.termMonth > 0 THEN 1
        WHEN mir.termDay > 0 THEN 2
    END "measureId",
    GREATEST (mir.termDay, mir.termMonth, mir.termYear) "termDate",
    mir.medInoculateCodeLink "linkId",
    (SELECT MIG.name + ' - ' + MI.name FROM MedInoculate MI join MedInoculateGroup MIG on MI.medinoculategroupcode = MIG.medinoculategroupcode WHERE mi.MedInoculateCode = mir.medInoculateCodeLink)  "link"
FROM
    MedInoculate AS mi
    JOIN MedInoculateRule AS mir ON mi.medInoculateCode = mir.medInoculateCode
WHERE
    $search
order by mir.position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createRules($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
insert into MedInoculateRule (medInoculateCode, position, ruleType, termDay, termMonth, termYear, medInoculateCodeLink)
values (?, (SELECT max(position)+1 from medinoculaterule), ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'nameId'), isUndefinedProperty($row, 'ruleType'), isUndefinedProperty($row, 'termDay'), isUndefinedProperty($row, 'termMonth'), isUndefinedProperty($row, 'termYear'), isUndefinedProperty($row, 'linkId') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedInoculateRule'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateRules($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedInoculateRule set
    medInoculeteCode = case when ? = 1 then ? else medInoculeteCode end,
    position = case when ? = 1 then ? else position end,
    ruleType = case when ? = 1 then ? else ruleType end,
    termDay = case when ? = 1 then ? else termDay end,
    termMonth = case when ? = 1 then ? else termMonth end,
    termYear = case when ? = 1 then ? else termYear end,
    medInoculateCodeLink = case when ? = 1 then ? else medInoculateCodeLink end
where medInoculateRuleCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'nameId'), isUndefinedProperty($row, 'nameId'), propertyExistsSQL($row, 'position'), isEmptyProperty($row, 'position'), propertyExistsSQL($row, 'ruleTypeId'), isEmptyProperty($row, 'ruleTypeId'), propertyExistsSQL($row, 'termDay'), isEmptyProperty($row, 'termDay'), propertyExistsSQL($row, 'termMonth'), isEmptyProperty($row, 'termMonth'), propertyExistsSQL($row, 'termYear'), isEmptyProperty($row, 'termYear'), propertyExistsSQL($row, 'linkId'), isEmptyProperty($row, 'linkId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteRules($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedInoculateRule', 'medInoculateRuleCode', $records); } public function deleteVaccineRules($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
DELETE from medinoculaterule
    WHERE medinoculatecode = $params
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } } $service = new Junior\Server(new med_vaccinations_rules()); $service->process(); 