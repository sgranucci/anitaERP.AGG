<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purga del cache de archivos (store 'file')
    |--------------------------------------------------------------------------
    |
    | El store 'file' de Laravel no tiene recolector de basura: una entrada vencida se
    | borra recién si alguien la vuelve a pedir. Los reportes contables guardan packs de
    | cientos de MB con TTL de 2 a 4 horas, así que lo que nadie vuelve a consultar queda
    | en disco para siempre. Medido el 2026-09-20: 2,1 GB acumulados desde marzo.
    |
    | La ventana tiene que ser mayor al TTL más largo que se guarde en este store (hoy 4 h
    | en MayorConceptoController) para no borrar un pack todavía vigente.
    |
    */

    'horas' => max(1, (int) env('CACHE_ARCHIVOS_PURGA_HORAS', 24)),

];
