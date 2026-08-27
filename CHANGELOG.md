# Changelog

Todos los cambios notables de **Finlia** se documentan en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
versionado sigue [SemVer](https://semver.org/lang/es/). La versión vigente del software
se declara en `config/finlia.php` y `package.json`, y debe coincidir con la versión más
reciente de este archivo.

> **Estado actual: desarrollo pre-MVP (sin tags).** Cada entrega mayor de
> funcionalidad publica un **minor** `0.x` y cada corrección un **patch**. El primer
> tag marcará el lanzamiento del MVP con la versión vigente de ese momento. Para
> actualizar este archivo usa la skill `/update-changelog`.

## [0.4.1] - 2026-08-26 — Invitaciones por correo

### Añadido
- **Las invitaciones a un hogar se envían por correo al invitado** (`HouseholdInvitationMail`).
  Hasta ahora el administrador tenía que copiar el enlace y mandarlo a mano, justo en el
  paso más importante del producto. El correo es Blade HTML autocontenido, en español,
  con versión en texto plano y sin imágenes remotas.
- Interruptor `finlia.mail.enabled` en `config/finlia.php` y detección de transports que
  **no entregan** (`log`, `array`): con ellos la pantalla no le promete al administrador un
  correo que nadie va a recibir.
- Configuración SMTP de producción documentada en `docs/DEPLOYMENT.md` §4 (Hostinger, SPF/DKIM).
- Tests: 7 de feature con `Mail::fake()` — envío al invitado, enlace con el token plano en el
  cuerpo, asunto, interruptor apagado, transport falso, caída del SMTP y aviso en la UI.

### Cambiado
- **Política de correo escrita y acotada ([ADR-0015](docs/DECISIONS.md#adr-0015))**: Finlia envía
  correo **solo cuando el destinatario no puede ver el mensaje dentro de la app** — invitación a un
  hogar y recuperación de contraseña. Recordatorios (Épica 9), resúmenes y comunicaciones de
  producto van **in-app**. Ningún correo transporta datos financieros. Añadir un correo nuevo exige
  un ADR. Se corrigen `docs/ARCHITECTURE.md` §7 y la Épica 9 de `docs/ROADMAP.md`, que dejaban la
  puerta abierta a notificar por email.
- `HouseholdService::inviteMember()` devuelve un tercer elemento (`bool $emailSent`) y acepta el
  nombre de quien invita como dato explícito, sin romper el seam de ADR-0010.
- La pantalla del hogar distingue "invitación **enviada**" de "invitación **creada**": el enlace
  manual sigue siempre visible como respaldo.

### Seguridad
- El envío nunca bloquea la operación: un SMTP caído deja la invitación creada y registra un
  `Log::warning` **sin token ni enlace** (`docs/SECURITY.md` §4).

## [0.4.0] - 2026-08-18 — Presupuestos y dinero disponible

### Añadido
- **`BudgetCalculatorService`**: responde "¿cuánto puedo gastar?" separando cuatro
  conceptos que no se mezclan — *balance actual*, *comprometido*, *disponible* y
  *libre* (ADR-0014). Sin dependencias HTTP, reutilizable por la futura API
  (ADR-0010).
- **Presupuestos** (`budgets`): total del mes y/o por categoría, con CRUD, unicidad por
  hogar/categoría/mes y `DECIMAL(15,2)` (ADR-0006). El comprometido toma el **mayor**
  entre el total y la suma de categorías para no contar dos veces.
- **Ingresos esperados** (`expected_incomes`): el usuario configura sus ingresos
  mensuales fijos (salario, arriendos, inversiones) con monto, día de cobro y estado
  activo. Son la entrada del cálculo; si no hay ninguno configurado se degrada a los
  ingresos ya registrados.
- Tarjeta **"💰 Puedes gastar aproximadamente"** en el panel de presupuestos y en el
  dashboard, con reparto diario y días restantes del período.
- Consulta por **esta semana / este mes / próximo mes**: los presupuestos se guardan
  mensuales y la vista semanal los **prorratea** (`BudgetScope` vs `BudgetPeriod`).
- **Alertas visuales al 80 % y al 100 %** por categoría (`BudgetAlertLevel`), con
  banners en presupuestos y dashboard, barras de progreso e indicador de **tendencia**
  (gasto proyectado a fin de período).
- Desglose opcional "¿Cómo se calcula?" que muestra la fórmula sin imponerla, marcando
  los términos que llegarán en las épicas 5-7.
- Enums `BudgetPeriod`, `BudgetScope` y `BudgetAlertLevel`; componente Blade
  `available-money-card`; helper y directiva `@percent` (formato colombiano `332,4 %`).
- Presupuestos e ingresos esperados de demo en `DatabaseSeeder` (datos siempre falsos).
- Tests: 26 unitarios de `BudgetCalculatorService` (prorrateo semanal, doble conteo,
  umbrales 80/100, próximo mes, aislamiento) y 36 de feature con CRUD, validación,
  mass assignment y aislamiento entre hogares (403).

### Corregido
- Los componentes `form-input` y `form-select` ignoraban el prop `id` (lo fijaban a
  `name`), de modo que el **modal de edición de categorías** de la Épica 3 no podía
  rellenarse y la página generaba `id` duplicados. Ahora aceptan `id` (y `step`).
- **Scroll horizontal en móvil en todas las pantallas**: `<main>` es un flex item sin
  `min-width: 0`, así que no podía encogerse por debajo del ancho intrínseco de su
  contenido (hasta 101 px de desbordamiento en el panel a 375 px). Verificado a 360,
  375, 414, 768, 1280 y 1440 px.
- Los **importes truncados** en las tarjetas KPI del panel ocultaban dígitos. Ahora usan
  tipografía fluida (`.money-figure`) en vez de `text-truncate`: en una app de finanzas
  un número a medias es peor que uno pequeño.
- La línea de categoría/cuenta/fecha de "Últimos movimientos" perdía la fecha en móvil
  por truncamiento; ahora envuelve.

### Notas
- El cálculo aún **no** descuenta gastos recurrentes, deuda ni ahorro programado: esos
  términos existen en el resultado con valor `0.0` y los rellenarán las épicas 5, 6 y 7
  sin cambiar la fórmula ni la UI (ADR-0014). El "puedes gastar" de hoy es, por tanto,
  optimista respecto al definitivo.

## [0.3.0] - 2026-08-14 — Cuentas, ingresos y gastos

### Añadido
- Cuentas financieras (efectivo, ahorros, corriente,…) con CRUD completo y **saldo
  persistido y recomputado en cada escritura** vía `AccountBalanceService` (ADR-0012).
- Ingresos y gastos en **tablas separadas** (ADR-0001), con dinero en `DECIMAL(15,2)`
  (ADR-0006), Form Requests y Policies por hogar; `MovementService` actualiza
  automáticamente el saldo de la cuenta afectada.
- Enums de dominio: `AccountType`, `CategoryType` y `PaymentMethod`.
- Categorías: catálogo global precargado (`CategorySeeder`) + categorías
  personalizadas por hogar, gestionadas en una única pantalla.
- Vista unificada **Movimientos** con filtros por cuenta, categoría, tipo y rango de
  fechas (`MovementSummaryService`).
- Dashboard del mes con Chart.js: totales de ingresos/gastos, gasto por categoría y
  tendencia mensual.
- Componente Blade `form-select` reutilizable para los formularios.
- Factories y seeders de datos demo (siempre falsos) y tests: Feature por recurso
  (incluye aislamiento entre hogares) y unitarios de `MovementSummaryService`.

## [0.2.0] - 2026-08-12 — Hogares, familias y miembros

### Añadido
- **Multi-tenancy por hogar** (ADR-0005): tablas `households`, `household_user` (roles
  `owner`/`member`) y `household_invitations`, con aislamiento por `household_id` en
  todas las consultas.
- CRUD completo de hogares con `HouseholdPolicy`.
- **Hogar personal auto-creado al registrarse** y concepto de *hogar activo* en sesión
  con selector en la barra superior (ADR-0011).
- Sistema de **invitaciones por email**: token aleatorio de 64 caracteres almacenado
  hasheado (sha256), expiración configurable, aceptación por enlace público y
  revocación por el owner (ADR-0003).
- Gestión de miembros desde la pantalla del hogar (expulsar, con reglas para el
  owner).
- `HouseholdService` con la lógica de dominio (crear, actualizar, invitar, aceptar,
  revocar, expulsar), sin dependencias HTTP para reutilizarla en la futura app móvil
  (ADR-0010).
- Helpers globales de hogar activo.
- Tests de hogares, invitaciones y hogar activo, incluyendo aislamiento entre hogares
  (403 ante manipulación de IDs).

## [0.1.0] - 2026-08-11 — Fundación y configuración

### Añadido
- Base operativa del repositorio: `CLAUDE.md`, `AGENTS.md`, documentación completa en
  `docs/` (arquitectura, modelo de datos, seguridad, despliegue, convenciones, ADR y
  roadmap) y la planificación Scrum en `scrum/epics/`.
- Agentes y skills de Claude Code para el flujo de trabajo: `implement-epic`,
  `security-checklist`, `epic-implementer`, `laravel-reviewer` y `security-auditor`.
- Autenticación completa a medida **sin Breeze** (ADR-0009): registro, inicio y cierre
  de sesión, y recuperación de contraseña, con validación en español (`lang/es/`),
  rate limiting en login/registro y Form Requests dedicados.
- Layout responsive con Bootstrap 5: navbar, sidebar móvil, footer, mensajes flash y
  componentes Blade reutilizables (`form-input`).
- Dashboard inicial y pantalla de bienvenida orientada al producto.
- Configuración del producto para Colombia (COP, timezone `America/Bogota`, locale
  `es`) centralizada en `config/finlia.php`.
- Tests PHPUnit de autenticación (registro, sesión, reset) y del dashboard.

### Cambiado
- **Tailwind 4 reemplazado por Bootstrap 5** vía Vite/npm (ADR-0004); eliminada la
  `welcome.blade.php` por defecto de Laravel.
- Renombrado del proyecto **Finami → Finlia** en configuración, README y documentación.
- Rediseño del front: estética glassmorphism, modo claro/oscuro con preferencia
  persistida y enfoque mobile-first.
- Sesiones, colas y caché con driver `database` para compatibilidad con hosting
  compartido (ADR-0008).
- Registrado el plan de **API REST para la app móvil (futura)** junto al ADR-0010: la
  lógica de dominio vive en Services sin dependencias HTTP.
