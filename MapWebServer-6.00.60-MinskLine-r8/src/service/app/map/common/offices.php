<?php
require_once(__DIR__ . '/../../init.php'); class map_common_offices { private $db; public function readOffices($params = null) { if (isEmptyProperty($params, 'isCombo')) { return $this->readOfficesToCombo($params); } $db = $this->db ?: $this->db = connectDb(); $firstLevelOnly = isEmptyProperty($params, 'firstLevel'); $currId = isEmptyProperty($params, 'currId'); $where = $currId ? "AND ES.EnterpriseStructureCode = $currId" : ""; $enterpriseId = isset($params->enterpriseId) && isEmptyProperty($params, 'enterpriseId') ? $params->enterpriseId : getSessionValue('mapEnterpriseCode'); $fullTree = isEmptyProperty($params, 'fullTree'); $condParent = ''; if (!$fullTree) { $condParent = isset($params->node) && isEmptyProperty($params, 'node') ? "AND COALESCE(ES.EnterpriseStructureParentCode, 0)::INTEGER = $params->node" : "AND ES.EnterpriseStructureParentCode IS NULL"; } $taskDate = Date("Ymd"); $depFullName = 'FN_cmDepartmentName(ES.DepartmentCodeWeb, 1)::TEXT'; $taskDateCondition = "AND $taskDate BETWEEN ES.StartDate AND ES.FinishDate"; if ($firstLevelOnly) { $depFullName = 'ESI.Name'; $useRecycleBin = isEmptyProperty($params, 'useRecycleBin'); if ($useRecycleBin) { $taskDateCondition = ""; } } $ciszOnly = isEmptyProperty($params, 'ciszOnly'); $whereCisz = $ciszOnly ? "AND (D.CiszGuid IS NOT NULL
        OR EXISTS (
            SELECT 1
            FROM EnterpriseStructure ES_
            JOIN department d_ ON d_.departmentCode = ES_.departmentCodeWeb
            WHERE ES_.EnterpriseStructureParentCode = ES.EnterpriseStructureCode
              AND d_.CiszGuid IS NOT NULL
        ))" : ""; $sql = <<<EOT
            SELECT
                ES.EnterpriseStructureCode                                  "id",
                ESI.Cipher                                                  "code",
                ESI.Name                                                    "name",
                ESIP.FullName                                               "parentName",
                {$depFullName}                                              "fullName",
                ES.EnterpriseStructureParentCode                            "parentId",
                ES.EnterpriseCode                                           "enterpriseId",
                ES.DepartmentCodeWeb                                        "departmentId",
                NULLIF(TRIM((D.json::json)->>'id'), '')                     "ciszOrgId",
                NULLIF(TRIM((D.json::json)->>'name'), '')                   "ciszOrgName",
                CASE WHEN EXISTS(
                        SELECT 1
                        FROM EnterpriseStructure ES_
                        JOIN EnterpriseStructureInfo ESI_ ON ESI_.EnterpriseStructureCode = ES_.EnterpriseStructureCode
                        WHERE $taskDate BETWEEN ES_.StartDate AND ES_.FinishDate
                            AND fn_WebUserAccessOffice(ES_.EnterpriseStructureCode) = 1
                            AND ES_.EnterpriseStructureParentCode = ES.EnterpriseStructureCode
                            AND ES_.EnterpriseStructureCode <> ES.EnterpriseStructureCode
                        LIMIT 1)
                    THEN 0
                    ELSE 1
                END                                                         "leaf",
                CASE WHEN ES.FinishDate != 19000101 THEN 1 ELSE NULL END    "active",
                d.branchCode                                                "branchId"
            FROM EnterpriseStructure ES
            JOIN EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = ES.EnterpriseStructureCode
            LEFT JOIN EnterpriseStructureInfo ESIP ON ESIP.EnterpriseStructureCode = ES.EnterpriseStructureParentCode
            LEFT JOIN department D ON D.departmentCode = ES.departmentCodeWeb
            WHERE ES.EnterpriseCode = $enterpriseId
                AND fn_WebUserAccessOffice(ES.EnterpriseStructureCode) = 1
                $taskDateCondition
                $condParent
                $where
                $whereCisz
            ORDER BY 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $items = []; $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass(); $item->id = $id = $row['id']; $item->parentId = $row['parentId']; $item->code = $row['code']; $item->name = $row['name']; $item->fullName = $row['fullName']; $item->parentName = $row['parentName']; $item->enterpriseId = $row['enterpriseId']; $item->departmentId = $row['departmentId']; $item->ciszOrgId = $row['ciszOrgId']; $item->ciszOrgName = $row['ciszOrgName']; $item->leaf = $firstLevelOnly ? true : $row['leaf'] == 1; $item->active = $row['active']; $item->firstLevel = $firstLevelOnly; $item->branchId = $row['branchId']; if ($currId) { return $item; } $items[$id] = $item; $root[] = $item; } $ret = new stdClass(); $ret->enterpriseId = $enterpriseId; $ret->items = $root; return $ret; } private function readOfficesToCombo($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = isset($params->enterpriseId) && isEmptyProperty($params, 'enterpriseId') ? $params->enterpriseId : getSessionValue('mapEnterpriseCode'); $isUnit = isEmptyProperty($params, 'isUnit'); $officeUnitId = isEmptyProperty($params, 'officeUnitId'); $branchId = isEmptyProperty($params, 'branchId'); $otherConditions = ""; if ($isUnit) { $condParent = "AND ES.EnterpriseStructureParentCode IS NULL"; } else { $condParent = "AND ES.EnterpriseStructureParentCode IS NOT NULL"; } if ($officeUnitId) { $otherConditions .= " AND ES.EnterpriseStructureParentCode = $officeUnitId"; } if($branchId) { $otherConditions .= " AND D.BranchCode = $branchId"; } $taskDate = Date("Ymd"); $taskDateCondition = "AND $taskDate BETWEEN ES.StartDate AND ES.FinishDate"; $sql = <<<EOT
            WITH RECURSIVE tree_off (id, parent_id) AS (
                SELECT
                    ES.EnterpriseStructureCode          id,
                    ES.EnterpriseStructureParentCode    parent_id
                FROM EnterpriseStructure ES
                WHERE ES.EnterpriseCode = $enterpriseId
                    AND ES.EnterpriseStructureParentCode is NULL
                    AND fn_WebUserAccessOffice(ES.EnterpriseStructureCode) = 1
                    $taskDateCondition
                UNION
                SELECT
                    ES.EnterpriseStructureCode          id,
                    ES.EnterpriseStructureParentCode    parent_id
                FROM tree_off TOf
                JOIN EnterpriseStructure ES ON ES.EnterpriseStructureParentCode = TOf.id
                WHERE fn_WebUserAccessOffice(ES.EnterpriseStructureCode) = 1
            )
            SELECT
                ES.EnterpriseStructureCode                                  "id",
                ESI.Cipher                                                  "code",
                ESI.Name                                                    "name",
                ESIP.FullName                                               "parentName",
                ES.EnterpriseStructureParentCode                            "parentId",
                ES.EnterpriseCode                                           "enterpriseId",
                ES.DepartmentCodeWeb                                        "departmentId",
                CASE WHEN ES.FinishDate != 19000101 THEN 1 ELSE NULL END    "active",
                D.BranchCode                                                "branchId"
            FROM tree_off TOf
            JOIN EnterpriseStructure ES ON ES.EnterpriseStructureCode = TOf.id
            JOIN EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = ES.EnterpriseStructureCode
            LEFT JOIN EnterpriseStructureInfo ESIP ON ESIP.EnterpriseStructureCode = ES.EnterpriseStructureParentCode
            LEFT JOIN department D ON D.DepartmentCode = ES.DepartmentCodeWeb
            WHERE ES.EnterpriseCode = 1
                $taskDateCondition
                $condParent
                $otherConditions
            ORDER BY 3
EOT;
$result = new stdClass(); $result->enterpriseId = $enterpriseId; $result->items = selectSql($db, $sql); return $result; } public function deleteOffices($records) { $db = $this->db ?: $this->db = connectDb(); $firstLevelOnly = isEmptyProperty($records, 'firstLevel'); $in = createSqlInCondition($records); $taskDate = new DateTime(); $taskDate->sub(new DateInterval('P1D')); $date = $taskDate->format('Ymd'); $sql = <<<EOT
            UPDATE EnterpriseStructure
               SET FinishDate = $date,
                   FinishDateManual = $date
            WHERE EnterpriseStructureCode $in
EOT;
if ($firstLevelOnly) { $sql = <<<EOT
                DO $$
                BEGIN
                DELETE FROM department
                WHERE departmentCode IN (SELECT departmentCodeWeb
                                         FROM enterpriseStructure
                                         WHERE enterpriseStructureCode $in);
                DELETE FROM enterpriseStructure
                WHERE EnterpriseStructureCode $in;
                END; $$
EOT;
} $stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateOffices($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE EnterpriseStructureInfo
               SET Name = CASE WHEN ? = 1 THEN ? ELSE Name END,
                   FullName = CASE WHEN ? = 1 THEN ? ELSE FullName END,
                   Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END
            WHERE EnterpriseStructureCode = ?
EOT;
$sqlInRecycleBin = <<<EOT
            UPDATE enterpriseStructure
               SET finishDate = :finishDate,
                   finishDateManual = :finishDate
            WHERE EnterpriseStructureCode = :id
EOT;
$sqlUpdateCiszDepartmentJson = <<<EOT
            UPDATE Department
               SET 
                   Json = CASE WHEN ? = 1 THEN ? ELSE Json END,
                   Ciszguid = CASE WHEN ? = 1 THEN (?::json)->>'id' ELSE Ciszguid END
            WHERE DepartmentCode = ?
EOT;
$stmt = $db->prepare($sql); $stmtBin = $db->prepare($sqlInRecycleBin); $stmtCiszDepartment = $db->prepare($sqlUpdateCiszDepartmentJson); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $firstLevelOnly = isEmptyProperty($row, 'firstLevel'); $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), $row->id ]); if ($firstLevelOnly) { if (propertyExistsSQL($row, 'active')) { $stmtBin->execute([ "finishDate" => $row->active ? '30000101' : '19000101', "id" => $row->id ]); } } if (isUndefinedProperty($row, 'departmentId')) { $ciszOrgJson = isUndefinedProperty($row, 'ciszDepartmentJson'); if ($ciszOrgJson) { $guid = json_decode($ciszOrgJson); $findedNotUniqeDepartment = selectSql($db, 'SELECT * FROM Department WHERE Ciszguid = ?   AND DepartmentCode != ? AND Json IS NULL', [$guid->id, isUndefinedProperty($row, 'departmentId')]); if (count($findedNotUniqeDepartment)) { foreach ($findedNotUniqeDepartment as $department) { if (!isUndefinedProperty($department, 'json')) { deleteSql($db, 'Department', 'departmentcode', [$department], 'departmentcode', true); } } } } $stmtCiszDepartment->execute([ propertyExistsSQL($row, 'ciszDepartmentJson'), isUndefinedProperty($row, 'ciszDepartmentJson'), propertyExistsSQL($row, 'ciszDepartmentJson'), isUndefinedProperty($row, 'ciszDepartmentJson'), $row->departmentId ]); } $param = new stdClass(); $param->currId = $row->id; $param->node = $row->parentId; $param->firstLevel = $firstLevelOnly; $ret[] = $this->readOffices($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); if ($e->getCode() == 23505) { throw new Exception('Такая запись отделения ЦИСЗ уже задана для другого отделения', 23505, $e); } else { throw $e; } } $root = new stdClass(); $root->items = $ret; return $root; } public function createOffices($records) { $db = $this->db ?: $this->db = connectDb(); $sqlInsES = <<<EOT
            INSERT INTO EnterpriseStructure
                (StartDateManual, FinishDateManual, EnterpriseCode, EnterpriseStructureParentCode, DepartmentCodeWeb)
            VALUES (19000101, 30000101, ?, NULLIF(?, 0), 0)
EOT;
$sqlInsESI = <<<EOT
            INSERT INTO EnterpriseStructureInfo
                (ChangeDate, Name, FullName, Cipher, EnterpriseStructureCode)
            VALUES (19000101, ?, ?, ?, ?)
EOT;
$stmtInsES = $db->prepare($sqlInsES); $stmtInsESI = $db->prepare($sqlInsESI); if (is_object($records)) { $records = [$records]; } $ret = []; $param = new stdClass; $db->beginTransaction(); try { foreach ($records as $row) { $firstLevelOnly = isEmptyProperty($row, 'firstLevel'); $stmtInsES->execute([ isEmptyProperty($row, 'enterpriseId'), isEmptyProperty($row, 'parentId') ]); $param->currId = $db->lastInsertId('seq_EnterpriseStructure'); $stmtInsESI->execute([ isEmptyProperty($row, 'name'), isUndefinedProperty($row, 'name'), isEmptyProperty($row, 'code'), $param->currId ]); $param->node = $row->parentId; $param->enterpriseId = $row->enterpriseId; $param->firstLevel = $firstLevelOnly; $ret['enterpriseId'] = $row->enterpriseId; $ret['items'][] = $this->readOffices($param); unset($param->currId); unset($param->node); unset($param->enterpriseId); unset($param->firstLevel); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } } handleJsonRpc('map_common_offices');