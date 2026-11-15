$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендации','Методика','Алгоритм','Программа','Решение',
           'Указ','Декрат','Закон','Соглашение','Письмо','Протокол','Перечень','Список','Наставление',
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

$OutDir='F:\МЗ'
$onDisk = @{}
Get-ChildItem $OutDir -Filter *.doc | ForEach-Object { $onDisk[$_.Name] = $_.FullName }
$out=@()
$out += "Файлов .doc на диске: $($onDisk.Count)"

$all  = Import-Csv "$PSScriptRoot\state\documents.csv"
$sel  = Import-Csv "$PSScriptRoot\state\documents_filtered.csv"

# --- накладки имён: разные документы -> один файл ---
$out += ""
$out += "=== ПРОВЕРКА НАКЛАДОК ИМЁН ==="
$fnMap = @{}
foreach ($d in $all) {
    $fn = Get-FileName $d.title $d.regnum
    if (-not $fnMap.ContainsKey($fn)) { $fnMap[$fn] = @() }
    $fnMap[$fn] += $d.regnum
}
$collisions = @($fnMap.Keys | Where-Object { $fnMap[$_].Count -gt 1 })
$out += "Имён-файлов всего: $($fnMap.Count)"
$out += "Имён, под которые попали НЕСКОЛЬКО документов: $($collisions.Count)"
$lostRegnums = @()
foreach ($c in $collisions) {
    $regs = $fnMap[$c]
    $out += "  '$c' <- $($regs -join ', ')"
    # на диске лежит один файл, значит все regnum кроме одного не представлены
    for ($i=1; $i -lt $regs.Count; $i++) { $lostRegnums += $regs[$i] }
}

# --- покрытие ---
function Cover($list, $label) {
    $present=0; $missing=@()
    foreach ($d in $list) {
        $fn = Get-FileName $d.title $d.regnum
        if ($onDisk.ContainsKey($fn)) { $present++ } else { $missing += $d }
    }
    $script:out += ""
    $script:out += "=== $label ==="
    $script:out += "Должно быть: $($list.Count)"
    $script:out += "Есть файл:   $present"
    $script:out += "Нет файла:   $($missing.Count)"
    return $missing
}

$m1 = Cover $sel  "ЦЕЛЕВОЙ ФИЛЬТР (пост.+приказ+инструкция, с 2015 г.)"
$m2 = Cover $all  "ИСХОДНЫЙ ЗАПРОС (все 5749 действующих документа Минздрава)"

$out += ""
$out += "=== ИТОГ ==="
$out += "Файлов на диске: $($onDisk.Count)"
$out += "Потеряно из-за накладок имён: $($lostRegnums.Count)"
$out += "Не покрыто по исходному запросу: $($m2.Count)"
$out += "  -> потери из-за накладок имён: " + (@($m2 | Where-Object { $lostRegnums -contains $_.regnum }).Count)
$out += "  -> реально не скачано: " + (@($m2 | Where-Object { $lostRegnums -notcontains $_.regnum }).Count)
if ($m1.Count) {
  $out += ""
  $out += "Не скачано по целевому фильтру:"
  $m1 | Select-Object -First 15 | ForEach-Object { $out += "  $($_.regnum): $($_.title.Substring(0,[Math]::Min(80,$_.title.Length)))" }
}
Set-Content -Path "$PSScriptRoot\logs\verify_full.txt" -Value ($out -join "`r`n") -Encoding UTF8