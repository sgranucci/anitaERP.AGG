<?php

return [
    'version' => '1.2',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Compras',

    /** Archivos PNG en public/docs/manual-compras/img/ (generar con manual:capturar-compras) */
    'capturas' => [
        'login' => ['archivo' => 'login.png', 'titulo' => 'Pantalla de inicio de sesión', 'seccion' => '2. Acceso al sistema'],
        'proveedor' => ['archivo' => 'proveedor-listado.png', 'titulo' => 'Listado de proveedores', 'seccion' => '5. Proveedores'],
        'requisicion' => ['archivo' => 'requisicion-listado.png', 'titulo' => 'Listado de requisiciones', 'seccion' => '8. Requisiciones de compra'],
        'presupuesto' => ['archivo' => 'presupuestos-tab.png', 'titulo' => 'Presupuestos en requisición', 'seccion' => '9. Presupuestos de requisición'],
        'listaprecio' => ['archivo' => 'listaprecio-proveedor.png', 'titulo' => 'Lista de precio proveedor', 'seccion' => '7. Listas de precio de proveedor'],
        'ordencompra' => ['archivo' => 'ordencompra-listado.png', 'titulo' => 'Listado de órdenes de compra', 'seccion' => '10. Órdenes de compra'],
        'tablas' => ['archivo' => 'tablas-maestras.png', 'titulo' => 'Tablas maestras de Compras', 'seccion' => '4. Tablas maestras de Compras'],
        'circuito' => ['archivo' => 'circuito-documental.svg', 'titulo' => 'Circuito documental de compras', 'seccion' => '11. Circuito documental integrado'],
    ],
];
