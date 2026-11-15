$docs = Import-Csv 'F:\ML\minskline.by\etalon\state\documents.csv'
$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендации','Методика','Алгоритм','Программа','Решение',
           'Указ','Декрет','Закон','Соглашение','Письмо','Протокол','Перечень','Список','Наставление',
           'Устав','Памятка','Порядок','Кодекс','Конституция','Договор','Акт','Справка','Рецензия','Доклад')
$months = @{'января'=1;'февраля'=2;'марта'=3;'апреля'=4;'мая'=5;'июня'=6;'июля'=7;'августа'=8;'сентября'=9;'октября'=10;'ноября'=11;'декабря'=12}
function Sanitize([string]$s){ if($null -eq $s){return ''}; $s=$s -replace '[<>:"/\\|?*]',' '; $s=$s -replace '[\x00-\x1F]',' '; $s=$s -replace '\s+',' '; return $s.Trim().TrimEnd('.') }
function Get-FileName([string]$title,[string]$regnum){
    $t=$title.Trim(); $sigIdx=-1; $type=''
    foreach($ty in $types){ $p=$t.LastIndexOf($ty); if($p -gt $sigIdx){$sigIdx=$p;$type=$ty} }
    if($sigIdx -lt 0){$sigIdx=$t.Length;$type='Документ'}
    $essence=$t.Substring(0,$sigIdx).Trim().TrimEnd('.'); $sig=$t.Substring($sigIdx)
    $date=''; $dm=[regex]::Match($sig,'от\s+(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г')
    if($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)){$date='{0}-{1:d2}-{2:d2}' -f $dm.Groups[3].Value,$months[$dm.Groups[2].Value],[int]$dm.Groups[1].Value}
    else { $dm=[regex]::Match($t,'(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г'); if($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)){$date='{0}-{1:d2}-{2:d2}' -f $dm.Groups[3].Value,$months[$dm.Groups[2].Value],[int]$dm.Groups[1].Value} }
    $num=''; $nm=[regex]::Match($sig,'№\s*([0-9A-Za-zА-Яа-я][0-9A-Za-zА-Яа\-/\.]*)')
    if(-not $nm.Success){$nm=[regex]::Match($t,'№\s*([0-9A-Za-zА-Яа-я][0-9A-Za-zА-Яа\-/\.]*)')}
    if($nm.Success){$num=($nm.Groups[1].Value -replace '\s+','' -replace '/','-')}
    $name=Sanitize ($date+'_'+$num+'_'+$type+'_'+$essence)
    if($name.Length -gt 170){$name=$name.Substring(0,170).Trim()}
    if($name.Length -lt 20){$name=$name+'_'+$regnum}
    return $name+'.doc'
}

$sel = @()
$noYear = 0; $noType = 0
foreach ($d in $docs) {
    $t = $d.title; $sigIdx=-1; $type=''
    foreach ($ty in $types) { $p=$t.LastIndexOf($ty); if($p -gt $sigIdx){$sigIdx=$p;$type=$ty} }
    if (-not ($type -in @('Постановление','Приказ','Инструкция'))) { $noType++; continue }
    $year = 0
    $dm=[regex]::Match($t,'(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г')
    if ($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)) { $year=[int]$dm.Groups[3].Value }
    if ($year -lt 2015) { if ($year -eq 0) { $noYear++ }; continue }
    $sel += [pscustomobject]@{ regnum=$d.regnum; title=$d.title; type=$type; year=$year }
}

$sel | Export-Csv 'F:\ML\minskline.by\etalon\state\documents_filtered.csv' -NoTypeInformation -Encoding UTF8

$have = @{}
Get-ChildItem 'F:\МЗ\*.doc' -ErrorAction SilentlyContinue | ForEach-Object { $have[$_.Name] = $true }
$left = @($sel | Where-Object { -not $have.ContainsKey((Get-FileName $_.title $_.regnum)) })

$out = @()
$out += "Фильтр: Постановление / Приказ / Инструкция, год >= 2015"
$out += "Отобрано документов: $($sel.Count)"
$out += "Уже скачано из них:   $($sel.Count - $left.Count)"
$out += "Осталось скачать:     $($left.Count)"
$out += ""
$out += "Исключено по типу акта (не Пост./Приказ/Инструкция): $noType"
$out += "Исключено по году (<2015): $noType"
$out += "  из них без распознанной даты: $noYear"
$out += ""
$out += "--- по типам ---"
foreach ($x in ($sel | Group-Object type | Sort-Object Name)) { $out += ("  {0,-16} {1}" -f $x.Name, $x.Count) }
$out += "--- по годам ---"
foreach ($x in ($sel | Group-Object year | Sort-Object Name)) { $out += ("  {0}: {1}" -f $x.Name, $x.Count) }
$out += ""
$out += "Оценка времени при потолке 3,2 док/мин: ~" + [math]::Round($left.Count / 3.2 / 60, 1) + " ч"
Set-Content -Path 'F:\ML\minskline.by\etalon\logs\filter.txt' -Value ($out -join "`r`n") -Encoding UTF8