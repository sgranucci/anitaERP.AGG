<?php

return [

    /*
    | Cumplimiento de requisiciones de compra → transferencia de mercadería.
    |
    | tipotransaccion_stock.id para la transferencia que genera el cumplimiento.
    | Si la variable está vacía, se resuelve por abreviatura 'TRA' (transferencia
    | normal) en tipotransaccion_stock. Setear el id explícito solo para forzar
    | otro tipo de transacción.
    */
    'requisicion_cumplimiento_tipotransaccion_stock_id' => env('COMPRAS_REQUISICION_CUMPLIMIENTO_TIPOTRANSACCION_ID'),

    /** Abreviatura del tipo de transacción usado como fallback/precarga (transferencia normal). */
    'requisicion_cumplimiento_tipotransaccion_abreviatura' => env('COMPRAS_REQUISICION_CUMPLIMIENTO_TIPOTRANSACCION_ABREVIATURA', 'TRA'),

];
