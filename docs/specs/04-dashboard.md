# SPEC-04 — Dashboard

## Objetivo

Cerrar el PRD §6.5 con otro recorte: el dashboard **no** es un segundo listado de tickets. El listado usable, con filtros y acciones, ya vive en `GET /tickets` (spec 01.1). Duplicar esa bandeja aquí no aporta.

El dashboard es la **vista de resumen** del técnico: a un lado los **3 tickets más recientes**; en el resto, **estadísticas del mes calendario actual** (tickets y clientes registrados, trabajos terminados, trabajos por terminar, y el mismo recuento por cada estado).

Reemplaza el placeholder de [`resources/js/pages/Dashboard.vue`](../../resources/js/pages/Dashboard.vue).

## Dependencias

- Spec 01: `RepairTicket`, `Customer`, `TicketStatus`, Create/Show
- Spec 01.1: listado CRUD en `GET /tickets` (ver / editar / borrar). Este dashboard **no** lo reemplaza ni lo replica
- Ruta actual: `GET /dashboard` es Inertia estático en [`routes/web.php`](../../routes/web.php)
- Tests existentes: [`tests/Feature/DashboardTest.php`](../../tests/Feature/DashboardTest.php) (guest redirect + acceso autenticado) — deben seguir pasando
- Sidebar ya tiene el ítem Dashboard (spec 01 agregó “Nuevo ticket”; spec 01.1 agrega “Tickets”)

## Alcance

- Snapshot de lectura scoped al `user_id` autenticado, en servicio
- Últimos 3 tickets del usuario (cualquier mes), con link a show
- KPIs del mes calendario actual: tickets y clientes registrados, terminados (`delivered`), por terminar (≠ `delivered`), y conteo por cada `TicketStatus`
- `DashboardController` en lugar del `Route::inertia`
- Página dashboard real: cards de stats + panel de recientes + CTAs a create e index
- Tests de scoping, recorte a 3, ventana del mes y desglose por estado

## Fuera de alcance

- Listado paginado, filtros (estado, fechas, tipo de equipo, cliente) y foco “solo pendientes” — eso es spec 01.1
- Selector de mes / histórico de meses anteriores; siempre es el mes calendario en curso
- Gráficas, KPIs de ingresos o métricas de v2
- Branding / nombre del técnico en el dashboard (spec 05)
- Cola compartida de un local o asignación entre técnicos (PRD §8)
- Historial dedicado por cliente (PRD §8)
- Bulk actions
- Live updates

## Modelo de datos

Sin tablas nuevas. Sin columnas nuevas.

**Ventana del mes:** primer día 00:00:00 hasta último día 23:59:59 del mes calendario actual, en la timezone de la app (`config('app.timezone')`). Un ticket entra en las estadísticas si su `received_at` cae en esa ventana. `received_at` es date: comparar por mes/año de esa fecha (equivalente a `whereYear` + `whereMonth`, o `whereBetween` sobre el rango del mes).

Tickets de meses anteriores **no** entran en ningún KPI, aunque sigan abiertos. Sí pueden aparecer en “últimos 3” si son los más recientes del usuario.

**Universo de KPIs:** tickets del usuario autenticado con `received_at` en el mes actual. Sobre ese mismo conjunto:

| KPI | Definición |
|-----|------------|
| Tickets del mes | `count` de tickets del universo, **cualquier** status |
| Clientes del mes | `count distinct customer_id` de esos tickets. Un cliente con 2 tickets del mes cuenta 1 vez |
| Terminados | subset con `status = delivered` |
| Por terminar | subset con `status != delivered` (incluye `ready`, `not_repairable`, etc.) |
| Por estado | un conteo por cada valor de `TicketStatus` sobre el mismo universo (los que no tengan tickets quedan en 0) |

“Por terminar” sigue el criterio pedido: distinto de entregado. No usar `isOpen()` aquí: `not_repairable` suma en “por terminar”.

Tickets del mes + clientes del mes + terminados + por terminar + desglose por estado son **el mismo mes y el mismo universo**. Terminados + por terminar = tickets del mes.

**Últimos 3 tickets:** no se recortan al mes. Son los 3 más recientes del usuario (`received_at` desc, `id` desc), con `customer`. Pueden ser de este mes o de meses previos.

## Backend

### Servicio — `app/Services/DashboardService.php`

Método de lectura (no mutaciones):

```
snapshotForUser(User $user): array
```

Siempre `where('user_id', $user->id)`.

Retorno (nombres orientativos):

```
{
  month: { year, month, label },          // p.ej. label “Agosto 2026”
  recentTickets: RepairTicket[],          // máximo 3, con customer
  stats: {
    tickets_count: int,                   // registrados en el mes, cualquier status
    customers_count: int,                 // clientes distintos de esos tickets
    completed_count: int,                 // status delivered
    pending_count: int,                   // status != delivered
    by_status: [                          // un ítem por cada TicketStatus::cases(), orden del enum
      { value, label, count }
    ]
  }
}
```

`recentTickets` no incluye history ni photos. `by_status` siempre tiene los 7 estados, aunque el count sea 0.

Una query (o un par) agrupando por `status` en la ventana del mes alcanza para todos los KPIs: `tickets_count` es la suma, `completed_count` es el bucket `delivered`, `pending_count` es la suma del resto, `customers_count` es un `distinct` aparte sobre el mismo universo.

### Controller — `app/Http/Controllers/DashboardController.php`

```
index(Request $request): Response
  - DashboardService::snapshotForUser($request->user())
  - Inertia::render('Dashboard', { month, recentTickets, stats })
```

Sin query params de filtro. Sin Form Request de listado.

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

Quitar `PlaceholderPattern` y las cards vacías.

Layout (md+: dos zonas; en mobile se apilan):

- Heading: “Dashboard” o “Resumen” + mes actual (`month.label`)
- CTAs: “Nuevo ticket” → `tickets.create`, “Ver todos” → `tickets.index`

**Zona de recientes (un lado):**

- Título: “Últimos tickets”
- Hasta 3 filas/cards: cliente, equipo (tipo · marca · modelo), estado (badge, label ES), recibido
- Click / link de la fila → `tickets.show`
- Empty: “Aún no hay tickets” + CTA crear

**Zona de estadísticas (el resto):**

Cards con el número grande y label en español. Mismo patrón para todas:

- **Tickets y clientes del mes** — dos cifras: tickets registrados y clientes distintos (cualquier status, siempre que se hayan registrado este mes)
- **Terminados** — trabajos del mes con status Entregado
- **Por terminar** — trabajos del mes con status distinto de Entregado
- **Una card (o fila compacta) por cada estado** del enum, con su `label` y `count` — Recibido, En revisión, En reparación, Esperando aprobación/repuesto, Listo para entregar, Entregado, No reparable / Cancelado

Si el mes no tiene tickets, las cards muestran `0`; no hace falta un empty state aparte de los ceros. Los recientes pueden seguir mostrando tickets de meses anteriores.

Las cards de stats no son filtros ni navegan a un listado prefiltrado (el index de spec 01.1 no está obligado a aceptar esos query params). Son solo lectura.

### Tipos TS

Props de la página: `month`, `recentTickets` (tickets con `customer`, sin history), `stats` como el retorno del servicio. Reutilizar `RepairTicket` / tipos de spec 01; no hace falta un tipo de listado paginado.

## Factory y seeder

Sin seeder nuevo. El `TicketSeeder` del spec 01 ya crea tickets de demo para `test@example.com`. Al loguearse, el dashboard debe mostrar hasta 3 de esos (los más recientes) y KPIs coherentes con cuántos tienen `received_at` en el mes actual.

## Tests

Actualizar/extender `DashboardTest`:

- Guest sigue redirigido a login
- User autenticado ve 200 y el component `Dashboard`
- Solo ve tickets de su `user_id` (recientes y stats)
- Recientes: máximo 3, orden `received_at` desc (el 4º más nuevo no aparece)
- Recientes incluyen un ticket de un mes anterior si es de los 3 más nuevos
- Ticket de otro usuario no aparece en recientes ni altera los counts
- `tickets_count` cuenta todos los del mes, cualquier status
- Ticket con `received_at` fuera del mes actual no suma a ningún KPI
- `customers_count`: dos tickets del mismo cliente en el mes cuentan 1 cliente y 2 tickets
- `completed_count` = tickets del mes con `delivered`
- `pending_count` = tickets del mes con status distinto de `delivered`
- `completed_count + pending_count === tickets_count`
- `by_status` tiene un ítem por cada valor del enum; la suma de counts es `tickets_count`
- Mes sin tickets: stats en 0 y `recentTickets` vacío si el usuario no tiene ninguno

## Criterios de aceptación

- El dashboard deja de ser placeholder y muestra un resumen, no una tabla filtrable
- Se ven los 3 tickets más recientes del usuario, con link al show
- Se ven tickets y clientes registrados en el mes (cualquier status)
- Se ven trabajos terminados del mes (`delivered`) y por terminar (≠ `delivered`)
- Cada estado del enum tiene su recuento del mes
- Todo KPI usa la misma ventana: mes calendario actual + `received_at`
- CTA a crear ticket y a `tickets.index`
- El dashboard no sustituye el index de tickets (spec 01.1)
- Un usuario no ve trabajos ni cifras de otro
- `DashboardTest` original (guest + auth access) sigue verde
