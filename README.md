# sistema_de_reserva

Sistema web de reservas multidisciplinario, configurable y desacoplado de una industria específica. La aplicación está preparada para personalizar identidad, disciplinas, servicios, profesionales y horarios desde administración.

## Qué incluye esta versión

- SQLite lista para usar, sin dependencia de MySQL.
- Paneles separados para administración, clientes y profesionales.
- Calendarios visuales con FullCalendar 6.
- Disponibilidad real por profesional.
  Bloques no laborables, pausas y excepciones visibles en calendario.
- Gestión inline de reservas desde modal.
  Admin, cliente y profesional pueden actualizar o cancelar sin salir del calendario.
- CRUD mejorado con filtros, búsqueda y acciones directas.
- Branding configurable.
  Nombre, portada, logo, favicon, contacto y horario.
- Migraciones SQLite registradas con `schema_migrations`.
- Script de humo para comprobar login, paneles y feed principal.

## Stack

- PHP 8.3
- SQLite
- Tailwind CSS por CDN
- FullCalendar 6 por CDN

## Estructura

- `sistema_de_reserva_app/`
- `sistema_de_reserva_app/assets/`
- `sistema_de_reserva_app/bookings/`
- `sistema_de_reserva_app/customer/`
- `sistema_de_reserva_app/management/`
- `sistema_de_reserva_app/staff/`
- `sistema_de_reserva_app/includes/`
- `sistema_de_reserva_app/data/sistema_de_reserva.sqlite`
- `tests/smoke.ps1`

## Credenciales demo

Administrador:
- correo: `admin@sistema.local`
- clave: `Admin12345`

Profesionales demo:
- usuario: `ana.bustos`
- clave: `Profesional123`
- usuario: `matias.reyes`
- clave: `Profesional123`
- usuario: `laura.mendez`
- clave: `Profesional123`

Clientes demo:
- usuario: `camila.torres`
- clave: `Cliente123`
- usuario: `diego.molina`
- clave: `Cliente123`
- usuario: `valentina.rojas`
- clave: `Cliente123`

## Datos demo incluidos

La base del repositorio arranca con:

- 3 disciplinas
- 3 profesionales
- 3 clientes
- 3 servicios
- reservas demo confirmadas y pendientes
- una excepción de horario para mostrar disponibilidad especial

Esto permite revisar el producto sin partir desde una base vacía.

## Flujos principales

Home pública:
- revisar disciplinas, servicios y equipo
- registrar cliente
- iniciar sesión como cliente

Panel cliente:
- reservar horario
- ver disponibilidad visual por profesional
- reprogramar o cancelar reservas desde modal

Panel profesional:
- revisar agenda propia
- ver pausas, tiempo no laborable y excepciones
- crear, actualizar o eliminar reservas desde el calendario

Panel administrador:
- configurar identidad y horario
- gestionar profesionales, clientes y servicios
- usar calendario global con filtros y edición inline

## Ejecución local

Arrancar:

```bat
start-local.cmd
```

Detener:

```bat
stop-local.cmd
```

URL local:

- `http://127.0.0.1:8000/`

## Requisitos

- PHP 8.3 o compatible
- extensiones `pdo_sqlite` y `sqlite3`
- permisos de escritura sobre:
  - `sistema_de_reserva_app/data/`
  - `sistema_de_reserva_app/assets/services/`
  - `sistema_de_reserva_app/assets/branding/`

## Pruebas de humo

Con el servidor local arriba:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke.ps1
```

El script valida:

- home pública
- login admin
- panel admin
- registro/login cliente
- panel cliente
- feed público del calendario

## Publicación recomendada

1. Mantener el repositorio con nombre `sistema_de_reserva`.
2. Ignorar `.local/` porque solo contiene runtime local y logs.
3. Conservar `sistema_de_reserva_app/data/sistema_de_reserva.sqlite` si se quiere una demo lista para revisar.
4. Cambiar credenciales iniciales del admin después del primer despliegue.
5. Ejecutar `tests/smoke.ps1` antes de publicar una nueva versión.

## Notas técnicas

- La app mantiene compatibilidad de migración para bases heredadas del proyecto original.
- El código operativo ya no depende de nombres antiguos relacionados con peluquería.
- Las migraciones versionadas nuevas viven en `sistema_de_reserva_app/includes/migrations.php`.
- Las utilidades visuales compartidas viven en `sistema_de_reserva_app/includes/view.php`.
