<?php

return [
    'version' => '1.6',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Compras',

    /**
     * Archivos en public/docs/manual-compras/img/
     * Generar: php artisan manual:capturar-compras-interno
     *          o python3 docs/manual-compras/capturar_playwright.py
     */
    'capturas' => [
        'login' => [
            'archivo' => 'login.png',
            'titulo' => 'Pantalla de inicio de sesión',
            'seccion' => '2. Acceso al sistema',
        ],
        'proveedor' => [
            'archivo' => 'proveedor-listado.png',
            'titulo' => 'Listado de proveedores',
            'seccion' => '5. Proveedores',
        ],
        'proveedor_edicion' => [
            'archivo' => 'proveedor-edicion.png',
            'titulo' => 'Ficha de edición de proveedor',
            'seccion' => '5. Proveedores',
        ],
        'requisicion' => [
            'archivo' => 'requisicion-listado.png',
            'titulo' => 'Listado de requisiciones',
            'seccion' => '8. Requisiciones de compra',
        ],
        'requisicion_edicion' => [
            'archivo' => 'requisicion-edicion.png',
            'titulo' => 'Formulario de requisición',
            'seccion' => '8. Requisiciones de compra',
        ],
        'presupuesto' => [
            'archivo' => 'presupuestos-tab.png',
            'titulo' => 'Solapa Presupuestos en requisición',
            'seccion' => '9. Presupuestos de requisición',
        ],
        'listaprecio' => [
            'archivo' => 'listaprecio-proveedor.png',
            'titulo' => 'Lista de precio proveedor',
            'seccion' => '7. Listas de precio de proveedor',
        ],
        'ordencompra' => [
            'archivo' => 'ordencompra-listado.png',
            'titulo' => 'Listado de órdenes de compra',
            'seccion' => '10. Órdenes de compra',
        ],
        'ordencompra_edicion' => [
            'archivo' => 'ordencompra-edicion.png',
            'titulo' => 'Formulario de orden de compra',
            'seccion' => '10. Órdenes de compra',
        ],
        'tablas' => [
            'archivo' => 'tablas-maestras.png',
            'titulo' => 'Tablas maestras de Compras',
            'seccion' => '4. Tablas maestras de Compras',
        ],
        'contrato_reporte' => [
            'archivo' => 'contrato-vencimiento.png',
            'titulo' => 'Reporte de contratos y OC abiertas por vencer',
            'seccion' => '13. Avisos y seguimiento de contratos',
        ],
        'circuito' => [
            'archivo' => 'circuito-documental.svg',
            'titulo' => 'Circuito documental de compras',
            'seccion' => '14. Circuito documental integrado',
        ],
    ],
];
