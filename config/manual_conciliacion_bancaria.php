<?php

return [
    'version' => '2.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Conciliación bancaria premium (Contaduría / Tesorería)',

    /**
     * Archivos en public/docs/manual-conciliacion-bancaria/img/
     * Generar mockups: php artisan manual:generar-mockups conciliacion-bancaria
     */
    'capturas' => [
        'flujo_mensual' => [
            'archivo' => 'flujo-mensual.png',
            'titulo' => 'Circuito mensual premium: corrida → worklist → cierre con evidencia → aprobación',
            'seccion' => '2. Circuito mensual',
        ],
        'conc_index' => [
            'archivo' => 'conc-index.png',
            'titulo' => 'Index: empresa, cuenta de caja, período, carátula y KPI',
            'seccion' => '5. Pantalla principal (index)',
        ],
        'conc_solapas' => [
            'archivo' => 'conc-solapas.png',
            'titulo' => 'Solapas del resultado: cheques, cruce IB, gastos',
            'seccion' => '5. Pantalla principal (index)',
        ],
        'conc_manual' => [
            'archivo' => 'conc-manual.png',
            'titulo' => 'Workspace manual: dos columnas, extracto CAMT/MT940 y evidencia',
            'seccion' => '6. Workspace manual',
        ],
        'conc_multi' => [
            'archivo' => 'conc-multi.png',
            'titulo' => 'Multi-match: totales vivos y diferencia dentro de tolerancia',
            'seccion' => '6. Workspace manual',
        ],
        'conc_cierre' => [
            'archivo' => 'conc-cierre.png',
            'titulo' => 'Cerrar / aprobar con evidencia (SoD)',
            'seccion' => '7. Cierre, aprobación y evidencia',
        ],
        'conc_tablero' => [
            'archivo' => 'conc-tablero.png',
            'titulo' => 'Tablero de avance multi-cuenta / empresa',
            'seccion' => '8. Tablero de avance',
        ],
        'conc_aging' => [
            'archivo' => 'conc-aging.png',
            'titulo' => 'Aging de pendientes + forecast por ventana',
            'seccion' => '9. Aging y forecast',
        ],
        'conc_worklist' => [
            'archivo' => 'conc-worklist.png',
            'titulo' => 'Worklist de excepciones: asignar y resolver',
            'seccion' => '10. Worklist de excepciones',
        ],
        'conc_config' => [
            'archivo' => 'conc-config.png',
            'titulo' => 'Configuración: reglas, mapas y residuales',
            'seccion' => '12. Configuración (sin intervención de sistemas)',
        ],
        'conc_doc_gasto' => [
            'archivo' => 'conc-doc-gasto.png',
            'titulo' => 'Doc. gastos / residuales: cuenta o concepto IVA',
            'seccion' => '12. Configuración (sin intervención de sistemas)',
        ],
        'conc_gastos' => [
            'archivo' => 'conc-gastos.png',
            'titulo' => 'Gastos bancarios y residuales clasificados',
            'seccion' => '11. Gastos bancarios y residuales',
        ],
        'conc_alinear' => [
            'archivo' => 'conc-alinear.png',
            'titulo' => 'Alinear Excel Contaduría (dry-run / aplicar)',
            'seccion' => '5. Pantalla principal (index)',
        ],
    ],
];
