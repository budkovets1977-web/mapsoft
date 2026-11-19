<?php
 require_once(__DIR__.'/../../init.php'); class crm_passworddb_databases { private $db; public function readDatabases ($params = null) { $db = $this->db ?: $this->db = connectDb(); $clientId = isEmptyProperty($params, 'clientId'); if ($clientId) { $where = 'WHERE CLD.client_id = '.$clientId; } else { $where = ''; } $sql = <<<"EOT"
select
   CLD.id "id",
   CLD.client_id "clientId",
   CLD.name "name",
   CLD.note "note",
   CLD.create_date "createDate"
from client_list_db CLD
$where
EOT;
$ret = selectSql($db, $sql); return $ret; } } handleJsonRpc('crm_passworddb_databases');