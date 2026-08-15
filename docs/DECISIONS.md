# Decisiones de Arquitectura (ADR) — Finlia

> Registro de decisiones significativas. Cada ADR: contexto → decisión → consecuencias → estado. Las marcadas **PENDIENTE** requieren confirmación antes de implementar la épica correspondiente.

Formato inspirado en ADR (Architecture Decision Records). Índice:

- [ADR-0001 — Ingresos y gastos en tablas separadas (`incomes` + `expenses`)](#adr-0001) — **ACEPTADA**
- [ADR-0002 — Modelado de tarjetas de crédito](#adr-0002) — **ACEPTADA**
- [ADR-0003 — IDs autoincrement vs UUID/ULID en recursos compartidos por URL](#adr-0003) — **ACEPTADA**
- [ADR-0004 — Tailwind reemplazado por Bootstrap 5](#adr-0004) — **ACEPTADA**
- [ADR-0005 — Multi-tenancy por fila (`household_id`)](#adr-0005) — **ACEPTADA**
- [ADR-0006 — Dinero con `DECIMAL(15,2)`](#adr-0006) — **ACEPTADA**
- [ADR-0007 — Tests con PHPUnit (Pest opcional)](#adr-0007) — **ACEPTADA**
- [ADR-0008 — Sesiones/colas/caché en `database` para Hostinger](#adr-0008) — **ACEPTADA**
- [ADR-0009 — Autenticación a medida con Bootstrap 5 (sin Breeze)](#adr-0009) — **ACEPTADA**
- [ADR-0010 — Web-first con seam de Services (API móvil diferida)](#adr-0010) — **ACEPTADA**
- [ADR-0011 — Hogar personal auto-creado al registrar + hogar activo en sesión](#adr-0011) — **ACEPTADA**
- [ADR-0012 — Saldo de cuenta persistido + recomputado en cada escritura](#adr-0012) — **ACEPTADA**

---

## ADR-0001
### Ingresos y gastos en tablas separadas (`incomes` + `expenses`) — **ACEPTADA**

**Contexto.** La Épica 3 modela ingresos y gastos como dos entidades distintas. El equipo prefiere mantener esa separación explícita en la base de datos en lugar de una tabla única polimórfica.

**Decisión.** Dos tablas independientes: `incomes` y `expenses`. Cada una con su modelo (`Income`, `Expense`), migración, factory, Form Request y Policy. Ambas referencian `household_id`, `user_id`, `account_id` y `category_id`.

**Alternativas (descartadas).**
- (a) Una sola tabla `transactions` con columna `type` — una sola migración/factory/policy y queries más simples para agregaciones; **descartada** por preferencia de separación explícita.
- (b) Double-entry (libro mayor con asientos) — **sobre-ingeniería** para el alcance actual.

**Consecuencias y mitigaciones.**
- Las agregaciones del dashboard/presupuesto que combinan ingresos y gastos requerirán **dos consultas** (o un `UNION`) en vez de una. Mitigación: un `MovementSummaryService` que centralice esas consultas para no duplicar lógica entre controladores.
- Las **transferencias entre cuentas** (Épica 10) no son ni ingreso ni gasto. Se resolverán probablemente con una tabla `transfers` dedicada (decisión diferida a Épica 10). No meter `transfer` dentro de `incomes`/`expenses`.
- Los filtros "por tipo de movimiento" se implementan a nivel de UI/controlador combinando ambas fuentes.

**Estado.** ACEPTADA — confirmado por el equipo. Aplica a partir de Épica 3.

---

## ADR-0002
### Modelado de tarjetas de crédito — **ACEPTADA**

**Contexto.** Las tarjetas de crédito son a la vez una "cuenta" (saldo, movimientos) y tienen atributos extra (límite, fecha de cierre, fecha de pago, cuota de manejo).

**Decisión.** `accounts` con `type = credit_card` + tabla complementaria `credit_cards(account_id, credit_limit, available_credit, statement_date, payment_due_date, annual_fee, monthly_fee)`.

**Alternativas (descartadas).**
- (a) Tabla `credit_cards` totalmente independiente — duplica la noción de saldo y rompe la unificación de movimientos por cuenta.
- (b) Todas las columnas extra en `accounts` (nullables) — ensucia `accounts` con campos que solo aplican a tarjetas.

**Consecuencias.** La opción elegida reutiliza el motor de cuentas/movimientos y aísla lo específico de tarjetas. Requiere un `leftJoin`/relación opcional al mostrar cuentas. `accounts` con `type=credit_card` puede referenciarse como cuenta en `incomes`/`expenses`.

**Estado.** ACEPTADA — confirmado por el equipo. Aplica en Épica 6.

---

## ADR-0003
### IDs autoincrement vs UUID/ULID en recursos compartidos por URL — **ACEPTADA**

**Contexto.** Las invitaciones a un hogar se comparten por enlace con un token. Usar IDs autoincrement en URLs expone cardinalidad y facilita enumeración.

**Decisión.** IDs autoincrement para todo **excepto** tokens de invitación, que usan un token aleatorio de 64 chars **hasheado (sha256)** en DB; el token plano viaja solo en el enlace que ve el owner. No adoptar UUID globalmente mientras no sea necesario (simplicidad).

**Alternativas (descartadas).** UUID/ULID globales en todas las tablas — evita enumeración en todos los recursos, pero añade complejidad e índices más grandes, y se desvía del principio de simplicidad.

**Consecuencias.** Mantenemos simplicidad; el riesgo de enumeración se mitiga con policies obligatorias (todo recurso requiere autorización, así conocer un ID no sirve sin pertenecer al hogar) y porque los tokens de invitación son opacos y hasheados. El hash evita que una fuga de la tabla permita aceptar invitaciones. Si en el futuro se exponen más recursos públicamente, reconsiderar.

**Estado.** ACEPTADA — confirmado al iniciar Épica 2 (2026-08-12). Implementado en `household_invitations.token`.

---

## ADR-0004
### Tailwind reemplazado por Bootstrap 5 — **ACEPTADA**

**Contexto.** El proyecto Laravel trae Tailwind 4 + Vite por defecto. La especificación obliga **Bootstrap 5** y prohíbe introducir frameworks JS pesados; el equipo domina Bootstrap.

**Decisión.** Quitar Tailwind y `@tailwindcss/vite` en la **Épica 1**, añadir Bootstrap 5 (vía Vite/npm o, si la compilación en Hostinger complica, por CDN/local en `public/`). Mantener Vite para assets.

**Consecuencias.** Coherencia con la spec y con el hosting. Hay que hacer el cambio antes de cualquier vista. Documentado para evitar confusión en el arranque.

---

## ADR-0005
### Multi-tenancy por fila (`household_id`) — **ACEPTADA**

**Contexto.** Múltiples hogares comparten la misma base de datos; cada uno aísla sus datos.

**Decisión.** Columna `household_id` en toda tabla financiera + aislamiento en aplicación (policies, scopes, consultas acotadas). **No** separar por base de datos ni por esquema.

**Consecuencias.** Simple y suficiente para el alcance. El coste: disciplina constante de filtrado (ver [SECURITY.md](SECURITY.md#1-aislamiento-multi-hogar-idor--amenaza-1)). Mitigado con global scope y tests de aislamiento.

---

## ADR-0006
### Dinero con `DECIMAL(15,2)` — **ACEPTADA**

**Contexto.** Valores monetarios en COP pueden ser grandes (millones). `FLOAT` introduce errores de redondeo.

**Decisión.** `DECIMAL(15,2)` para todo campo monetario; cast `decimal:2` en modelos; formatter central para visualización.

**Consecuencias.** Precisión exacta, dos decimales. 15 enteros es amplio para COP.

---

## ADR-0007
### Tests con PHPUnit (Pest opcional) — **ACEPTADA**

**Contexto.** PHPUnit 12.5 ya está instalado; Pest no, pero el plugin está permitido en `composer.json`. La spec dice "PHPUnit/Pest".

**Decisión.** **PHPUnit** por defecto (clases `extends TestCase`), para mínimas dependencias y compatibilidad. Pest queda como opcional si el equipo lo prefiere más adelante.

**Consecuencias.** Sin aprendizaje de sintaxis extra; alineado con lo ya instalado.

---

## ADR-0008
### Sesiones, colas y caché en `database` para Hostinger — **ACEPTADA**

**Contexto.** Hosting compartido: sin Redis, sin Memcached confiable, sin workers persistentes.

**Decisión.** `SESSION_DRIVER=database`, `CACHE_STORE=database` (o `file`), `QUEUE_CONNECTION=database`. Procesar colas vía cron (`queue:work --stop-when-empty`) cuando sea necesario.

**Consecuencias.** Cero servicios externos; todo vive en MySQL. Aceptable para el tráfico esperado.

---

## ADR-0009
### Autenticación a medida con Bootstrap 5 (sin Breeze) — **ACEPTADA**

**Contexto.** La Épica 1 necesita registro, login, logout y recuperación de contraseña con sesiones nativas de Laravel. ADR-0004 obliga a usar **Bootstrap 5** y prohibió introducir Tailwind.

**Decisión.** Implementar la auth **a medida** (controladores finos + Form Requests + vistas Blade/Bootstrap 5) en lugar de instalar Laravel Breeze o Jetstream. Sesiones nativas de Laravel (driver `database`), rate limiting propio en login/registro (`throttle` + `RateLimiter`), y recuperación de contraseña vía el broker nativo de Laravel. Sin verificación de email en esta épica (la columna `email_verified_at` se conserva para el futuro).

**Alternativas (descartadas).**
- (a) **Laravel Breeze (Blade)** — scaffolding rápido, pero sus vistas usan **Tailwind**, lo que contradice ADR-0004 y obligaría a reescribirlas todas. Descartado.
- (b) **Jetstream (Livewire/Inertia)** — introduce Livewire o Inertia+Vue, prohibido por la spec. Descartado.

**Consecuencias.**
- Cero dependencias extra; control total del marcado y los mensajes (en español).
- Mayor superficie de código auth a mantener, pero simple y alineada con las convenciones del proyecto (un Form Request por operación de escritura).
- El rate limiting se aplica tanto en la ruta (`throttle:5,1`) como dentro del `StoreSessionRequest` (`RateLimiter`, 5 intentos) para defensa en profundidad.
- La recuperación de contraseña siempre devuelve el mismo mensaje de éxito para no revelar si un correo existe (mitiga enumeración de usuarios).

**Estado.** ACEPTADA — aplica desde Épica 1.

---

## ADR-0010
### Web-first con seam de Services (API móvil diferida) — **ACEPTADA**

**Contexto.** El producto tendrá una app nativa (Android/iOS) en el futuro. Cabían dos caminos: (a) **API-first desde ahora** (REST/JSON como interfaz primaria, web como SPA) o (b) **web-first** con Blade+sesiones y API diferida. El equipo imaginaba "REST API" como base de todo; en Laravel la capa reutilizable es el **Service**, no el controller: dos capas de presentación (Blade y API) pueden compartir los mismos Services.

**Decisión.** Construir **web-first** con Blade + Bootstrap + sesiones (como ya mandan este manual y AGENTS.md), pero aislar **toda** la lógica de negocio en `app/Services/` con Services **libres de acoplamiento HTTP** (no leen `request()`/`session()`/`Auth::id()`; reciben datos explícitos). Los controllers quedan **delgados** (validar → autorizar → llamar Service → responder). La **API REST/JSON + Sanctum se añade en la Épica 14** (futura), con controllers API que **reutilizan los mismos Services**, Form Requests y Policies.

**Alternativas (descartadas).**
- (a) **API-first desde ahora** (REST primario, web como SPA/Inertia, Sanctum) — descartada: duplica el trabajo por épica (API + UI) ahora, contradice el principio de simplicidad y el stack definido (Blade), y la app móvil está lejana.
- (b) **Híbrido (doble endpoint por épica)** — descartada: dos controllers por recurso desde el inicio, más mantenimiento sin beneficio inmediato.

**Consecuencias y mitigaciones.**
- La app móvil será **barata** de construir **si y solo si** se respeta el seam: si la lógica se cuela en controllers o vistas, la API requerirá reescritura. Mitigación: regla obligatoria desde Épica 2 + revisión en `/security-checklist` y con el agente `laravel-reviewer`.
- Cero dependencias extra ahora (sin Sanctum hasta la Épica 14).
- Las decisiones de diseño que cuestan poco y facilitan la API futura (Services sin HTTP, helpers de formato compartidos, no acoplar a la sesión) se adoptan **desde hoy**.

**Estado.** ACEPTADA — confirmado por el equipo. Aplica desde Épica 2.

---

## ADR-0011
### Hogar personal auto-creado al registrar + hogar activo en sesión — **ACEPTADA**

**Contexto.** Todas las épicas siguientes (cuentas, ingresos, presupuestos…) operan **dentro** de un hogar. Un usuario recién registrado sin hogar dejaría la app inutilizable (dashboard, selector y futuras pantallas no tendrían contexto). Había que decidir (a) si el hogar inicial se crea automáticamente o manual, y (b) dónde se guarda el "hogar activo" cuando un usuario pertenece a varios.

**Decisión.**
1. **Auto-creación**: al registrarse, se crea un hogar personal `"Mi hogar"` con el usuario como `owner` (vía `HouseholdService::createHousehold`), que queda como hogar activo. El usuario puede renombrarlo o invitar miembros después.
2. **Hogar activo en sesión**: el `household_id` activo se guarda en `session('household_id')`. La resolución es HTTP-layer (helper `active_household()` en `app/Support/helpers.php`), **no** un Service de dominio (respeta ADR-0010: la noción de "activo" es estado de sesión, no lógica de negocio). Si la sesión no tiene uno, se resuelve el primer hogar del usuario y se persiste.

**Alternativas (descartadas).**
- Forzar la creación manual del primer hogar (redirección a un wizard) — más explícito, pero deja la app sin hogar activo hasta que el usuario actúe, y obliga a lidiar con el caso "sin hogar" en todas las épicas.
- Guardar el hogar activo como columna en `users` (`active_household_id`) — acopla la preferencia al usuario (no a la sesión/dispositivo) y rompe la multi-sesión.

**Consecuencias.**
- Cualquier código puede asumir que un usuario autenticado **siempre** tiene al menos un hogar (salvo state de prueba deliberado). Las vistas usan `active_household()` defensivamente (`?->` / fallback a "Crear hogar").
- Un usuario puede pertenecer a **varios** hogares y cambiar el activo en cualquier momento (selector en navbar).
- El seam de Services (ADR-0010) se mantiene: `HouseholdService` no toca `session()`/`Auth::id()`; el controlador setea la sesión tras crear/aceptar.

**Estado.** ACEPTADA — aplica desde Épica 2 (2026-08-12).

---

## ADR-0012
### Saldo de cuenta persistido + recomputado en cada escritura — **ACEPTADA**

**Contexto.** La Épica 3 introduce `accounts` con `initial_balance` y `current_balance`. Cabía decidir si `current_balance` se (a) **calcula al vuelo** (Σingresos − Σgastos cada vez que se lee), (b) se **persiste y actualiza incrementalmente** (+monto/−monto por cada movimiento) o (c) se **persiste y recomputa desde la fuente** en cada escritura. La DATA_MODEL lo tenía marcado ⚖️ pendiente.

**Decisión.** Opción **(c)**: `current_balance` se persiste en `accounts` y un `AccountBalanceService::recompute(Account)` lo recalcula desde la fuente de verdad (`initial_balance + Σincomes − Σexpenses`, excluyendo soft-deleted) **dentro de la misma transacción** de cada alta/edición/borrado de movimiento (`MovementService`). En la edición que cambia la cuenta, se recalculan **ambas** cuentas (vieja y nueva). La cuenta recién creada parte de `current_balance = initial_balance`.

**Alternativas (descartadas).**
- (a) **Calcular al vuelo** — sin drift posible, pero cada lectura del saldo implica agregaciones; encarece el dashboard y los listados, y complica un futuro caché de saldos.
- (b) **Incremental (+/− por movimiento)** — barato de leer y escribir, pero **acumula drift** ante bugs, ediciones fallidas a mitad de camino o datos modificados a mano. La reconciliación sería un dolor recurrente.

**Consecuencias y mitigaciones.**
- **Lecturas O(1)** (el saldo vive en la fila). El dashboard y la lista de cuentas leen directo.
- **Sin drift**: como se *recomputan* desde los movimientos (no se ajustan incrementalmente), el saldo siempre coincide con la fuente de verdad, incluso tras borrados lógicos o ediciones.
- Coste: en cada escritura de movimiento se hacen dos `SUM` (incomes/expenses de la cuenta afectada) + un `save`. Aceptable para el volumen del producto; se puede optimizar (delta incremental dentro de la tx) en Épica 11 si el perfilado lo pide, sin cambiar el contrato público de `AccountBalanceService`.
- Un borrado de cuenta se **bloquea** si tiene movimientos (`AccountController::destroy`), para que los saldos sigan cuadrando y no se pierda el histórico.

**Estado.** ACEPTADA — confirmado por el equipo al iniciar Épica 3 (2026-08-13). Implementado en `AccountBalanceService` + `MovementService`.

---

## ADR-0013
### CI en GitHub Actions con Pint + PHPUnit + build de Vite + E2E con Playwright — **ACEPTADA**

**Contexto.** Tras las épicas 1–3 el repo tenía ~90 tests PHPUnit que solo corrían en local, sin linter en runner ni pruebas de navegador. El repo será público y monetizado: la calidad debe verificarse en cada PR.

**Decisión.** Añadir `.github/workflows/ci.yml` con tres jobs: **PHP** (valida composer, `pint --test`, `composer audit` informativo, `route:list` como smoke y `php artisan test` sobre SQLite `:memory:`), **Assets** (`npm ci` + `npm run build`) y **E2E** (Playwright/Chromium). Para que los tests corran sin `.env` (como ocurre en el runner), `phpunit.xml` define una `APP_KEY` exclusiva de pruebas.

Los E2E usan `playwright.config.ts`: el `webServer` levanta `php artisan serve` en el puerto 8890 con configuración inyectada como **variables de entorno reales** (SQLite en `database/playwright.sqlite`, sesiones/cache en BD, `migrate:fresh --seed` con datos falsos del `DatabaseSeeder`), sin depender de ningún archivo `.env`. Un proyecto `setup` inicia sesión una sola vez y la comparte vía `storageState` para no agotar el `throttle:5,1` de `/login`.

**Alternativas (descartadas).**
- **PHPStan/Larastan** — útil, pero añade una dependencia y una configuración nueva fuera de lo definido por el repo (CLAUDE.md §8 establece Pint como herramienta de calidad). Se puede añadir en la Épica 11 (hardening) si se decide.
- **Archivo `.env.playwright`** para el entorno e2e — bloqueado por la regla de permisos sobre `.env*` y más frágil que inyectar variables.
- **Cypress** — Playwright es más directo para servir la app desde config y multiproveedor.

**Consecuencias.**
- Cada PR valida estilo, tests, assets y flujos reales de navegador (auth, registro, hogares, gastos, dashboard).
- El job E2E es secuencial (`workers: 1`) porque `php artisan serve` atiende con un solo worker en Windows; en Linux bastaría para paralelizar si se necesita.
- La `APP_KEY` de `phpunit.xml` y la de los E2E son de prueba (no secretos de producción) y se pueden rotar libremente.

**Estado.** ACEPTADA — 2026-08-14. Implementado en `.github/workflows/ci.yml`, `playwright.config.ts` y `tests/e2e/`.

---

## Cómo añadir un ADR

1. Numera correlativo (`ADR-00NN`).
2. Marca estado: **Propuesta / PENDIENTE / ACEPTADA / Rechazada / Sustituida por ADR-00NN**.
3. Incluye: contexto, decisión, alternativas, consecuencias.
4. Si sustituye a otro, enlázalo.
