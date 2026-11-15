. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\diag3.txt"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$out = @()
$out += "Файлов .doc в F:\МЗ: " + (Get-ChildItem 'F:\МЗ\*.doc' -ErrorAction SilentlyContinue).Count
$out += "Файлов .bad: " + (Get-ChildItem 'F:\МЗ\*.bad' -ErrorAction SilentlyContinue).Count

$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i = $line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}

function Probe($label, $url, $timeoutSec) {
    $sw = [Diagnostics.Stopwatch]::StartNew()
    try {
        $req = [System.Net.HttpWebRequest]::Create($url)
        $req.CookieContainer = $script:cc
        $req.UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
        $req.Timeout = $timeoutSec * 1000
        $req.ReadWriteTimeout = $timeoutSec * 1000
        $req.AllowAutoRedirect = $true
        $req.Referer = 'https://etalonline.by/'
        $resp = $req.GetResponse()
        $code = [int]$resp.StatusCode
        $rs = $resp.GetResponseStream()
        $ms = New-Object System.IO.MemoryStream
        $rs.CopyTo($ms); $rs.Close(); $resp.Close()
        $b = $ms.ToArray()
        $sig = if ($b.Length -ge 2) { '{0:X2}{1:X2}' -f $b[0], $b[1] } else { '??' }
        "{0,-28} HTTP {1} | {2} байт | сигн {3} | {4:N1} c" -f $label, $code, $b.Length, $sig, $sw.Elapsed.TotalSeconds
    } catch {
        "{0,-28} ОШИБКА: {1} | {2:N1} c" -f $label, $_.Exception.Message, $sw.Elapsed.TotalSeconds
    }
}

$out += ""
$out += "--- одиночные пробы (текущая сессия) ---"
$out += Probe 'главная'        'https://etalonline.by/' 30
$out += Probe 'документ HTML'  'https://etalonline.by/document/?regnum=p32600353' 30
$out += Probe 'экспорт doc'    'https://etalonline.by/document/?regnum=p32600353&q_id=&generate=doc' 30

$out += ""
$out += "--- повторная авторизация и экспорт ---"
try {
    $s2 = Connect-Account
    Save-Session -Session $s2 -Name 'session2.txt'
    $out += "Повторный вход: OK"
} catch {
    $out += "Повторный вход ОШИБКА: $($_.Exception.Message)"
}

Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8