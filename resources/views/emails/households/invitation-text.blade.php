{{-- Versión en texto plano (mejora la entregabilidad y sirve en clientes sin HTML). --}}
@if ($invitedByName){{ $invitedByName }} te invitó a unirte al hogar "{{ $householdName }}" en {{ $appName }}.@else Te invitaron a unirte al hogar "{{ $householdName }}" en {{ $appName }}.@endif

Acepta la invitación aquí:
{{ $acceptUrl }}

La invitación caduca el {{ $expiresAt->format('d/m/Y') }} y solo puede usarse una vez.
Necesitas una cuenta de {{ $appName }} con este mismo correo para aceptarla.

Si no esperabas esta invitación, ignora este mensaje: sin aceptarla nadie accede a tus datos.
{{ $appName }} solo te escribe para cosas imprescindibles como esta.
