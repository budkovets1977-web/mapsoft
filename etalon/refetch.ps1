Add-Type -AssemblyName System.Net.Http
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

# документы, потерянные из-за накладок имён
$lost = @('u625e3115','y226w0313c','u019e4054','y226w0248c','y226w0239c','y226w0325c','y226w0237c','u026e2187',
          'y226w0003c','y226w0070c','y226w0069c','y226w0347c','y226w0319c','y226w0323c','w62035404b','w62035404c',
          'w62035404d','w62035404e','w62035404f','w62035404g','w62035404h','w62035404i','y226w0208c','u625e3114',
          'y226w0214c','y226w0072c')

$all = Import-Csv "$PSScriptRoot\state\documents.csv"
$byReg = @{}
foreach ($d in $all) { $byReg[$d.regnum] = $d }

$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i=$line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}
$h = New-Object System.Net.Http.HttpClientHandler
$h.CookieContainer=$cc; $h.UseCookies=$true; $h.AllowAutoRedirect=$true
$c = New-Object System.Net.Http.HttpClient($h)
$c.Timeout=[TimeSpan]::FromSeconds(90)
$c.DefaultRequestHeaders.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')
$c.DefaultRequestHeaders.Add('Referer','https://etalonline.by/')

$out=@()
$out += "Докачиваем потерянные документы: $($lost.Count)"
$ok=0; $fail=@()
foreach ($reg in $lost) {
    $d = $byReg[$reg]
    if (-not $d) { $fail += "$reg (нет в списке)"; continue }
    # уникальное имя: базовое + regnum
    $base = [System.IO.Path]::GetFileNameWithoutExtension((Get-FileName $d.title $reg))
    $fn = $base + '_' + $reg + '.doc'
    $path = Join-Path 'F:\МЗ' $fn
    $bytes=$null
    try {
        $r=$c.GetAsync("https://etalonline.by/document/?regnum=$reg&q_id=&generate=doc").GetAwaiter().GetResult()
        $bytes=$r.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
    } catch { $fail += "$reg ($($_.Exception.Message))"; continue }
    if ($bytes -ne $null -and $bytes.Length -gt 500 -and $bytes[0] -eq 0x50 -and $bytes[1] -eq 0x4B) {
        [System.IO.File]::WriteAllBytes($path, $bytes); $ok++
        $out += "OK   $reg -> $fn"
    } else {
        # откат на HTML-страницу акта
        $html=[System.Text.Encoding]::UTF8.GetString($bytes)
        $i=$html.IndexOf('id="userContent"')
        if ($i -lt 0) { $fail += "$reg (нет текста)"; continue }
        $start=$html.IndexOf('>', $i)+1
        $end=$html.IndexOf('</body>', $start); if ($end -lt 0) { $end=$html.Length }
        $body=$html.Substring($start, $end-$start) -replace '(?s)<script[^>]*>.*?</script>',''
        $body=$body -replace 'src="/upload/','src="https://etalonline.by/upload/' -replace 'src="/local/','src="https://etalonline.by/local/'
        $doc="<html xmlns:o=""urn:schemas-microsoft-com:office:office"" xmlns:w=""urn:schemas-microsoft-com:office:word""><head><meta charset=""utf-8""><title>$($d.title)</title><style>body{font-family:'Times New Roman',serif;font-size:12pt}</style></head><body>$body</body></html>"
        [System.IO.File]::WriteAllText($path, $doc, (New-Object System.Text.UTF8Encoding($true)))
        $ok++; $out += "HTML $reg -> $fn"
    }
    Start-Sleep -Milliseconds 2500
}
$out += ""
$out += "Скачано: $ok, не удалось: $($fail.Count)"
if ($fail.Count) { $fail | ForEach-Object { $out += "  ОШИБКА $_" } }
$out += "Итого .doc в F:\МЗ: " + (Get-ChildItem 'F:\МЗ' -Filter *.doc).Count
Set-Content -Path "$PSScriptRoot\logs\refetch.txt" -Value ($out -join "`r`n") -Encoding UTF8