param(
    [string]$SupabaseHost = "<SUPABASE_HOST>",
    [int]$SupabasePort = 5432,
    [string]$SupabaseDb = "postgres",
    [string]$SupabaseUser = "<SUPABASE_USER>",
    [string]$SupabasePassword = "<SUPABASE_PASSWORD>",
    [string]$LocalHost = "localhost",
    [int]$LocalPort = 5432,
    [string]$LocalDb = "job_portal",
    [string]$LocalUser = "postgres",
    [string]$LocalPassword = "<LOCAL_POSTGRES_PASSWORD>",
    [string]$DumpFile = ".\\scripts\\supabase_sync.dump"
)

$ErrorActionPreference = "Stop"

function Require-Command {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' not found. Install PostgreSQL client tools and ensure '$Name' is in PATH."
    }
}

function Require-Value {
    param(
        [string]$Name,
        [string]$Value
    )
    if ([string]::IsNullOrWhiteSpace($Value) -or $Value.StartsWith("<")) {
        throw "Missing required value: $Name"
    }
}

Require-Command "pg_dump"
Require-Command "pg_restore"

Require-Value "SupabaseHost" $SupabaseHost
Require-Value "SupabasePassword" $SupabasePassword
Require-Value "LocalPassword" $LocalPassword

$dumpDir = Split-Path -Parent $DumpFile
if (-not [string]::IsNullOrWhiteSpace($dumpDir) -and -not (Test-Path $dumpDir)) {
    New-Item -ItemType Directory -Path $dumpDir -Force | Out-Null
}

Write-Host "[1/2] Dumping Supabase database to $DumpFile ..."
$env:PGPASSWORD = $SupabasePassword
pg_dump --host=$SupabaseHost --port=$SupabasePort --username=$SupabaseUser --dbname=$SupabaseDb --format=custom --file=$DumpFile --no-owner --no-privileges
if ($LASTEXITCODE -ne 0) {
    throw "pg_dump failed with exit code $LASTEXITCODE"
}

Write-Host "[2/2] Restoring snapshot into local database $LocalDb ..."
$env:PGPASSWORD = $LocalPassword
pg_restore --clean --if-exists --no-owner --no-privileges --host=$LocalHost --port=$LocalPort --username=$LocalUser --dbname=$LocalDb $DumpFile
if ($LASTEXITCODE -ne 0) {
    throw "pg_restore failed with exit code $LASTEXITCODE"
}

Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
Write-Host "Sync complete. Local database '$LocalDb' now mirrors Supabase snapshot."
