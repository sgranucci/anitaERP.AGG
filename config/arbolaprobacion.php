<?php

// Constantes de arbol de aprobacion
// Base absoluta de enlaces en mails (debe incluir esquema http:// o https://).

return [
    'ip_link' => env('ARBOLAPROBACION_IP_LINK', 'http://10.20.30.210'),

    // Cron: restaura reemplazos con vence_el vencido (día siguiente al último día inclusive).
    'reemplazo_firmante_vencidos' => [
        'hora' => env('ARBOLAPROBACION_REEMPLAZO_VENCIDOS_HORA', '00:05'),
    ],
];
