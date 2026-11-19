<?php
require_once(__DIR__ . '/../../init.php'); class med_ambulance_wealths { private $db; public function readWealths($params = null){ $db = $this->db ?: $this->db = connectDb(); $ambLogId = isEmptyProperty($params, 'ambLogId', 0); $ambTaskId = isEmptyProperty($params, 'ambTaskId', 0); $medicationType = isEmptyProperty($params, 'medicationType'); $where = ''; if ($medicationType && $medicationType === 1){ $where = ' AND lw.medicationType = 1'; } else if ($medicationType !== 2){ $where = ' AND (lw.medicationType IS NULL or lw.medicationType = 0)'; } $sql = <<<EOT
            SELECT
                lw.ambLogWealthCode             id,
                lw.ambLogCode                   "ambLogId",
                lw.amount,
                lw.matWealthCode                "medicationId",
                wn.name0                        "medicationName",
                lw.methodUse                    "methodOfApplication",
                FN_cmMatWealthLongNameEx(lw.matwealthcode, 2)
                                                "wealth",
                FN_cmMatWealthLongNameEx(lw.matwealthcode, 11)
                                                "size",
                lw.description                  "description",
                lw.medicationType               "medicationType",
                lw.note                         "note",
                lw.amountText                   "amountText",
                lw.forThePurposeOfCupping       "forThePurposeOfCupping",
                lw.shownInjection               "shownInjection",
                lw.medDoctorCodeAppointer       "doctorAppointerId",
                fn_cmFio(doc1.manCode)          "doctorAppointerName",
                lw.medDoctorCodeInjector        "doctorInjectorId",
                fn_cmFio(doc2.manCode)          "doctorInjectorName",
                CASE WHEN lw.injectionTime::DATE = '19000101'
                    THEN NULL
                    ELSE lw.injectionTime END   "injectionDate",
                CASE WHEN TO_CHAR(lw.injectionTime, 'SS') = '05'
                    THEN ''
                    ELSE TO_CHAR(lw.injectionTime, 'HH24:MI') END
                                                "injectionTime"
            FROM ambLogWealth lw
            LEFT JOIN matWealthNames wn ON wn.matWealthCode = lw.matWealthCode
            LEFT JOIN medDoctor doc1 ON doc1.medDoctorCode = lw.medDoctorCodeAppointer
            LEFT JOIN medDoctor doc2 ON doc2.medDoctorCode = lw.medDoctorCodeInjector
            WHERE (lw.ambLogCode = :ambLogId OR lw.ambTaskCode = :ambTaskId)
            $where
            ORDER BY 1
EOT;
$ret = selectSql($db, $sql, [ ':ambLogId' => $ambLogId, ':ambTaskId' => $ambTaskId ]); return $ret; } public function createWealths($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } for ($i = 0; $i < count($records); $i++){ $row = $records[$i]; $records[$i]->injectionTime = $this->createDateTime($row); } return insertSql($db, 'ambLogWealth', $records, 'ambLogCode, amount, matWealthCode,
                ambTaskCode, methodUse, description,
                medicationType, note, amountText,
                forThePurposeOfCupping, shownInjection, medDoctorCodeAppointer,
                medDoctorCodeInjector, injectionTime', 'ambLogId, amount, medicationId,
                ambTaskId, methodOfApplication, description,
                medicationType, note, amountText,
                forThePurposeOfCupping, shownInjection, doctorAppointerId,
                doctorInjectorId, injectionTime' ); } public function updateWealths($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } for ($i = 0; $i < count($records); $i++){ $row = $records[$i]; $records[$i]->injectionTime = $this->createDateTime($row); } return updateSql($db, 'ambLogWealth', 'ambLogWealthCode', $records, "amount, matWealthCode, methodUse,
                description, note, amountText,
                forThePurposeOfCupping, shownInjection, medDoctorCodeAppointer,
                medDoctorCodeInjector, injectionTime", "amount, medicationId, methodOfApplication,
                description, note, amountText,
                forThePurposeOfCupping, shownInjection, doctorAppointerId,
                doctorInjectorId, injectionTime" ); } public function deleteWealths($records){ $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'ambLogWealth', 'ambLogWealthCode', $records); } private function createDateTime($row){ $date = isUndefinedProperty($row, 'injectionDate'); $time = isUndefinedProperty($row, 'injectionTime'); $noDate = '19000101'; $noTime = '00:00:05'; $_date = new DateTime(empty($date) ? $noDate : $date); $_time = new DateTime(empty($time) ? $noTime : $time); $ret = date($_date->format('Y-m-d') . ' ' . $_time->format('H:i:s')); if (empty($date) && empty($time)){ $ret = NULL; } return $ret; } public function clearWealths($ambLogId){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            DELETE FROM ambLogWealth
            WHERE ambLogCode = {$ambLogId}
EOT;
return executeSql($db, $sql); } } handleJsonRpc('med_ambulance_wealths');