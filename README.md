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

## 🧪 Tests

```bash
composer test          # PHPUnit con SQLite en memoria
php artisan test --filter=HouseholdTest
```

## 📦 Despliegue

El despliegue se hace en **Hostinger** (hosting compartido). Instrucciones paso a paso, cron y optimizaciones en [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

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
- [docs/DECISIONS.md](docs/DECISIONS.md) — Decisiones (ADR)
- `scrum/epics/` — Épicas detalladas

## 📄 Licencia

MIT. Consulta el archivo [LICENSE](LICENSE).
