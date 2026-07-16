<?php

/**
 * Numerador compartido rendg_nro_oper para gastronomía + estacionamiento (tabla rendgastro).
 * Rango dedicado fuera de máquinas (~600k) y bingo (~700k). Secuencia global entre empresas.
 */
return [

    /**
     * Piso inclusive. Siguiente = max(piso-1, MAX Anita en rango, MAX ERP gastro+estac en rango) + 1.
     */
    'piso' => (int) env(
        'RENDICION_RENDGASTRO_NRO_OPER_PISO',
        env('RENDICION_ESTACIONAMIENTO_NRO_OPER_PISO', 850000),
    ),

    /** Techo exclusivo (0 = sin techo). */
    'techo' => (int) env(
        'RENDICION_RENDGASTRO_NRO_OPER_TECHO',
        env('RENDICION_ESTACIONAMIENTO_NRO_OPER_TECHO', 0),
    ),

    /** Lock al asignar (evita colisión gastro↔estac concurrente). */
    'lock_segundos' => (int) env('RENDICION_RENDGASTRO_NRO_OPER_LOCK_SEGUNDOS', 15),

];
