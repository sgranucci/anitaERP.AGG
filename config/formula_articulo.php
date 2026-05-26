<?php

return [
    /**
     * SKU de artículo cabecera derivado del código de fórmula Anita (stkcmae.stkcm_formula).
     * Ejemplo: código 365 → V0365 con prefijo V y 4 dígitos.
     */
    'sku_prefijo' => env('FORMULA_ARTICULO_SKU_PREFIJO', 'V'),
    'sku_digitos' => (int) env('FORMULA_ARTICULO_SKU_DIGITOS', 4),

    /**
     * Mostrar el "código" como número visible de la fórmula en el CRUD (listado, modal, título de edición,
     * etiquetas de subfórmula, exportaciones) en lugar del id interno. Las URLs/rutas siguen usando id.
     * Si está activo y la fórmula no tiene código cargado, se muestra "#<id>" como fallback.
     */
    'mostrar_codigo_como_numero' => filter_var(env('FORMULA_ARTICULO_MOSTRAR_CODIGO_COMO_NUMERO', false), FILTER_VALIDATE_BOOLEAN),
];
