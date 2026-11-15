. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\probe.txt"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$out = @()

# свежий вход
$sw = [Diagnostics.Stopwatch]::StartNew()
try { $s = Connect-Account; $out += "Вход: OK за $([math]::Round($sw.Elapsed.TotalSeconds,1)) c"; Save-Session -Session $s }
catch { $out += "Вход ОШИБКА: $($_.Exception.Message)"; Set-Content $log ($out -join "`r`n") -Encoding UTF8; exit }

$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i = $line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'

# заведомо ещё не скачанный документ (ищем по списку)
$docs = Import-Csv "$PSScriptRoot\state\documents.csv" | Select-Object -Skip 400 -First 3

foreach ($d in $docs) {
    $url = "https://etalonline.by/document/?regnum=$($d.regnum)&q_id=&generate=doc"
    $tt = [Diagnostics.Stopwatch]::StartNew()
    try {
        $req = [System.Net.HttpWebRequest]::Create($url)
        $req.CookieContainer = $cc
        $req.UserAgent = $ua
        $req.Timeout = 60000; $req.ReadWriteTimeout = 60000
        $req.AllowAutoRedirect = $true
        $req.Referer = 'https://etalonline.by/'
        $resp = $req.GetResponse()
        $tHeaders = $tt.Elapsed.TotalSeconds
        $rs = $resp.GetResponseStream()
        $ms = New-Object System.IO.MemoryStream
        $rs.CopyTo($ms); $rs.Close(); $resp.Close()
        $b = $ms.ToArray()
        $sig = if ($b.Length -ge 2) { '{0:X2}{1:X2}' -f $b[0],$b[1] } else { '??' }
        $kind = if ($sig -eq '504B') { 'ZIP/docx — OK' } else { 'НЕ ZIP (вероятно HTML)' }
        $out += ("{0}: {1:N0} байт, {2}, заголовки {3:N1} c, всего {4:N1} c" -f $d.regnum, $b.Length, $kind, $tHeaders, $tt.Elapsed.TotalSeconds)
    } catch {
        $out += ("{0}: ОШИБКА за {1:N1} c — {2}" -f $d.regnum, $tt.Elapsed.TotalSeconds, $_.Exception.Message)
    }
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8