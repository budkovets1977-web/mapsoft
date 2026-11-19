<?php
require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_log { private $db; const ACCESS_LOG_ADDING = 'med-qualitycontrol-log-adding'; const ERROR_TEXT_NO_ACCESS_LOG_ADDING = 'У вас нет прав на добавление оценок и экспертиз качества!'; const ACCESS_OTHER_USERS_EDITING = 'med-qualitycontrol-log-other-users-editing'; const ACCESS_LOG_DELETING = 'med-qualitycontrol-log-deleting'; const ACCESS_LOG_OTHER_USERS_DELETING = 'med-qualitycontrol-log-other-users-deleting'; public function readLog($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $limit = isEmptyProperty($params, 'limit', 0); $page = isEmptyProperty($params, 'page', 0) + 1; $id = isEmptyProperty($params, 'id'); $sort = isEmptyProperty($params,'sort'); $dir = isEmptyProperty($params,'dir', 'asc'); $limitSql = ''; $sqlWhere = ''; $sqlOrder = empty($sort)? "" : " ORDER BY MS.\"$sort\" $dir "; if ($id) { $sqlWhere .= " AND MS.id = $id "; } if ($limit && $page) { $limitSql = "LIMIT " . ($page * $limit); } $filterSql = $this->getFilterSql($params, $sqlParams); $sql = <<<EOT
            WITH Experts as (
                SELECT
                    MQLE.MedQualityLogCode,
                    STRING_AGG(MQLE.medDoctorCode, ',') "expertsIds",
                    STRING_AGG(FN_cmFIO(MD.manCode), ', ') "experts"
                FROM MedQualityLogExpert MQLE
                LEFT JOIN MedDoctor MD ON MD.medDoctorCode = MQLE.medDoctorCode
                WHERE MQLE.chairManFlag IS NULL
                GROUP BY MQLE.MedQualityLogCode
            ),
            mainSelect as (
                SELECT
                    MQL.MedQualityLogCode               "id",
                    MQL.EnterpriseCode                  "organizationId",
                    MQL.Type                            "type",
                    MQL.SourceType                      "sourceType",
                    MQL.StartDate                       "startDate",
                    MQL.FinishDate                      "finishDate",
                    MQL.UniDictCodeBasis                "basisId",
                    Basis.Value                         "basisName",
                    MQL.MedDoctorCode                   "doctorId",
                    FN_cmFIO(MQL.MedDoctorCode)         "doctorFio",
                    MQL.Mark                            "mark",
                    MQL.QualityResult                   "qualityResult",
                    MQL.Conclusion                      "conclusion",
                    MQL.ConclusionDate                  "conclusionDate",
                    MQL.Proposition                     "proposition",
                    MQL.Note                            "note",
                    MQL.MedQualityCriteriaKindCode      "qualityCriteriaKindId",
                    MQCK.MedQualityCriteriaDateCode     "qualityCriteriaDateId",
                    MQL.NeedRepeat                      "needRepeat",
                    MQL.CreateTime                      "createTime",
                    MQL.UpdateTime                      "updateTime",
                    MQL.EnterpriseStructureCode         "enterpriseStructureId",
                    E.Experts                           "expertsDisplay",
                    E."expertsIds"                      "experts",
                    MQLE_1.MedDoctorCode                "chairManDoctorId",
                    FN_cmFIO(MD_1.ManCode)              "chairManDoctorFio"
                FROM MedQualityLog MQL
                LEFT JOIN MedQualityCriteriaKind MQCK ON MQCK.MedQualityCriteriaKindCode = MQL.MedQualityCriteriaKindCode
                LEFT JOIN Experts E ON E.medQualityLogCode = MQL.medQualityLogCode
                LEFT JOIN MedQualityLogExpert MQLE_1 ON MQLE_1.medQualityLogCode = MQL.medQualityLogCode AND MQLE_1.chairManFlag = 1
                LEFT JOIN MedDoctor MD_1 ON MD_1.medDoctorCode = MQLE_1.medDoctorCode
                LEFT JOIN UniDict Basis ON Basis.UniDictCode = MQL.uniDictCodeBasis
                $filterSql
            ),
            countSelect as (
                SELECT
                    COUNT(id) "amount"
                FROM mainSelect
            )
            SELECT
                MS.*,
                CS.amount
            FROM mainSelect MS
            LEFT JOIN countSelect CS on 1 = 1
            WHERE 1 = 1
            $sqlWhere
            $sqlOrder            
            $limitSql
EOT;
$records = selectSql($db, $sql, $sqlParams); foreach ($records as &$record) { $record['experts'] = $record['experts'] ? safe_explode(',', $record['experts']) : null; } $result = new stdClass(); $result->items = $records; $result->total = empty($records) ? 0 : $records[0]['amount']; return $result; } protected function getFilterSql($params, &$sqlParams) { $resultSql = []; $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); if ($startDate) { $periodSql = "(:startDate::DATE <= MQL.startDate::DATE AND :finishDate::DATE >= MQL.startDate::DATE)"; $sqlParams[':startDate'] = dbDate($startDate); $sqlParams[':finishDate'] = dbDate($finishDate); $resultSql[] = $periodSql; } $patientId = isEmptyProperty($params, 'patientId'); if ($patientId) { $patientSql = "EXISTS(
                SELECT
                    MQLI.medQualityLogInCode
                FROM medQualityLogIn MQLI
                WHERE MQLI.medQualityLogCode = MQL.MedQualityLogCode AND MQLI.medPatientCode = :patientId
            )"; $sqlParams[':patientId'] = $patientId; $resultSql[] = $patientSql; } $doctorId = isEmptyProperty($params, 'doctorId'); if ($doctorId) { $doctorSql = "(MQL.medDoctorCode = :doctorId)"; $sqlParams[':doctorId'] = $doctorId; $resultSql[] = $doctorSql; } $expertId = isEmptyProperty($params, 'expertId'); if ($expertId) { $expertSql = "EXISTS(
                SELECT
                    MQLE.medQualityLogExpertCode
                FROM medQualityLogExpert MQLE
                WHERE MQLE.medQualityLogCode = MQL.MedQualityLogCode
                    AND MQLE.medDoctorCode = :expertId
            )"; $sqlParams[':expertId'] = $expertId; $resultSql[] = $expertSql; } $logType = isEmptyProperty($params, 'logType'); if ($logType) { $logTypeSql = "MQL.type = :logType"; $sqlParams[':logType'] = $logType; $resultSql[] = $logTypeSql; } $resultId = isEmptyProperty($params, 'resultId'); if ($resultId) { $resultIdSqlConfig = (object) [ 1 => "(MQL.mark >= 80 AND MQL.mark <= 100)", 2 => "(MQL.mark >= 55 AND MQL.mark <= 79.9)", 3 => "(MQL.mark <= 54.9)" ]; $resultIdSql = isEmptyProperty($resultIdSqlConfig, $resultId, ''); $resultSql[] = $resultIdSql; } $criteriaKindId = isEmptyProperty($params, 'criteriaKindId'); if ($criteriaKindId) { $criteriaKindSql = "MQL.medQualityCriteriaKindCode = :criteriaKindId"; $sqlParams[':criteriaKindId'] = $criteriaKindId; $resultSql[] = $criteriaKindSql; } $basisId = isEmptyProperty($params, 'basisId'); if ($basisId) { $basisSql = "MQL.uniDictCodeBasis = :basisId"; $sqlParams[':basisId'] = $basisId; $resultSql[] = $basisSql; } $needRepeat = isEmptyProperty($params, 'needRepeat'); if ($needRepeat) { $needRepeatSql = "MQL.needRepeat = 1"; $resultSql[] = $needRepeatSql; } $id = isEmptyProperty($params, 'id'); if ($id) { $idSql = "MQL.MedQualityLogCode = :id"; $sqlParams[':id'] = $id; $resultSql[] = $idSql; } if (empty($resultSql)) { return ''; } $result = 'WHERE ' . implode(' AND ', $resultSql); return $result; } public function createLog($records) { $db = $this->db ?: $this->db = connectDb(); $this->checkAccess(self::ACCESS_LOG_ADDING); if (!is_array($records)) { $records = [$records]; } $result = []; $sqlInsert = <<<EOT
            INSERT INTO MedQualityLog
                (conclusion, conclusiondate, enterprisecode,
                finishdate, mark, meddoctorcode,
                needrepeat, note, proposition,
                qualityresult, sourcetype, startdate,
                type, unidictcodebasis, enterprisestructurecode,
                medqualitycriteriakindcode)
            VALUES (:conclusion, :conclusionDate, :enterpriseCode,
                :finishDate, :mark, :meddoctorcode,
                :needrepeat, :note, :proposition,
                :qualityresult, :sourcetype, :startdate,
                :type, :unidictcodebasis, :enterprisestructurecode,
                :medqualitycriteriakindcode)
            RETURNING medQualitylogCode
EOT;
$db->beginTransaction(); try { foreach ($records as &$record) { $result[] = $rec = new stdClass(); $rec->id = $record->id = selectSqlValue($db, $sqlInsert, [ ':conclusion' => isUndefinedProperty($record, 'conclusion'), ':conclusionDate' => isUndefinedProperty($record, 'conclusionDate'), ':enterpriseCode' => getSessionValue('mapEnterpriseCode'), ':finishDate' => isUndefinedProperty($record, 'finishDate'), ':mark' => isUndefinedProperty($record, 'mark'), ':meddoctorcode' => isUndefinedProperty($record, 'doctorId'), ':needrepeat' => isUndefinedProperty($record, 'needRepeat'), ':note' => isUndefinedProperty($record, 'note'), ':proposition' => isUndefinedProperty($record, 'proposition'), ':qualityresult' => isUndefinedProperty($record, 'qualityResult'), ':sourcetype' => isUndefinedProperty($record, 'sourceType'), ':startdate' => isUndefinedProperty($record, 'startDate'), ':type' => isUndefinedProperty($record, 'type'), ':unidictcodebasis' => isUndefinedProperty($record, 'basisId'), ':enterprisestructurecode' => isUndefinedProperty($record, 'enterpriseStructureId'), ':medqualitycriteriakindcode' => isUndefinedProperty($record, 'qualityCriteriaKindId') ]); $this->updateExperts($record); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function updateExperts($record) { $db = $this->db ?: $this->db = connectDb(); $InsertExpertsSql = <<<"EOT"
INSERT INTO medqualitylogexpert (medqualitylogcode, meddoctorcode, chairmanflag)
VALUES (:medqualitylogcode, :expertcode, :chairmanflag)
EOT;
$deleteExpertsSql = <<<"EOT"
DELETE FROM medqualitylogexpert
WHERE medqualitylogcode = :medqualitylogcode
EOT;
$stmtInsertExpertsSql = $db->prepare($InsertExpertsSql); if (is_array(isUndefinedProperty($record, 'experts'))) { $deleteExpertsSql1 = $deleteExpertsSql . ' AND chairmanflag is null'; $experts = isUndefinedProperty($record, 'experts'); $stmtDeleteExpertsSql = $db->prepare($deleteExpertsSql1); $stmtDeleteExpertsSql->execute([ ':medqualitylogcode' => isUndefinedProperty($record, 'id') ]); foreach ($experts as $expert) { $stmtInsertExpertsSql->execute([ ':medqualitylogcode' => isUndefinedProperty($record, 'id'), ':expertcode' => $expert, ':chairmanflag' => null ]); } } if (isUndefinedProperty($record, 'chairManDoctorId')) { $deleteExpertsSql2 = $deleteExpertsSql . ' AND chairmanflag is not null'; $stmtDeleteExpertsSql = $db->prepare($deleteExpertsSql2); $stmtDeleteExpertsSql->execute([ ':medqualitylogcode' => isUndefinedProperty($record, 'id') ]); $stmtInsertExpertsSql->execute([ ':medqualitylogcode' => isUndefinedProperty($record, 'id'), ':expertcode' => isUndefinedProperty($record, 'chairManDoctorId'), ':chairmanflag' => 1 ]); } } public function updateLog($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $accessOtherUsersEditing = testUserAccess(self::ACCESS_OTHER_USERS_EDITING); $userDoctorId = (int)getSessionValue('mapMedDoctor')['MedDoctorCode']; $sql = <<<EOT
            UPDATE MedQualityLog SET
                conclusion                  = case when ? = 1 then ? else conclusion end,
                conclusiondate              = case when ? = 1 then ? else conclusiondate end,
                enterprisecode              = ?,
                finishdate                  = case when ? = 1 then ? else finishdate end,
                mark                        = case when ? = 1 then ? else mark end,
                meddoctorcode               = case when ? = 1 then ? else meddoctorcode end,
                needrepeat                  = case when ? = 1 then ? else needrepeat end,
                note                        = case when ? = 1 then ? else note end,
                proposition                 = case when ? = 1 then ? else proposition end,
                qualityresult               = case when ? = 1 then ? else qualityresult end,
                sourcetype                  = case when ? = 1 then ? else sourcetype end,
                startdate                   = case when ? = 1 then ? else startdate end,
                type                        = case when ? = 1 then ? else type end,
                unidictcodebasis            = case when ? = 1 then ? else unidictcodebasis end,
                enterprisestructurecode     = case when ? = 1 then ? else enterprisestructurecode end,
                medqualitycriteriakindcode  = case when ? = 1 then ? else medqualitycriteriakindcode end
            WHERE medqualitylogcode = ?
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $accessGranted = false; $logId = isEmptyProperty($row, 'id'); $params = new stdClass(); $params->id = $logId; $logData = $this->readLog($params); if ($logData->total > 0) { $logData = $logData->items[0]; $chairManDoctorId = $logData['chairManDoctorId']; $experts = $logData['experts']; $accessGranted = ($accessOtherUsersEditing || (in_array($userDoctorId, $experts) || $userDoctorId === $chairManDoctorId)); } if (!$accessGranted) { continue; } $stmt->execute([ propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'conclusionDate'), isUndefinedProperty($row, 'conclusionDate'), getSessionValue('mapEnterpriseCode'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'mark'), isUndefinedProperty($row, 'mark'), propertyExistsSQL($row, 'doctorId'), isUndefinedProperty($row, 'doctorId'), propertyExistsSQL($row, 'needRepeat'), isUndefinedProperty($row, 'needRepeat'), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), propertyExistsSQL($row, 'proposition'), isUndefinedProperty($row, 'proposition'), propertyExistsSQL($row, 'qualityResult'), isUndefinedProperty($row, 'qualityResult'), propertyExistsSQL($row, 'sourceType'), isUndefinedProperty($row, 'sourceType'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'type'), isUndefinedProperty($row, 'type'), propertyExistsSQL($row, 'basisId'), isUndefinedProperty($row, 'basisId'), propertyExistsSQL($row, 'enterpriseStructureId'), isUndefinedProperty($row, 'enterpriseStructureId'), propertyExistsSQL($row, 'qualityCriteriaKindId'), isUndefinedProperty($row, 'qualityCriteriaKindId'), $row->id ]); $this->updateExperts($row); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteLog($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $accessLogDeleting = testUserAccess(self::ACCESS_LOG_DELETING); $accessLogOtherUsersDeleting = testUserAccess(self::ACCESS_LOG_OTHER_USERS_DELETING); $userDoctorId = (int)getSessionValue('mapMedDoctor')['MedDoctorCode']; $sql = "DELETE FROM MedQualityLog WHERE MedQualityLogCode = ?"; $stmt = $db->prepare($sql); $db->beginTransaction(); try { foreach ($records as $row) { $logId = isEmptyProperty($row, 'id'); $accessGranted = false; $params = new stdClass(); $params->id = $logId; $logData = $this->readLog($params); $logData = $logData->items[0]; $chairManDoctorId = $logData['chairManDoctorId']; $experts = $logData['experts']; if ($accessLogOtherUsersDeleting || ($accessLogDeleting && (in_array($userDoctorId, $experts) || $userDoctorId === $chairManDoctorId))) { $accessGranted = true; } if (!$accessGranted) { continue; } $stmt->execute([ $logId ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function checkAccess($type) { switch ($type) { case self::ACCESS_LOG_ADDING: $access = self::ACCESS_LOG_ADDING; $errorText = self::ERROR_TEXT_NO_ACCESS_LOG_ADDING; break; default: return; } if (!testUserAccess($access)) { throw new Exception($errorText, ERROR_NOT_ALLOWED); } } } handleJsonRpc('med_qualitycontrol_log');