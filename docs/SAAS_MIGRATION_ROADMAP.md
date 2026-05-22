# Roadmap SaaS PostgreSQL / JWT / Mercado Pago

Este documento guia la evolucion incremental de `sistema_de_reserva` desde la
arquitectura hibrida actual hacia una plataforma SaaS multiempresa.

## Estado Actual

```text
React + TypeScript + Vite
  -> PHP JSON endpoints
  -> SQLite
```

La rama estable `main` conserva el sistema funcional actual. Esta migracion se
desarrolla en:

```text
develop/saas-postgres-jwt-mp
```

## Arquitectura Objetivo

```text
React + TypeScript
  -> API REST PHP /api/v1
  -> PostgreSQL
  -> JWT access/refresh tokens
  -> Tenant isolation
  -> Mercado Pago Checkout Pro
```

## Principios

- Migracion incremental sin romper el sistema actual.
- No confiar en `company_id` ni roles enviados por frontend.
- Todas las queries nuevas deben aislar por tenant.
- PostgreSQL productivo; SQLite queda solo como legado temporal.
- PDO + prepared statements, sin ORM pesado.
- Endpoints versionados bajo `/api/v1`.
- Logica separada en controllers, services, repositories y middlewares.

## Fase 1 - Base PostgreSQL y API v1

Objetivo: crear la base modular sin reemplazar aun todo el sistema.

Tareas:

- Crear estructura `backend/`.
- Agregar configuracion `.env.example`.
- Crear conexion PostgreSQL centralizada con PDO.
- Crear runner de migraciones.
- Crear migraciones SQL PostgreSQL base.
- Crear seeds iniciales.
- Crear repositorios base.
- Crear endpoint healthcheck `/api/v1/health`.
- Mantener PHP legacy funcionando.

Estructura objetivo inicial:

```text
backend/
├── api/
│   └── v1/
├── config/
├── database/
├── migrations/
├── repositories/
├── services/
├── middlewares/
└── support/
```

## Fase 2 - JWT Auth

Objetivo: introducir autenticacion stateless sin romper sesiones legacy.

Endpoints:

```text
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

JWT payload:

```json
{
  "user_id": "uuid",
  "company_id": "uuid",
  "role": "admin_empresa"
}
```

Roles:

- `super_admin`
- `admin_empresa`
- `staff`
- `customer`

Estado actual en rama:

- helpers JWT HS256 creados sin dependencia externa
- repositorio de usuarios PostgreSQL creado
- repositorio de refresh tokens creado
- `AuthService` creado
- endpoints `/api/v1/auth/login.php`, `/register.php`, `/refresh.php`, `/logout.php`, `/me.php` creados
- bridges bajo `sistema_de_reserva_app/api/v1/auth/` para exponerlos desde el webroot legacy
- flujo `login -> me -> refresh -> logout` validado contra PostgreSQL local
- frontend incremental `/saas-login` creado para probar JWT API v1 desde navegador sin reemplazar auth legacy

## Fase 3 - Multiempresa

Objetivo: aislar datos por empresa.

Tabla base:

```text
companies
```

Campos minimos:

- `id`
- `name`
- `slug`
- `logo`
- `created_at`
- `updated_at`

Agregar `company_id` a:

- usuarios
- clientes
- reservas
- servicios
- profesionales
- configuracion
- eventos
- notification_log
- audit_log

Tenant resolver:

- JWT
- subdominio
- slug en URL

## Fase 4 - Mercado Pago Checkout Pro

Estado: pausada hasta nuevo aviso. La prioridad actual es completar reservas multiempresa sobre API v1 antes de conectar pagos.

Objetivo: pagos online por reserva.

Endpoints:

```text
POST /api/v1/payments/create
POST /api/v1/payments/webhook
```

Tabla:

```text
payments
```

Campos:

- `id`
- `company_id`
- `booking_id`
- `mp_payment_id`
- `mp_preference_id`
- `amount`
- `status`
- `created_at`

Estados:

- `pending`
- `approved`
- `rejected`
- `cancelled`

## Fase 5 - Dockerizacion y Operacion

Servicios:

```text
frontend
backend
postgres
```

Archivos:

```text
Dockerfile.frontend
Dockerfile.backend
docker-compose.yml
```

Requisitos operativos:

- backups PostgreSQL
- logs
- variables de entorno
- CORS controlado
- rate limiting
- healthchecks

## Variables de Entorno Objetivo

Backend:

```text
DB_HOST=
DB_PORT=5432
DB_NAME=
DB_USER=
DB_PASSWORD=
JWT_SECRET=
JWT_REFRESH_SECRET=
MP_ACCESS_TOKEN=
MP_PUBLIC_KEY=
APP_URL=
FRONTEND_URL=
```

Frontend:

```text
VITE_API_URL=
VITE_MP_PUBLIC_KEY=
```

## Regla de Migracion

Cada fase debe incluir:

- codigo
- documentacion
- prueba manual minima
- validacion de build/lint/sintaxis
- commit independiente

## Prueba Local Fase 1

1. Crear `.env` desde `.env.example`.
2. Configurar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`.
3. Crear usuario y base en PostgreSQL.

Ejemplo local:

```powershell
$env:PGPASSWORD='postgres_password'
& 'C:\Program Files\PostgreSQL\17\bin\psql.exe' -h localhost -U postgres -d postgres -c "CREATE USER sistema_de_reserva WITH PASSWORD 'change_me';"
& 'C:\Program Files\PostgreSQL\17\bin\psql.exe' -h localhost -U postgres -d postgres -c "CREATE DATABASE sistema_de_reserva OWNER sistema_de_reserva;"
```

4. Ejecutar migraciones:

```powershell
.\backend-migrate.cmd
```

5. Revisar estado:

```powershell
.\backend-migration-status.cmd
```

Si todo esta aplicado:

```text
[x] 001_create_core_saas_schema.sql
[x] 002_seed_demo_company.sql
```

6. Probar healthcheck:

```text
http://127.0.0.1:8000/api/v1/health.php
```

Validacion local realizada en esta rama:

- `pdo_pgsql` habilitado en PHP local
- base `sistema_de_reserva` creada en PostgreSQL 17
- usuario local `sistema_de_reserva` creado
- migraciones ejecutadas correctamente
- tablas creadas: `companies`, `users`, `refresh_tokens`, `company_settings`, `disciplines`, `services`, `professionals`, `professional_services`, `customers`, `bookings`, `payments`, `audit_log`, `schema_migrations`

Resultado esperado si PostgreSQL esta configurado:

```json
{
  "ok": true,
  "api": "v1",
  "service": "sistema_de_reserva",
  "database": {
    "driver": "pgsql",
    "connected": true
  }
}
```
