. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test6.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session
    $html = Get-Page -Session $s -Url "$Base/search/"
    $srcs = @()
    foreach ($m in [regex]::Matches($html, '<script[^>]*src="([^"]+)"')) {
        $u = $m.Groups[1].Value
        if ($u -match 'bitrix/cache/js') { $srcs += $u }
    }
    $out += "Кэш-бандлы: " + ($srcs -join ' ; ')
    foreach ($f in $srcs) {
        $js = Get-Page -Session $s -Url ($Base + $f)
        $out += ""
        $out += "===== $f (len=$($js.Length)) ====="
        # все URL-строки и AJAX-вызовы
        foreach ($m in [regex]::Matches($js, 'url\s*:\s*[''"][^''"]+[''"]')) { $out += "  " + $m.Value }
        foreach ($m in [regex]::Matches($js, '[/][a-z_]+/[a-z_0-9./?=&-]*\.php')) { $out += "  PHP: " + $m.Value }
        foreach ($m in [regex]::Matches($js, 'href\s*=\s*[''"][^''"]*search[^''"]*[''"]')) { $out += "  LINK: " + $m.Value }
        # контекст вокруг organ
        foreach ($m in [regex]::Matches($js, '.{0,160}\borgan\b.{0,160}')) { $out += "  ORGAN: " + ($m.Value -replace '\s+',' ') }
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value (($out | Select-Object -Unique) -join "`r`n") -Encoding UTF8