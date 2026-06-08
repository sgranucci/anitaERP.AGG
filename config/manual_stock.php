<?php

return [
    'version' => '1.1',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Recuento de inventario (Stock)',

    /**
     * Archivos en public/docs/manual-stock/img/
     * Generar: php artisan manual:capturar-stock-interno
     *          o python3 docs/manual-stock/capturar_playwright.py
     */
    'capturas' => [
        'recuento_listado' => [
            'archivo' => 'recuento-listado.png',
            'titulo' => 'Listado de recuentos de inventario',
            'seccion' => '3. Listado de recuentos',
        ],
        'recuento_crear' => [
            'archivo' => 'recuento-crear.png',
            'titulo' => 'Alta de recuento — cabecera y líneas',
            'seccion' => '4. Alta y edición del recuento',
        ],
        'recuento_editar' => [
            'archivo' => 'recuento-editar.png',
            'titulo' => 'Edición de recuento con líneas de conteo',
            'seccion' => '4. Alta y edición del recuento',
        ],
        'recuento_ver' => [
            'archivo' => 'recuento-ver.png',
            'titulo' => 'Detalle del recuento e historial de estados',
            'seccion' => '5. Ver detalle del recuento',
        ],
        'recuento_cierre' => [
            'archivo' => 'recuento-opciones-cierre.png',
            'titulo' => 'Panel de cierre — modo de ajuste y botones',
            'seccion' => '6. Cierre de inventario: modos y filosofía',
        ],
        'recuento_movimientos' => [
            'archivo' => 'recuento-movimientos.png',
            'titulo' => 'Consulta de movimientos por artículo y depósito',
            'seccion' => '8. Consulta de movimientos de stock',
        ],
        'recuento_importar' => [
            'archivo' => 'recuento-importar.png',
            'titulo' => 'Importación de líneas desde Excel',
            'seccion' => '7. Herramientas de carga masiva',
        ],
        'circuito_estados' => [
            'archivo' => 'circuito-estados-recuento.svg',
            'titulo' => 'Circuito de estados del recuento',
            'seccion' => '9. Estados y ciclo de vida',
        ],
    ],
];
