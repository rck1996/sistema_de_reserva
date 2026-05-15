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
$exportCsv = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "management/export.php?type=reservas&format=csv") -WebSession $adminSession
Assert-Status "Export reservas CSV" $exportCsv.StatusCode

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
if not service or not professional:
    print(json.dumps({"service_id": 0, "professional_id": 0}))
    raise SystemExit

professional_id = professional[0]
service_id = service[0]
duration = cur.execute("select duracion_minutos from servicios where id_servicio = ?", (service_id,)).fetchone()[0]
from datetime import datetime, timedelta

def free_window(day, start_hm, minutes):
    start = f"{day} {start_hm}:00"
    start_dt = datetime.strptime(start, "%Y-%m-%d %H:%M:%S")
    end_dt = start_dt + timedelta(minutes=minutes)
    end = end_dt.strftime("%Y-%m-%d %H:%M:%S")
    busy = cur.execute(
        """
        select count(*) from eventos
        where id_professional = ?
          and estado_reserva != 'cancelada'
          and start < ?
          and end > ?
        """,
        (professional_id, end, start),
    ).fetchone()[0]
    return busy == 0

today = datetime.now().date()
reservation_day = None
for offset in range(20, 180):
    candidate = today + timedelta(days=offset)
    weekday = int(candidate.strftime("%w"))
    schedule = cur.execute(
        "select is_working from professional_availability where id_professional = ? and weekday = ?",
        (professional_id, weekday),
    ).fetchone()
    if schedule and schedule[0] == 1 and free_window(candidate.isoformat(), "15:30", duration) and free_window(candidate.isoformat(), "16:30", 60):
        reservation_day = candidate.isoformat()
        break

if reservation_day is None:
    raise RuntimeError("No free smoke-test slot found")

print(json.dumps({
    "service_id": service_id,
    "professional_id": professional_id,
    "reservation_day": reservation_day,
    "reservation_hour": "15:30",
    "edit_hour": "16:30",
    "edit_end_hour": "17:30"
}))
'@ | python - $dbPath
$idData = $idPayload | ConvertFrom-Json
$serviceId = [string]$idData.service_id
$professionalId = [string]$idData.professional_id
if ($serviceId -eq "0" -or $professionalId -eq "0") {
    throw "No se pudieron obtener IDs demo desde SQLite."
}
$reservationDay = [datetime]::ParseExact([string]$idData.reservation_day, "yyyy-MM-dd", $null)
$reservationHour = [string]$idData.reservation_hour
$editHour = [string]$idData.edit_hour
$editEndHour = [string]$idData.edit_end_hour

try {
    $reservationResponse = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/api.php?accion=agendar_customer") -Method Post -WebSession $customerSession -Headers @{ "X-CSRF-Token" = $loginCsrf } -Body @{
        csrf_token = $loginCsrf
        professional_id = $professionalId
        id_servicio = $serviceId
        dia = $reservationDay.ToString("yyyy-MM-dd")
        hora = $reservationHour
        notas_reserva = "Reserva smoke"
    }
    $reservationData = $reservationResponse.Content | ConvertFrom-Json
} catch {
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    throw ("Reservation error: " + $reader.ReadToEnd())
}
if (-not $reservationData.ok -or -not $reservationData.id_evento) {
    throw "No se pudo crear la reserva de humo."
}

$editResponse = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/api.php?accion=update_event_customer") -Method Post -WebSession $customerSession -Headers @{ "X-CSRF-Token" = $loginCsrf } -Body @{
    csrf_token = $loginCsrf
    id_evento = $reservationData.id_evento
    start = ($reservationDay.ToString("yyyy-MM-dd") + "T" + $editHour + ":00")
    end = ($reservationDay.ToString("yyyy-MM-dd") + "T" + $editEndHour + ":00")
    estado_reserva = "confirmada"
    notas_reserva = "Reserva smoke reprogramada"
}
$editData = $editResponse.Content | ConvertFrom-Json
if (-not $editData.ok) {
    throw "No se pudo editar la reserva de humo."
}

try {
    $waitlistResponse = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + "bookings/api.php?accion=agendar_customer") -Method Post -WebSession $customerSession -Headers @{ "X-CSRF-Token" = $loginCsrf } -Body @{
        csrf_token = $loginCsrf
        professional_id = $professionalId
        id_servicio = $serviceId
        dia = $reservationDay.ToString("yyyy-MM-dd")
        hora = $editHour
        notas_reserva = "Reserva smoke en espera"
        waitlist_on_failure = "1"
    }
    $waitlistData = $waitlistResponse.Content | ConvertFrom-Json
} catch {
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    throw ("Waitlist error: " + $reader.ReadToEnd())
}
if (-not $waitlistData.ok) {
    throw "No se pudo crear o enviar la solicitud a lista de espera."
}
if (-not $waitlistData.waitlist) {
    throw "Se esperaba una entrada de lista de espera para el segundo intento."
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
