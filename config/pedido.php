<?php

$base = [
    'imprimir_script' => env('PEDIDO_IMPRIMIR_SCRIPT', base_path('bin/imprimir-pedido.sh')),
    'imprimir_timeout_segundos' => (int) env('PEDIDO_IMPRIMIR_TIMEOUT_SEGUNDOS', 60),
    'imprimir_esperar_job_segundos' => (int) env('PEDIDO_IMPRIMIR_ESPERAR_JOB_SEGUNDOS', 60),
    'imprimir_fallback_habilitado' => filter_var(env('PEDIDO_IMPRIMIR_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
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