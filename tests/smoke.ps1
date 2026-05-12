param(
    [string]$BaseUrl = "http://127.0.0.1:8000/"
)

$ErrorActionPreference = "Stop"

function Get-CsrfToken {
    param(
        [string]$Content
    )

    if ($Content -match 'name="csrf_token" value="([^"]+)"') {
        return $matches[1]
    }

    throw "No se pudo obtener el token CSRF."
}

function Assert-Status {
    param(
        [string]$Name,
        [int]$Actual,
        [int]$Expected = 200
    )

    if ($Actual -ne $Expected) {
        throw "$Name devolvio $Actual y se esperaba $Expected."
    }
}

$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$publicHome = Invoke-WebRequest -UseBasicParsing -Uri $BaseUrl -WebSession $adminSession
Assert-Status "Home" $publicHome.StatusCode

$adminLogin = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "admin-login.php") -WebSession $adminSession
Assert-Status "Admin login" $adminLogin.StatusCode
$adminCsrf = Get-CsrfToken $adminLogin.Content
Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "auth.php") -Method Post -WebSession $adminSession -Body @{
    csrf_token = $adminCsrf
    action = "login-admin"
    email = "admin@sistema.local"
    password = "Admin12345"
} | Out-Null
$adminDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "admin-dashboard.php") -WebSession $adminSession
Assert-Status "Admin dashboard" $adminDashboard.StatusCode

$staffSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$staffLogin = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "staff-login.php") -WebSession $staffSession
Assert-Status "Staff login" $staffLogin.StatusCode
$staffCsrf = Get-CsrfToken $staffLogin.Content
Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "auth.php") -Method Post -WebSession $staffSession -Body @{
    csrf_token = $staffCsrf
    action = "login-staff"
    username = "ana.bustos"
    password = "Profesional123"
} | Out-Null
$staffDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "staff-dashboard.php") -WebSession $staffSession
Assert-Status "Staff dashboard" $staffDashboard.StatusCode

$customerSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$landingPage = Invoke-WebRequest -UseBasicParsing -Uri $BaseUrl -WebSession $customerSession
$publicCsrf = Get-CsrfToken $landingPage.Content
$customerEmail = "smoke." + [guid]::NewGuid().ToString("N").Substring(0, 8) + "@local.test"
$customerUser = "smoke" + [guid]::NewGuid().ToString("N").Substring(0, 6)
Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "auth.php") -Method Post -WebSession $customerSession -Body @{
    csrf_token = $publicCsrf
    action = "register-customer"
    first_name = "Smoke"
    last_name = "Tester"
    phone = "+56999999999"
    email = $customerEmail
    username = $customerUser
    password = "Cliente123"
} | Out-Null

$publicPage2 = Invoke-WebRequest -UseBasicParsing -Uri $BaseUrl -WebSession $customerSession
$loginCsrf = Get-CsrfToken $publicPage2.Content
Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "auth.php") -Method Post -WebSession $customerSession -Body @{
    csrf_token = $loginCsrf
    action = "login-customer"
    username = $customerUser
    password = "Cliente123"
} | Out-Null
$customerDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "customer-dashboard.php") -WebSession $customerSession
Assert-Status "Customer dashboard" $customerDashboard.StatusCode

$dbPath = Join-Path $PSScriptRoot "..\\sistema_de_reserva_app\\data\\sistema_de_reserva.sqlite"
$idPayload = @'
import json, sqlite3, sys
conn = sqlite3.connect(sys.argv[1])
cur = conn.cursor()
service = cur.execute("select id_servicio from servicios where activo = 1 order by id_servicio limit 1").fetchone()
professional = cur.execute("select id_professional from professionals where activo = 1 order by id_professional limit 1").fetchone()
print(json.dumps({"service_id": service[0] if service else 0, "professional_id": professional[0] if professional else 0}))
'@ | python - $dbPath
$idData = $idPayload | ConvertFrom-Json
$serviceId = [string]$idData.service_id
$professionalId = [string]$idData.professional_id
if ($serviceId -eq "0" -or $professionalId -eq "0") {
    throw "No se pudieron obtener IDs demo desde SQLite."
}

$reservationResponse = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/api.php?accion=agendar_customer") -Method Post -WebSession $customerSession -Headers @{ "X-CSRF-Token" = $loginCsrf } -Body @{
    csrf_token = $loginCsrf
    professional_id = $professionalId
    id_servicio = $serviceId
    dia = (Get-Date).AddDays(14).ToString("yyyy-MM-dd")
    hora = "16:00"
    notas_reserva = "Reserva smoke"
}
$reservationData = $reservationResponse.Content | ConvertFrom-Json
if (-not $reservationData.ok -or -not $reservationData.id_evento) {
    throw "No se pudo crear la reserva de humo."
}

$editResponse = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/api.php?accion=update_event_customer") -Method Post -WebSession $customerSession -Headers @{ "X-CSRF-Token" = $loginCsrf } -Body @{
    csrf_token = $loginCsrf
    id_evento = $reservationData.id_evento
    start = (Get-Date).AddDays(14).Date.AddHours(17).ToString("s")
    end = (Get-Date).AddDays(14).Date.AddHours(17).AddMinutes(30).ToString("s")
    estado_reserva = "confirmada"
    notas_reserva = "Reserva smoke reprogramada"
}
$editData = $editResponse.Content | ConvertFrom-Json
if (-not $editData.ok) {
    throw "No se pudo editar la reserva de humo."
}

$feed = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/public-feed.php")
Assert-Status "Public feed" $feed.StatusCode

@'
import sqlite3, sys
conn = sqlite3.connect(sys.argv[1])
cur = conn.cursor()
cur.execute("DELETE FROM clientes WHERE correo_cliente = ?", (sys.argv[2],))
conn.commit()
'@ | python - $dbPath $customerEmail | Out-Null

Write-Output "Smoke tests OK"
