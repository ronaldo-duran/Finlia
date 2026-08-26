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

## Cómo añadir un ADR

1. Numera correlativo (`ADR-00NN`).
2. Marca estado: **Propuesta / PENDIENTE / ACEPTADA / Rechazada / Sustituida por ADR-00NN**.
3. Incluye: contexto, decisión, alternativas, consecuencias.
4. Si sustituye a otro, enlázalo.
