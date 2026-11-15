@echo off
chcp 65001 >nul
:: Склеивает многотомный 7z-архив в один zip и распаковвает его рядом.
:: Если 7-Zip установлен — использует его, иначе нужно собрать тома вручную.
set "BASE=mapsoft-full-6.00.60-db10.01.26-theme2026-r7"
set "SEVENZ=C:\Program Files\7-Zip\7z.exe"
if not exist "%SEVENZ%" set "SEVENZ=C:\Program Files (x86)\7-Zip\7z.exe"
if not exist "%SEVENZ%" (
    echo 7-Zip не найден. Скачайте https://www.7-zip.org/ и запустите:
    echo   "%BASE%.7z.001" —^> извлечь здесь
    pause
    exit /b 1
)
"%SEVENZ%" x "%BASE%.7z.001" -o"%CD%" -y
if %errorlevel% neq 0 (
    echo Ошибка распаковки архива. Убедитесь, что все тома .7z.00x находятся в этой папке.
    pause
    exit /b %errorlevel%
)
echo Готово. Рядом появился файл: %BASE%.zip
echo Распакуйте его в папку установки MapWebServer.
pause
