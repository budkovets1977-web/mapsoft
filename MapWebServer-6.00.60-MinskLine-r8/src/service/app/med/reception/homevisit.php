<?php
require_once(__DIR__.'/../../init.php'); class med_reception_homevisit { private $db; const DELETE_TRANSFERRED_PERMISSION = 'med-reception-homevisit-delete-transferred'; const ERROR_DELETE_TRANSFERRED_PERMISSION = 'У Вас нет прав на удаление переданных вызовов. Обратитесь к Администратору.'; public function readHomeVisit($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $callDate = isEmptyProperty($params, 'callDate'); $branchId = isUndefinedProperty($params, 'branchId'); $departmentId = isUndefinedProperty($params, 'departmentId'); $patientId = isUndefinedProperty($params, 'patientId'); $visitType = isUndefinedProperty($params, 'visitType'); $branchSql = ''; $callDateSql = ''; $departmentSql = ''; $whereCommon = ''; if ($patientId) { $whereCommon .= " AND MC.MedPatientCode = $patientId"; } if ($visitType === 4) { $whereCommon .= " AND MC.InternetCall = 1"; } else { if ($visitType) { $whereCommon .= " AND MC.CallType = $visitType"; } } if ($callDate) { $callDateSql = "AND CAST(mc.callTime AS DATE) = $callDate"; } if ($departmentId) { $departmentSql = "AND md.departmentcode = $departmentId"; } $ids = isEmptyProperty($params, 'ids'); $idsSql = ''; if ($ids) { $idsSql = 'AND mc.medCallCode ' . createSqlInCondition($ids); } $taskDate = Date('Ymd'); $condDoctor = isEmptyProperty($params, 'doctorId') ? " AND mc.meddoctorcode = $params->doctorId" : ""; if (!isset($callDate) && !$ids) { return null; } if (is_int($branchId)) { $branchSql = <<<SQL
                AND (
                    EXISTS(
                        SELECT 1
                        FROM MedDoctor MDD
                        LEFT JOIN Department D ON D.DepartmentCode = MDD.DepartmentCode
                        LEFT JOIN Branch B ON B.BranchCode = D.BranchCode
                        WHERE (MDD.MedDoctorCode = mpg.MedDoctorCode
                                OR MDD.MedPatientGroupCode = mpg.medPatientGroupCode) AND
                        B.BranchCode = $branchId
                        LIMIT 1
                        )
                    )
                SQL;
} $sqlFromWhere = <<<SQL
            FROM medCall mc
            LEFT JOIN medPatient mp ON mp.medPatientCode = mc.medPatientCode
            LEFT JOIN manPassport mpt ON mpt.manCode = mp.manCode
            LEFT JOIN medDoctor md ON md.medDoctorCode = mc.medDoctorCode
            LEFT JOIN medProfession pr ON pr.medProfessionCode = md.medProfessionCode
            LEFT JOIN medPatientGroup mpg ON mpg.medPatientGroupCode = mp.medPatientGroupCode
            WHERE
                mc.enterpriseCode = $enterpriseCode
                $callDateSql
                $condDoctor
                $idsSql
                $branchSql
                $departmentSql
                $whereCommon
            SQL;
$sql = <<<SQL
            SELECT
                mc.medCallCode                          "id",
                mc.enterpriseCode                       "enterpriseId",
                mc.callTime                             "callTime",
                MC.MedPatientCode                       "patientId",
                CASE mc.medPatientCode IS NULL
                    WHEN FALSE THEN FN_cmFIO(mp.manCode)
                    WHEN TRUE THEN fio
                END                                     "patientName",
                CAST(FN_cmCalcAddress(mp.ManCode, 5, 3, 1) AS VARCHAR(100))
                                                        "patientAddress",
                FN_cmCalcAddress(MP.ManCode, 0)::TEXT   "patientHomeAddress",
                FN_cmCalcAddress(MP.ManCode, 1)::TEXT   "patientResidenceAddress",
                (SELECT STRING_AGG((CASE COALESCE(mph.phoneType, 0)
                                        WHEN 1 THEN 'д'
                                        ELSE 'м'
                                    END || E'. ' || mph.phoneNumber)::TEXT, E';')
                    FROM manPhone mph
                    WHERE mph.manCode = mp.ManCode
                        AND mph.phoneNumber IS NOT NULL
                        AND mph.phoneNumber != ''
                        AND COALESCE(mph.phoneType, 0) IN (1, 3))
                                                        "patientPhone",
                mpt.dateOfBirth                         "dateOfBirth",
                FN_wgCalcStage(2, $taskDate, mpt.DateOfBirth, 0, 0, 0, null)
                                                        "age",
                mc.note                                 "note",
                mc.motive                               "motive",
                mc.callType                             "callType",
                mc.medDoctorCode                        "doctorId",
                FN_cmFIO(md.manCode)                    "doctorName",
                pr.name                                 "doctorProfessionName",
                mc.doctorSendTime                       "doctorSendTime",
                mc.internetCall                         "internetCall",
                mc.phoneNumber                          "phoneNumber",
                mc.webUserCode                          "webUserId",
                mc.flags                                "flags",
                mpg.name                                "patientGroupName",
                (
                    CASE
                        WHEN COALESCE(MP.FinishDate, 30000101)::DATE = 30000101
                            OR MP.FinishDate > DATE('NOW'::DATE)
                        THEN 1
                        ELSE 0
                    END
                )                                       "patientIsActive",
                MP.OutReasonEx                          "patientOutReason",
                MP.FinishDate                           "patientFinishDate",
                MPT.Sex                                 "patientGender"
            $sqlFromWhere
            ORDER BY FN_cmFIO(MD.ManCode), MC.MedDoctorCode, MC.DoctorSendTime
            NULLS FIRST
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $data = []; $doctor = -1; $id = 0; $levelKind = 0; $group = 0; $totalUnassignedDoc = 0; $totalAssignedDoc = 0; $totalUnassigned = 0; $totalAssigned = 0; $totalFirst = 0; $totalReturn = 0; $totalActive = 0; $totalInternet = 0; $docRec = null; $totalAssignedRec = null; $totalUnassignedRec = null; while ($row = $stmt->fetch()) { if ($doctor !== $row['doctorId']) { if ($docRec !== null) { $docRec->totalAssigned = $totalAssignedDoc; $docRec->totalUnassigned = $totalUnassignedDoc; if ($totalAssignedRec !== null) { $totalAssignedRec->total = $totalAssignedDoc; $totalAssigned += $totalAssignedDoc; } if ($totalUnassignedRec !== null) { $totalUnassignedRec->total = $totalUnassignedDoc; $totalUnassigned += $totalUnassignedDoc; } } $doctor = $row['doctorId']; $totalAssignedDoc = 0; $totalUnassignedDoc = 0; $totalAssignedRec = null; $totalUnassignedRec = null; $id--; $levelKind = 0; $data[] = $rec = new stdClass; $docRec = $rec; $rec->id = $id; $rec->level = 0; $rec->doctorId = $row['doctorId']; if ($row['doctorId'] !== null) { $group = 1; $rec->groupId = $group; $rec->doctorName = $row['doctorName']; $rec->levelName = $row['doctorName']; $rec->doctorProfessionName = $row['doctorProfessionName']; } else { $rec->groupId = $group; $rec->levelName = 'Неподтвержденные'; } } if ($row['doctorId'] !== null) { if ($row['doctorSendTime'] === null) { $totalUnassignedDoc++; } else { $totalAssignedDoc++; } if (($levelKind == 0) && ($row['doctorSendTime'] === null)) { $levelKind = 1; $data[] = $rec = new stdClass; $totalUnassignedRec = $rec; $id--; $rec->id = $id; $rec->groupId = $group; $rec->level = 1; $rec->levelKind = 1; $rec->doctorId = $row['doctorId']; $rec->doctorName = $row['doctorName']; $rec->levelName = 'Непереданные'; } else { if (($levelKind <= 1) && ($row['doctorSendTime'] !== null)) { $levelKind = 2; $data[] = $rec = new stdClass; $totalAssignedRec = $rec; $id--; $rec->id = $id; $rec->groupId = $group; $rec->level = 1; $rec->levelKind = 2; $rec->doctorId = $row['doctorId']; $rec->doctorName = $row['doctorName']; $rec->levelName = 'Переданные'; } } } if ($row['internetCall'] === 1) { $totalInternet++; } switch ((int) $row['callType']) { case 1: $totalFirst++; break; case 2: $totalReturn++; break; case 3: $totalActive++; break; } $data[] = $rec = new stdClass; $rec->id = $row['id']; $rec->groupId = $group; $rec->level = 2; $rec->levelKind = $levelKind; $rec->enterpriseId = $row['enterpriseId']; $rec->callTime = $row['callTime']; $rec->patientId = $row['patientId']; $rec->patientName = $row['patientName']; $rec->patientAddress = $row['patientAddress']; $rec->patientHomeAddress = $row['patientHomeAddress']; $rec->patientResidenceAddress = $row['patientResidenceAddress']; $rec->patientPhone = $row['patientPhone']; $rec->dateOfBirth = $row['dateOfBirth']; $rec->age = $row['age']; $rec->note = $row['note']; $rec->motive = $row['motive']; $rec->callType = $row['callType']; $rec->doctorId = $row['doctorId']; if (!empty($row['doctorName'])) { $rec->doctorName = $row['doctorName']; } $rec->doctorSendTime = $row['doctorSendTime']; $rec->internetCall = $row['internetCall']; $rec->phoneNumber = $row['phoneNumber']; $rec->webUserId = $row['webUserId']; $rec->flags = $row['flags']; $rec->patientGroupName = $row['patientGroupName']; $rec->patientIsActive = $row['patientIsActive']; $rec->patientOutReason = $row['patientOutReason']; $rec->patientFinishDate = $row['patientFinishDate']; $rec->patientGender = $row['patientGender']; } if ($docRec !== null) { $docRec->totalAssigned = $totalAssignedDoc; $docRec->totalUnassigned = $totalUnassignedDoc; if ($totalAssignedRec !== null) { $totalAssignedRec->total = $totalAssignedDoc; $totalAssigned += $totalAssignedDoc; } if ($totalUnassignedRec !== null) { $totalUnassignedRec->total = $totalUnassignedDoc; $totalUnassigned += $totalUnassignedDoc; } } $sqlMotive = <<<SQL
            SELECT DISTINCT
                mc.motive "name"
            FROM medCall mc
            WHERE
                mc.enterpriseCode = $enterpriseCode
                AND COALESCE(mc.motive, E'' ) <> (E'')
                AND mc.callTime BETWEEN dateAdd('month', -1, today()) AND dateAdd('day', 1, today())
            ORDER BY 1
            SQL;
$stmtMotive = $db->prepare($sqlMotive); $stmtMotive->execute(); $motive = $stmtMotive->fetchAll(); $aData = new stdClass(); $aData->totalAssigned = $totalAssigned; $aData->totalUnassigned = $totalUnassigned; $aData->totalInternet = $totalInternet; $aData->totalFirst = $totalFirst; $aData->totalReturn = $totalReturn; $aData->totalActive = $totalActive; $root = new stdClass(); $root->visitData = $data; $root->motiveData = $motive; $root->additionalData = $aData; return $root; } public function updateHomeVisit($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            UPDATE medCall SET
                callTime =          CASE WHEN ? = 1 THEN ? ELSE callTime END,
                medPatientCode =    CASE WHEN ? = 1 THEN ? ELSE medPatientCode END,
                fio =               CASE WHEN ? = 1 THEN ? ELSE fio END,
                note =              CASE WHEN ? = 1 THEN ? ELSE note END,
                motive =            CASE WHEN ? = 1 THEN ? ELSE motive END,
                callType =          CASE WHEN ? = 1 THEN ? ELSE callType END,
                medDoctorCode =     CASE WHEN ? = 1 THEN ? ELSE medDoctorCode END,
                doctorSendTime =    CASE WHEN ? = 1 THEN ? ELSE doctorSendTime END,
                internetCall =      CASE WHEN ? = 1 THEN ? ELSE internetCall END,
                phoneNumber =       CASE WHEN ? = 1 THEN ? ELSE phoneNumber END,
                webUserCode =       CASE WHEN ? = 1 THEN ? ELSE webUserCode END,
                flags =             CASE WHEN ? = 1 THEN ? ELSE flags END
            WHERE medCallCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'callTime'), isUndefinedProperty($row, 'callTime'), propertyExistsSQL($row, 'patientId'), isEmptyProperty($row, 'patientId'), propertyExistsSQL($row, 'patientName'), isEmptyProperty($row, 'patientId') ? null : isEmptyProperty($row, 'patientName'), propertyExistsSQL($row, 'note'), isEmptyProperty($row, 'note'), propertyExistsSQL($row, 'motive'), isEmptyProperty($row, 'motive'), propertyExistsSQL($row, 'callType'), isUndefinedProperty($row, 'callType'), propertyExistsSQL($row, 'doctorId'), isEmptyProperty($row, 'doctorId'), propertyExistsSQL($row, 'doctorSendTime'), isUndefinedProperty($row, 'doctorSendTime'), propertyExistsSQL($row, 'internetCall'), isUndefinedProperty($row, 'internetCall'), propertyExistsSQL($row, 'phoneNumber'), isEmptyProperty($row, 'phoneNumber'), propertyExistsSQL($row, 'webUserId'), isEmptyProperty($row, 'webUserId'), propertyExistsSQL($row, 'flags'), isEmptyProperty($row, 'flags'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createHomeVisit($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $webUser = getSessionValue('user'); $now = new DateTime; $now->setTime(0, 0,0); $permissions = getUserAccess('med-reception-homevisit-add-past-date'); $sql = <<<EOT
            INSERT INTO medCall (enterpriseCode, callTime, medPatientCode,
                                fio, note, motive,
                                callType, medDoctorCode, doctorSendTime,
                                internetCall, phoneNumber, webUserCode,
                                flags)
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $callTime = isUndefinedProperty($row, 'callTime'); if (!$permissions['med-reception-homevisit-add-past-date']) { $cdate = DateTime::createFromFormat(DateTimeInterface::ISO8601, $callTime); $cdate->setTime(0, 0,0); $diff = $now->diff($cdate); if (($diff->invert === 1) and ($diff->days >= 0)) { throw new Exception('У вас нет прав на добавление вызова врача на дом за предыдущее число'); } } $stmt->execute([ $enterpriseCode, $callTime, isEmptyProperty($row, 'patientId'), isEmptyProperty($row, 'patientId') ? null : isEmptyProperty($row, 'patientName'), isEmptyProperty($row, 'note'), isEmptyProperty($row, 'motive'), isUndefinedProperty($row, 'callType'), isEmptyProperty($row, 'doctorId'), isUndefinedProperty($row, 'doctorSendTime'), isUndefinedProperty($row, 'internetCall'), isEmptyProperty($row, 'phoneNumber'), $webUser, isEmptyProperty($row, 'flags') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCall'); $rec->enterpriseId = $enterpriseCode; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } $root = new stdClass; $root->visitData = $ret; return $root; } protected function checkAccessDeleteTransferred () { if (!testUserAccess(self::DELETE_TRANSFERRED_PERMISSION)) { throw new Exception(self::ERROR_DELETE_TRANSFERRED_PERMISSION, ERROR_NOT_ALLOWED); } } public function deleteHomeVisit($records) { $db = $this->db ?: $this->db = connectDb(); if (!is_array($records)) { $records = [$records]; } $params = new stdClass(); $params->ids = $records; $data = $this->readHomeVisit($params); if ($data) { $records = $data->visitData; } $needCheckAccess = false; foreach ($records as $record) { if ($record->id > 0 && $record->levelKind === 2) { $needCheckAccess = true; break; } } if($needCheckAccess) { $this->checkAccessDeleteTransferred(); } return deleteSql($db, 'medCall', 'medCallCode', $records); } public function getPatientInfo($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $patientId = isset($params->patientId) && isEmptyProperty($params, 'patientId') ? $params->patientId : null; $taskDate = Date('Ymd'); $root = new stdClass; if ($params === null) { return null; } $sqlInfo = <<<EOT
            SELECT
                md.medDoctorCode "doctorId",
                FN_cmFIO(md.manCode) "doctorName",
                pr.Name "doctorProfessionName",
                FN_wgCalcStage(2, $taskDate, mpt.DateOfBirth, 0, 0, 0, null) "age",
                mpg.name "patientGroupName"
            FROM medPatient mp
            LEFT JOIN manPassport mpt ON mpt.manCode = mp.manCode
            JOIN medPatientGroup mpg ON mpg.medPatientGroupCode = mp.medPatientGroupCode
            JOIN medDoctor md ON md.medDoctorCode = mpg.medDoctorCode
            JOIN medProfession pr ON pr.medProfessionCode = md.medProfessionCode
                AND today() BETWEEN COALESCE(md.startDate, 19000101) AND COALESCE(md.finishDate, 30000101)
            WHERE mp.medPatientCode = ?
EOT;
$sqlVisit = <<<EOT
            SELECT
                mc.note "note"
            FROM medCall mc
            WHERE
                mc.enterpriseCode = ?
                AND mc.medPatientCode = ?
                AND mc.callTime < today()
            ORDER BY callTime DESC
            LIMIT 1
EOT;
if (isset($patientId)) { $stmtInfo = $db->prepare($sqlInfo); $stmtInfo->execute([$patientId]); $res = $stmtInfo->fetch(); if (!empty($res)) { $root->doctorId = $res['doctorId']; $root->doctorName = $res['doctorName']; $root->doctorProfessionName = $res['doctorProfessionName']; $root->age = $res['age']; $root->patientGroupName = $res['patientGroupName']; } $stmtVisit = $db->prepare($sqlVisit); $stmtVisit->execute([$enterpriseCode, $patientId]); $res = $stmtVisit->fetch(); if (!empty($res)) { $root->note = $res['note']; } } return $root; } public function checkInternetCall($params = null) { $db = $this->db ? : $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $callDate = isset($params->callDate) && isEmptyProperty($params, 'callDate') ? $params->callDate : null; if (!isset($callDate)) { return null; } $sql = <<<SQL
            SELECT
                1
            FROM medCall
            WHERE
                enterpriseCode = $enterpriseCode
                AND CAST(callTime AS DATE) = $callDate
                AND COALESCE(internetCall, 0) = 1
                AND medDoctorCode IS NULL
            LIMIT 1
            SQL;
$stmt = $db->prepare($sql); $stmt->execute(); $res = $stmt->fetch() ? true : false; return ($res); } } handleJsonRpc('med_reception_homevisit');