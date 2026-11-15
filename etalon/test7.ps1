. "$PSScriptRoot\common.ps1"
$log = "$PSScriptRoot\logs\test7.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$out = @()
try {
    $s = Load-Session
    $url = "$Base/search/?search_str=&organ%5B%5D=1400&force=1&ps=100"
    $html = Get-Page -Session $s -Url $url
    $out += "Длина: $($html.Length)"

    # Сколько документов в выдаче при ps=100
    $seen = New-Object System.Collections.Generic.HashSet[string]
    foreach ($m in [regex]::Matches($html, 'data-regnum="([^"]+)"')) { [void]$seen.Add($m.Groups[1].Value) }
    $out += "Уникальных regnum на странице: $($seen.Count)"

    $num = [regex]::Match($html, 'id="num-res-documents">(\d+)<').Groups[1].Value
    $out += "Всего найдено: $num"

    # Пагинация
    foreach ($kw in @('PAGEN_1', 'pagination', 'page-item', 'data-page', 'show-more', 'more-btn', 'strHtml')) {
        $i = $html.IndexOf($kw)
        if ($i -ge 0) {
            $start = [Math]::Max(0, $i - 500)
            $len = [Math]::Min(1600, $html.Length - $start)
            $out += "### [$kw] @$i"
            $out += ($html.Substring($start, $len) -replace '\s+', ' ')
        } else { $out += "### [$kw] не найдено" }
    }

    # Пример карточки результата (для извлечения названия)
    $i = $html.IndexOf('docTitle_')
    if ($i -ge 0) {
        $start = [Math]::Max(0, $i - 300)
        $out += "### карточка результата"
        $out += ($html.Substring($start, 2500) -replace '\s+', ' ')
    }
} catch {
    $out += "ОШИБКА: $($_.Exception.Message)"
}
Set-Content -Path $log -Value ($out -join "`r`n") -Encoding UTF8