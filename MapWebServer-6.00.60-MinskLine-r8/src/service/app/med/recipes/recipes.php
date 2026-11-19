<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../med/recipes/log.php'); resumeJsonRpc(); class med_recipes_recipes { private $db; public function readRecipes($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); function generateWhere($params, $type) { $doctorCond = $params->doctorId ? "and MD.MedDoctorCode = {$params->doctorId}" : ""; $patientCond = $params->patientId ? "and MRS.MedPatientCode = {$params->patientId}" : ""; $recordType = $type === 0 ? "and MRS.RecipeType = 0" : ""; $fieldDate = $type === 0 ? "RecipeDate" : "OutDate"; return <<<"EOT"
where ({$params->type} is null or {$params->type} = {$type})
    and COALESCE(MRS.{$fieldDate}, 30000101) <= LOCALTIMESTAMP
    {$recordType}
    and MRS.EnterpriseCode = {$params->enterpriseId}
    and COALESCE(MRS.State, 0) <> 2
    and ((COALESCE(MRS.{$fieldDate}, 30000101) BETWEEN DATEADD('MONTH', -{$params->depth}, LOCALTIMESTAMP) AND
                                                     DATEADD('MONTH', {$params->depth}, LOCALTIMESTAMP) and {$params->dateOut} is null) or
         (COALESCE(MRS.{$fieldDate}, 30000101) = {$params->dateOut} and {$params->dateOut} is not null))
    {$patientCond}
    {$doctorCond}
EOT;
} $recipeId = isEmptyProperty($params, 'recipeId'); $recipeType = isEmptyProperty($params, 'type', 'null'); $whereCondRecipe = ""; $whereCondRecipeStandart = ""; $whereCondRecipeSpecial = ""; if ($recipeId) { switch ((int)$recipeType) { case 0: $whereCondRecipe = "where MRS.MedRecipeCode = $recipeId"; break; case 1: $whereCondRecipeStandart = "where MRS.MedRecipeStandartCode = $recipeId"; break; case 2: $whereCondRecipeSpecial = "where MRS.MedRegistrySpecialCode = $recipeId"; break; } } else { $sqlParams = new stdClass(); $sqlParams->enterpriseId = $enterpriseId; $sqlParams->patientId = isEmptyProperty($params, 'patientId'); $sqlParams->doctorId = isEmptyProperty($params, 'doctorId'); $sqlParams->type = $recipeType; $sqlParams->date = isEmptyProperty($params, 'dateOut'); $sqlParams->depth = 12; $sqlParams->dateOut = $sqlParams->date ? Date("Ymd", safe_strtotime($sqlParams->date)) : 'null'; if (!$sqlParams->doctorId && !$sqlParams->patientId) { return []; } $whereCondRecipe = generateWhere($sqlParams, 0); $whereCondRecipeStandart = generateWhere($sqlParams, 1); $whereCondRecipeSpecial = generateWhere($sqlParams, 2); } $sqlRecipe = <<<"EOT"
select
    0::integer "type",
    MRS.MedRecipeCode "recipeId",
    MRS.RecipeDate "outDate",
    MRS.StartDate "startDate",
    MRS.DocumentSeries "series",
    CASE WHEN MRS.DocumentNumber = MRS.MedRecipeCode THEN ''
        ELSE MRS.DocumentNumber
    END "number",
    MRS.FlagConfirm "flagConfirm",
    MD.MedDoctorCode "doctorId",
    COALESCE(WM.Manufacturer, '') as "manufacturer",
    MRW.MatWealthManufacturerCode "manufacturerCode",
    FN_cmFIO(MD.manCode, 1) "doctorFio",
    MP.Name "doctorProfession",
    MRS.MedPatientCode "patientId",
    MRS.PurchaseCount "purchaseCount",
    MRS.PurchasePeriodDay "purchasePeriodDay",
    MW.MatWealthCode "drugId",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6) "drugName",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6, 1) "drugNameInn",
    MRS.UseINN "useInn",
    MRW.MethodUse "methodUse",
    MRW.Amount "amount",
    MRS.MedPrivelegeCode "contingentId",
    CASE WHEN COALESCE(MRS.TermMonth, 0) = 6 THEN 180
        WHEN COALESCE(MRS.TermMonth, 0) = 2 THEN 60
        WHEN COALESCE(MRS.TermDay, 0) = 30 THEN 30
        WHEN COALESCE(MRS.TermDay, 0) = 15 THEN 15
        WHEN COALESCE(MRS.TermDay, 0) = 5 THEN 5
        ELSE NULL
    END "termUse",
    MWO.Name "name",
    MWO.secondName "secondName",
    MRW.MedRecipeWealthCode "recipeWealthId",
    CASE MD.manCode IS NULL
         WHEN true THEN NULL
         WHEN false THEN FN_cmFIO(MD.manCode)::text
    END "doctor",
    coalesce(MRS.CodeHL7,'') "codeHL7",
    MPas.PersonalNumber "doctorPIN",
    (select Name
     from Enterprise
     where EnterpriseCode = MRS.EnterpriseCode) "enterprise",
    FN_cmFIO(MPt.ManCode) "patient",
    MPe.Name "privelege",
    MW.DoseNumber "doseNumber",
    FN_cmMatWealthNameEx(MRW.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
         CASE MRW.Amount = 0
              WHEN true THEN ''
              WHEN false THEN ' №' + asa_trim(STR(MRW.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)))
         END + ' ' + CASE MRW.Amount = 0 WHEN true
                          THEN '' WHEN false
                          THEN '(' + FN_cmSumProp(MRW.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)) + ')'
                     END + ' ' + CASE asa_trim(MRW.MethodUse) = ''
                                      WHEN true THEN ''
                                      WHEN false THEN ', Ds:' + (asa_trim(MRW.MethodUse) )
                                 END "characteristic",
    MRS.privilegePercent "privilegePercent",
    FN_cmMatWealthNameEx(MRW.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
      CASE MRW.Amount = 0
           WHEN true THEN ''
           WHEN false THEN ' №' + asa_trim(STR(MRW.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                  WHEN true THEN MW.DoseNumber
                                                                  WHEN false THEN 1
                                                             END)))
      END "displayWealth",
    MRS.EnterpriseCode "enterpriseId",
    CASE
         WHEN COALESCE(MRS.State, 0) = 5 THEN 1 -- красный
         WHEN COALESCE(MRS.State, 0) = 1 THEN 2 -- желтый
         WHEN COALESCE(MRS.CodeHL7, '') <> '' AND COALESCE(MRS.State) IN (0, 3) THEN 3 -- зеленый
         ELSE 0 -- белый
    END::integer "stateFlag",
    COALESCE(MRS.State, 0) "state",
    MW.LinkCipher "linkCipher",
    MWO.CodeIMN "codeIMN",
    (select MR_.MedRecipeCode
     from MedRecipe MR_
     where MR_.EnterpriseCode = $enterpriseId
       and MR_.recipeType = 1
       and MR_.DocumentSeries = MRS.DocumentSeries
       and MR_.DocumentNumber = MRS.DocumentNumber
       and COALESCE(MR_.MedPatientCode, 0) = COALESCE(MRS.MedPatientCode, 0)
       and COALESCE(MR_.PayDate, 19000101) = COALESCE(MRS.PayDate, 19000101)
       and COALESCE(MR_.RecipeDate, 19000101) = COALESCE(MRS.RecipeDate, 19000101)
     limit 1) "recipeIdOld",
    MWO.fullname "fullName",
    MWO.matWealthObjectCode "wealthObjectId",
    MRS.SignatureType "signatureType",
    MRS.centralizedRelease "centralizedRelease"
from MedRecipe MRS
    join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
    join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
    join MedPatient MPt on MPt.MedPatientCode = MRS.MedPatientCode
    left join MedPrivelege MPe on MPe.MedPrivelegeCode = MRS.MedPrivelegeCode
    left join MedRecipeWealth MRW on MRW.MedRecipeWealthCode =
       (select MedRecipeWealthCode from MedRecipeWealth
           where MedRecipeCode = MRS.MedRecipeCode limit 1)
    left join MatWealth MW on MW.MatWealthCode = MRW.matWealthCode
    left join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
    left join MatWealthManufacturer WM on WM.MatWealthManufacturerCode = MRW.MatWealthManufacturerCode
    LEFT JOIN ManPassport MPas on MD.ManCode = MPas.ManCode
$whereCondRecipe
EOT;
$sqlRecipeStandart = <<<"EOT"
select
    1::integer "type",
    MRS.MedRecipeStandartCode "recipeId",
    MRS.OutDate "outDate",
    MRS.StartDate "startDate",
    MRS.DocumentSeries "series",
    MRS.DocumentNumber "number",
    2 "flagConfirm", /* заглушка, для UNION*/
    MD.MedDoctorCode "doctorId",
    COALESCE(WM.Manufacturer, '') as "manufacturer",
    MRS.MatWealthManufacturerCode "manufacturerCode",
    FN_cmFIO(MD.manCode, 1) "doctorFio",
    MP.Name "doctorProfession",
    MRS.MedPatientCode "patientId",
	null "purchaseCount",
    null "purchasePeriodDay",
    MW.MatWealthCode "drugId",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6) "drugName",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6, 1) "drugNameInn",
    MRS.UseINN "useInn",
    MRS.MethodUse "methodUse",
    MRS.Amount "amount",
    null "contingentId",
    CASE WHEN COALESCE(MRS.TermMonth, 0) = 6 THEN 180
        WHEN COALESCE(MRS.TermMonth, 0) = 2 THEN 60
        WHEN COALESCE(MRS.TermDay, 0) = 30 THEN 30
        WHEN COALESCE(MRS.TermDay, 0) = 15 THEN 15
        WHEN COALESCE(MRS.TermDay, 0) = 5 THEN 5
        ELSE NULL
    END "termUse",
    MWO.Name "name",
    MWO.secondName "secondName",
    null "recipeWealthId",
    CASE MD.manCode IS NULL
         WHEN true THEN NULL
         WHEN false THEN FN_cmFIO(MD.manCode)::text
    END "doctor",
    coalesce(MRS.CodeHL7,'') "codeHL7",
    MPas.PersonalNumber "doctorPIN",
    (select Name
     from Enterprise
     where EnterpriseCode = MRS.EnterpriseCode) "enterprise",
    FN_cmFIO(MPt.ManCode) "patient",
    null "privelege",
    MW.DoseNumber "doseNumber",
    FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
         CASE mRS.Amount = 0
              WHEN true THEN ''
              WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)))
         END + ' ' + CASE mRS.Amount = 0 WHEN true
                          THEN '' WHEN false
                          THEN '(' + FN_cmSumProp(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)) + ')'
                     END + ' ' + CASE asa_trim(mRS.MethodUse) = ''
                                      WHEN true THEN ''
                                      WHEN false THEN ', Ds:' + (asa_trim(mRS.MethodUse) )
                                 END "characteristic",
    null "privilegePercent",
    FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
      CASE mRS.Amount = 0
           WHEN true THEN ''
           WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                  WHEN true THEN MW.DoseNumber
                                                                  WHEN false THEN 1
                                                             END)))
      END "displayWealth",
    MRS.EnterpriseCode "enterpriseId",
    CASE
         WHEN COALESCE(MRS.State, 0) = 5 THEN 1 -- красный
         WHEN COALESCE(MRS.State, 0) = 1 THEN 2 -- желтый
         WHEN COALESCE(MRS.CodeHL7, '') <> '' AND COALESCE(MRS.State, 0) IN (3) THEN 3 -- зеленый
         ELSE 0 -- белый
    END::integer "stateFlag",
    COALESCE(MRS.State, 0) "state",
    MW.LinkCipher "linkCipher",
    MWO.CodeIMN "codeIMN",
    null "recipeIdOld",
    MWO.fullname "fullName",
    MWO.matWealthObjectCode "wealthObjectId",
    MRS.SignatureType "signatureType",
    null "centralizedRelease"
from MedRecipeStandart MRS
    join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
    join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
    join MedPatient MPt on MPt.MedPatientCode = MRS.MedPatientCode
    left join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
    left join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
    LEFT JOIN ManPassport MPas on MD.ManCode = MPas.ManCode
    left join MatWealthManufacturer WM on WM.MatWealthManufacturerCode = MRS.MatWealthManufacturerCode
$whereCondRecipeStandart
EOT;
$sqlRecipeSpecial = <<<"EOT"
select
    2::integer "type",
    MRS.MedRegistrySpecialCode "recipeId",
    MRS.OutDate "outDate",
    null "startDate",
    case when MRS.DocumentNumber ~ '\W' then        -- если есть больше одного слова,
        substring(MRS.DocumentNumber from '^\w+')   -- то первое слово - серия
    else '' end "series",
    case when MRS.DocumentNumber ~ '\W' then                -- если есть больше одного слова,
        regexp_replace(MRS.DocumentNumber, '^\w+\W+', '')   -- то все слова кроме первого с пробелами - номер
    else MRS.DocumentNumber end "number",
    2 "flagConfirm", /* заглушка, для UNION*/
    MD.MedDoctorCode "doctorId",
    {$db->dialect("(SELECT
                    LIST(DISTINCT WM.Manufacturer, ', ') as manufacturer
                    FROM matWealthManufacturer as WM
                    WHERE ISNULL(WM.RegistryDate, 19000101) > 19000101
                      AND ISNULL(WM.TermDate, 19000101) >= now()
                      AND WM.matWealthCode = MW.matWealthCode)", "(SELECT
                    array_to_string_distinct(array_agg(DISTINCT WM.Manufacturer ORDER BY WM.Manufacturer), ', ') as manufacturer
                FROM matWealthManufacturer as WM
                WHERE COALESCE(WM.RegistryDate, '1900-01-01') > '1900-01-01'
                      AND COALESCE(WM.TermDate, '1900-01-01') >= (clock_timestamp()::timestamp)
                      AND WM.matWealthCode = MW.matWealthCode
                )")} "manufacturer", /* производители */
    NULL "manufacturerCode",
    FN_cmFIO(MD.manCode, 1) "doctorFio",
    MP.Name "doctorProfession",
    MRS.MedPatientCode "patientId",
	null "purchaseCount",
    null "purchasePeriodDay",
    MW.MatWealthCode "drugId",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6) "drugName",
    FN_cmMatWealthNameEx(MW.matWealthCode, 6, 1) "drugNameInn",
    MRS.UseINN "useInn",
    MRS.MethodUse "methodUse",
    MRS.Amount "amount",
    null "contingentId",
    null "termUse",
    MWO.Name "name",
    MWO.secondName "secondName",
    null "recipeWealthId",
    CASE MD.manCode IS NULL
         WHEN true THEN NULL
         WHEN false THEN FN_cmFIO(MD.manCode)::text
    END "doctor",
    coalesce(MRS.CodeHL7,'') "codeHL7",
    MPas.PersonalNumber "doctorPIN",
    (select Name
     from Enterprise
     where EnterpriseCode = MRS.EnterpriseCode) "enterprise",
    FN_cmFIO(MPt.ManCode) "patient",
    null "privelege",
    MW.DoseNumber "doseNumber",
    FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
         CASE mRS.Amount = 0
              WHEN true THEN ''
              WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)))
         END + ' ' + CASE mRS.Amount = 0 WHEN true
                          THEN '' WHEN false
                          THEN '(' + FN_cmSumProp(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                     WHEN true THEN MW.DoseNumber
                                                                     WHEN false THEN 1
                                                                END)) + ')'
                     END + ' ' + CASE asa_trim(mRS.MethodUse) = ''
                                      WHEN true THEN ''
                                      WHEN false THEN ', Ds:' + (asa_trim(mRS.MethodUse) )
                                 END "characteristic",
    null "privilegePercent",
    FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
      CASE mRS.Amount = 0
           WHEN true THEN ''
           WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                  WHEN true THEN MW.DoseNumber
                                                                  WHEN false THEN 1
                                                             END)))
      END "displayWealth",
    MRS.EnterpriseCode "enterpriseId",
    0::integer "stateFlag",
    COALESCE(MRS.State, 0) "state",
    MW.LinkCipher "linkCipher",
    MWO.CodeIMN "codeIMN",
    null "recipeIdOld",
    MWO.fullname "fullName",
    MWO.matWealthObjectCode "wealthObjectId",
    MRS.SignatureType "signatureType",
    null "centralizedRelease"
from MedRegistrySpecial MRS
    join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
    join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
    join MedPatient MPt on MPt.MedPatientCode = MRS.MedPatientCode
    left join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
    left join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
    LEFT JOIN ManPassport MPas on MD.ManCode = MPas.ManCode
$whereCondRecipeSpecial
EOT;
if ($recipeId) { switch ((int)$recipeType) { case 0: $res = selectSql($db, $sqlRecipe); break; case 1: $res = selectSql($db, $sqlRecipeStandart); break; case 2: $res = selectSql($db, $sqlRecipeSpecial); break; default: $res = false; } return $res ? $res[0] : $res; } else { $sql = <<<"EOT"
select
    *
from
    ($sqlRecipe
    union all
    $sqlRecipeStandart
    union all
    $sqlRecipeSpecial) "recipe"
order by
    "outDate" desc
EOT;
return selectSql($db, $sql); } } private function getTermParam($termUse) { switch ($termUse) { case 180: return [1, null, 1, 6]; case 60: return [1, null, 1, 2]; case 30: return [1, 30, 1, null]; case 15: return [1, 15, 1, null]; case 5: return [1, 5, 1, null]; default: return [0, null, 0, null]; } } private function getPrivelegesGroups($db) { $privelegesGroupSql = <<<SQL
SELECT
  MP.MedPrivelegeCode           "id",
  MP.MedPrivilegeGroupCode      "group"
FROM
  MedPrivelege MP
SQL;
$privelegesGroups = selectSql($db, $privelegesGroupSql, null, PDO::FETCH_KEY_PAIR); return $privelegesGroups; } public function updateRecipes($records) { $db = $this->db ?: $this->db = connectDb(); $sqlRecipe = <<<"EOT"
update MedRecipe set
       DocumentSeries = case when ? = 1 then ? else DocumentSeries end,
       DocumentNumber = case when ? = 1 then ? else DocumentNumber end,
       FlagConfirm = case when ? = 1 then ? else FlagConfirm end,
       MedPrivelegeCode = case when ? = 1 then ? else MedPrivelegeCode end,
       MedPrivilegeGroupCode = case when ? = 1 then ? else MedPrivilegeGroupCode end,
       privilegePercent = case when ? = 1 then ? else privilegePercent end,
       state = case when ? = 1 then ? else state end,
       PurchasePeriodDay = case when ? = 1 then ? else PurchasePeriodDay end,
       PurchaseCount = case when ? = 1 then ? else PurchaseCount end,
       TermDay = case when ? = 1 then ? else TermDay end,
       TermMonth = case when ? = 1 then ? else TermMonth end,
       UseINN = case when ? = 1 then ? else UseINN end,
       StartDate = case when ? = 1 then ? else StartDate end,
       CentralizedRelease = case when ? = 1 then ? else CentralizedRelease end
where MedRecipeCode = ?
EOT;
$sqlRecipeStandart = <<<"EOT"
update MedRecipeStandart set
       DocumentSeries = case when ? = 1 then ? else DocumentSeries end,
       DocumentNumber = case when ? = 1 then ? else DocumentNumber end,
       MatWealthCode = case when ? = 1 then ? else MatWealthCode end,
       State = case when ? = 1 then ? else State end,
       Amount = case when ? = 1 then ? else Amount end,
       MethodUse = case when ? = 1 then ? else MethodUse end,
       TermDay = case when ? = 1 then ? else TermDay end,
       TermMonth = case when ? = 1 then ? else TermMonth end,
       UseINN = case when ? = 1 then ? else UseINN end,
       StartDate = case when ? = 1 then ? else StartDate end,
       MatWealthManufacturerCode = case when ? = 1 then ? else MatWealthManufacturerCode end
where MedRecipeStandartCode = ?
EOT;
$sqlRegistrySpecial = <<<"EOT"
update MedRegistrySpecial set
       DocumentNumber = case when ? = 1 then ? else DocumentNumber end,
       MatWealthCode = case when ? = 1 then ? else MatWealthCode end,
       State = case when ? = 1 then ? else State end,
       Amount = case when ? = 1 then ? else Amount end,
       MethodUse = case when ? = 1 then ? else MethodUse end,
       UseINN = case when ? = 1 then ? else UseINN end
where MedRegistrySpecialCode = ?
EOT;
$stmtRecipe = $db->prepare($sqlRecipe); $stmtRecipeStandart = $db->prepare($sqlRecipeStandart); $stmtRegistrySpecial = $db->prepare($sqlRegistrySpecial); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $rec = new stdClass; $ret = []; try { $privelegesGroups = $this->getPrivelegesGroups($db); $log_module = new med_recipes_log(); foreach ($records as $row) { $this->setNeedSyncDPC($row); switch ($row->type) { case 0: if (isEmptyProperty($row, 'recipeWealthId')) { $log_module->updRecipeWealth([ propertyExistsSQL($row, 'drugId'), isEmptyProperty($row, 'drugId'), propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'methodUse'), isUndefinedProperty($row, 'methodUse'), null, null, null, null, propertyExistsSQL($row, 'manufacturerCode'), isEmptyProperty($row, 'manufacturerCode'), $row->recipeWealthId, ], $db); } else { $log_module->insRecipeWealth([ $row->recipeId, isEmptyProperty($row, 'drugId'), isUndefinedProperty($row, 'amount'), isUndefinedProperty($row, 'methodUse'), null, null ], $db); } $termParam = $this->getTermParam(isEmptyProperty($row, 'termUse')); array_add($termParam, [ propertyExistsSQL($row, 'useInn'), isUndefinedProperty($row, 'useInn'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'centralizedRelease'), isUndefinedProperty($row, 'centralizedRelease'), $row->recipeId ]); $contingentId = $row->contingentId ?? null; $hasPrivilegeGroup = $contingentId !== null && is_array($privelegesGroups) && array_key_exists($contingentId, $privelegesGroups); $arrParam = [ propertyExistsSQL($row, 'series'), isUndefinedProperty($row, 'series'), propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'flagConfirm'), isUndefinedProperty($row, 'flagConfirm'), propertyExistsSQL($row, 'contingentId'), isEmptyProperty($row, 'contingentId'), (int) $hasPrivilegeGroup, $hasPrivilegeGroup ? isUndefinedArrayValue($privelegesGroups, $contingentId) : null, propertyExistsSQL($row, 'privilegePercent'), isUndefinedProperty($row, 'privilegePercent'), propertyExistsSQL($row, 'state'), isUndefinedProperty($row, 'state'), propertyExistsSQL($row, 'purchasePeriodDay'), isUndefinedProperty($row, 'purchasePeriodDay'), propertyExistsSQL($row, 'purchaseCount'), isUndefinedProperty($row, 'purchaseCount') ]; array_add($arrParam, $termParam); $stmtRecipe->execute($arrParam); if (empty(isEmptyProperty($row, 'recipeIdOld'))) { $log_module->createRegistration($db, $row); } break; case 1: $termParam = $this->getTermParam(isEmptyProperty($row, 'termUse')); array_add($termParam, [ propertyExistsSQL($row, 'useInn'), isUndefinedProperty($row, 'useInn'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'manufacturerCode'), isEmptyProperty($row, 'manufacturerCode'), $row->recipeId ]); $arrParam = [ propertyExistsSQL($row, 'series'), isUndefinedProperty($row, 'series'), propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'drugId'), isEmptyProperty($row, 'drugId'), propertyExistsSQL($row, 'state'), isEmptyProperty($row, 'state'), propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'methodUse'), isUndefinedProperty($row, 'methodUse'), ]; array_add($arrParam, $termParam); $stmtRecipeStandart->execute($arrParam); break; case 2: $stmtRegistrySpecial->execute([ 1, trim(isUndefinedProperty($row, 'series', '') . ' ' . isUndefinedProperty($row, 'number', '')), propertyExistsSQL($row, 'drugId'), isEmptyProperty($row, 'drugId'), propertyExistsSQL($row, 'state'), isEmptyProperty($row, 'state'), propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'methodUse'), isUndefinedProperty($row, 'methodUse'), propertyExistsSQL($row, 'useInn'), isUndefinedProperty($row, 'useInn'), $row->recipeId ]); break; } $rec->recipeId = $row->recipeId; $rec->type = $row->type; $ret[] = $this->readRecipes($rec); unset($rec->recipeId); unset($rec->type); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function findBlankWealth($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       medRecipeWealthCode
from medRecipeWealth
where medRecipeCode = ?
  and matWealthCode is null
EOT;
return selectSqlValue($db, $sql, [ isUndefinedProperty($params, 'recipeId', 0) ]); } private function getTermUse(&$row, $termUse) { switch ($termUse) { case 180: $row->termMonth = 6; return; case 60: $row->termMonth = 2; return; case 30: $row->termDay = 30; return; case 15: $row->termDay = 15; return; case 5: $row->termDay = 5; return; } } private function setNeedSyncDPC(&$row) { if ($this->checkValidBarCode((object) [ 'patientId' => $row->patientId, 'outDate' => $row->outDate ])) { $row->state = !empty($row->codeIMN) && !empty($row->linkCipher) && !empty($row->drugId) ? 1 : $row->state; return $row->state === 1; } return false; } public function createRecipes($records) { $db = $this->db ?: $this->db = connectDb(); $params = new stdClass(); $params->enterpriseId = getSessionValue('mapEnterpriseCode'); $sqlRecipeIns = <<<"EOT"
insert into MedRecipe
    (RecipeType, EnterpriseCode, DocumentSeries, DocumentNumber, FlagConfirm, RecipeDate, MedPatientCode, MedDoctorCode,
        MedPrivelegeCode, MedPrivilegeGroupCode, TermDay, TermMonth, UseINN, State, PrivilegePercent, startdate, PurchaseCount, PurchasePeriodDay, centralizedrelease)
values
    (0, $params->enterpriseId, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlRecipeUpd = <<<"EOT"
update MedRecipe set
       RecipeDate = case when ? = 1 then ? else RecipeDate end,
       FlagConfirm = case when ? = 1 then ? else FlagConfirm end,
       MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
       MedPrivelegeCode = case when ? = 1 then ? else MedPrivelegeCode end,
       MedPrivilegeGroupCode = case when ? = 1 then ? else MedPrivilegeGroupCode end,
       PrivilegePercent = case when ? = 1 then ? else PrivilegePercent end,
       State = case when ? = 1 then ? else State end,
       startdate = case when ? = 1 then ? else startdate end,
       purchasecount = case when ? = 1 then ? else purchasecount end,
       purchaseperiodday = case when ? = 1 then ? else purchaseperiodday end,
       TermDay = case when ? = 1 then ? else TermDay end,
       TermMonth = case when ? = 1 then ? else TermMonth end,
       UseINN = case when ? = 1 then ? else UseINN end
where MedRecipeCode = ?
EOT;
$sqlIncNumber = <<<"EOT"
UPDATE MedRecipeStandartSN
SET
    documentNumber = incstr(?)
WHERE MedRecipeStandartSNCode = ?
EOT;
$sqlStandart = <<<"EOT"
insert into MedRecipeStandart
    (amount, enterprisecode, matwealthcode, meddoctorcode, medpatientcode, methoduse, outdate, documentnumber,
        documentseries, state, TermDay, TermMonth, useinn, departmentcode, startdate, matwealthmanufacturercode)
values
    (?, $params->enterpriseId, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlSpecial = <<<"EOT"
insert into MedRegistrySpecial
    (enterprisecode, outdate, documentnumber, meddoctorcode, medpatientcode, matwealthcode, amount, methoduse, state, useinn, departmentcode)
values
    ($params->enterpriseId, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$sqlRecipeNumberUpd = <<<"EOT"
UPDATE MedRecipe
SET
    documentNumber = ?
WHERE MedRecipeCode = ?
EOT;
$stmtRecipeIns = $db->prepare($sqlRecipeIns); $stmtRecipeUpd = $db->prepare($sqlRecipeUpd); $stmtStandart = $db->prepare($sqlStandart); $stmtIncNumber = $db->prepare($sqlIncNumber); $stmtSpecial = $db->prepare($sqlSpecial); $stmtRecipeNumberUpd = $db->prepare($sqlRecipeNumberUpd); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { $privelegesGroups = $this->getPrivelegesGroups($db); $log_module = new med_recipes_log(); foreach ($records as $row) { $params->doctorId = $row->doctorId; $params->series = $row->series; $params->number = $row->number; $params->type = $row->type; $rec = new stdClass; $rec->type = $row->type; $this->setNeedSyncDPC($row); switch ($rec->type) { case 0: $termUse = isUndefinedProperty($row, 'termUse', 0); $rec->recipeId = $this->findBlank($params); if (isset($rec->recipeId)) { $termParam = $this->getTermParam($termUse); array_add($termParam, [ propertyExistsSQL($row, 'useInn'), isUndefinedProperty($row, 'useInn'), $rec->recipeId ]); $contingentId = $row->contingentId ?? null; $hasPrivilegeGroup = $contingentId !== null && is_array($privelegesGroups) && array_key_exists($contingentId, $privelegesGroups); $arrParam = [ propertyExistsSQL($row, 'outDate'), isUndefinedProperty($row, 'outDate'), propertyExistsSQL($row, 'flagConfirm'), isUndefinedProperty($row, 'flagConfirm'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), propertyExistsSQL($row, 'contingentId'), isEmptyProperty($row, 'contingentId'), (int) $hasPrivilegeGroup, $hasPrivilegeGroup ? isUndefinedArrayValue($privelegesGroups, $contingentId) : null, propertyExistsSQL($row, 'privilegePercent'), isUndefinedProperty($row, 'privilegePercent'), propertyExistsSQL($row, 'state'), isUndefinedProperty($row, 'state'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'purchaseCount'), isUndefinedProperty($row, 'purchaseCount'), propertyExistsSQL($row, 'purchasePeriodDay'), isUndefinedProperty($row, 'purchasePeriodDay'), ]; array_add($arrParam, $termParam); $stmtRecipeUpd->execute($arrParam); $blankWealthId = $this->findBlankWealth((object) [ 'recipeId' => $rec->recipeId ]); if (isset($blankWealthId)) { $log_module->updRecipeWealth([ propertyExistsSQL($row, 'drugId'), isEmptyProperty($row, 'drugId'), propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'methodUse'), isUndefinedProperty($row, 'methodUse'), null, null, null, null, propertyExistsSQL($row, 'manufacturerCode'), isEmptyProperty($row, 'manufacturerCode'), $blankWealthId ], $db); } else { $log_module->insRecipeWealth([ $rec->recipeId, isEmptyProperty($row, 'drugId'), isUndefinedProperty($row, 'amount'), isUndefinedProperty($row, 'methodUse'), null, null ], $db); } } else { $this->getTermUse($row, $termUse); $stmtRecipeIns->execute([ isUndefinedProperty($row, 'series'), isUndefinedProperty($row, 'number'), isUndefinedProperty($row, 'flagConfirm'), isUndefinedProperty($row, 'outDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'contingentId'), isUndefinedArrayValue($privelegesGroups, $row->contingentId ?? null), isEmptyProperty($row, 'termDay'), isEmptyProperty($row, 'termMonth'), isUndefinedProperty($row, 'useInn'), isUndefinedProperty($row, 'state'), isUndefinedProperty($row, 'privilegePercent'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'purchaseCount'), isUndefinedProperty($row, 'purchasePeriodDay'), isUndefinedProperty($row, 'centralizedRelease') ]); $rec->recipeId = $db->lastInsertId('seq_MedRecipe'); $log_module->insRecipeWealth([ $rec->recipeId, isEmptyProperty($row, 'drugId'), isUndefinedProperty($row, 'amount'), isUndefinedProperty($row, 'methodUse'), null, null, isEmptyProperty($row, 'manufacturerCode') ], $db); } if (empty(isEmptyProperty($row, 'number'))) { $stmtRecipeNumberUpd->execute([ $rec->recipeId, $rec->recipeId ]); } if (empty(isEmptyProperty($row, 'recipeIdOld'))) { $log_module->createRegistration($db, $rec); } break; case 1: $params->series = $row->series; $params->number = $row->number; $params->type = $row->type; $rec = new stdClass; $rec->type = $row->type; $this->getTermUse($row, isUndefinedProperty($row, 'termUse', 0)); $stmtStandart->execute([ isUndefinedProperty($row, 'amount'), isEmptyProperty($row, 'drugId'), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'methodUse'), isEmptyProperty($row, 'outDate'), isUndefinedProperty($row, 'number'), isUndefinedProperty($row, 'series'), isUndefinedProperty($row, 'state'), isEmptyProperty($row, 'termDay'), isEmptyProperty($row, 'termMonth'), isUndefinedProperty($row, 'useInn'), $this->readDoctorInfo($row->doctorId)['departmentId'], isEmptyProperty($row, 'startDate'), isEmptyProperty($row, 'manufacturerCode') ]); $rec->recipeId = $db->lastInsertId('seq_MedRecipeStandart'); if (isEmptyProperty($row, 'isStandartNumber')) { $stmtIncNumber->execute([ isUndefinedProperty($row, 'number'), isEmptyProperty($row, 'standartSnId') ]); } break; case 2: $stmtSpecial->execute([ isEmptyProperty($row, 'outDate'), trim(isUndefinedProperty($row, 'series', '') . ' ' . isUndefinedProperty($row, 'number', '')), isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'drugId'), isUndefinedProperty($row, 'amount'), isEmptyProperty($row, 'methodUse'), isUndefinedProperty($row, 'state'), isUndefinedProperty($row, 'useInn'), $this->readDoctorInfo($row->doctorId)['departmentId'] ]); $rec->recipeId = $db->lastInsertId('seq_MedRegistrySpecial'); break; } $ret[] = $this->readRecipes($rec); unset($params->doctorId); unset($params->series); unset($params->number); unset($params->type); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteRecipes($records) { $db = $this->db ?: $this->db = connectDb(); function deleteReg($arr, $tableName, $method = 0) { $rowsDelReg = array_filter($arr, function ($el) use ($tableName, $method) { return $el->table === $tableName && $el->method === $method && !empty($el->recipeIdOld); }); if (!empty($rowsDelReg)) { $in = createSqlInCondition($rowsDelReg, 'recipeIdOld'); return "delete from {$tableName} where {$tableName}Code $in and recipeType = 1;\n"; } } $arrTable = ['MedRecipe', 'MedRecipeStandart', 'MedRegistrySpecial']; $upd = []; $del = []; if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { if ($this->setNeedSyncDPC($row)) { $upd[] = (object) [ 'id' => $row->recipeId, 'recipeIdOld' => $row->recipeIdOld, 'method' => 0, 'table' => $row->type === 0 ? $arrTable[0] : ($row->type === 1 ? $arrTable[1] : $arrTable[2]) ]; } else { $del[] = (object) [ 'id' => $row->recipeId, 'recipeIdOld' => $row->recipeIdOld, 'method' => 1, 'table' => $row->type === 0 ? $arrTable[0] : ($row->type === 1 ? $arrTable[1] : $arrTable[2]) ]; } } $sqlString = ''; foreach ($arrTable as $table) { $rowsUpd = array_filter($upd, function ($el) use ($table) { return $el->table === $table && $el->method === 0; }); if (!empty($rowsUpd)) { $in = createSqlInCondition($rowsUpd); $sqlString .= "update {$table} set state = 2 where {$table}Code $in;\n"; } if ($table === $arrTable[0]) { $sqlString .= deleteReg($upd, $table); } $rowsDel = array_filter($del, function ($el) use ($table) { return $el->table === $table && $el->method === 1; }); if (!empty($rowsDel)) { $in = createSqlInCondition($rowsDel); $sqlString .= "delete from {$table} where {$table}Code $in;\n"; } if ($table === $arrTable[0]) { $sqlString .= deleteReg($del, $table, 1); } } $sqlScript = <<<"EOT"
DO \$anon\$
BEGIN
    {$sqlString}
END \$anon\$;
EOT;
$db->beginTransaction(); try { executeSql($db, $sqlScript); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function isCheckRecipe($params) { $db = $this->db ?: $this->db = connectDb(); $recipeid = isEmptyProperty($params, 'recipeId', 'null'); $type = isEmptyProperty($params, 'type'); $otherCond = ""; $params->enterpriseId = getSessionValue('mapEnterpriseCode'); switch ($type) { case 0: $table = "MedRecipe"; $field = "MedRecipeCode"; $otherCond = "and RecipeType = 0"; break; case 1: $table = "MedRecipeStandart"; $field = "MedRecipeStandartCode"; break; case 2: $sql = <<<"EOT"
select
    1 "flag"
from MedRegistrySpecial
where EnterpriseCode = ?
  and COALESCE(DocumentNumber, '') = ?
  and ($recipeid is null or $recipeid <> MedRegistrySpecialCode)
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'enterpriseId', 0), trim(isUndefinedProperty($params, 'series', '') . ' ' . isUndefinedProperty($params, 'number', '')) ]); $ret = $stmt->fetch(); return $ret ? $ret : -1; } $sql = <<<"EOT"
select
    1 "flag"
from $table
where EnterpriseCode = ?
  $otherCond
  and COALESCE(DocumentSeries, '') = ?
  and COALESCE(DocumentNumber, '') = ?
  and ($recipeid is null or $recipeid <> $field)
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'enterpriseId', 0), isUndefinedProperty($params, 'series', ''), isUndefinedProperty($params, 'number', '') ]); $ret = $stmt->fetch(); return $ret ? $ret : -1; } private function findBlank($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
       MR.MedRecipeCode "id"
from MedRecipe MR
     join MedRecipeWealth MRW on MRW.MedRecipeWealthCode = (select
                                                                   MedRecipeWealthCode
                                                            from MedRecipeWealth
                                                            where MedRecipeCode = MR.MedRecipeCode
                                                            limit 1)
where MR.MedDoctorCode = ?
  and MR.RecipeType = 0
  and MR.EnterpriseCode = ?
  and (COALESCE(MR.RecipeDate, 19000101) = 19000101 and COALESCE(MR.PayDate, 19000101) = 19000101)
  and COALESCE(MR.State, 0) <> 2
  and MR.DocumentSeries = ?
  and MR.DocumentNumber = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isUndefinedProperty($params, 'doctorId', 0), isUndefinedProperty($params, 'enterpriseId', 0), isUndefinedProperty($params, 'series', ''), isUndefinedProperty($params, 'number', '') ]); return fetchValue($stmt, 'id'); } public function getRecipeNumber($params) { $db = $this->db ?: $this->db = connectDb(); $doctorId = isEmptyProperty($params, 'doctorId', 0); $type = isEmptyProperty($params, 'type', 0); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sqlLgt = <<<"EOT"
select
       MR.MedRecipeCode "id",
       MR.DocumentSeries "series",
       MR.DocumentNumber "number",
       MR.DocumentSeries || ' ' || MR.DocumentNumber "display",
       MR.RecipeType "type",
       MR.EnterpriseCode "enterpriseId",
       CASE
            WHEN COALESCE(MR.TermMonth, 0) = 6 THEN 180
            WHEN COALESCE(MR.TermMonth, 0) = 2 THEN 60
            WHEN COALESCE(MR.TermDay, 0) = 30 THEN 30
            WHEN COALESCE(MR.TermDay, 0) = 15 THEN 15
            WHEN COALESCE(MR.TermDay, 0) = 5 THEN 5
            ELSE NULL
       END "termUse"
from MedRecipe MR
     join MedRecipeWealth MRW on MRW.MedRecipeWealthCode = (select
                                                                   MedRecipeWealthCode
                                                            from MedRecipeWealth
                                                            where MedRecipeCode = MR.MedRecipeCode
                                                            limit 1)
where MR.MedDoctorCode = $doctorId
  and MR.RecipeType = 0
  and MR.EnterpriseCode = $enterpriseId
  and (COALESCE(MR.RecipeDate, 19000101) = 19000101 and COALESCE(MR.PayDate, 19000101) = 19000101)
  and COALESCE(MR.State, 0) <> 2
order by "display"
EOT;
$sqlStd = <<<"EOT"
select
    medRecipeStandartSNCode "id",
    documentNumber "number",
    documentSeries "series"
from MedRecipeStandartSN
where EnterpriseCode = $enterpriseId
order by 1 DESC
limit 1
EOT;
$stmt = $db->prepare($type === 0 ? $sqlLgt : $sqlStd); $stmt->execute(); return $stmt->fetchAll(); } public function getWealth($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
            SELECT
                MW.DoseNumber "doseNumber",
                COALESCE(MWO.codeimn, '') "codeIMN",
                MW.linkcipher "linkCipher",
                MWM.manufacturer "manufacturer",
                MWM.matwealthmanufacturercode "manufacturerId"
            FROM MatWealth MW
                JOIN MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatwealthObjectCode
                LEFT JOIN MatWealthManufacturer MWM on MWM.matWealthCode = MW.MatWealthCode
            WHERE MW.MatWealthCode = ?
            ORDER BY
                (MWM.termdate >= CURRENT_DATE) DESC
            EOT;
$stmt = $db->prepare($sql); $stmt->execute([isUndefinedProperty($params, 'drugId')]); $results = $stmt->fetchAll(); $groupedByIMN = []; foreach ($results as $row) { $codeIMN = $row['codeIMN']; $manufacturerId = $row['manufacturerId']; if (!isset($groupedByIMN[$codeIMN])) { $groupedByIMN[$codeIMN] = []; } if (!in_array($manufacturerId, $groupedByIMN[$codeIMN])) { $groupedByIMN[$codeIMN][] = $manufacturerId; } } return $results; } public function getDrugInfo($params) { $ret = []; $ret['methodUse'] = $this->getDrugMethodUse($params); $ret['wealth'] = $this->getWealth($params); $ret['methodsUse'] = $this->getDrugMethodsUse($params); return $ret; } private function getDrugMethodsUse($params) { $db = $this->db ?: $this->db = connectDb(); $currDate = Date("Ymd"); $depth = isUndefinedProperty($params, 'depth', 6); $drugId = isEmptyProperty($params, 'drugId', 0); $type = isUndefinedProperty($params, 'type'); $sqlRecipe = <<<"EOT"
select distinct
       MRW.MethodUse "methodUse"
from medRecipeWealth AS MRW
     left join medRecipe AS MR on MR.medRecipeCode = MRW.medRecipeCode
where COALESCE(MRW.MethodUse, '') <> ''
  and COALESCE(MR.State, 0) <> 2
  and MRW.MatWealthCode = $drugId
  and MR.RecipeDate BETWEEN DATEADD('MONTH', -$depth, $currDate) and DATEADD('DAY', 1, $currDate)
order by 1
EOT;
$sqlRecipeStandart = <<<"EOT"
select distinct
       MRS.MethodUse "methodUse"
from MedRecipeStandart MRS
where MRS.MatWealthCode = $drugId
  and COALESCE(MRS.State, 0) <> 2
  and MRS.OutDate BETWEEN DATEADD('MONTH', -$depth, $currDate) and DATEADD('DAY', 1, $currDate)
order by 1
EOT;
$sqlRecipeSpecial = <<<"EOT"
select distinct
       MRS.MethodUse "methodUse"
from MedRegistrySpecial MRS
where MRS.MatWealthCode = $drugId
   and COALESCE(MRS.State, 0) <> 2
   and MRS.OutDate BETWEEN DATEADD('MONTH', -$depth, $currDate) and DATEADD('DAY', 1, $currDate)
order by 1
EOT;
switch ($type) { case 0: return selectSql($db, $sqlRecipe); case 1: return selectSql($db, $sqlRecipeStandart); case 2: return selectSql($db, $sqlRecipeSpecial); default: return false; } } private function getDrugMethodUse($params) { $db = $this->db ?: $this->db = connectDb(); $type = isUndefinedProperty($params, 'type'); $patientId = isEmptyProperty($params, 'patientId', 0); $drugId = isEmptyProperty($params, 'drugId', 0); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sqlRecipe = <<<"EOT"
select
       MRW.MethodUse "methodUse"
from MedRecipe MR
     join MedRecipeWealth MRW on MRW.MedRecipeWealthCode = (select
                                                                   MedRecipeWealthCode
                                                            from MedRecipeWealth
                                                            where MedRecipeCode = MR.MedRecipeCode
                                                            limit 1)
where MR.MedPatientCode = $patientId
  and MRW.MatWealthCode = $drugId
  and MR.RecipeType = 0
  and MR.EnterpriseCode = $enterpriseId
  and COALESCE(MR.State, 0) <> 2
order by MR.RecipeDate desc
limit 1
EOT;
$sqlRecipeStandart = <<<"EOT"
select
       MRS.MethodUse "methodUse"
from MedRecipeStandart MRS
where MRS.MedPatientCode = $patientId
  and MRS.MatWealthCode = $drugId
  and MRS.EnterpriseCode = $enterpriseId
  and COALESCE(MRS.State, 0) <> 2
order by MRS.OutDate desc
limit 1
EOT;
$sqlRecipeSpecial = <<<"EOT"
select
       MRS.MethodUse "methodUse"
from MedRegistrySpecial MRS
where MRS.MedPatientCode = $patientId
  and MRS.MatWealthCode = $drugId
  and MRS.EnterpriseCode = $enterpriseId
  and COALESCE(MRS.State, 0) <> 2
order by MRS.OutDate desc
limit 1
EOT;
switch ($type) { case 0: $stmt = $db->prepare($sqlRecipe); break; case 1: $stmt = $db->prepare($sqlRecipeStandart); break; case 2: $stmt = $db->prepare($sqlRecipeSpecial); break; } $stmt->execute(); return fetchValue($stmt, 'methodUse'); } public function readDoctorInfo($doctorId = null) { $db = $this->db ?: $this->db = connectDb(); $currDoctor = getSessionValue('mapMedDoctor')['MedDoctorCode']; $id = empty($doctorId) ? (empty($currDoctor) ? 0 : $currDoctor) : $doctorId; $sql = <<<"EOT"
select
       md.MedDoctorCode "id",
       fn_cmFio(md.Mancode) "name",
       p.MedProfessionCode "professionId",
       p.Name "professionName",
       d.DepartmentCode "departmentId",
       d.Name "departmentName"
from MedDoctor md
     left outer join MedProfession p on p.MedProfessionCode = md.MedProfessionCode
     join Department d on d.DepartmentCode = md.DepartmentCode
where md.MedDoctorCode = $id
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch(); } public function checkValidBarCode($params) { $db = $this->db ?: $this->db = connectDb(); $patient = isEmptyProperty($params, 'patientId'); $date = isEmptyProperty($params, 'outDate'); $dateOut = $date ? Date("Ymd", safe_strtotime($date)) : 'null'; if (empty($patient) || empty($date)) { return []; } $sql = <<<"EOT"
select 1 "field"
from MedPatient MP
     join MAN AS M on M.ManCode = MP.ManCode
     join MedBarCode MBC on MBC.ManCode = M.ManCode
                        and $dateOut between MBC.StartDate and COALESCE(MBC.StopDate, 30000101) - INTERVAL '1 day'
where MP.MedPatientCode = $patient
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch() ? true : []; } public function selectRecipes($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $patientId = isEmptyProperty($params, 'patientId'); $sql = <<<"EOT"
select *
from (select *
      from (select
                MR.PurchaseCount "purchaseCount",
                MR.PurchasePeriodDay "purchasePeriodDay",
                MR.MedRecipeCode "recordId",
                MR.RecipeDate "recordDate",
                MR.RecipeDate "parentDate",
                COALESCE(MR.MedRecipeCode, '')::text || '-' "path",
                8 "recordType",
                null "parentId",
                null "parentType",
                6 "sortType",
                0 "type",
                CASE WHEN COALESCE(MR.TermMonth, 0) = 6 THEN 180
                    WHEN COALESCE(MR.TermMonth, 0) = 2 THEN 60
                    WHEN COALESCE(MR.TermDay, 0) = 30 THEN 30
                    WHEN COALESCE(MR.TermDay, 0) = 15 THEN 15
                    WHEN COALESCE(MR.TermDay, 0) = 5 THEN 5
                    ELSE NULL
                END "termUse",
                MR.MedRecipeCode "recipeId",
                MR.RecipeDate "outDate",
                MR.StartDate "startDate",
                MR.DocumentSeries "series",
               CASE WHEN CAST(MR.documentNumber AS text) = CAST(MR.MedRecipeCode AS text) THEN ''
                ELSE MR.documentNumber
                END "number",
                MR.MedPrivelegeCode "contingentId",
                MPe.Name "privelege",
                MD.MedDoctorCode "doctorId",
                (select Name
                    from Enterprise
                    where EnterpriseCode = MR.EnterpriseCode) "enterprise",
                MR.EnterpriseCode "enterpriseId",
                CASE MD.manCode IS NULL
                    WHEN true THEN NULL
                    WHEN false THEN (FN_cmFIO(MD.manCode, 1) || ', ')::text || MP.Name
                END "doctorFio",
                DP.Name "departmentName",
                MW.MatWealthCode "drugId",
                FN_cmMatWealthNameEx(MRW.MatWealthCode, 14, COALESCE(MR.UseINN, 0)) +
                    CASE MRW.Amount = 0
                         WHEN true THEN ''
                         WHEN false THEN ' №' + asa_trim(STR(MRW.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                                WHEN true THEN MW.DoseNumber
                                                                                WHEN false THEN 1
                                                                           END)))
                    END "displayWealth",
                MRW.MethodUse "methodUse",
                MRW.Amount "amount",
                MR.UseINN "useInn",
                PR.Name "privelegeName",
                MR.PrivilegePercent "privilegePercent",
                MR.State "state",
                (CASE
                    WHEN COALESCE(MR.State, 0) = 5 THEN 1 -- красный
                    WHEN COALESCE(MR.State, 0) = 1 THEN 2 -- желтый
                    WHEN COALESCE(MR.CodeHL7, '') <> '' AND COALESCE(MR.State) IN (0, 3) THEN 3 -- зеленый
                    ELSE 0 -- белый
                END::integer) "stateFlag",
                (select MR_.MedRecipeCode
                 from MedRecipe MR_
                 where MR_.EnterpriseCode = $enterpriseId
                   and MR_.recipeType = 1
                   and MR_.DocumentSeries = MR.DocumentSeries
                   and MR_.DocumentNumber = MR.DocumentNumber
                   and COALESCE(MR_.MedPatientCode, 0) = COALESCE(MR.MedPatientCode, 0)
                   and COALESCE(MR_.PayDate, 19000101) = COALESCE(MR.PayDate, 19000101)
                   and COALESCE(MR_.RecipeDate, 19000101) = COALESCE(MR.RecipeDate, 19000101)
                 limit 1) "recipeIdOld",
                coalesce(MR.CodeHL7,'') "codeHL7",
                MR.SignatureType "signatureType"
            from MedRecipe MR
                join MedDoctor MD on MD.MedDoctorCode = MR.medDoctorCode
                join Department DP on DP.DepartmentCode = MD.DepartmentCode
                join MedPrivelege MPe on MPe.MedPrivelegeCode = MR.MedPrivelegeCode
                join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                join MedRecipeWealth MRW on MRW.MedRecipeWealthCode = ( select
                                                                            MedRecipeWealthCode
                                                                        from MedRecipeWealth
                                                                        where MedRecipeCode = MR.MedRecipeCode
                                                                        limit 1 )
                join MatWealth MW on MW.MatWealthCode = MRW.matWealthCode
                join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
                left join MedPrivelege PR ON PR.MedPrivelegeCode = MR.MedPrivelegeCode
            where MR.MedPatientCode = $patientId
              and COALESCE(MR.RecipeDate) <= LOCALTIMESTAMP
              and MR.RecipeType = 0
              and MR.EnterpriseCode = $enterpriseId
              and COALESCE(MR.State, 0) <> 2
            order by MR.RecipeDate desc
            ) _recipes
      union all
      select *
      from (select
                null "purchaseCount",
                null "purchasePeriodDay",
                MRS.MedRecipeStandartCode "recordId",
                MRS.Outdate "recordDate",
                MRS.Outdate "parentDate",
                COALESCE(MRS.MedRecipeStandartCode, '')::text || '-' "path",
                8 "recordType",
                null "parentId",
                null "parentType",
                6 "sortType",
                1 "type",
                CASE WHEN COALESCE(MRS.TermMonth, 0) = 6 THEN 180
                    WHEN COALESCE(MRS.TermMonth, 0) = 2 THEN 60
                    WHEN COALESCE(MRS.TermDay, 0) = 30 THEN 30
                    WHEN COALESCE(MRS.TermDay, 0) = 15 THEN 15
                    WHEN COALESCE(MRS.TermDay, 0) = 5 THEN 5
                    ELSE NULL
                END "termUse",
                MRS.MedRecipeStandartCode "recipeId",
                MRS.Outdate "outDate",
                MRS.StartDate "startDate",
                MRS.DocumentSeries "series",
                MRS.documentNumber "number",
                null "contingentId",
                null "privelege",
                MD.MedDoctorCode "doctorId",
                (select Name
                    from Enterprise
                    where EnterpriseCode = MRS.EnterpriseCode) "enterprise",
                MRS.EnterpriseCode "enterpriseId",
                CASE MD.manCode IS NULL
                    WHEN true THEN NULL
                    WHEN false THEN (FN_cmFIO(MD.manCode, 1) || ', ')::text || MP.Name
                END "doctorFio",
                DP.Name "departmentName",
                MW.MatWealthCode "drugId",
                FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
                    CASE mRS.Amount = 0
                        WHEN true THEN ''
                        WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                            WHEN true THEN MW.DoseNumber
                                                                            WHEN false THEN 1
                                                                           END)))
                    END "displayWealth",
                MRS.MethodUse "methodUse",
                MRS.Amount "amount",
                MRS.UseINN "useInn",
                null "privelegeName",
                null "privilegePercent",
                MRS.State "state",
                (CASE
                    WHEN COALESCE(MRS.State, 0) = 5 THEN 1 -- красный
                    WHEN COALESCE(MRS.State, 0) = 1 THEN 2 -- желтый
                    WHEN COALESCE(MRS.CodeHL7, '') <> '' AND COALESCE(MRS.State) IN (0, 3) THEN 3 -- зеленый
                    ELSE 0 -- белый
                END::integer) "stateFlag",
                null "recipeIdOld",
                coalesce(MRS.CodeHL7,'') "codeHL7",
                MRS.SignatureType "signatureType"
            from MedRecipeStandart MRS
                join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
                join Department DP on DP.DepartmentCode = MD.DepartmentCode
                join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
                join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
            where MRS.MedPatientCode = $patientId
              and COALESCE(MRS.OutDate, 30000101) <= LOCALTIMESTAMP
              and MRS.EnterpriseCode = $enterpriseId
             and COALESCE(MRS.State, 0) <> 2
            order by MRS.OutDate desc
           ) _recipesStandart
      union all
      select *
      from (select
                null "purchaseCount",
                null "purchasePeriodDay",
                MRS.MedRegistrySpecialCode "recordId",
                MRS.Outdate "recordDate",
                MRS.Outdate "parentDate",
                COALESCE(MRS.MedRegistrySpecialCode, '')::text || '-' "path",
                8 "recordType",
                null "parentId",
                null "parentType",
                6 "sortType",
                2 "type",
                null,
                MRS.MedRegistrySpecialCode "recipeId",
                MRS.Outdate "outDate",
                null,
                '' "series",
                MRS.documentNumber "number",
                null "contingentId",
                null "privelege",
                MD.MedDoctorCode "doctorId",
                (select Name
                    from Enterprise
                    where EnterpriseCode = MRS.EnterpriseCode) "enterprise",
                MRS.EnterpriseCode "enterpriseId",
                CASE MD.manCode IS NULL
                    WHEN true THEN NULL
                    WHEN false THEN (FN_cmFIO(MD.manCode, 1) || ', ')::text || MP.Name
                END "doctorFio",
                DP.Name "departmentName",
                MW.MatWealthCode "drugId",
                FN_cmMatWealthNameEx(mRS.MatWealthCode, 14, COALESCE(MRS.UseINN, 0)) +
                    CASE mRS.Amount = 0
                        WHEN true THEN ''
                        WHEN false THEN ' №' + asa_trim(STR(mRS.Amount * (CASE COALESCE(MW.DoseNumber, 0) <> 0
                                                                            WHEN true THEN MW.DoseNumber
                                                                            WHEN false THEN 1
                                                                           END)))
                    END "displayWealth",
                MRS.MethodUse "methodUse",
                MRS.Amount "amount",
                MRS.UseINN "useInn",
                null "privelegeName",
                null "privilegePercent",
                MRS.State "state",
                0::integer "stateFlag",
                null "recipeIdOld",
                coalesce(MRS.CodeHL7,'') "codeHL7",
                MRS.SignatureType "signatureType"
            from MedRegistrySpecial MRS
                join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
                join Department DP on DP.DepartmentCode = MD.DepartmentCode
                join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
                join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
            where MRS.MedPatientCode = $patientId
              and COALESCE(MRS.OutDate, 30000101) <= LOCALTIMESTAMP
              and MRS.EnterpriseCode = $enterpriseId
              and COALESCE(MRS.State, 0) <> 2
            order by MRS.OutDate desc
            ) _recipesSpecial
) _recipesAll
order by _recipesAll."outDate" desc
EOT;
return selectSql($db, $sql); } } handleJsonRpc('med_recipes_recipes'); 