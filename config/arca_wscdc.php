<?php

$wscdcBase = env('ARCA_WSCDC_BASE', storage_path('app/arca/wscdc'));

return [
    /*
    |--------------------------------------------------------------------------
    | WSCDC — Constatación de comprobantes (ComprobanteConstatar)
    |--------------------------------------------------------------------------
    |
    | Certificados y TA propios bajo storage/app/arca/wscdc/ (independiente de
    | padrón config/arca.php y WSFE config/arca_wsfe.php).
    |
    | ARCA_WSCDC_CUIT_REPRESENTADA: CUIT del certificado delegado ante ARCA.
    |
    */
    'habilitado' => filter_var(env('ARCA_WSCDC_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'cuit_representada' => env('ARCA_WSCDC_CUIT_REPRESENTADA', env('ARCA_CUIT_REPRESENTADA')),

    'wsaa_service_id' => 'wscdc',

    'wscdc' => [
        'homo' => [
            'wsdl' => 'https://wswhomo.afip.gob.ar/WSCDC/service.asmx?WSDL',
            'wsdl_local' => $wscdcBase.'/wsdl/homo/service.wsdl',
        ],
        'prod' => [
            // servicios1.arca.gob.ar falla SNI/cert en algunos clientes; AFIP sigue respondiendo.
            'wsdl' => 'https://servicios1.afip.gov.ar/WSCDC/service.asmx?WSDL',
            'wsdl_local' => $wscdcBase.'/wsdl/prod/service.wsdl',
        ],
    ],

    'base_storage' => $wscdcBase,
    'cert_path' => env('ARCA_WSCDC_CERT_PATH', $wscdcBase.'/certs/cert.crt'),
    'private_key_path' => env('ARCA_WSCDC_PRIVATE_KEY_PATH', $wscdcBase.'/certs/privada.key'),
    'private_key_passphrase' => env('ARCA_WSCDC_PRIVATE_KEY_PASSPHRASE', env('ARCA_PRIVATE_KEY_PASSPHRASE', '')),
    'ta_storage_dir' => env('ARCA_WSCDC_TA_STORAGE', $wscdcBase.'/ta'),
    'tmp_dir' => env('ARCA_WSCDC_TMP_DIR', $wscdcBase.'/tmp'),

    'soap_timeout' => (int) env('ARCA_WSCDC_SOAP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Precarga compras (PDF+IA)
    |--------------------------------------------------------------------------
    */
    'precarga' => [
        'habilitado' => filter_var(env('ARCA_WSCDC_PRECARGA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'cbte_modo_default' => env('ARCA_WSCDC_CBTE_MODO_DEFAULT', 'CAE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comprobante proveedor (ABM / carga manual o desde precarga)
    |--------------------------------------------------------------------------
    |
    | Constatación WSCDC al grabar factura de proveedor (además de CAE en UI).
    |
    */
    'comprobante' => [
        'habilitado' => filter_var(env('ARCA_WSCDC_COMPROBANTE_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        // false = avisar y permitir grabar (WS caído, rechazo ARCA, datos incompletos).
        // true = bloquear el grabado ante hallazgos WSCDC (modo estricto).
        'bloquear_al_fallar' => filter_var(env('ARCA_WSCDC_COMPROBANTE_BLOQUEAR_AL_FALLAR', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
