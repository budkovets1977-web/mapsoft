param([int]$Limit = 0, [int]$Workers = 2, [int]$DelayMs = 2000, [string]$ListFile = '')
. "$PSScriptRoot\common.ps1"

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls11

$OutDir    = 'F:\МЗ'
if ($ListFile -eq '') { $ListFile = "$PSScriptRoot\state\documents.csv" }
$IndexFile = Join-Path $OutDir '00_ПЕРЕЧЕНЬ_ДОКУМЕНТОВ.csv'
$Progress  = "$PSScriptRoot\state\downloaded.csv"
$Log       = "$PSScriptRoot\logs\download.log"

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
New-Item -ItemType Directory -Force -Path (Split-Path $Log) | Out-Null
Get-ChildItem "$PSScriptRoot\state\part_*.csv" -ErrorAction SilentlyContinue | Remove-Item -Force

$months = @{
    'января'=1;'февраля'=2;'марта'=3;'апреля'=4;'мая'=5;'июня'=6;'июля'=7;'августа'=8;
    'сентября'=9;'октября'=10;'ноября'=11;'декабря'=12
}
$types = @('Постановление','Приказ','Инструкция','Разъяснение','Распоряжение','Положение','Правила',
           'Методические указания','Методические рекомендации','Методика','Алгоритм','Программа','Решение',
           'Указ','Декрет','Закон','Соглашение','Письмо','Протокол','Перечень','Список','Наставление',
           'Устав','Памятка','Порядок','Кодекс','Конституция','Договор','Акт','Справка','Рецензия','Доклад')

function Sanitize([string]$s) {
    if ($null -eq $s) { return '' }
    $s = $s -replace '[<>:"/\\|?*]', ' '
    $s = $s -replace '[\x00-\x1F]', ' '
    $s = $s -replace '\s+', ' '
    return $s.Trim().TrimEnd('.')
}

function Get-FileName([string]$title, [string]$regnum) {
    $t = $title.Trim()
    $sigIdx = -1; $type = ''
    foreach ($ty in $types) {
        $p = $t.LastIndexOf($ty)
        if ($p -gt $sigIdx) { $sigIdx = $p; $type = $ty }
    }
    if ($sigIdx -lt 0) { $sigIdx = $t.Length; $type = 'Документ' }
    $essence = $t.Substring(0, $sigIdx).Trim().TrimEnd('.')
    $sig     = $t.Substring($sigIdx)

    $date = ''
    $dm = [regex]::Match($sig, 'от\s+(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г')
    if ($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)) {
        $date = '{0}-{1:d2}-{2:d2}' -f $dm.Groups[3].Value, $months[$dm.Groups[2].Value], [int]$dm.Groups[1].Value
    } else {
        $dm = [regex]::Match($t, '(\d{1,2})\s+([а-яё]+)\s+(\d{4})\s*г')
        if ($dm.Success -and $months.ContainsKey($dm.Groups[2].Value)) {
            $date = '{0}-{1:d2}-{2:d2}' -f $dm.Groups[3].Value, $months[$dm.Groups[2].Value], [int]$dm.Groups[1].Value
        }
    }
    $num = ''
    $nm = [regex]::Match($sig, '№\s*([0-9A-Za-zА-Яа-я][0-9A-Za-zА-Яа\-/\.]*)')
    if (-not $nm.Success) { $nm = [regex]::Match($t, '№\s*([0-9A-Za-zА-Яа-я][0-9A-Za-zА-Яа\-/\.]*)') }
    if ($nm.Success) { $num = ($nm.Groups[1].Value -replace '\s+','' -replace '/', '-') }

    $name = Sanitize ($date + '_' + $num + '_' + $type + '_' + $essence)
    if ($name.Length -gt 170) { $name = $name.Substring(0, 170).Trim() }
    if ($name.Length -lt 20) { $name = $name + '_' + $regnum }
    return $name + '.doc'
}

# ---------- задания ----------
$docs = Import-Csv $ListFile
if ($Limit -gt 0) { $docs = @($docs | Select-Object -First $Limit) }
$jobs = New-Object System.Collections.Generic.List[object]
foreach ($d in $docs) {
    $fn = Get-FileName $d.title $d.regnum
    $jobs.Add([pscustomobject]@{ regnum = $d.regnum; title = $d.title; file = $fn; path = (Join-Path $OutDir $fn) })
}

# ---------- cookie-контейнер ----------
$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i = $line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}

$queue = New-Object System.Collections.Concurrent.ConcurrentQueue[object]
foreach ($j in $jobs) { $queue.Enqueue($j) }

$worker = {
    param($queue, $container, $DelayMs, $PartFile, $ctl)
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
    $res = New-Object System.Collections.Generic.List[object]
    $myDelay = $DelayMs

    Add-Type -AssemblyName System.Net.Http
    $handler = New-Object System.Net.Http.HttpClientHandler
    $handler.CookieContainer = $container
    $handler.UseCookies = $true
    $handler.AllowAutoRedirect = $true
    $client = New-Object System.Net.Http.HttpClient($handler)
    $client.Timeout = [TimeSpan]::FromSeconds(40)   # жёсткий лимит на всю операцию, включая тело ответа
    $client.DefaultRequestHeaders.Add('User-Agent', $ua)
    $client.DefaultRequestHeaders.Add('Referer', 'https://etalonline.by/')
    $client.DefaultRequestHeaders.Add('Accept', '*/*')

    while ($true) {
        if ($ctl['Stop'] -eq $true) { break }
        $item = $null
        if (-not $queue.TryDequeue([ref]$item)) { break }
        if ($null -eq $item) { continue }

        $status = 'error'
        if ((Test-Path $item.path) -and ((Get-Item $item.path).Length -gt 0)) {
            $status = 'skipped'
        } else {
            $url = "https://etalonline.by/document/?regnum=$($item.regnum)&q_id=&generate=doc"
            $ok = $false
            for ($try = 1; $try -le 2 -and -not $ok; $try++) {
                $bytes = $null
                try {
                    $resp = $client.GetAsync($url).GetAwaiter().GetResult()
                    $bytes = $resp.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
                    $resp.Dispose()
                } catch { $bytes = $null }

                if ($bytes -ne $null -and $bytes.Length -gt 500 -and $bytes[0] -eq 0x50 -and $bytes[1] -eq 0x4B) {
                    [System.IO.File]::WriteAllBytes($item.path, $bytes)
                    $status = 'ok'; $ok = $true
                    if ($myDelay -gt $DelayMs) { $myDelay = [Math]::Max($DelayMs, [int]($myDelay * 0.85)) }
                } elseif ($bytes -ne $null -and $bytes.Length -gt 0) {
                    [System.IO.File]::WriteAllBytes(($item.path + '.bad'), $bytes)
                    $status = 'bad'; $ok = $true
                } else {
                    Start-Sleep -Seconds (3 * $try)
                }
            }
            if ($status -eq 'error') {
                $ctl.Failures = [int]$ctl.Failures + 1
                $myDelay = [Math]::Min(60000, [int]($myDelay * 2))
            } elseif ($status -eq 'bad') {
                $ctl.Bad = [int]$ctl.Bad + 1
                $myDelay = [Math]::Min(60000, [int]($myDelay * 1.5))
            } else {
                $ctl.Ok = [int]$ctl.Ok + 1
                $ctl['LastOk'] = [DateTime]::Now
            }
            $ctl.Delay = $myDelay
            Start-Sleep -Milliseconds $myDelay
        }
        $res.Add([pscustomobject]@{ regnum=$item.regnum; status=$status; file=$item.file })
        if ([int]$ctl.Failures -ge 120) { break }
    }
    $res | Export-Csv -Path $PartFile -NoTypeInformation -Encoding UTF8
}

$ctl = [hashtable]::Synchronized(@{ Ok = 0; Failures = 0; Bad = 0; Delay = $DelayMs; Stop = $false; LastOk = [DateTime]::Now })

$pool = [runspacefactory]::CreateRunspacePool(1, $Workers)
$pool.Open()
$runners = @()
for ($w = 1; $w -le $Workers; $w++) {
    $ps = [powershell]::Create()
    $ps.RunspacePool = $pool
    $null = $ps.AddScript($worker)
    $null = $ps.AddArgument($queue)
    $null = $ps.AddArgument($cc)
    $null = $ps.AddArgument($DelayMs)
    $null = $ps.AddArgument("$PSScriptRoot\state\part_$w.csv")
    $null = $ps.AddArgument($ctl)
    $runners += $ps
}

Add-Content $Log "$(Get-Date -Format s) СТАРТ. Заданий: $($jobs.Count), потоков: $Workers, пауза: ${DelayMs}мс"

$handles = @()
foreach ($r in $runners) { $handles += $r.BeginInvoke() }

$total = $jobs.Count
$timer = [Diagnostics.Stopwatch]::StartNew()
$lastN = (Get-ChildItem $OutDir -Filter *.doc -ErrorAction SilentlyContinue).Count
$stallSec = 0
while ($true) {
    $running = @($handles | Where-Object { -not $_.IsCompleted }).Count
    if ($running -eq 0) { break }
    Start-Sleep -Seconds 15
    $n = (Get-ChildItem $OutDir -Filter *.doc -ErrorAction SilentlyContinue).Count
    $rate = if ($timer.Elapsed.TotalSeconds -gt 0) { [math]::Round($n / $timer.Elapsed.TotalSeconds, 2) } else { 0 }
    $eta  = if ($rate -gt 0) { [math]::Round(($total - $n) / $rate / 60, 1) } else { '?' }
    if ($n -gt $lastN) { $lastN = $n; $stallSec = 0 } else { $stallSec += 15 }
    Add-Content $Log "$(Get-Date -Format s) Скачано: $n/$total | темп $rate/с | осталось ~$eta мин | потоков: $running | пауза: $([int]$ctl.Delay)мс | ok=$($ctl.Ok) err=$($ctl.Failures) bad=$($ctl.Bad) | простой: ${stallSec}s"

    # сторож: если скачивание встало — останавливаемся, чтобы не висеть и не грузить сайт
    if ($stallSec -ge 300) {
        Add-Content $Log "$(Get-Date -Format s) СТОП: нет прогресса 300 c — загрузка зависла, останавливаюсь"
        $ctl['Stop'] = $true
    }
}

$all = @()
Get-ChildItem "$PSScriptRoot\state\part_*.csv" -ErrorAction SilentlyContinue | ForEach-Object {
    $all += @(Import-Csv $_.FullName)
    Remove-Item $_.FullName -Force
}
# Close/Dispose могут навсегда заблокироваться, если воркер завис на сети — не даём этому повесить всё
try { $pool.Close() } catch { Add-Content $Log "pool.Close: $($_.Exception.Message)" }
try { $pool.Dispose() } catch { Add-Content $Log "pool.Dispose: $($_.Exception.Message)" }

$all = @($all | Where-Object { $_ })
$all | Export-Csv -Path $Progress -NoTypeInformation -Encoding UTF8
$ok  = @($all | Where-Object { $_.status -eq 'ok' }).Count
$sk  = @($all | Where-Object { $_.status -eq 'skipped' }).Count
$bad = @($all | Where-Object { $_.status -eq 'bad' }).Count
$err = @($all | Where-Object { $_.status -eq 'error' }).Count
Add-Content $Log "$(Get-Date -Format s) ГОТОВО. ok=$ok skipped=$sk bad=$bad error=$err"

# ---------- итоговый перечень ----------
$map = @{}
foreach ($a in $all) { $map[$a.regnum] = $a }
$index = foreach ($j in $jobs) {
    $a = $map[$j.regnum]
    [pscustomobject]@{
        regnum = $j.regnum
        file   = $j.file
        status = if ($a) { $a.status } else { 'not-processed' }
        title  = $j.title
    }
}
$index | Sort-Object title | Export-Csv -Path $IndexFile -NoTypeInformation -Encoding UTF8
Add-Content $Log "$(Get-Date -Format s) Перечень сохранён: $IndexFile"