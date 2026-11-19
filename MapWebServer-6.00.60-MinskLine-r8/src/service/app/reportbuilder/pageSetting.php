<?php
 require_once(__DIR__ . '/../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/param.php'); resumeJsonRpc(); class reportbuilder_pageSetting { private $db; public function readPageSettings($params) { $db = $this->db ?: $this->db = connectDb(); $reportListId = isEmptyProperty($params, 'reportListId'); $htmPageId = isEmptyProperty($params, 'htmPageId', 0); $reportMetaSetId = isEmptyProperty($params, 'reportMetaSetId', 0); $piLevel = (int) isEmptyProperty($params, 'piLevel'); $meta = (int)isEmptyProperty($params, 'meta'); $type = (int)isEmptyProperty($params, 'type'); $report = isEmptyProperty($params, 'report'); $def_page_size = 9; $def_page_witdh = 0; $def_page_height = 0; $def_page_margin_left = 72; $def_page_margin_top = 54; $def_page_margin_right = 18; $def_page_margin_bottom = 54; $def_page_orientation = 0; $userId = (int)getSessionValue($type === 0 ? 'user' : 'mapUserCode'); switch (true) { case $htmPageId: $sql = <<<"EOT"
select
       DEF.reportPageInfoCode "id",
       null "reportListId",
       null "userId",
       COALESCE(DEF.size, {$def_page_size}) "defSize",
       COALESCE(DEF.width, {$def_page_witdh}) "defWidth",
       COALESCE(DEF.height, {$def_page_height}) "defHeight",
       COALESCE(DEF.marginLeft, {$def_page_margin_left}) "defMarginLeft",
       COALESCE(DEF.marginTop, {$def_page_margin_top}) "defMarginTop",
       COALESCE(DEF.marginRight, {$def_page_margin_right}) "defMarginRight",
       COALESCE(DEF.marginBottom, {$def_page_margin_bottom}) "defMarginBottom",
       COALESCE(DEF.printerOrientation, {$def_page_orientation}) & 1 "defPrinterOrientation",
       DEF.reportPageInfoCode "reportPageInfoId",
       COALESCE(DEF.size, {$def_page_size}) "curSize",
       COALESCE(DEF.width, {$def_page_witdh}) "curWidth",
       COALESCE(DEF.height, {$def_page_height}) "curHeight",
       COALESCE(DEF.marginLeft, {$def_page_margin_left}) "curMarginLeft",
       COALESCE(DEF.marginTop, {$def_page_margin_top}) "curMarginTop",
       COALESCE(DEF.marginRight, {$def_page_margin_right}) "curMarginRight",
       COALESCE(DEF.marginBottom, {$def_page_margin_bottom}) "curMarginBottom",
       COALESCE(DEF.printerOrientation, {$def_page_orientation}) & 1 "curPrinterOrientation",
       1 "useMargin",
       1 "pageCount",
       HP.htmPageCode "htmPageId"
from htmPage HP
     left join ReportPageInfo DEF on DEF.htmPageCode = HP.htmPageCode
                                 and DEF.UserCode is null
where HP.htmPageCode = {$htmPageId}
limit 1
EOT;
break; case $reportMetaSetId: $sql = <<<"EOT"
select
       CASE CAST($piLevel as int)
            WHEN 2 THEN DEF_PI.reportPageInfoCode
            WHEN 3 THEN USER_PI.reportPageInfoCode
       END "id",
       null "reportListId",
       CASE CAST($piLevel as int) = 3
            WHEN true THEN $userId
       END "userId",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.size
                END, BASE_PI.size, {$def_page_size}) "defSize",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.width
                END, BASE_PI.width, {$def_page_witdh}) "defWidth",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.height
                END, BASE_PI.height, {$def_page_height}) "defHeight",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.marginLeft
                END, BASE_PI.marginLeft, {$def_page_margin_left}) "defMarginLeft",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.marginTop
                END, BASE_PI.marginTop, {$def_page_margin_top}) "defMarginTop",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.marginRight
                END, BASE_PI.marginRight, {$def_page_margin_right}) "defMarginRight",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.marginBottom
                END, BASE_PI.marginBottom, {$def_page_margin_bottom}) "defMarginBottom",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN DEF_PI.printerOrientation
                END, BASE_PI.printerOrientation, {$def_page_orientation}) & 1 "defPrinterOrientation",
       CASE CAST($piLevel as int)
            WHEN 2 THEN DEF_PI.reportPageInfoCode
            WHEN 3 THEN USER_PI.reportPageInfoCode
       END "reportPageInfoId",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.size
                     WHEN false THEN DEF_PI.size
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.size
                               END, BASE_PI.size, {$def_page_size}))) "curSize",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.width
                     WHEN false THEN DEF_PI.width
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.width
                               END, BASE_PI.width, {$def_page_witdh}))) "curWidth",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.height
                     WHEN false THEN DEF_PI.height
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.height
                               END, BASE_PI.height, {$def_page_height}))) "curHeight",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.marginLeft
                     WHEN false THEN DEF_PI.marginLeft
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.marginLeft
                               END, BASE_PI.marginLeft, {$def_page_margin_left}))) "curMarginLeft",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.marginTop
                     WHEN false THEN DEF_PI.marginTop
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.marginTop
                               END, BASE_PI.marginTop, {$def_page_margin_top}))) "curMarginTop",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.marginRight
                     WHEN false THEN DEF_PI.marginRight
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.marginRight
                               END, BASE_PI.marginRight, {$def_page_margin_right}))) "curMarginRight",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.marginBottom
                     WHEN false THEN DEF_PI.marginBottom
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.marginBottom
                               END, BASE_PI.marginBottom, {$def_page_margin_bottom}))) "curMarginBottom",
       COALESCE(CASE $piLevel = 3
                     WHEN true THEN USER_PI.printerOrientation
                     WHEN false THEN DEF_PI.printerOrientation
                END, (COALESCE(CASE $piLevel = 3
                                    WHEN true THEN DEF_PI.printerOrientation
                               END, BASE_PI.printerOrientation, {$def_page_orientation}) & 1)) "curPrinterOrientation",
       1 "useMargin",
       1 "pageCount",
       CAST($piLevel as int) "piLevel"
from ReportMetaSet RMS
     join ReportList RL on RL.reportListCode = RMS.reportListCode
     left join ReportPageInfo BASE_PI on BASE_PI.reportListCode = RMS.reportListCode
                                     and BASE_PI.userCode is null
     left join ReportPageInfo DEF_PI on DEF_PI.reportMetaSetCode = RMS.reportMetaSetCode
                                    and DEF_PI.userCode is null
                                    and CAST($piLevel as int) in (2, 3)
                                    and DEF_PI.printerOrientation = BASE_PI.printerOrientation
     left join ReportPageInfo USER_PI on USER_PI.reportMetaSetCode = RMS.reportMetaSetCode
                                     and USER_PI.userCode = $userId
                                     and CAST($piLevel as int) = 3
                                     and USER_PI.printerOrientation = BASE_PI.printerOrientation
where RMS.reportMetaSetCode = $reportMetaSetId
order by "defOrientation"
limit 1
EOT;
break; default: if ($meta === 2) { suspendJsonRpc(); require_once(__DIR__ . '/paper.php'); $papers = new reportbuilder_paper(); resumeJsonRpc(); if ($type === 0) { $fileName = getServerRootPath() . "reports/{$report}.mrt"; $json = file_get_contents($fileName); if ($json === false) { throw new \Exception("Отчета \"$fileName\" не существует!"); } $ssReport = safe_json_decode($json); $pageSettings = reportbuilder_param::loadStimulsoftUserParameters($db, $userId, $report)->pageSettings; } else { $ssReport = safe_json_decode(selectSqlValue($db, 'select Sample from ReportList where ReportListCode = ?', [$reportListId])); } if (empty($ssReport)) { $ssReport = new stdClass(); } $toMm = function ($value) use ($ssReport) { switch ($ssReport->ReportUnit ?? '') { case 'Millimeters': return $value; case 'Inches': return $value * 25.4; case 'HundredthsOfInch': return $value * 25.4 * 100.0; default: return $value * 10.0; } }; if (empty($ssReport->Pages)) { $ssReport->Pages = [new stdClass]; } $defSql = ''; $orients = []; $pages = []; foreach ($ssReport->Pages as $ssPage) { $orient = isEmptyProperty($ssPage, 'Orientation') === 'Landscape' ? 1 : 0; if (isset($orients[$orient])) { $orients[$orient]++; continue; } $orients[$orient] = 1; $defSize = 9; if (isset($ssPage->PaperSize)) { $paper = arrayFindObject($papers::PAPERS, 'shortName', $ssPage->PaperSize); if ($paper) { $defSize = $paper->id; } } $margins = isEmptyProperty($ssPage, 'Margins', new stdClass); $defWidth = round((empty($ssPage->PageWidth) ? 210.3 : $toMm($ssPage->PageWidth)) * 10.0); $defHeight = round((empty($ssPage->PageHeight) ? 297.3 : $toMm($ssPage->PageHeight)) * 10.0); $defMarginLeft = round((empty($margins->Left) ? 10 : $toMm($margins->Left)) * 18.0/5.0); $defMarginTop = round((empty($margins->Top) ? 10 : $toMm($margins->Top)) * 18.0/5.0); $defMarginRight = round((empty($margins->Right) ? 10 : $toMm($margins->Right)) * 18.0/5.0); $defMarginBottom= round((empty($margins->Bottom) ? 10 : $toMm($margins->Bottom)) * 18.0/5.0); if ($type === 0) { $userSetting = isUndefinedProperty($pageSettings, (string)$orient); $pages[] = (object)[ 'id' => $orient - 2, 'userId' => $userId, 'pageCount' => $orients[$orient], 'defPrinterOrientation' => $orient, 'curPrinterOrientation' => isEmptyProperty($userSetting, 'orientation', $orient), 'defSize' => $defSize, 'curSize' => isEmptyProperty($userSetting, 'size', $defSize), 'defWidth' => $defWidth, 'curWidth' => isEmptyProperty($userSetting, 'width', $defWidth), 'defHeight' => $defHeight, 'curHeight' => isEmptyProperty($userSetting, 'height', $defHeight), 'defMarginLeft' => $defMarginLeft, 'curMarginLeft' => isEmptyProperty($userSetting, 'marginLeft', $defMarginLeft), 'defMarginTop' => $defMarginTop, 'curMarginTop' => isEmptyProperty($userSetting, 'marginTop', $defMarginTop), 'defMarginRight' => $defMarginRight, 'curMarginRight' => isEmptyProperty($userSetting, 'marginRight', $defMarginRight), 'defMarginBottom' => $defMarginBottom, 'curMarginBottom' => isEmptyProperty($userSetting, 'marginBottom', $defMarginBottom), 'useMargin' => isEmptyProperty($userSetting, 'useMargin', 0), 'type' => $type, 'report' => $report, 'reportPageInfoId' => $userSetting ? $orient - 2 : null ]; continue; } $defSql .= $defSql ? ' union all ' : ''; $defSql .= <<<"EOT"
select
    $reportListId ReportListCode,
    null UserCode,
    $orient - 2 ReportPageInfoCode,
    $orient PrinterOrientation,
    $defSize Size,
    $defWidth Width,
    $defHeight Height,
    $defMarginLeft MarginLeft,
    $defMarginTop MarginTop,
    $defMarginRight MarginRight,
    $defMarginBottom MarginBottom,
    $orients[$orient] ReportCount
EOT;
} if ($type === 0) { return $pages; } $defSql = "($defSql)"; } else { $defSql = 'ReportPageInfo'; } $sql = <<<"EOT"
select
       COALESCE(CUR.reportPageInfoCode, (COALESCE((CASE
                                                        WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.reportPageInfoCode
                                                        ELSE CUR_M.reportPageInfoCode
                                                   END)))) "id",
       RL.reportListCode "reportListId",
       CAST($userId AS int) "userId",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.Size
                      ELSE CUR_M.size
                 END), {$def_page_size}) "defSize",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.Width
                      ELSE CUR_M.width
                 END), {$def_page_witdh}) "defWidth",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.Height
                      ELSE CUR_M.height
                 END), {$def_page_height}) "defHeight",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.MarginLeft
                      ELSE CUR_M.marginLeft
                 END), {$def_page_margin_left}) "defMarginLeft",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.MarginTop
                      ELSE CUR_M.marginTop
                 END), {$def_page_margin_top}) "defMarginTop",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.MarginRight
                      ELSE CUR_M.marginRight
                 END), {$def_page_margin_right}) "defMarginRight",
       COALESCE((CASE
                      WHEN CUR_M.reportPageInfoCode is null THEN DEF.MarginBottom
                      ELSE CUR_M.marginBottom
                 END), {$def_page_margin_bottom}) "defMarginBottom",
       FN_cmMin(1, COALESCE((CASE
                                  WHEN CUR_M.reportPageInfoCode is null THEN DEF.PrinterOrientation
                                  ELSE CUR_M.printerOrientation
                             END), {$def_page_orientation})) "defPrinterOrientation",
       CUR.reportPageInfoCode "reportPageInfoId",
       COALESCE(CUR.size, (COALESCE((CASE
                                          WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.size
                                          ELSE CUR_M.size
                                     END), {$def_page_size}))) "curSize",
       COALESCE(CUR.width, (COALESCE((CASE
                                           WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.width
                                           ELSE CUR_M.width
                                      END), {$def_page_witdh}))) "curWidth",
       COALESCE(CUR.height, (COALESCE((CASE
                                            WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.height
                                            ELSE CUR_M.height
                                       END), {$def_page_height}))) "curHeight",
       COALESCE(CUR.marginLeft, (COALESCE((CASE
                                                WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.marginLeft
                                                ELSE CUR_M.marginLeft
                                           END), {$def_page_margin_left}))) "curMarginLeft",
       COALESCE(CUR.marginTop, (COALESCE((CASE
                                               WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.marginTop
                                               ELSE CUR_M.marginTop
                                          END), {$def_page_margin_top}))) "curMarginTop",
       COALESCE(CUR.marginRight, (COALESCE((CASE
                                                 WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.marginRight
                                                 ELSE CUR_M.marginRight
                                            END), {$def_page_margin_right}))) "curMarginRight",
       COALESCE(CUR.marginBottom, (COALESCE((CASE
                                                  WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.marginBottom
                                                  ELSE CUR_M.marginBottom
                                             END), {$def_page_margin_bottom}))) "curMarginBottom",
       COALESCE(CUR.printerOrientation, (FN_cmMin(1, COALESCE((CASE
                                                                    WHEN CUR_M.reportPageInfoCode IS NULL THEN DEF.printerOrientation
                                                                    ELSE CUR_M.printerOrientation
                                                               END), {$def_page_orientation})))) "curPrinterOrientation",
       COALESCE(CUR.useMargin, 0) "useMargin",
       DEF.reportCount "pageCount",
       $type "type",
       '$report' "report"
from ReportList RL
     /* настройка листов бумаги, сделанная разработчиком в шаблоне мета-отчета */
     left join /*ReportPageInfo*/$defSql DEF on DEF.reportListCode = RL.reportListCode
                                 and DEF.userCode is null
                                 and DEF.printerOrientation in (0, 1)
     /* настройка листов бумаги, сделанная пользователем по конкретной настройке мета-отчета */
     left join ReportPageInfo CUR_M on CUR_M.reportMetaSetCode = {$reportMetaSetId}
                                   and CUR_M.userCode is null
                                   and CUR_M.printerOrientation = DEF.printerOrientation
     /* настройка листов бумаги, сделанная пользователем выпускающим мета-отчет */
     left join ReportPageInfo CUR on CUR.reportListCode = RL.reportListCode
                                 and CUR.userCode = $userId
                                 and CUR.printerOrientation / 2 = DEF.printerOrientation
where RL.reportListCode = {$reportListId}
order by "defPrinterOrientation"
EOT;
} return selectSql($db, $sql); } public function getHTMLPaperParams($params) { $db = $this->db ?: $this->db = connectDb(); $userId = isEmptyProperty($params, 'userId'); $htmPageId = isEmptyProperty($params, 'htmPageId'); $reportMetaSetId = isEmptyProperty($params, 'reportMetaSetId'); $def_page_size = 9; $def_page_witdh = 0; $def_page_height = 0; $def_page_margin_left = 72; $def_page_margin_top = 54; $def_page_margin_right = 18; $def_page_margin_bottom = 54; $def_page_orientation = 0; if ($htmPageId) { $sql = <<<"EOT"
SELECT COALESCE( Def.Size, $def_page_size) as DefSize,
    COALESCE(Def.Width, $def_page_witdh) as DefWidth,
    COALESCE(Def.Height, $def_page_height) as DefHeight,
    COALESCE(Def.MarginLeft, $def_page_margin_left) as DefMarginLeft,
    COALESCE(Def.MarginTop, $def_page_margin_top) as DefMarginTop,
    COALESCE(Def.MarginRight, $def_page_margin_right) as DefMarginRight,
    COALESCE(Def.MarginBottom, $def_page_margin_bottom) as DefMarginBottom,
    COALESCE(Def.PrinterOrientation, $def_page_orientation) as DefPrinterOrientation,
    Def.ReportPageInfoCode as ReportPageInfoCode
FROM
    htmPage AS P LEFT OUTER JOIN ReportPageInfo AS Def ON Def.htmPageCode = P.htmPageCode
    AND Def.UserCode IS NULL
WHERE P.htmPageCode = $htmPageId
ORDER BY 8
LIMIT 1
EOT;
} else { $sql = <<<"EOT"
SELECT
    COALESCE(PI.Size, DefPI.Size, BasePI.Size, $def_page_size) as DefSize,
    COALESCE(PI.Width, DefPI.Width, BasePI.Width, $def_page_witdh) as DefWidth,
    COALESCE(PI.Height, DefPI.Height, BasePI.Height, $def_page_height) as DefHeight,
    COALESCE(PI.MarginLeft, DefPI.MarginLeft, BasePI.MarginLeft, $def_page_margin_left) as DefMarginLeft,
    COALESCE(PI.MarginTop, DefPI.MarginTop, BasePI.MarginTop, $def_page_margin_top) as DefMarginTop,
    COALESCE(PI.MarginRight, DefPI.MarginRight, BasePI.MarginRight, $def_page_margin_right) as DefMarginRight,
    COALESCE(PI.MarginBottom, DefPI.MarginBottom, BasePI.MarginBottom, $def_page_margin_bottom) as DefMarginBottom,
    COALESCE(PI.PrinterOrientation, DefPI.PrinterOrientation, BasePI.PrinterOrientation, $def_page_orientation) as DefPrinterOrientation
FROM
    ReportMetaSet AS S
    JOIN ReportList AS RL ON RL.ReportListCode = S.ReportListCode
    LEFT OUTER JOIN ReportPageInfo AS BasePI ON BasePI.ReportListCode = S.ReportListCode AND BasePI.UserCode IS NULL
    LEFT OUTER JOIN ReportPageInfo AS DefPI ON DefPI.ReportMetaSetCode = S.ReportMetaSetCode AND DefPI.UserCode IS NULL
    LEFT OUTER JOIN ReportPageInfo AS PI ON PI.ReportMetaSetCode = S.ReportMetaSetCode AND PI.UserCode = $userId
WHERE S.ReportMetaSetCode = $reportMetaSetId
ORDER BY 8
LIMIT 1
EOT;
} return selectSql($db, $sql); } private function insertOrUpdatePageSettings($records, $isInsert) { $db = $this->db ?: $this->db = connectDb(); $ret = []; if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $userId = isEmptyProperty($row, 'userId'); $htmPageId = isEmptyProperty($row, 'htmPageId'); $reportMetaSetId = isEmptyProperty($row, 'reportMetaSetId'); $ret[] = $rec = new stdClass; switch (true) { case $htmPageId: $sql = <<<"EOT"
INSERT INTO ReportPageInfo (ReportPageInfoCode, htmPageCode, Size, Width, Height, MarginLeft, MarginTop, MarginRight, MarginBottom, PrinterOrientation, UseMargin)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::integer & 1, ?)
ON CONFLICT (ReportPageInfoCode) DO UPDATE SET
    Size = case when ? = 1 then EXCLUDED.Size else ReportPageInfo.Size end,
    Width = case when ? = 1 then EXCLUDED.Width else ReportPageInfo.Width end,
    Height = case when ? = 1 then EXCLUDED.Height else ReportPageInfo.Height end,
    MarginLeft = case when ? = 1 then EXCLUDED.MarginLeft else ReportPageInfo.MarginLeft end,
    MarginTop = case when ? = 1 then EXCLUDED.MarginTop else ReportPageInfo.MarginTop end,
    MarginRight = case when ? = 1 then EXCLUDED.MarginRight else ReportPageInfo.MarginRight end,
    MarginBottom = case when ? = 1 then EXCLUDED.MarginBottom else ReportPageInfo.MarginBottom end,
    PrinterOrientation = case when ? = 1 then EXCLUDED.PrinterOrientation else ReportPageInfo.PrinterOrientation end,
    UseMargin = case when ? = 1 then EXCLUDED.UseMargin else ReportPageInfo.UseMargin end
RETURNING ReportPageInfoCode AS id
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), $htmPageId, isUndefinedProperty($row, 'curSize'), isUndefinedProperty($row, 'curWidth'), isUndefinedProperty($row, 'curHeight'), isUndefinedProperty($row, 'curMarginLeft'), isUndefinedProperty($row, 'curMarginTop'), isUndefinedProperty($row, 'curMarginRight'), isUndefinedProperty($row, 'curMarginBottom'), isUndefinedProperty($row, 'curPrinterOrientation'), isUndefinedProperty($row, 'useMargin'), propertyExistsSQL($row, 'curSize'), propertyExistsSQL($row, 'curWidth'), propertyExistsSQL($row, 'curHeight'), propertyExistsSQL($row, 'curMarginLeft'), propertyExistsSQL($row, 'curMarginTop'), propertyExistsSQL($row, 'curMarginRight'), propertyExistsSQL($row, 'curMarginBottom'), propertyExistsSQL($row, 'curPrinterOrientation'), propertyExistsSQL($row, 'useMargin') ]); $rec->id = fetchValue($stmt, 'id'); break; case $reportMetaSetId: $sql = <<<"EOT"
INSERT INTO ReportPageInfo (ReportPageInfoCode, ReportMetaSetCode, UserCode, Size, Width, Height, MarginLeft, MarginTop, MarginRight, MarginBottom, PrinterOrientation, UseMargin)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::integer & 1, ?)
ON CONFLICT (ReportPageInfoCode) DO UPDATE SET
    Size = case when ? = 1 then EXCLUDED.Size else ReportPageInfo.Size end,
    Width = case when ? = 1 then EXCLUDED.Width else ReportPageInfo.Width end,
    Height = case when ? = 1 then EXCLUDED.Height else ReportPageInfo.Height end,
    MarginLeft = case when ? = 1 then EXCLUDED.MarginLeft else ReportPageInfo.MarginLeft end,
    MarginTop = case when ? = 1 then EXCLUDED.MarginTop else ReportPageInfo.MarginTop end,
    MarginRight = case when ? = 1 then EXCLUDED.MarginRight else ReportPageInfo.MarginRight end,
    MarginBottom = case when ? = 1 then EXCLUDED.MarginBottom else ReportPageInfo.MarginBottom end,
    PrinterOrientation = case when ? = 1 then EXCLUDED.PrinterOrientation else ReportPageInfo.PrinterOrientation end,
    UseMargin = case when ? = 1 then EXCLUDED.UseMargin else ReportPageInfo.UseMargin end
RETURNING ReportPageInfoCode AS id
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), $reportMetaSetId, $userId, isUndefinedProperty($row, 'curSize'), isUndefinedProperty($row, 'curWidth'), isUndefinedProperty($row, 'curHeight'), isUndefinedProperty($row, 'curMarginLeft'), isUndefinedProperty($row, 'curMarginTop'), isUndefinedProperty($row, 'curMarginRight'), isUndefinedProperty($row, 'curMarginBottom'), isUndefinedProperty($row, 'curPrinterOrientation'), isUndefinedProperty($row, 'useMargin'), propertyExistsSQL($row, 'curSize'), propertyExistsSQL($row, 'curWidth'), propertyExistsSQL($row, 'curHeight'), propertyExistsSQL($row, 'curMarginLeft'), propertyExistsSQL($row, 'curMarginTop'), propertyExistsSQL($row, 'curMarginRight'), propertyExistsSQL($row, 'curMarginBottom'), propertyExistsSQL($row, 'curPrinterOrientation'), propertyExistsSQL($row, 'useMargin') ]); $rec->id = fetchValue($stmt, 'id'); break; default: $type = (int)isEmptyProperty($row, 'type'); if ($type === 0) { $report = isEmptyProperty($row, 'report'); $parameters = reportbuilder_param::loadStimulsoftUserParameters($db, $userId, $report); $pageSettings = $parameters->pageSettings; $orient = (string)isEmptyProperty($row, 'defPrinterOrientation', 0); $ps = $pageSettings->{$orient} = $pageSettings->{$orient} ?? new stdClass(); $ps->orientation = isUndefinedProperty($row, 'curPrinterOrientation', $ps->orientation ?? null); $ps->size = isUndefinedProperty($row, 'curSize', $ps->size ?? null); $ps->width = isUndefinedProperty($row, 'curWidth', $ps->width ?? null); $ps->height = isUndefinedProperty($row, 'curHeight', $ps->height ?? null); $ps->marginLeft = isUndefinedProperty($row, 'curMarginLeft', $ps->marginLeft ?? null); $ps->marginTop = isUndefinedProperty($row, 'curMarginTop', $ps->marginTop ?? null); $ps->marginRight = isUndefinedProperty($row, 'curMarginRight', $ps->marginRight ?? null); $ps->marginBottom = isUndefinedProperty($row, 'curMarginBottom', $ps->marginBottom ?? null); $ps->useMargin = isUndefinedProperty($row, 'useMargin', $ps->useMargin ?? null); reportbuilder_param::saveStimulsoftUserParameters($db, $userId, $report, $parameters); $rec->id = $orient - 2; } else { $sql = <<<"EOT"
INSERT INTO ReportPageInfo (ReportPageInfoCode, ReportListCode,  UserCode, Size, Width, Height, MarginLeft, MarginTop, MarginRight, MarginBottom, PrinterOrientation, UseMargin)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON CONFLICT (ReportPageInfoCode) DO UPDATE SET
    ReportListCode = case when ? = 1 then EXCLUDED.ReportListCode else ReportPageInfo.ReportListCode end,
    UserCode = case when ? = 1 then EXCLUDED.UserCode else ReportPageInfo.UserCode end,
    Size = case when ? = 1 then EXCLUDED.Size else ReportPageInfo.Size end,
    Width = case when ? = 1 then EXCLUDED.Width else ReportPageInfo.Width end,
    Height = case when ? = 1 then EXCLUDED.Height else ReportPageInfo.Height end,
    MarginLeft = case when ? = 1 then EXCLUDED.MarginLeft else ReportPageInfo.MarginLeft end,
    MarginTop = case when ? = 1 then EXCLUDED.MarginTop else ReportPageInfo.MarginTop end,
    MarginRight = case when ? = 1 then EXCLUDED.MarginRight else ReportPageInfo.MarginRight end,
    MarginBottom = case when ? = 1 then EXCLUDED.MarginBottom else ReportPageInfo.MarginBottom end,
    PrinterOrientation = case when ? = 1 then EXCLUDED.PrinterOrientation else ReportPageInfo.PrinterOrientation end,
    UseMargin = case when ? = 1 then EXCLUDED.UseMargin else ReportPageInfo.UseMargin end
RETURNING ReportPageInfoCode AS id
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $isInsert ? null : isUndefinedProperty($row, 'id'), isUndefinedProperty($row, 'reportListId'), $userId, isUndefinedProperty($row, 'curSize'), isUndefinedProperty($row, 'curWidth'), isUndefinedProperty($row, 'curHeight'), isUndefinedProperty($row, 'curMarginLeft'), isUndefinedProperty($row, 'curMarginTop'), isUndefinedProperty($row, 'curMarginRight'), isUndefinedProperty($row, 'curMarginBottom'), isUndefinedProperty($row, 'curPrinterOrientation'), isUndefinedProperty($row, 'useMargin'), propertyExistsSQL($row, 'reportListId'), propertyExistsSQL($row, 'userId'), propertyExistsSQL($row, 'curSize'), propertyExistsSQL($row, 'curWidth'), propertyExistsSQL($row, 'curHeight'), propertyExistsSQL($row, 'curMarginLeft'), propertyExistsSQL($row, 'curMarginTop'), propertyExistsSQL($row, 'curMarginRight'), propertyExistsSQL($row, 'curMarginBottom'), propertyExistsSQL($row, 'curPrinterOrientation'), propertyExistsSQL($row, 'useMargin') ]); $rec->id = fetchValue($stmt, 'id'); } break; } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function updatePageSettings($records) { return $this->insertOrUpdatePageSettings($records, false); } public function createPageSettings($records) { return $this->insertOrUpdatePageSettings($records, true); } } handleJsonRpc('reportbuilder_pageSetting'); 