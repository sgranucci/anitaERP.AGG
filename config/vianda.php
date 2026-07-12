<?php

/**
 * Viandas (comedor interno): costo desde lista 5000+mes; venta desde lista de la terminal.
 * Recálculo de costos catálogo: mismo job que gastronomía (gastronomia:actualizar-costo-mensual-catalogo).
 */
return [
    /**
     * Base listas de costo mensual (5000 + mes). Ej. julio → 5007.
     * Por defecto usa la misma base que gastronomía (GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE).
     */
    'costo_lista_base' => (int) env(
        'VIANDA_COSTO_LISTA_BASE',
        env('GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE', 5000)
    ),

    /** Un pedido activo por empleado de vianda y fecha de jornada (consumos anulados no cuentan). */
    'un_pedido_por_dia' => filter_var(env('VIANDA_UN_PEDIDO_POR_DIA', true), FILTER_VALIDATE_BOOLEAN),
];
