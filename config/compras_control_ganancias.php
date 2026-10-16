<?php

/**
 * Control de ganancias proveedores (reporte previo a actualización masiva).
 *
 * Completar actividad_retencion_codigo con el mapeo Impuestos:
 *   idActividad AFIP (SUPA) => código de retencionganancia.codigo
 */
return [
    'cache_arca_segundos' => (int) env('COMPRAS_CONTROL_GANANCIAS_CACHE_ARCA', 86400),

    /** Pausa entre consultas al padrón ARCA (ms). */
    'delay_ms_entre_consultas' => (int) env('COMPRAS_CONTROL_GANANCIAS_DELAY_MS', 150),

    'limite_default' => (int) env('COMPRAS_CONTROL_GANANCIAS_LIMITE', 100),

    'limite_max' => (int) env('COMPRAS_CONTROL_GANANCIAS_LIMITE_MAX', 20000),

    /**
     * Nombre esperado de condición IVA para monotributistas proveedores
     * (comparación flexible: contiene, sin acentos/mayúsculas).
     */
    'condicioniva_monotributo_nombre_esperado' => env(
        'COMPRAS_CONTROL_GANANCIAS_MONO_NOMBRE',
        'Monotributo C Proveedores'
    ),

    /**
     * Mapa idActividad AFIP → código retención ganancias del maestro.
     * Ejemplo: 620100 => '78'
     *
     * @var array<int|string, string>
     */
    'actividad_retencion_codigo' => [
        // Completar con el nomenclador de Impuestos.
    ],
];
