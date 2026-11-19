<?php
 require_once(__DIR__ . '/../init.php'); class med_talonby_findPatient { const TASK_NAME = 'talonbyReserveCouponStranger'; private $db; public function getPatient($aFIO, $aAddress, $aDateOfBirth) { $fioCount = count($aFIO); $addressCount = count($aAddress); $FIO = []; $address = []; $fioCount >= 1 ? $FIO[0] = $aFIO[0] : $FIO[0] = ''; $fioCount >= 2 ? $FIO[1] = $aFIO[1] : $FIO[1] = ''; $fioCount >= 3 ? $FIO[2] = $aFIO[2] : $FIO[2] = ''; $addressCount >=1 ? $address[0] = $aAddress[0] : $address[0] = ''; $addressCount >=2 ? $address[1] = $aAddress[1] : $address[1] = ''; $addressCount >=3 ? $address[2] = $aAddress[2] : $address[2] = ''; $addressCount >=4 ? $address[3] = $aAddress[3] : $address[3] = ''; $addressCount >=5 ? $address[4] = $aAddress[4] : $address[4] = ''; $birthday = isset($aDateOfBirth) ? $aDateOfBirth : null; return ($this->findPatient($FIO, $address, $birthday)); } private function findPatient($FIO, $address, $birthday) { $db = $this->db ?: $this->db = connectDb(null, null, true); $vowels = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я']; $surname = trim(str_replace($vowels, '_', mb_strtoupper($FIO[0], 'UTF-8'))) ?: ''; $firstName = trim(str_replace($vowels, '_', mb_strtoupper($FIO[1], 'UTF-8'))) ?: ''; $secondName = trim(str_replace($vowels, '_', mb_strtoupper($FIO[2], 'UTF-8'))) ?: ''; $street = trim(str_replace($vowels, '_', mb_strtoupper($address[0], 'UTF-8'))) ?: ''; $house = trim(str_replace($vowels, '_', mb_strtoupper($address[1], 'UTF-8'))) ?: ''; $corp = trim(str_replace($vowels, '_', mb_strtoupper($address[2], 'UTF-8'))) ?: ''; $flat = trim(str_replace($vowels, '_', mb_strtoupper($address[3], 'UTF-8'))) ?: ''; $place = trim(str_replace($vowels, '_', mb_strtoupper($address[4], 'UTF-8'))) ?: ''; $dateOfBirth = trim($birthday); if (!$surname || !$street) { return -2; } $sql = <<<"EOT"
SELECT MP.MedPatientCode as Code, FN_cmFIO(MP.ManCode) as Name
FROM MedPatient AS MP
    LEFT JOIN Man ON MP.ManCode = Man.ManCode
    LEFT JOIN ManPassport AS Pass ON MP.ManCode = Pass.ManCode
    LEFT JOIN Address AS Addr ON Pass.AddressCode = Addr.AddressCode
    LEFT JOIN Address AS AddrR ON Pass.AddressResideCode = AddrR.AddressCode
WHERE MP.FinishDate > (LOCALTIMESTAMP)
    AND Year(Pass.DateOfBirth) = Year(Date( E'$dateOfBirth' ))
    AND (Man.Surname ilike  E'$surname%' OR  E'$surname%' = E'' )
    AND (Man.Name ilike  E'$firstName%' OR  E'$firstName%' = E'' )
    AND (Man.SecondName ilike  E'$secondName%' OR  E'$secondName%' = E'' )
    AND (COALESCE(NULLIF(AddrR.Place, E'' ),NULLIF(Addr.Place, E'' ),  E'' ) ilike  E'$place%' OR  E'$place%' = E'' )
    AND (COALESCE(NULLIF(AddrR.Street, E'' ),NULLIF(Addr.Street, E'' ),  E'' ) ilike  E'%$street%' OR  E'%$street%' = E'' )
    AND (COALESCE(NULLIF(AddressResideHouse, E'' ),NULLIF(AddressHouse, E'' ),  E'' ) ilike  E'$house' OR  E'$house' = E'' )
    AND (COALESCE(NULLIF(AddressResideCorps, E'' ),NULLIF(AddressCorps, E'' ),  E'' ) ilike  E'$corp%' OR  E'$corp%' = E'' )
    AND (COALESCE(NULLIF(AddressResideFlat, E'' ),NULLIF(AddressFlat, E'' ),  E'' ) ilike  E'$flat' OR  E'$flat' = E'' );
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $patient = $stmt->fetchAll(); $patientCount = count($patient); if ($patientCount === 0) { return NULL; } else if ($patientCount > 1) { return -1; } else { return $patient[0]; } } } handleJsonRpc('med_talonby_findPatient'); 