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
