<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    |
    | homo: Testing/Homologación
    | prod: Producción
    |
    */
    'env' => env('ARCA_ENV', 'homo'),

    /*
    |--------------------------------------------------------------------------
    | WSAA
    |--------------------------------------------------------------------------
    */
    'wsaa' => [
        'homo' => [
            // Homologación: el certificado público actual es *.afip.gob.ar
            'wsdl' => 'https://wsaahomo.afip.gob.ar/ws/services/LoginCms?WSDL',
            'url' => 'https://wsaahomo.afip.gob.ar/ws/services/LoginCms',
        ],
        'prod' => [
            // Producción: wsaa.arca.gov.ar no resuelve en algunos DNS; wsaa.arca.gob.ar sí.
            'wsdl' => 'https://wsaa.arca.gob.ar/ws/services/LoginCms?WSDL',
            'url' => 'https://wsaa.arca.gob.ar/ws/services/LoginCms',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WS SR Constancia de Inscripción (Padrón)
    |--------------------------------------------------------------------------
    */
    'ws_sr_constancia_inscripcion' => [
        'service_id' => 'ws_sr_constancia_inscripcion',
        'homo' => [
            'wsdl' => 'https://awshomo.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL',
        ],
        'prod' => [
            'wsdl' => 'https://aws.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identificación del organismo / representada
    |--------------------------------------------------------------------------
    */
    'cuit_representada' => env('ARCA_CUIT_REPRESENTADA'),

    /*
    |--------------------------------------------------------------------------
    | Certificados (PEM)
    |--------------------------------------------------------------------------
    |
    | Por seguridad estos archivos deberían estar en storage y NO versionados.
    |
    */
    'cert_path' => env('ARCA_CERT_PATH', storage_path('app/arca/certs/cert.crt')),
    'private_key_path' => env('ARCA_PRIVATE_KEY_PATH', storage_path('app/arca/certs/privada.key')),
    'private_key_passphrase' => env('ARCA_PRIVATE_KEY_PASSPHRASE', ''),

    /*
    |--------------------------------------------------------------------------
    | Cache del TA
    |--------------------------------------------------------------------------
    */
    'ta_storage_dir' => storage_path('app/arca/ta'),
];
