<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formato numérico por defecto de las exportaciones
    |--------------------------------------------------------------------------
    |
    | Controla cómo se escriben los importes/cantidades en los Excel/CSV del
    | sistema cuando el reporte no fija un formato explícito.
    |
    |   auto  -> El xlsx guarda números reales con máscara neutra (#,##0.00);
    |            Excel/LibreOffice los muestra según la configuración regional
    |            de la PC que abre el archivo (1.234,56 en AR, 1,234.56 en INTL).
    |            Es la opción recomendada: cero configuración por pantalla.
    |   ar    -> Fuerza formato Argentina (1.234,56) como texto.
    |   intl  -> Fuerza formato internacional (1,234.56) como texto.
    |
    | CSV no lleva metadatos de formato: cuando el formato efectivo es "auto",
    | el CSV cae al formato de respaldo definido en "csv_fallback".
    |
    */

    'formato_numero' => env('EXPORT_FORMATO_NUMERO', 'auto'),

    // Formato de respaldo para CSV cuando el formato efectivo es "auto" (ar|intl).
    'csv_fallback' => env('EXPORT_CSV_FALLBACK', 'ar'),

];
