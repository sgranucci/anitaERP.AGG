<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Acumulador_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Acumulador_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\ReciboBaseCalculoSupport;
use App\Support\Sueldos\Formula\ContextoLiquidacion;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\Formula\ParametroSueldosResolver;
use App\Support\Sueldos\Formula\RastreadorFormula;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de calculo de una corrida de liquidacion. Recorre la secuencia de
 * conceptos por cada empleado, evalua las formulas con EvaluadorFormula y
 * persiste recibos + detalles. Soporta un modo de rastreo (depurador) que no
 * persiste y devuelve el arbol de calculo por concepto.
 */
class LiquidacionCalculadorService
{
    private EvaluadorFormula $motor;

    private GananciasPuenteLiquidacionService $puenteGanancias;

    private PlanCuotaLiquidacionService $planCuotas;

    private ConceptoSetEfectivoService $setEfectivo;

    private ReciboMultiempresaService $multiempresa;

    /** Mapa tipo de concepto -> columna del recibo. */
    private const COLUMNA = [
        'remunerativo' => 'haber',
        'no_remunerativo' => 'haber',
        'asignacion' => 'haber',
        'descuento' => 'descuento',
        'aporte' => 'descuento',
        'retencion' => 'descuento',
        'neto' => 'neto',
        'informativo' => 'informativo',
        // Contribución empleador: sección CE Anexo III; no suma a bruto/neto.
        'contribucion' => 'contribucion',
    ];

    public function __construct(
        GananciasPuenteLiquidacionService $puenteGanancias,
        PlanCuotaLiquidacionService $planCuotas,
        ConceptoSetEfectivoService $setEfectivo,
        ReciboMultiempresaService $multiempresa
    ) {
        $this->motor = new EvaluadorFormula;
        $this->puenteGanancias = $puenteGanancias;
        $this->planCuotas = $planCuotas;
        $this->setEfectivo = $setEfectivo;
        $this->multiempresa = $multiempresa;
    }

    /**
     * Calcula y persiste la corrida completa. Devuelve un resumen.
     *
     * @return array{recibos: int, total_neto: float, total_remunerativo: float, sin_conceptos: bool}
     */
    public function calcular(Liquidacion_Sueldos $liquidacion): array
    {
        $conceptos = $this->conceptos();
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $empleados = $this->empleados($liquidacion);
        $parametros = $this->parametros($liquidacion);

        if ($conceptos->isEmpty()) {
            return ['recibos' => 0, 'total_neto' => 0.0, 'total_remunerativo' => 0.0, 'sin_conceptos' => true];
        }

        return DB::transaction(function () use ($liquidacion, $conceptos, $acumDefs, $overrides, $empleados, $parametros) {
            // Limpia resultado previo de esta corrida.
            Liquidacion_Detalle_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();
            Liquidacion_Recibo_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();
            Liquidacion_Acumulador_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();

            $periodoAnio = (int) ($liquidacion->periodo_anio ?: now()->year);
            $periodoMes = (int) ($liquidacion->periodo_mes ?: now()->month);
            $periodo = $periodoAnio * 100 + $periodoMes;
            $snapshots = [];
            $ahora = Carbon::now();

            $numeroRecibo = 0;
            $tot = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'neto' => 0.0];
            $planPendientes = [];

            foreach ($empleados as $emp) {
                $numeroRecibo++;
                $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);
                $setInfo = null;
                $conceptosEmp = $this->conceptosParaEmpleado($conceptos, $emp, $liquidacion, $setInfo);
                $lineas = $this->calcularEmpleado(
                    $ctx, $emp, $conceptosEmp, $overrides, $liquidacion, $planPendientes,
                    $setInfo['meta'] ?? []
                );

                $rec = $this->totalesRecibo($lineas);
                $recibo = Liquidacion_Recibo_Sueldos::create([
                    'liquidacion_id' => $liquidacion->id,
                    'empleado_id' => $emp->id,
                    'legajo' => $emp->legajo,
                    'numero_recibo' => $numeroRecibo,
                    'apellido_nombre' => $emp->nombre,
                    'cuil' => $emp->cuil,
                    'categoria_id' => $emp->categoria_id,
                    'categoria_desc' => optional($emp->categoria)->descripcion,
                    'agrupamiento_id' => $emp->agrupamiento_id,
                    'lugartrabajo_id' => $emp->lugartrabajo_id,
                    'obrasocial_id' => $emp->obrasocial_id,
                    'sindicato_id' => $emp->sindicato_id,
                    'fecha_ingreso' => $emp->fecha_ingreso,
                    'sueldo_basico' => $emp->sueldo_basico,
                    'dias_trabajados' => (float) $ctx->variable('periodo.dias_trabajados'),
                    'dias_vacaciones' => 0,
                    'horas' => 0,
                    'total_remunerativo' => $rec['rem'],
                    'total_no_remunerativo' => $rec['norem'],
                    'total_bruto' => $rec['bruto'],
                    'total_descuentos' => $rec['desc'],
                    'total_aportes' => $rec['aportes'],
                    'total_contribuciones' => $rec['contrib'],
                    'total_asignaciones' => $rec['asig'],
                    'neto' => $rec['neto'],
                    'redondeo' => 0,
                    'neto_a_pagar' => $rec['neto'],
                    'estado' => 'calculado',
                ]);

                $nro = 0;
                foreach ($lineas as $l) {
                    $nro++;
                    Liquidacion_Detalle_Sueldos::create([
                        'recibo_id' => $recibo->id,
                        'liquidacion_id' => $liquidacion->id,
                        'empleado_id' => $emp->id,
                        'concepto_id' => $l['concepto_id'],
                        'concepto_codigo' => $l['codigo'],
                        'concepto_descripcion' => $l['descripcion'],
                        'tipo' => $l['tipo'],
                        'nro_linea' => $nro,
                        'columna' => $l['columna'],
                        'cantidad' => $l['cantidad'],
                        'valor' => $l['valor'],
                        'base_calculo' => $l['base_calculo'] ?? null,
                        'importe' => $l['importe'],
                        'remunerativo' => $l['tipo'] === 'remunerativo',
                        'va_recibo' => $l['va_recibo'],
                        'concepto_afip' => $l['concepto_afip'],
                        'leyenda' => $l['leyenda'],
                    ]);
                }

                $tot['rem'] += $rec['rem'];
                $tot['norem'] += $rec['norem'];
                $tot['desc'] += $rec['desc'];
                $tot['neto'] += $rec['neto'];

                // Snapshot de acumuladores para el historico (memoria hacia atras).
                foreach ($ctx->acumuladores() as $cod => $val) {
                    $snapshots[] = [
                        'empresa_id' => $liquidacion->empresa_id,
                        'empleado_id' => $emp->id,
                        'liquidacion_id' => $liquidacion->id,
                        'periodo' => $periodo,
                        'periodo_anio' => $periodoAnio,
                        'periodo_mes' => $periodoMes,
                        'tipo_corrida' => $liquidacion->tipo,
                        'codigo' => $cod,
                        'valor' => round((float) $val, 2),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }

            foreach (array_chunk($snapshots, 500) as $lote) {
                Liquidacion_Acumulador_Sueldos::insert($lote);
            }

            // Cuotas de planes (préstamos) calculadas: quedan pendientes hasta cerrar.
            $this->planCuotas->reemplazarPendientes((int) $liquidacion->id, $planPendientes);

            $liquidacion->update([
                'estado' => 'calculada',
                'fecha_calculo' => Carbon::now(),
                'cantidad_recibos' => $numeroRecibo,
                'total_remunerativo' => $tot['rem'],
                'total_no_remunerativo' => $tot['norem'],
                'total_bruto' => $tot['rem'] + $tot['norem'],
                'total_descuentos' => $tot['desc'],
                'total_neto' => $tot['neto'],
            ]);

            return [
                'recibos' => $numeroRecibo,
                'total_neto' => $tot['neto'],
                'total_remunerativo' => $tot['rem'],
                'sin_conceptos' => false,
            ];
        });
    }

    /**
     * Recorre la secuencia de conceptos para un empleado (sin persistir).
     *
     * @param  \Illuminate\Support\Collection<int, Concepto_Sueldos>  $conceptos
     * @param  array<int, array<string, array{signo: int, excluir: bool}>>  $overrides
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<int, array{origen?: string, detalle?: string, origen_label?: string}>  $origenMeta
     */
    private function calcularEmpleado(
        ContextoLiquidacion $ctx,
        Empleado_Sueldos $emp,
        $conceptos,
        array $overrides = [],
        ?Liquidacion_Sueldos $liquidacion = null,
        ?array &$planPendientes = null,
        array $origenMeta = []
    ): array {
        $lineas = [];
        $gananciasListo = false;
        foreach ($conceptos as $c) {
            if (! $gananciasListo && $liquidacion && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $tieneCantidadExplicita = filled($c->formula_cantidad);
            $tieneValorExplicito = filled($c->formula_valor);
            $cantidad = $tieneCantidadExplicita ? $this->evalNum($ctx, $c->formula_cantidad, $c) : 1.0;
            $valor = $tieneValorExplicito ? $this->evalNum($ctx, $c->formula_valor, $c) : 0.0;
            $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

            $importe = $c->formula
                ? $this->evalNum($ctx, $c->formula, $c)
                : $cantidad * $valor;
            $importe = round($importe, 2);

            $ctx->registrarConcepto((int) $c->codigo, $importe);
            // Contribución empleador / informativo: no alimentan acumuladores de bruto/neto.
            if (! in_array((string) $c->tipo, ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, true)) {
                $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);
            }

            if ($importe == 0.0 && ! $c->va_recibo) {
                continue;
            }
            if ($importe == 0.0 && $c->tipo !== 'informativo' && $c->tipo !== 'contribucion') {
                continue;
            }

            $unidad = ReciboBaseCalculoSupport::normalizarUnidad($c->unidad_medida)
                ?: (ReciboBaseCalculoSupport::inferirUnidad($c->descripcion, $c->factor !== null ? (float) $c->factor : null, (string) $c->tipo) ?? '');
            $tieneValorExplicito = $tieneValorExplicito || abs($valor) > 0.0000001;
            // % sin CA: alícuota desde factor (0.11 → 11) solo para presentar BASE.
            $cantidadBase = (float) $cantidad;
            $cantExplicitaBase = $tieneCantidadExplicita;
            if ($unidad === '%' && ! $tieneCantidadExplicita && $c->factor !== null) {
                $f = abs((float) $c->factor);
                if ($f > 0 && $f < 1) {
                    $cantidadBase = $f * 100.0;
                    $cantExplicitaBase = true;
                } elseif ($f >= 1 && $f <= 100) {
                    $cantidadBase = $f;
                    $cantExplicitaBase = true;
                }
            }
            $base = ReciboBaseCalculoSupport::derivar(
                $importe,
                $cantidadBase,
                (float) $valor,
                $unidad,
                $cantExplicitaBase,
                $tieneValorExplicito,
            );

            $origen = (string) ($origenMeta[$c->id]['origen'] ?? ConceptoElegibilidadCatalogo::ORIGEN_SISTEMA);
            $lineas[] = [
                'concepto_id' => $c->id,
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'tipo' => $c->tipo,
                'columna' => self::COLUMNA[$c->tipo] ?? 'informativo',
                'cantidad' => round($cantidad, 4),
                'valor' => round($valor, 4),
                'base_calculo' => $base,
                'unidad_medida' => $unidad !== '' ? $unidad : null,
                'importe' => $importe,
                'va_recibo' => (bool) $c->va_recibo,
                'concepto_afip' => $c->concepto_afip,
                'leyenda' => $c->leyenda_recibo,
                'origen' => $origen,
                'origen_label' => ConceptoElegibilidadCatalogo::origenLabel($origen),
                'origen_badge' => ConceptoElegibilidadCatalogo::origenBadge($origen),
                'origen_detalle' => (string) ($origenMeta[$c->id]['detalle'] ?? ''),
            ];
        }

        // Planes de cuotas (préstamos): un concepto que se liquida N veces y cae solo.
        if ($liquidacion) {
            foreach ($this->planCuotas->lineasPlan($emp, $liquidacion, $ctx) as $pl) {
                $importe = round((float) $pl['importe'], 2);
                $ctx->registrarConcepto((int) $pl['codigo'], $importe);
                $ctx->aplicarAcumuladores((string) $pl['tipo'], $importe, []);

                $lineas[] = [
                    'concepto_id' => $pl['concepto_id'],
                    'codigo' => $pl['codigo'],
                    'descripcion' => $pl['descripcion'],
                    'tipo' => $pl['tipo'],
                    'columna' => self::COLUMNA[$pl['tipo']] ?? 'informativo',
                    'cantidad' => 1.0,
                    'valor' => $importe,
                    'base_calculo' => ReciboBaseCalculoSupport::derivar(
                        $importe,
                        1.0,
                        $importe,
                        null,
                        false,
                        true,
                    ),
                    'unidad_medida' => null,
                    'importe' => $importe,
                    'va_recibo' => $pl['va_recibo'],
                    'concepto_afip' => $pl['concepto_afip'],
                    'leyenda' => $pl['leyenda'],
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'origen_badge' => ConceptoElegibilidadCatalogo::origenBadge(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'origen_detalle' => (string) ($pl['leyenda'] ?? 'Plan de cuotas del legajo'),
                ];

                if ($planPendientes !== null) {
                    $planPendientes[] = [
                        'plan_id' => $pl['plan_id'],
                        'numero_cuota' => $pl['numero_cuota'],
                        'periodo' => $pl['periodo'],
                        'importe' => $importe,
                        'empleado_id' => (int) $emp->id,
                    ];
                }
            }
        }

        return $lineas;
    }

    /**
     * Preview de recibo para un empleado (no persiste).
     * Usa corrida real del mismo período/tipo si existe; si no, liquidación virtual.
     *
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   totales: array{haber: float, descuento: float, contribucion: float, neto: float, cantidad: int},
     *   periodo: int,
     *   tipo: string,
     *   liquidacion_id: int|null,
     *   liquidacion_label: string,
     *   errores: list<string>
     * }
     */
    public function simularEmpleado(Empleado_Sueldos $emp, string $periodoInput, string $tipo = 'mensual'): array
    {
        [$periodoYm, $anio, $mes] = $this->parsePeriodoInput($periodoInput);
        if (! isset(Liquidacion_Sueldos::TIPOS[$tipo])) {
            $tipo = 'mensual';
        }

        $liqReal = Liquidacion_Sueldos::query()
            ->where('empresa_id', $emp->empresa_id)
            ->where('periodo', $periodoYm)
            ->where('tipo', $tipo)
            ->whereIn('estado', array_merge(Liquidacion_Sueldos::ESTADOS_EDITABLES, ['cerrada', 'contabilizada', 'pagada']))
            ->orderByRaw("FIELD(estado,'borrador','calculada','revisada','cerrada','contabilizada','pagada')")
            ->orderByDesc('numero')
            ->first();

        $liq = $liqReal ?? $this->liquidacionVirtual($emp, $periodoYm, $anio, $mes, $tipo);

        $setInfo = null;
        $conceptos = $this->conceptosParaEmpleado($this->conceptos(), $emp, $liq, $setInfo);
        $acumDefs = $this->acumuladoresDef($liq->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $parametros = $this->parametros($liq);
        $ctx = new ContextoLiquidacion($emp, $liq, $acumDefs, $parametros);

        $errores = [];
        $lineas = [];
        try {
            $lineas = $this->calcularEmpleado(
                $ctx, $emp, $conceptos, $overrides, $liq, null,
                $setInfo['meta'] ?? []
            );
        } catch (FormulaException $e) {
            $errores[] = $e->getMessage();
        }

        $totales = ['haber' => 0.0, 'descuento' => 0.0, 'contribucion' => 0.0, 'neto' => 0.0, 'cantidad' => count($lineas)];
        foreach ($lineas as $l) {
            $col = $l['columna'] ?? 'informativo';
            $imp = (float) ($l['importe'] ?? 0);
            if ($col === 'haber') {
                $totales['haber'] += $imp;
            } elseif ($col === 'descuento') {
                $totales['descuento'] += $imp;
            } elseif ($col === 'contribucion' || ($l['tipo'] ?? '') === 'contribucion') {
                $totales['contribucion'] += $imp;
            } elseif ($col === 'neto') {
                $totales['neto'] += $imp;
            }
        }
        if ($totales['neto'] == 0.0) {
            $totales['neto'] = $totales['haber'] - $totales['descuento'];
        }

        $label = $liqReal
            ? 'Corrida N° '.$liqReal->numero.' · '.$liqReal->descripcion.' ('.$liqReal->estado.')'
            : 'Simulación (sin corrida abierta para este período)';

        return [
            'lineas' => $lineas,
            'totales' => $totales,
            'periodo' => $periodoYm,
            'tipo' => $tipo,
            'liquidacion_id' => $liqReal ? (int) $liqReal->id : null,
            'liquidacion_label' => $label,
            'errores' => $errores,
            'set_efectivo' => [
                'modo' => $setInfo['modo'] ?? null,
                'modo_label' => $setInfo['modo_label'] ?? null,
                'grupos' => $setInfo['grupos'] ?? [],
                'cantidad_conceptos' => isset($setInfo['conceptos']) ? $setInfo['conceptos']->count() : 0,
                'cantidad_excluidos' => count($setInfo['excluidos'] ?? []),
                'excluidos' => $setInfo['excluidos'] ?? [],
                'meta' => $setInfo['meta'] ?? [],
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int} periodoYm, anio, mes
     */
    private function parsePeriodoInput(string $periodoInput): array
    {
        $periodoInput = trim($periodoInput);
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodoInput, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
        } elseif (preg_match('/^(\d{6})$/', $periodoInput, $m)) {
            $anio = (int) substr($m[1], 0, 4);
            $mes = (int) substr($m[1], 4, 2);
        } else {
            $now = Carbon::now();
            $anio = (int) $now->year;
            $mes = (int) $now->month;
        }
        if ($mes < 1 || $mes > 12) {
            $mes = 1;
        }

        return [$anio * 100 + $mes, $anio, $mes];
    }

    private function liquidacionVirtual(
        Empleado_Sueldos $emp,
        int $periodoYm,
        int $anio,
        int $mes,
        string $tipo
    ): Liquidacion_Sueldos {
        $ini = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $ini->copy()->endOfMonth();
        $liq = new Liquidacion_Sueldos([
            'empresa_id' => $emp->empresa_id,
            'numero' => 0,
            'descripcion' => 'Simulación preview',
            'tipo' => $tipo,
            'periodo' => (string) $periodoYm,
            'periodo_anio' => $anio,
            'periodo_mes' => $mes,
            'periodo_desde' => $ini->toDateString(),
            'periodo_hasta' => $fin->toDateString(),
            'fecha_liquidacion' => $fin->toDateString(),
            'estado' => 'borrador',
            'simulacion' => true,
            'acumula_novedades' => true,
        ]);
        $liq->id = 0;

        return $liq;
    }

    /**
     * Modo depurador legacy (vista trazar de corrida): solo pasos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trazarEmpleado(Liquidacion_Sueldos $liquidacion, Empleado_Sueldos $emp): array
    {
        return $this->depurarSobreLiquidacion($emp, $liquidacion)['pasos'];
    }

    /**
     * Debugger de fórmulas: preview por período/tipo (con o sin corrida abierta).
     *
     * @param  array{formula?: ?string, formula_cantidad?: ?string, formula_valor?: ?string}|null  $overridesFormula
     * @return array<string, mixed>
     */
    public function depurarEmpleado(
        Empleado_Sueldos $emp,
        string $periodoInput,
        string $tipo = 'mensual',
        ?int $soloCodigo = null,
        ?array $overridesFormula = null,
        bool $incluirContexto = true
    ): array {
        [$periodoYm, $anio, $mes] = $this->parsePeriodoInput($periodoInput);
        if (! isset(Liquidacion_Sueldos::TIPOS[$tipo])) {
            $tipo = 'mensual';
        }

        $liqReal = Liquidacion_Sueldos::query()
            ->where('empresa_id', $emp->empresa_id)
            ->where('periodo', $periodoYm)
            ->where('tipo', $tipo)
            ->whereIn('estado', array_merge(Liquidacion_Sueldos::ESTADOS_EDITABLES, ['cerrada', 'contabilizada', 'pagada']))
            ->orderByRaw("FIELD(estado,'borrador','calculada','revisada','cerrada','contabilizada','pagada')")
            ->orderByDesc('numero')
            ->first();

        $liq = $liqReal ?? $this->liquidacionVirtual($emp, $periodoYm, $anio, $mes, $tipo);

        return $this->depurarSobreLiquidacion(
            $emp,
            $liq,
            $soloCodigo,
            $overridesFormula,
            $incluirContexto,
            $liqReal ? 'Corrida N° '.$liqReal->numero.' · '.$liqReal->descripcion : 'Simulación (sin corrida)'
        );
    }

    /**
     * Valida sintaxis de una fórmula (sin evaluar).
     */
    public function validarFormula(string $formula): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return ['ok' => true, 'mensaje' => 'Fórmula vacía (se usará cantidad × valor).'];
        }
        $err = $this->motor->validar($formula);

        return $err === null
            ? ['ok' => true, 'mensaje' => 'Sintaxis OK']
            : ['ok' => false, 'mensaje' => $err];
    }

    /**
     * @param  array{formula?: ?string, formula_cantidad?: ?string, formula_valor?: ?string}|null  $overridesFormula
     * @return array<string, mixed>
     */
    private function depurarSobreLiquidacion(
        Empleado_Sueldos $emp,
        Liquidacion_Sueldos $liquidacion,
        ?int $soloCodigo = null,
        ?array $overridesFormula = null,
        bool $incluirContexto = true,
        string $liquidacionLabel = ''
    ): array {
        $setInfo = null;
        $conceptos = $this->conceptosParaEmpleado($this->conceptos(), $emp, $liquidacion, $setInfo);
        $origenMeta = $setInfo['meta'] ?? [];
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $parametros = $this->parametros($liquidacion);
        $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);
        $contextoInicial = $incluirContexto ? $ctx->snapshotDebug() : null;

        $pasos = [];
        $gananciasListo = false;
        $focusEncontrado = false;

        foreach ($conceptos as $c) {
            $codigo = (int) $c->codigo;
            $esFocus = $soloCodigo === null || $codigo === $soloCodigo;

            if (! $gananciasListo && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $fCant = (string) ($c->formula_cantidad ?? '');
            $fValor = (string) ($c->formula_valor ?? '');
            $fImp = (string) ($c->formula ?? '');
            if ($esFocus && is_array($overridesFormula)) {
                if (array_key_exists('formula_cantidad', $overridesFormula) && $overridesFormula['formula_cantidad'] !== null) {
                    $fCant = (string) $overridesFormula['formula_cantidad'];
                }
                if (array_key_exists('formula_valor', $overridesFormula) && $overridesFormula['formula_valor'] !== null) {
                    $fValor = (string) $overridesFormula['formula_valor'];
                }
                if (array_key_exists('formula', $overridesFormula) && $overridesFormula['formula'] !== null) {
                    $fImp = (string) $overridesFormula['formula'];
                }
            }

            $rastroCant = null;
            $rastroValor = null;
            $rastro = null;
            $error = null;

            try {
                if ($fCant !== '') {
                    if ($esFocus) {
                        [$cantidad, $rastroCant] = $this->motor->evaluarConRastro($fCant, $ctx);
                        $cantidad = (float) $cantidad;
                    } else {
                        $cantidad = (float) $this->motor->evaluar($fCant, $ctx);
                    }
                } else {
                    $cantidad = 1.0;
                }

                if ($fValor !== '') {
                    if ($esFocus) {
                        [$valor, $rastroValor] = $this->motor->evaluarConRastro($fValor, $ctx);
                        $valor = (float) $valor;
                    } else {
                        $valor = (float) $this->motor->evaluar($fValor, $ctx);
                    }
                } else {
                    $valor = 0.0;
                }

                $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

                if ($fImp !== '') {
                    if ($esFocus) {
                        [$importe, $rastro] = $this->motor->evaluarConRastro($fImp, $ctx);
                        $importe = round((float) $importe, 2);
                    } else {
                        $importe = round((float) $this->motor->evaluar($fImp, $ctx), 2);
                    }
                } else {
                    $importe = round($cantidad * $valor, 2);
                }
            } catch (FormulaException $e) {
                $error = $e->getMessage();
                $cantidad = $cantidad ?? 0.0;
                $valor = $valor ?? 0.0;
                $importe = 0.0;
            }

            if ($error === null) {
                $ctx->registrarConcepto($codigo, $importe);
                if (! in_array((string) $c->tipo, ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, true)) {
                    $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);
                }
            }

            if ($esFocus) {
                $focusEncontrado = true;
                $origen = (string) ($origenMeta[$c->id]['origen'] ?? ConceptoElegibilidadCatalogo::ORIGEN_SISTEMA);
                $pasos[] = [
                    'concepto_id' => (int) $c->id,
                    'codigo' => $codigo,
                    'descripcion' => $c->descripcion,
                    'tipo' => $c->tipo,
                    'formula' => $fImp !== '' ? $fImp : null,
                    'formula_cantidad' => $fCant !== '' ? $fCant : null,
                    'formula_valor' => $fValor !== '' ? $fValor : null,
                    'formula_override' => $esFocus && is_array($overridesFormula),
                    'cantidad' => round((float) $cantidad, 4),
                    'valor' => round((float) $valor, 4),
                    'importe' => $importe,
                    'error' => $error,
                    'rastro' => $rastro instanceof RastreadorFormula ? $rastro->arbol() : [],
                    'rastro_texto' => $rastro instanceof RastreadorFormula ? $rastro->texto() : '',
                    'rastro_cantidad' => $rastroCant instanceof RastreadorFormula ? $rastroCant->arbol() : [],
                    'rastro_valor' => $rastroValor instanceof RastreadorFormula ? $rastroValor->arbol() : [],
                    'acumuladores' => $ctx->acumuladores(),
                    'contexto_tras' => $incluirContexto ? $ctx->snapshotDebug() : null,
                    'origen' => $origen,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel($origen),
                    'en_set' => true,
                ];

                // Si filtramos un solo código, seguir calculando silenciosamente no hace falta
                // para el resto del set… salvo que el usuario quiera ver acumuladores previos.
                // Calculamos todos para que Iconcepto/acum previos existan; solo devolvemos focus.
                if ($soloCodigo !== null && $error !== null) {
                    // con error igual seguimos registrando 0? ya no registramos — ok
                }
            } elseif ($error !== null) {
                // Concepto previo falló: corta la cadena (igual que liquidar).
                break;
            }
        }

        if ($soloCodigo === null) {
            foreach ($this->planCuotas->lineasPlan($emp, $liquidacion, $ctx) as $pl) {
                $importe = round((float) $pl['importe'], 2);
                $ctx->registrarConcepto((int) $pl['codigo'], $importe);
                $ctx->aplicarAcumuladores((string) $pl['tipo'], $importe, []);
                $pasos[] = [
                    'concepto_id' => (int) ($pl['concepto_id'] ?? 0),
                    'codigo' => $pl['codigo'],
                    'descripcion' => $pl['descripcion'],
                    'tipo' => $pl['tipo'],
                    'formula' => '(plan de cuotas) '.$pl['leyenda'],
                    'formula_cantidad' => null,
                    'formula_valor' => null,
                    'formula_override' => false,
                    'cantidad' => 1.0,
                    'valor' => $importe,
                    'importe' => $importe,
                    'error' => null,
                    'rastro' => [],
                    'rastro_texto' => $pl['leyenda'],
                    'rastro_cantidad' => [],
                    'rastro_valor' => [],
                    'acumuladores' => $ctx->acumuladores(),
                    'contexto_tras' => null,
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'en_set' => true,
                ];
            }
        } elseif (! $focusEncontrado && $soloCodigo !== null) {
            // Concepto fuera del set: evaluar igual en sandbox (útil al programar en ABM).
            $c = Concepto_Sueldos::query()->where('codigo', $soloCodigo)->where('activo', true)->first();
            if ($c) {
                $fCant = (string) ($overridesFormula['formula_cantidad'] ?? $c->formula_cantidad ?? '');
                $fValor = (string) ($overridesFormula['formula_valor'] ?? $c->formula_valor ?? '');
                $fImp = (string) ($overridesFormula['formula'] ?? $c->formula ?? '');
                try {
                    $cantidad = $fCant !== '' ? (float) $this->motor->evaluar($fCant, $ctx) : 1.0;
                    $valor = $fValor !== '' ? (float) $this->motor->evaluar($fValor, $ctx) : 0.0;
                    $ctx->setEscalares($cantidad, $valor, (float) $c->factor);
                    $rastro = null;
                    if ($fImp !== '') {
                        [$importe, $rastro] = $this->motor->evaluarConRastro($fImp, $ctx);
                        $importe = round((float) $importe, 2);
                    } else {
                        $importe = round($cantidad * $valor, 2);
                    }
                    $pasos[] = [
                        'concepto_id' => (int) $c->id,
                        'codigo' => (int) $c->codigo,
                        'descripcion' => $c->descripcion,
                        'tipo' => $c->tipo,
                        'formula' => $fImp !== '' ? $fImp : null,
                        'formula_cantidad' => $fCant !== '' ? $fCant : null,
                        'formula_valor' => $fValor !== '' ? $fValor : null,
                        'formula_override' => is_array($overridesFormula),
                        'cantidad' => round($cantidad, 4),
                        'valor' => round($valor, 4),
                        'importe' => $importe,
                        'error' => null,
                        'rastro' => $rastro instanceof RastreadorFormula ? $rastro->arbol() : [],
                        'rastro_texto' => $rastro instanceof RastreadorFormula ? $rastro->texto() : '',
                        'rastro_cantidad' => [],
                        'rastro_valor' => [],
                        'acumuladores' => $ctx->acumuladores(),
                        'contexto_tras' => $incluirContexto ? $ctx->snapshotDebug() : null,
                        'origen' => 'fuera_set',
                        'origen_label' => 'Fuera del set',
                        'en_set' => false,
                        'aviso' => 'Este concepto no está en el set efectivo del legajo; se evaluó igual para depurar.',
                    ];
                } catch (FormulaException $e) {
                    $pasos[] = array_merge($this->pasoError($c, $e), [
                        'en_set' => false,
                        'origen' => 'fuera_set',
                        'origen_label' => 'Fuera del set',
                        'aviso' => 'Concepto fuera del set; falló al evaluar en sandbox.',
                    ]);
                }
            }
        }

        return [
            'pasos' => $pasos,
            'periodo' => (int) ($liquidacion->periodo ?: 0),
            'tipo' => (string) $liquidacion->tipo,
            'liquidacion_id' => (int) ($liquidacion->id ?? 0) ?: null,
            'liquidacion_label' => $liquidacionLabel,
            'empleado' => [
                'id' => (int) $emp->id,
                'legajo' => (int) $emp->legajo,
                'nombre' => (string) $emp->nombre,
                'empresa_id' => (int) $emp->empresa_id,
            ],
            'set_efectivo' => [
                'modo' => $setInfo['modo'] ?? null,
                'modo_label' => $setInfo['modo_label'] ?? null,
                'cantidad_conceptos' => isset($setInfo['conceptos']) ? $setInfo['conceptos']->count() : 0,
                'cantidad_excluidos' => count($setInfo['excluidos'] ?? []),
            ],
            'contexto_inicial' => $contextoInicial,
            'contexto_final' => $incluirContexto ? $ctx->snapshotDebug() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pasoError(Concepto_Sueldos $c, FormulaException $e): array
    {
        return [
            'codigo' => $c->codigo,
            'descripcion' => $c->descripcion,
            'tipo' => $c->tipo,
            'formula' => $c->formula,
            'cantidad' => 0,
            'valor' => 0,
            'importe' => 0,
            'error' => $e->getMessage(),
            'rastro' => [],
            'rastro_texto' => '',
            'acumuladores' => [],
        ];
    }

    private function evalNum(ContextoLiquidacion $ctx, string $formula, Concepto_Sueldos $c): float
    {
        try {
            return (float) $this->motor->evaluar($formula, $ctx);
        } catch (FormulaException $e) {
            throw FormulaException::evaluacion(
                "Concepto {$c->codigo} ({$c->descripcion}): ".$e->getMessage()
            );
        }
    }

    private function formulaUsaGanancias(Concepto_Sueldos $c): bool
    {
        $blob = strtolower(
            ($c->formula ?? '').' '.($c->formula_cantidad ?? '').' '.($c->formula_valor ?? '')
        );

        return str_contains($blob, 'ganancias(') || str_contains($blob, 'ganancia_linea(');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<string, float>
     */
    private function totalesRecibo(array $lineas): array
    {
        $t = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'aportes' => 0.0, 'contrib' => 0.0, 'asig' => 0.0];
        foreach ($lineas as $l) {
            switch ($l['tipo']) {
                case 'remunerativo': $t['rem'] += $l['importe']; break;
                case 'no_remunerativo': $t['norem'] += $l['importe']; break;
                case 'asignacion': $t['asig'] += $l['importe']; break;
                case 'descuento': $t['desc'] += $l['importe']; break;
                case 'aporte': $t['desc'] += $l['importe']; $t['aportes'] += $l['importe']; break;
                case 'retencion': $t['desc'] += $l['importe']; break;
                case 'contribucion': $t['contrib'] += $l['importe']; break;
            }
        }
        $t['bruto'] = $t['rem'] + $t['norem'];
        $t['neto'] = round($t['rem'] + $t['norem'] + $t['asig'] - $t['desc'], 2);

        return $t;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Concepto_Sueldos>
     */
    private function conceptos()
    {
        return Concepto_Sueldos::query()
            ->where('activo', true)
            ->where('momento', '!=', 'no_liquida')
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Filtra el catálogo global al set efectivo del legajo
     * (grupos + elegibilidad + novedades en modo grupos + explícitos).
     *
     * @param  \Illuminate\Support\Collection<int, Concepto_Sueldos>  $todos
     * @param  array<string, mixed>|null  $setInfo  salida del resolver (meta, modo, …)
     * @return \Illuminate\Support\Collection<int, Concepto_Sueldos>
     */
    private function conceptosParaEmpleado($todos, Empleado_Sueldos $emp, Liquidacion_Sueldos $liq, ?array &$setInfo = null)
    {
        $setInfo = $this->setEfectivo->resolver($emp, $liq);
        $ids = $setInfo['conceptos']->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return $todos->take(0);
        }
        $flip = array_flip($ids);

        return $todos->filter(fn ($c) => isset($flip[(int) $c->id]))->values();
    }

    /**
     * @return array<int, array{codigo: string, tipos: array<int,string>, signo: int}>
     */
    private function acumuladoresDef(?int $empresaId): array
    {
        $defs = Acumulador_Sueldos::query()
            ->where('activo', true)
            ->where(function ($q) use ($empresaId) {
                $q->whereNull('empresa_id');
                if ($empresaId) {
                    $q->orWhere('empresa_id', $empresaId);
                }
            })
            ->orderBy('orden')
            ->get();

        return $defs->map(fn ($a) => [
            'codigo' => (string) $a->codigo,
            'tipos' => $a->tipos_incluye ?? [],
            'signo' => (int) $a->signo,
        ])->all();
    }

    /**
     * Overrides concepto->acumulador: [concepto_id => ['ACUMCOD' => ['signo','excluir']]].
     *
     * @return array<int, array<string, array{signo: int, excluir: bool}>>
     */
    private function overridesConceptoAcumulador(): array
    {
        $map = [];
        try {
            $filas = DB::table('concepto_acumulador_sueldos as ca')
                ->join('acumulador_sueldos as a', 'a.id', '=', 'ca.acumulador_id')
                ->get(['ca.concepto_id', 'a.codigo', 'ca.signo', 'ca.excluir']);
            foreach ($filas as $f) {
                $map[(int) $f->concepto_id][strtoupper((string) $f->codigo)] = [
                    'signo' => (int) $f->signo,
                    'excluir' => (bool) $f->excluir,
                ];
            }
        } catch (\Throwable $e) {
            // Tabla aun no migrada: sin overrides.
        }

        return $map;
    }

    private function parametros(Liquidacion_Sueldos $liq): ParametroSueldosResolver
    {
        $fecha = $liq->fecha_liquidacion
            ? ($liq->fecha_liquidacion instanceof Carbon ? $liq->fecha_liquidacion : Carbon::parse($liq->fecha_liquidacion))
            : Carbon::now();

        return new ParametroSueldosResolver($liq->empresa_id, $fecha->toDateString());
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empleado_Sueldos>
     */
    private function empleados(Liquidacion_Sueldos $liq)
    {
        $query = Empleado_Sueldos::query()
            ->where('empresa_id', $liq->empresa_id)
            ->with(['categoria', 'motivoegreso'])
            ->orderBy('legajo');

        // Liquidacion final: empleados dados de baja (o con fecha de egreso) en el periodo.
        // El resto de corridas: solo activos.
        if ($liq->tipo === 'final') {
            $query->where(function ($q) use ($liq) {
                $q->where('estado', EmpleadoEstados::BAJA)
                    ->orWhereNotNull('fecha_egreso');
            });
            if ($liq->periodo_desde && $liq->periodo_hasta) {
                $query->whereBetween('fecha_egreso', [$liq->periodo_desde, $liq->periodo_hasta]);
            } elseif ($liq->periodo_anio && $liq->periodo_mes) {
                $query->whereYear('fecha_egreso', (int) $liq->periodo_anio)
                    ->whereMonth('fecha_egreso', (int) $liq->periodo_mes);
            }
            if ($liq->motivoegreso_id) {
                $query->where('motivoegreso_id', (int) $liq->motivoegreso_id);
            }
        } else {
            $query->where('estado', EmpleadoEstados::ACTIVO);
        }

        $filtros = $this->filtros($liq);
        if (! empty($filtros['empleado_ids']) && is_array($filtros['empleado_ids'])) {
            $query->whereIn('id', $filtros['empleado_ids']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $query->where('agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }

        $this->multiempresa->aplicarAlcanceAlQueryEmpleados($query, $liq);

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Liquidacion_Sueldos $liq): array
    {
        if (empty($liq->filtros_json)) {
            return [];
        }
        if (is_array($liq->filtros_json)) {
            return $liq->filtros_json;
        }
        $dec = json_decode((string) $liq->filtros_json, true);

        return is_array($dec) ? $dec : [];
    }
}
