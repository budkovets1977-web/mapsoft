<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_passport { private $db; public function readPassport($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'manId', 0); $sqlFromWhere = <<<"EOT"
FROM Man AS m
LEFT JOIN ManPassport AS pass ON pass.ManCode = m.ManCode
LEFT JOIN Address AS a ON a.AddressCode = pass.AddressResideCode
LEFT JOIN Address AS ar ON ar.AddressCode = pass.AddressCode
LEFT JOIN AddressPlaceType AS pt ON pt.AddressPlaceTypeCode = a.AddressPlaceTypeCode
LEFT JOIN AddressStreetType AS st ON st.AddressStreetTypeCode = a.AddressStreetTypeCode
WHERE m.ManCode = $id
EOT;
$sql = <<<"EOT"
    SELECT
        m.ManCode as "id",
        FN_cmFIO(m.ManCode)::text as "fio",
        FN_cmCalcAddress(m.ManCode, 0)::text as "address",
        FN_cmCalcAddress(m.ManCode, 1)::text as "addressReside",
        pass.ManPassportCode as "manPassportId",
        pass.AddressCode as "addressId",
        pass.AddressHouse as "addressHouse",
        pass.AddressCorps as "addressCorps",
        pass.AddressFlat as "addressFlat",
        pass.Zipcode as "addressZipcode",
        pass.AddressResideCode as "addressResideId",
        pass.AddressResideHouse as "addressResideHouse",
        pass.AddressResideCorps as "addressResideCorps",
        pass.AddressResideFlat as "addressResideFlat",
        pass.ZipcodeReside as "addressResideZipcode",
        pass.Sex as "sex",
        pass.DateOfBirth as "dateOfBirth",
        pass.PassportNumber as "passportNumber",
        pass.Authority as "authority",
        pass.DateOfIssue as "dateOfIssue",
        pass.DateOfExpiry as "dateOfExpiry",
        pass.PlaceOfBirth as "placeOfBirth",
        pass.PersonalNumber as "personalNumber",
        pass.Alien as "alien",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 1 ORDER BY ManPhoneCode DESC LIMIT 1) as "phoneId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 1 ORDER BY ManPhoneCode DESC LIMIT 1) as "phone",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhoneId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhone",
        (SELECT Note FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 3 ORDER BY ManPhoneCode DESC LIMIT 1) as "mobilePhoneOperator",
        (SELECT ManPhoneCode FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1) as "mailId",
        (SELECT PhoneNumber FROM ManPhone WHERE ManCode = m.ManCode AND PhoneType = 4 ORDER BY ManPhoneCode DESC LIMIT 1) as "mail"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(); } public function insertOrUpdatePassport($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $ret = []; $sqlMan = <<<"EOT"
INSERT INTO Man (ManCode, SurName, Name, SecondName)
VALUES (?, ?, ?, ?)
ON CONFLICT (ManCode) DO UPDATE SET
    SurName = case when ? = 1 then EXCLUDED.SurName else Man.SurName end,
    Name = case when ? = 1 then EXCLUDED.Name else Man.Name end,
    SecondName = case when ? = 1 then EXCLUDED.SecondName else Man.SecondName end
RETURNING ManCode AS id
EOT;
$stmtMan = $db->prepare($sqlMan); $sqlManPhone = <<<"EOT"
INSERT INTO ManPhone (ManPhoneCode, ManCode, PhoneNumber, PhoneType, Note)
VALUES (?, ?, ?, ?, ?)
ON CONFLICT (ManPhoneCode) DO UPDATE SET
    PhoneNumber = case when ? = 1 then EXCLUDED.PhoneNumber else ManPhone.PhoneNumber end,
    Note = case when ? = 1 then EXCLUDED.Note else ManPhone.Note end
RETURNING ManPhoneCode AS id
EOT;
$stmtManPhone = $db->prepare($sqlManPhone); $sqlManPassport = <<<"EOT"
INSERT INTO ManPassport (
    ManPassportCode, ManCode, Sex, DateOfBirth, AddressCode, AddressResideCode, PassportNumber, Authority, DateOfIssue,
    DateOfExpiry, PlaceOfBirth, PersonalNumber, AddressHouse, AddressCorps, AddressFlat, Zipcode, AddressResideHouse,
    AddressResideCorps, AddressResideFlat, ZipcodeReside, Alien)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (ManPassportCode) DO UPDATE SET
    Sex = case when ? = 1 then EXCLUDED.Sex else ManPassport.Sex end,
    DateOfBirth = case when ? = 1 then EXCLUDED.DateOfBirth else ManPassport.DateOfBirth end,
    AddressCode = case when ? = 1 then EXCLUDED.AddressCode else ManPassport.AddressCode end,
    AddressResideCode = case when ? = 1 then EXCLUDED.AddressResideCode else ManPassport.AddressResideCode end,
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
    Alien = case when ? = 1 then EXCLUDED.Alien else ManPassport.Alien end  
RETURNING ManPassportCode AS id
EOT;
$stmtManPassport = $db->prepare($sqlManPassport); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $rec->id = isUndefinedProperty($row, 'id') ? $row->id : null; if (isset($row->fio) && isUndefinedProperty($row, 'fio')) { $aFio = trim($row->fio); $fio = preg_split("/[\s.,]+/u", $aFio); $stmtMan->execute([ $rec->id, $fio[0], $fio[1], trim(implode(" ", (array_slice($fio, 2)))), isset($fio[0]) ? 1 : 0, isset($fio[1]) ? 1 : 0, isset($fio[2]) ? 1 : 0 ]); $rec->id = fetchValue($stmtMan, 'id'); unset($fio); } $stmtManPhone->execute([ isUndefinedProperty($row, 'phoneId'), $rec->id, isUndefinedProperty($row, 'phone'), 1, null, propertyExistsSQL($row, 'phone'), 0 ]); $rec->phoneId = fetchValue($stmtManPhone, 'id'); $stmtManPhone->execute([ isUndefinedProperty($row, 'mobilePhoneId'), $rec->id, isUndefinedProperty($row, 'mobilePhone'), 3, isUndefinedProperty($row, 'mobilePhoneOperator'), propertyExistsSQL($row, 'mobilePhone'), propertyExistsSQL($row, 'mobilePhoneOperator') ]); $rec->mobilePhoneId = fetchValue($stmtManPhone, 'id'); $stmtManPhone->execute([ isUndefinedProperty($row, 'mailId'), $rec->id, isUndefinedProperty($row, 'mail'), 4, null, propertyExistsSQL($row, 'mail'), 0 ]); $rec->mailId = fetchValue($stmtManPhone, 'id'); $stmtManPassport->execute([ isUndefinedProperty($row, 'manPassportId'), $rec->id, isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'dateOfBirth'), isUndefinedProperty($row, 'addressId'), isUndefinedProperty($row, 'addressResideId'), isUndefinedProperty($row, 'passportNumber'), isUndefinedProperty($row, 'authority'), isUndefinedProperty($row, 'dateOfIssue'), isUndefinedProperty($row, 'dateOfExpiry'), isUndefinedProperty($row, 'placeOfBirth'), isUndefinedProperty($row, 'personalNumber'), isUndefinedProperty($row, 'addressHouse'), isUndefinedProperty($row, 'addressCorps'), isUndefinedProperty($row, 'addressFlat'), isUndefinedProperty($row, 'addressZipcode'), isUndefinedProperty($row, 'addressResideHouse'), isUndefinedProperty($row, 'addressResideCorps'), isUndefinedProperty($row, 'addressResideFlat'), isUndefinedProperty($row, 'addressResideZipcode'), isUndefinedProperty($row,'alien'), propertyExistsSQL($row, 'sex'), propertyExistsSQL($row, 'dateOfBirth'), propertyExistsSQL($row, 'addressId'), propertyExistsSQL($row, 'addressResideId'), propertyExistsSQL($row, 'passportNumber'), propertyExistsSQL($row, 'authority'), propertyExistsSQL($row, 'dateOfIssue'), propertyExistsSQL($row, 'dateOfExpiry'), propertyExistsSQL($row, 'placeOfBirth'), propertyExistsSQL($row, 'personalNumber'), propertyExistsSQL($row, 'addressHouse'), propertyExistsSQL($row, 'addressCorps'), propertyExistsSQL($row, 'addressFlat'), propertyExistsSQL($row, 'addressZipcode'), propertyExistsSQL($row, 'addressResideHouse'), propertyExistsSQL($row, 'addressResideCorps'), propertyExistsSQL($row, 'addressResideFlat'), propertyExistsSQL($row, 'addressResideZipcode'), propertyExistsSQL($row,'alien') ]); $rec->manPassportId = fetchValue($stmtManPassport, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createPassport($records) { return $this->insertOrUpdatePassport($records, true); } public function updatePassport($records) { return $this->insertOrUpdatePassport($records, false); } } $service = new Junior\Server(new map_common_passport()); $service->process(); 