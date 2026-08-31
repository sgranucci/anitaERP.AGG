<?php

$base = [
    'imprimir_script' => env('PEDIDO_IMPRIMIR_SCRIPT', base_path('bin/imprimir-pedido.sh')),
    'imprimir_timeout_segundos' => (int) env('PEDIDO_IMPRIMIR_TIMEOUT_SEGUNDOS', 60),
    'imprimir_esperar_job_segundos' => (int) env('PEDIDO_IMPRIMIR_ESPERAR_JOB_SEGUNDOS', 60),
    'imprimir_fallback_habilitado' => filter_var(env('PEDIDO_IMPRIMIR_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
    // Cron El Bierzo: ventas:importar-pedido-anita --ejecutar (fecha entrega del día, todos los repartos)
    'importar_anita_diaria' => [
        'habilitado' => filter_var(env('PEDIDO_IMPORTAR_ANITA_DIARIA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('PEDIDO_IMPORTAR_ANITA_DIARIA_HORA', '01:00'),
        // Tras el alta de la 01:00: refresca pesada (penv_kilos_reales) durante el día.
        'refresco_habilitado' => filter_var(env('PEDIDO_IMPORTAR_ANITA_REFRESCO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'refresco_desde' => env('PEDIDO_IMPORTAR_ANITA_REFRESCO_DESDE', '05:00'),
        'refresco_hasta' => env('PEDIDO_IMPORTAR_ANITA_REFRESCO_HASTA', '18:00'),
    ],
];

if (config('app.empresa') == 'EL BIERZO') {
    return array_merge($base, [
        'impresora_default' => 'BIE_PS_229',
        'variante' => 'bierzo',
    ]);
}

if (strtoupper((string) config('app.empresa')) === 'INTERFORMING') {
    return array_merge($base, [
        'variante' => 'interforming',
    ]);
}

return array_merge($base, [
    'variante' => 'default',
]);