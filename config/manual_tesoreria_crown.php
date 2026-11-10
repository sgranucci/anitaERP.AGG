<?php

return [
    'version' => '1.3',
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Tesorería Crown (solo CROWN · capturas reales Chromium · ERP-first · PIN / avisos / auditor)',

    /**
     * Capturas en public/docs/manual-tesoreria-crown/img/
     * Reales: php artisan manual:capturar-tesoreria-crown
     * Mockup solo circuito: php artisan manual:generar-mockups tesoreria-crown
     */
    'capturas' => [
        'circuito_diario' => [
            'archivo' => 'circuito-diario.png',
            'titulo' => 'Circuito diario de Tesorería Crown — de la apertura al cierre de jornada',
            'seccion' => '3. Circuito diario de punta a punta',
        ],
        'jornada_listado' => [
            'archivo' => 'jornada-listado.png',
            'titulo' => 'Captura real — Jornadas (CIP): workbench, chips de sala y export',
            'seccion' => '4. Apertura de jornada y asignación de cajas',
        ],
        'jornada_escritorio' => [
            'archivo' => 'jornada-escritorio.png',
            'titulo' => 'Escritorio de la jornada — cajeros, mesas, bóveda, slots, anexos',
            'seccion' => '4. Apertura de jornada y asignación de cajas',
        ],
        'habilitacion_firma' => [
            'archivo' => 'habilitacion-firma.png',
            'titulo' => 'Habilitación — pedir firmas (tesorería entrega / cajero recibe)',
            'seccion' => '5. Habilitaciones y circuito de firmas',
        ],
        'rendicion_firma' => [
            'archivo' => 'rendicion-firma.png',
            'titulo' => 'Rendición de cajero — firmas (cajero entrega / tesorería recibe)',
            'seccion' => '6. Rendiciones de cajeros con firma',
        ],
        'rendicion_externa' => [
            'archivo' => 'rendicion-externa.png',
            'titulo' => 'Rendición hotel/gastronomía — cierres fiscales y Traer de PXSol',
            'seccion' => '7. Rendiciones externas (hotel, gastronomía, imports)',
        ],
        'cierre_caja' => [
            'archivo' => 'cierre-caja.png',
            'titulo' => 'Cierre de caja — diferencia habilitación vs rendición',
            'seccion' => '8. Cierres parciales de caja',
        ],
        'mesas_propina' => [
            'archivo' => 'mesas-propina.png',
            'titulo' => 'Mesas — estado final, drop y propina',
            'seccion' => '9. Mesas, drops y propinas',
        ],
        'slots_softcount' => [
            'archivo' => 'slots-softcount.png',
            'titulo' => 'Slots — drop, soft-count / contadora y CashBox a bóveda',
            'seccion' => '10. Slots, soft-count y CashBox',
        ],
        'boveda_stock' => [
            'archivo' => 'boveda-stock.png',
            'titulo' => 'Bóveda — movimientos INGR / HAB / REN y saldo',
            'seccion' => '11. Bóveda y stocks de valores',
        ],
        'poder_publico' => [
            'archivo' => 'poder-publico.png',
            'titulo' => 'Poder del público — fichas y slot del día',
            'seccion' => '12. Poder del público (fichas y slot)',
        ],
        'cotizacion' => [
            'archivo' => 'cotizacion.png',
            'titulo' => 'Cotizaciones Crown — venta del día para USD/EUR',
            'seccion' => '13. Cotizaciones, monedas y variables',
        ],
        'kardex_valores' => [
            'archivo' => 'kardex-valores.png',
            'titulo' => 'Kardex de valores — ledger unificado bóveda / pre-depósito / caja chica',
            'seccion' => '14. Kardex y ledger de valores',
        ],
        'anexo1' => [
            'archivo' => 'anexo1.png',
            'titulo' => 'Anexo I — ingresos, egresos, variación y caja final',
            'seccion' => '15. Anexos — fórmulas y lectura',
        ],
        'anexo8' => [
            'archivo' => 'anexo8.png',
            'titulo' => 'Anexo VIII — estado de bóveda (caja final del día)',
            'seccion' => '15. Anexos — fórmulas y lectura',
        ],
        'flash_crown' => [
            'archivo' => 'flash-crown.png',
            'titulo' => 'Flash Crown — resumen gerencial del día por sala',
            'seccion' => '18. Flash Crown',
        ],
    ],
];
