# Versiones

Historial resumido de la evolucion del proyecto `sistema_de_reserva`.

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
