<?php

return [

    /*
    | Requisición sala — TM a laboratorio al aprobar el árbol (ítems reparación/devolución).
    | Depósito destino resuelto por código en depmae (ej. 406 = laboratorio).
    */
    'requisicion_deposito_laboratorio_codigo' => env('SALA_REQUISICION_DEPOSITO_LABORATORIO_CODIGO', '406'),

    /** tipotransaccion_stock.id para TM tras aprobación del árbol (default TRA). */
    'requisicion_transferencia_tipotransaccion_id' => (int) env('SALA_REQUISICION_TRANSFERENCIA_TIPOTRANSACCION_ID', 1),

];
