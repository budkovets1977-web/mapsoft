<?php
 require_once(__DIR__ . '/../../init.php'); class med_hospitalstats_vacancies { private $db; private function error ($msg) { http_response_code(500); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); die; } public function getVacancies () { header('Access-Control-Allow-Origin: *'); $settings = readJsonSettings('settings_med'); if ($settings && isset($settings['defaultConfig'])) { $config = $settings['defaultConfig']; } else { $this->error('Отсутствует настройка конфигурации по умолчанию "defaultConfig" в файле "cfg\settings_med.json"'); } $db = $this->db ?: $this->db = connectDb($config); $date = new DateTime(selectSqlValue($db, 'select max(MoveDate) from MedStatMove where MoveDate <= date(now())')); $sql = <<<"EOT"
select
 coalesce(d.FullName, d.Name) name,
 sum(coalesce(CotEndFreeMale, 0) + coalesce(CotEndFreeMale, 0)) beds
from
 MedStatMove m
 join Department d on d.DepartmentCode = m.DepartmentCode
where movedate = ?
group by name
order by name
EOT;
$stmt = $db->prepare($sql); $stmt->execute([dbDate($date)]); $res = [ 'date' => $date->format('d.m.Y'), 'departments' => $stmt->fetchAll() ]; echo json_encode($res, JSON_UNESCAPED_UNICODE); } } $srv = new med_hospitalstats_vacancies(); $srv->getVacancies();