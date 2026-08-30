{{--
    Confirmación de baja del digest (ADR-0028). Página autocontenida,
    sin layout: el click llega desde el buzón y puede no haber sesión
    (ni siquiera en este dispositivo). Mismo lenguaje visual del correo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — Baja del resumen por correo</title>
    <style>
        body { margin:0; padding:0; background-color:#eef3f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a2330; }
        .wrap { max-width:480px; margin:48px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 14px -8px rgba(15,23,42,0.25); }
        .brand { background-color:#0b3f44; color:#ffffff; font-size:20px; font-weight:700; padding:20px 28px; letter-spacing:-0.3px; }
        .body { padding:28px; }
        h1 { margin:0 0 8px; font-size:20px; line-height:1.3; }
        p { margin:0 0 12px; font-size:15px; line-height:1.6; color:#5b6776; }
        p strong { color:#1a2330; }
        .btn { display:inline-block; margin-top:8px; padding:13px 26px; background-color:#0b3f44; border-radius:12px; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; }
        .foot { padding:18px 28px; background-color:#f6f9fc; border-top:1px solid rgba(15,23,42,0.08); font-size:12px; line-height:1.6; color:#5b6776; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">{{ $appName }}</div>
        <div class="body">
            <h1>Listo, ya no te enviamos el resumen</h1>
            <p>
                No volverá a llegar el digest de <strong>{{ $householdName }}</strong>
                a tu correo. Tus recordatorios siguen intactos dentro de
                {{ $appName }} — esto solo apaga el correo, no los avisos de la app.
            </p>
            <p>Si algún día quieres volver a recibirlo, actívalo de nuevo en Recordatorios → Resumen por correo.</p>
            <a class="btn" href="{{ route('login') }}">Ir a {{ $appName }}</a>
        </div>
        <div class="foot">
            Nada se borra con esta baja: obligaciones, pagos y metas quedan como estaban.
        </div>
    </div>
</body>
</html>
