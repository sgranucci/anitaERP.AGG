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
    'anita_escritura_habilitada' => filter_var(
        env('PAGOPROVEEDOR_ANITA_ESCRITURA_HABILITADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'tipocomprobante_default' => 'OPP',
    'letra_default' => 'A',
    'sucursal_default' => 1,

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
