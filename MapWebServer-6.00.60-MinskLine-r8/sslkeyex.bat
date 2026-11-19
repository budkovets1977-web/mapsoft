@echo off

:getOrg
set /p org="Organization Name: "
if "%org%" == "" goto getOrg

:getOrgUnit
set /p orgUnit="Organization Unit Name: "
if "%orgUnit%" == "" goto getOrgUnit

:getEmail
set /p email="E-mail: "
if "%email%" == "" goto getEmail

call bin\setup.bat sslkey "%org%" "%orgUnit%" "%email%"