. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test2.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session

    # --- Ищем эндпоинты экспорта в JS сайта ---
    $jsFiles = @(
        '/local/templates/new/js/common.js?v=04032026',
        '/local/templates/new/js/new_custom.js?v=20112024',
        '/local/templates/new/js/navigate.js',
        '/local/templates/new/js/favorites.js'
    )
    foreach ($f in $jsFiles) {
        try { $js = Get-Page -Session $s -Url ($Base + $f) } catch { $out += "$f -> ошибка загрузки"; continue }
        $out += "===== $f (len=$($js.Length)) ====="
        foreach ($m in [regex]::Matches($js, "[^\n]*(?:format|export|\.doc|save_?doc|download)[^\n]*")) {
            $t = $m.Value.Trim()
            if ($t.Length -gt 260) { $t = $t.Substring(0,260) }
            $out += "  $t"
        }
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8