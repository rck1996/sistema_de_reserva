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

## Disciplinas

```http
GET /api/v1/disciplines.php
```

Lista disciplinas de la empresa autenticada.

```http
POST /api/v1/disciplines.php
Content-Type: application/json
```

Roles permitidos:

- `super_admin`
- `admin_empresa`

Payload:

```json
{
  "name": "Consultoria",
  "description": "Servicios de diagnostico y seguimiento",
  "color": "#22d3ee",
  "is_active": true
}
```

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

## Reservas

```http
GET /api/v1/bookings.php
```

Lista reservas de la empresa autenticada con cliente, profesional y servicio.

```http
POST /api/v1/bookings.php
Content-Type: application/json
```

Roles permitidos:

- `super_admin`
- `admin_empresa`
- `staff`

Payload:

```json
{
  "customer_id": "uuid-cliente",
  "professional_id": "uuid-profesional",
  "service_id": "uuid-servicio",
  "starts_at": "2026-06-02T10:00:00-04:00",
  "status": "confirmed",
  "notes": "Reserva creada desde API v1"
}
```

Reglas:

- Cliente, profesional y servicio deben pertenecer a la empresa del JWT.
- El profesional debe estar asociado al servicio.
- `ends_at` se calcula con la duracion del servicio.
- No se permiten solapamientos activos para el mismo profesional.
- Reservas `cancelled` no bloquean disponibilidad.

```http
PATCH /api/v1/bookings.php
Content-Type: application/json
```

Cambiar estado:

```json
{
  "id": "uuid-reserva",
  "status": "completed"
}
```

Reprogramar:

```json
{
  "id": "uuid-reserva",
  "starts_at": "2026-06-03T15:30:00-04:00"
}
```

## Prueba Rapida En React

1. Levantar backend y frontend con `.\start-frontend.cmd`.
2. Abrir `http://127.0.0.1:5173/saas-login`.
3. Iniciar sesion con `admin@demo.local` / `Admin12345` / empresa `demo`.
4. Usar los botones `Listar disciplinas tenant`, `Listar servicios tenant`, `Listar profesionales tenant`, `Listar clientes tenant`, `Listar reservas tenant` y `Crear reserva demo`.

## Profesionales

```http
GET /api/v1/professionals.php
```

Lista profesionales de la empresa autenticada con sus servicios asociados.

```http
POST /api/v1/professionals.php
Content-Type: application/json
```

Roles permitidos:

- `super_admin`
- `admin_empresa`

Payload:

```json
{
  "name": "Valentina Morales",
  "email": "valentina.morales@demo.local",
  "phone": "+56933333333",
  "bio": "Especialista demo",
  "calendar_color": "#22d3ee",
  "booking_capacity": 1,
  "accepts_waitlist": true,
  "is_active": true,
  "service_ids": ["uuid-servicio"]
}
```

Reglas:

- Todos los `service_ids` deben pertenecer a la empresa autenticada.
- El `company_id` se resuelve desde JWT y no desde el payload.

## Validacion Local Realizada

```text
GET /api/v1/services.php: OK
GET /api/v1/disciplines.php: OK
GET /api/v1/professionals.php: OK
GET /api/v1/customers.php: OK
GET /api/v1/bookings.php: OK
POST /api/v1/bookings.php: OK
tenant desde JWT: OK
build frontend: OK
typecheck frontend: OK
```
