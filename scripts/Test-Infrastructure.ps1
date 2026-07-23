[CmdletBinding()]
param(
    [switch]$DockerBuildCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$composePath = Join-Path $workspaceRoot 'infrastructure\compose.yaml'
$dockerfiles = @(
    (Join-Path $workspaceRoot 'infrastructure\docker\backend\Dockerfile'),
    (Join-Path $workspaceRoot 'infrastructure\docker\api-proxy\Dockerfile'),
    (Join-Path $workspaceRoot 'infrastructure\docker\frontend\Dockerfile')
)

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

Write-Step 'Comprobando archivos y politicas estaticas de contenedores'

$requiredFiles = @($composePath) + $dockerfiles + @(
    (Join-Path $workspaceRoot 'infrastructure\.env.example'),
    (Join-Path $workspaceRoot 'infrastructure\docker\backend\entrypoint.sh'),
    (Join-Path $workspaceRoot 'infrastructure\docker\api-proxy\api.conf.template'),
    (Join-Path $workspaceRoot 'infrastructure\docker\frontend\frontend.conf.template')
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path -LiteralPath $file -PathType Leaf)) {
        throw "Falta el archivo obligatorio: $file"
    }
}

$infrastructureText = ($requiredFiles | ForEach-Object { Get-Content -Raw -LiteralPath $_ }) -join "`n"

if ($infrastructureText -match '(?im)^\s*(?:FROM|image:)\s+\S*:latest(?:\s|$)') {
    throw 'No se permiten imagenes con la etiqueta latest.'
}

if ($infrastructureText -match '(?i)(?:sk|pk)_(?:live|test)_[A-Za-z0-9]{16,}') {
    throw 'Se detecto un patron con apariencia de credencial en infraestructura.'
}

foreach ($dockerfile in $dockerfiles) {
    $content = Get-Content -Raw -LiteralPath $dockerfile

    if ($content -notmatch '(?m)^USER\s+') {
        throw "$dockerfile debe declarar un usuario no root."
    }

    if ($content -notmatch '(?m)^HEALTHCHECK\s+') {
        throw "$dockerfile debe declarar HEALTHCHECK."
    }

    if ($content -notmatch '@sha256:[a-f0-9]{64}') {
        throw "$dockerfile debe fijar la imagen base por digest."
    }
}

$python = Get-Command python -ErrorAction SilentlyContinue

if ($null -ne $python) {
    & $python.Source (Join-Path $PSScriptRoot 'validate_infrastructure.py') $composePath
    if ($LASTEXITCODE -ne 0) {
        throw 'La validacion estructural de Compose ha fallado.'
    }
}
else {
    Write-Warning 'Python no esta disponible; Docker Compose realizara la validacion autoritativa cuando se instale.'
}

$docker = Get-Command docker -ErrorAction SilentlyContinue

if ($null -eq $docker) {
    Write-Warning 'Docker no esta instalado: no se ejecutan docker compose config ni buildx --check.'
    Write-Host 'Validacion estatica completada.' -ForegroundColor Green
    exit 0
}

Write-Step 'Validando la configuracion con Docker Compose'

$secretNames = @('APP_KEY', 'AUDIT_INTEGRITY_KEY', 'POSTGRES_PASSWORD', 'REDIS_PASSWORD')
$previousValues = @{}

try {
    foreach ($name in $secretNames) {
        $previousValues[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
    }

    $env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
    $env:AUDIT_INTEGRITY_KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
    $env:POSTGRES_PASSWORD = 'ci-only-compose-validation-postgres'
    $env:REDIS_PASSWORD = 'ci-only-compose-validation-redis'

    & $docker.Source compose --file $composePath config --quiet
    if ($LASTEXITCODE -ne 0) {
        throw 'docker compose config ha fallado.'
    }

    if ($DockerBuildCheck) {
        Write-Step 'Ejecutando las comprobaciones estaticas de BuildKit'

        $checks = @(
            @('--file', 'infrastructure/docker/backend/Dockerfile', '--check', '.'),
            @('--file', 'infrastructure/docker/api-proxy/Dockerfile', '--check', '.'),
            @('--file', 'infrastructure/docker/frontend/Dockerfile', '--build-arg', 'APP_NAME=patient-portal', '--check', '.')
        )

        Push-Location $workspaceRoot
        try {
            foreach ($arguments in $checks) {
                & $docker.Source buildx build @arguments
                if ($LASTEXITCODE -ne 0) {
                    throw "docker buildx build --check ha fallado para: $($arguments -join ' ')"
                }
            }
        }
        finally {
            Pop-Location
        }
    }
}
finally {
    foreach ($name in $secretNames) {
        [Environment]::SetEnvironmentVariable($name, $previousValues[$name], 'Process')
    }
}

Write-Host 'Infraestructura validada correctamente.' -ForegroundColor Green
