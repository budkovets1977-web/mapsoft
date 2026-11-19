@echo off
chcp 866 >nul
setlocal
echo.

set APACHE=apache
set PHP=php
set CRT=crt
set BIN=bin
set CONF=bin\%APACHE%\conf
set APACHE_BIN=bin\%APACHE%\bin
set PHP_BIN=bin\%APACHE%\bin
set OPENSSL_BIN=%APACHE_BIN%\openssl
set OPENSSL_CONF=%CONF%\openssl.cnf
set REDIS=bin\redis
set ASSIST=bin\tools\webassist
if defined DomainName set ServerName=%DomainName%

if exist %CD%\setup.bat goto badpath
if not exist %CD%\install.bat goto badpath
if not exist %CD%\bin\nul goto badpath

if "%1"=="install" goto run
if "%1"=="start" goto run
if "%1"=="restart" goto run
if "%1"=="stop" goto run
if "%1"=="uninstall" goto run
if "%1"=="sslkey" goto run
if "%1"=="rsakey" goto run
goto help

:run

rem подготавливаем каталог с сертификатами, с копированием из старого места
if exist %CRT%\nul goto skipcrt
md %CRT%
copy %CONF%\mapsoftCA.crt %CRT%\ >nul 2>nul
move %CONF%\server.crt %CRT%\ >nul 2>nul
move %CONF%\server.key %CRT%\ >nul 2>nul
move bin\tools\auth\*.mky %CRT%\ >nul 2>nul
move bin\tools\auth\*.mrq %CRT%\ >nul 2>nul
move bin\tools\auth\auth.log log\server.log >nul 2>nul
del /Q %CONF%\mapsoftCA.srl %CONF%\server.csr %CONF%\v3.ext >nul 2>nul

:skipcrt

rem копируем новые, несуществующие, шаблоны настроек из оригинального каталога в рабочий
if not exist cfg\ini.bat set NOCFG=1
call :md cfg
echo n|copy /-y src\service\cfg\* cfg\ >nul 2>nul
copy /Y src\service\cfg\apps.txt cfg\  >nul 2>nul
%PHP_BIN%\php.exe -f src\service\util\setup.php dummy_config createFrontEndAppsJson src\desktop\apps.json
copy /Y version src\desktop\version.json >nul 2>nul
if exist src\desktop\build\production\nul (
    copy /Y src\desktop\apps.json src\desktop\build\production\ >nul 2>nul
    copy /Y src\desktop\standalone.json src\desktop\build\production\ >nul 2>nul
    copy /Y src\desktop\cordova.js src\desktop\build\production\ >nul 2>nul
    copy /Y version src\desktop\build\production\version.json >nul 2>nul
)
if exist src\desktop\build\testing\nul (
    copy /Y src\desktop\apps.json src\desktop\build\testing\ >nul 2>nul
    copy /Y src\desktop\standalone.json src\desktop\build\testing\ >nul 2>nul
    copy /Y src\desktop\cordova.js src\desktop\build\testing\ >nul 2>nul
    copy /Y version src\desktop\build\testing\version.json >nul 2>nul
)

rem создание обязательных каталогов
call :md log\%APACHE%
call :md log\%PHP%
call :md video
call :md video\equeue

if defined NOCFG goto nocfg

call cfg\ini.bat setup

if "%1"=="install" goto inst
if "%1"=="start" goto start
if "%1"=="restart" goto restart
if "%1"=="stop" goto stop
if "%1"=="uninstall" goto uninst
if "%1"=="sslkey" goto sslkey
if "%1"=="rsakey" goto rsakey

goto end

:inst
echo Регистрация и запуск службы веб-сервера...
echo.

call %REDIS%\install.bat
call %ASSIST%\install.bat
set ServerRoot=-C "Define ServerRoot %CD:\=/%"
%PHP_BIN%\php.exe -f src\service\util\setup.php createHttpdConf
%APACHE_BIN%\httpd.exe -k install -n %ServiceName% %ServerRoot%
%APACHE_BIN%\httpd.exe -k start -n %ServiceName%
goto end

:start
echo Запуск службы веб-сервера...
echo.
%PHP_BIN%\php.exe -f src\service\util\setup.php createHttpdConf
%APACHE_BIN%\httpd.exe -k start -n %ServiceName%
goto end

:restart
echo Перезапуск службы веб-сервера...
echo.
%PHP_BIN%\php.exe -f src\service\util\setup.php createHttpdConf
%APACHE_BIN%\httpd.exe -k stop -n %ServiceName%
call %ASSIST%\restart.bat
%APACHE_BIN%\httpd.exe -k start -n %ServiceName%
goto end

:stop
echo Остановка службы веб-сервера...
echo.
%APACHE_BIN%\httpd.exe -k stop -n %ServiceName%
goto end

:uninst
echo Остановка и удаление службы веб-сервера...
echo.

%APACHE_BIN%\httpd.exe -k stop -n %ServiceName%
%APACHE_BIN%\httpd.exe -k uninstall -n %ServiceName%
call %ASSIST%\uninstall.bat
call %REDIS%\uninstall.bat
goto end

:rsakey
set outfile=crt
echo Создание приватного ключа...
%OPENSSL_BIN% genrsa -out %outfile%\private.pem 512
echo Создание публичного ключа...
%OPENSSL_BIN% rsa -in %outfile%\private.pem -out %outfile%\public.pem -pubout
echo Done
goto end

:sslkey
echo Создание ключа и SSL-сертификата...
echo.

SET "certOrg=%~2"
IF "%certOrg%" == "" SET certOrg=ACME

SET "certOrgUnit=%~3"
IF "%certOrgUnit%" == "" SET certOrgUnit=IT

SET "certEmail=%~4"
IF "%certEmail%" == "" SET certEmail=it@acme.com

IF defined DomainName (
    SET "certCN=%DomainName%"
) ELSE (
    SET "certCN=%ServerName%"
)

if exist %CONF%\mapsoftCA.crt goto skipca
%OPENSSL_BIN% genrsa -out %CONF%\mapsoftCA.key 2048
%OPENSSL_BIN% req -x509 -new -key %CONF%\mapsoftCA.key -days 3650 -sha256 -out %CONF%\mapsoftCA.crt -subj "/C=BY/ST=Belarus/L=Minsk/O=%certOrg%/CN=%certCN%/OU=%certOrgUnit%/emailAddress=%certEmail%"

:skipca
echo authorityKeyIdentifier=keyid,issuer > %CRT%\v3.ext
echo basicConstraints=CA:FALSE >> %CRT%\v3.ext
echo keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment >> %CRT%\v3.ext
echo subjectAltName = @alt_names >> %CRT%\v3.ext
echo [alt_names] >> %CRT%\v3.ext
if not defined DomainName echo IP.1 = %ServerName% >> %CRT%\v3.ext
if not defined DomainName echo DNS.1 = %ServerName% >> %CRT%\v3.ext
if defined DomainName echo DNS.1 = %DomainName% >> %CRT%\v3.ext
%OPENSSL_BIN% genrsa -out %CRT%\server.key 2048
%OPENSSL_BIN% req -new -key %CRT%\server.key -out %CRT%\server.csr -subj "/C=BY/ST=Belarus/L=Minsk/O=%certOrg%/CN=%certCN%/OU=%certOrgUnit%/emailAddress=%certEmail%"
%OPENSSL_BIN% x509 -req -in %CRT%\server.csr -CA %CONF%\mapsoftCA.crt -CAkey %CONF%\mapsoftCA.key -CAcreateserial -out %CRT%\server.crt -days 3650 -sha256 -extfile %CRT%\v3.ext
copy /Y %CONF%\mapsoftCA.crt %CRT%\>nul
del /Q %CONF%\mapsoftCA.srl %CRT%\server.csr %CRT%\v3.ext>nul

echo.
echo Корневой сертификат mapsoftCA.crt создан в каталоге %CRT%\

goto end

:nocfg
echo Перед запуском управляющих bat-файлов необходимо выполнить настройки:
echo   - Веб-сервера в файле cfg\ini.bat
echo   - Подключений к базам данных в файле cfg\config.json
echo   - Настройки требуемых приложений в файлах cfg\settings_*.json

goto end

:badpath
echo Файл setup.bat необходимо запускать из корневого каталога веб-сервера!

:help
echo Использование: bin\setup.bat [install^|start^|restart^|stop^|uninstall^|sslkey]
goto end

rem функция создания каталога с проверкой на существование
:md
if not exist %1\nul md %1
exit /b

:end
endlocal
echo.
pause