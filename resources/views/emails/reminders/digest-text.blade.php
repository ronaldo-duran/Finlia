{{--
    Digest diario de recordatorios — versión texto plano (ADR-0028).
    Mismo contenido que digest.blade.php, sin HTML: algunos clientes y
    lectores de pantalla prefieren esta parte.
--}}
@php
    $partes = [];
    if ($summary['overdue'] > 0) {
        $partes[] = $summary['overdue'].' '.($summary['overdue'] === 1 ? 'obligación vencida' : 'obligaciones vencidas');
    }
    if ($summary['upcoming'] > 0) {
        $partes[] = $summary['upcoming'].' '.($summary['upcoming'] === 1 ? 'próxima' : 'próximas');
    }
@endphp
Hola: tienes {{ implode(' y ', $partes) }} en el hogar {{ $householdName }}.
Esto es lo que pide atención:

@foreach ($urgent as $item)
@php
    $days = $item['days_remaining'];
    $linea = match ($item['status']) {
        App\Enums\ReminderStatus::Overdue => 'VENCIDA hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días'),
        App\Enums\ReminderStatus::Upcoming => $days === 0 ? 'Vence HOY' : 'Vence en '.$days.' '.($days === 1 ? 'día' : 'días'),
        default => '',
    };
@endphp
* {{ $item['title'] }} — {{ $linea }}
  {{ $item['due_date']->format('d/m/Y') }} · {{ $item['source']->label() }}@if ($item['amount'] !== null) · $ {{ number_format($item['amount'], 0, ',', '.') }}@endif
@endforeach

Ver tus recordatorios: {{ $url }}

Un aviso se apaga pagando, no leyendo este correo: aquí no se marca nada
como leído ni cambia ningún dato.

Como máximo un correo al día por hogar, y solo cuando tengas urgentes.
Puedes desactivarlo cuando quieras en Recordatorios → Resumen por
correo dentro de {{ $appName }}.
