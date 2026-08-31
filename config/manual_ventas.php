<?php

return [
    'version' => '1.2',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Pedidos, Facturación y Abonos',

    /**
     * Capturas en public/docs/manual-ventas/img/
     * Generar: php artisan manual:capturar-ventas-interno
     */
    'capturas' => [
        'flujo_pedidos' => [
            'archivo' => 'flujo-pedidos.svg',
            'titulo' => 'Circuito pedido → pesada → factura',
            'seccion' => '3. Circuito completo del pedido',
        ],
        'pedido_listado' => [
            'archivo' => 'pedido-listado.png',
            'titulo' => 'Listado de pedidos de clientes',
            'seccion' => '4. Listado de pedidos en la empresa',
        ],
        'pedido_crear' => [
            'archivo' => 'pedido-crear.png',
            'titulo' => 'Alta de pedido — cabecera e ítems',
            'seccion' => '5. Carga de pedido (vendedores remotos)',
        ],
        'pedido_editar' => [
            'archivo' => 'pedido-editar.png',
            'titulo' => 'Edición del pedido — pesada y facturación',
            'seccion' => '6. Edición, guardado y estados',
        ],
        'pedido_cerrar' => [
            'archivo' => 'pedido-cerrar.png',
            'titulo' => 'Cierre masivo de pedidos',
            'seccion' => '11. Cierre de pedidos y anulaciones',
        ],
    ],
];
