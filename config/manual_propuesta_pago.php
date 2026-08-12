<?php

return [
    'version' => '1.1',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Cuentas a pagar / Propuesta de pagos y tesorería AP',

    /**
     * Archivos en public/docs/manual-propuesta-pago/img/
     * PNG reales cuando se implemente captura; SVG de respaldo ya incluidos.
     */
    'capturas' => [
        'flujo_premium' => [
            'archivo' => 'flujo-premium.svg',
            'titulo' => 'Circuito premium: de la deuda al clearing',
            'seccion' => '2. Visión del circuito premium',
        ],
        'config_premium' => [
            'archivo' => 'config-premium.svg',
            'titulo' => 'Configuración Premium / Light por empresa',
            'seccion' => '5. Configuración Premium / Light',
        ],
        'pp_listado' => [
            'archivo' => 'pp-listado.svg',
            'titulo' => 'Listado de propuestas de pagos',
            'seccion' => '6. Listado de propuestas',
        ],
        'pp_crear' => [
            'archivo' => 'pp-crear.svg',
            'titulo' => 'Alta de propuesta y grilla de deuda',
            'seccion' => '7. Armar una propuesta (desde la deuda)',
        ],
        'pp_autorizacion' => [
            'archivo' => 'pp-autorizacion.svg',
            'titulo' => 'Autorización del lote (árbol PP / Light)',
            'seccion' => '8. Autorización de pagos (el corazón premium)',
        ],
        'pp_instrumentos' => [
            'archivo' => 'pp-instrumentos.svg',
            'titulo' => 'Instrumentos y exclusiones post-aprobación',
            'seccion' => '9. Instrumentos y exclusiones post-aprobación',
        ],
        'pp_ejecutar' => [
            'archivo' => 'pp-ejecutar.svg',
            'titulo' => 'Ejecución del lote hacia órdenes de pago',
            'seccion' => '10. Ejecutar el lote → órdenes de pago',
        ],
        'pp_lote_bancario' => [
            'archivo' => 'pp-lote-bancario.svg',
            'titulo' => 'Lote bancario: generar, exportar y marcar enviado',
            'seccion' => '11. Lote bancario y envío al banco',
        ],
        'clearing' => [
            'archivo' => 'clearing.svg',
            'titulo' => 'Workbench de clearing bancario',
            'seccion' => '12. Clearing bancario (OP ↔ Interbanking)',
        ],
        'proyeccion_pagos' => [
            'archivo' => 'proyeccion-pagos.svg',
            'titulo' => 'Proyección de pagos: filtros, tramos y columnas',
            'seccion' => '13. Proyección de pagos (reporte)',
        ],
        'cockpit' => [
            'archivo' => 'cockpit.svg',
            'titulo' => 'Cockpit: KPIs, forecast y grilla SP+IE+PP',
            'seccion' => '14. Cockpit, cash position y forecast',
        ],
        'excepciones' => [
            'archivo' => 'excepciones.svg',
            'titulo' => 'Reabrir, parcial y propuesta delta',
            'seccion' => '15. Excepciones: reabrir, parcial y delta',
        ],
        'auditoria' => [
            'archivo' => 'auditoria.svg',
            'titulo' => 'Pack de auditoría / compliance',
            'seccion' => '16. Auditoría y compliance',
        ],
    ],
];
