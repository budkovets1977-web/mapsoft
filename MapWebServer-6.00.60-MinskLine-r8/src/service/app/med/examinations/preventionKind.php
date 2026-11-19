<?php
require_once(__DIR__ . '/../../init.php'); class med_examinations_preventionKinds { private $db; public function readPreventionKinds($params = null){ $db = $this->db ?: $this->db = connectDb(); $inputForm = isEmptyProperty($params, 'inputForm', '1'); switch ($inputForm) { case '2': $cond = 'in (2, 9)'; break; case '3': $cond = 'in (3, 10)'; break; default: $cond = 'in (' . $inputForm . ')'; } $sql = <<<SQL
            SELECT
                MedPreventTypeCode  "id",
                EnterpriseCode      "enterpriseId",
                Name                "name",
                InputForm           "inputForm",
                FormFields          "formFields",
                MedProfessionCode   "medProfessionId",
                MedCouponWorkCode   "medCouponWorkId",
                ExchangeDpc         "exchangeDpc",
                MedprivelegeCodeDpc "medprivelegeCodeDpc"
            FROM MedPreventType
            WHERE InputForm {$cond}
            ORDER BY Name
            SQL;
return selectSql($db, $sql); } } handleJsonRpc('med_examinations_preventionKinds');