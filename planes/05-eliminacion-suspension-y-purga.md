# Plan 05 — Eliminación de cuenta: suspensión 30 días y purga ✅ COMPLETADO

> Responde al punto 4 del dueño: eliminación = **modo suspensión** (sin
> correos ni interacciones, reactivable) durante **30 días**, y después
> "eliminar lo que conocemos de él".

## Contexto

Hoy no existe eliminación de cuenta. Borrar una cuenta Finlia no es un
`DELETE FROM users`: el usuario puede ser **dueño o miembro de un hogar
compartido** cuyo historial financiero (movimientos, presupuestos, deudas,
metas) **no le pertenece solo a él** — pertenece a la familia. Borrar en
cascada destruiría las finanzas de otras personas; no borrar nada retiene
datos personales para siempre (Ley 1581, derecho de supresión).

## Decisión

### A. Modo suspensión (`deletion_requested_at`)

- Columna `users.deletion_requested_at` (timestamp null). Null = cuenta
  normal.
- Al pedirla desde `/perfil` (formulario con confirmación seria: escribir
  "ELIMINAR" o contraseña — no un botón suelto), se marca la fecha y:
  - **Sesiones invalidadas** (`logoutOtherDevices` + logout): no puede
    navegar.
  - **Login bloqueado** durante la suspensión con pantalla honesta:
    "Tu cuenta está en proceso de eliminación el <fecha>. Puedes
    reactivarla entrando con tu contraseña" — reactivar = simplemente
    login exitoso → limpia `deletion_requested_at`. (El "modo
    suspensión" no es un estado opaco: reactivar es fácil, dentro de la
    ventana.)
  - **Sin correos**: el digest excluye usuarios suspendidos (junto al
    filtro de verificados del plan 01); invitations pendientes de ese
    correo se cancelan.
  - **Sin interacciones**: middleware `account.active` tras `auth` que
    redirige cualquier ruta privada a la pantalla de suspensión. Token de
    recordatorio de contraseña sigue funcionando (es la otra vía de
    reactivación).
- La suspensión **no toca ningún dato financiero**: nada de borrado
  inmediato (arrepentimiento = cero pérdida).

### B. Purga (día 30+): anonimizar al usuario, preservar al hogar

Comando diario `finlia:purge-pending-deletions` (programado en el
scheduler junto al digest), que procesa usuarios con
`deletion_requested_at <= now() - 30 días`:

1. **Usuario miembro de hogar compartido** (hay otros miembros):
   - Anonimizar `users`: `name = 'Usuario eliminado'`, `email` único
     sintético (`deleted+<id>@finlia.invalid`), `password` hash aleatorio,
     `birth_date`/`region`/`gender` null (plan 04), tokens y columnas
     pending (plan 02) a null, preferencias de correo off.
   - **Preservar movimientos y historial del hogar**: siguen con su
     `user_id` (integridad de las finanzas familiares; el registro ya no
     apunta a persona identificable). Enviar a la papelera los
     `household_invitations` que emitió.
2. **Usuario dueño único de un hogar**: no hay familia que preserve nada
   → **borrar hogar y todo su contenido** (transacción): cuentas,
   movimientos, presupuestos, deudas, metas, recordatorios, invitations.
   Es el único caso de cascade real.
3. **Usuario dueño con otros miembros**: transferir ownership al miembro
   activo más antiguo (si no hay ninguno activo, aplica la regla 2) +
   anonimización de la regla 1. **⚠ DECISIÓN**: ¿avisar al nuevo dueño
   por correo? Recomendación: sí, un único correo transaccional
   informativo.
4. Log de auditoría de cada purga (user id original + fecha + regla
   aplicada), sin datos personales en el log.

### C. Lo que NO se borra nunca automáticamente

- `terms_acceptances` (plan 03): son la **prueba del consentimiento**;
  se retienen desvinculadas del usuario anonimizado mientras la versión
  tenga valor probatorio, y se purgan cuando la versión caduque.
- `last_reminder_digest_at` y preferencias: se limpian en la
  anonimización.
- Backups de hosting: fuera de alcance técnico (documentar en la
  política, plan 06: "los backups rotan en N días").

## Alcance

- [ ] Migración `users.deletion_requested_at`.
- [ ] Pantalla de solicitud en `/perfil` (confirmación grave + aviso de
      "tienes X días para reactivar; luego borramos lo tuyo" + enlace a
      exportar datos, plan 06).
- [ ] Middleware `account.active` + pantalla de suspensión/reactivación
      (login desbloquea y reactiva).
- [ ] Comando `finlia:purge-pending-deletions` + scheduler (diario,
      `withoutOverlapping`).
- [ ] Exclusión del digest + cancelación de invitations.
- [ ] Reglas de purga (anonimizar / cascade dueño único / transferir) en
      un **Service** (`AccountDeletionService` — lógica de dominio, no
      controlador).
- [ ] Tests: suspensión bloquea navegación y correos; reactivación en
      día 29 funciona; purga miembro anonimiza sin tocar movimientos del
      hogar; purga dueño único borra todo (assert missing en todas las
      tablas del hogar); transferencia de ownership; idempotencia
      (re-ejecutar no re-procesa); aislamiento.

## ⚠ DECISIONES pendientes

1. **¿Avisar por correo al confirmar la suspensión?** ("Pediste eliminar
   tu cuenta; tienes 30 días para deshacerlo"). Recomendación: sí — es
   antifraude (si alguien más lo pidió, el dueño real se entera) y es un
   correo transaccional justificado bajo ADR-0015.
2. **¿Purgar cuentas nunca verificadas (plan 01) aquí?** Recomendación:
   sí, segunda regla del mismo comando (`created_at <= now() - 14 días`
   AND `email_verified_at IS NULL` AND sin actividad).
3. **Retention de aceptaciones de términos** — cuánto tiempo conservar
   la prueba (recomendación: mientras la versión sea referenciable; a
   definir con el texto legal del plan 03/06).

## Docs al implementar

- ADR nuevo: "Eliminación de cuenta: suspensión, anonimización y
  cascada solo para dueño único" (las 3 reglas + por qué el historial
  familiar se preserva).
- SECURITY (supresión Ley 1581), DATA_MODEL (`users` + comportamiento),
  ARCHITECTURE (nuevo comando + middleware), DEPLOYMENT (nada nuevo:
  mismo scheduler), CHANGELOG.

Tamaño: **L** — el más grande de la serie; su puerta de entrada es el
rechazo de términos (plan 03) y depende de 04 (qué campos se purgan).
