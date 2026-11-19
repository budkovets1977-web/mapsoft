<?php
 require_once(__DIR__ . '/../../init.php'); class map_common_matSources { private $db; public function readMatSources () { $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    MatSourceCode as "id",
    BuhAnalyticsCode as "analyticsId",
    EnterpriseCode as "enterpriseId",
    Name as "name",
    ShortName as "shortName",
    SeparateVAT as "separateVAT"
FROM MatSource
WHERE EnterpriseCode = :enterpriseId;
EOT;
return selectSql($db, $sql, ['enterpriseId' => $mapEnterpriseCode]); } } handleJsonRpc('map_common_matSources');