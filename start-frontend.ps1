$ErrorActionPreference = 'Stop'

$frontendPath = Join-Path $PSScriptRoot 'frontend'
$backendStart = Join-Path $PSScriptRoot 'start-local.ps1'
$backendStop = Join-Path $PSScriptRoot 'stop-local.ps1'

Write-Host 'Verificando backend PHP en http://127.0.0.1:8000/...'
$env:FRONTEND_REPLACES_PHP = '1'
$env:FRONTEND_URL = 'http://127.0.0.1:5173'
if (Test-Path $backendStop) {
    & $backendStop | Out-Null
}
& $backendStart

if (-not (Test-Path (Join-Path $frontendPath 'node_modules'))) {
    Write-Host 'Instalando dependencias del frontend...'
    Push-Location $frontendPath
    npm install
    Pop-Location
}

Write-Host 'Frontend React disponible en http://127.0.0.1:5173/'
Push-Location $frontendPath
npm run dev
Pop-Location
