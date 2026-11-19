<?php
 require_once(__DIR__.'/../../init.php'); class map_tables_summary { private $db; public function readSummary ($params) { $db = $this->db ?: $this->db = connectDb(); $setCode = (int) getSessionValue('mapGenTableSetCode'); $tables = isEmptyProperty($params, 'tables', '0'); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $userEnterpriseCodes = getSessionValue('mapUserEnterpriseCodes') ?: 'null'; $all = (int) selectSqlValue($db, "select fn_WebUserAccess('map-tables-reports-all-organizations')"); $sqlAnalytics = ''; $filterAnalytics = isUndefinedProperty($params, 'analytics'); if ($filterAnalytics && is_array($filterAnalytics)) { foreach ($filterAnalytics as $group) { $ids = $group->id; $ds = $group->data; $str1 = ""; for ($i = 0, $c = count($ids); $i < $c; $i++) { $or = $i > 0 ? "or " : ""; $anIds = $ids[$i]; $anValues = $ds[$i]; $str2 = ""; for ($j = 0, $cc = count($anIds); $j < $cc; $j++) { $and = $j > 0 ? "and " : ""; $id = $anIds[$j] === null ? "null" : $anIds[$j]; $value = $anValues[$j] === null ? "null" : $anValues[$j]; $str2 .= <<<"EOT"
            {$and} exists (select {$db->sybase("first")} 1 from GenTableConfigAnalytics
                where GenTableConfigCode = tds.GenTableConfigCode
                    and GenTableAnalyticsCode = {$id}
                    and GenTableDictDataCode = {$value}
                        {$db->postgre("limit 1")})
EOT;
} $str1 .= <<<"EOT"
        {$or} ({$str2})
EOT;
} $sqlAnalytics .= <<<"EOT"
    and ({$str1})
EOT;
} } $hex = str_pad(dechex($enterpriseCode), 8, '0', STR_PAD_LEFT); $sql = <<<"EOT"
select
    e.EnterpriseCode "enterpriseId",
    e.Name "enterpriseName"
from GenTableVersion tv
join GenTable pt on pt.GenTableCode = tv.GenTableCode
     and coalesce(pt.TableType, 0) = 1
join GenTable ft on ft.GenTableSetCode = {$setCode}
    and charindex({$db->postgre("E")}'"' || ft.Name || {$db->postgre("E")}'"', tv.ForeignTables) > 0
    and ft.GenTableCode <> tv.GenTableCode
join GenTableDataSet tds on tds.GenTableCode = ft.GenTableCode
    and coalesce(tds.Ready, 0) = 1
    and tds.PeriodDate = ?
    and tds.GenTableConfigCode is not null
    /* фильтруем кривые таблицы без периода (должны быть первого числа периода) */
    and (coalesce(ft.Periodicity,0) <> 0 or tds.ChangeDate = tds.PeriodDate)
    and (coalesce(ft.Periodicity,0) = 0
        /* or (tds.ChangeDate between DateFirstOfThisMonth(<знак вопроса(воспринимается постгри как параметр даже в комментариях)>) and DateLastOfThisMonth(<знак вопроса>)) */
        or tds.ChangeDate = ?
        )
join Enterprise e on e.EnterpriseCode = tds.EnterpriseCode
where tv.GenTableCode in ({$tables})
    and tv.OnDate = (
        select max(OnDate)
        from GenTableVersion
        where GenTableCode = tv.GenTableCode
        and OnDate <= ?)
    and ( (? = 0 and e.EnterpriseCode = ?) or                                                                                                                    --21854
          (? = 1 and (e.EnterpriseCode = ? or fn_TreePath('Enterprise', 'EnterpriseParentCode', e.EnterpriseParentCode) like '%{$hex}%')
                 and (? = 'null' or (? <> 'null' and e.EnterpriseCode in ({$userEnterpriseCodes}) ) ) ) )   --21854

{$sqlAnalytics}
group by e.EnterpriseCode, e.Name
order by 2
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->period, $params->toDate, $params->toDate, $all, $enterpriseCode, $all, $enterpriseCode, (string)$userEnterpriseCodes, (string)$userEnterpriseCodes, ]); return $stmt->fetchAll(); } } $service = new Junior\Server(new map_tables_summary()); $service->process(); 