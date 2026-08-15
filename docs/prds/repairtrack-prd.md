# RepairTrack — PRD (MVP)

> **Recorte de implementación:** la primera entrega (subdominio) es **por persona**: una cuenta = un técnico. Tickets, clientes y precios pertenecen al `User` y no se comparten con nadie del mismo local. El cobro es manual (PayPal / Binance + comprobante). Branding personal (logo, colores, remitente) queda para la siguiente iteración. Ver [docs/specs](../specs/README.md).

## 1. Resumen

RepairTrack es una plataforma SaaS en Laravel para que **un técnico** de reparación (celulares, consolas, laptops u otro equipo) lleve el control de *sus* arreglos. No es un sistema de taller: es el cuaderno de una persona.

Cada cuenta es de un técnico. Sus clientes, lo que cobra y sus tickets son solo suyos — aunque comparta mostrador con otros. En la siguiente iteración, cada técnico puede poner su marca (nombre, logo, colores, remitente) en los correos y en la vista pública de status. El cliente recibe un link para ver el estado de su equipo sin login.

El plan es $3 USD al mes **por persona**. Tres técnicos en el mismo local = tres cuentas.

## 2. Problema a resolver

Los técnicos de reparación suelen llevar el control de forma informal (WhatsApp, cuadernos, Excel), lo que genera:
- Falta de trazabilidad del estado de cada equipo
- Clientes llamando/escribiendo repetidamente para preguntar "¿cómo va mi equipo?"
- Sin historial estructurado de trabajos, clientes o costos

En un local suelen convivir varios técnicos independientes (consolas, teléfonos, laptops). Cada uno conoce a *sus* clientes y cobra lo suyo. Un sistema de “taller compartido” mezclaría esa información; RepairTrack no lo hace.

## 3. Objetivo del MVP

Dar a un técnico una herramienta simple para:
1. Registrar un equipo que le dejan a reparación
2. Actualizar su estado a medida que avanza el proceso
3. Notificar automáticamente al cliente por correo en cada cambio de estado (marca personal: siguiente iteración)
4. Que el cliente pueda ver el status en cualquier momento vía un link público, sin login
5. Que el técnico tenga un dashboard con todos **sus** trabajos, filtrable
6. Que el técnico pague $3/mes por PayPal o Binance (comprobante + revisión manual); sin pago marcado en los primeros 5 días del mes, la cuenta se suspende

## 4. Usuarios

- **Técnico (usuario):** usa el dashboard para crear, actualizar y consultar *sus* tickets de reparación; paga la suscripción desde Facturación. No ve clientes, precios ni tickets de otro usuario.
- **Admin de la plataforma:** inicia sesión, revisa comprobantes y marca pagado o rechazado. No es un técnico.
- **Cliente final:** solo recibe correos y accede a la vista pública de status vía link. No tiene cuenta ni login.

## 5. Estados del ticket

1. `received` — Recibido
2. `in_review` — En revisión
3. `in_repair` — En reparación
4. `waiting_approval` — Esperando aprobación/repuesto (cliente debe aprobar costo o se espera una pieza)
5. `ready` — Listo para entregar
6. `delivered` — Entregado
7. `not_repairable` — No reparable / Cancelado (estado alterno, sale del flujo normal)

## 6. Funcionalidades del MVP

### 6.1 Marca personal (branding) — siguiente iteración
- El técnico configura su marca: nombre (cómo lo ve el cliente), logo, color primario/secundario, nombre y correo de remitente
- Estos datos se usan dinámicamente en las plantillas de correo y en la vista pública de status
- **No entra en la primera entrega.** Hasta entonces, correos y vista pública usan la marca de la app.

### 6.2 Gestión de tickets
- Crear ticket: datos del cliente (nombre, teléfono, correo), tipo/marca/modelo de equipo, problema reportado, costo estimado, fecha de recepción, fecha estimada de entrega
- Actualizar estado del ticket (con nota opcional, ej. "esperando pantalla")
- Ver historial completo de estados de un ticket (timeline)

### 6.3 Notificaciones por correo
- Al crear el ticket y en cada cambio de estado, se envía un correo automático al cliente
- El correo usa el branding de la app en la primera entrega; logo, colores y remitente del técnico en la siguiente iteración
- El correo incluye un link único (token) a la vista pública de status
- Envío vía cola (queue) para no bloquear la UI

### 6.4 Vista pública de status
- Ruta accesible sin login vía token único por ticket
- Muestra: datos básicos del equipo, estado actual, timeline de estados; marca del técnico en la siguiente iteración
- No expone datos sensibles del técnico ni de otros clientes

### 6.5 Dashboard
- Listado de todos **sus** trabajos, con foco en los pendientes (no entregados)
- Filtros: por estado, por fecha de recepción, por fecha estimada de entrega, por tipo de equipo, búsqueda por cliente
- Indicador visual de tickets atrasados (fecha estimada de entrega ya pasada y aún no listos)

### 6.6 Cobro manual (PayPal / Binance)
- Un plan: $3 USD al mes **por persona**. El usuario paga por PayPal o Binance ID (sin Stripe ni cargo automático)
- Puede usar la app al registrarse. Si se registra después del día 5, el primer corte es el día 5 del mes siguiente; si no, el día 5 del mes actual
- Sube la imagen del comprobante; el pago queda pendiente de revisión y el admin recibe un correo con link a la web (debe iniciar sesión)
- El admin marca pagado o rechazado. Pagado reactiva la cuenta
- Recordatorios por correo los días 1, 3 y 5 a quien aún no tiene el periodo marcado como pagado (con link a Facturación)
- Si el día 6 la cuenta no está **marcada como pagada**, se suspende: no usa dashboard ni tickets; sí facturación y settings. Un comprobante pendiente no evita la suspensión
- La vista pública de status de sus clientes sigue accesible
- Detalle implementable: [spec 06](../specs/06-manual-billing.md)

## 7. Modelo de datos (alto nivel)

- **users** — dueño de tickets y clientes (todo scoped a `user_id`); `is_admin` y `subscription_status`
- **customers** — clientes del usuario, reutilizables entre *sus* tickets
- **repair_tickets** — ticket de reparación, con `public_token` único para la vista pública
- **subscription_payments** — comprobantes por periodo (`YYYY-MM`): pendiente / pagado / rechazado
- **subscription_reminder_logs** — un recordatorio por usuario, periodo y tipo (días 1, 3 y 5)
- **brands** — perfil de marca 1:1 del usuario (siguiente iteración; no cambia la dueñez de los tickets)
- **ticket_status_history** — historial de cada cambio de estado, con nota y quién lo cambió
- **ticket_notifications** — log de correos enviados por ticket (para debug/soporte)

## 8. Fuera de alcance en el MVP (v2+)

- Cuentas compartidas de un local, roles de taller, o visibilidad cruzada entre técnicos
- Notificaciones por SMS/WhatsApp
- Pasarela automática (Stripe) o cobros del técnico a su cliente dentro del ticket
- Dominio propio por usuario (más allá del subdominio)
- Historial de reparaciones por cliente como vista dedicada

## 9. Stack técnico propuesto

- Backend: Laravel
- Frontend: Vue + Inertia.js
- Notificaciones: Laravel Notifications + Queue (Redis)
- Base de datos: MySQL

## 10. Métrica de éxito del MVP

Un técnico real puede registrar un equipo, moverlo por todos los estados, y su cliente puede seguir el status desde el correo recibido, sin llamarlo para preguntar. Otro técnico del mismo local no ve esos datos.
