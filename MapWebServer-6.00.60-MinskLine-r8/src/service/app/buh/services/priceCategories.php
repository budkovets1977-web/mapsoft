<?php
 require_once(__DIR__ . '/../../init.php'); class buh_services_priceCategories { private $db; public function readPriceCategories() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select
    P.ServiceCategoryPriceCode "id",
    P.Name "name",
    coalesce(P.ForCitizen,0) "forCitizen",
    coalesce(P.ForForeiger,0) "forForeiger",
    P.CurrencyCode "currencyCode",
    (select FullName1 from Currency where CurrencyCode = P.CurrencyCode) "symbol"
from ServiceCategoryPrice P
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updatePriceCategories($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
update ServiceCategoryPrice set
    Name = case when ? then ? else Name end,
    ForCitizen = case when ? then ? else ForCitizen end,
    ForForeiger = case when ? then ? else ForForeiger end,
    CurrencyCode = case when ? then ? else CurrencyCode end
where ServiceCategoryPriceCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'forCitizen'), isUndefinedProperty($row, 'forCitizen'), propertyExistsSQL($row, 'forForeiger'), isUndefinedProperty($row, 'forForeiger'), propertyExistsSQL($row, 'currencyCode'), isUndefinedProperty($row, 'currencyCode'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createPriceCategories($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
insert into ServiceCategoryPrice (Name, ForCitizen, ForForeiger, CurrencyCode)
values (?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'forCitizen'), isUndefinedProperty($row, 'forForeiger'), isUndefinedProperty($row, 'currencyCode') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_ServiceCategoryPrice'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deletePriceCategories($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
delete from ServiceCategoryPrice where ServiceCategoryPriceCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('buh_services_priceCategories'); 