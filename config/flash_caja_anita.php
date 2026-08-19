<?php

return [

    /**
     * Importación del Flash diario desde Informix (tabla flash / flash.sql)
     * vía bridge Anita hacia flash_caja.
     */
    'sistema' => env('FLASH_CAJA_ANITA_SISTEMA', 'caja'),

    'tabla' => env('FLASH_CAJA_ANITA_TABLA', 'flash'),

    /**
     * Al generar el flash en el ERP, insertar en Anita si no hay registro nativo.
     */
    'escritura_habilitada' => filter_var(env('FLASH_CAJA_ANITA_ESCRITURA', true), FILTER_VALIDATE_BOOLEAN),
];
