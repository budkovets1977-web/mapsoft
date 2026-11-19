<?php
 require_once(__DIR__.'/../init.php'); class map_homevisit_eSign { protected $db; public function checkSettings () { $db = $this->db ?: $this->db = connectDb(); $signSettings = readExtraSettings($db, 'mapCommonESignSettings'); $ret = new stdClass(); $ntSignActive = isEmptyProperty($signSettings, 'ntSignActive') && selectSqlValue($db, 'select Type from signUser where UserCode = ?', [getSessionValue('mapUserCode')]) == 3; if ($ntSignActive) { $ret->ntSign = (object)[ 'url' => $signSettings->ntSignUrl, 'documents' => [] ]; } return $ret; } public function checkDocuments () { $db = $this->db ?: $this->db = connectDb(); $userCode = (int) getSessionValue('mapUserCode'); $sql = <<<"EOT"
select
    sd.SignDocumentCode as "id",
    sd.sourcekey as "sourceKey",
    sd.Source as "type",
    sd.Document as "content",
   coalesce(FN_cmMatWealthNameEx(s.MatWealthCode, 14), FN_cmMatWealthNameEx(rw.matwealthcode, 14)) as "description"
from SignDocument sd
left join MedRecipe r on  sd.Source = 'medrecipe' and r.MedRecipeCode = sd.sourcekey
left join MedRecipeStandart s on sd.Source = 'medrecipestandart' and s.MedRecipeStandartCode = sd.sourcekey
left join medrecipewealth rw on sd.Source = 'medrecipe' and rw.medrecipecode = r.medrecipecode
where sd.Type = 3
    and sd.State = 1
    and sd.UserCode = $userCode
limit 1
EOT;
$rows = selectSql($db, $sql); if ($rows) { foreach ($rows as &$r) { $r['content'] = base64_encode($r['content']); } } return $rows; } public function saveSignatures ($params) { if (!empty($params)) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update signDocument set
    State = ?,
    Signature = ?
where Source = ?
    and SourceKey = ?
EOT;
$stmt = $db->prepare($sql); foreach ($params->signatures as $sign) { $signature = isset($sign->signature) ? $sign->signature : ''; $stmt->execute([ 2, $signature, $sign->type, $sign->id ]); } } } } handleJsonRpc('map_homevisit_eSign');