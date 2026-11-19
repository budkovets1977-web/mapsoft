echo Stop Web Assist...

net stop MapWebAssist
"%~dp0webassist.exe" /uninstall /silent

echo.
