# Contexto del proyecto

## Ubicacion actual

Repositorio local:

```text
C:\Users\x13\sistema_de_reserva
```

Rama de evolucion SaaS:

```text
develop/saas-postgres-jwt-mp
```

Repositorio GitHub:

```text
https://github.com/rck1996/sistema_de_reserva
```

## Estado actual

`sistema_de_reserva` nacio como una renovacion de un sistema de reservas orientado a peluqueria, pero esa identidad ya no es el objetivo. La direccion actual es una plataforma SaaS marketplace multiempresa para reservas multidisciplinarias.

La aplicacion esta en una etapa de migracion hacia arquitectura SaaS limpia:

- el nuevo frontend esta en `frontend/` con React, TypeScript, Vite y Tailwind.
- la nueva API v1 esta en `backend/` con PHP modular, JWT y PostgreSQL.
- la base nueva usa PostgreSQL y migraciones SQL versionadas.
- el arbol PHP/SQLite legacy fue retirado del flujo activo.
- Mercado Pago, correo y WhatsApp estan pausados/desactivados hasta que se defina proveedor y modelo comercial.

## Vision nueva

El producto debe comportarse como un marketplace de reservas, no como un panel administrativo aislado.

Objetivo:

```text
Un cliente crea una sola cuenta en sistema_de_reserva.
Luego busca empresas disponibles en el marketplace.
Se inscribe en las empresas donde quiere reservar.
Desde su agenda global puede ver reservas de distintas empresas.
Cada empresa administra su perfil, catalogo, profesionales, disponibilidad y clientes inscritos.
```

Esto permite que una misma plataforma sirva para:

- centros de salud no critica
- estetica y bienestar
- consultorias
- academias
- servicios profesionales
- negocios con agenda por staff

## Modelo de negocio objetivo

La plataforma funciona como SaaS multiempresa:

- `super_admin`: propietario de la plataforma, gestiona operacion global.
- `admin_empresa`: administra solo su empresa.
- `staff`: profesional operativo de una empresa.
- `customer`: cliente global del marketplace.

Reglas clave:

- el cliente tiene una sola cuenta global.
- una empresa solo ve clientes inscritos en esa empresa.
- el cliente solo puede reservar en empresas donde esta inscrito.
- ninguna empresa puede acceder datos de otra empresa.
- el backend nunca debe confiar en `company_id` enviado por frontend.
- el tenant se resuelve desde JWT, slug publico o contexto controlado por backend.

## Arquitectura destino

```text
Frontend React + TypeScript
        |
        v
API REST /api/v1 PHP modular
        |
        v
PostgreSQL
        |
        v
JWT Auth + tenant isolation
```

Carpetas relevantes:

```text
backend/
  api/
  config/
  database/
  http/
  migrations/
  repositories/
  services/

frontend/
  src/
    app/
    components/
    features/
    services/
    store/
    types/

```

## Flujo de pantallas objetivo

Cliente:

```text
/                       landing
/marketplace            buscar empresas
/customer-register      registro cliente global
/customer-login         login cliente global
/saas-customer          portal cliente y agenda global
```

Empresa:

```text
/company-admin-login    acceso empresa
/saas-dashboard         metricas y operacion
/saas-calendar          calendario empresa
/saas-customers         clientes inscritos
```

Herramientas internas:

```text
/saas-login             laboratorio API v1 temporal
/superadmin-login       login oculto propietario
/superadmin             control global inicial
```

## Datos demo actuales

Cliente marketplace global:

```text
Email: cliente@marketplace.local
Clave: Cliente12345
```

Empresas demo:

```text
demo
centro-kine
estudio-estetica
consultora-pro
wellness-studio
```

El cliente demo esta inscrito en las 5 empresas y tiene 5 reservas de ejemplo en su agenda global.

## Hacia donde vamos

Prioridades inmediatas:

- completar el flujo de reserva marketplace desde perfil de empresa.
- crear dashboard empresa completo sobre PostgreSQL.
- terminar CRUD de empresa para disciplinas, servicios, profesionales, disponibilidad y bloqueos.
- retirar componentes React muertos heredados de la etapa transicional.
- mejorar aislamiento tenant con pruebas automaticas.
- agregar seeds y pruebas de humo repetibles.
- preparar dockerizacion y modo produccion.

Prioridades pausadas:

- Mercado Pago Checkout Pro.
- notificaciones transaccionales por correo.
- integracion WhatsApp.
- pagos, facturacion y comisiones marketplace.

Estas integraciones se retomaran cuando el flujo principal de marketplace y reservas este estable.
