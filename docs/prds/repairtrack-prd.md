# RepairTrack — PRD (MVP)

## 1. Resumen

RepairTrack es una plataforma SaaS brandable en Laravel para que negocios de reparación de celulares, consolas de videojuegos y equipos electrónicos lleven el control de sus procesos de arreglo. Cada negocio tiene su propia marca (logo, colores, nombre de remitente), y sus clientes reciben notificaciones por correo con un link público para ver el status de su equipo sin necesidad de login.

## 2. Problema a resolver

Los talleres de reparación pequeños suelen llevar el control de sus arreglos de forma informal (WhatsApp, cuadernos, Excel), lo que genera:
- Falta de trazabilidad del estado de cada equipo
- Clientes llamando/escribiendo repetidamente para preguntar "¿cómo va mi equipo?"
- Sin historial estructurado de trabajos, clientes o costos

## 3. Objetivo del MVP

Dar a un negocio de reparación una herramienta simple para:
1. Registrar un equipo que entra a reparación
2. Actualizar su estado a medida que avanza el proceso
3. Notificar automáticamente al cliente por correo en cada cambio de estado, con la marca del negocio
4. Que el cliente pueda ver el status en cualquier momento vía un link público, sin login
5. Que el negocio tenga un dashboard con todos los trabajos, filtrable

## 4. Usuarios

- **Negocio (admin/técnico):** usa el dashboard para crear, actualizar y consultar tickets de reparación.
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

### 6.1 Gestión de negocio (branding)
- Registro del negocio: nombre, logo, color primario/secundario, nombre y correo de remitente
- Estos datos se usan dinámicamente en las plantillas de correo y en la vista pública de status

### 6.2 Gestión de tickets
- Crear ticket: datos del cliente (nombre, teléfono, correo), tipo/marca/modelo de equipo, problema reportado, costo estimado, fecha de recepción, fecha estimada de entrega
- Actualizar estado del ticket (con nota opcional, ej. "esperando pantalla")
- Ver historial completo de estados de un ticket (timeline)

### 6.3 Notificaciones por correo
- Al crear el ticket y en cada cambio de estado, se envía un correo automático al cliente
- El correo usa el branding del negocio (logo, colores, nombre de remitente)
- El correo incluye un link único (token) a la vista pública de status
- Envío vía cola (queue) para no bloquear la UI

### 6.4 Vista pública de status
- Ruta accesible sin login vía token único por ticket
- Muestra: datos básicos del equipo, estado actual, timeline de estados, marca del negocio
- No expone datos sensibles del negocio ni de otros clientes

### 6.5 Dashboard
- Listado de todos los trabajos, con foco en los pendientes (no entregados)
- Filtros: por estado, por fecha de recepción, por fecha estimada de entrega, por tipo de equipo, búsqueda por cliente
- Indicador visual de tickets atrasados (fecha estimada de entrega ya pasada y aún no listos)

## 7. Modelo de datos (alto nivel)

- **businesses** — datos del negocio y su branding
- **users** — usuarios del negocio (admin/técnico); en MVP puede ser un solo usuario por negocio
- **customers** — clientes del negocio, reutilizables entre tickets
- **repair_tickets** — ticket de reparación, con `public_token` único para la vista pública
- **ticket_status_history** — historial de cada cambio de estado, con nota y quién lo cambió
- **ticket_notifications** — log de correos enviados por ticket (para debug/soporte)

## 8. Fuera de alcance en el MVP (v2+)

- Múltiples técnicos por negocio con su propia cola de trabajos
- Notificaciones por SMS/WhatsApp
- Cobros/pagos integrados dentro de la plataforma
- Dominio propio por negocio (más allá del subdominio)
- Historial de reparaciones por cliente como vista dedicada

## 9. Stack técnico propuesto

- Backend: Laravel
- Frontend: Vue + Inertia.js
- Notificaciones: Laravel Notifications + Queue (Redis)
- Base de datos: MySQL

## 10. Métrica de éxito del MVP

Un negocio real puede registrar un equipo, moverlo por todos los estados, y su cliente puede seguir el status del equipo desde el correo recibido, sin llamar al negocio para preguntar.
