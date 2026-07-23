[CmdletBinding()]
param(
    [switch]$IncludeDesignLab,
    [int]$TimeoutSeconds = 120
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-PortOrDefault([string]$Name, [int]$Default) {
    $value = [Environment]::GetEnvironmentVariable($Name, 'Process')
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Default
    }

    return [int]$value
}

$apiPort = Get-PortOrDefault API_HOST_PORT 8080
$patientPort = Get-PortOrDefault PATIENT_HOST_PORT 4200
$staffPort = Get-PortOrDefault STAFF_HOST_PORT 4201
$designPort = Get-PortOrDefault DESIGN_HOST_PORT 4300

$checks = [ordered]@{
    'API liveness' = "http://127.0.0.1:$apiPort/api/v1/health/live"
    'API readiness' = "http://127.0.0.1:$apiPort/api/v1/health/ready"
    'Portal paciente' = "http://127.0.0.1:$patientPort/healthz"
    'Portal profesional' = "http://127.0.0.1:$staffPort/healthz"
}

if ($IncludeDesignLab) {
    $checks['Design Lab'] = "http://127.0.0.1:$designPort/healthz"
}

$deadline = [DateTimeOffset]::UtcNow.AddSeconds($TimeoutSeconds)
$pending = [System.Collections.Generic.HashSet[string]]::new([string[]]$checks.Keys)

while ($pending.Count -gt 0 -and [DateTimeOffset]::UtcNow -lt $deadline) {
    foreach ($name in [string[]]$pending) {
        try {
            $response = Invoke-WebRequest -Uri $checks[$name] -Method Get -TimeoutSec 5 -UseBasicParsing
            if ($response.StatusCode -eq 200) {
                [void]$pending.Remove($name)
                Write-Host "[OK] $name" -ForegroundColor Green
            }
        }
        catch {
            # El servicio puede seguir dentro de su periodo de arranque.
        }
    }

    if ($pending.Count -gt 0) {
        Start-Sleep -Seconds 2
    }
}

if ($pending.Count -gt 0) {
    throw "No respondieron a tiempo: $([string]::Join(', ', $pending))"
}

Write-Host 'Todas las sondas locales responden correctamente.' -ForegroundColor Green
