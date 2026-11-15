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

$all = Import-Csv "$PSScriptRoot\state\documents.csv"
$sel = Import-Csv "$PSScriptRoot\state\documents_filtered.csv"

$fnMap=@{}
foreach ($d in $all) { $fn=Get-FileName $d.title $d.regnum; if(-not $fnMap.ContainsKey($fn)){$fnMap[$fn]=@()}; $fnMap[$fn]+=$d.regnum }

# потерянные в ЦЕЛЕВОМ фильтре
$selReg=@{}; $sel | ForEach-Object { $selReg[$_.regnum]=$true }
$lostSel=@()
foreach ($fn in $fnMap.Keys) {
    if ($fnMap[$fn].Count -le 1) { continue }
    $regs = $fnMap[$fn]
    # в файле лежит один документ из группы; считаем потерянными все, кроме первого
    for ($i=1; $i -lt $regs.Count; $i++) {
        if ($selReg.ContainsKey($regs[$i])) { $lostSel += $regs[$i] }
    }
}
$out=@()
$out += "=== ПОТЕРИ ИЗ-ЗА НАКЛАДОК ИМЁН ==="
$out += "Всего накладок во всём списке 5749: 55 имён"
$out += "Документов, потерянных из-за накладок (весь список): " + 65
$out += "Из них попадают в ЦЕЛЕВОЙ фильтр (3290): " + $lostSel.Count
$out += ""
if ($lostSel.Count) {
  $out += "--- потерянные документы целевого фильтра ---"
  foreach ($r in $lostSel) {
    $d = $sel | Where-Object { $_.regnum -eq $r } | Select-Object -First 1
    $out += "  $r : " + $d.title.Substring(0,[Math]::Min(110,$d.title.Length))
  }
}
# реально представлено (уникальных документов, а не имён)
$onDisk=@{}
Get-ChildItem 'F:\МЗ' -Filter *.doc | ForEach-Object { $onDisk[$_.Name]=$true }
$presentSel = 0
foreach ($d in $sel) { if ($onDisk.ContainsKey((Get-FileName $d.title $d.regnum))) { $presentSel++ } }
$out += ""
$out += "=== ЧЕСТНЫЙ ИТОГ ПО ЦЕЛЕВОМУ ФИЛЬТРУ ==="
$out += "Документов в фильтре: $($sel.Count)"
$out += "Файлов на диске, соответствующих фильтру: $presentSel"
$out += "РЕАЛЬНО представлено документов: " + ($presentSel - $lostSel.Count)
$out += "НЕДОСТАЁТ: " + ($sel.Count - $presentSel + $lostSel.Count)
Set-Content -Path "$PSScriptRoot\logs\collisions.txt" -Value ($out -join "`r`n") -Encoding UTF8