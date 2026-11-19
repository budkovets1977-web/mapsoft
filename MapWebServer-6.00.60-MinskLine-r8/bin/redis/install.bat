set EXE="%~dp0redis-server.exe"
set CFG="%~dp0redis.windows-service.conf"

echo Starting Redis...
sc.exe create "Map Redis" binPath= "\"%EXE%\" --service-run \"%CFG%\"" start= auto
sc.exe start "Map Redis"
echo.
