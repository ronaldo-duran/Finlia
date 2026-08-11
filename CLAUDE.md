# CLAUDE.md — Finami

> Manual operativo para Claude Code (y cualquier agente de IA) que trabaje en este repositorio.
> Lee este archivo **completo** antes de escribir una sola línea de código.

Finami es una aplicación web de **gestión de finanzas personales y familiares** dirigida al mercado colombiano. Se desarrolla por **épicas** (ver `scrum/epics/`). Este documento define cómo se trabaja; los detalles profundos viven en `docs/`.

---

## 1. Qué es Finami

Una app que ayuda a una persona o familia a responder:

> **"¿Cuánto dinero puedo gastar realmente sin comprometer mis obligaciones?"**

No es solo un registro de gastos: calcula **dinero disponible** = ingresos esperados − gastos fijos − gastos variables estimados − obligaciones de deuda − gastos recurrentes próximos − ahorro programado.

Mercado inicial: **Colombia** (COP, español, DD/MM/AAAA, timezone `America/Bogota`). La lógica **no debe acoplarse** a una sola moneda/país: diseñar para permitir multi-moneda futuro sin reescribir.

---

## 2. Stack tecnológico (obligatorio)

| Capa | Tecnología |
|---|---|
| Backend | **Laravel 13.8** · **PHP 8.3** |
| DB | **MySQL/MariaDB** (local y producción) · SQLite solo para tests |
| ORM | **Eloquent** + Migrations + Seeders + Factories |
| Frontend | **Blade** · **Bootstrap 5** · **JavaScript vanilla** · **Chart.js** |
| Auth | Laravel nativo (sesiones, no Sanctum/API) |
| Tests | **PHPUnit** (Pest opcional, no instalado) |
| Build | Vite (solo para assets; **Bootstrap reemplaza a Tailwind**) |

**Prohibido** salvo necesidad técnica explícita y justificada: React, Vue, Angular, Next, Node, Redis, Docker, WebSockets, microservicios. El despliegue es **hosting compartido (Hostinger)** → nada que requiera procesos persistentes.

> ⚠️ **Conflicto conocido**: el proyecto viene con Tailwind 4 instalado. La spec exige Bootstrap 5. El reemplazo (quitar Tailwind, añadir Bootstrap 5) se hace en la **Épica 1**. Mientras tanto, no añadir dependencias de Tailwind.

---

## 3. Regla fundamental antes de programar (siempre)

1. **Inspecciona** el proyecto y el estado actual.
2. **Identifica** qué existe y qué falta.
3. **Explica brevemente** qué vas a modificar.
4. **Implementa** respetando la arquitectura existente.
5. **Ejecuta** las pruebas disponibles (`composer test` o `php artisan test`).
6. **Verifica** migraciones (`php artisan migrate`).
7. **Verifica** rutas (`php artisan route:list`).
8. **Verifica** que no rompiste nada anterior.
9. **Resume** los cambios y cualquier decisión relevante.

Si una decisión afecta **significativamente la arquitectura**, **DETENTE** y explícala antes de continuar. No inventes credenciales, APIs ni servicios externos. El código debe quedar **realmente ejecutable**.

---

## 4. Protocolo por épica (cómo se construye)

Las épicas están en `scrum/epics/ÉPICA N — ….md`. Orden obligatorio (cada una depende de las anteriores):

1. Fundación y configuración
2. Hogares, familias y miembros
3. Cuentas, ingresos y gastos
4. Presupuestos y dinero disponible
5. Gastos recurrentes y obligaciones futuras
6. Deudas y tarjetas de crédito
7. Metas de ahorro
8. Dashboard y reportes financieros
9. Recordatorios y notificaciones
10. UX mobile y PWA
11. Hardening, tests y producción
12. Monetización y modelo SaaS
13. Portafolio profesional

**Por cada épica**: revisa el código existente → crea/actualiza migraciones → modelos y relaciones → Form Requests (validación) → Policies/Gates (autorización) → controladores → vistas responsive → seeders/factories → pruebas → actualiza docs → explica archivos modificados y decisiones. **No implementes funcionalidades de épicas futuras** aunque parezcan fáciles.

Para arrancar una épica, usa la skill `/implement-epic`. Al terminar, ejecuta `/security-checklist`.

---

## 5. Arquitectura (resumen)

Detalle completo en [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). Principios en orden de prioridad: **Simplicidad · Mantenibilidad · Seguridad · Rendimiento · Escalabilidad razonable · UX · Compatibilidad con hosting compartido · Código limpio.** No sobre-ingeniar.

- **Capas**: Rutas → Controladores (finos) → Servicios de dominio (lógica financiera, p.ej. `BudgetCalculatorService`) → Modelos Eloquent → DB.
- **Toda la lógica financiera compleja vive en clases `Service`**, nunca en controladores.
- **Multi-tenant por `household`**: cada dato financiero pertenece a un `household_id`. El aislamiento entre hogares es la **amenaza #1** del proyecto (ver seguridad).

---

## 6. Seguridad (prioridad máxima — el repo será público y luego monetizado)

Detalle en [docs/SECURITY.md](docs/SECURITY.md). Reglas no negociables:

- **Aislamiento por hogar**: cada query de datos financieros debe estar acotada al `household` del usuario autenticado. Usar **policies** + **global scopes** + Form Requests. **Nunca** confiar en IDs de URL sin autorizar. Un usuario **nunca** debe ver/editar datos de otro hogar (ni por manipulación de URL/ID).
- **Mass assignment**: definir `$fillable` (o atributo `#[Fillable]`) en cada modelo. Nunca `$guarded = []`.
- **Validación**: un Form Request por cada operación de escritura.
- **Autorización**: `$this->authorize()` o `authorizeResource()` en cada controlador; una Policy por recurso.
- **Dinero**: **siempre `DECIMAL(15,2)`**. **Nunca FLOAT** para valores monetarios.
- **XSS**: Blade `{{ }}` (auto-escape). Evitar `{!! !!}` salvo contenido seguro.
- **CSRF**: `@csrf` en todos los forms. Laravel lo trae por defecto.
- **SQL Injection**: solo Query Builder / Eloquent con bindings. Nunca `whereRaw` con input de usuario sin binding.
- **Rate limiting**: en login, registro y endpoints sensibles.
- **Secretos**: **nunca** commitear `.env`, credenciales, API keys ni datos financieros reales. Ver [docs/SECURITY.md](docs/SECURITY.md#sección-secrets).
- **Datos sensibles de tarjetas** (Épica 6): **nunca** almacenar número completo, CVV ni PIN.
- **Datos reales**: nunca subir al repo datos financieros reales de Ronaldo, Vanessa o cualquier persona. Solo datos falsos vía factories/seeders.

---

## 7. Convenciones de código (resumen)

Detalle en [docs/CONVENTIONS.md](docs/CONVENTIONS.md).

- **Identificadores**: en inglés (`accounts`, `expenses`, `BudgetCalculatorService`).
- **UI, docs y mensajes al usuario**: en **español**.
- **Comentarios**: en español, breves y solo donde aportan.
- **Tablas**: snake_case plural (`households`, `savings_goals`). FKs: `<modelo>_id`. Pivot: orden alfabético.
- **Fechas**: almacenar en DB como `date`/`datetime`; mostrar al usuario como **DD/MM/AAAA**.
- **Moneda**: COP, formato `$ 1.000.000` (punto miles, coma decimales). Centralizar formato en un helper/Blade `@money`.
- **Timestamps**: `created_at`, `updated_at` (y `deleted_at` solo si el borrado es lógico).
- **Money**: `DECIMAL(15,2)` en migraciones; cast a `decimal:2` en el modelo.
- **PHP**: tipado estricto (`declare(strict_types=1)` en clases de dominio), readonly ctor donde aporte, sin acoplamientos ocultos.

---

## 8. Comandos habituales

```bash
# Instalación local
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB MySQL en .env  (ver docs/DEPLOYMENT.md)
php artisan migrate --seed
npm install
npm run build      # o npm run dev para desarrollo

# Desarrollo
composer run dev   # arranca server + queue + pail + vite
php artisan serve

# Testing
composer test      # = php artisan test (usa SQLite :memory:)
php artisan test --filter=HouseholdTest

# Calidad
vendor/bin/pint            # formateo (Laravel Pint)
php artisan route:list
php artisan migrate:status
composer audit             # revisa vulnerabilidades de dependencias
```

> En Hostinger **no hay** `npm run dev` ni workers: se compila assets con `npm run build` y se sube `public/build`. Los jobs se ejecutan vía **cron** (`php artisan schedule:run`). Ver [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

---

## 9. Git

- Commits **pequeños y descriptivos**, en español o inglés pero consistentes.
- Estilo Conventional Commits recomendado: `feat:`, `fix:`, `test:`, `docs:`, `chore:`, `refactor:`.
- `.gitignore` ya excluye `.env`, `vendor/`, `node_modules/`, `storage/*.key`, etc. **Verifícalo** antes de commitear.
- **Nunca** commitear: contraseñas, API keys, `.env`, datos financieros reales, screenshots con datos reales.
- Ramas: `main` = estable. Trabajo en ramas tipo `epica-3-transacciones`.

---

## 10. Índice de documentación

| Documento | Para qué |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Capas, patrones, estructura de carpetas, flujos |
| [docs/DATA_MODEL.md](docs/DATA_MODEL.md) | Entidades por épica, relaciones, convención `household_id` |
| [docs/SECURITY.md](docs/SECURITY.md) | Política de seguridad completa (amenazas, controles, secrets) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Despliegue en Hostinger, cron, optimización |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Estado de las 13 épicas |
| [docs/CONVENTIONS.md](docs/CONVENTIONS.md) | Nombres, dinero, fechas, commits |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Registro de decisiones (ADR) |
| `scrum/epics/` | Definición detallada de cada épica |

---

## 11. Reglas para agentes de IA

Antes de actuar, lee también [AGENTS.md](AGENTS.md). Lo esencial:

- **Nunca** commitear `.env`, secretos ni datos reales.
- **Nunca** desbloquear funcionalidad Premium sólo en frontend (la autorización es **backend**).
- **Siempre** aislar por `household`. **Siempre** Form Request + Policy en cada escritura.
- **Siempre** `DECIMAL` para dinero.
- Si una épica dice "no implementar todavía X", **no lo implementes**.
- Si el código contradice la descripción de una tarea, **señálalo** antes de sobrescribir.
