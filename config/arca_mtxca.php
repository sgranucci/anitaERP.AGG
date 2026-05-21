<?php

/**
 * Facturación electrónica vía WSMTXCA (Codificación de Productos / Factura con Detalle).
 *
 * Rutas separadas de WSFE (config/arca_wsfe.php) y del padrón (config/arca.php).
 * Certificados por empresa_id: storage/app/arca/mtxca/certs/{carpeta}/cert.crt y privada.key
 *
 * CUIT emisor: empresa.nroinscripcion por empresa_id.
 * WSAA service id: wsmtxca
 */

/**
 * @return array<int, array{carpeta_cert: string}>
 */
$resolveArcaMtxcaEmpresas = static function (): array {
    /** @var array<string, array<int, string>> */
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

    $jsonOverride = env('ARCA_MTXCA_EMPRESAS_JSON');
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

    $carpetaUnica = env('ARCA_MTXCA_CARPETA_CERT');
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
    | soap: WSMTXCA nativo (ArcaMtxcaFacturaElectronicaService).
    */
    'transporte' => env('ARCA_MTXCA_TRANSPORTE', 'afip_php'),

    /** Service name para el TRA WSAA (manual MTXCA: wsmtxca) */
    'wsaa_service_id' => 'wsmtxca',

    'mtxca' => [
        'homo' => [
            'wsdl' => 'https://fwshomo.afip.gov.ar/wsmtxca/services/MTXCAService?wsdl',
            'url' => 'https://fwshomo.afip.gov.ar/wsmtxca/services/MTXCAService',
            'wsdl_local' => storage_path('app/arca/mtxca/wsdl/homo/MTXCAService.wsdl'),
        ],
        'prod' => [
            'wsdl' => 'https://serviciosjava.afip.gob.ar/wsmtxca/services/MTXCAService?wsdl',
            'url' => 'https://serviciosjava.afip.gob.ar/wsmtxca/services/MTXCAService',
            'wsdl_local' => storage_path('app/arca/mtxca/wsdl/prod/MTXCAService.wsdl'),
        ],
    ],

    'base_storage' => storage_path('app/arca/mtxca'),

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

    'empresas' => $resolveArcaMtxcaEmpresas(),

    'soap_timeout' => (int) env('ARCA_MTXCA_SOAP_TIMEOUT', 60),

    /**
     * Receptor (mismos criterios RG4444; compartido conceptualmente con WSFE).
     */
    'receptor' => [
        'consumidor_final_umbral_monto' => (float) env(
            'ARCA_MTXCA_RECEPTOR_CF_UMBRAL_MONTO',
            env('ARCA_WSFE_RECEPTOR_CF_UMBRAL_MONTO', 10000000)
        ),
        'consumidor_final_razon_social' => env(
            'ARCA_MTXCA_RECEPTOR_CF_RAZON_SOCIAL',
            env('ARCA_WSFE_RECEPTOR_CF_RAZON_SOCIAL', 'CONSUMIDOR FINAL')
        ),
        'consumidor_final_tipo_documento' => (int) env(
            'ARCA_MTXCA_RECEPTOR_CF_TIPO_DOCUMENTO',
            env('ARCA_WSFE_RECEPTOR_CF_TIPO_DOCUMENTO', 99)
        ),
        'consumidor_final_numero_documento' => env(
            'ARCA_MTXCA_RECEPTOR_CF_NUMERO_DOCUMENTO',
            env('ARCA_WSFE_RECEPTOR_CF_NUMERO_DOCUMENTO', '0')
        ),
        'cliente_erp_interno_id' => env(
            'ARCA_MTXCA_RECEPTOR_CLIENTE_ERP_INTERNO_ID',
            env('ARCA_WSFE_RECEPTOR_CLIENTE_ERP_INTERNO_ID')
        ),
        'consumidor_final_condicion_iva_id' => (int) env(
            'ARCA_MTXCA_RECEPTOR_CF_CONDICION_IVA_ID',
            env('ARCA_WSFE_RECEPTOR_CF_CONDICION_IVA_ID', 3)
        ),
        'identificado_tipo_documento_default' => (int) env(
            'ARCA_MTXCA_RECEPTOR_IDENTIFICADO_TIPO_DOCUMENTO',
            env('ARCA_WSFE_RECEPTOR_IDENTIFICADO_TIPO_DOCUMENTO', 96)
        ),
    ],

    'emision' => [
        'forzar_modo_caea' => filter_var(
            env('ARCA_MTXCA_FORZAR_MODO_CAEA', env('ARCA_WSFE_FORZAR_MODO_CAEA', false)),
            FILTER_VALIDATE_BOOLEAN
        ),
        'reintentar_caea_si_falla_comunicacion' => filter_var(
            env(
                'ARCA_MTXCA_REINTENTAR_CAEA_SI_FALLA_COMUNICACION',
                env('ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION', true)
            ),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
