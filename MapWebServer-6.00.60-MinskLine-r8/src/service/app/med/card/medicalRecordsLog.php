<?php
 require_once(__DIR__ . '/../../init.php'); class medicalRecordsLog { private $db; public function readMedicalRecordsLog($params) { $db = $this->db ?: $this->db = connectDb(); $patientCode = isUndefinedProperty($params, 'patientId'); $doctorCode = isUndefinedProperty($params, 'doctorId'); $dateFrom = isUndefinedProperty($params, 'dateFrom'); $dateTo = isUndefinedProperty($params, 'dateTo'); $documentTemplate = isUndefinedProperty($params, 'documentTemplate'); $dir = (string) isUndefinedProperty($params, 'dir', 'asc'); $sort = (string) isUndefinedProperty($params, 'sort'); $dir = in_array(strtolower($dir), ['asc','desc'], true) ? strtoupper($dir) : 'ASC'; $sortMap = [ 'visitDate' => 'visitDate', 'doctorFio' => 'doctorFio', 'patientFIO' => 'patientFio', 'patientFio' => 'patientFio', 'dateOfBirth' => 'dateOfBirth', 'templateName' => 'templateName', 'dateModified' => 'dateModified', 'createDate' => 'createDate', 'changeDate' => 'changeDate', 'departmentName' => 'departmentName', ]; $sqlOrder = "ORDER BY "; if ($sort !== '' && isset($sortMap[$sort])) { $alias = $sortMap[$sort]; $dateAliases = ['visitDate','dateOfBirth','dateModified','createDate','changeDate']; $nulls = in_array($alias, $dateAliases, true) ? ' NULLS LAST' : ''; $sqlOrder .= "\"$alias\" $dir$nulls, "; $sqlOrder .= "\"dateModified\" DESC NULLS LAST, \"id\" DESC"; } else { $sqlOrder .= "\"dateModified\" DESC NULLS LAST, \"id\" DESC"; } $limit = (int) (isUndefinedProperty($params, 'limit') ?? 20); $start = (int) (isUndefinedProperty($params, 'start') ?? 0); $page = (int) (isUndefinedProperty($params, 'page') ?? 1); if ($start < 0 && $page > 0) { $start = ($page - 1) * $limit; } elseif ($start < 0) { $start = 0; } $where = []; $bind = []; if ($patientCode !== null && $patientCode !== '') { $where[] = 'MVP.MedPatientCode = :patientCode'; $bind[':patientCode'] = $patientCode; } if ($doctorCode !== null && $doctorCode !== '') { $where[] = 'MV.MedDoctorCode = :doctorCode'; $bind[':doctorCode'] = $doctorCode; } if ($dateFrom !== null && $dateTo !== null) { $where[] = 'MVD.CreateTime::date BETWEEN CAST(:startDate AS date) AND CAST(:finishDate AS date)'; $bind[':startDate'] = $dateFrom; $bind[':finishDate'] = $dateTo; } if (!empty($documentTemplate)) { if (!is_array($documentTemplate)) { $documentTemplate = array_filter(array_map('trim', explode(',', (string)$documentTemplate)), 'strlen'); } $documentTemplate = array_values(array_unique($documentTemplate)); if (!empty($documentTemplate)) { $ph = []; foreach ($documentTemplate as $i => $val) { $key = ":tmpl{$i}"; $ph[] = $key; $bind[$key] = $val; } $where[] = 'MVD.htmtemplatecode IN (' . implode(',', $ph) . ')'; } } $fromJoins = <<<SQL
            FROM MedVisitDocument AS MVD
                LEFT JOIN MedVisitPatient AS MVP ON MVD.MedVisitPatientCode = MVP.MedVisitPatientCode
                LEFT JOIN MedVisit AS MV ON MVP.MedVisitCode = MV.MedVisitCode
                LEFT JOIN HtmTemplate AS HT ON HT.HtmTemplateCode = MVD.htmtemplatecode
                LEFT JOIN MedPatient AS MP ON MVP.MedPatientCode = MP.MedPatientCode
                LEFT JOIN MedDoctor AS MD ON MV.meddoctorcode = MD.meddoctorcode
                LEFT JOIN Department AS D ON MD.DepartmentCode = D.DepartmentCode
                LEFT JOIN ManPassport AS MPass ON MP.ManCode = MPass.ManCode
                LEFT JOIN MedVisitPatientFind AS MVPF ON MVP.MedVisitPatientCode = MVPF.MedVisitPatientCode
        SQL;
$whereSql = ''; if (!empty($where)) { $whereSql = "\nWHERE " . implode("\n AND ", $where); } $dataSql = <<<SQL
            SELECT
                MVD.MedVisitDocumentCode           "id",
                HT.Name                            "templateName",
                HT.Code                            "templateCode",
                MV.MedDoctorCode                   "doctorId",
                MVP.MedPatientCode                 "patientCode",
                FN_cmFIO(MP.ManCode)               "patientFio",
                FN_cmFIO(MD.ManCode)               "doctorFio",
                MVD.CreateTime                     "createDate",
                MVD.ChangeTime                     "changeDate",
                COALESCE(MVD.ChangeTime, MVD.CreateTime) "dateModified",
                D.Name                             "departmentName",
                Mpass.DateOfBirth                  "dateOfBirth",
                FN_cmCalcAddress(MP.ManCode, 1)::text "addressDisplay",
                MVPF.VisitDate                     "visitDate",
                HT.type                            "templateType",
                MVD.HtmPageCode                    "pageId",
                MP.NumberHistorySickness           "numberHistorySickness"
            $fromJoins
            $whereSql
            $sqlOrder
            LIMIT :_limit OFFSET :_offset
        SQL;
$countSql = <<<SQL
            SELECT COUNT(*) AS cnt
            $fromJoins
            $whereSql
        SQL;
$stmtCount = $db->prepare($countSql); foreach ($bind as $k => $v) { $stmtCount->bindValue($k, $v); } $stmtCount->execute(); $total = (int) $stmtCount->fetchColumn(); $stmtData = $db->prepare($dataSql); foreach ($bind as $k => $v) { $stmtData->bindValue($k, $v); } $stmtData->bindValue(':_limit', $limit, \PDO::PARAM_INT); $stmtData->bindValue(':_offset', $start, \PDO::PARAM_INT); $stmtData->execute(); $rows = $stmtData->fetchAll(\PDO::FETCH_ASSOC); $root = new \stdClass(); $root->total = $total; $root->items = $rows; return $root; } public function checkVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $patientCode = isUndefinedProperty($params, 'patientId'); $doctorCode = isUndefinedProperty($params, 'doctorId'); $dateVisit = substr(isUndefinedProperty($params, 'visitDate'), 0, 10); $sql = <<<SQL
        SELECT 
            MVD.MedVisitDocumentCode "medVisitDocumentId",
            MVD.MedVisitPatientCode "medVisitPatientCode"
        FROM MedVisitDocument MVD
        LEFT JOIN MedVisitPatient AS MVP ON MVD.MedVisitPatientCode = MVP.MedVisitPatientCode
        LEFT JOIN MedVisit AS MV ON MVP.MedVisitCode = MV.MedVisitCode
        WHERE MVP.MedPatientCode = :patientCode 
          AND MV.MedDoctorCode = :doctorCode
          AND MVD.CreateTime::date = :dateVisit::date
SQL;
$stmt = $db->prepare($sql); $stmt->execute([ ':patientCode' => $patientCode, ':doctorCode' => $doctorCode, ':dateVisit' => $dateVisit, ]); $res = $stmt->fetchAll(PDO::FETCH_ASSOC); return $res; } public function deleteMedicalRecordsLog($params) { $db = $this->db ?: $this->db = connectDb(); $visitDocumentId = isUndefinedProperty($params, 'id'); if ($visitDocumentId) { return deleteSql($db, 'MedVisitDocument', 'MedVisitDocumentCode', $visitDocumentId); } } } handleJsonRpc('medicalRecordsLog');