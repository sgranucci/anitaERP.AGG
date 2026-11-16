<?php

/*
| Jobs de plataforma (Kernel) visibles en el cockpit del Programador — solo lectura.
| No se despachan desde avisos: son mantenimiento / sync. Catálogo estático.
*/

return [
    'jobs' => [
        [
            'comando' => 'avisos:despachar-programados',
            'etiqueta' => 'Despacho avisos de negocio',
            'horario' => 'Cada 15′',
            'grupo' => 'negocio',
        ],
        [
            'comando' => 'padron-iibb-tasa:purge',
            'etiqueta' => 'Purge padrón IIBB tasa',
            'horario' => 'Día 10 · 03:00',
            'grupo' => 'padrones',
        ],
        [
            'comando' => 'padron-iibb-arba:purge',
            'etiqueta' => 'Purge padrón ARBA',
            'horario' => 'Día 10 · 03:05',
            'grupo' => 'padrones',
        ],
        [
            'comando' => 'padron-iibb-caba:purge',
            'etiqueta' => 'Purge padrón CABA',
            'horario' => 'Día 10 · 03:10',
            'grupo' => 'padrones',
        ],
        [
            'comando' => 'padron-iibb:alertar-vencidos',
            'etiqueta' => 'Alertar padrones vencidos',
            'horario' => 'Días 1/3/5/26/28 · 08:00',
            'grupo' => 'padrones',
        ],
        [
            'comando' => 'bitacora-acceso:purge',
            'etiqueta' => 'Purge bitácora acceso',
            'horario' => 'Diario · 03:20',
            'grupo' => 'mantenimiento',
        ],
        [
            'comando' => 'anita-notificacion:purge',
            'etiqueta' => 'Purge notificaciones',
            'horario' => 'Diario · ~03:45',
            'grupo' => 'mantenimiento',
        ],
        [
            'comando' => 'audits:purge',
            'etiqueta' => 'Purge audits (si flag)',
            'horario' => 'Diario · 03:50',
            'grupo' => 'mantenimiento',
        ],
        [
            'comando' => 'cache-archivos:purge',
            'etiqueta' => 'Purge cache archivos',
            'horario' => 'Diario · 04:10',
            'grupo' => 'mantenimiento',
        ],
        [
            'comando' => 'pdf-temporarios:purge',
            'etiqueta' => 'Purge PDF temporales',
            'horario' => 'Diario · 04:25',
            'grupo' => 'mantenimiento',
        ],
        [
            'comando' => 'interbanking:persistir-saldos-diarios',
            'etiqueta' => 'Interbanking saldos',
            'horario' => 'Diario · 07:15',
            'grupo' => 'sync',
        ],
        [
            'comando' => 'interbanking:persistir-movimientos',
            'etiqueta' => 'Interbanking movimientos',
            'horario' => 'Diario · 07:30',
            'grupo' => 'sync',
        ],
        [
            'comando' => 'solicitudpago:sincronizar-anita',
            'etiqueta' => 'Sync solicitudes pago Anita',
            'horario' => 'Diario · ~06:45',
            'grupo' => 'sync',
        ],
        [
            'comando' => 'user-task:sincronizar',
            'etiqueta' => 'Sync user tasks',
            'horario' => 'Cada 10′',
            'grupo' => 'sync',
        ],
        [
            'comando' => 'tracking-facturas:sincronizar-indice',
            'etiqueta' => 'Índice tracking facturas',
            'horario' => 'Diario · 02:40',
            'grupo' => 'sync',
        ],
    ],
];
