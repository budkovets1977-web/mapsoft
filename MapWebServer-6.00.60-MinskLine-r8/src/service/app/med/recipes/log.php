<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/sign.php'); resumeJsonRpc(); class med_recipes_log { private $db; public function readLog($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $currId = isEmptyProperty($params, 'currId'); if (!isset($currId)) { $startDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))); $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime($params->finishDate)) : '30000101'; $doctor = isEmptyProperty($params, 'doctorId'); $patient = isEmptyProperty($params, 'patientId'); $supplyId = isEmptyProperty($params, 'supplyId'); $privilege = isEmptyProperty($params, 'privilegeId'); $privilegePercent = isEmptyProperty($params, 'privilegePercent'); $recipeNumber = isEmptyProperty($params, 'recipeNumber'); $state = isEmptyProperty($params, 'state', 0); $limit = isset($params->limit) ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; } $sort = isEmptyProperty($params, 'sort'); $orderBy = 'order by r.DocumentNumber'; if ($sort) { $sort = safe_json_decode($sort); $sort = $sort[0]; $property = $sort->property; $direction = $sort->direction; $orderBy = "order by \"$property\" $direction"; } $sqlFromWhere = <<<SQL
            FROM MedRecipe AS R
            LEFT JOIN MedPatient AS P ON P.MedPatientCode = R.MedPatientCode
            LEFT JOIN MedDoctor AS DOC ON DOC.medDoctorCode = R.MedDoctorCode
            LEFT JOIN MedRecipeWealth AS RW ON RW.MedRecipeWealthCode = (
                SELECT MedRecipeWealthCode
                FROM MedRecipeWealth
                WHERE MedRecipeCode = R.MedRecipeCode
                LIMIT 1
            )
            LEFT JOIN MatWealth AS W ON W.MatWealthCode = RW.MatWealthCode
            LEFT JOIN MatWealthObject AS WO ON WO.MatWealthObjectCode = W.MatWealthObjectCode
            LEFT JOIN MatWealthManufacturer MWM on MWM.MatWealthManufacturerCode = RW.MatWealthManufacturerCode
            WHERE R.EnterpriseCode = $enterpriseId
                AND R.RecipeType = 0
                AND COALESCE(R.State, 0) <> 2
            SQL;
if (isset($currId)) { $sqlFromWhere .= " and r.MedRecipeCode = $currId"; } else { $sqlFromWhere .= isset($doctor) ? " and r.MedDoctorCode = $doctor" : ""; $sqlFromWhere .= isset($patient) ? " and r.MedPatientCode = $patient" : ""; $sqlFromWhere .= isset($supplyId) ? " and r.EnterpriseSupplyCode = $supplyId" : ""; $sqlFromWhere .= isset($privilege) ? " and r.medPrivelegeCode = $privilege" : ""; $sqlFromWhere .= isset($privilegePercent) ? " and COALESCE(r.PrivilegePercent, 0) = $privilegePercent" : ""; if (isset($recipeNumber)) { $pos = safe_strpos($recipeNumber, ' '); if ($pos) { $str1 = substr($recipeNumber, 0, $pos); $str2 = substr($recipeNumber, $pos + 1); $sqlFromWhere .= " and (r.DocumentNumber like '%$str1%')"; $sqlFromWhere .= " and (r.DocumentNumber like '%$str2%')"; } else { $sqlFromWhere .= " and r.DocumentNumber like '%$recipeNumber'"; } } switch ($state) { case 0: $sqlFromWhere .= " and ( ( (r.PayDate is null) and (r.RecipeDate between $startDate and $finishDate))" . " or ( (r.PayDate is not null) and (r.PayDate between $startDate and $finishDate))" . " or ((r.PayDate is null) and (r.RecipeDate is null)) )"; break; case 1: $sqlFromWhere .= " and (r.RecipeDate is not null)
                                       and (r.PayDate between $startDate and $finishDate)
                                       and (
                                            CASE COALESCE(r.UseINN, 0)
                                                WHEN 0 THEN (SELECT Name0 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                                ELSE (SELECT Name0_1 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                            END
                                            IS NOT NULL
                                       )
                                       and (fullSumm <> 0)
                                       and (r.MedPatientCode IS NOT NULL)
                                       and (r.MedDoctorCode IS NOT NULL)
                                    "; break; case 2: $sqlFromWhere .= " and (r.PayDate is null)
                                       and (r.RecipeDate between $startDate and $finishDate)
                                       and (r.MedPatientCode IS NOT NULL)
                                       and (
                                            (SELECT Name FROM Enterprise WHERE EnterpriseCode = r.EnterpriseSupplyCode)
                                            IS NULL
                                       )
                                    "; break; case 3: $sqlFromWhere .= " and (
                                            CASE COALESCE(r.UseINN, 0)
                                                WHEN 0 THEN (SELECT Name0 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                                ELSE (SELECT Name0_1 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                            END
                                            IS NULL
                                       )
                                       and (r.MedPatientCode IS NULL)
                                    "; break; case 4: $sqlFromWhere .= " and (r.RecipeDate is null)
                                       and (r.PayDate between $startDate and $finishDate)
                                       and (
                                            CASE COALESCE(r.UseINN, 0)
                                                WHEN 0 THEN (SELECT Name0 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                                ELSE (SELECT Name0_1 FROM MatWealthNames WHERE MatWealthCode = rw.MatWealthCode)
                                            END
                                            IS NOT NULL
                                       )
                                            and (
                                            (SELECT Name FROM Enterprise WHERE EnterpriseCode = r.EnterpriseSupplyCode)
                                            IS NOT NULL
                                       )
                                       and (fullSumm <> 0)
                                       and (r.MedPatientCode IS NULL or r.MedDoctorCode IS NULL)
                                    "; break; case 5: $sqlFromWhere .= " and (r.ImportDate is not null) and (r.ImportDate between $startDate and $finishDate)"; break; default: $sqlFromWhere .= ""; break; } $sql = <<<SQL
                SELECT COUNT(*) "total"
                $sqlFromWhere
                SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; } $sqlLimit = isset($currId) ? "" : "LIMIT $limit OFFSET $offset"; $sql = <<<SQL
            SELECT *,
                CASE "useINN" = 0
                    WHEN true THEN (SELECT Name0 FROM MatWealthNames WHERE MatWealthCode = "wealthId")
                    WHEN false THEN (SELECT Name0_1 FROM MatWealthNames WHERE MatWealthCode = "wealthId")
                END                             "wealthName",
                (SELECT Name FROM Enterprise WHERE EnterpriseCode = "enterpriseSupplyId")
                                                "enterpriseSupplyName",
                (SELECT Name FROM MedPrivilegeGroup WHERE MedPrivilegeGroupCode = "privilegeGroupId")
                                                "privilegeGroupName",
                (SELECT Name::TEXT FROM MedPrivelege WHERE medPrivelegeCode = "privilegeId")
                                                "privilegeName",
                (
                    SELECT MR_.MedRecipeCode
                    FROM MedRecipe MR_
                    WHERE MR_.EnterpriseCode = 1
                        AND MR_.recipeType = 1
                        AND MR_.DocumentSeries = "documentSeries"
                        AND MR_.DocumentNumber= "documentNumber"
                        AND COALESCE(MR_.MedPatientCode, 0) = COALESCE("patientId", 0)
                        AND COALESCE(MR_.PayDate, 19000101) = COALESCE("payDate", 19000101)
                        AND COALESCE(MR_.RecipeDate, 19000101) = COALESCE("recipeDate",19000101)
                    LIMIT 1)                    "recipeIdOld",
                    FN_cmFIO("manPatientId")    "patientName"
            FROM (
                SELECT DISTINCT
                    r.MedRecipeCode                 "id",
                    r.MedRecipeCode                 "recipeId",
                    r.RecipeType                    "recipeType",
                    0                               "type",             -- Необходимо для работы механизма цифровой подписи
                    r.EnterpriseCode                "enterpriseId",
                    r.RecipeDate                    "recipeDate",
                    r.DocumentSeries                "documentSeries",
                    r.DocumentNumber                "documentNumber",
                    r.MedPatientCode                "patientId",
                    P.ManCode                       "manPatientId",
                    r.MedDoctorCode                 "doctorId",
                    doc.ManCode                     "manDoctorId",
                    MWM.manufacturer                "manufacturer",
                    RW.MatWealthManufacturerCode    "recipeManufacturerCode",
                    doc.DepartmentCode              "departmentId",
                    r.PrivilegePercent              "privilegePercent",
                    r.PayDate                       "payDate",
                    r.EnterpriseSupplyCode          "enterpriseSupplyId",
                    r.MedPrivilegeGroupCode         "privilegeGroupId",
                    r.medPrivelegeCode              "privilegeId",
                    rw.MedRecipeWealthCode          "recipeWealthId",
                    rw.MatWealthCode                "wealthId",
                    w.MatWealthObjectCode           "wealthObjectId",
                    wo.Name                         "wealthObjectName",
                    wo.FullName                     "wealthFullName",
                    wo.CodeIMN                      "codeIMN",
                    w.LinkCipher                    "linkCipher",
                    rw.Amount                       "amount",
                    rw.MethodUse                    "methodUse",
                    COALESCE(rw.Summ, 0)            "summ",
                    COALESCE(rw.FullSumm, 0)        "fullSumm",
                    COALESCE(rw.FullSumm, 0) - COALESCE(rw.Summ, 0)
                                                    "privilegeSumm",
                    r.TermDay                       "termDay",
                    r.TermMonth                     "termMonth",
                    r.TermYear                      "termYear",
                    CASE
                        WHEN COALESCE(R.TermMonth, 0) = 2 THEN 0
                        WHEN COALESCE(R.TermDay, 0) = 30 THEN 1
                        WHEN COALESCE(R.TermDay, 0) = 5 THEN 2
                        WHEN COALESCE(R.TermMonth, 0) = 6 THEN 3
                        WHEN COALESCE(R.TermDay, 0) = 15 THEN 4
                    END                             "termId",
                    R.StartDate                     "startDate",
                    r.MedVisitPatientCode           "visitPatientId",
                    r.ImportDate                    "importDate",
                    COALESCE(r.UseINN, 0)           "useINN",
                    r.FlagConfirm                   "flagConfirm",
                    r.State                         "state",
                    r.purchasePeriodDay             "purchasePeriodDay",
                    FN_cmFIO(doc.ManCode)           "doctorName",
                    R.CodeHL7                       "codeHL7",
                    R.SignatureType                 "signatureType",
                    R.centralizedrelease            "centralizedRelease"
                $sqlFromWhere
                $orderBy
                $sqlLimit
            ) t
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); if (isset($currId)) { return $stmt->fetch(); } else { $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } } public function updateLog($records) { $db = $this->db ? : $this->db = connectDb(); $param = new stdClass(); $sqlRecipe = <<<"EOT"
update MedRecipe set
       RecipeType = case when ? = 1 then ? else RecipeType end,
       EnterpriseCode = case when ? = 1 then ? else EnterpriseCode end,
       DocumentSeries = case when ? = 1 then ? else DocumentSeries end,
       DocumentNumber = case when ? = 1 then ? else DocumentNumber end,
       RecipeDate = case when ? = 1 then ? else RecipeDate end,
       MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
       MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
       PrivilegePercent = case when ? = 1 then ? else PrivilegePercent end,
       PayDate = case when ? = 1 then ? else PayDate end,
       EnterpriseSupplyCode = case when ? = 1 then ? else EnterpriseSupplyCode end,
       MedPrivilegeGroupCode = case when ? = 1 then ? else MedPrivilegeGroupCode end,
       MedPrivelegeCode = case when ? = 1 then ? else MedPrivelegeCode end,
       TermDay = case when ? = 1 then ? else TermDay end,
       TermMonth = case when ? = 1 then ? else TermMonth end,
       TermYear = case when ? = 1 then ? else TermYear end,
       StartDate = case when ? = 1 then ? else StartDate end,
       MedVisitPatientCode = case when ? = 1 then ? else MedVisitPatientCode end,
       ImportDate = case when ? = 1 then ? else ImportDate end,
       UseINN = case when ? = 1 then nullif(?,0) else UseINN end,
       FlagConfirm = case when ? = 1 then ? else FlagConfirm end,
       purchasePeriodDay = case when ? = 1 then ? else purchasePeriodDay end,
        CentralizedRelease = case when ? = 1 then ? else CentralizedRelease end,
       State = case when ? = 1 then ? else State end
where MedRecipeCode = ?
EOT;
$sqlDelReg = <<<"EOT"
delete from MedRecipe
where MedRecipeCode = ?
EOT;
$sqlUpdRecipeReg = <<<"EOT"
with currRecipe as (
select EnterpriseCode, DocumentSeries, DocumentNumber, RecipeDate, MedPatientCode,
    MedDoctorCode, PrivilegePercent, PayDate, EnterpriseSupplyCode, MedPrivilegeGroupCode, medPrivelegeCode,
        TermDay, TermMonth, TermYear, StartDate, MedVisitPatientCode, ImportDate, UseINN, FlagConfirm, State
from MedRecipe
where MedRecipeCode = ?)
update MedRecipe set
       EnterpriseCode = currRecipe.EnterpriseCode,
       DocumentSeries = currRecipe.DocumentSeries,
       DocumentNumber = currRecipe.DocumentNumber,
       RecipeDate = currRecipe.RecipeDate,
       MedPatientCode = currRecipe.MedPatientCode,
       MedDoctorCode = currRecipe.MedDoctorCode,
       PrivilegePercent = currRecipe.PrivilegePercent,
       PayDate = currRecipe.PayDate,
       EnterpriseSupplyCode = currRecipe.EnterpriseSupplyCode,
       MedPrivilegeGroupCode = currRecipe.MedPrivilegeGroupCode,
       medPrivelegeCode = currRecipe.medPrivelegeCode,
       TermDay = currRecipe.TermDay,
       TermMonth = currRecipe.TermMonth,
       TermYear = currRecipe.TermYear,
       StartDate = currRecipe.StartDate,
       MedVisitPatientCode = currRecipe.MedVisitPatientCode,
       ImportDate = currRecipe.ImportDate,
       UseINN = currRecipe.UseINN,
       FlagConfirm = currRecipe.FlagConfirm,
       State = currRecipe.State
from currRecipe
where MedRecipeCode = ?
EOT;
$sqlUpdRecipeWealthReg = <<<"EOT"
with currRecipeWealth as (
select MRW.MatWealthCode, MRW.Amount, MRW.Methoduse, MRW.Summ, MRW.FullSumm
from MedRecipe MR
     join MedRecipeWealth MRW on MRW.MedRecipeCode = MR.MedRecipeCode
where MR.MedRecipeCode = ?
limit 1), cloneRecipeWealth as (
select MRW.MedRecipeWealthCode
from MedRecipe MR
     join MedRecipeWealth MRW on MRW.MedRecipeCode = MR.MedRecipeCode
where MR.MedRecipeCode = ?
limit 1)
update MedRecipeWealth set
       MatWealthCode = currRecipeWealth.MatWealthCode,
       Amount = currRecipeWealth.Amount,
       Methoduse = currRecipeWealth.Methoduse,
       Summ = currRecipeWealth.Summ,
       FullSumm = currRecipeWealth.FullSumm
from currRecipeWealth
where MedRecipeWealthCode = (select MedRecipeWealthCode from cloneRecipeWealth limit 1)
EOT;
$stmtRecipe = $db->prepare($sqlRecipe); $stmtDelReg = $db->prepare($sqlDelReg); $stmtUpdRecipeReg = $db->prepare($sqlUpdRecipeReg); $stmtUpdRecipeWealthReg = $db->prepare($sqlUpdRecipeWealthReg); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { $ret = []; foreach ($records as $row) { if ($this->isDocumentExists($row)) { throw new Exception('Рецепт с указанными серией и номером уже существует!'); } if (!$this->isValidPrivilege($row)) { throw new Exception('Дата выписки не соответствует датам действия льготы!'); } if (isUndefinedProperty($row, 'recipeWealthId')) { $this->updRecipeWealth([ propertyExistsSQL($row, 'wealthId'), isUndefinedProperty($row, 'wealthId'), propertyExistsSQL($row, 'amount'), isUndefinedProperty($row, 'amount'), propertyExistsSQL($row, 'methodUse'), isUndefinedProperty($row, 'methodUse'), propertyExistsSQL($row, 'summ'), isUndefinedProperty($row, 'summ'), propertyExistsSQL($row, 'fullSumm'), isUndefinedProperty($row, 'fullSumm'), propertyExistsSQL($row, 'manufacturerCode'), isEmptyProperty($row, 'manufacturerCode'), isUndefinedProperty($row, 'recipeWealthId') ]); } else { $this->insRecipeWealth([ $row->id, isUndefinedProperty($row, 'wealthId'), isUndefinedProperty($row, 'amount'), isUndefinedProperty($row, 'methodUse'), isUndefinedProperty($row, 'summ'), isUndefinedProperty($row, 'fullSumm'), isUndefinedProperty($row, 'manufacturerCode') ]); $row->recipeWealthId = $db->lastInsertId('seq_MedRecipeWealth'); } $stmtRecipe->execute([ propertyExistsSQL($row, 'recipeType'), isUndefinedProperty($row, 'recipeType'), propertyExistsSQL($row, 'enterpriseId'), isUndefinedProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'documentSeries'), isUndefinedProperty($row, 'documentSeries'), propertyExistsSQL($row, 'documentNumber'), isUndefinedProperty($row, 'documentNumber'), propertyExistsSQL($row, 'recipeDate'), isUndefinedProperty($row, 'recipeDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'privilegePercent'), isUndefinedProperty($row, 'privilegePercent'), propertyExistsSQL($row, 'payDate'), isUndefinedProperty($row, 'payDate'), propertyExistsSQL($row, 'enterpriseSupplyId'), isUndefinedProperty($row, 'enterpriseSupplyId'), propertyExistsSQL($row, 'privilegeGroupId'), isUndefinedProperty($row, 'privilegeGroupId'), propertyExistsSQL($row, 'privilegeId'), isUndefinedProperty($row, 'privilegeId'), propertyExistsSQL($row, 'termDay'), isUndefinedProperty($row, 'termDay'), propertyExistsSQL($row, 'termMonth'), isUndefinedProperty($row, 'termMonth'), propertyExistsSQL($row, 'termYear'), isUndefinedProperty($row, 'termYear'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'visitPatientId'), isUndefinedProperty($row, 'visitPatientId'), propertyExistsSQL($row, 'importDate'), isUndefinedProperty($row, 'importDate'), propertyExistsSQL($row, 'useINN'), isUndefinedProperty($row, 'useINN'), propertyExistsSQL($row, 'flagConfirm'), isUndefinedProperty($row, 'flagConfirm'), propertyExistsSQL($row, 'purchasePeriodDay'), isUndefinedProperty($row, 'purchasePeriodDay') ?: null, propertyExistsSQL($row, 'centralizedRelease'), isUndefinedProperty($row, 'centralizedRelease'), propertyExistsSQL($row, 'state'), isUndefinedProperty($row, 'state'), $row->id ]); if (empty($row->recipeIdOld)) { $createRegistrationParams = new stdClass(); $createRegistrationParams->recipeId = $row->id; $createRegistrationParams->manufacturerCode = isUndefinedProperty($row, 'manufacturerCode'); $this->createRegistration($db, $createRegistrationParams); } else if (empty($row->payDate)) { $stmtDelReg->execute([ $row->recipeIdOld ]); } else { $stmtUpdRecipeReg->execute([ $row->id, $row->recipeIdOld ]); $stmtUpdRecipeWealthReg->execute([ $row->id, $row->recipeIdOld ]); } $param->currId = $row->id; $ret[] = $this->readLog($param); unset($param->currId); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function getWealthInfo($wealthId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    select
            MWO.CodeIMN as "codeIMN",
            MW.LinkCipher as "linkCipher"
    from MatWealth MW
        join MatWealthObject MWO on MWO.MatWealthObjectCode = MW.MatWealthObjectCode
    where MW.MatWealthCode = ?
    EOT;
$stmt = $db->prepare($sql); $stmt->execute([$wealthId]); $results = $stmt->fetchAll(); return $results; } public function deleteLog($records) { $db = $this->db ? : $this->db = connectDb(); $param = new stdClass(); if (is_object($records)) { $records = [$records]; } $sqlDel = <<<"EOT"
delete from MedRecipe t0_
using MedRecipe R0, MedRecipe R
where (t0_.medrecipecode = R.medrecipecode)
  and (R0.MedRecipeCode = ?
  and R0.EnterpriseCode = R.EnterpriseCode
  and R0.DocumentSeries = R.DocumentSeries
  and R0.DocumentNumber = R.DocumentNumber
  and COALESCE(R0.MedPatientCode, 0) = COALESCE(R.MedPatientCode, 0)
  and COALESCE(R0.PayDate, 19000101) = COALESCE(R.PayDate, 19000101)
  and COALESCE(R0.RecipeDate, 19000101) = COALESCE(R.RecipeDate, 19000101)
  and R.RecipeType in (0, 1, 2))
EOT;
$sqlUpdState = <<<SQL
            UPDATE MedRecipe SET
                State = 2
            WHERE MedRecipeCode = ?
            SQL;
$stmtDel = $db->prepare($sqlDel); $stmtUpd = $db->prepare($sqlUpdState); $db->beginTransaction(); $sign = new med_recipes_sign($db); try { foreach ($records as $row) { $param->patientId = $row->patientId; $param->outDate = $row->recipeDate; $isValid = $this->checkValidBarCode($param); if ($row->codeHL7 && $row->codeIMN !== '' && $row->linkCipher !== '' && !empty($row->wealthId) && !empty($isValid)) { $stmtUpd->execute([ $row->id ]); $sign->sendRecipe2DPC($row->id, 'medrecipe'); } else { $stmtDel->execute([ $row->id ]); } unset($param->patientId); unset($param->outDate); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createRegistration($outerDb, $record) { $db = $outerDb ? $outerDb : ($this->db ? : $this->db = connectDb()); $sqlRecipe = <<<"EOT"
insert into MedRecipe
    (RecipeType, EnterpriseCode, DocumentSeries, DocumentNumber, RecipeDate, MedPatientCode,
        MedDoctorCode, PrivilegePercent, PayDate, EnterpriseSupplyCode, MedPrivilegeGroupCode, medPrivelegeCode,
            TermDay, TermMonth, TermYear, StartDate, MedVisitPatientCode, ImportDate, UseINN, FlagConfirm, State, centralizedrelease)
select
    1, EnterpriseCode, DocumentSeries, DocumentNumber, RecipeDate, MedPatientCode,
        MedDoctorCode, PrivilegePercent, PayDate, EnterpriseSupplyCode, MedPrivilegeGroupCode, medPrivelegeCode,
            TermDay, TermMonth, TermYear, StartDate, MedVisitPatientCode, ImportDate, UseINN, FlagConfirm, State, centralizedrelease
from MedRecipe
where MedRecipeCode = ?
EOT;
$sqlWealthCopy = <<<"EOT"
insert into MedRecipeWealth
    (MedRecipeCode, MatWealthCode, Amount, Summ, FullSumm, MethodUse, matwealthmanufacturercode)
select ?, MatWealthCode, Amount, Summ, FullSumm, MethodUse, ?
from MedRecipeWealth
where MedRecipeCode = ?
EOT;
$stmtRecipe = $db->prepare($sqlRecipe); $stmtWealthCopy = $db->prepare($sqlWealthCopy); $stmtRecipe->execute([ isUndefinedProperty($record, 'recipeId'), ]); $stmtWealthCopy->execute([ $db->lastInsertId('seq_MedRecipe'), isUndefinedProperty($record, 'manufacturerCode'), isUndefinedProperty($record, 'recipeId'), ]); return true; } public function checkValidBarCode($params) { $db = $this->db ? : $this->db = connectDb(); $patient = isEmptyProperty($params, 'patientId'); $date = isEmptyProperty($params, 'outDate'); $dateOut = $date ? Date("Ymd", safe_strtotime($date)) : 'null'; if (empty($patient) || empty($date)) { return false; } $sql = <<<"EOT"
select 1 "field"
from MedPatient MP
     join MAN AS M on M.ManCode = MP.ManCode
     join MedBarCode MBC on MBC.ManCode = M.ManCode
                        and $dateOut between MBC.StartDate and COALESCE(MBC.StopDate, 30000101) - INTERVAL '1 day'
where MP.MedPatientCode = $patient
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetch() ? true : []; } public function createLog($records) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $param = new stdClass(); $sqlRecipe = <<<"EOT"
insert into MedRecipe (RecipeType, EnterpriseCode, DocumentSeries, DocumentNumber, RecipeDate, MedPatientCode,
    MedDoctorCode, PrivilegePercent, PayDate, EnterpriseSupplyCode, MedPrivilegeGroupCode, medPrivelegeCode,
        TermDay, TermMonth, TermYear, StartDate, MedVisitPatientCode, ImportDate, UseINN, FlagConfirm, State, CentralizedRelease)
values (0, $enterpriseCode, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), 2, ?, ?)
EOT;
$stmtRecipe = $db->prepare($sqlRecipe); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if ($this->isDocumentExists($row)) { throw new Exception('Рецепт с указанными серией и номером уже существует!'); } if (!$this->isValidPrivilege($row)) { throw new Exception('Дата выписки не соответствует датам действия льготы!'); } $stmtRecipe->execute([ isUndefinedProperty($row, 'documentSeries'), isUndefinedProperty($row, 'documentNumber'), isUndefinedProperty($row, 'recipeDate'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'privilegePercent'), isUndefinedProperty($row, 'payDate'), isUndefinedProperty($row, 'enterpriseSupplyId'), isUndefinedProperty($row, 'privilegeGroupId'), isUndefinedProperty($row, 'privilegeId'), isUndefinedProperty($row, 'termDay'), isUndefinedProperty($row, 'termMonth'), isUndefinedProperty($row, 'termYear'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'visitPatientId'), isUndefinedProperty($row, 'importDate'), isUndefinedProperty($row, 'useINN'), isUndefinedProperty($row, 'state'), isUndefinedProperty($row, 'centralizedRelease') ]); $param->currId = $db->lastInsertId('seq_MedRecipe'); $this->insRecipeWealth([ $param->currId, isUndefinedProperty($row, 'wealthId'), isUndefinedProperty($row, 'amount'), isUndefinedProperty($row, 'methodUse'), isUndefinedProperty($row, 'summ'), isUndefinedProperty($row, 'fullSumm'), isUndefinedProperty($row, 'manufacturerCode') ]); if (!empty($row->payDate)) { $createRegistrationParams = new stdClass(); $createRegistrationParams->recipeId = $param->currId; $createRegistrationParams->manufacturerCode = isUndefinedProperty($row, 'manufacturerCode'); $this->createRegistration($db, $createRegistrationParams); } $ret[] = $this->readLog($param); unset($param->currId); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } private function isDocumentExists($row) { $db = $this->db ? : $this->db = connectDb(); $sqlExists = <<<"EOT"
select
       1 "flag"
from MedRecipe
where EnterpriseCode = ?
  and RecipeType = 0
  and COALESCE(DocumentSeries, '') = COALESCE(?, '')
  and COALESCE(DocumentNumber, '') = COALESCE(?, '')
  and MedRecipeCode <> COALESCE(?, 0)
  and MedRecipeCode <> COALESCE(?, 0)
limit 1
EOT;
$stmtExists = $db->prepare($sqlExists); $stmtExists->execute([ getSessionValue('mapEnterpriseCode'), isUndefinedProperty($row, 'documentSeries'), isUndefinedProperty($row, 'documentNumber'), isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'recipeIdOld') ]); return fetchValue($stmtExists, 'flag') === 1; } private function isValidPrivilege($row) { $db = $this->db ? : $this->db = connectDb(); if (!isUndefinedProperty($row, 'privilegeId') || !isUndefinedProperty($row, 'patientId') || !isUndefinedProperty($row, 'recipeDate')) { return true; } $sqlValid = <<<"EOT"
select
       1 "flag"
from MedPatientCard
where medPrivelegeCode = ?
  and MedPatientCode = ?
  and ? between StartDate and FinishDate
limit 1
EOT;
$stmtValid = $db->prepare($sqlValid); $stmtValid->execute([isUndefinedProperty($row, 'privilegeId'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'recipeDate')]); return fetchValue($stmtValid, 'flag') === 1; } public function getLastDocumentSeries($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
    DocumentSeries "documentSeries"
from medrecipe where medrecipeCode =
    (select max(medrecipecode) from medrecipe where enterprisecode = $enterpriseCode)
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = new stdClass(); $ret->documentSeries = fetchValue($stmt, 'documentSeries'); return $ret; } public function checkFreeCodeBlanksAdd($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    documentNumber "documentNumber"
FROM
    MedRecipe
WHERE
    documentNumber >= '$params->firstCode'
    AND documentNumber <= '$params->secondCode'
    AND documentSeries = '$params->documentSeries'
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function doDocumentNumber($number) { $number = strval($number); while (safe_strlen($number) < 7) { $number = '0' . $number; } return $number; } public function insertBlanks($records) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
insert into MedRecipe (medDoctorCode, documentNumber, documentSeries, termDay, termMonth, enterpriseCode, recipeType)
values (?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'documentNumber'), isUndefinedProperty($row, 'documentSeries'), isUndefinedProperty($row, 'termDay'), isUndefinedProperty($row, 'termMonth'), $enterpriseCode, 0 ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedRecipe'); $this->insRecipeWealth([ $rec->id, null, null, null, null, null, null ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createBlanksAdd($records) { $records->documentNumber--; for ($i = 0; $i <= $records->count - 1; $i++) { $records->documentNumber = $this->doDocumentNumber($records->documentNumber + 1); $ret[$i] = $this->insertBlanks($records); } return $ret; } public function updateBlanksAdd($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update MedRecipe set
       medDoctorCode = case when ? = 1 then ? else medDoctorCode end
where medRecipeCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'newDoctorId'), isUndefinedProperty($row, 'newDoctorId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteBlanksAdd($records) { $db = $this->db ? : $this->db = connectDb(); return deleteSql($db, 'MedRecipe', 'medRecipeCode', $records); } public function getCodeBlanksAdd($params) { $db = $this->db ? : $this->db = connectDb(); $stopDocumentNumber = isset($params->stopDocumentNumber) ? ("AND DocumentNumber <= '" . $params->stopDocumentNumber . "'") : ""; $sql = <<<"EOT"
SELECT
    medRecipeCode "id"
FROM
    medRecipe
WHERE
    medDoctorCode = $params->doctorId
    AND recipeDate is null
    AND payDate is null
    AND enterpriseCode = 1
    AND documentNumber >= '$params->startDocumentNumber'
    $stopDocumentNumber
    AND documentSeries = '$params->documentSeries'
LIMIT
    $params->count
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ids = $stmt->fetchAll(); foreach ($ids as $val) { $params->id = $val['id']; $this->updateBlanksAdd($params); } $result['col'] = count($ids); return $result; } public function insRecipeWealth($sqlParams, $outerDb = null) { $db = $outerDb ? $outerDb : ($this->db ? : $this->db = connectDb()); $sql = <<<"EOT"
insert into MedRecipeWealth (MedRecipeCode, MatWealthCode, Amount, Methoduse, Summ, FullSumm, matwealthmanufacturercode)
values (?, ?, ?, ?, ?, ?, ?)
EOT;
return executeSql($db, $sql, $sqlParams); } public function updRecipeWealth($sqlParams, $outerDb = null) { $db = $outerDb ? $outerDb : ($this->db ? : $this->db = connectDb()); $sql = <<<"SQL"
update MedRecipeWealth set
       MatWealthCode = case when ? = 1 then ? else MatWealthCode end,
       Amount = case when ? = 1 then ? else Amount end,
       Methoduse = case when ? = 1 then ? else Methoduse end,
       Summ = case when ? = 1 then ? else Summ end,
       FullSumm = case when ? = 1 then ? else FullSumm end,
       MatWealthManufacturerCode = case when ? = 1 then ? else MatWealthManufacturerCode end
where MedRecipeWealthCode = ?
SQL;
return executeSql($db, $sql, $sqlParams); } } handleJsonRpc('med_recipes_log');