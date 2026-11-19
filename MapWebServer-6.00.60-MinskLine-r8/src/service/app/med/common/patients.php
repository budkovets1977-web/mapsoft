<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/common/uniDicts.php'); require_once(__DIR__ . '/../../map/common/storage.php'); require_once(__DIR__ . '/../../map/common/addresses.php'); require_once(__DIR__ . '/../../map/common/addressPlaceTypes.php'); require_once(__DIR__ . '/../../map/common/addressStreetTypes.php'); resumeJsonRpc(); class med_common_patients { const MAX_PAGE_SIZE = 30; const PATIENT_ADD_PERMISSION = 'med-common-patients-add'; const ERROR_NO_ADD_PERMISSION = 'У Вас нет прав на добавление пациентов. Обратитесь к Администратору.'; const PATIENT_EDIT_DELETE_PERMISSION = 'med-common-patients-edit-and-delete'; const ERROR_NO_EDIT_DELETE_PERMISSION = 'У Вас нет прав на изменение или удаление пациентов. Обратитесь к Администратору.'; private $db; private function getFilter($filters) { if (isset($filters) && !empty($filters)) { $sb = array(); foreach ($filters as $filter) { if (isset($filter->revert)) { $sb[] = "($filter->value $filter->property)"; } else { switch ($filter->property) { case '"status"': if ($filter->inputValue !== 2) { switch ($filter->inputValue) { case 0: $sb[] = "(\"startDate\" <= NOW() AND \"finishDate\" >= NOW())"; break; case 1: $sb[] = "(\"startDate\" <= NOW() AND \"finishDate\" < NOW())"; break; case 3: $sb[] = "(COALESCE(\"finishDate\", 30000101)::date != 30000101 AND \"finishDate\" > DATE(NOW()))"; break; default: break; } } break; case '"printed"': if($filter->inputValue !== 2) { $sb[] = $filter->inputValue === 0 ? "(coalesce(\"attributes\",0) & 1) = 1" : "(coalesce(\"attributes\",0) & 1) = 0"; } break; case '"isResident"': if ($filter->inputValue) { $sb[] = "(pass.alien = 0)"; } break; case '"dateBirthday"': $date = dbDate($filter->inputValue); $sb[] = "($filter->property='$date')"; break; default: $sb[] = "($filter->property $filter->value)"; break; } } } return implode(' and ', $sb); } else { return '(1=1)'; } } private function getSort($sortstr, $dir = null) { if (isset($sortstr)) { $sorters = base64_decode($sortstr); $sorters = safe_json_decode($sorters, false); if((is_null($sorters)) and (!empty($dir))) { $it = new stdClass(); $it->property = $sortstr; $it->direction = $dir; $sorters = [$it]; } $sb = array(); foreach ($sorters as $sort) { switch ($sort->property) { case 'numberHistorySickness': $sb[] = "RIGHT(REPEAT(' ' , 20) || asa_trim(numberHistorySickness), 20) $sort->direction"; break; default: $sb[] = "$sort->property $sort->direction"; break; } } return implode(', ', $sb); } else { return '1'; } } public function checkBarCode($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT EXISTS(SELECT BarCode FROM MedBarCode WHERE BarCode LIKE ?) AS "res"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$params]); return fetchValue($stmt, 'res'); } public function readPhoto($params) { $cache = getSessionValue('medCommonPatientPhotoCache'); header('Content-type: image/png'); header('Cache-Control: max-age=31536000'); if (isset($cache)) { echo base64_decode($cache); } else { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select photoId from medPatient
where MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->id ]); $patientGUID = $stmt->fetchAll()[0]['photoid']; if ($patientGUID != null) { $storage = new map_common_storage(); $fileName = "patients-photo/" . mb_substr($patientGUID, 0, 1) . "/" . mb_substr($patientGUID, 1, 2) . "/" . $patientGUID . ".jpg"; $raw = base64_encode($storage->readFile(2, $fileName)); echo base64_decode($raw); } } } public function updatePhoto() { header('Content-type: text/html'); setSessionValue('medCommonPatientPhotoCache', base64_encode(file_get_contents($_FILES['photo']['tmp_name']))); unlink($_FILES['photo']['tmp_name']); $rec = new stdClass; $rec->success = true; echo json_encode($rec); } public function updatePhotoScan($params) { if (isUndefinedProperty($params, 'file')) { setSessionValue('medCommonPatientPhotoCache', $params->file); } return true; } public function commitPhoto($params) { $db = $this->db ?: $this->db = connectDb(); if (isUndefinedProperty($params, 'action') && $params->action === "ok") { if (!is_null(getSessionValue('medCommonPatientPhotoCache'))) { $storage = new map_common_storage(); $image = base64_decode(getSessionValue('medCommonPatientPhotoCache')); $sql = <<<"EOT"
select photoId from medPatient
where MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->id ]); $patientGUID = $stmt->fetchAll()[0]['photoid']; if ($patientGUID === null) { $patientGUID = generateUUID(); $sql = <<<"EOT"
update MedPatient set
       photoId =  ?
where MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientGUID, $params->id ]); } $fileName = "patients-photo/" . mb_substr($patientGUID, 0, 1) . "/" . mb_substr($patientGUID, 1, 2) . "/" . $patientGUID . ".jpg"; $storage->writeFile(2, $fileName, $image); } } setSessionValue('medCommonPatientPhotoCache', null); return true; } public function deletePhoto($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select photoId from medPatient
where MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->id ]); $patientGUID = $stmt->fetchAll()[0]['photoid']; if ($patientGUID != null) { $storage = new map_common_storage(); $fileName = "patients-photo/" . mb_substr($patientGUID, 0, 1) . "/" . mb_substr($patientGUID, 1, 2) . "/" . $patientGUID . ".jpg"; $storage->deleteFile(2, $fileName); } return true; } public function getNextNumberHistorySickness($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); if (isset($params) && $params != "") { $sql = <<<"EOT"
        select
         CASE COALESCE(
          (select 1
           from MedPatient
           where EnterpriseCode = $enterpriseCode
            and NumberHistorySickness = '$params'
           LIMIT 1 ), 0) = 0
         WHEN true then '$params'
         WHEN false THEN (select asa_trim(incstr(min(repeat( E' ' , 40 - length(p.NumberHistorySickness)) || p.NumberHistorySickness)))
           from MedPatient AS P left join MedPatient AS pn on pn.NumberHistorySickness = incstr(p.NumberHistorySickness)
            and pn.EnterpriseCode = p.EnterpriseCode
           where p.EnterpriseCode = $enterpriseCode
            and (repeat( E' ' , 40 - length(p.NumberHistorySickness)) || p.NumberHistorySickness) >= ((repeat( E' ' , 40 - length('$params')) || '$params')
            )and pn.MedPatientCode is null
          )
         END as "nextNumber";
EOT;
} else { $sql = <<<"EOT"
SELECT INCSTR(COALESCE(asa_trim((
SELECT MAX(REPEAT(' ', 20 - LENGTH(NumberHistorySickness)) + NumberHistorySickness) as Num
FROM MedPatient
WHERE EnterpriseCode = $enterpriseCode)), '0')) AS "nextNumber"
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $numberHistorySickness = fetchValue($stmt, 'nextNumber'); return $numberHistorySickness; } public function checkNextNumberHistorySickness($number = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    *
FROM
    MedPatient
WHERE
    numberHistorySickness = '$number'
LIMIT
    1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $PatientCode = fetchValue($stmt, 'medpatientcode'); return $PatientCode; } public function detectPatientGroup($patient, $busy = null) { function createRange() { $result = new stdClass(); $result->_all = null; $result->_from = null; $result->_to = null; $result->_even = null; $result->range = []; return $result; } function getLeft($_str) { $words = preg_split("/[\s-]+/u", $_str); return trim($words[0]); } function getRight($_str) { $words = preg_split("/[\s-]+/u", $_str); $result = $words[0]; if (count($words) > 1) { $result = $words[1]; } return trim($result); } function getEven(&$ix, $_str) { if (($ix < safe_strlen($_str)) && ($_str[$ix] == '%')) { $_val = ''; $ix += 1; while ($ix < safe_strlen($_str)) { if (($_str[$ix] >= '0') && ($_str[$ix] <= '9')) { $_val = $_val . $_str[$ix]; } else { break; } $ix += 1; } $result = (int) $_val; } else { $result = 0; } return $result; } function getRange(&$ix, $_str) { $result = null; $_range = ''; $_brack = false; while ($ix < safe_strlen($_str)) { $ch = $_str[$ix]; if ($ch == '[') { if ((!$_brack) && (safe_strlen($_range) !== 0)) { break; } $_brack = true; } else { if (($ch == ']') || ($ch == '/') || ($ch == ' ') || ($ch == '%')) { if (($ch == ']') || ($ch == ' ')) { $ix += 1; } break; } else { $_range = $_range . $ch; } } $ix += 1; } $parts = preg_split("/[,]+/u", $_range); if (!count($parts) && !$_brack) { return $result; } $result = createRange(); $result->_all = safe_strlen($_range) == 0; $result->_even = getEven($ix, $_str); if (!$result->_all) { foreach ($parts as $part) { $new_range = createRange(); $new_range->_all = false; $new_range->_from = getLeft(trim($part)); $new_range->_to = getRight(trim($part)); $new_range->_even = -1; array_push($result->range, $new_range); } } return $result; } function isNext(&$ix, $_str, $_what) { $result = false; $fIx = $ix; while ($fIx < safe_strlen($_str)) { if ($_str[$fIx] == $_what) { $ix = $fIx + 1; $result = true; break; } else { if ($_str[$fIx] !== ' ') { break; } } $fIx += 1; } return $result; } function getAddrRange($house) { $house = str_replace(array('\r\n', '\r', '\n', '\t'), ';', $house); $house = trim($house); $house = preg_replace('/\s+/', ' ', $house); $house = preg_replace('/,\s+/', ',', $house); $house = preg_replace('/\s+,/', ',', $house); $house = preg_replace('/\[\s+/', '[', $house); $house = preg_replace('/\s+\[/', '[', $house); $house = preg_replace('/\s+\]/', ']', $house); $house = preg_replace('/\]\s+/', ']', $house); $house = preg_replace('/-\s+/', '-', $house); $house = preg_replace('/\s+-/', '-', $house); $house = preg_replace('/%\s+/', '%', $house); $house = preg_replace('/\s+%/', '%', $house); $house = preg_replace('/\/\s+/', '/', $house); $house = preg_replace('/\s+\//', '/', $house); $house = preg_replace('/;\s+/', ';', $house); $house = preg_replace('/\s+;/', ';', $house); $parts = preg_split("/[;]+/u", $house); $housesInfo = []; foreach ($parts as $part) { if (!empty($part)) { $housesInfo[] = $rec = new stdClass; $ix = 0; $rec->houses = getRange($ix, trim($part)); if (isNext($ix, $part, '/')) { $rec->corpuses = getRange($ix, trim($part)); } else { $rec->corpuses = null; } $rec->flats = getRange($ix, trim($part)); } } return $housesInfo; } function testEven($val, $even) { if (!($even == 2 || $even == 1)) { $result = true; } else { $result = ((((int) $val % 2) == 1) && ($even == 1)) || ((((int) $val % 2) == 0) && ($even == 2)); } return $result; } function isNeedToBeEmpty($val) { return ($val == '~'); } function testRange($val, $range) { $result = false; if ((isNeedToBeEmpty($range->_from) || isNeedToBeEmpty($range->_to)) && !($val)) { $result = true; return $result; } if (($range->_from == $range->_to) && ($val == $range->_from) && !isNeedToBeEmpty($range->_from)) { $result = true; } else { $result = (($val >= $range->_from) && ($val <= $range->_to)); } return $result; } function testValue($val, $settings) { $result = false; if (!$settings) { $result = true; return $result; } $result = testEven($val, $settings->_even); if ($result && !$settings->_all) { $result = false; foreach ($settings->range as $range) { if (testRange($val, $range)) { $result = true; return $result; } } } return $result; } $db = $this->db ?: $this->db = connectDb(); $addressResideId = isEmptyProperty($patient, 'addressResideId', null); $addressId = $addressResideId ? $addressResideId : isEmptyProperty($patient, 'addressId', null); $addressIds = array_unique([$addressId, isEmptyProperty($patient, 'addressId', null)]); $result = new stdClass(); $result->clinicId = null; $result->sectorId = null; $result->addSectors = []; $flMasterFound = false; for ($index = 0; $index < count($addressIds); $index++) { $addressId = $addressIds[$index]; if ($addressId == null) { continue; } $prefix = ($addressId == $addressResideId) ? 'Reside' : ''; $house = isEmptyProperty($patient, 'address' . $prefix . 'House', null); $corps = isEmptyProperty($patient, 'address' . $prefix . 'Corps', null); $flat = isEmptyProperty($patient, 'address' . $prefix . 'Flat', null); if (!$addressId) { return $result; } $address = new map_common_addresses(); $tmpParams = new stdClass(); $tmpParams->id = $addressId; $address = $address->readAddresses($tmpParams); $address = (object) $address->items[0]; if (!$address) { return $result; } $dateOfBirth = Date("Ymd", safe_strtotime(isEmptyProperty($patient, 'dateOfBirth'))); $sqlAge = "select (EXTRACT(YEAR FROM age(today(), $dateOfBirth))) as age"; $stmtAge = $db->prepare($sqlAge); $stmtAge->execute(); $age = fetchValue($stmtAge, 'age'); $sex = isEmptyProperty($patient, 'sex', 0); $country = isEmptyProperty($address, 'country', ''); $area = isEmptyProperty($address, 'area', ''); $areaType = isEmptyProperty($address, 'areaType', ''); $district = isEmptyProperty($address, 'district', ''); $region = isEmptyProperty($address, 'region', ''); $place = isEmptyProperty($address, 'place', ''); $placeType = isEmptyProperty($address, 'placeType', ''); $ward = isEmptyProperty($address, 'ward', ''); $wardType = isEmptyProperty($address, 'wardType', ''); $street = isEmptyProperty($address, 'street', ''); $streetType = isEmptyProperty($address, 'streetType', ''); $sql = <<<"EOT"
SELECT mpl.medPatientLocationCode   "id",
       mpl.medPatientGroupCode      "sectorId",
       mpl.special                  "special",
       mpl.medPatientPolyclinicCode "clinicId",
       mpl.searchType               "searchType",
       mpl.useStartAge              "useStartAge",
       mpl.startAge                 "startAge",
       mpl.useFinishAge             "useFinishAge",
       mpl.finishAge                "finishAge",
       mpl.sex                      "sex",
       mpla.country                 "country",
       mpla.area                    "area",
       mpla.areaType                "areaType",
       mpla.district                "district",
       mpla.region                  "region",
       mpla.place                   "place",
       mpla.placeType               "placeType",
       mpla.ward                    "ward",
       mpla.wardType                "wardType",
       mpla.street                  "street",
       mpla.streetType              "streetType",
       mpla.house                   "house",
       mplb.enterpriseKindCode      "organizationKindId",
       mplb.enterpriseCode          "organizationId",
       mplb.departmentCode          "departmentId"
FROM medPatientLocation mpl
LEFT JOIN medPatientLocationAddress mpla ON mpla.medPatientLocationCode = mpl.medPatientLocationCode
      /* Настройка типа поиска AND COALESCE(mpl.searchType, 0) = 0 */
      /*Фильтр по адресам*/
      AND (COALESCE(mpla.country, '') = '' OR '$country' LIKE mpla.country || '%')
      AND (COALESCE(mpla.area, '') = '' OR '$area' LIKE mpla.area || '%')
      AND (COALESCE(mpla.areaType, '') = '' OR '$areaType' LIKE mpla.areaType || '%')
      AND (COALESCE(mpla.district, '') = '' OR '$district' LIKE mpla.district || '%')
      AND (COALESCE(mpla.region, '') = '' OR '$region' LIKE mpla.region || '%')
      AND (COALESCE(mpla.placeType, '') = '' OR REPLACE('$placeType', '.', '') LIKE REPLACE(mpla.placeType, '.', '') || '%')
      AND (COALESCE(mpla.ward, '') = '' OR '$ward' LIKE mpla.ward || '%')
      AND (COALESCE(mpla.wardType, '') = '' OR '$wardType' LIKE mpla.wardType || '%')
      AND (COALESCE(mpla.streetType, '') = '' OR REPLACE('$streetType', '.', '') LIKE REPLACE(mpla.streetType, '.', '') || '%')
LEFT JOIN medPatientLocationBusy mplb ON mplb.medPatientLocationCode = mpl.medPatientLocationCode AND mpl.searchType = 1
WHERE (mpla.medpatientlocationaddressCode IS NOT NULL OR mplb.medpatientlocationbusyCode IS NOT NULL)
  AND (COALESCE(mpl.sex, 2) = 2 OR COALESCE(mpl.sex, 0) = $sex)              /*Фильтр по настройке пола*/
  AND (COALESCE(mpl.useStartAge, 0) = 0 OR COALESCE(mpl.startAge) <= $age)   /*Возраст от*/
  AND (COALESCE(mpl.useFinishAge, 0) = 0 OR COALESCE(mpl.finishAge) >= $age) /*Возраст до*/
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $settings = $stmt->fetchAll(); $settings = array_filter($settings, function ($setting) use ($place, $street) { return $this->compareAddressNames($place, $setting['place']) && $this->compareAddressNames($street, $setting['street']); }); if ($settings) { foreach ($settings as $setting) { $setting = (object) $setting; if ($flMasterFound && !$setting->special) { continue; } if ($setting->house) { $houseSettings = getAddrRange($setting->house); foreach ($houseSettings as $houseSetting) { if ( testValue($house, $houseSetting->houses) && testValue($corps, $houseSetting->corpuses) && testValue($flat, $houseSetting->flats) ) { if ($setting->special) { array_push($result->addSectors, $setting->sectorId); if ($setting->clinicId && !isEmptyProperty($result, 'clinicId', false)) { $result->clinicId = $setting->clinicId; } } else { if ($index == 0) { $flMasterFound = true; $result->clinicId = $setting->clinicId; $result->sectorId = $setting->sectorId; } } } } } else { if ($setting->special) { array_push($result->addSectors, $setting->sectorId); } else { if ($index == 0) { $flMasterFound = true; $result->clinicId = $setting->clinicId; $result->sectorId = $setting->sectorId; } } } } } } if ($flMasterFound || count($result->addSectors)) { return $result; } else { return false; } } private function normalizeAddressName($value) { $value = mb_strtolower(trim((string)$value), 'UTF-8'); $value = str_replace('ё', 'е', $value); $value = preg_replace( '/(?<![\p{L}\p{N}])(\d+)\s*[-\s]?\s*(?:й|я|е|го|ая|ый|ое)(?![\p{L}\p{N}])/u', '$1', $value ); $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value); $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY); sort($words, SORT_STRING); return $words; } private function compareAddressNames($addressName, $settingName) { if (trim((string)$settingName) === '') { return true; } $addressWords = $this->normalizeAddressName($addressName); $settingWords = $this->normalizeAddressName($settingName); return $addressWords === $settingWords; } public function readPatients($params) { $storage = new map_common_storage(); $searchType = $params->searchType = isUndefinedProperty($params, 'searchType', 'auto'); $uniSearch = isEmptyProperty($params, 'uniSearch'); if ($uniSearch) { $searchType = $params->searchType = 'simple'; $letters = trim(preg_replace("/[^a-zA-ZА-Яа-я ]/u", '', $uniSearch)); $params->fio = $letters; $numbers = preg_replace("/[^0-9]/", '', $uniSearch); $params->mobilePhone = $numbers; } if ($searchType == 'auto+') { $root = new stdClass(); $root = $this->readWebPatients($params); } else if ($searchType == 'simple') { $root = new stdClass(); $root = $this->readWebPatientsSimple($params); } else { $root = new stdClass(); $root = $this->readMAPPatients($params); } foreach ($root->items as $key => $item) { if ($item['photoId'] != null) { $fileName = $fileName = "patients-photo/" . mb_substr($item['photoId'], 0, 1) . "/" . mb_substr($item['photoId'], 1, 2) . "/" . $item['photoId'] . ".jpg"; if ($storage->isFileExists(2, $fileName)) { $root->items[$key]['photoExist'] = 1; } } } return $root; } protected function readWebPatients($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $isLight = (int) in_array(getSessionValue('mapWebTaskCode'), [524, 526, 527, 528, 530, 531]); $patientId = isEmptyProperty($params, 'patientId', isEmptyProperty($params, 'id', 0)); $excludeId = isEmptyProperty($params, 'excludeId', -1); $getClinic = isEmptyProperty($params, 'getClinic'); $isActive = isEmptyProperty($params, 'isActive', false); $isActiveSql = ""; $currDate = Date("Ymd"); $allCondSearch = ""; $aFio = ""; $filters = []; $filterstr = isEmptyProperty($params, 'filter'); $searchArray = array(); if (isset($filterstr)) { $filterstr = base64_decode($filterstr); $index = ""; $filters = safe_json_decode($filterstr, false); foreach ($filters as $key => $search) { if ($search->property === 'search') { $searchArray = safe_explode('|', $search->value); $index = $key; } } unset($filters[$index]); } foreach ($searchArray as $aFio) { $aFio = trim($aFio); if ($aFio === "") { continue; } $condSearch = ""; $aFio = mb_strtolower($aFio, 'UTF-8'); $matches = array(); $reg = '/^(\+|\-|\ |\(|\)|\d)*$/'; $count = ""; if (preg_match_all($reg, $aFio, $matches, PREG_SET_ORDER, 0)) { $numbString = preg_replace("/[^0-9]/", '', $matches[0][0]); $count = iconv_strlen($numbString); $rez = str_split($numbString); } if ($count) { $value = implode("", $rez); if ($count >= 16) { $condSearch .= "EXISTS(select 1
                                          from MedBarCode
                                          where BarCode LIKE '$numbString'
                                            and ManCode = mp.ManCode
                                          limit 1)"; $condSearch .= " or mp.NumberHistorySickness LIKE '%$numbString%'"; } else { $condSearch .= " mp.NumberHistorySickness LIKE '%$numbString%'"; } if ($count >= 5) { $condSearch .= " or EXISTS(select PhoneNumber
                                          from ManPhone
                                          where translate(PhoneNumber,'0' || translate(PhoneNumber,'_0123456789','_'),'0') LIKE '%$value'
                                            and ManCode = mp.ManCode)"; } } else { $fio = preg_split("/[\s.,]+/u", $aFio); $condSearch = "M.SurName LIKE '$fio[0]%'"; if (isset($fio[1]) && $fio[1] !== "") { $condSearch .= " and M.Name LIKE '$fio[1]%'"; } if (isset($fio[2]) && $fio[2] !== "") { $fio[2] = trim(implode(" ", (array_slice($fio, 2)))); $condSearch .= " and M.SecondName LIKE '$fio[2]%'"; } $condSearch = '(' . $condSearch . ')'; preg_match_all('~"([^"]*)"~u', $aFio, $street); if (count($street[0]) != 0) { $aFio = str_replace($street[0][0], "", $aFio); $adress = preg_split('/[\s\"]+/u', $aFio); array_splice($adress, 1, 0, $street[1]); } else { $adress = preg_split('/[\s\"]+/u', $aFio); } $street = preg_split("/[.]+/", $adress[0]); if (isset($street[1]) && $street[1] === "" && isset($adress[1])) { $street[1] = $adress[1]; $adress[0] = $adress[0] . $adress[1]; unset($adress[1]); } if (isset($street[1])) { if (!isset($addressPlaceTypes)) { $addressPlaceTypes = new map_common_addressPlaceTypes(); $addressStreetTypes = new map_common_addressStreetTypes(); $streetType = $addressStreetTypes->readAddressStreetTypes(); $placeType = $addressPlaceTypes->readAddressPlaceTypes(); } switch (mb_strtolower($street[0], 'UTF-8')) { case "у": $street[0] = 'ул'; break; case "пер": $street[0] = 'п'; break; case "дер": $street[0] = 'д'; break; } foreach ($streetType as $value) { if (!is_numeric($value['shortName']) && $value['shortName']) { if ($value['shortName'][safe_strlen($value['shortName']) - 1] === '.') { $value['shortName'] = substr($value['shortName'], 0, -1); } if ($street[0] === mb_strtolower($value['shortName'], 'UTF-8')) { $adressType = " and ar.AddressStreetTypeCode=" . $value['id'] . ""; $adressPlace = "ar.street LIKE '%$street[1]%'"; $adressResideType = " and a.AddressPlaceTypeCode = " . $value['id'] . ""; $adressResidePlace = "a.place LIKE '%$street[1]%'"; break; } } } foreach ($placeType as $value) { if (!is_numeric($value['shortName']) && $value['shortName']) { if ($value['shortName'][safe_strlen($value['shortName']) - 1] === '.') { $value['shortName'] = substr($value['shortName'], 0, -1); } if ($street[0] === mb_strtolower($value['shortName'], 'UTF-8')) { if (isset($street[2])) { $adressPlace = "ar.place LIKE '%$street[1].$street[2]%'"; $adressResidePlace = "a.place LIKE '%$street[1].$street[2]%'"; $adressResideStreet = " or (a.place LIKE '%$street[1].$street[2]%'"; $adressStreet = " or (ar.place LIKE '%$street[1].$street[2]%'"; } else { $adressPlace = "ar.place LIKE '%$street[1]%'"; $adressResidePlace = "a.place LIKE '%$street[1]%'"; $adressResideStreet = " or (a.place LIKE '%$street[1]%'"; $adressStreet = " or (ar.place LIKE '%$street[1]%'"; } $adressType = " and ar.AddressPlaceTypeCode = " . $value['id'] . ""; $adressResideType = " and a.AddressPlaceTypeCode = " . $value['id'] . ""; $adressResideTypeStreet = $adressResideType; $adressTypeStreet = $adressType; break; } } } } if (!isset($adressType)) { $adressResideStreet = " or (a.place LIKE '%$adress[0]%'"; $adressResideTypeStreet = ""; $adressTypeStreet = ""; $adressStreet = " or (ar.place LIKE '%$adress[0]%'"; } if (isset($adress[1]) && $adress[1] !== "") { $localAdress = preg_split("/[\\\s\/]+/", $adress[1]); if (!isset($adressType)) { $adressType = ""; $adressPlace = "((ar.street LIKE '%$street[0]%') or (ar.place LIKE '%$street[0]%'))"; $adressResideType = ""; $adressResidePlace = "((a.street LIKE '%$street[0]%') or (a.place LIKE '%$street[0]%'))"; } $adressSearch = "$adressResidePlace $adressResideType"; $adressSearch .= " and pass.AddressResideHouse LIKE '$localAdress[0]'"; if (isset($localAdress[1]) && $localAdress[1] !== "") { $adressSearch .= " and pass.AddressResideCorps LIKE '$localAdress[1]'"; } if (isset($adress[2]) && $adress[2] !== "") { $adressSearch .= " and pass.AddressResideFlat LIKE '$adress[2]'"; } $adressSearch = '(' . $adressSearch . ') or ('; $adressSearch .= "$adressPlace $adressType"; $adressSearch .= " and pass.AddressHouse LIKE '$localAdress[0]'"; if (isset($localAdress[1]) && $localAdress[1] !== "") { $adressSearch .= " and pass.AddressCorps LIKE '$localAdress[1]'"; } if (isset($adress[2]) && $adress[2] !== "") { $adressSearch .= " and pass.AddressFlat LIKE '$adress[2]'"; } $adressSearch = '' . $adressSearch . ')'; $adressType = ""; $adressPlace = " and ar.street LIKE '%$adress[1]%'"; $adressResideType = ""; $adressResidePlace = " and a.street LIKE '%$adress[1]%'"; $street = preg_split("/[.]+/", $adress[1]); if (isset($street[1])) { $addressPlaceTypes = new map_common_addressPlaceTypes(); $addressStreetTypes = new map_common_addressStreetTypes(); $streetType = $addressStreetTypes->readAddressStreetTypes(); $placeType = $addressPlaceTypes->readAddressPlaceTypes(); switch (mb_strtolower($street[0], 'UTF-8')) { case "у": $street[0] = 'ул'; break; case "пер": $street[0] = 'п'; break; case "дер": $street[0] = 'д'; break; } foreach ($streetType as $value) { if (!is_numeric($value['shortName']) && $value['shortName']) { if ($value['shortName'][safe_strlen($value['shortName']) - 1] === '.') { $value['shortName'] = substr($value['shortName'], 0, -1); } if ($street[0] === mb_strtolower($value['shortName'], 'UTF-8')) { $adressType = " and ar.AddressStreetTypeCode=" . $value['id'] . ""; $adressResideType = " and a.AddressStreetTypeCode = " . $value['id'] . ""; $adressPlace = " and ar.street LIKE '%$street[1]%'"; $adressResidePlace = " and a.street LIKE '%$street[1]%'"; break; } } } } if (isset($adressResideStreet) && isset($adressResideTypeStreet)) { $adressSearch .= "$adressResideStreet $adressResideTypeStreet"; } if (isset($adressResidePlace) && isset($adressResideType)) { $adressSearch .= "$adressResidePlace $adressResideType"; } if (isset($adress[2]) && $adress[2] !== "") { $localAdress = preg_split("/[\s\/]|[\s\\\\]+/", $adress[2]); } else { $localAdress = null; } if (isset($localAdress[0]) && $localAdress[0] !== "") { $adressSearch .= " and pass.AddressResideHouse LIKE '$localAdress[0]'"; } if (isset($localAdress[1]) && $localAdress[1] !== "") { $adressSearch .= " and pass.AddressResideCorps LIKE '$localAdress[1]'"; } if (isset($adress[3]) && $adress[3] !== "") { $adressSearch .= " and pass.AddressResideFlat LIKE '$adress[3]')"; } else { $adressSearch .= ")"; } $adressSearch .= "$adressStreet $adressTypeStreet"; $adressSearch .= "$adressPlace $adressType"; if (isset($localAdress[0]) && $localAdress[0] !== "") { $adressSearch .= " and pass.AddressHouse LIKE '$localAdress[0]'"; } if (isset($localAdress[1]) && $localAdress[1] !== "") { $adressSearch .= " and pass.AddressCorps LIKE '$localAdress[1]'"; } if (isset($adress[3]) && $adress[3] !== "") { $adressSearch .= " and pass.AddressFlat LIKE '$adress[3]')"; } else { $adressSearch .= ")"; } } else { if (isset($adressType)) { $adressSearch = "($adressPlace $adressType) or ($adressResidePlace $adressResideType)"; } else { if (isset($street[1])) { $adressSearch = "(((a.street LIKE '%$street[1]%') or (a.place LIKE '%$street[1]%')) or ((ar.street LIKE '%$street[1]%') or (ar.place LIKE '%$street[1]%')))"; } else { $adressSearch = "(((a.street LIKE '%$street[0]%') or (a.place LIKE '%$street[0]%')) or ((ar.street LIKE '%$street[0]%') or (ar.place LIKE '%$street[0]%')))"; } } if (preg_match('/\d/', $adress[0]) && (iconv_strlen($adress[0]) == 14)) { $condSearch .= " or (pass.PersonalNumber LIKE '$adress[0]')"; $condSearch = '(' . $condSearch . ')'; } if (preg_match('/\d/', $adress[0])) { $condSearch .= "or (mp.NumberHistorySickness LIKE '%$adress[0]%')"; } } if (isset($adressSearch)) { $adressSearch = ' or (' . $adressSearch . ') '; $condSearch .= $adressSearch; } } if ($allCondSearch) { $allCondSearch .= ' or '; } $allCondSearch .= '(' . $condSearch . ')'; } if ($allCondSearch) { $allCondSearch = ' and (' . $allCondSearch . ') '; } $allCondSearch .= ' and (COALESCE(mp.medPatientCode, -1) <> ' . $excludeId . ') '; $filter = $this->getFilter($filters); $sort = $this->getSort(isEmptyProperty($params, 'sort'), isEmptyProperty($params, 'dir')); $addressSortField = ""; if (stristr($sort, '"addressDisplayf"') != FALSE) { $addressSortField = <<<"EOT"
    CASE
          WHEN pass.AddressResideCode is null THEN
                  ( select concat(COALESCE(asa_trim(pt.ShortName), E'' ),COALESCE(asa_trim(a.Place), E'' ) ,', ', COALESCE(asa_trim(st.ShortName), E'' ) , '.', COALESCE(asa_trim(a.Street), E'' ),
        ' д.', lpad(COALESCE(asa_trim(pass.AddressHouse), E'' ), 8, ' ') , ' к.', lpad(COALESCE(asa_trim(pass.AddressCorps), E'' ), 4, ' ') , ' кв.', lpad(COALESCE(asa_trim(pass.AddressFlat), E'' ), 9, ' ')
        )

   from Address AS a left outer join Country AS c on c.CountryCode = a.CountryCode
    left outer join AddressPlaceType AS pt on pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
    left outer join AddressStreetType AS st on st.AddressStreetTypeCode = a.AddressStreetTypeCode
   where a.AddressCode = pass.AddressCode LIMIT 1 )
          ELSE
                  ( select concat(COALESCE(asa_trim(pt.ShortName), E'' ),COALESCE(asa_trim(a.Place), E'' ) ,', ', COALESCE(asa_trim(st.ShortName), E'' ) , '.', COALESCE(asa_trim(a.Street), E'' ),
        ' д.', lpad(COALESCE(asa_trim(pass.AddressResideHouse), E'' ), 8, ' ') , ' к.', lpad(COALESCE(asa_trim(pass.AddressResideCorps), E'' ), 4, ' ') , ' кв.', lpad(COALESCE(asa_trim(pass.AddressResideFlat), E'' ), 9, ' ')
        )

   from Address AS a left outer join Country AS c on c.CountryCode = a.CountryCode
    left outer join AddressPlaceType AS pt on pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
    left outer join AddressStreetType AS st on st.AddressStreetTypeCode = a.AddressStreetTypeCode
   where a.AddressCode = pass.AddressResideCode LIMIT 1 )
          END as "addressDisplayf",
EOT;
} $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $fieldIsActive = <<<"EOT"
(CASE
    WHEN COALESCE(mp.FinishDate, 30000101)::date = 30000101 OR mp.finishDate > DATE(NOW()) THEN 1
    ELSE 0
END)
EOT;
if ($isActive) { $isActiveSql = " AND (COALESCE(mp.FinishDate, 30000101)::date = 30000101 OR mp.finishDate > DATE(NOW())) "; } $fieldAge = <<<"EOT"
(EXTRACT(YEAR FROM age($currDate, pass.DateOfBirth)))
EOT;
$inFilterFields = array( "\"isActive\"", "\"age1\"", "\"age2\"", "\"sectorId\"", "\"sectorAddId\"", "\"contingentId\"", "\"startDate\"", "\"finishDate\"" ); $outFilterFields = array( $fieldIsActive, $fieldAge, $fieldAge, "mp.MedPatientGroupCode", "mpag.MedPatientGroupCode", "mpc.MedPrivelegeCode", "mp.StartDate", "mp.FinishDate" ); $fieldClinic = $getClinic ? ',( select name from medPatientPolyclinic where MedPatientPolyclinicCode = mp.MedPatientPolyclinicCode) "clinicName"' : ''; $filter = str_replace($inFilterFields, $outFilterFields, $filter); $sqlFromWhere = <<<"EOT"
FROM MedPatient AS mp
JOIN Man AS m ON m.ManCode = mp.ManCode
LEFT JOIN ManPassport AS pass ON pass.ManCode = mp.ManCode
LEFT JOIN ManPassportKind AS mpk ON mpk.ManPassportKindCode = pass.ManPassportKindCode
LEFT JOIN MedPatientGroup AS mpg ON mpg.MedPatientGroupCode = mp.MedPatientGroupCode
LEFT JOIN MedPatientAddGroup AS mpag ON mpag.MedPatientCode = mp.MedPatientCode
LEFT JOIN MedPatientGroup AS mpgadd ON mpgadd.MedPatientGroupCode = mpag.MedPatientGroupCode
LEFT JOIN MedPatientInfo AS mpi ON mpi.MedPatientCode = mp.MedPatientCode
/* LEFT JOIN UniList AS ulwp ON ulwp.UniListCode = mpi.UniListWorkPlaceCode */
LEFT JOIN Address AS a ON a.AddressCode = pass.AddressResideCode
LEFT JOIN Country AS c ON c.CountryCode = a.CountryCode
LEFT JOIN Address AS ar ON ar.AddressCode = pass.AddressCode
LEFT JOIN Country AS cr ON cr.CountryCode = ar.CountryCode
LEFT JOIN AddressPlaceType AS pt ON pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
LEFT JOIN AddressStreetType AS st ON st.AddressStreetTypeCode = a.AddressStreetTypeCode
/* Подтормаживает, отдельные подзапросы справляются быстрее
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 1
                   ORDER BY ManPhoneCode DESC LIMIT 1) phone on TRUE
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 3
                   ORDER BY ManPhoneCode DESC LIMIT 1) phoneMobile on TRUE
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 4
                   ORDER BY ManPhoneCode DESC LIMIT 1) mail on TRUE
*/
LEFT JOIN MedPatientCard AS mpc ON mpc.MedPatientCode = mp.MedPatientCode AND
                                   $currDate BETWEEN mpc.StartDate AND COALESCE(mpc.FinishDate, 30000101)::date
LEFT JOIN ManTask MT ON MT.ManCode = mp.ManCode AND MT.EnterpriseCode = $enterpriseCode AND MT.TypeTask = 12
LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
WHERE mp.EnterpriseCode = $enterpriseCode
    and ($isLight = 1
        or ($isLight = 0
            /* хоть один участок доступен пользователю */
            and (mp.MedPatientGroupCode in (select gc from pg_)
                or (mpgadd.MedPatientGroupCode is not null
                    and mpgadd.MedPatientGroupCode in (select gc from pg_)
                )
                OR MP.MedPatientGroupCode IS NULL
            )
        )
    )
  AND mp.FlagGroup IS NULL
  AND (mp.MedPatientCode = $patientId or 0=$patientId)
  AND ($filter)
  $isActiveSql
  $allCondSearch
GROUP BY mp.MedPatientCode, mpg.MedPatientGroupCode, m.ManCode, pass.ManPassportCode, MT.Note /*, ulwp.UniListCode, mpc.MedPatientCardCode*/
EOT;
$sqlTotal = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
SELECT
       COUNT(*) AS "total"
FROM (SELECT DISTINCT mp.MedPatientCode
$sqlFromWhere) AS t
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
SELECT  t.*,
        FN_cmCalcAddress(t."manId", 0)::text as "address",
        FN_cmCalcAddress(t."manId", 1)::text as "addressReside",
        (SELECT string_agg(pg.Name::text, '; ' ORDER BY pg.Name)
         FROM MedPatientAddGroup AS MPAD
         JOIN MedPatientGroup AS pg ON pg.MedPatientGroupCode = MPAD.MedPatientGroupCode
         WHERE MPAD.MedPatientCode = t.id
           AND COALESCE(pg.ShowWithPatient, 0)::integer = 1)::text as "patientGroupAdd",
        (SELECT string_agg(eb.Name::text, '; ' ORDER BY eb.Name)
         FROM MedPatientBusy mb
         LEFT JOIN Enterprise as eb ON mb.EnterpriseCode = eb.EnterpriseCode
         WHERE mb.MedPatientCode = t.id
           AND $currDate BETWEEN mb.StartDate AND mb.FinishDate) as "workPlace",
        (SELECT string_agg(ud.Value::text, '; ' ORDER BY ud.Value)
         FROM MedPatientBusy mb
         LEFT JOIN Enterprise as eb ON mb.EnterpriseCode = eb.EnterpriseCode
         LEFT JOIN UniDict as ud ON ud.UniDictCode = COALESCE(mb.UniDictCodeProfession,0) and ud.DictNumber = 19
         WHERE mb.MedPatientCode = t.id
           AND $currDate BETWEEN mb.StartDate AND mb.FinishDate) as "profession",
        FN_medPatientPictureList(t.id, NULL, 2, 1) as "shortStatesList",
        FN_medPatientPictureList(t.id, NULL) as "fullStatesList"
FROM (
    SELECT
        mp.MedPatientCode                       "id",
        mp.ManCode                              "manId",
        $addressSortField
        FN_cmCalcAddress(mp.ManCode, 5)::text   "addressDisplay",
        FN_cmFIO(mp.ManCode)::text              "fio",
        m.surname                               "surname",
        m.name                                  "name",
        m.secondname                            "secondName",
        mp.Cipher                               "cipher",
        mp.PhotoId                              "photoId",
        0                                       "photoExist",
        mp.NumberHistorySickness                "numberHistorySickness",
        mp.MedPatientGroupCode                  "sectorId",
        mp.archivalNumber                       "archivalNumber",
        mp.MedPatientPolyclinicCode             "clinicId",
        mp.OutReasonEx                          "outReason",
        mpg.Name                                "sector",
        mpg.MedDoctorCode                       "sectorDoctorId",
        string_agg(distinct(mpgadd.Name::text), '; ')
                                                "sectorAdd",
        string_agg(distinct(mpgadd.MedPatientGroupCode::text), ',')
                                                "sectorAddIds",
        CASE
            WHEN mp.StartDate = 19000101 THEN null
            ELSE mp.StartDate
        END::date                               "startDate",
        CASE
            WHEN mp.FinishDate = 30000101 THEN null
            ELSE mp.FinishDate
        END::date                               "finishDate",
        pass.ManPassportCode                    "manPassportId",
        pass.AddressCode                        "addressId",
        pass.AddressText                        "addressText",
        pass.CiszAddressCode                    "addressCiszCode",
        pass.AddressHouse                       "addressHouse",
        pass.AddressCorps                       "addressCorps",
        pass.AddressFlat                        "addressFlat",
        pass.Zipcode                            "addressZipcode",
        MAX(c.name)                             "country",
        pass.AddressResideCode                  "addressResideId",
        pass.AddressResideText                  "addressResideText",
        pass.CiszAddressResideCode              "addressResideCiszCode",
        pass.AddressResideHouse                 "addressResideHouse",
        pass.AddressResideCorps                 "addressResideCorps",
        pass.AddressResideFlat                  "addressResideFlat",
        pass.Alien                              "alien",
        pass.ZipcodeReside                      "addressResideZipcode",
        MAX(cr.name)                            "countryReside",
        pass.Sex                                "sex",
        pass.DateOfBirth                        "dateOfBirth",
        pass.PassportNumber                     "passportNumber",
        pass.Authority                          "authority",
        pass.DateOfIssue                        "dateOfIssue",
        pass.DateOfExpiry                       "dateOfExpiry",
        pass.PlaceOfBirth                       "placeOfBirth",
        pass.PersonalNumber                     "personalNumber",
        pass.ManPassportKindCode                "passportKindId",
        MAX(mpk.Name)                           "passportKindName",
        random()                                "random",
        (select string_agg((concat( case
            when mpn.phoneType=1 then 'д.'
            when mpn.phoneType=2 then 'р.'
            when mpn.phoneType=3 then 'м.'
            when mpn.phoneType=4 then 'п.'
        else null
            end, mpn.phoneNumber))::text, ', ' ) from manphone AS mpn where mpn.ManCode=mp.ManCode)
                                                "contact",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType is not distinct from (SELECT CASE WHEN EXISTS (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 1) THEN 1 ELSE null END) ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "phoneId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType is not distinct from (SELECT CASE WHEN EXISTS (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 1) THEN 1 ELSE null END) ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "phone",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 2 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "workPhoneId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 2 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "workPhone",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "mobilePhoneId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "mobilePhone",
        (SELECT Note FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "mobilePhoneOperator",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "mailId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = mp.ManCode AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1)
                                                "mail",
        (
            SELECT inspectDate
            FROM medInspect
            WHERE inspectDate IS NOT NULL AND medPatientCode = mp.medPatientCode
            ORDER BY inspectDate DESC
            LIMIT 1
        )                                       "lastDispensaryExaminationDate",
        (
            SELECT STRING_AGG(pr.name, ', ')
            FROM medPatientCard pc
            JOIN medPrivelege pr ON pr.medPrivelegeCode = pc.medPrivelegeCode
            WHERE pc.medPatientCode = mp.medPatientCode
                AND ('NOW'::TIMESTAMP) BETWEEN pc.startDate AND COALESCE(pc.finishDate, 30000101)
        )                                       "privileges",
        (
            SELECT STRING_AGG(PR.Name, ', ')
            FROM MedPatientCard PC
            JOIN MedPrivelege PR ON PR.MedPrivelegeCode = PC.MedPrivelegeCode
            LEFT JOIN MedPrivelegeFeature MPF ON MPF.MedPrivelegeCode = PR.MedPrivelegeCode
            WHERE PC.MedPatientCode = MP.MedPatientCode
                AND ('NOW'::TIMESTAMP) BETWEEN PC.StartDate AND COALESCE(PC.FinishDate, 30000101)
                AND MPF.MedPrivelegeFeatureCode IS NOT NULL
        )                                       "privilegesToDisplay",      -- Контингенты, выбранные для отображения в справочнике Статусы пациентов
        $fieldIsActive                          "isActive",
        $fieldAge                               "age",
        MT.Note                                 "patientNote",              -- Примечание
        mp.JSON AS "json",
        mp.Anonymous AS "anonymous",
        MAX(COALESCE(CL.Status,0)) AS ciszState,
        CASE
    WHEN pass.CiszAddressCode IS NOT NULL
         OR (
              MAX(cr.name) IS NOT NULL
              AND TRIM(MAX(cr.name)) <> ''
              AND MAX(cr.name) NOT ILIKE '%беларус%'
              AND MAX(cr.name) <> 'РБ'
         )
    THEN true
    ELSE false
        END AS "isCiszAddress",
        CASE
            WHEN pass.CiszAddressResideCode IS NOT NULL
                 OR (
                      MAX(c.name) IS NOT NULL
                      AND TRIM(MAX(c.name)) <> ''
                      AND MAX(c.name) NOT ILIKE '%беларус%'
                      AND MAX(c.name) <> 'РБ'
                 )
            THEN true
            ELSE false
        END AS "isCiszAddressReside",
    CASE WHEN MAX(cr.name) IS NOT NULL AND TRIM(MAX(cr.name)) <> '' AND MAX(cr.name) NOT ILIKE '%беларус%' AND MAX(cr.name) <> 'РБ'
    THEN true ELSE false END AS "isForeignAddress",
    CASE WHEN MAX(c.name) IS NOT NULL AND TRIM(MAX(c.name)) <> '' AND MAX(c.name) NOT ILIKE '%беларус%' AND MAX(c.name) <> 'РБ'
    THEN true ELSE false END AS "isForeignAddressReside"
        /* ulwp.Value as "workPlaceRem" */
        {$fieldClinic}
$sqlFromWhere
order by $sort
limit $limit offset $start) AS t
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function readWebPatientsSimple($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $patientId = isEmptyProperty($params, 'patientId', isEmptyProperty($params, 'id', 0)); $currDate = Date("Ymd"); $aFio = isEmptyProperty($params, 'fio'); $phone = isEmptyProperty($params, 'mobilePhone'); $joinPhone = "LEFT JOIN lateral (select manPhoneCode, phoneNumber from ManPhone where ManCode = mp.ManCode and phoneType IN (1,3) order by phoneType desc limit 1) _ManPhone on true"; $condSearch = ""; $condPhone = ""; $aFio = mb_strtolower($aFio, 'UTF-8'); if ($aFio) { $fio = preg_split("/[\s.,]+/u", $aFio); $condSearch = " and M.SurName LIKE '$fio[0]%'"; if (isset($fio[1]) && $fio[1] !== "") { $condSearch .= " and M.Name LIKE '$fio[1]%'"; } if (isset($fio[2]) && $fio[2] !== "") { $fio[2] = trim(implode(" ", (array_slice($fio, 2)))); $condSearch .= " and M.SecondName LIKE '$fio[2]%'"; } } $matches = array(); $reg = '/^(\+|\-|\ |\(|\)|\d)*$/'; $count = ""; if (preg_match_all($reg, $phone, $matches, PREG_SET_ORDER, 0)) { $numbString = preg_replace("/[^0-9]/", '', $matches[0][0]); $count = iconv_strlen($numbString); $rez = str_split($numbString); } if ($count > 3) { $value = implode("", $rez); $condPhone = "JOIN lateral (select manPhoneCode, phoneNumber from ManPhone where ManCode = mp.ManCode and phoneType IN (1,3) AND translate(PhoneNumber,'0' || translate(PhoneNumber,'_0123456789','_'),'0') LIKE '%$value%' order by phoneType desc limit 1) _ManPhone on true"; } else { $condPhone = "$joinPhone"; } $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $fieldAge = <<<"EOT"
(EXTRACT(YEAR FROM age($currDate, pass.DateOfBirth)))
EOT;
$sqlFromWhere = <<<"EOT"
FROM MedPatient AS mp
JOIN Man AS m ON m.ManCode = mp.ManCode
LEFT JOIN ManPassport AS pass ON pass.ManCode = mp.ManCode
$condPhone
WHERE mp.EnterpriseCode = $enterpriseCode
  AND mp.FlagGroup IS NULL
  AND (mp.MedPatientCode = $patientId or 0=$patientId)
  $condSearch
EOT;
$sqlTotal = <<<"EOT"
SELECT
       COUNT(*) AS "total"
$sqlFromWhere
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql = <<<"EOT"
SELECT
        mp.MedPatientCode as "id",
        mp.ManCode as "manId",
        FN_cmCalcAddress(mp.ManCode, 5)::text as "addressDisplay",
        FN_cmCalcAddress(mp.ManCode, 5)::text as "addressReside",
        FN_cmFIO(mp.ManCode)::text as "fio",
        pass.Sex as "sex",
        pass.DateOfBirth as "dateOfBirth",
        mp.PhotoId as "photoId",
        0 as "photoExist",
        _ManPhone.manPhoneCode "phoneId",
        _ManPhone.PhoneNumber "mobilePhone",
        COALESCE(_ManPhone.phoneNumber, (
            SELECT phoneNumber
            FROM manPhone
            WHERE manCode = mp.manCode AND phoneType IN (1, 2, 3)
            ORDER BY manPhoneCode DESC
            LIMIT 1
        )) "phone",
        $fieldAge as "age"
$sqlFromWhere
limit $limit offset $start
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } private function doSetSearchByType($type = null, string $aFioDef = '', string $aFio = '') { $db = $this->db ?: $this->db = connectDb(); $currDate = Date("Ymd"); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $condSearch = null; $aFioDef = ltrim($aFioDef,'='); $aFio = ltrim($aFio,'='); switch ($type) { case 'fio': $aFio = mb_strtolower($aFioDef, 'UTF-8'); $aFio = trim($aFio); $fio = safe_explode(' ', $aFio); $condSearch = "M.SurName LIKE '$fio[0]%'"; if ( isset($fio[1]) && !is_numeric($fio[1]) && $fio[1] !== "") { $condSearch .= " and M.Name LIKE '$fio[1]%'"; } if (isset($fio[2]) && $fio[2] !== "") { $fio[2] = trim(implode(" ", (array_slice($fio, 2)))); $condSearch .= " and M.SecondName LIKE '$fio[2]%'"; } break; case 'phoneNumber': $matches = array(); $condSearch = "mp.ManCode IN (
                        SELECT ManCode
                        FROM ManPhone
                        WHERE phonenumber ILIKE '%' || fn_cmformatphonenumber($aFio) || '%')"; break; case 'number': $matches = array(); $condSearch = " mp.NumberHistorySickness LIKE '%$aFio%' or EXISTS(select 1
                                          from MedBarCode
                                          where BarCode LIKE '%$aFio%'
                                            and ManCode = mp.ManCode and $currDate >= COALESCE(StartDate, 19000101)
                                            and $currDate < COALESCE(StopDate, 30000101) limit 1)"; if (preg_match('/^(\+?375|80)?[\s-]?\(?\d{2}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}$|^\d{3}[\s-]?\d{2}[\s-]?\d{2}$/u', $aFio)) { $condSearch .= " OR mp.ManCode IN (
                        SELECT ManCode
                        FROM ManPhone
                        WHERE phonenumber ILIKE '%' || fn_cmformatphonenumber($aFio) || '%')"; } else if (preg_match('/^\d{7}[A-Z]\d{3}[A-Z]{2}\d$/u', $aFio)) { $condSearch .= " OR mp.ManCode IN (SELECT ManCode
                                                    FROM manpassport where pass.PersonalNumber LIKE '%$aFio%')"; } else if (preg_match('/^[A-ZА-Я]{2}\d{7}$/u', $aFio)) { $condSearch .= " OR mp.ManCode IN (SELECT ManCode
                                                    FROM manpassport pass where pass.PassportNumber LIKE '%$aFio%')"; } else { $condSearch .= " OR mp.ManCode IN (Select ManCode FROM medpatient
                                                        WHERE archivalnumber LIKE '%$aFio%')
                                     OR mp.ManCode IN (Select ManCode FROM medpatient
                                                        WHERE numberhistorysickness LIKE '%$aFio%')"; } break; case '№ak': $aFio != '' ? $condSearch = " mp.NumberHistorySickness LIKE '%$aFio%'" : null; break; case 'numberArchive': $aFio != '' ? $condSearch = " mp.ArchivalNumber LIKE '%$aFio%'" : null; break; case 'nikmo': $aFio != '' ? $condSearch = "EXISTS(select 1
                                          from MedBarCode
                                          where BarCode LIKE '%$aFio%'
                                            and ManCode = mp.ManCode
                                          limit 1)" : null; break; case 'pin': $aFio != '' ? $condSearch = "pass.PersonalNumber LIKE '%$aFio%'" : null; break; case 'serialNumber': $aFio != '' ? $condSearch = "pass.PassportNumber LIKE '%$aFio%'" : null; break; case 'address': $addrText = !empty(trim($aFioDef)) ? trim($aFioDef) : trim($aFio); if (!empty($addrText)) { $conditionsAddress = []; $conditionsAddressReside = []; if (!preg_match('/\d/', $addrText)) { $addrText = $this->addressPrepare($addrText); $placePrefixes = array_map('mb_strtolower', $this->addressPrefixesFetch($db, 'place')); $streetPrefixes = array_map('mb_strtolower', $this->addressPrefixesFetch($db, 'street')); $allPrefixes = array_map('mb_strtolower', array_merge($placePrefixes, $streetPrefixes)); $parts = preg_split('/[\s\.]+/u', $addrText, -1, PREG_SPLIT_NO_EMPTY); $filtered = array_filter($parts, function ($word) use ($allPrefixes) { return !in_array(mb_strtolower($word), $allPrefixes, true); }); $placePhrase = implode(' ', $filtered); $placeSql = <<<SQL
                            SELECT
                                A.Place AS "place",
                                SUBSTRING(:phrase FROM '(' || LOWER(TRIM(REGEXP_REPLACE(A.Place, '\s+', ' '))) || ').*') AS "matched"
                            FROM
                                Address AS A
                                LEFT JOIN Enterprise AS E ON E.CountryCode = A.CountryCode
                            WHERE
                                E.EnterpriseCode = :eId
                                AND :phrase ILIKE TRIM(REGEXP_REPLACE(A.Place, '\s+', ' ')) || '%'
                                AND COALESCE(A.Place, '') <> ''
                            GROUP BY A.Place
                            LIMIT 1
                        SQL;
$placeStmt = $db->prepare($placeSql); $placeStmt->execute([ ':eId' => $enterpriseCode, ':phrase' => $placePhrase, ]); $row = $placeStmt->fetch(); $placeName = null; $matchedPlace = null; if (!empty($row)) { $inputPlaceLower = mb_strtolower(trim($placePhrase)); $dbPlaceLower = mb_strtolower(trim($row['place'])); $pattern = '/^' . preg_quote($dbPlaceLower, '/') . '(?=\s|$)/u'; if (preg_match($pattern, $inputPlaceLower)) { $placeName = trim($row['place']); $placeCleanRe = '/' . preg_quote($placeName, '/') . '[^,\.\s]*/iu'; $placePhrase = trim(preg_replace($placeCleanRe, '', $placePhrase)); } } $street = null; if (!empty($placePhrase)) { $streetSql = <<<SQL
                                SELECT
                                    A.Street AS "street",
                                    SUBSTRING(LOWER(TRIM(REGEXP_REPLACE(A.Street, '\s+', ' '))) FROM '(' || :phrase || ')') AS "matched"
                                FROM
                                    Address AS A
                                    LEFT JOIN Enterprise AS E ON E.CountryCode = A.CountryCode
                                WHERE
                                    E.EnterpriseCode = :eId
                                    AND TRIM(REGEXP_REPLACE(A.Street, '\s+', ' ')) ILIKE '%' || :phrase || '%'
                                    AND COALESCE(A.Street, '') <> ''
                            SQL;
$streetStmt = $db->prepare($streetSql); $streetStmt->execute([ ':eId' => $enterpriseCode, ':phrase' => $placePhrase, ]); $row = $streetStmt->fetch(); if (!empty($row)) { $street = trim($row['street']); } } if (!empty($placeName)) { $conditionsAddress[] = injectSqlParams($db, 'ar.Place ILIKE :place', [':place' => $placeName . '%']); $conditionsAddressReside[] = injectSqlParams($db, 'a.Place ILIKE :place', [':place' => $placeName . '%']); } if (!empty($street)) { $conditionsAddress[] = injectSqlParams($db, 'ar.Street ILIKE :street', [':street' => '%' . $street . '%']); $conditionsAddressReside[] = injectSqlParams($db, 'a.Street ILIKE :street', [':street' => '%' . $street . '%']); } $sqlWhereAddress = !empty($conditionsAddress) ? '(' . implode(') AND (', $conditionsAddress) . ')' : null; $sqlWhereAddressReside = !empty($conditionsAddressReside) ? '(' . implode(') AND (', $conditionsAddressReside) . ')' : null; } else { $addr = $this->addressParse($addrText, $enterpriseCode); if (!is_null($addr)) { if (!empty($addr->place)) { $conditionsAddress[] = injectSqlParams($db, 'ar.Place ILIKE :place', [':place' => $addr->place . '%']); $conditionsAddressReside[] = injectSqlParams($db, 'a.Place ILIKE :place', [':place' => $addr->place . '%']); } if (!empty($addr->street)) { $conditionsAddress[] = injectSqlParams($db, 'ar.Street ILIKE :street', [':street' => '%' . $addr->street . '%']); $conditionsAddressReside[] = injectSqlParams($db, 'a.Street ILIKE :street', [':street' => '%' . $addr->street . '%']); } if (!empty($addr->house)) { $conditionsAddress[] = injectSqlParams($db, 'pass.AddressHouse = :house', [':house' => $addr->house]); $conditionsAddressReside[] = injectSqlParams($db, 'pass.AddressResideHouse = :house', [':house' => $addr->house]); } if (!empty($addr->bldg)) { $conditionsAddress[] = injectSqlParams($db, 'pass.AddressCorps = :bldg', [':bldg' => $addr->bldg]); $conditionsAddressReside[] = injectSqlParams($db, 'pass.AddressResideCorps = :bldg', [':bldg' => $addr->bldg]); } if (!empty($addr->apt)) { $conditionsAddress[] = injectSqlParams($db, 'pass.AddressFlat = :apt', [':apt' => $addr->apt]); $conditionsAddressReside[] = injectSqlParams($db, 'pass.AddressResideFlat = :apt', [':apt' => $addr->apt]); } } $sqlWhereAddress = !empty($conditionsAddress) ? '(' . implode(') AND (', $conditionsAddress) . ')' : null; $sqlWhereAddressReside = !empty($conditionsAddressReside) ? '(' . implode(') AND (', $conditionsAddressReside) . ')' : null; } if (!$sqlWhereAddress && !$sqlWhereAddressReside) { $sqlWhereAddress = injectSqlParams($db, 'ar.Street ILIKE :street', [':street' => '%' . $addrText . '%']); $sqlWhereAddressReside = injectSqlParams($db, 'a.Street ILIKE :street', [':street' => '%' . $addrText . '%']); } $conditions = array_filter([$sqlWhereAddress, $sqlWhereAddressReside]); $sqlWhere = !empty($conditions) ? '(' . implode(') OR (', $conditions) . ')' : null; $condSearch = $sqlWhere; } break; case 'statusCisz': $condSearch = "($aFio = 4 OR COALESCE(CL.Status,0) = ($aFio - 1))"; break; case 'addressCisz': $ciszAddressExpr = "
                (
                    pass.CiszAddressCode IS NOT NULL
                    OR (
                        cr.name IS NOT NULL
                        AND TRIM(cr.name) <> ''
                        AND cr.name NOT LIKE '%Беларус%'
                        AND cr.name <> 'РБ'
                    )
                )"; $ciszAddressResideExpr = "
                (
                    pass.CiszAddressResideCode IS NOT NULL
                    OR (
                        c.name IS NOT NULL
                        AND TRIM(c.name) <> ''
                        AND c.name NOT LIKE '%Беларус%'
                        AND c.name <> 'РБ'
                    )
                )"; switch ($aFio) { case 2: $condSearch = "($ciszAddressExpr AND $ciszAddressResideExpr)"; break; case 3: $condSearch = "(NOT $ciszAddressExpr AND NOT $ciszAddressResideExpr)"; break; case 4: $condSearch = "(NOT $ciszAddressResideExpr)"; break; case 5: $condSearch = "(NOT $ciszAddressExpr)"; break; } break; } return $condSearch; } private function addressCanParse($str) { $str = $this->addressPrepare($str); $re = '/^ ###
                    # населенный_пункт_улица дом
                  (?:(?<place>.+?)\s(?<house>(?:д|д\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+) #
                  $ #
        /iJxu'; $result = preg_match($re, $str, $matches); return $result; } private function addressPrepare($addrText) { $addrText = preg_replace('/([^a-zа-я0-9\.\-]+)/iu', ' ', $addrText); $addrText = trim(preg_replace('/\s+/', ' ', $addrText)); return $addrText; } private function addressParse($str, $enterpriseCode) { $result = (object)[ 'place' => null, 'street' => null, 'house' => null, 'bldg' => null, 'apt' => null, ]; $str = $this->addressPrepare($str); $parsed = false; $db = $this->db ?: $this->db = connectDb(); $re = '/^ ###
                    # населенный_пункт_улица дом корпус квартира
                  (?:(?<place>.+?)\s(?<house>(?:д|д\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+\s(?<bldg>(?:к|к\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+\s(?<apt>(?:кв|кв\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+) #
                  ###
                  | # населенный_пункт_улица дом квартира
                  (?:(?<place>.+?)\s(?<house>(?:д|д\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+\s(?<apt>(?:кв|кв\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+) #
                  ###
                  | # населенный_пункт_улица дом
                  (?:(?<place>.+?)\s(?<house>(?:д|д\.)?\s{0,1}\d{1}[0-9a-zа-я]*)+) #
                  $ #
        /iJxu'; if (preg_match($re, $str, $matches)) { $place = trim($matches['place']); if (!empty($place)) { $placePrefixes = $this->addressPrefixesFetch($db, 'place'); $streetPrefixes = $this->addressPrefixesFetch($db, 'street'); $placePhrase = mb_strtolower($place); $placePrefixesRe = '/' . implode('|', array_map(function($p) { return preg_quote($p, '/') . '[\.\s]{1}'; }, $placePrefixes)) . '/iu'; $streetPrefixesRe = '/' . implode('|', array_map(function($p) { return preg_quote($p, '/') . '[\.\s]{1}'; }, $streetPrefixes)) . '/iu'; $streetSplitRe = $streetPrefixesRe; $parts = preg_split($streetSplitRe, $placePhrase); if (is_array($parts) && count($parts) == 2) { $placeCandidate = trim($parts[0]); $streetCandidate = trim($parts[1]); $placeCandidate = trim(preg_replace($placePrefixesRe, '', $placeCandidate)); $result->place = $placeCandidate; $result->street = $streetCandidate; } else { $placePhrase = preg_replace($placePrefixesRe, '', $placePhrase); $placePhrase = preg_replace($streetPrefixesRe, '', $placePhrase); $placePhrase = trim($placePhrase); $placeSql = <<<SQL
                        SELECT
                            A.Place "place",
                            SUBSTRING(:phrase FROM '(' || LOWER(TRIM(REGEXP_REPLACE(A.Place, '\s+', ' '))) || ').*') "matched"
                        FROM
                            Address AS A
                            LEFT JOIN Enterprise AS E ON E.CountryCode = A.CountryCode
                        WHERE
                            (E.EnterpriseCode = :eId) AND
                            (:phrase ILIKE TRIM(REGEXP_REPLACE(A.Place, '\s+', ' ')) || '%')
                            AND (COALESCE(A.Place, '') <> '')
                        GROUP BY A.Place
                        LIMIT 1
                        SQL;
$placeStmt = $db->prepare($placeSql); $placeStmt->execute([ ':eId' => $enterpriseCode, ':phrase' => $placePhrase, ]); $row = $placeStmt->fetch(); if (!empty($row)) { $inputPlaceLower = mb_strtolower(trim($placePhrase)); $dbPlaceLower = mb_strtolower(trim($row['place'])); $matchedPlace = $row['matched']; $pattern = '/^' . preg_quote($dbPlaceLower, '/') . '(?=\s|$)/u'; if (preg_match($pattern, $inputPlaceLower)) { $placeName = trim($row['place']); $placeCleanRe = '/' . preg_quote($placeName, '/') . '[^,\.\s]*/iu'; $placePhrase = trim(preg_replace($placeCleanRe, '', $placePhrase)); $result->place = $placeName; } } if (!empty($placePhrase)) { $streetSql = <<<SQL
                            SELECT
                                A.Street "street",
                                SUBSTRING(LOWER(TRIM(REGEXP_REPLACE(A.Street, '\s+', ' '))) FROM '(' || :phrase || ')') "matched"
                            FROM
                                Address AS A
                                LEFT JOIN Enterprise AS E ON E.CountryCode = A.CountryCode
                            WHERE
                                (E.EnterpriseCode = :eId) AND
                                (TRIM(REGEXP_REPLACE(A.Street, '\s+', ' ')) ILIKE '%' || :phrase || '%')
                                AND (COALESCE(A.Street, '') <> '')
                            SQL;
$placeStmt = $db->prepare($streetSql); $placeStmt->execute([ ':eId' => $enterpriseCode, ':phrase' => $placePhrase, ]); $row = $placeStmt->fetch(); if (!empty($row)) { $result->street = $row['matched']; } else { return null; } } } $parsed = !empty($result->place) || !empty($result->street); } if ($parsed) { $result->house = $this->addressPrefixesRemove($matches['house'], ['д']); $result->bldg = $this->addressPrefixesRemove($matches['bldg'], ['к']); $result->apt = $this->addressPrefixesRemove($matches['apt'], ['кв']); } } return $parsed ? $result : null; } private function addressPrefixesFetch($db, $type) { $result = []; switch($type) { case 'place': $sql = <<<SQL
                    SELECT DISTINCT
                        LOWER(RTRIM(ShortName, '.')) "name"
                    FROM
                        AddressPlaceType
                    SQL;
break; case 'street': $sql = <<<SQL
                    SELECT DISTINCT
                        LOWER(RTRIM(ShortName, '.')) "name"
                    FROM
                        AddressStreetType
                    SQL;
break; } $stmt = $db->prepare($sql); $stmt->execute(); $values = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0); $result = array_filter($values); return $result; } private function addressPrefixesRemove($str, $prefixes = []) { $result = $str; foreach ($prefixes as $prefix) { $prefixWithDot = $prefix . '.'; $prefixWithSpace = $prefix . ' '; if (strpos($result, $prefixWithDot) === 0) { $result = substr($result, strlen($prefixWithDot)); } else if (strpos($result, $prefixWithSpace) === 0) { $result = substr($result, strlen($prefixWithSpace)); } } return trim($result); } private function doSetAmbulanceCardSearch($params, $arr = []) { $ambulanceDateFrom = isUndefinedProperty($params,'ambulanceDateFrom'); $ambulanceDateTo = isUndefinedProperty($params,'ambulanceDateTo'); $ambulanceDiagnosIds = isUndefinedProperty($params,'ambulanceDiagnosIds'); $ambluanceDoctorId = isUndefinedProperty($params,'ambluanceDoctorId'); $ambulanceStudyId = isUndefinedProperty($params,'ambulanceStudyId'); $returnStr = ''; foreach ($arr as $name) { switch ($name) { case 'isVisitPatient': $dateStr = $ambulanceDateFrom ? " AND MedVisit.visitDate >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " AND MedVisit.visitDate <= '" . $ambulanceDateTo . "' " : ''; $diagnosisStr = $ambulanceDiagnosIds ? ' AND MedVisitPatient.medDiagnosisCode IN (' . $ambulanceDiagnosIds . ') ' : ''; $doctorStr = $ambluanceDoctorId ? ' AND MedVisit.medDoctorCode = ' . $ambluanceDoctorId . ' ' : ''; $condSearch = <<<EOT
                                          EXISTS(SELECT 1
                                          FROM MedVisitPatient
                                          LEFT JOIN MedVisit ON MedVisitPatient.medVisitCode = MedVisit.medVisitCode
                                          WHERE medPatientCode = mp.medPatientCode
                                          $dateStr
                                          $diagnosisStr
                                          $doctorStr
                                          LIMIT 1)
                                EOT;
$returnStr = $returnStr ? $returnStr . ' OR ' . $condSearch : $condSearch; break; case 'isResearch': $dateStr = $ambulanceDateFrom ? " AND MLS.dirTime >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " AND MLS.dirTime <= '" . $ambulanceDateTo . "' " : ''; $diagnosisStr = $ambulanceDiagnosIds ? ' AND MLS.medDiagnosisCode IN (' . $ambulanceDiagnosIds . ') ' : ''; $doctorStr = $ambluanceDoctorId ? ' AND MLS.medDoctorCode = ' . $ambluanceDoctorId . ' ' : ''; $studyStr = $ambulanceStudyId ? ' AND MLS.medStudyCode = ' . $ambulanceStudyId . ' ' : ''; $condSearch = <<<EOT
                                          EXISTS(SELECT 1
                                          FROM MedLogStudy MLS
                                          WHERE MLS.medPatientCode = mp.medPatientCode
                                          $dateStr
                                          $diagnosisStr
                                          $doctorStr
                                          $studyStr
                                          LIMIT 1)
                                EOT;
$returnStr = $returnStr ? $returnStr . ' OR ' . $condSearch : $condSearch; break; case 'isAmbulanceOper': $dateStr = $ambulanceDateFrom ? " AND MAO.operationDate >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " AND MAO.operationDate <= '" . $ambulanceDateTo . "' " : ''; $diagnosisStr = $ambulanceDiagnosIds ? ' AND MAO.medDiagnosisCode IN (' . $ambulanceDiagnosIds . ') ' : ''; $doctorStr = $ambluanceDoctorId ? ' AND MAO.medDoctorCode = ' . $ambluanceDoctorId . ' ' : ''; $condSearch = <<<EOT
                                          EXISTS(SELECT 1
                                          FROM MedAmbulOperation MAO
                                          WHERE MAO.medPatientCode = mp.medPatientCode
                                          $dateStr
                                          $diagnosisStr
                                          $doctorStr
                                          LIMIT 1)
                                EOT;
$returnStr = $returnStr ? $returnStr . ' OR ' . $condSearch : $condSearch; break; case 'isDayHospital': $dateStr = $ambulanceDateFrom ? " AND MDH.startDate >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " AND MDH.finishDate <= '" . $ambulanceDateTo . "' " : ''; $diagnosisStr = $ambulanceDiagnosIds ? ' AND MDH.medDiagnosisCode IN (' . $ambulanceDiagnosIds . ') ' : ''; $doctorStr = $ambluanceDoctorId ? ' AND MDH.medDoctorCode = ' . $ambluanceDoctorId . ' ' : ''; $condSearch = <<<EOT
                                          EXISTS(SELECT 1
                                          FROM MedDayHospital MDH
                                          WHERE MDH.medPatientCode = mp.medPatientCode
                                          $dateStr
                                          $diagnosisStr
                                          $doctorStr
                                          LIMIT 1)
                                EOT;
$returnStr = $returnStr ? $returnStr . ' OR ' . $condSearch : $condSearch; break; case 'isInspection': $dateStr = $ambulanceDateFrom ? " AND I.inspectDate >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " AND I.inspectDate <= '" . $ambulanceDateTo . "' " : ''; $doctorStr = $ambluanceDoctorId ? ' AND I.medDoctorCode = ' . $ambluanceDoctorId . ' ' : ''; if ($dateStr || $doctorStr) { $condSearch = <<<EOT
                                          EXISTS(SELECT 1
                                          FROM MedInspect I
                                          WHERE I.medPatientCode = mp.medPatientCode
                                          $dateStr
                                          $doctorStr
                                          LIMIT 1)
                                EOT;
$returnStr = $returnStr ? $returnStr . ' OR ' . $condSearch : $condSearch; } else { throw new Exception('Для фильтрации по медосмотрам нужно указать период создания документов (записей) и/или лечащего врача'); } break; } } return $returnStr; } protected function readMAPPatients($params) { $db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $isLight = (int) in_array(getSessionValue('mapWebTaskCode'), [524, 526, 527, 528, 530, 531]); $patientId = isEmptyProperty($params, 'patientId', isEmptyProperty($params, 'id', 0)); $excludeId = isEmptyProperty($params, 'excludeId', -1); $currDate = Date("Ymd"); $allCondSearch = ""; $cipher = ""; $aFio = ""; $search = []; $filters = []; $filterstr = isEmptyProperty($params, 'filter'); $search = array(); if (isset($filterstr)) { $filterstr = base64_decode($filterstr); $index = []; $strFio = ''; $filters = safe_json_decode($filterstr, false); $objSearch = reset($filters); if ($objSearch->property === 'search' && trim($objSearch->value) === '') { $root = new stdClass(); $root->total = 0; $root->items = []; return $root; } $isSearchAllPlace = true; $ambulanceSearchParams = new stdClass(); foreach ($filters as $key => $searchV) { switch ($searchV->property) { case '"datePeriodFrom"': $ambulanceSearchParams->ambulanceDateFrom = dbDate($searchV->inputValue); $index[] = $key; break; case '"datePeriodTo"': $ambulanceSearchParams->ambulanceDateTo = dbDate($searchV->inputValue); $index[] = $key; break; case '"diagnosisId"': $ambulanceSearchParams->ambulanceDiagnosIds = implode(',',$searchV->inputValue); $index[] = $key; break; case '"doctor"': $ambulanceSearchParams->ambluanceDoctorId = $searchV->inputValue; $index[] = $key; break; case '"study"': $ambulanceSearchParams->ambulanceStudyId = $searchV->inputValue; $index[] = $key; break; } } foreach ($filters as $key => $searchV) { switch ($searchV->property) { case 'search': case '"search"': $search = $searchV->value ? safe_explode('|', $searchV->value) : $search; $index[] = $key; break; case '"sectorAddId"': $sectorAddVal = $searchV->value; break; case '"fio"': $condSearch = $this->doSetSearchByType('fio', $searchV->value); $strFio = $searchV->inputValue; break; case '"address"': $condSearch = $this->doSetSearchByType('address',$strFio, $searchV->value); break; case '"identNumber"': $condSearch = $this->doSetSearchByType('pin',$strFio, $searchV->value); break; case '"serialNumber"': $condSearch = $this->doSetSearchByType('serialNumber',$strFio, $searchV->value); break; case '"numberCard"': $condSearch = $this->doSetSearchByType('№ak',$strFio, $searchV->value); break; case '"numberArchive"': $condSearch = $this->doSetSearchByType('numberArchive',$strFio, $searchV->value); break; case '"numberIKMO"': $condSearch = $this->doSetSearchByType('nikmo',$strFio, $searchV->value); break; case '"statusCisz"': $condSearch = $this->doSetSearchByType('statusCisz',$strFio, $searchV->value); break; case '"addressCisz"': $condSearch = $this->doSetSearchByType('addressCisz',$strFio, $searchV->value); break; case '"isVisitPatient"': if ($searchV->inputValue) { $isSearchAllPlace = false; $condSearch = $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isVisitPatient']); } else { $index[] = $key; } break; case '"isResearch"': if ($searchV->inputValue) { $isSearchAllPlace = false; $condSearch = $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isResearch']); } else { $index[] = $key; } break; case '"isAmbulanceOper"': if ($searchV->inputValue) { $isSearchAllPlace = false; $condSearch = $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isAmbulanceOper']); } else { $index[] = $key; } break; case '"isDayHospital"': if ($searchV->inputValue) { $isSearchAllPlace = false; $condSearch = $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isDayHospital']); } else { $index[] = $key; } break; case '"isInspection"': if ($searchV->inputValue) { $isSearchAllPlace = false; $condSearch = $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isInspection']); } else { $index[] = $key; } break; case '"list"': if ($searchV->inputValue !== 2) { $ambulanceDateFrom = isUndefinedProperty($ambulanceSearchParams, 'ambulanceDateFrom'); $ambulanceDateTo = isUndefinedProperty($ambulanceSearchParams, 'ambulanceDateTo'); $dateStr = $ambulanceDateFrom ? " and MH.startDate >= '" . $ambulanceDateFrom . "' " : ''; $dateStr .= $ambulanceDateTo ? " and MH.finishDate <= '" . $ambulanceDateTo . "' " : ''; $isExists = $searchV->inputValue === 0 ? 'EXISTS' : 'NOT EXISTS'; $condSearch = <<<EOT
                                          $isExists(select 1
                                          from MedHospital MH
                                          where MH.medPatientCode = mp.medPatientCode
                                          $dateStr
                                          limit 1)
                            EOT;
} else { $index[] = $key; } break; } if (isset($condSearch)) { $index[] = $key; $allCondSearch .= ' AND (' . $condSearch . ')'; $condSearch = null; } } foreach ($index as $key) { unset($filters[$key]); } if ($isSearchAllPlace) { $ambulanceDateFrom = isUndefinedProperty($ambulanceSearchParams, 'ambulanceDateFrom'); $ambulanceDateTo = isUndefinedProperty($ambulanceSearchParams, 'ambulanceDateTo'); $ambulanceDiagnosIds = isUndefinedProperty($ambulanceSearchParams,'ambulanceDiagnosIds'); $ambluanceDoctorId = isUndefinedProperty($ambulanceSearchParams,'ambluanceDoctorId'); if ($ambulanceDateFrom || $ambulanceDateTo || $ambulanceDiagnosIds || $ambluanceDoctorId) { $allCondSearch .= ' AND (' . $this->doSetAmbulanceCardSearch($ambulanceSearchParams,['isVisitPatient', 'isResearch', 'isAmbulanceOper', 'isDayHospital', 'isInspection']) . ')'; } } } else if (!$patientId) { $root = new stdClass(); $root->total = 0; $root->items = []; return $root; } foreach ($search as $aFioDef) { $aFio = trim($aFioDef); $aFio = str_replace('-', ' ', $aFio); $isAddress = $this->addressCanParse($aFioDef); if ($params->searchType === 'auto') { $chChar = false; for ($i = 0; $i < safe_strlen($aFio); $i++) { if (preg_match('/^[A-ZА-Я]{2}\d{7}$/u', $aFio)) { $params->searchType = 'number'; break; } else if (preg_match('/^\d{7}[A-Z]\d{3}[A-Z]{2}\d$/u', $aFio)) { $params->searchType = 'number'; break; } else if (preg_match('/^(\+?375|80)?[\s-]?\(?\d{2}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}$|^\d{3}[\s-]?\d{2}[\s-]?\d{2}$/u', $aFio)) { $params->searchType = 'number'; break; } else if (is_numeric($aFio)) { $params->searchType = 'number'; break; } else if ($isAddress) { $params->searchType = 'address'; break; } else if ((ord($aFio[$i]) >= 30 && ord($aFio[$i]) <= 255) && !is_numeric($aFio[$i]) && $aFio[$i] !== '.') { $chChar = true; if ($chChar && $params->searchType === 'auto') { $params->searchType = 'fio'; } } } $condSearch = $this->doSetSearchByType($params->searchType, $aFioDef, $aFio); } } !isset($condSearch) ? $condSearch = '1 = 1' : null; $allCondSearch .= ' AND (' . $condSearch . ') AND (COALESCE(mp.medPatientCode, -1) <> ' . $excludeId . ') '; $filter = $this->getFilter($filters); $sort = $this->getSort(isEmptyProperty($params, 'sort'), isEmptyProperty($params, 'dir')); $sort = str_replace("fio", "fios", $sort); $sort = str_replace("addressDisplay", '"addressDisplayf"', $sort); $sort = str_replace('""', '"', $sort); $addressSortField = ""; if (stristr($sort, '"addressDisplayf"') != FALSE) { $addressSortField = <<<"EOT"
    CASE
          WHEN pass.AddressResideCode is null THEN
                  ( select concat(COALESCE(asa_trim(pt.ShortName), E'' ),COALESCE(asa_trim(a.Place), E'' ) ,', ', COALESCE(asa_trim(st.ShortName), E'' ) , '.', COALESCE(asa_trim(a.Street), E'' ),
        ' д.', lpad(COALESCE(asa_trim(pass.AddressHouse), E'' ), 8, ' ') , ' к.', lpad(COALESCE(asa_trim(pass.AddressCorps), E'' ), 4, ' ') , ' кв.', lpad(COALESCE(asa_trim(pass.AddressFlat), E'' ), 9, ' ')
        )

   from Address AS a left outer join Country AS c on c.CountryCode = a.CountryCode
    left outer join AddressPlaceType AS pt on pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
    left outer join AddressStreetType AS st on st.AddressStreetTypeCode = a.AddressStreetTypeCode
   where a.AddressCode = pass.AddressCode LIMIT 1 )
          ELSE
                  ( select concat(COALESCE(asa_trim(pt.ShortName), E'' ),COALESCE(asa_trim(a.Place), E'' ) ,', ', COALESCE(asa_trim(st.ShortName), E'' ) , '.', COALESCE(asa_trim(a.Street), E'' ),
        ' д.', lpad(COALESCE(asa_trim(pass.AddressResideHouse), E'' ), 8, ' ') , ' к.', lpad(COALESCE(asa_trim(pass.AddressResideCorps), E'' ), 4, ' ') , ' кв.', lpad(COALESCE(asa_trim(pass.AddressResideFlat), E'' ), 9, ' ')
        )

   from Address AS a left outer join Country AS c on c.CountryCode = a.CountryCode
    left outer join AddressPlaceType AS pt on pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
    left outer join AddressStreetType AS st on st.AddressStreetTypeCode = a.AddressStreetTypeCode
   where a.AddressCode = pass.AddressResideCode LIMIT 1 )
          END as "addressDisplayf",
EOT;
} $page = isEmptyProperty($params, 'page', 1); $limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $limit = $limit >= self::MAX_PAGE_SIZE ? self::MAX_PAGE_SIZE : $limit; $start = (int) (($page - 1) * $limit); $start = $start < 0 ? 0 : $start; $fieldIsActive = <<<"EOT"
(CASE
    WHEN COALESCE(mp.FinishDate, 30000101)::date = 30000101 OR mp.finishDate > DATE(NOW()) THEN 1
    ELSE 0
END)
EOT;
$fieldAge = <<<"EOT"
(EXTRACT(YEAR FROM age($currDate, pass.DateOfBirth)))
EOT;
$inFilterFields = array( "\"isActive\"", "\"age1\"", "\"age2\"", "\"sectorId\"", "\"sectorAddId\"", "\"contingentId\"", "\"startDate\"", "\"finishDate\"", "\"attributes\"", "\"dateBirthday\"" ); $outFilterFields = array( $fieldIsActive, $fieldAge, $fieldAge, "mp.MedPatientGroupCode", "mpag.MedPatientGroupCode", "mpc.MedPrivelegeCode", "mp.StartDate", "mp.FinishDate", "mp.Attributes", "pass.DateOfBirth" ); $filter = str_replace($inFilterFields, $outFilterFields, $filter); $selectedPatientIds = isUndefinedProperty($params, 'selectedPatientIds'); $sqlUnion = ''; $sqlWhereIfUnion = ''; if ($selectedPatientIds) { $sqlUnion = <<<EOT
                UNION ALL
                SELECT
                    mp.medPatientCode                               "id",
                    mp.manCode                                      "manId",
                    mp.personDataPermissionDate         "personDataPermissionDate",
                    CASE WHEN (coalesce(mp.Attributes,0) & 1) = 1 THEN 1 ELSE 0 END "printed",
                    CASE WHEN (coalesce(mp.Attributes,0) & 2) = 2 THEN 1 ELSE 0 END "unknown",
                    $addressSortField
                    TRIM(m.surName || ' ' || m.name || ' ' || m.secondname)
                                                                    "fios",
                    m.surname                                       "surname",
                    m.name                                          "name",
                    m.secondname                                    "secondName",
                    mp.cipher                                       "cipher",
                    mp.photoId                                      "photoId",
                    0                                               "photoExist",
                    STRING_AGG(DISTINCT(mpgadd.name::TEXT), '; ')   "sectorAdd",
                    STRING_AGG(DISTINCT(mpgadd.medPatientGroupCode::TEXT), ',')
                                                                    "sectorAddIds",
                    mp.numberHistorySickness                        "numberHistorySickness",
                    mp.medPatientGroupCode                          "sectorId",
                    mp.archivalNumber                               "archivalNumber",
                    mp.medPatientPolyclinicCode                     "clinicId",
                    mp.outReasonEx                                  "outReason",
                    mpg.name                                        "sector",
                    mpg.medDoctorCode                               "sectorDoctorId",
                    CASE
                        WHEN mp.startDate = 19000101 THEN null
                        ELSE mp.startDate
                    END::DATE                                       "startDate",
                    CASE
                        WHEN mp.finishDate = 30000101 THEN null
                        ELSE mp.finishDate
                    END::DATE                                       "finishDate",
                    pass.manPassportCode                            "manPassportId",
                    pass.addressCode                                "addressId",
                    pass.addressText                                "addressText",
                    pass.CiszAddressCode                            "addressCiszCode",
                    pass.addressHouse                               "addressHouse",
                    pass.addressCorps                               "addressCorps",
                    pass.addressFlat                                "addressFlat",
                    pass.zipcode                                    "addressZipcode",
                    MAX(c.name)                                     "country",
                    pass.addressResideCode                          "addressResideId",
                    pass.addressResideText                          "addressResideText",
                    pass.CiszAddressResideCode                      "addressResideCiszCode",
                    pass.addressResideHouse                         "addressResideHouse",
                    pass.addressResideCorps                         "addressResideCorps",
                    pass.addressResideFlat                          "addressResideFlat",
                    pass.zipcodeReside                              "addressResideZipcode",
                    MAX(cr.name)                                    "countryReside",
                    pass.sex                                        "sex",
                    pass.dateOfBirth                                "dateOfBirth",
                    pass.passportNumber                             "passportNumber",
                    pass.authority                                  "authority",
                    pass.dateOfIssue                                "dateOfIssue",
                    pass.dateOfExpiry                               "dateOfExpiry",
                    pass.placeOfBirth                               "placeOfBirth",
                    pass.personalNumber                             "personalNumber",
                    pass.manPassportKindCode                        "passportKindId",
                    pass.alien                                      "alien",
                    MAX(mpk.name)                                   "passportKindName",
                    $fieldIsActive                                  "isActive",
                    $fieldAge                                       "age",
                    TRUE                                            "isSelected",
                    /* ulwp.Value as "workPlaceRem" */
                    (
                        SELECT inspectDate
                        FROM medInspect
                        WHERE inspectDate IS NOT NULL AND medPatientCode = mp.medPatientCode
                        ORDER BY inspectDate DESC
                        LIMIT 1
                    )                                               "lastDispensaryExaminationDate",
                    (
                        SELECT STRING_AGG(pr.name, ', ')
                        FROM medPatientCard pc
                        JOIN medPrivelege pr ON pr.medPrivelegeCode = pc.medPrivelegeCode
                        WHERE pc.medPatientCode = mp.medPatientCode
                            AND ('NOW'::TIMESTAMP) BETWEEN pc.startDate AND COALESCE(pc.finishDate, 30000101)
                    )                                               "privileges",
                    (
                        SELECT STRING_AGG(PR.Name, ', ')
                        FROM MedPatientCard PC
                        JOIN MedPrivelege PR ON PR.MedPrivelegeCode = PC.MedPrivelegeCode
                        LEFT JOIN MedPrivelegeFeature MPF ON MPF.MedPrivelegeCode = PR.MedPrivelegeCode
                        WHERE PC.MedPatientCode = MP.MedPatientCode
                            AND ('NOW'::TIMESTAMP) BETWEEN PC.StartDate AND COALESCE(PC.FinishDate, 30000101)
                            AND MPF.MedPrivelegeFeatureCode IS NOT NULL
                    )                                               "privilegesToDisplay",        -- Контингенты, выбранные для отображения в справочнике Статусы пациентов
                    MT.Note                                         "patientNote",                -- Примечание
                    mp.JSON AS "json",
                    mp.Anonymous AS "anonymous"
                    MAX(COALESCE(CL.Status,0)) AS "ciszState",
                       CASE
                    WHEN pass.CiszAddressCode IS NOT NULL
                         OR (
                              MAX(cr.name) IS NOT NULL
                              AND TRIM(MAX(cr.name)) <> ''
                              AND MAX(cr.name) NOT ILIKE '%беларус%'
                              AND MAX(cr.name) <> 'РБ'
                         )
                    THEN true
                    ELSE false
                        END AS "isCiszAddress",
                        CASE
                    WHEN pass.CiszAddressResideCode IS NOT NULL
                         OR (
                              MAX(c.name) IS NOT NULL
                              AND TRIM(MAX(c.name)) <> ''
                              AND MAX(c.name) NOT ILIKE '%беларус%'
                              AND MAX(c.name) <> 'РБ'
                         )
                    THEN true
                    ELSE false
                        END AS "isCiszAddressReside",
                    CASE WHEN MAX(cr.name) IS NOT NULL AND TRIM(MAX(cr.name)) <> '' AND MAX(cr.name) NOT ILIKE '%беларус%' AND MAX(cr.name) <> 'РБ'
                    THEN true ELSE false END AS "isForeignAddress",
                    CASE WHEN MAX(c.name) IS NOT NULL AND TRIM(MAX(c.name)) <> '' AND MAX(c.name) NOT ILIKE '%беларус%' AND MAX(c.name) <> 'РБ'
                    THEN true ELSE false END AS "isForeignAddressReside"
                FROM medPatient mp
                JOIN Man m ON m.ManCode = mp.manCode
                LEFT JOIN manPassport pass ON pass.manCode = mp.manCode
                LEFT JOIN manPassportKind mpk ON mpk.manPassportKindCode = pass.manPassportKindCode
                LEFT JOIN medPatientGroup mpg ON mpg.medPatientGroupCode = mp.medPatientGroupCode
                LEFT JOIN medPatientAddGroup mpag ON mpag.medPatientCode = mp.medPatientCode
                LEFT JOIN medPatientGroup mpgadd ON mpgadd.medPatientGroupCode = mpag.medPatientGroupCode
                LEFT JOIN medPatientInfo mpi ON mpi.medPatientCode = mp.medPatientCode
                LEFT JOIN address a ON a.addressCode = pass.addressResideCode
                LEFT JOIN country c ON c.countryCode = a.countryCode
                LEFT JOIN address ar ON ar.addressCode = pass.addressCode
                LEFT JOIN country cr ON cr.countryCode = ar.countryCode
                LEFT JOIN addressPlaceType pt ON pt.addressPlaceTypeCode = a.addressPlaceTypeCode
                LEFT JOIN addressStreetType st ON st.addressStreetTypeCode = a.addressStreetTypeCode
                LEFT JOIN addressPlaceType ptr ON ptr.addressPlaceTypeCode = ar.addressPlaceTypeCode
                LEFT JOIN addressStreetType strr ON strr.addressStreetTypeCode = ar.addressStreetTypeCode
                LEFT JOIN medPatientCard mpc ON mpc.medPatientCode = mp.medPatientCode AND
                                                    $currDate BETWEEN mpc.startDate AND COALESCE(mpc.finishDate, 30000101)::DATE
                LEFT JOIN ManTask MT ON MT.ManCode = mp.ManCode AND MT.EnterpriseCode = $enterpriseCode AND MT.TypeTask = 12
                LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
                WHERE mp.enterpriseCode = $enterpriseCode
                    AND mp.medPatientCode IN ($selectedPatientIds)
                GROUP BY mp.medPatientCode, mpg.medPatientGroupCode, pass.manPassportCode, m.surname, m.name, m.secondname, MT.Note
EOT;
$sqlWhereIfUnion = " AND mp.medPatientCode NOT IN ($selectedPatientIds) "; } $sqlFromWhere = <<<"EOT"
FROM MedPatient AS mp
JOIN Man AS m ON m.ManCode = mp.ManCode
LEFT JOIN ManPassport AS pass ON pass.ManCode = mp.ManCode
LEFT JOIN ManPassportKind AS mpk ON mpk.ManPassportKindCode = pass.ManPassportKindCode
LEFT JOIN MedPatientGroup AS mpg ON mpg.MedPatientGroupCode = mp.MedPatientGroupCode
LEFT JOIN MedPatientAddGroup AS mpag ON mpag.MedPatientCode = mp.MedPatientCode
LEFT JOIN MedPatientGroup AS mpgadd ON mpgadd.MedPatientGroupCode = mpag.MedPatientGroupCode
LEFT JOIN MedPatientInfo AS mpi ON mpi.MedPatientCode = mp.MedPatientCode
/* LEFT JOIN UniList AS ulwp ON ulwp.UniListCode = mpi.UniListWorkPlaceCode */
LEFT JOIN Address AS a ON a.AddressCode = pass.AddressResideCode
LEFT JOIN Country AS c ON c.CountryCode = a.CountryCode
LEFT JOIN Address AS ar ON ar.AddressCode = pass.AddressCode
LEFT JOIN Country AS cr ON cr.CountryCode = ar.CountryCode
LEFT JOIN AddressPlaceType AS pt ON pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
LEFT JOIN AddressStreetType AS st ON st.AddressStreetTypeCode = a.AddressStreetTypeCode
LEFT JOIN AddressPlaceType AS ptr ON ptr.AddressPlaceTypeCode = ar.AddressPlaceTypeCode
LEFT JOIN AddressStreetType AS strr ON strr.AddressStreetTypeCode = ar.AddressStreetTypeCode
/* Подтормаживает, отдельные подзапросы справляются быстрее
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 1
                   ORDER BY ManPhoneCode DESC LIMIT 1) phone on TRUE
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 3
                   ORDER BY ManPhoneCode DESC LIMIT 1) phoneMobile on TRUE
LEFT JOIN LATERAL (SELECT ManPhoneCode, PhoneNumber, Note
                   FROM ManPhone
                   WHERE ManCode = mp.ManCode
                     AND PhoneType = 4
                   ORDER BY ManPhoneCode DESC LIMIT 1) mail on TRUE
*/
LEFT JOIN MedPatientCard AS mpc ON mpc.MedPatientCode = mp.MedPatientCode AND
                                   $currDate BETWEEN mpc.StartDate AND COALESCE(mpc.FinishDate, 30000101)::date
LEFT JOIN ManTask MT ON MT.ManCode = mp.ManCode AND MT.EnterpriseCode = $enterpriseCode AND MT.TypeTask = 12
LEFT JOIN CiszLink CL ON CL.TableName = 'MedPatient' AND CL.PrimaryKey = MP.MedPatientCode AND CL.Type = 1
WHERE mp.EnterpriseCode = $enterpriseCode
    $sqlWhereIfUnion
    and ($isLight = 1
        or ($isLight = 0
            /* хоть один участок доступен пользователю */
            and (mp.MedPatientGroupCode in (select gc from pg_)
                or (mpgadd.MedPatientGroupCode is not null
                    and mpgadd.MedPatientGroupCode in (select gc from pg_)
                )
                OR MP.MedPatientGroupCode IS NULL
            )
        )
    )
  AND mp.FlagGroup IS NULL
  AND (mp.MedPatientCode = $patientId or 0 = $patientId)
  AND ($filter)
  $allCondSearch
GROUP BY mp.MedPatientCode, mpg.MedPatientGroupCode, pass.ManPassportCode , m.SurName , m.Name , m.SecondName, MT.Note
EOT;
$sqlTotal = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
SELECT
       COUNT(*) AS "total"
FROM (SELECT DISTINCT mp.MedPatientCode
$sqlFromWhere) AS t
EOT;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute(); $total = (int) fetchValue($stmtTotal, 'total'); $start = $total < $start ? 0 : $start; $sql = <<<EOT
            WITH pg_ AS (
                SELECT MedPatientGroupCode gc
                FROM MedPatientGroup
                WHERE EnterpriseCode = $enterpriseCode
                    AND fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
            )
            SELECT  t.*,
                FN_cmCalcAddress(t."manId", 0)::text as "address",
                FN_cmCalcAddress(t."manId", 1)::text as "addressReside",
                FN_cmFIO(t."manId")::text as "fio",
                (SELECT string_agg(eb.Name::text, '; ' ORDER BY eb.Name)
                FROM MedPatientBusy mb
                LEFT JOIN Enterprise as eb ON mb.EnterpriseCode = eb.EnterpriseCode
                WHERE mb.MedPatientCode = t.id
                AND $currDate BETWEEN mb.StartDate AND mb.FinishDate) as "workPlace",
                (SELECT string_agg(ud.Value::text, '; ' ORDER BY ud.Value)
                FROM MedPatientBusy mb
                LEFT JOIN Enterprise as eb ON mb.EnterpriseCode = eb.EnterpriseCode
                LEFT JOIN UniDict as ud ON ud.UniDictCode = COALESCE(mb.UniDictCodeProfession,0) and ud.DictNumber = 19
                WHERE mb.MedPatientCode = t.id
                AND $currDate BETWEEN mb.StartDate AND mb.FinishDate) as "profession",
                FN_medPatientPictureList(t.id, NULL, 2, 1) as "shortStatesList",
                FN_medPatientPictureList(t.id, NULL) as "fullStatesList",
                FN_cmCalcAddress(t."manId", 5)::text as "addressDisplay",
                random() "random",
                (select string_agg((concat(case
                    when mpn.phoneType=1 then 'д.'
                    when mpn.phoneType=2 then 'р.'
                    when mpn.phoneType=3 then 'м.'
                    when mpn.phoneType=4 then 'п.'
                else null
                    end, mpn.phoneNumber))::text, ', ' ) from manphone AS mpn where mpn.ManCode=t."manId") as contact,
                (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType is not distinct from (SELECT CASE WHEN EXISTS (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 1) THEN 1 ELSE null END) ORDER BY ManPhoneCode DESC LIMIT 1) as "phoneId",
                (SELECT PhoneNumber FROM ManPhone WHERE ManCode = t."manId" AND PhoneType is not distinct from (SELECT CASE WHEN EXISTS (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 1) THEN 1 ELSE null END) ORDER BY ManPhoneCode DESC LIMIT 1) as "phone",
                (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 2 ORDER BY ManPhoneCode DESC LIMIT 1) as "workPhoneId",
                (SELECT PhoneNumber FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 2 ORDER BY ManPhoneCode DESC LIMIT 1) as "workPhone",
                (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhoneId",
                (SELECT PhoneNumber FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhone",
                (SELECT Note FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhoneOperator",
                (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1) as "mailId",
                (SELECT PhoneNumber FROM ManPhone WHERE ManCode = t."manId" AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1) as "mail",
                (SELECT string_agg(pg.Name::text, '; ' ORDER BY pg.Name)
                FROM MedPatientAddGroup AS MPAD
                JOIN MedPatientGroup AS pg ON pg.MedPatientGroupCode = MPAD.MedPatientGroupCode
                WHERE MPAD.MedPatientCode = t.id
                    AND COALESCE(pg.ShowWithPatient, 0)::integer = 1)::text as "patientGroupAdd"
            FROM (
                    (
                        SELECT
                            mp.MedPatientCode                   "id",
                            mp.ManCode                          "manId",
                            mp.personDataPermissionDate         "personDataPermissionDate",
                            CASE WHEN (coalesce(mp.Attributes,0) & 1) = 1 THEN 1 ELSE 0 END "printed",
                            CASE WHEN (coalesce(mp.Attributes,0) & 2) = 2 THEN 1 ELSE 0 END "unknown",
                            $addressSortField
                            trim(m.SurName || ' ' || m.Name || ' ' || m.SecondName)
                                                                "fios",
                            m.surname                           "surname",
                            m.name                              "name",
                            m.secondname                        "secondName",
                            mp.Cipher                           "cipher",
                            mp.PhotoId                          "photoId",
                            0                                   "photoExist",
                            string_agg(distinct(mpgadd.Name::text), '; ')
                                                                "sectorAdd",
                            string_agg(distinct(mpgadd.MedPatientGroupCode::text), ',')
                                                                "sectorAddIds",
                            mp.NumberHistorySickness            "numberHistorySickness",
                            mp.MedPatientGroupCode              "sectorId",
                            mp.archivalNumber                   "archivalNumber",
                            mp.MedPatientPolyclinicCode         "clinicId",
                            mp.OutReasonEx                      "outReason",
                            mpg.Name                            "sector",
                            mpg.MedDoctorCode                   "sectorDoctorId",
                            CASE
                                WHEN mp.StartDate = 19000101 THEN null
                                ELSE mp.StartDate
                            END::date                           "startDate",
                            CASE
                                WHEN mp.FinishDate = 30000101 THEN null
                                ELSE mp.FinishDate
                            END::date                           "finishDate",
                            pass.ManPassportCode                "manPassportId",
                            pass.AddressCode                    "addressId",
                            pass.AddressText                    "addressText",
                            pass.CiszAddressCode                "addressCiszCode",
                            pass.AddressHouse                   "addressHouse",
                            pass.AddressCorps                   "addressCorps",
                            pass.AddressFlat                    "addressFlat",
                            pass.Zipcode                        "addressZipcode",
                            MAX(c.name)                         "country",
                            pass.AddressResideCode              "addressResideId",
                            pass.AddressResideText              "addressResideText",
                            pass.CiszAddressResideCode          "addressResideCiszCode",
                            pass.AddressResideHouse             "addressResideHouse",
                            pass.AddressResideCorps             "addressResideCorps",
                            pass.AddressResideFlat              "addressResideFlat",
                            pass.ZipcodeReside                  "addressResideZipcode",
                            MAX(cr.name)                        "countryReside",
                            pass.Sex                            "sex",
                            pass.DateOfBirth                    "dateOfBirth",
                            pass.PassportNumber                 "passportNumber",
                            pass.Authority                      "authority",
                            pass.DateOfIssue                    "dateOfIssue",
                            pass.DateOfExpiry                   "dateOfExpiry",
                            pass.PlaceOfBirth                   "placeOfBirth",
                            pass.PersonalNumber                 "personalNumber",
                            pass.ManPassportKindCode            "passportKindId",
                            pass.Alien                          "alien",
                            MAX(mpk.name)                       "passportKindName",
                            $fieldIsActive                      "isActive",
                            $fieldAge                           "age",
                            FALSE                               "isSelected",
                            /* ulwp.Value as "workPlaceRem" */
                            (
                                SELECT inspectDate
                                FROM medInspect
                                WHERE inspectDate IS NOT NULL AND medPatientCode = mp.medPatientCode
                                ORDER BY inspectDate DESC
                                LIMIT 1
                            )                                   "lastDispensaryExaminationDate",
                            (
                                SELECT STRING_AGG(pr.name, ', ')
                                FROM medPatientCard pc
                                JOIN medPrivelege pr ON pr.medPrivelegeCode = pc.medPrivelegeCode
                                WHERE pc.medPatientCode = mp.medPatientCode
                                    AND ('NOW'::TIMESTAMP) BETWEEN pc.startDate AND COALESCE(pc.finishDate, 30000101)
                            )                                   "privileges",
                            (
                                SELECT STRING_AGG(PR.Name, ',')
                                FROM MedPatientCard PC
                                JOIN MedPrivelege PR ON PR.MedPrivelegeCode = PC.MedPrivelegeCode
                                LEFT JOIN MedPrivelegeFeature MPF ON MPF.MedPrivelegeCode = PR.MedPrivelegeCode
                                WHERE PC.MedPatientCode = MP.MedPatientCode
                                    AND ('NOW'::TIMESTAMP) BETWEEN PC.StartDate AND COALESCE(PC.FinishDate, 30000101)
                                    AND MPF.MedPrivelegeFeatureCode IS NOT NULL
                            )                                   "privilegesToDisplay",      -- Контингенты, выбранные для отображения в справочнике Статусы пациентов
                            MT.Note                             "patientNote",              -- Примечание
                            mp.JSON AS "json",
                            mp.Anonymous AS "anonymous",
                            MAX(COALESCE(CL.Status,0)) AS "ciszState",
                                CASE
                            WHEN pass.CiszAddressCode IS NOT NULL
                                 OR (
                                      MAX(cr.name) IS NOT NULL
                                      AND TRIM(MAX(cr.name)) <> ''
                                      AND MAX(cr.name) NOT ILIKE '%беларус%'
                                      AND MAX(cr.name) <> 'РБ'
                                 )
                            THEN true
                            ELSE false
                                END AS "isCiszAddress",
                                CASE
                            WHEN pass.CiszAddressResideCode IS NOT NULL
                                 OR (
                                      MAX(c.name) IS NOT NULL
                                      AND TRIM(MAX(c.name)) <> ''
                                      AND MAX(c.name) NOT ILIKE '%беларус%'
                                      AND MAX(c.name) <> 'РБ'
                                 )
                            THEN true
                            ELSE false
                                END AS "isCiszAddressReside",
                            CASE WHEN MAX(cr.name) IS NOT NULL AND TRIM(MAX(cr.name)) <> '' AND MAX(cr.name) NOT ILIKE '%беларус%' AND MAX(cr.name) <> 'РБ'
                            THEN true ELSE false END AS "isForeignAddress",
                            CASE WHEN MAX(c.name) IS NOT NULL AND TRIM(MAX(c.name)) <> '' AND MAX(c.name) NOT ILIKE '%беларус%' AND MAX(c.name) <> 'РБ'
                            THEN true ELSE false END AS "isForeignAddressReside"
                        $sqlFromWhere
                        ORDER BY "isSelected" DESC, $sort
                        LIMIT $limit
                        OFFSET $start
                    )
                $sqlUnion
            ) AS t
            ORDER BY "isSelected" DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function insertOrUpdatePatients($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $modifyingId = isEmptyProperty($records[0], 'id'); $isUpdate = is_int($modifyingId) && $modifyingId > 0; if ($isUpdate) { if (!testUserAccess(self::PATIENT_EDIT_DELETE_PERMISSION)) { throw new Exception(self::ERROR_NO_EDIT_DELETE_PERMISSION, ERROR_NOT_ALLOWED); } } else { if (!testUserAccess(self::PATIENT_ADD_PERMISSION)) { throw new Exception(self::ERROR_NO_ADD_PERMISSION, ERROR_NOT_ALLOWED); } } $enterpriseCode = getSessionValue('mapEnterpriseCode'); $ret = []; $attributesStr = ''; $sqlMan = <<<EOT
            INSERT INTO Man (ManCode, SurName, Name, SecondName)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (ManCode) DO UPDATE SET
                SurName     = EXCLUDED.SurName,
                Name        = EXCLUDED.Name,
                SecondName  = EXCLUDED.SecondName
            RETURNING ManCode AS id
EOT;
$stmtMan = $db->prepare($sqlMan); $sqlManPartial = <<<EOT
INSERT INTO Man (ManCode, SurName, Name, SecondName)
VALUES (?, ?, ?, ?)
ON CONFLICT (ManCode) DO UPDATE SET
    SurName = CASE WHEN ? = 1 THEN EXCLUDED.SurName ELSE Man.SurName END,
    Name = CASE WHEN ? = 1 THEN EXCLUDED.Name ELSE Man.Name END,
    SecondName = CASE WHEN ? = 1 THEN EXCLUDED.SecondName ELSE Man.SecondName END
RETURNING ManCode AS id
EOT;
$stmtManPartial = $db->prepare($sqlManPartial); $sqlManPassport = <<<"EOT"
INSERT INTO ManPassport (
    ManPassportCode, ManCode, Sex, DateOfBirth, AddressCode, AddressResideCode, ManPassportKindCode, PassportNumber, Authority, DateOfIssue,
    DateOfExpiry, PlaceOfBirth, PersonalNumber, AddressHouse, AddressCorps, AddressFlat, Zipcode, AddressResideHouse,
    AddressResideCorps, AddressResideFlat, ZipcodeReside, Alien, AddressResideText, AddressText, CiszAddressCode, CiszAddressResideCode)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (ManPassportCode) DO UPDATE SET
    Sex = case when ? = 1 then EXCLUDED.Sex else ManPassport.Sex end,
    DateOfBirth = case when ? = 1 then EXCLUDED.DateOfBirth else ManPassport.DateOfBirth end,
    AddressCode = case when ? = 1 then EXCLUDED.AddressCode else ManPassport.AddressCode end,
    AddressResideCode = case when ? = 1 then EXCLUDED.AddressResideCode else ManPassport.AddressResideCode end,
    ManPassportKindCode = case when ? = 1 then EXCLUDED.ManPassportKindCode else ManPassport.ManPassportKindCode end,
    PassportNumber = case when ? = 1 then EXCLUDED.PassportNumber else ManPassport.PassportNumber end,
    Authority = case when ? = 1 then EXCLUDED.Authority else ManPassport.Authority end,
    DateOfIssue = case when ? = 1 then EXCLUDED.DateOfIssue else ManPassport.DateOfIssue end,
    DateOfExpiry = case when ? = 1 then EXCLUDED.DateOfExpiry else ManPassport.DateOfExpiry end,
    PlaceOfBirth = case when ? = 1 then EXCLUDED.PlaceOfBirth else ManPassport.PlaceOfBirth end,
    PersonalNumber = case when ? = 1 then EXCLUDED.PersonalNumber else ManPassport.PersonalNumber end,
    AddressHouse = case when ? = 1 then EXCLUDED.AddressHouse else ManPassport.AddressHouse end,
    AddressCorps = case when ? = 1 then EXCLUDED.AddressCorps else ManPassport.AddressCorps end,
    AddressFlat = case when ? = 1 then EXCLUDED.AddressFlat else ManPassport.AddressFlat end,
    Zipcode = case when ? = 1 then EXCLUDED.Zipcode else ManPassport.Zipcode end,
    AddressResideHouse = case when ? = 1 then EXCLUDED.AddressResideHouse else ManPassport.AddressResideHouse end,
    AddressResideCorps = case when ? = 1 then EXCLUDED.AddressResideCorps else ManPassport.AddressResideCorps end,
    AddressResideFlat = case when ? = 1 then EXCLUDED.AddressResideFlat else ManPassport.AddressResideFlat end,
    ZipcodeReside = case when ? = 1 then EXCLUDED.ZipcodeReside else ManPassport.ZipcodeReside end,
    Alien = case when ? = 1 then EXCLUDED.Alien else ManPassport.Alien end,
    AddressResideText = case when ? = 1 then EXCLUDED.AddressResideText else ManPassport.AddressResideText end,
    CiszAddressResideJson = case
        when (EXCLUDED.AddressResideCode is not null
           or EXCLUDED.AddressResideHouse is not null
           or EXCLUDED.AddressResideCorps is not null
           or EXCLUDED.AddressResideFlat is not null
           or EXCLUDED.ZipcodeReside is not null)
        then null else ManPassport.CiszAddressResideJson end,
    AddressText = case when ? = 1 then EXCLUDED.AddressText else ManPassport.AddressText end,
    CiszAddressJson = case
        when (EXCLUDED.AddressCode is not null
           or EXCLUDED.AddressHouse is not null
           or EXCLUDED.AddressCorps is not null
           or EXCLUDED.AddressFlat is not null
           or EXCLUDED.Zipcode is not null)
        then null else ManPassport.CiszAddressJson end,
    CiszAddressCode = case when ? = 1 then EXCLUDED.CiszAddressCode else ManPassport.CiszAddressCode end,
    CiszAddressResideCode = case when ? = 1 then EXCLUDED.CiszAddressResideCode else ManPassport.CiszAddressResideCode end
RETURNING ManPassportCode AS id
EOT;
$stmtManPassport = $db->prepare($sqlManPassport); $sqlMedPatient = <<<"EOT"
INSERT INTO MedPatient (MedPatientCode, Cipher, NumberHistorySickness, StartDate, FinishDate, MedPatientGroupCode,
    EnterpriseCode, MedPatientPolyclinicCode, ManCode, ArchivalNumber, OutReasonEx, personDataPermissionDate, Attributes,
    JSON, Anonymous)
VALUES (?, ?, ?, ?, COALESCE(?::date, 30000101)::date, ?, ?, ?, ?, ?, ?, ?, ?, nullif(?, ''), nullif(?, 0))
ON CONFLICT (MedPatientCode) DO UPDATE SET
    Cipher = case when ? = 1 then EXCLUDED.Cipher else MedPatient.Cipher end,
    NumberHistorySickness = case when ? = 1 then EXCLUDED.NumberHistorySickness else MedPatient.NumberHistorySickness end,
    StartDate = case when ? = 1 then EXCLUDED.StartDate else MedPatient.StartDate end,
    FinishDate = case when ? = 1 then EXCLUDED.FinishDate else MedPatient.FinishDate end,
    MedPatientGroupCode = case when ? = 1 then EXCLUDED.MedPatientGroupCode else MedPatient.MedPatientGroupCode end,
    MedPatientPolyclinicCode = case when ? = 1 then EXCLUDED.MedPatientPolyclinicCode else MedPatient.MedPatientPolyclinicCode end,
    ArchivalNumber = case when ? = 1 then EXCLUDED.ArchivalNumber else MedPatient.ArchivalNumber end,
    OutReasonEx = case when ? = 1 then EXCLUDED.OutReasonEx else MedPatient.OutReasonEx end,
    personDataPermissionDate = case when ? = 1 then EXCLUDED.personDataPermissionDate else MedPatient.personDataPermissionDate end,
    Attributes = ?,
    JSON = CASE WHEN ? = 1 THEN EXCLUDED.JSON ELSE MedPatient.JSON END,
    Anonymous = CASE WHEN ? = 1 THEN EXCLUDED.Anonymous ELSE MedPatient.Anonymous END
RETURNING MedPatientCode AS id
EOT;
$stmtMedPatient = $db->prepare($sqlMedPatient); $sqlIdManTask = <<<"EOT"
SELECT
    ManTaskCode
FROM ManTask MT
WHERE
    MT.TypeTask = 12
  AND MT.EnterpriseCode = ?
  AND MT.ManCode = ?
EOT;
$sqlUpdateManTask = <<<"EOT"
UPDATE ManTask SET
    Note = ?
WHERE ManTaskCode = ?
EOT;
$sqlInsertManTask = <<<"EOT"
INSERT INTO ManTask (TypeTask, Note, EnterpriseCode, ManCode)
VALUES (12, ?, ?, ?)
EOT;
$db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $rec->manId = isUndefinedProperty($row, 'manId'); if (isUndefinedProperty($row, 'useFioParts')) { $stmtManPartial->execute([ $rec->manId, isUndefinedProperty($row, 'surname'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'secondName'), propertyExistsSQL($row, 'surname'), propertyExistsSQL($row, 'name'), propertyExistsSQL($row, 'secondName'), ]); $rec->manId = fetchValue($stmtManPartial, 'id'); } elseif (isset($row->fio) && isUndefinedProperty($row, 'fio')) { $aFio = trim($row->fio); $fio = preg_split("/[\s.,]+/u", $aFio); $stmtMan->execute([ isUndefinedProperty($row, 'manId'), $fio[0], isset($fio[1]) ? $fio[1] : '', trim(implode(" ", (array_slice($fio, 2)))) ]); $rec->manId = fetchValue($stmtMan, 'id'); unset($fio); } $oldAddress = $this->readAddress($row) ?? ''; $stmtManPassport->execute([ isUndefinedProperty($row, 'manPassportId'), $rec->manId, isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'dateOfBirth'), isUndefinedProperty($row, 'addressId'), isUndefinedProperty($row, 'addressResideId'), isUndefinedProperty($row, 'passportKindId'), isUndefinedProperty($row, 'passportNumber'), isUndefinedProperty($row, 'authority'), isUndefinedProperty($row, 'dateOfIssue'), isUndefinedProperty($row, 'dateOfExpiry'), isUndefinedProperty($row, 'placeOfBirth'), isUndefinedProperty($row, 'personalNumber'), isUndefinedProperty($row, 'addressHouse'), isUndefinedProperty($row, 'addressCorps'), isUndefinedProperty($row, 'addressFlat'), isUndefinedProperty($row, 'addressZipcode'), isUndefinedProperty($row, 'addressResideHouse'), isUndefinedProperty($row, 'addressResideCorps'), isUndefinedProperty($row, 'addressResideFlat'), isUndefinedProperty($row, 'addressResideZipcode'), isUndefinedProperty($row, 'alien'), isUndefinedProperty($row, 'addressResideText'), isUndefinedProperty($row, 'addressText'), isUndefinedProperty($row, 'addressCiszCode'), isUndefinedProperty($row, 'addressResideCiszCode'), propertyExistsSQL($row, 'sex'), propertyExistsSQL($row, 'dateOfBirth'), propertyExistsSQL($row, 'addressId'), propertyExistsSQL($row, 'addressResideId'), propertyExistsSQL($row, 'passportKindId'), propertyExistsSQL($row, 'passportNumber'), propertyExistsSQL($row, 'authority'), propertyExistsSQL($row, 'dateOfIssue'), propertyExistsSQL($row, 'dateOfExpiry'), propertyExistsSQL($row, 'placeOfBirth'), propertyExistsSQL($row, 'personalNumber'), propertyExistsSQL($row, 'addressHouse'), propertyExistsSQL($row, 'addressCorps'), propertyExistsSQL($row, 'addressFlat'), propertyExistsSQL($row, 'addressZipcode'), propertyExistsSQL($row, 'addressResideHouse'), propertyExistsSQL($row, 'addressResideCorps'), propertyExistsSQL($row, 'addressResideFlat'), propertyExistsSQL($row, 'addressResideZipcode'), propertyExistsSQL($row, 'alien'), propertyExistsSQL($row, 'addressResideText'), propertyExistsSQL($row, 'addressText'), propertyExistsSQL($row, 'addressCiszCode'), propertyExistsSQL($row, 'addressResideCiszCode') ]); $rec->manPassportId = fetchValue($stmtManPassport, 'id'); $newAddress = $this->readAddress($row) ?? ''; if (strcasecmp($oldAddress, $newAddress)) { $this->doWriteChangeAddress(isEmptyProperty($row, 'id', 0), $newAddress, $oldAddress); } if (!isUndefinedProperty($row, 'numberHistorySickness')) { $row->numberHistorySickness = $this->getNextNumberHistorySickness(); } $attributes = 0; if (isUndefinedProperty($row, 'printed')) { $attributes |= 1; } if (isUndefinedProperty($row, 'unknown')) { $attributes |= 2; } $stmtMedPatient->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'numberHistorySickness'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'sectorId'), $enterpriseCode, isUndefinedProperty($row, 'clinicId'), $rec->manId, isUndefinedProperty($row, 'archivalNumber'), isUndefinedProperty($row, 'outReason'), isUndefinedProperty($row, 'personDataPermissionDate'), $attributes, isUndefinedProperty($row, 'json'), isUndefinedProperty($row, 'anonymous'), propertyExistsSQL($row, 'cipher'), propertyExistsSQL($row, 'numberHistorySickness'), propertyExistsSQL($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), propertyExistsSQL($row, 'sectorId'), propertyExistsSQL($row, 'clinicId'), propertyExistsSQL($row, 'archivalNumber'), propertyExistsSQL($row, 'outReason'), propertyExistsSQL($row, 'personDataPermissionDate'), $attributes, propertyExistsSQL($row, 'json'), propertyExistsSQL($row, 'anonymous'), ]); $rec->id = fetchValue($stmtMedPatient, 'id'); $idManTask = selectSqlValue($db, $sqlIdManTask, [$enterpriseCode, $rec->manId]); $noteEx = propertyExistsSQL($row, 'patientNote'); if ($idManTask && $noteEx) { executeSql($db, $sqlUpdateManTask, [isUndefinedProperty($row, 'patientNote'), $idManTask]); } else if ($noteEx) { executeSql($db, $sqlInsertManTask, [isUndefinedProperty($row, 'patientNote'), $enterpriseCode, $rec->manId]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function readAddress($record) { $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
        select FN_cmCalcAddress(?, 5)::text
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($record, 'manId', 0) ]); return fetchValue($stmt, 'fn_cmcalcaddress'); } private function doWriteChangeAddress($patientId, $newAddress, $oldAddress) { $db = $this->db ?: $this->db = connectDb(); $currDate = udate('Ymd'); $sql = <<< "EOT"
        INSERT INTO medPatientChange (medPatientCode, type, value, changeDate)
        VALUES (?, 1, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId, $newAddress . ' (' . $oldAddress . ')', $currDate ]); return $stmt->fetch(); } public function createPatients($records) { return $this->insertOrUpdatePatients($records, true); } public function updatePatients($records) { return $this->insertOrUpdatePatients($records, false); } public function deletePatients($records) { if (!testUserAccess(self::PATIENT_EDIT_DELETE_PERMISSION)) { throw new Exception(self::ERROR_NO_EDIT_DELETE_PERMISSION, ERROR_NOT_ALLOWED); } return deleteSql($this->db, 'MedPatient', 'MedPatientCode', $records); } public function readPassportAuthoritys($params) { $db = $this->db ?: $this->db = connectDb(); $find = isUndefinedProperty($params, 'find'); if ($find) { $query = mb_ereg_replace("[\s]+", ":* & ", trim($find)) . ":*"; $sql = <<<"EOT"
SELECT DISTINCT asa_trim(Authority) as "id"
FROM ManPassport
WHERE Authority IS NOT NULL
  AND Authority <> ''
  AND to_tsvector(Authority) @@ to_tsquery('{$query}')
--GROUP BY upper(asa_trim(Authority)), "id"
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } else { return []; } } public function readPassportKinds() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    ManPassportKindCode "id",
    Cipher "cipher",
    Name "name"
FROM ManPassportKind
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPassportKinds($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO ManPassportKind (Cipher, Name)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ isEmptyProperty($row, 'cipher'), isEmptyProperty($row, 'name') ]); $rec->id = $db->lastInsertId('seq_ManPassportKind'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePassportKinds($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update ManPassportKind set
       cipher = case when ? = 1 then ? else Cipher end,
       name = case when ? = 1 then ? else Name end
where ManPassportKindCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'cipher'), propertyExistsSQL($row, 'name'), isEmptyProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePassportKinds($records) { return deleteSql($this->db, 'ManPassportKind', 'ManPassportKindCode', $records); } public function readAddSectors($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', null); $sql = <<<"EOT"
SELECT
    mpag.MedPatientGroupCode as "id",
    mpag.MedPatientAddGroupCode as "sectorAddId",
    mpag.MedPatientCode as "patientId"
FROM MedPatientAddGroup mpag
LEFT JOIN MedPatientGroup AS mpg ON mpg.MedPatientGroupCode = mpag.MedPatientGroupCode
WHERE mpag.MedPatientCode = $patientId
ORDER BY mpg.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createAddSectors($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedPatientAddGroup (MedPatientCode, MedPatientGroupCode)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'id') ]); $rec->sectorAddId = $db->lastInsertId('seq_MedPatientAddGroup'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteAddSectors($records) { return deleteSql($this->db, 'MedPatientAddGroup', 'MedPatientAddGroupCode', $records, 'sectorAddId'); } public function readPatientBusy($params) { $db = $this->db ?: $this->db = connectDb(); $paramWhere = []; $patientId = isEmptyProperty($params, 'patientId', null); $currDate = Date('Ymd'); $currBusy = isset($params->currBusy) && $params->currBusy ? "$currDate BETWEEN mb.StartDate AND mb.FinishDate" : ""; switch (true) { case $patientId: $paramWhere['patientId'] = "mb.MedPatientCode = $patientId"; if ($currBusy) { $paramWhere['currBusy'] = $currBusy; } break; case $currBusy: $paramWhere['currBusy'] = $currBusy; break; default: break; } $sqlWhere = !empty($paramWhere) ? "WHERE " . implode(" AND ", $paramWhere) : ""; $sql = <<<"EOT"
SELECT
    mb.MedPatientBusyCode as "id",
    mb.MedPatientCode as "patientId",
    mb.StartDate as "startDate",
    CASE
        WHEN COALESCE(mb.FinishDate, 30000101)::date = 30000101 THEN null
        ELSE mb.FinishDate
    END as "finishDate",
    mb.EnterpriseKindCode as "organizationKindId",
    mb.EnterpriseCode as "organizationId",
    e.Name as "organizationName",
    mb.ClassGroup as "classGroup",
    mb.UniDictCodeProfession as "professionId",
    (select Value from UniDict where uniDictCode = mb.UniDictCodeProfession) as "professionName",
    mb.DepartmentCode as "departmentId",
    FN_cmDepartmentName(mb.DepartmentCode) as "departmentName",
    mb.Attributes as "mainPlacejob",
    MB.CiszResourceValueCodeEmployment AS "specializationId",
    CRV.Name AS "specializationName",
    CRV.Code AS "specializationCode",
    CASE
    WHEN NOT EXISTS (
        SELECT 1
        FROM EnterpriseInKind EK
        WHERE EK.EnterpriseCode = E.EnterpriseCode
    )
    OR EXISTS (
        SELECT 1
        FROM EnterpriseInKind EK
        JOIN EnterpriseKind K
            ON K.EnterpriseKindCode = EK.EnterpriseKindCode
        WHERE EK.EnterpriseCode = E.EnterpriseCode
          AND (K.Type IN (0,1) OR K.Type IS NULL)
    )
    THEN true
    ELSE false
END AS "isWorkOrganization",
    EK.Type "isWorkStudy"
FROM MedPatientBusy mb
LEFT JOIN Enterprise as e ON mb.EnterpriseCode = E.EnterpriseCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MB.CiszResourceValueCodeEmployment
LEFT JOIN EnterpriseKind EK ON EK.EnterpriseKindCode = MB.EnterpriseKindCode
$sqlWhere
ORDER BY "startDate" DESC, "finishDate", "id" DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdatePatientBusy($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedPatientBusy (
    MedPatientBusyCode, MedPatientCode, StartDate, FinishDate, EnterpriseKindCode, EnterpriseCode, DepartmentCode,
    ClassGroup, UniDictCodeProfession, Attributes, CiszResourceValueCodeEmployment)
VALUES (?, ?, ?, COALESCE(?::date, 30000101)::date, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (MedPatientBusyCode) DO UPDATE SET
    StartDate = case when ? = 1 then EXCLUDED.StartDate else MedPatientBusy.StartDate end,
    FinishDate = case when ? = 1 then EXCLUDED.FinishDate else MedPatientBusy.FinishDate end,
    EnterpriseKindCode = case when ? = 1 then EXCLUDED.EnterpriseKindCode else MedPatientBusy.EnterpriseKindCode end,
    EnterpriseCode = case when ? = 1 then EXCLUDED.EnterpriseCode else MedPatientBusy.EnterpriseCode end,
    DepartmentCode = case when ? = 1 then EXCLUDED.DepartmentCode else MedPatientBusy.DepartmentCode end,
    ClassGroup = case when ? = 1 then EXCLUDED.ClassGroup else MedPatientBusy.ClassGroup end,
    UniDictCodeProfession = case when ? = 1 then EXCLUDED.UniDictCodeProfession else MedPatientBusy.UniDictCodeProfession end,
    Attributes = case when ? = 1 then EXCLUDED.Attributes else MedPatientBusy.Attributes end,
    CiszResourceValueCodeEmployment = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeEmployment ELSE MedPatientBusy.CiszResourceValueCodeEmployment END
    RETURNING MedPatientBusyCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'organizationKindId'), isUndefinedProperty($row, 'organizationId'), isUndefinedProperty($row, 'departmentId'), isUndefinedProperty($row, 'classGroup'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'mainPlacejob'), isUndefinedProperty($row, 'specializationId'), propertyExistsSQL($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), propertyExistsSQL($row, 'organizationKindId'), propertyExistsSQL($row, 'organizationId'), propertyExistsSQL($row, 'departmentId'), propertyExistsSQL($row, 'classGroup'), propertyExistsSQL($row, 'professionId'), propertyExistsSQL($row, 'mainPlacejob'), propertyExistsSQL($row, 'specializationId') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientBusy($records) { return $this->insertOrUpdatePatientBusy($records, true); } public function updatePatientBusy($records) { return $this->insertOrUpdatePatientBusy($records, false); } public function deletePatientBusy($records) { return deleteSql($this->db, 'MedPatientBusy', 'MedPatientBusyCode', $records); } public function readPatientContingents($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', null); $pCurrContingents = isEmptyProperty($params, 'currContingents'); $currDate = is_bool($pCurrContingents) ? Date('Ymd') : Date("Ymd", safe_strtotime($pCurrContingents)); $currContingents = $pCurrContingents ? "AND $currDate BETWEEN COALESCE(PC.StartDate, 19000101) AND COALESCE(PC.FinishDate, 30000101) AND $currDate <> PC.FinishDate" : ""; $isPrivilege = isset($params->isPrivilege) && $params->isPrivilege ? "AND COALESCE(P.PrivilegePercent, 0.0) > 0.0" : ""; $sql = <<<"EOT"

SELECT
    PC.MedPatientCardCode AS "id",
    PC.MedPatientCode AS "patientId",
    PC.StartDate AS "startDate",
    CASE
       WHEN COALESCE(PC.FinishDate, 30000101)::date = 30000101 THEN null
       ELSE PC.FinishDate
    END as "finishDate",
    CASE
       WHEN COALESCE(PC.RebillingDate, 30000101)::date = 30000101 THEN null
       ELSE PC.RebillingDate
    END as "rebillingDate",
    PC.MedPrivelegeCode AS "contingentId",
    P.Name AS "contingentName",
    PC.MedDiagnosisCode AS "diagnosisId",
    PC.Document AS "document",
    PC.DocumentNumber AS "documentNumber",
    PC.DocumentSeries AS "documentSeries",
    PC.DocumentDate AS "documentDate",
    PC.CauseDisease AS "causeDisease",
    PC.Enterprise AS "enterprise",
/*       asa_substr(D.Name + (CASE
                                WHEN NullIf(asa_trim(D.Cipher1), '') IS NULL THEN ''
                                ELSE ' - ' + asa_trim(D.Cipher1)
                            END), 1, 255) AS "diagnosisName",
*/
    CASE P.MedPrivelegeCode IS NOT NULL
       WHEN TRUE THEN P.PrivilegePercent
       WHEN FALSE THEN D.PrivilegePercent
    END AS "privilegePercent",
    FN_cmGetNumberBitList(PC.CauseDisability) AS "causeDisability",
    P.MedPrivilegeGroupCode AS "privilegeGroupId",
    SubStr(D.Name + (CASE WHEN NullIf( Trim( D.Cipher1 ), '') IS NULL THEN '' ELSE ' - ' + Trim(D.Cipher1) END), 1, 255 ) "diagnosisName",
    SubStr(P.Name + (CASE WHEN NullIf( Trim( P.Cipher ), '') IS NULL THEN '' ELSE ' - ' + Trim( P.Cipher ) END), 1, 255 ) "privelegeName",
    CASE WHEN (D.MedDiagnosisCode IS not null) and (D.PrivilegePercent IS not null) then
    D.PrivilegePercent
    ELSE P.PrivilegePercent END AS "privilegePercent",
    CASE WHEN PC.RebillingDate = '3000-01-01' THEN NULL ELSE PC.RebillingDate END AS "rebillingDate",
    PC.CiszDisabilityGroupCode AS "ciszDisabilityGroupId",
    PC.CiszHealthLossStageCode AS "ciszHealthLossStageId"
FROM MedPatientCard AS PC
LEFT JOIN MedDiagnosis AS D ON D.MedDiagnosisCode = PC.MedDiagnosisCode
LEFT JOIN MedPrivelege AS P ON P.MedPrivelegeCode = PC.MedPrivelegeCode
WHERE PC.MedPatientCode = $patientId
  $currContingents
  $isPrivilege
ORDER BY "startDate" DESC, "finishDate", "id" DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $result = $stmt->fetchAll(); if (!empty($result)) { $cardIds = array_column($result, 'id'); $cardIdsList = implode(',', array_map('intval', $cardIds)); $sqlCisz = <<<"EOT"
SELECT 
    MPCCP.MedPatientCardCode AS "cardId",
    ARRAY_TO_STRING(ARRAY_AGG(MPCCP.CiszPrivelegeCode ORDER BY MPCCP.CiszPrivelegeCode), ',') AS "ciszPrivelegeId"
FROM MedPatientCardCiszPrivelege MPCCP
WHERE MPCCP.MedPatientCardCode IN ($cardIdsList)
GROUP BY MedPatientCardCode
EOT;
$stmtCisz = $db->prepare($sqlCisz); $stmtCisz->execute(); $ciszResults = $stmtCisz->fetchAll(); $ciszMap = []; foreach ($ciszResults as $ciszRow) { if (!empty($ciszRow['ciszPrivelegeId'])) { $ciszMap[$ciszRow['cardId']] = array_map('intval', explode(',', $ciszRow['ciszPrivelegeId'])); } else { $ciszMap[$ciszRow['cardId']] = []; } } foreach ($result as &$row) { if (isset($ciszMap[$row['id']])) { $row['ciszDictPrivelegeId'] = $ciszMap[$row['id']]; } else { $row['ciszDictPrivelegeId'] = []; } } } return $result; } public function insertOrUpdatePatientContingents($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; if ($isInsert) { $sql = <<<"EOT"
INSERT INTO MedPatientCard (MedPatientCode, StartDate, FinishDate, RebillingDate, MedPrivelegeCode, MedDiagnosisCode,
    Document, DocumentNumber, DocumentSeries, DocumentDate, Enterprise, CauseDisability, CauseDisease,
     CiszDisabilityGroupCode, CiszHealthLossStageCode)
VALUES (
    :patientId, :startDate, COALESCE(:finishDate::date, 30000101)::date, COALESCE(:rebillingDate::date, 30000101)::date,
    :contingentId, :diagnosisId, :document, :documentNumber, :documentSeries, :documentDate, :enterprise, FN_cmGetBitListNumber(:causeDisability),
    :causeDisease, :ciszDisabilityGroupId, :ciszHealthLossStageId)
RETURNING medpatientcardcode AS id
EOT;
$params = [ ':patientId' => isUndefinedProperty($row, 'patientId'), ':startDate' => isUndefinedProperty($row, 'startDate'), ':finishDate' => isUndefinedProperty($row, 'finishDate'), ':rebillingDate' => isUndefinedProperty($row, 'rebillingDate'), ':contingentId' => isUndefinedProperty($row, 'contingentId'), ':diagnosisId' => isUndefinedProperty($row, 'diagnosisId'), ':document' => isUndefinedProperty($row, 'document'), ':documentNumber' => isUndefinedProperty($row, 'documentNumber'), ':documentSeries' => isUndefinedProperty($row, 'documentSeries'), ':documentDate' => isUndefinedProperty($row, 'documentDate'), ':enterprise' => isUndefinedProperty($row, 'enterprise'), ':causeDisability' => isUndefinedProperty($row, 'causeDisability'), ':causeDisease' => isUndefinedProperty($row, 'causeDisease'), ':ciszDisabilityGroupId' => isUndefinedProperty($row, 'ciszDisabilityGroupId'), ':ciszHealthLossStageId' => isUndefinedProperty($row, 'ciszHealthLossStageId') ]; } else { $sql = <<<"EOT"
UPDATE MedPatientCard SET
    StartDate = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.StartDate END,
    FinishDate = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.FinishDate END,
    RebillingDate = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.RebillingDate END,
    MedPrivelegeCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.MedPrivelegeCode END,
    MedDiagnosisCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.MedDiagnosisCode END,
    Document = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.Document END,
    DocumentNumber = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.DocumentNumber END,
    DocumentSeries = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.DocumentSeries END,
    DocumentDate = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.DocumentDate END,
    Enterprise = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.Enterprise END,
    CauseDisability = CASE WHEN ? = 1 THEN FN_cmGetBitListNumber(?) ELSE MedPatientCard.CauseDisability END,
    CauseDisease = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.CauseDisease END,
    CiszDisabilityGroupCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.CiszDisabilityGroupCode END,
    CiszHealthLossStageCode = CASE WHEN ? = 1 THEN ? ELSE MedPatientCard.CiszHealthLossStageCode END
WHERE MedPatientCardCode = ?
EOT;
$params = [ propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'rebillingDate'), isUndefinedProperty($row, 'rebillingDate'), propertyExistsSQL($row, 'contingentId'), isUndefinedProperty($row, 'contingentId'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'document'), isUndefinedProperty($row, 'document'), propertyExistsSQL($row, 'documentNumber'), isUndefinedProperty($row, 'documentNumber'), propertyExistsSQL($row, 'documentSeries'), isUndefinedProperty($row, 'documentSeries'), propertyExistsSQL($row, 'documentDate'), isUndefinedProperty($row, 'documentDate'), propertyExistsSQL($row, 'enterprise'), isUndefinedProperty($row, 'enterprise'), propertyExistsSQL($row, 'causeDisability'), isUndefinedProperty($row, 'causeDisability'), propertyExistsSQL($row, 'causeDisease'), isUndefinedProperty($row, 'causeDisease'), propertyExistsSQL($row, 'ciszDisabilityGroupId'), isUndefinedProperty($row, 'ciszDisabilityGroupId'), propertyExistsSQL($row, 'ciszHealthLossStageId'), isUndefinedProperty($row, 'ciszHealthLossStageId'), isUndefinedProperty($row, 'id') ]; } $stmt = $db->prepare($sql); $stmt->execute($params); $rec->id = $isInsert ? fetchValue($stmt, 'id') : isUndefinedProperty($row, 'id'); if (!empty($row->ciszDictPrivelegeId)) { if ($isInsert) { foreach ($row->ciszDictPrivelegeId as $item) { $sqlPrivelege = <<<SQL
                            INSERT INTO MedPatientCardCiszPrivelege (MedPatientCardCode, CiszPrivelegeCode)
                            VALUES (:cardId, :privelegeId)
                            SQL;
$params = [ ':cardId' => $rec->id, ':privelegeId' => $item ]; $stmt = $db->prepare($sqlPrivelege); $stmt->execute($params); } } else { $sqlDelete = <<<SQL
                            DELETE FROM MedPatientCardCiszPrivelege WHERE MedPatientCardCode = :cardId
                        SQL;
$params = [ 'cardId' => isUndefinedProperty($row, 'id') ]; $stmtDelete = $db->prepare($sqlDelete); $stmtDelete->execute($params); foreach ($row->ciszDictPrivelegeId as $item) { $sqlPrivelege = <<<SQL
                            INSERT INTO MedPatientCardCiszPrivelege  (MedPatientCardCode, CiszPrivelegeCode)
                            VALUES (:cardId, :privelegeId)
                            SQL;
$params = [ ':cardId' => $rec->id, ':privelegeId' => $item ]; $stmt = $db->prepare($sqlPrivelege); $stmt->execute($params); } } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientContingents($records) { return $this->insertOrUpdatePatientContingents($records, true); } public function updatePatientContingents($records) { return $this->insertOrUpdatePatientContingents($records, false); } public function deletePatientContingents($records) { return deleteSql($this->db, 'MedPatientCard', 'MedPatientCardCode', $records); } public function readPatientContacts($params) { $db = $this->db ?: $this->db = connectDb(); $manId = isEmptyProperty($params, 'manId', null); $sql = <<<"EOT"
SELECT
    manPhoneCode "id",
    phoneNumber "number",
    note "note",
    phoneType "type",
    manCode "manId",
    status "status"
FROM ManPhone
WHERE manCode = $manId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } private function insertOrUpdatePatientContacts($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO ManPhone (
    manPhoneCode, phoneNumber, note, phoneType, manCode, status)
VALUES (?, ?, ?, ?, ?, ?)
ON CONFLICT (manPhoneCode) DO UPDATE SET
    phoneNumber = case when ? = 1 then EXCLUDED.phoneNumber else ManPhone.phoneNumber end,
    note = case when ? = 1 then EXCLUDED.note else ManPhone.note end,
    phoneType = case when ? = 1 then EXCLUDED.phoneType else ManPhone.phoneType end,
    manCode = case when ? = 1 then EXCLUDED.manCode else ManPhone.manCode end,
    status = case when ? = 1 then EXCLUDED.status else ManPhone.status end
RETURNING ManPhoneCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'number'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'type'), isUndefinedProperty($row, 'manId'), isUndefinedProperty($row, 'status'), propertyExistsSQL($row, 'number'), propertyExistsSQL($row, 'note'), propertyExistsSQL($row, 'type'), propertyExistsSQL($row, 'manId'), propertyExistsSQL($row, 'status') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPatientContacts($records) { return $this->insertOrUpdatePatientContacts($records, true); } public function updatePatientContacts($records) { return $this->insertOrUpdatePatientContacts($records, false); } public function deletePatientContacts($records) { return deleteSql($this->db, 'ManPhone', 'ManPhoneCode', $records); } public function readPatientBarCodes($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', null); $sql = <<<"EOT"
SELECT mbc.BarCode AS "id",
       mbc.BarCode AS "barCode",
       mbc.StartDate AS "startDate",
       mbc.StopDate AS "stopDate",
       mbc.ManCode AS "manId",
       /* mbc.UserCode AS "userCode", */
       mbc.State AS "state",
       (MBC.Attributes & 1) <> 0 AS "isPolyclinic"
FROM MedPatient AS mp
JOIN MedBarCode mbc ON mbc.ManCode = mp.ManCode
WHERE mp.MedPatientCode = $patientId
ORDER BY "startDate" DESC, "stopDate", "id" DESC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createPatientBarCodes($records) { $db = $this->db ?: $this->db = connectDb(); $mapUserCode = getSessionValue('mapUserCode'); $ret = []; $sql = <<<"EOT"
INSERT INTO MedBarCode (BarCode, StartDate, StopDate, ManCode, UserCode, State, Attributes)
VALUES (?, ?, ?, ?, $mapUserCode, 0, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isPolyclinic = isUndefinedProperty($row, 'isPolyclinic'); $attributes = $isPolyclinic ? 1 : 0; $ret[] = $rec = new stdClass; $stmt->execute([ isUndefinedProperty($row, 'barCode'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'stopDate'), isUndefinedProperty($row, 'manId'), $attributes ]); $rec->id = isUndefinedProperty($row, 'barCode'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientBarCodes($records) { $db = $this->db ?: $this->db = connectDb(); $mapUserCode = getSessionValue('mapUserCode'); $ret = []; $sql = <<<"EOT"
UPDATE MedBarCode SET
    BarCode = case when ? = 1 then ? else BarCode end,
    StartDate = case when ? = 1 then ? else StartDate end,
    StopDate = case when ? = 1 then ? else StopDate end,
    Attributes = CASE
        WHEN ? = 1 THEN COALESCE(Attributes, 0) | 1
        WHEN ? = 1 THEN COALESCE(Attributes, 0) & ~1
        ELSE Attributes END
WHERE BarCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $hasPolyclinic = propertyExistsSQL($row, 'isPolyclinic'); $isPolyclinic = isUndefinedProperty($row, 'isPolyclinic'); $stmt->execute([ propertyExistsSQL($row, 'barCode'), isUndefinedProperty($row, 'barCode'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'stopDate'), isUndefinedProperty($row, 'stopDate'), $hasPolyclinic && $isPolyclinic ? 1 : 0, $hasPolyclinic && !$isPolyclinic ? 1 : 0, isUndefinedProperty($row, 'id') ]); $rec->id = isUndefinedProperty($row, 'barCode'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatientBarCodes($records) { return deleteSql($this->db, 'MedBarCode', 'BarCode', $records); } public function readPatientInfo($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', 0); $getAge = isEmptyProperty($params, 'getAge'); $getAddress = isEmptyProperty($params, 'getAddress'); $getSector = isEmptyProperty($params, 'getSector'); $getVisitZone = isEmptyProperty($params, 'getVisitZone'); $getClinic = isEmptyProperty($params, 'getClinic'); $getFirstSector = isEmptyProperty($params, 'getFirstSector'); $fieldFio = isUndefinedProperty($params, 'getFio', true) ? "FN_cmFIO(MP.manCode) \"patientFio\"" : "1"; $fieldsAge = $getAge ? ", FN_wgCalcStage(2, CURRENT_DATE, MPASS.DateOfBirth, 0, 0, 0, null) \"age\"" . ", MPASS.DateOfBirth \"dateOfBirth\"" : ""; $fieldAddress = $getAddress ? ", FN_cmCalcAddress(mp.manCode, 5, 0, 1) \"address\"" . ", FN_cmCalcAddress(mp.manCode, 0)::TEXT \"registrationAddress\"" . ", FN_cmCalcAddress(mp.manCode, 1)::TEXT \"residenceAddress\"" : ""; $fieldsSector = $getSector ? ", VISIT_ZONE.Name \"sectorName\"" . ", VISIT_ZONE.MedPatientGroupCode \"sectorId\"" : ""; $fieldVisitZone = $getVisitZone ? ", VISIT_ZONE.VisitZone \"visitZone\"" : ""; $fieldCardNumber = isEmptyProperty($params, 'getCardNumber') ? ", MP.NumberHistorySickness \"cardNumber\"" : ""; $fieldClinic = $getClinic ? <<<"EOT"
        , MPP.name "clinic"
        , MP.medPatientPolyclinicCode "clinicId"
EOT
: ''; $fieldFirstSector = $getFirstSector ? ", MPG.Name \"sectorFirstName\"" : ""; $joinMPASS = $getAge || $getAddress ? "left join ManPassport MPASS on MPASS.ManCode = MP.ManCode" : ""; $joinSector = $getSector || $getVisitZone ? "left join lateral (
                SELECT
                    _VISIT_ZONE.VisitZone,
                    _VISIT_ZONE.MedPatientGroupCode,
                    _PGN.Name
                FROM (
                    SELECT
                        _T.MedPatientGroupCode,
                        _T.VisitZone
                    FROM (
                        SELECT
                            _AG.MedPatientGroupCode,
                            _PG.VisitZone
                        FROM MedPatientAddGroup _AG
                        LEFT JOIN medPatientGroup _PG on _PG.medPatientGroupCode = _AG.MedPatientGroupCode
                        WHERE _AG.MedPatientCode = MP.medPatientCode
                        UNION
                        SELECT
                            _MP.MedPatientGroupCode,
                            _PG.VisitZone
                        FROM MedPatient _MP
                        LEFT JOIN medPatientGroup _PG on _PG.medPatientGroupCode = _MP.MedPatientGroupCode
                        WHERE _MP.MedPatientCode = MP.medPatientCode
                        ORDER BY 2 desc, 1 desc) AS _T
                        LIMIT 1
                    ) _VISIT_ZONE
                    LEFT JOIN medPatientGroup _PGN on _PGN.medPatientGroupCode = _VISIT_ZONE.MedPatientGroupCode
                ) VISIT_ZONE on true" : ""; $joinClinic = $getClinic ? 'left join MedPatientPolyclinic MPP on MPP.MedPatientPolyclinicCode = MP.MedPatientPolyclinicCode' : ''; $joinFirstSector = $getFirstSector ? 'LEFT JOIN MedPatientGroup AS MPG ON MPG.MedPatientGroupCode = MP.MedPatientGroupCode' : ''; $sql = <<<EOT
            SELECT
                $fieldFio
                $fieldsAge
                $fieldAddress
                $fieldsSector
                $fieldVisitZone
                $fieldCardNumber
                $fieldClinic
                $fieldFirstSector
            FROM MedPatient MP
            $joinMPASS
            $joinSector
            $joinClinic
            $joinFirstSector
            WHERE MP.MedPatientCode = $patientId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetch(); if (isEmptyProperty($params, 'isBusy')) { $par = new stdClass(); $par->patientId = $patientId; $par->currBusy = true; $ret['busy'] = $this->readPatientBusy($par); } if ($currContingents = isEmptyProperty($params, 'isContingents')) { $par = new stdClass(); $par->patientId = $patientId; $par->currContingents = is_bool($currContingents) ? true : Date("Ymd", safe_strtotime($currContingents)); $ret['contingents'] = $this->readPatientContingents($par); } return $ret; } public function readPatientDeseases($params) { $db = $this->db ?: $this->db = connectDb(); $sqlDeseases = <<<"EOT"
select
    mpd.MedPatientDeseasesCode "id",
    mpd.MedDiagnosisCode "diagnosisId",
    md.Cipher1 "cipher",
    md.Name "name",
    mpd.DateDeseases "dateDeseases",
    mpd.MedPatientCode "patientCode"
from MedPatient mp
    join MedPatientDeseases mpd on mpd.MedPatientCode = mp.MedPatientCode
    left join MedDiagnosis AS md on md.MedDiagnosisCode = mpd.MedDiagnosisCode
where mp.MedPatientCode = ?
order by mpd.DateDeseases desc
EOT;
$stmtDeseases = $db->prepare($sqlDeseases); $stmtDeseases->execute([$params->patientCode]); return $stmtDeseases->fetchAll(); } public function createPatientDeseases($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into MedPatientDeseases (MedDiagnosisCode, DateDeseases, MedPatientCode)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'dateDeseases'), isUndefinedProperty($row, 'patientCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedPatientDeseases'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientDeseases($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedPatientDeseases set
       MedDiagnosisCode = case when ? = 1 then ? else MedDiagnosisCode end,
       DateDeseases = case when ? = 1 then ? else DateDeseases end
where MedPatientDeseasesCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'dateDeseases'), isUndefinedProperty($row, 'dateDeseases'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePatientDeseases($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from MedPatientDeseases where MedPatientDeseasesCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function replacePatient($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $deleteId = isEmptyProperty($params, 'deleteId', 0); $replaceId = isEmptyProperty($params, 'replaceId', 0); if ($deleteId === 0 || $replaceId === 0) { return null; } if (!testUserAccess(self::PATIENT_EDIT_DELETE_PERMISSION)) { throw new Exception(self::ERROR_NO_EDIT_DELETE_PERMISSION, ERROR_NOT_ALLOWED); } $res = new stdClass; $sql = <<<"EOT"
select MP.NumberHistorySickness "number",
       FN_cmFIO(MP.ManCode) "name",
       MP.ManCode "manCode"
from MedPatient MP
inner join MedBarCode MBC on MBC.ManCode = MP.ManCode
      and current_date between COALESCE(MBC.StartDate, 19000101) and COALESCE(MBC.StopDate, 30000101)
where MP.MedPatientCode = :MedPatientCode
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId]); $rowD = $stmt->fetch(); $stmt->execute([$replaceId]); $rowR = $stmt->fetch(); if ($rowD && $rowR) { $res->error = 'Удаление с заменой невозможно, поскольку обе записи содержат информацию о действующей персональной карте пациента:<br>' . $rowD['name'] . ' - ' . $rowD['number'] . '<br>' . $rowR['name'] . ' - ' . $rowR['number'] . '<br>' . 'Для удаления с заменой необходимо оставить только один номер карты действующим, отменив второй.'; return $res; } $sql = <<<"EOT"
select StomCardCode "stomCardCode"
from StomCard
where MedPatientCode = :MedPatientCode
  and EnterpriseCode = :EnterpriseCode
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId, $enterpriseCode]); $rowD = $stmt->fetch(); $stmt->execute([$replaceId, $enterpriseCode]); $rowR = $stmt->fetch(); $db->beginTransaction(); try { if ($rowD && $rowR) { $sql = <<<"EOT"
select PR_Replace( :TableName, :OldKey, :NewKey )
EOT;
$stmt = $db->prepare($sql); $stmt->execute(['stomCard', $rowR['stomCardCode'], $rowD['stomCardCode']]); } $sql = <<<"EOT"
delete from MedPatientInfo where MedPatientCode = :MedPatientCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId]); $sql = <<<"EOT"
update medInspect set State = 1
where COALESCE(State,0) <> 1
  and MedInspectCodeParent in
      ( select I1.medInspectCode
        from medInspect as I1, medInspect as I2
        where I1.medPatientCode = :OldPatient
          and I2.medPatientCode = :NewPatient
          and I1.EnterpriseCode = I2.EnterpriseCode
          and I1.PlanDate = I2.PlanDate
          and I1.MedPreventCode = I2.MedPreventCode )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId, $replaceId]); $sql = <<<"EOT"
update medInspect AS _t0_ SET state = 1
from medInspect AS I1 , medInspect AS I2
where (_t0_.medinspectcode = I1.medinspectcode)
   and ( I1.medPatientCode = :OldPatient
   and I2.medPatientCode = :NewPatient
   and I1.EnterpriseCode = I2.EnterpriseCode
   and I1.PlanDate = I2.PlanDate
   and I1.MedPreventCode = I2.MedPreventCode
   and COALESCE(I1.State,0) <> (1))
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId, $replaceId]); $sql = <<<"EOT"
delete from medInspect
where MedInspectCodeParent in
      ( select I1.medInspectCode
        from medInspect AS I1 , medInspect AS I2
        where I1.medPatientCode = :OldPatient
          and I2.medPatientCode = :NewPatient
          and I1.EnterpriseCode = I2.EnterpriseCode
          and I1.PlanDate = I2.PlanDate
          and I1.MedPreventCode = I2.MedPreventCode )
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId, $replaceId]); $sql = <<<"EOT"
delete from medInspect AS _t0_
using   medInspect AS I1 ,
    medInspect AS I2
where (_t0_.medinspectcode = I1.medinspectcode)
  and (I1.medPatientCode = :OldPatient
  and I2.medPatientCode = :NewPatient
  and I1.EnterpriseCode = I2.EnterpriseCode
  and I1.PlanDate = I2.PlanDate
  and I1.MedPreventCode = I2.MedPreventCode)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$deleteId, $replaceId]); $sql = <<<"EOT"
select PR_Replace( :TableName, :OldKey, :NewKey, null, FN_medGetPatientInfo(:Old_Key) )
EOT;
$stmt = $db->prepare($sql); $stmt->execute(['medPatient', $deleteId, $replaceId, $deleteId]); $sql = <<<"EOT"
update MedBarCode
set ManCode = (select manCode from medpatient where medPatientCode = :newCode limit 1)
where ManCode = (select manCode from medpatient where medPatientCode = :oldCode limit 1)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$replaceId, $deleteId]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $res->success = true; return ($res); } public function replacePatientEx($params = null) { $db = $this->db ?: $this->db = connectDb(); $deleteId = isEmptyProperty($params, 'deleteId', 0); $replaceId = isEmptyProperty($params, 'replaceId', 0); if (empty($deleteId) || empty($replaceId)) { return null; } else { if ($replaceId === $deleteId) { return true; } } $sql = <<<"EOT"
select PR_SyReplace( :TableName, :OldKey, :NewKey, FN_medGetPatientInfo(:Old_Key) )
EOT;
$stmt = $db->prepare($sql); $stmt->execute(['medPatient', $deleteId, $replaceId, $deleteId]); return true; } public function deletePatientsEx($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DO $$
BEGIN
DELETE FROM pmsOrderPayment WHERE pmsOrderCode IN (SELECT pmsOrderCode FROM pmsOrder WHERE MedPatientCode $in);
DELETE FROM pmsOrderServiceWealth WHERE pmsOrderServiceCode IN (SELECT POS.pmsOrderServiceCode FROM pmsOrder PO
                                                                JOIN pmsOrderService POS ON POS.pmsOrderCode = PO.pmsOrderCode
                                                                WHERE PO.medPatientCode $in);
DELETE FROM pmsOrderService WHERE pmsOrderCode IN (SELECT pmsOrderCode FROM pmsOrder WHERE MedPatientCode $in);
DELETE FROM pmsOrder WHERE MedPatientCode $in;
DELETE FROM MedPatient where MedPatientCode $in;
END; $$
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPatientRegistration($params = null) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId'); $sql = <<<SQL
            SELECT
                MPR.MedPatientRegistrationCode  "id",
                MPR.MedPatientCode              "patientId",
                MPR.StartDate                   "startDate",
                (
                    CASE WHEN
                        MPR.FinishDate = 30000101
                            OR MPR.FinishDate IS NULL
                    THEN NULL
                    ELSE MPR.FinishDate
                    END
                )                               "finishDate",
                MPR.OutReasonEx                 "outReasonId",
                MPR.MedPatientGroupCode         "sectorId",
                MPG.Name                        "sectorName",
                MPR.MedPatientPolyclinicCode    "clinicId",
                MPP.Name                        "clinicName",
                MPR.RegistrationNumber          "registrationNumber",
                (
                    CASE WHEN
                        COALESCE(MPR.FinishDate, 30000101)::DATE = 30000101
                            OR MPR.FinishDate > 'NOW'::DATE
                    THEN 1
                    ELSE 0
                    END
                )                               "isActive",
                ROW_NUMBER() OVER (ORDER BY MPR.StartDate DESC)
                                                "rowNumber",
                MPR.AddressKind                  "addressKind",
                MPR.AddressCode                  "addressId",
                MPR.AddressHouse                 "house",
                MPR.AddressCorps                 "corps",
                MPR.AddressFlat                  "flat",
                CASE CRV.Code
                    WHEN 'residence' THEN false
                    WHEN 'temporary' THEN true
                END                              "isTemporaryRegistration",
                CASE CRV1.Code
                    WHEN 'main' THEN 1
                    WHEN 'additional' THEN 2
                END                              "registrationType",
                MPR.CreateDate                   "registrationDate",
                MEF.FileName                     "fileName",
                MPR.Json                         "json",
                MPR.AddressText                  "addressText",
                MPR.CiszAddressCode              "addressCiszCode",
                CASE WHEN c.name IS NOT NULL AND TRIM(c.name) <> '' AND c.name NOT ILIKE '%беларус%' AND c.name <> 'РБ'
                THEN true ELSE false END AS "isForeign"
            FROM MedPatientRegistration MPR
            LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MPR.MedPatientGroupCode
            LEFT JOIN MedPatientPolyclinic MPP ON MPP.MedPatientPolyclinicCode = MPR.MedPatientPolyclinicCode
            LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MPR.CiszResourceValueCodeType
            LEFT JOIN CiszResourceValue CRV1 ON CRV1.CiszResourceValueCode = MPR.CiszResourceValueCodeSubType
            LEFT JOIN MedExternalFileT MEF ON MEF.Source = 112 AND MEF.SourceCode = MPR.MedPatientRegistrationCode
            LEFT JOIN Address A ON A.AddressCode = MPR.AddressCode
            LEFT JOIN Country C ON C.CountryCode = A.CountryCode
            WHERE MPR.MedPatientCode = ?
            ORDER BY MPR.StartDate DESC
            SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId ]); $rows = $stmt->fetchAll(PDO::FETCH_OBJ); foreach ($rows as $row) { $row->address = $this->buildAddressFromRegistration( $db, $row->addressId, $row->house, $row->corps, $row->flat, $row->addressText ); } return $rows; } public function buildAddressFromRegistration($db, $addressCode, $house, $corps, $flat, $addressText = null): string { if (!empty($addressText)) { $addressText = trim($addressText); if (!empty($flat)) { $addressText .= ', кв. ' . trim($flat); } return $addressText; } if (!$addressCode) { return ''; } $sql = <<<SQL
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
$stmt = $db->prepare($sql); $stmt->execute([$addressCode]); $a = $stmt->fetch(PDO::FETCH_ASSOC); if (!$a) { return ''; } $parts = []; if (!empty($a['place'])) { $place = trim($a['place']); if (!empty($a['placetype'])) { $place = trim($a['placetype']) . ' ' . $place; } $parts[] = $place; } if (!empty($a['street'])) { $street = trim($a['street']); if (!empty($a['streettype'])) { $street = trim($a['streettype']) . ' ' . $street; } $parts[] = $street; } $housePart = []; if ($house) { $housePart[] = 'д. ' . trim($house); } if ($corps) { $housePart[] = 'к. ' . trim($corps); } if ($flat) { $housePart[] = 'кв. ' . trim($flat); } if ($housePart) { $parts[] = implode(' ', $housePart); } return implode(', ', $parts); } private function resolveRegistrationKindCode(PDO $db, ?bool $isTemporary): ?int { if ($isTemporary === null) { return null; } $code = $isTemporary ? 'temporary' : 'residence'; $stmt = $db->prepare( 'SELECT CRV.CiszResourceValueCode
         FROM CiszResourceValue CRV
         JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = ?
         WHERE CRV.Code = ?' ); $stmt->execute(['ReasonForServiceCare', $code]); return $stmt->fetchColumn() ?: null; } private function resolveRegistrationTypeCode(PDO $db, ?int $type): ?int { if ($type === null) { return null; } $map = [ 1 => 'main', 2 => 'additional' ]; if (!isset($map[$type])) { return null; } $stmt = $db->prepare( 'SELECT CRV.CiszResourceValueCode
         FROM CiszResourceValue CRV
         JOIN CiszResource CR ON CR.CiszResourceCode = CRV.CiszResourceCode AND CR.Identifier = ?
         WHERE CRV.Code = ?' ); $stmt->execute(['SubTypeServiceCare', $map[$type]]); return $stmt->fetchColumn() ?: null; } public function createPatientRegistration($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<SQL
            INSERT INTO MedPatientRegistration (
                FinishDate, MedPatientCode, MedPatientPolyclinicCode,
                OutReasonEx, StartDate, MedPatientGroupCode,
                RegistrationNumber, AddressKind, AddressCode,
                AddressHouse, AddressCorps, AddressFlat,
                Json, CiszResourceValueCodeType, CiszResourceValueCodeSubType,
                CreateDate, AddressText, CiszAddressCode
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $typeCode = $this->resolveRegistrationKindCode($db, isUndefinedProperty($row, 'isTemporaryRegistration')); $subTypeCode = $this->resolveRegistrationTypeCode($db, isUndefinedProperty($row, 'registrationType')); $stmt->execute([ isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'clinicId'), isUndefinedProperty($row, 'outReasonId'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'sectorId'), isUndefinedProperty($row, 'registrationNumber'), isUndefinedProperty($row, 'addressKind'), isUndefinedProperty($row, 'addressId'), isUndefinedProperty($row, 'house'), isUndefinedProperty($row, 'corps'), isUndefinedProperty($row, 'flat'), isUndefinedProperty($row, 'json'), $typeCode, $subTypeCode, isUndefinedProperty($row, 'registrationDate'), isUndefinedProperty($row, 'addressText'), isUndefinedProperty($row, 'addressCiszCode') ]); $ret[] = $rec = new stdClass(); $rec->id = $db->lastInsertId('seq_MedPatientRegistration'); } $this->updateCiszStatus($row); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePatientRegistration($records) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<SQL
            UPDATE MedPatientRegistration SET
                FinishDate                  = CASE WHEN ? = 1 THEN ? ELSE FinishDate END,
                MedPatientPolyclinicCode    = CASE WHEN ? = 1 THEN ? ELSE MedPatientPolyclinicCode END,
                OutReasonEx                 = CASE WHEN ? = 1 THEN ? ELSE OutReasonEx END,
                StartDate                   = CASE WHEN ? = 1 THEN ? ELSE StartDate END,
                MedPatientGroupCode         = CASE WHEN ? = 1 THEN ? ELSE MedPatientGroupCode END,
                RegistrationNumber          = CASE WHEN ? = 1 THEN ? ELSE RegistrationNumber END,
                AddressKind                 = CASE WHEN ? = 1 THEN ? ELSE AddressKind END,
                AddressCode                 = CASE WHEN ? = 1 THEN ? ELSE AddressCode END,
                AddressHouse                = CASE WHEN ? = 1 THEN ? ELSE AddressHouse END,
                AddressCorps                = CASE WHEN ? = 1 THEN ? ELSE AddressCorps END,
                AddressFlat                 = CASE WHEN ? = 1 THEN ? ELSE AddressFlat END,
                Json                        = CASE WHEN ? = 1 THEN ? ELSE Json END,
                CiszResourceValueCodeType   = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeType END,
                CiszResourceValueCodeSubType= CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeSubType END,
                CreateDate                  = CASE WHEN ? = 1 THEN ? ELSE CreateDate END,
                AddressText                 = CASE WHEN ? = 1 THEN ? ELSE AddressText END,
                CiszAddressCode             = CASE WHEN ? = 1 THEN ? ELSE CiszAddressCode END
            WHERE MedPatientRegistrationCode = ?
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $typeCode = $this->resolveRegistrationKindCode($db, isUndefinedProperty($row, 'isTemporaryRegistration')); $subTypeCode = $this->resolveRegistrationTypeCode($db, isUndefinedProperty($row, 'registrationType')); $stmt->execute([ propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'clinicId'), isUndefinedProperty($row, 'clinicId'), propertyExistsSQL($row, 'outReasonId'), isUndefinedProperty($row, 'outReasonId'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'sectorId'), isUndefinedProperty($row, 'sectorId'), propertyExistsSQL($row, 'registrationNumber'), isUndefinedProperty($row, 'registrationNumber'), propertyExistsSQL($row, 'addressKind'), isUndefinedProperty($row, 'addressKind'), propertyExistsSQL($row, 'addressId'), isUndefinedProperty($row, 'addressId'), propertyExistsSQL($row, 'house'), isUndefinedProperty($row, 'house'), propertyExistsSQL($row, 'corps'), isUndefinedProperty($row, 'corps'), propertyExistsSQL($row, 'flat'), isUndefinedProperty($row, 'flat'), propertyExistsSQL($row, 'json'), isUndefinedProperty($row, 'json'), propertyExistsSQL($row, 'isTemporaryRegistration'), $typeCode, propertyExistsSQL($row, 'registrationType'), $subTypeCode, propertyExistsSQL($row, 'registrationDate'), isUndefinedProperty($row, 'registrationDate'), propertyExistsSQL($row, 'addressText'), isUndefinedProperty($row, 'addressText'), propertyExistsSQL($row, 'addressCiszCode'), isUndefinedProperty($row, 'addressCiszCode'), isUndefinedProperty($row, 'id') ]); $rec->id = isUndefinedProperty($row, 'id'); $this->updateCiszStatus($row); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePatientRegistration($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedPatientRegistration', 'MedPatientRegistrationCode', $records); } public function getNextRegistrationNumber($params) { $db = $this->db ?: $this->db = connectDb(); $year = isEmptyProperty($params, 'year'); $sql = <<<SQL
            SELECT INCSTR(COALESCE(RegistrationNumber, '')) "nextNumber"
            FROM MedPatientRegistration
            WHERE DATE_PART('YEAR', StartDate) = ?
            ORDER BY LPAD(RegistrationNumber, 20, '0') DESC NULLS LAST
            LIMIT 1
            SQL;
$ret = selectSqlValue($db, $sql, [ $year ]); return $ret; } public function checkRegistrationNumber($params) { $db = $this->db ?: $this->db = connectDb(); $regNumber = isEmptyProperty($params, 'regNumber'); $year = isEmptyProperty($params, 'year'); $sql = <<<SQL
            SELECT EXISTS(
                SELECT 1
                FROM MedPatientRegistration
                WHERE RegistrationNumber = ?
                    AND DATE_PART('YEAR', StartDate) = ?
            )
            SQL;
$ret = selectSqlValue($db, $sql, [ $regNumber, $year ]); return !$ret; } public function findDeregisterPatient($params){ $finishDate = dbDate(isEmptyProperty($params, 'finishDate')); $finishAge = isEmptyProperty($params, 'finishAge'); $groupCode = isEmptyProperty($params, 'medPatientGroupCode'); $dateTime = new DateTime($finishDate); $formattedDate = $dateTime->format('d.m.Y'); $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            SELECT pass.dateofbirth, mp.ManCode, mp.medPatientCode, mp.finishdate
                FROM medpatient AS mp
                LEFT JOIN ManPassport AS pass ON pass.ManCode = mp.ManCode
                WHERE EXTRACT(YEAR FROM AGE(?, pass.dateofbirth)) = ? AND mp.finishdate > ?
        SQL;
if ($groupCode) { $sql .= "AND mp.medpatientgroupcode = ?"; } $stmt = $db->prepare($sql); if ($groupCode) { $stmt->execute([$formattedDate, $finishAge, $formattedDate, $groupCode]); } else { $stmt->execute([$formattedDate, $finishAge, $formattedDate]); } return $stmt->fetchAll(); } public function deregisterPatient($params){ $arr = isEmptyProperty($params, 'result'); $inspectEndDate = dbDate(isEmptyProperty($params, 'inspectDate')); $finishDate = dbDate(isEmptyProperty($params, 'finishDate')); $motivediscard = 3; $outReason = 2; $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try{ $sqlPatinet = <<<SQL
                UPDATE medpatientregistration SET
                finishdate =  ?,
                outreasonex = ?
                WHERE medpatientregistrationcode =
                (SELECT medpatientregistrationcode FROM medpatientregistration WHERE medpatientcode = ? ORDER BY finishdate DESC LIMIT 1)
            SQL;
$stmt = $db->prepare($sqlPatinet); $sqlDispensary = <<<SQL
                UPDATE MedDispensary SET
                finishdate = ?,
                motivediscard = ?
                WHERE medpatientcode = ?
            SQL;
$stmtInspect = $db->prepare($sqlDispensary); foreach ($arr as $item){ $medPatientCode = $item->medpatientcode; $stmt->execute([$finishDate, $outReason, $medPatientCode]); $stmtInspect->execute([$inspectEndDate, $motivediscard, $medPatientCode]); } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function readPatientRelatives(object $params): array { $db = $this->db ?: $this->db = connectDb(); $onlyAlive = isEmptyProperty($params, 'onlyAlive'); $onlyRepresentativeOrContact = isEmptyProperty($params, 'onlyRepresentativeOrContact'); $rows = selectSql($db, <<<SQL
            SELECT
                MR.MedRelativesCode AS "id",
                MR.MedPatientCode AS "patientId",
                MR.ManCode AS "manId",
                fn_cmFio(MR.ManCode) AS "fio",
                P.ManPassportCode AS "manPassportId",
                P.Sex AS "sex",
                P.DateOfBirth AS "dateOfBirth",
                P.PassportNumber AS "passportNumber",
                P.Authority AS "authority",
                P.DateOfIssue AS "dateOfIssue",
                P.DateOfExpiry AS "dateOfExpiry",
                P.PersonalNumber AS "personalNumber",
                P.AddressCode AS "addressId",
                fn_cmCalcAddress(MR.ManCode, 0, 0, 1) AS "address",
                P.AddressHouse AS "addressHouse",
                P.AddressCorps AS "addressCorps",
                P.AddressFlat AS "addressFlat",
                P.ZipCode AS "addressZipcode",
                P.AddressResideCode AS "addressResideId",
                fn_cmCalcAddress(MR.ManCode, 1, 0, 1) AS "addressReside",
                P.AddressResideHouse AS "addressResideHouse",
                P.AddressResideCorps AS "addressResideCorps",
                P.AddressResideFlat AS "addressResideFlat",
                P.ZipCodeReside AS "addressResideZipcode",
                MR.RelationDegree AS "degreeOld",
                MR.CiszResourceValueCodeFamilyMemeber AS "degree",
                MP.JSON AS "json",
                PH.phones AS "phones",
                MRF.MedRelativesFeatureCode AS "disabilityId",
                MRF.StartDate AS "disabilityStart",
                MRF.FinishDate AS "disabilityEnd",
                MRF.DocumentNumber AS "disabilityDocNum",
                MRF.DocumentSeries AS "disabilityDocSer",
                MRF.Enterprise AS "disabilityEnterprise",
                 MRF.DocumentDate AS "disabilityDocDate",
                MRF.CiszResourceValueCode AS "ciszDisabilityGroupId",
                P.ManPassportKindCode AS "passportKind",
                MR.CiszResourceValueCodeRelatedType AS "relativeType",
                CRVT.Name AS "relativeTypeName",
                MR.Note AS "note",
                (MR.Attributes & 1) <> 0 AS "isContact",
                CRV.Name AS "degreeName",
                P.AddressText "addressText",
                P.CiszAddressCode "addressCiszCode",
                P.CiszAddressResideCode "addressResideCiszCode",
                P.AddressResideText "addressResideText",
                        CASE
                    WHEN p.CiszAddressCode IS NOT NULL
                         OR (
                              cr.name IS NOT NULL
                              AND TRIM(cr.name) <> ''
                              AND cr.name NOT ILIKE '%беларус%'
                              AND cr.name <> 'РБ'
                         )
                    THEN true
                    ELSE false
                        END AS "isCiszAddress",
                        CASE
                    WHEN p.CiszAddressResideCode IS NOT NULL
                         OR (
                              c.name IS NOT NULL
                              AND TRIM(c.name) <> ''
                              AND c.name NOT LIKE '%Беларус%'
                              AND c.name <> 'РБ'
                         )
                    THEN true
                    ELSE false
                        END AS "isCiszAddressReside",
                    CASE WHEN cr.name IS NOT NULL AND TRIM(cr.name) <> '' AND cr.name NOT ILIKE '%беларус%' AND cr.name <> 'РБ'
                    THEN true ELSE false END AS "isForeignAddress",
                    CASE WHEN c.name IS NOT NULL AND TRIM(c.name) <> '' AND c.name NOT ILIKE '%беларус%' AND c.name <> 'РБ'
                    THEN true ELSE false END AS "isForeignAddressReside"
            FROM
                MedRelatives MR
                JOIN MedPatient MP ON MP.MedPatientCode = MR.MedPatientCode
                LEFT JOIN ManPassport P ON P.ManCode = MR.ManCode
                LEFT JOIN LATERAL (
                SELECT json_agg(json_build_object(
                    'id', MPH.ManPhoneCode,
                    'number', MPH.PhoneNumber,
                    'type', MPH.PhoneType
                )) AS phones
                FROM ManPhone MPH
                WHERE MPH.ManCode = MR.ManCode AND MPH.PhoneNumber <> '') PH ON TRUE
                LEFT JOIN MedRelativesFeature MRF ON MRF.MedRelativesCode = MR.MedRelativesCode
                    AND MRF.CiszResourceValueCode IN (SELECT CV.CiszResourceValueCode
                                                      FROM CiszResourceValue AS CV
                                                      JOIN CiszResource AS C ON CV.CiszResourceCode = C.CiszResourceCode
                                                      WHERE C.Identifier like 'DisabilityGroup')
                LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MR.CiszResourceValueCodeFamilyMemeber
                LEFT JOIN Address AS a ON a.AddressCode = p.AddressResideCode
                LEFT JOIN Country AS c ON c.CountryCode = a.CountryCode
                LEFT JOIN Address AS ar ON ar.AddressCode = p.AddressCode
                LEFT JOIN Country AS cr ON cr.CountryCode = ar.CountryCode
                LEFT JOIN CiszResourceValue CRVT ON MR.CiszResourceValueCodeRelatedType = CRVT.CiszResourceValueCode
            WHERE
                MR.MedPatientCode = :patientId
            ORDER BY 4, 1
        SQL,
[':patientId' => $params->patientId] ); foreach ($rows as &$row) { if (!empty($row['phones'])) { $phones = safe_json_decode($row['phones']); $row['phones'] = is_array($phones) ? $phones : []; } else { $row['phones'] = []; } $data = safe_json_decode($row['json']); $relative = arrayFindObject($data->relatives ?? [], 'id', $row['id']); $row['maritalStatus'] = $relative->maritalStatus ?? null; $row['isLegalRepresentative'] = $relative->isLegalRepresentative ?? false; $row['isContactPerson'] = $relative->isContactPerson ?? false; $row['isDeceased'] = $relative->isDeceased ?? false; $row['diagnosisId'] = $relative->diagnosisId ?? null; $row['conditionType'] = $relative->conditionType ?? null; unset($row['json']); } if ($onlyAlive) { $rows = array_filter($rows, static function ($row) { return !$row['isDeceased']; }); } if ($onlyRepresentativeOrContact) { $rows = array_filter($rows, static function ($row) { return $row['isLegalRepresentative'] || $row['isContactPerson']; }); } $rows = array_values($rows); return $rows; } private function generateNewPatientCardCode() { $db = $this->db ?: $this->db = connectDb(); $stmt = $db->query("SELECT COALESCE(MAX(MedPatientCardCode), 0) + 1 FROM MedPatientCard"); return (int)$stmt->fetchColumn(); } public function createPatientRelatives($records): array { return $this->insertOrUpdateRelatives($records, true); } public function updatePatientRelatives($records): array { return $this->insertOrUpdateRelatives($records, false); } public function deletePatientRelatives($records): bool { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedRelatives', 'MedRelativesCode', $records); } private function insertOrUpdateRelatives($records, $isInsert): array { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $ret = []; $sqlMan = <<<EOT
            INSERT INTO Man (ManCode, SurName, Name, SecondName)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (ManCode) DO UPDATE SET
                SurName     = EXCLUDED.SurName,
                Name        = EXCLUDED.Name,
                SecondName  = EXCLUDED.SecondName
            RETURNING ManCode AS id
        EOT;
$stmtMan = $db->prepare($sqlMan); $sqlManPassport = <<<"EOT"
            INSERT INTO ManPassport (
                ManPassportCode, ManCode, Sex, DateOfBirth, AddressCode, AddressResideCode, PassportNumber, Authority, DateOfIssue,
                DateOfExpiry, PersonalNumber, AddressHouse, AddressCorps, AddressFlat, Zipcode, AddressResideHouse,
               AddressResideCorps, AddressResideFlat, ZipcodeReside, ManPassportKindCode, AddressResideText, AddressText, CiszAddressCode, CiszAddressResideCode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (ManPassportCode) DO UPDATE SET
                Sex = case when ? = 1 then EXCLUDED.Sex else ManPassport.Sex end,
                DateOfBirth = case when ? = 1 then EXCLUDED.DateOfBirth else ManPassport.DateOfBirth end,
                AddressCode = case when ? = 1 then EXCLUDED.AddressCode else ManPassport.AddressCode end,
                AddressResideCode = case when ? = 1 then EXCLUDED.AddressResideCode else ManPassport.AddressResideCode end,
                PassportNumber = case when ? = 1 then EXCLUDED.PassportNumber else ManPassport.PassportNumber end,
                Authority = case when ? = 1 then EXCLUDED.Authority else ManPassport.Authority end,
                DateOfIssue = case when ? = 1 then EXCLUDED.DateOfIssue else ManPassport.DateOfIssue end,
                DateOfExpiry = case when ? = 1 then EXCLUDED.DateOfExpiry else ManPassport.DateOfExpiry end,
                PersonalNumber = case when ? = 1 then EXCLUDED.PersonalNumber else ManPassport.PersonalNumber end,
                AddressHouse = case when ? = 1 then EXCLUDED.AddressHouse else ManPassport.AddressHouse end,
                AddressCorps = case when ? = 1 then EXCLUDED.AddressCorps else ManPassport.AddressCorps end,
                AddressFlat = case when ? = 1 then EXCLUDED.AddressFlat else ManPassport.AddressFlat end,
                Zipcode = case when ? = 1 then EXCLUDED.Zipcode else ManPassport.Zipcode end,
                AddressResideHouse = case when ? = 1 then EXCLUDED.AddressResideHouse else ManPassport.AddressResideHouse end,
                AddressResideCorps = case when ? = 1 then EXCLUDED.AddressResideCorps else ManPassport.AddressResideCorps end,
                AddressResideFlat = case when ? = 1 then EXCLUDED.AddressResideFlat else ManPassport.AddressResideFlat end,
               ZipcodeReside = case when ? = 1 then EXCLUDED.ZipcodeReside else ManPassport.ZipcodeReside end,
                ManPassportKindCode = case when ? = 1 then EXCLUDED.ManPassportKindCode else ManPassport.ManPassportKindCode end,
                AddressResideText = case when ? = 1 then EXCLUDED.AddressResideText else ManPassport.AddressResideText end,
                AddressText = case when ? = 1 then EXCLUDED.AddressText else ManPassport.AddressText end,
                CiszAddressCode = case when ? = 1 then EXCLUDED.CiszAddressCode else ManPassport.CiszAddressCode end,
                CiszAddressResideCode = case when ? = 1 then EXCLUDED.CiszAddressResideCode else ManPassport.CiszAddressResideCode end
            RETURNING ManPassportCode AS id
        EOT;
$stmtManPassport = $db->prepare($sqlManPassport); $sqlMedRelatives = <<<"EOT"
            INSERT INTO MedRelatives (MedRelativesCode, MedPatientCode, ManCode, CiszResourceValueCodeFamilyMemeber, CiszResourceValueCodeRelatedType,
                                      Note, Attributes)
            VALUES (?, ?, ?, ?, ?,
                    ?, ?)
            ON CONFLICT (MedRelativesCode) DO UPDATE SET
                CiszResourceValueCodeFamilyMemeber = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeFamilyMemeber ELSE MedRelatives.CiszResourceValueCodeFamilyMemeber END,
                CiszResourceValueCodeRelatedType = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeRelatedType ELSE MedRelatives.CiszResourceValueCodeRelatedType END,
                Note = CASE WHEN ? = 1 THEN EXCLUDED.Note ELSE MedRelatives.Note END,
                Attributes =
                    (COALESCE(MedRelatives.Attributes, 0) & ~1)
                    | CASE WHEN ? = 1 THEN 1 ELSE 0 END
            RETURNING MedRelativesCode AS id
        EOT;
$stmtMedRelatives = $db->prepare($sqlMedRelatives); $sqlMedPatient = "UPDATE MedPatient SET JSON = ? WHERE MedPatientCode = ?"; $stmtMedPatient = $db->prepare($sqlMedPatient); $jsonData = selectSqlValue($db, "SELECT JSON FROM MedPatient WHERE MedPatientCode = ?", [$records[array_key_first($records)]->patientId] ); $jsonData = safe_json_decode($jsonData) ?? new stdClass(); $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $rec->manId = isUndefinedProperty($row, 'manId'); if (isset($row->fio) && isUndefinedProperty($row, 'fio')) { $aFio = trim($row->fio); $fio = preg_split("/[\s.,]+/u", $aFio); $stmtMan->execute([ isUndefinedProperty($row, 'manId'), $fio[0], $fio[1] ?? '', trim(implode(" ", (array_slice($fio, 2)))) ]); $rec->manId = fetchValue($stmtMan, 'id'); unset($fio); } $stmtManPassport->execute([ isUndefinedProperty($row, 'manPassportId'), $rec->manId, isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'dateOfBirth'), isUndefinedProperty($row, 'addressId'), isUndefinedProperty($row, 'addressResideId'), isUndefinedProperty($row, 'passportNumber'), isUndefinedProperty($row, 'authority'), isUndefinedProperty($row, 'dateOfIssue'), isUndefinedProperty($row, 'dateOfExpiry'), isUndefinedProperty($row, 'personalNumber'), isUndefinedProperty($row, 'addressHouse'), isUndefinedProperty($row, 'addressCorps'), isUndefinedProperty($row, 'addressFlat'), isUndefinedProperty($row, 'addressZipcode'), isUndefinedProperty($row, 'addressResideHouse'), isUndefinedProperty($row, 'addressResideCorps'), isUndefinedProperty($row, 'addressResideFlat'), isUndefinedProperty($row, 'addressResideZipcode'), isUndefinedProperty($row, 'passportKind'), isUndefinedProperty($row, 'addressResideText'), isUndefinedProperty($row, 'addressText'), isUndefinedProperty($row, 'addressCiszCode'), isUndefinedProperty($row, 'addressResideCiszCode'), propertyExistsSQL($row, 'sex'), propertyExistsSQL($row, 'dateOfBirth'), propertyExistsSQL($row, 'addressId'), propertyExistsSQL($row, 'addressResideId'), propertyExistsSQL($row, 'passportNumber'), propertyExistsSQL($row, 'authority'), propertyExistsSQL($row, 'dateOfIssue'), propertyExistsSQL($row, 'dateOfExpiry'), propertyExistsSQL($row, 'personalNumber'), propertyExistsSQL($row, 'addressHouse'), propertyExistsSQL($row, 'addressCorps'), propertyExistsSQL($row, 'addressFlat'), propertyExistsSQL($row, 'addressZipcode'), propertyExistsSQL($row, 'addressResideHouse'), propertyExistsSQL($row, 'addressResideCorps'), propertyExistsSQL($row, 'addressResideFlat'), propertyExistsSQL($row, 'addressResideZipcode'), propertyExistsSQL($row, 'passportKind'), propertyExistsSQL($row, 'addressResideText'), propertyExistsSQL($row, 'addressText'), propertyExistsSQL($row, 'addressCiszCode'), propertyExistsSQL($row, 'addressResideCiszCode') ]); $rec->manPassportId = fetchValue($stmtManPassport, 'id'); $isContact = (int)isUndefinedProperty($row, 'isContact'); $stmtMedRelatives->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'patientId'), $rec->manId, isUndefinedProperty($row, 'degree'), isUndefinedProperty($row, 'relativeType'), isUndefinedProperty($row, 'note'), $isContact, propertyExistsSQL($row, 'degree'), propertyExistsSQL($row, 'relativeType'), propertyExistsSQL($row, 'note'), $isContact ]); $rec->id = fetchValue($stmtMedRelatives, 'id'); if (!($relative = arrayFindObject($jsonData->relatives ?? [], 'id', $rec->id) ?? false)) { $jsonData->relatives[] = $relative = new stdClass(); } $relative->id = $rec->id; if ($row->maritalStatus) { $relative->maritalStatus = $row->maritalStatus; } $relative->isLegalRepresentative = $row->isLegalRepresentative; $relative->isContactPerson = $row->isContactPerson; $relative->isDeceased = $row->isDeceased; $relative->diagnosisId = $row->diagnosisId; $relative->conditionType = $row->conditionType; $stmtMedPatient->execute([ json_encode($jsonData), $row->patientId ]); $sqlFindFeature = <<<SQL
SELECT MRF.*
FROM MedRelativesFeature MRF
JOIN CiszResourceValue CV ON CV.CiszResourceValueCode = MRF.CiszResourceValueCode
JOIN CiszResource C ON C.CiszResourceCode = CV.CiszResourceCode
WHERE MRF.MedRelativesCode = ?
  AND C.Identifier LIKE 'DisabilityGroup'
SQL;
$stmtFindFeature = $db->prepare($sqlFindFeature); $stmtFindFeature->execute([$rec->id]); $existing = $stmtFindFeature->fetch(PDO::FETCH_ASSOC); $newCisz = property_exists($row, 'ciszDisabilityGroupId') ? ($row->ciszDisabilityGroupId ?: null) : ($existing['ciszresourcevaluecode'] ?? null); $updateFields = []; $updateValues = []; $fields = [ 'StartDate' => 'disabilityStart', 'FinishDate' => 'disabilityEnd', 'DocumentNumber' => 'disabilityDocNum', 'DocumentSeries' => 'disabilityDocSer', 'Enterprise' => 'disabilityEnterprise', 'CiszResourceValueCode' => null, 'DocumentDate' => 'disabilityDocDate' ]; foreach ($fields as $dbField => $jsonField) { if ($jsonField !== null && property_exists($row, $jsonField)) { $val = $row->$jsonField; $updateFields[] = "$dbField = ?"; $updateValues[] = ($val === '' ? null : $val); } } if ($newCisz !== ($existing['ciszresourcevaluecode'] ?? null)) { $updateFields[] = "CiszResourceValueCode = ?"; $updateValues[] = $newCisz; } if ($existing) { if ($newCisz === null || $newCisz === '') { $sql = "DELETE FROM MedRelativesFeature WHERE MedRelativesCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([$rec->id]); } else { if ($updateFields) { $sql = "UPDATE MedRelativesFeature SET " . implode(', ', $updateFields) . " WHERE MedRelativesCode = ?"; $stmt = $db->prepare($sql); $updateValues[] = $rec->id; $stmt->execute($updateValues); } } } else if ($newCisz !== null) { $sql = <<<SQL
INSERT INTO MedRelativesFeature (
    MedRelativesCode,
    StartDate,
    FinishDate,
    DocumentNumber,
    DocumentSeries,
    Enterprise,
    CiszResourceValueCode,
    DocumentDate
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $rec->id, $row->disabilityStart ?? null, $row->disabilityEnd ?? null, $row->disabilityDocNum ?? null, $row->disabilityDocSer ?? null, $row->disabilityEnterprise ?? null, $newCisz, $row->disabilityDocDate ?? null ]); } if (!empty($row->phones)) { $this->syncRelativesPhones($rec->manId, (array)$row->phones, $db); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function syncRelativesPhones(int $manId, array $phones, PDO $db): void { $stmtSelect = $db->prepare("SELECT ManPhoneCode, PhoneNumber, PhoneType FROM ManPhone WHERE ManCode = ?"); $stmtSelect->execute([$manId]); $existingPhones = $stmtSelect->fetchAll(PDO::FETCH_OBJ); $existingMap = []; foreach ($existingPhones as $p) { $existingMap[$p->manphonecode] = $p; } $incomingIds = []; foreach ($phones as $phone) { if (!empty($phone->id) && strpos($phone->id, 'extModel') !== 0) { $incomingIds[] = $phone->id; } } foreach ($existingMap as $id => $p) { if (!in_array($id, $incomingIds, true)) { $stmtDelete = $db->prepare("DELETE FROM ManPhone WHERE ManPhoneCode = ?"); $stmtDelete->execute([$id]); } } $stmtUpsert = $db->prepare(<<<'EOT'
        INSERT INTO ManPhone (ManPhoneCode, ManCode, PhoneNumber, PhoneType)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (ManPhoneCode) DO UPDATE SET
            PhoneNumber = EXCLUDED.PhoneNumber,
            PhoneType = EXCLUDED.PhoneType
        RETURNING ManPhoneCode AS id
    EOT)
; foreach ($phones as $phone) { $isNew = empty($phone->id) || strpos($phone->id, 'extModel') === 0; $phoneId = $isNew ? null : $phone->id; $stmtUpsert->execute([ $phoneId, $manId, $phone->number ?? '', $phone->type ?? 3 ]); $phone->id = fetchValue($stmtUpsert, 'id'); } } public function updateCiszStatus($records) { $statusCisz = selectSql($this->db, <<<SQL
        SELECT
           *
        FROM CiszLink
            where PrimaryKey = :id AND TableName = :entity AND Type = 1
 SQL,
[ ':id' => $records->patientId, ':entity' => 'MedPatient' ], PDO::FETCH_OBJ ); if ($statusCisz && $statusCisz[0]->status === 1) { executeSql($this->db, <<<SQL
            UPDATE CiszLink
            SET Status = 2
            WHERE PrimaryKey = :id AND TableName = :entity AND Type = 1
SQL,
[ ':id' => $records->patientId, ':entity' => 'MedPatient' ]); } return true; } public function countNumberOfDulicatePatientsByFIOandBirthday($params = null) { $id = isEmptyProperty($params, 'id'); $fio = isEmptyProperty($params, 'fio'); $dateOfBirth = isEmptyProperty($params, 'dateOfBirth'); $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $excludeWhere = !empty($id) ? '(MP.MedPatientCode <> :id) AND' : ''; $excludeParams = !empty($id) ? ['id' => $id] : []; $sql = <<<EOT
SELECT
  COUNT(*)
FROM
  MedPatient MP
  LEFT JOIN ManPassport AS Pass ON Pass.ManCode = MP.ManCode
WHERE
  $excludeWhere
  (MP.EnterpriseCode = :enterpriseCode) AND
  (FN_cmFIO(Pass.ManCode)::text = :fio) AND
  (Pass.DateOfBirth = :dateOfBirth)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ...$excludeParams, 'enterpriseCode' => $enterpriseCode, 'fio' => $fio, 'dateOfBirth' => $dateOfBirth, ]); $result = $stmt->fetchColumn(); return $result; } public function countNumberOfDulicatePatientsByDocumentNumber($params = null) { $id = isEmptyProperty($params, 'id'); $documentNumber = isEmptyProperty($params, 'documentNumber'); $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $excludeWhere = !empty($id) ? '(MP.MedPatientCode <> :id) AND' : ''; $excludeParams = !empty($id) ? ['id' => $id] : []; $sql = <<<EOT
SELECT
  COUNT(*)
FROM
  MedPatient MP
  LEFT JOIN ManPassport AS Pass ON Pass.ManCode = MP.ManCode
WHERE
  $excludeWhere
  (MP.EnterpriseCode = :enterpriseCode) AND
  (Pass.PersonalNumber = :documentNumber)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ...$excludeParams, 'enterpriseCode' => $enterpriseCode, 'documentNumber' => $documentNumber, ]); $result = $stmt->fetchColumn(); return $result; } } handleJsonRpc('med_common_patients');