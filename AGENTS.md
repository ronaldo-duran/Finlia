# AGENTS.md — Reglas de operación para agentes de IA

> Este archivo rige el comportamiento de **todo agente de IA** (Claude Code, Cursor, Copilot, subagentes propios) que trabaje en Finlia. Complementa a [CLAUDE.md](CLAUDE.md) y a [docs/SECURITY.md](docs/SECURITY.md).

Finlia maneja **dinero real de familias** y será un **repositorio público** que luego se monetiza. Un error de seguridad o un secreto filtrado puede tener consecuencias graves. Por eso, las reglas de abajo son **obligatorias y no negociables**.

---

## 1. Principios de conducta

1. **Lee antes de escribir.** Inspecciona el estado actual; no asumas.
2. **Respeta la arquitectura existente.** No reimplementes lo que ya funciona. Si crees que algo está mal, **señálalo** antes de cambiarlo.
3. **Código ejecutable, no pseudo-código.** Todo lo que escribas debe poder correr.
4. **No inventes** credenciales, URLs de APIs, servicios externos ni datos.
5. **No sobre-ingenieres.** Sin microservicios, sin abstracciones innecesarias, sin patrones "para lucirse".
6. **Mantén el alcance.** Implementa **solo** lo que pide la épica actual. Si la épica dice "todavía no", no lo hagas.
7. **Documenta decisiones** que afecten la arquitectura en [docs/DECISIONS.md](docs/DECISIONS.md) y **detente** a explicarlas si son significativas.
8. **No narres el código con comentarios.** Ver §1.1.

### 1.1 Comentarios y emojis — prohibición explícita

Este es el error más frecuente de un agente en este repositorio: explicar en un comentario lo que el código ya dice. El repositorio es **público** y se monetiza; un archivo salpicado de `// Ahora recorremos las cuentas` lo hace parecer un ejercicio, no un producto. La regla completa y sus razones están en [docs/CONVENTIONS.md §1.1](docs/CONVENTIONS.md); lo operativo:

**El único comentario admitido es PHPDoc** (`/** ... */`, y su equivalente JSDoc en JS), de una o dos líneas más las anotaciones (`@param`, `@return`, `@var`, `@throws`).

**No escribas nunca:**

| Prohibido | En su lugar |
|---|---|
| `// Calcula el total` sobre una línea | Un nombre de método o variable que lo diga |
| `$x = 7; // días de gracia` | `public const EARLY_PAYMENT_DAYS = 7;` |
| `// ---- Rutas públicas ----` | Agrupar con `Route::group()`, que ya lo expresa |
| `/*|-----|*/` (banner de Laravel en `routes/`) | Nada; bórralo si lo genera el framework |
| `{{-- Encabezado --}}` en Blade | Partir la vista en componentes |
| `// TODO: revisar esto` | Una entrada en [docs/ROADMAP.md](docs/ROADMAP.md) o un issue |
| Código antiguo comentado | Borrarlo; está en git |
| `// ⚠️ Ojo: ...`, `✓`, `👋` en UI o en consola | Texto plano; para iconos, Bootstrap Icons |

**Dónde va el "por qué"** cuando de verdad hace falta: en el **PHPDoc de la clase o del método** (una o dos líneas, con referencia al ADR: `(ADR-0040)`), en un **ADR** de [docs/DECISIONS.md](docs/DECISIONS.md), o en un **test con nombre descriptivo** — que además impide que ese "por qué" se rompa sin que nadie se entere. Nunca en medio de un método.

**Emojis**: cero en código y en la copia de la interfaz (PHP, Blade, JS, CSS, salida de comandos `artisan`, mensajes de commit y descripciones de PR). Los emojis de `docs/*.md` son navegación de la documentación y se conservan.

Antes de dar por terminado un cambio, comprueba que no introdujiste ninguno:

```bash
git diff -U0 | grep -nE '^\+\s*(//|#[^!\[])|^\+.*\S\s+// |^\+.*\{\{--' && echo "Hay comentarios prohibidos en el diff"
```

---

## 2. Seguridad — barreras obligatorias

Estas son **fallas que un agente nunca debe introducir**. Cada cambio de código debe revisarse contra esta lista.

### 2.1 Aislamiento multi-hogar (amenaza #1)
> Un usuario **nunca** debe acceder a datos de otro `household`, ni por manipulación de URL o ID.

- Toda consulta de datos financieros debe acotarse al `household` del usuario.
- **Nunca** uses `Model::find($id)` suelto en un controlador sin autorizar la pertenencia al hogar.
- Patrón obligatorio: **Policy + Form Request**. Idealmente **route-model-binding con `scope`** (p.ej. `$household->expenses()->findOrFail($id)`).
- Considera un **global scope** `HouseholdScope` para modelos sensibles.
- **Pruébalo**: cada recurso debe tener un test que verifique que el usuario B **no** puede leer/editar/borrar un recurso del usuario A (403).

### 2.2 Mass assignment
- Define `$fillable` (o atributo `#[Fillable([...])]`) en **cada** modelo.
- **Prohibido** `$guarded = []`.
- Los Form Requests definen los campos válidos; el modelo define los asignables. Ambos deben coincidir con lo mínimo necesario.

### 2.3 Validación y autorización
- Un **Form Request** por cada operación de escritura (store/update).
- Una **Policy** por cada recurso que pertenezca a un hogar. Autoriza **antes** de mutar.
- Reglas estrictas: tipos, rangos, `exists`, `in`, tamaño. Filtra listas permitidas con `Rule::in(...)`.

### 2.4 Datos sensibles
- **Dinero**: `DECIMAL(15,2)`. **Nunca FLOAT/DOUBLE**.
- **Tarjetas** (Épica 6): **nunca** almacenar número completo de tarjeta, CVV, PIN ni fechas de vencimiento completas. Solo últimos 4 dígitos si se quiere.
- **Passwords/tokens**: hash (bcrypt/argon2 para passwords; hash+random de 64+ chars para tokens de invitación).
- **Logs**: nunca loguear contraseñas, tokens, números de tarjeta ni montos con datos personales innecesarios.

### 2.5 Output e inyección
- **XSS**: usa `{{ }}` (auto-escape). `{!! !!}` solo para HTML generado por el sistema, jamás por entrada de usuario.
- **SQLi**: Query Builder / Eloquent con bindings. Nunca concatenes input en `whereRaw`/`selectRaw` sin `?`.
- **CSRF**: `@csrf` en cada form; métodos `POST/PUT/DELETE` vía form o con cabecera `X-CSRF-TOKEN`.

### 2.6 Limites y sesiones
- Rate limiting en `/login`, `/register` y endpoints sensibles (`throttle:5,1` o similar).
- Sesiones: driver `database` (compatible con Hostinger), `SESSION_ENCRYPT` según corresponda, expiración razonable.
- En producción: `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY` generada.

### 2.7 Frontend ≠ Backend
- La autorización **siempre** se valida en backend. Lo que el frontend oculta es **cosmético**.
- Nunca exponer IDs internos sensibles si no hace falta (considera UUID/slugs en recursos que se compartan por URL, p.ej. invitaciones).

### 2.8 Frontera `ee/` (licencia distinta)
El directorio `ee/` contiene las funciones Premium y **no está bajo la AGPL** del resto del repositorio, sino bajo licencia comercial ([ADR-0042](docs/DECISIONS.md#adr-0042), [ee/README.md](ee/README.md)).

- **La dependencia va en un solo sentido: `ee/` → núcleo, nunca al revés.** Ningún archivo de `app/`, `routes/` o `resources/` importa una clase `Finlia\Ee\*`, salvo el único punto de registro condicional del ServiceProvider.
- **Borrar `ee/` debe dejar la app funcionando**: arranca, pasa la suite y sirve todo lo gratuito. Si al implementar algo el núcleo lo necesita, ese código **va al núcleo bajo AGPL**, no se crea una dependencia hacia dentro de `ee/`.
- Que el código de pago sea visible hace la regla 2.7 **más** importante: la comprobación de suscripción se hace en backend contra la base de datos, nunca con un flag del cliente.
- Nada de secretos aquí tampoco: las claves de API van por `.env` (§3).

---

## 3. Secretos y repo público

**Nunca** commitear (ni stagging, ni en comentarios, ni en ejemplos):

- `.env`, `.env.*` con valores reales.
- Contraseñas, API keys, tokens, certificados.
- Datos financieros reales de Ronaldo, Vanessa o cualquier persona.
- Screenshots o dumps con datos reales.
- `auth.json`, `storage/*.key`.

Antes de cada commit, **verifica** con la lista de "cosas que nunca se commitean" de [CLAUDE.md §9](CLAUDE.md). El `.gitignore` ya cubre lo principal; no confíes solo en eso — revisa el diff (`git diff --cached`).

Los datos de demostración van **siempre** por Factories/Seeders con Faker. Nunca un seeder con datos reales.

---

## 4. Definición de "Hecho" (DoD) por épica

Una épica **no está terminada** hasta que se cumple **todo** lo siguiente:

- [ ] Migraciones creadas y reproducibles (`migrate:fresh --seed` funciona limpio).
- [ ] Modelos con `$fillable`/casts/relaciones correctas.
- [ ] Form Requests con validación completa.
- [ ] Policies/Gates y autorización en cada acción.
- [ ] Controladores finos; lógica de dominio en servicios.
- [ ] Vistas responsive **mobile-first** con el sistema de diseño de [docs/UI_DESIGN.md](docs/UI_DESIGN.md) sobre Bootstrap 5.
- [ ] Rutas nombradas y protegidas (middleware `auth`).
- [ ] Factories + Seeders con datos falsos.
- [ ] **Pruebas**: feature tests del recurso **+ test de aislamiento entre hogares (403)**.
- [ ] `composer test` en verde.
- [ ] No se rompió funcionalidad anterior.
- [ ] **Sin comentarios narrativos ni emojis** en el diff (§1.1): solo PHPDoc. Verificado con el `grep` de esa sección.
- [ ] **Guía de la pantalla al día** ([ADR-0045](docs/DECISIONS.md#adr-0045)): si la entrega añade o cambia algo que no se adivina mirando la pantalla, se refleja en `config/tours.php`. Guía nueva → se escribe; guía existente → se **sube su `version`** y los pasos añadidos llevan ese `since`, para que quien ya la vio reciba solo lo nuevo. Una guía que describe una pantalla que ya cambió miente, y eso es peor que no tenerla.
- [ ] Docs actualizadas (DATA_MODEL, ROADMAP, DECISIONS si aplica).
- [ ] Commits pequeños y descriptivos; sin secretos.
- [ ] Checklist de seguridad ([docs/SECURITY.md](docs/SECURITY.md)) revisado.

Antes de declarar "hecho", ejecuta `/security-checklist`.

---

## 5. Subagentes disponibles en este repo

Definidos en `.claude/agents/`:

| Agente | Cuándo usarlo |
|---|---|
| `epic-implementer` | Para implementar una épica completa siguiendo el protocolo. |
| `security-auditor` | Revisión adversarial de seguridad (aislamiento, mass assignment, secrets, money). **Read-only**. |
| `laravel-reviewer` | Revisión de calidad Laravel (N+1, validaciones, policies, convenciones). **Read-only**. |

Para tareas puntuales también puedes usar agentes genéricos (`Explore`, `Plan`, `general-purpose`), pero la **autorización** de cambios siempre la hace un agente con permisos de escritura y la **verificación** la hacen los agentes read-only.

---

## 6. Cómo reportar trabajo

Al terminar una unidad de trabajo, entrega siempre:

1. **Qué modificaste** (archivos y por qué).
2. **Decisiones** tomadas (y si afectan arquitectura, detente antes).
3. **Pruebas ejecutadas** y su resultado real (no "debería pasar").
4. **Riesgos o deuda técnica** pendiente.
5. **Siguiente paso** sugerido.

Si algo falló, dilo con la salida real. Si un paso se omitió, dilo. No adornes ni ocultes fallos.
