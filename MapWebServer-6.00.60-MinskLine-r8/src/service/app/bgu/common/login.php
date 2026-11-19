<?php
 $serviceClass = 'bgu_common_login'; class bgu_common_login extends common_login { public function readCustomers () { return [ ['id' => 1, 'name' => 'БГУ'] ]; } public function readUsers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<'EOT'
select user_id as id, 1 as "customerId", login as name
from bgu_schema.acl_user
order by name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function login ($userName, $password, $customerId) { $db = $this->db ?: $this->db = connectDb(); $id = selectSqlValue($db, "select user_id from bgu_schema.acl_user where login = ?", [$userName]); if (!$id) { throw new Exception("Пользователь '{$userName}' отсутствует в базе ЦОД", self::ERROR_USER_NOT_FOUND); } setSessionValue('su', true); setSessionValue( 'su', true, 'user', $id, 'userName', $userName, 'customer', 1, 'customerName', 'БГУ' ); return [ 'user' => [ 'id' => $id, 'customerId' => $customerId, 'name' => $userName, ], 'profile' => [] ]; } } 