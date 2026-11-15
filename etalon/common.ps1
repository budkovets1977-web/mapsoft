# Общие функции для работы с etalononline.by
$ErrorActionPreference = 'Stop'
$Base = 'https://etalonline.by'
$StateDir = 'F:\ML\minskline.by\etalon\state'
New-Item -ItemType Directory -Force -Path $StateDir | Out-Null

function New-Session {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $s.UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
    $s.Headers.Add('sec-ch-ua', '"Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"')
    $s.Headers.Add('sec-ch-ua-mobile', '?0')
    $s.Headers.Add('sec-ch-ua-platform', '"Windows"')
    $s.Headers.Add('Accept-Language', 'ru-RU,ru;q=0.9')
    return $s
}

function Get-Headers {
    param([string]$Referer = "$Base/", [string]$Accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
    return @{
        'Accept'            = $Accept
        'Origin'            = $Base
        'Referer'           = $Referer
        'Sec-Fetch-Site'    = 'same-origin'
        'Sec-Fetch-Mode'    = 'cors'
        'Sec-Fetch-Dest'    = 'empty'
        'Sec-Fetch-User'    = '?1'
    }
}

function Connect-Account {
    param([string]$Login = 'info@clinic3.by', [string]$Password = 'Bobruisk1387')
    $s = New-Session
    $h = Get-Headers -Accept 'application/json, text/javascript, */*; q=0.01'
    $h['X-Requested-With'] = 'XMLHttpRequest'
    Invoke-WebRequest -Uri "$Base/" -WebSession $s -UseBasicParsing | Out-Null
    $r = Invoke-WebRequest -Uri "$Base/auth/login.php" -Method POST -WebSession $s -UseBasicParsing -Headers $h `
        -Body @{ login = $Login; password = $Password; captcha_code = ''; captcha_word = ''; remember = 'true' }
    $txt = [string]$r.Content
    if ($txt -notmatch '"err":0') { throw "Не удалось войти: $txt" }
    return $s
}

function Save-Session {
    param($Session, [string]$Name = 'session.txt')
    $s = New-Session
    foreach ($c in $Session.Cookies.GetCookies($Base)) {
        $c2 = New-Object System.Net.Cookie($c.Name, $c.Value, '/', $Base.Replace('https://', ''))
        $s.Cookies.Add($c2)
    }
    $arr = @()
    foreach ($c in $Session.Cookies.GetCookies($Base)) { $arr += @($c.Name + '=' + $c.Value) }
    Set-Content -Path (Join-Path $StateDir $Name) -Value ($arr -join "`n") -Encoding UTF8
}

function Load-Session {
    param([string]$Name = 'session.txt')
    $f = Join-Path $StateDir $Name
    if (-not (Test-Path $f)) { throw "Файл сессии не найден: $f" }
    $s = New-Session
    foreach ($line in (Get-Content $f)) {
        if (-not $line.Trim()) { continue }
        $i = $line.IndexOf('=')
        $n = $line.Substring(0, $i); $v = $line.Substring($i + 1)
        $s.Cookies.Add((New-Object System.Net.Cookie($n, $v, '/', 'etalonline.by')))
    }
    return $s
}

# Загрузка страницы с автоопределением кодировки
function Get-Page {
    param($Session, [string]$Url, [string]$Referer = "$Base/")
    $tmp = [System.IO.Path]::GetTempFileName()
    Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing -Headers (Get-Headers -Referer $Referer) -OutFile $tmp
    $bytes = [System.IO.File]::ReadAllBytes($tmp)
    $utf8 = New-Object System.Text.UTF8Encoding($false, $true)
    try { $html = $utf8.GetString($bytes) }
    catch { $html = [System.Text.Encoding]::GetEncoding(1251).GetString($bytes) }
    Remove-Item $tmp -Force
    return $html
}

# Загрузка бинарного файла (doc/pdf)
function Get-File {
    param($Session, [string]$Url, [string]$OutFile, [string]$Referer = "$Base/")
    Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing -Headers (Get-Headers -Referer $Referer) -OutFile $OutFile
}