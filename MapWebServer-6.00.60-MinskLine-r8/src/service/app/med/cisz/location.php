<?php
 require_once(__DIR__ . '/../../init.php'); require_once('traits/utilsTrait.php'); class med_cisz_location { use med_cisz_utilsTrait; private $db; private object $settings; public function readLocation(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $clinicId = isEmptyProperty($params, 'clinicCode'); if (!$clinicId) { return []; } $clinic = selectSql($db, <<<SQL
        SELECT 
            CRV.CiszResourceValueCode AS "id",
            CRV.Code AS "Uuid",
            CRV.SourceCode AS "sourceId",
            CRV.Name AS "name"
        FROM CiszResourceValue CRV
        LEFT JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode 
        WHERE CRV.SourceCode = :sourceId AND CR.Identifier = '1.2.643.2.69.1.1.1.64'
    SQL,
[ ':sourceId' => $clinicId ], PDO::FETCH_OBJ ); $clinicUuid = $clinic[0]->Uuid; $locations = self::doHttpMethod($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Location?organization=' . 'Organization/' . $clinicUuid, ]); } public function readDepartments($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = isset($params->enterpriseId) && isEmptyProperty($params, 'enterpriseId') ? $params->enterpriseId : getSessionValue('mapEnterpriseCode'); $fullTree = isEmptyProperty($params, 'fullTree'); $condParent = ''; $condChild = ''; if (!$fullTree) { $condParent = isset($params->node) && isEmptyProperty($params, 'node') ? "AND D.BranchCode = $params->node" : "AND 0 = 1"; $condChild = isset($params->node) && isEmptyProperty($params, 'node') ? "AND 0 = 1" : ""; } $ciszOnly = isEmptyProperty($params, 'ciszOnly'); $whereCisz = ''; if ($ciszOnly) { $whereCisz = "AND D.CiszGuid IS NOT NULL"; } $sql = <<<EOT
            SELECT
                B.BranchCode                                                "id",
                B.Cipher                                                    "code",
                B.Name                                                      "name",
                NULL                                                        "parentName",
                B.FullName                                                  "fullName",
                NULL                                                        "parentId",
                B.EnterpriseCode                                            "enterpriseId",
                NULL                                                        "departmentId",
                false                                                       "leaf",
                CASE WHEN B.CloseDate IS NULL THEN 1 ELSE NULL END          "active",
                B.BranchCode                                                "branchId"
            FROM Branch B
            WHERE B.EnterpriseCode = $enterpriseId
                AND B.CloseDate IS NULL
                $condChild
            
                UNION ALL
                
            SELECT
                NULL                                                        "id",
                D.Cipher                                                    "code",
                D.Name                                                      "name",
                B.Name                                                      "parentName",
                D.FullName                                                  "fullName",
                B.BranchCode                                                "parentId",
                B.EnterpriseCode                                            "enterpriseId",
                D.DepartmentCode                                            "departmentId",
                true                                                        "leaf",
                CASE WHEN D.CloseDate IS NULL THEN 1 ELSE NULL END          "active",
                B.BranchCode                                                "branchId"
            FROM Department D
            JOIN Branch B ON B.BranchCode = D.BranchCode
            WHERE B.EnterpriseCode = $enterpriseId
            AND D.CloseDate IS NULL
            $condParent
            $whereCisz
            ORDER BY 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = []; while ($row = $stmt->fetch()) { $item = new \stdClass(); $item->id = $id = $row['id']; $item->parentId = $row['parentId']; $item->code = $row['code']; $item->name = $row['name']; $item->fullName = $row['fullName']; $item->parentName = $row['parentName']; $item->enterpriseId = $row['enterpriseId']; $item->departmentId = $row['departmentId']; $item->leaf = $row['leaf'] == 1; $item->active = $row['active']; $item->branchId = $row['branchId']; $root[] = $item; } $ret = new stdClass(); $ret->enterpriseId = $enterpriseId; $ret->items = $root; return $ret; } } handleJsonRpc('med_cisz_location');