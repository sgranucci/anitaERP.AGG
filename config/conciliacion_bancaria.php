<?php

/**
 * Codificación conciliación bancaria.
 * Fuente: Codificacion bcos.xlsx + mapeo P.C.C. validado contra extracto Macro (cuenta 127).
 */
return [
    'tolerancia_importe' => (float) env('CONCILIACION_BANCARIA_TOLERANCIA', 0.05),

    /** Importe mínimo (abs) de un pendiente para marcar anomalía IA. */
    'anomalia_importe_grande' => (float) env('CONCILIACION_BANCARIA_ANOMALIA_IMPORTE_GRANDE', 50000),

    /** Diferencia de saldo ajustado a partir de la cual se señala anomalía IA. */
    'anomalia_diferencia_saldo' => (float) env('CONCILIACION_BANCARIA_ANOMALIA_DIFERENCIA_SALDO', 1.0),

    'dias_tolerancia_fecha' => (int) env('CONCILIACION_BANCARIA_DIAS_FECHA', 3),

    /** OPP con Ch: vs CH DEP en extracto (cuenta 127: clearing ~5–15 días). */
    'dias_tolerancia_fecha_cheque' => (int) env('CONCILIACION_BANCARIA_DIAS_FECHA_CHEQUE', 30),

    /** OPP/OPA/TRF vs TRF.DATA y similares. */
    'dias_tolerancia_fecha_pago' => (int) env('CONCILIACION_BANCARIA_DIAS_FECHA_PAGO', 7),

    /** Importe único 1:1 (pasada IA candidatos cercanos). */
    'dias_tolerancia_fecha_unico' => (int) env('CONCILIACION_BANCARIA_DIAS_FECHA_UNICO', 15),

    /** Tolerancia al comparar carátula ERP vs Excel Contaduría. */
    'excel_tolerancia_importe' => (float) env('CONCILIACION_BANCARIA_EXCEL_TOLERANCIA', 1.0),

    /** Meses de mayor históricos a incluir (cheques previos a cobertura IB). */
    'historico_lookback_meses' => (int) env('CONCILIACION_BANCARIA_HISTORICO_MESES', 18),

    'memory_limit' => env('CONCILIACION_BANCARIA_MEMORY_LIMIT', '2048M'),

    /** Créditos IB pendientes que entran a carátula si no son CABAL (tope absoluto). */
    'caratula_credito_max_importe' => (float) env('CONCILIACION_BANCARIA_CARATULA_CREDITO_MAX', 45000),

    /**
     * Compatibilidad tipo comprobante mayor → concepto Interbanking (code_description_ib / bank).
     */
    'tipo_comp_conceptos_banco' => [
        'OPP' => ['TRF.DATA', 'CH DEP', 'CH/PAG', 'CHEQUE', 'CHP', 'CHD', 'SUELDO', 'PAGO.REMUN'],
        'OPA' => ['TRF.DATA', 'TRF', 'TRANSF', 'PAGO'],
        'TRF' => ['TRF.DATA', 'TRF', 'TRANSF', 'CRED', 'DEB'],
        'ING' => ['DEP', 'CRED', 'TRANSF', 'TRF.DATA', 'IDEP'],
        'EGR' => ['TRF.DATA', 'CH DEP', 'DEB', 'PAGO'],
        'COB' => ['DEP', 'CRED', 'TRANSF'],
    ],

    /**
     * P.C.C. (grouping_code_ib Interbanking) → código conciliación.
     * 'AC' = acumulado diario (sellos, IVA 10,5 %, intereses acuerdo).
     */
    'pcc_map' => [
        '2 1 1' => 9,
        '2 1 3' => 10,
        '2 2 1' => 10,
        '2 4 1' => 10,
        '2 5 1' => 10,
        '1 3 1' => 10,
        '1 5 1' => 10,
        '3 4 4' => 10,
        '9 9 1' => 10,
        '6 2 1' => 5,
        '6 3 0' => 6,
        '4 4 1' => 7,
        '4 3 1' => 7,
        '4 3 4' => 7,
        '4 2 8' => 7,
        '4 1 0' => 7,
        '6 1 1' => 2,
        '6 1 2' => 2,
        '6 4 1' => 3,
        '6 9 1' => 'AC',
        '6 2 2' => 'AC',
        '5 2 1' => 'AC',
    ],

    /** P.C.C. que siempre producen AC si el concepto es de acumulado diario. */
    'pcc_acumulado' => ['6 9 1', '6 2 2', '5 2 1'],

    /** Conceptos IB que se reportan como AC (referencia 0 en solapa Saldo). */
    'conceptos_acumulado' => [
        'SELL.PROV',
        'IVA.10.5%',
        'INT.S/ACUE',
        'INT.S/DESC',
    ],

    /**
     * P.C.C. ambiguos: se resuelven por concepto/descripción.
     *
     * @var array<string, list<array{codigo: int, descripcion: string, patrones_concepto?: list<string>, patrones_descripcion?: list<string>}>>
     */
    'pcc_ambiguos' => [
        '6 6 5' => [
            [
                'codigo' => 3,
                'descripcion' => 'I BRUTOS RETENCION POR 3 ROS',
                'patrones_concepto' => ['RET.IN.BRU'],
            ],
            [
                'codigo' => 4,
                'descripcion' => 'II.BB. PERCEPCION',
                'patrones_descripcion' => ['PERCEPCION IN', 'PERCIB', 'IIBBPERCEP', 'AGIP'],
            ],
        ],
        '9 8 1' => [
            [
                'codigo' => 10,
                'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS',
                'patrones_descripcion' => ['DBPAGREMU', 'PAGO REMUN', 'PAGREMU'],
            ],
            [
                'codigo' => 7,
                'descripcion' => 'COMISIONES/INTERESES/SELLOS',
                'patrones_descripcion' => ['COM.VAL', 'COMISION VAL', 'COMISION'],
            ],
            [
                'codigo' => 10,
                'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS',
                'patrones_concepto' => ['DEB.INTERN'],
            ],
        ],
    ],

    /**
     * Concepto IB (code_description_ib) → código cuando P.C.C. no alcanza.
     *
     * @var array<string, array{codigo: int|string, descripcion: string}>
     */
    'concepto_map' => [
        'TRF.DATA' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'CRED.INT' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'CR.TITULOS' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'CABAL' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'PAGO.REMUN' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'DEP.JUDICI' => ['codigo' => 10, 'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS'],
        'CH/RECIB48' => ['codigo' => 9, 'descripcion' => 'CHEQUES'],
        'CH DEP 48' => ['codigo' => 9, 'descripcion' => 'CHEQUES'],
        'CH/PAG' => ['codigo' => 9, 'descripcion' => 'CHEQUES'],
        'IVA 21%' => ['codigo' => 5, 'descripcion' => 'IVA AL 21%'],
        'IVA PERCEP' => ['codigo' => 6, 'descripcion' => 'IVA PERCEPCION 3ROS'],
        'COM.TEF DN' => ['codigo' => 7, 'descripcion' => 'COMISIONES/INTERESES/SELLOS'],
        'COM.P.PROV' => ['codigo' => 7, 'descripcion' => 'COMISIONES/INTERESES/SELLOS'],
        'CO.CH.P.CA' => ['codigo' => 7, 'descripcion' => 'COMISIONES/INTERESES/SELLOS'],
        'IMPUESTO AL DEBITO' => ['codigo' => 2, 'descripcion' => 'IMPUESTO AL DEBITO/CREDITO'],
        'IMPUESTO AL CREDITO' => ['codigo' => 2, 'descripcion' => 'IMPUESTO AL DEBITO/CREDITO'],
        'RRSIRCREB' => ['codigo' => 3, 'descripcion' => 'I BRUTOS RETENCION POR 3 ROS'],
        'RET.IN.BRU' => ['codigo' => 3, 'descripcion' => 'I BRUTOS RETENCION POR 3 ROS'],
    ],

    /**
     * Código operación IB → código conciliación (respaldo).
     *
     * @var array<string, int>
     */
    'cod_op_map' => [
        '1' => 9,
        '917' => 9,
        '300' => 10,
        '772' => 10,
        '261' => 10,
        '817' => 5,
        'M42' => 6,
        '308' => 7,
        'N24' => 2,
        'R86' => 2,
        'S69' => 3,
        '896' => 3,
    ],

    /**
     * Código => patrones texto (Codificacion bcos.xlsx — capa final).
     *
     * @var array<int, array{descripcion: string, patrones: list<string>}>
     */
    'codificacion_gastos' => [
        2 => [
            'descripcion' => 'IMPUESTO AL DEBITO/CREDITO',
            'patrones' => ['IMP A LOS CREDITOS', 'IMPUESTO AL DEBITO', 'IMPUESTO AL CREDITO', 'DEBITO MO COMP', 'DEB/CRED'],
        ],
        3 => [
            'descripcion' => 'I BRUTOS RETENCION POR 3 ROS',
            'patrones' => ['I BRUTOS RETENCION', 'RET SIRCREB', 'RRSIRCREB', 'INGRESOS BRUTOS SIRCRE', 'IMP. ING. BRUTOS', 'RET.IN.BRU'],
        ],
        4 => [
            'descripcion' => 'II.BB. PERCEPCION',
            'patrones' => ['II.BB. PERCEPCION', 'IIBBPERCEP', 'IMPUESTO I.BRUTOS - PE', ' AGIP', 'PERCEPCION CABA', 'PERCEPCION BSAS', 'PERCIB'],
        ],
        5 => [
            'descripcion' => 'IVA AL 21%',
            'patrones' => ['IVA 21%', 'IVA Basico 21', 'IVA C. FISCAL'],
        ],
        6 => [
            'descripcion' => 'IVA PERCEPCION 3ROS',
            'patrones' => ['IVA PERCEP', 'IVA PERCEPCION', 'IVA 5%', 'PERCEPCION IVA 3ROS', 'RESOL 3337'],
        ],
        7 => [
            'descripcion' => 'COMISIONES/INTERESES/SELLOS',
            'patrones' => ['COMISION', 'COM.TEF', 'COM.P.PROV', 'CO.CH.P.CA', 'INTERESES ACUERDO', 'GTOS BANC', 'C.MANT.CTA'],
        ],
        8 => [
            'descripcion' => 'RETENCION IVA',
            'patrones' => ['IVA RETENCION', 'RETENCION IVA'],
        ],
        9 => [
            'descripcion' => 'CHEQUES',
            'patrones' => ['CHEQUE', 'CH/RECIB', 'CH DEP', 'CH/PAG', 'CHP', 'CHD'],
        ],
        10 => [
            'descripcion' => 'TRANSFERENCIAS/PAGOS/DEPOSITOS',
            'patrones' => ['TRF.DATA', 'TRFDAT', 'TRANSF', 'DEPOSIT', 'SUELDO', 'PAGO.REMUN', 'CABAL', 'CR.TITULOS', 'CRED.INT', 'SUSFOREMBR', 'RESCATE', 'IDEP'],
        ],
    ],

    'codigo_transferencia_default' => 10,

    'codigo_gasto_default' => 7,
];
