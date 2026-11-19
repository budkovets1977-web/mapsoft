<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_cars { private $db; const MARK_DICT_NUMBER = 79; const MODEL_DICT_NUMBER = 80; const TYPE_DICT_NUMBER = 77; const CATEGORY_DICT_NUMBER = 78; const ERROR_CIPHER_UNIQUIE = 'Уже имеется запись с данным кодом'; public function readCars($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ""; $isActive = isEmptyProperty($params, 'isActive'); if ($isActive) { $where = "WHERE C.active = 1"; } $sql = <<<"EOT"
SELECT 
    C.ambCarCode "id",
    C.stateNumber "stateNumber",
    C.active "active",
    C.uniDictCodeMark "markUniDictId",
    Mark.value "markUniDictName",
    C.uniDictCodeModel "modelUniDictId",
    Model.value "modelUniDictName",
    C.uniDictCodeType "typeUniDictId",
    Type.value "typeUniDictName",
    C.uniDictCodeCategory "categoryUniDictId",
    Cat.value "categoryUniDictName",
    C.registryAddress "registryAddress",
    C.cipher "cipher"
FROM AmbCar C 
LEFT JOIN UniDict Mark ON Mark.uniDictCode = C.uniDictCodeMark 
LEFT JOIN UniDict Model ON Model.uniDictCode = C.uniDictCodeModel 
LEFT JOIN UniDict Type ON Type.uniDictCode = C.uniDictCodeType 
LEFT JOIN UniDict Cat ON Cat.uniDictCode = C.uniDictCodeCategory
$where
EOT;
return selectSql($db, $sql, $sqlParams); } public function updateCars($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update AmbCar set
    stateNumber = case when :existsStateNumber = 1 then :stateNumber else stateNumber end,
    active = case when :existsActive = 1 then :active else active end,
    uniDictCodeMark = case when :existsMarkUniDictId = 1 then :markUniDictId else uniDictCodeMark end,
    uniDictCodeModel = case when :existsModelUniDictId = 1 then :modelUniDictId else uniDictCodeModel end,
    uniDictCodeType = case when :existsTypeUniDictId = 1 then :typeUniDictId else uniDictCodeType end,
    uniDictCodeCategory = case when :existsCategoryUniDictId = 1 then :categoryUniDictId else uniDictCodeCategory end,
    registryAddress = case when :existsRegistryAddress = 1 then :registryAddress else registryAddress end,
    cipher = case when :existsCipher = 1 then :cipher else cipher end
    where ambCarCode = :id
returning ambCarCode
EOT;
if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $existsCipher = propertyExistsSQL($row, 'cipher'); $cipher = isUndefinedProperty($row, 'cipher'); if ($existsCipher) { $this->checkCipherUnique($db, $row->id, $cipher); } $existsMarkUniDictId = propertyExistsSQL($row, 'markUniDictName'); $markUniDictId = null; if ($existsMarkUniDictId) { $markUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'markUniDictName'), self::MARK_DICT_NUMBER); } $existsModelUniDictId = propertyExistsSQL($row, 'modelUniDictName'); $modelUniDictId = null; if ($existsModelUniDictId) { $modelUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'modelUniDictName'), self::MODEL_DICT_NUMBER); } $existsTypeUniDictId = propertyExistsSQL($row, 'typeUniDictName'); $typeUniDictId = null; if ($existsTypeUniDictId) { $typeUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'typeUniDictName'), self::TYPE_DICT_NUMBER); } $existsCategoryUniDictId = propertyExistsSQL($row, 'categoryUniDictName'); $categoryUniDictId = null; if ($existsCategoryUniDictId) { $categoryUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'categoryUniDictName'), self::CATEGORY_DICT_NUMBER); } selectSqlValue($db, $sql, [ ':existsStateNumber' => propertyExistsSQL($row, 'stateNumber'), ':stateNumber' => isUndefinedProperty($row, 'stateNumber'), ':existsActive' => propertyExistsSQL($row, 'active'), ':active' => isUndefinedProperty($row, 'active'), ':existsMarkUniDictId' => $existsMarkUniDictId, ':markUniDictId' => $markUniDictId, ':existsModelUniDictId' => $existsModelUniDictId, ':modelUniDictId' => $modelUniDictId, ':existsTypeUniDictId' => $existsTypeUniDictId, ':typeUniDictId' => $typeUniDictId, ':existsCategoryUniDictId' => $existsCategoryUniDictId, ':categoryUniDictId' => $categoryUniDictId, ':existsRegistryAddress' => propertyExistsSQL($row, 'registryAddress'), ':registryAddress' => isUndefinedProperty($row, 'registryAddress'), ':existsCipher' => $existsCipher, ':cipher' => $cipher, ':id' => $row->id ]); } $this->clearUniDict(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCars($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into AmbCar (stateNumber, active, uniDictCodeMark, uniDictCodeModel, 
                    uniDictCodeType, uniDictCodeCategory, registryAddress, cipher)
values (:stateNumber, :active, 
:markUniDictId, :modelUniDictId, :typeUniDictId, :categoryUniDictId, :registryAddress, :cipher
)
returning ambCarCode
EOT;
if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $cipher = isUndefinedProperty($row, 'cipher'); $this->checkCipherUnique($db, $row->id, $cipher); $ret[] = $rec = new stdClass; $markUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'markUniDictName'), self::MARK_DICT_NUMBER); $modelUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'modelUniDictName'), self::MODEL_DICT_NUMBER); $typeUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'typeUniDictName'), self::TYPE_DICT_NUMBER); $categoryUniDictId = $this->updateOrInsertUniDict($db, isUndefinedProperty($row, 'categoryUniDictName'), self::CATEGORY_DICT_NUMBER); $rec->id = selectSqlValue($db, $sql, [ ':stateNumber' => isUndefinedProperty($row, 'stateNumber'), ':active' => isUndefinedProperty($row, 'active'), ':markUniDictId' => $markUniDictId, ':modelUniDictId' => $modelUniDictId, ':typeUniDictId' => $typeUniDictId, ':categoryUniDictId' => $categoryUniDictId, ':registryAddress' => isUndefinedProperty($row, 'registryAddress'), ':cipher' => isUndefinedProperty($row, 'cipher') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } protected function checkCipherUnique($db, $id, $cipher) { $sql = <<<"EOT"
SELECT
    C.ambCarCode
FROM AmbCar C 
WHERE C.cipher like :cipher 
EOT;
$anotherId = selectSqlValue($db, $sql, [ ':cipher' => $cipher ]); if ($anotherId && $id !== $anotherId) { throw new Error(self::ERROR_CIPHER_UNIQUIE); } return true; } protected function clearUniDict () { $db = $this->db ?: $this->db = connectDb(); $mark = self::MARK_DICT_NUMBER; $model = self::MODEL_DICT_NUMBER; $type = self::TYPE_DICT_NUMBER; $category = self::CATEGORY_DICT_NUMBER; $sql = <<<"EOT"
DELETE 
FROM UniDict 
WHERE dictNumber in ($mark, $model, $type, $category) 
  AND NOT EXISTS(
      SELECT 1 
      FROM AmbCar C 
      WHERE UniDictCode in (C.uniDictCodeMark, C.uniDictCodeModel, 
        C.uniDictCodeType, C.uniDictCodeCategory)
  )
EOT;
selectSql($db, $sql); } protected function updateOrInsertUniDict($db, $value, $dictNumber) { if (!$value) { return null; } $sqlUpdate = <<<"EOT"
with t as (
    update uniDict set 
        value = :value 
    where dictNumber = :dictNumber and value like :value 
    returning uniDictCode
) 
select uniDictCode from t limit 1
EOT;
$id = selectSqlValue($db, $sqlUpdate, [ ':dictNumber' => $dictNumber, ':value' => $value ]); if ($id) { return $id; } $sqlInsert = <<<"EOT"
with t as (
    insert into uniDict (dictNumber, value)
    values (:dictNumber, :value)
    returning uniDictCode
) 
select uniDictCode from t limit 1
EOT;
return selectSqlValue($db, $sqlInsert, [ ':dictNumber' => $dictNumber, ':value' => $value ]); } public function deleteCars($records) { $db = $this->db ?: $this->db = connectDb(); $result = deleteSql($db, "AmbCar", "AmbCarCode", $records); $this->clearUniDict(); return $result; } } handleJsonRpc('med_ambulance_cars'); 