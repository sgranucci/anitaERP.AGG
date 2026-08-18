<?php

return [
    'version' => '1.1',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Contaduría: cierres de rendiciones y asientos',

    /**
     * Archivos en public/docs/manual-cierres-rendiciones/img/
     * Generar capturas reales: php artisan manual:capturar-cierres-rendiciones-interno (si existe)
     */
    'capturas' => [
        'flujo_cierre_rendicion' => [
            'archivo' => 'flujo-cierre-rendicion.svg',
            'titulo' => 'Circuito Caja → presentación → cierre contable → asiento → Anita',
            'seccion' => '2. Circuito general',
        ],
        'cierre_maquina_listado' => [
            'archivo' => 'cierre-maquina-listado.png',
            'titulo' => 'Cierre rendiciones máquinas — listado agrupado por día (turno C)',
            'seccion' => '3. Cierre máquinas: listado',
        ],
        'preview_asiento_maquina' => [
            'archivo' => 'preview-asiento-maquina.png',
            'titulo' => 'Preview del asiento de cierre máquinas antes de ejecutar',
            'seccion' => '4. Cierre máquinas: preview y ejecución',
        ],
        'matriz_cuentas_maquina' => [
            'archivo' => 'matriz-cuentas-maquina.svg',
            'titulo' => 'Matriz origen de datos → cuenta contable (máquinas)',
            'seccion' => '5. Origen de cuentas del asiento máquinas',
        ],
        'cierre_bingo' => [
            'archivo' => 'cierre-bingo.png',
            'titulo' => 'Cierre rendiciones bingo — listado y agrupación diaria',
            'seccion' => '6. Cierre bingo',
        ],
        'preview_bingo' => [
            'archivo' => 'preview-bingo.png',
            'titulo' => 'Preview del asiento BIN de cierre bingo',
            'seccion' => '6. Cierre bingo',
        ],
        'conciliacion_flash' => [
            'archivo' => 'conciliacion-flash.png',
            'titulo' => 'Conciliación Flash: rendiciones vs win_ol_slot / win_ol_rul',
            'seccion' => '9. Controles y conciliación Flash',
        ],
    ],
];
