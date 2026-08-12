<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Reportes contables definibles',

    /**
     * Archivos en public/docs/manual-reporte-definible/img/
     * SVG de circuitos y wireframes; reemplazar por PNG capturados cuando se arme el comando de captura.
     */
    'capturas' => [
        'mapa_modulo' => [
            'archivo' => 'mapa-modulo.svg',
            'titulo' => 'Mapa del módulo: catálogo → diseñar → ejecutar → publicar / distribuir / paridad',
            'seccion' => '1. Introducción',
        ],
        'glosario_flujo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Flujo operativo: de la definición al número presentado',
            'seccion' => '2. Conceptos básicos',
        ],
        'fuente_verdad' => [
            'archivo' => 'fuente-verdad.svg',
            'titulo' => 'Fuente de verdad por período (corte ERP / Anita)',
            'seccion' => '3. Fuente de verdad: ERP y Anita',
        ],
        'catalogo' => [
            'archivo' => 'pantalla-catalogo.svg',
            'titulo' => 'Catálogo de informes definibles',
            'seccion' => '4. Catálogo de informes',
        ],
        'disenar_estructura' => [
            'archivo' => 'pantalla-disenar.svg',
            'titulo' => 'Diseñar: árbol de rubros y cuentas',
            'seccion' => '5. Diseñar la estructura',
        ],
        'layouts' => [
            'archivo' => 'circuito-layouts.svg',
            'titulo' => 'Layouts de columnas (Report Painter)',
            'seccion' => '6. Layouts de columnas',
        ],
        'consolidacion' => [
            'archivo' => 'circuito-consolidacion.svg',
            'titulo' => 'Consolidación multiempresa, % y eliminaciones IC',
            'seccion' => '7. Consolidación intercompany',
        ],
        'ejecutar' => [
            'archivo' => 'pantalla-ejecutar.svg',
            'titulo' => 'Pantalla de ejecución con filtros y resultado',
            'seccion' => '8. Ejecutar un informe',
        ],
        'drill' => [
            'archivo' => 'circuito-drill.svg',
            'titulo' => 'Drill-down: rubro → cuentas → asientos → documento',
            'seccion' => '9. Drill-down y mayor',
        ],
        'publicacion' => [
            'archivo' => 'circuito-publicacion.svg',
            'titulo' => 'Publicación inmutable del resultado',
            'seccion' => '10. Publicar el número presentado',
        ],
        'distribucion' => [
            'archivo' => 'circuito-distribucion.svg',
            'titulo' => 'Distribución automática por mail',
            'seccion' => '11. Distribución automática',
        ],
        'notas' => [
            'archivo' => 'circuito-notas.svg',
            'titulo' => 'Notas al pie versionadas',
            'seccion' => '12. Notas al pie',
        ],
        'paridad' => [
            'archivo' => 'circuito-paridad.svg',
            'titulo' => 'Paridad Anita: tres brazos de comparación',
            'seccion' => '13. Paridad Anita',
        ],
        'alertas' => [
            'archivo' => 'circuito-alertas.svg',
            'titulo' => 'Alertas post-corrida y ecuación contable',
            'seccion' => '14. Alertas y validaciones',
        ],
        'ejemplos' => [
            'archivo' => 'ejemplos-practicos.svg',
            'titulo' => 'Ejemplos: Balance, EERR, multi-valuación y envío mensual',
            'seccion' => '16. Ejemplos prácticos',
        ],
    ],
];
