<?php

return [
    'nas_raiz' => env('IMPRESION_NAS_RAIZ', '/NAS'),
    'nas_facturas' => env('IMPRESION_NAS_FACTURAS', '/NAS/ComprobantesPDF/Facturas'),
    'nas_remitos' => env('IMPRESION_NAS_REMITOS', '/NAS/ComprobantesPDF/Remitos'),
    'nas_pedidos' => env('IMPRESION_NAS_PEDIDOS', '/NAS/ComprobantesPDF/Pedidos'),
    'cron_hora' => env('IMPRESION_REINTENTO_HORA', '06:20'),
    'cron_max_intentos' => (int) env('IMPRESION_REINTENTO_MAX', 8),
    'script_nas' => env('IMPRESION_NAS_SCRIPT', base_path('bin/archivar-comprobante-nas.sh')),
];
