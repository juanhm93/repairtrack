# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

RepairTrack: SaaS Laravel 13 + Vue 3 + Inertia para que **un técnico individual** gestione sus reparaciones. Multi-tenancy por `user_id` (una cuenta = un técnico, no un taller). El código, la UI y los commits están en **español**; los docblocks y comentarios de código, en inglés.

## Comandos

PHP 8.3, Node 22 (`.nvmrc`).

```bash
composer setup          # primer arranque: install + .env + key + migrate + storage:link + npm build
composer dev            # php artisan dev (servidor + Vite)
composer test           # GATE COMPLETO: config:clear + pint --test + phpstan + artisan test
composer ci:check       # lo que corre CI: eslint + prettier + vue-tsc + composer test
composer lint           # pint --parallel (escribe)
composer types:check    # phpstan analyse (nivel 7)
npm run dev             # solo Vite
npm run types:check     # vue-tsc --noEmit
```

Tests individuales:

```bash
php artisan test tests/Feature/Tickets/TicketTest.php
php artisan test --filter=test_tecnico_no_ve_tickets_de_otro
```

`composer test` ya incluye Pint y PHPStan, así que es el único comando necesario antes de dar por cerrado un cambio. Los tests corren con SQLite `:memory:`, `QUEUE_CONNECTION=sync` y `MAIL_MAILER=array` (`phpunit.xml`).

## Arquitectura

El big picture completo está en **[docs/architecture.md](docs/architecture.md)** — leerlo antes de un cambio que cruce capas. Lo esencial:

```
Ruta → Form Request (valida + normaliza) → Controller delgado (Gate::authorize)
     → app/Services/* (DB::transaction, única puerta de escritura al dominio)
     → Eloquent + Enums → Inertia::render(props) + Inertia::flash('toast')
```

Piezas que requieren leer varios archivos para entender:

- **Multi-tenancy en tres capas.** `RepairTicket::resolveRouteBinding()` filtra por `Auth::id()` (devuelve **404**, no 403), `RepairTicketPolicy` autoriza vía `Gate::authorize()`, y cada servicio añade `where('user_id', ...)`. No hay global scope: **toda query nueva sobre `RepairTicket` o `Customer` debe scopear a mano**.
- **Notificaciones como máquina de estados.** `TicketNotificationService` escribe un `TicketNotification{status: Queued}` *antes* de enviar; los listeners `MarkTicketNotificationSent` / `MarkTicketNotificationFailed` (registrados en `AppServiceProvider`) lo reconcilian con `where('status', Queued)`, lo que los vuelve idempotentes. El envío ocurre **fuera** de la transacción, a propósito.
- **Enums como contrato PHP ↔ TS.** `app/Enums/TicketStatus.php` tiene los estados, las etiquetas en español, `isTerminal()`/`isOpen()` y `options()`. Su espejo es `resources/js/types/ticket.ts`: **cambiar uno obliga a cambiar el otro**.
- **Layouts por convención**, no por página: `resources/js/app.ts` mapea `Welcome`/`public/*` → sin layout, `auth/*` → `AuthLayout`, `settings/*` → `[AppLayout, SettingsLayout]`, el resto → `AppLayout`.
- **`resources/js/routes/` y `resources/js/actions/` son generados por Wayfinder.** Nunca editarlos; se regeneran con `npm run dev`/`build` al cambiar rutas o controladores.
- **Ver / editar datos / cambiar estado son tres endpoints distintos** (`show`, `PUT update`, `PATCH status`). Es una decisión del PRD §6.2; no fusionarlos.

## Convenciones obligatorias

De `docs/specs/README.md`, y respetadas sin excepción en el código actual:

- Lógica de negocio en `app/Services/`; enums en `app/Enums/`. Controladores delgados, validación y normalización en Form Requests que exponen getters tipados (`->payload()`, `->filters()`, `->status()`).
- Modelos con atributos PHP de Laravel 13 (`#[Fillable]`, `#[Hidden]`, `#[Appends]`) en vez de propiedades, y docblocks `@property` completos — es lo que sostiene PHPStan nivel 7. **No bajar el nivel por código nuevo.**
- Toasts siempre con `Inertia::flash('toast', ['type' => ..., 'message' => ...])`; el cliente los consume en `resources/js/lib/flashToast.ts`. No inventar otro canal de feedback.
- UI autenticada con `AppLayout` + helpers de Wayfinder; primitivas de `resources/js/components/ui/` (shadcn-vue sobre reka-ui, `components.json`). Copy en español.
- Fechas: `CarbonImmutable` globalmente (`AppServiceProvider::configureDefaults()`).
- Tests en `tests/Feature/` con `RefreshDatabase`, extendiendo `Tests\TestCase` (ya hace `withoutVite()` y ofrece `skipUnlessFortifyHas()`).
- Un feature nuevo se cierra completo: modelo(s) + enum, servicio, controlador delgado + requests + rutas, factory/seeder, páginas Inertia y feature tests.

## Flujo de trabajo del proyecto

`docs/prds/repairtrack-prd.md` define el producto; `docs/specs/` lo parte en features con un orden declarado. Estado: specs 01, 01.1, 02, 03 cerrados; 04 (dashboard) entregado recortado — sin filtros ni indicador de atraso del PRD §6.5; **05 (marca personal) y 06 (cobro manual) sin empezar**. Un spec no se implementa hasta que su dependencia está cerrada.

## Gotchas de este repo

- **El deploy es FTP a hosting compartido, sin shell** (`.github/workflows/deploy.yml`). De ahí que las migraciones se disparen por HTTP desde `/admin/migrate` (`ApplicationMaintenanceService`), y que `OwnerController` consulte `Schema::hasColumn()` antes de usar `is_admin`. `docs/`, `tests/` y `public/uploads/` quedan excluidos del deploy.
- **No crear directorios reales en `public/` que choquen con rutas.** `public/tickets/` tapaba la ruta `/tickets` (403 en Apache/LiteSpeed); la migración `2026_08_19_181300_move_ticket_photos_out_of_public_tickets` los movió a `public/uploads/tickets/`.
- **`TicketSeeder` está roto como está:** busca `test@example.com` con `firstOrFail()`, pero `DatabaseSeeder` crea `juanhm93@gmail.com` y la llamada al seeder está comentada. Hay que alinear el email antes de usarlo.
- **El correo es sincrónico:** `TicketStatusNotification` no implementa `ShouldQueue` aunque el PRD §6.3 pida cola. Si se añade, hace falta un worker en el servidor y el `try/catch` de `TicketNotificationService` deja de capturar el fallo (lo cubre `MarkTicketNotificationFailed`).
- **Las cookies `appearance` y `sidebar_state` están excluidas de `encryptCookies` a propósito** (`bootstrap/app.php`): Blade las lee en `app.blade.php` para pintar el tema antes de montar Vue. No cifrarlas.
- **Un ticket ajeno responde 404, no 403** — el route binding filtra antes de la policy. Los tests deben esperar 404.
- `PublicTicketService::pageProps()` construye el array de props **a mano**; nunca serializar el modelo en la vista pública.
- Las fotos se sirven desde disco público (`uploads/tickets/{user}/{ticket}/{uuid}.ext`): la protección es el UUID, no autorización.
- `.env.example` usa `DB_CONNECTION=sqlite` y `MAIL_MAILER=log`, mientras el PRD §9 proyecta MySQL + Redis.
