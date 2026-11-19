<?php
 require_once(__DIR__.'/../../init.php'); class map_med_recipes { private $db; public function readMethodsUse($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select distinct
    MethodUse "name"
from (
    select
        0 "type",
        rs.MatWealthCode,
        rs.MethodUse,
        rs.OutDate,
        rs.EnterpriseCode
    from MedRecipeStandart rs
    where coalesce(rs.State, 0) <> 2
    union
    select
        1 "type",
        rw.MatWealthCode,
        rw.MethodUse,
        r.RecipeDate OutDate,
        r.EnterpriseCode
    from MedRecipe r
    join MedRecipeWealth rw on rw.MedRecipeWealthCode = (
        select {$db->sybase("first")} MedRecipeWealthCode
        from MedRecipeWealth
        where MedRecipeCode = r.MedRecipeCode
        {$db->postgre("limit 1")})
    where r.RecipeType = 0
        and coalesce(r.State, 0) <> 2
    union
    select
        2 "type",
        rs.MatWealthCode,
        rs.MethodUse,
        rs.OutDate,
        rs.EnterpriseCode
    from MedRegistrySpecial rs
    where coalesce(rs.State, 0) <> 2) recipes
where MatWealthCode = ?
    and OutDate between {$db->dialect("dateadd(month , -6, now()) and now()", "dateadd('month', - 6, (clock_timestamp()::timestamp)) and (clock_timestamp()::timestamp)")}
    and EnterpriseCode = ?
order by 1 desc {$db->postgre("NULLS LAST")}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ (int) isEmptyProperty($params, 'drugId', 0), getSessionValue('mapEnterpriseCode') ]); return $stmt->fetchAll(); } public function findRecipeNumber($params) { $db = $this->db ? : $this->db = connectDb(); $recipeNumber = isEmptyProperty($params, 'recipeNumber', ''); $enterprise = getSessionValue('mapEnterpriseCode'); $doctor = getSessionValue('mapMedDoctor'); $sql = <<<"EOT"
select distinct
    MedRecipeCode "id",
    DocumentSeries "value1",
    DocumentNumber "value2",
    {$db->dialect( "coalesce(if (TermYear is not null) then cast(TermYear as varchar) ||' г.' endif || ". "   if (TermMonth is not null) then cast(TermMonth as varchar) ||' мес.' endif || ". "   if (TermDay is not null) then cast(TermDay as varchar) ||' дн.' endif , ' ') \"value3\"", "coalesce(case (TermYear is not null) when true then cast(TermYear as varchar) || ' г.' end || ". "   case (TermMonth is not null) when true then cast(TermMonth as varchar) || ' мес.' end || ". "   case (TermDay is not null) when true then cast(TermDay as varchar) || ' дн.' end ,  ' ' ) as \"value3\"" )}
from MedRecipe
where EnterpriseCode = ?
  and MedDoctorCode = ?
  and coalesce(RecipeType,0) = 0
  and coalesce(State, 0) <> 2
  and RecipeDate is null
  and PayDate is null
  and coalesce(DocumentNumber,'') like '%$recipeNumber'
order by DocumentSeries, DocumentNumber
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $enterprise, $doctor['MedDoctorCode'] ]); return $stmt->fetchAll(); } public function readOneRecipeDrug ($drugId, $amount) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
    MatWealthCode "drugId",
    FN_cmMatWealthNameEx(MatWealthCode, 14) "drugName",
    FN_cmMatWealthNameEx(MatWealthCode, 14, 1) "drugNameInn",
    FN_cmMatWealthNameEx(MatWealthCode, 6) "wealthName",
    FN_cmMatWealthNameEx(MatWealthCode, 6, 1) "wealthNameInn",
    {$db->dialect( "if ? = 0 then '' else '№' + trim(str(? * if coalesce(DoseNumber, 0) <> 0 then DoseNumber else 1 endif)) + ' ( ' + FN_cmSumProp(? * if coalesce(DoseNumber, 0) <> 0 then DoseNumber else 1 endif, 0, 0, 1 ) + ' )' endif as \"amountText\"", "case ? = 0 when true then '' when false then  '№' + trim(str(? * case coalesce(DoseNumber, 0) <> 0 when true then DoseNumber when false then 1 end)) +  ' ( ' + FN_cmSumProp(? * case coalesce(DoseNumber, 0) <> 0 when true then DoseNumber when false then 1 end, 0, 0, 1 ) + ' )' end as \"amountText\"" )}
from MatWealth
where MatWealthCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ (float) $amount, (float) $amount, (float) $amount, (int) $drugId ]); return $stmt->fetchAll()[0]; } } $service = new Junior\Server(new map_med_recipes()); $service->process(); 