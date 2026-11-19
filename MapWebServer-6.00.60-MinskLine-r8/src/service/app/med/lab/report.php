<?php
 require_once(__DIR__.'/../../init.php'); class med_lab_report { private $db; public function readReport($params) { $db = $this->db ?: $this->db = connectDb(); $type = isset($params->type) && isEmptyProperty($params, 'type') ? $params->type : "null"; $zero = !is_null($type) && !isset($params->skipReportTypeZero) ? "OR MSR.Type = 0" : ""; $sql = <<<"EOT"
SELECT
       MSR.MedStudyReportCode "id",
       MSR.Name "name",
       MSR.Form "form",
       MSR.Type "type"
    FROM MedStudyReport MSR
    WHERE MSR.Type = $type $zero
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readReportStudy($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isUndefinedProperty($params, 'type'); if ($type && $type > 1000) { $sql = <<<"SQL"
SELECT
       HT.HtmTemplateCode "studyId",
       HT.Name "name",
       MSI.MedStudyReportInCode "id",
       MSI.MedStudyReportCode "reportId",
       MSI.HtmTemplateCode "templateCode"
    FROM HtmTemplate HT
    LEFT JOIN MedStudyReportIn MSI ON MSI.HtmTemplateCode = HT.HtmTemplateCode
    WHERE
        HT.HtmPageCode IS NOT NULL AND
        HT.Type = ($type - 1000)
SQL;
} else { $sql = <<<"SQL"
SELECT
       MS.MedStudyCode "studyId",
       MS.Name "name",
       MSI.MedStudyReportInCode "id",
       MSI.MedStudyReportCode "reportId",
       MSI.HtmTemplateCode "templateCode"
    FROM MedStudy MS
    LEFT JOIN MedStudyReportIn MSI ON MSI.MedStudyCode = MS.MedStudyCode
SQL;
} $stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readUnitName($params) { $db = $this->db ?: $this->db = connectDb(); $reportId = isUndefinedProperty($params, 'reportId'); $isSetDoc = isUndefinedProperty($params, 'isSetDoc'); $sqlParams = []; $sqlJoin = 'JOIN MedStudy S ON S.MedStudyCode = I.MedStudyCode'; if ($isSetDoc) { $sqlJoin = 'JOIN HtmTemplate S ON S.HtmTemplateCode = I.HtmTemplateCode'; } $sqlParams[':reportId'] = $reportId; $sql = <<<SQL
            SELECT DISTINCT U.Name
            FROM MedStudyReportIn I
            $sqlJoin
            JOIN HtmUnit U ON U.HtmPageCode = S.HtmPageCode
            WHERE I.MedStudyReportCode = :reportId
            ORDER BY U.Name
            SQL;
return selectSql($db, $sql, $sqlParams); } public function readStudyColumn($params = null) { $db = $this->db ?: $this->db = connectDb(); $type = isUndefinedProperty($params, 'type'); $sqlWhen = <<<"SQL"
    WHEN 1 THEN 'Номер п/п'
    WHEN 2 THEN 'Дата исследования'
    WHEN 3 THEN 'Пациент - ФИО'
    WHEN 4 THEN 'Пациент - Дата рождения'
    WHEN 5 THEN 'Пациент - Пол'
    WHEN 6 THEN 'Номер амбулаторной карты'
    WHEN 7 THEN 'Пациент - Адрес'
    WHEN 8 THEN 'Диагноз'
    WHEN 9 THEN 'Отделение'
    WHEN 10 THEN 'Врач направивший'
    WHEN 11 THEN 'Врач лаборатории'
    WHEN 12 THEN 'Лаборант'
    WHEN 13 THEN 'Заключение'
    WHEN 14 THEN 'Дата выдачи заключения'
    WHEN 15 THEN 'Элемент'
    WHEN 16 THEN 'Номер направления'
    WHEN 17 THEN 'Дата забора материала'
SQL;
if ($type > 1000) { $sqlWhen = <<<"SQL"
    WHEN 1 THEN 'Номер п/п'
    WHEN 2 THEN 'Дата посещения'
    WHEN 3 THEN 'Пациент - ФИО'
    WHEN 4 THEN 'Пациент - Дата рождения'
    WHEN 5 THEN 'Пациент - Пол'
    WHEN 6 THEN 'Номер амбулаторной карты'
    WHEN 7 THEN 'Пациент - Адрес'
    WHEN 8 THEN 'Шифр диагноза'
    WHEN 9 THEN 'Отделение'
    WHEN 10 THEN 'ФИО врача'
    WHEN 11 THEN 'Элемент'
SQL;
} $sql = <<<"SQL"
SELECT
    MSC.MedStudyReportColumnCode "id",
    MSC.ColumnType "columnType",
    CASE MSC.ColumnType
    $sqlWhen
    ELSE '' END "columnName",
    MSC.MedStudyCode "studyId",
    MSC.MedStudyReportCode "reportId",
    MSC.Name1 "name1",
    MSC.Name2 "name2",
    MSC.Name3 "name3",
    MSC.Position "position",
    MSC.UnitName "unitName",
    MSC.WidthPercent "widthPercent"
    FROM MedStudyReportColumn MSC
    ORDER BY MSC.Position ASC
SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function syncReport($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
insert into MedStudyReport (MedStudyReportCode, Name, Form, Type)
values (?, ?, ?, ?)
on conflict (MedStudyReportCode) do update set
    Name = case when ? = 1 then EXCLUDED.Name else MedStudyReport.Name end,
    Form = case when ? = 1 then EXCLUDED.Form else MedStudyReport.Form end,
    Type = case when ? = 1 then EXCLUDED.Type else MedStudyReport.Type end
        RETURNING MedStudyReportCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ is_numeric($row->id) ? $row->id : null, isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'form'), isUndefinedProperty($row, 'type'), propertyExistsSQL($row, 'name'), propertyExistsSQL($row, 'form'), propertyExistsSQL($row, 'type') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $rec; } public function createStudyColumn($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            INSERT INTO MedStudyReportColumn (
                ColumnType, MedStudyCode, MedStudyReportCode,
                Name1, Name2, Name3,
                Position, UnitName, WidthPercent
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                COALESCE(
                    (
                        SELECT MAX(Position) "position"
                        FROM MedStudyReportColumn
                        WHERE MedStudyReportCode = ?
                    ), 0
                ) + 1, ?, ?
            )
            RETURNING MedStudyReportColumnCode AS id
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $typeStmt = $db->prepare("
            SELECT Type
            FROM MedStudyReport
            WHERE MedStudyReportCode = ?
        "); $db->beginTransaction(); try { foreach ($records as $row) { $unitName = isUndefinedProperty($row, 'unitName'); $typeStmt->execute([isEmptyProperty($row, 'reportId')]); $reportType = (int)$typeStmt->fetchColumn(); if (safe_strpos($unitName, ',') !== false) { if ($reportType >= 1001) { $unitName = str_replace(',', ';', $unitName); } else { $unitName = str_replace(',', '; ', $unitName); } } $stmt->execute([ isUndefinedProperty($row, 'columnType'), isEmptyProperty($row, 'studyId'), isEmptyProperty($row, 'reportId'), isUndefinedProperty($row, 'name1'), isUndefinedProperty($row, 'name2'), isUndefinedProperty($row, 'name3'), isUndefinedProperty($row, 'reportId'), $unitName, isUndefinedProperty($row, 'widthPercent'), ]); $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $rec; } public function updateStudyColumn($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedStudyReportColumn set
       ColumnType = case when ? = 1 then ? else ColumnType end,
       MedStudyCode = case when ? = 1 then ? else MedStudyCode end,
       MedStudyReportCode = case when ? = 1 then ? else MedStudyReportCode end,
       Name1 = case when ? = 1 then ? else Name1 end,
       Name2 = case when ? = 1 then ? else Name2 end,
       Name3 = case when ? = 1 then ? else Name3 end,
       Position = case when ? = 1 then ? else Position end,
       UnitName = case when ? = 1 then ? else UnitName end,
       WidthPercent = case when ? = 1 then ? else WidthPercent end
where MedStudyReportColumnCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $typeStmt = $db->prepare("
            SELECT MR.Type
            FROM MedStudyReport MR
            JOIN MedStudyReportColumn MC ON MR.MedStudyReportCode = MC.MedStudyReportCode
            WHERE MedStudyReportColumnCode = ?
        "); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, 0, null, 0, null, 0, null, 0, null, 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, $row->id ]); } foreach ($records as $row) { $unitName = isUndefinedProperty($row, 'unitName'); $typeStmt->execute([isEmptyProperty($row, 'id')]); $reportType = (int)$typeStmt->fetchColumn(); if (safe_strpos($unitName, ',') !== false) { if ($reportType >= 1001) { $unitName = str_replace(',', ';', $unitName); } else { $unitName = str_replace(',', '; ', $unitName); } } $stmt->execute([ propertyExistsSQL($row, 'columnType'), isUndefinedProperty($row, 'columnType'), propertyExistsSQL($row, 'studyId'), isUndefinedProperty($row, 'studyId'), propertyExistsSQL($row, 'reportId'), isUndefinedProperty($row, 'reportId'), propertyExistsSQL($row, 'name1'), isUndefinedProperty($row, 'name1'), propertyExistsSQL($row, 'name2'), isUndefinedProperty($row, 'name2'), propertyExistsSQL($row, 'name3'), isUndefinedProperty($row, 'name3'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'unitName'), $unitName, propertyExistsSQL($row, 'widthPercent'), isUndefinedProperty($row, 'widthPercent'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createReportStudy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"SQL"
INSERT INTO MedStudyReportIn (MedStudyCode, MedStudyReportCode, HtmTemplateCode)
VALUES (?, ?, ?)
       RETURNING MedStudyReportInCode AS id
SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'studyId'), isEmptyProperty($row, 'reportId'), isEmptyProperty($row, 'templateCode') ]); $rec = new stdClass; $log = $stmt->fetch(); $rec->id = $log['id']; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $rec; } public function updateReportStudy($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"SQL"
UPDATE MedStudyReportIn SET
       MedStudyCode = CASE WHEN ? = 1 THEN ? ELSE MedStudyCode END,
       MedStudyReportCode = CASE WHEN ? = 1 THEN ? ELSE MedStudyReportCode END,
       HtmTemplateCode = CASE WHEN ? = 1 THEN ? ELSE HtmTemplateCode END
WHERE MedStudyReportInCode = ?
SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'studyId'), isUndefinedProperty($row, 'studyId'), propertyExistsSQL($row, 'reportId'), isUndefinedProperty($row, 'reportId'), propertyExistsSQL($row, 'templateCode'), isUndefinedProperty($row, 'templateCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteReport($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedStudyReport', 'MedStudyReportCode', $records); } public function deleteReportStudy($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sqlSRC = <<<SQL
            SELECT MedStudyReportCode
            FROM MedStudyReportIn
            WHERE MedStudyReportInCode = ?
            LIMIT 1
            SQL;
$sqlCount = <<<SQL
            SELECT COUNT(*)
            FROM MedStudyReportIn
            WHERE MedStudyReportCode = ?
            SQL;
$sqlDelReport = <<<SQL
            DELETE FROM MedStudyReportIn
            WHERE MedStudyReportInCode = ?
            SQL;
$sqlDelColumn = <<<SQL
            DELETE FROM MedStudyReportColumn
            WHERE MedStudyReportCode = ?
            SQL;
$db->beginTransaction(); try { foreach ($records as $row) { $medStudyReportInId = $row->id; $medStudyReportId = selectSqlValue($db, $sqlSRC, [ $medStudyReportInId ]); executeSql($db, $sqlDelReport, [ $medStudyReportInId ]); $docCount = (int)selectSqlValue($db, $sqlCount, [ $medStudyReportId ]); if ($docCount === 0) { executeSql($db, $sqlDelColumn, [ $medStudyReportId ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteStudyColumn($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedStudyReportColumn', 'MedStudyReportColumnCode', $records); } } handleJsonRpc('med_lab_report');