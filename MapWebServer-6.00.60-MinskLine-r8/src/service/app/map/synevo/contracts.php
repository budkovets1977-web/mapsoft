<?php
 require_once(__DIR__ . '/../../init.php'); class map_synevo_contracts { private $db; public function readContracts($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseId = isEmptyProperty($params, 'enterpriseId') ? isEmptyProperty($params, 'enterpriseId') : getSessionValue('mapEnterpriseCode'); $enterpriseStructureId = isEmptyProperty($params, 'enterpriseStructureId', 0); $isActive = isEmptyProperty($params, 'isActive'); $archive = isUndefinedProperty($params, 'archive'); $sqlWhere = ''; if ($isActive === 2) { $sqlWhere .= " and now() between sc.startDate and sc.finishDate"; } if ($archive === true) { $sqlWhere .= " and now()::date > sc.finishDate"; } if ($archive === false) { $sqlWhere .= " and now()::date between sc.startDate and sc.finishDate"; } if ($enterpriseId) { $sqlWhere .= " and sc.enterpriseCode = $enterpriseId"; } if (isset($params->enterpriseStructureId)) { $sqlWhere .= " and sc.enterpriseStructureCode = $enterpriseStructureId"; } $sql = <<< "EOT"
    SELECT
        sc.synevoContractCode "id",
        sc.enterpriseCode "enterpriseId",
        sc.enterpriseStructureCode "enterpriseStructureId",
        e.name "enterpriseName",
        sc.finishDate "finishDate",
        sc.id "contractId",
        sc.name "contractName",
        sc.startDate "startDate",
        sc.password "password",
        sc.username "username"
    FROM SynevoContract sc
    LEFT JOIN Enterprise e on e.enterprisecode = sc.enterprisecode
    WHERE 1=1 $sqlWhere
    order by 8 desc, 5 asc
EOT;
return selectSql($db, $sql); } public function createContracts($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<< "EOT"
    INSERT INTO SynevoContract (enterpriseCode, enterpriseStructureCode, finishDate, id, name, startDate, password, username)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'enterpriseId'), isUndefinedProperty($row, 'enterpriseStructureId'), isUndefinedProperty($row, 'finishDate'), isUndefinedProperty($row, 'contractId'), isUndefinedProperty($row, 'contractName'), isUndefinedProperty($row, 'startDate'), isUndefinedProperty($row, 'password'), isUndefinedProperty($row, 'username') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_SynevoContract'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateContracts($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
    update SynevoContract set
        enterpriseCode = case when ? = 1 then ? else enterpriseCode end,
        enterpriseStructureCode = case when ? = 1 then ? else enterpriseStructureCode end,
           finishDate = case when ? = 1 then ? else finishDate end,
           id = case when ? = 1 then ? else id end,
           name = case when ? = 1 then ? else name end,
           startDate = case when ? = 1 then ? else startDate end,
           password = case when ? = 1 then ? else password end,
           username = case when ? = 1 then ? else username end
    where SynevoContractCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'enterpriseId'), isUndefinedProperty($row, 'enterpriseId'), propertyExistsSQL($row, 'enterpriseStructureId'), isUndefinedProperty($row, 'enterpriseStructureId'), propertyExistsSQL($row, 'finishDate'), isUndefinedProperty($row, 'finishDate'), propertyExistsSQL($row, 'contractId'), isUndefinedProperty($row, 'contractId'), propertyExistsSQL($row, 'contractName'), isUndefinedProperty($row, 'contractName'), propertyExistsSQL($row, 'startDate'), isUndefinedProperty($row, 'startDate'), propertyExistsSQL($row, 'password'), isUndefinedProperty($row, 'password'), propertyExistsSQL($row, 'username'), isUndefinedProperty($row, 'username'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function checkIntersection($params) { $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $id = isEmptyProperty($params, 'id', 0); $whereStructure = ''; $enterpriseId = isEmptyProperty($params, 'enterpriseId') ? isEmptyProperty($params, 'enterpriseId') : getSessionValue('mapEnterpriseCode'); $enterpriseStructureId = isEmptyProperty($params, 'enterpriseStructureId'); if (isset($params->enterpriseStructureId)) { $whereStructure = ' and sc.enterpriseStructureCode = '.$params->enterpriseStructureId; } $sql = <<< "EOT"
    SELECT
        sc.finishDate "finishDate",
        sc.id "contractId",
        sc.startDate "startDate"
    FROM SynevoContract sc
    WHERE sc.enterpriseCode = $enterpriseId $whereStructure and sc.synevoContractCode <> $id
        and (($startDate between sc.startDate and sc. finishDate) or ($finishDate between sc.startDate and sc. finishDate))
    limit 1
EOT;
$ret = selectSql($db, $sql); return empty($ret) ? null : $ret[0]; } public function getDefaultParams($params) { $db = $this->db ?: $this->db = connectDb(); $startDate = isEmptyProperty($params, 'startDate'); $finishDate = isEmptyProperty($params, 'finishDate'); $enterpriseId = isEmptyProperty($params, 'enterpriseId'); $sql = <<< "EOT"
    SELECT
        sc.username "username",
        sc.name "contractName",
        sc.password "password"
    FROM SynevoContract sc
    order by sc.SynevoContractCode desc
    limit 1
EOT;
$ret = selectSql($db, $sql); return empty($ret) ? null : $ret[0]; } public function deleteContracts($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'SynevoContract', 'SynevoContractCode', $records); } } handleJsonRpc('map_synevo_contracts'); 