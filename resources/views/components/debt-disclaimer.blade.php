@php
    $clave = \App\Enums\AcknowledgementKey::DebtEstimates;
    $leido = auth()->check() && auth()->user()->hasAcknowledged($clave);
    $texto = 'La cuota, los intereses y las fechas son estimaciones calculadas con la fórmula
              estándar de amortización. Tu entidad puede aplicar otras reglas —seguros, cuota de
              manejo, días de mora, redondeos o compras nuevas—, así que los valores reales
              pueden variar.';
@endphp
@if ($leido)
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
        <form method="POST" action="{{ route('acknowledgements.store', $clave->value) }}" class="mt-2 ms-4 ps-1">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                Entendido, no mostrar de nuevo
            </button>
        </form>
    </div>
@endif
