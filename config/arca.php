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
    | Validación impuestos padrón — ABM clientes (RI / Monotributo)
    |--------------------------------------------------------------------------
    */
    'padron_validacion_cliente' => [
        'habilitado' => filter_var(env('ARCA_PADRON_VALIDAR_CLIENTE', true), FILTER_VALIDATE_BOOLEAN),
        'impuesto_iva_id' => 30,
        'impuesto_monotributo_id' => 20,
        'condicioniva_responsable_inscripto_id' => 1,
        'condicioniva_monotributo_id' => 4,
        'estado_impuesto_activo' => 'AC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validación impuestos padrón — proveedores en comprobante (RI / Monotributo)
    |--------------------------------------------------------------------------
    */
    'padron_validacion_proveedor' => [
        'habilitado' => filter_var(env('ARCA_PADRON_VALIDAR_PROVEEDOR', env('ARCA_PADRON_VALIDAR_CLIENTE', true)), FILTER_VALIDATE_BOOLEAN),
        'impuesto_iva_id' => 30,
        'impuesto_monotributo_id' => 20,
        'condicioniva_responsable_inscripto_id' => 1,
        'condicioniva_monotributo_id' => 4,
        'estado_impuesto_activo' => 'AC',
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

    /*
    |--------------------------------------------------------------------------
    | Puntos de venta AFIP (ABM puntoventa)
    |--------------------------------------------------------------------------
    */
    'ptos_venta' => [
        /** Segundos de caché Laravel tras consulta ARCA exitosa (precarga index + formulario) */
        'cache_ttl' => (int) env('ARCA_PTOS_VENTA_CACHE_TTL', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | CAEA quincenal (arca:solicitar-caea-quincenal)
    |--------------------------------------------------------------------------
    |
    | pedido_automatico: si false, el schedule de las 06:30 no corre (sigue disponible
    | el comando manual y la pantalla Ventas → CAEA ARCA).
    */
    'caea' => [
        'pedido_automatico' => filter_var(env('ARCA_CAEA_PEDIDO_AUTOMATICO', true), FILTER_VALIDATE_BOOLEAN),
        /** Réplica cada CAEA autorizado en Informix (tabla caea) vía bridge Anita */
        'replicar_en_anita' => filter_var(env('ARCA_CAEA_REPLICAR_ANITA', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitor conectividad ARCA (arca:monitorear-conectividad, cada 5 min)
    |--------------------------------------------------------------------------
    |
    | Activa failover CAEA en runtime (storage + cache) sin tocar .env.
    | Probe: FECompUltimoAutorizado (WSFE) o consultarUltimoComprobanteAutorizado (MTXCA).
    |
    | Recomendado en producción: ARCA_MONITOR_EMPRESA_ID, ARCA_MONITOR_PUNTOVENTA_ID
    | (PV CAE de referencia) y ARCA_MONITOR_TIPOTRANSACCION_ID o ARCA_MONITOR_CBTE_TIPO.
    */
    'monitor_conectividad' => [
        'habilitado' => filter_var(env('ARCA_MONITOR_CONECTIVIDAD', true), FILTER_VALIDATE_BOOLEAN),
        'empresa_id' => ($v = (int) env('ARCA_MONITOR_EMPRESA_ID', 0)) > 0 ? $v : null,
        'puntoventa_id' => ($v = (int) env('ARCA_MONITOR_PUNTOVENTA_ID', 0)) > 0 ? $v : null,
        'cbte_tipo' => ($v = (int) env('ARCA_MONITOR_CBTE_TIPO', 0)) > 0 ? $v : null,
        'tipotransaccion_id' => ($v = (int) env('ARCA_MONITOR_TIPOTRANSACCION_ID', 0)) > 0 ? $v : null,
        'fallos_para_activar' => max(1, (int) env('ARCA_MONITOR_FALLOS_PARA_ACTIVAR', 2)),
        'ok_para_desactivar' => max(1, (int) env('ARCA_MONITOR_OK_PARA_DESACTIVAR', 2)),
    ],
];
