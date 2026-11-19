<?php
 require_once(__DIR__ . '/../../init.php'); class med_qualitycontrol_logIn { private $db; public function readLogIn($params = null) { $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $limit = isEmptyProperty($params, 'limit', 0); $page = isEmptyProperty($params, 'page', 0) + 1; $limitSql = ''; if ($limit && $page){ $limitSql = "LIMIT " . ($page * $limit); } $filterSql = $this->getFilterSql($params, $sqlParams); $sql = <<<"EOT"
WITH Experts as (
SELECT 
    MQLE.MedQualityLogCode,
    STRING_AGG(FN_cmFIO(MD.manCode), ', ') "experts"
FROM MedQualityLogExpert MQLE
LEFT JOIN MedDoctor MD ON MD.medDoctorCode = MQLE.medDoctorCode 
WHERE MQLE.chairManFlag IS NULL
GROUP BY MQLE.MedQualityLogCode
),
types as (
SELECT 
    1 "type",
    'Оценка качества' "typeDisplay"
UNION
SELECT 
    2 "type",
    'Экспертиза качества' "typeDisplay"
),
mainSelect as (
    SELECT
        MQLI.MedQualityLogInCode "id",
        MQL.MedQualityLogCode "logId",
        MQL.EnterpriseCode "organizationId",
        MQL.type "type",
        T."typeDisplay" "typeDisplay",
        MQL.sourceType "sourceType",
        MQL.startDate "startDate",
        MQL.finishDate "finishDate",
        MQL.uniDictCodeBasis "basisId",
        Basis.value "basisName",
        MQL.medDoctorCode "doctorId",
        FN_cmFIO(MD_2.manCode) "doctorFio",
        MQL.mark "mark",
        MQL.qualityResult "result",
        MQL.conclusion "conclusion",
        MQL.proposition "proposition",
        MQL.note "note",
        MQL.createTime "createTime",
        MQL.updateTime "updateTime",
        E.experts "expertsDisplay",
        MQLE_1.medDoctorCode "chairManDoctorId",
        FN_cmFIO(MD_1.manCode) "chairManDoctorFio",
        MQLI.conclusionDate "conclusionDate"
    FROM MedQualityLogIn MQLI
    LEFT JOIN MedQualityLog MQL ON MQL.medQualityLogCode = MQLI.medQualityLogCode
    LEFT JOIN Experts E ON E.medQualityLogCode = MQL.medQualityLogCode
    LEFT JOIN MedQualityLogExpert MQLE_1 ON MQLE_1.medQualityLogCode = MQL.medQualityLogCode AND MQLE_1.chairManFlag = 1
    LEFT JOIN MedDoctor MD_1 ON MD_1.medDoctorCode = MQLE_1.medDoctorCode
    LEFT JOIN MedDoctor MD_2 ON MD_2.medDoctorCode = MQL.medDoctorCode
    LEFT JOIN UniDict Basis ON Basis.UniDictCode = MQL.uniDictCodeBasis
    LEFT JOIN types T ON T.type = MQL.type
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
$limitSql
EOT;
$records = selectSql($db, $sql, $sqlParams); $result = new stdClass(); $result->items = $records; $result->total = empty($records) ? 0 : $records[0]['amount']; return $result; } protected function getFilterSql ($params, &$sqlParams) { $resultSql = []; $patientId = isEmptyProperty($params, 'patientId'); if ($patientId) { $patientSql = "(MQLI.medPatientCode = :patientId)"; $sqlParams[':patientId'] = $patientId; $resultSql[] = $patientSql; } $search = isEmptyProperty($params, 'search'); if ($search) { $searchSql = getSearchSqlCondition($search, [ 'T."typeDisplay"', 'MQL.startDate::text', 'Basis.value', 'FN_cmFIO(MD_2.manCode)', 'FN_cmFIO(MD_1.manCode)', 'E.experts' ]); $resultSql[] = $searchSql; } $logType = isEmptyProperty($params, 'logType'); if ($logType) { $logTypeSql = "MQL.type = :logType"; $sqlParams[':logType'] = $logType; $resultSql[] = $logTypeSql; } if (empty($resultSql)) { return ''; } $result = 'WHERE ' . implode(' AND ', $resultSql); return $result; } public function deleteLogIn($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedQualityLogin', 'medqualitylogincode', $records); } } handleJsonRpc('med_qualitycontrol_logIn'); 