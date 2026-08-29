{{--
    Componente: aviso de que las cifras de deuda son aproximadas (ADR-0023).

    Finlia calcula con la fórmula estándar de amortización, pero cada entidad
    aplica sus reglas. El aviso acompaña a los números en las tres pantallas
    de deuda: alta, panel y detalle.

    Se puede dar por leído (ADR-0024), pero NO desaparece: se reduce a una
    línea que sigue junto a las cifras. Que la advertencia se esfume del todo
    es justo el escenario a evitar en una app de finanzas — alguien mira una
    proyección a veinte años meses después y la lee como si fuera del banco.

    Uso: <x-debt-disclaimer />
--}}
@php
    $clave = \App\Enums\AcknowledgementKey::DebtEstimates;
    $leido = auth()->check() && auth()->user()->hasAcknowledged($clave);

    $texto = 'La cuota, los intereses y las fechas son estimaciones calculadas con la fórmula
              estándar de amortización. Tu entidad puede aplicar otras reglas —seguros, cuota de
              manejo, días de mora, redondeos o compras nuevas—, así que los valores reales
              pueden variar.';
@endphp

@if ($leido)
    {{-- Ya lo leyó: queda el recordatorio mínimo, sin ocupar pantalla. --}}
    <p class="text-muted small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Los valores son aproximados y pueden variar según tu entidad.
    </p>
@else
    <div class="alert alert-warning border-0" role="note">
        <div class="d-flex gap-2">
            <i class="bi bi-exclamation-circle-fill mt-1"></i>
            <div class="small">
                <strong>Los valores son aproximados.</strong>
                {{ $texto }}
                Úsalos como guía para decidir, no como estado de cuenta de tu banco.
            </div>
        </div>

        {{-- Formulario normal, sin JavaScript: funciona igual si falla el JS
             y deja constancia en el servidor, no solo en este navegador. --}}
        <form method="POST" action="{{ route('acknowledgements.store', $clave->value) }}" class="mt-2 ms-4 ps-1">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                Entendido, no mostrar de nuevo
            </button>
        </form>
    </div>
@endif
