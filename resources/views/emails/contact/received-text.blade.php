[{{ $reasonLabel }}] {{ $senderName }}

De: {{ $senderName }} <{{ $senderEmail }}>
Enviado: {{ $sentAt }}
Origen: {{ $fromGuest ? 'sitio público (sin cuenta)' : 'aplicación (con sesión iniciada)' }}

--------------------------------------------------

{{ $body }}

--------------------------------------------------
@if ($context !== [])

Contexto técnico:
@foreach ($context as $clave => $valor)
- {{ $clave }}: {{ $valor }}
@endforeach
@endif

Responde a este correo para contestarle directamente.
