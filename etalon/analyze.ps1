$docs = Import-Csv 'F:\ML\minskline.by\etalon\state\documents.csv'
$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендации','Методика','Алгоритм','Программа','Решение',
           'Указ','Декрет','Закон','Соглашение','Письмо','Протокол','Перечень','Список','Наставление',
           'Устав','Памятка','Порядок','Кодекс','Конституция','Договор','Акт','Справка','Рецензия','Доклад')

$out = @()
$out += "Всего документов: $($docs.Count)"
$out += ""
$out += "--- распределение по видам актов (по последнему вхождению типа в названии) ---"

$stat = @{}
$noType = 0
foreach ($d in $docs) {
    $t = $d.title; $sigIdx = -1; $type = ''
    foreach ($ty in $types) { $p = $t.LastIndexOf($ty); if ($p -gt $sigIdx) { $sigIdx = $p; $type = $ty } }
    if ($sigIdx -lt 0) { $type = '(не определён)'; $noType++ }
    if (-not $stat.ContainsKey($type)) { $stat[$type] = 0 }
    $stat[$type]++
}
foreach ($k in ($stat.Keys | Sort-Object { -$stat[$_] })) {
    $pct = [math]::Round(100 * $stat[$k] / $docs.Count, 1)
    $out += ("  {0,-26} {1,5}  ({2}%)" -f $k, $stat[$k], $pct)
}

# Нормативные акты Минздрава
$normTypes = @('Постановление','Приказ','Инструкция','Положение','Правила',
               'Методические указания','Методические рекомендации','Программа','Порядок','Решение')
$norm = @()
foreach ($d in $docs) {
    $t = $d.title; $sigIdx = -1; $type = ''
    foreach ($ty in $types) { $p = $t.LastIndexOf($ty); if ($p -gt $sigIdx) { $sigIdx = $p; $type = $ty } }
    if ($sigIdx -ge 0 -and $normTypes -contains $type) { $norm += $d }
}
$out += ""
$out += "=== НОРМАТИВНЫЕ АКТЫ (постановления, приказы, инструкции, положения, правила) ==="
$out += "Количество: $($norm.Count)"
$out += "Из них уже скачано: " + (Get-ChildItem 'F:\МЗ\*.doc' -ErrorAction SilentlyContinue).Count

# сколько осталось докачать с учётом уже скачанных
$have = @{}
Get-ChildItem 'F:\МЗ\*.doc' -ErrorAction SilentlyContinue | ForEach-Object { $have[$_.Name] = $true }

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
$todo = @($norm | Where-Object { -not $have.ContainsKey((Get-FileName $_.title $_.regnum)) })
$out += "Осталось докачать: $($todo.Count)"
$out += ""
$out += "--- нормативные по годам (сколько осталось) ---"
$g = $todo | ForEach-Object {
  $fn = Get-FileName $_.title $_.regnum
  if ($fn -match '^(\d{4})-\d{2}-\d{2}') { $Matches[1] } else { 'без даты' }
} | Group-Object | Sort-Object Name
foreach ($x in $g) { $out += ("  {0}: {1}" -f $x.Name, $x.Count) }

$todo | Select-Object regnum, title | Export-Csv 'F:\ML\minskline.by\etalon\state\documents_norm.csv' -NoTypeInformation -Encoding UTF8
$out += ""
$out += "Список нормативных сохранён: state\documents_norm.csv"

Set-Content -Path 'F:\ML\minskline.by\etalon\logs\analysis.txt' -Value ($out -join "`r`n") -Encoding UTF8