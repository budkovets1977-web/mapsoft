<?php
require_once(__DIR__.'/../../init.php'); require_once(__DIR__.'/../../../lib/Slim/Slim/Slim.php'); class Server { private $db; private $app; function readSettings(){ $settings = readJsonSettings('settings_equeueinfobox'); if ($settings && isset($settings['apiConfig'])){ return $settings['apiConfig']; } else { return null; } } function array2xml($array, $xml = false){ if ($xml === false){ $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"utf-8\" ?><root/>"); } foreach($array as $key => $value){ if (is_array($value)){ if (is_numeric($key)){ $key = 'record'; } $this->array2xml($value, $xml->addChild($key)); } else { $xml->addChild($key, $value); } } return $xml->asXML(); } public function getAllPrivileges(){ $format = $this->app->request()->get('_format'); if ($format != 'application/xml'){ $format = 'application/json'; } $sql = <<<EOT
            select
                MedPrivelegeCode "code",
                Name "display"
            from MedPrivelege
EOT;
$stmt = $this->db->prepare($sql); $stmt->execute(); $data = $stmt->fetchAll(); $this->app->response->setStatus(200); $this->app->response['Content-Type'] = $format; switch ($format){ case 'application/json': $result = json_encode($data, JSON_UNESCAPED_UNICODE); break; case 'application/xml': $result = $this->array2xml($data); break; default: $result = json_encode($data, JSON_UNESCAPED_UNICODE); } echo $result; } public function getPatientPrivileges(){ $format = $this->app->request()->get('_format'); if ($format != 'application/xml'){ $format = 'application/json'; } $barCode = (string)$this->app->request()->post('identifier'); $sql = <<<EOT
            select
                MPR.MedPrivelegeCode "code",
                MPR.Name "display"
            from MedPatientCard MPC
            join MedPatient MP ON MP.MedPatientCode = MPC.MedPatientCode
            join MAN M ON M.ManCode = MP.ManCode
            join MedBarCode MB ON MB.BarCode = ?
                and MB.ManCode = M.ManCode
                and {$this->db->dialect("TODAY()", "current_date")} BETWEEN MB.StartDate AND DATEADD({$this->db->postgre("'")}day{$this->db->postgre("'")}, -1, COALESCE(MB.StopDate, 30000101))
            join MedPrivelege MPR ON MPR.MedPrivelegeCode = MPC.MedPrivelegeCode
            where
                {$this->db->dialect("TODAY()", "current_date")} between COALESCE(MPC.StartDate,19000101) and COALESCE(MPC.FinishDate,30000101)
EOT;
$stmt = $this->db->prepare($sql); $stmt->execute([$barCode]); $data = $stmt->fetchAll(); $this->app->response->setStatus(200); $this->app->response['Content-Type'] = $format; switch ($format){ case 'application/json': $result = json_encode($data, JSON_UNESCAPED_UNICODE); break; case 'application/xml': $result = $this->array2xml($data); break; default: $result = json_encode($data, JSON_UNESCAPED_UNICODE); } echo $result; } public function startServer(){ $apiConfig = $this->readSettings(); if (!$apiConfig){ die("Ошибка: отсутствует настройка конфигурации API"); } $this->db = $this->db ?: connectDb($apiConfig); Slim\Slim::registerAutoloader(); $this->app = new \Slim\Slim(); $this->app->get('/benefits', array($this, 'getAllPrivileges')); $this->app->post('/benefits', array($this, 'getPatientPrivileges')); $this->app->run(); } } $srv = new Server(); $srv->startServer();