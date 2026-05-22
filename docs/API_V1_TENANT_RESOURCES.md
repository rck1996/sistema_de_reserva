# API v1 Recursos Tenant

Base local:

```text
http://127.0.0.1:8000/api/v1
```

Todos estos endpoints requieren:

```http
Authorization: Bearer ACCESS_TOKEN
```

El `company_id` nunca se toma desde el frontend. Siempre se resuelve desde el JWT para evitar acceso cruzado entre empresas.

## Servicios

```http
GET /api/v1/services.php
```

Lista servicios de la empresa autenticada.

```http
POST /api/v1/services.php
Content-Type: application/json
```

Roles permitidos:

- `super_admin`
- `admin_empresa`

Payload:

```json
{
  "discipline_id": "uuid-opcional",
  "name": "Diagnostico inicial",
  "description": "Sesion de evaluacion",
  "price": 29990,
  "duration_minutes": 45,
  "modality": "presencial",
  "color": "#22d3ee",
  "is_active": true
}
```

Reglas:

- Si `discipline_id` viene informado, debe pertenecer a la misma empresa del JWT.
- No se acepta crear servicios para otra empresa desde el payload.

## Clientes

```http
GET /api/v1/customers.php
```

Lista clientes de la empresa autenticada.

```http
POST /api/v1/customers.php
Content-Type: application/json
```

Roles permitidos:

- `super_admin`
- `admin_empresa`
- `staff`

Payload:

```json
{
  "first_name": "Camila",
  "last_name": "Rojas",
  "email": "camila.rojas@demo.local",
  "phone": "+56911111111",
  "notes": "Cliente demo"
}
```

## Prueba Rapida En React

1. Levantar backend y frontend con `.\start-frontend.cmd`.
2. Abrir `http://127.0.0.1:5173/saas-login`.
3. Iniciar sesion con `admin@demo.local` / `Admin12345` / empresa `demo`.
4. Usar los botones `Listar servicios tenant` y `Listar clientes tenant`.

## Validacion Local Realizada

```text
GET /api/v1/services.php: OK
GET /api/v1/customers.php: OK
tenant desde JWT: OK
build frontend: OK
typecheck frontend: OK
```
