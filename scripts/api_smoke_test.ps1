param(
  [string]$BaseUrl = "http://localhost:8000/backend"
)

$ErrorActionPreference = "Stop"

function Invoke-ApiJson {
  param(
    [string]$Method,
    [string]$Url,
    $Body,
    [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
    [hashtable]$ExtraHeaders
  )
  $headers = @{}
  if ($ExtraHeaders) { $headers = $ExtraHeaders }
  if ($Body -ne $null) {
    return Invoke-RestMethod -Method $Method -Uri $Url -WebSession $Session -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 5)
  }
  return Invoke-RestMethod -Method $Method -Uri $Url -WebSession $Session -Headers $headers
}

function Get-CsrfFromSessionCookie {
  param([Microsoft.PowerShell.Commands.WebRequestSession]$Session)
  $cookie = $Session.Cookies.GetCookies($BaseUrl) | Where-Object { $_.Name -eq "jp_csrf" } | Select-Object -First 1
  if (-not $cookie) { return "" }
  return $cookie.Value
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$rand = Get-Random -Minimum 1000 -Maximum 9999
$email = "smoke$rand@example.com"
$password = "Aa!$rand" + "xyZ9"

Write-Host "1) Registering test user: $email"
$registerPayload = @{
  email = $email
  password = $password
  full_name = "Smoke User"
  role = "job_seeker"
  phone = "9841123456"
}
$reg = Invoke-ApiJson -Method POST -Url "$BaseUrl/auth/register.php" -Body $registerPayload -Session $session
if (-not $reg.success) { throw "Register failed" }

Write-Host "2) Logging in"
$login = Invoke-ApiJson -Method POST -Url "$BaseUrl/auth/login.php" -Body @{ email = $email; password = $password } -Session $session
if (-not $login.success) { throw "Login failed" }

Write-Host "3) Fetching jobs list"
$jobs = Invoke-RestMethod -Method GET -Uri "$BaseUrl/jobs/fetch_jobs.php?page=1&limit=5" -WebSession $session
if (-not $jobs.success) { throw "Fetch jobs failed" }

Write-Host "4) Loading own profile"
$profile = Invoke-RestMethod -Method GET -Uri "$BaseUrl/users/profile.php" -WebSession $session
if (-not $profile.success) { throw "Profile load failed" }

Write-Host "5) Changing password"
$csrf = Get-CsrfFromSessionCookie -Session $session
if (-not $csrf) { throw "Missing CSRF token cookie" }
$headers = @{ "X-CSRF-Token" = $csrf }
$newPassword = "Bb@${rand}Qq7"
$changed = Invoke-ApiJson -Method PUT -Url "$BaseUrl/users/change_password.php" -Body @{ current_password = $password; new_password = $newPassword } -Session $session -ExtraHeaders $headers
if (-not $changed.success) { throw "Change password failed" }

Write-Host "6) Logging out"
$logout = Invoke-ApiJson -Method POST -Url "$BaseUrl/auth/logout.php" -Body $null -Session $session -ExtraHeaders $headers
if (-not $logout.success) { throw "Logout failed" }

Write-Host "Smoke test passed."
