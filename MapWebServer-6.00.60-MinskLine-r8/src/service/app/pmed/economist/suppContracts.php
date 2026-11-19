<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_economist_suppcontracts { private $db; public function readSuppContracts ($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpiseCode = (int) getSessionValue('mapEnterpriseCode'); $search = isEmptyProperty($params, 'search'); $searchSql = empty($search) ? '' : " and (c.documentNumber like '%{$search}%' or e.name like '%{$search}%')"; $onDate = dbDate(isEmptyProperty($params, 'onDate')); $noDate = isEmptyProperty($params, 'noDate', false); $dateSql = (empty($onDate) || $noDate) ? '' : " and '{$onDate}' between c.startDate and c.finishDate"; $patientId = isEmptyProperty($params, 'patientId'); $patientSql = empty($patientId) ? '' : " and exists(select 1 from pmsSuppContractPatient where pmsSuppContractCode = c.pmsSuppContractCode and medPatientCode = {$patientId})"; $sql = <<<"EOT"
select
  c.pmsSuppContractCode     "id",
  c.enterpriseCode          "enterpriseId",
  c.enterpriseCodeSupply    "enterpriseSupplyId",
  e.name                    "enterpriseSupplyName",
  c.documentNumber          "documentNumber",
  c.documentDate            "documentDate",
  c.startDate               "startDate",
  c.finishDate              "finishDate",
  c.summd                   "summD",
  c.summk                   "summK"
from pmsSuppContract c
left join enterprise e on e.enterpriseCode = COALESCE(c.enterpriseCodeSupply,0)
where c.enterpriseCode = :enterpriseCode
$searchSql
$dateSql
$patientSql
EOT;
return selectSql($db, $sql, [ ':enterpriseCode' => $enterpiseCode ]); } public function createSuppContracts ($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); return insertSql($db, 'pmsSuppContract', $records, "EnterpriseCode, EnterpriseCodeSupply, DocumentNumber, DocumentDate, StartDate, FinishDate", "enterpriseSupplyId, documentNumber, documentDate, startDate, finishDate", "$enterpriseCode, ?, ?, ?, ?, ?" ); } public function updateSuppContracts ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsSuppContract', 'pmsSuppContractCode', $records, "EnterpriseCodeSupply, DocumentNumber, DocumentDate, StartDate, FinishDate", "enterpriseSupplyId, documentNumber, documentDate, startDate, finishDate" ); } public function deleteSuppContracts ($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DO \$anon$
begin
    delete from pmsSuppContractIncome where pmsSuppContractCode $in;
    delete from pmsSuppContract where pmsSuppContractCode $in;
end \$anon$;
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readSuppContractPatients ($params = null) { $db = $this->db ?: $this->db = connectDb(); $suppContractId = isEmptyProperty($params, 'suppContractId'); $sql = <<<"EOT"
select
    cp.pmsSuppContractPatientCode "id",
    cp.pmsSuppContractCode        "suppContractId",
    cp.medPatientCode             "patientId",
    FN_cmFIO(mp.ManCode)          "patientName",
    ph.phoneNumber                "patientPhone"
from pmsSuppContractPatient cp
join medPatient mp on mp.medPatientCode = cp.medPatientCode
left join lateral ( select manPhoneCode, phoneNumber, note
                    from ManPhone
                    where ManCode = mp.ManCode
                     and PhoneType = 3
                    order by ManPhoneCode DESC LIMIT 1 ) ph on true
where cp.pmsSuppContractCode = :suppContractCode
EOT;
return selectSql($db, $sql, [ ':suppContractCode' => $suppContractId ]); } public function createSuppContractPatients ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'pmsSuppContractPatient', $records, "pmsSuppContractCode, medPatientCode", "suppContractId, patientId" ); } public function updateSuppContractPatients ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsSuppContractPatient', 'pmsSuppContractPatientCode', $records, "pmsSuppContractCode, medPatientCode", "suppContractId, patientId" ); } public function deleteSuppContractPatients ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsSuppContractPatient', 'pmsSuppContractPatientCode', $records); } public function readSuppContractServices ($params = null) { $db = $this->db ?: $this->db = connectDb(); $suppContractId = isEmptyProperty($params, 'suppContractId'); $sql = <<<"EOT"
select
  cs.pmsSuppContractServiceCode "id",
  cs.pmsSuppContractCode        "suppContractId",
  coalesce(cs.pmsPriceListKindCode, plk.pmsPriceListKindCode) "priceListKindId",
  coalesce(lk.name, plk.name)   "priceListKindName",
  cs.pmsPriceListCode           "priceListId",
  pl.fullCode                   "priceListFullCode",
  pl.name                       "priceListName",
  case cs.pmsPriceListKindCode is not null
    when true then 1
    when false then 2
  end                           "rowLevel"
from pmsSuppContractService cs
left join pmsPriceListKind lk on lk.pmsPriceListKindCode = coalesce(cs.pmsPriceListKindCode, 0)
left join pmsPriceList pl on pl.pmsPriceListCode = coalesce(cs.pmsPriceListCode, 0)
left join pmsPriceListKind plk on plk.pmsPriceListKindCode = pl.pmsPriceListKindCode
where cs.pmsSuppContractCode = :suppContractCode
order by coalesce(lk.name, plk.name), cs.pmsPriceListKindCode DESC, pl.fullCode, pl.Name
EOT;
return selectSql($db, $sql, [ ':suppContractCode' => $suppContractId ]); } public function createSuppContractServices ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<< "EOT"
insert into PmsSuppContractService
    (pmsSuppContractCode, pmsPriceListKindCode, pmsPriceListCode)
values
    (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'suppContractId'), !isEmptyProperty($row, 'priceListId') ? isEmptyProperty($row, 'priceListKindId') : null, isEmptyProperty($row, 'priceListId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_PmsSuppContractService'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateSuppContractServices ($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update PmsSuppContractService set
       pmsSuppContractCode = case when ? = 1 then ? else pmsSuppContractCode end,
       pmsPriceListKindCode = case when ? = 1 then ? else pmsPriceListKindCode end,
       pmsPriceListCode = case when ? = 1 then ? else pmsPriceListCode end
where PmsSuppContractService = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'suppContractId'), isEmptyProperty($row, 'suppContractId'), propertyExistsSQL($row, 'priceListKindId'), !propertyExistsSQL($row, 'priceListId') ? isEmptyProperty($row, 'priceListKindId') : null, propertyExistsSQL($row, 'priceListId'), isEmptyProperty($row, 'priceListId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteSuppContractServices ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsSuppContractService', 'pmsSuppContractServiceCode', $records); } public function readSuppContractIncome ($params = null) { $db = $this->db ?: $this->db = connectDb(); $suppContractId = isEmptyProperty($params, 'suppContractId'); $sql = <<<"EOT"
select
  pmsSuppContractIncomeCode "id",
  pmsSuppContractCode "suppContractId",
  incomeDate "incomeDate",
  summ "summ"
from pmsSuppContractIncome
where pmsSuppContractCode = :suppContractCode
EOT;
return selectSql($db, $sql, [ ':suppContractCode' => $suppContractId ]); } public function createSuppContractIncome ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'pmsSuppContractIncome', $records, "pmsSuppContractCode, incomeDate, summ", "suppContractId, incomeDate, summ" ); } public function updateSuppContractIncome ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsSuppContractIncome', 'pmsSuppContractIncomeCode', $records, "pmsSuppContractCode, incomeDate, summ", "suppContractId, incomeDate, summ" ); } public function deleteSuppContractIncome ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsSuppContractIncome', 'pmsSuppContractIncomeCode', $records); } } handleJsonRpc('pmed_economist_suppcontracts');