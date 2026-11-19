<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_wealthSections { private ?PDO_ANY $db = null; public function readWealthSections($params = null) { $db = $this->db ?: $this->db = connectDb(); $formType = isUndefinedProperty($params, 'formType'); $sqlFormType = $formType ? ($formType === 'all' ? '' : (stripos($formType, "null") !== false ? "AND (S.FormType IN ($formType) OR S.FormType IS NULL)" : "AND S.FormType IN ($formType)")) : 'AND S.FormType IN (3, 5)'; $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<SQL
            SELECT
                S.MatWealthSectionCode      "id",
                S.Cipher                    "cipher",
                S.Name                      "name",
                S.TaskNumbers               "taskNumbers",
                S.FormType                  "formType",
                CASE S.FormType
                    WHEN 0 THEN 'Материалы и МБП'
                    WHEN 1 THEN 'Основное'
                    WHEN 2 THEN 'Продукты питания'
                    WHEN 3 THEN 'Медикаменты'
                    WHEN 4 THEN 'Бланки строгой отчетности'
                    WHEN 5 THEN 'Льготные рецепты'
                END                         "formName"
            FROM MatWealthSection S
            WHERE S.SectionType IS NULL
                $sqlFormType
                AND S.EnterpriseCode = $mapEnterpriseCode;
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getMaxSectionCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select asa_trim(COALESCE((
     select IncStr(Cipher)
     from MatWealthSection
     where enterprisecode=$mapEnterpriseCode
     order by RIGHT(REPEAT( E' ' ,20) + asa_trim(Cipher), 20) desc LIMIT 1 ),  E'0'));
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCode = fetchValue($stmt, 'asa_trim'); return $maxCode; } public function getMaxGroupCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $sectionCode = isset($params->sectionCode) && isEmptyProperty($params, 'sectionCode') ? $params->sectionCode : 0; $sql = <<<"EOT"
select asa_trim(COALESCE((
     select IncStr(Cipher)
     from MatWealthGroup
     where matWealthSectionCode=$sectionCode
     order by RIGHT(REPEAT( E' ' ,20) + asa_trim(Cipher), 20) desc LIMIT 1 ),  E'0'));
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCode = fetchValue($stmt, 'asa_trim'); return $maxCode; } public function checkGroupDelete($params = null) { $db = $this->db ?: $this->db = connectDb(); $groupCode = isset($params->groupCode) && isEmptyProperty($params, 'groupCode') ? $params->groupCode : 0; $sql = <<<"EOT"
select
    MatWealthObjectCode "id"
     from MatWealthObject
    where matWealthGroupCode=$groupCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function checkSectionDelete($params = null) { $db = $this->db ?: $this->db = connectDb(); $sectionCode = isset($params->sectionCode) && isEmptyProperty($params, 'sectionCode') ? $params->sectionCode : 0; $sql = <<<"EOT"
select
    MatWealthObjectCode "id"
     from MatWealthObject
    where matWealthSectionCode=$sectionCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateWealthSections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MatWealthSection set
       Name = case when ? = 1 then ? else Name end,
       Cipher = case when ? = 1 then ? else Cipher end,
       TaskNumbers = case when ? = 1 then ? else TaskNumbers end,
       FormType = case when ? = 1 then ? else FormType end
where MatWealthSectionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), 1, 11, 1, 3, $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWealthSections($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "MatWealthSection", "MatWealthSectionCode", $records); } public function createWealthSections($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MatWealthSection (Name, Cipher, TaskNumbers, FormType, EnterpriseCode)
values (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'cipher'), ',50,504,55,516,40,515,11,510,13,511,', 3, getSessionValue('mapEnterpriseCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MatWealthSection'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readWealthGroups($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
 g.MatWealthGroupCode "id",
 g.Cipher "cipher",
 g.Name "name",
 g.MatWealthSectionCode "sectionCode"
 from
 MatWealthGroup AS g
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateWealthGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MatWealthGroup set
       Name = case when ? = 1 then ? else Name end,
       Cipher = case when ? = 1 then ? else Cipher end,
       MatWealthSectionCode = case when ? = 1 then ? else MatWealthSectionCode end
where MatWealthGroupCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'sectionCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWealthGroups($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, "MatWealthGroup", "MatWealthGroupCode", $records); } public function createWealthGroups($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MatWealthGroup (Name, Cipher, MatWealthSectionCode)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'cipher'), isEmptyProperty($row, 'sectionCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MatWealthGroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_wealthSections'); 