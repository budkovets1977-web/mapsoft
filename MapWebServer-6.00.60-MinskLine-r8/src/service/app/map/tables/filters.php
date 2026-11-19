<?php
 require_once(__DIR__.'/../../init.php'); class map_tables_filters { private $db; public function readYears () { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select year(ChangeDate) "year"
from PBPeriod
where EnterpriseCode = ?
order by 1 desc {$db->postgre("NULLS LAST")}
EOT;
$stmt = $db->prepare($sql); $enterprise = getSessionValue('mapEnterpriseParentCode'); $stmt->execute([$enterprise]); $result = $stmt->fetchAll(); $curYear = date('Y'); if (empty($result) || (string)reset($result)['year'] < $curYear) { executeSql($db, 'insert into PBPeriod (EnterpriseCode, ChangeDate, Name) values (?, ?, ?)', [$enterprise, $curYear.'-01-01', $curYear] ); array_unshift($result, ['year' => $curYear]); } return $result; } public function readTables ($params) { $db = $this->db ?: $this->db = connectDb(); $genTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $enterpriseCode = (int) getSessionValue('mapEnterpriseCode'); $enterpriseParentCode = (int) getSessionValue('mapEnterpriseParentCode'); $userEnterpriseCodes = getSessionValue('mapUserEnterpriseCodes') ? : 'null'; $all = (int) selectSqlValue($db, "select fn_WebUserAccess('map-tables-reports-all-organizations')"); $tableType = empty($params->isReports) ? 1 : 0; $period = isEmptyProperty($params, "period", "19000101"); $sqlIsParent = $enterpriseCode === $enterpriseParentCode ? '' : 'and (coalesce(t.Flags, 0) & 1) = 0'; if ($tableType === 0) { $hex = str_pad(dechex($enterpriseCode), 8, '0', STR_PAD_LEFT); $sql = <<<"EOT"
select
    t.GenTableCode "id",
    t.Label "name",
    t.GenTableGroupCode "groupId",
    tg.Name "groupName",
    t.Position
from GenTable t
left outer join GenTableGroup tg on tg.GenTableGroupCode = t.GenTableGroupCode
where t.GenTableSetCode = $genTableSetCode
    and coalesce(t.TableType, 0) = 0
    $sqlIsParent
    and exists(
        select {$db->sybase("first")} 1
        from GenTableConfig
        where GenTableCode = t.GenTableCode
            and EnterpriseCode in (
                select EnterpriseCode from Enterprise where EnterpriseCode = $enterpriseCode
                union all
                select EnterpriseCode from Enterprise where $all <> 0
                    and fn_TreePath('Enterprise', 'EnterpriseParentCode', EnterpriseParentCode) like '%{$hex}%'
                    and ('{$userEnterpriseCodes}' = 'null' or ('{$userEnterpriseCodes}' <> 'null' and EnterpriseCode in ({$userEnterpriseCodes})))
            )
            and FinishDate >= '{$params->fromDate}' and StartDate <= '{$params->toDate}'
        {$db->postgre("limit 1")}
    )
order by 4, 5
EOT;
} else { $sql = $db->isSybase ? <<<"EOT"
select
    tt.GenTableCode "id",
    tt.Label "name",
    tt.GenTableGroupCode "groupId",
    tt.groupName "groupName"
from (
    select
        t.GenTableCode,
        t.Label,
        t.GenTableGroupCode,
        tg.Name "groupName",
        '{$period}' "period",
        cast(if t.periodicity = 0 then DateLastOfThisMonth("period")
            else DateLastOfThisMonth(ymd(year("period"), n.SysNumberCode * t.periodicity, 1))
            endif as date) "toDate"
    from GenTable t
    left outer join GenTableGroup tg on tg.GenTableGroupCode = t.GenTableGroupCode
    left outer join SysNumber n on
            // для "без периода" (0) получим одну запись, для остальных столько сколько отчетов в году
            n.SysNumberCode between 1 and 12 / coalesce(nullif(t.Periodicity, 0), 12)
    where t.GenTableSetCode = {$genTableSetCode}
        and coalesce(t.TableType, 0) = 1
        $sqlIsParent
        and "toDate" between '{$params->fromDate}' and '{$params->toDate}'
    order by tg.Name, t.Position
) tt
group by tt.GenTableCode, tt.Label, tt.GenTableGroupCode, tt.groupName
order by 4, 2
EOT
: <<<"EOT"
select
  tt.gentablecode as "id",
  tt.label as "name",
  tt.gentablegroupcode as "groupId",
  tt.groupname as "groupName"
from (select
         t.GenTableCode as "gentablecode",
         t.Label as "label",
         t.GenTableGroupCode as "gentablegroupcode",
         tg.Name as "groupname",
         E'{$period}' as "period",
         t.Position as "position",
         cast(case
                when t.periodicity = 0 then DateLastOfThisMonth(E'{$period}')
                else DateLastOfThisMonth(ymd(year(E'{$period}'), n.SysNumberCode * t.periodicity, 1))
              end as date) as "todate"
      from GenTable t
           left outer join GenTableGroup tg on tg.GenTableGroupCode = t.GenTableGroupCode
           left outer join SysNumber n on n.SysNumberCode between 1 and 12 / coalesce(nullif(t.Periodicity, 0), 12)
      where t.GenTableSetCode = {$genTableSetCode}
            and coalesce(t.TableType, 0) = 1
            $sqlIsParent
            and cast (case
                        when t.periodicity = 0 then DateLastOfThisMonth(E'{$period}')
                        else DateLastOfThisMonth(ymd(year(E'{$period}'), n.SysNumberCode * t.periodicity, 1))
                      end as date) between E'{$params->fromDate}'
            and E'{$params->toDate}'
      order by 4, 6) as tt
group by tt.genTableCode, tt.label, tt.genTableGroupCode, tt.groupName
order by 4, 2
EOT;
} $stmt = $db->prepare($sql); $stmt->execute(); $children = []; $group = null; while ($row = $stmt->fetch()) { if (!$group || $group->groupId !== (int)$row['groupId']) { $children[] = $group = new stdClass; $group->id = 'g.'.$row['id']; $group->groupId = (int)$row['groupId']; $group->text = $row['groupName'] ?: 'Без группы'; $group->expanded = true; $group->type = 'group'; } $group->children[] = $table = new stdClass; $table->id = (string)$row['id']; $table->text = $row['name']; $table->leaf = true; $table->type = 'table'; } return ['children' => $children]; } public function readEnterprises ($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseCode = getSessionValue('mapEnterpriseCode'); $userEnterpriseCodes = getSessionValue('mapUserEnterpriseCodes') ?: 'null'; $fromConfigurations = isUndefinedProperty($params, 'fromConfigurations', false); if ($fromConfigurations) { $all = (int) selectSqlValue($db, "select fn_WebUserAccess('map-tables-configurations-all-organizations')"); } else { $all = (int) selectSqlValue($db, "select fn_WebUserAccess('map-tables-reports-all-organizations')"); } $part = !$fromConfigurations ? "and (
    select {$db->sybase('first')} 1
    from GenTableConfig
    where EnterpriseCode = e.EnterpriseCode and FinishDate >= ? and StartDate <= ?
    {$db->postgre('limit 1')}
) = 1" : ""; $hex = str_pad(dechex($enterpriseCode), 8, '0', STR_PAD_LEFT); $sql = <<<"EOT"
select
    e.EnterpriseCode "id",
    e.Name "name"
from Enterprise e
where ((? = 0 and e.EnterpriseCode = ?)
    or (? = 1
        and (e.EnterpriseCode = ? or fn_TreePath('Enterprise', 'EnterpriseParentCode', e.EnterpriseParentCode) like '%{$hex}%')
        and (? = 'null' or (? <> 'null' and e.EnterpriseCode in ({$userEnterpriseCodes})))
    )
) {$part}
order by {$db->dialect("if e.EnterpriseCode = $enterpriseCode then 0 else 1 endif", "case when e.EnterpriseCode = $enterpriseCode then 0 else 1 end")}, 2
EOT;
$stmt = $db->prepare($sql); if (!$fromConfigurations) { $stmt->execute([ $all, $enterpriseCode, $all, $enterpriseCode, (string) $userEnterpriseCodes, (string) $userEnterpriseCodes, $params->fromDate, $params->toDate ]); } else { $stmt->execute([ $all, $enterpriseCode, $all, $enterpriseCode, (string) $userEnterpriseCodes, (string) $userEnterpriseCodes ]); } $rows = $stmt->fetchAll(); if ($fromConfigurations) { return $rows; } if (count($rows) === 1) { return $rows; } return array_merge([['id' => 0, 'name' => '< Все >']], $rows); } public function readAnalytics ($params) { $db = $this->db ?: $this->db = connectDb(); $enterpriseParentCode = getSessionValue('mapEnterpriseParentCode'); $period = isEmptyProperty($params, 'period', '1900-01-01'); $toDate = isEmptyProperty($params, 'toDate', '1900-01-01'); $tables = isEmptyProperty($params, 'tables', 'null'); $sql = $db->isSybase ? <<<"EOT"
select distinct
    (select
        '{' || list(
            map.JSON_Object(ca.GenTableAnalyticsCode,
                                               map.JSON_Numeric('id', ca.GenTableDictDataCode) ||
                                               map.JSON_String('text', dd.Value) ||
                map.JSON_String('filter', a.FilterName, 0)
            , 0)
        ) || '}'
        from GenTableConfigAnalytics ca
            join GenTableAnalytics a on a.GenTableAnalyticsCode = ca.GenTableAnalyticsCode
            join GenTableDictData dd on dd.GenTableDictDataCode = ca.GenTableDictDataCode
        where ca.GenTableConfigCode = tt.GenTableConfigCode
            and a.EnterpriseCode = ?
            and a.GenTableSetCode = tt.GenTableSetCode
    ) "analytics"
from (
    select
        pt.GenTableSetCode,
        tc.GenTableConfigCode
    from GenTable pt
    join GenTableVersion tv on tv.GenTableCode = pt.GenTableCode
        and tv.OnDate = (
            select max(OnDate)
            from GenTableVersion
            where GenTableCode = tv.GenTableCode
            and OnDate <= '{$toDate}')
    join GenTable ft on ft.GenTableSetCode = pt.GenTableSetCode
        and charindex('"' || ft.Name || '"', tv.ForeignTables) > 0
        and ft.GenTableCode <> tv.GenTableCode
    join GenTableDataSet tds on tds.GenTableCode = ft.GenTableCode
        and coalesce(tds.Ready, 0) = 1
        and tds.PeriodDate = '{$period}'
        and (coalesce(ft.Periodicity,0) = 0
            or (tds.ChangeDate between DateFirstOfThisMonth('{$toDate}') and DateLastOfThisMonth('{$toDate}'))
            or (coalesce(pt.Periodicity,0) > 1
                and coalesce(ft.Periodicity,0) = 1
                and tds.ChangeDate between ymd(year('{$toDate}'), 1, 1) and DateLastOfThisMonth('{$toDate}'))
            )
    join GenTableConfig tc on tc.GenTableConfigCode = tds.GenTableConfigCode
    where pt.GenTableCode in ({$tables})
    group by pt.GenTableSetCode, tc.GenTableConfigCode
) tt
where analytics <> '{}'
EOT
: <<<"EOT"
select distinct
	(select
     	E'{' || string_agg((map.JSON_Object(ca.GenTableAnalyticsCode,
                                        map.JSON_Numeric(E'id', (ca.GenTableDictDataCode)::numeric) ||
                                        map.JSON_String(E'text', dd.Value) ||
                                        map.JSON_String(E'filter', a.FilterName, 0), 0))::text, ',') || E'}'
     from GenTableConfigAnalytics ca
          join GenTableAnalytics a on a.GenTableAnalyticsCode = ca.GenTableAnalyticsCode
          join GenTableDictData dd on dd.GenTableDictDataCode = ca.GenTableDictDataCode
     where ca.GenTableConfigCode = tt.GenTableConfigCode
           and a.EnterpriseCode = ? /*0*/
           and a.GenTableSetCode = tt.GenTableSetCode) as "analytics"
from (select
         pt.GenTableSetCode,
         tc.GenTableConfigCode
      from GenTable pt
            join GenTableVersion tv on tv.GenTableCode = pt.GenTableCode
                 and tv.OnDate = (select
					                 max (OnDate)
                                  from GenTableVersion
                                  where GenTableCode = tv.GenTableCode
                                        and OnDate <= (E'{$toDate}'))
            join GenTable ft on ft.GenTableSetCode = pt.GenTableSetCode
            	 and charindex(E'"' || ft.Name || E'"', tv.ForeignTables) > 0
                 and ft.GenTableCode <> (tv.GenTableCode)
            join GenTableDataSet tds on tds.GenTableCode = ft.GenTableCode
            	 and coalesce(tds.Ready, 0) = 1
                 and tds.PeriodDate = E'{$period}'
                 and (coalesce(ft.Periodicity, 0) = 0 or (tds.ChangeDate between DateFirstOfThisMonth(E'{$toDate}') and DateLastOfThisMonth(E'{$toDate}'))
                                                      or (coalesce (pt.Periodicity, 0) > 1
                                                          and coalesce (ft.Periodicity, 0) = 1
                                                          and tds.ChangeDate between ymd(year(E'{$toDate}'), 1, 1) and DateLastOfThisMonth(E'{$toDate}')))
            join GenTableConfig tc on tc.GenTableConfigCode = tds.GenTableConfigCode
       where pt.GenTableCode in ({$tables})
       group by pt.GenTableSetCode,
                tc.GenTableConfigCode) tt
where (select
          E'{' || string_agg((map.JSON_Object(ca.GenTableAnalyticsCode,
                                          map.JSON_Numeric(E'id', (ca.GenTableDictDataCode)::numeric) ||
                                          map.JSON_String(E'text', dd.Value) ||
                                          map.JSON_String(E'filter', a.FilterName, 0), 0))::text, ',') || E'}'
        from GenTableConfigAnalytics ca
             join GenTableAnalytics a on a.GenTableAnalyticsCode = ca.GenTableAnalyticsCode
             join GenTableDictData dd on dd.GenTableDictDataCode = ca.GenTableDictDataCode
        where ca.GenTableConfigCode = tt.GenTableConfigCode
              and a.EnterpriseCode = ? /*1=0*/
              and a.GenTableSetCode = tt.GenTableSetCode) <> (E'{}');

EOT;
$stmt = $db->prepare($sql); $db->isSybase ? $stmt->execute([ $enterpriseParentCode ]) : $stmt->execute([ $enterpriseParentCode, $enterpriseParentCode ]); $ret = []; while($row = $stmt->fetch()) { $row['analytics'] = safe_json_decode($row['analytics']); $ret[] = $row; } return $ret; } public function readTableGroups ($params) { $db = $this->db ?: $this->db = connectDb(); $genTableSetCode = (int) getSessionValue('mapGenTableSetCode'); $tableType = empty($params->isReports) ? 0 : 1; $sql = <<<"EOT"
select
  tg.GenTableGroupCode "id",
  tg.Name "name"
from GenTableGroup tg
where exists (
  select 1 from GenTable
  where GenTableGroupCode = tg.GenTableGroupCode
    and GenTableSetCode = ?
    and coalesce(TableType, 0) = ?)
order by 2
EOT;
return selectSql($db, $sql, [ $genTableSetCode, $tableType ]); } } if (!isset($notExecute)) { $service = new Junior\Server(new map_tables_filters()); $service->process(); }