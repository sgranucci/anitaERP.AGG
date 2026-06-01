<?php

return [
	"GRABACION" => "CON_PRECARGA",

    /**
     * Lock de numeración tesorería (cobranza + caja_movimiento sin cobranza_id).
     * Clave: empresa_id + tipotransaccion_caja_id. Serializa gastronomía, cobranzas e ingreso/egreso.
     * Ver .env.example (COBRANZA_NUMERACION_*).
     */
    'numeracion_lock_segundos' => (int) env('COBRANZA_NUMERACION_LOCK_SEGUNDOS', 120),
    'numeracion_lock_espera_segundos' => (int) env('COBRANZA_NUMERACION_LOCK_ESPERA_SEGUNDOS', 90),

    /**
     * tipotransaccion_caja_id que usan numerador secuencial (MAX+1 solo dígitos).
     * Gastronomía (p. ej. id 2) queda fuera: numerotransaccion = B-00008-00807543 desde venta.codigo.
     */
    'tipotransaccion_caja_ids_secuencial' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('COBRANZA_TIPOTRANSACCION_SECUENCIAL_IDS', '1')),
    ))),

    "VALORES_A_DEPOSITAR" => [
            '1' => 111040000,
            '2' => 111040000,
            '3' => 111040000
            ],
    ];
