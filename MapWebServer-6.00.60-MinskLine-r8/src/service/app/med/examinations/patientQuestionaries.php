<?php
require_once(__DIR__ . '/../../init.php'); class med_examinations_patientQuestionaries { private $db; public function readPatientQuestionaries($params = null) { $db = $this->db ?: $this->db = connectDb(); $limit = isEmptyProperty($params, 'limit'); $start = isEmptyProperty($params, 'start', 0); $limitSql = ""; if ($limit) { $limitSql = "LIMIT $limit OFFSET $start"; } $sqlParams = []; $fromDate = isEmptyProperty($params, 'fromDate'); $fromDateSql = ''; if ($fromDate) { $fromDate = dbDate($fromDate); $fromDateSql = ' AND MPQ.documentDate >= :fromDate'; $sqlParams[':fromDate'] = $fromDate; } $toDate = isEmptyProperty($params, 'toDate'); $toDateSql = ''; if ($toDate) { $toDate = dbDate($toDate); $toDateSql = ' AND MPQ.documentDate <= :toDate'; $sqlParams[':toDate'] = $toDate; } $manAgeType = isEmptyProperty($params, 'manAgeType'); switch ($manAgeType) { case 1: $manAgeTypeSql = ' AND FN_wgCalcStage(3, today(), PASS.DateOfBirth, 0, 0, 0) >= 18'; break; case 2: $manAgeTypeSql = ' AND FN_wgCalcStage(3, today(), PASS.DateOfBirth, 0, 0, 0) < 18'; break; default: $manAgeTypeSql = ''; } $preventId = isEmptyProperty($params, 'preventId'); $preventIdSql = ''; if ($preventId) { $preventIdSql = ' AND MPQ.medPreventCode = :preventId'; $sqlParams[':preventId'] = $preventId; } $preventTypeId = isEmptyProperty($params, 'preventTypeId'); $preventTypeIdSql = ''; if ($preventTypeId) { $preventTypeIdSql = ' AND MP.medPreventTypeCode = :preventTypeId'; $sqlParams[':preventTypeId'] = $preventTypeId; } $sectorId = isEmptyProperty($params, 'sectorId'); $sectorIdSql = ''; if ($sectorId) { $sectorIdSql = ' AND P.medPatientGroupCode = :sectorId'; $sqlParams[':sectorId'] = $sectorId; } $patientId = isEmptyProperty($params, 'patientId'); $patientIdSql = ''; if ($patientId) { $patientIdSql = ' AND MPQ.medPatientCode = :patientId'; $sqlParams[':patientId'] = $patientId; } $sql = <<<"EOT"
SELECT
    MPQ.medPatientQuestionaryCode "id",
    MPQ.enterpriseCode "organizationId",
    MPQ.documentDate "documentDate",
    MPQ.medPatientCode "patientId",
    FN_cmFIO(P.manCode) "patientFio",
    P.numberHistorySickness "cardNumber",
    MPQ.medDoctorCode "doctorId",
    FN_cmFIO(MD.manCode) "doctorFio",
    MPQ.medPreventCode "preventId",
    MP.medPreventTypeCode "preventTypeId",
    MP.name "preventName",
    MVD.medVisitDocumentCode "visitDocumentId",
    MVD.htmTemplateCode "templateId",
    MVD.htmPageCode "pageId",
    HT.name "templateName",
    D.name "department",
    PASS.dateOfBirth "dateOfBirth",
    P.medPatientGroupCode "patientGroupId",
    COALESCE(HP.ready, 0) "ready",
    MPG.name "patientGroupName"
FROM MedPatientQuestionary MPQ
LEFT JOIN MedVisitDocument MVD ON MVD.medPatientQuestionaryCode = MPQ.medPatientQuestionaryCode
LEFT JOIN MedPatient P ON P.medPatientCode = MPQ.medPatientCode
LEFT JOIN MedDoctor MD ON MD.medDoctorCode = MPQ.medDoctorCode
LEFT JOIN HtmTemplate HT ON HT.htmTemplateCode = MVD.htmTemplateCode
LEFT JOIN HtmPage HP ON HP.HtmPageCode = MVD.HtmPageCode
LEFT JOIN MedPrevent MP ON MP.medPreventCode = MPQ.medPreventCode
LEFT JOIN ManPassport AS PASS ON PASS.ManCode = P.ManCode
LEFT JOIN MedPatientGroup MPG ON MPG.medPatientGroupCode = P.medPatientGroupCode
LEFT JOIN Department D ON MD.departmentcode = D.departmentcode
WHERE 1 = 1
$fromDateSql
$toDateSql
$manAgeTypeSql
$preventIdSql
$preventTypeIdSql
$sectorIdSql
$patientIdSql
EOT;
$result = new stdClass(); $itemsSql = <<<"EOT"
$sql
$limitSql
EOT;
$result->items = selectSql($db, $itemsSql, $sqlParams); $totalSql = <<<"EOT"
SELECT
    COUNT(t1.id)
FROM ($sql) as t1
EOT;
$result->total = selectSqlValue($db, $totalSql, $sqlParams); return $result; } public function updatePatientQuestionaries($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MedPatientQuestionary', 'medPatientQuestionaryCode', $records, 'enterpriseCode documentDate medPatientCode medDoctorCode medPreventCode', 'organizationId documentDate patientId doctorId preventId'); } public function createPatientQuestionaries($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); return insertSql($db, 'MedPatientQuestionary', $records, 'enterpriseCode documentDate medPatientCode medDoctorCode medPreventCode', 'documentDate patientId doctorId preventId', "$enterpriseCode, ?, ?, ?, ?"); } public function readQuestionaryAnswers($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $sqlPatientData = <<<"EOT"
SELECT
    M.surname "surname",
    M.name "name",
    M.secondName "patronymic",
    PASS.dateOfBirth "birthDate",
    FN_wgCalcStage(3, today(), PASS.DateOfBirth, 0, 0, 0) "age",
    (PASS.sex = 0) "man",
    PHONE.phoneNumber "phone",
    MPQ.documentDate "dateComplete"
FROM medPatientQuestionary MPQ
LEFT JOIN medPatient MP ON MP.medPatientCode = MPQ.medPatientCode
LEFT JOIN man M ON M.manCode = MP.manCode
LEFT JOIN manPassport PASS ON PASS.manCode = M.manCode
LEFT JOIN manPhone PHONE ON PHONE.manCode = M.manCode AND PHONE.phoneType = 3
WHERE MPQ.medPatientQuestionaryCode = :id
EOT;
$sqlResults = <<<"EOT"
SELECT
    MPQI.itemNumber "num",
    MPQI.itemAnswer::INTEGER "answer"
FROM medPatientQuestionaryItem MPQI
WHERE MPQI.medPatientQuestionaryCode = :id AND MPQI.itemNumber <> 25 AND MPQI.itemAnswer::INTEGER <> 0
EOT;
$patientData = selectSql($db, $sqlPatientData, [ ':id' => $id ]); $resultsData = selectSql($db, $sqlResults, [ ':id' => $id ]); $data = $patientData[0]; $data['result'] = $resultsData; $fileName = $data['surname'].$data['dateComplete']; $data = json_encode($data, JSON_UNESCAPED_UNICODE); $this->sendJsonFile($fileName, $data); } public function sendJsonFile ($fileName, $data) { $filePath = realpath(sys_get_temp_dir()) . "\\{$fileName}.json"; file_put_contents("{$filePath}", $data); header("Content-Type: application/download"); header('Cache-control: private, no-store, no-cache, must-revalidate, max-age=0'); header('Connection: close'); header('Content-Disposition: inline; filename="'.basename($fileName).'.json"'); readfile("{$filePath}"); unlink("{$filePath}"); } public function deletePatientQuestionaries($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedPatientQuestionary', 'medPatientQuestionaryCode', $records); } public function createMedVisitDocument($record) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedVisitDocument
    (medPatientQuestionaryCode, Type, UserCode, htmTemplateCode, Note, changeTime, createTime)
values
    (?, ?, ?, ?, 'Новая запись', LOCALTIMESTAMP, LOCALTIMESTAMP)
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { $stmt->execute([ isEmptyProperty($record, 'patientQuestionaryId'), isEmptyProperty($record, 'type'), isEmptyProperty($record, 'userId'), isEmptyProperty($record, 'templateId') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateMedVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update medVisitDocument set
       htmPageCode = :pageId,
       changeTime = LOCALTIMESTAMP
where medVisitDocumentCode = :documentId
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ ':pageId' => isEmptyProperty($params, 'pageId'), ':documentId' => isEmptyProperty($params, 'documentId', 0) ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_examinations_patientQuestionaries');