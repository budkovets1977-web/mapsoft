<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/../../map/infopanel/infopanelData.php'); resumeJsonRpc(); class map_infopanel_settings { private $db; public function readSettings(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT name,
                   data
            FROM infopanelSync
EOT;
$ret = selectSql($db, $sql); return $ret; } public function updateSettings($params){ $db = $this->db ?: $this->db = connectDb(); $srvPanelData = new map_infopanel_infopanelData(); $srvPanelData->updateInfopanelSyncTable($params); return true; } public function createSettings($params){ $db = $this->db ?: $this->db = connectDb(); $srvPanelData = new map_infopanel_infopanelData(); $srvPanelData->updateInfopanelSyncTable($params); return true; } public function deleteSettings($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } deleteSql($db, 'infopanelSync', 'name', $records, 'name'); return true; } } handleJsonRpc('map_infopanel_settings');