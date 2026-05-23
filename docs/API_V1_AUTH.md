# API v1 Auth JWT

Base local:

```text
http://127.0.0.1:8000/api/v1
```

La autenticacion nueva usa JWT stateless con:

- access token corto
- refresh token largo
- refresh tokens guardados hasheados en PostgreSQL
- tenant por `company_id`
- rol dentro del token

## Usuario Demo

```json
{
  "company_slug": "demo",
  "email": "admin@demo.local",
  "password": "Admin12345"
}
```

## Login

```http
POST /api/v1/auth/login.php
Content-Type: application/json
```

```json
{
  "company_slug": "demo",
  "email": "admin@demo.local",
  "password": "Admin12345"
}
```

Respuesta:

```json
{
  "ok": true,
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "expires_in": 900,
  "user": {
    "id": "...",
    "company_id": "...",
    "company_slug": "demo",
    "role": "admin_empresa"
  }
}
```

## Me

```http
GET /api/v1/auth/me.php
Authorization: Bearer ACCESS_TOKEN
```

## Refresh

```http
POST /api/v1/auth/refresh.php
Content-Type: application/json
```

```json
{
  "refresh_token": "..."
}
```

El refresh token usado se revoca y se emite uno nuevo.

## Logout

```http
POST /api/v1/auth/logout.php
Content-Type: application/json
```

```json
{
  "refresh_token": "..."
}
```

## Register Empresa

```http
POST /api/v1/auth/register.php
Content-Type: application/json
```

```json
{
  "company_name": "Empresa Demo",
  "company_slug": "empresa-demo",
  "email": "admin@empresa-demo.local",
  "password": "Admin12345",
  "username": "admin"
}
```

Esto crea:

- empresa
- usuario `admin_empresa`
- access token
- refresh token

## Login Cliente Marketplace

```http
POST /api/v1/auth/customer-login.php
Content-Type: application/json
```

```json
{
  "email": "camila@demo.local",
  "password": "Cliente123"
}
```

El cliente marketplace inicia sesion globalmente, sin `company_slug`.

Demo:

```json
{
  "email": "cliente@marketplace.local",
  "password": "Cliente12345"
}
```

## Login Superadmin

```http
POST /api/v1/auth/super-admin-login.php
Content-Type: application/json
```

```json
{
  "email": "super@rck1996.com",
  "password": "SuperAdmin12345"
}
```

## Register Cliente Marketplace

```http
POST /api/v1/auth/customer-register.php
Content-Type: application/json
```

```json
{
  "first_name": "Camila",
  "last_name": "Rojas",
  "email": "camila@demo.local",
  "phone": "+56911111111",
  "password": "Cliente123"
}
```

Esto crea:

- `users.role = customer`
- `customer_profiles.user_id = users.id`
- access token
- refresh token

Modelo marketplace objetivo:

- el cliente tiene cuenta global.
- el cliente puede inscribirse en multiples empresas mediante `company_customers`.
- el portal cliente debe resolver `users.id -> customer_profiles.user_id`; no acepta `customer_id` desde frontend.
- usar `customer-register.php` y `customer-login.php` para cuentas cliente marketplace.

## Claims JWT

```json
{
  "user_id": "...",
  "company_id": "...",
  "role": "admin_empresa",
  "iat": 123,
  "exp": 123
}
```

## Validacion Local Realizada

```text
login: OK
me: OK
refresh: OK
logout: OK
```

## Prueba Desde Frontend React

Levantar frontend:

```powershell
.\start-frontend.cmd
```

Abrir:

```text
http://127.0.0.1:5173/saas-login
```

La pantalla permite probar:

- login API v1
- `GET /auth/me.php`
- refresh token
- logout
- persistencia temporal en `localStorage`
- listado protegido de servicios y clientes tenant

Esta pantalla queda como laboratorio temporal de API v1.

Recursos tenant protegidos:

```text
docs/API_V1_TENANT_RESOURCES.md
```
