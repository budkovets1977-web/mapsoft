<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_serviceWorkType { private $db; public function readServiceWorkType(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                mcwp.medCouponWorkProfCode id,
                mcw.name "workName",
                mp.name "professionName"
            FROM medCouponWork mcw
            JOIN medCouponWorkProf mcwp ON mcwp.medCouponWorkCode = mcw.medCouponWorkCode
            LEFT JOIN medProfession mp ON mp.medProfessionCode = mcwp.medProfessionCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_serviceWorkType');