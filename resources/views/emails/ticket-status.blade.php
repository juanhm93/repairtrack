<x-mail::message>
@if ($branding)
{{-- Spec 05: logo, colores y remitente del técnico. Hoy $branding es null. --}}
@endif

# {{ $appName }}

Hola {{ $customerName }},

@if ($statusLabel === 'Recibido')
Recibimos tu equipo y ya está en nuestro registro.
@else
Hay una actualización en la reparación de tu equipo.
@endif

**Equipo:** {{ $equipment }}
**Estado actual:** {{ $statusLabel }}

@if ($note)
{{ $note }}
@endif

<x-mail::button :url="$statusUrl">
Ver el status de tu equipo
</x-mail::button>

Gracias,<br>
{{ $appName }}
</x-mail::message>
