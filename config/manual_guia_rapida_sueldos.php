<?php

return [
    'version' => '1.1',
    'titulo' => 'Guía rápida',
    'subtitulo' => 'Anita ERP — Liquidación de sueldos y jornales',

    /**
     * Archivos en public/docs/guia-rapida-sueldos/img/
     * Regenerar: php artisan manual:capturar-guia-rapida-sueldos
     */
    'capturas' => [
        'empleado_listado' => [
            'archivo' => 'empleado-listado.png',
            'titulo' => 'Personal → Empleados (workbench)',
            'seccion' => '2. Revisar el legajo',
        ],
        'novedad_listado' => [
            'archivo' => 'novedad-listado.png',
            'titulo' => 'Liquidación → Novedades de liquidación',
            'seccion' => '3. Cargar novedades',
        ],
        'novedad_crear' => [
            'archivo' => 'novedad-crear.png',
            'titulo' => 'Alta manual de una novedad',
            'seccion' => '3. Cargar novedades',
        ],
        'novedad_importar' => [
            'archivo' => 'novedad-importar.png',
            'titulo' => 'Importar novedades (Excel / CSV)',
            'seccion' => '4. Carga masiva de novedades',
        ],
        'liquidacion_listado' => [
            'archivo' => 'liquidacion-listado.png',
            'titulo' => 'Liquidación → Corridas de liquidación',
            'seccion' => '5. Armar y calcular la corrida',
        ],
        'liquidacion_crear' => [
            'archivo' => 'liquidacion-crear.png',
            'titulo' => 'Alta de una corrida',
            'seccion' => '5. Armar y calcular la corrida',
        ],
        'liquidacion_resultado' => [
            'archivo' => 'liquidacion-resultado.png',
            'titulo' => 'Resultado de la corrida (recibos)',
            'seccion' => '6. Revisar recibos y cerrar',
        ],
        'baja_legajo' => [
            'archivo' => 'empleado-listado.png',
            'titulo' => 'Legajo: la baja se confirma en Historia (no elimina el empleado)',
            'seccion' => '7. Baja del empleado y liquidación final',
        ],
        'baja_corrida_final' => [
            'archivo' => 'liquidacion-crear.png',
            'titulo' => 'Corrida tipo Liquidación final (definitiva del egreso)',
            'seccion' => '7. Baja del empleado y liquidación final',
        ],
    ],
];
