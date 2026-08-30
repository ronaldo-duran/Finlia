{{-- Versión en texto plano (mejor entregabilidad, clientes sin HTML). --}}
Confirma tu nuevo correo en {{ $appName }}

Hola {{ $userName }}, se pidió mover la cuenta de {{ $appName }} a este correo ({{ $newEmail }}). Confírmalo aquí para completar el cambio:

{{ $confirmationUrl }}

El enlace vence el {{ $expiresAt->copy()->timezone(config('app.timezone'))->format('d/m/Y, g:i a') }} (una hora). Si vence, pide el cambio de nuevo desde tu perfil.

Si no pediste este cambio, ignora este mensaje: sin confirmar el enlace, tu cuenta sigue con su correo actual y esta dirección no recibirá más correos de {{ $appName }}.
