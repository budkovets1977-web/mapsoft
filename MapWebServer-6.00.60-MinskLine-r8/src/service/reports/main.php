<?php
 require_once(__DIR__ . '/../app/init.php'); require_once 'stimulsoft/helper.php'; header('Content-Type: text/html; charset=UTF-8'); class reports_main { protected $params; public function __construct () { $this->params = (object)$_REQUEST; } public function openViewer () { $setup = $this->createSetup(); ?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<?php
 echo '<title>' . $setup->title . '</title>'; ?>
        <script src="scripts/stimulsoft.reports.js" type="text/javascript"></script>
        <script src="scripts/stimulsoft.viewer.js" type="text/javascript"></script>
        <style>
            html, body { font-family: sans-serif; }
        </style>
<?php
 StiHelper::init('handler.php', 900); ?>
        <script type="text/javascript">
        (function() {
            var SS = Stimulsoft,
                Locale = SS.Base.Localization.StiLocalization,
                Viewer = SS.Viewer,
                Report = SS.Report,
                Helper = SS.Helper,
                PageHelper = Report.Components.StiPageHelper,
                setup = <?= json_encode($setup, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
                options;

            SS.Base.StiLicense.loadFromFile("stimulsoft/license.php");

            Locale.addLocalizationFile("localization/ru.xml", true, "ru");
            Locale.setLocalizationFile("localization/ru.xml");

            options = new Viewer.StiViewerOptions();
            options.appearance.theme = Stimulsoft.Viewer.StiViewerTheme[setup.style]
            options.appearance.fullScreenMode = true;
            options.toolbar.showSendEmailButton = true;
            options.toolbar.showParametersButton = (setup.type == 0 && setup.original);

            window.viewer = new Viewer.StiViewer(options, "StiViewer", false);

            /*
            // Process report variables before rendering.
            viewer.onPrepareVariables = function (args, callback) {
                // Call the server-side handler
                Helper.process(args, callback);
            }
            */

            // Process SQL data sources. It can be used if it is necessary to correct the parameters of the data request.
        	viewer.onBeginProcessData = function (args, callback) {
                args.connectionString = setup.connect || args.connectionString;
                args.mapParams = setup.map && setup.map.params;
                args.session = <?= json_encode($_SESSION, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                Helper.process(args, callback);
            };

            /*
            // Manage export settings and, if necessary, transfer them to the server and manage there
            viewer.onBeginExportReport = function (args, callback) {
                // Call the server-side handler
                Helper.process(args, callback);
                // Manage export settings
                // args.fileName = "MyReportName";
            }
            */

            /*
            // Process exported report file on the server side
            viewer.onEndExportReport = function (args) {
                // Prevent built-in handler (save the exported report as a file)
                args.preventDefault = true;
                // Call the server-side handler
                Helper.process(args);
            }
            */

            /*
            // Send exported report to Email
            viewer.onEmailReport = function (args) {
                // Call the server-side handler
                Helper.process(args);
            }
            */

    		// Load and show report
            window.report = new Report.StiReport();
            // if (setup.type == 0) {
            //     report.loadFile(setup.reportUrl);
            // } else {
                if (setup.map && setup.map.data) {
                    // report.load(setup.map.data);
                    report.load(setup.map.data);
                }
                report.reportName = setup.report;
                report.reportAlias = setup.title;
                report.reportFile = setup.report + '.mrt';
            // }
            if (report.pages && report.pages.count && setup.map && setup.map.pagesInfo) {
                var mmUnit = new Report.Units.StiMillimetersUnit();
                function mm (v) {
                    return mmUnit.convertToHInches(report.unit.convertFromHInches(v));
                }
                for (var i = 0; i < report.pages.count; i++) {
                    var page = report.pages.list[i],
                        pi = setup.map.pagesInfo[page.orientation],
                        paperSize, size;
                    if (pi) {
                        page.orientation = pi.orientation;
                        if (pi.size == 256) {
                            page.paperSize = 0;
                        } else {
                            page.paperSize = (paperSize = PageHelper.getPaperSizeFromPaperKind(pi.size)) ? paperSize.kind : 0;
                        }
                        if (page.paperSize) {
                            size = PageHelper.getPaperSize(page, paperSize);
                            page.width = size.width;
                            page.height = size.height;
                        } else {
                            page.width = mm(pi.width);
                            page.height = mm(pi.height);

                        }
                        var margins = page.margins;
                        margins.left = mm(pi.marginLeft);
                        margins.top = mm(pi.marginTop);
                        margins.right = mm(pi.marginRight);
                        margins.bottom = mm(pi.marginBottom);
                    }
                }
            }
            viewer.report = report;
        }());

        function onLoad() {
            viewer.renderHtml("viewerContent");
        }
        </script>
    </head>
    <body onload="onLoad();">
        <div id="viewerContent"></div>
    </body>
</html>
<?php
 } public function openDesigner () { $setup = $this->createSetup(); ?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
 echo '<title>' . $setup->title . '</title>'; ?>
        <script src="scripts/stimulsoft.reports.js" type="text/javascript"></script>
        <script src="scripts/stimulsoft.viewer.js" type="text/javascript"></script>
        <script src="scripts/stimulsoft.designer.js" type="text/javascript"></script>
        <script src="scripts/stimulsoft.blockly.editor.js" type="text/javascript"></script>
        <style>
            html, body { font-family: sans-serif; }
        </style>
<?php
 StiHelper::init('handler.php', 900); ?>
        <script type="text/javascript">
        (function() {
            var SS = Stimulsoft,
                Locale = SS.Base.Localization.StiLocalization,
                Designer = SS.Designer,
                Report = SS.Report,
                Helper = SS.Helper,
                setup = <?= json_encode($setup, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
                options;

            SS.Base.StiLicense.loadFromFile("stimulsoft/license.php");

            Locale.addLocalizationFile("localization/ru.xml", true, "ru");
            Locale.setLocalizationFile("localization/ru.xml");

            options = new Designer.StiDesignerOptions();
            options.appearance.theme = Stimulsoft.Designer.StiDesignerTheme.Office2013LightGrayTeal;
            options.appearance.fullScreenMode = true;
            options.appearance.showSaveDialog = false;
            options.viewerOptions.appearance.theme = Stimulsoft.Viewer.StiViewerTheme[setup.style]
//            options.viewerOptions.toolbar.showSendEmailButton = true;
            options.viewerOptions.toolbar.showParametersButton = (setup.type == 0 && setup.original);

            window.designer = new Designer.StiDesigner(options, "StiDesigner", false);

            // Process SQL data sources. It can be used if it is necessary to correct the parameters of the data request.
        	designer.onBeginProcessData = function (args, callback) {
                args.connectionString = setup.connect || args.connectionString;
                args.mapParams = setup.map && setup.map.params;
                args.session = <?= json_encode($_SESSION, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                Helper.process(args, callback);
            };

            // Save report template on the server side.
            designer.onSaveReport = function (args, callback) {
                args.connectionString = setup.connect;
                if (setup.type == 0) {
                    args.serverPath = setup.serverPath;
                } else {
                    args.reportListCode = setup.reportListCode;
                    var r = args.report;
                    r.reportName = setup.report;
                    r.reportAlias = setup.title;
                    r.reportFile = setup.report + '.mrt';
                }
                Helper.process(args, callback);
            };

            window.report = new Report.StiReport();
            // if (setup.type == 0) {
            //     report.loadFile(setup.reportUrl);
            // } else {
                if (setup.map.data) {
                    report.load(setup.map.data);
                }
                report.reportName = setup.report;
                report.reportAlias = setup.title;
                report.reportFile = setup.report + '.mrt';
            // }
            designer.report = report;
        }());

        function onLoad() {
            designer.renderHtml("designerContent");
        }
        </script>
    </head>
    <body onload="onLoad();">
        <div id="designerContent"></div>
    </body>
</html>
<?php
 } private function createSetup() { $setup = $this->params; $setup->title = isEmptyProperty($setup, 'title', $setup->report); $setup->style = isEmptyProperty($setup, 'style', 'Office2013DarkGrayTeal'); $setup->connect = $this->getConnect(); if ($setup->type != 0) { $setup->map = $this->loadMapReport(); } else { $setup->report = preg_replace('/\\\\/u', '/', $setup->report); $setup->reportName = preg_replace('/.*\//u', '', $setup->report); if (substr($setup->report, 0, 1) === '/' || strpos($setup->report, ':') !== false) { $setup->serverPath = $setup->report; } else { $setup->serverPath = getServerRootPath() . 'reports/' . $setup->report . '.mrt'; } $setup->map = $this->loadStimulsoftReport($setup); } return $setup; } private function getConnect () { $data = getSessionValue('exdata'); if (empty($data['connect'])) { $data = getSessionValue('appConfig'); if (empty($data['connect'])) { echo 'Отсутствует подключение к базе данных!'; die(); } } $connect = $data['connect']; return preg_replace('/.*?:/', '',$connect); } private function loadStimulsoftReport($setup) { suspendJsonRpc(); require_once(__DIR__ . '/../app/reportbuilder/param.php'); resumeJsonRpc(); $db = connectDb(); $user = getSessionValue('user'); $report = $setup->report; $params = []; $pagesInfo = new stdClass(); $data = reportbuilder_param::loadStimulsoftReportData($report)->data; if (!empty($data)) { $userParameters = reportbuilder_param::loadStimulsoftUserParameters($db, $user, $report); $rows = reportbuilder_param::loadStimulsoftParamItems($db, $user, $report, $data, $userParameters, false); $params = self::convertLoadedParams($rows); foreach ($userParameters->pageSettings as $key => $set) if (isUndefinedProperty($set, 'useMargin')) { $set->orientation = (int)isUndefinedProperty($set, 'orientation', $key) & 1; unset($set->useMargin); $pagesInfo->{$key} = $set; } if(!empty($data->Dictionary->DataSources)) foreach ($data->Dictionary->DataSources as $ds) if (!empty($ds->SqlCommand)) { $ds->SqlCommand = preg_replace('/--.*$/um', '', $ds->SqlCommand); } } return (object)[ 'params' => $params, 'data' => $data, 'pagesInfo' => $pagesInfo ]; } private function loadMapReport() { $p = $this->params; $db = connectDb(); $userCode = $_SESSION['mapUserCode']; if (empty($p->reportListCode)) { $rows = selectSql($db, 'select reportlistcode, sample from ReportList where TaskType = ? and Name = ? order by ReportListCode limit 1', [$p->type, $p->report], PDO::FETCH_OBJ); if (empty($rows)) { throw new Exception("Отчета '{$p->report}' в задаче {$p->type} не существует!"); } $p->reportListCode = $rows[0]->reportlistcode; $json = $rows[0]->sample; } else { $json = selectSqlValue($db, 'select Sample from ReportList where ReportListCode = ?', [$p->reportListCode]); } $userOrSet = empty($p->setCode) ? "d_.UserCode = $userCode" : "d_.SetCode = '$p->setCode'"; $rows = selectSql($db, <<<"EOT"
select
    sn.SysNumberCode "sub",
    p.Name || case
        when p.TypeIn between 6 and 7 then
            case when sn.SysNumberCode = 1 then '_Начало_' else '_Конец_' end
        when p.TypeIn in (3,8,9) and sn.SysNumberCode = 2 then
            '_Все'
        else '' end "name",
    p.TypeIn "type",
    p.MultiSelect "multi",
    p.SelectAll "all",
    d.LastValue "value",
    coalesce(nullif(d.LastValueResult, ''), d.LastValue) "result"
from
    ReportIn p
    left outer join ReportUserData d on
        d.ReportUserDataCode = (select min(d_.ReportUserDataCode)
            from ReportUserData d_
            where d_.ReportInCode = p.ReportInCode and $userOrSet),
    SysNumber sn
where
    p.ReportListCode = ?
    and p.TypeIn in (1,2,3,4,5,6,7,8,9)
    and sn.SysNumberCode between 1 and 2
    and sn.SysNumberCode <= (case when (p.TypeIn in (3,8,9) and coalesce(P.SelectAll, 0) = 1)
        or (p.TypeIn between 6 and 7) then 2 else 1 end)
EOT
, [$p->reportListCode]); $params = self::convertLoadedParams($rows); $rows = selectSql($db, <<<"EOT"
select
  (PrinterOrientation / 2)::int orient,
  coalesce(PrinterOrientation, 0) & 1 orientation,
  size,
  width::float / 10 as width,
  height::float / 10 as height,
  marginleft::float * 5 / 18 as "marginLeft",
  margintop::float * 5 / 18 as "marginTop",
  marginright::float * 5 / 18 as "marginRight",
  marginbottom::float * 5 / 18 as "marginBottom"
from ReportPageInfo
where ReportListCode = ? and UserCode = ? and UseMargin = 1
EOT
, [$p->reportListCode, $userCode]); $pagesInfo = new stdClass(); foreach ($rows as $row) { $orient = $row['orient']; unset($row['orient']); $pagesInfo->{$orient} = $row; } return (object)[ 'params' => $params, 'data' => json_decode($json ?? ''), 'pagesInfo' => $pagesInfo ]; } static private function convertLoadedParams ($rows) { $params = []; foreach ($rows as $row) { $name = $row['name']; $value = $row['value']; $result = mb_strtoupper($row['result'] ?? ''); $type = (int)$row['type']; $multi = (int)$row['multi']; $all = (int)$row['all']; $sub = (int)$row['sub']; switch ($type) { case 1: $v = $value ? ("'" . dbDate($value). "'::date") : "'0001-01-01'::date"; break; case 2: $v = $value ? (($multi === 0) ? "'" . dbDateTime($value). "'::timestamp" : "'" . dbTime($value). "'::time") : (($multi === 0) ? "'0001-01-01 00:00'::timestamp" : "'00:00'::time"); break; case 3: $v = $sub === 1 ? $value : (($multi === 1 && $all === 1 && $value === '(0)' && $result === 'ВСЕ') ? 1 : 0); break; case 4: $v = "'" . $value . "'"; break; case 5: $v = $value; break; case 6: $value = $value ?: '0001-01-01'; $v = "'" . ($sub === 1 ? dbDate($value) : dbDate((new DateTime(dbDate($value)))->add(new DateInterval('P1M'))->sub(new DateInterval('P1D')))) . "'::date"; break; case 7: $value = $value ?: '0001-01-01'; $v = "'" . ($sub === 1 ? dbDate($value) : dbDate((new DateTime(dbDate($value)))->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D')))) . "'::date"; break; case 8: $v = $sub === 1 ? ($value === '(CHAR(1))' ? '(NULL)' : $value) : (($multi === 1 && $all === 1 && $value === '(CHAR(1))' && $result === 'ВСЕ') ? 1 : 0); break; case 9: $v = $sub === 1 ? $value : (($multi === 1 && $all === 1 && $value === '(99990101)' && $result === 'ВСЕ') ? 1 : 0); break; default: throw new Exception("Неизвестный тип $type параметра '$name'"); } $params[mb_strtolower($name)] = $v; $params[mb_strtolower($name) . '_список'] = "'{$row['result']}'"; } return $params; } } (new reports_main)->{$_REQUEST['method'] ?? 'openViewer'}();