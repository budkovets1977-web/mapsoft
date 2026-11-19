<?php
require_once(__DIR__.'/../../init.php'); class map_common_departments { private $db; public function readBranches($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseId = isset($params->enterpriseId) && isEmptyProperty($params, 'enterpriseId') ? $params->enterpriseId : getSessionValue('mapEnterpriseCode'); $taskDate = Date("Ymd"); $sql = <<<"EOT"
select
       B.BranchCode "id",
       B.Name "name",
       B.Cipher "cipher"
from Branch B
where B.EnterpriseCode = $enterpriseId
  and (B.BranchCode in (select BranchCode
                        from Department
                        where (0 = 0)) or
       not EXISTS(select 1
                  from Department
                  where BranchCode = B.BranchCode
                  limit 1))
  and COALESCE(CloseDate, 30000101) > $taskDate
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateBranches($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update Branch set
       Name = case when ? = 1 then ? else Name end,
       FullName = case when ? = 1 then ? else FullName end
where BranchCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteBranches($records) { $db = $this->db ? : $this->db = connectDb(); $in = createSqlInCondition($records); $closeDate = Date('Ymd'); $sql = <<<"EOT"
update Branch set
       CloseDate = $closeDate
where BranchCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createBranches($records) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sqlCipher = <<<"EOT"
select INCSTR((select MAX(RIGHT(REPEAT(' ', 10) + asa_trim(Cipher), 10))
               from Branch
               where EnterpriseCode = ?)) "cipher"
EOT;
$sqlBranches = <<<"EOT"
insert into Branch (EnterpriseCode, Name, FullName, Cipher)
values ($enterpriseCode, ?, ?, ?)
EOT;
$stmtCipher = $db->prepare($sqlCipher); $stmtBranches = $db->prepare($sqlBranches); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmtCipher->execute([ isUndefinedProperty($row, 'enterpriseId') ]); $cipher = fetchValue($stmtCipher, 'cipher'); $stmtBranches->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'name'), $cipher ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_Branch'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readDepartments($params = null) { $db = $this->db ? : $this->db = connectDb(); $branchWhere = isset($params->branchId) && isUndefinedProperty($params, 'branchId') ? "and D.BranchCode = " . $params->branchId : ""; $userCode = isset($params->userId) && isUndefinedProperty($params, 'userId') ? $params->userId : getSessionValue('mapUserCode'); $taskDate = Date("Ymd"); $sql = <<<"EOT"
select
      D.DepartmentCode "id",
      D.Name "name",
      D.Cipher "cipher",
      D.BranchCode "branchId"
from Department D
     left join UsersDepartment UD on UD.DepartmentCode = D.DepartmentCode
                                 and UD.UserCode = $userCode
     left join medHelpKind HK on HK.medHelpKindCode = D.medHelpKindCode
     left join Enterprise E on E.EnterpriseCode = D.EnterpriseCodePU
where (0 = 0 OR
       0 = 1 OR
       UD.UsersDepartmentCode IS NOT NULL)
  and COALESCE(CloseDate, 30000101) > $taskDate
  $branchWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateDepartments($records) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
update Department set
       Name = case when ? = 1 then ? else Name end,
       FullName = case when ? = 1 then ? else FullName end
where DepartmentCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDepartments($records) { $db = $this->db ? : $this->db = connectDb(); $in = createSqlInCondition($records); $closeDate = Date('Ymd'); $sql = <<<"EOT"
update Department set
       CloseDate = $closeDate
where DepartmentCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createDepartments($records) { $db = $this->db ? : $this->db = connectDb(); $sqlCipher = <<<"EOT"
select INCSTR((select MAX(RIGHT(REPEAT(' ', 10) + asa_trim(Cipher), 10))
               from Department
               where BranchCode = ?)) "cipher"
EOT;
$sqlBranches = <<<"EOT"
insert into Department (BranchCode, Name, FullName, Cipher)
values (?, ?, ?, ?)
EOT;
$stmtCipher = $db->prepare($sqlCipher); $stmtBranches = $db->prepare($sqlBranches); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmtCipher->execute([ isUndefinedProperty($row, 'branchId') ]); $cipher = fetchValue($stmtCipher, 'cipher'); $stmtBranches->execute([ isUndefinedProperty($row, 'branchId'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'name'), $cipher ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_Department'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateExtraSettings($data) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
            UPDATE Branch
            SET CodeHL7 = ?
            WHERE BranchCode = ?
        EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $config = new common_config(); $config->updateExtraSettings($data); foreach ($data->data as $record) { $stmt->execute([ $record->organizationIdentifier, $record->branchId ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('map_common_departments');