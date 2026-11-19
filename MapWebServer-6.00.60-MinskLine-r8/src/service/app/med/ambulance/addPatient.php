<?php
 require_once(__DIR__.'/../../init.php'); class med_ambulance_addPatient { private $db; public function addPatient($params){ $db = $this->db ?: $this->db = connectDb(); $result = false; $mapEnterpriseId = getSessionValue('mapEnterpriseCode'); $ambLogId = isEmptyProperty($params, 'ambLogId'); $ambLogId = is_numeric($ambLogId) ? $ambLogId : 0; $surname = isEmptyProperty($params, 'surname'); $name = isEmptyProperty($params, 'name'); $patronymic = isEmptyProperty($params, 'patronymic'); $birthDate = isEmptyProperty($params, 'birthDate'); if ($birthDate){ $birthDate = new DateTime($birthDate); $birthDate = $birthDate->format('Y-m-d'); } $phone = isEmptyProperty($params, 'phone'); $address = isEmptyProperty($params, 'address'); $house = isEmptyProperty($params, 'house'); $corps = isEmptyProperty($params, 'corps'); $appartment = isEmptyProperty($params, 'appartment'); $sectorId = isEmptyProperty($params, 'sectorId'); $sex = isEmptyProperty($params, 'sex'); $residenceId = isEmptyProperty($params, 'residenceId'); $db->beginTransaction(); try { $sql = <<<EOT
                INSERT INTO man (name, secondname, surname)
                VALUES (?, ?, ?)
EOT;
executeSql($db, $sql, [ $name, $patronymic, $surname ]); $manId = (int)$db->lastInsertId("seq_man"); $sql = <<<EOT
                INSERT INTO medPatient (medPatientGroupCode, manCode, startDate,
                        finishDate, enterpriseCode)
                VALUES (?, ?, NOW(), '30000101', ?);
EOT;
executeSql($db, $sql, [ $sectorId, $manId, $mapEnterpriseId ]); $medPatientId = (int)$db->lastInsertId("seq_medPatient"); $sql = <<<EOT
                INSERT INTO manPassport (manCode, dateOfBirth, addressResideCode,
                            addressResideHouse, addressResideCorps, addressResideFlat, sex)
                VALUES (?, ?, ?,
                    ?, ?, ?, ?);
EOT;
executeSql($db, $sql, [ $manId, $birthDate, $residenceId, $house, $corps, $appartment, $sex ]); $sql = <<<EOT
                INSERT INTO manPhone (phoneNumber, manCode, phoneType, status)
                VALUES (?, ?, 3, 1);
EOT;
executeSql($db, $sql, [ $phone, $manId ]); $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return $medPatientId; } } handleJsonRpc('med_ambulance_addPatient');