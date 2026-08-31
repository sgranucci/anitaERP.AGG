<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Sueldos · Libro de Sueldos Digital (ARCA)',

    /**
     * Archivos en public/docs/manual-lsd-sueldos/img/
     */
    'capturas' => [
        'lsd_workbench' => [
            'archivo' => 'lsd-workbench.png',
            'titulo' => 'Libro de Sueldos Digital: cobertura, circuito del período y generación del TXT',
            'seccion' => '3. Circuito del mes (paso a paso)',
        ],
        'concepto_sueldo' => [
            'archivo' => 'concepto-sueldo.png',
            'titulo' => 'Alta / edición de concepto: código AFIP, flags y bases del registro 04',
            'seccion' => '4. Cómo crear y mapear un concepto',
        ],
        'lsd_cobertura' => [
            'archivo' => 'lsd-cobertura.png',
            'titulo' => 'Cobertura LSD: conceptos exportables que todavía no tienen código AFIP',
            'seccion' => '4. Cómo crear y mapear un concepto',
        ],
        'concepto_1002' => [
            'archivo' => 'concepto-1002.png',
            'titulo' => 'Concepto 1002 — Base no imponible: fórmula detraccion() e informativo',
            'seccion' => '5. Detracción Ley 27.430 (reemplazo del 1002)',
        ],
        'parametro_detraccion' => [
            'archivo' => 'parametro-detraccion.png',
            'titulo' => 'Parámetro DETRACCION_LEY_27430: monto mensual vigente',
            'seccion' => '5. Detracción Ley 27.430 (reemplazo del 1002)',
        ],
        'parametro_tope' => [
            'archivo' => 'parametro-tope.png',
            'titulo' => 'Parámetro TOPE_SIPA: tope de la base imponible jubilatoria',
            'seccion' => '6. Tope SIPA y mínimo imponible',
        ],
        'lsd_ver' => [
            'archivo' => 'lsd-ver.png',
            'titulo' => 'Detalle de una presentación: TXT, estados y registros 01 a 06',
            'seccion' => '7. Generar el TXT e importarlo en ARCA',
        ],
    ],
];
