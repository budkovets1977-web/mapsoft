Add-Type -AssemblyName System.Net.Http
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$out=@()

$cc = New-Object System.Net.CookieContainer
foreach ($line in (Get-Content "$PSScriptRoot\state\session.txt")) {
    if (-not $line.Trim()) { continue }
    $i=$line.IndexOf('=')
    $cc.Add((New-Object System.Net.Cookie($line.Substring(0,$i), $line.Substring($i+1), '/', 'etalonline.by')))
}
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.CookieContainer=$cc; $handler.UseCookies=$true; $handler.AllowAutoRedirect=$true
$client = New-Object System.Net.Http.HttpClient($handler)
$client.Timeout=[TimeSpan]::FromSeconds(40)
$client.DefaultRequestHeaders.Add('User-Agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36')

foreach ($reg in @('w21529947','w21731694','y226w0347c')) {
    $out += "===== $reg ====="
    # 1) обычная страница документа (без generate)
    try {
        $r=$client.GetAsync("https://etalonline.by/document/?regnum=$reg").GetAwaiter().GetResult()
        $html=[System.Text.Encoding]::UTF8.GetString($r.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult())
        $out += "  страница: HTTP $($r.StatusCode), длина $($html.Length)"
        $out += "  есть userContent: " + ($html -match 'id="userContent"')
        $out += "  демо-режим: " + ($html -match 'сокращенном')
        $out += "  data-format=3: " + ($html -match 'data-format="3"')
        foreach ($k in @('attach','image','scan','Скан','file_link','generate=')) {
            if ($html -match $k) { $out += "  маркер '$k': есть" }
        }
        $t=$html -replace '(?s)<script.*?</script>',' ' -replace '<[^>]+>',' ' -replace '\s+',' '
        $j=$t.IndexOf('Информация о документе')
        if ($j -lt 0) { $j=0 }
        $out += "  текст: " + $t.Substring($j,[Math]::Min(300,$t.Length-$j))
    } catch { $out += "  страница ОШИБКА: $($_.Exception.Message)" }

    # 2) экспорт doc
    try {
        $r2=$client.GetAsync("https://etalonline.by/document/?regnum=$reg&q_id=&generate=doc").GetAwaiter().GetResult()
        $b=$r2.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
        $sig = if ($b.Length -ge 2) { '{0:X2}{1:X2}' -f $b[0],$b[1] } else { '??' }
        $ct = $r2.Content.Headers.ContentType
        $out += "  export doc: HTTP $($r2.StatusCode), $($b.Length) байт, сигн $sig, тип $ct"
    } catch { $out += "  export doc ОШИБКА: $($_.Exception.Message)" }
    $out += ""
}
Set-Content -Path "$PSScriptRoot\logs\diag3docs.txt" -Value ($out -join "`r`n") -Encoding UTF8