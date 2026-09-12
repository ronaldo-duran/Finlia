---
name: update-changelog
description: Publica una versión de Finlia en CHANGELOG.md a partir de los cambios reales del trabajo reciente (entrega mayor, fix, refactor o docs) y sincroniza la versión del software en config/finlia.php y package.json. Mantiene el formato Keep a Changelog en español con versionado SemVer 0.x. Invócala como /update-changelog al cerrar una entrega, antes de un merge a main o cuando el usuario pida "actualiza el changelog" o "nueva versión". También define cómo se taguean las versiones publicadas.
---

# Skill: update-changelog

Publica versiones en [CHANGELOG.md](../../../CHANGELOG.md) **a partir de
commits/diffs reales**, nunca de memoria. La fuente de verdad es `git log` / `git diff`
/ el código.

## 1. Recopilar qué cambió

- `git log --oneline` desde el último commit ya registrado en el changelog (o el
  `--stat` del commit de la entrega).
- Si el cambio aún no tiene commit, revisa el working tree (`git status`, `git diff`).
- Ante la duda sobre una funcionalidad, **verifícala en el código** (rutas, servicios,
  tests) antes de escribirla. No inventes ni sobreestimes.

## 2. Versionado (fase `0.x`)

- **SemVer en `0.x`**: cada entrega mayor de funcionalidad (lo que internamente el
  roadmap llama "épica") publica un **minor**: `0.1.0` fundación, `0.2.0` hogares,
  `0.3.0` cuentas/ingresos/gastos, `0.4.0` presupuestos, …
- Correcciones y cambios transversales posteriores publican un **patch** (`0.N.P`) en
  sección propia con su fecha.
- La **versión vigente** es la más reciente del changelog y debe coincidir con
  `config/finlia.php` (`'version'`) y `package.json` (`"version"`).
- **Cada versión publicada se taguea** (ver sección 5). No todas las versiones tienen
  tag: las hay publicadas sin él (0.33.0 y 0.34.0), y no se crean a posteriori.

## 3. Formato (obligatorio)

- **Keep a Changelog 1.1.0 en español**, secciones ordenadas de la versión **más
  reciente a la más antigua**:

  ```markdown
  ## [0.N.0] - AAAA-MM-DD — Título corto de la entrega

  ### Añadido
  - Cambio orientado al usuario, breve y concreto.
  ```

- Categorías: `Añadido`, `Cambiado`, `Corregido`, `Eliminado`, `Seguridad` (usa solo
  las que apliquen).
- Habla de **funcionalidad y versiones**, no de gestión interna: no menciones épicas,
  sprints ni ramas en el texto (las rutas de archivo solo si son el entregable:
  migraciones, skills, docs).
- Bullets en español, una línea cada uno (dos como máximo). Referencia el ADR cuando
  exista (`ADR-00XX`) y el nombre del service/componente si aporta.
- Nunca registres secretos, datos reales ni información interna.

## 4. Publicar una versión (checklist)

1. Añade la sección de versión nueva (minor o patch según la sección 2) con la fecha
   de hoy.
2. **Sincroniza la versión del software** en los dos sitios (deben quedar iguales):
   - `config/finlia.php` → clave `'version'` (el footer la muestra vía
     `config('finlia.version')`).
   - `package.json` → campo `"version"`.
3. Edita solo la sección nueva; no reescribas histórico.
4. No commitear salvo petición explícita del usuario (igual que el resto del repo).

## 5. Tags

Ejecutar **solo cuando el usuario lo pida explícitamente**:

1. El tag es **anotado**, sobre el commit de merge de la versión en `main` y con el CI
   en verde: `git tag -a vX.Y.Z -m "Finlia vX.Y.Z — <título de la versión>"`, el mismo
   título de la sección del changelog. Comprueba antes que la versión coincide en
   changelog, `config/finlia.php` y `package.json`.
2. **El push lo decide el usuario**: `git push origin vX.Y.Z`.
3. No se tagea hacia atrás: una versión que se publicó sin tag se queda sin él.

> Las **épicas completadas se liberaron** al publicar la v0.34.1: sus fichas salieron
> de `scrum/epics/` y su histórico vive en este changelog y en los ADR de
> [docs/DECISIONS.md](../../../docs/DECISIONS.md). Las épicas aún abiertas conservan su
> ficha. Cuando se cierre una, borra su ficha en la misma entrega que la publica.

## 6. Cierre

Entrega al usuario: sección de versión creada, commits que soportan cada entrada,
versión sincronizada en `config/finlia.php` + `package.json`, y cualquier diferencia
entre lo pedido y lo encontrado (p. ej. cambios sin commit).
