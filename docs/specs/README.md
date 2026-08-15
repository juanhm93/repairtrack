# Specs — RepairTrack

Los specs de este directorio traducen el [PRD](../prds/repairtrack-prd.md) en features implementables. Un spec no es una mini-tarea (crear una migración, un campo, un botón): es una idea cerrada, lista para usarse de punta a punta.

## Recorte de esta iteración

La primera entrega corre en un subdominio, **por usuario**, no por negocio:

- Tickets, clientes e historial pertenecen al `User` autenticado
- Correos y vista pública usan la marca de la app (`config('app.name')` + `mail.from`), no logo/colores por taller
- No hay tabla `businesses`, ni `business_id`, ni settings de marca

El cobro manual (PRD §6.6) es el [spec 06](06-manual-billing.md): se implementa **después de 01–04**, antes del branding. En el subdominio hay que poder cobrar; la marca del taller puede esperar.

El branding por negocio (PRD §6.1) queda en el [spec 05](05-business-branding.md) como **siguiente iteración**. Ese spec es aditivo: no cambia la dueñez de los tickets.

## Criterio de recorte

Cada spec entrega un feature completo:

- Modelo(s), relaciones y (si aplica) enum
- Servicio con la lógica de negocio
- Controlador delgado + Form Requests + rutas
- Factory y seeder para desarrollo local
- Páginas Inertia/Vue de esa idea
- Feature tests que demuestran que el feature está cerrado

Si al terminar el spec el frontend (o el siguiente spec) no puede usar el resultado, el recorte fue demasiado chico.

## Orden de implementación

```
01-repair-tickets  →  02-email-notifications
                   ↘  03-public-status
                   ↘  04-dashboard
                   ↘  06-manual-billing

05-business-branding   ← siguiente iteración, no bloquea el MVP en subdominio
```

| Spec | Idea | Depende de | Iteración |
|------|------|------------|-----------|
| [01-repair-tickets](01-repair-tickets.md) | PRD §6.2 Gestión de tickets | Auth Fortify | Ahora |
| [02-email-notifications](02-email-notifications.md) | PRD §6.3 Correos (sin marca por taller) | 01 | Ahora |
| [03-public-status](03-public-status.md) | PRD §6.4 Vista pública | 01 (02 para el link del correo) | Ahora |
| [04-dashboard](04-dashboard.md) | PRD §6.5 Dashboard | 01 | Ahora |
| [06-manual-billing](06-manual-billing.md) | PRD §6.6 Cobro manual PayPal/Binance | Auth + 01/04 (middleware) | Ahora (después de 01–04) |
| [05-business-branding](05-business-branding.md) | PRD §6.1 Marca por negocio | 01–04 | Después |

No se implementan en paralelo features que dependan de un spec anterior incompleto.

## Plantilla

Todo spec nuevo usa estas secciones, en este orden:

1. **Objetivo** — qué idea del PRD cierra
2. **Dependencias** — specs previos y piezas del repo
3. **Alcance** — qué entra
4. **Fuera de alcance** — qué no entra
5. **Modelo de datos** — tablas, campos, relaciones, enums
6. **Backend** — servicio, controlador, requests, rutas, scopes
7. **Frontend** — páginas, nav, tipos TS
8. **Factory y seeder**
9. **Tests**
10. **Criterios de aceptación**

## Convenciones técnicas

Heredadas por los specs 01–04 y 06:

- Lógica de negocio en `app/Services/` (carpeta nueva)
- Enums en `app/Enums/`
- Controllers delgados; validación en Form Requests (mismo patrón que Settings)
- Queries autenticadas siempre scoped al `user_id` del usuario autenticado
- UI autenticada con `AppLayout` + Wayfinder; copy en español
- Toasts con `Inertia::flash('toast', …)` como en `ProfileController`
- Tests en `tests/Feature/` con `RefreshDatabase`
- PHPStan level 7 y Pint; no relajar el nivel por código nuevo

## Fuera de alcance de esta iteración

- Branding por negocio (logo, colores, remitente) — spec 05
- Múltiples técnicos por negocio con cola propia (PRD §8)
- SMS / WhatsApp
- Pasarela automática (Stripe) o cobros del taller a su cliente
- Dominio propio por negocio
- Historial de reparaciones por cliente como vista dedicada
