<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_common_wealthGroups { const DEFAULT_SECTION_TYPE = 8; const DEFAULT_SECTION_NAME = "Медикаменты ПМУ"; private $db; public function checkWealthSection() { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $res = selectSqlValue($db, "select MatWealthSectionCode from MatWealthSection where SectionType = ?", [self::DEFAULT_SECTION_TYPE] ); if (!empty($res)) { return $res; } $sql = <<<'EOT'
insert into MatWealthSection (EnterpriseCode, SectionType, Name)
values (?, ?, ?)
EOT;
executeSql($db, $sql, [ $enterpriseCode, self::DEFAULT_SECTION_TYPE, self::DEFAULT_SECTION_NAME ]); return $db->lastInsertId('seq_MatWealthSection'); } public function readWealthGroups ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sectionId = $this->checkWealthSection(); $sql = <<<'EOT'
select
    MatWealthGroupCode as "id",
    Name as "name",
    Cipher as "cipher",
    Active as "active"
from MatWealthGroup
where MatWealthSectionCode = ?
EOT;
return selectSql($db, $sql, [$sectionId]); } public function createWealthGroups ($records) { $db = $this->db ?: $this->db = connectDb(); $sectionId = $this->checkWealthSection(); return insertSql($db, 'MatWealthGroup', $records, "MatWealthSectionCode, Name, Cipher, Active", "name, cipher, active", "$sectionId, ?, ?, ?" ); } public function updateWealthGroups ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MatWealthGroup', 'MatWealthGroupCode', $records, "Name, Cipher, Active", "name, cipher, active" ); } public function deleteWealthGroups ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MatWealthGroup', 'MatWealthGroupCode', $records); } } handleJsonRpc('pmed_common_wealthGroups');