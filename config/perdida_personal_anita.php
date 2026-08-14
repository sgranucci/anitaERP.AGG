<?php

return [

    /**
     * Sincronización perdmae (Pérdidas de personal) vía bridge Anita.
     */
    'sistema' => env('PERDIDA_PERSONAL_ANITA_SISTEMA', 'caja'),

    'tabla' => env('PERDIDA_PERSONAL_ANITA_TABLA', 'perdmae'),

    'numerador' => [
        'tabla' => env('PERDIDA_PERSONAL_ANITA_NUMABM_TABLA', 'numabm'),
        'sistema_shared' => env('PERDIDA_PERSONAL_ANITA_NUMABM_SISTEMA', 'shared'),
        'sistema_abm' => env('PERDIDA_PERSONAL_ANITA_NUMABM_SISTEMA_ABM', 'caja'),
        'programa' => env('PERDIDA_PERSONAL_ANITA_NUMABM_PROGRAMA', 'a-perdmae.c'),
        'referencia' => env('PERDIDA_PERSONAL_ANITA_NUMABM_REFERENCIA', '1'),
    ],

];
