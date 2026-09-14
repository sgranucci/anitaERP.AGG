<?php

/**
 * Facturación electrónica de exportación vía WSFEX v1 (RG 2758 / manual ARCA V3.1.1).
 *
 * Certificados por empresa_id: {ARCA_WSFEX_BASE}/certs/{carpeta}/cert.crt y privada.key
 * (misma convención que config/arca_wsfe.php).
 *
 * Manual: https://arca.gob.ar/ws/documentacion/manuales/WSFEX-Manualparaeldesarrollador_V3.1.1_ARCA.pdf
 * WSAA service id: "wsfex"
 *
 * CUIT emisor: empresa.nroinscripcion. No usar ARCA_CUIT_REPRESENTADA (padrón A5).
 */

/**
 * @return array<int, array{carpeta_cert: string}>
 */
$resolveArcaWsfexEmpresas = static function (): array {
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
        'Calzados Ferli' => [
            1 => 'ferli',
            3 => 'ferli',
        ],
    ];

    $empresaInstalacion = trim((string) env('EMPRESA', 'AGG'), " \t\n\r\0\x0B'\"");

    $carpetas = $porEntorno[$empresaInstalacion] ?? [];
    if ($carpetas === [] && $empresaInstalacion !== '') {
        $upper = strtoupper($empresaInstalacion);
        foreach ($porEntorno as $clave => $mapa) {
            if (strtoupper((string) $clave) === $upper) {
                $carpetas = $mapa;
                break;
            }
        }
    }

    $jsonOverride = env('ARCA_WSFEX_EMPRESAS_JSON');
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

    $carpetaUnica = env('ARCA_WSFEX_CARPETA_CERT');
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
    | afip_php: módulo externo certs_arca/afip_exp_v1 + XML (histórico).
    | soap: WSFEX v1 nativo (ArcaWsfexFacturaElectronicaService).
    */
    'transporte' => env('ARCA_WSFEX_TRANSPORTE', 'afip_php'),

    /** Service name para el TRA WSAA (manual WSFEX: "wsfex") */
    'wsaa_service_id' => 'wsfex',

    'wsfex' => [
        'homo' => [
            'wsdl' => 'https://wswhomo.afip.gov.ar/wsfexv1/service.asmx?WSDL',
            'wsdl_local' => storage_path('app/arca/wsfex/wsdl/homo/service.wsdl'),
        ],
        'prod' => [
            'wsdl' => 'https://servicios1.afip.gov.ar/wsfexv1/service.asmx?WSDL',
            'wsdl_local' => storage_path('app/arca/wsfex/wsdl/prod/service.wsdl'),
        ],
    ],

    'base_storage' => env('ARCA_WSFEX_BASE', storage_path('app/arca/wsfex')),

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
        'Calzados Ferli' => [
            1 => 'ferli',
            3 => 'ferli',
        ],
    ],

    'empresas' => $resolveArcaWsfexEmpresas(),

    'soap_timeout' => (int) env('ARCA_WSFEX_SOAP_TIMEOUT', 60),
];
