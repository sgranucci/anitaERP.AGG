<?php

/**
 * Facturación electrónica vía WSFEv1 (COMPG / RG 4291).
 *
 * Rutas separadas del WS de padrón (config/arca.php → storage/app/arca/sr_padron/...).
 * Certificados por empresa: storage/app/arca/wsfe/certs/{carpeta}/cert.crt y privada.key
 */
return [
    /*
    | afip_php: módulo externo (storage/.../afip.php + XML), comportamiento histórico.
    | soap: WSFEv1 nativo (ArcaWsfeFacturaElectronicaService).
    */
    'transporte' => env('ARCA_WSFE_TRANSPORTE', 'afip_php'),

    /** Service name para el TRA WSAA (manual COMPG: "wsfe") */
    'wsaa_service_id' => 'wsfe',

    'wsfe' => [
        'homo' => [
            'wsdl' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
        ],
        'prod' => [
            'wsdl' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL',
        ],
    ],

    /** Raíz dedicada WSFE (no mezclar con app/arca/sr_padron del padrón) */
    'base_storage' => storage_path('app/arca/wsfe'),

    /**
     * Mapeo empresa_id → carpeta bajo certs/ (biyemas, kandiko, rebisco, etc.)
     */
    'empresas' => [
        1 => ['carpeta_cert' => 'biyemas'],
        2 => ['carpeta_cert' => 'kandiko'],
        3 => ['carpeta_cert' => 'rebisco'],
    ],

    /** Timeout SOAP en segundos */
    'soap_timeout' => (int) env('ARCA_WSFE_SOAP_TIMEOUT', 60),
];
