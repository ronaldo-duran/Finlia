{{--
    Notificación: el usuario es nuevo propietario del hogar (Plan 05).
    HTML autocontenido. Sin datos financieros.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ahora eres administrador/a de {{ $householdName }}</title>
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
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:1.3; font-weight:700; color:#1a2330;">
                                Ahora eres administrador/a de {{ $householdName }}
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#5b6776;">
                                Hola <strong style="color:#1a2330;">{{ $newOwnerName }}</strong>, el propietario anterior del hogar
                                <strong style="color:#1a2330;">{{ $householdName }}</strong> eliminó su cuenta en
                                {{ $appName }}, y la administración del hogar te fue transferida a ti
                                por ser el miembro más antiguo activo.
                            </p>

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#5b6776;">
                                Ahora puedes gestionar los miembros e invitaciones del hogar desde tu perfil.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="background-color:#0b3f44; border-radius:12px;">
                                        <a href="{{ $dashboardUrl }}"
                                           style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Ir al inicio
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background-color:#f6f9fc; border-top:1px solid rgba(15,23,42,0.08);">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#5b6776;">
                                Este mensaje se generó automáticamente porque eres el miembro activo más antiguo del hogar.
                                Si tienes dudas, responde a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
