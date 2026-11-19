<?php
class med_cisz_ciszLink_main { public static function getData(PDO_ANY $db, ?object $params): ?med_cisz_ciszLink_data { $list = self::getList($db, $params, 1); return $list[0] ?? null; } public static function getList(PDO_ANY $db, ?object $params, int $limit = 0): array { [$whereSql, $sqlParams] = self::buildWhere($params); $limitSql = $limit > 0 ? ' LIMIT ' . $limit : ''; $sql = <<<"SQL"
SELECT
    ciszLinkCode       "ciszLinkCode",
    primaryKey         "primaryKey",
    type               "type",
    tableName          "tableName",
    uuid               "uuid",
    status             "status",
    bundleUuid         "bundleUuid",
    createTime         "createTime"
FROM CiszLink
$whereSql
$limitSql
SQL;
$rows = selectSql($db, $sql, $sqlParams, PDO::FETCH_OBJ); return empty($rows) ? [] : med_cisz_ciszLink_data::fromRows($rows); } private static function buildWhere(?object $params): array { $where = []; $bind = []; if (!empty($params->primaryKey)) { $where[] = 'primaryKey = :primaryKey'; $bind[':primaryKey'] = $params->primaryKey; } if (!empty($params->entity)) { $where[] = 'tableName = :tableName'; $bind[':tableName'] = $params->entity; } return ['WHERE ' . implode(' AND ', $where), $bind]; } public static function needsSending( med_cisz_ciszLink_data $link ): bool { return $link->needsSending(); } }