<?php

return [
    'sandbox' => [
        'url'               => 'https://sandbox.factura.com',
        'api-key'           => env('FACTURACOM_API_KEY', ''),
        'secret-key'        => env('FACTURACOM_SECRET_KEY', ''),
        'serie'             => env('FACTURACOM_SERIE', ''),
        'serie_complemento' => env('FACTURACOM_SERIE_COMPLEMENTO', ''),
        'headers' => [
            "Content-Type"  => "application/json",
            "F-PLUGIN"      => '9d4095c8f7ed5785cb14c0e3b033eeb8252416ed',
            "F-Api-Key"     => env('FACTURACOM_API_KEY', ''),
            "F-Secret-Key"  => env('FACTURACOM_SECRET_KEY', '')
        ],
        'headers_curl' => [
            "Content-Type: application/json",
            "F-PLUGIN: 9d4095c8f7ed5785cb14c0e3b033eeb8252416ed",
            "F-Api-Key: ".env('FACTURACOM_API_KEY', ''),
            "F-Secret-Key: ". env('FACTURACOM_SECRET_KEY', '')
        ]
    ],
    'produccion' => [
        'url'               => 'https://factura.com',
        'api-key'           => env('FACTURACOM_API_KEY', ''),
        'secret-key'        => env('FACTURACOM_SECRET_KEY', ''),
        'serie'             => env('FACTURACOM_SERIE', ''),
        'serie_complemento' => env('FACTURACOM_SERIE_COMPLEMENTO', ''),
        'headers' => [
            "Content-Type"  => "application/json",
            "F-PLUGIN"      => '9d4095c8f7ed5785cb14c0e3b033eeb8252416ed',
            "F-Api-Key"     => env('FACTURACOM_API_KEY', ''),
            "F-Secret-Key"  => env('FACTURACOM_SECRET_KEY', '')
        ],
        'headers_curl' => [
            "Content-Type: application/json",
            "F-PLUGIN: 9d4095c8f7ed5785cb14c0e3b033eeb8252416ed",
            "F-Api-Key: ".env('FACTURACOM_API_KEY', ''),
            "F-Secret-Key: ". env('FACTURACOM_SECRET_KEY', '')
        ]
    ],
];
