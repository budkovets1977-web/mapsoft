<?php
 require_once('documentReferenceTrait.php'); require_once(__DIR__.'/../../../../lib/tcpdf/tcpdf.php'); trait med_cisz_utilsTrait { public static function init(object $class, ?string $config = null): void { $class->db = connectDb($config); static::checkSettings(($class->settings = readExtraSettings($class->db, med_cisz_constant::SETTING_NAME))); } private static function checkSettings(object $settings): void { if (empty($settings)){ throw new Exception( med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_TEXT, med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_CODE ); } elseif (($settings->active ?? false)) { if (!($settings->url ?? false)){ throw new Exception( sprintf(med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_TEXT, 'URL взаимодействия'), med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_CODE ); } if (!($settings->urlAuth ?? false)){ throw new Exception( sprintf(med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_TEXT, 'URL авторизации'), med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_CODE ); } if (!($settings->organizationId ?? false)) { throw new Exception( sprintf(med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_TEXT, 'Организация'), med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_CODE ); } if (!($settings->clientId ?? false) && !($settings->mapClientId ?? false)) { throw new Exception( sprintf(med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_TEXT, 'Идентификатор клиента'), med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_CODE ); } if (!($settings->redirectUri ?? false) && !($settings->mapRedirectUri ?? false)) { throw new Exception( sprintf(med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_TEXT, 'URI перенаправления при входе'), med_cisz_constant::ERROR_SETTINGS_NOT_EXISTS_ITEM_CODE ); } $origin = parse_url($settings->redirectUri); $settings->origin = $origin['scheme'] . '://' . $origin['host'] . (empty($origin['port']) ? '' : (':' . $origin['port'])); if (!str_ends_with($settings->url, '/')){ $settings->url .= '/'; } if (!str_ends_with($settings->urlAuth, '/')){ $settings->url .= '/'; } if (!($settings->mapClientId ?? false)) { $settings->mapClientId = $settings->clientId; } if ($settings->mapRedirectUri) { $origin = parse_url($settings->mapRedirectUri); $settings->mapOrigin = $origin['scheme'] . '://' . $origin['host'] . (empty($origin['port']) ? '' : (':' . $origin['port'])); } } else { throw new Exception( med_cisz_constant::ERROR_SETTINGS_NOT_ACTIVE_TEXT, med_cisz_constant::ERROR_SETTINGS_NOT_ACTIVE_CODE ); } } public static function cleanEntity(&$entity): void { foreach ($entity as $key => &$value) { if (is_array($value) || is_object($value)) { self::cleanEntity($value); } elseif (is_null($value)) { if (is_array($entity)) { unset($entity[$key]); } elseif (is_object($entity)) { unset($entity->$key); } } } } public static function extractResourceFromSearchSet(?object $searchSet = null, ?int $index = 0): ?object { if (($result = $searchSet->resource ?? false) && ($result->id ?? false)) { return $result; } if (($result = $searchSet->entry[$index] ?? false) && ($result = $result->resource ?? false) && ($result->id ?? false) ) { return $result; } return null; } public static function extractResourceFromBundle(object $bundle, string $resourceType): ?object { foreach ($bundle->entry ?? [] as $entry) { if (($entry->resource->resourceType ?? false) == $resourceType) { return $entry->resource; } } return null; } public static function extractParameter(object $parameters, string $parameterName, bool $extractValue = true): mixed { foreach ($parameters->parameter ?? [] as $parameter) { if ($parameter->name == $parameterName) { foreach ($parameter as $key => $value) { if ($extractValue) { if (str_starts_with($key, 'value')) { return $value; } } else { return $parameter; } } } } return null; } public static function extractErrors(?object $resource = null): ?array { $errors = []; foreach ($resource->issue ?? [] as $issue) { if ($issue->severity) { $errors[] = $issue->text ?? $issue->details->text ?? $issue->diagnostics ?? null; } } if (($errors = array_filter($errors))) { return self::niceArray($errors); } return null; } public static function doHttpMethod( ?object $settings, object $params, string $method = 'GET', bool $useFormat = false, bool $useAuth = false, bool $useThrow = true ): object|string|null { $method = 'http' . strtoupper($method); $params->noPort = true; if ($useFormat) { $params->param['_format'] = 'json'; } $params->headers['X-fhir-by-Version'] = '1.3'; $params->debug = med_cisz_constant::DEBUG_HTTP_REQUEST; $params->proxy = ($settings->proxyUse ?? false) ? [ 'host' => $settings->proxyServer ?? '', 'port' => $settings->proxyPort ?? 0, 'usr' => $settings->proxyUser ?? '', 'pwd' => $settings->proxyPassword ?? '' ] : []; if ($useAuth) { $ciszData = getSessionValue('cisz'); $params->headers['Authorization'] = 'Bearer ' . $ciszData->access_token; } $isHtml = isset($params->headers['Accept']) && str_contains($params->headers['Accept'], 'text/html'); if (empty(($response = $method($params)))) { throw new Exception( sprintf(med_cisz_constant::ERROR_NO_RESPONSE_TEXT, $params->url), med_cisz_constant::ERROR_NO_RESPONSE_CODE ); } switch ($response['status'][1] ?? null) { case '200': case '202': if ($isHtml) { return $response['body']; } return safe_json_decode($response['body']) ?? $response['body']; default: if ($useThrow) { throw new Exception( sprintf( med_cisz_constant::ERROR_RESPONSE_STATUS_TEXT, $response['status'][1], $params->url, $response['body'] ?? '' ), med_cisz_constant::ERROR_RESPONSE_STATUS_CODE ); } else { if (!($result = safe_json_decode($response['body']))) { $result = $response['body']; } return $result; } } } public static function doHttpMethodWithAuth(?object $settings, object $params, string $method = 'GET', bool $useThrow = true): object|string|null { return self::doHttpMethod($settings, $params, $method, false, true, $useThrow); } public static function getHtmDataValue(?array $data, array $names): ?string { $result = null; foreach ($names as $name) { $result = arrayFindObject($data ?? [], 'name', $name)->value ?? arrayFindObject($data ?? [], 'name', toLower($name))->value ?? null; if (isset($result)) { break; } } return $result; } public static function getHtmDataValues(?array $data, array $names,bool $isTable = false) { $result = []; $resultIn = []; if (!$isTable) { foreach ($data as $name) { if (in_array(($name->name), $names) || in_array( (toLower($name->name)), array_map('toLower', $names) )) { $result[] = $name->value; } } } else { $resultIn = []; foreach ($data as $item) { $rowindex = $item->rowIndex; if (!isset($resultIn[$rowindex])) { $resultIn[$rowindex] = array_fill(0, count($names), null); } } foreach ($data as $item) { $name = $item->name; $rowindex = $item->rowIndex; $value = $item->value; $position = array_search(toLower($name), array_map('toLower', $names)); if ($position !== false) { $resultIn[$rowindex][$position] = $value; } } $finalResult = array_filter(array_values($resultIn), function($values) { return count(array_filter($values, function($value) { return $value !== null; })) > 0; }); $result =$finalResult; } return $result; } public static function getTermsOfService(int $value, ?bool $codeIsNumeric = false): string { return match ($value) { 3 => $codeIsNumeric ? '4' : 'home health', 4 => $codeIsNumeric ? '3' : 'inpatient', 2 => $codeIsNumeric ? '2' : 'stac', default => $codeIsNumeric ? '1' : 'ambulatory', }; } public static function getEncounterTypeOfService(int $value): string { return match ($value) { 2 => 'paid', 3 => 'insurance', 4 => 'agreement', default => 'free', }; } public static function getEncounterTypeOfVisit(int $value): string { return match ($value) { 2 => 'repeated', 3 => 'activeVisit', default => 'primary', }; } public static function getEncounterHealthCareForm(array $data): string { $value = self::getHtmDataValue($data, ['HealthCareForms']); if ($value === 'плановая медицинская помощь') { $value = '1'; } elseif ($value === 'неотложная медицинская помощь') { $value = '2'; } elseif ($value === 'экстренная медицинская помощь') { $value = '3'; } return match ((int) $value) { 2 => 'urgent', 3 => 'emergency', default => 'planned', }; } public static function getEncounterTypeOfHealthCare(array $data): string { $value = self::getHtmDataValue($data, ['TypeOfHealthCare']); if ($value === 'специализированная медицинская помощь') { $value = '3'; } elseif ($value === 'медико-социальная помощь') { $value = '2'; } elseif ($value === 'высокотехнологичная медицинская помощь') { $value = '4'; } elseif ($value === 'паллиативная помощь') { $value = '5'; } return match ((int) $value) { 2 => 'medsocial', 3 => 'special', 4 => 'hightechnology', 5 => 'palliative', default => 'primary', }; } public static function getEncounterReasons(int $value): string { return match ((int) $value) { 1 => 'ther-and-diagn', 5 => 'chronic-observ', 6 => 'chronic-visit', 7 => 'other-visit', 8 => 'med-exam', default => 'consult', }; } public static function getMaritalStatus(int $value): ?string { return match ((int) $value) { 1 => 'married', 2 => 'unmarried', 3 => 'divorced', 4 => 'widowed', default => null, }; } public static function getMaritalStatusStructure(?int $maritalStatus): ?object { if (!$maritalStatus || !($maritalStatus = self::getMaritalStatus($maritalStatus))) { return null; } return self::getCodingStructure(med_cisz_constant::$valueSet['MaritalStatus'], $maritalStatus); } public static function getCodingStructure(string $system, $code, ?string $display = null): object { if ($display) { return (object) [ 'coding' => [ (object) [ 'system' => $system, 'code' => $code, 'display' => $display, ] ] ]; } else { return (object) [ 'coding' => [ (object) [ 'system' => $system, 'code' => $code ] ] ]; } } public static function getEducationStructure(string $system, $code, $display): object { return (object) [ 'url' => med_cisz_constant::$structureDefinitions['PatientStudyKind'], 'valueCodeableConcept' => [ 'coding' => [ (object) [ 'system' => $system, 'code' => $code, 'display' => $display, ] ] ] ]; } public static function getStudyPeriodStructure(string $url, string $startDate, string $finishDate): object { return (object) [ 'url' => $url, 'valuePeriod' => (object) [ 'start' => $startDate, 'end' => $finishDate ] ]; } public static function getValueCodeableConceptStructure(string $url, ?string $system, $code, ?object $codingStructure = null, ?string $display = null): object { return (object) [ 'url' => $url, 'valueCodeableConcept' => $codingStructure ?? self::getCodingStructure($system, $code, $display), ]; } public static function getExtensionValueStructure(string $url, $value, bool $usePeriod = true, $finishDate = null): object { $result = (object) [ 'url' => $url, ]; if ($value instanceof DateTime) { $date3000 = (new DateTime('3000-01-01'))->format('c'); if ($usePeriod) { $result->valuePeriod = (object) [ 'start' => $value->format('c'), 'end' => $finishDate && ($finishDate->format('c') !== $date3000) ? $finishDate->format('c') : null ]; } else { $result->valueDate = $value->format('Y-m-d'); } } elseif (is_string($value)) { $result->valueString = $value; } elseif (is_bool($value)) { $result->valueBoolean = $value; } elseif (is_int($value)) { $result->valueInteger = $value; } self::cleanEntity($result); return $result; } public static function getTypePeriodStructure (?string $startDate = null, ?string $finishDate = null): ?object { return ($startDate ?? false) ? (object) [ 'start' => $startDate, 'end' => $finishDate ] : null; } public static function niceArray(?array $array = null): array { return array_values(array_filter($array ?? [])); } public static function getDisabilityReason(object $disability): ?string { { if (($disability->causeDisability & 1) == 1) { return 'ch-general'; } elseif (($disability->causeDisability & 2) == 2) { return 'since-childhood'; } elseif (($disability->causeDisability & 4) == 4) { return 'disease-military'; } elseif (($disability->causeDisability & 8) == 8) { return 'professional'; } elseif (($disability->causeDisability & 16) == 16) { return 'war-injury'; } elseif (($disability->causeDisability & 32) == 32) { return 'disease-military'; } elseif (($disability->causeDisability & 64) == 64) { return 'disease-aes'; } elseif (($disability->causeDisability & 128) == 128) { return 'disease-military'; } } return null; } public static function generateCodeVerifier(int $length = 64): string { $str = ''; if ($length < 43 || $length > 128) { throw new Exception( med_cisz_constant::ERROR_CODE_VERIFIER_LENGTH_TEXT, med_cisz_constant::ERROR_CODE_VERIFIER_LENGTH_CODE ); } for ($i = 0; $i < $length; $i++) { $str .= med_cisz_constant::PKCE_VERIFIER_CHARSET[ random_int(0, strlen(med_cisz_constant::PKCE_VERIFIER_CHARSET) - 1) ]; } return $str; } public static function generateCodeChallenge(string $codeVerifier): string { $hash = hash('sha256', $codeVerifier, true); return base64url_encode($hash); } public static function generateCodeChallengePair(): array { $verifier = self::generateCodeVerifier(); return [ 'code_verifier' => $verifier, 'code_challenge' => self::generateCodeChallenge($verifier), 'code_challenge_method' => med_cisz_constant::CODE_CHALLENGE_METHOD_S256, ]; } public static function storeLinkUnique(PDO $db, string $entity, $value, ?int $entityId = null, int $type = 1): void { executeSql($db, <<<SQL
        INSERT INTO ciszlink (type, tablename, primarykey, uuid)
        VALUES (:type, :table_name, :primary_key, :uuid)
        ON CONFLICT (uuid) DO UPDATE
        SET type = EXCLUDED.type,
            tablename = EXCLUDED.tablename,
            primarykey = EXCLUDED.primarykey
SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ':uuid' => (string)$value, ]); } public static function upsertCiszLink(PDO $db, string $entity, $value, ?int $entityId = null, int $type = 1, int $state = 0, ?string $bundleUuid = null, ?string $resourceType = null):void { $ciszLinkCode = selectSqlValue($db, <<<SQL
        SELECT
            CiszLinkCode
        FROM
            CiszLink
        WHERE
            Type = :type
            AND TableName = :table_name
            AND PrimaryKey = :primary_key
            AND (BundleItemIdentifier = :resourceType OR BundleItemIdentifier IS NULL)
    SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ':resourceType' => $resourceType ]); if ($ciszLinkCode) { executeSql($db, <<<SQL
                UPDATE CiszLink SET
                    UUID = :uuid,
                    Status = :state,
                    BundleUuid = :bundleuuid,
                    BundleItemIdentifier = :resourceType
                WHERE
                    CiszLinkCode = :id
            SQL,
[ ':uuid' => $value, ':state' => $state, ':bundleuuid' => $bundleUuid, ':resourceType' => $resourceType, ':id' => $ciszLinkCode, ]); } else { executeSql($db, <<<SQL
                INSERT INTO CiszLink (Type, TableName, PrimaryKey, UUID, Status, BundleUuid, BundleItemIdentifier)
                VALUES(:type, :table_name, :primary_key, :uuid, :state, :bundleuuid, :resourceType)
            SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ':uuid' => $value, ':state' => $state, ':bundleuuid' => $bundleUuid, ':resourceType' => $resourceType, ]); } } public static function storeLinkUniqueByPk(PDO $db, string $entity, $value, ?int $entityId = null, int $type = 1, int $state = 0): void { $id = selectSqlValue($db, <<<SQL
            SELECT
                ciszLinkCode
            FROM
                ciszLink
            WHERE
                Type = :type
                AND TableName = :table_name
                AND PrimaryKey = :primary_key
        SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ]); if ($id) { if ($state) { executeSql($db, <<<SQL
                UPDATE CiszLink SET
                    UUID = :uuid,
                    Status = :state
                WHERE
                    CiszLinkCode = :id
            SQL,
[ ':uuid' => $value, 'state' => $state, ':id' => $id, ]); } else { executeSql($db, <<<SQL
                UPDATE ciszLink SET
                    UUID = :uuid
                WHERE
                    ciszLinkCode = :id
            SQL,
[ ':uuid' => $value, ':id' => $id, ]); } } else { if ($state) { executeSql($db, <<<SQL
                INSERT INTO ciszLink (Type, TableName, PrimaryKey, UUID, status)
                VALUES(:type, :table_name, :primary_key, :uuid, :state)
            SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ':uuid' => $value, ':state' => $state, ]); } else { executeSql($db, <<<SQL
                INSERT INTO ciszLink (Type, TableName, PrimaryKey, UUID)
                VALUES(:type, :table_name, :primary_key, :uuid)
            SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $entityId, ':uuid' => $value, ]); } } } public static function deleteLinkUnique(PDO $db, string $entity, $value): void { switch ($entity) { case 'MedInspect': case 'MedPatient': case 'MedVisitPatient': case 'MedPatientRegistration': case 'MedAmbulOperation': case 'MedVaccinationRecommendation': case 'MedMedication': case 'StomVisit': case 'MedTherapyStom': case 'MedDayHospital': case 'MedXRayStat': $data = selectSql($db, <<<SQL
                    SELECT CiszLinkCode "CiszLinkCode"
                    FROM CiszLink
                    WHERE TableName = :entity AND PrimaryKey = :id
                SQL,
[ ':entity' => $entity, ':id' => $value, ], PDO::FETCH_OBJ); foreach ($data as $row) { executeSql($db, <<<SQL
                        DELETE FROM CiszLink WHERE CiszLinkCode = :id
                    SQL,
[ ':id' => $row->CiszLinkCode, ]); } break; case 'MedLogStudyLab': case 'MedLogStudy': case 'MedLogStudyImage': case 'MedRadioIzotopStat': case 'MedRadioIzotopStom': $data = selectSql($db, <<<SQL
                    SELECT CiszLinkCode "CiszLinkCode"
                    FROM CiszLink
                    WHERE TableName LIKE :entity AND PrimaryKey = :id
                SQL,
[ ':entity' => $entity . '/%', ':id' => $value, ], PDO::FETCH_OBJ); foreach ($data as $row) { executeSql($db, <<<SQL
                        DELETE FROM CiszLink WHERE CiszLinkCode = :id
                    SQL,
[ ':id' => $row->CiszLinkCode, ]); } break; case 'MedProcedure': $data = selectSql($db, <<<SQL
                    SELECT CiszLinkCode "CiszLinkCode"
                    FROM CiszLink
                    WHERE TableName LIKE :entity AND PrimaryKey = :id
                SQL,
[ ':entity' => $entity . '%', ':id' => $value, ], PDO::FETCH_OBJ); foreach ($data as $row) { executeSql($db, <<<SQL
                        DELETE FROM CiszLink WHERE CiszLinkCode = :id
                    SQL,
[ ':id' => $row->CiszLinkCode, ]); } break; case 'DicomDevice': $data = selectSql($db, <<<SQL
                    SELECT CiszLinkCode "CiszLinkCode"
                    FROM CiszLink
                    WHERE TableName = :entity AND PrimaryKey = :id
                SQL,
[ ':entity' => $entity, ':id' => $value, ], PDO::FETCH_OBJ); foreach ($data as $row) { executeSql($db, <<<SQL
                        DELETE FROM CiszLink WHERE CiszLinkCode = :id
                    SQL,
[ ':id' => $row->CiszLinkCode, ]); } break; } } public static function getLinkEntityUUID(PDO $db, int $type, string $entity, ?int $id = null): ?string { return selectSqlValue($db, <<<SQL
            SELECT
                UUID
            FROM
                ciszLink
            WHERE
                Type = :type
                AND TableName = :table_name
                AND coalesce(PrimaryKey, 0) = coalesce(:primary_key, 0)
            ORDER BY ciszLinkCode
        SQL,
[ ':type' => $type, ':table_name' => $entity, ':primary_key' => $id, ]); } public static function getLinkEntityStudyUUID(PDO $db, int $type, string $entity, ?int $id = null): ?string { return selectSqlValue($db, <<<SQL
            SELECT
                UUID
            FROM
                ciszLink
            WHERE
                Type = :type
                AND TableName LIKE :table_name_pattern
                AND coalesce(PrimaryKey, 0) = coalesce(:primary_key, 0)
                AND UUID not like '(%'
            ORDER BY ciszLinkCode desc
        SQL,
[ ':type' => $type, ':table_name_pattern' => $entity . '/%', ':primary_key' => $id, ]); } public static function getRelationship(int $degree, int $sex): string { return match ($degree) { 1 => $sex == 0 ? 'father' : 'mother', 2 => 'adopter', 3 => 'caregiver', 4 => $sex == 0 ? 'grandpa' : 'grandma', 6 => $sex == 0 ? 'son' : 'daughter', 10 => 'parent', 11 => 'trustee', 12 => $sex == 0 ? 'husband' : 'wife', default => 'other', }; } public static function getRelationshipForFamilyHistory(int $degree, int $sex): string { return match ($degree) { 0 => 'Distant', 1 => $sex == 0 ? 'Father' : 'Mother', 4 => $sex == 0 ? 'Grandfather' : 'Grandmother', 5 => $sex == 0 ? 'Brother' : 'Sister', 9 => $sex == 0 ? 'Grandson' : 'Granddaughter', 13 => $sex == 0 ? 'Great-grandfather' : 'Great-grandmother', default => 'Unknown', }; } public static function getClientIp(): ?string { $keys = [ 'X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ]; foreach ($keys as $key) { if (isset($_SERVER[$key])) { return $_SERVER[$key]; } } return $_SERVER['REMOTE_ADDR'] ?? null; } public static function whoIsThePatient(PDO $db, int $code) : string{ $code = selectSqlValue($db, <<<SQL
            SELECT
               code AS "code"
            FROM
               ciszresourcevalue
            WHERE
               SourceCode = :SourceCode
        SQL,
[ ':SourceCode' => $code ]); if (!$code) { throw new Exception( med_cisz_constant:: ERROR_CONFIGURATION_OF_THE_CISZ_DIRECTORY, med_cisz_constant::ERROR_CONFIGURATION_OF_THE_CISZ_DIRECTORY_CODE ); } return $code; } public static function normalizeStrictBool(mixed $v): ?bool { if (is_bool($v)) { return $v; } if (is_int($v)) { return $v === 1 ? true : ($v === 0 ? false : null); } if (is_string($v)) { $s = strtolower(trim($v)); if ($s === 'true' || $s === '1' || $s === 'yes' || $s === 'on') { return true; } if ($s === 'false' || $s === '0' || $s === 'no' || $s === 'off') { return false; } return null; } return null; } public static function loadHtmlData(PDO $db, int $visitDocumentId,bool $isStat = false): mixed { if ($isStat) { return selectSqlValue($db, <<<SQL
            SELECT
                hp.HTML
            FROM
            MedStatCardDoc MSCD
            JOIN htmPage hp ON hp.htmPageCode = MSCD.htmpagecode
            WHERE
            MedStatCardDocCode = :visitDocumentId
            SQL,
[ ':visitDocumentId' => $visitDocumentId ] ); } else { return selectSqlValue($db, <<<SQL
            SELECT
                hp.HTML
            FROM
            MedvisitDocument mvd
            JOIN htmPage hp ON hp.htmPageCode = mvd.htmpagecode
            WHERE
            MedvisitDocumentCode = :visitDocumentId
            SQL,
[ ':visitDocumentId' => $visitDocumentId ] ); } } private static function createDocument(object $visit, string $html, string $elementName, object $patient, ?string $title = null, ?object $organization = null): mixed { $doc = new DOMDocument('1.0', 'utf-8'); $search = array( '<cmp', '</cmp>' ); $replace = array( '<div cmp ', '</div>' ); $html = str_replace($search, $replace, $html); $html = mb_encode_numericentity( htmlspecialchars_decode( htmlentities($html, ENT_NOQUOTES, 'UTF-8', false) , ENT_NOQUOTES ), [0x80, 0x10FFFF, 0, ~0], 'UTF-8' ); libxml_use_internal_errors(true); $doc->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_NOENT); if ($elementName) { $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@cfg]'); if (!$all || !$all->length) { return null; } $element = null; foreach ($all as $el) { if (!$el) { continue; } if (!$el->hasAttributes()) { continue; } $cfg = safe_json_decode($el->getAttribute('cfg')); if (!($cfg->n ?? false)) { continue; } if ($cfg->n == $elementName) { $element = $el; continue; } if ($element) { $value = self::getHtmDataValue($visit->htmData, [$cfg->n]); switch ($el->getAttribute('elid')) { case 'chk': $value = self::normalizeStrictBool($value) ? 'Да' : 'Нет'; break; } if ($value) { $el->nodeValue = $value; } } } if (!$element) { return null; } } $pdf = new TCPDF('L'); $pdf->SetCompression(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetMargins(5, 5, 5); $pdf->SetFont('arial', '', 8); $pdf->AddPage(); $pdf->setHtmlVSpace([ 'div' => [ 0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0] ] ]); $htmlElement = $doc->saveHTML($element ?? null); $htmlElement = preg_replace( '/<table\b/i', '<table border="1" cellpadding="4" cellspacing="0"', $htmlElement, 1 ); $pdf->writeHTML($htmlElement); $data = $pdf->Output('', 'S'); return self::buildDocumentBody( $visit->htmData[0]?->visitDocumentId ?? $visit->id, 'med-doc-other', $patient->link, $data, $title, $organization ); } public static function findObservation(string $codeCodingCode, ?array $observations): ?object { foreach ($observations ?? [] as $item) { if ($item->resource?->code?->coding[0]?->code == $codeCodingCode) { return $item; } } return null; } public static function changeDateMonths(string $dateString, int $months): string { $format = str_contains($dateString, ".") ? 'd.m.Y' : 'Y-m-d'; $dateString = substr($dateString, 0, 10); $date = DateTime::createFromFormat($format, $dateString); $date->modify("{$months} months"); return $date->format('Y-m-d'); } public static function historyUpdateId(PDO_ANY $db, int $historyId, string $filedName, string $recordId): void { executeSql( $db, "UPDATE ciszHistory SET $filedName = ? WHERE ciszHistoryCode = ?", [$recordId, $historyId] ); } public function extractExtensionData($extensions) { $result = null; if (!is_array($extensions)) { return $result; } foreach ($extensions as $extension) { if (!isset($extension->url)) { continue; } $urlParts = explode('/', $extension->url); $extensionName = end($urlParts); $extensionName = lcfirst($extensionName); if (isset($extension->valueCodeableConcept)) { $result[$extensionName . 'Code'] = $extension->valueCodeableConcept->coding[0]->code; $result[$extensionName . 'Display'] = $extension->valueCodeableConcept->coding[0]->display; } elseif (isset($extension->extension)) { foreach ($extension->extension as $ext) { $urlPartExt = explode('/', $ext->url); $extnName = end($urlPartExt); $extnName = lcfirst($extnName); if (isset($ext->valueCodeableConcept)) { $result[$extnName . 'Code'] = $ext->valueCodeableConcept->coding[0]->code; $result[$extnName . 'Display'] = $ext->valueCodeableConcept->coding[0]->display; } elseif (isset($ext->valueQuantity)) { $result[$extnName . 'Code'] = $ext->valueQuantity->code; $result[$extnName . 'Display'] = $ext->valueQuantity->unit; $result[$extnName . 'Value'] = $ext->valueQuantity->value; } elseif (isset($ext->valueDate)) { $result[$extnName] = $ext->valueDate; } } } elseif (isset($extension->valueBoolean)) { $result[$extensionName] = $extension->valueBoolean; } } return $result; } public function getPatientUuidFromCisz(?string $identifier = null, ?string $type = null): ?string { if ($type === 'personalNumber') { $param = [ 'identifier' => $identifier ]; } elseif ($type === 'passportNumber') { $param = [ 'identifier' => $identifier ]; } elseif ($type === 'docNumber') { $param = [ 'identifier' => $identifier, 'assigner' => $this->organization->link->reference ]; } elseif ($type === 'unknown') { throw new Exception("Не указаны данные пациента для поиска"); } $patientResource = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Patient', 'param' => $param ]); if (($patientResource = self::extractResourceFromBundle($patientResource, 'Patient'))) { $ciszData = getSessionValue('cisz') ?? (object) []; $ciszData->{$patientResource->id} = $patientResource; setSessionValue('cisz', $ciszData); return $patientResource->id; } return null; } public function checkPatientUuid(?int $patientCode = null): ?string { $uuid = selectSqlValue($this->db, "SELECT Uuid FROM CiszLink WHERE PrimaryKey = ? AND TableName = ? AND Type = ?", [$patientCode, 'MedPatient', 1]); if (!$uuid) { if ($this->patient->raw->anonymous || $this->patient->raw->unknown) { $uuid = self::getPatientUuidFromCisz($this->patient->raw->docNumber, 'docNumber'); } else if ($this->patient->raw->personalNumber) { $uuid = self::getPatientUuidFromCisz($this->patient->raw->personalNumber, 'personalNumber'); } else if ($this->patient->raw->passportNumber) { $uuid = self::getPatientUuidFromCisz($this->patient->raw->passportNumber, 'passportNumber'); } else if ($this->patient->raw->docNumber) { $uuid = self::getPatientUuidFromCisz($this->patient->raw->docNumber, 'docNumber'); } else { throw new Exception("Не указаны данные пациента для поиска"); } $ciszLinkId = selectSqlValue($this->db, <<<SQL
                SELECT CiszLinkCode
                FROM CiszLink 
                WHERE TableName = :tableName AND Uuid = :Uuid
            SQL,
[ 'tableName' => 'MedPatient', 'Uuid' => $uuid ]); if ($ciszLinkId) { return (string)$uuid; } if ($uuid) { self::upsertContract($uuid); executeSql($this->db, 'UPDATE CiszLink SET Uuid = ? WHERE PrimaryKey = ? AND TableName = ? AND Type = ?', [$uuid, $patientCode, 'MedPatient', 1]); $historyId = selectSqlValue($this->db, "SELECT CiszHistoryCode FROM CiszHistory WHERE MedPatientCode = ? AND RecordId = ?", [$patientCode, 'Patient/inProgress']); $uuid = str_starts_with($uuid, 'Patient/') ? $uuid : "Patient/$uuid"; if ($historyId) { self::historyUpdateId($this->db, $historyId, 'RecordId', $uuid); } } else { if ($this->entity === 'MedPatient') { return $uuid; } else { $patientCisz = selectSqlValue($this->db, "SELECT PrimaryKey FROM CiszLink WHERE PrimaryKey = ? AND TableName = ? AND Type = ?", [$patientCode, 'MedPatient', 1]); if ($patientCisz) { throw new Exception( med_cisz_constant::$globalError['patientStillInProgress']['text'], med_cisz_constant::$globalError['patientStillInProgress']['code'] ); } else { throw new Exception( med_cisz_constant::$globalError['notRegisterPatient']['text'], med_cisz_constant::$globalError['notRegisterPatient']['code'] ); } } } } return $uuid; } private function upsertContract($uuid) { $db = $this->db ?: $this->db = connectDb(); $registrationData = selectSql($db, <<<SQL
                        SELECT 
                            MPR.MedPatientRegistrationCode AS "id",
                            MPR.MedPatientCode AS "patientId",
                            MPR.StartDate AS "startDate",
                            MPR.FinishDate AS "finishDate",
                            MPR.CiszResourceValueCodeType AS "typeId",
                            CRVT.Code AS "typeCode",
                            MPR.CiszResourceValueCodeSubType AS "subTypeId",
                            CRVS.Code AS "subTypeCode"
                        FROM MedPatientRegistration MPR
                            LEFT JOIN CiszResourceValue CRVT ON CRVT.CiszResourceValueCode = MPR.CiszResourceValueCodeType
                            LEFT JOIN CiszResourceValue CRVS ON CRVS.CiszResourceValueCode = MPR.CiszResourceValueCodeSubType
                        WHERE MPR.MedPatientCode = :patientId
                    SQL,
[ ':patientId' => $this->patient->raw->patientId ], PDO::FETCH_OBJ); $contract = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/Patient/' . $uuid . '/Contract?_profile=https://fhir.by/StructureDefinition/PlaceOfCare&patient=' . $uuid . '&_sort=-_lastUpdated', ]); $registrationIndex = []; foreach ($registrationData as $registration) { $typeCode = $registration->typeCode ?? 'residence'; $subTypeCode = $registration->subTypeCode; $key = $typeCode . '_' . $subTypeCode; $registrationIndex[$key] = $registration; } $currentOrgId = str_replace('Organization/', '', $this->organization->link->reference); foreach ($contract->entry as $entry) { if (!isset($entry->fullUrl)) { continue; } if (str_replace('Organization/', '', $entry->resource->author->reference) !== $currentOrgId) { continue; } $subTypeCode = $entry->resource->subType[0]->coding[0]->code ?? null; $typeCode = $entry->resource->type->coding[0]->code ?? null; if (!$subTypeCode || !$typeCode) { continue; } $expectedTypeCode = ($typeCode === 'temporary') ? 'temporary' : 'residence'; $searchKey = $expectedTypeCode . '_' . $subTypeCode; if (isset($registrationIndex[$searchKey])) { self::storeLinkUniqueByPk( $this->db, 'MedPatientRegistration', $entry->resource->id, $registrationIndex[$searchKey]->id, 1, 0 ); } } } public static function getcCiszValue(PDO $db, int $code) : string{ $code = selectSqlValue($db, <<<SQL
            SELECT
               code AS "code"
            FROM
               CiszResourceValue
            WHERE
               CiszResourceValueCode = :SourceCode
        SQL,
[ ':SourceCode' => $code ]); return $code; } public static function getcCiszRelatedValue(PDO $db, int $code, int $source) : string{ $value = selectSqlValue($db, <<<SQL
            SELECT
               CRV.code AS "code"
            FROM
               CiszResource CR
            LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceCode = CR.CiszResourceCode 
            WHERE
                CR.Source = :Source AND
               CRV.SourceCode = :SourceCode
               
        SQL,
[ ':SourceCode' => $code, ':Source' => $source ]); return $value; } public static function getValueCodeableConceptStructureText(string $url, ?string $system, $code, $text, ?object $codingStructure = null): object { return (object) [ 'url' => $url, 'valueCodeableConcept' => $codingStructure ?? self::getCodingStructureText($system, $code, $text), ]; } public static function getCodingStructureText(string $system, $code, $text): object { return (object) [ 'coding' => [ (object) [ 'system' => $system, 'code' => $code ] ], 'text' => $text ]; } private function getFullConditionResource($diagnosisId, $diagnosisExtraId, $visitDate, $patientId,?string $diagNote = 'Примечание', string $typeDiag = 'Clinical'): object|null { if (!$diagnosisId || !$diagnosisExtraId || !$visitDate || !$patientId) { return null; } $id = generateUUID(null, null, true); $sqlDiagnosisData = selectSql($this->db, <<<SQL
                    SELECT
                        CDEA2.AnatomicCodes                  "bodyParts",
                        CDE.ciszresourcevaluecodecancerstage "cancerStage",
                        CDE.Note                             "note",
                        CDE.ciszresourcevaluecodediagkind    "kindOfDiagnosis",
                        CDE.ClinicalDiagnosis                "clinicalDiagnosis"
                    FROM CiszDiagnosisExtra CDE
                    LEFT JOIN (
                    SELECT
                        CDEA.CiszDiagnosisExtraCode,
                        string_agg(
                            CDEA.CiszResourceValueCodeAnatomic, '; '
                            ORDER BY CDEA.CiszDiagnosisExtraCode
                        ) AS AnatomicCodes
                    FROM CiszDiagnosisExtraAnatomic CDEA
                    GROUP BY CDEA.CiszDiagnosisExtraCode
                ) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
                    WHERE CDE.CiszDiagnosisExtraCode = :diagnosisExtraId
                SQL,
[ 'diagnosisExtraId' => $diagnosisExtraId ], PDO::FETCH_OBJ); $diagnosisData = new stdClass(); if (!empty($sqlDiagnosisData)) { foreach ($sqlDiagnosisData as $record) { foreach ($record as $key => $value) { $diagnosisData->$key = $value; } } } $clinicalStatus = 'active'; $bodySite = []; $bodyParts = $diagnosisData->bodyParts; $items = is_string($bodyParts) ? explode(';', $bodyParts) : (array)$bodyParts; if (!empty($items)) { foreach ($items as $item) { $bodySite[] = self::getCodingStructure(med_cisz_constant::$valueSet['AnatomicalTerminology'], self::getcCiszValue($this->db, trim($item))); } } $kindOfDiagnosis = $diagnosisData->kindOfDiagnosis ? self::getcCiszValue($this->db, $diagnosisData->kindOfDiagnosis) : null; $sqlFirstDiagnosisDate = selectSql($this->db, <<<SQL
            SELECT
                MIN(MV.VisitDate) AS "firstVisitDate"
            FROM MedVisitPatientEx MVPE
                LEFT JOIN MedVisitPatient MVP ON MVP.MedVisitPatientCode = MVPE.MedVisitPatientCode
                LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
            WHERE MVP.MedPatientCode = :patientId
                AND MVP.MedDiagnosisCode = :diagnosisId
        SQL,
[ 'patientId' => $patientId, 'diagnosisId' => $diagnosisId ], PDO::FETCH_OBJ); $firstDiagnosisDate = reset($sqlFirstDiagnosisDate); $extensions = [ self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['KindOfDiagnosis'], med_cisz_constant::$valueSet['KindOfDiagnosisCode'], $kindOfDiagnosis ?? $typeDiag ), (object) [ 'url' => med_cisz_constant::$structureDefinitions['AssertedDate'], 'valueDateTime' => self::getDateTime($visitDate) ] ]; $sqlDiagnosisData = selectSql($this->db, <<<SQL
                    SELECT
                        MD.Cipher1 "diagnosisCipher"
                    FROM MedDiagnosis MD
                    WHERE MD.MedDiagnosisCode = :diagnosisId
                SQL,
[ 'diagnosisId' => $diagnosisId ], PDO::FETCH_OBJ); $diagnosisCipher = $sqlDiagnosisData[0]->diagnosisCipher ?? null; $natureOfDisease = selectSqlValue($this->db, <<<SQL
                SELECT D.DiagnosisType AS natureOfDisease
                FROM MedDiagnosis D 
                WHERE D.MedDiagnosisCode = :id
                SQL,
[ 'id' => $diagnosisId ]); $extensions[] = (object) [ 'url' => med_cisz_constant::$structureDefinitions['FirstTimeRevealed'], 'valueBoolean' => true ]; if ($natureOfDisease) { switch ($natureOfDisease) { case 1: case 3: $sicknessKind = 'chronic'; break; case 2: default: $sicknessKind = 'acute'; break; } $extensions[] = self::getValueCodeableConceptStructure( med_cisz_constant::$structureDefinitions['CourseOfDisease'], med_cisz_constant::$valueSet['CourseOfDisease'], $sicknessKind ); } $note = $diagnosisData->note ?? $diagNote; $code = $diagnosisData->clinicalDiagnosis ? self::getCodingStructureText(med_cisz_constant::$valueSet['InternClassificDiseases10'], $diagnosisCipher, $diagnosisData->clinicalDiagnosis) : self::getCodingStructure(med_cisz_constant::$valueSet['InternClassificDiseases10'], $diagnosisCipher); $diagnosis = (object) [ 'fullUrl' => 'Condition/' . $id, 'resource' => (object) [ 'resourceType' => 'Condition', 'id' => $id, 'meta' => (object) [ 'profile' => [med_cisz_constant::$structureDefinitions['FinalDiagnosis']], ], 'extension' => $extensions, 'clinicalStatus' => self::getCodingStructure(med_cisz_constant::$structureDefinitions['condition-clinical'], $clinicalStatus), 'code' => $code, 'bodySite' => !empty($bodySite) ? $bodySite : null, 'subject' => $this->patient->link, 'onsetDateTime' => self::getDateTime($visitDate), 'recordedDate' => self::getDateTime($firstDiagnosisDate->firstVisitDate ?? $visitDate), 'participant' => $this->practitioner->actor, 'stage' => $diagnosisData->cancerStage ? [ (object) [ 'summary' => self::getCodingStructure( med_cisz_constant::$valueSet['StagesOfMalignTum'], self::getcCiszValue($this->db, $diagnosisData->cancerStage) ) ] ] : null, 'note' => $note ? [ (object) [ 'text' => $note ] ] : null ] ]; self::cleanEntity($diagnosis); return $diagnosis; } public function getFullConditionLink(?object $diag = null): object| null { $result = null; $diagnosisCode = $diag->diagCode; $diagDate = $diag->diagDate; $diagExtraId = $diag->diagExtraId; if (!$diagnosisCode || !$diagExtraId || !$diagDate || !$diag->patientId) { return null; } $sqlDiagnosisData = selectSql($this->db, <<<SQL
                    SELECT
                        CDEA2.AnatomicCodes                  "bodyParts",
                        CRV1.Code                            "cancerStage",
                        CDE.Note                             "note",
                        CRV2.Code                            "kindOfDiagnosis",
                        CDE.ClinicalDiagnosis                "clinicalDiagnosis"
                    FROM CiszDiagnosisExtra CDE
                    LEFT JOIN CiszResourceValue CRV1 ON CRV1.CiszResourceValueCode = CDE.ciszresourcevaluecodecancerstage
                    LEFT JOIN CiszResourceValue CRV2 ON CRV2.CiszResourceValueCode = CDE.ciszresourcevaluecodediagkind
                    LEFT JOIN (
                    SELECT
                        CDEA.CiszDiagnosisExtraCode,
                        string_agg(
                            CRV3.Code, '; '
                            ORDER BY CDEA.CiszDiagnosisExtraCode
                        ) AS AnatomicCodes
                    FROM CiszDiagnosisExtraAnatomic CDEA
                    LEFT JOIN CiszResourceValue CRV3 ON CRV3.CiszResourceValueCode = CDEA.CiszResourceValueCodeAnatomic
                    GROUP BY CDEA.CiszDiagnosisExtraCode
                ) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
                    WHERE CDE.CiszDiagnosisExtraCode = :diagnosisExtraId
                SQL,
[ 'diagnosisExtraId' => $diagExtraId ], PDO::FETCH_OBJ); $linkCondition = ''; if (isset($this->conditions)) { foreach ($this->conditions as $condition) { if (!isset($condition->resource)) { continue; } $resource = $condition->resource; $cipherDiagnosis = $resource->code->coding[0]->code ?? null; if ($cipherDiagnosis !== $diagnosisCode) { continue; } $clinicalDiagnosis = $resource->code->text ?? ''; if ($clinicalDiagnosis !== $sqlDiagnosisData[0]->clinicalDiagnosis) { continue; } $existingBodyParts = []; if (!empty($resource->bodySite)) { foreach ($resource->bodySite as $bs) { $existingBodyParts[] = $bs->coding[0]->code ?? null; } } $sqlBodyParts = $sqlDiagnosisData[0]->bodyParts ?? null; $targetBodyParts = $sqlBodyParts ? explode(';', $sqlBodyParts) : []; $existingBodyParts = array_map( fn($v) => trim((string)$v), array_values(array_filter($existingBodyParts)) ); $targetBodyParts = array_map( fn($v) => trim((string)$v), array_values(array_filter($targetBodyParts)) ); sort($existingBodyParts); sort($targetBodyParts); if ($existingBodyParts != $targetBodyParts) { continue; } $existingStage = $resource->stage[0]->summary->coding[0]->code ?? null; $targetStage = $sqlDiagnosisData[0]->cancerStage ?? null; if ($existingStage != $targetStage) { continue; } $existingNote = $resource->note[0]->text ?? null; $targetNote = $sqlDiagnosisData[0]->note ?? null; if (trim((string)$existingNote) !== trim((string)$targetNote)) { continue; } $existingKind = null; if (!empty($resource->extension)) { foreach ($resource->extension as $ext) { if (($ext->url ?? null) === med_cisz_constant::$structureDefinitions['KindOfDiagnosis']) { $existingKind = $ext->valueCodeableConcept->coding[0]->code ?? null; break; } } } $targetKind = $sqlDiagnosisData[0]->kindOfDiagnosis ?? null; if ($existingKind != $targetKind) { continue; } $linkCondition = $condition; break; } } if (!$linkCondition) { $this->conditions[] = $linkCondition = self::getFullConditionResource( $diag->diagId, $diagExtraId, $diagDate, $diag->patientId, $diag->diagNote ?? null ); } $useCodingCode = 'mainDiagnosis'; $result = (object) [ 'reference' => $linkCondition->fullUrl ]; return $result; } private function getDate($date) { return $date ? (new DateTime($date))->format('d.m.Y') : null; } private function getDateTime($date) { return $date ? (new DateTime($date))->format(DateTime::ATOM) : null; } private function checkStudyType() { $study =!empty($this->funcDiagnostic) ? $this->funcDiagnostic[array_key_first($this->funcDiagnostic)] : null; $type = selectSqlValue( $this->db, <<<SQL
                SELECT
                    CR.Identifier "type"
                FROM CiszStudyList CSL
                LEFT JOIN CiszStudy CS ON CS.CiszStudyCode = CSL.CiszStudyCode
                LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = CS.CiszResourceValueCode
                LEFT JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
                 WHERE CSL.MedStudyCode = :medStudyCode
            SQL,
[ ':medStudyCode' => $study->medStudyId, ] ); return $type; } private function getCiszCodeByCiszResourceValueCode($ciszResourceValueCode) { $sql = <<<"SQL"
SELECT
    CRV.code "code"
FROM CiszResourceValue CRV
WHERE ciszResourceValueCode = :ciszResourceValueCode
SQL;
return selectSqlValue($this->db, $sql, [':ciszResourceValueCode' => $ciszResourceValueCode]); } private function getDisplayByCode($code, $system) { $display = selectSqlValue( $this->db, <<<SQL
                SELECT
                    CRV.Name "display"
                FROM CiszResourceValue CRV
                LEFT JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
                 WHERE CRV.Code = :code
                    AND CR.Identifier = :system
            SQL,
[ ':code' => $code, ':system' => $system ] ); return $display; } public function getSourceCodeByCiszCode(string $ciszCode, string $ciszDictionary):?int { $sql = <<<"SQL"
SELECT
    CRV.sourceCode     
FROM CiszResource CR 
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceCode = CR.CiszResourceCode
WHERE CR.identifier = :ciszDictionary AND CRV.code = :ciszCode
SQL;
return selectSqlValue($this->db, $sql, [ ':ciszDictionary' => $ciszDictionary, ':ciszCode' => $ciszCode ]); } private function addDiagnosisFromCisz(object $resource, int $doctorCode, $patientCode) { sleep(5); $diagnosis = self::doHttpMethodWithAuth($this->settings,(object)[ 'url' => $this->settings->url . 'fhir/Patient/' . $this->patient->profile->resource->id . '/Condition?_profile='.med_cisz_constant::$structureDefinitions['FinalDiagnosis'] . '&clinical-status=active&code=' . $resource->code->coding[0]->code . '&onset-date=' . $resource->onsetDateTime ]); if ($diagnosis->total > 0) { $resource = $diagnosis->entry[1]->resource; } foreach ($resource->extension as $extension) { if ($extension->url === med_cisz_constant::$structureDefinitions['KindOfDiagnosis']) { $kind = $extension->valueCodeableConcept->coding[0]->code; $kindId = selectSqlValue($this->db, <<<SQL
                SELECT
                    CRV.CiszResourceValueCode "code"
                FROM CiszResourceValue CRV
                LEFT JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode
                 WHERE CRV.Code = :code
                    AND CR.Identifier = :system
            SQL,
[ ':code' => $kind, ':system' => 'KindOfDiagnosisCode' ] ); break; } } executeSql($this->db, <<<SQL
        INSERT INTO CiszPatientDignosis (AssertedDate, DiagnosisCipher, MedDoctorCode, MedPatientCode, Uuid, CiszResourceValueCodeDiagKind)
        VALUES (:assertedDate, :diagnosisCipher, :medDoctorCode, :medPatientCode, :uuid, :ciszResourceValueCodeDiagKind)
SQL,
[ ':assertedDate' => $resource->onsetDateTime, ':diagnosisCipher' => $resource->code->coding[0]->code, ':medDoctorCode' => $doctorCode, ':medPatientCode' => $patientCode, ':uuid' => $resource->id, ':ciszResourceValueCodeDiagKind' => $kindId, ]); } private function findBundleArrayResource(string $path, $define) : ?object { if (!$this->$path) { return null; } foreach ($this->$path as $res){ if ($res->resource->meta->profile[0] === $define) { return $res; } } return null; } private function fhirMerge(object $a, object $b): object { $result = clone $a; foreach ($b as $key => $value){ if ($value === null){ continue; } if ($key === 'meta') { $result->$key = $value; continue; } if (!property_exists($result, $key)){ $result->$key = $value; continue; } $existKey = $result->$key; if (is_object($existKey) && is_object($value)){ $result->$key = $this->fhirMerge($existKey, $value); continue; } if (is_array($existKey) && is_array($value)){ $result->$key = $this->fhirMergeArr($key, $existKey, $value, 'Patient'); continue; } $result->$key = $value; } return $result; } private function fhirMergeArr(string $key, array $a, array $b, string $profile): array { if ($profile === 'Patient' && $key === 'identifier') { return $this->mergePatientIdentifiers($a, $b); } $mergeProfile = med_cisz_constant::$propertyListFHIR[$profile] ?? null; if ($mergeProfile === null) { return $b; } else { if ($key === 'extension') { return array_values($this->mergeExtensions($a, $b, med_cisz_constant::$propertyListFHIR[$profile][$key] ?? null)); } $mergeProfileKeys = $mergeProfile[$key] ?? null; if ($mergeProfileKeys === null) { return $b; } else { $arrIn = []; foreach ($a as $item) { $arrIn[$this->identifyArrRes($item, $mergeProfileKeys)] = $item; } foreach ($b as $item) { $arrIn[$this->identifyArrRes($item, $mergeProfileKeys)] = $item; } return array_values($arrIn); } } } private function mergeExtensions(array &$main, array &$additional, ?array $keys) : array { if ($keys === null) { return $additional; } $findExt = function (array $exts, string $url): ?object { foreach ($exts as $e) { if (is_object($e) && ($e->url ?? null) === $url) return $e; } return null; }; $findAllExt = function (array $exts, string $url): array { $out = []; foreach ($exts as $e) { if (is_object($e) && ($e->url ?? null) === $url) $out[] = $e; } return $out; }; $getNestedPeriod = function (object $ext, string $url) use ($findExt): ?object { $inner = $findExt($ext->extension ?? [], $url); return $inner->valuePeriod ?? null; }; $periodsOverlap = function (?object $p1, ?object $p2): bool { if ($p1 === null || $p2 === null) return false; $s1 = $p1->start ?? null; $e1 = $p1->end ?? null; $s2 = $p2->start ?? null; $e2 = $p2->end ?? null; if ($e1 !== null && $s2 !== null && $e1 < $s2) return false; if ($e2 !== null && $s1 !== null && $e2 < $s1) return false; return true; }; $getMainJobPlace = function (object $job) use ($findExt): ?bool { $inner = $findExt($job->extension ?? [], med_cisz_constant::$structureDefinitions['MainJobPlace']); return $inner->valueBoolean ?? null; }; $setMainJobPlace = function (object $job, bool $v) use ($findExt): void { foreach ($job->extension ?? [] as $inner) { if (($inner->url ?? null) === med_cisz_constant::$structureDefinitions['MainJobPlace']) { $inner->valueBoolean = $v; return; } } }; $getPrefCode = function (object $pref): ?string { $coding = $pref->valueCodeableConcept->coding ?? []; return $coding[0]->code ?? null; }; $getConsentStart = function (object $consent) use ($findExt): ?string { $p = $findExt($consent->extension ?? [], med_cisz_constant::$structureDefinitions['ConsentPersonalData']); return $p->valuePeriod->start ?? null; }; $result = []; foreach ([med_cisz_constant::$structureDefinitions['IncapacityExtension'], med_cisz_constant::$structureDefinitions['DisabilityGroup'], med_cisz_constant::$structureDefinitions['DisabilityReason'], med_cisz_constant::$structureDefinitions['PatientBirthDateTime']] as $url) { $a = $findExt($main, $url); $b = $findExt($additional, $url); $merged = $b ?? $a; if ($merged !== null) $result[] = $merged; } $jobsMain = $findAllExt($main, med_cisz_constant::$structureDefinitions['PatientJob']); $jobsAdd = $findAllExt($additional, med_cisz_constant::$structureDefinitions['PatientJob']); $usedMain = []; foreach ($jobsAdd as $jobAdd) { $pAdd = $getNestedPeriod($jobAdd, med_cisz_constant::$structureDefinitions['TermOFWork']); $matched = false; foreach ($jobsMain as $i => $jobMain) { if (isset($usedMain[$i])) continue; $pMain = $getNestedPeriod($jobMain, med_cisz_constant::$structureDefinitions['TermOFWork']); if ($periodsOverlap($pMain, $pAdd)) { $result[] = $jobAdd; $usedMain[$i] = true; $matched = true; break; } } if (!$matched) $result[] = $jobAdd; } foreach ($jobsMain as $i => $jobMain) { if (!isset($usedMain[$i])) $result[] = $jobMain; } $trueIdx = []; foreach ($result as $i => $e) { if (($e->url ?? null) === med_cisz_constant::$structureDefinitions['PatientJob'] && $getMainJobPlace($e) === true) { $trueIdx[] = $i; } } if (count($trueIdx) > 1) { $keep = end($trueIdx); foreach ($trueIdx as $i) { if ($i !== $keep) $setMainJobPlace($result[$i], false); } } $stMain = $findAllExt($main, med_cisz_constant::$structureDefinitions['PatientStudy']); $stAdd = $findAllExt($additional, med_cisz_constant::$structureDefinitions['PatientStudy']); $usedMain = []; foreach ($stAdd as $sAdd) { $pAdd = $getNestedPeriod($sAdd, med_cisz_constant::$structureDefinitions['PatientTermOfStudy']); $matched = false; foreach ($stMain as $i => $sMain) { if (isset($usedMain[$i])) continue; $pMain = $getNestedPeriod($sMain, med_cisz_constant::$structureDefinitions['PatientTermOfStudy']); if ($periodsOverlap($pMain, $pAdd)) { $result[] = $sAdd; $usedMain[$i] = true; $matched = true; break; } } if (!$matched) $result[] = $sAdd; } foreach ($stMain as $i => $sMain) { if (!isset($usedMain[$i])) $result[] = $sMain; } $codes = []; foreach ($findAllExt($main, med_cisz_constant::$structureDefinitions['PatientPreferences']) as $p) { $c = $getPrefCode($p); if ($c !== null && !in_array($c, $codes, true)) { $result[] = $p; $codes[] = $c; } } foreach ($findAllExt($additional, med_cisz_constant::$structureDefinitions['PatientPreferences']) as $p) { if (count($codes) >= 4) break; $c = $getPrefCode($p); if ($c !== null && !in_array($c, $codes, true)) { $result[] = $p; $codes[] = $c; } } $cMain = $findExt($main, med_cisz_constant::$structureDefinitions['ConsentPersonalData']); $cAdd = $findExt($additional, med_cisz_constant::$structureDefinitions['ConsentPersonalData']); if ($cMain === null && $cAdd === null) { } elseif ($cMain === null) { $result[] = $cAdd; } elseif ($cAdd === null) { $result[] = $cMain; } else { $sMain = $getConsentStart($cMain); $sAdd = $getConsentStart($cAdd); $result[] = ($sMain === null || ($sAdd !== null && $sAdd >= $sMain)) ? $cAdd : $cMain; } return $result; } private function setupArray(array &$a, string $urlF) : ?array { $tmpArr = []; foreach ($a as $k => $item) { if ($item->url === $urlF) { $tmpArr[] = $item; unset($a[$k]); } } $a = array_values($a); return $tmpArr; } private function mergeUpdate(?array $a, ?array $b, array $key) : ?array { $result = []; if (empty($a)) { return $b; } if (empty($b)) { return $a; } foreach ($a as $k => $item) { } return $result; } private function mergeUnion(?array $a, ?array $b, array $key) : ?array { $result = []; if (empty($a)) { return $b; } if (empty($b)) { return $a; } $tmpArr = []; foreach ($a as $k => $item) { if ($item->url === 'https://fhir.by/StructureDefinition/PatientJob') { } } return $result; } private function identifyArrRes($item, array $keys): string { $arr = is_object($item) ? get_object_vars($item) : (array)$item; $partsOf = []; foreach ($keys as $key) { $val = $arr[$key] ?? ''; $partsOf[] = is_scalar($val) ? (string)$val : json_encode($val); } return implode('|', $partsOf); } private function mergePatientIdentifiers(array $a, array $b): array { $result = $b; foreach ($a as $ciszId) { $code = $ciszId->type->coding[0]->code ?? null; if ($code === 'INP') { continue; } if ($code === 'UMD') { $ciszValue = $ciszId->value ?? null; $dup = false; foreach ($result as $r) { if (($r->type->coding[0]->code ?? null) === 'UMD' && ($r->value ?? null) === $ciszValue) { $dup = true; break; } } if (!$dup) { $result[] = $ciszId; } continue; } $found = false; foreach ($result as $i => $ourId) { if (($ourId->type->coding[0]->code ?? null) !== $code) { continue; } $found = true; $ourDate = $this->getIdentifierDate($ourId); $ciszDate = $this->getIdentifierDate($ciszId); if ($ciszDate !== null && $ourDate !== null && $ciszDate > $ourDate) { $result[$i] = $ciszId; } break; } if (!$found) { $result[] = $ciszId; } } return array_values($result); } private function getIdentifierDate(object $identifier): ?string { foreach ($identifier->extension ?? [] as $extension) { if (str_contains($extension->url, 'PassportIssued')) { foreach ($extension->extension ?? [] as $nestedExtension) { if (isset($nestedExtension->valueDate) && str_contains($nestedExtension->url, 'PassportIssuedDate')) { return $nestedExtension->valueDate; } } } } return $identifier->period->start ?? null; } } 