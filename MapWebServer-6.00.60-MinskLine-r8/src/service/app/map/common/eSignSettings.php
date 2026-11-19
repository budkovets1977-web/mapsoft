<?php
 require_once(__DIR__.'/../../init.php'); class map_common_eSignSettings { protected $db; public function readUsers () { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    wu.UserCode "id",
    wu.Nick "nick",
    wu.FIO "fio",
    su.Type "signType",
    CASE WHEN EXISTS(SELECT 1 FROM MedDoctor WHERE UserCode = wu.UserCode) THEN 'Врач' END "categories",
    we.WebEnterpriseCode "enterpriseId",
    we.Name "enterpriseName"
FROM
    WebUser wu
    JOIN WebEnterprise we ON we.WebEnterpriseCode = wu.WebEnterpriseCode
        AND we.Active = 1
    LEFT OUTER JOIN signUser su on su.UserCode = wu.UserCode
    LEFT OUTER JOIN MedDoctor md on md.UserCode = wu.UserCode
WHERE
    wu.ThisRole IS NULL
    AND wu.Active = 1
    AND wu.UserCode IS NOT NULL
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $res = $stmt->fetchAll(PDO::FETCH_OBJ); return $res; } public function updateUsers($params) { $db = $this->db ?: $this->db = connectDb(); if (is_object($params)) { $params = [$params]; } $db->beginTransaction(); try { $sql = <<<"EOT"
insert into signUser (UserCode, Type) values (?,?)
on conflict (UserCode) do update set Type = excluded.Type
EOT;
$stmt = $db->prepare($sql); foreach($params as $rec) { $stmt->execute([$rec->id, $rec->signType]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } } handleJsonRpc('map_common_eSignSettings');