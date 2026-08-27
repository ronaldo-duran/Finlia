# Marca Finlia

> Identidad visual aprobada (Claude Design, "Finlia — Marca", turno 6 · entregable final,
> [ADR-0017](DECISIONS.md#adr-0017)). Reemplaza el verde provisional del rediseño mobile-first
> ([ADR-0016](DECISIONS.md#adr-0016)) como color de marca oficial.

## El símbolo

Treinta puntos, rejilla 6 × 5: los días de un mes. Veinticuatro en **petróleo** son los días que
ya pasaron; seis en **cobre** son los que el dinero disponible todavía cubre — es literalmente la
cifra "puedes gastar hoy" del Panel convertida en marca.

- **Construcción**: punto ø14, separación 6, paso 20, caja 114×94, rejilla 6×5 = 30.
- **Simplificación** (el símbolo completo no cabe a tamaños pequeños):
  - ≥ 24 px → rejilla 3×2 (4 puntos claros + 2 cobre) — es lo que hay en `public/finlia-icon.svg`.
  - < 24 px → rejilla 2×2 (3 claros + 1 cobre) — `public/finlia-icon-16.png`.
  - En un lockup (icono + palabra) siempre va la versión 3×2, nunca la de 30 puntos completa.

## Color

| Nombre | Hex | Token CSS | Uso |
|---|---|---|---|
| Petróleo | `#0B3F44` | `--finlia-primary` (claro) | Color principal: botones, enlaces, iconos activos. |
| Teal oscuro | `#3F8F8A` | `--finlia-primary` (oscuro) | Equivalente de petróleo en tema oscuro — petróleo puro no tiene contraste suficiente sobre fondos casi negros. |
| Cobre | `#C08A3E` | `--finlia-accent` (claro) | **Siempre "lo disponible"** — nunca un acento decorativo genérico. |
| Cobre oscuro | `#D9A45E` | `--finlia-accent` (oscuro) | Ídem, tema oscuro. |
| Claro | `#E8F1F0` | — | Puntos claros del símbolo sobre el contenedor petróleo (fijo, no cambia con el tema). |
| Fondo oscuro | `#0B1C1F` | — | Fondo de piezas de marca sobre oscuro (no es `--finlia-bg`; ver nota). |

**El contenedor del icono es invariante**: siempre fondo petróleo `#0B3F44` con puntos claros
`#E8F1F0` y cobre `#D9A45E` (la variante "oscura" del cobre, elegida porque se lee mejor sobre el
petróleo del contenedor sin importar el tema del sitio). Por eso `<x-brandmark>` no tiene
variante clara/oscura ni sigue el toggle de tema — es la misma pieza siempre, como
`public/finlia-icon.svg`.

`--finlia-bg` (el fondo general de la app) **no** se tocó a `#E8F1F0`/`#0B1C1F` al adoptar la
marca — sigue siendo el gris azulado del rediseño mobile-first. El símbolo/logo son piezas
puntuales (favicon, navbar, sidebar), no una repintada de toda la superficie neutra de la app.

## Cómo usarlo en Blade

- **Icono/isotipo en la UI** (navbar, sidebar, cabeceras): `<x-brandmark :size="26" />`
  (`resources/views/components/brandmark.blade.php`). Es SVG inline con los colores fijos de
  arriba — no le pases clases de color, no las va a usar.
- **Favicon**: `@include('layouts.partials.favicon')` en el `<head>` — ya está en
  `layouts/app.blade.php` y `layouts/guest.blade.php`, no lo agregues a mano en una vista nueva.
- **Cobre en la interfaz** (docs/UI_DESIGN.md): `.text-finlia-accent` / `.bg-finlia-accent-subtle`
  / `.border-finlia-accent`, **solo** para "dinero disponible" (la cifra hero del Panel, la
  tarjeta de Presupuestos) — nunca como variante decorativa de un botón o un badge cualquiera. El
  resto de la marca (botones, enlaces, iconos activos, `.btn-finlia`) sigue usando
  `--finlia-primary`/`.text-finlia` sin cambios.
- **Lockup completo pre-renderizado** (`public/finlia-logo.svg`, `-claro.png`, `-oscuro.png`): son
  piezas de marca **estáticas** (texto en un color fijo, no sigue el tema) para contextos sin CSS
  vivo — un email, un README, una imagen social. **No** las uses dentro de la app: ahí el texto
  "Finlia" ya es HTML normal (`<span>Finlia</span>`) que hereda el color de tema correcto solo con
  `<x-brandmark>` al lado.

## Reglas (del entregable de marca)

- Aire mínimo alrededor del lockup: el diámetro de un punto.
- Bajo 88 px de ancho, solo el icono, sin la palabra "Finlia".
- Sobre foto o color, el icono va siempre en su contenedor petróleo (nunca suelto sin fondo).
- El cobre nunca cubre todo el símbolo ni se usa como color de texto de cuerpo.
- No estirar, rotar, sombrear ni degradar el símbolo.
- El número de puntos en cobre (seis en el símbolo completo, dos en la simplificación ≥24px, uno
  en la <24px) es una lectura, no un patrón decorativo — no lo cambies "para que se vea mejor".

## Archivos (`public/`)

| Archivo | Para qué |
|---|---|
| `favicon.svg`, `finlia-icon-{16,32}.png` | Favicon (ver `layouts/partials/favicon.blade.php`). |
| `finlia-icon-192.png` | `apple-touch-icon` / ícono PWA. |
| `finlia-icon-512.png` | Ícono PWA de alta resolución (cuando exista `manifest.json`, Épica 10). |
| `finlia-icon.svg` | El icono con contenedor, para cualquier uso que no sea favicon (`<x-brandmark>` lo inlinea en vez de referenciar este archivo, para poder controlar el tamaño con CSS/props sin una petición HTTP extra). |
| `finlia-logo.svg`, `-claro.png`, `-oscuro.png` | Lockup horizontal completo, colores fijos — solo para contextos sin CSS vivo (ver arriba). |
| `finlia-simbolo.svg`, `.png`, `-oscuro.png` | El símbolo de 30 puntos suelto, fondo transparente — piezas de marca (redes, portafolio), no de producto. |
