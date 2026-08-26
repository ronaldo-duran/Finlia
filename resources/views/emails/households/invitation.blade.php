{{--
    Correo de invitación a un hogar.
    HTML autocontenido con estilos en línea (los clientes de correo no cargan
    hojas externas). Sin imágenes remotas ni datos financieros.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $householdName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#eef3f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a2330;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef3f8; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 14px -8px rgba(15,23,42,0.25);">

                    <tr>
                        <td style="background-color:#0f766e; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:700; letter-spacing:-0.3px;">{{ $appName }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:1.3; font-weight:700; color:#1a2330;">
                                Te invitaron a un hogar en {{ $appName }}
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#5b6776;">
                                @if ($invitedByName)
                                    <strong style="color:#1a2330;">{{ $invitedByName }}</strong> te invitó a unirte al hogar
                                @else
                                    Te invitaron a unirte al hogar
                                @endif
                                <strong style="color:#1a2330;">{{ $householdName }}</strong>
                                para llevar juntos las finanzas de la casa.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background-color:#0f766e; border-radius:12px;">
                                        <a href="{{ $acceptUrl }}"
                                           style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Aceptar invitación
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px; font-size:13px; line-height:1.6; color:#5b6776;">
                                La invitación caduca el <strong style="color:#1a2330;">{{ $expiresAt->format('d/m/Y') }}</strong>
                                y solo puede usarse una vez. Necesitas una cuenta de {{ $appName }} con este mismo
                                correo para aceptarla; si aún no la tienes, podrás crearla en el enlace.
                            </p>

                            <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#5b6776;">
                                ¿El botón no funciona? Copia y pega esta dirección en tu navegador:
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.5; word-break:break-all;">
                                <a href="{{ $acceptUrl }}" style="color:#0f766e;">{{ $acceptUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background-color:#f6f9fc; border-top:1px solid rgba(15,23,42,0.08);">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#5b6776;">
                                Si no esperabas esta invitación, ignora este mensaje: sin aceptarla nadie accede
                                a tus datos. {{ $appName }} solo te escribe para cosas imprescindibles como esta.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
