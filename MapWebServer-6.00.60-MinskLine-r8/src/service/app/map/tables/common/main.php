<?php
 require_once(__DIR__.'/../../common/main.php'); $serviceClass = 'map_tables_common_main'; class map_tables_common_main extends map_common_main { public function changeApp () { $ret = parent::changeApp(); $db = $this->db ?: $this->db = connectDb(); $tableSet = isset(getSessionValue('appConfig')['tableSet']) ? getSessionValue('appConfig')['tableSet'] : ''; setSessionValue('mapGenTableSetCode', selectSqlValue($db, "select {$db->sybase('first')} GenTableSetCode id
from GenTableSet where Name = ? {$db->postgre('limit 1')}", [$tableSet] )); setSessionValue('mapEnterpriseParentCode', selectSqlValue($db, 'select
    coalesce(e3.EnterpriseCode, e2.EnterpriseCode, e1.EnterpriseCode, e.EnterpriseCode) "id"
from
    Enterprise e
    left outer join Enterprise e1 on e1.EnterpriseCode = e.EnterpriseParentCode
    left outer join Enterprise e2 on e2.EnterpriseCode = e1.EnterpriseParentCode
    left outer join Enterprise e3 on e3.EnterpriseCode = e2.EnterpriseParentCode
where
    e.EnterpriseCode = ?', [getSessionValue('mapEnterpriseCode')] )); $ret->access = array_merge( isset($ret->access) ? $ret->access : [], getUserAccess('map-tables-reports-close')); return $ret; } } 