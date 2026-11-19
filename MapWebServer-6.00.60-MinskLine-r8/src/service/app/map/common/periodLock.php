<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_periodLock { private $db; public function readLockDate() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<< "EOT"
    SELECT medDocumentCloseDate
    FROM Enterprise
    WHERE enterpriseCode = $enterpriseId
EOT;
return selectSql($db, $sql); } public function writeLockDate($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $date = isEmptyProperty($params, 'date'); $sql = <<< "EOT"
    UPDATE Enterprise
    SET medDocumentCloseDate = ?
    WHERE enterpriseCode = $enterpriseId
EOT;
return selectSql($db, $sql, [$date]); } public function getVisitLockDate() { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<< "EOT"
    SELECT
    COALESCE(e.medDocumentCloseDate, '19000101') "lockDate"
    FROM Enterprise e
    WHERE e.enterpriseCode = $enterpriseId
EOT;
return selectSql($db, $sql)[0]['lockDate']; } } handleJsonRpc('map_common_periodLock'); 