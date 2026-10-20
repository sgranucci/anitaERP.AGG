<?php

return [
    'version' => '1.2',
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
        'op_listado_devolver' => [
            'archivo' => 'op-listado-devolver.png',
            'titulo' => 'Listado de órdenes de pago — acción Devolver retenciones',
            'seccion' => '17. Devolución de retenciones (herramienta OP)',
        ],
        'op_modal_devolver' => [
            'archivo' => 'op-modal-devolver.png',
            'titulo' => 'Modal Devolver retenciones — selección, monto y medio',
            'seccion' => '17.b Pantallas del modal y de la edición',
        ],
        'op_editar_devolver' => [
            'archivo' => 'op-editar-devolver.png',
            'titulo' => 'Edición de OP — botón Devolver retenciones',
            'seccion' => '17.b Pantallas del modal y de la edición',
        ],
        'ddjj_impacto' => [
            'archivo' => 'ddjj-impacto.png',
            'titulo' => 'Impacto de la devolución en declaraciones juradas',
            'seccion' => '18. Impacto en declaraciones juradas de impuestos',
        ],
    ],
];
