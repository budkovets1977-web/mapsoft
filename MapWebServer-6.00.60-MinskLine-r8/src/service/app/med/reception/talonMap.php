<?php
require_once(__DIR__ . '/../../init.php'); class med_reception_talonMap { private $db; const MAIN_VIEW = 'main'; const PATIENT_VIEW = 'patient'; const DOCTOR_VIEW = 'doctor'; const FIRST_SHIFT_START = "'07:00'"; const FIRST_SHIFT_FINISH = "'14:00'"; const SECOND_SHIFT_START = "'14:00'"; const SECOND_SHIFT_FINISH = "'20:00'"; const ERROR_ALREADY_BOOKED_MESSAGE = 'Талон уже забронирован!'; const ERROR_ALREADY_BOOKED_CODE = -11; const ERROR_BOOKING_TALON_DELETED_MESSAGE = 'Заказываемый талон не найден!'; const ERROR_BOOKING_TALON_DELETED_CODE = -12; const BOOKING_TIME_MINUTES = 5; const UPDATE_TALONMAP_NOTICE = 'medreception-talonmap-update'; public function readTalonGridData($params = null) { $viewType = isEmptyProperty($params, 'viewType'); $this->doCancelBookTalon(); switch($viewType) { case self::MAIN_VIEW: return $this->readMainViewData($params); case self::PATIENT_VIEW: return $this->readPatientViewData($params); case self::DOCTOR_VIEW: return $this->readDoctorViewData($params); } return false; } public function readMainViewData($params = null) { $db = $this->db ?: $this->db = connectDb(); $ret = new stdClass(); $page = isUndefinedProperty($params, 'page'); $limit = isUndefinedProperty($params, 'numberOfColumns'); $usePages = isUndefinedProperty($params, 'usePages'); $offset = 0; if ($page > 0) { $offset = $page * $limit; } $sqlParamsDoctor = []; $sqlParamsCoupon = []; $filter = isEmptyProperty($params, 'filter'); $filterDoctorSql = ""; $filterCouponSql = ""; $procedureId = isEmptyProperty($params, 'procedureId'); $ids = isEmptyProperty($params, 'ids'); if ($ids) { $ids = createSqlInCondition($ids); $filterCouponSql = "c.medCouponCode $ids"; } if ($filter) { $this->constructMainViewFilter($filter, $sqlParamsDoctor, $sqlParamsCoupon, $filterDoctorSql, $filterCouponSql, $procedureId); } $sqlDoctorFrom = <<<SQL
            FROM medCouponGraph g
            LEFT JOIN medCouponGraphWork gw on gw.medCouponGraphCode = g.medCouponGraphCode
            LEFT JOIN medDoctor d on d.medDoctorCode = g.medDoctorCode
            LEFT JOIN medProfession mp on mp.medProfessionCode = d.medProfessionCode
            LEFT JOIN medPatientGroup pg ON pg.medPatientGroupCode = d.medPatientGroupCode
            WHERE $filterDoctorSql
            SQL;
$sql = <<<SQL
            SELECT DISTINCT
                NULLIF(REGEXP_REPLACE(COALESCE(NULLIF(gw.cabinet, ''), g.cabinet, d.cabinet), '\D', '', 'g'), '')::INT "cabinetSort",
                g.medDoctorCode "doctorId",
                COALESCE(NULLIF(gw.cabinet, ''), g.cabinet, d.cabinet) "cabinet",
                FN_cmFIO(d.manCode, 1)::TEXT "doctorFio",
                mp.name "profession",
                pg.Name "sectorName"
            $sqlDoctorFrom
            SQL;
$sqlTotal = <<<SQL
            SELECT COUNT(*) "total" FROM ($sql) tab
            SQL;
$sql .= " ORDER BY 1, 2"; if ($usePages) { $sql .= " OFFSET $offset LIMIT $limit"; } $columns = selectSql($db, $sql, $sqlParamsDoctor); $doctorsIds = createSqlInCondition($columns, 'doctorId'); $cityInAddress = isUndefinedProperty($params, 'cityInAddress'); $typeOut = $cityInAddress ? 0 : 3; $sqlRecords = <<<SQL
            SELECT
                c.medCouponCode                             "id",
                c.onDate                                    "onDate",
                COALESCE(c.visitTime, ca.visitTime)         "visitTime",
                CASE WHEN c.visitTime IS NULL THEN 1 ELSE 0 END
                                                            "isExtraTalon",
                ca.visitTime                                "extraTalonVisitTime",
                c.medDoctorCode                             "doctorId",
                c.medPatientCode                            "patientId",
                p.manCode                                   "patientManId",
                c.couponState                               "state",
                c.couponType                                "couponType",
                COALESCE(c.selfRecording, 0)                "selfRecording",
                c.orderTime                                 "orderTime",
                c.giveTime                                  "giveTime",
                c.fio                                       "fio",
                pg.Name                                     "sectorName",
                pg2.Name                                    "doctorSectorName",
                mp.Name                                     "doctorProfession",
                (SELECT STRING_AGG((CONCAT(E', ', CASE
                        WHEN mpn.phoneType = 1 THEN 'д'
                        WHEN mpn.phoneType = 2 THEN 'р'
                        WHEN mpn.phoneType = 3 THEN 'м'
                        WHEN mpn.phoneType = 4 THEN 'п'
                        ELSE NULL
                        END, E'.', mpn.phoneNumber))::TEXT, E'')
                    FROM manphone mpn
                    WHERE mpn.manCode = p.manCode)          "contact",
                c.note                                      "note",
                COALESCE(ca.cabinet, (SELECT COALESCE(NULLIF(mpgw.cabinet, ''), NULLIF(mpg.cabinet, ''))
                            FROM medCouponGraph mpg
                            LEFT JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                                AND mpgw.startTime <= c.visitTime AND mpgw.finishTime > c.visitTime
                            WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                            LIMIT 1), doc.cabinet)          "cabinet",
                FN_cmFIO(doc.manCode, 1)                    "doctor",
                FN_cmFIO(p.manCode, 1)                      "patient",
                FN_cmCalcAddress(p.manCode, 5, $typeOut)::TEXT
                                                            "address",
                c.medCouponWorkCode                         "jobId",
                c.medCouponWorkCodeChange                   "jobChangeId",
                mp.medProfessionCode                        "professionId",
                c.triggerFlag                               "triggerFlag",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.IconIndex
                    ELSE cw.IconIndex
                END)                                        "iconIndex",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.Name
                    ELSE cw.name
                END)                                        "workName",
                cw.flagCancel                               "flagcancel",
                FN_cmCalcAddress(p.manCode, 5, 0)::TEXT     "printAddress",      -- Адрес для печати на талоне
                FN_cmFIO(p.manCode, 0)                      "printPatient",
                p.numberHistorySickness                     "numberHistorySickness",
                (
                    SELECT MIN(mpg.startTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                           "cabinetWorkStartTime",
                (
                    SELECT MAX(mpg.finishTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                           "cabinetWorkFinishTime"
            FROM medCoupon c
            LEFT JOIN medPatient p ON p.medPatientCode = c.medPatientCode
            JOIN medDoctor doc ON doc.medDoctorCode = c.medDoctorCode
            LEFT JOIN medPatientGroup pg ON pg.medPatientGroupCode = p.medPatientGroupCode
            LEFT JOIN medPatientGroup pg2 ON pg2.medPatientGroupCode = doc.medPatientGroupCode
            LEFT JOIN medProfession mp ON mp.medProfessionCode = doc.medProfessionCode
            LEFT JOIN department d ON d.departmentCode = doc.departmentCode
            LEFT JOIN enterpriseStructure es ON es.departmentCodeWeb = d.departmentCode
            LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
            LEFT JOIN medCouponWork cw ON cw.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
            LEFT JOIN MedCouponWork MCW ON MCW.medCouponWorkCode = c.medCouponWorkCode
            WHERE c.medDoctorCode $doctorsIds AND $filterCouponSql
                    /* AND COALESCE(c.visitTime, ca.visitTime) IS NOT NULL */ /* WS-2063 */
            ORDER BY c.medDoctorCode, c.onDate, COALESCE(c.visitTime, ca.visitTime) NULLS LAST, c.medCouponCode ASC
            SQL;
$ret->columns = $columns; $ret->records = selectSql($db, $sqlRecords, $sqlParamsCoupon); $ret->total = (int)selectSqlValue($db, $sqlTotal, $sqlParamsDoctor); return $ret; } private function constructMainViewFilter($filter, &$sqlParamsDoctor, &$sqlParamsCoupon, &$filterDoctorSql, &$filterCouponSql, $procedureId) { $this->constructMainViewCouponFilter($filter, $sqlParamsCoupon, $filterCouponSql, $filterDoctorSql, $procedureId); $couponExists = <<<EOT
            AND EXISTS(
                SELECT
                    c.medCouponCode "id"
                FROM medCoupon c
                LEFT JOIN medPatient p ON p.medPatientCode = c.medPatientCode
                JOIN medDoctor doc ON doc.medDoctorCode = c.medDoctorCode
                LEFT JOIN medPatientGroup pg ON pg.MedPatientGroupCode = p.medPatientGroupCode
                LEFT JOIN medPatientGroup pg2 ON pg2.MedPatientGroupCode = doc.medPatientGroupCode
                LEFT JOIN medProfession mp ON mp.medProfessionCode = doc.medProfessionCode
                LEFT JOIN department d ON d.departmentCode = doc.departmentCode
                LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
                LEFT JOIN enterpriseStructure es ON es.departmentCodeWeb = d.departmentCode
                WHERE $filterCouponSql
                    AND c.medDoctorCode = g.medDoctorCode
                    AND (
                        (coalesce(c.visitTime::TIME, ca.visitTime::TIME) >= gw.startTime::TIME OR c.visitTime IS NULL
                    )
                    AND (
                        coalesce(c.visitTime::TIME, ca.visitTime::TIME) < gw.finishTime::TIME OR c.visitTime IS NULL)
                    )
            )
EOT;
$filterDoctorSql .= " $couponExists"; $sqlParamsDoctor = array_merge($sqlParamsDoctor, $sqlParamsCoupon); } private function constructMainViewCouponFilter($filter, &$sqlParamsCoupon, &$filterCouponSql,&$filterDoctorSql, $procedureId) { if ($procedureId) { $procedures = new med_reception_procedureScheduleMap(); $procedureItems = $procedures->readProcedureItems((object)['id' => $procedureId]); } $shift = isUndefinedProperty($filter, 'shift'); $date = isUndefinedProperty($filter, 'date'); $date = $date ?? date("Y-m-d"); $filterDoctorSql = "g.onDate = '$date'"; $filterCouponSql .= $filterCouponSql ? " AND c.onDate = '$date'" : "c.onDate = '$date'"; $shiftCouponSql = ''; if ($shift === 1) { $startTime = self::FIRST_SHIFT_START; $finishTime = self::FIRST_SHIFT_FINISH; $shiftCouponSql = <<<"EOT"
AND ((c.visitTime::time >= $startTime::time AND c.visitTime::time < $finishTime::time) OR (c.visitTime IS NULL AND ca.visitTime IS NULL))
EOT;
} else if ($shift === 2) { $startTime = self::SECOND_SHIFT_START; $finishTime = self::SECOND_SHIFT_FINISH; $shiftCouponSql = <<<"EOT"
AND ((c.visitTime::time >= $startTime::time AND c.visitTime::time <= $finishTime::time) OR (c.visitTime IS NULL AND ca.visitTime IS NULL))
EOT;
} $filterCouponSql .= " $shiftCouponSql"; $startTimeProc = !empty($procedureItems[0]['minWriteTime']) ? $procedureItems[0]['minWriteTime'] : []; $finishTimeProc = !empty($procedureItems[0]['maxWriteTime']) ? $procedureItems[0]['maxWriteTime'] : []; $timeCouponSql = ''; if (!empty($startTimeProc) && !empty($finishTimeProc)) { $timeCouponSql = "AND ((c.visitTime::time >= :startTime::time) AND (c.visitTime::time <= :finishTime::time) OR c.visitTime IS NULL)"; $sqlParamsCoupon[':startTime'] = $startTimeProc; $sqlParamsCoupon[':finishTime'] = $finishTimeProc; } $filterCouponSql .= " $timeCouponSql"; $couponWorkId = isUndefinedProperty($filter, 'couponWorkId'); $couponWorkIdProc = !empty($procedureItems[0]['couponWorkIds']) ? $procedureItems[0]['couponWorkIds'] : []; $couponWorkSql = ''; if ($couponWorkId) { $couponWorkSql = <<<"EOT"
AND ((coalesce(c.medCouponWorkCodeChange, c.medCouponWorkCode) = :couponWorkId) OR (ca.visitTime IS NULL AND c.visitTime IS NULL AND c.medPatientCode IS NULL))
EOT;
$sqlParamsCoupon[':couponWorkId'] = $couponWorkId; } else if (!empty($couponWorkIdProc)) { $placeholders = []; foreach ($couponWorkIdProc as $i => $val) { $ph = ":couponWorkId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } $couponWorkSql = " AND coalesce(c.medCouponWorkCodeChange, c.medCouponWorkCode) IN (" . implode(',', $placeholders) . ")"; } $filterCouponSql .= " $couponWorkSql"; $professionId = isUndefinedProperty($filter, 'professionId'); $professionIdProc = !empty($procedureItems[0]['professionIds']) ? $procedureItems[0]['professionIds'] : []; $professionSql = ''; if ($professionId) { $professionSql = <<<"EOT"
AND (doc.medProfessionCode = :professionId)
EOT;
$sqlParamsCoupon[':professionId'] = $professionId; } else if (!empty($professionIdProc)) { $placeholders = []; foreach ($professionIdProc as $i => $val) { $ph = ":professionId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } $professionSql .= " AND doc.medProfessionCode IN (" . implode(',', $placeholders) . ")"; } $filterCouponSql .= " $professionSql"; $sectorId = isUndefinedProperty($filter, 'sectorId'); $sectorSql = ''; if ($sectorId) { $sectorSql = <<<"EOT"
AND (doc.medPatientGroupCode = :sectorId OR p.medPatientGroupCode = :sectorId)
EOT;
$sqlParamsCoupon[':sectorId'] = $sectorId; } $filterCouponSql .= " $sectorSql"; $officeUnitId = isEmptyProperty($filter, 'officeUnitId'); $officeUnitIdProc = !empty($procedureItems[0]['branchId']) ? $procedureItems[0]['branchId'] : []; $officeUnitSql = ''; if ($officeUnitId) { $officeUnitSql = "AND (ES.enterpriseStructureParentCode = :officeUnitId)"; $sqlParamsCoupon[':officeUnitId'] = $officeUnitId; } else if (!empty($officeUnitIdProc)) { $placeholders = []; foreach ($officeUnitIdProc as $i => $val) { $ph = ":officeUnitId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } if ($placeholders) { $officeUnitSql .= " AND D.BranchCode IN (" . implode(',', $placeholders) . ")"; } } $filterCouponSql .= " $officeUnitSql"; $officeId = isUndefinedProperty($filter, 'officeId'); $officeIdProc = !empty($procedureItems[0]['departmentId']) ? $procedureItems[0]['departmentId'] : []; $officeSql = ''; if ($officeId) { $officeSql = <<<"EOT"
AND (ES.enterpriseStructureCode = :officeId)
EOT;
$sqlParamsCoupon[':officeId'] = $officeId; } else if (!empty($officeIdProc)) { $placeholders = []; foreach ($officeIdProc as $i => $val) { $ph = ":officeId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } if ($placeholders) { $officeSql .= " AND d.departmentCode IN (" . implode(',', $placeholders) . ")"; } } $filterCouponSql .= " $officeSql"; $doctorIdProc = !empty($procedureItems[0]['doctorIds']) ? $procedureItems[0]['doctorIds'] : []; if (!empty($doctorIdProc)) { $placeholders = []; foreach ($doctorIdProc as $i => $val) { $ph = ":doctorId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } $filterCouponSql .= " AND c.medDoctorCode IN (" . implode(',', $placeholders) . ")"; } $cabinetIdProc = !empty($procedureItems[0]['cabinets']) ? $procedureItems[0]['cabinets'] : []; if (!empty($cabinetIdProc )) { $placeholders = []; foreach ($cabinetIdProc as $i => $val) { $ph = ":cabinetId$i"; $placeholders[] = $ph; $sqlParamsCoupon[$ph] = $val; } if ($placeholders) { $filterCouponSql .= " AND COALESCE(
            ca.cabinet,
            (
                SELECT COALESCE(NULLIF(mpgw.cabinet, ''), NULLIF(mpg.cabinet, ''))
                FROM medCouponGraph mpg
                LEFT JOIN medCouponGraphWork mpgw
                    ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    AND mpgw.startTime <= c.visitTime
                    AND mpgw.finishTime > c.visitTime
                WHERE mpg.onDate = c.onDate
                  AND mpg.medDoctorCode = c.medDoctorCode
                LIMIT 1
            ),
            doc.cabinet
        ) IN (" . implode(',', $placeholders) . ")"; } } } public function readPatientViewData($params = null) { $db = $this->db ?: $this->db = connectDb(); $ret = new stdClass(); $sqlWhere = ''; $filter = isUndefinedProperty($params, 'filter'); $ids = isUndefinedProperty($params, 'ids'); if ($ids) { $ids = createSqlInCondition($ids); $sqlWhere .= " AND c.medCouponCode $ids"; } $onlyActiveTalons = isUndefinedProperty($filter, 'onlyActiveTalons'); if ($onlyActiveTalons) { $sqlWhere .= " AND c.couponState IN (1, 2)"; } $patientId = isUndefinedProperty($filter, 'patientId'); $sqlParams[':patientId'] = $patientId; $startDate = dbDate(isUndefinedProperty($filter, 'startDate')); if ($startDate) { $sqlWhere .= " AND c.onDate >= :startDate::DATE"; $sqlParams[':startDate'] = $startDate; } $finishDate = dbDate(isUndefinedProperty($filter, 'finishDate')); if ($finishDate) { $sqlWhere .= " AND c.onDate <= :finishDate::DATE"; $sqlParams[':finishDate'] = $finishDate; } $doctorProfessionId = isUndefinedProperty($filter, 'doctorProfessionId'); if ($doctorProfessionId) { $sqlWhere .= " AND mp.medProfessionCode = :doctorProfessionId"; $sqlParams[':doctorProfessionId'] = $doctorProfessionId; } $doctorId = isUndefinedProperty($filter, 'doctorId'); if ($doctorId) { $sqlWhere .= " AND c.medDoctorCode = :doctorId"; $sqlParams[':doctorId'] = $doctorId; } $sql = <<<EOT
            SELECT
                c.medCouponCode                                 "id",
                c.onDate                                        "onDate",
                COALESCE(c.visitTime, ca.visitTime)             "visitTime",
                CASE WHEN c.visitTime IS NULL THEN 1 ELSE 0 END "isExtraTalon",
                ca.visitTime                                    "extraTalonVisitTime",
                c.medDoctorCode                                 "doctorId",
                c.medPatientCode                                "patientId",
                p.manCode                                       "patientManId",
                c.couponState                                   "state",
                c.couponType                                    "couponType",
                COALESCE(c.selfRecording, 0)                    "selfRecording",
                c.orderTime                                     "orderTime",
                c.giveTime                                      "giveTime",
                c.fio                                           "fio",
                pg.Name                                         "sectorName",
                pg2.Name                                        "doctorSectorName",
                mp.Name                                         "doctorProfession",
                (SELECT STRING_AGG((CONCAT(E', ', CASE
                        WHEN mpn.phoneType = 1 THEN 'д'
                        WHEN mpn.phoneType = 2 THEN 'р'
                        WHEN mpn.phoneType = 3 THEN 'м'
                        WHEN mpn.phoneType = 4 THEN 'п'
                        ELSE NULL
                        END, E'.', mpn.phoneNumber))::TEXT, E'')
                    FROM manphone mpn
                    WHERE mpn.manCode = p.manCode)              "contact",
                c.note                                          "note",
                COALESCE(ca.cabinet, (SELECT COALESCE(NULLIF(mpgw.cabinet, ''), NULLIF(mpg.cabinet, ''))
                            FROM medCouponGraph mpg
                            LEFT JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                                AND mpgw.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
                            WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                            LIMIT 1), doc.cabinet)              "cabinet",
                FN_cmFIO(doc.manCode)                           "doctor",
                COALESCE(FN_cmFIO(p.manCode), c.fio)            "patient",
                FN_cmCalcAddress(p.manCode, 5, 0)::TEXT         "address",
                c.medCouponWorkCode                             "jobId",
                c.medCouponWorkCodeChange                       "jobChangeId",
                mp.medProfessionCode                            "professionId",
                c.triggerFlag                                   "triggerFlag",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.IconIndex
                    ELSE cw.IconIndex
                END)                                            "iconIndex",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.Name
                    ELSE cw.name
                END)                                            "workName",
                FN_cmCalcAddress(p.manCode, 5, 0)::TEXT         "printAddress",      -- Адрес для печати на талоне
                FN_cmFIO(p.manCode, 0)                          "printPatient",
                p.numberHistorySickness                         "numberHistorySickness",
                (
                    SELECT MIN(mpg.startTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                               "cabinetWorkStartTime",
                (
                    SELECT MAX(mpg.finishTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                               "cabinetWorkFinishTime",
                cw.flagcancel                                   "flagcancel"
            FROM medCoupon c
            LEFT JOIN medPatient p ON p.medPatientCode = c.medPatientCode
            JOIN medDoctor doc ON doc.medDoctorCode = c.medDoctorCode
            LEFT JOIN medPatientGroup pg ON pg.MedPatientGroupCode = p.medPatientGroupCode
            LEFT JOIN medPatientGroup pg2 ON pg2.MedPatientGroupCode = doc.medPatientGroupCode
            LEFT JOIN medProfession mp ON mp.medProfessionCode = doc.medProfessionCode
            LEFT JOIN department d ON d.departmentcode = doc.departmentcode
            LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
            LEFT JOIN medCouponWork cw ON cw.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
            LEFT JOIN MedCouponWork MCW ON MCW.medCouponWorkCode = c.medCouponWorkCode
            WHERE c.medPatientCode = :patientId
                    /* AND COALESCE(c.visitTime, ca.visitTime) IS NOT NULL */ /* WS-2063 */
                $sqlWhere
            ORDER BY c.onDate DESC, COALESCE(c.visitTime, ca.visitTime) DESC NULLS  LAST, c.orderTime DESC
EOT;
$ret->records = selectSql($db, $sql, $sqlParams); return $ret; } public function readDoctorViewData($params = null) { $db = $this->db ?: $this->db = connectDb(); $ret = new stdClass(); $flagSecondReception = isUndefinedProperty($params, 'flagSecondReception'); $flagSecondReceptionSql = ''; $sqlParamsDoctor = []; $sqlParamsCoupon = []; $filterDoctorSql = ""; $filterCouponSql = ""; $ids = isEmptyProperty($params, 'ids'); if ($ids) { $ids = createSqlInCondition($ids); $filterCouponSql = " AND c.medCouponCode $ids"; } $filter = isEmptyProperty($params, 'filter'); $page = isUndefinedProperty($params, 'page'); $limit = isUndefinedProperty($params, 'numberOfColumns'); $usePages = isUndefinedProperty($params, 'usePages'); $offset = 0; if ($page > 0) { $offset = $page * $limit; } if ($flagSecondReception) { $flagSecondReceptionSql = " AND cw.FlagSecondReception = 1 "; $filter->flagSecondReception = true; } if ($filter) { $this->constructDoctorViewFilter($filter, $sqlParamsDoctor, $sqlParamsCoupon, $filterDoctorSql, $filterCouponSql); } $sqlDoctorFrom = <<<SQL
            FROM medCouponGraph g
            LEFT JOIN medCouponGraphWork gw on gw.medCouponGraphCode = g.medCouponGraphCode
            LEFT JOIN medDoctor d on d.medDoctorCode = g.medDoctorCode
            LEFT JOIN medProfession mp on mp.medProfessionCode = d.medProfessionCode
            WHERE $filterDoctorSql
            SQL;
$sql = <<<SQL
            SELECT DISTINCT
                g.onDate "onDate",
                NULLIF(REGEXP_REPLACE(COALESCE(NULLIF(gw.cabinet, ''), g.cabinet, d.cabinet), '\D', '', 'g'), '')::INT "cabinetSort",
                g.medDoctorCode "doctorId",
                COALESCE(NULLIF(gw.cabinet, ''), g.cabinet, d.cabinet) "cabinet",
                FN_cmFIO(d.manCode, 1)::TEXT "doctorFio",
                mp.name "profession",
                mp.medProfessionCode  "professionId",
                ARRAY_AGG(gw.medCouponGraphWorkCode) medCouponGraphWorkCodes
            $sqlDoctorFrom
            GROUP BY 1, 2, 3, 4, 5, 6, 7
            SQL;
$sqlTotal = <<<SQL
            SELECT COUNT(*) "total"
            FROM ($sql) tab
            SQL;
$sql .= " ORDER BY 1, 2"; if ($usePages) { $sql .= " OFFSET $offset LIMIT $limit"; } $columns = selectSql($db, $sql, $sqlParamsDoctor); $cityInAddress = isUndefinedProperty($params, 'cityInAddress'); $typeOut = $cityInAddress ? 0 : 3; $sqlRecords = <<<SQL
            WITH columns as ($sql),
                couponGraph as (
                    SELECT
                        gw.medCouponGraphCode,
                        col.cabinet,
                        g.onDate,
                        gw.medCouponWorkCode,
                        gw.startTime,
                        gw.finishTime,
                        g.medDoctorCode,
                        col.profession,
                        col."professionId"
                    FROM columns col
                    JOIN medCouponGraphWork gw ON gw.medCouponGraphWorkCode = ANY(col.medCouponGraphWorkCodes)
                    JOIN medCouponGraph g ON g.medCouponGraphCode = gw.medCouponGraphCode
                )
            SELECT
                DISTINCT c.medCouponCode                        "id",
                NULLIF(REGEXP_REPLACE(cg.cabinet, '\D', '', 'g'), '')::INT
                                                                "cabinetSort",
                c.onDate                                        "onDate",
                COALESCE(c.visitTime, ca.visitTime)             "visitTime",
                CASE WHEN c.visitTime IS NULL THEN 1 ELSE 0 END "isExtraTalon",
                ca.visitTime                                    "extraTalonVisitTime",
                c.medDoctorCode                                 "doctorId",
                c.medPatientCode                                "patientId",
                p.manCode                                       "patientManId",
                c.couponState                                   "state",
                c.couponType                                    "couponType",
                COALESCE(c.selfRecording, 0)                    "selfRecording",
                c.orderTime                                     "orderTime",
                c.giveTime                                      "giveTime",
                c.fio                                           "fio",
                pg.Name                                         "sectorName",
                pg2.Name                                        "doctorSectorName",
                cg.profession                                   "doctorProfession",
                (SELECT STRING_AGG((CONCAT(E', ' ,CASE
                        WHEN mpn.phoneType = 1 THEN 'д'
                        WHEN mpn.phoneType = 2 THEN 'р'
                        WHEN mpn.phoneType = 3 THEN 'м'
                        WHEN mpn.phoneType = 4 THEN 'п'
                        ELSE NULL
                        END, E'.', mpn.phoneNumber))::TEXT, E'')
                    FROM manphone mpn
                    WHERE mpn.manCode = p.manCode)              "contact",
                c.note                                          "note",
                cg.cabinet                                      "cabinet",
                FN_cmFIO(doc.manCode, 1)                        "doctor",
                FN_cmFIO(p.manCode, 1)                          "patient",
                FN_cmCalcAddress(p.manCode, 5, $typeOut)::TEXT  "address",
                c.medCouponWorkCode                             "jobId",
                c.medCouponWorkCodeChange                       "jobChangeId",
                cg."professionId"                               "professionId",
                c.triggerFlag                                   "triggerFlag",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.IconIndex
                    ELSE cw.IconIndex
                END)                                            "iconIndex",
                (CASE
                    WHEN cw.flagCancel = 1
                        THEN MCW.Name
                    ELSE cw.name
                END)                                            "workName",
                cw.flagcancel "flagcancel",
                FN_cmCalcAddress(p.manCode, 5, 0)::TEXT         "printAddress",      -- Адрес для печати на талоне
                FN_cmFIO(p.manCode, 0)                          "printPatient",
                p.numberHistorySickness                         "numberHistorySickness",
                (
                    SELECT MIN(mpg.startTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                               "cabinetWorkStartTime",
                (
                    SELECT MAX(mpg.finishTime)
                    FROM medCouponGraph mpg
                    JOIN medCouponGraphWork mpgw ON mpgw.medCouponGraphCode = mpg.medCouponGraphCode
                    WHERE mpg.onDate = c.onDate AND mpg.medDoctorCode = c.medDoctorCode
                )                                               "cabinetWorkFinishTime"
            FROM medCoupon c
            LEFT JOIN medPatient p ON p.medPatientCode = c.medPatientCode
            JOIN medDoctor doc ON doc.medDoctorCode = c.medDoctorCode
            LEFT JOIN medPatientGroup pg ON pg.MedPatientGroupCode = p.medPatientGroupCode
            LEFT JOIN medPatientGroup pg2 ON pg2.MedPatientGroupCode = doc.medPatientGroupCode
            JOIN couponGraph cg ON cg.medDoctorCode = c.medDoctorCode
                AND cg.onDate = c.onDate AND cg.startTime <= coalesce(c.visitTime, cg.startTime)
                AND cg.finishTime >= coalesce(c.visitTime, cg.finishTime)
            LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
            LEFT JOIN medCouponWork cw ON cw.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
            LEFT JOIN MedCouponWork MCW ON MCW.medCouponWorkCode = c.medCouponWorkCode
            WHERE c.medDoctorCode = :doctorId $flagSecondReceptionSql $filterCouponSql
                    /* AND COALESCE(c.visitTime, ca.visitTime) IS NOT NULL */ /* WS-2063 */
            ORDER BY c.onDate, 2, COALESCE(c.visitTime, ca.visitTime) NULLS LAST, c.orderTime NULLS LAST
            SQL;
$sqlParamsCoupon = array_merge($sqlParamsCoupon, $sqlParamsDoctor); $ret->columns = $columns; $ret->records = selectSql($db, $sqlRecords, $sqlParamsCoupon); $ret->total = (int)selectSqlValue($db, $sqlTotal, $sqlParamsDoctor); return $ret; } private function constructDoctorViewFilter($filter, &$sqlParamsDoctor, &$sqlParamsCoupon, &$filterDoctorSql, &$filterCouponSql) { $this->constructDoctorViewCouponFilter($filter, $sqlParamsCoupon, $filterCouponSql,$filterDoctorSql); $date = isUndefinedProperty($filter,'date', date("Y-m-d")); $filterDoctorSql = "g.onDate >= :onDate"; $sqlParamsDoctor[':onDate'] = $date; $doctorId = isUndefinedProperty($filter, 'doctorId'); $doctorSql = 'AND g.medDoctorCode = :doctorId'; $sqlParamsDoctor[':doctorId'] = $doctorId; $filterDoctorSql .= " $doctorSql"; $couponExists = <<<"EOT"
AND EXISTS(
SELECT
c.medCouponCode "id"
FROM medCoupon c
LEFT JOIN medPatient p ON p.medPatientCode = c.medPatientCode
JOIN medDoctor doc ON doc.medDoctorCode = c.medDoctorCode
LEFT JOIN medPatientGroup pg ON pg.MedPatientGroupCode = p.medPatientGroupCode
LEFT JOIN medPatientGroup pg2 ON pg2.MedPatientGroupCode = doc.medPatientGroupCode
LEFT JOIN medCouponAdd ca ON ca.medCouponCode = c.medCouponCode
LEFT JOIN department d ON d.departmentcode = doc.departmentcode
LEFT JOIN medCouponWork CW ON CW.medCouponWorkCode = COALESCE(c.medCouponWorkCodeChange, c.medCouponWorkCode)
WHERE c.medDoctorCode = g.medDoctorCode AND C.onDate = g.onDate
AND (
    (coalesce(c.visitTime::TIME, ca.visitTime::TIME) >= gw.startTime::TIME OR c.visitTime IS NULL
)
AND (
    coalesce(c.visitTime::TIME, ca.visitTime::TIME) < gw.finishTime::TIME OR c.visitTime IS NULL)
)
$filterCouponSql
)
EOT;
$filterDoctorSql .= " $couponExists"; $sqlParamsDoctor = array_merge($sqlParamsDoctor, $sqlParamsCoupon); } private function constructDoctorViewCouponFilter($filter, &$sqlParamsCoupon, &$filterCouponSql, &$filterDoctorSql) { $shift = isUndefinedProperty($filter, 'shift'); $shiftCouponSql = ''; if ($shift === 1) { $startTime = self::FIRST_SHIFT_START; $finishTime = self::FIRST_SHIFT_FINISH; $shiftCouponSql = <<<"EOT"
AND (c.visitTime::time >= $startTime::time AND c.visitTime::time < $finishTime::time)
EOT;
} else if ($shift === 2) { $startTime = self::SECOND_SHIFT_START; $finishTime = self::SECOND_SHIFT_FINISH; $shiftCouponSql = <<<"EOT"
AND (c.visitTime::time >= $startTime::time AND c.visitTime::time <= $finishTime::time)
EOT;
} $filterCouponSql .= " $shiftCouponSql"; $couponWorkId = isUndefinedProperty($filter, 'couponWorkId'); $couponWorkSql = ''; if ($couponWorkId) { $couponWorkSql = <<<"EOT"
AND ((coalesce(c.medCouponWorkCodeChange, c.medCouponWorkCode) = :couponWorkId) OR (ca.visitTime IS NULL AND c.visitTime IS NULL AND c.medPatientCode IS NULL))
EOT;
$sqlParamsCoupon[':couponWorkId'] = $couponWorkId; } $filterCouponSql .= " $couponWorkSql"; $flagSecondReception = isUndefinedProperty($filter, 'flagSecondReception'); $flagSecondReceptionSql = ''; if ($flagSecondReception) { $flagSecondReceptionSql = " AND CW.FlagSecondReception = 1 "; } $filterCouponSql .= " $flagSecondReceptionSql"; } public function doBookTalon ($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $where = 'WHERE C.medCouponCode = :id'; $sqlParams = [ ':id' => $id ]; $sql = <<<"EOT"
SELECT
    C.medPatientCode "patientId",
    C.couponState "state"
FROM medCoupon C
$where
EOT;
$coupons = selectSql($db, $sql, $sqlParams); if (count($coupons) > 0) { $coupon = $coupons[0]; if ($coupon['state'] === 1 && $coupon['patientId'] === null) { throw new Exception(self::ERROR_ALREADY_BOOKED_MESSAGE, self::ERROR_ALREADY_BOOKED_CODE); } $sql = <<<"EOT"
SELECT
    FN_medCouponToBook(:id, :minutes)
EOT;
$bookingResult = selectSqlValue($db, $sql, [ ':id' => $id, ':minutes' => self::BOOKING_TIME_MINUTES ]); if ($bookingResult === 1) { $this->createNotice(); return true; } } else { throw new Exception(self::ERROR_BOOKING_TALON_DELETED_MESSAGE, self::ERROR_BOOKING_TALON_DELETED_CODE); } return false; } public function doCancelBookTalon($params = null) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); if ($id) { $sql = <<<"EOT"
SELECT
    PR_medCouponCancelBook(:id, 1)
EOT;
selectSql($db, $sql, [ ':id' => $id ]); $this->createNotice(); return true; } $sql = <<<"EOT"
SELECT
    C.medCouponCode "id"
FROM medCouponBooking CB
JOIN medCoupon C ON C.medCouponCode = CB.medCouponCode
WHERE CB.limitTime < (LOCALTIMESTAMP)
EOT;
$unbookingTalons = selectSql($db, $sql); if (count($unbookingTalons) > 0) { $sql = <<<"EOT"
SELECT
    PR_medCouponCancelBook()
EOT;
selectSql($db, $sql); $this->createNotice(); return true; } return true; } public function createNotice() { $notice = new stdClass(); $notice->type = self::UPDATE_TALONMAP_NOTICE; $notice->source = 'medcoupon'; $notice->validTime = (new DateTime())->add(new DateInterval('PT2M')); $notice->userCodeAuthor = getSessionValue('mapUserCode'); $data = new stdClass(); $notice->data = $data; common_notices::createNotice($notice); } } handleJsonRpc('med_reception_talonMap');