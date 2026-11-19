<?php
 require_once (__DIR__ . '/../../init.php'); class med_common_patientAgreement { private $db; public function readPatientAgreement($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : 0; $sql = <<<"EOT"
SELECT
    MedPatientAgreementCode "id",
    MedPatientCode "patientId",
    Type "type",
    Cipher "cipher",
    AccountNumber "accountNumber",
    PhoneNumber "phoneNumber"
FROM MedPatientAgreement
WHERE
    MedPatientCode = $patientId
EOT;
return selectSql($db, $sql); } public function createPatientAgreement($records) { $db = $this->db ?: $this->db = connectDb(); $webUser = getSessionValue('user'); $sql = <<<"EOT"
INSERT INTO MedPatientAgreement (MedPatientCode, Type, Cipher, AccountNumber, PhoneNumber, UserCode)
VALUES (?,?,?,?,?,$webUser)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } suspendJsonRpc(); require_once (__DIR__ . '/../reception/linkTalon.php'); resumeJsonRpc(); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'type'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'accountNumber'), isUndefinedProperty($row, 'phoneNumber') ]); } $talon = new med_reception_linkTalon(); $sinc = $talon->cabinetUser($records); if ($sinc) { $db->commit(); } else { $db->rollback(); $result = "В процессе синхронизации с talon.by произошла ошибка"; return $result; } } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updatePatientAgreement($records) { $db = $this->db ?: $this->db = connectDb(); $webUser = getSessionValue('user'); $sql = <<<"EOT"
UPDATE MedPatientAgreement SET
    MedPatientCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCode END,
    Type = CASE WHEN ? = 1 THEN ? ELSE Type END,
    Cipher = CASE WHEN ? = 1 THEN ? ELSE Cipher END,
    AccountNumber = CASE WHEN ? = 1 THEN ? ELSE AccountNumber END,
    PhoneNumber = CASE WHEN ? = 1 THEN ? ELSE PhoneNumber END,
    UserCode = $webUser
WHERE MedPatientAgreementCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } suspendJsonRpc(); require_once (__DIR__ . '/../reception/linkTalon.php'); resumeJsonRpc(); $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'type'), isUndefinedProperty($row, 'type'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'accountNumber'), isUndefinedProperty($row, 'accountNumber'), propertyExistsSQL($row, 'phoneNumber'), isUndefinedProperty($row, 'phoneNumber'), $row->id ]); } $talon = new med_reception_linkTalon(); $sinc = $talon->cabinetUser($records); if ($sinc) { $db->commit(); } else { $db->rollback(); $result = "В процессе синхронизации с talon.by произошла ошибка"; return $result; } } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_common_patientAgreement'); 