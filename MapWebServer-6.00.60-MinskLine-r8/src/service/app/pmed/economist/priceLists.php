<?php
 require_once(__DIR__ . '/../../init.php'); class pmed_economist_pricelists { private $db; public function readPriceListKinds ($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpiseCode = (int) getSessionValue('mapEnterpriseCode'); $enterpriseStructureCode = (int) getSessionValue('EnterpriseStructureCode', 0); $sql = <<<"EOT"
select
    pmsPriceListKindCode as "id",
    Name as "name",
    Active as "active",
    EnterpriseStructureCode as "filialId"
from pmsPriceListKind
where EnterpriseCode = :enterpriseCode
 and (:enterpriseStructureCode = 0
    or EnterpriseStructureCode = :enterpriseStructureCode
    or EnterpriseStructureCode = 0
)
EOT;
return selectSql($db, $sql, [ ':enterpriseCode' => $enterpiseCode, ':enterpriseStructureCode' => $enterpriseStructureCode ]); } public function createPriceListKinds ($records) { $db = $this->db ?: $this->db = connectDb(); $enterpiseCode = (int) getSessionValue('mapEnterpriseCode'); return insertSql($db, 'pmsPriceListKind', $records, "EnterpriseCode, EnterpriseStructureCode, Name, Active", "filialId, name, active", "$enterpiseCode, coalesce(?, 0), ?, ?" ); } public function updatePriceListKinds ($records) { $db = $this->db ?: $this->db = connectDb(); return updateSql($db, 'pmsPriceListKind', 'pmsPriceListKindCode', $records, "EnterpriseStructureCode, Name, Active", "filialId, name, active" ); } public function deletePriceListKinds ($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsPriceListKind', 'pmsPriceListKindCode', $records); } public function readPriceLists ($params = null) { $db = $this->db ?: $this->db = connectDb(); $enterpiseCode = (int) getSessionValue('mapEnterpriseCode'); $enterpriseStructureCode = (int) getSessionValue('EnterpriseStructureCode', 0); $kindId = (int) isEmptyProperty($params, 'kindId'); $onDate = dbDate(isEmptyProperty($params, 'onDate', date("Y-m-d"))); $active = (bool) isEmptyProperty($params, 'active'); $sql = <<<"EOT"
with recursive rpl (pmsPriceListCode) as (
    select
    pmsPriceListCode
    from pmsPriceList
    where pmsPriceListKindCode = :kindId
        and pmsPriceListCodeParent is null
    union all
    select
        pl.pmsPriceListCode
    from rpl
    join pmsPriceList as pl on pl.pmsPriceListCodeParent = rpl.pmsPriceListCode
    where pmsPriceListKindCode = :kindId
)
select
    pl.SortCode as "sortCode",
    pl.pmsPriceListCode as "id",
    pl.Active as "active",
    pl.Code as "code",
    pl.FullCode as "fullCode",
    pl.Kind as "kind",
    pl.Name as "name",
    pl.pmsPriceListCodeParent as "parentId",
    pl.pmsPriceListKindCode as "kindId",
    case pl.Kind when 2 then 1 else 2 end as "rowLevel",
    pl.Active as "realActive",
    0 as "isWealth",
    coalesce(pls.OnDate, :onDate) as "onDate",
    null as "priceListWealthId",
    null as "wealthId",
    null as "wealthAmount",
    pls.Tariff as "sumTariff",
    pls.MaterialSum as "sumMatCosts",
    pls.AsideServiceSum as "sumThirdParty",
    pls.ServicePrice as "sumServicePrice",
    (select json_object('',
        json_numeric('tariffTariff', pls1.Tariff) ||
        json_numeric('tariffSum', pls1.TariffSum) ||
        json_numeric('tariffVatRate', pls1.TariffVatRate) ||
        json_numeric('tariffVatSum', pls1.TariffVatSum) ||
        json_numeric('materialSum', pls1.MaterialSum) ||
        json_numeric('materialSumWithoutVat', pls1.MaterialSumWithoutVat) ||
        json_numeric('materialSumWithVat', pls1.MaterialSumWithVat) ||
        json_numeric('asideServiceSum', pls1.AsideServiceSum) ||
        json_numeric('asideServiceSumVat', pls1.AsideServiceSumVat) ||
        json_numeric('servicePrice', pls1.ServicePrice, 0), 0)
     from pmsPriceList as pl1
     left join pmsPriceListSum as pls1 on pls1.pmsPriceListSumCode = pls.pmsPriceListSumCode
     where pl1.pmsPriceListCode = pl.pmsPriceListCode
        and pl1.Kind = 1
    ) as "sumData",
    pls.pmsPriceListSumCode as "priceListSumId",
    null as "wealthPriceInfo",
    0 as "cancelWealthPrice"
from rpl
join pmsPriceList as pl on pl.pmsPriceListCode = rpl.pmsPriceListCode
left join pmsPriceListSum as pls on pls.pmsPriceListCode = pl.pmsPriceListCode
    and Ondate = (
        select max(OnDate)
        from pmsPriceListSum
        where pmsPriceListCode = pls.pmsPriceListCode
            and OnDate <= :onDate
    )
union all
select
    pl.SortCode || '1' as "sortCode",
    null as "id",
    plw.Active as "active",
    null as "code",
    null as "fullCode",
    null as "kind",
    fn_pmsMatWealthName(plw.MatWealthCode, 3) as "name",
    pl.pmsPriceListCode as "parentId",
    null as "kindId",
    3 as "rowLevel",
    plw.Active as "realActive",
    1 as "isWealth",
    null as "onDate",
    plw.pmsPriceListWealthCode as "priceListWealthId",
    plw.MatWealthCode as "wealthId",
    plw.Amount as "wealthAmount",
    null as "sumTariff",
    pt."price" * coalesce(plw.Amount, 1) as "sumMatCosts",
    null as "sumThirdParty",
    pt."price" * coalesce(plw.Amount, 1) as "sumServicePrice",
    null as "sumData",
    null as "priceListSumId",
    (select json_object('',
        json_numeric('id', pwp1.pmsWealthPriceCode) ||
        json_numeric('price', pwp1.Price) ||
        json_numeric('vat', pwp1.Vat) ||
        json_numeric('tradingCost', pwp1.TradingCost, 0), 0)
     from pmsWealthPrice as pwp1
     where pwp1.pmsWealthPriceCode = pt.pmsWealthPriceCode
        and pwp1.Active = 1
    ) as "wealthPriceInfo",
    case coalesce(pt.pmsWealthPriceCode, 0) = 0
        when true then 0
        else coalesce(pt.active, 0) # 1
    end as "cancelWealthPrice"
from rpl
join pmsPriceList as pl on pl.pmsPriceListCode = rpl.pmsPriceListCode
join pmsPriceListWealth as plw on plw.pmsPriceListCode = rpl.pmsPriceListCode
    and plw.Kind = 1
join MatWealth as w on w.MatWealthCode = plw.MatWealthCode
left join lateral (
    select
        pwp.pmsWealthPriceCode,
        case coalesce(pwp.active, 0) = 1
            when true then pwp.Price
            else null
        end as "price",
        pwp.Active
    from pmsWealthPrice as pwp
    where pwp.EnterpriseCode = :enterpriseId
        and pwp.EnterpriseStructureCode = :filialId
        and pwp.MatWealthCode in (
            select MatWealthCode
            from MatWealth
            where MatWealthObjectCode = w.MatWealthObjectCode
                and Active = 1
        )
        and pwp.OnDate = (
            select max(Ondate)
            from pmsWealthPrice
            where EnterpriseCode = pwp.EnterpriseCode
                and EnterpriseStructureCode = pwp.EnterpriseStructureCode
                and MatWealthCode in (
                    select MatWealthCode
                    from MatWealth
                    where MatWealthObjectCode = w.MatWealthObjectCode
                        and Active = 1
                )
                and OnDate <= :onDate
        )
    order by pwp.pmsWealthPriceCode
    limit 1
) as pt on true
order by 1, "name"
EOT;
$records = selectSql($db, $sql, [ 'kindId' => $kindId, 'enterpriseId' => $enterpiseCode, 'filialId' => $enterpriseStructureCode, 'onDate' => $onDate ]); if ($records) { for ($i = count($records) - 1; $i > -1; $i--) { if (!$active && !$records[$i]['active'] && !empty($records[$i]['parentId'])) { for ($j = $i; $j > -1; $j--) { if ($records[$j]['id'] === $records[$i]['parentId']) { $records[$j]['active'] = null; } } } } } return $records; } public function createPriceLists ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
insert into pmsPriceList (
    Active, Code, Kind, Name,
    pmsPriceListCodeParent, pmsPriceListKindCode
) values (
    ?, ?, ?, ?,
    ?, ?
)
EOT;
$stmt = $db->prepare($sql); $sqlWealth = <<<"EOT"
insert into pmsPriceListWealth (
    Active, Amount, Kind, MatWealthCode, pmsPriceListCode
) values (
    ?, ?, 1, ?, ?
)
EOT;
$stmtWealth = $db->prepare($sqlWealth); $id = 0; $db->beginTransaction(); try { foreach ($records as $row) { if ($row->isWealth == 0) { $stmt->execute([ isEmptyProperty($row, 'active'), isEmptyProperty($row, 'code'), isEmptyProperty($row, 'kind'), isEmptyProperty($row, 'name'), isEmptyProperty($row, 'parentId'), isEmptyProperty($row, 'kindId') ]); $id = $db->lastInsertId('seq_pmsPriceList'); } else { $id = isEmptyProperty($row, 'parentId', $id); if ($id > 0) { $stmtWealth->execute([ isEmptyProperty($row, 'active'), isEmptyProperty($row, 'wealthAmount'), isEmptyProperty($row, 'wealthId'), $id ]); } } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $id; } public function updatePriceLists ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
update pmsPriceList set
    Active = case when ? then ? else Active end,
    Code = case when ? then ? else Code end,
    Name = case when ? then ? else Name end
where pmsPriceListCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlWealth = <<<"EOT"
update pmsPriceListWealth set
    Active = case when ? then ? else Active end,
    Amount = case when ? then ? else Amount end,
    MatWealthCode = case when ? then ? else MatWealthCode end
where pmsPriceListWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row) { if ($row->isWealth == 0) { $stmt->execute([ propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), $row->id ]); if (property_exists($row, 'sumData')) { $onDate = dbDate(isUndefinedProperty($row, 'onDate', date("Y-m-d"))); $priceSumId = (int) selectSqlValue($db, 'select pmsPriceListSumCode from pmsPriceListSum where pmsPriceListCode = :priceId and OnDate = :onDate limit 1', ['priceId' => $row->id, 'onDate' => $onDate] ); $sumData = json_clean_decode(isUndefinedProperty($row, 'sumData')); if ($priceSumId > 0) { $sumData->id = $priceSumId; updateSql($db, 'pmsPriceListSum', 'pmsPriceListSumCode', $sumData, "Tariff, TariffSum, TariffVatRate, TariffVatSum, MaterialSum, MaterialSumWithoutVat, MaterialSumWithVat, AsideServiceSum, AsideServiceSumVat, ServicePrice", "tariffTariff, tariffSum, tariffVatRate, tariffVatSum, materialSum, materialSumWithoutVat, materialSumWithVat, asideServiceSum, asideServiceSumVat, servicePrice", "id", false ); } else { insertSql($db, 'pmsPriceListSum', $sumData, "pmsPriceListCode, OnDate, Tariff, TariffSum, TariffVatRate, TariffVatSum, MaterialSum, MaterialSumWithoutVat, MaterialSumWithVat, AsideServiceSum, AsideServiceSumVat, ServicePrice", "tariffTariff, tariffSum, tariffVatRate, tariffVatSum, materialSum, materialSumWithoutVat, materialSumWithVat, asideServiceSum, asideServiceSumVat, servicePrice", "{$row->id}, '$onDate', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?", null, false ); } } } else { $stmtWealth->execute([ propertyExistsSQL($row, 'active'), isUndefinedProperty($row, 'active'), propertyExistsSQL($row, 'wealthAmount'), isUndefinedProperty($row, 'wealthAmount'), propertyExistsSQL($row, 'wealthId'), isEmptyProperty($row, 'wealthId'), $row->priceListWealthId ]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deletePriceLists ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<"EOT"
delete from pmsPriceList
where pmsPriceListCode = ?
EOT;
$stmt = $db->prepare($sql); $sqlWealth = <<<"EOT"
delete from pmsPriceListWealth
where pmsPriceListWealthCode = ?
EOT;
$stmtWealth = $db->prepare($sqlWealth); $db->beginTransaction(); try { foreach ($records as $row) { if ($row->isWealth == 0) { $stmt->execute([$row->id]); } else { $stmtWealth->execute([$row->priceListWealthId]); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteSumsByDate($params) { $db = $this->db ?: $this->db = connectDb(); $kindId = (int) isEmptyProperty($params, 'kindId'); $onDate = dbDate(isEmptyProperty($params, 'onDate')); $db->beginTransaction(); try { $sql = <<<"EOT"
delete from pmsPriceListSum as pls1
using pmsPriceList as pl
join pmsPriceListSum as pls2 on pls2.pmsPriceListCode = pl.pmsPriceListCode
where pls1.pmsPriceListSumCode = pls2.pmsPriceListSumCode
    and pl.pmsPriceListKindCode = :kindId
    and pl.Kind = 1
    and pl.Active = 1
    and pls2.OnDate = :onDate
EOT;
executeSql($db, $sql, ['kindId' => $kindId, 'onDate' => $onDate]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function updateSumsByDate($params) { $db = $this->db ?: $this->db = connectDb(); $kindId = (int) isEmptyProperty($params, 'kindId'); $onDate = dbDate(isEmptyProperty($params, 'onDate')); $toDate = dbDate(isEmptyProperty($params, 'toDate')); $db->beginTransaction(); try { $sql = <<<"EOT"
update pmsPriceListSum as pls1 set
    OnDate = :toDate
from pmsPriceList as pl
join pmsPriceListSum as pls2 on pls2.pmsPriceListCode = pl.pmsPriceListCode
where pls1.pmsPriceListSumCode = pls2.pmsPriceListSumCode
    and pl.pmsPriceListKindCode = :kindId
    and pl.Kind = 1
    and pl.Active = 1
    and pls2.OnDate = :onDate
EOT;
executeSql($db, $sql, ['kindId' => $kindId, 'onDate' => $onDate, 'toDate' => $toDate]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function readPriceListHistory($params) { $db = $this->db ?: $this->db = connectDb(); $priceListId = (int) isEmptyProperty($params, 'priceListId'); $sql = <<<"EOT"
select
    pls.pmsPriceListSumCode as "id",
    pls.OnDate as "onDate",
    pls.Tariff as "tariffTariff",
    pls.MaterialSum as "materialSum",
    pls.AsideServiceSum as "asideServiceSum",
    pls.ServicePrice as "servicePrice",
    pl.Name as "name",
    pl.Code as "code",
    pl.FullCode as "fullCode",
    (select json_object('',
        json_numeric('tariffTariff', pls1.Tariff) ||
        json_numeric('tariffSum', pls1.TariffSum) ||
        json_numeric('tariffVatRate', pls1.TariffVatRate) ||
        json_numeric('tariffVatSum', pls1.TariffVatSum) ||
        json_numeric('materialSum', pls1.MaterialSum) ||
        json_numeric('materialSumWithoutVat', pls1.MaterialSumWithoutVat) ||
        json_numeric('materialSumWithVat', pls1.MaterialSumWithVat) ||
        json_numeric('asideServiceSum', pls1.AsideServiceSum) ||
        json_numeric('asideServiceSumVat', pls1.AsideServiceSumVat) ||
        json_numeric('servicePrice', pls1.ServicePrice, 0), 0)
     from pmsPriceList as pl1
     left join pmsPriceListSum as pls1 on pls1.pmsPriceListSumCode = pls.pmsPriceListSumCode
     where pl1.pmsPriceListCode = pl.pmsPriceListCode
        and pl1.Kind = 1
    ) as "sumData"
from pmsPriceList as pl
join pmsPriceListSum as pls on pls.pmsPriceListCode = pl.pmsPriceListCode
where pl.pmsPriceListCode = :priceListId
order by 2 desc
EOT;
return selectSql($db, $sql, ['priceListId' => $priceListId]); } public function deletePriceListHistory($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'pmsPriceListSum', 'pmsPriceListSumCode', $records); } public function updatePriceListHistory ($records) { $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { if (property_exists($row, 'sumData')) { $sumData = json_clean_decode(isUndefinedProperty($row, 'sumData')); $sumData->id = $row->id; updateSql($db, 'pmsPriceListSum', 'pmsPriceListSumCode', $sumData, "Tariff, TariffSum, TariffVatRate, TariffVatSum, MaterialSum, MaterialSumWithoutVat, MaterialSumWithVat, AsideServiceSum, AsideServiceSumVat, ServicePrice", "tariffTariff, tariffSum, tariffVatRate, tariffVatSum, materialSum, materialSumWithoutVat, materialSumWithVat, asideServiceSum, asideServiceSumVat, servicePrice", "id", false ); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('pmed_economist_pricelists');