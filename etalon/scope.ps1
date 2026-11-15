$docs = Import-Csv 'F:\ML\minskline.by\etalon\state\documents.csv'
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
$have = @{}
Get-ChildItem 'F:\МЗ\*.doc' -ErrorAction SilentlyContinue | ForEach-Object { $have[$_.Name] = $true }

# разбор: тип акта + год
$items = @()
foreach ($d in $docs) {
    $t = $d.title; $sigIdx=-1; $type=''
    foreach ($ty in $types) { $p=$t.LastIndexOf($ty); if($p -gt $sigIdx){$sigIdx=$p;$type=$ty} }
    $year = 0
    $dm=[regex]::Match($t,'(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г')
    if ($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)) { $year=[int]$dm.Groups[3].Value }
    $fn = Get-FileName $d.title $d.regnum
    $items += [pscustomobject]@{ regnum=$d.regnum; type=$type; year=$year; file=$fn; have=$have.ContainsKey($fn) }
}

$norm = @('Постановление','Приказ','Инструкция','Положение','Правила','Методические указания','Методические рекомендации','Программа','Порядок','Решение')
function Stat($label, $sel) {
  $sel = @($sel)
  $n = $sel.Count
  $left = @($sel | Where-Object { -not $_.have }).Count
  $hours = [math]::Round($left / 3.2 / 60, 1)
  return ("{0,-52} всего {1,5} | осталось {2,5} | ~{3} ч" -f $label, $n, $left, $hours)
}

$out = @()
$out += "Потолок сервиса: ~130 документов за ~40 мин = ~3,2 документа в минуту"
$out += "Уже скачано: $($have.Count)"
$out += ""
$out += "--- варианты объёма ---"
$out += (Stat "ВСЕ документы органа"                           $items)
$out += (Stat "Нормативные (Пост.+Приказ+Инстр.+Полож.+Прав.)" (@($items | Where-Object { $norm -contains $_.type })))
$out += (Stat "Только Постановления"                           (@($items | Where-Object { $_.type -eq 'Постановление' })))
$out += (Stat "Только Постановления + Приказы"                  (@($items | Where-Object { $_.type -eq 'Постановление' -or $_.type -eq 'Приказ' })))
$out += (Stat "Пост.+Приказ+Инструкция, с 2010 г."             (@($items | Where-Object { ($_.type -eq 'Постановление' -or $_.type -eq 'Приказ' -or $_.type -eq 'Инструкция') -and $_.year -ge 2010 })))
$out += (Stat "Пост.+Приказ+Инструкция, с 2015 г."             (@($items | Where-Object { ($_.type -eq 'Постановление' -or $_.type -eq 'Приказ' -or $_.type -eq 'Инструкция') -and $_.year -ge 2015 })))
$out += (Stat "Пост.+Приказ, с 2020 г."                        (@($items | Where-Object { ($_.type -eq 'Постановление' -or $_.type -eq 'Приказ') -and $_.year -ge 2020 })))
$out += (Stat "Только Постановления с 2015 г."                  (@($items | Where-Object { $_.type -eq 'Постановление' -and $_.year -ge 2015 })))
$out += (Stat "Только Приказы с 2020 г."                       (@($items | Where-Object { $_.type -eq 'Приказ' -and $_.year -ge 2020 })))
$out += (Stat "Всё с 2020 г."                                  (@($items | Where-Object { $_.year -ge 2020 })))

Set-Content -Path 'F:\ML\minskline.by\etalon\logs\scope.txt' -Value ($out -join "`r`n") -Encoding UTF8