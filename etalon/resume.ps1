# Возобновление загрузки документов Минздрава РБ.
# Запуск:  powershell -NoProfile -ExecutionPolicy Bypass -File "F:\ML\minskline.by\etalon\resume.ps1"
#
# Скрипт:
#   1) проверяет доступность сайта (если недоступен — ничего не дёргает, выходит);
#   2) переавторизуется;
#   3) скачивает только недостающие документы (уже скачанные пропускаются).

param([int]$Workers = 2, [int]$DelayMs = 1500)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Base = 'https://etalonline.by'
$Log  = 'F:\ML\minskline.by\etalon\logs\resume.log'
New-Item -ItemType Directory -Force -Path (Split-Path $Log) | Out-Null

function Say($m) { $line = "$(Get-Date -Format s) $m"; Add-Content $Log $line }

Say "=== Запуск возобновления ==="

# --- 1. проверка доступности ---
$ok = $false
try {
    $req = [System.Net.HttpWebRequest]::Create("$Base/")
    $req.UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
    $req.Timeout = 25000
    $resp = $req.GetResponse()
    $code = [int]$resp.StatusCode
    $resp.Close()
    $ok = ($code -eq 200)
    Say "Проверка доступности: HTTP $code"
} catch {
    Say "Сайт недоступен: $($_.Exception.Message)"
    Say "Выход. Повторите позже (например, через несколько часов)."
    Write-Host "Сайт etalononline.by недоступен. Повторите позже." -ForegroundColor Yellow
    exit 1
}
if (-not $ok) { Say "Сайт ответил не 200 — выход."; exit 1 }

# --- 2. переавторизация ---
try {
    . 'F:\ML\minskline.by\etalon\common.ps1'
    $s = Connect-Account
    Save-Session -Session $s
    Say "Авторизация: OK"
} catch {
    Say "Ошибка авторизации: $($_.Exception.Message)"
    Write-Host "Не удалось войти: $($_.Exception.Message)" -ForegroundColor Red
    exit 2
}

Say "Запуск загрузчика (потоков: $Workers, пауза: ${DelayMs}мс)"

# --- 3. загрузка ---
& 'F:\ML\minskline.by\etalon\download.ps1' -Workers $Workers -DelayMs $DelayMs

Say "=== Завершено ==="
& 'F:\ML\minskline.by\etalon\summary.ps1'
Say "Сводка обновлена: F:\ML\minskline.by\etalon\logs\summary.txt"