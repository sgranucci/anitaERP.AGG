<?php

/**
 * Facturación electrónica vía WSFEv1 (COMPG / RG 4291).
 *
 * Rutas separadas del WS de padrón (config/arca.php → storage/app/arca/sr_padron/...).
 * Certificados por empresa_id: storage/app/arca/wsfe/certs/{carpeta}/cert.crt y privada.key
 *
 * La carpeta se resuelve según EMPRESA (.env, misma variable que config/app.php → app.empresa).
 * Ver empresas_por_entorno. Los certificados no van en Git (solo la estructura de carpetas en el servidor).
 *
 * CUIT emisor (CAE, CAEA, consultas WSFE): empresa.nroinscripcion por empresa_id.
 * ARCA_CUIT_REPRESENTADA (.env / config/arca.php padron) es solo para el WS de padrón A5.
 */

/**
 * @return array<int, array{carpeta_cert: string}>
 */
$resolveArcaWsfeEmpresas = static function (): array {
    /** @var array<string, array<int, string>> empresa instalación → empresa_id → nombre de carpeta bajo wsfe/certs/ */
    $porEntorno = [
        'EL BIERZO' => [
            1 => 'bierzo',
        ],
        'AGG' => [
            1 => 'biyemas',
            2 => 'kandiko',
            3 => 'rebisco',
        ],
        'INTERFORMING' => [
            1 => 'interforming',
        ],
        'FRASLE' => [
            1 => 'frasle',
        ],
    ];

    $empresaInstalacion = trim((string) env('EMPRESA', 'AGG'), " \t\n\r\0\x0B'\"");

    $carpetas = $porEntorno[$empresaInstalacion] ?? [];

    $jsonOverride = env('ARCA_WSFE_EMPRESAS_JSON');
    if (is_string($jsonOverride) && $jsonOverride !== '') {
        $decoded = json_decode($jsonOverride, true);
        if (is_array($decoded)) {
            $carpetas = [];
            foreach ($decoded as $empresaId => $carpeta) {
                if (is_numeric($empresaId) && is_string($carpeta) && $carpeta !== '') {
                    $carpetas[(int) $empresaId] = $carpeta;
                }
            }
        }
    }

    $carpetaUnica = env('ARCA_WSFE_CARPETA_CERT');
    if (is_string($carpetaUnica) && trim($carpetaUnica) !== '') {
        $carpetaUnica = trim($carpetaUnica);
        if ($carpetas === []) {
            $carpetas[1] = $carpetaUnica;
        } else {
            foreach (array_keys($carpetas) as $empresaId) {
                $carpetas[$empresaId] = $carpetaUnica;
            }
        }
    }

    $empresas = [];
    foreach ($carpetas as $empresaId => $carpetaCert) {
        $empresas[(int) $empresaId] = [
            'carpeta_cert' => (string) $carpetaCert,
        ];
    }

    return $empresas;
};

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
     * Mapeo por instalación (EMPRESA en .env). Referencia versionada en Git.
     * En runtime se expone resuelto en la clave empresas.
     */
    'empresas_por_entorno' => [
        'EL BIERZO' => [
            1 => 'bierzo',
        ],
        'AGG' => [
            1 => 'biyemas',
            2 => 'kandiko',
            3 => 'rebisco',
        ],
        'INTERFORMING' => [
            1 => 'interforming',
        ],
        'FRASLE' => [
            1 => 'frasle',
        ],
    ],

    /**
     * empresa_id → carpeta bajo storage/app/arca/wsfe/certs/ para el entorno actual (EMPRESA).
     * Opcional: ARCA_WSFE_CARPETA_CERT o ARCA_WSFE_EMPRESAS_JSON='{"1":"bierzo"}'.
     */
    'empresas' => $resolveArcaWsfeEmpresas(),

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
