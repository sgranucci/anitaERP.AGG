<?php

return [
    'version' => '1.0',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Solicitudes de pago',

    /**
     * Archivos en public/docs/manual-solicitudpago/img/
     * Generar capturas reales: php artisan manual:capturar-solicitudpago-interno
     */
    'capturas' => [
        'flujo_operativo' => [
            'archivo' => 'flujo-operativo.svg',
            'titulo' => 'Visión general del circuito de solicitudes de pago',
            'seccion' => '2. Conceptos básicos',
        ],
        'circuito_estados' => [
            'archivo' => 'circuito-estados.svg',
            'titulo' => 'Estados de una solicitud de pago',
            'seccion' => '3. Circuito del proceso',
        ],
        'circuito_madre_hija' => [
            'archivo' => 'circuito-madre-hija.svg',
            'titulo' => 'Relación entre SP madre, cuotas e hijas',
            'seccion' => '6. Consulta de madres, hijas y cuotas',
        ],
        'sp_listado' => [
            'archivo' => 'sp-listado.png',
            'titulo' => 'Listado de solicitudes de pago',
            'seccion' => '4. Listado de solicitudes',
        ],
        'sp_filtros' => [
            'archivo' => 'sp-filtros.png',
            'titulo' => 'Panel de filtros del listado (Madre/Hija, estado, fechas)',
            'seccion' => '5. Filtros del listado (detalle)',
        ],
        'sp_modal_familia' => [
            'archivo' => 'sp-modal-familia.png',
            'titulo' => 'Modal de plan / cuotas (madre e hijas)',
            'seccion' => '6. Consulta de madres, hijas y cuotas',
        ],
        'sp_formulario' => [
            'archivo' => 'sp-formulario.png',
            'titulo' => 'Formulario de solicitud — solapas Datos y Cuentas',
            'seccion' => '7. Alta y edición de una SP',
        ],
        'sp_cuotas' => [
            'archivo' => 'sp-cuotas.png',
            'titulo' => 'Solapa Cuotas de una SP madre',
            'seccion' => '8. Plan de cuotas (madre)',
        ],
        'sp_informe' => [
            'archivo' => 'sp-informe.png',
            'titulo' => 'Informe de solicitudes de pago',
            'seccion' => '9. Informe de solicitudes de pago',
        ],
    ],
];
