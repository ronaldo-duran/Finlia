{{--
    Selector de variante visual del rediseño (Épica 10, adelantado):
    "a" Enfoque (un número, un gesto) vs "b" Control (el mes de un vistazo).
    Preferencia de UI en sesión (helper design_variant()), no de negocio.
--}}
@php
    $variant = design_variant();
@endphp

<div class="segmented variant-switch w-100" role="group" aria-label="Variante de diseño">
    <form method="POST" action="{{ route('preferences.design-variant') }}" class="flex-fill">
        @csrf
        <input type="hidden" name="variant" value="a">
        <button type="submit" class="segmented-item w-100 {{ $variant === 'a' ? 'active' : '' }}">
            Enfoque
        </button>
    </form>
    <form method="POST" action="{{ route('preferences.design-variant') }}" class="flex-fill">
        @csrf
        <input type="hidden" name="variant" value="b">
        <button type="submit" class="segmented-item w-100 {{ $variant === 'b' ? 'active' : '' }}">
            Control
        </button>
    </form>
</div>
