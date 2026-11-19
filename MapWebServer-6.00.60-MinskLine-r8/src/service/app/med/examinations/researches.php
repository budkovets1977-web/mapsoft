<?php
require_once(__DIR__.'/../../init.php'); class med_examinations_researches { private $db; public function readResearches($params = null){ $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $researchId = isEmptyProperty($params, 'researchId'); $onlyOthers = isEmptyProperty($params, 'onlyOthers'); $where = ""; if ($id){ $where .= " AND ud.uniDictCode = $id "; } if ($researchId){ $where .= " AND mr.medResearchCode = $researchId "; } if ($onlyOthers){ $where .= " AND mr.medPreventTypeCode IS NOT NULL "; } $sql = <<<EOT
WITH answer AS (
    SELECT 
        string_agg(ud2.valuestring1,',') AS answers, 
        mq.unidictcode_question AS unidictcode_question,
        mq.medresearchcode,
        ud3.code
    FROM medquestionary mq
        LEFT JOIN unidict ud2 ON mq.unidictcode_answer = ud2.unidictcode
        LEFT JOIN unidict ud3 ON ud3.unidictcode = mq.unidictcode_question
    GROUP BY mq.unidictcode_question, mq.medresearchcode,ud3.code,ud3.position
    ORDER BY ud3.position 
)

SELECT
    ud.uniDictCode              "id",
    ud.value                    "name",
   -- ud.valueString1             "designation", -- анкетирование
    CASE mr.medPreventTypeCode IS NOT NULL
        WHEN TRUE THEN 4                    -- Прочие
        WHEN FALSE THEN (
             SELECT COALESCE(ms_.type, 0) -- Если ms_.type NULL, подставляем 0
        FROM medResearchRow mrr_
        LEFT JOIN medStudy ms_ ON ms_.medStudyCode = mrr_.medStudyCode
        LEFT JOIN htmTemplate ht_ ON ht_.htmTemplateCode = mrr_.htmTemplateCode
        WHERE mrr_.medResearchCode = mr.medResearchCode
        ORDER BY mrr_.medStudyCode NULLS LAST -- Сортируем, чтобы сначала шли записи с заполненным medStudyCode
        LIMIT 1
        )
    END                         "researchTypeId",
    CASE mr.MedPreventTypeCode IS NOT NULL
        WHEN TRUE THEN (
            SELECT name
            FROM medPreventType
            WHERE medPreventTypeCode = mr.medPreventTypeCode
        )
        WHEN FALSE THEN (
            SELECT STRING_AGG(
                COALESCE(ms_.name, ht.name)::TEXT, ',' 
                ORDER BY COALESCE(ms_.name, ht.name)
            )
            FROM medResearchRow mrr_
            LEFT JOIN medStudy ms_ ON ms_.medStudyCode = mrr_.medStudyCode
            LEFT JOIN htmtemplate ht ON ht.htmtemplatecode = mrr_.htmtemplatecode
            WHERE mrr_.medResearchCode = mr.medResearchCode
        )
    END                         "docType",
    mr.medResearchCode          "researchId",
    mr.medPreventTypeCode       "docTypeId",
    mr.rowCode131uDV            "codeUdv",
    mr.rowCode131uDR            "codeUdr"/*,
    CASE WHEN mr.medResearchCode IS NOT NULL THEN
    string_agg(a.code || CASE WHEN a.code IS NOT NULL THEN '-' END || a.answers, ',')
    ELSE 'Недоступно' END       "questionary"*/--анкетирование
FROM uniDict ud
    LEFT JOIN medResearch mr ON mr.uniDictCode = ud.uniDictCode
    LEFT JOIN answer a ON a.medresearchcode = mr.medresearchcode
WHERE ud.dictNumber = 28
    $where
GROUP BY 1,mr.medresearchcode,mr.medPreventTypeCode
ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $id ? $stmt->fetch() : $stmt->fetchAll(); } public function createResearches($record){ $db = $this->db ?: $this->db = connectDb(); $param = new stdClass; if (!is_int($record->id)){ $sqlUniDict = <<<EOT
                INSERT INTO uniDict (dictNumber, value, valueString1)
                VALUES (28, ?, ?)
EOT;
$stmtUniDict = $db->prepare($sqlUniDict); $stmtUniDict->execute([ isEmptyProperty($record, 'name'), isEmptyProperty($record, 'designation') ]); $param->id = $record->id = (int) $db->lastInsertId('seq_UniDict'); } $sql = <<<EOT
            INSERT INTO medResearch (uniDictCode, medPreventTypeCode, rowCode131uDV, rowCode131uDR)
            VALUES (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($record, 'id'), isEmptyProperty($record, 'docTypeId'), isEmptyProperty($record, 'codeUdv'), isEmptyProperty($record, 'codeUdr') ]); $param->researchId = (int)$db->lastInsertId('seq_MedResearch'); $ret = isEmptyProperty($record, 'docTypeId') ? $this->readResearches($param) : $param; return $ret; } public function updateResearches($record){ $db = $this->db ?: $this->db = connectDb(); $sqlUniDict = <<<EOT
            UPDATE uniDict
            SET
                value =         CASE WHEN ? = 1 THEN ? ELSE value END,
                valueString1 =  CASE WHEN ? = 1 THEN ? ELSE valueString1 END
            WHERE uniDictCode = ?
EOT;
$sql = <<<EOT
            UPDATE medResearch
            SET
                medPreventTypeCode =    CASE WHEN ? = 1 THEN ? ELSE medPreventTypeCode END,
                rowCode131uDV =         CASE WHEN ? = 1 THEN ? ELSE rowCode131uDV END,
                rowCode131uDR =         CASE WHEN ? = 1 THEN ? ELSE rowCode131uDR END
            WHERE medResearchCode = ?
EOT;
$stmtUniDict = $db->prepare($sqlUniDict); $stmt = $db->prepare($sql); $stmtUniDict->execute([ propertyExistsSQL($record, 'name'), isEmptyProperty($record, 'name'), propertyExistsSQL($record, 'designation'), isEmptyProperty($record, 'designation'), isEmptyProperty($record, 'id') ]); $stmt->execute([ propertyExistsSQL($record, 'docTypeId'), isEmptyProperty($record, 'docTypeId'), propertyExistsSQL($record, 'codeUdv'), isEmptyProperty($record, 'codeUdv'), propertyExistsSQL($record, 'codeUdr'), isEmptyProperty($record, 'codeUdr'), isEmptyProperty($record, 'researchId') ]); $param = new stdClass; $param->id = isEmptyProperty($record, 'id'); $param->designation = isEmptyProperty($record, 'designation'); $param->researchId = isEmptyProperty($record, 'researchId'); $ret = isEmptyProperty($record, 'docTypeId') ? $this->readResearches($param) : $param; return $ret; } public function insertOrUpdateResearches($record){ return isEmptyProperty($record, 'researchId') ? $this->updateResearches($record) : $this->createResearches($record); } public function deleteResearches($records){ $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'uniDict', 'uniDictCode', $records); } public function readResearchTemplates($params = null){ $db = $this->db ?: $this->db = connectDb(); $researchId = isNullProperty($params, 'researchId', 0); $condResearch = "WHERE MRR.medResearchCode = $researchId"; $sql = <<<EOT
        SELECT
        MRR.medResearchRowCode AS "id",
        MRR.medResearchCode AS "researchId",
        COALESCE(MRR.medStudyCode, MRR.htmtemplatecode) AS "templateId",
        COALESCE(MS.code, HT.code) AS "templateCode",
        COALESCE(MS.name, HT.name) AS "templateName",
        COALESCE(MS.type, HT.type) AS "type",
        MRR.resultTemplate AS "resultMask"
        FROM medResearchRow MRR
        LEFT JOIN MedStudy MS ON MS.MedStudyCode = MRR.medStudyCode
        LEFT JOIN htmTemplate HT ON HT.htmTemplateCode = MRR.htmTemplateCode
            $condResearch
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdateResearchTemplates($records){ if (is_object($records)){ $records = [$records]; } foreach ($records as $row){ if (isEmptyProperty($row, 'researchId')){ is_int(isEmptyProperty($row, 'id')) ? $this->updateResearchTemplates($row) : $this->createResearchTemplates($row); } } return true; } public function updateResearchTemplates($record){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE medResearchRow
            SET
                resultTemplate = CASE WHEN ? = 1 THEN ? ELSE resultTemplate END
            WHERE MedResearchRowCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($record, 'resultMask'), isEmptyProperty($record, 'resultMask'), isEmptyProperty($record, 'id') ]); return true; } public function createResearchTemplates($record){ if ($record) { $db = $this->db ?: $this->db = connectDb(); $type = $record->type; if ($type === 0) { $sql = <<< EOT
            INSERT INTO medResearchRow (medResearchCode, htmtemplatecode, resultTemplate)
            VALUES (?, ?, ?)
EOT;
} else { $sql = <<< EOT
            INSERT INTO medResearchRow (medResearchCode, medStudyCode, resultTemplate)
            VALUES (?, ?, ?)
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($record, 'researchId'), isEmptyProperty($record, 'templateId'), isEmptyProperty($record, 'resultMask') ]); return true; } else { return true; } } public function deleteResearchTemplates($records){ $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'medResearchRow', 'medResearchRowCode', $records->records); } } handleJsonRpc('med_examinations_researches');