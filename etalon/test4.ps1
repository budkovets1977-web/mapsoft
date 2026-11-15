. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test4.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session
    $html = Get-Page -Session $s -Url "$Base/document/?regnum=p32600353"

    foreach ($kw in @('file-link', 'saveBtn', 'dropdown-item', 'data-format', 'ifmcontentstoprint')) {
        $idx = 0; $n = 0
        while (($idx = $html.IndexOf($kw, $idx)) -ge 0 -and $n -lt 3) {
            $start = [Math]::Max(0, $idx - 400)
            $len = [Math]::Min(1100, $html.Length - $start)
            $out += "### [$kw] @$idx"
            $out += ($html.Substring($start, $len) -replace '\s+', ' ')
            $idx += $kw.Length; $n++
        }
        if ($n -eq 0) { $out += "### [$kw] не найдено" }
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8