<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_msgRules { private $db; private $EVENT_TYPES = ["medCoupon" => ["set", "cancel"]]; public function readMsgRules($params = null) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue("mapEnterpriseCode"); $node = isUndefinedProperty($params, 'node', 0); if($node === 0) { return $this->getEventTypesAsRecords(); } else { $types = $this->getEventTypesAsString(); $sqlWhere = "and mr.type in ({$types})"; $sqlWhere .= " and mr.type = '{$node}'"; $sqlRules = <<< "EOT"
    select
        mr.msgRuleCode "id",
        mr.name "name",
        mr.type "parentId",
        mr.stage "stage",
        mr.startPoint "startPoint",
        mr.startShift "startShift",
        mr.template "template",
        mr.active "checked",
        1 "leaf"
    from MsgRule mr
    where mr.enterpriseCode = ?
    {$sqlWhere}
EOT;
return selectSql($db, $sqlRules, [$mapEnterpriseCode]); } } public function createMsgRules($records) { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue("mapEnterpriseCode"); return insertSql($db, "MsgRule", $records, "enterpriseCode,active, name, stage, startPoint, startShift, template, type", "checked, name, stage, startPoint, startShift, template, type", "{$mapEnterpriseCode}, ?,  ?, ?, ?, ?, ?, ?"); } private function getEventTypesAsRecords () { $ret = []; foreach ($this->EVENT_TYPES as $type => $events) { foreach ($events as $event) { $rec = new stdClass(); $rec->id = "{$type}-{$event}"; $rec->name = $rec->id; $rec->loaded = false; $rec->parentId = 0; $ret[] = $rec; } } return $ret; } private function getEventTypesAsString() { $ret = []; foreach ($this->EVENT_TYPES as $type => $events) { foreach ($events as $event) { $ret[] = "'{$type}-{$event}'"; } } return implode(',', $ret); } public function updateMsgRules($records) { $db = $this->db ?: $this->db = connectDb(); $fields = 'name, stage, startPoint, startShift, template'; return updateSql($db, 'MsgRule', 'MsgRuleCode', $records, $fields, $fields); } public function deleteMsgRules($records) { $db = $this->db ?: $this->db = connectDb(); deleteSql($db, 'MsgRule', 'MsgRuleCode', $records); } public function updateActivity($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $active = (int)isUndefinedProperty($params, 'checked', 1); $sql =<<<"EOT"
update MsgRule set
    active = {$active}
where MsgRuleCode = {$id}
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return true; } } handleJsonRpc('med_common_msgRules'); 