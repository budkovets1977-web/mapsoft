<?php
 require_once(__DIR__ . '/../../init.php'); class med_cisz_questionnaires { private $db; public function readQuestionnaires(): array { $db = $this->db ?: $this->db = connectDb(); return selectSql($db, <<<SQL
            SELECT
                ciszQuestionnaireCode AS "id",
                Name AS "name",
                Title AS "title",
                JSON AS "json"
            FROM
                ciszQuestionnaire
        SQL
); } public function createQuestionnaires($records): array { $db = $this->db ?: $this->db = connectDb(); return insertSql( $db, 'ciszQuestionnaire', $records, 'Name, Title, JSON', 'name, title, json' ); } public function updateQuestionnaires($records): bool { $db = $this->db ?: $this->db = connectDb(); return updateSql( $db, 'ciszQuestionnaire', 'ciszQuestionnaireCode', $records, 'Name, Title, JSON', 'name, title, json' ); } public function deleteQuestionnaires($records): bool { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'ciszQuestionnaire', 'ciszQuestionnaireCode', $records); } } handleJsonRpc('med_cisz_questionnaires');