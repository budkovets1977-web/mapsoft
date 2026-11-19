<?php
require_once(__DIR__.'/../../init.php'); class med_examinations_researchQuestionary { private $db; public function readResearchQuestionaryQuestions($params = null){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                uniDictCode id,
                code,
                valueString1 "name"
            FROM uniDict
            WHERE dictNumber = 74
                AND valueInteger = 1
            ORDER BY position
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function readResearchQuestionaryAnswers($params = null){ $db = $this->db ?: $this->db = connectDb(); $researchId = isUndefinedProperty($params, 'researchId'); $riskId = isUndefinedProperty($params, 'riskId'); $sql = <<<EOT
            WITH questions AS (
                SELECT
                    uniDictCode id,
                    code,
                    valueString1 "name"
                FROM uniDict
                WHERE dictNumber = 74
                    AND valueInteger = 1
                ORDER BY position
            ),
            answers AS (
                SELECT
                    uniDictCode id,
                    code,
                    valueString1 "name"
                FROM uniDict
                WHERE dictNumber = 75
                ORDER BY position
            ),
            questionary AS (
                SELECT
                    medQuestionaryCode id,
                    uniDictCode_question qid,
                    uniDictCode_answer aid,
                    medResearchCode "medResearchId",
                    unidictcode_riskfactor "riskId"
                FROM medQuestionary
                WHERE medResearchCode = :researchId OR unidictcode_riskfactor = :riskId
            )
            SELECT
                qu.id "questionaryId",
                q.id "questionId",
                a.id "answerId",
                a.code,
                a.name,
                CASE
                    WHEN qu.id IS NOT NULL THEN TRUE
                    ELSE FALSE
                END "checked",
                qu."medResearchId"
            FROM questions q
            CROSS JOIN answers a
            LEFT JOIN questionary qu ON qu.qid = q.id AND qu.aid = a.id
            ORDER BY q.id, a.code
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ 'researchId' => $researchId, 'riskId' => $riskId ]); $ret = $stmt->fetchAll(); return $ret; } public function updateResearchQuestionaryAnswers($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sqlDelete = <<<EOT
            DELETE FROM medQuestionary
            WHERE medQuestionaryCode = :questionaryId 
              AND ((1 = :isReserch AND unidictcode_riskfactor is NULL ) OR (0 = :isReserch AND medResearchCode is NULL))
EOT;
$stmtDelete = $db->prepare($sqlDelete); $sqlInsert = <<<EOT
            INSERT INTO medQuestionary (medResearchCode, uniDictCode_Question, uniDictCode_Answer, unidictcode_riskfactor)
            VALUES (:researchId, :questionId, :answerId, :riskId)
EOT;
$stmtInsert = $db->prepare($sqlInsert); $db->beginTransaction(); try { foreach ($records as $row){ $checked = isUndefinedProperty($row, 'checked'); $researchId = isUndefinedProperty($row, 'medResearchId'); $riskId = isUndefinedProperty($row, 'riskId'); $questionId = isUndefinedProperty($row, 'questionId'); $answerId = isUndefinedProperty($row, 'answerId'); $questionaryId = isUndefinedProperty($row, 'questionaryId'); $riskId = empty($riskId) ? null : $riskId; $researchId = empty($researchId) ? null : $researchId; $isReserch = empty($researchId) ? 0 : 1; if ($checked){ $stmtInsert->execute([ ':researchId' => $researchId, ':questionId' => $questionId, ':answerId' => $answerId, ':riskId' => $riskId, ]); } else { $stmtDelete->execute([ ':questionaryId' => $questionaryId, ':isReserch' => $isReserch ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_examinations_researchQuestionary');