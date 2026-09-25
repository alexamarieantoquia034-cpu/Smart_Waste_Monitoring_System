# ============================================================================
# reset_postgres_password.ps1
# Smart Waste Monitoring System - Reset the PostgreSQL "postgres" password
# ============================================================================
# WHY: pgAdmin fails with "password authentication failed for user postgres".
#      This script safely resets that password using the standard recovery
#      method (temporarily trust local connections -> set password -> restore).
#
# HOW TO RUN (one time):
#   1. Click Start -> type "PowerShell"
#   2. Right-click "Windows PowerShell" -> "Run as administrator"
#   3. Run:
#        powershell -ExecutionPolicy Bypass -File "C:\xampp\htdocs\Smart_Waste_Monitoring_System\database\sql\postgresql\reset_postgres_password.ps1"
#      (Add  -NewPassword "yourpass"  to choose a different password.)
#
# DEFAULT new password: postgres   (matches the value in your Laravel .env)
# ============================================================================

param([string]$NewPassword = "postgres")

$ErrorActionPreference = 'Stop'

$pgBin  = 'C:\Program Files\PostgreSQL\18\bin'
$data   = 'C:\Program Files\PostgreSQL\18\data'
$hba    = Join-Path $data 'pg_hba.conf'
$backup = "$hba.bak"

if (-not (Test-Path $hba)) {
    Write-Error "pg_hba.conf not found at $hba"
    exit 1
}

# 1) Back up the auth config
Copy-Item $hba $backup -Force
Write-Host "[1/6] Backed up pg_hba.conf -> $backup" -ForegroundColor Cyan

# 2) Allow passwordless local connections (IPv4 + IPv6 localhost only)
(Get-Content $hba) |
    ForEach-Object {
        $_ -replace '^(host\s+all\s+all\s+127\.0\.0\.1/32\s+)scram-sha-256', '$1trust' `
           -replace '^(host\s+all\s+all\s+::1/128\s+)scram-sha-256', '$1trust'
    } |
    Set-Content $hba -Encoding ascii
Write-Host "[2/6] Enabled temporary 'trust' auth for localhost" -ForegroundColor Cyan

# 3) Reload the server config
& "$pgBin\pg_ctl.exe" -D $data reload
if ($LASTEXITCODE -ne 0) {
    Restart-Service 'postgresql-x64-18'
}
Write-Host "[3/6] Server config reloaded (passwordless connections now allowed)" -ForegroundColor Cyan

# 4) Set the new password while trust auth is active
$escaped = $NewPassword -replace "'", "''"
& "$pgBin\psql.exe" -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER USER postgres WITH PASSWORD '$escaped';"
if ($LASTEXITCODE -ne 0) {
    Write-Error "ALTER USER failed - aborting before restoring config"
    exit 1
}
Write-Host "[4/6] New password set for user 'postgres'" -ForegroundColor Cyan

# 5) Restore the original secure auth (scram-sha-256)
Copy-Item $backup $hba -Force
& "$pgBin\pg_ctl.exe" -D $data reload
if ($LASTEXITCODE -ne 0) {
    Restart-Service 'postgresql-x64-18'
}
Write-Host "[5/6] Original 'scram-sha-256' auth restored" -ForegroundColor Cyan

# 6) Verify login with the new password
$env:PGPASSWORD = $NewPassword
$out = & "$pgBin\psql.exe" -h 127.0.0.1 -U postgres -d postgres -t -A -c 'SELECT 1;' 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "[6/6] SUCCESS! Password is now: $NewPassword" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:"
    Write-Host "  1. In pgAdmin, right-click 'PostgreSQL 18' -> Properties -> Connection"
    Write-Host "     and type the password above. Then open the Query Tool and run"
    Write-Host "     01_create_tables.sql then 02_seed_data.sql (in database/sql/postgresql)."
    Write-Host "  2. Or from this terminal run the Laravel setup:"
    Write-Host "     cd C:\xampp\htdocs\Smart_Waste_Monitoring_System"
    Write-Host "     php artisan migrate:fresh --seed --force"
} else {
    Write-Host "[6/6] Login verification FAILED: $out" -ForegroundColor Red
}