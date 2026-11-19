<?php
 require_once(__DIR__ . '/../../init.php'); class med_lab_studiesSOP { private $db; public function readStudiesSOP($params = null) { $db = $this->db ?: $this->db = connectDb(); $studyId = isset($params->studyId) ? (int) $params->studyId : 0; $checkDicomAttributes = isset($params->checkDicomAttributes) ? (bool) $params->checkDicomAttributes : false; $joins = $checkDicomAttributes ? <<<"EOT"
LEFT JOIN DicomPacs DP ON MLSS.DicomPacsCode = DP.DicomPacsCode
LEFT JOIN DicomService DS ON DS.DicomServiceCode = DP.DicomServiceCode
EOT
: ''; $where = $checkDicomAttributes ? <<<"EOT"
AND (((~ COALESCE(DS.Attributes, 0)) & 1) = 1)
EOT
: ''; if ($params->group) { $sql = <<<"EOT"
select
    MLSS.StudyInstanceUID "id",
    MLSS.MedLogStudyCode "studyId",
    MLSS.DicomPacsCode "pacsId",
    MLSS.StudyInstanceUID "studyInstanceUid",
    MLSS.StudyTime "studyTime",
    MLSS.Modality "modality"
from MedLogStudySOP MLSS
    $joins
    WHERE (MLSS.MedLogStudyCode = ? OR 0 = ?) $where
group by MLSS.MedLogStudyCode, MLSS.StudyTime, MLSS.DicomPacsCode, MLSS.StudyInstanceUID, MLSS.Modality
order by MLSS.StudyTime DESC, MLSS.Modality
EOT;
} else { $sql = <<<"EOT"
select
    MLSS.MedLogStudySOPCode "id",
    MLSS.MedLogStudyCode "studyId",
    MLSS.DicomPacsCode "pacsId",
    MLSS.PatientId "patientId",
    MLSS.StudyInstanceUID "studyInstanceUid",
    MLSS.StudyTime "studyTime",
    MLSS.AccessionNumber "accessNum",
    MLSS.SeriesInstanceUID "seriesInstanceUid",
    MLSS.SeriesNumber "seriesNum",
    MLSS.Modality "modality",
    MLSS.SopInstanceUID "sopInstanceUid",
    MLSS.InstanceNumber "instanceNum"
from MedLogStudySOP MLSS
    $joins
    WHERE (MLSS.MedLogStudyCode = ? OR 0 = ?) $where
order by MLSS.StudyTime DESC, MLSS.Modality
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([$studyId, $studyId]); return $stmt->fetchAll(); } } handleJsonRpc('med_lab_studiesSOP'); 