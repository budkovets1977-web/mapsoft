Add-Type -AssemblyName System.Net.Http
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$out=@()
$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i=$line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}
$h = New-Object System.Net.Http.HttpClientHandler
$h.CookieContainer=$cc; $h.UseCookies=$true; $h.AllowAutoRedirect=$true
$c = New-Object System.Net.Http.HttpClient($h)
$c.Timeout=[TimeSpan]::FromSeconds(120)
$c.DefaultRequestHeaders.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')
$r=$c.GetAsync("https://etalonline.by/document/?regnum=w21731694").GetAwaiter().GetResult()
$html=[System.Text.Encoding]::UTF8.GetString($r.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult())
$i=$html.IndexOf('id="userContent"')
$out += "userContent на позиции $i из $($html.Length)"
# что идёт после блока с текстом акта — ищем маркеры-хвосты
foreach ($mark in @('isSpecialTechnology','printBtn','b-print','</section>','favBtn','relatedDocs','doc-navigate')) {
    $idx = $html.IndexOf($mark, $i)
    $out += ("  маркер '{0}': позиция {1}" -f $mark, $idx)
}
$tail = $html.Substring($i, [Math]::Min(400, $html.Length-$i))
$out += ""
$out += "НАЧАЛО БЛОКА: " + $tail
[System.IO.File]::WriteAllText("$PSScriptRoot\logs\struct.txt", ($out -join "`r`n"), (New-Object System.Text.UTF8Encoding($false)))