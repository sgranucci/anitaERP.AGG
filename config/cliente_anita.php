<?php

return [
    /**
     * Tabla Informix de jurisdicciones CM05 / percepción IIBB por CUIT (cliibr).
     * Vacío = no replica filas CM05 a Anita.
     */
    'cm05_tabla' => env('CLIENTE_ANITA_CM05_TABLA', 'cliibr'),
];
