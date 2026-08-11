# Convenciones — Finami

> Reglas de estilo para que el código sea consistente y predecible. Aplica a todo el código nuevo.

## 1. Idioma

- **Identificadores** (clases, métodos, variables, tablas, columnas): **inglés**.
- **UI, mensajes al usuario y documentación**: **español**.
- **Comentarios**: **español**, breves, solo donde aportan claridad (el "qué" lo dice el código; el "por qué", el comentario).
- Errores de validación visibles al usuario: en español, claros.

## 2. Nombres

| Elemento | Convención | Ejemplo |
|---|---|---|
| Tablas | snake_case plural | `households`, `savings_goals`, `recurring_expenses` |
| Columnas | snake_case | `household_id`, `current_balance` |
| FK | `<tabla_singular>_id` | `account_id`, `debt_id` |
| Pivot (orden alfabético) | `<a>_<b>` | `household_user` |
| Modelos | Singular, StudlyCase | `Household`, `Expense`, `SavingsGoal` |
| Controladores | `<Recurso>Controller` | `ExpenseController` |
| Form Requests | `<Acción><Recurso>Request` | `StoreExpenseRequest`, `UpdateBudgetRequest` |
| Policies | `<Modelo>Policy` | `ExpensePolicy` |
| Servicios | `<Dominio>Service` | `BudgetCalculatorService`, `AvailableMoneyService` |
| Enums | StudlyCase, en `app/Enums` | `CategoryType`, `Frequency` |
| Rutas (name) | snake_case con punto | `expenses.store`, `households.members.index` |
| Variables/props | camelCase | `$currentBalance`, `$availableMoney` |

## 3. Dinero (CRÍTICO)

- **Tipo de columna**: `DECIMAL(15,2)`. **Nunca** `FLOAT`, `DOUBLE` ni `INTEGER` "de centavos" sin justificación documentada.
- **Migración**: `$table->decimal('amount', 15, 2);`
- **Cast de modelo**: `'amount' => 'decimal:2'`.
- **Validación**: `numeric|min:0` (los montos se almacenan positivos; el signo lo da `type`).
- **Visualización COP**: `$ 1.000.000` (punto miles, coma decimales).
- **Centralización**: usar `App\Support\MoneyFormatter` y un Blade component `@money($value, $currency = 'COP')`.
- Para sumas/reportes, preferir agregación SQL sobre `SUM()` de PHP cuando sea posible (precisión y rendimiento).
- No hardcodear el símbolo `$` ni el formato en cada vista: pasar por el formatter (prepara multi-moneda).

## 4. Fechas

- **Almacenamiento**: `date` (sin hora) para fechas de movimiento/vencimiento; `datetime`/`timestamp` para auditoría.
- **Zona horaria**: `America/Bogota` (configurable por hogar en el futuro).
- **Visualización**: `DD/MM/AAAA` (p.ej. `10/08/2026`). Usar `Carbon` + helper Blade `@date($value)`.
- **Semana/mes**: en Colombia la semana suele iniciar en **lunes**. Configurar `firstDayOfWeek` en los calendarios/pickers.

## 5. Moneda

- Default: **COP**. Colocar en `households.currency` y `users.preferred_currency`.
- **No** acoplar lógica a un símbolo o código fijo: el código de moneda es un dato (`'COP'`), no una constante esparcida.
- Reservar campo de moneda por cuenta/transacción para futuro multi-moneda, aunque hoy todo sea COP.

## 6. PHP / Laravel

- `declare(strict_types=1);` en clases de dominio (Services) y Enums.
- Tipado de parámetros y retornos siempre que sea posible.
- **Fillable**: atributo `#[Fillable([...])]` o propiedad `$fillable`. **Nunca** `$guarded = []`.
- **Casts**: método `casts(): array` con tipos nativos (`datetime`, `decimal:2`, `boolean`, `enum`).
- **Relaciones**: métodos en minúsculas plural/singular (`expenses()`, `household()`). Usar `return $this->hasMany(...)`.
- **Scopes**: `scopeFoo()` en camelCase.
- **No** usar `env()` en código de aplicación (rompe con `config:cache`). Solo en archivos de `config/`.
- Controladores **finos**: validación → autorización → servicio → respuesta.

## 7. Blade / Frontend

- Layout principal: `resources/views/layouts/app.blade.php`.
- Componentes reutilizables en `resources/views/components/` (p.ej. `<x-stat-card>`, `<x-money>`).
- **`{{ }}`** siempre; `{!! !!}` solo con contenido seguro del sistema.
- `@csrf` en todos los forms. `@method('PUT')` donde corresponda.
- Clases de **Bootstrap 5** (grid, utilities). Mobile-first: diseñar primero `col-12`, luego breakpoints.
- Tablas largas: **paginación** (`{{ $items->links() }}`), no listas infinitas.
- JS **vanilla** en `resources/js/`; Chart.js para gráficos.

## 8. Base de datos

- Una migración por cambio de esquema, con nombres descriptivos (`create_expenses_table`, `add_emergency_fund_to_savings_goals`).
- **Índices** en todas las FK y en columnas de filtro frecuente (`household_id`, `date`, `status`).
- **Foreign keys** con `onDelete` explícito (cascade para hijos; restrict para críticas).
- `utf8mb4` / `utf8mb4_unicode_ci`.
- Seeders idempotentes (`firstOrCreate`) para no duplicar al re-seed.
- Factories con estados realistas (montos COP coherentes, fechas recientes).

## 9. Git / Commits

- **Conventional Commits** (en español o inglés, pero consistente):
  - `feat:` nueva funcionalidad
  - `fix:` corrección de bug
  - `test:` solo tests
  - `docs:` solo documentación
  - `refactor:` sin cambio de comportamiento
  - `chore:` tooling, deps, config
  - `perf:` rendimiento
- Commits **pequeños y atómicos**, con descripción del "por qué".
- Cuerpo del commit explica contexto si no es obvio.
- Una rama por épica: `epica-N-descripcion`. PR/merge a `main` cuando tests estén verdes.
- **Nunca** commitear `.env`, secretos ni datos reales (ver [SECURITY.md](SECURITY.md#2-secrets)).

## 10. Testing

- **Feature tests** para flujos HTTP (autenticación, CRUD, permisos).
- **Unit tests** para servicios de cálculo (`BudgetCalculatorService`, etc.).
- Cada recurso del hogar **debe** tener un test de aislamiento (403 contra otro hogar).
- Usar `RefreshDatabase` + factories. Datos falsos siempre.
- Nombrado: `test_<sujeto>_<escenario>` (p.ej. `test_owner_puede_invitar_miembro`).

## 11. Formato

- **Laravel Pint** (PSR-12 + reglas Laravel) para PHP: `vendor/bin/pint`.
- Espacios, no tabs; fin de archivo con salto de línea; sin espacios al final de línea.
