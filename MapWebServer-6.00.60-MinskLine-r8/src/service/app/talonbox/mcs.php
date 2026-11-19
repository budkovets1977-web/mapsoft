<?php
 class mcs { const ERROR_NOT_AVAILABLE_DB = -2001; const MEDCOMISSION_MODE = 4; private $talonbox; private $db; private $cfg; public function __construct($talonbox) { $this->cfg = $talonbox->cfg; $this->talonbox = $talonbox; $this->getDb(); } private function getDb() { try { $db = $this->db ? : $this->db = connectDb(null, null, true); return $db; } catch (Throwable $e) { throw new Exception('Ошибка подключения к БД (тип map): ' . $e->getMessage(), self::ERROR_NOT_AVAILABLE_DB); } } public function ping() { $this->talonbox->doLog(self::class . ' [ping]', $this->talonbox::LOG_DBG); $res = new stdClass(); $db = $this->getDb(); $enterpriseId = getObjValueByPath($this->cfg, 'connect/map/currentEnterpriseId', 0); $this->talonbox->doLog(self::class . ' [ping] $enterpriseId=' . print_r($enterpriseId, true), $this->talonbox::LOG_DBG); $sql = <<<EOT
            SELECT
                COALESCE(t.cnt1, 0) cnt1,
                COALESCE(t.cnt2, 0) cnt2
            FROM (
                SELECT
                    COUNT(CASE COALESCE(t1.withoutControl, 0) WHEN 0 THEN 1 ELSE NULL END) cnt1,
                    COUNT(CASE COALESCE(t1.withoutControl, 0) WHEN 1 THEN 1 ELSE NULL END) cnt2
                FROM (
                    SELECT
                        mp.medProfessionCode,
                        mcc.withoutControl
                    FROM medProfession mp
                    JOIN medCouponControl mcc ON mp.medProfessionCode = mcc.medProfessionCode
                    WHERE mcc.flagSelfRecording = 1
                        AND EXISTS (
                            SELECT 1
                            FROM medDoctor md
                            JOIN department d ON d.departmentCode = md.departmentCode
                            JOIN branch b ON b.branchCode = d.branchCode
                            JOIN medCoupon mc ON mc.medDoctorCode = md.medDoctorCode
                                AND mc.onDate >= 'NOW'::DATE
                                AND mc.visitTime IS NOT NULL
                            WHERE md.medProfessionCode = mp.medProfessionCode
                                AND b.EnterpriseCode = :enterpriseId
                        )
                    GROUP BY mp.medProfessionCode, mcc.withoutControl
                    ) t1
                ) t
EOT;
$row = selectSql($db, $sql, [ ':enterpriseId' => $enterpriseId ]); $row = reset($row); return $res; } public function getProfessions(int $professionId, int $mode) { $this->talonbox->doLog(self::class . ' [getProfessions] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $mapConfig = getObjValueByPath($this->cfg, 'connect/map', null); $enterpriseId = isEmptyProperty($mapConfig, 'currentEnterpriseId', 0); $enterpriseStructureId = isEmptyProperty($mapConfig, 'enterpriseStructureId', 0); $unitCondition = ''; if ($enterpriseStructureId) { $unitCondition = " AND ES.EnterpriseStructureParentCode = $enterpriseStructureId"; } $sql = <<<EOT
            SELECT DISTINCT
                mp.medProfessionCode id,
                mp.name,
                COALESCE(
                    (
                        SELECT MIN(mpg.medPatientGroupCode)
                        FROM medDoctor md
                        JOIN department d ON d.departmentCode = md.departmentCode
                        JOIN branch b ON b.branchCode = d.branchCode
                        JOIN medPatientGroup mpg on mpg.medDoctorCode = md.medDoctorCode
                        WHERE md.medProfessionCode = mp.medProfessionCode
                            AND b.enterpriseCode = :enterpriseId
                    ), 0
                ) "groupId"
            FROM medProfession mp
            JOIN medCouponControl mcc ON mp.medProfessionCode = mcc.medProfessionCode
            WHERE mcc.flagSelfRecording = 1
                AND COALESCE(mcc.withoutControl, 0) = :mode
                AND EXISTS (
                    SELECT md.medDoctorCode
                    FROM medDoctor md
                    JOIN department d ON d.departmentCode = md.departmentCode
                    LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = MD.DepartmentCode
                    JOIN branch b ON b.branchCode = d.branchCode
                    JOIN medCoupon mc ON mc.medDoctorCode = md.medDoctorCode
                        AND mc.onDate >= 'NOW'::DATE
                        AND mc.visitTime IS NOT NULL
                    WHERE md.medProfessionCode = mp.medProfessionCode
                        AND b.enterpriseCode = :enterpriseId
                        $unitCondition
                    LIMIT 1
                )
                /*<--WS-249*/
                AND NOT EXISTS (
                    SELECT 1 FROM medCouponWorkProf
                    WHERE medProfessionCode = mp.medProfessionCode
                       AND medCouponWorkCode = mcc.medCouponWorkCode
                       AND medEqServiceCode IS NOT NULL
                )
               /*WS-249-->*/
            ORDER BY 2
EOT;
$rows = selectSql($db, $sql, [ ':enterpriseId' => $enterpriseId, ':mode' => $mode == self::MEDCOMISSION_MODE ? 1 : 0 ]); $res = []; foreach ($rows as $v) { if ($professionId == 0 || $professionId == $v['id']) { $res[] = arrayToObject($v, 'stdClass'); } } return $res; } public function getDoctors(int $professionId, int $mode, ?array $doctorIdList = null, ?int $structureId = null, ?int $territoryDoctorId = null) { $this->talonbox->doLog(self::class . ' [getDoctors] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $enterpriseStructureId = getObjValueByPath($this->cfg, 'connect/map/enterpriseStructureId', 0); $condition = ''; if ($doctorIdList) { $len = count($doctorIdList); $condition = ' AND md.medDoctorCode IN (' . $doctorIdList[0]; for($i = 1; $i < $len; $i++) { $condition .= ', ' . $doctorIdList[$i]; } $condition .= ')'; } if ($enterpriseStructureId) { $condition .= " AND ES.EnterpriseStructureParentCode = $enterpriseStructureId"; } if ($structureId) { $condition .= " AND ES.EnterpriseStructureCode = $structureId
                            AND MD.MedDoctorCode != $territoryDoctorId"; } $sql = <<<EOT
            SELECT
                MD.MedDoctorCode                "id",
                fn_cmFIO(MD.ManCode, 1)         "fio",
                fn_cmFIO(MD.ManCode)            "fullName",
                MIN(MCG.Cabinet)                "cabinet",
                MIN(ES.EnterpriseStructureCode) "structureId",
                MIN(ES.NamePath)                "structureName",
                (
                    SELECT STRING_AGG(Name::TEXT, ', ' ORDER BY Name)
                    FROM MedPatientGroup
                    WHERE MedDoctorCode = MD.MedDoctorCode
                )                           "sectors"
            FROM MedCouponControl MCC
            JOIN MedDoctor MD ON MD.MedProfessionCode = :professionId
            JOIN MedCouponGraph MCG ON MCG.MedDoctorCode = MD.MedDoctorCode AND MCG.OnDate >= 'now'::DATE
            LEFT JOIN MedCoupon MC ON MC.MedDoctorCode = MD.MedDoctorCode
            LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = MD.DepartmentCode
                AND MC.OnDate = MCG.OnDate
                AND COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode) = MCC.MedCouponWorkCode
                AND ((MC.MedPatientCode IS NULL AND COALESCE(MC.CouponState, 0) = 0) OR MC.CouponState IN (1, 2))
            WHERE MCC.MedProfessionCode = :professionId
                AND MCC.FlagSelfRecording = 1
                AND COALESCE(MCC.WithoutControl, 0) = :mode
                AND MC.VisitTime IS NOT NULL
                AND ES.NamePath IS NOT NULL
                $condition
            GROUP BY 1
            ORDER BY 6, 3
EOT;
$rows = selectSql($db, $sql, [ ':professionId' => $professionId, ':mode' => $mode == self::MEDCOMISSION_MODE ? 1 : 0 ]); $res = []; foreach ($rows as $v) { $res[] = arrayToObject($v, 'stdClass'); } return $res; } public function getCalendar(int $professionId, int $doctorId, int $patientId, int $mode) { $this->talonbox->doLog(self::class . ' [getCalendar] professionId=' . $professionId . ' doctorId=' . $doctorId . ' patientId=' . $patientId . ' mode=' . $mode, $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<SQL
            WITH t AS (
                SELECT
                    mc.onDate "onDate",
                    COUNT(CASE COALESCE(mc.couponState, 0) IN (0, 1, 2) WHEN TRUE THEN mc.medCouponCode END) "couponTotalCount",
                    COUNT(CASE COALESCE(mc.couponState, 0) IN (1, 2) WHEN TRUE THEN mc.medCouponCode END) "couponOrderedCount",
                    COUNT(CASE COALESCE(mc.couponState, 0) = 0 WHEN TRUE THEN mc.medCouponCode END) "couponFreeCount",
                    COUNT(CASE mc.medPatientCode = :patientId WHEN TRUE THEN mc.medCouponCode END) "myCouponCount",
                    MAX(fn_medCouponQuotaPercent(COALESCE(mc.medCouponWorkCode, mc.medCouponWorkCodeChange), md.medProfessionCode, mc.onDate)) * 0.01 "maxCouponQuota",
                    TO_CHAR(MIN(mc.visitTime), 'HH24:MI') "dayStart",
                    TO_CHAR(MAX(MC.VisitTime), 'HH24:MI') "dayFinish",
                    (ARRAY_AGG(MCW.CancelReason ORDER BY MC.OnDate))[1] "cancelReason"
                FROM medDoctor md
                JOIN medCouponGraph dcg ON dcg.medDoctorCode = md.medDoctorCode AND dcg.onDate BETWEEN 'now'::DATE AND DATEADD('month', 6, 'now'::DATE)
                JOIN MedCoupon MC ON MC.MedDoctorCode = MD.MedDoctorCode
                        AND MC.OnDate = DCG.OnDate
                        AND MC.VisitTime IS NOT NULL
                        AND COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode, 0) IN (
                            SELECT MedCouponWorkCode
                            FROM MedCouponControl
                            WHERE MedProfessionCode = MD.MedProfessionCode
                            AND FlagSelfRecording = 1
                            UNION ALL
                            SELECT MedCouponWorkCode
                            FROM MedCouponWork
                            WHERE FlagCancel = 1
                        )
                LEFT JOIN MedCouponWork MCW ON MCW.MedCouponWorkCode = MC.MedCouponWorkCodeChange
                        AND MC.CouponState = 3
                        AND :doctorId != 0
                WHERE (md.medDoctorCode = :doctorId OR :doctorId = 0)
                    AND (md.medProfessionCode = :professionId OR :professionId = 0)
                    AND CASE
                            WHEN MC.OnDate = 'NOW'::DATE THEN TO_CHAR(LOCALTIMESTAMP, 'HH24:MI') < TO_CHAR(MC.VisitTime, 'HH24:MI')
                            ELSE TRUE
                        END
                GROUP BY mc.onDate
                ORDER BY 1
            )
            SELECT
                t."onDate",
                t."couponFreeCount",
                CASE (ROUND(t."couponTotalCount" * t."maxCouponQuota", 0) - t."couponOrderedCount") > t."couponFreeCount"
                    WHEN TRUE THEN t."couponFreeCount"
                    WHEN FALSE THEN
                        CASE (ROUND(t."couponTotalCount" * t."maxCouponQuota", 0) - t."couponOrderedCount") < 0
                            WHEN TRUE THEN 0
                            WHEN FALSE THEN (ROUND(t."couponTotalCount" * t."maxCouponQuota", 0) - t."couponOrderedCount")
                        END
                END "couponFreeQuotaCount",
                t."myCouponCount",
                t."dayStart",
                t."dayFinish",
                T."cancelReason"
            FROM T
            SQL;
$rows = selectSql($db, $sql, [ ':professionId' => $professionId, ':doctorId' => $doctorId, ':patientId' => $patientId ]); $sql = <<<SQL
            SELECT REPLACE(TRIM(BasicTimetable, E'\n'), E'\n', '<br>') "basicTimetable"
            FROM MedDoctor
            WHERE MedDoctorCode = :doctorId
            SQL;
$timetable = selectSqlValue($db, $sql, [ ':doctorId' => $doctorId ]); $ret = new stdClass(); $ret->calendar = []; $ret->timetable = $timetable; foreach($rows as $v) { $ret->calendar[] = arrayToObject($v, 'stdClass'); } return $ret; } public function getCoupons(int $professionId, int $doctorId, string $onDate, int $patientId, int $mode) { $this->talonbox->doLog(self::class . ' [getCoupons] profCode=' . $professionId . ' docCode=' . $doctorId . ' onDate=' . $onDate . ' patientCode=' . $patientId . ' mode=' . $mode, $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<SQL
            SELECT
                STRING_AGG(mc.medCouponCode::TEXT, ',') id,
                TO_CHAR(mc.visitTime, 'HH24:MI') "visitTime",
                STRING_AGG((
                CASE (FN_medCouponCheckDocQuota('$onDate'::DATE, mc.medDoctorCode) = 0)
                    AND (CASE COALESCE(mc.medPatientCode, -1) = :patientId
                        WHEN TRUE THEN 1
                        WHEN FALSE THEN 0 END = 0)
                    WHEN TRUE THEN 4
                    WHEN FALSE THEN CASE COALESCE(mc.medPatientCode, -1) = :patientId
                        WHEN TRUE THEN 1
                        WHEN FALSE THEN 0 END
                END)::TEXT, ',') status,
                MAX(COALESCE(mc.selfRecording, 0)) "selfRecording",
                STRING_AGG(mc.medDoctorCode::TEXT, ',') "docList",
                STRING_AGG(FN_medCouponQuotaType(COALESCE(mc.medCouponWorkCode, mc.medCouponWorkCodeChange), md.medProfessionCode, '$onDate'::DATE)::TEXT, ',') "quotaTypeList"
            FROM medCoupon mc
            JOIN medDoctor md ON mc.medDoctorCode = md.medDoctorCode
            JOIN medCouponControl mcc ON mcc.medProfessionCode = md.medProfessionCode
                AND mcc.flagSelfRecording = 1
                AND COALESCE(mcc.withoutControl, 0) = :mode
            WHERE mc.onDate = '$onDate'::DATE
                AND ((mc.medPatientCode IS NULL AND (mc.couponState IS NULL OR mc.couponState = 0))
                    OR (:patientId = mc.medPatientCode AND mc.couponState IN (1, 2)))
                AND COALESCE(mc.medCouponWorkCodeChange, mc.medCouponWorkCode, 0) = mcc.medCouponWorkCode
                AND mc.visitTime IS NOT NULL
                AND (md.medProfessionCode = :professionId OR (:professionId = 0 AND :doctorId <> 0))
                AND (mc.medDoctorCode = :doctorId OR (:doctorId = 0 AND :professionId <> 0))
                AND CASE
                        WHEN MC.OnDate = 'NOW'::DATE THEN TO_CHAR(LOCALTIMESTAMP, 'HH24:MI') < TO_CHAR(MC.VisitTime, 'HH24:MI')
                        ELSE TRUE
                    END
            GROUP BY mc.VisitTime
            ORDER BY 2
            SQL;
$rows = selectSql($db, $sql, [ ':patientId' => $patientId, ':mode' => $mode == self::MEDCOMISSION_MODE ? 1 : 0, ':professionId' => $professionId, ':doctorId' => $doctorId ]); $res = []; foreach ($rows as $v) { $res[] = arrayToObject($v, 'stdClass'); } return $res; } public function authorize(string $surname, string $name, string $patronymic, string $birthDate, string $city, string $street, string $house, string $corpus, string $flat) { $this->talonbox->doLog(self::class . ' [authorize] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<SQL
            SELECT
                MP.MedPatientCode           "id",
                FN_cmFIO(MP.ManCode)        "fio",
                MP.NumberHistorySickness    "cardNumber",
                MPG.Name                    "sectorName",
                (
                    SELECT COALESCE(JSON_AGG(MPC.MedPrivelegeCode), '[]'::JSON)
                    FROM MedPatientCard MPC
                    WHERE MPC.MedPatientCode = MP.MedPatientCode
                )                           "medPrivilegeCodes"
            FROM MedPatient MP
            LEFT JOIN MAN M on M.ManCode = MP.ManCode
            LEFT JOIN MedPatientRegistration MPR on MPR.MedPatientCode = MP.MedPatientCode
            LEFT JOIN MedPatientGroup MPG on MPG.MedPatientGroupCode = MPR.MedPatientGroupCode
            LEFT JOIN ManPassport PASS on PASS.ManCode = MP.ManCode
            LEFT JOIN Address ADDR on ADDR.AddressCode = PASS.AddressCode
            LEFT JOIN Address ADDRR on ADDRR.AddressCode = PASS.AddressResideCode
            WHERE COALESCE(MP.FinishDate, '3000-01-01'::DATE) > CURRENT_DATE
                AND YEAR(PASS.DateOfBirth) = YEAR(:birthDate::DATE)
                AND MONTH(PASS.DateOfBirth) = MONTH(:birthDate::DATE)
                AND DAY(PASS.DateOfBirth) = DAY(:birthDate::DATE)
                AND (M.Surname LIKE :surname OR :surname = '')
                AND (M.Name LIKE :name OR :name = '')
                AND (M.SecondName LIKE :patronymic OR :patronymic = '')
                AND (COALESCE(NULLIF(ADDRR.Place, ''), NULLIF(ADDR.Place, ''), '') LIKE :city OR :city = '')
                AND (COALESCE(NULLIF(ADDRR.Street, ''), NULLIF(ADDR.Street, ''), '') LIKE :street OR :street = '')
                AND (COALESCE(NULLIF(PASS.AddressResideHouse, ''), NULLIF(PASS.AddressHouse, ''), '') LIKE :house OR :house = '')
                AND (COALESCE(NULLIF(PASS.AddressResideCorps, ''), NULLIF(PASS.AddressCorps, ''), '') LIKE :corpus OR :corpus = '')
                AND (COALESCE(NULLIF(PASS.AddressResideFlat, ''), NULLIF(PASS.AddressFlat, ''), '') LIKE :flat OR :flat = '')
            SQL;
$ret = selectSql($db, $sql, [ ':surname' => $surname, ':name' => $name, ':patronymic' => $patronymic, ':birthDate' => $birthDate, ':city' => $city, ':street' => $street, ':house' => $house, ':corpus' => $corpus, ':flat' => $flat ]); return $ret; } public function authorizeByBarcode(string $barcode) { $this->talonbox->doLog(self::class . ' [authorizeByBarcode] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<SQL
            SELECT
                MP.MedPatientCode           "id",
                FN_cmFIO(MP.ManCode)        "fio",
                MP.NumberHistorySickness    "cardNumber",
                MPG.Name                    "sectorName",
                (
                    SELECT COALESCE(JSON_AGG(MPC.MedPrivelegeCode), '[]'::json)
                    FROM MedPatientCard MPC
                    WHERE MPC.MedPatientCode = MP.MedPatientCode
                ) "medPrivilegeCodes"
            FROM MedPatient MP
            LEFT JOIN MedBarcode MB ON MB.ManCode = MP.ManCode
            LEFT JOIN MedPatientRegistration MPR ON MPR.MedPatientCode = MP.MedPatientCode
            LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = MPR.MedPatientGroupCode
            WHERE MB.Barcode = :barcode
                AND 'NOW'::DATE BETWEEN MB.StartDate AND COALESCE(MB.StopDate, 30000101)
            SQL;
$ret = selectSql($db, $sql, [ ':barcode' => $barcode ]); return $ret; } public function reserveCoupon(int $couponId, int $patientId, string $patientFio) { $this->talonbox->doLog(self::class . ' [reserveCoupon] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $errorCode = 0; if ($patientId) { $errorCode = $this->checkCoupon($couponId, $patientId); $this->talonbox->doLog(self::class . ' [reserveCoupon errorCode] ' . print_r($errorCode, true), $this->talonbox::LOG_DBG); } if ($errorCode >= 0) { try { $this->updateCoupon($couponId, 0, $patientId, $patientFio); } catch (Throwable $e) { $errorCode = $e->getCode(); $this->talonbox->doLog(self::class . ' [reserveCoupon updateCoupon] ' . print_r($e, true), $this->talonbox::LOG_DBG); throw $e; } } return $errorCode; } public function cancelCoupon(int $couponId, int $patientId) { $this->talonbox->doLog(self::class . ' [cancelCoupon] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $ret = $this->updateCoupon($couponId, 1); return $ret; } private function checkCoupon(int $couponId, int $patientId) { $this->talonbox->doLog(self::class . ' [checkCoupon] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $maxActiveCoupons = getObjValueByPath($this->cfg, 'maxActiveCoupons', 5); $sql = <<<EOT
                SELECT
                    CASE WHEN mcc.medCouponControlCode IS NULL THEN 0 ELSE 1 END "setupIsPresent",
                    CASE mc.onDate = 'now'::DATE WHEN TRUE THEN 1 WHEN FALSE THEN 0 END today,
                    FN_medCouponQuotaPercent(mcc.medCouponWorkCode, md.medProfessionCode, mc.onDate) "QPercent",
                    CASE (
                        EXISTS (
                            SELECT 1
                            FROM talonServicePrice tsp
                            JOIN talonService ts ON ts.talonServiceCode = tsp.talonServiceCode
                            WHERE COALESCE(tsp.active, 0) = 1
                                AND COALESCE(ts.active, 0) = 1
                                AND tsp.medProfessionCode = md.medProfessionCode
                                AND tsp.medCouponWorkCode = COALESCE(mc2.medCouponWorkCodeChange, mc2.medCouponWorkCode)
                        )
                    ) WHEN TRUE THEN 1 END "pFlag",
                    COUNT(CASE COALESCE(mc.couponState, 0) IN (0, 1, 2) AND mc.medDoctorCode = mc2.medDoctorCode
                            WHEN TRUE THEN mc.medCouponCode END) "totalCount",
                    COUNT(CASE mc.medPatientCode = :patientId AND
                            COALESCE(mc.couponState, 0) IN (1, 2)
                            WHEN TRUE THEN mc.medCouponCode END) "patientCount",
                    COUNT(CASE mc.medPatientCode IS NOT NULL AND mc.medDoctorCode = mc2.medDoctorCode
                            AND COALESCE(mc.couponState, 0) IN (1, 2)
                            AND COALESCE(mc.couponType, 0) = 1
                            WHEN TRUE THEN mc.medCouponCode END) "orderedCount",
                    COUNT(CASE COALESCE(mc.couponState, 0) IN (0) AND mc.medDoctorCode = mc2.medDoctorCode
                            WHEN TRUE THEN mc.medCouponCode END) "freeCount",
                    ROUND(
                        (
                            COUNT(
                                CASE COALESCE(mc.couponState, 0) IN (0, 1, 2) AND mc.medDoctorCode = mc2.medDoctorCode
                                WHEN TRUE THEN mc.medCouponCode END
                            )
                        ) * (
                            FN_medCouponQuotaPercent(mcc.medCouponWorkCode, md.medProfessionCode, mc.onDate)
                        ) * 0.01, 0
                    ) - (
                        COUNT(
                            CASE
                                -- mc.medPatientCode IS NOT NULL AND    -- Учитывать также пациентов не из картотеки
                                mc.medDoctorCode = mc2.medDoctorCode
                                AND COALESCE(mc.couponState, 0) IN (1, 2)
                                -- AND COALESCE(mc.couponType, 0) = 1   -- Квота на предварительную запись должна учитываться независимо от того, талон по самозаписи или нет
                            WHEN TRUE THEN mc.medCouponCode END
                        )
                    ) "restCount",
                    (
                        SELECT COUNT(*)
                        FROM medCoupon
                        WHERE medPatientCode = :patientId
                            AND selfRecording = 1
                            AND onDate >= 'now'::DATE
                            AND couponState IN (1, 2)
                    ) "activeCount"
                FROM medCoupon mc2
                JOIN medDoctor md ON md.medDoctorCode = mc2.medDoctorCode
                LEFT OUTER JOIN medCouponControl mcc ON mcc.medProfessionCode = md.medProfessionCode
                    AND mcc.flagSelfRecording = 1
                    AND mcc.medCouponWorkCode = COALESCE(mc2.medCouponWorkCodeChange, mc2.medCouponWorkCode)
                LEFT OUTER JOIN medDoctor md3 ON md3.medProfessionCode = mcc.medProfessionCode
                LEFT OUTER JOIN medCoupon mc ON mc.medDoctorCode = md3.medDoctorCode
                    AND mc.onDate = mc2.onDate
                    AND COALESCE(mc.medCouponWorkCodeChange, mc.medCouponWorkCode) = mcc.medCouponWorkCode
                WHERE mc2.medCouponCode = :couponId
                    AND (COALESCE(mc2.couponState, 0) = 0
                        OR (COALESCE(mc2.medPatientCode, 0) = :patientId AND COALESCE(mc2.couponState, 0) <> (0)))
                    AND mc.onDate IS NOT NULL
                GROUP BY 1, 2, 3, md.medProfessionCode, mc2.medCouponWorkCodeChange, mc2.medCouponWorkCode
EOT;
$ret = selectSql($db, $sql, [ ':couponId' => $couponId, ':patientId' => $patientId ]); $result = -999; if (count($ret) > 0) { $coupon = $ret[0]; $result = 0; if ($coupon['activeCount'] >= $maxActiveCoupons) { $result = -5; } else if ($coupon['totalCount'] == 0) { $result = -4; } else if ($coupon['patientCount'] > 0) { $result = -1; } else if ($coupon['setupIsPresent'] == 0) { $result = -2; } else if ($coupon['restCount'] <= 0) { $result = -3; } else if ($coupon['pFlag'] == 1) { $result = -10; } } return $result; } private function updateCoupon(int $couponId, int $cancelCoupon = 0, ?int $patientId = null, ?string $patientFio = null) { $this->talonbox->doLog(self::class . ' [updateCoupon] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $selfRecording = 1; $couponState = 1; $orderTime = date("Y-m-d H:i:s"); $couponType = 1; $cancelShortNumber = 0; if ($cancelCoupon) { $patientId = null; $patientFio = null; $selfRecording = null; $couponState = 0; $orderTime = null; $couponType = null; $cancelShortNumber = 1; } $sql = <<<EOT
                UPDATE medCoupon
                SET
                    medPatientCode = :patientId,
                    fio = :patientFio,
                    selfRecording = :selfRecording,
                    couponState = :couponState,
                    orderTime = :orderTime,
                    couponType = :couponType,
                    shortNumber = CASE WHEN :cancelShortNumber = 1 THEN NULL ELSE shortNumber END
                WHERE medCouponCode = :couponId
EOT;
$ret = selectSql($db, $sql, [ ':couponId' => $couponId, ':patientId' => $patientId, ':patientFio' => $patientFio, ':selfRecording' => $selfRecording, ':couponState' => $couponState, ':orderTime' => $orderTime, ':couponType' => $couponType, ':cancelShortNumber' => $cancelShortNumber ]); return 0; } public function getPatientCoupons(int $patientId, int $kioskNumber, bool $isFullList) { $this->talonbox->doLog(self::class . ' [getPatientCoupons] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $ret = new stdClass(); if (!$isFullList) { $sql = $sql = <<<SQL
                SELECT
                    MC.MedCouponCode                    "id",
                    MC.OnDate                           "visitDate",
                    DATEFORMAT(MC.VisitTime, 'hh:nn')   "visitTime",
                    MC.OrderTime                        "orderTime",
                    MC.MedDoctorCode                    "doctorId",
                    FN_cmFIO(MD.ManCode)                "doctorFio",
                    FN_cmFIO(MD.ManCode, 1)             "doctorFioShort",
                    MPR.Name                            "profession",
                    MPR.MedProfessionCode               "professionId",
                    MCG.Cabinet                         "cabinet",
                    MMF.MedMapFloorCode                 "floorId",
                    MMF.Name                            "floorName",
                    MMF.FloorMap                        "map",
                    MMFI.MedMapFloorItemCode            "itemId",
                    MMFI.Coordinates                    "coordinates",
                    MMFI.ShapeType                      "shapeType"
                FROM MedCoupon MC
                LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MC.MedDoctorCode
                JOIN MedCouponControl MCC ON (MCC.MedProfessionCode = MD.MedProfessionCode AND MCC.FlagSelfRecording = 1)
                LEFT JOIN MedProfession MPR ON MD.MedProfessionCode = MPR.MedProfessionCode
                LEFT JOIN MedCouponGraph MCG ON MCG.MedDoctorCode = MD.MedDoctorCode AND MCG.OnDate = MC.OnDate
                LEFT JOIN MedCabinet Cab ON Cab.Cabinet = MCG.Cabinet
                LEFT JOIN MedMapFloorItem MMFI ON MMFI.MedCabinetCode = Cab.MedCabinetCode AND MMFI.Active = 1
                LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
                WHERE MC.MedPatientCode = :patientId AND COALESCE(MC.CouponState, 0) IN (1, 2)
                    AND COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode, 0) = MCC.MedCouponWorkCode
                    AND MC.OnDate::DATE >= 'NOW'::DATE
                ORDER BY 2, 3
                LIMIT 5
                SQL;
} else { $sql = <<<SQL
                SELECT
                    MC.MedCouponCode                    "id",
                    MC.OnDate                           "visitDate",
                    DATEFORMAT(MC.VisitTime, 'hh:nn')   "visitTime",
                    MC.OrderTime                        "orderTime",
                    MC.MedDoctorCode                    "doctorId",
                    FN_cmFIO(MD.ManCode)                "doctorFio",
                    FN_cmFIO(MD.ManCode, 1)             "doctorFioShort",
                    MPR.Name                            "profession",
                    MPR.MedProfessionCode               "professionId",
                    MCG.Cabinet                         "cabinet",
                    MMF.MedMapFloorCode                 "floorId",
                    MMF.Name                            "floorName",
                    MMF.FloorMap                        "map",
                    MMFI.MedMapFloorItemCode            "itemId",
                    MMFI.Coordinates                    "coordinates",
                    MMFI.ShapeType                      "shapeType",
                    COALESCE(MCC.FlagSelfRecording, 0)  "selfRecording"
                FROM MedCoupon MC
                LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MC.MedDoctorCode
                LEFT JOIN MedCouponControl MCC ON (MCC.MedProfessionCode = MD.MedProfessionCode) AND (MCC.MedCouponWorkCode = COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode))
                LEFT JOIN MedProfession MPR ON MD.MedProfessionCode = MPR.MedProfessionCode
                LEFT JOIN MedCouponGraph MCG ON MCG.MedDoctorCode = MD.MedDoctorCode AND MCG.OnDate = MC.OnDate
                LEFT JOIN MedCabinet Cab ON Cab.Cabinet = MCG.Cabinet
                LEFT JOIN MedMapFloorItem MMFI ON MMFI.MedCabinetCode = Cab.MedCabinetCode AND MMFI.Active = 1
                LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
                WHERE MC.MedPatientCode = :patientId AND COALESCE(MC.CouponState, 0) IN (1, 2)
                    AND MC.OnDate::DATE >= 'NOW'::DATE
                    --
                    -- Только основные талоны, без ДОП талонов (у ДОП талонов время визита хранится отдельно)
                    AND MC.VisitTime IS NOT NULL
                ORDER BY 2, 3
                SQL;
} $ret->coupons = selectSql($db, $sql, [ ':patientId' => $patientId ]); $ret->kiosk = $this->getKioskData($kioskNumber); return $ret; } public function getKioskData(int $kioskNumber) { $this->talonbox->doLog(self::class . ' [getKioskData] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<SQL
            SELECT
                MMF.MedMapFloorCode "floorId",
                MMF.Name            "floorName",
                MMF.FloorMap        "map",
                MMFI.Coordinates    "coordinates"
            FROM MedMapFloorItem MMFI
            LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
            WHERE MMFI.Number = :kioskNumber
            SQL;
$kioskData = selectSql($db, $sql, [ ':kioskNumber' => $kioskNumber ]); $ret = reset($kioskData); return $ret; } public function getTerritoryDoctor(int $patientId, int $professionId) { $this->talonbox->doLog(self::class . ' [getTerritoryDoctor] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<EOT
                SELECT
                    medDoctorCode           "id",
                    fn_cmFIO(manCode, 1)    "fio",
                    fn_cmFIO(manCode)       "fullName",
                    cabinet                 "cabinet",
                    structure_id            "structureId",
                    structure_name          "structureName",
                    (
                        SELECT STRING_AGG(Name::TEXT, ', ' ORDER BY Name)
                        FROM MedPatientGroup
                        WHERE MedDoctorCode = s.MedDoctorCode
                    )                       "sectors",
                    (
                        SELECT count(medCouponCode)
                        FROM medCoupon
                        WHERE medDoctorCode = s.medDoctorCode
                            AND COALESCE(couponState, 0) = 0
                            AND onDate >= 'now'::DATE
                    )                       "availableTalons"
                FROM
                (
                    SELECT
                        mpg.medDoctorCode,
                        md.medProfessionCode,
                        mcc.medCouponWorkCode,
                        md.manCode,
                        md.cabinet,
                        ES.EnterpriseStructureCode    structure_id,
                        ES.NamePath                   structure_name
                    FROM medPatient mp
                    LEFT JOIN medPatientGroup mpg ON mp.medPatientGroupCode = mpg.medPatientGroupCode
                    LEFT JOIN medDoctor md ON mpg.medDoctorCode = md.medDoctorCode
                    LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = MD.DepartmentCode
                    JOIN medCouponControl mcc ON
                        (
                            md.medProfessionCode = mcc.medProfessionCode
                            AND mcc.flagSelfRecording = 1
                        )
                    WHERE medPatientCode = :patientId
                    UNION
                    SELECT
                        mpg1.medDoctorCode,
                        md1.medProfessionCode,
                        mcc1.medCouponWorkCode,
                        md1.manCode,
                        md1.cabinet,
                        ES.EnterpriseStructureCode    structure_id,
                        ES.NamePath                   structure_name
                    FROM medPatientAddGroup mp1
                    LEFT JOIN medPatientGroup mpg1 ON mp1.medPatientGroupCode = mpg1.medPatientGroupCode
                    LEFT JOIN medDoctor md1 ON mpg1.medDoctorCode = md1.medDoctorCode
                    LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = MD1.DepartmentCode
                    JOIN medCouponControl mcc1 ON
                        (
                            md1.medProfessionCode = mcc1.medProfessionCode
                            AND mcc1.flagSelfRecording = 1
                            AND COALESCE(mcc1.withoutControl, 0) = 0
                        )
                    WHERE mp1.medPatientCode = :patientId
                ) s
                WHERE medProfessionCode = :professionId
                LIMIT 1
EOT;
$ret = selectSql($db, $sql, [ ':patientId' => $patientId, ':professionId' => $professionId ]); return $ret; } public function getCouponDataByCode(int $code) { $this->talonbox->doLog(self::class . ' [getCouponDataByCode] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $sql = <<<EOT
                SELECT mc.medCouponCode id,
                        mc.onDate "visitDate",
                        TO_CHAR(mc.visitTime, 'HH24:MI') "visitTime",
                        mc.medDoctorCode "doctorId",
                        FN_cmFIO(md.manCode) "doctorFio",
                        mp.name "profession",
                        mcg.cabinet,
                        COALESCE(mc.medPatientCode, 0) "patientId"
                FROM medCoupon mc
                LEFT OUTER JOIN medDoctor md ON md.medDoctorCode = mc.medDoctorCode
                LEFT OUTER JOIN medProfession mp ON mp.medProfessionCode = md.medProfessionCode
                LEFT OUTER JOIN medCouponGraph mcg ON (mc.medDoctorCode = mcg.medDoctorCode AND mc.onDate = mcg.onDate)
                LEFT JOIN medEqQueueCoupon eq ON eq.medCouponCode = mc.medCouponCode
                WHERE mc.onDate >= 'now'::DATE AND mc.shortNumber = :code
                ORDER BY 2 DESC, 3 DESC
                LIMIT 1
EOT;
$ret = selectSql($db, $sql, [ ':code' => $code ]); return $ret; } public function getBuildingData() { $this->talonbox->doLog(self::class . ' [getBuildingData]', $this->talonbox::LOG_DBG); $ret = new stdClass(); $db = $this->getDb(); $sqlItems = <<<SQL
            SELECT
                MFI.MedMapFloorItemCode                 "id",
                MFI.Active                              "active",
                MFI.Description                         "description",
                MFI.Id                                  "jsonId",
                MFI.MedCabinetCode                      "cabinetId",
                MFI.Name                                "name",
                MFI.Type                                "type",
                MFI.Attributes                          "attributes",
                MFI.Coordinates                         "coordinates",
                MFI.Number                              "kioskNumber",
                MFI.ShapeType                           "shapeType",
                MF.Active                               "floorActive",
                MF.Description                          "floorDescription",
                MF.Name                                 "floorName",
                MF.Id                                   "floorJsonId",
                MB.EnterpriseCode                       "enterpriseId",
                MB.Name                                 "buildingName",
                MB.Id                                   "buildingJsonId",
                (COALESCE(MFI.Attributes, 0) & 1) = 1   "talonboxCoupon",       -- Есть возможность заказа талона к врачу
                (COALESCE(MFI.Attributes, 0) & 2) = 2   "equeueCoupon"          -- Есть возможность заказа талона в Электронную очередь
            FROM MedMapFloorItem MFI
            LEFT JOIN MedMapFloor MF ON MF.MedMapFloorCode = MFI.MedMapFloorCode
            LEFT JOIN MedMapBuilding MB ON MB.MedMapBuildingCode = MF.MedMapBuildingCode
            WHERE MFI.Active = 1
                AND MF.Active = 1
            ORDER BY MF.Position, MFI.Type, MFI.Name
            SQL;
$sqlFloors = <<<SQL
            SELECT
                MedMapFloorCode         "id",
                Description             "description",
                MedMapBuildingCode      "medMapBuildingCode",
                Name                    "name",
                Id                      "jsonId",
                FloorMap                "floorMap"
            FROM MedMapFloor
            WHERE Active = 1
            ORDER BY Position
            SQL;
$ret->items = selectSql($db, $sqlItems); $ret->floors = selectSql($db, $sqlFloors); return $ret; } public function getSearchResults($params) { $this->talonbox->doLog(self::class . ' [getSearchResults]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $ret = new stdClass(); $searchString = isEmptyProperty($params, 'searchString'); $kioskNumber = isEmptyProperty($params, 'kioskNumber'); $whereSearch = ''; if ($searchString) { $whereSearch = "AND (MMFI.Name LIKE '%$searchString%'
                OR DD.DoctorFio LIKE '%$searchString%'
                OR DD.DoctorProfessionName LIKE '%$searchString%')"; } $sql = <<<SQL
            SELECT
                MMFI.MedMapFloorItemCode                "id",
                MMFI.Id                                 "jsonId",
                MMFI.Name                               "floorItemName",
                DD.DoctorFio                            "doctorFio",
                DD.DoctorProfessionName                 "doctorProfessionName",
                MMF.MedMapFloorCode                     "floorId",
                MMF.Id                                  "floorJsonId",
                MMF.Name                                "floorName",
                MMF.FloorMap                            "map",
                MMFI.Coordinates                        "coordinates",
                MMFI.Type                               "floorItemType",
                MMFI.ShapeType                          "shapeType",
                (COALESCE(MMFI.Attributes, 0) & 1) = 1  "talonboxCoupon",       -- Есть возможность заказа талона к врачу
                (COALESCE(MMFI.Attributes, 0) & 2) = 2  "equeueCoupon"          -- Есть возможность заказа талона в Электронную очередь
            FROM MedMapFloorItem MMFI
            LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
            LEFT JOIN MedCabinet MC ON MC.MedCabinetCode = MMFI.MedCabinetCode
            LEFT JOIN (
                SELECT *
                FROM (
                    SELECT
                        COALESCE(NULLIF(MCGW.Cabinet, ''), NULLIF(MCG.Cabinet, ''), MD.Cabinet) cabinet,
                        FN_cmFio(MD.ManCode) doctorFio,
                        MP.Name doctorProfessionName
                    FROM MedCouponGraph MCG
                    LEFT JOIN MedCouponGraphWork MCGW ON MCGW.MedCouponGraphCode = MCG.MedCouponGraphCode
                    LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MCG.MedDoctorCode
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE MCG.OnDate = 'now'::DATE
                    UNION ALL
                    SELECT
                        MD.Cabinet,
                        FN_cmFio(MD.ManCode),
                        MP.Name
                    FROM MedDoctor MD
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE NULLIF(MD.Cabinet, '') != ''
                        AND 'now'::DATE BETWEEN MD.StartDate AND MD.FinishDate
                ) QQ
                GROUP BY 1, 2, 3
            ) DD ON DD.Cabinet = MC.Cabinet
            WHERE MMFI.Active = 1
                $whereSearch
            ORDER BY MMF.Name, MMFI.Name
            SQL;
$ret->searchResults = selectSql($db, $sql); $ret->kiosk = $this->getKioskData($kioskNumber); return $ret; } public function readCabinetDoctors($params = null) { $this->talonbox->doLog(self::class . ' [readCabinetDoctors]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $cabinetId = isEmptyProperty($params, 'cabinetId'); $where = ''; if ($cabinetId) { $where = "WHERE QQ.Cabinet LIKE (SELECT Cabinet FROM MedCabinet WHERE MedCabinetCode = $cabinetId)"; } $sql = <<<SQL
            SELECT
                ROW_NUMBER() OVER (ORDER BY DD.Cabinet)     "id",
                DD.Cabinet                                  "cabinet",
                FN_cmFio(DD.DoctorPersonId, 1)              "doctorShortFio",
                FN_cmFio(DD.DoctorPersonId)                 "doctorFio",
                DD.DoctorProfessionName                     "doctorProfessionName",
                DD.DoctorId                                 "doctorId",
                REPLACE(DD.BasicTimetable, E'\n', '<br>')   "basicTimetable",
                SC.Sectors                                  "sectors",
                COALESCE(CC.FreeCouponCount, 0)             "freeCouponCount"
            FROM (
                SELECT * FROM (
                    SELECT
                        COALESCE(NULLIF(MCGW.Cabinet, ''), NULLIF(MCG.Cabinet, ''), MD.Cabinet) cabinet,
                        MD.ManCode doctorPersonId,
                        MP.Name doctorProfessionName,
                        MD.MedDoctorCode doctorId,
                        TRIM(MD.BasicTimetable, E'\n') basicTimetable
                    FROM MedCouponGraph MCG
                    LEFT JOIN MedCouponGraphWork MCGW ON MCGW.MedCouponGraphCode = MCG.MedCouponGraphCode
                    LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MCG.MedDoctorCode
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE MCG.OnDate = 'now'::DATE
                    UNION ALL
                    SELECT
                        MD.Cabinet,
                        MD.ManCode,
                        MP.Name,
                        MD.MedDoctorCode,
                        TRIM(MD.BasicTimetable, E'\n')
                    FROM MedDoctor MD
                    LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MD.MedProfessionCode
                    WHERE NULLIF(MD.Cabinet, '') != ''
                        AND 'now'::DATE BETWEEN MD.StartDate AND MD.FinishDate
                    ORDER BY Cabinet
                ) QQ
                $where
                GROUP BY 1, 2, 3, 4, 5
            ) DD
            LEFT JOIN (
                -- Получаем участки участкового врача и группируем их
                SELECT
                    QQ.MedDoctorCode doctorId,
                    STRING_AGG(qq.Address, E'<br><br>') sectors
                FROM (
                    SELECT
                        MD.MedDoctorCode,
                        E'<b>' || MPG.Name || E'</b><br>' ||
                        CASE WHEN MPLA.PlaceType IS NOT NULL THEN
                            STRING_AGG(
                                MPLA.PlaceType
                                || MPLA.Place || ', '
                                || MPLA.StreetType
                                || MPLA.Street
                                || CASE WHEN MPLA.House IS NOT NULL THEN ': ' END
                                || MPLA.House, E';<br>' ORDER BY MPLA.Place, MPLA.Street) || E';'
                        END ADDRESS
                    FROM MedPatientGroup MPG
                    LEFT JOIN MedPatientLocation MPL ON MPL.MedPatientGroupCode = MPG.MedPatientGroupCode
                    LEFT JOIN MedPatientLocationAddress MPLA ON MPLA.MedPatientLocationCode = MPL.MedPatientLocationCode
                    LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MPG.MedDoctorCode
                    GROUP BY MPG.Name, MD.MedDoctorCode, MPLA.PlaceType
                    ORDER BY MD.MedDoctorCode
                ) QQ
                GROUP BY QQ.MedDoctorCode
            ) SC ON SC.DoctorId = DD.DoctorId
            LEFT JOIN (
                SELECT
                    COUNT(MC.MedCouponCode) freeCouponCount,
                    MC.MedDoctorCode doctorId
                FROM MedCoupon MC
                JOIN MedDoctor MD ON MD.MedDoctorCode = MC.MedDoctorCode
                JOIN MedCouponControl MCC ON MCC.MedProfessionCode = MD.MedProfessionCode AND MCC.FlagSelfRecording = 1
                WHERE MC.OnDate >= 'now'::DATE
                    AND (MC.MedPatientCode IS NULL AND (MC.CouponState IS NULL OR MC.CouponState = 0))
                    AND COALESCE(MC.MedCouponWorkCodeChange, MC.MedCouponWorkCode, 0) = MCC.MedCouponWorkCode
                    AND MC.VisitTime IS NOT NULL
                    AND CASE
                            WHEN MC.OnDate = 'now'::DATE THEN TO_CHAR(LOCALTIMESTAMP, 'HH24:MI') < TO_CHAR(MC.VisitTime, 'HH24:MI')
                            ELSE TRUE
                        END
                GROUP BY 2
            ) CC ON CC.DoctorId = DD.DoctorId
            ORDER BY FN_cmFio(DD.DoctorPersonId)
            SQL;
$ret = $cabinetId ? selectSql($db, $sql) : []; return $ret; } public function readCabinetEqueueServices($params = null) { $this->talonbox->doLog(self::class . ' [readCabinetEqueueServices]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $cabinetId = isEmptyProperty($params, 'cabinetId'); $sql = <<<SQL
            SELECT
                ROW_NUMBER() OVER (ORDER BY EQC.ServiceId)  "id",
                EQC.ServiceId                               "serviceId",
                EQC.ServiceName                             "serviceName",
                EQC.CouponsNumber                           "couponsNumber"
            FROM (
                SELECT
                    MES.MedEqServiceCode serviceId,
                    MES.Name serviceName,
                    TA.CouponsNumber couponsNumber
                FROM MedEqService MES
                LEFT JOIN MedEqOwp MEO ON MEO.MedEqServiceCode = MES.MedEqServiceCode
                LEFT JOIN (
                    SELECT
                        COUNT(*) CouponsNumber,
                        MedEqServiceCode
                    FROM MedEqQueueCoupon
                    WHERE State IN (0, -1)
                    GROUP BY MedEqServiceCode
                ) TA ON TA.MedEqServiceCode = MES.MedEqServiceCode
                WHERE MEO.MedCabinetCode = :cabinetId
                GROUP BY 1, 2, 3
            ) EQC
            ORDER BY EQC.ServiceName
            SQL;
$ret = $cabinetId ? selectSql($db, $sql, [ ':cabinetId' => $cabinetId ]) : []; return $ret; } public function getEqueueCoupon($params = null) { $this->talonbox->doLog(self::class . ' [getEqueueCoupon]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $serviceId = isEmptyProperty($params, 'serviceId'); $categoryId = isEmptyProperty($params, 'categoryId'); $logStudyId = isEmptyProperty($params, 'logStudyId'); $patientId = isEmptyProperty($params, 'patientId'); $talonboxKioskNumber = isEmptyProperty($params, 'talonboxKioskNumber'); $ret = null; $sql = <<<SQL
            SELECT MedEqInfoboxCode
            FROM MedMapFloorItem
            WHERE TRIM(Number) = TRIM(?)
            LIMIT 1
            SQL;
$infoboxId = selectSqlValue($db, $sql, [ $talonboxKioskNumber ]); if (!$infoboxId) { $ret['id'] = -999; return $ret; } $sql = <<<SQL
            SELECT FN_medEqGetNewCouponCode(?, ?, ?, ?, ?, ?, ?) id
            SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $patientId, $logStudyId, $serviceId, null, $categoryId, $infoboxId, null ]); $ret = $stmt->fetch(); $sql = <<<SQL
            SELECT
                MEC.CreateTime                  "createTime",
                TRIM(MEC.DocumentNumber)        "couponNumber",
                MES.Name                        "serviceName",
                MEC.MedEqPatientCategoryCode    "categoryId",
                MEP.Name                        "categoryName"
            FROM MedEqQueueCoupon MEC
            LEFT JOIN MedEqService MES ON MES.MedEqServiceCode = MEC.MedEqServiceCode
            LEFT JOIN MedEqPatientCategory MEP ON MEP.MedEqPatientCategoryCode = MEC.MedEqPatientCategoryCode
            WHERE MedEqQueueCouponCode = ?
            SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $ret['id'] ]); $couponData = $stmt->fetch(); if ($couponData) { $ret['couponCreateTime'] = $couponData['createTime']; $ret['couponNumber'] = $couponData['couponNumber']; $ret['serviceName'] = $couponData['serviceName']; $ret['categoryName'] = $couponData['categoryName']; } $sql = <<<SQL
            SELECT COUNT(*) "couponsTotal"
            FROM MedEqQueueCoupon
            WHERE MedEqServiceCode = ?
                AND State IN (0, -1)
            SQL;
$stmt = $db->prepare($sql); $stmt->execute([ $serviceId ]); $couponData = $stmt->fetch(); if ($couponData) { $ret['couponsTotal'] = $couponData['couponsTotal']; } return $ret; } public function changeEqueueCouponState($params = null) { $this->talonbox->doLog(self::class . ' [changeEqueueCouponState]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $couponId = isEmptyProperty($params, 'couponId'); $state = isEmptyProperty($params, 'state'); $sql = <<<SQL
            UPDATE MedEqQueueCoupon
            SET State = :state
            WHERE MedEqQueueCouponCode = :couponId
            SQL;
selectSql($db, $sql, [ ':couponId' => $couponId, ':state' => $state ]); return true; } public function checkEqueueBarcode($params = null) { $this->talonbox->doLog(self::class . ' [checkEqueueBarcode]', $this->talonbox::LOG_DBG); $db = $this->getDb(); $enterpriseId = getObjValueByPath($this->cfg, 'connect/map/currentEnterpriseId', 0); $barcode = isEmptyProperty($params, 'barcode'); $sql = 'SELECT FN_MedEqBarCodeCheck(?, ?) "equeueData"'; $stmt = $db->prepare($sql); $stmt->execute([ $barcode, $enterpriseId ]); $ret = fetchValue($stmt, 'equeueData'); return $ret; } public function getPatientOrders(int $patientId, int $kioskNumber, $ordersValidityMonths) { $this->talonbox->doLog(self::class . ' [getPatientOrders] ' . print_r(func_get_args(), true), $this->talonbox::LOG_DBG); $db = $this->getDb(); $ret = new stdClass(); $doctorOrdersSql = <<<SQL
            -- Отображаются направления, добавленные для врача:
            -- * со специальностью, для которой добавлена связь услуги электронной очереди
            --   (Электронная очередь / Справочники / Услуги очереди / Услуга / Вид работ) и вида работ соответствующей
            --   специальности - для кабинетов с приемом по ЭО
            -- * со специальностью, для которой указан признак Назначение для инфокиоска в справочнике Специальности
            --   (Интерактивная карта / Справочники / Сотрудники / Специальности) - для кабинетов с приемом по "живой очереди"
            SELECT
                'order-doctor'                                          "type",
                MLS.DocumentDate                                        "date",
                MLS.MedLogSendCode                                      "id",
                MP.Name                                                 "title",
                -- Кабинет берется из ЭО, но если врач принимает не по ЭО, то из графика врача, иначе - из карточки врача
                TO_JSON(
                    ARRAY_REMOVE(
                        COALESCE(
                            -- ... из ЭО
                            ARRAY_AGG(DISTINCT MCEQ.MedCabinetCode) FILTER (WHERE MCEQ.MedCabinetCode IS NOT NULL),
                            -- ... из графика врача (если включено "Назначение для инфобокса")
                            CASE WHEN MP.DisplayInInfobox = 1 THEN ARRAY_AGG(DISTINCT MCDG.MedCabinetCode) FILTER (WHERE MCDG.MedCabinetCode IS NOT NULL) ELSE NULL END,
                            -- ... из карточки врача (если включено "Назначение для инфобокса")
                            CASE WHEN MP.DisplayInInfobox = 1 THEN ARRAY_AGG(DISTINCT MCD.MedCabinetCode) FILTER (WHERE MCD.MedCabinetCode IS NOT NULL) ELSE NULL END
                        ),
                        NULL
                    )
                ) "cabinetIds"
            FROM
                -- Направления
                MedLogSend MLS
                --
                -- Специальности врачей
                LEFT JOIN MedProfession MP ON MP.MedProfessionCode = MLS.MedProfessionCode
                --
                -- Врачи специальностей
                LEFT JOIN MedDoctor MPD ON
                    (MPD.MedProfessionCode = MP.MedProfessionCode) AND
                    -- Не уволенные врачи
                    ((MPD.FinishDate IS NULL) OR (MPD.FinishDate >= CURRENT_DATE))
                --
                -- Связь с услугой ЭО
                LEFT JOIN MedCouponWorkProf MCWP ON MCWP.MedProfessionCode = MP.MedProfessionCode
                --
                -- Кабинеты ЭО
                LEFT JOIN MedEqOWP EQOWP ON (EQOWP.MedEqServiceCode = MCWP.MedEqServiceCode) AND (EQOWP.Active = 1)
                --
                -- График работы врачей
                LEFT JOIN MedCouponGraph MCG ON (MCG.MedDoctorCode = MPD.MedDoctorCode) AND (MCG.OnDate >= 'now'::DATE)
                --
                -- Кабинеты
                -- ... из ЭО
                LEFT JOIN MedCabinet MCEQ ON (MCEQ.MedCabinetCode = EQOWP.MedCabinetCode) AND (MCEQ.Active = 1)
                -- ... из графика работа врача
                LEFT JOIN MedCabinet MCDG ON (MCDG.Cabinet = MCG.Cabinet) AND (MCDG.Active = 1)
                -- ... из карточки врача
                LEFT JOIN MedCabinet MCD ON (MCD.Cabinet = MPD.Cabinet) AND (MCD.Active = 1)
            WHERE
                -- Пациент
                (MLS.MedPatientCode = :patientId) AND
                --
                -- "Консультация специалиста" или "Другие обследования"
                (MLS.SendType IN (1, 5)) AND
                --
                -- Дата формирования направления НЕ старше, чем "срок годности" назначений
                (MLS.DocumentDate >= :docDate) AND
                --
                (
                    (
                        -- Связь услуги ЭО со специальностью
                        MCWP.MedEqServiceCode IS NOT NULL
                    ) OR (
                        -- Специальность с признаком "Назначение для инфокиоска"
                        MP.DisplayInInfobox = 1
                    )
                ) AND
                --
                -- Для пациента НЕТ посещения врача соответствующей специальности с датой не ранее
                -- даты формирования направления
                MLS.MedPatientCode NOT IN (
                    SELECT MVP.MedPatientCode
                    FROM
                        MedVisitPatient MVP
                    LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
                    WHERE
                        (MV.MedProfessionCode = MP.MedProfessionCode) AND
                        (MV.VisitDate >= MLS.DocumentDate)
                    GROUP BY
                        MVP.MedVisitPatientCode
                ) AND
                --
                -- Для пациента НЕ заказан талон к врачу соответствующей специальности c датой талона не
                -- ранее даты формирования направления
                MLS.MedProfessionCode NOT IN (
                    SELECT
                        MP.MedProfessionCode
                    FROM
                        MedProfession MP
                    LEFT JOIN MedDoctor MD ON MD.MedProfessionCode = MP.MedProfessionCode
                    LEFT JOIN MedCoupon MC ON MC.MedDoctorCode = MD.MedDoctorCode
                    WHERE
                        (MC.MedPatientCode = MLS.MedPatientCode) AND
                        (MC.OnDate >= MLS.DocumentDate)
                )
            --
            GROUP BY
                MLS.MedLogSendCode,
                MP.MedProfessionCode
            SQL;
$studyOrdersSql = <<<SQL
            SELECT
                'order-study'                                           "type",
                DATE(MLS.DirTime)                                       "date",
                MLS.MedLogStudyCode                                     "id",
                MS.Name                                                 "title",
                TO_JSON(ARRAY_REMOVE(ARRAY_AGG(DISTINCT MEQOWP.MedCabinetCode), NULL)) "cabinetIds"
            FROM
                MedLogStudy MLS
                LEFT JOIN MedStudy MS ON MS.MedStudyCode = MLS.MedStudyCode
                LEFT JOIN MedEqStudyLink MEQSL ON MEQSL.MedStudyCode = MS.MedStudyCode
                LEFT JOIN MedEqOWP MEQOWP ON MEQOWP.MedEqServiceCode = MEQSL.MedEqServiceCode
            WHERE
                -- Пациент
                (MLS.MedPatientCode = :patientId) AND
                --
                -- Дата формирования направления НЕ старше, чем "срок годности" назначений
                (MLS.DirTime >= :docDate) AND
                --
                -- Исключаются направления на исследования, для которых задано заключение
                (MLS.State <> 2) AND
                --
                -- Направления сформированные на шаблон документа направления,
                -- для которого добавлена связь с услугой ЭО
                (MEQSL.MedEqStudyLinkCode IS NOT NULL)
            GROUP BY
                MLS.MedLogStudyCode, MS.Name
            SQL;
$examOrdersSql = <<<SQL
            SELECT
                'order-exam'      "type",
                MF.OnDate         "date",
                MF.MedFluorCode   "id",
                MPT.Name          "title",
                TO_JSON(ARRAY_REMOVE(ARRAY_AGG(DISTINCT MPTC.MedCabinetCode), NULL)) "cabinetIds"
            FROM
                MedFluor AS MF
                LEFT JOIN MedPreventType MPT ON MPT.MedPreventTypeCode = MF.MedPreventTypeCode
                LEFT JOIN MedPreventTypeCabinet MPTC ON MPTC.MedPreventTypeCode = MF.MedPreventTypeCode
            WHERE
                -- Пациент
                (MF.MedPatientCode = :patientId) AND
                --
                -- Дата формирования направления НЕ старше, чем "срок годности" назначений
                (MF.OnDate >= :docDate) AND
                --
                -- Направления, сформированные на профилактику, для которой задан признак "Назначение для инфокиоска"
                (MPT.DisplayInInfobox = 1) AND
                --
                -- Исключаются направления, для которых задано заключение
                (MF.Conclusion IS NULL)
            GROUP BY
                MF.MedFluorCode, MPT.Name
            SQL;
$sql = <<<SQL
            ( $doctorOrdersSql )
            UNION ALL
            ( $studyOrdersSql )
            UNION ALL
            ( $examOrdersSql )
            ORDER BY "date" ASC, "title" ASC
            SQL;
$docDate = date('Y-m-d', strtotime('-' . $ordersValidityMonths . ' months', time())); $orders = selectSql($db, $sql, [ ':patientId' => $patientId, ':docDate' => $docDate, ]); $cabinetIds = []; foreach ($orders as &$orderRow) { $cabs = json_decode($orderRow['cabinetIds']); if (!empty($cabs)) { $orderRow['cabinetIds'] = $cabs; $cabinetIds = array_merge($cabinetIds, $cabs); } else { $orderRow['cabinetIds'] = []; } } if (!empty($cabinetIds)) { $cabinetSqlIn = createSqlInCondition($cabinetIds); $cabinetSql = <<<SQL
                SELECT
                    MC.*,
                    MMF.MedMapFloorCode                 "mapFloorId",
                    MMF.Name                            "mapFloorName",
                    MMF.FloorMap                        "map",
                    MMFI.MedMapFloorItemCode            "mapItemId",
                    MMFI.Coordinates                    "mapCoordinates",
                    MMFI.ShapeType                      "mapShapeType"
                FROM
                    (
                        SELECT
                            MC.MedCabinetCode                           "id",
                            MC.Cabinet                                  "name",
                            BOOL_OR(EQOWP.MedCabinetCode IS NOT NULL)   "hasQueue",
                            (
                                SELECT
                                    COUNT(MEQC.*)
                                FROM
                                    MedEqQueueCoupon MEQC
                                WHERE
                                    (MEQC.MedEqServiceCode = MIN(EQOWP.MedEqServiceCode)) AND
                                    (MEQC.State IN (0, -1))
                            )                                           "queueCount",
                            CASE
                                WHEN BOOL_AND(MEOP.MedEqOWPperiodCode IS NULL) THEN 'not-working'
                                ELSE NULL
                            END                                         "statusCode"
                        FROM
                            MedCabinet MC
                            LEFT JOIN MedEqOWP EQOWP ON EQOWP.MedCabinetCode = MC.MedCabinetCode
                            LEFT JOIN MedEqOWPperiod AS MEOP ON MEOP.MedEqOWPCode = EQOWP.MedEqOWPCode
                                AND (CLOCK_TIMESTAMP()::TIMESTAMP) BETWEEN MEOP.StartTime AND MEOP.FinishTime
                                AND MEOP.StartTime >= (DATE((CLOCK_TIMESTAMP()::TIMESTAMP)))
                        WHERE
                            MC.MedCabinetCode $cabinetSqlIn
                        GROUP BY
                            MC.MedCabinetCode
                    ) AS MC
                    LEFT JOIN MedMapFloorItem MMFI ON MMFI.MedCabinetCode = MC.id AND MMFI.Active = 1
                    LEFT JOIN MedMapFloor MMF ON MMF.MedMapFloorCode = MMFI.MedMapFloorCode
                SQL;
$_cabinets = selectSql($db, $cabinetSql); $cabinets = []; foreach ($_cabinets as $_cabRow) { $cabinets[$_cabRow['id']] = [ 'id' => $_cabRow['id'], 'name' => $_cabRow['name'], 'hasQueue' => $_cabRow['hasQueue'], 'queueCount' => $_cabRow['queueCount'], 'statusCode' => $_cabRow['statusCode'], 'mapInfo' => [ 'floorId' => $_cabRow['mapFloorId'], 'floorName' => $_cabRow['mapFloorName'], 'map' => $_cabRow['map'], 'itemId' => $_cabRow['mapItemId'], 'coordinates' => $_cabRow['mapCoordinates'], 'shapeType' => $_cabRow['mapShapeType'], ], ]; } } else { $cabinets = []; } foreach ($orders as &$order) { $order['cabinets'] = array_map(function($id) use($cabinets) { return $cabinets[$id]; }, $order['cabinetIds']); usort($order['cabinets'], function($a, $b) { return ($a['queueCount'] == $b['queueCount']) ? ($a['name'] <=> $b['name']) : ($a['queueCount'] <=> $b['queueCount']); }); unset($order['cabinetIds']); } $ret->orders = $orders; return $ret; } public function getCouponShortNumberByVisitDateAndDoctor($params) { $couponId = isUndefinedProperty($params, 'couponId', 0); $visitDate = isUndefinedProperty($params, 'visitDate', null); $doctorId = isUndefinedProperty($params, 'doctorId', 0); $db = $this->getDb(); if (!$visitDate || !$doctorId || !$couponId) { return null; } $sql = <<<SQL
            SELECT M.MedCouponCode "medCouponCode"
            FROM MedCoupon M
            WHERE onDate = :visitDate
              AND medDoctorCode = :doctorId
              AND onDate IS NOT NULL
            ORDER BY visitTime
        SQL;
$coupons = selectSql($db, $sql, [ ':visitDate' => $visitDate, ':doctorId' => $doctorId ]); foreach ($coupons as $index => $row) { if ((int)$row['medCouponCode'] === (int)$couponId) { return $index + 1; } } return null; } }