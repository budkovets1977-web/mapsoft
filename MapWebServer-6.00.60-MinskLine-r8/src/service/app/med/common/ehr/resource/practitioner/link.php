<?php
 namespace ehr; interface iPractitionerLink extends iPatientLink { } class PractitionerLink extends PatientLink implements iPractitionerLink { protected const RESOURCE_NAME = 'Practitioner'; protected const LINK_SCOPE = 'EHR.Practitioner.Read EHR.Practitioner.Write '; protected $practitioner; public function getPractitioner(): ?PractitionerResource { return $this->practitioner; } public function __construct(LinkParams $params, $timeOut = Exchange::HTTP_TIMEOUT_DEF, $accessScope = '') { parent::__construct($params, $timeOut, self::LINK_SCOPE . $accessScope); $this->practitioner = new PractitionerResource( $this, self::RESOURCE_NAME, 'medDoctor', ResourceItem::ITEM_SOURCE_RECIPE_PRATITIONER ); } public function __destruct() { parent::__destruct(); unset($this->practitioner); } private function getPractitionerListSQL(int $doctorId): string { if (empty($doctorId)) { $where = <<<"EOT"
DR.medDoctorCode = in (select DR_.medDoctorCode
                       from Branch BR
                            join Department D on D.branchCode = BR.branchCode
                            join MedDoctor DR_ on DR_.departmentCode = D.departmentCode
                       where BR.enterpriseCode = {$this->getLs()->getParam('enterpriseCode')}
                         and DR_.state = 1
                         {$this->getDrListFromRecipe()})
EOT;
} else { $where = "DR.medDoctorCode = {$doctorId}"; } return <<<"EOT"
select
      DR.medDoctorCode "medDoctorCode",
      asa_trim(M.surname) "surname",
      COALESCE(NULLIF(asa_trim(M.name), ''), '.') "name",
      COALESCE(NULLIF(asa_trim(M.secondName), '' ), '.') "secondName",
      DR.codeHL7 "guid",
      DR.state "state",
      MP.personalNumber "personalNumber",
      CASE MP.personalNumber <> ''
           WHEN true THEN FN_cmCheckPersonalNumber(MP.personalNumber, 'ПИН "' || MP.personalNumber || '"')
      END "errorPersonalNumber"
from medDoctor DR
     join Man M on M.manCode = DR.manCode
     left join ManPassport MP on MP.manCode = DR.manCode
where
     {$where}
  and (asa_trim(COALESCE(DR.CodeHL7, '')) = '' or COALESCE(DR.State, 0) = 1)
EOT;
} private function getDrListFromRecipe(): string { return ''; } public function transfer(): void { parent::transfer(); $logger = $this->getLogger(); $logger->addLog('начало обработки врачей'); $res = $this->params->doctorCode; $practitioner = $this->getPractitioner(); $practitioner->send2DPC($this->getPractitionerListSQL($res)); $logger->addLog(" обработано врачей: {$practitioner->getGuidCache()->getCount()}"); } public function initTransfer(): void { } } 