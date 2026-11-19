<?php
 require_once(__DIR__.'/../../init.php'); class med_vaccinations_inoculationsLog { private $db; public function readInoculationsLog($params) { $db = $this->db ?: $this->db = connectDb(); $patientId = isEmptyProperty($params, 'patientId', null); $isCard = isEmptyProperty($params, 'isCard', false); $cardFields = $isCard ? <<<"EOT"
L.medInoculateLogCode as "recordId",
L.InoculateDate       as "recordDate",
L.InoculateDate       as "parentDate",
COALESCE(L.medInoculateLogCode, '')::text || '-' as "path",
9                     as "recordType",
null                  as "parentId",
null                  as "parentType",
8                     as "sortType",
EOT
: ''; $sql = <<<"EOT"
      SELECT
/*Обязательные поля для выбора в амбулаторную карту*/
            $cardFields
/*Основной запрос*/
/*{ 1}*/    L.medInoculateLogCode as "id",                            /*код*/
/*{ 2}*/    asa_trim(G.Name) || ' - ' || I.Name ||
              CASE FailReason
                WHEN 1 THEN ' (Отказ)'
                WHEN 2 THEN ' (Медотвод)'
                ELSE ''
              END                 as "name",                          /*название прививки*/
/*{ 3}*/    L.PlanDate            as "planDate",                      /*плановая дата*/
/*{ 4}*/    L.RuleDate            as "ruleDate",                      /*расчетная дата*/
/*{ 5}*/    L.InoculateDate       as "inoculateDate",                 /*фактическая дата*/
/*{ 6}*/    (CASE WHEN L.prev_InoculateLogCode IS NULL THEN '' ELSE G.Name || ' - ' END) ||
              (SELECT asa_trim(p_I.Name) || ' ' ||
                 DATEFORMAT( p_IL.InoculateDate, 'dd.mm.yyyy' )
               FROM medInoculateLog as p_IL, medInoculate as p_I
               WHERE p_IL.medInoculateLogCode = L.prev_InoculateLogCode
                 AND p_I.medInoculateCode = p_IL.medInoculateCode
               LIMIT 1)           as "lastInoculate"                  /*предыдущая прививка*/
/*
            L.medPatientCode      as medPatientCode,
            FN_cmFIO(P.Mancode)   as medPatientFIO,
            FN_cmCalcAddress( P.ManCode,0,0,1 ) as medPatientAdress,
            P.NumberHistorySickness as NumberHistorySickness,
            (SELECT MP.DateOfBirth
             FROM ManPassport as MP
             WHERE MP.ManCode = P.ManCode LIMIT 1) as DateOfBirth,
            L.medInoculateCode      as medInoculateCode,
            ISNULL(L.InputPlace,0)  as InputPlace,
            ISNULL(L.InputMethod,0) as InputMethod,
            L.ReactionResult        as ReactionResult,
            L.TestResult            as TestResult,
            L.buhMolCode            as buhMolCode,
            ISNULL(L.FailReason,0)  as FailReason,
           (SELECT FN_cmFIO(MOL.ManCode)
            FROM buhMol as MOL
            WHERE MOL.buhMolCode = L.buhMolCode) as buhMolName,
            MONTHS( ISNULL( DateOfBirth, DATE(NOW(*)) ), DATE(NOW(*)) ) / 12 as Age,
            CAST(
              'д/р: ' || DATEFORMAT( DateOfBirth, 'dd.mm.yyyy' ) || CHAR(13)||CHAR(10)||
              'адрес: ' || medPatientAdress || CHAR(13)||CHAR(10)||
              'участок: ' ||
                   (SELECT FIRST PG.Name
                    FROM medPatientGroup as PG
                    WHERE PG.medPatientGroupCode = P.medPatientGroupCode) as VARCHAR(254) ) as Info,
            P.medPatientGroupCode as medPatientGroupCode,
            I.medInoculateGroupCode as medInoculateGroupCode,
            ISNULL(L.InoculateReason,0) as InoculateReason,
            L.TubVaccineResult as TubVaccineResult,
            L.TestTypeResult as TestTypeResult,
            L.ValueResult as ValueResult
*/
       FROM
         (SELECT
            Q.medPatientCode          as medPatientCode,
            Q.PlanDate                as PlanDate,
            Q.medInoculateLogCode     as medInoculateLogCode,
            MAX( Q.medInoculateCode ) as medInoculateCode,
            MAX( Q.RuleDate )         as RuleDate,
            MAX( Q.InoculateDate )    as InoculateDate,
            MAX( Q.InputPlace )       as InputPlace,
            MAX( Q.InputMethod )      as InputMethod,
            MAX( Q.ReactionResult )   as ReactionResult,
            MAX( Q.TestResult )       as TestResult,
            MAX( Q.buhMolCode )       as buhMolCode,
            MAX( Q.FailReason )       as FailReason,
            MAX( Q.InoculateReason )  as InoculateReason,
            MAX( Q.TubVaccineResult ) as TubVaccineResult,
            MAX( Q.TestTypeResult )   as TestTypeResult,
            MAX( Q.ValueResult )      as ValueResult,
            asa_substr(string_agg((Q.prev_InoculateLogCode )::text, ',' ORDER BY Q.prev_InoculateDate DESC), 1, 12 ) as CodeList,
            NULLIF(CAST(
              CASE CHARINDEX(',' , asa_substr(string_agg((Q.prev_InoculateLogCode )::text, ',' ORDER BY Q.prev_InoculateDate DESC), 1, 12 ) ) <= 0
                WHEN true THEN asa_substr(string_agg((Q.prev_InoculateLogCode )::text, ',' ORDER BY Q.prev_InoculateDate DESC), 1, 12 )
                WHEN false THEN asa_substr(asa_substr(string_agg((Q.prev_InoculateLogCode )::text, ',' ORDER BY Q.prev_InoculateDate DESC), 1, 12 ), 1, CHARINDEX(',' , asa_substr(string_agg((Q.prev_InoculateLogCode )::text, ',' ORDER BY Q.prev_InoculateDate DESC), 1, 12 ) ) - 1) END AS int4), 0) as prev_InoculateLogCode
          FROM
            (SELECT
               IL1.medPatientCode      as medPatientCode,
               IL1.PlanDate            as PlanDate,
               IL1.medInoculateLogCode as medInoculateLogCode,
               IL1.medInoculateCode    as medInoculateCode,
               IL1.RuleDate            as RuleDate,
               IL1.InoculateDate       as InoculateDate,
               IL1.InputPlace          as InputPlace,
               IL1.InputMethod         as InputMethod,
               IL1.ReactionResult      as ReactionResult,
               IL1.TestResult          as TestResult,
               IL1.buhMolCode          as buhMolCode,
               IL1.FailReason          as FailReason,
               IL2.medInoculateLogCode as prev_InoculateLogCode,
               IL2.InoculateDate       as prev_InoculateDate,
               IL1.InoculateReason     as InoculateReason,
               IL1.TubVaccineResult    as TubVaccineResult,
               IL1.TestTypeResult      as TestTypeResult,
               IL1.ValueResult         as ValueResult
             FROM MedInoculateLog as IL1
             LEFT JOIN medInoculateLog as IL2 ON IL2.medPatientCode = IL1.medPatientCode AND IL2.InoculateDate < IL1.PlanDate
                 AND (SELECT I2.medInoculateCode
                      FROM medInoculate as I1, medInoculate as I2
                      WHERE I1.medInoculateCode = IL1.medInoculateCode
                        AND I2.medInoculateCode = IL2.medInoculateCode
                        AND I1.medInoculateGroupCode = I2.medInoculateGroupCode LIMIT 1) IS NOT NULL
             WHERE /*(IL1.PlanDate BETWEEN :SD AND :FD OR IL1.InoculateDate BETWEEN :SD AND :FD)
                 AND */IL1.medPatientCode = $patientId) as Q
          GROUP BY medPatientCode, PlanDate, medInoculateLogCode) as L
       LEFT JOIN medPatient as P ON P.medPatientCode = L.medPatientCode /* AND P.EnterpriseCode = :EnterpriseCode */
       LEFT JOIN medInoculate as I ON I.medInoculateCode = L.medInoculateCode
       LEFT JOIN medInoculateGroup as G ON G.medInoculateGroupCode = I.medInoculateGroupCode
       WHERE P.medPatientCode IS NOT NULL
         AND P.FlagGroup IS NULL
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function insertOrUpdateInoculationsLog($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; $sql = <<<"EOT"
INSERT INTO MedInoculateLog (MedInoculateLogCode)
VALUES (?)
ON CONFLICT (MedInoculateLogCode) DO UPDATE SET
    MedInoculateLogCode = case when ? = 1 then EXCLUDED.MedInoculateLogCode else MedInoculateLog.MedInoculateLogCode end
RETURNING MedInoculateLogCode AS id
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $ret[] = $rec = new stdClass; $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), propertyExistsSQL($row, 'id') ]); $rec->id = fetchValue($stmt, 'id'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function createInoculationsLog($records) { return $this->insertOrUpdateInoculationsLog($records, true); } public function updateInoculationsLog($records) { return $this->insertOrUpdateInoculationsLog($records, false); } public function deleteInoculationsLog($records) { return deleteSql($this->db, 'MedInoculateLog', 'MedInoculateLogCode', $records); } } if (!isset($notExecute)) { handleJsonRpc('med_vaccinations_inoculationsLog'); }