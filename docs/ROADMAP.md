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
| 9 | Recordatorios y notificaciones | 🔴 | 5, 6, 7 |
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

### Épica 9 — Recordatorios y notificaciones
`reminders` para recurrentes, deudas, metas, obligaciones anuales. Estados, **in-app** (WhatsApp/push como canales futuros). Laravel Scheduler + cron Hostinger.

> 📧 **Los recordatorios NO van por correo.** El correo queda reservado a lo imprescindible —invitar a alguien al hogar y recuperar la contraseña— porque ahí el destinatario no puede ver el aviso dentro de la app. Ver [ADR-0015](DECISIONS.md#adr-0015).

### Épica 10 — UX mobile y PWA
Navbar/botones/forms/tablas/gráficos optimizados móvil. Botón flotante "+" (gasto, ingreso, transferencia, aporte, pago deuda). PWA (manifest, iconos, instalación). Selects inteligentes (última categoría/cuenta usada).

> 🟡 **Parcialmente adelantada** (igual que el theming se adelantó a la Épica 1): rediseño mobile-first del Panel, Movimientos y Registrar gasto/ingreso, con barra de navegación inferior + botón flotante "+" y sistema de diseño documentado en [docs/UI_DESIGN.md](UI_DESIGN.md). Puramente visual/UX — **falta** el manifest/instalación PWA, "gasto/ingreso" desde el FAB como hoja unificada (hoy navega a `/gastos/crear` o `/ingresos/crear`), transferencia/aporte/pago de deuda en el FAB (dependen de las épicas 6-7) y selects inteligentes.

### Épica 11 — Hardening, tests y producción
Auditoría de seguridad completa, privacy, DB (índices, FK, DECIMAL), tests de funciones críticas, performance (N+1, paginación), producción (.env, cache, cron). README completo de instalación.

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
