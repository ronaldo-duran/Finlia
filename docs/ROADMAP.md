# Roadmap de Épicas — Finami

> Las épicas se desarrollan **en orden**. Cada una depende de las anteriores. El detalle de cada una está en `scrum/epics/`.

Estado: 🔴 No iniciada · 🟡 En progreso · 🟢 Completada

| # | Épica | Estado | Depende de |
|---|---|---|---|
| 1 | Fundación y configuración del proyecto | 🟢 | — |
| 2 | Hogares, familias y miembros | 🔴 | 1 |
| 3 | Cuentas, ingresos y gastos | 🔴 | 2 |
| 4 | Presupuestos y dinero disponible | 🔴 | 3 |
| 5 | Gastos recurrentes y obligaciones futuras | 🔴 | 3, 4 |
| 6 | Deudas y tarjetas de crédito | 🔴 | 2, 3 |
| 7 | Metas de ahorro | 🔴 | 3 |
| 8 | Dashboard y reportes financieros | 🔴 | 3, 4, 5, 6, 7 |
| 9 | Recordatorios y notificaciones | 🔴 | 5, 6, 7 |
| 10 | UX mobile y PWA | 🔴 | 3 (y resto) |
| 11 | Hardening, tests y producción | 🔴 | Todas |
| 12 | Monetización y modelo SaaS | 🔴 | 2, 11 |
| 13 | Portafolio profesional | 🔴 | 11 |

## Resumen por épica

### Épica 1 — Fundación y configuración
Configurar Laravel (PHP, MySQL, `.env`, timezone Colombia, locale español, COP), Git/README, tabla `users`, autenticación (login/registro/logout/recuperación), layout responsive (navbar, sidebar móvil, footer, flash), dashboard inicial vacío. **Bootstrap 5** reemplaza a Tailwind. Tests básicos.

### Épica 2 — Hogares, familias y miembros
`households`, `household_user` (roles owner/member), `household_invitations` (token seguro, expiración, estado). Policies, selector de hogar, configuración, miembros, invitaciones. Tests de aislamiento.

### Épica 3 — Cuentas, ingresos y gastos
`accounts`, `categories` (seed global + custom por hogar), `incomes` y `expenses` en tablas separadas (ADR-0001). Botón "Registrar gasto" rápido. Dashboard del mes con Chart.js. Filtros. Seeders/factories de demo.

### Épica 4 — Presupuestos y dinero disponible
`budgets` (total + por categoría). Servicio `BudgetCalculatorService`/`AvailableMoneyService`: ingresos − fijos − recurrentes − deuda − ahorro − comprometido = disponible. Tarjeta "💰 Puedes gastar", indicadores, alertas 80%/100%.

### Épica 5 — Gastos recurrentes y obligaciones futuras
`recurring_expenses` (frecuencias semanal→anual, próxima fecha). Sección "Próximas obligaciones", alertas, cálculo de ahorro mensual necesario. Integra al cálculo de dinero disponible.

### Épica 6 — Deudas y tarjetas de crédito
`debts`, `debt_payments`, soporte de tarjetas (crédito, límite, fecha de pago). Dashboard de deuda, historial de pagos, refinanciación, proyecciones. Preparar (no implementar obligatoriamente) avalancha/bola de nieve.

### Épica 7 — Metas de ahorro
`savings_goals` + `savings_goal_contributions`. Aportes/retiros, progreso, aporte mensual recomendado, marca `emergency_fund`.

### Épica 8 — Dashboard y reportes
Resumen (ingresos, gastos, balance, disponible, deuda, ahorro, metas), 5 gráficos Chart.js, comparación de períodos, insights sencillos, export CSV (preparar PDF).

### Épica 9 — Recordatorios y notificaciones
`reminders` para recurrentes, deudas, metas, obligaciones anuales. Estados, in-app inicial (preparar email/WhatsApp/push). Laravel Scheduler + cron Hostinger.

### Épica 10 — UX mobile y PWA
Navbar/botones/forms/tablas/gráficos optimizados móvil. Botón flotante "+" (gasto, ingreso, transferencia, aporte, pago deuda). PWA (manifest, iconos, instalación). Selects inteligentes (última categoría/cuenta usada).

### Épica 11 — Hardening, tests y producción
Auditoría de seguridad completa, privacy, DB (índices, FK, DECIMAL), tests de funciones críticas, performance (N+1, paginación), producción (.env, cache, cron). README completo de instalación.

### Épica 12 — Monetización y SaaS
`plans`, `subscriptions`, features/limits. Plan gratuito útil; Premium futuro (más hogares, PDF, multi-moneda, etc.). Puntos de publicidad no invasiva. Autorización de features en backend.

### Épica 13 — Portafolio profesional
README profesional, `/docs` completa, diagramas Mermaid, limpieza de Git, demo con datos ficticios (nunca reales), sección "Why this project?".

---

## Cómo avanzar

1. Para iniciar una épica: usa `/implement-epic` indicando el número.
2. Al terminar: ejecuta `/security-checklist` y actualiza el estado en esta tabla y el `[x]` de DoD en [AGENTS.md](../AGENTS.md).
3. Commits por épica en su rama (`epica-N-...`); merge a `main` solo verde.
