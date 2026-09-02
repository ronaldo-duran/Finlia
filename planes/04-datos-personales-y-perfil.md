# Plan 04 — Datos personales y perfil

> Responde al punto 5 del dueño: fecha de nacimiento "es un dato muy útil
> para análisis" + pregunta abierta: *"¿qué más datos? ¿género? ¿región?
> ¿hobbies?"*

## Contexto

`users` hoy solo tiene `name`, `email`, `password` (+ timestamps y
acuses ADR-0024). Cero datos demográficos. Para analítica de producto
(futuro SaaS, Épica 12) y para segmentación del mercado colombiano, la
fecha de nacimiento y la región son las dos señales con mejor
relación valor/coste. Pero cada dato extra es superficie de PII: se
recoge lo que tiene un uso concreto, no "por si acaso" (minimización,
Ley 1581).

## Decisión — qué recogemos (recomendación)

| Dato | ¿Cuándo? | Obligatorio | Por qué / por qué no |
|---|---|---|---|
| **`birth_date`** (date) | Registro + perfil | **Sí** | Lo pide el dueño. Permite cohortes de edad (analítica), y fija la mayoría de edad (ver decisión). Validación: fecha pasada real (no < 1900, no futura). |
| **`region`** (string, select) | Perfil (y opcional en registro) | No | Departamento de Colombia (lista fija de 32). Analítica de mercado COP; barato de responder. |
| **`gender`** (string, select) | Perfil | No | Opciones: Mujer / Hombre / No binario / **Prefiero no decirlo** (default). ⚠ Ronda los datos sensibles de Ley 1581: opcional SIEMPRE, con explicación de para qué, y sin inferencias automáticas. |
| Ocupación | — | — | Fuera de este corte: valor medio, otro input más. Reconsiderar con la Épica 12. |
| Hobbies / intereses | — | — | **No**: casi nulo valor analítico financiero y pura superficie de PII. Si algún día alimenta "recomendaciones", se decide entonces. |

Principio para el futuro: **cada dato nuevo necesita un caso de uso escrito
y su eliminatoria en la purga** (plan 05) — nada de "total, es un campo".

## ⚠ DECISIÓN pendiente (bloquea el formulario de registro)

**¿Exigir mayoría de edad (18+)?** Finlia maneja finanzas y datos de un
hogar completo. Ley 1581: menores de 14 requieren representación legal;
14–17 pueden autorizar tratamiento de datos no sensibles. Recomendación:
**18+** — simplifica el cumplimiento (nada de consentimiento parental) y
el público real (jefes de hogar) lo cumple. La alternativa (14+ con
restrictivo) duplica el trabajo legal para un público marginal.

## Diseño

- Migración `users`: `birth_date` (date, null en DB aunque el registro lo
  exija — usuarios heredados), `region` (string null), `gender`
  (string null). Casts: `birth_date => date:Y-m-d`.
- Form Request del registro (plan 01 lo toca también): + `birth_date`
  required/date/pasado/(18+ si se decide); región y género NO en el
  registro — el perfil es suficiente (menos fricción de entrada).
- `/perfil` (pantalla del plan 02): sección "Datos personales" con los
  tres campos, editable, validada (región en la lista de departamentos,
  género en la lista cerrada).
- Helper de edad para analítica futura (`User::age()`), sin columnas
  derivadas (la edad se calcula, no se almacena — nunca guardar lo
  derivable).
- La **eliminación/purga** (plan 05) pone estos campos en null; el
  **export** (plan 06) los incluye.

## Alcance

> ✅ **Implementado el 2026-08-30** — [ADR-0032](../docs/DECISIONS.md#adr-0032),
> CHANGELOG 0.18.0. La ⚠ DECISIÓN de la mayoría de edad quedó resuelta como
> recomendaba el plan: **18+** (regla compartida `AdultBirthDate`, corte
> inclusivo). Nota: la lista de regiones son 32 departamentos **+ Bogotá
> D.C.** (33; el plan decía "32" — omitir el distrito capital dejaría fuera
> a gran parte del mercado). Lo que sigue es el alcance original (cumplido)
> tal como se planeó.

- [x] Migración + casts + `User::age()`.
- [x] Validación en registro (según decisión 18+) y en perfil.
- [x] Sección "Datos personales" en `/perfil` (selects con lista de
      departamentos — constante/enum propio, no tabla).
- [x] Tests: validaciones (fecha futura, menor de edad si aplica, región
      inválida, género fuera de lista), actualización de perfil,
      aislamiento (nadie edita el perfil de otro).

## Docs al implementar

- [x] ADR nuevo: "Datos demográficos mínimos: qué recogemos y por qué"
      (la tabla de este plan, con fecha) — [ADR-0032](../docs/DECISIONS.md#adr-0032).
- [x] DATA_MODEL (`users`), SECURITY (minimización), CHANGELOG.

Tamaño: **S–M**. Se implementa con el plan 02 (misma pantalla).
