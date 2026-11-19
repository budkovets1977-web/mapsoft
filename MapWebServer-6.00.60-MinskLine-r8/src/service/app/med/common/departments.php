<?php
 require_once(__DIR__.'/../../init.php'); class map_med_departments { private $db; public function readDepartments($params) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
select
    D.DepartmentCode "id",
    D.BranchCode  "branchId",
    D.Name "name",
    D.FullName "fullName",
    cast((right(repeat({$db->postgre("E")}' ', 10) + {$db->dialect("TRIM", "asa_trim")}(D.Cipher), 10)) as varchar(10)) "cipher",
    D.medHelpKindCode "helpKindId",
    coalesce( HK.Name, {$db->postgre("E")}'') "helpKindName",
    D.EnterpriseStructureCode "enterpriseStructureId",
    FN_cmEnterpriseStructureName( D.EnterpriseStructureCode, 2 ) "enterpriseStructureName",
    D.LinkCipher "linkCipher",
    cast((select name from DepartmentCase where DepartmentCode = D.DepartmentCode and TypeCase = 1) as VarChar(254)) "caseName1",
    cast((select name from DepartmentCase where DepartmentCode = D.DepartmentCode and TypeCase = 2) as VarChar(254)) "caseName2",
    cast((select name from DepartmentCase where DepartmentCode = D.DepartmentCode and TypeCase = 3) as VarChar(254)) "caseName3",
    cast((select name from DepartmentCase where DepartmentCode = D.DepartmentCode and TypeCase = 4) as VarChar(254)) "caseName4",
    cast((select name from DepartmentCase where DepartmentCode = D.DepartmentCode and TypeCase = 5) as VarChar(254)) "caseName5",
    D.CloseDate "closeDate",
    D.EnterpriseCodePU "enterpriseIdPU",
    E.Name "enterpriseNamePU"
from Branch B
join Department D on D.BranchCode = B.BranchCode
left outer join medHelpKind HK on HK.medHelpKindCode = D.medHelpKindCode
left outer join Enterprise AS E ON E.EnterpriseCode = D.EnterpriseCodePU
where
    B.EnterpriseCode = ? and  /* ? - EnterpriseCode */
    coalesce(B.CloseDate,30000101) >= coalesce(?, {$db->dialect("today()", "current_date")}) and  /* ? - CurrDate */
    exists ( select MD.MedDoctorCode
             from MedDoctor MD
             where
             MD.DepartmentCode = D.DepartmentCode and
             coalesce(?, {$db->dialect("today()", "current_date")}) between coalesce(MD.StartDate,19000101) and coalesce(MD.FINISHDATE,30000101) )  /* ? - CurrDate */
order by 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapEnterpriseCode'), isEmptyProperty($params, 'currDate', date("Ymd")), isEmptyProperty($params, 'currDate', date("Ymd")) ]); return $stmt->fetchAll(); } } $service = new Junior\Server(new map_med_departments()); $service->process(); 