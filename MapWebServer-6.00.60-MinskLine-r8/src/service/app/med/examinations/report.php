<?php
require_once(__DIR__.'/../../init.php'); class med_examinations_report { private $db; public function readReport($params = null){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                medResearchReportCode   "id",
                name                    "reportName"
            FROM medResearchReport
            ORDER BY name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $ret = $stmt->fetchAll(); return $ret; } public function createReport($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $ret = insertSql($db, 'medResearchReport', $records, 'name', 'reportName'); return $ret; } public function updateReport($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $ret = updateSql($db, 'medResearchReport', 'medResearchReportCode', $records, 'name', 'reportName'); return $ret; } public function deleteReport($records){ $db = $this->db ?: $this->db = connectDb(); $ret = deleteSql($db, 'medResearchReport', 'medResearchReportCode', $records); return $ret; } public function readReportRow($params = null){ $db = $this->db ?: $this->db = connectDb(); $reportId = isEmptyProperty($params, 'reportId'); $sql = <<<EOT
            SELECT
                medResearchReportRowCode    "id",
                medResearchReportCode       "reportId",
                name                        "reportRowName",
                position                    "position",
                sourceType                  "sourceTypeId"
            FROM medResearchReportRow
            WHERE medResearchReportCode = :reportId
            ORDER BY position
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ 'reportId' => $reportId ]); $ret = $stmt->fetchAll(); return $ret; } public function createReportRow($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $ret = insertSql($db, 'medResearchReportRow', $records, 'medResearchReportCode, name, sourceType, position', 'reportId, reportRowName, sourceTypeId, position' ); return $ret; } public function updateReportRow($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sqlPos = <<<EOT
            UPDATE medResearchReportRow
            SET position = NULL
            WHERE medResearchReportRowCode = ?
EOT;
$sqlRowInDelete = <<<EOT
            DELETE FROM medResearchReportRowIn
            WHERE medResearchReportRowCode = ?
EOT;
foreach ($records as $row){ $position = isUndefinedProperty($row, 'position'); $sourceTypeId = isUndefinedProperty($row, 'sourceTypeId'); if ($position){ executeSql($db, $sqlPos, [$row->id]); } if ($sourceTypeId){ executeSql($db, $sqlRowInDelete, [$row->id]); } } $ret = updateSql($db, 'medResearchReportRow', 'medResearchReportRowCode', $records, 'medResearchReportCode, name, sourceType, position', 'reportId, reportRowName, sourceTypeId, position' ); return $ret; } public function deleteReportRow($records){ $db = $this->db ?: $this->db = connectDb(); $ret = deleteSql($db, 'medResearchReportRow', 'medResearchReportRowCode', $records); return $ret; } public function readReportRowIn($params = null){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $reportRowId = isEmptyProperty($params, 'reportRowId'); $reportRowSourceTypeId = isEmptyProperty($params, 'reportRowSourceTypeId'); $sql = <<<EOT
            SELECT
                ri.medResearchReportRowInCode       "id",
                ri.medResearchReportRowCode         "researchReportRowId",
                ri.htmTemplateCode                  "htmTemplateId",
                ht.name                             "htmTemplateName",
                ri.medPreventTypeCode               "preventTypeId",
                pt.name                             "preventTypeName",
                ri.uniDictCodeResearch              "uniDictResearchId",
                ud.value                            "uniDictResearchName",
                ------------
                -- ri.medPreventTypeCode               "preventTypeId",
                -- pt.name                             "preventTypeName",
                -- ri.uniDictCodeResearch              "uniDictResearchId",
                -- ud.value                            "uniDictResearchName",
                ------------
                ri.medStudyCode                     "studyId",
                ms.name                             "studyName",
                ri.outputMask                       "outputMask",
                CASE WHEN ((ri.outputMask & 1) = 1) THEN TRUE ELSE FALSE END
                                                    "flagDate",
                CASE WHEN ((ri.outputMask & 2) = 2) THEN TRUE ELSE FALSE END
                                                    "flagResult",
                CASE WHEN ((ri.outputMask & 4) = 4) THEN TRUE ELSE FALSE END
                                                    "flagTemplate",
                ri.resultTemplate                   "resultTemplate"
            FROM medResearchReportRowIn ri
            LEFT JOIN htmTemplate ht ON ht.htmTemplateCode = ri.htmTemplateCode
            LEFT JOIN medPreventType pt ON pt.medPreventTypeCode = ri.medPreventTypeCode
            LEFT JOIN uniDict ud ON ud.uniDictCode = ri.uniDictCodeResearch
            LEFT JOIN medStudy ms ON ms.medStudyCode = ri.medStudyCode
            WHERE medResearchReportRowCode = :reportRowId
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ 'reportRowId' => $reportRowId ]); $ret = $stmt->fetchAll(); return $ret; } public function createReportRowIn($records){ $db = $this->db ?: $this->db = connectDb(); $ret = null; if (is_object($records)){ $records = [$records]; } $ret = insertSql($db, 'medResearchReportRowIn', $records, 'htmTemplateCode, medPreventTypeCode, medResearchReportRowCode,
                medStudyCode, outputMask, resultTemplate,
                uniDictCodeResearch', 'htmTemplateId, preventTypeId, researchReportRowId,
                studyId, outputMask, resultTemplate,
                uniDictResearchId' ); return $ret; } public function updateReportRowIn($records){ $db = $this->db ?: $this->db = connectDb(); $ret = null; if (is_object($records)){ $records = [$records]; } $ret = updateSql($db, 'medResearchReportRowIn', 'medResearchReportRowInCode', $records, 'htmTemplateCode, medPreventTypeCode, medResearchReportRowCode,
                medStudyCode, outputMask, resultTemplate,
                uniDictCodeResearch', 'htmTemplateId, preventTypeId, researchReportRowId,
                studyId, outputMask, resultTemplate,
                uniDictResearchId' ); return $ret; } public function deleteReportRowIn($records){ $db = $this->db ?: $this->db = connectDb(); $ret = null; $ret = deleteSql($db, 'medResearchReportRowIn', 'medResearchReportRowInCode', $records); return $ret; } public function copyReport($params){ $db = $this->db ?: $this->db = connectDb(); $reportId = isUndefinedProperty($params, 'reportId'); $sql = <<<EOT
            DO $$
                DECLARE
                    varReportIdTo int;
                    varReportRowIdTo int;
                    c1_r RECORD;
                    c1_ CURSOR FOR
                        SELECT
                            name || ' (копия)' reportName
                        FROM medResearchReport
                        WHERE medResearchReportCode = $reportId;
                BEGIN
                    FOR c1_r IN c1_ LOOP
                        INSERT INTO medResearchReport(name)
                        VALUES (c1_r.reportName);
                        varReportIdTo := lastval();
                    END LOOP;

                DECLARE
                    c2_r RECORD;
                    c2_ CURSOR FOR
                        SELECT
                            medResearchReportRowCode    reportRowId,
                            name                        reportRowName,
                            sourceType                  reportRowSourceType,
                            position                    reportRowPosition
                        FROM medResearchReportRow
                        WHERE medResearchReportCode = $reportId;
                BEGIN
                    FOR c2_r IN c2_ LOOP
                        INSERT INTO medResearchReportRow
                            (medResearchReportCode, name, sourceType, position)
                        VALUES (varReportIdTo, c2_r.reportRowName, c2_r.reportRowSourceType,
                                c2_r.reportRowPosition);
                        varReportRowIdTo := lastval();
                        INSERT INTO medResearchReportRowIn
                            (medResearchReportRowCode, htmTemplateCode, medPreventTypeCode,
                            medStudyCode, uniDictCodeResearch, outputMask,
                            resultTemplate)
                        SELECT
                            varReportRowIdTo,
                            htmTemplateCode,
                            medPreventTypeCode,
                            medStudyCode,
                            uniDictCodeResearch,
                            outputMask,
                            resultTemplate
                        FROM medResearchReportRowIn
                        WHERE medResearchReportRowCode = c2_r.reportRowId;
                    END LOOP;
                END;
            END $$;
EOT;
$db->beginTransaction(); try { executeSql($db, $sql); $db->commit(); return true; } catch (Throwable $e){ $db->rollback(); throw $e; } return false; } } handleJsonRpc('med_examinations_report');