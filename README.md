# sistema_de_reserva

Sistema web de reservas multidisciplinario, configurable y desacoplado de una industria especifica. La aplicacion permite operar agendas, clientes, profesionales, servicios y branding desde una sola plataforma.

## Licencia

Este proyecto es software propietario de `rck1996.com`.

No es open source. No se permite copiar, redistribuir, revender, sublicenciar,
publicar ni reutilizar el codigo fuente total o parcialmente sin autorizacion
escrita.

Ver:

- `LICENSE.md`
- `NOTICE.md`

La version estable actual mantiene PHP + SQLite para operar el sistema completo. En paralelo se agrego una nueva base `frontend/` con React + TypeScript para migrar la experiencia hacia un SaaS premium moderno sin romper los flujos existentes.

## Resumen

El proyecto esta pensado para negocios que necesitan:

- reservas online con validacion real de disponibilidad
- multiples profesionales y disciplinas
- una identidad neutral que luego pueda personalizarse
- paneles separados por rol
- una base ligera, facil de clonar y ejecutar

No esta limitado a peluqueria. Puede adaptarse a centros de bienestar, asesoria, estetica, salud no critica, servicios profesionales y otros modelos de agenda similares.

## Modelo SaaS y permisos

La evolucion actual apunta a un SaaS multiempresa. Cada empresa opera su propio espacio aislado por `company_id`; el frontend nunca decide la empresa activa, porque el backend la obtiene desde el JWT.

Roles propuestos:

- `super_admin`: propietario de la plataforma. Puede administrar la operacion global, revisar empresas, soporte, auditoria y configuracion central.
- `admin_empresa`: administrador de una sola empresa. Puede gestionar clientes, servicios, staff, reservas y configuracion de su empresa, pero nunca ve datos de otra empresa.
- `staff`: profesional/equipo operativo. Puede ver y operar reservas de su empresa y crear/actualizar clientes cuando el flujo de atencion lo requiera.
- `customer`: cliente final. Solo debe ver su perfil y sus propias reservas.

Regla de negocio clave:

- `admin_empresa` no depende del `super_admin` para corregir clientes, servicios o reservas de su propio negocio.
- `super_admin` no debe operar manualmente cada empresa; su rol es administrar la plataforma.
- ninguna query productiva debe aceptar `company_id` desde el frontend.
- toda lectura/escritura tenant debe filtrar por `company_id` obtenido desde el JWT.

## Frontend moderno principal

La carpeta `frontend/` contiene la nueva experiencia principal del producto. Las pantallas publicas, cliente, profesional y administracion se revisan desde React:

- React 19 + TypeScript
- Vite
- Tailwind CSS v4
- Framer Motion
- TanStack React Query
- Zustand
- React Hook Form + Zod
- FullCalendar React
- componentes UI reutilizables con estilo shadcn/ui
- layout SaaS con sidebar, mobile bottom nav, cards premium, drawer lateral y calendario interactivo

Para revisarlo:

```powershell
cd C:\Users\x13\sistema_de_reserva
.\start-frontend.cmd
```

Abrir:

```text
http://127.0.0.1:5173/
```

Rutas principales:

```text
Inicio:       http://127.0.0.1:5173/
Acceso:       http://127.0.0.1:5173/login
Cliente:      http://127.0.0.1:5173/cliente
Profesional:  http://127.0.0.1:5173/profesional
Admin:        http://127.0.0.1:5173/admin
```

Atajo global:

```text
Ctrl+K abre la paleta de comandos.
```

Uso de la paleta:

- escribir para buscar una pantalla o accion
- usar flechas arriba/abajo para moverse
- presionar `Enter` para ejecutar
- presionar `Esc` o hacer clic fuera para cerrar

Acciones disponibles actualmente:

- navegacion por secciones del panel activo
- crear reserva desde cliente
- abrir agenda del cliente
- abrir configuracion de admin
- abrir calendario, clientes y estadisticas
- cerrar sesion

Credenciales demo:

```text
Admin
Tipo: Admin
Email: admin@sistema.local
Clave: Admin12345

Profesional
Tipo: Profesional
Usuario: pro1
Clave: Profesional123

Cliente
Tipo: Cliente
Usuario: cliente_demo
Clave: Cliente123
```

Sesion:

- volver a `Inicio` no cierra la sesion
- el boton `Salir` es el unico flujo que destruye la sesion
- sin marcar `Recordarme`, la sesion expira tras 2 horas de inactividad
- marcando `Recordarme por 30 dias`, la cookie queda persistente y el timeout interno se extiende a 30 dias
- usar `Recordarme` solo en equipos personales

Si una base local antigua tiene credenciales demo desactualizadas:

```powershell
& 'C:\Users\x13\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -c '.local\php.ini' 'scripts\ensure-demo-accounts.php'
```

Si una base local antigua todavia muestra servicios heredados o de una industria especifica, normalizar el catalogo demo:

```powershell
& 'C:\Users\x13\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -c '.local\php.ini' 'scripts\normalize-demo-catalog.php'
```

Para validar build:

```powershell
npm run build
npm audit --audit-level=moderate
```

Cuando se usa `start-frontend.cmd`, el backend PHP se levanta con `FRONTEND_REPLACES_PHP=1`. En ese modo, las URLs PHP antiguas redirigen hacia React. Si se necesita abrir una pantalla PHP antigua para depurar, se puede agregar `?legacy=1`.

### Conexion con backend actual

El frontend React ya intenta cargar datos reales desde:

```text
/php-api/api/saas-dashboard.php
```

En desarrollo, Vite redirige `/php-api` hacia `http://127.0.0.1:8000`. El endpoint requiere sesion de administrador PHP y entrega un `csrfToken` para mutaciones de reservas. Si no hay sesion admin, la UI queda en modo demo sin exponer datos privados.

Para revisar con datos reales:

```powershell
cd C:\Users\x13\sistema_de_reserva
.\start-frontend.cmd
```

Luego inicia sesion como admin en:

```text
http://127.0.0.1:8000/admin-login.php
```

Y abre:

```text
http://127.0.0.1:5173/
```

## Caracteristicas funcionales

### Publico

- portada configurable desde administracion
- visualizacion de disciplinas activas
- catalogo de servicios con imagen, precio, duracion y modalidad
- listado visible de profesionales
- registro de clientes desde la home
- acceso de clientes, profesionales y administracion
- recuperacion de contrasena

### Cliente

- login propio
- panel personal con agenda visual
- wizard guiado de reserva
- creacion de reservas con lista de espera automatica si no hay cupo
- reprogramacion inline desde modal
- cancelacion inline desde modal
- visualizacion de disponibilidad por profesional
- edicion de perfil
- historial visible de reservas proximas
- lectura de estados ampliados: pendiente, confirmada, en progreso, completada, no asistio y cancelada

### Profesional

- login propio
- panel con agenda individual
- creacion rapida de reservas
- drag and drop para mover reservas
- resize para ajustar duracion desde calendario
- cambio de estado desde modal
- edicion de notas desde modal
- eliminacion directa de reservas
- filtros por estado, servicio y tipo de vista
- visualizacion de bloques no laborables, pausas y excepciones
- edicion de perfil
- soporte para cupos simultaneos por profesional segun capacidad configurada

### Administracion

- login administrador
- calendario global editable
- drag and drop y resize de reservas
- cambio de profesional, estado, notas y horario desde modal
- eliminacion de reservas desde modal
- creacion manual de reservas
- gestion de clientes
- gestion de profesionales
- gestion de servicios
- gestion de disciplinas
- gestion de feriados y bloqueos globales
- busqueda en listados de clientes, profesionales y servicios
- previews de branding
- configuracion de horarios de apertura y cierre
- configuracion del intervalo de agenda
- configuracion de nombre interno, nombre visible, portada, logo, favicon y datos de contacto
- metricas reales de agenda
- exportacion CSV y PDF de reservas y clientes
- backup y restauracion de SQLite
- cola de notificaciones y bitacora de auditoria
- lista de espera visible desde administracion

## Disponibilidad y reglas de reserva

El sistema no solo agenda por hora exacta. Tambien valida:

- solape real entre reservas
- horario semanal por profesional
- pausas internas del profesional
- excepciones por fecha
- dias no laborables
- bloqueos o feriados globales
- profesional activo o inactivo
- duracion real del servicio
- intervalo minimo de agenda
- buffers antes y despues por servicio
- capacidad simultanea por profesional
- servicios exclusivos o paralelos segun configuracion

Ademas, parte de esa informacion ahora se ve visualmente en calendario:

- bloques grises para tiempo no disponible
- bloques especiales para pausas
- excepciones visibles al consultar disponibilidad por profesional

## Seguridad y endurecimiento

- sesiones endurecidas
- regeneracion de sesion en login
- timeout de inactividad
- consultas preparadas
- contrasenas con `password_hash` / `password_verify`
- CSRF en formularios y acciones criticas
- validacion de correo, telefono, colores, fechas y horarios
- validacion de subida de imagenes por extension y MIME
- tokens de recuperacion de contrasena
- auditoria de acciones operativas en base

## Branding y personalizacion

Desde administracion se puede configurar:

- nombre interno de la plataforma
- nombre visible del negocio
- tipo de negocio
- titulo principal de portada
- subtitulo principal de portada
- correo, telefono, direccion y ciudad
- horario de apertura y cierre
- intervalo base de agenda
- buffer global entre reservas
- recordatorios en horas antes de la reserva
- mensaje de reserva
- logo
- favicon
- imagen de portada
- colores del sistema
- canal email y WhatsApp para notificaciones
- remitente de correo y timezone operativa
- host, puerto, usuario, clave y cifrado SMTP

Por decision de producto actual, email y WhatsApp quedan desactivados por defecto mientras la nueva API v1 multiempresa termina reservas y se define un proveedor transaccional.

Si no se sube favicon, la app usa uno por defecto para evitar errores 404 en navegacion.

### WhatsApp opcional

La integracion WhatsApp esta desactivada por defecto:

```text
notifications_whatsapp_enabled = 0
```

El sistema no envia mensajes automaticos por WhatsApp mientras esa opcion este inactiva. En ese estado, las reservas y lista de espera siguen funcionando, pero solo se generan notificaciones por los canales activos.

Implementacion actual:

- canal opcional basado en enlaces `https://wa.me/...`
- no requiere token ni proveedor externo para generar el enlace
- no envia mensajes en segundo plano por la API oficial de Meta
- no tiene costo de API mientras se mantenga en modo enlace manual
- si se quiere envio automatico real, se debe integrar un proveedor como WhatsApp Business Cloud API, Twilio, MessageBird u otro gateway

Activacion manual desde admin:

1. Entrar como admin.
2. Ir a `Configuracion`.
3. Cambiar `WhatsApp inactivo por defecto` a `WhatsApp activo manual`.
4. Guardar configuracion.
5. Confirmar que los clientes tengan telefono en formato internacional, por ejemplo `+56912345678`.
6. Crear o actualizar una reserva de prueba.
7. Revisar la cola de notificaciones o auditoria para confirmar que se genero el canal `whatsapp`.
8. Abrir el enlace `wa.me` generado y confirmar que WhatsApp muestra el texto prellenado antes de enviarlo manualmente.

Checklist antes de activar en produccion:

- validar consentimiento del cliente para recibir mensajes
- documentar horarios de envio y frecuencia
- probar con un numero interno primero
- mantener WhatsApp inactivo si no existe responsable de operacion
- no guardar tokens reales en el repositorio

Para envio automatico real con WhatsApp Business Cloud API:

1. Crear una cuenta en Meta for Developers.
2. Crear o conectar una cuenta WhatsApp Business.
3. Obtener `phone_number_id`, `business_account_id` y token de acceso.
4. Crear plantillas aprobadas para confirmacion, recordatorio y cancelacion.
5. Guardar credenciales en `.env`, nunca en Git.
6. Reemplazar el dispatcher de `notification_log` para llamar al endpoint oficial de Meta.
7. Probar en sandbox con un numero verificado.
8. Activar `notifications_whatsapp_enabled = 1` solo despues de pasar pruebas.

## Datos demo incluidos

La base que va versionada en el repositorio incluye una demo util para revision:

- disciplinas demo
- profesionales demo
- clientes demo
- servicios demo
- reservas confirmadas y pendientes
- reservas en progreso y completadas
- excepciones de horario demo
- bloqueo global demo

Esto permite revisar el flujo completo sin partir desde cero.

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

Nota:

- si tu base local venia de una version heredada, la migracion intenta reparar automaticamente datos antiguos como `peluqueros` hacia `professionals`

## Arquitectura general

### Stack

- PHP 8.3
- SQLite
- Tailwind CSS compilado en `assets/styles/app.css`
- FullCalendar 6 por CDN

### Estructura principal

- `sistema_de_reserva_app/`
- `sistema_de_reserva_app/assets/`
- `sistema_de_reserva_app/assets/styles/`
- `sistema_de_reserva_app/assets/services/`
- `sistema_de_reserva_app/assets/branding/`
- `sistema_de_reserva_app/bookings/`
- `sistema_de_reserva_app/customer/`
- `sistema_de_reserva_app/management/`
- `sistema_de_reserva_app/staff/`
- `sistema_de_reserva_app/includes/`
- `sistema_de_reserva_app/data/sistema_de_reserva.sqlite`
- `tests/smoke.ps1`
- `VERSIONES.md`

### Archivos clave

- `sistema_de_reserva_app/includes/bootstrap.php`
  Inicializacion, helpers de dominio, validaciones y compatibilidad de datos heredados.
- `sistema_de_reserva_app/includes/operations.php`
  Reglas operativas: estados, waitlist, notificaciones, SMTP, exportes, backups y auditoria.
- `sistema_de_reserva_app/includes/migrations.php`
  Migraciones SQLite versionadas.
- `sistema_de_reserva_app/includes/view.php`
  Helpers compartidos de presentacion.
- `sistema_de_reserva_app/bookings/api.php`
  Acciones AJAX para crear, editar y eliminar reservas.
- `sistema_de_reserva_app/bookings/availability-feed.php`
  Feed visual de disponibilidad y bloques no laborables.

## Flujos principales

### Flujo publico

1. El visitante entra a la home.
2. Revisa disciplinas, servicios y profesionales.
3. Se registra como cliente o inicia sesion.

### Flujo cliente

1. El cliente entra a su panel.
2. Filtra por disciplina y elige profesional y servicio.
3. Revisa la confirmacion del wizard y reserva un bloque horario.
4. Si no hay cupo, puede caer a lista de espera automatica.
5. Puede reprogramar o cancelar desde el modal del calendario.

### Flujo profesional

1. El profesional entra a su agenda.
2. Visualiza reservas, pausas y tiempo no disponible.
3. Puede crear, mover, editar o eliminar reservas desde el calendario.

### Flujo administrador

1. El admin entra al panel global.
2. Configura identidad y agenda.
3. Gestiona clientes, equipo, servicios y disciplinas.
4. Opera reservas desde el calendario central.
5. Exporta datos, procesa notificaciones, genera backups y revisa auditoria.

## Instalacion y ejecucion local

### Requisitos

- PHP 8.3 o compatible
- extensiones `pdo_sqlite` y `sqlite3`
- permisos de escritura sobre:
  - `sistema_de_reserva_app/data/`
  - `sistema_de_reserva_app/assets/services/`
  - `sistema_de_reserva_app/assets/branding/`
  - `sistema_de_reserva_app/assets/styles/`

### Variables de entorno

- copia `.env.example` a `.env` si quieres forzar valores de entorno locales
- claves base:
  - `APP_ENV`
  - `APP_TIMEZONE`
  - `NOTIFICATIONS_SEND_EMAIL`
  - `SMTP_HOST`
  - `SMTP_PORT`
  - `SMTP_USERNAME`
  - `SMTP_PASSWORD`
  - `SMTP_ENCRYPTION`
  - `SMTP_FROM_NAME`
  - `SMTP_FROM_EMAIL`

### Correo real

- por defecto las notificaciones de correo quedan inactivas
- para envio real:
  - activa `notifications_send_email` en administracion o `NOTIFICATIONS_SEND_EMAIL=1` en `.env`
  - configura host, puerto, usuario, clave y cifrado SMTP
  - el sistema usa envio SMTP directo, sin Composer ni dependencias externas
- en la arquitectura SaaS nueva se recomienda reemplazar SMTP legacy por un proveedor API como Resend o Brevo

### Arranque local

Iniciar:

```bat
start-local.cmd
```

Detener:

```bat
stop-local.cmd
```

URL:

- `http://127.0.0.1:8000/`

## Pruebas

- prueba de humo principal:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke.ps1
```

- la prueba cubre:
  - home
  - login admin
  - exportacion CSV
  - login profesional
  - registro y login cliente
  - creacion de reserva
  - reprogramacion
  - lista de espera
  - feed publico

### Smoke tests

Con el servidor levantado:

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke.ps1
```

El script valida:

- home publica
- login admin
- panel admin
- login profesional
- panel profesional
- registro y login cliente
- panel cliente
- creacion de reserva cliente
- reprogramacion de reserva cliente
- feed publico del calendario

## Publicacion recomendada

1. Mantener el repositorio con nombre `sistema_de_reserva`.
2. Ignorar `.local/` porque solo contiene runtime local y logs.
3. Conservar `sistema_de_reserva_app/data/sistema_de_reserva.sqlite` si se quiere una demo lista para revisar.
4. Cambiar credenciales iniciales del admin despues del primer despliegue.
5. Ejecutar `tests/smoke.ps1` antes de publicar una nueva version.
6. Revisar `VERSIONES.md` para dejar trazabilidad clara del release.

## Limitaciones actuales

- Tailwind sigue cargando por CDN, no por build local.
- No hay suite formal de tests unitarios o integracion mas alla del smoke test.
- Parte de la compatibilidad heredada sigue concentrada en `bootstrap.php`.
- No hay sistema de notificaciones por correo o WhatsApp.

## Documentacion adicional

- historial de cambios: [VERSIONES.md](C:/Users/x13/sistema_de_reserva/VERSIONES.md)

## Notas tecnicas

- El codigo operativo ya no depende de nombres antiguos relacionados con peluqueria.
- La app mantiene compatibilidad de migracion para bases heredadas del proyecto original.
- Las migraciones nuevas se registran en `schema_migrations`.
- Los helpers visuales compartidos viven en `sistema_de_reserva_app/includes/view.php`.
