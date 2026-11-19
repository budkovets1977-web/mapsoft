<?php
 require_once('utilsTrait.php'); trait med_cisz_placeOfCare { use med_cisz_utilsTrait; private function createPlaceOfCare() { $data = $this->patient->raw; if ($data->anonymous || $data->unknown) { return []; } $registrationsData = selectSql($this->db, <<<SQL
            SELECT
                MER.MedPatientRegistrationCode AS "id",
                MER.MedPatientCode AS "patientId",
                MER.StartDate AS "startDate",
                NULLIF(MER.FinishDate, '3000-01-01') AS "finishDate",
                MER.OutReasonEx AS "outReasonEx",
                MER.TriggerFlag AS "triggerFlag",
                MER.RegistrationNumber AS "registrationNumber",
                MER.Json AS "json",
                MER.CiszResourceValueCodeType AS "typeId",
                CRVT.Code AS "typeCode",
                MER.CiszResourceValueCodeSubType AS "subTypeId",
                CRVS.Code AS "subTypeCode",
                MER.AddressCode AS "addressCode",
                MP.ManCode AS "manId",
                (
                    SELECT 
                        CASE 
                            WHEN (MER.AddressCode = MPASS.addressresidecode OR MER.CiszAddressCode = MPASS.CiszAddressResideCode) THEN 'home'
                            WHEN (MER.AddressCode = MPASS.addresscode OR MER.CiszAddressCode = MPASS.CiszAddressCode) THEN 'temp'
                            ELSE NULL
                        END
                    FROM ManPassport MPASS
                    WHERE MPASS.ManCode = MP.ManCode
                    LIMIT 1
                ) AS "addressType",
                MER.AddressHouse AS "addressHouse",
                MER.AddressCorps AS "addressCorps",
                MER.AddressFlat AS "addressFlat",
                MER.AddressText AS "addressText",
                A.CodeSoato AS "soato",
                MER.CiszAddressJson AS "ciszAddressJson",
                MER.AddressKind AS "addressKind",
                MER.CreateDate AS "createDate",
                MER.MedPatientPolyclinicCode AS "polyclinicId",
                MER.OutReasonEx AS "outReasonEx",
                MER.CiszAddressCode AS "ciszAddressCode",
                MER.AddressText AS "addressText",
                C.Name AS "countryName"
            FROM MedPatientRegistration MER
            LEFT JOIN CiszResourceValue CRVT ON CRVT.CiszResourceValueCode = MER.CiszResourceValueCodeType
            LEFT JOIN CiszResourceValue CRVS ON CRVS.CiszResourceValueCode = MER.CiszResourceValueCodeSubType
            LEFT JOIN Address A ON A.AddressCode = MER.AddressCode
            LEFT JOIN MedPatient MP ON MP.MedPatientCode = MER.MedPatientCode
            LEFT JOIN ManPassport MPASS ON MPASS.ManCode = MP.ManCode
            LEFT JOIN Country C ON C.CountryCode = A.CountryCode
            WHERE MER.MedPatientCode = :patientId
        SQL,
[ ':patientId' => $data->patientId ], PDO::FETCH_OBJ); foreach ($registrationsData as $registrationData) { $id = generateUUID(null, null, true); $organisationUuid = selectSqlValue($this->db, <<<SQL
            SELECT code
            FROM CiszResourceValue CRV
            LEFT JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
            WHERE sourceCode = :sourceId AND CR.Identifier = '1.2.643.2.69.1.1.1.64'
        SQL,
[ ':sourceId' => $registrationData->polyclinicId ]); if ($data->uuid && $registrationData->subTypeCode === 'main') { $patientResource = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Patient/' . $data->uuid . '/Contract?status=executable&type=residence&subtype=main' . '&_profile=' . med_cisz_constant::$structureDefinitions['PlaceOfCare'] ]); foreach ($patientResource->entry as $contract) { if (!isset($contract->fullUrl)) { continue; } if (str_replace('Organization/', '', $contract->resource->authority[0]->reference) === $organisationUuid) { continue 2; } else { continue; } } } elseif ($data->uuid && $registrationData->subTypeCode === 'additional') { $patientResource = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Patient/' . $data->uuid . '/Contract?status=executable&type=residence&subtype=additional' . '&_profile=' . med_cisz_constant::$structureDefinitions['PlaceOfCare'] ]); foreach ($patientResource->entry as $contract) { if (!isset($contract->fullUrl)) { continue; } if (str_replace('Organization/', '', $contract->resource->authority[0]->reference) === $organisationUuid) { continue 2; } else { continue; } } } $json = $registrationData->json ? json_decode($registrationData->json) : ''; $location = $json && isset($json->location) ? $json->location : null; self::createDocumentReferences($registrationData->id, 112); $registrationData->placeOfCareDocument = $this->placeOfCareDocuments; $this->placeOfCareDocuments = null; if (isset($registrationData->startDate)) { $status = 'executable'; } if (!$organisationUuid) { throw new Exception( med_cisz_constant::$patientError['organizationPlaceOfCare']['text'], med_cisz_constant::$patientError['organizationPlaceOfCare']['code'] ); } if (isset($registrationData->outReasonEx)) { switch ($registrationData->outReasonEx) { case 1: $outReason = 'Выбыл'; break; case 2: $outReason = 'По возрасту'; break; case 3: $outReason = 'Передан в др.учреждение'; break; case 4: $outReason = 'Смерть'; break; case 5: $outReason = 'Изъят'; break; case 6: $outReason = 'По сроку прикрепления'; break; } } if ($registrationData->subTypeCode === 'main' && $organisationUuid === str_replace('Organization/', '', $this->organization->link->reference)) { $location = ''; } else { $location = $json && isset($json->location) ? $json->location : null; } $profile = (object) [ 'fullUrl' => 'PlaceOfCare/' . $id, 'resource' => (object) [ 'resourceType' => 'Contract', 'id' => $id, 'meta' => (object) [ 'profile' => [ med_cisz_constant::$structureDefinitions['PlaceOfCare'] ] ], 'extension' => self::getPlaceOfCareExtension($registrationData), 'status' => $status, 'issued' => $registrationData->createDate ?? date('c'), 'applies' => (object) [ 'start' => $registrationData->startDate ?? null, 'end' => $registrationData->finishDate ?? null ], 'subject' => [$this->patient->link], 'authority' => [ (object) [ 'reference' => 'Organization/' . $organisationUuid ] ], 'site' => $location ? [ (object)[ 'reference' => 'Location/' . $location->uuid ] ] : null, 'title' => 'Закрепление пациента', 'subtitle' => isset($outReason) ? $outReason : null, 'author' => $this->organization->link, 'type' => self::getCodingStructure(med_cisz_constant::$valueSet['ReasonForServiceCare'], $registrationData->typeCode ? $registrationData->typeCode : 'residence'), 'subType' => [ self::getCodingStructure(med_cisz_constant::$valueSet['SubTypeServiceCare'], $registrationData->subTypeCode) ], 'supportingInfo' => self::getCarePlanDocuments($registrationData->placeOfCareDocument) ] ]; self::cleanEntity($profile); $this->placeOfCare[] = $profile; } } private function getCarePlanDocuments($data) { $result = []; if (!$data) { return null; } foreach ($data as $document) { $result[] = (object) [ 'reference' => $document->fullUrl, ]; } return $result; } private function getPlaceOfCareExtension($data) { $result = []; $isForeignAddress = false; if (!empty($data->countryName) && !str_contains($data->countryName, 'Беларус') && !str_contains($data->countryName, 'PБ')) { $isForeignAddress = true; } if ($data->ciszAddressCode || $data->addressCode || $isForeignAddress) { $adressText = ''; if ($data->addressType === 'home') { $adressText = selectSqlValue($this->db, <<<SQL
                    SELECT
                        FN_cmCalcAddress( MP.ManCode, 1)
                    FROM ManPassport MP
                    WHERE MP.ManCode = :manId
                SQL,
[ ':manId' => $data->manId ]); } elseif ($data->addressType === 'temp') { $adressText = selectSqlValue($this->db, <<<SQL
                    SELECT
                        FN_cmCalcAddress( MP.ManCode, 2)
                    FROM ManPassport MP
                    WHERE MP.ManCode = :manId
                SQL,
[ ':manId' => $data->manId ]); } else { $adressText = self::buildAddressFromRegistration($this->db, $data); } $extensions = []; if (!$isForeignAddress && $data->ciszAddressCode) { $extensions[] = self::getValueCodeableConceptStructure(med_cisz_constant::$structureDefinitions['StreetGeoRegistry'], med_cisz_constant::$valueSet['StreetGeoRegistry'], $data->ciszAddressCode); } if ($data->addressFlat) { $extensions[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['ApartmentRoom'], 'valueString' => 'кв.' . $data->addressFlat ]; } $result[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['AddressExtension'], 'valueAddress' => (object) [ 'extension' => $extensions ?: null, 'text' => $adressText, 'use' => $data->addressType ?? null ] ]; } else { throw new Exception( med_cisz_constant::$patientError['registrationAddress']['text'], med_cisz_constant::$patientError['registrationAddress']['code'] ); } return $result; } private function buildAddressFromRegistration($db, $data): string { if (!empty($data->addressText)) { $addressText = trim($data->addressText); if (!empty($data->addressFlat)) { $addressText .= ', кв. ' . trim($data->addressFlat); } return $addressText; } if (!$data->addressCode) { return ''; } $sql = <<<SQL
        SELECT
            A.Area,
            A.AreaType,
            A.District,
            A.Place,
            PT.ShortName AS placeType,
            A.Street,
            ST.ShortName AS streetType
        FROM Address A
        LEFT JOIN AddressPlaceType PT
            ON PT.AddressPlaceTypeCode = A.AddressPlaceTypeCode
        LEFT JOIN AddressStreetType ST
            ON ST.AddressStreetTypeCode = A.AddressStreetTypeCode
        WHERE A.AddressCode = ?
        LIMIT 1
    SQL;
$stmt = $db->prepare($sql); $stmt->execute([$data->addressCode]); $a = $stmt->fetch(PDO::FETCH_ASSOC); if (!$a) { return ''; } $parts = []; if (!empty($a['place'])) { $place = trim($a['place']); if (!empty($a['placetype'])) { $place = trim($a['placetype']) . ' ' . $place; } $parts[] = $place; } if (!empty($a['street'])) { $street = trim($a['street']); if (!empty($a['streettype'])) { $street = trim($a['streettype']) . ' ' . $street; } $parts[] = $street; } $housePart = []; if ($data->addressHouse) { $housePart[] = 'д. ' . trim($data->addressHouse); } if ($data->addressCorps) { $housePart[] = 'к. ' . trim($data->addressCorps); } if ($data->addressFlat) { $housePart[] = 'кв. ' . trim($data->addressFlat); } if ($housePart) { $parts[] = implode(' ', $housePart); } return implode(', ', $parts); } } 