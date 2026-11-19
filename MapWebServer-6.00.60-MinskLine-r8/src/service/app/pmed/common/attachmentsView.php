<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/common/patients.php'); resumeJsonRpc(); class pmed_common_attachmentsView { private $db; public function readAttachment($params) { $type = isEmptyProperty($params, 'type'); $contentType = 'application/pdf'; if ($type == 0) { $document = $this->getPatientPhoto($params); return; } else if ($type == 1) { $document = $this->getAttachmentFromBD($params); } if (is_int($document)) { $this->buildResponseError($document); return; } $this->buildResponse($document, $contentType); } private function buildResponseError($error) { switch ($error) { case 1: $text = 'Нету связанного вложения!'; break; case 2: $text = 'Отсутствует файл!'; break; default: $text = 'Не удалось загрузить вложение!'; break; } echo <<<html
{$text}
html;
} private function buildResponse($attachment, $contentType) { $fileName = str_replace("\\", '_', $attachment['storagefilename']); $filePath = realpath(sys_get_temp_dir()) . "\\{$fileName}"; file_put_contents("{$filePath}", $attachment['data']); header("Content-Type: {$contentType}"); header('Cache-control: private, no-store, no-cache, must-revalidate, max-age=0'); header('Connection: close'); readfile("{$filePath}"); unlink("{$filePath}"); } public function getAttachmentFromBD($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       fileexists       "fileexists",
       filename         "filename",
       storagefilename  "storagefilename",
       data::bytea      "data"
from medExternalFile
where sourceCode = ?
  and source = ?
EOT;
$rows = selectSql($db, $sql, [ isEmptyProperty($params, 'sourceId', 0), isEmptyProperty($params, 'source', 0) ]); if ($rows === false) { error_log('1: Нету связанного вложения!'); return 1; } $row = reset($rows); if (empty($row['fileexists'])) { error_log('2: Отсутствует файл!'); return 2; } return $row; } public function getPatientPhoto($params) { $patient = new med_common_patients(); $params->id = $params->sourceId; $patient->readPhoto($params); } } handleJsonRpc('pmed_common_attachmentsView'); 