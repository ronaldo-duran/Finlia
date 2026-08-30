# Plan 06 — Política de retiro de software, migración y gestión de datos

> Responde al punto 7 del dueño: "Política de Retiro de Software,
> Migración y Gestión de Datos".

## Contexto

Un SaaS que maneja finanzas familiares (camino a la Épica 12) necesita
responder, de forma pública y verificable: **¿qué pasa con mis datos si
Finlia cierra, cambia de dueño, o yo me quiero ir?** Eso es la política
de retiro/migración. Hoy la app tiene el mecanismo (export es la mitad;
eliminación es el plan 05) pero **no tiene ni la página pública ni el
compromiso escrito**.

Este plan tiene dos mitades: (A) el texto público — dueño del producto,
no el agente; (B) el mecanismo que hace **creíble** ese texto — export
real de datos, que sí es nuestro.

## Decisión

### A. La política pública (texto del dueño)

- Página pública `/datos` ("Tus datos y Finlia"): what-we-store,
  portabilidad, retención, retiro del servicio.
- Contenido **mínimo** que el mecanismo ya respalda (el resto lo define
  el dueño):
  1. Qué datos guardamos (`users` + datos por hogar) y para qué.
  2. **Portabilidad**: puedes descargar todo (export de la mitad B).
  3. **Eliminación**: cómo pedirlla, ventana de 30 días, qué se conserva
     del hogar compartido (plan 05) — honestidad sobre el historial
     familiar.
  4. **Retiro del software**: si Finlia deja de operar, aviso con
     antelación (p. ej. 60 días) + export masivo disponible + borrado
     posterior. Cronograma de backups.
  5. **Migración**: formato de export documentado (README dentro del
     ZIP) para importar a otra herramienta.
- Ubicación en el footer de todas las páginas (junto a `/terminos`),
  accesible sin cuenta — también para quien evalúa antes de registrarse.
- **El agente no redacta el texto definitivo**: se entrega un BORRADOR
  estructurado con los puntos 1–5 y marcadores [COMPLETAR].

### B. Export real de datos (`/perfil/exportar`)

Sin export, la política es promesa vacía. Implementación:

- Botón en `/perfil` → GET `/perfil/exportar` (auth + throttle, p. ej.
  3/día) genera y descarga un **ZIP** con:
  - Un **CSV por entidad** del hogar activo: cuentas, movimientos,
    presupuestos (y partidas), gastos recurrentes, deudas, pagos de
    deuda, metas, aportes a metas, recordatorios — mismas columnas y
    nombres del modelo, fechas DD/MM/AAAA, dinero coma decimal.
    **Abrible en Excel Colombia** (BOM UTF-8 + separador `;`).
  - Un `datos.csv` del usuario: perfil + preferencias (los campos del
    plan 04; contraseña JAMÁS — un hash no es dato portable).
  - Un `README.txt` (español): qué es cada archivo, formato de fechas/
    dinero, y cómo leerlo/importarlo en otra herramienta (creíble para
    el punto 5 de la política).
  - Un `finlia.json` maestro (misma data, para herramientas técnicas/
    migración futura).
- Generación vía stream/queue-safe y **acotada al hogar activo** (la
  misma regla de aislamiento de toda la app: nunca datos de otro hogar;
  si el usuario tiene varios hogares, exporta el activo y puede cambiar
  + repetir).
- Los CSV no incluyen datos de OTROS miembros (nombres/emails ajenos
  quedan fuera): portabilidad propia, no exfiltración.

**Fuera de alcance**: import (migración DESDE otras apps) — problema
distinto, Épica 11+; y export PDF.

## Alcance

- [ ] Ruta + botón `/perfil/exportar` (throttle; Policy: dueño de la
      cuenta).
- [ ] `DataExportService` (app/Services): arma el arreglo por entidad —
      lógica de dominio reutilizable (seam Épica 14: la futura API
      puede servir el mismo JSON).
- [ ] ZIP descargable (CSV BOM `;` + README + JSON maestro).
- [ ] Página pública `/datos` con el BORRADOR estructurado.
- [ ] Enlaces en footer (junto a `/terminos`) y desde el flujo de
      eliminación (plan 05) y el rechazo de términos (plan 03).
- [ ] Tests: export contiene TODAS las entidades del hogar con sus
      valores; **aislamiento** (usuario B nunca ve datos de A en su
      ZIP); sin datos de otros miembros; sin hash de contraseña;
      throttle; ZIP/CSV bien formados (BOM, `;`, comillas).

## ⚠ DECISIONES pendientes

1. **Ventana de aviso de retiro** (¿60 días? ¿90?) y qué prometemos en
   el cierre — es compromiso de negocio, no técnico.
2. ¿Export **todos los hogares** en un solo ZIP vs solo el activo?
   Recomendación: activo + selector (mantiene el ZIP pequeño y claro);
   "todos" como mejora posterior si alguien lo pide.
3. Retención de backups en Hostinger (rotación real de snapshots) —
   responder con lo que el hosting realmente hace, no con una cifra
   inventada.

## Docs al implementar

- ADR nuevo: "Portabilidad: export real como base de la política de
  datos" (ZIP + formatos + lo que nunca se exporta).
- SECURITY (portabilidad y límites), ARCHITECTURE (§7 si suma correos:
  ninguno), DATA_MODEL (ruta), CHANGELOG.

Tamaño: **M**. Cierra la serie: la eliminación (05) enlaza aquí y la
política (A) solo se publica cuando (B) existe.
