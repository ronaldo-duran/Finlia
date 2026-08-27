# Sistema de diseño — Finlia

> Cómo se ve Finlia y con qué piezas se construye una vista nueva. Nace del rediseño mobile-first
> (Claude Design, Épica 10 adelantada — igual que el theming se adelantó en la Épica 1) y es el
> lenguaje visual **por defecto** de la app: toda vista nueva debe construirse con estas piezas,
> no reinventar Bootstrap suelto.

Fuente: `resources/css/app.css` (sección "Épica 10 (adelantado) — Rediseño mobile-first").
Vistas de referencia: `resources/views/dashboard.blade.php`, `dashboard/_hero-enfoque.blade.php`,
`movements/index.blade.php`, `movements/_item.blade.php`, `expenses/_form.blade.php`,
`components/brandmark.blade.php`. Identidad de marca (logo/color): [docs/BRAND.md](BRAND.md).

## 1. Principios

1. **Mobile-first, sin dejar de usarse desde PC.** Se diseña primero a 390px; el sidebar de
   escritorio y las grillas son la ampliación, no el punto de partida.
2. **Un color de marca, poco ruido.** `--finlia-primary` (petróleo/teal, [BRAND.md](BRAND.md))
   como único acento — más `--finlia-accent` (cobre) reservado exclusivamente para "dinero
   disponible", nunca decorativo. Nada de iconos de colores distintos por KPI "porque sí" (eso fue
   justo el feedback que motivó el rediseño: "los botones del dashboard no mantienen armonía").
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
| `--finlia-primary` / `-hover` / `-rgb` | Marca (petróleo/teal oscuro — ver [docs/BRAND.md](BRAND.md)). |
| `--finlia-accent` / `-hover` / `-rgb` | Cobre. **Siempre** "dinero disponible" ([docs/BRAND.md](BRAND.md)) — nunca un acento decorativo suelto. |
| `--finlia-success` / `--finlia-danger` / `--finlia-warning` (+ `-rgb`) | Estados. Desaturados; pisan `.text-success`, `.bg-danger-subtle`, `.text-bg-warning`, etc. — **no** uses los verdes/rojos "de fábrica" de Bootstrap a mano, usa las clases utilitarias de siempre y el token las resuelve. |
| `--finlia-surface`, `--finlia-nav-bg`, `--finlia-border`, `--finlia-glass-border` | Vidrio. Ya aplicados por `.card`, `.glass-nav`, `.finlia-sidebar`. |
| `--finlia-radius` (16px) / `--finlia-radius-sm` (12px) | Radios. Los componentes nuevos (`.chip`, `.hero-card`, `.bottom-nav-fab`…) usan `999px`/`24px` a propósito — son elementos "de gesto", no tarjetas de contenido. |
| `--finlia-safe-bottom` | `env(safe-area-inset-bottom)`, para la barra inferior en móviles con notch. |

Nunca hardcodees el hex de marca en una vista nueva. Si necesitas un color inline (p. ej. un
`style=""` puntual porque el elemento no puede usar una clase), usa `var(--finlia-primary)` /
`var(--finlia-accent)` — **excepto en emails** (`resources/views/emails/`) y `<x-brandmark>`
(`resources/views/components/brandmark.blade.php`), donde los clientes de correo y el icono de
marca (colores invariantes por diseño, ver BRAND.md) no usan custom properties y el hex va
literal; mantenlo sincronizado a mano si cambia la marca.

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
Alternancia binaria de igual peso (Gasto/Ingreso). `.segmented-item` puede ser un `<a>` (navega)
o un `<span class="active">` (la opción actual, no clicable).

```blade
<div class="segmented">
    <span class="segmented-item active">Gasto</span>
    <a href="{{ route('incomes.create') }}" class="segmented-item">Ingreso</a>
</div>
```

### `.hero-card` / `.hero-figure`
La cifra protagonista de la pantalla (§1.4). `.hero-card` no trae fondo propio — combínalo con
`bg-finlia-accent-subtle` (dinero **disponible** — el caso normal, ver [BRAND.md](BRAND.md)) o
`bg-danger-subtle` (alerta, p. ej. te pasaste del plan), como en `dashboard/_hero-enfoque.blade.php`.
Si la cifra protagonista NO es "disponible" (p. ej. un total gastado), usa `bg-finlia-subtle`
normal — el cobre es específicamente para disponibilidad, no un tinte genérico de "cifra grande".

### `.bottom-nav` / `.bottom-nav-item` / `.bottom-nav-fab`
Ya está en el layout (`layouts/partials/mobile-bottom-nav.blade.php`), **no la repitas** en
vistas nuevas. Son **4 destinos fijos** (Panel, Movimientos, Presupuesto, Más) + el FAB central de
registro — si una vista nueva necesita un quinto hueco, hay que sacar algo, no añadir un sexto. El
sidebar de escritorio (`layouts/app.blade.php`) tiene, además de esos 4, los enlaces que no caben
en la barra (Cuentas, Categorías, Ingresos esperados, Hogares) — esos mismos son los que aparecen
en el "Más" móvil (abre el sidebar completo como offcanvas, **desde la derecha**: coherente con
que el botón "Más" está a la derecha de la barra — si algún día se mueve de posición, cambia
`offcanvas-end` a `offcanvas-start` junto con él). Panel/Movimientos/Presupuesto se ocultan
(`d-none d-lg-block`) en esa vista móvil del sidebar porque ya tienen su propio hueco en la barra;
mostrarlos ahí también sería el mismo destino dos veces.

### `.day-group-label`
Encabezado de grupo en listas cronológicas (`movements/index`, agrupa por
`$items->groupBy(fn ($m) => $m['date']->format('Y-m-d'))`). Reutilízalo en cualquier listado
nuevo que tenga sentido agrupar por día (p. ej. una futura vista de "Gastos recurrentes").

### `<details><summary>Más detalles</summary>...</details>`
Patrón (no clase CSS, HTML nativo) para bajar el ruido de campos secundarios/opcionales en
formularios largos (ver `expenses/_form.blade.php`: medio de pago y notas quedan ocultos hasta
que el usuario los pide, salvo que ya tengan valor — entonces `open` por defecto). Úsalo cuando
un formulario tenga más de ~5 campos y algunos sean claramente opcionales.

### `data-money-input` (`window.FinliaMoney`, en `resources/js/app.js`)
Cualquier `<input type="text" inputmode="decimal" data-money-input>` de dinero se formatea en
vivo con la convención colombiana (punto de miles, coma decimal: "1.234.567,50") y se reescribe a
numérico plano ("1234567.50") justo antes de enviar el formulario, para que el Form Request
(`numeric`) y el cast `decimal:2` lo reciban sin cambios. **No** uses `type="number"` para dinero
— no admite el punto de miles y forzaría a elegir entre formato bonito o validación nativa; con
`data-money-input` no hay que elegir: sigue siendo un input real, `required` sigue funcionando,
solo cambia el `type`. Si necesitas leer el valor numérico desde otro script en la misma página
(como el aviso "te quedarían $X" de `expenses/_form.blade.php`), usa
`window.FinliaMoney.parse(input.value)`, nunca `parseFloat` directo — el valor en pantalla lleva
puntos de miles que `parseFloat` interpretaría como decimales.

### Chips que fijan un `<select>` — sincronización en los dos sentidos
Cuando un `.chip-row` es un atajo sobre un `<select>` real (categoría en
`expenses/incomes/_form.blade.php`), el chip elegido debe iluminarse **y** el `<select>` debe
reflejar cualquier cambio hecho por fuera de los chips (el usuario abre el desplegable y elige
otra categoría). La regla: un único `syncChips(value)` que limpia todos los `.active` y enciende
el que coincida (ninguno si el valor no está entre los chips rápidos), llamado tanto desde el
`click` de cada chip como desde el `change` del `<select>`. Ver el script de
`expenses/_form.blade.php` — cópialo si añades chips-sobre-select en un formulario nuevo.

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

## 6. Colores de marca y estado

Los colores de marca (petróleo/cobre) son los de la identidad aprobada — ver
[docs/BRAND.md](BRAND.md) para el símbolo, la paleta completa y las reglas de uso del logo. Los
de estado (`--finlia-success/-danger/-warning`) son independientes de la marca (semántica de
éxito/peligro/aviso, no identidad) pero siguen el mismo criterio que motivó el rediseño mobile-
first: *"se ve demasiado neón en algunas partes"* — tono apagado, no saturado, coherente en claro
y oscuro. Si necesitas un nuevo tono de estado (p. ej. un badge "vencido"), sigue ese mismo
criterio; no reintroduzcas los verdes/rojos vivos por defecto de Bootstrap.

## 7. Checklist para una vista nueva

1. ¿Extiende `layouts.app` (autenticado) o `layouts.guest` (público)? No dupliques navbar/footer/tema/favicon —ya están ahí, y el favicon nuevo trae su propio `x-brandmark`/`layouts.partials.favicon`, no un `bi-wallet2` suelto (ver [BRAND.md](BRAND.md)).
2. ¿El contenedor principal es `.card` (ya viene con vidrio)? No añadas `backdrop-filter` a mano.
3. ¿Hay una cifra de dinero protagonista? → `.hero-card` + `.hero-figure`, no un `<h1>` gigante suelto.
4. ¿Hay un filtro o alternancia de pocas opciones? → `.chip-row` o `.segmented`, con el control
   real (`<select>`/enlace con query string) siempre presente y funcional.
5. ¿Botones de acción? Uno primario `.btn-finlia`, el resto `.btn-outline-*` — evita 3+ botones
   del mismo peso visual en una fila (la razón original del rediseño).
6. ¿Lista de movimientos? Reutiliza `movements/_item.blade.php`, no la reescribas.
7. ¿Formulario largo? Campos esenciales arriba, secundarios bajo `<details>`.
8. ¿Un input de dinero? `data-money-input`, nunca `type="number"` (§4).
9. ¿Necesita aparecer en la navegación inferior móvil? Edita el partial existente (4 huecos fijos
   + FAB), no crees otra barra — y si algo no cabe, va al sidebar/"Más", no a un quinto hueco.
10. Corre `npx playwright test` si tocaste Panel/Movimientos/Registrar — son las pantallas con
    cobertura E2E más estricta sobre el layout.
