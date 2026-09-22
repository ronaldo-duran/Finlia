<?php

declare(strict_types=1);

return [
    'version' => '0.40.0',
    'compulsive_survey' => [
        'enabled' => filter_var(env('FINLIA_COMPULSIVE_SURVEY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'subscription' => [
        'premium_for_all' => filter_var(
            env('FINLIA_PREMIUM_FOR_ALL', true),
            FILTER_VALIDATE_BOOLEAN,
        ),
    ],
    'domains' => [
        'marketing' => env('FINLIA_MARKETING_DOMAIN'),
        'app' => env('FINLIA_APP_DOMAIN'),
    ],
    'market' => env('FINLIA_MARKET', 'CO'),
    'locale' => env('APP_FAKER_LOCALE', 'es_CO'),

    'mail' => [
        'enabled' => env('FINLIA_MAIL_ENABLED', true),
        'fake_transports' => ['log', 'array'],
    ],
    'contact' => [
        'inbox' => env('FINLIA_CONTACT_EMAIL'),
    ],
    'currency' => [
        'code' => env('FINLIA_CURRENCY_CODE', 'COP'),
        'symbol' => env('FINLIA_CURRENCY_SYMBOL', '$'),
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'decimals' => 2,
    ],
];
