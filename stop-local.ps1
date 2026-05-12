$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$pidFile = Join-Path $projectRoot '.local\php-server.pid'

if (!(Test-Path $pidFile)) {
    Write-Output 'No hay servidor en ejecucion.'
    exit 0
}

$serverPid = Get-Content $pidFile -ErrorAction SilentlyContinue
if ($serverPid) {
    $process = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
    if ($process) {
        Stop-Process -Id $serverPid -Force
    }
}

Remove-Item -Force $pidFile -ErrorAction SilentlyContinue
Write-Output 'Servidor detenido.'
