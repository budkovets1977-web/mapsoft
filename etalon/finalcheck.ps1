Add-Type -AssemblyName System.IO.Compression.FileSystem

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

$OutDir = 'F:\МЗ'
$out = @()

# удалить мусор .bad
Get-ChildItem $OutDir -Filter *.bad -ErrorAction SilentlyContinue | ForEach-Object { Remove-Item $_.FullName -Force }

$files = Get-ChildItem $OutDir -Filter *.doc
$out += "Файлов .doc в папке: $($files.Count)"

# целостность
$good=0; $broken=@(); $htmlDoc=0; $min=[int]::MaxValue; $max=0
foreach ($f in $files) {
    $isOk = $false
    try {
        $zip=[System.IO.Compression.ZipFile]::OpenRead($f.FullName)
        $e=$zip.Entries | Where-Object { $_.FullName -eq 'word/document.xml' }
        if ($e -and $e.Length -gt 200) { $isOk = $true }
        $zip.Dispose()
    } catch {
        # не ZIP — проверяем, это Word-HTML (.doc) с реальным текстом
        try {
            $head = [System.Text.Encoding]::UTF8.GetString([System.IO.File]::ReadAllBytes($f.FullName)[0..200])
            if ($head -match '(?i)<html' -and $f.Length -gt 2000) { $isOk = $true; $htmlDoc++ }
        } catch {}
    }
    if ($isOk) { $good++ } else { $broken += $f.Name }
    if ($f.Length -lt $min) { $min=$f.Length }
    if ($f.Length -gt $max) { $max=$f.Length }
}
$out += "Корректных: $good"
$out += "  из них в формате docx (сгенерировано сайтом): $($good - $htmlDoc)"
$out += "  из них в формате Word-HTML (собрано вручную): $htmlDoc"
$out += "Повреждённых: $($broken.Count)"
if ($broken.Count) { $out += ($broken | Select-Object -First 10) }
$out += ("Размер: от {0:N0} до {1:N0} байт" -f $min, $max)
$out += ("Общий объём: {0:N1} МБ" -f (($files | Measure-Object Length -Sum).Sum/1MB))

# полнота по фильтру
$have=@{}; $files | ForEach-Object { $have[$_.Name]=$true }
$norm = Import-Csv 'F:\ML\minskline.by\etalon\state\documents_norm.csv' | Where-Object { $true }
$sel  = Import-Csv 'F:\ML\minskline.by\etalon\state\documents_filtered.csv'
$missing = @($sel | Where-Object { -not $have.ContainsKey((Get-FileName $_.title $_.regnum)) })
$out += ""
$out += "=== ЦЕЛЕВОЙ ФИЛЬТР (Пост.+Приказ+Инструкция, с 2015 г.) ==="
$out += "Должно быть: $($sel.Count)"
$out += "Отсутствует:  $($missing.Count)"
if ($missing.Count) { $missing | Select-Object -First 10 | ForEach-Object { $out += "  ОТСУТСТВУЕТ: $($_.title.Substring(0,[Math]::Min(90,$_.title.Length)))" } }

# сопоставление названий -> файлы, полный перечень
$all = Import-Csv 'F:\ML\minskline.by\etalon\state\documents.csv'
$rows = foreach ($d in $all) {
    $fn = Get-FileName $d.title $d.regnum
    if ($have.ContainsKey($fn)) {
        [pscustomobject]@{
            regnum   = $d.regnum
            file     = $fn
            size     = (Get-Item (Join-Path $OutDir $fn)).Length
            title    = $d.title
        }
    }
}
$rows | Sort-Object title | Export-Csv (Join-Path $OutDir '00_ПЕРЕЧЕНЬ_ДОКУМЕНТОВ.csv') -NoTypeInformation -Encoding UTF8
$out += ""
$out += "Перечень сохранён: F:\МЗ\00_ПЕРЕЧЕНЬ_ДОКУМЕНТОВ.csv (строк: $($rows.Count))"

# статистика по годам
$out += ""
$out += "--- по годам (все скачанные) ---"
$g = $files | ForEach-Object { if ($_.Name -match '^(\d{4})-\d{2}-\d{2}') { $Matches[1] } else { 'нет даты' } } | Group-Object | Sort-Object Name
foreach ($x in $g) { $out += ("  {0}: {1}" -f $x.Name, $x.Count) }

Set-Content -Path 'F:\ML\minskline.by\etalon\logs\final_check.txt' -Value ($out -join "`r`n") -Encoding UTF8