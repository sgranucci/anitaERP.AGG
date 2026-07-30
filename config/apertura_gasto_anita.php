<?php

return [

    /**
     * Sincronización maestro apgasto (Apertura de gastos) vía bridge Anita.
     */
    'sistema' => env('APERTURA_GASTO_ANITA_SISTEMA', 'caja'),

    'tabla' => env('APERTURA_GASTO_ANITA_TABLA', 'apgasto'),

];
