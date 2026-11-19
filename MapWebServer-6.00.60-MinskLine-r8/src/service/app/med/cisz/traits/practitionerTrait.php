<?php
 require_once('utilsTrait.php'); trait med_cisz_practitionerTrait { use med_cisz_utilsTrait; private function createPractitioner(): void { $ciszData = getSessionValue('cisz'); if ($this->doctorId) { self::storeLinkUnique( $this->db, 'MedDoctor', $ciszData->practitioner_id, $this->doctorId, ); } $row = selectSql($this->db, <<<SQL
SELECT
    md.MedDoctorCode AS "doctorId",
    md.ManCode AS "manId",
    m.SurName AS "surName",
    m.Name AS "name",
    m.SecondName AS "secondName",
    mps.PersonalNumber AS "personalNumber",
    mps.dateofissue AS "dateOfIssue",
    coalesce(mps.sex, 0) AS sex,
    mps.DateOfBirth AS "dateOfBirth"
FROM 
    MedDoctor md
    JOIN Man m ON m.ManCode = md.ManCode
    LEFT JOIN ManPassport mps ON mps.ManCode = m.ManCode
WHERE
    md.MedDoctorCode = :doctorId
SQL,
[ ':doctorId' => $this->doctorId ], PDO::FETCH_OBJ ); $row = reset($row); if (!($row->doctorId ?? false)) { return; } $row->fullFio = transformFio($row->surName . ' ' . $row->name . ' ' . $row->secondName, 'fullfio'); $profile = (object) [ 'fullUrl' => 'Practitioner/' . $ciszData->practitioner_id, ]; $profileRole = self::doHttpMethodWithAuth($this->settings, (object) [ 'url' => $this->settings->url . 'fhir/PractitionerRole?practitioner='. $profile->fullUrl . '&_count=100' ]); if ($profileRole->total !== 0) { foreach ($profileRole->entry as $entry) { if (!isset($entry->fullUrl) || isset($entry->resource->period->end)) { continue; } $practitioner = $entry->resource; break; } } if (!isset($practitioner)) { throw new Exception( med_cisz_constant::$globalError['noPracticionerUuid']['text'], med_cisz_constant::$globalError['noPracticionerUuid']['code'] ); } $this->practitioner = (object) [ 'raw' => $row, 'actor' => [ (object) [ 'actor' => (object) [ 'reference' => $practitioner->resourceType . '/' . $practitioner->id, ], ] ], 'link' => (object) [ 'reference' => $profile->fullUrl, 'display' => $row->fullFio, ], 'role' =>(object)[ 'reference' => $practitioner->resourceType . '/' . $practitioner->id ], 'location' => (object)[ 'reference' => $practitioner->location[0]->reference ] ]; } }