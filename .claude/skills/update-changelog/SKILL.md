---
name: update-changelog
description: Publica una versión de Finlia en CHANGELOG.md a partir de los cambios reales del trabajo reciente (entrega mayor, fix, refactor o docs) y sincroniza la versión del software en config/finlia.php y package.json. Mantiene el formato Keep a Changelog en español con versionado SemVer pre-MVP (0.x, sin tags). Invócala como /update-changelog al cerrar una entrega, antes de un merge a main o cuando el usuario pida "actualiza el changelog" o "nueva versión". También define el flujo del primer tag (MVP).
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

## 2. Versionado (fase pre-MVP, sin tags)

- **SemVer en `0.x`**: cada entrega mayor de funcionalidad (lo que internamente el
  roadmap llama "épica") publica un **minor**: `0.1.0` fundación, `0.2.0` hogares,
  `0.3.0` cuentas/ingresos/gastos, `0.4.0` presupuestos, …
- Correcciones y cambios transversales posteriores publican un **patch** (`0.N.P`) en
  sección propia con su fecha.
- La **versión vigente** es la más reciente del changelog y debe coincidir con
  `config/finlia.php` (`'version'`) y `package.json` (`"version"`).
- No se generan tags todavía: el primer tag marcará el **MVP** (ver sección 5).

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

## 5. Flujo del primer tag (MVP)

Ejecutar **solo cuando el usuario lo pida explícitamente** (decisión del equipo: no se
generan tags antes de liberar el MVP):

1. El tag taguea la **versión vigente** en ese momento, sobre `main` verde:
   `git tag -a vX.Y.Z -m "Finlia MVP"`. El push del tag lo decide el usuario.
2. **Eliminar `scrum/epics/*.md`**: con el primer tag el MVP queda liberado y los
   ficheros de planificación se borran del repositorio (decisión del equipo). Antes de
   borrar:
   - Verifica con Grep que nada referencie `scrum/epics` y quede roto (ROADMAP,
     CLAUDE.md, AGENTS.md, skills); ajusta esos textos al estado "planificación
     completada, histórico en CHANGELOG.md".
   - Confirma la lista de borrados con el usuario antes de ejecutarlo.
3. Tras el tag, continuar con SemVer normal (`0.x+1` o `1.0.0` según decida el equipo).

## 6. Cierre

Entrega al usuario: sección de versión creada, commits que soportan cada entrada,
versión sincronizada en `config/finlia.php` + `package.json`, y cualquier diferencia
entre lo pedido y lo encontrado (p. ej. cambios sin commit).
