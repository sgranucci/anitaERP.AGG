<?php
// Constantes de lectura automatica de cotizaciones y moneda default del sistema

return [
    'monedaIdCommand' => 2,
    'usuarioIdCommand' => 1,
    'ID_MONEDA_DEFAULT' => 1,
    'ABREVIATURA_LOCAL' => env('COTIZACION_ABREVIATURA_LOCAL', 'ARS'),
    // Cron cotizacion:leeapi — después de publicación BNA (~10hs). 06:00 tomaba día anterior.
    'hora_command' => env('COTIZACION_CRON_HORA', '11:00'),
];
