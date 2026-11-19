<?php
 require_once (__DIR__.'/../../init.php'); class med_examinations_riskFactors { private $db; public function readRiskFactors($params = null) { $db = $this->db ?: $this->db = connectDb(); $riskFactorId = isEmptyProperty($params, 'riskFactorId'); $riskFactorIdSQL = ''; $arr = array(); if ($riskFactorId) { $riskFactorIdSQL = ' and (cast(:riskFactorId as INTEGER )  is NULL or :riskFactorId = risk . unidictcode)'; $arr[':riskFactorId'] = $riskFactorId; } $sql = <<<"EOT"
SELECT
    risk.unidictcode as id, 
    risk.unidictcode as "riskId",
    risk.value as "riskName",
    risk.code as "riskСipher"/*,
    ( WITH QList (unidictcode_question) as
        (SELECT DISTINCT
        UniDictCode_Question
        FROM medQuestionary
        WHERE UniDictCode_RiskFactor = risk.UniDictCode
            AND UniDictCode_Question IS NOT NULL)
        SELECT string_agg((UDQ.Code ||  '-' ||
            (SELECT string_agg((UDA.ValueString1 )::text, ',' ORDER BY UDA.Position)
            FROM medQuestionary AS MQ JOIN UniDict AS UDA ON UDA.UniDictCode = COALESCE(MQ.UniDictCode_Answer,0)
            WHERE COALESCE(MQ.UniDictCode_Question,0) = QL.unidictcode_question
            AND COALESCE(MQ.UniDictCode_RiskFactor,0) = risk.UniDictCode))::text,  ', ' ORDER BY UDQ.Position)
        FROM QList AS QL JOIN UniDict AS UDQ ON UDQ.UniDictCode = QL.UniDictCode_Question
      ) as "questionary" */--анкетирование     
FROM
  unidict risk
  LEFT JOIN medquestionary anket on  risk.unidictcode = anket.unidictcode_riskfactor    
    LEFT JOIN unidict question on question.unidictcode = anket.unidictcode_question
    LEFT JOIN unidict answers on answers.unidictcode = anket.unidictcode_answer
WHERE risk.dictnumber = 76 $riskFactorIdSQL
GROUP BY 1,2,3

EOT;
$stmt = $db->prepare($sql); $stmt->execute($arr); $results = $stmt->fetchAll(); return $results; } public function createRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    INSERT INTO unidict (dictnumber, code, value, valueinteger)
    VALUES (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ 76, isEmptyProperty($row, 'riskСipher'), isEmptyProperty($row, 'riskName'), isEmptyProperty($row, 'riskNumber') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_unidict'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE unidict SET    
    code = case when ? = 1 then ? else code end,
    value = case when ? = 1 then ? else value end,
    valueinteger = case when ? = 1 then ? else valueinteger end
WHERE unidictcode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'riskСipher'), isUndefinedProperty($row, 'riskСipher'), propertyExistsSQL($row, 'riskName'), isUndefinedProperty($row, 'riskName'), propertyExistsSQL($row, 'riskNumber'), isUndefinedProperty($row, 'riskNumber'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteRiskFactors($records) { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); try { $sql = <<<"EOT"
                DELETE FROM medQuestionary WHERE unidictcode_riskfactor = ?
            EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $records->id ]); $sql = <<<"EOT"
                DELETE FROM unidict WHERE unidictcode = ?
             EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $records->id ]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } $service = new Junior\Server(new med_examinations_riskFactors()); $service->process(); 