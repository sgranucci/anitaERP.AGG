<?php

$padronBase = env('ARCA_PADRON_BASE', storage_path('app/arca/sr_padron'));

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
            'wsdl' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL',
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
    | Almacenamiento WSAA / certificados — Padrón (sin subcarpetas por empresa)
    |--------------------------------------------------------------------------
    |
    | Misma idea que WSFE (storage/app/arca/wsfe/...), en una sola raíz:
    |   sr_padron/certs/   cert.crt, privada.key
    |   sr_padron/ta/      tickets de acceso WSAA (padrón)
    |   sr_padron/tmp/     firma PKCS7 del TRA
    |
    | Facturación WSFE sigue en config/arca_wsfe.php → app/arca/wsfe/...
    |
    | Si migrás desde la ubicación antigua (app/arca/certs, app/arca/ta, app/arca/tmp),
    | copiá los archivos a sr_padron/... o definí ARCA_CERT_PATH / ARCA_PADRON_BASE.
    |
    */
    'padron_base_storage' => $padronBase,

    'cert_path' => env('ARCA_CERT_PATH', $padronBase.'/certs/cert.crt'),
    'private_key_path' => env('ARCA_PRIVATE_KEY_PATH', $padronBase.'/certs/privada.key'),
    'private_key_passphrase' => env('ARCA_PRIVATE_KEY_PASSPHRASE', ''),

    'ta_storage_dir' => env('ARCA_TA_STORAGE', $padronBase.'/ta'),

    'tmp_dir' => env('ARCA_TMP_DIR', $padronBase.'/tmp'),
];
