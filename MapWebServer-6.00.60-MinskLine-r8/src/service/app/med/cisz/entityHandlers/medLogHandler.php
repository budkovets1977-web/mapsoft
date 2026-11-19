<?php
class med_cisz_entityHandlers_medLogHandler { const array PROFILES = ['Contract_RejectionBy']; public static function loadPatientId(PDO_ANY $db, ?object $params = null): ?int { $params->primaryKey = $params->primaryKey ?? null; $sql = <<<"SQL"
SELECT
    MedPatientCode
FROM MedLog ML
WHERE MedLogCode = :primaryKey
SQL;
return selectSqlValue($db, $sql, [':primaryKey' => $params->primaryKey]); } public static function getCiszDocExtraData(PDO_ANY $db, ?object $params = null): ?med_cisz_ciszDocExtra_data { $getParams = (object) [ 'medLogCode' => $params->primaryKey, 'documentType' => med_cisz_ciszDocExtra_documentType::ServiceRequestHospitalisation->value ]; return med_cisz_ciszDocExtra_main::getData($db, $getParams); } }