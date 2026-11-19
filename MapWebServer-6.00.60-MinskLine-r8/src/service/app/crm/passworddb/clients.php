<?php
 require_once(__DIR__.'/../../init.php'); class crm_passworddb_clients { private $db; public function readClients($params = null) { $db = $this->db ?: $this->db = connectDb(); $limit = isEmptyProperty($params, 'limit', 0); $offset = isEmptyProperty($params, 'page', 0) * $limit; $search = isEmptyProperty($params, 'search', ''); $filterByDb = isEmptyProperty($params, 'filterByDb', false); $sqlParams = []; $where = ''; $limitSql = ''; if ($search) { $search = '%' . $search . '%'; $where = "where (LOWER(CL.name || ' ' || CL.address || ' ' || CL.business_name) LIKE LOWER(?))"; array_push($sqlParams, $search); } if ($limit) { $limitSql = 'limit ' . $limit . ' offset ' . $offset; } if ($filterByDb) { $where ? $where .= ' and (EXISTS (SELECT 1 FROM client_list_db WHERE client_id = CL.id))' : $where = 'where (EXISTS (SELECT 1 FROM client_list_db WHERE client_id = CL.id))'; } $sql = <<<"EOT"
select
    CL.id "id",
    CL.name "name",
    CL.business_name "businessName",
    CL.town_id "townId",
    CL.active "active",
    CL.address "address",
    LT.name "townName"
from client_list CL
left join locate_town LT on CL.town_id = LT.id

$where
order by name
$limitSql
EOT;
$ret = selectSql($db, $sql, $sqlParams); return $ret; } } handleJsonRpc('crm_passworddb_clients');