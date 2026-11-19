<?php
 require_once(__DIR__.'/../../init.php'); class map_med_privileges { private $db; public function readPrivileges () { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    g.MedPrivilegeGroupCode || {$db->postgre("E")}'.' || p.MedPrivelegeCode "id",
    g.MedPrivilegeGroupCode "groupId",
    g.Cipher "groupCipher",
    g.Name "groupName",
    {$db->dialect("trim(g.Cipher || ' ' || g.Name)", "asa_trim(g.Cipher || E' ' || g.Name)")} "groupDisplay",
    p.MedPrivelegeCode "privilegeId",
    p.Cipher "privilegeCipher",
    p.Name "privilegeName",
    {$db->dialect("trim(p.Cipher || ' ' || p.Name)", "asa_trim(p.Cipher || E' ' || p.Name)")} "privilegeDisplay"
from MedPrivilegeGroup g
join MedPrivelege p on p.MedPrivilegeGroupCode = g.MedPrivilegeGroupCode
order by 5, 9
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = []; $g = null; while ($row = $stmt->fetch()) { if (!$g || $g->groupId !== (int)$row['groupId']) { $ret[] = $g = new stdClass; $g->id = (string)$row['groupId'].'.0'; $g->groupId = (int)$row['groupId']; $g->cipher = $row['groupCipher']; $g->name = $row['groupName']; $g->display = $row['groupDisplay']; $g->expanded = false; $g->checked = false; } $g->children[] = $p = new stdClass; $p->id = (string)$row['id']; $p->privilegeId = (int)$row['privilegeId']; $p->groupId = (int)$row['groupId']; $p->cipher = $row['privilegeCipher']; $p->name = $row['privilegeName']; $p->display = $row['privilegeDisplay']; $p->leaf = true; $p->checked = false; } return ['children' => $ret]; } } $service = new Junior\Server(new map_med_privileges()); $service->process(); 