Add-Type -AssemblyName System.Net.Http
. "$PSScriptRoot\common.ps1"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

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
$have=@{}; Get-ChildItem $OutDir -Filter *.doc | ForEach-Object { $have[$_.Name]=$true }
$sel = Import-Csv "$PSScriptRoot\state\documents_filtered.csv"
$missing = @($sel | Where-Object { -not $have.ContainsKey((Get-FileName $_.title $_.regnum)) })

# сессия
$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i=$line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.CookieContainer = $cc; $handler.UseCookies = $true; $handler.AllowAutoRedirect = $true
$client = New-Object System.Net.Http.HttpClient($handler)
$client.Timeout=[TimeSpan]::FromSeconds(40)
$client.DefaultRequestHeaders.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')
$client.DefaultRequestHeaders.Add('Referer','https://etalonline.by/')

$out=@()
$out += "Не хватает документов: $($missing.Count)"
foreach ($m in $missing) {
    $fn = Get-FileName $m.title $m.regnum
    $path = Join-Path $OutDir $fn
    $url = "https://etalonline.by/document/?regnum=$($m.regnum)&q_id=&generate=doc"
    $bytes = $null
    try { $r=$client.GetAsync($url).GetAwaiter().GetResult(); $bytes=$r.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult() } catch {}
    if ($bytes -ne $null -and $bytes.Length -gt 500 -and $bytes[0] -eq 0x50 -and $bytes[1] -eq 0x4B) {
        [System.IO.File]::WriteAllBytes($path, $bytes)
        $out += "ZIP  $($m.regnum) -> $fn"
        continue
    }
    # сервер вернул HTML: собираем .doc сами из текста акта
    $html = if ($bytes) { [System.Text.Encoding]::UTF8.GetString($bytes) } else { '' }
    if (-not $html) { $out += "ОШИБКА $($m.regnum): пустой ответ"; continue }
    $i = $html.IndexOf('id="userContent"')
    if ($i -lt 0) { $out += "ОШИБКА $($m.regnum): не найден текст акта"; continue }
    $start = $html.IndexOf('>', $i) + 1
    $end = $html.LastIndexOf('</div>')
    if ($end -le $start) { $end = $html.Length }
    $body = $html.Substring($start, [Math]::Min($end-$start, 400000))
    $head = "<html xmlns:o=""urn:schemas-microsoft-com:office:office"" xmlns:w=""urn:schemas-microsoft-com:office:word""><head><meta charset=""utf-8""><title>" + ($m.title -replace '<','&lt;') + "</title>
<style>body{font-family:'Times New Roman',serif;font-size:12pt} p{margin:0 0 6pt 0}</style></head><body>"
    $doc = $head + $body + "</body></html>"
    $enc = New-Object System.Text.UTF8Encoding($true)
    [System.IO.File]::WriteAllText($path, $doc, $enc)
    $out += "HTML $($m.regnum) -> $fn (" + [math]::Round((Get-Item $path).Length/1KB) + " КБ)"
}
$out += ""
$out += "Итого .doc теперь: " + (Get-ChildItem $OutDir -Filter *.doc).Count
Set-Content -Path "$PSScriptRoot\logs\missing.txt" -Value ($out -join "`r`n") -Encoding UTF8