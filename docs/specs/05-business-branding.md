# SPEC-05 — Negocio y branding (siguiente iteración)

## Objetivo

Cerrar el PRD §6.1 **después** de que el MVP por usuario ya esté en un subdominio: cada usuario puede configurar la marca de su taller (nombre, logo, colores, remitente) y esa marca se usa en los correos (spec 02) y en la vista pública (spec 03).

Este spec **no** convierte la app en multi-tenant. Los tickets y clientes siguen siendo del `User`. El branding es un perfil 1:1 aditivo.

No implementar este spec en la primera entrega.

## Dependencias

- Specs 01–04 cerrados y en producción (o al menos usables)
- Auth Fortify sin cambios de registro obligatorios (el user ya existe)
- Settings: [`routes/settings.php`](../../routes/settings.php), [`resources/js/layouts/settings/Layout.vue`](../../resources/js/layouts/settings/Layout.vue)
- Shared Inertia: [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
- Plantilla de correo del spec 02 y página `public/TicketStatus` del spec 03 (ya preparadas para un bloque de branding opcional)

## Alcance

- Tabla y modelo `Business` como perfil de marca del usuario (hasOne)
- Settings para editar branding y subir logo
- Compartir `auth.business` en Inertia
- Correos: `from` + logo/colores del taller cuando existan; fallback a config de la app
- Vista pública: header/footer con nombre, logo y colores del taller cuando existan
- Factory, seeder y tests

## Fuera de alcance

- Mover `customers` / `repair_tickets` de `user_id` a `business_id` (la dueñez no cambia)
- Extender el registro con “nombre del negocio” como campo obligatorio (se puede crear el perfil vacío o en settings)
- Subdominio o dominio propio por negocio (PRD §8)
- Múltiples usuarios/técnicos por negocio
- Tickets, dashboard o clientes nuevos

## Modelo de datos

### `businesses`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `user_id` | FK `users.id`, unique | Un perfil de marca por usuario |
| `name` | string | Nombre comercial |
| `logo_path` | string, nullable | Path relativo en disco `public` |
| `primary_color` | string(7) | Hex, default `#0F766E` |
| `secondary_color` | string(7) | Hex, default `#134E4A` |
| `sender_name` | string | Nombre de remitente de correo |
| `sender_email` | string | Correo de remitente |
| `created_at` / `updated_at` | timestamps | |

### `users`

Sin `business_id`. La relación es `User::business()` hasOne `Business` (`businesses.user_id`).

### Relaciones

- `User` hasOne `Business`
- `Business` belongsTo `User`
- `RepairTicket` / `Customer` **no** ganan `business_id`; se llega a la marca vía `ticket.user.business`

### Modelo `Business`

- Path: `app/Models/Business.php`
- Accessor `logo_url`: URL pública del logo o `null`
- Fillable: `user_id`, `name`, `logo_path`, `primary_color`, `secondary_color`, `sender_name`, `sender_email`

## Backend

### Servicio — `app/Services/BusinessService.php`

```
updateBranding(User $user, array $data, ?UploadedFile $logo): Business
  - firstOrCreate del Business del user
  - actualiza name, colores, sender_name, sender_email
  - si hay logo: guarda en disk `public` bajo `logos/{user_id}`
  - si reemplaza logo, borra el archivo anterior
```

Defaults al crear: `name` = `user.name`, `sender_name` = `user.name`, `sender_email` = `user.email`, colores del schema.

### Settings

- `app/Http/Controllers/Settings/BusinessController.php`
  - `edit`: Inertia `settings/Business`
  - `update`: Form Request + `BusinessService::updateBranding` + toast
- `app/Http/Requests/Settings/UpdateBusinessRequest.php`
  - `name`: required, string, max 255
  - `primary_color` / `secondary_color`: required, regex hex `#RRGGBB`
  - `sender_name`: required, string, max 255
  - `sender_email`: required, email
  - `logo`: nullable, image, max 2MB, mimes jpg/png/webp
- Autorización: el user solo edita su propio `Business`

### Rutas

En `routes/settings.php`, grupo `auth` + `verified`:

```
GET   /settings/business   → BusinessController@edit    (business.edit)
PATCH /settings/business   → BusinessController@update  (business.update)
```

### Inertia share

En `HandleInertiaRequests`:

```
auth.user      → user autenticado (como hoy)
auth.business  → negocio del user o null (id, name, logo_url, primary_color, secondary_color, sender_name, sender_email)
```

No exponer `logo_path` crudo; exponer `logo_url`.

### Correos (enganche spec 02)

En `TicketStatusNotification`:

- Si `ticket.user.business` existe: `from` = sender del negocio; subject y cuerpo usan `business.name`, `logo_url`, colores inline
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

### Settings — `resources/js/pages/settings/Business.vue`

Mismo patrón que `Profile.vue`:

- Heading: “Negocio” / “Logo, colores y remitente de los correos a tus clientes”
- Form: nombre, logo (preview + file), color primario, color secundario, nombre de remitente, correo de remitente
- Submit con Wayfinder

### Nav de settings

En `layouts/settings/Layout.vue`, item “Negocio” → `business.edit`.

### Tipos TS

```
Business = { id, name, logo_url, primary_color, secondary_color, sender_name, sender_email }
Auth = { user: User, business: Business | null }
```

### Pública y correos

- `public/TicketStatus.vue`: header/footer y CSS variables `--business-primary` / `--business-secondary` cuando `branding` no es null
- Plantilla `emails.ticket-status`: rama branded vs app default

El registro (`Register.vue`) **no** se toca.

## Factory y seeder

- `BusinessFactory`: name, colores default, sender_name, sender_email; state `withLogo()` opcional
- `DatabaseSeeder`: opcionalmente un `Business` para `test@example.com` (“Taller Demo”)
- `UserFactory` **no** debe crear Business por defecto (los tests 01–04 no dependen de él)

## Tests

Feature tests nuevos, `RefreshDatabase`:

- Guest no entra a `settings/business`
- User autenticado crea/actualiza su branding (name, colores, remitente)
- Upload de logo guarda archivo y actualiza `logo_path`
- User A no puede actualizar el branding de user B
- Correo de un ticket usa `from` del business cuando existe
- Correo de un user sin business sigue usando config de la app
- Vista pública incluye `branding` cuando el dueño tiene business
- Vista pública sin branding sigue igual que spec 03
- Tickets siguen scoped por `user_id` (regresión: no aparece `business_id` en tickets)

## Criterios de aceptación

- Settings → Negocio permite cambiar marca y logo, con toast de éxito
- `auth.business` está disponible en páginas autenticadas
- Correos y `/t/{token}` usan la marca si el usuario la configuró; si no, la app
- Los tickets no cambian de dueño ni de schema (`user_id` se mantiene)
- Los specs 01–04 siguen verdes
- Este spec no se implementa en la primera entrega al subdominio
