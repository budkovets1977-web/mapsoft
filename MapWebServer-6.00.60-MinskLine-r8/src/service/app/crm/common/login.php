<?php
 $serviceClass = 'map_crm_common_login'; class map_crm_common_login extends common_login { public function readCustomers () { return [ ['id' => 1, 'name' => 'МАПСОФТ'] ]; } public function readUsers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<'EOT'
select id, 1 "customerId", name
from rbac_role
where this_user and active 
order by name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function login ($userName, $password, $customerId) { $db = $this->db ?: $this->db = connectDb(); if (empty($password)) { throw new Exception("Неверный пароль", self::ERROR_INVALID_PASSWORD); } $sql = <<<'EOT'
select r.id, 1 "customerId", r.name, r.not_in_domain,
       u.password, u.full_name "fio", birthday "birthDate", 
    (select string_agg(perm, ',') from (
        select 'password_db_request' where rbac_test_access('password_db_request', 0, id) union all 
        select 'password_db_approve' where rbac_test_access('password_db_approve', 0, id) union all
        select 'password_db_log' where rbac_test_access('password_db_log', 0, id)
        ) t(perm)) "roles"
from rbac_role r left join rbac_user u on u.role_id = r.id 
where this_user and active and name = ?
limit 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$userName]); if (($row = $stmt->fetch())) { if ($row['not_in_domain']) { if (($row['password'] || $password) && md5($password) !== $row['password']) { throw new Exception("Неверный пароль", self::ERROR_INVALID_PASSWORD); } } else { $ldap = @ldap_connect('ldap://dc.mapsoft.by'); if (!$ldap) { throw new Exception("Нет подключения к серверу LDAP", self::ERROR_LDAP_CONNECT); } if (!($bind = @ldap_bind($ldap, $row['name'] . '@mapsoft.by', $password))) { throw new Exception("Неверный пароль", self::ERROR_INVALID_PASSWORD); } @ldap_close($ldap); } } else { throw new Exception("Пользователь '{$userName}' отсутствует в базе ЦОД", self::ERROR_USER_NOT_FOUND); } $profile = (object)[ 'fio' => $row['fio'], 'birthDate' => $row['birthDate'], 'roles' => $row['roles'] ]; setSessionValue( 'roles', $row['roles'], 'user', $row['id'], 'userName', $row['name'], 'customer', 1, 'customerName', 'МАПСОФТ', 'userProfile', $profile ); return [ 'user' => [ 'id' => $row['id'], 'customerId' => $row['customerId'], 'name' => $row['name'], ], 'profile' => $profile ]; } } 