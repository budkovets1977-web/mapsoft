<?php
 require_once(__DIR__.'/../init.php'); class homevisit_patientRecipes { private $db; public function readPatientRecipes($params) { $db = $this->db ? : $this->db = connectDb(); $patientId = (int) $params->patientId; $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = $db->isSybase ? <<<"EOT"
select top 100 * from (
    select * from (
        // обычные рецепты
        select top 100
            0 "typeId",
            MRS.MedRecipeStandartCode "recipeId",
            MRS.Outdate "outDate",
            IFNULL(MRS.DocumentSeries, '', MRS.DocumentSeries || ' - ') || MRS.documentNumber "recipeText",
            MD.MedDoctorCode "doctorId",
            IFNULL(MD.manCode, '', FN_cmFIO(MD.manCode, 1) || ', ') || MP.Name "doctorText",
            MW.MatWealthCode "drugId",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14) "drugName",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) "drugNameInn",
            if LENGTH(MRS.MethodUse) = 0 then '' else 'Ds: ' || MRS.MethodUse endif "methodText",
            MRS.Amount "amount",
            MRS.DocumentSeries "series",
            MRS.UseINN "useInn",
            MRS.MethodUse "methodUse",
            if coalesce(MW.DoseNumber, 0) <> 0 then MW.DoseNumber else 1 endif as "doseNumber",
            if MRS.Amount = 0 then '' else '№' + trim(str(MRS.Amount * "doseNumber")) + ' ( ' + FN_cmSumProp(MRS.Amount * "doseNumber", 0, 0, 1 ) + ' )' endif as "amountText",
            MRS.StartDate,
            cast(null as integer) "privelegeId",
            cast(null as integer) "privelegeGroupId",
            cast(null as numeric) "privelegePercent"
        from MedRecipeStandart MRS
        inner join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
        inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
        inner join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
        inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
        where MRS.MedPatientCode = $patientId
          and (MRS.OutDate is not null and (MRS.outDate <= now()))
          and MRS.EnterpriseCode = $enterpriseId
          and COALESCE(MRS.State, 0) <> 2
        order by MRS.OutDate desc) _MedRecipeStandart
union all
    select * from (
        // льготные рецепты
        select top 100
            1 "typeId",
            MR.MedRecipeCode "recipeId",
            MR.RecipeDate "outDate",
            IFNULL(MR.DocumentSeries, '', MR.DocumentSeries || ' - ') || MR.documentNumber "recipeText",
            MD.MedDoctorCode "doctorId",
            IFNULL(MD.manCode, '', FN_cmFIO(MD.manCode, 1) || ', ') || MP.Name "doctorText",
            MW.MatWealthCode "drugId",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14) "drugName",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) "drugNameInn",
            if LENGTH(MRW.MethodUse) = 0 then '' else 'Ds: ' || MRW.MethodUse endif "methodText",
            MRW.Amount "amount",
            MR.DocumentSeries "series",
            MR.UseINN "useInn",
            MRW.MethodUse "methodUse",
            if coalesce(MW.DoseNumber, 0) <> 0 then MW.DoseNumber else 1 endif as "doseNumber",
            if MRW.Amount = 0 then '' else '№' + trim(str(MRW.Amount * "doseNumber")) + ' ( ' + FN_cmSumProp(MRW.Amount * "doseNumber", 0, 0, 1 ) + ' )' endif as "amountText",
            MR.StartDate,
            MR.MedPrivelegeCode "privelegeId",
            MR.MedPrivelegeGroupCode "privelegeGroupId",
            MR.PrivelegePercent "privelegePercent"
        from MedRecipe MR
        inner join MedDoctor MD on MD.MedDoctorCode = MR.medDoctorCode
        inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
        inner join MedRecipeWealth MRW on MRW.MedRecipeWealthCode =
                   // взято из с формы Журнал льготных рецептов
                   (select first MedRecipeWealthCode
                    from MedRecipeWealth
                    where MedRecipeCode = MR.MedRecipeCode)
        inner join MatWealth MW on MW.MatWealthCode = MRW.matWealthCode
        inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
        where MR.MedPatientCode = $patientId
          and (MR.RecipeDate is not null and (MR.RecipeDate <= now()))
          and MR.RecipeType = 0
          and MR.EnterpriseCode = $enterpriseId
          and COALESCE(MR.State, 0) <> 2
        order by MR.RecipeDate desc) _MedRecipe
union all
    select * from (
        //  медикаменты особого контроля
        select top 100
            2 "typeId",
            MRS.MedRegistrySpecialCode "recipeId",
            MRS.Outdate "outDate",
            MRS.DocumentNumber "recipeText",
            MD.MedDoctorCode "doctorId",
            IFNULL(MD.manCode, '', FN_cmFIO(MD.manCode, 1) || ', ') || MP.Name "doctorText",
            MW.MatWealthCode "drugId",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14) "drugName",
            FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) "drugNameInn",
            if LENGTH(MRS.MethodUse) = 0 then '' else 'Ds: ' || MRS.MethodUse endif "methodText",
            MRS.Amount "amount",
            trim(if trim(MRS.DocumentNumber) = '' then '' else ExtractWord(1, MRS.DocumentNumber, '-') endif) "series",
            MRS.UseINN "useInn",
            MRS.MethodUse "methodUse",
            if coalesce(MW.DoseNumber, 0) <> 0 then MW.DoseNumber else 1 endif as "doseNumber",
            if MRS.Amount = 0 then '' else '№' + trim(str(MRS.Amount * "doseNumber")) + ' ( ' + FN_cmSumProp(MRS.Amount * "doseNumber", 0, 0, 1 ) + ' )' endif as "amountText",
            null as StartDate,
            cast(null as integer) "privelegeId",
            cast(null as integer) "privelegeGroupId",
            cast(null as numeric) "privelegePercent"
        from MedRegistrySpecial MRS
        inner join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
        inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
        inner join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
        inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
        where MRS.MedPatientCode = $patientId
          and (MRS.OutDate is not null and (MRS.outDate <= now()))
          and MRS.EnterpriseCode = $enterpriseId
          and COALESCE(MRS.State, 0) <> 2
        order by MRS.OutDate desc) _MedRegistrySpecial
    ) _Recipe
order by _Recipe.outDate desc
EOT
: <<<"EOT"
select
  *
from (select /* обычные рецепты */
         *
       from (select
                0 as "typeId",
                MRS.MedRecipeStandartCode as "recipeId",
                MRS.Outdate as "outDate",
                (case
                   when MRS.DocumentSeries is null then E''
                   else MRS.DocumentSeries || E' - '
                 end) || MRS.documentNumber as "recipeText",
                MD.MedDoctorCode as "doctorId",
                (case
                   when MD.manCode is null then E''
                   else FN_cmFIO(MD.manCode, 1) || E', '
                 end) || MP.Name as "doctorText",
                MW.MatWealthCode as "drugId",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14) as "drugName",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) as "drugNameInn",
                case
                  when LENGTH(MRS.MethodUse) = 0 then E''
                  else E'Ds: ' || MRS.MethodUse
                end as "methodText",
                MRS.Amount as "amount",
                MRS.DocumentSeries as "series",
                MRS.UseINN as "useInn",
                MRS.MethodUse as "methodUse",
                case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end as "doseNumber",
                case MRS.Amount = 0 when true then '' when false then  '№' + trim(str(MRS.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end)) +  ' ( ' + FN_cmSumProp(MRS.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end, 0, 0, 1 ) + ' )' end as "amountText",
                MRS.StartDate "startDate"
              from MedRecipeStandart MRS
                   inner join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
                   inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                   inner join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
                   inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
              where MRS.MedPatientCode = $patientId
                    and (MRS.OutDate is not null
                    and (MRS.outDate <= ((clock_timestamp()::timestamp))))
                    and MRS.EnterpriseCode = $enterpriseId
                    and COALESCE(MRS.State, 0) <> (2)
              order by MRS.OutDate desc NULLS LAST
              limit 100) _MedRecipeStandart
       union all
       select
         *
       from (select /* лиготные рецепты */
                1 as "typeId",
                MR.MedRecipeCode as "recipeId",
                MR.RecipeDate as "outDate",
                (case
                   when MR.DocumentSeries is null then E''
                   else MR.DocumentSeries || E' - '
                 end) || MR.documentNumber as "recipeText",
                MD.MedDoctorCode as "doctorId",
                (case
                   when MD.manCode is null then E''
                   else FN_cmFIO(MD.manCode, 1) || E', '
                 end) || MP.Name as "doctorText",
                MW.MatWealthCode as "drugId",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14) as "drugName",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) as "drugNameInn",
                case
                  when LENGTH(MRW.MethodUse) = 0 then E''
                  else E'Ds: ' || MRW.MethodUse
                end as "methodText",
                MRW.Amount as "amount",
                MR.DocumentSeries as "series",
                MR.UseINN as "useInn",
                MRW.MethodUse as "methodUse",
                case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end as "doseNumber",
                case MRW.Amount = 0 when true then '' when false then  '№' + trim(str(MRW.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end)) +  ' ( ' + FN_cmSumProp(MRW.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end, 0, 0, 1 ) + ' )' end as "amountText",
                MR.StartDate "startDate"
              from MedRecipe MR
                   inner join MedDoctor MD on MD.MedDoctorCode = MR.medDoctorCode
                   inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                   inner join MedRecipeWealth MRW on MRW.MedRecipeWealthCode =
                   (select
                       MedRecipeWealthCode
                     from MedRecipeWealth
                     where MedRecipeCode = MR.MedRecipeCode
                     limit 1)
                   inner join MatWealth MW on MW.MatWealthCode = MRW.matWealthCode
                   inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
              where MR.MedPatientCode = $patientId
                    and (MR.RecipeDate is not null
                    and (MR.RecipeDate <= ((clock_timestamp()::timestamp))))
                    and MR.RecipeType = 0
                    and MR.EnterpriseCode = $enterpriseId
                    and COALESCE(MR.State, 0) <> (2)
              order by MR.RecipeDate desc NULLS LAST
              limit 100) _MedRecipe
       union all
       select
         *
       from (select /* медикаменты особого контроля */
                2 as "typeId",
                MRS.MedRegistrySpecialCode as "recipeId",
                MRS.Outdate as "outDate",
                MRS.DocumentNumber as "recipeText",
                MD.MedDoctorCode as "doctorId",
                (case
                   when MD.manCode is null then E''
                   else FN_cmFIO(MD.manCode, 1) || E', '
                 end) || MP.Name as "doctorText",
                MW.MatWealthCode as "drugId",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14) as "drugName",
                FN_cmMatWealthNameEx(MW.matWealthCode, 14, 1) as "drugNameInn",
                case
                  when LENGTH(MRS.MethodUse) = 0 then E''
                  else E'Ds: ' || MRS.MethodUse
                end as "methodText",
                MRS.Amount as "amount",
                asa_trim (case
                            when asa_trim(MRS.DocumentNumber) = E'' then E''
                            else ExtractWord(1, MRS.DocumentNumber, E'-')
                          end) as "series",
                MRS.UseINN as "useInn",
                MRS.MethodUse as "methodUse",
                case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end as "doseNumber",
                case MRS.Amount = 0 when true then '' when false then  '№' + trim(str(MRS.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end)) +  ' ( ' + FN_cmSumProp(MRS.Amount * case coalesce(MW.DoseNumber, 0) <> 0 when true then MW.DoseNumber when false then 1 end, 0, 0, 1 ) + ' )' end as "amountText",
                null "startDate"
              from MedRegistrySpecial MRS
                   inner join MedDoctor MD on MD.MedDoctorCode = MRS.medDoctorCode
                   inner join MedProfession MP on MP.MedProfessionCode = MD.MedProfessionCode
                   inner join MatWealth MW on MW.MatWealthCode = MRS.matWealthCode
                   inner join MatWealthObject MWO on MWO.matWealthObjectCode = MW.MatWealthObjectCode
              where MRS.MedPatientCode = $patientId
                    and (MRS.OutDate is not null
                    and (MRS.outDate <= ((clock_timestamp()::timestamp))))
                    and MRS.EnterpriseCode = $enterpriseId
                    and COALESCE(MRS.State, 0) <> (2)
              order by MRS.OutDate desc NULLS LAST
              limit 100) _MedRegistrySpecial
     ) _Recipe
order by 3 desc NULLS LAST
limit 100
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } $service = new Junior\Server(new homevisit_patientRecipes()); $service->process();