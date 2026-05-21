# Frontend SaaS Preview

Nueva base frontend para migrar `sistema_de_reserva` hacia una experiencia moderna sin romper el PHP actual.

## Stack

- React 19 + TypeScript
- Vite
- Tailwind CSS v4
- Framer Motion
- TanStack React Query
- Zustand
- React Hook Form + Zod
- FullCalendar React
- Componentes UI propios con estilo shadcn/ui

## Ejecutar

```powershell
cd C:\Users\x13\sistema_de_reserva\frontend
npm install
npm run dev
```

Abrir:

```text
http://127.0.0.1:5173/
```

## Enfoque

Esta app es una migracion incremental. Primero reemplaza la experiencia visual y de interaccion; despues se conectan endpoints reales del sistema PHP o se migra el backend a Node/Nest + PostgreSQL + Prisma.

La app incluye datos demo para revisar UI/UX sin depender de sesiones PHP.
