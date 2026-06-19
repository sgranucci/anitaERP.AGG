<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Canjes Marketing (Gastronomía)',

    /**
     * Capturas en public/docs/manual-canjes-marketing/img/
     * Generar: php artisan manual:capturar-canjes-marketing-interno
     */
    'capturas' => [
        'flujo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Flujo operativo canjes marketing vs gastronomía',
            'seccion' => '2. Gastronomía vs Canjes Marketing',
        ],
        'cliente_vip_listado' => [
            'archivo' => 'cliente-vip-listado.png',
            'titulo' => 'Listado de clientes VIP',
            'seccion' => '4. Clientes VIP — padrón y búsqueda',
        ],
        'cliente_vip_crear' => [
            'archivo' => 'cliente-vip-crear.png',
            'titulo' => 'Alta de cliente VIP',
            'seccion' => '4. Clientes VIP — padrón y búsqueda',
        ],
        'facturador_login' => [
            'archivo' => 'facturador-canjes-login.png',
            'titulo' => 'Facturador canjes — ingreso de mozo',
            'seccion' => '5. Facturador canjes marketing',
        ],
        'facturador_pantalla' => [
            'archivo' => 'facturador-canjes-pantalla.png',
            'titulo' => 'Facturador canjes — carga de artículos y cliente VIP',
            'seccion' => '6. Login mozo, cuentas y carga de artículos',
        ],
        'listado_marketing' => [
            'archivo' => 'listado-marketing.png',
            'titulo' => 'Listado de canjes marketing',
            'seccion' => '10. Listado canjes marketing',
        ],
    ],
];
