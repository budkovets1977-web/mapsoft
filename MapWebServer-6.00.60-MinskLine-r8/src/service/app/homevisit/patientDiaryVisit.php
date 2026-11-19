<?php
 require_once(__DIR__.'/../init.php'); suspendJsonRpc(); require_once(__DIR__.'/../med/recipes/sign.php'); resumeJsonRpc(); class homevisit_patientDiaryVisit { const ERROR_NO_DATA = -1; const ERROR_NO_VISIT = -2; const ERROR_NO_VISITPATIENT = -3; const ERROR_NO_VISITDOCUMENT = -4; const ERROR_NO_HOSPITALNUMBER = -5; const ERROR_NO_HOSPITAL = -6; const ERROR_NO_COUPON = -7; const ERROR_COUPON_ALREADY_ORDERED = -8; const ERROR_COUPON_ALREADY_ISSUED = -9; const ERROR_COUPON_CANCELED = -10; const ERROR_COUPON_UNKNOWN_STATUS = -11; const ERROR_COUPON_NOT_ORDERED = -12; const ERROR_NO_DRUGRECIPE = -13; const SOLIS_MESSAGE = "Откройте направление для назначения штрихкода и отправки! Создано в мобильном приложении"; private $db; private function getCoupon($id) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select coalesce(CouponState, 0) "state"
from MedCoupon
where MedCouponCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); if (!($row = $stmt->fetch())) { throw new Exception("Неверный идентификатор талона ($id)!", self::ERROR_NO_COUPON); } return $row; } private function syncRecipe($patientId, $type, $recipes, $ntSignActive) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $enterprise = getSessionValue('mapEnterpriseCode'); $doctor = getSessionValue('mapMedDoctor'); $outDate = "OutDate"; switch ($type) { case 0: $table = "MedRecipeStandart"; break; case 1: $table = "MedRecipe"; $outDate = "RecipeDate"; break; case 2: $table = "MedRegistrySpecial"; break; default: return; } $tableCode = $table."Code"; $sqlInCondition = createSqlInCondition($recipes); executeSql($db, <<<"EOT"
delete from $table where EnterpriseCode = ?
    and MedPatientCode = ?
    and date($outDate) = date(now())
    and MedDoctorCode = ?
    and $tableCode not $sqlInCondition
    and coalesce(CodeHL7, '') = ''
EOT
, [$enterprise, $patientId, $doctor['MedDoctorCode']]); executeSql($db, <<<"EOT"
update $table set State = 2 where EnterpriseCode = ?
    and MedPatientCode = ?
    and date($outDate) = date(now())
    and MedDoctorCode = ?
    and $tableCode not $sqlInCondition
    and coalesce(CodeHL7, '') <> ''
EOT
, [$enterprise, $patientId, $doctor['MedDoctorCode']]); foreach ($recipes as $r) { $sd = isEmptyProperty($r, 'startDate'); if ($sd) { $sd = date_format(safe_date_create($sd), 'Y-m-d'); } $termUse = (int) isEmptyProperty($r, 'termUse'); if ($type == 1) { $sql = <<<"EOT"
select {$db->sybase("first")} MedRecipeCode "id"
from MedRecipe
where EnterpriseCode = ?
    and MedDoctorCode = ?
--    and DocumentSeries = ?
--    and coalesce(DocumentSeries, '') <> ''
--    and DocumentNumber = ?
--    and coalesce(DocumentNumber, '') <> ''
    and coalesce(DocumentSeries, '') = ?
    and coalesce(DocumentNumber, '') = ?
    and RecipeDate is null
{$db->postgre("limit 1")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterprise, $doctor['MedDoctorCode'], isEmptyProperty($r, 'series', ''), isEmptyProperty($r, 'number', '') ]); $row = $stmt->fetch(); if ($row && $row['id']) { $r->id = (int) $row['id']; } if ($r->id == 0) { $sql = <<<"EOT"
insert into MedRecipe (RecipeDate, DocumentNumber, MedPatientCode, MedDoctorCode,
    PrivilegePercent, DocumentSeries, TermDay, TermMonth, TermYear, EnterpriseCode,
    RecipeType, FlagConfirm, MedPrivilegeGroupCode, MedPrivelegeCode, State, UseINN, StartDate)
values (now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 2, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($r, 'number'), $patientId, $doctor['MedDoctorCode'], isEmptyProperty($r, 'privilegePercent'), isEmptyProperty($r, 'series'), $termUse === 2 ? 30 : ($termUse === 3 ? 5 : null), $termUse === 1 ? 2 : null, null, $enterprise, isEmptyProperty($r, 'privilegeGroupId'), isEmptyProperty($r, 'privilegeId'), 1, (int) isEmptyProperty($r, 'useInn', 0), $sd ]); $r->id = $db->isSybase ? (int) $db->lastInsertId() : (int) $db->lastInsertId("seq_MedRecipe"); $isUpdate = false; } else { $sql = <<<"EOT"
update MedRecipe set
    RecipeDate = now(),
    DocumentNumber = ?,
    MedPatientCode = ?,
    PrivilegePercent = ?,
    DocumentSeries = ?,
    TermDay = ?,
    TermMonth = ?,
    TermYear = ?,
    MedPrivilegeGroupCode = ?,
    MedPrivelegeCode = ?,
    State = ?,
    UseINN = ?,
    StartDate = ?
where MedRecipeCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($r, 'number'), $patientId, isEmptyProperty($r, 'privilegePercent'), isEmptyProperty($r, 'series'), $termUse === 2 ? 30 : ($termUse === 3 ? 5 : null), $termUse === 1 ? 2 : null, null, isEmptyProperty($r, 'privilegeGroupId'), isEmptyProperty($r, 'privilegeId'), 1, (int) isEmptyProperty($r, 'useInn', 0), $sd, $r->id ]); $isUpdate = true; } if (!$r->id) { throw new Exception("Не удалось создать запись льготного рецепта (MedRecipe)!", self::ERROR_NO_DRUGRECIPE); } $sql = $db->isSybase ? <<<"EOT"
begin
    declare varCode integer;
    declare varRecipeCode integer;
    declare varAmount numeric(20,4);
    declare varWealthCode integer;
    declare varMethodUse varchar(250);
    set varRecipeCode = {$r->id};
    set varAmount = {$r->amount};
    set varWealthCode = {$r->drugId};
    set varMethodUse = '{$r->methodUse}';
    set varCode = (
        select first MedRecipeWealthCode
        from MedRecipeWealth
        where MedRecipeCode = varRecipeCode);
    if varCode is null then
        insert into MedRecipeWealth (MedRecipeCode, Amount, MatWealthCode, MethodUse)
        values (varRecipeCode, varAmount, varWealthCode, varMethodUse);
    else
        update MedRecipeWealth set
            Amount = varAmount,
            MatWealthCode = varWealthCode,
            MethodUse = varMethodUse
        where MedRecipeWealthCode = varCode;
    end if;
end
EOT
: <<<"EOT"
DO \$anon$
DECLARE
    varCode integer;
    varRecipeCode integer;
    varAmount numeric(20,4);
    varWealthCode integer;
    varMethodUse varchar(250);
BEGIN
    varRecipeCode := {$r->id};
    varAmount := {$r->amount};
    varWealthCode := {$r->drugId};
    varMethodUse :=  E'{$r->methodUse}' ;
    varCode := (select MedRecipeWealthCode
        from MedRecipeWealth
        where MedRecipeCode = varRecipeCode LIMIT 1);
    if varCode is null then
        insert into MedRecipeWealth (medrecipecode, amount, matwealthcode, methoduse)
        values (varRecipeCode, varAmount, varWealthCode, varMethodUse);
    else
        UPDATE MedRecipeWealth set
            amount = varAmount,
            matwealthcode = varWealthCode,
            methoduse = varMethodUse
        where MedRecipeWealthCode = varCode;
    end if;
END \$anon$;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } else { $isUpdate = false; if ($r->id == 0) { if ($r->type === 0 && !isEmptyProperty($r, 'series') && !isEmptyProperty($r, 'number')) { $stmt = $db->prepare("select FN_medGetStandartRecipeSN($enterprise, ';') sn"); $stmt->execute(); $row = $stmt->fetch(); if ($row && $row['sn']) { $arr = safe_explode(';', $row['sn']); if ($arr) { $r->series = isEmptyArrayValue($arr, 0); $r->number = isEmptyArrayValue($arr, 1); } } } $seriesField = "DocumentSeries, "; $series = "'".isEmptyProperty($r, 'series')."',"; $startDateField = "StartDate, "; $startDate = "'".$sd."',"; $termField = "TermDay, TermMonth, TermYear, "; $term = ($termUse == 2 ? "30" : ($termUse == 3 ? "5" : "null")).", ".($termUse == 1 ? "2" : "null").","."null, "; if ($r->type === 2) { $seriesField = ""; $series = ""; $startDateField = ""; $startDate = ""; $termField = ""; $term = ""; $r->number = isEmptyProperty($r, 'series') && isEmptyProperty($r, 'number') ? $r->series."-".$r->number : isEmptyProperty($r, 'number') || isEmptyProperty($r, 'series'); } $sql = <<<"EOT"
insert into $table (EnterpriseCode, OutDate, DepartmentCode,
    MedDoctorCode, MedPatientCode, MatWealthCode, Amount, MethodUse,
    $termField $seriesField DocumentNumber, State, $startDateField UseINN)
values (?, now(), ?, ?, ?, ?, ?, ?, $term $series ?, ?, $startDate ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterprise, $doctor['DepartmentCode'], $doctor['MedDoctorCode'], $patientId, (int) $r->drugId, isEmptyProperty($r, 'amount'), isEmptyProperty($r, 'methodUse'), isEmptyProperty($r, 'number'), 1, (int) isEmptyProperty($r, 'useInn', 0) ]); $r->id = $db->isSybase ? (int) $db->lastInsertId() : (int) $db->lastInsertId("seq_$table"); } else { $isUpdate = true; $seriesUpdate = "DocumentSeries = '{$r->series}',"; $startDateUpdate = "StartDate = '{$sd}',"; $termUpdate = "TermDay = ".($termUse == 2 ? "30" : ($termUse == 3 ? "5" : "null")).", TermMonth = ".($termUse == 1 ? "2" : "null").", TermYear = null, "; if ($r->type === 2) { $r->number = isEmptyProperty($r, 'series') && isEmptyProperty($r, 'number') ? $r->series."-".$r->number : isEmptyProperty($r, 'number') || isEmptyProperty($r, 'series'); $seriesUpdate = ""; $startDateUpdate = ""; $termUpdate = ""; } if ($r->type === 0 && !isEmptyProperty($r, 'series')) { $numberUpdate = ""; } $numberUpdate = "DocumentNumber = '{$r->number}',"; if ($r->type === 0 && !isEmptyProperty($r, 'number')) { $numberUpdate = ""; } $sql = <<<"EOT"
update $table set
    MatWealthCode = ?,
    Amount = ?,
    MethodUse = ?,
    $termUpdate
    $seriesUpdate
    $numberUpdate
    State = ?,
    $startDateUpdate
    UseINN = ?
where $tableCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ (int) $r->drugId, isEmptyProperty($r, 'amount'), isEmptyProperty($r, 'methodUse'), 1, (int) isEmptyProperty($r, 'useInn', 0), $r->id ]); } } if ($ntSignActive && !$isUpdate) { $typeStr = strtolower($table); app_log("На подпись пациент $patientId, врач {$doctor['MedDoctorCode']}, рецепт $typeStr $r->id", 0); $generator = new med_recipes_sign($db); $content = $generator->signMainController($patientId, $doctor['MedDoctorCode'], $r->id, $typeStr); if (!empty($content)) { app_log("sign: $content->sign", 0); app_log("signedContent: $content->signedContent", 0); $content64 = base64_encode($content->sign); executeSql($db, <<<"EOT"
insert into signDocument (Source, SourceKey, Document) values (?,?,?)
on conflict (Source, SourceKey) do update set Document = excluded.Document
EOT
, [$typeStr, $r->id, $content->signedContent]); $ret[] = (object)[ 'id' => (int)$r->id, 'type' => $typeStr, 'description' => $r->displayName, 'content' => $content64 ]; } else { app_log("signMainController() вернул false", 2); } } } return $ret; } public function saveSignatures ($params) { if (!empty($params)) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update signDocument set Type = ?, Signature = ?
where Source = ? and SourceKey = ?
EOT;
$stmt = $db->prepare($sql); foreach ($params->signatures as $sign) { $signature = isset($sign->signature) ? $sign->signature : ''; $stmt->execute([ 4, $signature, $sign->type, $sign->id ]); $table = $sign->type; $tablePK = $table . 'code'; executeSql($db, <<<"EOT"
update $table set State = 1, SignatureType = 3, Signature = ?,
    SignedContent = (select Document from signDocument where Source = ? and SourceKey = ?)
where $tablePK = ?
EOT
, [$signature, $sign->type, $sign->id, $sign->id]); } } $this->sendToDPC($params->patientId); } private function sendToDPC ($patientId) { $db = $this->db ?: $this->db = connectDb(); $this->dcpSettings = readExtraSettings(null, 'medRecipesLinkRecipes'); if (!isEmptyProperty($this->dcpSettings, 'active')) { app_log("Обмен с ЦОД отключен"); return; } app_log("Отправка рецептов в ЦОД"); $enterpriseId = getSessionValue('mapEnterpriseCode'); $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $tables = ['medrecipestandart', 'medrecipe']; foreach ($tables as $table) { $outDate = ($table === 'medrecipe' ? 'recipedate' : 'outdate'); $tablePk = $table . 'code'; $sql = <<<"EOT"
select
    $tablePk as "id",
    case when coalesce(SignedContent,'') <> '' then 1 else 0 end "existContent",
    DocumentNumber "number"
from $table
where
    EnterpriseCode = ?
    and MedPatientCode = ?
    and MedDoctorCode = ?
    and date($outDate) = date(now())
    and State in (1,2,5)
    and (
        State = 2 -- посылаем отмена всегда
        or coalesce(SignatureType,0) = 0 -- без подписи всегда
        or coalesce(CodeHL7,'') = '') -- иначе, только если не посылался ранее
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseId, $patientId, $doctorId ]); $error = ''; while(($row = $stmt->fetch(PDO::FETCH_OBJ))) { app_log("Рецепт $table:$row->id"); if (empty($row->existContent)) { app_log('отсутствует xml - создание'); $xmlGen = new med_recipes_sign($db); $content = $xmlGen->signMainController($patientId, $doctorId, $row->id, $table); if (!empty($content)) { executeSql($db, "update $table set SignedContent = ? where $tablePk = ?", [$content->signedContent, $row->id]); app_log("создано XML"); } else { app_log("signMainController() вернул false", 2); } } $generator = new med_recipes_sign($db); if (!$generator->sendRecipe2DPC($row->id, $table)) { app_log("Ошибка отправки рецепта в ЦОД ($table:$row->id)", 2); if (empty($error)) { $error = "Ошибка отправки в ЦОД рецептов с номерами: "; } else { $error .= ", "; } $error .= $row->number; } else { app_log("успешная отправка в ЦОД"); } } if (!empty($error)) { throw new Exception($error . '!'); } } } public function readDiary ($params) { if (!$params || !$params->elements) { return; } setSessionValue('homeVisitDiaryElements', $params->elements); $db = $this->db ?: $this->db = connectDb(); $doctor = getSessionValue('mapMedDoctor'); if (!$doctor) { return null; } $sqlVisit = <<<"EOT"
select {$db->sybase('first')}
    v.MedVisitCode "visitId",
    vp.MedVisitPatientCode "visitPatientId",
    vp.MedDiagnosisCode "diagnosisId",
    diag.Cipher1 "diagnosisCipher",
    diag.Name "diagnosisName",
    diag.DiagnosisType "diagnosisType",
    {$db->dialect("if vp.VisitEndNew is not null and vp.VisitEndNew <> 2 then 1 else 0 endif", "case
                     when vp.VisitEndNew is not null and vp.VisitEndNew <> (2) then 1
                     else 0
                   end")} "diagnosisRegister",
    vp.Pressure "pressure",
    v.VisitDate "visitDate"
from MedVisit v
left outer join MedVisitPatient vp on vp.MedVisitCode = v.MedVisitCode
    and vp.MedPatientCode = ?
    and coalesce(vp.VisitPlace, 0) <> 0
    and coalesce(vp.AttendantDiagnosis, 0) = 0
left outer join MedDiagnosis diag on diag.MedDiagnosisCode = vp.MedDiagnosisCode
where v.MedDoctorCode = ?
    and v.VisitDate = ?
    and v.MedProfessionCode = ?
    and v.DepartmentCode = ?
order by coalesce(vp.AttendantDiagnosis, 0)
{$db->postgre('limit 1')}
EOT;
$stmtVisit = $db->prepare($sqlVisit); $stmtVisit->execute([ isEmptyProperty($params, 'patientId'), $doctor['MedDoctorCode'], isEmptyProperty($params, 'onDate', date("Ymd")), $doctor['MedProfessionCode'], $doctor['DepartmentCode'] ]); $rowVisit = $stmtVisit->fetch() ?: []; $sqlElement = <<<"EOT"
select {$db->sybase('first')} MedVisitWebDataCode "id", Value "value" from MedVisitWebData
where MedVisitPatientCode = ? and Name = ? {$db->postgre('limit 1')}
EOT;
$stmtElement = $db->prepare($sqlElement); $sql = <<<'EOT'
select
    o.MedLexemeCode "lexemeId",
    l.Name "lexemeName"
from MedLexemeOption o
join MedLexeme l on l.MedLexemeCode = o.MedLexemeCode
where o.Name = ?
order by l.Position
EOT;
$stmt = $db->prepare($sql); $ret = new \stdClass; $ret->data = new \stdClass; $ret->data->visitId = (int) isUndefinedArrayValue($rowVisit, 'visitId'); $ret->data->visitPatientId = (int) isUndefinedArrayValue($rowVisit, 'visitPatientId'); $ret->data->visitDate = isUndefinedArrayValue($rowVisit, 'visitDate'); $elements = []; for ($i = 0; $i < count($params->elements); $i++) { $element = new \stdClass; $element->name = $params->elements[$i]; $stmtElement->execute([ $ret->data->visitPatientId, $element->name ]); $el = $stmtElement->fetch() ?: []; $element->id = isUndefinedArrayValue($el, 'id'); $element->value = isUndefinedArrayValue($el, 'value'); $stmt->execute([$element->name]); $lexemes = []; while ($row = $stmt->fetch()) { $lexeme = new \stdClass; $lexeme->id = (int) $row['lexemeId']; $lexeme->name = $row['lexemeName']; $lexemes[] = $lexeme; } $element->lexemes = $lexemes; $elements[] = $element; } $ret->elements = $elements; $diagnosis = null; if ($rowVisit) { if ($rowVisit['diagnosisId']) { $diagnosis = new \stdClass; $diagnosis->id = (int) $rowVisit['diagnosisId']; $diagnosis->cipher = $rowVisit['diagnosisCipher']; $diagnosis->name = $rowVisit['diagnosisName']; $diagnosis->register = (bool) $rowVisit['diagnosisRegister']; $diagnosis->type = (int) $rowVisit['diagnosisType']; $ret->data->diagnosis = $diagnosis; } $ret->data->pressure = $rowVisit['pressure']; } $sql = <<<'EOT'
select
    vp.MedVisitPatientCode "visitPatientId",
    vp.MedDiagnosisCode "diagnosisId",
    diag.Cipher1 "diagnosisCipher",
    diag.Name "diagnosisName",
    diag.DiagnosisType "diagnosisType"
from MedVisitPatient vp
join MedDiagnosis diag on diag.MedDiagnosisCode = vp.MedDiagnosisCode
where vp.MedVisitCode = ?
    and vp.MedPatientCode = ?
    and coalesce(vp.AttendantDiagnosis, 0) = 1
order by 4, 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ (int) isUndefinedArrayValue($rowVisit, 'visitId'), isEmptyProperty($params, 'patientId') ]); $addDiagnoses = []; while ($row = $stmt->fetch()) { $addDiagnosis = new \stdClass; $addDiagnosis->id = (int) $row['diagnosisId']; $addDiagnosis->cipher = $row['diagnosisCipher']; $addDiagnosis->name = $row['diagnosisName']; $addDiagnosis->type = (int) $row['diagnosisType']; $addDiagnosis->visitPatientId = (int) $row['visitPatientId']; $addDiagnoses[] = $addDiagnosis; } if (count($addDiagnoses) > 0) { $ret->data->addDiagnoses = $addDiagnoses; } $sql = <<<'EOT'
select
    mls.MedLogStudyCode "id",
    ms.Name "studyName",
    ms.MedStudyCode "studyId",
    ms.Type "studyType",
    mls.pin as "pin",
    (select array_to_json(array_agg(row_to_json(t)))
    from (
        select
            st.UnitName as "unitName",
            st.LoincCode as "LoincCode",
            st.InternalId as "InternalId"
        from MedLabStudy as s
        join lateral (
            select json_object_keys(mls.RawResult::json) as un
        ) as kt on 1 = 1
        join lateral (
            select *
            from json_to_record((mls.RawResult::json ->> kt.un)::json) as t("InternalId" int, "LoincCode" text)
        ) as rr  on 1 = 1
        join MedLabStudyTest as st on st.MedLabStudyCode = s.MedLabStudyCode
        where s.MedStudyCode = ms.MedStudyCode
            and s.Active = 1
            and st.Active = 1
            and st.UnitName = kt.un
            and st.InternalId = rr."InternalId"
        ) as t
    ) as "solisTests"
from MedLogStudy mls
join MedStudy ms on ms.MedStudyCode = mls.MedStudyCode
where mls.MedPatientCode = ?
    and date(mls.DirTime) = ?
    and mls.MedDoctorCode = ?
order by 4, 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'patientId'), isEmptyProperty($params, 'onDate', date("Ymd")), $doctor['MedDoctorCode'] ]); $studies = []; while ($row = $stmt->fetch()) { $study = new \stdClass; $study->id = (int) $row['id']; $study->studyName = $row['studyName']; $study->studyId = (int) $row['studyId']; $study->studyType = (int) $row['studyType']; $study->pin = $row['pin']; $st = false; if ((string) $row['solisTests']) { $st = json_clean_decode((string) $row['solisTests']); } if (is_array($st)) { $study->solisTests = $st; } $studies[] = $study; } if (count($studies) > 0) { $ret->data->studies = $studies; } $sql = <<<"EOT"
select
    "type",
    "recipeId",
    DocumentSeries "series",
    DocumentNumber "number",
    MatWealthCode "drugId",
    FN_cmMatWealthNameEx(MatWealthCode, 14) "drugName",
    FN_cmMatWealthNameEx(MatWealthCode, 14, 1) "drugNameInn",
    FN_cmMatWealthNameEx(MatWealthCode, 6) "wealthName",
    FN_cmMatWealthNameEx(MatWealthCode, 6, 1) "wealthNameInn",
    MethodUse "methodUse",
    Amount "amount",
    TermUse "termUse",
    UseINN "useInn",
    {$db->dialect( "if Amount = 0 then '' else '№' + trim(str(Amount * dn)) + ' ( ' + FN_cmSumProp(Amount * dn, 0, 0, 1 ) + ' )' endif as \"amountText\"", "case Amount = 0 when true then '' when false then  '№' + trim(str(Amount * dn)) +  ' ( ' + FN_cmSumProp(Amount * dn, 0, 0, 1 ) + ' )' end as \"amountText\"" )},
    StartDate "startDate",
    MedPrivelegeCode "privilegeId",
    MedPrivilegeGroupCode "privilegeGroupId",
    PrivilegePercent "privilegePercent",
    case when
/*            coalesce(SignatureType,0) <> 0 -- есть подпись
            and coalesce(CodeHL7,'') <> '' -- лежит в ЦОД
            and coalesce(State,0) not in (1,2) -- и нет признаков повторной передачи в ЦОД
*/
-- WS-622 с 27.07.2020 все отправленные в ЦОД не редактируются
        coalesce(CodeHL7,'') <> ''
        then 1 -- тогда нередактируемый (удалять можно)
        else 0 end "notEditable"
from (
    select
        0 "type",
        rs.MedRecipeStandartCode "recipeId",
        rs.MatWealthCode,
        rs.Amount,
        rs.MethodUse,
        rs.MedPatientCode,
        rs.MedDoctorCode,
        rs.OutDate,
        rs.EnterpriseCode,
        rs.DocumentSeries,
        rs.DocumentNumber,
        {$db->dialect("if coalesce(rs.TermMonth, 0) = 2 then 1 else
                           if coalesce(rs.TermDay, 0) = 30 then 2 else
                               if coalesce(rs.TermDay, 0) = 5 then 3 else null
                               endif
                           endif
                       endif", "case
                         when coalesce(rs.TermMonth, 0) = 2 then 1
                         else case
                                when coalesce(rs.TermDay, 0) = 30 then 2
                                else case
                                       when coalesce(rs.TermDay, 0) = 5 then 3
                                       else null
                                     end
                              end
                       end")} TermUse,
        rs.UseINN,
        {$db->dialect( "if coalesce(mw.DoseNumber, 0) <> 0 then mw.DoseNumber else 1 endif as dn", "case coalesce(mw.DoseNumber, 0) <> 0 when true then mw.DoseNumber when false then 1 end as dn" )},
        rs.StartDate,
        cast(null as integer) as MedPrivelegeCode,
        cast(null as integer) as MedPrivilegeGroupCode,
        cast(null as numeric) as PrivilegePercent,
        rs.State,
        rs.SignatureType,
        rs.CodeHL7
    from MedRecipeStandart rs
    join MatWealth mw on mw.MatWealthCode = rs.MatWealthCode
    where coalesce(rs.State, 0) <> 2
    union
    select
        1 "type",
        r.MedRecipeCode "recipeId",
        rw.MatWealthCode,
        rw.Amount,
        rw.MethodUse,
        r.MedPatientCode,
        r.MedDoctorCode,
        r.RecipeDate OutDate,
        r.EnterpriseCode,
        r.DocumentSeries,
        r.DocumentNumber,
        {$db->dialect("if coalesce(r.TermMonth, 0) = 2 then 1 else
                           if coalesce(r.TermDay, 0) = 30 then 2 else
                               if coalesce(r.TermDay, 0) = 5 then 3 else null
                               endif
                           endif
                       endif", "case
                         when coalesce(r.TermMonth, 0) = 2 then 1
                         else case
                                when coalesce(r.TermDay, 0) = 30 then 2
                                else case
                                       when coalesce(r.TermDay, 0) = 5 then 3
                                       else null
                                     end
                              end
                       end")} TermUse,
        r.UseINN,
        {$db->dialect( "if coalesce(mw.DoseNumber, 0) <> 0 then mw.DoseNumber else 1 endif as dn", "case coalesce(mw.DoseNumber, 0) <> 0 when true then mw.DoseNumber when false then 1 end dn" )},
        r.StartDate,
        r.MedPrivelegeCode,
        r.MedPrivilegeGroupCode,
        r.PrivilegePercent,
        r.State,
        r.SignatureType,
        r.CodeHL7
    from MedRecipe r
    join MedRecipeWealth rw on rw.MedRecipeWealthCode = (
        select {$db->sybase('first')} MedRecipeWealthCode
        from MedRecipeWealth
        where MedRecipeCode = r.MedRecipeCode {$db->postgre('limit 1')})
    join MatWealth mw on mw.MatWealthCode = rw.MatWealthCode
    where r.RecipeType = 0
        and coalesce(r.State, 0) <> 2
    union
    select
        2 "type",
        rs.MedRegistrySpecialCode "recipeId",
        rs.MatWealthCode,
        rs.Amount,
        rs.MethodUse,
        rs.MedPatientCode,
        rs.MedDoctorCode,
        rs.OutDate,
        rs.EnterpriseCode,
        {$db->dialect("trim(if trim(rs.DocumentNumber) = '' then '' else ExtractWord(1, rs.DocumentNumber, '-') endif)", "asa_trim(case
                                  when asa_trim(rs.DocumentNumber) = E'' then E''
                                  else ExtractWord(1, rs.DocumentNumber, E'-')
                                end)")} DocumentSeries,
        {$db->dialect("trim(if trim(rs.DocumentNumber) = '' then '' else ExtractWord(2, rs.DocumentNumber, '-') endif)", "asa_trim(case
                                  when asa_trim(rs.DocumentNumber) = E'' then E''
                                  else ExtractWord(2, rs.DocumentNumber, E'-')
                                end)")} DocumentNumber,
        null TermUse,
        rs.UseINN,
        {$db->dialect( "if coalesce(mw.DoseNumber, 0) <> 0 then mw.DoseNumber else 1 endif as dn", "case coalesce(mw.DoseNumber, 0) <> 0 when true then mw.DoseNumber when false then 1 end dn" )},
        null StartDate,
        null MedPrivelegeCode,
        null MedPrivilegeGroupCode,
        null PrivilegePercent,
        rs.State,
        rs.SignatureType,
        rs.CodeHL7
    from MedRegistrySpecial rs
    join MatWealth mw on mw.MatWealthCode = rs.MatWealthCode
    where coalesce(rs.State, 0) <> 2) recipes
where MatWealthCode is not null
    and MedPatientCode = ?
    and MedDoctorCode = ?
    and OutDate = ?
    and EnterpriseCode = ?
order by 1 desc
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'patientId'), $doctor['MedDoctorCode'], isEmptyProperty($params, 'onDate', date("Ymd")), getSessionValue('mapEnterpriseCode') ]); $recipes = []; while ($row = $stmt->fetch()) { $recipe = new \stdClass; $recipe->id = (int) $row['recipeId']; $recipe->type = (int) $row['type']; $recipe->series = $row['series']; $recipe->number = $row['number']; $recipe->drugId = (int) $row['drugId']; $recipe->drugName = $row['drugName']; $recipe->drugNameInn = $row['drugNameInn']; $recipe->wealthName = $row['wealthName']; $recipe->wealthNameInn = $row['wealthNameInn']; $recipe->methodUse = $row['methodUse']; $recipe->amount = (float) $row['amount']; $recipe->termUse = (int) $row['termUse']; $recipe->useInn = (bool) $row['useInn']; $recipe->amountText = (string) $row['amountText']; $recipe->startDate = $row['startDate']; $recipe->privilegeId = $row['privilegeId']; $recipe->privilegeGroupId = $row['privilegeGroupId']; $recipe->privilegePercent = $row['privilegePercent']; $recipe->notEditable = (bool)$row['notEditable']; $recipes[] = $recipe; } if (count($recipes) > 0) { $ret->data->recipes = $recipes; } $sql = <<<"EOT"
select {$db->sybase("first")}
    h.MedHospitalCode "id",
    hn.DocumentSeries "series",
    hn.DocumentNumber "number",
    dateformat(h.StartDate, {$db->postgre("E")}'dd.mm.yyyy') "startDate",
    fn_cmFio(doc.ManCode, 1) "doctorName",
    p.Name "doctorProfession",
    d.Cipher1 "diagnosisCipher",
    d.Name "diagnosisName"
from MedHospital h
left outer join MedHospitalNext hn on hn.MedHospitalCode = h.MedHospitalcode
join MedDoctor doc on doc.MedDoctorCode = h.MedDoctorOpenCode
left outer join MedProfession p on p.MedProfessionCode = doc.MedProfessionCode
join MedDiagnosis d on d.MedDiagnosisCode = h.MedDiagnosisPrimaryCode
where h.MedPatientCode = ?
    and h.StartDate <= ?
    and coalesce(h.FinishDate, 30000101) = 30000101
order by h.StartDate desc {$db->postgre("NULLS LAST")}, hn.Position desc {$db->postgre("NULLS LAST")}
{$db->postgre("limit 1")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'patientId'), isEmptyProperty($params, 'onDate', date("Ymd")) ]); $row = $stmt->fetch(); if ($row) { $hospital = new \stdClass; $hospital->id = (int) $row['id']; $hospital->series = $row['series']; $hospital->number = $row['number']; $hospital->startDate = $row['startDate']; $hospital->doctorName = $row['doctorName']; $hospital->doctorProfession = $row['doctorProfession']; $hospital->diagnosisCipher = $row['diagnosisCipher']; $hospital->diagnosisName = $row['diagnosisName']; $ret->data->hospital = $hospital; } $sql = <<<"EOT"
select {$db->sybase("first")}
    c.MedCouponCode "id",
    dateformat(c.OnDate, {$db->postgre("E")}'dd.mm.yyyy') "onDate",
    dateformat(c.VisitTime, {$db->postgre("E")}'hh:nn') "onTime"
from MedCoupon c
join MedCouponWork cw on cw.MedCouponWorkCode = c.MedCouponWorkCode
    and cw.FlagSecondReception = 1
where c.CouponState in (1, 2)
    and c.MedDoctorCode = ?
    and c.MedPatientCode = ?
    and c.OnDate > ?
order by c.OnDate, c.VisitTime
{$db->postgre("limit 1")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $doctor['MedDoctorCode'], isEmptyProperty($params, 'patientId'), isEmptyProperty($params, 'onDate', date("Ymd")) ]); $row = $stmt->fetch(); if ($row) { $coupon = new \stdClass; $coupon->id = (int) $row['id']; $coupon->onDate = $row['onDate']; $coupon->onTime = $row['onTime']; $ret->data->coupon = $coupon; } $couponsDirection = selectSql( $db, <<<"EOT"
select
    c.MedCouponCode as "id",
    to_char(c.OnDate, 'YYYY-MM-DD') || to_char(c.VisitTime, ' HH24:MI:SS') as "onTime",
    d.MedProfessionCode as "professionId",
    p.Name as "professionName",
    c.MedDoctorCode as "doctorId",
    fn_cmFio(d.ManCode) as "doctorName"
from MedLogSend as ls
join MedCoupon as c on c.MedCouponCode = ls.MedCouponCode
join MedDoctor as d on d.MedDoctorCode = c.MedDoctorCode
join MedProfession as p on p.MedProfessionCode = d.MedProfessionCode
where ls.SendType = 1
    and ls.DocumentDate = :onDate
    and ls.MedDoctorCode = :doctorId
    and ls.MedPatientCode = :patientId
EOT,
[ 'onDate' => isEmptyProperty($params, 'onDate', date("Ymd")), 'doctorId' => $doctor['MedDoctorCode'], 'patientId' => isEmptyProperty($params, 'patientId') ] ); if ($couponsDirection && count($couponsDirection) > 0) { $ret->data->couponsDirection = $couponsDirection; } return $ret; } public function saveDiary ($params) { if (!isset($params) || !isset($params->data)) { throw new Exception("Отсутствуют данные для сохранения!", self::ERROR_NO_DATA); } $db = $this->db ?: $this->db = connectDb(); $enterpriseId = (int) getSessionValue('mapEnterpriseCode'); $doctor = getSessionValue('mapMedDoctor'); $elements = $params->elements; $data = $params->data; $diagnosisId = (int) isEmptyProperty(isEmptyProperty($data, 'diagnosis'), 'id'); $hospital = $data->hospital; $visitId = (int) $data->visitId; $visitPatientId = (int) $data->visitPatientId; $recipes = isEmptyProperty($data, 'recipes'); if (!is_array($recipes)) { $recipes = []; } $addDiagnoses = isEmptyProperty($data, 'addDiagnoses'); $data->callType = (int) $data->callType; $this->dcpSettings = readExtraSettings(null, 'medRecipesLinkRecipes'); $signSettings = readExtraSettings($db, 'mapCommonESignSettings'); $ret = new stdClass(); $ntSignActive = isEmptyProperty($signSettings, 'ntSignActive') && isEmptyProperty($this->dcpSettings, 'active') && selectSqlValue($db, 'select Type from signUser where UserCode = ?', [getSessionValue('mapUserCode')]) == 3; $ntSignDocuments = isEmptyProperty($signSettings, 'documents', []); if ($ntSignActive) { $ret->ntSign = (object)[ 'url' => $signSettings->ntSignUrl, 'documents' => [] ]; } $db->beginTransaction(); try { if ($visitId === 0) { $sql = <<<"EOT"
insert into MedVisit (VisitDate, MedDoctorCode, MedProfessionCode, DepartmentCode)
values ({$db->dialect("now()", "clock_timestamp()::timestamp")}, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $doctor['MedDoctorCode'], $doctor['MedProfessionCode'], $doctor['DepartmentCode'] ]); $visitId = $db->isSybase ? (int) $db->lastInsertId() : (int) $db->lastInsertId("seq_MedVisit"); } if ($visitId === 0) { throw new Exception("Не удалось создать запись посещения (MedVisit)!", self::ERROR_NO_VISIT); } $visitPatientId = $this->syncVisit( $visitId, (int) $data->patientId, $data->callType, $visitPatientId, isEmptyProperty($data, 'diagnosis'), isEmptyProperty($data, 'pressure')); if (is_array($addDiagnoses)) { foreach ($addDiagnoses as $d) { $this->syncVisit( $visitId, (int) $data->patientId, $data->callType, (int) isEmptyProperty($d, 'visitPatientId'), $d, null, 1); } } $userCode = getSessionValue('mapUserCode'); $sql = <<<"EOT"
select
    coalesce(vd.MedVisitDocumentCode, FN_webCreateMedVisitDocument($visitPatientId, $userCode, 1)) "id"
from SysNumber sn
left outer join MedVisitDocument vd on vd.MedVisitPatientCode = $visitPatientId
    and vd.Type = 1
left outer join HtmTemplate ht on ht.HtmTemplateCode = vd.HtmTemplateCode
    and ht.Type = 1
    and ht.WebForm = 1
where SysNumberCode = 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $visitDocumentId = (int) fetchValue($stmt, 'id'); if ($visitDocumentId === 0) { throw new Exception("Не удалось создать запись дневника посещения (MedVisitDocument)!", self::ERROR_NO_VISITDOCUMENT); } $sql = <<<"EOT"
select FN_htmPageWriteToFile(p.htmPageCode, compress(
      {$db->postgre("E")}'<Page>' || xmlelement(name Html, p.HTML){$db->postgre("::text")}/*ifpsql::text*//*endpsql*/ || {$db->postgre("E")}'<Units></Units></Page>'))
from MedVisitDocument vd
join htmPage p on p.htmPageCode = vd.htmPageCode
where vd.MedVisitDocumentCode = $visitDocumentId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if (is_array($elements)) { $sql = $db->isSybase ? "
begin
    declare varCode integer;" : "DO \$anon$
DECLARE
    varCode integer;
BEGIN"; foreach ($elements as $e) { $id = isEmptyProperty($e, 'id', 0); $sql .= $db->isSybase ? <<<"EOT"
    set varCode = (
        select first MedVisitWebDataCode
        from MedVisitWebData
        where MedVisitPatientCode = $visitPatientId
            and MedVisitWebDataCode = $id);
    if varCode is null then
        insert into MedVisitWebData (MedVisitPatientCode, Name, Value)
        values ($visitPatientId, '{$e->name}', '{$e->value}');
    else
        update MedVisitWebData set Value = '{$e->value}'
        where MedVisitWebDataCode = $id;
    end if;
EOT
: <<<"EOT"
    varCode := (select MedVisitWebDataCode
                from MedVisitWebData
                where MedVisitPatientCode = $visitPatientId
                  and MedVisitWebDataCode = $id
                LIMIT 1);
    if varCode is null then
        insert into MedVisitWebData (medvisitpatientcode, name, value)
            values ($visitPatientId,  E'{$e->name}' ,  E'{$e->value}');
    else
        UPDATE MedVisitWebData set
            value =  E'{$e->value}'
        where MedVisitWebDataCode = $id;
    end if;
EOT;
} $sql .= $db->isSybase ? "
end" : "END \$anon$;"; } $stmt = $db->prepare($sql); $stmt->execute(); $sqlInCondition = createSqlInCondition(isUndefinedProperty($data, 'studies'), 'id'); $sql = <<<"EOT"
delete from MedLogStudy
where MedPatientCode = ?
    and date(DirTime) = {$db->dialect("date(now())", "date((clock_timestamp()::timestamp))")}
    and MedDoctorCode = ?
    and MedLogStudyCode not $sqlInCondition
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ (int) $data->patientId, $doctor['MedDoctorCode'] ]); if (isUndefinedProperty($data, 'studies') && is_array($data->studies)) { foreach ($data->studies as $s) { $solisTests = isEmptyProperty($s, 'solisTests'); $solisTestsObj = null; if (is_array($solisTests) && count($solisTests) > 0) { $solisTestsObj = new stdClass(); foreach ($solisTests as $test) { $solisTestsObj->{$test->unitName} = new stdClass(); foreach ($test as $key => $value) { if ($key != "unitName") { $solisTestsObj->{$test->unitName}->{$key} = $value; } } } $solisTestsObj = json_encode($solisTestsObj, JSON_UNESCAPED_UNICODE); } if (!isEmptyProperty($s, 'id')) { $sql = <<<"EOT"
insert into MedLogStudy (
    MedPatientCode,
    DirTime,
    MedDoctorCode,
    DepartmentCode,
    MedStudyCode,
    htmPageCode,
    State,
    Type,
    DirNumber,
    NumberHistorySickness,
    PatientFIO,
    PatientDateOfBirth,
    PatientAddress,
    PatientSex,
    Department,
    MedDiagnosisCode,
    PIN,
    RawResult,
    SyncErrorMessage,
    StatusText
)
select
    mp.MedPatientCode,
    date(now()),
    md.MedDoctorCode,
    md.DepartmentCode,
    ? curMedStudyCode,
    ? curHtmPageCode,
    1,
    ? curType,
    {$db->dialect("(select first trim(IncStr(coalesce((select first repeat(' ', 20 - length(trim(DirNumber))) || trim(DirNumber)
                                                       from MedLogStudy
                                                       where MedDoctorCode = md.MedDoctorCode
                                                             and MedStudyCode = curMedStudyCode
                                                             and year(DirTime) = year(now())
                                                       order by 1 desc), '0'))))", "(select asa_trim(IncStr(coalesce((select repeat(E' ', 20 - length(asa_trim(DirNumber))) || asa_trim(DirNumber)
                                                     from MedLogStudy
                                                     where MedDoctorCode = md.MedDoctorCode
                                                       and MedStudyCode = ?
                                                       and year(DirTime) = year((clock_timestamp()::timestamp))
                                                     order by 1 desc NULLS LAST
                                                     limit 1), E'0')))
                    limit 1)")},
    mp.NumberHistorySickness,
    fn_cmFIO(mp.ManCode) PatientFIO,
    p.DateOfBirth PatientDateOfBirth,
    FN_cmCalcAddress(mp.ManCode) PatientAddress,
    p.Sex PatientSex,
    {$db->dialect("if trim(coalesce(d.FullName,'')) = '' then trim(coalesce(d.Name,'')) else trim(coalesce(d.FullName,'')) endif", "case
                     when asa_trim(coalesce(d.FullName, E'')) = E'' then asa_trim(coalesce(d.Name, E''))
                     else asa_trim(coalesce(d.FullName, E''))
                   end")} Department,
    nullif(?, 0) MedDiagnosisCode,
    ? as PIN,
    ? as RawResult,
    ? as SyncErrorMessage,
    ? as StatusText
from MedPatient mp
left outer join ManPassport p on p.ManCode = mp.ManCode,
MedDoctor md
left outer join Department d on d.DepartmentCode = md.DepartmentCode
where mp.MedPatientCode = ?
    and md.MedDoctorCode = ?
EOT;
$stmt = $db->prepare($sql); if ($db->isSybase) { $stmt->execute([ (int) $s->studyId, null, (int) $s->studyType, $diagnosisId, $s->pin, $solisTestsObj, $solisTestsObj ? self::SOLIS_MESSAGE : null, $solisTestsObj ? '502' : null, (int) $data->patientId, $doctor['MedDoctorCode'] ]); } else { $stmt->execute([ (int) $s->studyId, null, (int) $s->studyType, (int) $s->studyId, $diagnosisId, $s->pin, $solisTestsObj, $solisTestsObj ? self::SOLIS_MESSAGE : null, $solisTestsObj ? '502' : null, (int) $data->patientId, $doctor['MedDoctorCode'] ]); } } else { if ($solisTestsObj) { executeSql($db, "update MedLogStudy set ". "   RawResult = ?, ". "   SyncErrorMessage = ?, ". "   StatusText = ? ". "where MedLogStudyCode = ? ". "   and coalesce(BiopsySyncStatus, 0) <> 3", [ $solisTestsObj, self::SOLIS_MESSAGE, $solisTestsObj ? '502' : null, $s->id ] ); } } } } if ($hospital) { if ($hospital->open) { $series = isEmptyProperty($hospital->open, 'series'); $ht = $series ? 0 : 1; $number = isEmptyProperty($hospital->open, 'number'); if (!$number) { throw new Exception("Для открытия больничного необходимо указать номер!", self::ERROR_NO_HOSPITALNUMBER); } $sql = <<<"EOT"
insert into MedHospital (EnterpriseCode, HospitalType, MedPatientCode, MedDoctorOpenCode,
    StartDate, GiveDate, StartHospitalDate, MedDiagnosisPrimaryCode)
values (?, ?, ?, ?, {$db->dialect("now()", "(clock_timestamp()::timestamp)")},
                    {$db->dialect("now()", "(clock_timestamp()::timestamp)")},
                    {$db->dialect("now()", "(clock_timestamp()::timestamp)")}, nullif(?, 0))
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterpriseId, $ht, (int) $data->patientId, $doctor['MedDoctorCode'], $diagnosisId ]); $hospitalId = $db->isSybase ? (int) $db->lastInsertId() : $db->lastInsertId("seq_MedHospital"); if (!$hospitalId) { throw new Exception("Открытие больничного. Не удалось создать запись!", self::ERROR_NO_HOSPITAL); } $sql = <<<"EOT"
insert into MedHospitalNext (MedHospitalCode, DocumentSeries, DocumentNumber, Position)
values (?, {$db->dialect("trim(?)", "asa_trim(?)")}, {$db->dialect("trim(?)", "asa_trim(?)")}, 0)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $hospitalId, $series, $number ]); } elseif ($hospital->extend) { $hospitalId = isEmptyProperty($hospital->extend, 'id'); if (!$hospitalId) { throw new Exception("Продление больничного. Запись больничного не существует!", self::ERROR_NO_HOSPITAL); } $sql = <<<"EOT"
insert into MedHospitalExtension (MedHospitalCode, ToDate, MedDoctorCode)
values (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $hospitalId, isEmptyProperty($hospital->extend, 'toDate'), $doctor['MedDoctorCode'] ]); } } if (is_array($recipes) ) { $r0 = []; $r1 = []; $r2 = []; foreach ($recipes as $r) { $id = (int) isEmptyProperty($r, 'id'); $r->id = $id; if ($r->type == 0) { $r0[] = $r; } elseif ($r->type == 1) { $r1[] = $r; } elseif ($r->type == 2) { $r2[] = $r; } } $docs = $this->syncRecipe((int) $data->patientId, 0, $r0, $ntSignActive && array_search('medrecipestandart', $ntSignDocuments) !== false); if ($ntSignActive) { array_add($ret->ntSign->documents, $docs); } $docs = $this->syncRecipe((int) $data->patientId, 1, $r1, $ntSignActive && array_search('medrecipe', $ntSignDocuments) !== false); if ($ntSignActive) { array_add($ret->ntSign->documents, $docs); } $docs = $this->syncRecipe((int) $data->patientId, 2, $r2, $ntSignActive && array_search('medregistryspecial', $ntSignDocuments) !== false); if ($ntSignActive) { array_add($ret->ntSign->documents, $docs); } } $db->commit(); } catch (Throwable $ex) { $db->rollback(); throw $ex; } if ($ntSignActive && empty($ret->ntSign->documents)) { unset($ret->ntSign); } return $ret; } public function saveCoupon($patientId, $coupon) { $patientId = (int) $patientId; if (!isset($coupon) || $patientId === 0) { return; } $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $newCouponId = (int) isEmptyProperty($coupon, 'id'); $oldCouponId = (int) isEmptyProperty($coupon, 'oldId'); $cancel = $newCouponId !== $oldCouponId && $oldCouponId !== 0 ? true : false; if ($cancel && $oldCouponId) { $row = $this->getCoupon($oldCouponId); if ($row['state'] != 1) { switch ((int)$row['state']) { case 0: throw new Exception('Талон не является заказанным!', self::ERROR_COUPON_NOT_ORDERED); case 2: throw new Exception('Талон уже выдан!', self::ERROR_COUPON_ALREADY_ISSUED); case 3: throw new Exception('Талон отменен!', self::ERROR_COUPON_CANCELED); default: throw new Exception('Неизвестный статус талона!', self::ERROR_COUPON_UNKNOWN_STATUS); } } $sql = <<<"EOT"
update MedCoupon set
	OrderTime = null,
	SelfRecording = null,
	CouponState = 0,
	MedPatientCode = null
where MedCouponCode = $oldCouponId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } if ($newCouponId) { $row = $this->getCoupon($newCouponId); if ($row['state'] != 0 && !$cancel) { switch ((int)$row['state']) { case 1: throw new Exception('Талон уже заказан!', self::ERROR_COUPON_ALREADY_ORDERED); case 2: throw new Exception('Талон уже выдан!', self::ERROR_COUPON_ALREADY_ISSUED); case 3: throw new Exception('Талон отменен!', self::ERROR_COUPON_CANCELED); default: throw new Exception('Неизвестный статус талона!', self::ERROR_COUPON_UNKNOWN_STATUS); } } $sql = <<<"EOT"
update MedCoupon set
	OrderTime = {$db->dialect("now(*)", "(clock_timestamp()::timestamp)")},
	SelfRecording = 0,
	CouponState = 1,
	MedPatientCode = $patientId
where MedCouponCode = $newCouponId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } $db->commit(); } catch (Throwable $ex) { $db->rollback(); throw $ex; } return true; } function readSettings() { $res = new \stdClass(); $appConfig = getSessionValue('appConfig'); if (empty($appConfig['mapBin'])) { $res->mapBin = null; $res->mapExecutablePath = null; } else { $res->mapBin = $appConfig['mapBin']; $connect = empty($appConfig['connect']) ? '' : " -db \"{$appConfig['connect']}\""; $res->mapExecutablePath = realpath($appConfig['mapBin'])."\\map.exe{$connect}"; } $res->mobileMedCallAdd = empty($appConfig['mobileMedCallAdd']) ? null : $appConfig['mobileMedCallAdd']; $res->mobileMedCallAssign = empty($appConfig['mobileMedCallAssign']) ? null : $appConfig['mobileMedCallAssign']; return $res; } private function syncVisit($visitId, $patientId, $callType, $visitPatientId, $diagnosis, $pressure, $isAdd = 0) { $db = $this->db ?: $this->db = connectDb(); $diagnosisId = (int) isEmptyProperty($diagnosis, 'id'); $diagnosisRegister = $isAdd == 0 ? (bool) isEmptyProperty($diagnosis, 'register') : true; switch ($callType) { case 1: case 2: $visitPlace = 1; break; case 3: $visitPlace = 2; break; default: $visitPlace = 3; } if ($callType === 2) { $visitCaseNew = 2; } else { $visitCaseNew = 1; } $visitCase = null; $sicknessKind = null; $visitEndNew = null; if ($diagnosisId !== 0 && $diagnosisRegister) { $sql = <<<"EOT"
select {$db->sybase("first")}
    vp.MedVisitPatientCode "id",
    v.VisitDate "visitDate",
    {$db->dialect("if v.VisitDate < ymd(datepart(year, now()), 1, 1) then 1 else 0 endif", "case
                     when v.VisitDate < ymd(((EXTRACT(year from (clock_timestamp()::timestamp)))::integer), 1, 1) then 1
                     else 0
                   end ")} "firstInYear"
from MedVisitPatient vp
join MedVisit v on v.MedVisitCode = vp.MedVisitCode
where vp.MedPatientCode = ?
    and vp.MedDiagnosisCode = ?
    and vp.VisitCase = 1
    and vp.VisitEndNew is not null
    and vp.VisitEndNew <> 2
    and vp.MedVisitPatientCode <> ?
order by v.VisitDate desc {$db->postgre("NULLS LAST")}
{$db->postgre("limit 1")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId, $diagnosisId, $visitPatientId ]); $row = $stmt->fetch(); switch ((int) $diagnosis->type) { case 1: if (!$row || !$row['id']) { $visitCase = 1; $sicknessKind = 2; } elseif ((bool) $row['firstInYear']) { $visitCase = 1; $sicknessKind = 4; } else { $visitCase = 2; $sicknessKind = 4; } break; case 2: $visitCase = 1; $sicknessKind = 1; break; } $visitEndNew = 1; } if ($visitPatientId === 0) { $sql = <<<"EOT"
insert into MedVisitPatient (MedVisitCode, MedPatientCode, MedDiagnosisCode, VisitPlace,
    VisitMarkNew, ServiceForm, VisitCaseNew, VisitCase, SicknessKind, VisitEndNew, Pressure, AttendantDiagnosis)
values (?, ?, nullif(?, 0), ?, 1, 1, ?, ?, ?, ?, nullif(?, {$db->postgre("E")}''), ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $visitId, $patientId, $diagnosisId, $visitPlace, $visitCaseNew, $visitCase, $sicknessKind, $visitEndNew, $isAdd == 0 ? $pressure : null, $isAdd == 0 ? 0 : 1 ]); $visitPatientId = $db->isSybase ? (int) $db->lastInsertId() : (int) $db->lastInsertId("seq_MedVisitPatient"); } else { $sql = <<<"EOT"
update MedVisitPatient set
    MedVisitCode = ?,
    MedPatientCode = ?,
    MedDiagnosisCode = nullif(?, 0),
    VisitPlace = ?,
    VisitMarkNew = 1,
    ServiceForm = 1,
    VisitCaseNew = ?,
    VisitCase = ?,
    SicknessKind = ?,
    VisitEndNew = ?,
    Pressure = nullif(?, {$db->postgre("E")}''),
    AttendantDiagnosis = ?
where MedVisitPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $visitId, $patientId, $diagnosisId, $visitPlace, $visitCaseNew, $visitCase, $sicknessKind, $visitEndNew, $isAdd == 0 ? $pressure : null, $isAdd == 0 ? 0 : 1, $visitPatientId ]); } if ($visitPatientId === 0) { throw new Exception("Не удалось создать запись посещения пациента (MedVisitPatient)!", self::ERROR_NO_VISITPATIENT); } if ($isAdd == 0) { return $visitPatientId; } else { return null; } } public function generatePin($studies) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($studies)) { $studies = [$studies]; } $sql = ''; foreach ($studies as $s) { if (!isUndefinedProperty($s, "id") && !isUndefinedProperty($s, "pin")) { $sql = ($sql ? $sql."\nunion all\n" : "")."select {$s->studyId} as f1, fn_medGetPIN() as f2"; } } if ($sql) { $rows = selectSql($db, $sql); foreach ($rows as $r) { foreach ($studies as &$s) { if ($s->studyId == $r['f1']) { $s->pin = $r['f2']; } } } } return $studies; } } $service = new Junior\Server(new homevisit_patientDiaryVisit()); $service->process();