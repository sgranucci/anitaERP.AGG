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

];
