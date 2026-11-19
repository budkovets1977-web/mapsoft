<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/sign.php'); resumeJsonRpc(); class med_recipes_linkRecipes { private $db; public function Exchange($params) { $settings = readExtraSettings(null, 'medRecipesLinkRecipes'); $db = $this->db ?: $this->db = connectDb(); if (!isEmptyProperty($settings, 'active')) { app_log("Обмен с ЦОД отключен"); return; } app_log("Отправка рецептов в ЦОД"); $tables = ['medrecipestandart', 'medrecipe']; $enterpriseId = $settings->organizationId; $dayLimitOut = $settings->dayLimitOut; foreach ($tables as $table) { $outDate = ($table === 'medrecipe' ? 'recipedate' : 'outdate'); $tablePk = $table . 'code'; $sql = <<<"EOT"
SELECT
    $tablePk "id"
FROM $table
WHERE EnterpriseCode = $enterpriseId
    AND date($outDate) >= (date(now()) - interval '$dayLimitOut days')
    AND (State = 2 OR (State IN (1,5) AND (coalesce(SignatureType,0) = 0 OR coalesce(CodeHL7,'') = '')))

EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $error = ''; while (($row = $stmt->fetch(PDO::FETCH_OBJ))) { app_log("Рецепт $table:$row->id"); $generator = new med_recipes_sign($db); if (!$generator->sendRecipe2DPC($row->id, $table)) { app_log("Ошибка отправки рецепта в ЦОД ($table:$row->id)", 2); if (empty($error)) { $error = "Ошибка отправки в ЦОД рецептов с номерами: "; } else { $error .= ", "; } $error .= $row->id; } else { app_log("успешная отправка в ЦОД"); } } if (!empty($error)) { throw new Exception($error . '!'); } } } public function CheckSettings() { $db = $this->db ?: $this->db = connectDb(); $signType = selectSqlValue($db, 'select Type from signUser where UserCode = ?', [getSessionValue('mapUserCode')]); $dcpSettings = readExtraSettings(null, 'medRecipesLinkRecipes'); $signSettings = readExtraSettings($db, 'mapCommonESignSettings'); $result = new stdClass; $result->avestActive = isEmptyProperty($signSettings, 'avestFlashActive'); $result->dpcActive = isEmptyProperty($dcpSettings, 'active'); $result->avestSign = $signType == 1; $result->signType = $signType; $result->documents = isEmptyProperty($signSettings, 'documents', []); $result->avestAPI = isEmptyProperty($signSettings, 'avestApiUrl'); return $result; } public function getPassNumber($params) { $db = $this->db ?: $this->db = connectDb(); $table = $params->table; $id = $params->code; $code = 'MR.'.$params->table.'code'; $sql = <<<"EOT"
SELECT MP.personalnumber
FROM $table MR
    JOIN MedDoctor MD on MR.MedDoctorCode = MD.MedDoctorCode
    JOIN ManPassport MP on MD.ManCode = MP.ManCode
WHERE $code = $id
EOT;
return selectSqlValue($db, $sql); } public function deletePrescriptionInDPC($record) { $db = $this->db ?: $this->db = connectDb(); $arrTable = ['MedRecipe', 'MedRecipeStandart', 'MedRegistrySpecial']; $record->state = 2; updateSql($db, $arrTable[$record->type], $arrTable[$record->type] . 'code', $record, 'state', 'state', 'recipeId'); $sign = new med_recipes_sign($db); $sign->sendRecipe2DPC($record->recipeId, mb_strtolower($arrTable[$record->type])); } } handleJsonRpc('med_recipes_linkRecipes');