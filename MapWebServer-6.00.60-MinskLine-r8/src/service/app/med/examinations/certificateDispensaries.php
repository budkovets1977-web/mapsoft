<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_certificateDispensaries { private $db; public function readCertificateDispensaries($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $ret = []; $sql = <<<"EOT"
SELECT
    medcertificatedispensarycode "id",
    name "name",
    codeokpo "codeOKPO",
    cast(settings as varchar) "settings",
    enterprisecode "enterpriseId",
    active "active"
FROM
    MedCertificateDispensary
WHERE
    EnterpriseCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseId]); foreach ($stmt->fetchAll() as $row) { $ret[] = $rec = (object) $row; $settings = safe_json_decode($row['settings']); if ($settings) { $rec->url = isUndefinedProperty($settings, 'url'); $rec->port = isUndefinedProperty($settings, 'port'); $rec->isMain = isUndefinedProperty($settings, 'isMain'); $rec->positiveData = isUndefinedProperty($settings, 'positiveData'); $rec->negativeData = isUndefinedProperty($settings, 'negativeData'); if(!isEmptyProperty($rec, 'positiveData') || !isEmptyProperty($rec, 'negativeData') ) { $rec->isSetResult = 'Не заданы'; } else { $rec->isSetResult = 'Заданы'; } } } return $ret; } public function createCertificateDispensaries($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
INSERT INTO MedCertificateDispensary ( name, codeokpo, settings, enterprisecode, active )
VALUES (?, ?, CAST(? AS VARCHAR), ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $settings = new stdClass(); $settings->url = isEmptyProperty($row, 'url'); $settings->port = isEmptyProperty($row, 'port'); $settings->isMain = isEmptyProperty($row, 'isMain'); $settings->positiveData = isEmptyProperty($row, 'positiveData'); $settings->negativeData = isEmptyProperty($row, 'negativeData'); $stmt->execute([ isEmptyProperty($row, 'name'), isEmptyProperty($row, 'codeOKPO'), json_encode($settings, JSON_UNESCAPED_UNICODE), $enterpriseCode, 1, ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCertificateDispensary'); $rec->active = 1; $rec->enterpriseId = $enterpriseCode; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateCertificateDispensaries($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCertificateDispensary SET
    name = case when ? = 1 then ? else name end,
    codeokpo = case when ? = 1 then ? else codeokpo end,
    settings = case when ? = 1 then CAST(? AS VARCHAR) else settings end,
    active = case when ? = 1 then ? else active end
WHERE MedCertificateDispensaryCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $settings = new stdClass(); $settings->url = isEmptyProperty($row, 'url'); $settings->port = isEmptyProperty($row, 'port'); $settings->isMain = isEmptyProperty($row, 'isMain'); $settings->positiveData = isEmptyProperty($row, 'positiveData'); $settings->negativeData = isEmptyProperty($row, 'negativeData'); $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'codeOKPO'), isUndefinedProperty($row, 'codeOKPO'), 1, json_encode($settings), propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteCertificateDispensaries($records) { deleteSql($this->db, 'MedCertificateDispensary', 'MedCertificateDispensaryCode', $records); } } handleJsonRpc('med_examinations_certificateDispensaries'); 