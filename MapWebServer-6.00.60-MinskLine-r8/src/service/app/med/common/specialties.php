<?php
 require_once(__DIR__ . '/../../init.php'); class med_common_specialties { private $db; public function readSpecialties($params = null) { $db = $this->db ?: $this->db = connectDb(); $flagSelfRecording = isEmptyProperty($params, 'flagSelfRecording', 0); $doctorId = isEmptyProperty($params, 'doctorId'); $professionId = isEmptyProperty($params, 'professionId'); $departmentId = isEmptyProperty($params, 'departmentId'); $couponJobId = isEmptyProperty($params, 'couponJobId'); $search = isEmptyProperty($params, "search", ""); $professionIdAddInList = isEmptyProperty($params, "professionIdAddInList", 0); $specialityId = isEmptyProperty($params, "specialityId", 0); $serviceTypeId = isEmptyProperty($params, "serviceTypeId", 0); if ($search) { $search = "(mp.Name ilike '%$search%')"; $search = ($flagSelfRecording ? " and" : "where").$search; } $sqlFilterWhere = ''; if ($couponJobId) { $sqlFilterWhere .= "AND MCQ.MedCouponWorkCode = $couponJobId"; } $sqlWhere = ''; if ($flagSelfRecording) { $sqlWhere .= "AND (SELECT MAX(flagselfrecording) FROM MedCouponControl WHERE MedProfessionCode = MP.MedProfessionCode) = $flagSelfRecording"; } if ($doctorId && $professionId && $departmentId) { $sqlWhere .= <<<SQL
                AND MP.MedProfessionCode IN (
                    SELECT DISTINCT(MCQ.MedProfessionCode)
                    FROM MedCouponQuotaPercent MCQP
                    LEFT JOIN MedCouponQuota MCQ ON MCQ.MedCouponQuotaCode = MCQP.MedCouponQuotaCode
                    WHERE 1 = 1
                        $sqlFilterWhere
                        AND (
                            MCQP.MedDoctorCode = $doctorId
                            OR (MCQP.MedDoctorCode IS NULL 
                                AND MCQP.MedProfessionCode = $professionId 
                                AND (MCQP.DepartmentCode = $departmentId OR MCQP.DepartmentCode IS NULL))
                            OR (MCQP.MedDoctorCode IS NULL 
                                AND MCQP.MedProfessionCode IS NULL 
                                AND MCQP.DepartmentCode = $departmentId)
                        )
                )
                SQL;
} if ($specialityId) { $sqlWhere .= " AND MP.MedProfessionCode IN (SELECT CRV.SourceCode FROM CiszResourceValue CRV WHERE CRV.CiszResourceValueCode = $specialityId) "; } if ($serviceTypeId) { $sqlWhere .= " AND MP.MedProfessionCode IN (SELECT CRV.SourceCode FROM CiszResourceValue CRV WHERE CRV.CiszResourceValueCode = $serviceTypeId) "; } $search .= " OR MP.MedProfessionCode = $professionIdAddInList"; $sql = <<<SQL
SELECT
    MP.MedProfessionCode    "id",
    MP.Name                 "name",
    MP.TalonByCode          "uniId",
    MP.Cipher               "cipher",
    MP.HourOnRate           "hourOnRate",
    MP.DisplayInInfobox     "displayInInfobox",
    UL.Name                 "uniName",
    UL.Code                 "uniSpecialty"
FROM MedProfession MP
LEFT JOIN UniImportLink UL ON UL.UniImportLinkCode = MP.TalonByCode
WHERE 1 = 1
$sqlWhere
$search
ORDER BY 2
SQL;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function readSpecialtiesRate($params = null) { $db = $this->db ?: $this->db = connectDb(); $profId = isEmptyProperty($params, 'profId', 0); $sqlСontrol = <<<"EOT"
SELECT
    MC.MedCouponControlCode "couponControlId",
    MC.OrderPercent "orderPercent",
    MW.Name "jobType",
    MC.FlagSelfRecording "flagSelfRecording",
    MC.WithoutControl "withoutControl",
    MC.UponDirection "uponDirection",
    MC.SelfRecordingPercent "selfRecordingPercent",
    MC.SelfRecordingPercentMin "selfRecordingPercentMin",
    MW.MedCouponWorkCode "jobId",
    CQ.MedCouponQuotaCode "couponQuotaId",
    MC.MedProfessionCode "professionId"
FROM MedCouponControl MC
JOIN MedCouponWork MW on MW.MedCouponWorkCode = MC.MedCouponWorkCode
LEFT JOIN MedCouponQuota CQ on CQ.MedCouponWorkCode = MW.MedCouponWorkCode AND  CQ.MedProfessionCode = MC.MedProfessionCode
WHERE MC.MedProfessionCode = $profId
ORDER BY 3
EOT;
$sqlWorkProf = <<<"EOT"
SELECT
    MW.Name "jobType",
    MWF.Coefficient "coefficient",
    MWF.VisitNumberOnHour "visitNumberOnHour",
    MWF.MedCouponWorkProfCode "workProfId",
    MW.MedCouponWorkCode "jobId",
    CQ.MedCouponQuotaCode "couponQuotaId",
    MWF.MedProfessionCode "professionId",
    60/MWF.VisitNumberOnHour "visitTime"
FROM MedCouponWorkProf MWF
JOIN MedCouponWork MW on MW.MedCouponWorkCode = MWF.MedCouponWorkCode
LEFT JOIN MedCouponQuota CQ on CQ.MedCouponWorkCode = MW.MedCouponWorkCode AND  CQ.MedProfessionCode = MWF.MedProfessionCode
WHERE MWF.MedProfessionCode = $profId
ORDER BY 1
EOT;
$stmtControl = $db->prepare($sqlСontrol); $stmtWorkProf = $db->prepare($sqlWorkProf); $stmtControl->execute(); $stmtWorkProf->execute(); $control = $stmtControl->fetchAll(); $workProf = $stmtWorkProf->fetchAll(); $resultMass = []; $keyMass = []; foreach ($control as $controlItem) { $keyMass[] = $controlItem['jobId']; } foreach ($workProf as $workProfItem) { $keyMass[] = $workProfItem['jobId']; } $keyMass = array_unique($keyMass); $len = 0; foreach ($keyMass as $i => $keyItem) { $resultMass[$len] = []; foreach ($control as $controlItem) { if ($controlItem['jobId'] === $keyItem) { $item = [ "jobId" => $controlItem['jobId'], "jobType" => $controlItem['jobType'], "professionId" => $controlItem['professionId'], "couponControlId" => $controlItem['couponControlId'], "selfRecordingPercent" => $controlItem['selfRecordingPercent'], "selfRecordingPercentMin" => $controlItem['selfRecordingPercentMin'], "uponDirection" => $controlItem['uponDirection'], "withoutControl" => $controlItem['withoutControl'], "flagSelfRecording" => $controlItem['flagSelfRecording'], "orderPercent" => $controlItem['orderPercent'], ]; $resultMass[$len] = array_merge($resultMass[$len], $item); } } foreach ($workProf as $workProfItem) { if ($workProfItem['jobId'] === $keyItem) { $item = [ "workProfId" => $workProfItem['workProfId'], "jobType" => $workProfItem['jobType'], "visitTime" => $workProfItem['visitTime'], "jobId" => $workProfItem['jobId'], "professionId" => $workProfItem['professionId'], "couponQuotaId" => $workProfItem['couponQuotaId'], "visitNumberOnHour" => $workProfItem['visitNumberOnHour'], "coefficient" => $workProfItem['coefficient'], ]; $resultMass[$len] = array_merge($resultMass[$len], $item); } } $len += 1; } return $resultMass; } public function syncSpecialtiesRate($records) { $db = $this->db ?: $this->db = connectDb(); $sqlControl = <<<"EOT"
INSERT INTO MedCouponControl (MedCouponControlCode, OrderPercent, FlagSelfRecording, WithoutControl,UponDirection,SelfRecordingPercent,
    SelfRecordingPercentMin,MedProfessionCode,MedCouponWorkCode)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (MedCouponControlCode) DO UPDATE SET
    OrderPercent = case when ? = 1 then EXCLUDED.OrderPercent else MedCouponControl.OrderPercent end,
    FlagSelfRecording = case when ? = 1 then EXCLUDED.FlagSelfRecording else MedCouponControl.FlagSelfRecording end,
    WithoutControl = case when ? = 1 then EXCLUDED.WithoutControl else MedCouponControl.WithoutControl end,
    UponDirection = case when ? = 1 then EXCLUDED.UponDirection else MedCouponControl.UponDirection end,
    SelfRecordingPercent = case when ? = 1 then EXCLUDED.SelfRecordingPercent else MedCouponControl.SelfRecordingPercent end,
    SelfRecordingPercentMin = case when ? = 1 then EXCLUDED.SelfRecordingPercentMin else MedCouponControl.SelfRecordingPercentMin end,
    MedCouponWorkCode = case when ? = 1 then EXCLUDED.MedCouponWorkCode else MedCouponControl.MedCouponWorkCode end
EOT;
$stmtControl = $db->prepare($sqlControl); $sqlWorkProf = <<<"EOT"
INSERT INTO MedCouponWorkProf (MedCouponWorkProfCode, MedProfessionCode, MedCouponWorkCode, Coefficient, VisitNumberOnHour)
VALUES (?, ?, ?, ?, ?)
ON CONFLICT (MedCouponWorkProfCode) DO UPDATE SET
    Coefficient = case when ? = 1 then EXCLUDED.Coefficient else MedCouponWorkProf.Coefficient end,
    VisitNumberOnHour = case when ? = 1 then EXCLUDED.VisitNumberOnHour else MedCouponWorkProf.VisitNumberOnHour end,
    MedCouponWorkCode = case when ? = 1 then EXCLUDED.MedCouponWorkCode else MedCouponWorkProf.MedCouponWorkCode end
EOT;
$stmtWorkProf = $db->prepare($sqlWorkProf); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmtControl->execute([ isEmptyProperty($row, 'couponControlId', null), isUndefinedProperty($row, 'orderPercent'), isUndefinedProperty($row, 'flagSelfRecording'), isUndefinedProperty($row, 'withoutControl'), isUndefinedProperty($row, 'uponDirection'), isUndefinedProperty($row, 'selfRecordingPercent'), isUndefinedProperty($row, 'selfRecordingPercentMin'), isEmptyProperty($row, 'professionId'), isEmptyProperty($row, 'jobId'), propertyExistsSQL($row, 'orderPercent'), propertyExistsSQL($row, 'flagSelfRecording'), propertyExistsSQL($row, 'withoutControl'), propertyExistsSQL($row, 'uponDirection'), propertyExistsSQL($row, 'selfRecordingPercent'), propertyExistsSQL($row, 'selfRecordingPercentMin'), propertyExistsSQL($row, 'jobId') ]); $stmtWorkProf->execute([ isEmptyProperty($row, 'workProfId', null), isEmptyProperty($row, 'professionId'), isEmptyProperty($row, 'jobId'), isUndefinedProperty($row, 'coefficient'), isUndefinedProperty($row, 'visitNumberOnHour'), propertyExistsSQL($row, 'coefficient'), propertyExistsSQL($row, 'visitNumberOnHour'), propertyExistsSQL($row, 'jobId') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } public function deleteSpecialtiesRate($params = null) { $db = $this->db ?: $this->db = connectDb(); $controlId = isEmptyProperty($params, 'couponControlId', 0); $workProfId = isEmptyProperty($params, 'workProfId', 0); $sqlControl = <<<"EOT"
DELETE FROM MedCouponControl WHERE MedCouponControlCode = $controlId
EOT;
$sqlWork = <<<"EOT"
DELETE FROM MedCouponWorkProf WHERE MedCouponWorkProfCode = $workProfId
EOT;
$stmtControl = $db->prepare($sqlControl); $stmtWork = $db->prepare($sqlWork); $db->beginTransaction(); try { $stmtControl->execute(); $stmtWork->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readUniSpecialties($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    UniImportLinkCode "id",
    Name "name",
    Code "uniCode"
FROM UniImportLink
WHERE Program = 6
  AND DataType = 1
ORDER BY 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateSpecialties($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedProfession SET
       Name = case when ? = 1 then ? else Name end,
       Cipher = case when ? = 1 then ? else Cipher end,
       HourOnRate = case when ? = 1 then ? else HourOnRate end,
       TalonByCode = case when ? = 1 then ? else TalonByCode end,
       DisplayInInfobox = case when ? = 1 then ? else DisplayInInfobox end
WHERE MedProfessionCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'hourOnRate'), isUndefinedProperty($row, 'hourOnRate'), propertyExistsSQL($row, 'uniId'), isEmptyProperty($row, 'uniId'), propertyExistsSQL($row, 'displayInInfobox'), isEmptyProperty($row, 'displayInInfobox'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteSpecialties($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM MedProfession WHERE MedProfessionCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function getMaxCipher($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT incstr(COALESCE((SELECT MAX(Cipher) FROM MedProfession),  E'0000' )) AS "maxCipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $maxCipher = fetchValue($stmt, 'maxCipher'); return $maxCipher; } public function createSpecialties($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedProfession (cipher , name , talonbycode, houronrate, displayininfobox)
VALUES (?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'cipher'), isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'uniId'), isUndefinedProperty($row, 'hourOnRate'), isUndefinedProperty($row, 'displayInInfobox') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedProfession'); $row->id = $rec->id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readCouponQuotaPercent($params = null) { $db = $this->db ?: $this->db = connectDb(); $quotaProfessionId = isEmptyProperty($params, 'quotaProfessionId', 0); $sql = <<<"EOT"
SELECT
a.MedCouponQuotaPercentCode "id",
a.MedCouponQuotaCode "couponQuotaId",
a.MedCouponQuotaPercentCode "couponQuotaId",
a.MedDoctorCode "doctorId",
a.DepartmentCode "departmentId",
a.MedProfessionCode "professionId",
cq.MedCouponWorkCode "couponWorkId",
a.Percent "percent",
COALESCE(b.Name ,  E'<все>' ) "departmentName",
case FN_cmFIO(c.ManCode)
when   E'' then  E'<все>' else FN_cmFIO (c.ManCode)
end "doctorFio",
COALESCE(d.Name,  E'<все>' ) "specialityName"
FROM MEDCOUPONQUOTAPERCENT AS a left outer join Department AS b on a.DepartmentCode = b.DepartmentCode
left outer join MedDoctor AS c on a.MedDoctorCode = c.MedDoctorCode
left outer join MedProfession AS d on a.MedProfessionCode = d.MedProfessionCode
left outer join MedCouponQuota AS cq on cq.MedCouponQuotaCode = a.MedCouponQuotaCode
where cq.MedProfessionCode = $quotaProfessionId
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createCouponQuotaPercent($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MedCouponQuotaPercent  (MedCouponQuotaCode , MedDoctorCode , DepartmentCode, MedProfessionCode, Percent)
VALUES (?, ?, ?, ?, ?)
EOT;
$sqlQouta = <<<"EOT"
INSERT INTO MedCouponQuota (MedCouponWorkCode , MedProfessionCode)
VALUES (?, ?)
EOT;
$stmt = $db->prepare($sql); $stmtQuota = $db->prepare($sqlQouta); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $couponQuotaId = isEmptyProperty($row, 'couponQuotaId'); if (!$couponQuotaId) { $stmtQuota->execute([ isEmptyProperty($row, 'couponWorkId'), isEmptyProperty($row, 'quotaProfessionId') ]); $couponQuotaId = $db->lastInsertId('seq_MedCouponQuota'); } $stmt->execute([ $couponQuotaId, isEmptyProperty($row, 'doctorId'), isEmptyProperty($row, 'departmentId'), isEmptyProperty($row, 'professionId'), isUndefinedProperty($row, 'percent') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCouponQuotaPercent'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateCouponQuotaPercent($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCouponQuotaPercent SET
       MedDoctorCode = case when ? = 1 then ? else MedDoctorCode end,
       DepartmentCode = case when ? = 1 then ? else DepartmentCode end,
       MedProfessionCode = case when ? = 1 then ? else MedProfessionCode end,
       Percent = case when ? = 1 then ? else Percent end
WHERE MedCouponQuotaPercentCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'percent'), isUndefinedProperty($row, 'percent'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteCouponQuotaPercent($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MedCouponQuotaPercent', 'MedCouponQuotaPercentCode', $records); } } $service = new Junior\Server(new med_common_specialties()); $service->process(); 