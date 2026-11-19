<?php
 require_once(__DIR__.'/../../init.php'); class map_docs_editor { const UNIT_MAXVALUELENGTH = 254; const SQL_PARAM_CHAR = ':'; const attrHasBorder = 2; const attrNonPrintable = 3; const attrPrintCaption = 5; const attrNotUnderlineWhenViewing = 7; const attrCaptionToPrefixWhenPrinting = 9; const attrLeftward = 10; const attrUseFontWhenPrinting = 14; const DENY_UPDATE_HTML_NOT_OWN_RULE = 'med-card-cards_deny_update_html_not_own'; const DENY_UPDATE_HTML_NOT_OWN_TEXT = 'У вас нет права на редактирование дневников других пользователей'; const DENY_UPDATE_HTML_NOT_OWN_CODE = - 403; const tableNamesForSaveDoc = [ 'medlogstudy' => [ 'idName' => 'medlogstudycode', 'docName' => 'htmpagecode' ], 'medvisitdocument' => [ 'idName' => 'medvisitdocumentcode', 'docName' => 'htmpagecode' ] ]; private $db; private $currentHtmPageCode; private $uf; private $options; private function getUpdateFieldsSql($id) { $updateFields = $this->uf; $s = $s1 = $s2 = ""; if (isset($updateFields) && is_object($updateFields)) { foreach ($updateFields as $field => $value) { if ($id == 0) { $s1 = glueStrings($s1, $field, ","); } else { $s = $field . "="; } if (is_bool($value)) { $s2 = glueStrings($s2, $s . ($value ? "1" : "0"), ","); } elseif (is_string($value)) { $s2 = glueStrings($s2, $s . "'" . $value . "'", ","); } elseif ($value) { $s2 = glueStrings($s2, $s . $value, ","); } else { $s2 = glueStrings($s2, $s . "null", ","); } } } $res = new stdClass(); $res->s1 = $s1; $res->s2 = $s2; return $res; } private function getComponentsFromDb($id, $t) { $db = $this->db ? : $this->db = connectDb(); $id = (int) $id; $tc = $_SESSION['mapWebTaskCode']; $uf = $this->uf; if ($t) { $st = $uf ? (string) isUndefinedProperty($uf, 'sourceTable') : ''; $sql = <<<"EOT"
select
    c.htmComponentCode "id",
    c.Name "name",
    c.HTML "html",
    c.Note "note",
    c.Caption "caption",
    g.Name "groupName"
from htmComponentGroup g
join htmComponent c on c.htmComponentGroupCode = g.htmComponentGroupCode
join htmComponentTable t on t.htmComponentCode = c.htmComponentCode
where t.SourceTable = ?
    and g.Type = 1
    and g.TaskNumbers like '%,$tc,%'
order by g.Name, c.Caption
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$st]); } else { $sql = <<<"EOT"
select
    c.htmComponentCode "id",
    c.Name "name",
    c.HTML "html",
    c.Note "note",
    c.Caption "caption",
    g.Name "groupName"
from htmComponentGroup g
join htmComponent pc on pc.htmComponentGroupCode = g.htmComponentGroupCode
    and pc.htmComponentCode = $id
join htmComponent c on c.htmComponentGroupCode = g.htmComponentGroupCode
    and c.htmComponentCode <> pc.htmComponentCode
join htmComponentTable pt on pt.htmComponentCode = pc.htmComponentCode
join htmComponentTable t on t.htmComponentCode = c.htmComponentCode
    and t.SourceTable = pt.SourceTable
where g.TaskNumbers like '%,$tc,%'
    and g.Type = 1
group by g.Name, c.htmComponentCode, c.Name, c.HTML, c.Note, c.Caption
order by g.Name, c.Caption
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); } $cmp = []; while ($row = $stmt->fetch()) { $cc = new stdClass(); $cc->id = (int) $row['id']; $cc->nm = $row['name']; $cc->h = $row['html']; $cc->n = $row['note']; $cc->c = $row['caption']; $cc->gn = $row['groupName']; $cmp[] = $cc; } return $cmp; } private function getUnitsFromDb($id) { $db = $this->db ? : $this->db = connectDb(); $rows = selectSql($db, 'select
                htmUnitCode "id",
                Name "name",
                GUID "guid",
                RowIndex "rowIndex",
                Value "value",
                LongValue "longValue"
            from htmUnit
            where htmPageCode = ?', [$id]); $res = []; foreach ($rows as $row) { $u = new stdClass(); $u->n = (string) $row['name']; $u->g = (string) $row['guid']; $u->i = $row['rowIndex']; $u->v = $row['longValue'] ? $row['longValue'] : $row['value']; $res[] = $u; } return $res; } private function getDomDocument($ct, $html) { if (!$html) { return; } if ($ct) { $search = array( '<'.$ct, '</'.$ct.'>' ); $replace = array( '<div cmp ', '</div>' ); $html = str_replace($search, $replace, $html); } $html = mb_encode_numericentity( htmlspecialchars_decode( htmlentities($html, ENT_NOQUOTES, 'UTF-8', false) ,ENT_NOQUOTES ), [0x80, 0x10FFFF, 0, ~0], 'UTF-8' ); $doc = new DOMDocument('1.0', 'utf-8'); libxml_use_internal_errors(true); $doc->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_NOENT); return $doc; } private function getDomInnerHtml(DOMElement $element) { if (!$element) { return ''; } $innerHTML = ""; $children = $element->childNodes; foreach ($children as $child) { $innerHTML .= $element->ownerDocument->saveHTML($child); } return $innerHTML; } private function setDomInnerHtml(DOMDocument $doc, DOMElement $element, $html) { while ($element->hasChildNodes()) { $element->removeChild($element->firstChild); } if (!$html) { return; } $f = $doc->createDocumentFragment(); $r = @$f->appendXML($html); if ($r) { if ($f->hasChildNodes()) { $element->appendChild($f); } } else { $f = new DOMDocument(); $html = mb_encode_numericentity( htmlspecialchars_decode( htmlentities($html, ENT_NOQUOTES, 'UTF-8', false) ,ENT_NOQUOTES ), [0x80, 0x10FFFF, 0, ~0], 'UTF-8' ); $r = @$f->loadHTML('<htmlfragment>'.$html.'</htmlfragment>'); if ($r) { $import = $f->getElementsByTagName('htmlfragment')->item(0); foreach ($import->childNodes as $child) { $importedNode = $element->ownerDocument->importNode($child, true); $element->appendChild($importedNode); } } else { } } } private function getDomElement(DOMDocument $doc, $selector, DOMElement $parent) { if (!$doc) { return; } $xpath = new DOMXpath($doc); $all = $xpath->query($selector, $parent); if ($all && $all->length > 0) { return $all->item(0); } } private function getDomElementAttribute(DOMElement $element, $attribute) { if (!$element || !$element->hasAttributes()) { return 0; } return $element->getAttribute($attribute); } private function getDomChildIndex(DOMElement $parent, DOMElement $child) { if (!$parent || !$child) { return; } $all = $parent->getElementsByTagName($child->tagName); if (!$all || !$all->length) { return; } for ($i = 0; $i < $all->length; $i++) { if ($child->isSameNode($all->item($i))) { return $i; } } } private function getDomParentElementByElement(DOMElement $element, $tag) { if (!$element) { return; } $el = $element; while ($el && ($el->nodeType == XML_ELEMENT_NODE) && $el->tagName != 'body') { if (toLower($el->tagName) == toLower($tag)) { return $el; } $el = $el->parentNode; } } private function getPageData($it, $id, $ct, $html) { if ($it == 2) { $u = self::getUnitsFromDb($id); $this->currentHtmPageCode = $id; } else { $u = []; $this->currentHtmPageCode = 0; } $res = new stdClass; $res->u = $u; $doc = self::getDomDocument($ct, $html); if ($doc) { $u = self::processUnits($u, $doc); if (!isUndefinedProperty($this->options, 'dqao', false)) { $res->c1 = self::processComponentsData($doc, $u, '1,3,4,5'); } if (isUndefinedProperty($this->options, 'eqa', false)) { $res->c2 = self::processComponentsData($doc, $u, '1,3'); } if (isUndefinedProperty($this->options, 'sign', false)) { $res->sign = self::loadSign($id); } $res->rs = self::processDataLoad($id); } return $res; } private function processUnits($units, DOMDocument $doc) { $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@elid]'); if ($all && $all->length > 0) { foreach ($all as $el) { $id = $el->getAttribute('elid'); $cfg = safe_json_decode($el->getAttribute('cfg')); if (!isUndefinedProperty($cfg, 'n')) { continue; } foreach ($units as $u) { if (!isUndefinedProperty($u, 'g') || $u->g != $cfg->g) { continue; } $u->nd = (bool) isUndefinedProperty($cfg, 'sn'); $v = $u->v; switch ($id) { case 'wrap': case 'chk': $u->v = $v == 'false' ? false : (bool) $v; break; case 'btn': if (is_string($v)) { $u->v = safe_json_decode($v); } break; case 'txt': case 'memo': if ($u->nd) { if (is_string($v)) { $u->v = safe_json_decode($v); } } else { $u->v = (string) ($v); } break; case 'num': $u->v = number($v); break; default: $u->v = $v; break; } } } } return $units; } private function processComponentsData(DOMDocument $doc, $units, $queryTypes) { if ($queryTypes) { $queryTypes = ' and q.querytype in ('.$queryTypes.')'; } $xpath = new DOMXpath($doc); $cmpAll = $xpath->query('//div[@cmp]'); if (!$cmpAll || $cmpAll->length == 0) { return; } $db = $this->db ? : $this->db = connectDb(); $cd = []; foreach($cmpAll as $cmp) { $cn = "'".$cmp->getAttribute('nm')."'"; $sql = <<<"EOT"
select
    array_to_json(array_agg(row_to_json(c)))
from (
    select
        c.name "n",
        g.name "gn",
        coalesce(nullif(c.caption,''), c.name) "cn",
        q.name "qn",
        q.querytype "qt",
        q.elementname "en",
        q.querytext "qs"
    from htmcomponent c
    join htmcomponentgroup g on g.htmcomponentgroupcode = c.htmcomponentgroupcode
    join htmcomponentquery q on q.htmcomponentcode = c.htmcomponentcode
    where coalesce(g.type,0) = 1
        and c.name = $cn
        $queryTypes
    order by q.querytype, q.position
) c
EOT;
$cAll = selectSqlValue($db, $sql, null); if (!$cAll) { continue; } $cAll = safe_json_decode($cAll); foreach ($cAll as $c) { if (!isUndefinedProperty($c, 'qs')) { continue; } if ($c->qt != 4 && $c->qt != 5) { $c->qs = self::setQueryParams($c->qs, $units, $cmp); try { if (stripos($c->qs, 'select') === 0 || stripos($c->qs, 'with') === 0) { $c->d = selectSql($db, $c->qs, null); } else { executeSql($db, $c->qs, null); } } catch (Throwable $exc) { $c->er = $exc->getMessage(); } } $cd[] = $c; } } return $cd; } private function loadSign($id) { $res = new stdClass(); $db = $this->db ? : $this->db = connectDb(); $rows = selectSql($db, 'select coalesce(p.Ready, 0) ready, fn_htmSignInfo(p.htmPageCode, 1) info,
                (select COUNT(*) from htmPageT where htmPageCodeParent = p.htmPageCode) cnt
            from htmPageT AS p
            where p.htmPageCode = ?', [$id]); if (is_array($rows)) { $rows = $rows[0]; $res->ready = $rows['ready'] == 1; $res->info = (string) $rows['info']; $res->count = (int) $rows['cnt']; } return $res; } private function processDataLoad($id) { $db = $this->db ? : $this->db = connectDb(); $rows = selectSql($db, 'SELECT HtmPageLoadCode, Data, State, Position
                FROM HtmPageLoad WHERE HtmPageCode = ? AND State IN (1, 3)
                ORDER BY Position, HtmPageLoadCode, HtmPageCode', [$id]); return $rows; } private function setQueryParams($sql, $units, $cmp) { if (!$sql) { return $sql; } $ri = null; $tbl = self::getDomParentElementByElement($cmp, 'table'); $tr = self::getDomParentElementByElement($cmp, 'tr'); if ($tbl && $tr) { $ri = self::getDomChildIndex($tbl, $tr); } $matches = null; if (preg_match_all('/'.self::SQL_PARAM_CHAR.'[а-яa-z0-9_]{1,}/iu', $sql, $matches)) { $matches = array_unique($matches[0]); foreach ($matches as $m) { $n = substr($m, 1); $r = null; switch (toLower($n)) { case 'currententerprisecode': $r = (string) getSessionValue('mapEnterpriseCode'); break; case 'currentdate': $r = "'".date('Y-m-d')."'"; break; case 'currenttime': $r = "'".date('H:i')."'"; break; case 'currentusercode': $r = (string) getSessionValue('mapUserCode'); break; case 'currenthtmpagecode': $r = $this->currentHtmPageCode ? (string) $this->currentHtmPageCode : 'null'; break; case 'sourcetable': $r = $this->uf ? (string) isUndefinedProperty($this->uf, 'sourceTable') : ''; break; case 'sourcekeyname': $r = $this->uf ? ((string) isUndefinedProperty($this->uf, 'sourceTable')).'Code' : ''; break; case 'sourcekeycode': $r = $this->uf ? (string) isUndefinedProperty($this->uf, 'sourceKey') : ''; break; case 'currentfilialcode': $r = (string) getSessionValue('EnterpriseStructureCode'); break; default: $r = ''; $u = null; foreach ($units as $u_) { if ($u_->n == $n && $ri != null && $u_->i === $ri) { $u = $u_; break; } } if (!$u) { $u = arrayFindObject($units, 'n', $n); } if (is_object($u)) { if (is_array($u->v)) { if (empty($u->v)) { $v = ''; } else { $v = reset($u->v); } } else { $v = $u->v; } if (is_object($v)) { if (property_exists($v, 'code')) { $v = $v->code; } else { $b = false; foreach ($v as $key => $value) { if (is_int($value)) { $v = $value; $b = true; break; } } if (!$b) { $v = isUndefinedProperty($v, 'displayValue'); } } } if (is_string($v)) { $r = "'".$v."'"; } else { $r = (string) $v; } } break; } if ($r !== null) { if ($r === '') { $r = 'null'; } $sql = str_replace($m, $r, $sql); } } } return $sql; } private function getUnitsSql($units) { if (!is_array($units)) { return; } $db = $this->db ? : $this->db = connectDb(); $sql = ''; foreach ($units as $u) { $value = $longValue = isUndefinedProperty($u, 'nd') || is_array(isUndefinedProperty($u, 'v')) ? is_array($u->v) && array_key_exists('Value', $u->v) ? $u->v['Value'] ?? '' : json_encode($u->v ?? '', JSON_UNESCAPED_UNICODE) : isUndefinedProperty($u, 'v', ''); if (safe_strlen($value) > self::UNIT_MAXVALUELENGTH) { $value = ''; } else { $longValue = ''; } $value = $db->quote($value ?? ''); $longValue = $db->quote($longValue ?? ''); if ($sql) { $sql .= "\n union "; } $sql .= "select
    xmlelement(
        name Unit,
        xmlforest(
            E'{$u->n}' as Name,
            E'{$u->g}' as Guid,
            nullif('{$u->i}', '') as Row,
            nullif({$value}, '') as Val,
            nullif({$longValue}, '') as Lval))::text as u"; } if ($sql) { $sql = "select string_agg((u)::text, E'') as units from (" .$sql. ") t(u)"; } return $sql; } private function getGlobalSettings() { $db = $this->db ? : $this->db = connectDb(); $res = new stdClass(); $st = $this->uf ? (string) isUndefinedProperty($this->uf, 'sourceTable') : ''; $sk = $this->uf ? (int) isUndefinedProperty($this->uf, 'sourceKey') : 0; $row = selectSqlValue($db, "select HTML from htmSetup where Type = 3 and Name = 'global_settings' limit 1"); if (!empty($row)) { $res->settings = json_clean_decode($row); } $res->solis = new stdClass(); $res->solis->active = false; $res->solis->complete = false; if (strcasecmp($st, 'medlogstudy') == 0) { $sql = <<<"EOT"
select
    l.MedLabStudyCode as f1,
    lt.MedLabStudyTestCode as f2,
    lt.Name as f3,
    lt.UnitName as f4,
    case s.BiopsySyncStatus in (3,4) and s.State = 2 and trim(coalesce(s.Barcode, '')) <> ''
        when true then 1
        else 0
    end as f5
from MedLogStudy s
join MedLabStudy l on l.MedStudyCode = s.MedStudyCode
    and l.Active = 1 and l.ElisType is null
left join MedLabStudyTest lt on lt.MedLabStudyCode = l.MedLabStudyCode
    and lt.Active = 1
where s.MedLogStudyCode = ?
EOT;
$row = selectSql($db, $sql, [$sk]); foreach ($row as $r) { $res->solis->labStudyCode = (int) $r['f1']; if ((int) $r['f2'] != 0) { $res->solis->tests = isUndefinedProperty($res->solis, 'tests', []); $t = new stdClass(); $t->code = (int) $r['f2']; $t->name = (string) $r['f3']; $t->unitName = (string) $r['f4']; $res->solis->tests[] = $t; } if (!$res->solis->active) { $res->solis->active = true; } if ((int) $r['f5'] == 1 && !$res->solis->complete) { $res->solis->complete = true; } } } return $res; } private function getPageSettings($it, $id) { $res = new stdClass(); $st = $this->uf ? (string) isUndefinedProperty($this->uf, 'sourceTable') : ''; $sk = $this->uf ? (int) isUndefinedProperty($this->uf, 'sourceKey') : ''; if ($it == -1 || empty($st) || empty($sk)) { return $res; } $db = $this->db ? : $this->db = connectDb(); if ($it == 1 || $it == 3) { $code = $id; } elseif (!empty($st)) { switch (toLower($st)) { case 'medlogstudy': $sql = "select s.htmPageCode from {$st} l ". "join medStudy s on s.medStudyCode = l.medStudyCode ". "where {$st}Code = {$sk}"; break; default: $sql = "select t.htmPageCode from {$st} d ". "join htmTemplate t on t.htmTemplateCode = d.htmTemplateCode ". "where {$st}Code = {$sk}"; break; } $code = selectSqlValue($db, $sql, null); } if (!$code) { return $res; } $sql = <<<"EOT"
select json_build_object('id', id, 's', s, 'o', o, 'w', w, 'h', h, 'm',
  json_build_object('t', mt, 'r', mr, 'b', mb, 'l', ml)) f
from (
    select
        coalesce(Size, 9) s,
        coalesce(PrinterOrientation, 0) o,
        coalesce(Width, 0) w,
        coalesce(Height, 0) h,
        coalesce(MarginTop, 54) mt,
        coalesce(MarginRight, 18) mr,
        coalesce(MarginBottom, 54) mb,
        coalesce(MarginLeft, 72) ml,
        ReportPageInfoCode AS id
    from ReportPageInfo
    where htmPageCode = ?
        and UserCode is null
    limit 1
) ps
EOT;
$res = selectSql($db, $sql, [$code]); if (!is_array($res) || !count($res)) { $m = new stdClass(); $res = new stdClass(); $res->s = 9; $res->o = 0; $m->t = 54; $m->r = 18; $m->b = 54; $m->l = 72; $res->m = $m; $res->w = 0; $res->h = 0; $res->id = null; } else { $res = safe_json_decode((string) $res[0]['f']); } $res->m->t = round($res->m->t*5/18, 2); $res->m->r = round($res->m->r*5/18, 2); $res->m->b = round($res->m->b*5/18, 2); $res->m->l = round($res->m->l*5/18, 2); if ($res->w > 0) { $res->w = round($res->w / 10, 2); } if ($res->h > 0) { $res->h = round($res->h / 10, 2); } if (($res->o & 1) == 1) { $res->o = 1; } else { $res->o = 0; } return $res; } private function getDomElementByGuid($doc, $inGuid, $inEl) { if (!$doc) { return; } $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@cfg]'); if (!$all || !$all->length) { return; } foreach ($all as $el) { if (!$el) { continue; } $cfg = safe_json_decode(self::getDomElementAttribute($el, 'cfg')); if (!isUndefinedProperty($cfg, 'g')) { continue; } if ($cfg->g == $inGuid && !$el->isSameNode($inEl)) { return $el; } } } private function getDomElementByName($doc, $inName, $inEl) { if (!$doc) { return; } $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@cfg]'); if (!$all || !$all->length) { return; } foreach ($all as $el) { if (!$el) { continue; } $cfg = safe_json_decode(self::getDomElementAttribute($el, 'cfg')); if (!isUndefinedProperty($cfg, 'n')) { continue; } if ($cfg->n == $inName && !$el->isSameNode($inEl)) { return $el; } } } private function setUniqueGUIDs($doc) { if (!$doc) { return; } $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@cfg]'); if (!$all || !$all->length) { return; } foreach ($all as $el) { if (!$el) { continue; } $cfg = safe_json_decode(self::getDomElementAttribute($el, 'cfg')); if (!isUndefinedProperty($cfg, 'g') || !self::getDomElementByGuid($doc, $cfg->g, $el)) { continue; } $cfg->g = toLower(generateUUID()); $el->setAttribute('cfg', json_encode($cfg)); } } private function setUniqueElements($doc) { if (!$doc) { return; } $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@cfg]'); if (!$all || !$all->length) { return; } $res = []; foreach ($all as $el) { if (!$el) { continue; } $cfg = safe_json_decode(self::getDomElementAttribute($el, 'cfg')); if (!isUndefinedProperty($cfg, 'n')) { continue; } while (true) { if (!self::getDomElementByName($doc, $cfg->n, $el)) { break; } $cfg->n = 'Копия_'.$cfg->n; $el->setAttribute('cfg', json_encode($cfg)); } $u = new stdClass(); $u->g = $cfg->g; $u->n = $cfg->n; $u->i = isUndefinedProperty($cfg, 'i'); $res[] = $u; } return $res; } private function utf8ToWin1251Bytes(string $str): string { static $win1251Set = null; if ($win1251Set === null) { $win1251Set = []; for ($i = 0; $i < 256; $i++) { $char = mb_convert_encoding(chr($i), 'UTF-8', 'CP1251'); if ($char !== '') { $win1251Set[$char] = true; } } } $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY); $result = ''; foreach ($chars as $char) { if (isset($win1251Set[$char])) { $result .= $char; } else { $code = $this->utf8_ord($char); $hex = strtoupper(dechex($code)); $result .= "__x{$hex};"; } } return $result; } private function encodeSpecialChars(string $str): string { $specialCodes = [ 160, 8203, 161, 162, 163, 164, 165, 166, 167, 169, 171, 172, 174, 176, 177, 181, 182, 187, 188, 189, 190, 191, 247, 8240, 8364, 8482, 8721, 8734, 8764, 8800, 8804, 8805 ]; if ($str === '') { return $str; } $specialSet = array_fill_keys($specialCodes, true); $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY); $result = ''; foreach ($chars as $char) { $code = $this->utf8_ord($char); if (isset($specialSet[$code])) { $result .= '&#' . $code . ';'; } else { $result .= $char; } } return $result; } private function utf8_ord(string $char): int { $code = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8')); return $code[1]; } public function save($params) { $db = $this->db ? : $this->db = connectDb(); $userCode = (int) getSessionValue('mapUserCode'); $id = (int) isUndefinedProperty($params, 'id'); $isMap = isEmptyProperty($params, 'isMap', false); $html = (string) isEmptyProperty($params, 'h'); $c = (bool) isUndefinedProperty($params, 'c', false); if (!$isMap && !$c && $id) { $this->checkUpdateAccess($id); } $d = (bool) isUndefinedProperty($params, 'd'); $ct = (string) isUndefinedProperty($params, 'ct', 'cmp'); $this->uf = isUndefinedProperty($params, 'uf'); $uf = self::getUpdateFieldsSql($id); $s2 = $uf->s2 ? ',' . $uf->s2 : $uf->s2; if (!$isMap) { $html = base64_decode($html); $html = $this->encodeSpecialChars($html); $html = $this->utf8ToWin1251Bytes($html); } $htmPageLoadCode = isUndefinedProperty($params, 'htmPageLoadCode'); $units = isUndefinedProperty($params, 'u'); if ((bool)isEmptyProperty($params, 'encoded')) { foreach ($units as $unit) { $unitsV = $unit->v; $unitsDV = $unit->dv; if ($unitsV && is_string($unitsV)) { $unit->v = base64_decode($unitsV); } if ($unitsDV && is_string($unitsDV)) { $unit->dv = base64_decode($unitsDV); } } } $params->u = $units; $u = ''; if ($d && is_array($units)) { $u = cloneFn($units); } $it = $d ? 2 : 3; if ($id == 0) { $s1 = $uf->s1 ? ',' . $uf->s1 : $uf->s1; if ($c) { $sql = "INSERT INTO HtmComponent (HTML $s1) VALUES (? $s2)"; $sqlParams = [$html]; } else { $sql = <<<"EOT"
insert into htmPageT (UserCode, isTemplate $s1) values ($userCode, $it $s2)
EOT;
$sqlParams = []; } } else { if ($c) { $sql = "UPDATE HtmComponent SET HTML = ? $s2 WHERE HtmComponentCode = ?"; $sqlParams = [$html, $id]; } else { $sql = <<<"EOT"
                    UPDATE HtmPageT SET UserCode = $userCode $s2 WHERE HtmPageCode = $id
EOT;
} } $sqlD = "DELETE FROM HtmUnitT WHERE HtmPageCode = ?"; if (is_array($units)) { $sqlU = self::getUnitsSql($units); } $db->beginTransaction(); try { $res = new stdClass(); $stmt = $db->prepare($sql); $stmt->execute($sqlParams ?? []); if ($id == 0) { $id = $db->lastInsertId($c ? 'seq_htmComponent' : 'seq_htmPageT'); $this->currentHtmPageCode = $id; if (!$c) { $tableName = $this->uf ? isUndefinedProperty($this->uf, 'sourceTable') : null; $sourceKey = $this->uf ? isUndefinedProperty($this->uf, 'sourceKey') : null; if (($tableName && $sourceKey) && !empty(self::tableNamesForSaveDoc[strtolower($tableName)])) { $tableName = strtolower($tableName); $docName = self::tableNamesForSaveDoc[$tableName]['docName']; $idName = self::tableNamesForSaveDoc[$tableName]['idName']; $sqlUpdateTable = <<<EOT
UPDATE $tableName
SET $docName = $id
WHERE $idName = ?::INT
EOT;
executeSql($db, $sqlUpdateTable, [ $sourceKey ]); } } } $res->id = (int) $id; if (!$c) { $stmt = $db->prepare($sqlD); $stmt->execute([$id]); if (is_array($units) && $sqlU) { $stmt = $db->prepare($sqlU); $stmt->execute(); $units = fetchValue($stmt, 'units'); } else { $units = ''; } $sqlP = <<<"EOT"
select FN_htmPageWriteToFile(?,
    compress(E'<Page>' || xmlelement(name Html,
        asa_trim(?))::text ||
            E'<Units>' || asa_trim(?)::text || E'</Units></Page>'), 0) as res
EOT;
$stmt = $db->prepare($sqlP); $stmt->execute([$id, $html, $units]); } $db->commit(); if ($htmPageLoadCode) { $par = new stdClass(); $par->htmPageLoadCode = $htmPageLoadCode; $par->state = 2; $par->note = ''; $this->updateDataLoad($par); } } catch (Throwable $e) { $db->rollback(); if ($htmPageLoadCode) { $par = new stdClass(); $par->htmPageLoadCode = $htmPageLoadCode; $par->state = 3; $par->note = 'Ошибка при сохранении документа'; $this->updateDataLoad($par); } throw $e; } if (!$c) { $res->ec = (int) fetchValue($stmt, 'res'); if (!$isMap && $d) { $doc = self::getDomDocument($ct, $html); if ($doc) { if ((isUndefinedProperty($this->uf, 'sourceTable') === 'medLogStudy') && isUndefinedProperty($this->uf, 'sourceKey')) { $sqlUpdateTableMedLogStudy = <<<EOT
UPDATE medLogStudy
SET important = null
WHERE medlogstudycode = ?::INT
EOT;
executeSql($db, $sqlUpdateTableMedLogStudy, [ isUndefinedProperty($this->uf, 'sourceKey') ]); } $res->c = self::processComponentsData($doc, $u, '2'); $res->gs = self::getGlobalSettings(); } } $uf = isUndefinedProperty($params, 'uf'); $tableName = isEmptyProperty($uf, 'sourceTable'); $recordId = isEmptyProperty($uf, 'sourceKey'); if ($tableName === 'MedLogStudy') { $sqlRawRes = <<<SQL
                    SELECT RawResult "RawResult"
                    FROM MedLogStudy MLS
                    WHERE MedLogStudyCode = ?
                    SQL;
$rawRes = selectSqlValue($db, $sqlRawRes, [ $recordId ]); if ($rawRes) { $sqlRes = <<<SQL
                        SELECT Result "res"
                        FROM MedLogStudy MLS
                        WHERE MedLogStudyCode = ?
                        SQL;
$result = selectSqlValue($db, $sqlRes, [ $recordId ]); if (!$result) { $sqlDecResult = <<<SQL
                            SELECT FN_htmMask2Str(MLS.htmPageCode, MS.ResultMask) "res"
                            FROM MedLogStudy MLS
                            LEFT JOIN medStudy MS ON MS.MedStudyCode = MLS.MedStudyCode
                            WHERE MedLogStudyCode = ?
                            SQL;
$decResult = selectSqlValue($db, $sqlDecResult, [ $recordId ]); $sqlDecDate = <<<SQL
                            SELECT exectime
                            FROM MedLogStudy MLS
                            LEFT JOIN medStudy MS ON MS.MedStudyCode = MLS.MedStudyCode
                            WHERE MedLogStudyCode = ?
                            SQL;
$decDate = selectSqlValue($db, $sqlDecDate, [ $recordId ]); $resDate = dbDate($decDate); $sqlRes = <<<SQL
                            UPDATE MedLogStudy
                            SET result = ?,
                                exectime = ?
                            WHERE MedLogStudyCode = ?
                            SQL;
$result = selectSqlValue($db, $sqlRes, [ $decResult, $resDate, $recordId ]); } } } } return $res; } public function updateDataLoad($params) { $db = $this->db ? : $this->db = connectDb(); $id = isUndefinedProperty($params, 'htmPageLoadCode'); $state = isUndefinedProperty($params, 'state'); if (!$id) { return false; } $sql = <<<EOT
UPDATE HtmPageLoad SET
    State = ?,
    Note = ?,
    ProceedTime = ?
WHERE HtmPageLoadCode = ?
EOT;
return executeSql($db, $sql, [ $state, isUndefinedProperty($params, 'note'), $state === 2 ? dbDate(new DateTime()) : null, $id ]); } protected function checkUpdateAccess ($htmPageId) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    type
FROM htmPage
WHERE htmPageCode = :htmPageId
EOT;
$type = selectSqlValue($db, $sql, [':htmPageId' => $htmPageId]); switch($type) { case 'Дневник врача': $doctorId = getSessionValue('mapMedDoctor')['MedDoctorCode']; $userId = getSessionValue('mapUserCode'); $accessData = selectSql($db, <<<"EOT"
SELECT
    MV.MedDoctorCode "doctorId",
    MVD.userCode "userId"
FROM MedVisitDocument MVD
LEFT JOIN MedVisitPatient MVP ON MVP.MedVisitPatientCode = MVD.MedVisitPatientCode
LEFT JOIN MedVisit MV ON MV.MedVisitCode = MVP.MedVisitCode
WHERE MVD.HtmPageCode = :htmPageId
LIMIT 1
EOT,
[ ':htmPageId' => $htmPageId ] ); if (!empty($accessData) && $accessData[0]['doctorId'] !== $doctorId && $accessData[0]['userId'] !== $userId) { $this->checkAccess( self::DENY_UPDATE_HTML_NOT_OWN_RULE, self::DENY_UPDATE_HTML_NOT_OWN_TEXT, self::DENY_UPDATE_HTML_NOT_OWN_CODE, true); } break; } } protected function checkAccess($rule, $errorText, $errorCode, $isInverse = false) { if (($isInverse && testUserAccess($rule)) || (!$isInverse && !testUserAccess($rule))) { throw new Exception($errorText, $errorCode); } } public function load($params) { $id = (int) isUndefinedProperty($params, 'id'); $t = (bool) isUndefinedProperty($params, 't'); $c = (bool) isUndefinedProperty($params, 'c'); $d = (bool) isUndefinedProperty($params, 'd'); $v = (bool) isUndefinedProperty($params, 'v'); $this->uf = isUndefinedProperty($params, 'uf'); $ct = (string) isUndefinedProperty($params, 'ct', 'cmp'); $this->options = (object) isUndefinedProperty($params, 'o'); $db = $this->db ? : $this->db = connectDb(); if ($c) { $sql = <<<EOT
                SELECT
                    C.HtmComponentCode  "id",
                    NULL                "t",
                    C.Name              "n",
                    C.Caption           "c",
                    C.HTML              "h",
                    -1                  "it",
                    NULL                "sourceKey",
                    NULL                "sourceTable"
                FROM HtmComponent C
                WHERE C.HtmComponentCode = ?
EOT;
} else { $sql = <<<EOT
                SELECT
                    P.HtmPageCode       "id",
                    P.Type              "t",
                    P.Name              "n",
                    NULL                "c",
                    P.HTML              "h",
                    P.IsTemplate        "it",
                    P.SourceKey         "sourceKey",
                    P.SourceTable       "sourceTable"
                FROM HtmPage P
                WHERE P.HtmPageCode = ?
EOT;
} $stmt = $db->prepare($sql); $stmt->execute([$id]); $res = $stmt->fetch(); if ($res) { $res['id'] = (int) $res['id']; $res['it'] = (int) $res['it']; if (!$res['c']) { unset($res['c']); } $res['ps'] = self::getPageSettings($res['it'], $id); if ($d) { if ($res['it'] == 0) { $processParams = new stdClass(); $processParams->html = $res['h']; $processParams->htmPageId = $res['id']; $processParams->recordId = $res['sourceKey']; $processParams->tableName = $res['sourceTable']; $res['h'] = self::processOldVersion($processParams); $res['pd'] = new stdClass; if (isUndefinedProperty($this->options, 'sign', false)) { $res['pd']->sign = self::loadSign($id); } } else { $res['gs'] = self::getGlobalSettings(); $res['pd'] = self::getPageData($res['it'], $id, $ct, $res['h']); } } if (isUndefinedArrayValue($res, 'h')) { $res['h'] = base64_encode($res['h']); } } if ($t || $c) { if (!is_array($res)) { $res = []; } $res['cmp'] = self::getComponentsFromDb($id, $t); } return $res; } public function updateFields($params) { $db = $this->db ? : $this->db = connectDb(); $id = (int) isUndefinedProperty($params, 'id'); $c = (bool) isUndefinedProperty($params, 'c'); if ($id == 0) { return false; } $this->uf = isUndefinedProperty($params, 'uf'); $uf = self::getUpdateFieldsSql($id); $s2 = $uf->s2; if (!$s2) { return false; } if ($c) { $sql = <<<"EOT"
update htmComponent set $s2 where htmComponentCode = ?
EOT;
} else { $sql = <<<"EOT"
update htmPageT set $s2 where htmPageCode = ?
EOT;
} $db->beginTransaction(); try { $stmt = $db->prepare($sql); $stmt->execute([$id]); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } private function findTagEnd(string $s, int $from): ?int { $len = strlen($s); $q = null; for ($i = $from; $i < $len; $i++) { $ch = $s[$i]; if ($q !== null) { if ($ch === $q) $q = null; continue; } if ($ch === '"' || $ch === "'") { $q = $ch; continue; } if ($ch === '>') return $i; } return null; } public function replaceHtmlFragment(string $source_html, string $replacing_html, string $tag, ?string $attr = null, int $posStart = 0): string { $openExact = '<' . $tag . $attr . '>'; $openPrefix = '<' . $tag; $closeTag = '</' . $tag . '>'; $posOpen = strpos($source_html, $openExact, $posStart); if ($posOpen === false) { return $source_html; } $scanPos = $posOpen + strlen($openExact); $depth = 1; while ($depth > 0) { $nextOpen = strpos($source_html, $openPrefix, $scanPos); $nextClose = strpos($source_html, $closeTag, $scanPos); if ($nextClose === false) { return $source_html; } if ($nextOpen !== false && $nextOpen < $nextClose) { $depth++; $scanPos = $nextOpen + 1; } else { $depth--; $scanPos = $nextClose + strlen($closeTag); } } $posClose = $scanPos - strlen($closeTag); $innerStart = $posOpen + strlen($openExact); $innerLen = $posClose - $innerStart; $source_html = substr_replace($source_html, $replacing_html, $innerStart, $innerLen); $afterClose = $innerStart + strlen($replacing_html) + strlen($closeTag); return $this->replaceHtmlFragment($source_html, $replacing_html, $tag, $attr, $afterClose); } public function updateComponent($params) { $db = $this->db ? : $this->db = connectDb(); $id = (int) isUndefinedProperty($params, 'id'); $tc = $cc = 0; $nm = []; $cur = selectSql($db, 'select html, name from htmComponent where htmComponentCode = ?', [$id]); if (is_array($cur)) { $cur = $cur[0]; } $curName = $cur['name']; $curHtml = $cur['html']; if ($curName) { $sql = <<<"EOT"
select
    0 "rt",
    htmPageCode "id",
    HTML "h",
    '' "n",
    Name "caption"
from htmPage
where htmPageCodeParent is null
    and IsTemplate = 3
    and HTML like '%$curName%'
union all
select
    1 "rt",
    htmComponentCode "id",
    HTML "h",
    Name "n",
    coalesce(Caption, Name) "caption"
from htmComponent
where htmComponentCode <> ?
    and HTML like '%$curName%'
EOT;
$stmt = $db->prepare($sql); $stmt->execute([$id]); while ($row = $stmt->fetch()) { $isC = (int) $row['rt'] == 1; $html = $row['h']; $html = str_replace ('&nbsp;', '@nbsp;', $html); $attr = ' nm="' . $curName . '"'; $rth = $this->replaceHtmlFragment($html, $curHtml, 'cmp', $attr); $doc = self::getDomDocument(null, $rth); if (!$doc) { continue; } $xpath = new DOMXpath($doc); self::setUniqueGUIDs($doc); $u = self::setUniqueElements($doc); $all = $xpath->query('//*[@cfg]'); if ($all && $all->length) { foreach ($all as $el) { if (!$el) { continue; } $cfg = self::getDomElementAttribute($el, 'cfg'); $el->setAttribute('cfg', htmlspecialchars($cfg, ENT_QUOTES)); } } $body = $doc->getElementsByTagName('body')->item(0); $newHtml = strtr(self::getDomInnerHtml($body), array('&amp;'=>'&')); $newHtml = str_replace( array("\xE2\x80\x8B", "@nbsp;"), array("&#8203;", "&nbsp;"), $newHtml); $p = new stdClass(); $p->id = $row['id']; $p->c = (int) $row['rt'] == 1; $p->h = base64_encode($newHtml); $p->u = $u; self::save($p); if ($isC) { $cc++; $nm[] = 'комопнент: '.$row['caption']; } else { $tc++; $nm[] = 'шаблон: '.$row['caption']; } } } $res = new stdClass(); $res->tc = $tc; $res->cc = $cc; $res->nm = $nm; return $res; } public function updateSign($params) { $db = $this->db ? : $this->db = connectDb(); $id = (int) isUndefinedProperty($params, 'id'); $r = (int) isUndefinedProperty($params, 'r'); executeSql($db, 'update htmPageT set Ready = nullif(?, 0) where htmPageCode = ?', [$r, $id]); return self::loadSign($id); } public function saveGlobalSettings($settings) { $db = $this->db ? : $this->db = connectDb(); $sql = <<<"EOT"
insert into htmSetup (type, html, name)
values (3, ?, 'global_settings')
    on conflict (type, name) do
        update set html = excluded.html
EOT;
executeSql($db, $sql, [$settings]); return true; } private function oldVersionCheckAttribute($attributes, $attribute) { $a = pow(2, $attribute); return ($attributes & $a) === $a; } private function oldVersionGetChkLabelText(DOMElement $chk, $a) { if (self::oldVersionCheckAttribute($a, self::attrLeftward)) { $tn = $chk->previousSibling; } else { $tn = $chk->nextSibling; } if ($tn ) { return $tn->nodeValue; } } private function oldVersionSetChkLabelText(DOMElement $chk, $a, $text) { $tn = $chk->previousSibling; if ($tn ) { $tn->nodeValue = ''; } $tn = $chk->nextSibling; if ($tn ) { $tn->nodeValue = ''; } if (self::oldVersionCheckAttribute($a, self::attrLeftward)) { $tn = $chk->previousSibling; } else { $tn = $chk->nextSibling; } if ($tn ) { $tn->nodeValue = $text; } } private function oldVersionGetFontByElementAttr(DOMElement $element, $html) { $res = $html; if (!$element) { return $res; } $s = '<font'; $fontName = (string) self::getDomElementAttribute($element, 'fname'); if (!$fontName) { $fontName = 'Microsoft Sans Serif'; } $s .= ' face="'.$fontName.'"'; $fontSize = (int) self::getDomElementAttribute($element, 'fsize'); if (!$fontSize) { $fontSize = 6; } $s .= ' size='.floor($fontSize / 3); $fontColor = (int) self::getDomElementAttribute($element, 'fcolor'); if ($fontColor) { $s .= ' color="#'.($fontColor & 0xFF).(($fontColor >> 8) & 0xFF).(($fontColor >> 16) & 0xFF).'"'; } $s .= '>'; $fontStyle = (int) self::getDomElementAttribute($element, 'fstyle'); if ($fontStyle) { if (($fontStyle & 1) === 1) { $res = '<b>'.$res.'</b>'; } if (($fontStyle & 2) === 2) { $res = '<i>'.$res.'</i>'; } if (($fontStyle & 4) === 4) { $res = '<u>'.$res.'</u>'; } if (($fontStyle & 8) === 8) { $res = '<s>'.$res.'</s>'; } } return $s.$res.'</font>'; } private function oldVersionGetBottomCellByCell(DOMElement $tbl, DOMElement $td) { if (!$tbl || !$td) { return; } $tr = self::getDomParentElementByElement($td, 'tr'); if (!$tr) { return; } $tdIndex = self::getDomChildIndex($tr, $td); $trIndex = self::getDomChildIndex($tbl, $tr); $all = $tbl->getElementsByTagName('tr'); if (!$all || !$all->length) { return; } $tr = $all->item($trIndex + 1); if (!$tr) { return; } $all = $tr->getElementsByTagName('td'); if (!$all || !$all->length) { return; } return $all->item($tdIndex); } private function oldVersionCheckNoBorderTable(DOMElement $el) { $tbl = self::getDomParentElementByElement($el, 'table'); if (!$tbl || toLower(self::getDomElementAttribute($tbl, 'id')) == 'wrap2_') { return; } $tbla = self::oldVersionCheckAttribute((int) self::getDomElementAttribute($tbl, 'elementattr'), self::attrHasBorder); if ($tbla) { return true; } if ($td = self::getDomParentElementByElement($el, 'td')) { return true; if (stripos(self::getDomElementAttribute($td, 'style'), 'BORDER-BOTTOM: #000000 1px solid') !== false) { return true; } if ($td2 = self::oldVersionGetBottomCellByCell($tbl, $td)) { if (stripos(self::getDomElementAttribute($td2, 'style'), 'BORDER-TOP: #000000 1px solid') !== false) { return true; } } } } private function insertMapResults($params) { $db = $this->db ?: $this->db = connectDb(); $html = isEmptyProperty($params, 'html'); $recordId = isEmptyProperty($params, 'recordId'); $tableName = isEmptyProperty($params, 'tableName'); $resultJson = null; $ret = new stdClass(); function processDateValue($key, $value) { if (safe_strpos(strtolower($key), 'date') !== false) { $value = (new DateTime($value))->format('d.m.Y'); } return $value ?? ''; } $sqlResults = <<<EOT
            SELECT RawResult "resultJson"
            FROM MedLogStudy
            WHERE MedLogStudyCode = ?
            EOT;
if ($tableName === 'MedLogStudy') { $resultJson = selectSqlValue($db, $sqlResults, [ $recordId ]); $resultJson = safe_json_decode($resultJson, true); } $ret->html = $html; $ret->results = $resultJson; if (!is_array($resultJson)) { return $ret; } foreach ($resultJson as $key => $result) { if (is_array($result) && array_key_exists('NormalValue', $result)) { $resultJson[$key . '_NormalValue'] = $result['NormalValue']; } } $doc = $this->getDomDocument('span', $html); if (!$doc) { return $ret; } $xpath = new DOMXpath($doc); $all = $xpath->query('//*[@id]'); if (!$all || !$all->length) { return $ret; } foreach ($all as $el) { if (!$el) { continue; } $id = toLower($this->getDomElementAttribute($el, 'id')); $attr = (int) $this->getDomElementAttribute($el, 'elementattr'); $guid = $this->getDomElementAttribute($el, 'guid'); $elName = $this->getDomElementAttribute($el, 'elementname'); if (!empty($resultJson) && array_key_exists($elName, $resultJson)) { if (is_array($resultJson[$elName])) { if (isset($resultJson[$elName]['Value'])) { $el->setAttribute('value', processDateValue($elName, $resultJson[$elName]['Value'])); } if (isset($resultJson[$elName]['Important'])) { $el->setAttribute('class', 'Important'); } if ($id === 'table_') { function findElementsByAttribute($node, $attr, $value, &$results = []) { if ($node->hasAttributes() && $node->getAttribute($attr) === $value) { $results[] = $node; } foreach ($node->childNodes as $child) { if ($child instanceof DOMElement) { findElementsByAttribute($child, $attr, $value, $results); } } return $results; }; $tbody = $el->getElementsByTagName('tbody')[0]; $fr = $el->getElementsByTagName('tr')[0]; $tr_copy = $fr->cloneNode(true); $resultArr = $resultJson[$elName]; self::setDomInnerHtml($doc, $tbody, ''); for ($i = 0; $i < count($resultArr); $i++) { $values = $resultArr[$i]; foreach ($values as $key => $value) { $fe = findElementsByAttribute($tr_copy, 'elementname', $key); if($fe && count($fe)) { $fe[0]->setAttribute('value', processDateValue($key, $value)); } } $nr = $tbody->appendChild($tr_copy); $tr_copy = $nr->cloneNode(true); } } } else { $resultValue = mb_strtolower(trim($resultJson[$elName] ?? '')); $el->setAttribute('value', processDateValue($elName, $resultJson[$elName])); if ($resultValue !== '') { $children = $el->getElementsByTagName('option'); if ($children->length) { foreach ($children as $child) { $nodeValue = mb_strtolower(trim($child->nodeValue ?? '')); $child->removeAttribute('selected'); if ($nodeValue === $resultValue) { $child->setAttribute('selected', 'selected'); } } } } } } } $body = $doc->getElementsByTagName('body')->item(0); $html = $this->getDomInnerHtml($body); $ret->html = $html; return $ret; } public function processOldVersion($params) { $db = $this->db ?: $this->db = connectDb(); $html = isEmptyProperty($params, 'html'); $mapDocResults = $this->insertMapResults($params); $html = isEmptyProperty($mapDocResults, 'html'); $results = isEmptyProperty($mapDocResults, 'results'); $params->html = $html; $params->results = $results; $this->saveMapDocument($params); $doc = self::getDomDocument('span', $html); if (!$doc) { return $html; } $xpath = new DOMXpath($doc); $all = $xpath->query('//td[contains(@style, "line-through")]'); foreach ($all as $el) { if (!$el) { continue; } $tr = $el->parentNode; $tr->setAttribute('style', 'display:none'); } while (true) { $more = false; $all = $xpath->query('//*[@id]'); if (!$all || !$all->length) { return $html; } foreach ($all as $el) { if (!$el) { continue; } $id = toLower(self::getDomElementAttribute($el, 'id')); $attr = (int) self::getDomElementAttribute($el, 'elementattr'); $guid = self::getDomElementAttribute($el, 'guid'); if (self::oldVersionCheckAttribute($attr, self::attrNonPrintable) || ($el->tagName == 'hr' && stripos($id, 'page-break-line_') !== false)) { $el->setAttribute('style', 'display:none'); continue; } if ($el->hasAttribute('onclick')) { $el->removeAttribute('onclick'); } $sText = ''; $sClass = self::getDomElementAttribute($el, 'class'); $s = ''; $do = false; switch ($id) { case 'button_': $el->parentNode->removeChild($el); break; case 'checkbox_': $do = true; $e = self::getDomElement($doc, './/input[@name="'.$guid.'"]', $el); if ($e) { if (self::oldVersionCheckAttribute($attr, self::attrPrintCaption)) { if ((bool) self::getDomElementAttribute($e, 'checked')) { $sText = (string) self::oldVersionGetChkLabelText($e, $attr); } self::oldVersionSetChkLabelText($e, $attr, ''); } else { if ((bool) self::getDomElementAttribute($e, 'checked')) { $sText = 'да'; } else { $sText = 'нет'; } } } break; case 'editbox_': case 'numeric_': case 'time_': case 'date_': $do = true; $sText = (string) self::getDomElementAttribute($el, 'value'); break; case 'radiobtn_': $do = true; $childs = $el->getElementsByTagName('input'); if ($childs) { foreach ($childs as $child) { if ((string) self::getDomElementAttribute($child, 'name') == $guid && (bool) self::getDomElementAttribute($child, 'checked')) { $e = $child->nextSibling; if ($e) { $sText = $e->nodeValue; break; } } } } break; case 'textarea_': $do = true; $sText = $el->nodeValue; break; case 'combobox_': case 'listbox_': $do = true; $childs = $el->getElementsByTagName('option'); if ($childs) { foreach ($childs as $child) { if ((bool) self::getDomElementAttribute($child, 'selected')) { $sText = $child->nodeValue; break; } } } break; case 'c_wrap_': $style = (string) self::getDomElementAttribute($el, 'style'); $pel = $el->parentNode; if (stripos($style, 'display: none') !== false && $pel) { if (toLower(self::getDomElementAttribute($pel, 'id')) == 'wrap_') { $pel->setAttribute('style', 'display:none'); } } $el->setAttribute('style', 'border-width:0px'); if ($pel) { $wc = ''; $pguid = self::getDomElementAttribute($pel, 'guid'); $e = self::getDomElement($doc, './/span[@guid="L_'.$pguid.'"]', $pel); if ($e) { $eattr = (int) self::getDomElementAttribute($e, 'elementattr'); if (!self::oldVersionCheckAttribute($eattr, self::attrPrintCaption)) { $e->setAttribute('style', 'display:none'); } $wc = trim($e->nodeValue ?? ''); } $pattr = (int) self::getDomElementAttribute($pel, 'elementattr'); if ($wc && self::oldVersionCheckAttribute($pattr, self::attrCaptionToPrefixWhenPrinting) && self::oldVersionCheckAttribute($pattr, self::attrPrintCaption)) { if ($el->hasChildNodes()) { $child = $el->childNodes->item(0); if ($child && $child->tagName == 'div' && !trim($child->previousSibling->nodeValue ?? '')) { $child->firstChild->nodeValue = $wc.' '; } else { $el->firstChild->nodeValue = $wc.' '; } } else { $el->firstChild->nodeValue = $wc.' '; } if ($e) { $e->nodeValue = ''; } } } break; case 'wrap2_': $wc = self::getDomElement($doc, './/td[@class="WRAP_CAPTION"]', $el); $wb = self::getDomElement($doc, './/td[@class="WRAP_BODY"]', $el); if ($wc && $wb) { $pstyle = (string) self::getDomElementAttribute($wb->parentNode, 'style'); if (stripos($pstyle, 'display: none') !== false) { $el->setAttribute('style', 'display:none'); } $pstyle = (string) self::getDomElementAttribute($wb->parentNode, 'style'); if (stripos($pstyle, 'display: none') === false) { $el->setAttribute('style', 'border-width:0px'); if (!self::oldVersionCheckAttribute($attr, self::attrPrintCaption)) { $new = '<div>'.self::getDomInnerHtml($wb).'</div>'; } else if (self::oldVersionCheckAttribute($attr, self::attrCaptionToPrefixWhenPrinting) && !empty($wc->nodeValue)) { if ($wb->hasChildNodes()) { $child = self::getDomElement($doc, './/div', $wb); if ($child && !trim($child->previousSibling->nodeValue ?? '')) { self::setDomInnerHtml($doc, $child, self::getDomInnerHtml($wc).' '.self::getDomInnerHtml($child)); } else { self::setDomInnerHtml($doc, $wb, self::getDomInnerHtml($wc).' '.self::getDomInnerHtml($wb)); } } else { self::setDomInnerHtml($doc, $wb, self::getDomInnerHtml($wc).' '.self::getDomInnerHtml($wb)); } $wc->nodeValue = ''; $new = self::getDomInnerHtml($wb); } else { $new = '<div>'.self::getDomInnerHtml($wc).'</div><div>'.self::getDomInnerHtml($wb).'</div>'; } $nn = $doc->createElement('font'); self::setDomInnerHtml($doc, $nn, $new); $pn = $el->parentNode; if ($pn) { $pn->replaceChild($nn, $el); $more = true; } } } break; } if ($more) { break; } $sText = nl2br($sText); if ($do) { if ($sText && ($id == 'checkbox_' || $id == 'editbox_')) { $s = '&nbsp;'; } if (self::oldVersionCheckAttribute($attr, self::attrUseFontWhenPrinting)) { $sText = self::oldVersionGetFontByElementAttr($el, $sText); } if (self::oldVersionCheckNoBorderTable($el) || self::oldVersionCheckAttribute($attr, self::attrNotUnderlineWhenViewing)) { $nn = $doc->createElement('bdo'); if ($sClass) { $nn->setAttribute('class', $sClass); } } else { $nn = $doc->createElement('u'); } self::setDomInnerHtml($doc, $nn, $sText.$s); $pn = $el->parentNode; if ($pn) { $pn->replaceChild($nn, $el); } } } $body = $doc->getElementsByTagName('body')->item(0); $html = self::getDomInnerHtml($body); if (!$more) { break; } } return $html; } private function saveMapDocument($params) { $html = isEmptyProperty($params, 'html'); $htmPageId = isEmptyProperty($params, 'htmPageId'); $recordId = isEmptyProperty($params, 'recordId'); $tableName = isEmptyProperty($params, 'tableName'); $results = isEmptyProperty($params, 'results'); $units = $this->getUnitsFromDb($htmPageId); if ($units && $results) { foreach ($units as $unit) { foreach ($results as $key => $value) { if ($unit->n === $key && trim($unit->v ?? '') === '') { $unit->v = $value; } } } } $params = new stdClass(); $params->isMap = true; $params->id = $htmPageId; $params->uf = new stdClass(); $params->uf->sourceKey = $recordId; $params->uf->sourceTable = $tableName; $params->h = $html; $params->u = $units; $params->c = false; $params->d = true; $this->save($params); } } handleJsonRpc('map_docs_editor');