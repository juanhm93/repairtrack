# SPEC-04 — Dashboard

## Objetivo

Cerrar el PRD §6.5: el usuario ve todos **sus** trabajos en un listado usable, con foco en los pendientes, filtros (estado, fechas, tipo de equipo, cliente) e indicador visual de tickets atrasados.

Reemplaza el placeholder de [`resources/js/pages/Dashboard.vue`](../../resources/js/pages/Dashboard.vue).

## Dependencias

- Spec 01: `RepairTicket`, `Customer`, `TicketStatus`, Create/Show
- Ruta actual: `GET /dashboard` es Inertia estático en [`routes/web.php`](../../routes/web.php)
- Tests existentes: [`tests/Feature/DashboardTest.php`](../../tests/Feature/DashboardTest.php) (guest redirect + acceso autenticado) — deben seguir pasando
- Sidebar ya tiene el ítem Dashboard (spec 01 agregó “Nuevo ticket”)

## Alcance

- Query de listado scoped al `user_id` autenticado, en servicio
- Filtros del PRD
- Flag `is_overdue` por ticket
- `DashboardController` en lugar del `Route::inertia`
- Página dashboard real: lista, filtros, links a show/create
- Tests de filtrado, scoping y atrasados

## Fuera de alcance

- Branding / nombre del técnico en el dashboard (spec 05)
- Gráficas, KPIs de ingresos o métricas de v2
- Cola compartida de un local o asignación entre técnicos (PRD §8)
- Historial dedicado por cliente (PRD §8)
- Bulk actions (cambiar estado a varios tickets)
- Paginación infinita / live updates; paginación offset estándar sí entra

## Modelo de datos

Sin tablas nuevas.

**Atrasado (`is_overdue`):** `estimated_delivery_at` no es null, es estrictamente anterior a hoy (timezone de la app), y el status **no** es `ready` ni `delivered` ni `not_repairable`.

Un ticket `ready` con fecha pasada no se marca atrasado: ya está listo para entregar. `not_repairable` tampoco.

## Backend

### Servicio — `app/Services/TicketQueryService.php`

Métodos de lectura aparte de las mutaciones del spec 01:

```
paginateForDashboard(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
```

Filtros aceptados (todos opcionales):

| Query param | Comportamiento |
|-------------|----------------|
| `status` | Exact match a un valor de `TicketStatus` |
| `received_from` / `received_to` | Rango inclusivo sobre `received_at` |
| `delivery_from` / `delivery_to` | Rango inclusivo sobre `estimated_delivery_at` |
| `device_type` | Exact match (`celular`, `tablet`, `laptop`, `pc_desktop`, `consola`, `otro`) |
| `q` | Búsqueda LIKE sobre `customers.name`, `customers.email`, `customers.phone` |

Default (sin `status`): foco en pendientes = `status` not in (`delivered`). El usuario puede filtrar explícitamente a `delivered` para verlos.

Orden default: atrasados primero, luego `estimated_delivery_at` asc (nulls last), luego `received_at` desc.

Cada item del paginator incluye relación `customer` y el atributo calculado `is_overdue` (accessor en el modelo o map en el service).

Siempre `where('user_id', $user->id)`.

### Controller — `app/Http/Controllers/DashboardController.php`

```
index(Request $request): Response
  - validar filtros (Form Request liviano o inline)
  - TicketQueryService::paginateForDashboard
  - Inertia::render('Dashboard', { tickets, filters, statusOptions })
```

`statusOptions`: lista `{ value, label }` del enum, para el select.

Validación de filtros: `status` nullable|enum, fechas `date`, `device_type` nullable|in:celular,tablet,laptop,pc_desktop,consola,otro, `q` nullable|string|max:100. Fechas `*_to` after_or_equal a `*_from` cuando ambas vienen.

### Rutas

Reemplazar en `web.php`:

```
// antes
Route::inertia('dashboard', 'Dashboard')->name('dashboard');

// después
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
```

Mismo nombre de ruta (`dashboard`) para no romper Fortify home, Wayfinder y `DashboardTest`.

## Frontend

### `resources/js/pages/Dashboard.vue`

Quitar `PlaceholderPattern` y las tres cards vacías.

Layout:

- Heading: “Trabajos” + CTA “Nuevo ticket” → `tickets.create`
- Barra de filtros (GET, Inertia): estado, tipo de equipo, rango recepción, rango entrega estimada, búsqueda de cliente
- Controles “Limpiar filtros” y submit (o filtros on change con `router.get`)
- Tabla o lista:
  - Cliente
  - Equipo (tipo · marca · modelo)
  - Estado (badge, label ES)
  - Recibido
  - Entrega estimada
  - Indicador atrasado (badge “Atrasado” / row highlight) cuando `is_overdue`
  - Click / link de la fila → `tickets.show`
- Paginación Laravel estándar
- Empty state: “No hay tickets” + CTA crear, o “Nada coincide con los filtros”

Foco visual: los pendientes son el default; no hace falta un toggle extra si el default ya excluye `delivered`.

### Tipos TS

Extender `RepairTicket` (o un `DashboardTicket`) con `is_overdue: boolean` y `customer` sin history. Props de la página: paginator Inertia + `filters` actuales + `statusOptions`.

## Factory y seeder

Sin seeder nuevo. El `TicketSeeder` del spec 01 ya incluye un ticket atrasado (`overdue()`). El dashboard debe mostrarlo con el badge al loguearse como `test@example.com`.

## Tests

Actualizar/extender `DashboardTest`:

- Guest sigue redirigido a login
- User autenticado ve 200 y el component `Dashboard`
- Solo ve tickets de su `user_id`
- Default: no lista `delivered` (sí lista `ready`, `in_repair`, etc.)
- `?status=delivered` sí lista entregados
- `?q=` filtra por nombre/email de customer
- `?device_type=celular` filtra
- Rangos de fecha filtran
- Ticket con `estimated_delivery_at` ayer y status `in_repair` tiene `is_overdue: true`
- Ticket `ready` con fecha pasada tiene `is_overdue: false`
- Ticket de otro usuario no aparece aunque coincida el filtro

## Criterios de aceptación

- El dashboard deja de ser placeholder y muestra los tickets del usuario
- Por defecto se ven los no entregados
- Los cinco filtros del PRD funcionan
- Los atrasados se distinguen a simple vista
- Desde el listado se abre el show (spec 01) y se crea un ticket nuevo
- Un usuario no ve trabajos de otro
- `DashboardTest` original (guest + auth access) sigue verde
