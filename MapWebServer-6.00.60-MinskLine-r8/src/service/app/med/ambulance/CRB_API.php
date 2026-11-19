<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_linkCRB { private $db; const TASK_NAME = 'medAmbulanceAPI'; public function syncEmergency($params) { $publicKey = file_get_contents(__DIR__ . '/../../../../../crt/public.pem'); suspendJsonRpc(); require_once(__DIR__ . '/../../../lib/php-jwt/JWT.php'); resumeJsonRpc(); $JWTService = new Firebase\JWT\JWT(); $data = $JWTService->decode($params, $publicKey, ['RS256']); $data = json_clean_decode($data[0]); $this->updateMedEmergency($data); } private function updateMedEmergency($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedEmergency
    (linkcode,linkstatus,calldate,calltime,callnumber,medpatientcode,patientfio,patientaddress,patientage,
asset,visitzone,priority,meddiagnosiscode,chronic)
VALUES (?,3,?,?,?,?,?,?,?,?,?,?,?,?)
ON CONFLICT (linkcode) DO UPDATE SET
    linkstatus = 3,
    calldate = case when ? = 1 then EXCLUDED.calldate else MedEmergency.calldate end,
    calltime = case when ? = 1 then EXCLUDED.calltime else MedEmergency.calltime end,
    callnumber = case when ? = 1 then EXCLUDED.callnumber else MedEmergency.callnumber end,
    medpatientcode = case when ? = 1 then EXCLUDED.medpatientcode else MedEmergency.medpatientcode end,
    patientfio = case when ? = 1 then EXCLUDED.patientfio else MedEmergency.patientfio end,
    patientaddress = case when ? = 1 then EXCLUDED.patientaddress else MedEmergency.patientaddress end,
    patientage = case when ? = 1 then EXCLUDED.patientage else MedEmergency.patientage end,
    asset = case when ? = 1 then EXCLUDED.asset else MedEmergency.asset end,
    visitzone = case when ? = 1 then EXCLUDED.visitzone else MedEmergency.visitzone end,
    priority = case when ? = 1 then EXCLUDED.priority else MedEmergency.priority end,
    meddiagnosiscode = case when ? = 1 then EXCLUDED.meddiagnosiscode else MedEmergency.meddiagnosiscode end,
    chronic = case when ? = 1 then EXCLUDED.chronic else MedEmergency.chronic end
WHERE MedEmergency.linkcode = EXCLUDED.linkcode
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'medemergencycode'), isUndefinedProperty($row, 'calldate'), isUndefinedProperty($row, 'calltime'), isUndefinedProperty($row, 'callnumber'), isUndefinedProperty($row, 'medpatientcode'), isUndefinedProperty($row, 'patientfio'), isUndefinedProperty($row, 'patientaddress'), isUndefinedProperty($row, 'patientage'), isUndefinedProperty($row, 'asset'), isUndefinedProperty($row, 'visitzone'), isUndefinedProperty($row, 'priority'), isUndefinedProperty($row, 'meddiagnosiscode'), isUndefinedProperty($row, 'chronic'), propertyExistsSQL($row, 'calldate'), propertyExistsSQL($row, 'calltime'), propertyExistsSQL($row, 'callnumber'), propertyExistsSQL($row, 'medpatientcode'), propertyExistsSQL($row, 'patientfio'), propertyExistsSQL($row, 'patientaddress'), propertyExistsSQL($row, 'patientage'), propertyExistsSQL($row, 'asset'), propertyExistsSQL($row, 'visitzone'), propertyExistsSQL($row, 'priority'), propertyExistsSQL($row, 'meddiagnosiscode'), propertyExistsSQL($row, 'chronic') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("ошибка $e"); throw $e; } return true; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } $service = new med_ambulance_linkCRB(); $sessionsValues = json_clean_decode(file_get_contents(__DIR__ . '/../../../cfg/settings_ambulance.json')); setSessionValue('config', $sessionsValues->apiConfig); $data = file_get_contents('php://input'); $service->syncEmergency($data); 