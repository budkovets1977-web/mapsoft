<?php
 require_once(__DIR__.'/../../init.php'); function mb_strcasecmp($target1, $target2, $encoding = null) { if ($encoding === null) { $encoding1 = mb_detect_encoding($target1); $encoding2 = mb_detect_encoding($target2); } else { $encoding1 = $encoding; $encoding2 = $encoding; } return strcmp(mb_strtoupper($target1, $encoding1), mb_strtoupper($target2, $encoding2)); } class map_med_inspectRW { private $db; private $enterpriseCode; const professionDictNumber = 19; const researchDictNumber = 28; const professionRWDictNumber = 41; const comissionDictNumber = 42; const workConditionDictNumber = 43; private $sqlSelectMedPatient = <<<'EOT'
SELECT FIRST
    MedPatientCode as "medPatientCode",
    MedPatientGroupCode as "medPatientGroupCode",
    NumberHistorySickness as "numberHistorySickness"
FROM medPatient
WHERE
    EnterpriseCode = ?
    AND ISNULL( ManCode, 0 ) = ?
    AND TODAY() BETWEEN ISNULL(StartDate,19000101) AND ISNULL(FinishDate,30000101)
EOT;
private $sqlSelectMedPatientPg = <<<'EOT'
SELECT
    MedPatientCode as "medPatientCode",
    MedPatientGroupCode as "medPatientGroupCode",
    NumberHistorySickness as "numberHistorySickness"
FROM medPatient
WHERE
    EnterpriseCode = ?
    AND COALESCE( ManCode, 0 ) = ?
    AND today() BETWEEN COALESCE(StartDate,19000101) AND COALESCE(FinishDate,30000101) LIMIT 1
EOT;
private $stmtSelectMedPatient; private $sqlAddMedPatient = <<<'EOT'
INSERT INTO MedPatient
    (medPatientGroupCode, numberHistorySickness, manCode, startDate, finishDate, enterpriseCode)
VALUES
    (?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedPatient; private $sqlUpdateMedPatient = <<<'EOT'
UPDATE MedPatient
SET
    medPatientGroupCode = ?,
    numberHistorySickness = ?
WHERE
    medPatientCode = ?
EOT;
private $stmtUpdateMedPatient; private $sqlSelectManPassport = <<<'EOT'
SELECT
    M.ManCode as "manCode",
    M.Surname as "surname",
    M.Name as "name",
    M.SecondName as "secondName",
    MP.ManPassportCode as "manPassportCode",
    MP.DateOfBirth as "dateOfBirth",
    MP.Sex as "sex",
    MP.AddressCode as "addressCode",
    MP.AddressHouse as "addressHouse",
    MP.AddressCorps as "addressCorps",
    MP.AddressFlat as "addressFlat",
    MP.ZipCode as "zipCode",
    MP.AddressResideCode as "addressResideCode",
    MP.AddressResideHouse as "addressResideHouse",
    MP.AddressResideCorps as "addressResideCorps",
    MP.AddressResideFlat as "addressResideFlat",
    MP.ZipCodeReside as "zipResideCode"
FROM
    ManPassport MP
    JOIN Man M ON M.ManCode = MP.ManCode
WHERE
    coalesce(MP.PersonalNumber,'') = ?
EOT;
private $stmtSelectManPassport; private $sqlAddMan = <<<'EOT'
INSERT INTO Man
    (SurName, Name, SecondName)
VALUES
    (?,?,?)
EOT;
private $stmtAddMan; private $sqlAddManPassport = <<<'EOT'
INSERT INTO ManPassport
    (ManCode, PersonalNumber, DateOfBirth, Sex, AddressCode, AddressHouse, AddressCorps, AddressFlat, ZipCode,
     AddressResideCode, AddressResideHouse, AddressResideCorps, AddressResideFlat, ZipCodeReside)
VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddManPassport; private $sqlUpdateManPassport = <<<'EOT'
UPDATE ManPassport SET
    DateOfBirth = ?,
    Sex = ?,
    AddressCode = ?,
    AddressHouse = ?,
    AddressCorps = ?,
    AddressFlat = ?,
    ZipCode = ?,
    AddressResideCode = ?,
    AddressResideHouse = ?,
    AddressResideCorps = ?,
    AddressResideFlat = ?,
    ZipCodeReside = ?
WHERE
    ManPassportCode = ?
EOT;
private $stmtUpdateManPassport; private $sqlSelectPatientGroup = <<<'EOT'
SELECT
    MedPatientGroupCode AS "medPatientGroupCode",
    Name as "name"
FROM MedPatientGroup
EOT;
private $stmtSelectPatientGroup; private $sqlAddPatientGroup = <<<'EOT'
INSERT INTO MedPatientGroup
    (Cipher, Name, VisitZone, EnterpriseCode)
VALUES
    (?, ?, ?, ?)
EOT;
private $stmtAddPatientGroup; private $sqlSelectCountry = <<<'EOT'
SELECT
    CountryCode as "countryCode",
    Cipher as "cipher",
    Name as "name"
FROM Country
EOT;
private $stmtSelectCountry; private $sqlAddCountry = <<<'EOT'
INSERT INTO Country
    (Cipher, Name)
VALUES
    (?, ?)
EOT;
private $stmtAddCountry; private $sqlSelectAddressPlaceType = <<<'EOT'
SELECT
    AddressPlaceTypeCode as "addressPlaceTypeCode",
    ShortName as "shortName",
    Name as "name"
FROM AddressPlaceType
EOT;
private $stmtSelectAddressPlaceType; private $sqlAddAddressPlaceType = <<<'EOT'
INSERT INTO AddressPlaceType
    (ShortName, Name)
VALUES
    (?, ?)
EOT;
private $stmtAddAddressPlaceType; private $sqlSelectAddressStreetType = <<<'EOT'
SELECT
    AddressStreetTypeCode AS "addressStreetTypeCode",
    Name AS "name",
    ShortName AS "shortName"
FROM AddressStreetType
EOT;
private $stmtSelectAddressStreetType; private $sqlAddAddressStreetType = <<<'EOT'
INSERT INTO AddressStreetType
    (ShortName, Name)
VALUES
    (?, ?)
EOT;
private $stmtAddAddressStreetType; private $sqlSelectAddress = <<<'EOT'
SELECT
    AddressCode AS "addressCode",
    CountryCode AS "countryCode",
    Region AS "region",
    District AS "district",
    Area AS "area",
    Place AS "place",
    Ward AS "ward",
    Street AS "street",
    WardType AS "wardType",
    AreaType AS "areaType",
    CodeSOATO AS "codeSOATO",
    AddressPlaceTypeCode AS "addressPlaceTypeCode",
    AddressStreetTypeCode AS "addressStreetTypeCode"
FROM Address
EOT;
private $stmtSelectAddress; private $sqlAddAddress = <<<'EOT'
INSERT INTO Address
    (CountryCode, Region, District, Area, Place, Ward, Street, WardType, AreaType, CodeSOATO, AddressPlaceTypeCode, AddressStreetTypeCode)
VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddAddress; private $sqlSelectMedPatientBusy = <<<'EOT'
SELECT
    MPB.MedPatientBusyCode as "medPatientBusyCode",
    MPB.EnterpriseKindCode as "enterpriseKindCode",
    EK.Name as "enterpriseKindName",
    MPB.EnterpriseCode as "enterpriseCode",
    E.name as "enterpriseName",
    MPB.StartDate as "startDate",
    MPB.FinishDate as "finishDate",
    MPB.ClassGroup as "classGroup",
    MPB.UniDictCodeProfession as "uniDictProfessionCode",
    ud.Value as "UniDictProfessionName",
    MPB.DepartmentCode as "departmentCode",
    D.Name as "departmantName",
    B.BranchCode as "branchCode",
    B.Name as "BranchName"
FROM
    MedPatientBusy MPB
    JOIN Enterprise e on MPB.EnterpriseCode = E.EnterpriseCode
    LEFT JOIN Department D ON D.DepartmentCode = MPB.DepartmentCode
    LEFT JOIN Branch B ON B.BranchCode = D.BranchCode AND B.EnterpriseCode = MPB.EnterpriseCode
    LEFT JOIN EnterpriseKind EK on EK.EnterpriseKindCode = MPB.EnterpriseKindCode
    left join UniDict ud on ud.uniDictCode = MPB.UniDictCodeProfession
WHERE
    MedPatientCode = ?
    and today() >= coalesce(MPB.StartDate,19000101)
    and today() <= coalesce(MPB.FinishDate,30000101)
    and ( (coalesce(B.CloseDate, 30000101) > MPB.StartDate AND
           coalesce(D.CloseDate, 30000101) > MPB.StartDate
          )
          OR MPB.DepartmentCode IS NULL )
EOT;
private $stmtSelectMedPatientBusy; private $sqlAddMedPatientBusy = <<<'EOT'
INSERT INTO MedPatientBusy
    (MedPatientCode, EnterpriseCode, EnterpriseKindCode, DepartmentCode, StartDate, FinishDate, ClassGroup, UniDictCodeProfession)
VALUES
    (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedPatientBusy; private $sqlUpdateMedPatientBusy = <<<'EOT'
UPDATE MedPatientBusy SET
    EnterpriseKindCode = ?,
    DepartmentCode = ?,
    FinishDate = ?,
    ClassGroup = ?,
    UniDictCodeProfession = ?
WHERE
    MedPatientBusyCode = ?
EOT;
private $stmtUpdateMedPatientBusy; private $sqlDeleteMedPatientBusy = <<<'EOT'
DELETE FROM MedPatientBusy
WHERE MedPatientBusyCode = ?
EOT;
private $stmtDeleteMedPatientBusy; private $sqlSelectEnterpriseKind = <<<'EOT'
SELECT
    EnterpriseKindCode AS "enterpriseKindCode",
    Name as "name"
FROM EnterpriseKind
EOT;
private $stmtSelectEnterpriseKind; private $sqlAddEnterpriseKind = <<<'EOT'
INSERT INTO EnterpriseKind
    (Name)
VALUES
    (?)
EOT;
private $stmtAddEnterpriseKind; private $sqlSelectEnterprise = <<<'EOT'
SELECT
    EnterpriseCode AS "enterpriseCode",
    Cipher as "cipher",
    Name as "name"
FROM Enterprise
EOT;
private $stmtSelectEnterprise; private $sqlAddEnterprise = <<<'EOT'
INSERT INTO Enterprise
    (Name, FullName, StartDate, FinishDate)
VALUES
    (?, ?, ?, 30000101)
EOT;
private $stmtAddEnterprise; private $sqlSelectBranch = <<<'EOT'
SELECT
    BranchCode AS "branchCode",
    EnterpriseCode as "enterpriseCode",
    Name as "name"
FROM
    Branch
WHERE
    coalesce(CloseDate,30000101) >= today()
EOT;
private $stmtSelectBranch; private $sqlAddBranch = <<<'EOT'
INSERT INTO Branch
    (Name, FullName, EnterpriseCode)
VALUES
    (?, ?, ?)
EOT;
private $stmtAddBranch; private $sqlSelectDepartment = <<<'EOT'
SELECT
    DepartmentCode as "departmentCode",
    BranchCode AS "branchCode",
    Name as "name"
FROM
    Department
WHERE
    coalesce(CloseDate,30000101) >= today()
EOT;
private $stmtSelectDepartment; private $sqlAddDepartment = <<<'EOT'
INSERT INTO Department
    (Name, FullName, BranchCode)
VALUES
    (?, ?, ?)
EOT;
private $stmtAddDepartment; private $sqlSelectUniDictData = <<<'EOT'
SELECT
    UniDictCode AS "uniDictCode",
    Value as "name"
FROM UniDict
WHERE
    DictNumber = ?
EOT;
private $stmtSelectUniDictData; private $sqlAddUniDictData = <<<'EOT'
INSERT INTO UniDict
    (DictNumber, Value)
VALUES
    (? , ?)
EOT;
private $stmtAddUniDictData; private $sqlSelectMedPreventType = <<<'EOT'
SELECT
    MedPreventTypeCode AS "medPreventTypeCode",
    Name AS "name"
FROM MedPreventType
WHERE EnterpriseCode = ?
EOT;
private $stmtSelectMedPreventType; private $sqlAddMedPreventType = <<<'EOT'
INSERT INTO MedPreventType
    (EnterpriseCode, Name, FormFields, InputForm)
VALUES
    (?, ?, ?, 5)
EOT;
private $stmtAddMedPreventType; private $sqlSelectMedPrevent = <<<'EOT'
SELECT
    MedPreventCode as "medPreventCode",
    MedPreventTypeCode as "medPreventTypeCode",
    Position as "position",
    Name as name
FROM MedPrevent
EOT;
private $stmtSelectMedPrevent; private $sqlAddMedPrevent = <<<'EOT'
INSERT INTO MedPrevent
    (MedPreventTypeCode, Name, PeriodMonth, Sex, AgeFrom, AgeTo, Position)
VALUES
    (?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedPrevent; private $sqlSelectMedInspect = <<<'EOT'
SELECT
    MedInspectCode as "medInspectCode",
    Checked as "checked",
    PlanDate as "planDate",
    MedPatientCode as "medPatientCode",
    MedPreventCode as "medPreventCode",
    DiagnosisNote as "diagnosisNote",
    DocumentList as "documentList",
    Information as "information",
    InspectKind as "inspectKind",
    InspectDate as "inspectDate",
    DocumentNumber as "documentNumber",
    ActualDate as "actualDate",
    UniDictCodeDrComission as "uniDictDrComissionCode",
    UniDictCodeDrComissionDirect as "uniDictDrComissionDirectCode",
    UniDictCodeProfessionRW as "uniDictProfessionRWCode",
    UniDictCodeWorkCondition as "uniDictWorkConditionCode"
FROM
    MedInspect
WHERE
    EnterpriseCode = ?
    AND GUID = ?
EOT;
private $stmtSelectMedInspect; private $sqlAddMedInspect = <<<'EOT'
INSERT INTO MedInspect
    (EnterpriseCode,
     MedPreventCode,
     MedPatientCode,
     PlanDate,
     DiagnosisNote,
     DocumentList,
     Information,
     InspectKind,
     InspectDate,
     DocumentNumber,
     ActualDate,
     UniDictCodeDrComission,
     UniDictCodeDrComissionDirect,
     UniDictCodeProfessionRW,
     UniDictCodeWorkCondition,
     GUID )
VALUES
    ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedInspect; private $sqlUpdateMedInspect = <<<'EOT'
UPDATE MedInspect
SET
    MedPreventCode = ?,
    MedPatientCode = ?,
    PlanDate = ?,
    DiagnosisNote = ?,
    DocumentList = ?,
    Information = ?,
    InspectKind = ?,
    InspectDate = ?,
    DocumentNumber =?,
    ActualDate = ?,
    UniDictCodeDrComission = ?,
    UniDictCodeDrComissionDirect = ?,
    UniDictCodeProfessionRW = ?,
    UniDictCodeWorkCondition = ?
WHERE
    MedInspectCode = ?
EOT;
private $stmtUpdateMedInspect; private $sqlSelectMedProfHazard = <<<'EOT'
SELECT
    MedProfHazardCode as "medProfHazardCode",
    Cipher as "cipher",
    InspectType as "inspectType"
FROM MedProfHazard
WHERE COALESCE(Active,0) = 1
EOT;
private $stmtSelectMedProfHazard; private $sqlSelectMedInspectHazard = <<<'EOT'
SELECT
    MedInspectHazardCode as "medInspectHazardCode",
    MedProfHazardCode as "medProfHazardCode",
    StageMonth as "stageMonth",
    StageYear as "stageYear"
FROM
    MedInspectHazard
WHERE
    medInspectCode = ?
EOT;
private $stmtSelectMedInspectHazard; private $sqlAddMedInspectHazard = <<<'EOT'
INSERT INTO MedInspectHazard
    (MedInspectCode, MedProfHazardCode, StageMonth, StageYear)
VALUES
    (?, ?, ?, ?)
EOT;
private $stmtAddMedInspectHazard; private $sqlUpdateMedInspectHazard = <<<'EOT'
UPDATE MedInspectHazard
SET
    MedProfHazardCode = ?,
    StageMonth = ?,
    StageYear = ?
WHERE
    MedInspectHazardCode = ?
EOT;
private $stmtUpdateMedInspectHazard; private $sqlDeleteMedInspectHazard = <<<'EOT'
DELETE FROM MedInspectHazard
WHERE
    MedInspectHazardCode = ?
EOT;
private $stmtDeleteMedInspectHazard; private $sqlSelectMedProfession = <<<'EOT'
SELECT
    MedProfessionCode as "medProfessionCode",
    Name as name
FROM
    MedProfession
EOT;
private $stmtSelectMedProfession; private $sqlAddMedProfession = <<<'EOT'
INSERT INTO MedProfession
    (cipher, name)
VALUES
    (?, ?)
EOT;
private $stmtAddMedProfession; private $sqlSelectMedInspectResult = <<<'EOT'
SELECT
    R.MedInspectResultCode as "medInspectResultCode",
    R.InspectDate as "inspectDate",
    R.MedProfessionCode as "medProfessionCode",
    R.MedDiagnosisCode as "medDiagnosisCode",
    R.Manual as "manual",
    R.Note as "note",
    R.FirstTime as "firstTime",
    R.ProfDisease as "profDisease",
    R.Contraindication as "contraindication",
    R.PrimaryDiagnosis as "primaryDiagnosis"
FROM
    MedInspectResult R
WHERE
    R.MedInspectCode = ?
EOT;
private $stmtSelectMedInspectResult; private $sqlAddMedInspectResult = <<<'EOT'
INSERT INTO MedInspectResult
    (MedInspectCode, InspectDate, MedProfessionCode, MedDiagnosisCode, Manual, Note, FirstTime, ProfDisease, Contraindication, PrimaryDiagnosis)
VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedInspectResult; private $sqlUpdateMedInspectResult = <<<'EOT'
UPDATE MedInspectResult
SET
    InspectDate = ?,
    MedProfessionCode = ?,
    MedDiagnosisCode = ?,
    Manual = ?,
    Note = ?,
    FirstTime = ?,
    ProfDisease = ?,
    Contraindication = ?,
    PrimaryDiagnosis = ?
WHERE
    MedInspectResultCode = ?
EOT;
private $stmtUpdateMedInspectResult; private $sqlDeleteMedInspectResult = <<<'EOT'
DELETE FROM MedInspectResult
WHERE
    MedInspectResultCode = ?
EOT;
private $stmtDeleteMedInspectResult; private $sqlSelectMedDiagnosis = <<<'EOT'
SELECT FIRST
    MedDiagnosisCode AS "medDiagnosisCode"
FROM
    MedDiagnosis
WHERE
    ISNULL(Cipher1,'') = ?
EOT;
private $sqlSelectMedDiagnosisPg = <<<'EOT'
SELECT
    MedDiagnosisCode AS "medDiagnosisCode"
FROM
    MedDiagnosis
WHERE
    COALESCE(Cipher1, E'' ) = ?
LIMIT 1
EOT;
private $stmtSelectMedDiagnosis; private $sqlSelectMedInspectResearch = <<<'EOT'
SELECT
    M.MedInspectResearchCode as "medInspectResearchCode",
    M.ResearchDate AS "researchDate",
    M.UniDictCodeResearch as "uniDictResearchCode",
    M.MedDiagnosisCode as "medDiagnosisCode",
    M.Conclusion as "conclusion",
    M.AddExamination as "addExamination"
FROM
    MedInspectResearch AS M
WHERE
    M.MedInspectCode = ?
EOT;
private $stmtSelectMedInspectResearch; private $sqlAddMedInspectResearch = <<<'EOT'
INSERT INTO MedInspectResearch
    (MedInspectCode, ResearchDate, UniDictCodeResearch, MedDiagnosisCode, Conclusion, AddExamination)
VALUES
    (?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedInspectResearch; private $sqlUpdateMedInspectResearch = <<<'EOT'
UPDATE MedInspectResearch
SET
    ResearchDate = ?,
    UniDictCodeResearch = ?,
    MedDiagnosisCode = ?,
    Conclusion = ?,
    AddExamination = ?
WHERE
    MedInspectResearchCode = ?
EOT;
private $stmtUpdateMedInspectResearch; private $sqlDeleteMedInspectResearch = <<<'EOT'
DELETE FROM MedInspectResearch
WHERE
    MedInspectResearchCode = ?
EOT;
private $stmtDeleteMedInspectResearch; private $sqlSelectMedAction = <<<'EOT'
SELECT
    MedActionCode as "medActionCode",
    Name as "name"
FROM MedAction
EOT;
private $stmtSelectMedAction; private $sqlAddMedAction = <<<'EOT'
INSERT INTO MedAction
    (Name, Note)
VALUES
    (?, ?)
EOT;
private $stmtAddMedAction; private $sqlSelectMedInspectAction = <<<'EOT'
SELECT
    MedInspectActionCode as "medInspectActionCode",
    MedActionCode as "medActionCode",
    MedDiagnosisCode as "medDiagnosisCode",
    Executed as "executed",
    Recommend as "recommend",
    RecommendBefore as "recommendBefore"
FROM
    MedInspectAction
WHERE
    MedInspectCode = ?
EOT;
private $stmtSelectMedInspectAction; private $sqlAddMedInspectAction = <<<'EOT'
INSERT INTO MedInspectAction
    (MedInspectCode, MedActionCode, MedDiagnosisCode, Executed, Recommend, RecommendBefore)
VALUES
    (?, ?, ?, ?, ?, ?)
EOT;
private $stmtAddMedInspectAction; private $sqlUpdateMedInspectAction = <<<'EOT'
UPDATE MedInspectResearch
SET
    MedActionCode = ?,
    MedDiagnosisCode = ?,
    Executed = ?,
    Recommend = ?,
    RecommendBefore = ?
WHERE
    MedInspectActionCode = ?
EOT;
private $stmtUpdateMedInspectAction; private $sqlDeleteMedInspectAction = <<<'EOT'
DELETE FROM MedInspectAction
WHERE
    MedInspectActionCode = ?
EOT;
private $stmtDeleteMedInspectAction; private $sqlSelectMedConclusion = <<<'EOT'
SELECT
    MedConclusionCode as "medConclusionCode",
    Name as "name"
FROM MedConclusion
EOT;
private $stmtSelectMedConclusion; private $sqlAddMedConclusion = <<<'EOT'
INSERT INTO MedConclusion
    (Name)
VALUES
    (?)
EOT;
private $stmtAddMedConclusion; private $sqlSelectMedConclusionGroup = <<<'EOT'
SELECT
    MedConclusionGroupCode as "medConclusionGroupCode",
    MedConclusionCode as "medConclusionCode",
    Position as "position",
    Name as "name"
FROM MedConclusionGroup
EOT;
private $stmtSelectMedConclusionGroup; private $sqlAddMedConclusionGroup = <<<'EOT'
INSERT INTO MedConclusionGroup
    (MedConclusionCode, Name, Position)
VALUES
    (?, ?, ?)
EOT;
private $stmtAddMedConclusionGroup ; private $sqlSelectMedInspectConclusion = <<<'EOT'
SELECT
    MedInspectConclusionCode as "medInspectConclusionCode",
    MedConclusionCode as "medConclusionCode",
    MedConclusionGroupCode as "medConclusionGroupCode",
    Note as "note"
FROM
    MedInspectConclusion
WHERE
    MedInspectCode = ?
EOT;
private $stmtSelectMedInspectConclusion; private $sqlAddMedInspectConclusion = <<<'EOT'
INSERT INTO MedInspectConclusion
    (MedInspectCode, MedConclusionCode, MedConclusionGroupCode, Note)
VALUES
    (?, ?, ?, ?)
EOT;
private $stmtAddMedInspectConclusion; private $sqlUpdateMedInspectConclusion = <<<'EOT'
UPDATE MedInspectConclusion
SET
    MedConclusionCode = ?,
    MedConclusionGroupCode = ?,
    Note = ?
WHERE
    MedInspectConclusionCode = ?
EOT;
private $stmtUpdateMedInspectConclusion; private $sqlDeleteMedInspectConclusion = <<<'EOT'
DELETE FROM MedInspectConclusion
WHERE
    MedInspectConclusionCode = ?
EOT;
private $stmtDeleteMedInspectConclusion; private function readSettings() { $settings = readJsonSettings('settings_inspectrw'); if ($settings && isset($settings['configName']) && isset($settings['enterpriseCode'])) { $this->enterpriseCode = $settings['enterpriseCode']; return $settings['configName']; } else { return null; } } private function connectDataBase() { $сonfig = $this->readSettings(); if (!$сonfig) { die("Ошибка: отсутствует настройка конфигурации сервиса"); } $this->db = $this->db ?: connectDb($сonfig, null, true); return $this->db; } public function ping () { TestHTTPS(); $сonfig = $this->readSettings(); if (!$сonfig) { die("Ошибка: отсутствует настройка конфигурации сервиса"); } $this->db = $this->db ?: connectDb($сonfig, null, true); return true; } private function prepareSQL() { $db = $this->db or $db = $this->db = $this->connectDataBase(); $this->stmtSelectMedPatient = $db->prepare($db->dialect($this->sqlSelectMedPatient, $this->sqlSelectMedPatientPg)); $this->stmtAddMedPatient = $db->prepare($this->sqlAddMedPatient); $this->stmtUpdateMedPatient = $db->prepare($this->sqlUpdateMedPatient); $this->stmtSelectManPassport = $db->prepare($this->sqlSelectManPassport); $this->stmtAddMan = $db->prepare($this->sqlAddMan); $this->stmtAddManPassport = $db->prepare($this->sqlAddManPassport); $this->stmtUpdateManPassport = $db->prepare($this->sqlUpdateManPassport); $this->stmtSelectPatientGroup = $db->prepare($this->sqlSelectPatientGroup); $this->stmtAddPatientGroup = $db->prepare($this->sqlAddPatientGroup); $this->stmtSelectCountry = $db->prepare($this->sqlSelectCountry); $this->stmtAddCountry = $db->prepare($this->sqlAddCountry); $this->stmtSelectAddressPlaceType = $db->prepare($this->sqlSelectAddressPlaceType); $this->stmtAddAddressPlaceType = $db->prepare($this->sqlAddAddressPlaceType); $this->stmtSelectAddressStreetType = $db->prepare($this->sqlSelectAddressStreetType); $this->stmtAddAddressStreetType = $db->prepare($this->sqlAddAddressStreetType); $this->stmtSelectAddress = $db->prepare($this->sqlSelectAddress); $this->stmtAddAddress = $db->prepare($this->sqlAddAddress); $this->stmtSelectMedPatientBusy = $db->prepare($this->sqlSelectMedPatientBusy); $this->stmtAddMedPatientBusy = $db->prepare($this->sqlAddMedPatientBusy); $this->stmtUpdateMedPatientBusy = $db->prepare($this->sqlUpdateMedPatientBusy); $this->stmtDeleteMedPatientBusy = $db->prepare($this->sqlDeleteMedPatientBusy); $this->stmtSelectEnterpriseKind = $db->prepare($this->sqlSelectEnterpriseKind); $this->stmtAddEnterpriseKind = $db->prepare($this->sqlAddEnterpriseKind); $this->stmtSelectEnterprise = $db->prepare($this->sqlSelectEnterprise); $this->stmtAddEnterprise = $db->prepare($this->sqlAddEnterprise); $this->stmtSelectBranch = $db->prepare($this->sqlSelectBranch); $this->stmtAddBranch = $db->prepare($this->sqlAddBranch); $this->stmtSelectDepartment = $db->prepare($this->sqlSelectDepartment); $this->stmtAddDepartment = $db->prepare($this->sqlAddDepartment); $this->stmtSelectUniDictData = $db->prepare($this->sqlSelectUniDictData); $this->stmtAddUniDictData = $db->prepare($this->sqlAddUniDictData); $this->stmtSelectMedPreventType = $db->prepare($this->sqlSelectMedPreventType); $this->stmtAddMedPreventType = $db->prepare($this->sqlAddMedPreventType); $this->stmtSelectMedPrevent = $db->prepare($this->sqlSelectMedPrevent); $this->stmtAddMedPrevent = $db->prepare($this->sqlAddMedPrevent); $this->stmtSelectMedInspect = $db->prepare($this->sqlSelectMedInspect); $this->stmtAddMedInspect = $db->prepare($this->sqlAddMedInspect); $this->stmtUpdateMedInspect = $db->prepare($this->sqlUpdateMedInspect); $this->stmtSelectMedProfHazard = $db->prepare($this->sqlSelectMedProfHazard); $this->stmtSelectMedInspectHazard = $db->prepare($this->sqlSelectMedInspectHazard); $this->stmtAddMedInspectHazard = $db->prepare($this->sqlAddMedInspectHazard); $this->stmtUpdateMedInspectHazard = $db->prepare($this->sqlUpdateMedInspectHazard); $this->stmtDeleteMedInspectHazard = $db->prepare($this->sqlDeleteMedInspectHazard); $this->stmtSelectMedProfession = $db->prepare($this->sqlSelectMedProfession); $this->stmtAddMedProfession = $db->prepare($this->sqlAddMedProfession); $this->stmtSelectMedInspectResult = $db->prepare($this->sqlSelectMedInspectResult); $this->stmtAddMedInspectResult = $db->prepare($this->sqlAddMedInspectResult); $this->stmtUpdateMedInspectResult = $db->prepare($this->sqlUpdateMedInspectResult); $this->stmtDeleteMedInspectResult = $db->prepare($this->sqlDeleteMedInspectResult); $this->stmtSelectMedDiagnosis = $db->prepare($db->dialect($this->sqlSelectMedDiagnosis, $this->sqlSelectMedDiagnosisPg)); $this->stmtSelectMedInspectResearch = $db->prepare($this->sqlSelectMedInspectResearch); $this->stmtAddMedInspectResearch = $db->prepare($this->sqlAddMedInspectResearch); $this->stmtUpdateMedInspectResearch = $db->prepare($this->sqlUpdateMedInspectResearch); $this->stmtDeleteMedInspectResearch = $db->prepare($this->sqlDeleteMedInspectResearch); $this->stmtSelectMedAction = $db->prepare($this->sqlSelectMedAction); $this->stmtAddMedAction = $db->prepare($this->sqlAddMedAction); $this->stmtSelectMedInspectAction = $db->prepare($this->sqlSelectMedInspectAction); $this->stmtAddMedInspectAction = $db->prepare($this->sqlAddMedInspectAction); $this->stmtUpdateMedInspectAction = $db->prepare($this->sqlUpdateMedInspectAction); $this->stmtDeleteMedInspectAction = $db->prepare($this->sqlDeleteMedInspectAction); $this->stmtSelectMedConclusion = $db->prepare($this->sqlSelectMedConclusion); $this->stmtAddMedConclusion = $db->prepare($this->sqlAddMedConclusion); $this->stmtSelectMedConclusionGroup = $db->prepare($this->sqlSelectMedConclusionGroup); $this->stmtAddMedConclusionGroup = $db->prepare($this->sqlAddMedConclusionGroup); $this->stmtSelectMedInspectConclusion = $db->prepare($this->sqlSelectMedInspectConclusion); $this->stmtAddMedInspectConclusion = $db->prepare($this->sqlAddMedInspectConclusion); $this->stmtUpdateMedInspectConclusion = $db->prepare($this->sqlUpdateMedInspectConclusion); $this->stmtDeleteMedInspectConclusion = $db->prepare($this->sqlDeleteMedInspectConclusion); } public function syncInspectData ($aData) { TestHTTPS(); $db = $this->db or $db = $this->db = $this->connectDataBase(); $enterpriseCode = $this->enterpriseCode; $this->prepareSQL(); $aMedPatientGroup = $this->getArrayData($this->stmtSelectPatientGroup, null, ['medPatientGroupCode', 'name']); $aCountry = $this->getArrayData($this->stmtSelectCountry, null, ['countryCode', 'cipher', 'name']); $aAddressPlaceType = $this->getArrayData($this->stmtSelectAddressPlaceType, null, ['addressPlaceTypeCode', 'shortName', 'name']); $aAddressStreetType = $this->getArrayData($this->stmtSelectAddressStreetType, null, ['addressStreetTypeCode', 'shortName', 'name']); $aAddress = $this->getArrayData($this->stmtSelectAddress, null, ['addressCode', 'countryCode', 'region', 'district', 'area', 'place', 'ward', 'street', 'wardType', 'areaType', 'codeSOATO', 'addressPlaceTypeCode', 'addressStreetTypeCode']); $aEnterpriseKind = $this->getArrayData($this->stmtSelectEnterpriseKind, null, ['enterpriseKindCode', 'name']); $aEnterprise = $this->getArrayData($this->stmtSelectEnterprise, null, ['enterpriseCode', 'name']); $aBranch = $this->getArrayData($this->stmtSelectBranch, null, ['branchCode', 'enterpriseCode', 'name']); $aDepartment = $this->getArrayData($this->stmtSelectDepartment, null, ['departmentCode', 'branchCode', 'name']); $aUniDictProfession = $this->getArrayData($this->stmtSelectUniDictData, [self::professionDictNumber], ['uniDictCode', 'name']); $aMedPreventType = $this->getArrayData($this->stmtSelectMedPreventType, [$enterpriseCode], ['medPreventTypeCode', 'name']); $aMedPrevent = $this->getArrayData($this->stmtSelectMedPrevent, null, ['medPreventCode', 'medPreventTypeCode', 'name', 'position']); $aUniDictDrComission = $this->getArrayData($this->stmtSelectUniDictData, [self::comissionDictNumber], ['uniDictCode', 'name']); $aUniDictProfessionRW = $this->getArrayData($this->stmtSelectUniDictData, [self::professionRWDictNumber], ['uniDictCode', 'name']); $aUniDictWorkCondition = $this->getArrayData($this->stmtSelectUniDictData, [self::workConditionDictNumber], ['uniDictCode', 'name']); $aMedProfHazard = $this->getArrayData($this->stmtSelectMedProfHazard, null, ['medProfHazardCode', 'cipher', 'inspectType']); $aMedProfession = $this->getArrayData($this->stmtSelectMedProfession, null, ['medProfessionCode','name']); $aUniDictResearch = $this->getArrayData($this->stmtSelectUniDictData, [self::researchDictNumber], ['uniDictCode', 'name']); $aMedAction = $this->getArrayData($this->stmtSelectMedAction, null, ['medActionCode', 'name']); $aMedConclusion = $this->getArrayData($this->stmtSelectMedConclusion, null, ['medConclusionCode', 'name']); $aMedConclusionGroup = $this->getArrayData($this->stmtSelectMedConclusionGroup, null, ['medConclusionGroupCode', 'medConclusionCode', 'position', 'name']); if ($db->inTransaction()) { $db->commit(); } $res = []; foreach ($aData as $inspection) { $db->beginTransaction(); try { $res[] = $result = new stdClass; $result->guid = isEmptyProperty($inspection, 'guid'); $medPatientData = $inspection->medPatientData; $medPatientData->medPatientGroupCode = $this->syncMedPatientGroup($medPatientData, $aMedPatientGroup); $medPatientData->countryCode = $this->syncCountry(1, $medPatientData, $aCountry); $medPatientData->addressPlaceTypeCode = $this->syncAddressPlaceType(1, $medPatientData, $aAddressPlaceType); $medPatientData->addressStreetTypeCode = $this->syncAddressStreetType(1, $medPatientData, $aAddressStreetType); $medPatientData->addressCode = $this->syncAddress(1, $medPatientData, $aAddress); $medPatientData->countryResideCode = $this->syncCountry(2, $medPatientData, $aCountry); $medPatientData->addressResidePlaceTypeCode = $this->syncAddressPlaceType(2, $medPatientData, $aAddressPlaceType); $medPatientData->addressResideStreetTypeCode = $this->syncAddressStreetType(2, $medPatientData, $aAddressStreetType); $medPatientData->addressResideCode = $this->syncAddress(2, $medPatientData, $aAddress); $medPatientData->manCode = $this->syncMan($medPatientData); $medPatientData->medPatientCode = $this->synсMedPatient($medPatientData, $aEnterprise, $aBranch, $aDepartment, $aEnterpriseKind, $aUniDictProfession); $inspection->medPreventTypeCode = $this->syncMedPreventType($inspection, $aMedPreventType); $inspection->medPreventCode = $this->syncMedPrevent($inspection, $aMedPrevent); $inspection->uniDictDrComissionCode = $this->synсUniDictData($inspection, 'uniDictCode', 'uniDictDrComissionName', self::comissionDictNumber, $aUniDictDrComission); $inspection->uniDictDrComissionDirectCode = $this->synсUniDictData($inspection, 'uniDictCode', 'uniDictDrComissionDirectName', self::comissionDictNumber, $aUniDictDrComission); $inspection->uniDictProfessionRWCode = $this->synсUniDictData($inspection, 'uniDictCode', 'uniDictProfessionRWName', self::professionRWDictNumber, $aUniDictProfessionRW); $inspection->uniDictWorkConditionCode = $this->synсUniDictData($inspection, 'uniDictCode', 'uniDictWorkConditionName', self::workConditionDictNumber, $aUniDictWorkCondition); $inspection->state = $this->syncMedInspect($inspection, $aMedProfHazard, $aMedProfession, $aUniDictResearch, $aMedAction, $aMedConclusion, $aMedConclusionGroup); $db->commit(); $result->state = $inspection->state; } catch (Throwable $e) { $db->rollBack(); $result->state = -1; throw $e; } } return $res; } private function syncMedPatientGroup($medPatientData, &$aMedPatientGroup) { $enterpriseCode = $this->enterpriseCode; $stmtAddPatientGroup = $this->stmtAddPatientGroup; $foundCode = 0; foreach($aMedPatientGroup as $group) { if(mb_strcasecmp(isEmptyProperty($group,'name'), isEmptyProperty($medPatientData,'medPatientGroupName')) == 0 ) { $foundCode = $group->medPatientGroupCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddPatientGroup->execute([isEmptyProperty($medPatientData, 'medPatientGroupCipher'), isEmptyProperty($medPatientData, 'medPatientGroupName'), isEmptyProperty($medPatientData, 'medPatientGroupVisitZone'), $enterpriseCode]); $medPatientGroupCode = $this->db->lastInsertId(); $aMedPatientGroup[] = $group = new stdClass; $group->medPatientGroupCode = $medPatientGroupCode; $group->name = isEmptyProperty($medPatientData, 'medPatientGroupName'); return $medPatientGroupCode; } } private function syncCountry($type, $medPatientData, &$aCountry) { if (!isEmptyProperty($medPatientData, ($type==1 ? 'countryName' : 'countryResideName'))) { return null; } else { $stmtAddCountry = $this->stmtAddCountry; $foundCode = 0; foreach($aCountry as $country) { if(mb_strcasecmp(isEmptyProperty($country,'name'), isEmptyProperty($medPatientData, ($type==1 ? 'countryName' : 'countryResideName'))) == 0 ) { $foundCode = $country->countryCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddCountry->execute([isEmptyProperty($medPatientData, ($type==1 ? 'countryCipher' : 'countryResideCipher')), isEmptyProperty($medPatientData, ($type==1 ? 'countryName' : 'countryResideName'))]); $countryCode = $this->db->lastInsertId(); $aCountry[] = $country = new stdClass; $country->countryCode = $countryCode; $country->cipher = isEmptyProperty($medPatientData, ($type==1 ? 'countryCipher' : 'countryResideCipher')); $country->name = isEmptyProperty($medPatientData, ($type==1 ? 'countryName' : 'countryResideName')); return $countryCode; } } } private function syncAddressPlaceType($type, $medPatientData, &$aAddressPlaceType) { if (!isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeShortName' : 'addressResidePlaceTypeShortName'))) { return null; } else { $stmtAddAddressPlaceType = $this->stmtAddAddressPlaceType; $foundCode = 0; foreach($aAddressPlaceType as $addressPlaceType) { if(mb_strcasecmp(isEmptyProperty($addressPlaceType,'shortName'), isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeShortName' : 'addressResidePlaceTypeShortName'))) == 0 ) { $foundCode = $addressPlaceType->addressPlaceTypeCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddAddressPlaceType->execute([isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeShortName' : 'addressResidePlaceTypeShortName')), isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeName' : 'addressResidePlaceTypeName'))]); $addressPlaceTypeCode = $this->db->lastInsertId(); $aAddressPlaceType[] = $placeType = new stdClass; $placeType->addressPlaceTypeCode = $addressPlaceTypeCode; $placeType->shortName = isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeShortName' : 'addressResidePlaceTypeShortName')); $placeType->name = isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeName' : 'addressResidePlaceTypeName')); return $addressPlaceTypeCode; } } } private function syncAddressStreetType($type, $medPatientData, &$aAddressStreetType) { if (!isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeShortName' : 'addressResideStreetTypeShortName'))) { return null; } else { $stmtAddAddressStreetType = $this->stmtAddAddressStreetType; $foundCode = 0; foreach($aAddressStreetType as $addressStreetType) { if(mb_strcasecmp(isEmptyProperty($addressStreetType,'shortName'), isEmptyProperty($medPatientData,($type==1 ? 'addressStreetTypeShortName' : 'addressResideStreetTypeShortName'))) == 0 ) { $foundCode = $addressStreetType->addressStreetTypeCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddAddressStreetType->execute([isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeShortName' : 'addressResideStreetTypeShortName')), isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeName' : 'addressResideStreetTypeName'))]); $addressStreetTypeCode = $this->db->lastInsertId(); $aAddressStreetType[] = $addressStreetType = new stdClass; $addressStreetType->addressStreetTypeCode = $addressStreetTypeCode; $addressStreetType->shortName = isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeShortName' : 'addressResideStreetTypeShortName')); $addressStreetType->name = isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeName' : 'addressResideStreetTypeName')); return $addressStreetTypeCode; } } } private function syncAddress($type, $medPatientData, &$aAddress) { if (!isEmptyProperty($medPatientData, ($type==1 ? 'countryCode' : 'countryResideCode')) && !isEmptyProperty($medPatientData, ($type==1 ? 'region' : 'regionReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'district' : 'districtReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'area' : 'areaReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'place' : 'placeReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'ward' : 'wardReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'street' : 'streetReside')) && !isEmptyProperty($medPatientData, ($type==1 ? 'wardType' : 'wardResideType')) && !isEmptyProperty($medPatientData, ($type==1 ? 'areaType' : 'areaResideType')) && !isEmptyProperty($medPatientData, ($type==1 ? 'codeSOATO' : 'resideCodeSOATO')) && !isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeCode' : 'addressResidePlaceTypeCode')) && !isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeCode' : 'addressResideStreetTypeCode'))) { return null; } else { if (($type == 2) && !empty($medPatientData->addressCode) && (isEmptyProperty($medPatientData, 'countryCode') == isEmptyProperty($medPatientData, 'countryResideCode')) && (mb_strcasecmp(isEmptyProperty($medPatientData,'region'), isEmptyProperty($medPatientData,'regionReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'district'), isEmptyProperty($medPatientData,'districtReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'area'), isEmptyProperty($medPatientData,'areaReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'place'), isEmptyProperty($medPatientData,'placeReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'ward'), isEmptyProperty($medPatientData,'wardReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'street'), isEmptyProperty($medPatientData,'streetReside')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'wardType'), isEmptyProperty($medPatientData,'wardResideType')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'areaType'), isEmptyProperty($medPatientData,'areaResideType')) == 0) && (mb_strcasecmp(isEmptyProperty($medPatientData,'codeSOATO'), isEmptyProperty($medPatientData,'resideCodeSOATO')) == 0)) { return $medPatientData->addressCode; } else { $stmtAddAddress = $this->stmtAddAddress; $foundCode = 0; foreach($aAddress as $address) { if( (isEmptyProperty($address,'countryCode') == isEmptyProperty($medPatientData, ($type==1 ? 'countryCode' : 'countryResideCode'))) && (mb_strcasecmp(isEmptyProperty($address,'region'), isEmptyProperty($medPatientData,($type==1 ? 'region' : 'regionReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'district'), isEmptyProperty($medPatientData,($type==1 ? 'district' : 'districtReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'area'), isEmptyProperty($medPatientData,($type==1 ? 'area' : 'areaReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'place'), isEmptyProperty($medPatientData,($type==1 ? 'place' : 'placeReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'ward'), isEmptyProperty($medPatientData,($type==1 ? 'ward' : 'wardReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'street'), isEmptyProperty($medPatientData,($type==1 ? 'street' : 'streetReside'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'wardType'), isEmptyProperty($medPatientData,($type==1 ? 'wardType' : 'wardResideType'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'areaType'), isEmptyProperty($medPatientData,($type==1 ? 'areaType' : 'areaResideType'))) == 0) && (mb_strcasecmp(isEmptyProperty($address,'codeSOATO'), isEmptyProperty($medPatientData,($type==1 ? 'codeSOATO' : 'resideCodeSOATO'))) == 0) && (isEmptyProperty($address,'addressPlaceTypeCode') == isEmptyProperty($medPatientData,($type==1 ? 'addressPlaceTypeCode' : 'addressResidePlaceTypeCode'))) && (isEmptyProperty($address,'addressStreetTypeCode') == isEmptyProperty($medPatientData,($type==1 ? 'addressStreetTypeCode' : 'addressResideStreetTypeCode'))) ) { $foundCode = $address->addressCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddAddress->execute([isEmptyProperty($medPatientData, ($type==1 ? 'countryCode' : 'countryResideCode')), isEmptyProperty($medPatientData, ($type==1 ? 'region' : 'regionReside')), isEmptyProperty($medPatientData, ($type==1 ? 'district' : 'districtReside')), isEmptyProperty($medPatientData, ($type==1 ? 'area' : 'areaReside')), isEmptyProperty($medPatientData, ($type==1 ? 'place' : 'placeReside')), isEmptyProperty($medPatientData, ($type==1 ? 'ward' : 'wardReside')), isEmptyProperty($medPatientData, ($type==1 ? 'street' : 'streetReside')), isEmptyProperty($medPatientData, ($type==1 ? 'wardType' : 'wardResideType')), isEmptyProperty($medPatientData, ($type==1 ? 'areaType' : 'areaResideType')), isEmptyProperty($medPatientData, ($type==1 ? 'codeSOATO' : 'resideCodeSOATO')), isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeCode' : 'addressResidePlaceTypeCode')), isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeCode' : 'addressResideStreetTypeCode'))]); $addressCode = $this->db->lastInsertId(); $aAddress[] = $address = new stdClass; $address->addressCode = $addressCode; $address->countryCode = isEmptyProperty($medPatientData, ($type==1 ? 'countryCode' : 'countryResideCode')); $address->region = isEmptyProperty($medPatientData, ($type==1 ? 'region' : 'regionReside')); $address->district = isEmptyProperty($medPatientData, ($type==1 ? 'district' : 'districtReside')); $address->area = isEmptyProperty($medPatientData, ($type==1 ? 'area' : 'areaReside')); $address->place = isEmptyProperty($medPatientData, ($type==1 ? 'place' : 'placeReside')); $address->ward = isEmptyProperty($medPatientData, ($type==1 ? 'ward' : 'wardReside')); $address->street = isEmptyProperty($medPatientData, ($type==1 ? 'street' : 'streetReside')); $address->wardType = isEmptyProperty($medPatientData, ($type==1 ? 'wardType' : 'wardResideType')); $address->areaType = isEmptyProperty($medPatientData, ($type==1 ? 'areaType' : 'areaResideType')); $address->codeSOATO = isEmptyProperty($medPatientData, ($type==1 ? 'codeSOATO' : 'resideCodeSOATO')); $address->addressPlaceTypeCode = isEmptyProperty($medPatientData, ($type==1 ? 'addressPlaceTypeCode' : 'addressResidePlaceTypeCode')); $address->addressStreetTypeCode = isEmptyProperty($medPatientData, ($type==1 ? 'addressStreetTypeCode' : 'addressResideStreetTypeCode')); return $addressCode; } } } } private function syncMan($medPatientData) { $stmtSelectManPassport = $this->stmtSelectManPassport; $stmtAddMan = $this->stmtAddMan; $stmtAddManPassport = $this->stmtAddManPassport; $stmtUpdateManPassport = $this->stmtUpdateManPassport; if (mb_strcasecmp(isEmptyProperty($medPatientData, 'personalNumber'), '') == 0 ) { $row = false; } else { $stmtSelectManPassport->execute([isEmptyProperty($medPatientData, 'personalNumber')]); $row = $stmtSelectManPassport->fetch(); } if (!$row) { $stmtAddMan->execute([isEmptyProperty($medPatientData, 'surname'), isEmptyProperty($medPatientData, 'name'), isEmptyProperty($medPatientData, 'secondName')]); $manCode = $this->db->lastInsertId(); $stmtAddManPassport->execute([$manCode, isEmptyProperty($medPatientData, 'personalNumber'), isEmptyProperty($medPatientData, 'dateOfBirth'), (int)isEmptyProperty($medPatientData, 'sex'), isEmptyProperty($medPatientData, 'addressCode'), isEmptyProperty($medPatientData, 'addressHouse'), isEmptyProperty($medPatientData, 'addressCorps'), isEmptyProperty($medPatientData, 'addressFlat'), isEmptyProperty($medPatientData, 'zipCode'), isEmptyProperty($medPatientData, 'addressResideCode'), isEmptyProperty($medPatientData, 'addressResideHouse'), isEmptyProperty($medPatientData, 'addressResideCorps'), isEmptyProperty($medPatientData, 'addressResideFlat'), isEmptyProperty($medPatientData, 'zipResideCode')]); return $manCode; } else { if( (mb_strcasecmp($row['dateOfBirth'], isEmptyProperty($medPatientData, 'dateOfBirth')) <> 0) || ((int)$row['sex'] == (int)isEmptyProperty($medPatientData, 'sex')) || ($row['addressCode'] == isEmptyProperty($medPatientData, 'addressCode')) || (mb_strcasecmp($row['addressHouse'], isEmptyProperty($medPatientData, 'addressHouse')) <> 0) || (mb_strcasecmp($row['addressCorps'], isEmptyProperty($medPatientData, 'addressCorps')) <> 0) || (mb_strcasecmp($row['addressFlat'], isEmptyProperty($medPatientData, 'addressFlat')) <> 0) || (mb_strcasecmp($row['zipCode'], isEmptyProperty($medPatientData, 'zipCode')) <> 0) || ($row['addressResideCode'] == isEmptyProperty($medPatientData, 'addressResideCode')) || (mb_strcasecmp($row['addressResideHouse'], isEmptyProperty($medPatientData, 'addressResideHouse')) <> 0) || (mb_strcasecmp($row['addressResideCorps'], isEmptyProperty($medPatientData, 'addressResideCorps')) <> 0) || (mb_strcasecmp($row['addressResideFlat'], isEmptyProperty($medPatientData, 'addressResideFlat')) <> 0) || (mb_strcasecmp($row['zipResideCode'], isEmptyProperty($medPatientData, 'zipResideCode')) <> 0) ) { $stmtUpdateManPassport->execute([isEmptyProperty($medPatientData, 'dateOfBirth'), (int)isEmptyProperty($medPatientData, 'sex'), isEmptyProperty($medPatientData, 'addressCode'), isEmptyProperty($medPatientData, 'addressHouse'), isEmptyProperty($medPatientData, 'addressCorps'), isEmptyProperty($medPatientData, 'addressFlat'), isEmptyProperty($medPatientData, 'zipCode'), isEmptyProperty($medPatientData, 'addressResideCode'), isEmptyProperty($medPatientData, 'addressResideHouse'), isEmptyProperty($medPatientData, 'addressResideCorps'), isEmptyProperty($medPatientData, 'addressResideFlat'), isEmptyProperty($medPatientData, 'zipResideCode'), $row['manPassportCode'] ]); } return $row['manCode']; } } private function synсMedPatient($medPatientData, &$aEnterprise, &$aBranch, &$aDepartment, &$aEnterpriseKind, &$aUniDictProfession) { $stmtSelectMedPatient = $this->stmtSelectMedPatient; $stmtAddMedPatient = $this->stmtAddMedPatient; $stmtUpdateMedPatient = $this->stmtUpdateMedPatient; $stmtSelectMedPatientBusy = $this->stmtSelectMedPatientBusy; $stmtAddMedPatientBusy = $this->stmtAddMedPatientBusy; $stmtUpdateMedPatientBusy = $this->stmtUpdateMedPatientBusy; $stmtDeleteMedPatientBusy = $this->stmtDeleteMedPatientBusy; $enterpriseCode = $this->enterpriseCode; $stmtSelectMedPatient->execute([$enterpriseCode, isEmptyProperty($medPatientData, 'manCode')]); $row = $stmtSelectMedPatient->fetch(); if (!$row) { $stmtAddMedPatient->execute([isEmptyProperty($medPatientData, 'medPatientGroupCode'), isEmptyProperty($medPatientData, 'numberHistorySickness'), isEmptyProperty($medPatientData, 'manCode'), date("Ymd"), "30000101", $enterpriseCode]); $medPatientCode = $this->db->lastInsertId(); } else { $medPatientCode = $row['medPatientCode']; if( ($row['medPatientGroupCode'] == isEmptyProperty($medPatientData, 'medPatientGroupCode')) || (mb_strcasecmp($row['numberHistorySickness'], isEmptyProperty($medPatientData, 'numberHistorySickness')) <> 0) ) { $stmtUpdateMedPatient->execute([isEmptyProperty($medPatientData, 'medPatientGroupCode'), isEmptyProperty($medPatientData, 'numberHistorySickness'), $medPatientCode]); } } $stmtSelectMedPatientBusy->execute([$medPatientCode]); $medPatientBusyData = $stmtSelectMedPatientBusy->fetchAll(); $aBusy = isEmptyProperty($medPatientData, 'medPatientBusy'); if (empty($aBusy)) { $aBusy = []; } $aInsertBusy = []; $aUpdateBusy = []; $aDeleteBusy = []; foreach ($aBusy as $busy) { $busy->enterpriseKindCode = $this->synсEnterpriseKind($busy, $aEnterpriseKind); $busy->uniDictProfessionCode = $this->synсUniDictData($busy, 'uniDictCode', 'uniDictProfessionName', self::professionDictNumber, $aUniDictProfession); $busy->enterpriseCode = $this->synсEnterprise($busy, $aEnterprise); $busy->branchCode = $this->syncBranch($busy, $aBranch); $busy->departmentCode = $this->syncDepartment($busy, $aDepartment); $found = FALSE; $foundUpdate = FALSE; foreach ($medPatientBusyData as $row) { if( ($row['enterpriseKindCode'] == isEmptyProperty($busy, 'enterpriseKindCode')) && ($row['enterpriseCode'] == isEmptyProperty($busy, 'enterpriseCode')) && ($row['departmentCode'] == isEmptyProperty($busy, 'departmentCode')) && (mb_strcasecmp($row['startDate'], isEmptyProperty($busy, 'startDate')) == 0) && (mb_strcasecmp($row['finishDate'], isEmptyProperty($busy, 'finishDate')) == 0) && (mb_strcasecmp($row['classGroup'], isEmptyProperty($busy, 'classGroup')) == 0) && ($row['uniDictProfessionCode'] == isEmptyProperty($busy, 'uniDictProfessionCode')) ) { $found = TRUE; break; } else { if( ($row['enterpriseCode'] == isEmptyProperty($busy, 'enterpriseCode')) && (mb_strcasecmp($row['startDate'], isEmptyProperty($busy, 'startDate')) == 0) ) { $foundUpdate = TRUE; $busy->medPatientBusyCode = $row['medPatientBusyCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertBusy[] = $busy; } else { if ($foundUpdate) { $aUpdateBusy[] = $busy; } } } $found = FALSE; foreach ($medPatientBusyData as $row) { $found = FALSE; foreach ($aBusy as $busy) { if( ($row['enterpriseCode'] == isEmptyProperty($busy, 'enterpriseCode')) && (mb_strcasecmp($row['startDate'], isEmptyProperty($busy, 'startDate')) == 0) ) { $found = TRUE; break; } } if (!$found) { $aDeleteBusy[] = $row; } } foreach ($aInsertBusy as $insertBusy) { $stmtAddMedPatientBusy->execute([$medPatientCode, isEmptyProperty($insertBusy, 'enterpriseCode'), isEmptyProperty($insertBusy, 'enterpriseKindCode'), isEmptyProperty($insertBusy, 'departmentCode'), isEmptyProperty($insertBusy, 'startDate'), isEmptyProperty($insertBusy, 'finishDate'), isEmptyProperty($insertBusy, 'classGroup'), isEmptyProperty($insertBusy, 'uniDictProfessionCode')]); } foreach ($aUpdateBusy as $updateBusy) { $stmtUpdateMedPatientBusy->execute([isEmptyProperty($updateBusy, 'enterpriseKindCode'), isEmptyProperty($updateBusy, 'DepartmentCode'), isEmptyProperty($updateBusy, 'finishDate'), isEmptyProperty($updateBusy, 'classGroup'), isEmptyProperty($updateBusy, 'uniDictProfessionCode'), isEmptyProperty($updateBusy, 'medPatientBusyCode') ]); } foreach ($aDeleteBusy as $deleteBusy) { $stmtDeleteMedPatientBusy->execute([$deleteBusy['medPatientBusyCode']]); } return $medPatientCode; } private function synсEnterpriseKind ($busy ,&$aEnterpriseKind) { if (empty($busy->enterpriseKindName)) { return NULL; } $stmtAddEnterpriseKind = $this->stmtAddEnterpriseKind; $foundCode = 0; foreach($aEnterpriseKind as $kind) { if(mb_strcasecmp(isEmptyProperty($kind,'name'), isEmptyProperty($busy,'enterpriseKindName')) == 0 ) { $foundCode = $kind->enterpriseKindCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddEnterpriseKind->execute([isEmptyProperty($busy,'enterpriseKindName')]); $enterpriseKindCode = $this->db->lastInsertId(); $aEnterpriseKind[] = $kind = new stdClass; $kind->enterpriseKindCode = $enterpriseKindCode; $kind->name = isEmptyProperty($busy,'enterpriseKindName'); return $enterpriseKindCode; } } private function getArrayData($object, $params, $fields) { $stmtSelect = $object; $stmtSelect->execute($params); $aResult = []; while ($row = $stmtSelect->fetch()) { $aResult[] = $record = new stdClass; foreach ($fields as $field) { $record->$field = $row[$field]; } } return $aResult; } private function synсUniDictData($object, $propertyCode, $propertyName, $dictNumber, &$aData) { if (empty($object->$propertyName)) { return NULL; } $stmtAddUniDictData = $this->stmtAddUniDictData; $foundCode = 0; foreach($aData as $data) { if(mb_strcasecmp(isEmptyProperty($data,'name'), isEmptyProperty($object, $propertyName)) == 0 ) { $foundCode = $data->$propertyCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddUniDictData->execute([$dictNumber, isEmptyProperty($object, $propertyName)]); $uniDictCode = $this->db->lastInsertId(); $aData[] = $data = new stdClass; $data->$propertyCode = $uniDictCode; $data->name = isEmptyProperty($object, $propertyName); return $uniDictCode; } } private function synсEnterprise ($busy ,&$aEnterprise) { if (empty($busy->enterpriseName)) { return NULL; } $stmtAddEnterprise = $this->stmtAddEnterprise; $foundCode = 0; foreach($aEnterprise as $enterprise) { if(mb_strcasecmp(isEmptyProperty($enterprise,'name'), isEmptyProperty($busy,'enterpriseName')) == 0 ) { $foundCode = $enterprise->enterpriseCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddEnterprise->execute([isEmptyProperty($busy,'enterpriseName'), isEmptyProperty($busy,'enterpriseName'), isEmptyProperty($busy,'startDate')]); $enterpriseCode = $this->db->lastInsertId(); $aEnterprise[] = $record = new stdClass; $record->enterpriseCode = $enterpriseCode; $record->name = isEmptyProperty($busy, 'enterpriseName'); return $enterpriseCode; } } private function syncBranch ($busy, &$aBranch) { if (empty($busy->branchName)) { return NULL; } $stmtAddBranch = $this->stmtAddBranch; $foundCode = 0; foreach($aBranch as $branch) { if( (mb_strcasecmp(isEmptyProperty($branch,'name'), isEmptyProperty($busy,'branchName')) == 0 ) && (isEmptyProperty($branch,'enterpriseCode') == isEmptyProperty($busy,'enterpriseCode')) ) { $foundCode = $branch->branchCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddBranch->execute([isEmptyProperty($busy,'branchName'), isEmptyProperty($busy,'branchName'), isEmptyProperty($busy, 'enterpriseCode')]); $branchCode = $this->db->lastInsertId(); $aBranch[] = $branch = new stdClass; $branch->branchCode = $branchCode; $branch->enterpriseCode = isEmptyProperty($busy, 'enterpriseCode'); $branch->name = isEmptyProperty($busy, 'branchName'); return $branchCode; } } private function syncDepartment ($busy, &$aDepartment) { if (empty($busy->departmentName)) { return NULL; } $stmtAddDepartment = $this->stmtAddDepartment; $foundCode = 0; foreach($aDepartment as $department) { if( (mb_strcasecmp(isEmptyProperty($department,'name'), isEmptyProperty($busy,'departmentName')) == 0 ) && (isEmptyProperty($department,'branchCode') == isEmptyProperty($busy,'branchCode')) ) { $foundCode = $department->departmentCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddDepartment->execute([isEmptyProperty($busy,'departmentName'), isEmptyProperty($busy,'departmentName'), isEmptyProperty($busy, 'branchCode')]); $departmentCode = $this->db->lastInsertId(); $aDepartment[] = $department = new stdClass; $department->departmentCode = $departmentCode; $department->name = isEmptyProperty($busy, 'departmentName'); return $departmentCode; } } private function syncMedPreventType($inspection, &$aMedPreventType) { $enterpriseCode = $this->enterpriseCode; $stmtAddMedPreventType = $this->stmtAddMedPreventType; $foundCode = 0; foreach($aMedPreventType as $medPreventType) { if(mb_strcasecmp(isEmptyProperty($medPreventType, 'name'), isEmptyProperty($inspection, 'medPreventTypeName')) == 0 ) { $foundCode = $medPreventType->medPreventTypeCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddMedPreventType->execute([$enterpriseCode, isEmptyProperty($inspection, 'medPreventTypeName'), isEmptyProperty($inspection, 'medPreventTypeFormFields') ]); $medPreventTypeCode = $this->db->lastInsertId(); $aMedPreventType[] = $preventType = new stdClass; $preventType->medPreventTypeCode = $medPreventTypeCode; $preventType->name = isEmptyProperty($inspection, 'medPreventTypeName'); return $medPreventTypeCode; } } private function syncMedPrevent($inspection, &$aMedPrevent) { $stmtAddMedPrevent = $this->stmtAddMedPrevent; $foundCode = 0; foreach($aMedPrevent as $medPrevent) { if(mb_strcasecmp(isEmptyProperty($medPrevent, 'name'), isEmptyProperty($inspection, 'medPreventName')) == 0 ) { $foundCode = $medPrevent->medPreventCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $position = 0; foreach($aMedPrevent as $medPrevent) { if( (int)isEmptyProperty($medPrevent, 'position')> $position ) { $position = $medPrevent->position; } } $position++; $stmtAddMedPrevent->execute([isEmptyProperty($inspection, 'medPreventTypeCode'), isEmptyProperty($inspection, 'medPreventName'), isEmptyProperty($inspection, 'medPreventPeriodMonth'), (int)isEmptyProperty($inspection, 'medPreventSex'), isEmptyProperty($inspection, 'medPreventAgeFrom'), isEmptyProperty($inspection, 'medPreventAgeTo'), $position]); $medPreventCode = $this->db->lastInsertId(); $aMedPrevent[] = $medPrevent = new stdClass; $medPrevent->medPreventCode = $medPreventCode; $medPrevent->medPreventTypeCode = isEmptyProperty($inspection, 'medPreventTypeCode');; $medPrevent->name = isEmptyProperty($inspection, 'medPreventName'); $medPrevent->position = $position; return $medPreventCode; } } private function syncMedInspect($inspection, &$aMedProfHazard, &$aMedProfession, &$aUniDictResearch, &$aMedAction, &$aMedConclusion, &$aMedConclusionGroup) { if (empty($inspection->guid) || empty($inspection->medPatientData)) { return NULL; } $enterpriseCode = $this->enterpriseCode; $stmtSelectMedInspect = $this->stmtSelectMedInspect; $stmtAddMedInspect = $this->stmtAddMedInspect; $stmtUpdateMedInspect = $this->stmtUpdateMedInspect; $stmtSelectMedInspect->execute([$enterpriseCode, isEmptyProperty($inspection, 'guid')]); $row = $stmtSelectMedInspect->fetch(); if (!$row) { $stmtAddMedInspect->execute([$enterpriseCode, isEmptyProperty($inspection, 'medPreventCode'), isEmptyProperty($inspection->medPatientData, 'medPatientCode'), isEmptyProperty($inspection, 'planDate'), isEmptyProperty($inspection, 'diagnosisNote'), isEmptyProperty($inspection, 'documentList'), isEmptyProperty($inspection, 'information'), isEmptyProperty($inspection, 'inspectKind'), isEmptyProperty($inspection, 'inspectDate'), isEmptyProperty($inspection, 'documentNumber'), isEmptyProperty($inspection, 'actualDate'), isEmptyProperty($inspection, 'uniDictDrComissionCode'), isEmptyProperty($inspection, 'uniDictDrComissionDirectCode'), isEmptyProperty($inspection, 'uniDictProfessionRWCode'), isEmptyProperty($inspection, 'uniDictWorkConditionCode'), isEmptyProperty($inspection, 'guid')]); $medInspectCode = $this->db->lastInsertId(); } else { if ($row['checked'] == 1) { return 1; } else { $medInspectCode = $row['medInspectCode']; if( ($row['medPreventCode'] == isEmptyProperty($inspection, 'medPreventCode')) || ($row['medPatientCode'] == isEmptyProperty($inspection->medPatientData, 'medPatientCode')) || (mb_strcasecmp($row['planDate'], isEmptyProperty($inspection, 'planDate')) <> 0) || (mb_strcasecmp($row['diagnosisNote'], isEmptyProperty($inspection, 'diagnosisNote')) <> 0) || (mb_strcasecmp($row['documentList'], isEmptyProperty($inspection, 'documentList')) <> 0) || (mb_strcasecmp($row['information'], isEmptyProperty($inspection, 'information')) <> 0) || ($row['inspectKind'] == isEmptyProperty($inspection, 'inspectKind')) || (mb_strcasecmp($row['inspectDate'], isEmptyProperty($inspection, 'inspectDate')) <> 0) || (mb_strcasecmp($row['documentNumber'], isEmptyProperty($inspection, 'documentNumber')) <> 0) || (mb_strcasecmp($row['actualDate'], isEmptyProperty($inspection, 'actualDate')) <> 0) || ($row['uniDictDrComissionCode'] == isEmptyProperty($inspection, 'uniDictDrComissionCode')) || ($row['uniDictDrComissionDirectCode'] == isEmptyProperty($inspection, 'uniDictDrComissionDirectCode')) || ($row['uniDictProfessionRWCode'] == isEmptyProperty($inspection, 'uniDictProfessionRWCode')) || ($row['uniDictWorkConditionCode'] == isEmptyProperty($inspection, 'uniDictWorkConditionCode')) ) { $stmtUpdateMedInspect->execute([isEmptyProperty($inspection, 'medPreventCode'), isEmptyProperty($inspection->medPatientData, 'medPatientCode'), isEmptyProperty($inspection, 'planDate'), isEmptyProperty($inspection, 'diagnosisNote'), isEmptyProperty($inspection, 'documentList'), isEmptyProperty($inspection, 'information'), isEmptyProperty($inspection, 'inspectKind'), isEmptyProperty($inspection, 'inspectDate'), isEmptyProperty($inspection, 'documentNumber'), isEmptyProperty($inspection, 'actualDate'), isEmptyProperty($inspection, 'uniDictDrComissionCode'), isEmptyProperty($inspection, 'uniDictDrComissionDirectCode'), isEmptyProperty($inspection, 'uniDictProfessionRWCode'), isEmptyProperty($inspection, 'uniDictWorkConditionCode'), $row['medInspectCode'] ]); } } } $inspection->medInspectCode = $medInspectCode; $this->syncHazard($inspection, $aMedProfHazard); $this->syncResult($inspection, $aMedProfession); $this->syncResearch($inspection, $aUniDictResearch); $this->syncAction($inspection, $aMedAction); $this->syncConclusion($inspection, $aMedConclusion, $aMedConclusionGroup); return 0; } private function syncHazard($inspection, &$aMedProfHazard) { $stmtSelectMedInspectHazard = $this->stmtSelectMedInspectHazard; $stmtAddMedInspectHazard = $this ->stmtAddMedInspectHazard; $stmtUpdateMedInspectHazard = $this ->stmtUpdateMedInspectHazard; $stmtDeleteMedInspectHazard = $this ->stmtDeleteMedInspectHazard; $stmtSelectMedInspectHazard->execute([$inspection->medInspectCode]); $medInspectHazardData = $stmtSelectMedInspectHazard->fetchAll(); $aHazard = isEmptyProperty($inspection, 'medInspectHazard'); if (empty($aHazard)) { $aHazard = []; } $aInsertHazard = []; $aUpdateHazard = []; $aDeleteHazard = []; foreach ($aHazard as $hazard) { $hazard->medProfHazardCode = $this->synсMedProfHazard($hazard, $aMedProfHazard); if (empty($hazard->medProfHazardCode)) { continue; } $found = FALSE; $foundUpdate = FALSE; foreach ($medInspectHazardData as $row) { if( ($row['medProfHazardCode'] == isEmptyProperty($hazard, 'medProfHazardCode')) && ($row['stageMonth'] == isEmptyProperty($hazard, 'stageMonth')) && ($row['stageYear'] == isEmptyProperty($hazard, 'stageYear')) ) { $found = TRUE; break; } else { if( $row['medProfHazardCode'] == isEmptyProperty($hazard, 'medProfHazardCode')) { $foundUpdate = TRUE; $hazard->medInspectHazardCode = $row['medInspectHazardCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertHazard[] = $hazard; } else { if ($foundUpdate) { $aUpdateHazard[] = $hazard; } } } $found = FALSE; foreach ($medInspectHazardData as $row) { $found = FALSE; foreach ($aHazard as $hazard) { if( $row['medProfHazardCode'] == isEmptyProperty($hazard, 'medProfHazardCode')) { $found = TRUE; break; } } if (!$found) { $aDeleteHazard[] = $row; } } foreach ($aInsertHazard as $insertHazard) { $stmtAddMedInspectHazard->execute([$inspection->medInspectCode, isEmptyProperty($insertHazard, 'medProfHazardCode'), isEmptyProperty($insertHazard, 'stageMonth'), isEmptyProperty($insertHazard, 'stageYear')]); } foreach ($aUpdateHazard as $updateHazard) { $stmtUpdateMedInspectHazard->execute([isEmptyProperty($updateHazard, 'stageMonth'), isEmptyProperty($updateHazard, 'stageYear'), isEmptyProperty($updateHazard, 'medInspectHazardCode')]); } foreach ($aDeleteHazard as $deleteHazard) { $stmtDeleteMedInspectHazard->execute([$deleteHazard['medInspectHazardCode']]); } } private function synсMedProfHazard($hazard, $aMedProfHazard) { $foundCode = 0; foreach($aMedProfHazard as $medProfHazard) { if( (strcasecmp(isEmptyProperty($medProfHazard, 'cipher'), isEmptyProperty($hazard, 'cipher')) == 0) && (strcasecmp(isEmptyProperty($medProfHazard, 'inspectType'), isEmptyProperty($hazard, 'inspectType')) == 0) ) { $foundCode = $medProfHazard->medProfHazardCode; break; } } if ($foundCode != 0) { return $foundCode; } else { return null; } } private function syncResult($inspection, &$aMedProfession) { $stmtSelectMedInspectResult = $this->stmtSelectMedInspectResult; $stmtAddMedInspectResult = $this ->stmtAddMedInspectResult; $stmtUpdateMedInspectResult = $this ->stmtUpdateMedInspectResult; $stmtDeleteMedInspectResult = $this ->stmtDeleteMedInspectResult; $stmtSelectMedInspectResult->execute([$inspection->medInspectCode]); $medInspectResultData = $stmtSelectMedInspectResult->fetchAll(); $aResult = isEmptyProperty($inspection, 'medInspectResult'); if (empty($aResult)) { $aResult = []; } $aInsertResult = []; $aUpdateResult = []; $aDeleteResult = []; foreach ($aResult as $result) { $result->medProfessionCode = $this->synсMedProfession($result, $aMedProfession); $result->medDiagnosisCode = $this->synсMedDiagnosis(isEmptyProperty($result,'medDiagnosisCipher')); $found = FALSE; $foundUpdate = FALSE; foreach ($medInspectResultData as $row) { if( ($row['medProfessionCode'] == isEmptyProperty($result, 'medProfessionCode')) && ($row['medDiagnosisCode'] == isEmptyProperty($result, 'medDiagnosisCode')) && (mb_strcasecmp($row['inspectDate'], isEmptyProperty($result, 'inspectDate')) == 0) && (mb_strcasecmp($row['note'], isEmptyProperty($result, 'note')) == 0) && ($row['firstTime'] == isEmptyProperty($result, 'firstTime')) && ($row['profDisease'] == isEmptyProperty($result, 'profDisease')) && ($row['contraindication'] == isEmptyProperty($result, 'contraindication')) && ($row['primaryDiagnosis'] == isEmptyProperty($result, 'primaryDiagnosis')) ) { $found = TRUE; break; } else { if( ($row['medProfessionCode'] == isEmptyProperty($result, 'medProfessionCode')) && (mb_strcasecmp($row['inspectDate'], isEmptyProperty($result, 'inspectDate')) == 0) ) { $foundUpdate = TRUE; $result->medInspectResultCode = $row['medInspectResultCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertResult[] = $result; } else { if ($foundUpdate) { $aUpdateResult[] = $result; } } } $found = FALSE; foreach ($medInspectResultData as $row) { $found = FALSE; foreach ($aResult as $result) { if( (strcasecmp($row['medProfessionCode'], isEmptyProperty($result, 'medProfessionCode')) == 0) && (strcasecmp($row['inspectDate'], isEmptyProperty($result, 'inspectDate')) == 0) ) { $found = TRUE; break; } } if (!$found) { $aDeleteResult[] = $row; } } foreach ($aInsertResult as $insertResult) { $stmtAddMedInspectResult->execute([ $inspection->medInspectCode, isEmptyProperty($insertResult, 'inspectDate'), isEmptyProperty($insertResult, 'medProfessionCode'), isEmptyProperty($insertResult, 'medDiagnosisCode'), (int)isEmptyProperty($insertResult, 'manual'), isEmptyProperty($insertResult, 'note'), isEmptyProperty($insertResult, 'firstTime'), isEmptyProperty($insertResult, 'profDisease'), isEmptyProperty($insertResult, 'contraindication'), isEmptyProperty($insertResult, 'primaryDiagnosis') ]); } foreach ($aUpdateResult as $updateResult) { $stmtUpdateMedInspectResult->execute([ isEmptyProperty($updateResult, 'inspectDate'), isEmptyProperty($updateResult, 'medProfessionCode'), isEmptyProperty($updateResult, 'medDiagnosisCode'), (int)isEmptyProperty($updateResult, 'manual'), isEmptyProperty($updateResult, 'note'), isEmptyProperty($updateResult, 'firstTime'), isEmptyProperty($updateResult, 'profDisease'), isEmptyProperty($updateResult, 'contraindication'), isEmptyProperty($updateResult, 'primaryDiagnosis'), $inspection->medInspectCode ]); } foreach ($aDeleteResult as $deleteResult) { $stmtDeleteMedInspectResult->execute([$deleteResult['medInspectResultCode']]); } } private function synсMedProfession ($result, &$aMedProfession) { if (empty($result->medProfessionName)) { return NULL; } $stmtAddMedProfession = $this->stmtAddMedProfession; $foundCode = 0; foreach($aMedProfession as $medProfession) { if( (mb_strcasecmp(isEmptyProperty($medProfession,'name'), isEmptyProperty($result,'medProfessionName')) == 0 ) ) { $foundCode = $medProfession->medProfessionCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddMedProfession->execute([isEmptyProperty($result,'medProfessionCipher'), isEmptyProperty($result,'medProfessionName')]); $medProfessionCode = $this->db->lastInsertId(); $aMedProfession[] = $medProfession = new stdClass; $medProfession->medProfessionCode = $medProfessionCode; $medProfession->name = isEmptyProperty($result, 'medProfessionName'); return $medProfessionCode; } } private function synсMedDiagnosis($medDiagnosisCipher) { $stmtSelectMedDiagnosis = $this->stmtSelectMedDiagnosis; $stmtSelectMedDiagnosis->execute([$medDiagnosisCipher]); $row = $stmtSelectMedDiagnosis->fetch(); if ($row) { return $row['medDiagnosisCode']; } else { return null; } } private function syncResearch($inspection, &$aUniDictResearch) { $stmtSelectMedInspectResearch = $this->stmtSelectMedInspectResearch; $stmtAddMedInspectResearch = $this ->stmtAddMedInspectResearch; $stmtUpdateMedInspectResearch = $this ->stmtUpdateMedInspectResearch; $stmtDeleteMedInspectResearch = $this ->stmtDeleteMedInspectResearch; $stmtSelectMedInspectResearch->execute([$inspection->medInspectCode]); $medInspectResearchData = $stmtSelectMedInspectResearch->fetchAll(); $aResearch = isEmptyProperty($inspection, 'medInspectResearch'); if (empty($aResearch)) { $aResearch = []; } $aInsertResearch = []; $aUpdateResearch = []; $aDeleteResearch = []; foreach ($aResearch as $research) { $research->uniDictResearchCode = $this->synсUniDictData($research, 'uniDictCode', 'researchName', self::researchDictNumber, $aUniDictResearch); $research->medDiagnosisCode = $this->synсMedDiagnosis(isEmptyProperty($research,'diagnosisCipher')); $found = FALSE; $foundUpdate = FALSE; foreach ($medInspectResearchData as $row) { if( (mb_strcasecmp($row['researchDate'], isEmptyProperty($research, 'researchDate')) == 0) && ($row['uniDictResearchCode'] == isEmptyProperty($research, 'uniDictResearchCode')) && ($row['medDiagnosisCode'] == isEmptyProperty($research, 'medDiagnosisCode')) && (mb_strcasecmp($row['conclusion'], isEmptyProperty($research, 'conclusion')) == 0) && ($row['addExamination'] == isEmptyProperty($research, 'addExamination')) ) { $found = TRUE; break; } else { if( ($row['uniDictResearchCode'] == isEmptyProperty($research, 'uniDictResearchCode')) && (mb_strcasecmp($row['researchDate'], isEmptyProperty($research, 'researchDate')) == 0) ) { $foundUpdate = TRUE; $research->medInspectResearchCode = $row['medInspectResearchCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertResearch[] = $research; } else { if ($foundUpdate) { $aUpdateResearch[] = $research; } } } $found = FALSE; foreach ($medInspectResearchData as $row) { $found = FALSE; foreach ($aResearch as $research) { if( ($row['uniDictResearchCode'] == isEmptyProperty($research, 'uniDictResearchCode')) && (mb_strcasecmp($row['researchDate'], isEmptyProperty($research, 'researchDate')) == 0) ) { $found = TRUE; break; } } if (!$found) { $aDeleteResearch[] = $row; } } foreach ($aInsertResearch as $insertResearch) { $stmtAddMedInspectResearch->execute([ $inspection->medInspectCode, isEmptyProperty($insertResearch, 'researchDate'), isEmptyProperty($insertResearch, 'uniDictResearchCode'), isEmptyProperty($insertResearch, 'medDiagnosisCode'), isEmptyProperty($insertResearch, 'conclusion'), isEmptyProperty($insertResearch, 'addExamination') ]); } foreach ($aUpdateResearch as $updateResearch) { $stmtUpdateMedInspectResearch->execute([ isEmptyProperty($updateResearch, 'researchDate'), isEmptyProperty($updateResearch, 'uniDictResearchCode'), isEmptyProperty($updateResearch, 'medDiagnosisCode'), isEmptyProperty($updateResearch, 'conclusion'), isEmptyProperty($updateResearch, 'addExamination'), isEmptyProperty($updateResearch, 'medInspectResearchCode')]); } foreach ($aDeleteResearch as $deleteResearch) { $stmtDeleteMedInspectResearch->execute([$deleteResearch['medInspectResearchCode']]); } } private function syncAction($inspection, &$aMedAction) { $stmtSelectMedInspectAction = $this->stmtSelectMedInspectAction; $stmtAddMedInspectAction = $this ->stmtAddMedInspectAction; $stmtUpdateMedInspectAction = $this ->stmtUpdateMedInspectAction; $stmtDeleteMedInspectAction = $this ->stmtDeleteMedInspectAction; $stmtSelectMedInspectAction->execute([$inspection->medInspectCode]); $medInspectActionData = $stmtSelectMedInspectAction->fetchAll(); $aAction = isEmptyProperty($inspection, 'medInspectAction'); if (empty($aAction)) { $aAction = []; } $aInsertAction = []; $aUpdateAction = []; $aDeleteAction = []; foreach ($aAction as $action) { $action->medActionCode = $this->synсMedAction($action, $aMedAction); $action->medDiagnosisCode = $this->synсMedDiagnosis(isEmptyProperty($action,'diagnosisCipher')); $found = FALSE; $foundUpdate = FALSE; foreach ($medInspectActionData as $row) { if( ($row['medActionCode'] == isEmptyProperty($action, 'medActionCode')) && ($row['medDiagnosisCode'] == isEmptyProperty($action, 'medDiagnosisCode')) && ($row['executed'] == isEmptyProperty($action, 'executed')) && ($row['recommend'] == isEmptyProperty($action, 'recommend')) && ($row['recommendBefore'] == isEmptyProperty($action, 'recommendBefore')) ) { $found = TRUE; break; } else { if( ($row['medActionCode'] == isEmptyProperty($action, 'medActionCode')) ) { $foundUpdate = TRUE; $action->medInspectActionCode = $row['medInspectActionCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertAction[] = $action; } else { if ($foundUpdate) { $aUpdateAction[] = $action; } } } $found = FALSE; foreach ($medInspectActionData as $row) { $found = FALSE; foreach ($aAction as $action) { if( ($row['medActionCode'] == isEmptyProperty($action, 'medActionCode')) ) { $found = TRUE; break; } } if (!$found) { $aDeleteAction[] = $row; } } foreach ($aInsertAction as $insertAction) { $stmtAddMedInspectAction->execute([ $inspection->medInspectCode, isEmptyProperty($insertAction, 'medActionCode'), isEmptyProperty($insertAction, 'medDiagnosisCode'), isEmptyProperty($insertAction, 'executed'), isEmptyProperty($insertAction, 'recommend'), isEmptyProperty($insertAction, 'recommendBefore') ]); } foreach ($aUpdateAction as $updateAction) { $stmtUpdateMedInspectAction->execute([ isEmptyProperty($updateAction, 'medActionCode'), isEmptyProperty($updateAction, 'medDiagnosisCode'), isEmptyProperty($updateAction, 'executed'), isEmptyProperty($updateAction, 'recommend'), isEmptyProperty($updateAction, 'recommendBefore'), isEmptyProperty($updateAction, 'medInspectActionCode')]); } foreach ($aDeleteAction as $deleteAction) { $stmtDeleteMedInspectAction->execute([$deleteAction['medInspectActionCode']]); } } private function synсMedAction($action, &$aMedAction) { if (empty($action->actionName)) { return NULL; } $stmtAddMedAction = $this->stmtAddMedAction; $foundCode = 0; foreach($aMedAction as $medAction) { if(mb_strcasecmp(isEmptyProperty($medAction,'name'), isEmptyProperty($action,'actionName')) == 0 ) { $foundCode = $medAction->medActionCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddMedAction->execute([isEmptyProperty($action,'actionName'),isEmptyProperty($action,'actionNote')]); $medActionCode = $this->db->lastInsertId(); $aMedAction[] = $record = new stdClass; $record->medActionCode = $medActionCode; $record->name = isEmptyProperty($action,'actionName'); return $medActionCode; } } private function syncConclusion($inspection, &$aMedConclusion, &$aMedConclusionGroup) { $stmtSelectMedInspectConclusion = $this->stmtSelectMedInspectConclusion; $stmtAddMedInspectConclusion = $this ->stmtAddMedInspectConclusion; $stmtUpdateMedInspectConclusion = $this ->stmtUpdateMedInspectConclusion; $stmtDeleteMedInspectConclusion = $this ->stmtDeleteMedInspectConclusion; $stmtSelectMedInspectConclusion->execute([$inspection->medInspectCode]); $medInspectConclusionData = $stmtSelectMedInspectConclusion->fetchAll(); $aConclusion = isEmptyProperty($inspection, 'medInspectConclusion'); if (empty($aConclusion)) { $aConclusion = []; } $aInsertConclusion = []; $aUpdateConclusion = []; $aDeleteConclusion = []; foreach ($aConclusion as $conclusion) { $conclusion->medConclusionCode = $this->synсMedConclusion($conclusion, $aMedConclusion); $conclusion->medConclusionGroupCode = $this->synсMedConclusionGroup($conclusion, $aMedConclusionGroup); $found = FALSE; $foundUpdate = FALSE; foreach ($medInspectConclusionData as $row) { if( ($row['medConclusionCode'] == isEmptyProperty($conclusion, 'medConclusionCode')) && ($row['medConclusionGroupCode'] == isEmptyProperty($conclusion, 'medConclusionGroupCode')) && (mb_strcasecmp($row['note'], isEmptyProperty($conclusion, 'conclusionNote')) == 0) ) { $found = TRUE; break; } else { if( ($row['medConclusionCode'] == isEmptyProperty($conclusion, 'medConclusionCode')) ) { $foundUpdate = TRUE; $conclusion->medInspectConclusionCode = $row['medInspectConclusionCode']; break; } } } if (!$found && !$foundUpdate) { $aInsertConclusion[] = $conclusion; } else { if ($foundUpdate) { $aUpdateConclusion[] = $conclusion; } } } $found = FALSE; foreach ($medInspectConclusionData as $row) { $found = FALSE; foreach ($aConclusion as $conclusion) { if( ($row['medConclusionCode'] == isEmptyProperty($conclusion, 'medConclusionCode')) ) { $found = TRUE; break; } } if (!$found) { $aDeleteConclusion[] = $row; } } foreach ($aInsertConclusion as $insertConclusion) { $stmtAddMedInspectConclusion->execute([ $inspection->medInspectCode, isEmptyProperty($insertConclusion, 'medConclusionCode'), isEmptyProperty($insertConclusion, 'medConclusionGroupCode'), isEmptyProperty($insertConclusion, 'conclusionNote') ]); } foreach ($aUpdateConclusion as $updateConclusion) { $stmtUpdateMedInspectConclusion->execute([ isEmptyProperty($updateConclusion, 'medConclusionCode'), isEmptyProperty($updateConclusion, 'medConclusionGroupCode'), isEmptyProperty($updateConclusion, 'conclusionNote'), isEmptyProperty($updateConclusion, 'medInspectConclusionCode')]); } foreach ($aDeleteConclusion as $deleteConclusion) { $stmtDeleteMedInspectConclusion->execute([$deleteConclusion['medInspectConclusionCode']]); } } private function synсMedConclusion($conclusion, &$aMedConclusion) { if (empty($conclusion->conclusionName)) { return NULL; } $stmtAddMedConclusion = $this->stmtAddMedConclusion; $foundCode = 0; foreach($aMedConclusion as $medConclusion) { if(mb_strcasecmp(isEmptyProperty($medConclusion,'name'), isEmptyProperty($conclusion,'conclusionName')) == 0 ) { $foundCode = $medConclusion->medConclusionCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $stmtAddMedConclusion->execute([isEmptyProperty($conclusion,'conclusionName')]); $medConclusionCode = $this->db->lastInsertId(); $aMedConclusion[] = $record = new stdClass; $record->medConclusionCode = $medConclusionCode; $record->name = isEmptyProperty($conclusion,'conclusionName'); return $medConclusionCode; } } private function synсMedConclusionGroup($conclusion, &$aMedConclusionGroup) { if (empty($conclusion->conclusionGroupName)) { return NULL; } $stmtAddMedConclusionGroup = $this->stmtAddMedConclusionGroup; $foundCode = 0; foreach($aMedConclusionGroup as $medConclusionGroup) { if( (isEmptyProperty($medConclusionGroup,'medConclusionCode') == isEmptyProperty($conclusion,'medConclusionCode')) && (mb_strcasecmp(isEmptyProperty($medConclusionGroup,'name'), isEmptyProperty($conclusion,'conclusionGroupName')) == 0 ) ) { $foundCode = $medConclusionGroup->medConclusionGroupCode; break; } } if ($foundCode != 0) { return $foundCode; } else { $position = 0; foreach($aMedConclusionGroup as $medConclusionGroup) { if( (isEmptyProperty($medConclusionGroup, 'medConclusionCode') == isEmptyProperty($conclusion,'medConclusionCode')) && ((int)isEmptyProperty($medConclusionGroup, 'position') > $position) ) { $position = $medConclusionGroup->position; } } $position++; $stmtAddMedConclusionGroup->execute([isEmptyProperty($conclusion,'medConclusionCode'), isEmptyProperty($conclusion,'conclusionGroupName'), $position]); $medConclusionGroupCode = $this->db->lastInsertId(); $aMedConclusionGroup[] = $record = new stdClass; $record->medConclusionGroupCode = $medConclusionGroupCode; $record->medConclusionCode = isEmptyProperty($conclusion,'medConclusionCode'); $record->name = isEmptyProperty($conclusion,'conclusionGroupName'); $record->position = $position; return $medConclusionGroupCode; } } } $service = new Junior\Server(new map_med_inspectRW()); $service->process();