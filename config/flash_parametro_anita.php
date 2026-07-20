<?php

return [

    /**
     * Importación de parámetros del Flash (budgets e índices estacionales) desde
     * Informix vía bridge Anita. Origen: tablas paramflash e indexflash del legacy l-flash.c.
     */
    'sistema' => env('FLASH_PARAMETRO_ANITA_SISTEMA', 'caja'),

    /** Cabecera mensual de budgets/totales season por empresa y período (YYYYMM). */
    'tabla_parametro' => env('FLASH_PARAMETRO_ANITA_TABLA_PARAMETRO', 'paramflash'),

    /** Índices estacionales diarios por empresa y fecha (YYYYMMDD). */
    'tabla_indice' => env('FLASH_PARAMETRO_ANITA_TABLA_INDICE', 'indexflash'),
];
