<?php

return [
    'version' => '1.3',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Gastronomía',

    /**
     * Capturas en public/docs/manual-gastronomia/img/
     * Generar: php artisan manual:capturar-gastronomia-interno
     */
    'capturas' => [
        'flujo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Diagrama del flujo operativo diario',
            'seccion' => '3. Flujo mínimo de trabajo diario',
        ],
        'jornada' => [
            'archivo' => 'jornada.png',
            'titulo' => 'Apertura y cierre de jornada',
            'seccion' => '4. Apertura y cierre de jornada',
        ],
        'habilitacion_turno' => [
            'archivo' => 'habilitacion-turno.png',
            'titulo' => 'Habilitación de turno gastronomía',
            'seccion' => '5. Habilitación de turno y cierres',
        ],
        'huecos_arca_recuperables' => [
            'archivo' => 'huecos-arca-recuperables.png',
            'titulo' => 'Modal de cierre: lote recuperable y NC consolidada',
            'seccion' => '5. Habilitación de turno y cierres',
        ],
        'huecos_arca_sin_conexion' => [
            'archivo' => 'huecos-arca-sin-conexion.png',
            'titulo' => 'Modal de cierre: ARCA sin conexión y cierre no bloqueado',
            'seccion' => '5. Habilitación de turno y cierres',
        ],
        'proceso_facturacion' => [
            'archivo' => 'proceso-facturacion.png',
            'titulo' => 'Proceso de facturación (POS)',
            'seccion' => '6. Proceso de facturación (POS)',
        ],
        'facturas_dia' => [
            'archivo' => 'facturas-dia.png',
            'titulo' => 'Facturas del día',
            'seccion' => '9. Facturas del día',
        ],
        'cierres_turno' => [
            'archivo' => 'cierres-turno.png',
            'titulo' => 'Consulta de cierres de turno',
            'seccion' => '10. Cierres de turno (consulta)',
        ],
        'informe_gerente' => [
            'archivo' => 'informe-gerente.png',
            'titulo' => 'Informe gerente',
            'seccion' => '11. Informe gerente',
        ],
        'articulos_vendidos' => [
            'archivo' => 'articulos-vendidos.png',
            'titulo' => 'Artículos vendidos',
            'seccion' => '12. Artículos vendidos',
        ],
        'saneamiento_turno' => [
            'archivo' => 'saneamiento-turno.png',
            'titulo' => 'Saneamiento de turnos',
            'seccion' => '8. Cuentas pendientes y saneamiento',
        ],
        'configuracion_pv' => [
            'archivo' => 'configuracion-pv.png',
            'titulo' => 'Configuración punto de venta gastronomía',
            'seccion' => '14. Configuración previa (resumen)',
        ],
        'totem_waitry' => [
            'archivo' => 'totem-waitry.png',
            'titulo' => 'Tótems Waitry — configuración (AGG)',
            'seccion' => '13. Waitry y canjes Wigos (AGG)',
        ],
    ],
];
