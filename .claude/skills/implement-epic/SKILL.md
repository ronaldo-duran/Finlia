---
name: implement-epic
description: Procedimiento para implementar una épica de Finlia de principio a fin, siguiendo el protocolo de CLAUDE.md (regla de los 9 pasos) y la Definition of Done de AGENTS.md. Invócala cuando el usuario pida iniciar, implementar o continuar una épica concreta (p. ej. "implementa la épica 3", "continúa con hogares"). Recibe el número de épica como argumento.
---

# Skill: implement-epic

Implementa una épica de Finlia de forma disciplinada. **Argumento esperado**: número de épica (1–13) o su nombre.

## 1. Leer y contextualizar

- Lee la épica: `scrum/epics/ÉPICA <N> — ….md`.
- Lee el estado actual: [docs/ROADMAP.md](../../../docs/ROADMAP.md) (¿está iniciada?), [docs/DATA_MODEL.md](../../../docs/DATA_MODEL.md) (entidades), [docs/DECISIONS.md](../../../docs/DECISIONS.md) (ADR pendientes de esa épica).
- Comprueba que las épicas previas de las que depende están **completas**. Si no, avisa y detente.

## 2. Inspeccionar el código existente

Antes de escribir nada, revisa con Glob/Grep/Read:
- Migraciones actuales (`database/migrations`).
- Modelos, Policies, Requests, Controllers existentes.
- Rutas (`routes/web.php`) y vistas (`resources/views`).
- Verifica que no vas a duplicar ni romper nada.

## 3. Planificar y comunicar

- Resume al usuario **qué vas a crear/modificar** y por qué.
- Si existe un **ADR PENDIENTE** relevante (p. ej. ADR-0001 para Épica 3), **confírmalo** antes de implementar. Si la decisión es significativa, regístrala como ACEPTADA en [docs/DECISIONS.md](../../../docs/DECISIONS.md).

## 4. Implementar (en orden)

1. **Migración(es)** — `DECIMAL(15,2)` para dinero, `household_id` + index + FK en tablas del hogar, `onDelete` explícito, `utf8mb4`.
2. **Modelo(s)** — `$fillable`/`#[Fillable]`, `casts()`, relaciones, scopes de filtro.
3. **Enum(s)** en `app/Enums` para tipos (AccountType, CategoryType, Frequency, etc.).
4. **Factory(ies)** con datos falsos realistas (montos COP, fechas recientes).
5. **Form Request(s)** por cada `store`/`update`, reglas estrictas, sin aceptar `household_id`.
6. **Policy(ies)** por recurso del hogar; autorización por membresía + rol.
7. **Controller(s)** finos → delegan a `app/Services` cuando hay lógica de dominio.
8. **Rutas** nombradas, agrupadas con middleware `auth`.
9. **Vistas** Blade mobile-first con Bootstrap 5; componentes en `resources/views/components`.
10. **Seeder** si la épica lo pide (datos de demo); integrar en `DatabaseSeeder`.

## 5. Verificar (regla de los 9 pasos)

Ejecuta y reporta resultados **reales**:
```bash
php artisan migrate:status
php artisan route:list
composer test            # o: php artisan test --filter=...
```
Si algo falla, arréglalo antes de declarar terminado. No omitas pasos.

## 6. Pruebas mínimas

Por recurso del hogar:
- CRUD feliz (crear/listar/editar/borrar).
- **Aislamiento**: usuario B obtiene **403** sobre recurso del usuario A.
- Para servicios de cálculo: tests unitarios con casos límite.

## 7. Actualizar docs

- [docs/ROADMAP.md](../../../docs/ROADMAP.md): marca la épica como 🟡/🟢.
- [docs/DATA_MODEL.md](../../../docs/DATA_MODEL.md): si añadiste/cambiaste entidades.
- [docs/DECISIONS.md](../../../docs/DECISIONS.md): si tomaste decisiones (ADR).

## 8. Cierre

Entrega al usuario:
1. Archivos modificados (con por qué).
2. Decisiones tomadas.
3. Resultado real de tests/migraciones/rutas.
4. Riesgos o deuda técnica.
5. Sugiere ejecutar `/security-checklist` para la verificación de seguridad.

## Reglas inquebrantables (recuerda)

- Aislamiento por `household` siempre (Policy + consultas acotadas; nunca `::find()` suelto).
- `DECIMAL` para dinero, nunca FLOAT.
- `$fillable` siempre; nunca `$guarded = []`.
- No commitear `.env`, secretos ni datos reales (no hagas commit salvo petición explícita).
- Implementa solo lo de la épica actual.
