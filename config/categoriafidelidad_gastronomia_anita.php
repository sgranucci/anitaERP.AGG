<?php

/**
 * Mapeo Anita → anitaERP para sincronización de categorías de fidelidad gastronomía.
 *
 * Maestros (sin filtro de fecha):
 *   clicat    → categoriafidelidad_gastronomia   (clcat_categoria, clcat_desc)
 *   clicatart → categoriafidelidad_articulo_gastronomia (clcart_categoria, clcart_orden, clcart_articulo)
 *
 * Movimientos (con filtro clcent_fecha >= fecha_desde):
 *   clicatent → categoriafidelidad_entrega_gastronomia
 */
return [
    'tabla_categoria' => 'clicat',
    'tabla_articulo' => 'clicatart',
    'tabla_entrega' => 'clicatent',

    /** Sistema Informix en apiERP.php (null = sin cláusula de sistema). */
    'sistema' => env('CATEGORIA_FIDELIDAD_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    /**
     * Fecha mínima Anita (entero Ymd) para importar entregas clicatent.
     * 20250101 = 1/1/2025.
     */
    'fecha_desde' => (int) env('CATEGORIA_FIDELIDAD_GASTRONOMIA_SYNC_ANITA_FECHA_DESDE', 20250101),

    /**
     * clcent_categoria en clicatent suele venir en 0; categoría ERP por defecto (3 = Platino).
     */
    'entrega_categoria_codigo_default' => (int) env('CATEGORIA_FIDELIDAD_GASTRONOMIA_ENTREGA_CATEGORIA_DEFAULT', 3),

    'campos_categoria' => 'clcat_categoria, clcat_desc',
    'campos_articulo' => 'clcart_categoria, clcart_orden, clcart_articulo',
    'campos_entrega' => 'clcent_documento, clcent_tarjeta, clcent_fecha, clcent_articulo, clcent_tipo, clcent_letra, clcent_sucursal, clcent_nro, clcent_categoria, clcent_apellido, clcent_nombre',
];
