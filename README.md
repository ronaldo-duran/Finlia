<div align="center">

# 💰 Finlia

**Gestión de finanzas personales y familiares**

*¿Cuánto dinero puedo gastar realmente sin comprometer mis obligaciones?*

</div>

---

Finlia es una aplicación web que ayuda a personas y familias a registrar ingresos y gastos, controlar deudas y tarjetas, crear presupuestos y metas de ahorro, y —sobre todo— **calcular cuánto dinero tienen realmente disponible** para gastar. Pensada para usarse a diario desde el celular.

> 🇨🇴 Dirigida inicialmente al mercado colombiano (COP, español). Diseñada para permitir futura expansión a otras monedas y países.

## ✨ Funcionalidades (roadmap)

- Registro rápido de ingresos y gastos (mobile-first)
- Cuentas, medios de pago y tarjetas de crédito
- Categorización de movimientos
- Presupuestos por categoría y cálculo de **dinero disponible**
- Gastos recurrentes y obligaciones futuras (SOAT, seguros, matrículas…)
- Deudas y tarjetas de crédito
- Metas de ahorro (con fondo de emergencia)
- Dashboard y reportes con gráficos
- Recordatorios de pagos próximos
- Hogares compartidos con roles e invitaciones

El estado detallado de cada funcionalidad está en [docs/ROADMAP.md](docs/ROADMAP.md).

## 🧱 Stack

- **Laravel 13.8** · **PHP 8.3**
- **MySQL/MariaDB** (SQLite para tests)
- **Blade** · **Bootstrap 5** · **JavaScript vanilla** · **Chart.js**
- **Eloquent** · Migrations · Seeders · Factories
- **PHPUnit**
- Despliegue: **Hostinger** (hosting compartido)
- UI mobile-first propia sobre Bootstrap 5 (glass, chips, barra inferior + FAB) — ver [docs/UI_DESIGN.md](docs/UI_DESIGN.md)

## 🚀 Instalación local

```bash
git clone https://github.com/<usuario>/finlia.git
cd finlia
composer install
cp .env.example .env
php artisan key:generate
```

Configura la base de datos MySQL en `.env` (ver [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) para los valores exactos y la configuración de Colombia):

```env
APP_NAME=Finlia
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FAKER_LOCALE=es_CO

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finlia
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

Luego:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Abre `http://localhost:8000`.

> 🔑 **Usuario de demostración** (creado por el seeder con datos falsos):
> correo `demo@finlia.test` · contraseña `finlia123`.

### Seeders

`--seed` ejecuta `DatabaseSeeder`, que deja la app usable de inmediato:

| Seeder | Qué crea |
|---|---|
| `CategorySeeder` | Categorías por defecto de ingreso y gasto del hogar |
| `TermsVersionSeeder` | Versión vigente de los términos (sin ella el login rebota a aceptarlos) |
| `DatabaseSeeder` | Usuario demo, su hogar y movimientos, deudas y metas de ejemplo |

Todos los datos son **falsos**, generados con Faker (`es_CO`). El repositorio
nunca contiene datos financieros reales de nadie.

Para rehacer la base desde cero: `php artisan migrate:fresh --seed`.

## 🧪 Tests

```bash
composer test          # PHPUnit con SQLite en memoria
php artisan test --filter=HouseholdTest
```

### Calidad y E2E

```bash
vendor/bin/pint                  # linter/formatter PHP (en CI: pint --test)
npm run test:e2e                 # Playwright (Chromium) contra Laravel + SQLite + seed
npm run test:e2e:ui              # ídem, con inspector visual
```

Los E2E levantan su propio servidor (`php artisan serve` en el puerto 8890) con una
BD SQLite aislada (`database/playwright.sqlite`) y el seeder de datos falsos; no
tocan tu `.env` ni tu base local. Se necesita `npx playwright install chromium` la
primera vez.

### CI (GitHub Actions)

En cada push/PR corre [.github/workflows/ci.yml](.github/workflows/ci.yml) con tres
jobs: **PHP** (Pint + PHPUnit), **Assets** (build de Vite) y **E2E** (Playwright con
Chromium, sube el reporte como artefacto si falla).

## 📦 Despliegue

El despliegue se hace en **Hostinger** (hosting compartido). Instrucciones paso a paso y optimizaciones en [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Resumen de un despliegue posterior:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build          # genera public/build (no está en git)
php artisan config:cache route:cache view:cache
```

El *document root* del dominio debe apuntar a `public/`, para que `.env`, `storage/` y `app/` queden fuera de la web.

### ⏱️ Cron

Hosting compartido no admite procesos permanentes: **todo lo periódico entra por el Scheduler**, con una sola entrada de cron (Hostinger → *Advanced → Cron Jobs*):

```cron
* * * * * cd /home/uXXXX/domains/tudominio/finlia && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Esa única línea dispara todas las tareas programadas (`routes/console.php`):

| Tarea | Cuándo | Para qué |
|---|---|---|
| `finlia:process-export-requests` | 02:00 | Genera el ZIP de datos del hogar y lo envía por correo (hora valle) |
| `finlia:purge-pending-deletions` | 05:30 | Borra definitivamente las cuentas cuyo plazo de 30 días venció |
| `finlia:generate-recurring-payments` | 06:00 | Materializa los gastos recurrentes que tocan hoy |
| `finlia:send-reminder-digests` | 06:30 | Resumen diario de obligaciones por correo |

Comprueba que está bien con `php artisan schedule:list`.

## 🛠️ Troubleshooting

| Síntoma | Causa habitual | Solución |
|---|---|---|
| `419 Page Expired` al enviar un formulario | Falta `@csrf`, o la sesión caducó | Añade `@csrf` al formulario. `CsrfTokenSweepTest` detecta el caso — la suite no lo pilla sola porque Laravel desactiva CSRF en tests |
| `500` tras desplegar, con la web en blanco | Caché de config apuntando a valores viejos | `php artisan config:clear` y vuelve a cachear |
| Los estilos no cargan en producción | Falta `public/build` (está en `.gitignore`) | `npm ci && npm run build` y sube la carpeta |
| `SQLSTATE[HY000] [1045]` al migrar | Credenciales de BD incorrectas en `.env` | Revisa `DB_USERNAME` / `DB_PASSWORD` |
| El login rebota siempre a «aceptar términos» | Falta la versión vigente de términos | `php artisan db:seed --class=TermsVersionSeeder` |
| Las invitaciones no llegan por correo | `MAIL_MAILER=log` (no entrega a bandejas) | Configura SMTP; mientras tanto la app ofrece el enlace manual |
| Las tareas programadas no corren | El cron de `schedule:run` no está puesto | Añade la línea de la sección anterior |
| `Permission denied` en `storage/` | Permisos tras subir por FTP | `chmod -R 775 storage bootstrap/cache` |

## 🔒 Seguridad

Este proyecto maneja **información financiera sensible** y es un repositorio **público**. La política de seguridad completa está en [docs/SECURITY.md](docs/SECURITY.md). Lo esencial:

- Aislamiento estricto por hogar (multi-tenant).
- `DECIMAL` para dinero (nunca `FLOAT`).
- Policies + Form Requests en cada operación.
- Nunca se commitean `.env`, credenciales ni datos reales.

Para reportar una vulnerabilidad, abre un issue privado o contacta al maintainer. **No abras un issue público** con detalles explotables.

## 📚 Documentación

- [CLAUDE.md](CLAUDE.md) — Manual operativo para IA
- [AGENTS.md](AGENTS.md) — Reglas de agentes
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — Arquitectura
- [docs/DATA_MODEL.md](docs/DATA_MODEL.md) — Modelo de datos
- [docs/SECURITY.md](docs/SECURITY.md) — Seguridad
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — Despliegue
- [docs/ROADMAP.md](docs/ROADMAP.md) — Roadmap de épicas
- [docs/CONVENTIONS.md](docs/CONVENTIONS.md) — Convenciones
- [docs/UI_DESIGN.md](docs/UI_DESIGN.md) — Sistema de diseño (UI mobile-first)
- [docs/BRAND.md](docs/BRAND.md) — Identidad de marca (símbolo, logo, paleta)
- [docs/DECISIONS.md](docs/DECISIONS.md) — Decisiones (ADR)
- `scrum/epics/` — Épicas detalladas

## 📄 Licencia

MIT. Consulta el archivo [LICENSE](LICENSE).
