<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_addPatient { const TASK_NAME = 'talonbyAddPatient'; const SETTINGS_NAME = 'medReceptionLinkTalon'; private $db; public function addPatient($aData) { $db = $this->db ?: $this->db = connectDb(null, null, true); $aCount = count($aData); if ($aCount < 13) { return -1; } isset($aData['pSurName']) ? $pSurName = $aData['pSurName'] : $pSurName = ''; isset($aData['pName']) ? $pName = $aData['pName'] : $pName = ''; isset($aData['pSecondName']) ? $pSecondName = $aData['pSecondName'] : $pSecondName = ''; isset($aData['pDateOfBirth']) ? $pDateOfBirth = $aData['pDateOfBirth'] : $pDateOfBirth = ''; isset($aData['aPlaceType']) ? $aPlaceType = $aData['aPlaceType'] : $aPlaceType = ''; isset($aData['aPlace']) ? $aPlace = $aData['aPlace'] : $aPlace = ''; isset($aData['aStreetType']) ? $aStreetType = $aData['aStreetType'] : $aStreetType = ''; isset($aData['aStreet']) ? $aStreet = $aData['aStreet'] : $aStreet = ''; isset($aData['aHouse']) ? $aHouse = $aData['aHouse'] : $aHouse = ''; isset($aData['aCorp']) ? $aCorp = $aData['aCorp'] : $aCorp = ''; isset($aData['aFlat']) ? $aFlat = $aData['aFlat'] : $aFlat = ''; isset($aData['pPhone']) ? $pPhone = $aData['pPhone'] : $pPhone = ''; isset($aData['pEmail']) ? $pEmail = $aData['pEmail'] : $pEmail = ''; $webExtraSettings = safe_json_decode($this->getWebExtraSettings()); isset($webExtraSettings->sectorId) ? (int) $sectorId = $webExtraSettings->sectorId : $sectorId = 0; isset($webExtraSettings->mapEnterpriseCode) ? (int) $mapEnterpriseCode = $webExtraSettings->mapEnterpriseCode : $mapEnterpriseCode = 0; if ($pSurName == '' || $pDateOfBirth == '') { return -1; } if ($sectorId == 0) { return -2; } $sql2 = <<<"EOT"
SELECT lastval()
EOT;
$sql = <<<"EOT"
DO $$
DECLARE
	varSurName varchar;
	varName varchar;
	varSecondName varchar;
	varDateOfBirth date;
	varPhone varchar;
	varMail varchar;
	varManCode int4;
	varPlaceType varchar;
	varPlaceTypeCode int4;
	varPlace varchar;
	varStreetType varchar;
	varStreetTypeCode int4;
	varStreet varchar;
	varAddressCode int4;
	varHouse varchar;
	varCorps varchar;
	varFlat varchar;
	varPatientGroupCode int4;
	varEnterpriseCode int4;
BEGIN
    varSurName := '$pSurName';
    varName := '$pName';
    varSecondName := '$pSecondName';
    varDateOfBirth := '$pDateOfBirth';
    varHouse := '$aHouse';
    varCorps := '$aCorp';
    varFlat := '$aFlat';
    VarPhone := '$pPhone';
    VarMail := '$pEmail';
    VarPlaceType := '$aPlaceType';
    VarPlace := '$aPlace';
    VarStreetType := '$aStreetType';
    VarStreet := '$aStreet';
    VarPatientGroupCode := $sectorId;
    VarEnterpriseCode := $mapEnterpriseCode;
  IF (COALESCE(VarPlaceType, E'' ) <> ( E'' )) THEN
    SELECT AddressPlaceTypeCode INTO varPlaceTypeCode FROM AddressPlaceType WHERE COALESCE(ShortName,  E'' ) = VarPlaceType LIMIT 1 ;
    IF (varPlaceTypeCode IS NULL) THEN
      INSERT INTO AddressPlaceType (shortname ) VALUES (VarPlaceType);
      varPlaceTypeCode := lastval() ;
    END IF;
  ELSE
    varPlaceTypeCode := NULL;
  END IF;

  IF (COALESCE(varStreetType, E'' ) <> ( E'' )) THEN
    SELECT AddressStreetTypeCode INTO varStreetTypeCode FROM AddressStreetType WHERE COALESCE(ShortName,  E'' ) = VarStreetType LIMIT 1 ;
    IF (varStreetTypeCode IS NULL) THEN
      INSERT INTO AddressStreetType (shortname ) VALUES (VarStreetType);
      varStreetTypeCode := lastval() ;
    END IF;
  ELSE
    varStreetTypeCode := NULL;
  END IF;

  SELECT AddressCode INTO varAddressCode FROM Address
  WHERE COALESCE(AddressPlaceTypeCode,0) = COALESCE(varPlaceTypeCode,0)
    AND COALESCE(Place, E'' ) = COALESCE(VarPlace, E'' )
    AND COALESCE(AddressStreetTypeCode,0) = COALESCE(varStreetTypeCode,0)
    AND COALESCE(Street, E'' ) = COALESCE(VarStreet, E'' ) LIMIT 1 ;
  IF (varAddressCode IS NULL) THEN
    INSERT INTO Address (addressplacetypecode , place , addressstreettypecode , street ) VALUES (varPlaceTypeCode, VarPlace, varStreetTypeCode, VarStreet);
    varAddressCode := lastval() ;
  END IF;

  INSERT INTO Man (surname , name , secondname ) VALUES (varSurName, varName, varSecondName);
  varManCode := lastval() ;
  INSERT INTO ManPassport (mancode , dateofbirth , addresscode , addresshouse , addresscorps , addressflat )
   VALUES (varManCode, varDateOfBirth, varAddressCode, varHouse, varCorps, varFlat);
  IF (VarPhone IS NOT NULL) THEN
    INSERT INTO ManPhone (mancode , phonetype , phonenumber ) VALUES (varManCode, 3, VarPhone);
  END IF;
  IF (VarMail IS NOT NULL) THEN
    INSERT INTO ManPhone (mancode , phonetype , phonenumber ) VALUES (varManCode, 4, VarMail);
  END IF;

  INSERT INTO MedPatient (mancode , medpatientgroupcode , enterprisecode , startdate , finishdate )
   VALUES (varManCode, VarPatientGroupCode, VarEnterpriseCode, today(), 30000101);

END $$
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->log("При редактировании талона произошла ошибка: $e"); return $e; } $code = selectSql($db, $sql2); return $code; } private function getWebExtraSettings() { $db = $this->db ?: $this->db = connectDb(); $settingsName = self::SETTINGS_NAME; $sql = <<<"EOT"
SELECT
    data
FROM webextrasettings
WHERE
    COALESCE(name, E'' ) = '$settingsName';

EOT;
return selectSql($db, $sql)[0]['data']; } private function log($message, $type = 0) { app_log($message, $type, self::TASK_NAME); } } handleJsonRpc('med_talonby_addPatient'); 