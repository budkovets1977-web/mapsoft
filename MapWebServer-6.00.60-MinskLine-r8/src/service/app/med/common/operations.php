<?php
 require_once(__DIR__.'/../../init.php'); class med_common_operations { private $db; public function readOperations($params = null) { $db = $this->db ?: $this->db = connectDb(); $operationType = isEmptyProperty($params, 'operationType', 0); $onlyNCSP = isEmptyProperty($params, 'onlyNCSP'); $search = isset($params->search) ? " AND (mo.name LIKE '%$params->search%' OR mo.cipher LIKE '%$params->search%') " : " "; $where = ''; if ($onlyNCSP) { $where = "
AND (
    (N.sysnumbercode = 2 AND child.CipherNCSP IS NOT NULL)
    OR
    (N.sysnumbercode = 1
        AND EXISTS (
            SELECT 1
            FROM MedOperation c
            WHERE c.medoperationparentcode = parent.medoperationcode
              AND c.CipherNCSP IS NOT NULL
        ))
    OR
    (N.sysnumbercode = 3
        AND (
            mo.CipherNCSP IS NOT NULL
            OR EXISTS (
                SELECT 1
                FROM MedOperation c
                WHERE c.medoperationparentcode = mo.medoperationcode
                  AND c.CipherNCSP IS NOT NULL
            )
        )
    )
)
    "; } $sql = <<<"EOT"
SELECT DISTINCT
    case N.sysnumbercode when 1 then parent.medoperationcode when 2 then child.medoperationcode else mo.medoperationcode end  "id",
    case N.sysnumbercode when 1 then parent.cipher when 2 then child.cipher else mo.cipher end "cipher",
    case N.sysnumbercode when 1 then parent.name when 2 then child.name else mo.name end "name",
    case N.sysnumbercode when 1 then parent.medoperationparentcode when 2 then child.medoperationparentcode else mo.medoperationparentcode end "parentId",
    case N.sysnumbercode when 1 then parent.operationtype when 2 then child.operationtype else mo.operationtype end "operationType",
    case N.sysnumbercode when 1 then parent.medprofessioncode when 2 then child.medprofessioncode else mo.medprofessioncode end "professionId",
    case N.sysnumbercode when 1 then parent.position when 2 then child.position else mo.position end "position",
    case N.sysnumbercode when 1 then parent.timenorm when 2 then child.timenorm else mo.timenorm end "timeNorm",
    case N.sysnumbercode when 1 then parent.visitcond when 2 then child.visitcond else mo.visitcond end "visitCond",
    case N.sysnumbercode when 1 then mp1.name when 2 then mp.name else mp.name end "profession",
    CASE N.sysnumbercode WHEN 1 THEN Parent.CipherNCSP WHEN 2 then Child.CipherNCSP ELSE MO.CipherNCSP END "codeNCSP",
    CASE N.sysnumbercode WHEN 1 THEN '' WHEN 2 then CRV.Name ELSE '' END "operationCiszName"
FROM
    MedOperation AS mo
    JOIN Sysnumber as N on N.sysnumbercode between 1 and 3
    LEFT OUTER JOIN MedOperation as parent on N.sysnumbercode = 1 and mo.medoperationparentcode = parent.medoperationcode
    LEFT OUTER JOIN MedOperation as child on N.sysnumbercode = 2 and mo.medoperationcode = child.medoperationparentcode and coalesce(child.operationtype,0) = $operationType
    LEFT JOIN MedProfession as mp1 on N.sysnumbercode = 1 and mp1.medprofessioncode = parent.medprofessioncode
    LEFT JOIN MedProfession as mp on N.sysnumbercode = 3 and mp.medprofessioncode = mo.medprofessioncode
    LEFT JOIN (
        SELECT V.Code, MIN(V.Name) AS Name
        FROM CiszResourceValue V
        JOIN CiszResource CR ON CR.CiszResourceCode = V.CiszResourceCode AND CR.Identifier = 'SurgicalInterventions'
        GROUP BY V.Code
    ) CRV ON CRV.Code = Child.CipherNCSP
WHERE
    coalesce(mo.operationType,0) = $operationType $search
    and ((N.sysnumbercode = 1 and parent.medoperationcode is not null) or (N.sysnumbercode = 2 and child.medoperationcode is not null) or N.sysnumbercode = 3)
    $where
    ORDER BY position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readOperationDesriptions($params = null) { $db = $this->db ?: $this->db = connectDb(); $medOperationCodes = isEmptyProperty($params, 'medOperationCodes', []); $where = ''; if (count($medOperationCodes)) { $where = 'WHERE MOD.MedOperationCode '.createSqlInCondition($medOperationCodes); } $sql = <<<"EOT"
SELECT
    MOD.MedOperationDescriptionCode "id",
    MOD.MedOperationCode "medoperationcode",
    MOD.Description "description"
FROM MedOperationDescription MOD
$where
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createOperations($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    insert into MedOperation (cipher, name, medoperationparentcode, position, operationtype,timenorm, visitcond, medprofessioncode, CipherNCSP)
    values (?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $parent = isEmptyProperty($row, 'parentId', 0); $operationType = isEmptyProperty($row, 'operationType', 0); $sqlMaxPosition = <<<"EOT"
SELECT
    max(position) "res"
FROM
    MedOperation
WHERE
        coalesce(medOperationParentCode,0) = $parent
        AND coalesce(operationType,0) = $operationType
EOT;
$stmtMaxPosition = $db->prepare($sqlMaxPosition); $stmtMaxPosition->execute(); $position = fetchValue($stmtMaxPosition, "res"); $position++; $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'parentId'), $position, isUndefinedProperty($row, 'operationType'), isUndefinedProperty($row, 'timeNorm'), isUndefinedProperty($row, 'visitCond'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'codeNCSP') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedOperation'); $rec->position = $position; $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createOperationDesriptions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
    insert into MedOperationDescription (medoperationcode, description)
    values (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'medoperationcode'), isUndefinedProperty($row, 'description') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedOperationDescription'); $ret[] = $rec; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateOperations($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedOperation set
       cipher = case when ? = 1 then ? else cipher end,
       name = case when ? = 1 then ? else name end,
       position = case when ? = 1 then ? else position end,
       timenorm = case when ? = 1 then ? else timenorm end,
       visitcond = case when ? = 1 then ? else visitcond end,
       medProfessionCode = case when ? = 1 then ? else medProfessionCode end,
       CipherNCSP = CASE WHEN ? = 1 THEN ? ELSE CipherNCSP END
where medOperationCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), propertyExistsSQL($row, 'position'), isEmptyProperty($row, 'position'), propertyExistsSQL($row, 'timeNorm'), isEmptyProperty($row, 'timeNorm'), propertyExistsSQL($row, 'visitCond'), isEmptyProperty($row, 'visitCond'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'codeNCSP'), isEmptyProperty($row, 'codeNCSP'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateOperationDesriptions($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedOperationDescription set
       description = case when ? = 1 then ? else description end
where MedOperationDescriptionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'description'), isUndefinedProperty($row, 'description'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteOperations($records) { $db = $this->db ?: $this->db = connectDb(); $opIn = createSqlInCondition($records, 'id'); $logSql = <<<SQL
            SELECT
                MAO.MedAmbulOperationCode
            FROM
                MedAmbulOperation MAO
                LEFT JOIN MedOperation MO ON MO.MedOperationCode = MAO.MedOperationCode
            WHERE
                (MO.MedOperationCode $opIn) OR
                (MO.MedOperationParentCode $opIn)
SQL;
$hasLinkedOperationLogEntries = selectSqlValue($db, $logSql) != null; if ($hasLinkedOperationLogEntries) { throw new Exception('Нельзя удалить запись, так как она связана с другими данными.'); } return deleteSql($db, 'MedOperation', 'MedOperationCode', $records); } public function deleteOperationDesriptions($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedOperationDescription', 'MedOperationDescriptionCode', $records); } public function getNextCipher($params) { $db = $this->db ?: $this->db = connectDb(); $operationType = isEmptyProperty($params, 'operationType', 0); $parentId = isEmptyProperty($params, 'parentId'); $search = isset($parentId) ? " AND medOperationParentCode = $parentId" : "AND medoperationparentcode is null "; $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(Cipher), 20)) as varchar(20))
    FROM
        MedOperation
    WHERE
        coalesce(operationType,0) = $operationType
        $search ORDER BY 1 DESC LIMIT 1)), '0')) AS "cipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'cipher'); return $cipher; } public function getCiszOperationName($params) { $db = $this->db ?: $this->db = connectDb(); $code = isEmptyProperty($params, 'codeNCSP'); $sql = "
        SELECT CRV.Name
        FROM CiszResourceValue CRV
        JOIN CiszResource CR
            ON CR.CiszResourceCode = CRV.CiszResourceCode
           AND CR.Identifier = ?
        WHERE CRV.Code = ?
        LIMIT 1
    "; return selectSqlValue( $db, $sql, ['SurgicalInterventions', $code] ); } } $service = new Junior\Server(new med_common_operations()); $service->process(); 