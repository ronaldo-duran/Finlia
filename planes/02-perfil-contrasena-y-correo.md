# Plan 02 — Perfil: cambio de contraseña y cambio de correo

> Responde a los puntos 2 y 3 del dueño. **Hoy NO existe ninguno de los
> dos**: la única vía de cambiar contraseña es recuperarla por correo, y el
> correo no se puede cambiar jamás (verificado con el código del
> 2026-08-30: sin rutas de perfil, sin `current_password` en la app).

## Contexto

- **Contraseña**: sin pantalla de perfil. Si el usuario está logueado y
  quiere rotar su contraseña, debe hacer el flujo completo de "olvidé mi
  contraseña" — que además solo funciona si accede a su correo.
- **Correo**: imposible de cambiar. Y con la verificación del plan 01 en
  marcha, cambiarlo "a lo bruto" rompería el invariante de identidad:
  un correo nuevo sin verificar no puede ser la llave de la cuenta ni
  destino del digest.

## Decisión

### A. Pantalla `/perfil` (nueva)

Sección "Mi cuenta" con: nombre, correo (con estado verificado y flujo de
cambio), contraseña, y — cuando llegue el plan 04 — fecha de nacimiento,
región y género. Es preferencia del **usuario**, no del hogar: la pantalla
vive fuera del multi-tenant de finances (no requiere `household`).

### B. Cambio de contraseña (simple, con re-autenticación)

1. Formulario: contraseña actual + nueva + confirmación.
2. Validación: `current_password` (Laravel) contra el hash del usuario.
3. Al cambiar: `Auth::logoutOtherDevices($nueva)` (invalida otras sesiones
   y remember tokens) + mantener la sesión actual. Re-login no necesario.
4. Correo de aviso al propio correo ("tu contraseña cambió; si no fuiste
   tú, usa recuperar contraseña") — patrón estándar antifraudulento.

### C. Cambio de correo con doble confirmación

El nuevo correo **debe verificarse antes** de reemplazar al actual:

1. Columnas nuevas en `users`: `pending_email` (string null),
   `pending_email_token` (string null, **hash** del token público — patrón
   de `household_invitations`), `pending_email_requested_at` (timestamp
   null, expiración 60 min).
2. Form en `/perfil`: pide correo nuevo (unique contra `email` **y** contra
   `pending_email` de otros). No cambia nada todavía.
3. Correo de confirmación **al correo NUEVO** (solo quien controla esa
   bandeja puede completar el cambio) con enlace firmado por hash.
4. Al confirmar (GET público con token, como `restablecer-contrasena`):
   - `email = pending_email`, `email_verified_at = now()` (verificado por
     construcción), limpieza de las columnas pending.
   - Correo de aviso **al correo antiguo**: "tu correo de Finlia cambió a
     X; si no fuiste tú → cambia tu contraseña / recuperar acceso".
   - Sesión intacta (mismo user id): hogares, digests y preferencias
     siguen igual — el digest del plan de correos no necesita cambios
     porque consulta el `email` vigente en cada corrida.
5. Si el usuario era miembro con digest activo, la preferencia del pivote
   no se toca: sigue activa, ahora hacia el nuevo correo.

## Alcance

- [ ] Ruta+pantalla `/perfil` (GET) + `PUT /perfil/datos` (nombre).
- [ ] `PUT /perfil/contrasena` (Request con `current_password` + reglas
      fuertes; Policy: solo el propio usuario — `updateOwnProfile`).
- [ ] `PUT /perfil/correo` (arranca el flujo pending) + GET público
      `confirmar-correo/{token}`.
- [ ] Dos correos propios (aviso contraseña, confirmación correo nuevo) +
      aviso al correo antiguo. Estilo Finlia, español, transaccionales
      (ADR-0015).
- [ ] Tests: contraseña correcta/incorrecta; otras sesiones mueren; correo
      en uso rechazado (incluye pending ajeno); token válido cambia y
      verifica; token inválido/expirado rechaza; aviso al correo antiguo;
      aislamiento (nadie cambia el perfil de otro).

**No incluye**: 2FA (ni existe épica que lo pida), historial de sesiones.

## Docs al implementar

- ADR nuevo: "Cambio de correo con doble confirmación" (el invariante:
  ningún correo entra a `users.email` sin haber sido verificado).
- SECURITY (gestión de credenciales), ARCHITECTURE §7 (política de correo:
  +2 transaccionales de seguridad), DATA_MODEL (`users` nuevas columnas).

Tamaño: **M**. Idealmente junto con el plan 04 (misma pantalla).
