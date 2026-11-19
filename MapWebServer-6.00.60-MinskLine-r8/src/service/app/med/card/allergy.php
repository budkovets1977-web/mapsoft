<?php
 require_once(__DIR__ . '/../../init.php'); class med_card_allergy { private $db; public function readAllergy($params) { $patientCode = isUndefinedProperty($params, 'patientCode'); if ($patientCode) { $db = $this->db ?: $this->db = connectDb(); $sqlAllergy = <<<"EOT"
        SELECT 
            MA.MedAllergyCode                                        "id",
            MA.MedPatientCode                                        "patientId",
            MA.Type                                                  "typeAllergy",
            MA.DateDetection                                         "dateDetection",
            MA.DateLastReaction                                      "dateLastReaction",
            MA.DateDetectionText                                     "dateDetectionText",
            MA.MedDoctorCode                                         "doctorId",
            FN_cmFIO(MD.ManCode, 1)                                  "doctor",
            MA.CiszResourceValueCodeCauses                           "causesAllergyId",
            CRVC.Name                                                "causesAllergy",
            MA.AllergenSubstance                                     "substanceAllergy",
            MA.CiszResourceValueCodeClinicalStatus                   "clinicalStatusId",
            CRVS.Name                                                "clinicalStatus",
            MA.CiszResourceValueCodeVerifyStatus                     "verificationAllergyStatusId",
            CRVV.Name                                                 "verificationAllergyStatus",
            MA.Criticality                                           "criticalityAllergy",
            MA.Note                                                  "noteAllergy",
            MA.Json                                                  "allergenAllergyJson",
            ----------------------------РЕАКЦИЯ-----------------------------------
            MAR.MedAllergyReactionCode                               "reactionId",
            MAR.OnDateTime                                           "reactionDate",
            MAR.AllergenSubstance                                    "allergenSubstanceReaction",
            MAR.MedDiagnosisCode                                     "diagnosisId",
            MDG.cipher1                                              "diagnosisCipher",
            MDG.Name                                                 "diagnosis",
            MAR.CiszResourceValueCodeRoute                           "roteId",
            CRVR.Name                                                "rote",
            MAR.MatWealthCode                                        "allergenReactionStopId",
            MAR.Description                                          "reactionDescription",
            MAR.Note                                                 "reactionNote",
            ----------------------Жизненно-важные показатели--------------------------
            MAX(CASE WHEN MARVS.Type = 1 THEN MARVS.ValueNumber END) "bodyTemperature",
            MAX(CASE WHEN MARVS.Type = 2 THEN MARVS.ValueNumber END) "bloodPressureSystolic",
            MAX(CASE WHEN MARVS.Type = 3 THEN MARVS.ValueNumber END) "bloodPressureDiastolic",
            MAX(CASE WHEN MARVS.Type = 4 THEN MARVS.ValueNumber END) "pulse",
            MAX(CASE WHEN MARVS.Type = 5 THEN MARVS.ValueNumber END) "bloodOxygenSaturation",
            MAX(CASE WHEN MARVS.Type = 6 THEN MARVS.ValueNumber END) "intraocularPressureOD",
            MAX(CASE WHEN MARVS.Type = 7 THEN MARVS.ValueNumber END) "intraocularPressureOS",
            MAX(CASE WHEN MARVS.Type = 8 THEN MARVS.ValueNumber END) "glucoseLevel",
            MAX(CASE WHEN MARVS.Type = 9 THEN MARVS.ValueNumber END) "score",
            (SELECT COUNT(*) 
             FROM MedExternalFileT AS MEFT 
             WHERE MEFT.Source = 120 
               AND MEFT.SourceCode = MA.MedAllergyCode)             "documentCount"
        FROM MedAllergy AS MA
            LEFT JOIN CiszResourceValue AS CRVV ON CRVV.CiszResourceValueCode = MA.CiszResourceValueCodeVerifyStatus
            LEFT JOIN CiszResourceValue AS CRVS ON CRVS.CiszResourceValueCode = MA.CiszResourceValueCodeClinicalStatus
            LEFT JOIN MedAllergyReaction AS MAR ON MAR.MedAllergyCode = MA.MedAllergyCode
            LEFT JOIN MedAllergyReactionVitalSigns AS MARVS ON MARVS.MedAllergyReactionCode = MAR.MedAllergyReactionCode
            LEFT JOIN MedDoctor AS MD ON MD.MedDoctorCode = MA.MedDoctorCode
            LEFT JOIN CiszResourceValue AS CRVC ON CRVC.CiszResourceValueCode = MA.CiszResourceValueCodeCauses
            LEFT JOIN MedDiagnosis AS MDG ON MDG.MedDiagnosisCode = MAR.MedDiagnosisCode
            LEFT JOIN CiszResourceValue AS CRVR ON CRVR.CiszResourceValueCode = MAR.CiszResourceValueCodeRoute
            LEFT JOIN MedAllergyReactionToStop AS MARTS ON MARTS.MedAllergyReactionCode = MAR.MedAllergyReactionCode
            LEFT JOIN CiszResourceValue AS CRVRS ON CRVRS.CiszResourceValueCode = MARTS.CiszResourceValueCode
            WHERE MA.medPatientCode = ?
        GROUP BY 
            MA.MedAllergyCode,
            MA.MedPatientCode,
            MA.Type,
            MA.DateDetection,
            MA.DateLastReaction,
            MA.DateDetectionText,
            MA.MedDoctorCode,
            FN_cmFIO(MD.ManCode, 1),
            MA.CiszResourceValueCodeCauses,
            CRVC.Name,
            MA.AllergenSubstance,
            MA.CiszResourceValueCodeClinicalStatus,
            CRVS.Name,
            MA.CiszResourceValueCodeVerifyStatus,
            CRVV.Name,
            MA.Criticality,
            MA.Note,
            MAR.MedAllergyReactionCode,
            MAR.OnDateTime,
            MAR.allergensubstance,
            MAR.MedDiagnosisCode,
            MDG.Name,
            MDG.cipher1,
            MAR.CiszResourceValueCodeRoute,
            CRVR.Name,
            MAR.MatWealthCode,
            MARTS.MedAllergyReactionToStopCode,
            CRVRS.Name,
            MAR.Description,
            MAR.Note
EOT;
$stmtAllergy = $db->prepare($sqlAllergy); $stmtAllergy->execute([$patientCode]); $allergy = $stmtAllergy->fetchAll(); $reactionIds = []; foreach ($allergy as $item) { if (!empty($item['reactionId'])) { $reactionIds[] = $item['reactionId']; } } if (!empty($reactionIds)) { $placeholders = implode(',', array_fill(0, count($reactionIds), '?')); $sqlMeasures = 'SELECT 
                    MARTS.MedAllergyReactionCode    "reactionId",
                    MARTS.CiszResourceValueCode     "measureId",
                    CRVRS.Name                      "measureName"
                FROM MedAllergyReactionToStop AS MARTS
                LEFT JOIN CiszResourceValue AS CRVRS ON CRVRS.CiszResourceValueCode = MARTS.CiszResourceValueCode
                WHERE MARTS.MedAllergyReactionCode IN (' . $placeholders . ')'; $stmtMeasures = $db->prepare($sqlMeasures); $stmtMeasures->execute($reactionIds); $measures = $stmtMeasures->fetchAll(); $measuresByReaction = []; foreach ($measures as $measure) { $reactionId = $measure['reactionId']; if (!isset($measuresByReaction[$reactionId])) { $measuresByReaction[$reactionId] = [ 'ids' => [], 'names' => [] ]; } $measuresByReaction[$reactionId]['ids'][] = $measure['measureId']; $measuresByReaction[$reactionId]['names'][] = $measure['measureName']; } foreach ($allergy as &$item) { $reactionId = $item['reactionId']; if (isset($measuresByReaction[$reactionId])) { $item['measuresForReliefIds'] = $measuresByReaction[$reactionId]['ids']; $item['measuresForRelief'] = $measuresByReaction[$reactionId]['names']; } else { $item['measuresForReliefIds'] = []; $item['measuresForRelief'] = []; } } } return $allergy; } else { throw new Exception("Пациент не определен"); } } public function readAllergyDocuments($params) { $allergyId = isUndefinedProperty($params, 'allergyId'); $db = $this->db ?: $this->db = connectDb(); $sqlDocumentsAllergy = <<<"EOT"
            SELECT
               MEFT.MedExternalFileCode         as "id",
               MEFT.FileName                    as "documentName",
               MEFT.Source                      as "source",
               MEFT.SourceCode                  as "allergyId"
            FROM MedExternalFileT MEFT
            WHERE MEFT.Source = 120 AND MEFT.SourceCode = ?
        EOT;
$stmtDocumentsAllergy = $db->prepare($sqlDocumentsAllergy); $stmtDocumentsAllergy->execute([$allergyId]); $documents = $stmtDocumentsAllergy->fetchAll(); return $documents; } public function insertAllergy($params) { try { $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); $sqlAllergy = "INSERT INTO MedAllergy (
                MedPatientCode,
                Type,
                DateDetection,
                DateLastReaction,
                DateDetectionText,
                MedDoctorCode,
                CiszResourceValueCodeCauses,
                AllergenSubstance,
                CiszResourceValueCodeClinicalStatus,
                CiszResourceValueCodeVerifyStatus,
                Criticality,
                Note,
                Json) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; $stmtAllergy = $db->prepare($sqlAllergy); $stmtAllergy->execute([ isEmptyProperty($params,'patientId'), isEmptyProperty($params, 'typeAllergy'), isEmptyProperty($params, 'dateDetection'), isEmptyProperty($params, 'dateLastReaction'), isEmptyProperty($params, 'dateDetectionText'), isEmptyProperty($params, 'doctorId'), isEmptyProperty($params, 'causesAllergyId'), isEmptyProperty($params, 'substanceAllergy'), isEmptyProperty($params, 'clinicalStatusId'), isEmptyProperty($params, 'verificationAllergyStatusId'), isEmptyProperty($params, 'criticalityAllergy'), isEmptyProperty($params, 'noteAllergy'), isEmptyProperty($params, 'allergenAllergyJson') ]); $allergyId = $db->lastInsertId(); $sqlReaction = "INSERT INTO MedAllergyReaction (
                    MedAllergyCode,
                    OnDateTime,
                    AllergenSubstance,
                    MedDiagnosisCode,
                    CiszResourceValueCodeRoute,
                    MatWealthCode,
                    Description,
                    Note) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; $stmtReaction = $db->prepare($sqlReaction); $stmtReaction->execute([ $allergyId, isEmptyProperty($params,'reactionDate'), isEmptyProperty($params,'allergenSubstanceReaction'), isEmptyProperty($params,'diagnosisId'), isEmptyProperty($params,'roteId'), isEmptyProperty($params,'allergenReactionStopId'), isEmptyProperty($params,'reactionDescription'), isEmptyProperty($params,'reactionNote') ]); $reactionId = $db->lastInsertId(); $sqlMeasures = "INSERT INTO MedAllergyReactionToStop (
                        MedAllergyReactionCode,
                        CiszResourceValueCode ) 
                    VALUES (?, ?)"; $stmtMeasures = $db->prepare($sqlMeasures); $measuresIds = isEmptyProperty($params, 'measuresForRelief'); foreach ($measuresIds as $measureId) { $measureId = intval(trim($measureId)); if (!empty($measureId) && is_numeric($measureId)) { $stmtMeasures->execute([ $reactionId, $measureId ]); } } $vitalSigns = [ ['type' => 1, 'value' => $params->bodyTemperature ?? null], ['type' => 2, 'value' => $params->bloodPressureSystolic ?? null], ['type' => 3, 'value' => $params->bloodPressureDiastolic ?? null], ['type' => 4, 'value' => $params->pulse ?? null], ['type' => 5, 'value' => $params->bloodOxygenSaturation ?? null], ['type' => 6, 'value' => $params->intraocularPressureOD ?? null], ['type' => 7, 'value' => $params->intraocularPressureOS ?? null], ['type' => 8, 'value' => $params->glucoseLevel ?? null], ['type' => 9, 'value' => $params->score ?? null] ]; $sqlVitalSigns = "INSERT INTO MedAllergyReactionVitalSigns (
        MedAllergyReactionCode,
        Type,
        ValueNumber
    ) VALUES (?, ?, ?)"; $stmtVitalSigns = $db->prepare($sqlVitalSigns); foreach ($vitalSigns as $sign) { if ($sign['value'] !== null && $sign['value'] !== 0 && is_numeric($sign['value'])) { $stmtVitalSigns->execute([ $reactionId, $sign['type'], $sign['value'] ]); } } executeSql($db, <<<SQL
                UPDATE CiszLink SET
                   Status = :state
                WHERE
                    PrimaryKey = :id AND type = :type AND TableName = :tableName
            SQL,
[ ':state' => 2, ':id' => isEmptyProperty($params,'patientId'), ':type' => 1, ':tableName' => 'MedPatient' ]); $db->commit(); return $allergyId; } catch (Exception $e) { return $e; } } public function updateAllergy($params) { try { $allergyId = isUndefinedProperty($params, 'id'); if (!$allergyId) { throw new Exception("ID аллергии не указан"); } $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); $sqlUpdateAllergy = "UPDATE MedAllergy SET
            Type = ?,
            DateDetection = ?,
            DateLastReaction = ?,
            DateDetectionText = ?,
            MedDoctorCode = ?,
            CiszResourceValueCodeCauses = ?,
            AllergenSubstance = ?,
            CiszResourceValueCodeClinicalStatus = ?,
            CiszResourceValueCodeVerifyStatus = ?,
            Criticality = ?,
            Note = ?,
            Json = ?
        WHERE MedAllergyCode = ?"; $stmtAllergy = $db->prepare($sqlUpdateAllergy); $stmtAllergy->execute([ isEmptyProperty($params, 'typeAllergy'), isEmptyProperty($params, 'dateDetection'), isEmptyProperty($params, 'dateLastReaction'), isEmptyProperty($params, 'dateDetectionText'), isEmptyProperty($params, 'doctorId'), isEmptyProperty($params, 'causesAllergyId'), isEmptyProperty($params, 'substanceAllergy'), isEmptyProperty($params, 'clinicalStatusId'), isEmptyProperty($params, 'verificationAllergyStatusId'), isEmptyProperty($params, 'criticalityAllergy'), isEmptyProperty($params, 'noteAllergy'), isEmptyProperty($params, 'allergenAllergyJson'), $allergyId ]); $reactionId = isUndefinedProperty($params, 'reactionId'); if ($reactionId) { $sqlUpdateReaction = "UPDATE MedAllergyReaction SET
                OnDateTime = ?,
                AllergenSubstance = ?,
                MedDiagnosisCode = ?,
                CiszResourceValueCodeRoute = ?,
                MatWealthCode = ?,
                Description = ?,
                Note = ?
            WHERE MedAllergyReactionCode = ? AND MedAllergyCode = ?"; $stmtReaction = $db->prepare($sqlUpdateReaction); $stmtReaction->execute([ isEmptyProperty($params, 'reactionDate'), isEmptyProperty($params, 'allergenSubstanceReaction'), isEmptyProperty($params, 'diagnosisId'), isEmptyProperty($params, 'roteId'), isEmptyProperty($params, 'allergenReactionStopId'), isEmptyProperty($params, 'reactionDescription'), isEmptyProperty($params, 'reactionNote'), $reactionId, $allergyId ]); } else { $sqlInsertReaction = "INSERT INTO MedAllergyReaction (
                MedAllergyCode,
                OnDateTime,
                AllergenSubstance,
                MedDiagnosisCode,
                CiszResourceValueCodeRoute,
                MatWealthCode,
                Description,
                Note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; $stmtReaction = $db->prepare($sqlInsertReaction); $stmtReaction->execute([ $allergyId, isEmptyProperty($params, 'reactionDate'), isEmptyProperty($params, 'allergenSubstanceReaction'), isEmptyProperty($params, 'diagnosisId'), isEmptyProperty($params, 'roteId'), isEmptyProperty($params, 'allergenReactionStopId'), isEmptyProperty($params, 'reactionDescription'), isEmptyProperty($params, 'reactionNote') ]); $reactionId = $db->lastInsertId(); } if ($reactionId) { $sqlDeleteMeasures = "DELETE FROM MedAllergyReactionToStop 
                                  WHERE MedAllergyReactionCode = ?"; $stmtDeleteMeasures = $db->prepare($sqlDeleteMeasures); $stmtDeleteMeasures->execute([$reactionId]); $measuresIds = isEmptyProperty($params, 'measuresForRelief'); if (!empty($measuresIds)) { $sqlInsertMeasures = "INSERT INTO MedAllergyReactionToStop (
                    MedAllergyReactionCode,
                    CiszResourceValueCode
                ) VALUES (?, ?)"; $stmtInsertMeasures = $db->prepare($sqlInsertMeasures); foreach ($measuresIds as $measureId) { $measureId = intval(trim($measureId)); if (!empty($measureId) && is_numeric($measureId)) { $stmtInsertMeasures->execute([ $reactionId, $measureId ]); } } } } if ($reactionId) { $sqlDeleteVitalSigns = "DELETE FROM MedAllergyReactionVitalSigns 
                                    WHERE MedAllergyReactionCode = ?"; $stmtDeleteVitalSigns = $db->prepare($sqlDeleteVitalSigns); $stmtDeleteVitalSigns->execute([$reactionId]); $vitalSigns = [ ['type' => 1, 'value' => $params->bodyTemperature ?? null], ['type' => 2, 'value' => $params->bloodPressureSystolic ?? null], ['type' => 3, 'value' => $params->bloodPressureDiastolic ?? null], ['type' => 4, 'value' => $params->pulse ?? null], ['type' => 5, 'value' => $params->bloodOxygenSaturation ?? null], ['type' => 6, 'value' => $params->intraocularPressureOD ?? null], ['type' => 7, 'value' => $params->intraocularPressureOS ?? null], ['type' => 8, 'value' => $params->glucoseLevel ?? null], ['type' => 9, 'value' => $params->score ?? null] ]; $sqlInsertVitalSigns = "INSERT INTO MedAllergyReactionVitalSigns (
        MedAllergyReactionCode,
        Type,
        ValueNumber
    ) VALUES (?, ?, ?)"; $stmtVitalSigns = $db->prepare($sqlInsertVitalSigns); foreach ($vitalSigns as $sign) { if ($sign['value'] !== null && $sign['value'] !== 0 && is_numeric($sign['value'])) { $stmtVitalSigns->execute([ $reactionId, $sign['type'], $sign['value'] ]); } } } executeSql($db, <<<SQL
                UPDATE CiszLink SET
                   Status = :state
                WHERE
                    PrimaryKey = :id AND type = :type AND TableName = :tableName
            SQL,
[ ':state' => 2, ':id' => isEmptyProperty($params,'patientId'), ':type' => 1, ':tableName' => 'MedPatient' ]); $db->commit(); return $allergyId; } catch (Exception $e) { if ($db->inTransaction()) { $db->rollBack(); } return false; } } public function deleteAllergy($params) { try { $allergyId = isUndefinedProperty($params, 'id'); if (!$allergyId) { throw new Exception("ID аллергии не указан"); } $db = $this->db ?: $this->db = connectDb(); $db->beginTransaction(); $sqlGetReaction = "SELECT MedAllergyReactionCode FROM MedAllergyReaction 
                          WHERE MedAllergyCode = ?"; $stmtGetReaction = $db->prepare($sqlGetReaction); $stmtGetReaction->execute([$allergyId]); $reaction = $stmtGetReaction->fetch(); $reactionId = $reaction['MedAllergyReactionCode'] ?? null; if ($reactionId) { $sqlDeleteVitalSigns = "DELETE FROM MedAllergyReactionVitalSigns 
                                   WHERE MedAllergyReactionCode = ?"; $stmtDeleteVitalSigns = $db->prepare($sqlDeleteVitalSigns); $stmtDeleteVitalSigns->execute([$reactionId]); $sqlDeleteMeasures = "DELETE FROM MedAllergyReactionToStop 
                                 WHERE MedAllergyReactionCode = ?"; $stmtDeleteMeasures = $db->prepare($sqlDeleteMeasures); $stmtDeleteMeasures->execute([$reactionId]); $sqlDeleteReaction = "DELETE FROM MedAllergyReaction WHERE MedAllergyCode = ?"; $stmtDeleteReaction = $db->prepare($sqlDeleteReaction); $stmtDeleteReaction->execute([$allergyId]); } $sqlDeleteDocuments = "DELETE FROM MedExternalFileT 
                              WHERE Source = 120 AND SourceCode = ?"; $stmtDeleteDocuments = $db->prepare($sqlDeleteDocuments); $stmtDeleteDocuments->execute([$allergyId]); $sqlDeleteAllergy = "DELETE FROM MedAllergy WHERE MedAllergyCode = ?"; $stmtDeleteAllergy = $db->prepare($sqlDeleteAllergy); $stmtDeleteAllergy->execute([$allergyId]); $rowCount = $stmtDeleteAllergy->rowCount(); $sqlDelete = "DELETE FROM MedDayHopsitalPurpose WHERE MedAllergyCode = ?"; $stmtDeleteLs = $db->prepare($sqlDelete); $stmtDeleteLs->execute([$allergyId]); if ($rowCount === 0) { throw new Exception("Аллергия с ID $allergyId не найдена"); } $db->commit(); return true; } catch (Exception $e) { if (isset($db) && $db->inTransaction()) { $db->rollBack(); } return false; } } } handleJsonRpc('med_card_allergy'); 