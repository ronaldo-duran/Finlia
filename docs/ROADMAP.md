# Roadmap de Épicas — Finlia

> Las épicas se desarrollan **en orden**. Cada una depende de las anteriores. El detalle de cada una está en `scrum/epics/`.

Estado: 🔴 No iniciada · 🟡 En progreso · 🟢 Completada

| # | Épica | Estado | Depende de |
|---|---|---|---|
| 1 | Fundación y configuración del proyecto | 🟢 | — |
| 2 | Hogares, familias y miembros | 🟢 | 1 |
| 3 | Cuentas, ingresos y gastos | 🟢 | 2 |
| 4 | Presupuestos y dinero disponible | 🟢 | 3 |
| 5 | Gastos recurrentes y obligaciones futuras | 🟢 | 3, 4 |
| 6 | Deudas y tarjetas de crédito | 🟢 | 2, 3 |
| 7 | Metas de ahorro | 🟢 | 3 |
| 8 | Dashboard y reportes financieros | 🟢 | 3, 4, 5, 6, 7 |
| 9 | Recordatorios y notificaciones | 🟢 | 5, 6, 7 |
| 10 | UX mobile y PWA | 🟡 | 3 (y resto) |
| 11 | Hardening, tests y producción | 🔴 | Todas |
| 12 | Monetización y modelo SaaS | 🔴 | 2, 11 |
| 13 | Portafolio profesional | 🔴 | 11 |
| 14 | API REST para app móvil (futura) | 🔴 | 3, 11 |

## Resumen por épica

### Épica 1 — Fundación y configuración
Configurar Laravel (PHP, MySQL, `.env`, timezone Colombia, locale español, COP), Git/README, tabla `users`, autenticación (login/registro/logout/recuperación), layout responsive (navbar, sidebar móvil, footer, flash), dashboard inicial vacío. **Bootstrap 5** reemplaza a Tailwind. Tests básicos.

### Épica 2 — Hogares, familias y miembros
`households`, `household_user` (roles owner/member), `household_invitations` (token seguro, expiración, estado). Policies, selector de hogar, configuración, miembros, invitaciones. Tests de aislamiento.

### Épica 3 — Cuentas, ingresos y gastos
`accounts`, `categories` (seed global + custom por hogar), `incomes` y `expenses` en tablas separadas (ADR-0001). Botón "Registrar gasto" rápido. Dashboard del mes con Chart.js. Filtros. Seeders/factories de demo.

### Épica 4 — Presupuestos y dinero disponible
`budgets` (total + por categoría) y `expected_incomes` (ingresos mensuales fijos configurables, [ADR-0014](DECISIONS.md#adr-0014)). Servicio `BudgetCalculatorService`: ingresos esperados − gastado − comprometido = disponible; los términos de recurrentes, deuda y ahorro quedan **en cero como seams** para las épicas 5-7. Tarjeta "💰 Puedes gastar" (panel y dashboard), consulta semana/mes/próximo mes, indicadores de consumo y tendencia, alertas 80 %/100 %.

### Épica 5 — Gastos recurrentes y obligaciones futuras 🟢
`recurring_expenses` (frecuencias semanal→anual + personalizada, próxima fecha, cuenta/categoría opcionales, pausar). Sección "Próximas obligaciones" (agrupada: vencidas / esta semana / más adelante), alertas en el dashboard (ventana de 30 días), "Separa ~X al mes" (SOAT $600.000 anual → $50.000/mes) y **"Marcar pagado"** (registra el gasto y avanza la fecha, sin duplicar en el cálculo, [ADR-0018](DECISIONS.md#adr-0018)). Integra al cálculo de dinero disponible **rellenando las claves `fixed_expenses` y `recurring`** de `BudgetCalculatorService` (ver [ADR-0014](DECISIONS.md#adr-0014)) sin tocar la fórmula ni la UI. La generación automática de recordatorios queda para la Épica 9 (Scheduler).

### Épica 6 — Deudas y tarjetas de crédito ✅
`debts`, `debt_payments`, `debt_refinancings` y `credit_cards` (ligada a `accounts` con `type=credit_card`, ADR-0002). Panel de deuda con total, pago mensual comprometido y progreso; historial de pagos, refinanciación y proyección de fin de deuda (marcada siempre como **estimación**). Orden por estrategia avalancha/bola de nieve (`DebtStrategy`): la épica pedía preparar la arquitectura, no el plan de pagos.

Dos decisiones propias: el saldo es **derivado** de una línea base más los pagos ([ADR-0020](DECISIONS.md#adr-0020)) y un pago con cuenta asociada **genera el movimiento real** ([ADR-0021](DECISIONS.md#adr-0021)), que además rellena el término `debt` del dinero disponible.

> 🔒 Nunca se almacena número completo de tarjeta, CVV ni PIN: esas columnas no existen, y un test lo verifica contra el esquema.

### Épica 7 — Metas de ahorro ✅
`savings_goals` + `savings_goal_contributions` ([ADR-0025](DECISIONS.md#adr-0025)). Panel con resumen, filtro por estado (vigentes/logradas/archivadas) y alerta de metas vencidas; detalle con progreso, **aporte mensual recomendado** (estimación informativa) e historial de aportes y retiros; prioridad, marca `emergency_fund`, pausar/reactivar/completar/archivar. El ahorrado es **derivado** del historial (Σ aportes − Σ retiros) y al llegar al objetivo la meta se marca lograda sola. Los movimientos **no mueven cuentas** (la transferencia real llega en la Épica 10) y el aporte mensual programado rellena el término `savings` del dinero disponible ([ADR-0014](DECISIONS.md#adr-0014)). Tarjeta de progreso en el dashboard.

### Épica 8 — Dashboard y reportes ✅
Pantalla **`/reportes`** con los cinco gráficos Chart.js (gastos por categoría, ingresos vs gastos, evolución mensual del balance, evolución de la deuda — nueva `DebtService::balanceEvolution`, consistente con [ADR-0020](DECISIONS.md#adr-0020) — y progreso de metas), comparación de **períodos honestos** (`ReportPeriod`: mes, mes anterior, últimos 3/6, año YTD contra el mismo tramo del año previo) e **insights descriptivos** con umbrales anti-ruido. Export **CSV** en streaming (BOM UTF-8, `;`, coma decimal — Excel en español) con `throttle:10,1`; el enum `ReportFormat` es el seam del PDF Premium (Épica 12). El Panel se mantiene **ligero** (guía mobile de la propia épica): solo gana los KPIs de deuda total y ahorro en metas y el enlace "Ver reportes" ([ADR-0026](DECISIONS.md#adr-0026)). De paso se corrigió un bug latente: el JSON de los gráficos del Panel se escapaba con `&quot;` dentro de `<script>` y llevaba roto desde la Épica 3.

### Épica 9 — Recordatorios y notificaciones ✅
Recordatorios **derivados en vivo** de sus fuentes —recurrentes (`next_date`), deudas (cuota del mes, [`due_day`](DECISIONS.md#adr-0020)), metas con fecha objetivo— más avisos sueltos del usuario ("obligación anual": tecnomecánica, pasaporte…) en la tabla `reminders` ([ADR-0027](DECISIONS.md#adr-0027)): el estado se calcula contra hoy, así ningún cron puede dejarlo caducado, y un aviso **se apaga pagando**, no cerrando la notificación. Página `/recordatorios` con la lista unificada (vencidas / vencen pronto en 7 días / más adelante) y acción por origen; campanita en el navbar con el conteo urgente (con **caché corta invalidada por eventos de modelo**, para no pagar el cálculo en cada página) y banner "🔔 Tienes N obligaciones próximas" en el Panel; interruptor del hogar (solo administrador). Casilla `auto_generate` en recurrentes: el **Scheduler** (`finlia:generate-recurring-payments`, diario 06:00, cron de Hostinger) registra el gasto vencido reutilizando `markAsPaid` ([ADR-0018](DECISIONS.md#adr-0018)) — una ocurrencia por corrida, con su fecha real, sin ráfagas.

> 📧 **Digest diario opcional por correo** ([ADR-0028](DECISIONS.md#adr-0028)): un correo por hogar y miembro al día (`finlia:send-reminder-digests`, 06:30), **solo si hay urgentes** y **solo a quien lo pidió** en `/recordatorios` (opt-in por miembro; jamás marketing). Síncrono dentro del cron vía SMTP (Brevo free en producción, ver DEPLOYMENT §4) — corre en el proceso del cron, así que nunca añade latencia a la app. Con volumen (umbral ~200–250 digest diarios), la Fase 2 es un Job por destinatario + `queue:work --stop-when-empty` ([ADR-0028](DECISIONS.md#adr-0028) §4). El correo trae título/fecha/monto de las urgentes y el enlace, nunca saldos ni cuentas, y **la baja está a un click**: enlace firmado (60 días, sin sesión) + cabeceras `List-Unsubscribe` RFC 8058, el botón nativo de Gmail/Yahoo.

### Épica 10 — UX mobile y PWA
Navbar/botones/forms/tablas/gráficos optimizados móvil. Botón flotante "+" (gasto, ingreso, transferencia, aporte, pago deuda). PWA (manifest, iconos, instalación). Selects inteligentes (última categoría/cuenta usada).

> 🟡 **Parcialmente adelantada** (igual que el theming se adelantó a la Épica 1): rediseño mobile-first del Panel, Movimientos y Registrar gasto/ingreso, con barra de navegación inferior + botón flotante "+" y sistema de diseño documentado en [docs/UI_DESIGN.md](UI_DESIGN.md). Puramente visual/UX — **falta** el manifest/instalación PWA, "gasto/ingreso" desde el FAB como hoja unificada (hoy navega a `/gastos/crear` o `/ingresos/crear`), transferencia/aporte/pago de deuda en el FAB (dependen de las épicas 6-7) y selects inteligentes.

> 🔔 Aquí llega el **push de recordatorios**: Web Push nativo con VAPID (W3C, sin proveedor ni cuota — gratis de verdad, ver [ADR-0028](DECISIONS.md#adr-0028) §7). Requiere el Service Worker del PWA y HTTPS (Hostinger lo trae). Consumirá `ReminderService::list()/summary()` tal cual, sin reescribir lógica.

### Épica 11 — Hardening, tests y producción
Auditoría de seguridad completa, privacy, DB (índices, FK, DECIMAL), tests de funciones críticas, performance (N+1, paginación), producción (.env, cache, cron). README completo de instalación.

> **Deuda de performance/patrón detectada en la revisión de la Épica 8** (2026-08-29, rama `epica-8-dashboard-reportes`; las líneas son de ese momento). Ningún punto nota el usuario hoy — son de patrón, no de latencia — pero conviene cerrarlos aquí:
>
> 1. **N+1 de `category` en el detalle de cuenta** (épica 3): `AccountController::show` carga los últimos 10 ingresos/gastos sin `with('category')` y la vista accede a `->category?->name` en los bucles (hasta 20 lookups por PK). Fix: `->load(['incomes' => fn ($q) => $q->with('category')->latest('date')->take(10), ...])`.
> 2. **`monthlySeries`/`monthlyTrend`: 2 queries por mes** (`ReportService`/`MovementSummaryService`): con "Año" son ~24 `SUM` idénticos en estructura. Fix: 2 queries totales con `GROUP BY` por mes natural (`strftime('%Y-%m', date)` vale para MySQL y SQLite) mapeadas al array de puntos.
> 3. **`/reportes` calcula dos veces los mismos números**: `overview()` e `insights()` ejecutan cada uno `rangeTotals` del período y del anterior (4 sums, 2 redundantes); `expensesByCategory` del período corre dos veces (lista completa + top 5). Fix: memoizar por hogar+rango en la instancia del service, o inyectar los cálculos.
> 4. **`DebtService::committedInRange`: `exists()` por deuda y vencimiento**: N queries diminutas en cada carga de Panel/presupuestos/reportes. Fix: `with('payments')` y `contains` sobre la colección (el patrón correcto ya está en `balanceEvolution`).
> 5. **Triple query a `savings_goals`** en Panel y Reportes (`outstandingGoals` + `summary` + `committedMonthly`). Fix: que `summary()` acepte la colección ya cargada.
> 6. **`SavingsGoalService::recalculateAmount` suma en PHP**: materializa todas las filas para sumarlas. Fix: `SUM(CASE type WHEN 'deposit' THEN amount ELSE -amount END)` en SQL.
> 7. **`chartData` de la torta duplicada** entre `DashboardController` y `ReportController` (shape + fallback de color). Fix: presenter compartido o método del service.
> 8. **`percent()` reinventado en ~11 líneas repartidas en 6 vistas** (reports, savings, dashboard, accounts, debts): `str_replace('.', ',', ...)` manual — riesgo de deriva de formato. Fix: usar el helper `percent()` o directiva `@percent` junto a `@money`.
> 9. **`household_id` en `#[Fillable]` de `Income`/`Expense`** cuando `Debt`/`SavingsGoal` no lo traen (se asigna siempre desde el hogar activo). Inconsistencia de convención, no hueco activo. Fix: unificar quitándolo.
>
> Ya verificado **sin** problema en esa revisión (no re-auditar): índices cubren todas las queries calientes (`(household_id, date)`, `(household_id, category_id)`, `(household_id, status)`, etc.), dinero siempre `DECIMAL(15,2)`, FKs con `onDelete` explícito, eager loading correcto en el resto de listados, vistas sin queries en bucles y lista larga paginada.

### Épica 12 — Monetización y SaaS
`plans`, `subscriptions`, features/limits. Plan gratuito útil; Premium futuro (más hogares, PDF, multi-moneda, etc.). Puntos de publicidad no invasiva. Autorización de features en backend.

### Épica 13 — Portafolio profesional
README profesional, `/docs` completa, diagramas Mermaid, limpieza de Git, demo con datos ficticios (nunca reales), sección "Why this project?".

---

### Épica 14 — API REST para app móvil (futura)
> Sin fichero de épica todavía. Se desarrolla cuando la web esté en producción. Añade `routes/api.php` + **Sanctum** (tokens para móvil) + API Resources/Controllers que **reutilizan los mismos `app/Services/`, Form Requests y Policies** del web (ver [ADR-0010](DECISIONS.md#adr-0010)). Solo es barata si la lógica quedó bien aislada desde las épicas 2-9.

---

## Cómo avanzar

1. Para iniciar una épica: usa `/implement-epic` indicando el número.
2. Al terminar: ejecuta `/security-checklist`, actualiza el estado en esta tabla y el `[x]` de DoD en [AGENTS.md](../AGENTS.md), y registra los cambios en [CHANGELOG.md](../CHANGELOG.md) con `/update-changelog`.
3. Commits por épica en su rama (`epica-N-...`); merge a `main` solo verde.
