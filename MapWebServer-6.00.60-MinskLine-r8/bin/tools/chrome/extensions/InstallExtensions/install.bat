
set HOST=http://localhost:8080

rem Close all Google Chrome process
TASKKILL /F /IM chrome.exe

rem Update Google Chrome policy
regedit.exe -s "%~dp0PolicyUpdate\regExtension.reg"

rem Install Chrome Extensions
c:
cd "c:\Program Files (x86)\Google\Chrome\Application\"
chrome.exe "%HOST%/extensions/MapsoftTools.crx" "%HOST%/extensions/MapsoftToolsApp.crx"