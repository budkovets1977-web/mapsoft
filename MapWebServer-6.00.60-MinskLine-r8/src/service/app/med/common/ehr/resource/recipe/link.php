<?php
 namespace ehr; interface iRecipeLink extends iPractitionerLink { } class RecipeLink extends PractitionerLink implements iRecipeLink { protected const RESOURCE_NAME = 'MedicationPrescription'; protected const LINK_SCOPE = 'EHR.MedicationPrescription.$register ' . 'EHR.MedicationPrescription.Read EHR.MedicationPrescription.Write'; private const TABLE_NAME_STANDART = 'medRecipeStandart', TABLE_NAME_SPECIAL = 'medRegistrySpecial', TABLE_NAME_PRIVILEGE = 'medRecipe'; private $startDate; private $finishDate; private $allRecipes; private $byPeriod; private $medRecipeCode; private $medRegistrySpecialCode; private $medRecipeStandartCode; protected $standart; protected $special; protected $privilege; protected $curRecipe; public function getStartDate(): string { return dbDate($this->startDate); } public function getFinishDate(): string { return dbDate($this->finishDate); } private function getPrivilege(): ?RecipeResourcePrivilege { return $this->privilege; } private function getSpecial(): ?RecipeResourceSpecial { return $this->special; } private function getStandart(): ?RecipeResourceStandart { return $this->standart; } public function getCurRecipe(): ?RecipeResource { return $this->curRecipe; } public function getMedRegistrySpecialCode(): int { return $this->medRegistrySpecialCode; } public function getMedRecipeStandartCode(): int { return $this->medRecipeStandartCode; } public function getMedRecipeCode(): int { return $this->medRecipeCode; } public function __construct(LinkParams $params, $timeOut = Exchange::HTTP_TIMEOUT_DEF, $accessScope = '') { $this->initData(); $doctor = 0; $patient = 0; if (!empty($params->byPeriod)) { $this->startDate = $params->byPeriod['begin']; $this->finishDate = $params->byPeriod['end']; } else if ($params->byLinkSetup ?? false) { $this->allRecipes = true; } else if ($params->byPrivilegeCode ?? 0) { $this->medRecipeCode = $params->byPrivilegeCode; $this->fillIdPatientDoctor(self::TABLE_NAME_PRIVILEGE, $this->medRecipeCode, $patient, $doctor); } else if ($params->byStandartCode ?? 0) { $this->medRecipeStandartCode = $params->byStandartCode; $this->fillIdPatientDoctor(self::TABLE_NAME_STANDART, $this->medRecipeStandartCode, $patient, $doctor); } else if ($params->bySpecialCode ?? 0) { $this->medRegistrySpecialCode = $params->bySpecialCode; $this->fillIdPatientDoctor(self::TABLE_NAME_SPECIAL, $this->medRegistrySpecialCode, $patient, $doctor); } else { throw new \Exception("Указан неизвестный параметры для отправки рецептов в ЦОД."); } $params->patientCode = $patient; $params->doctorCode = $doctor; parent::__construct($params, $timeOut, self::LINK_SCOPE . $accessScope); $this->privilege = new RecipeResourcePrivilege( $this, self::RESOURCE_NAME, self::TABLE_NAME_PRIVILEGE, ResourceItem::ITEM_SOURCE_RECIPE_MEDICATIONPRESCRIPTION ); $this->standart = new RecipeResourceStandart( $this, self::RESOURCE_NAME, self::TABLE_NAME_STANDART, ResourceItem::ITEM_SOURCE_RECIPE_MEDICATIONPRESCRIPTION ); $this->special = new RecipeResourceSpecial( $this, self::RESOURCE_NAME, self::TABLE_NAME_SPECIAL, ResourceItem::ITEM_SOURCE_RECIPE_MEDICATIONPRESCRIPTION ); $this->curRecipe = null; } public function __destruct() { unset($this->special); unset($this->standart); unset($this->privilege); parent::__destruct(); } private function fillIdPatientDoctor(string $tableName, int $id, int &$patient, int &$doctor): void { $sql = <<<"EOT"
select
       medPatientCode "patientId",
       medDoctorCode "doctorId"
from {$tableName}
where {$tableName}Code = {$id}
limit 1            
EOT;
$res = selectSql($this->getConnetDb(), $sql); if (empty($res)) { return; } $patient = $res[0]['patientId']; $doctor = $res[0]['doctorId']; } public function getRecipeFilter(string $fnD, string $fnID, int $id): string { if ($this->allRecipes) { $dayLimitOut = $this->getLs()->getParam('DayLimitOut'); $cond = $this->getLs()->getParam('useDayLimitOut') && $dayLimitOut > 0 ? "and {$fnD} >= CURRENT_DATE - interval '{$dayLimitOut} day'" : ""; return <<<"EOT"
and {$fnD} < CURRENT_DATE + interval '1 day'
{$cond}
EOT;
} if ($this->byPeriod) { return <<<"EOT"
and {$fnD} >= {$this->getStartDate()}
and {$fnD} < {$this->getFinishDate()}::date + interval '1 day'
EOT;
} return <<<"EOT"
and {$fnID} = {$id}
and {$fnD} is not null
EOT;
} private function getDrListFromRecipe(): string { $enterpriseCode = $this->getLs()->getParam('EnterpriseCode'); return <<<"EOT"
union
select R1.medDoctorCode
from medRecipe R1
where R1.enterpriseCode = {$enterpriseCode}
  and R1.recipeType = 0
  and R1.state in (1,2,3,5)
  {$this->getRecipeFilter('R1.recipeDate', 'R1.medRecipeCode', $this->medRecipeCode)}
union
select R2.medDoctorCode
from medRecipeStandart R2
where R2.enterpriseCode = {$enterpriseCode}
  and R2.state in (1,2,5)
  {$this->getRecipeFilter('R2.outDate', 'R2.medRecipeStandartCode', $this->medRecipeStandartCode)}
union
select R3.medDoctorCode
from medRegistrySpecial R3
where R3.enterpriseCode = {$enterpriseCode}
  and R3.state in (1,2,4)
  {$this->getRecipeFilter('R3.OutDate', 'R3.medRegistrySpecialCode', $this->medRegistrySpecialCode)}
EOT;
} public function transfer(): void { parent::transfer(); $logger = $this->getLogger(); $logger->addLog('начало обработки льготных рецептов'); $this->curRecipe = $recipe = $this->getPrivilege(); $recipe->send2DPC($recipe->getSQL()); $logger->addLog(" обработано льготных рецептов: {$recipe->getGuidCache()->getCount()}"); $logger->addLog('начало обработки обычных рецептов'); $this->curRecipe = $recipe = $this->getStandart(); $recipe->send2DPC($recipe->getSQL()); $logger->addLog(" обработано обычных рецептов: {$recipe->getGuidCache()->getCount()}"); } private function initData(): void { $this->startDate = Link::DATE1900; $this->finishDate = Link::DATE1900; $this->allRecipes = false; $this->medRecipeCode = 0; $this->medRecipeStandartCode = 0; $this->medRegistrySpecialCode = 0; } public function initTransfer(): void { } } 