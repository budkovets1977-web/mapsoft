<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_storage { const LOG = 'storages'; private $db; public function readStorage($params) { $db = $this->db ?: $this->db = connectDb(); $type = isEmptyProperty($params, 'type', 0); $id = isEmptyProperty($params, 'id', 0); $sql = <<<"EOT"
SELECT
  SysStorageCode as id,
  COALESCE(Disabled,0) as Disabled,
  Note as Note,
  Position as Position,
  StoragePath  "storagePath",
  Type as Type,
  CAST(null as varchar(250)) as FreeSize,
  CAST( E'Обновить' as varchar(250)) as  Size,
  CAST( E'Обновить' as varchar(250)) as "fileCount"
FROM SysStorage
Where (sysstorage.Type =$type or 0=$type ) and (sysstorage.SysStorageCode = $id or 0=$id)
Order by 4;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateStorage($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update SysStorage set
       Disabled = case when ? = 1 then ? else Disabled end,
       Note = case when ? = 1 then ? else Note end,
       Position = case when ? = 1 then ? else Position end,
       StoragePath = case when ? = 1 then ? else StoragePath end,
       Type = case when ? = 1 then ? else Type end
where SysStorageCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 0, null, 0, null, propertyExistsSQL($row, 'position'), null, 0, null, 0, null, $row->id ]); } foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'disabled'), isUndefinedProperty($row, 'disabled', 0), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'position'), isUndefinedProperty($row, 'position'), propertyExistsSQL($row, 'storagePath'), isUndefinedProperty($row, 'storagePath'), propertyExistsSQL($row, 'type'), isUndefinedProperty($row, 'type'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteStorage($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'SysStorage', 'SysStorageCode', $records); } public function createStorage($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into SysStorage (Disabled, Note, Position, StoragePath, Type)
values (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $sqlPos = <<<"EOT"
select max(position) from SysStorage
EOT;
$stmtPos = $db->prepare($sqlPos); $stmtPos->execute(); $pos = fetchValue($stmtPos, 'max'); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'disabled'), isUndefinedProperty($row, 'note'), $pos + 1, isUndefinedProperty($row, 'storagePath'), isUndefinedProperty($row, 'type') ]); $param = new stdClass; $param->id = (int) $db->lastInsertId('seq_SysStorage'); $ret = $this->readStorage($param); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getDocumentCount($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select count(*)  as cnt,
sum(byte_length(CompressedHTML)*(CASE Version is null WHEN true then 1.5 WHEN false THEN 1.0 END ))
as size from htmPageT where CompressedHTML is not null and isTemplate not in(1,3) ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(); } public function dirSize($path) { $returnSize = 0; if (!$h = @opendir($path)) return false; while (($element = readdir($h)) !== false) { if ($element <> "." and $element <> "..") { $all_path = $path . "/" . $element; if (@filetype($all_path) == "file") { $returnSize += filesize($all_path); } elseif (@filetype($all_path) == "dir") { @$returnSize += $this->dirSize($all_path); } } } closedir($h); return $returnSize; } public function fileCount($path) { $ite = new RecursiveDirectoryIterator($path); $nbfiles = 0; foreach (new RecursiveIteratorIterator($ite) as $filename => $cur) { if ($cur->getFileName() != '.' && $cur->getFileName() != '..') { $nbfiles++; } } return $nbfiles; } public function exportToStorage($params) { $db = $this->db ?: $this->db = connectDb(); $path = isEmptyProperty($params, 'path', ''); if (is_dir($path)) { $sql = <<<"EOT"
select FN_htmPageWriteToFile(? , null, 0 ) as Code;
EOT;
$stmt = $db->prepare($sql); $sqlSelect = <<<"EOT"
Select htmPageCode "htmPageCode" From htmPageT Where CompressedHTML is not null and isTemplate not in(1,3)
EOT;
$stmtSelect = $db->prepare($sqlSelect); $stmtSelect->execute(); $results = $stmtSelect->fetchAll(); if (is_object($results)) { $results = [$results]; } $failDoc = 0; $db->beginTransaction(); try { foreach ($results as $row) { $stmt->execute([ $row['htmPageCode'] ]); $res = $stmt->fetch(); if ($res !== false) { $res = reset($res); } switch ($res) { case 1: $ret = "Не указан путь для записи в хранилище HTML-документов"; break; case 2: $ret = "Ошибка создания каталога в хранилище HTML-документов"; break; case 3: $failDoc += 1; break; } if ($res != 0 && $res != 3) { break; } } $db->commit(); if ($failDoc != 0) { $ret = "Документы записаны в файлы!</br>$failDoc не удалось записать в хранилище!"; } else { $ret = "Документы записаны в файлы!"; } } catch (Throwable $e) { $db->rollback(); throw $e; } } else { return 'Путь ' . $path . ' не существует!'; } return $ret; } private function log($message, $type = 0) { app_log($message, $type, self::LOG); } public function getStorageData($params) { $db = $this->db ?: $this->db = connectDb(); $path = isEmptyProperty($params, 'path', ''); $this->log("проверка пути: " . $path); if (is_dir($path)) { $this->log("Директория найдена"); $this->log("Рассчет свободного места"); $df = disk_free_space($path); if ($df != false || $df === 0) { $this->log("Свбодное место рассчитано"); $this->log("Рассчет размера хранилища"); $df = round($df / (1024 * 1024)); $dt = $this->dirSize($path); if ($dt != false || $dt === 0) { $this->log("Размер хранилища рассчитан"); $this->log("Рассчет количества файлов"); $dt = round($dt / (1024 * 1024)); $dc = $this->fileCount($path); if ($dc != false || $dc === 0) { $this->log("Количество файлов рассчитано"); $ret = new stdClass; $ret->freeSize = $df; $ret->size = $dt; $ret->fileCount = $dc; $this->log('Получения данных успешно завершено!'); return $ret; } else { return 'Ошибка получения данных'; $this->log('Ошибка получения данных'); } } else { $this->log('Ошибка получения данных'); return 'Ошибка получения данных'; } } else { $this->log('Ошибка получения данных'); return 'Ошибка получения данных'; } } else { $this->log('Путь ' . $path . ' не существует!'); return 'Путь ' . $path . ' не существует!'; } } public function getStorageFreeSize($params) { $db = $this->db ?: $this->db = connectDb(); $path = isEmptyProperty($params, 'path', ''); $this->log("проверка пути: " . $path); if (is_dir($path)) { $this->log("Директория найдена"); $this->log("Рассчет свободного места"); $df = disk_free_space($path); if ($df != false || $df === 0) { $df = round($df / (1024 * 1024)); $this->log("Свбодное место рассчитано"); $ret = new stdClass; $ret->freeSize = $df; $this->log('Получения данных успешно завершено!'); return $ret; } else { $this->log('Ошибка получения данных'); return 'Ошибка получения данных'; } } else { $this->log('Путь ' . $path . ' не существует!'); return 'Путь ' . $path . ' не существует!'; } } public function isFileExists ($type, $filename) { $storages = $this->getActiveStorages($type); foreach ($storages as $path) { if (file_exists(self::getRealPath($path, $filename))) { return true; } } return false; } public function deleteFile ($type, $filename) { $this->log("Хранилище $type: удаление $filename"); $result = 0; $storages = $this->getActiveStorages($type, false, true); $isError = false; foreach ($storages as $path) { $name = self::getRealPath($path, $filename); if (file_exists($name)) { if (unlink($name) === true) { $result++; $this->log("файл $name удален"); } else { $this->log("ошибка удаления $name"); $isError = true; } } } if ($isError && $result === 0) { throw new \Exception("Ошибка удаления из хранилища файлов"); } return $result; } public function readFile ($type, $filename) { $this->log("Хранилище $type: чтение $filename"); $storages = $this->getActiveStorages($type); foreach ($storages as $path) { $name = self::getRealPath($path, $filename); if (file_exists($name)) { $this->log("чтение контента $name"); $result = file_get_contents($name); if ($result === false) { $this->log("ошибка чтения $name"); throw new \Exception("Ошибка чтения из хранилища файлов"); } return $result; } } return false; } public function writeFile ($type, $filename, $data) { $this->log("Хранилище $type: запись $filename"); $storages = $this->getActiveStorages($type, true, true); $name = self::getRealPath($storages[0], $filename); $result = file_force_put_contents($name, $data); if ($result === false) { $this->log("ошибка записи $name"); throw new \Exception("Ошибка записи в хранилище файлов"); } return $result; } protected function getActiveStorages ($type, $onlyFirst = false, $forWrite = false) { $db = $this->db ?: $this->db = connectDb(); $limit = $onlyFirst ? 'limit 1' : ''; $sql = <<<"EOT"
select StoragePath path
from SysStorage
where Type = ? and coalesce(Disabled, 0) = 0
order by Position
$limit
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$type]); $res = array_column($stmt->fetchAll(), 'path'); if (empty($res)) { if ($forWrite) { $this->log("не настроено или отключено хранилище"); throw new \Exception("Не настроено или отключено хранилище файлов"); } } return $res; } protected static function getRealPath ($path, $filename) { return rtrim($path, '\\/') . '/' . ltrim($filename, '\\/'); } } handleJsonRpc('map_common_storage'); 