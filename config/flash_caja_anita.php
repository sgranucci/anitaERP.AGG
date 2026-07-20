<?php

return [

    /**
     * Importación del Flash diario desde Informix (tabla flash / flash.sql)
     * vía bridge Anita hacia flash_caja.
     */
    'sistema' => env('FLASH_CAJA_ANITA_SISTEMA', 'caja'),

    'tabla' => env('FLASH_CAJA_ANITA_TABLA', 'flash'),
];
