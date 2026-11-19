<?php
 require_once('utilsTrait.php'); trait med_cisz_patientTrait { use med_cisz_utilsTrait; private function createPatient(): void { $visit = $this->visitPatient[array_key_first($this->visitPatient ?? [])] ?? $this->funcDiagnostic[array_key_first($this->funcDiagnostic ?? [])] ?? $this->medication[array_key_first($this->medication ?? [])] ?? $this->procedurePatient[array_key_first($this->procedurePatient ?? [])] ?? null; $row = selectSql($this->db, <<<SQL
SELECT
    mp.MedPatientCode AS "patientId",
    mp.ManCode AS "manId",
    m.SurName AS "surName",
    m.Name AS "name",
    m.SecondName AS "secondName",
    coalesce(mps.Alien, 0) AS "alien",
    mpk.Cipher AS "passportKindCipher",
    mps.PersonalNumber AS "personalNumber",
    mps.dateofissue AS "dateOfIssue",
    mps.dateofexpiry AS "dateOfExpiry",
    mps.Authority AS "authority",
    nullif(mps.PassportNumber,'') AS "passportNumber",
    coalesce(mps.sex, 0) AS sex,
    mps.DateOfBirth AS "dateOfBirth",
    coalesce(mp.OutReasonEx, 0) AS "outReason",
    mp.StartDate AS "startDate",
    mp.FinishDate AS "finishDate",
    ma.MaritalStatus AS "maritalStatus",
    (
        SELECT
            to_jsonb(array_agg(mpc))
        FROM (
          SELECT
              mpc.StartDate AS "startDate",
              mpc.FinishDate AS "finishDate",
              mpc.DocumentDate AS "documentDate",
              mpc.Enterprise AS "enterprise",
              concat(mpc.DocumentSeries, mpc.DocumentNumber) AS "documentNumber",
              mpc.CauseDisability AS "causeDisability",
              mpc.ciszDisabilityGroupCode AS "disabilityGroupId",
              CRVG.Code AS "disabilityGroupCode",
              mpc.ciszHealthLossStageCode AS "disabilityStageId",
              CRVL.Code AS "disabilityStageCode",
               (
                SELECT json_agg(CP.CiszPrivelegeCode)
                FROM MedPatientCardCiszPrivelege AS CP
                WHERE CP.MedPatientCardCode = PC.MedPatientCardCode
                ) AS "privelegeId",
              CRVP.Code AS "privelegeCode"
          FROM
              MedPatientCard mpc
                LEFT JOIN CiszResourceValue CRVG ON CRVG.CiszResourceValueCode = mpc.CiszDisabilityGroupCode
                LEFT JOIN CiszResourceValue CRVL ON CRVL.CiszResourceValueCode = mpc.CiszHealthLossStageCode
                LEFT JOIN CiszResourceValue CRVP ON CRVP.CiszResourceValueCode = mpc.ciszPrivelegeCode
          WHERE
              mpc.MedPatientCode = mp.MedPatientCode
              AND :onDate BETWEEN mpc.StartDate AND coalesce(mpc.FinishDate, '3000-1-1')
          ORDER BY mpc.StartDate DESC
        ) mpc
    ) AS "contingents",
    cl.UUID AS "uuid",
    mp.JSON AS "json",
    mp.Anonymous AS "anonymous",
    extract('year' FROM age(:onDate, mps.DateOfBirth)) AS "age",
    mp.NumberHistorySickness AS "docNumber",
    mp.personDataPermissionDate AS "personDataPermissionDate",
    CASE WHEN (coalesce(mp.attributes,0) & 2) = 2 THEN 1 ELSE 0 END "unknown"
    FROM 
    MedPatient mp
    JOIN Man m ON m.ManCode = mp.ManCode
    LEFT JOIN MedPatientCard PC ON PC.MedPatientCode = mp.MedPatientCode    
    LEFT JOIN ciszLink cl ON cl.Type = 1
        AND cl.TableName = 'MedPatient'
        AND cl.PrimaryKey = mp.MedPatientCode
    LEFT JOIN ManPassport mps ON mps.ManCode = m.ManCode
    LEFT JOIN ManPassportKind mpk ON mpk.ManPassportKindCode = mps.ManPassportKindCode
    LEFT JOIN MedAnamnesis ma ON ma.MedPatientCode = mp.MedPatientCode
    LEFT JOIN LATERAL (
        SELECT
            *
        FROM
            MedPatientBusy mpb
        LEFT JOIN enterpriseKind ek ON ek.EnterpriseKindCode = mpb.EnterpriseKindCode
        WHERE
            mpb.MedPatientCode = mp.MedPatientCode
            AND current_date BETWEEN mpb.StartDate AND coalesce(mpb.FinishDate, '3000-1-1')
            AND ek.Type = 1
        ORDER BY mpb.StartDate DESC
        LIMIT 1
    ) mpb ON TRUE
    LEFT JOIN LATERAL (
        SELECT
            *
        FROM
            MedPatientBusy mpb
        LEFT JOIN EnterpriseKind ek ON ek.EnterpriseKindCode = mpb.EnterpriseKindCode
        WHERE
            mpb.MedPatientCode = mp.MedPatientCode
            AND ek.Type = 2
        ORDER BY mpb.StartDate DESC
        LIMIT 1
    ) mpl ON TRUE
    LEFT JOIN Enterprise e ON e.EnterpriseCode = mpb.EnterpriseCode
    LEFT JOIN Enterprise el ON el.EnterpriseCode = mpl.EnterpriseCode
    LEFT JOIN UniDict ud ON ud.UniDictCode = mpb.UniDictCodeProfession
    LEFT JOIN UniDict udl ON udl.UniDictCode = mpl.UniDictCodeProfession
WHERE
    mp.MedPatientCode = :patientId
SQL,
[ ':patientId' => $visit->patientId ?? $this->patientId, ':onDate' => $visit->visitDate ?? dbDate(date_create()) ], PDO::FETCH_OBJ ); $row = reset($row); if (!($row->patientId ?? false)) { return; } $row->patientJob = self::getPatientJob($visit->patientId ?? $this->patientId); $row->patientStudy = self::getPatientStudy($visit->patientId ?? $this->patientId); if ($row->patientStudy) { foreach ($row->patientStudy as &$study) { if (!$study['patientStudyProfessionCode']) { throw new Exception( med_cisz_constant::$patientError['studyProfName']['text'], med_cisz_constant::$patientError['studyProfName']['code'] ); } $educationKind = self::whoIsThePatient($this->db, $study['patientStudyProfessionCode']); $study['educationKind'] = $educationKind; } } $this->patientId = $row->patientId; $row->json = safe_json_decode($row->json) ?? (object) []; if ($row->personalNumber) { $type = 'personalNumber'; } elseif ($row->passportNumber) { $type = 'passportNumber'; } elseif ($row->docNumber) { $type = 'docNumber'; } else { $type = 'unknown'; } $id = $this->findPatient($row->personalNumber ?: ($row->passportNumber ?: $row->docNumber), $type); if (!$id) { $id = $row->uuid ?? generateUUID(null, null, true); } $row->fullFio = transformFio($row->surName . ' ' . $row->name . ' ' . $row->secondName, 'fullfio'); $row->contingents = safe_json_decode($row->contingents); $row->disability = self::getPatientDisability($row); $row->privelege = self::getPatientPrivelege($row); $row->relatives = self::loadPatientRelatives($row->json->relatives ?? []); $profile = (object) [ 'fullUrl' => 'Patient/' . $id, 'resource' => (object) [ 'resourceType' => 'Patient', 'id' => $id, 'meta' => (object) [ 'profile' => [ $row->anonymous ? med_cisz_constant::$structureDefinitions['AnonymousPatientBy'] : ($row->personalNumber ? med_cisz_constant::$structureDefinitions['PatientWithIdentificationNumber'] : med_cisz_constant::$structureDefinitions['PatientWithoutIdentificationNumber']) ] ], 'extension' => self::getPatientExtensions($row), 'identifier' => $this->getManIdentifier($row), 'active' => true, 'name' => self::getManName($row), 'telecom' => $this->getManTelecom($row->manId), 'gender' => $row->sex == 1 ? 'female' : 'male', 'deceasedBoolean' => !$row->anonymous ? $row->outReason == 4 : null, 'address' => $row->anonymous ? null : $this->getManAddress($row), 'maritalStatus' => $this->getMaritalStatusStructure($row->maritalStatus), 'contact' => $this->getPatientContact($row->relatives), ], ]; if (!$row->unknown) { if (!$row->dateOfBirth) { throw new Exception( med_cisz_constant::$patientError['dateBirth']['text'], med_cisz_constant::$patientError['dateBirth']['code'] ); } $profile->resource->birthDate = $row->dateOfBirth; } elseif ($row->unknown) { $profile->resource->birthDate = null; } self::cleanEntity($profile); $this->patient = (object) [ 'profile' => $profile, 'raw' => $row, 'link' => (object) [ 'reference' => $profile->fullUrl, 'display' => $row->fullFio, ] ]; $patientUuid = self::checkPatientUuid($this->patientId); if ($patientUuid) { $fhirProfile = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/' . (str_starts_with($patientUuid,'Patient') ? $patientUuid : ('Patient/' . $patientUuid))]); if ($fhirProfile) { $this->patient->profile->resource = self::fhirMerge($fhirProfile, $profile->resource); } } } public function findPatient(?string $identifier = null, ?string $type = null): ?string { if ($type === 'personalNumber') { $param = [ 'identifier' => $identifier ]; } elseif ($type === 'passportNumber') { $param = [ 'identifier' => $identifier ]; } elseif ($type === 'docNumber') { $param = [ 'identifier' => $identifier, 'assigner' => $this->organization->link->reference ]; } elseif ($type === 'unknown') { throw new Exception("Не указаны данные пациента для поиска"); } $patientResource = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Patient', 'param' => $param ]); if (($patientResource = self::extractResourceFromBundle($patientResource, 'Patient'))) { $ciszData = getSessionValue('cisz') ?? (object) []; $ciszData->{$patientResource->id} = $patientResource; setSessionValue('cisz', $ciszData); return $patientResource->id; } return null; } private function getPatientJob($data) { $rows = selectSql($this->db, <<<SQL
        SELECT
            MPB.MedPatientBusyCode AS "patientBusyId",
            MPB.StartDate AS "patientBusyStartDate",
            MPB.FinishDate AS "patientBusyFinishDate",
            E.Name AS "patientBusyEnterpriseName",
            UD.Value AS "patientBusyProfessionName",
            MPB.attributes AS "mainPlaceJob",
            MPB.CiszResourceValueCodeEmployment AS "employmentId",
            CRV.Code AS "employmentCode"
        FROM MedPatient MP
            LEFT JOIN MedPatientBusy MPB ON MPB.MedPatientCode = MP.MedPatientCode
            LEFT JOIN EnterpriseKind EK ON EK.EnterpriseKindCode = MPB.EnterpriseKindCode
            LEFT JOIN Enterprise E ON E.EnterpriseCode = MPB.EnterpriseCode
            LEFT JOIN UniDict UD ON UD.UniDictCode = MPB.UniDictCodeProfession
            LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MPB.CiszResourceValueCodeEmployment
        WHERE MP.MedPatientCode = :patientid
          AND EK.Type = 1
        SQL,
[ 'patientid' => $data ]); return $rows; } private function getPatientStudy($data) { $rows = selectSql($this->db, <<<SQL
        SELECT
            mpb.MedPatientBusyCode AS "patientStudyId",
            mpb.StartDate AS "patientStudyStartDate",
            mpb.FinishDate AS "patientStudyFinishtDate",
            mpb.attributes AS "mainjobPlace",
            e.Name AS "patientStudyEnterpriseName",
            ud.UniDictCode AS "patientStudyProfessionCode",
            ud.Value AS "patientStudyProfessionName"
        FROM MedPatient mp
            LEFT JOIN MedPatientBusy mpb ON mpb.MedPatientCode = mp.MedPatientCode
            LEFT JOIN enterpriseKind ek ON ek.EnterpriseKindCode = mpb.EnterpriseKindCode
            LEFT JOIN Enterprise e ON e.EnterpriseCode = mpb.EnterpriseCode
            LEFT JOIN UniDict ud ON ud.UniDictCode = mpb.UniDictCodeProfession
        WHERE mp.MedPatientCode = :patientid
          AND ek.Type = 2
        SQL,
[ 'patientid' => $data ]); return $rows; } private function getManTelecom(int $manId): ?array { $result = null; $rows = selectSql($this->db, <<<SQL
SELECT
    coalesce(status, 0) AS "status",
    coalesce(PhoneType, 0) AS "phoneType",
    PhoneNumber AS "phoneNumber"
FROM
    ManPhone
WHERE
    ManCode = :manId
    AND (
        coalesce(status, 0) = 1
        OR (coalesce(status, 0) = 0 AND coalesce(PhoneType, 0) <> 4)
        OR coalesce(PhoneType, 0) = 4
    ) 
ORDER BY coalesce(status, 0) DESC,
    CASE coalesce(PhoneType, 0)
        WHEN 3 THEN 1
        WHEN 1 THEN 2
        WHEN 2 THEN 3
        ELSE 4
    END
SQL,
[ ':manId' => $manId ], PDO::FETCH_OBJ ); foreach ($rows as $row) { if ($row->phoneNumber) { $result[] = (object)[ 'system' => $row->phoneType == 4 ? 'email' : 'phone', 'value' => $row->phoneNumber, ]; } } return $result; } private function getManAddress($row, bool $checkObligation = true): ?array { $result = null; $data = selectSql($this->db, <<<SQL
SELECT
    mp.AddressResideCode AS "addressResideId",
    nullif(fn_cmCalcAddress(mp.ManCode, 1, 3, 1), '') as "lineReside",
    nullif(a_reside.place, '') AS "placeReside",
    nullif(a_reside.district, '') AS "districtReside",
    nullif(a_reside.area, '') AS "areaReside",
    nullif(mp.ZipCodeReside, '') AS "zipCodeReside",
    coalesce(nullif(c_reside.alpha2, ''), c_reside.cipher) AS "countryReside",
    c_reside.Name AS "countryNameReside",
    nullif(fn_cmCalcAddress(mp.ManCode, 1, 1, 3), '') as "textReside",
    mp.AddressCode AS "addressId",
    nullif(fn_cmCalcAddress(mp.ManCode, 2, 3, 1), '') as line,
    nullif(a.place, '') AS "place",
    nullif(a.district, '') AS "district",
    nullif(a.area, '') AS "area",
    nullif(mp.ZipCode, '') AS "zipCode",
    coalesce(nullif(c.alpha2, ''), c.cipher) AS country,
    c.Name AS "countryName",
    a_reside.CodeSoato AS "resideSoato",
    a.CodeSoato AS "soato",
    mp.AddressHouse AS "addressHouse",
    mp.AddressResideHouse  AS "addressResideHouse",
    mp.AddressFlat AS "addressFlat",
    mp.AddressResideFlat AS "addressResideFlat",
    nullif(fn_cmCalcAddress(mp.ManCode, 2, 1, 3), '') as "text",
    mp.CiszAddressJson AS "ciszAddressJson",
    mp.CiszAddressResideJson AS "ciszAddressResideJson",
    mp.CiszAddressCode AS "ciszAddressCode",
    mp.CiszAddressResideCode AS "ciszAddressResideCode"
FROM
    ManPassport mp
    LEFT JOIN Address a_reside ON a_reside.AddressCode = mp.AddressResideCode
    LEFT JOIN Country c_reside ON c_reside.CountryCode = a_reside.CountryCode
    LEFT JOIN Address a ON a.AddressCode = mp.AddressCode
    LEFT JOIN Country c ON c.CountryCode = a.CountryCode
WHERE
    mp.ManCode = :manId
SQL,
[ ':manId' => $row->manId ], PDO::FETCH_OBJ ); $data = reset($data); $isForeignAddress = false; $isForeignAddressReside = false; if (!empty($data->countryNameReside) && !str_contains($data->countryNameReside, 'Беларус') && !str_contains($data->countryNameReside, 'PБ')) { $isForeignAddressReside = true; } if (!empty($data->countryName) && !str_contains($data->countryName, 'Беларус') && !str_contains($data->countryName, 'PБ')) { $isForeignAddress = true; } if ($row->personalNumber && !$data->ciszAddressJson && !$data->ciszAddressResideJson && !$data->addressId && !$data->addressResideId && $checkObligation && !$data->ciszAddressCode && !$data->ciszAddressResideCode && !$isForeignAddressReside && !$isForeignAddress) { throw new Exception( med_cisz_constant::$patientError['address']['text'], med_cisz_constant::$patientError['address']['code'] ); } if ($data->ciszAddressJson) { $result[] = safe_json_decode($data->ciszAddressJson); } else { if (($data->addressId || $data->ciszAddressCode) ?? false) { $extensions = []; if (!$isForeignAddress && $data->ciszAddressCode) { $extensions[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['StreetGeoRegistry'], med_cisz_constant::$valueSet['StreetGeoRegistry'], $data->ciszAddressCode ); } if ($data->addressFlat && !$isForeignAddress) { $extensions[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['ApartmentRoom'], 'valueString' => 'кв. ' . $data->addressFlat ]; } $result[] = (object) [ 'extension' => $extensions ?: null, 'use' => 'home', 'text' => $data->text, 'line' => [$data->line], 'city' => $data->place, 'district' => $data->district, 'state' => $data->area, 'postalCode' => $data->zipCode, 'country' => $data->countryName, ]; } } if ($data->ciszAddressResideJson) { $result[] = safe_json_decode($data->ciszAddressResideJson); } else { if (($data->addressResideId || $data->ciszAddressResideCode) ?? false) { $extensions = []; if (!$isForeignAddressReside && $data->ciszAddressResideCode) { $extensions[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['StreetGeoRegistry'], med_cisz_constant::$valueSet['StreetGeoRegistry'], $data->ciszAddressResideCode ); } if ($data->addressResideFlat && !$isForeignAddressReside) { $extensions[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['ApartmentRoom'], 'valueString' => 'кв. ' . $data->addressResideFlat ]; } $result[] = (object) [ 'extension' => $extensions ?: null, 'use' => 'temp', 'text' => $data->textReside, 'line' => [$data->lineReside], 'city' => $data->placeReside, 'district' => $data->districtReside, 'state' => $data->areaReside, 'postalCode' => $data->zipCodeReside, 'country' => $data->countryNameReside, ]; } } return $result; } private function getPatientExtensions(object $data): ?array { $result = null; $jsonData = $data->json ?? (object) []; if ($data->anonymous ?? false) { if ($data->dateOfBirth) { $result[] = self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['AnonymousPatientAge'], (int) $data->age ); } return $result; } if (isset($jsonData->incapacity)) { $result[] = self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['IncapacityExtension'], $jsonData->incapacity == true ? false : true ); } else { $result[] = self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['IncapacityExtension'], true ); } if (!empty($data->patientJob)) { foreach ($data->patientJob as $job) { if (!$job['patientBusyProfessionName']) { throw new Exception( med_cisz_constant::$patientError['profName']['text'], med_cisz_constant::$patientError['profName']['code'] ); } $extensions = self::niceArray([ $job['patientBusyProfessionName'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PatientJobProfession'], $job['patientBusyProfessionName'] ) : null, $job['employmentCode'] ? self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['PatientJobProfessionCode'], med_cisz_constant::$valueSet['SpecialtyProfPerson'], $job['employmentCode'] ) : null, $job['patientBusyEnterpriseName'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PatientJobPlace'], $job['patientBusyEnterpriseName'] ) : null, $job['patientBusyStartDate'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['TermOFWork'], new DateTime($job['patientBusyStartDate']), true, new DateTime($job['patientBusyFinishDate']), ) : null, $job['mainPlaceJob'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['MainJobPlace'], (bool)$job['mainPlaceJob'] ) : self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['MainJobPlace'], false ) ]); $result[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['PatientJob'], 'extension' => $extensions ?: null, ]; } } if (!empty($data->patientStudy)) { foreach ($data->patientStudy as $study) { if (!$study['patientStudyProfessionName']) { throw new Exception( med_cisz_constant::$patientError['studyProfName']['text'], med_cisz_constant::$patientError['studyProfName']['code'] ); } $dateTime = new DateTime($study['patientStudyStartDate']); $extensions = self::niceArray([ $study['patientStudyEnterpriseName'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PatientStudyPlace'], $study['patientStudyEnterpriseName'] ) : null, $study['patientStudyProfessionCode'] ? self::getEducationStructure( med_cisz_constant::$valueSet['EducationKind'], $study['educationKind'], $study['patientStudyProfessionName'] ?? '' ) : null, $study['patientStudyStartDate'] ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PatientTermOfStudy'], new DateTime($study['patientStudyStartDate'],), true, new DateTime($study['patientStudyFinishtDate']), ) : null ]); $result[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['PatientStudy'], 'extension' => $extensions ?: null, ]; } } if ($data->disability ?? false) { if ($data->disability->disabilityGroupCode && !self::getDisabilityReason($data->disability)) { throw new Exception( med_cisz_constant::$patientError['disabilityReason']['text'], med_cisz_constant::$patientError['disabilityReason']['code'] ); } if ($data->disability && !$data->disability->documentNumber) { throw new Exception( med_cisz_constant::$patientError['disabilityDocument']['text'], med_cisz_constant::$patientError['disabilityDocument']['code'] ); } $result[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['DisabilityGroup'], med_cisz_constant::$valueSet['DisabilityGroup'], $data->disability->disabilityGroupCode ?? $data->disability->disabilityStageCode ); $result[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['DisabilityReason'], med_cisz_constant::$valueSet['DisabilityReason'], self::getDisabilityReason($data->disability) ); } if ($data->privelege ?? false) { if (!empty($data->privelege->privelegeId)) { foreach ($data->privelege->privelegeId as $privilege) { $result[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['PatientPreferences'], med_cisz_constant::$valueSet['VSPatientPreferences'], self::getcCiszValue($this->db, $privilege) ); } } } if ($data->personDataPermissionDate ?? false) { $dateTime = new DateTime($data->personDataPermissionDate); $result[] = (object) [ 'extension' => [ self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['ConsentPersonalDataBoolean'], $data->personDataPermissionDate ? true : false ), self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['ConsentPersonalDataPeriod'], $dateTime, true ) ], 'url' => med_cisz_constant::$structureDefinitions['ConsentPersonalData'] ]; } return $result; } private function getManIdentifier(object $data, ?array $ignoreValueSets = [], bool $related = false, $relatedPerm = true): ?array { $result = null; $jsonData = $data->json ?? (object) []; $getPassportExtensions = function (?string $date, ?string $authority): array { return [ $date ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PassportIssuedDate'], new DateTime($date), false ) : null, $authority ? self::getExtensionValueStructure( med_cisz_constant::$structureDefinitions['PassportIssuedString'], $authority ) : null, ]; }; $makeDocumentStructure = function (string $code, string $number, ?array $extensions = null, ?string $startDate = null, ?string $finishDate = null): object { return (object) [ 'extension' => $extensions ? [ (object) [ 'url' => med_cisz_constant::$structureDefinitions['PassportIssued'], 'extension' => self::niceArray($extensions), ] ] : null, 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], $code), 'value' => $number, 'period' => self::getTypePeriodStructure($startDate, $finishDate), ]; }; if ($data->anonymous ?? false) { if (!in_array('ANO', $ignoreValueSets ?? [])) { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['DocNumbersBy'], 'ANO'), 'value' => addslashes($data->docNumber), 'period' => self::getTypePeriodStructure($data->startDate), ]; $resource->assigner = $this->organization->link; } return $result; } if ($data->passportKindCipher == 10) { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'MCN'), 'value' => $data->passportNumber, 'period' => self::getTypePeriodStructure($data->dateOfIssue) ]; } if ($data->passportKindCipher == '08') { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'RCN'), 'value' => $data->passportNumber, 'period' => self::getTypePeriodStructure(!empty($data->startDate) ? $data->startDate : $data->dateOfIssue) ]; } if ($data->passportKindCipher == '09') { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'Biometric-for-a-foreign-citizen'), 'value' => $data->personalNumber, 'period' => self::getTypePeriodStructure($data->startDate ? $data->startDate : $data->dateOfIssue) ]; } if (!empty($data->docNumber) && empty($data->unknown) && !$related) { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'UMD'), 'value' => addslashes($data->docNumber), 'assigner' => $this->organization->link ]; } else if (!empty($data->docNumber)) { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'UIP'), 'value' => addslashes($data->docNumber), 'assigner' => $this->organization->link ]; } if ($related && !$relatedPerm) { $result[] = $resource = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'UMD'), 'value' => (string)$data->id, 'assigner' => $this->organization->link ]; } if (($data->personalNumber ?? false) && !in_array('INP', $ignoreValueSets ?? [])) { $result[] = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'INP'), 'value' => $data->personalNumber, 'period' => self::getTypePeriodStructure($data->dateOfIssue), ]; if (($data->passportKindCipher !== '09') && !in_array('Identification-card-of-a-citizen-RB', $ignoreValueSets ?? []) && $data->passportKindCipher == '11') { $result[] = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'Identification-card-of-a-citizen-RB'), 'value' => $data->personalNumber, 'period' => self::getTypePeriodStructure($data->dateOfIssue), ]; } } if (!($data->alien ?? false) && (!$data->passportKindCipher || $data->passportKindCipher == '01') && !in_array('Passport-RB', $ignoreValueSets ?? []) && $data->passportNumber ) { $result[] = $makeDocumentStructure( 'Passport-RB', $data->passportNumber, $getPassportExtensions($data->dateOfIssue, $data->authority), $data->dateOfIssue, $data->dateOfExpiry ?? null ); } if (($data->alien ?? false) && !$data->personalNumber && !in_array('FDN', $ignoreValueSets ?? []) && $data->passportKindCipher !== '09' && $data->passportKindCipher !== '08' && $data->passportKindCipher !== '99') { $result[] = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'INP'), 'value' => $data->personalNumber, 'period' => self::getTypePeriodStructure($data->dateOfIssue, $data->dateOfExpiry ?? null), ]; } if ($data->passportKindCipher == '03' && $related) { $result[] = $makeDocumentStructure( 'FDN', $data->passportNumber, ); } if ($data->passportKindCipher == '99') { $result[] = $makeDocumentStructure( 'CCN', $data->json->convictedCertificate, ); } if (($data->disability ?? false) && !empty($data->disability->documentNumber) && !in_array('CDP', $ignoreValueSets ?? []) ) { $result[] = $makeDocumentStructure( 'CDP', $data->disability->documentNumber, $getPassportExtensions($data->disability->documentDate ?? $data->disability->startDate, $data->disability->enterprise), $data->disability->startDate, $data->disability->finishDate ); } if ($related && $data->docDisabilityNumber) { $result[] = $makeDocumentStructure( 'CDP', $data->docDisabilitySeries . $data->docDisabilityNumber, $getPassportExtensions($data->disabilityDocDate ?? $data->disabilityStartDate, $data->disabilityEnterprise), $data->disabilityStartDate, $data->disabilityFinishDate ); } if (($jsonData->birthCertificate ?? false) && !in_array('BC', $ignoreValueSets ?? [])) { $result[] = (object) [ 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['PersonalDocumentType'], 'BC'), 'value' => $jsonData->birthCertificate, ]; } return $result; } private static function getPatientDisability(object $data): ?object { foreach ($data->contingents ?? [] as $item) { if ($item->disabilityGroupId || $item->disabilityStageId) { return $item; } } return null; } private static function getPatientPrivelege(object $data): ?object { foreach ($data->contingents ?? [] as $item) { if ($item->privelegeId) { return $item; } } return null; } private function loadPatientRelatives(?array $relatives = null, bool $tempDis = false): array { if (!$relatives) { return []; } if ($tempDis) { $manIds = $relatives[0]->id; if (!$manIds) { return []; } $whereCondition = "m.ManCode = $manIds"; } else { $relativeIds = implode(',', array_column($relatives, 'id')); if (!$relativeIds) { return []; } $whereCondition = "mr.MedRelativesCode IN ($relativeIds)"; } $result = selectSql($this->db, <<<SQL
            SELECT
                MR.MedRelativesCode AS "id",
                MR.ManCode AS "manId",
                m.SurName AS "surName",
                m.Name AS "name",
                m.SecondName AS "secondName",
                mpk.Cipher AS "passportKindCipher",
                mp.PersonalNumber AS "personalNumber",
                mp.dateofissue AS "dateOfIssue",
                mp.Authority AS "authority",
                mp.PassportNumber AS "passportNumber",
                coalesce(mp.Sex, 0) AS "sex",
                mp.DateOfBirth AS "dateOfBirth",
                MR.RelationDegree AS "degree",
                cl.uuid,
                mp.DateOfIssue AS "dateOfIssue",
                mrf.DocumentSeries AS "docDisabilitySeries",
                mrf.DocumentDate AS "disabilityDocDate",
                mrf.StartDate AS "disabilityStartDate",
                mrf.FinishDate AS "disabilityFinishDate",
                mrf.Enterprise AS "disabilityEnterprise",
                mrf.DocumentNumber AS "docDisabilityNumber",
                crv.Code AS "disabilityCode",
                crv.Name AS "disabilityName",
                nullif(fn_cmCalcAddress(mp.ManCode, 1, 1, 3), '') as "textResideAddress",
                nullif(fn_cmCalcAddress(mp.ManCode, 2, 1, 3), '') as "textAddress",
                MR.Attributes AS "attributes",
                MR.CiszResourceValueCodeFamilyMemeber AS "familyMemeberRelationship",
                CRVF.Code AS "familyMemeberRelationshipCode",
                MR.CiszResourceValueCodeRelatedType AS "relatedPersonType",
                CRVR.Code AS "relatedPersonTypeCode"
            FROM
                MedRelatives MR
                JOIN Man m ON m.ManCode = MR.ManCode
                LEFT JOIN ManPassport mp ON mp.ManCode = MR.ManCode
                LEFT JOIN ManPassportKind mpk ON mpk.ManPassportKindCode = mp.ManPassportKindCode
                LEFT JOIN MedRelativesFeature mrf ON mrf.MedRelativesCode = MR.MedRelativesCode
                LEFT JOIN CiszResourceValue crv ON crv.CiszResourceValueCode = MRF.CiszResourceValueCode
                LEFT JOIN CiszResourceValue CRVF ON CRVF.CiszResourceValueCode = MR.CiszResourceValueCodeFamilyMemeber
                LEFT JOIN CiszResourceValue CRVR ON CRVR.CiszResourceValueCode = MR.CiszResourceValueCodeRelatedType
                LEFT JOIN ciszLink cl ON cl.Type = 1
                    AND cl.TableName = 'MedRelatives'
                    AND cl.PrimaryKey = MR.MedRelativesCode 
            WHERE
                $whereCondition
            SQL,
null, PDO::FETCH_OBJ ); foreach ($result as &$item) { $item->fullFio = transformFio($item->surName . ' ' . $item->name . ' ' . $item->secondName, 'fullfio'); if ($tempDis) { $relative = arrayFindObject($relatives, 'manId', $item->manId); } else { $relative = arrayFindObject($relatives, 'id', $item->id); } if ($relative) { $item->diagnosisCipher = selectSqlValue( $this->db, 'SELECT Cipher1 FROM MedDiagnosis WHERE MedDiagnosisCode = ?', [$relative->diagnosisId ?? null] ); $item = mergeObjects($item, $relative); } } return $result; } private function getPatientContact(array $relatives): ?array { $result = null; foreach ($relatives as $relative) { if ($relative->attributes === 1) { $telecom = self::getManTelecom($relative->manId); if (!$telecom) { throw new Exception( med_cisz_constant::$patientError['contactPerson']['text'], med_cisz_constant::$patientError['contactPerson']['code'] ); } if ($relative->relatedPersonType) { $relationship = [ self::getCodingStructure( med_cisz_constant::$valueSet['RelatedPersonType'], $relative->relatedPersonTypeCode ) ]; } $addresses = self::getManAddress($relative, false); $address = null; if ($addresses) { foreach ($addresses as $item) { if (($item->use ?? null) === 'home') { $address = $item; break; } } $address ??= reset($addresses); unset($address->use); } $profile = (object)[ 'relationship' => isset($relationship) ? $relationship : null, 'name' => self::getManName($relative)[0] ?? null, 'telecom' => self::getManTelecom($relative->manId), 'address' => $address ]; if ($profile->name->period ?? false) { $profile->name->period = null; } self::cleanEntity($profile); $result[] = $profile; } } return $result; } private function getManName(object $data) { if (!isset($data->unknown) || !$data->unknown) { return [ (object) [ 'text' => $data->fullFio ?? null, 'family' => $data->surName, 'given' => self::niceArray([$data->name ?? null, $data->secondName ?? null]), 'period' => (($data->anonymous ?? 0) !==1) ? (object)[ 'start' => (new DateTime($data->dateOfBirth ?? date('c')))->format('c') ] : null ] ]; } } public function getAdditionalPatientInfo(int $type = 0): void { $this->addInfo = new StdClass(); switch ($type) { case 0 : { $hospRequest = self::doHttpMethodWithAuth($this->settings, (object)[ 'url' => $this->settings->url . 'fhir/Patient/' . $this->patient->profile->resource->id . '/EpisodeOfCare?_profile=https://fhir.by/StructureDefinition/Hospitalisation&status=active' . '&organization=' . $this->organization->id ]); if (count($hospRequest->entry) > 1) { $hospRequest = $hospRequest->entry[1]->resource->resourceType . '/' . $hospRequest->entry[1]->resource->id; } else { $hospRequest = null; }; $this->addInfo->hospitalisation = $hospRequest; } } } }