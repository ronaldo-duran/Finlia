# Sistema de diseño — Finlia

> Cómo se ve Finlia y con qué piezas se construye una vista nueva. Nace del rediseño mobile-first
> (Claude Design, Épica 10 adelantada — igual que el theming se adelantó en la Épica 1) y es el
> lenguaje visual **por defecto** de la app: toda vista nueva debe construirse con estas piezas,
> no reinventar Bootstrap suelto.

Fuente: `resources/css/app.css` (sección "Épica 10 (adelantado) — Rediseño mobile-first").
Vistas de referencia: `resources/views/dashboard.blade.php`, `dashboard/_hero-*.blade.php`,
`movements/index.blade.php`, `movements/_item.blade.php`, `expenses/_form.blade.php`.

## 1. Principios

1. **Mobile-first, sin dejar de usarse desde PC.** Se diseña primero a 390px; el sidebar de
   escritorio y las grillas son la ampliación, no el punto de partida.
2. **Un color de marca, poco ruido.** Verde `--finlia-primary` como único acento; nada de
   iconos de colores distintos por KPI "porque sí" (eso fue justo el feedback que motivó el
   rediseño: "los botones del dashboard no mantienen armonía").
3. **Glass sutil, no decorativo.** `backdrop-filter: blur` ya es el fondo por defecto de `.card`
   desde la Épica 1 — no hace falta añadirlo a mano, y no lo satures (blur fuerte + mucho color
   vuelve la app "neón").
4. **Jerarquía: una cifra protagonista por pantalla.** Si una pantalla responde a una pregunta de
   dinero ("¿cuánto puedo gastar?", "¿cuánto vale esto?"), esa cifra va en `.hero-figure`, grande,
   arriba. Todo lo demás baja de tamaño.
5. **Los inputs reales nunca se sacrifican por estética.** Un input "bonito" sigue siendo un
   `<input>`/`<select>` real, con `name`, `required` y validación HTML5 intactos — los atajos
   visuales (chips, anillos) son una capa **encima** del control real, nunca un reemplazo que
   rompa el envío del formulario o la navegación por teclado.

## 2. Tokens (ya en `:root` / `[data-bs-theme="dark"]`)

| Variable | Uso |
|---|---|
| `--finlia-primary` / `-hover` / `-rgb` | Marca. Claro `#0f6f66`, oscuro `#57b6a8` (desaturados a propósito, ver §7). |
| `--finlia-success` / `--finlia-danger` / `--finlia-warning` (+ `-rgb`) | Estados. También desaturados; pisan `.text-success`, `.bg-danger-subtle`, `.text-bg-warning`, etc. — **no** uses los verdes/rojos "de fábrica" de Bootstrap a mano, usa las clases utilitarias de siempre y el token las resuelve. |
| `--finlia-surface`, `--finlia-nav-bg`, `--finlia-border`, `--finlia-glass-border` | Vidrio. Ya aplicados por `.card`, `.glass-nav`, `.finlia-sidebar`. |
| `--finlia-radius` (16px) / `--finlia-radius-sm` (12px) | Radios. Los componentes nuevos (`.chip`, `.hero-card`, `.bottom-nav-fab`…) usan `999px`/`24px` a propósito — son elementos "de gesto", no tarjetas de contenido. |
| `--finlia-safe-bottom` | `env(safe-area-inset-bottom)`, para la barra inferior en móviles con notch. |

Nunca hardcodees el hex de marca en una vista nueva. Si necesitas un color inline (p. ej. un
`style=""` puntual porque el elemento no puede usar una clase), usa `var(--finlia-primary)` —
**excepto en emails** (`resources/views/emails/`), donde los clientes de correo no soportan CSS
custom properties y el hex sí va literal (`#0f6f66`, mantenlo sincronizado a mano si cambia la marca).

## 3. Piezas base (desde la Épica 1 — ya el default, no hace falta pedirlas)

- `.card` → vidrio + radio + sombra por defecto. Es el contenedor por defecto de cualquier bloque de contenido.
- `.btn-finlia` / `.btn-outline-finlia` → acción primaria / secundaria de marca.
- `.btn-icon` → botón circular de icono (volver, editar, eliminar, toggle de tema).
- `.avatar-btn` / `.static-avatar` → iniciales en círculo con gradiente de marca.
- `.money-figure` / `.money-hero` → tipografía tabular que escala con el viewport para cifras en COP.
- `.color-dot` → punto de color para categorías **sin** icono propio (la mayoría hoy — `Category.icon` está sin poblar). Para categorías con transacciones (listas de movimientos), preferimos el círculo de icono tintado (§5) porque añade contexto (bolsa de compras, auto…), pero `.color-dot` sigue siendo válido para listas simples (p. ej. `categories/index`).

## 4. Piezas nuevas del rediseño (úsalas por defecto en vistas nuevas)

### `.chip` / `.chip-row`
Selección rápida o filtro de una sola dimensión (tipo, período, categoría frecuente). Es un
**atajo visual sobre un control real** — nunca el único medio de filtrar/seleccionar si hay más
opciones que las que caben en chips: acompáñalo del `<select>`/form real (ver
`expenses/_form.blade.php`, que tiene chips de categoría **y** el `<select id="category_id">`
completo debajo, sincronizados por JS).

```blade
<div class="chip-row">
    <a href="..." class="chip {{ $active ? 'active' : '' }}">Todos</a>
    <a href="..." class="chip">Gastos</a>
</div>
```

Úsalo para: filtros de listado (`movements/index`), selector de período (`budgets/index`),
categorías frecuentes en un formulario. **No** lo uses para navegación estructural (eso es el
sidebar / bottom nav) ni para más de ~6 opciones (ahí va un `<select>` normal).

### `.segmented` / `.segmented-item`
Alternancia binaria de igual peso (Gasto/Ingreso, Enfoque/Control). `.segmented-item` puede ser
un `<a>` (navega), un `<button type="submit">` (envía un form, como el toggle de variante) o un
`<span class="active">` (la opción actual, no clicable).

```blade
<div class="segmented">
    <span class="segmented-item active">Gasto</span>
    <a href="{{ route('incomes.create') }}" class="segmented-item">Ingreso</a>
</div>
```

### `.hero-card` / `.hero-figure`
La cifra protagonista de la pantalla (§1.4). `.hero-card` no trae fondo propio — combínalo con
`bg-finlia-subtle` (positivo/neutro), `bg-danger-subtle` (alerta) o un `style="background-image:
linear-gradient(160deg, var(--finlia-primary), var(--finlia-primary-hover))"` + `text-white`
(cabecera "hero" con foto de marca, como en `dashboard/_hero-control.blade.php`).

### `.budget-ring` / `.budget-ring-inner`
Anillo de progreso (`conic-gradient` con `currentColor`, variable CSS `--pct` de 0 a 100). Solo
para "% de algo consumido/completado" en la variante "Control" de una pantalla de dinero — no lo
uses como gráfico genérico (para gráficos reales sigue siendo Chart.js, ver `resources/js/charts.js`).

### `.quick-action` / `.quick-action-icon`
Grilla de 3–4 accesos directos con icono circular + etiqueta debajo (ver el header "Control" del
Panel). Para navegación entre 3 y 6 acciones relacionadas con la pantalla actual, no para el menú
principal (eso es el sidebar/bottom nav).

### `.bottom-nav` / `.bottom-nav-item` / `.bottom-nav-fab`
Ya está en el layout (`layouts/partials/mobile-bottom-nav.blade.php`), **no la repitas** en
vistas nuevas. Si una vista nueva necesita aparecer en la barra inferior, edita ese partial (5
huecos fijos; hoy: Panel, Movimientos, FAB central, Presupuesto/Reportes, Más/Perfil) en vez de
crear una barra propia.

### `.day-group-label`
Encabezado de grupo en listas cronológicas (`movements/index`, agrupa por
`$items->groupBy(fn ($m) => $m['date']->format('Y-m-d'))`). Reutilízalo en cualquier listado
nuevo que tenga sentido agrupar por día (p. ej. una futura vista de "Gastos recurrentes").

### `<details><summary>Más detalles</summary>...</details>`
Patrón (no clase CSS, HTML nativo) para bajar el ruido de campos secundarios/opcionales en
formularios largos (ver `expenses/_form.blade.php`: medio de pago y notas quedan ocultos hasta
que el usuario los pide, salvo que ya tengan valor — entonces `open` por defecto). Úsalo cuando
un formulario tenga más de ~5 campos y algunos sean claramente opcionales.

## 5. Iconografía de categoría (listas de movimientos)

`Category.icon` casi nunca está poblado (el seeder solo define `color`), así que el patrón es:
icono **genérico por tipo** (`bi-arrow-down-left` ingreso / `bi-arrow-up-right` gasto) en un
círculo tintado con el **color de la categoría** si existe, o el tinte de marca si no:

```blade
<div style="background-color: {{ $tint ? $tint.'26' : 'rgba(var(--finlia-primary-rgb), .12)' }};
            color: {{ $tint ?? 'var(--finlia-primary)' }};">
    <i class="bi {{ $icon }}"></i>
</div>
```

(`'26'` al final del hex = ~15% de opacidad en notación `#RRGGBBAA`.) Ver `movements/_item.blade.php`
— **reutilízalo** (`@include('movements._item', ['m' => $m, 'showActions' => true])`) en vez de
copiar el markup: ya lo usan el Panel y Movimientos.

## 6. Variante de diseño conmutable (`design_variant()`)

El Panel tiene **dos formas** de mostrar el mismo mes: "Enfoque" (`a`, una cifra + un gesto) y
"Control" (`b`, resumen con anillo + accesos rápidos). Es una preferencia de **sesión**, no de
negocio (`App\Support\helpers.php::design_variant()`, `DesignVariantController`, sin modelo ni
migración) — igual que `active_household()`.

**No generalices esto a toda vista nueva.** Solo tiene sentido cuando una pantalla de uso diario
admite honestamente dos jerarquías de información igual de válidas (como el Panel). Una pantalla
de administración (Cuentas, Categorías, Hogares…) no necesita una "variante" — tiene una sola
forma correcta de mostrarse.

## 7. Colores de marca y estado — por qué están desaturados

Los tokens de marca (`#0f6f66`/`#57b6a8`) y de estado (`--finlia-success/-danger/-warning`) se
ajustaron a partir del feedback explícito de la sesión de diseño: *"se ve demasiado neón en
algunas partes"*. Si necesitas un nuevo tono para un estado que no existe todavía (p. ej. un
badge "vencido"), sigue el mismo criterio: tono apagado, no saturado, coherente en claro y
oscuro — no reintroduzcas los verdes/rojos vivos por defecto de Bootstrap.

## 8. Checklist para una vista nueva

1. ¿Extiende `layouts.app` (autenticado) o `layouts.guest` (público)? No dupliques navbar/footer/tema.
2. ¿El contenedor principal es `.card` (ya viene con vidrio)? No añadas `backdrop-filter` a mano.
3. ¿Hay una cifra de dinero protagonista? → `.hero-card` + `.hero-figure`, no un `<h1>` gigante suelto.
4. ¿Hay un filtro o alternancia de pocas opciones? → `.chip-row` o `.segmented`, con el control
   real (`<select>`/enlace con query string) siempre presente y funcional.
5. ¿Botones de acción? Uno primario `.btn-finlia`, el resto `.btn-outline-*` — evita 3+ botones
   del mismo peso visual en una fila (la razón original del rediseño).
6. ¿Lista de movimientos? Reutiliza `movements/_item.blade.php`, no la reescribas.
7. ¿Formulario largo? Campos esenciales arriba, secundarios bajo `<details>`.
8. ¿Necesita aparecer en la navegación inferior móvil? Edita el partial existente, no crees otra barra.
9. Corre `npx playwright test` si tocaste Panel/Movimientos/Registrar — son las pantallas con
   cobertura E2E más estricta sobre el layout.
