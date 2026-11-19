<?php
require_once(__DIR__ . '/../../init.php'); class med_common_nurses { private $db; const MAX_PAGE_SIZE = 'NULL'; public function readNurses($params = null) { $db = $this->db ? : $this->db = connectDb(); $currDate = Date("Ymd"); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $sort = $this->getSort(isEmptyProperty($params, 'sort')); $start = isUndefinedProperty($params, 'start', 0); $condSearch = ""; if (isEmptyProperty($params, 'loadOneWorker', 0)) { $condSearch = " and MOL.BuhMolCode = " . isEmptyProperty($params, 'id', 0); } else { if (isset($params->search) && isEmptyProperty($params, 'search') && $params->search !== "") { $condSearch = getSearchManSqlCondition($params->search); $condSearchEx = getSearchSqlCondition($params->search, ["D.Name", "B.Name", "MOL.Cipher"]); $condSearch = "and (($condSearch) or $condSearchEx)"; } $btw = "between COALESCE(MOL.StartDate, 19000101) and COALESCE(MOL.FinishDate, 30000101)"; switch (isUndefinedProperty($params, 'working', 0)) { case 1: $condSearch .= " and $currDate $btw"; break; case 2: $condSearch .= " and $currDate not $btw"; break; } } $sqlFromWhere = <<<"EOT"
from Branch AS B
     JOIN Department AS D ON D.BranchCode = B.BranchCode
        and fn_WebUserAccessDepartment(d.DepartmentCode) = 1
     JOIN buhMol AS MOL ON MOL.DepartmentCode = D.DepartmentCode
     LEFT JOIN EnterpriseStructure as ES on ES.DepartmentCodeWeb = D.DepartmentCode
     LEFT OUTER JOIN Man AS M ON M.ManCode = MOL.ManCode
     /*пользователя берём либо из самой таблицы медперсонала, либо по связи в пользователем МАП из WebUser.
      После синхронизации id пользователя запишется в таблицу медперсонала.*/
     LEFT OUTER JOIN WebUser AS WU ON wu.WebUserCode = COALESCE(MOL.WebUserCode,
        (select
                wus.webUserCode
         from webuser wus
              left join WebEnterprise as WE on WE.webEnterpriseCode = wus.webEnterpriseCode
         where wus.UserCode = MOL.usercode and we.enterpriseCode = $enterpriseCode
         order by 1 limit 1)
        )
    WHERE 1 = 1
    $condSearch
EOT;
$limit = isEmptyProperty($params, 'limit', self::MAX_PAGE_SIZE); $sqlTotal = <<<"EOT"
SELECT
       COUNT(*) "total"
FROM (SELECT
    *
    $sqlFromWhere
    ) AS t
EOT;
$total = (int) selectSqlValue($db, $sqlTotal); $sql = <<< "EOT"
SELECT
    MOL.buhMolCode "id",
    MOL.DepartmentCode "departmentId",
    COALESCE(FN_cmDepartmentName(ES.DepartmentCodeWeb,1)::text, D.Name) "department",
    MOL.StartDate "startDate",
    CASE
        WHEN MOL.FinishDate = 30000101 THEN null
        ELSE MOL.FinishDate
    END::date "finishDate",
    MOL.ManCode as "manId",
    fn_cmFio(MOL.ManCode) as "fio",
    MOL.Cipher as "cipher",
    MOL.UserCode "userId",
    wu.WebUserCode "webUserId",
    WU.Nick "user"
    $sqlFromWhere
ORDER BY $sort
limit $limit offset $start
EOT;
$root = new stdClass(); $root->total = $total; $root->items = selectSql($db, $sql); return $root; } public function createNurses($records) { $db = $this->db ? : $this->db = connectDb(); $sqlInsertMan = <<< "EOT"
    INSERT INTO Man (SurName, Name, SecondName)
    values (?, ?, ?)
EOT;
$sqlInsert = <<< "EOT"
    INSERT INTO BuhMol (manCode, departmentCode, userCode, webUserCode, cipher, startDate, finishDate)
    values (?, ?, ?, ?, ?, ?, coalesce(?,'30000101')::date)
EOT;
$stmtMan = $db->prepare($sqlInsertMan); $stmt = $db->prepare($sqlInsert); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $aFio = preg_split("/[\s.,]+/", $row->fio); $stmtMan->execute([ isset($aFio[0]) ? $aFio[0] : null, isset($aFio[1]) ? $aFio[1] : null, isset($aFio[2]) ? trim(implode(" ", (array_slice($aFio, 2)))) : null ]); $manId = $db->lastInsertId('seq_Man'); $stmt->execute([ $manId, isEmptyProperty($row, 'departmentId'), isEmptyProperty($row, 'userId'), isEmptyProperty($row, 'webUserId'), isEmptyProperty($row, 'cipher'), isEmptyProperty($row, 'startDate'), isEmptyProperty($row, 'finishDate') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_BuhMol'); $rec->manId = $manId; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateNurses($records) { $db = $this->db ? : $this->db = connectDb(); $sqlFio = <<<"EOT"
update Man set
    SurName = case when ? = 1 then ? else SurName end,
    Name = case when ? = 1 then ? else Name end,
    SecondName = case when ? = 1 then ? else SecondName end
where ManCode = ?
EOT;
$sqlNurses = <<< "EOT"
update BuhMol set
    departmentCode = case when ? = 1 then ? else departmentCode end,
    userCode = case when ? = 1 then ? else userCode end,
    webUserCode = case when ? = 1 then ? else webUserCode end,
    cipher = case when ? = 1 then ? else cipher end,
    startDate = case when ? = 1 then ? else startDate end,
    finishDate = case when ? = 1 then coalesce(?,'30000101')::date else finishDate end
where buhMolCode = ?
EOT;
$stmtMan = $db->prepare($sqlFio); $stmt = $db->prepare($sqlNurses); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { if (isset($row->fio) && isUndefinedProperty($row, 'fio')) { $aFio = trim($row->fio); $fio = preg_split("/[\s.,]+/", $aFio); $stmtMan->execute([ isset($fio[0]) ? 1 : 0, isset($fio[0]) ? $fio[0] : null, isset($fio[1]) ? 1 : 0, isset($fio[1]) ? $fio[1] : null, isset($fio[2]) ? 1 : 0, isset($fio[2]) ? trim(implode(" ", (array_slice($fio, 2)))) : null, $row->manId ]); unset($fio); } $stmt->execute([ propertyExistsSQL($row, 'departmentId'), isEmptyProperty($row, 'departmentId'), propertyExistsSQL($row, 'userId'), isEmptyProperty($row, 'userId'), propertyExistsSQL($row, 'webUserId'), isEmptyProperty($row, 'webUserId'), propertyExistsSQL($row, 'cipher'), isEmptyProperty($row, 'cipher'), propertyExistsSQL($row, 'startDate'), isEmptyProperty($row, 'startDate'), propertyExistsSQL($row, 'finishDate'), isEmptyProperty($row, 'finishDate'), isEmptyProperty($row, 'id') ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteNurses($records) { return deleteSql($this->db, 'BuhMol', 'BuhMolCode', $records) && deleteSql($this->db, 'Man', 'ManCode', $records, 'manId'); } public function getNextCipher() { $db = $this->db ? : $this->db = connectDb(); $sql = <<< "EOT"
    select
        incstr(cipher)
    from BuhMol
    order by buhMolCode DESC
    limit 1
EOT;
return selectSql($db, $sql)[0]['incstr']; } private function getSort($sortstr) { if (isset($sortstr)) { $sorters = safe_json_decode($sortstr, false); $sb = array(); foreach ($sorters as $sort) { switch ($sort->property) { case "cipher": $sb[] = "RIGHT(REPEAT( E' ' ,20)+asa_trim(MOL.Cipher),20) $sort->direction"; break; case "startDate": $sb[] = "MOL.startDate $sort->direction"; break; case "finishDate": $sb[] = "MOL.finishDate $sort->direction"; break; default: $sb[] = "$sort->property $sort->direction"; } } return implode(', ', $sb); } else { return '1'; } } } handleJsonRpc('med_common_nurses');