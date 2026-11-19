<?php
 require_once('utilsTrait.php'); trait med_cisz_prescriptionListTrait { use med_cisz_utilsTrait; public function createPrescriptionList(): void { if (!($this->visitPatientId ?? false)) { return; } $visit = $this->visitPatient[array_key_first($this->visitPatient)]; $visitDate = $visit->outDate ?? $visit->visitDate; $data = selectSql($this->db, <<<SQL
            -- обычные рецепты
            SELECT
                MRS.MedRecipeStandartCode AS id,
                MRS.CodeHL7 AS "codeHL7"
            FROM
                MedRecipeStandart MRS
            WHERE
                MRS.MedPatientCode = :patient_id
                AND coalesce(MRS.CodeHL7, '') <> ''
                AND coalesce(MRS.State, 0) <> 2
                AND MRS.OutDate = :visit_date
            UNION ALL
            -- льготные
            SELECT
                MR.MedRecipeCode AS id,
                MR.CodeHL7 AS "codeHL7"
            FROM
                MedRecipe MR
            WHERE
                MR.MedPatientCode = :patient_id
                AND coalesce(MR.CodeHL7, '') <> ''
                AND coalesce(MR.State, 0) <> 2
                AND MR.RecipeDate = :visit_date
            SQL,
[ ':patient_id' => $this->patientId, ':visit_date' => $visitDate, ], PDO::FETCH_OBJ ); if (!$data) { return; } $id = generateUUID(null, null, true); if (!isset($this->encounter)) { $resp = self::doHttpMethodWithAuth($this->settings, (object)[ 'url' => $this->settings->url . 'fhir/Patient/' . $this->patient->profile->resource->id . '/Encounter?status=completed&_profile='. med_cisz_constant::$structureDefinitions['EncounterGeneral'] ]); $resp = $resp->entry[$resp->total]; $encounterLink = (object)[ 'reference' => 'Encounter/' . $resp->resource->id ]; } $this->prescriptionList[] = $profile = (object) [ 'fullUrl' => 'List/' . $id, 'resource' => (object) [ 'resourceType' => 'List', 'id' => $id, 'meta' => (object) [ 'profile' => [med_cisz_constant::$structureDefinitions['PrescriptionList']] ], 'extension' => [ (object) [ 'url' => med_cisz_constant::$structureDefinitions['FromOrganization'], 'valueReference' => $this->organization->link, ] ], 'status' => 'current', 'title' => 'Список назначенных препаратов', 'mode' => 'working', 'subject' => [$this->patient->link], 'encounter' => $this->encounter->link ?? $encounterLink ?? null, 'date' => date('c'), 'source' => $this->practitioner->role, ], ]; foreach ($data as $row) { $profile->resource->entry[] = (object) [ 'item' => (object) [ 'identifier' => (object) [ 'system' => 'ehr.platform', 'value' => $row->codeHL7, ] ] ]; } } }