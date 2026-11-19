<?php
require_once(__DIR__ . '/../init.php'); class talonbox_admin { const PAGE_SIZE = 30; private $db; public function readFloors ($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $page = isUndefinedProperty($params, 'page', 1); $limit = isUndefinedProperty($params, 'limit', self::PAGE_SIZE); $start = $limit = isEmptyProperty($params, 'start'); $buildId = selectSqlValue($db, 'SELECT medmapbuildingcode 
                FROM MedMapBuilding 
                WHERE enterprisecode = ?', [$enterpriseId]); $ret = new stdClass(); if(!$buildId){ return $ret; } $sql = <<<"EOT"
SELECT 
    MMF.MedMapFloorCode "floorCode",
    (CASE WHEN  MMF.Active IS NOT NULL THEN 0 ELSE 1 END )::integer "isActive",
    MMF.Description "description",
    MMF.Id "floorId",
    MMF.MedMapBuildingCode "buildingCode",
    MMF.Name "name",
    MMF.Json "json",
    MMF.floormap  "map",
    MMF.Position "position",
    (CASE WHEN  MMF.FloorMap IS NOT NULL THEN 1 ELSE 0 END )::integer "isLoadMap",
    COUNT(FI.medmapflooritemcode)::integer AS "totalCount",
    SUM(CASE WHEN  FI.MedCabinetCode IS NOT NULL THEN 1 ELSE 0 END )::integer AS "cabinetCount"
FROM MedMapFloor MMF
LEFT JOIN MedMapFloorItem FI ON MMF.MedMapFloorCode = FI.MedMapFloorCode 
GROUP BY MMF.MedMapFloorCode
ORDER BY MMF.Position ASC 
LIMIT ? OFFSET ?
EOT;
$sqlTotal = <<<EOT
            SELECT COUNT(*) total
            FROM MedMapFloor MMF
            WHERE MMF.MedMapBuildingCode = ? 
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute([$buildId]); $ret->items = selectSql($db, $sql, [$limit, $start]); $ret->total = (int) fetchValue($stmtTotal, 'total'); return $ret; } private function createFloorItem($floorName = null, $floorActive = null, $jsonData = null, $data = null){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $florItems = null; $position = selectSqlValue($db, 'SELECT  incstr(MAX(mmf.position))
FROM medmapfloor mmf
LEFT JOIN medmapbuilding mmb
ON mmf.medmapbuildingcode = mmb.medmapbuildingcode
where mmb.enterprisecode = ?', [$enterpriseId]); $sqlBuildInsert = <<<"EOT"
insert into MedMapBuilding
    (EnterpriseCode, Id, Name)
values
    ($enterpriseId, ?, ?)
EOT;
$sqlFloorInsert = <<<"EOT"
insert into MedMapFloor
    (Active, Description, Id, MedMapBuildingCode, Name, Position, FloorMap, Json)
values
    (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtBuildInsert = $db->prepare($sqlBuildInsert); $stmtFloorInsert = $db->prepare($sqlFloorInsert); $buildId = selectSqlValue($db, 'SELECT medmapbuildingcode 
                FROM MedMapBuilding 
                WHERE enterprisecode = ?', [$enterpriseId]); if (!$buildId) { $stmtBuildInsert->execute([ isEmptyProperty($jsonData, 'id'), $floorName ]); $buildId = $db->lastInsertId('seq_MedMapBuilding'); } if ($jsonData) { $florData = $jsonData->building->floor; $florItems = $florData->items; if(!empty($floorActive)) { $florData -> active = $floorActive; } if(!empty($floorName)) { $florData -> name = $floorName; } $stmtFloorInsert->execute([ isUndefinedProperty($florData, 'active'), isEmptyProperty($florData, 'description'), isEmptyProperty($florData, 'id'), $buildId, isEmptyProperty($florData, 'name'), empty($position) ? 1 : $position, $florData->map, $data ]); } else { $stmtFloorInsert->execute([ $floorActive,null,null,$buildId,$floorName, $position, null,null ]); } $floorId = $db->lastInsertId('seq_MedMapFloor'); return array('buildId' => $buildId, 'floorId' => $floorId, 'florItems' => $florItems); } public function saveFloor ($params = null) { $db = $this->db ?: $this->db = connectDb(); $params->enterpriseId = getSessionValue('mapEnterpriseCode'); $data = base64_decode(isEmptyProperty($params, 'data', '')); $name = isEmptyProperty($params, 'floorName'); $active = isEmptyProperty($params, 'active'); $floorData = json_decode($data); $ret = new stdClass(); $isNonUniqeName = selectSqlValue($db, 'SELECT mmf.name
FROM medmapfloor mmf
LEFT JOIN medmapbuilding mmb
ON mmf.medmapbuildingcode = mmb.medmapbuildingcode
where mmb.enterprisecode = ? and mmf.name = ?', [$params->enterpriseId, trim($name)]); if($isNonUniqeName) { $ret->code = 23505; $ret->errorMessage = "Наименование этажа должно быть уникальным"; return $ret; } $data = $this->createFloorItem($name, $active, $floorData, $data); $buildId = $data['buildId']; $floorId = $data['floorId']; $florItems = $data['florItems']; $ret->floor = $data; if (isset($florItems)) { $createdItems = insertSql($db, 'medmapflooritem', $florItems, "active, attributes, coordinates, description, id, medcabinetcode,medmapfloorcode, name, number, type, shapetype", "coordinates, description, id, name, type, shapeType", "1, 0, ?, ?, ?, null, $floorId, ?, null, ?, ?", 'id', false ); $ret->florItems = $createdItems; } return $ret; } public function updateFloor ($params = null) { $db = $this->db ?: $this->db = connectDb(); $params->enterpriseId = getSessionValue('mapEnterpriseCode'); $floorId = $params->floorId; $active = isUndefinedProperty($params, 'active', null); $floorName = isUndefinedProperty($params, 'floorName'); $sqlUpdateFloorFields = 'Active = case when ? = 1 then 1 else NULL end,
        Name = ?'; $sqlUpdateFloorParams = [$active, $floorName]; if(isset($params->data)) { $data = base64_decode(isEmptyProperty($params, 'data')); $jsonData = json_decode($data); $florData = $jsonData->building->floor; $florItems = $florData->items; $sqlUpdateFloorFields = $sqlUpdateFloorFields. ', FloorMap = ?, Json = ?'; array_push($sqlUpdateFloorParams, $florData->map, $data); $db->beginTransaction(); try {foreach ($florItems as $row) { $sql = ''; $floorItemId = selectSqlValue($db, 'SELECT medmapflooritemcode 
                FROM MedMapFloorItem 
                WHERE id = ? AND medmapfloorcode = ?', [$row->id, $floorId]); if ($floorItemId) { $updSql = ''; $updValues = array(); if(property_exists($row, 'update')) { $updSql .= "Coordinates = ?, Name = ?"; $updValues[] = $row->coordinates; $updValues[] = $row->name; } if(property_exists($row, 'delete')) { $updSql .= (strlen($updSql) ? ", " : "") ."Active = ?"; $updValues[] = 0; } if(count($updValues)) { $updValues[] = $floorItemId; $updSql = <<<"EOT"
UPDATE MedMapFloorItem
    SET $updSql
WHERE MedMapFloorItemCode = ?
EOT;
$stmt = $db->prepare($updSql); $stmt->execute($updValues); } } else { $active = (int)(!property_exists($row, 'delete')); insertSql($db, 'medmapflooritem', [$row], "active, attributes, coordinates, description, id, medcabinetcode,medmapfloorcode, name, number, type, shapetype", "coordinates, description, id, name, type, shapeType", "$active, 0, ?, ?, ?, null, $floorId, ?, null, ?, ?", 'medmapflooritemcode', false ); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } } $sqlUpdateFloor = <<<"EOT"
UPDATE MedMapFloor
    SET $sqlUpdateFloorFields
WHERE MedMapFloorCode = ?
EOT;
$stmtUpdateFloor = $db->prepare($sqlUpdateFloor); $sqlUpdateFloorParams[] = $floorId; return $stmtUpdateFloor->execute($sqlUpdateFloorParams); } public function deleteFloors( $params=null) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedMapFloor', 'medmapfloorcode', isUndefinedProperty($params, 'floorCode')); } public function readFloorItems($params=null){ $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'id', null); $limit = isEmptyProperty($params, 'limit', 0); $page = isEmptyProperty($params, 'page', 1); $offset = ($page - 1) * $limit; $search = isEmptyProperty($params, 'search', ''); $type = isEmptyProperty($params, 'type'); if (isset($params->page)) $page = (int) $params->page; ELSE $page = 1; $start = (int)(($page-1) * $limit) + 1; $limitSql = ''; $where = 'WHERE MMFI.MedMapFloorCode = ?'; $sqlParams = [$id]; $having = ''; if($type) { $where = $where." AND MMFI.type = ?"; $sqlParams[] = $type; } if ($search) { $having = "HAVING LOWER(STRING_AGG(DD.DoctorProfessionName, ';')) LIKE LOWER('%$search%')
                OR LOWER(STRING_AGG(DD.DoctorFio, ';')) LIKE LOWER('%$search%')
                OR LOWER(MMFI.Name) LIKE LOWER('%$search%')"; } if (safe_strlen($search) > 1) { $offset = 0; } if ($limit) { $limitSql = 'limit ' . $limit . ' offset ' . $offset; } $sql = <<<"EOT"
SELECT  
                MMFI.Name                                           "name",
                MMFI.MedMapFloorItemCode                            "medmapflooritemcode",
                CASE WHEN MMFI.Active = 0 THEN true ELSE false END  "active",
                MMFI.MedCabinetCode                                 "medcabinetcode",
                LOWER(STRING_AGG(DD.DoctorFio, ';'))                "doctorFio",
                LOWER(STRING_AGG(DD.DoctorProfessionName, ';') )    "doctorProfessionName",
                MMF.MedMapFloorCode                                 "medmapfloorcode",
                MMF.FloorMap                                        "map",
                MMFI.Coordinates                                    "coordinates",
                MMFI.Number                                         "number",
                MMFI.Id                                             "id",
                MMFI.Type                                           "type",
                MMFI.ShapeType                                      "shapetype",
                MMFI.Attributes                                     "attributes",
                MMFI.MedEqInfoboxCode                               "medeqinfoboxcode"
            FROM MedMapFloorItem MMFI
            LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
            LEFT JOIN MedCabinet MC ON MC.MedCabinetCode = MMFI.MedCabinetCode
            LEFT JOIN (
                SELECT *
                FROM (
                    SELECT
                        COALESCE(NULLIF(MCGW.Cabinet, ''), NULLIF(MCG.Cabinet, ''), MD.Cabinet) cabinet,
                        FN_cmFio(MD.ManCode) doctorFio,
                        MP.Name doctorProfessionName
                    FROM MedCouponGraph MCG
                    LEFT JOIN MedCouponGraphWork MCGW ON MCGW.MedCouponGraphCode = MCG.MedCouponGraphCode
                    LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MCG.MedDoctorCode
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE MCG.OnDate = 'now'::DATE
                    UNION ALL
                    SELECT
                        MD.Cabinet,
                        FN_cmFio(MD.ManCode),
                        MP.Name
                    FROM MedDoctor MD
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE NULLIF(MD.Cabinet, '') != ''
                        AND 'now'::DATE BETWEEN MD.StartDate AND MD.FinishDate
                ) QQ
                GROUP BY 1, 2, 3
            ) DD ON DD.Cabinet = MC.Cabinet
            $where  
            GROUP BY MMFI.Name, MMFI.MedMapFloorItemCode, MMFI.Active, MMFI.MedCabinetCode, mmf.medmapfloorcode, mmfi.coordinates,
            mmfi.number, mmfi.id, mmfi.type, mmfi.shapetype, mmfi.attributes
            $having
            ORDER BY MMF.Name, MMFI.Name
EOT;
$sqlTotal = <<<EOT
            SELECT COUNT(*) total
            FROM (
                $sql
            ) AS t
EOT;
$root = new stdClass(); $root->total = (int) selectSqlValue($db, $sqlTotal, $sqlParams); $sql .= ' '. $limitSql; $root->items = selectSql($db, $sql, $sqlParams); return $root; } public function updateFloorItems($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sqlUpdateItem = <<<"EOT"
UPDATE MedMapFloorItem SET
  Active = ?,
  Name = case when ? = 1 then ? else Name end,
  Attributes = case when ? = 1 then ? else Attributes end,
  Type = case when ? = 1 then ? else Type end,
  MedCabinetCode = case when ? = 1 then ? else MedCabinetCode end,
  Description = case when ? = 1 then ? else Description end,
  Number = case when ? = 1 then ? else Number end,
  MedEqInfoboxCode = case when ? = 1 then ? else MedEqInfoboxCode end
WHERE MedMapFloorItemCode = ?
EOT;
$stmtUpdateItem = $db->prepare($sqlUpdateItem); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; if(!empty($row->medmapflooritemcode)) { $row->active = isEmptyProperty($row, 'active') ? 0 : 1; $stmtUpdateItem->execute([ $row->active, propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'attributes'), isUndefinedProperty($row, 'attributes'), propertyExistsSQL($row, 'type'), isUndefinedProperty($row, 'type'), propertyExistsSQL($row, 'medcabinetcode'), isUndefinedProperty($row, 'medcabinetcode'), propertyExistsSQL($row, 'description'), isUndefinedProperty($row, 'description'), propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'medeqinfoboxcode'), isUndefinedProperty($row, 'medeqinfoboxcode'), ($rec->id = $row->medmapflooritemcode) ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getInfoAboutCabinet ($params = null) { $db = $this->db ?: $this->db = connectDb(); $currentMonth = date("Y-m-d"); $cabinet = $params-> cabinet; $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select distinct *
    from (select
    md.MedDoctorCode "id",
    FN_cmFIO(md.ManCode) "name",
    md.Cabinet "doctorCabinet",
    md.MedProfessionCode "specialtyCode",
    mp.Name "specialty"
from MedDoctor md
left outer join MedProfession mp on mp.MedProfessionCode = md.MedProfessionCode
join Department d on d.DepartmentCode = md.DepartmentCode
    and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
left outer join Branch b on b.branchCode = d.branchCode
left outer join EnterpriseStructure es on es.departmentCodeWeb = d.DepartmentCode
where coalesce(md.FinishDate,30000101) >= ? and md.Cabinet = ?
and b.enterpriseCode = ?
union
select distinct 
    md.MedDoctorCode "id",
    FN_cmFIO(md.ManCode) "name",
    MCG.Cabinet "doctorCabinet",
    md.MedProfessionCode "specialtyCode",
    mp.Name "specialty"
    from MedCouponGraph MCG
    inner join MedDoctor MD on MD.MedDoctorCode = MCG.MedDoctorCode
    left outer join MedProfession mp on mp.MedProfessionCode = md.MedProfessionCode
    where MCG.cabinet = ?) w
order by name ASC
EOT;
return selectSql($db, $sql,[$currentMonth, $cabinet, $enterpriseId, $cabinet]); } public function getEqinfo ($params = null): array { $db = $this->db ?: $this->db = connectDb(); $medcabinetcode = $params->medcabinetcode; $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT 
ME.medeqowpcode         "medeqowpcode",
ME.medcabinetcode       "medcabinetcode",
MS.name                 "service"
FROM medeqowp ME
LEFT OUTER JOIN medeqservice MS on ME.medeqservicecode = MS.medeqservicecode 
WHERE me.medcabinetcode = ? and ME.active = 1 and ME.enterprisecode = ?
EOT;
return selectSql($db, $sql,[$medcabinetcode, $enterpriseId]); } public function updateFloorPositions($records) { $db = $this->db ?: $this->db = connectDb(); foreach ($records as $row) { $row->id = $row->floorCode; } return updateSql($db, 'MedMapFloor', 'medmapfloorcode', $records, 'id, position', 'id, position'); } } handleJsonRpc('talonbox_admin');