<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Vending (Gastronomía y Caja)',

    /**
     * Capturas en public/docs/manual-vending/img/
     * Generar: php artisan manual:capturar-vending-interno
     */
    'capturas' => [
        'flujo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Flujo operativo Vending — Ventas, Anita y Caja',
            'seccion' => '2. Conceptos clave',
        ],
        'maquinas_listado' => [
            'archivo' => 'maquinas-listado.png',
            'titulo' => 'Listado de máquinas vending',
            'seccion' => '4. Máquinas vending — listado',
        ],
        'maquinas_form' => [
            'archivo' => 'maquinas-form.png',
            'titulo' => 'Formulario alta/edición de máquina vending',
            'seccion' => '5. Máquinas vending — alta y edición',
        ],
        'rendicion_ventas_listado' => [
            'archivo' => 'rendicion-ventas-listado.png',
            'titulo' => 'Listado de rendiciones vending (Ventas)',
            'seccion' => '7. Rendiciones Ventas — listado',
        ],
        'rendicion_ventas_form' => [
            'archivo' => 'rendicion-ventas-form.png',
            'titulo' => 'Alta de rendición vending en Ventas',
            'seccion' => '8. Rendiciones Ventas — alta y edición',
        ],
        'rendicion_ventas_comprobante' => [
            'archivo' => 'rendicion-ventas-comprobante.png',
            'titulo' => 'Comprobante PDF rendición Ventas',
            'seccion' => '9. Comprobante PDF — rendición Ventas',
        ],
        'caja_listado' => [
            'archivo' => 'caja-listado.png',
            'titulo' => 'Listado presentaciones vending en Caja',
            'seccion' => '10. Presentación Caja — listado',
        ],
        'caja_form' => [
            'archivo' => 'caja-form.png',
            'titulo' => 'Alta presentación vending en Caja',
            'seccion' => '11. Presentación Caja — alta',
        ],
        'caja_editar' => [
            'archivo' => 'caja-editar.png',
            'titulo' => 'Edición presentación vending en Caja',
            'seccion' => '12. Presentación Caja — edición y anulación',
        ],
        'caja_comprobante' => [
            'archivo' => 'caja-comprobante.png',
            'titulo' => 'Comprobante PDF presentación Caja',
            'seccion' => '13. Comprobante PDF — presentación Caja',
        ],
    ],
];
