<?php
 require_once(__DIR__ . '/../../init.php'); class med_cisz_studies { private $db; public function readCiszStudy(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); return selectSql($db, <<<SQL
        SELECT CS.CiszStudyCode AS "id",
        CS.Note AS "studyNote",
        CRV.Name AS "studyName",
        CRV.CiszResourceValueCode AS "ciszStudyId",
        COALESCE(array_agg(MS.Name), '{}') "studyTemplate",
        COALESCE(array_agg(MS.MedStudyCode), '{}') "templateId",
        COALESCE(array_agg(H.isTemplate), '{}') "templateType",
        CASE
            WHEN CR.Identifier = 'UltrasoundTypes' THEN 2
            WHEN CR.Identifier = 'VSDiagnosticMethodsFD' THEN 3
            WHEN CR.Identifier = 'DiagnosticImagingStudy' THEN 4
            ELSE ''
        END "medStudyType",
        CASE
            WHEN CR.Identifier = 'UltrasoundTypes' THEN 'УЗИ диагностика'
            WHEN CR.Identifier = 'VSDiagnosticMethodsFD' THEN 'Функциональная диагностика'
            WHEN CR.Identifier = 'DiagnosticImagingStudy' THEN 'Рентгеновская диагностика'
            ELSE ''
        END "medStudyTypeName",
        CS.CiszResourceValueCodeCategory "categoryId",
        CRV2.Name "categoryName",
        CRV3.Name "codeName",
        CS.CiszResourceValueCodeServiceType "codeId"
        FROM CiszStudy CS
        JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = CS.CiszResourceValueCode
        JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
        JOIN CiszStudyList CSL ON CSL.CiszStudyCode = CS.CiszStudyCode
        JOIN MedStudy MS ON MS.MedStudyCode = CSL.MedStudyCode
        JOIN HtmPaget H on MS.HtmPageCode = H.HtmPageCode
        LEFT JOIN CiszResourceValue CRV2 ON CRV2.CiszResourceValueCode = CS.CiszResourceValueCodeCategory
        LEFT JOIN CiszResourceValue CRV3 ON CRV3.CiszResourceValueCode = CS.CiszResourceValueCodeServiceType
        GROUP BY CS.CiszStudyCode, CS.Note, CRV.Name, CRV.CiszResourceValueCode, MS.Type, CRV2.Name, CR.Identifier, CRV3.Name
        ORDER BY CRV.Name
        SQL
); } public function updateCiszStudy(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); if (empty($params->id)) { return ['success' => false]; } $studyCode = $params->id; $updateFields = []; $updateParams = [':studyCode' => $studyCode]; if (isset($params->studyNote)) { $updateFields[] = 'Note = :note'; $updateParams[':note'] = $params->studyNote; } if (isset($params->ciszStudyId)) { $updateFields[] = 'CiszResourceValueCode = :resCode'; $updateParams[':resCode'] = $params->ciszStudyId; } if (isset($params->categoryId)) { $updateFields[] = 'CiszResourceValueCodeCategory = :categoryId'; $updateParams[':categoryId'] = $params->categoryId; } if (isset($params->codeId)) { $updateFields[] = 'CiszResourceValueCodeServiceType = :codeId'; $updateParams[':codeId'] = $params->codeId; } if (!empty($updateFields)) { $sqlUpdate = 'UPDATE CiszStudy SET ' . implode(', ', $updateFields) . ' WHERE CiszStudyCode = :studyCode'; $stmt = $db->prepare($sqlUpdate); $stmt->execute($updateParams); } if (!empty($params->templateId)) { $incomingTemplates = is_array($params->templateId) ? $params->templateId : explode(',', $params->templateId); $stmt = $db->prepare("SELECT MedStudyCode FROM CiszStudyList WHERE CiszStudyCode = :studyCode"); $stmt->execute([':studyCode' => $studyCode]); $currentTemplates = $stmt->fetchAll(PDO::FETCH_COLUMN); $toAdd = array_diff($incomingTemplates, $currentTemplates); $stmtAdd = $db->prepare("INSERT INTO CiszStudyList (CiszStudyCode, MedStudyCode) VALUES (:studyCode, :medCode)"); foreach ($toAdd as $medCode) { $stmtAdd->execute([ ':studyCode' => $studyCode, ':medCode' => $medCode ]); } $toDelete = array_diff($currentTemplates, $incomingTemplates); if (!empty($toDelete)) { $stmtDel = $db->prepare( "DELETE FROM CiszStudyList 
                 WHERE CiszStudyCode = :studyCode AND MedStudyCode = ANY(:medCodes)" ); $stmtDel->execute([ ':studyCode' => $studyCode, ':medCodes' => '{' . implode(',', $toDelete) . '}' ]); } } return ['success' => true, 'id' => $studyCode]; } public function createCiszStudy(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $sqlMain = "INSERT INTO CiszStudy (Note, CiszResourceValueCode, CiszResourceValueCodeCategory, CiszResourceValueCodeServiceType)
                VALUES (:note, :resCode, :categoryId, :codeId)
                RETURNING CiszStudyCode"; $stmt = $db->prepare($sqlMain); $stmt->execute([ ':note' => $params->studyNote, ':resCode' => $params->ciszStudyId, ':categoryId' => $params->categoryId, ':codeId' => $params->codeId ]); $newCiszStudyCode = $stmt->fetchColumn(); if (!empty($params->templateId)) { $templateIds = is_array($params->templateId) ? $params->templateId : explode(',', $params->templateId); $sqlList = "INSERT INTO CiszStudyList (CiszStudyCode, MedStudyCode)
                VALUES (:studyCode, :medCode)"; $stmtList = $db->prepare($sqlList); foreach ($templateIds as $medCode) { $stmtList->execute([ ':studyCode' => $newCiszStudyCode, ':medCode' => $medCode ]); } } return [ 'success' => true, 'id' => $newCiszStudyCode ]; } public function deleteCiszStudy($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'CiszStudy', 'CiszStudyCode', $records); } public function readStudyValue(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $currentId = isEmptyProperty($params, 'currentId') ?: 0; $identifier = isEmptyProperty($params, 'identifier'); $identifier = $db->quote('%' . $identifier . '%'); return selectSql($db, <<<SQL
            SELECT
                CRV.CiszResourceValueCode AS "id",
                CRV.Name AS "name"
            FROM
                CiszResourceValue CRV
                JOIN CiszResource CR ON CRV.CiszResourceCode = CR.CiszResourceCode
            WHERE CR.Identifier LIKE $identifier
            AND CRV.CiszResourceValueCode NOT IN (SELECT CiszResourceValueCode 
                                                  FROM CiszStudy WHERE CiszResourceValueCode <> $currentId)
            ORDER BY 2
        SQL
); } public function readCiszStudyItem(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isEmptyProperty($params, 'studyId'); if ($studyId) { $studyId = "WHERE CSI.CiszStudyCode = $studyId"; } $sql = <<<SQL
        SELECT
        CSI.CiszStudyItemCode AS "id",
        CRV1.CiszResourceValueCode AS "itemId",
        CRV2.CiszResourceValueCode AS "measureId",
        CRV1.Name AS "name",
        CRV2.Name AS "indicatorMeasure",
        CSI.DataType AS "fieldType",
        COALESCE(string_agg(CRVL.Name, ', '), '') AS "itemValues"
        FROM CiszStudyItem CSI
        LEFT JOIN CiszResourceValue CRV1 
            ON CRV1.CiszResourceValueCode = CSI.CiszResourceValueCode
        LEFT JOIN CiszResourceValue CRV2 
            ON CRV2.CiszResourceValueCode = CSI.CiszResourceValueCodeUnit
        LEFT JOIN CiszStudyItemList CSIL
            ON CSIL.CiszStudyItemCode = CSI.CiszStudyItemCode
        LEFT JOIN CiszResourceValue CRVL
            ON CRVL.CiszResourceValueCode = CSIL.CiszResourceValueCode
        $studyId
        GROUP BY CSI.CiszStudyItemCode, CRV1.CiszResourceValueCode, CRV2.CiszResourceValueCode, CRV1.Name, CRV2.Name, CSI.DataType
        ORDER BY 4
    SQL;
return selectSql($db, $sql, []); } public function createCiszStudyItem(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = $params->studyId ?? null; $studyType = isEmptyProperty($params, 'studyType'); $ids = $params->ids ?? ''; if (!$studyId) { return ['success' => false, 'message' => 'studyId is required']; } $newIds = array_filter(array_map('trim', explode(',', $ids))); $sql = "SELECT CiszResourceValueCode FROM CiszStudyItem WHERE CiszStudyCode = ?"; $currentRows = selectSql($db, $sql, [$studyId]); $currentIds = array_column($currentRows, 'ciszresourcevaluecode'); $toAdd = array_diff($newIds, $currentIds); $toDelete = array_diff($currentIds, $newIds); if ($toDelete) { $in = implode(',', array_fill(0, count($toDelete), '?')); $sqlDelete = "DELETE FROM CiszStudyItem WHERE CiszStudyCode = ? AND CiszResourceValueCode IN ($in)"; $paramsDelete = array_merge([$studyId], $toDelete); executeSql($db, $sqlDelete, $paramsDelete); } if ($toAdd) { if ((int)$studyType === 2) { $api = new med_cisz_api(); foreach ($toAdd as $id) { $code = selectSqlValue($db, <<<SQL
                SELECT Code
                FROM CiszResourceValue
                WHERE CiszResourceValueCode = :id
            SQL,
[ ':id' => $id ]); if (!$code) { continue; } $inserted = $api->processStudyParameter( $db, $code, $studyId ); if (!$inserted) { executeSql($db,"INSERT INTO CiszStudyItem (CiszStudyCode, CiszResourceValueCode) VALUES (?, ?)", [$studyId, $id]); } } } else { $sqlInsert = "INSERT INTO CiszStudyItem (CiszStudyCode, CiszResourceValueCode) VALUES "; $values = []; $paramsInsert = []; foreach ($toAdd as $id) { $values[] = "(?, ?)"; $paramsInsert[] = $studyId; $paramsInsert[] = $id; } $sqlInsert .= implode(',', $values); executeSql($db, $sqlInsert, $paramsInsert); } } return ['success' => true, 'added' => $toAdd, 'deleted' => $toDelete]; } public function readAllCiszStudyItem(?object $params = null): array { $db = $this->db ?: $this->db = connectDb(); $search = isEmptyProperty($params, 'search'); $method = isEmptyProperty($params, 'method'); $studyType = isEmptyProperty($params, 'studyType'); $where = ''; switch ($studyType) { case 2: $where = "AND CR.Identifier LIKE 'UltrasoundParametres'"; break; case 3: $where = " AND (CR.Identifier LIKE 'QuantityParametersFD' OR CR.Identifier LIKE 'QualitativeParametersFD')"; break; } if ($search && $method) { switch ($method) { case 1: $search = "AND CRV.Name LIKE '$search%'"; break; case 2: $search = "AND CRV.Name LIKE '%$search%'"; break; case 3: $search = "AND CRV.Name LIKE '$search'"; break; } } return selectSql($db, <<<SQL
            SELECT
                CRV.CiszResourceValueCode AS "id",
                CRV.Code AS "code",
                CRV.Name AS "name"
            FROM
                CiszResourceValue CRV
                JOIN CiszResource CR ON CRV.CiszResourceCode = CR.CiszResourceCode
            WHERE 1 = 1 
            $where
            $search
            ORDER BY 3, 2
        SQL
); } public function readCurrentCiszStudyItem(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isEmptyProperty($params, 'studyId'); if ($studyId) { $studyId = "WHERE CSI.CiszStudyCode = $studyId"; } $sql = <<<SQL
        SELECT
        CRV1.CiszResourceValueCode AS "id",
        CRV1.Name AS "name"
        FROM CiszStudyItem CSI
        LEFT JOIN CiszResourceValue CRV1 
            ON CRV1.CiszResourceValueCode = CSI.CiszResourceValueCode
        $studyId
        ORDER BY 2
    SQL;
return selectSql($db, $sql, []); } public function deleteCiszStudyItem($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'CiszStudyItem', 'CiszStudyItemCode', $records); } public function updateCiszStudyItem(?object $params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $measureId = isEmptyProperty($params, 'measureId'); if (!$id) { return false; } try { if (!$measureId) { $sql = "UPDATE CiszStudyItem 
                    SET CiszResourceValueCodeUnit = NULL 
                    WHERE CiszStudyItemCode = :id"; $stmt = $db->prepare($sql); $stmt->bindValue(':id', $id, PDO::PARAM_INT); } else { $sql = "UPDATE CiszStudyItem 
                    SET CiszResourceValueCodeUnit = :measureId 
                    WHERE CiszStudyItemCode = :id"; $stmt = $db->prepare($sql); $stmt->bindValue(':measureId', $measureId, PDO::PARAM_INT); $stmt->bindValue(':id', $id, PDO::PARAM_INT); } $stmt->execute(); return true; } catch (Exception $e) { return false; } } } handleJsonRpc('med_cisz_studies');