# Plan 03 — Términos y condiciones versionados

> Responde al punto 6 del dueño: *"esto es algo full importante"*.

## Contexto

No existe nada de términos hoy. Requisitos del dueño:

1. El usuario acepta términos y condiciones **de entrada** (como toda
   página).
2. Si los términos **cambian**, el usuario debe **volver a aceptarlos para
   navegar**.
3. Si no quiere aceptar, puede **eliminar su cuenta** (flujo del plan 05).

Además la app maneja **datos financieros familiares**: el registro de
consentimiento con fecha y versión es la prueba ante un reclamo o una
auditoría (Ley 1581).

## Decisión

### Mecanismo de versiones (inmutable + audit-friendly)

- Tabla `terms_versions`: `version` (string único, p. ej. `2026-09-v1`),
  `title`, `content` (longText, el texto completo de ESA versión — **nunca
  se edita**: cambiar los términos = publicar versión nueva), `published_at`.
- Tabla `user_terms_acceptances`: `user_id`, `terms_version_id`,
  `accepted_at`, `ip_address` (nullable).
  - **⚠ DECISIÓN**: guardar IP. Es dato personal (Ley 1581), pero es la
    prueba estándar del consentimiento ("quién, qué versión, cuándo, desde
    dónde"). Recomendación: sí, documentado en la política de datos (plan
    06) con finalidad exclusiva de prueba de aceptación.
  - Unique `(user_id, terms_version_id)` (idempotente, patrón de
    `user_acknowledgements` ADR-0024).
  - **No** reutilizamos `user_acknowledgements`: los avisos UX (ADR-0024)
    son por clave de enum y mutables; los términos necesitan versión con
    contenido histórico inmutable y valor probatorio. Cosas distintas.
- Relación `User::acceptedTerms()`; helper `TermsVersion::current()`
  (la `published_at` más reciente) y `User::hasAcceptedCurrentTerms()`.

### Middleware obligatorio

`terms.current` en el grupo `auth` (después de `auth` + `verified` del
plan 01): si el usuario no tiene aceptación de la versión vigente →
redirect a la pantalla de aceptación. No hay manera de navegar sin
aceptar: cubre el registro nuevo y el cambio de términos en un solo
mecanismo.

### Pantalla de aceptación (`/terminos/aceptar`)

- Texto completo de la versión vigente (scroll, no link externo), con
  fecha de vigencia y, si ya había aceptado otra, un diff/resumen de
  "qué cambió" (campo `change_summary` opcional en la versión).
- Dos botones, sin trampas oscuras:
  - **Aceptar y continuar** → registra aceptación (versión, fecha, IP).
  - **No aceptar** → pantalla honesta: "sin aceptar no puedes usar Finlia;
    puedes **descargar tus datos** (plan 06) y **eliminar tu cuenta**
    (plan 05), o simplemente cerrar sesión". Rechazar **no** borra nada
    por sí solo — nunca acciones destructivas implícitas.

### Publicación de versiones

Pre-MVP, sin panel de administración: se publica por seeder/registro
manual documentado (una fila con `published_at`). Página pública
`/terminos` muestra la vigente; `/terminos/{version}` la histórica
(necesaria como referencia de qué aceptó cada usuario).

### El texto legal

**No lo redacta el agente.** El mecanismo es del plan; la redacción es del
dueño (idealmente con abogado). El plan entrega un `terms_versions` de
ejemplo con marcador claro "BORRADOR — reemplazar por texto legal".

## Alcance

> ✅ **Implementado el 2026-08-30** — [ADR-0031](../docs/DECISIONS.md#adr-0031),
> CHANGELOG 0.17.0, 458 tests en verde. La ⚠ DECISIÓN de la IP quedó
> resuelta como recomendaba el plan: **sí guardar**, nullable, con
> finalidad exclusiva de prueba de aceptamiento (a documentar en la
> política de datos del plan 06). Lo que sigue es el alcance original
> (cumplido) tal como se planeó.

- [x] Migraciones (2 tablas) + modelos + relaciones + casts.
- [x] Middleware `terms.current` + registro en el grupo auth.
- [x] Rutas: GET `/terminos` (pública), GET `/terminos/{version}` (pública),
      GET `/terminos/aceptar`, POST `/terminos/aceptar` (registrar),
      POST `/terminos/rechazar` (landing de salida, sin destruir nada).
- [x] Vistas: aceptación (con texto + botones), salida, pública de términos.
- [x] Seeder de la versión inicial (BORRADOR).
- [x] Tests: sin aceptar → redirect desde cualquier página privada;
      aceptar registra (versión correcta + idempotencia); publicar nueva
      versión re-exige; rechazar no toca datos; histórico accesible.

## Docs al implementar

- [x] ADR nuevo: "Términos versionados con re-aceptación obligatoria"
      ([ADR-0031](../docs/DECISIONS.md#adr-0031)).
- [x] SECURITY (consentimiento y valor probatorio), ARCHITECTURE, CHANGELOG.

Tamaño: **M**. Paralelizable con 01/02.
