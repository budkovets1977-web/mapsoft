# GetCertName.ps1
# Возвращает friendly name активного сертификата ЭЦП (с приватным ключом).
# Вывод в UTF-8, чтобы вызывающий PHP корректно прочитал кириллицу.

$ErrorActionPreference = 'SilentlyContinue'

$certs = @(Get-ChildItem Cert:\CurrentUser\My | Where-Object { $_.HasPrivateKey })
if ($certs.Count -eq 0) {
    exit 1
}

$certs = $certs | Sort-Object -Property NotAfter -Descending
$cert = $certs[0]

$fn = $cert.FriendlyName
if ([string]::IsNullOrWhiteSpace($fn)) {
    if ($cert.Subject -match 'CN=([^,]+)') {
        $fn = $Matches[1]
    }
}

# Вывести в UTF-8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
Write-Output $fn
