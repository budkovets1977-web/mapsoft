<?php
 require_once(__DIR__ . '/../../init.php'); require_once(__DIR__ . '/dictMisList.php'); class med_cisz_dicts { private $db; public function readDicts(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $whereClause = ""; if (!empty($params->dictName) && is_array($params->dictName)) { $placeholders = implode(',', array_fill(0, count($params->dictName), '?')); $whereClause = "WHERE CR.Identifier IN ($placeholders)"; } $res = selectSql($db, <<<SQL
            SELECT
                CRV.CiszResourceValueCode "id",
                CRV.Code "code",
                CRV.Name "name",
                CR.Identifier "dictName"
            FROM
                CiszResourceValue CRV
            LEFT JOIN CiszResource CR ON  CR.CiszResourceCode  = CRV.CiszResourceCode
            $whereClause
        SQL,
$params->dictName ?? []); return $res; } public function readDictsCisz(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $result = selectSql($db, <<<SQL
        SELECT
            CR.ciszresourcecode "id",
            CR.Name "dictName",
            CR.Status "status",
            CR.Uuid "uuid",
            CR.identifier "identifier",
            CR.Source "source"
        FROM CiszResource CR
        ORDER BY CR.Name
    SQL
); return $result; } public function readDictCiszValues(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $dicId = isEmptyProperty($params, 'dictId'); $whereConditions = ["CRV.active = 1"]; if ($dicId) { $whereConditions[] = "CRV.ciszresourcecode = $dicId"; } $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : ""; $sql = <<<SQL
        SELECT
          MIN(CRV.CiszResourceValueCode) "id",
          CRV.CiszResourceCode "resourceId",
          CRV.Code "code",
          CRV.Name "name",
          CRV.Version "version",
          ARRAY_REMOVE(ARRAY_AGG(DISTINCT CRV.SourceCode), NULL) "dictMisValue",
          MAX(CRV.SourceValueString) FILTER (WHERE CRV.SourceValueString IS NOT NULL) "dictMisValueManually"
        FROM CiszResourceValue CRV
        $whereClause
        GROUP BY CRV.CiszResourceCode, CRV.Code, CRV.Name, CRV.Version
        ORDER BY CRV.Name
    SQL;
return selectSql($db, $sql, []); } public function readDictMisList(?object $options = null): array { $options = $options ?? (object)[]; $codesFilter = []; if (!empty($options->codes)) { foreach ((array)$options->codes as $raw) { $key = strtoupper(trim((string)$raw)); if ($key !== '') { $codesFilter[$key] = true; } } } $result = []; foreach (dictMisList::LIST as $key => $cfg) { if ($codesFilter && !isset($codesFilter[strtoupper($key)])) { continue; } $result[] = [ 'id' => isset($cfg['id']) ? (int)$cfg['id'] : null, 'code' => (string)$key, 'name' => isset($cfg['name']) ? (string)$cfg['name'] : (string)$key, 'resource' => isset($cfg['resource']) ? (string)$cfg['resource'] : dictMisList::RESOURCE_UNIDICT, ]; } usort($result, static function(array $a, array $b) { $ai = $a['id'] ?? PHP_INT_MAX; $bi = $b['id'] ?? PHP_INT_MAX; if ($ai === $bi) { return strcmp($a['name'], $b['name']); } return $ai <=> $bi; }); return $result; } public function updateCiszResourceSource(?object $params = null): bool { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $source = isEmptyProperty($params, 'source'); if ($id <= 0) { throw new Exception('Invalid ciszResource id'); } executeSql($db, <<<SQL
        UPDATE CiszResource
           SET Source = :source
         WHERE ciszResourceCode = :id
    SQL,
[ ':id' => $id, ':source' => $source, ]); return true; } public function readMisDictValues(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'sourceId'); $identifier = isEmptyProperty($params, 'identifier'); $search = isEmptyProperty($params, 'search'); $sortType = isEmptyProperty($params, 'sortType'); $sort = ''; $misCode = isEmptyProperty($params, 'misCode'); $misDictCode = isEmptyProperty($params, 'misDictCode'); $codesList = isEmptyProperty($params, 'codesList'); $hasLink = false; $distinctCode = isEmptyProperty($params, 'distinctCode'); $uniqueIds = isEmptyProperty($params, 'uniqueIds'); if (isset($identifier) && empty($id)) { if ($misDictCode && ($misDictCode === 35 || $misDictCode === 36)) { $hasLinkSql = <<<SQL
    SELECT 1
    FROM CiszResourceValue CRV
    JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
    WHERE Source = :misDictCode
      AND CRV.SourceCode = :misCode
      AND CRV.Active = 1
    LIMIT 1
SQL;
$stmt = $db->prepare($hasLinkSql); $stmt->execute([ ':misDictCode' => $misDictCode, ':misCode' => $misCode ]); $hasLink = (bool) $stmt->fetchColumn(); $where = ''; $bindParams = [':identifier' => $identifier]; if (!empty($search)) { $where .= ' AND (CRV.Code LIKE :search OR CRV.Value LIKE :search)'; $bindParams[':search'] = '%' . $search . '%'; } if ($sortType) { switch ($sortType) { case 1: $sort = 'ORDER BY CRV.Name'; break; case 2: $sort = 'ORDER BY CRV.Code'; break; case 3: $sort = 'ORDER BY CRV.CiszResourceValueCode'; break; } } if ($misCode && $hasLink) { $where .= ' AND CRV.SourceCode = :misCode'; $bindParams[':misCode'] = $misCode; } $sql = <<<SQL
            SELECT
                MIN(CRV.CiszResourceValueCode) "id",
                CRV.Code "code",
                CRV.Name "name"
            FROM CiszResourceValue CRV
            LEFT JOIN CiszResource CR ON CR.ciszresourcecode = CRV.ciszresourcecode
             WHERE identifier LIKE :identifier
             AND CRV.Active = 1
             $where
             GROUP BY CRV.Name, CRV.Code
SQL;
if ($sort) { $sql .= " " . $sort; } $rows = selectSql($db, $sql, $bindParams); $out = []; foreach ((array)$rows as $r) { $idVal = $r['id']; $codeVal = $r['code'] ?? ''; $nameVal = (string)($r['name'] ?? ''); if ($nameVal === '') { continue; } $out[] = ['id' => $idVal, 'code' => $codeVal, 'name' => $nameVal]; } return $out; } $bindParams = []; if (!empty($distinctCode)) { $select = "
            MIN(CRV.CiszResourceValueCode) AS id,
            CRV.Code AS code,
            CRV.Name AS name"; } else { $select = "
            CRV.CiszResourceValueCode AS id,
            CRV.Code AS code,
            CRV.Name AS name"; } $sql = <<<SQL
            SELECT {$select}
            FROM CiszResourceValue CRV
            LEFT JOIN CiszResource CR ON CR.ciszresourcecode = CRV.ciszresourcecode
             WHERE 1=1
            AND CRV.Active = 1
SQL;
if (is_array($identifier)) { $in = []; foreach ($identifier as $i => $val) { $key = ":identifier{$i}"; $in[] = $key; $bindParams[$key] = $val; } $sql .= " AND CR.identifier IN (" . implode(',', $in) . ")"; } else { $sql .= " AND CR.identifier LIKE :identifier"; $bindParams[':identifier'] = $identifier; } if (!empty($search)) { $sql .= " AND (Code LIKE :search OR Value LIKE :search)"; $bindParams[':search'] = '%' . $search . '%'; } if (!empty($codesList) && is_array($codesList)) { $placeholders = []; foreach ($codesList as $index => $code) { $key = ':code' . $index; $placeholders[] = $key; $bindParams[$key] = $code; } $sql .= ' AND CRV.Code IN (' . implode(',', $placeholders) . ')'; } if (!empty($uniqueIds)) { $tableName = $uniqueIds->tableName; $fieldName = $uniqueIds->fieldName; $sql .= "
                    AND CRV.CiszResourceValueCode NOT IN (
                        SELECT DISTINCT {$fieldName}
                        FROM {$tableName} T
                        WHERE {$fieldName} <> :currentId
                    )"; $bindParams[':currentId'] = $uniqueIds->currentId ?? 0; } if (!empty($distinctCode)) { $sql .= " GROUP BY CRV.Code, CRV.Name"; } if ($sortType) { switch ($sortType) { case 1: $sort = 'ORDER BY CRV.Name'; break; case 2: $sort = 'ORDER BY CRV.Code'; break; case 3: $sort = 'ORDER BY CRV.CiszResourceValueCode'; break; } } if ($sort) { $sql .= " " . $sort; } $rows = selectSql($db, $sql, $bindParams); $out = []; foreach ((array)$rows as $r) { $idVal = $r['id']; $codeVal = $r['code'] ?? ''; $nameVal = (string)($r['name'] ?? ''); $sourceVal = $r['sourceId'] ?? ''; if ($nameVal === '') { continue; } $out[] = ['id' => $idVal, 'code' => $codeVal, 'name' => $nameVal, 'sourceId' => $sourceVal]; } return $out; } else if (isset($id)) { $dictItem = null; foreach (dictMisList::LIST as $code => $cfg) { if (isset($cfg['id']) && (int)$cfg['id'] === (int)$id) { $dictItem = $cfg + ['__code' => $code]; break; } } if (!$dictItem) { return []; } $resource = $dictItem['resource'] ?? null; if (is_string($resource)) { $fields = $dictItem['fields'] ?? []; $codeCol = $fields['code'] ?? 'Code'; $nameCol = $fields['name'] ?? 'Name'; $dictNumber = $fields['DictNumber'] ?? null; $where = ''; $whereActive = ''; if (strtolower($resource) === 'ciszresourcevalue') { $whereActive = 'AND Active = 1'; } if ($dictNumber) { $where = "AND DictNumber = {$dictNumber}"; } $sql = "SELECT
                        {$codeCol} AS id,
                        {$nameCol} AS name
                     FROM {$resource}
                        WHERE {$nameCol} IS NOT NULL AND {$nameCol} <> ''
                          {$whereActive}
                          {$where}"; $bindParams = []; if (!empty($search)) { $sql .= " AND ({$nameCol} LIKE :search OR {$codeCol} LIKE :search)"; $bindParams[':search'] = '%' . $search . '%'; } $sql .= " ORDER BY {$nameCol}"; $rows = selectSql($db, $sql, $bindParams); $out = []; foreach ((array)$rows as $r) { $idVal = $r['id']; $idVal = is_numeric($idVal) ? (int)$idVal : (string)$idVal; $nameVal = (string)($r['name'] ?? ''); if ($nameVal === '') { continue; } $out[] = ['id' => $idVal, 'name' => $nameVal]; } return $out; } return []; } return []; } public function setCiszResourceValueSources(?object $params = null): bool { $db = $this->db ?: $this->db = connectDb(); $baseId = (int)($params->baseId ?? 0); if ($baseId <= 0) { throw new Exception('Invalid baseId'); } $sourceValueString = null; if (property_exists($params, 'SourceValueString')) { $sourceValueString = $params->SourceValueString; } elseif (property_exists($params, 'sourceValueString')) { $sourceValueString = $params->sourceValueString; } $base = selectSqlValue($db, <<<SQL
    SELECT row_to_json(t) FROM (
        SELECT
            CiszResourceValueCode AS id,
            CiszResourceCode AS resourceId,
            Code, Name, Version
        FROM CiszResourceValue
        WHERE CiszResourceValueCode = :id
    ) t
SQL,
[':id' => $baseId]); if (!$base) { throw new Exception('Base row not found'); } $base = json_decode($base, true); $resourceId = (int)$base['resourceid']; $db->beginTransaction(); try { if ($sourceValueString !== null) { executeSql($db, <<<SQL
            UPDATE CiszResourceValue
               SET SourceValueString = :sv,
                   SourceCode = NULL,
                   active = 1
             WHERE CiszResourceValueCode = :id
        SQL,
[':sv' => $sourceValueString, ':id' => $baseId]); executeSql($db, <<<SQL
            DELETE FROM CiszResourceValue
             WHERE CiszResourceCode = :res
               AND Code IS NOT DISTINCT FROM :code
               AND Name IS NOT DISTINCT FROM :name
               AND Version IS NOT DISTINCT FROM :ver
               AND CiszResourceValueCode <> :base
        SQL,
[ ':res' => $resourceId, ':code' => $base['code'], ':name' => $base['name'], ':ver' => $base['version'], ':base' => $baseId, ]); $db->commit(); return true; } $selected = []; foreach ((array)($params->sourceCodes ?? []) as $v) { $iv = (int)$v; if ($iv > 0) $selected[$iv] = true; } $selectedList = array_keys($selected); if (empty($selectedList)) { executeSql($db, <<<SQL
            DELETE FROM CiszResourceValue
             WHERE CiszResourceCode = :res
               AND Code IS NOT DISTINCT FROM :code
               AND Name IS NOT DISTINCT FROM :name
               AND Version IS NOT DISTINCT FROM :ver
               AND CiszResourceValueCode <> :base
        SQL,
[ ':res' => $resourceId, ':code' => $base['code'], ':name' => $base['name'], ':ver' => $base['version'], ':base' => $baseId, ]); executeSql($db, <<<SQL
            UPDATE CiszResourceValue
               SET SourceCode = NULL,
                   SourceValueString = NULL,
                   active = 1
             WHERE CiszResourceValueCode = :id
        SQL,
[':id' => $baseId]); $db->commit(); return true; } executeSql($db, <<<SQL
        DELETE FROM CiszResourceValue
         WHERE CiszResourceCode = :res
           AND Code IS NOT DISTINCT FROM :code
           AND Name IS NOT DISTINCT FROM :name
           AND Version IS NOT DISTINCT FROM :ver
           AND CiszResourceValueCode <> :base
    SQL,
[ ':res' => $resourceId, ':code' => $base['code'], ':name' => $base['name'], ':ver' => $base['version'], ':base' => $baseId, ]); $firstSource = $selectedList[0]; executeSql($db, <<<SQL
        UPDATE CiszResourceValue
           SET SourceCode = :sc,
               SourceValueString = NULL,
               active = 1
         WHERE CiszResourceValueCode = :id
    SQL,
[':sc' => $firstSource, ':id' => $baseId]); if (count($selectedList) > 1) { $stmtInsert = $db->prepare(<<<SQL
            INSERT INTO CiszResourceValue (
                CiszResourceCode, 
                Code, 
                Name, 
                Version, 
                SourceCode, 
                SourceValueString,
                active
            ) VALUES (
                :res, :code, :name, :ver, :sc, NULL, 1
            )
        SQL)
; for ($i = 1; $i < count($selectedList); $i++) { $stmtInsert->execute([ ':res' => $resourceId, ':code' => $base['code'], ':name' => $base['name'], ':ver' => $base['version'], ':sc' => $selectedList[$i] ]); } } $db->commit(); return true; } catch (Throwable $e) { $db->rollBack(); throw $e; } } public function matchedCiszDictionaryValues($params): array { $db = $this->db ?: $this->db = connectDb(); $ciszResourceCode = isUndefinedProperty($params, 'dictId'); if ($ciszResourceCode <= 0) { return []; } $linkedRows = (array)selectSql($db, <<<SQL
        SELECT
            CRV.CiszResourceValueCode id,
            CRV.SourceCode "sourceValueId",
            CRV.SourceValueString "sourceValueString",
            CR.Source "sourceId"
        FROM CiszResourceValue CRV
        LEFT JOIN CiszResource CR
               ON CR.CiszResourceCode = CRV.CiszResourceCode
        WHERE CRV.CiszResourceCode = ?
          AND (CRV.SourceCode IS NOT NULL OR COALESCE(CRV.SourceValueString,'') <> '')
        ORDER BY CRV.CiszResourceValueCode
    SQL,
[$ciszResourceCode]); if (!$linkedRows) { return []; } $misSourceId = (int)($linkedRows[0]['sourceId'] ?? 0); $misDictCfg = null; foreach (dictMisList::LIST as $misKey => $cfg) { if (isset($cfg['id']) && (int)$cfg['id'] === $misSourceId) { $misDictCfg = $cfg; break; } } $codeToReadableName = []; if ($misDictCfg) { $resource = $misDictCfg['resource'] ?? dictMisList::RESOURCE_MANUAL; if ($resource === dictMisList::RESOURCE_MANUAL) { foreach ((array)($misDictCfg['values'] ?? []) as $v) { $code = $v['code'] ?? ($v['id'] ?? null); if ($code === null) { continue; } $name = (string)($v['name'] ?? ($v['value'] ?? $code)); $codeToReadableName[(string)$code] = $name; } } elseif (is_string($resource)) { $fields = (array)($misDictCfg['fields'] ?? []); $codeColumn = $fields['code'] ?? 'Code'; $nameColumn = $fields['name'] ?? 'Name'; $tableName = $resource; foreach ([$codeColumn, $nameColumn, $tableName] as $ident) { if (!preg_match('~^[A-Za-z_][A-Za-z0-9_]*$~', $ident)) { throw new InvalidArgumentException("Bad identifier: {$ident}"); } } $requiredCodes = []; foreach ($linkedRows as $r) { $val = $r['sourceValueId']; if ($val !== null && $val !== '') { $requiredCodes[(string)$val] = true; } } if ($requiredCodes) { $placeholders = []; $bind = []; $i = 0; foreach (array_keys($requiredCodes) as $codeVal) { $ph = ":p{$i}"; $placeholders[] = $ph; $bind[$ph] = $codeVal; $i++; } $sql = "SELECT
                            {$codeColumn} AS code,
                            {$nameColumn} AS name
                          FROM {$tableName}
                         WHERE {$codeColumn} IN (" . implode(',', $placeholders) . ")"; $misRows = (array)selectSql($db, $sql, $bind); foreach ($misRows as $mr) { $code = $mr['code']; $name = (string)($mr['name'] ?? ''); if ($code !== null && $name !== '') { $codeToReadableName[(string)$code] = $name; } } } } } $result = []; foreach ($linkedRows as $row) { $sourceValueId = $row['sourceValueId']; $resolvedName = ($sourceValueId !== null) ? ($codeToReadableName[(string)$sourceValueId] ?? '') : ''; $result[] = [ 'id' => (int)$row['id'], 'sourceValueId' => $sourceValueId, 'sourceValueString' => (string)($row['sourceValueString'] ?? ''), 'name' => $resolvedName, ]; } return $result; } public function readDictResourceValue(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $identifier = isEmptyProperty($params, 'identifier'); if ($identifier) { $identifier = "WHERE CR.Identifier LIKE '$identifier'"; } return selectSql($db, <<<SQL
            SELECT
                CRV.CiszResourceValueCode AS "id",
                CRV.Code AS "code",
                CRV.Name AS "name"
            FROM
                CiszResourceValue CRV
                JOIN CiszResource CR ON CRV.CiszResourceCode = CR.CiszResourceCode
            $identifier
            ORDER BY 3, 2
        SQL
); } public function getDefaultValue($params = null) { $db = $this->db ?: $this->db = connectDb(); $identifier = isEmptyProperty($params, 'identifier'); $code = isEmptyProperty($params, 'code'); $sql = <<<"EOT"
SELECT
        CRV.CiszResourceValueCode "id",
        CRV.Name "name"
        FROM CiszResourceValue CRV
            JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = ?
        WHERE CRV.Code = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$identifier, $code]); return $stmt->fetch(PDO::FETCH_ASSOC); } } handleJsonRpc('med_cisz_dicts');