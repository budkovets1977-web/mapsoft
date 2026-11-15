. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test5.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session
    $u = "$Base/document/?regnum=p32600353&q_id=&generate=doc"
    $f = "$PSScriptRoot\tmp_test.doc"
    Get-File -Session $s -Url $u -OutFile $f
    $fi = Get-Item $f
    $bytes = [System.IO.File]::ReadAllBytes($f)
    $head = [System.Text.Encoding]::ASCII.GetString($bytes[0..([Math]::Min(7,$bytes.Length-1))])
    $out += "Файл: $($fi.Name), размер $($fi.Length) байт"
    $out += "Сигнатура (ASCII): $head"
    $out += "Сигнатура (hex): " + (($bytes[0..7] | ForEach-Object { $_.ToString('X2') }) -join ' ')
    # Текстовая проверка содержимого
    $txt = [System.Text.Encoding]::UTF8.GetString($bytes)
    if ($txt -match 'сокращенном') { $out += "ВНИМАНИЕ: документ в демо-режиме!" } else { $out += "Полный текст: ДА (нет пометки о сокращении)" }
    $out += "Содержит 'узнагароджанн': " + ($txt -match 'узнагароджанн')
    Remove-Item $f -Force
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8