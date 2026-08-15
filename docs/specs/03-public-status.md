# SPEC-03 — Vista pública de status

## Objetivo

Cerrar el PRD §6.4 **sin marca personal**: el cliente final abre un link único (sin login) y ve el estado de su equipo y el timeline. No ve datos de otros clientes ni información del usuario dueño del ticket.

La página se presenta como RepairTrack (nombre de la app). Logo/colores del técnico son el spec 05.

## Dependencias

- Spec 01: `RepairTicket.public_token`, customer, status, history
- Spec 02: el correo ya apunta a `route('public.tickets.show', $token)` → `GET /t/{token}`
- Layout: `Welcome` y `auth/*` muestran cómo renderizar sin `AppLayout` ([`resources/js/app.ts`](../../resources/js/app.ts))

## Alcance

- Ruta pública por token
- Controller que resuelve el ticket y arma props seguros
- Página Inertia `public/TicketStatus.vue` sin shell autenticado
- 404 si el token no existe
- Tests de acceso, props y aislamiento

## Fuera de alcance

- Marca personal (logo, colores, nombre del técnico) — spec 05
- Login o cuenta para el cliente (PRD §4)
- Aprobar costo / responder desde la página pública (`waiting_approval` es solo informativo)
- Buscador público por teléfono/email
- Rate limiting avanzado anti-enumeración más allá de token opaco de 32 chars
- Editar el ticket desde esta vista

## Modelo de datos

Sin tablas nuevas. Usa `repair_tickets.public_token` (único, opaco) del spec 01.

Lookup: `RepairTicket::query()->where('public_token', $token)->firstOrFail()`.

No usar el id numérico en la URL.

## Backend

### Controller — `app/Http/Controllers/PublicTicketController.php`

```
show(string $token): Response
  - buscar ticket por public_token
  - 404 si no existe
  - eager load: customer, history (sin changedBy, o sin exponerlo)
  - Inertia::render('public/TicketStatus', [...props seguros])
```

Sin middleware `auth` / `verified`. No cargar el `User` dueño en los props.

### Props seguros

Incluir solo:

```
app: { name }               // config('app.name')
ticket: {
  device_type, brand, model,
  status, status_label,
  received_at, estimated_delivery_at,
  history: [{ to_status, to_status_label, note, created_at }]
}
customer: { name }
```

Excluir explícitamente:

- `public_token` no hace falta en la página (ya está en la URL)
- `estimated_cost`
- `customer.email`, `customer.phone`
- `customer.id`, `ticket.id`, `user_id`
- `changed_by` (nombre del técnico)
- Email o nombre del dueño del ticket
- Cualquier otro ticket o cliente

`history` en orden cronológico. `from_status` puede omitirse; el cliente ve “qué pasó” (`to_status` + nota + fecha).

Los props deben poder crecer en spec 05 con un bloque `branding` opcional sin romper esta página.

### Rutas

En [`routes/web.php`](../../routes/web.php), **fuera** del grupo autenticado:

```
GET /t/{token}  → PublicTicketController@show  (public.tickets.show)
```

`{token}`: constraint alfanumérico del mismo alfabeto que `Str::random`, longitud razonable.

Si spec 02 ya registró el named route, este spec solo implementa el controller.

### Layout resolution

En `app.ts`, páginas `public/*` → **sin** `AppLayout` (igual que `Welcome`). No mostrar sidebar ni user menu. Un header mínimo con el nombre de la app va en la propia página.

## Frontend

### `resources/js/pages/public/TicketStatus.vue`

Página standalone:

- `<Head>`: “Status de tu equipo — {app.name}”
- Header: nombre de la app (RepairTrack)
- Saludo breve con `customer.name`
- Datos del equipo (tipo, marca, modelo)
- Estado actual destacado (label ES)
- Fechas de recepción y entrega estimada (si hay)
- Timeline de estados (fecha, label, nota)
- Footer corto: “Consulta generada por {app.name}” — sin links al dashboard

No hay formularios. Un link discreto a la home es opcional.

Estilos: tokens de [`resources/css/tokens.css`](../../resources/css/tokens.css) y componentes UI (badge, etc.) sin el sidebar. No CSS variables de marca personal.

### Tipos TS

Tipo `PublicTicketPage` específico en `resources/js/types/ticket.ts` (no reciclar `RepairTicket` completo).

## Factory y seeder

Sin seeder nuevo. Los tickets de demo del spec 01 ya tienen `public_token`. `/t/{token}` de un ticket demo es la URL de prueba.

## Tests

`tests/Feature/PublicTicketTest.php` (o carpeta `Public/`):

- Token existente: 200, Inertia component `public/TicketStatus`
- Token inexistente: 404
- Guest (sin login) puede ver la página
- Assert de props: están `app.name`, status, history, nombre del cliente
- Assert de props: **no** están `estimated_cost`, `customer.email`, `customer.phone`, `user_id`, `changed_by`, ids internos
- Token del ticket A no revela datos del ticket B
- La ruta no redirige a login

## Criterios de aceptación

- Un cliente sin cuenta abre `/t/{token}` y entiende el estado de su equipo
- Ve el nombre de RepairTrack y el timeline; no ve marca del técnico
- No ve costo, contactos, dueño del ticket ni otros tickets
- Token inválido = 404
- El CTA del correo del spec 02 aterriza en esta página
- El usuario autenticado sigue usando Create/Show; esta vista no reemplaza el dashboard
