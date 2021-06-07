@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../wemersonjanuario/wkhtmltopdf-windows/bin/32bit/wkhtmltopdf.exe
call "%BIN_TARGET%" %*
