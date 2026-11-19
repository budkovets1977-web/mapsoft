# SetCiszCert.ps1  (ASCII-only source, safe for PowerShell 5.1)
# Writes the friendly name of the active signing certificate (has private key)
# into [sigma] cert= of NTClientSoftware.ini (encoded Windows-1251).
#
# Usage:
#   powershell -NoProfile -ExecutionPolicy Bypass -File SetCiszCert.ps1            # auto-detect name
#   powershell -NoProfile -ExecutionPolicy Bypass -File SetCiszCert.ps1 -Show      # only show
#   powershell -NoProfile -ExecutionPolicy Bypass -File SetCiszCert.ps1 -Restart   # also restart agent
#   powershell -NoProfile -ExecutionPolicy Bypass -File SetCiszCert.ps1 -Name "..." # manual name

param(
    [switch]$Restart,
    [switch]$Show,
    [string]$Name = ''
)

$ErrorActionPreference = 'Stop'

$iniPath = 'C:\NTClientSoftware\config\NTClientSoftware.ini'
$exePath = 'C:\NTClientSoftware\NTClientSoftware.exe'

# ---- 1. Resolve friendly name ----
$friendlyName = ''
if ($Name -ne '') {
    $friendlyName = $Name
} else {
    $certs = @(Get-ChildItem Cert:\CurrentUser\My -ErrorAction SilentlyContinue | Where-Object { $_.HasPrivateKey })
    if ($certs.Count -eq 0) {
        Write-Error 'No certificate with private key in CurrentUser\My.'
        exit 2
    }
    $certs = $certs | Sort-Object -Property NotAfter -Descending
    $cert = $certs[0]
    $friendlyName = $cert.FriendlyName
    if ([string]::IsNullOrWhiteSpace($friendlyName)) {
        if ($cert.Subject -match 'CN=([^,]+)') { $friendlyName = $Matches[1] }
    }
    Write-Output ("Active cert: [" + $friendlyName + "] SN=" + $cert.SerialNumber)
}

if ([string]::IsNullOrWhiteSpace($friendlyName)) {
    Write-Error 'Cannot determine friendly name.'
    exit 3
}

if ($Show) { exit 0 }

# ---- 2. Read ini as Windows-1251 (cp1251) ----
if (-not (Test-Path $iniPath)) {
    Write-Error "INI not found: $iniPath"
    exit 4
}

$enc = [System.Text.Encoding]::GetEncoding(1251)
$raw = [System.IO.File]::ReadAllBytes($iniPath)
$text = $enc.GetString($raw)

# ---- 3. Replace ONLY the cert= line inside [sigma] section ----
# Work on byte-level semantics via string; keep CRLF.
$nl = "`r`n"
$lines = $text -split "`r`n", -1
$inSigma = $false
$found = $false
for ($i = 0; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]
    if ($line -match '^\s*\[') {
        $inSigma = ($line -match '^\s*\[sigma\]\s*$')
        continue
    }
    if ($inSigma -and $line -match '^\s*cert=') {
        $lines[$i] = 'cert=' + $friendlyName
        $found = $true
    }
}

if (-not $found) {
    Write-Warning 'cert= line not found in [sigma].'
}

$newText = $lines -join $nl
$outBytes = $enc.GetBytes($newText)
[System.IO.File]::WriteAllBytes($iniPath, $outBytes)
Write-Output ("cert written: " + $friendlyName)

# ---- 4. Optionally restart NTClientSoftware ----
if ($Restart) {
    $proc = Get-Process -Name 'NTClientSoftware' -ErrorAction SilentlyContinue
    if ($proc) {
        Write-Output ("Restarting NTClientSoftware (PID " + $proc.Id + ") ...")
        Stop-Process -Id $proc.Id -Force
        Start-Sleep -Seconds 2
    }
    if (Test-Path $exePath) {
        Start-Process -FilePath $exePath
        Write-Output 'NTClientSoftware started.'
    }
}
Write-Output 'Done.'
