<?php
require_once(__DIR__.'/../../init.php'); class map_infopanel_infopanelData { private $db; private $timeOut = 420; public function readInfopanelData($params){ $db = $this->db ?: $this->db = connectDb(); $exitLoop = false; $ret = new stdClass(); $inPanelName = isEmptyProperty($params, 'name'); $inSegmentId = isEmptyProperty($params, 'segmentId'); $firstRun = isEmptyProperty($params, 'firstRun'); $i = 0; do { $sql = <<<EOT
                SELECT data
                FROM infopanelSync
                WHERE name LIKE ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $inPanelName ]); $panelDBData = $stmt->fetch(); if (!$panelDBData){ $panelDBData = new stdClass(); $panelDBData->segmentId = 1; if ($inSegmentId == 1){ $panelDBData->startSegmentFromId = 0; $panelDBData->configured = 0; $panelDBData->name = $inPanelName; } }else{ $panelDBData = safe_json_decode($panelDBData['data']); $ret = $panelDBData; if ($inSegmentId == 1 && $firstRun){ $panelDBData->startSegmentFromId = 0; } } if ($panelDBData->segmentId == $inSegmentId || $inSegmentId == 1){ $exitLoop = true; $ret->infopanelData = $this->getInfopanelDataFromDB($panelDBData); } if ($i > $this->timeOut){ $exitLoop = true; } sleep(1); $i++; } while (!$exitLoop); $ret->segmentId = $panelDBData->segmentId; $ret->startSegmentFromId = $panelDBData->startSegmentFromId; $ret->dateTime = date("Y-m-d H:i:s"); $ret->configured = $panelDBData->configured; return $ret; } private function getInfopanelDataFromDB($params){ $db = $this->db ?: $this->db = connectDb(); $inEnterpriseId = isUndefinedProperty($params, 'enterpriseId', 0); $inBranchId = isUndefinedProperty($params, 'branchId', 0); $departmentIds = isUndefinedProperty($params, 'departmentIds'); $where = ''; if (isset($departmentIds) && !empty($departmentIds)){ $where = " AND d.departmentCode" . createSqlInCondition($departmentIds); } $sql = <<<EOT
            SELECT
                ASA_TRIM(P.Cipher) AS "professionCipher",
                P.Name AS "professionName",
                Dr.medProfessionCode AS "medProfessionCode",
                ASA_TRIM(D.Cipher) AS "departmentCipher",
                D.Name AS "departmentName",
                D.DepartmentCode AS "departmentCode",
                FN_cmFio(Dr.ManCode) AS "doctorFIO",
                Dr.medDoctorCode AS "medDoctorCode",
                MAX(COALESCE(CASE Q.OnDate = DATE((LOCALTIMESTAMP)) WHEN TRUE THEN Q.Cabinet END, Dr.Cabinet)) AS cabinet,
                Dr.BasicTimeTable AS "basicTimetable",
                DrLocality.CipherList AS "localityCipherList",
                DrLocality.NameList AS "localityNameList",
                DrLocality.AddressList AS "localityAddressList",
                MAX(CASE Q.OnDate = DATE((LOCALTIMESTAMP)) WHEN TRUE THEN Q.TimeList END) AS "todayTimetable",
                (
                    SELECT COUNT(*)
                    FROM medCoupon AS C
                    LEFT OUTER JOIN medCouponWork AS CW ON CW.medCouponWorkCode = COALESCE(C.medCouponWorkCodeChange, C.medCouponWorkCode)
                    WHERE C.OnDate = DATE((LOCALTIMESTAMP))
                        AND (CAST(CAST(C.OnDate AS DATE) + CAST(C.VisitTime AS TIME) AS TIMESTAMP) > (LOCALTIMESTAMP)) AND C.MedDoctorCode = Dr.medDoctorCode
                        AND COALESCE(C.CouponState, 0) = 0
                        AND COALESCE(CW.FlagCoupon, 0) = 1
                        AND COALESCE(CW.FlagSecondReception, 0) = 0
                        AND COALESCE(CW.FlagSpecial, 0) = 0
                        AND COALESCE(CW.FlagCancel, 0) = 0
                ) AS "todayCouponCount",
                MAX(CASE Q.OnDate = DATEADD('DAY', 1, DATE((LOCALTIMESTAMP))) WHEN TRUE THEN Q.TimeList END) AS "tomorrowTimetable",
                (
                    SELECT COUNT(*)
                    FROM medCoupon AS C
                    LEFT OUTER JOIN medCouponWork AS CW ON CW.medCouponWorkCode = COALESCE(C.medCouponWorkCodeChange, C.medCouponWorkCode)
                    WHERE C.OnDate = DATEADD('DAY', 1, DATE((LOCALTIMESTAMP)))
                        AND C.MedDoctorCode = Dr.medDoctorCode
                        AND COALESCE(C.CouponState, 0) = 0
                        AND COALESCE(CW.FlagCoupon, 0) = 1
                        AND COALESCE(CW.FlagSecondReception, 0) = 0
                        AND COALESCE(CW.FlagSpecial, 0) = 0
                        AND COALESCE(CW.FlagCancel, 0) = 0
                ) AS "tomorrowCouponCount",
                ASA_TRIM(Br.Cipher) AS "branchCipher",
                Br.BranchCode AS "branchCode",
                Br.Name AS "branchName",
                DrLocality.ListItemCount AS "localityCount",
                SPACE(20-LENGTH(P.Cipher)) || P.Cipher AS "professionCipherOrdering",
                D.Cipher AS "departmentCipherOrdering",
                Br.Cipher AS "branchCipherOrdering",
                U.Code AS "uniProfessionCode",
                COALESCE(Dr.AttributeMark, 0) AS "drAttributeMark"
            FROM Branch AS Br
            JOIN Department AS D ON D.BranchCode = Br.BranchCode
            JOIN medDoctor AS Dr ON Dr.DepartmentCode = D.DepartmentCode AND Dr.StartDate <= (DATEADD('DAY', 1, DATE((LOCALTIMESTAMP)))) AND Dr.FinishDate >= (DATE((LOCALTIMESTAMP)))
            LEFT OUTER JOIN (
                SELECT DrPG.medDoctorCode AS medDoctorCode,
                    COUNT(*) AS ListItemCount,
                    STRING_AGG((DrPG.Cipher)::TEXT, CHR(13)||CHR(10) ORDER BY DrPG.Cipher ) AS CipherList,
                    STRING_AGG((DrPG.Name)::TEXT, CHR(13)||CHR(10) ORDER BY DrPG.Cipher ) AS NameList,
                    STRING_AGG((DrPG.PatientLocality)::TEXT, CHR(13)||CHR(10) ORDER BY DrPG.Cipher ) AS AddressList
                FROM (
                    SELECT PG.medDoctorCode AS medDoctorCode,
                        PG.Cipher AS Cipher,
                        PG.Name AS Name,
                        STRING_AGG((REPLACE(FN_medPatientLocationExpand(PL.medPatientLocationCode,1), E',', E', '))::TEXT, E';' ORDER BY FN_medPatientLocationExpand(PL.medPatientLocationCode,1) ) AS PatientLocality
                    FROM medPatientGroup AS PG LEFT OUTER JOIN medPatientLocation AS PL ON PL.medPatientGroupCode = PG.medPatientGroupCode AND COALESCE( PL.SearchType, 0 ) = 0
                    WHERE PG.EnterpriseCode = :EnterpriseCode
                    GROUP BY 1, 2, 3
                ) AS DrPG
                GROUP BY 1
            ) AS DrLocality ON DrLocality.medDoctorCode = Dr.medDoctorCode
            JOIN medProfession AS P ON P.MedProfessionCode = Dr.MedProfessionCode
            LEFT OUTER JOIN UniImportLink AS U ON U.UniImportLinkCode = P.TalonByCode
            LEFT OUTER JOIN (
                SELECT
                    CG.OnDate AS OnDate,
                    CG.MedCouponGraphCode AS MedCouponGraphCode,
                    CG.MedDoctorCode AS MedDoctorCode,
                    NULLIF(ASA_TRIM(CG.Cabinet), E'') AS Cabinet,
                    (
                        SELECT
                            ARRAY_TO_STRING_DISTINCT(
                                ARRAY_AGG(
                                CASE qS.WorkName <> (E'')
                                    WHEN TRUE
                                        THEN qS.WorkName
                                    WHEN FALSE
                                        THEN CAST(qS.StartTimeFn AS varchar(5)) || E' - ' || CAST(qF.FinishTimeFn AS varchar(5))
                                END
                                ), CHR(13) || CHR(10)
                            )
                        FROM (
                            SELECT
                                CAST(CASE MOD((TO_CHAR((GW1.StartTime)::TIMESTAMP,'FMMI')::INTEGER), 10) = 1 WHEN TRUE THEN DATEADD('MINUTE', -1, GW1.StartTime) WHEN FALSE THEN GW1.StartTime END AS time) AS StartTimeFn,
                                COALESCE(CASE COALESCE(W.FlagCancel, 0) = 1 WHEN TRUE THEN W.Name END , E'') AS WorkName,
                                ROW_NUMBER() OVER(ORDER BY (CAST(CASE MOD((TO_CHAR((GW1.StartTime)::TIMESTAMP, 'FMMI')::INTEGER), 10) = 1 WHEN TRUE THEN DATEADD('MINUTE', -1, GW1.StartTime) WHEN FALSE THEN GW1.StartTime END AS time)), (COALESCE(CASE COALESCE(W.FlagCancel, 0) = 1 WHEN TRUE THEN W.Name END, E''))) AS RN
                            FROM medCouponGraphWork AS GW1
                            LEFT OUTER JOIN medCouponWork AS W ON W.medCouponWorkCode = GW1.medCouponWorkCode
                            LEFT OUTER JOIN medCouponGraphWork AS GW2 ON GW2.MedCouponGraphCode = GW1.MedCouponGraphCode
                                AND GW2.MedCouponWorkCode IN (
                                    SELECT W_.medCouponWorkCode
                                        FROM medCouponWork AS W_
                                        WHERE COALESCE(W_.FlagCoupon, 0) = COALESCE(W.FlagCoupon, 0)
                                            AND COALESCE(W_.FlagCancel, 0) = COALESCE(W.FlagCancel, 0) AND COALESCE(W_.FlagSpecial, 0) = COALESCE(W.FlagSpecial, 0)
                                )
                                AND CAST(GW2.StartTime AS time) < CAST(GW1.StartTime AS time)
                                AND CAST(DATEADD('MINUTE', 1, GW2.FinishTime) AS time) >= (CAST(GW1.StartTime AS time))
                            WHERE GW1.MedCouponGraphCode = CG.MedCouponGraphCode
                                AND COALESCE(W.FlagSpecial, 0) = 0
                                AND (COALESCE(W.FlagCoupon, 0) = 1 OR COALESCE(W.FlagCancel, 0) = 1)
                                AND GW2.medCouponGraphWorkCode IS NULL
                        ) AS qS
                        JOIN (
                            SELECT
                                CAST(CASE MOD((TO_CHAR((GW1.FinishTime)::TIMESTAMP, 'FMMI')::INTEGER), 10) = 9 WHEN TRUE THEN DATEADD('MINUTE', 1, GW1.FinishTime) WHEN FALSE THEN GW1.FinishTime END AS time) AS FinishTimeFn,
                                COALESCE(CASE COALESCE(W.FlagCancel, 0) = 1 WHEN TRUE THEN W.Name END ,  E'') AS WorkName,
                                ROW_NUMBER() OVER(ORDER BY (CAST(CASE MOD((TO_CHAR((GW1.FinishTime)::TIMESTAMP, 'FMMI')::INTEGER), 10) = 9 WHEN TRUE THEN DATEADD('MINUTE', 1, GW1.FinishTime) WHEN FALSE THEN GW1.FinishTime END AS time)), (COALESCE(CASE COALESCE(W.FlagCancel, 0) = 1 WHEN TRUE THEN W.Name END, E''))) AS RN
                            FROM medCouponGraphWork AS GW1
                            LEFT OUTER JOIN medCouponWork AS W ON W.medCouponWorkCode = GW1.medCouponWorkCode
                            LEFT OUTER JOIN medCouponGraphWork AS GW2 ON GW2.MedCouponGraphCode = GW1.MedCouponGraphCode
                                AND GW2.MedCouponWorkCode IN (
                                    SELECT W_.medCouponWorkCode
                                    FROM medCouponWork AS W_
                                    WHERE COALESCE(W_.FlagCoupon, 0) = COALESCE(W.FlagCoupon, 0)
                                        AND COALESCE(W_.FlagCancel, 0) = COALESCE(W.FlagCancel, 0)
                                        AND COALESCE(W_.FlagSpecial, 0) = COALESCE(W.FlagSpecial, 0)
                                )
                                AND (CAST(GW2.StartTime AS time) = CAST(GW1.FinishTime AS time)
                                OR CAST(GW2.StartTime AS time) = CAST(DATEADD('MINUTE', 1, GW1.FinishTime) AS time))
                            WHERE GW1.MedCouponGraphCode = CG.MedCouponGraphCode
                                AND COALESCE(W.FlagSpecial, 0) = 0
                                AND (COALESCE(W.FlagCoupon, 0) = 1 OR COALESCE(W.FlagCancel, 0) = 1)
                                AND GW2.medCouponGraphWorkCode IS NULL) AS qF ON qF.RN = qS.RN
                        WHERE CG.OnDate BETWEEN DATE((LOCALTIMESTAMP))
                            AND DATEADD('DAY', 1, DATE((LOCALTIMESTAMP)))) AS TimeList
                    FROM medCouponGraph AS CG JOIN medCouponGraphWork AS GW ON GW.MedCouponGraphCode = CG.MedCouponGraphCode
                    WHERE CG.OnDate BETWEEN DATE((LOCALTIMESTAMP)) AND DATEADD('MONTH', 2, DATE((LOCALTIMESTAMP)))
                        AND GW.MedCouponWorkCode IN (
                            SELECT medCouponWorkCode
                            FROM medCouponWork
                            WHERE COALESCE(FlagCoupon, 0) = 1
                                OR COALESCE(FlagCancel, 0) = 1)
            ) AS Q ON Q.MedDoctorCode = Dr.MedDoctorCode
            WHERE Br.EnterPriseCode = :EnterpriseCode
                AND (:BranchCode = 0 OR Br.BranchCode = :BranchCode)
                AND (COALESCE(Dr.AttributeMark, 0) & 1) != 1
                $where
            GROUP BY 1, 22, 2, 3, 25, 18, 24, 19, 20, 4, 23, 5, 6, 7, 8, 10, 11, 12, 13, 21, 26
            ORDER BY 22, 2, 3, 23, 5, 6, 11, 12, 7, 8
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ ':EnterpriseCode' => $inEnterpriseId, ':BranchCode' => $inBranchId ]); $ret = $stmt->fetchAll(); return $ret; } public function updateInfopanelSyncTable($params){ $db = $this->db ?: $this->db = connectDb(); $data = $this->getJsonData($params); $sql = <<<EOT
            INSERT INTO infopanelSync (name, data) VALUES (?, ?)
            ON CONFLICT (name) DO UPDATE SET data = EXCLUDED.data
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->name, $data ]); } private function getJsonData($params){ $enterpriseId = isEmptyProperty($params, 'enterpriseId', 0); $branchId = isEmptyProperty($params, 'branchId', 0); $segmentsNumber = isEmptyProperty($params, 'segmentsNumber', 1); $segmentId = isUndefinedProperty($params, 'segmentId', 1); $startSegmentFromId = isUndefinedProperty($params, 'startSegmentFromId', 0); $configured = isUndefinedProperty($params, 'configured', $enterpriseId ? $enterpriseId : 0); $reloadTime = isUndefinedProperty($params, 'reloadTime', 15); $departmentIds = isUndefinedProperty($params, 'departmentIds'); $ret = json_encode((object)[ 'segmentId' => $segmentId, 'startSegmentFromId' => $startSegmentFromId, 'configured' => $configured, 'enterpriseId' => $enterpriseId, 'branchId' => $branchId, 'segmentsNumber' => $segmentsNumber, 'reloadTime' => $reloadTime, 'departmentIds' => $departmentIds ], JSON_UNESCAPED_UNICODE); return $ret; } } handleJsonRpc('map_infopanel_infopanelData');