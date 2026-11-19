@echo off
REM install.bat — установка хелпера SetCiszCert на клиентскую машину
REM Копирует скрипт в C:\MAP\Avest\ и добавляет в автозагрузку.

setlocal

set SCRIPT_DIR=%~dp0
set DEST=C:\MAP\Avest

if not exist "%DEST%" mkdir "%DEST%"

echo Копирование SetCiszCert.ps1 в %DEST% ...
copy /Y "%SCRIPT_DIR%SetCiszCert.ps1" "%DEST%\SetCiszCert.ps1"

echo Добавление в автозагрузку...
regedit /s "%SCRIPT_DIR%autostart.reg"

echo.
echo Готово. Хелпер будет запускаться при входе пользователя и
echo записывать friendly name активного сертификата в cert= секции [sigma]
echo файла C:\NTClientSoftware\config\NTClientSoftware.ini.
echo.
echo Для немедленного применения запустите вручную:
echo   powershell -NoProfile -ExecutionPolicy Bypass -File C:\MAP\Avest\SetCiszCert.ps1
echo.
pause
