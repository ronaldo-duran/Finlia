<x-mail::message>
# Tus datos están listos

Hola **{{ $user->name }}**,

Adjuntamos el archivo ZIP con todos los datos de tu hogar activo en Finlia.
El archivo contiene tus registros en CSV (para abrir en Excel) y JSON (para migración técnica).

**¿Qué hay en el ZIP?**
- Cuentas, ingresos, gastos, presupuestos
- Gastos recurrentes, deudas, metas de ahorro
- Recordatorios y perfil de usuario

> Los datos personales de otros miembros de tu hogar no están incluidos.
> Tu contraseña nunca aparece en ningún archivo.

Puedes solicitar una nueva exportación desde tu perfil cuando necesites datos actualizados.

<x-mail::button :url="route('profile.edit')" color="primary">
Ir a mi perfil
</x-mail::button>

Si no solicitaste esta exportación, escríbenos de inmediato.

Finlia · Finanzas familiares
</x-mail::message>
