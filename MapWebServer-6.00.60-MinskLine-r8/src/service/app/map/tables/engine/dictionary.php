<?php
 class DictionaryItem { public $c; public $v; } class Dictionary { public $items; public static $dictionaries; public static $allItems; public static function load () { if (isset(self::$dictionaries)) { return; } self::$dictionaries = new \stdClass; self::$allItems = new \stdClass; $db = Engine::$db; $sqlDict = <<<'EOT'
select
    /*GenTableDictCode "id", */
    Name "name"
from
    GenTableDict
where
    GenTableSetCode = ?
EOT;
$stmtDict = $db->prepare($sqlDict); $stmtDict->execute([Engine::$setId]); while ($row = $stmtDict->fetch()) { self::$dictionaries->{$row['name']} = $dict = new \stdClass; $dict->items = new \stdClass; } $sqlData = <<<'EOT'
select
    /*D.GenTableDictCode "idDict", */
    D.Name "name",
    V.GenTableDictDataCode "id",
    V.Cipher "code",
    V.Value "value",
    D.GenTableDictCode
from
    GenTableDict D
    join GenTableDictData V on
        V.GenTableDictCode = D.GenTableDictCode
where
    D.GenTableSetCode = ?
order by 5
EOT;
$stmtData = $db->prepare($sqlData); $stmtData->execute([Engine::$setId]); while ($row = $stmtData->fetch()) { $item = (object) [ 'c' => $row['code'], 'v' => $row['value'] ]; self::$allItems->{$row['id']} = $item; self::$dictionaries->{$row['name']}->items->{$row['id']} = $item; } } public static function getValue ($dataCode) { if (isset(self::$allItems->{$dataCode})) { return self::$allItems->{$dataCode}->v; } return null; } public static function getIdOfValue ($dictName, $value) { if (isset(self::$dictionaries->{$dictName})) { $items = self::$dictionaries->{$dictName}->items; foreach ($items as $k => $item) { if ($item->v == $value) { return $k; } } } return null; } }