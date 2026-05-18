<?php

return [
    /**
     * SKU de artículo cabecera derivado del código de fórmula Anita (stkcmae.stkcm_formula).
     * Ejemplo: código 365 → V0365 con prefijo V y 4 dígitos.
     */
    'sku_prefijo' => env('FORMULA_ARTICULO_SKU_PREFIJO', 'V'),
    'sku_digitos' => (int) env('FORMULA_ARTICULO_SKU_DIGITOS', 4),
];
