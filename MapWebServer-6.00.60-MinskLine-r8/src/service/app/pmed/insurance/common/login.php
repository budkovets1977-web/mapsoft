<?php
 $serviceClass = 'pmed_insurance_common_login'; class pmed_insurance_common_login extends common_login { const ERROR_ENTERPRISE_NOT_LINK = -1001; const NEW_CUSTOMER_NAME = 'Текущая организация'; const NEW_USER_NAME = 'Администратор'; public function readCustomers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    id,
    name,
    domains
from organization
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = []; while(($row = $stmt->fetch())) { if (!empty($row['domains']) && preg_match("/{$row['domains']}/im", $_SERVER['SERVER_NAME']) !== 1) { continue; } unset($row['domains']); $ret[] = $row; } return $ret; } public function readUsers ($params = null) { $db = $this->db ?: $this->db = connectDb(); $params = $params ?: new stdClass; $customerId = isEmptyProperty($params, 'customerId', 0); $sql = <<<"EOT"
select
    id,
    organization_id "customerId",
    name
from person
where active and organization_id = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$customerId]); $ret = $stmt->fetchAll(); return $ret; } public function login ($userName, $password, $customerId) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<'EOT'
select p.id, p.name, p.active, p.password, p.organization_id "customerId", o.name "customerName" 
from person p join organization o on p.organization_id = o.id
where organization_id = ? and name = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$customerId, $userName]); if (($row = $stmt->fetch())) { if (!$row['active']) { throw new Exception("Пользователь '{$row['name']}' отключен в базе ЦОД", self::ERROR_USER_NOT_ACTIVE); } if (($row['password'] || $password) && $password !== $row['password']) { throw new Exception("Неверный пароль", self::ERROR_INVALID_PASSWORD); } } else { throw new Exception("Пользователь '{$userName}' отсутствует в базе ЦОД", self::ERROR_USER_NOT_FOUND); } setSessionValue( 'user', $row['id'], 'userName', $row['name'], 'customer', $row['customerId'], 'customerName', $row['customerName'] ); return [ 'user' => $row ]; } } 