$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендании','Методика','Алгоритм','Программа','Решение',
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
$files = Get-ChildItem $OutDir -Filter *.doc
$onDisk=@{}; $files | ForEach-Object { $onDisk[$_.Name]=$true }
$bySuf=@{}
foreach ($f in $files) {
    $b=[System.IO.Path]::GetFileNameWithoutExtension($f.Name)
    if ($b -match '_([a-z0-9]+)$') { $k=$Matches[1]; if($k -match '^(?:u|w|y|hk|p|c|b|f)\d') { $bySuf[$k]=$f.Name } }
}

function HasDoc($d) {
    if ($bySuf.ContainsKey($d.regnum)) { return $true }
    return $onDisk.ContainsKey((Get-FileName $d.title $d.regnum))
}

$sel = Import-Csv "$PSScriptRoot\state\documents_filtered.csv"
$all = Import-Csv "$PSScriptRoot\state\documents.csv"

$missSel=@($sel | Where-Object { -not (HasDoc $_) })
$missAll=@($all | Where-Object { -not (HasDoc $_) })

$out=@()
$out += "Файлов .doc в $OutDir : $($files.Count)"
$out += "Объём: " + [math]::Round((($files | Measure-Object Length -Sum).Sum/1MB),1) + " МБ"
$out += ""
$out += "=== ЦЕЛЕВОЙ ФИЛЬТР (постановления+приказы+инструкции, с 2015 г.) ==="
$out += "Должно быть : $($sel.Count)"
$out += "Покрыто     : $($sel.Count - $missSel.Count)"
$out += "Отсутствует : $($missSel.Count)"
if ($missSel.Count) { $missSel | ForEach-Object { $out += "  $($_.regnum): $($_.title.Substring(0,[Math]::Min(95,$_.title.Length)))" } }
$out += ""
$out += "=== ИСХОДНЫЙ ЗАПРОС (все действующие документы Минздраса) ==="
$out += "Должно быть : $($all.Count)"
$out += "Покрыто     : $($all.Count - $missAll.Count)"
$out += "Отсутствует : $($missAll.Count)"
$out += ""
$out += "=== ФОРМАТЫ ==="
$zipc=0; $htmlc=0; $broken=@()
Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($f in $files) {
    $isZip=$false
    try { $z=[System.IO.Compression.ZipFile]::OpenRead($f.FullName); $isZip=$true; $z.Dispose() } catch {}
    if ($isZip) { $zipc++ } else {
        $h=[System.Text.Encoding]::UTF8.GetString([System.IO.File]::ReadAllBytes($f.FullName)[0..100])
        if ($h -match '(?i)<html' -and $f.Length -gt 2000) { $htmlc++ } else { $broken += $f.Name }
    }
}
$out += "docx (экспорт сайта): $zipc"
$out += "Word-HTML (собрано вручную): $htmlc"
$out += "Повреждённых: $($broken.Count)"
if ($broken.Count) { $broken | Select-Object -First 10 | ForEach-Object { $out += "  $_" } }

Set-Content -Path "$PSScriptRoot\logs\FINAL.txt" -Value ($out -join "`r`n") -Encoding UTF8