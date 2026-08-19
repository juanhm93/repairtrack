# SPEC-01 — Tickets de reparación

## Objetivo

Cerrar el PRD §6.2: el usuario registra un equipo, lo mueve de estado con una nota opcional y ve el historial (timeline) de ese ticket. Los clientes se crean o reutilizan al crear el ticket; no hay módulo de clientes.

Al terminar, create/show/cambio de estado están usables en el frontend. El listado filtrable es el spec 04.

Todo es **por persona**: un ticket y un cliente pertenecen al `User` que los creó. No hay `Brand` ni cuenta de taller.

## Dependencias

- Auth Fortify ya existente (registro, login, verificación). El registro **no** se extiende
- `AppLayout` + Wayfinder
- Sidebar: [`resources/js/components/AppSidebar.vue`](../../resources/js/components/AppSidebar.vue)
- Seeder actual: user `test@example.com` en [`DatabaseSeeder`](../../database/seeders/DatabaseSeeder.php)

## Alcance

- Tablas `customers`, `repair_tickets`, `ticket_status_history`, `ticket_photos`
- Enum `TicketStatus` con los 7 estados del PRD
- `TicketService`: crear ticket (find-or-create customer), cambiar estado, escribir historial, guardar fotos
- Catálogo curado de marcas/modelos (`config/devices.php`) + historial del técnico en el formulario
- Número de serie opcional
- Dictado por voz al problema reportado (solo transcripción, sin audio)
- Hasta 5 fotos del equipo en disco `public` (`php artisan storage:link`)
- Páginas autenticadas: crear ticket y ver ticket (datos + fotos + timeline + cambio de estado)
- Nav “Nuevo ticket”
- Factory, seeder y tests

## Fuera de alcance

- Envío de correo al crear o cambiar estado (spec 02)
- Ruta pública `/t/{token}` (spec 03); este spec sí genera y persiste `public_token`
- Dashboard con filtros y atrasados (spec 04)
- Tabla `brands`, `brand_id`, settings de marca (spec 05)
- Vista dedicada de historial por cliente (PRD §8)
- Cuentas compartidas de un local, visibilidad cruzada o asignación de ticket (PRD §8)
- Cobros / costos reales más allá del `estimated_cost` del ticket
- Catálogo exhaustivo de SKUs o API externa de modelos
- Guardar el audio de la nota de voz
- Webcam en escritorio (`getUserMedia`)
- Fotos o número de serie en la vista pública `/t/{token}` (spec 03)
- Editar fotos después de crear el ticket

## Modelo de datos

### Enum `App\Enums\TicketStatus`

Backed string, labels en español:

| Value | Label |
|-------|--------|
| `received` | Recibido |
| `in_review` | En revisión |
| `in_repair` | En reparación |
| `waiting_approval` | Esperando aprobación/repuesto |
| `ready` | Listo para entregar |
| `delivered` | Entregado |
| `not_repairable` | No reparable / Cancelado |

Helpers útiles en el enum (no son specs aparte): `label(): string`, `isTerminal(): bool` (`delivered`, `not_repairable`), `isOpen(): bool` (el resto).

No se fuerza una máquina de estados rígida: el usuario puede pasar a cualquier estado distinto del actual. `not_repairable` es un estado alterno, no un “paso” del flujo.

### `customers`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id` | Scoped al usuario dueño |
| `name` | string | |
| `phone` | string, nullable | |
| `email` | string | Destinatario de correos (spec 02) |
| `created_at` / `updated_at` | timestamps | |

Unique compuesto: `['user_id', 'email']` — un cliente se reutiliza por correo **dentro del mismo usuario**.

### `repair_tickets`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id` | Dueño del ticket |
| `customer_id` | FK `customers.id` | |
| `public_token` | string, unique | Token opaco para la vista pública (spec 03) |
| `device_type` | string | Ej. celular, tablet, laptop, pc_desktop, consola, otro |
| `brand` | string, nullable | Sugerido (catálogo + historial); texto libre |
| `model` | string, nullable | Sugerido (filtrado por marca); texto libre |
| `serial_number` | string, nullable | IMEI / número de serie; interno, no va a spec 03 |
| `reported_issue` | text | Problema reportado (también se puede dictar) |
| `estimated_cost` | decimal(10,2), nullable | |
| `received_at` | date | Fecha de recepción |
| `estimated_delivery_at` | date, nullable | Fecha estimada de entrega |
| `status` | string | Valor de `TicketStatus` |
| `created_at` / `updated_at` | timestamps | |

Índices: `user_id`, `status`, `received_at`, `estimated_delivery_at`, `customer_id`.

`public_token`: string aleatorio URL-safe (p.ej. 32 chars `Str::random(32)`), único, generado al crear. No es el id.

### `ticket_photos`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `repair_ticket_id` | FK `repair_tickets.id` | cascade delete |
| `path` | string | Relativo al disco `public` |
| `sort_order` | unsignedTinyInteger | 0–4 |
| `created_at` / `updated_at` | timestamps | |

Hasta 5 fotos por ticket. Archivos en `tickets/{user_id}/{ticket_id}/{uuid}.{ext}` en el disco `public`. El JSON de Inertia expone `url`, no `path`.

Requisito local: `php artisan storage:link` (enlace `public/storage` → `storage/app/public`, ya definido en [`config/filesystems.php`](../../config/filesystems.php)). El script `composer setup` lo ejecuta.

### Catálogo de equipos

[`config/devices.php`](../../config/devices.php): marcas y modelos populares **por `device_type`**. No es un catálogo exhaustivo. En create se pasa:

- `deviceCatalog` — el config
- `deviceHistory` — `distinct device_type, brand, model` de los tickets **del usuario autenticado**

El frontend mergea historial + catálogo. Marca y modelo siguen siendo texto libre.

### `ticket_status_history`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `repair_ticket_id` | FK `repair_tickets.id` | cascade delete |
| `from_status` | string, nullable | `null` en el evento de creación |
| `to_status` | string | |
| `note` | text, nullable | Ej. “esperando pantalla” |
| `changed_by` | FK `users.id`, nullable | Quién lo cambió; `nullOnDelete` |
| `created_at` | timestamp | Sin `updated_at` (append-only) |

### Relaciones

- `User` hasMany `Customer`, hasMany `RepairTicket`
- `Customer` belongsTo `User`, hasMany `RepairTicket`
- `RepairTicket` belongsTo `User`, belongsTo `Customer`, hasMany `TicketStatusHistory`, hasMany `TicketPhoto`
- `TicketStatusHistory` belongsTo `RepairTicket`, belongsTo `User` (`changed_by`)
- `TicketPhoto` belongsTo `RepairTicket`

Casts en `RepairTicket`: `status` → `TicketStatus`, fechas → `date`, `estimated_cost` → `decimal:2`.

## Backend

### Servicio — `app/Services/TicketService.php`

Toda la mutación de tickets pasa por aquí.

```
create(User $actor, array $data, array $photos = []): RepairTicket
  - find-or-create Customer por (user_id, email)
    - si existe: actualizar name/phone si vinieron más recientes
  - crear RepairTicket con user_id del actor, status received, public_token nuevo
  - escribir primer TicketStatusHistory (from=null, to=received, note opcional de recepción)
  - guardar fotos (máx. 5) en disco `public` con nombre UUID
  - todo en transacción

changeStatus(RepairTicket $ticket, User $actor, TicketStatus $to, ?string $note): RepairTicket
  - no-op / validación si $to === status actual
  - actualizar ticket.status
  - append history (from, to, note, changed_by)
  - transacción
```

No envía notificaciones. Spec 02 engancha este servicio.

### Scoping

- Policy `RepairTicketPolicy`: `view` / `update` solo si `ticket.user_id === user.id`
- Queries del controller siempre `RepairTicket::query()->where('user_id', $user->id)`
- Un usuario no puede ver ni mutar tickets de otro (404/403)

### Controller — `app/Http/Controllers/TicketController.php`

```
create  → Inertia tickets/Create (customers, deviceCatalog, deviceHistory)
store   → TicketService::create → redirect tickets.show + toast
show    → Inertia tickets/Show (ticket + customer + history.changedBy + photos)
updateStatus → TicketService::changeStatus → back + toast
```

No hay `index` aquí (spec 04). No hay `destroy` en el MVP.

### Form Requests

`StoreTicketRequest`:

- Cliente: `customer_name` required, `customer_phone` nullable, `customer_email` required|email
- Equipo: `device_type` required, `brand` nullable, `model` nullable, `serial_number` nullable|max:255, `reported_issue` required
- `photos` nullable|array|max:5; `photos.*` image|mimes:jpeg,jpg,png,webp|max:4096
- `estimated_cost` nullable|numeric|min:0
- `received_at` required|date
- `estimated_delivery_at` nullable|date|after_or_equal:received_at

`UpdateTicketStatusRequest`:

- `status` required, valor de `TicketStatus`
- `note` nullable|string|max:1000

### Rutas

En [`routes/web.php`](../../routes/web.php), grupo `auth` + `verified`:

```
GET   /tickets/create           tickets.create
POST  /tickets                  tickets.store
GET   /tickets/{ticket}         tickets.show
PATCH /tickets/{ticket}/status  tickets.status.update
```

Route model binding scoped al usuario autenticado. Ticket de otro user → 404.

## Frontend

### Tipos TS

Nuevo `resources/js/types/ticket.ts` (exportado desde `types/index.ts`):

```
TicketStatus = 'received' | 'in_review' | 'in_repair' | 'waiting_approval' | 'ready' | 'delivered' | 'not_repairable'

Customer = { id, name, phone, email }

TicketStatusHistoryItem = { id, from_status, to_status, note, changed_by: { id, name } | null, created_at }

TicketPhoto = { id, url, sort_order }

RepairTicket = {
  id, public_token, device_type, brand, model, serial_number, reported_issue,
  estimated_cost, received_at, estimated_delivery_at, status,
  customer: Customer,
  history: TicketStatusHistoryItem[],
  photos: TicketPhoto[]
}

DeviceCatalog = Record<device_type, Record<brand, model[]>>
DeviceHistoryItem = { device_type, brand, model }
```

### `resources/js/pages/tickets/Create.vue`

- `AppLayout`, breadcrumb Dashboard → Nuevo ticket
- Form: datos del cliente, tipo/marca/modelo (autocomplete), número de serie, problema (textarea + dictado), fotos, costo estimado, fecha recepción, fecha estimada de entrega
- `device_type`: select con valores `celular`, `tablet`, `laptop`, `pc_desktop`, `consola`, `otro` (cubre el filtro del spec 04)
- Marca y modelo: `SuggestInput` con sugerencias del catálogo filtradas por tipo (modelos por marca) mergeadas con `deviceHistory`; texto libre si no hay match
- Problema reportado: botón de dictado (Web Speech API, `es-DO` / `es`). Si el navegador no soporta, toast; el textarea sigue siendo el camino principal. El texto dictado se añade al campo. No se guarda audio
- Fotos: hasta 5. Botón “Tomar foto” (`capture="environment"`, abre cámara en móvil) y “Elegir de galería” (sin `capture`). Previews con quitar. En escritorio `capture` se ignora (file picker)
- Submit → `tickets.store` (multipart si hay fotos)

### `resources/js/pages/tickets/Show.vue`

- Breadcrumb Dashboard → Ticket #{id}
- Bloque de cliente y equipo (incluye número de serie si existe)
- Galería de fotos del equipo (thumbnails con `url`)
- Badge del estado actual (label en español)
- Form compacto: select de nuevo estado + nota opcional + submit
- Timeline de `history` ordenada cronológicamente (creación primero)
- No mostrar `public_token` en grande; un texto secundario “Link público (próximamente)” es suficiente. Spec 03 lo vuelve real.

### Nav

En `AppSidebar.vue`, además de Dashboard:

- “Nuevo ticket” → `tickets.create`

El listado vive en el dashboard (spec 04); no agregar un ítem “Tickets” vacío.

## Factory y seeder

- `CustomerFactory`: name, phone, email; requiere `user_id`
- `RepairTicketFactory`: equipo, fechas, status `received`, `public_token`; states por status (`inReview()`, `ready()`, `delivered()`, `overdue()` con `estimated_delivery_at` en el pasado)
- Al crear un ticket por factory, generar el history inicial (`received`) para que Show no quede vacío
- `TicketSeeder` (llamado desde `DatabaseSeeder`): para el user `test@example.com`, varios customers y tickets en distintos estados (al menos uno `received`, uno `in_repair`, uno `waiting_approval`, uno `ready`, uno `delivered`, uno atrasado no entregado)

## Tests

`tests/Feature/Tickets/` (o archivos equivalentes), `RefreshDatabase`:

- Guest no accede a create/show
- User autenticado crea ticket: persiste customer, ticket `received`, `public_token`, primer history, `user_id` del actor
- Create incluye `deviceCatalog` y `deviceHistory` solo de tickets del usuario (no de otro)
- Create persiste `serial_number` opcional
- Create con fotos: persiste hasta 5 en disco `public`; más de 5 o mime inválido → 422
- Mismo email en el mismo usuario reutiliza el customer (no duplica)
- Mismo email en otro usuario crea otro customer
- Show carga ticket + customer + history
- Cambio de estado persiste `status`, append history con nota y `changed_by`
- No se puede cambiar al mismo estado (422)
- User A recibe 404 al ver/cambiar ticket de user B

No testear envío de mail ni ruta `/t/{token}`.

## Criterios de aceptación

- Desde el sidebar se puede abrir el formulario y crear un ticket
- El ticket se ve con timeline y se le puede cambiar el estado con nota
- El cliente se reutiliza por email dentro del mismo usuario
- Cada ticket tiene `public_token` único listo para el spec 03
- El seeder deja tickets de demo en varios estados para `test@example.com`
- Un usuario no ve tickets de otro
- No hay `brands` ni `brand_id`
- Marca/modelo se sugieren desde catálogo + historial propio; se puede escribir texto libre
- Se puede guardar número de serie y hasta 5 fotos del equipo
- El problema reportado se puede dictar al textarea (sin persistir audio)
- No se envía correo y no existe aún el listado filtrable ni la página pública
- Fotos y serie no se exponen en spec 03
