<?php

return [

    /**
     * Sincronización maestro impperd (Imputaciones de pérdidas) vía bridge Anita.
     */
    'sistema' => env('IMPUTACION_PERDIDA_ANITA_SISTEMA', 'caja'),

    'tabla' => env('IMPUTACION_PERDIDA_ANITA_TABLA', 'impperd'),

];
