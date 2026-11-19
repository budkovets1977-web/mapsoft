<?php
 require_once(__DIR__.'/../../init.php'); class map_common_currency { private $db; public function readCurrency($params = null) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    CurrencyCode "id",
    Symbol "symbol",
    ShortName "shortName",
    Name1 "name1",
    FullName1 "fullName1",
    Name3 "name3",
    FullName3 "fullName3",
    Name5 "name5",
    FullName5 "fullName5",
    SymbolCent "symbolCent",
    ShortCent "shortCent",
    Cent1 "cent1",
    FullCent1 "fullCent1",
    Cent3 "cent3",
    FullCent3 "fullCent3",
    Cent5 "cent5",
    FullCent5 "fullCent5",
    CurrencyKind "currencyKind",
    Sex "sex",
    SexCent "sexCent",
    Code "code"
FROM Currency
WHERE CurrencyKind <> 2
ORDER BY 18 DESC, 5 ASC
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCurrency($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE Currency SET
    Symbol = case when ? then ? else Symbol end,
    ShortName = case when ? then ? else ShortName end,
    Name1 = case when ? then ? else Name1 end,
    FullName1 = case when ? then ? else FullName1 end,
    Name3 = case when ? then ? else Name3 end,
    FullName3 = case when ? then ? else FullName3 end,
    Name5 = case when ? then ? else Name5 end,
    FullName5 = case when ? then ? else FullName5 end,
    SymbolCent = case when ? then ? else SymbolCent end,
    ShortCent = case when ? then ? else ShortCent end,
    Cent1 = case when ? then ? else Cent1 end,
    FullCent1 = case when ? then ? else FullCent1 end,
    Cent3 = case when ? then ? else Cent3 end,
    FullCent3 = case when ? then ? else FullCent3 end,
    Cent5 = case when ? then ? else Cent5 end,
    FullCent5 = case when ? then ? else FullCent5 end,
    CurrencyKind = case when ? then ? else CurrencyKind end,
    Sex = case when ? then ? else Sex end,
    SexCent = case when ? then ? else SexCent end,
    Code = case when ? then ? else Code end
WHERE CurrencyCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'symbol'), isUndefinedProperty($row, 'symbol'), propertyExistsSQL($row, 'shortName'), isUndefinedProperty($row, 'shortName'), propertyExistsSQL($row, 'name1'), isUndefinedProperty($row, 'name1'), propertyExistsSQL($row, 'fullName1'), isUndefinedProperty($row, 'fullName1'), propertyExistsSQL($row, 'name3'), isUndefinedProperty($row, 'name3'), propertyExistsSQL($row, 'fullName3'), isUndefinedProperty($row, 'fullName3'), propertyExistsSQL($row, 'name5'), isUndefinedProperty($row, 'name5'), propertyExistsSQL($row, 'fullName5'), isUndefinedProperty($row, 'fullName5'), propertyExistsSQL($row, 'symbolCent'), isUndefinedProperty($row, 'symbolCent'), propertyExistsSQL($row, 'shortCent'), isUndefinedProperty($row, 'shortCent'), propertyExistsSQL($row, 'cent1'), isUndefinedProperty($row, 'cent1'), propertyExistsSQL($row, 'fullCent1'), isUndefinedProperty($row, 'fullCent1'), propertyExistsSQL($row, 'cent3'), isUndefinedProperty($row, 'cent3'), propertyExistsSQL($row, 'fullCent3'), isUndefinedProperty($row, 'fullCent3'), propertyExistsSQL($row, 'cent5'), isUndefinedProperty($row, 'cent5'), propertyExistsSQL($row, 'fullCent5'), isUndefinedProperty($row, 'fullCent5'), propertyExistsSQL($row, 'currencyKind'), isUndefinedProperty($row, 'currencyKind'), propertyExistsSQL($row, 'sex'), isUndefinedProperty($row, 'sex'), propertyExistsSQL($row, 'sexCent'), isUndefinedProperty($row, 'sexCent'), propertyExistsSQL($row, 'code'), isUndefinedProperty($row, 'code'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCurrency($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
INSERT INTO Currency (Symbol, ShortName, Name1, FullName1, Name3, FullName3, Name5, FullName5, SymbolCent, ShortCent,
Cent1, FullCent1, Cent3, FullCent3, Cent5, FullCent5, CurrencyKind, Sex, SexCent, Code)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'symbol'), isUndefinedProperty($row, 'shortName'), isUndefinedProperty($row, 'name1'), isUndefinedProperty($row, 'fullName1'), isUndefinedProperty($row, 'name3'), isUndefinedProperty($row, 'fullName3'), isUndefinedProperty($row, 'name5'), isUndefinedProperty($row, 'fullName5'), isUndefinedProperty($row, 'symbolCent'), isUndefinedProperty($row, 'shortCent'), isUndefinedProperty($row, 'cent1'), isUndefinedProperty($row, 'fullCent1'), isUndefinedProperty($row, 'cent3'), isUndefinedProperty($row, 'fullCent3'), isUndefinedProperty($row, 'cent5'), isUndefinedProperty($row, 'fullCent5'), isUndefinedProperty($row, 'currencyKind'), isUndefinedProperty($row, 'sex'), isUndefinedProperty($row, 'sexCent'), isUndefinedProperty($row, 'code') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_Currency'); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function deleteCurrency($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM Currency WHERE CurrencyCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('map_common_currency'); 