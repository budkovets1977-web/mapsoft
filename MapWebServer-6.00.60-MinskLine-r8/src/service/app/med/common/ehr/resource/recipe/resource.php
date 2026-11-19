<?php
 namespace ehr; interface iRecipeResource extends iResource { } abstract class RecipeResource extends Resource implements iRecipeResource { public function createResourceItem(): void { $this->basicItem = new RecipeResourceItem($this); } public function getItem(): ?RecipeResourceItem { return $this->basicItem; } public function setItem(RecipeResourceItem $item): void { $this->basicItem = $item; } public function getLink(): ?RecipeLink { return $this->basicLink; } public function setLink(RecipeLink $link): void { $this->basicLink = $link; } public function afterOpenBeforeSend(): void { $link = $this->getLink(); $curRecipe = $link->getCurRecipe(); $practitioner = $link->getPractitioner(); $patient = $link->getPatient(); if (empty($curRecipe)) { $practitioner->setDoctorGUID(null); $patient->setPatientGUID(null); } else { $current = current($curRecipe->getQuery()); $practitioner->setDoctorGUID($current['doctorGUID'] ?? ''); $patient->setPatientGUID($current['patientGUID'] ?? ''); } } public function sendItem2DPC(int $key): ?string { $res = parent::sendItem2DPC($key); $item = $this->getItem(); try { $link = $this->getLink(); $logger = $link->getLogger(); if (empty($item->getPatientGUID())) { $logger->addLog( "ошибка {$item->logName()} - пациент {$item->getPatientFIO()}, key {$item->getMedPatientCode()} без GUID", '', logger::LMT_ERROR, true ); } if (empty($item->getDoctorGUID())) { $this->dbMarkDoctor(); $logger->addLog( "ошибка {$item->logName()} - врач {$item->getDoctorFIO}, key {$item->getMedDoctorCode()} без GUID", '', logger::LMT_ERROR, true ); } if (empty($item->getCodeIMN())) { $logger->addLog( "ошибка {$item->logName()} - МЦ, key {$item->getMatWealthCode()} без IMN", '', logger::LMT_ERROR, true ); } if (in_array($item->getState(), [1, 2, 5]) || empty($item->getGUID())) { if (empty($item->getGUID())) { $this->addNewRecipe($item); } else { $exchange = $link->getExchange(); $urlDPC = $link->getLs()->getParam('urlDPC'); $this->json = $exchange->getJSONResult( $exchange->rfc("{$urlDPC}MedicationPrescription/{$item->getGUID()}?_format=json"), "при поиске в ЦОД {$item->logName()}, GUID {$item->getGUID()}", true ); $item->setVersionGUID(LinkJSON::getNodeValueByPath($this->json, LinkJSON::SOURCE_VERSION_ID)); if (empty($item->getVersionGUID())) { $logger->addLog( "ошибка {$item->logName()}, GUID {$item->getGUID()} не получен GUID версии", '', Logger::LMT_ERROR, true ); } $item->appendJSON(); $this->json = $exchange->getJSONResult( $exchange->rfc( "{$urlDPC}MedicationPrescription/{$item->getGUID()}", Exchange::HTTP_PUT, $item->getJSONData() ), "при обновлении {$item->logName()}, GUID {$item->getGUID()}", true ); $this->dbUpdateRecipe($item); } } $res = $item->getGUID(); } catch (Throwable $exc) { $this->dbMarkErrRecipe($item); throw new Exception; } return $res; } private function addNewRecipe(RecipeResourceItem &$item): void { $link = $this->getLink(); $exchange = $link->getExchange(); if ($link->getEDSMode()) { $item->prepareEDS(); } else if ($link->getWorkFromARM()) { $item->clearSign(); } $urlDPC = $link->getLs()->getParam('urlDPC'); if (empty($item->getEDSpresent())) { $this->json = $exchange->getJSONResult( $exchange->rfc( "{$urlDPC}MedicationPrescription", Exchange::HTTP_POST, $item->getJSONData() ), "при отправке в ЦОД {$item->logName()}", true ); } else { $this->json = $exchange->getJSONResult( $exchange->rfc( "{$urlDPC}MedicationPrescription/\$register", Exchange::HTTP_POST, $item->getJSONData() ), "при отправке в ЦОД {$item->logName()}", true ); } $item->setGUID(LinkJSON::getNodeValueByPath($this->json, 'id')); if (empty($item->getGUID())) { $link->getLogger()->addLog("ошибка получения в ЦОД GUID {$item->logName()}", '', logger::LMT_ERROR, true); } $this->dbUpdateRecipe($item); } private function dbUpdateRecipe(RecipeResourceItem $item): void { if ($item->getState() === 2) { if ($item->getSourceCode() === 1) { $this->dbDeleteRecipePrivilege($item); } else { $this->dbDeleteRecipeOther($item); } $item->setGUID(''); } else { $this->dbRefreshRecipe($item); } $item->setState(0); } private function dbRefreshRecipe(RecipeResourceItem $item): void { $db = $this->getLink()->getConnetDb(); $curRecipe = $this->getLink()->getCurRecipe(); $state = $item->getSourceCode() === 1 ? 3 : 'null'; executeSql($db, <<<"EOT"
update {$curRecipe->getTableName()} set
       codehl7 = {$item->getGUID()},
       signatureType = NULLIF({$item->getSignatureType()}, 0),
       signature = null,
       signedContent = null,
       state = CASE state = 2
                    WHEN true THEN 2
                    WHEN false THEN {$state}
               END
WHERE {$curRecipe->getFieldName()} = {$item->getId()}
EOT
); } private function dbDeleteRecipeOther(RecipeResourceItem $item): void { $db = $this->getLink()->getConnetDb(); $curRecipe = $this->getLink()->getCurRecipe(); executeSql($db, <<<"EOT"
delete from {$curRecipe->getTableName()} where {$curRecipe->getFieldName()} = {$item->getId()}
EOT
); } private function dbDeleteRecipePrivilege(RecipeResourceItem $item): void { $db = $this->getLink()->getConnetDb(); executeSql($db, <<<"EOT"
delete from medRecipe
where medRecipeCode in (select D.medRecipeCode
                        from MedRecipe D
                             join MedRecipe R on R.enterpriseCode = D.enterpriseCode
                                             and R.recipeDate = D.recipeDate
                                             and R.documentNumber = D.documentNumber
                                             and R.documentSeries = D.documentSeries
                                             and COALESCE(R.medPatientCode,0) = COALESCE(D.medPatientCode, R.medPatientCode,0)
                        where R.medRecipeCode = {$item->getId()}
                          and D.RecipeType IN (0,1,2))
EOT
); } private function dbMarkDoctor(RecipeResourceItem $item): void { $db = $this->getLink()->getConnetDb(); executeSql($db, <<<"EOT"
update MedDoctor set
       state = 1
where medDoctorCode = {$item->getMedDoctorCode()}
EOT
); } private function dbMarkErrRecipe(RecipeResourceItem $item): void { if ($item->getState() === 1) { $link = $this->getLink(); $curRecipe = $link->getCurRecipe(); $db = $link->getConnetDb(); executeSql($db, <<<"EOT"
update {$curRecipe->getTableName()} set
       state = 5
where {$curRecipe->getFieldName()} = {$item->getId()}
EOT
); $item->setState(5); } } } 