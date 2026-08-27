@props(['size' => 28])

{{--
    Isotipo Finlia (docs/BRAND.md): treinta puntos como los días del mes,
    simplificados a 3×2 en el contenedor. Colores fijos —el contenedor
    petróleo y el cobre de "fondo oscuro" son invariantes en los dos
    temas, igual que en public/finlia-icon.svg—, así que este componente
    NO seguirá el toggle de tema y no hay que darle una variante oscura.
--}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}"
     role="img" aria-label="Finlia" {{ $attributes }}>
    <rect width="64" height="64" rx="17" fill="#0B3F44"></rect>
    <g fill="#E8F1F0">
        <circle cx="15" cy="23.5" r="5.5"></circle>
        <circle cx="32" cy="23.5" r="5.5"></circle>
        <circle cx="49" cy="23.5" r="5.5"></circle>
        <circle cx="15" cy="40.5" r="5.5"></circle>
    </g>
    <g fill="#D9A45E">
        <circle cx="32" cy="40.5" r="5.5"></circle>
        <circle cx="49" cy="40.5" r="5.5"></circle>
    </g>
</svg>
