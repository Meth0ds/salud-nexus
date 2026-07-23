[CmdletBinding()]
param(
    [switch]$IncludeE2E,
    [switch]$DockerBuildCheck,
    [string]$ComposerPath = 'composer'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$frontendRoot = Join-Path $workspaceRoot 'frontend'
$backendRoot = Join-Path $workspaceRoot 'backend'

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Invoke-Checked(
    [string]$Executable,
    [string[]]$Arguments,
    [string]$WorkingDirectory
) {
    Push-Location $WorkingDirectory
    try {
        & $Executable @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "Fallo ($LASTEXITCODE): $Executable $($Arguments -join ' ')"
        }
    }
    finally {
        Pop-Location
    }
}

Write-Step 'Validacion de infraestructura'
& (Join-Path $PSScriptRoot 'Test-Infrastructure.ps1') -DockerBuildCheck:$DockerBuildCheck

$npm = Get-Command npm.cmd -ErrorAction SilentlyContinue
if ($null -eq $npm) {
    $npm = Get-Command npm -ErrorAction Stop
}

Write-Step 'Frontend: formato, lint, pruebas unitarias, build y auditoria'
Invoke-Checked $npm.Source @('run', 'format:check') $frontendRoot
Invoke-Checked $npm.Source @('run', 'lint') $frontendRoot
Invoke-Checked $npm.Source @('run', 'test:unit') $frontendRoot
Invoke-Checked $npm.Source @('run', 'build') $frontendRoot
Invoke-Checked $npm.Source @('audit', '--audit-level=high') $frontendRoot
Invoke-Checked $npm.Source @('audit', 'signatures') $frontendRoot

if ($IncludeE2E) {
    Write-Step 'Frontend: pruebas E2E con Playwright'
    Invoke-Checked $npm.Source @('run', 'test:e2e') $frontendRoot
}

$composer = Get-Command $ComposerPath -ErrorAction SilentlyContinue
if ($null -eq $composer) {
    throw "No se encontro Composer. Usa -ComposerPath con la ruta a composer o composer.cmd."
}

Write-Step 'Backend: formato, analisis estatico, pruebas y auditoria'
Invoke-Checked $composer.Source @('run', 'verify') $backendRoot

Write-Step 'Integridad del diff'
$git = Get-Command git -ErrorAction Stop
Invoke-Checked $git.Source @('diff', '--check') $workspaceRoot

Write-Host "`nVerificacion global completada correctamente." -ForegroundColor Green
