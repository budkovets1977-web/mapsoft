<?php
require_once(__DIR__ . '/../../init.php'); class med_common_doctors { const PHOTO_CACHE = 'medCommonDoctorPhotoCache'; const TASK_NAME = 'medCommon'; private $db; public function readDoctors($params = null) { $db = $this->db ?: $this->db = connectDb(); $isPM = (int) in_array(getSessionValue('mapWebTaskCode'), [526, 527, 528]); $depName = ($isPM ? 'DEP.Name' : 'FN_cmDepartmentName(D.DepartmentCode, 1)::TEXT'); if (!isset($params)) { $params = new stdClass(); } $isFilter = isset($params) ? (int) isUndefinedProperty($params, 'isFilter', 0) : 0; $enterpriseCode = getSessionValue('mapEnterpriseCode'); $currDate = Date("Ymd", safe_strtotime(isEmptyProperty($params, 'date', Date('Ymd')))); $condSearch = ""; $joinEnterpriseStructureCode = ""; $joinEnterpriseStructureParentCode = ""; $selectEnterpriseStructureCode = ""; $selectEnterpriseStructureParentCode = ""; $doctorId = isEmptyProperty($params, 'id', 0); if (!isEmptyProperty($params, 'loadOneWorker')) { if (isset($params->search) && isEmptyProperty($params, 'search') && $params->search !== "") { $aFio = trim($params->search); $fio = preg_split("/[\s.,]+/u", $aFio); $condSearch .= "M.SurName LIKE E'%$fio[0]%'"; if (isset($fio[1]) && $fio[1] !== "") { $condSearch .= " AND M.Name LIKE E'%$fio[1]%'"; } if (isset($fio[2]) && $fio[2] !== "") { $fio[2] = trim(implode(" ", (array_slice($fio, 2)))); $condSearch .= " AND M.SecondName LIKE E'%$fio[2]%'"; } $condSearchEx = getSearchSqlCondition($params->search, ["MP.Name", "M.SecondName", "M.Name", "M.SurName"]); $condSearch = "(" . $condSearch . " OR " . $condSearchEx . ")"; if (is_numeric($aFio)) { $condSearch .= "or EXISTS(select 1
                                          from MedBarCode
                                          where BarCode = E'$aFio'
                                            and ManCode = D.ManCode
                                          limit 1)"; } $condSearch = ' AND (' . $condSearch . ')'; } if (isset($params->branches)) { $condSearch .= ' AND B.BranchCode' . createSqlInCondition($params->branches); } if (isset($params->codeZoneAccess)) { $condSearch .= ' AND (fn_WebUserAccessDepartment(D.DepartmentCode) = 1) '; $joinEnterpriseStructureCode .= "LEFT JOIN enterprisestructure es ON es.enterprisestructurecode = (SELECT enterprisestructurecode FROM enterprisestructure WHERE departmentcodeweb = DEP.departmentcode AND finishdate >= now()::date LIMIT 1)"; $selectEnterpriseStructureCode .= 'es.enterprisestructurecode "doctorEnterpriseStructureCode",'; if (isset($params->enterprisestructurecode)) { $condSearch .= ' AND es.enterprisestructurecode' . createSqlInCondition($params->enterprisestructurecode); } } if (isset($params->departments)) { $condSearch .= ' AND DEP.DepartmentCode' . createSqlInCondition($params->departments); } if (isset($params->professions) && $params->professions != 0) { $condSearch .= ' AND MP.MedProfessionCode' . createSqlInCondition($params->professions); } if (isset($params->esParentCode)) { $joinEnterpriseStructureParentCode .= "LEFT JOIN EnterpriseStructure ES ON ES.DepartmentCodeWeb = DEP.DepartmentCode"; $selectEnterpriseStructureParentCode .= 'es.EnterpriseStructureParentCode "officeUnitId",'; } $btw = "BETWEEN COALESCE(D.StartDate, 19000101) AND COALESCE(D.FinishDate, 30000101)"; switch (isUndefinedProperty($params, 'working', 0)) { case 1: $condSearch .= " AND $currDate $btw"; break; case 2: $condSearch .= " AND $currDate NOT $btw"; break; } switch (isUndefinedProperty($params, 'hidden', 0)) { case 1: $condSearch .= " AND (COALESCE(D.AttributeMark, 0) & 1) <> 1"; break; case 2: $condSearch .= " AND (COALESCE(D.AttributeMark, 0) & 1) = 1"; break; } if (isset($params->onlyCiphers)) { if (!empty($params->onlyCiphers)) { $condSearch .= ' AND D.Cipher' . createSqlInCondition($params->onlyCiphers); } else { $condSearch .= ' AND FALSE'; } } } $checkGraph = ""; if (isset($params->checkGraph) && ($params->checkGraph) && (!$doctorId)) { if (isset($params->jobId) && $params->jobId != 0) { if (isset($params->couponState) && !is_null($params->couponState)) { $checkGraph = "JOIN (" . "SELECT DISTINCT MedDoctorCode " . "FROM MedCoupon " . "WHERE $currDate <= OnDate and MedCouponWorkCode = $params->jobId and CouponState = $params->couponState" . ") AS G ON G.MedDoctorCode = D.MedDoctorCode"; } else { $checkGraph = "JOIN (" . "SELECT DISTINCT MedDoctorCode " . "FROM MedCoupon " . "WHERE $currDate <= OnDate and MedCouponWorkCode = $params->jobId" . ") AS G ON G.MedDoctorCode = D.MedDoctorCode"; } } else { $checkGraph = "JOIN (" . "SELECT DISTINCT MedDoctorCode " . "FROM MedCouponGraph " . "WHERE $currDate <= OnDate " . "UNION " . "SELECT DISTINCT MedDoctorCode " . "FROM MedCoupon " . "WHERE $currDate <= OnDate" . ") AS G ON G.MedDoctorCode = D.MedDoctorCode"; } } $graphInfo = ""; if (isset($params->getGraphInfo) && ($params->getGraphInfo)) { $graphInfo = <<<"EOT"
, D.BasicTimeTable "basicTimeTable",
  ( SELECT 'Кабинет: ' || COALESCE(wg.Cabinet, D.Cabinet) || ' Время работы: ' || wg.StartTime || '-' || wg.FinishTime || ' ' || wg.graph
    FROM (
        SELECT mpg.Cabinet,
               CAST(CAST(mpg.StartTime AS TIME) AS VARCHAR(5)) StartTime,
               CAST(CAST(mpg.FinishTime AS TIME) AS VARCHAR(5)) FinishTime,
               string_agg(mcw.Name || ': ' || CAST(CAST(mpgw.StartTime AS TIME) AS VARCHAR(5)) || '-' || CAST(CAST(mpgw.FinishTime AS TIME) AS VARCHAR(5)), ' ' ORDER BY mpgw.StartTime, mcw.Name) graph
        FROM MedCouponGraph mpg
        JOIN MedCouponGraphWork mpgw ON mpgw.MedCouponGraphCode = mpg.MedCouponGraphCode
        JOIN MedCouponWork mcw ON mcw.MedCouponworkCode = mpgw.MedCouponworkCode
        WHERE mpg.OnDate = $currDate AND mpg.MedDoctorCode = D.MedDoctorCode
        GROUP BY 1, 2, 3) wg
  )::text "timeTable"
EOT;
} $sort = isEmptyProperty($params, 'sort', '"fio"'); $dir = isEmptyProperty($params, 'dir', ''); $sqlPage = ''; if (isset($params->page) && isset($params->limit)) { $sqlPage = 'limit ' . $params->limit . ' offset ' . (($params->page - 1) * $params->limit); } if (isset($params->newSearch) && $params->newSearch == 1) { $sqlPage = 'limit ' . $params->limit . ' offset 0'; } $roles = isEmptyProperty($params, 'roles'); $roleWhere = empty($roles) ? '' : "AND RP.AmbRoleCode IN ($roles)"; $roleJoin = empty($roles) ? '' : 'LEFT JOIN AmbRoleProfession RP ON MP.medProfessionCode = RP.medProfessionCode'; $ambGraphId = isUndefinedProperty($params, 'ambGraphId'); $ambDocIds = isUndefinedProperty($params, 'ambDocIds'); $ambGraphJoin = ''; $ambGraphWhere = ''; $union = ''; if (!empty($ambGraphId)){ $ambGraphJoin = 'LEFT JOIN AmbGraphPerson agp ON agp.medDoctorCode = d.medDoctorCode'; $ambGraphWhere = "AND agp.ambGraphCode IN ($ambGraphId)"; if (!empty($ambDocIds)){ $union = <<<EOT
                    UNION
                    SELECT
                        d.medDoctorCode "id",
                        FN_cmFIO(d.manCode)::TEXT "fio",
                        FN_cmFIO(d.manCode, 1)::TEXT "abbName",
                        mp.name "profession",
                        d.medProfessionCode "professionId",
                        d.departmentCode "departmentId",
                        FN_cmDepartmentName(d.departmentCode, 1)::TEXT "departmentName",
                        d.cabinet "cabinet",
                        NULL
                    FROM medDoctor d
                    LEFT JOIN man m ON m.manCode = d.manCode
                    LEFT JOIN medProfession mp ON mp.medProfessionCode = d.medProfessionCode
                    WHERE d.medDoctorCode IN ($ambDocIds)
EOT;
} } $sqlFromWhere = "
            FROM MedDoctor D
                JOIN Man M on M.ManCode = D.ManCode
                $checkGraph
                LEFT JOIN MedProfession MP ON MP.MedProfessionCode = D.MedProfessionCode
                $roleJoin
                JOIN Department DEP ON DEP.DepartmentCode = D.DepartmentCode
                    AND ($isFilter = 0 or ($isFilter = 1 AND fn_WebUserAccessDepartment(d.DepartmentCode) = 1))
                $joinEnterpriseStructureCode
                $joinEnterpriseStructureParentCode
                JOIN Branch B ON B.BranchCode = DEP.BranchCode AND B.EnterpriseCode = $enterpriseCode
                LEFT JOIN MedPatientGroup MPG ON MPG.MedPatientGroupCode = D.MedPatientGroupCode
                LEFT JOIN Users U ON U.UserCode = D.UserCode
                LEFT OUTER JOIN WebUser WU ON WU.UserCode = U.UserCode
                LEFT JOIN ManPassport MPass ON MPass.ManCode = D.ManCode
                $ambGraphJoin
            WHERE (0 = $doctorId OR d.MedDoctorCode = $doctorId)
            $roleWhere
            $ambGraphWhere
            $condSearch "; $sql = <<<EOT
            SELECT COUNT(*) "total"
            $sqlFromWhere
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int) fetchValue($stmt, 'total'); $sql = <<<EOT
            SELECT DISTINCT
                D.MedDoctorCode                     "id",
                D.Cipher                            "cipher",
                FN_cmFIO(D.ManCode)::TEXT           "fio",
                FN_cmFIO(d.manCode, 1)::TEXT        "abbName",
                MP.Name                             "profession",
                D.MedProfessionCode                 "professionId",
                D.DepartmentCode                    "departmentId",
                DEP.BranchCode                      "branchId",
                B.EnterpriseCode                    "organizationId",
                $selectEnterpriseStructureCode
                $selectEnterpriseStructureParentCode
                {$depName}                          "departmentName",
                D.Cabinet                           "cabinet",
                (
                    select string_agg(name::TEXT, ', ' ORDER BY Name)
                    from MedPatientGroup
                    where MedDoctorCode = D.MedDoctorCode
                )                                   "sectors",
                MPass.PersonalNumber                "doctorPersonalNumber"
                $graphInfo
EOT;
if (!isset($params->compactModel) || !$params->compactModel) { $sql .= <<<EOT
                , D.ManCode                         "manId",
                D.Cipher                            "cipher",
                D.MedPatientGroupCode               "patientGroupId",
                MPG.Name                            "patientGroup",
                U.Nick                              "user",
                WU.Nick                             "webUser",
                random()                            "random",
                D.UserCode                          "userId",
                COALESCE(D.AttributeMark, 0)::INTEGER & 1
                                                    "hidden",
                CASE
                    WHEN $currDate BETWEEN COALESCE(D.StartDate, 19000101)::DATE AND COALESCE(D.FinishDate, 30000101)::DATE THEN 1
                    ELSE 0
                END                                 "working",
                CASE
                    WHEN D.StartDate = 19000101 THEN null
                    ELSE D.StartDate
                END::date                           "startDate",
                CASE
                    WHEN D.FinishDate = 30000101 THEN null
                    ELSE D.FinishDate
                END::date                           "finishDate",
                D.Intensity                         "intensity",
                CASE WHEN D.Photo IS NOT NULL THEN 1 ELSE 0 END
                                                    "photoExists" -- WS-312
EOT;
} $sql .= <<<EOT
$sqlFromWhere
$union
order by $sort $dir
$sqlPage
EOT;
$root = new stdClass(); $root->total = $total; $root->items = selectSql($db, $sql); return $root; } public function deleteDoctors($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM MedDoctor WHERE MedDoctorCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateDoctors($records) { $db = $this->db ?: $this->db = connectDb(); $resChangeFlag = false; $sqlFio = <<<EOT
            UPDATE man SET
                surName =       CASE WHEN ? = 1 THEN ? ELSE surName END,
                name =          CASE WHEN ? = 1 THEN ? ELSE name END,
                secondName =    CASE WHEN ? = 1 THEN ? ELSE secondName END
            WHERE manCode = ?
EOT;
$sqlDoctor = <<<EOT
            update MedDoctor set
                Cipher =                case when ? = 1 then ? else Cipher end,
                StartDate =             case when ? = 1 then ? else StartDate end,
                FinishDate =            case when ? = 1 then ? else FinishDate end,
                MedProfessionCode =     case when ? = 1 then ? else MedProfessionCode end,
                DepartmentCode =        case when ? = 1 then ? else DepartmentCode end,
                UserCode =              case when ? = 1 then ? else UserCode end,
                Cabinet =               case when ? = 1 then ? else Cabinet end,
                Intensity =             case when ? = 1 then ? else Intensity end,
                MedPatientGroupCode =   case when ? = 1 then ? else MedPatientGroupCode end,
                /* такая вот нехитрая конструкция, для сохранения других атрибутов необходимо добавить еще одну секцию case в */
                /* select через логическое сложение (на postgre скорее всего нужно по другому делать) */
                AttributeMark = case
                    when ? = 1 then
                    (
                        select
                        (
                            case when ? = 1 then COALESCE(AttributeMark, 0)::integer | (1 << 0)
                                else COALESCE(AttributeMark, 0)::integer & ~ (1 << 0)
                                end
                        )
                    )
                    else AttributeMark end
            where MedDoctorCode = ?
EOT;
$sqlResourceChange = <<<EOT
            SELECT PR_syResourceChange( E'medDoctor' )
EOT;
$stmtDoctor = $db->prepare($sqlDoctor); $stmtFio = $db->prepare($sqlFio); $stmtResourceChange = $db->prepare($sqlResourceChange); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (!empty($row->resChangeFlag)) { $resChangeFlag = true; } if (isset($row->fio) && isUndefinedProperty($row, 'fio')) { $aFio = trim($row->fio); $fio = preg_split("/[\s.,]+/", $aFio); $stmtFio->execute([ isset($fio[0]) ? 1 : 0, isset($fio[0]) ? $fio[0] : null, isset($fio[1]) ? 1 : 0, isset($fio[1]) ? $fio[1] : null, isset($fio[2]) ? 1 : 0, isset($fio[2]) ? trim(implode(" ", (array_slice($fio, 2)))) : null, $row->manId ]); unset($fio); } $param = new stdClass(); $param->userId = isEmptyProperty($row, 'userId'); $doctorExist = $this->validationUser($param); $stmtDoctor->execute([ propertyExistsSQL($row, 'cipher'), isUndefinedProperty($row, 'cipher'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'professionId'), isEmptyProperty($row, 'professionId'), propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), !$doctorExist ? propertyExistsSQL($row, 'userId') : 0, isEmptyProperty($row, 'userId'), propertyExistsSQL($row, 'cabinet'), isEmptyProperty($row, 'cabinet'), propertyExistsSQL($row, 'intensity'), isEmptyProperty($row, 'intensity'), propertyExistsSQL($row, 'patientGroupId'), isEmptyProperty($row, 'patientGroupId'), propertyExistsSQL($row, 'hidden'), isEmptyProperty($row, 'hidden'), $row->id ]); } if ($resChangeFlag) { $stmtResourceChange->execute(); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createDoctors($records) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sqlMan = <<<"EOT"
insert into Man
    (SurName, Name, SecondName)
values
    (?, ?, ?)
EOT;
$sqlMaxCipher = <<<"EOT"
select INCSTR(asa_trim((select MAX(RIGHT(SPACE(20) || MD.Cipher, 20))
                    from Branch as Br
                         join Department D on D.BranchCode = BR.BranchCode
                         join MedDoctor MD on MD.DepartmentCode = D.DepartmentCode
                                          and COALESCE(MD.Cipher, '')::text <> ''
                    where Br.EnterpriseCode = $enterpriseCode))) "cipher"
EOT;
$sqlManTask = <<<"EOT"
insert into ManTask
    (ManCode, EnterpriseCode, Cipher, TypeTask)
values
    (?, $enterpriseCode, ?, 11)
EOT;
$sqlDoctor = <<<"EOT"
insert into MedDoctor
    (Cipher, StartDate, FinishDate, MedProfessionCode, ManCode, DepartmentCode, Cabinet, Intensity, AttributeMark, UserCode, MedPatientGroupCode)
values
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmtMan = $db->prepare($sqlMan); $stmtMaxCipher = $db->prepare($sqlMaxCipher); $stmtManTask = $db->prepare($sqlManTask); $stmtDoctor = $db->prepare($sqlDoctor); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $aFio = preg_split("/[\s.,]+/", $row->fio); $stmtMan->execute([ isset($aFio[0]) ? $aFio[0] : null, isset($aFio[1]) ? $aFio[1] : null, isset($aFio[2]) ? trim(implode(" ", (array_slice($aFio, 2)))) : null ]); $manId = $db->lastInsertId('seq_Man'); if (empty($row->cipher)) { $stmtMaxCipher->execute(); $cipher = fetchValue($stmtMaxCipher, 'cipher'); } else { $cipher = $row->cipher; } $stmtManTask->execute([ $manId, $cipher ]); $param = new stdClass(); $param->userId = isEmptyProperty($row, 'userId'); $doctorExist = $this->validationUser($param); $stmtDoctor->execute([ $cipher, isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'finishDate'), isEmptyProperty($row, 'professionId'), $manId, isEmptyProperty($row, 'departmentId'), isEmptyProperty($row, 'cabinet'), isEmptyProperty($row, 'intensity'), isEmptyProperty($row, 'hidden'), !$doctorExist ? isEmptyProperty($row, 'userId') : null, isEmptyProperty($row, 'patientGroupId') ]); $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedDoctor'); $ret = $this->readDoctors($rec); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function readDoctorInfo() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select {$db->sybase('first')}
       md.MedDoctorCode "id",
       fn_cmFio(md.Mancode) "name",
       p.MedProfessionCode "professionId",
       p.Name "professionName",
       d.DepartmentCode "departmentId",
       d.Name "departmentName"
from MedDoctor md
     left outer join MedProfession p on p.MedProfessionCode = md.MedProfessionCode
     join Department d on d.DepartmentCode = md.DepartmentCode
where md.MedDoctorCode = ?
{$db->postgre('limit 1')}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapMedDoctor')['MedDoctorCode'] ]); return $stmt->fetch(); } public function readPhoto($params) { $cache = getSessionValue(self::PHOTO_CACHE); if (isset($cache)) { $raw = base64_encode(file_get_contents($cache)); } else { $db = $this->db ?: $this->db = connectDb(); $raw = selectSqlValue($db, "select encode(photo, 'base64') from MedDoctor where MedDoctorCode = ?", [$params->id]); } header('Content-type: image/jpeg'); header('Cache-Control: max-age=31536000'); echo base64_decode($raw); } public function updatePhoto() { header('Content-type: text/html'); $temp = $_FILES['photo']['tmp_name']; $fileName = $temp . '.doctor.img'; rename($temp, $fileName); setSessionValue(self::PHOTO_CACHE, $fileName); $rec = new stdClass; $rec->success = true; echo json_encode($rec); } public function publishPhotos() { $db = $this->db ?: $this->db = connectDb(); $path = readExtraSettings(null, self::TASK_NAME); $path = $path == '' || $path == '\\' || is_object($path) ? '1' : $path; if (file_exists($path)) { $sql = <<<"EOT"
select
       md.MedDoctorCode "id",
       encode(md.photo, 'base64')  "photo"
from MedDoctor md
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $doctors = $stmt->fetchAll(); $opDir = opendir($path); while ($file = readdir($opDir)) { if ($file != "." && $file != "..") { if (!is_dir($path . $file)) { unlink($path . $file); } } } closedir($opDir); foreach ($doctors as $doctor) { if (!is_null($doctor['photo']) && $doctor['photo'] != "") { $name = $doctor['id']; $file = fopen("$path/$name.jpeg", "w+"); fwrite($file, base64_decode($doctor['photo'])); fclose($file); } } $this->resourceChangeDoctor(); return true; } else { return 'Указанный в настройках путь к каталогу фотографий на сервере не корректный либо такого каталога не существует!'; } } public function validationUser($params) { $db = $this->db ?: $this->db = connectDb(); $userId = isEmptyProperty($params, 'userId', 0); if ($userId != 0) { $sql = <<<EOT
                SELECT
                    md.medDoctorCode            "id",
                    mp.name                     "profession",
                    FN_cmFIO(md.manCode)::TEXT  "fio"
                FROM medDoctor md
                LEFT JOIN medProfession mp ON mp.medProfessionCode = md.medProfessionCode
                WHERE md.userCode = $userId
                LIMIT 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $doctor = $stmt->fetch(); return $doctor; } return null; } public function resetUser($params) { $db = $this->db ?: $this->db = connectDb(); $userId = isEmptyProperty($params, 'userId', 0); $prevDoctorId = isEmptyProperty($params, 'prevDoctorId', 0); $doctorId = isEmptyProperty($params, 'doctorId', 0); $sql = <<<"EOT"
update MedDoctor set
       UserCode =  ?
where MedDoctorCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ null, $prevDoctorId ]); $stmt->execute([ $userId, $doctorId ]); return true; } public function commitPhoto($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id', 0); $action = isEmptyProperty($params, 'action', 'reject'); $f = getSessionValue(self::PHOTO_CACHE); $isCache = !is_null($f); if ($action == "ok") { if ($isCache) { $image = file_get_contents($f); executeSql($db, "update MedDoctor set photo = decode(?, 'base64') where MedDoctorCode = ?", [base64_encode($image), $id] ); } } if ($isCache) { unlink($f); setSessionValue(self::PHOTO_CACHE, null); } return true; } public function deletePhoto($params) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update MedDoctor set
       photo =  decode(null, 'base64')
where MedDoctorCode = ?
EOT;
executeSql($db, $sql, [ isEmptyProperty($params, 'id', 0) ]); return true; } public function resourceChangeDoctor($params = null) { $db = $this->db ?: $this->db = connectDb(); $date = 19000101; $sql = <<<"EOT"
select 1
from MedDoctor
where photoTime = $date
limit 1
EOT;
$sqlSetFlag = <<<"EOT"
insert into SysResourceChange
    (changeTime, name)
values
    (LOCALTIMESTAMP, ?::text)
on conflict (name) do update set
    changeTime = LOCALTIMESTAMP
EOT;
$sqlUpdate = <<<"EOT"
update MedDoctor set
       photoTime = LOCALTIMESTAMP
where photoTime = $date
EOT;
if (!empty(selectSqlValue($db, $sql))) { $db->beginTransaction(); try { executeSql($db, $sqlUpdate); executeSql($db, $sqlSetFlag, ['medDoctorPhoto']); executeSql($db, $sqlSetFlag, ['medDoctor']); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } } return true; } public function getNextCipher($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
SELECT
    INCSTR(
        ASA_TRIM(
            (
                SELECT
                    MAX(RIGHT(SPACE(20) || MD.Cipher, 20))
                FROM
                    Branch AS BR
                    JOIN Department D ON D.BranchCode = BR.BranchCode
                    JOIN MedDoctor MD ON MD.DepartmentCode = D.DepartmentCode AND COALESCE(MD.Cipher, '')::text <> ''
                WHERE
                    BR.EnterpriseCode = ?
            )
        )
    ) "cipher"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$enterpriseCode]); $cipher = fetchValue($stmt, 'cipher'); return $cipher; } public function checkCipher($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $cipher = isEmptyProperty($params, 'cipher'); if (empty($cipher)) { return false; } $sql = <<<"EOT"
SELECT
    COUNT(MD.Cipher::integer) "count"
FROM
    MedDoctor MD
WHERE
    (MD.Cipher = ?)
EOT;
$data = [ $cipher ]; if (!empty($id)) { $sql .= ' AND (MD.MedDoctorCode <> ?)'; $data[] = $id; } $stmt = $db->prepare($sql); $stmt->execute($data); $count = fetchValue($stmt, 'count'); return ($count == 0); } } handleJsonRpc('med_common_doctors');