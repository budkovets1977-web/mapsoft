<?php
 require_once(__DIR__.'/../../init.php'); class med_sickdays_disabilityType { private $db; const CODE_UNIQUE_ERROR_CODE = -101; function __construct() { $this->db = connectDb(); } public function readDisabilityType($params = []) { $db = $this->db; $active = isEmptyProperty($params, 'active'); $onlyNoICD = isUndefinedProperty($params, 'onlyNoICD'); $allIfNoICDEmpty = isUndefinedProperty($params, 'allIfNoICDEmpty'); $disabilityTypeIds = isEmptyProperty($params, 'disabilityTypeIds'); $sqlParams = []; $sqlWhere = ''; $sqlWhereNoICD = ''; if (isset($active)) { $sqlParams[':active'] = $active ? 0 : 1; $sqlWhere .= ' AND UD.Inactive = :active'; } if (isset($onlyNoICD)) { if ($onlyNoICD) { $sqlWhereNoICD .= ' AND UD.ValueInteger = 1'; } else { $sqlWhereNoICD .= ' AND (UD.ValueInteger IS NULL OR UD.ValueInteger = 0)'; } } if (!empty($disabilityTypeIds) && is_array($disabilityTypeIds)) { $placeholders = []; foreach ($disabilityTypeIds as $index => $id) { $paramName = ':disabilityTypeId_' . $index; $sqlParams[$paramName] = $id; $placeholders[] = $paramName; } if (!empty($placeholders)) { $placeholdersStr = implode(', ', $placeholders); $sqlWhere .= " AND UniDictCode IN ($placeholdersStr)"; } } $sqlMain = <<<SQL
            SELECT
                UD.UniDictCode              "id",
                UD.ValueText                "name",
                UD.Code                     "code",
                CASE UD.Inactive
                    WHEN 1 THEN 0
                    WHEN 0 THEN 1
                END                         "active",
                UD.ValueInteger             "noICD",    -- Без МКБ
                MDTD.DiagnosisTerminalIds   "diagnosisTerminalIds"
            FROM UniDict UD
            LEFT JOIN (
                SELECT
                    UniDictCodeTempDisabilityType,
                    STRING_AGG(MedDiagnosisTerminalCode::TEXT, ',') AS DiagnosisTerminalIds
                FROM MedDiagnosisTerminalDisabilityType
                GROUP BY UniDictCodeTempDisabilityType
            ) MDTD ON MDTD.UniDictCodeTempDisabilityType = UD.UniDictCode
            WHERE UD.DictNumber = 54
            SQL;
$sqlOrderBy = <<<SQL
            ORDER BY RIGHT(REPEAT(E' ', 20) + ASA_TRIM(UD.Code), 20)
            SQL;
$sql = <<<SQL
            $sqlMain
            $sqlWhere
            $sqlWhereNoICD
            $sqlOrderBy
            SQL;
$ret = selectSql($db, $sql, $sqlParams); if ($onlyNoICD && $allIfNoICDEmpty && count($ret) < 1) { $sql = <<<SQL
                $sqlMain
                $sqlWhere
                $sqlOrderBy
                SQL;
$ret = selectSql($db, $sql, $sqlParams); } return $ret; } public function updateDisabilityType($records) { $db = $this->db; $sql = <<<"EOT"
 update UniDict  set
    ValueText = case when ? = 1 then ? else ValueText end,
    Code = case when ? = 1 then ? else Code end,
    Inactive = case when ? = 1 then ? else Inactive end,
    ValueInteger = case when ? = 1 then ? else ValueInteger end
where UniDictCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $code = isUndefinedProperty($row, 'code'); $code && $this->checkCodeUnique($db, $code); $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'code'), $code, propertyExistsSQL($row, 'active'), falseTo0(!isUndefinedProperty($row, 'active')), propertyExistsSQL($row, 'noICD'), isUndefinedProperty($row, 'noICD'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } protected function checkCodeUnique ($db, $code) { $sql = <<<"EOT"
SELECT EXISTS(
    SELECT
        UniDictCode
    FROM UniDict
    WHERE dictNumber = 54 AND code = :code
)
EOT;
$result = selectSqlValue($db, $sql, [ ':code' => $code ]); if ($result) { throw new Exception("Запись с кодом $code уже существует", self::CODE_UNIQUE_ERROR_CODE); } } public function createDisabilityType($records) { $db = $this->db; $sql = <<<SQL
            INSERT INTO UniDict (
                ValueText, Code, Inactive,
                ValueInteger, DictNumber
            )
            VALUES (
                ?, ?, ?,
                ?, 54
            )
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $code = isUndefinedProperty($row, 'code'); $this->checkCodeUnique($db, $code); $ret[] = $rec = new stdClass; $rec->id = selectSqlValue($db, $sql, [ isUndefinedProperty($row, 'name'), $code, falseTo0(!isUndefinedProperty($row, 'active')), isUndefinedProperty($row, 'noICD'), ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteDisabilityType($records) { $db = $this->db; return deleteSql($db, 'UniDict', 'UniDictCode', $records); } } handleJsonRpc('med_sickdays_disabilityType');