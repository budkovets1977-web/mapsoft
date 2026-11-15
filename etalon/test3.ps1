. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test3.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session
    $html = Get-Page -Session $s -Url "$Base/document/?regnum=p32600353"

    $srcs = @()
    foreach ($m in [regex]::Matches($html, '<script[^>]*src="([^"]+)"')) { $srcs += $m.Groups[1].Value }
    $out += "Скриптов на странице документа: $($srcs.Count)"

    foreach ($f in $srcs) {
        if ($f -notmatch '^/') { continue }
        try { $js = Get-Page -Session $s -Url ($Base + $f) } catch { continue }
        $hits = @()
        $rx = '[^\n]*(?:\.doc|data-format|format=|save|download|print|export)[^\n]*'
        foreach ($m in [regex]::Matches($js, $rx)) {
            $t = $m.Value.Trim()
            if ($t.Length -gt 200) { $t = $t.Substring(0,200) }
            $hits += $t
        }
        if ($hits.Count -gt 0) {
            $out += "===== $f (len=$($js.Length)) ====="
            $out += ($hits | Select-Object -Unique)
        }
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8