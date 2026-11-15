. "$PSScriptRoot\common.ps1"
$log    = "$PSScriptRoot\logs\collect.log"
$listFile = "$PSScriptRoot\state\documents.csv"
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null

function Clean-Text([string]$t) {
    if ($null -eq $t) { return '' }
    $t = $t -replace '(?s)<[^>]+>', ' '
    $t = $t -replace '&nbsp;', ' '
    $t = $t -replace '&amp;', '&'
    $t = $t -replace '&lt;', '<'
    $t = $t -replace '&gt;', '>'
    $t = $t -replace '&quot;', '"'
    $t = $t -replace '&#39;', "'"
    $t = $t -replace '&#(\d+);', { param($m) [char][int]$m.Groups[1].Value }
    $t = $t -replace '\s+', ' '
    return $t.Trim()
}

$s = Load-Session
$results = New-Object System.Collections.Generic.List[object]
$seen = New-Object System.Collections.Generic.HashSet[string]
$total = 0
$fail = 0

$search = "$Base/search/?search_str=&organ%5B%5D=1400&force=1&ps=100"
$first = Get-Page -Session $s -Url $search
$total = [int][regex]::Match($first, 'id="num-res-documents">(\d+)<').Groups[1].Value
$pages = [Math]::Ceiling($total / 100)
Add-Content $log "$(Get-Date -Format s) Всего найдено: $total, страниц: $pages"

for ($p = 1; $p -le $pages; $p++) {
    $url = if ($p -eq 1) { $search } else { "$Base/search/?search_str=&organ%5B%5D=1400&force=1&ps=100&PAGEN_1=$p" }
    try {
        if ($p -eq 1) { $html = $first } else { $html = Get-Page -Session $s -Url $url }
    } catch {
        $fail++
        Add-Content $log "$(Get-Date -Format s) ОШИБКА страницы $p : $($_.Exception.Message)"
        continue
    }

    $rx = 'id="docTitle_(?<reg>[^"]+)"[\s\S]*?<a\s+href="[^"]*"\s+[^>]*class="docLink"[^>]*>(?<title>[\s\S]*?)</a>'
    $count = 0
    foreach ($m in [regex]::Matches($html, $rx)) {
        $reg = $m.Groups['reg'].Value
        if (-not $seen.Add($reg)) { continue }
        $title = Clean-Text $m.Groups['title'].Value
        $results.Add([pscustomobject]@{ regnum = $reg; title = $title })
        $count++
    }
    Add-Content $log "$(Get-Date -Format s) Стр.$p : +$count (всего $($results.Count)/$total) ошибок: $fail"
    Start-Sleep -Milliseconds 400
}

$results | Export-Csv -Path $listFile -NoTypeInformation -Encoding UTF8
Add-Content $log "$(Get-Date -Format s) ГОТОВО. Сохранено $($results.Count) записей в $listFile"