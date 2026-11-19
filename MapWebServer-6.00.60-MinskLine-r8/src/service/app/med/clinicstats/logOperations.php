<?php
 require_once(__DIR__.'/../../init.php'); class med_clinicstats_logoperations { private $db; public function selectOperations($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
       -----------------------------------------------------------------------------------------------------------------
       MAO.MedAmbulOperationCode "recordId",
       MAO.OperationDate "recordDate",
       COALESCE(MAO.MedAmbulOperationCode, '')::text || '-' "path",
       MAO.OperationDate::date "parentDate",
       17 "recordType",
       17 "docType",
       null "parentId",
       null "parentType",
       4 "sortType",
       -----------------------------------------------------------------------------------------------------------------
       MAO.MedAmbulOperationCode "id",
       MAO.NumberOrder "numberOrder",
       MAO.OperationDate "operationDate",
       MAO.MedDoctorCode "doctorId",
       MAO.MedProfessionCode "professionId",
       MAO.DepartmentCode "departmentId",
       MAO.BuhMolCodeSister "sisterId",
       MAO.MedOperationCode "operationId",
       MAO.Anesthesia "anesthesia",
       MAO.Description "descriptioin",
       MAO.MedDiagnosisCode "diagnosisId",
       FN_cmFIO(MD.ManCode) "doctorFio",
--       FN_cmFIO(MD.ManCode) as DoctorFIO_OLD,
       MD1.Cipher1 "diagnosisCipher",
       MD1.Name "diagnosisName",
--       (MD1.Cipher1 ) as DiagnosisCipher_OLD,
--       MD1.Name DiagnosisName_OLD,
       MO.Name "operationName",
       COALESCE(B.Name, '') || ' - ' || COALESCE(D.Name, '' ) "departmentName",
       MPR.Name "professionName",
       CASE (MAO.BuhMolCodeSister is not NULL)
            WHEN true THEN fn_cmFIO(M.ManCode) || '(' || COALESCE(B1.Name, '') || ' - ' || COALESCE(D1.Name, '' ) || ')'
            WHEN false THEN ''
       END "sisterFio",
       COALESCE(MAO.OperationCount, 1) "operationCount"
from MedAmbulOperation MAO
     left join MedPatient MP ON MP.MedPatientCode = MAO.MedPatientCode
     left join MedDoctor MD ON MD.MedDoctorCode = MAO.MedDoctorCode
     left join MedDiagnosis MD1 ON MD1.MedDiagnosisCode = MAO.MedDiagnosisCode
     left join MedOperation MO ON MO.MedOperationCode = MAO.MedOperationCode
     left join Department D ON D.DepartmentCode = MAO.DepartmentCode
     left join Branch B ON B.BranchCode = D.BranchCode
     left join MedProfession MPR ON MPR.MedProfessionCode = MAO.MedProfessionCode
     left join BuhMol M ON M.BuhMolCode = MAO.BuhMolCodeSister
     left join Department D1 ON D1.DepartmentCode = M.DepartmentCode
     left join Branch B1 ON B1.BranchCode = D1.BranchCode
where MAO.EnterpriseCode = $enterpriseId
  and MAO.MedPatientCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($params, 'patientId') ]); return $stmt->fetchAll(); } public function readLogOperations($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $id = isEmptyProperty($params, 'id'); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : null; $departmentsId = isEmptyProperty($params, 'departmentsId'); $doctorsId = isEmptyProperty($params, 'doctorsId'); $specialtiesId = isEmptyProperty($params, 'specialtiesId'); $operationsId = isEmptyProperty($params, 'operationsId'); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $isCard = isEmptyProperty($params, 'isCard', false); $sort = isEmptyProperty($params, 'sort', 'onDate'); $dir = isEmptyProperty($params, 'dir', 'DESC'); $patientId = isEmptyProperty($params, 'patientId'); if (isset($params->page) && (int)$params->page > 0) { $page = (int)$params->page; $offset = ($page - 1) * $limit; } else { $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; } $sqlLimit = "LIMIT $limit offset $offset"; $sqlCard = ''; if ($isCard) { $sqlCard = <<<SQL
   MAO.MedAmbulOperationCode "recordId",
   MAO.OperationDate "recordDate",
   COALESCE(MAO.MedAmbulOperationCode, '')::text || '-' "path",
   MAO.OperationDate::date "parentDate",
   17 "recordType",
   17 "docType",
   null "parentId",
   null "parentType",
   4 "sortType",
SQL;
} $sqlFromWhere = "
    FROM MedAmbulOperation MAO
    JOIN Department D ON D.DepartmentCode = MAO.DepartmentCode
        and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
    left join Branch B ON B.BranchCode = D.BranchCode
    LEFT OUTER JOIN MedOperation MO ON MO.MedOperationCode = MAO.MedOperationCode
    LEFT OUTER JOIN MedProfession MPROF ON MPROF.MedProfessionCode = MAO.MedProfessionCode
    LEFT OUTER JOIN MedDoctor MDOC ON MDOC.MedDoctorCode = MAO.MedDoctorCode
    LEFT OUTER JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MAO.MedDiagnosisCode
    LEFT OUTER JOIN MedPatient MP ON MP.MedPatientCode = MAO.MedPatientCode
    LEFT OUTER JOIN MedDiagnosis MD2 ON MD2.MedDiagnosisCode = MAO.MedDiagnosisCodeAfter
    LEFT OUTER JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MAO.CiszResourceValueCodeAnesthesiaType
    LEFT JOIN LATERAL (
        SELECT
        FN_CmFio(MD.Mancode, 1) AS SurgeonName,
        CRV.Name                AS SurgeonRoleName
        FROM MedAmbulOperationTeam OT
        LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = OT.MedDoctorCode
        JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = OT.CiszResourceValueCodeRole AND CRV.Code = 'operator'
        WHERE OT.MedAmbulOperationCode = MAO.MedAmbulOperationCode
        LIMIT 1
    ) MAOT ON true
    WHERE MAO.EnterpriseCode = $enterpriseId"; if (isset($id)) { $sqlFromWhere .= " and MAO.MedAmbulOperationCode = $id"; } if (isset($startDate)) { $sqlFromWhere .= " and MAO.OperationDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MAO.OperationDate <= $finishDate"; } if (isset($departmentsId)) { $sqlFromWhere .= " and MAO.DepartmentCode = $departmentsId"; } if (isset($doctorsId)) { $sqlFromWhere .= " and MAO.MedDoctorCode = $doctorsId"; } if (isset($specialtiesId)) { $sqlFromWhere .= " and MAO.MedProfessionCode = $specialtiesId"; } if (isset($operationsId)) { $sqlFromWhere .= " and MAO.MedOperationCode = $operationsId"; } if (isset($patientId)) { $sqlFromWhere .= " AND MAO.MedPatientCode = $patientId"; } $sql = <<<"EOT"
SELECT
       COUNT(*) "total"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
SELECT
    MAO.MedAmbulOperationCode "id",
    $sqlCard
    MAO.NumberOrder "number",
    MAO.OperationDate "onDate",
    COALESCE(MAO.OperationCount, 1) "count",
    MAO.DepartmentCode "departmentsId",
    COALESCE(B.Name, '') || ' - ' || COALESCE(D.Name, '' ) "departmentsName",
    MAO.MedDoctorCode "doctorsId",
    fn_cmFIO(MDOC.ManCode, 1) "doctorsName",
    MAO.MedProfessionCode "specialtiesId",
    MPROF.Name "specialtiesName",
    MAO.MedOperationCode "operationsId",
    MO.Name "operationsName",
    MAO.meddiagnosiscode "diagnosisId",
    CASE WHEN MAO.MedDiagnosisCode IS NOT NULL THEN MD.Cipher1 || ' - ' || MD.Name END "diagnosisName",
    MAO.medpatientcode "patientId",
    fn_cmFIO(MP.ManCode) "patientName",
    MAO.Description "description",
    MAO.Anesthesia "anesthesia",
    MAO.BuhMolCodeSister "nurseId",
    MAO.OperationDateFinish "finishDate",
    MAO.OperationDateFinish "finishTime",
    MAO.OperationDate "startTime",
    MAO.MedDiagnosisCodeAfter "diagnosisFinalId",
    CASE WHEN MAO.MedDiagnosisCodeAfter IS NOT NULL THEN MD2.Cipher1 || ' - ' || MD2.Name END "diagnosisFinalName",
    MAO.CiszResourceValueCodeAnesthesiaType "anesthesiaCode",
    CRV.Name "anesthesiaName",
    MAOT.SurgeonName "surgeonName",
    MAOT.SurgeonRoleName "surgeonRoleName"
$sqlFromWhere
                ORDER BY "$sort" $dir
$sqlLimit
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); if ($isCard) { return $stmt->fetchAll(); } else { $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } } public function createLogOperations($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<SQL
            INSERT INTO MedAmbulOperation (
                NumberOrder, OperationDate, Anesthesia,
                Description, MedPatientCode, MedDoctorCode,
                MedProfessionCode, MedOperationCode, MedDiagnosisCode,
                DepartmentCode, OperationCount, EnterpriseCode,
                BuhMolCodeSister, CiszResourceValueCodeAnesthesiaType, MedDiagnosisCodeAfter,
                OperationDateFinish, ciszDiagnosisExtraCode, ciszDiagnosisExtraCodeAfter
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?)
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $number = isEmptyProperty($row, 'number'); $diagnoses = isEmptyProperty($row, 'diagnoses'); $diagnosisCiszId = null; $diagnosisCiszFinalId = null; if ($diagnoses) { foreach ($diagnoses as $diag) { if ($diag->diagnosisType === 1) { $diagnosisCiszId = $this->createLogOperationDiagnoses($diag, true)[0]->id; } else if ($diag->diagnosisType === 2) { $diagnosisCiszFinalId = $this->createLogOperationDiagnoses($diag, true)[0]->id; } } } $stmt->execute([ $number, isEmptyProperty($row, 'onDate'), isEmptyProperty($row, 'anesthesia'), isEmptyProperty($row, 'description'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'doctorsId'), isEmptyProperty($row, 'specialtiesId'), isEmptyProperty($row, 'operationsId'), isEmptyProperty($row, 'diagnosisId'), isEmptyProperty($row, 'departmentsId'), isEmptyProperty($row, 'count'), $enterpriseId, isEmptyProperty($row, 'nurseId'), isEmptyProperty($row, 'anesthesiaCode'), isEmptyProperty($row, 'diagnosisFinalId'), isEmptyProperty($row, 'finishDate'), $diagnosisCiszId, $diagnosisCiszFinalId ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedAmbulOperation'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readLogOperations($rec); } public function readLogOperationDocuments($params) { $db = $this->db ?: $this->db = connectDb(); $medAmbulOperationCode = isEmptyProperty($params, 'medAmbulOperationCode'); $sql = <<<SQL
    SELECT
        MAO.medambuloperationcode "medambuloperationcode",
        HU.name "name",
        HU.value "value",
        HU.longvalue "longvalue",
        MVD.htmpagecode "pageId",
        MVD.MedVisitDocumentCode "documentId",
        HT.name "pageName",
        MAOT.SurgeonName "surgeonName",
        MAOT.SurgeonRoleName "surgeonRoleName"
    FROM MedAmbulOperation MAO
    LEFT JOIN MedVisitDocument MVD ON MVD.medambuloperationcode = MAO.medambuloperationcode
    LEFT JOIN HtmUnit HU ON HU.htmpagecode = MVD.htmpagecode
        AND HU.name IN (
            'OperationDate',
            'OperationNAME',
            'DoctorFIO',
            'DepartmentName',
            'ProfessionName',
            'OVTB1DT3'
        )
    LEFT JOIN HtmTemplate HT ON HT.htmtemplatecode = MVD.htmtemplatecode
        LEFT JOIN LATERAL (
        SELECT
        FN_CmFio(MD.Mancode, 1) AS SurgeonName,
        CRV.Name                AS SurgeonRoleName
        FROM MedAmbulOperationTeam OT
        LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = OT.MedDoctorCode
        JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = OT.CiszResourceValueCodeRole AND CRV.Code = 'operator'
        WHERE OT.MedAmbulOperationCode = MAO.MedAmbulOperationCode
        LIMIT 1
    ) MAOT ON true
    WHERE MAO.medambuloperationcode = ?
    SQL;
$stmt = $db->prepare($sql); $stmt->execute([$medAmbulOperationCode]); $docData = $stmt->fetchAll(); $documents = []; foreach ($docData as $row) { $docId = $row['documentId']; if (!isset($documents[$docId])) { $documents[$docId] = [ 'date' => '', 'operationName' => '', 'doctorInfo' => '', 'pageId' => (string)$row['pageId'], 'documentId' => (string)$row['documentId'], 'medambuloperationcode' => (string)$row['medambuloperationcode'], 'pageName' => $row['pageName'], '_doctorInfoParts' => new stdClass() ]; $documents[$docId]['_doctorInfoParts']->fio = ''; $documents[$docId]['_doctorInfoParts']->department = ''; $documents[$docId]['_doctorInfoParts']->profession = ''; } switch ($row['name']) { case 'OperationDate': case 'OVTB1DT3': $documents[$docId]['date'] = trim($row['value'] ?? ''); break; case 'OperationNAME': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'name', '')); $documents[$docId]['operationName'] = empty($displayValue) ? $name : $displayValue; } break; case 'DoctorFIO': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $fio = trim(isEmptyProperty($json, 'fio', '')); $documents[$docId]['_doctorInfoParts']->fio = empty($displayValue) ? $fio : $displayValue; } break; case 'DepartmentName': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $nameWithParent = trim(isEmptyProperty($json, 'nameWithParent', '')); $documents[$docId]['_doctorInfoParts']->department = empty($displayValue) ? $nameWithParent : $displayValue; } break; case 'ProfessionName': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'name', '')); $documents[$docId]['_doctorInfoParts']->profession = empty($displayValue) ? $name : $displayValue; } break; } if (empty($documents[$docId]['_doctorInfoParts']->fio)) { $documents[$docId]['_doctorInfoParts']->fio = $row['surgeonName']; } if (empty($documents[$docId]['_doctorInfoParts']->profession)) { $documents[$docId]['_doctorInfoParts']->profession = $row['surgeonRoleName']; } } $result = []; foreach ($documents as $doc) { if (!isset($doc['pageName']) || !$doc['pageName'] || !isset($doc['pageName'])) continue; $parts = $doc['_doctorInfoParts']; unset($doc['_doctorInfoParts']); $doc['doctorInfo'] = implode(', ', array_filter([ $parts->fio, $parts->profession, $parts->department ])); $result[] = $doc; } return $result; } public function readLogOperationDocumentForCreateForm($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId'); $medAmbulOperationCode = isEmptyProperty($params, 'medAmbulOperationCode'); $sql = <<<SQL
    SELECT
        HU.name "name",
        HU.value "value",
        HU.longvalue "longvalue",
        MVD.htmpagecode "pageId",
        MVD.MedVisitDocumentCode "documentId",
        HT.name "pageName"
    FROM MedVisitDocument MVD
    LEFT JOIN HtmUnit HU ON HU.htmpagecode = MVD.htmpagecode
        AND HU.name IN (
            'OperationNumber',
            'OperationDate',
            'DoctorFIO',
            'ProfessionName',
            'DepartmentName',
            'MedDoctorCode',
            'DepartmentCode',
            'MedProfessionCode',
            'OperationNAME',
            'Description',
            'MedOperationCode1',
            'diagnosisMain',
            'MedDiagnosisCode',
            'Anesthesia1',
            'OperSisterNAME',
            'BuhMolCodeSister',
            'MedPatientCode',
            'PatientFIO'
        )
    LEFT JOIN HtmTemplate HT ON HT.htmtemplatecode = MVD.htmtemplatecode
    WHERE MVD.medvisitdocumentcode = ?
    SQL;
$stmt = $db->prepare($sql); $stmt->execute([$documentId]); $docData = $stmt->fetchAll(); $documentData = new stdClass(); $documentData->medAmbulOperationCode = $medAmbulOperationCode; $documentData->operationNumber = ''; $documentData->operationDate = ''; $documentData->doctorsId = ''; $documentData->specialtiesId = ''; $documentData->specialtiesName = ''; $documentData->departmentsId = ''; $documentData->departmentsName = ''; $documentData->operationsId = ''; $documentData->operationsName = ''; $documentData->operationDescription = ''; $documentData->diagnosisId = ''; $documentData->nurseId = ''; $documentData->anesthesia = ''; $documentData->patientId = ''; $documentData->patientName = ''; foreach ($docData as $row) { switch ($row['name']) { case 'OperationNumber': $documentData->operationNumber = trim($row['value'] ?? ''); break; case 'OperationDate': $documentData->operationDate = trim($row['value'] ?? ''); break; case 'MedDoctorCode': $documentData->doctorsId = trim($row['value'] ?? ''); break; case 'MedProfessionCode': $documentData->specialtiesId = trim($row['value'] ?? ''); break; case 'ProfessionName': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'name', '')); $documentData->specialtiesName = empty($displayValue) ? $name : $displayValue; } break; case 'DepartmentCode': $documentData->departmentsId = trim($row['value'] ?? ''); break; case 'DepartmentName': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'name', '')); $documentData->departmentsName = empty($displayValue) ? $name : $displayValue; } break; case 'MedOperationCode1': $documentData->operationsId = trim($row['value'] ?? ''); break; case 'OperationNAME': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'name', '')); $documentData->operationsName = empty($displayValue) ? $name : $displayValue; } break; case 'Description': $documentData->operationDescription = trim($row['value'] ?? ''); break; case 'MedDiagnosisCode': $documentData->diagnosisId = trim($row['value'] ?? ''); break; case 'OperSisterNAME': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $documentData->nurseId = trim(isEmptyProperty($json, 'id', '')); } break; case 'Anesthesia1': $documentData->anesthesia = trim($row['value'] ?? '') ?: trim($row['longvalue'] ?? ''); break; case 'MedPatientCode': $documentData->patientId = trim($row['value'] ?? ''); break; case 'PatientFIO': $value = $row['longvalue'] ?: $row['value']; $json = safe_json_decode(trim($value ?? '')); if ($json) { $displayValue = trim(isEmptyProperty($json, 'displayValue', '')); $name = trim(isEmptyProperty($json, 'fio', '')); $documentData->patientName = empty($displayValue) ? $name : $displayValue; } break; } } return $documentData; } public function getLogOperationDocuments($ambulOperationRecordIds) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            select
                --------------------------------------------------------------------------------------
                MVD.medVisitDocumentCode    "recordId",
                MVD.createTime              "recordDate",
                COALESCE(MVD.medambuloperationcode, '')::text || '-' || COALESCE(MVD.medVisitDocumentCode, '')::text
                                            "path",
                MAO.operationdate::date     "parentDate",
                16                          "recordType",
                17                          "docType",
                MVD.medambuloperationcode   "parentId",
                18                          "parentType",
                4                           "sortType",
                --------------------------------------------------------------------------------------
                HT.name                     "pageName",
                U.userCode                  "userId",
                U.nick                      "userName",
                MVD.htmPageCode             "pageId",
                HP.createTime               "createTime",
                MVD.changeTime              "changeTime",
                COALESCE(HP.ready, 0)       "ready",
                MVD.htmTemplateCode         "templateId",
                HT.htmPageCode              "templatePageId"
            from MedVisitDocument MVD
            --
            left join Users U on U.userCode = MVD.userCode
            left join htmPage HP on HP.htmPageCode = MVD.htmPageCode
            left join htmTemplate HT on HT.htmTemplateCode = MVD.htmTemplateCode
            left join MedAmbulOperation MAO on MAO.medambuloperationcode = MVD.medambuloperationcode
            where MVD.medambuloperationcode in ($ambulOperationRecordIds) -- гл.записи
            and ((MVD.type = 1 and COALESCE(HP.isDeleted, 0) = 0) or
                (MVD.type = 2 and ((0 = 1 and MVD.deleteTime IS NOT NULL)
                                    or (0 = 0 and MVD.deleteTime IS NULL))))
EOT;
return selectSql($db, $sql); } public function deleteLogOperationDocuments($params) { $this->deleteDocument($params); } public function updateLogOperations($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            UPDATE MedAmbulOperation SET
                NumberOrder         = CASE WHEN ? = 1 THEN ? ELSE NumberOrder END,
                OperationDate       = CASE WHEN ? = 1 THEN ? ELSE OperationDate END,
                Anesthesia          = CASE WHEN ? = 1 THEN ? ELSE Anesthesia END,
                Description         = CASE WHEN ? = 1 THEN ? ELSE Description END,
                MedPatientCode      = CASE WHEN ? = 1 THEN ? ELSE MedPatientCode END,
                MedDoctorCode       = CASE WHEN ? = 1 THEN ? ELSE MedDoctorCode END,
                MedProfessionCode   = CASE WHEN ? = 1 THEN ? ELSE MedProfessionCode END,
                MedOperationCode    = CASE WHEN ? = 1 THEN ? ELSE MedOperationCode END,
                MedDiagnosisCode    = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCode END,
                Departmentcode      = CASE WHEN ? = 1 THEN ? ELSE DepartmentCode END,
                OperationCount      = CASE WHEN ? = 1 THEN ? ELSE OperationCount END,
                BuhMolCodeSister    = CASE WHEN ? = 1 THEN ? ELSE BuhMolCodeSister END,
                CiszResourceValueCodeAnesthesiaType = CASE WHEN ? = 1 THEN ? ELSE CiszResourceValueCodeAnesthesiaType END,
                MedDiagnosisCodeAfter               = CASE WHEN ? = 1 THEN ? ELSE MedDiagnosisCodeAfter END,
                OperationDateFinish                 = CASE WHEN ? = 1 THEN ? ELSE OperationDateFinish END,
                CiszDiagnosisExtraCode              = CASE WHEN ? = 1 THEN ? ELSE CiszDiagnosisExtraCode END,
                CiszDiagnosisExtraCodeAfter         = CASE WHEN ? = 1 THEN ? ELSE CiszDiagnosisExtraCodeAfter END
            WHERE MedAmbulOperationCode = ?
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $rec = new stdClass; try { foreach ($records as $row) { $diagnoses = isEmptyProperty($row, 'diagnoses'); $diagnosisCiszId = null; $diagnosisCiszFinalId = null; if ($diagnoses) { foreach ($diagnoses as $diag) { if ($diag->diagnosisType === 1) { $diagnosisCiszId = $this->createLogOperationDiagnoses($diag, true)[0]->id; } else if ($diag->diagnosisType === 2) { $diagnosisCiszFinalId = $this->createLogOperationDiagnoses($diag, true)[0]->id; } } } $flagBefore = $diagnosisCiszId ? 1 : 0; $flagAfter = $diagnosisCiszFinalId ? 1 : 0; $stmt->execute([ propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'anesthesia'), isUndefinedProperty($row, 'anesthesia'), propertyExistsSQL($row, 'description'), isUndefinedProperty($row, 'description'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'doctorsId'), isUndefinedProperty($row, 'doctorsId'), propertyExistsSQL($row, 'specialtiesId'), isUndefinedProperty($row, 'specialtiesId'), propertyExistsSQL($row, 'operationsId'), isUndefinedProperty($row, 'operationsId'), propertyExistsSQL($row, 'diagnosisId'), isUndefinedProperty($row, 'diagnosisId'), propertyExistsSQL($row, 'departmentsId'), isUndefinedProperty($row, 'departmentsId'), propertyExistsSQL($row, 'count'), isUndefinedProperty($row, 'count'), propertyExistsSQL($row, 'nurseId'), isUndefinedProperty($row, 'nurseId'), propertyExistsSQL($row, 'anesthesiaCode'), isUndefinedProperty($row, 'anesthesiaCode'), propertyExistsSQL($row, 'diagnosisFinalId'), isUndefinedProperty($row, 'diagnosisFinalId'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), $flagBefore, $diagnosisCiszId, $flagAfter, $diagnosisCiszFinalId, $row->id ]); $rec->id = $row->id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readLogOperations($rec); } public function deleteLogOperations($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedAmbulOperation', 'MedAmbulOperationCode', $records); } public function getNextCipher() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(NumberOrder), 20)) as varchar(20))
    FROM
        MedAmbulOperation
    WHERE
        MedAmbulOperationCode = (SELECT max(MedAmbulOperationCode) from MedAmbulOperation)
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "number"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'number'); return $cipher; } public function createVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
INSERT INTO MedVisitDocument (
    Type, UserCode,
    HtmTemplateCode, Note, ChangeTime,
    CreateTime, MedAmbulOperationCode
)
VALUES (
    ?, ?,
    ?, 'Новая запись', LOCALTIMESTAMP,
    LOCALTIMESTAMP, ?
)
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { $stmt->execute([ isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId'), isEmptyProperty($params, 'medAmbulOperationCode') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createAndFillVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $fields = isEmptyProperty($params, 'fields'); $sql = <<<SQL
        INSERT INTO MedVisitDocument (
            Type, UserCode,
            HtmTemplateCode, Note, ChangeTime,
            CreateTime, MedAmbulOperationCode
        )
        VALUES (
            ?, ?,
            ?, 'Новая запись', LOCALTIMESTAMP,
            LOCALTIMESTAMP, ?
        )
        SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); $ret = new stdClass(); try { $stmt->execute([ isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId'), isEmptyProperty($params, 'medAmbulOperationCode') ]); $ret->id = +$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createEmptyVisitDocument($params) { $db = $this->db ?: ($this->db = connectDb()); $enterpriseId = getSessionValue('mapEnterpriseCode'); $nextCipher = $this->getNextCipher(); $sqlAmbulOperation = <<<SQL
            INSERT INTO MedAmbulOperation (
                NumberOrder, OperationDate, MedPatientCode,
                MedDoctorCode, MedProfessionCode, DepartmentCode,
                OperationCount, EnterpriseCode
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL;
$sqlVisitDocument = <<<SQL
            INSERT INTO MedVisitDocument (
                Type, UserCode,
                HtmTemplateCode, Note, ChangeTime,
                CreateTime, MedAmbulOperationCode
            )
            VALUES (?, ?, ?, 'Новая запись', LOCALTIMESTAMP, LOCALTIMESTAMP, ?)
        SQL;
$stmtAmbul = $db->prepare($sqlAmbulOperation); $stmtVisit = $db->prepare($sqlVisitDocument); $db->beginTransaction(); try { $stmtAmbul->execute([ $nextCipher, date('Y-m-d H:i:s'), isEmptyProperty($params, 'patientId'), isEmptyProperty($params, 'doctorId'), isEmptyProperty($params, 'professionId'), isEmptyProperty($params, 'departmentId'), 1, $enterpriseId, ]); $ret = new stdClass(); $ret->medAmbulOperationId = $db->lastInsertId('seq_MedAmbulOperation'); $stmtVisit->execute([ isEmptyProperty($params, 'type'), getSessionValue('mapUserCode'), isEmptyProperty($params, 'templateId'), $ret->medAmbulOperationId ]); $ret->id = (int)$db->lastInsertId('seq_MedVisitDocument'); $db->commit(); return $ret; } catch (Throwable $e) { $db->rollback(); throw $e; } } public function updateVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId', 0); $pageId = isEmptyProperty($params, 'pageId'); $medAmbulOperationCode = isEmptyProperty($params, 'medAmbulOperationCode'); $sql = <<<SQL
UPDATE MedVisitDocument SET
    HtmPageCode = ?,
    ChangeTime = LOCALTIMESTAMP,
    MedAmbulOperationCode = ?
WHERE MedVisitDocumentCode = ?
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $pageId, $medAmbulOperationCode, $documentId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readLogOperations((object)['id' => $medAmbulOperationCode]); } public function updateVisitDocumentBeforeLogOperationAdd($params) { $db = $this->db ?: $this->db = connectDb(); $documentId = isEmptyProperty($params, 'documentId', 0); $pageId = isEmptyProperty($params, 'pageId'); $sql = <<<SQL
UPDATE MedVisitDocument SET
    HtmPageCode = ?,
    ChangeTime = LOCALTIMESTAMP
WHERE MedVisitDocumentCode = ?
SQL;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ $pageId, $documentId ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDocument($params) { $db = $this->db ?: $this->db = connectDb(); $visitDocumentId = isEmptyProperty($params, 'visitDocumentId'); $medAmbulOperationCode = isEmptyProperty($params, 'medAmbulOperationCode'); if ($medAmbulOperationCode) { deleteSql($db, 'MedAmbulOperation', 'MedAmbulOperationCode', $medAmbulOperationCode); } return deleteSql($db, 'MedVisitDocument', 'MedVisitDocumentCode', $visitDocumentId); } public function deleteVisitDocument($params) { $db = $this->db ?: $this->db = connectDb(); $this->deleteDocument($params); return true; } public function readLogOperationsTeam($params = null) { $db = $this->db ?: $this->db = connectDb(); $operationId = isEmptyProperty($params, 'operationId'); $sql = <<<"EOT"
SELECT 
    MAOT.MedAmbulOperationTeamCode "id",
    MAOT.MedAmbulOperationCode "operationId",
    MAOT.MedDoctorCode "doctorsId",
    FN_CMFio(MD.ManCode) "doctorsName",
    MAOT.BuhMolCode "nurseId",
    FN_CMFio(BM.ManCode) "nurseName",
    MAOT.StartTime "startDate",
    MAOT.StartTime "startTime",
    MAOT.FinishTime "finishDate",
    MAOT.FinishTime "finishTime",
    MAOT.CiszResourceValueCodeRole "roleCode",
    CRV.Name "roleName",
    CASE WHEN MAOT.MedDoctorCode IS NOT NULL THEN FN_CMFio(MD.ManCode) ELSE FN_CMFio(BM.ManCode) END "participantName"
FROM MedAmbulOperationTeam MAOT
LEFT JOIN MedDoctor MD ON MD.MedDoctorCode = MAOT.MedDoctorCode
LEFT JOIN BuhMol BM ON BM.BuhMolCode = MAOT.BuhMolCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceValueCode = MAOT.CiszResourceValueCodeRole
WHERE MedAmbulOperationCode = $operationId
ORDER BY 1
EOT;
$records = selectSql($db, $sql); return $records; } public function createLogOperationsTeam($records) { $db = $this->db ?: $this->db = connectDb(); return insertSql($db, 'MedAmbulOperationTeam', $records, 'MedAmbulOperationCode MedDoctorCode BuhMolCode CiszResourceValueCodeRole StartTime FinishTime', 'operationId doctorsId nurseId roleCode startDate finishDate'); } public function updateLogOperationsTeam($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'MedAmbulOperationTeam', 'MedAmbulOperationTeamCode', $records, "MedDoctorCode BuhMolCode CiszResourceValueCodeRole StartTime FinishTime", "doctorsId nurseId roleCode startDate finishDate" ); } public function deleteLogOperationsTeam($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedAmbulOperationTeam', 'MedAmbulOperationTeamCode', $records); } public function createLogOperationDiagnoses($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<EOT
INSERT INTO CiszDiagnosisExtra (CiszDiagnosisExtraCode, CiszResourceValueCodeCancerStage, CiszResourceValueCodeDiagKind, ClinicalDiagnosis, Note)
VALUES (?, ?, ?, ?, ?)
ON CONFLICT (CiszDiagnosisExtraCode) DO UPDATE SET
    CiszResourceValueCodeCancerStage = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeCancerStage ELSE CiszDiagnosisExtra.CiszResourceValueCodeCancerStage END,
    CiszResourceValueCodeDiagKind = CASE WHEN ? = 1 THEN EXCLUDED.CiszResourceValueCodeDiagKind ELSE CiszDiagnosisExtra.CiszResourceValueCodeDiagKind END,
    ClinicalDiagnosis = CASE WHEN ? = 1 THEN EXCLUDED.ClinicalDiagnosis ELSE CiszDiagnosisExtra.ClinicalDiagnosis END,
    Note = CASE WHEN ? = 1 THEN EXCLUDED.Note ELSE CiszDiagnosisExtra.Note END
RETURNING CiszDiagnosisExtraCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $isInsert = !is_int($row->id); $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'cancerStageId'), isUndefinedProperty($row, 'kindDiagId'), isUndefinedProperty($row, 'clinicalDiagnosis'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'cancerStageId'), propertyExistsSQL($row, 'kindDiagId'), propertyExistsSQL($row, 'clinicalDiagnosis'), propertyExistsSQL($row, 'note') ]); $rec->id = fetchValue($stmt, 'id'); if (isset($row->anatomic)) { $this->updateAnatomic((object)[ 'id' => $rec->id, 'items' => $row->anatomic ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readLogOperationDiagnoses($params = null) { $db = $this->db ?: $this->db = connectDb(); $operationId = isEmptyProperty($params, 'operationId'); $sql = <<<"EOT"
SELECT
    1                                    "diagnosisType",
    CDE.CiszDiagnosisExtraCode           "id",
    CDE.CiszResourceValueCodeCancerStage "cancerStageId",
    CDE.CiszResourceValueCodeDiagKind    "kindDiagId",
    CRV.Name                             "kindDiagName",
    CDE.ClinicalDiagnosis                "clinicalDiagnosis",
    CDE.Note                             "note",
    CDEA2.AnatomicCodes                  "anatomic",
    MAO.MedDiagnosisCode                 "diagnosisId",
    CASE WHEN MAO.MedDiagnosisCode IS NOT NULL THEN MD.Cipher1 || ' - ' || MD.Name END "diagnosisName"
FROM MedAmbulOperation MAO
LEFT JOIN CiszDiagnosisExtra CDE ON CDE.CiszDiagnosisExtraCode = MAO.CiszDiagnosisExtraCode
LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MAO.MedDiagnosisCode
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceVAlueCode = CDE.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
WHERE MAO.MedAmbulOperationCode = $operationId

UNION ALL

SELECT
    2                                    "diagnosisType",
    CDE.CiszDiagnosisExtraCode           "id",
    CDE.CiszResourceValueCodeCancerStage "cancerStageId",
    CDE.CiszResourceValueCodeDiagKind    "kindDiagId",
    CRV.Name                             "kindDiagName",
    CDE.ClinicalDiagnosis                "clinicalDiagnosis",
    CDE.Note                             "note",
    CDEA2.AnatomicCodes                  "anatomic",
    MAO.MedDiagnosisCodeAfter            "diagnosisId",
    CASE WHEN MAO.MedDiagnosisCodeAfter IS NOT NULL THEN MD.Cipher1 || ' - ' || MD.Name END "diagnosisName"
FROM MedAmbulOperation MAO
LEFT JOIN CiszDiagnosisExtra CDE ON CDE.CiszDiagnosisExtraCode = MAO.CiszDiagnosisExtraCodeAfter
LEFT JOIN MedDiagnosis MD ON MD.MedDiagnosisCode = MAO.MedDiagnosisCodeAfter
LEFT JOIN CiszResourceValue CRV ON CRV.CiszResourceVAlueCode = CDE.CiszResourceValueCodeDiagKind
LEFT JOIN (
    SELECT
        CDEA.CiszDiagnosisExtraCode,
        string_agg(
            CDEA.CiszResourceValueCodeAnatomic, ';'
            ORDER BY CDEA.CiszDiagnosisExtraCode
        ) AS AnatomicCodes
    FROM CiszDiagnosisExtraAnatomic CDEA
    GROUP BY CDEA.CiszDiagnosisExtraCode
) CDEA2 ON CDEA2.CiszDiagnosisExtraCode = CDE.CiszDiagnosisExtraCode
WHERE MAO.MedAmbulOperationCode = $operationId
ORDER BY 1
EOT;
$records = selectSql($db, $sql); return $records; } public function readAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $items = isUndefinedProperty($params, 'items'); if (!$items) { return []; } $sql = <<<SQL
SELECT
    CRV.CiszResourceValueCode AS "id",
    CRV.Name AS "name"
FROM CiszResourceValue CRV
SQL;
$conditions = []; $bindings = []; $incomingIds = []; if (is_array($items)) { foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } } if (!empty($incomingIds)) { $placeholders = implode(',', array_map(fn($k) => ":item$k", array_keys($incomingIds))); $conditions[] = "CRV.CiszResourceValueCode IN ($placeholders)"; foreach ($incomingIds as $k => $id) { $bindings[":item$k"] = $id; } } if (!empty($conditions)) { $sql .= ' WHERE ' . implode(' AND ', $conditions); } $stmt = $db->prepare($sql); $stmt->execute($bindings); return $stmt->fetchAll(PDO::FETCH_ASSOC); } public function updateAnatomic($params = null) { $db = $this->db ?: $this->db = connectDb(); $diagnosisCiszId = isEmptyProperty($params, 'id'); $items = isUndefinedProperty($params, 'items'); if (!$diagnosisCiszId || !is_array($items)) { return true; } $sql = "
        SELECT CiszResourceValueCodeAnatomic
        FROM CiszDiagnosisExtraAnatomic
        WHERE CiszDiagnosisExtraCode = :inspectId
    "; $stmt = $db->prepare($sql); $stmt->execute(['inspectId' => $diagnosisCiszId]); $dbRows = $stmt->fetchAll(PDO::FETCH_COLUMN); $dbIds = array_map('intval', $dbRows); $incomingIds = []; foreach ($items as $item) { if (!empty($item->id)) { $incomingIds[] = (int)$item->id; } } $incomingIds = array_unique($incomingIds); $toInsert = array_diff($incomingIds, $dbIds); $toDelete = array_diff($dbIds, $incomingIds); if (!empty($toInsert)) { $insertSql = "
            INSERT INTO CiszDiagnosisExtraAnatomic
            (CiszDiagnosisExtraCode, CiszResourceValueCodeAnatomic)
            VALUES (:inspectId, :anatomicId)
        "; $insertStmt = $db->prepare($insertSql); foreach ($toInsert as $anatomicId) { $insertStmt->execute([ 'inspectId' => $diagnosisCiszId, 'anatomicId' => $anatomicId ]); } } if (!empty($toDelete)) { $placeholders = implode(',', array_fill(0, count($toDelete), '?')); $deleteSql = "
            DELETE FROM CiszDiagnosisExtraAnatomic
            WHERE CiszDiagnosisExtraCode = ?
            AND CiszResourceValueCodeAnatomic IN ($placeholders)
        "; $deleteStmt = $db->prepare($deleteSql); $deleteStmt->execute(array_merge([$diagnosisCiszId], $toDelete)); } return true; } } if (!isset($notExecute)) { handleJsonRpc('med_clinicstats_logoperations'); }