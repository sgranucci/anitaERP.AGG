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

    /**
     * Receptor en comprobantes (RG4444 / manual WSFEv1). Compartido entre unidades de negocio.
     */
    'receptor' => [
        /** Umbral en pesos: bajo → Consumidor Final (tipo doc. 99, nro. 0); sobre → cliente maestro o datos manuales. */
        'consumidor_final_umbral_monto' => (float) env(
            'ARCA_WSFE_RECEPTOR_CF_UMBRAL_MONTO',
            env('GASTRONOMIA_CONSUMIDOR_FINAL_TOPE_MONTO', 10000000)
        ),
        'consumidor_final_razon_social' => env(
            'ARCA_WSFE_RECEPTOR_CF_RAZON_SOCIAL',
            env('GASTRONOMIA_CONSUMIDOR_FINAL_NOMBRE', 'CONSUMIDOR FINAL')
        ),
        'consumidor_final_tipo_documento' => (int) env(
            'ARCA_WSFE_RECEPTOR_CF_TIPO_DOCUMENTO',
            env('GASTRONOMIA_CONSUMIDOR_FINAL_TIPODOC_ARCA', 99)
        ),
        'consumidor_final_numero_documento' => env(
            'ARCA_WSFE_RECEPTOR_CF_NUMERO_DOCUMENTO',
            env('GASTRONOMIA_CONSUMIDOR_FINAL_DOCUMENTO_ARCA', '0')
        ),
        /** Cliente ERP para IVA/contabilidad cuando el receptor ARCA es CF o manual (no define DocTipo/Nro hacia AFIP). */
        'cliente_erp_interno_id' => env(
            'ARCA_WSFE_RECEPTOR_CLIENTE_ERP_INTERNO_ID',
            env('GASTRONOMIA_CLIENTE_CONTABLE_INTERNO_ID')
        ),
        'consumidor_final_condicion_iva_id' => (int) env(
            'ARCA_WSFE_RECEPTOR_CF_CONDICION_IVA_ID',
            env('GASTRONOMIA_CONSUMIDOR_FINAL_CONDICIONIVA_ID', 3)
        ),
        /** Tipo documento ARCA por defecto para receptor identificado manual (96=DNI; 80 si 11 dígitos). */
        'identificado_tipo_documento_default' => (int) env(
            'ARCA_WSFE_RECEPTOR_IDENTIFICADO_TIPO_DOCUMENTO',
            env('GASTRONOMIA_RECEPTOR_MANUAL_TIPODOC_ARCA', 96)
        ),
    ],

    /**
     * Estrategia CAE (WS en línea) vs CAEA (contingencia) por transacción.
     */
    'emision' => [
        /**
         * true: obliga PV/modo CAEA sin invocar ARCA (corte de red o mantenimiento).
         * false: intenta CAE; si hay error de comunicación y reintentar_caea…=true, una sola vez usa CAEA.
         */
        'forzar_modo_caea' => filter_var(
            env('ARCA_WSFE_FORZAR_MODO_CAEA', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'reintentar_caea_si_falla_comunicacion' => filter_var(
            env(
                'ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION',
                env('GASTRONOMIA_REINTENTAR_CAEA', true)
            ),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
