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

Esta pantalla es incremental y no reemplaza aun el login legacy.
