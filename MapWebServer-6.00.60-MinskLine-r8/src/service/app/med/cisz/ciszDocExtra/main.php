<?php
class med_cisz_ciszDocExtra_main { public static function getData(PDO_ANY $db, ?object $params): ?med_cisz_ciszDocExtra_data { $list = self::getList($db, $params, 1); return $list[0] ?? null; } public static function getList(PDO_ANY $db, ?object $params, int $limit = 0): array { [$whereSql, $sqlParams] = self::buildWhere($params); $limitSql = $limit > 0 ? ' LIMIT ' . $limit : ''; $sql = <<<"SQL"
SELECT
    ciszDocExtraCode      "ciszDocExtraCode",
    medStatCardDocCode    "medStatCardDocCode",
    medVisitDocumentCode  "medVisitDocumentCode",
    medLogCode            "medLogCode",
    documentType          "documentType",
    json                  "json"
FROM CiszDocExtra
$whereSql
$limitSql
SQL;
$rows = selectSql($db, $sql, $sqlParams, PDO::FETCH_OBJ); return empty($rows) ? [] : med_cisz_ciszDocExtra_data::fromRows($rows); } private static function buildWhere(?object $params): array { $where = []; $bind = []; if (!empty($params->ciszDocExtraCode)) { $where[] = 'ciszDocExtraCode = :ciszDocExtraCode'; $bind[':ciszDocExtraCode'] = (int) $params->ciszDocExtraCode; } if (!empty($params->medStatCardDocCode)) { $where[] = 'medStatCardDocCode = :medStatCardDocCode'; $bind[':medStatCardDocCode'] = (int) $params->medStatCardDocCode; } if (!empty($params->medVisitDocumentCode)) { $where[] = 'medVisitDocumentCode = :medVisitDocumentCode'; $bind[':medVisitDocumentCode'] = (int) $params->medVisitDocumentCode; } if (!empty($params->medLogCode)) { $where[] = 'medLogCode = :medLogCode'; $bind[':medLogCode'] = (int) $params->medLogCode; } if (isset($params->documentType)) { $where[] = 'documentType = :documentType'; $bind[':documentType'] = (int) $params->documentType; } return ['WHERE ' . implode(' AND ', $where), $bind]; } }