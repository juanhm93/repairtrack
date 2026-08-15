# SPEC-02 — Notificaciones por correo

## Objetivo

Cerrar el PRD §6.3 **sin marca por taller**: al crear un ticket y en cada cambio de estado, el cliente recibe un correo automático con un link único al status. El envío va por cola y queda un log por ticket para soporte.

El remitente y el nombre son los de la app (`config('mail.from')`, `config('app.name')`). Logo, colores y remitente por negocio son el spec 05.

## Dependencias

- Spec 01: `TicketService::create` y `changeStatus`, `public_token`, `Customer.email`
- Queue: tablas `jobs` / `failed_jobs` ya existen; `QUEUE_CONNECTION` default es `database` (el PRD menciona Redis; este spec funciona con el driver configurado)
- Mail: `config/mail.php` (local: `log` / tests: `array`)
- `User` ya usa `Notifiable`; las notificaciones van al **customer**, no al user

## Alcance

- Tabla `ticket_notifications` (log de envíos)
- Notification queued con plantilla de la app (no branded por usuario)
- Disparo desde `TicketService` en create y changeStatus
- Link público `route('public.tickets.show', token)` — la página se implementa en spec 03; el named route y la URL se definen aquí
- Indicador mínimo en `tickets/Show`: último correo enviado
- Tests con fake de notificaciones y assert del log

## Fuera de alcance

- Implementar la página pública (spec 03); solo el URL
- Logo, colores, `sender_name` / `sender_email` por negocio (spec 05)
- SMS / WhatsApp (PRD §8)
- Reenvío manual desde UI, bandeja de notificaciones, o panel de soporte
- Personalizar el cuerpo del correo por estado más allá de subject + label del estado
- Configurar Redis como requisito

## Modelo de datos

### `ticket_notifications`

| Columna | Tipo | Notas |
|---------|------|--------|
| `id` | bigint PK | |
| `repair_ticket_id` | FK `repair_tickets.id` | cascade delete |
| `type` | string | `created` \| `status_changed` |
| `to_email` | string | Destinatario real |
| `status` | string | `queued` \| `sent` \| `failed` |
| `ticket_status` | string | Estado del ticket al momento del envío |
| `error_message` | text, nullable | Si `failed` |
| `created_at` / `updated_at` | timestamps | |

Relación: `RepairTicket::notifications()` hasMany `TicketNotification`.

## Backend

### Notification — `app/Notifications/TicketStatusNotification.php`

- Implements `ShouldQueue`
- Via `mail`
- Constructor: `RepairTicket $ticket`, `string $type` (`created` | `status_changed`)
- Eager-load seguro: el job recarga `ticket.customer` (y `ticket.user` solo si hace falta el nombre del taller en el futuro; ahora no)
- `from`: `config('mail.from.address')` + `config('mail.from.name')` (default de Laravel; no override por usuario)
- Subject:
  - created: “Recibimos tu equipo — {app.name}”
  - status_changed: “Actualización de tu reparación — {status label}”
- Markdown o view (`emails.ticket-status`) con:
  - Nombre de la app (no logo de taller)
  - Nombre del cliente, tipo/marca/modelo, estado actual (label ES)
  - Nota del último cambio de historial, si existe
  - CTA: “Ver el status de tu equipo” → URL pública del token
- No incluir: costo estimado, email/teléfono de otros clientes, datos del usuario dueño del ticket

La plantilla debe poder aceptar branding opcional más adelante (spec 05) sin reescribir el flujo de envío: hoy los extras van vacíos / se ignoran.

### Log de envío

Servicio `app/Services/TicketNotificationService.php`:

```
notify(RepairTicket $ticket, string $type): void
  - crear TicketNotification (status=queued, to_email=customer.email, ticket_status=ticket.status)
  - Notification::route('mail', $email)->notify(...)
```

Registrar `sent` / `failed`:

- Listener de `NotificationSent` → marcar el log más reciente `queued` de ese ticket+type como `sent`
- Listener de `NotificationFailed` (o try/catch en un job wrapper) → `failed` + `error_message`

Si el customer no tiene email (no debería: spec 01 lo exige), no se encola y no se escribe log.

### Enganche en `TicketService`

Al final de la transacción de `create` y de `changeStatus`, llamar `TicketNotificationService::notify`.

Encolar after-commit (`after_commit` / `dispatchAfterCommit`) para no disparar el job antes de que el ticket exista.

`changeStatus` al mismo estado sigue sin notificar (ya lo rechaza spec 01).

### Ruta del link (contrato para spec 03)

Definir ya el named route para que el correo no use URLs hardcodeadas:

```
GET /t/{token}  →  public.tickets.show
```

Registrar la ruta en `web.php` fuera del grupo `auth` apuntando a un controller que spec 03 implementa. Si este spec se mergea solo, el link del correo es válido y la página llega en 03.

No autenticar esta ruta.

## Frontend

No hay módulo de notificaciones.

En `tickets/Show.vue` (spec 01), agregar un renglón secundario:

- “Último correo: {to_email} · {created_at} · {status del log}”
- Si no hay logs: no mostrar el bloque

El controller `show` incluye `latestNotification` (o la última de `notifications`) en los props. No listar el historial completo de correos.

## Factory y seeder

- `TicketNotificationFactory`: type, to_email, status `sent`, ticket_status
- El seeder de tickets (spec 01) puede crear 1–2 logs `sent` en tickets de demo para que Show no quede vacío. No enviar mail real en `db:seed`

## Tests

`tests/Feature/Notifications/` (o equivalente), `RefreshDatabase`, `Notification::fake()`:

- Crear ticket encola `TicketStatusNotification` al email del customer y escribe log
- Cambiar estado encola otra notificación y otro log
- El mail usa `from` = config de la app (no un remitente por usuario)
- El mail contiene el `public_token` / URL `public.tickets.show`
- Ticket de user A no dispara mail al customer de un ticket de user B
- Actualizar tests del spec 01 si ahora el service notifica (`Notification::fake()` en esos tests o en setUp)

`phpunit.xml` ya usa `MAIL_MAILER=array` y `QUEUE_CONNECTION=sync`.

## Criterios de aceptación

- Crear o cambiar estado de un ticket encola un correo al cliente
- El correo se identifica como RepairTrack (nombre/from de la app), no como un taller
- El correo incluye un link `/t/{public_token}`
- Queda un registro en `ticket_notifications` por envío
- Show muestra el último correo
- La UI no se bloquea por SMTP (queue / `ShouldQueue`)
- No hay logo, colores ni remitente por usuario
