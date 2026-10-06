# RepairTrack — Arquitectura del sistema

> Big picture del sistema tal como está implementado hoy. Para el *qué* y el *por qué* de producto, ver el [PRD](prds/repairtrack-prd.md); para el detalle de cada feature, [docs/specs](specs/README.md).
>
> Última revisión: 2026-10-06 (commit `6514a21`).

## Contenido

1. [Qué es, en una frase](#1-qué-es-en-una-frase)
2. [Ciclo de una petición](#2-ciclo-de-una-petición-las-capas-reales)
3. [Tres superficies, tres niveles de confianza](#3-tres-superficies-tres-niveles-de-confianza)
4. [Modelo de dominio](#4-modelo-de-dominio)
5. [Multi-tenancy](#5-multi-tenancy-defensa-en-tres-capas)
6. [Flujo crítico: crear ticket → notificar](#6-flujo-crítico-crear-ticket--notificar-cliente)
7. [Arquitectura del frontend](#7-arquitectura-del-frontend)
8. [Autenticación y seguridad](#8-autenticación-y-seguridad)
9. [Infraestructura y despliegue](#9-infraestructura-y-despliegue)
10. [Estado real vs. PRD](#10-estado-real-vs-prd)
11. [Observaciones arquitectónicas](#11-observaciones-arquitectónicas)

---

## 1. Qué es, en una frase

SaaS Laravel 13 + Vue 3 + Inertia para que **un técnico individual** gestione sus reparaciones, con **multi-tenancy por `user_id`** (no por taller), notificaciones por correo al cliente y una **vista pública sin login** por token.

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3, Laravel ^13.17 |
| Auth | Laravel Fortify (+ 2FA, passkeys WebAuthn) |
| Puente SPA | Inertia.js 3 (`inertiajs/inertia-laravel`) |
| Frontend | Vue 3.5 (`<script setup>` + TS), reka-ui/shadcn-vue, Tailwind 4 |
| Rutas tipadas | Laravel Wayfinder → `resources/js/routes/` + `resources/js/actions/` (generados) |
| Build | Vite 8, code splitting por página |
| DB | SQLite (`.env.example`); el PRD proyecta MySQL |
| Calidad | Pint, PHPStan/Larastan nivel 7 (`phpstan.neon`), ESLint + Prettier, `vue-tsc`, PHPUnit 12 |

---

## 2. Ciclo de una petición (las capas reales)

```
Browser (Vue + Inertia)
   │  visita / submit  →  URL generada por Wayfinder (tipada)
   ▼
bootstrap/app.php ── middleware web
   ├─ encryptCookies(except: appearance, sidebar_state)
   ├─ HandleAppearance          → View::share('appearance')  (tema sin FOUC)
   ├─ HandleInertiaRequests     → props compartidas: name, auth.user, sidebarOpen
   └─ AddLinkHeadersForPreloadedAssets
   ▼
routes/web.php | routes/settings.php
   ├─ auth + verified            (técnico)
   └─ auth + verified + admin    (dueño de plataforma)
   ▼
Controller DELGADO  ── Form Request (validación + normalización)
   │                └─ Gate::authorize(...) → RepairTicketPolicy
   ▼
app/Services/*       ← TODA la lógica de negocio, DB::transaction
   ▼
Eloquent Models + Enums (casts)
   ▼
Inertia::render('pages/X', props)  +  Inertia::flash('toast', ...)
   ▼
resources/views/app.blade.php → @vite(... pages/{$page['component']}.vue)
```

Tres reglas de estilo que el código respeta sin excepción (declaradas en [`docs/specs/README.md`](specs/README.md)):

1. **Controlador delgado.** `TicketController` solo autoriza, delega a `TicketService` y lanza el toast. Ni una query de negocio.
2. **Validación fuera del controlador.** `StoreTicketRequest`, `IndexTicketRequest` y `UpdateTicketStatusRequest` validan *y* normalizan (`prepareForValidation` convierte `''` → `null`, baja el email a minúsculas) y exponen getters tipados: `->payload()`, `->photos()`, `->status()`, `->filters()`.
3. **Queries siempre scoped a `user_id`.**

---

## 3. Tres superficies, tres niveles de confianza

```
┌─ PÚBLICA (sin sesión) ──────────────────────────────────────────┐
│ GET  /                      → Welcome.vue (landing, CSS propio) │
│ GET  /t/{token}             → public/TicketStatus.vue           │
│      token: [A-Za-z0-9]{16,64}, layout = null                   │
└─────────────────────────────────────────────────────────────────┘
┌─ TÉCNICO  (auth + verified) ────────────────────────────────────┐
│ GET    /dashboard                   DashboardController         │
│ GET    /tickets          ?status&q  index                       │
│ GET    /tickets/create              create                      │
│ POST   /tickets                     store                       │
│ GET    /tickets/{t}                 show                        │
│ GET    /tickets/{t}/edit            edit                        │
│ PUT    /tickets/{t}                 update   (datos, NO estado) │
│ PATCH  /tickets/{t}/status          updateStatus (acción aparte)│
│ DELETE /tickets/{t}                 destroy                     │
│ /settings/profile · /settings/security · /settings/appearance   │
└─────────────────────────────────────────────────────────────────┘
┌─ ADMIN  (auth + verified + admin) ──────────────────────────────┐
│ GET  /admin          lista de usuarios de la plataforma         │
│ POST /admin/migrate  throttle:3,1  → php artisan migrate        │
│ POST /admin/cache    throttle:6,1  → php artisan optimize:clear │
└─────────────────────────────────────────────────────────────────┘
```

La separación **ver / editar datos / cambiar estado** en tres endpoints distintos es una decisión explícita del PRD §6.2, no una casualidad del CRUD.

---

## 4. Modelo de dominio

```
                    ┌──────────────────┐
                    │      User        │  is_admin, 2FA, passkeys
                    │  (= el tenant)   │
                    └────┬────────┬────┘
              hasMany    │        │   hasMany
                 ┌───────┘        └────────┐
                 ▼                         ▼
        ┌────────────────┐        ┌─────────────────────┐
        │   Customer     │ 1────* │   RepairTicket      │
        │ unique(user_id,│        │ public_token UNIQUE │
        │        email)  │        │ status (enum)       │
        └────────────────┘        │ received_at (idx)   │
                                  │ estimated_cost dec. │
                                  └──┬────────┬─────┬───┘
                      hasMany        │        │     │  hasOne latestOfMany
            ┌─────────────────┐◄─────┘        │     └──────────────┐
            │TicketStatus     │               ▼                    ▼
            │History          │   ┌────────────────┐  ┌────────────────────┐
            │ from/to_status  │   │  TicketPhoto   │  │ TicketNotification │
            │ note, changed_by│   │ path (Hidden)  │  │ type, to_email,    │
            │ created_at only │   │ url (Appends)  │  │ status, ticket_st. │
            └─────────────────┘   │ sort_order     │  │ error_message      │
                                  └────────────────┘  └────────────────────┘
```

Detalles de diseño que importan:

- **`Customer` es del técnico**: la unicidad es `(user_id, email)`, así que el mismo cliente puede existir una vez por cada cuenta. `TicketService::findOrCreateCustomer()` hace `firstOrNew` sobre esa clave — crear un ticket reutiliza o crea el cliente, nunca lo duplica.
- **`TicketStatusHistory` es append-only**: `const UPDATED_AT = null`, tabla `ticket_status_history` (singular, override explícito), solo `created_at`.
- **`TicketPhoto`** oculta `path` y expone `url` vía `#[Appends]` — el frontend nunca ve la ruta de disco.
- Atributos PHP de Laravel 13 (`#[Fillable]`, `#[Hidden]`, `#[Appends]`) en lugar de propiedades `$fillable`.
- Todos los modelos llevan docblocks `@property` completos: es lo que sostiene PHPStan nivel 7.

### Enums como fuente única de verdad

`app/Enums/TicketStatus.php` concentra los 7 estados, sus etiquetas en español, `isTerminal()` / `isOpen()` y `options()` (lo que se serializa al frontend). El espejo TypeScript vive en `resources/js/types/ticket.ts`.

```
received → in_review → in_repair → waiting_approval → ready → delivered ┐
                                                                         ├ terminales
                                       not_repairable (salida alterna) ──┘
```

Lo mismo aplica a `TicketNotificationStatus` (`queued` / `sent` / `failed`) y `TicketNotificationType` (`created` / `status_changed`).

---

## 5. Multi-tenancy: defensa en tres capas

No hay global scope; el aislamiento se construye explícitamente, tres veces:

| Capa | Mecanismo | Dónde |
|---|---|---|
| Route binding | `resolveRouteBinding()` añade `where('user_id', Auth::id())` → 404, no 403 | `app/Models/RepairTicket.php:116` |
| Autorización | `Gate::authorize('view'\|'update'\|'delete', $ticket)` | `app/Policies/RepairTicketPolicy.php` |
| Query | `->where('user_id', $user->id)` en cada servicio | `TicketService`, `DashboardService`, `DeviceCatalog` |

Un tenant con fuga requiere fallar las tres. El costo: **el scoping es manual**, así que cada query nueva debe recordarlo (ver §11).

---

## 6. Flujo crítico: crear ticket → notificar cliente

```
POST /tickets
  │
  ├─ StoreTicketRequest: valida (customer_name required solo si el cliente
  │   no existe ya), normaliza, extrae hasta 5 fotos jpeg/png/webp ≤4MB
  ▼
TicketService::create()  ─── DB::transaction ────────────────────┐
  │  1. findOrCreateCustomer(actor, data)                        │
  │  2. RepairTicket::create(public_token = Str::random(32),     │
  │                          status = Received)                  │
  │  3. history()->create(from: null, to: Received, note)        │
  │  4. storePhotos() → disk('public')                           │
  │        uploads/tickets/{userId}/{ticketId}/{uuid}.ext        │
  └───────────────── commit ─────────────────────────────────────┘
  │
  ▼  (FUERA de la transacción — correcto: no envía correo si hubo rollback)
TicketNotificationService::notify($ticket, Created)
  │  crea TicketNotification{status: Queued}   ← log en DB primero
  ▼
Notification::route('mail', $email)->notify(TicketStatusNotification)
  │  markdown: resources/views/emails/ticket-status.blade.php
  │  incluye route('public.tickets.show', $public_token)
  ▼
Eventos de Laravel:
  NotificationSent   → MarkTicketNotificationSent   → status = Sent
  NotificationFailed → MarkTicketNotificationFailed → status = Failed + error
  (ambos registrados en AppServiceProvider::registerNotificationListeners)
```

El patrón **log-antes-de-enviar + listeners que reconcilian el estado** es la pieza más interesante del backend: el `UPDATE` de los listeners está condicionado a `where('status', Queued)`, así que es idempotente y no sobrescribe un fallo ya registrado.

`PATCH /tickets/{t}/status` recorre exactamente el mismo camino con `StatusChanged`, y `TicketService::changeStatus()` rechaza con `ValidationException` un cambio al estado en que el ticket ya está.

`TicketService::delete()` borra las fotos del disco y sus directorios **dentro** de la transacción, antes de borrar el ticket; el cliente se conserva (PRD §6.2).

---

## 7. Arquitectura del frontend

```
resources/js/
├── app.ts ............. createInertiaApp + resolución de layout POR CONVENCIÓN
├── pages/ ............. 1 página = 1 ruta Inertia (code-split por Vite)
├── layouts/ ........... AppLayout(sidebar|header) · AuthLayout(card|simple|split)
├── components/
│   ├── ui/ ............ ~25 primitivas shadcn-vue sobre reka-ui (components.json)
│   └── tickets/ ....... CustomerFields, DevicePhotosField, SuggestInput, DeleteDialog
├── composables/ ....... useAppearance, useTwoFactorAuth, useSpeechToText, useInitials
├── routes/ + actions/ . GENERADOS por Wayfinder — no editar a mano
├── types/ ............. espejo TS del dominio PHP
└── plugins/date/ ...... adapter date-fns
```

El ruteo de layouts es puramente convencional (`resources/js/app.ts`), sin tocar cada página:

| Nombre de página | Layout |
|---|---|
| `Welcome`, `public/*` | `null` (landing y vista pública: sin chrome) |
| `auth/*` | `AuthLayout` |
| `settings/*` | `[AppLayout, SettingsLayout]` (anidado) |
| resto | `AppLayout` |

Dos canales server → client, además de las props:

- **Toasts**: `Inertia::flash('toast', ...)` en PHP → `router.on('flash')` en `resources/js/lib/flashToast.ts` → `vue-sonner`. Un único punto de entrada para todo el feedback de acción.
- **Preferencias de UI**: cookies no cifradas `appearance` y `sidebar_state` (excluidas a propósito en `bootstrap/app.php`) para que Blade pinte el tema correcto **antes** de que monte Vue; el `<script>` inline de `app.blade.php` evita el flash de tema claro.

CSS en dos sistemas distintos, deliberadamente: Tailwind 4 con `@theme inline` sobre tokens (`resources/css/tokens.css`) para la app autenticada, y CSS modular clásico (`resources/css/landing/*.css`, 12 archivos por sección) para la landing.

Toques de producto que viven en el frontend:

- `useSpeechToText` — dictado del problema reportado vía Web Speech API, con degradación limpia si el navegador no lo soporta.
- `SuggestInput` + `DeviceCatalog` — el catálogo curado de `config/devices.php` **unido al historial real del técnico** (`DeviceCatalog::historyFor()`), con texto libre siempre permitido.

---

## 8. Autenticación y seguridad

Fortify se configura por completo en `app/Providers/FortifyServiceProvider.php`, con las **vistas re-apuntadas a Inertia** (`Fortify::loginView(fn => Inertia::render('auth/Login'))`) en lugar de Blade.

- Features activas: registro, reset de password, verificación de email, **2FA con confirmación** y **passkeys** (`@laravel/passkeys` + contrato `PasskeyUser`).
- Rate limiting: `login` 5/min por email+IP, `two-factor` 5/min por sesión, `passkeys` 10/min, `user-password.update` 6/min, `/admin/migrate` 3/min, `/admin/cache` 6/min.
- `/settings/security` exige `RequirePassword` (re-confirmación de contraseña).
- Política de password endurecida **solo en producción** (`min(12)` + mixedCase + símbolos + `uncompromised()`); en local sin reglas, para no estorbar el desarrollo — `AppServiceProvider::configureDefaults()`.
- `DB::prohibitDestructiveCommands(app()->isProduction())`.
- `Date::use(CarbonImmutable::class)` globalmente.
- Superficie pública minimizada: `PublicTicketService::pageProps()` construye un array **a mano** con 8 campos; nunca serializa el modelo. No expone el cliente completo, el costo, las notas internas ni las fotos.

---

## 9. Infraestructura y despliegue

```
.github/workflows/tests.yml   → lint + types + PHPUnit
.github/workflows/deploy.yml  → push a main|develop
     composer install --no-dev → npm ci → npm run build
     → FTP-Deploy-Action a hosting compartido
```

El deploy es **FTP a hosting compartido, sin acceso a shell**. Eso explica tres piezas que de otro modo parecerían raras:

1. **`ApplicationMaintenanceService` + `/admin/migrate` y `/admin/cache`**: `Artisan::call('migrate')` y `optimize:clear` disparados desde la web, porque no hay terminal en el servidor. `set_time_limit(120)` y `promoteToAdmin()` completan el arranque en frío.
2. **La migración `2026_08_19_181300_move_ticket_photos_out_of_public_tickets`**: `public/tickets/` como directorio real *tapaba* las rutas `/tickets` en Apache/LiteSpeed (403). La migración mueve los archivos a `public/uploads/tickets/` — una migración que toca el filesystem, no el esquema.
3. **`OwnerController::clients()` consulta `Schema::hasColumn('users', 'is_admin')`** antes de usar la columna: la vista admin debe funcionar incluso *antes* de que corran las migraciones.

Tests: SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array` (`phpunit.xml`).

---

## 10. Estado real vs. PRD

Los specs de [`docs/specs/`](specs/README.md) declaran un orden de implementación. Contrastado con el código:

| Spec | Estado | Evidencia |
|---|---|---|
| 01 — Tickets (crear/ver/estado) | ✅ | `TicketService`, 27 tests en `tests/Feature/Tickets/TicketTest.php` |
| 01.1 — Listar/editar/eliminar | ✅ | `paginateForIndex` con filtros `status` + `q` |
| 02 — Correos | ⚠️ funciona, **sin cola** | `TicketStatusNotification` no implementa `ShouldQueue` |
| 03 — Vista pública | ✅ | `PublicTicketService`, 7 tests |
| 04 — Dashboard | ⚠️ recortado | KPIs del mes + 3 tickets recientes; sin filtros ni indicador de atraso (PRD §6.5) |
| 06 — Cobro manual | ❌ no empezado | sin `subscription_payments`, sin middleware de suspensión |
| 05 — Marca personal | ❌ siguiente iteración | `'branding' => null` en el mail |

**97 tests feature** cubren auth, tenancy, dashboard, vista pública, notificaciones y admin.

---

## 11. Observaciones arquitectónicas

Lo que la arquitectura ya resuelve bien y conviene no romper:

- Servicios como única puerta a la escritura del dominio; la transacción envuelve DB + filesystem, y el correo queda **fuera** de ella.
- El log de notificación como máquina de estados reconciliada por eventos, idempotente.
- Enum PHP ↔ tipo TS como contrato; `TicketStatus::options()` evita duplicar etiquetas en el frontend.
- Triple aislamiento de tenant.

Los cuatro puntos con tensión real:

1. **Correo sincrónico** (`app/Notifications/TicketStatusNotification.php`). El PRD §6.3 pide cola y `QUEUE_CONNECTION=database` está configurado, pero el envío bloquea el request del técnico: un SMTP lento hace que crear un ticket se sienta lento. Añadir `implements ShouldQueue` es un cambio de una línea; el `try/catch` de `TicketNotificationService` dejaría de capturar el fallo, pero `MarkTicketNotificationFailed` ya cubre ese caso. Requiere un worker en el servidor.
2. **Scoping manual sin global scope.** Cada query futura sobre `RepairTicket` o `Customer` debe recordar `where('user_id', ...)`. Es la fuente de fuga más probable a medida que crezca el código.
3. **Fotos en disco público.** `uploads/tickets/{user}/{ticket}/{uuid}.ext` lo sirve directo el servidor web: la protección es la impredecibilidad del UUID, no autorización. Aceptable para el MVP; si las fotos pasan a considerarse sensibles, hay que moverlas a disco privado con ruta firmada.
4. **Artisan expuesto por HTTP.** Necesario con deploy FTP, pero `/admin/migrate` corre migraciones y auto-promueve a admin. Está tras `auth + verified + admin` y `throttle:3,1`; vale tenerlo presente como superficie si algún día la cuenta admin se comparte.

Detalle de configuración: `.env.example` declara `DB_CONNECTION=sqlite` y `MAIL_MAILER=log`, mientras el PRD §9 proyecta MySQL + Redis. Es una divergencia consciente de MVP, pero el `.env` de producción es lo único que la decide.
