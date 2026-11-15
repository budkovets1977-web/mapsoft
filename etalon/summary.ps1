. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\summary.txt"
$OutDir = 'F:\МЗ'

$docs  = Import-Csv "$PSScriptRoot\state\documents.csv"
$jobs = @{}
$months = @{'января'=1;'февраля'=2;'марта'=3;'апреля'=4;'мая'=5;'июня'=6;'июля'=7;'августа'=8;'сентября'=9;'октября'=10;'ноября'=11;'декабря'=12}
$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендации','Методика','Алгоритм','Программа','Решение',
           'Указ','Декрет','Закон','Соглашение','Письмо','Протокол','Перечень','Список','Наставление',
           'Устав','Памятка','Порядок','Кодекс','Конституция','Договор','Акт','Справка','Рецензия','Доклад')
function Sanitize([string]$s){ if($null -eq $s){return ''}; $s = $s -replace '[<>:"/\\|?*]',' '; $s = $s -replace '[\x00-\x1F]',' '; $s = $s -replace '\s+',' '; return $s.Trim().TrimEnd('.') }
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

foreach ($d in $docs) { $jobs[(Get-FileName $d.title $d.regnum)] = $d }

$onDisk = Get-ChildItem $OutDir -Filter *.doc -ErrorAction SilentlyContinue
$bad = Get-ChildItem $OutDir -Filter *.bad -ErrorAction SilentlyContinue
$out = @()
$out += "=== СОСТОЯНИЕ ЗАГРУЗКИ ==="
$out += "Заданий всего: $($docs.Count)"
$out += "Скачано .doc  : $($onDisk.Count)"
$out += "Брак .bad     : $($bad.Count)  (демо-режим, HTML — не документ)"
$out += "Осталось      : $($docs.Count - $onDisk.Count)"

# строим перечень по факту наличия файлов
$rows = foreach ($f in $onDisk) {
    $d = $jobs[$f.Name]
    [pscustomobject]@{
        file    = $f.Name
        size    = $f.Length
        regnum  = if ($d) { $d.regnum } else { '' }
        title   = if ($d) { $d.title } else { '(не сопоставлено)' }
    }
}
$rows | Sort-Object title | Export-Csv -Path (Join-Path $OutDir '00_ПЕРЕЧЕНЬ_ССКАЧАННЫХ.csv') -NoTypeInformation -Encoding UTF8
$out += ""
$out += "Перечень скачанных сохранён: F:\МЗ\00_ПЕРЕЧЕНЬ_СКАЧАННЫХ.csv"

# удаляем мусор .bad
foreach ($b in $bad) { Remove-Item $b.FullName -Force -ErrorAction SilentlyContinue }
$out += "Удалено .bad: $($bad.Count)"

$out += ""
$out += "--- по годам ---"
$g = $onDisk | ForEach-Object { if ($_.Name -match '^(\d{4})-\d{2}-\d{2}') { $Matches[1] } else { 'без даты' } } |
     Group-Object | Sort-Object Name
foreach ($x in $g) { $out += ("  {0}: {1}" -f $x.Name, $x.Count) }

$out += ""
$out += "--- по видам актов (первые 15) ---"
$t = $onDisk | ForEach-Object { if ($_.Name -match '_\d{4}-\d{2}-\d{2}_[^_]+_([^_]+)_') { $Matches[1] } else { '?' } } |
     Group-Object | Sort-Object Count -Descending | Select-Object -First 15
foreach ($x in $t) { $out += ("  {0}: {1}" -f $x.Name, $x.Count) }

$out += ""
$out += ("Итого объём: {0:N1} МБ" -f (($onDisk | Measure-Object Length -Sum).Sum / 1MB))

Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8