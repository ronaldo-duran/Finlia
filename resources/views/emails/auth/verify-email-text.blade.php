{{-- Versión en texto plano (mejora la entregabilidad y sirve en clientes sin HTML). --}}
Confirma tu correo en {{ $appName }}

Hola {{ $userName }}, tu cuenta en {{ $appName }} ya está creada. Solo falta confirmar tu correo para activarla:

{{ $verificationUrl }}

El enlace vence el {{ $expiresAt->copy()->timezone(config('app.timezone'))->format('d/m/Y, g:i a') }} (una hora). Si vence, puedes pedir uno nuevo desde la app.

Si no creaste esta cuenta, ignora este mensaje: sin confirmar el enlace, la cuenta no se activa y no recibirá más correos de {{ $appName }}.
