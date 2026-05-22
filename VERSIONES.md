# Versiones

Historial resumido de la evolucion del proyecto `sistema_de_reserva`.

## 0.2.0 - Frontend SaaS premium experimental

Fecha: 2026-05-20

Incluye:

- nueva carpeta `frontend/` con React + TypeScript para migracion incremental
- Vite, Tailwind CSS v4, Framer Motion, TanStack React Query, Zustand, React Hook Form, Zod y FullCalendar React
- layout SaaS premium con sidebar, navegacion movil inferior, hero, metricas animadas, calendario interactivo y drawer lateral
- componentes UI reutilizables: botones, cards, badges, inputs y drawer
- store local con Zustand y datos demo para revisar UI/UX sin depender de sesiones PHP
- conexion opcional a datos reales mediante `api/saas-dashboard.php`
- reemplazo de experiencia publica, login, cliente, profesional y admin por rutas React
- nuevos endpoints JSON para auth, datos publicos, cliente y profesional
- redireccion controlada de pantallas PHP antiguas hacia React cuando `FRONTEND_REPLACES_PHP=1`
- persistencia de cambios de reserva desde React usando endpoint PHP existente y CSRF
- script `start-frontend.cmd` para levantar backend PHP y frontend React juntos
- code splitting del calendario para reducir el bundle inicial
- `npm audit --audit-level=moderate` validado con 0 vulnerabilidades
- `npm run build` validado correctamente
- pantalla cliente separada del dashboard generico: inicio, reserva guiada, agenda visual, historial y perfil
- flujo cliente con filtro disciplina -> servicio -> profesional y creacion real de reservas contra backend PHP
- calendario cliente en modo lectura para evitar mutaciones de staff/admin desde una cuenta cliente
- CSRF de autenticacion endurecido para recuperar token fresco si el formulario se envia antes de cargar la sesion
- script `scripts/normalize-demo-catalog.php` para limpiar datos demo heredados y dejar servicios multidisciplinarios
- migracion parcial de administracion a React con API `api/admin-management.php`
- configuracion, reglas basicas, clientes, disciplinas y servicios editables desde el panel React con CSRF
- WhatsApp documentado como integracion opcional desactivada por defecto, con guia de activacion y pruebas
- lista de espera corregida para no generar notificaciones WhatsApp si `notifications_whatsapp_enabled` esta inactivo
- paleta de comandos real con `Ctrl+K`, busqueda, navegacion por teclado y acciones por rol
- login React con opcion `Recordarme por 30 dias` y timeout extendido sin cerrar sesion al volver al inicio
- licencia propietaria rck1996.com aplicada con `LICENSE.md`, `NOTICE.md` y aviso en README
- filtros del calendario conectados a datos reales por profesional, estado y servicio
- rama `develop/saas-postgres-jwt-mp`: Fase 2 JWT iniciada con endpoints API v1 auth y refresh tokens PostgreSQL
- frontend incremental `/saas-login` conectado a API v1 JWT para probar login, me, refresh y logout
- API v1 tenant inicial con endpoints protegidos para servicios y clientes sobre PostgreSQL
- seed demo PostgreSQL con disciplinas, servicios y clientes para validar multiempresa desde JWT
- API v1 tenant ampliada con disciplinas y profesionales protegidos por JWT
- notificaciones de correo y WhatsApp desactivadas por defecto mientras se define proveedor transaccional
- Mercado Pago pausado hasta nuevo aviso para priorizar reservas multiempresa
- API v1 de reservas sobre PostgreSQL con validacion tenant, servicio/profesional y anti-solapamiento basico
- dashboard API v1 con metricas reales PostgreSQL y primera pantalla React protegida `/saas-dashboard`
- calendario SaaS v1 protegido por JWT con FullCalendar, filtros, drawer y acciones de estado sobre PostgreSQL
- pantalla `/saas-customers` con búsqueda, listado responsive y creación de clientes sobre API v1 PostgreSQL
- actualizacion de clientes API v1 con permisos SaaS por rol y aislamiento tenant desde JWT
- README ampliado con modelo de negocio SaaS, roles y reglas multiempresa

## En desarrollo - 2026-05-14

Incluye:

- reglas de negocio ampliadas con buffers por servicio, cupos por profesional, feriados globales y lista de espera
- estados de reserva ampliados: pendiente, confirmada, en progreso, completada, no asistio y cancelada
- notificaciones de confirmacion, actualizacion, cancelacion y recordatorio con cola operativa
- soporte de correo SMTP real configurable desde panel o `.env`
- exportacion CSV y PDF de reservas y clientes
- backups y restauracion de SQLite desde administracion
- auditoria operativa en base de datos
- panel admin con metricas, bloqueos globales, operaciones y seguimiento de waitlist/notificaciones
- wizard de reserva cliente con resumen y fallback a lista de espera
- migracion de Tailwind desde CDN a build local compilado en `assets/styles/app.css`
- loader simple de `.env` y archivo `.env.example`
- prueba de humo ampliada con exportacion y lista de espera

## b464e31 - Upgrade calendars, UX, docs, and smoke tests

Fecha: 2026-05-12

Incluye:

- disponibilidad visual avanzada en calendarios
- bloques no laborables, pausas y excepciones visibles
- calendario profesional con filtros, eliminacion directa y mejor UX
- calendario cliente con reprogramacion inline desde modal
- calendario admin con filtros, overlay por profesional y CRUD mas directo
- helpers compartidos de vista en `includes/view.php`
- migraciones versionadas en `includes/migrations.php`
- favicon por defecto y mejora de branding visual
- README ampliado con caracteristicas, flujos y operacion
- script de humo `tests/smoke.ps1`
- correccion de migracion para datos heredados desde `peluqueros`

## 1376663 - Harden forms and upgrade admin customer booking UX

Fecha: 2026-05-12

Incluye:

- CSRF extendido a formularios clasicos
- reactivacion de validacion CSRF en handlers `create`, `update` y `delete`
- panel admin con modal editable y acciones directas sobre reservas
- panel cliente mejorado para editar y cancelar reservas propias
- refactor de vistas auxiliares de cliente, admin y profesional
- limpieza visual y consistencia de flujos protegidos

## 0389fd9 - Add availability, recovery, and calendar controls

Fecha: 2026-05-12

Incluye:

- disponibilidad real por profesional
- horarios semanales y excepciones por fecha
- recuperacion de contrasena con token temporal
- endurecimiento de sesion
- CSRF en auth y API
- branding configurable preparado en backend
- mejoras de agenda profesional con cambio de estado y drag/drop
- seed demo inicial mas util

## 0605ea2 - Refactor project into sistema_de_reserva

Fecha: 2026-05-12

Incluye:

- cambio de identidad del proyecto a `sistema_de_reserva`
- reorganizacion de rutas, carpetas y paneles
- neutralizacion funcional del dominio para quitar dependencia directa de peluqueria
- nuevo enfoque multidisciplinario y configurable
- paneles publicos y privados alineados con la nueva identidad

## 017bcd4 - Creacion

Commit heredado del repositorio original previo a la renovacion profunda.

## 46d29a4 - Initial commit

Commit inicial del proyecto en su etapa base.

## Notas

- Los commits `017bcd4` y `46d29a4` pertenecen a etapas anteriores a la transformacion de la plataforma.
- Desde `0605ea2` en adelante el repositorio ya refleja la nueva direccion funcional del sistema.
