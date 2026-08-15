# SPEC-06 — Cobro manual (PayPal / Binance)

## Objetivo

Cerrar el PRD §6.6: el usuario paga $3/mes por PayPal o Binance, sube el comprobante, el admin lo revisa en la web y marca pagado. Si una cuenta no está **marcada como pagada** los primeros 5 días del mes, se suspende (sin dashboard ni tickets) hasta que el admin marque pagado.

No hay Stripe ni cobro automático. El precio y los métodos ya están en la landing ([`resources/js/pages/Welcome.vue`](../../resources/js/pages/Welcome.vue) §precio / FAQ).

Al terminar, el usuario puede enviar un comprobante, el admin puede aprobarlo o rechazarlo, recibe recordatorios de pago los días 1, 3 y 5, y el cron del día 6 cierra el feature: las cuentas impagas quedan `suspended`.

## Dependencias

- Auth Fortify (registro, login, verificación). El registro **no** se extiende: el usuario entra `active` y puede usar la app
- Specs 01 y 04: rutas de tickets y dashboard para colgar el middleware de suscripción
- Spec 03: la ruta pública `/t/{token}` **no** se bloquea
- Mail + queue como spec 02 (`ShouldQueue`, `MAIL_MAILER=array` en tests)
- Sidebar: [`resources/js/components/AppSidebar.vue`](../../resources/js/components/AppSidebar.vue)
- Shared Inertia: [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
- Seeder actual: user `test@example.com` en [`DatabaseSeeder`](../../database/seeders/DatabaseSeeder.php)
- Schedule: [`routes/console.php`](../../routes/console.php) (hoy solo `inspire`)

Este spec **no** depende del spec 05. Se implementa después de 01–04; el branding puede esperar.

## Alcance

- Columnas en `users`: `is_admin`, `subscription_status`
- Tabla `subscription_payments`
- Enums `PaymentMethod`, `PaymentStatus`, `SubscriptionStatus`
- `BillingService` + comando diario de suspensión + comando diario de recordatorios
- Middleware `EnsureSubscriptionActive` en dashboard y tickets
- Página de facturación del usuario (instrucciones + upload)
- Panel admin de pagos pendientes (login de admin, no link firmado)
- Correos: recordatorios de pago (días 1, 3 y 5); al admin al enviar comprobante; al usuario en pagado / rechazado / suspendido
- Tabla `subscription_reminder_logs` para no reenviar el mismo recordatorio del periodo
- Config `.env` para monto, gracia, PayPal, Binance e email de admin
- Factory, seeder y tests

## Fuera de alcance

- Stripe, webhooks, suscripciones automáticas o cualquier pasarela
- Cobros del taller a *su* cliente (el `estimated_cost` del ticket no se cobra aquí)
- Link firmado sin login para marcar pagado
- Cancelación explícita de la suscripción (si no paga el mes siguiente, el cron la suspende)
- Cobrar meses atrasados: pagar el periodo **actual** reactiva
- Prorrateo, cupones, planes distintos de $3/mes
- Bloquear `/t/{token}` cuando el dueño está suspendido
- Branding por negocio (spec 05)
- Roles más allá de `is_admin`

## Modelo de datos

### Enum `App\Enums\SubscriptionStatus`

Backed string:

| Value | Label |
|-------|--------|
| `active` | Activa |
| `suspended` | Suspendida |

Default al registrarse: `active`. Un admin nunca pasa a `suspended`.

### Enum `App\Enums\PaymentMethod`

| Value | Label |
|-------|--------|
| `paypal` | PayPal |
| `binance` | Binance ID |

### Enum `App\Enums\PaymentStatus`

| Value | Label |
|-------|--------|
| `pending_review` | Pendiente de revisión |
| `paid` | Pagado |
| `rejected` | Rechazado |

`pending_review` **no** cuenta como pagado. El cron mira solo `paid`.

### `users` (columnas nuevas)

| Columna | Tipo | Notas |
|---------|------|--------|
| `is_admin` | boolean, default false | Panel `/admin/payments`; no paga ni se suspende |
| `subscription_status` | string, default `active` | Valor de `SubscriptionStatus` |

El registro Fortify no pide estos campos: defaults de la migración.

### `subscription_payments`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id` | cascade delete |
| `period` | char(7) | `YYYY-MM` (mes calendario cobrado) |
| `amount` | decimal(8,2) | Snapshot al enviar (default 3.00) |
| `currency` | string(3) | Snapshot, default `USD` |
| `method` | string | Valor de `PaymentMethod` |
| `proof_path` | string | Path en disco `local` (no público) |
| `status` | string | Valor de `PaymentStatus` |
| `submitted_at` | timestamp | |
| `reviewed_at` | timestamp, nullable | |
| `reviewed_by` | FK `users.id`, nullable | Admin; `nullOnDelete` |
| `review_note` | text, nullable | Motivo de rechazo (opcional en pagado) |
| `created_at` / `updated_at` | timestamps | |

Índices: `user_id`, `period`, `status`, `['user_id', 'period']`.

Unicidad de pendientes: como máximo **un** `pending_review` por (`user_id`, `period`). Tras `rejected`, el usuario puede enviar otro. Puede coexistir un `paid` de un periodo con un `rejected` anterior del mismo periodo.

### `subscription_reminder_logs`

Un envío por usuario, periodo y tipo. Evita duplicados si el cron corre dos veces el mismo día.

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id` | cascade delete |
| `period` | char(7) | `YYYY-MM` |
| `type` | string | Valor de `ReminderType` |
| `sent_at` | timestamp | |
| `created_at` | timestamp | Sin `updated_at` (append-only) |

Unique: `['user_id', 'period', 'type']`.

### Enum `App\Enums\ReminderType`

| Value | Cuándo se envía |
|-------|-----------------|
| `day_1` | Día 1 del mes (vence el día 5) |
| `day_3` | Día 3 (quedan 2 días) |
| `day_5` | Día 5 (último día antes de suspender) |

No hay recordatorio el día 6: ese día corre la suspensión y `SubscriptionSuspendedNotification`.

### Relaciones

- `User` hasMany `SubscriptionPayment`, hasMany `SubscriptionReminderLog`
- `SubscriptionPayment` belongsTo `User`, belongsTo `User` (`reviewed_by`)
- `SubscriptionReminderLog` belongsTo `User`

Casts: `status` → `PaymentStatus`, `method` → `PaymentMethod`, `amount` → `decimal:2`, `submitted_at` / `reviewed_at` → datetime. En `User`: `is_admin` → bool, `subscription_status` → `SubscriptionStatus`.

### Periodo a cobrar

`currentPeriod(today)` = `YYYY-MM` de `today` en timezone de la app.

`firstDuePeriod(User)`:

- Si `user.created_at.day <= config('billing.grace_day')` (5) → mes de `created_at`
- Si `created_at.day > 5` → mes siguiente (el resto del mes de registro es cortesía)

Ejemplos (gracia = día 5):

| Registro | Primer corte | Primer `period` |
|----------|--------------|-----------------|
| 3 ago | 5 ago | `2026-08` |
| 5 ago | 5 ago | `2026-08` |
| 10 ago | 5 sep | `2026-09` |

Después del primer periodo, cada mes calendario. Pagar el periodo actual reactiva; no se exigen agosto/septiembre si el admin marca octubre.

### Quién se suspende

`shouldBeSuspended(User, today)` es true si **todas** se cumplen:

1. `user.is_admin` es false
2. `firstDuePeriod(user) <= currentPeriod(today)`
3. `today.day > grace_day`
4. No existe `subscription_payments` con `user_id`, `period = currentPeriod`, `status = paid`

Quien ya está `suspended` **no** vuelve a `active` el día 1 del mes siguiente. Solo `markPaid` lo reactiva.

Un comprobante `pending_review` el día 4 no evita la suspensión el día 6. El criterio es “marcada como pagada”.

## Backend

### Config — `config/billing.php`

Valores por `.env`, nunca hardcodeados en Vue:

```
monthly_amount     BILLING_MONTHLY_AMOUNT     default 3
currency           BILLING_CURRENCY           default USD
grace_day          BILLING_GRACE_DAY          default 5
paypal.email       BILLING_PAYPAL_EMAIL
paypal.link        BILLING_PAYPAL_LINK        paypal.me u otro
binance.id         BILLING_BINANCE_ID
binance.network    BILLING_BINANCE_NETWORK    nullable
admin_email        BILLING_ADMIN_EMAIL        destinatario del correo de revisión
reminder_days      —                          [1, 3, 5] fijo en config (no hace falta .env)
```

Documentar las keys en `.env.example`. `reminder_days` no se expone al frontend.

### Servicio — `app/Services/BillingService.php`

Toda mutación de pagos y status de suscripción pasa por aquí.

```
submitProof(User $user, PaymentMethod $method, UploadedFile $proof, ?Carbon $today = null): SubscriptionPayment
  - period = currentPeriod(today); si firstDuePeriod > current, period = firstDuePeriod
    (el usuario que se registró el 10 puede pagar septiembre en agosto)
  - rechazar si ya hay paid de ese period (422)
  - rechazar si ya hay pending_review de ese period (422; debe esperar o no hay “reemplazar”)
  - guardar imagen en disk `local` bajo `payment-proofs/{user_id}/{period}-{uuid}.{ext}`
  - crear payment: pending_review, amount/currency de config, submitted_at = now
  - encolar PaymentSubmittedNotification al admin (after-commit)
  - no cambia subscription_status

markPaid(SubscriptionPayment $payment, User $admin, ?string $note): SubscriptionPayment
  - solo desde pending_review
  - status=paid, reviewed_at, reviewed_by, review_note
  - user.subscription_status = active
  - encolar PaymentReviewedNotification al user (pagado)

reject(SubscriptionPayment $payment, User $admin, ?string $note): SubscriptionPayment
  - solo desde pending_review
  - status=rejected, reviewed_at, reviewed_by, review_note
  - no cambia subscription_status (si ya estaba suspended, sigue)
  - encolar PaymentReviewedNotification al user (rechazado)

firstDuePeriod(User $user): string
currentPeriod(?Carbon $today = null): string
shouldBeSuspended(User $user, ?Carbon $today = null): bool

suspendUnpaid(?Carbon $today = null): int
  - si today.day <= grace_day: no suspende a nadie (return 0)
  - users !is_admin, firstDuePeriod <= current, sin paid del period actual
    → subscription_status = suspended
  - encolar SubscriptionSuspendedNotification a cada user que **cambió** de active a suspended
  - no toca admins ni users cuyo firstDuePeriod es futuro

sendPaymentReminders(?Carbon $today = null): int
  - si today.day no está en reminder_days (1, 3, 5): return 0
  - candidatos: !is_admin, subscription_status = active,
    firstDuePeriod <= currentPeriod, sin paid del period actual
  - no enviar si ya existe log (user_id, period, type del día)
  - pending_review no exime: el criterio sigue siendo “marcada como pagada”
  - por cada candidato: encolar PaymentReminderNotification + escribir log
  - no envía a suspendidos (ya recibieron SubscriptionSuspendedNotification)
  - no envía a quien firstDuePeriod es futuro (cortesía del mes de registro)
```

`today` inyectable para tests (no depender de `Carbon::setTestNow` como único mecanismo, aunque sí se puede usar).

### Comando + schedule

`app/Console/Commands/SuspendUnpaidSubscriptions.php`:

```
php artisan billing:suspend-unpaid
```

`app/Console/Commands/SendPaymentReminders.php`:

```
php artisan billing:send-payment-reminders
```

Llaman `BillingService::suspendUnpaid()` y `sendPaymentReminders()`. En [`routes/console.php`](../../routes/console.php):

```
Schedule::command('billing:send-payment-reminders')->daily()
Schedule::command('billing:suspend-unpaid')->daily()
```

Timezone = `config('app.timezone')`. Los recordatorios corren **antes** que la suspensión en el schedule (el día 5 manda el último aviso; el día 6 solo suspende).

### Middleware — `EnsureSubscriptionActive`

- Si guest: no aplica (las rutas ya tienen `auth`)
- Si `user.is_admin`: pasa
- Si `subscription_status === suspended`: redirect a `billing.show` + toast (“Tu cuenta está suspendida. Envía el comprobante de pago para reactivarla.”)
- Si `active`: pasa

Registrar alias `subscription.active` en [`bootstrap/app.php`](../../bootstrap/app.php).

Aplicar en el grupo de dashboard y tickets (specs 01/04), **después** de `auth` + `verified`.

**No** aplicar en:

- `GET/POST /billing`
- settings (`routes/settings.php`)
- logout / rutas Fortify
- `/admin/*`
- `/t/{token}` (spec 03)
- `/` landing

Un usuario suspendido puede entrar, ver settings y facturación, y enviar otro comprobante.

### Controllers

`app/Http/Controllers/BillingController.php`:

```
show   → Inertia billing/Index
          props: instructions (config), currentPeriod, firstDuePeriod,
                 subscription_status, latestPayment del period (o null)
store  → SubmitPaymentRequest + BillingService::submitProof → back + toast
```

`app/Http/Controllers/Admin/PaymentController.php` (middleware `admin`):

```
index  → Inertia admin/payments/Index
         default: pending_review primero; query ?status=
show   → Inertia admin/payments/Show (payment + user)
proof  → descarga/stream del archivo desde disk local (auth admin)
pay    → BillingService::markPaid → redirect index + toast
reject → BillingService::reject → redirect index + toast
```

### Form Requests

`SubmitPaymentRequest` (`auth`):

- `method` required, valor de `PaymentMethod`
- `proof` required, image, mimes jpg/jpeg/png/webp, max 5120 (5 MB)

`RejectPaymentRequest` (`admin`):

- `review_note` nullable|string|max:1000

`MarkPaidRequest` (`admin`):

- `review_note` nullable|string|max:1000

### Autorización

- Middleware `EnsureUserIsAdmin`: `user.is_admin === true` o 403
- Policy `SubscriptionPaymentPolicy`: el user ve/crea solo los suyos; el admin ve/revisa todos
- `proof` nunca se sirve por `/storage`; solo `admin.payments.proof`

### Rutas

En [`routes/web.php`](../../routes/web.php):

Grupo `auth` + `verified` (sin `subscription.active`):

```
GET   /billing                  billing.show
POST  /billing                  billing.store
```

Grupo `auth` + `verified` + `subscription.active`:

```
(dashboard y tickets de specs 01/04)
```

Grupo `auth` + `verified` + `admin`:

```
GET   /admin/payments                    admin.payments.index
GET   /admin/payments/{payment}          admin.payments.show
GET   /admin/payments/{payment}/proof    admin.payments.proof
POST  /admin/payments/{payment}/pay      admin.payments.pay
POST  /admin/payments/{payment}/reject   admin.payments.reject
```

### Notificaciones (queued)

`app/Notifications/PaymentSubmittedNotification.php` — al admin:

- Via mail a `config('billing.admin_email')` (si vacío, a todos los `is_admin`)
- Subject: “Comprobante pendiente — {user.email} — {period}”
- Cuerpo: nombre/email del user, method, period, amount, CTA a `admin.payments.show`
- El admin **debe iniciar sesión**; el link no es firmado

`app/Notifications/PaymentReviewedNotification.php` — al user:

- Pagado: “Pago confirmado — {period}”
- Rechazado: “Comprobante rechazado — {period}” + `review_note` si hay + CTA a `/billing`

`app/Notifications/PaymentReminderNotification.php` — al user:

- Constructor: `User $user`, `ReminderType $type`, `string $period`
- Subject / tono según el día:
  - `day_1`: “Recordatorio: tu pago de {period} vence el día 5”
  - `day_3`: “Quedan 2 días para pagar RepairTrack — {period}”
  - `day_5`: “Último día para pagar — mañana se suspende la cuenta”
- Cuerpo: monto ($3 USD), métodos (PayPal / Binance), fecha límite (día 5), CTA a `/billing`
- Si el user tiene `pending_review` del period: una línea extra — “Ya recibimos tu comprobante; si no se confirma hoy, la cuenta igual se suspende el día 6”
- No incluir datos de otros users ni el comprobante

`app/Notifications/SubscriptionSuspendedNotification.php` — al user:

- Subject: “Tu cuenta fue suspendida”
- CTA a `/billing`

`from` = config de la app (mismo criterio que spec 02). No branding de taller.

### Inertia share

En `HandleInertiaRequests`, además de `auth.user`:

```
auth.user.is_admin
auth.subscription = {
  status,          // active | suspended
  current_period,  // YYYY-MM
  first_due_period
} | null           // null si guest
```

No compartir `proof_path`. El user autenticado siempre recibe `auth.subscription` (también suspendido).

## Frontend

### Tipos TS

Nuevo `resources/js/types/billing.ts` (exportado desde `types/index.ts`):

```
PaymentMethod = 'paypal' | 'binance'
PaymentStatus = 'pending_review' | 'paid' | 'rejected'
SubscriptionStatus = 'active' | 'suspended'

BillingInstructions = {
  monthly_amount, currency, grace_day,
  paypal: { email, link },
  binance: { id, network }
}

SubscriptionPayment = {
  id, period, amount, currency, method, status,
  submitted_at, reviewed_at, review_note,
  user?: { id, name, email }
}

Auth.subscription = { status, current_period, first_due_period } | null
```

`User` gana `is_admin: boolean`.

### `resources/js/pages/billing/Index.vue`

- `AppLayout`, breadcrumb Facturación
- Banner si `suspended`: no puede usar el dashboard hasta que el admin marque pagado
- Banner si hay `pending_review` del periodo: “En revisión”
- Si hay `paid` del periodo: “Pagado — {period}”
- Instrucciones: $3 USD, PayPal (email/link), Binance ID (y network si existe)
- Form: select método + input file + submit (oculto si ya `paid` o `pending_review` de ese period)
- Copy en español

### `resources/js/pages/admin/payments/Index.vue`

- Layout autenticado (mismo `AppLayout` o uno admin mínimo; no inventar un design system)
- Tabla: user, period, method, status, submitted_at, link a show
- Filtro por status; default pendientes

### `resources/js/pages/admin/payments/Show.vue`

- Datos del user y del pago
- Imagen del comprobante vía `admin.payments.proof` (no URL pública)
- Acciones si `pending_review`: “Marcar pagado” y “Rechazar” (nota opcional)
- Si ya revisado: solo lectura

### Nav

En `AppSidebar.vue`:

- “Facturación” → `billing.show` (todos los users autenticados)
- “Pagos” → `admin.payments.index` solo si `auth.user.is_admin`

Banner compacto en el layout autenticado si `auth.subscription.status === 'suspended'` (link a Facturación). No bloquear settings.

Toasts con `Inertia::flash('toast', …)` como `ProfileController`.

## Factory y seeder

- `UserFactory`: `is_admin` false, `subscription_status` active; state `admin()`, `suspended()`
- `SubscriptionPaymentFactory`: period del mes actual, method paypal, status `pending_review`; states `paid()`, `rejected()`, `forPeriod(string)`
- `SubscriptionReminderLogFactory`: period actual, type `day_1`; no se usa en el seeder de demo
- `DatabaseSeeder`:
  - user admin `admin@example.com` (`is_admin` true)
  - el `test@example.com` existente sigue `active` y no-admin
  - 1–2 payments `pending_review` de users de demo (sin enviar mail real)

No crear archivos de imagen reales en seed si se puede evitar; el factory puede poner un `proof_path` ficticio. Los tests de upload sí usan `UploadedFile::fake()->image(...)`.

## Tests

`tests/Feature/Billing/`, `RefreshDatabase`, `Notification::fake()`, `Storage::fake('local')`. Viajar en el tiempo con `Carbon::setTestNow` donde haga falta.

- Guest no entra a `/billing` ni `/admin/payments`
- User autenticado envía comprobante: persiste `pending_review`, guarda archivo, encola mail al admin
- No puede enviar segundo `pending_review` del mismo period (422)
- Tras `rejected`, puede enviar de nuevo
- Ya `paid` del period: 422 al reenviar
- User no-admin recibe 403 en `/admin/payments`
- Admin marca paid: payment `paid`, user `active`, mail al user
- Admin rechaza: payment `rejected`, status del user no cambia, mail al user
- User A no ve el payment de user B
- `proof` no es accesible sin ser admin
- Suspendido: redirect desde dashboard y `tickets.create` hacia `/billing`; `GET/POST /billing` y settings sí
- `/t/{token}` de un ticket cuyo dueño está suspendido sigue 200
- Comando el día 6: user sin `paid` del period y `firstDue` vencido → `suspended` + mail
- Comando el día 4: no suspende
- User registrado el día 10: el día 6 de *ese* mes no se suspende; el día 6 del mes siguiente sí (si no hay `paid`)
- User con `paid` del period actual: el día 6 no se suspende
- Admin nunca se suspende
- `pending_review` el día 6 no evita la suspensión
- `markPaid` de un suspendido lo deja `active`
- Suspendido el 20 ago sigue `suspended` el 1 sep hasta que haya un `paid`
- Recordatorio día 1: user `active` sin `paid` del period y `firstDue` vencido → encola `PaymentReminderNotification` y escribe log `day_1`
- Recordatorio día 3 y día 5: igual, con su `type`; no reenvía si el log de ese type+period ya existe
- Día 2, 4 o 6: `sendPaymentReminders` no encola nada
- User con `paid` del period: no recibe recordatorio
- User con `pending_review`: sí recibe recordatorio (el mail menciona que el comprobante no evita la suspensión)
- User registrado el día 10: no recibe recordatorios en *ese* mes; sí el día 1/3/5 del mes siguiente
- Admin y user `suspended`: no reciben recordatorio
- Correr el comando dos veces el mismo día 1 no duplica el mail

## Criterios de aceptación

- `/billing` muestra cómo pagar $3 por PayPal o Binance y permite adjuntar la imagen
- Al enviar, el pago queda `pending_review` y el admin recibe un correo con link a la web (debe loguearse)
- El admin marca pagado o rechaza; pagado reactiva la cuenta
- Los días 1, 3 y 5 del mes, quien debe el periodo y no está `paid` recibe un recordatorio con link a `/billing` (una vez por tipo y periodo)
- Después del día 5, una cuenta sin `paid` del periodo vigente queda `suspended`
- Suspendido no usa dashboard ni tickets; sí facturación y settings
- `/t/{token}` sigue funcionando
- Admins no pagan ni reciben recordatorios
- No hay Stripe ni cobro automático
- El seeder deja un admin y al menos un pago pendiente para desarrollo local
