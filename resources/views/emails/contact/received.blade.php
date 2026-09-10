{{--
    Aviso interno de un mensaje de contacto. HTML autocontenido, sin datos
    financieros: solo lo que la persona escribió y su contexto técnico.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>[{{ $reasonLabel }}] {{ $senderName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#eef3f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a2330;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef3f8; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; padding:28px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 4px; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0b3f44;">
                                {{ $reasonLabel }}
                            </p>
                            <h1 style="margin:0 0 20px; font-size:20px; line-height:1.3;">{{ $senderName }}</h1>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#5b6776; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:2px 0;">Correo</td>
                                    <td style="padding:2px 0; text-align:right; color:#1a2330;">{{ $senderEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0;">Enviado</td>
                                    <td style="padding:2px 0; text-align:right; color:#1a2330;">{{ $sentAt }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0;">Origen</td>
                                    <td style="padding:2px 0; text-align:right; color:#1a2330;">
                                        {{ $fromGuest ? 'Sitio público, sin cuenta' : 'Aplicación, con sesión' }}
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color:#f4f7fa; border-radius:12px; padding:16px; font-size:15px; line-height:1.6; white-space:pre-wrap;">{{ $body }}</div>

                            @if ($context !== [])
                                <p style="margin:24px 0 8px; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#5b6776;">
                                    Contexto técnico
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; color:#5b6776;">
                                    @foreach ($context as $clave => $valor)
                                        <tr>
                                            <td style="padding:3px 0;">{{ $clave }}</td>
                                            <td style="padding:3px 0; text-align:right; color:#1a2330; word-break:break-all;">{{ $valor }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <p style="margin:24px 0 0; font-size:13px; color:#5b6776;">
                                Responde a este correo para contestarle directamente.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
