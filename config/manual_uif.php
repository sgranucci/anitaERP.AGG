<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo UIF · Clientes, premios e informes',

    /**
     * Archivos en public/docs/manual-uif/img/
     * Generar capturas reales: php artisan manual:capturar-uif-interno
     */
    'capturas' => [
        'flujo_uif' => [
            'archivo' => 'flujo-uif.svg',
            'titulo' => 'Circuito UIF: alta de cliente → premio → informe mensual → XML',
            'seccion' => '2. Visión del circuito UIF',
        ],
        'roles_uif' => [
            'archivo' => 'roles-uif.svg',
            'titulo' => 'Quién hace qué: cajero, Op-Uif y Enc-Uif',
            'seccion' => '3. Roles y permisos',
        ],
        'clientes_listado' => [
            'archivo' => 'clientes-listado.png',
            'titulo' => 'Listado de clientes UIF con filtros inteligentes',
            'seccion' => '4. Clientes UIF',
        ],
        'clientes_alta' => [
            'archivo' => 'clientes-alta.png',
            'titulo' => 'Alta / edición de cliente UIF (datos personales y cumplimiento)',
            'seccion' => '4. Clientes UIF',
        ],
        'premios_listado' => [
            'archivo' => 'premios-listado.png',
            'titulo' => 'Listado de premios UIF',
            'seccion' => '5. Premios UIF',
        ],
        'informe_consulta' => [
            'archivo' => 'informe-consulta.png',
            'titulo' => 'Consulta del informe de datos por mes (empresa, período, importe)',
            'seccion' => '6. Informe de datos de clientes UIF',
        ],
        'informe_resultado' => [
            'archivo' => 'informe-resultado.png',
            'titulo' => 'Resultado del informe: premios reportables + Excel / PDF / XML',
            'seccion' => '6. Informe de datos de clientes UIF',
        ],
        'congelados_listado' => [
            'archivo' => 'congelados-listado.png',
            'titulo' => 'Clientes congelados UIF',
            'seccion' => '7. Clientes congelados',
        ],
        'conciliacion_wigos' => [
            'archivo' => 'conciliacion-wigos.png',
            'titulo' => 'Conciliación Wigos UIF',
            'seccion' => '8. Conciliación Wigos',
        ],
    ],
];
