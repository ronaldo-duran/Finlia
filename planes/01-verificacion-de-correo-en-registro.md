# Plan 01 — Verificación de correo en el registro

> Respondes a la pregunta del dueño: *"¿puedo hacer que registre todo y se
> envíe el correo de confirmación?"* — **Sí.** Esa es exactamente la
> decisión recomendada: la cuenta se crea completa y el correo se valida
> después, bloqueando la app hasta que llegue la confirmación.

## Contexto

Hoy `RegisteredUserController::store()` crea el usuario, su hogar inicial y
hace `Auth::login()` de inmediato. El correo **nunca se verifica**. Con la
Épica 9 esto dejó de ser teórico: **enviamos correos diarios** (digest,
ADR-0028) a la dirección que el usuario tecleó — que puede ser de otra
persona. Igual la recuperación de contraseña: cualquiera puede registrar
`correo@ajeno.com` y hacer que esa persona reciba correos de Finlia.

`User` ya trae el cast de `email_verified_at` y el import de
`MustVerifyEmail` comentado: el seam existe desde la fundación.

## Decisión

1. **Registrar todo + correo de confirmación** (flujo nativo de Laravel):
   - `User implements MustVerifyEmail` (descomentar; el trait
     `Notifiable` ya está).
   - El registro crea usuario + hogar como hoy, **pero no lleva al
     dashboard**: redirige a una pantalla "Revisa tu correo" con el enlace
     de verificación ya enviado.
   - Middleware `verified` sobre **todo** el grupo `auth` (solo logout y
     reenvío quedan accesibles sin verificar).
2. **Correo de verificación propio** (`VerifyEmailMail`): la notificación
   nativa es markdown en inglés; se crea una con el estilo visual de Finlia
   (patrón de la invitación y el digest: HTML autocontenido en español +
   texto plano, ADR-0015). Enlace firmado con expiración (60 min, estándar
   de Laravel).
3. **Reenvío con throttle** (`verification.send`, ~3/minuto por usuario)
   y aviso de "revisa spam". El correo de verificación es transaccional
   imprescindible bajo ADR-0015 (el destinatario no puede ver el aviso
   dentro de la app: no puede entrar).
4. **Digest defensivo**: `finlia:send-reminder-digests` excluye miembros
   con `email_verified_at` null (cinturón y suspenderes: nunca enviar
   correos periódicos a direcciones sin verificar).
5. **Usuarios existentes** (pre-MVP): migración marca
   `email_verified_at = now()` para los ya registrados — no vale la pena
   forzarles el flujo a una base de usuarios de desarrollo.
6. **Anti-squatting (recuperación del correo)**: si alguien registró
   `tu@correo.com` y nunca lo verificó, ese fantasma **no puede bloquear
   al dueño real**: el registro detecta email existente con
   `email_verified_at` null → **borra el fantasma** (usuario + hogar
   vacío, en transacción — es inerte por construcción: sin verificar no
   se puede crear ningún dato) y crea la cuenta nueva. El dueño real
   nunca ve "correo ya registrado".
   - Defensa adicional: "olvidé mi contraseña" siempre cae en la bandeja
     del dueño real — vía de recuperación universal.
   - Nota: solo aplica a cuentas **sin verificar**. Una cuenta verificada
     con ese email prueba que quien la creó controla esa bandeja (no es
     squatting).

## Alcance

> ✅ **Implementado el 2026-08-30** — [ADR-0029](../docs/DECISIONS.md#adr-0029),
> CHANGELOG 0.15.0, 421 tests en verde. Lo que sigue es el alcance original
> (cumplido) tal como se planeó.

**Incluye**
- [x] `User implements MustVerifyEmail` + notificación propia en español.
- [x] Rutas: `verificar-correo` (aviso), enlace de verificación firmado
      (GET público), reenvío (POST, throttle). Nombres en español, estilo
      del resto (`recuperar-contrasena`).
- [x] Middleware `verified` aplicado al grupo auth (tras `auth`).
- [x] Vista `auth/verify-email` (aviso + reenviar + "usar otro correo"
      → pista hacia soporte/cambio, sin flujo complejo).
- [x] Migración puntual para usuarios existentes.
- [x] Exclusión de no-verificados en el comando del digest.
- [x] Tests: registro no loguea directo; verificación con firma válida
      activa; firma inválida/expirada rechaza; reenvío con throttle;
      usuario no verificado obtiene redirect al aviso en cualquier página
      privada; el correo renderiza en español; **registro sobre un email
      fantasma sin verificar lo reclama** (borra fantasma + hogar vacío,
      crea la cuenta nueva, sin error al usuario).

**No incluye** (viven en otros planes): cambio de correo (02), reenvío del
hogar inicial si el correo nunca se verifica (la cuenta queda inerte: sin
datos sensibles más allá del hogar vacío — aceptable).

## ⚠ DECISIÓN pendiente

- ¿Purgar cuentas que nunca verificaron su correo (p. ej. a los 14 días)?
  Recomendación: sí, reutilizar el comando de purga del plan 05 con una
  regla propia; evita acumular hogares fantasma. Es **higiene de fondo**,
  no el mecanismo de desbloqueo (ese ya lo da la regla 6: reclamar en el
  registro). Puede posarse al plan 05.

## Docs al implementar

- ADR nuevo: "Verificación de correo obligatoria" (enmienda implícita a la
  tabla de correos de ADR-0015/ARCHITECTURE §7: pasa a 4 correos).
- `docs/SECURITY.md` (identidad), ROADMAP, CHANGELOG, DEPLOYMENT (nada nuevo
  en .env: usa el SMTP ya configurado).

Tamaño: **M**.
