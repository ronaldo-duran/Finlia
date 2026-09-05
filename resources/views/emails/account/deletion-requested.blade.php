{{--
    Correo antifraude: solicitud de eliminación de cuenta (Plan 05).
    HTML autocontenido. Sin datos financieros.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitaste eliminar tu cuenta de {{ $appName }}</title>
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
                                Recibimos tu solicitud de eliminación de cuenta
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#5b6776;">
                                Hola <strong style="color:#1a2330;">{{ $userName }}</strong>, registramos tu solicitud para eliminar
                                tu cuenta en <strong style="color:#1a2330;">{{ $appName }}</strong>.
                            </p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#5b6776;">
                                Tu cuenta quedará <strong style="color:#1a2330;">suspendida hasta el {{ $deadline }}</strong>.
                                Si cambias de opinión antes de esa fecha, puedes reactivarla con el botón de abajo.
                                Después de esa fecha, la cuenta y sus datos se eliminarán de forma permanente.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background-color:#0b3f44; border-radius:12px;">
                                        <a href="{{ $reactivateUrl }}"
                                           style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Reactivar mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#5b6776;">
                                ¿El botón no funciona? Copia y pega esta dirección en tu navegador:<br>
                                <a href="{{ $reactivateUrl }}" style="color:#0b3f44; word-break:break-all;">{{ $reactivateUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background-color:#f6f9fc; border-top:1px solid rgba(15,23,42,0.08);">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#5b6776;">
                                <strong>¿No fuiste tú?</strong> Si no solicitaste eliminar tu cuenta, inicia sesión de inmediato
                                y cambia tu contraseña para proteger tu acceso.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
