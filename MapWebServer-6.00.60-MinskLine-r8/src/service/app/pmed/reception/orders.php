<?php
 require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/common/storage.php'); resumeJsonRpc(); class pmed_reception_orders { private $db; const ATTACHMENT_CACHE = 'pmedReceptionOrdersAttachmentCache'; public function readOrders($params) { $db = $this->db ?: $this->db = connectDb(); $allCondition = ""; $condPatient = ""; $sqlWhere = ""; $structureCode = (int) getSessionValue('EnterpriseStructureCode', 0); $id = (int) isEmptyProperty($params, 'id'); $patientId = isEmptyProperty($params, 'patientId', 0); $filter = isEmptyProperty($params, 'search'); $state = isUndefinedProperty($params, 'state'); $stateCond = is_numeric($state) ? "and POS.state = $state" : ""; $startDate = dbDate(isEmptyProperty($params, 'startDate', $id ? "1900-01-01" : date("Y-m-d"))); $finishDate = dbDate(isEmptyProperty($params, 'finishDate', $id ? "3000-01-01" : $startDate)); if ($filter) { $filter = mb_strtolower($filter, 'UTF-8'); $matches = array(); $reg = '/^(\+|\-|\ |\(|\)|\d)*$/'; $count = ""; if (preg_match_all($reg, $filter, $matches, PREG_SET_ORDER, 0)) { $numbString = preg_replace("/[^0-9]/", '', $matches[0][0]); $count = iconv_strlen($numbString); $rez = str_split($numbString); } if ($count) { $condNumber = ""; $value = implode("", $rez); if ($count > 1) { $condPrice = "PO.resultSum like '%{$value}%'"; $allCondition = $condPrice; } if ($count > 2) { $condNumber = "or PO.orderNumber like '%{$value}%'"; $allCondition .= $condNumber; $allCondition .= "or translate(_ManPhone.PhoneNumber,'0' || translate(_ManPhone.PhoneNumber,'_0123456789','_'),'0') LIKE '%$value%'"; } } else { $fio = preg_split("/[\s.,]+/u", $filter); $condPatient = $this->makeFIOCondition('M', $fio); $condDoctor = $this->makeFIOCondition('ManMD', $fio); $allCondition .= "({$condPatient}) or ({$condDoctor})"; } $condDiagnosis = "or D.name like '%{$filter}%' or D.cipher1 like '%{$filter}%'"; $condSum = "or PO.resultSum like '%{$filter}%'"; $allCondition .= $condDiagnosis; $allCondition .= $condSum; $allCondition = "and ({$allCondition})"; } if ($patientId) { $sqlWhere = "WHERE PO.medPatientCode = {$patientId}"; $sqlParams = null; } else { $sqlWhere = <<< "EOT"
        WHERE PO.enterpriseCode = ?
        and (? = 0 or PO.enterpriseStructureCode = ?)
        and (? = 0 or po.pmsOrderCode = ?)
        and COALESCE(PO.orderDate, 19000101) <= '{$finishDate}'::date
        and '{$startDate}'::date <= COALESCE(PO.doneDate, 30000101)
        $stateCond
        $allCondition
EOT;
$sqlParams = [ getSessionValue('mapEnterpriseCode'), $structureCode, $structureCode, $id, $id ]; } $sql = <<<"EOT"
SELECT
    PO.pmsOrderCode "id",
    POS.state "orderState",
    PO.diagnosisNote "diagnosisNote",
    PO.doneDate "doneDate",
    PO.medDiagnosisCode "diagnosisId",
    PO.medPatientCode "patientId",
    FN_cmFIO(MP.ManCode) "patientFio",
    _ManPhone.phoneNumber "patientPhone",
    PO.medDoctorCode "doctorId",
    FN_cmFIO(MD.ManCode, 1) "doctorFio",
    PO.note "note",
    D.cipher1 "diagnosisCipher",
    D.name "diagnosisName",
    PO.orderDate "orderDate",
    PO.orderNumber "orderNumber",
    PO.paymentType "paymentType",
    PO.resultSum "resultSum",
    PO.discountType "discountType",
    PO.discountValue "discountValue",
    PO.medProfessionCode "professionId",
    MPR.name "professionName",
    PVS.name "goal",
    POS.changeTime::date "changeStateTime",
    PVC.pmsVisitCouponCode "visitCouponId",
    to_char(PVC.visitTime, 'YYYY-MM-DD HH24:MI:SS') "visitCouponOrderTime",
    PO.pmsSuppContractCode "suppContractId",
    PO.pmsPatientInsuranceCode "patientInsuranceId",
    PR.summD-PR.summK "patientBalance",
    PO.resultSum - _orderPayment.sum "orderRest",
    _orderPayment.sum "paymentSum",
    PSC.summD "suppContractSummD",
    PSC.summK "suppContractSummK",
    (
        SELECT string_agg(PPL.fullcode, ',') from PmsOrderService POService
        LEFT JOIN PmsPriceList PPL on POService.pmsPriceListCode = PPL.pmsPriceListCode
        where  POService.pmsOrderCode = PO.pmsOrderCode
      ) "servicesList"
{$this->addSelectAttachmentSqlCondition(true)}
FROM PmsOrder PO
left JOIN MedPatient MP on PO.medpatientcode = MP.medpatientcode
LEFT JOIN PmsOrderState POS on POS.pmsOrderCode = PO.pmsOrderCode
    and COALESCE(POS.ChangeTime,19000101) = COALESCE((
        select max(ChangeTime)
        from PmsOrderState
        where pmsOrderCode = PO.pmsOrderCode
    ),19000101)
LEFT JOIN PmsPatientRest PR on PR.medPatientCode = PO.medPatientCode
LEFT JOIN MedDoctor MD on MD.medDoctorCode = PO.medDoctorCode
left join MedProfession MPR on MPR.MedProfessionCode = PO.MedProfessionCode
LEFT JOIN Man M on M.manCode = MP.manCode
LEFT JOIN LATERAL (select COALESCE(SUM(paymentSum), 0) sum from pmsorderpayment
where pmsOrderCode = PO.pmsOrderCode) _orderPayment on true
LEFT JOIN Man ManMD on ManMD.manCode = MD.manCode
LEFT JOIN lateral (select phoneNumber from ManPhone
                   where ManCode = mp.ManCode
                   and phoneType IN (1,3)
                   order by phoneType desc limit 1) _ManPhone on true
LEFT JOIN MedDiagnosis D on D.medDiagnosisCode = PO.medDiagnosisCode
LEFT JOIN PmsVisitCoupon PVC on PVC.pmsVisitCouponCode = PO.pmsVisitCouponCode
LEFT JOIN PmsVisitService PVS on PVS.pmsVisitServiceCode = PVC.pmsVisitServiceCode
LEFT JOIN pmsSuppContract PSC ON PSC.pmsSuppContractCode = PO.pmsSuppContractCode
LEFT JOIN PmsOrderService POService ON POService.pmsOrderCode = PO.pmsOrderCode
LEFT JOIN PmsPriceList PPL on POService.pmsPriceListCode = PPL.pmsPriceListCode
{$this->addSelectAttachmentSqlCondition()}
$sqlWhere
order by PO.pmsOrderCode desc
EOT;
return selectSql($db, $sql, $sqlParams); } private function addSelectAttachmentSqlCondition($fields = false) { if ($fields) { $sql = <<<EOT
    ,MEF.medExternalFileCode "attachmentId",
    MEF.fileName "attachmentName",
    MEF.fileExists "attachmentExists"
EOT;
} else { $sql = <<<EOT
left join lateral (select
                          medExternalFileCode,
                          fileName,
                          fileExists
                   from medExternalFile
                   where source = 100 -- код типа источника
                     and sourceCode = PO.pmsOrderCode
                   order by medExternalFileCode desc
                   limit 1) MEF on true
EOT;
} return $sql; } public function createOrders($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO PmsOrder (diagnosisNote, doneDate, enterpriseCode, enterpriseStructureCode, medDiagnosisCode,
    medPatientCode, note, orderDate, orderNumber, paymentType,
    usercode, medDoctorCode, medProfessionCode, pmsSuppContractCode, pmsPatientInsuranceCode,
    discountType, discountValue, pmsVisitCouponCode)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'diagnosisNote'), isUndefinedProperty($row, 'doneDate'), getSessionValue('mapEnterpriseCode'), (int) getSessionValue('EnterpriseStructureCode', 0), isUndefinedProperty($row, 'diagnosisId'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'orderDate'), isUndefinedProperty($row, 'orderNumber'), isUndefinedProperty($row, 'paymentType'), (int) getSessionValue('mapUserCode'), isUndefinedProperty($row, 'doctorId'), isUndefinedProperty($row, 'professionId'), isUndefinedProperty($row, 'suppContractId'), isUndefinedProperty($row, 'patientInsuranceId'), isUndefinedProperty($row, 'discountType'), isUndefinedProperty($row, 'discountValue'), isUndefinedProperty($row, 'visitCouponId') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_PmsOrder'); if (isset($row->orderState)) { $this->insertOrderState($row, $rec->id); } if(isset($row->visitCouponId)) { $this->updateCouponPatient($rec->id, $row->patientId); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateOrders($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update PmsOrder set
    diagnosisNote = case when ? = 1 then ? else diagnosisNote end,
    doneDate = case when ? = 1 then ? else doneDate end,
    medDiagnosisCode = case when ? = 1 then ? else medDiagnosisCode end,
    medPatientCode = case when ? = 1 then ? else medPatientCode end,
    note = case when ? = 1 then ? else note end,
    orderDate = case when ? = 1 then ? else orderDate end,
    orderNumber = case when ? = 1 then ? else orderNumber end,
    paymentType = case when ? = 1 then ? else paymentType end,
    medDoctorCode = case when ? = 1 then ? else medDoctorCode end,
    medProfessionCode = case when ? = 1 then ? else medProfessionCode end,
    pmsSuppContractCode = case when ? = 1 then ? else pmsSuppContractCode end,
    pmsPatientInsuranceCode = case when ? = 1 then ? else pmsPatientInsuranceCode end,
    discountType = case when ? = 1 then ? else discountType end,
    discountValue = case when ? = 1 then ? else discountValue end
where pmsOrderCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (isset($row->orderState)) { $this->insertOrderState($row); } if(isset($row->patientId)) { $this->updateCouponPatient($row->id, $row->patientId); } $stmt->execute([ propertyExistsSQL($row, 'diagnosisNote'), isEmptyProperty($row, 'diagnosisNote'), propertyExistsSQL($row, 'doneDate'), isEmptyProperty($row, 'doneDate'), propertyExistsSQL($row, 'diagnosisId'), isEmptyProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), propertyExistsSQL($row, 'note'), isEmptyProperty($row, 'note'), propertyExistsSQL($row, 'orderDate'), isEmptyProperty($row, 'orderDate'), propertyExistsSQL($row, 'orderNumber'), isEmptyProperty($row, 'orderNumber'), propertyExistsSQL($row, 'paymentType'), isEmptyProperty($row, 'paymentType'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'suppContractId'), isEmptyProperty($row, 'suppContractId'), propertyExistsSQL($row, 'patientInsuranceId'), isEmptyProperty($row, 'patientInsuranceId'), propertyExistsSQL($row, 'discountType'), isEmptyProperty($row, 'discountType'), propertyExistsSQL($row, 'discountValue'), isEmptyProperty($row, 'discountValue'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteOrders($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $querys = [ "DELETE FROM pmsOrderPayment WHERE pmsOrderCode = $row->id", "DELETE FROM pmsOrderServiceWealth WHERE pmsOrderServiceCode IN (SELECT pmsOrderServiceCode FROM pmsOrderService WHERE pmsOrderCode = $row->id)", "DELETE FROM pmsOrderService WHERE pmsOrderCode = $row->id", "DELETE FROM pmsOrder WHERE pmsOrderCode = $row->id"]; foreach ($querys as $oneQuery) { $stmt = $db->prepare($oneQuery); $stmt->execute(); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getNewOrderNumber() { $db = $this->db ?: $this->db = connectDb(); $structureCode = (int) getSessionValue('EnterpriseStructureCode', 0); $sql = <<<"EOT"
SELECT incstr(orderNumber) from PmsOrder
WHERE enterpriseCode = ?
and (? = 0 or enterpriseStructureCode = ?)
ORDER BY pmsOrderCode DESC LIMIT 1
EOT;
return selectSqlValue($db, $sql, [ getSessionValue('mapEnterpriseCode'), $structureCode, $structureCode ]); } private function insertOrderState($row, $orderId = null) { $db = $this->db ?: $this->db = connectDb(); $sqlState = <<<"EOT"
INSERT INTO PmsOrderState (changeTime, note, pmsOrderCode, state)
VALUES (now(), ?, ?, ?)
EOT;
$stmt = $db->prepare($sqlState); $stmt->execute([ isUndefinedProperty($row, 'note'), $orderId ? $orderId : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'orderState') ]); return true; } private function updateCouponPatient($orderId, $patientId) { $db = $this->db ?: $this->db = connectDb(); $sqlState = <<<"EOT"
update PmsVisitCoupon set
    medPatientCode = ?,
    patientFio = ?,
    patientPhone = ?
where pmsVisitCouponCode = (select COALESCE(pmsVisitCouponCode, 0) from pmsOrder where pmsOrderCode = ? )
EOT;
$stmt = $db->prepare($sqlState); $stmt->execute([ $patientId, null, null, $orderId ]); } private function makeFIOCondition($tableName, $fio) { $condPatient = " $tableName.SurName LIKE '$fio[0]%'"; if (isset($fio[1]) && $fio[1] !== "") { $condPatient .= " and $tableName.Name LIKE '$fio[1]%'"; } if (isset($fio[2]) && $fio[2] !== "") { $fio[2] = trim(implode(" ", (array_slice($fio, 2)))); $condPatient .= " and $tableName.SecondName LIKE '$fio[2]%'"; } return $condPatient; } public function commitAttachment($params = null) { $db = $this->db ?: $this->db = connectDb(); $file = getSessionValue(self::ATTACHMENT_CACHE); if (isEmptyProperty($params, 'action') === 'ok') { if (!is_null($file)) { $db->beginTransaction(); try { $source = isEmptyProperty($params, 'source', 100); $sourceId = isEmptyProperty($params, 'sourceId'); $attachmentId = isEmptyProperty($params, 'attachmentId', 'null'); $sql = <<<EOT
insert into medExternalFileT as MEFT
    (medExternalFileCode, data, source, sourceCode, fileName)
values
    ({$attachmentId}, :PDF, {$source}, {$sourceId}, '{$file->name}')
on conflict (medExternalFileCode)
do update set
   data = excluded.data,
   fileName = excluded.fileName
EOT;
$stmt = $db->prepare($sql); $pdf = file_exists($file->tmpFile) ? file_get_contents($file->tmpFile) : null; $stmt->bindParam(':PDF', $pdf, PDO::PARAM_LOB); $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); $this->clearAttachmentCache(); throw $e; } } } $this->clearAttachmentCache(); return true; } private function clearAttachmentCache() { $file = getSessionValue(self::ATTACHMENT_CACHE); if (!is_null($file)) { file_exists($file->tmpFile) && unlink($file->tmpFile); setSessionValue(self::ATTACHMENT_CACHE, null); } } public function updateAttachment() { $this->clearAttachmentCache(); header('Content-type: text/html'); $temp = $_FILES['attachment']['tmp_name']; $fileName = $temp . '.tmp'; rename($temp, $fileName); $cache = new stdClass(); $cache->tmpFile = $fileName; $cache->name = $_FILES['attachment']['name']; setSessionValue(self::ATTACHMENT_CACHE, $cache); $rec = new stdClass; $rec->success = true; echo json_encode($rec); } } handleJsonRpc('pmed_reception_orders'); 