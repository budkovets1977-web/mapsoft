<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_referrals { private $db; public function readReferrals(){ $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT
                s.medStudyCode              "referralId",
                s.name                      "referralName",
                s.fullCode                  "fullCode",
                msl.medEqStudyLinkCode      "studyLinkId",
                ms.medEqServiceCode         "serviceId",
                ms.name                     "serviceName",
                mso.medEqServiceOptionCode  "optionId",
                mso.name                    "optionName",
                htm.isTemplate              "templateType"
            FROM medStudy s
            LEFT JOIN htmPageT htm ON htm.htmPageCode = s.htmPageCode
            LEFT JOIN medEqStudyLink msl ON msl.medStudyCode = s.medStudyCode
            LEFT JOIN medEqService ms ON ms.medEqServiceCode = msl.medEqServiceCode
            LEFT JOIN medEqServiceOption mso ON mso.medEqServiceOptionCode = msl.medEqServiceOptionCode
            WHERE htm.isTemplate IN (1, 3)
            ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateReferrals($records){ $db = $this->db ?: $this->db = connectDb(); $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            INSERT INTO medEqStudyLink AS mes (medStudyCode,
                                            medEqServiceCode,
                                            medEqServiceOptionCode,
                                            enterpriseCode)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (medStudyCode, enterpriseCode)
            DO UPDATE
            SET medStudyCode = CASE WHEN ? = 1 THEN EXCLUDED.medStudyCode ELSE mes.medStudyCode END,
                medEqServiceCode = CASE WHEN ? = 1 THEN EXCLUDED.medEqServiceCode ELSE mes.medEqServiceCode END,
                medEqServiceOptionCode = CASE WHEN ? = 1 THEN EXCLUDED.medEqServiceOptionCode ELSE mes.medEqServiceOptionCode END,
                enterpriseCode = EXCLUDED.enterpriseCode
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ isEmptyProperty($records, 'referralId'), isEmptyProperty($records, 'serviceId'), isEmptyProperty($records, 'optionId'), $enterpriseId, propertyExistsSQL($records, 'referralId'), propertyExistsSQL($records, 'serviceId'), propertyExistsSQL($records, 'optionId') ]); return true; } public function deleteReferrals($records){ $db = $this->db ?: $this->db = connectDb(); $linkId = $records->linkId; $sql = "DELETE FROM medEqStudyLink WHERE medEqStudyLinkCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $linkId ]); return true; } } handleJsonRpc('equeue_app_referrals');