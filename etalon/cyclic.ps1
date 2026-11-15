# Циклическая загрузка документов Минздрава РБ с etalononline.by.
# Работает волнами: отрабатывает доступный лимит (~130 док.), затем ждёт снятия блокировки
# и продолжает. Останавливается, когда все документы скачаны или несколько волн подряд
# не дали прогресса.
#
# Запуск:
#   powershell -NoProfile -ExecutionPolicy Bypass -File "F:\ML\minskline.by\etalon\cyclic.ps1"

param(
    [string]$List        = 'F:\ML\minskline.by\etalon\state\documents_filtered.csv',
    [int]$Workers        = 2,
    [int]$DelayMs        = 2000,
    [int]$CooldownMin    = 45,
    [int]$MaxIdleWaves   = 4
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Base   = 'https://etalonline.by'
$OutDir = 'F:\МЗ'
$Log    = 'F:\ML\minskline.by\etalon\logs\cyclic.log'
New-Item -ItemType Directory -Force -Path (Split-Path $Log) | Out-Null

function Say($m) { Add-Content $Log "$(Get-Date -Format s) $m" }
function DocCount { @(Get-ChildItem $OutDir -Filter *.doc -ErrorAction SilentlyContinue).Count }

Say "=== ЦИКЛИЧЕСКАЯ ЗАГРУЗКА === Список: $List"
Say "Потоков: $Workers, базовая пауза: ${DelayMs}мс, пауза между волнами: $CooldownMin мин"

$wave = 0
$idle = 0

while ($true) {
    $wave++
    $need = (Import-Csv $List).Count
    $have = DocCount
    Say "--- ВОЛНА $wave --- | всего .doc: $have"
    if ($have -ge $need) { Say "Все документы на месте. Завершаю."; break }
    if ($idle -ge $MaxIdleWaves) { Say "Волн без прогресса: $idle — останавливаюсь."; break }

    # --- предпроверка доступности: если сайт заблокировал, не дёргаем его ---
    $up = $false
    try {
        $req = [System.Net.HttpWebRequest]::Create("$Base/")
        $req.UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
        $req.Timeout = 20000
        $resp = $req.GetResponse()
        $up = ([int]$resp.StatusCode -eq 200)
        $resp.Close()
    } catch { Say "Сайт недоступен: $($_.Exception.Message)" }

    if (-not $up) {
        $idle++
        Say "Блокировка активна. Жду $CooldownMin мин (волна без прогресса $idle/$MaxIdleWaves)."
        Start-Sleep -Seconds ($CooldownMin * 60)
        continue
    }

    # --- авторизация ---
    $okAuth = $false
    try {
        . 'F:\ML\minskline.by\etalon\common.ps1'
        $s = Connect-Account
        Save-Session -Session $s
        $okAuth = $true
        Say "Авторизация: OK"
    } catch { Say "Ошибка авторизации: $($_.Exception.Message)" }

    if (-not $okAuth) {
        $idle++
        Say "Нет доступа. Жду $CooldownMin мин."
        Start-Sleep -Seconds ($CooldownMin * 60)
        continue
    }

    # --- сама загрузка ---
    $before = DocCount
    & 'F:\ML\minskline.by\etalon\download.ps1' -ListFile $List -Workers $Workers -DelayMs $DelayMs
    $after = DocCount
    $gained = $after - $before

    Say "Итог волны: +$gained документов (было $before, стало $after)"
    if ($gained -gt 0) { $idle = 0 } else { $idle++ }

    if ($gained -gt 0 -and $after -lt $need) {
        Say "Есть прогресс. Короткая пауза 5 мин перед следующей волной."
        Start-Sleep -Seconds 300
    } elseif ($gained -le 0) {
        Say "Прогресса нет. Жду $CooldownMin мин."
        Start-Sleep -Seconds ($CooldownMin * 60)
    }
}

Say "=== ЗАВЕРШЕНО === | .doc в папке: $(DocCount)"
& 'F:\ML\minskline.by\etalon\summary.ps1'