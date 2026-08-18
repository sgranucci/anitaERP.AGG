<?php

return [
    'version' => '1.2',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Stock gastronómico, fórmulas e insumos',

    /**
     * Archivos en public/docs/manual-stock-gastronomia/img/
     * Generar capturas reales: php artisan manual:capturar-stock-gastronomia-interno (cuando exista)
     */
    'capturas' => [
        'flujo_stock_gastro' => [
            'archivo' => 'flujo-stock-gastro.svg',
            'titulo' => 'Flujo: configuración PV → fórmula → factura → movimientos de stock',
            'seccion' => '1. Introducción y roles',
        ],
        'config_depositos' => [
            'archivo' => 'config-depositos.png',
            'titulo' => 'Configuración PV gastronomía — depósito venta e insumos',
            'seccion' => '3. Configuración PV gastronomía — depósitos venta e insumos',
        ],
        'formula_listado' => [
            'archivo' => 'formula-listado.png',
            'titulo' => 'Listado de fórmulas de artículos',
            'seccion' => '4. Armado de fórmulas (stock/formula-articulo)',
        ],
        'formula_edicion' => [
            'archivo' => 'formula-edicion.png',
            'titulo' => 'Edición de fórmula — cabecera, hijos y costos',
            'seccion' => '4. Armado de fórmulas (stock/formula-articulo)',
        ],
        'articulo_proveedor' => [
            'archivo' => 'articulo-proveedor.png',
            'titulo' => 'Artículo — referencias por proveedor y conversión UM compra a UM stock',
            'seccion' => '5. Artículos de compra por proveedor y conversión de unidades',
        ],
        'consumo_factura' => [
            'archivo' => 'consumo-factura.svg',
            'titulo' => 'Descuento al facturar: ítem en depósito venta e insumos en depósito insumos',
            'seccion' => '6. Descuento de stock al facturar en el POS',
        ],
        'tipos_movimiento' => [
            'archivo' => 'tipos-movimiento.svg',
            'titulo' => 'Tipos de movimiento: stock manual, ventas, COM, TRA y recuento',
            'seccion' => '7. Tipos de movimientos de stock',
        ],
        'insumos_reporte' => [
            'archivo' => 'insumos-reporte.png',
            'titulo' => 'Reporte insumos por tipo de artículo',
            'seccion' => '8. Reportes de insumos y artículos vendidos',
        ],
    ],
];
