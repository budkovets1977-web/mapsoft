<?php
 require_once(__DIR__.'/../../init.php'); class map_med_studiesSelect { private $db; public function readStudiesSelect() { $db = $this->db ? : $this->db = connectDb(); $needSOLISTests = (int) getSessionValue('mapWebTaskCode') == 502; $sqlTests = ""; if ($needSOLISTests) { $sqlTests = <<<"EOT"
    ,
    (select array_to_json(array_agg(row_to_json(t)))
    from (
        select
            st.MedLabStudyTestCode as "id",
            st.Name as "name",
            st.UnitName as "unitName",
            st.LoincCode as "LoincCode",
            st.InternalId as "InternalId"
        from MedLabStudy as s
        join MedLabStudyTest as st on st.MedLabStudyCode = s.MedLabStudyCode
        where s.MedStudyCode = ms.MedStudyCode
            and s.Active = 1
            and st.Active = 1
            and coalesce(st.UnitName, '') <> ''
        ) as t
    ) as "solisTests"
EOT;
} $sql = <<<"EOT"
select
    MS.MedStudyCode "id",
    MS.MedStudyCodeParent "parentId",
    MS.Code "code",
    MS.Name "name",
    MS.Type "type"
    $sqlTests
from MedStudy MS
join htmPageT as p on p.htmPageCode = ms.htmPageCode
where MS.MedStudyCodeParent is not null
    and p.isTemplate = 1
order by MS.Type, MS.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = []; $g = null; while ($row = $stmt->fetch()) { if (!$g || $g->type !== $row['type']) { $ret[] = $g = new stdClass(); $g->id = $row['type'] * -1; $g->parentid = 0; $g->code = ''; $g->type = $row['type']; switch ($g->type) { case 1: $g->display = 'Лабораторные исследования'; break; case 2: $g->display = 'Лучевые исследования'; break; case 3: $g->display = 'Функциональные исследования'; break; } $g->expanded = false; } $g->children[] = $p = new stdClass(); $p->id = $row['id']; $p->parentId = $g->id; $p->code = $row['code']; $p->display = $row['name']; $p->type = $row['type']; $p->leaf = true; $p->checked = false; $st = false; if ((string) $row['solisTests']) { $st = json_clean_decode((string) $row['solisTests']); } if ($needSOLISTests && is_array($st)) { unset($p->checked); $p->leaf = false; foreach ($st as $v) { $p->children[] = $t = new stdClass(); $t->id = $p->id * $v->id; $t->parentId = $p->id; $t->code = $v->LoincCode; $t->LoincCode = $v->LoincCode; $t->InternalId = $v->InternalId; $t->display = $v->unitName; $t->type = $p->type."001"; $t->leaf = true; $t->isTest = true; $t->checked = false; } $p->expanded = false; } } return ['children' => $ret]; } } $service = new Junior\Server(new map_med_studiesSelect()); $service->process(); 