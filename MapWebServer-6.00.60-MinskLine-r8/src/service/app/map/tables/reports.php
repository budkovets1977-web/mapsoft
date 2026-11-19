<?php
 require_once(__DIR__.'/engine/engine.php'); class map_tables_reports { private $db; public function readReports ($params) { $db = $this->db ?: $this->db = connectDb(); $enterprises = $params->enterprises ?: 'null'; $tables = $params->tables ?: 'null'; $sql = $db->isSybase ? <<<"EOT"
select
    tc.GenTableConfigCode "configId",
    tg.GenTableGroupCode "groupId",
    tg.Name "groupName",
    t.GenTableCode "tableId",
    t.Label "tableName",
    t.ReportName "reportName",
    e.EnterpriseCode "enterpriseId",
    e.Name "enterpriseName",
    cast(? as date) "period",
    coalesce(t.Periodicity, 0) "periodicity",
    n.SysNumberCode "periodNumber",
    case periodicity
        when 0 then 'Общий'
        when 1 then FN_cmMonthName(periodNumber, 1, 0)
        when 3 then periodNumber || ' квартал'
        when 6 then periodNumber || ' полугодие'
        when 12 then 'Год'
        else 'Общий'
    end "periodName",
    cast(if periodicity = 0 then period // начало периода
                        else ymd(year(period), (n.SysNumberCode - 1) * periodicity + 1, 1)
        endif as date) "fromDate",
    cast(if periodicity = 0 then period //без периода только первое число начала периодаDateLastOfThisMonth(period)
                        else DateLastOfThisMonth(ymd(year(period), n.SysNumberCode * periodicity, 1))
        endif as date) "toDate",
    ds.GenTableDataSetCode "dataSetId",
    ds.PageName "pageName",
    ds.EditTime "editTime",
    ds.Ready "ready",
    ds.Condition "condition",
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
        where ca.GenTableConfigCode = tc.GenTableConfigCode
    ) "analytics",
    coalesce(ds.Closed, 0) "closed"
from
    GenTableConfig tc
    join GenTable t on
        t.GenTableCode = tc.GenTableCode
    join GenTableGroup tg on
        tg.GenTableGroupCode = t.GenTableGroupCode
    left outer join SysNumber n on
        // для "без периода" (0) получим одну запись, для остальных столько сколько отчетов в году
        n.SysNumberCode between 1 and 12 / coalesce(nullif(t.Periodicity, 0), 12)
    left outer join GenTableDataSet ds on
        ds.PeriodDate = period
        and ds.EnterpriseCode = tc.EnterpriseCode
        and ds.GenTableCode = tc.GenTableCode
        and ds.GenTableConfigCode = tc.GenTableConfigCode
        and ds.ChangeDate between fromDate and toDate
    left outer join Enterprise e on e.EnterpriseCode = tc.EnterpriseCode
where
    tc.EnterpriseCode in ({$enterprises})
    and tc.GenTableCode in ({$tables})
    and tc.FinishDate >= ? and tc.StartDate <= ?
    and toDate between ? and ?
order by
    tg.Name, t.Position, e.Name, fromDate, pageName
EOT
: <<<"EOT"
select
  tc.GenTableConfigCode as "configId",
  tg.GenTableGroupCode as "groupId",
  tg.Name as "groupName",
  t.GenTableCode as "tableId",
  t.Label as "tableName",
  t.ReportName as "reportName",
  e.EnterpriseCode as "enterpriseId",
  e.Name as "enterpriseName",
  ?::date /*0*/ as "period",
  coalesce(t.Periodicity, 0) as "periodicity",
  n.SysNumberCode as "periodNumber",
  case coalesce(t.Periodicity, 0)
    when 0 then E'Общий'
    when 1 then FN_cmMonthName(n.SysNumberCode, 1, 0)
    when 3 then n.SysNumberCode || E' квартал'
    when 6 then n.SysNumberCode || E' полугодие'
    when 12 then E'Год'
    else E'Общий'
  end as "periodName",
  cast(case
         when coalesce(t.Periodicity, 0) = 0 then ? /*1=0*/
         else ymd(year(? /*2=0*/), (n.SysNumberCode - 1) * coalesce(t.Periodicity, 0) + 1, 1)
       end as date) as "fromDate",
  cast(case
         when coalesce(t.Periodicity, 0) = 0 then ? /*3=0*/
         else DateLastOfThisMonth(ymd(year(? /*4=0*/), n.SysNumberCode * coalesce(t.Periodicity, 0), 1))
       end as date) as "toDate",
  ds.GenTableDataSetCode as "dataSetId",
  ds.PageName as "pageName",
  ds.EditTime as "editTime",
  ds.Ready as "ready",
  ds.Condition as "condition",
  (select
      E'{' || string_agg((map.JSON_Object(ca.GenTableAnalyticsCode,
                                      map.JSON_Numeric(E'id', (ca.GenTableDictDataCode)::numeric) ||
                                      map.JSON_String(E'text', dd.Value) ||
                                      map.JSON_String(E'filter', a.FilterName, 0), 0))::text, ',') || E'}'
   from GenTableConfigAnalytics ca
        join GenTableAnalytics a on a.GenTableAnalyticsCode = ca.GenTableAnalyticsCode
        join GenTableDictData dd on dd.GenTableDictDataCode = ca.GenTableDictDataCode
   where ca.GenTableConfigCode = tc.GenTableConfigCode) as "analytics",
  coalesce (ds.Closed, 0) as "closed",
  t.Position
from GenTableConfig tc
     join GenTable t on t.GenTableCode = tc.GenTableCode
     join GenTableGroup tg on tg.GenTableGroupCode = t.GenTableGroupCode
     left outer join SysNumber n on n.SysNumberCode between 1 and 12 / coalesce(nullif(t.Periodicity, 0), 12)
     left outer join GenTableDataSet ds on ds.PeriodDate = ? /*5=0*/
    	and ds.EnterpriseCode = tc.EnterpriseCode
        and ds.GenTableCode = tc.GenTableCode
        and ds.GenTableConfigCode = tc.GenTableConfigCode
        and ds.ChangeDate between cast (case
                                          when coalesce(t.Periodicity, 0) = 0 then ? /*6=0*/
                                          else ymd(year(? /*7=0*/), (n.SysNumberCode - 1) * coalesce(t.Periodicity, 0) + 1, 1)
                                        end as date) and cast(case
                                                                when coalesce(t.Periodicity, 0) = 0 then ? /*8=0*/
                                                                else DateLastOfThisMonth(ymd(year(? /*9=0*/), n.SysNumberCode * coalesce(t.Periodicity, 0), 1))
                                                              end as date)
     left outer join Enterprise e on e.EnterpriseCode = tc.EnterpriseCode
where tc.EnterpriseCode in ({$enterprises})
      and tc.GenTableCode in ({$tables})
      and tc.FinishDate >= (? /*10=1*/)
      and tc.StartDate <= (? /*11=2*/)
      and cast(case
                 when coalesce(t.Periodicity, 0) = 0 then ? /*12=0*/
                 else DateLastOfThisMonth(ymd(year(? /*13=0*/), n.SysNumberCode * coalesce(t.Periodicity, 0), 1))
               end as date) between ? /*14=3*/

      and ? /*15=4*/
order by 3, 22, 8, 13, 16;
EOT;
$stmt = $db->prepare($sql); $db->isSybase ? $stmt->execute([ $params->period, $params->fromDate, $params->toDate, $params->fromDate, $params->toDate ]) : $stmt->execute([ $params->period, $params->period, $params->period, $params->period, $params->period, $params->period, $params->period, $params->period, $params->period, $params->period, $params->fromDate, $params->toDate, $params->period, $params->period, $params->fromDate, $params->toDate ]); $ret = []; while($row = $stmt->fetch()) { $row['analytics'] = safe_json_decode($row['analytics']); $ret[] = $row; } return $ret; } public function deleteReports ($dataSetId) { return Engine::delete($dataSetId); } public function setClosed($param) { if (!$param) { return false; } $closed = isUndefinedProperty($param, 'closed'); $dataSetId = isUndefinedProperty($param, 'dataSetId'); if (!$dataSetId) { return false; } $db = $this->db ?: $this->db = connectDb(); $sql = <<< "EOT"
update GenTableDataSet set
    Closed = ?
where GenTableDataSetCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([(int) $closed, $dataSetId]); return true; } public function updateReadyAll($param) { $res = null; $ready = (bool) isUndefinedProperty($param, 'ready'); if (!is_array($param->params)) { $param->params = [$param->params]; } foreach ($param->params as $p) { Engine::$isBatch = true; $r = Engine::load($p); $ds = $r->dataSets[$r->dataSetId]; $ds->isReport = 1; $ds->dataSetId = $r->dataSetId; $ds->ready = (int) $ready; $res = Engine::save($ds); } return $res; } public function updateClosedAll($param) { $db = $this->db ?: $this->db = connectDb(); checkUserAccess("map-tables-reports-close"); $closed = (bool) isUndefinedProperty($param, 'closed'); if (!is_array($param->params)) { $param->params = [$param->params]; } $in = createSqlInCondition($param->params); $db->beginTransaction(); try { executeSql($db, "update GenTableDataSet set Closed = ? where GenTableDataSetCode $in", [(int) $closed] ); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } $service = new Junior\Server(new map_tables_reports()); $service->process();