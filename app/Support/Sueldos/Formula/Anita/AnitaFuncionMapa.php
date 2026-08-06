<?php

namespace App\Support\Sueldos\Formula\Anita;

/**
 * Diccionario de traducción Anita (parser legacy de sueldos) → motor de fórmulas
 * del ERP (App\Support\Sueldos\Formula).
 *
 * NO ejecuta nada: solo describe cómo mapear nombres de funciones y variables.
 * La lógica de traducción vive en AnitaFormulaTraductor.
 *
 * Referencia Anita: parser.fc / p-liquidacion.c / a-concepto.c.
 */
class AnitaFuncionMapa
{
    /**
     * Funciones Anita → ERP.
     *
     * Estructura por clave (nombre Anita en MAYÚSCULAS):
     *   erp     => nombre de función ERP a emitir (o null si se resuelve especial)
     *   exacto  => true si la semántica coincide 1:1 con el ERP
     *   especial=> clave de manejo especial en el traductor (B, REDON, SQR)
     *   nota    => aclaración para el reporte
     *
     * @var array<string, array{erp: ?string, exacto: bool, especial?: string, nota?: string}>
     */
    public const FUNCIONES = [
        // --- Coinciden 1:1 con builtins / dominio del ERP ---
        'IF' => ['erp' => 'si', 'exacto' => true],
        'ROUND' => ['erp' => 'redondear', 'exacto' => true],
        'ABS' => ['erp' => 'abs', 'exacto' => true],
        'SQRT' => ['erp' => 'raiz', 'exacto' => true],
        'TRUNC' => ['erp' => 'truncar', 'exacto' => true],
        // im(n): importe del concepto n en la corrida (numérico, igual que Anita).
        'IM' => ['erp' => 'im', 'exacto' => true],

        // --- Requieren función de dominio nueva en el ERP (aprox / a implementar) ---
        'IR' => ['erp' => 'im_rango', 'exacto' => true, 'nota' => 'suma de importes de conceptos en rango'],
        'V' => ['erp' => 'novedad', 'exacto' => true, 'nota' => 'valor1 de novedad_sueldos del concepto (corrida/período)'],
        'VR' => ['erp' => 'novedad_rango', 'exacto' => true, 'nota' => 'suma de valor1 de novedades en rango de conceptos'],
        'P' => ['erp' => 'novedad2', 'exacto' => true, 'nota' => 'valor2 de novedad_sueldos del concepto'],
        'F' => ['erp' => 'factor', 'exacto' => true, 'nota' => 'factor (hab_factor) del concepto'],
        'ANT' => ['erp' => 'antiguedad_tabla', 'exacto' => false, 'nota' => '% de antigüedad por tabla (distinto de antiguedad())'],
        'DIAS' => ['erp' => 'dias_del_mes', 'exacto' => false, 'nota' => 'días del mes indicado'],
        'REDON' => ['erp' => null, 'exacto' => false, 'especial' => 'REDON', 'nota' => 'redondeo final por tabla; se aproxima a redondear(x, 2)'],
        'SQR' => ['erp' => null, 'exacto' => true, 'especial' => 'SQR', 'nota' => 'x^2'],
        'B' => ['erp' => null, 'exacto' => false, 'especial' => 'B', 'nota' => 'base de cálculo B(n)'],

        // --- Dominio implementado como stub/aprox en ContextoLiquidacion ---
        'IL' => ['erp' => 'im_liquidacion', 'exacto' => false, 'nota' => 'importe en otra liquidación / retroactivo'],
        'IC' => ['erp' => 'novedad_periodo', 'exacto' => true, 'nota' => 'valor1 de novedad por período YYYYMM'],
        // VC(concepto, mesesAtras|periodo): novedad histórica (parser.fc VC)
        'VC' => ['erp' => 'novedad_hist', 'exacto' => true, 'nota' => 'novedad valor1: meses atrás o período YYYYMM'],
        'VL' => ['erp' => 'valor_liquidacion', 'exacto' => false, 'nota' => 'valor en otra liquidación'],
        'CL' => ['erp' => 'cantidad_liquidacion', 'exacto' => false, 'nota' => 'cantidad en otra liquidación'],
        'VE' => ['erp' => 'novedad_empresa', 'exacto' => false, 'nota' => 'novedad de otra empresa'],
        'PE' => ['erp' => 'novedad2_empresa', 'exacto' => false, 'nota' => 'novedad 2 de otra empresa'],
        'IME' => ['erp' => 'im_empresa', 'exacto' => false, 'nota' => 'importe de otra empresa'],
        'A' => ['erp' => 'aux_rango', 'exacto' => false, 'nota' => 'suma de auxiliares en rango'],
        'SUM' => ['erp' => 'acum_periodos', 'exacto' => false, 'nota' => 'acumulador histórico por cantidad de períodos'],
        'SUMV' => ['erp' => 'acum_variable_periodos', 'exacto' => false, 'nota' => 'acumulador variable histórico'],
        'AP' => ['erp' => 'acum_anio', 'exacto' => false, 'nota' => 'acumulador por año calendario'],
        'MMES' => ['erp' => 'mejor_mes_acum', 'exacto' => false, 'nota' => 'mejor mes del acumulador'],
        'ACCA' => ['erp' => 'acum_cantidad_concepto', 'exacto' => false, 'nota' => 'acumulado de cantidades del concepto actual'],
        'ACVA' => ['erp' => 'acum_valor_concepto', 'exacto' => false, 'nota' => 'acumulado de valores del concepto actual'],
        'ACIM' => ['erp' => 'acum_importe_concepto', 'exacto' => false, 'nota' => 'acumulado de importes del concepto actual'],
        'ACCIM' => ['erp' => 'acum_importe_por_concepto', 'exacto' => false, 'nota' => 'acumulado de importes por concepto'],
        'AGUI' => ['erp' => 'aguinaldo', 'exacto' => false, 'nota' => 'cálculo de aguinaldo'],
        'CANTVAC' => ['erp' => 'cantidad_vacaciones', 'exacto' => false, 'nota' => 'días de vacaciones'],
        'DVAC' => ['erp' => 'dias_vacaciones', 'exacto' => false, 'nota' => 'días de vacaciones por tope'],
        'TVAC' => ['erp' => 'total_vacaciones', 'exacto' => false, 'nota' => 'total vacaciones'],
        'CANTAS' => ['erp' => 'cantidad_asignacion', 'exacto' => false, 'nota' => 'cantidad de asignación familiar'],
        'IMPAS' => ['erp' => 'importe_asignacion', 'exacto' => false, 'nota' => 'importe de asignación familiar'],
        'DTRAB' => ['erp' => 'dias_trabajados', 'exacto' => false, 'nota' => 'días trabajados'],
        'DNOTRAB' => ['erp' => 'dias_no_trabajados', 'exacto' => false, 'nota' => 'días no trabajados'],
        'MT' => ['erp' => 'meses_trabajados', 'exacto' => false, 'nota' => 'meses trabajados'],
        'TE' => ['erp' => 'tabla_empleado', 'exacto' => false, 'nota' => 'valor de tabla del empleado'],
        'DTBR' => ['erp' => 'descuento_bruto', 'exacto' => true, 'nota' => 'factor del concepto si no liquidado en el período'],
        'BCAT' => ['erp' => 'base_categoria', 'exacto' => false, 'nota' => 'base de la categoría'],
        'EMPMAD' => ['erp' => 'es_empresa_madre', 'exacto' => false, 'nota' => 'flag empresa madre'],
        'EASOC' => ['erp' => 'es_asociacion', 'exacto' => false, 'nota' => 'flag asociación'],
        'ICR' => ['erp' => 'im_concepto_rem', 'exacto' => false, 'nota' => 'importe concepto (variante)'],
        'VAL' => ['erp' => 'val', 'exacto' => false, 'nota' => 'placeholder Anita (devuelve 1)'],
    ];

    /**
     * Variables Anita → ERP.
     *
     * tipo: 'var'  => variable con ruta (empleado.x, periodo.x, cantidad, valor)
     *       'acum' => se emite acum("CODIGO")
     *       'crudo'=> texto ERP literal
     * exacto: true si coincide semánticamente.
     *
     * @var array<string, array{tipo: string, erp: string, exacto: bool, nota?: string}>
     */
    public const VARIABLES = [
        // Exactas (existen en ContextoLiquidacion)
        'CATE' => ['tipo' => 'var', 'erp' => 'empleado.categoria_codigo', 'exacto' => true],
        'AGRU' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_codigo', 'exacto' => true],
        'MLIQ' => ['tipo' => 'var', 'erp' => 'periodo.mes', 'exacto' => true],
        'ALIQ' => ['tipo' => 'var', 'erp' => 'periodo.anio', 'exacto' => true],
        'CA' => ['tipo' => 'var', 'erp' => 'cantidad', 'exacto' => true],
        'VA' => ['tipo' => 'var', 'erp' => 'valor', 'exacto' => true],
        'SIND' => ['tipo' => 'var', 'erp' => 'empleado.sindicato_codigo', 'exacto' => true],
        'OSOC' => ['tipo' => 'var', 'erp' => 'empleado.obrasocial_codigo', 'exacto' => true],
        'LUGT' => ['tipo' => 'var', 'erp' => 'empleado.lugartrabajo_codigo', 'exacto' => true],
        'EACT' => ['tipo' => 'var', 'erp' => 'empleado.empresa_codigo', 'exacto' => true],
        'DEGR' => ['tipo' => 'var', 'erp' => 'empleado.dia_egreso', 'exacto' => true, 'nota' => 'día de egreso en el mes de liquidación (0 si no egresa)'],
        'DEGRE' => ['tipo' => 'var', 'erp' => 'empleado.dia_egreso', 'exacto' => true],
        'FLIQ' => ['tipo' => 'var', 'erp' => 'periodo.fecha_liq', 'exacto' => true, 'nota' => 'fecha liquidación YYYYMMDD'],
        'LVAC' => ['tipo' => 'var', 'erp' => 'periodo.tipo_vacaciones', 'exacto' => false, 'nota' => '1 plus / 2 adelanto vacaciones'],
        'FING' => ['tipo' => 'var', 'erp' => 'empleado.fecha_ingreso_n', 'exacto' => false, 'nota' => 'fecha ingreso YYYYMMDD'],
        'FEGR' => ['tipo' => 'var', 'erp' => 'empleado.fecha_egreso_n', 'exacto' => false, 'nota' => 'fecha egreso YYYYMMDD'],
        'MODA' => ['tipo' => 'var', 'erp' => 'empleado.modalidad_sijp', 'exacto' => false, 'nota' => 'modalidad SIJP numérica'],
        'MOBR' => ['tipo' => 'var', 'erp' => 'empleado.mano_obra', 'exacto' => false, 'nota' => 'mano de obra SIJP 1/2/3'],
        'GRRE' => ['tipo' => 'var', 'erp' => 'empleado.grupo_remuneracion', 'exacto' => false],
        'GRDE' => ['tipo' => 'var', 'erp' => 'empleado.grupo_deduccion', 'exacto' => false],
        'GRAP' => ['tipo' => 'var', 'erp' => 'empleado.grupo_aporte', 'exacto' => false],
        // Anita tliq(): frecuencia del legajo (emp_men_quin). Distinto de TLQ.
        'TLIQ' => ['tipo' => 'var', 'erp' => 'empleado.tliq_n', 'exacto' => true, 'nota' => 'frecuencia liq. del empleado (tliq)'],
        // Anita tlq(): tipo de maeliq (1=1Q … 3=mensual_squin … 5=vac … 6/7=SAC … 10=mensual).
        'TLQ' => ['tipo' => 'var', 'erp' => 'periodo.tipo_liq_n', 'exacto' => true, 'nota' => 'tipo corrida maeliq (tlq)'],
        'PLIQ' => ['tipo' => 'var', 'erp' => 'periodo.periodo', 'exacto' => false],

        // Aproximadas (acumuladores del ERP; dependen de definición y orden)
        // Anita p-liquidacion acum_pases: "bruto" / BR = tot_caportes (hab_tipo=1 remunerativo
        // que va al recibo). No incluye no remunerativo (tot_saportes). Ver case 3 % s/bruto.
        'BR' => ['tipo' => 'acum', 'erp' => 'REM', 'exacto' => true, 'nota' => 'bruto con aportes Anita (REM; no rem+norem)'],
        'DE' => ['tipo' => 'acum', 'erp' => 'DESC', 'exacto' => false, 'nota' => 'total descuentos (según acumulador DESC)'],
        'AS' => ['tipo' => 'acum', 'erp' => 'ASIG', 'exacto' => false, 'nota' => 'asignaciones familiares'],
        'TG' => ['tipo' => 'acum', 'erp' => 'NETO', 'exacto' => false, 'nota' => 'total general / neto'],
        'RE' => ['tipo' => 'acum', 'erp' => 'DESC', 'exacto' => false, 'nota' => 'otras deducciones'],
        'SD' => ['tipo' => 'acum', 'erp' => 'APOR', 'exacto' => false, 'nota' => 'total aportes'],
        'AN' => ['tipo' => 'var', 'erp' => 'empleado.antiguedad_anios', 'exacto' => false, 'nota' => 'antigüedad en años'],
        'AT' => ['tipo' => 'var', 'erp' => 'empleado.antiguedad_anios', 'exacto' => false, 'nota' => 'antigüedad (variante)'],
        'AE' => ['tipo' => 'var', 'erp' => 'empleado.antiguedad_anios', 'exacto' => false, 'nota' => 'antigüedad (variante)'],
        'ATM' => ['tipo' => 'var', 'erp' => 'empleado.antiguedad_meses', 'exacto' => false, 'nota' => 'antigüedad en meses'],
        'NDIAS' => ['tipo' => 'var', 'erp' => 'periodo.dias', 'exacto' => false, 'nota' => 'días del período'],
        'DLIQ' => ['tipo' => 'var', 'erp' => 'periodo.dias', 'exacto' => false, 'nota' => 'días de liquidación'],
        'VAG1' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var1', 'exacto' => false],
        'VAG2' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var2', 'exacto' => false],
        'VAG3' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var3', 'exacto' => false],
        'VAG4' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var4', 'exacto' => false],
        'VAGN' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var1', 'exacto' => false, 'nota' => 'alias VAG1'],
        'VN' => ['tipo' => 'var', 'erp' => 'empleado.agrupamiento_var1', 'exacto' => false, 'nota' => 'alias agrupamiento var'],
    ];

    /**
     * Funciones que el ERP ya conoce (builtins de Evaluador + dominio de
     * ContextoLiquidacion). Sirve para marcar qué llamadas quedan "colgadas"
     * (requieren implementar la función de dominio) tras traducir.
     *
     * @var list<string>
     */
    public const ERP_FUNCIONES_CONOCIDAS = [
        // builtins Evaluador
        'redondear', 'truncar', 'abs', 'techo', 'piso', 'raiz', 'potencia',
        'min', 'max', 'entre', 'si', 'if',
        // dominio ContextoLiquidacion
        'concepto', 'im', 'im_rango', 'concepto_rango', 'factor', 'base_num', 'acum', 'param', 'p', 'base', 'antiguedad', 'ant', 'dias',
        'acum_hist', 'mejor_rem_semestre', 'prom_rem_semestre', 'mejor_rem_meses',
        'dias_semestre', 'dias_trabajados_semestre', 'dias_mes', 'dias_trabajados_mes',
        'antiguedad_245', 'antiguedad_meses', 'ganancias', 'ganancia_linea',
        // dominio Anita (stubs / parciales)
        'novedad', 'novedad2', 'novedad_rango', 'novedad_periodo', 'novedad_hist',
        'novedad_empresa', 'novedad2_empresa',
        'dias_trabajados', 'dias_no_trabajados', 'dias_del_mes', 'meses_trabajados',
        'im_liquidacion', 'im_empresa', 'valor_liquidacion', 'cantidad_liquidacion',
        'aux_rango', 'acum_periodos', 'acum_variable_periodos', 'acum_anio', 'mejor_mes_acum',
        'acum_cantidad_concepto', 'acum_valor_concepto', 'acum_importe_concepto', 'acum_importe_por_concepto',
        'aguinaldo', 'cantidad_vacaciones', 'dias_vacaciones', 'total_vacaciones',
        'cantidad_asignacion', 'importe_asignacion', 'tabla_empleado', 'descuento_bruto',
        'base_categoria', 'es_empresa_madre', 'es_asociacion', 'im_concepto_rem', 'val',
        'antiguedad_tabla',
    ];

    public static function funcion(string $nombreAnita): ?array
    {
        return self::FUNCIONES[strtoupper($nombreAnita)] ?? null;
    }

    public static function variable(string $nombreAnita): ?array
    {
        return self::VARIABLES[strtoupper($nombreAnita)] ?? null;
    }

    public static function erpConoceFuncion(string $nombreErp): bool
    {
        return in_array(strtolower($nombreErp), self::ERP_FUNCIONES_CONOCIDAS, true);
    }
}
