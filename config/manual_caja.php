<?php

return [
    'version' => '1.1',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Caja, posición financiera, Flash, máquinas y bingo',

    /**
     * Capturas en public/docs/manual-caja/img/
     * Generar capturas: comando interno de manual (cuando esté disponible).
     */
    'capturas' => [
        'mapa_caja' => [
            'archivo' => 'mapa-caja.svg',
            'titulo' => 'Mapa de módulos del área Caja / tesorería',
            'seccion' => '2. Mapa de módulos',
        ],
        'flujo_datos' => [
            'archivo' => 'flujo-datos.svg',
            'titulo' => 'Flujo diario: Wigos, rendiciones, Flash, posición financiera y cierre contable',
            'seccion' => '3. Flujo de datos diario',
        ],
        'posicion_financiera' => [
            'archivo' => 'posicion-financiera.png',
            'titulo' => 'Posición financiera — consulta mensual por empresa',
            'seccion' => '4. Posición financiera',
        ],
        'flash_form' => [
            'archivo' => 'flash-form.png',
            'titulo' => 'Flash de caja — formulario diario',
            'seccion' => '5. Flash de caja',
        ],
        'flash_origen' => [
            'archivo' => 'flash-origen.png',
            'titulo' => 'Flash — modal origen de total (API origen-total)',
            'seccion' => '6. Flash — origen de cada total',
        ],
        'rendicion_maquina' => [
            'archivo' => 'rendicion-maquina.png',
            'titulo' => 'Rendición de máquinas — carga por turno',
            'seccion' => '7. Rendición de máquinas',
        ],
        'bingo_rendicion' => [
            'archivo' => 'bingo-rendicion.png',
            'titulo' => 'Bingo — carga de rendición en terminal',
            'seccion' => '8. Bingo',
        ],
        'bingo_pozo' => [
            'archivo' => 'bingo-pozo.png',
            'titulo' => 'Bingo — pozo acumulado y presentación en caja',
            'seccion' => '8. Bingo',
        ],
    ],
];
