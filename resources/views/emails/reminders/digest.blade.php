{{--
    Digest diario de recordatorios (ADR-0028).
    HTML autocontenido con estilos en línea (los clientes de correo no
    cargan hojas externas). Solo obligaciones urgentes; la app es la
    verdad, este correo es un aviso con dedo que trae de vuelta.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — Recordatorios</title>
</head>
<body style="margin:0; padding:0; background-color:#eef3f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a2330;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef3f8; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 14px -8px rgba(15,23,42,0.25);">

                    <tr>
                        <td style="background-color:#0b3f44; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:700; letter-spacing:-0.3px;">{{ $appName }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 8px; font-size:20px; line-height:1.3; font-weight:700; color:#1a2330;">
                                @if ($summary['overdue'] > 0)
                                    Tienes {{ $summary['overdue'] }} {{ $summary['overdue'] === 1 ? 'obligación vencida' : 'obligaciones vencidas' }}
                                    @if ($summary['upcoming'] > 0)
                                        y {{ $summary['upcoming'] }} {{ $summary['upcoming'] === 1 ? 'próxima' : 'próximas' }}
                                    @endif
                                @else
                                    Tienes {{ $summary['upcoming'] }} {{ $summary['upcoming'] === 1 ? 'obligación próxima' : 'obligaciones próximas' }}
                                @endif
                            </h1>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#5b6776;">
                                en el hogar <strong style="color:#1a2330;">{{ $householdName }}</strong>.
                                Esto es lo que pide atención:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                @foreach ($urgent as $item)
                                    @php
                                        $days = $item['days_remaining'];
                                        [$line, $color] = match ($item['status']) {
                                            App\Enums\ReminderStatus::Overdue => [
                                                'Vencida hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días'),
                                                '#b02a37',
                                            ],
                                            App\Enums\ReminderStatus::Upcoming => [
                                                $days === 0 ? 'Vence hoy' : 'Vence en '.$days.' '.($days === 1 ? 'día' : 'días'),
                                                '#8a5a00',
                                            ],
                                            default => ['', '#5b6776'],
                                        };
                                    @endphp
                                    <tr>
                                        <td style="padding:12px 0; border-bottom:1px solid rgba(15,23,42,0.08);">
                                            <span style="display:block; font-size:15px; font-weight:600; color:#1a2330;">{{ $item['title'] }}</span>
                                            <span style="display:block; margin-top:2px; font-size:13px; color:{{ $color }};">{{ $line }}</span>
                                            <span style="display:block; margin-top:2px; font-size:13px; color:#5b6776;">
                                                {{ $item['due_date']->format('d/m/Y') }} · {{ $item['source']->label() }}
                                                @if ($item['amount'] !== null) · $ {{ number_format($item['amount'], 0, ',', '.') }} @endif
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background-color:#0b3f44; border-radius:12px;">
                                        <a href="{{ $url }}"
                                           style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Ver mis recordatorios
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#5b6776;">
                                Un aviso se apaga <strong style="color:#1a2330;">pagando</strong>, no leyendo este
                                correo: aquí no se marca nada como leído ni cambia ningún dato.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background-color:#f6f9fc; border-top:1px solid rgba(15,23,42,0.08);">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#5b6776;">
                                Como máximo un correo al día por hogar, y solo cuando tengas urgentes.
                                <a href="{{ $unsubscribeUrl }}" style="color:#5b6776; text-decoration:underline;">Ya no quiero recibir este resumen</a>
                                — o en Recordatorios → Resumen por correo dentro de {{ $appName }}.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
