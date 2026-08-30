# Arquitectura — Finlia

> Cómo está organizada la aplicación y por qué. Lee esto antes de añadir nada nuevo.

## 1. Objetivos de diseño (en orden de prioridad)

1. **Simplicidad** — fácil de entender para un equipo pequeño.
2. **Mantenibilidad** — código legible, predecible, sin sorpresas.
3. **Seguridad** — sobre todo el aislamiento entre hogares.
4. **Rendimiento** — sin N+1, con índices y paginación.
5. **Escalabilidad razonable** — cientos/miles de hogares, no millones (al principio).
6. **UX** — mobile-first, registrar un gasto en segundos.
7. **Compatibilidad con hosting compartido** — sin procesos persistentes obligatorios.
8. **Código limpio** — convenciones Laravel, sin excentricidades.

**No** over-ingenierías: sin microservicios, sin colas complejas innecesarias, sin abstracciones "por si acaso".

## 2. Capas

```
HTTP Request
   │
   ▼
Routes (routes/web.php)            ← rutas nombradas, agrupadas, con middleware
   │
   ▼
Form Request (validation + auth)   ← valida entrada; autoriza recurso
   │
   ▼
Controller (App\Http\Controllers)  ← FINO: orquesta, no tiene lógica de negocio
   │
   ▼
Service (App\Services)             ← LÓGICA DE DOMINIO (cálculos financieros)
   │
   ▼
Model (App\Models) + Eloquent      ← datos, relaciones, scopes
   │
   ▼
Database (MySQL)
```

### Regla de ubicación

| Tipo de lógica | Dónde vive |
|---|---|
| Validación de entrada | `app/Http/Requests/*Request.php` |
| Autorización | `app/Policies/*Policy.php` + `$this->authorize()` |
| Orquestación (qué se hace) | Controladores (`app/Http/Controllers`) |
| Lógica de negocio / cálculos | Servicios (`app/Services`, p.ej. `BudgetCalculatorService`) |
| Reglas de formato/dominio reutilizables | `app/Support/` (helpers, formatters) |
| Acceso a datos y relaciones | Modelos (`app/Models`) |
| Consultas complejas reutilizables | Query Scopes en el modelo o `app/QueryBuilder` |

**Los controladores nunca contienen cálculos financieros.** Si necesitas sumar ingresos, restar obligaciones o calcular dinero disponible → va en un `Service`, con sus tests unitarios.

## 3. Estructura de carpetas (objetivo)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── Auth/               (Épica 1)
│   │   ├── HouseholdController.php
│   │   ├── AccountController.php
│   │   ├── ExpenseController.php
│   │   ├── IncomeController.php
│   │   └── ...
│   └── Requests/               ← un Request por cada store/update
├── Models/                     ← Eloquent
├── Policies/                   ← una Policy por recurso perteneciente a un hogar
├── Services/                   ← lógica de dominio
│   ├── BudgetCalculatorService.php
│   ├── AvailableMoneyService.php
│   └── ...
├── Support/                    ← helpers, MoneyFormatter, etc.
├── Enums/                      ← tipos (AccountType, CategoryType, DebtType, Frequency...)
└── Providers/
database/
├── migrations/
├── seeders/                    ← DatabaseSeeder orquesta todos
└── factories/                  ← una factory por modelo
resources/
├── views/
│   ├── layouts/                ← app.blade.php, auth.blade.php
│   ├── components/             ← componentes Blade reutilizables
│   ├── dashboard/
│   ├── expenses/
│   ├── incomes/
│   └── ...
├── js/                         ← vanilla + Chart.js
└── css/                        ← Bootstrap 5 (Épica 1)
routes/
├── web.php
└── console.php                 ← comandos scheduler (Épica 9)
```

## 4. Multi-tenancy por hogar (patrón central)

Finlia **no** es un SaaS multi-tenant por base de datos ni por dominio. Es **multi-tenant por fila**: todas las tablas financieras tienen `household_id` y el aislamiento se hace en la aplicación.

### Mecanismo de aislamiento (3 capas, defensa en profundidad)

1. **Contexto activo**: el usuario tiene un `household` activo en sesión (o su hogar por defecto). Un helper/middleware lo resuelve (`activeHousehold()`).
2. **Relaciones en controlador**: nunca `Expense::find($id)` (ni `Income::find`). Siempre `$household->expenses()->findOrFail($id)` o route-model-binding con scope.
3. **Policies**: cada Policy verifica `$household->id === $resource->household_id` y el rol del miembro.
4. *(Opcional)* **Global scope**: para modelos muy sensibles, un `HouseholdScope` que filtra automáticamente por el hogar del contexto.

> Ver [docs/SECURITY.md](SECURITY.md#aislamiento-multi-hogar) para el detalle de controles y los tests obligatorios.

## 5. Manejo de dinero

- Tipo de columna: `DECIMAL(15,2)`. Soporta hasta 999.999.999.999.999,99 (suficiente para COP).
- Cast en modelo: `'amount' => 'decimal:2'`.
- Para operaciones en PHP, trabajar con strings/BCMATH o enteros de centavos cuando la precisión sea crítica; para sumas de reportes, `DECIMAL` agregado en SQL es aceptable.
- Formato de visualización COP: `$ 1.000.000` → centralizar en `App\Support\MoneyFormatter` y un Blade component `@money($value)`.

Ver [docs/CONVENTIONS.md](CONVENTIONS.md#dinero).

## 6. Autenticación y sesión

- Laravel nativo (sesiones + cookies). **No** API tokens / Sanctum salvo necesidad futura.
- Sesiones en `database` (compatible con Hostinger).
- Login, registro, logout, recuperación de contraseña (Épica 1).
- Rate limiting en login/registro.
- Rutas privadas bajo middleware `auth` (y opcional `verified`).

## 7. Notificaciones y scheduler (Épica 9)

- Recordatorios derivados en vivo de su fuente (ADR-0027) + comandos programados (`schedule:run`) vía cron de Hostinger (1 vez por minuto).
- **Sin** workers de cola persistentes obligatorios. Si se usan colas, driver `database` y un cron que ejecute `queue:work --stop-when-empty` o `schedule:run`.
- Canal de recordatorios: **in-app** (tabla propia `reminders` para los avisos sueltos; el resto se deriva). Además, **digest diario opcional por correo** (ADR-0028). WhatsApp/Push quedan como canales futuros (push VAPID en la Épica 10).

### Política de correo — **estrictamente lo imprescindible** (ADR-0015, enmendada por ADR-0028)

Finlia **sí** envía correo, pero muy poco y con reglas: lo imprescindible (donde el destinatario no puede ver el mensaje dentro de la app) más un único digest opt-in de recordatorios.

| Caso | ¿Correo? | Por qué |
|---|---|---|
| **Invitar a alguien al hogar** | ✅ Sí | El invitado puede no tener cuenta todavía: no hay bandeja in-app donde avisarle. |
| **Recuperar contraseña** | ✅ Sí | El usuario está fuera de la sesión por definición. |
| **Digest diario de recordatorios** (Épica 9, [ADR-0028](DECISIONS.md#adr-0028)) | ✅ Solo opt-in | La app avisa cuando el usuario la abre; una obligación vencida que nadie vio es el caso donde el correo aporta valor real. Máx. 1 por hogar y miembro al día, solo con urgentes. |
| Correos por evento ("pagaste X", "vence Y mañana") | ❌ No | Ruido: para eso está el digest y la app. |
| Resúmenes periódicos, informes, novedades, marketing | ❌ Nunca | No son imprescindibles y convierten la app en una fuente de ruido. |

Reglas que acompañan a la política:

- **Los correos llevan lo mínimo**: la invitación solo nombre del hogar y quién invita; el digest solo título, fecha y monto de las urgentes — **nunca** saldos, movimientos ni nombres de cuentas.
- **El correo es opcional**: si no hay SMTP configurado la app funciona igual. La invitación siempre deja un **enlace manual** que el administrador puede compartir; el digest con transport falso (`log`/`array`) ni siquiera corre (`mail_is_deliverable()`), para no gastar el envío del día.
- Interruptor global en `config/finlia.php` → `finlia.mail.enabled`. Apaga invitaciones **y** digest.
- Añadir un correo nuevo **exige un ADR**: así entró el digest (ADR-0028).
- Envío **síncrono** por ahora (invitaciones: acción del usuario; digest: corrida del cron de madrugada con `try/catch` por destinatario). Si el volumen crece, se encola con `ShouldQueue` + driver `database` y el cron ya documentado; no hace falta cambiar nada más.

## 8. Frontend

- **Bootstrap 5** vía Vite (Épica 1) como base de grid/utilities.
- **Sistema de diseño propio** encima de Bootstrap: ver [docs/UI_DESIGN.md](UI_DESIGN.md) — vidrio
  ("liquid glass" sutil), chips, segmentados, tarjetas hero, barra de navegación inferior + FAB en
  móvil (`layouts/partials/mobile-bottom-nav.blade.php`). Es el lenguaje visual por defecto — toda
  vista nueva lo usa, no reinventa Bootstrap suelto.
- **Chart.js** para gráficos del dashboard (Épica 8).
- JavaScript **vanilla**: suficiente para interacciones. Sin frameworks.
- Blade para todo el render server-side.
- Mobile-first: el layout y el flujo "registrar gasto" se diseñan primero para celular.
- Componentes Blade (`resources/views/components`) para tarjetas, stats, tablas, formularios.

## 9. Flujo principal: registrar un gasto

```
Usuario (celular)
  → Botón flotante "+" (Épica 10)
  → Form "Registrar gasto" (mínimos pasos):
      1. valor
      2. categoría
      3. cuenta/medio de pago
      4. fecha (default: hoy)
      5. descripción (opcional)
  → POST /expenses  (StoreExpenseRequest valida)
  → ExpenseController@store  →  authorize  →  crea el Expense
  → Eloquent insert (household_id + user_id + account_id)
  → actualiza current_balance de la cuenta
  → flash + redirect al dashboard
```

El objetivo: **< 5 segundos** desde abrir la app hasta confirmar.

## 10. Decisiones diferidas

Las decisiones arquitectónicas viven en [docs/DECISIONS.md](DECISIONS.md). Resumen:

- **ADR-0001 (ACEPTADA)**: ingresos y gastos en tablas separadas (`incomes` + `expenses`).
- **ADR-0002 (ACEPTADA)**: tarjetas de crédito como `accounts` con `type=credit_card` + tabla `credit_cards` de extensión.
- **ADR-0003 (PENDIENTE)**: ¿IDs auto-increment o UUID para recursos compartidos por URL (invitaciones)? Se confirma al iniciar la Épica 2.
