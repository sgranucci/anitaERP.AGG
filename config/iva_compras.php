<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reporte IVA Compras — cuentas de conciliación / auditoría
|--------------------------------------------------------------------------
|
| Cuentas del mayor on-line (asiento_movimiento) para cuadre vs libro IVA compras.
| Solo IVA crédito y percepciones: el “neto” del libro no se confronta con un
| barrido de cuentas de gasto (cada factura imputa a cuentas distintas).
|
| Códigos de cuentacontable.codigo por empresa_id.
|
*/

return [
    'conciliacion' => [
        'cuentas_iva_credito_por_empresa' => [
            // Crédito fiscal + prorrateo + gastro + NC + no utilizado (521130001)
            1 => [114010001, 114010002, 114010006, 114010010, 114010011, 521130001],
            2 => [114010001, 114010002, 114010006, 114010010, 114010011, 521130001],
            3 => [114010001, 114010002, 114010006, 114010010, 114010011, 521130001],
        ],
        'cuentas_perc_iva_por_empresa' => [
            1 => [114010009],
            2 => [114010009],
            3 => [114010009],
        ],
        'cuentas_perc_iibb_por_empresa' => [
            1 => [214010004],
            2 => [214010004],
            3 => [214010004],
        ],
    ],
];
