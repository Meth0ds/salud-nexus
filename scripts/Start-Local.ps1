[CmdletBinding()]
param(
    [switch]$IncludeDesignLab,
    [switch]$IncludeWorkers,
    [switch]$SkipBuild,
    [int]$HealthTimeoutSeconds = 180
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$infrastructureRoot = Join-Path $workspaceRoot 'infrastructure'
$composePath = Join-Path $infrastructureRoot 'compose.yaml'
$examplePath = Join-Path $infrastructureRoot '.env.example'
$environmentPath = Join-Path $infrastructureRoot '.env'

function New-RandomBase64([int]$ByteCount, [switch]$UrlSafe) {
    $bytes = New-Object byte[] $ByteCount
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }

    $value = [Convert]::ToBase64String($bytes)

    if ($UrlSafe) {
        return $value.TrimEnd('=').Replace('+', '-').Replace('/', '_')
    }

    return $value
}

function Get-EnvironmentOrDefault([string]$Name, [string]$Default) {
    $value = [Environment]::GetEnvironmentVariable($Name, 'Process')
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Default
    }

    return $value
}

function Set-DotEnvValue([string]$Path, [string]$Name, [string]$Value) {
    $lines = [System.Collections.Generic.List[string]]::new()
    $lines.AddRange([string[]](Get-Content -LiteralPath $Path))
    $updated = $false

    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match "^$([Regex]::Escape($Name))=") {
            $lines[$index] = "$Name=$Value"
            $updated = $true
            break
        }
    }

    if (-not $updated) {
        $lines.Add("$Name=$Value")
    }

    [IO.File]::WriteAllLines($Path, $lines, [Text.UTF8Encoding]::new($false))
}

function Import-DotEnv([string]$Path) {
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed.Length -eq 0 -or $trimmed.StartsWith('#')) {
            continue
        }

        $parts = $trimmed.Split('=', 2)
        if ($parts.Count -ne 2) {
            throw "Linea .env no valida: $trimmed"
        }

        [Environment]::SetEnvironmentVariable($parts[0], $parts[1], 'Process')
    }
}

$docker = Get-Command docker -ErrorAction SilentlyContinue
if ($null -eq $docker) {
    throw 'Docker no esta instalado o no esta disponible en PATH. Instala Docker Desktop/Engine con Compose v2 antes de arrancar el entorno.'
}

if (-not (Test-Path -LiteralPath $environmentPath)) {
    Copy-Item -LiteralPath $examplePath -Destination $environmentPath
    Write-Host 'Creado infrastructure/.env a partir de la plantilla.' -ForegroundColor Yellow
}

$currentEnvironment = Get-Content -Raw -LiteralPath $environmentPath

if ($currentEnvironment -match '(?m)^APP_KEY=\s*$') {
    Set-DotEnvValue $environmentPath APP_KEY "base64:$(New-RandomBase64 32)"
}

if ($currentEnvironment -match '(?m)^AUDIT_INTEGRITY_KEY=\s*$') {
    Set-DotEnvValue $environmentPath AUDIT_INTEGRITY_KEY (New-RandomBase64 32)
}

if ($currentEnvironment -match '(?m)^POSTGRES_PASSWORD=\s*$') {
    Set-DotEnvValue $environmentPath POSTGRES_PASSWORD (New-RandomBase64 36 -UrlSafe)
}

if ($currentEnvironment -match '(?m)^REDIS_PASSWORD=\s*$') {
    Set-DotEnvValue $environmentPath REDIS_PASSWORD (New-RandomBase64 36 -UrlSafe)
}

Import-DotEnv $environmentPath

foreach ($requiredSecret in @('APP_KEY', 'AUDIT_INTEGRITY_KEY', 'POSTGRES_PASSWORD', 'REDIS_PASSWORD')) {
    $value = [Environment]::GetEnvironmentVariable($requiredSecret, 'Process')
    if ([string]::IsNullOrWhiteSpace($value) -or $value.Length -lt 32) {
        throw "$requiredSecret debe contener un valor generado de al menos 32 caracteres."
    }
}

$composeArguments = @('compose', '--env-file', $environmentPath, '--file', $composePath)

if ($IncludeDesignLab) {
    $composeArguments += @('--profile', 'design')
}

if ($IncludeWorkers) {
    $composeArguments += @('--profile', 'workers')
}

$upArguments = $composeArguments + @('up', '--detach', '--wait')
if (-not $SkipBuild) {
    $upArguments += '--build'
}

Write-Host 'Arrancando Salud Nexus. Los secretos permanecen en infrastructure/.env y no se imprimen.' -ForegroundColor Cyan
& $docker.Source @upArguments
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose no pudo arrancar todos los servicios.'
}

Write-Host 'Aplicando migraciones compatibles de Laravel...' -ForegroundColor Cyan
& $docker.Source @composeArguments exec --no-TTY api php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    throw 'Las migraciones no se completaron. Los contenedores quedan activos para diagnostico; no se ha eliminado ningun volumen.'
}

& (Join-Path $PSScriptRoot 'Test-LocalHealth.ps1') `
    -IncludeDesignLab:$IncludeDesignLab `
    -TimeoutSeconds $HealthTimeoutSeconds

Write-Host "`nEntorno disponible:" -ForegroundColor Green
$patientPort = Get-EnvironmentOrDefault PATIENT_HOST_PORT '4200'
$staffPort = Get-EnvironmentOrDefault STAFF_HOST_PORT '4201'
$apiPort = Get-EnvironmentOrDefault API_HOST_PORT '8080'
$designPort = Get-EnvironmentOrDefault DESIGN_HOST_PORT '4300'
Write-Host "  Portal paciente: http://127.0.0.1:$patientPort"
Write-Host "  Portal profesional: http://127.0.0.1:$staffPort"
Write-Host "  API: http://127.0.0.1:$apiPort/api/v1"
if ($IncludeDesignLab) {
    Write-Host "  Design Lab: http://127.0.0.1:$designPort"
}
