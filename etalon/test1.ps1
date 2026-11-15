. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test1.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Connect-Account
    Save-Session -Session $s
    $out += "Вход: OK"

    $html = Get-Page -Session $s -Url "$Base/document/?regnum=p32600353"
    $out += "Длина HTML: $($html.Length)"
    $out += "Есть 'сокращенном': "    + ($html -match 'сокращенном')
    $out += "Есть 'демонстрационном': " + ($html -match 'демонстрационном')
    $out += "Есть 'только пользователям': " + ($html -match 'только пользователям')

    # Ищем кнопки экспорта (doc/pdf/html)
    $out += "--- фрагменты со словом export/download/doc ---"
    foreach ($kw in @('data-format', 'export', '/local/ajax/', 'print')) {
        $i = $html.IndexOf($kw)
        if ($i -ge 0) { $out += "[$kw] ... " + ($html.Substring([Math]::Max(0,$i-300), [Math]::Min(900, $html.Length-[Math]::Max(0,$i-300))) -replace '\s+',' ') }
        else { $out += "[$kw] не найдено" }
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8