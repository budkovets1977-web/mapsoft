<?php
 #[\AllowDynamicProperties] class DataSetBand { public $parent; public $ofName; public $tableBand; public $children; } #[\AllowDynamicProperties] class DataSetBands { public $maxId; public $ofName; public $root; } #[\AllowDynamicProperties] class DataSet { public $id; public $nm; public $table; public $cols; public $rows; public $cells; public $processedCells; public $cleaned; public static $allDataSets = []; public static $changedDataSets; public static function get ($id, $recalculate = false) { if (!$id) { $dataSet = self::create(); } else if (!isset(self::$allDataSets[$id])) { $db = Engine::$db; $sql = <<<"EOT"
select
    ds.GenTableDataSetCode "id",
    ds.Hash "hash",
    ds.GenTableCode "tableId",
    (
        select {$db->sybase("first")} GenTableVersionCode from GenTableVersion
        where GenTableCode = ds.GenTableCode and OnDate <= ds.ChangeDate order by OnDate desc, GenTableVersionCode
        {$db->postgre("NULLS LAST limit 1")}
    ) "tableVersionId",
    e.EnterpriseCode "enterpriseId",
    e.Name "enterpriseName",
    e.FullName "enterpriseFullName",
    e.PhoneNumber "phone",
    /* изменил выборку адреса, теперь выбирается из теблицы EnterpriseBasic*/
    /*FN_cmAddressName((select {$db->sybase("first")} AddressLocationCode from EnterpriseAddress*/
    /*    where EnterpriseCode = e.EnterpriseCode and AddressType = 0*/
    /*    order by AddressLocationCode*/
    /*    {$db->postgre("limit 1")})) "address",*/
    FN_cmAddressName(ea.AddressLocationCode) "address",
    en.CodeUNK "codeUnk",
    ds.PeriodDate "period",
    ds.ChangeDate "changeDate",
    ds.PageName "page",
    ds.EditTime "editTime",
    ds.Ready "ready",
    ds.Condition "condition",
    ds.GenTableConfigCode "configId",
    (
        select {$db->dialect("list(lower(trim(a.Name)) || '^@' || d.Value, '^@')", "string_agg((lower(asa_trim(a.Name)) || E'^@' || d.Value)::text, E'^@')")}
        from GenTableConfigAnalytics c
            join GenTableAnalytics a on a.GenTableAnalyticsCode = c.GenTableAnalyticsCode
            join GenTableDictData d on d.GenTableDictDataCode = c.GenTableDictDataCode
        where c.GenTableConfigCode = ds.GenTableConfigCode
    ) "analytics",
    ds.JSON "data",
    ds.Closed "closed",
    u.WebUserCode "userId",
    u.Profile "userProfile",
    md.SurName || ' ' || md.Name || ' ' || md.SecondName "director",
    ma.SurName || ' ' || ma.Name || ' ' || ma.SecondName "accountant"
from
    GenTableDataSet ds
    join Enterprise e on e.EnterpriseCode = ds.EnterpriseCode
    left outer join WebUser u on u.WebUserCode = ds.WebUserCode
    left outer join EnterpriseBasic eb on eb.EnterpriseCode = e.EnterpriseCode
    left outer join EnterpriseAddress ea on ea.EnterpriseAddressCode = eb.EnterpriseAddressCode
    left outer join EnterpriseNumber en on en.EnterpriseCode = e.EnterpriseCode
    left outer join WorkerInfo wid on wid.WorkerCode = eb.WorkerDirectorCode
    left outer join Man md on md.ManCode = wid.ManCode
    left outer join WorkerInfo wia on wia.WorkerCode = eb.WorkerAccountantCode
    left outer join Man ma on ma.ManCode = wia.ManCode
where
    ds.GenTableDataSetCode = ?
EOT;
static $stmt = null; ($stmt && !Engine::$isBatch) || ($stmt = $db->prepare($sql)); $stmt->execute([$id]); $row = $stmt->fetch(); $data = safe_json_decode($row['data']); if (!isset($data)) { throw new ErrorException("Устаревший формат данных таблицы {$id}! Выполните функцию БД FN_gnTableCompileAllDataSets()."); } checkJsonDecode(); unset($row['data']); $dataSet = arrayToObject($row, 'DataSet'); mergeObjects($dataSet, $data); $dataSet->id = $id; $dataSet->recalculate = $recalculate; $dataSet->userProfile = isset($dataSet->userProfile) ? safe_json_decode($dataSet->userProfile) : new \stdClass; $dataSet->compile(); } else { $dataSet = self::$allDataSets[$id]; } Engine::$params->dataSetsOfName[$dataSet->nm][$dataSet->id] = $dataSet; return $dataSet; } public static function create () { $db = Engine::$db; $params = Engine::$params; $sql = <<<"EOT"
select
    0 "id",
    {$db->dialect("cast(? as integer)", "?::integer")} "tableId",
    coalesce(
    (
        select {$db->sybase('first')} GenTableVersionCode from GenTableVersion
        where GenTableCode = ? and OnDate <= ? order by OnDate desc
        {$db->postgre('limit 1')}
    ),
    (
        select {$db->sybase('first')} GenTableVersionCode from GenTableVersion
        where GenTableCode = ? order by OnDate
        {$db->postgre('limit 1')}
    )) "tableVersionId",
    e.EnterpriseCode "enterpriseId",
    e.Name "enterpriseName",
    e.FullName "enterpriseFullName",
    e.PhoneNumber "phone",
    /* изменил выборку адреса, теперь выбирается из таблицы EnterpriseBasic*/
    /*FN_cmAddressName((select {$db->sybase('first')} AddressLocationCode from EnterpriseAddress*/
    /*     where EnterpriseCode = e.EnterpriseCode and AddressType = 0 {$db->postgre('limit 1')})) "address",*/
    FN_cmAddressName(ea.AddressLocationCode) "address",
    en.CodeUNK "codeUnk",
    {$db->dialect("cast(? as date)", "?::date")} "period",
    {$db->dialect("cast(? as date)", "?::date")} "changeDate",
    {$db->dialect("cast(? as text)", "?::text")} "page",
    {$db->dialect("now(*)", "clock_timestamp()::timestamp")} "editTime",
    0 "ready",
    0 "condition",
    {$db->dialect("cast(? as integer)", "?::integer")} "configId",
    (
        select {$db->dialect("list(lower(trim(a.Name)) || '^@' || d.Value, '^@')", "string_agg((lower(asa_trim(a.Name)) || E'^@' || d.Value)::text, E'^@')")}
        from GenTableConfigAnalytics c
            join GenTableAnalytics a on a.GenTableAnalyticsCode = c.GenTableAnalyticsCode
            join GenTableDictData d on d.GenTableDictDataCode = c.GenTableDictDataCode
        where c.GenTableConfigCode = ?
    ) "analytics",
    0 "closed",
    md.SurName || ' ' || md.Name || ' ' || md.SecondName "director",
    ma.SurName || ' ' || ma.Name || ' ' || ma.SecondName "accountant"
from
    Enterprise e
    left outer join EnterpriseBasic eb on eb.EnterpriseCode = e.EnterpriseCode
    left outer join EnterpriseAddress ea on ea.EnterpriseAddressCode = eb.EnterpriseAddressCode
    left outer join EnterpriseNumber en on en.EnterpriseCode = e.EnterpriseCode
    left outer join WorkerInfo wid on wid.WorkerCode = eb.WorkerDirectorCode
    left outer join Man md on md.ManCode = wid.ManCode
    left outer join WorkerInfo wia on wia.WorkerCode = eb.WorkerAccountantCode
    left outer join Man ma on ma.ManCode = wia.ManCode
where
    e.EnterpriseCode = ?
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->tableId, $params->tableId, $params->changeDate, $params->tableId, $params->period, $params->changeDate, $params->page, $params->configId, $params->configId, $params->isReport ? $params->enterpriseId : getSessionValue('mapEnterpriseCode') ]); $row = $stmt->fetch(); $dataSet = arrayToObject($row, 'DataSet'); $dataSet->userId = getSessionValue('user'); $dataSet->userProfile = getSessionValue('userProfile'); $dataSet->id = 0; $dataSet->cols = new stdClass; $dataSet->cols->children = []; $dataSet->rows = new stdClass; $dataSet->rows->children = []; $dataSet->cells = new stdClass; $dataSet->recalculate = true; $dataSet->compile(); return $dataSet; } public static function loadForeign () { $db = Engine::$db; $params = Engine::$params; $enterpriseParentCode = (int) getSessionValue('mapEnterpriseParentCode'); $sqlEnterprises = implode(',', isset($params->enterprises) ? $params->enterprises : []); if ($sqlEnterprises != '') { $sqlEnterprises = "ds.EnterpriseCode in ({$sqlEnterprises})"; } else { $sqlEnterprises = "1 = 1"; } $sqlEnterprises = <<<"EOT"
(((coalesce(ft.Flags, 0) & 1) = 0 and {$sqlEnterprises})
    or ((coalesce(ft.Flags, 0) & 1) = 1 and ds.EnterpriseCode = {$enterpriseParentCode}))
EOT;
$sqlReportEnterprise = <<<"EOT"
(((coalesce(ft.Flags, 0) & 1) = 0 and ds.EnterpriseCode = ?)
    or ((coalesce(ft.Flags, 0) & 1) = 1 and ds.EnterpriseCode = {$enterpriseParentCode}))
EOT;
$sqlAnalytics = ''; $sqlAnalyticsA = ''; $sqlAnalyticsB = ''; if (isset($params->analytics) && is_array($params->analytics)) { foreach ($params->analytics as $group) { $ids = $group->id; $ds = $group->data; $str1a = ''; $str1b = ''; for ($i = 0, $c = count($ids); $i < $c; $i++) { $or = $i > 0 ? 'or ' : ''; $anIds = $ids[$i]; $anValues = $ds[$i]; $str2a = ''; $str2b = ''; for ($j = 0, $cc = count($anIds); $j < $cc; $j++) { $and = $j > 0 ? 'and ' : ''; $id = $anIds[$j] === null ? 'null' : $anIds[$j]; $value = $anValues[$j] === null ? 'null' : $anValues[$j]; $str2a .= <<<"EOT"
            {$and} (select GenTableDictDataCode from GenTableConfigAnalytics
                where GenTableConfigCode = ds.GenTableConfigCode
                    and GenTableAnalyticsCode = {$id}) = {$value}
EOT;
$str2b .= <<<"EOT"
            {$and} coalesce((select GenTableDictDataCode from GenTableConfigAnalytics
                where GenTableConfigCode = ds.GenTableConfigCode
                    and GenTableAnalyticsCode = {$id}), {$value}) = {$value}
EOT;
} $str1a .= "\n{$or} ({$str2a})"; $str1b .= "\n{$or} ({$str2b})"; } $sqlAnalyticsA .= "\n".($sqlAnalyticsA ? 'and ' : '')."({$str1a})"; $sqlAnalyticsB .= "\n".($sqlAnalyticsB ? 'and ' : '')."({$str1b})"; } $sqlAnalytics = $db->isSybase ? <<<"EOT"
and ((baseTableIdx > 0 and {$sqlAnalyticsA})
    or (baseTableIdx <= 0 and {$sqlAnalyticsB}))
EOT
: <<<"EOT"
and ((charindex(E'"' || ft.Name || E'"', tv.ForeignTables) > 0 and {$sqlAnalyticsA})
    or (charindex(E'"' || ft.Name || E'"', tv.ForeignTables) <= 0 and {$sqlAnalyticsB}))
EOT;
} $sql = $db->isSybase ? <<<"EOT"
select
    ds.GenTableDataSetCode "id",
    charindex('"' || ft.Name || '"', tv.ForeignTables) "baseTableIdx"
from
    GenTable pt
    join GenTableVersion tv on
        tv.GenTableVersionCode = (select first GenTableVersionCode from GenTableVersion
            where GenTableCode = pt.GenTableCode and OnDate <= ? order by OnDate desc)
    join GenTable ft on
        ft.GenTableSetCode = pt.GenTableSetCode and
        charindex('"' || ft.Name || '"', tv.CalculateTables) > 0
    join GenTableDataSet ds on
        ds.GenTableCode = ft.GenTableCode
        /* проверка периода необязательна, может быть два с предыдущим, все проверки по changeDate
        //and ds.PeriodDate = ?
        and ds.PeriodDate between
            // для расчета таблицы без периода, захватываем прошлый период
            if coalesce(pt.Periodicity,0) = 0 then
                (select first ChangeDate from PBPeriod
                    where EnterpriseCode = ? and ChangeDate < PD
                    order by ChangeDate desc)
            else
                period
            endif and period
        and*/
        and (coalesce(pt.TableType, 0) = 1 or {$sqlReportEnterprise})
        and (? <> 0 or coalesce(pt.TableType, 0) <> 1 or ds.Ready = 1)
        and ds.GenTableConfigCode is not null
        and ( -- признаки рассчитываемого отчета должны совпадать с признаками вторичных или у вторичного пусто
            ? = 0 or coalesce(( // or not exists( // не работает! глюк сибэйса
                select first 1
                from GenTableConfigAnalytics a
                where
                    GenTableConfigCode = ? and exists(
                        select first 1
                        from GenTableConfigAnalytics
                        where
                            GenTableConfigCode = ds.GenTableConfigCode and
                            GenTableAnalyticsCode = a.GenTableAnalyticsCode and
                            GenTableDictDataCode <> a.GenTableDictDataCode)), 0) <> 1
        )
        and {$sqlEnterprises}
        // фильтруем кривые таблицы без периода (должны быть первого числа периода)
        and (coalesce(ft.Periodicity,0) <> 0 or ds.ChangeDate = ds.PeriodDate)
        and (
            // с совпадающей датой (одного квартала, месяца, года..)
            ds.ChangeDate = ? // changeDate
            // + все без периодичности (первое число периода)
            or ds.ChangeDate = ? // periodDate
            // + если считаем без переодичности, то другие переодичные с последней датой прошлого периода
            or (coalesce(pt.Periodicity,0) = 0
                and coalesce(ft.Periodicity,0) <> 0
                and ds.ChangeDate = dateadd(day, -1, ?)) // periodDate
        )

        /*    -- все "без периода" но без прошлого периода
            or (coalesce(ft.Periodicity,0) = 0 and ds.ChangeDate = PD)
            -- остальные все попадающие в changeDate или последней датой предыдущего периода
            or (
                (coalesce(ft.Periodicity,0) <> 0
                    and (ds.ChangeDate = CD or ds.ChangeDate = dateadd(day, -1, PD))
                )
            )
        )*/
        and ds.GenTableCode <> ?
        //необязательное, предыдущее условие отрежет and ds.GenTableDataSetCode <> ?
        {$sqlAnalytics}
where
    pt.GenTableCode = ?
EOT
: <<<"EOT"
select
  ds.GenTableDataSetCode as "id",
  charindex(E'"' || ft.Name || E'"', tv.ForeignTables) as "baseTableIdx"
from GenTable pt
     join GenTableVersion tv on tv.GenTableVersionCode = (select
                                                             GenTableVersionCode
                                                          from GenTableVersion
                                                          where GenTableCode = pt.GenTableCode
                                                                and OnDate <= (? /*0*/)
                                                          order by OnDate desc NULLS LAST
                                                          limit 1)
     join GenTable ft on ft.GenTableSetCode = pt.GenTableSetCode and charindex(E'"' || ft.Name || E'"', tv.CalculateTables) > 0
     join GenTableDataSet ds on ds.GenTableCode = ft.GenTableCode
		  and {$sqlEnterprises}
          and (coalesce(pt.TableType, 0) = 1 or {$sqlReportEnterprise} /*1*/)
          and (? /*2*/ <> (0) or coalesce (pt.TableType, 0) <> (1) or ds.Ready = 1)
          and ds.GenTableConfigCode is not null
          and (? /*3*/ = 0 or coalesce((select 1
                                        from GenTableConfigAnalytics a
                                        where GenTableConfigCode = ? /*4*/
                                              and exists(select 1
                                                         from GenTableConfigAnalytics
                                                         where GenTableConfigCode = ds.GenTableConfigCode
                                                               and GenTableAnalyticsCode = a.GenTableAnalyticsCode
                                                               and GenTableDictDataCode <> (a.GenTableDictDataCode)
                                                         limit 1)
                                          limit 1), 0) <> (1))
          and (coalesce(ft.Periodicity, 0) <> (0) or ds.ChangeDate = ds.PeriodDate)
          and (ds.ChangeDate = ? /*5*/ or ds.ChangeDate = ? /*6*/ or (coalesce(pt.Periodicity, 0) = 0
                                                                      and coalesce(ft.Periodicity, 0) <> (0)
                                                                      and ds.ChangeDate = dateadd('day', - 1, ? /*7*/)))
          and ds.GenTableCode <> ? /*8*/
          {$sqlAnalytics}
where pt.GenTableCode = ? /*9*/

EOT;
$stmt = $db->prepare($sql); $stmt->execute([$params->changeDate, $params->enterpriseId, $params->isReport, isset($params->configId) ? $params->configId : 0, isset($params->configId) ? $params->configId : 0, $params->changeDate, $params->period, $params->period, $params->tableId, $params->tableId]); if (Engine::$mainId == Engine::$params->dataSetId) { $dataSets = []; $tables = []; while($row = $stmt->fetch()) { $dataSet = self::get($row['id']); $dataSets[$dataSet->id] = $dataSet; $tables[$dataSet->table->id] = $dataSet->table; } Engine::$returnDataSets = $dataSets; Engine::$returnTables = $tables; } else { while($row = $stmt->fetch()) { self::get($row['id']); } } } private function compile () { $params = Engine::$params; $this->isReport = $params->isReport; $this->table = $table = Table::get($this->tableVersionId); $this->nm = $table->nm; $this->parsedDate = (object)date_parse($this->changeDate); $this->month = $this->parsedDate->month; $this->ready = (int)$this->ready; $this->condition = (int)$this->condition; $this->closed = (int)$this->closed; $this->userId = (int)$this->userId; $this->userProfile = isset($this->userProfile) ? $this->userProfile : new stdClass; self::$allDataSets[$this->id] = $this; if ($this->analytics) { $analytics = safe_explode('^@', (string)$this->analytics); $this->analytics = new stdClass; for ($count = count($analytics), $i = 0; $i < $count; $i += 2) { $this->analytics->{$analytics[$i]} = $analytics[$i + 1]; } } $cols = new DataSetBands; $cols->maxId = 0; $cols->root = $this->cols; $this->cols = $cols; $this->compileBands($cols, $cols->root); $this->correctBands($table->cols->root, $cols, $cols->root); $rows = new DataSetBands; $rows->maxId = 0; $rows->root = $this->rows; $this->rows = $rows; $this->compileBands($rows, $rows->root); $this->correctBands($table->rows->root, $rows, $rows->root); if ($this->recalculate || empty($this->hash) || !isset($this->hashTable) || $this->hashTable !== $table->hash ) { $newParams = $params; if ($this->id != $params->dataSetId) { $newParams = new stdClass; $newParams->isReport = 1; $newParams->period = $this->period; $newParams->changeDate = $this->changeDate; $newParams->enterpriseId = $this->enterpriseId; $newParams->tableId = $this->tableId; $newParams->configId = $this->configId; $newParams->page = $this->page; $newParams->dataSetId = $this->id; Engine::$params = $newParams; } self::loadForeign(); $this->calculateAll(); if ($this->id != 0 ) { self::save($this->cleaned = $this->getCleanedDataSet()); } Engine::$params = $params; } else if (Engine::$mainId == $this->id) { self::loadForeign(); } } private function compileBands ($bands, $parent) { foreach ($parent->children as $band) { $parent->ofName[$band->n][] = $band; if ($bands->maxId < $band->k) { $bands->maxId = $band->k; } if (isset($band->children)) { $this->compileBands($bands, $band); } } } private function correctBands ($tableParent, $bands, $parent) { $oldOfName = isset($parent->ofName) ? $parent->ofName : null; $parent->children = []; $parent->ofName = []; foreach ($tableParent->children as $tableBand) { $newBands = []; $nm = $tableBand->nm; $isRepeat = isset($tableBand->fl) && $tableBand->fl & 0x10; if (isset($oldOfName[$nm])) { if ($isRepeat) { $newBands = $oldOfName[$nm]; } else { $newBands[] = $oldOfName[$nm][0]; } } else { $newBands[] = $band = new DataSetBand; $band->k = ++$bands->maxId; $band->n = $nm; } array_add($parent->children, $newBands); foreach ($newBands as $band) { $band->parent = $parent; $band->tableBand = $tableBand; $parent->ofName[$nm][] = $band; $bands->ofName[$nm][] = $band; if (isset($tableBand->children)) { $this->correctBands($tableBand, $bands, $band); } else { unset($band->children); unset($band->ofName); } } } } private function getCleanedDataSet () { $data = new stdClass; $data->cells = $this->cells; self::getCleanedBands($data->cols = new stdClass, $this->cols->root->children); self::getCleanedBands($data->rows = new stdClass, $this->rows->root->children); $data->address = $this->address; $data->analytics = $this->analytics; $data->checkErrors = isset($this->checkErrors) ? $this->checkErrors : 0; $data->changeDate = $this->changeDate; $data->condition = $this->condition; $data->configId = $this->configId; $data->editTime = $this->editTime; $data->enterpriseFullName = $this->enterpriseFullName; $data->enterpriseId = $this->enterpriseId; $data->enterpriseName = $this->enterpriseName; isset($this->hash) && ($data->hash = $this->hash); isset($this->hashTable) && ($data->hashTable = $this->hashTable); $data->id = $this->id; $data->month = $this->month; $data->nm = $this->nm; $data->page = $this->page; $data->period = $this->period; $data->phone = $this->phone; $data->ready = $this->ready; $data->tableId = $this->tableId; $data->tableVersionId = $this->tableVersionId; $data->closed = $this->closed; $data->userId = $this->userId; $data->userProfile = $this->userProfile; $data->director = $this->director; $data->accountant = $this->accountant; $data->codeUnk = $this->codeUnk; return $data; } public function getCleaned () { return $this->cleaned ?: ($this->cleaned = $this->getCleanedDataSet()); } private static function getCleanedBands ($parent, $children) { $parent->children = []; foreach ($children as $band) { $parent->children[] = $newBand = new stdClass; $newBand->k = $band->k; $newBand->n = $band->n; if (isset($band->children)) { self::getCleanedBands($newBand, $band->children); } } } public static function cleanupDataSets ($dataSets) { foreach ($dataSets as $id => $dataSet) { $dataSets[$id] = $dataSet->getCleaned(); } return $dataSets; } public static function save ($dataSet) { $data = new stdClass; $data->cols = $dataSet->cols; $data->rows = $dataSet->rows; $data->cells = $dataSet->cells; $data->hashTable = isset($dataSet->hashTable) ? $dataSet->hashTable : null; $data->condition = (int)$dataSet->condition; $data->ready = (int)$dataSet->ready; $data->checkErrors = (int)$dataSet->checkErrors; $data->page = (string)$dataSet->page; $hash = hash('md5', $json = json_encode($data, JSON_UNESCAPED_UNICODE)); if ($dataSet->id && isset($dataSet->hash) && $dataSet->hash === $hash) { return; } $dataSet->hash = $hash; $dataSet->json = $json; self::$changedDataSets[] = self::commit($dataSet); unset($dataSet->json); self::recalculateDependents(); } public static function commit ($dataSet) { $db = Engine::$db; static $stmtCheck = null; static $stmtFind = null; static $stmtInsert = null; static $stmtUpdate = null; ($stmtCheck && !Engine::$isBatch) || ($stmtCheck = $db->prepare( "select {$db->sybase("first")} 1 from GenTableDataSet where GenTableDataSetCode = ? order by 1 {$db->postgre("limit 1")}" )); ($stmtFind && !Engine::$isBatch) || ($stmtFind = $db->prepare(<<<"EOT"
select {$db->sybase('first')}
    GenTableDataSetCode "id"
from GenTableDataSet
where
    PeriodDate = ? and
    ChangeDate = ? and
    EnterpriseCode = ? and
    GenTableCode = ? and
    GenTableConfigCode = ? and
    coalesce(PageName, {$db->postgre("E")}'') = coalesce(?, {$db->postgre("E")}'')
order by GenTableDataSetCode
{$db->postgre('limit 1')}
EOT
)); ($stmtInsert && !Engine::$isBatch) || ($stmtInsert = $db->prepare(<<<"EOT"
insert into GenTableDataSet (PeriodDate, ChangeDate, EnterpriseCode, GenTableCode,
    GenTableConfigCode, PageName, Ready, Condition, JSON, Hash, EditTime, WebUserCode)
values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$db->dialect("now(*)", "clock_timestamp()::timestamp")}, ?)
EOT
)); ($stmtUpdate && !Engine::$isBatch) || ($stmtUpdate = $db->prepare(<<<"EOT"
update GenTableDataSet set PageName = ?, Ready = ?, Condition = ?, JSON = ?,
    Hash = ?, EditTime = {$db->dialect("now(*)", "clock_timestamp()::timestamp")}, WebUserCode = ?
where GenTableDataSetCode = ?
EOT
)); $id = $dataSet->id; if ($id) { $stmtCheck->execute([$id]); if (!$stmtCheck->fetch()) { $id = 0; } } if (!$id) { $stmtFind->execute([ $dataSet->period, $dataSet->changeDate, $dataSet->enterpriseId, $dataSet->tableId, $dataSet->configId, $dataSet->page ]); if ($row = $stmtFind->fetch()) { $id = $row['id']; } } if ($id) { $stmtUpdate->execute([ $dataSet->page, $dataSet->ready, $dataSet->condition, $dataSet->json, $dataSet->hash, $dataSet->userId ?: null, $id ]); } else { $stmtInsert->execute([ $dataSet->period, $dataSet->changeDate, $dataSet->enterpriseId, $dataSet->tableId, $dataSet->configId, $dataSet->page, $dataSet->ready, $dataSet->condition, $dataSet->json, $dataSet->hash, $dataSet->userId ?: null ]); $id = $db->isSybase ? $db->lastInsertId() : $db->lastInsertId("seq_GenTableDataSet"); } $dataSet->id = $id; $ret = new stdClass; $ret->period = $dataSet->period; $ret->changeDate = $dataSet->changeDate; $ret->enterpriseId = $dataSet->enterpriseId; $ret->tableId = $dataSet->tableId; $ret->configId = $dataSet->configId; $ret->page = $dataSet->page; $ret->ready = $dataSet->ready; $ret->condition = $dataSet->condition; $ret->dataSetId = $id; $ret->closed = $dataSet->closed; return $ret; } public static function recalculateDependents () { $db = Engine::$db; $params = Engine::$params; $sql = $db->isSybase ? <<<"EOT"
select
    ds.GenTableDataSetCode "id"
from
    GenTable pt
    join GenTableVersion tv on
        charindex('"' || pt.Name || '"', tv.CalculateTables) > 1 -- обязательно больше 1, чтобы не найти себя
    join GenTable ft on
        ft.GenTableCode = tv.GenTableCode and
        ft.GenTableSetCode = pt.GenTableSetCode
    join GenTableDataSet ds on
        ds.GenTableCode = ft.GenTableCode
        and ds.PeriodDate = ? // пересчет отчетов только в своем периоде
        and coalesce(ft.TableType, 0) = 0
        and ds.EnterpriseCode = ?
        and ds.GenTableConfigCode is not null
        and coalesce(( -- у искомого отчета признаки должны совпадать или быть пустыми
        // and not exists( // не работает! глюк сибэйса
            select first 1
            from GenTableConfigAnalytics a
            where
                GenTableConfigCode = ? and exists(
                    select first 1
                    from GenTableConfigAnalytics
                    where
                        GenTableConfigCode = ds.GenTableConfigCode and
                        GenTableAnalyticsCode = a.GenTableAnalyticsCode and
                        GenTableDictDataCode <> a.GenTableDictDataCode
                )
        ), 0) <> 1
        and (
            // точное совпадение периодов
            ds.ChangeDate = ?
            // + все без переодичности или вообще все если пересчитанная без переодичности
            or coalesce(pt.Periodicity,0) = 0 or coalesce(ft.Periodicity,0) = 0
        )
        // фикс, фильтруем кривые таблицы - без периода должны быть первого числа периода
        and (coalesce(ft.Periodicity,0) <> 0 or ds.ChangeDate = ds.PeriodDate)

        /*and (
            -- все "без периода"
            coalesce(ft.Periodicity,0) = 0
            or coalesce(pt.Periodicity,0) = 0
            -- все попадающие в месяц changeDate
            or ds.ChangeDate between DateFirstOfThisMonth(?) and DateLastOfThisMonth(?)
        )*/
where
    pt.GenTableCode = ?
EOT
: <<<"EOT"
select
  ds.GenTableDataSetCode as "id"
from GenTable pt
     join GenTableVersion tv on charindex(E'"' || pt.Name || E'"', tv.CalculateTables) > 1
     join GenTable ft on ft.GenTableCode = tv.GenTableCode and ft.GenTableSetCode = pt.GenTableSetCode
     join GenTableDataSet ds on ds.GenTableCode = ft.GenTableCode
          and ds.PeriodDate = ? /*0*/
          and coalesce(ft.TableType, 0) = 0
          and ds.EnterpriseCode = ? /*1*/
          and ds.GenTableConfigCode is not null
          and coalesce((select 1
                        from GenTableConfigAnalytics a
                        where GenTableConfigCode = ? /*2*/
                              and exists(select 1
                                         from GenTableConfigAnalytics
                                         where GenTableConfigCode = ds.GenTableConfigCode
                                               and GenTableAnalyticsCode = a.GenTableAnalyticsCode
                                               and GenTableDictDataCode <> (a.GenTableDictDataCode)
                                         limit 1)
                        limit 1), 0) <> (1)
          and (ds.ChangeDate = ? /*3*/ or coalesce(pt.Periodicity, 0) = 0 or coalesce(ft.Periodicity, 0) = 0)
          and (coalesce(ft.Periodicity, 0) <> (0) or ds.ChangeDate = ds.PeriodDate)
where pt.GenTableCode = ? /*4*/
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $params->period, $params->enterpriseId, $params->configId, $params->changeDate, $params->tableId ]); while($row = $stmt->fetch()) { self::get($row['id'], true); } } public static function delete ($dataSetId) { $db = Engine::$db; $params = Engine::$params; $rows = selectSql($db, <<<'EOT'
select
    GenTableCode "tableId",
    PeriodDate "period",
    ChangeDate "changeDate",
    EnterpriseCode "enterpriseId",
    GenTableConfigCode "configId"
from
    GenTableDataSet
where
    GenTableDataSetCode = ?
EOT
, [$dataSetId] ); if ($rows) { mergeObjects($params, $rows[0]); executeSql($db, <<<'EOT'
delete from GenTableDataSet where GenTableDataSetCode = ?
EOT
, [$dataSetId] ); self::recalculateDependents(); } } public static function restart () { self::$allDataSets = []; self::$changedDataSets = null; } private function fill () { $table = $this->table; if ($table->filled) { $this->isFill = true; $this->processedCells = []; $cols = $this->cols; $rows = $this->rows; $this->fillBands($table->cols->root, $cols, $cols->root, false); $cols->ofName = $cols->afterFillOfName; unset($cols->afterFillOfName); $this->fillBands($table->rows->root, $rows, $rows->root, true); $rows->ofName = $rows->afterFillOfName; unset($rows->afterFillOfName); unset($this->isFill); } } public function calculateAll () { $this->fill(); $this->processedCells = []; $this->checkErrors = 0; $colsOfName = $this->cols->ofName; $rowsOfName = $this->rows->ofName; foreach ($this->table->calculatedCells as $id) { $pos = safe_explode(':', $id); foreach ($colsOfName[$pos[0]] as $col) { foreach ($rowsOfName[$pos[1]] as $row) { $this->getCellValue($col, $row); } } } $this->condition = (int)($this->checkErrors > 0); $this->hashTable = $this->table->hash; unset($this->recalculate); } private function fillBands ($tableParent, $bands, $parent, $isRow) { $crossOfName = $isRow ? $this->cols->ofName : $this->rows->ofName; $newChildren = []; foreach ($tableParent->children as $tableBand) { $nm = $tableBand->nm; $isRepeat = isset($tableBand->fl) && $tableBand->fl & 0x10; if (isset($parent->ofName[$nm])) { $oldBands = $parent->ofName[$nm]; } else { $oldBands = [$newBand = new stdClass]; $newBand->k = ++$bands->maxId; $newBand->n = $nm; $newBand->parent = $parent; $newBand->tableBand = $tableBand; $parent->ofName[$nm][] = $newBand; $bands->ofName[$nm][] = $newBand; } if (isset($tableBand->ff)) { if ($isRepeat) { $newHash = []; foreach ($tableBand->ff as $formula) { try { $aSet = []; $aCalc = []; if (isset($formula->a)) { foreach ($formula->a as $a) { if (isset($a->s->a)) { $aCalc[] = $a; } else { $aSet[] = $a; } } } $fillBands = (new Calculator($this, $isRow ? $crossOfName[$formula->d][0] : $oldBands[0], $isRow ? $oldBands[0] : $crossOfName[$formula->d][0] ))->fill($formula, $isRow); $oldHash = []; foreach ($oldBands as $oldBand) { if ($isRow) { $hash = '$'.$this->getCellValue( $crossOfName[$formula->d][0], $oldBand ); foreach ($aSet as $a) { $hash .= '$'.$this->getCellValue( $crossOfName[$a->d][0], $oldBand ); } } else { $hash = '$'.$this->getCellValue( $oldBand, $crossOfName[$formula->d][0] ); foreach ($aSet as $a) { $hash .= '$'.$this->getCellValue( $oldBand, $crossOfName[$a->d][0] ); } } $oldHash[$hash] = $oldBand; } foreach ($fillBands as $fillBand) { $hash = '$'.$fillBand[$formula->d]; foreach ($aSet as $a) { $hash .= '$'.$fillBand[$a->d]; } if (isset($newHash[$hash])) { $hashBand = &$newHash[$hash]; foreach ($aCalc as $a) { switch ($a->s->a) { case 'sum': case 'avg': $hashBand[$a->d] = correctFloat( $hashBand[$a->d] + $fillBand[$a->d] ); break; case 'max': $hashBand[$a->d] = $hashBand[$a->d] > $fillBand[$a->d] ? $hashBand[$a->d] : $fillBand[$a->d]; break; case 'min': $hashBand[$a->d] = $hashBand[$a->d] < $fillBand[$a->d] ? $hashBand[$a->d] : $fillBand[$a->d]; break; } } } else { $newHash[$hash] = $fillBand; } } } catch (Throwable $e) { $f = json_encode($formula, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); $b = $isRow ? 'строки' : 'колонки'; throw new ErrorException(<<<"EOT"
<pre>Ошибка при заполнении {$b} <b>'{$nm}'</b>
Формула:
<b>{$f}</b>
</pre>
{$e}
EOT
); }} ksort($newHash, SORT_LOCALE_STRING | SORT_FLAG_CASE); if ($fillBands) { foreach ($newHash as $hash => $fillBand) { if (isset($oldHash[$hash])) { $newBand = $oldHash[$hash]; } else { $newBand = new stdClass; $newBand->k = ++$bands->maxId; $newBand->n = $nm; $newBand->parent = $parent; $newBand->tableBand = $tableBand; } $newChildren[] = $newBand; $this->fillBandValues($newBand, $fillBand, $isRow); } } else { $newChildren[] = $oldBands[0]; } } else { foreach ($tableBand->ff as $formula) { $fillBands = (new Calculator($this, $isRow ? $crossOfName[$formula->d][0] : $oldBands[0], $isRow ? $oldBands[0] : $crossOfName[$formula->d][0], null ))->fill($formula, $isRow); if ($fillBands) { $this->fillBandValues($oldBands[0], end($fillBands), $isRow); } } $newChildren[] = $oldBands[0]; } } else { array_add($newChildren, $oldBands); } } $parent->children = $newChildren; $parent->ofName = []; foreach ($newChildren as $children) { $parent->ofName[$children->n][] = $children; $bands->afterFillOfName[$children->n][] = $children; } foreach ($newChildren as $children) { if (isset($children->tableBand->children)) { $this->fillBands($children->tableBand, $bands, $children, $isRow); } else { unset($children->children); unset($children->ofName); } } } private function fillBandValues ($band, $fillBand, $isRow) { if ($isRow) { $colsOfName = $this->cols->ofName; foreach ($fillBand as $field => $value) { $this->setCellValue($colsOfName[$field][0], $band, $value); } } else { $rowsOfName = $this->rows->ofName; foreach ($fillBand as $field => $value) { $this->setCellValue($band, $rowsOfName[$field][0], $value); } } } private function setCellValue ($col, $row, $value) { $table = $this->table; $tCells = $table->cells; $tCellId = $col->n.':'.$row->n; $tCell = isset($tCells->{$tCellId}) ? $tCells->{$tCellId} : null; $cells = $this->cells; $cells->{$col->k.':'.$row->k} = $cell = new stdClass; $cell->f = 0; if ($tCell && isset($tCell->dc)) { $cell->v = Dictionary::getIdOfValue($tCell->dc, $value); } else { $cell->v = $value; } } public function getCellValue ($col, $row) { $cellId = $col->k.':'.$row->k; if (isset($this->processedCells) && ( isset($this->processedCells[$cellId]) || array_key_exists($cellId, $this->processedCells) )) { return $this->processedCells[$cellId]; } $cells = $this->cells; $cell = isset($cells->{$cellId}) ? $cells->{$cellId} : null; $table = $this->table; $tCells = $table->cells; $tCellId = $col->n.':'.$row->n; $tCell = isset($tCells->{$tCellId}) ? $tCells->{$tCellId} : null; if ($tCell) { if (Engine::$params->dataSetId == $this->id && !isset($this->isFill) && (isset($tCell->ft) || isset($tCell->fc)) ) { $this->processedCells[$cellId] = null; if (isset($tCell->ft) && (!isset($cell->f) || ($cell->f & 1) === 0)) { $cells->{$cellId} = $cell = new stdClass; $cell->v = null; switch ($tCell->ft) { case 1: $fa = $tCell->fa; break; case 2: $fa = $table->cols->ofName[$col->n]->fa; break; case 3: $fa = $table->rows->ofName[$row->n]->fa; break; } $res = (new Calculator($this, $col, $row))->execute($fa); $style = $table->styles->{$tCell->st}; if (isset($style->rs) && $style->rs != 0.0) { $res = floatToString(roundEx($res, $style->rs, isset($style->rt) ? $style->rt : 0 )); } else if (!$res) { $res = '0'; } else if (is_numeric($res)) { $res = floatToString($res); } $cell->v = $res; } else { if ($cell) { $res = isset($tCell->dc) ? Dictionary::getValue($cell->v) : $cell->v; } else { $res = isset($tCell->vl) ? $tCell->vl : null; } } $this->processedCells[$cellId] = $res; if (isset($cell)) { unset($cell->r); unset($cell->c); } if (isset($tCell->fc)) { if (!isset($cell)) { $cells->{$cellId} = $cell = new stdClass; $cell->v = null; } $cell->r = 1; foreach ($tCell->fc as $fc) { $calc = new Calculator($this, $col, $row); $r = $calc->execute($fc->a, true) ? 1 : 0; $cell->r &= $r; $cell->c[] = $check = new stdClass; $check->s = $calc->result; $check->r = $r; } if (!$cell->r) { ++$this->checkErrors; } } return $res; } else { if ($cell) { return isset($tCell->dc) ? Dictionary::getValue($cell->v) : $cell->v; } else { return isset($tCell->vl) ? $tCell->vl : null; } } } return isset($cell) ? $cell->v : null; } public static function findParentBand ($childrenBand, $findTableBand) { while (isset($childrenBand->parent)) { $childrenBand = $childrenBand->parent; if ($childrenBand->tableBand === $findTableBand) { return $childrenBand; } } } public static function findChildrenBands ($parentBand, $findName) { $result = []; if ($parentBand->ofName[$findName]) { return $parentBand->ofName[$findName]; } foreach ($parentBand->children as $child) { if (isset($child->children)) { array_add($result, self::findChildrenBands($child, $findName) ); } } return $result; } public function findRangeBands ($tableBands, $bandString, $band2String, $dataBands, $gravityBand, $mainBandName, $cellDataBand ) { $bs = $bandString === '?' ? $mainBandName : $bandString; if (!isset($tableBands->ofName[$bs])) { return []; } $from = $tableBands->ofName[$bs]->index; $bs2 = $band2String === '?' ? $mainBandName : $band2String; if (!isset($tableBands->ofName[$bs2])) { return []; } $to = $tableBands->ofName[$bs2]->index; if ($from === $to && isset($cellDataBand) && $cellDataBand->n === $bs) { return [$cellDataBand]; } if ($from > $to) { $temp = $from; $from = $to; $to = $temp; } $result = []; for ($i = $from; $i <= $to; ++$i) { $tableBand = $tableBands->ofIndex[$i]; if ($gravityBand) { $gravityTableBand = $gravityBand->tableBand; if ($gravityTableBand === $tableBand) { $result[] = $gravityBand; continue; } if ( Table::isParentBand($tableBand, $gravityTableBand) ) { $result[] = DataSet::findParentBand($gravityBand, $tableBand); continue; } if (isset($gravityTableBand->children) && Table::isChildrenBand($tableBand, $gravityTableBand) ) { array_add($result, DataSet::findChildrenBands($gravityBand, $tableBand->nm) ); continue; } } array_add($result, $dataBands->ofName[$tableBand->nm]); } return $result; } public function getMacroValue ($macro, $fullAnalytics = null) { static $monthNames = [ 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря' ]; $arr = safe_explode('.', $macro); $major = $arr[0]; $minor = $arr[1]; $date = $this->parsedDate; $period = $this->table->pd; switch ($major) { case 'таблица': switch ($minor) { case 'дата': return $date->month; case 'датаследующегопериода': $y = $date->year; $di = $date->month % 12; $ny = $di != 0 ? $y : $y + 1; return "1 $monthNames[$di] $ny года"; case 'организация': return $this->enterpriseFullName ?: $this->enterpriseName; case 'организациякратко': return $this->enterpriseName; case 'адрес': return $this->address.($this->phone ? ', тел. '.$this->phone : ''); case 'телефон': return $this->phone; case 'еи': return 'руб.'; case 'периодичность': switch ($period) { case 1: return 'месячная'; case 3: return $date->month !== 12 ? 'квартальная' : 'годовая'; case 6: return $date->month !== 12 ? 'полугодовая' : 'годовая'; case 12: return 'годовая'; } break; case 'год': return $date->year; case 'полугодие': return $period >= 1 && $period <= 6 ? intdiv($date->month - 1, 6) + 1 : 0; case 'квартал': return $period >= 1 && $period <= 3 ? intdiv($date->month - 1, 3) + 1 : 0; case 'месяц': return $period == 1 ? $date->month : 0; } break; case 'аналитика': if (isset($fullAnalytics) && is_array($fullAnalytics)) { $ret = ''; foreach ($fullAnalytics as $a) { $n = mb_strtolower($a->n, mb_detect_encoding($a->n)); if ($n == $minor) { if (isset($a->v) && is_array($a->v)) { $c = count($a->v); $cnt = 0; foreach ($a->v as $v) { if ($v->c) { ++$cnt; $ret .= ($ret ? ', ' : '') . $v->v; } } if ($c != 1 && $c == $cnt) { return null; } } break; } } return $ret ?: null; } $analytics = $this->analytics; return isset($analytics->{$minor}) ? $analytics->{$minor} : null; case 'организация': switch ($minor) { case 'название': return $this->enterpriseFullName ?: $this->enterpriseName; case 'названиекратко': return $this->enterpriseName; case 'адрес': return $this->address.($this->phone ? ', тел. '.$this->phone : ''); case 'телефон': return $this->phone; case 'унк': return $this->codeUnk; } break; case 'исполнитель': $profile = $this->userProfile ?: getSessionValue('userProfile'); switch ($minor) { case 'полноефио': return isset($profile->fio) ? transformFio($profile->fio, 'fullfio') : ''; case 'фио': return isset($profile->fio) ? transformFio($profile->fio, 'fio') : ''; case 'иоф': return isset($profile->fio) ? transformFio($profile->fio, 'iof') : ''; case 'должность': return isset($profile->post) ? $profile->post : ''; case 'контакты': return isset($profile->contacts) ? $profile->contacts : ''; } break; case 'руководитель': switch ($minor) { case 'полноефио': return transformFio($this->director, 'fullfio'); case 'фио': return transformFio($this->director, 'fio'); case 'иоф': return transformFio($this->director, 'iof'); } break; case 'главныйбухгалтер': switch ($minor) { case 'полноефио': return transformFio($this->accountant, 'fullfio'); case 'фио': return transformFio($this->accountant, 'fio'); case 'иоф': return transformFio($this->accountant, 'iof'); } break; } return null; } } 