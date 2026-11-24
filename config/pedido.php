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
        // Tras el alta de la 01:00: cada 30 min solo pedidos nuevos y pesada faltante (--solo-nuevos).
        'refresco_habilitado' => filter_var(env('PEDIDO_IMPORTAR_ANITA_REFRESCO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'refresco_desde' => env('PEDIDO_IMPORTAR_ANITA_REFRESCO_DESDE', '05:00'),
        'refresco_hasta' => env('PEDIDO_IMPORTAR_ANITA_REFRESCO_HASTA', '18:00'),
    ],
    // Reemplazo l-pedkilo -b (REPARTO_AGRUP+TOTAL por artículo): ventas:distribuir-kilos-pedidos-mail
    'kilos_pedidos_mail' => [
        'habilitado' => filter_var(env('PEDIDO_KILOS_PEDIDOS_MAIL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    ],
    // Reemplazo l-pedrem -b: ventas:distribuir-control-afip-pedidos-mail (sábados 18:00)
    'control_afip_pedidos_mail' => [
        'habilitado' => filter_var(env('PEDIDO_CONTROL_AFIP_PEDIDOS_MAIL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    ],
    // Reemplazo p-controlnc: ventas:distribuir-control-nc-descuento-mail (diario 23:00)
    'control_nc_descuento_mail' => [
        'habilitado' => filter_var(env('PEDIDO_CONTROL_NC_DESCUENTO_MAIL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    ],
    // Robot interno: control Anita↔ERP de batch mails (kilos / NC / stkmov). No va a socios.
    'batch_mails_anita_erp_control' => [
        'habilitado' => filter_var(env('PEDIDO_BATCH_MAILS_ANITA_ERP_CONTROL_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('PEDIDO_BATCH_MAILS_ANITA_ERP_CONTROL_HORA', '23:30'),
        'email' => env('PEDIDO_BATCH_MAILS_ANITA_ERP_CONTROL_EMAIL', 'sergiogranucci@gmail.com'),
    ],
];

if (config('app.empresa') == 'EL BIERZO') {
    return array_merge($base, [
        // Cola CUPS del entrepiso para emitir pedidos (ubicación «Entrepiso (Emilio)»).
        // PedidoImpresoraDefaultSupport la asigna al entrar al index si el usuario no tiene impresora de pedidos.
        'impresora_default' => 'BIE_PS_224',
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