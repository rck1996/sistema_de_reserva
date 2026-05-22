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

