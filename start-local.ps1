$phpExe = 'C:\Users\x13\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$webRoot = Join-Path $projectRoot 'backend'
$logDir = Join-Path $projectRoot '.local'
$stdoutLogFile = Join-Path $logDir 'php-server.out.log'
$stderrLogFile = Join-Path $logDir 'php-server.err.log'
$pidFile = Join-Path $logDir 'php-server.pid'
$iniFile = Join-Path $projectRoot '.local\php.ini'
$sessionDir = Join-Path $projectRoot '.local\sessions'
$hostAddress = '127.0.0.1'
$port = 8000

if (!(Test-Path $phpExe)) {
    throw "No se encontro php.exe en $phpExe"
}

if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

if (!(Test-Path $sessionDir)) {
    New-Item -ItemType Directory -Path $sessionDir -Force | Out-Null
}

if (Test-Path $pidFile) {
    $existingPid = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($existingPid) {
        $existingProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
        if ($existingProcess) {
            Write-Output "El servidor ya esta corriendo en http://$hostAddress`:$port/"
            exit 0
        }
    }
    Remove-Item -Force $pidFile
}

$arguments = @('-c', $iniFile, '-S', "$hostAddress`:$port", '-t', $webRoot)
$process = Start-Process -FilePath $phpExe -ArgumentList $arguments -WorkingDirectory $webRoot -RedirectStandardOutput $stdoutLogFile -RedirectStandardError $stderrLogFile -WindowStyle Hidden -PassThru
$process.Id | Set-Content -Path $pidFile

Write-Output "Servidor iniciado en http://$hostAddress`:$port/"
