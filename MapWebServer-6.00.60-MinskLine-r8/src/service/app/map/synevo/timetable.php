<?php
 require_once(__DIR__.'/../../init.php'); suspendJsonRpc(); require_once(__DIR__.'/orders.php'); resumeJsonRpc(); class map_synevo_timetable { private $db; private function filterOrders($interval, $orders, $cabinet, $onTime) { $res = []; foreach ($orders as $o) { if ($o['cabinetId'] != $cabinet['id']) { continue; } $vTime = date_create_from_format('H:i', date_format(date_create_from_format('Y-m-d H:i', $o['visitTime']), 'H:i')); $iTime = date_add(clone $onTime, date_interval_create_from_date_string("$interval minute")); if ($vTime >= $onTime && $vTime < $iTime) { $res[] = (object) $o; } } return $res; } public function readData($param) { $db = $this->db ?: $this->db = connectDb(); $onDate = date_format(safe_date_create(isEmptyProperty($param, 'onDate')), 'Y-m-d'); $interval = isEmptyProperty($param, 'interval', 30); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $res = new stdClass(); $sql = <<<"EOT"
select
    medCabinetCode as "id",
    cabinet as "name",
    dateformat(StartTime, 'hh:nn') as "startTime",
    dateformat(FinishTime, 'hh:nn') as "finishTime",
    Note as "note"
from medCabinet
where EnterpriseCode = ?
    and Active = 1
    and StartTime is not null
    and FinishTime is not null
order by 2
EOT;
$res->cabinets = $cabinets = selectSql($db, $sql, [$enterpriseCode]); $ordersClass = new map_synevo_orders(); $sql = $ordersClass->getOrdersSql(); $sql = <<<"EOT"
$sql
    and sls.VisitTime::date = ?
order by sls.VisitTime, 2
EOT;
$orders = selectSql($db, $sql, [$onDate]); $minT = null; $maxT = null; $fields = new stdClass(); foreach ($cabinets as $cab) { $t = date_create_from_format('H:i', $cab['startTime']); if (empty($minT) || $t < $minT) { $minT = $t; } $t = date_create_from_format('H:i', $cab['finishTime']); if (empty($maxT) || $t > $maxT) { $maxT = $t; } $fields->{'field_'.$cab['id']} = $cab; } $res->data = []; $curT = $minT; if (!empty($curT)) { while (true) { $row = new stdClass(); $row->time = date_format($curT, 'H:i'); foreach ($fields as $key => $value) { $row->{$key} = new stdClass(); $t = date_create_from_format('H:i', $value['startTime']); $t1 = date_create_from_format('H:i', $value['finishTime']); if ($curT >= $t && $curT <= $t1) { $row->{$key}->cabinetId = $value['id']; } $filteredOrders = $this->filterOrders($interval, $orders, $value, $curT); if (!empty($filteredOrders)) { $row->{$key}->orders = $filteredOrders; } } $res->data[] = $row; date_add($curT, date_interval_create_from_date_string("$interval minute")); if ($curT > $maxT) { break; } } } return $res; } public function getLastData($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $cabinetId = (int) isEmptyProperty($params, 'cabinetId'); $visitTime = date_format(safe_date_create(isEmptyProperty($params, 'visitTime')), 'Y-m-d H:i'); $sql = <<<"EOT"
select
    DoctorFio as "doctorFio"
from synevoLogService
where EnterpriseCode = ?
    and MedCabinetCode = ?
    and VisitTime::date = '$visitTime'::date
    and VisitTime < '$visitTime'
order by VisitTime desc
limit 1
EOT;
$res = selectSql($db, $sql, [ $enterpriseCode, $cabinetId ], PDO::FETCH_OBJ); $res = reset($res); return (object) $res; } } handleJsonRpc('map_synevo_timetable');