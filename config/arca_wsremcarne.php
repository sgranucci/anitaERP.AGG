<?php

/**
 * Remito electrónico cárnico (wsremcarne / RemCarneService).
 *
 * Certificados y TA propios bajo storage/app/arca/wsremcarne/ (independiente de
 * padrón, WSFE y WSCDC). En El Bierzo/Surmar el cert es el de remito electrónico
 * (CUIT 30-50515037-2, CN remelec), no el de facturación.
 *
 * WSAA service id: "wsremcarne"
 */

$base = env('ARCA_WSREMCARNE_BASE', storage_path('app/arca/wsremcarne'));

/**
 * @return array<int, array{carpeta_cert: string}>
 */
$resolveEmpresas = static function () use ($base): array {
    $porEntorno = [
        'EL BIERZO' => [
            // Surmar (empresa_id=3): remito cárnico
            3 => 'surmar',
        ],
    ];

    $empresaInstalacion = trim((string) env('EMPRESA', 'AGG'), " \t\n\r\0\x0B'\"");
    $carpetas = $porEntorno[$empresaInstalacion] ?? [];

    $jsonOverride = env('ARCA_WSREMCARNE_EMPRESAS_JSON');
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

    $carpetaUnica = env('ARCA_WSREMCARNE_CARPETA_CERT');
    if (is_string($carpetaUnica) && trim($carpetaUnica) !== '') {
        $carpetaUnica = trim($carpetaUnica);
        if ($carpetas === []) {
            $carpetas[3] = $carpetaUnica;
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
    'habilitado' => filter_var(env('ARCA_WSREMCARNE_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    /** Service name para el TRA WSAA */
    'wsaa_service_id' => 'wsremcarne',

    /**
     * CUIT titular mercadería / representada (Anita hardcodea 30-50515037-2).
     * Vacío → se toma empresa.nroinscripcion de la empresa Surmar.
     */
    'cuit_titular_default' => env('ARCA_WSREMCARNE_CUIT_TITULAR', '30505150372'),

    'wsremcarne' => [
        'homo' => [
            'url' => env(
                'ARCA_WSREMCARNE_HOMO_URL',
                'https://fwshomo.afip.gov.ar/wsremcarne/RemCarneService'
            ),
            'wsdl_local' => $base.'/wsremcarne.wsdl',
        ],
        'prod' => [
            'url' => env(
                'ARCA_WSREMCARNE_PROD_URL',
                'https://serviciosjava.afip.gob.ar/wsremcarne/RemCarneService'
            ),
            'wsdl_local' => $base.'/wsremcarne.wsdl',
        ],
    ],

    'base_storage' => $base,
    'wsdl_path' => env('ARCA_WSREMCARNE_WSDL', $base.'/wsremcarne.wsdl'),
    'ta_storage_dir' => env('ARCA_WSREMCARNE_TA_STORAGE', $base.'/ta'),
    'tmp_dir' => env('ARCA_WSREMCARNE_TMP_DIR', $base.'/tmp'),

    'empresas_por_entorno' => [
        'EL BIERZO' => [
            3 => 'surmar',
        ],
    ],

    'empresas' => $resolveEmpresas(),

    'soap_timeout' => (int) env('ARCA_WSREMCARNE_SOAP_TIMEOUT', 60),

    /** Defaults de negocio (a-certsan.c / remito_electronico.fc) */
    'defaults' => [
        'tipo_comprobante' => 995,
        'categoria_emisor' => (int) env('ARCA_WSREMCARNE_CATEGORIA_EMISOR', 3), // abastecedor
        'tipo_receptor' => env('ARCA_WSREMCARNE_TIPO_RECEPTOR', 'MI'),
        'categoria_receptor' => (int) env('ARCA_WSREMCARNE_CATEGORIA_RECEPTOR', 2), // Otros
        'tipo_movimiento' => env('ARCA_WSREMCARNE_TIPO_MOVIMIENTO', 'ENV'),
        'distancia_km' => (float) env('ARCA_WSREMCARNE_DISTANCIA_KM', 1),
        'punto_emision' => (int) env('ARCA_WSREMCARNE_PUNTO_EMISION', 1),
        /** Código cliente Anita que usa codDomDestino=1 */
        'cliente_domicilio_especial' => env('ARCA_WSREMCARNE_CLIENTE_DOM_ESPECIAL', '000004'),
    ],
];
