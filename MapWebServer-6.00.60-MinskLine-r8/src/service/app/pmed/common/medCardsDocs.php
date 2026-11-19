<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_common_medCardsDocs { private $db; public function readMedCardDocs($params = null){ $db = $this->db ?: $this->db = connectDb(); $templateType = isEmptyProperty($params, 'templateType'); $medPatientId = isEmptyProperty($params, 'medPatientId'); $studyComplete = isEmptyProperty($params, 'studyComplete'); $id = isEmptyProperty($params, 'id'); $userId = getSessionValue('mapUserCode'); $sqlWhere = ''; if ($id){ $sqlWhere = "AND pd.pmsPatientDocumentCode = $id\n"; }else{ $sqlWhere = "AND (pt.ready = 1 OR pt.userCode = $userId)\n"; } if ($templateType){ $sqlWhere .= "AND t.type = $templateType\n"; } if ($templateType == 102){ if ($studyComplete){ $sqlWhere .= "AND pd.resultDate IS NOT NULL"; }else{ $sqlWhere .= "AND pd.resultDate IS NULL"; } } $sql = <<<EOT
            SELECT pd.pmsPatientDocumentCode    "id",
                    pd.createTime               "createTime",
                    pd.nextVisitDate            "nextVisitDate",
                    t.type                      "templateType",
                    FN_cmFIO(doc.manCode, 1)    "doctorFio",
                    p.name                      "doctorProfessionName",
                    t.name                      "templateName",
                    d.cipher1                   "diagnosisCipher",
                    d.name                      "diagnosisName",
                    pd.result                   "result",
                    pt.ready                    "ready",
                    pd.medPatientCode           "medPatientId",
                    pd.pmsOrderCode             "pmsOrderId",
                    pd.htmTemplateCode          "htmTemplateId",
                    pd.medDoctorCode            "medDoctorId",
                    pd.medProfessionCode        "medProfessionId",
                    pd.htmPageCode              "htmPageId",
                    pd.enterpriseCodePlace      "placeEnterpriseId",
                    e.name                      "placeEnterpriseName",
                    pd.resultDate               "resultDate"
            FROM pmsPatientDocument pd
            LEFT JOIN medDoctor doc ON doc.medDoctorCode = pd.medDoctorCode
            LEFT JOIN medProfession p ON p.medProfessionCode = pd.medProfessionCode
            LEFT JOIN htmTemplate t ON t.htmTemplateCode = pd.htmTemplateCode
            LEFT JOIN medDiagnosis d ON d.medDiagnosisCode = pd.medDiagnosisCode
            LEFT JOIN htmPageT pt ON pt.htmPageCode = pd.htmPageCode
            LEFT JOIN enterprise e ON e.enterpriseCode = pd.enterpriseCodePlace
            WHERE pd.medPatientCode = ?
                    $sqlWhere
            ORDER BY pd.createTime DESC
EOT;
$ret = selectSql($db, $sql, [ $medPatientId ]); return $ret; } public function deleteMedCardDocs($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } deleteSql($db, 'pmsPatientDocument', 'pmsPatientDocumentCode', $records); return true; } public function createMedCardDocs($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            INSERT INTO pmsPatientDocument
                (medPatientCode, pmsOrderCode, htmTemplateCode,
                medDoctorCode, medProfessionCode)
            VALUES (?, ?, ?,
                    ?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; foreach ($records as $row){ $medPatientId = isUndefinedProperty($row, 'medPatientId'); $templateType = isUndefinedProperty($row, 'templateType'); $stmt->execute([ $medPatientId, isUndefinedProperty($row, 'pmsOrderId'), isUndefinedProperty($row, 'htmTemplateId'), isUndefinedProperty($row, 'medDoctorId'), isUndefinedProperty($row, 'medProfessionId') ]); $rec = new stdClass; $rec->id = (int)$db->lastInsertId('seq_pmsPatientDocument'); $rec->medPatientId = $medPatientId; $rec->templateType = $templateType; $res = $this->readMedCardDocs($rec); $ret[] = reset($res); } return $ret; } public function updateMedCardDocs($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            UPDATE pmsPatientDocument
            SET htmPageCode = CASE WHEN ? THEN ? ELSE htmPageCode END
            WHERE pmsPatientDocumentCode = ?
EOT;
$stmt = $db->prepare($sql); foreach ($records as $row){ $stmt->execute([ propertyExistsSQL($row, 'htmPageId'), isUndefinedProperty($row, 'htmPageId'), isUndefinedProperty($row, 'id') ]); $rec = new stdClass; $rec->id = isUndefinedProperty($row, 'id'); $rec->medPatientId = isUndefinedProperty($row, 'medPatientId'); $rec->templateType = isUndefinedProperty($row, 'templateType'); $res = $this->readMedCardDocs($rec); $ret[] = reset($res); } return $ret; } } handleJsonRpc('pmed_common_medCardsDocs');