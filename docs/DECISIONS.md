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
- El correo de recuperación de contraseña sigue usando la plantilla nativa de Laravel, que se renderiza **en inglés** (no hay `lang/es.json`). Queda pendiente traducirlo para que los dos únicos correos de Finlia hablen el mismo idioma.
- `HouseholdService::inviteMember()` pasa a devolver **tres** elementos. El desestructurado de dos que usan los tests existentes sigue siendo válido en PHP, así que no hubo cambios en cascada.
- El Service llama a `Mail::` y el Mailable construye la URL con `route()`. No rompe ADR-0010: no se toca `request()`, `session()` ni `Auth::id()` — el nombre de quien invita entra como **dato explícito** desde el controlador, y `route()` es determinista a partir de `APP_URL`. Una futura API (Épica 14) reutiliza el envío sin escribir nada.
- Sin SMTP configurado (`MAIL_MAILER=log`), la app funciona exactamente como antes de esta entrega. La configuración de producción está en `docs/DEPLOYMENT.md` §4.
- Un buzón inexistente o un SMTP caído generan un `Log::warning`, nunca un error visible: el administrador ve el enlace manual y sigue adelante.

**Estado.** ACEPTADA — 2026-08-26. Implementada en `HouseholdInvitationMail`, `HouseholdService::inviteMember()`, `config/finlia.php` (`finlia.mail`) y las vistas `emails/households/invitation*`.

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

## Cómo añadir un ADR

1. Numera correlativo (`ADR-00NN`).
2. Marca estado: **Propuesta / PENDIENTE / ACEPTADA / Rechazada / Sustituida por ADR-00NN**.
3. Incluye: contexto, decisión, alternativas, consecuencias.
4. Si sustituye a otro, enlázalo.
