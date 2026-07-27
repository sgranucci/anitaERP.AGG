<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo Contable · Cierres y aperturas de período',

    /**
     * Archivos en public/docs/manual-contable/img/
     * Generar capturas reales: php artisan manual:capturar-contable-interno
     */
    'capturas' => [
        'flujo_cierre' => [
            'archivo' => 'flujo-cierre.svg',
            'titulo' => 'Visión general: agenda → ejecución → bloqueo por módulo',
            'seccion' => '2. Conceptos básicos',
        ],
        'circuito_bloqueo' => [
            'archivo' => 'circuito-bloqueo.svg',
            'titulo' => 'Cómo se valida una operación contra el cierre',
            'seccion' => '3. Cómo funciona el bloqueo',
        ],
        'cierre_agenda' => [
            'archivo' => 'cierre-agenda.png',
            'titulo' => 'Pantalla de cierre de período — agenda del mes',
            'seccion' => '4. Pantalla de cierre de período',
        ],
        'cierre_herramientas' => [
            'archivo' => 'cierre-herramientas.png',
            'titulo' => 'Barra de herramientas de la agenda (Programar todos / Cerrar todos / Aplicar pendientes)',
            'seccion' => '5. Herramientas de la agenda (detalle)',
        ],
        'cierre_programar_todos' => [
            'archivo' => 'cierre-programar-todos.png',
            'titulo' => 'Modal Programar todos los módulos',
            'seccion' => '6. Paso a paso: programar el mes',
        ],
        'cierre_historico' => [
            'archivo' => 'cierre-historico.png',
            'titulo' => 'Histórico de cierres con columna Módulo',
            'seccion' => '6. Paso a paso: programar el mes',
        ],
        'apertura_listado' => [
            'archivo' => 'apertura-listado.png',
            'titulo' => 'Listado de aperturas programadas',
            'seccion' => '7. Aperturas programadas',
        ],
    ],
];
