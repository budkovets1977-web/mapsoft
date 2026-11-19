<?php
 require_once(__DIR__ . '/../../init.php'); define("C_DELIMITER_SYMBOLS_OR", "|\\/"); define("C_DELIMITER_SYMBOLS_AND", "+"); define("C_NEEDEQUALITY_SYMBOL", "^"); class map_med_drugs { const PAGE_SIZE = 30; private $db; public function readDrugs($params) { function parseIncoming(&$aSearch, $aSearchEl, $aIdx, $aOne = false) { for ($i = ($aOne ? 1 : 0), $cnt = count($aSearch); $i < $cnt; $i++) { if ($i !== $aIdx) { if (safe_strpos($aSearchEl, C_NEEDEQUALITY_SYMBOL) !== 0 && safe_strpos($aSearch[$i], $aSearchEl) === 0) { $aSearch[$aIdx] = C_NEEDEQUALITY_SYMBOL . $aSearch[$aIdx]; } } } return true; } function checkDelimiter($aStr) { $matches = null; preg_match('/[' . C_DELIMITER_SYMBOLS_OR . C_DELIMITER_SYMBOLS_AND . ']+/', $aStr, $matches, PREG_OFFSET_CAPTURE); return $matches; } function parse3Level($aStr) { $phrase = safe_explode(C_DELIMITER_SYMBOLS_AND, $aStr); $str = ''; $cnt = count($phrase); if ($cnt === 1) { $str = (safe_strpos($phrase[0], C_NEEDEQUALITY_SYMBOL) === 0 ? substr($phrase[0], 1) : $phrase[0] . ':*'); } else { $idx = 0; foreach ($phrase as $el) { if ($el !== '') { $idx++; $pos = safe_strpos($el, C_NEEDEQUALITY_SYMBOL); $str .= ($idx === 1 ? '(' : ' & ') . ($pos === 0 ? substr($el, 1) : $el . ':*') . ($idx === $cnt ? ')' : ''); } } } return $str; } function parse2Level($aStr) { $phrase = preg_split("/[" . C_DELIMITER_SYMBOLS_OR . "]+/u", $aStr); $strName = ''; $cnt = count($phrase); if ($cnt === 1) { $strName = parse3Level($phrase[0]); } else { $idx = 0; foreach ($phrase as $el) { if ($el !== '') { $idx++; $str = parse3Level($el); $strName .= ($idx === 1 ? '(' : ' | ') . $str . ($idx === $cnt ? ')' : ''); } } } return $strName; } function getTotalCount($aRet) { return isset($aRet[0]["full_count"]) ? $aRet[0]["full_count"] : 0; } $db = $this->db ?: $this->db = connectDb(); $sqlArr = array( "sqlCTE" => "", "/* optimization */", "sqlOptimizedSelect" => "", "/* optimization */", "select", "sqlFields" => "", "/* ExtraInfo */", "sqlFieldExtraInfo" => "", "/* ExtraInfo */", "sqlFieldCount" => "", "sqlFrom" => "", "/* ExtraInfo */", "sqlFromExtraInfo" => "", "/* ExtraInfo */", "sqlWhere" => "", "sqlOrder" => "", "sqlLimit" => "", "/* optimization */", "sqlOptimizedJoin" => "", "/* optimization */" ); $enterpriseId = getSessionValue('mapEnterpriseCode'); $search = isEmptyProperty($params, 'search'); $wealthId = isUndefinedProperty($params, 'wealthId'); $wealthId = empty($wealthId) ? isUndefinedProperty($params, 'id') : $wealthId; $isAmbulance = isEmptyProperty($params,'isAmbulance'); $taskNumbersSql = ''; if ($wealthId === 0) { return false; } $simpleType = (string) $search === ''; if (!$simpleType && mb_strlen($search) < 3) { return false; } $sort = isEmptyProperty($params, 'sort', $simpleType ? "id" : "displayName"); $dir = isEmptyProperty($params, 'dir', ''); $limit = isEmptyProperty($params, 'limit'); $maxRecords = isEmptyProperty($params, 'maxRecords'); $extraInfo = isEmptyProperty($params, 'extraInfo'); $arrSearch = safe_explode(' ', $search); $idx = 0; foreach ($arrSearch as $searchEl) { if ($searchEl !== '') { $idx++; if (empty(checkDelimiter($searchEl))) { parseIncoming($arrSearch, $searchEl, $idx - 1, true); } else { $tmpStr = ""; $phrase = preg_split("/[" . C_DELIMITER_SYMBOLS_OR . "]+/u", $searchEl); $idx1 = 0; foreach ($phrase as $el) { $tmpStr1 = ""; if (checkDelimiter($el)) { $phrase1 = safe_explode(C_DELIMITER_SYMBOLS_AND, $el); $idxEl = 0; foreach ($phrase1 as $el1) { parseIncoming($phrase1, $el1, $idxEl); $tmpStr1 .= ($idxEl === 0 ? '' : '+') . $phrase1[$idxEl]; $idxEl++; } } $tmpStr .= ($idx1 === 0 ? '' : '|') . (empty($tmpStr1) ? $el : $tmpStr1); $idx1++; } $tmpStr !== "" && $arrSearch[$idx - 1] = $tmpStr; } } } $textSearchName = $textSearchFeature = ""; $idx = 0; foreach ($arrSearch as $searchEl) { if ($searchEl !== '') { $idx++; if ($idx == 1) { $textSearchName = parse2Level($searchEl); } else { $tmpStr = parse2Level($searchEl); $textSearchFeature .= ($idx === 2 ? '' : ' & ') . ($tmpStr[0] === '(' ? '' : '(') . $tmpStr . ($tmpStr[safe_strlen($tmpStr) - 1] === ')' ? '' : ')'); } } } if ($extraInfo) { $extrInfoStr = <<<"EOT"
       COALESCE(MW.Vitality, MWO.Vitality, 0) "vitality", /*льготируется*/
       (select
              array_to_string(ARRAY(select DISTINCT /* так будет немного быстрее */
                                    UNNEST(array_agg((case
                                                           when _WM.CountryCode is null then ''
                                                           else _C.Name || ', '
                                                      end) ||
                                                      COALESCE(_WM.Manufacturer, '') ||
                                                      ', регистрация ' || COALESCE(_WM.RegistryNumber, '') ||
                                                      ' от ' || DATEFORMAT(_WM.RegistryDate, 'dd.mm.yyyy') ||
                                                      ' действительна по ' || DATEFORMAT(_WM.TermDate, 'dd.mm.yyyy')
                                                      order by COALESCE(_C.Name, '') NULLS FIRST,
                                                      _WM.Manufacturer NULLS FIRST))
                                     order by 1
                                     ), chr(13) || chr(10)) ManufList
        from matWealthManufacturer _WM
             left outer join Country _C on _C.CountryCode = _WM.CountryCode
        where COALESCE(_WM.RegistryDate, 19000101) > 19000101
          and COALESCE(_WM.TermDate, 19000101) >= ((clock_timestamp()::timestamp))
          and _WM.matWealthCode = MW.matWealthCode) "manufList", /*производители*/
       MW.Note "note" /*аннотация*/
EOT;
if ($limit) { $sqlArr["sqlOptimizedSelect"] = <<<"EOT"
select
       t.*,
       {$extrInfoStr}
from (
EOT;
$extrInfoStr = ""; $sqlArr["sqlOptimizedJoin"] = <<<"EOT"
) t
      join MatWealth MW on MW.matWealthCode = t."id"
      join matWealthObject MWO on MWO.matWealthObjectCode = MW.matWealthObjectCode
EOT;
} $sqlArr["sqlFieldExtraInfo"] = <<<"EOT"
       , COALESCE(MW.SaleCondition, 0) "saleCondition", /*по рецепту*/
       {$extrInfoStr}
       U.Name "groupFarmName", /*фармгруппа*/
       WG.Name "groupName" /*группа*/
EOT;
$sqlArr["sqlFromExtraInfo"] = <<<"EOT"
    left join Uni U on U.UniCode = MW.UniCodeGroupFarm
                   and U.Dict = 2
    left join matWealthGroup WG on WG.matWealthGroupCode = MWO.MatWealthGroupCode
EOT;
} $limit && $sqlArr["sqlFieldCount"] = "      , COUNT(*) OVER() \"full_count\""; $sqlUnionAnalogy = isUndefinedProperty($params, 'notAnalogy') ? "" : <<<"EOT"
union all
select
       AW.matWealthCode,
       0 original
from ANALOGY_WEALTH AW
EOT;
$sqlArr["sqlFields"] = <<<"EOT"
       MW.matWealthCode "id",
       MWO.Name "name",
       MWN.Name12 "feature",
       MWO.secondname "inn",
       MWO.matWealthObjectCode "objectId",
       MWN.name0 "displayName",
       MWN.name0_1 "displayNameInn",
       MWO.fullname "fullName"
EOT;
if ($isAmbulance) { $taskNumbersSql = " and WordIntersect(WS.taskNumbers, ',530,531,', ',') <> '' "; } else { $taskNumbersSql = " and WordIntersect(WS.taskNumbers, ',11,13,40,50,55,', ',') <> '' "; } if ($simpleType) { $sqlArr["sqlFrom"] = <<<"EOT"
from matWealth MW
     join matWealthObject MWO on MWO.matWealthObjectCode = MW.matWealthObjectCode
     join MatWealthNames MWN on MWN.matWealthCode = MW.MatWealthCode
     /* условия на выборку группы медикаментов */
     join matWealthSection WS on WS.matWealthSectionCode = MWO.matWealthSectionCode
                             and WS.enterpriseCode = $enterpriseId
                             and WS.sectionType is null
                             and WS.formType = any(ARRAY[3, 5])
                             $taskNumbersSql
EOT;
} else { $sqlTextSearch = empty($textSearchFeature) ? "" : "and MW.textSearch @@ to_tsquery((select c_tsearch from TEXTSEARCH_FEATURE))"; $sqlArr["sqlCTE"] = <<<"EOT"
with TEXTSEARCH_NAME as (
select '{$textSearchName}'::text c_tsearch
), TEXTSEARCH_FEATURE as (
select '{$textSearchFeature}'::text c_tsearch
), ORIGINAL_WEALTH as (
select
       MW.matWealthCode,
       COALESCE(MWO.secondName, '') secondName
from matWealth MW
    join matWealthObject MWO on MWO.matWealthObjectCode = MW.matWealthObjectCode
    /* условия на выборку группы медикаментов */
    join matWealthSection WS on WS.matWealthSectionCode = MWO.matWealthSectionCode
                            and WS.enterpriseCode = $enterpriseId
                            and WS.sectionType is null
                            and WS.formType = any(ARRAY[3, 5])
                            $taskNumbersSql
/* текстовый поиск по оригиналам (наименование) ИЛИ простой LIKE поиск */
where (MW.textSearch @@ to_tsquery((select c_tsearch from TEXTSEARCH_NAME)) OR (MWO.Name ILIKE '%{$search}%' OR MWO.fullname ILIKE '%{$search}%' OR MWO.secondname ILIKE '%{$search}%'))
), ANALOGY_WEALTH as (
select
       MW.matWealthCode
from (select distinct
             secondName
      from ORIGINAL_WEALTH
      where secondName <> '') OWN
    join matWealthObject MWO on MWO.secondName = OWN.secondName
    join matWealth MW on MW.matWealthObjectCode = MWO.matWealthObjectCode
                     /* текстовый поиск по аналогам (характеристики) */
                     {$sqlTextSearch}
    /* условия на выборку группы медикаментов */
    join matWealthSection WS on WS.matWealthSectionCode = MWO.matWealthSectionCode
                            and WS.enterpriseCode = $enterpriseId
                            and WS.sectionType is null
                            and WS.formType = any(ARRAY[3, 5])
                            $taskNumbersSql
    /* отсекаем оригиналы */
    left join ORIGINAL_WEALTH OW on OW.matWealthCode = MW.matWealthCode
/*  */
where OW.matWealthCode is null
)
EOT;
$sqlArr["sqlFrom"] = <<<"EOT"
from (select
             OW.matWealthCode,
             1 original
      from ORIGINAL_WEALTH OW
           join MatWealth MW on MW.matWealthCode = OW.matWealthCode
      where 1 = 1
        /* текстовый поиск по оригиналам (характеристики) */
        {$sqlTextSearch}
        /* поиск по аналогам */
        {$sqlUnionAnalogy}
     ) WEALTHS
    join matWealthNames MWN on MWN.matWealthCode = WEALTHS.matWealthCode
    join MatWealth MW on MW.matWealthCode = WEALTHS.matWealthCode
    join matWealthObject MWO on MWO.matWealthObjectCode = MW.matWealthObjectCode
EOT;
} $vitality = isEmptyProperty($params, 'vitality') ? "and COALESCE(MW.Vitality, MWO.Vitality, 0) <> 0" : ""; $saleCondition = isEmptyProperty($params, 'saleCondition'); $notSaleCondition = isEmptyProperty($params, 'notSaleCondition'); $saleCondition = $saleCondition && $notSaleCondition ? "" : ($saleCondition ? "and COALESCE(MW.SaleCondition, 0) = 1" : ($notSaleCondition ? " and COALESCE(MW.SaleCondition, 0) = 0" : "")); $registartionIsValid = isEmptyProperty($params, 'registartionIsValid') ? " and COALESCE(TermDate, 19000101) >= clock_timestamp()::timestamp" : ""; $native = !isEmptyProperty($params, 'native') ? "" : <<<"EOT"
  and CountryCode = (select CountryCode
                     from Enterprise
                     where EnterpriseCode = $enterpriseId
                     limit 1)
EOT;
$byManufacturer = !($registartionIsValid || $native) ? "" : <<<"EOT"
  and EXISTS(select 1
             from MatWealthManufacturer
             where MatWealthCode = MW.MatWealthCode
               /* регистрация действует */
               $registartionIsValid
               /* отечественный */
               $native
             limit 1)
EOT;
$byDiagnosis = !isEmptyProperty($params, 'diagnosisId') ? "" : <<<"EOT"
  and EXISTS(select 1
             from MedPrivilegeDiagPharmGroup PDPG
                  join MedPrivilegeDiagnosis PD on PD.MedPrivilegeDiagnosisCode = PDPG.MedDiagnosisPrivilegeCode
                                               and PD.EnterpriseCode = $enterpriseId
             where PDPG.UniCodeGroupFarm = MW.UniCodeGroupFarm
               and FN_MedDiagnosisInList(PD.Cipher, 0)
             limit 1)
EOT;
$byWealth = $wealthId ? "and MW.MatWealthCode = {$wealthId}" : ""; $sqlArr["sqlWhere"] = <<<"EOT"
where 1 = 1
  /* по ценности */
  {$byWealth}
  /* льготруемый */
  {$vitality}
  /* рецептурный */
  {$saleCondition}
  /* отечественный|действующая регистрация */
  {$byManufacturer}
  /* по диагнозу */
  {$byDiagnosis}
EOT;
if ($simpleType) { $sqlArr["sqlOrder"] = "order by \"{$sort}\" {$dir}"; } else { $sqlArr["sqlOrder"] = "order by WEALTHS.original desc, \"{$sort}\" {$dir}"; } if ($limit) { $limit = $limit ? $limit : self::PAGE_SIZE; $offset = isUndefinedProperty($params, 'start', 0); $sqlArr["sqlLimit"] = "limit {$limit} offset {$offset}"; } else if ($maxRecords) { $sqlArr["sqlLimit"] = "limit {$maxRecords}"; } $ret = selectSql($db, implode("\n", $sqlArr)); if ($limit) { $root = new stdClass(); $root->total = getTotalCount($ret); if ($root->total > 0 && $root->total < $limit && $offset > 0) { $sqlArr["sqlLimit"] = "limit {$limit} offset 0"; $ret = selectSql($db, implode("\n", $sqlArr)); $root->total = getTotalCount($ret); } $root->items = $ret; return $root; } return $ret; } public function readDrugInfo($params) { $db = $this->db ?: $this->db = connectDb(); if (!isset($params->matWealthCode) || !$params->matWealthCode) { return; } $matWealthCode = $params->matWealthCode; $enterpriseId = getSessionValue('mapEnterpriseCode'); $sql = <<<"EOT"
select
    w.MatWealthCode "id",
    w.DoseSize "doseSize",
    w.DoseNumber "doseNumber",
    w.Substance "substance",
    w.MatWealthMeasureCode "measureId",
    w.MatWealthMeasureDoseCode "measureDoseId",
    w.MatWealthMeasureDoseSizeCode "doseSizeId",
    w.MatWealthMeasureCodeSubstance "measureIdSubstance",
    w.UniCodeGroupReg "uniCodeGroupReg",
    w.UniCodeGroupFarm "uniCodeGroupFarm",
    w.UniCodeStoragePlace "uniCodeStoragePlace",
    w.Feature "feature2",
    w.LinkCipher "linkCipher",
    wo.MatWealthObjectCode "objectId",
    FN_cmMatWealthNameEx(w.MatWealthCode, 6) "displayName", /*наименование*/
    {$db->dialect("isnull", "COALESCE")}(w.SaleCondition, 0) "saleCondition", /*по рецепту*/
    {$db->dialect("isnull", "COALESCE")}(w.Vitality, 0) "vitality", /*льготируется*/
    {$db->dialect("(SELECT
                        LIST(DISTINCT IFNULL(WM.CountryCode, '', C.Name || ', ') || WM.Manufacturer || ', регистрация ' ||
                            WM.RegistryNumber || ' от ' || DATEFORMAT( WM.RegistryDate, 'dd.mm.yyyy') || ' действительна по ' ||
                            DATEFORMAT(WM.TermDate, 'dd.mm.yyyy'), CHAR(13) || CHAR(10)
                            ORDER BY ISNULL( C.Name, ''), WM.Manufacturer) as ManufList
                        FROM matWealthManufacturer as WM
                        left outer join Country as C ON C.CountryCode = WM.CountryCode
                        WHERE
                          ISNULL(WM.RegistryDate, 19000101) > 19000101 AND ISNULL(WM.TermDate, 19000101) >= now() /*только действующие*/
                    AND WM.matWealthCode = W.matWealthCode)", "(select
                        array_to_string_distinct(array_agg((case
                                                              when WM.CountryCode is null then E''
                                                              else C.Name || E', '
                                                            end) || WM.Manufacturer || E', регистрация ' || WM.RegistryNumber || E' от ' ||
                                                            DATEFORMAT(WM.RegistryDate, E'dd.mm.yyyy') || E' действительна по ' || DATEFORMAT(WM.TermDate, E'dd.mm.yyyy')
                                                 order by COALESCE(C.Name, E'') NULLS FIRST, WM.Manufacturer NULLS FIRST), chr(13) || chr(10)) as ManufList
                    from matWealthManufacturer as WM
                         left outer join Country as C on C.CountryCode = WM.CountryCode
                    where COALESCE (WM.RegistryDate, 19000101) > 19000101
                          and COALESCE (WM.TermDate, 19000101) >= ((clock_timestamp()::timestamp))
                          and WM.matWealthCode = W.matWealthCode
                    )")} "manufList", /*производители*/
    w.Note "note", /*аннотация*/
    u2.Name "groupFarmName", /*фармгруппа*/
    wg.Name as "groupName" /*группа*/
from (
    select
        wo1.MatWealthObjectCode,
        wo1.MatWealthSectionCode,
        wo1.SecondName,
        ws.EnterpriseCode
    from MatWealthObject wo1
    join MatWealthSection ws on ws.MatWealthSectionCode = wo1.MatWealthSectionCode
        and ws.EnterpriseCode = $enterpriseId /* текущая организация */
        and ws.SectionType is null
        and ws.FormType in (3, 5)
        and WordIntersect(ws.TaskNumbers, ',11,13,40,50,55,', ',') <> ''
    group by wo1.MatWealthObjectCode, wo1.MatWealthSectionCode,
        wo1.SecondName, ws.EnterpriseCode) wt
join MatWealthObject wo on wo.MatWealthSectionCode = wt.MatWealthSectionCode
    and (wo.MatWealthObjectCode = wt.MatWealthObjectCode)
join MatWealth w on w.MatWealthObjectCode = wo.MatWealthObjectCode and w.MatWealthCode=$matWealthCode
left outer join MatWealthNames  wn on wn.MatWealthCode = w.MatWealthCode
left outer join Uni AS u2 ON u2.UniCode = w.UniCodeGroupFarm AND u2.Dict = 2
left outer join matWealthGroup as wg ON wg.matWealthGroupCode = wo.MatWealthGroupCode
group by w.MatWealthCode, wo.MatWealthObjectCode, w.SaleCondition, w.Vitality, w.Note, u2.Name, wg.Name
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function createDrugs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO MatWealth
    (MatWealthObjectCode, MatWealthMeasureCode, Feature, DoseNumber, MatWealthMeasureDoseCode,
    DoseSize, MatWealthMeasureDoseSizeCode, Substance, MatWealthMeasureCodeSubstance,
    UniCodeGroupReg, UniCodeGroupFarm, UniCodeStoragePlace, LinkCipher, Vitality)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isEmptyProperty($row, 'objectId'), isEmptyProperty($row, 'measureId'), isEmptyProperty($row, 'feature2'), isEmptyProperty($row, 'doseNumber'), isEmptyProperty($row, 'measureDoseId'), isEmptyProperty($row, 'doseSize'), isEmptyProperty($row, 'doseSizeId'), isEmptyProperty($row, 'substance'), isEmptyProperty($row, 'measureIdSubstance'), isEmptyProperty($row, 'uniCodeGroupReg'), isEmptyProperty($row, 'uniCodeGroupFarm'), isEmptyProperty($row, 'uniCodeStoragePlace'), isEmptyProperty($row, 'linkCipher'), isEmptyProperty($row, 'vitality') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MatWealth'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updateDrugs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MatWealth SET
    MatWealthObjectCode = case when ? = 1 then ? else MatWealthObjectCode end,
    MatWealthMeasureCode = case when ? = 1 then ? else MatWealthMeasureCode end,
    Feature = case when ? = 1 then ? else Feature end,
    DoseNumber = case when ? = 1 then ? else DoseNumber end,
    MatWealthMeasureDoseCode = case when ? = 1 then ? else MatWealthMeasureDoseCode end,
    DoseSize = case when ? = 1 then ? else DoseSize end,
    MatWealthMeasureDoseSizeCode = case when ? = 1 then ? else MatWealthMeasureDoseSizeCode end,
    Substance = case when ? = 1 then ? else Substance end,
    MatWealthMeasureCodeSubstance = case when ? = 1 then ? else MatWealthMeasureCodeSubstance end,
    UniCodeGroupReg = case when ? = 1 then ? else UniCodeGroupReg end,
    UniCodeGroupFarm = case when ? = 1 then ? else UniCodeGroupFarm end,
    UniCodeStoragePlace = case when ? = 1 then ? else UniCodeStoragePlace end,
    LinkCipher = case when ? = 1 then ? else LinkCipher end,
    Vitality = case when ? = 1 then ? else Vitality end
WHERE MatWealthCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'objectId'), isUndefinedProperty($row, 'objectId'), propertyExistsSQL($row, 'measureId'), isEmptyProperty($row, 'measureId'), propertyExistsSQL($row, 'feature2'), isUndefinedProperty($row, 'feature2'), propertyExistsSQL($row, 'doseNumber'), isEmptyProperty($row, 'doseNumber'), propertyExistsSQL($row, 'measureDoseId'), isEmptyProperty($row, 'measureDoseId'), propertyExistsSQL($row, 'doseSize'), isEmptyProperty($row, 'doseSize'), propertyExistsSQL($row, 'doseSizeId'), isEmptyProperty($row, 'doseSizeId'), propertyExistsSQL($row, 'substance'), isEmptyProperty($row, 'substance'), propertyExistsSQL($row, 'measureIdSubstance'), isEmptyProperty($row, 'measureIdSubstance'), propertyExistsSQL($row, 'uniCodeGroupReg'), isEmptyProperty($row, 'uniCodeGroupReg'), propertyExistsSQL($row, 'uniCodeGroupFarm'), isEmptyProperty($row, 'uniCodeGroupFarm'), propertyExistsSQL($row, 'uniCodeStoragePlace'), isEmptyProperty($row, 'uniCodeStoragePlace'), propertyExistsSQL($row, 'linkCipher'), isEmptyProperty($row, 'linkCipher'), propertyExistsSQL($row, 'vitality'), isEmptyProperty($row, 'vitality'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteDrugs($records) { $db = $this->db ?: $this->db = connectDb(); return deleteSql($db, 'MatWealth', 'MatWealthCode', $records); } } $service = new Junior\Server(new map_med_drugs()); $service->process(); 