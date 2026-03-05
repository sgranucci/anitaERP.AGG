<?php
// Constantes de configuracion modulo receptivo

return [
	"comisiones" => [
                'CUENTA_CAJA_ID' => 90, 
                'MONEDA_ID' => 1,
                'COTIZACION' => 1,
    ],
    "gastos_a_compensar" => [
                'tipotransaccion_caja_id' => 3
    ],
    "rendicion" => [
                'cuentacaja' => [
                    '$' => 90,
                    'U$S' => 91,
                    'Rea' => 92,
                    'EU' => 93,
                    'GS' => 116
                ],
                'tipotransaccion_caja_ingreso_id' => 2,
                'tipotransaccion_caja_egreso_id' => 3,
                'conceptogasto_egreso_id' => 26,
                'conceptogasto_ingreso_id' => 27
    ]
    ];