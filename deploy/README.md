# deploy/

Deployment packages for MapWebServer 6.00.60 (MinskLine).

- mapsoft-full-6.00.60-db10.01.26-theme2026-r7.7z.001/.002 + extract-r7.cmd
  - full server deployment package r7 (unpack 7z volumes, then extract-r7.cmd).
- workstation-fixes-v2026-11-16/
  - 02-WORKSTATION-fixes-v2026-11-16.zip - client-side fixes (UI themes x3, CertOkWatcher v2 with CertName preselect, ResetCiszCert/Restart-CiszAgent/Diag-CiszCert utilities).
  - hashes-WORKSTATION.txt - SHA256 checksums of all files inside the zip.
- server-fixes-v2026-11-19/
  - 01-SERVER-fixes-v2026-11-19.zip - server-side CISZ fixes, including the Encounter.actualPeriod FHIR per-1 fix.
  - hashes-SERVER.txt - SHA256 checksums of all files inside the zip.
