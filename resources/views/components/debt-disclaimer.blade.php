{{--
    Componente: aviso de que las cifras de deuda son aproximadas (ADR-0023).

    Finlia calcula con la fórmula estándar de amortización, pero cada entidad
    aplica sus propias reglas. El aviso debe verse allí donde el usuario mira
    un número de deuda: al registrarla, en el panel y en el detalle.

    Uso: <x-debt-disclaimer />                 (bloque completo)
         <x-debt-disclaimer compact />         (una línea, para pies de página)
--}}
@props(['compact' => false])

@php
    $texto = 'La cuota, los intereses y las fechas son estimaciones calculadas con la fórmula
              estándar de amortización. Tu entidad puede aplicar otras reglas —seguros, cuota de
              manejo, días de mora, redondeos o compras nuevas—, así que los valores reales
              pueden variar.';
@endphp

@if ($compact)
    <p class="text-muted small mt-4 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        {{ $texto }}
    </p>
@else
    <div class="alert alert-warning d-flex gap-2 border-0" role="note">
        <i class="bi bi-exclamation-circle-fill mt-1"></i>
        <div class="small">
            <strong>Los valores son aproximados.</strong>
            {{ $texto }}
            Úsalos como guía para decidir, no como estado de cuenta de tu banco.
        </div>
    </div>
@endif
