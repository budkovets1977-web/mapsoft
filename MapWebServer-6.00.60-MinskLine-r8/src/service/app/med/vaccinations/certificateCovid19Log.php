<?php
require_once(__DIR__.'/../../init.php'); class med_vaccinations_certificateCovid19Log { private $db; private $extraSettings; const TASK_NAME = 'medVaccinationsLinkCertificateCovid19'; const MAX_PAGE_SIZE = 30; public function readCertificateCovid19Log($params = null){ $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $startDate = $startDate ? $startDate : '19000101'; $finishDate = $finishDate ? $finishDate : '30000101'; $search = isEmptyProperty($params, 'search'); $sort = isEmptyProperty($params, 'sort'); $sort = $this->getSort($sort); $sqlFromWhere = <<<EOT
            FROM medCertificateCovid19 mcc19
            LEFT JOIN medPatient mp ON mp.medPatientCode = mcc19.medPatientCode
            LEFT JOIN man m ON m.manCode = mp.manCode
            LEFT JOIN manPassport mps ON mps.manCode = m.manCode
            LEFT JOIN LATERAL (
                SELECT
                    mil.inoculateDate "vaccinationdate",
                    mv.name "vaccinename",
                    FN_cmFIO(md.manCode) "doctorname"
                FROM medInoculateLog mil
                LEFT JOIN MedDoctor md ON md.medDoctorCode = mil.medDoctorCode
                LEFT JOIN MedVaccineMove mvm ON mvm.medInoculateLogCode = mil.medInoculateLogCode
                LEFT JOIN MedVaccineParty mvp ON mvp.medVaccinePartyCode = mvm.medVaccinePartyCode
                LEFT JOIN MedVaccine mv ON mv.medVaccineCode = mvp.medVaccineCode
                WHERE mil.medCertificateCovid19Code = mcc19.medCertificateCovid19Code
                ORDER BY mil.inoculateDate DESC LIMIT 1
            ) li ON TRUE
            WHERE 1 = 1
EOT;
if ($startDate || $finishDate){ $sqlFromWhere .= " AND (mcc19.issueDate >= $startDate AND mcc19.issueDate <= $finishDate)"; } if ($search){ $sqlFromWhere .= " AND (mcc19.documentNumber LIKE '%$search%' OR FN_cmFIO(mp.manCode) LIKE '%$search%')"; } $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int)(($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $sqlTotal = <<<EOT
            SELECT COUNT(*) "total"
            FROM (SELECT mcc19.medCertificateCovid19Code id
            $sqlFromWhere
            GROUP BY 1) AS t
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int)fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql = <<<EOT
                SELECT
                    mcc19.medCertificateCovid19Code id,
                    mcc19.certificateId "certificateId",          -- id сертификата в ЕАИС по регистрации сертификатов
                    mcc19.documentNumber "certificateNumber",
                    mcc19.issueDate "certificateDate",
                    mcc19.medPatientCode "patientId",
                    -- mcc19.enterpriseCode "enterpriseId",
                    -- mcc19.nameLat "nameLat",
                    -- mcc19.surnameLat "surnameLat",
                    -- mcc19.qrCode "qrCode",
                    -- mcc19.qrCodeUrl "qrCodeUrl",
                    -- mcc19.statusСode "statusСode",
                    -- mcc19.statusMessage "statusMessage",
                    mcc19.registrationDate "certificateRegistrationDate",    -- дата регистрации сертификата в ЕАИС
                    mps.sex gender,
                    -- m.name "firstName",
                    -- m.surName "lastName",
                    -- m.secondName "patronymic",
                    mcc19.nameLat "firstNameLat",
                    mcc19.surnameLat "surnameLat",
                    mcc19.secondNameLat "patronymicLat",
                    FN_cmFIO(mp.manCode) "patientName",
                    FN_cmCalcAddress(mp.manCode, 5) "addressDisplay",
                    mps.dateOfBirth "dateOfBirth",
                    mps.personalNumber "personalNumber",
                    mps.passportNumber "passportNumber",
                    (SELECT JSONB_AGG(t)
                        FROM (
                            SELECT
                                mil.medInoculateLogCode "medInoculateId",
                                mil.inoculateDate "vaccinationDate",
                                mv.name "vaccineName",
                                mvp.serial "vaccineSeries",
                                mvp.doseSize "vaccineDoseSize",
                                mil.reactionResult "vaccineReactionResult",
                                mil.medDoctorCode "medDoctorId",
                                FN_cmFIO(md.manCode) "doctorName",
                                md.fioLat "doctorFioLat"
                            FROM medInoculateLog mil
                            LEFT JOIN MedDoctor md ON md.medDoctorCode = mil.medDoctorCode
                            LEFT JOIN MedVaccineMove mvm ON mvm.medInoculateLogCode = mil.medInoculateLogCode
                            LEFT JOIN MedVaccineParty mvp ON mvp.medVaccinePartyCode = mvm.medVaccinePartyCode
                            LEFT JOIN MedVaccine mv ON mv.medVaccineCode = mvp.medVaccineCode
                            WHERE mil.medCertificateCovid19Code = mcc19.medCertificateCovid19Code
                            ORDER BY mil.inoculateDate
                        ) t
                    ) "dataJson",
                    li.vaccinationDate "lastVaccinationDate",
                    li.vaccineName "lastVaccineName",
                    li.doctorName "lastVaccineDoctorName"
                $sqlFromWhere
                ORDER BY $sort
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new \stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } private function getSort($sortstr){ if (isset($sortstr)){ $sorters = safe_json_decode($sortstr, false); $sb = array(); foreach ($sorters as $sort) { $sb[] = "\"$sort->property\" $sort->direction"; } return implode(', ', $sb); } else { return '4 DESC, 3 DESC, 1'; } } public function getExtraSettings(){ $db = $this->db ?: $this->db = connectDb(); if (!empty($this->extraSettings)){ return $this->extraSettings; } $this->extraSettings = readExtraSettings($db, self::TASK_NAME); return $this->extraSettings; } public function createCertificateCovid19Log($records){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); if (is_object($records)){ $records = [ $records ]; } $ret = []; $sql = <<<EOT
            INSERT INTO medCertificateCovid19 (
                            documentNumber, enterpriseCode, issueDate,
                            medPatientCode, nameLat, surnameLat,
                            secondnameLat
                        )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?
            )
            RETURNING medCertificateCovid19Code id
EOT;
$db->beginTransaction(); try { foreach ($records as $row){ $certificateNumber = $this->getNextCertificateCovid19DocumentNumber(); $stmt = $db->prepare($sql); $stmt->execute([ $certificateNumber, $enterpriseId, dbDate(isUndefinedProperty($row, 'certificateDate')), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'firstNameLat'), isUndefinedProperty($row, 'surnameLat'), isUndefinedProperty($row, 'patronymicLat') ]); $row->id = (int)fetchValue($stmt, 'id'); $this->updateInoculates($row); $rec = new \stdClass(); $rec->id = $row->id; $rec->certificateNumber = $certificateNumber; $ret[] = $rec; } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } private function getNextCertificateCovid19DocumentNumber(){ $db = $this->db ?: $this->db = connectDb(); $settings = $this->getExtraSettings(); $fdn = 0; $records = 1; $sqlCn = <<<EOT
            SELECT documentNumber "documentNumber"
            FROM medCertificateCovid19
            ORDER BY medCertificateCovid19Code DESC
            LIMIT 1
EOT;
$sqlCount = <<<EOT
            SELECT COUNT(*) records
            FROM medCertificateCovid19
            WHERE documentNumber = ?
EOT;
$stmtCn = $db->prepare($sqlCn); $stmtCn->execute(); $certificateNumber = fetchValue($stmtCn, 'documentNumber'); $number = safe_explode("-", $certificateNumber); if (is_array($number) && array_key_exists(2, $number)){ $fdn = (int)$number[2]; } while ($records != 0 && $fdn < 99999){ $fdn++; $fiveDigitsNumber = substr("00000" . $fdn, -5); $newCertificateNumber = $settings->certificateRegionCode . '-' . $settings->certificateOrganizationCode . '-' . $fiveDigitsNumber; $stmtCount = $db->prepare($sqlCount); $stmtCount->execute([ $newCertificateNumber ]); $records = fetchValue($stmtCount, 'records'); } return $newCertificateNumber; } public function updateCertificateCovid19Log($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [ $records ]; } $sqlCheckNumber = <<<EOT
            SELECT count(medCertificateCovid19Code) counter
            FROM medCertificateCovid19
            WHERE documentNumber LIKE ?
EOT;
$sql = <<<EOT
            UPDATE medCertificateCovid19
            SET documentNumber = CASE WHEN ? = 1 THEN ? ELSE documentNumber END,
                issueDate = CASE WHEN ? = 1 THEN ? ELSE issueDate END,
                medPatientCode = CASE WHEN ? = 1 THEN ? ELSE medPatientCode END,
                nameLat = CASE WHEN ? = 1 THEN ? ELSE nameLat END,
                surnameLat = CASE WHEN ? = 1 THEN ? ELSE surnameLat END,
                secondNameLat = CASE WHEN ? = 1 THEN ? ELSE secondNameLat END
            WHERE medCertificateCovid19Code = ?
EOT;
$db->beginTransaction(); try { foreach ($records as $row){ $certId = isUndefinedProperty($row, 'id'); $certNumber = isUndefinedProperty($row, 'certificateNumber'); if (isset($certNumber)){ $stmtCheck = $db->prepare($sqlCheckNumber); $stmtCheck->execute([ $certNumber ]); $certCounter = fetchValue($stmtCheck, 'counter'); if ($certCounter > 0){ throw new Exception('Сертификат с таким номером уже существует.'); } } $stmt = $db->prepare($sql); $stmt->execute([ propertyExistsSQL($row, 'certificateNumber'), $certNumber, propertyExistsSQL($row, 'certificateDate'), dbDate(isUndefinedProperty($row, 'certificateDate')), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'firstNameLat'), isUndefinedProperty($row, 'firstNameLat'), propertyExistsSQL($row, 'surnameLat'), isUndefinedProperty($row, 'surnameLat'), propertyExistsSQL($row, 'patronymicLat'), isUndefinedProperty($row, 'patronymicLat'), $certId ]); if ($certId){ $this->updateInoculates($row); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } private function updateInoculates($data){ $db = $this->db ?: $this->db = connectDb(); $bundle = []; for ($i = 1; $i <= 4; $i++){ $dat = new \stdClass(); $dat->medInoculateId = isUndefinedProperty($data, 'medInoculateId' . $i); $dat->medDoctorId = isUndefinedProperty($data, 'medDoctorId' . $i); $dat->doctorFioLat = isUndefinedProperty($data, 'doctorFioLat' . $i); $bundle[] = $dat; } $sqlInoculate = <<<EOT
            UPDATE medInoculateLog
            SET medCertificateCovid19Code = CASE WHEN ? = 1 THEN ? ELSE medCertificateCovid19Code END,
                medDoctorCode = CASE WHEN ? = 1 THEN ? ELSE medDoctorCode END
            WHERE medInoculateLogCode = ?
EOT;
$sqlDoctor = <<<EOT
            UPDATE medDoctor
            SET fioLat = CASE WHEN ? = 1 THEN ? ELSE fioLat END
            WHERE medDoctorCode = ?
EOT;
$stmt = $db->prepare($sqlInoculate); $stmtDoctor = $db->prepare($sqlDoctor); foreach ($bundle as $item){ $medInoculateId = isUndefinedProperty($item, 'medInoculateId'); $medDoctorId = isUndefinedProperty($item, 'medDoctorId'); if (isset($medInoculateId)){ $stmt->execute([ propertyExistsSQL($data, 'id'), isUndefinedProperty($data, 'id'), propertyExistsSQL($item, 'medDoctorId'), isUndefinedProperty($item, 'medDoctorId'), $medInoculateId ]); } if (isset($medDoctorId)){ $stmtDoctor->execute([ propertyExistsSQL($item, 'doctorFioLat'), isUndefinedProperty($item, 'doctorFioLat'), $medDoctorId ]); } } } public function deleteCertificateCovid19Log($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [ $records ]; } $sql = <<<EOT
            DELETE FROM medCertificateCovid19
            WHERE medCertificateCovid19Code = ?
EOT;
$db->beginTransaction(); try { foreach ($records as $row){ $locked = isUndefinedProperty($row, 'certificateId'); $certId = isUndefinedProperty($row, 'id'); if (!$locked){ $stmt = $db->prepare($sql); $stmt->execute([ $certId ]); } } $db->commit(); } catch (Throwable $e){ $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_vaccinations_certificateCovid19Log');