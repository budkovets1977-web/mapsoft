<?php
require_once(__DIR__ . '/../../init.php'); class map_docs_templates { const ERROR_COPYING = 'Не удалось скопировать шаблон'; private $db; public function readTemplates($params) { function accessFieldName($type) { switch ($type) { case 1: $res = "WebUserCode"; break; case 2: $res = "MedProfessionCode"; break; case 3: $res = "DepartmentCode"; break; default: $res = ""; break; } return $res; } $db = $this->db ?: $this->db = connectDb(); $templateType = isEmptyProperty($params, 'templateType', 0); $isTemplate = $templateType >= 0; $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $tableFieldCode = $tableName . 'Code'; $tableFieldParent = $tableName . 'CodeParent'; $type = abs($templateType); $isMap = isEmptyProperty($params, 'isMap', false); $isSimple = isEmptyProperty($params, 'simple', false); $condSelecting = isset($params->selecting) && isEmptyProperty($params, 'selecting') ? "and ST_.htmPageCode IS NOT NULL" : ""; if ($isSimple) { $condParent = " and st.htmPageCode is not null"; } else { $condParent = isset($params->node) && isEmptyProperty($params, 'node') ? "and COALESCE($tableFieldParent, 0) = $params->node" : "and $tableFieldParent IS NULL"; } $condSelectOne = isset($params->selectId) && isEmptyProperty($params, 'selectId') ? "and ST.$tableFieldCode =  $params->selectId" : ""; $selectFullName = $isTemplate ? '' : 'ST.FullName "fullName",'; $selectResultMask = 'ST.ResultMask "resultMask",'; $fieldType = $isTemplate ? 'ST.Type "type",' : '-ST.Type "type",'; $selectDicomModality = $isTemplate ? '' : 'ST.dicom_modality "dicomModality",'; $condAccess = ""; $condAccessT = ""; $condAccessGroup = ""; $condAccessGroupT = ""; $condAccessSelect = ""; $professionId = isEmptyProperty($params, 'professionId'); $departmentId = isEmptyProperty($params, 'departmentId'); $analyzerDriverId = isEmptyProperty($params, 'analyzerDriverId'); if ($analyzerDriverId) { $sql = <<<SQL
                SELECT
                    ST.MedStudyCode             "id",
                    NULL                        "parentId",
                    ST.Code                     "code",
                    ST.Name                     "name",
                    P.HtmPageCode               "pageId",
                    ST.TreePath                 "treePath",
                    ST.FullCode                 "fullCode",
                    ST.SortCode                 "sortCode",
                    -ST.Type                    "type",
                    ST.FullName                 "fullName",
                    ST.ResultMask               "resultMask",
                    ST.Dicom_modality           "dicomModality",
                    1                           "leaf",
                    0                           "childExist",
                    NULL                        "WebUserCode",          -- С заглавной буквы для совместимости с кодом ниже
                    NULL                        "MedProfessionCode",
                    NULL                        "DepartmentCode",
                    NULL                        "WebUserCode",
                    NULL                        "MedProfessionCode",
                    NULL                        "DepartmentCode"
                FROM MedStudy ST
                LEFT JOIN HtmPage P ON P.HtmPageCode = ST.HtmPageCode
                LEFT JOIN MedLabDriverTemplate M ON M.MedStudyCode = ST.MedStudyCode
                WHERE P.IsTemplate = 3 -- WEB-шаблон
                    AND M.MedLabDriverCode = ?
                SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $analyzerDriverId ]); return $stmt->fetchAll(); } if (isUndefinedProperty($params, 'access') && $params->access == true && $params->access != false) { if ($professionId && $departmentId) { $access = []; $access[] = (array(1, getSessionValue('user'), 0)); $access[] = (array(2, $professionId, 0)); $access[] = (array(3, $departmentId, 0)); } else { $rows = selectSql($db, 'select
                        medProfessionCode "professionId",
                        departmentCode "departmentId"
                    from medDoctor
                    where usercode = ?
                    union
                    select
                        null "professionId",
                        departmentCode "departmentId"
                    from buhMol
                    where userCode = ?', [getSessionValue('mapUserCode'), getSessionValue('mapUserCode')]); $access = []; $grp = 0; foreach ($rows as $row) { $access[] = (array(1, getSessionValue('user'), $grp)); $access[] = (array(2, $row['professionId'], $grp)); $access[] = (array(3, $row['departmentId'], $grp)); $grp++; } if (!$access) { $access[] = (array(1, getSessionValue('user'), 0)); } } } if (isset($access)) { $prev_grp = 0; foreach ($access as $value) { if ($value) { if (empty($condAccess)) { $condAccess = '('; } if (empty($condAccessT)) { $condAccessT = '('; } $grp = (int) $value[2]; if ($grp !== $prev_grp) { $condAccess .= $condAccessGroup . ') or ('; $condAccessT .= $condAccessGroupT . ') or ('; $condAccessGroup = ''; $condAccessGroupT = ''; } if (!empty($condAccessGroup)) { $condAccessGroup .= ' and '; } if (!empty($condAccessGroupT)) { $condAccessGroupT .= ' and '; } $tmpWhere = "and A." . accessFieldName($value[0]); $tmpWhere .= $value[1] ? " = " . $value[1] : " IS NULL"; if (!$isTemplate) { $condAccessGroup .= <<<"EOT"
    ((select A.medStudyAccessCode
          from medStudyAccess A
          where A.medStudyCode = ST.medStudyCode
            and A.TypeAccess = {$value[0]}  and (ST.MedStudyCodeParent IS NOT NULL OR (ST.MedStudyCodeParent IS NULL and ST.HtmPageCode IS NOT NULL))
          limit 1) IS NULL or
         (select A.medStudyAccessCode
          from medStudyAccess A
          where A.medStudyCode = ST.medStudyCode
            and A.TypeAccess = {$value[0]}
            $tmpWhere
          limit 1) IS NOT NULL)
EOT;
} else { $condAccessGroup .= <<<"EOT"
    ((select A.htmTemplateAccessCode
          from htmTemplateAccess A
          where A.htmTemplateCode = ST.htmTemplateCode
            and A.TypeAccess = {$value[0]}  and (ST.htmTemplateCodeParent IS NOT NULL OR ( ST.htmTemplateCodeParent IS NULL and ST.htmpagecode IS NOT NULL))
          limit 1) IS NULL or
         (select A.htmTemplateAccessCode
          from htmTemplateAccess A
          where A.htmTemplateCode = ST.htmTemplateCode
            and A.TypeAccess = {$value[0]}
            $tmpWhere
          limit 1) IS NOT NULL)
EOT;
} if (!$isTemplate) { $condAccessGroupT .= <<<"EOT"
    ((select A.medStudyAccessCode
          from medStudyAccess A
          where A.medStudyCode = ST_.medStudyCode
            and A.TypeAccess = {$value[0]}  and (ST_.MedStudyCodeParent IS NOT NULL OR (ST_.MedStudyCodeParent IS NULL and ST_.HtmPageCode IS NOT NULL))
          limit 1) IS NULL or
         (select A.medStudyAccessCode
          from medStudyAccess A
          where A.medStudyCode = ST_.medStudyCode
            and A.TypeAccess = {$value[0]}
            $tmpWhere
          limit 1) IS NOT NULL)
EOT;
} else { $condAccessGroupT .= <<<"EOT"
    ((select A.htmTemplateAccessCode
          from htmTemplateAccess A
          where A.htmTemplateCode = ST_.htmTemplateCode
            and A.TypeAccess = {$value[0]}  and (ST_.htmTemplateCodeParent IS NOT NULL OR ( ST_.htmTemplateCodeParent IS NULL and ST_.htmpagecode IS NOT NULL))
          limit 1) IS NULL or
         (select A.htmTemplateAccessCode
          from htmTemplateAccess A
          where A.htmTemplateCode = ST_.htmTemplateCode
            and A.TypeAccess = {$value[0]}
            $tmpWhere
          limit 1) IS NOT NULL)
EOT;
} $tmpName = '"'. accessFieldName($value[0]) . '"'; $tmpNameFull = "A." . accessFieldName($value[0]); if (!$isTemplate) { $condAccessSelect .= <<<"EOT"
,
  (
        SELECT
            $tmpNameFull
        FROM
            medStudyAccess A
        WHERE A.medStudyCode = ST.medStudyCode
        AND A.TypeAccess = {$value[0]}
          LIMIT 1
    ) $tmpName
EOT;
} else { $condAccessSelect .= <<<"EOT"
,
  (
        SELECT
            $tmpNameFull
        FROM
            htmtemplateaccess A
        WHERE
            A.htmtemplatecode = ST.htmtemplatecode
        AND A.TypeAccess = {$value[0]}
          LIMIT 1
    ) $tmpName
EOT;
} } if (!next($access)) { $condAccess .= $condAccessGroup . ')'; $condAccessT .= $condAccessGroupT . ')'; } $prev_grp = $grp; } } unset($value); if (!empty($condAccess)) { $condAccess = 'and (' . $condAccess . ')'; } if (!empty($condAccessT)) { $condAccessT = 'and (' . $condAccessT . ')'; } $sql = <<<"EOT"
select
       ST.$tableFieldCode "id",
       ST.$tableFieldParent "parentId",
       ST.Code "code",
       ST.Name "name",
       p.htmPageCode "pageId",
       ST.TreePath "treePath",
       ST.FullCode "fullCode",
       ST.SortCode "sortCode",
       $fieldType
       $selectFullName
       $selectResultMask
       $selectDicomModality
       case
           when exists(select 1
                         from $tableName ST_
                              left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
                         where ST_.TreePath LIKE ST.TreePath || '%'
                           and ST_.Type = $type
                           and ST_.$tableFieldCode <> ST.$tableFieldCode
                           and ((p_.IsTemplate = ?) or (p_.htmPageCode IS NULL))
                           $condSelecting
                           $condAccess
                       limit 1) then 0
           else 1
       end "leaf",
        case
    when exists(select 1
                from $tableName ST_
                     left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
                where ST_.TreePath LIKE ST.TreePath || '%'
                  and ST_.Type = $type
                  and ST_.$tableFieldCode <> ST.$tableFieldCode
                  and ((p_.IsTemplate = ?) or (p_.htmPageCode IS NULL))
                  $condSelecting
                  $condAccessT
              limit 1) then 1
    else 0
    end "childExist"/*,
    fn_htmgetfulldocname(st.htmpagecode) as "treePathName"*/
    $condAccessSelect
from $tableName ST
     left join htmPage p on p.htmPageCode = ST.htmPageCode
where ST.Type = $type
  $condParent
  $condSelectOne
  and ((p.IsTemplate = ?) or (p.htmPageCode IS NULL))
  and exists(select 1
             from $tableName ST_
                  left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
             where ST_.TreePath LIKE ST.TreePath || '%'
               and ((p_.IsTemplate = ?) or (p_.htmPageCode IS NULL))
               $condSelecting
               $condAccess
             limit 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $isMap ? 1 : 3, $isMap ? 1 : 3, $isMap ? 1 : 3, $isMap ? 1 : 3 ]); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass(); $item->id = $id = $row['id']; $item->parentId = $row['parentId']; $item->code = $row['code']; $item->name = $row['name']; $item->resultMask = $row['resultMask']; if (!$isTemplate) { $item->fullName = $row['fullName']; $item->dicomModality = $row['dicomModality']; } $item->pageId = $row['pageId']; $item->treePath = $row['treePath']; $item->fullCode = $row['fullCode']; $item->sortCode = $row['sortCode']; $item->type = $row['type']; $item->leaf = $row['leaf'] == 1; $item->childExist = $row['childExist'] == 1; if (isset($access)) { $item->webusercode = $row['WebUserCode']; $item->medprofessioncode = !empty($row['MedProfessionCode']) ? $row['MedProfessionCode'] : null; $item->departmentcode = !empty($row['DepartmentCode']) ? $row['DepartmentCode'] : null; } $items[$id] = $item; $root[] = $item; } foreach (array_keys($root) as $key) { if (!$root[$key]->leaf && !$root[$key]->childExist) { unset($root[$key]); } } sort($root); return $root; } public function pasteTemplate($params) { $db = $this->db ?: $this->db = connectDb(); $templateId = isEmptyProperty($params, 'templateId', 0); $parentId = isEmptyProperty($params, 'parentId'); $templateType = isEmptyProperty($params, 'templateType', 0); $isTemplate = $templateType >= 0; $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $tableFieldCode = $tableName . 'Code'; $tableFieldParent = $tableName . 'CodeParent'; $fieldType = $isTemplate ? 'ST.Type "type",' : '-ST.Type "type",'; $selectFullName = $isTemplate ? '' : 'ST.FullName "fullName",'; $selectResultMask = 'ST.ResultMask "resultMask",'; $cond_where = isset($params->parentId) && $parentId ? "and coalesce($tableFieldParent, 0) = $parentId" : "and $tableFieldParent IS NULL"; $db->beginTransaction(); try { $sql = <<<"EOT"
select
       $fieldType
       ST.Name "name",
       ST.Code "code",
       ST.htmPageCode "id",
       $selectFullName
       $selectResultMask
       COALESCE((select
                        ST1.$tableFieldCode
                 from $tableName ST1
                 where ST1.Name = ST.Name
                    $cond_where
                   and ST1.Code = ST.Code
                 limit 1), 0) "nameExists",
       (select MIN(sysNumberCode)
        from sysNumber
             left join $tableName ST2 on ST2.Name = ST.Name || ' ' || sysNumberCode::text
                                     $cond_where
        where ST2.$tableFieldCode IS NULL) "n"
from $tableName ST
where ST.$tableFieldCode = $templateId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $row = $stmt->fetch(); if ($row) { $insertF = $isTemplate ? "" : ", Fullname"; $insertV = $isTemplate ? "" : ", ?"; $name = $row['name']; if ($row['nameExists'] != 0) { $name .= ' ' . $row['n']; } $sql = <<<"EOT"
insert into $tableName
    (Type, $tableFieldParent, Code, Name $insertF)
values
    (?, ?, ?, ? $insertV)
EOT;
$stmt = $db->prepare($sql); $par = [ abs($templateType), $parentId, $row['code'], $name ]; if (!$isTemplate) { $par[] = $row['fullName']; } $stmt->execute($par); $newCode = $db->lastInsertId('seq_' . $tableName); if ($newCode) { $htmPageCode = $row['id']; if ($htmPageCode) { $sql = <<<"EOT"
select FN_htmPageCopy(?, $newCode, ?) "pageId"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $htmPageCode, $name ]); $row = $stmt->fetch(); $htmPageCode = $row['pageId']; if ($htmPageCode) { $sql = <<<"EOT"
update $tableName set
       htmPageCode = $htmPageCode
where $tableFieldCode = $newCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } } if ($isTemplate) { if (!$isTemplate) { $sql = <<<"EOT"
insert into medStudyAccess
    (medStudyAccessCode, TypeAccess, webUserCode, DepartmentCode, MedProfessionCode, ServiceCode, ServiceWealthSetCode, Value)
select
    $newCode, TypeAccess, webUserCode, DepartmentCode, MedProfessionCode, ServiceCode, ServiceWealthSetCode, Value
from medStudyAccess
where medStudyAccessCode = $templateId
EOT;
} else { $sql = <<<"EOT"
insert into htmTemplateAccess
    (htmTemplateCode, TypeAccess, webUserCode, DepartmentCode, MedProfessionCode, ServiceCode, ServiceWealthSetCode, Value)
select
    $newCode, TypeAccess, webUserCode, DepartmentCode, MedProfessionCode, ServiceCode, ServiceWealthSetCode, Value
from htmTemplateAccess
where htmTemplateCode = $templateId
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); } } else { throw new Exception(self::ERROR_COPYING); } } else { throw new Exception(self::ERROR_COPYING); } $db->commit(); $param = new stdClass(); $param->selectId = $newCode; $param->node = $parentId; $param->templateType = $templateType; return $this->readTemplates($param); } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollback(); } throw $e; } } public function moveTempalte($params) { $db = $this->db ?: $this->db = connectDb(); $templateId = isEmptyProperty($params, 'templateId', 0); $parentId = isEmptyProperty($params, 'parentId'); $templateType = isEmptyProperty($params, 'templateType', 0); $isTemplate = $templateType >= 0; $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $tableFieldCode = $tableName . 'Code'; $tableFieldParent = $tableName . 'CodeParent'; $fieldType = $isTemplate ? 'ST.Type "type",' : '-ST.Type "type",'; $selectFullName = $isTemplate ? '' : 'ST.FullName "fullName",'; $selectResultMask = 'ST.ResultMask "resultMask",'; $cond_where = isset($params->parentId) && $parentId ? "and coalesce($tableFieldParent, 0) = $parentId" : "and $tableFieldParent IS NULL"; $db->beginTransaction(); try { $sql = <<<"EOT"
select
       $fieldType
       ST.Name "name",
       ST.Code "code",
       ST.htmPageCode "id",
       $selectFullName
       $selectResultMask
       COALESCE((select
                        ST1.$tableFieldCode
                 from $tableName ST1
                 where ST1.Name = ST.Name
                    $cond_where
                   and ST1.Code = ST.Code
                 limit 1), 0) "nameExists",
       (select MIN(sysNumberCode)
        from sysNumber
             left join $tableName ST2 on ST2.Name = ST.Name || ' ' || sysNumberCode::text
                                     $cond_where
        where ST2.$tableFieldCode IS NULL) "n"
from $tableName ST
where ST.$tableFieldCode = $templateId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $row = $stmt->fetch(); $name = $row['name']; if ($row['nameExists'] != 0) { $name .= ' ' . $row['n']; } $sql = <<<"EOT"
update $tableName set
       $tableFieldParent = ?,
       name = ?
where $tableFieldCode = $templateId
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$parentId,$name]); $row = $stmt->fetch(); $db->commit(); $param = new stdClass(); $param->selectId = $templateId; $param->node = $parentId; $param->templateType = $templateType; return $this->readTemplates($param); } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollback(); } throw $e; } } public function deleteTemplates($records) { $db = $this->db ?: $this->db = connectDb(); $record = is_object($records) ? $records : $records[0]; $tableName = $record->type >= 0 ? 'htmTemplate' : 'medStudy'; $tableField = $tableName . 'Code'; $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from $tableName
where $tableField $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateTemplates($records) { $db = $this->db ?: $this->db = connectDb(); $record = is_object($records) ? $records : $records[0]; $isTemplate = $record->type >= 0; $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $tableFieldCode = $tableName . 'Code'; $tableFieldParent = $tableName . 'CodeParent'; $updFullName = $isTemplate ? '' : 'FullName = case when ? = 1 then ? else FullName end,'; $updResultMask = 'ResultMask = case when ? = 1 then ? else ResultMask end,'; $updDicomModality = $isTemplate ? '' : 'dicom_modality = case when ? = 1 then ? else dicom_modality end,'; $sql = <<<"EOT"
update $tableName set
       $tableFieldParent = case when ? = 1 then ? else $tableFieldParent end,
       Code = case when ? = 1 then ? else Code end,
       Name = case when ? = 1 then ? else Name end,
       $updResultMask
       $updFullName
       $updDicomModality
       htmPageCode = NULLIF(case when ? = 1 then ? else htmPageCode end, 0)
where $tableFieldCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $par = [ propertyExistsSQL($row, 'parentId'), isEmptyProperty($row, 'parentId'), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'resultMask'), isUndefinedProperty($row, 'resultMask') ]; if (!$isTemplate) { array_add($par, [ propertyExistsSQL($row, 'fullName'), isUndefinedProperty($row, 'fullName'), propertyExistsSQL($row, 'dicomModality'), isUndefinedProperty($row, 'dicomModality') ]); } array_add($par, [ propertyExistsSQL($row, 'pageId'), isEmptyProperty($row, 'pageId'), $row->id ]); $stmt->execute($par); $this->checkUniqueTemplate($db, $record, $tableName, $tableFieldCode); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createTemplates($records) { $db = $this->db ?: $this->db = connectDb(); $record = is_object($records) ? $records : $records[0]; $isTemplate = $record->type >= 0; $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $tableFieldParent = $tableName . 'CodeParent'; $codeField = $tableName . 'Code'; $insertF = $isTemplate ? "" : ", Fullname, dicom_modality"; $insertV = $isTemplate ? "" : ", ?, ?"; $sql = <<<"EOT"
insert into $tableName
    (Type, $tableFieldParent, Code, Name, ResultMask $insertF)
values
    (?, ?, ?, ?, ? $insertV)
returning $codeField
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $par = [ abs(isEmptyProperty($row, 'type')), isEmptyProperty($row, 'parentId'), isUndefinedProperty($row, 'code'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'resultMask') ]; if (!$isTemplate) { array_add($par, [ isUndefinedProperty($row, 'fullName'), isUndefinedProperty($row, 'dicomModality') ]); } $id = selectSqlValue($db, $sql, $par); $record->id = $id; $this->checkUniqueTemplate($db, $record, $tableName, $codeField); $ret[] = $rec = new stdClass; $rec->id = $id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } protected function checkUniqueTemplate ($db, $record, $tableName, $codeField) { if (propertyExistsSQL($record, 'name') || propertyExistsSQL($record, 'code')) { $sql = <<<"EOT"
SELECT
    MainT.name "name",
    MainT.code "code"
FROM $tableName MainT
WHERE $codeField = :id
AND EXISTS(
    SELECT
        1
    FROM $tableName OtherT
    WHERE OtherT.$codeField <> :id
      AND OtherT.type = MainT.type
      AND COALESCE(OtherT.name, '') = COALESCE(MainT.name, '')
      AND COALESCE(OtherT.fullCode, '') = COALESCE(MainT.fullCode, '')
)
EOT;
$result = selectSql($db, $sql, [':id' => $record->id]); if (count($result)) { $name = $result[0]['name']; $code = $result[0]['code']; throw new Error("Запись с кодом \"$code\" и наименованием \"$name\" уже существует."); } } } private function getNewDSSelectionData($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $doctorId = isEmptyProperty($params, 'doctorId'); $onDate = isEmptyProperty($params, 'onDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'onDate'))) : null; $examIds = isEmptyProperty($params, 'examIds'); $fieldScheduled = 'NULL "scheduled", '; $examIdsCondition = ''; if ($examIds) { $examIdsCondition = " OR MS.MedStudyCode IN ($examIds)"; } if ($patientId && $doctorId && $onDate) { $fieldScheduled = <<<EOT
                    CASE WHEN ((
                        SELECT 1
                        FROM MedLogStudy M
                        WHERE M.MedPatientCode = $patientId
                            AND M.MedDoctorCode = $doctorId
                            AND M.DirTime::DATE = $onDate
                            AND M.MedStudyCode = MS.MedStudyCode
                        LIMIT 1
                    ) IS NOT NULL $examIdsCondition)
                    THEN 1
                    ELSE 0
                END                 "scheduled",
EOT;
} $sql = <<<EOT
            SELECT
                MS.MedStudyCode     "medStudyId",
                MS.Code             "code",
                MS.Name             "name",
                MS.HtmPageCode      "pageId",
                MS.TreePath         "treePath",
                MS.FullCode         "fullCode",
                MS.SortCode         "sortCode",
                MS.Type             "type",
                MS.FullName         "fullName",
                $fieldScheduled
                1                   "leaf"
            FROM MedStudy MS
            WHERE EXISTS(
                    SELECT 1
                    FROM MedResearchROW T
                    WHERE T.MedStudyCode = MS.medStudyCode
                )
                AND EXISTS(
                    SELECT 1
                    FROM MedStudy _MS
                    LEFT JOIN HtmPage _P ON _P.HtmPageCode = _MS.HtmPageCode
                    WHERE _MS.TreePath LIKE MS.TreePath || '%'
                        AND ((_P.IsTemplate = 3) OR (_P.HtmPageCode IS NULL))
                        LIMIT 1
                )
                AND MS.Type IN (1, 2, 3)
            ORDER BY MS.Type, MS.Code, MS.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = []; $g = null; $groups = []; while ($row = $stmt->fetch()) { if (!array_key_exists((int)$row['type'], $groups)) { $root[] = $g = new stdClass(); $g->id = $row['type'] * -1; $g->parentid = 0; $g->code = ''; $g->type = $row['type']; $groups[(int)$row['type']] = $g->id; switch ((int)$g->type) { case 1: $g->name = 'Лабораторные исследования'; break; case 2: $g->name = 'Лучевые исследования'; break; case 3: $g->name = 'Функциональные исследования'; break; case 0: $g->name = 'Медицинские документы'; break; } $g->expanded = true; } $g->children[] = $item = new \stdClass(); $item->id = $id = $row['medStudyId']; $item->parentId = $groups[(int)$row['type']]; $item->code = $row['code']; $item->name = $row['scheduled'] ? '(НАЗНАЧЕНО) '. $row['name'] : $row['name']; $item->fullName = $row['fullName']; $item->pageId = $row['pageId']; $item->treePath = $row['treePath']; $item->fullCode = $row['fullCode']; $item->sortCode = $row['sortCode']; $item->type = $row['type']; $item->leaf = ($row['leaf'] === 1); $item->scheduled = $row['scheduled']; $item->checked = $row['scheduled'] ? null : false; $root[] = $item; } return $root; } public function readMultiTemplates($params) { $db = $this->db ?: $this->db = connectDb(); $newDSSelection = isEmptyProperty($params, 'newDSSelection'); if ($newDSSelection) { return $this->getNewDSSelectionData($params); } $typeFilterFlag = isset($params->typeFilter) ? $params->typeFilter : false; $extraFilterType = isset($params->extraFilterType) ? $params->extraFilterType : false; $ciszTemplatesArray = isset($params->ciszTemplates) ? (array)$params->ciszTemplates : []; $ciszTemplatesStr = implode(',', array_map('intval', $ciszTemplatesArray)); if ($typeFilterFlag) { $medStudyType = isEmptyProperty($params, 'medStudyType'); $medStudyType = $medStudyType ?? 3; $typeFilter = "AND ST.Type = $medStudyType"; switch ($extraFilterType) { case 1: $typeFilter .= " AND ST.MedStudyCode NOT IN 
                                (SELECT 
                                    RI.MedStudyCode
                                FROM MedStudyReport R
                                JOIN MedStudyReportIn RI ON RI.MedStudyReportCode = R.MedStudyReportCode
                                WHERE R.Type BETWEEN 101 AND 108)"; break; default: $typeFilter .= " AND ST.MedStudyCode NOT IN (SELECT MedStudyCode FROM CiszStudyList)"; break; } if (!empty($ciszTemplatesStr)) { $typeFilter .= " AND ST.MedStudyCode NOT IN ($ciszTemplatesStr)"; } } else { $typeFilter = ""; } $typeTemplate = isEmptyProperty($params, 'isMap'); if ($typeTemplate) { $typeTemplate = "1"; } else { $typeTemplate = "3"; } $sql = <<<"EOT"
with recursive tree (medStudyCode, medStudyCodeParent, code, name, pageId, treePath, fullCode, sortCode, type, fullName, leaf) AS
    (
        select
        ST.medStudyCode  "medStudyCode",
        ST.medStudyCodeParent "medStudyCodeParent",
        ST.Code "code",
        ST.Name "name",
        p.htmPageCode "pageId",
        ST.TreePath "treePath",
        ST.FullCode "fullCode",
        ST.SortCode "sortCode",
        ST.Type "type",
        ST.FullName "fullName",
        case
            when exists(
                select 1
                from medStudy  ST_
                left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
                where
                    ST_.TreePath LIKE ST.TreePath || '%'
                    and ST_.Type = ST.Type
                    and ST_.medStudyCode <> ST.medStudyCode
                    and ((p_.IsTemplate = $typeTemplate) or (p_.htmPageCode IS NULL))
                    limit 1) then 0
            else 1
        end "leaf"
from medStudy  ST
left join htmPage p on p.htmPageCode = ST.htmPageCode
where
    ST.medStudyCodeParent IS NULL
    and ((p.IsTemplate = $typeTemplate) or (p.htmPageCode IS NULL))
    and exists(
        select 1
        from medStudy ST_
        left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
        where
            ST_.TreePath LIKE ST.TreePath || '%'
            and ((p_.IsTemplate = $typeTemplate) or (p_.htmPageCode IS NULL))
            limit 1)
            $typeFilter

union all

    select
        ST.medStudyCode  "medStudyCode",
        ST.medStudyCodeParent "medStudyCodeParent",
        ST.Code "code",
        ST.Name "name",
        p.htmPageCode "pageId",
        ST.TreePath "treePath",
        ST.FullCode "fullCode",
        ST.SortCode "sortCode",
        ST.Type "type",
        ST.FullName "fullName",
        case
            when exists(
                select 1
                from medStudy  ST_
                left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
                where
                    ST_.TreePath LIKE ST.TreePath || '%'
                    and ST_.Type = ST.Type
                    and ST_.medStudyCode <> ST.medStudyCode
                    and ((p_.IsTemplate = $typeTemplate) or (p_.htmPageCode IS NULL))
                    limit 1) then 0
            else 1
        end "leaf"
from medStudy  ST
left join htmPage p on p.htmPageCode = ST.htmPageCode and ((p.IsTemplate = $typeTemplate) or (p.htmPageCode IS NULL))
inner join tree tr on (ST.medStudyCodeParent = tr.medStudyCode)
where
    exists(
        select 1
        from medStudy ST_
        left join htmPage p_ on p_.htmPageCode = ST_.htmPageCode
        where
            ST_.TreePath LIKE ST.TreePath || '%'
            and ((p_.IsTemplate = $typeTemplate) or (p_.htmPageCode IS NULL))
            limit 1)
            $typeFilter
    )

select
    medStudyCode "medStudyCode",
    medStudyCodeParent "medStudyCodeParent",
    code "code",
    name "name",
    pageId "pageId",
    treePath "treePath",
    fullCode "fullCode",
    sortCode "sortCode",
    type "type",
    fullName "fullName",
    leaf "leaf"
from tree tr
order by medStudyCodeParent, name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = []; $g = null; $groups = []; while ($row = $stmt->fetch()) { if (!array_key_exists((int) $row['type'], $groups)) { $root[] = $g = new stdClass(); $g->id = $row['type'] * -1; $g->parentid = 0; $g->code = ''; $g->type = $row['type']; $groups[(int) $row['type']] = $g->id; switch ((int) $g->type) { case 1: $g->name = 'Лабораторные исследования'; break; case 2: $g->name = 'Лучевые исследования'; break; case 3: $g->name = 'Функциональные исследования'; break; case 0: $g->name = 'Медицинские документы'; break; } $g->expanded = false; } $g->children[] = $item = new \stdClass(); $item->id = $id = $row['medStudyCode']; $item->parentId = $row['medStudyCodeParent'] !== null ? $row['medStudyCodeParent'] : $groups[(int) $row['type']]; $item->code = $row['code']; $item->name = $row['name']; $item->fullName = $row['fullName']; $item->pageId = $row['pageId']; $item->treePath = $row['treePath']; $item->fullCode = $row['fullCode']; $item->sortCode = $row['sortCode']; $item->type = $row['type']; $item->leaf = ($row['leaf'] == 1); $item->checked = $item->pageId ? false : null; $root[] = $item; } return $root; } public function readUsers($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isset($params->type) && isEmptyProperty($params, 'type') ? $params->type : "null"; $isTemplate = $type > 0; if ($isTemplate) { $sql = <<<"EOT"
SELECT
    hta.HtmTemplateAccessCode "id",
    wu.WebUserCode "webUserCode",
    wu.Nick "name",
    hta.HtmTemplateCode "htmCode"
    FROM HtmTemplateAccess hta
    left outer join WebUser wu on wu.WebUserCode = hta.WebUserCode
    WHERE hta.TypeAccess = 1 and wu.webUserCode !=null
EOT;
} else { $sql = <<<"EOT"
SELECT
    hta.medStudyAccessCode "id",
    wu.WebUserCode "webUserCode",
    wu.Nick "name",
    hta.medStudyCode "htmCode"
    FROM medStudyAccess hta
    left outer join WebUser wu on wu.WebUserCode = hta.WebUserCode
    WHERE hta.TypeAccess = 1 and wu.webUserCode !=null
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readSpeciality($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isset($params->type) && isEmptyProperty($params, 'type') ? $params->type : "null"; $isTemplate = $type > 0; if ($isTemplate) { $sql = <<<"EOT"
SELECT
    hta.HtmTemplateAccessCode "id",
    MP.MedProfessionCode "professionCode",
    MP.Name "name",
    hta.HtmTemplateCode "htmCode"
    FROM HtmTemplateAccess hta
    left outer join MedProfession MP on MP.MedProfessionCode = hta.MedProfessionCode
    WHERE hta.TypeAccess = 2
EOT;
} else { $sql = <<<"EOT"
SELECT
    hta.medStudyAccessCode "id",
    MP.MedProfessionCode "professionCode",
    MP.Name "name",
    hta.medStudyCode "htmCode"
    FROM medStudyAccess hta
    left outer join MedProfession MP on MP.MedProfessionCode = hta.MedProfessionCode
    WHERE hta.TypeAccess = 2
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readDepartments($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isset($params->type) && isEmptyProperty($params, 'type') ? $params->type : "null"; $isTemplate = $type > 0; if ($isTemplate) { $sql = <<<"EOT"
SELECT
    hta.HtmTemplateAccessCode "id",
    D.DepartmentCode "departmentCode",
    D.Name "name",
    hta.HtmTemplateCode "htmCode"
    FROM HtmTemplateAccess hta
    left outer join Department D on D.DepartmentCode = hta.DepartmentCode
    WHERE hta.TypeAccess = 3
EOT;
} else { $sql = <<<"EOT"
SELECT
    hta.medStudyAccessCode "id",
    D.DepartmentCode "departmentCode",
    D.Name "name",
    hta.medStudyCode "htmCode"
    FROM medStudyAccess hta
    left outer join Department D on D.DepartmentCode = hta.DepartmentCode
    WHERE hta.TypeAccess = 3
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function createUsers($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
insert into HtmTemplateAccess (htmTemplateCode,typeAccess,webUserCode)
values (?, 1, ?)
       RETURNING HtmTemplateAccessCode AS id
EOT;
} else { $sql = <<<"EOT"
insert into medStudyAccess (medStudyCode,typeAccess,webUserCode)
values (?, 1, ?)
       RETURNING medStudyAccessCode AS id
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($row, 'htmCode'), $row->webUserCode ]); $log = $stmt->fetch(); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $log['id']; } public function updateUsers($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
update HtmTemplateAccess set
       WebUserCode = case when ? = 1 then ? else WebUserCode end
where HtmTemplateAccessCode = ?
EOT;
} else { $sql = <<<"EOT"
update medStudyAccess set
       WebUserCode = case when ? = 1 then ? else WebUserCode end
where medStudyAccessCode = ?
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($row, 'webUserCode'), $row->webUserCode, $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createSpeciality($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
insert into HtmTemplateAccess (htmTemplateCode,typeAccess,medProfessionCode)
values (?, 2, ?)
       RETURNING HtmTemplateAccessCode AS id
EOT;
} else { $sql = <<<"EOT"
insert into medStudyAccess (medStudyCode,typeAccess,medProfessionCode)
values (?, 2, ?)
       RETURNING medStudyAccessCode AS id
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($row, 'htmCode'), isEmptyProperty($row, 'professionCode'), ]); $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $rec; } public function updateSpeciality($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
update HtmTemplateAccess set
       MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end
where HtmTemplateAccessCode = ?
EOT;
} else { $sql = <<<"EOT"
update medStudyAccess set
       MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end
where medStudyAccessCode = ?
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($row, 'professionCode'), isEmptyProperty($row, 'professionCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createDepartments($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
insert into HtmTemplateAccess (htmTemplateCode,typeAccess,departmentCode)
values (?, 3, ?)
       RETURNING HtmTemplateAccessCode AS id
EOT;
} else { $sql = <<<"EOT"
insert into medStudyAccess (medStudyCode,typeAccess,departmentCode)
values (?, 3, ?)
       RETURNING medStudyAccessCode AS id
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($row, 'htmCode'), isEmptyProperty($row, 'departmentCode'), ]); $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $rec; } public function updateDepartments($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isTemplate = isEmptyProperty($row, 'type') >= 0; if ($isTemplate) { $sql = <<<"EOT"
update HtmTemplateAccess set
       DepartmentCode = case when ? = 1 then ? else DepartmentCode end
where HtmTemplateAccessCode = ?
EOT;
} else { $sql = <<<"EOT"
update medStudyAccess set
       DepartmentCode = case when ? = 1 then ? else DepartmentCode end
where medStudyAccessCode = ?
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($row, 'departmentCode'), isEmptyProperty($row, 'departmentCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteUsers($records) { $db = $this->db ?: $this->db = connectDb(); $isTemplate = isEmptyProperty($records, 'templateType') >= 0; if ($isTemplate) { return deleteSql($db, 'HtmTemplateAccess', 'HtmTemplateAccessCode', $records); } else { return deleteSql($db, 'MedStudyAccess', 'MedStudyAccessCode', $records); } } public function deleteSpeciality($records) { $db = $this->db ?: $this->db = connectDb(); $isTemplate = isEmptyProperty($records, 'templateType') >= 0; if ($isTemplate) { return deleteSql($db, 'HtmTemplateAccess', 'HtmTemplateAccessCode', $records); } else { return deleteSql($db, 'MedStudyAccess', 'MedStudyAccessCode', $records); } } public function deleteDepartments($records) { $db = $this->db ?: $this->db = connectDb(); $isTemplate = isEmptyProperty($records, 'templateType') >= 0; if ($isTemplate) { return deleteSql($db, 'HtmTemplateAccess', 'HtmTemplateAccessCode', $records); } else { return deleteSql($db, 'MedStudyAccess', 'MedStudyAccessCode', $records); } } public function selectTemplates($params) { $db = $this->db ?: $this->db = connectDb(); $type = isEmptyProperty($params, 'type'); if (!isset($type)) { throw new Exception('Не передан тип шаблона'); } $isTemplate = $type > 0; $type = abs($type); $tableName = $isTemplate ? 'htmTemplate' : 'medStudy'; $showAll = isEmptyProperty($params, 'showAll') ? 0 : 3; $sql = <<<"EOT"
with recursive __TREE(id, code, name, sortCode, parentId, pageId, treePath, depth) as (
select
       TBL.{$tableName}Code "id",
       TBL.code "code",
       TBL.name "name",
       TBL.sortcode "sortCode",
       TBL.{$tableName}CodeParent "parentId",
       TBL.htmPageCode "pageId",
       TBL.treePath "treePath",
       0
from {$tableName} TBL
where TBL.{$tableName}CodeParent is null
  and TBL.Type = {$type}
union all
select
       TBL.{$tableName}Code "id",
       TBL.code "code",
       TBL.name "name",
       TBL.sortcode "sortCode",
       TBL.{$tableName}CodeParent "parentId",
       TBL.htmPageCode "pageId",
       TBL.treePath "treePath",
       __T.depth + 1 "depth"
from {$tableName} TBL
     inner join __TREE __T on __T."id" = TBL.{$tableName}CodeParent
where TBL.Type = {$type}
)

select __T."id",
       __T."code",
       __T."name",
       __T."depth",
       __T."pageid" "pageId",
       HP.isTemplate "isTemplate"
from __TREE __T
     left join htmPageT HP on HP.htmPageCode = __T."pageid"
where (0 = {$showAll} or HP.IsTemplate = {$showAll} or HP.htmPageCode is null)
order by __T."treepath"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readPmedServices($params) { $db = $this->db ?: $this->db = connectDb(); $templateId = (int) isEmptyProperty($params, 'templateId'); $sql = <<<"EOT"
select
    tpl.pmsTemplatePriceListCode as "id",
    tpl.htmTemplateCode as "templateId",
    tpl.pmsPriceListCode as "priceListId",
    pl.SortCode as "sortCode",
    pl.FullCode as "fullCode",
    pl.Name as "name",
    tpl.Amount as "amount",
    1 as "rowLevel",
    null as "wealthId",
    null as "templatePriceListWealthId"
from pmsTemplatePriceList as tpl
join pmsPriceList as pl on pl.pmsPriceListCode = tpl.pmsPriceListCode
where tpl.htmTemplateCode = :templateId
    and pl.Active = 1
union all
select
    null as "id",
    tpl.htmTemplateCode as "templateId",
    tpl.pmsPriceListCode as "priceListId",
    pl.SortCode || '1' as "sortCode",
    null as "fullCode",
    fn_pmsMatWealthName(tplw.MatWealthCode, 3) as "name",
    tplw.Amount as "amount",
    2 as "rowLevel",
    tplw.matWealthCode as "wealthId",
    tplw.pmsTemplatePriceListWealthCode as "templatePriceListWealthId"
from pmsTemplatePriceListWealth as tplw
join pmsTemplatePriceList as tpl on tpl.pmsTemplatePriceListCode = tplw.pmsTemplatePriceListCode
join pmsPriceList as pl on pl.pmsPriceListCode = tpl.pmsPriceListCode
where tpl.htmTemplateCode = :templateId
    and pl.Active = 1
    and tplw.Kind = 1
order by 4, 6
EOT;
return selectSql($db, $sql, ['templateId' => $templateId]); } public function createPmedServices ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
insert into pmsTemplatePriceList (htmTemplateCode, pmsPriceListCode, Amount)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $sqlWealth = <<<"EOT"
insert into pmsTemplatePriceListWealth (pmsTemplatePriceListCode, MatWealthCode, Kind, Amount)
values (?, ?, 1, ?)
EOT;
$stmtWealth = $db->prepare($sqlWealth); $id = 0; $db->beginTransaction(); try { foreach ($records as $row) { if ($row->rowLevel == 1) { $stmt->execute([ isEmptyProperty($row, 'templateId'), isEmptyProperty($row, 'priceListId'), isEmptyProperty($row, 'amount') ]); $id = $db->lastInsertId('seq_pmsTemplatePriceList'); } else { if ($id > 0) { $stmtWealth->execute([ $id, isEmptyProperty($row, 'wealthId'), isEmptyProperty($row, 'amount') ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $id; } public function deletePmedServices ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
delete from pmsTemplatePriceList
where pmsTemplatePriceListCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlWealth = <<<"EOT"
delete from pmsTemplatePriceListWealth
where pmsTemplatePriceListWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row) { if ($row->rowLevel == 1) { $stmt->execute([$row->id]); } else { $stmtWealth->execute([$row->templatePriceListWealthId]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function checkTemplateAccess($params) { $db = $this->db ?: $this->db = connectDb(); $templateId = isEmptyProperty($params, 'templateId'); $professionId = isEmptyProperty($params, 'professionId'); $departmentId = isEmptyProperty($params, 'departmentId'); $userId = getSessionValue('user'); if (!$templateId) { return false; } $sql = <<<"EOT"
        SELECT COUNT(*) as count
        FROM HtmTemplateAccess A
        WHERE A.HtmTemplateCode = ?
        EOT;
$result = selectSql($db, $sql, [$templateId]); $hasAccessRecords = $result && $result[0]['count'] > 0; if (!$hasAccessRecords) { return true; } $hasUserAccess = false; $sql = <<<"EOT"
        SELECT 1
        FROM HtmTemplateAccess A
        WHERE A.HtmTemplateCode = ? AND A.TypeAccess = 1 AND A.WebUserCode = ?
        LIMIT 1
        EOT;
$hasUserAccessResult = selectSql($db, $sql, [$templateId, $userId]); $hasUserAccess = !empty($hasUserAccessResult); $hasProfessionAccess = false; if ($professionId) { $sql = <<<"EOT"
            SELECT 1
            FROM HtmTemplateAccess A
            WHERE A.HtmTemplateCode = ? AND A.TypeAccess = 2 AND A.MedProfessionCode = ?
            LIMIT 1
            EOT;
$hasProfessionAccessResult = selectSql($db, $sql, [$templateId, $professionId]); $hasProfessionAccess = !empty($hasProfessionAccessResult); } $hasDepartmentAccess = false; if ($departmentId) { $sql = <<<"EOT"
            SELECT 1
            FROM htmTemplateAccess A
            WHERE A.HtmTemplateCode = ? AND A.TypeAccess = 3 AND A.DepartmentCode = ?
            LIMIT 1
            EOT;
$hasDepartmentAccessResult = selectSql($db, $sql, [$templateId, $departmentId]); $hasDepartmentAccess = !empty($hasDepartmentAccessResult); } return $hasUserAccess || $hasProfessionAccess || $hasDepartmentAccess; } public function readTemplateTypes () { $db = $this->db ?: $this->db = connectDb(); return selectSql($db, <<<"EOT"
SELECT
    Type "type",
    SourceTable "sourceTable",
    ShortName "name"
FROM FN_htmGetTypes()
EOT
); } } handleJsonRpc('map_docs_templates');