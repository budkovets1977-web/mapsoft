@echo off
chcp 65001 >nul
rem ============================================================
rem CertOkWatcher - установка авто-подтверждения сертификата ЭЦП
rem Запускать ОТ ТОГО ПОЛЬЗОВАТЕЛЯ, который работает с МИС/ЭЦП.
rem Не требует прав администратора.
rem ============================================================
set DEST=c:\MAP\Avest

if not exist "%DEST%" mkdir "%DEST%"
copy /y "%~dp0CertOkWatcher.ps1" "%DEST%\CertOkWatcher.ps1" >nul
if errorlevel 1 (echo ОШИБКА копирования в %DEST% & pause & exit /b 1)

rem автозагрузка текущего пользователя
reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Run" /v CertOkWatcher /t REG_SZ /d "powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File %DEST%\CertOkWatcher.ps1" /f >nul

rem включить встроенную опцию NTClientTool (нажимает ОК, если сертификат один)
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=Join-Path $env:APPDATA 'Avest\saved.txt'; if ((Test-Path $p) -and -not (Select-String -Path $p -Pattern 'skip_cert_window' -Quiet)) { Add-Content -Path $p -Value 'skip_cert_window:true' -Encoding Default }"

rem остановить старый экземпляр watcher'а и запустить новый
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process -Filter \"Name='powershell.exe'\" | Where-Object { $_.CommandLine -like '*CertOkWatcher*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
start "" powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%DEST%\CertOkWatcher.ps1"

echo.
echo Установлено: %DEST%\CertOkWatcher.ps1
echo Первое окно сертификата подтверждается вручную, остальные - автоматически.
echo Лог: %DEST%\CertOkWatcher.log
pause
