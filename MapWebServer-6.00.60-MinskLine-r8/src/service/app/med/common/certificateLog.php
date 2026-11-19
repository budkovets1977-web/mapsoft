<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_certificateLog { private $db; public function readCertificateLog($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $id = isEmptyProperty($params, 'id'); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : null; $patientId = isEmptyProperty($params, 'patientId'); $certificateType = isEmptyProperty($params, 'certificateType'); $purposeId = isEmptyProperty($params, 'purposeId'); $targetId = isEmptyProperty($params, 'targetId'); $requestStatus = isEmptyProperty($params, 'requestStatus'); $conclusionResult = isEmptyProperty($params, 'conclusionResult'); $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $sort = isEmptyProperty($params, 'sort', 'code'); $dir = isEmptyProperty($params, 'dir', ''); $sqlFromWhere = <<<SQL
            FROM MedCertificate MC
            LEFT JOIN MedPatient MP ON MP.MedPatientCode = MC.MedPatientCode
            LEFT OUTER JOIN ManPassport PASS ON PASS.ManCode = MP.ManCode
            LEFT JOIN UniDict UD ON UD.UniDictCode = MC.UniDictCodeMark
            LEFT JOIN MedCertificateDispensary MCD ON MCD.MedCertificateDispensaryCode = MC.MedCertificateDispensaryCode
            WHERE MC.EnterpriseCode = $enterpriseCode
            SQL;
if (isset($startDate)) { $startDate = date('Y-m-d 00:00:00', strtotime($startDate)); $sqlFromWhere .= " AND MC.issuedate >= '$startDate'"; } if (isset($finishDate)) { $finishDate = date('Y-m-d 23:59:59', strtotime($finishDate)); $sqlFromWhere .= " AND MC.issuedate <= '$finishDate'"; } if (isset($patientId)) { $sqlFromWhere .= " and MC.MedPatientCode = $patientId"; } if (isset($certificateType)) { $sqlFromWhere .= " and MC.certificateType = $certificateType"; } if (isset($purposeId)) { $sqlFromWhere .= " and MC.unidictcodemark = $purposeId"; } if (isset($targetId)) { $sqlFromWhere .= " and (NULLIF(MC.json, '')::jsonb ->> 'targetId')::int = $targetId"; } if (isset($requestStatus)) { switch ($requestStatus) { case 'withConlusion': $sqlFromWhere .= " and MC.requestresult IS NOT NULL"; break; case 'noConclusion': $sqlFromWhere .= " and MC.requestresult = NULL"; break; case 'all': $sqlFromWhere .= ""; break; } } if (isset($conclusionResult)) { switch ($conclusionResult) { case 'noRegistered': $sqlFromWhere .= " and MC.requestresult = 1"; break; case 'registered': $sqlFromWhere .= " and MC.requestresult = 2"; break; } } if (isset($id)) { $sqlFromWhere .= " and MC.MedCertificateCode = $id"; } $sql = <<<SQL
            SELECT COUNT(*) "total"
            $sqlFromWhere
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<SQL
            SELECT
                MC.MedCertificateCode               "id",
                MC.Code                             "code",
                MC.IssueDate                        "issueDate",
                MC.MedPatientCode                   "patientId",
                MC.Conclusion                       "conclusion",
                MC.CertificateType                  "certificateType",
                CASE WHEN MC.MedPatientCode IS NULL THEN MC.Fio ELSE FN_cmFIO(MP.ManCode, 0) END
                                                    "patientFIO",
                CASE WHEN MC.MedPatientCode IS NULL THEN MC.Fio ELSE FN_cmFIO(MP.ManCode, 1) END
                                                    "patientFIO2",
                CASE WHEN MC.MedPatientCode IS NULL THEN MC.Birthday ELSE PASS.DateOfBirth END
                                                    "birth",
                UD.Value                            "purposeName",
                MC.UniDictCodeMark                  "purposeId",
                MC.Purpose                          "targetName",
                CASE 
                    WHEN MC.MedPatientCode IS NULL THEN MC.Address
                    WHEN MC.CertificateType = 9 THEN FN_cmCalcAddress(MP.ManCode, 1,0,1)
                    ELSE FN_cmCalcAddress(MP.ManCode, 5)
                END AS                              "patientAddress",
                CASE 
                    WHEN MC.MedPatientCode IS NULL THEN NULL
                    ELSE FN_cmCalcAddress(MP.ManCode,0,0,1) 
                END AS                              "patientRegistrationAddress", 
                CASE WHEN MC.MedPatientCode IS NULL THEN MC.Passport ELSE PASS.PassportNumber END
                                                    "passport",
                PASS.PersonalNumber                 "personalNumber",
                MP.StartDate                        "startDate",
                MC.RequestTime                      "requestTime",
                MC.RespondTime                      "responseTime",
                MC.Conclusiontime                   "conclusionTime",
                MC.RequestResult                    "requestResult",
                MCD.Name                            "dispensaryName",
                MCD.CodeOkpo                        "dispensaryOkpo",
                MCD.Active                          "dispensaryActive",
                MC.Attributes                       "attributes",
                MC.Note                             "note",
                -- Признак платности
                CASE WHEN (MC.Attributes & 1) = 1 THEN 1 ELSE 0 END AS "isPaid",
                -- Признак автоматичности
                CASE WHEN (MC.Attributes & 2) = 2 THEN true ELSE false END AS "isAuto",
                -- Диспансерный код (либо 4, либо 8)
                CASE 
                    WHEN (MC.Attributes & 4) = 4 THEN 4
                    WHEN (MC.Attributes & 8) = 8 THEN 8
                    ELSE null
                END AS "dispensaryCode",
                NULLIF(MC.json, '')::jsonb ->> 'dispensaryName' AS "dispensaryNameFromCod",
                NULLIF(MC.json, '')::jsonb ->> 'dispensaryId' AS "dispensaryCodeFromCod",
                NULLIF(MC.json, '')::jsonb ->> 'targetId' AS "targetId"
            $sqlFromWhere
            ORDER BY "$sort" $dir
            LIMIT $limit OFFSET $offset
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function createCertificateLog($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $userCode = getSessionValue('mapUserCode'); $sql = <<<"EOT"
    INSERT INTO MedCertificate (Code, IssueDate, MedPatientCode, CertificateType, UniDictCodeMark, enterpriseCode, userCode, conclusion, birthday, address, passport, fio)
    values (?, ?, ?, ?, ?, $enterpriseCode, $userCode, ? ,? ,? ,? ,?)
    EOT;
$sqlWithDispensary = <<<"EOT"
    INSERT INTO MedCertificate (Code, IssueDate, MedPatientCode, CertificateType, UniDictCodeMark, Purpose, Attributes, enterpriseCode, userCode, conclusion, birthday, address, passport, fio, note, json)
    values (?, ?, ?, ?, ?, ?, ?, $enterpriseCode, $userCode, ? ,? ,? ,? ,?, ?, ?)
    EOT;
$sqlCard = <<<"EOT"
    update MedPatientCard set
        FinishDate = DATEADD('day', -1, ?)
    where MedPatientCode = ?
    and coalesce(FinishDate, 30000101) >= ?
    EOT;
$sqlDispensary = <<<"EOT"
    update MedDispensary set
        FinishDate = DATEADD('day', -1, ?),
        MotiveDiscard = 2,
        MedDiagnosisCodeDiscard = MedDiagnosisCode
    where MedPatientCode = ?
    and coalesce(FinishDate, 30000101) = 30000101
    EOT;
$sqlPatient = <<<"EOT"
    update MedPatient set
        FinishDate = ?,
        OutReason = 0,
        OutReasonEx = 1
    where MedPatientCode = ?
    EOT;
$sqlDeregisterPatient= <<<"EOT"
    update MedPatientRegistration set
        FinishDate = ?,
        OutReasonEx = 1
    where MedPatientCode = ?
    and (COALESCE(FinishDate, 30000101)::DATE = 30000101 OR FinishDate > 'NOW'::DATE)
    EOT;
$sqlSearchNoResultRecord = <<<"EOT"
    SELECT
        MC.fio "fio",
        MC.MedPatientCode "medpatientcode"
    FROM MedCertificate MC
    WHERE MC.MedPatientCode = ?
        AND MC.EnterpriseCode = ?
        AND (MC.Attributes & ?) = ?
        AND MC.RequestTime IS NOT NULL
        ANd MC.RequestResult IS NULL
    LIMIT 1
    EOT;
$sqlSearchNoSendRecord = <<<"EOT"
    SELECT
        MC.fio "fio",
        MC.MedPatientCode "medpatientcode"
    FROM MedCertificate MC
    WHERE MC.MedPatientCode = ?
        AND MC.EnterpriseCode = ?
        AND (MC.Attributes & ?) = ?
        AND MC.RequestTime IS NULL
    LIMIT 1
    EOT;
$stmt = $db->prepare($sql); $stmtWithDispensary = $db->prepare($sqlWithDispensary); $stmtCard = $db->prepare($sqlCard); $stmtDispensary = $db->prepare($sqlDispensary); $stmtPatient = $db->prepare($sqlPatient); $stmtDeregisterPatient = $db ->prepare($sqlDeregisterPatient); $stmtSearchNoResultRecord = $db->prepare($sqlSearchNoResultRecord); $stmtSearchNoSendRecord = $db->prepare($sqlSearchNoSendRecord); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if ($row->certificateType == 9) { $dispensaryCodes = isEmptyProperty($row, 'dispensaryCode', []); foreach ($dispensaryCodes as $medcertificatedispensarycode) { $dispensaryBit = (int)$medcertificatedispensarycode; $stmtSearchNoSendRecord->execute([ isUndefinedProperty($row, 'patientId'), getSessionValue('mapEnterpriseCode'), $dispensaryBit, $dispensaryBit ]); $resSearchNoSendRecord = $stmtSearchNoSendRecord->fetch(); $stmtSearchNoResultRecord->execute([ isUndefinedProperty($row, 'patientId'), getSessionValue('mapEnterpriseCode'), $dispensaryBit, $dispensaryBit ]); $resSearchNoResultRecord = $stmtSearchNoResultRecord->fetch(); if ($resSearchNoSendRecord) { throw new Exception('Запись пациента для диспансера уже существует'); } else if ($resSearchNoResultRecord) { throw new Exception('Для пациента уже есть отправленный запрос'); } } foreach ($dispensaryCodes as $medcertificatedispensarycode) { $attributes = 0; if (isEmptyProperty($row, 'isPaid')) { $attributes |= 1; } if ($medcertificatedispensarycode == 4) { $attributes |= 4; } if ($medcertificatedispensarycode == 8) { $attributes |= 8; } $stmtWithDispensary->execute([ isEmptyProperty($row, 'code'), isEmptyProperty($row, 'issueDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'certificateType'), isEmptyProperty($row, 'purposeId'), isEmptyProperty($row, 'targetName'), $attributes, isEmptyProperty($row, 'conclusion'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'birth'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'patientAddress'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'passport'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'patientFIO'), isEmptyProperty($row, 'note'), json_encode(['targetId' => isEmptyProperty($row, 'targetId')]) ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCertificate'); } } else { $stmt->execute([ isEmptyProperty($row, 'code'), isEmptyProperty($row, 'issueDate'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'certificateType'), isEmptyProperty($row, 'purposeId'), isEmptyProperty($row, 'conclusion'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'birth'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'patientAddress'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'passport'), isEmptyProperty($row, 'patientId') != null ? null : isEmptyProperty($row, 'patientFIO') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCertificate'); } if (property_exists($row, 'closeCard') && $row->closeCard) { $stmtCard->execute([ isUndefinedProperty($row, 'issueDate'), isUndefinedProperty($row, 'patientId'), isUndefinedProperty($row, 'issueDate') ]); } if (property_exists($row, 'closeDisp') && $row->closeDisp) { $stmtDispensary->execute([ isUndefinedProperty($row, 'issueDate'), isUndefinedProperty($row, 'patientId') ]); } if (property_exists($row, 'closePatient') && $row->closePatient) { $stmtPatient->execute([ isUndefinedProperty($row, 'issueDate'), isUndefinedProperty($row, 'patientId') ]); $stmtDeregisterPatient->execute([ isUndefinedProperty($row, 'issueDate'), isUndefinedProperty($row, 'patientId') ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (count($ret) > 1) { $root = new stdClass(); $root->total = count($ret); $root->items = []; foreach ($ret as $res) { $root->items[] = $this->readCertificateLog($res)->items[0]; } return $root; } else { return $this->readCertificateLog($rec); } } public function updateCertificateLog($records) { $db = $this->db ?: $this->db = connectDb(); $mcdCodesForSplit = []; $mcdCodeToUpdate = null; function buildUpdatedJson($db, $certificateId, $targetId) { $stmtJson = $db->prepare('SELECT Json FROM MedCertificate WHERE MedCertificateCode = ?'); $stmtJson->execute([$certificateId]); $existingJson = $stmtJson->fetchColumn(); $jsonData = []; if ($existingJson) { $decoded = json_decode($existingJson, true); if (json_last_error() === JSON_ERROR_NONE) { $jsonData = $decoded; } } if ($targetId) { $jsonData['targetId'] = $targetId; } return json_encode($jsonData, JSON_UNESCAPED_UNICODE); } $sqlSelect = <<<"EOT"
    SELECT
        MC.RequestTime "requesttime",
        MC.CertificateType "certificateType",
        CASE 
            WHEN (MC.Attributes & 4) = 4 THEN 4
            WHEN (MC.Attributes & 8) = 8 THEN 8
            ELSE null
        END AS "dispensaryCode",
        MC.MedPatientCode "medpatientcode"
    FROM MedCertificate MC
    WHERE MC.MedCertificateCode = ?
    EOT;
$sqlSearchNoResultRecord = <<<"EOT"
    SELECT
        MC.fio "fio",
        MC.MedPatientCode "medpatientcode"
    FROM MedCertificate MC
    WHERE MC.MedPatientCode = ?
        AND MC.EnterpriseCode = ?
        AND (MC.Attributes & ?) = ?
        AND MC.RequestTime IS NOT NULL
        ANd MC.RequestResult IS NULL
    LIMIT 1
    EOT;
$sqlSearchNoSendRecord = <<<"EOT"
    SELECT
        MC.fio "fio",
        MC.MedPatientCode "medpatientcode"
    FROM MedCertificate MC
    WHERE MC.MedPatientCode = ?
        AND MC.EnterpriseCode = ?
        AND (MC.Attributes & ?) = ?
        AND MC.RequestTime IS NULL
    LIMIT 1
    EOT;
$stmtSelect = $db->prepare($sqlSelect); $stmtSearchNoSendRecord = $db->prepare($sqlSearchNoSendRecord); $stmtSearchNoResultRecord = $db->prepare($sqlSearchNoResultRecord); if (is_object($records)) { $records = [$records]; } foreach ($records as $row) { $stmtSelect->execute([isUndefinedProperty($row, 'id')]); $res = $stmtSelect->fetch(); if ($res['certificateType'] == 9) { $row->certificateType = 9; if ($res['requesttime']) { throw new Exception('Нельзя вносить изменения, данные были отправленны в цод'); } $dispensaryCodes = isEmptyProperty($row, 'dispensaryCode', []); if (isUndefinedProperty($row, 'patientId') === $res['medpatientcode'] || !isUndefinedProperty($row, 'patientId')) { if (count($dispensaryCodes) === 0) { $mcdCodeToUpdate = $res['dispensaryCode']; } else { foreach ($dispensaryCodes as $dispensaryCode) { if ($dispensaryCode !== $res['dispensaryCode']) { $dispensaryBit = (int)$dispensaryCode; $stmtSearchNoSendRecord->execute([ isUndefinedProperty($row, 'patientId'), getSessionValue('mapEnterpriseCode'), $dispensaryBit, $dispensaryBit ]); $resSearchNoSendRecord = $stmtSearchNoSendRecord->fetch(); $stmtSearchNoResultRecord->execute([ isUndefinedProperty($row, 'patientId'), getSessionValue('mapEnterpriseCode'), $dispensaryBit, $dispensaryBit ]); $resSearchNoResultRecord = $stmtSearchNoResultRecord->fetch(); if ($resSearchNoSendRecord) { throw new Exception('Запись пациента для диспансера уже существует'); } else if ($resSearchNoResultRecord) { throw new Exception('Для пациента уже есть отправленный запрос'); } else { $mcdCodesForSplit[] = $dispensaryCode; } } else { $mcdCodeToUpdate = $dispensaryCode; } } } } else { if (count($dispensaryCodes) === 0) { $mcdCodesForSplit = $res['dispensaryCode']; } else { $mcdCodesForSplit = $dispensaryCodes; } } } } $sql = <<<"EOT"
    UPDATE MedCertificate SET
        Code = case when ? = 1 then ? else Code end,
        IssueDate = case when ? = 1 then ? else IssueDate end,
        MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
        CertificateType = case when ? = 1 then ? else CertificateType end,
        UniDictCodeMark = case when ? = 1 then ? else UniDictCodeMark end,
        Conclusion = case when ? = 1 then ? else Conclusion end,
        BirthDay = case when ? = 1 then ? else BirthDay end,
        Address = case when ? = 1 then ? else Address end,
        Passport = case when ? = 1 then ? else Passport end,
        Fio = case when ? = 1 then ? else Fio end
    WHERE
        MedCertificateCode = ?
    EOT;
$sqlWithDispensary = <<<"EOT"
    UPDATE MedCertificate SET
        Code = case when ? = 1 then ? else Code end,
        Note = case when ? = 1 then ? else Note end,
        IssueDate = case when ? = 1 then ? else IssueDate end,
        MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
        CertificateType = case when ? = 1 then ? else CertificateType end,
        Purpose = case when ? = 1 then ? else Purpose end,
        UniDictCodeMark = case when ? = 1 then ? else UniDictCodeMark end,
        Attributes = case when ? = 1 then ? else Attributes end,
        Conclusion = case when ? = 1 then ? else Conclusion end,
        BirthDay = case when ? = 1 then ? else BirthDay end,
        Address = case when ? = 1 then ? else Address end,
        Passport = case when ? = 1 then ? else Passport end,
        Fio = case when ? = 1 then ? else Fio end,
        Json = case when ? = 1 then ? else Json end
    WHERE
        MedCertificateCode = ?
    EOT;
$stmt = $db->prepare($sql); $stmtWithDispensary = $db->prepare($sqlWithDispensary); $db->beginTransaction(); $ret = []; try { foreach ($records as $row) { if (isUndefinedProperty($row, 'certificateType') == 9) { foreach ($mcdCodesForSplit as $key => $mcdCode) { $attributes = 0; if (isEmptyProperty($row, 'isPaid')) { $attributes |= 1; } if ($mcdCode === 4) { $attributes |= 4; } if ($mcdCode === 8) { $attributes |= 8; } if ($key == 0 && !$mcdCodeToUpdate) { $stmtWithDispensary->execute([ propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'issueDate'), isUndefinedProperty($row, 'issueDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'certificateType'), isUndefinedProperty($row, 'certificateType'), propertyExistsSQL($row, 'targetName'), isEmptyProperty($row, 'targetName'), propertyExistsSQL($row, 'purposeId'), isEmptyProperty($row, 'purposeId'), true, $attributes, propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'birth'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'birth'), propertyExistsSQL($row, 'patientAddress'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientAddress'), propertyExistsSQL($row, 'passport'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'passport'), propertyExistsSQL($row, 'patientFIO'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientFIO'), true, buildUpdatedJson($db, $row->id, isEmptyProperty($row, 'targetId')), $row->id ]); $ret[] = $rec = new stdClass; $rec->id = $row->id; } else { $row->dispensaryCode = [$mcdCode]; $newRecord = $row; $newRecord = $this->createCertificateLog([$newRecord]); $ret[] = $rec = new stdClass; $rec->id = $newRecord->items[0]['id']; } } if ($mcdCodeToUpdate) { $attributes = 0; if (isEmptyProperty($row, 'isPaid')) { $attributes |= 1; } if ($mcdCodeToUpdate === 4) { $attributes |= 4; } if ($mcdCodeToUpdate === 8) { $attributes |= 8; } $stmtWithDispensary->execute([ propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'issueDate'), isUndefinedProperty($row, 'issueDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'certificateType'), isUndefinedProperty($row, 'certificateType'), propertyExistsSQL($row, 'targetName'), isEmptyProperty($row, 'targetName'), propertyExistsSQL($row, 'purposeId'), isEmptyProperty($row, 'purposeId'), true, $attributes, propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'birth'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'birth'), propertyExistsSQL($row, 'patientAddress'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientAddress'), propertyExistsSQL($row, 'passport'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'passport'), propertyExistsSQL($row, 'patientFIO'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientFIO'), true, buildUpdatedJson($db, $row->id, isEmptyProperty($row, 'targetId')), $row->id ]); $ret[] = $rec = new stdClass; $rec->id = $row->id; } } else { $stmt->execute([ propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'issueDate'), isUndefinedProperty($row, 'issueDate'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'certificateType'), isUndefinedProperty($row, 'certificateType'), propertyExistsSQL($row, 'purposeId'), isEmptyProperty($row, 'purposeId'), propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'birth'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'birth'), propertyExistsSQL($row, 'patientAddress'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientAddress'), propertyExistsSQL($row, 'passport'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'passport'), propertyExistsSQL($row, 'patientFIO'), isEmptyProperty($row, 'patientId') != null ? null : isUndefinedProperty($row, 'patientFIO'), $row->id ]); $ret[] = $rec = new stdClass; $rec->id = $row->id; } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } if (count($ret) > 1) { $root = new stdClass(); $root->total = count($ret); $root->items = []; foreach ($ret as $res) { $root->items[] = $this->readCertificateLog($res)->items[0]; } return $root; } else { return $this->readCertificateLog($rec); } } public function deleteCertificateLog($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedCertificate', 'MedCertificateCode', $records); } public function getNextCipher($certificateType) { $db = $this->db ?: $this->db = connectDb(); $type = isset($certificateType) ? $certificateType : 0; $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(code), 20)) as varchar(20))
    FROM
        MedCertificate
    WHERE
        coalesce(certificateType,0) = $type
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "code"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'code'); return $cipher; } public function updateCertificateByExternalServerResponse( $medcertificatecode, $requestresult, $respondtime, $conclusion, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId ) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
        UPDATE MedCertificate SET
            RequestResult = CASE WHEN ? = 1 THEN ? ELSE RequestResult END,
            RespondTime = CASE WHEN ? = 1 THEN ? ELSE RespondTime END,
            Conclusion = CASE WHEN ? = 1 THEN ? ELSE Conclusion END,
            ConclusionTime = CASE WHEN ? = 1 THEN ? ELSE ConclusionTime END,
            Attributes = CASE 
                WHEN ? = 1 THEN (Attributes | 2)  -- установить бит isAuto
                ELSE Attributes
            END,
            json = (
                CASE 
                    WHEN NULLIF(json, '') IS NULL THEN
                        jsonb_set(
                            jsonb_set('{}'::jsonb, '{dispensaryName}', to_jsonb(?::text), true),
                            '{dispensaryId}', to_jsonb(?::int), true
                        )::text
                    ELSE
                        jsonb_set(
                            jsonb_set(json::jsonb, '{dispensaryName}', to_jsonb(?::text), true),
                            '{dispensaryId}', to_jsonb(?::int), true
                        )::text
                END
            )
        WHERE MedCertificateCode = ?
        EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute([ true, $requestresult, true, $respondtime, true, $conclusion, true, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId, $dispensaryName, $dispensaryId, $medcertificatecode ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function sendWrittenDispenserCertificates($data = null) { $config = getSessionValue('config'); $settings = readExtraSettings($config, 'medExaminationsLinkCertificateDispensary'); $infoMsg = ''; $params = httpCreateParam($settings); $params->request_method = 'getRequestsByOrganisationId'; $params->param = array( 'guid' => $settings->guid ); $response = httpGET($params); $result = null; if (!isNullArrayValue($response['status'], 1)) { $result = 'пустой ответ'; } switch (isEmptyArrayValue($response['status'], 1)) { case "200": $result = safe_json_decode($response['body']); if (is_array($result)) { $requestIds= []; foreach ($result as $res) { $responseData = is_string($res->response_data) ? safe_json_decode($res->response_data) : $res->response_data; $requestData = is_string($res->request_data) ? safe_json_decode($res->request_data) : $res->request_data; $organizations = is_string($requestData->Organizations) ? safe_json_decode($requestData->Organizations) : $requestData->Organizations; $dispensaryData = $organizations->dest; $conclusion = null; $respondtime = null; $conclusionTime = null; $dispensaryName = null; $dispensaryId = null; $requestresult = $responseData->Result; $medcertificatecode = $requestData->MedCertificateCode; $isAutoResultFromCod = $requestData->Auto ? 1 : 0; if ($requestresult) { $requestIds[] = $res->id; $date = new DateTime(); $now = $date->format('Y-m-d H:i:s'); $conclusionTime = $now; $respondtime = $now; $conclusion = $responseData->Conclusion; $dispensaryName = $dispensaryData->EnterpriseName; $dispensaryId = $dispensaryData->EnterpriseCode; } $this->updateCertificateByExternalServerResponse( $medcertificatecode, $requestresult, $respondtime, $conclusion, $conclusionTime, $isAutoResultFromCod, $dispensaryName, $dispensaryId ); } $params->request_method = 'setOkRequestByOrganisation'; $params->param = array( 'guid' => $settings->guid ); $params->request_content = json_encode($requestIds); $response = httpPOST($params); } break; default: $result = $result ? : $response['body']; throw new Exception('Ошибка при получении обработанных результатов из цод'.$result); } return $infoMsg; } } if (!isset($notExecute)) { $service = new Junior\Server(new med_common_certificateLog()); $service->process(); }