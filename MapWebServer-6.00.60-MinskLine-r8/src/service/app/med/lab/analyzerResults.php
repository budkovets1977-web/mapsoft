<?php
require_once(__DIR__ . '/../../init.php'); class med_lab_analyzerresults { const TASK_NAME = 'medLabAnalyzerResults'; const PAGE_SIZE = 25; private $db; public function readAnalyzerResults($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $devicePeriod = (int)isEmptyProperty($params, 'devicePeriod', 0); $devicePeriodStart = isEmptyProperty($params, 'devicePeriodStart'); $devicePeriodFinish = isEmptyProperty($params, 'devicePeriodFinish'); $studyPeriod = (int)isEmptyProperty($params, 'studyPeriod', 0); $studyPeriodStart = isEmptyProperty($params, 'studyPeriodStart'); $studyPeriodFinish = isEmptyProperty($params, 'studyPeriodFinish'); $medPatientId = isEmptyProperty($params, 'medPatientId', 0); $medRefDoctorId = isEmptyProperty($params, 'medRefDoctorId', 0); $medLabDriverId = isEmptyProperty($params, 'medLabDriverId', 0); $medLabDeviceId = isEmptyProperty($params, 'medLabDeviceId', 0); $medStudyId = isEmptyProperty($params, 'medStudyId', 0); $onlyNew = (int)isEmptyProperty($params, 'onlyNew'); $sortStr = isEmptyProperty($params, 'sort'); $sort = $sortStr ? "\"$sortStr\"" : '2'; $dir = isEmptyProperty($params, 'dir', ''); $pageSize = isEmptyProperty($params, 'limit', self::PAGE_SIZE); $sqlParams = [ ':devicePeriod' => $devicePeriod, ':devicePeriodStart' => $devicePeriodStart, ':devicePeriodFinish' => $devicePeriodFinish, ':studyPeriod' => $studyPeriod, ':studyPeriodStart' => $studyPeriodStart, ':studyPeriodFinish' => $studyPeriodFinish, ':medPatientId' => $medPatientId, ':enterpriseId' => $enterpriseId, ':medRefDoctorId' => $medRefDoctorId, ':medLabDriverId' => $medLabDriverId, ':medLabDeviceId' => $medLabDeviceId, ':medStudyId' => $medStudyId, ':onlyNew' => $onlyNew ]; $from = <<<SQL
            FROM LabStudy LabS
            JOIN MedLabDevice LabDev ON LabDev.MedLabDeviceCode = LabS.MedLabDeviceCode
            JOIN MedLabDriver LabDrv ON LabDrv.MedLabDriverCode = LabDev.MedLabDriverCode
            LEFT OUTER JOIN MedLogStudy LS ON LS.MedLogStudyCode = LabS.MedLogStudyCode
            LEFT OUTER JOIN MedDoctor Dr ON Dr.MedDoctorCode = LS.MedDoctorCode
            LEFT OUTER JOIN MedPatient P ON P.MedPatientCode = LS.MedPatientCode
            LEFT OUTER JOIN MedStudy S ON S.MedStudyCode = LS.MedStudyCode
            SQL;
$where = <<<SQL
            WHERE (:devicePeriod = 0 OR (:devicePeriod = 1 AND LabS.DeviceTime >= :devicePeriodStart AND LabS.DeviceTime < DATEADD('day', 1, :devicePeriodFinish)))
                AND (:studyPeriod = 0 OR (:studyPeriod = 1 AND LS.DirTime >= :studyPeriodStart AND LS.DirTime < DATEADD('day', 1, :studyPeriodFinish)))
                AND ((:medPatientId = 0 AND (LS.MedPatientCode IS NULL OR P.EnterpriseCode = :enterpriseId )) OR LS.MedPatientCode = :medPatientId)
                AND ((:medRefDoctorId = 0 AND (LS.medDoctorCode IS NULL OR Dr.DepartmentCode IN (
                        SELECT D.DepartmentCode
                        FROM Branch Br
                        JOIN Department D ON D.BranchCode = Br.BranchCode
                        WHERE Br.EnterpriseCode = :enterpriseId
                    ))) OR LS.MedDoctorCode = :medRefDoctorId)
                AND (:medLabDriverId = 0 OR LabDev.MedLabDriverCode = :medLabDriverId)
                AND (:medLabDeviceId = 0 OR LabS.MedLabDeviceCode = :medLabDeviceId)
                AND (:medStudyId = 0 OR LS.MedStudyCode = :medStudyId)
                AND (LS.DepartmentCode IS NULL OR LS.DepartmentCode IN (
                        SELECT D.DepartmentCode
                        FROM Branch Br
                        JOIN Department D ON D.BranchCode = Br.BranchCode
                        WHERE Br.EnterpriseCode = :enterpriseId
                    ))
                AND (:onlyNew = 0 OR LabS.MedLogStudyCode IS NULL)
            SQL;
$sqlTotal = <<<SQL
            SELECT COUNT(LabS.LabStudyCode) "total"
            $from
            $where
            SQL;
$stmtTotal = $db->prepare($sqlTotal); $stmtTotal->execute($sqlParams); $ret = new stdClass(); $ret->total = (int)fetchValue($stmtTotal, 'total'); $start = $ret->total < $pageSize ? 0 : isEmptyProperty($params, 'start', 0); $limit = "LIMIT $pageSize OFFSET $start"; $sql = <<<SQL
            SELECT
                LabS.LabStudyCode               "id",                   -- 1
                LabS.LabStudyCode               "labStudyId",           -- 2
                LabS.MedLabDeviceCode           "medLabDeviceId",       -- 3
                LabS.MedLogStudyCode            "medLogStudyId",        -- 4
                LabS.DeviceTime                 "deviceTime",           -- 5
                LabS.DeviceNumber               "deviceNumber",         -- 6
                LabS.Barcode                    "barcode",              -- 7
                LabS.ChangeTime                 "changeTime",           -- 8
                LabS.StudyNumber                "studyNumber",          -- 9
                LabS.CreateTime                 "createTime",           -- 10
                LabDev.Model                    "deviceModel",          -- 11
                LabDev.SerialNumber             "deviceSerialNumber",   -- 12
                COALESCE(LabDrv.ProtocolName, LabDrv.Name)
                                                "driverName",           -- 13
                FN_cmFIO(Dr.ManCode)            "doctorFio",            -- 14
                LS.MedDoctorCode                "medDoctorId",          -- 15
                COALESCE(FN_cmFIO(P.ManCode), LS.PatientFIO)
                                                "patientFio",           -- 16
                LS.MedPatientCode               "medPatientId",         -- 17
                LS.DirTime                      "dirTime",              -- 18
                S.Name                          "studyName",            -- 19
                LS.MedStudyCode                 "medStudyId",           -- 20
                LS.DirNumber                    "dirNumber",            -- 21
                LabDev.Active                   "deviceIsActive",       -- 22
                LabDrv.MedLabDriverCode         "medLabDriverId",       -- 23
                LabDev.Name                     "deviceName",           -- 24
                (
                    SELECT COUNT(*)
                    FROM LabStudyTest V
                    JOIN MedLabDeviceTest T ON T.MedLabDeviceTestCode = V.MedLabDeviceTestCode
                        AND T.Active = 1
                    WHERE V.LabStudyCode = LabS.LabStudyCode
                        AND T.TemplateName <> ''
                )                               "measuredCount",        -- 25
                (
                    SELECT JSON_OBJECT(ARRAY_AGG(T.TemplateName), ARRAY_AGG(COALESCE(RTRIM(TO_CHAR(V.ValueNumber::NUMERIC, 'FM999999999999999999999999999990.99'), '.') , V.ValueString)))
                    FROM LabStudyTest V
                    JOIN MedLabDeviceTest T ON T.MedLabDeviceTestCode = V.MedLabDeviceTestCode
                        AND T.Active = 1
                    WHERE V.LabStudyCode = LabS.LabStudyCode
                        AND T.TemplateName <> ''
                )                               "resultJson"            -- 26
            $from
            $where
            ORDER BY $sort $dir
            $limit
            SQL;
$stmt = $db->prepare($sql); $stmt->execute($sqlParams); $ret->items = $stmt->fetchAll(); return $ret; } public function updateAnalyzerResults($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'LabStudy', 'LabStudyCode', $records, "MedLogStudyCode", "logStudyId"); } } handleJsonRpc('med_lab_analyzerresults');