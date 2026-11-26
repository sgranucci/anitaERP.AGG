<?php

/**
 * Transferencias de ventas Crown (p-tr-ven / p-tr-pxsol).
 * Los servidores Informix son los de cada sala en Anita. El bridge los alcanza
 * por INFORMIXSERVER (sqlhosts del host Anita).
 */
return [

    /**
     * Salas de p-tr-ven.c. El nombre es el INFORMIXSERVER de sqlhosts.
     *
     * @var list<array{codigo: string, nombre: string, ifx_server: string}>
     */
    'salas_gastronomia' => [
        ['codigo' => 'CIP', 'nombre' => 'Cipolletti', 'ifx_server' => 'cncadmin'],
        ['codigo' => 'ROC', 'nombre' => 'Roca', 'ifx_server' => 'grncadmin'],
        ['codigo' => 'CAT', 'nombre' => 'Catriel', 'ifx_server' => 'cancadmin'],
        ['codigo' => 'CHO', 'nombre' => 'Choele Choel', 'ifx_server' => 'ccncadmin'],
        ['codigo' => 'VIE', 'nombre' => 'Viedma', 'ifx_server' => 'vncadmin'],
        ['codigo' => 'EFA', 'nombre' => 'El Faro', 'ifx_server' => 'efncadmin'],
        ['codigo' => 'LGR', 'nombre' => 'Las Grutas', 'ifx_server' => 'lgncadmin'],
    ],

    /** Tope de días del rango de consulta. */
    'max_dias' => max(1, (int) env('VENTAS_TRANSFERENCIA_CROWN_MAX_DIAS', 31)),

    /**
     * Tope de comprobantes por preview. Cipolletti puede superar 10k en un mes.
     * 0 = sin tope.
     */
    'max_filas' => max(0, (int) env('VENTAS_TRANSFERENCIA_CROWN_MAX_FILAS', 50000)),

    /**
     * Filas a mostrar en la grilla de preview (el resto queda en el token para Generar).
     */
    'preview_filas_pantalla' => max(50, (int) env('VENTAS_TRANSFERENCIA_CROWN_PREVIEW_FILAS', 300)),

    /**
     * Si true, además de venta lee subdiario (segunda llamada Anita / contab).
     * Por defecto false: una sola lectura de venta y asiento con cuentas del PV.
     */
    'leer_subdiario' => filter_var(env('VENTAS_TRANSFERENCIA_CROWN_LEER_SUBDIARIO', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Endpoint canónico de vouchers (trae payment_type y fiscal_point_of_sale).
     * No usar voucher/list/v2 acá: ese sirve para totales de rendición.
     */
    'pxsol_list_url' => env(
        'VENTAS_TRANSFERENCIA_CROWN_PXSOL_LIST_URL',
        'https://gateway-prod.pxsol.com/v2/voucher/list'
    ),

    'pxsol_timeout' => max(10, (int) env('VENTAS_TRANSFERENCIA_CROWN_PXSOL_TIMEOUT', 60)),

    /**
     * Hoteles PXSOL para la transferencia por API.
     * token: Bearer del hotel, leído acá (config) y no con env() en runtime:
     * con config:cache env() devuelve null y el token caía al de otro hotel (401).
     * Cada hotel tiene su propio token; no hay fallback entre hoteles.
     *
     * @var list<array{codigo: string, nombre: string, hotel_id: int, token: string, puntoventas: list<array{codigo: string, nombre: string, fiscal?: int}>}>
     */
    'pxsol_hoteles' => [
        [
            'codigo' => 'CIP',
            'nombre' => 'Cipolletti — Casino del Río',
            'hotel_id' => (int) env('PXSOL_HOTEL_CIP_ID', 23134),
            'token' => (string) env('TESORERIA_CROWN_PXSOL_TOKEN', ''),
            'puntoventas' => [
                [
                    'codigo' => '00131',
                    'nombre' => 'Hotel Cipolletti',
                    'fiscal' => 131,
                ],
            ],
        ],
        [
            'codigo' => 'VIE',
            'nombre' => 'Viedma — Casino del Río',
            'hotel_id' => (int) env('PXSOL_HOTEL_VIE_ID', 18731),
            'token' => (string) env('PXSOL_HOTEL_VIE_TOKEN', ''),
            'puntoventas' => [
                [
                    'codigo' => '00127',
                    'nombre' => 'Hotel Viedma',
                    'fiscal' => 127,
                ],
            ],
        ],
        [
            'codigo' => 'GRO',
            'nombre' => 'General Roca — Casino del Río',
            'hotel_id' => (int) env('PXSOL_HOTEL_GRO_ID', 23135),
            'token' => (string) env('PXSOL_HOTEL_GRO_TOKEN', ''),
            'puntoventas' => [
                [
                    'codigo' => '00130',
                    'nombre' => 'Hotel Gral Roca',
                    'fiscal' => 130,
                ],
            ],
        ],
        [
            'codigo' => 'LGR',
            'nombre' => 'Las Grutas — Casino del Río',
            'hotel_id' => (int) env('PXSOL_HOTEL_LGR_ID', 22084),
            'token' => (string) env('PXSOL_HOTEL_LGR_TOKEN', ''),
            'puntoventas' => [
                [
                    'codigo' => '00128',
                    'nombre' => 'Fact. electrónica LGR PXSOL',
                    'fiscal' => 128,
                ],
            ],
        ],
    ],

];
