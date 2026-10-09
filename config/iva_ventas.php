<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reporte IVA Ventas — cuentas de conciliación / auditoría
|--------------------------------------------------------------------------
|
| Rango de cuentas contables que el reporte IVA Ventas (ventas/iva-ventas)
| suma al conciliar contra el mayor del ERP.
|
| Se agregan a las cuentas que ya resuelve IvaVentasConciliacionCuentaSupport
| desde gastronomia_cierre_jornada_config y config/facturacion.php.
|
| Los valores son códigos de cuentacontable.codigo por empresa_id; se resuelven
| al id por empresa al conciliar. Editar acá para cambiar el rango sin tocar
| código (mismo criterio que config/gastronomia.php).
|
| - cuentas_ventas_por_empresa: cuentas de ingreso por ventas (haber = +).
| - cuentas_iva_debito_por_empresa: IVA débito fiscal (haber = +).
| - cuentas_iva_credito_por_empresa: IVA crédito fiscal (debe = −, netea el IVA).
|
*/

$empresaEntorno = strtoupper(trim((string) env('EMPRESA', 'AGG')));
$defaultSalaAgg = $empresaEntorno === 'AGG';

return [
    /*
    |--------------------------------------------------------------------------
    | Features de pantalla / proceso (Bingo, unidades, host).
    | El reporte no consulta el bridge Anita.
    |--------------------------------------------------------------------------
    |
    | En AGG quedan encendidos por defecto. En Ferli / El Bierzo / otros se
    | ocultan checkboxes y cuadros de sala. Override por .env sin redeploy.
    |
    */
    'features' => [
        'bingo_fsl' => filter_var(
            env('IVA_VENTAS_BINGO_FSL', $defaultSalaAgg ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'unidades_negocio' => filter_var(
            env('IVA_VENTAS_UNIDADES_NEGOCIO', $defaultSalaAgg ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'clasificar_por_host' => filter_var(
            env('IVA_VENTAS_CLASIFICAR_HOST', $defaultSalaAgg ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    'conciliacion' => $empresaEntorno === 'CALZADOS FERLI'
        ? [
            // F-VENTAS y L-VENTAS. El IVA débito es 213100001.
            // No sumar 114210001 (IVA crédito fiscal): es de compras y desbalancea el cuadre de ventas.
            'cuentas_ventas_por_empresa' => [
                1 => [411000001, 411000003],
                2 => [411000001, 411000003],
                3 => [411000001, 411000003],
            ],
            'cuentas_iva_debito_por_empresa' => [
                1 => [213100001],
                2 => [213100001],
                3 => [213100001],
            ],
            'cuentas_iva_credito_por_empresa' => [
                1 => [],
                2 => [],
                3 => [],
            ],
        ]
        : [
            'cuentas_ventas_por_empresa' => [
                1 => [413010001, 414010001, 415010003, 414020001],
                2 => [413010001, 414010001, 415010003, 414020001],
                3 => [413010001, 414010001, 415010003, 414020001],
            ],
            'cuentas_iva_debito_por_empresa' => [
                1 => [214010009],
                2 => [214010009],
                3 => [214010009],
            ],
            'cuentas_iva_credito_por_empresa' => [
                1 => [114010011],
                2 => [114010011],
                3 => [114010011],
            ],
        ],

    /*
    |--------------------------------------------------------------------------
    | Unidades de negocio (clasificación por PC/PV de la factura)
    |--------------------------------------------------------------------------
    |
    | La unidad de negocio se deduce de la PC/punto de venta del comprobante:
    |   - Estacionamiento: la venta tiene emisión de estacionamiento.
    |   - Vending: el punto de venta pertenece a una máquina vending (tabla
    |     maquinavending) o está listado en vending_puntoventa_ids.
    |   - Gastronomía: la venta tiene emisión de gastronomía.
    |   - Administración / Otros: el resto.
    |
    */
    'unidades_negocio' => [
        'labels' => [
            'gastronomia' => 'Gastronomía',
            'vending' => 'Máquinas (vending)',
            'estacionamiento' => 'Estacionamiento',
            'otros' => 'Administración',
        ],
        // PVs extra a forzar como vending (por id), además de los de la tabla maquinavending.
        'vending_puntoventa_ids' => [],
    ],
];
