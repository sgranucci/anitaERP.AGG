<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Recepción de proveedores y movimientos de stock',

    /**
     * Archivos en public/docs/manual-recepcion-movstock/img/
     * Generar capturas reales: php artisan manual:capturar-recepcion-movstock-interno
     */
    'capturas' => [
        'recepcion_listado' => [
            'archivo' => 'recepcion-listado.png',
            'titulo' => 'Listado de recepciones de proveedores',
            'seccion' => '3. Listado de recepciones',
        ],
        'recepcion_form' => [
            'archivo' => 'recepcion-form.png',
            'titulo' => 'Formulario de recepción — cabecera, ítems y solapas',
            'seccion' => '5. Alta y edición de recepción',
        ],
        'recepcion_modal_oc' => [
            'archivo' => 'recepcion-modal-oc.png',
            'titulo' => 'Modal de órdenes de compra pendientes',
            'seccion' => '5. Alta y edición de recepción',
        ],
        'recepcion_devolucion' => [
            'archivo' => 'recepcion-devolucion.png',
            'titulo' => 'Devolución a proveedor contra recepción confirmada',
            'seccion' => '7. Devolución y anulación',
        ],
        'movimientos_listado' => [
            'archivo' => 'movimientos-listado.png',
            'titulo' => 'Listado unificado de movimientos y transferencias',
            'seccion' => '9. Listado de movimientos de stock',
        ],
        'movimientos_form' => [
            'archivo' => 'movimientos-form.png',
            'titulo' => 'Alta/edición de movimiento — cabecera, ítems y asiento',
            'seccion' => '10. Entradas, salidas y transferencias manuales',
        ],
        'transferencia_pantalla' => [
            'archivo' => 'transferencia-pantalla.png',
            'titulo' => 'Pantalla rápida de transferencia entre depósitos',
            'seccion' => '12. Transferencia de mercadería (pantalla ágil)',
        ],
        'transferencia_pendientes' => [
            'archivo' => 'transferencia-pendientes.png',
            'titulo' => 'Bandeja de transferencias pendientes de aprobación',
            'seccion' => '13. Aprobación y rechazo de transferencias',
        ],
        'flujo_operativo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Relación entre recepción, movimientos y transferencias',
            'seccion' => '2. Conceptos básicos',
        ],
        'circuito_recepcion' => [
            'archivo' => 'circuito-recepcion.svg',
            'titulo' => 'Estados de una recepción de proveedor',
            'seccion' => '6. Confirmación y estados',
        ],
        'circuito_transferencia' => [
            'archivo' => 'circuito-transferencia.svg',
            'titulo' => 'Estados de una transferencia de mercadería',
            'seccion' => '11. Tipos y variantes de transferencia',
        ],
    ],
];
