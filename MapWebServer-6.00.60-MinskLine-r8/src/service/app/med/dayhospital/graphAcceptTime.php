<?php
require_once(__DIR__.'/../../init.php'); class med_dayhospital_graphAcceptTime { private $db; public function readGraphAcceptTime($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $where = ''; $whereArr = []; $graphId = isEmptyProperty($params, 'graphId'); if ($graphId) { $sqlParams[':graphId'] = $graphId; $whereArr[] = '(MGAT.MedGraphAcceptCode = :graphId)'; } if (!empty($whereArr)) { $where = 'WHERE ' . implode(' AND ', $whereArr); } $sql = <<<"EOT"
SELECT 
    MGAT.MedGraphAcceptTimeCode "id",
    MGAT.MedGraphAcceptCode "graphId",
    MGAT.hour "hour",
    MGAT.minute "minute",
    MGAT.tillHour "tillHour",
    MGAT.tillMinute "tillMinute"
FROM MedGraphAcceptTime MGAT
$where
ORDER BY MGAT.MedGraphAcceptTimeCode
EOT;
$records = selectSql($db, $sql, $sqlParams); foreach ($records as &$record) { $datetime = safe_date_create(); $record['startTime'] = date_format(date_time_set($datetime, $record['hour'], $record['minute']), 'Y-m-d H:i'); $datetime = safe_date_create(); $record['finishTime'] = date_format(date_time_set($datetime, $record['tillHour'], $record['tillMinute']), 'Y-m-d H:i'); } return $records; } public function updateGraphAcceptTime($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MedGraphAcceptTime', 'MedGraphAcceptTimeCode', $records, "MedGraphAcceptCode hour minute tillHour tillMinute", "graphId hour minute tillHour tillMinute" ); } public function createGraphAcceptTime($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'MedGraphAcceptTime', $records, 'MedGraphAcceptCode hour minute tillHour tillMinute', 'graphId hour minute tillHour tillMinute'); } public function deleteGraphAcceptTime($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedGraphAcceptTime', 'MedGraphAcceptTimeCode', $records); } } handleJsonRpc('med_dayhospital_graphAcceptTime');