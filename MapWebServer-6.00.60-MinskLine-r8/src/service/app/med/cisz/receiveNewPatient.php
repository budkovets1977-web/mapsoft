<?php
 require_once(__DIR__ . '/../../init.php'); class med_cisz_receiveNewPatient { private $db; public function findPersonalNumber($params) { $personalNumber = isEmptyProperty($params, 'personalNumber'); $db = $this->db ?: $this->db = connectDb(); $sql = "SELECT fn_cmfio(MP.Mancode) AS fio
            FROM ManPassport MP
            JOIN MedPatient MT ON MT.ManCode = MP.ManCode
            WHERE MP.PersonalNumber = :personalNumber
            LIMIT 1"; $stmt = $db->prepare($sql); $stmt->execute(['personalNumber' => $personalNumber]); $result = $stmt->fetch(PDO::FETCH_ASSOC); if ($result) { return [ 'isnew' => false, 'fio' => $result['fio'] ]; } else { return [ 'isnew' => true ]; } } public function saveNewPatient($params) { $db = $this->db ?: $this->db = connectDb(); if (!$params || !isset($params->patientRecord)) { return null; } $patient = $params->patientRecord; $contacts = $params->stores->contacts ?? []; $records = new stdClass; $records->manId = null; $records->manPassportId = null; $records->medPatientId = null; $fio = trim($patient->patientName ?? ''); $parts = explode(' ', $fio, 3); $records->surname = $parts[0] ?? ''; $records->name = $parts[1] ?? ''; $records->secondname = $parts[2] ?? ''; $records->sex = $patient->gender ?? 0; $records->dateOfBirth = $patient->birthDate ?? null; $records->passportKindId = null; $records->passportNumber = null; $records->dateOfIssue = null; $records->dateOfExpiry = null; $records->authority = null; $selectedDoc = null; if (!empty($params->stores->documents) && is_array($params->stores->documents)) { $documents = $params->stores->documents; $priority = [ 'Passport-RB', 'Identification-card-of-a-citizen-RB', 'Resident-card', 'RCN', 'Biometric-for-a-foreign-citizen', 'Biometric-for-stateless-persons' ]; foreach ($priority as $type) { foreach ($documents as $doc) { if (($doc->documentType ?? '') === $type) { $selectedDoc = $doc; break 2; } } } if ($selectedDoc) { $documentType = $selectedDoc->documentType ?? ''; $sqlManPassportKind = <<<"EOT"
SELECT CRV.SourceCode AS passportKindId
FROM CiszResource CR
JOIN CiszResourceValue CRV ON CRV.CiszResourceCode = CR.CiszResourceCode
WHERE CRV.Code LIKE :documentType AND CR.Source = 6
LIMIT 1
EOT;
$stmtKind = $db->prepare($sqlManPassportKind); $stmtKind->execute(['documentType' => "%{$documentType}%"]); $passportKindRow = $stmtKind->fetch(PDO::FETCH_ASSOC); $records->passportKindId = $passportKindRow['passportkindid'] ?? null; $records->passportNumber = $selectedDoc->documentNumber ?? null; $records->dateOfIssue = $selectedDoc->documentStart ?? null; $records->dateOfExpiry = $selectedDoc->documentFinish ?? null; $records->authority = $selectedDoc->documentAuthority ?? null; } } $records->personalNumber = $patient->personalNumber ?? null; $records->addressText = $patient->registrationAddress; $records->addressResideText = $patient->residentialAddress; $records->addressJson = isset($patient->addressRegJson) ? json_encode($patient->addressRegJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null; $records->addressResideJson = isset($patient->addressJson) ? json_encode($patient->addressJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null; $records->addressCode = $patient->addressRegCode; $records->addressText = preg_replace('/\s*,?\s*кв\.?\s*\d+/iu', '', $records->addressText); $records->flatNumber = isset($patient->flatRegNumber) ? preg_replace('/\D+/u', '', $patient->flatRegNumber) : null; $records->addressResideCode = $patient->addressCode; $records->addressResideText = preg_replace('/\s*,?\s*кв\.?\s*\d+/iu', '', $records->addressResideText); $records->flatResideNumber = isset($patient->flatNumber) ? preg_replace('/\D+/u', '', $patient->flatNumber) : null; $records->startDate = $params->startDate ?? date('Y-m-d'); $records->changeDate = date('Y-m-d'); $records->finishDate = null; $records->personDataPermissionDate = $patient->personalDataProcessingDate ?? null; $records->numberHistoryOfSickness = null; $patientInfo = new med_common_patients(); $records->numberHistoryOfSickness = $patientInfo->getNextNumberHistorySickness(); $incapacity = isset($patient->legalCapacity) ? ($patient->legalCapacity == 1 ? false : true) : false; $birthCertificate = $patient->birthCertificateNumber ?? null; $records->json = json_encode([ 'incapacity' => $incapacity, 'birthCertificate' => $birthCertificate ], JSON_UNESCAPED_UNICODE); $records->medPatientGroupCode = $params->sectorId ?? null; $records->medPatientPolyclinicCode = $params->clinicId ?? null; $records->startDateCard = $patient->benefitDocumentStartDate ?? null; $records->finishDateCard = $patient->benefitDocumentExpirationDate ?? null; $records->disabilityDocNumber = $patient->benefitDocumentNumber ?? null; $sqlDisability = <<<"EOT"
SELECT CRV.CiszResourceValueCode AS code
FROM CiszResource CR
JOIN CiszResourceValue CRV ON CRV.CiszResourceCode = CR.CiszResourceCode
WHERE CRV.Code LIKE :documentType AND CR.Identifier LIKE :identifier
LIMIT 1
EOT;
$stmtDisability = $db->prepare($sqlDisability); $records->disabilityGroupCode = null; $records->healthLossStageCode = null; $records->privilegeCode = null; if (!empty($patient->disabilityGroupCode)) { $stmtDisability->execute([ 'documentType' => "{$patient->disabilityGroupCode}", 'identifier' => 'DisabilityGroup' ]); $records->disabilityGroupCode = $stmtDisability->fetchColumn() ?: null; } if (!empty($patient->disabilityReasonCode)) { $stmtDisability->execute([ 'documentType' => "{$patient->disabilityReasonCode}", 'identifier' => 'DisabilityReason' ]); $records->healthLossStageCode = $stmtDisability->fetchColumn() ?: null; } if (!empty($patient->benefitCode)) { $stmtDisability->execute([ 'documentType' => "{$patient->benefitCode}", 'identifier' => 'VSPatientPreferences' ]); $records->privilegeCode = $stmtDisability->fetchColumn() ?: null; } $sqlDisabilityDict = <<<"EOT"
SELECT CD.CiszDictCode AS code
FROM CiszDict CD
WHERE CD.Code LIKE :documentType AND CD.DictNumber = :dictNumber
LIMIT 1
EOT;
$stmtDisabilityDict = $db->prepare($sqlDisabilityDict); $records->disabilityGroupDict = null; $records->privilegeDict = null; if (!empty($patient->disabilityGroupCode)) { $stmtDisabilityDict->execute([ 'documentType' => "{$patient->disabilityGroupCode}", 'dictNumber' => 1 ]); $records->disabilityGroupDict = $stmtDisabilityDict->fetchColumn() ?: null; } if (!empty($patient->benefitCode)) { $stmtDisabilityDict->execute([ 'documentType' => "{$patient->benefitCode}", 'dictNumber' => 3 ]); $records->privilegeDict = $stmtDisabilityDict->fetchColumn() ?: null; } $records->maritalStatus = null; switch ($patient->maritalStatusCode) { case 'married': $records->maritalStatus = 1; break; case 'unmarried': $records->maritalStatus = 2; break; case 'widowed': $records->maritalStatus = 4; break; case 'divorced': $records->maritalStatus = 3; break; } $records->contacts = []; foreach ($contacts as $c) { $records->contacts[] = [ 'phoneType' => ($c->contactType ?? '') === 'phone' ? 3 : 4, 'phoneNumber' => $c->contact ?? '' ]; } $records->uuid = $patient->uuid ?? null; $records->patientUuid = $patient->patientUuid ?? null; $records->patientBundleUuid = $patient->patientBundleUuid ?? null; return $this->insertCiszPatient($records); } public function insertCiszPatient($records) { $db = $this->db ?: $this->db = connectDb(); $api = new med_cisz_api(); $addresses = new med_cisz_addresses(); $belarusId = $addresses->getBelarusId(); if (is_object($records)) { $records = [$records]; } $enterpriseCode = getSessionValue('mapEnterpriseCode'); $ret = []; $sqlMan = <<<EOT
            INSERT INTO Man (SurName, Name, SecondName)
            VALUES (?, ?, ?)
            RETURNING ManCode AS id
EOT;
$stmtMan = $db->prepare($sqlMan); $sqlManPassport = <<<"EOT"
INSERT INTO ManPassport (ManCode, Sex, DateOfBirth, ManPassportKindCode, PassportNumber, DateOfIssue,
    DateOfExpiry, PersonalNumber, AddressText, AddressResideText, Authority, CiszAddressJson, CiszAddressResideJson,
    CiszAddressCode, CiszAddressResideCode,
    AddressFlat, AddressResideFlat, addressCode, addressResideCode,
    AddressHouse, addressResideHouse, addressCorps, addressResideCorps)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?)
RETURNING ManPassportCode AS id
EOT;
$stmtManPassport = $db->prepare($sqlManPassport); $sqlMedPatient = <<<"EOT"
INSERT INTO MedPatient (
    StartDate, FinishDate, EnterpriseCode, ManCode,
    personDataPermissionDate, JSON, MedPatientGroupCode, ChangeDate, NumberHistorySickness
)
VALUES (?, COALESCE(?::date, '3000-01-01')::date, ?, ?, ?, ?, ?, ?, ?)
RETURNING MedPatientCode AS id
EOT;
$stmtMedPatient = $db->prepare($sqlMedPatient); $sqlInsertManTask = <<<"EOT"
INSERT INTO ManTask (TypeTask, EnterpriseCode, ManCode)
VALUES (12, ?, ?)
EOT;
$stmtInsertManTask = $db->prepare($sqlInsertManTask); $sqlMedPatientCard = <<<"EOT"
INSERT INTO MedPatientCard (MedPatientCode, StartDate, FinishDate, 
    Document, DocumentNumber,
    ciszDisabilityGroupCode, ciszHealthLossStageCode, ciszPrivelegeCode, 
    ciszDictDisabilityGroupCode, ciszDictPrivelegeCode)
VALUES (?, ?, COALESCE(?::date, 30000101)::date, ?, ?, ?, ?, ?, ?, ?)
RETURNING MedPatientCardCode AS id
EOT;
$stmtMedPatientCard = $db->prepare($sqlMedPatientCard); $sqlMedAnamnesis = <<<EOT
            INSERT INTO MedAnamnesis (MedPatientCode, MaritalStatus)
            VALUES (?, ?)
            RETURNING MedAnamnesisCode AS id
EOT;
$stmtMedAnamnesis = $db->prepare($sqlMedAnamnesis); $sqlMedPatientRegistration = <<<"EOT"
            INSERT INTO MedPatientRegistration (
                FinishDate, MedPatientCode, MedPatientPolyclinicCode,
                StartDate, MedPatientGroupCode,
                AddressText, CiszResourceValueCodeSubType, CiszResourceValueCodeType,
                CreateDate, CiszAddressCode
            )
            VALUES ( COALESCE(?::date, 30000101)::date, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?)
            EOT;
$stmtMedPatientRegistration = $db->prepare($sqlMedPatientRegistration); $sqlCiszLink = <<<"EOT"
INSERT INTO CiszLink ( PrimaryKey, TableName, Type, Uuid, Status )
VALUES (?, ?, ?, ?, ?)
EOT;
$stmtCiszLink = $db->prepare($sqlCiszLink); $sqlCRV = <<<"EOT"
SELECT
    (SELECT CRV.CiszResourceValueCode
       FROM CiszResourceValue CRV
       JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
       WHERE CR.Identifier = 'ReasonForServiceCare'
         AND CRV.Code = 'residence') AS "type",
    (SELECT CRV2.CiszResourceValueCode
       FROM CiszResourceValue CRV2
       JOIN CiszResource CR2 ON CR2.CiszResourceCode = CRV2.CiszResourceCode
       WHERE CR2.Identifier = 'SubTypeServiceCare'
         AND CRV2.Code = 'main') AS "subType";
EOT;
$stmtCRV = $db->prepare($sqlCRV); $stmtCRV->execute(); $crv = $stmtCRV->fetch(PDO::FETCH_ASSOC); $regType = $crv['type'] ?? null; $regSubType = $crv['subType'] ?? null; $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $rec->personalNumber = $row->personalNumber ?? null; $stmtMan->execute([ $row->surname, $row->name, $row->secondname ]); $rec->manId = $stmtMan->fetchColumn(); $address = null; $addressReside = null; if ($row->addressCode) { $address = $this->getAddressId($row->addressCode, $api, $addresses, $belarusId); } if ($row->addressResideCode) { $addressReside = $this->getAddressId($row->addressResideCode, $api, $addresses, $belarusId); } $stmtManPassport->execute([ $rec->manId, $row->sex, $row->dateOfBirth, $row->passportKindId, $row->passportNumber, $row->dateOfIssue, $row->dateOfExpiry, $row->personalNumber, $row->addressText, $row->addressResideText, isUndefinedProperty($row, 'authority', null), $row->addressJson, $row->addressResideJson, $row->addressCode, $row->addressResideCode, $row->flatNumber, $row->flatResideNumber, $address->addressId ?? null, $addressReside->addressId ?? null, $address->house ?? null, $addressReside->house ?? null, $address->corpus ?? null, $addressReside->corpus ?? null ]); $rec->manPassportId = $stmtManPassport->fetchColumn(); $stmtMedPatient->execute([ $row->startDate ?? null, $row->finishDate ?? null, $enterpriseCode, $rec->manId, $row->personDataPermissionDate ?? null, $row->json ?? null, $row->medPatientGroupCode ?? null, $row->changeDate ?? null, $row->numberHistoryOfSickness ?? null ]); $rec->medPatientId = $stmtMedPatient->fetchColumn(); $stmtInsertManTask->execute([ $enterpriseCode, $rec->manId ]); if ($row->maritalStatus) { $stmtMedAnamnesis->execute([ $rec->medPatientId, $row->maritalStatus ]); } $stmtMedPatientRegistration->execute([ $row->finishDate ?? null, $rec->medPatientId, $row->medPatientPolyclinicCode ?? null, $row->startDate ?? null, $row->medPatientGroupCode ?? null, $row->addressText ?? $rec->addressResideText ?? null, $regSubType, $regType, date('Y-m-d H:i:s'), $row->addressCode ?? $rec->addressResideCode ?? null ]); if (!empty($row->contacts)) { $stmtPhone = $db->prepare("
                    INSERT INTO ManPhone (ManCode, phoneType, phoneNumber)
                    VALUES (?, ?, ?)
                "); foreach ($row->contacts as $c) { $stmtPhone->execute([ $rec->manId, $c['phoneType'], $c['phoneNumber'] ]); } } if (!empty($row->patientUuid)){ $stmtCiszLink->execute([ $rec->medPatientId, 'MedPatient', 1, $row->patientUuid, 1 ]); } if (!empty($row->patientBundleUuid)){ $stmtCiszLink->execute([ $rec->medPatientId, 'MedPatient', 2, $row->patientBundleUuid, 1 ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getAddressId($code, $api = null, $addresses = null, $belarusId = null) { if (!$api) { $api = new med_cisz_api(); } if (!$addresses) { $addresses = new med_cisz_addresses(); } if (!$belarusId) { $belarusId = $addresses->getBelarusId(); } $ciszResult = $api->readCiszAddresses((object)[ 'code' => $code, 'limit' => 1, ]); $items = $ciszResult->items ?? []; if (empty($items)) { return null; } $ciszAddress = $items[0]; $houseData = $this->parseHouseAndCorpus( $ciszAddress->fullName ?? '' ); $addressId = $addresses->getAddressCode((object)[ 'partOf' => $ciszAddress->partOf, 'address' => $ciszAddress, 'belarusId' => $belarusId ]); return (object)[ 'addressId' => $addressId, 'house' => $houseData->house ?? null, 'corpus' => $houseData->corpus ?? null, ]; } private function parseHouseAndCorpus(string $fullName) { $result = (object)[ 'house' => null, 'corpus' => null, ]; if (preg_match_all('/д\.\s*(\d+[а-яa-z]?)(?:\s*,?\s*к\.\s*(\d+))?/ui', $fullName, $matches, PREG_SET_ORDER)) { $last = end($matches); $result->house = $last[1]; $result->corpus = !empty($last[2]) ? $last[2] : null; } return $result; } public function getDocumentType($params) { $db = $this->db ?: $this->db = connectDb(); $documentCode = isEmptyProperty($params, 'documentCode'); return selectSqlValue( $db, "SELECT CRV.SourceCode
         FROM CiSZResourceValue CRV
         WHERE CRV.Code = " . $db->quote($documentCode) . "
         LIMIT 1" ); } public function getBusyAttributes($params) { $db = $this->db ?: $this->db = connectDb(); $organizationName = trim(isEmptyProperty($params, 'organizationName') ?? ''); $isWork = filter_var(isEmptyProperty($params, 'isWork'), FILTER_VALIDATE_BOOLEAN); $professionCode = isEmptyProperty($params, 'professionCode'); $professionText = trim(isEmptyProperty($params, 'professionText') ?? ''); $enterpriseId = null; $enterpriseKindCode = null; $professionId = null; $specializationId = null; $enterpriseId = selectSqlValue( $db, "SELECT EnterpriseCode
         FROM Enterprise
         WHERE Name = " . $db->quote($organizationName) . "
            OR FullName = " . $db->quote($organizationName) . "
         LIMIT 1" ); if (!$enterpriseId) { $db->query( "INSERT INTO Enterprise (Name)
             VALUES (" . $db->quote($organizationName) . ")" ); $enterpriseId = $db->lastInsertId(); $enterpriseKindCode = selectSqlValue( $db, "SELECT EnterpriseKindCode
             FROM EnterpriseKind
             WHERE Type = " . ($isWork ? 1 : 2) . "
             LIMIT 1" ); if ($enterpriseKindCode) { $db->query( "INSERT INTO EnterpriseInKind
                    (EnterpriseCode, EnterpriseKindCode)
                 VALUES (
                    " . $db->quote($enterpriseId) . ",
                    " . $db->quote($enterpriseKindCode) . "
                 )" ); } } else { $enterpriseKindCode = selectSqlValue( $db, "SELECT EIK.EnterpriseKindCode
             FROM EnterpriseInKind EIK
             JOIN EnterpriseKind EK ON EK.EnterpriseKindCode = EIK.EnterpriseKindCode
             WHERE EIK.EnterpriseCode = " . $db->quote($enterpriseId) . "
               AND EK.Type = " . ($isWork ? 1 : 2) . "
             LIMIT 1" ); } if ($professionText !== '') { $professionId = selectSqlValue( $db, "SELECT UniDictCode
             FROM UniDict
             WHERE DictNumber = 19
               AND Value = " . $db->quote($professionText) . "
             LIMIT 1" ); if (!$professionId) { $db->query( "INSERT INTO UniDict (DictNumber, Value)
                 VALUES (19, " . $db->quote($professionText) . ")" ); $professionId = $db->lastInsertId(); } } if (!empty($professionCode)) { $resource = $db->query( "SELECT *
                 FROM CiszResourceValue
                 WHERE Code = " . $db->quote($professionCode) . "
                 LIMIT 1" )->fetch(PDO::FETCH_ASSOC); if ($resource) { $specializationId = $resource['ciszresourcevaluecode']; if (!$isWork && !empty($professionId)) { if (empty($resource['sourcecode'])) { $db->query( "UPDATE CiszResourceValue
                            SET SourceCode = " . $db->quote($professionId) . "
                            WHERE CiszResourceValueCode = " . $db->quote($specializationId) ); } else { unset($resource['ciszresourcevaluecode']); $resource['sourcecode'] = $professionId; $columns = array_keys($resource); $values = array_map(fn($value) => $value === null ? 'NULL' : $db->quote($value), array_values($resource)); $db->query( 'INSERT INTO CiszResourceValue
                             ("' . implode('", "', $columns) . '")
                             VALUES (' . implode(', ', $values) . ')' ); } $specializationId = null; } } } return [ 'organizationId' => $enterpriseId, 'organizationKindId' => $enterpriseKindCode, 'specializationId' => $specializationId, 'professionId' => $professionId ]; } public function updatePatientCisz($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $maritalStatusCode = isEmptyProperty($params, 'maritalStatusCode'); $patientActive = isEmptyProperty($params, 'patientActive'); $maritalStatusMap = [ 'married' => 1, 'unmarried' => 2, 'widowed' => 4, 'divorced' => 3 ]; $db->beginTransaction(); try { if ($maritalStatusCode !== null && isset($maritalStatusMap[$maritalStatusCode])) { $sql = <<<EOT
        INSERT INTO MedAnamnesis (MedPatientCode, MaritalStatus)
        VALUES (?, ?)
        ON CONFLICT (MedPatientCode) DO UPDATE SET
            MaritalStatus = EXCLUDED.MaritalStatus
        EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId, $maritalStatusMap[$maritalStatusCode] ]); } if ($patientActive !== null) { $sql = <<<EOT
        UPDATE MedPatient
        SET Attributes = CASE
            WHEN ? = 0 THEN COALESCE(Attributes, 0) | 8
            ELSE COALESCE(Attributes, 0) & ~8
        END
        WHERE MedPatientCode = ?
        EOT;
$stmt = $db->prepare($sql); $stmt->execute([ filter_var($patientActive, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $patientId ]); } $db->commit(); } catch (Throwable $e) { $db->rollBack(); throw $e; } } } handleJsonRpc('med_cisz_receiveNewPatient'); 