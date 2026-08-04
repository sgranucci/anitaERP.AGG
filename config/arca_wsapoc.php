<?php

$wsapocBase = env('ARCA_WSAPOC_BASE', storage_path('app/arca/wsapoc'));

return [
    /*
    |--------------------------------------------------------------------------
    | WSAPOC — Consulta contribuyentes con facturas apócrifas (GetPublicacionAPOC)
    |--------------------------------------------------------------------------
    |
    | Certificados y TA propios bajo storage/app/arca/wsapoc/ (mismo par que padrón/WSCDC).
    | WSAA service id: wsapoc
    |
    */
    'habilitado' => filter_var(env('ARCA_WSAPOC_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'cuit_representada' => env('ARCA_WSAPOC_CUIT_REPRESENTADA', env('ARCA_WSCDC_CUIT_REPRESENTADA', env('ARCA_CUIT_REPRESENTADA'))),

    'wsaa_service_id' => 'wsapoc',

    'wsapoc' => [
        'homo' => [
            'wsdl' => 'https://eapoc-ws-qaext.afip.gob.ar/Service.asmx?WSDL',
            'wsdl_local' => $wsapocBase.'/wsdl/homo/service.wsdl',
        ],
        'prod' => [
            'wsdl' => 'https://eapoc-ws.afip.gob.ar/service.asmx?WSDL',
            'wsdl_local' => $wsapocBase.'/wsdl/prod/service.wsdl',
        ],
    ],

    'base_storage' => $wsapocBase,
    'cert_path' => env('ARCA_WSAPOC_CERT_PATH', $wsapocBase.'/certs/cert.crt'),
    'private_key_path' => env('ARCA_WSAPOC_PRIVATE_KEY_PATH', $wsapocBase.'/certs/privada.key'),
    'private_key_passphrase' => env('ARCA_WSAPOC_PRIVATE_KEY_PASSPHRASE', env('ARCA_PRIVATE_KEY_PASSPHRASE', '')),
    'ta_storage_dir' => env('ARCA_WSAPOC_TA_STORAGE', $wsapocBase.'/ta'),
    'tmp_dir' => env('ARCA_WSAPOC_TMP_DIR', $wsapocBase.'/tmp'),

    'soap_timeout' => (int) env('ARCA_WSAPOC_SOAP_TIMEOUT', 30),

    // Reintentos ante fallas transitorias de ARCA (transporte o respuesta 200 sin
    // el elemento *Result esperado). Total de intentos = reintentos (>= 1).
    'reintentos' => max(1, (int) env('ARCA_WSAPOC_REINTENTOS', 5)),
    'reintento_pausa_ms' => max(0, (int) env('ARCA_WSAPOC_REINTENTO_PAUSA_MS', 1500)),

    /*
    |--------------------------------------------------------------------------
    | Suspensión automática al detectar publicación APOC
    |--------------------------------------------------------------------------
    */
    'tiposuspension_id' => (int) env('ARCA_WSAPOC_TIPOSUSPENSION_ID', 0),
    'tiposuspension_nombre' => env('ARCA_WSAPOC_TIPOSUSPENSION_NOMBRE', 'Facturas apócrifas (ARCA APOC)'),

    /*
    |--------------------------------------------------------------------------
    | Integraciones (ABM, comprobante, precarga IA, pagos futuros)
    |--------------------------------------------------------------------------
    */
    'validar_proveedor_abm' => filter_var(env('ARCA_WSAPOC_VALIDAR_PROVEEDOR_ABM', true), FILTER_VALIDATE_BOOLEAN),
    'validar_comprobante_proveedor' => filter_var(env('ARCA_WSAPOC_VALIDAR_COMPROBANTE', true), FILTER_VALIDATE_BOOLEAN),
    'validar_precarga_ia' => filter_var(env('ARCA_WSAPOC_VALIDAR_PRECARGA_IA', true), FILTER_VALIDATE_BOOLEAN),
    'validar_cliente_abm' => filter_var(env('ARCA_WSAPOC_VALIDAR_CLIENTE_ABM', true), FILTER_VALIDATE_BOOLEAN),
    'validar_factura_cliente' => filter_var(env('ARCA_WSAPOC_VALIDAR_FACTURA_CLIENTE', true), FILTER_VALIDATE_BOOLEAN),
    'suspender_automatico' => filter_var(env('ARCA_WSAPOC_SUSPENDER_AUTOMATICO', true), FILTER_VALIDATE_BOOLEAN),

    'tiposuspension_cliente_id' => (int) env('ARCA_WSAPOC_TIPOSUSPENSION_CLIENTE_ID', 0),
    'tiposuspension_cliente_nombre' => env('ARCA_WSAPOC_TIPOSUSPENSION_CLIENTE_NOMBRE', 'Facturas apócrifas (ARCA APOC)'),

    /*
    |--------------------------------------------------------------------------
    | Job nocturno
    |--------------------------------------------------------------------------
    |
    | modo novedades (default): una llamada GetAllByPublicacion por rango de fechas
    | y marca/suspende proveedores y clientes cuya CUIT apareció en las novedades.
    | modo completo: recorre proveedores con GetPublicacionAPOC (reconciliación manual).
    |
    */
    'auditoria_nocturna' => [
        'habilitada' => filter_var(env('ARCA_WSAPOC_AUDITORIA_NOCTURNA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('ARCA_WSAPOC_AUDITORIA_HORA', '05:30'),
        'modo' => env('ARCA_WSAPOC_AUDITORIA_MODO', 'novedades'),
        'dias_ventana' => max(1, (int) env('ARCA_WSAPOC_AUDITORIA_DIAS_VENTANA', 2)),
        'pausa_ms' => (int) env('ARCA_WSAPOC_AUDITORIA_PAUSA_MS', 250),
        'solo_activos' => filter_var(env('ARCA_WSAPOC_AUDITORIA_SOLO_ACTIVOS', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail al suspender proveedores o clientes (job novedades / modo completo)
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'habilitado' => filter_var(env('ARCA_WSAPOC_MAIL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'destinatarios' => env('ARCA_WSAPOC_MAIL_DESTINATARIOS', env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_EMAIL', '')),
        'solo_si_suspendidos' => filter_var(env('ARCA_WSAPOC_MAIL_SOLO_SI_SUSPENDIDOS', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
