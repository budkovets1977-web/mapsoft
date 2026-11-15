. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test8.txt"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$out = @()
$out += "--- cookies в state/session.txt ---"
Get-Content "$PSScriptRoot\state\session.txt" | ForEach-Object { $out += "  $_" }

$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i = $line.IndexOf('=')
    $c = New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')
    $cc.Add($c)
}
$out += "--- cookies, которые CookieContainer отдаст для https://etalonline.by/ ---"
foreach ($c in $cc.GetCookies('https://etalonline.by/')) { $out += "  $($c.Name) = $($c.Value)" }

$out += ""
$out += "--- WebClient напрямую (без заголовков) ---"
$wc = New-Object System.Net.WebClient
$wc.CookieContainer = $cc
$wc.Headers.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')
$bytes = $wc.DownloadData('https://etalonline.by/document/?regnum=p32600353&q_id=&generate=doc')
$out += "  размер: $($bytes.Length), сигнатура: " + (($bytes[0..3] | ForEach-Object { $_.ToString('X2') }) -join ' ')
$txt = [System.Text.Encoding]::UTF8.GetString($bytes)
$out += "  демо-режим: " + ($txt -match 'сокращенном')

$out += ""
$out += "--- тоже, но в отдельном runspace (как в загрузчике) ---"
$rs = [runspacefactory]::CreateRunspace(); $rs.Open()
$ps = [powershell]::Create(); $ps.Runspace = $rs
$null = $ps.AddScript({
    param($container)
    $c = New-Object System.Net.WebClient
    $c.CookieContainer = $container
    $c.Headers.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')
    $b = $c.DownloadData('https://etalonline.by/document/?regnum=p32600353&q_id=&generate=doc')
    $t = [System.Text.Encoding]::UTF8.GetString($b)
    [pscustomobject]@{ size = $b.Length; sig = (($b[0..3] | ForEach-Object { $_.ToString('X2') }) -join ' '); demo = ($t -match 'сокращенном') }
})
$null = $ps.AddArgument($cc)
$r = $ps.Invoke()
$out += "  size=$($r.size) sig=$($r.sig) demo=$($r.demo)"
$rs.Close()

Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8