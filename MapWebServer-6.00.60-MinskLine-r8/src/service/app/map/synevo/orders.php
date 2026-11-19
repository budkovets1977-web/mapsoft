<?php
 require_once(__DIR__.'/../../init.php'); class map_synevo_orders { private $db; private function getSearchSqlWhere($search) { $search = (string) $search; $result = $cpin = $cphone = $cfio = $cfio2 = $cdoctorfio = $caddress = $ccontact = $cnum = $cabinet = ""; if (safe_strlen($search) == 0) { return $result; } $search = toLower($search); $hasNum = preg_match("/\d/", $search); $hasNotPhone = preg_match('/[^\s\d\+\-\(\)]/u', $search); $searchArray = preg_split("/[\s.,]+/u", $search); if ($hasNum) { if (safe_strlen($search) == 14) { $cpin = "coalesce(mp.PersonalNumber, '') ilike '$search'"; } if (!$hasNotPhone) { $cphone = preg_replace("/\\D/", "", $search); $cphone = "exists (select 1 from ManPhone where ManCode = p.ManCode and coalesce(PhoneType, 0) in (0,1,2,3) and regexp_replace(PhoneNumber, '\D', '', 'ig') ilike '%$cphone')"; } $caddress = "fn_cmCalcAddress(p.ManCode, 5) ilike '%" . implode("%", $searchArray) . "%'"; } else { $cfio = "m.SurName ilike '" . $searchArray[0] . "%'"; $cfio2 = "split_part(sls.PatientFio, ' ', 1) ilike '" . $searchArray[0] . "%'"; $cdoctorfio = "split_part(sls.DoctorFio, ' ', 1) ilike '" . $searchArray[0] . "%'"; if (isset($searchArray[1]) && $searchArray[1] !== "") { $cfio .= "and m.Name ilike '" . $searchArray[1] . "%'"; $cfio2 .= "and split_part(sls.PatientFio, ' ', 2) ilike '" . $searchArray[1] . "%'"; $cdoctorfio .= "and split_part(sls.DoctorFio, ' ', 2) ilike '" . $searchArray[1] . "%'"; } if (isset($searchArray[2]) && $searchArray[2] !== "") { $cfio .= "and m.SecondName ilike '" . trim(implode(" ", (array_slice($searchArray, 2)))) . "%'"; $cfio2 .= "and split_part(sls.PatientFio, split_part(sls.PatientFio, ' ', 2) || ' ', 2) ilike '" . trim(implode(" ", (array_slice($searchArray, 2)))) . "%'"; $cdoctorfio .= "and split_part(sls.DoctorFio, split_part(sls.DoctorFio, ' ', 2) || ' ', 2) ilike '" . trim(implode(" ", (array_slice($searchArray, 2)))) . "%'"; } $cfio = "($cfio)"; $cfio2 = "($cfio2)"; $cdoctorfio = "($cdoctorfio)"; } $ccontact = "exists (select 1 from ManPhone where ManCode = p.ManCode and coalesce(PhoneType, 0) in (0,1,2,3) and PhoneNumber ilike '$search')"; $cabinet = "exists (select 1 from MedCabinet where MedCabinetCode = sls.MedCabinetCode and Cabinet ilike '%$search%')"; $cdate = DateTime::createFromFormat('Y-m-d', $search); if (!$cdate) { $cdate = DateTime::createFromFormat('Ymd', $search); } if (!$cdate) { $cdate = DateTime::createFromFormat('d.m.Y', $search); } if (!$cdate) { $cdate = DateTime::createFromFormat('d.m.y', $search); } if (!$cdate) { $cdate = DateTime::createFromFormat('d/m/Y', $search); } if (!$cdate) { $cdate = DateTime::createFromFormat('d/m/y', $search); } $cnum = "sls.DocumentNumber ilike '%".$searchArray[0]."%'"; if (isset($searchArray[1]) && $searchArray[1] !== "") { $cnum .= "and sls.DocumentNumber ilike '%" . $searchArray[1] . "%'"; } if (isset($searchArray[2]) && $searchArray[2] !== "") { $cnum .= "and sls.DocumentNumber ilike '%" . trim(implode(" ", (array_slice($searchArray, 2)))) . "%'"; } $cnum = "($cnum)"; $result = glueStrings($cpin, $cphone, "\n     or "); $result = glueStrings($result, $cfio, "\n     or "); $result = glueStrings($result, $cfio2, "\n     or "); $result = glueStrings($result, $cdoctorfio, "\n     or "); $result = glueStrings($result, $cnum, "\n     or "); if ($cdate) { $result = glueStrings($result, "sls.DocumentDate = ".$cdate->format('Ymd'), "\n     or "); $result = glueStrings($result, "sls.DoneDate = ".$cdate->format('Ymd'), "\n     or "); $result = glueStrings($result, "mp.DateOfBirth = ".$cdate->format('Ymd'), "\n     or "); $result = glueStrings($result, "sls.VisitTime::date = ".$cdate->format('Ymd'), "\n     or "); } if (is_numeric($search) && (float) $search > 0) { $result = glueStrings($result, "sls.Summ = ".(float) $search, "\n     or "); $result = glueStrings($result, "sls.PaymentSum = ".(float) $search, "\n     or "); } if (isset($caddress)) { $result = glueStrings($result, $caddress, "\n     or "); } $result = glueStrings($result, $cabinet, "\n     or "); $result = "and (\n     " . glueStrings($result, $ccontact, "\n     or ") . "\n    )"; return $result; } public function getOrdersSql() { $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); return <<<"EOT"
select
    sls.synevoLogServiceCode as "id",
    sls.orderId as "orderId",
    sls.State as "state",
    sls.DocumentNumber as "documentNumber",
    sls.DocumentDate as "documentDate",
    sls.DoctorFio as "doctorFio",
    sls.MedPatientCode as "patientId",
    case sls.MedPatientCode is null
        when true then sls.PatientFio
        else fn_cmFio(p.ManCode)
    end as "patientFio",
    mp.DateOfBirth as "dateOfBirth",
    fn_cmGetMonthYearString(date_part('year', age(sls.DocumentDate, mp.DateOfBirth))) as "age",
    fn_cmCalcAddress(p.ManCode, 5) "address",
    (select string_agg((concat(
        case
            when mpn.PhoneType=1 then 'д.'
            when mpn.PhoneType=2 then 'р.'
            when mpn.PhoneType=3 then 'м.'
            when mpn.PhoneType=4 then 'п.'
            else null
        end, mpn.PhoneNumber)), ', ' )
    from ManPhone as mpn
    where mpn.ManCode = p.ManCode) as "contact",
    sls.PaymentSum as "paymentSum",
    sls.DoneDate as "doneDate",
    sls.Summ as "summ",
    /*sls.SummVat as "summVat"*/
    sls.Note as "note",
    dateformat(sls.VisitTime, 'YYYY-mm-dd hh:nn') as "visitTime",
    sls.MedCabinetCode as "cabinetId",
    coalesce(mc.Active, 0) as "cabinetActive"
from synevoLogService as sls
left join MedPatient as p on p.MedPatientCode = sls.MedPatientCode
left join Man as m on m.ManCode = p.ManCode
left join ManPassport as mp on mp.ManCode = p.ManCode
left join MedCabinet mc on mc.MedCabinetCode = sls.MedCabinetCode
where sls.EnterpriseCode = $enterpriseCode
EOT;
} public function readOrders ($params) { $db = $this->db ?: $this->db = connectDb(); $done = $search = ''; $offset = 0; $limit = 1000; $orders = isEmptyProperty($params, 'orders'); if (is_array($orders)) { $orders = ' and sls.synevoLogServiceCode '.createSqlInCondition($orders); } else { $orders = ''; $search = isEmptyProperty($params, 'search', ''); $done = isEmptyProperty($params, 'done', 0); $done = empty($search) ? "and (($done = 0 and sls.DoneDate is null) or ($done = 1 and sls.DoneDate is not null))" : ''; $limit = isEmptyProperty($params, 'limit', 25); $offset = isEmptyProperty($params, 'start', 0); $search = self::getSearchSqlWhere($search); } $sql = self::getOrdersSql(); $sql = <<<"EOT"
$sql
    $done
    $search
    $orders
order by sls.DocumentDate desc, sls.synevoLogServiceCode desc

EOT;
$root = new stdClass(); $total = (int) selectSqlValue($db, "select count(*) from ($sql) as t_"); $root->total = $total; $offset = $total < $offset ? 0 : $offset; $sql .= "limit $limit offset $offset"; $root->items = selectSql($db, $sql); return $root; } public function createOrders ($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $userId = (int) getSessionValue('user'); return insertSql($db, 'synevoLogService', $records, "EnterpriseCode, WebUserCode, DocumentNumber, DocumentDate, DoctorFio, MedPatientCode, PaymentSum, DoneDate, Summ, SummVat, PatientFio, Note, VisitTime, MedCabinetCode", "documentNumber, documentDate, doctorFio, patientId, paymentSum, doneDate, summ, summVat, patientFio, note, visitTime, cabinetId", "$enterpriseCode, $userId, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" ); } public function updateOrders ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'synevoLogService', 'synevoLogServiceCode', $records, "DocumentNumber, DocumentDate, DoctorFio, MedPatientCode, PaymentSum, DoneDate, Summ, SummVat, PatientFio, Note, VisitTime, MedCabinetCode", "documentNumber, documentDate, doctorFio, patientId, paymentSum, doneDate, summ, summVat, patientFio, note, visitTime, cabinetId" ); } public function deleteOrders ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'synevoLogService', 'synevoLogServiceCode', $records); } public function getNextDocumentNumber() { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); return selectSqlValue($db, "select coalesce(c, 0) + 1 from (select count(1) as c from synevoLogService where EnterpriseCode = ?) as t", [$enterpriseCode] ); } public function readOrderServices ($params) { $db = $this->db ?: $this->db = connectDb(); $logServiceId = (int) isEmptyProperty($params, 'logServiceId'); $sql = <<<"EOT"
select
    r.synevoLogServiceRowCode as "id",
    r.synevoLogServiceCode as "logServiceId",
    l.state as "orderState",
    r.ServiceID as "serviceId",
    r.ServiceCode as "serviceCode",
    r.ServiceName as "serviceName",
    r.ServiceAmount as "serviceAmount",
    r.ServicePrice as "servicePrice",
    /*
    r.ServicePriceVAT as "servicePriceVat",
    r.WealthPrice as "wealthPrice",
    r.WealthPriceVAT as "wealthPriceVat",
    */
    r.ResultSum as "resultSum"/*,
    r.ResultSumVAT as "resultSumVat"
    */
from synevoLogServiceRow as r
join synevoLogService as l on l.synevoLogServiceCode = r.synevoLogServiceCode
where r.synevoLogServiceCode = ?
EOT;
return selectSql($db, $sql, [$logServiceId]); } public function createOrderServices ($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'synevoLogServiceRow', $records, "synevoLogServiceCode, ServiceID, ServiceCode, ServiceName, ServiceAmount, ServicePrice, ServicePriceVAT, WealthPrice, WealthPriceVAT, ResultSum, ResultSumVAT", "logServiceId, serviceId, serviceCode, serviceName, serviceAmount, servicePrice, servicePriceVat, wealthPrice, wealthPriceVat, resultSum, resultSumVat" ); } public function updateOrderServices ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'synevoLogServiceRow', 'synevoLogServiceRowCode', $records, "ServiceID, ServiceCode, ServiceName, ServiceAmount, ServicePrice, ServicePriceVAT, WealthPrice, WealthPriceVAT, ResultSum, ResultSumVAT", "serviceId, serviceCode, serviceName, serviceAmount, servicePrice, servicePriceVat, wealthPrice, wealthPriceVat, resultSum, resultSumVat" ); } public function deleteOrderServices ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'synevoLogServiceRow', 'synevoLogServiceRowCode', $records); } public function readLastDoctors() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    DoctorFio as "name"
from synevoLogService
where DocumentDate between now()::date - interval '1 year' and now()::date
group by DoctorFio
having coalesce(DoctorFio, '') <> ''
order by 1
EOT;
return selectSql($db, $sql); } } handleJsonRpc('map_synevo_orders');