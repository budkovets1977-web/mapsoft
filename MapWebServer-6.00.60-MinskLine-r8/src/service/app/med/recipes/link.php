<?php
 require_once(__DIR__.'/../../init.php'); suspendJsonRpc(); require_once(__DIR__.'/sign.php'); resumeJsonRpc(); class med_recipes_link { const TASK_NAME = 'medRecipesLink'; protected $db; public function exchange($params = null) { $this->log("Начало отправки рецептов ЦОД"); $db = $this->db ?: $this->db = connectDb(); $settings = $this->readSettings(); $processedRecipes = 0; $errorCount = 0; $tables = ['medrecipestandart', 'medrecipe']; foreach ($tables as $table) { $tablePk = $table . 'code'; $outDate = ($table === 'medrecipe' ? 'recipedate' : 'outdate'); $condRecipeType = $table === 'medrecipe' ? 'AND RecipeType = 0' : ''; $condDate = ""; if (isEmptyProperty($settings, 'useDayLimitOut', 0) and isEmptyProperty($settings, 'dayLimitOut', 0) > 0) { $condDate = "and date($outDate) between (date(now()) - $settings->dayLimitOut) and date(now())"; } $sql = <<<"EOT"
select
    $tablePk as "id",
    DocumentSeries "series",
    DocumentNumber "number"
from $table
where
    EnterpriseCode = ?
    $condDate
    and State in (1,2,5)
    $condRecipeType
    and (
        State = 2 -- посылаем отмена всегда
        or coalesce(SignatureType,0) = 0 -- без подписи всегда
        or coalesce(CodeHL7,'') = '') -- иначе, только если не посылался ранее
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $settings->organizationId ]); while(($row = $stmt->fetch(PDO::FETCH_OBJ))) { $processedRecipes++; try { $generator = new med_recipes_sign($db); if (!$generator->sendRecipe2DPC($row->id, $table)) { $errorCount++; $this->log("Ошибка отправки рецепта {$row->series}{$row->number} ($table:$row->id)", 2); } } catch (Throwable $e) { $errorCount++; $msg = $e->getMessage(); $this->log("Ошибка отправки рецепта {$row->series}{$row->number} ($table:$row->id) $msg", 2); } } } $this->log("Отправлено $processedRecipes рецептов, ошибок отправки $errorCount."); return (object)[ 'processedRecipes' => $processedRecipes, 'errorCount' => $errorCount ]; } public function readSettings() { $settings = readExtraSettings(null, 'medRecipesLinkRecipes'); if (!isEmptyProperty($settings, 'active')) { throw new Exception("Обмен с ЦОД отключен"); } if (!isEmptyProperty($settings, 'organizationId')) { throw new Exception("В настройках отсутствует организация"); } return $settings; } protected function log ($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_recipes_link');