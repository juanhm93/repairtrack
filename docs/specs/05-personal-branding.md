# SPEC-05 — Marca personal (siguiente iteración)

## Objetivo

Cerrar el PRD §6.1 **después** de que el MVP por usuario ya esté en un subdominio: cada técnico puede configurar **su** marca (nombre, logo, colores, remitente) y esa marca se usa en los correos (spec 02) y en la vista pública (spec 03).

La marca es personal, no del local. Dos técnicos que comparten mostrador tienen dos cuentas, dos marcas y cero visibilidad cruzada: clientes, precios y tickets no se mezclan.

Este spec **no** convierte la app en multi-tenant ni en un “taller compartido”. Los tickets y clientes siguen siendo del `User`. El branding es un perfil 1:1 aditivo.

No implementar este spec en la primera entrega.

## Dependencias

- Specs 01–04 cerrados y en producción (o al menos usables)
- Auth Fortify sin cambios de registro obligatorios (el user ya existe)
- Settings: [`routes/settings.php`](../../routes/settings.php), [`resources/js/layouts/settings/Layout.vue`](../../resources/js/layouts/settings/Layout.vue)
- Shared Inertia: [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
- Plantilla de correo del spec 02 y página `public/TicketStatus` del spec 03 (ya preparadas para un bloque de branding opcional)

## Alcance

- Tabla y modelo `Brand` como perfil de marca del usuario (hasOne)
- Settings para editar branding y subir logo
- Compartir `auth.brand` en Inertia
- Correos: `from` + logo/colores del técnico cuando existan; fallback a config de la app
- Vista pública: header/footer con nombre, logo y colores del técnico cuando existan
- Factory, seeder y tests

## Fuera de alcance

- Mover `customers` / `repair_tickets` de `user_id` a `brand_id` (la dueñez no cambia)
- Extender el registro con “nombre comercial” como campo obligatorio (se puede crear el perfil vacío o en settings)
- Subdominio o dominio propio por usuario (PRD §8)
- Cuentas compartidas de un local, roles de taller, o que un técnico vea clientes/precios/tickets de otro
- Tickets, dashboard o clientes nuevos

## Modelo de datos

### `brands`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id`, unique | Un perfil de marca por usuario |
| `name` | string | Cómo te ve el cliente (nombre propio o apodo comercial) |
| `logo_path` | string, nullable | Path relativo en disco `public` |
| `primary_color` | string(7) | Hex, default `#0F766E` |
| `secondary_color` | string(7) | Hex, default `#134E4A` |
| `sender_name` | string | Nombre de remitente de correo |
| `sender_email` | string | Correo de remitente |
| `created_at` / `updated_at` | timestamps | |

### `users`

Sin `brand_id`. La relación es `User::brand()` hasOne `Brand` (`brands.user_id`).

### Relaciones

- `User` hasOne `Brand`
- `Brand` belongsTo `User`
- `RepairTicket` / `Customer` **no** ganan `brand_id`; se llega a la marca vía `ticket.user.brand`

### Modelo `Brand`

- Path: `app/Models/Brand.php`
- Accessor `logo_url`: URL pública del logo o `null`
- Fillable: `user_id`, `name`, `logo_path`, `primary_color`, `secondary_color`, `sender_name`, `sender_email`

## Backend

### Servicio — `app/Services/BrandService.php`

```
updateBranding(User $user, array $data, ?UploadedFile $logo): Brand
  - firstOrCreate del Brand del user
  - actualiza name, colores, sender_name, sender_email
  - si hay logo: guarda en disk `public` bajo `logos/{user_id}`
  - si reemplaza logo, borra el archivo anterior
```

Defaults al crear: `name` = `user.name`, `sender_name` = `user.name`, `sender_email` = `user.email`, colores del schema.

### Settings

- `app/Http/Controllers/Settings/BrandController.php`
  - `edit`: Inertia `settings/Brand`
  - `update`: Form Request + `BrandService::updateBranding` + toast
- `app/Http/Requests/Settings/UpdateBrandRequest.php`
  - `name`: required, string, max 255
  - `primary_color` / `secondary_color`: required, regex hex `#RRGGBB`
  - `sender_name`: required, string, max 255
  - `sender_email`: required, email
  - `logo`: nullable, image, max 2MB, mimes jpg/png/webp
- Autorización: el user solo edita su propio `Brand`

### Rutas

En `routes/settings.php`, grupo `auth` + `verified`:

```
GET   /settings/brand   → BrandController@edit    (brand.edit)
PATCH /settings/brand   → BrandController@update  (brand.update)
```

### Inertia share

En `HandleInertiaRequests`:

```
auth.user   → user autenticado (como hoy)
auth.brand  → marca del user o null (id, name, logo_url, primary_color, secondary_color, sender_name, sender_email)
```

No exponer `logo_path` crudo; exponer `logo_url`.

### Correos (enganche spec 02)

En `TicketStatusNotification`:

- Si `ticket.user.brand` existe: `from` = sender del técnico; subject y cuerpo usan `brand.name`, `logo_url`, colores inline
- Si no existe: comportamiento actual (config de la app)

### Vista pública (enganche spec 03)

En `PublicTicketController`, agregar prop opcional:

```
branding: { name, logo_url, primary_color, secondary_color } | null
```

`TicketStatus.vue` usa branding si viene; si no, sigue mostrando `app.name`.

### Storage

- Disco `public`
- `php artisan storage:link` si no está documentado

## Frontend

### Settings — `resources/js/pages/settings/Brand.vue`

Mismo patrón que `Profile.vue`:

- Heading: “Marca” / “Logo, colores y remitente de los correos a tus clientes”
- Form: nombre, logo (preview + file), color primario, color secundario, nombre de remitente, correo de remitente
- Submit con Wayfinder

### Nav de settings

En `layouts/settings/Layout.vue`, item “Marca” → `brand.edit`.

### Tipos TS

```
Brand = { id, name, logo_url, primary_color, secondary_color, sender_name, sender_email }
Auth = { user: User, brand: Brand | null }
```

### Pública y correos

- `public/TicketStatus.vue`: header/footer y CSS variables `--brand-primary` / `--brand-secondary` cuando `branding` no es null
- Plantilla `emails.ticket-status`: rama branded vs app default

El registro (`Register.vue`) **no** se toca.

## Factory y seeder

- `BrandFactory`: name, colores default, sender_name, sender_email; state `withLogo()` opcional
- `DatabaseSeeder`: opcionalmente un `Brand` para `test@example.com` (usar el nombre del user, no un “Taller Demo”)
- `UserFactory` **no** debe crear Brand por defecto (los tests 01–04 no dependen de él)

## Tests

Feature tests nuevos, `RefreshDatabase`:

- Guest no entra a `settings/brand`
- User autenticado crea/actualiza su branding (name, colores, remitente)
- Upload de logo guarda archivo y actualiza `logo_path`
- User A no puede actualizar el branding de user B
- User A no ve `auth.brand` ni tickets/clientes de user B (regresión de aislamiento)
- Correo de un ticket usa `from` del brand cuando existe
- Correo de un user sin brand sigue usando config de la app
- Vista pública incluye `branding` cuando el dueño tiene brand
- Vista pública sin branding sigue igual que spec 03
- Tickets siguen scoped por `user_id` (regresión: no aparece `brand_id` en tickets)

## Criterios de aceptación

- Settings → Marca permite cambiar nombre, logo y colores, con toast de éxito
- `auth.brand` está disponible en páginas autenticadas
- Correos y `/t/{token}` usan la marca si el usuario la configuró; si no, la app
- Los tickets no cambian de dueño ni de schema (`user_id` se mantiene)
- Un técnico no ve marca, clientes ni precios de otro, aunque trabajen en el mismo local
- Los specs 01–04 siguen verdes
- Este spec no se implementa en la primera entrega al subdominio
