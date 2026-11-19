<?php
 require_once(__DIR__ . '/../../init.php'); class buh_services_templates { private $db; private function getDB() { return $this->db ? $this->db : $this->db = connectDb(); } public function createTemplate($records) { $db = $this->getDb(); $sql = <<<"EOT"
insert into ServiceTemplate
    (enterpriseCode, serviceType, name, serviceKindCode)
values
    (?, ?, ?, ?)

EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ getSessionValue('mapEnterpriseCode'), isUndefinedProperty($row, 'serviceType'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'serviceKindId'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_ServiceTemplate'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readTemplate($params = null) { $db = $this->getDb(); $sql = <<<"EOT"
select
       ST.serviceTemplateCode "id",
       ST.enterpriseCode "enterpriseId",
       ST.serviceType "serviceType",
       ST.name "name",
       ST.serviceKindCode "serviceKindId"
from ServiceTemplate ST
where ST.enterpriseCode = ?
  and ST.serviceType = ?
EOT;
return selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode'), isUndefinedProperty($params, 'type', 0) ]); } public function updateTemplate($records) { $db = $this->getDb(); $sql = <<<"EOT"
update ServiceTemplate set
       serviceType = case when ? = 1 then ? else serviceType end,
       name = case when ? = 1 then ? else name end,
       serviceKindCode = case when ? = 1 then ? else serviceKindCode end
where serviceTemplateCode = ?

EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'serviceType'), isUndefinedProperty($row, 'serviceType'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'serviceKindId'), isUndefinedProperty($row, 'serviceKindId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteTemplate($records) { $db = $this->getDb(); return deleteSql($db, 'ServiceTemplate', 'serviceTemplateCode', $records); } public function createTemplateIn($records) { $db = $this->getDb(); $sql = <<<"EOT"
insert into ServiceTemplateIn
    (serviceTemplateCode, serviceCode, serviceWealthSetCode)
values
    (?, ?, NULLIF(?, 0))

EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'templateId'), isUndefinedProperty($row, 'serviceId'), isUndefinedProperty($row, 'wealthSetId'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_ServiceTemplateIn'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readTemplateIn($params = null) { $db = $this->getDb(); $sql = <<<"EOT"
select
       STI.serviceTemplateInCode "id",
       STI.serviceTemplateCode "templateId",
       STI.serviceCode "serviceId",
       FN_ServiceName(STI.serviceCode, 7) "serviceName",
       STI.serviceWealthSetCode "wealthSetId",
       COALESCE(SWS.name, 'Нет наборов') "wealthSetName"--,
       --FN_ServiceName(STI.serviceCode, 1) "searchName"
from ServiceTemplateIn STI
     join ServiceTemplate ST on ST.serviceTemplateCode = STI.serviceTemplateCode
                            and ST.enterpriseCode = ?
     left join ServiceWealthSet SWS on STI.serviceWealthSetCode = SWS.serviceWealthSetCode
                                   and STI.serviceCode = SWS.serviceCode
EOT;
return selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode') ]); } public function updateTemplateIn($records) { $db = $this->getDb(); $sql = <<<"EOT"
update ServiceTemplateIn set
       serviceTemplateCode = case when ? = 1 then ? else serviceTemplateCode end,
       serviceCode = case when ? = 1 then ? else serviceCode end,
       serviceWealthSetCode = case when ? = 1 then ? else serviceWealthSetCode end
where serviceTemplateInCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'templateId'), isUndefinedProperty($row, 'templateId'), propertyExistsSQL($row, 'serviceId'), isUndefinedProperty($row, 'serviceId'), propertyExistsSQL($row, 'wealthSetId'), isUndefinedProperty($row, 'wealthSetId'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteTemplateIn($records) { $db = $this->getDb(); return deleteSql($db, 'ServiceTemplateIn', 'serviceTemplateInCode', $records); } public function readServices($params = null) { $db = $this->getDb(); $sql = <<<"EOT"
select
       S.serviceCode "id",
       S.sortCode "orderedCode",
       SK.Name "serviceKindName",
       S.name "serviceName",
       --S.fullCode "code",
       FN_cmEnterpriseStructureName(S.enterpriseStructureCode) "branchName",
       --S.code "simpleCode",
       --FN_ServiceName(S.serviceCode, 6) "complexName",
       COALESCE(MWM.name, '') "measureName",
       --COALESCE(FN_cmEnterpriseStructureName(S.enterpriseStructureCode), '') "branchOrder",
       FN_ServiceName(S.serviceCode, 7) "complexName",
       S.code39 "code39",
       S.name39 "name39",
       --S.uet "uet",
       --S.autoOnVisit "autoOnVisit",
       CASE
            WHEN COALESCE(SWS.name,'') <> '' THEN SWS.name
            ELSE 'Нет набора'
       END "wealthSetName",
       SWS.serviceWealthSetCode "wealthSetId"
from Service S
     left join MatWealthMeasure MWM on MWM.matWealthMeasureCode = S.matWealthMeasureCode
     left join ServiceKind SK on SK.serviceKindCode = S.serviceKindCode
     left join ServiceWealthSet SWS on SWS.serviceCode = S.serviceCode
                                   and COALESCE(SWS.isDeleted, 0) = 0
where S.enterpriseCode = ?
order by 2, 3, 4 --"orderCode", "serviceKindName", "serviceName"
EOT;
return selectSql($db, $sql, [ getSessionValue('mapEnterpriseCode') ]); } } handleJsonRpc('buh_services_templates'); 