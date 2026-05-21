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
    | Padrón — WS Constancia de Inscripción (personaServiceA5)
    |--------------------------------------------------------------------------
    |
    | ARCA_CUIT_REPRESENTADA (padron.cuit_representada) es el CUIT del certificado
    | delegado ante ARCA para consultar datos de terceros. Solo aplica a este WS.
    |
    | Factura electrónica (WSFEv1 / CAE / CAEA) NO usa esta variable: el CUIT emisor
    | sale de empresa.nroinscripcion (ver ArcaWsfeFacturaElectronicaService::cuitEmisor).
    |
    */
    'padron' => [
        'cuit_representada' => env('ARCA_CUIT_REPRESENTADA'),

        'ws_sr_constancia_inscripcion' => [
            'service_id' => 'ws_sr_constancia_inscripcion',
            'homo' => [
                'wsdl' => 'https://awshomo.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL',
            ],
            'prod' => [
                'wsdl' => 'https://aws.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL',
            ],
        ],

        'base_storage' => $padronBase,
        'cert_path' => env('ARCA_CERT_PATH', $padronBase.'/certs/cert.crt'),
        'private_key_path' => env('ARCA_PRIVATE_KEY_PATH', $padronBase.'/certs/privada.key'),
        'private_key_passphrase' => env('ARCA_PRIVATE_KEY_PASSPHRASE', ''),
        'ta_storage_dir' => env('ARCA_TA_STORAGE', $padronBase.'/ta'),
        'tmp_dir' => env('ARCA_TMP_DIR', $padronBase.'/tmp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alias legacy (solo padrón) — preferir arca.padron.*
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

    'cuit_representada' => env('ARCA_CUIT_REPRESENTADA'),

    'padron_base_storage' => $padronBase,
    'cert_path' => env('ARCA_CERT_PATH', $padronBase.'/certs/cert.crt'),
    'private_key_path' => env('ARCA_PRIVATE_KEY_PATH', $padronBase.'/certs/privada.key'),
    'private_key_passphrase' => env('ARCA_PRIVATE_KEY_PASSPHRASE', ''),
    'ta_storage_dir' => env('ARCA_TA_STORAGE', $padronBase.'/ta'),
    'tmp_dir' => env('ARCA_TMP_DIR', $padronBase.'/tmp'),

    /*
    |--------------------------------------------------------------------------
    | Catálogo tipos de comprobante AFIP (ABM tipotransaccion, etc.)
    |--------------------------------------------------------------------------
    |
    | null / vacío: inferir por puntos de venta activos en modo CAE (modofacturacion=C).
    | wsmtxca | wsfev1: forzar el webservice ARCA para FEParamGetTiposCbte / consultarTiposComprobante.
    */
    'tipos_cbte' => [
        'webservice' => env('ARCA_TIPOS_CBTE_WEBSERVICE'),
        /** Guardar catálogo en tabla arca_tipo_comprobante tras cada consulta ARCA exitosa */
        'persistir_en_bd' => filter_var(env('ARCA_TIPOS_CBTE_PERSISTIR', true), FILTER_VALIDATE_BOOLEAN),
        /** Si refresh=0, usar BD si ya hay sincronización para empresa + webservice */
        'usar_bd_sin_refresh' => filter_var(env('ARCA_TIPOS_CBTE_USAR_BD', true), FILTER_VALIDATE_BOOLEAN),
        /** Bloquear si CUIT del cert ≠ empresa.nroinscripcion (desactivado por defecto; afip.php puede usar otro CUIT en el XML) */
        'validar_cuit_certificado' => filter_var(env('ARCA_TIPOS_CBTE_VALIDAR_CUIT', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
