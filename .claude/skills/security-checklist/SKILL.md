---
name: security-checklist
description: Verifica que los cambios recientes de Finami cumplen la política de seguridad (aislamiento multi-hogar, mass assignment, autorización, dinero, secretos). Úsala antes de considerar una épica terminada o de un merge a main. Lee docs/SECURITY.md y comprueba cada ítem contra el código real; reporta solo hallazgos verificados.
---

# Skill: security-checklist

Pasada de seguridad para confirmar que una épica (o un conjunto de cambios) cumple [docs/SECURITY.md](../../../docs/SECURITY.md). **Verifica con código real** (Grep/Read); no marques "OK" sin haberlo comprobado.

## Cómo usarla

Primero identifica **qué se modificó** (`git diff`/`git status` sobre los archivos de la épica) y centra la revisión ahí (sin ignorar el contexto que toca). Luego recorre cada bloque:

### 1. Aislamiento multi-hogar (IDOR) — crítico
- [ ] Ningún `::find(`/`::findOrFail(`/`::where('id',` en controladores sin acotar por `household`.
- [ ] Cada recurso del hogar se resuelve vía `$household->recurso()->...` o route-model-binding con scope.
- [ ] `household_id` **no** se acepta del cliente (se fuerza en servidor).
- [ ] Cada Policy verifica membresía del hogar (+ rol si aplica).
- [ ] Existe **test de aislamiento 403** por recurso nuevo.

### 2. Mass assignment
- [ ] Cada modelo nuevo tiene `$fillable`/`#[Fillable]` explícito.
- [ ] No hay `$guarded = []` en ningún modelo.
- [ ] Los Form Requests no permiten campos sensibles (`household_id`, `user_id` forzados).

### 3. Autorización
- [ ] Cada `store/update/destroy` llama a `authorize`/Policy.
- [ ] Rutas privadas con middleware `auth`.

### 4. Dinero
- [ ] Columnas monetarias nuevas son `decimal(15,2)` (grep en migraciones). Sin `float`/`double`.

### 5. Validación / Output / Inyección
- [ ] Form Request por cada escritura nueva; reglas estrictas.
- [ ] Sin `whereRaw`/`selectRaw`/`DB::raw` con concatenación de input (SQLi).
- [ ] Sin `{!! !!}` con input de usuario (XSS). Forms con `@csrf`.

### 6. Secretos y datos sensibles
- [ ] Grep de `password|secret|api_key|token|cvv|card_number|pin` en el código: sin valores hardcodeados.
- [ ] No se almacenan número completo de tarjeta / CVV / PIN (Épica 6).
- [ ] Factories/seeders sin datos reales de personas.
- [ ] No se loguean secretos/PII sensible.
- [ ] `git diff --cached` revisado: sin `.env`, sin credenciales, sin datos reales.

### 7. Producción / config
- [ ] Si se tocó config: `APP_DEBUG` no se fuerza a `true` para producción; nada de `env()` en código de app.

## Salida

Informe conciso. Por cada ítem: marca ✅ (verificado, con archivos) o ⚠️ (hallazgo con `archivo:línea` + explotación + fix). Al final, un veredicto:

- **APROBADO** — sin hallazgos críticos/altos.
- **BLOQUEADO** — hay hallazgos críticos/altos que deben corregirse antes de continuar.

Si encuentras algo crítico de aislamiento o secretos, **destácalo primero**. No inventes ni suavices hallazgos. Si quieres profundizar, deriva al agente `security-auditor`.
