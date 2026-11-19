<?php
 namespace ehr; interface iPatientLink extends iLink { const PHONE_LIST_DELIMITER = ';'; } class PatientLink extends Link implements iPatientLink { protected const RESOURCE_NAME = 'Patient'; protected const LINK_SCOPE = 'EHR.Patient.Read EHR.Patient.Write '; protected $patient; public function __construct(LinkParams $params, $timeOut = Exchange::HTTP_TIMEOUT_DEF, $accessScope = '') { parent::__construct($params, $timeOut, self::LINK_SCOPE . $accessScope); $this->patient = new PatientResource($this, self::RESOURCE_NAME, 'medPatient', ResourceItem::ITEM_SOURCE_RECIPE_PATIENT); } public function __destruct() { unset($this->patient); parent::__destruct(); } public function getPatient(): ?PatientResource { return $this->patient; } private function getPatientListSQL(int $patientId): string { $where = empty($patientId) ? "MP.enterpriseCode = {$this->getLs()->getParam('enterpriseCode')}" : "MP.medPatientCode = {$patientId}"; $delimiter = self::PHONE_LIST_DELIMITER; return <<<"EOT"
select
       MP.medPatientCode "medPatientCode",
       asa_trim(COALESCE(M.surname, '')) "surname",
       asa_trim(COALESCE(M.name, '')) "name",
       asa_trim(COALESCE(M.secondName, '')) "secondName",
       CASE COALESCE(P.sex, -1)
            WHEN -1 THEN  'unknown'
            WHEN 0 THEN  'male'
            WHEN 1 THEN  'female'
       END "sex",
       CASE
            WHEN P.dateOfBirth IS NULL THEN ''
            ELSE DATEFORMAT(P.dateOfBirth, 'yyyy-mm-dd')
       END "dateOfBirth",
       MB.barCode "barcode",
       P.personalNumber "personalNumber",
       MP.outReason "outReason",
       NULL "addressReside",
       FN_cmCalcAddress(MP.manCode, 5, 4, 1) "addressRegistration", -- адрес жительства, если нет то адрес прописки
       (select -- указав 0 в типе, мы искусственно отключим вывод типа телефона в JSON-ке для ЦОД ЭР
               string_agg(('0.' || PH_.phoneNumber)::text, '{$delimiter}' ORDER BY PH_.phoneNumber)
        from ManPhone PH_
        where PH_.manCode = MP.manCode
          and PH_.phoneNumber <> '') "phoneList",
       MP.numberHistorySickness "numberHistory",
       MP.codeHL7 "guid",
       MP.state "medPatientState",
       MB.state "medPatientBarState",
       COALESCE(CASE MP.enterpriseCode = MPP.enterpriseCode
                     WHEN true THEN 1
                END, 0) "isLocal",
       CASE P.personalNumber <> ''
            WHEN true THEN FN_cmCheckPersonalNumber(P.personalNumber, 'ПИН "' || P.personalNumber || '"')
       END "errorPersonalNumber"
from medPatient MP
     join Man M on M.manCode = MP.manCode
     join ManPassport P on P.manCode = M.manCode
     left join medPatientPolyclinic MPP on MPP.medPatientPolyclinicCode = MP.medPatientPolyclinicCode
     join medBarCode MB on MB.barCode = (select TB.barCode
                                         from medBarCode TB
                                         where TB.manCode = M.manCode
                                           and COALESCE(TB.barCode, '') <> ''
                                           and today() between TB.startDate and DATEADD('DAY', -1, COALESCE(TB.stopDate, 30000101))
                                         order by TB.startDate, TB.barCode
                                       limit 1)
where
{$where}
  and MP.state = 1
EOT;
} public function transfer(): void { parent::transfer(); $logger = $this->getLogger(); $logger->addLog('начало обработки пациентов'); $res = $this->params->patientCode; $patient = $this->getPatient(); $patient->send2DPC($this->getPatientListSQL($res)); $logger->addLog(" обработано пациентов: {$patient->getGuidCache()->getCount()}"); } public function initTransfer(): void { } } 