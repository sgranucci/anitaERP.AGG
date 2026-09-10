<?php

return [
    /*
     * Numeración OP vía Anita (pago.c):
     * - MultiEmpresa: t_comp O{empresa} → numerador (O1→223, O2→224, O3→225).
     * - Mono: t_comp PAGOPROVEEDOR_ANITA_TCOMP_CLAVE (OPP→205).
     */
    'anita_multiempresa' => filter_var(
        env('PAGOPROVEEDOR_ANITA_MULTIEMPRESA', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    'anita_tcomp_clave' => env('PAGOPROVEEDOR_ANITA_TCOMP_CLAVE', 'OPP'),
    'anita_sistema_tcomp' => env('PAGOPROVEEDOR_ANITA_SISTEMA_TCOMP', 'compras'),
    'anita_sistema_numerador' => env('PAGOPROVEEDOR_ANITA_SISTEMA_NUMERADOR', 'ventas'),
    /** Sistema Anita para retmov / retibrmov / retimov / retsmov. */
    'anita_sistema_retenciones' => env('PAGOPROVEEDOR_ANITA_SISTEMA_RETENCIONES', 'compras'),
    'anita_escritura_habilitada' => filter_var(
        env('PAGOPROVEEDOR_ANITA_ESCRITURA_HABILITADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Período híbrido: al acumular Ganancias (RG 830) lee retmov Anita como
     * respaldo de OPs que no están en pagoproveedor_retencion.
     * Clave completa tipo|letra|sucursal|nro|empresa (Anita graba letra).
     */
    'acumulado_ganancias_anita_respaldo' => filter_var(
        env('PAGOPROVEEDOR_ACUMULADO_GANANCIAS_ANITA_RESPALDO', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'tipocomprobante_default' => 'OPP',
    /** Anita MultiEmpresa (a-movim/pago) usa espacio; no grabar letra A. */
    'letra_default' => ' ',
    'sucursal_default' => 1,

    /**
     * Imagen de firma del agente (pie de certificados de retención de la OP).
     * Izquierda del pie; a la derecha queda "Recibí conforme" del proveedor.
     */
    'firma_agente_retencion' => env(
        'PAGOPROVEEDOR_FIRMA_AGENTE_RETENCION',
        resource_path('firmas/firma_acosta.jpg')
    ),

    /** Modo cotización default al abrir el formulario. */
    'modo_cotizacion_default' => env('PAGOPROVEEDOR_MODO_COTIZACION_DEFAULT', 'factura'),

    /**
     * Cheques propios posdatados: misma cuenta global caja.cheques_diferidos (211010013).
     * Al día → cuentacaja.cuentacontable_id del banco.
     */
    'cheque_propio_usa_diferidos' => filter_var(
        env('PAGOPROVEEDOR_CHEQUE_DIFERIDOS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'auditoria_diaria' => [
        'habilitada' => filter_var(env('PAGOPROVEEDOR_AUDITORIA_ANITA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('PAGOPROVEEDOR_AUDITORIA_ANITA_HORA', '08:45'),
        'usuario_id' => (int) env('PAGOPROVEEDOR_AUDITORIA_ANITA_USUARIO_ID', 1),
        'email' => env('PAGOPROVEEDOR_AUDITORIA_ANITA_EMAIL', env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
        'ventana_dias' => max(1, (int) env('PAGOPROVEEDOR_AUDITORIA_ANITA_VENTANA_DIAS', 7)),
        'auto_reparar' => filter_var(env('PAGOPROVEEDOR_AUDITORIA_ANITA_AUTO_REPARAR', false), FILTER_VALIDATE_BOOLEAN),
        'mail_siempre' => filter_var(env('PAGOPROVEEDOR_AUDITORIA_ANITA_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'mail_si_reparo' => filter_var(env('PAGOPROVEEDOR_AUDITORIA_ANITA_MAIL_SI_REPARO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'numeracion_lock_segundos' => (int) env('PAGOPROVEEDOR_NUMERACION_LOCK', 15),

    /*
     * Certificados de retención (lee_num_tes): clave G/V/T/S{n} o RGP/RIP/RTP/RSP.
     * Si no hay t_comp con esa clave, se usa este mapa empresaAnita → num_clave ventas.
     * Valores actuales del numerador Anita (Biyemas/Kandiko/Rebisco).
     */
    'retencion_num_clave' => [
        'G' => [ // Ganancias
            1 => env('PAGOPROVEEDOR_RET_GAN_EMP1', '331'),
            2 => env('PAGOPROVEEDOR_RET_GAN_EMP2', '332'),
            3 => env('PAGOPROVEEDOR_RET_GAN_EMP3', '333'),
        ],
        'V' => [ // IVA (serie activa Ret.Iva *)
            1 => env('PAGOPROVEEDOR_RET_IVA_EMP1', '353'),
            2 => env('PAGOPROVEEDOR_RET_IVA_EMP2', '354'),
            3 => env('PAGOPROVEEDOR_RET_IVA_EMP3', '355'),
        ],
        'T' => [ // IIBB
            1 => env('PAGOPROVEEDOR_RET_IIBB_EMP1', '343'),
            2 => env('PAGOPROVEEDOR_RET_IIBB_EMP2', '344'),
            3 => env('PAGOPROVEEDOR_RET_IIBB_EMP3', '345'),
        ],
        'S' => [ // SUSS
            1 => env('PAGOPROVEEDOR_RET_SUSS_EMP1', '381'),
            2 => env('PAGOPROVEEDOR_RET_SUSS_EMP2', '382'),
            3 => env('PAGOPROVEEDOR_RET_SUSS_EMP3', '383'),
        ],
    ],
];
