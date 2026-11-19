<?php
 namespace ehr; interface iRecipeResourceStandart extends iRecipeResource { } class RecipeResourceStandart extends RecipeResource implements iRecipeResourceStandart { public function getItemFromDB(int $key): string { return ''; } public function getSQL(): string { $link = $this->getLink(); return <<<"EOT"
select
       R.meDRecipeStandartCode "meDRecipeStandartCode",
       0 "sourceCode",
       '?' || R.meDRecipeStandartCode::varchar "prefix",
       R.codeHL7 "guid",
       CASE COALESCE(R.state, 0) <> 2
            WHEN true THEN 'active'
            WHEN false THEN 'entered-in-error'
       END "status",
       asa_trim(R.documentNumber) "documentNumber",
       asa_trim(R.documentSeries) "documentSeries",
       DATEFORMAT(R.outDate, 'yyyy-mm-dd') "outDate",
       DATEFORMAT(DATEADD('DAY', COALESCE(R.termDay, 0),
                  DATEADD('MONTH', COALESCE(R.termMonth, 0),
                  DATEADD('YEAR', COALESCE(R.termYear, 0), COALESCE(R.startDate, R.outDate)))), 'yyyy-mm-dd') "termDate",
       DATEFORMAT(COALESCE(R.startDate, R.outDate), 'yyyy-mm-dd') "startDate",
       0 "privilegePercent",
       WO.codeIMN "codeIMN",
       WO.name "nameMWO",
       WO.secondName "secondNameMWO",
       W.linkCipher "linkCipher",
       COALESCE(W.doseNumber, 1) "doseNumber",
       COALESCE(NULLIF(R.methodUse, '' ), '.' ) "methodUse",
       R.amount "amount",
       R.useINN "useINN",
       'medicinal' "typeMWO",
       R.state "state",
       asa_trim(asa_trim(COALESCE(MANP.surname, '')) || ' ' ||
                asa_trim(COALESCE(MANP.name, '')) || ' ' ||
                asa_trim(COALESCE(MANP.secondname, ' '))) "patientFIO",
       P.codeHL7 "patientGUID",
       asa_trim(asa_trim(MANDR.surname) || ' ' ||
                COALESCE(NULLIF(asa_trim(MANDR.name),  ''), '.') || ' ' ||
                COALESCE(NULLIF(asa_trim(MANDR.secondName), '' ), '.')) "doctorFIO",
       DR.codeHL7 "doctorGUID",
       R.medPatientCode "medPatientCode",
       R.medDoctorCode "medDoctorCode",
       W.matWealthCode "matWealthCode",
       PASDR.personalNumber "drPersonalNumber",
       MB.BarCode "barcode",
       R.SignatureType "signatureType",
       R.Signature "signature",
       R.SignedContent "signedContent"
from MedRecipeStandart R
     join MedPatient P on P.medPatientCode = R.medPatientCode
     join MedDoctor DR on DR.medDoctorCode = R.medDoctorCode
     join MatWealth W on W.matWealthCode = R.matWealthCode
     join MatWealthObject WO on WO.matWealthObjectCode = W.matWealthObjectCode
     left join ManPassport PASDR on PASDR.manCode = DR.manCode
     left join MedBarCode MB on MB.barcode = (select TB.barCode
                                              from MedBarCode TB
                                              where TB.manCode = P.manCode
                                                and COALESCE(TB.barCode, '') <> ''
                                                and TODAY() between TB.startDate and COALESCE(TB.stopDate, 30000101)::date - interval '1 day'
                                              order by TB.startDate, TB.barCode
                                              limit 1)
     left join Man MANDR on MANDR.manCode = DR.manCode
     left join Man MANP on MANP.manCode = P.manCode
where R.enterpriseCode = {$link->getLs()->getParam('enterpriseCode')}
  and R.state in (1,2,5)
  {$link->getRecipeFilter('R.OutDate', 'R.medRecipeStandartCode', $link->getMedRecipeStandartCode())}
EOT;
} } 