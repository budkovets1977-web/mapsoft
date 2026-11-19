<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/wealthGroups.php'); resumeJsonRpc(); class pmed_common_wealths { private $db; private function getSectionId() { $groupClass = new pmed_common_wealthGroups; return $groupClass->checkWealthSection(); } public function readWealths ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sectionId = $this->getSectionId(); $groupIdExist = isset($params) && property_exists($params, 'groupId') ? 1 : 0; $groupId = (int) isEmptyProperty($params, 'groupId'); $search = isEmptyProperty($params, 'search', ''); $active = isEmptyProperty($params, 'active'); $objectId = (int) isEmptyProperty($params, 'objectId'); $rowLevel = (int) isEmptyProperty($params, 'rowLevel'); $wealthId = (int) isEmptyProperty($params, 'wealthId'); $sqlWhereSearch = ""; if ($search) { $searchArr = safe_explode(" ", $search); if ($searchArr) { foreach ($searchArr as $v) { if (!empty(trim($v))) { $sqlWhereSearch = glueStrings($sqlWhereSearch, trim($v), ":* & "); } } $sqlWhereSearch = $sqlWhereSearch.":*"; $sqlWhereSearch = "and (w.TextSearch @@ to_tsquery('{$sqlWhereSearch}')". "   or w.TextSearch @@ to_tsquery('simple', '{$sqlWhereSearch}') )"; } } $sqlWhereObjectSearch = ""; if ($sqlWhereSearch) { $sqlWhereObjectSearch = <<<"EOT"
and exists (
    select 1
    from MatWealth as w
    where w.MatWealthObjectCode = wo.MatWealthObjectCode
        and coalesce(w.Active, 0) = :active
    $sqlWhereSearch
    limit 1
)
EOT;
} $sqlFieldObjectActive = "wo.Active"; if (!$active) { $sqlFieldObjectActive = <<<"EOT"
case (exists (
        select 1
        from MatWealth as w
        where w.MatWealthObjectCode = wo.MatWealthObjectCode
            and w.Active = null
            $sqlWhereSearch
        limit 1 ))
    when true then null
    else wo.Active
end
EOT;
} $sql = <<<"EOT"
select
    1 as "rowLevel",
    wo.MatWealthObjectCode as "wealthObjectId",
    null as "wealthId",
    wo.MatwealthSectionCode as "sectionId",
    wo.MatWealthGroupCode as "groupId",
    wo.Name as "name",
    wo.SecondName as "secondName",
    $sqlFieldObjectActive as "active",
    wo.MatWealthMeasureCode as "measureId",
    wm1.Name as "measureName",
    wo.DoseSize as "doseSize",
    wo.MatWealthMeasureDoseSizeCode as "measureDoseSizeId",
    wm2.Name as "measureDoseSizeName",
    null as "commercialName",
    null as "feature",
    null as "manufacturer",
    null as "manufacturerCountryId",
    null as "manufacturerCountryName",
    null as "barcode",
    wo.Active as "realActive",
    fn_pmsMatWealthName((select MatWealthCode from MatWealth where MatWealthObjectCode = wo.MatWealthObjectCode limit 1), 1) as "displayName",
    fn_pmsMatWealthName((select MatWealthCode from MatWealth where MatWealthObjectCode = wo.MatWealthObjectCode limit 1), 3) as "displayFullName",
    null as "displayObjectName",
    (select count(*)
     from MatWealth
     where MatWealthObjectCode = wo.MatWealthObjectCode
        and coalesce(Active, 0) = :active) as "childCount"
from MatWealthObject as wo
left join MatWealthMeasure as wm1 on wm1.MatWealthMeasureCode = wo.MatWealthMeasureCode
left join MatWealthMeasure as wm2 on wm2.MatWealthMeasureCode = wo.MatWealthMeasureDoseSizeCode
where wo.MatwealthSectionCode = :sectionId
    and (
        (:groupIdExist = 1 and coalesce(wo.MatWealthGroupCode, 0) = coalesce(:groupId, 0))
        or :groupIdExist = 0
    )
    and coalesce($sqlFieldObjectActive, 0) = :active
    and :objectId = 0
    and (:rowLevel = 0 or :rowLevel = 1)
    $sqlWhereObjectSearch

union all

select
    2 as "rowLevel",
    w.MatWealthObjectCode as "wealthObjectId",
    w.MatWealthCode as "wealthId",
    wo.MatwealthSectionCode as "sectionId",
    wo.MatWealthGroupCode as "groupId",
    wo.Name as "name",
    wo.SecondName as "secondName",
    w.Active as "active",
    wo.MatWealthMeasureCode as "measureId",
    wm1.Name as "measureName",
    wo.DoseSize as "doseSize",
    wo.MatWealthMeasureDoseSizeCode as "measureDoseSizeId",
    wm2.Name as "measureDoseSizeName",
    w.CommercialName as "commercialName",
    w.Feature as "feature",
    wm.Manufacturer as "manufacturer",
    wm.CountryCode as "manufacturerCountryId",
    c.Name as "manufacturerCountryName",
    w.Barcode as "barcode",
    w.Active as "realActive",
    fn_pmsMatWealthName(w.MatWealthCode, 2) as "displayName",
    fn_pmsMatWealthName(w.MatWealthCode, 3) as "displayFullName",
    fn_pmsMatWealthName(w.MatWealthCode, 1) as "displayObjectName",
    (select count(*)
     from MatWealth
     where MatWealthObjectCode = w.MatWealthObjectCode
        and coalesce(Active, 0) = :active) as "childCount"
from MatWealth as w
join MatWealthObject as wo on wo.MatWealthObjectCode = w.MatWealthObjectCode
left join MatWealthManufacturer as wm on wm.MatWealthCode = w.MatWealthCode
left join Country as c on c.CountryCode = wm.CountryCode
left join MatWealthMeasure as wm1 on wm1.MatWealthMeasureCode = wo.MatWealthMeasureCode
left join MatWealthMeasure as wm2 on wm2.MatWealthMeasureCode = wo.MatWealthMeasureDoseSizeCode
where wo.MatwealthSectionCode = :sectionId
    and (
        (:groupIdExist = 1 and coalesce(wo.MatWealthGroupCode, 0) = coalesce(:groupId, 0))
        or :groupIdExist = 0
    )
    and coalesce(w.Active, 0) = :active
    and (w.MatWealthObjectCode = :objectId or :objectId = 0)
    and (:rowLevel = 0 or :rowLevel = 2)
    and (:wealthId = 0 or w.MatWealthCode = :wealthId)
    $sqlWhereSearch
order by "name", "secondName", "measureName", "doseSize", "measureDoseSizeName", "rowLevel", "commercialName", "manufacturerCountryName", "manufacturer", "barcode"
EOT;
return selectSql($db, $sql, [ "sectionId" => $sectionId, "groupId" => $groupId, "active" => (int) $active, "objectId" => $objectId, "groupIdExist" => $groupIdExist, "rowLevel" => $rowLevel, "wealthId" => $wealthId ]); } public function createWealths ($records) { $db = $this->db ?: $this->db = connectDb(); $sectionId = $this->getSectionId(); if (is_object($records)) { $records = [$records]; } $sqlObject = <<<"EOT"
insert into MatWealthObject (
    MatWealthSectionCode, MatWealthGroupCode, Name,
    SecondName, Active, MatWealthMeasureCode,
    DoseSize, MatWealthMeasureDoseSizeCode
) values (
    ?, ?, ?,
    ?, ?, ?,
    ?, ?
)
EOT;
$stmtObject = $db->prepare($sqlObject); $sqlWealth = <<<"EOT"
insert into MatWealth (
    MatWealthObjectCode, Active, CommercialName,
    Feature, Barcode
) values (
    ?, ?, ?,
    ?, ?
)
EOT;
$stmtWealth = $db->prepare($sqlWealth); $ret = new stdClass(); $db->beginTransaction(); try { foreach ($records as $row) { $objectId = 0; if ($row->rowLevel == 1) { $this->checkExistObject($row); $stmtObject->execute([ $sectionId, isEmptyProperty($row, 'groupId'), isEmptyProperty($row, 'name'), isEmptyProperty($row, 'secondName'), isEmptyProperty($row, 'active'), isEmptyProperty($row, 'measureId'), isEmptyProperty($row, 'doseSize'), isEmptyProperty($row, 'measureDoseSizeId') ]); $objectId = $db->lastInsertId('seq_MatWealthObject'); $ret->wealthObjectId = $objectId; } if ($row->rowLevel == 2 || propertyExistsSQL($row, 'commercialName')) { $objectId = isEmptyProperty($row, 'wealthObjectId', $objectId); if (empty($objectId)) { continue; } $this->checkExistWealth($row); $stmtWealth->execute([ $objectId, isEmptyProperty($row, 'active'), isEmptyProperty($row, 'commercialName'), isEmptyProperty($row, 'feature'), isEmptyProperty($row, 'barcode') ]); $wealthId = $db->lastInsertId('seq_MatWealth'); $ret->wealthId = $wealthId; if (propertyExistsSQL($row, 'manufacturerCountryId') || propertyExistsSQL($row, 'manufacturer')) { $this->checkManufacturer($wealthId, $row); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateWealths ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sqlObject = <<<"EOT"
update MatWealthObject set
    Name = case when ? then ? else Name end,
    SecondName = case when ? then ? else SecondName end,
    Active = case when ? then ? else Active end,
    MatWealthMeasureCode = case when ? then ? else MatWealthMeasureCode end,
    DoseSize = case when ? then ? else DoseSize end,
    MatWealthMeasureDoseSizeCode = case when ? then ? else MatWealthMeasureDoseSizeCode end,
    MatWealthGroupCode = case when ? then ? else MatWealthGroupCode end
where MatWealthObjectCode = ?
EOT;
$stmtObject = $db->prepare($sqlObject); $sqlWealth = <<<"EOT"
update MatWealth set
    Active = case when ? then ? else Active end,
    CommercialName = case when ? then ? else CommercialName end,
    Feature = case when ? then ? else Feature end,
    Barcode = case when ? then ? else Barcode end,
    MatWealthObjectCode = case when ? then ? else MatWealthObjectCode end
where MatWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row) { if ($row->rowLevel == 1) { $this->checkExistObject($row); $stmtObject->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'secondName'), isEmptyProperty($row, 'secondName'), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'measureId'), isEmptyProperty($row, 'measureId'), propertyExistsSQL($row, 'doseSize'), isEmptyProperty($row, 'doseSize'), propertyExistsSQL($row, 'measureDoseSizeId'), isEmptyProperty($row, 'measureDoseSizeId'), propertyExistsSQL($row, 'groupId'), isEmptyProperty($row, 'groupId'), $row->wealthObjectId ]); } else { $this->checkExistWealth($row); $stmtWealth->execute([ propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'commercialName'), isUndefinedProperty($row, 'commercialName'), propertyExistsSQL($row, 'feature'), isEmptyProperty($row, 'feature'), propertyExistsSQL($row, 'barcode'), isEmptyProperty($row, 'barcode'), propertyExistsSQL($row, 'wealthObjectId'), isEmptyProperty($row, 'wealthObjectId'), $row->wealthId ]); if (propertyExistsSQL($row, 'manufacturerCountryId') || propertyExistsSQL($row, 'manufacturer')) { $this->checkManufacturer($row->wealthId, $row); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteWealths ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sqlObject = <<<"EOT"
delete from MatWealthObject
where MatWealthObjectCode = ?
EOT;
$stmtObject = $db->prepare($sqlObject); $sqlWealth = <<<"EOT"
delete from MatWealth
where MatWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row) { if ($row->rowLevel == 1) { $stmtObject->execute([$row->wealthObjectId]); } else { $stmtWealth->execute([$row->wealthId]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function checkManufacturer($wealthId, $row) { $db = $this->db ?: $this->db = connectDb(); if (!isEmptyProperty($row, 'manufacturerCountryId') && !isEmptyProperty($row, 'manufacturer')) { executeSql($db, "delete from MatWealthManufacturer where MatWealthCode = ?", [$wealthId]); return; } $sql = <<<"EOT"
select MatWealthManufacturerCode
from MatWealthManufacturer
where MatWealthCode = ?
EOT;
$id = selectSqlValue($db, $sql, [$wealthId]); if (!$id) { executeSql($db, "insert into MatWealthManufacturer (MatWealthCode, CountryCode, Manufacturer) values (?, ?, ?)", [$wealthId, isEmptyProperty($row, 'manufacturerCountryId'), isEmptyProperty($row, 'manufacturer')] ); } else { executeSql($db, "update MatWealthManufacturer set ". " CountryCode = case when ? then ? else CountryCode end, ". " Manufacturer = case when ? then ? else Manufacturer end ". "where MatWealthCode = ?", [ propertyExistsSQL($row, 'manufacturerCountryId'), isEmptyProperty($row, 'manufacturerCountryId'), propertyExistsSQL($row, 'manufacturer'), isEmptyProperty($row, 'manufacturer'), $wealthId ] ); } } public function checkExistObject($row, $return = false) { $db = $this->db ?: $this->db = connectDb(); $sectionId = $this->getSectionId(); $name = isEmptyProperty($row, 'secondName', isEmptyProperty($row, 'name')); $sql = <<<"EOT"
select MatWealthObjectCode
from MatWealthObject
where MatWealthSectionCode = ?
    and coalesce(MatWealthGroupCode, 0) = coalesce(?, 0)
    and MatWealthObjectCode <> coalesce(?, 0)
    and coalesce(Name, '') = coalesce(?, '')
    and coalesce(nullif(SecondName, ''), '') = coalesce(?, '')
    and coalesce(MatWealthMeasureCode, 0) = coalesce(?, 0)
    and coalesce(DoseSize, 0.0) = coalesce(?, 0.0)
    and coalesce(MatWealthMeasureDoseSizeCode, 0) = coalesce(?, 0)
limit 1
EOT;
$res = selectSqlValue($db, $sql, [ $sectionId, isEmptyProperty($row, 'groupId'), isEmptyProperty($row, 'wealthObjectId'), isEmptyProperty($row, 'name'), isEmptyProperty($row, 'secondName'), isEmptyProperty($row, 'measureId'), isEmptyProperty($row, 'doseSize'), isEmptyProperty($row, 'measureDoseSizeId') ]); if ($return) { return $res; } if (!empty($res)) { throw new Exception("\"{$name}\" уже существует!", -1001); } } public function checkExistWealth($row, $return = false) { $db = $this->db ?: $this->db = connectDb(); $objectName = isEmptyProperty($row, 'secondName', isEmptyProperty($row, 'name')); $name = isEmptyProperty($row, 'commercialName'); $sql = <<<"EOT"
select w.MatWealthCode
from MatWealth as w
left join MatWealthManufacturer as wm on wm.MatWealthCode = w.MatWealthCode
where w.MatWealthObjectCode = ?
    and w.MatWealthCode <> coalesce(?, 0)
    and coalesce(w.CommercialName, '') = coalesce(?, '')
    and coalesce(nullif(w.Feature, ''), '') = coalesce(?, '')
    and coalesce(nullif(w.Barcode, ''), '') = coalesce(?, '')
    and coalesce(wm.CountryCode, 0) = coalesce(?, 0)
    and coalesce(nullif(wm.Manufacturer, ''), '') = coalesce(?, '')
limit 1
EOT;
$res = selectSqlValue($db, $sql, [ isEmptyProperty($row, 'wealthObjectId'), isEmptyProperty($row, 'wealthId'), isEmptyProperty($row, 'commercialName'), isEmptyProperty($row, 'feature'), isEmptyProperty($row, 'barcode'), isEmptyProperty($row, 'manufacturerCountryId'), isEmptyProperty($row, 'manufacturer') ]); if ($return) { return $res; } if (!empty($res)) { throw new Exception("Вариант \"{$name}\" уже существует для \"{$objectName}\"!", -1002); } } } handleJsonRpc('pmed_common_wealths');