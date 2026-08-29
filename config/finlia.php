<?php

declare(strict_types=1);

/*
|----------------------------------------------------------------------
| Configuración de Finlia
|----------------------------------------------------------------------
|
| Constantes base de la aplicación. Mercado inicial: Colombia (COP).
| El diseño no se acopla a una sola moneda; estos valores son la
| referencia central que heredarán las próximas épicas (helper de
| formato `@money`, etc. — se implementa en Épica 3).
|
*/

return [

    // Versión actual del software (fuente de verdad; sincronizar con package.json
    // y CHANGELOG.md al publicar cada versión).
    'version' => '0.8.0',

    // Mercado / idioma por defecto.
    'market' => env('FINLIA_MARKET', 'CO'),
    'locale' => env('APP_FAKER_LOCALE', 'es_CO'),

    /*
    | Correo transaccional (ADR-0015).
    |
    | Finlia envía correo SOLO para lo estrictamente necesario: invitar a
    | alguien al hogar y recuperar la contraseña. Nada de resúmenes,
    | recordatorios ni marketing (esos son in-app). El correo es OPCIONAL:
    | si no hay SMTP configurado la app sigue funcionando y la invitación
    | se comparte con el enlace manual.
    */
    'mail' => [
        // Interruptor global del correo transaccional.
        'enabled' => env('FINLIA_MAIL_ENABLED', true),

        // Transports que NO entregan a una bandeja real (desarrollo y tests).
        // Con ellos la UI sigue mostrando el enlace manual como vía principal.
        'fake_transports' => ['log', 'array'],
    ],

    // Moneda por defecto (ISO 4217).
    'currency' => [
        'code' => env('FINLIA_CURRENCY_CODE', 'COP'),
        'symbol' => env('FINLIA_CURRENCY_SYMBOL', '$'),
        // Formato colombiano: 1.000.000,00 (punto miles, coma decimales).
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'decimals' => 2,
    ],

];
