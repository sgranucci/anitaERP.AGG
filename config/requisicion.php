<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filtro por oficina de compras (usuario)
    |--------------------------------------------------------------------------
    |
    | Si es true, el listado y la comprobación de acceso por requisición
    | restringen según oficinacompra_id del usuario, y en estado EN_COMPRAS
    | solo puede intervenir quien coincida en oficina.
    |
    */
    'filtro_oficina_compras_activo' => filter_var(
        env('REQUISICION_FILTRO_OFICINA_COMPRAS_ACTIVO', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
