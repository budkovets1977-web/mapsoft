<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_talonServicePrices { private $db; const TASK_NAME = 'medReceptionLinkTalon'; public function readTalonServicePrices($params = null) { $db = $this->db ?: $this->db = connectDb(); $specialtiesId = isset($params->specialtiesId) && isEmptyProperty($params, 'specialtiesId') ? $params->specialtiesId : null; $couponWorkId = isset($params->couponWorkId) && isEmptyProperty($params, 'couponWorkId') ? $params->couponWorkId : null; $currentEnterprise = getSessionValue('mapEnterpriseCode'); $sqlFromWhere = <<<"EOT"
FROM TalonServicePrice TSP
    JOIN TalonService TS on TSP.TalonServiceCode = TS.TalonServiceCode
    JOIN MedProfession MP on TSP.MedProfessionCode = MP.MedProfessionCode
    JOIN MedCouponWork MCW on TSP.MedCouponWorkCode = MCW.MedCouponWorkCode
WHERE TSP.EnterpriseCode = $currentEnterprise
EOT;
if (isset($specialtiesId)) { $sqlFromWhere .= " AND TSP.MedProfessionCode = $specialtiesId"; } if (isset($couponWorkId)) { $sqlFromWhere .= " AND TSP.MedCouponWorkCode = $couponWorkId"; } $sql = <<<"EOT"
SELECT
    TSP.TalonServicePriceCode "id",
    TSP.TalonServiceCode "medService",
    TS.Name "serviceName",
    TS.FullCode "serviceCode",
    TS.Active "activeService",
    TSP.Active "activePrice",
    TSP.MedProfessionCode "specialty",
    TSP.WarnMsg "warnMsg",
    TSP.MinPriceOnly "minPriceOnly",
    MP.Name "professionName",
    MP.Cipher "professionCipher",
    TSP.MedCouponWorkCode "couponWork",
    MCW.Name "couponWorkName",
    TSP.Price "price",
    TSP.Note "description"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createTalonServicePrices($records) { $db = $this->db ?: $this->db = connectDb(); $currentEnterprise = getSessionValue('mapEnterpriseCode'); $userName = getSessionValue('userName'); $sql = <<<"EOT"
INSERT INTO TalonServicePrice (TalonServiceCode, MedProfessionCode, MedCouponWorkCode, Price, Note, Active, MinPriceOnly, WarnMsg, EnterpriseCode)
VALUES (?,?,?,?,?,?,?,?, $currentEnterprise)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'medService'), isUndefinedProperty($row, 'specialty'), isUndefinedProperty($row, 'couponWork'), isUndefinedProperty($row, 'price'), isUndefinedProperty($row, 'description'), isUndefinedProperty($row, 'activePrice'), isUndefinedProperty($row, 'minPriceOnly'), isUndefinedProperty($row, 'warnMsg') ]); $ret[] = $rec = new stdClass; $rec->id = intval($db->lastInsertId('seq_TalonServicePrice')); } $db->commit(); $this->log("$userName: Создание связок: ".json_encode($records, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) { $db->rollback(); $this->log("При создании связок произошла ошибка: $e"); throw $e; } return $ret; } public function updateTalonServicePrices($records) { $db = $this->db ?: $this->db = connectDb(); $userName = getSessionValue('userName'); $sql = <<<"EOT"
UPDATE TalonServicePrice SET
    TalonServiceCode = case when ? = 1 then ? else TalonServiceCode end,
    MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end,
    MedCouponWorkCode = case when ? = 1 then ? else MedCouponWorkCode end,
    Price = case when ? = 1 then ? else price end,
    Note = case when ? = 1 then ? else Note end,
    Active = case when ? = 1 then ? else Active end,
    MinPriceOnly = case when ? = 1 then ? else MinPriceOnly end,
    WarnMsg = case when ? = 1 then ? else WarnMsg end
WHERE TalonServicePriceCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'medService'), isEmptyProperty($row, 'medService'), propertyExistsSQL($row, 'specialty'), isEmptyProperty($row, 'specialty'), propertyExistsSQL($row, 'couponWork'), isEmptyProperty($row, 'couponWork'), propertyExistsSQL($row, 'price'), isEmptyProperty($row, 'price'), propertyExistsSQL($row, 'description'), isEmptyProperty($row, 'description'), propertyExistsSQL($row, 'activePrice'), isEmptyProperty($row, 'activePrice'), propertyExistsSQL($row, 'minPriceOnly'), isEmptyProperty($row, 'minPriceOnly'), propertyExistsSQL($row, 'warnMsg'), isEmptyProperty($row, 'warnMsg'), $row->id ]); } $db->commit(); $this->log("$userName: Редактирование связок: ".json_encode($records, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) { $db->rollback(); $this->log("При редактировании связок произошла ошибка: $e"); throw $e; } return true; } public function deleteTalonServicePrices($records) { $db = $this->db ?: $this->db = connectDb(); $userName = getSessionValue('userName'); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE from TalonServicePrice where TalonServicePriceCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); $this->log("$userName: Удаление связок:".json_encode($records, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) { $db->rollback(); $this->log("При удалении связки произошла ошибка: $e"); throw $e; } return true; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_common_talonServicePrices'); 