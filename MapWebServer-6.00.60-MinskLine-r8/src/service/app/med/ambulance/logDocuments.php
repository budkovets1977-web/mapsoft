<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/docs/templates.php'); resumeJsonRpc(); class med_ambulance_logDocuments { private $db; private function sessionConnectDb(){ return $this->db ?: $this->db = connectDb(); } public function readTemplates($params){ $this->sessionConnectDb(); $paramsTmp = new stdClass(); $paramsTmp->selecting = true; $paramsTmp->simple = true; $paramsTmp->templateType = 17; $paramsTmp->access = 1; $templates = new map_docs_templates(); $templates = $templates->readTemplates($paramsTmp); $documents = $this->readDocuments($params); if (empty($documents)){ return $templates; } foreach ($templates as $template){ foreach ($documents as $document){ if ($document['templateId'] === $template->id){ $template->existsDoc = true; $template->sourceKey = $document['id']; break; } } } return $templates; } public function readDocuments($params){ $this->sessionConnectDb(); $id = isEmptyProperty($params, 'docId'); $templateId = isEmptyProperty($params, 'templateId'); $ambLogId = isEmptyProperty($params, 'ambLogId'); $whereSql = ''; empty($id) ? '' : $whereSql .= "and ALD.ambLogDocumentCode = {$id}"; empty($templateId) ? '' : $whereSql .= "and ALD.htmTemplateCode = {$templateId}"; empty($ambLogId) ? '' : $whereSql .= "and ALD.ambLogCode = {$ambLogId}"; $sql = <<<"EOT"
select
    ALD.ambLogDocumentCode  "id",
    ALD.ambLogCode          "ambLogId",
    ALD.htmTemplateCode     "templateId",
    ALD.htmPageCode         "pageId",
    HPT.ready               "ready"
from AmbLogDocument ALD
     left join HtmPageT HPT on HPT.htmPageCode = ALD.htmPageCode
where 0 = 0
  {$whereSql}
  --and COALESCE(ALD.htmPageCode, 0) <> 0 -- избегаем левых записей?
EOT;
$ret = selectSql($this->db, $sql); return empty($id) ? $ret : reset($ret); } public function createDocuments($records){ $this->sessionConnectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<"EOT"
insert into AmbLogDocument
    (amblogcode, htmTemplateCode)
values
    (?, ?)
returning ambLogDocumentCode "id"
EOT;
$this->db->beginTransaction(); $params = new stdClass(); try { $stmt = $this->db->prepare($sql); foreach ($records as $row){ $stmt->execute([ isUndefinedProperty($row, 'ambLogId'), isUndefinedProperty($row, 'templateId') ]); $row = $stmt->fetch(); $params->docId = $row === false ? null : reset($row); $ret[] = $this->readDocuments($params); } $this->db->commit(); } catch (Throwable $e){ $this->db->rollback(); throw $e; } return $ret; } public function updateDocuments($records){ $this->sessionConnectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
update AmbLogDocument set
    htmPageCode = case when ? then ? else htmPageCode end
where ambLogDocumentCode = ?
EOT;
$this->db->beginTransaction(); try { $stmt = $this->db->prepare($sql); foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'pageId'), isUndefinedProperty($row, 'pageId'), isUndefinedProperty($row, 'id') ]); } $this->db->commit(); } catch (Throwable $e){ $this->db->rollback(); throw $e; } return true; } public function deleteDocuments($records){ return deleteSql($this->db, 'ambLogDocument', 'ambLogDocumentCode', $records); } public function readCards($params = null){ $sqlParams = []; $ambLogId = isEmptyProperty($params , 'ambLogId'); $ambLogCodeParent = isEmptyProperty($params,'ambLogCodeParent'); if ($ambLogId && $ambLogCodeParent){ $sqlParams[':ambLogId'] = $ambLogId; $sqlParams[':ambLogCodeParent'] = $ambLogCodeParent; $sql = <<<EOT
SELECT
    L.amblogcode "id",
    L.carddata "carddata",
    AT.teamNumber "teamNumber"
FROM amblog L
LEFT JOIN AmbGraph AG ON AG.ambGraphCode = L.ambGraphCode
LEFT JOIN AmbTeam AT ON AT.ambTeamCode = AG.ambTeamCode
WHERE L.amblogcode <> :ambLogId
AND (L.amblogcodeparent = :ambLogCodeParent OR L.amblogcode = :ambLogCodeParent)
AND L.ambgraphcode IS NOT NULL
AND L.carddata IS NOT NULL
EOT;
$records = selectSql($this->sessionConnectDb(),$sql,$sqlParams); foreach ($records as &$row){ $row['carddata'] = safe_json_decode($row['carddata']); } return $records; } } public function readMapCallBrigades($params = null){ $sqlParams = []; $ambLogId = isEmptyProperty($params, 'ambLogId'); if ($ambLogId){ $sqlParams[':ambLogId'] = $ambLogId; $sql = <<<EOT
                SELECT
                    cardData
                FROM ambLog
                WHERE ambLogCode = :ambLogId
EOT;
$result = selectSqlValue($this->sessionConnectDb(), $sql, $sqlParams); if ($result){ return safe_json_decode($result); } else return false; } } public function createMapCallBrigades($record = null){ $db = $this->sessionConnectDb(); $ambLogId = isEmptyProperty($record, 'ambLogId'); $secondCard = isEmptyProperty($record, 'isSecondCard'); $thirdCard = isEmptyProperty($record, 'isThirdCard'); if ($ambLogId && is_int($ambLogId)){ $sqlParams[':ambLogId'] = $ambLogId; $sql = <<<EOT
                SELECT
                    cardData
                FROM ambLog
                WHERE ambLogCode = :ambLogId
EOT;
$cards = selectSqlValue($db, $sql, $sqlParams); if ($cards){ $cards = safe_json_decode($cards); } else { $cards = new stdClass(); } if ($secondCard){ $cards->secondCardBrigades = $record; } else if ($thirdCard){ $cards->thirdCardBrigades = $record; } else { $cards->mainCardBrigades = $record; $this->ambLogSheet($db, $record); } $cards = json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $cards = mb_convert_encoding(mb_convert_encoding($cards, 'CP1251', 'UTF-8'), 'UTF-8', 'CP1251'); $sql = <<<"EOT"
                UPDATE ambLog
                    SET
                    cardData = ?
                WHERE ambLogCode = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $cards, $ambLogId ]); $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } } private function ambLogSheet($db = null, $record = null){ if ($db && $record){ $sqlSheet = <<<EOT
                    UPDATE ambLogSheet
                    SET
                        lowPressure = CASE WHEN ? THEN ? ELSE lowPressure END,
                        highPressure = CASE WHEN ? THEN ? ELSE highPressure END,
                        pulse = CASE WHEN ? THEN ? ELSE pulse END,
                        heartRate = CASE WHEN ? THEN ? ELSE heartRate END,
                        temperature = CASE WHEN ? THEN ? ELSE temperature END
                    WHERE ambLogCode = ?;

EOT;
$stmtSheet = $db->prepare($sqlSheet); $stmtSheet->execute([ propertyExistsSQL($record, 'bloodLowPressure'), isUndefinedProperty($record, 'bloodLowPressure'), propertyExistsSQL($record, 'bloodHighPressure'), isUndefinedProperty($record, 'bloodHighPressure'), propertyExistsSQL($record, 'pulsenumber'), isUndefinedProperty($record, 'pulsenumber'), propertyExistsSQL($record, 'breathingRate'), isUndefinedProperty($record, 'breathingRate'), propertyExistsSQL($record, 'bodyTemperature'), isUndefinedProperty($record, 'bodyTemperature'), isUndefinedProperty($record, 'ambLogId') ]); $sqlSheet = <<<EOT
                    INSERT INTO ambLogSheet
                        (ambLogCode, lowPressure, highPressure, pulse, heartRate, temperature)
                    SELECT :ambLogId, :lowPressure, :highPressure, :pulse, :heartRate, :temperature
                    WHERE NOT EXISTS (SELECT 1 FROM ambLogSheet WHERE ambLogCode = :ambLogId);
EOT;
$stmtSheet = $db->prepare($sqlSheet); $stmtSheet->execute([ ':ambLogId' => isEmptyProperty($record, 'ambLogId'), ':lowPressure' => isEmptyProperty($record, 'bloodLowPressure'), ':highPressure' => isEmptyProperty($record, 'bloodHighPressure'), ':pulse' => isEmptyProperty($record, 'pulsenumber'), ':heartRate' => isEmptyProperty($record, 'breathingRate'), ':temperature' => isEmptyProperty($record, 'bodyTemperature') ]); } } public function updateMapCallBrigades($record = null){ $ambLogId = isEmptyProperty($record, 'ambLogId'); if ($ambLogId) return $this->createMapCallBrigades($record); } public function getDoctor($params = null){ $db = $this->sessionConnectDb(); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; if ($doctorId){ return $doctorId; } return false; } } handleJsonRpc('med_ambulance_logDocuments');