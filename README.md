# sistema_de_reserva

Sistema Reserva es una plataforma SaaS marketplace multiempresa para reservas multidisciplinarias.

El producto ya no usa SQLite ni el sistema legacy como flujo activo. La arquitectura principal es React + API v1 PHP modular + PostgreSQL + JWT.

## Vision

Un cliente crea una cuenta global en Sistema Reserva, explora negocios publicados, se inscribe en las empresas que le interesan y reserva servicios segun la disponibilidad real de sus profesionales.

Cada empresa administra su propio perfil publico, catalogo, profesionales, horarios, bloqueos, clientes inscritos, metricas y configuracion operativa.

## Arquitectura

```text
frontend/
  React + TypeScript + Vite + Tailwind
        |
        v
backend/api/v1/
  PHP modular + JWT + repositories
        |
        v
PostgreSQL
```

Carpetas principales:

```text
backend/
  api/v1/              endpoints REST versionados
  config/              variables y configuracion
  database/            conexion y migrador
  migrations/          SQL PostgreSQL versionado
  middlewares/         auth, roles y tenant
  repositories/        acceso a datos
  services/            casos de uso

frontend/
  src/app/             rutas principales
  src/features/        pantallas por dominio
  src/services/        cliente API v1
  src/components/      UI reutilizable
```

## Roles

- `customer`: cliente marketplace global. Ve agenda personal, explora empresas, se inscribe y reserva.
- `staff`: profesional dentro de una empresa. Ve agenda laboral y bloquea horarios no disponibles.
- `admin_empresa`: administra una empresa, perfil publico, servicios, staff, clientes, horarios y metricas.
- `super_admin`: propietario de plataforma. Acceso oculto para operacion global.

Regla critica:

- un profesional siempre pertenece a una empresa.
- toda agenda profesional se filtra por `company_id`.
- un profesional no puede tener dos reservas activas solapadas.
- el cliente solo puede reservar en una empresa si esta inscrito.
- el frontend nunca decide `company_id`; el backend lo resuelve desde JWT o slug publico validado.

## Pantallas

Publicas:

```text
/                       landing de Sistema Reserva
/marketplace            explorador de negocios
/customer-login         login cliente visible
/staff-login            login profesional visible
/company-admin-login    login empresa visible
/superadmin-login       login propietario oculto
```

Paneles:

```text
/saas-customer          agenda y perfil cliente
/staff-workspace        agenda y bloqueos profesional
/saas-dashboard         dashboard empresa
/saas-calendar          calendario empresa
/saas-customers         clientes inscritos empresa
/superadmin             control global inicial
```

## Flujos por rol

Cliente:

```text
1. Entra por /customer-login o crea cuenta en /customer-register.
2. Abre /marketplace.
3. Busca una empresa y revisa su perfil, disciplinas, servicios y profesionales.
4. Se inscribe en la empresa para habilitar el uso de sus datos.
5. Selecciona disciplina, servicio, profesional y fecha/hora.
6. Crea la reserva desde el marketplace.
7. Revisa su agenda global en /saas-customer.
```

La reserva valida en backend:

- cliente autenticado con rol `customer`
- empresa publica existente
- cliente inscrito en esa empresa
- profesional dentro de esa empresa
- profesional asociado al servicio seleccionado
- horario dentro de disponibilidad
- sin bloqueo del profesional
- sin solape con otra reserva activa

Profesional:

```text
1. Entra por /staff-login con slug de empresa.
2. Abre /staff-workspace.
3. Revisa agenda laboral propia.
4. Crea bloqueos, pausas, vacaciones o excepciones.
```

Admin empresa:

```text
1. Entra por /company-admin-login con slug de empresa.
2. Revisa /saas-dashboard con metricas operativas.
3. Gestiona calendario en /saas-calendar.
4. Gestiona clientes inscritos en /saas-customers.
```

Superadmin:

```text
1. Entra por /superadmin-login.
2. Revisa metricas globales de plataforma.
3. Lista empresas, usuarios, servicios, profesionales y reservas agregadas.
4. Edita perfiles publicos de empresas y visibilidad marketplace.
5. Revisa actividad reciente de reservas.
```

## Datos demo

Cliente marketplace:

```text
Email: cliente@marketplace.local
Clave: Cliente12345
```

Empresa:

```text
Slug: demo
Email: admin@demo.local
Clave: Admin12345
```

Profesional:

```text
Slug: centro-kine
Email: camila.rojas@centrokine.local
Clave: Staff12345
```

Superadmin:

```text
Email: super@rck1996.com
Clave: SuperAdmin12345
Ruta: /superadmin-login
```

Empresas demo:

```text
demo
centro-kine
estudio-estetica
consultora-pro
wellness-studio
```

## Ejecutar local

Requisitos:

- PHP 8.3 con PDO PostgreSQL
- PostgreSQL local
- Node.js + npm

Aplicar migraciones:

```powershell
cd C:\Users\x13\sistema_de_reserva
.\backend-migrate.cmd
```

Levantar backend y frontend:

```powershell
.\start-frontend.cmd
```

Abrir:

```text
http://127.0.0.1:5173/
```

Validar API:

```text
http://127.0.0.1:8000/api/v1/health.php
```

## Estado actual

Implementado:

- PostgreSQL con migraciones versionadas.
- JWT para customer, staff, admin empresa y superadmin.
- marketplace publico con cinco empresas demo.
- inscripcion cliente-empresa.
- agenda global cliente.
- dashboard, calendario y clientes de empresa.
- staff workspace con agenda profesional y bloqueos.
- superadmin operativo con metricas globales, empresas, edicion de perfil publico y actividad reciente.
- marketplace con buscador, inscripcion y creacion de reserva desde la misma pantalla.
- reserva marketplace con validacion de empresa, servicio, profesional, disponibilidad, bloqueos y solape.
- webroot backend limpio en `backend/`.

Pendiente:

- completar CRUD visual de perfil empresa, servicios, profesionales, horarios, feriados y vacaciones.
- mejorar reserva marketplace con slots visuales generados automaticamente en vez de selector manual `datetime-local`.
- eliminar componentes React muertos heredados del periodo transicional.
- agregar tests automaticos de auth, tenant isolation, reservas y disponibilidad.
- dockerizar frontend, backend y PostgreSQL.
- retomar pagos/notificaciones cuando el core marketplace este estable.

## Licencia

Software propietario de `rck1996.com`.

Ver `LICENSE.md` y `NOTICE.md`.
