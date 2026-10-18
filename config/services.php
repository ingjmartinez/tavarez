<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'centro_costo' => [
        'url' => env('CENTRO_COSTO_API_URL'),
        'token' => env('CENTRO_COSTO_API_TOKEN'),
        'empresa_id' => env('CENTRO_COSTO_API_EMPRESA_ID'),
        'timeout' => (int) env('CENTRO_COSTO_API_TIMEOUT', 120),
    ],

    'ventas_usuario' => [
        'token_url' => env('VENTAS_USUARIO_API_TOKEN_URL'),
        'ventas_url' => env('VENTAS_USUARIO_API_VENTAS_URL'),
        'pagos_url' => env('VENTAS_USUARIO_API_PAGOS_URL'),
        'pagos_otra_empresa_url' => env('VENTAS_USUARIO_API_PAGOS_OTRA_EMPRESA_URL'),
        'ventas_raza_url' => env('VENTAS_USUARIO_API_VENTAS_RAZA_URL'),
        'codigo' => env('VENTAS_USUARIO_API_CODIGO', '08'),
        'header_cc' => env('VENTAS_USUARIO_API_HEADER_CC'),
        'header_vb' => env('VENTAS_USUARIO_API_HEADER_VB'),
        'timeout' => (int) env('VENTAS_USUARIO_API_TIMEOUT', 60),
    ],

];
