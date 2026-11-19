<?php
 require_once(__DIR__.'/../../init.php'); class med_fluorography_log { private $db; public function readLog($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $id = isset($params->id) && isEmptyProperty($params, 'id') ? $params->id : null; $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : null; $preventKindId = isset($params->preventKindId) && isEmptyProperty($params, 'preventKindId') ? $params->preventKindId : null; $enterprisePlaceId = isset($params->enterprisePlaceId) && isEmptyProperty($params, 'enterprisePlaceId') ? $params->enterprisePlaceId : null; $patientSector = isset($params->patientSector) && isEmptyProperty($params, 'patientSector') ? $params->patientSector : null; $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $limit = isset($params->limit) && (int) $params->limit ? (int) $params->limit : 25; $offset = isset($params->start) && (int) $params->start >= 0 ? (int) $params->start : 0; $currDate = Date("Ymd"); $isPathology = isEmptyProperty($params, 'pathologyId'); $sqlFromWhere = "
FROM MedFluor AS MF
    JOIN MedPatient AS MP ON MF.MedPatientCode = MP.MedPatientCode
    LEFT OUTER JOIN ManPassport PASS ON PASS.ManCode = MP.ManCode
WHERE
MF.EnterpriseCode = $enterpriseCode
    /* хоть один участок доступен пользователю */
    and (mp.MedPatientGroupCode in (select gc from pg_)
        or (
            exists (select 1 from MedPatientAddGroup pga
                where pga.MedPatientCode = mp.MedPatientCode
                    and pga.MedPatientGroupCode in (select gc from pg_))
        )
    )"; if (isset($startDate)) { $sqlFromWhere .= " and MF.OnDate >= $startDate"; } if (isset($finishDate)) { $sqlFromWhere .= " and MF.OnDate <= $finishDate"; } if (isset($preventKindId)) { $sqlFromWhere .= " and MF.MedPreventTypeCode = $preventKindId"; } if (isset($enterprisePlaceId)) { $sqlFromWhere .= " and MF.EnterpriseCodePlace = $enterprisePlaceId"; } if (isset($patientSector)) { $sqlFromWhere .= " and MP.MedPatientGroupCode = $patientSector or $patientSector = 0"; } if (isset($patientId)) { $sqlFromWhere .= " and MP.MedPatientCode = $patientId"; } if (isset($id)) { $sqlFromWhere .= " and MF.MedFluorCode = $id"; } if ($isPathology === 1) { $sqlFromWhere .= " AND MF.Important = 1"; } else if ($isPathology === 2) { $sqlFromWhere .= " AND (MF.Important IS NULL OR MF.Important = 0)"; } $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
SELECT
    COUNT(*) "total"
$sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $offset = $total < $offset ? 0 : $offset; $sql = <<<"EOT"
with pg_ as (
    select MedPatientGroupCode gc
    from MedPatientGroup
    where EnterpriseCode = $enterpriseCode
        and fn_WebUserAccessMedSector(MedPatientGroupCode) = 1
)
select
    MF.MedFluorCode AS "id",
    MF.Number AS "number",
    MF.OnDate AS "onDate",
    MP.MedPatientCode AS "patientId",
    MP.NumberHistorySickness AS "numberHistorySickness",
    FN_cmFIO( MP.ManCode ) AS "patientFIO",
    PASS.DateOfBirth "birth",
    FN_cmCalcAddress( MP.ManCode, 2) AS "patientRegistrationAddress",
    FN_cmCalcAddress( MP.ManCode, 1) AS "patientAddress",
    MP.MedPatientGroupCode "patientSectorId",
    (select Name from MedPatientGroup where MedPatientGroupCode = MP.MedPatientGroupCode) AS "patientSector",
    CAST(asa_trim(MF.Conclusion) as varchar(255)) AS "conclusion",
    MF.Radiation AS "radiation",
    MF.MedClinicalGroupCode AS "clinicalGroupId" ,
    (select name from MedClinicalGroup where MedClinicalGroupCode = MF.MedClinicalGroupCode LIMIT 1 ) AS "clinicalGroupName",
    CAST(asa_trim(MF.Recommendations) as varchar(255)) AS "recommendations",
    MF.NumberSnapshot AS "numberSnapshot",
    MF.EnterpriseCodePlace AS "enterprisePlaceId",
    (select name from Enterprise where enterprisecode = MF.EnterpriseCodePlace) AS "enterprisePlaceName",
    MF.MedPreventTypeCode AS "preventKindId",
                (CASE (DATEDIFFYEAR(PASS.dateOfBirth, $currDate)) > 0
       WHEN true THEN CAST(FN_cmGetMonthYearString((DATEDIFFYEAR(PASS.dateOfBirth, $currDate))) as VARCHAR(250))
       WHEN false THEN CASE (DATEDIFFMONTH(PASS.dateOfBirth, $currDate)) > 0
                          WHEN true then cast(FN_cmGetMonthYearString(((DATEDIFFMONTH(PASS.dateOfBirth, $currDate)) / 100.00), 2) as VARCHAR(250))
                          WHEN false THEN CASE (DATEDIFF('day', PASS.dateOfBirth, $currDate)) > 0
                                               WHEN true THEN CAST(FN_cmGetDayString((DATEDIFF('day', PASS.dateOfBirth, $currDate))) as VARCHAR(250))
                                          END
                     END
       END) "patientAge",
    MF.Important "isPathology",
    MF.Note AS "note"
$sqlFromWhere
LIMIT $limit OFFSET $offset
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } public function updateLog($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedFluor SET
    OnDate = case when ? = 1 then ? else OnDate end,
    Number = case when ? = 1 then ? else Number end,
    MedPatientCode = case when ? = 1 then ? else MedPatientCode end,
    Radiation = case when ? = 1 then ? else Radiation end,
    Conclusion = case when ? = 1 then ? else Conclusion end,
    EnterpriseCodePlace = case when ? = 1 then ? else EnterpriseCodePlace end,
    MedPreventTypeCode = case when ? = 1 then ? else MedPreventTypeCode end,
    MedClinicalGroupCode = case when ? = 1 then ? else MedClinicalGroupCode end,
    Recommendations = case when ? = 1 then ? else Recommendations end,
    NumberSnapshot = case when ? = 1 then ? else NumberSnapshot end,
    Important = CASE WHEN ? = 1 THEN ? ELSE Important END,
    Note = CASE WHEN ? = 1 THEN ? ELSE Note END
WHERE MedFluorCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); $rec = new stdClass; try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'onDate'), isUndefinedProperty($row, 'onDate'), propertyExistsSQL($row, 'number'), isUndefinedProperty($row, 'number'), propertyExistsSQL($row, 'patientId'), isUndefinedProperty($row, 'patientId'), propertyExistsSQL($row, 'radiation'), isUndefinedProperty($row, 'radiation'), propertyExistsSQL($row, 'conclusion'), isUndefinedProperty($row, 'conclusion'), propertyExistsSQL($row, 'enterprisePlaceId'), isUndefinedProperty($row, 'enterprisePlaceId'), propertyExistsSQL($row, 'preventKindId'), isUndefinedProperty($row, 'preventKindId'), propertyExistsSQL($row, 'clinicalGroupId'), isUndefinedProperty($row, 'clinicalGroupId'), propertyExistsSQL($row, 'recommendations'), isUndefinedProperty($row, 'recommendations'), propertyExistsSQL($row, 'numberSnapshot'), isUndefinedProperty($row, 'numberSnapshot'), propertyExistsSQL($row, 'isPathology'), isEmptyProperty($row, 'isPathology', null), propertyExistsSQL($row, 'note'), isUndefinedProperty($row, 'note'), $row->id ]); $rec->id = $row->id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readLog($rec); } public function deleteLog($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedFluor', 'medfluorcode', $records); } public function createLog($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<SQL
            INSERT INTO MedFluor (
                OnDate, Number, MedPatientCode,
                Radiation, Conclusion, EnterpriseCodePlace,
                MedPreventTypeCode, MedClinicalGroupCode, Recommendations,
                NumberSnapshot, EnterpriseCode, Important, Note
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, $enterpriseCode, ?, ?
            )
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'onDate'), isEmptyProperty($row, 'number'), isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'radiation'), isEmptyProperty($row, 'conclusion'), isEmptyProperty($row, 'enterprisePlaceId'), isEmptyProperty($row, 'preventKindId'), isEmptyProperty($row, 'clinicalGroupId'), isEmptyProperty($row, 'recommendations'), isEmptyProperty($row, 'numberSnapshot'), isEmptyProperty($row, 'isPathology'), isEmptyProperty($row, 'note'), ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedFluor'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $this->readLog($rec); } public function getFormFields($params = null) { $db = $this->db ?: $this->db = connectDb(); $preventKindId = isset($params->preventKindId) && isEmptyProperty($params, 'preventKindId') ? $params->preventKindId : null; $sql = <<<"EOT"
Select
    s.MedPreventTypeCode,
    s.NBit,
    s.Name,
    s.Value
From
    (SELECT
        M.MedPreventTypeCode,
        POWER(2,N.SysNumberCode-1) AS NBit,
        CASE M.InputForm
        WHEN 1 THEN
            CASE (POWER(2,N.SysNumberCode-1) ) WHEN 1 THEN  E'Доза' END
        END AS Name,
        CASE (COALESCE(M.FormFields,0) & (POWER(2,N.SysNumberCode-1) ) ) = (POWER(2,N.SysNumberCode-1) ) WHEN true THEN 1 WHEN false THEN 0 END AS Value
FROM
    MedPreventType AS M ,
    SysNumber AS N WHERE
    M.MedPreventTypeCode = $preventKindId
    AND N.SysNumberCode BETWEEN 1 AND
  CASE M.InputForm WHEN 1 THEN 1 WHEN 2 THEN 0 END
Union all
SELECT
 M1.MedPreventTypeCode,
 2 AS NBit,
 CASE M1.InputForm
 WHEN 1 THEN  E'Клиническая группа' END AS Name,
 CASE (COALESCE(M1.FormFields,0) & (2 ) ) = (2 ) WHEN true THEN 1 WHEN false THEN 0 END AS Value
FROM
 MedPreventType AS M1 WHERE
 M1.MedPreventTypeCode = $preventKindId
Union all
SELECT
 m1_344.MedPreventTypeCode,
 4 AS NBit,
 CASE m1_344.InputForm
 WHEN 1 THEN  E'Рекомендации по дообследованию' END AS Name,
 CASE (COALESCE(m1_344.FormFields,0) & (4 ) ) = (4 ) WHEN true THEN 1 WHEN false THEN 0 END AS Value
FROM
 MedPreventType AS m1_344 WHERE
 m1_344.MedPreventTypeCode = $preventKindId
Union all
SELECT
 m1_434.MedPreventTypeCode,
 8 AS NBit,
 CASE m1_434.InputForm
 WHEN 1 THEN  E'Количество снимков' END AS Name,
 CASE (COALESCE(m1_434.FormFields,0) & (8 ) ) = (8 ) WHEN true THEN 1 WHEN false THEN 0 END AS Value
FROM
 MedPreventType AS m1_434 WHERE
 m1_434.MedPreventTypeCode = $preventKindId
Union all
SELECT
 m1_343.MedPreventTypeCode,
 16 AS NBit,
 CASE m1_343.InputForm
 WHEN 1 THEN  E'Примечание' END AS Name,
 CASE (COALESCE(m1_343.FormFields,0) & (16 ) ) = (16 ) WHEN true THEN 1 WHEN false THEN 0 END AS Value
FROM
 MedPreventType AS m1_343 WHERE
 m1_343.MedPreventTypeCode = $preventKindId
) AS S ;
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function getNextCipher() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT incstr(COALESCE(asa_trim((
    SELECT
        CAST((RIGHT(REPEAT(' ' , 20) || asa_trim(number), 20)) as varchar(20))
    FROM
        MedFluor
    WHERE
        MedFluorCode = (SELECT max(MedFluorCode) from MedFluor)
        ORDER BY 1 DESC LIMIT 1)), '0')) AS "number"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $cipher = fetchValue($stmt, 'number'); return $cipher; } public function readTips($params) { $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'startDate'))) : null; $finishDate = isEmptyProperty($params, 'finishDate') ? Date("Ymd", safe_strtotime(isEmptyProperty($params, 'finishDate'))) : null; $preventKindId = isset($params->preventKindId) && isEmptyProperty($params, 'preventKindId') ? $params->preventKindId : null; $sql = <<<"EOT"
SELECT DISTINCT
    conclusion "name"
FROM medFluor
WHERE ondate >= $startDate and ondate <= $finishDate and medpreventtypecode = $preventKindId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('med_fluorography_log');