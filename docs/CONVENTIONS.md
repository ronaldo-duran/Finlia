# Convenciones — Finlia

> Reglas de estilo para que el código sea consistente y predecible. Aplica a todo el código nuevo.

## 1. Idioma

- **Identificadores** (clases, métodos, variables, tablas, columnas): **inglés**.
- **UI, mensajes al usuario y documentación**: **español**.
- **Comentarios**: **español**, y sujetos a la regla de la sección 1.1.
- Errores de validación visibles al usuario: en español, claros.

## 1.1 Comentarios — el único formato admitido es PHPDoc

El código de Finlia **no lleva comentarios narrativos**. La regla es dura a propósito: un comentario que explica lo que ya dice el código envejece mal, se desincroniza en la primera refactorización y hace que el repositorio —que es público— parezca un tutorial en vez de un producto.

**Permitido:**

- **PHPDoc** (`/** ... */`) sobre clases, métodos, propiedades y constantes. Una descripción de **una o dos líneas** más las anotaciones (`@param`, `@return`, `@var`, `@throws`, `@deprecated`). Aquí es donde se documenta una decisión de negocio o un ADR, no en medio del método.
- **Docblocks obligatorios de Laravel**: `@var` para propiedades tipadas por array, `@return` con genéricos de colecciones.

**Prohibido:**

- Comentarios de línea `//` y `#` de cualquier tipo: ni sobre una línea, ni al final de ella, ni como separador.
- Bloques `/* ... */` que no sean PHPDoc, incluidas las cabeceras tipo banner (`/*|-----|*/`) que Laravel trae por defecto en `routes/`.
- Comentarios Blade `{{-- ... --}}` para rotular secciones de una vista. Si una vista necesita rótulos para entenderse, hay que partirla en componentes.
- Comentarios en JS/CSS (`//`, `/* */`). Se admite **JSDoc** (`/** ... */`) sobre una función exportada.
- Código comentado "por si acaso". Para eso está git.
- `TODO`, `FIXME`, `XXX`, `HACK`. Lo pendiente va a una épica de [docs/ROADMAP.md](ROADMAP.md) o a un issue, no al código.

**Emojis**: prohibidos en todo el código y en la copia de la interfaz (PHP, Blade, JS, CSS, salida de comandos de consola, mensajes de commit). Para un icono se usa **Bootstrap Icons** (`<i class="bi bi-..."></i>`), que ya está en el bundle. Los emojis de `docs/*.md` son navegación de la documentación y sí se conservan.

**Si el "por qué" es imprescindible**, tiene tres sitios y ninguno es el cuerpo del método:

1. El **PHPDoc** de la clase o del método, en una o dos líneas.
2. Un **ADR** en [docs/DECISIONS.md](DECISIONS.md), referenciado desde el PHPDoc (`(ADR-0040)`).
3. Un **test** con nombre descriptivo, que además impide que el "por qué" se rompa en silencio.

**Antes de que el código sea mejor con un comentario, suele serlo con un nombre mejor**: extraer la condición a un método con nombre, o el número mágico a una constante, elimina la necesidad del comentario y la mantiene verificada por el compilador.

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

- Layout principal: `resources/views/layouts/app.blade.php` (autenticado, con sidebar de
  escritorio + barra inferior móvil) / `layouts/guest.blade.php` (público).
- **Sistema de diseño**: [docs/UI_DESIGN.md](UI_DESIGN.md) — es el lenguaje visual **por
  defecto** de Finlia (glass, `.chip`/`.chip-row`, `.segmented`, `.hero-card`, `.btn-finlia`,
  bottom nav móvil). Toda vista nueva se construye con esas piezas; no Bootstrap genérico suelto
  ni un estilo ad-hoc por pantalla. Componentes reutilizables adicionales en
  `resources/views/components/` (p.ej. `<x-form-input>`, `<x-form-select>`).
- **`{{ }}`** siempre; `{!! !!}` solo con contenido seguro del sistema.
- `@csrf` en todos los forms. `@method('PUT')` donde corresponda.
- Clases de **Bootstrap 5** (grid, utilities) como base; los componentes de marca de
  `docs/UI_DESIGN.md` van encima. Mobile-first: diseñar primero `col-12`, luego breakpoints.
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
