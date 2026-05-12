# sistema_de_reserva

Sistema web de reservas multidisciplinario, configurable y desacoplado de una industria especifica. La aplicacion esta preparada para personalizar identidad, disciplinas, servicios, profesionales y horarios desde administracion.

## Estado actual

- Base de datos SQLite lista para usar.
- Identidad neutral configurable desde el panel de administracion.
- Paneles separados para administracion, clientes y profesionales.
- Calendarios visuales con FullCalendar para agenda global, cliente y profesional.
- Servicios con imagen, duracion, modalidad, precio y colores.
- Validacion de reservas por solape real de horarios.
- Subida segura de imagenes para servicios.
- Estructura y nombres internos migrados a `professionals` / `id_professional`.

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

## Acceso inicial

- Admin: `admin@sistema.local`
- Clave: `Admin12345`

## Ejecucion local

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

## Requisitos para otro entorno

- PHP 8.3 o compatible
- extensiones `pdo_sqlite` y `sqlite3`
- permisos de escritura sobre:
  - `sistema_de_reserva_app/data/`
  - `sistema_de_reserva_app/assets/services/`

## Configuracion funcional

Desde administracion se puede configurar:

- nombre interno y nombre visible
- tipo de negocio
- titulo y subtitulo de portada
- colores principales
- correo y datos de contacto
- horario de apertura y cierre
- intervalo de agenda
- disciplinas
- profesionales
- servicios

## Publicacion recomendada

Para subir esta version limpia a GitHub:

1. Mantener el repositorio con nombre `sistema_de_reserva`.
2. Ignorar `.local/` porque solo contiene runtime local de PHP y logs.
3. Conservar `sistema_de_reserva_app/data/sistema_de_reserva.sqlite` si se quiere una demo lista para probar.
4. Cambiar credenciales iniciales del admin despues del primer despliegue.

## Notas

- La app mantiene una migracion interna para convertir bases antiguas heredadas del proyecto original al nuevo esquema neutral.
- El codigo operativo ya no depende de nombres antiguos relacionados con peluqueria.
