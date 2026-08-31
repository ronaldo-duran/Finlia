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
- [ADR-0013 — CI en GitHub Actions con Pint + PHPUnit + build de Vite + E2E con Playwright](#adr-0013) — **ACEPTADA**
- [ADR-0014 — Ingresos esperados configurables y dinero disponible con seams por épica](#adr-0014) — **ACEPTADA**
- [ADR-0015 — Correo transaccional mínimo: solo invitaciones y recuperación de contraseña](#adr-0015) — **ACEPTADA**
- [ADR-0016 — Rediseño mobile-first adelantado (Épica 10 parcial) + sistema de diseño propio](#adr-0016) — **ACEPTADA**
- [ADR-0017 — Identidad de marca Finlia (símbolo de puntos, petróleo/cobre)](#adr-0017) — **ACEPTADA**
- [ADR-0018 — Recurrentes: seams fijo/obligación por frecuencia, ocurrencias reales y "marcar pagado"](#adr-0018) — **ACEPTADA**
- [ADR-0019 — Los recursos financieros solo se operan desde su hogar activo](#adr-0019) — **ACEPTADA**
- [ADR-0020 — Saldo de deuda derivado de una línea base más los pagos](#adr-0020) — **ACEPTADA**
- [ADR-0021 — Un pago de deuda genera el movimiento real de la cuenta](#adr-0021) — **ACEPTADA**
- [ADR-0022 — La deuda se pacta en cuotas, y el pago mínimo no es el plan de pago](#adr-0022) — **ACEPTADA**
- [ADR-0023 — Registrar una deuda es un simulador de crédito, no un formulario en blanco](#adr-0023) — **ACEPTADA**
- [ADR-0024 — Avisos que el usuario da por leídos, por clave y en servidor](#adr-0024) — **ACEPTADA**
- [ADR-0025 — Metas de ahorro: ahorrado derivado, aportes que no mueven cuentas y aporte mensual programado](#adr-0025) — **ACEPTADA**
- [ADR-0026 — Reportes: panel ligero + pantalla dedicada, comparaciones honestas y CSV como único formato](#adr-0026) — **ACEPTADA**
- [ADR-0027 — Recordatorios derivados de su fuente + tabla solo para avisos sueltos](#adr-0027) — **ACEPTADA**
- [ADR-0028 — Digest de recordatorios por correo: opt-in, síncrono por cron y SMTP gratis (Brevo)](#adr-0028) — **ACEPTADA**
- [ADR-0029 — Verificación de correo obligatoria en el registro con reclaim anti-squatting](#adr-0029) — **ACEPTADA**
- [ADR-0030 — Perfil propio: cambio de contraseña con revocación de sesiones y cambio de correo con doble confirmación](#adr-0030) — **ACEPTADA**
- [ADR-0031 — Términos y condiciones versionados con re-aceptación obligatoria](#adr-0031) — **ACEPTADA**

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

## ADR-0014
### Ingresos esperados configurables y dinero disponible con seams por épica — **ACEPTADA**

**Contexto.** La Épica 4 define el cálculo central del producto:

```
ingresos esperados − gastos fijos − recurrentes próximos − obligaciones de deuda
                  − ahorro programado − presupuesto comprometido = dinero disponible
```

Al implementarla surgieron tres huecos:

1. **No existe la noción de "ingreso esperado".** La Épica 3 solo registra ingresos *ya ocurridos* (`incomes`). Sin una fuente de expectativa, la vista "próximo mes" arrancaría siempre en cero y el número principal del producto sería inútil.
2. **Cuatro términos de la fórmula pertenecen a épicas futuras**: gastos fijos y recurrentes (Épica 5), deuda (Épica 6) y ahorro programado (Épica 7). No se pueden implementar ahora sin violar la regla de alcance.
3. **Doble conteo del presupuesto**: si un hogar define presupuesto total *y* por categoría, sumar ambos infla el comprometido.

**Decisión.**

1. **Nueva tabla `expected_incomes`** (no prevista en DATA_MODEL): el usuario configura sus ingresos mensuales fijos (salario, arriendos, inversiones) con monto, día previsto de cobro y un flag `is_active`. Es la entrada del término "ingresos esperados".
2. **Ingreso esperado del período = `max(Σ expected_incomes × factor, ingresos registrados del período)`.** Se toma el mayor, no la suma, para no contar dos veces el mismo salario cuando ya se registró como `income`, y para no quedarse corto si entró más de lo previsto. Si el hogar no ha configurado nada, se degrada a los ingresos registrados (el número sigue siendo útil desde el primer día).
3. **Los términos de épicas 5-7 se declaran explícitamente en cero** dentro de `committed` (`fixed_expenses`, `recurring`, `debt`, `savings`), no se omiten. Cada épica futura solo tiene que rellenar su clave: ni la fórmula, ni la firma del Service, ni la UI cambian. La pantalla los muestra atenuados con la épica que los traerá, así el usuario entiende que el número aún no lo contempla.
4. **Presupuesto comprometido = `max(total pendiente, Σ pendientes por categoría)`** — el mayor de los dos, evitando el doble conteo cuando existen ambos y sin quedarse corto si las categorías superan al total.
5. **Cuatro conceptos que no se mezclan** (lo exige la épica): `current_balance` (saldo real hoy), `committed` (reservado del período), `available` (ingresos esperados − gastado − comprometido = "puedes gastar") y `free` (saldo real − comprometido).
6. **Los presupuestos se guardan siempre como mensuales** (`BudgetPeriod::Monthly`). La consulta "esta semana" **prorratea** el mensual por `días del rango / días del mes`; no existen presupuestos semanales. `BudgetScope` (semana/mes/próximo mes) es la ventana consultada, distinta de `BudgetPeriod`.

**Alternativas (descartadas).**
- **Estimar los ingresos esperados como promedio de los últimos 3 meses** — cero configuración, pero es una caja negra: el usuario no puede corregirla y un mes atípico distorsiona la previsión. Descartada a favor del control explícito.
- **Sumar ingresos esperados + registrados** — duplica el salario en cuanto se registra el movimiento.
- **Omitir de la fórmula los términos de épicas 5-7** — obligaría a reescribir el Service, sus tests y la UI en cada épica siguiente.
- **Sumar presupuesto total y de categorías** — infla el comprometido y hunde artificialmente el "puedes gastar".
- **Presupuestos semanales propios** (`BudgetPeriod::Weekly`) — triplica el mantenimiento (el usuario tendría que definir dos presupuestos coherentes entre sí) sin ganancia real.

**Consecuencias y mitigaciones.**
- El número principal es **conservador por diseño**: si entra un ingreso extra no previsto y ya hay un esperado mayor, no se refleja hasta superarlo. En una app de finanzas, subestimar lo disponible es el sesgo seguro.
- `expected_incomes` **no** es la tabla de recurrentes de la Épica 5 (`recurring_expenses`): aquella modela *gastos* con frecuencias variadas; esta modela *ingresos* mensuales. No se fusionan.
- La periodicidad de `expected_incomes` es **mensual fija**. Si la Épica 5 generaliza `Frequency`, se puede añadir aquí sin romper el cálculo (el Service solo consume el importe mensual).
- Al no haber aún gastos recurrentes ni deuda, el "puedes gastar" de hoy es **optimista** respecto al que dará la Épica 6. La UI lo hace explícito en "¿Cómo se calcula?".

**Estado.** ACEPTADA — confirmado por el equipo al iniciar Épica 4 (2026-08-18). Implementado en `BudgetCalculatorService`, `expected_incomes`, `budgets`, `BudgetScope`, `BudgetPeriod` y `BudgetAlertLevel`.

---

## ADR-0015
### Correo transaccional mínimo: solo invitaciones y recuperación de contraseña — **ACEPTADA**

**Contexto.** Hasta ahora el único correo que salía de Finlia era el de recuperación de contraseña (broker nativo de Laravel, ADR-0009). Las invitaciones a un hogar (Épica 2, ADR-0003) generaban un enlace con token que el administrador tenía que **copiar y enviar a mano** por WhatsApp o donde fuera. Eso rompe el flujo justo en el momento más importante del producto —meter a la pareja o la familia en el hogar— y es el punto donde más gente abandona.

Al mismo tiempo, `docs/ARCHITECTURE.md` decía que el canal inicial de notificaciones era in-app y que "Email/WhatsApp/Push" quedaban "preparados como canales futuros". Redactado así, cualquiera podía leerlo como una invitación a mandar por correo los recordatorios de la Épica 9, los resúmenes mensuales o novedades del producto.

Hay tres razones para no dejar esa puerta abierta:

1. **Privacidad.** Finlia maneja datos financieros de una familia. Un correo es texto plano en un buzón ajeno, reenviable e indexable. Cuantos menos correos salgan, menos superficie de exposición.
2. **Confianza.** Una app de finanzas que empieza a mandar resúmenes y novedades se percibe como spam y acaba en la carpeta de correo no deseado — arrastrando consigo los dos correos que **sí** son imprescindibles.
3. **Coste operativo.** El despliegue es hosting compartido (ADR-0002): sin workers persistentes, con cuotas de envío del proveedor y una reputación de dominio frágil. El volumen de correo debe ser mínimo por diseño, no por configuración.

**Decisión.**

1. **Finlia envía correo solo cuando el destinatario no puede ver el mensaje dentro de la app.** Ese es el criterio único, y hoy da exactamente dos casos:
   - **Invitación a un hogar** — el invitado puede no tener cuenta todavía; no hay bandeja in-app donde avisarle. *(Nuevo en esta entrega.)*
   - **Recuperación de contraseña** — el usuario está fuera de la sesión por definición.
2. **Todo lo demás es in-app.** Los recordatorios de la Épica 9 (pagos recurrentes, cuotas de deuda, metas), los resúmenes, los informes y cualquier comunicación de producto o marketing **no** salen por correo. Se rectifica la redacción de `docs/ARCHITECTURE.md` §7 y de la Épica 9 en `docs/ROADMAP.md`, que sugerían lo contrario.
3. **Ningún correo transporta datos financieros.** Ni saldos, ni montos, ni movimientos, ni nombres de cuentas. La invitación lleva únicamente el nombre del hogar, el nombre de quien invita y el enlace.
4. **El correo es una comodidad, nunca el mecanismo.** La invitación se crea y es válida aunque el envío falle: `HouseholdService::inviteMember()` captura cualquier `Throwable`, lo registra sin token ni enlace, y devuelve `false` en el tercer elemento de su tupla. La UI siempre muestra el enlace manual como respaldo, con un texto distinto según haya salido el correo o no.
5. **Interruptor y detección de transport falso.** `finlia.mail.enabled` apaga el correo transaccional. Además, con los transports `log` y `array` (desarrollo y tests) se considera que **no hay entrega real**: la app no le promete al administrador un correo que nadie va a recibir.
6. **Envío síncrono.** Son dos correos puntuales disparados por una acción explícita del usuario. Encolarlos ahora obligaría a montar el cron de `queue:work` (ver `docs/DEPLOYMENT.md` §6) y, si ese cron no existe, las invitaciones se perderían en silencio — peor que esperar un segundo.
7. **Añadir un correo nuevo exige un ADR** que justifique por qué ese aviso no puede ser in-app.

**Alternativas (descartadas).**
- **Dejar solo el enlace manual** (statu quo) — cero infraestructura, pero traslada el trabajo al usuario justo en el paso decisivo y hace que el token circule por canales que no controlamos.
- **Notificación de Laravel (`Notification::route('mail', ...)`) en vez de un Mailable** — equivalente en resultado, pero el sistema de notificaciones está pensado para entidades con varios canales; el invitado aún no es un `User`. Un Mailable expresa mejor lo que es: un correo suelto a una dirección.
- **Encolar el envío desde ya** (`ShouldQueue` + driver `database`) — correcto en cuanto haya volumen, pero hoy depende de un cron que aún no está configurado y fallaría en silencio. Se pospone a la Épica 11 (hardening/producción), donde el cron entra en el checklist de despliegue.
- **Plantillas markdown de Laravel (`mail::message`)** — más rápidas de escribir, pero sus componentes (botón, subcopia "If you're having trouble clicking...") vienen en inglés y obligarían a publicar y traducir las vistas de `vendor`. Se usa Blade HTML autocontenido con estilos en línea, íntegramente en español.
- **Mandar los recordatorios de la Épica 9 por correo** — es la vía fácil para "engagement", y es exactamente lo que esta decisión prohíbe: convierte una app de finanzas en una fuente de ruido y quema la reputación del dominio.

**Consecuencias y mitigaciones.**
- El correo de recuperación de contraseña usa la plantilla nativa de Laravel, cuyo texto vive en el framework. Se traduce con `lang/es.json` (resuelto en 0.8.1), de modo que los dos únicos correos de Finlia hablan el mismo idioma. Las claves de ese archivo deben coincidir **carácter a carácter** con las cadenas del framework —incluidos el salto de línea y las comillas escapadas de la subcopia del botón—, o la traducción se ignora sin avisar; hay tests que renderizan el correo y fallan si vuelve a salir en inglés.
- `HouseholdService::inviteMember()` pasa a devolver **tres** elementos. El desestructurado de dos que usan los tests existentes sigue siendo válido en PHP, así que no hubo cambios en cascada.
- El Service llama a `Mail::` y el Mailable construye la URL con `route()`. No rompe ADR-0010: no se toca `request()`, `session()` ni `Auth::id()` — el nombre de quien invita entra como **dato explícito** desde el controlador, y `route()` es determinista a partir de `APP_URL`. Una futura API (Épica 14) reutiliza el envío sin escribir nada.
- Sin SMTP configurado (`MAIL_MAILER=log`), la app funciona exactamente como antes de esta entrega. La configuración de producción está en `docs/DEPLOYMENT.md` §4.
- Un buzón inexistente o un SMTP caído generan un `Log::warning`, nunca un error visible: el administrador ve el enlace manual y sigue adelante.

**Estado.** ACEPTADA — 2026-08-26. Implementada en `HouseholdInvitationMail`, `HouseholdService::inviteMember()`, `config/finlia.php` (`finlia.mail`) y las vistas `emails/households/invitation*`.

> **Enmendada en 2026-08-30 por [ADR-0028](#adr-0028)**: se añade un tercer correo, el digest diario de recordatorios, **opt-in por miembro** y solo con urgentes. El espíritu se mantiene intacto — jamás marketing, jamás correos por evento — y la propia 0028 es el ADR que el punto 7 exigía. El resto de esta decisión no cambia.

---

## ADR-0016
### Rediseño mobile-first adelantado (Épica 10 parcial) + sistema de diseño propio — **ACEPTADA**

**Contexto.** El producto encargó un rediseño de Panel, Movimientos y Registrar gasto/ingreso a
través de Claude Design (mobile-first, "liquid glass" sutil, dark/light) porque el feedback sobre
el estado actual era concreto: la marca se veía "demasiado neón" y los botones del panel "no
mantienen una armonía" — mucha información con distinto peso visual, sin jerarquía. El diseño
salió en **dos direcciones** igual de válidas ("Enfoque": una sola cifra y un gesto; "Control": el
mes de un vistazo) sin que el histórico de chat del encargo se decantara por una.

La Épica 10 (UX mobile y PWA) es la que formalmente cubre "navbar/botones/forms optimizados
móvil" y "botón flotante +", pero está después de las épicas 5-9 en el orden declarado. El
theming ya sentó el precedente de adelantar trabajo puramente visual cuando el producto lo pide
explícitamente (ver comentario en `resources/css/app.css` sobre la Épica 1).

**Decisión.**

1. **Adelantar la porción de Épica 10 que el rediseño cubre**, igual que se hizo con el theming en
   la Épica 1: navegación inferior móvil + FAB, sidebar de escritorio pulido, y el rediseño visual
   de Panel/Movimientos/Registrar gasto-ingreso. Lo que la Épica 10 pide y el rediseño **no** cubre
   (manifest/instalación PWA, selects inteligentes, FAB con transferencia/aporte/pago de deuda —
   dependen de las épicas 6-7) queda **sin implementar**, tal como manda la regla de "no
   implementes lo que la épica todavía no pide".
2. **Una sola variante: "Enfoque".** Se probó primero con ambas direcciones conmutables en sesión
   (`design_variant()`), pero al verlas en uso el producto se decantó explícitamente: el
   dashboard-resumen de "Control" (anillo de presupuesto + `doughnut` de Chart.js debajo) "no
   aporta mucho" y hacía ver "rara" la barra lateral de escritorio (el segmentado
   Enfoque/Control no encajaba ahí). Se retiró "Control" por completo —helper, controlador, ruta,
   partial y CSS del anillo/grilla de accesos— y el Panel quedó con una sola forma de mostrarse:
   la cifra "puedes gastar hoy" + un gesto (Gasto/Ingreso).
3. **El resultado del rediseño se documenta como sistema de diseño reutilizable**
   ([docs/UI_DESIGN.md](UI_DESIGN.md)), no como un one-off del Panel. Todo componente nuevo
   (`.chip`, `.segmented`, `.hero-card`, el patrón de icono-tintado-por-categoría, el input de
   dinero con formato de miles) se documenta con **cuándo usarlo y cuándo no**, para que las
   épicas siguientes (5-9, que añaden pantallas nuevas) lo usen por defecto en vez de reinventar
   Bootstrap suelto pantalla a pantalla — que es exactamente el problema que originó el encargo.
4. **Los controles reales nunca se sustituyen por el atajo visual.** El importe grande de
   "Registrar gasto" sigue siendo un `<input name="amount" required>` real (con formato de miles
   en vivo vía `data-money-input`, ver §UI_DESIGN.md); los chips de categoría son un atajo que fija
   el `<select>` real por JS, sincronizado en los dos sentidos (elegir un chip resalta solo ese
   chip; cambiar el `<select>` a mano resincroniza cuál chip, si alguno, queda iluminado). Se
   prioriza no romper la validación HTML5 ni la cobertura Playwright existente por encima del
   parecido pixel-perfect con la maqueta.
5. **El offcanvas del sidebar móvil abre desde el lado de su botón.** Lo activa "Más", que vive en
   el extremo derecho de la barra inferior — por eso es `offcanvas-end` (desde la derecha), no
   `offcanvas-start`: abrirlo desde el lado contrario al botón que lo dispara se sentía
   desconectado del gesto que lo originó.

**Alternativas (descartadas).**
- **Esperar a la Épica 10 en su orden natural** — coherente con el protocolo, pero deja el
  feedback de diseño (ya encargado y pagado en tiempo de diseño) sin usar durante 5+ épicas, y
  arriesga que el criterio de diseño se pierda o haya que rehacerlo.
- **Mantener "Enfoque" y "Control" conmutables indefinidamente** — es lo que se implementó
  primero, precisamente para no forzar una decisión de producto sin que el usuario la hubiera
  tomado. Una vez el usuario las vio en uso y pudo comparar, la decisión sí se tomó (punto 2) y
  mantener las dos ya no aportaba —solo una rama de código y una UI de más que mantener.
- **Sustituir el input de importe por un teclado numérico a medida** (como en la maqueta) —
  visualmente más fiel, pero exige reimplementar la validación (`required`, tipo, `:invalid`) en
  JS y rompe la prueba e2e `no permite guardar un gasto sin cuenta ni valor`. Se prefirió un
  `<input type="text" inputmode="decimal" data-money-input>` real con formato en vivo: mismo
  resultado visual (miles separados) sin tocar la validación nativa.

**Consecuencias.**
- La Épica 10 pasa a 🟡 en `docs/ROADMAP.md` con una nota explícita de qué falta.
- Nueva tabla de colores de marca/estado (`--finlia-primary/-success/-danger/-warning`, ver
  `resources/css/app.css`), más desaturada que la original de la Épica 1 — cualquier hex de marca
  hardcodeado en una vista (incluidos los correos, que no pueden usar custom properties) debe
  mantenerse sincronizado a mano.
- Épicas 5-9 (aún no iniciadas) deben construir sus vistas nuevas sobre
  [docs/UI_DESIGN.md](UI_DESIGN.md); `docs/CONVENTIONS.md`, `docs/ARCHITECTURE.md`, `CLAUDE.md`,
  `AGENTS.md` y los skills/agentes de Claude Code (`implement-epic`, `epic-implementer`,
  `laravel-reviewer`) quedan actualizados para exigirlo por defecto.
- El sidebar de escritorio y el offcanvas móvil ("Más") comparten el mismo `<aside>`: los enlaces
  que ya tienen hueco en la barra inferior (Panel, Movimientos, Presupuesto) se ocultan en móvil
  con `d-none d-lg-block` para no duplicar destino — cualquier ítem nuevo del sidebar que se añada
  a la barra inferior debe recibir la misma clase.

**Estado.** ACEPTADA — 2026-08-27. Implementada en `resources/css/app.css`, `layouts/app.blade.php`,
`layouts/partials/mobile-bottom-nav.blade.php`, `dashboard.blade.php` + `dashboard/_hero-enfoque.blade.php`,
`movements/{index,_item}.blade.php`, `expenses/incomes/_form.blade.php`, `resources/js/app.js`
(`window.FinliaMoney`), y documentada en [docs/UI_DESIGN.md](UI_DESIGN.md).

---

## ADR-0017
### Identidad de marca Finlia (símbolo de puntos, petróleo/cobre) — **ACEPTADA**

**Contexto.** El rediseño mobile-first (ADR-0016) fijó los tokens `--finlia-primary`
(`#0f6f66`/`#57b6a8`) como un verde-teal **provisional**, elegido solo para bajar el "neón" del
verde de fábrica de la Épica 1 — nunca hubo un ejercicio real de identidad de marca. El producto
encargó ese ejercicio a Claude Design ("Finlia — Marca") y entregó un símbolo aprobado: **treinta
puntos en rejilla 6×5, los días del mes** — veinticuatro en petróleo (días transcurridos), seis en
cobre (días que el dinero disponible todavía cubre). Es la cifra "puedes gastar hoy" del Panel
convertida en marca, con paleta (`#0B3F44` petróleo / `#C08A3E` cobre / equivalentes de tema
oscuro) y reglas de uso (espacio mínimo, simplificación por tamaño, "el cobre siempre es lo
disponible") documentadas en el propio entregable.

**Decisión.**

1. **El verde provisional se reemplaza por la marca aprobada.** `--finlia-primary` pasa a
   petróleo `#0B3F44` (claro) / teal oscuro `#3F8F8A` (oscuro — petróleo puro no tiene contraste
   suficiente sobre fondos casi negros, mismo criterio que ya se usó para el resto de la paleta).
   Los colores de **estado** (`--finlia-success/-danger/-warning`) no cambian: son semántica de
   éxito/peligro/aviso, independiente de la identidad de marca.
2. **Nuevo token `--finlia-accent` (cobre), con un solo uso permitido: "dinero disponible".** La
   regla del propio entregable de marca ("el cobre representa siempre lo disponible, en la marca
   y en la interfaz") es una instrucción explícita, no solo para el logo — se aplica a la cifra
   hero del Panel (`dashboard/_hero-enfoque.blade.php`) y a la tarjeta de disponible de
   Presupuestos (`components/available-money-card.blade.php`), y a ningún otro sitio. No se
   convierte en un "segundo color de marca" de uso libre.
3. **El símbolo entra a la UI como `<x-brandmark>`** (`resources/views/components/brandmark.blade.php`),
   SVG inline con los colores fijos del icono con contenedor —invariante entre temas, igual que
   `public/finlia-icon.svg`—, reemplazando el `<i class="bi bi-wallet2">` que hacía de "logo"
   provisional en navbar, sidebar móvil y la pantalla de login/registro. Favicon real
   (`layouts/partials/favicon.blade.php`: SVG + PNG + apple-touch-icon) reemplaza el `.ico` por
   defecto de Laravel (que se deja como *fallback* legado, no se borra, por si algún cliente viejo
   lo pide directamente en vez de leer los `<link>`).
4. **Los archivos de marca "planos" (`finlia-logo.svg`, `-claro.png`, `-oscuro.png`) no se usan
   dentro de la app.** Llevan el texto "Finlia" en un color fijo (no seguirían el toggle de tema);
   dentro de la app ese texto ya es HTML normal que hereda el color correcto solo con
   `<x-brandmark>` al lado. Esas piezas quedan para contextos sin CSS vivo (email, redes,
   portafolio).
5. **`--finlia-bg` (el fondo general de la app) no se toca.** La marca trae sus propios "Claro"
   (`#E8F1F0`) y "Fondo oscuro" (`#0B1C1F`) para piezas de marca, pero repintar toda la superficie
   neutra de la app con esos tonos es un cambio de mucho más alcance que "usar el logo y el color
   donde corresponda" — el símbolo/favicon/color de marca son piezas puntuales, no una repintada
   general.

**Alternativas (descartadas).**
- **Mantener el verde-teal provisional e ignorar el entregable de marca** — es exactamente lo que
  el usuario pidió corregir; el verde nunca fue una decisión de identidad, era un parche de "baja
  el neón" sobre el color de fábrica.
- **Usar cobre como acento secundario libre** (p. ej. para cualquier botón "secundario" o badge
  destacado) — más flexible para el agente, pero contradice la regla explícita del entregable de
  marca ("nunca se usa para todo el símbolo"/"siempre lo disponible") y diluye la señal: si el
  cobre aparece en todas partes, deja de significar "esto es tu dinero disponible".
- **Repintar `--finlia-bg`/`--finlia-bg-2` a los tonos "Claro"/"Fondo oscuro" de la marca** — más
  coherente visualmente en el límite, pero es un cambio de alcance mucho mayor (toca cada
  superficie neutra de la app) que lo pedido, y no estaba en el entregable de marca como regla
  ("el fondo general" no es una de las piezas que define `docs/BRAND.md`).

**Consecuencias.**
- Todo hex de marca hardcodeado que quedó del rediseño anterior (`#0f766e` en algún fallback,
  `INCOME_COLOR` de `resources/js/charts.js`) se sincroniza al nuevo petróleo `#0B3F44`.
- `docs/UI_DESIGN.md` §2 y §6 y `docs/BRAND.md` (nuevo) quedan como referencia obligatoria para
  cualquier uso futuro del logo o del color de marca — ver también el checklist de UI_DESIGN.md.
- El favicon por defecto de Laravel (`public/favicon.ico`) queda huérfano (nada lo referencia ya
  explícitamente) pero no se borra: **no hay herramienta de conversión de imágenes en este
  entorno** para regenerarlo a partir del símbolo nuevo, así que se mantiene como fallback legado
  hasta que alguien lo regenere con las herramientas adecuadas.

**Estado.** ACEPTADA — 2026-08-27. Implementada en `resources/css/app.css`,
`resources/views/components/brandmark.blade.php`, `layouts/partials/favicon.blade.php`,
`layouts/{app,guest}.blade.php`, `dashboard/_hero-enfoque.blade.php`,
`components/available-money-card.blade.php`, `resources/js/charts.js`, `public/*` (assets de
marca), y documentada en [docs/BRAND.md](BRAND.md).

---

## ADR-0018
### Recurrentes: seams fijo/obligación por frecuencia, ocurrencias reales y "marcar pagado" — **ACEPTADA**

**Contexto.** La Épica 5 debe rellenar las claves `fixed_expenses` y `recurring` que la Épica 4
declaró en `0.0` como *seams* ([ADR-0014](#adr-0014)) sin tocar la fórmula ni la UI del
calculador. Eso deja cuatro decisiones abiertas: (1) qué separa un "gasto fijo" de una
"obligación futura" cuando la tabla es única; (2) cuánto compromete cada recurrente en una
ventana (la semana del calculador tiene 7 días, el mes 28-31); (3) cómo registrar el pago sin
duplicar la obligación en el cálculo; (4) si la app genera los gastos sola.

**Decisión.**

1. **Clasificación determinista por frecuencia** (`Frequency::isFixedLike()`): semanal,
   quincenal y mensual (y `custom` ≤ 31 días) alimentan `fixed_expenses`; trimestral,
   semestral y anual (y `custom` > 31 días) alimentan `recurring`. La fórmula resta ambas por
   igual, así que la separación es **transparencia del desglose** ("− Gastos fijos" vs "−
   Obligaciones próximas"), no aritmética; por eso no se le pide al usuario que clasifique.
2. **Comprometido por ocurrencias reales en la ventana, no prorrateo anual.**
   `committedInRange()` simula el cursor desde `next_date` (`addMonthNoOverflow()` /
   `addYearNoOverflow()` — seguro en años bisiestos: 29/feb/2028 → 28/feb/2029) y cuenta
   cuántas veces cae cada recurrente en `[from, to]`. Un mercado semanal compromete 4-5 veces
   en el mes; un SOAT anual solo si vence dentro de la ventana. Una obligación **vencida**
   cuenta su siguiente ocurrencia real: la regularización es manual ("marcar pagado").
3. **"Marcar pagado" es una transacción**: crea el gasto real vía `MovementService` (que
   recomputa el saldo, [ADR-0012](#adr-0012)) **si el recurrente tiene cuenta asociada**, y
   siempre avanza `next_date` una frecuencia. Sin cuenta (`expenses.account_id` es NOT NULL)
   solo avanza la fecha y el mensaje avisa para registrar el gasto a mano. La no-duplicación
   cae naturalmente: la ocurrencia sale del `comprometido` exactamente cuando entra al
   `gastado`, y el **disponible no cambia** (hay test que lo fija).
4. **Sin `auto_generate` todavía.** La generación automática de gastos exige el Scheduler
   (Épica 9, cron en Hostinger); en la Épica 5 todo pago es una acción explícita del usuario.
   La columna no se crea para no dejar un booleano muerto.
5. **Ahorro mensual necesario** = `amount × ocurrenciasPorAño / 12`, redondeado a 2 decimales
   (SOAT $600.000 anual → "separa ~$50.000/mes"). En la UI solo se muestra para frecuencias
   ≠ mensual, donde no aporta ruido.
6. **Cast `date:Y-m-d` en `next_date`.** El cast `date` por defecto serializa con el formato
   *datetime* del grammar (`'Y-m-d H:i:s'`), y SQLite escribía `2026-09-05 00:00:00` en una
   columna `date`; el formato explícito lo mantiene portable entre SQLite (tests) y MySQL.

**Alternativas (descartadas).**
- **Prorrateo mensual fijo** (el mismo `monthlySavings` para el comprometido) — más simple,
  pero infla la ventana semana (un arriendo de $1.200.000 comprometería $1.200.000 en 7 días
  aunque no venza) y contradice el espíritu del calculador: la semana pregunta "qué vence
  ahora".
- **Campo `is_fixed` elegido por el usuario** — más flexible, pero duplica lo deducible de la
  frecuencia y añade fricción a un formulario que debe ser rápido.
- **Comando que auto-genera gastos al día** — es exactamente el alcance de la Épica 9; hacerlo
  aquí mezclaría épicas.

**Consecuencias.**
- `BudgetCalculatorService` no cambia su fórmula: inyecta `RecurringExpenseService` y
  rellena los dos seams (patrón que repetirán Deuda y Metas).
- La Épica 9 añadirá `auto_generate` como migración + scheduler, reutilizando
  `markAsPaid()` como rutina de generación.
- `frequency_interval` solo es obligatorio para `custom` (1-3650 días); el Form Request lo
  descarta para las demás frecuencias.

**Estado.** ACEPTADA — 2026-08-27. Implementada en `app/Enums/Frequency.php`,
`app/Services/RecurringExpenseService.php`, `app/Services/BudgetCalculatorService.php`,
`app/Models/RecurringExpense.php` y `app/Http/Controllers/RecurringExpenseController.php`.

---

## ADR-0019
### Los recursos financieros solo se operan desde su hogar activo — **ACEPTADA**

**Contexto.** Un `/security-checklist` sobre la Épica 5 destapó una fuga entre hogares **explotable desde la Épica 3**, no introducida por la 5.

Había dos capas midiendo hogares distintos:

- las **Policies** autorizaban contra el hogar **del recurso** (`$resource->household_id`);
- los **Form Requests** acotaban `account_id`/`category_id` al hogar **activo en sesión** (`active_household_id()`).

Para un usuario de un solo hogar coinciden. Para uno que pertenece a **varios** (escenario soportado por [ADR-0011](#adr-0011)) no, y ahí se abría el hueco: con el hogar A activo se podía editar un recurso del hogar B enlazándole una **cuenta de A**. Verificado ejecutando el ataque:

```
>>> gasto creado: household_id=2 account_id=1   (hogar B=2, cuenta del hogar A=1)
>>> saldo cuenta de A: inicial='1200000.00' actual='700000.00'
>>> tercero (solo miembro de B) ve "CuentaSecretaDeA": SÍ — FUGA
```

Es decir: el saldo del hogar A se alteraba por actividad del hogar B, y un miembro de B **sin ninguna relación con A** veía el nombre de una cuenta de A en `/movimientos`. Amenaza #1 de [SECURITY.md](SECURITY.md).

**Decisión.** Autorizar un recurso financiero exige **dos** condiciones, no una:

1. que el usuario sea **miembro** del hogar dueño del recurso, y
2. que ese hogar sea además su **hogar activo**.

Se implementa en un trait único, `App\Policies\Concerns\ChecksHouseholdAccess`, que usan las siete policies de recursos financieros (Account, Category, Expense, Income, Budget, ExpectedIncome, RecurringExpense). Con el invariante, hogar-de-validación y hogar-de-autorización son **siempre el mismo** y la discrepancia deja de existir. Para operar sobre otro hogar hay que activarlo, que es justo lo que hace la UI.

`HouseholdPolicy` y `HouseholdInvitationPolicy` quedan **fuera** a propósito: gestionar un hogar (verlo, renombrarlo, invitar, activarlo) tiene que poder hacerse desde fuera del hogar activo o sería imposible cambiar de hogar.

**Alternativas (descartadas).**
- **Acotar los Form Requests al hogar del recurso** en vez de a la sesión — arregla los campos que te acuerdes de acotar, y deja viva la clase de fallo para el siguiente campo o recurso que se añada. Es un parche, no un invariante.
- **Global scope `HouseholdScope`** — impediría incluso resolver el modelo (404 en vez de 403) y complica los tests y cualquier lectura administrativa futura. Reconsiderable en la Épica 11.
- **Dejarlo como estaba** — descartado: la fuga alcanza a terceros que nunca tuvieron acceso.

**Consecuencias.**
- Un usuario multi-hogar que abra por URL un recurso de un hogar no activo recibe **403** en vez de operar sobre él. Es el comportamiento correcto y coincide con lo que la UI ofrece.
- Las Policies dependen ahora de `active_household()` (estado de sesión) también en `view/update/delete`, no solo en `create`. Es capa HTTP dentro de capa HTTP, coherente con [ADR-0011](#adr-0011); los Services siguen sin tocar la sesión ([ADR-0010](#adr-0010)).
- Los tests de aislamiento clásicos **no cubrían** esto: usan un intruso que no es miembro de nada, así que la membresía ya lo frenaba. Se añade `tests/Feature/Household/MultiHouseholdIsolationTest.php` con el caso multi-hogar recurso por recurso.
- Efecto colateral positivo: elimina siete copias de `userInHousehold()`.

**Estado.** ACEPTADA — 2026-08-22, a raíz del `/security-checklist` de la Épica 5. Suite completa (212 PHPUnit + 18 E2E) en verde tras el cambio, sin ajustar ningún test existente.

---

## ADR-0020
### Saldo de deuda derivado de una línea base más los pagos — **ACEPTADA**

**Contexto.** La Épica 6 guarda `original_amount` y `current_balance`. Había que decidir qué es `current_balance`: un número que el usuario teclea y mantiene a mano, o algo derivado de los pagos registrados.

El proyecto ya resolvió la pregunta equivalente para cuentas en ADR-0012 (saldo persistido y recomputado desde la fuente en cada escritura). Pero una deuda tiene una complicación que una cuenta no tiene: **los intereses**, y la posibilidad de **refinanciar**, que cambia el punto de partida.

**Decisión.**

1. **`current_balance` es derivado, nunca se teclea.** Se recalcula como `línea base − Σ pagos posteriores a esa línea base`, y se persiste (igual que ADR-0012: derivado pero materializado, para no recalcular en cada lectura).
2. **La línea base es `original_amount`**, o el `refinanced_balance` de la **refinanciación más reciente** si la hay. Los pagos anteriores a una refinanciación ya están incorporados en el saldo refinanciado, así que no se vuelven a restar.
3. **El saldo nunca baja de cero.** Un sobrepago deja la deuda en 0, no en negativo: un saldo negativo sería un préstamo a favor, que no es lo que modela esta entidad.
4. **El estado se mueve solo entre `active` y `paid`.** Al llegar a cero la deuda se marca pagada; si vuelve a haber saldo (se borró un pago) regresa a activa. `refinanced` y `written_off` los pone el usuario y el sistema no los pisa.
5. **`current_balance` no es fillable** y el Form Request no lo acepta, así que no se puede forzar por mass assignment.

**Alternativas (descartadas).**
- **Saldo tecleado por el usuario** — es lo que hace una hoja de cálculo, y arrastra su mismo problema: en cuanto el usuario registra un pago y olvida actualizar el saldo, los dos números se contradicen y el panel de deuda deja de ser fiable.
- **Saldo derivado con amortización real de intereses** (recalcular mes a mes aplicando la tasa) — es lo correcto para un banco, pero exigiría conocer la fecha exacta de capitalización, los días de mora, las cuotas de manejo y los seguros. Sin esos datos la simulación daría un número *preciso pero falso*, que es peor que uno aproximado y honesto.
- **Saldo incremental** (sumar/restar en cada pago sin recomputar) — un borrado o una edición dejan el saldo desviado para siempre, sin forma de detectarlo.

**Consecuencias y mitigaciones.**
- **Los intereses no se acumulan solos.** El saldo solo baja con los pagos; si el banco capitaliza intereses, el saldo real será mayor que el que muestra Finlia. La vía para corregirlo es registrar una **refinanciación** con el saldo real, que fija una nueva línea base. Está documentado en la UI como estimación.
- La proyección de fin de deuda (`projectPayoff`) **sí** aplica la tasa mes a mes, pero solo para *estimar* una fecha, nunca para mover el saldo guardado. La UI lo marca como estimación y advierte de lo que no contempla.
- Borrar un pago devuelve el saldo automáticamente: no hay que recordar ajustar nada.
- Cambiar `original_amount` mueve la línea base, así que el controlador recalcula el saldo tras cada edición.

**Estado.** ACEPTADA — 2026-08-29. Implementada en `DebtService::recalculateBalance()`, `baseline()` y las tablas `debts` / `debt_payments` / `debt_refinancings`.

---

## ADR-0021
### Un pago de deuda genera el movimiento real de la cuenta — **ACEPTADA**

**Contexto.** Pagar una deuda son dos hechos a la vez: baja lo que debes **y** sale dinero de una cuenta. Si Finlia solo registrara lo primero, el saldo de la cuenta quedaría inflado y el "puedes gastar" mentiría justo después de la operación más importante del mes.

La Épica 5 ya resolvió el caso gemelo: "Marcar pagado" en un gasto recurrente crea el `Expense` sobre la cuenta asociada.

**Decisión.**

1. **`debt_payments.account_id` es opcional.** Si se indica una cuenta del hogar, `DebtService::registerPayment()` crea el `Expense` correspondiente vía `MovementService` **en la misma transacción**: o quedan las dos cosas, o ninguna. El pago guarda `expense_id` para no perder el vínculo.
2. **Sin cuenta, solo baja la deuda.** Es el caso del pago en efectivo o desde una cuenta que el hogar no lleva en Finlia. La UI lo dice explícitamente en vez de inventarse un movimiento.
3. **Borrar un pago deshace las dos cosas**: elimina el gasto (lo que devuelve el dinero al saldo de la cuenta, ADR-0012) y recalcula la deuda.
4. **El término `debt` del dinero disponible cuenta solo las cuotas PENDIENTES** de la ventana. Si la cuota del mes ya se pagó, sale del comprometido, porque ese dinero ya figura como gasto. Sin esta resta, pagar una deuda bajaría el "puedes gastar" **dos veces**: una como gasto y otra como cuota comprometida.

**Alternativas (descartadas).**
- **Crear siempre el gasto, obligando a elegir cuenta** — rompe el caso real del pago en efectivo o desde una cuenta no registrada, y forzaría al usuario a inventarse una cuenta falsa.
- **No crear nunca el gasto** — deja el saldo de las cuentas mintiendo y obliga a registrar el mismo pago dos veces, a mano, en dos pantallas distintas.
- **Un tipo de movimiento propio "pago de deuda"** en lugar de un `Expense` — duplicaría el motor de movimientos y su recálculo de saldos por una distinción que la descripción del gasto ya expresa.

**Consecuencias y mitigaciones.**
- El gasto generado lleva la descripción `"<deuda> (pago de deuda)"`, así que se distingue en /movimientos sin necesidad de un tipo nuevo.
- La categoría es opcional: si el hogar tiene una categoría "Deudas" puede elegirla y el pago entra en sus informes de gasto por categoría.
- `expenses` usa borrado lógico, así que al borrar un pago el movimiento queda como historial pero deja de contar para el saldo.
- La FK `expense_id` es `nullOnDelete`: borrar el movimiento desde /movimientos no borra el historial de la deuda, solo rompe el vínculo.

**Estado.** ACEPTADA — 2026-08-29. Implementada en `DebtService::registerPayment()` / `deletePayment()` / `committedInRange()` y en `BudgetCalculatorService`.

---

## ADR-0022
### La deuda se pacta en cuotas, y el pago mínimo no es el plan de pago — **ACEPTADA**

**Contexto.** Al usar el módulo de deudas recién entregado (Épica 6) salieron dos problemas de modelado que el propio usuario detectó:

1. **`end_date` como fecha que se teclea.** Nadie pacta con un banco una *fecha* de fin: se pactan **cuotas**. La fecha es la consecuencia, no el dato. Además, pedir una fecha libre deja pasar plazos absurdos (una tarjeta a 20 años).
2. **`minimum_payment` + `scheduled_payment` ("cuota pactada").** El nombre miente: no es lo que pacta la entidad, es lo que el usuario **decide** pagar. Puestos uno al lado del otro sin explicación, nadie sabe cuál rellenar.

**Decisión.**

1. **El plazo se expresa en `term_months` (número de cuotas)** y `end_date` pasa a ser **derivada**: `start_date + term_months`, calculada en `DebtService`. Deja de ser `fillable` y desaparece del formulario. Sin fecha de inicio no hay fin previsto, y se dice así en lugar de inventarlo.
2. **Tope de cuotas por tipo de deuda** (`DebtType::maxTermMonths()`): tarjeta 100, vehículo 96, hipotecario 480, y 120 para préstamo, familiar y otras. Son topes **prácticos** para atajar errores de dedo (escribir 1200 en vez de 120), **no límites legales**: ninguna norma colombiana fija estos números y cada entidad tiene los suyos, así que se es deliberadamente generoso.
3. **Nuevo tipo `DebtType::Mortgage`** (crédito hipotecario). No estaba en la Épica 6 y su plazo no cabe en ningún otro tipo: registrarlo como "préstamo" lo habría limitado a 120 cuotas cuando lo normal son 180-240.
4. **`scheduled_payment` se renombra a `planned_payment`**, y los dos campos se explican por lo que son:
   - **Cuota mínima** (`minimum_payment`): lo que **exige la entidad**. Es la obligación; por debajo hay mora.
   - **Lo que planeas pagar** (`planned_payment`): lo que **tú decides**. Vacío significa "solo el mínimo".
   - Validación: el plan **nunca** por debajo del mínimo, pero solo se compara si hay mínimo declarado (si no, `gte` compararía contra null y rechazaría cualquier valor).
5. **El comportamiento no cambia**: `monthlyCommitment()` sigue siendo `plan ?? mínimo`. Ya era lo correcto —lo que sale del bolsillo es lo que vas a pagar de verdad— y esa cifra alimenta tanto el dinero comprometido como la proyección. Lo que cambia son los nombres.

**Alternativas (descartadas).**
- **Dejar la fecha de fin y solo validarla** — no resuelve la objeción de fondo: se seguiría pidiendo un dato que el usuario no tiene a mano y que puede teclear mal.
- **Un solo campo de cuota** — más simple, pero pierde cuál es el mínimo exigido, que es justo lo que marca la mora en una tarjeta y lo que hace útil el aviso de "estás pagando solo el mínimo".
- **Solo cambiar las etiquetas, sin tocar la columna** — habría dejado un nombre que miente en la base de datos, y el siguiente que lea `scheduled_payment` volverá a entender "cuota pactada con el banco".
- **Topes legales por tipo** — no existen como tales; fingir una regulación que no se ha verificado sería peor que un tope práctico declarado como tal.

**Consecuencias y mitigaciones.**
- Migración de renombrado sobre una tabla recién creada. Se verificó `up` y `down` contra el esquema real, no solo en tests.
- La refinanciación ya traía `term_months` e `installment`: ahora también copia el plazo a la deuda y su cuota va a `planned_payment`, que es lo que es.
- El formulario ajusta el tope de cuotas al tipo elegido con JavaScript, pero **la validación de verdad está en el servidor**: el JS es comodidad, no control (docs/SECURITY.md).
- Sigue sin acumularse interés (ADR-0020): el plazo pactado y el plazo que sale de la proyección pueden no coincidir, y son cosas distintas a propósito — uno es lo acordado, el otro lo que pasaría a tu ritmo real.

**Estado.** ACEPTADA — 2026-08-30, a petición del usuario tras probar la Épica 6. Implementada en `DebtType`, `Debt`, `DebtService`, `StoreDebtRequest` y el formulario de deudas.

---

## ADR-0023
### Registrar una deuda es un simulador de crédito, no un formulario en blanco — **ACEPTADA**

**Contexto.** Al usar el alta de deudas salieron tres problemas de experiencia, y los dos últimos son el mismo:

1. **El formulario vivía incrustado en el panel**, siempre visible. Uno adquiere deudas de vez en cuando, no a diario: ocupaba la mitad de la pantalla para algo que se usa dos veces al año.
2. **Se podía registrar una deuda imposible.** El caso real: 10.000.000, tasa 0 %, cuota mínima 0, 120 cuotas y un plan de 20.000 al mes. La aplicación lo aceptaba sin rechistar y después calculaba, con razón, que a ese ritmo se tardarían 500 meses. Los datos se contradecían entre sí desde el momento de guardarlos.
3. **Los campos eran los equivocados.** Se pedía la cuota como dato de entrada cuando en un crédito la cuota es *consecuencia* del monto, la tasa y el plazo. Es al revés de como lo pide cualquier banco.

**Decisión.**

1. **El alta se mueve a su propia pantalla** (`debts.create`), con un botón «Registrar deuda» en la cabecera del panel, siguiendo el patrón que ya usan cuentas y presupuestos. El botón es `w-100 w-sm-auto`, así que en móvil ocupa el ancho y en escritorio no.
2. **El formulario es un simulador.** El usuario declara lo que pacta —**monto, tasa y número de cuotas**— y la aplicación calcula **cuota mensual**, **fecha de fin** e **intereses totales**, en vivo mientras escribe.
3. **La cuota se muestra calculada y bloqueada**, con un interruptor «Mi entidad cobra otra cuota» que la desbloquea. Ni imponerla (una entidad real cobra seguros y cuota de manejo encima de la fórmula) ni dejarla en blanco (era la puerta a la incoherencia): calculada por defecto, ajustable a propósito.
4. **La coherencia se valida en el servidor**, no solo en el navegador:
   - la cuota debe cubrir al menos los intereses del primer mes, o el saldo sube en lugar de bajar;
   - la cuota debe bastar para saldar el monto en el plazo pactado, con un 1 % de holgura por redondeos. El mensaje dice **cuánto haría falta**, no solo que está mal.
5. **Una sola matemática** (`DebtCalculator`), compartida por el simulador, la validación y la proyección del panel. Si cada uno tuviera la suya, la cuota pactada y la fecha proyectada volverían a contradecirse en pantalla, que es el problema original.
6. **La tasa se interpreta como efectiva anual (E.A.)**, como se cotiza el crédito en Colombia, y la mensual equivalente es `(1 + EA)^(1/12) − 1`. Antes se dividía entre 12, que es la convención **nominal** y sobreestima el interés: con 28,5 % E.A. daba 2,375 % mensual en lugar del 2,114 % real.
7. **La cuota se redondea al céntimo hacia arriba.** Con el redondeo al más cercano se quedaba unos céntimos corta y hacía falta un mes extra para saldar el resto: el simulador decía «36 cuotas» y la proyección «37 meses».
8. **Sin JavaScript sigue funcionando**: si la cuota llega vacía, la deriva `DebtService` al guardar.

**Alternativas (descartadas).**
- **Un modal en vez de una pantalla** — el formulario tiene cuatro secciones; en un móvil un modal con scroll propio es peor que una página.
- **Dejar la cuota siempre editable con un aviso** — el aviso se ignora, y era exactamente lo que permitió registrar la deuda imposible.
- **Bloquearla del todo, sin escape** — obligaría a mentir a quien tenga una cuota real distinta por seguros o cuota de manejo.
- **Validar solo en el navegador** — se salta desactivando JavaScript o enviando la petición a mano; la validación de verdad tiene que estar en el Form Request.
- **Mantener la tasa nominal (`EA/12`)** — más simple, pero da cuotas más altas que las del banco y el simulador dejaría de servir para comparar.

**Consecuencias y mitigaciones.**
- Cambia la convención de tasa, así que las proyecciones existentes darán números algo distintos (menores). Es una corrección, no una regresión: los anteriores sobreestimaban el interés.
- Los datos de demostración dejan de fijar la cuota a mano y la derivan, de modo que el seeder no puede generar una deuda incoherente.
- Los campos de dinero pasan a `data-money-input` (docs/UI_DESIGN.md); antes usaban `type="number"`, que no admite el punto de miles.
- La confirmación de borrado pasa a `data-confirm`, el mecanismo que ya existía en `app.js` para no meter datos del usuario dentro de JavaScript en línea. Sustituye al parche con `@js()` de la 0.8.2 y elimina el JS en línea del todo.
- La validación vive en el Form Request, así que crear deudas por Service (seeder, factories, futura API) sigue sin comprobarla. Es coherente con ADR-0010: validar es responsabilidad de la capa HTTP.
- **Ninguna cifra se presenta como definitiva.** El componente `<x-debt-disclaimer />` avisa, en el alta, en el panel y en el detalle, de que cuota, intereses y fechas son estimaciones y de que cada entidad aplica sus propias reglas. No es letra pequeña al pie: es un bloque visible junto a los números, porque el error de leer una estimación como un estado de cuenta lo paga el usuario con su dinero. El interruptor «Mi entidad cobra otra cuota» refuerza lo mismo desde el otro lado: además de desbloquear el campo, recuerda que el banco cobra cosas que la fórmula no conoce.

**Estado.** ACEPTADA — 2026-08-30, tras probar el alta de deudas. Implementada en `DebtCalculator`, `DebtService`, `StoreDebtRequest`, `debts/create`, `debts/_form` y el simulador de `resources/js/app.js`.

---

## ADR-0024
### Avisos que el usuario da por leídos, por clave y en servidor — **ACEPTADA**

**Contexto.** El aviso de que las cifras de deuda son estimaciones (ADR-0023) ocupa media pantalla en un móvil y salía **en cada visita**. Un aviso que se ve siempre deja de leerse a la tercera vez: se convierte en ruido, que es lo contrario de lo que pretende. Pero quitarlo del todo tampoco vale — en una app de finanzas, que la advertencia desaparezca es el escenario que se paga con dinero del usuario.

Se plantearon tres caminos: descartarlo en el navegador (localStorage), aceptar una política una sola vez al registrarse, o descartarlo con constancia en servidor.

**Decisión.**

1. **El aviso se puede dar por leído, pero no desaparece: se reduce.** Primera vez, bloque completo con «Entendido, no mostrar de nuevo»; a partir de ahí, una línea discreta que sigue junto a las cifras. Deja de estorbar sin dejar de advertir.
2. **La constancia va en el servidor**, no en el navegador. Persiste entre dispositivos —lo cierras en el móvil y el portátil lo respeta—, sobrevive a un borrado de datos de navegación y deja registrada la fecha, que importa el día que haya Premium o una reclamación.
3. **Tabla por clave (`user_acknowledgements`), no una columna por aviso.** Metas de ahorro (Épica 7) y reportes (Épica 8) traerán advertencias parecidas; con una columna por cada una, `users` acabaría con media docena de `*_ack_at`. La tabla lleva `unique(user_id, key)`.
4. **La clave se valida contra un enum cerrado** (`AcknowledgementKey`). Llega en la URL, así que sin lista blanca cualquiera podría llenar la tabla de filas inventadas. Una clave desconocida es un 404.
5. **Es una preferencia del USUARIO, no del hogar.** Dos miembros del mismo hogar leen el aviso por separado; que uno lo descarte no se lo oculta al otro.
6. **Formulario normal, sin JavaScript.** Funciona con el JS desactivado y no depende del navegador para nada. El `user_id` sale siempre del usuario autenticado, nunca de la petición.
7. **`acknowledge()` es idempotente**: pulsar dos veces no duplica la fila ni mueve la fecha original.

**Alternativas (descartadas).**
- **localStorage o cookie** — cero backend, pero es por dispositivo y navegador: lo descartas en el móvil y reaparece en el portátil. Se pierde al borrar datos o en incógnito, y no queda constancia de que se leyó.
- **Aceptar una política al registrarse y no volver a avisar** — deja la interfaz limpia, pero los usuarios ya existentes nunca lo verían y la advertencia desaparece justo del sitio donde están los números. Seis meses después nadie recuerda qué aceptó. Además, redactar términos de aceptación tiene implicaciones legales que no conviene improvisar.
- **Una columna `debt_disclaimer_ack_at` en `users`** — más simple hoy, insostenible en cuanto haya tres avisos.

**Consecuencias y mitigaciones.**
- El componente consulta el acuse en cada render. `hasAcknowledged()` usa la relación si está cargada, para no lanzar una consulta por cada componente de la misma página.
- Si algún día conviene reponer un aviso ya descartado —porque el texto cambia de forma sustancial—, basta con una clave nueva (`debt_estimates_v2`); los acuses viejos quedan como historial.
- Es un mecanismo de comodidad, no de autorización: no protege nada, solo decide cuánto ocupa un aviso.

**Estado.** ACEPTADA — 2026-08-31, a petición del usuario tras ver el aviso en móvil. Implementada en `user_acknowledgements`, `AcknowledgementKey`, `UserAcknowledgement`, `User::hasAcknowledged()/acknowledge()`, `AcknowledgementController` y `<x-debt-disclaimer />`.

---

## ADR-0025
### Metas de ahorro: ahorrado derivado, aportes que no mueven cuentas y aporte mensual programado — **ACEPTADA**

**Contexto.** La Épica 7 introduce metas de ahorro. Había tres decisiones que no venían de la épica y afectan al modelo de dinero:

1. ¿De dónde sale "lo ahorrado"? La épica dibujaba `current_amount` editable por el usuario.
2. ¿Registrar un aporte debe mover dinero entre cuentas? "Guardar" puede entenderse como una transferencia (Nequi → ahorros).
3. ¿Cómo entra el ahorro al dinero disponible (ADR-0014)? El seam `savings` nació en cero esperando esta épica.

**Decisión.**

1. **`current_amount` es derivado, no tecleado** — espejo de ADR-0020 para deudas: la fuente de verdad es el historial de `savings_goal_contributions` (Σ aportes − Σ retiros), recalculado en cada escritura de movimiento. Lo que ya tenías ahorrado se registra como aporte inicial. Al alcanzar el objetivo la meta pasa de activa a **lograda** automáticamente (y vuelve a activa si se borra un movimiento), toggle que refleja el de deuda pagada. No se valida que un retiro deje la meta en positivo más allá de no retirar más de lo ahorrado.
2. **Los aportes NO mueven cuentas ni crean gastos.** Ahorrar no es gastar: si cada aporte fuera un gasto, el dinero disponible bajaría dos veces (una por el aporte, otra por el gasto de verdad cuando se use la meta) y las cifras mentirían. La transferencia real entre cuentas queda **deferida a la Épica 10** (botón "+" → transferencia). Un movimiento de meta es progreso hacia el objetivo, no un movimiento bancario.
3. **El compromiso de ahorro entra por `monthly_commitment`** (opcional, "cuánto destinarás al mes"): alimenta el seam `savings` del presupuesto **solo para metas activas**, contando como mucho lo que le falte a la meta (la última cuota nunca supera el faltante). Pausar una meta es exactamente dejar de comprometer ese dinero — por eso pausa y reactivación son acciones dedicadas y no un campo del formulario. El *aporte mensual recomendado* (lo que falta repartido en los meses hasta la fecha objetivo) es solo una **estimación informativa** en pantalla: no escribe `monthly_commitment` ni nada.

**Alternativas (descartadas).**
- **`current_amount` editable a mano** — rápido de construir, pero un número que nadie sabe cómo llegó ahí y que una edición rompe en silencio; además pierde el historial de aportes, que es justo lo que motiva.
- **Aporte = transferencia + gasto "ahorro"** — coherente en contabilidad, mentiroso en la práctica: duplica el impacto del ahorro en el disponible (ADR-0021 resolvió el caso contrario de las deudas, donde el pago SÍ mueve la cuenta porque es dinero que sale de verdad).
- **`current_amount` como columna fuente y `monthly_commitment` calculado de atrás hacia adelante** — el aporte que "deberías" hacer no es el que pactaste; mezclarlo con el comprometido del presupuesto sorprendería.

**Consecuencias y mitigaciones.**
- El saldo de cuentas no refleja "cuánto hay ahorrado en metas" (la cuenta no sabe de la meta). Es a propósito: las metas son una capa de significado sobre el mismo dinero. La UI lo deja claro en el formulario ("no mueve tu saldo").
- El compromiso entra al disponible aunque el aporte del mes no se haya registrado: es lo que la Épica 4 llama comprometido, no lo gastado.
- `household_id` se desnormaliza en `savings_goal_contributions` para poder aislar por hogar sin joins, igual que en `debt_payments`.
- Los aportes y retiros llevan `date <= hoy` (como las deudas): no se registra intención futura como si fuera dinero puesto.

**Estado.** ACEPTADA — 2026-08-31, al implementar la Épica 7. Implementada en `SavingsGoalService` (recalculo, toggles, `committedMonthly`, `recommendedMonthlyContribution`), el seam `savings` de `BudgetCalculatorService` y las vistas de metas.

---

## ADR-0026
### Reportes: panel ligero + pantalla dedicada, comparaciones honestas y CSV como único formato — **ACEPTADA**

**Contexto.** La Épica 8 pide un "dashboard" con resumen, cinco gráficos, comparación de períodos, insights y exportación — pero ella misma advierte *mobile-first: evitar demasiados gráficos simultáneos*. El Panel ya existe (las épicas 3-7 le fueron añadiendo tarjetas). Cuatro decisiones no venían resueltas: dónde viven los gráficos, cómo comparar períodos sin mentir, qué puede afirmar un insight, y cómo preparar el PDF sin construirlo todavía.

**Decisión.**

1. **El Panel se mantiene ligero; el dashboard completo vive en `/reportes`.** El Panel solo gana dos KPIs (deuda total, ahorro en metas) y el enlace "Ver reportes". Los cinco gráficos, la comparación de períodos y los insights van a la pantalla de reportes. La separación de propósitos queda así: Panel = *¿cuánto puedo gastar hoy?* (presente), Reportes = *¿qué ha pasado?* (histórico y comparación).
2. **Períodos comparables honestos** (`ReportPeriod::resolve`): cada período se compara contra su equivalente anterior inmediato (mes ↔ mes previo, últimos 3 ↔ los 3 anteriores a aquellos). El **año se compara YTD contra el mismo tramo del año previo** — jamás contra el año completo anterior, que incluiría meses aún no ocurridos y fabricaría caídas ficticias. Los porcentajes solo se calculan si la base anterior existe (> 0); si no, se muestra solo la diferencia absoluta.
3. **Insights = hechos descriptivos, con umbrales anti-ruido.** Solo afirmaciones calculadas sobre datos que existen (cinco reglas: cambio de gasto total ≥ 5 %, categoría que sube/baja ≥ 15 %, categoría dominante ≥ 10 % del gasto, balance en rojo). Nada de recomendaciones financieras ni de porcentajes sin base previa. Máximo cuatro por período.
4. **CSV es el único formato; `ReportFormat` es el seam del PDF.** El enum tiene un solo caso (`csv`) y el controlador un `match` sobre él: el PDF Premium (Épica 12) añade su caso y su rama, reutilizando las mismas filas del Service. El CSV sale en streaming con **BOM UTF-8, separador `;` y coma decimal** para que Excel en español lo abra bien sin importar nada, y la ruta lleva `throttle:10,1`.

**Alternativas (descartadas).**
- **Poner los cinco gráficos en el Panel** — viola la guía mobile de la propia épica y convierte la respuesta a "¿cuánto puedo gastar?" en un muro de gráficos que hay que scrollear.
- **Año contra año completo anterior** — la comparación más común en apps de banca, y la más deshonesta a mitad de año: enero–agosto 2026 vs todo 2025 siempre "cae".
- **Insights sin umbrales** — un +2 % en alimentación es ruido del calendario, no información; sin umbral la pantalla grita siempre y el usuario deja de leerla.
- **Log `report_exports` de exportaciones** — la épica lo menciona como posible; no lo exige y hoy no hay requisito de auditoría que lo justifique. Se deja fuera (se puede añadir sin migración rompiente si llega a hacer falta).

**Consecuencias y mitigaciones.**
- La evolución de deuda exigió calcular el saldo **en una fecha pasada** (`DebtService::balanceAt`): línea base vigente al corte (refinanciaciones incluidas) menos pagos hasta entonces, piso 0, y 0 antes de `start_date` — consistente con [ADR-0020](#adr-0020).
- Dos superficies que muestran dinero (Panel y Reportes) obligan a que ambas sacaran siempre las cifras de los mismos Services; ya lo hacen (`DebtService::summary`, `SavingsGoalService::summary`, `MovementSummaryService`).
- El enum de formatos en la query (`?format=csv`) hace que pedir `pdf` hoy de error de validación controlado, no 404 ni silencio.
- De paso se corrigió un bug latente: el JSON de los gráficos del Panel se inyectaba con `{{ }}` dentro de `<script type="application/json">`, y Blade escapa `"` a `&quot;` — que los navegadores **no** decodifican dentro de `<script>`, así que `JSON.parse` fallaba y los gráficos del Panel llevaban rotos desde la Épica 3. Ahora se usa `json_encode($data, JSON_HEX_TAG)` (seguro contra `</script>` y JSON válido).

**Estado.** ACEPTADA — 2026-08-29, al implementar la Épica 8. Implementada en `ReportService`, `ReportPeriod`, `ReportFormat`, `ReportController`, `DebtService::balanceEvolution` y las vistas `reports/index` y Panel.

## ADR-0027
### Recordatorios derivados de su fuente + tabla solo para avisos sueltos — **ACEPTADA**

**Contexto.** La Épica 9 pide avisos de cuatro orígenes (gasto recurrente, deuda, meta, obligación anual) con estados pendiente/próximo/vencido/completado, 100 % in-app ([ADR-0015](#adr-0015)). El diseño provisional de `docs/DATA_MODEL.md` planteaba una tabla con `source_type` morph, `user_id` y `scheduled_at`, "o usar `notifications` de Laravel — decidir en Épica 9". Tres problemas: los avisos de los tres primeros orígenes **ya existen** como datos (la fecha de vencimiento es `next_date`, el día de pago o `target_date`) y duplicarlos en otra tabla crea dos verdades que se desincronizan; una tabla poblada por cron puede quedar **caducada** si el cron no corre (hosting compartido); y el modelo de "notificación leída" es equivocado para pagos — marcar leída no debe silenciar un aviso de cuota.

**Decisión.**

1. **Derivar en vivo; la tabla es solo para avisos sueltos.** `ReminderService::list()` construye la lista unificada consultando las fuentes (`recurring_expenses` activos, deudas vigentes con cuota, metas vigentes con fecha) y le suma los avisos creados a mano por el usuario ("tecnomecánica", "renovar pasaporte"), que son los únicos que viven en `reminders`. La fuente es la verdad: no hay copia que desincronizar.
2. **El estado se deriva contra hoy, no se persiste.** `ReminderStatus::resolve(dueDate, today, 7)` decide vencido/próximo/pendiente en cada lectura; en la tabla solo se persisten `pending`/`completed`. Sin cron, los estados son correctos igual — el scheduler solo **ejecuta pagos** (`auto_generate`), nunca mantiene estados.
3. **"Completar" no genera gasto.** Un recordatorio es un aviso, no un movimiento (igual que los aportes de metas, [ADR-0025](#adr-0025)): si se repite, avanza una frecuencia; si no, queda completado. El gasto se registra desde su origen (marcar pagado, pago de deuda, aporte a meta) — la vista enlaza esa acción según `source`.
4. **Sin `user_id` ni `scheduled_at`.** El aviso es del **hogar** (coherente con el resto del multi-tenant) y la fecha de notificar se deriva de `due_date` − ventana; almacenarla sería otra copia desincronizable. `frequency` reutiliza el enum `Frequency` (subconjunto mensual→anual; `null` = una sola vez).
5. **Interruptor por hogar (`households.reminders_enabled`)** que solo el administrador mueve: apaga campanita, banner y listado de todo el hogar de una vez, sin tocar los datos subyacentes.
6. **Seam de canales:** WhatsApp/push futuros consumen `list()/summary()` tal cual; el servicio no conoce rutas ni HTTP ([ADR-0010](#adr-0010)) — la vista es la que decide qué acción sigue a cada origen.

**Alternativas (descartadas).**
- **Tabla `notifications` de Laravel** — su modelo es "evento con leído/no leído"; aquí el aviso se apaga pagando. Además cada recordatorio derivado tendría que crearse/actualizarse por cron, reintroduciendo el problema de caducidad.
- **Tabla densa con morph `source_type`/`source_id` para todo** — duplica `next_date`/`due_day`/`target_date` y exige un generador cron que, si falla, deja la campanita en silencio falso.
- **`user_id` por aviso** — rompería la semántica de hogar (el SOAT le urge a la casa, no a un miembro) y multiplicaría las filas por miembro.

**Consecuencias y mitigaciones.**
- Los ítems derivados no tienen `Reminder` en DB: la lista es una colección de arrays serializables con `source` + `id`, y las acciones (editar/borrar) solo existen para los sueltos — el resto se administra desde su épica.
- La cuota de deuda usa la colección de pagos **eager-loaded** (`with('payments')`) para saber si el mes ya está pagado, sin una query por deuda (no empeorar la deuda de performance #4 registrada para la Épica 11).
- `finlia:generate-recurring-payments` (diario 06:00, cron Hostinger) registra **una** ocurrencia por corrida con su **fecha real** (no hoy): un atraso de N meses se recupera en N corridas, sin una ráfaga de gastos todos fechados "hoy".
- La ventana de "próximo" quedó fijada en 7 días (`UPCOMING_DAYS`), distinta de la de 30 días de las próximas obligaciones de la Épica 5: la campanita avisa lo urgente, la planificación mira más lejos.

**Estado.** ACEPTADA — 2026-08-29, al implementar la Épica 9. Implementada en `ReminderService`, `Reminder`, `ReminderSource`, `ReminderStatus`, `ReminderPolicy`, `ReminderController`, la vista `reminders/index`, la campanita del navbar, el banner del Panel y el comando `finlia:generate-recurring-payments`.

---

## ADR-0028
### Digest de recordatorios por correo: opt-in, síncrono por cron y SMTP gratis (Brevo) — **ACEPTADA**

**Contexto.** A petición del producto se quiso correo para los recordatorios y push gratuito, con una restricción dura de infraestructura: el despliegue es **hosting compartido (Hostinger)**, sin VM ni workers persistentes — el único latido es el cron que ejecuta `schedule:run` cada minuto ([ADR-0008](#adr-0008), DEPLOYMENT §6). Además, [ADR-0015](#adr-0015) había cerrado el correo a invitación + contraseña con un criterio explícito: "solo lo que el destinatario no puede ver dentro de la app".

**Decisión.**

1. **Un digest diario, no correos por evento.** Comando `finlia:send-reminder-digests` (Scheduler, 06:30 — después del `generate-recurring-payments` de 06:00 para reflejar los pagos de la mañana). Un correo por hogar y miembro al día, **solo si hay urgentes** (`attention > 0`): sin urgentes hay silencio, porque el silencio también es información.
2. **Opt-in por miembro** (`household_user.reminders_email`, default `false`): preferencia personal gestionada en `/recordatorios`, nunca del hogar ni global. Todo lo demás de [ADR-0015](#adr-0015) sigue en pie: **jamás marketing**, jamás novedades de producto.
3. **Idempotencia por pivote**: `last_reminder_digest_at` guarda el último envío y la corrida respeta "ya recibió hoy" — reintentos del cron o doble corrida no duplican. Un envío que falla no marca el pivote, así que se reintenta al día siguiente.
4. **Envío síncrono dentro del comando (Fase 1).** Sin colas: con Brevo free (300 correos/día) y una base pequeña de usuarios, la corrida de madrugada sobra — y como corre en el **proceso del cron**, nunca añade latencia a la app (el cuello real con volumen es el límite de duración del proceso en hosting compartido y la cuota del proveedor, no el HTTP). **La Fase 2, cuando los opt-in con urgentes rocen ~200–250/día, es un Job `SendReminderDigest` por destinatario**, despachado desde el comando y procesado por el `queue:work --stop-when-empty` de DEPLOYMENT §6 (cola `database`, [ADR-0008](#adr-0008)). El Job marca `last_reminder_digest_at` **después** de enviar de verdad. Ojo: encolar el Mailable directo (`ShouldQueue`) no vale — marcaría el pivote al despachar y un worker caído dejaría el día "enviado" sin correo. Ambos eventos del Scheduler llevan `withoutOverlapping()` para que una corrida larga no se solape con la del minuto siguiente.
5. **Brevo por SMTP puro, cero código de proveedor.** `config/mail.php` es el estándar de Laravel: Brevo son cuatro variables de `.env` (`smtp-relay.brevo.com:587`, ver DEPLOYMENT §4). Cambiar de proveedor es cambiar `.env`, no código. Nada de SDK ni API propia del proveedor.
6. **Enmienda puntual y explícita a [ADR-0015](#adr-0015)**: el digest es el tercer correo, y este ADR es la justificación que su punto 7 exigía. La razón de peso: la app solo puede avisar cuando el usuario la abre; una obligación vencida que nadie vio es justo el caso donde el correo aporta valor real. En transporte de datos es más pobre que la app a propósito: solo título, fecha y monto de cada urgente — **nunca** saldos, nombres de cuentas ni historial.
7. **Push: Web Push nativo (VAPID) queda documentado para la Épica 10.** El estándar W3C no necesita proveedor ni tiene cuota — gratis de verdad, sin SDK — pero exige Service Worker + HTTPS, que llegan con la PWA de la Épica 10. Consumirá `ReminderService::list()/summary()` tal cual (seam del punto 6 de [ADR-0027](#adr-0027)).
8. **Baja de un click desde el propio correo (RFC 8058).** El pie del digest trae un enlace "Ya no quiero recibir este resumen" a una **URL firmada por usuario+hogar** (validez 60 días), válida **sin sesión** — el click llega desde el buzón, no desde la app; la firma es la autorización. GET muestra confirmación; POST al mismo URL ejecuta la baja directa (el botón nativo "Cancelar suscripción" de Gmail/Yahoo, vía cabeceras `List-Unsubscribe`/`List-Unsubscribe-Post`). Es la válvula de escape barata: convierte un "reportar spam" (caro para la reputación del dominio) en una baja (gratis), y es la postura correcta ante la Ley 1581. Idempotente, por hogar (la baja del digest de A no toca el de B), y **no aplica** a invitaciones ni recuperación — son transaccionales de un solo envío.

**Alternativas (descartadas).**
- **Cola con `queue:work`** — no hay workers en hosting compartido; improvisar un worker con cron por minuto es frágil y duplica estados. La cola `database` queda como seam, no como dependencia.
- **API de Brevo con su SDK** — acopla código al proveedor y no aporta nada sobre SMTP para 300 correos al día.
- **OneSignal u otro push "gratis"** — gratis con condiciones (límites, branding), SDK de tercero, y el proveedor ve los payloads; para datos financieros, VAPID nativo sin intermediarios.
- **Push ya, sin PWA** — sin Service Worker no hay Web Push; montarlo fuera de la Épica 10 sería construir la PWA a destiempo.
- **Digest aunque no haya urgentes** ("resumen diario de que no debes nada") — un correo diario que dice "nada" se ignora a la tercera vez y entrena al usuario para borrar los de verdad importantes.

**Consecuencias y mitigaciones.**
- 300 correos/día de cuota sobran para el MVP; si el volumen crece, la Fase 2 (cola) reparte el envío y/o se sube de plan — el código no cambia.
- **La Fase 2 no se activa antes de tiempo a propósito**: exige la línea del worker en el cron de producción, y sin ella los digests fallarían **en silencio** (la misma razón por la que [ADR-0015](#adr-0015) no encola las invitaciones). Umbral de activación: ~200–250 opt-in con urgentes por día, donde la cuota free de Brevo se cruza antes que el límite de duración del proceso del cron.
- La corrida itera destinatarios con `try/catch`: un buzón que rebota registra `Log::warning` y no frena al resto (patrón de `inviteMember`, ADR-0015).
- El correo reafirma que **no apaga nada**: "un aviso se apaga pagando" ([ADR-0027](#adr-0027)) — el digest es un dedo que trae de vuelta, la app sigue siendo la verdad.
- Tests con `Mail::fake` (opt-in, silencio sin urgentes, hogar desactivado, no duplicación) más un render real del Mailable HTML, que caza errores de Blade que `assertSent` no toca.
- La preferencia y la fecha de último envío viven en el pivote `household_user` con `withPivot`/casts — un usuario multi-hogar opta y recibe por separado en cada hogar (máximo uno por hogar al día).

**Estado.** ACEPTADA — 2026-08-30, a petición del usuario (Brevo free; push diferido a la Épica 10). Implementada en `SendReminderDigests`, `ReminderDigest` + `emails/reminders/digest*`, preferencias en `household_user`, `UpdateReminderEmailRequest`, `ReminderController::email()/unsubscribe()`, la vista `reminders/unsubscribe`, el bloque "Resumen por correo" de `/recordatorios`, la ruta firmada `recordatorios/correo/baja` y el schedule 06:30 de `routes/console.php`.

---

## ADR-0029
### Verificación de correo obligatoria en el registro con reclaim anti-squatting — **ACEPTADA**

**Contexto.** El registro creaba la cuenta y entraba de inmediato sin verificar correo alguno. Con la Épica 9 esto dejó de ser teórico: **enviamos correos diarios** (digest, [ADR-0028](#adr-0028)) a la dirección que el usuario tecleó — que puede ser de otra persona. Lo mismo la recuperación de contraseña: cualquiera puede registrar `correo@ajeno.com` y hacer que esa persona reciba correo de Finlia (reputación del dominio + Ley 1581). Decisión del producto: **registrar todo y validar el correo después**, bloqueando la app hasta la confirmación.

**Decisión.**

1. **Flujo nativo `MustVerifyEmail`**: el registro crea usuario + hogar (como siempre) pero no entra al dashboard; redirige a un aviso "Revisa tu correo" con el enlace ya enviado. El middleware `verified` cubre **todo** el grupo privado — sin confirmar solo son alcanzables logout, el aviso y el reenvío. Corolario estructural: **un usuario sin verificar no puede crear ningún dato** (la única fila suya es el hogar vacío auto-creado).
2. **Correo propio en español** (`VerifyEmailMail`, HTML+texto, patrón ADR-0015) con enlace **firmado a 60 min** (`auth.verification.expire`). El enlace es **público + firmado** (patrón de la baja del digest): la firma es la autorización y el click llega desde el buzón, con o sin sesión. El hash (sha1 del correo) es la otra mitad de la prueba, verificada en controlador.
3. **Reenvío con throttle por usuario** (`RateLimiter::for('verification')`, 3/min por id de usuario, no por IP) desde el aviso, con la pista de "revisa spam". Es correo transaccional imprescindible bajo ADR-0015: el destinatario no puede ver el aviso dentro de la app porque **no puede entrar**.
4. **Anti-squatting (reclaim en el registro)**: si el correo existe con `email_verified_at` NULL, el registro **borra el fantasma** (usuario + hogar vacío, en transacción, FKs en cascada) y crea la cuenta nueva. El dueño real del correo nunca ve "ya está registrado": `Rule::unique` solo cuenta correos **verificados**. Por construcción el fantasma es inerte (punto 1) y el squatting jamás completa (el enlace siempre cae en la bandeja del dueño real). "Olvidé mi contraseña" queda como vía de recuperación universal; una cuenta **verificada** con ese correo prueba control de la bandeja y queda fuera del reclaim.
5. **Digest defensivo**: `finlia:send-reminder-digests` excluye miembros con correo sin verificar (cinturón y suspenderes junto al opt-in de ADR-0028).
6. **Usuarios existentes** quedan verificados de oficio por migración de datos (base de desarrollo; nadie "verificó de verdad" porque el flujo no existía). Esa migración fija la invariante del punto 4: a partir de ella, todo `email_verified_at` NULL es un registro posterior sin datos. Irreversible a propósito.
7. **Enmienda puntual a la tabla de correos de [ADR-0015](#adr-0015)/[ADR-0028](#adr-0028)**: pasa a **4 correos** (invitación, contraseña, digest, verificación). La verificación es tan transaccional e imprescindible como la recuperación: sin ella la cuenta no existe operativamente. El correo no lleva datos financieros: solo nombre y enlace. Falla en silencio con `Log::warning` sin PII (un SMTP caído no rompe el registro; el reenvío es la recuperación), y con transport falso no se envía (`mail_is_deliverable()`).

**Alternativas (descartadas).**
- **Verificar antes de crear** (correo → luego contraseña/datos): dos pasos con estado intermedio y peor UX móvil; el flujo nativo "crear y confirmar" es el estándar del mercado.
- **Notificación markdown nativa de Laravel**: en inglés y fuera del estilo de marca; el Mailable propio ya era el patrón (invitación, digest).
- **Solo avisar sin bloquear** (banner en vez de middleware): no cierra el problema — el digest y la recuperación seguirían saliendo a correos sin confirmar.
- **Purgar fantasmas solo por cron (14 días, plan 05)** como mecanismo de desbloqueo: deja al dueño real bloqueado hasta la corrida; el reclaim en el registro reduce la ventana a cero. La purga queda como higiene de fondo, no como dependencia.

**Consecuencias y mitigaciones.**
- El registro envía el correo **después** del commit de la transacción: un SMTP lento no sostiene locks, y si falla el registro ya quedó bien (el botón de reenvío es la vía).
- La sesión se regenera al confirmar dentro de sesión (fijación de sesión) y el redirect distingue "confirmado con sesión" (dashboard) de "confirmado desde otro dispositivo" (login).
- Race menor aceptada: dos registros simultáneos sobre el mismo correo fantasma chocan en el `unique` de DB para el segundo — indistinguible de un correo tomado y ya cubierto por el throttle del registro.
- Los correos existentes de invitación a un fantasma no se tocan: quien acepta debe tener sesión verificada, y el fantasma no puede tenerla.
- Cambio de correo (re-verificación del correo nuevo) queda fuera de este ADR: es el plan 02 de la serie de cuentas, junto al perfil.

**Estado.** ACEPTADA — 2026-08-30 (Plan 01 de `planes/`). Implementada en `User` (implements `MustVerifyEmail` + envío propio), `VerifyEmailMail` + `emails/auth/verify-email*`, `EmailVerificationController`, la vista `auth/verify-email`, el grupo `verified` de `routes/web.php`, el reclaim de `RegisteredUserController` + `StoreRegistrationRequest`, la exclusión del digest en `SendReminderDigests`, el `RateLimiter` de `AppServiceProvider`, `config/auth.php` (`verification.expire`) y la migración `2026_09_03_000001`.

---

## ADR-0030
### Perfil propio: cambio de contraseña con revocación de sesiones y cambio de correo con doble confirmación — **ACEPTADA**

**Contexto.** No existía pantalla de perfil: la única vía de rotar la contraseña era "olvidé mi contraseña" (que exige salir de la sesión), y el correo era **inmutable** para siempre. Con [ADR-0029](#adr-0029) la inmutabilidad dejó de ser anecdótica: el correo **verificado** es la llave de la cuenta (digest, recuperación, avisos de seguridad). Cambiarlo "a lo bruto" habría roto ese invariante — un correo nuevo sin probar habría pasado a recibir datos y poder de recuperación de la cuenta.

**Decisión.**

1. **Pantalla `/perfil`** (propia del usuario, fuera del multi-tenant de finanzas — nada de `household_id`): nombre, contraseña y correo con su estado. El plan 04 añadirá nacimiento/región/género sobre la misma pantalla. Enrutada sin `{user}` a propósito: **solo existe el autenticado** (`UserPolicy::update` = `$user->is($target)`), el aislamiento es estructural.
2. **Contraseña con re-autenticación**: regla `current_password` (probar la identidad antes de rotar la llave) + `Auth::logoutOtherDevices()`. En Laravel 11+ eso re-hashea la contraseña; quien cierra las demás sesiones es el middleware `AuthenticateSession` (registrado en el grupo `web`), que compara el hash en **cada** sesión y cookie de "recuérdame" (el recaller lleva el hash firmado). La sesión actual sobrevive — el middleware re-almacena el hash nuevo dentro de la misma request. Corolario deseable: **la recuperación por correo también revoca todas las sesiones** por construcción (re-hashea), que es exactamente lo que quiere una víctima de sesión robada. Aviso al propio correo ("si no fuiste tú → recuperar contraseña").
3. **Cambio de correo con doble confirmación**: el correo nuevo vive en `users.pending_email` (+ `pending_email_token` = **sha256** del token público, patrón de `household_invitations` — un volcado no revela enlaces; `pending_email_requested_at`, TTL 60 min). La confirmación es **GET público `confirmar-correo/{token}`** (throttle 6/min): poseer el token equivale a controlar la bandeja nueva, mismo criterio que `verification.verify`. Al confirmar, swap transaccional: `email = pending`, `email_verified_at = now()` (**verificado por construcción**), limpieza de pendientes, y **aviso al correo ANTIGUO** (la pierna antifraude: si alguien robó la sesión y movió la cuenta, el dueño real se entera donde siempre recibió).
4. **Invariante preservado**: ningún correo entra a `users.email` sin bandeja verificada — por el registro ([ADR-0029](#adr-0029)) o por esta confirmación. Hogares, preferencias (p. ej. el opt-in del digest) y sesión siguen intactos: consultan el `email` vigente, nada que migrar.
5. **Anti-squatting coherente con ADR-0029**: la validación solo rechaza correos **verificados** de otros o **pendientes** de otros; al confirmar, un fantasma sin verificar con ese correo se reclama (borra, igual que el registro — es inerte por construcción) y un conflicto con cuenta **verificada** se rechaza limpio con limpieza del pendiente. Repetir el propio pendiente regenera el enlace (el hash anterior muere).
6. **Enmienda a la tabla de correos de [ADR-0015](#adr-0015)/[ADR-0029](#adr-0029)**: pasa a **7 correos** (+ aviso de contraseña, confirmación al correo nuevo, aviso al correo antiguo). Los tres son transaccionales de seguridad — el aviso de fraude es información que el destinatario no puede ver dentro de la app porque quizá ni siquiera es él quien está dentro. Sin datos financieros; fallan en silencio (`Log::warning` sin PII) porque la acción ya quedó hecha y no llevan enlace con secreto.

**Alternativas (descartadas).**
- **Cambiar el correo directo, sin confirmación** — rompe el invariante (punto 4): el digest y la recuperación saldrían a una bandeja sin probar, el problema exacto que ADR-0029 cerró.
- **Reutilizar el enlace firmado de verificación** — la firma ata `id+hash(email)` al usuario, no al intento de cambio; un token de BD por solicitud (regenerable, revocable al repetir) es el mecanismo correcto y ya existía (`household_invitations`).
- **Pantalla intermedia con POST en la confirmación** — un GET que muta aquí es seguro: el secreto es el token de la URL, no la sesión; igual que `verification.verify` y `password.reset` en su momento.
- **Purgar sesiones a mano** (`DELETE FROM sessions WHERE user_id = ?`) — acopla al driver y duplica lo que el framework resuelve; se optó por el mecanismo nativo (`AuthenticateSession`).
- **Avisar al correo antiguo ANTES de confirmar** — ruido para el caso feliz (99 %); el aviso posterior es el que sirve al dueño real: llega con el cambio ya hecho y la instrucción de reaccionar.

**Consecuencias y mitigaciones.**
- `AuthenticateSession` corre en **toda** request autenticada: un `hash_equals` por request, despreciable frente a la garantía de revocación.
- El middleware también store/valida el hash en la cookie de "recuérdame" (`viaRemember`): un dispositivo recordado con hash viejo muere, no resucita.
- Carrera menor heredada de ADR-0029: dos confirmaciones simultáneas sobre el mismo correo chocan en el `unique` de `users.email`; cubierta por el throttle de la ruta.
- `pending_email` **no** lleva índice unique a propósito: un pendiente no es propiedad — el primero que confirma gana el correo y el segundo recibe el rechazo honesto (conflicto verificado).
- El test de revocación simula "la otra sesión" sembrando `password_hash_web` con el hash anterior — exactamente el estado en que ese middleware deja toda sesión viva.

**Estado.** ACEPTADA — 2026-08-30 (Plan 02 de `planes/`). Implementada en `ProfileController` + `ProfileService`, `UserPolicy`, los Requests de `App\Http\Requests\Profile`, `PasswordChangedMail`/`ConfirmEmailChangeMail`/`EmailChangedNoticeMail` + `emails/profile/*`, las vistas `profile/edit` y `profile/email-error`, las rutas `perfil*` y `confirmar-correo/{token}` de `routes/web.php`, `AuthenticateSession` en `bootstrap/app.php`, `User` (cast + `pending_email_token` oculto) y la migración `2026_09_04_000001`.

---

## ADR-0031
### Términos y condiciones versionados con re-aceptación obligatoria — **ACEPTADA**

**Contexto.** Finlia maneja **datos financieros familiares** y no existía nada de términos. Requisitos del dueño: aceptación de entrada, re-aceptación obligatoria cuando cambien, y salida honesta para quien no quiera aceptar. Además, con la Ley 1581 de 2012 el registro de consentimiento con **versión exacta, fecha e IP** es la prueba ante un reclamo o auditoría — no basta un booleano `accepted_terms` que se sobreescribe.

**Decisión.**

1. **Versiones inmutables**: tabla `terms_versions` (`version` única legible tipo `2026-09-v1`, `title`, `content` longText, `change_summary` opcional, `published_at`). Una fila nunca se edita ni se borra: **cambiar los términos es publicar una versión nueva**. La vigente es la `published_at` más reciente (`TermsVersion::current()`); `/terminos` la muestra públicamente y `/terminos/{version}` el histórico — la referencia externa de qué aceptó cada usuario.
2. **Consentimiento versionado**: `user_terms_acceptances` (`user_id`, `terms_version_id`, `accepted_at`, `ip_address` nullable) con `unique (user_id, terms_version_id)` — idempotente, patrón de [ADR-0024](#adr-0024), pero **tabla aparte**: los avisos UX son por clave de enum y mutables; los términos exigen contenido histórico inmutable y valor probatorio. El `restrictOnDelete` de la FK **impide en base de datos** borrar una versión aceptada: el historial de consentimiento es intocable por diseño (hay test que lo verifica).
3. **IP guardada** (⚠ decisión del plan resuelta afirmativamente): es dato personal, pero es la prueba estándar del consentimiento ("quién, qué versión, cuándo, desde dónde"). Nullable, finalidad exclusiva de prueba de aceptación; la política de datos (plan 06) debe documentarla.
4. **Middleware `terms.current`** en el grupo nivel 3 (`auth` → `verified` → términos): sin aceptación de la vigente, **toda** la app redirige a `/terminos/aceptar`. Un solo mecanismo cubre el registro nuevo y el cambio de términos. **Fail-open**: sin versión publicada no hay obligación y la app se usa normal — así el mecanismo no rompe entornos sin seeder (tests, instalaciones limpias).
5. **Orden de puertas**: primero correo verificado ([ADR-0029](#adr-0029)), después términos. El flujo de aceptación vive en su propio grupo `auth`+`verified` **sin** `terms.current` (si no, bucle de redirección).
6. **Sin trampas oscuras**: pantalla de aceptación con el texto completo en scroll (no un enlace externo) y dos salidas equivalentes en visibilidad — **Aceptar y continuar** registra (versión, fecha, IP) y sigue; **No aceptar** lleva a una pantalla honesta que **no destruye nada** (ni cuenta, ni datos, ni sesión: rechazar jamás borra por sí solo). Las acciones de exportar/eliminar (planes 05/06) enlazarán desde esa pantalla cuando existan.
7. **El texto legal no lo redacta el agente**: el seeder publica `2026-09-v1` como **BORRADOR** con marcadores; la redacción definitiva es del dueño (idealmente con abogado) y se publica como versión nueva, nunca editando la existente.

**Alternativas (descartadas).**
- **Columna `terms_accepted_at` en `users`** — no registra qué versión, se sobreescribe con cada aceptación y destruye la prueba anterior; insuficiente para Ley 1581.
- **Reutilizar `user_acknowledgements`** ([ADR-0024](#adr-0024)) — por clave de enum sin contenido histórico: no puede referenciar "el texto exacto que aceptaste" ni impedir su borrado.
- **Checkbox de términos en el registro** — no resuelve el cambio de términos (el requisito 2 del dueño) y duplica mecanismo; el middleware obligatorio cubre ambos casos en uno.
- **Cerrar sesión automáticamente al rechazar** — acción implícita sobre la cuenta del usuario; el plan exige que rechazar no tenga efectos destructivos.
- **Exigir aceptación también sin versión publicada** (fail-closed) — rompería toda instalación/entorno sin seeder y no aporta: sin texto publicado no hay nada que consentir.

**Consecuencias y mitigaciones.**
- El middleware añade 2 queries indexadas por request privada (`current()` + `exists`); despreciable frente a la garantía de bloqueo total.
- Publicar una versión "por error" deja a todos los usuarios fuera de la app hasta re-aceptar — es exactamente el requisito del dueño ("full importante"), pero hace de la publicación una acción deliberada: pre-MVP se hace por seeder/registro manual documentado, sin panel.
- La URL pública de la histórica usa el slug `version` (no el id): estable y compartible; el patrón `YYYY-MM-vN` con `where()` impide colisiones con las URIs fijas.
- `user_id` no es fillable en `UserTermsAcceptance` (viene del autenticado); `terms_version_id` sí lo es porque viene de `TermsVersion::current()` en servidor — nunca de la petición.
- Los usuarios demo del seeder traen aceptación prefabricada (consentimiento falso, como el resto de sus datos) para que el login local y los e2e no caigan en la pantalla de aceptación.

**Estado.** ACEPTADA — 2026-08-30 (Plan 03 de `planes/`). Implementada en las migraciones `2026_09_04_000002/3`, `TermsVersion` + `UserTermsAcceptance`, `User::acceptTerms()/hasAcceptedCurrentTerms()`, `EnsureTermsAccepted` (alias `terms.current`) en `bootstrap/app.php`, `TermsController`, las vistas `terms/{accept,show,exit}`, las rutas `terminos*` de `routes/web.php`, `TermsVersionSeeder` (+ aceptaciones demo en `DatabaseSeeder`) y `TermsTest` (14 pruebas).

1. Numera correlativo (`ADR-00NN`).
2. Marca estado: **Propuesta / PENDIENTE / ACEPTADA / Rechazada / Sustituida por ADR-00NN**.
3. Incluye: contexto, decisión, alternativas, consecuencias.
4. Si sustituye a otro, enlázalo.
