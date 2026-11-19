<?php
 require_once(__DIR__ . '/../../../init.php'); class med_common_dicom_services { private $db; public function readDicomServices($params = null) { $db = $this->db ?: $this->db = connectDb(); $organizationId = isset($params->organizationId) ? (int) $params->organizationId : 0; $sql = <<<"EOT"
select
    ds.dicomservicecode "id",
    ds.ae "ae",
    ds.incomingport "incomingPort",
    ds.active "active",
    ds.note "note",
    ds.enterprisecode "organizationId",
    e.name "organizationName",
    (((~ COALESCE(ds.Attributes, 0)) & 1) = 1) "registerImages",
    ((COALESCE(ds.Attributes, 0) & 2) = 2) "registerResults",
    ((COALESCE(ds.Attributes, 0) & 4) = 4) "extractImages"
from DicomService ds
join Enterprise e on e.EnterpriseCode = ds.EnterpriseCode
    and fn_WebUserAccessOrganization(e.EnterpriseCode) = 1
where ds.enterprisecode = ? or 0 = ?
order by ds.ae
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$organizationId, $organizationId]); return $stmt->fetchAll(); } public function updateDicomServices($records) { $db = $this->db ?: $this->db = connectDb(); $columns = [ 'ae' => 'ae', 'incomingPort' => 'incomingport', 'active' => 'active', 'note' => 'note', 'organizationId' => 'enterprisecode', ]; $skipEmpty = ['active']; if (is_object($records)) { $records = [$records]; } foreach ($records as $record) { $updateColumns = []; $updateData = []; foreach ($columns as $prop => $column) { if (isset($record->{$prop}) && (array_search($prop, $skipEmpty) == false || !empty($record->{$prop})) ) { $updateColumns[] = $column . ' = ?'; $updateData[] = $this->_convertValue($record->{$prop}); } } $updateColumns[] = $this->_createAttributesUpdateSql($record); $updateData[] = $record->id; $updateColumns = array_filter($updateColumns); if (!empty($updateColumns)) { $sql = 'UPDATE DicomService SET ' . implode(', ', $updateColumns) . ' WHERE DicomServiceCode = ?'; executeSql($db, $sql, $updateData); } } return true; } public function deleteDicomServices($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'DicomService', 'dicomservicecode', $records); } public function createDicomServices($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } foreach ($records as &$record) { $this->_convertRecordAttributes($record); } emptyRecordFields($records, 'active'); return insertSql($db, 'DicomService', $records, 'ae, incomingport, active, note, enterprisecode, attributes', 'ae, incomingPort, active, note, organizationId, attributes'); } protected function _createAttributesUpdateSql($record) { $result = ''; $ops = []; if (isset($record->registerImages)) { $ops[] = !$record->registerImages ? '| (1 << 0)' : '& ~(1 << 0)'; } if (isset($record->registerResults)) { $ops[] = $record->registerResults ? '| (1 << 1)' : '& ~(1 << 1)'; } if (isset($record->extractImages)) { $ops[] = $record->extractImages ? '| (1 << 2)' : '& ~(1 << 2)'; } if (!empty($ops)) { $result = 'Attributes = COALESCE(Attributes, 0) ' . implode(' ', $ops); } return $result; } protected function _convertRecordAttributes($record) { if (isset($record->registerImages) || isset($record->registerResults) || isset($record->extractImages)) { $attributes = 0; if (isset($record->registerImages)) { if (!$record->registerImages) { $attributes |= 1; } unset($record->registerImages); } if (isset($record->registerResults)) { if ($record->registerResults) { $attributes |= 2; } unset($record->registerResults); } if (isset($record->extractImages)) { if ($record->extractImages) { $attributes |= 4; } unset($record->extractImages); } $record->attributes = $attributes; } } protected function _convertValue($value) { if ($value === false) { $value = 0; } return $value; } } handleJsonRpc('med_common_dicom_services'); 